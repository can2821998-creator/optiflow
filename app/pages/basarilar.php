<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

$staffList = assignable_staff();
$star = star_of_month();
$viewId = query_int('u') ?: (int) $user['id'];
if (!in_array($viewId, array_column($staffList, 'id'), true)) {
    $viewId = (int) $user['id'];
}
$viewName = '';
foreach ($staffList as $s) { if ((int) $s['id'] === $viewId) { $viewName = $s['full_name']; } }

$achievements = staff_achievements($viewId);
$unlockedCount = count(array_filter($achievements, static fn($a) => $a['unlocked']));
$totalCount = count($achievements);
$xp = staff_xp($viewId);
$title = staff_title($xp);
$secrets = staff_secret_achievements($viewId);
$calendar = staff_streak_calendar($viewId, 28);
$maxCal = max(1, ...array_column($calendar, 'cnt'));

page_start('Başarılar', 'basarilar');
page_header(
    'Başarılar',
    e($viewName) . ' · ' . $unlockedCount . ' / ' . $totalCount . ' rozet açıldı',
    '<form method="get" class="inline"><select name="u" data-auto-submit>'
        . select_options(array_column($staffList, 'full_name', 'id'), (string) $viewId)
        . '</select></form>',
    '', 'Ekip'
);
?>
<?php if ($star && $star === $viewId): ?>
  <div class="alert alert-info" style="display:flex;align-items:center;gap:10px">
    <span style="font-size:22px">🌟</span> Bu ayın en çok satan personeli — tebrikler!
  </div>
<?php endif; ?>

<section class="card level-card">
  <div class="level-icon"><?= $title['icon'] ?></div>
  <div class="level-body">
    <div class="level-head"><b><?= e($title['name']) ?></b><span><?= $xp ?> XP</span></div>
    <div class="badge-progress lg"><i style="width:<?= $title['pct'] ?>%"></i></div>
    <small class="muted"><?= $title['next'] ? ('Sonraki unvan: ' . e($title['next']['icon'] . ' ' . $title['next']['name']) . ' — %' . $title['pct']) : 'En yüksek unvana ulaşıldı 🎉' ?></small>
  </div>
</section>

<div class="badge-grid">
  <?php foreach ($achievements as $key => $a): $def = $a['def']; $pct = min(100, round($a['current'] / max(1, $def['goal']) * 100)); ?>
    <div class="badge-card <?= $a['unlocked'] ? 'is-unlocked' : '' ?>">
      <span class="badge-icon"><?= $a['unlocked'] ? $def['icon'] : '🔒' ?></span>
      <b class="badge-title"><?= e($def['title']) ?></b>
      <span class="badge-desc"><?= e($def['desc']) ?></span>
      <div class="badge-progress"><i style="width:<?= $pct ?>%"></i></div>
      <small class="badge-count"><?= min((int) $a['current'], (int) $def['goal']) ?> / <?= (int) $def['goal'] ?></small>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($secrets): ?>
  <section class="card" style="margin-top:22px">
    <div class="card-head"><h2>🎁 Gizli rozetler</h2><small class="muted">Beklenmedik anlarda açılır</small></div>
    <div class="badge-grid">
      <?php foreach ($secrets as $def): ?>
        <div class="badge-card is-unlocked is-secret">
          <span class="badge-icon"><?= $def['icon'] ?></span>
          <b class="badge-title"><?= e($def['title']) ?></b>
          <span class="badge-desc"><?= e($def['desc']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<section class="card" style="margin-top:22px">
  <div class="card-head"><h2>Son 28 gün</h2><small class="muted">Sipariş yoğunluğu</small></div>
  <div class="streak-cal">
    <?php foreach ($calendar as $c): $lvl = $c['cnt'] === 0 ? 0 : min(4, (int) ceil($c['cnt'] / $maxCal * 4)); ?>
      <span class="streak-cell lvl-<?= $lvl ?>" title="<?= date_tr($c['date']) ?> · <?= $c['cnt'] ?> sipariş"></span>
    <?php endforeach; ?>
  </div>
</section>

<section class="card" style="margin-top:22px">
  <div class="card-head"><h2>Tüm ekip</h2><small class="muted">Kim kaç rozet açtı</small></div>
  <div class="hbars">
    <?php
    $ranking = [];
    foreach ($staffList as $s) {
        $a = staff_achievements((int) $s['id']);
        $ranking[] = ['name' => $s['full_name'], 'id' => (int) $s['id'], 'count' => count(array_filter($a, static fn($x) => $x['unlocked']))];
    }
    usort($ranking, static fn($x, $y) => $y['count'] <=> $x['count']);
    foreach ($ranking as $r): ?>
      <div class="hbar"><span><a class="link" href="basarilar.php?u=<?= $r['id'] ?>"><?= e($r['name']) ?></a><?= $star === $r['id'] ? ' 🌟' : '' ?></span><div class="hbar-track"><i style="width:<?= round($r['count'] / max(1, $totalCount) * 100) ?>%"></i></div><b><?= $r['count'] ?> / <?= $totalCount ?></b></div>
    <?php endforeach; ?>
  </div>
</section>
<?php page_end();
