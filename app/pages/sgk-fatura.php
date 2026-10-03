<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/fatura.php';

/* ==========================================================================
   4.16.1 — SGK ay sonu toplu faturası
   Ay içinde teslim edilen SGK'lı reçeteler biriktirilir; ay sonunda SGK'ya
   TEK fatura (KDV oranına göre satır) + reçete dökümü. Önceki aylardan
   faturalanmamış kalan reçeteler de listelenir. Fatura iptal edilirse
   reçeteler yeniden seçilebilir.
   ========================================================================== */

$me = require_login();
ozellik_gereksin('efatura');
if (!can_see_amounts()) {
    render_error_page('Yetkiniz yok', 'Tutarları görme yetkiniz olmadığı için faturalar açılamaz.');
}

$ay = fatura_sgk_ay(is_post() ? post('ay') : query('ay'));

if (is_post()) {
    try {
        if (post('eylem') === 'olustur') {
            $medula = post('medula_toplam') === '' ? null : parse_money(post('medula_toplam'));
            if (post('medula_toplam') !== '' && $medula === null) {
                throw new DomainException('Medula toplamı geçersiz. Örnek: 12.450,00');
            }
            $fid = fatura_sgk_donem_taslagi($ay, array_map('intval', (array) ($_POST['siparis'] ?? [])), $medula);
            audit('fatura_taslak', 'fatura', $fid, ['kaynak' => 'SGK dönem ' . $ay, 'reçete' => count((array) ($_POST['siparis'] ?? []))]);
            flash(fatura_sgk_ay_adi($ay) . ' SGK faturası taslağı oluşturuldu.');
            redirect('fatura.php?id=' . $fid);
        }
        if (post('eylem') === 'eski_iptal') {
            $n = fatura_sgk_eski_taslaklari_iptal();
            audit('fatura_iptal', 'fatura', null, ['kaynak' => 'eski sipariş bazlı SGK taslakları', 'adet' => $n]);
            flash($n . ' eski SGK taslağı iptal edildi; reçeteleri artık dönem faturasına eklenebilir.');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('sgk-fatura.php?ay=' . $ay);
}

$liste = fatura_sgk_donem_siparisleri($ay);
$faturalar = fatura_sgk_donem_faturalari($ay);
$eski = fatura_sgk_eski_taslaklar();
$toplam = round(array_sum(array_column($liste, 'sgk_amount')), 2);
$onceki = count(array_filter($liste, static fn(array $o): bool => (bool) $o['onceki']));
$teslimBekleyen = (int) scalar(
    "SELECT COUNT(*) FROM orders WHERE order_stage NOT IN ('teslim_edildi','iptal') AND sgk_amount > 0 AND created_at < ?",
    [date('Y-m-d', strtotime($ay . '-01 +1 month')) . ' 00:00:00']
);
$aylar = [];
for ($i = 0; $i < 12; $i++) {
    $k = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
    $aylar[$k] = fatura_sgk_ay_adi($k);
}
if (!isset($aylar[$ay])) {
    $aylar[$ay] = fatura_sgk_ay_adi($ay);
}

page_start('SGK ay sonu faturası', 'faturalar');
page_header('SGK ay sonu faturası', 'Ay içindeki SGK\'lı reçeteler tek faturada SGK\'ya', '', '', 'Kasa');
?>
<form method="get" class="btn-row" style="margin-bottom:14px">
  <label class="field" style="min-width:200px"><span>Dönem</span><select name="ay" data-auto-submit><?= select_options($aylar, $ay) ?></select></label>
  <button class="btn btn-sm">Göster</button>
</form>

<?php if ($eski): ?>
  <div class="alert alert-warn">
    Eski usulde sipariş bazında açılmış <b><?= count($eski) ?></b> SGK fatura taslağı var (toplam <?= e(money(array_sum(array_column($eski, 'genel_toplam')))) ?>).
    Yeni usulde bunlara gerek yok: dönem faturası oluşturulunca ilgili reçetelerin eski taslakları kendiliğinden iptal edilir.
    <form method="post" class="inline" data-confirm="Sipariş bazındaki <?= count($eski) ?> SGK taslağı iptal edilsin mi? (Hasta faturaları etkilenmez.)"><?= csrf_field() ?><input type="hidden" name="ay" value="<?= e($ay) ?>"><input type="hidden" name="eylem" value="eski_iptal"><button class="btn btn-sm">Hepsini iptal et</button></form>
  </div>
<?php endif; ?>

<?php if ($faturalar): ?>
<section class="card">
  <div class="card-head"><h2><?= e(fatura_sgk_ay_adi($ay)) ?> SGK faturası</h2></div>
  <div class="table-wrap"><table class="table"><tbody>
    <?php foreach ($faturalar as $f): [$dAd, $dTon] = fatura_durumlari()[$f['durum']] ?? [$f['durum'], 'gray']; ?>
      <tr>
        <td><a class="link" href="fatura.php?id=<?= (int) $f['id'] ?>"><b><?= e($f['fatura_no'] ?: 'Taslak #' . (int) $f['id']) ?></b></a></td>
        <td><?= (int) $f['adet'] ?> reçete</td>
        <td class="num"><b><?= e(money($f['genel_toplam'])) ?></b></td>
        <td><span class="badge tone-<?= e($dTon) ?>"><?= e($dAd) ?></span></td>
        <td><a class="link" href="print.php?type=sgk_dokum&amp;id=<?= (int) $f['id'] ?>" target="_blank" rel="noopener">Döküm</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <?php if ($liste): ?><p class="hint">Aşağıdaki reçeteler henüz bir SGK faturasında değil (sonradan teslim edilenler ya da seçilmeyenler).</p><?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
  <div class="card-head"><h2>Faturalanacak reçeteler</h2><span class="badge"><?= count($liste) ?></span></div>
  <?php if ($teslimBekleyen): ?><p class="muted small">Teslim edilmemiş <?= $teslimBekleyen ?> SGK'lı sipariş var; teslim edilince listeye girer.</p><?php endif; ?>
  <?php if (!$liste): ?>
    <?= empty_state('Faturalanacak reçete yok', 'Bu dönemin sonuna kadar teslim edilmiş ve henüz SGK faturasına girmemiş SGK\'lı sipariş yok.') ?>
  <?php else: ?>
  <form method="post" class="stack" style="gap:12px">
    <?= csrf_field() ?><input type="hidden" name="ay" value="<?= e($ay) ?>"><input type="hidden" name="eylem" value="olustur">
    <div class="table-wrap"><table class="table">
      <thead><tr><th></th><th>Sipariş</th><th>Hasta</th><th class="hide-sm">e-Reçete</th><th>Teslim</th><th class="num">SGK payı</th></tr></thead>
      <tbody><?php foreach ($liste as $o): ?>
        <tr>
          <td><input type="checkbox" name="siparis[]" value="<?= (int) $o['id'] ?>" checked aria-label="Faturaya ekle"></td>
          <td><a class="link" href="order.php?id=<?= (int) $o['id'] ?>"><?= e(order_no((int) $o['id'])) ?></a></td>
          <td><?= e(trim((string) $o['first_name'] . ' ' . (string) $o['last_name'])) ?></td>
          <td class="hide-sm"><?= $o['sgk_erecete'] ? e((string) $o['sgk_erecete']) : '<span class="text-danger small">yok</span>' ?></td>
          <td><?= e(date_tr((string) $o['delivered_at'])) ?><?= $o['onceki'] ? ' <span class="badge tone-amber">önceki ay</span>' : '' ?></td>
          <td class="num"><?= e(money($o['sgk_amount'])) ?><?= (float) $o['kdv'] !== fatura_varsayilan_kdv() ? '<small class="block muted">KDV %' . e((string) (float) $o['kdv']) . '</small>' : '' ?></td>
        </tr>
      <?php endforeach; ?></tbody>
      <tfoot><tr><th colspan="5">Toplam (<?= count($liste) ?> reçete<?= $onceki ? ', ' . $onceki . ' önceki aydan' : '' ?>)</th><th class="num"><?= e(money($toplam)) ?></th></tr></tfoot>
    </table></div>
    <label class="field" style="max-width:320px"><span>Medula dönem toplamı (isteğe bağlı)</span><input name="medula_toplam" inputmode="decimal" placeholder="<?= e(number_format($toplam, 2, ',', '.')) ?>" data-money></label>
    <p class="hint">Medula'nın bu dönem için hesapladığı fatura tutarı OptiFlow toplamından farklıysa buraya yazın; fatura o tutarla kesilir, fark notta görünür. Boş bırakırsanız seçilen reçetelerin SGK payları toplanır.</p>
    <div><button class="btn btn-primary"><?= icon('receipt') ?> Seçilenlerle SGK faturası taslağı oluştur</button></div>
  </form>
  <?php endif; ?>
</section>
<?php page_end();
