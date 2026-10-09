<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

/* 4.21.0 — Katalogdan teklif: müşteri → çerçeve → 1–3 alternatif cam → Medula payı → iskonto → döküm.
   4.26.0 — Bir teklifte 1–3 gözlük (ör. uzak + yakın; her birinin çerçevesi, camları ve SGK payı ayrı) ve düzenleme
   (teklif-yeni.php?id=…; siparişe dönmüş teklif düzenlenemez).
   İş mantığı app/teklif.php; canlı hesap assets/teklif.js (sunucu kaydederken aynı hesabı yeniden yapar). */

$duzenleId = is_post() ? post_int('duzenle_id') : query_int('id');
$duzenlenen = null;
if ($duzenleId > 0) {
    $duzenlenen = row('SELECT * FROM quotes WHERE id = ?', [$duzenleId]);
    if (!$duzenlenen || ($duzenlenen['tip'] ?? 'serbest') !== 'katalog') {
        flash('Bu teklif bu ekrandan düzenlenemez.', 'error');
        redirect('quotes.php');
    }
    if (teklif_siparise_donmus($duzenlenen)) {
        flash('Bu teklif siparişe dönmüş; düzenlenemez. Gerekirse yeni teklif hazırlayın.', 'error');
        redirect('quote.php?id=' . $duzenleId);
    }
}

$hata = '';
$g = [];
if (is_post()) {
    $g = $_POST;
    try {
        $id = teklif_kaydet($_POST, $user, $duzenlenen ? (int) $duzenlenen['id'] : 0);
        flash($duzenlenen ? 'Teklif güncellendi.' : 'Teklif hazır. Dökümü yazdırabilir ya da WhatsApp ile gönderebilirsiniz.');
        redirect('quote.php?id=' . $id);
    } catch (DomainException $e) {
        $hata = $e->getMessage();
    }
} elseif ($duzenlenen) {
    $g = teklif_form_degerleri($duzenlenen);
}

/** Gözlük n'nin form değerleri: 1 formun kökü, 2–3 ek[n]. */
$kaynak = static fn(int $n): array => $n === 1 ? $g : (is_array($g['ek'][$n] ?? null) ? $g['ek'][$n] : []);
$ad = static fn(int $n, string $k): string => $n === 1 ? $k : "ek[$n][$k]";
$deger = static fn(array $k, string $alan, string $d = ''): string => is_string($k[$alan] ?? null) ? $k[$alan] : $d;
$degerI = static fn(array $k, string $alan, int $i): string => is_array($k[$alan] ?? null) && is_string($k[$alan][$i] ?? null) ? $k[$alan][$i] : '';

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
// Düzenlenen teklifteki çerçeve stokta kalmamış olsa da listede görünsün
$seciliCerceveler = array_filter(array_map(static fn(int $n) => (int) ($kaynak($n)['frame_item_id'] ?? 0), range(1, TEKLIF_GOZLUK_SAYISI)));
$cerceveler = rows('SELECT * FROM frame_items WHERE is_active = 1 AND (qty > 0' . ($seciliCerceveler ? ' OR id IN (' . implode(',', array_map('intval', $seciliCerceveler)) . ')' : '') . ') ORDER BY brand, model, color');
$kullanimlar = lens_designs();
$sgkTahmin = [];
foreach (array_keys($kullanimlar) as $k) {
    $sgkTahmin[$k] = teklif_sgk_tahmini($k);
}
$sinir = teklif_iskonto_siniri();
$varsayilanAd = ['Ekonomik', 'Dengeli', 'Premium'];
$oranMetni = static fn(float $o): string => rtrim(rtrim(number_format($o, 2, ',', ''), '0'), ',');
$etkin = static fn(int $n): bool => $n === 1 || ($kaynak($n)['aktif'] ?? '') === '1';
$adet = count(array_filter(range(1, TEKLIF_GOZLUK_SAYISI), $etkin));

$baslik = $duzenlenen ? 'Teklifi düzenle' : 'Yeni teklif';
page_start($baslik, 'quotes');
page_header($baslik,
    $duzenlenen
        ? 'Teklif #' . (int) $duzenlenen['id'] . ' · ' . $duzenlenen['customer_name'] . '. Değişiklikler kaydedilince döküm de güncellenir.'
        : 'Müşteriyi seçin; her gözlük için çerçeveyi ve 1–3 cam seçeneğini katalogdan seçin. Uzak + yakın gibi ikinci bir gözlük de ekleyebilirsiniz.',
    '', $duzenlenen ? 'quote.php?id=' . (int) $duzenlenen['id'] : 'quotes.php', 'Satış');
?>
<?php if ($hata !== ''): ?><div class="alert alert-error" role="alert"><?= e($hata) ?></div><?php endif; ?>
<?php if (!$urunler): ?>
  <div class="alert alert-warn">Cam kataloğunda aktif ürün yok. Önce <a class="link" href="settings.php?tab=katalog">Ayarlar › Cam kataloğu</a>'na camları marka, özellik ve fiyatlarıyla ekleyin.</div>
