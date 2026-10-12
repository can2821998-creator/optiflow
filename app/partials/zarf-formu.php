<?php
declare(strict_types=1);

/* ==========================================================================
   4.32.0 — Zarf formu (A5): elle yazılan sipariş formunun yerini alır; sipariş zarfına konur.
   print.php?type=zarf&id=<sipariş> (özellik: kagitsiz). Ayrı sayfa (A5 dikey), assets/zarf.css + print.js.
   Tutarlar yalnız tutar görme yetkisi olana basılır.
   ========================================================================== */

$o = find_order(query_int('id'));
if (!$o) {
    render_error_page('Sipariş bulunamadı', '');
}
$rx = row('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph FROM prescription_records r LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.order_id = ? ORDER BY r.prescription_date DESC, r.id DESC LIMIT 1', [$o['id']]);
$kalemler = $rx ? rows('SELECT lens_label, lens_type, lens_value, eye, item_group, stock_status FROM prescription_lens_items WHERE prescription_id = ? ORDER BY item_group, lens_no, id', [$rx['id']]) : [];
$tutarlar = can_see_amounts();
$v = static fn(string $k) => trim((string) ($rx[$k] ?? ''));
$no = order_no((int) $o['id']);
$takip = (track_page_exists() && ($u = order_track_url((int) $o['id'])) !== '') ? $u : '';
$ad = trim($o['c_first'] . ' ' . $o['c_last']);
$kullanim = $rx ? (lens_designs()[(string) $rx['lens_design']] ?? '') : '';
$yakinVar = $rx && ($v('near_right_sph') !== '' || $v('near_left_sph') !== '');
$addVar = $rx && ($v('right_add') !== '' || $v('left_add') !== '');
$durumlar = stock_statuses();

$hucre = static fn(string $d) => $d !== '' ? e($d) : '<span class="bos">—</span>';
$satir = '';
if ($rx) {
    foreach (['right' => 'Sağ', 'left' => 'Sol'] as $t => $ad2) {
        $satir .= '<tr><th>' . $ad2 . '</th><td>' . $hucre($v($t . '_sph')) . '</td><td>' . $hucre($v($t . '_cyl')) . '</td><td>' . $hucre($v($t . '_axis') !== '' ? $v($t . '_axis') . '°' : '') . '</td>'
            . ($addVar ? '<td>' . $hucre($v($t . '_add')) . '</td>' : '') . '</tr>';
    }
    if ($yakinVar) {
        $satir .= '<tr class="yakin"><th>Yakın</th><td colspan="' . ($addVar ? 4 : 3) . '">Sağ ' . $hucre($v('near_right_sph')) . ' · Sol ' . $hucre($v('near_left_sph')) . ($v('near_lens_type') !== '' ? ' · ' . e($v('near_lens_type')) : '') . '</td></tr>';
    }
}
$pd = $v('right_pd') !== '' || $v('left_pd') !== '' ? 'Sağ ' . ($v('right_pd') ?: '—') . ' · Sol ' . ($v('left_pd') ?: '—') : ($v('pd') !== '' ? $v('pd') . ' mm' : '');
$yuk = $v('right_height') !== '' || $v('left_height') !== '' ? 'Sağ ' . ($v('right_height') ?: '—') . ' · Sol ' . ($v('left_height') ?: '—') : '';

$camlar = '';
foreach ($kalemler as $k) {
    $stokta = $k['stock_status'] === 'stokta_var';
    $camlar .= '<li><span class="cam-ad"><b>' . e((string) ($k['lens_label'] ?: 'Cam')) . '</b> ' . e(trim((string) ($k['lens_type'] ?: $k['lens_value']))) . '</span>'
        . '<span class="kutucuk' . ($stokta ? ' dolu' : '') . '">Stokta</span><span class="kutucuk' . (!$stokta ? ' dolu' : '') . '">' . e($stokta ? 'Depodan' : ($durumlar[$k['stock_status']][0] ?? 'Depodan')) . '</span></li>';
}
if ($camlar === '' && trim((string) $o['lens_type']) !== '') {
    $camlar = '<li><span class="cam-ad"><b>Cam</b> ' . e((string) $o['lens_type']) . '</span></li>';
}

