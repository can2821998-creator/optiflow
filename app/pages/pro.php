<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_ROOT . '/app/pazarlama.php';

/* ==========================================================================
   OptiFlow Pro tanıtım sayfası (4.11.0)
   Lite kullanıcı kilitli bir Pro özelliğe tıkladığında buraya gelir.
   Pro paketli mağaza tarayıcıdan geldiyse "OptiFlow Pro uygulamasını açın" der.
   ========================================================================== */
$me = require_login();

$ozellikler = pro_ozellikleri();
$secili = query('ozellik');
if (!isset($ozellikler[$secili])) {
    $secili = (string) array_key_first($ozellikler);
}
$oz = $ozellikler[$secili];
$durum = pro_ozellik_durumu($secili);

// Zaten açıksa doğrudan özelliğe git.
if ($durum === 'acik') {
    redirect($oz['sayfa']);
}

$shop = setting('shop_name', 'OptiFlow');
$wa = pz_whatsapp('Merhaba, ' . $shop . ' için OptiFlow Pro paketine geçmek istiyorum.');
$tel = pz_tel();
$eposta = optiflow_pazarlama()['eposta'] ?? '';

page_start('OptiFlow Pro', '');
page_header('OptiFlow Pro', 'Mağazanızı bir üst seviyeye taşıyan özellikler', '', '', 'Paket');
?>
<div class="pro-sayfa">
  <section class="card pro-hero">
    <span class="pro-rozet buyuk">PRO</span>
    <h2><?= e($oz['ad']) ?></h2>
    <p class="pro-kisa"><?= e($oz['kisa']) ?></p>
    <ul class="pro-maddeler">
      <?php foreach ($oz['madde'] as $m): ?><li><?= icon('check') ?><span><?= e($m) ?></span></li><?php endforeach; ?>
    </ul>

    <?php if ($durum === 'masaustu_gerekli'): ?>
      <div class="alert alert-info" style="margin-top:18px">
        <span>Mağazanız <b>OptiFlow Pro</b> paketinde. Bu özellik bilgisayarınızdaki
        <b>OptiFlow Pro</b> uygulamasında çalışır; uygulamayı açıp aynı hesapla giriş yapın.</span>
      </div>
    <?php else: ?>
      <p class="pro-durum">Mağazanız şu an <b>OptiFlow Lite</b> paketinde.</p>
      <div class="form-actions pro-cta">
        <?php if ($wa !== ''): ?><a class="btn btn-primary btn-lg" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> Pro'ya geçmek istiyorum</a><?php endif; ?>
        <?php if ($tel !== ''): ?><a class="btn btn-lg" href="<?= e($tel) ?>">Arayın</a><?php endif; ?>
        <?php if ($wa === '' && $tel === '' && $eposta !== ''): ?><a class="btn btn-primary btn-lg" href="mailto:<?= e($eposta) ?>?subject=<?= rawurlencode('OptiFlow Pro') ?>">Pro'ya geçmek istiyorum</a><?php endif; ?>
        <?php if ($wa === '' && $tel === '' && $eposta === ''): ?><p class="hint">Pro'ya geçmek için OptiFlow destek ekibinizle iletişime geçin.</p><?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>Lite ve Pro</h2></div>
    <div class="table-wrap">
      <table class="table compact pro-tablo">
        <thead><tr><th></th><th>Lite <small class="muted">· tarayıcı</small></th><th>Pro <small class="muted">· masaüstü uygulaması</small></th></tr></thead>
        <tbody>
          <tr><td>Sipariş, müşteri, atölye, stok, kasa, raporlar</td><td><?= icon('check') ?></td><td><?= icon('check') ?></td></tr>
          <tr><td>SGK reçetesini elle yapıştırıp çözümleme</td><td><?= icon('check') ?></td><td><?= icon('check') ?></td></tr>
          <?php foreach ($ozellikler as $k => $o): ?>
          <tr class="<?= $k === $secili ? 'secili' : '' ?>"><td><a href="pro.php?ozellik=<?= e($k) ?>"><?= e($o['ad']) ?></a></td><td class="muted"><?= icon('lock') ?></td><td><?= icon('check') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
<?php page_end();
