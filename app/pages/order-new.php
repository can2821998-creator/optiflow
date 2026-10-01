<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

$customer = null;
$customerId = is_post() ? post_int('customer_id') : query_int('customer_id');
if ($customerId > 0) {
    $customer = find_customer($customerId);
}

$quote = null;
$quoteId = is_post() ? post_int('quote_id') : query_int('quote_id');
if ($quoteId > 0) {
    $quote = row('SELECT * FROM quotes WHERE id = ? AND converted_order_id IS NULL', [$quoteId]);
    if ($quote && !$customer && $quote['customer_id']) {
        $customer = find_customer((int) $quote['customer_id']);
    }
}

if (is_post()) {
    $errors = [];

    // --- Müşteri ---
    if (!$customer) {
        $first = tr_title(post('first_name'));
        $last = tr_title(post('last_name'));
        $phone = normalize_phone(post('phone'));
        $birth = post('birth_year');
        if ($first === '' || $last === '') {
            $errors[] = 'Müşteri adı ve soyadı zorunlu.';
        }
        if ($phone === null) {
            $errors[] = 'Telefon numarası geçersiz. Örnek: 0532 111 22 33';
        }
        if ($birth !== '' && (!ctype_digit($birth) || (int) $birth < 1900 || (int) $birth > (int) date('Y'))) {
            $errors[] = 'Doğum yılı geçersiz.';
        }
    }

    // --- Sipariş ---
    $stage = post('order_stage', 'siparis_verildi');
    if (!isset(stages()[$stage]) || $stage === 'iptal') {
        $errors[] = 'Sipariş durumu geçersiz.';
    }
    $txType = post('transaction_type', 'gozluk');
    if (!isset(transaction_types()[$txType])) {
        $txType = 'gozluk';
    }
    $serviceType = $txType === 'tamir' ? post('service_type') : null;
    if ($serviceType !== null && $serviceType !== '' && !isset(service_types()[$serviceType])) {
        $errors[] = 'Yapılan işlem türü geçersiz.';
    }
    $isFree = $txType === 'tamir' && post('is_free') === '1';
    $lens = post('lens_type');
    if ($txType === 'tamir') {
        $lens = '';
    }
    if (!valid_lens_type($lens)) {
        $errors[] = 'Cam tipi listede yok.';
    }
    $promised = post('promised_date');
    if ($promised !== '' && !valid_date($promised)) {
        $errors[] = 'Teslim tarihi geçersiz.';
    }
    $orderDate = post('order_date') ?: date('Y-m-d');
    if (!valid_date($orderDate) || $orderDate > date('Y-m-d')) {
        $errors[] = 'Sipariş tarihi geçersiz (ileri tarih olamaz).';
    }
    $amount = $isFree ? 0.0 : (can_see_amounts() ? parse_money(post('total_amount')) : 0.0);
    if ($amount === null || $amount < 0) {
        $errors[] = 'Sipariş tutarı geçersiz. Örnek: 4.250,00';
    }
    $deposit = can_take_payments() ? parse_money(post('deposit')) : 0.0;
    $method = post('deposit_method', 'nakit');
    if ($deposit === null || $deposit < 0) {
        $errors[] = 'Kapora tutarı geçersiz.';
    } elseif ($amount !== null && $deposit > $amount + 0.001) {
        $errors[] = 'Kapora sipariş tutarından büyük olamaz.';
    }
    if (!isset(payment_methods()[$method])) {
        $method = 'nakit';
    }

    if ($errors) {
        remember_input();
        foreach ($errors as $err) {
            flash($err, 'error');
        }
        redirect('order-new.php' . ($customer ? '?customer_id=' . (int) $customer['id'] : ''));
    }

    $orderId = transaction(function () use ($customer, $user, $stage, $txType, $serviceType, $isFree, $lens, $promised, $amount, $deposit, $method, $orderDate) {
        $createdAt = $orderDate === date('Y-m-d') ? date('Y-m-d H:i:s') : $orderDate . ' 12:00:00';
        if ($customer) {
            $cid = (int) $customer['id'];
        } else {
            $first = tr_title(post('first_name'));
            $last = tr_title(post('last_name'));
            $phone = (string) normalize_phone(post('phone'));
            // Aynı telefon + aynı ad soyad zaten kayıtlıysa yeni müşteri açma.
            $cid = $phone !== '' ? (int) scalar('SELECT id FROM customers WHERE phone = ? AND first_name = ? AND last_name = ? LIMIT 1', [$phone, $first, $last]) : 0;
            if (!$cid) {
                $cid = insert('customers', [
                    'first_name' => $first,
                    'last_name'  => $last,
                    'phone'      => $phone,
                    'birth_year' => post('birth_year') !== '' ? (int) post('birth_year') : null,
                    'created_by' => $user['id'],
                    'created_at' => $createdAt,
                ]);
                audit('customer_create', 'customer', $cid, ['ad' => "$first $last"]);
            }
        }
        $c = find_customer($cid);
        $id = insert('orders', [
            'customer_id'   => $cid,
            'first_name'    => $c['first_name'],
            'last_name'     => $c['last_name'],
            'phone'         => $c['phone'],
            'total_amount'  => $amount,
            'status'        => $stage,
            'order_stage'   => $stage,
            'transaction_type' => $txType,
            'service_type'  => $serviceType ?: null,
            'is_free'       => $isFree ? 1 : 0,
            'lens_type'     => $lens,
            'frame_info'    => mb_substr(post('frame_info'), 0, 255),
            'notes'         => mb_substr(post('notes'), 0, 2000) ?: null,
            'promised_date' => $promised ?: null,
            'sales_person'  => $user['full_name'],
            'created_by'    => $user['id'],
            'created_at'    => $createdAt,
            'delivered_at'  => $stage === 'teslim_edildi' ? $createdAt : null,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        audit('order_create', 'order', $id, ['müşteri' => $c['first_name'] . ' ' . $c['last_name'], 'tutar' => $amount]);
        // Stoktan çerçeve seçildiyse vitrinden düş
        $stokCerceve = post_int('frame_item_id');
        if ($stokCerceve > 0) {
            order_set_frame_item($id, $stokCerceve, null);
            if (trim((string) post('frame_info')) === '') {
                $sc = row('SELECT * FROM frame_items WHERE id = ?', [$stokCerceve]);
                if ($sc) {
                    q('UPDATE orders SET frame_info = ? WHERE id = ?', [mb_substr(frame_item_label($sc), 0, 255), $id]);
                }
            }
        }
        if ($deposit > 0) {
            $pid = insert('payments', ['order_id' => $id, 'amount' => $deposit, 'method' => $method, 'note' => 'Kapora', 'created_by' => $user['id'], 'created_at' => $createdAt]);
            audit('payment_create', 'order', $id, ['tutar' => $deposit, 'yöntem' => $method, 'ödeme' => $pid]);
        }
        return $id;
    });

    $todayCount = (int) scalar("SELECT COUNT(*) FROM orders WHERE created_by = ? AND DATE(created_at) = CURDATE()", [$user['id']]);
    flash('Sipariş ' . order_no($orderId) . ' oluşturuldu. 🎉 Bugünkü ' . $todayCount . '. siparişiniz!');
    if ($quote) {
        update('quotes', ['converted_order_id' => $orderId], 'id = ?', [$quote['id']]);
    }
    $avgAmount = (float) scalar("SELECT AVG(total_amount) FROM orders WHERE order_stage <> 'iptal' AND created_at >= CURDATE() - INTERVAL 60 DAY AND id <> ?", [$orderId]);
    $isCritical = $avgAmount > 0 && $amount >= max(5000, $avgAmount * 2.2);
    $celebrateSuffix = '&yeni=1' . ($isCritical ? '&kritik=1' : '');
    redirect((post('next') === 'rx' ? 'rx.php?order_id=' . $orderId : 'order.php?id=' . $orderId) . $celebrateSuffix);
}

$lensTypes = lens_type_options();
page_start('Yeni sipariş', 'orders');
page_header('Yeni sipariş', 'Müşteriyi seçin veya ekleyin, ardından sipariş bilgilerini girin.', '', 'index.php', 'Yeni kayıt');
?>
<?php if ($quote): ?>
  <div class="alert alert-info">
    <b>“<?= e($quote['customer_name']) ?>” teklifinden dönüştürülüyor.</b>
    <?php foreach ([1, 2, 3] as $i): if ($quote["opt{$i}_name"]): ?>
      <span> · <?= e($quote["opt{$i}_name"]) ?><?= $quote["opt{$i}_price"] !== null ? ' (' . money($quote["opt{$i}_price"]) . ')' : '' ?></span>
    <?php endif; endforeach; ?>
    <span> — hangisi seçildiyse tutarı aşağıya siz girin.</span>
  </div>
<?php endif; ?>
<form method="post" class="form-layout" data-guard>
  <?= csrf_field() ?>
  <?php if ($quote): ?><input type="hidden" name="quote_id" value="<?= (int) $quote['id'] ?>"><?php endif; ?>
  <section class="card">
    <div class="card-head"><h2><span class="step">1</span> Müşteri</h2></div>

    <div class="customer-picker" data-customer-picker <?= $customer ? 'data-selected' : '' ?>>
      <input type="hidden" name="customer_id" value="<?= $customer ? (int) $customer['id'] : '' ?>" data-customer-id>
      <div class="picked" <?= $customer ? '' : 'hidden' ?> data-picked>
        <span class="avatar"><?= $customer ? e(initials($customer['first_name'], $customer['last_name'])) : '' ?></span>
        <span class="cell-main"><b data-picked-name><?= $customer ? e($customer['first_name'] . ' ' . $customer['last_name']) : '' ?></b>
          <small data-picked-meta><?= $customer ? e(phone_display($customer['phone']) ?: 'Telefon yok') : '' ?></small></span>
        <button type="button" class="btn btn-ghost btn-sm" data-unpick>Değiştir</button>
      </div>

      <div data-picker-body <?= $customer ? 'hidden' : '' ?>>
        <label class="field search-field">
          <span>Kayıtlı müşteri ara</span>
          <input type="search" placeholder="Ad soyad veya telefon" autocomplete="off" data-customer-search>
        </label>
        <ul class="suggest" data-suggest hidden></ul>
        <div class="divider"><span>veya yeni müşteri</span></div>
        <div class="grid cols-4">
          <label class="field"><span>Ad *</span><input name="first_name" value="<?= e(old('first_name', $quote ? explode(' ', $quote['customer_name'], 2)[0] : '')) ?>" autocomplete="off" data-new-required></label>
          <label class="field"><span>Soyad *</span><input name="last_name" value="<?= e(old('last_name', $quote ? (explode(' ', $quote['customer_name'], 2)[1] ?? '') : '')) ?>" autocomplete="off" data-new-required></label>
          <label class="field"><span>Telefon</span><input name="phone" type="tel" inputmode="tel" value="<?= e(old('phone', $quote['customer_phone'] ?? '')) ?>" placeholder="05XX XXX XX XX"></label>
          <label class="field"><span>Doğum yılı</span><input name="birth_year" inputmode="numeric" maxlength="4" value="<?= e(old('birth_year')) ?>" placeholder="Öneri asistanı için"></label>
        </div>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2><span class="step">2</span> İşlem</h2></div>
    <div class="chip-group" role="radiogroup" aria-label="İşlem türü">
      <?php foreach (transaction_types() as $tk => $tl): ?>
        <label class="chip <?= old('transaction_type', 'gozluk') === $tk ? 'active' : '' ?>">
          <input type="radio" name="transaction_type" value="<?= e($tk) ?>" <?= old('transaction_type', 'gozluk') === $tk ? 'checked' : '' ?> style="position:absolute;opacity:0"> <?= e($tl) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <div class="grid cols-3" style="margin-top:16px">
      <label class="field" data-show-for="gozluk,gunes_gozlugu"><span>Cam tipi</span>
        <select name="lens_type"><option value="">Reçetede seçilecek / gerekmiyor</option><?= select_options($lensTypes, old('lens_type'), false) ?></select>
      </label>
      <label class="field" data-show-for="tamir"><span>Yapılan işlem</span>
        <select name="service_type"><option value="">Seçiniz</option><?= select_options(service_types(), old('service_type')) ?></select>
      </label>
      <label class="field"><span>Durum</span>
        <select name="order_stage"><?= select_options(array_diff_key(stages(), ['iptal' => 1]), old('order_stage', 'siparis_verildi')) ?></select>
      </label>
      <label class="field"><span>Sipariş tarihi</span><input type="date" name="order_date" value="<?= e(old('order_date', date('Y-m-d'))) ?>" max="<?= date('Y-m-d') ?>"></label>
      <label class="field"><span>Söz verilen teslim</span><input type="date" name="promised_date" value="<?= e(old('promised_date')) ?>"></label>
      <label class="field span-2" data-label-for="frame_info"><span>Çerçeve / ürün / işlem açıklaması</span><input name="frame_info" value="<?= e(old('frame_info')) ?>" placeholder="örn. Ray-Ban 5154 52□21 — ya da 'Sağ sap vidası sıkıldı' — ya da 'Uvex güneş gözlüğü'"></label>
      <label class="field span-2" data-show-for="gozluk,gunes_gozlugu"><span>Stoktaki çerçeve <small class="muted">(seçince vitrin stoğundan düşer)</small></span>
        <select name="frame_item_id">
          <option value="">— müşterinin kendi çerçevesi / stok dışı —</option>
          <?= select_options(frame_stock_options(), old('frame_item_id')) ?>
        </select>
      </label>
      <?php if (can_see_amounts()): ?>
        <label class="field" data-show-for="tamir">
          <span>&nbsp;</span>
          <span class="check-field" style="min-height:46px"><input type="checkbox" name="is_free" value="1" <?= old('is_free') ? 'checked' : '' ?> data-free-toggle> Ücretsiz işlem</span>
        </label>
        <label class="field"><span>Sipariş tutarı <span data-amount-required>*</span></span><input name="total_amount" inputmode="decimal" value="<?= e(old('total_amount')) ?>" placeholder="0,00" data-money data-amount-field></label>
      <?php endif; ?>
      <?php if (can_take_payments()): ?>
        <label class="field" data-show-for="gozluk,gunes_gozlugu,tamir"><span>Kapora / ön ödeme</span><input name="deposit" inputmode="decimal" value="<?= e(old('deposit')) ?>" placeholder="0,00" data-money></label>
        <label class="field"><span>Ödeme yöntemi</span><select name="deposit_method"><?= select_options(payment_methods(), old('deposit_method', 'nakit')) ?></select></label>
      <?php endif; ?>
      <label class="field span-all"><span>Not</span><textarea name="notes" rows="2" placeholder="Atölye için notlar"><?= e(old('notes')) ?></textarea></label>
    </div>
  </section>

  <div class="form-actions sticky-actions">
    <a class="btn btn-ghost" href="index.php">Vazgeç</a>
    <button class="btn" name="next" value="order">Kaydet</button>
    <button class="btn btn-primary" name="next" value="rx" data-show-for="gozluk"><?= icon('glasses') ?> Kaydet ve reçete ekle</button>
  </div>
</form>
<?php /* 4.15.1: işlem türü alanları assets/moduller.js içinde (CSP) */ ?>
<?php page_end();
