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
        ] + (column_exists('suppliers', 'email') ? ['email' => filter_var(trim(post('email')), FILTER_VALIDATE_EMAIL) ?: null] : [])
          + (array_key_exists('uts_kurum_no', $supplier) ? ['uts_kurum_no' => mb_substr(preg_replace('/\D/', '', post('uts_kurum_no')) ?? '', 0, 20) ?: null] : []), 'id = ?', [$id]);
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
        $dueDate = post('due_date');
        if ($dueDate !== '' && !valid_date($dueDate)) { $errors[] = 'Vade tarihi geçersiz.'; }
        $senetVer = post('senet_ver') === '1' && ozellik_acik('tedarik_finans');
        if ($senetVer && !valid_date(post('senet_vade'))) { $errors[] = 'Senet vadesi geçersiz.'; }
        if ($errors) {
            foreach ($errors as $err) { flash($err, 'error'); }
            redirect($self);
        }
        if (row('SELECT id FROM supplier_invoices WHERE supplier_id = ? AND invoice_no = ?', [$id, $invoiceNo])) {
            flash('Bu tedarikçide ' . $invoiceNo . ' numaralı fatura zaten kayıtlı.', 'error');
            redirect($self);
        }
        $iid = insert('supplier_invoices', [
            'supplier_id'  => $id,
            'invoice_no'   => $invoiceNo,
            'invoice_date' => $date,
            'amount'       => $amount,
            'note'         => mb_substr(post('note'), 0, 255) ?: null,
            'created_by'   => current_user()['id'],
        ] + (column_exists('supplier_invoices', 'due_date') ? ['due_date' => $dueDate ?: null] : []));
        audit('supplier_invoice', 'supplier', $id, ['fatura' => $invoiceNo, 'tutar' => $amount]);
        $msg = 'Fatura kaydedildi: ' . $invoiceNo . ' · ' . money($amount);
        if ($senetVer) {
            try {
                senet_ver($id, (float) $amount, post('senet_vade'), post('senet_no'), $date, $iid);
                audit('senet_ver', 'supplier', $id, ['fatura' => $invoiceNo, 'tutar' => $amount, 'vade' => post('senet_vade')]);
                $msg .= ' · senetle kapatıldı (vade ' . date_tr(post('senet_vade')) . ')';
            } catch (DomainException $e) {
                flash('Fatura kaydedildi ama senet verilemedi: ' . $e->getMessage(), 'error');
            }
        }
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
            'method'      => isset(payment_methods()[post('method')]) ? post('method') : 'havale',
            'note'        => mb_substr(post('note'), 0, 255) ?: null,
            'created_by'  => current_user()['id'],
            'created_at'  => "$date $time",
        ]);
        audit('supplier_payment', 'supplier', $id, ['tutar' => $amount, 'yöntem' => payment_methods()[post('method')] ?? post('method')]);
        flash('Ödeme kaydedildi: ' . money($amount));
        redirect($self);
    }

    if ($action === 'delete_invoice' && is_super()) {
        // 4.14.0: e-Fatura'dan stoğa girmiş adetler geri alınır; senede bağlı fatura silinmez.
        $inv = row('SELECT id FROM supplier_invoices WHERE id = ? AND supplier_id = ?', [post_int('row_id'), $id]);
        try {
            if ($inv) {
                alis_fatura_sil((int) $inv['id']);
            }
            flash('Fatura silindi.', 'info');
        } catch (DomainException $e) {
            flash($e->getMessage(), 'error');
        }
        redirect($self);
    }

    if ($action === 'delete_payment' && is_super()) {
        if (cam_hata_odeme_bagli_mi(post_int('row_id'))) {
            flash('Bu kayıt bir hatalı cam iadesine ait. Siparişin "Hatalı cam" bölümünden geri alın.', 'error');
            redirect($self);
        }
        if ($bagli = senet_odeme_bagli_mi(post_int('row_id'))) {
            flash('Bu kayıt ' . ($bagli['senet_no'] ? $bagli['senet_no'] . ' numaralı ' : '') . 'senede ait. Senetler bölümünden senedi iptal edin.', 'error');
            redirect($self);
        }
        q('DELETE FROM supplier_payments WHERE id = ? AND supplier_id = ?', [post_int('row_id'), $id]);
        flash('Ödeme kaydı silindi.', 'info');
        redirect($self);
    }

    // 4.14.0 — Senet ver / öde / iptal
    if (in_array($action, ['senet_ver', 'senet_ode', 'senet_iptal', 'senet_geri'], true) && is_super() && ozellik_acik('tedarik_finans')) {
        try {
            if ($action === 'senet_ver') {
                $tutar = parse_money(post('senet_tutar'));
                if ($tutar === null || $tutar <= 0) {
                    throw new DomainException('Senet tutarı geçersiz.');
                }
                senet_ver($id, $tutar, post('senet_vade'), post('senet_no'), post('senet_duzenleme') ?: date('Y-m-d'), post_int('senet_fatura') ?: null, post('senet_not'), post('senet_duzenleme_yeri'), post('senet_odeme_yeri'));
                audit('senet_ver', 'supplier', $id, ['tutar' => $tutar, 'vade' => post('senet_vade'), 'no' => post('senet_no')]);
                flash('Senet kaydedildi; cari ' . money($tutar) . ' kapandı, borç senete geçti.');
            } else {
                $sn = row('SELECT * FROM tedarikci_senetleri WHERE id = ? AND supplier_id = ?', [post_int('senet_id'), $id]);
                if (!$sn) {
                    throw new DomainException('Senet bulunamadı.');
                }
                if ($action === 'senet_ode') {
                    senet_ode((int) $sn['id'], post('odeme_tarihi') ?: date('Y-m-d'), post('odeme_yontemi') ?: 'havale');
                    flash('Senet ödendi olarak işaretlendi.');
                } elseif ($action === 'senet_geri') {
                    senet_odeme_geri_al((int) $sn['id']);
                    flash('Senet yeniden "ödenecek" durumuna alındı.', 'info');
                } else {
                    senet_iptal((int) $sn['id']);
                    flash('Senet iptal edildi; tutar cariye borç olarak döndü.', 'info');
                }
                audit('senet_' . substr($action, 6), 'supplier', $id, ['senet' => (int) $sn['id'], 'tutar' => $sn['tutar']]);
            }
        } catch (DomainException $e) {
            flash($e->getMessage(), 'error');
        }
        redirect($self . '#senetler');
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
    $ledger[] = ['kind' => 'invoice', 'date' => $inv['invoice_date'], 'sort' => $inv['invoice_date'] . ' 00:00:01', 'amount' => (float) $inv['amount'], 'label' => 'Fatura ' . $inv['invoice_no'] . (!empty($inv['due_date']) ? ' · vade ' . date_tr((string) $inv['due_date']) : ''), 'note' => $inv['note'], 'id' => (int) $inv['id']];
}
foreach ($payments as $p) {
    $ledger[] = ['kind' => 'payment', 'date' => substr($p['created_at'], 0, 10), 'sort' => $p['created_at'], 'amount' => -(float) $p['amount'], 'label' => 'Ödeme · ' . (tedarik_odeme_yontemleri()[$p['method']] ?? $p['method']), 'note' => $p['note'], 'id' => (int) $p['id']];
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
  <?php if (ozellik_acik('tedarik_finans')): $senetBorc = senet_bekleyen_toplam($id); ?>
    <div class="stat <?= $senetBorc > 0.009 ? 'tone-amber' : '' ?>"><small>Ödenmemiş senet</small><b><?= money($senetBorc) ?></b><span>Toplam borç <?= money($balance + $senetBorc) ?></span></div>
  <?php else: ?>
  <div class="stat"><small>Etiketli cam</small><b><?= $lensCount ?></b></div>
  <?php endif; ?>
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
        <label class="field"><span>Vade (opsiyonel)</span><input type="date" name="due_date"></label>
        <label class="field span-2"><span>Not</span><input name="note" placeholder="örn. hangi siparişlerin camları"></label>
        <?php if (ozellik_acik('tedarik_finans')): ?>
        <details class="span-all frame-invoice-toggle">
          <summary>Bu fatura için senet verildi mi?</summary>
          <div class="grid cols-3" style="margin-top:12px">
            <label class="check" style="align-self:end"><input type="checkbox" name="senet_ver" value="1"> Fatura tutarında senet verildi</label>
            <label class="field"><span>Senet vadesi</span><input type="date" name="senet_vade"></label>
            <label class="field"><span>Senet no (opsiyonel)</span><input name="senet_no" maxlength="40"></label>
          </div>
        </details>
        <?php endif; ?>
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

    <?php if (ozellik_acik('tedarik_finans')):
        $senetler = rows("SELECT * FROM tedarikci_senetleri WHERE supplier_id = ? AND durum <> 'iptal' ORDER BY (durum = 'bekliyor') DESC, vade, id LIMIT 100", [$id]);
        $acikFaturalar = rows('SELECT id, invoice_no, invoice_date, amount FROM supplier_invoices WHERE supplier_id = ? ORDER BY invoice_date DESC, id DESC LIMIT 50', [$id]); ?>
    <section class="card" id="senetler">
      <div class="card-head"><h2><?= icon('receipt') ?> Senetler</h2><a class="link" href="senetler.php">Ödeme takvimi</a></div>
      <?php if ($senetler): ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Vade</th><th>Senet</th><th class="num">Tutar</th><th>Durum</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($senetler as $sn): [$snAd, $snTon] = senet_durumlari()[$sn['durum']] ?? [$sn['durum'], 'gray'];
              $gecikti = $sn['durum'] === 'bekliyor' && $sn['vade'] < date('Y-m-d'); ?>
            <tr>
              <td class="<?= $gecikti ? 'text-danger' : '' ?>"><b><?= e(date_tr((string) $sn['vade'])) ?></b><?= $gecikti ? '<small class="block">gecikti</small>' : '' ?></td>
              <td><?= e($sn['senet_no'] ?: '#' . $sn['id']) ?><small class="block muted"><?= e(date_tr((string) $sn['duzenleme'])) ?><?= $sn['notlar'] ? ' · ' . e((string) $sn['notlar']) : '' ?></small></td>
              <td class="num"><?= money($sn['tutar']) ?></td>
              <td><span class="badge tone-<?= e($snTon) ?>"><?= e($snAd) ?></span><?php if ($sn['durum'] === 'odendi'): ?><small class="block muted"><?= e(date_tr((string) $sn['odeme_tarihi'])) ?> · <?= e(payment_methods()[$sn['odeme_yontemi']] ?? (string) $sn['odeme_yontemi']) ?></small><?php endif; ?></td>
              <td class="num">
                <div class="btn-row" style="justify-content:flex-end">
                  <a class="btn btn-sm btn-ghost" href="senetler.php?yazdir=<?= (int) $sn['id'] ?>" target="_blank" title="Senedi yazdır"><?= icon('print') ?></a>
                  <?php if ($sn['durum'] === 'bekliyor'): ?>
                    <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="senet_ode"><input type="hidden" name="senet_id" value="<?= (int) $sn['id'] ?>">
                      <select name="odeme_yontemi" aria-label="Ödeme yöntemi"><?= select_options(payment_methods(), 'havale') ?></select>
                      <button class="btn btn-sm btn-primary">Ödendi</button></form>
                    <form method="post" class="inline" data-confirm="Senet iptal edilsin mi? Tutar cariye borç olarak döner."><?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="senet_iptal"><input type="hidden" name="senet_id" value="<?= (int) $sn['id'] ?>"><button class="linkish danger">İptal</button></form>
                  <?php else: ?>
                    <form method="post" class="inline" data-confirm="Ödeme geri alınsın mı? Senet yeniden ödenecek olur."><?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="senet_geri"><input type="hidden" name="senet_id" value="<?= (int) $sn['id'] ?>"><button class="linkish">Geri al</button></form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php else: ?>
        <p class="muted small">Bu tedarikçiye verilmiş senet yok.</p>
      <?php endif; ?>
      <details style="margin-top:12px">
        <summary class="linkish"><?= icon('plus') ?> Senet ver</summary>
        <form method="post" class="grid cols-3" style="margin-top:10px">
          <?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= $id ?>"><input type="hidden" name="action" value="senet_ver">
          <label class="field"><span>Tutar *</span><input name="senet_tutar" inputmode="decimal" required placeholder="0,00" value="<?= $balance > 0.009 ? e(number_format($balance, 2, ',', '.')) : '' ?>"></label>
          <label class="field"><span>Vade *</span><input type="date" name="senet_vade" required min="<?= date('Y-m-d') ?>"></label>
          <label class="field"><span>Senet no</span><input name="senet_no" maxlength="40"></label>
          <label class="field"><span>Düzenleme tarihi</span><input type="date" name="senet_duzenleme" value="<?= date('Y-m-d') ?>"></label>
          <label class="field"><span>Fatura (opsiyonel)</span><select name="senet_fatura"><option value="">—</option><?php foreach ($acikFaturalar as $af): ?><option value="<?= (int) $af['id'] ?>"><?= e($af['invoice_no'] . ' · ' . date_tr((string) $af['invoice_date']) . ' · ' . money($af['amount'])) ?></option><?php endforeach; ?></select></label>
          <label class="field"><span>Not</span><input name="senet_not" maxlength="255"></label>
          <label class="field"><span>Düzenleme yeri (yazdırmak için)</span><input name="senet_duzenleme_yeri" maxlength="80" value="<?= e(setting('firma_il', '')) ?>"></label>
          <label class="field"><span>Ödeme yeri</span><input name="senet_odeme_yeri" maxlength="80" value="<?= e(setting('firma_il', '')) ?>"></label>
          <div class="form-actions"><button class="btn btn-primary">Senedi kaydet</button></div>
          <p class="hint span-all">Senet verilince cari aynı tutarda kapanır; borç "ödenmemiş senet" olarak izlenir. Vadeye <?= SENET_UYARI_GUN ?> gün kala uyarı gelir.</p>
        </form>
      </details>
    </section>
    <?php endif; ?>

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
        <?php if (array_key_exists('uts_kurum_no', $supplier)): ?><label class="field"><span>ÜTS kurum no <small class="muted">(ÜTS'de iade için)</small></span><input name="uts_kurum_no" inputmode="numeric" maxlength="20" value="<?= e($supplier['uts_kurum_no'] ?? '') ?>"></label><?php endif; ?>
        <label class="field"><span>Not</span><textarea name="note" rows="3"><?= e($supplier['note'] ?? '') ?></textarea></label>
        <button class="btn btn-block">Bilgileri kaydet</button>
      </form>
    </section>
  </div>
  <?php endif; ?>
</div>
<?php page_end();
