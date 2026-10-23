<?php
/* 4.48.0 — Tamir / hizmet fişi (print.php?type=hizmet&id=). Yarım A4: üst yarı müşteriye (ne yapılacak, teslim alırken
   görülenler, ne zaman hazır, ücret, takip karekodu); alt yarı mağazada kalır ve gözlükle atölyeye gider (büyük numara
   şeridi, işlemler, paraf, teslim imzaları). Ortak çerçeve: app/fis.php. */
declare(strict_types=1);

$o = find_order(query_int('id'));
if (!$o || $o['transaction_type'] !== 'tamir') {
    render_error_page('Hizmet kaydı bulunamadı', '');
}
$oid = (int) $o['id'];
$d = hizmet_detay($o);
$no = order_no($oid);
$ad = trim($o['c_first'] . ' ' . $o['c_last']);
$tel = phone_display((string) $o['c_phone']);
$tutar = can_see_amounts();
$ucretsiz = (int) $o['is_free'] === 1;
$islemHtml = '<ul class="fis-islem">' . implode('', array_map(static fn($k) => '<li>' . e(service_type_label($k)) . '</li>', $d['islemler'])) . '</ul>';
$sikayet = trim((string) $o['notes']);
$gozluk = trim((string) $o['frame_info']);
$durumHtml = $d['durumlar'] ? fis_isaretler(HIZMET_DURUMLARI, $d['durumlar']) : '<p class="bos-not">Teslim alırken görünür bir hasar işaretlenmedi.</p>';
$hazir = $d['hemen'] || $o['order_stage'] === 'teslim_edildi'
    ? ['Durum', 'Teslim edildi', $o['delivered_at'] ? date_tr((string) $o['delivered_at'], true) : '']
    : ['Ne zaman hazır', $o['promised_date'] ? date_tr((string) $o['promised_date']) : 'Hazır olunca aranırsınız', $o['promised_date'] ? date_long((string) $o['promised_date']) : ''];
$ucretVurgu = !$tutar ? '' : ($ucretsiz
    ? fis_vurgu('Ücret', 'Ücretsiz', 'Bakım hizmetimizdir')
    : fis_vurgu('Ücret', money($o['total_amount']), (float) $o['paid'] > 0.009 ? 'Ödenen ' . money($o['paid']) . ' · kalan ' . money(max(0, (float) $o['balance'])) : 'Teslimde ödenir'));
$takip = track_page_exists() ? order_track_url($oid) : '';

$musteri = '<div>'
    . fis_kutu('01', 'Yapılacak', ($gozluk !== '' ? '<p><b>' . e($gozluk) . '</b></p>' : '') . $islemHtml . ($sikayet !== '' ? '<p class="not">' . e($sikayet) . '</p>' : ''))
    . fis_kutu('02', 'Teslim alırken görülen', $durumHtml, 'bosluk-ust')
    . '</div><div>'
    . fis_vurgu($hazir[0], $hazir[1], $hazir[2], 'koyu')
    . $ucretVurgu
    . fis_karekod($takip, 'Gözlüğünüzün durumunu karekoddan izleyin.')
    . '<p class="fis-kural">Gözlüğünüzü <b>bu fişle</b> teslim alırsınız. 3 ay içinde alınmayan ürünlerden mağazamız sorumlu değildir.</p>'
    . '</div>';

$magaza = '<div>'
    . '<div class="fis-serit"><b>' . e($no) . '</b><span><strong>' . e($ad) . '</strong><span class="mono">' . e($tel) . '</span>'
    . ($o['promised_date'] && !$d['hemen'] ? '<span>Hazır: ' . e(date_tr((string) $o['promised_date'])) . '</span>' : '') . '</span></div>'
    . fis_kutu('01', 'İşlem', ($gozluk !== '' ? '<p><b>' . e($gozluk) . '</b></p>' : '') . $islemHtml . ($sikayet !== '' ? '<p class="not">' . e($sikayet) . '</p>' : ''), 'bosluk-ust')
    . '</div><div>'
    . fis_kutu('02', 'Teslim alırken görülen', $durumHtml)
    . fis_kutu('03', 'Atölye', '<table class="fis-tablo paraf"><thead><tr><th>Adım</th><th>Tamam</th><th>Paraf</th></tr></thead><tbody>'
        . '<tr><td>İşlem yapıldı</td><td><span class="kare"></span></td><td></td></tr><tr><td>Kontrol · müşteriye haber</td><td><span class="kare"></span></td><td></td></tr></tbody></table>'
        . '<p class="raf">Raf / kutu: <span>' . e((string) ($o['raf_yeri'] ?? '')) . '</span></p>', 'bosluk-ust')
    . '</div>'
    . '<div class="tam">' . fis_imzalar([['Teslim alan personel', (string) ($o['created_by_name'] ?? '')], ['Müşteri · teslim aldım', $ad]]) . '</div>';

fis_sayfa([
    'baslik' => 'Hizmet fişi ' . $no,
    'etiket' => 'Tamir · hizmet fişi',
    'no' => $no,
    'tarih' => date_tr((string) $o['created_at'], true),
    'musteri' => $musteri,
    'magaza' => $magaza,
]);
