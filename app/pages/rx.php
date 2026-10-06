<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

$rxId = is_post() ? post_int('rx_id') : query_int('id');
$rx = $rxId ? row('SELECT * FROM prescription_records WHERE id = ?', [$rxId]) : null;
if ($rxId && !$rx) {
    render_error_page('Reçete bulunamadı', 'Silinmiş olabilir.');
}
$orderId = $rx ? (int) $rx['order_id'] : (is_post() ? post_int('order_id') : query_int('order_id'));
$order = find_order($orderId);
if (!$order) {
    render_error_page('Sipariş bulunamadı', 'Reçete bir siparişe bağlı olarak girilir.');
}
$near = $rx ? row('SELECT * FROM near_prescription_details WHERE prescription_id = ?', [$rx['id']]) : null;

if (is_post()) {
    [$data, $nearData, $errors] = rx_from_post();
    $stockOptions = array_keys(stock_statuses());
    $stock = [];
    foreach (['uzak', 'yakin'] as $g) {
        $v = post('stock_' . $g);
        if ($v !== '' && !in_array($v, $stockOptions, true)) {
            $errors[] = 'Stok durumu geçersiz.';
        }
        $stock[$g] = $v === '' ? ($rx ? null : 'stokta_var') : $v;
    }
    if ($errors) {
        remember_input();
        foreach (array_unique($errors) as $err) {
            flash($err, 'error');
        }
        redirect($rx ? 'rx.php?id=' . $rx['id'] : 'rx.php?order_id=' . $orderId);
    }

    $savedId = transaction(function () use ($rx, $order, $orderId, $data, $nearData, $stock, $user) {
        $data['customer_id'] = $order['customer_id'];
        if ($rx) {
            $data['updated_by'] = $user['id'];
            update('prescription_records', $data, 'id = ?', [$rx['id']]);
            $id = (int) $rx['id'];
            $changes = [];
            foreach (['right_sph', 'right_cyl', 'right_axis', 'right_add', 'left_sph', 'left_cyl', 'left_axis', 'left_add', 'lens_type', 'lens_design'] as $k) {
                if ((string) $rx[$k] !== (string) $data[$k]) {
                    $changes[$k] = ['önce' => $rx[$k], 'sonra' => $data[$k]];
                }
            }
            audit('rx_update', 'order', $orderId, ['reçete' => $id] + $changes);
        } else {
            $data['order_id'] = $orderId;
            $data['created_by'] = $user['id'];
            $id = insert('prescription_records', $data);
            audit('rx_create', 'order', $orderId, ['reçete' => $id, 'sağ' => rx_line($data, 'right'), 'sol' => rx_line($data, 'left'), 'cam' => $data['lens_type']]);
        }
        if ($nearData) {
            $exists = scalar('SELECT id FROM near_prescription_details WHERE prescription_id = ?', [$id]);
            $exists ? update('near_prescription_details', $nearData, 'prescription_id = ?', [$id])
                    : insert('near_prescription_details', $nearData + ['prescription_id' => $id]);
        } else {
            q('DELETE FROM near_prescription_details WHERE prescription_id = ?', [$id]);
        }
        sync_lens_items($id, $data, $nearData, $stock);
        if (($order['lens_type'] ?? '') === '' && $data['lens_type'] !== '') {
            q('UPDATE orders SET lens_type = ? WHERE id = ?', [$data['lens_type'], $orderId]);
        }
        return $id;
    });

    $missing = (int) scalar("SELECT COUNT(*) FROM prescription_lens_items WHERE prescription_id = ? AND stock_status = 'stokta_yok'", [$savedId]);

    /* SGK katkı payı: reçeteden tek akışla siparişe ön dolum. Tahminidir; sipariş sayfasından elle düzeltilebilir. */
    $sgk = sgk_katki_uygula($orderId, (int) $order['customer_id'], $data);
    if ($sgk['uyari']) {
        flash($sgk['uyari'], 'warn');
    }
    $sgkMesaj = !empty($sgk['korundu']) ? ' SGK payı teklifteki tutarda (' . money($sgk['tutar']) . ') bırakıldı.'
        : ($sgk['tutar'] > 0 ? ' SGK katkısı tahmini ' . money($sgk['tutar']) . ' olarak siparişe işlendi (kontrol edin).' : '');
    flash('Reçete kaydedildi.' . ($missing ? " $missing cam depo listesine eklendi." : '') . $sgkMesaj);
    redirect('order.php?id=' . $orderId);
}

