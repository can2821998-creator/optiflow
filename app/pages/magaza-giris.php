<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

// 4.18.0 — "Farklı mağaza": bu cihazın hatırlanan mağazası ve oturumu bırakılır.
if (is_post() && post('action') === 'farkli') {
    hatirla_cerez_sil(HATIRLA_KULLANICI_CEREZ);   // personel çerezi de (kaydı kendi mağaza veritabanında süresi dolunca silinir)
    magaza_hatirla_unut();
    logout_session();
    redirect('magaza-giris.php');
}

if (tenant_oturum()) {
    redirect('login.php');
}

$error = '';
$email = '';
$hatirla = is_optiflow_desktop();   // masaüstü: mağazanın kendi bilgisayarı → varsayılan işaretli
if (is_post()) {
    $email = post('email');
    $hatirla = isset($_POST['hatirla']);
    $magaza = tenant_giris($email, (string) ($_POST['password'] ?? ''));
    if ($magaza) {
        if ($hatirla) {
            magaza_hatirla_ver($magaza);
        }
        redirect('login.php');
    }
    $error = 'E-posta veya şifre hatalı.';
}
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#141012">
<title>Mağaza girişi · OptiFlow</title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="assets/fonts/manrope-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<?= ga_head() ?>
</head>
<body class="auth-page magaza-giris">
<main class="auth-single">
  <div class="auth-card">
    <a class="mg-marka<?= is_optiflow_desktop() ? ' pro-baslik' : '' ?>" href="<?= is_optiflow_desktop() ? 'magaza-giris.php' : 'index.php' ?>"><?= brand_mark() ?><span>OptiFlow<?php if (is_optiflow_desktop()): ?> <b>Pro</b><?php endif; ?></span></a>
    <span class="kicker">Mağaza girişi</span>
    <h1>Mağazanıza <em>hoş geldiniz</em></h1>
    <p class="muted">Önce mağazanızın hesabıyla giriş yapın; ardından kendi kullanıcı bilgilerinizi gireceksiniz.</p>
    <?php foreach (flashes() as [$type, $msg]): ?>
      <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
    <?php endforeach; ?>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="stack" autocomplete="on">
      <?= csrf_field() ?>
      <label class="field"><span>Mağaza e-postası</span>
        <input name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
      </label>
      <label class="field"><span>Mağaza şifresi</span>
        <input name="password" type="password" autocomplete="current-password" required>
      </label>
      <label class="check-line remember-line"><input type="checkbox" name="hatirla" value="1" <?= $hatirla ? 'checked' : '' ?>>
        <span>Bu bilgisayarda mağazayı hatırla <small class="muted">· 30 gün şifre sorulmaz; ortak bilgisayarda işaretlemeyin</small></span></label>
      <button class="btn btn-primary btn-block btn-lg">Devam et</button>
    </form>
    <?php if (!is_optiflow_desktop()): ?><small class="foot">Yeni mağazasınız? <a class="link" href="kayit.php">Ücretsiz deneyin</a></small><?php endif; ?>
  </div>
</main>
</body>
</html>
