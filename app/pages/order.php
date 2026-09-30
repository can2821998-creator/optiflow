<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

$id = is_post() ? post_int('order_id') : query_int('id');
$order = find_order($id);
if (!$order) {
    http_response_code(404);
    render_error_page('Sipariş bulunamadı', 'Silinmiş veya hatalı bir bağlantı olabilir.');
}
$self = 'order.php?id=' . $id;

if (is_post()) {
    $action = post('action');

    if ($action === 'set_stage') {
        $stage = post('stage');
        if (!isset(stages()[$stage]) || ($stage === 'iptal' && !is_super())) {
            flash('Bu durum değişikliği yapılamaz.', 'error');
            redirect($self);
        }
        $actorId = post_int('actor_id') ?: $user['id'];
        $actorName = staff_name($actorId);
        if ($stage !== $order['order_stage']) {
            update('orders', [
                'order_stage'    => $stage,
                'status'         => $stage,
                'workshop_stage' => $stage === 'atolyede' ? ($order['workshop_stage'] ?: 'montajda') : null,
                'assigned_to'    => $stage === 'atolyede' ? $actorId : $order['assigned_to'],
                'qc_by'          => $stage === 'hazirlandi' ? $actorId : $order['qc_by'],
                'delivered_at'   => $stage === 'teslim_edildi' ? date('Y-m-d H:i:s') : null,
                'delivered_by'   => $stage === 'teslim_edildi' ? $actorId : $order['delivered_by'],
                'balance_promise_date' => $stage === 'teslim_edildi' && (float) $order['balance'] > 0.009 ? (post('balance_promise_date') ?: null) : null,
                'updated_at'     => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            audit('order_stage', 'order', $id, ['önce' => stage_label($order['order_stage']), 'sonra' => stage_label($stage), 'işlemi yapan' => $actorName]);
            // Sipariş iptal edilirse stoktan düşülen çerçeve vitrine geri döner
            if ($stage === 'iptal' && $order['frame_item_id']) {
                frame_move((int) $order['frame_item_id'], +1, 'iade', $id, 'Sipariş iptal edildi');
                q('UPDATE orders SET frame_item_id = NULL WHERE id = ?', [$id]);
            }
            push_order_event($id, match ($stage) {
                'hazirlandi'    => 'hazir',
                'teslim_edildi' => 'teslim',
                'atolyede'      => 'atolyede',
                default         => 'guncellendi',
            });
            flash('Durum “' . stage_label($stage) . '” olarak güncellendi — işlemi yapan: ' . $actorName . '.');
            if ($stage === 'teslim_edildi' && (float) $order['balance'] > 0.009 && can_see_amounts()) {
                flash('Dikkat: teslim edilen siparişte ' . money($order['balance']) . ' kalan bakiye var.', 'warn');
            }
            if ($stage === 'hazirlandi') {
                $missing = (int) scalar("SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE r.order_id = ? AND i.stock_status <> 'stokta_var'", [$id]);
                if ($missing) {
                    flash("Dikkat: bu siparişte henüz gelmemiş $missing cam görünüyor.", 'warn');
                }
            }
        }
        $celebrateFlag = $stage === 'teslim_edildi' && $stage !== $order['order_stage'] ? '&teslim=1' : '';
        redirect((post('return') === 'pano' ? 'workshop.php' : $self) . $celebrateFlag);
    }

    if ($action === 'workshop_move') {
        $to = post('to');
        $valid = ['montajda', 'kontrol', 'hazir'];
        if ($order['order_stage'] !== 'atolyede' || !in_array($to, $valid, true)) {
            flash('Bu işlem şu anda yapılamaz.', 'error');
            redirect($self);
        }
        if ($to === 'kontrol' && ($order['workshop_stage'] ?: 'montajda') !== 'montajda') {
            flash('Önce montajın tamamlanmış olması gerekir.', 'error');
            redirect($self);
        }
        if ($to === 'kontrol' && (int) $order['own_frame_pending'] === 1) {
            flash('Müşterinin kendi çerçevesi henüz gelmedi — kontrole gönderilemez.', 'error');
            redirect($self);
        }
        if ($to === 'hazir') {
            if (($order['workshop_stage'] ?? '') !== 'kontrol') {
                flash('Hazır işaretlemeden önce kalite kontrolden geçmesi gerekir.', 'error');
                redirect($self);
            }
            update('orders', [
                'order_stage'    => 'hazirlandi',
                'status'         => 'hazirlandi',
                'workshop_stage' => null,
                'qc_by'          => $user['id'],
                'qc_at'          => date('Y-m-d H:i:s'),
                'updated_at'     => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
            audit('order_stage', 'order', $id, ['önce' => 'Atölyede · kalite kontrolde', 'sonra' => 'Hazır']);
            push_order_event($id, 'hazir');
            $missing = (int) scalar("SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE r.order_id = ? AND i.stock_status <> 'stokta_var'", [$id]);
            flash('Kalite kontrol tamamlandı, sipariş “Hazır” olarak işaretlendi.');
            if ($missing) {
                flash("Dikkat: bu siparişte henüz gelmemiş $missing cam görünüyor.", 'warn');
            }
        } else {
            update('orders', ['workshop_stage' => $to, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            audit('workshop_stage', 'order', $id, ['aşama' => workshop_stage_label($to)]);
            flash('Atölye aşaması “' . workshop_stage_label($to) . '” olarak güncellendi.');
        }
        redirect(post('return') === 'pano' ? 'workshop.php' : $self);
    }

    if ($action === 'workshop_assign') {
        if ($order['order_stage'] !== 'atolyede') {
            flash('Bu işlem şu anda yapılamaz.', 'error');
            redirect($self);
        }
        $staffId = post_int('assigned_to') ?: null;
        update('orders', ['assigned_to' => $staffId, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        flash($staffId ? 'Sipariş bir personele atandı.' : 'Atama kaldırıldı.');
        redirect(post('return') === 'pano' ? 'workshop.php' : $self);
    }

    if ($action === 'maker_note') {
        if (!in_array($order['order_stage'], ['atolyede', 'hazirlandi'], true)) {
            flash('Not yalnızca atölyedeki veya hazır siparişlere yazılabilir.', 'error');
            redirect($self);
        }
        try {
            require_once dirname(__DIR__) . '/maker.php';
            maker_note_save((int) $id, (int) $user['id'], post('maker_note'));
            flash(trim(post('maker_note')) === '' ? 'Müşteri notu silindi.' : 'Not kaydedildi; müşterinin durum sayfasında görünecek.');
        } catch (Throwable $e) {
            flash('Not kaydedilemedi: ' . $e->getMessage(), 'error');
        }
        redirect($self);
    }

    if ($action === 'toggle_own_frame') {
        if ($order['order_stage'] !== 'atolyede') {
            flash('Bu işlem şu anda yapılamaz.', 'error');
            redirect($self);
        }
        $pending = post('pending') === '1' ? 1 : 0;
        update('orders', ['own_frame_pending' => $pending, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        audit('own_frame_pending', 'order', $id, ['durum' => $pending ? 'Çerçeve bekleniyor işaretlendi' : 'Çerçeve geldi, bekleme kaldırıldı']);
        flash($pending ? 'Sipariş “müşterinin çerçevesi bekleniyor” olarak işaretlendi.' : 'Çerçeve bekleme kaldırıldı, montaja devam edebilirsiniz.');
        redirect(post('return') === 'pano' ? 'workshop.php' : $self);
    }

    if ($action === 'set_promise' && can_take_payments()) {
        if ($order['order_stage'] !== 'teslim_edildi') {
            flash('Bu işlem yalnızca teslim edilmiş siparişlerde yapılabilir.', 'error');
            redirect($self);
        }
        $promiseDate = post('balance_promise_date') ?: null;
        if ($promiseDate !== null && !valid_date($promiseDate)) {
            flash('Tarih geçersiz.', 'error');
            redirect($self);
        }
        update('orders', ['balance_promise_date' => $promiseDate, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        audit('balance_promise', 'order', $id, ['tarih' => $promiseDate ?: 'Kaldırıldı']);
        flash($promiseDate ? 'Ödeme sözü tarihi ' . date_tr($promiseDate) . ' olarak kaydedildi.' : 'Ödeme sözü tarihi kaldırıldı.');
        redirect($self);
    }

    if ($action === 'update_order') {
        $errors = [];
        $lens = post('lens_type');
        $promised = post('promised_date');
        if (!valid_lens_type($lens)) {
            $errors[] = 'Cam tipi listede yok.';
        }
        if ($promised !== '' && !valid_date($promised)) {
            $errors[] = 'Teslim tarihi geçersiz.';
        }
        $data = [
            'lens_type'        => $order['transaction_type'] === 'tamir' ? $order['lens_type'] : $lens,
            'service_type'     => $order['transaction_type'] === 'tamir' ? (post('service_type') ?: null) : $order['service_type'],
            'promised_date'    => $promised ?: null,
            'frame_info'       => mb_substr(post('frame_info'), 0, 255),
            'frame_product_id' => post_int('frame_product_id') ?: null,
            'notes'            => mb_substr(post('notes'), 0, 2000) ?: null,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];
        $newBrand = mb_substr(trim(post('new_frame_brand')), 0, 80);
        if ($newBrand !== '') {
            $data['frame_product_id'] = find_or_create_frame_brand($newBrand, post('new_frame_model'));
        }
        // Stoktan seçilen çerçeve: açıklama boşsa ondan doldur
        $stokCerceve = post_int('frame_item_id') ?: null;
        if ($stokCerceve && trim((string) $data['frame_info']) === '') {
            $sc = row('SELECT * FROM frame_items WHERE id = ?', [$stokCerceve]);
            if ($sc) {
                $data['frame_info'] = mb_substr(frame_item_label($sc), 0, 255);
            }
        }
        if (is_super() && isset($_POST['total_amount'])) {
            $amount = parse_money(post('total_amount'));
            if ($amount === null || $amount < 0) {
                $errors[] = 'Tutar geçersiz. Örnek: 4.250,00';
            } elseif ($amount + 0.001 < (float) $order['paid']) {
                $errors[] = 'Tutar, alınan ödemelerden (' . money($order['paid']) . ') az olamaz.';
            } else {
                $data['total_amount'] = $amount;
            }
        }
        if (is_super() && isset($_POST['sgk_amount'])) {
            $sgkTutar = parse_money(post('sgk_amount'));
            if ($sgkTutar === null || $sgkTutar < 0) {
                $errors[] = 'SGK katkısı geçersiz. Örnek: 150,00';
            } else {
                $data['sgk_amount'] = $sgkTutar;
            }
        }
        if (!$errors && (isset($data['total_amount']) || isset($data['sgk_amount']))) {
            $yeniTutar = (float) ($data['total_amount'] ?? $order['total_amount']);
            $yeniSgk = (float) ($data['sgk_amount'] ?? $order['sgk_amount']);
            if ($yeniTutar + 0.001 < $yeniSgk + (float) $order['paid']) {
                $errors[] = 'Sipariş tutarı, SGK katkısı ile alınan ödemelerin toplamından (' . money($yeniSgk + (float) $order['paid']) . ') az olamaz.';
            }
        }
        if ($errors) {
            foreach ($errors as $err) {
                flash($err, 'error');
            }
            redirect($self);
        }
        $changes = [];
        foreach ($data as $k => $v) {
            if ($k !== 'updated_at' && (string) $order[$k] !== (string) $v && !(in_array($k, ['total_amount', 'sgk_amount'], true) && abs((float) $order[$k] - (float) $v) < 0.001)) {
                $changes[$k] = ['önce' => $order[$k], 'sonra' => $v];
            }
        }
        update('orders', $data, 'id = ?', [$id]);
        order_set_frame_item($id, $stokCerceve, $order['frame_item_id'] !== null ? (int) $order['frame_item_id'] : null);
        if ($changes) {
            audit('order_update', 'order', $id, $changes);
        }
        flash('Sipariş bilgileri kaydedildi.');
        redirect($self);
    }

    if ($action === 'add_payment' && can_take_payments()) {
        $amount = parse_money(post('amount'));
        $date = post('payment_date', date('Y-m-d'));
        $method = post('method', 'nakit');
        $balance = (float) $order['balance'];
        if ($amount === null || $amount <= 0) {
            flash('Tahsilat tutarı geçersiz. Örnek: 1.500,00', 'error');
        } elseif ($amount > $balance + 0.001) {
            flash('Tahsilat, kalan bakiyeden (' . money($balance) . ') büyük olamaz.', 'error');
        } elseif (!valid_date($date) || $date > date('Y-m-d')) {
            flash('Tahsilat tarihi geçersiz (ileri tarih olamaz).', 'error');
        } elseif (!isset(payment_methods()[$method])) {
            flash('Ödeme yöntemi geçersiz.', 'error');
        } else {
            $time = $date === date('Y-m-d') ? date('H:i:s') : '12:00:00';
            $pid = insert('payments', [
                'order_id'   => $id,
                'amount'     => $amount,
                'method'     => $method,
                'note'       => mb_substr(post('note'), 0, 255),
                'created_at' => "$date $time",
                'created_by' => $user['id'],
            ]);
            audit('payment_create', 'order', $id, ['tutar' => $amount, 'yöntem' => payment_methods()[$method], 'ödeme' => $pid]);
            flash(money($amount) . ' tahsilat kaydedildi.');
        }
        redirect($self . '#odeme');
    }

    if ($action === 'delete_payment' && is_super()) {
        $p = row('SELECT * FROM payments WHERE id = ? AND order_id = ?', [post_int('payment_id'), $id]);
        if ($p) {
            q('DELETE FROM payments WHERE id = ?', [$p['id']]);
            audit('payment_delete', 'order', $id, ['tutar' => $p['amount'], 'tarih' => $p['created_at'], 'not' => $p['note']]);
            flash(money($p['amount']) . ' tutarındaki tahsilat silindi.', 'info');
        }
        redirect($self . '#odeme');
    }

    if ($action === 'delete_rx' && is_super()) {
        $rx = row('SELECT * FROM prescription_records WHERE id = ? AND order_id = ?', [post_int('rx_id'), $id]);
        if ($rx) {
            q('DELETE FROM prescription_records WHERE id = ?', [$rx['id']]);
            audit('rx_delete', 'order', $id, ['reçete' => $rx['id'], 'tarih' => $rx['prescription_date'], 'sağ' => rx_line($rx, 'right'), 'sol' => rx_line($rx, 'left')]);
            flash('Reçete silindi.', 'info');
        }
        redirect($self);
    }

    if ($action === 'delete_order' && is_super()) {
        audit('order_delete', 'order', $id, [
            'müşteri' => $order['c_first'] . ' ' . $order['c_last'],
            'tutar'   => $order['total_amount'],
            'durum'   => stage_label($order['order_stage']),
            'not'     => mb_substr(trim(post('reason')), 0, 255),
        ]);
        q('DELETE FROM orders WHERE id = ?', [$id]);
        flash(order_no($id) . ' numaralı sipariş kalıcı olarak silindi.', 'info');
        redirect('index.php');
    }

    flash('İşlem yapılamadı veya yetkiniz yok.', 'error');
    redirect($self);
}

/* ---------- Görünüm verisi ---------- */
$payments = can_see_amounts()
    ? rows('SELECT p.*, u.full_name AS by_name FROM payments p LEFT JOIN user_accounts u ON u.id = p.created_by WHERE p.order_id = ? ORDER BY p.created_at DESC, p.id DESC', [$id])
    : [];
$rxList = rows(
    'SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph, pr.brand AS product_brand, pr.name AS product_name
     FROM prescription_records r
     LEFT JOIN near_prescription_details n ON n.prescription_id = r.id
     LEFT JOIN lens_products pr ON pr.id = r.advisor_product_id
     WHERE r.order_id = ? ORDER BY r.prescription_date DESC, r.id DESC',
    [$id]
);
$items = [];
$lensCostKnown = 0.0;
$lensCostMissing = 0;
foreach (rows('SELECT i.* FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE r.order_id = ? ORDER BY i.prescription_id, i.lens_no', [$id]) as $it) {
    $items[(int) $it['prescription_id']][] = $it;
    if ($it['unit_cost'] !== null) {
        $lensCostKnown += (float) $it['unit_cost'];
    } else {
        $lensCostMissing++;
    }
}
$frameCost = $order['frame_product_id'] ? (float) (scalar('SELECT avg_cost FROM frame_products WHERE id = ?', [$order['frame_product_id']]) ?? 0) : 0.0;
$hasFrameCost = $order['frame_product_id'] && $frameCost > 0;
$estProfit = (float) $order['total_amount'] - $lensCostKnown - $frameCost;
$otherOrders = rows('SELECT id, order_stage, created_at, total_amount FROM orders WHERE customer_id = ? AND id <> ? ORDER BY created_at DESC LIMIT 5', [$order['customer_id'], $id]);
$history = is_super() ? rows("SELECT * FROM audit_log WHERE entity = 'order' AND entity_id = ? ORDER BY id DESC LIMIT 12", [$id]) : [];
$staff = $order['order_stage'] === 'atolyede' ? assignable_staff() : [];
$wa = whatsapp_links($order);

/* Sipariş hazırsa: "Müşteriye haber ver" — "hazır" şablonu (yoksa yerleşik metin) + müşteri durum bağlantısı */
/* Ustanın müşteriye notu (yalnızca atölyedeki/hazır siparişlerde); modül yüklenemezse bölüm gizlenir */
$ustaNotuGoster = in_array($order['order_stage'], ['atolyede', 'hazirlandi'], true);
$ustaNotu = '';
if ($ustaNotuGoster) {
    try {
        require_once dirname(__DIR__) . '/maker.php';
        $mn = maker_note_get((int) $id);
        $ustaNotu = $mn ? $mn['metin'] : '';
    } catch (Throwable $e) {
        $ustaNotuGoster = false;
    }
}

$haberVer = null;
if ($order['order_stage'] === 'hazirlandi' && $wa) {
    $haberMetin = '';
    $haberAd = 'Sipariş hazır';
    foreach ($wa as $w) {
        if (mb_stripos((string) $w['name'], 'hazır') !== false) {
            $haberMetin = (string) $w['text'];
            $haberAd = (string) $w['name'];
            break;
        }
    }
    if ($haberMetin === '') {
        $haberMetin = 'Merhaba ' . $order['c_first'] . ', ' . order_no((int) $id) . ' numaralı gözlüğünüz hazır. Dilediğiniz zaman ' . setting('shop_name', 'OptiFlow') . "'dan teslim alabilirsiniz.";
    }
    $haberLink = order_track_url((int) $id);
    if ($haberLink !== '' && !str_contains($haberMetin, $haberLink)) {
        $haberMetin .= "\n\nSiparişinizin durumunu buradan görebilirsiniz: " . $haberLink;
    }
    $haberVer = [
        'url'  => 'https://wa.me/' . (string) $order['c_phone'] . '?text=' . rawurlencode($haberMetin),
        'ad'   => $haberAd,
        'metin' => $haberMetin,
    ];
}
$name = $order['c_first'] . ' ' . $order['c_last'];
$paidPct = (float) $order['total_amount'] > 0 ? min(100, round(((float) $order['paid'] + (float) $order['sgk_amount']) / (float) $order['total_amount'] * 100)) : 0;
$stageKeys = array_keys(stages());
$flow = $order['transaction_type'] === 'gozluk'
    ? ['siparis_verildi', 'rx_siparis_verildi', 'atolyede', 'hazirlandi', 'teslim_edildi']
    : ['siparis_verildi', 'atolyede', 'hazirlandi', 'teslim_edildi'];
$displayStage = in_array($order['order_stage'], $flow, true)
    ? $order['order_stage']
    : (in_array($order['order_stage'], ['bekliyor', 'rx_siparis_verildi'], true) ? 'siparis_verildi' : $order['order_stage']);

page_start($name . ' ' . order_no($id), 'orders');
?>
<a class="back-link" href="index.php"><?= icon('arrow-left') ?> Siparişler</a>

<section class="hero" <?= query('kritik') === '1' ? 'data-critical-sale' : ((query('teslim') === '1' || query('yeni') === '1') ? 'data-celebrate' : '') ?>>
  <div class="hero-main">
    <span class="avatar lg"><?= e(initials($order['c_first'], $order['c_last'])) ?></span>
    <div>
      <small class="eyebrow">Sipariş <?= order_no($id) ?> · <?= date_tr($order['created_at'], true) ?><?= $order['created_by_name'] ? ' · ' . e($order['created_by_name']) : '' ?></small>
      <h1><a href="customer.php?id=<?= (int) $order['customer_id'] ?>"><?= e($name) ?></a></h1>
      <p><span class="badge sm tone-gray"><?= e(transaction_type_label($order['transaction_type'])) ?><?= $order['transaction_type'] === 'tamir' && $order['service_type'] ? ' · ' . e(service_type_label($order['service_type'])) : '' ?></span> <?php if ((int) $order['is_free']): ?><span class="badge sm tone-green">Ücretsiz</span> <?php endif; ?><?= $order['c_phone'] ? e(phone_display($order['c_phone'])) : 'Telefon girilmemiş' ?> · <?= stage_badge($order['order_stage']) ?>
        <?php if ($order['promised_date']): ?> · Teslim: <b><?= date_tr($order['promised_date']) ?></b><?php endif; ?></p>
    </div>
  </div>
  <div class="hero-actions">
    <?php if ($wa): ?>
      <details class="menu">
        <summary class="btn btn-wa"><?= icon('chat') ?> WhatsApp</summary>
        <div class="menu-list">
          <?php foreach ($wa as $w): ?>
            <a href="<?= e($w['url']) ?>" target="_blank" rel="noopener" data-wa-log data-order="<?= $id ?>" data-template="<?= e($w['name']) ?>" title="<?= e($w['text']) ?>">
              <b><?= e($w['name']) ?></b><small><?= e(mb_strimwidth($w['text'], 0, 90, '…')) ?></small></a>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endif; ?>
    <a class="btn" href="print.php?type=order&id=<?= $id ?>" target="_blank"><?= icon('print') ?> Fiş</a>
    <a class="btn" href="customer.php?id=<?= (int) $order['customer_id'] ?>"><?= icon('user') ?> Müşteri kartı</a>
    <?php if (ozellik_acik('efatura') && can_see_amounts() && $order['order_stage'] !== 'iptal'):
      $faturaVar = (int) scalar("SELECT id FROM faturalar WHERE order_id = ? AND durum <> 'iptal' ORDER BY id LIMIT 1", [$id]); ?>
      <?php if ($faturaVar): ?>
        <a class="btn" href="fatura.php?id=<?= $faturaVar ?>"><?= icon('receipt') ?> Fatura</a>
      <?php else: ?>
        <form method="post" action="faturalar.php" style="display:inline"><?= csrf_field() ?><input type="hidden" name="eylem" value="siparisten"><input type="hidden" name="siparis" value="<?= $id ?>"><input type="hidden" name="sgk_ayri" value="1">
          <button class="btn"><?= icon('receipt') ?> Fatura taslağı</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</section>

<?php $waKuyrukta = $haberVer && ozellik_acik('whatsapp') ? row("SELECT durum FROM wa_mesajlar WHERE tekil = ?", ['hazir:o' . $id]) : null; ?>
<?php if ($waKuyrukta): ?>
  <div class="alert alert-ok"><span><b>Gözlük hazır.</b> Müşteri mesajı WhatsApp kuyruğunda: <?= e(wa_durum_etiketi((string) $waKuyrukta['durum'])[0]) ?>.</span>
    <a class="btn btn-sm" href="mesajlar.php"><?= icon('chat') ?> Kuyruğu aç</a></div>
<?php elseif ($haberVer): ?>
  <div class="alert alert-ok">
    <span><b>Gözlük hazır.</b> Müşteriye haber verin; mesaja durum sayfası bağlantısı da eklenir.</span>
    <a class="btn btn-wa btn-sm" href="<?= e($haberVer['url']) ?>" target="_blank" rel="noopener" data-wa-log data-order="<?= $id ?>" data-template="<?= e($haberVer['ad']) ?>" title="<?= e($haberVer['metin']) ?>"><?= icon('chat') ?> Müşteriye haber ver</a>
  </div>
<?php endif; ?>

<?php if ($ustaNotuGoster): ?>
<section class="card">
  <form method="post" class="stack">
    <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="maker_note">
    <label class="field"><span>Müşteriye kısa not (isteğe bağlı)</span>
      <textarea name="maker_note" rows="2" maxlength="240" placeholder="Örn. Camlarınız çok güzel oturdu. Sapları ilk günlerde sıkı gelirse uğrayın."><?= e($ustaNotu) ?></textarea>
      <small class="muted">Müşterinin durum sayfasında adınızla ("— Ali") görünür. Adınızın görünmesi için Profilim'den izin vermiş olmanız gerekir; aksi hâlde "Atölye ekibi" yazar. Boş bırakıp kaydederseniz not silinir.</small>
    </label>
    <div class="form-actions"><button class="btn btn-sm">Notu kaydet</button></div>
  </form>
</section>
<?php endif; ?>

<?php if ($order['order_stage'] !== 'iptal'): ?>
<div class="stepper2-wrap">
  <div class="stepper2">
    <?php $currentIdx = array_search($displayStage, $flow, true);
    foreach ($flow as $i => $st):
        $state = $displayStage === $st ? 'current' : ($currentIdx !== false && $i < $currentIdx ? 'done' : 'upcoming'); ?>
      <div class="step2 <?= $state ?>" <?= $state === 'current' ? 'aria-current="step"' : '' ?>>
        <span class="step2-dot"><?= $state === 'done' ? icon('check') : $i + 1 ?></span>
        <span class="step2-label"><?= e(stage_label($st)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<form method="post" class="card stage-changer">
  <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="set_stage">
  <div class="card-head"><h2>Durumu değiştir</h2><span class="muted small">Her değişiklikte kimin yaptığı kaydedilir ve fişte görünür.</span></div>
  <div class="grid cols-3">
    <label class="field"><span>Yeni durum</span>
      <select name="stage" required>
        <?= select_options(array_diff_key(stages(), ['iptal' => 1]), $order['order_stage']) ?>
      </select>
    </label>
    <label class="field"><span>Kim yaptı *</span>
      <select name="actor_id" required><?= select_options(array_column(assignable_staff(), 'full_name', 'id'), (string) $user['id']) ?></select>
    </label>
    <?php if (can_see_amounts() && (float) $order['balance'] > 0.009): ?>
      <label class="field"><span>Bakiye ödeme sözü <small>(teslim edildi seçilirse)</small></span>
        <input type="date" name="balance_promise_date" value="<?= e($order['balance_promise_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>">
      </label>
    <?php endif; ?>
  </div>
  <button class="btn btn-primary">Uygula</button>
</form>
<?php else: ?>
  <div class="alert alert-error">Bu sipariş iptal edildi.
    <?php if (is_super()): ?>
      <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="set_stage"><button class="btn btn-sm" name="stage" value="siparis_verildi">Siparişi geri al</button></form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($order['order_stage'] === 'atolyede'):
    $wstage = $order['workshop_stage'] ?: 'montajda';
    $framePending = (int) $order['own_frame_pending'] === 1;
    $waitDays = days_since($order['created_at']);
    $overdue = days_overdue($order['promised_date']); ?>
<section class="card workshop-card">
  <div class="card-head">
    <h2><?= icon('glasses') ?> Atölye durumu</h2>
    <?= workshop_stage_badge($wstage) ?>
  </div>

  <?php if ($framePending): ?>
    <div class="alert alert-warn frame-alert">
      <b>⚠ Çerçeve bekleniyor</b> — müşteri montaj için kendi çerçevesini henüz getirmedi.
      <div class="frame-alert-dates">
        <span>Sipariş tarihi: <b><?= date_tr($order['created_at']) ?></b> (<?= $waitDays ?> gündür)</span>
        <?php if ($order['promised_date']): ?>
          <span>Söz verilen teslim: <b><?= date_tr($order['promised_date']) ?></b><?= $overdue > 0 ? ' — <b class="text-red">' . $overdue . ' gün gecikti</b>' : '' ?></span>
        <?php endif; ?>
      </div>
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="toggle_own_frame"><input type="hidden" name="pending" value="0">
        <button class="btn btn-primary btn-sm">Çerçeve geldi, montaja devam</button>
      </form>
    </div>
  <?php endif; ?>

  <div class="workshop-row">
    <form method="post" class="inline">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="workshop_assign">
      <label class="field"><span>Kim üzerinde çalışıyor</span>
        <select name="assigned_to" onchange="this.form.submit()">
          <option value="">— Atanmadı —</option>
          <?= select_options(array_combine(array_column($staff, 'id'), array_column($staff, 'full_name')), $order['assigned_to'] !== null ? (string) $order['assigned_to'] : null) ?>
        </select>
      </label>
      <noscript><button class="btn btn-sm">Kaydet</button></noscript>
    </form>
    <?php if (!$framePending): ?>
    <form method="post" class="inline">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="workshop_move">
      <?php if ($wstage === 'montajda'): ?>
        <button class="btn btn-primary btn-sm" name="to" value="kontrol">Montaj tamam · Kontrole gönder</button>
      <?php elseif ($wstage === 'kontrol'): ?>
        <button class="btn btn-sm" name="to" value="montajda">← Montaja geri gönder</button>
        <button class="btn btn-primary btn-sm" name="to" value="hazir"><?= icon('check') ?> Kontrol tamam · Hazır</button>
      <?php endif; ?>
    </form>
    <?php endif; ?>
    <?php if ($wstage === 'montajda' && !$framePending): ?>
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="toggle_own_frame"><input type="hidden" name="pending" value="1">
        <button class="btn btn-ghost btn-sm">Müşteri kendi çerçevesini getirecek</button>
      </form>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>


<div class="split">
  <div class="split-main">

    <?php if ($order['transaction_type'] !== 'tamir'): ?>
    <section class="card">
      <div class="card-head">
        <h2>Reçete ve camlar</h2>
        <a class="btn btn-primary btn-sm" href="rx.php?order_id=<?= $id ?>"><?= icon('plus') ?> Reçete ekle</a>
      </div>
      <?php if (!$rxList): ?>
        <?= empty_state('Henüz reçete yok', 'Reçete girildiğinde camlar otomatik olarak depo listesine düşer.', '<a class="btn" href="rx.php?order_id=' . $id . '">' . icon('glasses') . ' Reçete gir</a>') ?>
      <?php endif; ?>
      <?php foreach ($rxList as $rx): ?>
        <article class="rx-card">
          <header>
            <div>
              <b><?= date_tr($rx['prescription_date']) ?> · <?= e(lens_designs()[$rx['lens_design']] ?? $rx['lens_design']) ?></b>
              <small><?= e($rx['lens_type'] ?: 'Cam tipi seçilmedi') ?><?= $rx['doctor'] ? ' · Dr. ' . e($rx['doctor']) : '' ?></small>
            </div>
            <div class="row-actions">
              <a class="icon-btn" href="print.php?type=rx&id=<?= (int) $rx['id'] ?>" target="_blank" title="Yazdır" aria-label="Reçeteyi yazdır"><?= icon('print') ?></a>
              <a class="btn btn-sm" href="rx.php?id=<?= (int) $rx['id'] ?>"><?= icon('edit') ?> Düzenle</a>
              <?php if (is_super()): ?>
                <form method="post" data-confirm="Bu reçete ve bağlı cam satırları silinsin mi? Bu işlem geri alınamaz.">
                  <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_rx"><input type="hidden" name="rx_id" value="<?= (int) $rx['id'] ?>">
                  <button class="icon-btn danger" title="Sil" aria-label="Reçeteyi sil"><?= icon('trash') ?></button>
                </form>
              <?php endif; ?>
            </div>
          </header>
          <?php include dirname(__DIR__, 2) . '/app/partials/rx-table.php'; ?>
          <?php if (!empty($items[(int) $rx['id']])): ?>
            <ul class="lens-items">
              <?php foreach ($items[(int) $rx['id']] as $it): ?>
                <li><span><?= e($it['lens_label']) ?></span><code><?= e($it['lens_value']) ?></code><?= stock_badge($it['stock_status']) ?>
                  <?php if (is_super() && ($it['supplier_id'] || $it['unit_cost'] !== null)): ?>
                    <small class="lens-cost" title="Sadece yöneticiye görünür">
                      <?= icon('lock') ?><?= $it['supplier_id'] ? e(supplier_label((int) $it['supplier_id'])) : '—' ?><?= $it['unit_cost'] !== null ? ' · Maliyet ' . money($it['unit_cost']) : '' ?>
                    </small>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($rx['advisor_summary']): ?>
            <details class="advisor-note"><summary><?= icon('spark') ?> Öneri asistanı notu<?= $rx['product_name'] ? ' · ' . e($rx['product_brand'] . ' ' . $rx['product_name']) : '' ?></summary><p><?= nl2br(e($rx['advisor_summary'])) ?></p></details>
          <?php endif; ?>
          <?php if ($rx['prescription_note']): ?><p class="note"><?= nl2br(e($rx['prescription_note'])) ?></p><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head"><h2>Sipariş bilgileri</h2></div>
      <form method="post" class="grid cols-2" data-guard>
        <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="update_order">
        <?php if ($order['transaction_type'] === 'tamir'): ?>
          <label class="field"><span>Yapılan işlem</span>
            <select name="service_type"><option value="">Seçilmedi</option><?= select_options(service_types(), $order['service_type']) ?></select></label>
          <label class="field"><span>Söz verilen teslim</span><input type="date" name="promised_date" value="<?= e($order['promised_date']) ?>"></label>
          <label class="field span-all"><span>Ürün / parça açıklaması</span><input name="frame_info" value="<?= e($order['frame_info']) ?>" placeholder="örn. Ray-Ban Aviator, sağ sap"></label>
        <?php else: ?>
          <label class="field"><span>Cam tipi</span>
            <select name="lens_type"><option value="">Seçilmedi</option><?= select_options(lens_type_options($order['lens_type']), $order['lens_type'], false) ?></select></label>
          <label class="field"><span>Söz verilen teslim</span><input type="date" name="promised_date" value="<?= e($order['promised_date']) ?>"></label>
          <label class="field span-all"><span>Çerçeve</span><input name="frame_info" value="<?= e($order['frame_info']) ?>" placeholder="Marka, model, renk, ölçü"></label>
          <label class="field span-all"><span>Stoktaki çerçeve <small class="muted">(seçince vitrin stoğundan düşer)</small></span>
            <select name="frame_item_id">
              <option value="">— müşterinin kendi çerçevesi / stok dışı —</option>
              <?= select_options(frame_stock_options($order['frame_item_id'] !== null ? (int) $order['frame_item_id'] : null), $order['frame_item_id'] !== null ? (string) $order['frame_item_id'] : null) ?>
            </select>
          </label>
        <?php endif; ?>
        <?php if (is_super()): ?>
          <label class="field"><span>Çerçeve markası (maliyet takibi)</span>
            <select name="frame_product_id">
              <option value="">— Seçilmedi —</option>
              <?= select_options(array_column(frame_options(), 'label', 'id'), $order['frame_product_id'] !== null ? (string) $order['frame_product_id'] : null) ?>
            </select>
          </label>
          <label class="field"><span>Listede yoksa: yeni marka</span><input name="new_frame_brand" placeholder="örn. Ray-Ban"></label>
        <?php endif; ?>
        <?php if (is_super()): ?>
          <label class="field"><span>Sipariş tutarı</span><input name="total_amount" inputmode="decimal" value="<?= e(number_format((float) $order['total_amount'], 2, ',', '.')) ?>" data-money></label>
        <?php elseif (can_see_amounts()): ?>
          <div class="field"><span>Sipariş tutarı</span><output class="readonly"><?= money($order['total_amount']) ?></output></div>
        <?php endif; ?>
        <?php if (is_super()): ?>
          <label class="field"><span>SGK katkısı <small class="muted">(tahmini — reçeteden otomatik gelir)</small></span>
            <input name="sgk_amount" inputmode="decimal" value="<?= e(number_format((float) $order['sgk_amount'], 2, ',', '.')) ?>" data-money></label>
        <?php elseif (can_see_amounts() && (float) $order['sgk_amount'] > 0): ?>
          <div class="field"><span>SGK katkısı (tahmini)</span><output class="readonly"><?= money($order['sgk_amount']) ?></output></div>
        <?php endif; ?>
        <label class="field span-all"><span>Not</span><textarea name="notes" rows="3"><?= e($order['notes']) ?></textarea></label>
        <div class="form-actions span-all">
          <?php if (is_super() && $order['order_stage'] !== 'iptal'): ?>
            <button class="btn btn-ghost danger" form="cancel-form" type="submit">Siparişi iptal et</button>
          <?php endif; ?>
          <button class="btn btn-primary">Kaydet</button>
        </div>
      </form>
      <?php if (is_super() && $order['order_stage'] !== 'iptal'): ?>
        <form method="post" id="cancel-form" data-confirm="Sipariş iptal edilsin mi? Camları depo listesinden düşer.">
          <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="set_stage"><input type="hidden" name="stage" value="iptal">
        </form>
      <?php endif; ?>
    </section>
  </div>

  <aside class="split-side">

    <section class="card">
      <div class="card-head"><h2><?= icon('download') ?> SGK reçetesi</h2></div>
      <p class="muted small">Medula Optik ekranındaki reçeteyi bu siparişe aktarın; değerler önizlemede
         gösterilir, siz onaylayınca yazılır.</p>
      <div class="btn-row" style="margin-top:12px">
        <a class="btn btn-sm btn-primary" href="sgk-aktar.php?order=<?= (int) $id ?>">Reçeteyi aktar</a>
      </div>
    </section>

    <?php $takipUrl = order_track_url($id); if ($takipUrl !== ''): ?>
      <section class="card track-share">
        <div class="card-head"><h2><?= icon('glasses') ?> Müşteri takip bağlantısı</h2></div>
        <p class="muted small">Fişteki karekod bu sayfayı açar. Müşteriye ayrıca WhatsApp ile de gönderebilirsiniz
           (mesaj şablonlarında <code>{takip_linki}</code> değişkeni kullanılabilir).</p>
        <div class="track-share-row">
          <input type="text" readonly value="<?= e($takipUrl) ?>" data-track-link aria-label="Takip bağlantısı">
          <button type="button" class="btn btn-sm" data-copy-track>Kopyala</button>
        </div>
        <div class="btn-row" style="margin-top:10px">
          <a class="btn btn-sm" href="<?= e($takipUrl) ?>" target="_blank" rel="noopener"><?= icon('eye') ?> Müşterinin gördüğü sayfa</a>
        </div>
        <?php if (!track_page_exists()): ?>
          <div class="alert alert-error" style="margin:12px 0 0">
            <b>durum.php sunucuya yüklenmemiş.</b> Karekod fişe basılmaz ve bu bağlantı
            çalışmaz. Paketteki <code>durum.php</code> ile <code>app/pages/durum.php</code>
            dosyalarını da yükleyin.
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if (can_see_amounts()): ?>
    <section class="card" id="odeme">
      <div class="card-head"><h2>Ödeme</h2></div>
      <div class="money-summary">
        <div><small>Tutar</small><b><?= money($order['total_amount']) ?></b></div>
        <?php if ((float) $order['sgk_amount'] > 0): ?>
          <div><small>SGK katkısı<?= is_super() ? '' : ' (tahmini)' ?></small><b class="text-ok"><?= money($order['sgk_amount']) ?></b></div>
        <?php endif; ?>
        <div><small>Ödenen</small><b class="text-ok"><?= money($order['paid']) ?></b></div>
        <div><small>Kalan</small><b class="<?= (float) $order['balance'] > 0.009 ? 'text-danger' : '' ?>"><?= money($order['balance']) ?></b></div>
      </div>
      <?php if ((float) $order['sgk_amount'] > 0 && is_super()): ?>
        <p class="muted small" style="margin-top:8px">SGK katkısı MEDULA'nın kesin ödeyeceği tutar değil, reçeteden otomatik hesaplanan tahmindir; sipariş bilgilerinden düzeltebilirsiniz.</p>
      <?php endif; ?>
      <div class="progress" role="progressbar" aria-valuenow="<?= $paidPct ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?= $paidPct ?>%"></i></div>

      <?php if ($order['order_stage'] === 'teslim_edildi' && (float) $order['balance'] > 0.009): ?>
        <form method="post" class="grid cols-2" style="margin-top:14px">
          <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="set_promise">
          <label class="field span-all"><span>Ödeme sözü tarihi</span><input type="date" name="balance_promise_date" value="<?= e($order['balance_promise_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>"></label>
          <button class="btn btn-sm span-all">Kaydet</button>
        </form>
      <?php endif; ?>

      <?php if (can_take_payments() && (float) $order['balance'] > 0.009 && $order['order_stage'] !== 'iptal'): ?>
        <form method="post" class="grid cols-2 pay-form">
          <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="add_payment">
          <label class="field"><span>Tutar</span><input name="amount" inputmode="decimal" placeholder="<?= e(number_format((float) $order['balance'], 2, ',', '.')) ?>" required data-money></label>
          <label class="field"><span>Yöntem</span><select name="method"><?= select_options(payment_methods(), 'nakit') ?></select></label>
          <label class="field"><span>Tarih</span><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></label>
          <label class="field"><span>Not</span><input name="note" maxlength="255"></label>
          <div class="form-actions span-all">
            <button type="button" class="btn btn-ghost btn-sm" data-fill-balance="<?= e(number_format((float) $order['balance'], 2, ',', '.')) ?>">Kalanın tamamı</button>
            <button class="btn btn-primary"><?= icon('wallet') ?> Tahsil et</button>
          </div>
        </form>
      <?php endif; ?>

      <?php if ($payments): ?>
        <ul class="timeline">
          <?php foreach ($payments as $p): ?>
            <li>
              <div><b><?= money($p['amount']) ?></b> <small><?= e(payment_methods()[$p['method']] ?? $p['method']) ?></small>
                <small class="block"><?= date_tr($p['created_at']) ?><?= $p['by_name'] ? ' · ' . e($p['by_name']) : '' ?><?= $p['note'] ? ' · ' . e($p['note']) : '' ?></small></div>
              <div class="inline">
                <a class="icon-btn sm" href="print.php?type=payment&id=<?= (int) $p['id'] ?>" target="_blank" aria-label="Makbuz yazdır" title="Makbuz yazdır"><?= icon('print') ?></a>
                <?php if (is_super()): ?>
                <form method="post" data-confirm="<?= e(money($p['amount'])) ?> tutarındaki tahsilat silinsin mi?">
                  <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= (int) $p['id'] ?>">
                  <button class="icon-btn danger sm" aria-label="Tahsilatı sil"><?= icon('trash') ?></button>
                </form>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p class="muted small">Henüz tahsilat yok.</p>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if (is_super() && ($lensCostKnown > 0 || $hasFrameCost)): ?>
    <section class="card profit-card">
      <div class="card-head"><h2><?= icon('lock') ?> Tahmini kârlılık</h2></div>
      <div class="money-summary">
        <div><small>Satış</small><b><?= money($order['total_amount']) ?></b></div>
        <div><small>Cam maliyeti<?= $lensCostMissing ? ' *' : '' ?></small><b><?= $lensCostKnown > 0 ? money($lensCostKnown) : '—' ?></b></div>
        <div><small>Çerçeve maliyeti</small><b><?= $hasFrameCost ? money($frameCost) : '—' ?></b></div>
      </div>
      <div class="profit-total <?= $estProfit >= 0 ? 'tone-green' : 'tone-red' ?>">
        <span>Tahmini kâr</span><b><?= money($estProfit) ?></b>
      </div>
      <?php if ($lensCostMissing): ?><p class="muted small">* <?= $lensCostMissing ?> cam için henüz fatura girilmediğinden maliyeti hesaba katılmadı.</p><?php endif; ?>
      <?php if (!$hasFrameCost && $order['frame_product_id']): ?><p class="muted small">Çerçeve markası seçili ama bu marka için henüz fatura girilmedi.</p><?php endif; ?>
      <p class="muted small">Yalnızca size görünür · gerçek maliyet fatura girildikçe netleşir.</p>
    </section>
    <?php endif; ?>

    <?php if ($otherOrders): ?>
    <section class="card">
      <div class="card-head"><h2>Müşterinin diğer siparişleri</h2></div>
      <ul class="mini-list">
        <?php foreach ($otherOrders as $oo): ?>
          <li><a href="order.php?id=<?= (int) $oo['id'] ?>"><span><?= order_no((int) $oo['id']) ?> · <?= date_tr($oo['created_at']) ?></span><?= stage_badge($oo['order_stage']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php if ($history): ?>
    <section class="card">
      <div class="card-head"><h2>Hareketler</h2><a class="link" href="logs.php?entity=order&id=<?= $id ?>">Tümü</a></div>
      <ul class="timeline compact">
        <?php foreach ($history as $h): ?>
          <li><div><b><?= e(audit_label($h['action'])) ?></b><small class="block"><?= date_tr($h['created_at'], true) ?> · <?= e($h['user_name']) ?></small></div></li>
        <?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php if (is_super()): ?>
    <details class="card danger-zone">
      <summary class="card-head" style="cursor:pointer"><h2><?= icon('trash') ?> Tehlikeli bölge</h2></summary>
      <p class="muted" style="margin-top:10px">Bu, siparişi <b>“İptal”</b> olarak işaretlemekten farklıdır — kayıt (reçeteler, ödemeler dahil) veritabanından tamamen kalıcı olarak silinir, geri alınamaz. Yalnızca yanlışlıkla oluşturulmuş sipariş kayıtları için kullanın; gerçekten vazgeçilen bir sipariş için “İptal” durumunu kullanın.</p>
      <form method="post" class="stack" data-confirm="“<?= e($order['c_first'] . ' ' . $order['c_last']) ?>” için <?= order_no($id) ?> numaralı sipariş KALICI OLARAK silinecek. Bu işlem geri alınamaz. Emin misiniz?">
        <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="action" value="delete_order">
        <label class="field"><span>Silme nedeni (opsiyonel, kayıt için)</span><input name="reason" placeholder="örn. yanlışlıkla iki kez oluşturuldu"></label>
        <button class="btn btn-ghost danger btn-block"><?= icon('trash') ?> Siparişi kalıcı olarak sil</button>
      </form>
    </details>
    <?php endif; ?>
  </aside>
</div>
<?php page_end();
