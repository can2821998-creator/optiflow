<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_super();

$sonuc = null;
$gonderilen = null;

if (is_post()) {
    csrf_check();
    $eylem = post('eylem');

    if ($eylem === 'gonder') {
        $baslik = trim(mb_substr(post('baslik'), 0, 80));
        $govde  = trim(mb_substr(post('govde'), 0, 240));
        $link   = trim(post('link'));
        $hedef  = post('hedef');

        if ($baslik === '') {
            flash('Başlık boş olamaz.', 'error');
            redirect('push-admin.php');
        }
        if ($link !== '' && !preg_match('#^[a-z0-9\-]+\.php(\?[\w=&%\-.]*)?$#i', $link)) {
            flash('Bağlantı yalnızca site içi bir sayfa olabilir (örn. order.php?id=89).', 'error');
            redirect('push-admin.php');
        }

        $mesaj = [
            'title' => $baslik,
            'body'  => $govde,
            'url'   => $link !== '' ? $link : 'index.php',
            'tag'   => 'duyuru-' . time(),
        ];

        $kullanicilar = [];
        if ($hedef === 'ben') {
            $kullanicilar = [(int) $me['id']];
        } elseif (ctype_digit((string) $hedef)) {
            $kullanicilar = [(int) $hedef];
        }

        $sonuc = push_send($mesaj, $kullanicilar);
        $gonderilen = $mesaj;

        audit('push_broadcast', 'user', (int) $me['id'], [
            'başlık'   => $baslik,
            'hedef'    => $hedef === 'herkes' ? 'tüm cihazlar' : ($hedef === 'ben' ? 'kendi cihazlarım' : 'kullanıcı #' . $hedef),
            'ulaşan'   => $sonuc['sent'],
            'ulaşmayan' => $sonuc['failed'],
        ]);
    }

    if ($eylem === 'sil') {
        $sid = post_int('id');
        if ($sid > 0) {
            q('DELETE FROM push_subscriptions WHERE id = ?', [$sid]);
            flash('Cihaz kaydı silindi.');
        }
        redirect('push-admin.php');
    }
}

$tanilar = push_diagnostics();
$sorunVar = false;
foreach ($tanilar as $t) {
    if (!$t['ok']) {
        $sorunVar = true;
    }
}

$cihazlar = [];
try {
    $cihazlar = rows(
        'SELECT p.*, u.full_name, u.username
           FROM push_subscriptions p
           LEFT JOIN user_accounts u ON u.id = p.user_id
          ORDER BY p.created_at DESC LIMIT 100'
    );
} catch (Throwable $e) {
    $cihazlar = [];
}

$kullanicilar = [];
try {
    $kullanicilar = rows(
        'SELECT u.id, u.full_name, COUNT(p.id) AS cihaz
           FROM user_accounts u LEFT JOIN push_subscriptions p ON p.user_id = u.id
          WHERE u.is_active = 1 GROUP BY u.id, u.full_name ORDER BY u.full_name'
    );
} catch (Throwable $e) {
    $kullanicilar = [];
}

page_start('Bildirim gönder', 'push');
page_header(
    'Bildirim gönder',
    'Kayıtlı tüm telefonlara anında duyuru geçin',
    '',
    '',
    'Yönetim'
);
?>

