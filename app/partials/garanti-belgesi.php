<?php
declare(strict_types=1);

/* 4.16.0 — Garanti kartı (yazdırılır). 4.22.0: ortak döküm tasarımı (app/dokum.php).
   Beklenen: $gBelge (garanti_bul satırları, aynı müşteri), $shop */
$gIlk = $gBelge[0];
$gMusteri = trim((string) $gIlk['first_name'] . ' ' . (string) $gIlk['last_name']);
$gNolar = array_map(static fn(array $g): string => garanti_no((int) $g['id']), $gBelge);
$gSonBitis = max(array_map(static fn(array $g): string => (string) $g['bitis'], $gBelge));
$gKalan = garanti_durumu(['durum' => 'aktif', 'bitis' => $gSonBitis]);

echo dokum_ust('GARANTİ BELGESİ', [
    ['Belge no', count($gNolar) > 1 ? $gNolar[0] . ' +' . (count($gNolar) - 1) : $gNolar[0]],
    ['Başlangıç', date_tr((string) $gIlk['baslangic'])],
    ['Sipariş', $gIlk['order_id'] ? order_no((int) $gIlk['order_id']) : '—'],
]);
echo dokum_selam('Sayın', $gMusteri !== '' ? $gMusteri : 'Değerli müşterimiz',
    'Satın aldığınız ' . (count($gBelge) > 1 ? count($gBelge) . ' ürün' : 'ürün') . ' aşağıdaki süreler boyunca <b>' . e($shop) . '</b> garantisi altındadır. Her kalemin karekodunu okutarak garantinizi dilediğiniz an görebilirsiniz.',
    dokum_vurgu('Garanti bitişi', date_tr($gSonBitis), $gKalan['kod'] === 'gecerli' ? garanti_kalan_metni($gKalan['kalan_gun']) . ' kaldı' : 'süresi doldu', 'ok'),
    dokum_rozet('ok', 'sgk', count($gBelge) . ' kalem garanti kapsamında'));

foreach ($gBelge as $g):
    $gUrl = garanti_url($g);
    $gd = garanti_durumu($g);
?>
<article class="garanti-kalem<?= $gd['kod'] !== 'gecerli' ? ' bitti' : '' ?>">
  <div class="gk">
    <span><?= e(garanti_no((int) $g['id'])) ?> · <?= e(garanti_kalemleri()[$g['kalem']] ?? $g['kalem']) ?></span>
    <h2><?= e((string) $g['urun']) ?></h2>
    <dl>
      <div><dt>Başlangıç</dt><dd><?= e(date_tr((string) $g['baslangic'])) ?></dd></div>
      <div><dt>Bitiş</dt><dd><?= e(date_tr((string) $g['bitis'])) ?></dd></div>
      <?php if ($g['seri_no']): ?><div><dt>Seri no</dt><dd><?= e((string) $g['seri_no']) ?></dd></div><?php endif; ?>
      <div><dt>Durum</dt><dd><?= e($gd['etiket']) ?></dd></div>
    </dl>
    <?php if ($g['kapsam']): ?><p><?= e((string) $g['kapsam']) ?></p><?php endif; ?>
  </div>
  <?php if ($gUrl !== ''): ?><div class="gk-kod"><span><?= qr_svg($gUrl, 112, 'Q') ?></span><small>Okutun · garantiniz</small></div><?php endif; ?>
</article>
<?php endforeach;

$gKosullar = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', garanti_kosullari())))));
if ($gKosullar) {
    echo '<div class="bolum">' . dokum_kutu('Garanti koşulları', '<ol class="maddeler' . (count($gKosullar) < 4 ? ' tek' : '') . '">' . implode('', array_map(static fn($s) => '<li>' . e($s) . '</li>', $gKosullar)) . '</ol>') . '</div>';
}
echo dokum_imzalar([
    ['Satıcı (kaşe / imza)', $shop, 'Ürünün yukarıdaki süre boyunca garanti kapsamında olduğunu taahhüt eder.'],
    ['Müşteri', $gMusteri, 'Garanti belgesini ve koşullarını teslim aldım.'],
]);
echo dokum_son('<p>Bu belgeyi saklayın; garanti talebinde <b>ürünle birlikte getirin</b>.</p><p>' . e(implode(' · ', $gNolar)) . '</p>');
