<?php
declare(strict_types=1);

/* ==========================================================================
   4.33.0 — Tezgâh (özellik: kagitsiz). Müşteri reçeteyi verir → e-reçete no OptiFlow'a yazılır →
   OptiFlow Pro Medula'da sorguyu doldurur → personel Medula'da kontrol edip "Aktar"a basar →
   tek ekranda müşteri, cam + stok, çerçeve, tutar + kapora → kaydet → yarım sayfa fiş + müşteri nüshası.
   Sayfa: app/pages/tezgah.php · JS: assets/tezgah.js · Pro: desktop/src/medula/sorgu.ts

   4.34.0 — Tek akış (kullanıcı kararı 16.10): ① Reçete (Medula) → ② Müşteri → ③ Teklif (atlanabilir) →
   ④ Siparişe çevir → ⑤ Döküm + gönder (stoktaki camlar atölyeye, eksikler gün sonu depo listesine).
   ② reçeteyi sipariş açmadan "bekleyen reçete" olarak müşteriye bağlar (bekleyen_receteler); teklif
   quotes.recete_id ile taşır; order-new.php siparişe çevirirken recete_siparise_uygula() ile yazar.
   ========================================================================== */

/** Akışın adımları. */
function akis_adimlari(): array
{
    return [1 => 'Reçete', 2 => 'Müşteri', 3 => 'Teklif', 4 => 'Sipariş', 5 => 'Döküm ve gönder'];
}

/** Sayfanın üstündeki adım çubuğu. $atlanan: atlanmış adımlar (ör. teklifsiz siparişte 3). */
function akis_cubugu(int $aktif, array $atlanan = []): string
{
    $h = '<ol class="akis-cubugu" aria-label="Satış akışı">';
    foreach (akis_adimlari() as $no => $ad) {
        $sinif = in_array($no, $atlanan, true) ? 'atlandi' : ($no < $aktif ? 'bitti' : ($no === $aktif ? 'simdi' : ''));
        $h .= '<li class="' . $sinif . '"' . ($no === $aktif ? ' aria-current="step"' : '') . '><span class="akis-no">' . $no . '</span><span class="akis-ad">' . e($ad) . '</span></li>';
    }
    return $h . '</ol>';
}

/**
 * Bekleyen reçeteyi kaydeder. $rx: rx_from_post() verisi (cam tipi siparişte seçilir), $near: yakın değerler.
 * T.C. kimlik no hiçbir zaman buraya gelmez (rx_from_post alanları).
 */
