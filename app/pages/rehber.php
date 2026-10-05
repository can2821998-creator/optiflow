<?php
declare(strict_types=1);

/**
 * Rehber (blog) — herkese açık sayfa. Veritabanına ve oturuma dokunmaz, bootstrap yüklemez.
 *   rehber.php          → yazı listesi (gazetenin ön sayfası)
 *   rehber.php?y=<slug> → tek yazı
 * 4.16.6: "Gözlükçü Gazetesi" — eskimiş gazete sayfası tasarımı (sararmış kâğıt, mürekkep, sepya baskı fotoğraf).
 * Yazıların JSON'unda isteğe bağlı alanlar: "manset": true (ön sayfa manşeti), "bolum", "gorsel" (assets/onizleme/*.webp).
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
$aylar = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$tarihTr = static function (string $d) use ($aylar): string {
    return preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $d, $m) ? (int) $m[3] . ' ' . $aylar[(int) $m[2]] . ' ' . $m[1] : '';
};
$gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
$bugun = (int) date('j') . ' ' . $aylar[(int) date('n')] . ' ' . date('Y') . ', ' . $gunler[(int) date('w')];

/** Gazete bölümü (yazının konusu) ve fotoğrafı: JSON'da yoksa adresten tahmin edilir. */
$bolumVe = static function (array $y): array {
    $s = $y['slug'] . ' ' . mb_strtolower($y['hedef_kelime']);
    $tablo = [
        ['medula', 'Medula', 'siparis'], ['sgk', 'SGK', 'sgk'], ['garanti', 'Müşteri', 'garanti'],
        ['atolye', 'Atölye', 'atolye'], ['uts', 'ÜTS', 'atolye'], ['kasa', 'Kasa', 'liste'],
    ];
    $bolum = 'Dükkân';
    $gorsel = 'liste';
    foreach ($tablo as [$k, $b, $g]) {
        if (str_contains($s, $k)) {
            [$bolum, $gorsel] = [$b, $g];
            break;
        }
    }
    $bolum = trim((string) ($y['bolum'] ?? '')) ?: $bolum;
    $g = (string) ($y['gorsel'] ?? '');
    $gorsel = preg_match('/^[a-z0-9\-]+$/', $g) && is_file(dirname(__DIR__, 2) . '/assets/onizleme/' . $g . '.webp') ? $g : $gorsel;
    return [$bolum, $gorsel];
};
$fotoAlt = [
    'siparis' => 'OptiFlow sipariş ekranı: Medula\'dan aktarılan reçete',
    'sgk'     => 'SGK ay sonu faturası ekranı',
    'garanti' => 'Garanti kaydı ekranı',
    'atolye'  => 'Atölye panosu: siparişler aşama aşama',
    'liste'   => 'OptiFlow sipariş listesi ve günün özeti',
];
/** Yazının ilk paragrafları (ön sayfa özeti için), en fazla $n paragraf. */
$ilkParagraflar = static function (string $govde, int $n): string {
    preg_match_all('#<p>(.*?)</p>#su', rehber_temizle($govde), $m);
    return implode('', array_map(static fn($p) => '<p>' . $p . '</p>', array_slice($m[1] ?? [], 0, $n)));
};

$manset = null;
foreach ($hepsi as $y) {
    if (!empty($y['manset'])) {
        $manset = $y;
        break;
    }
}
$manset ??= $hepsi ? reset($hepsi) : null;
$digerleri = $manset ? array_filter($hepsi, static fn($y) => $y['slug'] !== $manset['slug']) : [];

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

$ilan = static function (): void { ?>
  <aside class="ilan" aria-label="OptiFlow">
    <p class="ilan-bas">İlan</p>
    <p class="ilan-baslik">Reçeteyi elle yazmaktan yorulan gözlükçülere</p>
    <p>Medula'daki reçete tek tuşla siparişe gelir. SGK katkı payı ve ay sonu faturası hazır, atölye her işi panodan izler.</p>
    <p class="ilan-not">30 gün ücretsiz. Kredi kartı istenmez.</p>
    <a class="ilan-dug" href="kayit.php">Ücretsiz deneyin</a>
    <span class="damga" aria-hidden="true">Ücretsiz</span>
  </aside>
<?php };

