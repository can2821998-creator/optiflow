<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_login();

/* ==========================================================================
   Gözlük bağışı — bağışlanan çerçeveler, verilen gözlükler ve karşılanan cam maliyeti.
   Alıcının kimliği (ad, telefon, fotoğraf) bu sistemde KAYDEDİLMEZ; bu ekranda bunun için
   bilerek bir alan yoktur. Ayrıntı: app/donation.php
   ========================================================================== */

$yuklu = false;
try {
    require_once dirname(__DIR__) . '/donation.php';
    $yuklu = function_exists('donation_add');
} catch (Throwable $e) {
    $yuklu = false;
}
if (!$yuklu) {
    render_error_page('Gözlük bağışı yüklenemedi', 'app/donation.php dosyası eksik ya da okunamıyor.');
}

if (is_post()) {
    csrf_check();
    $eylem = post('eylem');
    try {
        if ($eylem === 'ekle') {
            donation_add(post('tanim'), post('bagisci'), post('not'), (int) $me['id']);
            flash('Çerçeve bağışı kaydedildi. Teşekkürler!');
        } elseif ($eylem === 'ver') {
            $siparisNo = (int) (preg_replace('/\D+/', '', post('siparis_no')) ?? '');
            flash(donation_give(post_int('id'), post('maliyet'), $siparisNo, post('not')));
        } elseif ($eylem === 'sil' && is_super()) {
            donation_delete(post_int('id'));
            flash('Kayıt silindi.', 'info');
        } elseif ($eylem === 'onceki' && is_super()) {
            setting_set('bagis_onceki_cerceve', (string) max(0, post_int('onceki_cerceve')));
            setting_set('bagis_onceki_gozluk', (string) max(0, post_int('onceki_gozluk')));
            $yil = post_int('baslangic_yili');
            setting_set('bagis_baslangic_yili', ($yil >= 1980 && $yil <= (int) date('Y')) ? (string) $yil : '');
            flash('Önceki yılların toplamları kaydedildi.');
        }
    } catch (Throwable $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('bagis.php');
}

$t = donation_totals();
$stok = donation_stock();
$son = donation_recent();
$tutar = can_see_amounts();

page_start('Gözlük bağışı', 'bagis');
page_header(
    'Gözlük bağışı',
    'Kullanılmayan çerçeveleri ihtiyaç sahipleriyle buluşturuyoruz; camlarını biz karşılıyoruz',
    '',
    '',
    'Dayanışma'
);
?>

<div class="alert alert-info">
  <span><b>Alıcının onuru:</b> Gözlüğü alan kişinin adı, telefonu veya fotoğrafı bu sistemde <b>kaydedilmez</b>;
  bu ekranda bunun için bilerek bir alan yoktur. Sitede ve müşteri sayfalarında yalnızca iki toplam sayı görünür.</span>
</div>

<div class="stats three">
  <div class="stat"><small>Bağışlanan çerçeve</small><b><?= (int) $t['cerceve'] ?></b><span><?= $t['yil'] ? (int) $t['yil'] . ' yılından beri' : 'toplam' ?></span></div>
  <div class="stat tone-green"><small>İhtiyaç sahibiyle buluşan gözlük</small><b><?= (int) $t['gozluk'] ?></b><span>camları mağazamız karşıladı</span></div>
  <div class="stat"><small><?= $tutar ? 'Sistemde kayıtlı cam maliyeti' : 'Stokta bekleyen bağış çerçevesi' ?></small><b><?= $tutar ? e(money($t['maliyet'])) : (int) $t['stokta'] ?></b><span><?= $tutar ? 'bu ekrandan girilen' : 'verilmeyi bekliyor' ?></span></div>
</div>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2><?= icon('plus') ?> Çerçeve bağışı kaydet</h2></div>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="ekle">
        <label class="field"><span>Çerçeve <small class="muted">(kısa tanım)</small></span>
          <input name="tanim" maxlength="160" required placeholder="Örn. Siyah dikdörtgen çerçeve, erkek, 52 göz"></label>
        <label class="field"><span>Bağışlayan <small class="muted">(isteğe bağlı, teşekkür için)</small></span>
          <input name="bagisci" maxlength="80" placeholder="Örn. Ayşe K."></label>
        <label class="field"><span>Not <small class="muted">(isteğe bağlı)</small></span>
          <input name="not" maxlength="240" placeholder="Örn. Sapı ayar istiyor"></label>
        <div class="form-actions"><button class="btn btn-primary">Kaydet</button></div>
      </form>
    </section>

    <section class="card">
      <div class="card-head"><h2>Stoktaki bağış çerçeveleri</h2><small class="muted"><?= count($stok) ?> adet</small></div>
      <?php if (!$stok): ?>
        <p class="muted">Şu an stokta bekleyen bağış çerçevesi yok. Yeni gelenleri yukarıdan kaydedin.</p>
      <?php else: ?>
        <ul class="kv">
          <?php foreach ($stok as $f): ?>
            <li>
              <span><b><?= e($f['description']) ?></b><br>
                <small class="muted"><?= e(date_tr($f['received_on'])) ?> tarihinde geldi<?= $f['donor'] !== '' ? ' · ' . e($f['donor']) : '' ?><?= $f['note'] !== '' ? ' · ' . e($f['note']) : '' ?></small></span>
              <b>
                <details class="menu">
                  <summary class="btn btn-sm btn-primary">Gözlük verildi</summary>
                  <div class="menu-list" style="padding:12px;min-width:250px">
                    <form method="post" class="stack">
                      <?= csrf_field() ?><input type="hidden" name="eylem" value="ver"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                      <label class="field"><span>Sipariş no <small class="muted">(isteğe bağlı)</small></span>
                        <input name="siparis_no" inputmode="numeric" placeholder="00073"></label>
                      <label class="field"><span>Cam maliyeti <small class="muted">(boşsa siparişten hesaplanır)</small></span>
                        <input name="maliyet" inputmode="decimal" placeholder="0,00"></label>
                      <label class="field"><span>İç not <small class="muted">(isim yazmayın)</small></span>
                        <input name="not" maxlength="240"></label>
                      <button class="btn btn-primary btn-sm">Verildi olarak kaydet</button>
                    </form>
                  </div>
                </details>
                <?php if (is_super()): ?>
                  <form method="post" class="inline" data-confirm="Bu bağış kaydı silinsin mi?">
                    <?= csrf_field() ?><input type="hidden" name="eylem" value="sil"><input type="hidden" name="id" value="<?= (int) $f['id'] ?>">
                    <button class="btn btn-sm" title="Kaydı sil" aria-label="Kaydı sil">×</button>
                  </form>
                <?php endif; ?>
              </b>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </div>

  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2>Son verilen gözlükler</h2></div>
      <?php if (!$son): ?>
        <p class="muted small">Henüz sistemde kayıtlı verilmiş gözlük yok.</p>
      <?php else: ?>
        <ul class="kv">
          <?php foreach ($son as $f): ?>
            <li>
              <span><?= e($f['description']) ?><br>
                <small class="muted"><?= e(date_tr((string) $f['given_on'])) ?><?= $f['order_id'] ? ' · <a class="link" href="order.php?id=' . (int) $f['order_id'] . '">' . e(order_no((int) $f['order_id'])) . '</a>' : '' ?></small></span>
              <b><?= $tutar && $f['lens_cost'] !== null ? e(money($f['lens_cost'])) : '' ?></b>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Duyuru nerede görünüyor?</h2></div>
      <ul class="kv">
        <li><span>Web sitesinde "Gözlük Dayanışması" bölümü</span><b>Sayaçlı</b></li>
        <li><span>Müşteri durum sayfası (gözlük hazır / teslim)</span><b>Kart</b></li>
        <li><span>Gözlük bakım kartı</span><b>Kart</b></li>
      </ul>
      <p class="hint">Gösterilen sayılar: <b><?= (int) $t['cerceve'] ?></b> çerçeve · <b><?= (int) $t['gozluk'] ?></b> gözlük.</p>
    </section>

    <?php if (is_super()): ?>
    <section class="card">
      <div class="card-head"><h2>Sistemden önceki yıllar</h2></div>
      <p class="muted small">Bu çalışmayı sistemden önce de yürüttüğünüz için, o yılların toplamlarını buraya elle girin; sayaca eklenir.</p>
      <?php $o = donation_offsets(); ?>
      <form method="post" class="stack">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="onceki">
        <label class="field"><span>Önceki bağışlanan çerçeve</span><input name="onceki_cerceve" inputmode="numeric" value="<?= (int) $o['cerceve'] ?>"></label>
        <label class="field"><span>Önceki verilen gözlük</span><input name="onceki_gozluk" inputmode="numeric" value="<?= (int) $o['gozluk'] ?>"></label>
        <label class="field"><span>Çalışmaya başladığımız yıl</span><input name="baslangic_yili" inputmode="numeric" maxlength="4" placeholder="Örn. 2016" value="<?= $o['yil'] ? (int) $o['yil'] : '' ?>"></label>
        <div class="form-actions"><button class="btn btn-sm">Kaydet</button></div>
      </form>
    </section>
    <?php endif; ?>
  </aside>
</div>

<?php page_end();
