<?php
declare(strict_types=1);

/* ==========================================================================
   4.33.0 — Tezgâh (özellik: kagitsiz). Müşteri reçeteyi verir → e-reçete no OptiFlow'a yazılır →
   OptiFlow Pro Medula'da sorguyu doldurur → personel Medula'da kontrol edip "Aktar"a basar →
   tek ekranda müşteri, cam + stok, çerçeve, tutar + kapora → kaydet → yarım sayfa fiş + müşteri nüshası.
   Sayfa: app/pages/tezgah.php · JS: assets/tezgah.js · Pro: desktop/src/medula/sorgu.ts
   ========================================================================== */

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
    foreach (rows('SELECT id, parsed, created_at FROM sgk_incoming WHERE user_id = ? AND used_order_id IS NULL AND created_at >= ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$userId, $sinir]) as $r) {
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
