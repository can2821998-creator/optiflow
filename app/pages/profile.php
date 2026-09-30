<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_login();

/* Müşteri sayfasında görünme izni ve kısa tanıtım ("Gözlüğünüzü kim yaptı?") */
if (is_post() && post('eylem') === 'usta_profil') {
    try {
        require_once dirname(__DIR__) . '/maker.php';
        maker_profile_save((int) $me['id'], post('gorunsun') === '1', post('unvan'), post('tanitim'));
        flash('Müşteri sayfası tanıtımınız kaydedildi.');
    } catch (Throwable $e) {
        flash('Kaydedilemedi: ' . $e->getMessage(), 'error');
    }
    redirect('profile.php');
}

if (is_post()) {
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    $again = (string) ($_POST['new_password_again'] ?? '');
    $hash = (string) scalar('SELECT password_hash FROM user_accounts WHERE id = ?', [$me['id']]);
    if (!password_verify($current, $hash)) {
        flash('Mevcut parola hatalı.', 'error');
    } elseif ($new !== $again) {
        flash('Yeni parolalar eşleşmiyor.', 'error');
    } elseif ($p = password_problem($new)) {
        flash($p, 'error');
    } elseif (password_verify($new, $hash)) {
        flash('Yeni parola eskisiyle aynı olamaz.', 'error');
    } else {
        set_password((int) $me['id'], $new);
        audit('user_password', 'user', (int) $me['id'], ['kendi parolası' => 'evet']);
        flash('Parolanız değiştirildi. Diğer cihazlardaki oturumlar kapatıldı.');
    }
    redirect('profile.php');
}

$ustaYuklu = false;
$ustaProf = ['show' => false, 'title' => '', 'bio' => ''];
try {
    require_once dirname(__DIR__) . '/maker.php';
    $ustaYuklu = function_exists('maker_profile');
    if ($ustaYuklu) {
        $ustaProf = maker_profile((int) $me['id']);
    }
} catch (Throwable $e) {
    $ustaYuklu = false;
}

page_start('Profilim');
page_header('Profilim', e($me['full_name']) . ' · ' . e(roles()[$me['role']] ?? $me['role']), '', '', 'Hesap');
?>
<section class="card narrow">
  <div class="card-head"><h2>Parola değiştir</h2></div>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label class="field"><span>Kullanıcı adı</span><input value="<?= e($me['username']) ?>" disabled></label>
    <label class="field"><span>Mevcut parola</span><input type="password" name="current_password" autocomplete="current-password" required></label>
    <label class="field"><span>Yeni parola</span><input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label>
    <label class="field"><span>Yeni parola (tekrar)</span><input type="password" name="new_password_again" autocomplete="new-password" minlength="8" required></label>
    <small class="muted">En az 8 karakter, en az bir harf ve bir rakam.</small>
    <div class="form-actions"><button class="btn btn-primary"><?= icon('lock') ?> Parolayı değiştir</button></div>
  </form>
</section>

<?php if ($ustaYuklu): ?>
<section class="card narrow">
  <div class="card-head"><h2><?= icon('user') ?> Müşteri sayfasında tanıtımım</h2></div>
  <p class="muted" style="margin-bottom:14px">
    Bir gözlük siparişinde <b>"Kim üzerinde çalışıyor"</b> olarak sizi seçtiklerinde ya da kalite kontrolü siz yaptığınızda,
    müşteri durum sayfasında <b>adınızı ve aşağıdaki tanıtımı</b> görür: "Gözlüğünüzü Ali hazırladı". Müşteriyle aramızda küçük bir aile bağı kurar.
    <b>Yalnızca ilk adınız</b> gösterilir; soyadınız ve telefonunuz hiçbir zaman gösterilmez. Bu tamamen sizin izninize bağlıdır ve istediğiniz an kapatabilirsiniz.
  </p>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="eylem" value="usta_profil">
    <label style="display:flex;gap:10px;align-items:flex-start;font-weight:600">
      <input type="checkbox" name="gorunsun" value="1" <?= $ustaProf['show'] ? 'checked' : '' ?> style="margin-top:4px">
      <span>Adım ve tanıtımım müşteri sayfasında görünsün</span>
    </label>
    <label class="field"><span>Unvanım (isteğe bağlı)</span><input name="unvan" maxlength="60" value="<?= e($ustaProf['title']) ?>" placeholder="Örn. Montaj ustası"></label>
    <label class="field"><span>Kısa tanıtım (isteğe bağlı)</span><textarea name="tanitim" rows="2" maxlength="240" placeholder="Örn. 12 yıldır gözlük yapıyorum. İlk günlerde bir sıkıntı olursa hemen uğrayın."><?= e($ustaProf['bio']) ?></textarea></label>
    <div class="form-actions"><button class="btn btn-primary">Kaydet</button></div>
  </form>
</section>
<?php endif; ?>

<section class="card narrow" data-push-card data-csrf="<?= e(csrf_token()) ?>">
  <div class="card-head">
    <h2><?= icon('bell') ?> Telefon bildirimleri</h2>
    <span class="badge sm tone-gray" data-push-state>Kontrol ediliyor…</span>
  </div>
  <p class="muted" style="margin-bottom:16px">
    Cam geldiğinde, sipariş hazır olduğunda ve teslim günü geldiğinde telefonunuza
    bildirim düşer. Her cihaz için ayrı ayrı açılır.
  </p>
  <div class="btn-row">
    <button type="button" class="btn btn-primary" data-push-toggle disabled>Bu cihazda aç</button>
    <button type="button" class="btn btn-sm" data-push-test hidden>Deneme bildirimi gönder</button>
  </div>
  <p class="alert alert-warn" data-push-reason hidden style="margin-top:14px"></p>
  <p class="hint">
    iPhone'da bildirimler yalnızca uygulama <b>ana ekrana eklendiğinde</b> çalışır
    (Safari → Paylaş → Ana Ekrana Ekle). Android'de tarayıcıda da çalışır.
  </p>
</section>
<?php page_end(['push.js']);
