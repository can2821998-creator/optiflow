<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_super();

/* ==========================================================================
   Kârlılık ve prim
   Satış − (cam maliyeti + çerçeve maliyeti) = brüt kâr. Cam maliyeti
   tedarikçi faturalarından cam cam gelir; çerçeve maliyeti önce stok
   kaydından, yoksa markanın ortalama maliyetinden alınır.
   ========================================================================== */

$presets = [
    'bu_ay'    => ['Bu ay', date('Y-m-01'), date('Y-m-d')],
    'gecen_ay' => ['Geçen ay', date('Y-m-01', strtotime('first day of last month')), date('Y-m-t', strtotime('last day of last month'))],
    'son_90'   => ['Son 90 gün', date('Y-m-d', strtotime('-89 days')), date('Y-m-d')],
    'bu_yil'   => ['Bu yıl', date('Y-01-01'), date('Y-m-d')],
];
$p = query('p', 'bu_ay');
$from = query('from');
$to = query('to');
if (!valid_date($from) || !valid_date($to)) {
    [, $from, $to] = $presets[$p] ?? $presets['bu_ay'];
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}
$range = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];

/* Baz: sipariş tarihi mi, teslim tarihi mi? */
$baz = query('baz') === 'teslim' ? 'teslim' : 'siparis';
$tarihAlani = $baz === 'teslim' ? 'o.delivered_at' : 'o.created_at';

/* ---------- Sipariş bazında kâr ---------- */
$siparisler = rows(
    "SELECT o.id, o.created_at, o.delivered_at, o.order_stage, o.lens_type, o.total_amount,
            c.first_name, c.last_name,
            u.full_name AS personel, o.created_by,
            COALESCE(cam.maliyet, 0) AS cam_maliyet,
            COALESCE(cam.eksik, 0) AS cam_eksik,
            COALESCE(fi.cost, fp.avg_cost, 0) AS cerceve_maliyet
       FROM orders o
       JOIN customers c ON c.id = o.customer_id
       LEFT JOIN user_accounts u ON u.id = o.created_by
       LEFT JOIN frame_items fi ON fi.id = o.frame_item_id
       LEFT JOIN frame_products fp ON fp.id = o.frame_product_id
       LEFT JOIN (
            SELECT r.order_id,
                   SUM(COALESCE(i.unit_cost, 0)) AS maliyet,
                   SUM(i.unit_cost IS NULL) AS eksik
              FROM prescription_lens_items i
              JOIN prescription_records r ON r.id = i.prescription_id
             GROUP BY r.order_id
       ) cam ON cam.order_id = o.id
      WHERE o.order_stage <> 'iptal'
        AND $tarihAlani >= ? AND $tarihAlani < ?
      ORDER BY (o.total_amount - COALESCE(cam.maliyet, 0) - COALESCE(fi.cost, fp.avg_cost, 0)) DESC
      LIMIT 500",
    $range
);

$toplam = ['satis' => 0.0, 'cam' => 0.0, 'cerceve' => 0.0, 'kar' => 0.0, 'adet' => 0, 'eksik' => 0];
$camTipi = [];
$personel = [];
foreach ($siparisler as $s) {
    $kar = (float) $s['total_amount'] - (float) $s['cam_maliyet'] - (float) $s['cerceve_maliyet'];
    $toplam['satis']   += (float) $s['total_amount'];
    $toplam['cam']     += (float) $s['cam_maliyet'];
    $toplam['cerceve'] += (float) $s['cerceve_maliyet'];
    $toplam['kar']     += $kar;
    $toplam['adet']++;
    if ((int) $s['cam_eksik'] > 0) {
        $toplam['eksik']++;
    }

    $tip = (string) ($s['lens_type'] ?: 'Belirtilmemiş');
    $camTipi[$tip] ??= ['adet' => 0, 'satis' => 0.0, 'kar' => 0.0];
    $camTipi[$tip]['adet']++;
    $camTipi[$tip]['satis'] += (float) $s['total_amount'];
    $camTipi[$tip]['kar'] += $kar;

    $pid = (int) ($s['created_by'] ?: 0);
    $personel[$pid] ??= ['ad' => (string) ($s['personel'] ?: 'Bilinmiyor'), 'adet' => 0, 'satis' => 0.0, 'kar' => 0.0];
    $personel[$pid]['adet']++;
    $personel[$pid]['satis'] += (float) $s['total_amount'];
    $personel[$pid]['kar'] += $kar;
}
uasort($camTipi, static fn($a, $b) => $b['kar'] <=> $a['kar']);
uasort($personel, static fn($a, $b) => $b['satis'] <=> $a['satis']);

