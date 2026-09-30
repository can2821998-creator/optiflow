<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.12.0 — Stok satın alma önerisi (tedarikçi bazında)
   ========================================================================== */

$me = require_login();
ozellik_gereksin('stok_oneri');

$cerceveler = stok_cerceve_onerileri();
$camlar = stok_cam_ozeti();
$gruplar = [];
foreach ($cerceveler as $r) {
    $k = $r['supplier_id'] ? (int) $r['supplier_id'] : 0;
    $gruplar[$k]['ad'] = $r['tedarikci'] ?: 'Tedarikçisi belirtilmemiş';
    $gruplar[$k]['kalem'][] = $r;
}
$telefonlar = [];
foreach (rows('SELECT id, phone FROM suppliers') as $s) {
    $telefonlar[(int) $s['id']] = (string) $s['phone'];
}
$camToplam = array_sum(array_map(static fn($c) => (int) $c['adet'], $camlar));

page_start('Satın alma önerisi', 'stok-oneri');
page_header('Satın alma önerisi', 'Kritik seviyedeki çerçeveler ve sipariş bekleyen camlar, tedarikçiye göre gruplu.', '<button class="btn btn-sm" type="button" data-yazdir>' . icon('print') . ' Yazdır</button>', '', 'Tedarik');
?>
<section class="stats">
  <div class="stat <?= $cerceveler ? 'tone-amber' : '' ?>"><small>Kritik çerçeve</small><b><?= count($cerceveler) ?></b><span>model</span></div>
  <div class="stat"><small>Önerilen çerçeve</small><b><?= array_sum(array_column($cerceveler, 'oneri')) ?></b><span>adet</span></div>
  <div class="stat <?= $camToplam ? 'tone-red' : '' ?>"><small>Sipariş bekleyen cam</small><b><?= $camToplam ?></b><span>fişe girmemiş</span></div>
</section>

<section class="card">
  <div class="card-head"><h2><?= icon('eye') ?> Sipariş bekleyen camlar</h2>
    <?php if ($camToplam && ozellik_acik('cam_siparis')): ?><a class="btn btn-sm btn-primary no-print" href="cam-siparis.php"><?= icon('truck') ?> Cam sipariş fişi oluştur</a><?php endif; ?></div>
  <?php if (!$camlar): ?>
    <p class="hint">Eksik cam yok.</p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Cam</th><th>Tedarikçi</th><th class="num">Adet</th><th>En yakın teslim sözü</th></tr></thead>
      <tbody><?php foreach ($camlar as $c): ?>
        <tr><td><?= e((string) $c['lens_type']) ?></td><td><?= e((string) ($c['tedarikci'] ?: '—')) ?></td><td class="num"><b><?= (int) $c['adet'] ?></b></td>
          <td class="<?= $c['en_yakin'] && $c['en_yakin'] <= date('Y-m-d', strtotime('+2 days')) ? 'text-danger' : '' ?>"><?= $c['en_yakin'] ? e(date_tr($c['en_yakin'])) : '—' ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  <?php endif; ?>
</section>

<?php if (!$gruplar): ?>
  <section class="card"><?= empty_state('Kritik çerçeve yok', 'Asgari stok seviyesinin altına düşen çerçeve bulunmuyor. Asgari seviyeleri Çerçeve stoğu ekranından ayarlayabilirsiniz.') ?></section>
<?php endif; ?>
<?php foreach ($gruplar as $sid => $g):
  $metin = stok_cerceve_metni($g['ad'], $g['kalem']);
  $wa = $sid && !empty($telefonlar[$sid]) ? wa_url($telefonlar[$sid], $metin) : ''; ?>
  <section class="card">
    <div class="card-head"><h2><?= icon('glasses') ?> <?= e($g['ad']) ?></h2>
      <span class="no-print" style="display:flex;gap:6px"><?php if ($wa !== ''): ?><a class="btn btn-sm btn-wa" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> Tedarikçiye gönder</a><?php endif; ?></span></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Çerçeve</th><th class="num">Eldeki</th><th class="num">Asgari</th><th class="num hide-sm">Aylık satış</th><th class="num">Önerilen</th></tr></thead>
      <tbody><?php foreach ($g['kalem'] as $r): ?>
        <tr><td><a class="link" href="cerceve.php?duzenle=<?= (int) $r['id'] ?>"><?= e(trim($r['brand'] . ' ' . $r['model'])) ?></a><small class="block muted"><?= e(trim($r['color'] . ' ' . $r['size'])) ?><?= $r['barcode'] ? ' · ' . e((string) $r['barcode']) : '' ?></small></td>
          <td class="num <?= (int) $r['qty'] === 0 ? 'text-danger' : '' ?>"><?= (int) $r['qty'] ?></td><td class="num"><?= (int) $r['min_qty'] ?></td>
          <td class="num hide-sm"><?= (int) $r['aylik_satis'] ?></td><td class="num"><b><?= (int) $r['oneri'] ?></b></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>
<?php endforeach; ?>
<p class="hint">Önerilen adet: son 90 günün aylık satış ortalaması + asgari stok − eldeki (en az asgari stoğa tamamlar).</p>
<?php page_end();
