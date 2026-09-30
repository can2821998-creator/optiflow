<?php
/* Çocuk göz gelişim eğrisi kartı. $gelisim: gelisim_hazirla() çıktısı (app/gelisim.php).
   Sağ göz = daire + bordo çizgi, sol göz = kare + mavi çizgi (renge ek olarak şekille de ayrılır). */
?><section class="card">
  <div class="card-head"><h2><?= icon('chart') ?> Göz gelişimi</h2><small class="muted"><?= e($gelisim['olcum']) ?> reçete</small></div>

  <?php if ($gelisim['hizli']): ?>
    <div class="push-note" style="margin-bottom:12px"><b>Reçete değişimi hızlı görünüyor</b>
      <p><?= e(implode(' · ', $gelisim['hizli'])) ?>. Veliye, çocuğun göz doktoruna görünmesini hatırlatmanız önerilir.</p></div>
  <?php endif; ?>

  <svg viewBox="0 0 <?= e($gelisim['w']) ?> <?= e($gelisim['h']) ?>" style="width:100%;height:auto;color:var(--ink,#241a15)" role="img" aria-label="Sağ ve sol göz için reçete değişim grafiği">
    <?php foreach ($gelisim['ytick'] as $t): ?>
      <line x1="44" x2="<?= e($gelisim['w'] - 12) ?>" y1="<?= e($t['y']) ?>" y2="<?= e($t['y']) ?>" stroke="currentColor" stroke-opacity=".14"/>
      <text x="40" y="<?= e($t['y'] + 3.5) ?>" text-anchor="end" font-size="10" fill="currentColor" fill-opacity=".7"><?= e($t['etiket']) ?></text>
    <?php endforeach; ?>
    <?php foreach ($gelisim['xtick'] as $t): ?>
      <text x="<?= e($t['x']) ?>" y="<?= e($gelisim['h'] - 8) ?>" text-anchor="<?= e($t['ank']) ?>" font-size="10" fill="currentColor" fill-opacity=".7"><?= e($t['etiket']) ?></text>
    <?php endforeach; ?>
    <?php if ($gelisim['sol'] !== ''): ?>
      <polyline points="<?= e($gelisim['sol']) ?>" fill="none" stroke="#1f6f8b" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
      <?php foreach ($gelisim['solNokta'] as $n): ?><rect x="<?= e($n['x'] - 3.4) ?>" y="<?= e($n['y'] - 3.4) ?>" width="6.8" height="6.8" fill="#1f6f8b"><title><?= e($n['etiket']) ?></title></rect><?php endforeach; ?>
    <?php endif; ?>
    <?php if ($gelisim['sag'] !== ''): ?>
      <polyline points="<?= e($gelisim['sag']) ?>" fill="none" stroke="<?= e(brand_color()) ?>" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
      <?php foreach ($gelisim['sagNokta'] as $n): ?><circle cx="<?= e($n['x']) ?>" cy="<?= e($n['y']) ?>" r="3.8" fill="<?= e(brand_color()) ?>"><title><?= e($n['etiket']) ?></title></circle><?php endforeach; ?>
    <?php endif; ?>
  </svg>

  <ul class="kv" style="margin-top:6px">
    <li><span><span style="color:<?= e(brand_color()) ?>">●</span> Sağ göz</span><b>son <?= e($gelisim['sagSon']) ?> D</b></li>
    <li><span><span style="color:#1f6f8b">■</span> Sol göz</span><b>son <?= e($gelisim['solSon']) ?> D</b></li>
  </ul>
  <p class="hint">Değerler küre eşdeğeridir (küre + silindir/2, dioptri). Farklı hekimlerin ölçümleri arasında küçük farklar olabilir.
    Bu bir tanı değil, kayıtlı reçetelerin görsel özetidir. Uyarı eşiği: yılda <?= e($gelisim['esik']) ?> D.</p>
</section>
