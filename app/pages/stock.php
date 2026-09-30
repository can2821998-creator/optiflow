<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$tabs = [
    'eksik'   => ['Eksik camlar', "i.stock_status = 'stokta_yok'"],
    'siparis' => ['Depoya sipariş verildi', "i.stock_status = 'siparis_verildi'"],
    'gelen'   => ['Son gelenler (14 gün)', "i.stock_status = 'stokta_var' AND i.arrived_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)"],
];

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
$where = [$tabs[$tab][1], "o.order_stage <> 'iptal'"];
$params = [];
if ($lens !== '') { $where[] = 'i.lens_type = ?'; $params[] = $lens; }
if ($from !== '' && valid_date($from)) { $where[] = 'i.created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($until !== '' && valid_date($until)) { $where[] = 'i.created_at < ?'; $params[] = date('Y-m-d', strtotime($until . ' +1 day')) . ' 00:00:00'; }
if ($supplierFilter && $tab !== 'eksik') { $where[] = 'i.supplier_id = ?'; $params[] = $supplierFilter; }
$whereSql = implode(' AND ', $where);

$items = rows(
    "SELECT i.*, r.order_id, c.first_name, c.last_name, o.order_stage, o.promised_date, sup.name AS supplier_name
     FROM prescription_lens_items i
     JOIN prescription_records r ON r.id = i.prescription_id
     JOIN orders o ON o.id = r.order_id
     JOIN customers c ON c.id = o.customer_id
     LEFT JOIN suppliers sup ON sup.id = i.supplier_id
     WHERE $whereSql
     ORDER BY i.lens_type, i.sph, i.cyl, i.created_at
     LIMIT 500",
    $params
);
$counts = [];
foreach ($tabs as $k => [$label, $cond]) {
    $counts[$k] = (int) scalar("SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id JOIN orders o ON o.id = r.order_id WHERE $cond AND o.order_stage <> 'iptal'");
}
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

page_start('Depo ve stok', 'stock');
page_header(
    'Depo / Stok',
    'Reçeteden gelen cam satırları. Seçip toplu işaretleyin; tüm camları gelen siparişler otomatik atölyeye alınır.',
    $items && $tab !== 'gelen' ? '<a class="btn" href="print.php?' . e($printQuery) . '" target="_blank">' . icon('print') . ' ' . e($printLabel) . '</a>' : '',
    '',
    'Cam takibi'
);
?>
<nav class="tabs" aria-label="Stok durumları">
  <?php foreach ($tabs as $k => [$label]): ?>
    <a class="tab <?= $tab === $k ? 'active' : '' ?>" href="<?= e(url_with(['tab' => $k])) ?>"><?= e($label) ?> <em><?= $counts[$k] ?></em></a>
  <?php endforeach; ?>
</nav>

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
        <?php if ($tab !== 'siparis'): ?>
          <?php if ($suppliers): ?>
            <select name="supplier_id" class="bulk-supplier" aria-label="Tedarikçi">
              <option value="">Tedarikçi seç (opsiyonel)</option>
              <?= select_options(array_column($suppliers, 'name', 'id'), null) ?>
            </select>
          <?php endif; ?>
          <button class="btn btn-sm" name="to" value="siparis_verildi" disabled data-needs-selection>Depoya sipariş verildi</button>
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
              <td><b><?= e($it['lens_type']) ?></b><small class="block muted"><?= e(['uzak' => 'Uzak', 'yakin' => 'Yakın', 'cok_odak' => 'Çok odak'][$it['item_group']] ?? '') ?> · <?= $it['eye'] === 'R' ? 'Sağ' : 'Sol' ?></small></td>
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

<?php if ($groups && $tab !== 'gelen'): ?>
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
