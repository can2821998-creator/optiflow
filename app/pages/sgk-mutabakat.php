<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.12.0 — SGK mutabakat: dönem özeti + Medula listesiyle karşılaştırma
   ========================================================================== */

$me = require_login();
ozellik_gereksin('sgk_mutabakat');

if (is_post() && post('eylem') === 'karsilastir') {
    $metin = (string) ($_POST['liste'] ?? '');
    $numaralar = sgk_metinden_numaralar($metin);
    sgk_kontrol_kaydet($numaralar, 'yapistir');
    audit('sgk_liste_kontrol', 'sgk', null, ['numara' => count($numaralar), 'kaynak' => 'yapıştırma']);
    redirect('sgk-mutabakat.php?kontrol=1');
}
if (is_post() && post('eylem') === 'temizle') {
    unset($_SESSION['sgk_kontrol']);
    redirect('sgk-mutabakat.php');
}

$ay = query('ay', date('Y-m'));
$d = sgk_donem_ozeti($ay);
$o = $d['ozet'];
$kontrol = sgk_kontrol_oku();
$sonuc = $kontrol ? sgk_liste_karsilastir($kontrol['numaralar']) : null;
$aylar = [];
for ($i = 0; $i < 12; $i++) {
    $t = strtotime(date('Y-m-01') . " -$i months");
    $aylar[date('Y-m', $t)] = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'][(int) date('n', $t)] . ' ' . date('Y', $t);
}
$tutarGor = can_see_amounts();

page_start('SGK mutabakat', 'sgk-mutabakat');
page_header('SGK mutabakat', 'Aktarılan reçeteler, SGK siparişleri ve Medula\'da bekleyen reçeteler', '', '', 'SGK');
?>
<form method="get" class="filters" style="margin-bottom:12px">
  <label class="field" style="max-width:240px"><span>Dönem</span>
    <select name="ay" data-auto-submit><?php foreach ($aylar as $k => $ad): ?><option value="<?= e($k) ?>" <?= $d['ay'] === $k ? 'selected' : '' ?>><?= e($ad) ?></option><?php endforeach; ?></select></label>
  <button class="btn btn-sm">Göster</button>
</form>

<section class="stats">
  <div class="stat"><small>Aktarılan reçete</small><b><?= (int) $o['aktarim'] ?></b><span><?= (int) $o['kullanilmayan'] ?> tanesi siparişe dönüşmedi</span></div>
  <div class="stat"><small>SGK siparişi</small><b><?= (int) $o['siparis'] ?></b><span><?= $tutarGor ? e(money($o['sgk_toplam'])) . ' SGK payı' : '' ?></span></div>
  <div class="stat tone-green"><small>Teslim edildi</small><b><?= (int) $o['teslim_edilen'] ?></b><span><?= $tutarGor ? e(money($o['teslim_sgk'])) . ' faturalanabilir' : 'faturalanabilir' ?></span></div>
  <div class="stat <?= $o['erecetesiz'] ? 'tone-amber' : '' ?>"><small>e-Reçete no eksik</small><b><?= (int) $o['erecetesiz'] ?></b><span>SGK siparişi</span></div>
</section>