$foto = static function (string $gorsel, string $alt, bool $buyuk, bool $tembel = true) { ?>
  <figure class="foto<?= $buyuk ? ' buyuk' : '' ?>">
    <span class="foto-cerceve"><img src="assets/onizleme/<?= pz_e($gorsel) ?>.webp" alt="<?= pz_e($alt) ?>" width="1280" height="800"<?= $tembel ? ' loading="lazy"' : '' ?> decoding="async"></span>
    <figcaption><?= pz_e($alt) ?>.</figcaption>
  </figure>
<?php };
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="<?= $bulunamadi ? 'noindex,follow' : 'index,follow' ?>">
<meta name="theme-color" content="#2b2418">
<title><?= pz_e($bulunamadi ? 'Yazı bulunamadı | OptiFlow Rehber' : $title) ?></title>
<meta name="description" content="<?= pz_e($desc) ?>">
<?php if (!$bulunamadi): ?><link rel="canonical" href="<?= pz_e($canon) ?>"><?php endif; ?>
<?= pz_dogrulama_meta() ?>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preload" as="font" type="font/woff2" href="assets/fonts/fraunces-latin-500-normal.woff2" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="assets/fonts/fraunces-latin-ext-500-normal.woff2" crossorigin>
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
@font-face { font-family: "Fraunces"; font-style: normal; font-display: swap; font-weight: 500;
  src: url("assets/fonts/fraunces-latin-500-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Fraunces"; font-style: normal; font-display: swap; font-weight: 500;
  src: url("assets/fonts/fraunces-latin-ext-500-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: "Fraunces"; font-style: italic; font-display: swap; font-weight: 500;
  src: url("assets/fonts/fraunces-latin-500-italic.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Fraunces"; font-style: italic; font-display: swap; font-weight: 500;
  src: url("assets/fonts/fraunces-latin-ext-500-italic.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }

/* ---- Eskimiş gazete: sararmış kâğıt, sıcak siyah mürekkep, solmuş kırmızı ikinci renk ---- */
:root{
  --masa:#2b2418;          /* kâğıdın durduğu koyu masa */
  --kagit:#eadfbf;         /* sararmış gazete kâğıdı */
  --kagit-acik:#f1e8cf;
  --murekkep:#231c11;      /* dağılmış sıcak siyah */
  --soluk:#5a4e38;         /* soluk baskı */
  --cizgi:rgba(35,28,17,.72);
  --ince:rgba(35,28,17,.28);
  --kirmizi:#9b2d1d;       /* ikinci baskı rengi */
  --serif:"Fraunces",Georgia,"Times New Roman",serif;
}
*{box-sizing:border-box}
html{background:var(--masa)}
body{margin:0;color:var(--murekkep);font-family:var(--serif);font-weight:500;line-height:1.62;-webkit-font-smoothing:antialiased;
  background:var(--masa) radial-gradient(ellipse at 50% 0%,#3d3322 0%,var(--masa) 70%) fixed}
a{color:inherit}
:focus-visible{outline:2px dashed var(--kirmizi);outline-offset:3px}
::selection{background:rgba(155,45,29,.25)}

.kagit{position:relative;max-width:1240px;margin:28px auto 40px;padding:0 44px 36px;
  background:
    url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='260' height='260'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3CfeColorMatrix values='0 0 0 0 .32 0 0 0 0 .24 0 0 0 0 .1 0 0 0 .55 -.18'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E"),
    radial-gradient(circle at 12% 18%,rgba(140,96,34,.16) 0 26px,transparent 64px),
    radial-gradient(circle at 86% 9%,rgba(140,96,34,.12) 0 14px,transparent 40px),
    radial-gradient(circle at 78% 72%,rgba(140,96,34,.14) 0 30px,transparent 80px),
    radial-gradient(circle at 22% 88%,rgba(140,96,34,.11) 0 18px,transparent 52px),
    linear-gradient(90deg,transparent 49.6%,rgba(90,62,24,.13) 49.95%,rgba(255,248,224,.35) 50.15%,transparent 50.6%),
    radial-gradient(ellipse at 50% 45%,var(--kagit-acik) 0%,var(--kagit) 55%,#d9c595 100%);
  box-shadow:0 1px 0 rgba(255,255,255,.08) inset,0 30px 60px -20px rgba(0,0,0,.65),0 2px 6px rgba(0,0,0,.4);
  text-shadow:0 0 .6px rgba(35,28,17,.45)}
/* kenarlar biraz yıpranmış */
.kagit::before{content:"";position:absolute;inset:0;pointer-events:none;
  box-shadow:inset 0 0 0 1px rgba(120,86,36,.25),inset 0 0 70px rgba(122,82,26,.38),inset 0 0 14px rgba(110,74,22,.35)}
.kagit>*{position:relative}

/* üst şerit: siteye dönüş */
.serit{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 0 10px;font-size:14px;border-bottom:1px solid var(--ince)}
.serit a{text-decoration:none;color:var(--soluk)}
.serit a:hover{color:var(--murekkep);text-decoration:underline}
.serit .sag{display:flex;gap:18px}
.serit .sag a.kirmizi{color:var(--kirmizi)}

/* künye (gazete başlığı) */
.kunye{display:grid;grid-template-columns:1fr auto 1fr;align-items:end;gap:24px;padding:26px 0 14px}
.kunye-yan{font-style:italic;font-size:15px;line-height:1.35;color:var(--soluk)}
.kunye-yan.sag{text-align:right}
.ad{display:block;text-align:center;text-decoration:none;font-size:clamp(2.6rem,8.4vw,6.4rem);line-height:.92;letter-spacing:-.035em;
  font-variation-settings:"opsz" 144;margin:0;font-weight:500;text-shadow:0 0 1px rgba(35,28,17,.55),.4px .3px 0 rgba(35,28,17,.35)}
.ad small{display:block;font-size:max(.17em,13px);letter-spacing:.01em;font-style:italic;margin-top:.55em;color:var(--soluk);text-shadow:none}
.kunye-alt{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:7px 2px;font-size:14.5px;
  border-top:3px double var(--cizgi);border-bottom:1px solid var(--cizgi);margin-bottom:28px}
.kunye-alt .sayi{color:var(--kirmizi)}

/* ortak */
.bolum{margin:0 0 6px;font-style:italic;font-size:15px;color:var(--kirmizi)}
.bolum::after{content:"";display:inline-block;width:38px;height:1px;background:currentColor;vertical-align:middle;margin-left:10px;opacity:.7}
h1,h2,h3{font-weight:500;margin:0;text-wrap:balance}
.spot{font-style:italic;color:var(--soluk);font-size:1.12rem;line-height:1.45;margin:10px 0 0}
.kunye-satir{display:flex;flex-wrap:wrap;gap:6px 18px;font-size:14px;color:var(--soluk);border-top:1px solid var(--ince);border-bottom:1px solid var(--ince);padding:7px 0;margin:16px 0 18px}
.kunye-satir span+span{border-left:1px solid var(--ince);padding-left:18px}

/* sepya baskı fotoğraf (nokta taramalı) */
.foto{margin:16px 0 14px}
.foto-cerceve{display:block;position:relative;overflow:hidden;border:1px solid var(--cizgi);background:#cdb98a}
.foto img{display:block;width:100%;height:auto;aspect-ratio:16/10;object-fit:cover;object-position:top left;
  filter:grayscale(1) sepia(.65) contrast(1.35) brightness(.92);mix-blend-mode:multiply;opacity:.92}
.foto-cerceve::after{content:"";position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(rgba(35,26,12,.42) 28%,transparent 31%) 0 0/3px 3px,
             radial-gradient(ellipse at 50% 50%,transparent 55%,rgba(70,48,16,.35) 100%);
  mix-blend-mode:multiply}
.foto figcaption{font-size:13.5px;font-style:italic;color:var(--soluk);padding-top:6px;border-bottom:1px solid var(--ince);padding-bottom:6px}
.foto.buyuk img{aspect-ratio:16/9}

/* gövde metni */
.metin p{margin:0 0 .9em;hyphens:auto;-webkit-hyphens:auto}
.metin>p:first-child::first-letter,.sutunlu>p:first-child::first-letter{float:left;font-size:4.1em;line-height:.82;padding:.06em .08em 0 0;color:var(--kirmizi);font-variation-settings:"opsz" 144}
.sutunlu{columns:2 15rem;column-gap:30px;column-rule:1px solid var(--ince);text-align:justify;font-size:1.02rem}
.devami{display:inline-block;margin-top:4px;font-style:italic;color:var(--kirmizi);text-decoration:none;border-bottom:1px solid currentColor}
.devami:hover{color:var(--murekkep)}

/* ön sayfa */
.on{display:grid;grid-template-columns:minmax(0,2.15fr) minmax(0,1fr);gap:34px}
.on>.yan{border-left:1px solid var(--cizgi);padding-left:30px}
.manset-baslik{font-size:clamp(2.1rem,4.6vw,3.6rem);line-height:1.02;letter-spacing:-.025em;font-variation-settings:"opsz" 144}
.manset-baslik a,.kisa h2 a,.bu-sayida a{text-decoration:none}
.manset-baslik a:hover,.kisa h2 a:hover,.bu-sayida a:hover{text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:4px}

.bu-sayida h2,.ic-baslik{font-size:1.45rem;font-style:italic;padding-bottom:6px;border-bottom:3px double var(--cizgi);margin-bottom:6px}
.bu-sayida ol{list-style:none;margin:0 0 26px;padding:0;counter-reset:s}
.bu-sayida li{display:grid;grid-template-columns:1fr auto;gap:12px;align-items:baseline;padding:11px 0;border-bottom:1px dotted var(--cizgi);counter-increment:s}
.bu-sayida li::after{content:"s. " counter(s);font-size:13px;color:var(--soluk);font-style:italic}
.bu-sayida li b{display:block;font-weight:500;font-size:1.04rem;line-height:1.3}
.bu-sayida li small{display:block;font-style:italic;color:var(--kirmizi);font-size:13px}
.bu-sayida li.simdi b{text-decoration:underline;text-decoration-thickness:1px;text-underline-offset:4px}

/* küçük ilan */
.ilan{position:relative;border:3px double var(--cizgi);padding:16px 18px 18px;text-align:center;background:rgba(255,248,226,.28)}
.ilan p{margin:0 0 8px}
.ilan-bas{font-style:italic;color:var(--soluk);font-size:14px;border-bottom:1px solid var(--ince);padding-bottom:6px}
.ilan-baslik{font-size:1.45rem;line-height:1.12;letter-spacing:-.01em;margin:8px 0 10px !important}
.ilan-not{font-style:italic;color:var(--soluk);font-size:14.5px}
.ilan-dug{display:inline-block;margin-top:6px;padding:9px 18px;border:1.5px solid var(--murekkep);text-decoration:none;font-size:1.02rem}
.ilan-dug:hover{background:var(--murekkep);color:var(--kagit)}
.damga{position:absolute;right:-10px;top:-16px;transform:rotate(9deg);color:var(--kirmizi);border:2px solid currentColor;border-radius:4px;padding:1px 9px;
  font-size:15px;font-style:italic;opacity:.78;text-shadow:none;background:rgba(234,223,191,.6);mix-blend-mode:multiply}

/* iç sayfa haberleri */
.ic{margin-top:34px;border-top:3px double var(--cizgi);padding-top:8px}
.ic-izgara{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:0}
.kisa{padding:14px 22px 18px;border-left:1px solid var(--ince)}
.kisa:first-child{border-left:0;padding-left:0}
.kisa h2{font-size:1.42rem;line-height:1.12;letter-spacing:-.015em}
.kisa .foto{margin:12px 0 10px}
.kisa p.ozet{margin:0 0 6px;font-size:.98rem}
.kisa small{font-style:italic;color:var(--soluk);font-size:13.5px}

/* yazı sayfası */
.yazi{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:40px}
.yazi>.yan{border-left:1px solid var(--cizgi);padding-left:30px}
.haber h1{font-size:clamp(2rem,4.4vw,3.3rem);line-height:1.04;letter-spacing:-.025em;font-variation-settings:"opsz" 144}
.yol{font-size:14px;color:var(--soluk);margin:0 0 14px}
.yol a{text-decoration:none}
.yol a:hover{text-decoration:underline}
.haber .metin{font-size:1.1rem;line-height:1.72;max-width:66ch}
.metin h2{font-size:1.5rem;line-height:1.2;margin:1.5em 0 .5em;padding-top:.55em;border-top:1px solid var(--ince)}
.metin h3{font-size:1.18rem;font-style:italic;margin:1.2em 0 .4em}
.metin ul,.metin ol{padding-left:1.3em;margin:0 0 1em}
.metin li{margin:.35em 0}
.metin li::marker{color:var(--kirmizi)}
.metin strong{font-weight:500;font-style:italic;color:#000}
.metin a{text-decoration-color:var(--kirmizi);text-underline-offset:3px}
.metin blockquote{margin:1.4em 0;padding:.6em 0 .6em 1em;border-left:3px solid var(--kirmizi);font-style:italic;font-size:1.22rem;line-height:1.45}
.metin blockquote p{margin:0}
.metin table{width:100%;border-collapse:collapse;margin:1.2em 0;font-size:.95rem;border-top:2px solid var(--cizgi);border-bottom:2px solid var(--cizgi)}
.metin th,.metin td{padding:8px 10px;text-align:left;border-bottom:1px solid var(--ince);vertical-align:top}
.metin th{font-style:italic;font-weight:500}
.son-isaret{display:inline-block;width:.55em;height:.55em;background:var(--kirmizi);margin-left:.3em;vertical-align:middle}
.bos{padding:40px 0;text-align:center;font-style:italic;color:var(--soluk);font-size:1.2rem}

/* alt künye */
.alt{margin-top:40px;border-top:3px double var(--cizgi);padding-top:12px;display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:14px;color:var(--soluk);font-style:italic}
.alt a{text-decoration:none}
.alt a:hover{text-decoration:underline}

@media (max-width:980px){
  .on,.yazi{grid-template-columns:1fr}
  .on>.yan,.yazi>.yan{border-left:0;padding-left:0;border-top:3px double var(--cizgi);padding-top:18px}
  .kunye{grid-template-columns:1fr}
  .kunye-yan{display:none}
}
@media (max-width:700px){
  .kagit{margin:0;padding:0 16px 24px;box-shadow:none}
  .kagit::before{box-shadow:inset 0 0 40px rgba(122,82,26,.3)}
  .serit .sag a:not(.kirmizi){display:none}
  .kunye-alt span:nth-child(3){display:none}
  .kisa{border-left:0;padding:14px 0 18px;border-top:1px solid var(--ince)}
  .kisa:first-child{border-top:0}
  .sutunlu{text-align:left}
  .damga{right:4px}
}
@media print{html,body{background:#fff}.kagit{box-shadow:none;background:#fff;margin:0}.kagit::before,.serit,.ilan{display:none}}
</style>
<?= ga_head() ?>
</head>
<body>
<div class="kagit">
  <nav class="serit" aria-label="Site">
    <a href="/">← OptiFlow ana sayfa</a>
    <span class="sag"><a href="indir.php">Pro'yu indir</a><a class="kirmizi" href="kayit.php">30 gün ücretsiz deneyin</a></span>
  </nav>

  <header>
    <div class="kunye">
      <p class="kunye-yan">Medula, SGK ve ÜTS işleri,<br>atölye, stok ve kasa</p>
      <?php if (!$yazi && !$bulunamadi): ?>
        <h1 class="ad">Gözlükçü Gazetesi<small>Gözlükçüler için uygulamalı rehberler</small></h1>
      <?php else: ?>
        <a class="ad" href="rehber.php">Gözlükçü Gazetesi<small>Gözlükçüler için uygulamalı rehberler</small></a>
      <?php endif; ?>
      <p class="kunye-yan sag">OptiFlow yayınıdır.<br>Her sayıda dükkândan bir iş.</p>
    </div>
    <div class="kunye-alt">
      <span class="sayi">Sayı <?= count($hepsi) ?></span>
      <span><?= pz_e($bugun) ?></span>
      <span>optiflow.com.tr/rehber</span>
      <span>Fiyatı: ücretsiz</span>
    </div>
  </header>

<?php if ($yazi): [$bolum, $gorsel] = $bolumVe($yazi); ?>
  <div class="yazi">
    <main>
      <article class="haber">
        <p class="yol"><a href="/">OptiFlow</a> / <a href="rehber.php">Rehber</a> / <?= pz_e($bolum) ?></p>
        <p class="bolum"><?= pz_e($bolum) ?></p>
        <h1><?= pz_e($yazi['baslik']) ?></h1>
        <?php if ($yazi['meta'] !== ''): ?><p class="spot"><?= pz_e($yazi['meta']) ?></p><?php endif; ?>
        <p class="kunye-satir">
          <span>Rehber masası</span>
          <?php if ($yazi['yayin_tarihi']): ?><span><?= pz_e($tarihTr($yazi['yayin_tarihi'])) ?></span><?php endif; ?>
          <span><?= rehber_sure($yazi['govde']) ?> dakikalık okuma</span>
          <?php if ($yazi['guncelleme'] && $yazi['guncelleme'] !== $yazi['yayin_tarihi']): ?><span>Güncellendi: <?= pz_e($tarihTr($yazi['guncelleme'])) ?></span><?php endif; ?>
        </p>
        <?php $foto($gorsel, $fotoAlt[$gorsel] ?? 'OptiFlow ekranı', true, false); ?>
        <div class="metin"><?= rehber_temizle($yazi['govde']) ?></div>
        <p><span class="son-isaret" aria-hidden="true"></span></p>
      </article>
    </main>
    <div class="yan">
      <?php $ilan(); ?>
      <?php if (count($hepsi) > 1): ?>
      <section class="bu-sayida" style="margin-top:30px">
        <h2>Bu sayıda</h2>
        <ol>
          <?php foreach ($hepsi as $d): [$db] = $bolumVe($d); ?>
            <li class="<?= $d['slug'] === $yazi['slug'] ? 'simdi' : '' ?>"><a href="rehber.php?y=<?= pz_e($d['slug']) ?>"<?= $d['slug'] === $yazi['slug'] ? ' aria-current="page"' : '' ?>><small><?= pz_e($db) ?></small><b><?= pz_e($d['baslik']) ?></b></a></li>
          <?php endforeach; ?>
        </ol>
      </section>
      <?php endif; ?>
    </div>
  </div>

<?php elseif ($bulunamadi): ?>
  <main class="bos">
    <h1 style="font-size:2rem;font-style:normal;color:var(--murekkep)">Bu yazı bulunamadı</h1>
    <p>Yazı kaldırılmış ya da adresi değişmiş olabilir.</p>
    <p><a class="devami" href="rehber.php">Gazetenin ön sayfasına dönün</a></p>
  </main>

<?php elseif (!$manset): ?>
  <main class="bos"><p>İlk sayı hazırlanıyor.</p></main>

<?php else: [$mBolum, $mGorsel] = $bolumVe($manset); ?>
  <main>
    <div class="on">
      <article class="manset">
        <p class="bolum"><?= pz_e($mBolum) ?></p>
        <h2 class="manset-baslik"><a href="rehber.php?y=<?= pz_e($manset['slug']) ?>"><?= pz_e($manset['baslik']) ?></a></h2>
        <?php if ($manset['meta'] !== ''): ?><p class="spot"><?= pz_e($manset['meta']) ?></p><?php endif; ?>
        <?php $foto($mGorsel, $fotoAlt[$mGorsel] ?? 'OptiFlow ekranı', true, false); ?>
        <div class="sutunlu metin"><?= $ilkParagraflar($manset['govde'], 2) ?></div>
        <a class="devami" href="rehber.php?y=<?= pz_e($manset['slug']) ?>">Yazının devamı, <?= rehber_sure($manset['govde']) ?> dakikalık okuma</a>
      </article>
      <div class="yan">
        <section class="bu-sayida">
          <h2>Bu sayıda</h2>
          <ol>
            <?php foreach ($hepsi as $d): [$db] = $bolumVe($d); ?>
              <li><a href="rehber.php?y=<?= pz_e($d['slug']) ?>"><small><?= pz_e($db) ?></small><b><?= pz_e($d['baslik']) ?></b></a></li>
            <?php endforeach; ?>
          </ol>
        </section>
        <?php $ilan(); ?>
      </div>
    </div>

    <?php if ($digerleri): ?>
    <section class="ic" aria-label="Diğer yazılar">
      <h2 class="ic-baslik">İç sayfalar</h2>
      <div class="ic-izgara">
        <?php foreach ($digerleri as $d): [$db, $dg] = $bolumVe($d); ?>
          <article class="kisa">
            <p class="bolum"><?= pz_e($db) ?></p>
            <h2><a href="rehber.php?y=<?= pz_e($d['slug']) ?>"><?= pz_e($d['baslik']) ?></a></h2>
            <?php $foto($dg, $fotoAlt[$dg] ?? 'OptiFlow ekranı', false); ?>
            <p class="ozet"><?= pz_e(mb_substr($d['meta'], 0, 190)) ?></p>
            <small><?= pz_e($tarihTr($d['yayin_tarihi'])) ?>, <?= rehber_sure($d['govde']) ?> dakikalık okuma</small>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
  </main>
<?php endif; ?>

  <footer class="alt">
    <span>Gözlükçü Gazetesi, OptiFlow tarafından yayımlanır. © <?= date('Y') ?></span>
    <span><a href="/">Gözlükçü programı OptiFlow</a> · <a href="indir.php">OptiFlow Pro</a> · <a href="kvkk.php">KVKK</a></span>
  </footer>
</div>
</body>
</html>
