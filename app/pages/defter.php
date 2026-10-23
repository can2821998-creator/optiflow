<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.45.0 — Müşteri ekstresi (veresiye defterinin bir sayfası)
   Siparişler borç, SGK payı ve ödemeler alacak; yürüyen bakiye. Açık siparişlerin
   taksit planı, hızlı tahsilat, taksitlendirme; yazdır ve WhatsApp'la hesap özeti.
   ========================================================================== */

$me = require_login();
if (!can_see_amounts()) {
    render_error_page('Yetkiniz yok', 'Tutarları görme yetkiniz olmadığı için ekstre açılamaz.');
}
$cid = query_int('musteri');
$musteri = $cid > 0 ? find_customer($cid) : null;
if (!$musteri) {
    redirect('tahsilat.php');
}

$ekstre = defter_ekstre($cid);
$acik = rows(
    "SELECT o.id, o.total_amount, o.sgk_amount, o.balance_promise_date, o.order_stage,
            o.total_amount - o.sgk_amount - (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = o.id) AS bakiye
       FROM orders o WHERE o.customer_id = ? AND o.order_stage <> 'iptal' ORDER BY o.created_at",
    [$cid]
);
$acik = array_values(array_filter($acik, static fn($o) => (float) $o['bakiye'] > 0.009));
$ad = trim($musteri['first_name'] . ' ' . $musteri['last_name']);
$wa = wa_url($musteri['phone'] ?? '', defter_wa_metni($musteri, $ekstre));
$don = 'defter.php?musteri=' . $cid;
$durumAd = ['odendi' => 'Ödendi', 'kismi' => 'Kısmen', 'gecikti' => 'Gecikti', 'bekliyor' => 'Bekliyor'];
$durumTon = ['odendi' => 'green', 'kismi' => 'amber', 'gecikti' => 'red', 'bekliyor' => 'gray'];

page_start('Ekstre · ' . $ad, 'tahsilat');
page_header(
    $ad,
    ($musteri['phone'] ? e(phone_display($musteri['phone'])) . ' · ' : '') . 'Kalan borç <b>' . e(money($ekstre['bakiye'])) . '</b>',
    ($wa !== '' ? '<a class="btn btn-wa no-print" href="' . e($wa) . '" target="_blank" rel="noopener">' . icon('chat') . ' Hesap özeti gönder</a>' : '')
    . '<button type="button" class="btn no-print" data-yazdir>' . icon('print') . ' Yazdır</button>'
    . '<a class="btn no-print" href="customer.php?id=' . $cid . '">' . icon('user') . ' Müşteri kartı</a>',
    'tahsilat.php',
    'Ekstre'
);
?>

<?php foreach ($acik as $o):
  $oid = (int) $o['id'];
  $plan = $ekstre['planlar'][$oid] ?? [];
  $planKalan = array_sum(array_column($plan, 'kalan'));
  $planDisi = $plan ? round((float) $o['bakiye'] - $planKalan, 2) : 0.0; ?>
