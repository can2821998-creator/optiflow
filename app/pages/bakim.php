<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   Gözlük bakım kartı — oturum gerektirmez, sipariş bilgisi içermez.
   Teslim fişindeki küçük karekod bu sayfayı açar. İçerik herkes için aynıdır;
   yalnızca mağaza iletişim bilgileri Ayarlar'dan gelir.
   Görünüm assets/musteri.css içindedir.
   ========================================================================== */

header('Cache-Control: private, max-age=300');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

/* ---------- Mağaza bilgileri ---------- */
$shop     = setting('shop_name', 'OptiFlow');
$adres    = setting('shop_address', '');
$telefon  = setting('shop_phone', '');
$telHam   = preg_replace('/\D+/', '', $telefon) ?? '';
$waNumara = $telHam !== '' ? (str_starts_with($telHam, '90') ? $telHam : '90' . ltrim($telHam, '0')) : '';
$telUrl   = $telHam !== '' ? 'tel:' . $telHam : '';
$waUrl    = $waNumara !== '' ? 'https://wa.me/' . $waNumara . '?text=' . rawurlencode('Merhaba, gözlüğüm için bakım/ayar konusunda bilgi almak istiyorum.') : '';

$haritaOzel = trim(setting('shop_map_url', ''));
if ($haritaOzel !== '' && preg_match('#^https://[^\s]+$#i', $haritaOzel)) {
    $haritaUrl = $haritaOzel;
} elseif ($adres !== '') {
    $haritaUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim($adres . ' ' . $shop));
} else {
    $haritaUrl = '';
}
$karoVar = $telUrl !== '' || $waUrl !== '' || $haritaUrl !== '';

$saatler = [];
foreach (explode("\n", str_replace("\r", '', setting('shop_hours', ''))) as $satir) {
    $satir = trim($satir);
    if ($satir !== '') {
        $saatler[] = $satir;
    }
}
$saatler = array_slice($saatler, 0, 7);
$iletisimVar = $karoVar || count($saatler) > 0;

/* ---------- İçerik ---------- */
$temizlik = [
    ['baslik' => 'Ellerinizi yıkayıp kurulayın', 'metin' => 'Yağlı ya da nemli parmaklar cama leke taşır ve temizliği zorlaştırır.'],
    ['baslik' => 'Ilık akan suyla çalkalayın', 'metin' => 'Sıcak değil, ılık su. Üzerindeki toz ve kum taneleri akıp gitsin; kuru bezle silmek camı çizer.'],
    ['baslik' => 'Bir damla bulaşık deterjanı sürün', 'metin' => 'Losyonsuz, nazik bir deterjanı parmak uçlarınızla cama, çerçeveye, burun pedlerine ve saplara hafifçe yayın.'],
    ['baslik' => 'Ilık suyla iyice durulayın', 'metin' => 'Sabun kalıntısı kalmasın; kalırsa cam bulanık görünür.'],
    ['baslik' => 'Temiz mikrofiber bezle kurulayın', 'metin' => 'Tüy bırakmayan temiz bir bezle bastırarak kurulayın; sertçe ovmayın.'],
];
$yapmayin = [
    ['baslik' => 'Gömlek ucu, peçete veya kâğıt havlu ile silmeyin', 'metin' => 'Gözle görülmeyen toz cama sürtünüp ince çizikler bırakır.'],
    ['baslik' => 'Alkol, aseton, çamaşır suyu ya da amonyaklı cam temizleyici kullanmayın', 'metin' => 'Camın kaplamasına ve bazı çerçevelere zarar verebilir.'],
    ['baslik' => 'Sıcak sudan ve ısıdan uzak tutun', 'metin' => 'Araba torpidosunda, kaloriferin üstünde, sauna ya da sıcak güneşte bırakmayın; çerçeve eğilebilir, cam kaplaması bozulabilir.'],
    ['baslik' => 'Ağzınızla buğulayıp silmeyin', 'metin' => 'Nem ve sürtme birleşince cam çizilebilir; ıslak temizlik yeterlidir.'],
    ['baslik' => 'Ultrasonik temizleyiciyi kendi başınıza denemeyin', 'metin' => 'Kaplamalı camlar için uygun olup olmadığını önce bize sorun.'],
];
$gunluk = [
    ['baslik' => 'İki elle takıp çıkarın', 'metin' => 'Tek elle çekmek sapları zamanla eğer ve gevşetir.'],
    ['baslik' => 'Kullanmadığınızda kılıfında saklayın', 'metin' => 'Camların masaya değecek şekilde durmasına izin vermeyin; çantada kılıfsız taşımayın.'],
    ['baslik' => 'Başınızın üstünde taşımayın', 'metin' => 'Toka gibi kullanmak sapları açar ve gözlüğün oturuşunu bozar.'],
    ['baslik' => 'Saç spreyi ve parfümü gözlükten önce sıkın', 'metin' => 'Kaplama üzerinde film bırakabilir; gözlüğü çıkarıp sıkmanız yeterli.'],
    ['baslik' => 'Bezinizi düzenli yıkayın', 'metin' => 'Kirli bez cam üzerinde toz taşıyıp çizer. Yumuşatıcı kullanmadan yıkayıp kurutun.'],
];
$alisma = [
    ['baslik' => 'İlk günlerde alışmak normaldir', 'metin' => 'Yeni gözlük ya da yeni numara ilk birkaç günden iki haftaya kadar farklı hissettirebilir: mesafeleri değişik algılamak, hafif yorgunluk gibi. Gözlüğü gün boyu düzenli takmak alışmayı kolaylaştırır.'],
    ['baslik' => 'Uzun sürerse bize danışın', 'metin' => 'Baş ağrısı, baş dönmesi ya da bulanıklık iki haftadan uzun sürerse veya çok rahatsız ediyorsa beklemeyin; bize uğrayın. Gerekirse göz doktorunuza danışmanızı öneririz.'],
    ['baslik' => 'Muayenenizi aksatmayın', 'metin' => 'Reçetenizin güncel kalması için göz doktorunuzun önerdiği aralıklarla muayene olun.'],
];
$sayfaBasligi = 'Gözlük bakım kartı · ' . $shop;

