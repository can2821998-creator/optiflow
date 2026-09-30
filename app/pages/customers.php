<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$search = mb_substr(query('q'), 0, 80);
$where = '';
$params = [];
if ($search !== '') {
    $digits = preg_replace('/\D+/', '', $search) ?? '';
    $where = "WHERE CONCAT(c.first_name, ' ', c.last_name) LIKE ?";
    $params[] = '%' . $search . '%';
    if (strlen($digits) >= 4) {
        $where .= ' OR c.phone LIKE ?';
        $params[] = '%' . ltrim($digits, '0') . '%';
    }
}
$total = (int) scalar("SELECT COUNT(*) FROM customers c $where", $params);
$pg = paginate($total, 30);
$customers = rows(
    "SELECT c.*, s.order_count, s.last_order, s.active_count, s.balance
     FROM customers c
     LEFT JOIN (
        SELECT o.customer_id, COUNT(*) AS order_count, MAX(o.created_at) AS last_order,
               SUM(o.order_stage NOT IN ('teslim_edildi','iptal')) AS active_count,
               SUM(CASE WHEN o.order_stage <> 'iptal' THEN o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount ELSE 0 END) AS balance
        FROM orders o " . PAID_JOIN . " GROUP BY o.customer_id
     ) s ON s.customer_id = c.id
     $where
     ORDER BY COALESCE(s.last_order, c.created_at) DESC
     LIMIT {$pg['per']} OFFSET {$pg['offset']}",
    $params
);

page_start('Müşteriler', 'customers');
page_header('Müşteriler', $total . ' kayıtlı müşteri', '<a class="btn btn-primary" href="order-new.php">' . icon('plus') . ' Yeni sipariş</a>', '', 'Müşteri defteri');
?>
<section class="card">
  <div class="toolbar">
    <form class="search" method="get" role="search">
      <?= icon('search') ?>
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Ad soyad veya telefon" aria-label="Müşteri ara" autofocus>
    </form>
  </div>
  <?php if (!$customers): ?>
    <?= empty_state('Müşteri bulunamadı', 'Yeni müşteri, ilk siparişiyle birlikte oluşturulur.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Müşteri</th><th class="hide-sm">Telefon</th><th>Sipariş</th><th class="hide-md">Son sipariş</th><?php if (can_see_amounts()): ?><th class="num">Bakiye</th><?php endif; ?></tr></thead>
        <tbody>
          <?php foreach ($customers as $c): ?>
            <tr>
              <td><a class="cell-link" href="customer.php?id=<?= (int) $c['id'] ?>"><span class="avatar"><?= e(initials($c['first_name'], $c['last_name'])) ?></span>
                <span class="cell-main"><b><?= e($c['first_name'] . ' ' . $c['last_name']) ?></b><small class="show-sm"><?= e(phone_display($c['phone'])) ?></small></span></a></td>
              <td class="hide-sm"><?= $c['phone'] ? e(phone_display($c['phone'])) : '<span class="muted">—</span>' ?></td>
              <td><?= (int) $c['order_count'] ?><?php if ((int) $c['active_count']): ?> <span class="badge tone-blue sm"><?= (int) $c['active_count'] ?> aktif</span><?php endif; ?></td>
              <td class="hide-md"><?= date_tr($c['last_order']) ?></td>
              <?php if (can_see_amounts()): ?><td class="num <?= (float) $c['balance'] > 0.009 ? 'text-danger' : 'muted' ?>"><?= (float) $c['balance'] > 0.009 ? money($c['balance']) : '—' ?></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="list-foot"><span class="muted"><?= $total ?> kayıt</span><?= pagination_links($pg) ?></div>
  <?php endif; ?>
</section>
<?php page_end();
