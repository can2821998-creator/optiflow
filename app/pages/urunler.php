<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   Ürün kataloğu (4.17.0) — çerçeve dışındaki hazır ürünler: güneş gözlüğü,
   kontakt lens, solüsyon, aksesuar, hizmet. Hızlı satış buradan okur.
   Stok her değiştiğinde urun_hareketleri'ne yazılır.
   ========================================================================== */

$me = require_login();
ozellik_gereksin('hizli_satis');
if (!can_see_amounts()) {
    render_error_page('Yetki yok', 'Ürün kataloğu fiyat içerdiği için tutarları görme yetkisi gerekir.');
}

$kategoriler = urun_kategorileri();

if (is_post()) {
    $eylem = post('eylem');
    $id = post_int('id');
    $don = 'urunler.php' . ($id ? '?duzenle=' . $id : '');
    try {
        if ($eylem === 'kaydet') {
            $id = urun_kaydet([
                'ad' => post('ad'), 'kategori' => post('kategori'), 'barkod' => post('barkod') !== '' ? post('barkod') : ($id ? '' : urun_yeni_barkod()),
                'fiyat' => post('fiyat'), 'maliyet' => post('maliyet'), 'kdv' => post_int('kdv'), 'stok' => post_int('stok'),
                'min_stok' => post_int('min_stok'), 'stok_takip' => isset($_POST['stok_takip']), 'not_metni' => post('not_metni'),
            ], $id ?: null);
            if (isset($_POST['is_active'])) {
                update('urunler', ['is_active' => 1], 'id = ?', [$id]);
            } elseif (post_int('id')) {
                update('urunler', ['is_active' => 0], 'id = ?', [$id]);
            }
            flash(post_int('id') ? 'Ürün güncellendi.' : 'Ürün kataloğa eklendi.');
            redirect('urunler.php?duzenle=' . $id);
        }
        if ($eylem === 'hareket') {
            $u = urun_bul($id);
            if (!$u) {
                throw new DomainException('Ürün bulunamadı.');
            }
            $sebep = post('sebep');
            $adet = post_int('adet');
            if ($sebep === 'sayim') {
                $delta = max(0, $adet) - (int) $u['stok'];
            } elseif ($sebep === 'giris') {
                $delta = max(0, $adet);
            } elseif ($sebep === 'fire') {
                $delta = -max(0, $adet);
            } else {
                throw new DomainException('Geçersiz hareket.');
            }
            if ($delta === 0) {
                throw new DomainException($sebep === 'sayim' ? 'Sayılan adet kayıttaki adetle aynı.' : 'Adet girin.');
            }
            urun_hareket($id, $delta, $sebep, null, post('not'));
            audit('urun_stok', 'urun', $id, ['delta' => $delta, 'sebep' => $sebep]);
            flash('Stok güncellendi: ' . ($delta > 0 ? '+' : '') . $delta . '.');
            redirect($don);
        }
    } catch (DomainException $e) {
        remember_input();
        flash($e->getMessage(), 'error');
        redirect($don);
    }
}

$f = query('f', 'aktif');
$filtreler = ['aktif' => 'Satışta', 'kritik' => 'Kritik / biten', 'pasif' => 'Satış dışı', 'hepsi' => 'Tümü'];
if (!isset($filtreler[$f])) {
    $f = 'aktif';
}
$kat = query('k');
$q = trim(query('q'));
$kosul = match ($f) {
    'kritik' => 'AND is_active = 1 AND stok_takip = 1 AND stok <= min_stok',
    'pasif'  => 'AND is_active = 0',
    'hepsi'  => '',
    default  => 'AND is_active = 1',
};
$p = [];
if (isset($kategoriler[$kat])) {
    $kosul .= ' AND kategori = ?';
    $p[] = $kat;
}
if ($q !== '') {
    $kosul .= ' AND (LOWER(ad) LIKE ? OR barkod = ?)';
    $p[] = '%' . mb_strtolower($q) . '%';
    $p[] = $q;
}
$liste = rows("SELECT * FROM urunler WHERE 1 = 1 $kosul ORDER BY (stok_takip = 1 AND stok <= min_stok) DESC, kategori, ad LIMIT 500", $p);

