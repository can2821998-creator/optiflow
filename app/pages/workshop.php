<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$staff = assignable_staff();
$staffNames = array_column($staff, 'full_name', 'id');

/* ---------- 1) Cam bekliyor ---------- */
$waiting = rows(
    "SELECT o.id, o.order_stage, o.created_at, o.promised_date, c.first_name, c.last_name,
            (SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id
              WHERE r.order_id = o.id AND i.stock_status <> 'stokta_var') AS missing
     FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.order_stage IN ('siparis_verildi','bekliyor','rx_siparis_verildi') AND o.transaction_type <> 'tamir'
     ORDER BY o.created_at ASC LIMIT 60"
);

/* ---------- 2) Montajda ---------- */
$montajda = rows(
    "SELECT o.id, o.created_at, o.promised_date, o.assigned_to, o.own_frame_pending, c.first_name, c.last_name
     FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.order_stage = 'atolyede' AND (o.workshop_stage IS NULL OR o.workshop_stage = 'montajda') AND o.transaction_type <> 'tamir'
     ORDER BY o.own_frame_pending DESC, o.promised_date IS NULL, o.promised_date ASC, o.created_at ASC LIMIT 60"
);

/* ---------- 3) Kontrolde ---------- */
$kontrol = rows(
    "SELECT o.id, o.created_at, o.promised_date, o.assigned_to, c.first_name, c.last_name
     FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.order_stage = 'atolyede' AND o.workshop_stage = 'kontrol' AND o.transaction_type <> 'tamir'
     ORDER BY o.promised_date IS NULL, o.promised_date ASC, o.created_at ASC LIMIT 60"
);

