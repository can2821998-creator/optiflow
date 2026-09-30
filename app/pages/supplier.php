<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$id = is_post() ? post_int('supplier_id') : query_int('id');
$supplier = row('SELECT * FROM suppliers WHERE id = ?', [$id]);
if (!$supplier) {
    http_response_code(404);
    render_error_page('Tedarikçi bulunamadı', 'Silinmiş veya hatalı bir bağlantı olabilir.');
}
$self = 'supplier.php?id=' . $id;
$viewerCanSeeLedger = is_super();

if (is_post()) {
    $action = post('action');

    if ($action === 'update_supplier' && is_super()) {
        $name = mb_substr(trim(post('name')), 0, 120);
        if ($name === '') {
            flash('Tedarikçi adı zorunludur.', 'error');
            redirect($self);
        }
        update('suppliers', [
            'name'         => $name,
            'contact_name' => mb_substr(post('contact_name'), 0, 120) ?: null,
            'phone'        => mb_substr(post('phone'), 0, 20) ?: null,
            'address'      => mb_substr(post('address'), 0, 255) ?: null,
            'tax_no'       => mb_substr(post('tax_no'), 0, 30) ?: null,
            'note'         => mb_substr(post('note'), 0, 2000) ?: null,
            'updated_at'   => date('Y-m-d H:i:s'),
        ] + (column_exists('suppliers', 'email') ? ['email' => filter_var(trim(post('email')), FILTER_VALIDATE_EMAIL) ?: null] : []), 'id = ?', [$id]);
        flash('Tedarikçi bilgileri güncellendi.');
        redirect($self);
    }

    if ($action === 'toggle_active' && is_super()) {
        update('suppliers', ['is_active' => (int) $supplier['is_active'] ? 0 : 1], 'id = ?', [$id]);
        flash((int) $supplier['is_active'] ? 'Tedarikçi pasife alındı.' : 'Tedarikçi tekrar aktif.');
        redirect($self);
    }

    if ($action === 'invoice_delivery') {
        $backTo = $viewerCanSeeLedger ? $self : 'deliveries.php';
        $delivery = row('SELECT * FROM supplier_deliveries WHERE id = ? AND supplier_id = ? AND invoice_id IS NULL', [post_int('delivery_id'), $id]);
        if (!$delivery) {
            flash('Teslimat bulunamadı veya zaten faturalanmış.', 'error');
            redirect($backTo);
        }
        $errors = [];
        $invoiceNo = mb_substr(trim(post('invoice_no')), 0, 60);
        $date = post('invoice_date') ?: date('Y-m-d');
        if ($invoiceNo === '') { $errors[] = 'Fatura numarası zorunlu.'; }
        if (!valid_date($date)) { $errors[] = 'Fatura tarihi geçersiz.'; }

        // Her cam satırı için ayrı maliyet: hepsine eşit bölmek yerine, aynı faturada
        // farklı siparişlerden/cam tiplerinden gelen camlar doğru maliyetle eşleşir.
        $items = delivery_items($delivery['id']);
        $rawCosts = (array) ($_POST['item_cost'] ?? []);
        $itemCosts = [];
        $amount = 0.0;
        foreach ($items as $it) {
            $raw = (string) ($rawCosts[$it['id']] ?? '');
            $c = $raw === '' ? 0.0 : parse_money($raw);
            if ($c === null || $c < 0) {
                $errors[] = 'Cam #' . $it['id'] . ' için maliyet geçersiz.';
                continue;
            }
            $itemCosts[(int) $it['id']] = $c;
            $amount += $c;
        }

        // Ek kalemler: kargo, boyama gibi camdan bağımsız fatura satırları. Sistem
        // camları getiremediğinde de bağımsız tutar girmenin tek yolu burasıdır.
        $extraDescs = (array) ($_POST['extra_desc'] ?? []);
        $extraAmounts = (array) ($_POST['extra_amount'] ?? []);
        $extraLines = [];
        foreach ($extraDescs as $i => $desc) {
            $desc = mb_substr(trim((string) $desc), 0, 80);
            $raw = (string) ($extraAmounts[$i] ?? '');
            if ($desc === '' && $raw === '') { continue; }
            $amt = $raw === '' ? null : parse_money($raw);
            if ($desc === '' || $amt === null || $amt <= 0) {
                $errors[] = 'Ek kalem satırı eksik veya hatalı (açıklama + tutar birlikte girilmeli).';
                continue;
            }
            $extraLines[] = ['desc' => $desc, 'amount' => $amt];
            $amount += $amt;
        }

        if ($amount <= 0) { $errors[] = 'Toplam tutar 0’dan büyük olmalı — cam maliyeti veya ek kalem girin.'; }
        if ($errors) {
            foreach ($errors as $err) { flash($err, 'error'); }
            redirect($backTo);
        }
        $note = 'Teslimat #' . $delivery['id'] . ' · ' . $delivery['item_count'] . ' cam';
        if ($extraLines) {
            $note .= ' · Ek: ' . implode(', ', array_map(static fn($l) => $l['desc'] . ' ' . money($l['amount']), $extraLines));
        }
        transaction(function () use ($id, $delivery, $invoiceNo, $date, $amount, $itemCosts, $note) {
            $iid = insert('supplier_invoices', [
                'supplier_id'  => $id,
                'invoice_no'   => $invoiceNo,
                'invoice_date' => $date,
                'amount'       => $amount,
                'note'         => $note,
                'created_by'   => current_user()['id'],
            ]);
            update('supplier_deliveries', ['invoice_id' => $iid], 'id = ?', [$delivery['id']]);
            foreach ($itemCosts as $itemId => $cost) {
                q('UPDATE prescription_lens_items SET unit_cost = ? WHERE id = ? AND delivery_id = ?', [$cost, $itemId, $delivery['id']]);
            }
        });
        audit('supplier_invoice', 'supplier', $id, ['fatura' => $invoiceNo, 'tutar' => $amount, 'teslimat' => $delivery['item_count'] . ' cam']);
        flash($itemCosts ? 'Fatura kaydedildi, her cam kendi girdiğiniz maliyetle işaretlendi.' : 'Fatura kaydedildi.');
        redirect($backTo);
    }

    if ($action === 'add_invoice' && is_super()) {
        $errors = [];
        $invoiceNo = mb_substr(trim(post('invoice_no')), 0, 60);
        $date = post('invoice_date') ?: date('Y-m-d');
        $amount = parse_money(post('amount'));
        $frameBrand = mb_substr(trim(post('frame_brand')), 0, 80);
        $frameModel = mb_substr(trim(post('frame_model')), 0, 80);
        $frameQty = post_int('frame_qty');
        if ($invoiceNo === '') { $errors[] = 'Fatura numarası zorunlu.'; }
        if (!valid_date($date)) { $errors[] = 'Fatura tarihi geçersiz.'; }
        if ($amount === null || $amount <= 0) { $errors[] = 'Tutar geçersiz.'; }
        if ($frameBrand !== '' && $frameQty < 1) { $errors[] = 'Çerçeve markası girdiyseniz adet de girilmeli.'; }
        if ($errors) {
            foreach ($errors as $err) { flash($err, 'error'); }
            redirect($self);
        }
        $iid = insert('supplier_invoices', [
            'supplier_id'  => $id,
            'invoice_no'   => $invoiceNo,
            'invoice_date' => $date,
            'amount'       => $amount,
            'note'         => mb_substr(post('note'), 0, 255) ?: null,
            'created_by'   => current_user()['id'],
        ]);
        audit('supplier_invoice', 'supplier', $id, ['fatura' => $invoiceNo, 'tutar' => $amount]);
        $msg = 'Fatura kaydedildi: ' . $invoiceNo . ' · ' . money($amount);
        if ($frameBrand !== '') {
            record_frame_purchase($id, $frameBrand, $frameModel, $frameQty, $amount);
            $msg .= ' · “' . $frameBrand . ($frameModel ? ' ' . $frameModel : '') . '” için ortalama çerçeve maliyeti güncellendi.';
        }
        flash($msg);
        redirect($self);
    }

    if ($action === 'add_payment' && is_super()) {
        $amount = parse_money(post('amount'));
        $date = post('payment_date') ?: date('Y-m-d');
        if ($amount === null || $amount <= 0) {
            flash('Tutar geçersiz.', 'error');
            redirect($self);
        }
        if (!valid_date($date) || $date > date('Y-m-d')) {
            flash('Ödeme tarihi geçersiz (ileri tarih olamaz).', 'error');
            redirect($self);
        }
        $time = $date === date('Y-m-d') ? date('H:i:s') : '12:00:00';
        insert('supplier_payments', [
            'supplier_id' => $id,
            'amount'      => $amount,
            'method'      => post('method') ?: 'havale',
            'note'        => mb_substr(post('note'), 0, 255) ?: null,
            'created_by'  => current_user()['id'],
            'created_at'  => "$date $time",
        ]);
        audit('supplier_payment', 'supplier', $id, ['tutar' => $amount, 'yöntem' => payment_methods()[post('method')] ?? post('method')]);
        flash('Ödeme kaydedildi: ' . money($amount));
        redirect($self);
    }

    if ($action === 'delete_invoice' && is_super()) {
        q('DELETE FROM supplier_invoices WHERE id = ? AND supplier_id = ?', [post_int('row_id'), $id]);
        flash('Fatura silindi.', 'info');
        redirect($self);
    }

    if ($action === 'delete_payment' && is_super()) {
        q('DELETE FROM supplier_payments WHERE id = ? AND supplier_id = ?', [post_int('row_id'), $id]);
        flash('Ödeme kaydı silindi.', 'info');
        redirect($self);
    }

    redirect($self);
}

