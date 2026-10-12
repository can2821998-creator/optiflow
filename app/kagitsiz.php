<?php
declare(strict_types=1);

/* ==========================================================================
   4.32.0 — Kâğıtsız tezgâh (özellik: kagitsiz)

   Türkiye'deki optiklerin çoğunda akış: reçete Medula'dan kontrol edilir, değerler elle
   sipariş formuna yazılır, form sipariş zarfına konur; stokta olmayan camların numaraları
   gün sonunda ayrı bir kâğıda yeniden yazılıp fotoğrafı WhatsApp'tan depoya gönderilir.
   Bu modül iki yeniden yazmayı kaldırır:
     1) Sipariş fişi yarım sayfa (4.32.1): siparişteki "Fiş" A4'ün üst yarısına basılır, ortada kesme çizgisi;
        kesilen alt yarı bir sonraki fişe kullanılır (app/partials/siparis-fisi.php). Elle yazılan formun yerini alır.
     2) Gün sonu depo siparişi: eksik camlar türe ve numaraya göre gruplanıp TEK TUŞLA depoya
        WhatsApp metni olarak gider; camlar "Depoya sipariş verildi" olur. Son gönderim geri alınabilir.
   Depoya giden metinde MÜŞTERİ BİLGİSİ YOKTUR (yalnız cam türü, numara, adet).
   ========================================================================== */

const DEPO_GERI_AL_SAAT = 12;   // son gönderim bu kadar saat geri alınabilir

/** Depoya gidecek eksik camlar (fişe ya da gönderime girmemiş, iptal olmayan siparişlerden). */
function depo_eksik_kalemler(): array
{
    return rows(
        "SELECT i.id, i.lens_type, i.lens_value, i.lens_label, i.eye, i.sph, i.cyl, i.axis, i.add_power, i.item_group
           FROM prescription_lens_items i
           JOIN prescription_records r ON r.id = i.prescription_id
           JOIN orders o ON o.id = r.order_id
          WHERE i.stock_status = 'stokta_yok' AND o.order_stage <> 'iptal'
            AND (i.cam_siparis_id IS NULL OR i.cam_siparis_id = 0)
          ORDER BY i.lens_type, i.id
          LIMIT 500"
    );
}

/** Numarayı depo diliyle yazar: "-2" → "−2,00", "+1.5" → "+1,50"; boşsa ''. */
function depo_numara(?string $deger): string
{
    $s = str_replace([',', '−', ' '], ['.', '-', ''], trim((string) $deger));
    if ($s === '' || !is_numeric($s)) {
        return trim((string) $deger);
    }
    $f = (float) $s;
    if (abs($f) < 0.001) {
        return '0,00';
    }
    return ($f > 0 ? '+' : '−') . number_format(abs($f), 2, ',', '');
}

/**
 * Kalemleri depo listesine çevirir: cam türüne göre, her türde SPH/CYL(/ADD) numarasına göre adet.
 * Dönüş: [['tur' => '1.50 HMC', 'adet' => 3, 'satirlar' => [['numara' => '−2,00 −0,50', 'adet' => 2], ...]], ...]
 * Aks yazılmaz: stok camında aks kesimde verilir.
 */
function depo_gruplari(array $kalemler): array
{
    $gruplar = [];
    foreach ($kalemler as $k) {
        $tur = trim((string) ($k['lens_type'] ?: $k['lens_value'] ?: 'Cam'));
        $sph = depo_numara($k['sph'] ?? '');
        $cyl = depo_numara($k['cyl'] ?? '');
        $add = depo_numara($k['add_power'] ?? '');
        $numara = trim(($sph !== '' ? $sph : '0,00') . ($cyl !== '' && $cyl !== '0,00' ? ' ' . $cyl : '') . ($add !== '' && $add !== '0,00' ? ' ADD ' . ltrim($add, '+') : ''));
        if (($k['item_group'] ?? '') === 'yakin') {
            $numara .= ' (yakın)';
        }
        $gruplar[$tur]['tur'] = $tur;
        $gruplar[$tur]['adet'] = ($gruplar[$tur]['adet'] ?? 0) + 1;
        $gruplar[$tur]['satirlar'][$numara] = ($gruplar[$tur]['satirlar'][$numara] ?? 0) + 1;
    }
    $sonuc = [];
    foreach ($gruplar as $g) {
        $satirlar = [];
        foreach ($g['satirlar'] as $n => $a) {
            $satirlar[] = ['numara' => $n, 'adet' => $a];
        }
        // eksiden artıya
        usort($satirlar, static fn($a, $b) => depo_sirala($a['numara']) <=> depo_sirala($b['numara']));
        $sonuc[] = ['tur' => $g['tur'], 'adet' => $g['adet'], 'satirlar' => $satirlar];
    }
    usort($sonuc, static fn($a, $b) => $b['adet'] <=> $a['adet'] ?: strcmp($a['tur'], $b['tur']));
    return $sonuc;
}

