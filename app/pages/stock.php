<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

/* 4.37.0 — "Cam siparişleri" (cam-siparis.php) bu sayfayla birleşti: eksik camlar seçilip tedarikçiye sipariş fişi
   buradan oluşturulur (fiş ekranı: cam-siparis.php?id=…), fişler "Fişler" sekmesinde. Tek sayfa: eksik → fiş →
   siparişte → geldi (tüm camları gelen sipariş atölyeye). */
$tabs = [
    'eksik'   => ['Eksik', "i.stock_status = 'stokta_yok'"],
    'siparis' => ['Siparişte', "i.stock_status = 'siparis_verildi'"],
    'gelen'   => ['Gelenler (14 gün)', "i.stock_status = 'stokta_var' AND i.arrived_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)"],
    'fisler'  => ['Sipariş fişleri', ''],
];

/* Eksik camlardan tedarikçiye sipariş fişi (app/tedarik.php) → fiş ekranında yazdır / WhatsApp / e-posta → "Gönderildi" */
if (is_post() && post('eylem') === 'fis') {
    csrf_check();
    try {
        $fid = cam_fis_olustur(post_int('supplier_id'), (array) ($_POST['items'] ?? []), '');
        flash('Sipariş fişi hazır. Gönderin, sonra "Gönderildi" deyin; camlar "Siparişte" sekmesine geçer.');
        redirect('cam-siparis.php?id=' . $fid);
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
        redirect('stock.php?tab=eksik');
    }
}

/* 4.32.0 kâğıtsız tezgâh: gün sonu eksik camlar tek tuşla depoya (WhatsApp), son gönderimi geri al */
if (is_post() && in_array(post('eylem'), ['depo_whatsapp', 'depo_geri'], true)) {   // 4.38.1: her mağazada (Eksik sekmesindeki kart)
    if (post('eylem') === 'depo_geri') {
        $n = depo_geri_al();
        flash($n > 0 ? $n . ' cam yeniden "Eksik" listesine alındı.' : 'Geri alınacak gönderim yok.');
        redirect(post('geri') === 'ana' ? 'index.php' : 'stock.php');
    }
    $tedarikciSecim = post_int('supplier_id') ?: null;
    if ($tedarikciSecim) {
        setting_set('depo_tedarikci_id', (string) $tedarikciSecim);   // bir dahaki sefere de bu depo
    }
    $sonuc = depo_gonder($tedarikciSecim);
    if ($sonuc['adet'] === 0) {
        flash('Depoya gidecek eksik cam yok.');
        redirect(post('geri') === 'stok' ? 'stock.php' : 'index.php');
    }
    header('Location: ' . $sonuc['url'], true, 303);   // WhatsApp (yeni sekmede açılır; ana sekme kendini yeniler)
    exit;
}

if (is_post()) {
    $ids = array_values(array_filter(array_map('intval', (array) ($_POST['items'] ?? []))));
    $to = post('to');
    $supplierId = post_int('supplier_id') ?: null;
    $back = 'stock.php?' . http_build_query(array_filter(['tab' => post('tab'), 'lens' => post('lens'), 'from' => post('from'), 'until' => post('until'), 'supplier' => post('supplier')]));
    if (!$ids || !in_array($to, ['stokta_yok', 'siparis_verildi', 'stokta_var'], true)) {
        flash('İşlem için en az bir cam seçin.', 'error');
        redirect($back);
    }
    $in = in_placeholders($ids);
    $orderIds = array_column(rows("SELECT DISTINCT r.order_id FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE i.id IN ($in)", $ids), 'order_id');
    $deliveryMsg = '';
    transaction(function () use ($ids, $in, $to, $supplierId, &$deliveryMsg) {
        $set = match ($to) {
            'stokta_var'      => "stock_status = 'stokta_var', arrived_at = NOW()",
            'siparis_verildi' => "stock_status = 'siparis_verildi', ordered_at = NOW(), arrived_at = NULL",
            default           => "stock_status = 'stokta_yok', ordered_at = NULL, arrived_at = NULL",
        };
        $params = $ids;
        if ($to === 'siparis_verildi' && $supplierId) {
            $set .= ', supplier_id = ?';
            $params = array_merge([$supplierId], $ids);
        }
        q("UPDATE prescription_lens_items SET $set WHERE id IN ($in)", $params);

        // Camlar "geldi" işaretlendiğinde: tedarikçisi belliyse otomatik bir teslimat
        // (alışveriş) kaydı oluştur; fatura girilene kadar tedarikçi sayfasında bekler.
        if ($to === 'stokta_var') {
            $bySupplier = rows("SELECT supplier_id, COUNT(*) AS cnt FROM prescription_lens_items WHERE id IN ($in) AND supplier_id IS NOT NULL GROUP BY supplier_id", $ids);
            $names = [];
            foreach ($bySupplier as $g) {
                $sid = (int) $g['supplier_id'];
                $did = insert('supplier_deliveries', ['supplier_id' => $sid, 'item_count' => (int) $g['cnt'], 'created_by' => current_user()['id'] ?? null]);
                q("UPDATE prescription_lens_items SET delivery_id = ? WHERE id IN ($in) AND supplier_id = ? AND delivery_id IS NULL", array_merge([$did], $ids, [$sid]));
                $names[] = (int) $g['cnt'] . ' cam · ' . supplier_label($sid);
            }
            if ($names) {
                $deliveryMsg = ' Tedarikçi sayfasında fatura girmenizi bekleyen yeni teslimat: ' . implode(', ', $names) . '.';
            }
        }
    });
    foreach ($orderIds as $oid) {
        audit('stock_update', 'order', (int) $oid, ['durum' => stock_statuses()[$to][0], 'adet' => count($ids)]);
    }
    if (function_exists('cam_fisleri_tamamla') && column_exists('prescription_lens_items', 'cam_siparis_id')) {
        cam_fisleri_tamamla();   // 4.12.0 tüm camları gelen cam sipariş fişleri "tamamlandı"
    }
    $msg = count($ids) . ' cam “' . stock_statuses()[$to][0] . '” olarak işaretlendi.';
    if ($to === 'stokta_var') {
        $moved = advance_orders_if_lenses_ready($orderIds);
        if ($moved) {
            $msg .= ' Tüm camları tamamlanan ' . count($moved) . ' sipariş “Atölyede” aşamasına alındı: ' . implode(', ', array_map(static fn($i) => order_no((int) $i), $moved)) . '.';
        }
        $msg .= $deliveryMsg;
    }
    flash($msg);
    redirect($back);
}

