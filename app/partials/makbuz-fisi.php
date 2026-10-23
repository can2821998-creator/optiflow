<?php
/* 4.48.0 — Tahsilat makbuzu, yarım A4 (print.php?type=payment&id=; ayrıntılı A4: &tam=1). Üst yarı müşteriye
   (tahsil edilen, yazıyla, kalan bakiye, sıradaki taksit), alt yarı kasada kalır (aynı özet + iki imza). */
declare(strict_types=1);

$p = row('SELECT p.*, o.id AS order_id, o.transaction_type, o.total_amount, o.sgk_amount, c.first_name, c.last_name, c.phone, u.full_name AS by_name
          FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id
          LEFT JOIN user_accounts u ON u.id = p.created_by WHERE p.id = ?', [query_int('id')]);
if (!$p) {
    render_error_page('Makbuz bulunamadı', '');
}
$gecmis = rows('SELECT * FROM payments WHERE order_id = ? AND (created_at < ? OR (created_at = ? AND id <= ?)) ORDER BY created_at, id',
    [$p['order_id'], $p['created_at'], $p['created_at'], $p['id']]);
$net = (float) $p['total_amount'] - (float) $p['sgk_amount'];
$odenen = array_sum(array_map(static fn($x) => (float) $x['amount'], $gecmis));
$kalan = max(0.0, round($net - $odenen, 2));
$ad = trim($p['first_name'] . ' ' . $p['last_name']);
$yontem = payment_methods()[$p['method']] ?? $p['method'];
$sipNo = order_no((int) $p['order_id']);
$plan = function_exists('defter_plan') ? defter_plan((int) $p['order_id']) : [];
$sira = $plan ? defter_siradaki($plan) : null;

$satirlar = '';
foreach ($gecmis as $x) {
    $buMu = (int) $x['id'] === (int) $p['id'];
    $satirlar .= '<tr' . ($buMu ? ' class="bu"' : '') . '><td>' . e(date_tr((string) $x['created_at'])) . ($buMu ? ' · <b>bu makbuz</b>' : '') . '</td><td>'
        . e(payment_methods()[$x['method']] ?? $x['method']) . '</td><td class="num">' . e(money($x['amount'])) . '</td></tr>';
}
$ozet = '<table class="fis-tablo"><thead><tr><th>Tarih</th><th>Yöntem</th><th class="num">Tutar</th></tr></thead><tbody>' . $satirlar . '</tbody>'
    . '<tfoot><tr><td colspan="2">' . e(transaction_type_label($p['transaction_type'])) . ' tutarı' . ((float) $p['sgk_amount'] > 0 ? ' (SGK payı düşülmüş)' : '') . '</td><td class="num">' . e(money($net)) . '</td></tr></tfoot></table>';
$sag = fis_vurgu('Tahsil edilen', money($p['amount']), $yontem, 'koyu')
    . fis_vurgu('Kalan bakiye', money($kalan), $kalan <= 0.009 ? 'Hesabınız kapandı' : ($sira ? 'Sıradaki taksit ' . date_tr($sira['vade']) . ' · ' . money($sira['kalan']) : ($kalan > 0 ? 'Teşekkür ederiz' : '')));

$musteri = '<div>' . fis_kutu('01', 'Sayın', '<p><b>' . e($ad) . '</b> · <span class="mono">' . e(phone_display((string) $p['phone'])) . '</span></p>'
        . '<p>' . e($sipNo) . ' için aşağıdaki tutar tarafımızca <b>tahsil edilmiştir</b>.</p><p class="not">Yalnız: <b>' . e(tutar_yaziyla((float) $p['amount'])) . '</b></p>')
    . fis_kutu('02', 'Hesap özeti', $ozet, 'bosluk-ust') . '</div>'
    . '<div>' . $sag . '<p class="fis-kural">Bu makbuz işlem takibi içindir, <b>fatura yerine geçmez</b>.</p></div>';

$magaza = '<div>' . fis_satirlar([['Müşteri', '<b>' . e($ad) . '</b>'], ['Telefon', '<span class="mono">' . e(phone_display((string) $p['phone'])) . '</span>'],
        ['Sipariş', '<span class="mono">' . e($sipNo) . '</span>'], ['Yöntem', e($yontem)], ['Yazıyla', e(tutar_yaziyla((float) $p['amount']))]])
    . fis_kutu('01', 'Hesap özeti', $ozet, 'bosluk-ust') . '</div>'
    . '<div>' . $sag . '</div>'
    . '<div class="tam">' . fis_imzalar([['Tahsilatı yapan', (string) ($p['by_name'] ?? '')], ['Ödemeyi yapan', $ad]]) . '</div>';

fis_sayfa([
    'baslik' => 'Tahsilat makbuzu ' . $sipNo,
    'etiket' => 'Tahsilat makbuzu',
    'no' => dokum_no((int) $p['id'], 'M'),
    'tarih' => date_tr((string) $p['created_at'], true),
    'musteri' => $musteri,
    'magaza' => $magaza,
]);