/* ---------- Ledger (fatura + ödeme, kronolojik, çalışan bakiye) ---------- */
$from = valid_date(query('from')) ? query('from') : '';
$until = valid_date(query('until')) ? query('until') : '';

$invoices = rows('SELECT * FROM supplier_invoices WHERE supplier_id = ? ORDER BY invoice_date, id', [$id]);
$payments = rows('SELECT * FROM supplier_payments WHERE supplier_id = ? ORDER BY created_at, id', [$id]);

$ledger = [];
foreach ($invoices as $inv) {
    $ledger[] = ['kind' => 'invoice', 'date' => $inv['invoice_date'], 'sort' => $inv['invoice_date'] . ' 00:00:01', 'amount' => (float) $inv['amount'], 'label' => 'Fatura ' . $inv['invoice_no'], 'note' => $inv['note'], 'id' => (int) $inv['id']];
}
foreach ($payments as $p) {
    $ledger[] = ['kind' => 'payment', 'date' => substr($p['created_at'], 0, 10), 'sort' => $p['created_at'], 'amount' => -(float) $p['amount'], 'label' => 'Ödeme · ' . (payment_methods()[$p['method']] ?? $p['method']), 'note' => $p['note'], 'id' => (int) $p['id']];
}
usort($ledger, static fn($a, $b) => $a['sort'] <=> $b['sort']);