$hesap = '';
if ($tutarlar && (string) $o['order_stage'] !== 'iptal') {
    $hesap = '<dl class="hesap"><div><dt>Tutar</dt><dd>' . money($o['total_amount']) . '</dd></div>'
        . ((float) $o['sgk_amount'] > 0 ? '<div><dt>SGK payı</dt><dd>−' . money($o['sgk_amount']) . '</dd></div>' : '')
        . '<div><dt>Alınan</dt><dd>' . money($o['paid']) . '</dd></div>'
        . '<div class="kalan"><dt>Kalan</dt><dd>' . money($o['balance']) . '</dd></div></dl>';
}
$qr = $takip !== '' ? qr_svg($takip, 92, 'M', '#141012') : '';
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Zarf <?= e($no) ?> · <?= e($ad) ?></title>
<link rel="stylesheet" href="<?= e(asset('zarf.css')) ?>">
</head>
<body>
<div class="arac no-print"><button type="button" data-print>Yazdır</button><button type="button" class="ghost" data-close>Kapat</button><span>Kâğıt: <b>A5</b> (A4'e basarsanız "Kâğıda sığdır" seçin).</span></div>
<main class="zarf">
  <header class="ust">
    <div>
      <small class="dukkan"><?= e(setting('shop_name', 'OptiFlow')) ?> · Sipariş zarfı</small>
      <div class="no"><?= e($no) ?></div>
      <div class="tur"><?= e(transaction_type_label($o['transaction_type'])) ?><?= (string) $o['sgk_erecete'] !== '' ? ' · e-Reçete <b>' . e((string) $o['sgk_erecete']) . '</b>' : '' ?></div>
    </div>
    <?php if ($qr !== ''): ?><div class="qr"><?= $qr ?><small>Takip</small></div><?php endif; ?>
  </header>

  <section class="musteri">
    <div class="ad"><?= e($ad) ?></div>
    <div class="bilgi">
      <span><small>Telefon</small><b><?= e(phone_display($o['c_phone'])) ?></b></span>
      <span><small>Sipariş</small><b><?= e(date_tr($o['created_at'])) ?></b></span>
      <span class="soz"><small>Teslim sözü</small><b><?= e($o['promised_date'] ? date_tr($o['promised_date']) : '—') ?></b></span>
      <span><small>Alan</small><b><?= e((string) ($o['created_by_name'] ?: '—')) ?></b></span>
    </div>
  </section>

  <?php if ($rx): ?>
  <section class="recete">
    <h2>Reçete <small><?= e(trim(date_tr($rx['prescription_date']) . ($kullanim !== '' ? ' · ' . $kullanim : '') . ($v('doctor') !== '' ? ' · ' . $v('doctor') : ''))) ?></small></h2>
    <table><thead><tr><th></th><th>SPH</th><th>CYL</th><th>AKS</th><?= $addVar ? '<th>ADD</th>' : '' ?></tr></thead><tbody><?= $satir ?></tbody></table>
    <div class="olcu"><?php if ($pd !== ''): ?><span><small>PD</small><b><?= e($pd) ?></b></span><?php endif; ?><?php if ($yuk !== ''): ?><span><small>Yükseklik</small><b><?= e($yuk) ?></b></span><?php endif; ?></div>
  </section>
  <?php endif; ?>

  <section class="urun">
    <div><h2>Cam</h2><ul class="camlar"><?= $camlar ?: '<li class="bos">—</li>' ?></ul></div>
    <div><h2>Çerçeve</h2><p class="cerceve"><?= e(trim((string) $o['frame_info']) ?: '—') ?></p><?= $hesap ?></div>
  </section>

  <section class="atolye">
    <h2>Atölye</h2>
    <div class="adimlar"><span>Cam geldi</span><span>Kesim</span><span>Montaj</span><span>Kontrol</span><span>Haber verildi</span></div>
    <div class="not"><small>Not</small><p><?= $rx && $v('prescription_note') !== '' ? nl2br(e($v('prescription_note'))) : '' ?></p></div>
  </section>
  <footer><?= e($no) ?> · <?= e($ad) ?> · <?= e(date('d.m.Y H:i')) ?></footer>
</main>
<script src="<?= e(asset('print.js')) ?>" defer></script>
</body>
</html>
