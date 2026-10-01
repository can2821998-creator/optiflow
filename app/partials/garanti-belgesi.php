<?php
declare(strict_types=1);

/* 4.16.0 — Garanti kartı (yazdırılır). Beklenen: $gBelge (garanti_bul satırları, aynı müşteri), $shop */
$gIlk = $gBelge[0];
$gMusteri = trim((string) $gIlk['first_name'] . ' ' . (string) $gIlk['last_name']);
?>
<div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">GARANTİ BELGESİ</p><h1><?= e($shop) ?></h1><p><?= e(setting('shop_address')) ?><?= setting('shop_phone') ? ' · ' . e(setting('shop_phone')) : '' ?></p></div></div>
  <div class="doc-ref"><b><?= e(implode(' · ', array_map(static fn(array $g): string => garanti_no((int) $g['id']), $gBelge))) ?></b><?= e(date_tr((string) $gIlk['baslangic'])) ?><?= $gIlk['order_id'] ? ' · Sipariş ' . e(order_no((int) $gIlk['order_id'])) : '' ?></div></div>

<table class="kv-table">
  <tr><th>Müşteri</th><td><?= e($gMusteri !== '' ? $gMusteri : '—') ?></td><th>Telefon</th><td><?= e(phone_display($gIlk['phone'] ?? '') ?: '—') ?></td></tr>
</table>

<?php foreach ($gBelge as $g): $gUrl = garanti_url($g); ?>
  <div class="track-qr garanti-kalem">
    <?php if ($gUrl !== ''): ?><div class="track-qr-code"><?= qr_svg($gUrl, 112, 'Q') ?></div><?php endif; ?>
    <div class="track-qr-txt">
      <b><?= e(garanti_no((int) $g['id'])) ?> · <?= e(garanti_kalemleri()[$g['kalem']] ?? $g['kalem']) ?>: <?= e((string) $g['urun']) ?></b>
      <p>Garanti başlangıcı <b><?= e(date_tr((string) $g['baslangic'])) ?></b> · bitişi <b><?= e(date_tr((string) $g['bitis'])) ?></b><?= $g['seri_no'] ? ' · Seri no <b>' . e((string) $g['seri_no']) . '</b>' : '' ?></p>
      <?php if ($g['kapsam']): ?><p><?= e((string) $g['kapsam']) ?></p><?php endif; ?>
      <?php if ($gUrl !== ''): ?><small>Karekodu okutarak garanti durumunuzu görebilirsiniz · <?= e(preg_replace('#^https?://#', '', $gUrl)) ?></small><?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>

<h2>Garanti koşulları</h2>
<ul class="garanti-kosul">
  <?php foreach (explode("\n", str_replace("\r", '', garanti_kosullari())) as $satir): if (trim($satir) === '') { continue; } ?>
    <li><?= e(trim($satir)) ?></li>
  <?php endforeach; ?>
</ul>

<div class="sign-box">
  <div class="box"><small>Satıcı (kaşe / imza)</small><div class="who"><?= e($shop) ?></div><p>Ürünün yukarıdaki süre boyunca garanti kapsamında olduğunu taahhüt eder.</p><div class="pen-line"></div></div>
  <div class="box"><small>Müşteri</small><div class="who"><?= e($gMusteri !== '' ? $gMusteri : ' ') ?></div><p>Garanti belgesini ve koşullarını teslim aldım.</p><div class="pen-line"></div></div>
</div>
<div class="foot"><span class="foot-mark"><?= brand_mark() ?></span><span><?= e($shop) ?></span><span class="foot-note">Bu kartı saklayın; garanti talebinde ürünle birlikte getirin.</span></div>