<?php endif; ?>

<form method="post" class="form-layout teklif-form" data-guard data-teklif
      data-sgk-tahmin="<?= e(json_encode($sgkTahmin)) ?>" data-iskonto-max="<?= e((string) $sinir) ?>">
  <?= csrf_field() ?>
  <?php if ($duzenlenen): ?><input type="hidden" name="duzenle_id" value="<?= (int) $duzenlenen['id'] ?>"><?php endif; ?>

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
          <label class="field"><span>Ad *</span><input name="first_name" value="<?= e($deger($g, 'first_name')) ?>" autocomplete="off" data-new-required></label>
          <label class="field"><span>Soyad *</span><input name="last_name" value="<?= e($deger($g, 'last_name')) ?>" autocomplete="off" data-new-required></label>
          <label class="field"><span>Telefon</span><input name="phone" type="tel" inputmode="tel" value="<?= e($deger($g, 'phone')) ?>" placeholder="05XX XXX XX XX"></label>
        </div>
      </div>
    </div>
  </section>

  <?php for ($n = 1; $n <= TEKLIF_GOZLUK_SAYISI; $n++):
      $k = $kaynak($n);
      $acik = $etkin($n);
      $cerceveTur = $deger($k, 'cerceve_tur', $cerceveler ? 'stok' : 'elle');
      $sgkVar = $k ? ($k['sgk_var'] ?? '') === '1' : true;
      $kullanim = $deger($k, 'lens_design', $n === 2 ? 'tek_odak_yakin' : 'tek_odak_uzak');
      $adDegeri = $n === 1 ? $deger($g, 'gozluk_ad') : $deger($k, 'ad');
  ?>
  <section class="card gozluk-blok" data-gozluk="<?= $n ?>" <?= $acik ? '' : 'hidden' ?>>
    <div class="card-head gozluk-bas">
      <h2><span class="gozluk-rozet" aria-hidden="true"><?= icon('glasses') ?></span>
        <label class="gozluk-ad">
          <input name="<?= e($n === 1 ? 'gozluk_ad' : $ad($n, 'ad')) ?>" value="<?= e($adDegeri) ?>" maxlength="40" aria-label="Gözlüğün adı" data-gozluk-ad
                 placeholder="<?= e(teklif_gozluk_varsayilan_ad($n, max($adet, $n === 1 ? 1 : 2))) ?>" data-ad-tek="Gözlük" data-ad-cok="<?= e(teklif_gozluk_varsayilan_ad($n, 2)) ?>"></label></h2>
      <?php if ($n > 1): ?>
        <input type="hidden" name="<?= e($ad($n, 'aktif')) ?>" value="<?= $acik ? '1' : '0' ?>" data-gozluk-aktif>
        <button type="button" class="btn btn-ghost btn-sm" data-gozluk-kaldir><?= icon('trash') ?> Kaldır</button>
      <?php else: ?>
        <small class="muted">Çerçeve, camlar ve Medula payı bu gözlüğe ait</small>
      <?php endif; ?>
    </div>

    <h3 class="gozluk-alt">Çerçeve</h3>
    <div class="chip-group" role="radiogroup" aria-label="Çerçeve">
      <?php foreach (['stok' => 'Stoktan seç', 'elle' => 'Elle yaz', 'kendi' => 'Müşterinin kendi çerçevesi'] as $tk => $tl): ?>
        <label class="chip <?= $cerceveTur === $tk ? 'active' : '' ?>"><input type="radio" name="<?= e($ad($n, 'cerceve_tur')) ?>" value="<?= e($tk) ?>" <?= $cerceveTur === $tk ? 'checked' : '' ?> class="gizli-radyo" data-cerceve-tur> <?= e($tl) ?></label>
      <?php endforeach; ?>
    </div>
    <div class="grid cols-3" style="margin-top:16px">
      <label class="field span-2" data-cerceve="stok"><span>Stoktaki çerçeve</span>
        <select name="<?= e($ad($n, 'frame_item_id')) ?>" data-cerceve-sec>
          <option value="">— Seçin —</option>
          <?php foreach ($cerceveler as $f): ?>
            <option value="<?= (int) $f['id'] ?>" data-fiyat="<?= e((string) (float) ($f['price'] ?? 0)) ?>" <?= (int) $deger($k, 'frame_item_id') === (int) $f['id'] ? 'selected' : '' ?>><?= e(frame_item_label($f)) ?><?= $f['price'] !== null ? ' — ' . e(money($f['price'])) : '' ?> · stok <?= (int) $f['qty'] ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field span-2" data-cerceve="elle"><span>Çerçeve açıklaması</span>
        <input name="<?= e($ad($n, 'frame_desc')) ?>" value="<?= e($deger($k, 'frame_desc')) ?>" placeholder="örn. Ray-Ban RB5154 52□21 siyah"></label>
      <label class="field" data-cerceve="stok,elle"><span>Çerçeve fiyatı (₺)</span>
        <input name="<?= e($ad($n, 'frame_price')) ?>" inputmode="decimal" value="<?= e($deger($k, 'frame_price')) ?>" placeholder="0,00" data-money data-cerceve-fiyat></label>
    </div>

    <h3 class="gozluk-alt">Cam seçenekleri <small class="muted">1–3 seçenek; müşteri karşılaştırıp birini seçer</small></h3>
    <div class="teklif-secenekler">
      <?php for ($i = 1; $i <= TEKLIF_SECENEK_SAYISI; $i++): $secili = (int) $degerI($k, 'urun', $i); ?>
        <div class="teklif-secenek" data-secenek="<?= $i ?>">
          <b class="teklif-secenek-no"><?= $i ?></b>
          <label class="field"><span>Başlık</span><input name="<?= e($ad($n, 'secenek_ad')) ?>[<?= $i ?>]" value="<?= e($degerI($k, 'secenek_ad', $i)) ?>" placeholder="<?= e($varsayilanAd[$i - 1]) ?>" maxlength="60" data-secenek-ad></label>
          <label class="field teklif-cam"><span>Cam (marka · ad · özellik)</span>
            <select name="<?= e($ad($n, 'urun')) ?>[<?= $i ?>]" data-cam-sec>
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
          <label class="field"><span>Cam fiyatı (çift, ₺)</span><input name="<?= e($ad($n, 'cam_fiyat')) ?>[<?= $i ?>]" inputmode="decimal" value="<?= e($degerI($k, 'cam_fiyat', $i)) ?>" placeholder="Katalog fiyatı" data-money data-cam-fiyat></label>
        </div>
      <?php endfor; ?>
    </div>

    <h3 class="gozluk-alt">Kullanım ve Medula payı</h3>
    <div class="grid cols-3">
      <label class="field"><span>Kullanım şekli <small class="muted">(SGK uzak/yakın)</small></span>
        <select name="<?= e($ad($n, 'lens_design')) ?>" data-kullanim><?= select_options($kullanimlar, $kullanim) ?></select></label>
      <label class="field"><span>&nbsp;</span>
        <span class="check-field" style="min-height:46px"><input type="checkbox" name="<?= e($ad($n, 'sgk_var')) ?>" value="1" <?= $sgkVar ? 'checked' : '' ?> data-sgk-var> SGK'lı (Medula payı düşülsün)</span></label>
      <label class="field" data-sgk-alan><span>Medula (SGK) payı (₺)</span>
        <input name="<?= e($ad($n, 'sgk_amount')) ?>" inputmode="decimal" value="<?= e($deger($k, 'sgk_amount')) ?>" placeholder="Tahmin" data-money data-sgk-tutar></label>
    </div>
  </section>
  <?php endfor; ?>

  <div class="gozluk-ekle" data-gozluk-ekle-alan>
    <button type="button" class="btn" data-gozluk-ekle><?= icon('plus') ?> Gözlük ekle</button>
    <small class="muted">Uzak + yakın gibi ikinci (ya da üçüncü) bir gözlük. Her gözlüğün çerçevesi, camları ve SGK payı ayrıdır; siparişe ayrı ayrı döner.</small>
  </div>

  <section class="card">
    <div class="card-head"><h2>İskonto ve not</h2></div>
    <div class="grid cols-3">
      <label class="field"><span>İskonto (%) <small class="muted"><?= $sinir >= 100 ? 'sınırsız' : 'en çok %' . e($oranMetni($sinir)) ?></small></span>
        <input name="discount_rate" inputmode="decimal" value="<?= e($deger($g, 'discount_rate')) ?>" placeholder="0" data-iskonto></label>
      <label class="field span-2"><span>Not</span><input name="note" value="<?= e($deger($g, 'note')) ?>" maxlength="255" placeholder="örn. bilgisayar başında çok çalışıyor"></label>
    </div>
    <p class="hint">İskonto her gözlükte, SGK payı düşüldükten sonra kalan tutara uygulanır. SGK payı Ayarlar'daki taban tutara göre tahmindir; kesin tutar Medula'da belli olur.</p>
  </section>

  <section class="card teklif-ozet" aria-live="polite">
    <div class="card-head"><h2>Hesap</h2></div>
    <div class="table-wrap">
      <table class="table teklif-ozet-tablo">
        <thead><tr><th>Seçenek</th><th class="num">Cam + çerçeve</th><th class="num">SGK payı</th><th class="num">İskonto</th><th class="num">Ödenecek</th></tr></thead>
        <tbody data-ozet><tr><td colspan="5" class="muted">Cam seçince hesap burada görünür.</td></tr></tbody>
      </table>
    </div>
  </section>

  <div class="form-actions sticky-actions">
    <a class="btn btn-ghost" href="<?= $duzenlenen ? 'quote.php?id=' . (int) $duzenlenen['id'] : 'quotes.php' ?>">Vazgeç</a>
    <button class="btn btn-primary"><?= icon('check') ?> <?= $duzenlenen ? 'Değişiklikleri kaydet' : 'Teklifi kaydet ve döküm al' ?></button>
  </div>
</form>
<?php page_end(['teklif.js']);
