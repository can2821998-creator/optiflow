<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

/* 4.21.0 — Katalogdan teklif: müşteri → çerçeve → 1–3 alternatif cam → Medula payı → iskonto → döküm.
   İş mantığı app/teklif.php; canlı hesap assets/teklif.js (sunucu kaydederken aynı hesabı yeniden yapar). */

$hata = '';
$g = [];
if (is_post()) {
    $g = $_POST;
    try {
        $id = teklif_kaydet($_POST, $user);
        flash('Teklif hazır. Dökümü yazdırabilir ya da WhatsApp ile gönderebilirsiniz.');
        redirect('quote.php?id=' . $id);
    } catch (DomainException $e) {
        $hata = $e->getMessage();
    }
}
$v = static fn(string $k, string $d = ''): string => is_string($g[$k] ?? null) ? $g[$k] : $d;
$vi = static fn(string $k, int $i, string $d = ''): string => is_array($g[$k] ?? null) && is_string($g[$k][$i] ?? null) ? $g[$k][$i] : $d;

$musteri = null;
$mid = (int) ($g['customer_id'] ?? query_int('customer_id'));
if ($mid > 0) {
    $musteri = find_customer($mid);
}

$urunler = teklif_cam_urunleri();
$urunGruplu = [];
foreach ($urunler as $u) {
    $urunGruplu[teklif_tasarim_adi((string) $u['design'])][] = $u;
}
$cerceveler = rows('SELECT * FROM frame_items WHERE is_active = 1 AND qty > 0 ORDER BY brand, model, color');
$kullanimlar = lens_designs();
$sgkTahmin = [];
foreach (array_keys($kullanimlar) as $k) {
    $sgkTahmin[$k] = teklif_sgk_tahmini($k);
}
$sinir = teklif_iskonto_siniri();
$cerceveTur = $v('cerceve_tur', $cerceveler ? 'stok' : 'elle');
$sgkVar = !$g || ($g['sgk_var'] ?? '') === '1';
$varsayilanAd = ['Ekonomik', 'Dengeli', 'Premium'];
$oranMetni = static fn(float $o): string => rtrim(rtrim(number_format($o, 2, ',', ''), '0'), ',');

page_start('Yeni teklif', 'quotes');
page_header('Yeni teklif', 'Müşteriyi seçin, çerçeveyi ve 1–3 cam seçeneğini katalogdan seçin; Medula payı ve iskonto düşülmüş tutar canlı hesaplanır.', '', 'quotes.php', 'Satış');
?>
<?php if ($hata !== ''): ?><div class="alert alert-error" role="alert"><?= e($hata) ?></div><?php endif; ?>
<?php if (!$urunler): ?>
  <div class="alert alert-warn">Cam kataloğunda aktif ürün yok. Önce <a class="link" href="settings.php?tab=katalog">Ayarlar › Cam kataloğu</a>'na camları marka, özellik ve fiyatlarıyla ekleyin.</div>
<?php endif; ?>