$tab = query('tab', 'eksik');
if (!isset($tabs[$tab])) {
    $tab = 'eksik';
}
$lens = query('lens');
$from = query('from');
$until = query('until');
$supplierFilter = query_int('supplier');
$where = [$tab === 'fisler' ? '1 = 0' : $tabs[$tab][1], "o.order_stage <> 'iptal'"];
$params = [];
if ($lens !== '') { $where[] = 'i.lens_type = ?'; $params[] = $lens; }
if ($from !== '' && valid_date($from)) { $where[] = 'i.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($until !== '' && valid_date($until)) { $where[] = 'i.created_at < ?'; $params[] = date('Y-m-d', strtotime($until . ' +1 day')) . ' 00:00:00'; }
if ($supplierFilter && $tab !== 'eksik') { $where[] = 'i.supplier_id = ?'; $params[] = $supplierFilter; }
$whereSql = implode(' AND ', $where) . sube_kosul('o');   // 4.49.0 şube: camın siparişi bu şubede

$items = rows(
    "SELECT i.*, r.order_id, c.first_name, c.last_name, o.order_stage, o.promised_date, sup.name AS supplier_name, cs.durum AS fis_durum
     FROM prescription_lens_items i
     JOIN prescription_records r ON r.id = i.prescription_id
     JOIN orders o ON o.id = r.order_id
     JOIN customers c ON c.id = o.customer_id
     LEFT JOIN suppliers sup ON sup.id = i.supplier_id
     LEFT JOIN cam_siparisleri cs ON cs.id = i.cam_siparis_id
     WHERE $whereSql
     ORDER BY i.lens_type, i.sph, i.cyl, i.created_at
     LIMIT 500",
    $params
);
$counts = [];
foreach ($tabs as $k => [$label, $cond]) {
    if ($k === 'fisler') {
        $counts[$k] = (int) scalar("SELECT COUNT(*) FROM cam_siparisleri WHERE durum IN ('taslak', 'gonderildi')");   // açık fişler
        continue;
    }
    $counts[$k] = (int) scalar("SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id JOIN orders o ON o.id = r.order_id WHERE $cond AND o.order_stage <> 'iptal'" . sube_kosul('o'));
}
$fisler = $tab === 'fisler' ? rows(
    "SELECT c.*, s.name AS tedarikci, (SELECT COUNT(*) FROM prescription_lens_items i WHERE i.cam_siparis_id = c.id) AS adet
       FROM cam_siparisleri c LEFT JOIN suppliers s ON s.id = c.supplier_id
      WHERE c.durum <> 'iptal' ORDER BY c.id DESC LIMIT 100"
) : [];
$lensFilter = array_column(rows("SELECT DISTINCT lens_type FROM prescription_lens_items WHERE lens_type <> '' ORDER BY lens_type"), 'lens_type');
$suppliers = supplier_options();

// Depo özeti: aynı cam + aynı numara gruplanır.
$groups = [];
foreach ($items as $it) {
    $key = $it['lens_type'] . '|' . $it['sph'] . '|' . $it['cyl'] . '|' . $it['axis'] . '|' . $it['add_power'];
    $groups[$key] ??= ['lens_type' => $it['lens_type'], 'value' => $it['lens_value'], 'count' => 0];
    $groups[$key]['count']++;
}
$printQuery = http_build_query(array_filter(['type' => 'depot', 'tab' => $tab, 'lens' => $lens, 'from' => $from, 'until' => $until, 'supplier' => $supplierFilter ?: null]));
$printLabel = $supplierFilter ? supplier_label($supplierFilter) . ' için PDF' : 'Tedarikçi listesi (PDF)';

page_start('Cam siparişleri · depo', 'stock');
page_header(
    'Cam siparişleri · depo',
    'Eksik camları seçip tedarikçiye sipariş fişi gönderin; gelince "Geldi" deyin. Tüm camları gelen sipariş kendiliğinden atölyeye geçer.',
    $items && in_array($tab, ['eksik', 'siparis'], true) ? '<a class="btn" href="print.php?' . e($printQuery) . '" target="_blank">' . icon('print') . ' ' . e($printLabel) . '</a>' : '',
    '',
    'Cam takibi'
);
?>
<nav class="tabs" aria-label="Stok durumları">
  <?php foreach ($tabs as $k => [$label]): ?>
    <a class="tab <?= $tab === $k ? 'active' : '' ?>" href="<?= e(url_with(['tab' => $k])) ?>"><?= e($label) ?> <em><?= $counts[$k] ?></em></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'eksik'):   // 4.38.1: gün sonu tek tuş depoya WhatsApp (fiş olarak kaydedilir)
    $depoGruplar = depo_gruplari(depo_eksik_kalemler());
    $depoToplam = array_sum(array_column($depoGruplar, 'adet'));
    $depoTedarikci = depo_tedarikci();
    $depoTedarikciler = !$depoTedarikci && $depoToplam ? rows('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name') : [];
    $depoSon = depo_son_gonderim();
    $depoGeri = 'stok';
    require dirname(__DIR__) . '/partials/depo-karti.php';
