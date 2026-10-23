<?php
/* 4.44.0 — Zarfsız atölye: iş emrindeki "Adım · Tamam · Paraf · Saat" tablosunun ekrandaki karşılığı.
   Girdi: $order (find_order), $atolyeDon ('' = sipariş sayfası, 'pano' = atölye panosu).
   Sıradaki adımın düğmesi kendi satırında; Kontrol satırı kutu / raf ister. Son adım geri alınabilir. */
declare(strict_types=1);

$oid = (int) $order['id'];
$adimlar = atolye_adimlari($oid);
$siradaki = atolye_siradaki($order, $adimlar);
$sonAdim = $adimlar ? array_key_last($adimlar) : '';
$camGeldi = atolye_cam_geldi($oid);
$cerceveBekliyor = (int) ($order['own_frame_pending'] ?? 0) === 1;
$raf = (string) ($order['raf_yeri'] ?? '');
$gizli = csrf_field() . '<input type="hidden" name="order_id" value="' . $oid . '">'
    . ($atolyeDon !== '' ? '<input type="hidden" name="return" value="' . e($atolyeDon) . '">' : '');
$sira = 1;
?>
<ol class="is-adimlar">
  <li class="<?= $camGeldi['durum'] === 'eksik' ? 'uyari' : 'bitti' ?>">
    <i><?= sprintf('%02d', $sira++) ?></i><b>Cam geldi</b>
    <span class="paraf"><?= match ($camGeldi['durum']) {
        'eksik' => '<em>eksik cam var</em>',
        'yok'   => '—',
        default => 'Depo' . ($camGeldi['saat'] !== '' ? ' · ' . e(date('d.m H:i', strtotime($camGeldi['saat']))) : ''),
    } ?></span>
  </li>
  <?php foreach (ATOLYE_ADIMLARI as $k => $ad): $a = $adimlar[$k] ?? null; ?>
    <li class="<?= $a ? 'bitti' : ($k === $siradaki ? 'simdi' : '') ?>">
      <i><?= sprintf('%02d', $sira++) ?></i><b><?= e($ad) ?></b>
      <?php if ($a): ?>
        <span class="paraf" title="<?= e($a['ad']) ?>"><?= e(atolye_damga($a)) ?></span>
        <?php if ($k === $sonAdim && (is_super() || (int) $a['user_id'] === (int) current_user()['id'])): ?>
          <form method="post" action="order.php" class="geri"><?= $gizli ?><input type="hidden" name="action" value="atolye_geri"><input type="hidden" name="adim" value="<?= e($k) ?>"><button class="linkish" title="Bu adımı geri al">geri al</button></form>
        <?php endif; ?>
      <?php elseif ($k === $siradaki && !($k === 'montaj' && $cerceveBekliyor)): ?>
        <form method="post" action="order.php" class="adim-at"><?= $gizli ?><input type="hidden" name="action" value="atolye_adim"><input type="hidden" name="adim" value="<?= e($k) ?>">
          <?php if ($k === 'kontrol'): ?>
            <label class="raf-gir"><span>Kutu / raf</span><input name="raf_yeri" value="<?= e($raf !== '' ? $raf : (string) atolye_bos_kutu()) ?>" maxlength="20" required autocomplete="off"></label>
          <?php endif; ?>
          <button class="btn btn-primary btn-sm"><?= icon('check') ?> <?= $k === 'kontrol' ? 'Kontrol tamam · hazır' : ($k === 'haber' ? 'Haber verildi' : e($ad) . ' tamam') ?></button>
        </form>
      <?php elseif ($k === 'montaj' && $k === $siradaki): ?>
        <span class="paraf"><em>çerçeve bekleniyor</em></span>
      <?php else: ?>
        <span class="paraf">—</span>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
</ol>