$balance = 0.0;
foreach ($ledger as &$row) {
    $balance += $row['amount'];
    $row['balance'] = $balance;
}
unset($row);

$totalInvoiced = array_sum(array_column($invoices, 'amount'));
$totalPaid = array_sum(array_column($payments, 'amount'));

$ledgerView = $ledger;
if ($from !== '' || $until !== '') {
    $ledgerView = array_values(array_filter($ledger, static function ($r) use ($from, $until) {
        if ($from !== '' && $r['date'] < $from) { return false; }
        if ($until !== '' && $r['date'] > $until) { return false; }
        return true;
    }));
}

$lensCount = (int) scalar('SELECT COUNT(*) FROM prescription_lens_items WHERE supplier_id = ?', [$id]);
$pendingDeliveries = rows('SELECT * FROM supplier_deliveries WHERE supplier_id = ? AND invoice_id IS NULL ORDER BY delivered_at DESC', [$id]);

page_start($supplier['name'], 'suppliers');
?>
<a class="back-link" href="suppliers.php"><?= icon('arrow-left') ?> Tedarikçiler</a>

<section class="hero">
  <div class="hero-main">
    <span class="avatar lg"><?= e(mb_substr($supplier['name'], 0, 2)) ?></span>
    <div>
      <small class="eyebrow">Tedarikçi<?= (int) $supplier['is_active'] === 0 ? ' · Pasif' : '' ?></small>
      <h1><?= e($supplier['name']) ?></h1>
      <p><?= $supplier['contact_name'] ? e($supplier['contact_name']) . ' · ' : '' ?><?= $supplier['phone'] ? e(phone_display($supplier['phone'])) : 'Telefon girilmemiş' ?></p>
    </div>
  </div>
  <div class="hero-actions">
    <?php if ($viewerCanSeeLedger): ?>
    <a class="btn" href="print.php?type=supplier&id=<?= $id ?><?= $from ? '&from=' . $from : '' ?><?= $until ? '&until=' . $until : '' ?>" target="_blank"><?= icon('print') ?> Ekstre (PDF)</a>
    <form method="post" class="inline" data-confirm="<?= (int) $supplier['is_active'] ? 'Tedarikçi pasife alınsın mı?' : 'Tedarikçi tekrar aktif edilsin mi?' ?>">
      <?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="toggle_active">
      <button class="btn btn-ghost"><?= (int) $supplier['is_active'] ? 'Pasife al' : 'Aktif et' ?></button>
    </form>
    <?php endif; ?>
  </div>
