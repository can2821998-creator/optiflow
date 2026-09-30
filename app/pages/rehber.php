<?php
declare(strict_types=1);

/**
 * Rehber (blog) — herkese açık sayfa. Veritabanına ve oturuma dokunmaz, bootstrap yüklemez.
 *   rehber.php          → yazı listesi
 *   rehber.php?y=<slug> → tek yazı
 */
require dirname(__DIR__) . '/pazarlama.php';
require_once dirname(__DIR__) . '/rehber.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
$gaCsp = ga_csp();
header(
    "Content-Security-Policy: default-src 'self'; "
    . "img-src 'self' data:" . $gaCsp['img'] . "; "
    . "style-src 'self' 'unsafe-inline'; "
    . "script-src 'self'" . $gaCsp['script'] . "; "
    . "connect-src 'self'" . $gaCsp['connect'] . "; "
    . "frame-ancestors 'self'; base-uri 'self'"
);

$slug = isset($_GET['y']) ? (string) $_GET['y'] : '';
$hepsi = rehber_hepsi(true);
$yazi = null;
if ($slug !== '') {
    $yazi = rehber_gecerli_slug($slug) ? ($hepsi[$slug] ?? null) : null;
    if (!$yazi) {
        http_response_code(404);
    }
}
$bulunamadi = $slug !== '' && !$yazi;

$ld = static fn(array $a): string => str_replace('</', '<\/', (string) json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
$tarihTr = static function (string $d): string {
    $aylar = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $d, $m) ? (int) $m[3] . ' ' . $aylar[(int) $m[2]] . ' ' . $m[1] : '';
};

