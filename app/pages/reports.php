<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_super();

$presets = [
    'bu_ay'    => ['Bu ay', date('Y-m-01'), date('Y-m-d')],
    'gecen_ay' => ['Geçen ay', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'son_30'   => ['Son 30 gün', date('Y-m-d', strtotime('-29 days')), date('Y-m-d')],
    'bu_yil'   => ['Bu yıl', date('Y-01-01'), date('Y-m-d')],
];
$from = query('from');
$to = query('to');
if (!valid_date($from) || !valid_date($to)) {
    [, $from, $to] = $presets[query('p', 'bu_ay')] ?? $presets['bu_ay'];
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$end = date('Y-m-d', strtotime($to . ' +1 day'));
$range = [$from . ' 00:00:00', $end . ' 00:00:00'];

/* ---------- CSV dışa aktarma ---------- */
if (query('export') === 'csv') {
    $list = rows(
        "SELECT o.id, o.created_at, c.first_name, c.last_name, c.phone, o.lens_type, o.order_stage, o.total_amount, o.sgk_amount,
                COALESCE(p.paid, 0) AS paid, u.full_name AS staff
         FROM orders o JOIN customers c ON c.id = o.customer_id " . PAID_JOIN . "
         LEFT JOIN user_accounts u ON u.id = o.created_by
         WHERE o.created_at >= ? AND o.created_at < ? ORDER BY o.created_at",
        $range
    );
    audit('report_export', 'report', null, ['csv' => "$from – $to", 'satır' => count($list)]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="siparisler-' . $from . '-' . $to . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Sipariş no', 'Tarih', 'Ad', 'Soyad', 'Telefon', 'Cam tipi', 'Durum', 'Tutar', 'SGK katkısı', 'Ödenen', 'Kalan', 'Personel'], ';', '"', '');
    foreach ($list as $r) {
        fputcsv($out, [
            order_no((int) $r['id']), date_tr($r['created_at'], true), $r['first_name'], $r['last_name'], phone_display($r['phone']),
            $r['lens_type'], stage_label($r['order_stage']),
            number_format((float) $r['total_amount'], 2, ',', ''), number_format((float) $r['sgk_amount'], 2, ',', ''), number_format((float) $r['paid'], 2, ',', ''),
            number_format((float) $r['total_amount'] - (float) $r['sgk_amount'] - (float) $r['paid'], 2, ',', ''), $r['staff'],
        ], ';', '"', '');
    }
    exit;
}

/* ---------- Metrikler ---------- */
$summary = row(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(o.total_amount), 0) AS turnover,
            COALESCE(SUM(o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount), 0) AS period_balance,
            SUM(o.order_stage = 'teslim_edildi') AS delivered
     FROM orders o " . PAID_JOIN . " WHERE o.order_stage <> 'iptal' AND o.created_at >= ? AND o.created_at < ?",
    $range
);
$cancelled = (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage = 'iptal' AND created_at >= ? AND created_at < ?", $range);
$collected = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE created_at >= ? AND created_at < ?', $range);
$openAll = (float) scalar("SELECT COALESCE(SUM(o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount), 0) FROM orders o " . PAID_JOIN . " WHERE o.order_stage <> 'iptal' AND o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount > 0");
$avg = (int) $summary['orders'] ? (float) $summary['turnover'] / (int) $summary['orders'] : 0;

// Günlük / aylık seri
$days = (strtotime($to) - strtotime($from)) / 86400 + 1;
$monthly = $days > 62;
$fmt = $monthly ? '%Y-%m' : '%Y-%m-%d';
$hs = satis_rapor($range, $fmt);   // 4.17.0 hızlı satış: ciro, tahsilat, seri, personel ve yöntemlere eklenir
$summary['turnover'] = (float) $summary['turnover'] + $hs['ciro'];
$collected += $hs['tahsilat'];
$series = [];
foreach (rows("SELECT DATE_FORMAT(created_at, '$fmt') AS k, SUM(total_amount) AS v FROM orders WHERE order_stage <> 'iptal' AND created_at >= ? AND created_at < ? GROUP BY k", $range) as $r) {
    $series[$r['k']]['sales'] = (float) $r['v'];
}
foreach (rows("SELECT DATE_FORMAT(created_at, '$fmt') AS k, SUM(amount) AS v FROM payments WHERE created_at >= ? AND created_at < ? GROUP BY k", $range) as $r) {
    $series[$r['k']]['paid'] = (float) $r['v'];
}
foreach ($hs['seri_satis'] as $k => $v) {
    $series[$k]['sales'] = ($series[$k]['sales'] ?? 0) + $v;
}
foreach ($hs['seri_odeme'] as $k => $v) {
    $series[$k]['paid'] = ($series[$k]['paid'] ?? 0) + $v;
}
$buckets = [];
for ($t = strtotime($from); $t <= strtotime($to); $t = strtotime($monthly ? '+1 month' : '+1 day', $t)) {
    $k = date($monthly ? 'Y-m' : 'Y-m-d', $t);
    $buckets[$k] = ['sales' => $series[$k]['sales'] ?? 0, 'paid' => $series[$k]['paid'] ?? 0];
    if ($monthly) {
        $t = strtotime(date('Y-m-01', $t));
    }
}
$seriesMax = max(1, ...array_values(array_map(static fn($b) => max($b["sales"], $b["paid"]), $buckets ?: [["sales" => 0, "paid" => 0]])));

$lensDist = rows("SELECT COALESCE(NULLIF(lens_type, ''), 'Seçilmemiş') AS name, COUNT(*) AS cnt, SUM(total_amount) AS total FROM orders WHERE order_stage <> 'iptal' AND created_at >= ? AND created_at < ? GROUP BY name ORDER BY cnt DESC LIMIT 12", $range);
$designDist = rows('SELECT lens_design AS name, COUNT(*) AS cnt FROM prescription_records WHERE prescription_date BETWEEN ? AND ? GROUP BY lens_design ORDER BY cnt DESC', [$from, $to]);
$stageDist = rows('SELECT order_stage AS name, COUNT(*) AS cnt FROM orders WHERE created_at >= ? AND created_at < ? GROUP BY order_stage', $range);
$staff = rows("SELECT COALESCE(u.full_name, o.sales_person, '—') AS name, COUNT(*) AS cnt, SUM(o.total_amount) AS total FROM orders o LEFT JOIN user_accounts u ON u.id = o.created_by WHERE o.order_stage <> 'iptal' AND o.created_at >= ? AND o.created_at < ? GROUP BY name ORDER BY total DESC", $range);
$methods = rows('SELECT method, COUNT(*) AS cnt, SUM(amount) AS total FROM payments WHERE created_at >= ? AND created_at < ? GROUP BY method ORDER BY total DESC', $range);
$staff = satis_kasa_birlestir($staff, $hs['personel'], ['name']);
$methods = satis_kasa_birlestir($methods, $hs['yontem']);
$maxLens = max(1, ...array_map('intval', array_column($lensDist, 'cnt') ?: [0]));
$stageOrder = array_flip(array_keys(stages()));
usort($stageDist, static fn($a, $b) => ($stageOrder[$a['name']] ?? 99) <=> ($stageOrder[$b['name']] ?? 99));

$qs = 'from=' . $from . '&to=' . $to;
page_start('Raporlar', 'reports');
page_header(
    'Raporlar',
    date_tr($from) . ' – ' . date_tr($to),
    '<a class="btn" href="reports.php?' . e($qs) . '&export=csv">' . icon('download') . ' CSV</a><a class="btn" href="print.php?type=report&' . e($qs) . '" target="_blank">' . icon('print') . ' Yazdır</a>',
    '',
    'Analiz'
);
?>
<section class="card">
  <form class="filters" method="get">
    <div class="chips">
      <?php foreach ($presets as $k => [$label, $pf, $pt]): ?>
        <a class="chip <?= $pf === $from && $pt === $to ? 'active' : '' ?>" href="reports.php?p=<?= e($k) ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
    <label class="field"><span>Başlangıç</span><input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="field"><span>Bitiş</span><input type="date" name="to" value="<?= e($to) ?>"></label>
    <div class="filter-actions"><button class="btn btn-primary">Uygula</button></div>
  </form>
</section>

<section class="stats">
  <div class="stat"><small>Sipariş</small><b><?= (int) $summary['orders'] ?></b><span><?= $cancelled ?> iptal hariç · <?= (int) $summary['delivered'] ?> teslim</span></div>
  <div class="stat"><small>Ciro</small><b><?= money($summary['turnover']) ?></b><span><?= $hs['adet'] ? (int) $hs['adet'] . ' hızlı satış dahil (' . money($hs['ciro']) . ')' : 'Sipariş ortalaması ' . money($avg) ?></span></div>
  <div class="stat tone-green"><small>Tahsilat</small><b><?= money($collected) ?></b><span>Bu dönemde alınan tüm ödemeler</span></div>
  <div class="stat tone-red"><small>Dönem siparişlerinin kalanı</small><b><?= money($summary['period_balance']) ?></b><span>Tüm zamanlar açık: <?= money($openAll) ?></span></div>
</section>

<section class="card">
  <div class="card-head"><h2><?= $monthly ? 'Aylık' : 'Günlük' ?> satış ve tahsilat</h2>
    <div class="legend"><span class="key sales"></span>Satış <span class="key paid"></span>Tahsilat</div></div>
  <div class="chart" role="img" aria-label="Satış ve tahsilat grafiği">
    <?php foreach ($buckets as $k => $b): ?>
      <div class="chart-col" title="<?= e(($monthly ? date('m.Y', strtotime($k . '-01')) : date_tr($k)) . ' · Satış ' . money($b['sales']) . ' · Tahsilat ' . money($b['paid'])) ?>">
        <div class="bars"><i class="sales" style="height:<?= round($b['sales'] / $seriesMax * 100, 1) ?>%"></i><i class="paid" style="height:<?= round($b['paid'] / $seriesMax * 100, 1) ?>%"></i></div>
        <small><?= $monthly ? date('m.y', strtotime($k . '-01')) : date('d', strtotime($k)) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<div class="grid cols-2 gap-lg">
  <section class="card">
    <div class="card-head"><h2>Cam tipi</h2></div>
    <?php if (!$lensDist): ?><p class="muted">Veri yok.</p><?php endif; ?>
    <div class="hbars">
      <?php foreach ($lensDist as $l): ?>
        <div class="hbar"><span><?= e($l['name']) ?></span><div class="hbar-track"><i style="width:<?= round((int) $l['cnt'] / $maxLens * 100) ?>%"></i></div><b><?= (int) $l['cnt'] ?></b><small><?= money($l['total']) ?></small></div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2>Sipariş durumları</h2><small class="muted">Dönemde oluşturulanlar</small></div>
    <?php if (!$stageDist): ?><p class="muted">Veri yok.</p><?php else: ?>
      <div class="donut-row">
        <?php
          $stageTotal = array_sum(array_column($stageDist, 'cnt'));
          $stageSegs = [];
          foreach ($stageDist as $i => $s) { $stageSegs[] = ['label' => stage_label($s['name']), 'value' => (int) $s['cnt'], 'color' => chart_palette($i)]; }
          echo svg_donut($stageSegs);
        ?>
        <ul class="donut-legend">
          <?php foreach ($stageDist as $i => $s): ?>
            <li><i style="background:<?= e(chart_palette($i)) ?>"></i><span><?= stage_badge($s['name']) ?></span><b><?= (int) $s['cnt'] ?></b><small><?= $stageTotal ? round((int) $s['cnt'] / $stageTotal * 100) : 0 ?>%</small></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php if ($designDist): ?>
      <h3 class="sub">Reçete kullanım şekli</h3>
      <div class="hbars">
        <?php $maxDesign = max(1, ...array_map('intval', array_column($designDist, 'cnt') ?: [0]));
        foreach ($designDist as $i => $d): ?>
          <div class="hbar"><span><?= e(lens_designs()[$d['name']] ?? $d['name']) ?></span><div class="hbar-track"><i style="width:<?= round((int) $d['cnt'] / $maxDesign * 100) ?>%;background:<?= e(chart_palette($i)) ?>"></i></div><b><?= (int) $d['cnt'] ?></b></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>Personel</h2></div>
    <?php if (!$staff): ?><p class="muted">Veri yok.</p><?php else: ?>
      <div class="hbars">
        <?php $maxStaff = max(1, ...array_map('floatval', array_column($staff, 'total') ?: [0]));
        foreach ($staff as $i => $s): ?>
          <div class="hbar"><span><?= e($s['name']) ?> <small class="muted"><?= (int) $s['cnt'] ?> sipariş</small></span><div class="hbar-track"><i style="width:<?= round((float) $s['total'] / $maxStaff * 100) ?>%;background:<?= e(chart_palette($i)) ?>"></i></div><b><?= money($s['total']) ?></b></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h2>Ödeme yöntemleri</h2></div>
    <?php if (!$methods): ?><p class="muted">Veri yok.</p><?php else: ?>
      <div class="donut-row">
        <?php
          $methodSegs = [];
          foreach ($methods as $i => $m) { $methodSegs[] = ['label' => payment_methods()[$m['method']] ?? $m['method'], 'value' => (float) $m['total'], 'color' => chart_palette($i)]; }
          echo svg_donut($methodSegs);
        ?>
        <ul class="donut-legend">
          <?php $methodTotal = array_sum(array_column($methods, 'total'));
          foreach ($methods as $i => $m): ?>
            <li><i style="background:<?= e(chart_palette($i)) ?>"></i><span><?= e(payment_methods()[$m['method']] ?? $m['method']) ?> <small class="muted"><?= (int) $m['cnt'] ?> işlem</small></span><b><?= money($m['total']) ?></b></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php page_end();