/* ---------- Form değerleri ---------- */
$copyFrom = !$rx && query_int('kopya') ? row('SELECT * FROM prescription_records WHERE id = ? AND customer_id = ?', [query_int('kopya'), $order['customer_id']]) : null;
$source = $rx ?? $copyFrom ?? [];
$v = static function (string $key, string $default = '') use ($source): string {
    $o = old($key, "\0");
    if ($o !== "\0") {
        return $o;
    }
    return isset($source[$key]) && $source[$key] !== null ? (string) $source[$key] : $default;
};
$nv = static function (string $key) use ($near): string {
    $o = old('near_' . $key, "\0");
    return $o !== "\0" ? $o : (string) ($near[$key] ?? '');
};
$lastRx = !$rx ? row('SELECT id, prescription_date FROM prescription_records WHERE customer_id = ? ORDER BY prescription_date DESC, id DESC LIMIT 1', [$order['customer_id']]) : null;
$currentItems = $rx ? rows('SELECT * FROM prescription_lens_items WHERE prescription_id = ? ORDER BY lens_no', [$rx['id']]) : [];
$lensTypes = lens_type_options($v('lens_type'));
$design = $v('lens_design', 'tek_odak_uzak');
$age = $order['c_birth_year'] ? (int) date('Y') - (int) $order['c_birth_year'] : null;

$catalog = array_map(static fn($p) => [
    // 4.21.0: yakın destekli / miyopi kontrol camları asistanda tek odak sayılır; kaplama birden çok olabilir
    'id' => (int) $p['id'], 'brand' => $p['brand'], 'name' => $p['name'],
    'design' => in_array($p['design'], ['tek_odak_destekli', 'miyopi_kontrol'], true) ? 'tek_odak' : $p['design'], 'tier' => $p['tier'],
    'index' => $p['lens_index'], 'coating' => $p['coating'], 'coatings' => lens_kaplama_listesi($p['coating']), 'price' => $p['price'] !== null ? (float) $p['price'] : null, 'note' => $p['note'],
], rows('SELECT * FROM lens_products WHERE is_active = 1 ORDER BY design, tier, price'));
$advisorBoot = [
    'age'         => $age,
    'catalog'     => $catalog,
    'showPrices'  => can_see_amounts(),
    'productId'   => (int) $v('advisor_product_id'),
    'tiers'       => product_tiers(),
];

