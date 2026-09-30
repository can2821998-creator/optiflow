<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
if (!is_super()) {
    render_error_page('Yetkiniz yok', 'Tedarikçi cari hesapları yalnızca yöneticiler tarafından görüntülenebilir.');
}

if (is_post()) {
    $name = mb_substr(trim(post('name')), 0, 120);
    if ($name === '') {
        flash('Tedarikçi adı zorunludur.', 'error');
        redirect('suppliers.php');
    }
    $id = insert('suppliers', [
        'name'         => $name,
        'contact_name' => mb_substr(post('contact_name'), 0, 120) ?: null,
        'phone'        => mb_substr(post('phone'), 0, 20) ?: null,
        'address'      => mb_substr(post('address'), 0, 255) ?: null,
        'tax_no'       => mb_substr(post('tax_no'), 0, 30) ?: null,
    ]);
    audit('supplier_create', 'supplier', $id, ['ad' => $name]);
    flash('“' . $name . '” tedarikçi olarak eklendi.');
    redirect('supplier.php?id=' . $id);
}

$search = mb_substr(query('q'), 0, 80);
$where = 's.is_active = 1';
$params = [];
if ($search !== '') {
    $where .= ' AND (s.name LIKE ? OR s.contact_name LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$suppliers = rows(
    "SELECT s.*, COALESCE(si.invoiced, 0) - COALESCE(sp.paid, 0) AS balance,
            (SELECT MAX(invoice_date) FROM supplier_invoices WHERE supplier_id = s.id) AS last_invoice
     FROM suppliers s " . SUPPLIER_BALANCE_JOIN . "
     WHERE $where
     ORDER BY balance DESC, s.name",
    $params
);
$totalDebt = array_sum(array_column($suppliers, 'balance'));

page_start('Tedarikçiler', 'suppliers');
page_header('Tedarikçiler', count($suppliers) . ' tedarikçi · toplam borç ' . money($totalDebt), '<a class="btn" href="tedarikci-karne.php">' . icon('chart') . ' Tedarikçi karnesi</a>', '', 'Cari hesap');
?>
<details class="card">
  <summary class="card-head" style="cursor:pointer"><h2><?= icon('plus') ?> Yeni tedarikçi ekle</h2></summary>
  <form method="post" class="grid cols-2" style="margin-top:16px">
    <?= csrf_field() ?>
    <label class="field span-all"><span>Tedarikçi adı *</span><input name="name" required placeholder="örn. Essilor, Hoya, Yerel Depo"></label>
    <label class="field"><span>Yetkili kişi</span><input name="contact_name"></label>
    <label class="field"><span>Telefon</span><input name="phone" type="tel"></label>
    <label class="field span-all"><span>Adres</span><input name="address"></label>
    <label class="field"><span>Vergi no</span><input name="tax_no"></label>
    <button class="btn btn-primary">Tedarikçiyi ekle</button>
  </form>
</details>

<section class="card">
  <div class="toolbar">
    <form class="search" method="get" role="search">
      <?= icon('search') ?>
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Tedarikçi adı" aria-label="Tedarikçi ara">
    </form>
  </div>
  <?php if (!$suppliers): ?>
    <?= empty_state('Henüz tedarikçi yok', 'Essilor, Hoya, Zeiss veya yerel depo gibi çalıştığınız tedarikçileri yukarıdan ekleyin.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Tedarikçi</th><th class="hide-sm">Yetkili</th><th class="hide-sm">Telefon</th><th class="hide-md">Son fatura</th><th class="num">Bakiye (borcumuz)</th></tr></thead>
        <tbody>
          <?php foreach ($suppliers as $s): ?>
            <tr>
              <td><a class="cell-link" href="supplier.php?id=<?= (int) $s['id'] ?>"><b><?= e($s['name']) ?></b></a></td>
              <td class="hide-sm"><?= e($s['contact_name'] ?: '—') ?></td>
              <td class="hide-sm"><?= $s['phone'] ? e(phone_display($s['phone'])) : '—' ?></td>
              <td class="hide-md"><?= date_tr($s['last_invoice']) ?></td>
              <td class="num <?= (float) $s['balance'] > 0.009 ? 'text-danger' : 'muted' ?>"><?= (float) $s['balance'] > 0.009 ? money($s['balance']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php page_end();
