<?php
/** @var array $rx  Reçete satırı (prescription_records) — near_* alanları varsa yakın satırı da gösterilir. */
$showAdd = ($rx['right_add'] ?? '') !== '' || ($rx['left_add'] ?? '') !== '';
$showPd = ($rx['right_pd'] ?? '') !== '' || ($rx['left_pd'] ?? '') !== '';
$showH = ($rx['right_height'] ?? '') !== '' || ($rx['left_height'] ?? '') !== '';
$dash = static fn($v) => ($v === null || $v === '') ? '<span class="muted">—</span>' : e($v);
?>
<div class="table-wrap">
<table class="rx-table">
  <thead><tr><th></th><th>SPH</th><th>CYL</th><th>AKS</th><?php if ($showAdd): ?><th>ADD</th><?php endif; ?><?php if ($showPd): ?><th>PD</th><?php endif; ?><?php if ($showH): ?><th>Yük.</th><?php endif; ?></tr></thead>
  <tbody>
    <?php foreach (['right' => 'Sağ (R)', 'left' => 'Sol (L)'] as $side => $label):
        $off = ($rx['lens_eyes'] ?? 'both') !== 'both' && ($rx['lens_eyes'] ?? '') !== $side; ?>
      <tr class="<?= $off ? 'off' : '' ?>">
        <th><?= $label ?></th>
        <td><?= $dash($rx[$side . '_sph']) ?></td>
        <td><?= $dash($rx[$side . '_cyl']) ?></td>
        <td><?= ($rx[$side . '_axis'] ?? '') !== '' ? e($rx[$side . '_axis']) . '°' : $dash('') ?></td>
        <?php if ($showAdd): ?><td><?= $dash($rx[$side . '_add']) ?></td><?php endif; ?>
        <?php if ($showPd): ?><td><?= $dash($rx[$side . '_pd']) ?></td><?php endif; ?>
        <?php if ($showH): ?><td><?= $dash($rx[$side . '_height']) ?></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>
<?php if (!empty($rx['near_right_sph']) || !empty($rx['near_left_sph'])): ?>
  <p class="small muted">Yakın: Sağ <b><?= e($rx['near_right_sph'] ?: '—') ?></b> · Sol <b><?= e($rx['near_left_sph'] ?: '—') ?></b><?= !empty($rx['near_lens_type']) ? ' · ' . e($rx['near_lens_type']) : '' ?><?= ($rx['pd'] ?? '') !== '' ? ' · PD ' . e($rx['pd']) : '' ?></p>
<?php elseif (($rx['pd'] ?? '') !== ''): ?>
  <p class="small muted">Toplam PD: <b><?= e($rx['pd']) ?> mm</b></p>
<?php endif; ?>