endif; ?>
<?php if ($tab === 'fisler'): ?>
<section class="card">
  <?php if (!$fisler): ?>
    <?= empty_state('Henüz sipariş fişi yok', '“Eksik” sekmesinde camları seçip tedarikçiyle “Sipariş fişi oluştur”a basın.') ?>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Fiş</th><th>Tedarikçi</th><th class="num">Cam</th><th class="hide-sm">Tarih</th><th>Durum</th></tr></thead>
      <tbody>
        <?php foreach ($fisler as $fs): [$dAd, $dTon] = cam_fis_durumlari()[$fs['durum']] ?? [$fs['durum'], 'gray']; ?>
          <tr>
            <td><a class="link" href="cam-siparis.php?id=<?= (int) $fs['id'] ?>"><b>#<?= (int) $fs['id'] ?></b></a></td>
            <td><?= e((string) $fs['tedarikci'] ?: ($fs['kanal'] === 'whatsapp' ? 'WhatsApp (kişi seçildi)' : '—')) ?><?= $fs['kanal'] === 'whatsapp' ? ' <small class="muted">gün sonu</small>' : '' ?></td>
            <td class="num"><?= (int) $fs['adet'] ?></td>
            <td class="hide-sm"><?= e(date_tr($fs['created_at'])) ?></td>
            <td><span class="badge tone-<?= e($dTon) ?>"><?= e($dAd) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php page_end(); exit; endif; ?>