function depo_sirala(string $numara): float
{
    $ilk = explode(' ', str_replace(['−', ','], ['-', '.'], $numara))[0];
    return is_numeric($ilk) ? (float) $ilk : 0.0;
}

/** WhatsApp metni (müşteri bilgisi YOK). */
function depo_metni(array $gruplar): string
{
    $toplam = array_sum(array_column($gruplar, 'adet'));
    $s = [setting('shop_name', 'OptiFlow') . ' — cam siparişi ' . date('d.m.Y'), ''];
    foreach ($gruplar as $g) {
        $s[] = $g['tur'] . ' (' . $g['adet'] . ')';
        foreach ($g['satirlar'] as $r) {
            $s[] = '  ' . $r['numara'] . ($r['adet'] > 1 ? '  × ' . $r['adet'] : '');
        }
        $s[] = '';
    }
    $s[] = 'Toplam ' . $toplam . ' cam. Teşekkürler.';
    return implode("\n", $s);
}

/** Varsayılan depo: ayardaki tedarikçi; yoksa telefonu olan tek etkin tedarikçi. */
function depo_tedarikci(): ?array
{
    $id = (int) setting('depo_tedarikci_id', '0');
    if ($id > 0 && ($t = row('SELECT id, name, phone FROM suppliers WHERE id = ? AND is_active = 1', [$id]))) {
        return $t;
    }
    $telli = rows("SELECT id, name, phone FROM suppliers WHERE is_active = 1 AND phone IS NOT NULL AND phone <> '' ORDER BY id LIMIT 2");
    return count($telli) === 1 ? $telli[0] : null;
}

/**
 * Eksik camları depoya gönderilmiş sayar: "Depoya sipariş verildi" + tedarikçi + zaman; geri alma için kaydeder.
 * Dönüş: ['adet' => n, 'url' => WhatsApp bağlantısı, 'metin' => ...]. Eksik yoksa adet 0.
 */
function depo_gonder(?int $tedarikciId = null): array
{
    $kalemler = depo_eksik_kalemler();
    if (!$kalemler) {
        return ['adet' => 0, 'url' => '', 'metin' => ''];
    }
    $metin = depo_metni(depo_gruplari($kalemler));
    $tedarikci = $tedarikciId ? row('SELECT id, name, phone FROM suppliers WHERE id = ? AND is_active = 1', [$tedarikciId]) : depo_tedarikci();
    $ids = array_map(static fn($k) => (int) $k['id'], $kalemler);
    $in = in_placeholders($ids);
    transaction(static function () use ($ids, $in, $tedarikci): void {
        $set = "stock_status = 'siparis_verildi', ordered_at = ?, arrived_at = NULL";   // zaman PHP'de (SQL taşınabilir)
        $p = array_merge([date('Y-m-d H:i:s')], $ids);
        if ($tedarikci) {
            $set .= ', supplier_id = ?';
            $p = array_merge([date('Y-m-d H:i:s'), (int) $tedarikci['id']], $ids);
        }
        q("UPDATE prescription_lens_items SET $set WHERE id IN ($in) AND stock_status = 'stokta_yok'", $p);
    });
    setting_set('depo_son_gonderim', (string) json_encode(['ids' => $ids, 'at' => time(), 'tedarikci' => $tedarikci['name'] ?? ''], JSON_UNESCAPED_UNICODE));
    audit('depo_gonderim', 'stock', 0, ['adet' => count($ids), 'tedarikci' => $tedarikci['name'] ?? '']);
    $url = $tedarikci ? wa_url((string) $tedarikci['phone'], $metin) : '';
    if ($url === '') {
        $url = 'https://wa.me/?text=' . rawurlencode($metin);   // numara yoksa WhatsApp kişi seçtirir
    }
    return ['adet' => count($ids), 'url' => $url, 'metin' => $metin];
}

/** Son gönderim (geri alınabilir süre içindeyse). */
function depo_son_gonderim(): ?array
{
    $j = json_decode(setting('depo_son_gonderim', ''), true);
    if (!is_array($j) || empty($j['ids']) || (int) ($j['at'] ?? 0) < time() - DEPO_GERI_AL_SAAT * 3600) {
        return null;
    }
    return $j;
}

/** Son gönderimi geri alır: hâlâ "sipariş verildi" olan camlar "eksik"e döner. Dönüş: geri alınan adet. */
function depo_geri_al(): int
{
    $son = depo_son_gonderim();
    if (!$son) {
        return 0;
    }
    $ids = array_map('intval', (array) $son['ids']);
    $in = in_placeholders($ids);
    $n = 0;
    transaction(static function () use ($ids, $in, &$n): void {
        $n = (int) scalar("SELECT COUNT(*) FROM prescription_lens_items WHERE id IN ($in) AND stock_status = 'siparis_verildi'", $ids);
        q("UPDATE prescription_lens_items SET stock_status = 'stokta_yok', ordered_at = NULL WHERE id IN ($in) AND stock_status = 'siparis_verildi'", $ids);
    });
    setting_set('depo_son_gonderim', '');
    audit('depo_geri_al', 'stock', 0, ['adet' => $n]);
    return $n;
}