$title = $rx ? 'Reçeteyi düzenle' : 'Yeni reçete';
page_start($title, 'orders', ['body' => 'page-rx']);
page_header(
    $title,
    '<a href="order.php?id=' . $orderId . '">' . e($order['c_first'] . ' ' . $order['c_last']) . ' · ' . order_no($orderId) . '</a>' . ($age ? ' · ' . $age . ' yaş' : ''),
    $lastRx && !$copyFrom ? '<a class="btn btn-sm" href="rx.php?order_id=' . $orderId . '&kopya=' . (int) $lastRx['id'] . '">Son reçeteyi kopyala (' . date_tr($lastRx['prescription_date']) . ')</a>' : '',
    'order.php?id=' . $orderId,
    'Reçete kartı'
);
if ($copyFrom) {
    echo '<div class="alert alert-info">' . date_tr($copyFrom['prescription_date']) . ' tarihli reçete değerleri kopyalandı. Kontrol edip kaydedin.</div>';
}
?>
<div class="rx-layout">
<form method="post" class="rx-form" id="rx-form" data-guard novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="order_id" value="<?= $orderId ?>">
  <input type="hidden" name="rx_id" value="<?= $rx ? (int) $rx['id'] : '' ?>">
  <input type="hidden" name="advisor_summary" value="<?= e($v('advisor_summary')) ?>" data-advisor-summary>
  <input type="hidden" name="advisor_product_id" value="<?= e($v('advisor_product_id')) ?>" data-advisor-product>

  <section class="card">
    <div class="grid cols-4">
      <label class="field"><span>Reçete tarihi</span><input type="date" name="prescription_date" value="<?= e($rx ? $v('prescription_date') : old('prescription_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>" required></label>
      <label class="field span-2"><span>Kullanım şekli</span><select name="lens_design" data-design><?= select_options(lens_designs(), $design) ?></select></label>
      <label class="field"><span>Gözler</span><select name="lens_eyes" data-eyes><?= select_options(['both' => 'İki göz', 'right' => 'Sadece sağ', 'left' => 'Sadece sol'], $v('lens_eyes', 'both')) ?></select></label>
      <label class="field span-2"><span>Cam tipi</span><select name="lens_type" data-lens-type><option value="">Seçilmedi (depoya düşmez)</option><?= select_options($lensTypes, $v('lens_type', (string) $order['lens_type']), false) ?></select></label>
      <label class="field span-2"><span>Doktor / muayene yeri</span><input name="doctor" value="<?= e($v('doctor')) ?>" maxlength="120"></label>
    </div>
  </section>

  <section class="card">
    <div class="card-head">
      <h2>Reçete değerleri</h2>
      <div class="btn-row">
        <button type="button" class="btn btn-ghost btn-sm" data-transpose title="Hekim +CYL yazmışsa laboratuvar formatı olan −CYL'e çevirir (veya tersi).">↔ Transpoze et (CYL işaretini çevir)</button>
        <button type="button" class="btn btn-ghost btn-sm" data-copy-right>Sağı sola kopyala</button>
      </div>
    </div>
    <div class="table-wrap">
      <table class="rx-input">
        <thead><tr><th></th><th>SPH</th><th>CYL</th><th>AKS</th><th data-col-add>ADD</th><th>PD</th><th data-col-height>Yükseklik</th></tr></thead>
        <tbody>
          <?php foreach (['right' => ['Sağ', 'R'], 'left' => ['Sol', 'L']] as $side => [$label, $code]): ?>
            <tr data-eye-row="<?= $side ?>">
              <th><span class="eye-tag"><?= $code ?></span><?= $label ?></th>
              <td data-label="SPH"><input name="<?= $side ?>_sph" value="<?= e($v($side . '_sph')) ?>" inputmode="decimal" placeholder="0.00" data-diopter aria-label="<?= $label ?> SPH"></td>
              <td data-label="CYL"><input name="<?= $side ?>_cyl" value="<?= e($v($side . '_cyl')) ?>" inputmode="decimal" placeholder="0.00" data-diopter aria-label="<?= $label ?> CYL"></td>
              <td data-label="AKS"><input name="<?= $side ?>_axis" value="<?= e($v($side . '_axis')) ?>" inputmode="numeric" placeholder="°" maxlength="3" data-axis aria-label="<?= $label ?> aks"></td>
              <td data-col-add data-label="ADD"><input name="<?= $side ?>_add" value="<?= e($v($side . '_add')) ?>" inputmode="decimal" placeholder="+0.00" data-diopter data-add aria-label="<?= $label ?> ADD"></td>
              <td data-label="PD"><input name="<?= $side ?>_pd" value="<?= e($v($side . '_pd')) ?>" inputmode="decimal" placeholder="mm" aria-label="<?= $label ?> PD"></td>
              <td data-col-height data-label="Yükseklik"><input name="<?= $side ?>_height" value="<?= e($v($side . '_height')) ?>" inputmode="decimal" placeholder="mm" aria-label="<?= $label ?> montaj yüksekliği"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="grid cols-4 mt">
      <label class="field"><span>Toplam PD (mm)</span><input name="pd" value="<?= e($v('pd')) ?>" inputmode="decimal" placeholder="ör. 63"></label>
      <p class="hint span-3">Değerler 0.25 adımla kaydedilir; virgül veya nokta kullanabilirsiniz. CYL girilen gözde AKS zorunludur. Sıfır AKS 180 olarak kaydedilir.</p>
    </div>
  </section>

  <section class="card" data-near-section <?= in_array($design, ['ayri_uzak_yakin', 'tek_odak_yakin'], true) ? '' : 'hidden' ?>>
    <div class="card-head"><h2>Yakın gözlük</h2><small class="muted">SPH + ADD ile otomatik hesaplanır, gerekirse değiştirin</small></div>
    <div class="grid cols-3">
      <label class="field"><span>Yakın cam tipi</span><select name="near_lens_type"><option value="">Uzak ile aynı</option><?= select_options(lens_type_options($nv('lens_type')), $nv('lens_type'), false) ?></select></label>
      <label class="field"><span>Sağ yakın SPH</span><input name="near_right_sph" value="<?= e($nv('right_sph')) ?>" inputmode="decimal" data-near="right"></label>
      <label class="field"><span>Sol yakın SPH</span><input name="near_left_sph" value="<?= e($nv('left_sph')) ?>" inputmode="decimal" data-near="left"></label>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2>Cam stoğu</h2><a class="link" href="stock.php">Depo listesi</a></div>
    <div class="grid cols-2">
      <label class="field" data-stock-far><span>Uzak / çok odaklı camlar</span>
        <select name="stock_uzak">
          <?php if ($rx): ?><option value="">Mevcut durumu koru</option><?php endif; ?>
          <?= select_options(stock_statuses(), old('stock_uzak', $rx ? '' : 'stokta_var')) ?>
        </select></label>
      <label class="field" data-stock-near <?= in_array($design, ['ayri_uzak_yakin', 'tek_odak_yakin'], true) ? '' : 'hidden' ?>><span>Yakın camlar</span>
        <select name="stock_yakin">
          <?php if ($rx): ?><option value="">Mevcut durumu koru</option><?php endif; ?>
          <?= select_options(stock_statuses(), old('stock_yakin', $rx ? '' : 'stokta_var')) ?>
        </select></label>
    </div>
    <?php if ($currentItems): ?>
      <ul class="lens-items mt">
        <?php foreach ($currentItems as $it): ?>
          <li><span><?= e($it['lens_label']) ?></span><code><?= e($it['lens_value']) ?></code><?= stock_badge($it['stock_status']) ?></li>
        <?php endforeach; ?>
      </ul>
      <p class="hint">Numara veya cam tipi değişirse gelmemiş camlar yeniden “Eksik” olarak işaretlenir. Gelmiş camların durumu korunur.</p>
    <?php endif; ?>
    <label class="field mt"><span>Reçete notu</span><textarea name="prescription_note" rows="2"><?= e($v('prescription_note')) ?></textarea></label>
  </section>

  <div class="form-actions sticky-actions">
    <a class="btn btn-ghost" href="order.php?id=<?= $orderId ?>">Vazgeç</a>
    <button class="btn btn-primary btn-lg"><?= icon('check') ?> Reçeteyi kaydet</button>
  </div>