/* Prim oranları */
$genelOran = (float) str_replace(',', '.', setting('commission_rate', '0'));
$oranlar = [];
foreach (rows('SELECT id, full_name, commission_rate FROM user_accounts') as $u) {
    $oranlar[(int) $u['id']] = $u['commission_rate'] !== null ? (float) $u['commission_rate'] : $genelOran;
}

/* Dönem giderleri ve sipariş dışı gelirler */
$gider = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date >= ? AND expense_date <= ?', [$from, $to]);
$ekGelir = 0.0;
try {
    $ekGelir = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM revenues WHERE revenue_date >= ? AND revenue_date <= ?', [$from, $to]);
} catch (Throwable) {
    $ekGelir = 0.0;
}
$primToplam = 0.0;
foreach ($personel as $pid => $v) {
    $primToplam += $v['satis'] * (($oranlar[$pid] ?? $genelOran) / 100);
}
$net = $toplam['kar'] + $ekGelir - $gider - $primToplam;
$marj = $toplam['satis'] > 0 ? $toplam['kar'] / $toplam['satis'] * 100 : 0;

page_start('Kârlılık', 'kar');
page_header(
    'Kârlılık ve prim',
    date_tr($from) . ' – ' . date_tr($to) . ' · ' . ($baz === 'teslim' ? 'teslim tarihine göre' : 'sipariş tarihine göre'),
    '', '', 'Yönetim'
);
?>

<section class="card">
  <form method="get" class="grid cols-4" style="align-items:end">
    <label class="field"><span>Başlangıç</span><input type="date" name="from" value="<?= e($from) ?>"></label>
    <label class="field"><span>Bitiş</span><input type="date" name="to" value="<?= e($to) ?>"></label>
    <label class="field"><span>Hesap bazı</span>
      <select name="baz">
        <option value="siparis" <?= $baz === 'siparis' ? 'selected' : '' ?>>Sipariş tarihi</option>
        <option value="teslim" <?= $baz === 'teslim' ? 'selected' : '' ?>>Teslim tarihi</option>
      </select>
    </label>
    <div class="form-actions"><button class="btn btn-primary">Uygula</button></div>
  </form>
  <div class="chips" style="margin-top:12px">
    <?php foreach ($presets as $k => $v): ?>
      <a class="chip" href="kar.php?p=<?= e($k) ?>&baz=<?= e($baz) ?>"><?= e($v[0]) ?></a>
    <?php endforeach; ?>
  </div>
</section>

<section class="stats">
  <div class="stat"><small>Satış</small><b><?= e(money($toplam['satis'])) ?></b><span><?= (int) $toplam['adet'] ?> işlem</span></div>
  <div class="stat tone-amber"><small>Maliyet</small><b><?= e(money($toplam['cam'] + $toplam['cerceve'])) ?></b><span>cam <?= e(money($toplam['cam'])) ?> · çerçeve <?= e(money($toplam['cerceve'])) ?></span></div>
  <div class="stat <?= $toplam['kar'] >= 0 ? 'tone-green' : 'tone-red' ?>"><small>Brüt kâr</small><b><?= e(money($toplam['kar'])) ?></b><span>marj %<?= number_format($marj, 1, ',', '.') ?></span></div>
  <div class="stat <?= $net >= 0 ? '' : 'tone-red' ?>"><small>Gider ve prim sonrası</small><b><?= e(money($net)) ?></b><span>gider <?= e(money($gider)) ?> · prim <?= e(money($primToplam)) ?></span></div>
