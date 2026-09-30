<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.12.0 — Barkod / karekod okut (OptiFlow Pro masaüstü)
   ?kod=…&json=1 → okuyucu betiği için JSON; ?kod=… → sonuç sayfası.
   ========================================================================== */

$me = require_login();
ozellik_gereksin('barkod');

$kod = query('kod');
if ($kod !== '' && query('json') === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $r = barkod_coz($kod);
    echo json_encode(['ok' => $r['tur'] !== 'bilinmiyor', 'tur' => $r['tur'], 'hedef' => $r['hedef'], 'etiket' => $r['etiket']], JSON_UNESCAPED_UNICODE);
    exit;
}
$sonuc = $kod !== '' ? barkod_coz($kod) : null;
if ($sonuc && in_array($sonuc['tur'], ['cerceve', 'siparis'], true) && query('git') !== '0') {
    redirect($sonuc['hedef']);
}

page_start('Barkod okut', 'barkod');
page_header('Barkod okut', 'USB okuyucuyla ÜTS karekodu, çerçeve barkodu ya da sipariş fişindeki karekodu okutun.', '', '', 'Atölye');
?>
<section class="card">
  <form method="get" class="stack" style="gap:8px;max-width:560px">
    <label class="field"><span>Kod</span><input name="kod" value="<?= e($kod) ?>" autofocus autocomplete="off" data-barkod-alani placeholder="Okutun ya da yazıp Enter'a basın"></label>
    <button class="btn btn-primary btn-sm" style="align-self:flex-start"><?= icon('search') ?> Bul</button>
  </form>
  <p class="hint">Okuyucu modu açıkken herhangi bir OptiFlow ekranında (yazı alanı seçili değilken) kod okutmanız yeterli; ilgili kayıt kendiliğinden açılır.</p>
</section>

<?php if ($sonuc): ?>
  <section class="card">
    <div class="card-head"><h2><?= $sonuc['tur'] === 'uts' ? 'ÜTS ürünü' : 'Sonuç' ?></h2></div>
    <?php if ($sonuc['tur'] === 'uts'): $g = $sonuc['ayrinti']; ?>
      <ul class="kv">
        <li><span>GTIN (ürün numarası)</span><b><code><?= e($g['gtin']) ?></code></b></li>
        <?php if ($g['skt']): ?><li><span>Son kullanma</span><b class="<?= $g['skt'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(date_tr($g['skt'])) ?></b></li><?php endif; ?>
        <?php if ($g['parti']): ?><li><span>Parti / lot</span><b><?= e($g['parti']) ?></b></li><?php endif; ?>
        <?php if ($g['seri']): ?><li><span>Seri no</span><b><?= e($g['seri']) ?></b></li><?php endif; ?>
      </ul>
      <p class="hint">Bu ürün çerçeve stoğunda kayıtlı değil. <a class="link" href="cerceve.php">Çerçeve stoğuna ekleyin</a> (barkod alanına GTIN'i yazın)<?= pro_ozellik_acik('uts') ? ' ya da <a class="link" href="uts-karekod.php">ÜTS karekod</a> ekranını kullanın' : '' ?>.</p>
    <?php else: ?>
      <p><b><?= e($sonuc['etiket']) ?></b> — <code><?= e($kod) ?></code></p>
      <p class="hint">Çerçeve barkodu, sipariş numarası (örn. #00123) ya da sipariş fişindeki karekod okutulabilir. Kod bir çerçeveye aitse, çerçeve kartındaki barkod alanına kaydedin.</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php page_end();