</form>

<aside class="advisor" id="advisor" aria-label="Optik öneri asistanı">
  <div class="advisor-head">
    <span class="advisor-ic"><?= icon('spark') ?></span>
    <div><b>Öneri asistanı</b><small>Reçete ve kullanım alışkanlığına göre cam önerisi</small></div>
  </div>
  <div class="advisor-inputs grid cols-2">
    <label class="field"><span>Yaş</span><input type="number" min="3" max="100" data-a="age" value="<?= $age ?? '' ?>"></label>
    <label class="field"><span>Bütçe</span><select data-a="budget"><option value="ekonomik">Ekonomik</option><option value="dengeli" selected>Dengeli</option><option value="premium">Premium</option></select></label>
    <label class="field"><span>Günlük ekran (saat)</span><input type="number" min="0" max="16" value="0" data-a="screen"></label>
    <label class="field"><span>Çerçeve tipi</span><select data-a="frame"><option value="kapali">Kapalı (metal/asetat)</option><option value="nylor">Misinalı (nylor)</option><option value="vidali">Vidalı (çerçevesiz)</option><option value="spor">Spor / sargılı</option></select></label>
    <label class="field"><span>Cam genişliği A (mm)</span><input type="number" min="38" max="70" value="52" data-a="a"></label>
    <label class="field"><span>Köprü DBL (mm)</span><input type="number" min="10" max="26" value="18" data-a="dbl"></label>
    <fieldset class="field span-all checks">
      <legend>Kullanım</legend>
      <label><input type="checkbox" data-a="drive"> Gece sürüşü</label>
      <label><input type="checkbox" data-a="outdoor"> Açık hava / güneş</label>
      <label><input type="checkbox" data-a="sport"> Spor / çocuk (darbe)</label>
      <label><input type="checkbox" data-a="desk"> Masa başı / okuma</label>
      <label><input type="checkbox" data-a="firstprog"> İlk kez progressive</label>
    </fieldset>
  </div>
  <div class="advisor-out" data-advisor-out aria-live="polite">
    <p class="muted">Reçete değerlerini girdikçe öneri burada oluşur.</p>
  </div>
  <label class="advisor-attach"><input type="checkbox" data-advisor-attach <?= $v('advisor_summary') !== '' ? 'checked' : '' ?>> Öneri özetini reçeteye kaydet</label>
  <script type="application/json" id="advisor-data"><?= json_encode($advisorBoot, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</aside>
</div>
<?php page_end(['rx.js']);
