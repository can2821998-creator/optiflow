<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (tenant_oturum()) {
    redirect('login.php');
}

$hata = '';
$v = ['isim' => '', 'email' => '', 'admin_ad' => '', 'admin_kullanici' => ''];
if (is_post()) {
    $v['isim'] = trim(post('isim'));
    $v['email'] = trim(post('email'));
    $v['admin_ad'] = trim(post('admin_ad'));
    $v['admin_kullanici'] = trim(post('admin_kullanici'));
    $magazaSifre = (string) ($_POST['magaza_sifre'] ?? '');
    $adminSifre = (string) ($_POST['admin_sifre'] ?? '');
    if ($magazaSifre !== (string) ($_POST['magaza_sifre_tekrar'] ?? '')) {
        $hata = 'Mağaza şifresi ile tekrarı aynı değil.';
    } elseif (merkez_hiz_asildi('kayit', '', KAYIT_MAX_IP_GUN, 0, 86400)) {
        // 4.20.1: her kayıt anında yeni bir veritabanı açar; toplu sahte kayda karşı IP başına günlük sınır.
        $hata = 'Bu bağlantıdan bugün çok sayıda mağaza açıldı. Yarın tekrar deneyin ya da bizimle iletişime geçin.';
    } else {
        try {
            $magaza = tenant_basvuru($v['isim'], $v['email'], $magazaSifre, $v['admin_ad'], $v['admin_kullanici'], $adminSifre);
            merkez_hiz_kaydet('kayit', $v['email']);
            if ($magaza['durum'] === 'aktif') {
                tenant_oturum_ac($magaza);
                flash('Mağazanız oluşturuldu. 30 günlük ücretsiz deneminiz ' . date_tr($magaza['deneme_bitis']) . ' tarihine kadar sürüyor. Şimdi kendi kullanıcı bilgilerinizle giriş yapın.');
                redirect('login.php');
            }
            flash('Başvurunuz alındı. Hesabınız incelenip kısa süre içinde etkinleştirilecek; e-postanıza ya da telefonunuza haber vereceğiz.');
            redirect('kayit.php');
        } catch (DomainException $e) {
            $hata = $e->getMessage();
        }
    }
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#141012">
<title>Mağaza kaydı — 30 gün ücretsiz</title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<script src="<?= e(asset('tema.js')) ?>"></script>
<style>
  .kayit .auth-card{max-width:480px;width:100%}
  .kayit fieldset{margin:0 0 6px}
  .kayit legend{margin-bottom:12px;font:600 10.5px/1 var(--sans);letter-spacing:.2em;text-transform:uppercase;color:var(--accent-ink)}
  .kayit .row2{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .kayit small.hint{display:block;margin-top:2px;font-size:12px}
  @media (max-width:480px){.kayit .row2{grid-template-columns:1fr}}
</style>
<?= ga_head() ?>
</head>
<body class="auth-page kayit">
<main class="auth-single">
<div class="auth-card">
  <a class="mg-marka" href="index.php"><?= brand_mark() ?><span>OptiFlow</span></a>
  <span class="kicker">30 gün ücretsiz deneme</span>
  <h1>Mağazanızı <em>açın</em></h1>
  <p class="muted">Kredi kartı gerekmez. Deneme süresi bitince sizinle iletişime geçeriz.</p>
  <?php if ($hata): ?><div class="alert alert-error" role="alert"><?= e($hata) ?></div><?php endif; ?>
  <form method="post" class="stack" autocomplete="on">
    <?= csrf_field() ?>
    <fieldset>
      <legend>Mağaza bilgileri</legend>
      <label class="field"><span>Mağaza / dükkân adı</span>
        <input name="isim" value="<?= e($v['isim']) ?>" required autofocus placeholder="örn. Aydın Optik">
      </label>
      <label class="field"><span>Mağaza e-postası</span>
        <input name="email" type="email" value="<?= e($v['email']) ?>" autocomplete="username" required>
        <small class="hint">Bu adresle mağaza girişi yaparsınız; personel girişinden farklıdır.</small>
      </label>
      <div class="row2">
        <label class="field"><span>Mağaza şifresi</span>
          <input name="magaza_sifre" type="password" autocomplete="new-password" minlength="8" required>
        </label>
        <label class="field"><span>Şifre (tekrar)</span>
          <input name="magaza_sifre_tekrar" type="password" autocomplete="new-password" minlength="8" required>
        </label>
      </div>
    </fieldset>
    <fieldset>
      <legend>İlk yönetici kullanıcı</legend>
      <label class="field"><span>Ad soyad</span>
        <input name="admin_ad" value="<?= e($v['admin_ad']) ?>" required placeholder="Sistemde görünecek isim">
      </label>
      <label class="field"><span>Kullanıcı adı</span>
        <input name="admin_kullanici" value="<?= e($v['admin_kullanici']) ?>" autocapitalize="none" required>
        <small class="hint">Günlük girişte (mağaza girişinden sonra) bu kullanıcı adını kullanacaksınız.</small>
      </label>
      <label class="field"><span>Şifre</span>
        <input name="admin_sifre" type="password" autocomplete="new-password" minlength="8" required>
      </label>
    </fieldset>
    <button class="btn btn-primary btn-block btn-lg">Mağazamı oluştur</button>
    <small class="hint" style="display:block;margin-top:12px">Kaydolarak kişisel verilerinizin <a class="link" href="kvkk.php" target="_blank" rel="noopener">KVKK aydınlatma metni</a> kapsamında işleneceğini kabul etmiş olursunuz.</small>
  </form>
  <small class="foot">Zaten mağazanız var mı? <a class="link" href="magaza-giris.php">Giriş yapın</a></small>
</div>
</main>
</body>
</html>