<section class="card">
  <form method="get" class="filters">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <label class="field"><span>Cam tipi</span><select name="lens"><option value="">Tümü</option><?= select_options($lensFilter, $lens, false) ?></select></label>
    <?php if ($tab !== 'eksik' && $suppliers): ?>
      <label class="field"><span>Tedarikçi</span><select name="supplier"><option value="">Tümü</option><?= select_options(array_column($suppliers, 'name', 'id'), $supplierFilter ? (string) $supplierFilter : null) ?></select></label>
    <?php endif; ?>
    <label class="field"><span>Başlangıç</span><input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="field"><span>Bitiş</span><input type="date" name="until" value="<?= e($until) ?>"></label>
    <div class="filter-actions"><button class="btn">Filtrele</button><?php if ($lens || $from || $until || $supplierFilter): ?><a class="btn btn-ghost" href="stock.php?tab=<?= e($tab) ?>">Temizle</a><?php endif; ?></div>
  </form>

  <?php if (!$items): ?>
    <?= empty_state($tab === 'eksik' ? 'Eksik cam yok' : 'Kayıt yok', $tab === 'eksik' ? 'Reçetede “Eksik” seçilen camlar burada listelenir.' : '') ?>
  <?php else: ?>
  <form method="post" data-bulk>
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($tab) ?>"><input type="hidden" name="lens" value="<?= e($lens) ?>">
    <input type="hidden" name="from" value="<?= e($from) ?>"><input type="hidden" name="until" value="<?= e($until) ?>">
    <input type="hidden" name="supplier" value="<?= e((string) $supplierFilter) ?>">
    <div class="bulk-bar" data-bulk-bar>
      <label class="check"><input type="checkbox" data-check-all> <span data-selected-count>0 seçili</span></label>
      <div class="bulk-actions">
        <?php if ($tab === 'eksik'): ?>
          <?php if ($suppliers): ?>
            <select name="supplier_id" class="bulk-supplier" aria-label="Tedarikçi">
              <option value="">Tedarikçi seçin</option>
              <?= select_options(array_column($suppliers, 'name', 'id'), null) ?>
            </select>
            <button class="btn btn-sm" name="eylem" value="fis" disabled data-needs-selection><?= icon('truck') ?> Sipariş fişi oluştur</button>
          <?php else: ?>
            <button class="btn btn-sm" name="to" value="siparis_verildi" disabled data-needs-selection>Sipariş verildi</button>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($tab !== 'gelen'): ?><button class="btn btn-primary btn-sm" name="to" value="stokta_var" disabled data-needs-selection><?= icon('check') ?> Geldi</button><?php endif; ?>
        <?php if ($tab !== 'eksik'): ?><button class="btn btn-ghost btn-sm" name="to" value="stokta_yok" disabled data-needs-selection>Eksik’e geri al</button><?php endif; ?>
      </div>
    </div>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th class="w-check"></th><th>Cam</th><th>Numara</th><th class="hide-sm">Müşteri / sipariş</th><?php if ($tab !== 'eksik'): ?><th class="hide-md">Tedarikçi</th><?php endif; ?><th class="hide-md"><?= $tab === 'gelen' ? 'Geldi' : ($tab === 'siparis' ? 'Sipariş' : 'Eklendi') ?></th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td class="w-check"><input type="checkbox" name="items[]" value="<?= (int) $it['id'] ?>" aria-label="Seç"></td>
              <td><b><?= e($it['lens_type']) ?></b><small class="block muted"><?= e(['uzak' => 'Uzak', 'yakin' => 'Yakın', 'cok_odak' => 'Çok odak'][$it['item_group']] ?? '') ?> · <?= $it['eye'] === 'R' ? 'Sağ' : 'Sol' ?><?php if (!empty($it['cam_siparis_id'])): ?> · <a class="link" href="cam-siparis.php?id=<?= (int) $it['cam_siparis_id'] ?>">fiş #<?= (int) $it['cam_siparis_id'] ?><?= $it['fis_durum'] === 'taslak' ? ' (gönderilmedi)' : '' ?></a><?php endif; ?></small></td>
              <td><code class="rx-code"><?= e($it['lens_value']) ?></code></td>
              <td class="hide-sm"><a class="link" href="order.php?id=<?= (int) $it['order_id'] ?>"><?= e($it['first_name'] . ' ' . $it['last_name']) ?></a><small class="block muted"><?= order_no((int) $it['order_id']) ?> · <?= e(stage_label($it['order_stage'])) ?></small></td>
              <?php if ($tab !== 'eksik'): ?><td class="hide-md"><?= $it['supplier_name'] ? e($it['supplier_name']) : '<span class="muted">—</span>' ?></td><?php endif; ?>
              <td class="hide-md"><?= date_tr($tab === 'gelen' ? $it['arrived_at'] : ($tab === 'siparis' ? $it['ordered_at'] : $it['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </form>
  <?php endif; ?>
</section>

<?php if ($groups && $tab === 'siparis'): ?>
<section class="card">
  <div class="card-head"><h2>Depo özeti</h2><small class="muted">Aynı cam ve numara birleştirildi · müşteri bilgisi içermez</small></div>
  <div class="table-wrap"><table class="table compact">
    <thead><tr><th>Cam tipi</th><th>Numara</th><th class="num">Adet</th></tr></thead>
    <tbody><?php foreach ($groups as $g): ?><tr><td><?= e($g['lens_type']) ?></td><td><code class="rx-code"><?= e($g['value']) ?></code></td><td class="num"><b><?= $g['count'] ?></b></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr><th colspan="2">Toplam</th><th class="num"><?= count($items) ?></th></tr></tfoot>
  </table></div>
</section>
<?php endif; ?>
<?php page_end();