if ($yazi) {
    $title = ($yazi['seo_baslik'] ?: $yazi['baslik']);
    $title .= mb_strlen($title) <= 48 ? ' | OptiFlow' : '';
    $desc = $yazi['meta'] ?: mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($yazi['govde'])) ?? ''), 0, 155);
    $canon = rehber_url($yazi['slug']);
} else {
    $title = 'Gözlükçüler İçin Rehber: Medula, SGK, ÜTS ve Dükkân Yönetimi';
    $desc = 'Optik mağazaları için uygulamalı rehberler: Medula reçetesi, SGK katkı payı, ÜTS karekod, atölye takibi, stok ve kasa yönetimi.';
    $canon = rehber_url();
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="<?= $bulunamadi ? 'noindex,follow' : 'index,follow' ?>">
<title><?= pz_e($bulunamadi ? 'Yazı bulunamadı | OptiFlow Rehber' : $title) ?></title>
<meta name="description" content="<?= pz_e($desc) ?>">
<?php if (!$bulunamadi): ?><link rel="canonical" href="<?= pz_e($canon) ?>"><?php endif; ?>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preload" as="font" type="font/woff2" href="assets/fonts/manrope-latin-wght-normal.woff2" crossorigin>
<meta property="og:type" content="<?= $yazi ? 'article' : 'website' ?>">
<meta property="og:site_name" content="OptiFlow">
<meta property="og:title" content="<?= pz_e($yazi ? $yazi['baslik'] : $title) ?>">
<meta property="og:description" content="<?= pz_e($desc) ?>">
<meta property="og:url" content="<?= pz_e($canon) ?>">
<meta property="og:image" content="https://optiflow.com.tr/assets/og-optiflow.png">
<meta property="og:locale" content="tr_TR">
<meta name="twitter:card" content="summary_large_image">
<?php if ($yazi): ?>
<script type="application/ld+json"><?= $ld([
    '@context' => 'https://schema.org', '@type' => 'Article',
    'headline' => mb_substr($yazi['baslik'], 0, 110), 'description' => $desc, 'inLanguage' => 'tr',
    'datePublished' => $yazi['yayin_tarihi'] ?: $yazi['guncelleme'], 'dateModified' => $yazi['guncelleme'] ?: $yazi['yayin_tarihi'],
    'mainEntityOfPage' => $canon, 'image' => 'https://optiflow.com.tr/assets/og-optiflow.png',
    'author' => ['@type' => 'Organization', 'name' => 'OptiFlow', 'url' => 'https://optiflow.com.tr/'],
    'publisher' => ['@type' => 'Organization', 'name' => 'OptiFlow', 'logo' => ['@type' => 'ImageObject', 'url' => 'https://optiflow.com.tr/assets/icons/icon-512.png']],
]) ?></script>
<script type="application/ld+json"><?= $ld([
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'OptiFlow', 'item' => 'https://optiflow.com.tr/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Rehber', 'item' => rehber_url()],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $yazi['baslik'], 'item' => $canon],
    ],
]) ?></script>
<?php endif; ?>
<style>
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-wght-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-ext-wght-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: "Manrope Fallback"; src: local("Arial"); size-adjust: 102%; ascent-override: 96%; descent-override: 24%; line-gap-override: 0%; }
:root{--bg:#fafafa;--card:#fff;--ink:#14131f;--ink-soft:#56556a;--line:#ececf1;--primary:#3346ff;--primary-deep:#1c2ecc;--primary-tint:#eceffe;--pop:#db2f20;--pop-deep:#b8261a}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Manrope,"Manrope Fallback",system-ui,Arial,sans-serif;line-height:1.65;font-weight:500;-webkit-font-smoothing:antialiased}
a{color:var(--primary-deep)}
header{border-bottom:1px solid var(--line);background:#fff;position:sticky;top:0;z-index:5}
.wrap{max-width:1080px;margin:0 auto;padding:0 20px}
.narrow{max-width:740px}
.nav{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px}
.wordmark{display:flex;align-items:center;gap:10px;font-weight:800;font-size:19px;text-decoration:none;color:var(--ink)}
.wordmark svg{width:28px;height:28px}
.nav-r{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.nav-r a{font-weight:700;font-size:14px;color:var(--ink-soft);text-decoration:none;padding:8px 12px;border-radius:99px}
.nav-r a:hover{color:var(--ink);background:#f1f1f6}
.nav-r a.cta{background:var(--pop);color:#fff}
.nav-r a.cta:hover{background:var(--pop-deep)}
main.wrap{padding-block:40px 72px}
.crumb{font-size:13.5px;font-weight:700;color:var(--ink-soft);margin:0 0 16px}
.crumb a{color:var(--ink-soft);text-decoration:none}
.crumb a:hover{color:var(--primary-deep)}
h1{font-size:clamp(1.8rem,4.4vw,2.6rem);line-height:1.15;letter-spacing:-.025em;margin:0 0 12px;font-weight:800;text-wrap:balance}
.lede{color:var(--ink-soft);font-weight:600;font-size:1.06rem;margin:0 0 30px;max-width:62ch}
.meta{display:flex;gap:14px;flex-wrap:wrap;font-size:13.5px;font-weight:700;color:var(--ink-soft);margin:0 0 30px;padding-bottom:22px;border-bottom:1px solid var(--line)}
.body{font-size:1.06rem}
.body h2{font-size:1.4rem;line-height:1.3;letter-spacing:-.015em;margin:38px 0 10px;font-weight:800}
.body h3{font-size:1.13rem;margin:26px 0 8px;font-weight:800}
.body p,.body li{color:#2a2938}
.body ul,.body ol{padding-left:22px}
.body li{margin:6px 0}
.body blockquote{margin:22px 0;padding:14px 18px;border-left:4px solid var(--primary);background:var(--primary-tint);border-radius:0 12px 12px 0}
.body table{width:100%;border-collapse:collapse;margin:18px 0;font-size:.95rem}
.body th,.body td{border:1px solid var(--line);padding:9px 11px;text-align:left}
.cta-box{margin:44px 0 0;padding:26px 26px;border-radius:20px;background:linear-gradient(150deg,#4a5aff 0%,#3346ff 45%,#1c2ecc 100%);color:#fff;display:grid;gap:10px}
.cta-box b{font-size:1.2rem;letter-spacing:-.01em}
.cta-box p{margin:0;color:#e3e6ff;font-weight:600}
.cta-box a{justify-self:start;background:#fff;color:var(--primary-deep);font-weight:800;text-decoration:none;padding:12px 20px;border-radius:99px;margin-top:6px}
.related{margin-top:48px}
.related h2{font-size:1.1rem;margin:0 0 14px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px}
.card{display:grid;gap:8px;align-content:start;background:var(--card);border:1px solid var(--line);border-radius:18px;padding:22px;text-decoration:none;color:inherit;transition:border-color .15s,box-shadow .15s,transform .15s}
.card:hover{border-color:#d4d6f5;box-shadow:0 18px 34px -24px rgba(28,46,204,.45);transform:translateY(-2px)}
.card h2,.card h3{font-size:1.12rem;line-height:1.35;margin:0;font-weight:800;letter-spacing:-.01em}
.card p{margin:0;color:var(--ink-soft);font-size:.95rem}
.card small{font-weight:700;color:var(--ink-soft)}
.kicker{display:inline-block;font-size:12.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--primary-deep);margin-bottom:10px}
.empty{padding:30px;border:1.5px dashed var(--line);border-radius:18px;color:var(--ink-soft);font-weight:600}
footer{border-top:1px solid var(--line);padding:26px 0;font-size:13.5px;color:var(--ink-soft);font-weight:700;background:#fff}
footer .wrap{display:flex;gap:16px;flex-wrap:wrap;justify-content:space-between}
footer a{color:var(--ink-soft);text-decoration:none}
footer a:hover{color:var(--ink)}
:focus-visible{outline:3px solid var(--primary);outline-offset:2px;border-radius:6px}
@media (max-width:560px){.nav-r a:not(.cta){display:none}.cta-box{padding:22px}}
</style>
<?= ga_head() ?>
</head>
<body>
<header><div class="wrap nav">
  <a class="wordmark" href="/" aria-label="OptiFlow ana sayfa"><?= pz_logo('lgR') ?>OptiFlow</a>
  <nav class="nav-r" aria-label="Menü">
    <a href="/">Gözlükçü programı</a>
    <a href="rehber.php">Rehber</a>
    <a class="cta" href="kayit.php">30 gün ücretsiz deneyin</a>
  </nav>
</div></header>

<?php if ($yazi): ?>
<main class="wrap narrow">
  <p class="crumb"><a href="/">OptiFlow</a> › <a href="rehber.php">Rehber</a></p>
  <article>
    <h1><?= pz_e($yazi['baslik']) ?></h1>
    <p class="meta">
      <?php if ($yazi['yayin_tarihi']): ?><span><?= pz_e($tarihTr($yazi['yayin_tarihi'])) ?></span><?php endif; ?>
      <span><?= rehber_sure($yazi['govde']) ?> dk okuma</span>
      <?php if ($yazi['guncelleme'] && $yazi['guncelleme'] !== $yazi['yayin_tarihi']): ?><span>Güncelleme: <?= pz_e($tarihTr($yazi['guncelleme'])) ?></span><?php endif; ?>
    </p>
    <div class="body"><?= rehber_temizle($yazi['govde']) ?></div>
  </article>
  <aside class="cta-box">
    <b>Reçeteyi bir daha elle yazmayın.</b>
    <p>OptiFlow; Medula reçetesini siparişe aktarır, SGK katkı payını hesaplar, atölyeyi takip eder. Kredi kartı gerekmez.</p>
    <a href="kayit.php">30 gün ücretsiz deneyin</a>
  </aside>
  <?php $diger = array_slice(array_filter($hepsi, static fn($y) => $y['slug'] !== $yazi['slug']), 0, 3);
  if ($diger): ?>
  <section class="related">
    <h2>Diğer rehberler</h2>
    <div class="grid">
      <?php foreach ($diger as $d): ?>
        <a class="card" href="rehber.php?y=<?= pz_e($d['slug']) ?>"><h3><?= pz_e($d['baslik']) ?></h3><p><?= pz_e(mb_substr($d['meta'], 0, 120)) ?></p></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php elseif ($bulunamadi): ?>
<main class="wrap narrow">
  <h1>Bu yazı bulunamadı</h1>
  <p class="lede">Yazı kaldırılmış ya da adresi değişmiş olabilir.</p>
  <p><a href="rehber.php">Tüm rehberlere dönün →</a></p>
</main>
<?php else: ?>
<main class="wrap">
  <span class="kicker">Rehber</span>
  <h1>Gözlükçüler için uygulamalı rehberler</h1>
  <p class="lede">Medula reçetesi, SGK katkı payı, ÜTS karekod, atölye ve kasa: optik mağazasında her gün karşılaşılan işleri adım adım anlatıyoruz.</p>
  <?php if (!$hepsi): ?>
    <div class="empty">İlk rehberler hazırlanıyor.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($hepsi as $y): ?>
        <a class="card" href="rehber.php?y=<?= pz_e($y['slug']) ?>">
          <h2><?= pz_e($y['baslik']) ?></h2>
          <p><?= pz_e(mb_substr($y['meta'], 0, 160)) ?></p>
          <small><?= pz_e($tarihTr($y['yayin_tarihi'])) ?> · <?= rehber_sure($y['govde']) ?> dk okuma</small>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>
<?php endif; ?>

<footer><div class="wrap">
  <span>© <?= date('Y') ?> OptiFlow · Gözlükçüler için yönetim programı</span>
  <span><a href="/">Ana sayfa</a> · <a href="rehber.php">Rehber</a> · <a href="kvkk.php">KVKK</a></span>
</div></footer>
</body>
</html>
