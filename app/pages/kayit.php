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
    } else {
        try {
            $magaza = tenant_basvuru($v['isim'], $v['email'], $magazaSifre, $v['admin_ad'], $v['admin_kullanici'], $adminSifre);
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
<meta name="theme-color" content="#081520">
<title>Mağaza kaydı — 30 gün ücretsiz</title>
<style>
  :root{--ink:#1a1723;--muted:#6b6673;--bg:#faf8f4;--brand:#7a0a16;--border:#e5dfd6}
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);font:15px/1.6 system-ui,Arial,sans-serif;color:var(--ink);padding:16px 16px 40px}
  .card{width:100%;max-width:460px;background:#fff;border:1px solid var(--border);border-radius:16px;padding:32px}
  h1{font-size:22px;margin:0 0 4px}
  p.muted{color:var(--muted);margin:0 0 22px;font-size:14px}
  .badge{display:inline-block;background:#fdf1e0;color:#8a5a12;font-size:12.5px;font-weight:700;padding:4px 10px;border-radius:99px;margin-bottom:14px}
  fieldset{border:0;padding:0;margin:0 0 18px}
  legend{font-size:13px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;margin-bottom:10px;padding:0}
  .field{display:block;margin-bottom:12px}
  .field span{display:block;font-size:13px;font-weight:600;margin-bottom:5px}
  .field input{width:100%;padding:11px 12px;border:1.4px solid var(--border);border-radius:9px;font-size:15px}
  .row2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
  .btn{width:100%;padding:12px;border:0;border-radius:9px;background:var(--brand);color:#fff;font-weight:700;font-size:15px;cursor:pointer;margin-top:6px}
  .alert{background:#fdeceb;color:#9c2b23;border-radius:9px;padding:10px 12px;font-size:13.5px;margin-bottom:16px}
  .foot{display:block;margin-top:18px;text-align:center;font-size:13px;color:var(--muted)}
  .foot a{color:var(--brand);font-weight:600;text-decoration:none}
  small.hint{display:block;color:var(--muted);font-size:12px;margin-top:4px}
</style>
<?= ga_head() ?>
</head>
<body>
<div class="card">
  <span class="badge">30 gün ücretsiz deneme</span>
  <h1>Mağazanızı açın</h1>
  <p class="muted">Kredi kartı gerekmez. Deneme süresi bitince sizinle iletişime geçeriz.</p>
  <?php if ($hata): ?><div class="alert" role="alert"><?= e($hata) ?></div><?php endif; ?>
  <form method="post" autocomplete="on">
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
    <button class="btn">Mağazamı oluştur</button>
    <small class="hint" style="display:block;margin-top:12px">Kaydolarak kişisel verilerinizin <a href="kvkk.php" target="_blank" rel="noopener">KVKK aydınlatma metni</a> kapsamında işleneceğini kabul etmiş olursunuz.</small>
  </form>
  <small class="foot">Zaten mağazanız var mı? <a href="magaza-giris.php">Giriş yapın</a></small>
</div>
</body>
</html>