<section class="card">
  <div class="card-head"><h2><?= icon('search') ?> Medula listesiyle karşılaştır</h2>
    <?php if ($kontrol): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="temizle"><button class="btn btn-sm btn-ghost">Temizle</button></form><?php endif; ?></div>
  <?php if ($sonuc): ?>
    <p class="hint" style="margin-top:0"><?= e(date_tr(date('Y-m-d H:i:s', (int) $kontrol['zaman']), true)) ?> · <?= $kontrol['kaynak'] === 'masaustu' ? 'OptiFlow Pro Medula ekranından okundu' : 'yapıştırılan listeden' ?> · <?= count($kontrol['numaralar']) ?> reçete numarası</p>
    <div class="grid cols-2">
      <div>
        <h3 class="<?= $sonuc['yok'] ? 'text-danger' : '' ?>">OptiFlow'a aktarılmamış (<?= count($sonuc['yok']) ?>)</h3>
        <?php if (!$sonuc['yok']): ?><p class="muted">Listedeki tüm reçeteler OptiFlow'da kayıtlı.</p><?php endif; ?>
        <ul class="kv"><?php foreach ($sonuc['yok'] as $n): ?><li><span><code><?= e($n) ?></code></span><b class="mini muted">Medula'da açıp “Reçeteyi aktar”</b></li><?php endforeach; ?></ul>
      </div>
      <div>
        <h3>OptiFlow'da kayıtlı (<?= count($sonuc['var']) ?>)</h3>
        <ul class="kv"><?php foreach ($sonuc['var'] as $n => $b): ?>
          <li><span><code><?= e((string) $n) ?></code></span><b class="mini">
            <?php if ($b['siparis']): ?><a class="link" href="order.php?id=<?= (int) $b['siparis'] ?>"><?= e(order_no((int) $b['siparis'])) ?></a>
            <?php elseif ($b['gelen']): ?><a class="link" href="sgk-aktar.php?gelen=<?= (int) $b['gelen'] ?>">siparişe dönüşmedi</a><?php endif; ?></b></li>
        <?php endforeach; ?></ul>
      </div>
    </div>
    <p class="hint">Numaralar e-reçete biçimine (7 karakter, harf ve rakam) göre okunur; listede başka kodlar da varsa “aktarılmamış” tarafında görünebilir.</p>
  <?php else: ?>
    <p class="hint" style="margin-top:0"><?= is_optiflow_desktop() && pro_ozellik_acik('sgk_kopru')
        ? 'Medula\'da reçete listesini açıp araç çubuğundaki <b>Listeyi kontrol et</b> düğmesine basın; sonuç burada açılır. Ya da listeyi kopyalayıp aşağıya yapıştırın.'
        : 'Medula\'daki reçete listesini seçip kopyalayın (Ctrl+A, Ctrl+C) ve aşağıya yapıştırın. Liste sunucuda saklanmaz; yalnızca reçete numaraları karşılaştırılır.' ?></p>
    <form method="post" class="stack" style="gap:8px">
      <?= csrf_field() ?><input type="hidden" name="eylem" value="karsilastir">
      <textarea name="liste" rows="6" required placeholder="Medula reçete listesini buraya yapıştırın"></textarea>
      <button class="btn btn-primary btn-sm" style="align-self:flex-start">Karşılaştır</button>
    </form>
  <?php endif; ?>
</section>

<div class="grid cols-2">
  <section class="card">
    <div class="card-head"><h2>SGK siparişleri</h2><small class="muted"><?= e($aylar[$d['ay']] ?? $d['ay']) ?></small></div>
    <?php if (!$d['siparisler']): ?><p class="hint">Bu dönemde SGK siparişi yok.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Sipariş</th><th>e-Reçete</th><th>Durum</th><?php if ($tutarGor): ?><th class="num">SGK</th><?php endif; ?></tr></thead>
      <tbody><?php foreach ($d['siparisler'] as $s): ?>
        <tr><td><a class="link" href="order.php?id=<?= (int) $s['id'] ?>"><?= e(order_no((int) $s['id'])) ?></a><small class="block muted"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></small></td>
          <td><?= $s['erecete'] ? '<code>' . e((string) $s['erecete']) . '</code>' : '<span class="text-danger mini">eksik</span>' ?></td>
          <td><?= stage_badge((string) $s['order_stage']) ?></td>
          <?php if ($tutarGor): ?><td class="num"><?= e(money($s['sgk_amount'])) ?></td><?php endif; ?></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>
  <section class="card">
    <div class="card-head"><h2>Aktarılan reçeteler</h2><small class="muted"><?= e($aylar[$d['ay']] ?? $d['ay']) ?></small></div>
    <?php if (!$d['gelen']): ?><p class="hint">Bu dönemde aktarım yok.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Zaman</th><th>e-Reçete</th><th>Sipariş</th></tr></thead>
      <tbody><?php foreach ($d['gelen'] as $g): ?>
        <tr><td><?= e(date_tr($g['created_at'], true)) ?><small class="block muted"><?= e((string) $g['full_name']) ?> · <?= e($g['kaynak'] === 'masaustu' ? 'masaüstü' : (string) $g['kaynak']) ?></small></td>
          <td><?= $g['erecete'] ? '<code>' . e((string) $g['erecete']) . '</code>' : '—' ?></td>
          <td><?= $g['used_order_id'] ? '<a class="link" href="order.php?id=' . (int) $g['used_order_id'] . '">' . e(order_no((int) $g['used_order_id'])) . '</a>' : '<a class="link text-danger" href="sgk-aktar.php?gelen=' . (int) $g['id'] . '">siparişe dönüşmedi</a>' ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>
</div>
<?php page_end();