$duzenle = query_int('duzenle') ? urun_bul(query_int('duzenle')) : null;
if (!$duzenle && $q !== '' && count($liste) === 1 && ($liste[0]['barkod'] ?? '') === $q) {
    $duzenle = $liste[0];
}
$hareketler = $duzenle ? rows(
    'SELECT h.*, u.full_name AS kullanici FROM urun_hareketleri h LEFT JOIN user_accounts u ON u.id = h.created_by WHERE h.urun_id = ? ORDER BY h.id DESC LIMIT 30',
    [(int) $duzenle['id']]
) : [];
$ozet = row("SELECT COUNT(*) AS kalem, COALESCE(SUM(CASE WHEN stok_takip = 1 AND stok > 0 THEN stok ELSE 0 END), 0) AS adet,
                    COALESCE(SUM(CASE WHEN stok_takip = 1 AND stok > 0 THEN stok * COALESCE(maliyet, 0) ELSE 0 END), 0) AS maliyet,
                    COALESCE(SUM(CASE WHEN stok_takip = 1 AND stok <= min_stok THEN 1 ELSE 0 END), 0) AS kritik
               FROM urunler WHERE is_active = 1") ?: ['kalem' => 0, 'adet' => 0, 'maliyet' => 0, 'kritik' => 0];
$d = $duzenle ?? [];
$para = static fn($v): string => $v === null || $v === '' ? '' : number_format((float) $v, 2, ',', '.');

page_start('Ürün kataloğu', 'urunler');
page_header(
    'Ürün kataloğu',
    'Güneş gözlüğü, lens, solüsyon ve aksesuar: hızlı satışta barkodla satılır',
    '<a class="btn btn-primary" href="hizli-satis.php">' . icon('receipt') . ' Hızlı satış</a>',
    '',
    'Depo · Ürünler'
);
?>
<section class="stats">
  <div class="stat"><small>Satıştaki ürün</small><b><?= (int) $ozet['kalem'] ?></b><span><?= (int) $ozet['adet'] ?> adet stokta</span></div>
  <div class="stat"><small>Stok maliyeti</small><b><?= e(money($ozet['maliyet'])) ?></b><span>Alış fiyatlarıyla</span></div>
  <div class="stat <?= (int) $ozet['kritik'] > 0 ? 'tone-amber' : '' ?>"><small>Kritik / biten</small><b><?= (int) $ozet['kritik'] ?></b><span>en az adedin altında</span></div>
</section>

<nav class="tabs" aria-label="Ürün filtresi">
  <?php foreach ($filtreler as $k => $ad): ?>
    <a class="tab <?= $f === $k ? 'active' : '' ?>" href="urunler.php?f=<?= e($k) ?><?= $kat !== '' ? '&k=' . e($kat) : '' ?>"><?= e($ad) ?></a>
  <?php endforeach; ?>
</nav>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="toolbar">
        <form class="search" method="get" role="search">
          <input type="hidden" name="f" value="<?= e($f) ?>">
          <?= icon('search') ?>
          <input type="search" name="q" value="<?= e($q) ?>" placeholder="Ürün adı ya da barkod" aria-label="Ürün ara">
          <select name="k" aria-label="Kategori" data-auto-submit>
            <option value="">Tüm kategoriler</option>
            <?php foreach ($kategoriler as $k => $ad): ?><option value="<?= e($k) ?>" <?= $kat === $k ? 'selected' : '' ?>><?= e($ad) ?></option><?php endforeach; ?>
          </select>
          <button class="btn btn-sm">Ara</button>
        </form>
      </div>
      <?php if (!$liste): ?>
        <?= empty_state('Ürün yok', $q !== '' ? '“' . e($q) . '” için kayıt bulunamadı.' : 'Sağdaki formdan ilk ürünü ekleyin: solüsyon, kılıf, kontakt lens, güneş gözlüğü…') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Ürün</th><th class="hide-sm">Barkod</th><th class="num">Stok</th><th class="num">Fiyat</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($liste as $u):
              $takip = (int) $u['stok_takip'] === 1;
              $kritik = $takip && (int) $u['stok'] <= (int) $u['min_stok']; ?>
              <tr>
                <td><a class="cell-link" href="urunler.php?duzenle=<?= (int) $u['id'] ?>"><span class="cell-main"><b><?= e($u['ad']) ?></b>
                  <small class="block muted"><?= e(urun_kategori_adi($u['kategori'])) ?><?= !(int) $u['is_active'] ? ' · satış dışı' : '' ?></small></span></a></td>
                <td class="hide-sm small muted"><?= e((string) $u['barkod']) ?></td>
                <td class="num"><?php if ($takip): ?><b class="<?= (int) $u['stok'] <= 0 ? 'text-danger' : ($kritik ? 'text-warn' : '') ?>"><?= (int) $u['stok'] ?></b><small class="block muted">en az <?= (int) $u['min_stok'] ?></small><?php else: ?><span class="muted">takip yok</span><?php endif; ?></td>
                <td class="num"><?= $u['fiyat'] !== null ? e(money($u['fiyat'])) : '<span class="muted">satışta girilir</span>' ?><small class="block muted"><?= $u['maliyet'] !== null ? 'alış ' . e(money($u['maliyet'])) : '' ?></small></td>
                <td class="row-actions"><a class="btn btn-sm" href="urunler.php?duzenle=<?= (int) $u['id'] ?>">Aç</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="list-foot"><span class="muted"><?= count($liste) ?> ürün · kritik olanlar üstte</span></div>
      <?php endif; ?>
    </section>

    <?php if ($duzenle && $hareketler): ?>
    <section class="card">
      <div class="card-head"><h2>Stok hareketleri</h2><small class="muted"><?= e($duzenle['ad']) ?></small></div>
      <div class="table-wrap">
        <table class="table compact">
          <thead><tr><th>Zaman</th><th>Hareket</th><th>Sebep</th><th class="hide-sm">Kim / not</th></tr></thead>
          <tbody>
          <?php foreach ($hareketler as $h): ?>
            <tr>
              <td class="small muted nowrap"><?= e(date_tr($h['created_at'], true)) ?></td>
              <td><b class="<?= (int) $h['delta'] > 0 ? 'text-ok' : 'text-danger' ?>"><?= (int) $h['delta'] > 0 ? '+' : '' ?><?= (int) $h['delta'] ?></b></td>
              <td><?= e(urun_hareket_sebepleri()[$h['sebep']] ?? $h['sebep']) ?><?php if ($h['satis_id']): ?> · <a class="link" href="hizli-satis.php?satis=<?= (int) $h['satis_id'] ?>">satış #<?= (int) $h['satis_id'] ?></a><?php endif; ?></td>
              <td class="hide-sm small muted"><?= e((string) $h['kullanici']) ?><?= $h['not_metni'] ? ' · ' . e((string) $h['not_metni']) : '' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>
  </div>

  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2><?= $duzenle ? 'Ürünü düzenle' : 'Yeni ürün' ?></h2><?php if ($duzenle): ?><a class="btn btn-sm" href="urunler.php">Yeni ekle</a><?php endif; ?></div>
      <form method="post" class="grid cols-2" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="kaydet">
        <input type="hidden" name="id" value="<?= (int) ($d['id'] ?? 0) ?>">
        <label class="field span-all"><span>Ürün adı</span><input name="ad" value="<?= e(old('ad', (string) ($d['ad'] ?? ''))) ?>" required maxlength="160" placeholder="Solüsyon 360 ml"></label>
        <label class="field"><span>Kategori</span>
          <select name="kategori"><?php foreach ($kategoriler as $k => $ad): ?><option value="<?= e($k) ?>" <?= ($d['kategori'] ?? 'aksesuar') === $k ? 'selected' : '' ?>><?= e($ad) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Barkod</span><input name="barkod" value="<?= e((string) ($d['barkod'] ?? '')) ?>" placeholder="okutun ya da boş bırakın" data-barcode-input></label>
        <label class="field"><span>Satış fiyatı</span><input name="fiyat" inputmode="decimal" value="<?= e($para($d['fiyat'] ?? null)) ?>" placeholder="boşsa satışta girilir"></label>
        <label class="field"><span>Alış fiyatı</span><input name="maliyet" inputmode="decimal" value="<?= e($para($d['maliyet'] ?? null)) ?>"></label>
        <label class="field"><span>KDV</span>
          <select name="kdv"><?php foreach ([20, 10, 1, 0] as $o): ?><option value="<?= $o ?>" <?= (int) ($d['kdv'] ?? 20) === $o ? 'selected' : '' ?>>%<?= $o ?></option><?php endforeach; ?></select></label>
        <?php if (!$duzenle): ?>
          <label class="field"><span>İlk stok</span><input name="stok" inputmode="numeric" value="0"></label>
        <?php else: ?>
          <label class="field"><span>Stokta</span><input value="<?= (int) $d['stok'] ?>" disabled></label>
        <?php endif; ?>
        <label class="field"><span>Kritik adet</span><input name="min_stok" inputmode="numeric" value="<?= (int) ($d['min_stok'] ?? 1) ?>"></label>
        <label class="field check-field"><input type="checkbox" name="stok_takip" <?= !$duzenle || (int) $d['stok_takip'] ? 'checked' : '' ?>> Stok takip edilsin</label>
        <?php if ($duzenle): ?><label class="field check-field"><input type="checkbox" name="is_active" <?= (int) $d['is_active'] ? 'checked' : '' ?>> Satışta</label><?php endif; ?>
        <label class="field span-all"><span>Not</span><input name="not_metni" value="<?= e((string) ($d['not_metni'] ?? '')) ?>" maxlength="255"></label>
        <div class="form-actions span-all"><button class="btn btn-primary"><?= icon('check') ?> <?= $duzenle ? 'Kaydet' : 'Kataloğa ekle' ?></button></div>
      </form>
      <?php if ($duzenle): ?>
        <p class="hint" style="margin-top:12px">Barkodu hızlı satış ekranına okutunca ürün sepete eklenir. Kendi barkodu olmayan ürüne verilen <b>PU…</b> numarası etikete elle yazılabilir.</p>
      <?php endif; ?>
    </section>

    <?php if ($duzenle && (int) $d['stok_takip']): ?>
    <section class="card">
      <div class="card-head"><h2>Stok girişi / sayım</h2><small class="muted">şu an <?= (int) $d['stok'] ?></small></div>
      <form method="post" class="grid cols-2">
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="hareket">
        <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
        <label class="field"><span>İşlem</span>
          <select name="sebep"><option value="giris">Stok girişi (+)</option><option value="sayim">Sayım: adet şu</option><option value="fire">Fire / kırık (−)</option></select></label>
        <label class="field"><span>Adet</span><input name="adet" inputmode="numeric" required></label>
        <label class="field span-all"><span>Not</span><input name="not" maxlength="255" placeholder="ör. irsaliye no"></label>
        <div class="form-actions span-all"><button class="btn"><?= icon('check') ?> Stoğu güncelle</button></div>
      </form>
    </section>
    <?php endif; ?>
  </aside>
</div>
<?php page_end();
