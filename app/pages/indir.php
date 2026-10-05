<?php
declare(strict_types=1);

/**
 * OptiFlow Pro indirme sayfası (4.16.5) — bağımsız sayfa (veritabanına ve oturuma dokunmaz, bootstrap yüklemez).
 *   indir.php          → sayfa (sürüm, boyut, kurulum adımları)
 *   indir.php?dosya=1  → doğrudan kurulum dosyasına yönlendirir (WhatsApp'ta paylaşmak için kısa bağlantı)
 * Sürüm bilgisi indir/masaustu/latest.yml'den gelir (app/indir.php).
 */
require dirname(__DIR__) . '/pazarlama.php';
require_once dirname(__DIR__) . '/indir.php';

$b = indir_masaustu_bilgi();

if (($_GET['dosya'] ?? '') === '1') {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    if ($b) {
        header('Location: ' . $b['url'], true, 302);
    } else {
        header('Location: ' . INDIR_SAYFA, true, 302);
    }
    exit;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
$gaCsp = ga_csp();
header(
    "Content-Security-Policy: default-src 'self'; "
    . "img-src 'self' data:" . $gaCsp['img'] . "; "
    . "style-src 'self' 'unsafe-inline'; "
    . "script-src 'self'" . $gaCsp['script'] . "; "
    . "connect-src 'self'" . $gaCsp['connect'] . "; "
    . "frame-ancestors 'self'; base-uri 'self'"
);

$p = optiflow_pazarlama();
$wa = pz_whatsapp('Merhaba, OptiFlow Pro kurulumu için yardım istiyorum.');
$tarih = $b && $b['tarih'] !== '' ? date('d.m.Y', (int) strtotime($b['tarih'])) : '';
$aciklama = 'OptiFlow Pro: gözlükçüler için Windows uygulaması. Medula Optik ve OptiFlow aynı pencerede; reçete tek tuşla siparişe. Ücretsiz indirin; yeni sürümü uygulama kendisi bulur.';
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="index,follow">
<meta name="theme-color" content="#080d2b">
<title>OptiFlow Pro'yu indirin — Windows uygulaması | OptiFlow</title>
<meta name="description" content="<?= pz_e($aciklama) ?>">
<link rel="canonical" href="https://optiflow.com.tr/indir.php">
<meta property="og:type" content="website">
<meta property="og:title" content="OptiFlow Pro'yu indirin — Medula ve OptiFlow aynı pencerede">
<meta property="og:description" content="<?= pz_e($aciklama) ?>">
<meta property="og:url" content="https://optiflow.com.tr/indir.php">
<meta property="og:image" content="https://optiflow.com.tr/assets/og-optiflow.png">
<meta property="og:locale" content="tr_TR">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="preload" as="font" type="font/woff2" href="assets/fonts/manrope-latin-wght-normal.woff2" crossorigin>
<?php if ($b): ?>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'OptiFlow Pro',
    'operatingSystem' => 'Windows 10, Windows 11', 'applicationCategory' => 'BusinessApplication', 'inLanguage' => 'tr',
    'softwareVersion' => $b['surum'], 'downloadUrl' => 'https://optiflow.com.tr/' . $b['url'],
    'fileSize' => $b['boyut_mb'] ? $b['boyut_mb'] . ' MB' : null, 'description' => $aciklama,
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'TRY', 'description' => '30 gün ücretsiz deneme'],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<style>
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-wght-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-ext-wght-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }
:root{--ink:#0a1033;--ink-2:#454b6b;--line:#e3e6f2;--bg:#f3f5fb;--blue:#2a36ff;--blue-tint:#eceefe;--magenta:#c414d8;--red:#f2301f;--night:#080d2b;--night-2:#11173d;--night-ink:#c3cbf5;--ok:#0f7a45}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Manrope,system-ui,Arial,sans-serif;line-height:1.6;font-weight:500;-webkit-font-smoothing:antialiased}
a{color:var(--blue)}
.wrap{max-width:920px;margin:0 auto;padding:0 20px}
header{background:var(--night);color:#fff}
.nav{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;gap:12px}
.wordmark{display:flex;align-items:center;gap:10px;font-weight:800;font-size:19px;text-decoration:none;color:#fff}
.wordmark svg{width:28px;height:28px}
.nav a.geri{color:var(--night-ink);font-weight:700;font-size:14px;text-decoration:none}
.ust{background:linear-gradient(180deg,var(--night) 0%,#1b1460 100%);color:#fff;padding:44px 0 64px}
.rozet{display:inline-block;font-weight:800;font-size:13px;padding:5px 12px;border-radius:999px;background:var(--magenta);color:#fff}
h1{font-size:clamp(2rem,5vw,3rem);line-height:1.08;letter-spacing:-.02em;margin:14px 0 10px;font-weight:800;max-width:20ch}
.lede{color:var(--night-ink);font-size:1.12rem;max-width:56ch;margin:0 0 28px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:16px 26px;border-radius:12px;font-weight:800;font-size:16px;text-decoration:none;border:2px solid transparent;white-space:nowrap}
.btn-red{background:var(--red);color:#fff}
.btn-red:hover{background:#d8240f}
.btn-line{border-color:rgba(255,255,255,.6);color:#fff}
.btn svg{width:20px;height:20px;flex:none}
.eylem{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
.meta{margin:14px 0 0;color:var(--night-ink);font-size:14px;font-weight:600}
.meta b{color:#fff}
.mobil-not{display:none;margin-top:16px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.18);border-radius:12px;padding:12px 14px;font-size:14px;color:var(--night-ink)}
@media (max-width:760px),(pointer:coarse) and (max-width:1024px){.mobil-not{display:block}}
main.wrap{padding:52px 20px 72px}
h2{font-size:1.4rem;letter-spacing:-.01em;margin:0 0 16px;font-weight:800}
section+section{margin-top:48px}
.adimlar{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(4,1fr);gap:14px;counter-reset:a}
.adimlar li{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;counter-increment:a}
.adimlar li::before{content:counter(a);display:grid;place-items:center;width:30px;height:30px;border-radius:50%;background:var(--blue);color:#fff;font-weight:800;font-size:14px;margin-bottom:10px}
.adimlar b{display:block;margin-bottom:4px}
.adimlar span{color:var(--ink-2);font-size:14.5px}
.uyari{background:#fff;border:1px solid var(--line);border-left:5px solid var(--blue);border-radius:14px;padding:18px 20px}
.uyari p{margin:6px 0 0;color:var(--ink-2)}
.uyari kbd{font-family:inherit;font-weight:800;background:var(--blue-tint);color:var(--ink);border-radius:6px;padding:1px 7px;white-space:nowrap}
.izgara{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.kart{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px}
.kart h3{margin:0 0 6px;font-size:1.05rem}
.kart p{margin:0;color:var(--ink-2);font-size:15px}
.kart a{font-weight:800}
.tablo{width:100%;border-collapse:collapse;background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden;font-size:15px}
.tablo th,.tablo td{text-align:left;padding:11px 16px;border-top:1px solid var(--line);vertical-align:top}
.tablo tr:first-child th,.tablo tr:first-child td{border-top:0}
.tablo th{width:34%;color:var(--ink-2);font-weight:700}
footer{border-top:1px solid var(--line);padding:24px 0;font-size:13px;color:var(--ink-2);font-weight:700}
footer .wrap{display:flex;gap:16px;flex-wrap:wrap;justify-content:space-between}
footer a{color:var(--ink-2)}
@media (max-width:760px){.adimlar{grid-template-columns:1fr 1fr}.izgara{grid-template-columns:1fr}.btn{width:100%}}
@media (max-width:420px){.adimlar{grid-template-columns:1fr}}
</style>
<?= ga_head() ?>
</head>
<body>
<header><div class="nav"><a class="wordmark" href="/"><?= pz_logo('lgI') ?>OptiFlow</a><a class="geri" href="/#surumler">← Lite ve Pro</a></div></header>

<div class="ust">
  <div class="wrap">
    <span class="rozet">Pro</span>
    <h1>OptiFlow Pro'yu indirin</h1>
    <p class="lede">Windows uygulaması: Medula Optik ve OptiFlow aynı pencerede. Reçete tek tuşla siparişe gelir, SGK hakkı Medula ekranından sorgulanır, karekod okuyucu doğrudan çalışır.</p>
    <?php if ($b): ?>
      <div class="eylem">
        <a class="btn btn-red" href="<?= pz_e($b['url']) ?>" download><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M4 20h16"/></svg>Windows için indir</a>
        <a class="btn btn-line" href="magaza-giris.php">Tarayıcıda giriş yap</a>
      </div>
      <p class="meta">Sürüm <b><?= pz_e($b['surum']) ?></b><?= $b['boyut_mb'] ? ' · ' . (int) $b['boyut_mb'] . ' MB' : '' ?><?= $tarih !== '' ? ' · ' . pz_e($tarih) : '' ?> · Windows 10 / 11 (64 bit) · Ücretsiz</p>
      <p class="mobil-not">Bu dosya Windows bilgisayar içindir. Telefon ve tablette OptiFlow'u tarayıcıdan kullanın: <a href="magaza-giris.php" style="color:#fff;font-weight:800">Mağaza girişi</a>. Bağlantıyı bilgisayara göndermek için bu sayfanın adresini paylaşın: <b style="color:#fff">optiflow.com.tr/indir.php</b></p>
    <?php else: ?>
      <div class="eylem">
        <?php if ($wa !== ''): ?><a class="btn btn-red" href="<?= pz_e($wa) ?>" target="_blank" rel="noopener">Kurulum dosyasını isteyin</a><?php endif; ?>
        <a class="btn btn-line" href="magaza-giris.php">Tarayıcıda giriş yap</a>
      </div>
      <p class="meta">Kurulum dosyası şu an güncelleniyor; birkaç dakika sonra yeniden deneyin.</p>
    <?php endif; ?>
  </div>
</div>

<main class="wrap">
  <section>
    <h2>Kurulum 2 dakika</h2>
    <ol class="adimlar">
      <li><b>İndirin</b><span>"Windows için indir"e basın; dosya İndirilenler klasörüne iner.</span></li>
      <li><b>Çalıştırın</b><span>Dosyayı açın, kurulum sihirbazında "İleri"ye basın. Yönetici şifresi istemez.</span></li>
      <li><b>Giriş yapın</b><span>Masaüstündeki OptiFlow Pro simgesini açın; mağaza ve personel girişinizi yapın.</span></li>
      <li><b>Medula'yı açın</b><span>Üstteki "Medula Optik" sekmesinden Medula'ya her zamanki gibi kendiniz girin.</span></li>
    </ol>
  </section>

  <section>
    <div class="uyari">
      <b>Windows "bilgisayarınız korundu" derse</b>
      <p>Yeni yayınlanan uygulamalarda Windows SmartScreen bu uyarıyı gösterebilir. <kbd>Ek bilgi</kbd> yazısına, ardından <kbd>Yine de çalıştır</kbd> düğmesine basın. Dosyayı yalnızca bu sayfadan (optiflow.com.tr) indirin.</p>
    </div>
  </section>

  <section>
    <h2>Bilmeniz gerekenler</h2>
    <div class="izgara">
      <div class="kart"><h3>SGK şifreniz bizde değil</h3><p>Medula şifrenizi isterseniz Chrome gibi kaydeder: yalnızca bu bilgisayarda, Windows şifrelemesiyle saklanır ve girişte kendiliğinden yazılır. Güvenlik kodunu siz girersiniz. Şifre OptiFlow sunucusuna hiç gönderilmez. Reçete yalnızca siz "Aktar"a bastığınızda, ekranda görünen haliyle gelir.</p></div>
      <div class="kart"><h3>Güncellemeyi kendisi bulur</h3><p>Uygulama yeni sürümü kendisi denetler ve araç çubuğunda haber verir; tek tıkla indirip kurarsınız. Bu sayfaya tekrar gelmeniz gerekmez.</p></div>
      <div class="kart"><h3>Aynı hesap, aynı veriler</h3><p>Uygulamada tarayıcıdaki mağaza hesabınızla giriş yaparsınız. Telefondan ve tabletten tarayıcıyla kullanmaya devam edebilirsiniz.</p></div>
      <div class="kart"><h3>Pro özellikleri paketle açılır</h3><p>Medula aktarımı ve ÜTS karekod OptiFlow Pro paketinde çalışır. Hesabınız yoksa <a href="kayit.php">30 gün ücretsiz deneyin</a><?= $wa !== '' ? '; paket için <a href="' . pz_e($wa) . '" target="_blank" rel="noopener">bize yazın</a>' : '' ?>.</p></div>
    </div>
  </section>

  <section>
    <h2>Sistem gereksinimleri</h2>
    <table class="tablo">
      <tr><th scope="row">İşletim sistemi</th><td>Windows 10 ya da Windows 11, 64 bit</td></tr>
      <tr><th scope="row">Disk</th><td>En az 500 MB boş alan</td></tr>
      <tr><th scope="row">İnternet</th><td>Gerekli (kesilirse açık siparişler şifreli kopyadan okunabilir)</td></tr>
      <tr><th scope="row">Medula</th><td>Medula Optik kullanıcı hesabınız (giriş her zamanki gibi sizde)</td></tr>
      <tr><th scope="row">İsteğe bağlı</th><td>USB barkod / karekod okuyucu, fiş ve etiket yazıcısı</td></tr>
    </table>
  </section>
</main>

<footer><div class="wrap"><span>© <?= date('Y') ?> <?= pz_e($p['sirket_unvani'] !== '' ? $p['sirket_unvani'] : 'OptiFlow') ?></span><span><a href="/">Ana sayfa</a> · <a href="rehber.php">Rehber</a> · <a href="kvkk.php">KVKK</a></span></div></footer>
</body>
</html>
