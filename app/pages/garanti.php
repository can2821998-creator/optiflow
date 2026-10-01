<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.16.0 — Garanti kartı karekodu sayfası (müşteri; oturum gerektirmez).
   Adres: garanti.php?m=<mağaza>&k=<22 karakter anahtar>. Gösterilen: ürün,
   garanti süresi, kalan süre, taleplerin durumu. Fiyat, maliyet, telefon ve
   soyadın tamamı GÖSTERİLMEZ. Personel aynı karekodu "Barkod okut" ile
   okutunca garanti kaydı açılır.
   ========================================================================== */

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$g = null;
try {
    $g = garanti_bul_token((string) ($_GET['k'] ?? ''));
} catch (Throwable $e) {
    $g = null;   // tablo yok (şema eski) ya da veritabanı hatası: "bulunamadı"
}
$talepler = [];
if ($g) {
    try {
        $talepler = garanti_talepleri((int) $g['id']);
    } catch (Throwable $e) {
        $talepler = [];
    }
}

$shop    = setting('shop_name', 'OptiFlow');
$adres   = setting('shop_address', '');
$telefon = setting('shop_phone', '');
$telHam  = preg_replace('/\D+/', '', $telefon) ?? '';
$telUrl  = $telHam !== '' ? 'tel:' . $telHam : '';
$waNo    = $telHam !== '' ? (str_starts_with($telHam, '90') ? $telHam : '90' . ltrim($telHam, '0')) : '';

$d = $g ? garanti_durumu($g) : null;
$ton = match ($d['kod'] ?? '') {
    'gecerli' => $d['kalan_gun'] <= 30 ? 'gold' : 'green',
    'bitti', 'iptal' => 'gray',
    default => 'gray',
};
$waUrl = '';
if ($g && $waNo !== '') {
    $waUrl = 'https://wa.me/' . $waNo . '?text=' . rawurlencode('Merhaba, ' . garanti_no((int) $g['id']) . ' numaralı garantim hakkında bilgi almak istiyorum.');
}
$kalemAd = $g ? (garanti_kalemleri()[$g['kalem']] ?? 'Ürün') : '';
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#faf6ef" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#150f0d" media="(prefers-color-scheme: dark)">
<title><?= e(($g ? 'Garanti · ' : 'Garanti bulunamadı · ') . $shop) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('musteri.css')) ?>">
<?= function_exists('brand_style_tag') ? brand_style_tag() : '' ?>
</head>
<body class="tk-page">

<main class="tk tk-tone-<?= e($ton) ?>">
  <header class="tk-head">
    <span class="tk-mark" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
    </span>
    <div class="tk-brand">
      <b><?= e($shop) ?></b>
      <small>Garanti sorgulama</small>
    </div>
    <?php if ($telUrl !== ''): ?>
      <a class="tk-call" href="<?= e($telUrl) ?>" aria-label="Mağazayı ara">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </a>
    <?php endif; ?>
  </header>

<?php if (!$g): ?>
  <section class="tk-card tk-center tk-tone-gray">
    <h1>Garanti bulunamadı</h1>
    <p>Bu bağlantı geçersiz olabilir. Garanti kartınızla mağazamıza başvurabilir ya da bizi arayabilirsiniz.</p>
    <?php if ($telUrl !== ''): ?><div class="tk-stack"><a class="tk-btn is-ghost" href="<?= e($telUrl) ?>">Mağazayı ara</a></div><?php endif; ?>
  </section>
<?php else: ?>
  <section class="tk-card tk-hero" aria-labelledby="garanti-baslik">
    <div class="tk-hero-top">
      <span class="tk-pill"><span><?= e($kalemAd) ?></span><b><?= e(garanti_no((int) $g['id'])) ?></b></span>
    </div>
    <?php $ad = garanti_musteri_kisa($g['first_name'] ?? '', $g['last_name'] ?? ''); if ($ad !== ''): ?><span class="tk-hi">Sayın <?= e($ad) ?>,</span><?php endif; ?>
    <h1 id="garanti-baslik"><?= e($d['etiket']) ?></h1>
    <p class="tk-lead"><?php if ($d['kod'] === 'gecerli'): ?>Ürününüz <b><?= e(date_tr((string) $g['bitis'])) ?></b> tarihine kadar garanti kapsamında. Kalan süre: <b><?= e(garanti_kalan_metni($d['kalan_gun'])) ?></b>.<?php elseif ($d['kod'] === 'bitti'): ?>Garanti süresi <?= e(date_tr((string) $g['bitis'])) ?> tarihinde sona erdi. Tamir ve bakım için mağazamıza bekleriz.<?php else: ?>Bu garanti kaydı iptal edilmiştir. Ayrıntı için mağazamıza başvurun.<?php endif; ?></p>
  </section>

  <section class="tk-card tk-info" aria-label="Garanti bilgileri">
    <div class="tk-eyebrow">Garanti bilgileri</div>
    <dl class="tk-kv">
      <div><dt>Ürün</dt><dd><?= e((string) $g['urun']) ?></dd></div>
      <?php if ($g['seri_no']): ?><div><dt>Seri no</dt><dd><?= e((string) $g['seri_no']) ?></dd></div><?php endif; ?>
      <div><dt>Başlangıç</dt><dd><?= e(date_tr((string) $g['baslangic'])) ?></dd></div>
      <div><dt>Bitiş</dt><dd><?= e(date_tr((string) $g['bitis'])) ?></dd></div>
      <?php if ($g['kapsam']): ?><div><dt>Kapsam</dt><dd><?= e((string) $g['kapsam']) ?></dd></div><?php endif; ?>
    </dl>
  </section>

  <?php if ($talepler): ?>
  <section class="tk-card tk-info" aria-label="Garanti talepleri">
    <div class="tk-eyebrow">Talepleriniz</div>
    <ul class="tk-talepler">
      <?php foreach ($talepler as $t): [$tAd] = garanti_talep_durumlari()[$t['durum']] ?? [$t['durum']]; ?>
        <li>
          <b><?= e($t['durum'] === 'tamamlandi' && $t['sonuc_tur'] ? (garanti_sonuclari()[$t['sonuc_tur']] ?? $tAd) : ($t['durum'] === 'tedarikcide' ? 'Üreticiye / tedarikçiye gönderildi' : ($t['durum'] === 'acik' ? 'Mağazada inceleniyor' : $tAd))) ?></b>
          <span><?= e(date_tr((string) $t['created_at'])) ?> · <?= e((string) $t['sikayet']) ?></span>
          <?php if ($t['sonuc']): ?><small><?= e((string) $t['sonuc']) ?></small><?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="tk-card tk-contact">
    <h2>Garanti talebi için</h2>
    <p>Ürününüzü ve garanti kartınızı mağazamıza getirmeniz yeterli.</p>
    <?php if ($telUrl !== '' || $waUrl !== ''): ?>
    <div class="tk-tiles">
      <?php if ($telUrl !== ''): ?><a class="tk-tile is-call" href="<?= e($telUrl) ?>">Ara</a><?php endif; ?>
      <?php if ($waUrl !== ''): ?><a class="tk-tile is-wa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
<?php endif; ?>

  <footer class="tk-foot">
    <b><?= e($shop) ?></b>
    <?php if ($adres !== ''): ?><span><?= e($adres) ?></span><?php endif; ?>
    <?php if ($telefon !== ''): ?><a href="<?= e($telUrl) ?>"><?= e($telefon) ?></a><?php endif; ?>
    <small>Bu sayfa yalnızca garanti durumunu gösterir; fiyat ve kişisel bilgi içermez.</small>
  </footer>
</main>
</body>
</html>
