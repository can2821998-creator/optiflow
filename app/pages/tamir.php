<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.48.0 — Tamir · hizmet: tezgâhta bir dakikalık kayıt + yarım A4 hizmet fişi.
   01 müşteri · 02 gözlük ve işlemler · 03 teslim alırken görülenler · 04 ücret ve zaman.
   "Bırakıldı" → fiş basılır (üst yarı müşteriye, alt yarı gözlükle atölyeye); "Hemen yapıldı" → teslim edildi.
   ========================================================================== */

$me = require_login();
$musteri = query_int('customer_id') > 0 ? find_customer(query_int('customer_id')) : null;

if (is_post()) {
    csrf_check();
    $v = [
        'customer_id' => post_int('customer_id'),
        'ad' => post('ad'), 'soyad' => post('soyad'), 'telefon' => post('telefon'),
        'gozluk' => post('gozluk'), 'sikayet' => post('sikayet'),
        'islemler' => (array) ($_POST['islemler'] ?? []), 'durumlar' => (array) ($_POST['durumlar'] ?? []),
        'hemen' => post('hemen') === '1', 'ucretsiz' => post('ucretsiz') === '1',
        'ucret' => can_see_amounts() ? (parse_money(post('ucret')) ?? -1) : 0, 'kapora' => can_take_payments() ? (parse_money(post('kapora')) ?? 0) : 0,
        'yontem' => post('yontem'), 'soz' => post('soz'),
    ];
    try {
        $id = hizmet_kaydet($v, (int) $me['id'], (string) $me['full_name']);
        audit('order_create', 'order', $id, ['tür' => 'tamir / hizmet', 'işlem' => hizmet_islem_ozeti(hizmet_detay(['hizmet_detay' => json_encode(['islemler' => $v['islemler']])])['islemler'])]);
        if ($v['hemen']) {
            flash('Hizmet kaydedildi ve teslim edildi — ' . order_no($id) . '. Fişi isterseniz listeden basabilirsiniz.');
            redirect('tamir.php');
        }
        redirect('print.php?type=hizmet&id=' . $id . '&yazdir=1');
    } catch (DomainException $e) {
        remember_input();
        flash($e->getMessage(), 'error');
        redirect('tamir.php' . ($v['customer_id'] ? '?customer_id=' . $v['customer_id'] : ''));
    }
}

$sayilar = hizmet_sayilar();
$son = rows(
    "SELECT o.id, o.order_stage, o.created_at, o.total_amount, o.is_free, o.service_type, o.hizmet_detay, o.promised_date, c.first_name, c.last_name
       FROM orders o JOIN customers c ON c.id = o.customer_id
      WHERE o.transaction_type = 'tamir'" . sube_kosul_siparis('o') . " ORDER BY o.id DESC LIMIT 12"
);
$eskiGirdi = $_SESSION['old_input'] ?? [];   // old() ilk çağrıda siler; çoklu seçimler önce alınır
$eskiIslem = (array) ($eskiGirdi['islemler'] ?? []);
$eskiDurum = (array) ($eskiGirdi['durumlar'] ?? []);
$hemenSecili = old('hemen') === '1';

