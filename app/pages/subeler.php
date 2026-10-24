<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.49.0 — Şubeler: seçici (herkes, eylem=sec), karşılaştırma + şube tanımı + personel ataması (yönetici).
   Özellik 'sube' merkez panelinden açılır; kapalıyken sayfa açılmaz.
   ========================================================================== */

$me = require_login();
if (!sube_acik()) {
    render_error_page('Şubeler kapalı', 'Şube özelliği bu mağazada açık değil. OptiFlow merkez panelinden açılır.');
}

if (is_post()) {
    csrf_check();
    $eylem = post('eylem');
    if ($eylem === 'sec') {
        sube_sec(post_int('sube'));
        $don = post('don');
        redirect(preg_match('/^[a-z0-9-]+\.php(\?[^\s<>"]*)?$/i', $don) ? $don : 'index.php');
    }
    require_super();
    try {
        if ($eylem === 'kaydet') {
            $id = sube_kaydet($_POST, post_int('id') ?: null);
            audit('sube_kaydet', 'sube', $id, ['ad' => post('ad')]);
            flash('Şube kaydedildi: ' . mb_substr(trim(post('ad')), 0, 80) . '.');
        } elseif ($eylem === 'ata') {
            foreach ((array) ($_POST['atama'] ?? []) as $uid => $subeler) {
                sube_personel_ata((int) $uid, array_map('intval', (array) $subeler));
            }
            foreach (array_diff(array_map('intval', (array) ($_POST['personel'] ?? [])), array_map('intval', array_keys((array) ($_POST['atama'] ?? [])))) as $uid) {
                sube_personel_ata((int) $uid, []);   // hiçbir kutu işaretlenmediyse atama silinir (ana şube)
            }
            audit('sube_atama', 'sube', null, []);
            flash('Personel şubeleri kaydedildi.');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('subeler.php' . (post_int('id') ? '?duzenle=' . post_int('id') : ''));
}

require_super();
$tablo = sube_karsilastirma();
$tum = sube_listesi(true);
$duzenle = query_int('duzenle') ? ($tum[query_int('duzenle')] ?? null) : null;
$personel = rows("SELECT id, full_name, role FROM user_accounts WHERE is_active = 1 ORDER BY role = 'super_yetkili' DESC, full_name");
$top = static fn(string $k) => array_sum(array_column($tablo, $k));

page_start('Şubeler', 'subeler');
page_header('Şubeler', 'Şubeler yan yana: bugünkü iş, kasa, atölye ve bekleyen gözlükler. Müşteri kartları bütün şubelerde ortak.', '', '', 'Yönetim', bant_veri([
    ['Şube', count($tablo), '', ''],
    ['Bugün sipariş', $top('siparis'), '', ''],
    ['Bugün tahsilat', money($top('tahsilat')), '', ''],
    ['Geciken', $top('geciken'), $top('geciken') ? 'red' : '', ''],
]));
?>
<section class="card">
  <div class="card-head"><h2><?= icon('chart') ?> Bugün şubeler</h2><small class="muted"><?= e(date_long()) ?></small></div>
  <div class="table-wrap">
    <table class="table sube-tablo">
      <thead><tr><th>Şube</th><th class="num">Sipariş</th><th class="num">Tahsilat</th><th class="num">Atölyede</th><th class="num">Hazır bekleyen</th><th class="num">Geciken</th><th class="num hide-sm">Açık bakiye</th><th class="hide-sm">Yolda</th><th>Kasa</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tablo as $id => $r): ?>
          <tr>
            <td><span class="sube-rozet"><?= e((string) $r['sube']['kisa']) ?></span> <b><?= e((string) $r['sube']['ad']) ?></b></td>
            <td class="num mono"><?= (int) $r['siparis'] ?></td>
            <td class="num mono"><?= e(money($r['tahsilat'])) ?></td>
            <td class="num mono"><?= (int) $r['atolyede'] ?></td>
            <td class="num mono"><?= (int) $r['hazir'] ?></td>
            <td class="num mono <?= $r['geciken'] ? 'text-danger' : '' ?>"><?= (int) $r['geciken'] ?></td>
            <td class="num mono hide-sm"><?= e(money($r['bakiye'])) ?></td>
            <td class="hide-sm"><?= $r['yolda'] ? '<span class="badge sm tone-amber">' . (int) $r['yolda'] . ' gelecek</span>' : '<span class="muted">—</span>' ?></td>
            <td><?= $r['kasa'] ? '<span class="badge sm tone-green">Kapandı</span>' : '<span class="badge sm tone-gray">Açık</span>' ?></td>
            <td class="row-actions">
              <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="sec"><input type="hidden" name="sube" value="<?= (int) $id ?>"><input type="hidden" name="don" value="index.php"><button class="btn btn-sm">Şubeye geç</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot><tr><td>Toplam</td><td class="num mono"><?= (int) $top('siparis') ?></td><td class="num mono"><?= e(money($top('tahsilat'))) ?></td><td class="num mono"><?= (int) $top('atolyede') ?></td>
        <td class="num mono"><?= (int) $top('hazir') ?></td><td class="num mono"><?= (int) $top('geciken') ?></td><td class="num mono hide-sm"><?= e(money($top('bakiye'))) ?></td><td class="hide-sm"></td><td></td><td></td></tr></tfoot>
    </table>
  </div>
</section>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2><?= icon('users') ?> Personel ve şubeler</h2><small class="muted">Personel yalnız işaretli şubeleri görür; yönetici hepsini</small></div>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="ata">
        <div class="table-wrap">
          <table class="table sube-atama">
            <thead><tr><th>Personel</th><?php foreach (sube_listesi() as $s): ?><th class="num"><?= e((string) $s['kisa']) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($personel as $u): $yonetici = $u['role'] === 'super_yetkili'; $atanan = sube_personel((int) $u['id']); ?>
                <tr>
                  <td><b><?= e((string) $u['full_name']) ?></b><small class="block muted"><?= $yonetici ? 'Yönetici · bütün şubeler' : ($atanan ? count($atanan) . ' şube' : 'Atanmamış → ' . e(sube_adi((int) array_key_first(sube_listesi())))) ?></small>
                    <?php if (!$yonetici): ?><input type="hidden" name="personel[]" value="<?= (int) $u['id'] ?>"><?php endif; ?></td>
                  <?php foreach (sube_listesi() as $sid => $s): ?>
                    <td class="num"><?php if ($yonetici): ?><span class="muted">✓</span><?php else: ?>
                      <input type="checkbox" name="atama[<?= (int) $u['id'] ?>][]" value="<?= (int) $sid ?>" <?= in_array((int) $sid, $atanan, true) ? 'checked' : '' ?> aria-label="<?= e($u['full_name'] . ' · ' . $s['ad']) ?>">
                    <?php endif; ?></td>
                  <?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <button class="btn btn-primary" style="margin-top:12px"><?= icon('check') ?> Atamaları kaydet</button>
      </form>
    </section>
  </div>
  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2><?= icon('home') ?> <?= $duzenle ? 'Şubeyi düzenle' : 'Yeni şube' ?></h2><?php if ($duzenle): ?><a class="link" href="subeler.php">Yeni ekle</a><?php endif; ?></div>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="kaydet"><input type="hidden" name="id" value="<?= (int) ($duzenle['id'] ?? 0) ?>">
        <label class="field"><span>Şube adı *</span><input name="ad" value="<?= e((string) ($duzenle['ad'] ?? '')) ?>" required maxlength="80" placeholder="örn. Kadıköy"></label>
        <label class="field"><span>Kısa kod</span><input name="kisa" value="<?= e((string) ($duzenle['kisa'] ?? '')) ?>" maxlength="4" placeholder="KDK"></label>
        <label class="field"><span>Telefon</span><input name="telefon" value="<?= e((string) ($duzenle['telefon'] ?? '')) ?>" inputmode="tel"></label>
        <label class="field"><span>Adres</span><input name="adres" value="<?= e((string) ($duzenle['adres'] ?? '')) ?>"></label>
        <?php if ($duzenle): ?><label class="check-field"><input type="checkbox" name="is_active" value="1" <?= (int) $duzenle['is_active'] ? 'checked' : '' ?>> Etkin</label><?php endif; ?>
        <button class="btn btn-primary"><?= $duzenle ? 'Kaydet' : 'Şube ekle' ?></button>
      </form>
      <ul class="mini-list" style="margin-top:14px">
        <?php foreach ($tum as $s): ?>
          <li><span><span class="sube-rozet"><?= e((string) $s['kisa']) ?></span> <b><?= e((string) $s['ad']) ?></b><?= (int) $s['is_active'] ? '' : ' <small class="muted">· kapalı</small>' ?></span><a class="link" href="subeler.php?duzenle=<?= (int) $s['id'] ?>">Düzenle</a></li>
        <?php endforeach; ?>
      </ul>
    </section>
  </aside>
</div>
<?php page_end(); ?>
