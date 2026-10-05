<?php
declare(strict_types=1);

/**
 * KVKK aydınlatma metni — bağımsız sayfa (veritabanına ve oturuma dokunmaz, bootstrap yüklemez).
 * Bilgiler app/pazarlama.php'den gelir.
 * ÖNEMLİ: Bu metin genel bir TASLAKTIR. Yayına almadan önce bir hukukçuya kontrol ettirin ve
 * şirket unvanı / adres / e-posta alanlarını app/pazarlama.php'de doldurun.
 */
require dirname(__DIR__) . '/pazarlama.php';

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

$p      = optiflow_pazarlama();
$unvan  = $p['sirket_unvani'] !== '' ? $p['sirket_unvani'] : 'OptiFlow';
$adres  = $p['adres'];
$eposta = $p['kvkk_eposta'] !== '' ? $p['kvkk_eposta'] : $p['eposta'];
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="index,follow">
<title>KVKK Aydınlatma Metni — OptiFlow</title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<style>
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-wght-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-ext-wght-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }
:root{--bg:#f5f3f3;--ink:#1b1416;--ink-soft:#5f5558;--line:#e8e1e2;--primary:#b4233c;--primary-tint:#fbe4e8}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Manrope,system-ui,Arial,sans-serif;line-height:1.65;font-weight:500}
header{border-bottom:1px solid var(--line);background:#fff}
.wrap{max-width:780px;margin:0 auto;padding:0 20px}
.nav{display:flex;align-items:center;justify-content:space-between;padding:16px 0}
.wordmark{display:flex;align-items:center;gap:10px;font-weight:800;font-size:19px;text-decoration:none;color:var(--ink)}
.wordmark svg{width:28px;height:28px}
.back{font-weight:700;font-size:14px;color:var(--ink-soft);text-decoration:none}
main{padding:48px 0 72px}
h1{font-size:clamp(1.7rem,4vw,2.3rem);letter-spacing:-.02em;margin:0 0 8px;font-weight:800}
.lede{color:var(--ink-soft);font-weight:600;margin:0 0 32px}
h2{font-size:1.15rem;font-weight:800;margin:34px 0 10px;letter-spacing:-.01em}
p,li{font-size:15.5px;color:#2d2c3a}
ul{padding-left:22px}
li{margin:4px 0}
.box{background:var(--primary-tint);border-radius:16px;padding:18px 20px;margin:24px 0;font-weight:600}
footer{border-top:1px solid var(--line);padding:24px 0;font-size:13px;color:var(--ink-soft);font-weight:700}
</style>
<?= ga_head() ?>
</head>
<body>
<header><div class="wrap nav"><a class="wordmark" href="/"><?= pz_logo('lgK') ?>OptiFlow</a><a class="back" href="/">← Ana sayfa</a></div></header>
<main class="wrap">
  <h1>Kişisel Verilerin Korunması Aydınlatma Metni</h1>
  <p class="lede">6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") kapsamında hazırlanmıştır.</p>

  <h2>1. Veri sorumlusu</h2>
  <p>Bu metin, <b><?= pz_e($unvan) ?></b><?= $adres !== '' ? ' (' . pz_e($adres) . ')' : '' ?> tarafından, OptiFlow web sitesini ziyaret eden ve OptiFlow'a mağaza olarak kaydolan kişilerin kişisel verilerinin işlenmesine ilişkin olarak hazırlanmıştır.</p>

  <div class="box">Gözlükçü mağazalarının OptiFlow'a girdiği <b>müşteri ve reçete bilgileri</b> bakımından veri sorumlusu ilgili mağazadır. OptiFlow bu veriler için, mağazanın talimatları doğrultusunda ve yalnızca hizmetin sunulması amacıyla veri işleyen sıfatıyla hareket eder. Bu verilerle ilgili başvurularınızı öncelikle işlem yaptığınız gözlükçüye iletmeniz gerekir.</div>

  <h2>2. İşlenen kişisel veriler</h2>
  <ul>
    <li><b>Kimlik ve iletişim:</b> ad soyad, mağaza adı, e-posta, telefon.</li>
    <li><b>Hesap bilgileri:</b> kullanıcı adı, şifrelenmiş (geri çözülemez biçimde saklanan) parola, son giriş zamanı.</li>
    <li><b>İşlem güvenliği:</b> IP adresi, giriş denemeleri, oturum bilgileri.</li>
  </ul>

  <h2>3. İşleme amaçları</h2>
  <ul>
    <li>Mağaza kaydının oluşturulması, deneme süresinin ve aboneliğin yürütülmesi,</li>
    <li>Kurulum, destek ve bilgilendirme amacıyla sizinle iletişime geçilmesi,</li>
    <li>Hesap güvenliğinin sağlanması ve yetkisiz erişimin önlenmesi,</li>
    <li>Hukuki yükümlülüklerin yerine getirilmesi.</li>
  </ul>

  <h2>4. Hukuki sebepler ve toplama yöntemi</h2>
  <p>Kişisel verileriniz; kayıt formu, giriş ekranları ve iletişim kanalları aracılığıyla elektronik ortamda toplanır ve KVKK madde 5/2 kapsamında sözleşmenin kurulması ve ifası, hukuki yükümlülüklerin yerine getirilmesi ve temel hak ve özgürlüklerinize zarar vermemek kaydıyla meşru menfaat hukuki sebeplerine dayanılarak işlenir.</p>

  <h2>5. Aktarım</h2>
  <p>Kişisel verileriniz, hizmetin sunulması için gerekli olduğu ölçüde barındırma (sunucu) hizmeti sağlayıcılarına ve kanunen yetkili kamu kurum ve kuruluşlarına aktarılabilir. Verileriniz pazarlama amacıyla üçüncü kişilere satılmaz veya kiralanmaz.</p>

  <h2>6. Saklama süresi</h2>
  <p>Verileriniz, işleme amacının gerektirdiği süre ve ilgili mevzuatta öngörülen süreler boyunca saklanır; bu sürelerin sonunda silinir, yok edilir veya anonim hale getirilir.</p>

  <h2>7. Haklarınız</h2>
  <p>KVKK madde 11 uyarınca; kişisel verilerinizin işlenip işlenmediğini öğrenme, işlenmişse bilgi talep etme, işlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme, aktarıldığı üçüncü kişileri bilme, eksik veya yanlış işlenmişse düzeltilmesini, şartları oluştuğunda silinmesini veya yok edilmesini isteme, bu işlemlerin aktarıldığı kişilere bildirilmesini isteme, otomatik sistemlerle analiz sonucu aleyhinize bir sonuç çıkmasına itiraz etme ve kanuna aykırı işleme nedeniyle zarara uğramanız hâlinde zararın giderilmesini talep etme haklarına sahipsiniz.</p>
  <?php if ($eposta !== ''): ?>
  <p>Başvurularınızı <a href="mailto:<?= pz_e($eposta) ?>"><?= pz_e($eposta) ?></a> adresine iletebilirsiniz. Başvurunuz en geç 30 gün içinde ücretsiz olarak sonuçlandırılır.</p>
  <?php else: ?>
  <p>Başvurularınızı yazılı olarak veri sorumlusuna iletebilirsiniz. Başvurunuz en geç 30 gün içinde ücretsiz olarak sonuçlandırılır.</p>
  <?php endif; ?>
</main>
<footer><div class="wrap">© <?= date('Y') ?> <?= pz_e($unvan) ?></div></footer>
</body>
</html>
