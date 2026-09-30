<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();
if (!is_super()) {
    render_error_page('Yetkiniz yok', 'Tedarikçi karnesi yalnızca yöneticiler tarafından görüntülenebilir.');
}

/* ==========================================================================
   Tedarikçi karnesi — hangi tedarikçi camı kaç günde getiriyor, kim söz verilen
   teslim tarihini aşırıyor, ne kadar harcadık, ne kadar borcumuz var?

   ÖLÇÜM NOTU: Camın tedarikçiye "sipariş verildiği an" sistemde kaydedilmiyor.
   Bu yüzden "bekleme" = siparişin açıldığı gün ile camın gerçekten geldiği gün
   arasındaki süre. Sipariş açıldıktan sonra tedarikçiye geç verildiyse süre şişer;
   yine de tedarikçileri birbiriyle kıyaslamak için tutarlı bir ölçüdür.
   ========================================================================== */

/** Ondalık sıralı bir dizide yüzdelik değer (0..1). */
function karne_yuzdelik(array $sirali, float $p): float
{
    $n = count($sirali);
    if ($n === 0) {
        return 0.0;
    }
    return (float) $sirali[(int) floor($p * ($n - 1))];
}

/** İki tarih arası tam gün (negatifse 0). */
function karne_gun(string $ilk, string $son): int
{
    $a = strtotime(date('Y-m-d', (int) strtotime($ilk)));
    $b = strtotime(date('Y-m-d', (int) strtotime($son)));
    return $a && $b ? max(0, (int) floor(($b - $a) / 86400)) : 0;
}

/**
 * Cam satırlarından tedarikçi başına özet. Satır: supplier_id, delivery_id, delivered_at,
 * siparis (sipariş tarihi), promised_date, unit_cost.
 */
function karne_hesapla(array $satirlar): array
{
    $agg = [];
    foreach ($satirlar as $r) {
        $sid = (int) $r['supplier_id'];
        $agg[$sid] ??= ['cam' => 0, 'teslimat' => [], 'bekleme' => [], 'sozlu' => 0, 'gec' => 0, 'maliyet' => []];
        $agg[$sid]['cam']++;
        $agg[$sid]['teslimat'][(int) $r['delivery_id']] = true;
        $agg[$sid]['bekleme'][] = karne_gun((string) $r['siparis'], (string) $r['delivered_at']);
        if (!empty($r['promised_date']) && !str_starts_with((string) $r['promised_date'], '0000')) {
            $agg[$sid]['sozlu']++;
            if (date('Y-m-d', (int) strtotime((string) $r['delivered_at'])) > substr((string) $r['promised_date'], 0, 10)) {
                $agg[$sid]['gec']++;
            }
        }
        if ($r['unit_cost'] !== null && (float) $r['unit_cost'] > 0) {
            $agg[$sid]['maliyet'][] = (float) $r['unit_cost'];
        }
    }
    $out = [];
    foreach ($agg as $sid => $a) {
        $b = $a['bekleme'];
        sort($b);
        $zamaninda = $a['sozlu'] >= 5 ? (int) round(100 * (1 - $a['gec'] / $a['sozlu'])) : null;
        if ($zamaninda === null) {
            $etiket = ['Veri az', 'gray'];
        } elseif ($zamaninda >= 90) {
            $etiket = ['Güvenilir', 'green'];
        } elseif ($zamaninda >= 75) {
            $etiket = ['Orta', 'amber'];
        } else {
            $etiket = ['Gecikmeli', 'red'];
        }
        $out[$sid] = [
            'cam'       => $a['cam'],
            'teslimat'  => count($a['teslimat']),
            'ort'       => round(array_sum($b) / max(1, count($b)), 1),
            'medyan'    => karne_yuzdelik($b, 0.5),
            'p90'       => karne_yuzdelik($b, 0.9),
            'en_uzun'   => $b ? max($b) : 0,
            'sozlu'     => $a['sozlu'],
            'gec'       => $a['gec'],
            'zamaninda' => $zamaninda,
            'etiket'    => $etiket[0],
            'ton'       => $etiket[1],
            'birim'     => $a['maliyet'] ? round(array_sum($a['maliyet']) / count($a['maliyet']), 2) : null,
        ];
    }
    return $out;
}

