<?php
declare(strict_types=1);

/* 4.16.1 — SGK dönem faturasının reçete dökümü (faturaya ek). 4.22.0: ortak döküm tasarımı (app/dokum.php).
   Beklenen: $f (fatura), $shop. T.C. kimlik no YOK (saklanmaz). */
$dokum = fatura_sgk_dokum((int) $f['id']);
$dokumToplam = round(array_sum(array_column($dokum, 'tutar')), 2);
$firma = fatura_firma();
$fark = round((float) $f['genel_toplam'] - $dokumToplam, 2);
$ayAdi = fatura_sgk_ay_adi((string) $f['sgk_donem']);

echo dokum_ust('SGK FATURA EKİ · REÇETE DÖKÜMÜ', [
    ['Dönem', $ayAdi],
    ['Fatura no', (string) ($f['fatura_no'] ?: 'Taslak #' . (int) $f['id'])],
    ['Reçete', (string) count($dokum)],
], (string) ($firma['unvan'] ?: $shop), [
    ['belge', trim(($firma['kimlik'] ? 'VKN/TCKN ' . $firma['kimlik'] : '') . ($firma['vergi_dairesi'] !== '' ? ' · ' . $firma['vergi_dairesi'] . ' V.D.' : ''), ' ·')],
    ['konum', trim($firma['adres'] . ' ' . trim($firma['ilce'] . ' / ' . $firma['il'], ' /'))],
]);
echo dokum_selam('Alıcı', (string) $f['alici_unvan'],
    e($ayAdi) . ' dönemine ait SGK faturasının ekidir; faturaya konu reçeteler Medula işlem tarihine göre sıralanmıştır.',
    dokum_vurgu('Reçete toplamı', money($dokumToplam), count($dokum) . ' reçete'));
echo dokum_bilgi([
    ['belge', 'Fatura tutarı', money($f['genel_toplam'])],
    ['sgk', 'Reçete toplamı', money($dokumToplam)],
    ['yuzde', 'Fark', money($fark), abs($fark) < 0.01 ? 'tutarlar eşit' : 'KDV / yuvarlama'],
    ['takvim', 'Düzenleme', date_tr((string) ($f['duzenleme'] ?? $f['created_at']))],
]);
$satir = '';
foreach ($dokum as $i => $r) {
    $satir .= '<tr><td><span class="sira">' . ($i + 1) . '</span></td><td>' . e(date_tr((string) ($r['medula_islendi_at'] ?: $r['delivered_at']))) . '</td><td><b>' . e(trim((string) $r['first_name'] . ' ' . (string) $r['last_name'])) . '</b></td>'
        . '<td class="mono">' . e((string) ($r['sgk_erecete'] ?: '—')) . '</td><td>' . e(order_no((int) $r['order_id'])) . '</td><td class="num">' . e(money($r['tutar'])) . '</td></tr>';
}
if (!$dokum) { $satir = '<tr><td colspan="6" class="bos">Bu faturada reçete yok.</td></tr>'; }
echo '<div class="bolum">' . dokum_kutu('Reçeteler', '<table class="tablo"><thead><tr><th>#</th><th>Medula</th><th>Hasta</th><th>e-Reçete no</th><th>Sipariş</th><th class="num">SGK payı</th></tr></thead><tbody>' . $satir
    . '</tbody><tfoot><tr class="genel"><th colspan="5">Toplam</th><th class="num">' . e(money($dokumToplam)) . '</th></tr></tfoot></table>', 'uzun', count($dokum) . ' reçete') . '</div>';
echo dokum_son('<p>Bu döküm <b>' . e($ayAdi) . '</b> SGK faturasının ekidir. Kimlik numaraları saklanmadığı için yer almaz.</p>');
