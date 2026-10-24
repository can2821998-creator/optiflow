<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.50.0 — Şube transferi: çerçeve ve ürün stoku şubeler arasında. Gönderince kaynaktan düşer (yolda),
   hedef "Teslim aldık" deyince hedefe eklenir; gönderen yoldayken geri alabilir.
   ========================================================================== */

$me = require_login();
if (!sube_acik()) {
    render_error_page('Şubeler kapalı', 'Şube özelliği bu mağazada açık değil. OptiFlow merkez panelinden açılır.');
}
$izinli = sube_izinli();
$kaynak = aktif_sube() ?: (int) query_int('kaynak');
if (!in_array($kaynak, $izinli, true)) {
    $kaynak = 0;
}

if (is_post()) {
    csrf_check();
    $eylem = post('eylem');
    try {
        if ($eylem === 'gonder') {
            $kalemler = [];
            foreach (['cerceve', 'urun'] as $tur) {
                foreach ((array) ($_POST['adet'][$tur] ?? []) as $id => $adet) {
                    if ((int) $adet > 0) {
                        $kalemler[] = ['tur' => $tur, 'id' => (int) $id, 'adet' => (int) $adet];
                    }
                }
            }
            $tid = sube_transfer_olustur(post_int('kaynak'), post_int('hedef'), $kalemler, (int) $me['id'], post('not'));
            audit('sube_transfer', 'sube', $tid, ['kaynak' => sube_adi(post_int('kaynak')), 'hedef' => sube_adi(post_int('hedef')), 'kalem' => count($kalemler)]);
            flash('Transfer #' . $tid . ' gönderildi: ' . count($kalemler) . ' kalem ' . sube_adi(post_int('hedef')) . ' şubesine yolda.');
        } elseif ($eylem === 'teslim') {
            sube_transfer_teslim(post_int('id'), (int) $me['id']);
            audit('sube_transfer_teslim', 'sube', post_int('id'), []);
            flash('Transfer #' . post_int('id') . ' teslim alındı; stok şubenize eklendi.');
        } elseif ($eylem === 'iptal') {
            sube_transfer_iptal(post_int('id'), (int) $me['id']);
            audit('sube_transfer_iptal', 'sube', post_int('id'), []);
            flash('Transfer #' . post_int('id') . ' geri alındı; stok yerine döndü.', 'info');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('transfer.php' . (post_int('kaynak') ? '?kaynak=' . post_int('kaynak') : ''));
}

$q = mb_substr(trim(query('q')), 0, 60);
$cerceveler = $urunler = [];
if ($kaynak) {
    $p = [$kaynak];
    $ara = '';
    if ($q !== '') {
        $ara = ' AND (brand LIKE ? OR model LIKE ? OR color LIKE ? OR barcode = ?)';
        array_push($p, "%$q%", "%$q%", "%$q%", $q);
    }
    $cerceveler = rows('SELECT * FROM frame_items WHERE sube_id = ? AND is_active = 1 AND qty > 0' . $ara . ' ORDER BY brand, model LIMIT 60', $p);
    if (table_var_mi('urunler')) {
        $pu = [$kaynak];
        $araU = $q !== '' ? ' AND (LOWER(u.ad) LIKE ? OR u.barkod = ?)' : '';
        if ($q !== '') {
            array_push($pu, '%' . mb_strtolower($q) . '%', $q);
        }
        $urunler = rows('SELECT u.id, u.ad, u.barkod, us.stok FROM urunler u JOIN urun_stok us ON us.urun_id = u.id AND us.sube_id = ?
                          WHERE u.is_active = 1 AND u.stok_takip = 1 AND us.stok > 0' . $araU . ' ORDER BY u.ad LIMIT 60', $pu);
    }
}
$transferler = sube_transferler();
$gelen = count(array_filter($transferler, static fn($t) => $t['durum'] === 'yolda' && sube_erisim((int) $t['hedef_sube_id']) && (sube_degerler() === null || in_array((int) $t['hedef_sube_id'], sube_degerler(), true))));
$giden = count(array_filter($transferler, static fn($t) => $t['durum'] === 'yolda' && (sube_degerler() === null || in_array((int) $t['kaynak_sube_id'], sube_degerler(), true))));

page_start('Şube transferi', 'transfer');
page_header('Şube transferi', 'Çerçeve ve ürün stokunu şubeler arasında gönderin; karşı şube teslim alınca stoğuna eklenir.', '', '', 'Atölye', bant_veri([
    ['Gelen · yolda', $gelen, $gelen ? 'amber' : '', ''],
    ['Giden · yolda', $giden, '', ''],
    ['Şube', count(sube_listesi()), '', is_super() ? 'subeler.php' : ''],
]));
?>
<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2><?= icon('truck') ?> Yeni transfer</h2><?= $kaynak ? '<small class="muted">Kaynak: <b>' . e(sube_adi($kaynak)) . '</b></small>' : '' ?></div>
      <?php if (!$kaynak): ?>
        <p class="hint">Göndereceğiniz şubeyi seçin (menüdeki şube seçicisi ya da aşağıdan).</p>
        <div class="chips"><?php foreach ($izinli as $sid): ?><a class="chip" href="transfer.php?kaynak=<?= (int) $sid ?>"><?= e(sube_adi((int) $sid)) ?></a><?php endforeach; ?></div>
      <?php else: ?>
        <form class="search" method="get" role="search" style="margin-bottom:12px">
          <input type="hidden" name="kaynak" value="<?= (int) $kaynak ?>"><?= icon('search') ?>
          <input type="search" name="q" value="<?= e($q) ?>" placeholder="Marka, model, ürün adı ya da barkod" aria-label="Stokta ara" data-barcode-input>
        </form>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="eylem" value="gonder"><input type="hidden" name="kaynak" value="<?= (int) $kaynak ?>">
          <?php if (!$cerceveler && !$urunler): ?>
            <?= empty_state('Bu şubede gönderilecek stok yok', $q !== '' ? '"' . $q . '" için sonuç yok.' : 'Stoktaki çerçeve ve ürünler burada listelenir.') ?>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table transfer-tablo">
                <thead><tr><th>Kalem</th><th class="hide-sm">Barkod</th><th class="num">Stok</th><th class="num">Gönder</th></tr></thead>
                <tbody>
                  <?php foreach ($cerceveler as $f): ?>
                    <tr><td><span class="badge sm tone-violet">Çerçeve</span> <b><?= e(frame_item_label($f)) ?></b></td><td class="hide-sm mono"><?= e((string) $f['barcode']) ?></td>
                      <td class="num mono"><?= (int) $f['qty'] ?></td><td class="num"><input type="number" name="adet[cerceve][<?= (int) $f['id'] ?>]" min="0" max="<?= (int) $f['qty'] ?>" value="0" class="adet-gir" aria-label="Gönderilecek adet"></td></tr>
                  <?php endforeach; ?>
                  <?php foreach ($urunler as $u): ?>
                    <tr><td><span class="badge sm tone-teal">Ürün</span> <b><?= e((string) $u['ad']) ?></b></td><td class="hide-sm mono"><?= e((string) $u['barkod']) ?></td>
                      <td class="num mono"><?= (int) $u['stok'] ?></td><td class="num"><input type="number" name="adet[urun][<?= (int) $u['id'] ?>]" min="0" max="<?= (int) $u['stok'] ?>" value="0" class="adet-gir" aria-label="Gönderilecek adet"></td></tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <div class="grid cols-2" style="margin-top:12px;align-items:end">
              <label class="field"><span>Hedef şube *</span><select name="hedef" required><option value="">Seçin</option>
                <?php foreach (sube_listesi() as $sid => $s): if ((int) $sid === $kaynak) { continue; } ?><option value="<?= (int) $sid ?>"><?= e((string) $s['ad']) ?></option><?php endforeach; ?></select></label>
              <label class="field"><span>Not</span><input name="not" maxlength="255" placeholder="örn. kargo ile, servis aracı"></label>
            </div>
            <button class="btn btn-primary" style="margin-top:12px"><?= icon('truck') ?> Gönder (stok kaynaktan düşer)</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </section>
  </div>
  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2><?= icon('history') ?> Transferler</h2></div>
      <?php if (!$transferler): ?>
        <p class="muted small">Henüz transfer yok.</p>
      <?php endif; ?>
      <ul class="transfer-listesi">
        <?php foreach ($transferler as $t): $yolda = $t['durum'] === 'yolda'; ?>
          <li class="durum-<?= e($t['durum']) ?>">
            <div class="ust"><b class="mono">#<?= (int) $t['id'] ?></b><span><?= e(sube_adi((int) $t['kaynak_sube_id'])) ?> → <?= e(sube_adi((int) $t['hedef_sube_id'])) ?></span>
              <span class="badge sm tone-<?= $yolda ? 'amber' : ($t['durum'] === 'teslim' ? 'green' : 'gray') ?>"><?= $yolda ? 'Yolda' : ($t['durum'] === 'teslim' ? 'Teslim alındı' : 'Geri alındı') ?></span></div>
            <small><?= e(implode(' · ', array_map(static fn($k) => $k['adet'] . ' × ' . $k['ad'], $t['kalemler']))) ?></small>
            <small class="muted"><?= e(date_tr((string) $t['created_at'], true)) ?><?= $t['gonderen'] ? ' · ' . e((string) $t['gonderen']) : '' ?><?= $t['not_metni'] ? ' · ' . e((string) $t['not_metni']) : '' ?></small>
            <?php if ($yolda): ?><div class="eylem">
              <?php if (sube_erisim((int) $t['hedef_sube_id'])): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="teslim"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-sm btn-primary"><?= icon('check') ?> Teslim aldık</button></form><?php endif; ?>
              <?php if (sube_erisim((int) $t['kaynak_sube_id'])): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="iptal"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="linkish" data-onay="Transfer geri alınsın mı? Stok gönderen şubeye döner.">Geri al</button></form><?php endif; ?>
            </div><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  </aside>
</div>
<?php page_end(); ?>