/* Gözlük dayanışması kartı: yalnızca iki toplam sayı (kişisel bilgi yok). Modül yüklenemezse sayı gösterilmez. */
$bagis = ['cerceve' => 0, 'gozluk' => 0, 'yil' => null];
try {
    require_once dirname(__DIR__) . '/donation.php';
    $bagis = donation_public_stats();
} catch (Throwable $e) {
    $bagis = ['cerceve' => 0, 'gozluk' => 0, 'yil' => null];
}
$bagisSayi = $bagis['cerceve'] > 0 || $bagis['gozluk'] > 0;
$bagisWa   = $waNumara !== '' ? 'https://wa.me/' . $waNumara . '?text=' . rawurlencode('Merhaba, kullanmadığım gözlük çerçevesini bağışlamak istiyorum.') : '';
$bagisGoster = $bagisWa !== '';

/* "Çerçeveleri telefonunuzda deneyin" (sitedeki sanal deneme): uygulama yüklü değilse kart hiç çıkmaz */
try {
    require_once dirname(__DIR__) . '/deneme.php';
    $denemeUrl = deneme_url();
} catch (Throwable $e) {
    $denemeUrl = '';
}
$denemeGoster = $denemeUrl !== '';
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#faf6ef" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#150f0d" media="(prefers-color-scheme: dark)">
<title><?= e($sayfaBasligi) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="stylesheet" href="<?= e(asset('musteri.css')) ?>">
<?= brand_style_tag() ?>
</head>
<body class="tk-page">

