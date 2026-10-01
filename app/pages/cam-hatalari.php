<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.15.0 — Hatalı camlar: dönem raporu ve bekleyen laboratuvar iadeleri
   ========================================================================== */

$me = require_login();
ozellik_gereksin('cam_hata');

$donemler = ['ay' => 'Bu ay', 'gecen' => 'Geçen ay', '90' => 'Son 90 gün', '365' => 'Son 12 ay'];
$donem = query('donem', 'ay');
if (!isset($donemler[$donem])) {
    $donem = 'ay';
}
[$bas, $bit] = match ($donem) {
    'gecen' => [date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('first day of last month'))],
    '90'    => [date('Y-m-d', strtotime('-90 days')), date('Y-m-d')],
    '365'   => [date('Y-m-d', strtotime('-365 days')), date('Y-m-d')],
    default => [date('Y-m-01'), date('Y-m-d')],
};
$r = cam_hata_rapor($bas, $bit);
$liste = rows(
    'SELECT h.*, s.name AS tedarikci, u.full_name AS sorumlu, o.first_name, o.last_name FROM cam_hatalari h
       LEFT JOIN suppliers s ON s.id = h.supplier_id LEFT JOIN user_accounts u ON u.id = h.sorumlu_id LEFT JOIN orders o ON o.id = h.order_id
      WHERE h.created_at >= ? AND h.created_at <= ? ORDER BY h.id DESC LIMIT 200',
    [$bas . ' 00:00:00', $bit . ' 23:59:59']
);
$bekleyen = is_super() ? rows("SELECT h.*, s.name AS tedarikci FROM cam_hatalari h LEFT JOIN suppliers s ON s.id = h.supplier_id WHERE h.alacak_durum = 'bekliyor' ORDER BY h.id") : [];
$gor = can_see_amounts();

page_start('Hatalı camlar', 'cam-hatalari');
page_header('Hatalı camlar', date_tr($bas) . ' – ' . date_tr($bit) . ' · yeniden yapılan camlar, sebepleri ve maliyeti', '', '', 'Atölye');
?>
<div class="btn-row" style="margin-bottom:14px">
  <?php foreach ($donemler as $k => $ad): ?><a class="btn btn-sm <?= $k === $donem ? 'btn-primary' : '' ?>" href="cam-hatalari.php?donem=<?= e($k) ?>"><?= e($ad) ?></a><?php endforeach; ?>
</div>
<section class="stats">
  <div class="stat <?= $r['adet'] ? 'tone-amber' : '' ?>"><small>Yeniden yapım</small><b><?= $r['adet'] ?></b><span>kayıt</span></div>
  <?php if ($gor): ?>
    <div class="stat <?= $r['maliyet'] > 0 ? 'tone-red' : '' ?>"><small>Maliyet</small><b><?= money($r['maliyet']) ?></b><span>iade düşülmeden</span></div>
    <div class="stat tone-green"><small>Laboratuvardan iade</small><b><?= money($r['iade_alinan']) ?></b><span><?= money($r['iade_bekleyen']) ?> bekleniyor</span></div>
    <div class="stat"><small>Net kayıp</small><b><?= money($r['maliyet'] - $r['iade_alinan']) ?></b></div>
  <?php endif; ?>
</section>

<?php if ($bekleyen): ?>
<section class="card">
  <div class="card-head"><h2>Bekleyen laboratuvar iadeleri</h2><span class="badge tone-amber"><?= count($bekleyen) ?></span></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Sipariş</th><th>Tedarikçi</th><th class="num">Tutar</th><th class="hide-sm">Tarih</th></tr></thead>
    <tbody><?php foreach ($bekleyen as $b): ?>
      <tr><td><a class="link" href="order.php?id=<?= (int) $b['order_id'] ?>#cam-hata"><?= e(order_no((int) $b['order_id'])) ?></a></td><td><?= e((string) $b['tedarikci']) ?></td><td class="num"><?= money($b['alacak_tutar']) ?></td><td class="hide-sm"><?= e(date_tr((string) $b['created_at'])) ?> · <?= days_since((string) $b['created_at']) ?> gün</td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <p class="hint">İade gelince siparişin "Hatalı cam" bölümünden "İade alındı" deyin; tutar tedarikçi carisine alacak olarak düşer.</p>
