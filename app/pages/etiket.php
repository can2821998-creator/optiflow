<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_login();

/* ==========================================================================
   Etiket sihirbazı — A4 kâğıt ve sıradan yazıcıyla çerçeve barkodlama.
   ========================================================================== */

$duzenler = label_layouts();
$d = label_layout_from($_GET);
$kesim   = query('kesim', '1') === '1';       // kesim çizgileri
$fiyatli = query('fiyat', '1') === '1';       // fiyat basılsın mı
$atla    = max(0, min(200, query_int('atla')));
$kacar   = max(1, min(99, query_int('kacar') ?: 1));
/* Varsayılan: her fiziksel çerçeve için bir etiket (stoktaki adet kadar). */
$stokKadar = query('stokkadar', '1') === '1';
const ETIKET_SINIRI = 2000;
$kapsam  = query('kapsam', 'secili');
$secili  = array_values(array_filter(array_map('intval', explode(',', query('ids'))), static fn($i) => $i > 0));

/* Süper yetkili düzeni varsayılan yapabilir */
if (is_post()) {
    csrf_check();
    if (post('eylem') === 'varsayilan' && is_super()) {
        setting_set('label_layout', $d['anahtar']);
        foreach (['w', 'h', 'cols', 'rows', 'ml', 'mt', 'gx', 'gy'] as $k) {
            setting_set('label_' . $k, (string) $d[$k]);
        }
        flash('Bu düzen varsayılan olarak kaydedildi.');
    }
    redirect('etiket.php?' . http_build_query(array_filter($_GET, static fn($v) => $v !== '')));
}

/* ---------- Hangi çerçeveler ---------- */
$kosul = match ($kapsam) {
    'hepsi'  => 'WHERE is_active = 1 AND qty > 0',
    'kritik' => 'WHERE is_active = 1 AND qty <= min_qty',
    default  => $secili ? 'WHERE id IN (' . implode(',', array_fill(0, count($secili), '?')) . ')' : 'WHERE 1 = 0',
};
$params = $kapsam === 'secili' ? $secili : [];
$kalemler = rows("SELECT * FROM frame_items $kosul ORDER BY brand, model, color LIMIT 400", $params);

/** Bir kalem için kaç etiket basılacak. */
$adetHesapla = static fn(array $k): int => $stokKadar ? max(1, (int) $k['qty']) : $kacar;

/* Toplam etiket ve sınır denetimi */
$toplamEtiket = 0;
foreach ($kalemler as $k) {
    $toplamEtiket += $adetHesapla($k);
}
$kirpildi = $toplamEtiket + $atla > ETIKET_SINIRI;

/* Barkodu olmayanlara üret (etiketsiz kalmasın) */
foreach ($kalemler as $i => $k) {
    if (trim((string) $k['barcode']) === '') {
        $yeni = frame_new_barcode();
        q('UPDATE frame_items SET barcode = ? WHERE id = ?', [$yeni, (int) $k['id']]);
        $kalemler[$i]['barcode'] = $yeni;
    }
}