page_start('Tamir · hizmet', 'tamir');
page_header('Tamir · hizmet', 'Vida, burunluk, ayar, lehim… Bir dakikada kayıt; bırakılan gözlüğe yarım A4 fiş.', '', '', 'Tezgâh', bant_veri([
    ['Bugün alınan', $sayilar['bugun'], '', ''],
    ['Hemen yapılan', $sayilar['hemen'], '', ''],
    ['Atölyede bekleyen', $sayilar['bekleyen'], $sayilar['bekleyen'] ? 'amber' : '', 'workshop.php'],
    ['Hazır, alınmadı', $sayilar['hazir'], $sayilar['hazir'] ? 'green' : '', 'hatirlatma.php?tur=bugun'],
]));
?>
<form method="post" class="hizmet-form">
  <?= csrf_field() ?>
  <div class="hizmet-izgara">
    <section class="card">
      <div class="card-head"><h2><span class="step">01</span> Müşteri</h2></div>
      <?php if ($musteri): ?>
        <input type="hidden" name="customer_id" value="<?= (int) $musteri['id'] ?>">
        <p class="hizmet-musteri"><b><?= e($musteri['first_name'] . ' ' . $musteri['last_name']) ?></b><span class="mono"><?= e(phone_display($musteri['phone'])) ?></span>
          <a class="link" href="tamir.php">başka müşteri</a></p>
      <?php else: ?>
        <div class="grid cols-2">
          <label class="field"><span>Ad *</span><input name="ad" value="<?= e(old('ad')) ?>" required autocomplete="off"></label>
          <label class="field"><span>Soyad *</span><input name="soyad" value="<?= e(old('soyad')) ?>" required autocomplete="off"></label>
          <label class="field span-all"><span>Telefon * <small>hazır olunca aranır</small></span><input name="telefon" value="<?= e(old('telefon')) ?>" inputmode="tel" required placeholder="0532 111 22 33" autocomplete="off"></label>
        </div>
        <p class="hint">Kayıtlı müşteriyse aynı ad ve telefonla yeni kayıt açılmaz.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2><span class="step">02</span> Gözlük ve işlem</h2></div>
      <label class="field"><span>Gözlük</span><input name="gozluk" value="<?= e(old('gozluk')) ?>" placeholder="örn. Ray-Ban siyah metal, numaralı" autocomplete="off"></label>
      <fieldset class="field"><legend>Yapılacak işlem *</legend>
        <div class="chips hizmet-cip">
          <?php foreach (service_types() as $k => $ad): ?>
            <label class="chip"><input type="checkbox" name="islemler[]" value="<?= e($k) ?>" <?= in_array($k, $eskiIslem, true) ? 'checked' : '' ?>><?= e($ad) ?></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
      <label class="field"><span>Şikâyet / not</span><input name="sikayet" value="<?= e(old('sikayet')) ?>" placeholder="örn. sol sap düşüyor" autocomplete="off"></label>
    </section>

    <section class="card">
      <div class="card-head"><h2><span class="step">03</span> Teslim alırken görülen</h2></div>
      <p class="hint" style="margin-top:-6px">Fişe basılır: sonradan "bu çizik sizde oldu" tartışması olmaz.</p>
      <div class="chips hizmet-cip">
        <?php foreach (HIZMET_DURUMLARI as $k => $ad): ?>
          <label class="chip"><input type="checkbox" name="durumlar[]" value="<?= e($k) ?>" <?= in_array($k, $eskiDurum, true) ? 'checked' : '' ?>><?= e($ad) ?></label>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card">
      <div class="card-head"><h2><span class="step">04</span> Ücret ve zaman</h2></div>
      <div class="secim-cip hizmet-zaman" role="radiogroup" aria-label="Ne zaman">
        <label><input type="radio" name="hemen" value="0" <?= $hemenSecili ? '' : 'checked' ?>><span>Bırakıldı · fiş bas</span></label>
        <label><input type="radio" name="hemen" value="1" <?= $hemenSecili ? 'checked' : '' ?>><span>Hemen yapıldı</span></label>
      </div>
      <div class="grid cols-2" style="margin-top:12px">
        <?php if (can_see_amounts()): ?>
          <label class="field hizmet-ucret"><span>Ücret</span><input name="ucret" inputmode="decimal" value="<?= e(old('ucret')) ?>" placeholder="0,00"></label>
        <?php endif; ?>
        <label class="field"><span>&nbsp;</span><span class="check-field" style="min-height:44px"><input type="checkbox" name="ucretsiz" value="1" <?= old('ucretsiz') ? 'checked' : '' ?>> Ücretsiz</span></label>
        <label class="field hizmet-birak"><span>Ne zaman hazır</span><input type="date" name="soz" value="<?= e(old('soz') ?: date('Y-m-d', strtotime('+1 day'))) ?>" min="<?= date('Y-m-d') ?>"></label>
        <?php if (can_take_payments()): ?>
          <label class="field hizmet-birak hizmet-ucret"><span>Ön ödeme</span><input name="kapora" inputmode="decimal" value="<?= e(old('kapora')) ?>" placeholder="0,00"></label>
          <label class="field hizmet-ucret"><span>Ödeme yöntemi</span><select name="yontem"><?= select_options(payment_methods(), old('yontem') ?: 'nakit') ?></select></label>
        <?php endif; ?>
      </div>
      <button class="btn btn-primary btn-block hizmet-kaydet"><?= icon('print') ?> <span class="hizmet-birak-yazi">Kaydet ve fişi bas</span><span class="hizmet-hemen-yazi">Kaydet · teslim edildi</span></button>
    </section>
  </div>
</form>

<section class="card">
  <div class="card-head"><h2><?= icon('history') ?> Son tamir ve hizmetler</h2><a class="btn btn-sm" href="workshop.php">Atölye panosu</a></div>
  <?php if (!$son): ?>
    <?= empty_state('Henüz kayıt yok', 'İlk tamir ya da hizmet kaydı burada görünür.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>No</th><th>Müşteri</th><th class="hide-sm">İşlem</th><th>Durum</th><?php if (can_see_amounts()): ?><th class="num hide-sm">Ücret</th><?php endif; ?><th></th></tr></thead>
        <tbody>
          <?php foreach ($son as $o): ?>
            <tr>
              <td class="mono"><a class="link" href="order.php?id=<?= (int) $o['id'] ?>"><?= e(order_no((int) $o['id'])) ?></a></td>
              <td><b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b><small class="block muted"><?= e(date_tr($o['created_at'], true)) ?></small></td>
              <td class="hide-sm"><?= e(hizmet_islem_ozeti(hizmet_detay($o)['islemler'])) ?></td>
              <td><?= stage_badge($o['order_stage']) ?></td>
              <?php if (can_see_amounts()): ?><td class="num mono hide-sm"><?= (int) $o['is_free'] ? 'Ücretsiz' : e(money($o['total_amount'])) ?></td><?php endif; ?>
              <td class="row-actions"><a class="btn btn-sm" href="print.php?type=hizmet&id=<?= (int) $o['id'] ?>"><?= icon('print') ?> Fiş</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php page_end(); ?>