/* ---------- Dönem ---------- */
$secenekler = ['90' => 'Son 3 ay', '365' => 'Son 12 ay', '0' => 'Tümü'];
$donem = query('donem');
if (!isset($secenekler[$donem])) {
    $donem = '365';
}
$gun = (int) $donem;
$bas = $gun > 0 ? date('Y-m-d', strtotime('-' . $gun . ' days')) : null;

/* ---------- Veri ---------- */
$satirlar = rows(
    "SELECT d.supplier_id, d.id AS delivery_id, d.delivered_at, o.id AS order_id, o.created_at AS siparis,
            o.promised_date, i.unit_cost, i.lens_label
       FROM prescription_lens_items i
       JOIN supplier_deliveries d ON d.id = i.delivery_id
       JOIN prescription_records r ON r.id = i.prescription_id
       JOIN orders o ON o.id = r.order_id
      WHERE d.supplier_id IS NOT NULL" . ($bas ? ' AND d.delivered_at >= ?' : ''),
    $bas ? [$bas . ' 00:00:00'] : []
);
$karne = karne_hesapla($satirlar);

$tedarikciler = rows(
    'SELECT s.id, s.name, COALESCE(si.invoiced, 0) - COALESCE(sp.paid, 0) AS balance FROM suppliers s ' . SUPPLIER_BALANCE_JOIN
);
$harcama = [];
foreach (rows(
    'SELECT supplier_id, SUM(amount) AS t FROM supplier_invoices' . ($bas ? ' WHERE invoice_date >= ?' : '') . ' GROUP BY supplier_id',
    $bas ? [$bas] : []
) as $r) {
    $harcama[(int) $r['supplier_id']] = (float) $r['t'];
}

$tablo = [];
foreach ($tedarikciler as $t) {
    $sid = (int) $t['id'];
    $k = $karne[$sid] ?? null;
    if (!$k && !isset($harcama[$sid]) && (float) $t['balance'] <= 0.009) {
        continue;   // dönemde hiçbir hareketi ve borcu olmayan tedarikçiyi listeleme
    }
    $tablo[] = ['id' => $sid, 'ad' => (string) $t['name'], 'k' => $k, 'harcama' => $harcama[$sid] ?? 0.0, 'borc' => (float) $t['balance']];
}
/* Sıralama: gecikmeli olanlar üstte (zamanında oranı düşükten yükseğe), verisi olmayanlar altta */
usort($tablo, static function (array $a, array $b): int {
    $za = $a['k'] && $a['k']['zamaninda'] !== null ? $a['k']['zamaninda'] : 101;
    $zb = $b['k'] && $b['k']['zamaninda'] !== null ? $b['k']['zamaninda'] : 101;
    return $za <=> $zb ?: strcmp($a['ad'], $b['ad']);
});

/* En uzun bekleyen 8 cam */
$adlar = array_column($tedarikciler, 'name', 'id');
$yavas = [];
foreach ($satirlar as $r) {
    $yavas[] = [
        'gun'   => karne_gun((string) $r['siparis'], (string) $r['delivered_at']),
        'ted'   => (string) ($adlar[(int) $r['supplier_id']] ?? '—'),
        'cam'   => (string) $r['lens_label'],
        'order' => (int) $r['order_id'],
        'tarih' => (string) $r['delivered_at'],
    ];
}
usort($yavas, static fn(array $a, array $b): int => $b['gun'] <=> $a['gun']);
$yavas = array_slice($yavas, 0, 8);

$toplamCam = count($satirlar);
$toplamGec = array_sum(array_column($karne, 'gec'));
$toplamSozlu = array_sum(array_column($karne, 'sozlu'));

page_start('Tedarikçi karnesi', 'suppliers');
page_header('Tedarikçi karnesi', $toplamCam . ' cam · ' . $secenekler[$donem], '', 'suppliers.php', 'Cari hesap');
?>

<div class="btn-row" style="margin-bottom:14px">
  <?php foreach ($secenekler as $k => $etiket): ?>
    <a class="btn btn-sm <?= (string) $k === $donem ? 'btn-primary' : '' ?>" href="tedarikci-karne.php?donem=<?= e((string) $k) ?>"><?= e($etiket) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($toplamCam === 0): ?>
  <section class="card">
    <p class="muted">Bu dönemde tedarikçiden "geldi" olarak işaretlenmiş cam yok. Camlar tedarikçiden geldiğinde
    tedarikçiden "geldi" olarak işaretlendikçe karne kendiliğinden dolar.</p>
  </section>