<main class="tk tk-tone-brand">
  <header class="tk-head">
    <span class="tk-mark" aria-hidden="true">
      <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round"><path d="M12 36c0-6.5 4.5-11 10-11s10 4.5 10 11-4.5 10-10 10-10-4.5-10-10Zm20 0c0-6.5 4.5-11 10-11s10 4.5 10 11-4.5 10-10 10-10-4.5-10-10Zm-10-3h10M12 31l-5-2m45 2 5-2"/></svg>
    </span>
    <div class="tk-brand">
      <b><?= e($shop) ?></b>
      <small>Bakım kartı</small>
    </div>
    <?php if ($telUrl !== ''): ?>
      <a class="tk-call" href="<?= e($telUrl) ?>" aria-label="Mağazayı ara">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </a>
    <?php endif; ?>
  </header>

  <section class="tk-card tk-hero" aria-labelledby="bakim-baslik">
    <svg class="tk-hero-art" viewBox="0 0 220 120" fill="none" stroke="currentColor" stroke-linecap="round" aria-hidden="true">
      <circle cx="78" cy="62" r="34" stroke-width="3"/><circle cx="146" cy="62" r="34" stroke-width="3"/>
      <path d="M112 56q10-9 20 0" stroke-width="3"/><path d="M44 56 14 40" stroke-width="2.4"/><path d="M180 56l30-16" stroke-width="2.4"/>
      <circle cx="78" cy="62" r="24" stroke-width="1" opacity=".5"/><circle cx="146" cy="62" r="24" stroke-width="1" opacity=".5"/>
    </svg>
    <div class="tk-hero-top">
      <span class="tk-medal" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c3.2 4.2 5.5 6.9 5.5 10a5.5 5.5 0 0 1-11 0C6.5 9.9 8.8 7.2 12 3Z"/><path d="M9.6 14.2a2.6 2.6 0 0 0 2.4 1.9"/></svg>
      </span>
      <span class="tk-pill"><span>Gözlük</span><b>Bakım kartı</b></span>
    </div>
    <h1 id="bakim-baslik" class="tk-care-title">Gözlüğünüzü nasıl temizlersiniz?</h1>
    <p class="tk-lead">Doğru temizlik, camlarınızın kaplamasını ve çerçevenizi uzun yıllar korur. Bir dakikanızı alır.</p>
  </section>

  <section class="tk-card tk-sec">
    <div class="tk-eyebrow">Adım adım temizlik</div>
    <ol class="tk-how">
      <?php foreach ($temizlik as $n => $t): ?>
        <li>
          <span class="tk-how-n" aria-hidden="true"><?= e($n + 1) ?></span>
          <span class="tk-how-t"><b><?= e($t['baslik']) ?></b><span><?= e($t['metin']) ?></span></span>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>

  <section class="tk-card tk-sec">
    <div class="tk-eyebrow">Bunları yapmayın</div>
    <ul class="tk-list">
      <?php foreach ($yapmayin as $t): ?>
        <li>
          <span class="tk-ic no" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg></span>
          <span class="tk-how-t"><b><?= e($t['baslik']) ?></b><span><?= e($t['metin']) ?></span></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="tk-card tk-sec">
    <div class="tk-eyebrow">Günlük kullanım ve saklama</div>
    <ul class="tk-list">
      <?php foreach ($gunluk as $t): ?>
        <li>
          <span class="tk-ic ok" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg></span>
          <span class="tk-how-t"><b><?= e($t['baslik']) ?></b><span><?= e($t['metin']) ?></span></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="tk-card tk-sec">
    <div class="tk-eyebrow">Yeni gözlüğünüze alışırken</div>
    <ul class="tk-list">
      <?php foreach ($alisma as $t): ?>
        <li>
          <span class="tk-ic info" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M12 11v6M12 7.2v.01"/></svg></span>
          <span class="tk-how-t"><b><?= e($t['baslik']) ?></b><span><?= e($t['metin']) ?></span></span>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php if ($denemeGoster): ?>
    <section class="tk-card tk-try">
      <div class="tk-eyebrow">Yeni</div>
      <h2>Çerçeveleri yüzünüzde deneyin</h2>
      <p>Telefonunuzun kamerasıyla farklı çerçeve stillerini deneyin. Görüntü telefonunuzdan çıkmaz.</p>
      <a class="tk-btn" href="<?= e($denemeUrl) ?>" target="_blank" rel="noopener">Denemeye başla</a>
    </section>
  <?php endif; ?>

  <?php if ($bagisGoster): ?>
    <section class="tk-card tk-give">
      <div class="tk-eyebrow">Gözlük dayanışması</div>
      <h2>Eski gözlüğünüz başkasının ışığı olsun</h2>
      <p>Kullanmadığınız gözlük çerçevelerini toplar, temizler ve gözlüğe ulaşmakta zorlanan insanlarla buluştururuz; camlarını biz karşılarız.</p>
      <?php if ($bagisSayi): ?><p class="tk-give-count"><b><?= e($bagis['cerceve']) ?></b> çerçeve bağışlandı · <b><?= e($bagis['gozluk']) ?></b> gözlük sahibini buldu</p><?php endif; ?>
      <a class="tk-btn is-ghost" href="<?= e($bagisWa) ?>" target="_blank" rel="noopener">Bağış yapmak istiyorum</a>
    </section>
  <?php endif; ?>

  <?php if ($iletisimVar): ?>
    <section class="tk-card tk-contact">
      <h2>Kendiniz onarmayın, bize uğrayın</h2>
      <p>Saplar gevşediyse, burun pedi sarardı ya da düştüyse, cam çizildiyse sıkıp bükmeyin; gözlüğünüzü birlikte ayarlayalım.</p>
      <?php if ($karoVar): ?>
      <div class="tk-tiles">
        <?php if ($telUrl !== ''): ?>
          <a class="tk-tile is-call" href="<?= e($telUrl) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Ara
          </a>
        <?php endif; ?>
        <?php if ($waUrl !== ''): ?>
          <a class="tk-tile is-wa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.13c-.25.7-1.24 1.28-2.02 1.45-.55.11-1.26.2-3.66-.78-2.97-1.23-4.88-4.24-5.03-4.44-.15-.2-1.2-1.6-1.2-3.05 0-1.45.75-2.16 1.02-2.46.27-.3.58-.37.78-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.25.6.85 2.07.92 2.22.07.15.12.33.02.53-.1.2-.15.33-.3.5-.15.18-.31.4-.45.54-.15.15-.3.31-.13.61.17.3.76 1.25 1.63 2.03 1.12 1 2.06 1.31 2.36 1.46.3.15.48.13.65-.08.18-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.28.1 1.76.83 2.06.98.3.15.5.22.57.35.08.13.08.73-.17 1.43Z"/></svg>
            WhatsApp
          </a>
        <?php endif; ?>
        <?php if ($haritaUrl !== ''): ?>
          <a class="tk-tile is-map" href="<?= e($haritaUrl) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Yol tarifi
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($saatler): ?>
        <div class="tk-hours">
          <b>Çalışma saatleri</b>
          <?php foreach ($saatler as $sa): ?><span><?= e($sa) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <footer class="tk-foot">
    <b><?= e($shop) ?></b>
    <?php if ($adres !== ''): ?><span><?= e($adres) ?></span><?php endif; ?>
    <?php if ($telefon !== ''): ?><a href="<?= e($telUrl) ?>"><?= e($telefon) ?></a><?php endif; ?>
    <small>Bu kart genel bilgi amaçlıdır. Gözlüğünüzle ilgili özel bir sorunuz varsa bize danışın.</small>
  </footer>
</main>

</body>
</html>
