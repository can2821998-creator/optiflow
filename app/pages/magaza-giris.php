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
<meta name="theme-color" content="#081520">
<title>Mağaza girişi</title>
<style>
  :root{--ink:#1a1723;--muted:#6b6673;--bg:#faf8f4;--brand:#7a0a16;--border:#e5dfd6}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);font:15px/1.6 system-ui,Arial,sans-serif;color:var(--ink);padding:16px}
  .card{width:100%;max-width:400px;background:#fff;border:1px solid var(--border);border-radius:16px;padding:32px}
  h1{font-size:22px;margin:0 0 4px}
  p.muted{color:var(--muted);margin:0 0 22px;font-size:14px}
  .field{display:block;margin-bottom:14px}
  .field span{display:block;font-size:13px;font-weight:600;margin-bottom:5px}
  .field input{width:100%;padding:11px 12px;border:1.4px solid var(--border);border-radius:9px;font-size:15px}
  .btn{width:100%;padding:12px;border:0;border-radius:9px;background:var(--brand);color:#fff;font-weight:700;font-size:15px;cursor:pointer;margin-top:6px}
  .alert{background:#fdeceb;color:#9c2b23;border-radius:9px;padding:10px 12px;font-size:13.5px;margin-bottom:16px}
  .foot{display:block;margin-top:18px;text-align:center;font-size:13px;color:var(--muted)}
  .foot a{color:var(--brand);font-weight:600;text-decoration:none}
  .remember{display:flex;gap:9px;align-items:flex-start;font-size:13.5px;margin:2px 0 8px;cursor:pointer}
  .remember input{margin-top:3px;width:16px;height:16px;accent-color:var(--brand)}
  .remember small{display:block;color:var(--muted);font-size:12px}
  .pro-baslik{display:flex;align-items:center;gap:10px;margin:0 0 18px;font-size:17px;color:#122f4d}
  .pro-baslik svg{width:34px;height:34px}.pro-baslik b{color:#b08a2a}
</style>
<?= ga_head() ?>
</head>
<body>
<div class="card">
  <?php if (is_optiflow_desktop()): ?><div class="pro-baslik"><?= brand_mark() ?><span>OptiFlow <b>Pro</b></span></div><?php endif; ?>
  <h1>Mağaza girişi</h1>
  <p class="muted">Önce mağazanızın hesabıyla giriş yapın; ardından kendi kullanıcı bilgilerinizi gireceksiniz.</p>
  <?php foreach (flashes() as [$type, $msg]): ?>
    <div class="alert"><?= e($msg) ?></div>
  <?php endforeach; ?>
  <?php if ($error): ?><div class="alert" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <label class="field"><span>Mağaza e-postası</span>
      <input name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
    </label>
    <label class="field"><span>Mağaza şifresi</span>
      <input name="password" type="password" autocomplete="current-password" required>
    </label>
    <label class="remember"><input type="checkbox" name="hatirla" value="1" <?= $hatirla ? 'checked' : '' ?>>
      <span>Bu bilgisayarda mağazayı hatırla<small>30 gün boyunca mağaza şifresi sorulmaz. Ortak kullanılan bilgisayarlarda işaretlemeyin.</small></span></label>
    <button class="btn">Devam et</button>
  </form>
  <?php if (!is_optiflow_desktop()): ?><small class="foot">Yeni mağazasınız? <a href="kayit.php">Ücretsiz deneyin</a></small><?php endif; ?>
</div>
</body>
</html>