<?php if ($sonuc !== null): ?>
  <div class="alert alert-<?= $sonuc['sent'] > 0 ? 'ok' : 'error' ?>" role="status">
    <?php if ($sonuc['sent'] > 0): ?>
      Bildirim <b><?= (int) $sonuc['sent'] ?></b> cihaza ulaştı<?= $sonuc['failed'] ? ', ' . (int) $sonuc['failed'] . ' cihaza ulaşmadı' : '' ?>.
    <?php else: ?>
      Bildirim gönderilemedi<?= $sonuc['reason'] !== '' ? ': ' . e($sonuc['reason']) : '' ?>.
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="split">
  <div class="split-main">

    <section class="card">
      <div class="card-head">
        <h2><?= icon('bell') ?> Yeni bildirim</h2>
        <small class="muted">Başlık ve alt başlık, telefonun bildirim ekranında göründüğü gibi</small>
      </div>

      <form method="post" class="stack" id="push-form">
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="gonder">

        <label class="field">
          <span>Başlık</span>
          <input name="baslik" maxlength="80" required placeholder="Örn. Mağaza bugün 19:00'da kapanıyor"
                 data-push-preview="title" value="<?= e(post('baslik')) ?>">
        </label>

        <label class="field">
          <span>Alt başlık (bildirim metni)</span>
          <textarea name="govde" maxlength="240" rows="3" placeholder="Örn. Teslimatları 18:30'a kadar tamamlayalım."
                    data-push-preview="body"><?= e(post('govde')) ?></textarea>
        </label>

        <div class="grid cols-2">
          <label class="field">
            <span>Dokununca açılacak sayfa <small class="muted">(isteğe bağlı)</small></span>
            <input name="link" placeholder="index.php" value="<?= e(post('link')) ?>">
          </label>
          <label class="field">
            <span>Kime gidecek</span>
            <select name="hedef">
              <option value="herkes">Tüm cihazlar (<?= count($cihazlar) ?>)</option>
              <option value="ben">Yalnızca benim cihazlarım (deneme)</option>
              <?php foreach ($kullanicilar as $k): ?>
                <option value="<?= (int) $k['id'] ?>"><?= e($k['full_name']) ?> (<?= (int) $k['cihaz'] ?> cihaz)</option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>

        <div class="push-preview" aria-hidden="true">
          <span class="push-preview-label">Telefonda böyle görünecek</span>
          <div class="push-note">
            <span class="push-note-ic">
              <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-linecap="round">
                <circle cx="17.5" cy="25" r="8.5" stroke-width="2.6"/>
                <circle cx="30.5" cy="25" r="8.5" stroke-width="2.6"/>
                <path d="M22.3 22.4c1.1-.9 2.3-.9 3.4 0" stroke-width="2.6"/>
              </svg>
            </span>
            <div class="push-note-txt">
              <b data-push-out="title"><?= e(setting('shop_name', 'OptiFlow')) ?></b>
              <span data-push-out="body">Alt başlık burada görünür.</span>
            </div>
            <small>şimdi</small>
          </div>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary btn-lg"><?= icon('bell') ?> Bildirimi gönder</button>
        </div>
      </form>
    </section>

    <?php if ($sonuc !== null && $sonuc['detail']): ?>
      <section class="card">
        <div class="card-head"><h2>Gönderim dökümü</h2><small class="muted"><?= e($gonderilen['title'] ?? '') ?></small></div>
        <div class="table-wrap">
          <table class="table compact">
            <thead><tr><th>Cihaz</th><th>Servis</th><th>Sonuç</th><th>Açıklama</th></tr></thead>
            <tbody>
              <?php foreach ($sonuc['detail'] as $d): ?>
                <tr>
                  <td><?= e($d['device'] !== '' ? $d['device'] : 'Bilinmeyen cihaz') ?></td>
                  <td class="small muted"><?= e($d['host']) ?></td>
                  <td>
                    <?php if ($d['code'] >= 200 && $d['code'] < 300): ?>
                      <span class="badge sm tone-green">Ulaştı</span>
                    <?php else: ?>
                      <span class="badge sm tone-red"><?= $d['code'] > 0 ? 'HTTP ' . (int) $d['code'] : 'Bağlanamadı' ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="small"><?= e($d['error'] !== '' ? $d['error'] : '—') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head">
        <h2>Kayıtlı cihazlar</h2>
        <small class="muted"><?= count($cihazlar) ?> cihaz</small>
      </div>
      <?php if (!$cihazlar): ?>
        <div class="empty">
          <b>Henüz kayıtlı cihaz yok</b>
          <p>Her kullanıcı kendi telefonundan <b>Profilim → Telefon bildirimleri → Bu cihazda aç</b> demeli.
             iPhone'da uygulamanın önce ana ekrana eklenmiş olması gerekir.</p>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table compact">
            <thead><tr><th>Kullanıcı</th><th>Cihaz</th><th class="hide-sm">Eklendi</th><th class="hide-sm">Son başarılı</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($cihazlar as $c): ?>
                <tr>
                  <td><b><?= e($c['full_name'] ?? 'Silinmiş kullanıcı') ?></b></td>
                  <td><?= e($c['device'] ?: '—') ?><?= (int) $c['fail_count'] > 0 ? ' <span class="badge sm tone-amber">' . (int) $c['fail_count'] . ' hata</span>' : '' ?></td>
                  <td class="hide-sm small muted"><?= e(date_tr($c['created_at'])) ?></td>
                  <td class="hide-sm small muted"><?= $c['last_ok_at'] ? e(date_tr($c['last_ok_at'], true)) : 'hiç' ?></td>
                  <td class="row-actions">
                    <form method="post" data-confirm="Bu cihaz kaydı silinsin mi?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="eylem" value="sil">
                      <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                      <button class="icon-btn danger sm" title="Kaydı sil"><?= icon('trash') ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <div class="split-side">
    <section class="card">
      <div class="card-head">
        <h2>Sistem durumu</h2>
        <span class="badge sm tone-<?= $sorunVar ? 'red' : 'green' ?>"><?= $sorunVar ? 'Sorun var' : 'Hazır' ?></span>
      </div>
      <ul class="kv">
        <?php foreach ($tanilar as $t): ?>
          <li>
            <span>
              <b style="display:block;font-size:13.5px"><?= e($t['ad']) ?></b>
              <small class="muted"><?= e($t['not']) ?></small>
            </span>
            <span class="badge sm tone-<?= $t['ok'] ? 'green' : 'red' ?>"><?= $t['ok'] ? '✓' : '✗' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="hint">
        Bir satır kırmızıysa bildirim gönderilemez. En sık sebep: hosting'in dış
        bağlantıyı engellemesi ya da göçün çalıştırılmamış olması
        (<b>kontrol.php</b> sayfasını bir kez açın).
      </p>
    </section>

    <section class="card">
      <div class="card-head"><h2>Nasıl çalışır</h2></div>
      <ul class="kv">
        <li><span><b>1.</b> Kullanıcı kendi telefonunda <b>Profilim</b> sayfasından bildirimi açar.</span></li>
        <li><span><b>2.</b> iPhone'da uygulama <b>ana ekrana eklenmiş</b> olmalı (Safari → Paylaş → Ana Ekrana Ekle).</span></li>
        <li><span><b>3.</b> Buradan yazdığınız başlık ve alt başlık o cihazlara anında düşer.</span></li>
      </ul>
    </section>
  </div>
</div>

<?php page_end(['push-admin.js']);