function recete_bekleyen_kaydet(int $customerId, array $rx, ?array $near, string $erecete, int $gelenId, int $uid): int
{
    unset($rx['lens_type'], $rx['advisor_summary'], $rx['advisor_product_id']);
    if ($near !== null) {
        unset($near['lens_type']);
    }
    return insert('bekleyen_receteler', [
        'customer_id' => $customerId,
        'gelen_id'    => $gelenId > 0 ? $gelenId : null,
        'erecete'     => $erecete !== '' ? $erecete : null,
        'veri'        => json_encode(['rx' => $rx, 'near' => $near], JSON_UNESCAPED_UNICODE),
        'created_by'  => $uid,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}

/** Bekleyen reçete (çözülmüş): id, customer_id, gelen_id, erecete, rx, near, used_order_id, created_at. */
function recete_bekleyen(int $id): ?array
{
    $r = $id > 0 ? row('SELECT * FROM bekleyen_receteler WHERE id = ?', [$id]) : null;
    if (!$r) {
        return null;
    }
    $v = json_decode((string) $r['veri'], true);
    $v = is_array($v) ? $v : [];
    return [
        'id'            => (int) $r['id'],
        'customer_id'   => (int) $r['customer_id'],
        'gelen_id'      => (int) ($r['gelen_id'] ?? 0),
        'erecete'       => (string) ($r['erecete'] ?? ''),
        'rx'            => is_array($v['rx'] ?? null) ? $v['rx'] : [],
        'near'          => is_array($v['near'] ?? null) ? $v['near'] : null,
        'used_order_id' => (int) ($r['used_order_id'] ?? 0),
        'created_at'    => (string) $r['created_at'],
    ];
}

/** Müşterinin son 30 günde kaydedilmiş, henüz siparişe yazılmamış reçetesi. */
function recete_son_bekleyen(int $customerId): ?array
{
    $sinir = date('Y-m-d H:i:s', time() - 30 * 86400);
    $id = (int) scalar('SELECT id FROM bekleyen_receteler WHERE customer_id = ? AND used_order_id IS NULL AND created_at >= ? ORDER BY id DESC LIMIT 1', [$customerId, $sinir]);
    return $id > 0 ? recete_bekleyen($id) : null;
}

/** Tek satırlık özet: "Sağ −2.00 −0.50 × 90° · Sol … · PD 62". */
function recete_ozet(array $r): string
{
    $rx = $r['rx'] ?? [];
    $p = ['Sağ ' . rx_line($rx, 'right'), 'Sol ' . rx_line($rx, 'left')];
    if (($rx['pd'] ?? '') !== '') {
        $p[] = 'PD ' . $rx['pd'];
    }
    return implode(' · ', $p);
}

/**
 * Reçeteyi bir gözlüğün kullanım şekline uyarlar (ör. ayrı uzak + yakın reçetenin "yakın" gözlüğü).
 * Döner: [rx, near] — rx_from_post() biçiminde, sync_lens_items() için.
 */
function recete_kullanim_icin(array $r, string $design): array
{
    $rx = $r['rx'] ?? [];
    $near = $r['near'] ?? null;
    if ($design === '' || !isset(lens_designs()[$design])) {
        $design = (string) ($rx['lens_design'] ?? 'tek_odak_uzak');
    }
    $rx['lens_design'] = $design;
    if (in_array($design, ['ayri_uzak_yakin', 'tek_odak_yakin'], true)) {
        $hazir = is_array($near) ? $near : [];
        $near = ['usage_type' => $design, 'lens_type' => ''];
        foreach (['right', 'left'] as $t) {
            $near[$t . '_sph'] = ($hazir[$t . '_sph'] ?? '') !== '' ? $hazir[$t . '_sph'] : near_sphere((string) ($rx[$t . '_sph'] ?? ''), (string) ($rx[$t . '_add'] ?? ''));
            $near[$t . '_cyl'] = (string) ($rx[$t . '_cyl'] ?? '');
            $near[$t . '_axis'] = (string) ($rx[$t . '_axis'] ?? '');
        }
    } else {
        $near = null;
    }
    return [$rx, $near];
}

/**
 * ④ Bekleyen reçeteyi yeni siparişe yazar: reçete + yakın + cam satırları (stok durumuyla), e-reçete no,
 * SGK katkısı; ⑤ stoktaki camlarla sipariş atölyeye geçer, eksik camlar gün sonu depo listesinde kalır.
 * $design: gözlüğün kullanım şekli (teklif gözlüğü); boşsa reçetedeki. $stock: ['uzak' => …, 'yakin' => …].
 * Aynı reçete çok gözlüklü teklifte birden çok siparişe yazılabilir; ilk sipariş "kullanıldı" olarak işlenir.
 */
function recete_siparise_uygula(int $receteId, int $orderId, string $design, string $lensType, string $nearLensType, array $stock, int $uid): array
{
    $r = recete_bekleyen($receteId);
    $order = $r ? row('SELECT id, customer_id, order_stage FROM orders WHERE id = ?', [$orderId]) : null;
    if (!$r || !$order) {
        throw new DomainException('Reçete ya da sipariş bulunamadı.');
    }
    if ((int) $order['customer_id'] !== $r['customer_id']) {
        throw new DomainException('Reçete bu siparişin müşterisine ait değil.');
    }
    [$rx, $near] = recete_kullanim_icin($r, $design);
    $rx['lens_type'] = $lensType;
    if ($near !== null) {
        $near['lens_type'] = $nearLensType !== '' ? $nearLensType : $lensType;
    }
    $simdi = date('Y-m-d H:i:s');
    $rxId = insert('prescription_records', $rx + [
        'order_id'         => $orderId,
        'customer_id'      => $r['customer_id'],
        'sgk_rapor_no'     => $r['erecete'] !== '' ? $r['erecete'] : null,
        'sgk_rapor_tarihi' => $r['erecete'] !== '' ? ($rx['prescription_date'] ?? null) : null,
        'created_by'       => $uid,
        'created_at'       => $simdi,
    ]);
    if ($near !== null) {
        insert('near_prescription_details', $near + ['prescription_id' => $rxId]);
    }
    sync_lens_items($rxId, $rx, $near, $stock);
    q('UPDATE orders SET lens_type = ?, sgk_erecete = ? WHERE id = ?', [$lensType, $r['erecete'] !== '' ? $r['erecete'] : null, $orderId]);
    if ($r['used_order_id'] === 0) {
        q('UPDATE bekleyen_receteler SET used_order_id = ?, used_at = ? WHERE id = ?', [$orderId, $simdi, $receteId]);
        if ($r['gelen_id'] > 0) {
            q('UPDATE sgk_incoming SET used_order_id = ?, used_at = ? WHERE id = ? AND used_order_id IS NULL', [$orderId, $simdi, $r['gelen_id']]);
        }
    }
    audit('rx_create', 'order', $orderId, ['reçete' => $rxId, 'sağ' => rx_line($rx, 'right'), 'sol' => rx_line($rx, 'left'), 'cam' => $lensType, 'kaynak' => 'bekleyen reçete']);
    $sgk = $r['erecete'] !== '' ? sgk_katki_uygula($orderId, $r['customer_id'], $rx) : ['tutar' => 0.0, 'uyari' => null, 'korundu' => false];

    $eksik = (int) scalar("SELECT COUNT(*) FROM prescription_lens_items WHERE prescription_id = ? AND stock_status <> 'stokta_var'", [$rxId]);
    $atolye = false;
    if ($eksik === 0 && $order['order_stage'] === 'siparis_verildi') {
        q("UPDATE orders SET order_stage = 'atolyede', status = 'atolyede', updated_at = ? WHERE id = ?", [$simdi, $orderId]);
        audit('order_stage', 'order', $orderId, ['önce' => stage_label('siparis_verildi'), 'sonra' => stage_label('atolyede'), 'kaynak' => 'camlar stokta']);
        $atolye = true;
    }
    return ['rx' => $rxId, 'eksik' => $eksik, 'atolye' => $atolye, 'sgk' => $sgk];
}

/** e-Reçete no: boşluk/tire atılır, büyük harf; 4–12 harf/rakam ve en az bir rakam. Geçersizse ''. */
function tezgah_recete_no(string $s): string
{
    $s = strtoupper(preg_replace('/[\s\-]+/u', '', trim($s)) ?? '');
    $s = strtr($s, ['ı' => 'I', 'i' => 'I', 'İ' => 'I']);
    return preg_match('/^[0-9A-Z]{4,12}$/', $s) && preg_match('/\d/', $s) ? $s : '';
}

/**
 * sgk_parse() çözümünden tek ekran formunun ön değerleri. Alan adları rx_from_post() ile aynıdır
 * (kayıt reçete ekranının doğrulamasından geçer). T.C. kimlik no ALINMAZ (KVKK).
 */
function tezgah_on_dolum(array $c): array
{
    $design = sgk_lens_design($c);
    $yakinVar = ($c['yakin_sag']['sph'] ?? '') !== '' || ($c['yakin_sol']['sph'] ?? '') !== '';
    $d = [
        'lens_design'       => $design,
        'prescription_date' => sgk_tarih_db((string) ($c['recete_tarihi'] ?? '')) ?: date('Y-m-d'),
        'pd'                => (string) ($c['pd'] ?? ''),
        'doctor'            => mb_substr((string) ($c['doktor'] ?? ''), 0, 120),
        'erecete'           => tezgah_recete_no((string) ($c['erecete'] ?? '')),
        'ad'                => tr_title((string) ($c['ad'] ?? '')),
        'soyad'             => tr_title((string) ($c['soyad'] ?? '')),
        'dogum'             => ($c['yas'] ?? '') !== '' && ctype_digit((string) $c['yas']) ? (string) ((int) date('Y') - (int) $c['yas']) : '',
        'yakin'             => $yakinVar,
    ];
    foreach (['sag' => 'right', 'sol' => 'left'] as $g => $t) {
        $d[$t . '_sph'] = (string) ($c[$g]['sph'] ?? '');
        $d[$t . '_cyl'] = (string) ($c[$g]['cyl'] ?? '');
        $d[$t . '_axis'] = (string) ($c[$g]['aks'] ?? '');
        $d[$t . '_add'] = (string) ($c[$g]['add'] ?? '');
        $d['near_' . $t . '_sph'] = (string) ($c['yakin_' . $g]['sph'] ?? '');
    }
    return $d;
}

/** Bu kullanıcının Medula'dan aktarıp henüz kaydetmediği reçeteler (son 2 gün). Hasta adı yalnızca ekranda. */
function tezgah_bekleyenler(int $userId, int $limit = 8): array
{
    $sinir = date('Y-m-d H:i:s', time() - 2 * 86400);
    $out = [];
    // 4.34.0: müşteriye bağlanmış (bekleyen reçeteye dönmüş) aktarımlar listeden düşer
    foreach (rows('SELECT id, parsed, created_at FROM sgk_incoming WHERE user_id = ? AND used_order_id IS NULL AND created_at >= ?
                     AND NOT EXISTS (SELECT 1 FROM bekleyen_receteler b WHERE b.gelen_id = sgk_incoming.id)
                   ORDER BY id DESC LIMIT ' . max(1, $limit), [$userId, $sinir]) as $r) {
        $p = json_decode((string) $r['parsed'], true);
        $p = is_array($p) ? $p : [];
        $out[] = [
            'id'      => (int) $r['id'],
            'hasta'   => (string) ($p['hasta'] ?? ''),
            'erecete' => tezgah_recete_no((string) ($p['erecete'] ?? '')),
            'zaman'   => (string) $r['created_at'],
        ];
    }
    return $out;
}

/** Varsayılan teslim sözü: bugünden N iş günü sonra (pazar atlanır). Ayar: tezgah_teslim_gun (varsayılan 3). */
function tezgah_teslim_tarihi(?int $bugun = null): string
{
    $gun = max(0, min(30, (int) setting('tezgah_teslim_gun', '3')));
    $t = $bugun ?? time();
    while ($gun > 0) {
        $t += 86400;
        if ((int) date('w', $t) !== 0) {
            $gun--;
        }
    }
    return date('Y-m-d', $t);
}
