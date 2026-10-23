<?php
/* 4.48.0 — Hızlı satış fişi, yarım A4 (print.php?type=satis&id=; ayrıntılı A4: &tam=1). Üst yarı müşteriye
   (ürünler, toplam, ödeme, değişim/iade kuralı), alt yarı mağazada kalır (aynı döküm + satışı yapanın imzası). */
declare(strict_types=1);

$s = satis_bul(query_int('id'));
if (!$s || (!is_super() && (int) $s['created_by'] !== (int) (current_user()['id'] ?? 0))) {
    render_error_page('Satış bulunamadı', '');
}
$mus = $s['musteri'] ? trim($s['musteri']['first_name'] . ' ' . $s['musteri']['last_name']) : '';
$iptal = $s['durum'] === 'iptal';
$kalemInd = array_sum(array_map(static fn($k) => (float) $k['indirim'], $s['kalemler']));
$genelInd = round((float) $s['indirim'] - $kalemInd, 2);

$satir = '';
foreach ($s['kalemler'] as $k) {
    $satir .= '<tr><td><b>' . e($k['ad']) . '</b>' . ((float) $k['indirim'] > 0 ? ' <small>(−' . e(money($k['indirim'])) . ')</small>' : '') . '</td>'
        . '<td class="num">' . (int) $k['adet'] . '</td><td class="num">' . e(money($k['tutar'])) . '</td></tr>';
}
$alt = ($genelInd > 0 ? '<tr><td colspan="2">İndirim</td><td class="num">−' . e(money($genelInd)) . '</td></tr>' : '')
    . '<tr><td colspan="2">Toplam</td><td class="num">' . e(money($s['toplam'])) . '</td></tr>';
$tablo = '<table class="fis-tablo"><thead><tr><th>Ürün</th><th class="num">Adet</th><th class="num">Tutar</th></tr></thead><tbody>' . $satir . '</tbody><tfoot>' . $alt . '</tfoot></table>';
$odeme = implode(' · ', array_map(static fn($od) => (payment_methods()[$od['method']] ?? $od['method']) . ' ' . money($od['amount']), $s['odemeler']));
$sag = fis_vurgu($iptal ? 'İptal edildi' : 'Toplam', money($s['toplam']), $odeme !== '' ? $odeme : '', $iptal ? '' : 'koyu');

$musteri = '<div>' . fis_kutu('01', $mus !== '' ? 'Sayın ' . $mus : 'Ürünler', $tablo) . '</div>'
    . '<div>' . $sag . '<p class="fis-kural">Değişim ve iade için ürünü ambalajı ve <b>bu fişle</b> 14 gün içinde getirin. Açılmış hijyen ürünleri '
    . '(solüsyon, damla) iade alınmaz. Bu fiş <b>fatura yerine geçmez</b>.</p>'
    . ($iptal ? '<p class="not">İptal: ' . e(date_tr((string) $s['iptal_at'], true)) . ' · ' . e((string) $s['iptal_sebep']) . '</p>' : '') . '</div>';

$magaza = '<div>' . fis_kutu('01', 'Satış dökümü', $tablo) . ($s['not_metni'] ? '<p class="not">' . e((string) $s['not_metni']) . '</p>' : '') . '</div>'
    . '<div>' . $sag . fis_satirlar([['Müşteri', $mus !== '' ? e($mus) : 'Perakende'], ['Satışı yapan', e((string) $s['personel'])]]) . '</div>'
    . '<div class="tam">' . fis_imzalar([['Satışı yapan', (string) $s['personel']], ['Kasa kontrol', '']]) . '</div>';

fis_sayfa([
    'baslik' => 'Satış fişi #' . (int) $s['id'],
    'etiket' => 'Satış fişi',
    'no' => dokum_no((int) $s['id'], 'S'),
    'tarih' => date_tr((string) $s['created_at'], true),
    'musteri' => $musteri,
    'magaza' => $magaza,
]);
