<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (!tenant_oturum() || !empty($GLOBALS['__kok_istek'])) {
    // 4.11.0 — OptiFlow Pro (masaüstü) tanıtım sayfasını göstermez; doğrudan mağaza girişine gider.
    if (is_optiflow_desktop()) {
        redirect('magaza-giris.php');
    }
    render_karsilama();   // 4.19.1: alan adının kökü oturum açıkken de tanıtım sayfasıdır
}

require_login();

$filters = [
    'aktif'    => 'Aktif',
    'bekleyen' => 'Cam bekleyen',
    'atolyede' => 'Atölyede',
    'hazir'    => 'Hazır',
    'teslim'   => 'Teslim edildi',
    'borclu'   => 'Borçlu',
    'iptal'    => 'İptal',
    'tumu'     => 'Tümü',
];
if (!can_see_amounts()) {
    unset($filters['borclu']);
}
$filter = query('durum', 'aktif');
if (!isset($filters[$filter])) {
    $filter = 'aktif';
}
$search = mb_substr(query('q'), 0, 80);

$where = [];
$params = [];
switch ($filter) {
    case 'aktif':    $where[] = "o.order_stage NOT IN ('teslim_edildi','iptal')"; break;
    case 'bekleyen': $where[] = "o.order_stage IN ('siparis_verildi','bekliyor','rx_siparis_verildi')"; break;
    case 'atolyede': $where[] = "o.order_stage = 'atolyede'"; break;
    case 'hazir':    $where[] = "o.order_stage = 'hazirlandi'"; break;
    case 'teslim':   $where[] = "o.order_stage = 'teslim_edildi'"; break;
    case 'iptal':    $where[] = "o.order_stage = 'iptal'"; break;
    case 'borclu':   $where[] = "o.order_stage <> 'iptal' AND o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount > 0.009"; break;
}
if ($search !== '') {
    $digits = preg_replace('/\D+/', '', $search) ?? '';
    $or = ["CONCAT(c.first_name, ' ', c.last_name) LIKE ?"];
    $params[] = '%' . $search . '%';
    if ($digits !== '') {
        $or[] = 'o.id = ?';
        $params[] = (int) $digits;
        if (strlen($digits) >= 4) {
            $or[] = 'c.phone LIKE ?';
            $params[] = '%' . ltrim($digits, '0') . '%';
        }
    }
    $where[] = '(' . implode(' OR ', $or) . ')';
}
if ($sk = sube_kosul_siparis('o')) {   // 4.49.0 şube: açıldığı ya da teslim edileceği şube
    $where[] = substr($sk, 5);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$from = 'FROM orders o JOIN customers c ON c.id = o.customer_id ' . PAID_JOIN;

$total = (int) scalar("SELECT COUNT(*) $from $whereSql", $params);
$pg = paginate($total, 25);
$orders = rows(
    "SELECT o.id, o.customer_id, o.order_stage, o.lens_type, o.transaction_type, o.service_type, o.is_free, o.total_amount, o.created_at, o.promised_date,
            c.first_name, c.last_name, c.phone, COALESCE(p.paid, 0) AS paid, o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount AS balance,
            (SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id
              WHERE r.order_id = o.id AND i.stock_status <> 'stokta_var') AS missing
     $from $whereSql
     ORDER BY o.created_at DESC, o.id DESC
     LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $params
);

// Özet kutucukları
$stats = row(
    "SELECT
        SUM(order_stage NOT IN ('teslim_edildi','iptal')) AS active,
        SUM(order_stage IN ('siparis_verildi','bekliyor','rx_siparis_verildi')) AS waiting,
        SUM(order_stage = 'hazirlandi') AS ready,
        SUM(order_stage = 'atolyede') AS workshop,
        SUM(DATE(created_at) = CURDATE() AND order_stage <> 'iptal') AS today
     FROM orders o WHERE 1 = 1" . sube_kosul_siparis('o')
) ?? [];
$overdue = (int) scalar("SELECT COUNT(*) FROM orders o WHERE promised_date < CURDATE() AND order_stage NOT IN ('hazirlandi','teslim_edildi','iptal')" . sube_kosul_siparis('o'));
$openBalance = is_super()
    ? (float) scalar("SELECT COALESCE(SUM(o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount), 0) FROM orders o " . PAID_JOIN . " WHERE o.order_stage <> 'iptal' AND o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount > 0" . sube_kosul('o'))
    : null;

// "Bugün" eylem panosu: dikkat gerektiren dört liste.
$todayDue = rows(
    "SELECT o.id, o.promised_date, c.first_name, c.last_name FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.promised_date = CURDATE() AND o.order_stage NOT IN ('teslim_edildi', 'iptal')" . sube_kosul_siparis('o') . " ORDER BY o.id LIMIT 6"
);
$overdueList = rows(
    "SELECT o.id, o.promised_date, c.first_name, c.last_name FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.promised_date < CURDATE() AND o.order_stage NOT IN ('hazirlandi', 'teslim_edildi', 'iptal')" . sube_kosul_siparis('o') . " ORDER BY o.promised_date LIMIT 6"
);
$readyList = rows(
    "SELECT o.id, o.updated_at, c.first_name, c.last_name, c.phone FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.order_stage = 'hazirlandi'" . sube_kosul_hazir('o') . " ORDER BY o.updated_at LIMIT 6"
);
$dueBalanceList = can_see_amounts() ? rows(
    "SELECT o.id, c.first_name, c.last_name, o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount AS balance FROM orders o JOIN customers c ON c.id = o.customer_id " . PAID_JOIN . "
     WHERE o.order_stage <> 'iptal' AND o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount > 0.009" . sube_kosul('o') . " ORDER BY balance DESC LIMIT 6"
) : [];

// 4.32.0 kâğıtsız tezgâh: gün sonu depoya gidecek eksik camlar (müşteri bilgisi gitmez)
$depoAcik = ozellik_acik('kagitsiz');
$depoGruplar = $depoAcik ? depo_gruplari(depo_eksik_kalemler()) : [];
$depoToplam = array_sum(array_column($depoGruplar, 'adet'));
$depoTedarikci = $depoAcik ? depo_tedarikci() : null;
$depoTedarikciler = $depoAcik && !$depoTedarikci && $depoToplam ? rows('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name') : [];
$depoSon = $depoAcik ? depo_son_gonderim() : null;

page_start('Siparişler', 'orders');
page_header(
    'Siparişler',
    e(date_long()),
    '<a class="btn btn-primary" href="tezgah.php">' . icon('plus') . ' Yeni müşteri · reçete</a> <a class="btn btn-ghost" href="order-new.php" title="Reçetesiz iş: tamir, güneş gözlüğü, lens">Reçetesiz sipariş</a>',   // 4.38.1 tek giriş
    '',
    greeting() . ', ' . explode(' ', (string) current_user()['full_name'])[0],
    bant_veri([   // 4.46.0 gece bandında günün sayıları
        ['Aktif', (int) ($stats['active'] ?? 0), '', 'index.php?durum=aktif'],
        ['Atölyede', (int) ($stats['workshop'] ?? 0), '', 'workshop.php'],
        ['Hazır', (int) ($stats['ready'] ?? 0), (int) ($stats['ready'] ?? 0) > 0 ? 'green' : '', 'index.php?durum=hazir'],
        ['Geciken', $overdue, $overdue > 0 ? 'red' : '', 'index.php?durum=aktif'],
    ])
);
?>
<?php require dirname(__DIR__) . '/partials/depo-karti.php'; ?>
<?php if ($todayDue || $overdueList || $readyList || $dueBalanceList): ?>
<section class="card today-board">
  <div class="card-head"><h2><?= icon('spark') ?> Bugün</h2>
    <?php if ($openBalance !== null): ?><button type="button" class="stat-secret bakiye-gizli is-masked" data-reveal aria-pressed="false"><small>Toplam açık bakiye</small> <b><?= money($openBalance) ?></b> <span><?= icon('eye') ?></span></button><?php endif; ?></div>
  <div class="today-cols">
    <div class="today-col">
      <b class="today-col-head tone-wine">Bugün teslim sözü verilen</b>
      <?php if (!$todayDue): ?><p class="muted small">Yok.</p><?php endif; ?>
      <?php foreach ($todayDue as $o): ?>
        <a class="today-row" href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['first_name'] . ' ' . $o['last_name']) ?><small><?= order_no((int) $o['id']) ?></small></a>
      <?php endforeach; ?>
    </div>
    <div class="today-col">
      <b class="today-col-head tone-red">Teslimi geciken (<?= $overdue ?>)</b>
      <?php if (!$overdueList): ?><p class="muted small">Yok.</p><?php endif; ?>
      <?php foreach ($overdueList as $o): ?>
        <a class="today-row" href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['first_name'] . ' ' . $o['last_name']) ?><small><?= date_tr($o['promised_date']) ?> · <?= days_overdue($o['promised_date']) ?> gün</small></a>
      <?php endforeach; ?>
      <?php if ($overdue > 6): ?><a class="today-more" href="?durum=aktif">Tümünü gör →</a><?php endif; ?>
    </div>
    <div class="today-col">
      <b class="today-col-head tone-green">Hazır, haber verilmeli</b>
      <?php if (!$readyList): ?><p class="muted small">Yok.</p><?php endif; ?>
      <?php foreach ($readyList as $o): ?>
        <a class="today-row" href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['first_name'] . ' ' . $o['last_name']) ?><small><?= days_since($o['updated_at']) ?> gündür hazır</small></a>
      <?php endforeach; ?>
      <?php if (count($readyList) >= 6): ?><a class="today-more" href="?durum=hazir">Tümünü gör →</a><?php endif; ?>
    </div>
    <?php if (can_see_amounts()): ?>
    <div class="today-col">
      <b class="today-col-head tone-amber">Bekleyen tahsilat</b>
      <?php if (!$dueBalanceList): ?><p class="muted small">Yok.</p><?php endif; ?>
      <?php foreach ($dueBalanceList as $o): ?>
        <a class="today-row" href="order.php?id=<?= (int) $o['id'] ?>"><?= e($o['first_name'] . ' ' . $o['last_name']) ?><small><?= money($o['balance']) ?></small></a>
      <?php endforeach; ?>
      <a class="today-more" href="?durum=borclu">Tümünü gör →</a>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <div class="toolbar">
    <form class="search" method="get" role="search">
      <input type="hidden" name="durum" value="<?= e($filter) ?>">
      <?= icon('search') ?>
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Ad, telefon veya sipariş no ile ara" aria-label="Sipariş ara">
    </form>
    <nav class="chips" aria-label="Durum filtresi">
      <?php foreach ($filters as $key => $label): ?>
        <?php $chipSayi = ['aktif' => (int) ($stats['active'] ?? 0), 'bekleyen' => (int) ($stats['waiting'] ?? 0), 'hazir' => (int) ($stats['ready'] ?? 0)][$key] ?? 0;   /* 4.40.0: sayı kutuları yerine */ ?>
        <a class="chip <?= $filter === $key ? 'active' : '' ?>" href="<?= e(url_with(['durum' => $key, 'sayfa' => null])) ?>"><?= e($label) ?><?= $chipSayi ? ' <em>' . $chipSayi . '</em>' : '' ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <?php if (!$orders): ?>
    <?= empty_state($search !== '' ? '“' . $search . '” için sonuç yok' : 'Bu listede sipariş yok', 'Filtreyi değiştirin veya yeni sipariş oluşturun.', '<a class="btn" href="order-new.php">' . icon('plus') . ' Yeni sipariş</a>') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table rows-link">
        <thead>
          <tr>
            <th>Müşteri</th>
            <th class="hide-sm">Cam</th>
            <th>Durum</th>
            <th class="hide-md">Teslim</th>
            <?php if (can_see_amounts()): ?><th class="num">Tutar</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o):
              $late = $o['promised_date'] && $o['promised_date'] < date('Y-m-d') && !in_array($o['order_stage'], ['hazirlandi', 'teslim_edildi', 'iptal'], true); ?>
            <tr>
              <td>
                <a class="cell-link" href="order.php?id=<?= (int) $o['id'] ?>">
                  <span class="avatar"><?= e(initials($o['first_name'], $o['last_name'])) ?></span>
                  <span class="cell-main"><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
                    <small><?= order_no((int) $o['id']) ?> · <?= date_tr($o['created_at']) ?><?= $o['phone'] ? ' · ' . e(phone_display($o['phone'])) : '' ?></small></span>
                </a>
              </td>
              <td class="hide-sm"><?= e($o['lens_type'] ?: ($o['transaction_type'] === 'tamir' ? ($o['service_type'] ? service_type_label($o['service_type']) : transaction_type_label('tamir')) : ($o['transaction_type'] !== 'gozluk' ? transaction_type_label($o['transaction_type']) : '—'))) ?>
                <?php if ((int) $o['missing'] > 0): ?><br><span class="badge tone-red sm"><?= (int) $o['missing'] ?> cam eksik</span><?php endif; ?></td>
              <td><?= stage_badge($o['order_stage']) ?></td>
              <td class="hide-md <?= $late ? 'text-danger' : '' ?>"><?= $o['promised_date'] ? date_tr($o['promised_date']) . ($late ? ' · gecikti' : '') : '—' ?></td>
              <?php if (can_see_amounts()): ?>
                <td class="num"><b><?= money($o['total_amount']) ?></b>
                  <?php if ((float) $o['balance'] > 0.009 && $o['order_stage'] !== 'iptal'): ?><small class="text-danger">Kalan <?= money($o['balance']) ?></small>
                  <?php elseif ((float) $o['total_amount'] > 0): ?><small class="text-ok">Ödendi</small><?php endif; ?>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="list-foot"><span class="muted"><?= $total ?> kayıt</span><?= pagination_links($pg) ?></div>
  <?php endif; ?>
</section>
<?php /* 4.40.0: takım hedefi listenin altında, ince */ ?>
<?php $teamGoal = team_monthly_goal(); ?>
<section class="card team-goal-card ince" <?= $teamGoal['pct'] >= 100 ? 'data-celebrate-big="🎉 Bu ayın takım hedefi tamamlandı! Hepiniz harikasınız! 🎉" data-celebrate-once="teamgoal-' . date('Y-m') . '"' : '' ?>>
  <div class="team-goal-head"><span>🚀 Bu ayın takım hedefi</span><b><?= $teamGoal['current'] ?> / <?= $teamGoal['goal'] ?> sipariş</b></div>
  <div class="badge-progress lg"><i style="width:<?= $teamGoal['pct'] ?>%"></i></div>
  <small class="muted"><?= $teamGoal['pct'] >= 100 ? 'Hedef tamamlandı, tebrikler ekip! 🎉' : ('%' . $teamGoal['pct'] . ' tamamlandı — hep birlikte!') ?></small>
</section>
<?php page_end();
