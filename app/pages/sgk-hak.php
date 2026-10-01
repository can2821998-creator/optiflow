<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.15.0 — SGK hak kontrolü
   • ?gelen=ID : OptiFlow Pro "Aktar" ile gelen Medula / e-Devlet hak ekranı
   • yapıştır  : web'de (Lite dahil) ekran metnini yapıştırma
   Okunan sonuç bir müşteriye (ve isterseniz siparişe) bağlanır.
   ========================================================================== */

$me = require_login();
ozellik_gereksin('sgk_hak');

if (is_post()) {
    $eylem = post('eylem');
    try {
        if ($eylem === 'yapistir') {
            $metin = mb_substr((string) ($_POST['metin'] ?? ''), 0, 100000);
            if (trim($metin) === '') {
                throw new DomainException('Ekran metnini yapıştırın.');
            }
            $_SESSION['sgk_hak_metin'] = $metin;
            redirect('sgk-hak.php?yapistir=1');
        }
        if ($eylem === 'kaydet') {
            $gelenId = post_int('gelen_id');
            $metin = $gelenId > 0
                ? (string) scalar('SELECT raw_text FROM sgk_incoming WHERE id = ? AND user_id = ?', [$gelenId, (int) $me['id']])
                : (string) ($_SESSION['sgk_hak_metin'] ?? '');
            if (trim($metin) === '') {
                throw new DomainException('Okunan ekran bulunamadı; yeniden aktarın ya da yapıştırın.');
            }
            $c = sgk_hak_coz($metin);
            $mid = post_int('customer_id');
            if ($mid <= 0) {
                throw new DomainException('Müşteriyi seçin.');
            }
            $sid = post_int('order_id') ?: null;
            if (post('son_alim') !== '') {   // personel okunan tarihi düzeltti
                $sa = post('son_alim');
                if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $sa, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || $sa > date('Y-m-d')) {
                    throw new DomainException('Son alım tarihi geçersiz.');
                }
                $c['son_alim'] = $sa;
            }
            sgk_hak_kaydet($c, $gelenId > 0 ? 'masaustu' : 'yapistir', $mid, $sid, $gelenId ?: null);
            if ($gelenId > 0) {
                q('UPDATE sgk_incoming SET used_at = ? WHERE id = ? AND user_id = ?', [date('Y-m-d H:i:s'), $gelenId, (int) $me['id']]);
            }
            unset($_SESSION['sgk_hak_metin']);
            audit('sgk_hak', 'customer', $mid, ['kaynak' => $gelenId > 0 ? 'masaüstü' : 'yapıştırma', 'son alım' => $c['son_alim'] ?: '—']);
            $d = sgk_hak_durumu($mid, (int) $sid);
            flash('Kaydedildi. ' . $d['mesaj'], $d['durum'] === 'yok' ? 'warn' : 'ok');
            redirect($sid ? 'order.php?id=' . $sid . '#sgk-hak' : 'customer.php?id=' . $mid);
        }
        if ($eylem === 'ayar' && is_super()) {
            setting_set('sgk_hak_ay', (string) max(1, min(120, post_int('ay') ?: 24)));
            setting_set('sgk_hak_cocuk_ay', (string) max(1, min(120, post_int('cocuk_ay') ?: 12)));
            setting_set('sgk_hak_cocuk_yas', (string) max(0, min(25, post_int('cocuk_yas'))));
            audit('settings_update', 'settings', null, ['bölüm' => 'SGK hak süreleri']);
            flash('Hak süreleri kaydedildi.');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('sgk-hak.php' . (post_int('gelen_id') > 0 ? '?gelen=' . post_int('gelen_id') : (isset($_SESSION['sgk_hak_metin']) ? '?yapistir=1' : '')));
}

$gelenId = query_int('gelen');
$metin = '';
if ($gelenId > 0) {
    $metin = (string) scalar('SELECT raw_text FROM sgk_incoming WHERE id = ? AND user_id = ?', [$gelenId, (int) $me['id']]);
    if (trim($metin) === '') {
        flash('Aktarılan kayıt bulunamadı.', 'error');
        redirect('sgk-hak.php');
    }
} elseif (query('yapistir') === '1') {
    $metin = (string) ($_SESSION['sgk_hak_metin'] ?? '');
}

page_start('SGK hak kontrolü', 'sgk-hak');
page_header('SGK hak kontrolü', 'Medula / e-Devlet hak ekranından son alım tarihini okuyup müşteriye bağlar; siparişte hak durumu buna göre gösterilir.', '', '', 'SGK');

if (trim($metin) !== ''):
    $c = sgk_hak_coz($metin);
    $adaylar = sgk_musteri_bul($c['ad'], $c['soyad']);
    ?>
    <section class="card">
      <div class="card-head"><h2>Okunan bilgi</h2><span class="badge tone-<?= $c['hak'] === 'var' ? 'green' : ($c['hak'] === 'yok' ? 'red' : 'gray') ?>"><?= e(['var' => 'Hak var', 'yok' => 'Hak yok', 'belirsiz' => 'Hak ifadesi yok'][$c['hak']]) ?></span></div>
      <ul class="kv">
        <li><span>Hasta</span><b><?= e($c['hasta'] ?: '—') ?></b></li>
        <li><span>Son alım</span><b><?= $c['son_alim'] ? e(date_tr($c['son_alim'])) : '<span class="text-danger">bulunamadı</span>' ?></b></li>
        <?php if ($c['sonraki_hak']): ?><li><span>Sonraki hak</span><b><?= e(date_tr($c['sonraki_hak'])) ?></b></li><?php endif; ?>
      </ul>
      <?php if ($c['satirlar']): ?>
        <details><summary class="linkish">Tarih bulunan satırlar (<?= count($c['satirlar']) ?>)</summary>
          <div class="table-wrap"><table class="table"><tbody>
            <?php foreach ($c['satirlar'] as $s): ?><tr><td><b><?= e(date_tr($s['tarih'])) ?></b></td><td><small><?= e($s['metin']) ?></small></td></tr><?php endforeach; ?>
          </tbody></table></div></details>
      <?php elseif (!sgk_hak_metni_mi($metin)): ?>
        <div class="alert alert-warn">Bu ekran bir hak sorgu / cam-çerçeve geçmişi ekranına benzemiyor. Medula'da hastanın hak (geçmiş alım) ekranını açıp yeniden aktarın.</div>
      <?php endif; ?>
      <form method="post" class="stack" style="gap:10px;margin-top:12px">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="kaydet"><input type="hidden" name="gelen_id" value="<?= (int) $gelenId ?>">
        <label class="field" style="max-width:240px"><span>Son alım tarihi (düzeltebilirsiniz)</span><input type="date" name="son_alim" value="<?= e((string) $c['son_alim']) ?>" max="<?= date('Y-m-d') ?>"></label>
        <?php if ($adaylar): ?>
          <fieldset class="stack" style="gap:6px;border:0;padding:0;margin:0">
            <legend class="muted small">Müşteri</legend>
            <?php foreach ($adaylar as $i => $a): ?>
              <label class="check"><input type="radio" name="customer_id" value="<?= (int) $a['id'] ?>" <?= $i === 0 ? 'checked' : '' ?>> <?= e($a['first_name'] . ' ' . $a['last_name']) ?><?= $a['phone'] ? ' · ' . e(phone_display((string) $a['phone'])) : '' ?></label>
              <?php foreach ($a['siparisler'] as $o): ?>
                <label class="check" style="margin-left:26px"><input type="radio" name="order_id" value="<?= (int) $o['id'] ?>"> <?= e(order_no((int) $o['id'])) ?> · <?= e(stage_label((string) $o['order_stage'])) ?> · <?= e(date_tr((string) $o['created_at'])) ?></label>
              <?php endforeach; ?>
            <?php endforeach; ?>
            <label class="check" style="margin-left:26px"><input type="radio" name="order_id" value="" checked> Siparişe bağlama</label>
          </fieldset>
        <?php else: ?>
          <label class="field" style="max-width:240px"><span>Müşteri no (müşteri kartı adresindeki id)</span><input name="customer_id" inputmode="numeric" required></label>
          <p class="hint">Hasta adıyla eşleşen müşteri bulunamadı. Müşteri kartını açıp adres çubuğundaki <code>id=</code> değerini yazın ya da müşteriyi önce ekleyin.</p>
        <?php endif; ?>
        <div><button class="btn btn-primary"><?= icon('check') ?> Kaydet</button></div>
      </form>
    </section>
<?php endif; ?>

<section class="card">
  <div class="card-head"><h2>Ekranı yapıştır</h2></div>
  <form method="post" class="stack" style="gap:8px">
    <?= csrf_field() ?><input type="hidden" name="eylem" value="yapistir">
    <textarea name="metin" rows="5" maxlength="100000" placeholder="Medula hak sorgulama / cam-çerçeve geçmişi ekranını ya da e-Devlet 'Medula Optik Cam ve Çerçeve Bilgisi Sorgulama' sonucunu seçip kopyalayın, buraya yapıştırın" required></textarea>
    <div><button class="btn"><?= icon('search') ?> Oku</button></div>
  </form>
  <p class="hint">OptiFlow Pro'da: Medula'da hak ekranını açın ve araç çubuğundaki <b>Aktar</b>'a basın; ekran kendiliğinden buraya gelir.</p>
</section>

<?php
$son = rows('SELECT h.*, c.first_name, c.last_name FROM sgk_hak_sorgulari h LEFT JOIN customers c ON c.id = h.customer_id ORDER BY h.id DESC LIMIT 25');
if ($son): ?>
<section class="card">
  <div class="card-head"><h2>Son sorgular</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Müşteri</th><th>Son alım</th><th>Hak</th><th class="hide-sm">Kaynak</th></tr></thead>
    <tbody><?php foreach ($son as $h): ?>
      <tr>
        <td><?php if ($h['customer_id']): ?><a class="link" href="customer.php?id=<?= (int) $h['customer_id'] ?>"><?= e(trim($h['first_name'] . ' ' . $h['last_name'])) ?></a><?php else: ?><?= e((string) ($h['ad'] ?: '—')) ?><?php endif; ?><?= $h['order_id'] ? ' · <a class="link" href="order.php?id=' . (int) $h['order_id'] . '#sgk-hak">' . e(order_no((int) $h['order_id'])) . '</a>' : '' ?></td>
        <td><?= $h['son_alim'] ? e(date_tr((string) $h['son_alim'])) : '—' ?></td>
        <td><?= e(['var' => 'Var', 'yok' => 'Yok', 'belirsiz' => '—'][$h['hak']] ?? '—') ?></td>
        <td class="hide-sm"><small><?= e(['masaustu' => 'OptiFlow Pro', 'yapistir' => 'Yapıştırma', 'elle' => 'Elle'][$h['kaynak']] ?? '') ?> · <?= e(date_tr((string) $h['created_at'], true)) ?></small></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>

<?php if (is_super()): $a = sgk_hak_ayarlari(); ?>
<section class="card">
  <div class="card-head"><h2>Hak süreleri</h2></div>
  <form method="post" class="grid cols-3" style="align-items:end">
    <?= csrf_field() ?><input type="hidden" name="eylem" value="ayar">
    <label class="field"><span>Yetişkin (ay)</span><input type="number" name="ay" min="1" max="120" value="<?= (int) $a['ay'] ?>"></label>
    <label class="field"><span>Çocuk (ay)</span><input type="number" name="cocuk_ay" min="1" max="120" value="<?= (int) $a['cocuk_ay'] ?>"></label>
    <label class="field"><span>Çocuk sayılan yaş (altı)</span><input type="number" name="cocuk_yas" min="0" max="25" value="<?= (int) $a['cocuk_yas'] ?>"></label>
    <div class="form-actions"><button class="btn btn-sm">Kaydet</button></div>
  </form>
  <p class="hint">Varsayılan: yetişkinde 2 yıl, 14 yaş altında 1 yıl. Numara 0,50 D ve üzeri değiştiyse doktor raporuyla erken yenilenebilir. Güncel kuralı SGK sözleşmenizden teyit edin. Yaş, müşteri kartındaki doğum yılından hesaplanır.</p>
</section>
<?php endif; ?>
<?php page_end();