<section class="card defter-siparis">
  <div class="card-head">
    <h2><a class="link" href="order.php?id=<?= $oid ?>"><?= e(order_no($oid)) ?></a> <small class="muted"><?= e(stage_label($o['order_stage'])) ?></small></h2>
    <b class="defter-kalan"><?= e(money($o['bakiye'])) ?></b>
  </div>

  <?php if ($plan): ?>
    <table class="table defter-plan">
      <thead><tr><th>Taksit</th><th>Vade</th><th class="num hide-sm">Tutar</th><th class="num">Kalan</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($plan as $s): ?>
          <tr class="<?= $s['durum'] === 'odendi' ? 'soluk' : '' ?>">
            <td class="mono"><?= $s['sira'] ?>/<?= count($plan) ?></td>
            <td><?= e(date_tr($s['vade'])) ?></td>
            <td class="num mono hide-sm"><?= e(money($s['tutar'])) ?></td>
            <td class="num mono"><?= $s['kalan'] > 0.009 ? e(money($s['kalan'])) : '—' ?></td>
            <td><span class="badge sm tone-<?= $durumTon[$s['durum']] ?>"><?= $durumAd[$s['durum']] ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php if ($planDisi > 0.009): ?>
      <p class="hint text-danger">Plan dışında <?= e(money($planDisi)) ?> borç var (sipariş tutarı sonradan arttı) — yeniden taksitlendirin.</p>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted small" style="margin-top:-6px"><?= $o['balance_promise_date'] ? 'Ödeme sözü: ' . e(date_tr($o['balance_promise_date'])) : 'Taksit planı ya da ödeme sözü yok.' ?></p>
  <?php endif; ?>

  <div class="defter-eylem no-print">
    <?php if (can_take_payments()): ?>
    <form method="post" action="tahsilat.php" class="defter-tahsil">
      <?= csrf_field() ?><input type="hidden" name="eylem" value="tahsilat"><input type="hidden" name="order_id" value="<?= $oid ?>"><input type="hidden" name="don" value="<?= e($don) ?>">
      <?php $oneri = ($s = defter_siradaki($plan)) ? min((float) $s['kalan'], (float) $o['bakiye']) : (float) $o['bakiye']; ?>
      <label class="field"><span>Tahsilat</span><input name="amount" inputmode="decimal" value="<?= e(number_format($oneri, 2, ',', '.')) ?>"></label>
      <label class="field"><span>Yöntem</span><select name="method"><?= select_options(payment_methods(), 'nakit') ?></select></label>
      <button class="btn btn-primary"><?= icon('check') ?> Kaydet</button>
    </form>
    <?php endif; ?>
    <details class="defter-taksit">
      <summary class="btn"><?= $plan ? 'Yeniden taksitlendir' : 'Taksitlendir' ?></summary>
      <form method="post" action="tahsilat.php" class="defter-tahsil">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="taksit"><input type="hidden" name="order_id" value="<?= $oid ?>"><input type="hidden" name="don" value="<?= e($don) ?>">
        <label class="field"><span>Taksit</span><select name="adet"><?php for ($i = 2; $i <= DEFTER_EN_COK_TAKSIT; $i++): ?><option value="<?= $i ?>" <?= $i === 3 ? 'selected' : '' ?>><?= $i ?> taksit</option><?php endfor; ?></select></label>
        <label class="field"><span>İlk vade</span><input type="date" name="ilk_vade" value="<?= e(defter_ay_ekle(date('Y-m-d'), 1)) ?>" min="<?= date('Y-m-d') ?>" required></label>
        <button class="btn btn-primary">Planı kur</button>
      </form>
      <?php if ($plan): ?>
        <form method="post" action="tahsilat.php">
          <?= csrf_field() ?><input type="hidden" name="eylem" value="taksit_kaldir"><input type="hidden" name="order_id" value="<?= $oid ?>"><input type="hidden" name="don" value="<?= e($don) ?>">
          <button class="linkish danger" data-onay="Taksit planı kaldırılsın mı? Ödemeler silinmez.">Planı kaldır</button>
        </form>
      <?php endif; ?>
    </details>
  </div>
</section>
<?php endforeach; ?>

<section class="card">
  <div class="card-head"><h2><?= icon('receipt') ?> Hesap hareketleri</h2><small class="muted"><?= count($ekstre['hareketler']) ?> kayıt</small></div>
  <?php if (!$ekstre['hareketler']): ?>
    <?= empty_state('Hareket yok', 'Bu müşterinin siparişi ya da ödemesi bulunmuyor.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table defter-hareket">
        <thead><tr><th>Tarih</th><th>Açıklama</th><th class="num">Borç</th><th class="num">Ödeme</th><th class="num">Bakiye</th></tr></thead>
        <tbody>
          <?php foreach ($ekstre['hareketler'] as $h): ?>
            <tr>
              <td class="mono"><?= e(date('d.m.Y', strtotime($h['tarih']))) ?></td>
              <td><a class="link" href="order.php?id=<?= (int) $h['order_id'] ?>"><?= e($h['aciklama']) ?></a></td>
              <td class="num mono"><?= $h['borc'] > 0 ? e(money($h['borc'])) : '' ?></td>
              <td class="num mono"><?= $h['alacak'] > 0 ? e(money($h['alacak'])) : '' ?></td>
              <td class="num mono"><b><?= e(money($h['bakiye'])) ?></b></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="4">Kalan borç</td><td class="num mono"><b><?= e(money($ekstre['bakiye'])) ?></b></td></tr></tfoot>
      </table>
    </div>
  <?php endif; ?>
</section>
<p class="muted small defter-alt">Döküm tarihi <?= e(date('d.m.Y H:i')) ?> · <?= e(setting('shop_name', 'OptiFlow')) ?></p>

<?php page_end();
