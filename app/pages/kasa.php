<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();
if (!can_see_amounts()) {
    render_error_page('Yetkiniz yok', 'Kasa raporu yalnızca tutarları görebilen personel tarafından görüntülenebilir.');
}

$date = valid_date(query('date')) ? query('date') : date('Y-m-d');
$range = [$date . ' 00:00:00', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00'];

function kasa_expected_cash(array $range, string $date): float
{
    $cashIn = (float) scalar("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE method = 'nakit' AND created_at >= ? AND created_at < ?", $range);
    $cashRevenue = (float) scalar("SELECT COALESCE(SUM(amount), 0) FROM revenues WHERE method = 'nakit' AND revenue_date = ?", [$date]);
    $cashOutSupplier = (float) scalar("SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE method = 'nakit' AND created_at >= ? AND created_at < ?", $range)
        + (table_var_mi('tedarikci_senetleri') ? (float) scalar("SELECT COALESCE(SUM(tutar), 0) FROM tedarikci_senetleri WHERE durum = 'odendi' AND odeme_yontemi = 'nakit' AND odeme_tarihi = ?", [$date]) : 0.0);   // 4.14.0 nakit ödenen senetler
    $cashOutExpense = (float) scalar("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE method = 'nakit' AND expense_date = ?", [$date]);
    $cashSale = function_exists('satis_kasa_yontemleri')   // 4.17.0 hızlı satış nakdi
        ? (float) (array_column(satis_kasa_yontemleri($range), 'total', 'method')['nakit'] ?? 0) : 0.0;
    return $cashIn + $cashSale + $cashRevenue - $cashOutSupplier - $cashOutExpense;
}

if (is_post()) {
    $action = post('action', 'count');

    if ($action === 'add_expense') {
        $desc = mb_substr(trim(post('description')), 0, 160);
        $amount = parse_money(post('amount'));
        if ($desc === '' || $amount === null || $amount <= 0) {
            flash('Gider açıklaması ve tutarı geçerli olmalı.', 'error');
            redirect('kasa.php?date=' . $date);
        }
        insert('expenses', [
            'expense_date' => $date,
            'description'  => $desc,
            'amount'       => $amount,
            'method'       => post('method') ?: 'nakit',
            'created_by'   => $user['id'],
        ]);
        audit('expense_create', 'cash', 0, ['tarih' => $date, 'açıklama' => $desc, 'tutar' => $amount]);
        flash('Gider kaydedildi: ' . $desc . ' · ' . money($amount));
        redirect('kasa.php?date=' . $date);
    }

    if ($action === 'delete_expense' && is_super()) {
        q('DELETE FROM expenses WHERE id = ? AND expense_date = ?', [post_int('expense_id'), $date]);
        flash('Gider silindi.', 'info');
        redirect('kasa.php?date=' . $date);
    }

    if ($action === 'add_revenue') {
        $desc = mb_substr(trim(post('description')), 0, 160);
        $amount = parse_money(post('amount'));
        if ($desc === '' || $amount === null || $amount <= 0) {
            flash('Gelir açıklaması ve tutarı geçerli olmalı.', 'error');
            redirect('kasa.php?date=' . $date);
        }
        insert('revenues', [
            'revenue_date' => $date,
            'description'  => $desc,
            'amount'       => $amount,
            'method'       => post('method') ?: 'nakit',
            'created_by'   => $user['id'],
        ]);
        audit('revenue_create', 'cash', 0, ['tarih' => $date, 'açıklama' => $desc, 'tutar' => $amount]);
        flash('Gelir kaydedildi: ' . $desc . ' · ' . money($amount));
        redirect('kasa.php?date=' . $date);
    }

    if ($action === 'delete_revenue' && is_super()) {
        q('DELETE FROM revenues WHERE id = ? AND revenue_date = ?', [post_int('revenue_id'), $date]);
        flash('Gelir silindi.', 'info');
        redirect('kasa.php?date=' . $date);
    }

    if ($action === 'count') {
        $counted = parse_money(post('counted_amount'));
        if ($counted === null || $counted < 0) {
            flash('Sayılan tutar geçersiz.', 'error');
            redirect('kasa.php?date=' . $date);
        }
        $expectedCash = kasa_expected_cash($range, $date);
        $diff = round($counted - $expectedCash, 2);
        insert('cash_counts', [
            'count_date'      => $date,
            'counted_amount'  => $counted,
            'expected_amount' => $expectedCash,
            'difference'      => $diff,
            'note'            => mb_substr(post('note'), 0, 255) ?: null,
            'created_by'      => $user['id'],
        ]);
        audit('cash_count', 'cash', 0, ['tarih' => $date, 'sayılan' => $counted, 'fark' => $diff]);
        flash(abs($diff) < 0.01 ? 'Kasa tam uyuyor, gün kapatıldı. 🎯' : 'Gün kapatıldı — fark: ' . money($diff));
        redirect('kasa.php?date=' . $date . (abs($diff) < 0.01 ? '&mukemmel=1' : ''));
    }
}

$byMethod = rows(
    "SELECT method, COUNT(*) AS cnt, SUM(amount) AS total FROM payments WHERE created_at >= ? AND created_at < ? GROUP BY method ORDER BY total DESC",
    $range
);
$byMethod = satis_kasa_birlestir($byMethod, satis_kasa_yontemleri($range));   // 4.17.0 hızlı satış ödemeleri dahil
$totalIn = (float) array_sum(array_column($byMethod, 'total'));

$byStaff = rows(
    "SELECT COALESCE(u.full_name, 'Bilinmiyor') AS name, p.method, SUM(p.amount) AS total, COUNT(*) AS cnt
     FROM payments p LEFT JOIN user_accounts u ON u.id = p.created_by
     WHERE p.created_at >= ? AND p.created_at < ? GROUP BY name, p.method ORDER BY name, total DESC",
    $range
);
$byStaff = satis_kasa_birlestir($byStaff, satis_kasa_personel($range), ['name', 'method']);
$staffTotals = [];
foreach ($byStaff as $r) {
    $staffTotals[$r['name']] ??= ['cnt' => 0, 'total' => 0.0, 'methods' => []];
    $staffTotals[$r['name']]['cnt'] += (int) $r['cnt'];
    $staffTotals[$r['name']]['total'] += (float) $r['total'];
    $staffTotals[$r['name']]['methods'][] = (payment_methods()[$r['method']] ?? $r['method']) . ' ' . money($r['total']);
}

$expenses = rows('SELECT e.*, u.full_name AS by_name FROM expenses e LEFT JOIN user_accounts u ON u.id = e.created_by WHERE e.expense_date = ? ORDER BY e.created_at DESC', [$date]);
$totalExpenses = (float) array_sum(array_column($expenses, 'amount'));

$revenues = rows('SELECT r.*, u.full_name AS by_name FROM revenues r LEFT JOIN user_accounts u ON u.id = r.created_by WHERE r.revenue_date = ? ORDER BY r.created_at DESC', [$date]);
$totalRevenues = (float) array_sum(array_column($revenues, 'amount'));

$cashOutSupplier = (float) scalar("SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE method = 'nakit' AND created_at >= ? AND created_at < ?", $range)
        + (table_var_mi('tedarikci_senetleri') ? (float) scalar("SELECT COALESCE(SUM(tutar), 0) FROM tedarikci_senetleri WHERE durum = 'odendi' AND odeme_yontemi = 'nakit' AND odeme_tarihi = ?", [$date]) : 0.0);   // 4.14.0 nakit ödenen senetler
$cashOutExpense = (float) array_sum(array_map(static fn($e) => $e['method'] === 'nakit' ? (float) $e['amount'] : 0, $expenses));
$cashRevenue = (float) array_sum(array_map(static fn($r) => $r['method'] === 'nakit' ? (float) $r['amount'] : 0, $revenues));
$cashIn = (float) (array_column($byMethod, 'total', 'method')['nakit'] ?? 0);
$expectedCash = $cashIn + $cashRevenue - $cashOutSupplier - $cashOutExpense;

$payments = rows(
    "SELECT p.*, o.id AS order_id, c.first_name, c.last_name, u.full_name AS by_name
     FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id
     LEFT JOIN user_accounts u ON u.id = p.created_by
     WHERE p.created_at >= ? AND p.created_at < ? ORDER BY p.created_at DESC",
    $range
);

$counts = rows('SELECT c.*, u.full_name AS by_name FROM cash_counts c LEFT JOIN user_accounts u ON u.id = c.created_by WHERE c.count_date = ? ORDER BY c.created_at DESC', [$date]);
$lastCount = $counts[0] ?? null;
$isClosed = (bool) $lastCount;
$isToday = $date === date('Y-m-d');

page_start('Gün sonu kasa raporu', 'kasa');
page_header(
    'Gün sonu kasa raporu',
    date_tr($date) . ' · ' . count($payments) . ' tahsilat' . ($isClosed ? ' · <span class="badge sm tone-green">Gün kapatıldı</span>' : ''),
    '<a class="btn" href="print.php?type=kasa&date=' . e($date) . '" target="_blank">' . icon('print') . ' Dökümü yazdır</a>'
    . '<form method="get" class="inline">'
    . '<input type="date" name="date" value="' . e($date) . '" max="' . date('Y-m-d') . '" onchange="this.form.submit()">'
    . '<button class="btn btn-primary" type="submit">Görüntüle</button>'
    . '</form>',
    '', 'Kasa'
);
?>
<div class="stats">
  <?php foreach (['nakit' => 'tone-green', 'kart' => 'tone-teal', 'havale' => 'tone-violet', 'diger' => 'tone-gray'] as $m => $tone): $row = null; foreach ($byMethod as $b) { if ($b['method'] === $m) { $row = $b; break; } } ?>
    <div class="stat <?= $tone ?>"><small><?= e(payment_methods()[$m] ?? $m) ?></small><b><?= $row ? money($row['total']) : money(0) ?></b><span><?= $row ? (int) $row['cnt'] . ' tahsilat' : 'Yok' ?></span></div>
  <?php endforeach; ?>
  <div class="stat"><small>Toplam</small><b><?= money($totalIn) ?></b><span>Tüm yöntemler</span></div>
  <div class="stat tone-red"><small>Günlük gider</small><b><?= money($totalExpenses) ?></b><span><?= count($expenses) ?> kalem</span></div>
  <div class="stat tone-green"><small>Diğer gelir</small><b><?= money($totalRevenues) ?></b><span><?= count($revenues) ?> kalem</span></div>
</div>

<div class="split">
  <div class="split-main">

    <section class="card">
      <div class="card-head"><h2>Personel bazında tahsilat</h2></div>
      <?php if (!$staffTotals): ?>
        <?= empty_state('Bu tarihte tahsilat yok') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Personel</th><th class="hide-sm">Kırılım</th><th class="num">Toplam</th></tr></thead>
            <tbody>
              <?php foreach ($staffTotals as $name => $s): ?>
                <tr>
                  <td><b><?= e($name) ?></b><small class="block muted"><?= $s['cnt'] ?> tahsilat</small></td>
                  <td class="hide-sm muted"><?= e(implode(' · ', $s['methods'])) ?></td>
                  <td class="num"><b><?= money($s['total']) ?></b></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <?php $gunSatis = function_exists('satis_ozeti') && ozellik_acik('hizli_satis') ? satis_ozeti($date, $date) : null; ?>
    <?php if ($gunSatis && $gunSatis['adet'] > 0): ?>
    <section class="card">
      <div class="card-head"><h2><?= icon('receipt') ?> Hızlı satışlar</h2><a class="btn btn-sm" href="hizli-satis.php">Aç</a></div>
      <p class="muted" style="margin-top:-8px"><?= (int) $gunSatis['adet'] ?> satış · <?= money($gunSatis['ciro']) ?>. Ödemeleri yukarıdaki yöntem ve personel toplamlarına dahildir.</p>
    </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head"><h2><?= icon('wallet') ?> Diğer gelirler</h2></div>
      <p class="muted" style="margin-top:-8px">Sipariş dışı gelirleri (kasaya eklenen para vb.) buraya girin.<?= function_exists('ozellik_acik') && ozellik_acik('hizli_satis') ? ' Ürün satışı için <a class="link" href="hizli-satis.php">Hızlı satış</a>\'ı kullanın; stok da düşer.' : '' ?></p>
      <form method="post" class="grid cols-3" style="margin-bottom:16px">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_revenue">
        <label class="field"><span>Açıklama *</span><input name="description" required placeholder="örn. Kılıf / mendil satışı"></label>
        <label class="field"><span>Tutar *</span><input name="amount" inputmode="decimal" required placeholder="0,00"></label>
        <label class="field"><span>Yöntem</span><select name="method"><?= select_options(payment_methods(), 'nakit') ?></select></label>
        <button class="btn btn-primary span-all">Geliri kaydet</button>
      </form>
      <?php if (!$revenues): ?>
        <p class="muted small">Bu tarihte ek gelir kaydı yok.</p>
      <?php else: ?>
        <ul class="mini-list">
          <?php foreach ($revenues as $r): ?>
            <li>
              <span><b><?= e($r['description']) ?></b><small class="block muted"><?= e(payment_methods()[$r['method']] ?? $r['method']) ?> · <?= e($r['by_name'] ?: '—') ?> · <?= date('H:i', strtotime($r['created_at'])) ?></small></span>
              <div class="inline">
                <b class="text-danger" style="color:var(--green)"><?= money($r['amount']) ?></b>
                <?php if (is_super()): ?>
                  <form method="post" data-confirm="Bu gelir silinsin mi?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_revenue"><input type="hidden" name="revenue_id" value="<?= (int) $r['id'] ?>"><button class="icon-btn danger sm" aria-label="Sil"><?= icon('trash') ?></button></form>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2><?= icon('wallet') ?> Günlük giderler</h2></div>
      <p class="muted" style="margin-top:-8px">Kasadan yapılan küçük harcamaları (kırtasiye, temizlik, kargo vb.) buraya girin — nakit kasa sayımındaki farkın nedenini açıklar.</p>
      <form method="post" class="grid cols-3" style="margin-bottom:16px">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_expense">
        <label class="field"><span>Açıklama *</span><input name="description" required placeholder="örn. Temizlik malzemesi"></label>
        <label class="field"><span>Tutar *</span><input name="amount" inputmode="decimal" required placeholder="0,00"></label>
        <label class="field"><span>Yöntem</span><select name="method"><?= select_options(payment_methods(), 'nakit') ?></select></label>
        <button class="btn span-all">Gideri kaydet</button>
      </form>
      <?php if (!$expenses): ?>
        <p class="muted small">Bu tarihte gider kaydı yok.</p>
      <?php else: ?>
        <ul class="mini-list">
          <?php foreach ($expenses as $e): ?>
            <li>
              <span><b><?= e($e['description']) ?></b><small class="block muted"><?= e(payment_methods()[$e['method']] ?? $e['method']) ?> · <?= e($e['by_name'] ?: '—') ?> · <?= date('H:i', strtotime($e['created_at'])) ?></small></span>
              <div class="inline">
                <b><?= money($e['amount']) ?></b>
                <?php if (is_super()): ?>
                  <form method="post" data-confirm="Bu gider silinsin mi?"><?= csrf_field() ?><input type="hidden" name="action" value="delete_expense"><input type="hidden" name="expense_id" value="<?= (int) $e['id'] ?>"><button class="icon-btn danger sm" aria-label="Sil"><?= icon('trash') ?></button></form>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Tüm tahsilatlar</h2></div>
      <?php if (!$payments): ?>
        <?= empty_state('Bu tarihte tahsilat yok') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Saat</th><th>Müşteri</th><th class="hide-sm">Yöntem</th><th class="hide-md">Personel</th><th class="num">Tutar</th></tr></thead>
            <tbody>
              <?php foreach ($payments as $p): ?>
                <tr>
                  <td><?= date('H:i', strtotime($p['created_at'])) ?></td>
                  <td><a class="link" href="order.php?id=<?= (int) $p['order_id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></a></td>
                  <td class="hide-sm"><?= e(payment_methods()[$p['method']] ?? $p['method']) ?></td>
                  <td class="hide-md"><?= e($p['by_name'] ?: '—') ?></td>
                  <td class="num"><?= money($p['amount']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  </div>

  <div class="split-side">
    <section class="card sticky">
      <div class="card-head"><h2><?= icon('wallet') ?> Nakit kasa sayımı</h2></div>
      <div class="money-summary">
        <div><small>Nakit giren</small><b><?= money($cashIn) ?></b></div>
        <div><small>Diğer nakit gelir</small><b><?= $cashRevenue > 0 ? '+ ' . money($cashRevenue) : '—' ?></b></div>
        <div><small>Tedarikçiye / gider</small><b><?= ($cashOutSupplier + $cashOutExpense) > 0 ? '− ' . money($cashOutSupplier + $cashOutExpense) : '—' ?></b></div>
        <div><small>Kasada olmalı</small><b><?= money($expectedCash) ?></b></div>
      </div>

      <?php if ($isClosed): $ok = abs((float) $lastCount['difference']) < 0.01; ?>
        <div class="profit-total <?= $ok ? 'tone-green' : 'tone-red' ?>" style="margin-top:16px" <?= $ok && query('mukemmel') === '1' ? 'data-celebrate' : '' ?>>
          <span>Sayıldı: <?= money($lastCount['counted_amount']) ?> · <?= e($lastCount['by_name'] ?: '—') ?> · <?= date('H:i', strtotime($lastCount['created_at'])) ?></span>
          <b><?= $ok ? 'Tam uyuyor' : (((float) $lastCount['difference'] > 0 ? '+' : '') . money($lastCount['difference'])) ?></b>
        </div>
        <?php if ($ok):
          $recentCounts = rows(
              "SELECT c.count_date, c.difference FROM cash_counts c
               INNER JOIN (SELECT count_date, MAX(created_at) AS mx FROM cash_counts WHERE count_date <= ? GROUP BY count_date) latest
                 ON latest.count_date = c.count_date AND latest.mx = c.created_at
               ORDER BY c.count_date DESC LIMIT 30",
              [$date]
          );
          $streak = 0;
          foreach ($recentCounts as $rc) { if (abs((float) $rc['difference']) < 0.01) { $streak++; } else { break; } }
          if ($streak >= 3): ?>
            <p class="muted small" style="margin-top:8px">🔥 <b><?= $streak ?> gündür</b> kusursuz kasa kapanışı!</p>
          <?php endif;
        endif; ?>
        <?php if ($lastCount['note']): ?><p class="muted small">Not: <?= e($lastCount['note']) ?></p><?php endif; ?>
        <a class="btn btn-primary btn-block" href="print.php?type=kasa&date=<?= e($date) ?>" target="_blank" style="margin-top:12px"><?= icon('print') ?> Dökümü yazdır</a>
        <?php if ($isToday): ?>
          <p class="muted small" style="margin-top:10px">Bugünün kasası kapatıldı. Yarın açıldığında yeni bir sayım burada başlayacak.</p>
        <?php endif; ?>
        <?php if (is_super()): ?>
          <details style="margin-top:14px"><summary class="muted small" style="cursor:pointer">Düzeltme yap (yeniden say)</summary>
            <form method="post" class="stack" style="margin-top:10px">
              <?= csrf_field() ?><input type="hidden" name="action" value="count">
              <label class="field"><span>Yeni sayım tutarı</span><input name="counted_amount" inputmode="decimal" required placeholder="0,00"></label>
              <label class="field"><span>Not</span><input name="note" placeholder="düzeltme nedeni"></label>
              <button class="btn btn-block">Düzeltmeyi kaydet</button>
            </form>
          </details>
        <?php endif; ?>
      <?php else: ?>
        <form method="post" class="stack" style="margin-top:16px">
          <?= csrf_field() ?><input type="hidden" name="action" value="count">
          <label class="field"><span>Saydığınız nakit tutarı *</span><input name="counted_amount" inputmode="decimal" required placeholder="0,00"></label>
          <label class="field"><span>Not</span><input name="note" placeholder="varsa fark nedeni"></label>
          <button class="btn btn-primary btn-block"><?= icon('check') ?> Sayımı kaydet ve günü kapat</button>
        </form>
      <?php endif; ?>
    </section>
  </div>
</div>
<?php page_end();