/* ---------- 4) Hazır (teslim bekleyen) ---------- */
$hazirTotal = (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage = 'hazirlandi' AND transaction_type <> 'tamir'");
$hazir = rows(
    "SELECT o.id, o.created_at, c.first_name, c.last_name, c.phone
     FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.order_stage = 'hazirlandi' AND o.transaction_type <> 'tamir'
     ORDER BY o.updated_at ASC LIMIT 20"
);

/* ---------- 5) Tamir / Bakım — kendi basit akışı ---------- */
$tamirAlindi = rows(
    "SELECT o.id, o.created_at, o.service_type, o.is_free, c.first_name, c.last_name
     FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.transaction_type = 'tamir' AND o.order_stage IN ('siparis_verildi','bekliyor','rx_siparis_verildi')
     ORDER BY o.created_at ASC LIMIT 30"
);
$tamirHazirlaniyor = rows(
    "SELECT o.id, o.created_at, o.service_type, o.is_free, c.first_name, c.last_name
     FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.transaction_type = 'tamir' AND o.order_stage = 'atolyede'
     ORDER BY o.created_at ASC LIMIT 30"
);
$tamirHazir = rows(
    "SELECT o.id, o.updated_at, o.service_type, o.is_free, c.first_name, c.last_name, c.phone
     FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.transaction_type = 'tamir' AND o.order_stage = 'hazirlandi'
     ORDER BY o.updated_at ASC LIMIT 30"
);

/* ---------- 6) Bekleyen tahsilat ---------- */
$dueBalance = can_see_amounts() ? rows(
    "SELECT o.id, c.first_name, c.last_name, o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount AS balance
     FROM orders o JOIN customers c ON c.id = o.customer_id " . PAID_JOIN . "
     WHERE o.order_stage <> 'iptal' AND o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount > 0.009
     ORDER BY balance DESC LIMIT 8"
) : [];
$dueBalanceTotal = can_see_amounts() ? (float) scalar(
    "SELECT COALESCE(SUM(o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount), 0) FROM orders o " . PAID_JOIN . "
     WHERE o.order_stage <> 'iptal' AND o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount > 0.009"
) : 0.0;

/* ---------- 7) "Bugün" özeti ---------- */
$todayRange = [date('Y-m-d') . ' 00:00:00', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00'];
$todayOrders = (int) scalar('SELECT COUNT(*) FROM orders WHERE created_at >= ? AND created_at < ?', $todayRange);
$todayCollected = can_see_amounts() ? (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE created_at >= ? AND created_at < ?', $todayRange) : null;
$kasaClosedToday = (bool) scalar("SELECT COUNT(*) FROM cash_counts WHERE count_date = CURDATE()");
$pendingQuotes = (int) scalar('SELECT COUNT(*) FROM quotes WHERE converted_order_id IS NULL');
$pendingInvoices = (int) scalar('SELECT COUNT(*) FROM supplier_deliveries WHERE invoice_id IS NULL');

$today = date('Y-m-d');

function wf_promised(?string $promisedDate): string
{
    if (!$promisedDate) {
        return '';
    }
    $overdue = days_overdue($promisedDate);
    return $overdue > 0
        ? ' · <b class="text-red">' . $overdue . ' gün gecikti</b>'
        : ' · Teslim ' . date_tr($promisedDate);
}

$overdueCount = 0;
foreach (array_merge($montajda, $kontrol) as $o) {
    if (days_overdue($o['promised_date']) > 0) { $overdueCount++; }
}
$framePendingCount = count(array_filter($montajda, static fn($o) => (int) $o['own_frame_pending'] === 1));
$staleReadyCount = count(array_filter($hazir, static fn($o) => days_since($o['created_at']) >= 15));
$totalInFlight = count($waiting) + count($montajda) + count($kontrol);

page_start('Atölye panosu', 'workshop');
?>
<a class="back-link" href="index.php"><?= icon('arrow-left') ?> Siparişler</a>

<section class="page-head">
  <div>
    <small class="eyebrow">Atölye</small>
    <h1>Atölye <em>panosu</em></h1>
    <p class="muted">Camın geldiği andan teslime kadar her siparişin nerede olduğunu tek ekranda görün — sürükleyip bırakarak ilerletin.</p>
  </div>
  <div class="quick-actions">
    <a class="btn btn-primary" href="order-new.php"><?= icon('plus') ?> Yeni sipariş</a>
    <a class="btn" href="quotes.php"><?= icon('spark') ?> Yeni teklif</a>
    <a class="btn" href="kasa.php"><?= icon('wallet') ?> Kasa<?= $kasaClosedToday ? '' : ' <span class="badge sm tone-amber">açık</span>' ?></a>
    <a class="btn" href="ekran.php" target="_blank" rel="noopener"><?= icon('chart') ?> Ayrı ekranda aç</a>
  </div>
</section>

<div class="stats pano-stats">
  <div class="stat"><small>Bugün alınan sipariş</small><b><?= $todayOrders ?></b><span>Bugün oluşturulan</span></div>
  <?php if (can_see_amounts()): ?>
  <div class="stat tone-green"><small>Bugün tahsilat</small><b><?= money($todayCollected) ?></b><span><a href="kasa.php">Kasayı gör →</a></span></div>
  <?php endif; ?>
  <div class="stat <?= $pendingQuotes ? '' : 'tone-green' ?>"><small>Cevap bekleyen teklif</small><b><?= $pendingQuotes ?></b><span><a href="quotes.php">Teklifler →</a></span></div>
  <div class="stat <?= $pendingInvoices ? 'tone-amber' : 'tone-green' ?>"><small>Fatura bekleyen teslimat</small><b><?= $pendingInvoices ?></b><span><a href="deliveries.php">Teslimatlar →</a></span></div>
</div>

<div class="stats pano-stats">
  <div class="stat"><small>Şu an içeride</small><b><?= $totalInFlight ?></b><span>Cam bekleyen + atölyedeki</span></div>
  <div class="stat <?= $overdueCount ? 'tone-red' : '' ?>"><small>Teslimi geciken</small><b><?= $overdueCount ?></b><span>Montajda veya kontrolde</span></div>
  <div class="stat <?= $framePendingCount ? 'tone-amber' : '' ?>"><small>Çerçeve bekleyen</small><b><?= $framePendingCount ?></b><span>Müşteri getirecek</span></div>
  <div class="stat <?= $staleReadyCount ? 'tone-red' : 'tone-green' ?>"><small>15+ gündür hazır</small><b><?= $staleReadyCount ?></b><span>Haber verilmeli</span></div>
</div>

<div class="pano" data-pano>

  <div class="pano-col">
    <div class="pano-col-head tone-amber">
      <span><?= icon('box') ?> Cam bekliyor</span><b><?= count($waiting) ?></b>
    </div>
    <p class="pano-col-sub">Depodan / tedarikçiden gelmesini bekliyor</p>
    <div class="pano-cards" data-drop-zone="bekliyor">
      <?php if (!$waiting): ?>
        <p class="pano-empty">Cam bekleyen sipariş yok.</p>
      <?php endif; ?>
      <?php foreach ($waiting as $o): $days = days_since($o['created_at']); ?>
        <a class="pano-card" href="order.php?id=<?= (int) $o['id'] ?>">
          <b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
          <small><?= order_no((int) $o['id']) ?> · <?= stage_label($o['order_stage']) ?></small>
          <div class="pano-card-foot">
            <?php if ((int) $o['missing'] > 0): ?><span class="badge sm tone-red"><?= (int) $o['missing'] ?> cam eksik</span><?php endif; ?>
            <span class="badge sm <?= $days >= 7 ? 'tone-red' : 'tone-gray' ?>"><?= $days ?> gündür</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <a class="pano-more" href="index.php?durum=bekleyen">Tümünü gör →</a>
  </div>

  <div class="pano-col">
    <div class="pano-col-head tone-teal">
      <span><?= icon('settings') ?> Montajda</span><b><?= count($montajda) ?></b>
    </div>
    <p class="pano-col-sub">Ustanın elinde, camlar çerçeveye takılıyor</p>
    <div class="pano-cards" data-drop-zone="montajda">
      <?php if (!$montajda): ?>
        <p class="pano-empty">Montajda sipariş yok.</p>
      <?php endif; ?>
      <?php foreach ($montajda as $o): $framePending = (int) $o['own_frame_pending'] === 1; $overdue = days_overdue($o['promised_date']); ?>
        <div class="pano-card <?= $framePending ? 'pano-card-alert' : '' ?> <?= $overdue ? 'pano-card-late' : '' ?>" data-order-id="<?= (int) $o['id'] ?>" data-from="montajda">
          <?php if (!$framePending): ?><span class="drag-handle" draggable="true" title="Sürükleyerek taşı"><?= icon('menu') ?></span><?php endif; ?>
          <a href="order.php?id=<?= (int) $o['id'] ?>"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
          <small><?= order_no((int) $o['id']) ?><?= wf_promised($o['promised_date']) ?></small></a>
          <div class="pano-card-foot">
            <?php if ($framePending): ?>
              <span class="badge sm tone-amber">⚠ Çerçeve bekleniyor</span>
            <?php endif; ?>
            <span class="badge sm tone-gray"><?= $o['assigned_to'] ? e($staffNames[$o['assigned_to']] ?? '—') : 'Atanmadı' ?></span>
          </div>
          <?php if ($framePending): ?>
            <form method="post" action="order.php" class="pano-actions" data-form="frame">
              <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
              <input type="hidden" name="action" value="toggle_own_frame"><input type="hidden" name="pending" value="0"><input type="hidden" name="return" value="pano">
              <button class="btn btn-primary btn-sm btn-block">Çerçeve geldi</button>
            </form>
          <?php else: ?>
            <form method="post" action="order.php" class="pano-actions" data-form="move">
              <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
              <input type="hidden" name="action" value="workshop_move"><input type="hidden" name="return" value="pano">
              <button class="btn btn-sm btn-block" name="to" value="kontrol">Kontrole gönder</button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pano-col">
    <div class="pano-col-head tone-violet">
      <span><?= icon('eye') ?> Kalite kontrolde</span><b><?= count($kontrol) ?></b>
    </div>
    <p class="pano-col-sub">Son bakış — ölçüm, eksen, ayar</p>
    <div class="pano-cards" data-drop-zone="kontrol">
      <?php if (!$kontrol): ?>
        <p class="pano-empty">Kontrolde bekleyen sipariş yok.</p>
      <?php endif; ?>
      <?php foreach ($kontrol as $o): $overdue = days_overdue($o['promised_date']); ?>
        <div class="pano-card <?= $overdue ? 'pano-card-late' : '' ?>" data-order-id="<?= (int) $o['id'] ?>" data-from="kontrol">
          <span class="drag-handle" draggable="true" title="Sürükleyerek taşı"><?= icon('menu') ?></span>
          <a href="order.php?id=<?= (int) $o['id'] ?>"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
          <small><?= order_no((int) $o['id']) ?><?= wf_promised($o['promised_date']) ?></small></a>
          <div class="pano-card-foot">
            <span class="badge sm tone-gray"><?= $o['assigned_to'] ? e($staffNames[$o['assigned_to']] ?? '—') : 'Atanmadı' ?></span>
          </div>
          <form method="post" action="order.php" class="pano-actions" data-form="move">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
            <input type="hidden" name="action" value="workshop_move"><input type="hidden" name="return" value="pano">
            <button class="btn btn-sm" name="to" value="montajda">← Geri</button>
            <button class="btn btn-primary btn-sm" name="to" value="hazir"><?= icon('check') ?> Hazır</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pano-col">
    <div class="pano-col-head tone-green">
      <span><?= icon('glasses') ?> Hazır · teslim bekliyor</span><b><?= $hazirTotal ?></b>
    </div>
    <p class="pano-col-sub">Kutuda, müşteriyi bekliyor</p>
    <div class="pano-cards" data-drop-zone="hazir">
      <?php if (!$hazir): ?>
        <p class="pano-empty">Teslim bekleyen sipariş yok.</p>
      <?php endif; ?>
      <?php foreach ($hazir as $o): $days = days_since($o['created_at']); ?>
        <a class="pano-card" href="order.php?id=<?= (int) $o['id'] ?>">
          <b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
          <small><?= order_no((int) $o['id']) ?><?= $o['phone'] ? ' · ' . e(phone_display($o['phone'])) : '' ?></small>
          <div class="pano-card-foot">
            <span class="badge sm <?= $days >= 15 ? 'tone-red' : 'tone-gray' ?>"><?= $days ?> gündür hazır</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <a class="pano-more" href="index.php?durum=hazir">Tümünü gör →</a>
  </div>

</div>

<section class="page-head" style="margin-top:36px">
  <div>
    <small class="eyebrow">Camsız işler</small>
    <h1>Tamir <em>ve bakım</em></h1>
    <p class="muted">Reçetesiz, camdan bağımsız işler — kendi basit akışıyla.</p>
  </div>
</section>

<div class="pano pano-3">
  <div class="pano-col">
    <div class="pano-col-head tone-wine">
      <span><?= icon('orders') ?> Alındı</span><b><?= count($tamirAlindi) ?></b>
    </div>
    <p class="pano-col-sub">Sıraya girdi, henüz başlanmadı</p>
    <div class="pano-cards">
      <?php if (!$tamirAlindi): ?><p class="pano-empty">Bekleyen tamir yok.</p><?php endif; ?>
      <?php foreach ($tamirAlindi as $o): $days = days_since($o['created_at']); ?>
        <div class="pano-card">
          <a href="order.php?id=<?= (int) $o['id'] ?>"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
          <small><?= order_no((int) $o['id']) ?> · <?= e(service_type_label($o['service_type'])) ?></small></a>
          <div class="pano-card-foot">
            <?php if ((int) $o['is_free']): ?><span class="badge sm tone-green">Ücretsiz</span><?php endif; ?>
            <span class="badge sm <?= $days >= 3 ? 'tone-red' : 'tone-gray' ?>"><?= $days ?> gündür</span>
          </div>
          <form method="post" action="order.php" class="pano-actions">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
            <input type="hidden" name="action" value="set_stage"><input type="hidden" name="return" value="pano">
            <button class="btn btn-primary btn-sm btn-block" name="stage" value="atolyede">Başla</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pano-col">
    <div class="pano-col-head tone-teal">
      <span><?= icon('settings') ?> Hazırlanıyor</span><b><?= count($tamirHazirlaniyor) ?></b>
    </div>
    <p class="pano-col-sub">İşlem devam ediyor</p>
    <div class="pano-cards">
      <?php if (!$tamirHazirlaniyor): ?><p class="pano-empty">İşlemde tamir yok.</p><?php endif; ?>
      <?php foreach ($tamirHazirlaniyor as $o): $days = days_since($o['created_at']); ?>
        <div class="pano-card">
          <a href="order.php?id=<?= (int) $o['id'] ?>"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
          <small><?= order_no((int) $o['id']) ?> · <?= e(service_type_label($o['service_type'])) ?></small></a>
          <div class="pano-card-foot">
            <?php if ((int) $o['is_free']): ?><span class="badge sm tone-green">Ücretsiz</span><?php endif; ?>
            <span class="badge sm <?= $days >= 3 ? 'tone-red' : 'tone-gray' ?>"><?= $days ?> gündür</span>
          </div>
          <form method="post" action="order.php" class="pano-actions">
            <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
            <input type="hidden" name="action" value="set_stage"><input type="hidden" name="return" value="pano">
            <button class="btn btn-primary btn-sm btn-block" name="stage" value="hazirlandi"><?= icon('check') ?> Hazır</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="pano-col">
    <div class="pano-col-head tone-green">
      <span><?= icon('glasses') ?> Hazır</span><b><?= count($tamirHazir) ?></b>
    </div>
    <p class="pano-col-sub">Müşteriyi bekliyor</p>
    <div class="pano-cards">
      <?php if (!$tamirHazir): ?><p class="pano-empty">Hazır tamir yok.</p><?php endif; ?>
      <?php foreach ($tamirHazir as $o): $days = days_since($o['updated_at']); ?>
        <a class="pano-card" href="order.php?id=<?= (int) $o['id'] ?>">
          <b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
          <small><?= order_no((int) $o['id']) ?> · <?= e(service_type_label($o['service_type'])) ?><?= $o['phone'] ? ' · ' . e(phone_display($o['phone'])) : '' ?></small>
          <div class="pano-card-foot">
            <?php if ((int) $o['is_free']): ?><span class="badge sm tone-green">Ücretsiz</span><?php endif; ?>
            <span class="badge sm <?= $days >= 7 ? 'tone-red' : 'tone-gray' ?>"><?= $days ?> gündür</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php if (can_see_amounts()): ?>
<section class="page-head" style="margin-top:36px">
  <div>
    <small class="eyebrow">Cari</small>
    <h1>Bekleyen <em>tahsilat</em></h1>
    <p class="muted">Bakiyesi olan siparişler, en yüksekten en düşüğe.</p>
  </div>
  <div class="quick-actions"><span class="badge tone-amber">Toplam <?= money($dueBalanceTotal) ?></span></div>
</section>
<section class="card">
  <?php if (!$dueBalance): ?>
    <?= empty_state('Bekleyen tahsilat yok', 'Tüm siparişler tamamen ödenmiş görünüyor.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Müşteri</th><th class="num">Bakiye</th></tr></thead>
        <tbody>
          <?php foreach ($dueBalance as $o): ?>
            <tr>
              <td><a class="cell-link" href="order.php?id=<?= (int) $o['id'] ?>"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b><small class="block muted"><?= order_no((int) $o['id']) ?></small></a></td>
              <td class="num text-danger"><b><?= money($o['balance']) ?></b></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <a class="pano-more" href="index.php?durum=borclu">Tümünü gör →</a>
  <?php endif; ?>
</section>
<?php endif; ?>
<?php page_end(['workshop.js']); ?>