</section>
<?php endif; ?>

<?php if ($r['adet']): ?>
<div class="grid cols-2" style="gap:14px">
  <section class="card">
    <div class="card-head"><h2>Sebebe göre</h2></div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($r['neden'] as $n): ?><tr><td><?= e(cam_hata_nedenleri()[$n['k']] ?? $n['k']) ?></td><td class="num"><b><?= (int) $n['adet'] ?></b></td><?php if ($gor): ?><td class="num"><?= money($n['maliyet']) ?></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
  <section class="card">
    <div class="card-head"><h2>Tedarikçiye göre</h2><a class="link" href="tedarikci-karne.php">Karne</a></div>
    <?php if (!$r['tedarikci']): ?><p class="muted small">Tedarikçi bağlı kayıt yok.</p><?php else: ?>
    <div class="table-wrap"><table class="table"><thead><tr><th>Tedarikçi</th><th class="num">Lab hatası</th><th class="num">Toplam</th></tr></thead><tbody>
      <?php foreach ($r['tedarikci'] as $t): ?><tr><td><?= e((string) $t['k']) ?></td><td class="num <?= (int) $t['lab'] ? 'text-danger' : '' ?>"><?= (int) $t['lab'] ?></td><td class="num"><?= (int) $t['adet'] ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
  </section>
  <?php if ($r['sorumlu'] && is_super()): ?>
  <section class="card">
    <div class="card-head"><h2>Mağaza kaynaklı (ölçü, montaj, kırılma)</h2></div>
    <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($r['sorumlu'] as $s): ?><tr><td><?= e((string) $s['k']) ?></td><td class="num"><b><?= (int) $s['adet'] ?></b></td><?php if ($gor): ?><td class="num"><?= money($s['maliyet']) ?></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div>
  </section>
  <?php endif; ?>
</div>
<?php endif; ?>

<section class="card">
  <div class="card-head"><h2>Kayıtlar</h2></div>
  <?php if (!$liste): ?>
    <?= empty_state('Bu dönemde hatalı cam yok', 'Kayıtlar siparişin "Hatalı cam / yeniden yapım" bölümünden girilir.') ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Sipariş</th><th>Sebep</th><th class="hide-sm">Tedarikçi · sorumlu</th><?php if ($gor): ?><th class="num">Maliyet</th><?php endif; ?><th>İade</th></tr></thead>
    <tbody><?php foreach ($liste as $h): [$aAd, $aTon] = cam_hata_alacak_durumlari()[$h['alacak_durum']] ?? ['—', 'gray']; ?>
      <tr>
        <td><a class="link" href="order.php?id=<?= (int) $h['order_id'] ?>#cam-hata"><?= e(order_no((int) $h['order_id'])) ?></a><small class="block muted"><?= e(trim($h['first_name'] . ' ' . $h['last_name'])) ?> · <?= e(date_tr((string) $h['created_at'])) ?></small></td>
        <td><?= e(cam_hata_nedenleri()[$h['neden']] ?? $h['neden']) ?><?= $h['aciklama'] ? '<small class="block muted">' . e((string) $h['aciklama']) . '</small>' : '' ?></td>
        <td class="hide-sm"><small><?= e((string) ($h['tedarikci'] ?: '—')) ?><?= $h['sorumlu'] ? ' · ' . e((string) $h['sorumlu']) : '' ?></small></td>
        <?php if ($gor): ?><td class="num"><?= money($h['maliyet']) ?></td><?php endif; ?>
        <td><?php if ($h['alacak_durum'] !== 'yok'): ?><span class="badge tone-<?= e($aTon) ?>"><?= e($aAd) ?></span><?php else: ?>—<?php endif; ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php page_end();
