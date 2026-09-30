<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_super();

$where = [];
$params = [];
$userId = query_int('user');
$action = query('action');
$entity = query('entity');
$entityId = query_int('id');
$from = query('from');
$until = query('until');
if ($userId) { $where[] = 'user_id = ?'; $params[] = $userId; }
if ($action !== '' && isset(audit_actions()[$action])) { $where[] = 'action = ?'; $params[] = $action; }
if ($entity !== '' && $entityId) { $where[] = 'entity = ? AND entity_id = ?'; $params[] = $entity; $params[] = $entityId; }
if (valid_date($from)) { $where[] = 'created_at >= ?'; $params[] = $from . ' 00:00:00'; }
if (valid_date($until)) { $where[] = 'created_at < ?'; $params[] = date('Y-m-d', strtotime($until . ' +1 day')); }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int) scalar("SELECT COUNT(*) FROM audit_log $whereSql", $params);
$pg = paginate($total, 50);
$logs = rows("SELECT * FROM audit_log $whereSql ORDER BY id DESC LIMIT {$pg['per']} OFFSET {$pg['offset']}", $params);
$users = rows('SELECT id, full_name FROM user_accounts ORDER BY full_name');

function log_details(?string $json): string
{
    if (!$json) {
        return '';
    }
    $d = json_decode($json, true);
    if (!is_array($d)) {
        return e($json);
    }
    $parts = [];
    foreach ($d as $k => $v) {
        if (is_array($v) && array_key_exists('önce', $v)) {
            $parts[] = '<span class="kvp"><b>' . e($k) . '</b> ' . e(is_scalar($v['önce']) || $v['önce'] === null ? (string) $v['önce'] : json_encode($v['önce'])) . ' → ' . e(is_scalar($v['sonra']) || $v['sonra'] === null ? (string) $v['sonra'] : json_encode($v['sonra'])) . '</span>';
        } else {
            $parts[] = '<span class="kvp"><b>' . e($k) . '</b> ' . e(is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE)) . '</span>';
        }
    }
    return implode(' ', $parts);
}

page_start('İşlem geçmişi', 'logs');
page_header('İşlem geçmişi', 'Kim, ne zaman, neyi değiştirdi. Kayıtlar silinemez.', '', '', 'Denetim');
?>
<section class="card">
  <form class="filters" method="get">
    <label class="field"><span>Kullanıcı</span><select name="user"><option value="">Tümü</option><?php foreach ($users as $u): ?><option value="<?= (int) $u['id'] ?>" <?= $userId === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option><?php endforeach; ?></select></label>
    <label class="field"><span>İşlem</span><select name="action"><option value="">Tümü</option><?= select_options(audit_actions(), $action) ?></select></label>
    <label class="field"><span>Başlangıç</span><input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="field"><span>Bitiş</span><input type="date" name="until" value="<?= e($until) ?>"></label>
    <?php if ($entity && $entityId): ?><input type="hidden" name="entity" value="<?= e($entity) ?>"><input type="hidden" name="id" value="<?= $entityId ?>"><?php endif; ?>
    <div class="filter-actions"><button class="btn">Filtrele</button><a class="btn btn-ghost" href="logs.php">Temizle</a></div>
  </form>
  <?php if ($entity && $entityId): ?><p class="small muted">Yalnızca <?= $entity === 'order' ? 'sipariş ' . order_no($entityId) : e($entity) . ' #' . $entityId ?> kayıtları gösteriliyor.</p><?php endif; ?>

  <?php if (!$logs): ?>
    <?= empty_state('Kayıt yok') ?>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Zaman</th><th>Kullanıcı</th><th>İşlem</th><th>Ayrıntı</th></tr></thead>
      <tbody>
        <?php foreach ($logs as $l): ?>
          <tr>
            <td class="nowrap"><?= date_tr($l['created_at'], true) ?></td>
            <td><?= e($l['user_name']) ?><small class="block muted"><?= e($l['ip']) ?></small></td>
            <td><?= e(audit_label($l['action'])) ?>
              <?php if ($l['entity'] === 'order' && $l['entity_id']): ?><small class="block"><a class="link" href="order.php?id=<?= (int) $l['entity_id'] ?>"><?= order_no((int) $l['entity_id']) ?></a></small>
              <?php elseif ($l['entity'] === 'customer' && $l['entity_id']): ?><small class="block"><a class="link" href="customer.php?id=<?= (int) $l['entity_id'] ?>">Müşteri #<?= (int) $l['entity_id'] ?></a></small><?php endif; ?></td>
            <td class="details"><?= log_details($l['details']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="list-foot"><span class="muted"><?= $total ?> kayıt</span><?= pagination_links($pg) ?></div>
  <?php endif; ?>
</section>
<?php page_end();