/* ---------- Baskı görünümü ---------- */
if (query('bas') === '1' || query('cetvel') === '1') {
    $shop = setting('shop_name', 'OptiFlow');
    $cetvel = query('cetvel') === '1';

    // Etiket akışı: her kalem $kacar kez, başta $atla boş hücre
    $hucreler = [];
    for ($i = 0; $i < $atla; $i++) {
        $hucreler[] = null;
    }
    foreach ($kalemler as $k) {
        $n = $adetHesapla($k);
        for ($i = 0; $i < $n; $i++) {
            if (count($hucreler) >= ETIKET_SINIRI) {
                break 2;                       // güvenlik sınırı: tek seferde en çok 2000 etiket
            }
            $hucreler[] = $k;
        }
    }
    $sayfalar = $hucreler ? array_chunk($hucreler, max(1, (int) $d['sayfada'])) : [];
    ?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><title>Çerçeve etiketleri</title>
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<style>
  :root{
    --lw: <?= $d['w'] ?>mm; --lh: <?= $d['h'] ?>mm;
    --lml: <?= $d['ml'] ?>mm; --lmt: <?= $d['mt'] ?>mm;
    --lgx: <?= $d['gx'] ?>mm; --lgy: <?= $d['gy'] ?>mm;
    --lqr: <?= $d['qr'] ?>mm;
    --lcols: <?= (int) $d['cols'] ?>;
  }
</style>
<link rel="stylesheet" href="<?= e(asset('print.css')) ?>" media="all">
</head>
<body class="label-page">
<div class="label-toolbar no-print">
  <a class="btn" href="etiket.php?<?= e(http_build_query(array_diff_key($_GET, ['bas' => 1, 'cetvel' => 1]))) ?>">← Ayarlar</a>
  <button class="btn btn-primary" onclick="window.print()">Yazdır</button>
  <span class="muted small">
    <?= $cetvel ? 'Ölçü denetim sayfası' : count($kalemler) . ' çerçeve · ' . count($hucreler ?? []) . ' etiket · ' . count($sayfalar) . ' sayfa'
        . ($kirpildi ? ' · sınır nedeniyle ' . ETIKET_SINIRI . ' etikette kesildi' : '') ?>
    · Yazdırma penceresinde <b>ölçek %100</b>, kenar boşluğu <b>yok</b> olmalı.
  </span>
</div>

<?php if ($cetvel): ?>
  <div class="lbl-page lbl-test">
    <h1>Ölçü denetimi</h1>
    <p>Bu sayfayı basın ve cetvelle ölçün. Çizgi <b>tam 100 mm</b>, kare <b>tam 50×50 mm</b> değilse
       yazdırma penceresinde ölçeği <b>%100 (gerçek boyut)</b> yapın, "sayfaya sığdır" seçeneğini kapatın.</p>
    <div class="ruler"><?php for ($i = 0; $i <= 10; $i++): ?><span></span><?php endfor; ?></div>
    <div class="ruler-label">100 mm</div>
    <div class="test-square">50 × 50 mm</div>
    <p class="small muted">Seçili düzen: <?= e($d['ad']) ?> · etiket <?= e((string) $d['w']) ?>×<?= e((string) $d['h']) ?> mm ·
      sayfada <?= (int) $d['sayfada'] ?> adet · karekod <?= e(number_format($d['qr'], 1, ',', '')) ?> mm</p>
  </div>
<?php else: ?>
  <?php foreach ($sayfalar as $sayfa): ?>
    <div class="lbl-page">
      <div class="lbl-grid <?= $kesim ? ($d['gx'] + $d['gy'] < 0.01 ? 'izgara' : 'kesimli') : '' ?> tip-<?= e($d['tip']) ?>">
        <?php if ($kesim && $d['gx'] + $d['gy'] < 0.01): ?>
          <span class="crop c1"></span><span class="crop c2"></span><span class="crop c3"></span><span class="crop c4"></span>
        <?php endif; ?>
        <?php foreach ($sayfa as $k): ?>
          <?php if ($k === null): ?>
            <div class="lbl bos"></div>
          <?php else: $L = label_lines($k); ?>
            <div class="lbl<?= $d['dar'] ? ' dar' : '' ?>">
              <?php if ($d['tip'] === 'katlamali'): ?>
                <div class="lbl-yuz on">
                  <?php if (!$d['dar']): ?><div class="lbl-shop"><?= e($shop) ?></div><?php endif; ?>
                  <div class="lbl-brand"><?= e($L['ust']) ?><?= $L['model'] !== '' ? ' ' . e($L['model']) : '' ?></div>
                  <?php if ($L['alt'] !== ''): ?><div class="lbl-sub"><?= e($L['alt']) ?></div><?php endif; ?>
                  <?php if ($fiyatli && $L['fiyat'] !== ''): ?><div class="lbl-price"><?= e($L['fiyat']) ?></div><?php endif; ?>
                </div>
                <div class="lbl-yuz arka">
                  <div class="lbl-qr"><?= qr_svg($L['kod'], 200, 'M') ?></div>
                  <small class="lbl-kod"><?= e($L['kod']) ?></small>
                </div>
              <?php elseif ($d['tip'] === 'asma'): ?>
                <div class="lbl-half ters">
                  <div class="lbl-brand"><?= e($L['ust']) ?><?= $L['model'] !== '' ? ' ' . e($L['model']) : '' ?></div>
                  <div class="lbl-sub"><?= e($L['alt']) ?></div>
                  <?php if ($fiyatli && $L['fiyat'] !== ''): ?><div class="lbl-price"><?= e($L['fiyat']) ?></div><?php endif; ?>
                </div>
                <div class="lbl-fold"><span class="lbl-hole"></span></div>
                <div class="lbl-half">
                  <div class="lbl-brand"><?= e($L['ust']) ?><?= $L['model'] !== '' ? ' ' . e($L['model']) : '' ?></div>
                  <div class="lbl-sub"><?= e($L['alt']) ?></div>
                  <div class="lbl-row">
                    <div class="lbl-qr"><?= qr_svg($L['kod'], 200, 'M') ?></div>
                    <div class="lbl-meta">
                      <?php if ($fiyatli && $L['fiyat'] !== ''): ?><b><?= e($L['fiyat']) ?></b><?php endif; ?>
                      <small><?= e($L['kod']) ?></small>
                    </div>
                  </div>
                </div>
              <?php else: ?>
                <div class="lbl-top">
                  <?php if (!$d['dar']): ?><div class="lbl-shop"><?= e($shop) ?></div><?php endif; ?>
                  <div class="lbl-brand"><?= e($L['ust']) ?><?= $L['model'] !== '' ? ' ' . e($L['model']) : '' ?></div>
                  <?php if (!$d['dar'] && $L['alt'] !== ''): ?><div class="lbl-sub"><?= e($L['alt']) ?></div><?php endif; ?>
                </div>
                <div class="lbl-row">
                  <div class="lbl-qr"><?= qr_svg($L['kod'], 200, 'M') ?></div>
                  <div class="lbl-meta">
                    <?php if ($fiyatli && $L['fiyat'] !== ''): ?><b><?= e($L['fiyat']) ?></b><?php endif; ?>
                    <small><?= e($L['kod']) ?></small>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
</body></html>
    <?php
    exit;
}

