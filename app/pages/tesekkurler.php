<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

$kayit = $_SESSION['kayit_basarili'] ?? null;
if (!$kayit) {
    redirect('kayit.php');
}
$aktif = $kayit['durum'] === 'aktif';
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>Teşekkürler — OptiFlow</title>
<style>
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-wght-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-ext-wght-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }
:root{--bg:#f5f3f3;--ink:#1b1416;--ink-soft:#5f5558;--primary:#1b1416;--pop:#b4233c;--line:#e8e1e2}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:radial-gradient(80% 140% at 100% 0%,rgba(255,107,129,.3),transparent 55%),linear-gradient(120deg,#1a1214 0%,#3a1520 100%);font-family:Manrope,system-ui,Arial,sans-serif;padding:24px}
.card{background:#fff;border-radius:24px;padding:44px 36px;max-width:440px;width:100%;text-align:center;box-shadow:0 30px 80px -30px rgba(0,0,0,.7)}
.mark{width:52px;height:52px;margin:0 auto 20px}
.badge{display:inline-flex;align-items:center;justify-content:center;width:52px;height:52px;border-radius:50%;background:#e6f4ea;margin-bottom:18px}
h1{font-family:Manrope,sans-serif;font-weight:800;font-size:1.7rem;color:var(--ink);margin:0 0 12px;letter-spacing:-.02em}
p{color:var(--ink-soft);font-size:1rem;line-height:1.6;margin:0 0 26px;font-weight:600}
.btn{display:inline-flex;padding:14px 26px;border-radius:10px;background:linear-gradient(135deg,#d0334f,#8f1a2e);color:#fff;font-weight:700;text-decoration:none;font-size:15px}
.btn:hover{filter:brightness(1.08)}
small{display:block;margin-top:18px;color:var(--ink-soft);font-size:12.5px;font-weight:700}
</style>
<?= ga_head() ?>
</head>
<body>
<div class="card">
  <svg class="mark" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs><linearGradient id="logoGrad" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse">
      <stop offset="0" stop-color="#ff6b81"/><stop offset=".55" stop-color="#d0334f"/><stop offset="1" stop-color="#8f1a2e"/>
    </linearGradient></defs>
    <g transform="rotate(-10 50 50)"><path fill-rule="evenodd" clip-rule="evenodd"
      d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z"
      fill="url(#logoGrad)"/></g>
  </svg>

  <?php if ($aktif): ?>
    <h1>Mağazanız hazır!</h1>
    <p>Deneme süreniz <?= e(date_tr($kayit['bitis'])) ?> tarihine kadar sürüyor. Şimdi kendi kullanıcı bilgilerinizle giriş yapabilirsiniz.</p>
    <a class="btn" href="login.php">Giriş yap</a>
  <?php else: ?>
    <h1>Başvurunuz alındı</h1>
    <p>Mağazanızı sizin için açıyoruz. Hazır olduğunda sizi arayıp ilk ayarları birlikte yapacağız; e-postanıza da bilgi gelecek.</p>
    <?php $pzWa = pz_whatsapp('Merhaba, OptiFlow\'a mağaza başvurusu yaptım.'); ?>
    <?php if ($pzWa !== ''): ?>
      <a class="btn" href="<?= e($pzWa) ?>" target="_blank" rel="noopener">WhatsApp'tan yazın</a>
      <br><a href="/" style="display:inline-block;margin-top:14px;font-weight:800;color:var(--ink-soft);font-size:14px">Ana sayfaya dön</a>
    <?php else: ?>
      <a class="btn" href="/">Ana sayfaya dön</a>
    <?php endif; ?>
  <?php endif; ?>
  <small>OptiFlow</small>
</div>
</body>
</html>