</section>

<?php if ($viewerCanSeeLedger): ?>
<div class="stats">
  <div class="stat"><small>Toplam fatura</small><b><?= money($totalInvoiced) ?></b></div>
  <div class="stat"><small>Toplam ödeme</small><b><?= money($totalPaid) ?></b></div>
  <div class="stat <?= $balance > 0.009 ? 'tone-red' : 'tone-green' ?>"><small>Güncel bakiye (borcumuz)</small><b><?= money($balance) ?></b></div>
  <div class="stat"><small>Etiketli cam</small><b><?= $lensCount ?></b></div>
</div>
<?php endif; ?>

<div class="split">
  <div class="split-main">
    <?php if ($pendingDeliveries): ?>
    <section class="card">
      <div class="card-head">
        <h2><?= icon('box') ?> Fatura bekleyen teslimatlar</h2>
        <span class="badge tone-amber"><?= count($pendingDeliveries) ?></span>
      </div>
      <p class="muted" style="margin-top:-8px">Camlar “Geldi” işaretlendiğinde buraya otomatik düşer. Aynı faturada farklı sipariş/cam tipi olabileceği için maliyeti cam cam girin — hepsine otomatik eşit bölünmez.</p>
      <div class="stack">
        <?php foreach ($pendingDeliveries as $d): $dItems = delivery_items((int) $d['id']); ?>
          <form method="post" class="delivery-row stack" data-delivery-form>
            <?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="invoice_delivery"><input type="hidden" name="delivery_id" value="<?= (int) $d['id'] ?>">
            <div class="field span-all delivery-meta"><b><?= (int) $d['item_count'] ?> cam</b> · <?= date_tr($d['delivered_at'], true) ?> tarihinde geldi olarak işaretlendi</div>
            <?php if ($dItems): ?>
            <div class="table-wrap">
              <table class="table">
                <thead><tr><th>Sipariş</th><th>Cam</th><th class="num">Maliyet</th></tr></thead>
                <tbody>
                  <?php foreach ($dItems as $it): ?>
                    <tr>
                      <td><a class="link" href="order.php?id=<?= (int) $it['order_id'] ?>" target="_blank"><?= e($it['first_name'] . ' ' . $it['last_name']) ?></a><small class="block muted"><?= order_no((int) $it['order_id']) ?></small></td>
                      <td><?= e($it['lens_type']) ?><small class="block muted"><?= $it['eye'] === 'R' ? 'Sağ' : 'Sol' ?> · SPH <?= e($it['sph']) ?><?= $it['cyl'] ? ' CYL ' . e($it['cyl']) : '' ?></small></td>
                      <td class="num"><input name="item_cost[<?= (int) $it['id'] ?>]" inputmode="decimal" placeholder="0,00" class="cost-input" data-cost-input></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="2">Toplam</th><th class="num" data-cost-total>0,00 ₺</th></tr></tfoot>
              </table>
            </div>
            <?php else: ?>
              <p class="muted small">Bu teslimattaki camlar sistemde bulunamadı (silinmiş/değişmiş olabilir). Aşağıya fatura tutarını “Ek kalemler” olarak girebilirsiniz.</p>
            <?php endif; ?>
            <div class="extra-lines">
              <b class="extra-lines-label">Ek kalemler <small class="muted">(kargo, boyama vb. — camdan bağımsız)</small></b>
              <?php for ($i = 0; $i < 3; $i++): ?>
                <div class="grid cols-3">
                  <label class="field span-2"><span>Açıklama</span><input name="extra_desc[]" placeholder="örn. Kargo"></label>
                  <label class="field"><span>Tutar</span><input name="extra_amount[]" inputmode="decimal" placeholder="0,00" data-cost-input></label>
                </div>
              <?php endfor; ?>
            </div>
            <div class="grid cols-3">
              <label class="field"><span>Fatura no *</span><input name="invoice_no" required></label>
              <label class="field"><span>Fatura tarihi</span><input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>"></label>
            </div>
            <button class="btn btn-primary">Faturayı kaydet</button>
          </form>
        <?php endforeach; ?>
      </div>
    </section>
    <script>
    document.querySelectorAll('[data-delivery-form]').forEach(function (form) {
      var total = form.querySelector('[data-cost-total]');
      function recalc() {
        var sum = 0;
        form.querySelectorAll('[data-cost-input]').forEach(function (inp) {
          var v = parseFloat((inp.value || '0').replace(/\./g, '').replace(',', '.'));
          if (!isNaN(v)) sum += v;
        });
        total.textContent = sum.toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
      }
      form.querySelectorAll('[data-cost-input]').forEach(function (inp) { inp.addEventListener('input', recalc); });
    });
    </script>
    <?php elseif (!$viewerCanSeeLedger): ?>
    <section class="card">
      <?= empty_state('Fatura bekleyen teslimat yok', 'Camlar “Geldi” işaretlendiğinde ve tedarikçisi belliyse burada listelenir.') ?>
    </section>
    <?php endif; ?>

    <?php if ($viewerCanSeeLedger): ?>
    <section class="card">
      <div class="card-head">
        <h2>Fatura ekle</h2>
      </div>
      <form method="post" class="grid cols-3">
        <?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="add_invoice">
        <label class="field"><span>Fatura no *</span><input name="invoice_no" required></label>
        <label class="field"><span>Fatura tarihi</span><input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>"></label>
        <label class="field"><span>Tutar *</span><input name="amount" inputmode="decimal" required placeholder="0,00"></label>
        <label class="field span-all"><span>Not</span><input name="note" placeholder="örn. hangi siparişlerin camları"></label>
        <details class="span-all frame-invoice-toggle">
          <summary>Bu bir çerçeve alımı mı? (opsiyonel — ortalama maliyeti günceller)</summary>
          <div class="grid cols-3" style="margin-top:12px">
            <label class="field"><span>Çerçeve markası</span><input name="frame_brand" placeholder="örn. Ray-Ban"></label>
            <label class="field"><span>Model (opsiyonel)</span><input name="frame_model" placeholder="örn. RB2140"></label>
            <label class="field"><span>Adet</span><input name="frame_qty" type="number" min="1" placeholder="örn. 20"></label>
          </div>
        </details>
        <button class="btn btn-primary">Faturayı kaydet</button>
      </form>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Ödeme ekle</h2>
      </div>
      <form method="post" class="grid cols-3">
        <?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="add_payment">
        <label class="field"><span>Tutar *</span><input name="amount" inputmode="decimal" required placeholder="0,00"></label>
        <label class="field"><span>Yöntem</span><select name="method"><?= select_options(payment_methods(), 'havale') ?></select></label>
        <label class="field"><span>Ödeme tarihi</span><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></label>
        <label class="field span-all"><span>Not</span><input name="note"></label>
        <button class="btn btn-primary">Ödemeyi kaydet</button>
      </form>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Cari hesap ekstresi</h2>
        <form method="get" class="inline">
          <input type="hidden" name="id" value="<?= $id ?>">
          <input type="date" name="from" value="<?= e($from) ?>" aria-label="Başlangıç">
          <input type="date" name="until" value="<?= e($until) ?>" aria-label="Bitiş">
          <button class="btn btn-sm">Filtrele</button>
          <?php if ($from || $until): ?><a class="btn btn-ghost btn-sm" href="<?= e($self) ?>">Temizle</a><?php endif; ?>
        </form>
      </div>
      <?php if (!$ledgerView): ?>
        <?= empty_state('Hareket yok', 'Fatura veya ödeme eklendiğinde burada listelenir.') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Tarih</th><th>Hareket</th><th class="hide-sm">Not</th><th class="num">Tutar</th><th class="num">Bakiye</th><?php if (is_super()): ?><th></th><?php endif; ?></tr></thead>
            <tbody>
              <?php foreach (array_reverse($ledgerView) as $r): ?>
                <tr>
                  <td><?= date_tr($r['date']) ?></td>
                  <td><?= e($r['label']) ?></td>
                  <td class="hide-sm muted"><?= e($r['note'] ?: '—') ?></td>
                  <td class="num <?= $r['amount'] > 0 ? 'text-danger' : '' ?>"><?= $r['amount'] > 0 ? '+' : '− ' ?><?= money(abs($r['amount'])) ?></td>
                  <td class="num"><b><?= money($r['balance']) ?></b></td>
                  <?php if (is_super()): ?>
                    <td><form method="post" data-confirm="Bu hareket silinsin mi?"><?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_<?= $r['kind'] ?>"><input type="hidden" name="row_id" value="<?= $r['id'] ?>"><button class="icon-btn danger sm" aria-label="Sil"><?= icon('trash') ?></button></form></td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
    <?php endif; ?>

  </div>

  <?php if ($viewerCanSeeLedger): ?>
  <div class="split-side">
    <section class="card sticky">
      <div class="card-head"><h2>Bilgiler</h2></div>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="action" value="update_supplier">
        <label class="field"><span>Tedarikçi adı</span><input name="name" value="<?= e($supplier['name']) ?>" required></label>
        <label class="field"><span>Yetkili kişi</span><input name="contact_name" value="<?= e($supplier['contact_name'] ?? '') ?>"></label>
        <label class="field"><span>Telefon</span><input name="phone" type="tel" value="<?= e($supplier['phone'] ?? '') ?>"></label>
        <?php if (array_key_exists('email', $supplier)): ?><label class="field"><span>E-posta (cam siparişi için)</span><input name="email" type="email" value="<?= e($supplier['email'] ?? '') ?>"></label><?php endif; ?>
        <label class="field"><span>Adres</span><input name="address" value="<?= e($supplier['address'] ?? '') ?>"></label>
        <label class="field"><span>Vergi no</span><input name="tax_no" value="<?= e($supplier['tax_no'] ?? '') ?>"></label>
        <label class="field"><span>Not</span><textarea name="note" rows="3"><?= e($supplier['note'] ?? '') ?></textarea></label>
        <button class="btn btn-block">Bilgileri kaydet</button>
      </form>
    </section>
  </div>
  <?php endif; ?>
</div>
<?php page_end();