<?php else: ?>

<div class="stats three">
  <div class="stat"><small>Gelen cam</small><b><?= (int) $toplamCam ?></b><span><?= count($karne) ?> tedarikçiden</span></div>
  <div class="stat"><small>Söz verilen tarihi aşan</small><b><?= (int) $toplamGec ?></b><span>tarihli <?= (int) $toplamSozlu ?> camdan</span></div>
  <div class="stat"><small>Genel zamanında oranı</small><b><?= $toplamSozlu > 0 ? e((string) (int) round(100 * (1 - $toplamGec / $toplamSozlu))) . '%' : '—' ?></b><span>müşteri sözüne göre</span></div>
</div>

<section class="card">
  <div class="card-head"><h2>Tedarikçiler</h2><small class="muted">Gecikmeli olanlar üstte</small></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr>
        <th>Tedarikçi</th><th class="num">Cam</th><th class="num">Ort. bekleme</th><th class="num hide-sm">%90 sürede</th>
        <th class="num hide-sm">En uzun</th><th class="num">Sözü aşan</th><th>Durum</th>
        <th class="num hide-md">Harcama</th><th class="num hide-md">Açık borç</th>
      </tr></thead>
      <tbody>
      <?php foreach ($tablo as $t): $k = $t['k']; ?>
        <tr>
          <td><a class="link" href="supplier.php?id=<?= (int) $t['id'] ?>"><?= e($t['ad']) ?></a></td>
          <?php if ($k): ?>
            <td class="num"><?= (int) $k['cam'] ?></td>
            <td class="num"><b><?= e(number_format((float) $k['ort'], 1, ',', '.')) ?></b> gün</td>
            <td class="num hide-sm"><?= e(number_format((float) $k['p90'], 0, ',', '.')) ?> gün</td>
            <td class="num hide-sm"><?= (int) $k['en_uzun'] ?> gün</td>
            <td class="num"><?= $k['sozlu'] > 0 ? (int) $k['gec'] . ' / ' . (int) $k['sozlu'] : '—' ?></td>
            <td><span class="badge sm tone-<?= e($k['ton']) ?>"><?= e($k['etiket']) ?><?= $k['zamaninda'] !== null ? ' · %' . (int) $k['zamaninda'] : '' ?></span></td>
          <?php else: ?>
            <td class="num muted">—</td><td class="num muted">—</td><td class="num hide-sm muted">—</td><td class="num hide-sm muted">—</td><td class="num muted">—</td>
            <td><span class="badge sm tone-gray">Cam gelmedi</span></td>
          <?php endif; ?>
          <td class="num hide-md"><?= $t['harcama'] > 0 ? e(money($t['harcama'])) : '—' ?></td>
          <td class="num hide-md"><?= $t['borc'] > 0.009 ? e(money($t['borc'])) : '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="hint"><b>Zamanında oranı</b> yalnızca müşteriye teslim tarihi verilmiş ve en az 5 cam gelmiş tedarikçiler için hesaplanır.
    <b>%90 sürede:</b> camların %90'ı bu kadar günde ya da daha kısa sürede geldi.
    <b>Bekleme</b>, siparişin açıldığı gün ile camın geldiği gün arasındaki süredir (tedarikçiye sipariş verilme anı kaydedilmediği için yaklaşıktır).</p>
</section>

<section class="card">
  <div class="card-head"><h2>En uzun bekleyen camlar</h2><small class="muted"><?= e($secenekler[$donem]) ?></small></div>
  <ul class="kv">
    <?php foreach ($yavas as $y): ?>
      <li>
        <span><a class="link" href="order.php?id=<?= (int) $y['order'] ?>"><?= e(order_no($y['order'])) ?></a> · <?= e($y['cam']) ?><br><small class="muted"><?= e($y['ted']) ?> · <?= e(date_tr($y['tarih'])) ?></small></span>
        <b><?= (int) $y['gun'] ?> gün</b>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<?php endif; ?>

<?php page_end();
