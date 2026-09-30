<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.12.0 — Tedarikçiye cam siparişi (sipariş fişi)
   ========================================================================== */

$me = require_login();
ozellik_gereksin('cam_siparis');

$fisId = is_post() ? post_int('fis_id') : query_int('id');

if (is_post()) {
    $eylem = post('eylem');
    try {
        if ($eylem === 'olustur') {
            $id = cam_fis_olustur(post_int('supplier_id'), (array) ($_POST['kalem'] ?? []), post('not'));
            flash('Sipariş fişi oluşturuldu. Yazdırın ya da WhatsApp/e-posta ile gönderin, sonra "Gönderildi" deyin.');
            redirect('cam-siparis.php?id=' . $id);
        }
        if ($eylem === 'gonderildi' && $fisId > 0) {
            $n = cam_fis_gonderildi($fisId, post('kanal'), post('ref'));
            flash($n . ' cam "Depoya sipariş verildi" olarak işaretlendi.');
            redirect('cam-siparis.php?id=' . $fisId);
        }
        if ($eylem === 'iptal' && $fisId > 0) {
            cam_fis_iptal($fisId);
            flash('Fiş iptal edildi; camlar eksik listesine döndü.');
            redirect('cam-siparis.php');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect($fisId > 0 ? 'cam-siparis.php?id=' . $fisId : 'cam-siparis.php');
}

$tedarikciler = rows('SELECT id, name, phone, email FROM suppliers WHERE is_active = 1 ORDER BY name');

/* ---------- Tek fiş ---------- */
if ($fisId > 0) {
    $fis = row('SELECT c.*, s.name AS tedarikci, s.phone AS t_tel, s.email AS t_eposta, s.contact_name FROM cam_siparisleri c LEFT JOIN suppliers s ON s.id = c.supplier_id WHERE c.id = ?', [$fisId]);
    if (!$fis) {
        render_error_page('Fiş bulunamadı', 'Silinmiş olabilir.');
    }
    $kalemler = cam_fis_kalemleri($fisId);
    $metin = cam_fis_metni($fis, $kalemler);
    $waLink = wa_url((string) $fis['t_tel'], $metin);
    $mailLink = filter_var((string) $fis['t_eposta'], FILTER_VALIDATE_EMAIL)
        ? 'mailto:' . rawurlencode((string) $fis['t_eposta']) . '?subject=' . rawurlencode(setting('shop_name', 'OptiFlow') . ' cam siparişi #' . $fisId) . '&body=' . rawurlencode($metin)
        : '';
    [$dAd, $dTon] = cam_fis_durumlari()[$fis['durum']] ?? [$fis['durum'], 'gray'];
    page_start('Cam siparişi #' . $fisId, 'cam-siparis');
    ?>
    <a class="back-link no-print" href="cam-siparis.php"><?= icon('arrow-left') ?> Cam siparişleri</a>
    <?php page_header('Cam siparişi #' . $fisId, e((string) $fis['tedarikci']) . ' · ' . date_tr($fis['created_at']), '<span class="badge tone-' . e($dTon) . '">' . e($dAd) . '</span>', '', 'Tedarik'); ?>
    <section class="card cam-fis">
      <div class="card-head"><h2><?= e(setting('shop_name', 'OptiFlow')) ?> → <?= e((string) $fis['tedarikci']) ?></h2><small class="muted"><?= count($kalemler) ?> cam</small></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Sipariş</th><th>Cam</th><th>Göz / değerler</th><th class="hide-sm">PD · yükseklik</th><th>Durum</th></tr></thead>
        <tbody>
        <?php foreach ($kalemler as $i): ?>
          <tr>
            <td><a class="link" href="order.php?id=<?= (int) $i['order_id'] ?>"><?= e(order_no((int) $i['order_id'])) ?></a><small class="block muted"><?= e(mb_substr((string) $i['first_name'], 0, 1) . '. ' . $i['last_name']) ?><?= $i['promised_date'] ? ' · söz ' . e(date_tr($i['promised_date'])) : '' ?></small></td>
            <td><?= e((string) ($i['lens_type'] ?: $i['lens_value'])) ?></td>
            <td><?= e(cam_kalem_tarifi($i)) ?></td>
            <td class="hide-sm"><small><?= e(trim(($i['right_pd'] ? $i['right_pd'] . '/' . $i['left_pd'] : (string) $i['pd']) . ' · ' . ($i['right_height'] ? $i['right_height'] . '/' . $i['left_height'] : '—'), ' ·')) ?></small></td>
            <td><?= stock_badge((string) $i['stock_status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($fis['notlar']): ?><p class="hint">Not: <?= e((string) $fis['notlar']) ?></p><?php endif; ?>
    </section>
    <?php if ($fis['durum'] === 'taslak'): ?>
      <section class="card no-print">
        <div class="card-head"><h2>Gönder</h2></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
          <button class="btn" type="button" data-yazdir><?= icon('print') ?> Yazdır</button>
          <?php if ($waLink !== ''): ?><a class="btn btn-wa" href="<?= e($waLink) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> WhatsApp</a><?php endif; ?>
          <?php if ($mailLink !== ''): ?><a class="btn" href="<?= e($mailLink) ?>"><?= icon('chat') ?> E-posta</a><?php endif; ?>
        </div>
        <?php if ($waLink === '' && $mailLink === ''): ?><p class="hint">Tedarikçinin telefonu veya e-postası yok; <a class="link" href="supplier.php?id=<?= (int) $fis['supplier_id'] ?>">tedarikçi kartından</a> ekleyebilirsiniz.</p><?php endif; ?>
        <details><summary class="linkish">Metni göster / kopyala</summary><textarea readonly rows="10" style="width:100%;margin-top:8px" data-kopyala><?= e($metin) ?></textarea></details>
        <form method="post" class="grid cols-3" style="align-items:end;margin-top:12px">
          <?= csrf_field() ?><input type="hidden" name="eylem" value="gonderildi"><input type="hidden" name="fis_id" value="<?= $fisId ?>">
          <label class="field"><span>Nasıl gönderildi</span><select name="kanal"><option value="whatsapp">WhatsApp</option><option value="eposta">E-posta</option><option value="portal">Tedarikçi portalı</option><option value="telefon">Telefon</option><option value="yazdir">Çıktı / faks</option></select></label>
          <label class="field"><span>Tedarikçi sipariş no (varsa)</span><input name="ref" maxlength="60"></label>
          <div class="form-actions"><button class="btn btn-primary"><?= icon('check') ?> Gönderildi</button></div>
        </form>
        <form method="post" data-confirm="Fiş iptal edilsin mi? Camlar eksik listesine döner." style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="eylem" value="iptal"><input type="hidden" name="fis_id" value="<?= $fisId ?>"><button class="linkish danger">Fişi iptal et</button></form>
      </section>
    <?php elseif ($fis['durum'] === 'gonderildi'): ?>
      <p class="hint no-print"><?= e(date_tr($fis['gonderim_at'], true)) ?> tarihinde gönderildi<?= $fis['tedarikci_ref'] ? ' · tedarikçi no ' . e((string) $fis['tedarikci_ref']) : '' ?>. Camlar gelince <a class="link" href="stock.php?tab=siparis">Depo · Stok › Depoya sipariş verildi</a> sekmesinden "geldi" işaretleyin; fiş kendiliğinden tamamlanır.</p>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

/* ---------- Liste + yeni fiş ---------- */
$eksikler = cam_eksikler();
$fisler = rows(
    "SELECT c.*, s.name AS tedarikci, (SELECT COUNT(*) FROM prescription_lens_items i WHERE i.cam_siparis_id = c.id) AS adet
       FROM cam_siparisleri c LEFT JOIN suppliers s ON s.id = c.supplier_id
      WHERE c.durum <> 'iptal' ORDER BY c.id DESC LIMIT 60"
);

page_start('Cam siparişleri', 'cam-siparis');
page_header('Cam siparişleri', 'Eksik camları tedarikçiye tek fişte gönderin; gönderilince “Depoya sipariş verildi” olur.', '', '', 'Tedarik');
?>
<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2>Sipariş verilecek camlar</h2><small class="muted"><?= count($eksikler) ?> cam · teslim sözü yakın olan üstte</small></div>
      <?php if (!$eksikler): ?>
        <?= empty_state('Eksik cam yok', 'Siparişlerde "stokta yok" işaretli ve henüz fişe girmemiş cam bulunmuyor.') ?>
      <?php elseif (!$tedarikciler): ?>
        <p class="hint">Önce <a class="link" href="suppliers.php">tedarikçi</a> ekleyin.</p>
      <?php else: ?>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="eylem" value="olustur">
          <div class="table-wrap"><table class="table">
            <thead><tr><th class="selc"><input type="checkbox" data-hepsini-sec="kalem[]" aria-label="Hepsini seç"></th><th>Sipariş</th><th>Cam</th><th>Değerler</th><th class="hide-sm">Tedarikçi</th></tr></thead>
            <tbody>
            <?php foreach ($eksikler as $i): ?>
              <tr>
                <td class="selc"><input type="checkbox" name="kalem[]" value="<?= (int) $i['id'] ?>" aria-label="Seç"></td>
                <td><a class="link" href="order.php?id=<?= (int) $i['order_id'] ?>"><?= e(order_no((int) $i['order_id'])) ?></a><small class="block muted"><?= e($i['first_name'] . ' ' . $i['last_name']) ?><?= $i['promised_date'] ? ' · ' . e(date_tr($i['promised_date'])) : '' ?></small></td>
                <td><?= e((string) ($i['lens_type'] ?: $i['lens_value'])) ?></td>
                <td><small><?= e(cam_kalem_tarifi($i)) ?></small></td>
                <td class="hide-sm"><small><?= e(supplier_label($i['supplier_id'] ? (int) $i['supplier_id'] : null)) ?></small></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table></div>
          <div class="grid cols-3" style="align-items:end;margin-top:12px">
            <label class="field"><span>Tedarikçi</span><select name="supplier_id" required><option value="">Seçin</option><?php foreach ($tedarikciler as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Not (isteğe bağlı)</span><input name="not" maxlength="500" placeholder="örn. acil, yarın öğlene kadar"></label>
            <div class="form-actions"><button class="btn btn-primary"><?= icon('truck') ?> Seçilenlerle fiş oluştur</button></div>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </div>
  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2>Son fişler</h2></div>
      <?php if (!$fisler): ?><p class="hint">Henüz fiş yok.</p><?php endif; ?>
      <?php foreach ($fisler as $f): [$dAd, $dTon] = cam_fis_durumlari()[$f['durum']] ?? [$f['durum'], 'gray']; ?>
        <a class="kv-line" href="cam-siparis.php?id=<?= (int) $f['id'] ?>" style="display:block;text-decoration:none">
          <b>#<?= (int) $f['id'] ?> · <?= e((string) $f['tedarikci']) ?></b>
          <span class="muted small"><?= (int) $f['adet'] ?> cam · <?= e(date_tr($f['created_at'])) ?></span>
          <span class="badge tone-<?= e($dTon) ?>" style="float:right"><?= e($dAd) ?></span>
        </a>
      <?php endforeach; ?>
    </section>
  </aside>
</div>
<?php page_end();