</section>

<?php if ($toplam['eksik']): ?>
  <div class="alert alert-warn"><span><b><?= (int) $toplam['eksik'] ?> siparişte</b> henüz faturası girilmemiş cam var; bu siparişlerin kârı olduğundan yüksek görünür. Fatura girildikçe tablo kendiliğinden düzelir.</span></div>
<?php endif; ?>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2>Sipariş bazında kâr</h2><small class="muted">En kârlıdan en zararlıya</small></div>
      <?php if (!$siparisler): ?>
        <?= empty_state('Bu dönemde işlem yok', 'Tarih aralığını değiştirip tekrar deneyin.') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table rem-table">
            <thead>
              <tr>
                <th>Sipariş</th>
                <th class="hide-sm">Cam tipi</th>
                <th class="num">Satış</th>
                <th class="num hide-md">Maliyet</th>
                <th class="num">Kâr</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($siparisler, 0, 100) as $s):
              $maliyet = (float) $s['cam_maliyet'] + (float) $s['cerceve_maliyet'];
              $kar = (float) $s['total_amount'] - $maliyet; ?>
              <tr>
                <td>
                  <a class="cell-link" href="order.php?id=<?= (int) $s['id'] ?>">
                    <span class="cell-main">
                      <b><?= e($s['first_name'] . ' ' . $s['last_name']) ?></b>
                      <small class="block muted"><?= e(order_no((int) $s['id'])) ?> · <?= e(date_tr($baz === 'teslim' ? $s['delivered_at'] : $s['created_at'])) ?><?= (int) $s['cam_eksik'] ? ' · fatura eksik' : '' ?></small>
                    </span>
                  </a>
                </td>
                <td class="hide-sm"><?= e($s['lens_type'] ?: '—') ?><small class="block muted"><?= e((string) $s['personel']) ?></small></td>
                <td class="num"><?= e(money($s['total_amount'])) ?></td>
                <td class="num hide-md"><?= $maliyet > 0 ? e(money($maliyet)) : '<span class="muted">—</span>' ?></td>
                <td class="num"><b class="<?= $kar >= 0 ? 'text-ok' : 'text-danger' ?>"><?= e(money($kar)) ?></b></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if (count($siparisler) > 100): ?>
          <div class="list-foot"><span class="muted">İlk 100 sipariş gösteriliyor (dönemde <?= count($siparisler) ?> işlem var).</span></div>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Cam tipine göre</h2></div>
      <?php if (!$camTipi): ?>
        <p class="muted">Veri yok.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table compact">
            <thead><tr><th>Cam tipi</th><th class="num">Adet</th><th class="num">Satış</th><th class="num">Kâr</th><th class="num hide-sm">Marj</th></tr></thead>
            <tbody>
              <?php foreach ($camTipi as $ad => $v): ?>
                <tr>
                  <td><?= e($ad) ?></td>
                  <td class="num"><?= (int) $v['adet'] ?></td>
                  <td class="num"><?= e(money($v['satis'])) ?></td>
                  <td class="num"><b class="<?= $v['kar'] >= 0 ? 'text-ok' : 'text-danger' ?>"><?= e(money($v['kar'])) ?></b></td>
                  <td class="num hide-sm">%<?= number_format($v['satis'] > 0 ? $v['kar'] / $v['satis'] * 100 : 0, 1, ',', '.') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2><?= icon('spark') ?> Satış şampiyonları</h2><small class="muted">Bu dönem, satışa göre</small></div>
      <?php if (!$personel): ?>
        <p class="muted">Bu dönemde satış yok.</p>
      <?php else: ?>
        <?php $topN = array_slice($personel, 0, 3, true); $maxSatis = max(1, ...array_map(static fn($v) => (float) $v['satis'], $personel)); $medals = ['🥇', '🥈', '🥉']; $i = 0; ?>
        <div class="podium">
          <?php foreach ($topN as $pid => $v): $oran = $oranlar[$pid] ?? $genelOran; $prim = $v['satis'] * ($oran / 100); ?>
            <div class="podium-card rank-<?= $i + 1 ?>">
              <span class="podium-medal"><?= $medals[$i] ?></span>
              <b class="podium-name"><?= e($v['ad']) ?></b>
              <span class="podium-amt"><?= e(money($v['satis'])) ?></span>
              <small class="muted"><?= (int) $v['adet'] ?> işlem<?= $oran > 0 ? ' · prim ' . e(money($prim)) : '' ?></small>
              <?php if ($pid): $unlocked = array_filter(staff_achievements($pid), static fn($a) => $a['unlocked']); if ($unlocked): ?>
                <div class="podium-badges"><?php foreach (array_slice($unlocked, -4) as $a): ?><span title="<?= e($a['def']['title']) ?>"><?= $a['def']['icon'] ?></span><?php endforeach; ?></div>
              <?php endif; endif; ?>
            </div>
          <?php $i++; endforeach; ?>
        </div>
        <?php if (count($personel) > 3): ?>
          <div class="hbars" style="margin-top:18px">
            <?php $i = 0; foreach ($personel as $pid => $v): $i++; if ($i <= 3) { continue; } ?>
              <div class="hbar"><span>#<?= $i ?> <?= e($v['ad']) ?></span><div class="hbar-track"><i style="width:<?= round((float) $v['satis'] / $maxSatis * 100) ?>%"></i></div><b><?= money($v['satis']) ?></b></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Personel ve prim</h2><small class="muted">Oranlar Ayarlar › Kullanıcılar</small></div>
      <?php if (!$personel): ?>
        <p class="muted">Bu dönemde satış yok.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table compact">
            <thead><tr><th>Personel</th><th class="num">Satış</th><th class="num">Prim</th></tr></thead>
            <tbody>
              <?php foreach ($personel as $pid => $v):
                $oran = $oranlar[$pid] ?? $genelOran;
                $prim = $v['satis'] * ($oran / 100); ?>
                <tr>
                  <td><?= e($v['ad']) ?><small class="block muted"><?= (int) $v['adet'] ?> işlem · %<?= number_format($oran, 2, ',', '.') ?></small></td>
                  <td class="num"><?= e(money($v['satis'])) ?></td>
                  <td class="num"><b><?= $oran > 0 ? e(money($prim)) : '—' ?></b></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th>Toplam</th><th class="num"><?= e(money($toplam['satis'])) ?></th><th class="num"><?= e(money($primToplam)) ?></th></tr></tfoot>
          </table>
        </div>
      <?php endif; ?>
      <?php if ($genelOran <= 0): ?>
        <p class="hint" style="margin-top:10px">Prim oranı girilmemiş. <a class="link" href="settings.php?tab=hatirlatma">Ayarlar</a>'dan genel oranı, kullanıcı kartından kişiye özel oranı girebilirsiniz.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Hesap nasıl yapılıyor?</h2></div>
      <ul class="kv">
        <li><span><b>Cam maliyeti</b> tedarikçi faturası girildikçe cam cam işlenir; faturası girilmemiş camlar sıfır sayılır.</span></li>
        <li><span><b>Çerçeve maliyeti</b> önce stok kaydındaki alış fiyatından, yoksa markanın ortalama maliyetinden alınır.</span></li>
        <li><span><b>Brüt kâr</b> = satış − (cam + çerçeve). Kira, maaş gibi sabit giderler bu satırda yoktur.</span></li>
        <li><span><b>Gider ve prim sonrası</b> satırı dönem giderlerini, sipariş dışı gelirleri ve prim hak edişini de hesaba katar.</span></li>
        <li><span>İptal edilen siparişler hiçbir hesaba girmez.</span></li>
      </ul>
    </section>
  </aside>
</div>

<?php page_end();