<form method="post" class="form-layout teklif-form" data-guard data-teklif
      data-sgk-tahmin="<?= e(json_encode($sgkTahmin)) ?>" data-iskonto-max="<?= e((string) $sinir) ?>">
  <?= csrf_field() ?>

  <section class="card">
    <div class="card-head"><h2><span class="step">1</span> Müşteri</h2></div>
    <div class="customer-picker" data-customer-picker <?= $musteri ? 'data-selected' : '' ?>>
      <input type="hidden" name="customer_id" value="<?= $musteri ? (int) $musteri['id'] : '' ?>" data-customer-id>
      <div class="picked" <?= $musteri ? '' : 'hidden' ?> data-picked>
        <span class="avatar"><?= $musteri ? e(initials($musteri['first_name'], $musteri['last_name'])) : '' ?></span>
        <span class="cell-main"><b data-picked-name><?= $musteri ? e($musteri['first_name'] . ' ' . $musteri['last_name']) : '' ?></b>
          <small data-picked-meta><?= $musteri ? e(phone_display($musteri['phone']) ?: 'Telefon yok') : '' ?></small></span>
        <button type="button" class="btn btn-ghost btn-sm" data-unpick>Değiştir</button>
      </div>
      <div data-picker-body <?= $musteri ? 'hidden' : '' ?>>
        <label class="field search-field"><span>Kayıtlı müşteri ara</span>
          <input type="search" placeholder="Ad soyad veya telefon" autocomplete="off" data-customer-search></label>
        <ul class="suggest" data-suggest hidden></ul>
        <div class="divider"><span>veya yeni müşteri kaydet</span></div>
        <div class="grid cols-3">
          <label class="field"><span>Ad *</span><input name="first_name" value="<?= e($v('first_name')) ?>" autocomplete="off" data-new-required></label>
          <label class="field"><span>Soyad *</span><input name="last_name" value="<?= e($v('last_name')) ?>" autocomplete="off" data-new-required></label>
          <label class="field"><span>Telefon</span><input name="phone" type="tel" inputmode="tel" value="<?= e($v('phone')) ?>" placeholder="05XX XXX XX XX"></label>
        </div>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2><span class="step">2</span> Çerçeve</h2></div>
    <div class="chip-group" role="radiogroup" aria-label="Çerçeve">
      <?php foreach (['stok' => 'Stoktan seç', 'elle' => 'Elle yaz', 'kendi' => 'Müşterinin kendi çerçevesi'] as $tk => $tl): ?>
        <label class="chip <?= $cerceveTur === $tk ? 'active' : '' ?>"><input type="radio" name="cerceve_tur" value="<?= e($tk) ?>" <?= $cerceveTur === $tk ? 'checked' : '' ?> class="gizli-radyo"> <?= e($tl) ?></label>
      <?php endforeach; ?>
    </div>
    <div class="grid cols-3" style="margin-top:16px">
      <label class="field span-2" data-cerceve="stok"><span>Stoktaki çerçeve</span>
        <select name="frame_item_id" data-cerceve-sec>
          <option value="">— Seçin —</option>
          <?php foreach ($cerceveler as $f): ?>
            <option value="<?= (int) $f['id'] ?>" data-fiyat="<?= e((string) (float) ($f['price'] ?? 0)) ?>" <?= (int) $v('frame_item_id') === (int) $f['id'] ? 'selected' : '' ?>><?= e(frame_item_label($f)) ?><?= $f['price'] !== null ? ' — ' . e(money($f['price'])) : '' ?> · stok <?= (int) $f['qty'] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field span-2" data-cerceve="elle"><span>Çerçeve açıklaması</span>
        <input name="frame_desc" value="<?= e($v('frame_desc')) ?>" placeholder="örn. Ray-Ban RB5154 52□21 siyah"></label>
      <label class="field" data-cerceve="stok,elle"><span>Çerçeve fiyatı (₺)</span>
        <input name="frame_price" inputmode="decimal" value="<?= e($v('frame_price')) ?>" placeholder="0,00" data-money data-cerceve-fiyat></label>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2><span class="step">3</span> Cam seçenekleri</h2><small class="muted">1–3 seçenek; müşteri karşılaştırıp birini seçer</small></div>
    <div class="teklif-secenekler">
      <?php for ($i = 1; $i <= TEKLIF_SECENEK_SAYISI; $i++): $secili = (int) $vi('urun', $i); ?>
        <div class="teklif-secenek" data-secenek="<?= $i ?>">
          <b class="teklif-secenek-no"><?= $i ?></b>
          <label class="field"><span>Başlık</span><input name="secenek_ad[<?= $i ?>]" value="<?= e($vi('secenek_ad', $i)) ?>" placeholder="<?= e($varsayilanAd[$i - 1]) ?>" maxlength="60"></label>
          <label class="field teklif-cam"><span>Cam (marka · ad · özellik)</span>
            <select name="urun[<?= $i ?>]" data-cam-sec>
              <option value=""><?= $i === 1 ? '— Cam seçin —' : '— Bu seçeneği kullanma —' ?></option>
              <?php foreach ($urunGruplu as $grup => $liste): ?>
                <optgroup label="<?= e($grup) ?>">
                  <?php foreach ($liste as $u): $et = teklif_cam_etiketi($u); ?>
                    <option value="<?= (int) $u['id'] ?>" data-fiyat="<?= $u['price'] !== null ? e((string) (float) $u['price']) : '' ?>" data-tasarim="<?= e((string) $u['design']) ?>" data-segment="<?= e(product_tiers()[$u['tier']] ?? '') ?>" <?= $secili === (int) $u['id'] ? 'selected' : '' ?>><?= e($et['ad']) ?> — <?= e($et['ozellik']) ?><?= $et['not'] !== '' ? ' · ' . e($et['not']) : '' ?><?= $u['price'] !== null ? ' — ' . e(money($u['price'])) : ' — fiyat sorulur' ?></option>
                  <?php endforeach; ?>
                </optgroup>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="field"><span>Cam fiyatı (çift, ₺)</span><input name="cam_fiyat[<?= $i ?>]" inputmode="decimal" value="<?= e($vi('cam_fiyat', $i)) ?>" placeholder="Katalog fiyatı" data-money data-cam-fiyat></label>
        </div>
      <?php endfor; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2><span class="step">4</span> Medula payı ve iskonto</h2></div>
    <div class="grid cols-3">
      <label class="field"><span>Kullanım şekli <small class="muted">(SGK uzak/yakın)</small></span>
        <select name="lens_design" data-kullanim><?= select_options($kullanimlar, $v('lens_design', 'tek_odak_uzak')) ?></select></label>
      <label class="field"><span>&nbsp;</span>
        <span class="check-field" style="min-height:46px"><input type="checkbox" name="sgk_var" value="1" <?= $sgkVar ? 'checked' : '' ?> data-sgk-var> SGK'lı (Medula payı düşülsün)</span></label>
      <label class="field" data-sgk-alan><span>Medula (SGK) payı (₺)</span>
        <input name="sgk_amount" inputmode="decimal" value="<?= e($v('sgk_amount')) ?>" placeholder="Tahmin" data-money data-sgk-tutar></label>
      <label class="field"><span>İskonto (%) <small class="muted"><?= $sinir >= 100 ? 'sınırsız' : 'en çok %' . e($oranMetni($sinir)) ?></small></span>
        <input name="discount_rate" inputmode="decimal" value="<?= e($v('discount_rate')) ?>" placeholder="0" data-iskonto></label>
      <label class="field span-2"><span>Not</span><input name="note" value="<?= e($v('note')) ?>" maxlength="255" placeholder="örn. bilgisayar başında çok çalışıyor"></label>
    </div>
    <p class="hint">SGK payı, Ayarlar'daki taban tutara göre tahmindir; kesin tutar Medula'da belli olur. İskonto, SGK payı düşüldükten sonra kalan tutara uygulanır.</p>
  </section>

  <section class="card teklif-ozet" aria-live="polite">
    <div class="card-head"><h2><span class="step">5</span> Hesap</h2></div>
    <div class="table-wrap">
      <table class="table teklif-ozet-tablo">
        <thead><tr><th>Seçenek</th><th class="num">Cam + çerçeve</th><th class="num">SGK payı</th><th class="num">İskonto</th><th class="num">Ödenecek</th></tr></thead>
        <tbody data-ozet><tr><td colspan="5" class="muted">Cam seçince hesap burada görünür.</td></tr></tbody>
      </table>
    </div>
  </section>

  <div class="form-actions sticky-actions">
    <a class="btn btn-ghost" href="quotes.php">Vazgeç</a>
    <button class="btn btn-primary"><?= icon('check') ?> Teklifi kaydet ve döküm al</button>
  </div>
</form>
<?php page_end(['teklif.js']);