/* ---------- Ayar ekranı ---------- */
$bag = static function (array $ek) use ($d, $kesim, $fiyatli, $atla, $kacar, $stokKadar, $kapsam, $secili): string {
    $p = [
        'duzen' => $d['anahtar'], 'w' => $d['w'], 'h' => $d['h'], 'cols' => $d['cols'], 'rows' => $d['rows'],
        'ml' => $d['ml'], 'mt' => $d['mt'], 'gx' => $d['gx'], 'gy' => $d['gy'],
        'kesim' => $kesim ? '1' : '0', 'fiyat' => $fiyatli ? '1' : '0',
        'atla' => $atla, 'kacar' => $kacar, 'stokkadar' => $stokKadar ? '1' : '0',
        'kapsam' => $kapsam, 'ids' => implode(',', $secili),
    ];
    return 'etiket.php?' . http_build_query(array_merge($p, $ek));
};

page_start('Etiket sihirbazı', 'cerceve');
page_header('Etiket sihirbazı', 'A4 kâğıt ve normal yazıcıyla çerçeve barkodlama', '', 'cerceve.php', 'Depo · Vitrin');
?>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2><?= icon('print') ?> Düzen</h2><small class="muted">Kâğıdınıza göre seçin</small></div>
      <form method="get" class="stack">
        <input type="hidden" name="kapsam" value="<?= e($kapsam) ?>">
        <input type="hidden" name="ids" value="<?= e(implode(',', $secili)) ?>">
        <div class="pick-list">
          <?php foreach ($duzenler as $k => $v): ?>
            <label class="pick">
              <input type="radio" name="duzen" value="<?= e($k) ?>" <?= $d['anahtar'] === $k ? 'checked' : '' ?>>
              <span>
                <b><?= e($v['ad']) ?></b>
                <small class="block muted">
                  <?= match ($v['tip']) {
                      'kesme' => 'Düz A4 kâğıda bitişik basılır: kesimler uçtan uca düz çizgi olur, önce şeritlere sonra tek tek kesersiniz. Bütün yazılar düz durur.',
                      'katlamali' => 'Ortadaki kesikli çizgiden katlanıp sapa geçirilir: bir yüzde marka ve fiyat, öbür yüzde karekod. Yazılar ters dönmez.',
                      'asma'  => 'Ortadan YATAY katlanan askı etiketi; bu yüzden üst yarısı bilerek ters basılır, katlayınca iki yüz de düz okunur. Delikten ip/lastik geçirilir.',
                      default => 'Kırtasiyeden alınan A4 yapışkanlı etiket kâğıdı. Ölçüleri kendi kâğıdınıza göre aşağıdan ayarlayın.',
                  } ?>
                </small>
              </span>
            </label>
          <?php endforeach; ?>
        </div>

        <h3 class="sub">Ölçüler (mm)</h3>
        <div class="grid cols-4">
          <label class="field"><span>Etiket genişliği</span><input name="w" inputmode="decimal" value="<?= e((string) $d['w']) ?>"></label>
          <label class="field"><span>Etiket yüksekliği</span><input name="h" inputmode="decimal" value="<?= e((string) $d['h']) ?>"></label>
          <label class="field"><span>Sütun</span><input name="cols" inputmode="numeric" value="<?= (int) $d['cols'] ?>"></label>
          <label class="field"><span>Satır</span><input name="rows" inputmode="numeric" value="<?= (int) $d['rows'] ?>"></label>
          <label class="field"><span>Sol kenar</span><input name="ml" inputmode="decimal" value="<?= e((string) $d['ml']) ?>"></label>
          <label class="field"><span>Üst kenar</span><input name="mt" inputmode="decimal" value="<?= e((string) $d['mt']) ?>"></label>
          <label class="field"><span>Yatay aralık</span><input name="gx" inputmode="decimal" value="<?= e((string) $d['gx']) ?>"></label>
          <label class="field"><span>Dikey aralık</span><input name="gy" inputmode="decimal" value="<?= e((string) $d['gy']) ?>"></label>
        </div>

        <h3 class="sub">Baskı</h3>
        <div class="grid cols-4">
          <input type="hidden" name="stokkadar" value="0">
          <label class="field check-field span-2"><input type="checkbox" name="stokkadar" value="1" <?= $stokKadar ? 'checked' : '' ?>>
            Stok adedi kadar bas <small class="muted">(her fiziksel çerçeveye bir etiket)</small></label>
          <label class="field"><span>Sabit sayı</span>
            <input name="kacar" inputmode="numeric" value="<?= (int) $kacar ?>" <?= $stokKadar ? 'readonly' : '' ?>>
            <small class="muted">Yukarıdaki kutu işaretliyse stok adedi geçerlidir</small></label>
          <label class="field"><span>Baştan kaç etiket atlansın</span>
            <input name="atla" inputmode="numeric" value="<?= (int) $atla ?>">
            <small class="muted">Yarısı kullanılmış yapışkanlı kâğıt için</small></label>
          <label class="field check-field"><input type="checkbox" name="kesim" value="1" <?= $kesim ? 'checked' : '' ?>> Kesim çizgileri</label>
          <label class="field check-field"><input type="checkbox" name="fiyat" value="1" <?= $fiyatli ? 'checked' : '' ?>> Fiyat yazılsın</label>
        </div>

        <div class="form-actions">
          <button class="btn btn-primary"><?= icon('check') ?> Önizlemeyi güncelle</button>
        </div>
      </form>
    </section>

    <section class="card">
      <div class="card-head">
        <h2>Sayfada nasıl duracak</h2>
        <small class="muted">A4 · <?= (int) $d['cols'] ?>×<?= (int) $d['rows'] ?> = <?= (int) $d['sayfada'] ?> etiket</small>
      </div>
      <div class="sheet-preview" style="--pw:<?= $d['w'] ?>;--ph:<?= $d['h'] ?>;--pml:<?= $d['ml'] ?>;--pmt:<?= $d['mt'] ?>;--pgx:<?= $d['gx'] ?>;--pgy:<?= $d['gy'] ?>;--pcols:<?= (int) $d['cols'] ?>">
        <?php for ($i = 0; $i < (int) $d['sayfada']; $i++): ?>
          <div class="sheet-cell <?= $i < $atla ? 'atlanan' : '' ?>"></div>
        <?php endfor; ?>
      </div>
      <p class="hint">
        Karekod kenarı <b><?= e(number_format($d['qr'], 1, ',', '')) ?> mm</b> olacak.
        <?= $d['dar'] ? 'Bu ölçü sınırda; telefonla okunmazsa daha büyük bir etiket seçin.' : 'Telefon kamerasıyla rahat okunur.' ?>
      </p>
    </section>
  </div>

  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2>Hangi çerçeveler</h2></div>
      <div class="pick-list">
        <label class="pick">
          <input type="radio" name="kapsam-r" <?= $kapsam === 'secili' ? 'checked' : '' ?>
                 onclick="location.href='<?= e($bag(['kapsam' => 'secili'])) ?>'">
          <span><b>Seçilenler</b><small class="block muted"><?= count($secili) ?> çerçeve · stok listesinden gelir</small></span>
        </label>
        <label class="pick">
          <input type="radio" name="kapsam-r" <?= $kapsam === 'hepsi' ? 'checked' : '' ?>
                 onclick="location.href='<?= e($bag(['kapsam' => 'hepsi'])) ?>'">
          <span><b>Vitrindeki bütün çerçeveler</b><small class="block muted">Adedi sıfırdan büyük olanlar</small></span>
        </label>
        <label class="pick">
          <input type="radio" name="kapsam-r" <?= $kapsam === 'kritik' ? 'checked' : '' ?>
                 onclick="location.href='<?= e($bag(['kapsam' => 'kritik'])) ?>'">
          <span><b>Kritik / biten</b><small class="block muted">Yeni gelenleri etiketlerken</small></span>
        </label>
      </div>
      <?php $basilacak = min(ETIKET_SINIRI, $toplamEtiket) + $atla; ?>
      <p class="hint" style="margin-top:12px">
        <?= count($kalemler) ?> çerçeve · toplam <b><?= (int) $basilacak ?> etiket</b>
        (<?= (int) ceil(max(1, $basilacak) / max(1, (int) $d['sayfada'])) ?> sayfa)
        <?php if ($stokKadar): ?><br><span class="muted">Stok adedi kadar basılıyor: 120 adetlik bir model için 120 etiket.</span><?php endif; ?>
        <?php if ($kirpildi): ?><br><span class="text-danger">Tek seferde en çok <?= ETIKET_SINIRI ?> etiket basılır; kalanı ikinci baskıda alın.</span><?php endif; ?>
      </p>

      <div class="form-actions" style="margin-top:12px;flex-wrap:wrap;gap:8px">
        <a class="btn btn-primary btn-lg" href="<?= e($bag(['bas' => '1'])) ?>" target="_blank"><?= icon('print') ?> Etiketleri bas</a>
        <a class="btn" href="<?= e($bag(['cetvel' => '1'])) ?>" target="_blank">Ölçü denetim sayfası</a>
      </div>
      <?php if (is_super()): ?>
        <form method="post" style="margin-top:10px">
          <?= csrf_field() ?>
          <input type="hidden" name="eylem" value="varsayilan">
          <button class="btn btn-sm">Bu düzeni varsayılan yap</button>
        </form>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Elimde sadece A4 var, nasıl?</h2></div>
      <ul class="kv">
        <li><span><b>Kesmesi kolay olsun diye</b> etiketler bitişik dizildi: her kesim sayfanın bir ucundan
          öbür ucuna düz bir çizgi. Önce yatay şeritlere ayırın, sonra şeritleri dikey kesin. Köşelerdeki
          işaretler ilk kesimi hizalamak içindir.</span></li>
        <li><span><b>Sapa takmak için</b> “sapa katlanan iki yüzlü” düzeni seçin: ortadan katlayıp sapa geçirin,
          uçlarını şeffaf bantla birleştirin. Ön yüzde marka ve fiyat, arka yüzde karekod olur.</span></li>
        <li><span><b>En derli toplu yol:</b> kırtasiyeden <b>A4 yapışkanlı etiket kâğıdı</b> alın (lazer/inkjet,
          paketin üstünde 70×37 gibi ölçü yazar). Düzeni seçip ölçüyü girin, doğrudan yapışkanlı kâğıda basın.</span></li>
        <li><span><b>Ters duran yazı:</b> yalnızca “askı etiketi” düzeninde üst yarı ters basılır — o etiket
          yatay katlandığı için. Katlayınca iki yüz de düz okunur. Diğer düzenlerin hepsinde yazılar düzdür.</span></li>
        <li><span><b>İlk baskıdan önce</b> “Ölçü denetim sayfası”nı basıp cetvelle ölçün: yazıcı ölçeği %100 değilse
          bütün etiketler kayar.</span></li>
        <li><span><b>Barkod okuyucu gerekmez:</b> etiketteki karekodu bu sistemdeki arama kutusundan
          <b>telefon kamerasıyla</b> okutuyorsunuz; altındaki kodu elle de yazabilirsiniz.</span></li>
      </ul>
    </section>
  </aside>
</div>

<?php page_end();
