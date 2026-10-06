<?php
declare(strict_types=1);

/* 4.16.0 — Garanti kartı (yazdırılır). 4.22.0: ortak döküm tasarımı (app/dokum.php).
   Beklenen: $gBelge (garanti_bul satırları, aynı müşteri), $shop */
$gIlk = $gBelge[0];
$gMusteri = trim((string) $gIlk['first_name'] . ' ' . (string) $gIlk['last_name']);
$gNolar = array_map(static fn(array $g): string => garanti_no((int) $g['id']), $gBelge);
$gSonBitis = max(array_map(static fn(array $g): string => (string) $g['bitis'], $gBelge));
$gKalan = garanti_durumu(['durum' => 'aktif', 'bitis' => $gSonBitis]);

echo dokum_bas([
    'etiket' => 'GARANTİ BELGESİ', 'no' => count($gNolar) > 1 ? $gNolar[0] . '+' : $gNolar[0], 'tarih' => date_tr((string) $gIlk['baslangic']),
    'kucuk' => 'Sayın', 'baslik' => $gMusteri !== '' ? $gMusteri : 'Değerli müşterimiz',
    'rozet' => ['ok', 'kalkan', count($gBelge) . ' kalem garanti kapsamında'],
    'metin' => 'Satın aldığınız ürünler aşağıdaki süreler boyunca <b>' . e($shop) . '</b> garantisi altındadır. Kalemin karekodunu okutarak garantinizi dilediğiniz an görebilirsiniz.',
    'rota' => [['Başlangıç', date('d.m', strtotime((string) $gIlk['baslangic'])), date('Y', strtotime((string) $gIlk['baslangic']))], ['Bitiş', date('d.m', strtotime($gSonBitis)), date('Y', strtotime($gSonBitis))]],
    'vurgu' => ['Garanti bitişi', date_tr($gSonBitis), $gKalan['kod'] === 'gecerli' ? garanti_kalan_metni($gKalan['kalan_gun']) . ' kaldı' : 'süresi doldu', 'ok'],
    'alanlar' => [['belge', 'Sipariş', $gIlk['order_id'] ? order_no((int) $gIlk['order_id']) : ''], ['tel', 'Telefon', phone_display($gIlk['phone'] ?? '')], ['kalkan', 'Belge', implode(', ', $gNolar)]],
]);

foreach ($gBelge as $g):
    $gUrl = garanti_url($g);
    $gd = garanti_durumu($g);
    $gBas = strtotime((string) $g['baslangic']) ?: time();
    $gSon = strtotime((string) $g['bitis']) ?: time();
    $gOran = $gSon > $gBas ? (time() - $gBas) / ($gSon - $gBas) : 1.0;
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
    <?= dokum_ilerleme($gOran, 'Garanti süresi', $gd['kod'] === 'gecerli' ? garanti_kalan_metni($gd['kalan_gun']) . ' kaldı' : 'sona erdi') ?>
    <?php if ($g['kapsam']): ?><p><?= e((string) $g['kapsam']) ?></p><?php endif; ?>
  </div>
  <?php if ($gUrl !== ''): ?><div class="gk-kod"><span><?= qr_svg($gUrl, 112, 'Q') ?></span><small>Okutun, garantinizi görün</small></div><?php endif; ?>
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
echo dokum_son('<p>Bu belgeyi saklayın; garanti talebinde <b>ürünle birlikte getirin</b>.</p>');
