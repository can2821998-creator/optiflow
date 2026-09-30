<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_login();
pro_gereksin('uts');   // 4.11.0 — OptiFlow Pro özelliği (Pro paket + masaüstü); değilse tanıtım sayfası

/* ==========================================================================
   ÜTS Karekod — ÜTS'den alınan stok / bildirim listesinden Medula'ya
   okutulabilir karekod etiketleri (A4 PDF) + SKT geçmiş ürünler için ÜTS
   imha/bertaraf toplu bildirim dosyası.

   Dosya SUNUCUYA YÜKLENMEZ: okuma, sınıflandırma, karekod ve PDF üretimi
   tamamen tarayıcıda (assets/uts-karekod.js) yapılır. Bu sayfa yalnızca
   arayüzü ve mağazanın etiket düzenlerini verir.
   ========================================================================== */

/* Etiket düzenleri: klasik UTS düzeni + mağazanın hazır A4 düzenleri */
$duzenler = [
    'klasik' => [
        'ad' => 'Klasik ÜTS · A4 4×10 (≈49×27 mm, çerçeveli)',
        'w' => (210 - 12 - 1.2 * 3) / 4, 'h' => (297 - 12 - 1.2 * 9) / 10,
        'cols' => 4, 'rows' => 10, 'ml' => 6, 'mt' => 6, 'gx' => 1.2, 'gy' => 1.2,
    ],
];
foreach (label_layouts() as $k => $d) {
    if (($d['tip'] ?? '') === 'asma' || ($d['tip'] ?? '') === 'katlamali') {
        continue;                                  // katlanan/askı düzenleri karekod etiketine uygun değil
    }
    $duzenler[$k] = [
        'ad' => $d['ad'], 'w' => (float) $d['w'], 'h' => (float) $d['h'],
        'cols' => (int) $d['cols'], 'rows' => (int) $d['rows'],
        'ml' => (float) $d['ml'], 'mt' => (float) $d['mt'], 'gx' => (float) $d['gx'], 'gy' => (float) $d['gy'],
    ];
}
$varsayilan = setting('uts_label_layout', 'klasik');
if (!isset($duzenler[$varsayilan])) {
    $varsayilan = 'klasik';
}
if (is_post()) {
    csrf_check();
    $k = (string) post('duzen');
    if (post('eylem') === 'varsayilan' && isset($duzenler[$k])) {
        setting_set('uts_label_layout', $k);
        flash('ÜTS etiket düzeni varsayılan yapıldı.');
    }
    redirect('uts-karekod.php');
}

$kategoriler = [
    'all' => 'Tümü', 'cam' => 'Cam', 'cerceve' => 'Çerçeve', 'lens' => 'Lens',
    'kontak-lens' => 'Kontak lens', 'lens-solusyonu' => 'Solüsyon', 'unmatched' => 'Ayrılmayan',
];
$sktler = ['all' => 'Tümü', 'expired' => 'Geçmiş', '30' => '≤ 30 gün', '90' => '≤ 90 gün', 'missing' => 'SKT yok'];

page_start('ÜTS karekod', 'uts', ['body' => 'uts-page']);
page_header('ÜTS karekod etiketleri', 'ÜTS listesinden Medula\'ya okutulabilir karekodları hazırlayın, A4 etikete basın', '', '', 'Atölye');
?>
<link rel="stylesheet" href="<?= e(asset('uts-karekod.css')) ?>">

<div id="uts-app" class="uts" data-layouts="<?= e(json_encode($duzenler, JSON_UNESCAPED_UNICODE)) ?>">

  <noscript><div class="alert alert-error">Bu araç tarayıcıda çalışır; JavaScript açık olmalıdır.</div></noscript>
  <div id="uts-durum" class="alert alert-info" role="status" hidden></div>
  <ul id="uts-uyari" class="alert alert-warn uts-warn" hidden></ul>

  <div class="split">
    <div class="split-main">

      <section class="card">
        <div class="card-head">
          <h2><?= icon('download') ?> ÜTS listesini yükle</h2>
          <small class="muted">Dosya bu bilgisayarda işlenir, sunucuya gönderilmez</small>
        </div>
        <label id="uts-drop" class="uts-drop" for="uts-file">
          <input id="uts-file" type="file" accept=".xls,.xlsx,.csv,.txt,.xml" class="uts-hide">
          <b>ÜTS dosyasını buraya bırakın ya da tıklayıp seçin</b>
          <span>XLS · XLSX · CSV · XML — stok listesi veya bildirim listesi</span>
        </label>
        <dl class="uts-meta">
          <dt>Dosya</dt><dd id="uts-dosya-adi">Henüz dosya seçilmedi</dd>
          <dt>Algılanan</dt><dd id="uts-sayfa-adi">—</dd>
        </dl>
        <p class="hint">Cam / çerçeve ayrımı için dosyada <b>Ürün Tanımı</b> sütunu olmalıdır (ÜTS › Stok sorgulama çıktısı).
          Yalnızca bildirim listesi yüklenirse tüm karekodlar yine üretilir, ayrım yapılamaz.</p>
      </section>

      <section class="stats uts-stats">
        <div class="stat"><small>Toplam</small><b data-count="all">0</b><span>karekod üretilebilir</span></div>
        <div class="stat tone-blue"><small>Cam</small><b data-count="cam">0</b><span>Çerçeve: <i data-count="cerceve">0</i></span></div>
        <div class="stat tone-teal"><small>Lens</small><b data-count="lensAll">0</b><span><i data-count="kontak-lens">0</i> kontak lens · <i data-count="lens-solusyonu">0</i> solüsyon</span></div>
        <div class="stat tone-red"><small>SKT geçmiş</small><b data-count="expired">0</b><span>≤30 gün <i data-count="30">0</i> · ≤90 gün <i data-count="90">0</i></span></div>
      </section>

      <section class="card">
        <div class="card-head">
          <h2>Önizleme</h2>
          <label class="uts-search"><?= icon('search') ?><input id="uts-ara" type="search" placeholder="GTIN, seri, lot veya ürün ara" data-need-file disabled></label>
        </div>
        <div class="table-wrap">
          <table class="table compact uts-table">
            <thead><tr><th>Satır</th><th>Kategori</th><th>SKT</th><th>GTIN</th><th>Seri</th><th>Lot</th><th>Ürün</th><th>Karekod içeriği</th></tr></thead>
            <tbody id="uts-tablo"><tr><td colspan="8" class="muted">Dosya yüklendiğinde liste burada görünür.</td></tr></tbody>
          </table>
        </div>
      </section>
    </div>

    <aside class="split-side">
      <section class="card">
        <div class="card-head"><h2><?= icon('print') ?> Baskı seçimi</h2></div>
        <div class="stack">
          <fieldset class="field uts-fs">
            <legend>Ürün grubu</legend>
            <div class="chip-group">
              <?php foreach ($kategoriler as $v => $ad): ?>
                <label class="chip"><input class="uts-hide" type="radio" name="uts-kat" value="<?= e($v) ?>" <?= $v === 'all' ? 'checked' : '' ?>><?= e($ad) ?>&nbsp;<em data-count="<?= e($v) ?>">0</em></label>
              <?php endforeach; ?>
            </div>
          </fieldset>
          <fieldset class="field uts-fs">
            <legend>Son kullanma tarihi</legend>
            <div class="chip-group">
              <?php foreach ($sktler as $v => $ad): ?>
                <label class="chip"><input class="uts-hide" type="radio" name="uts-skt" value="<?= e($v) ?>" <?= $v === 'all' ? 'checked' : '' ?>><?= e($ad) ?></label>
              <?php endforeach; ?>
            </div>
          </fieldset>

          <label class="field"><span>Etiket düzeni</span>
            <select id="uts-duzen">
              <?php foreach ($duzenler as $k => $d): ?>
                <option value="<?= e($k) ?>" <?= $k === $varsayilan ? 'selected' : '' ?>><?= e($d['ad']) ?></option>
              <?php endforeach; ?>
              <option value="ozel">Özel (sütun × satır)</option>
            </select>
          </label>
          <div id="uts-ozel" class="uts-grid4" hidden>
            <label class="field"><span>Sütun</span><input id="uts-sutun" type="number" min="1" max="8" value="4" inputmode="numeric"></label>
            <label class="field"><span>Satır</span><input id="uts-satir" type="number" min="1" max="20" value="10" inputmode="numeric"></label>
            <label class="field"><span>Kenar mm</span><input id="uts-kenar" type="number" min="0" max="25" step="0.5" value="6"></label>
            <label class="field"><span>Boşluk mm</span><input id="uts-bosluk" type="number" min="0" max="10" step="0.1" value="1.2"></label>
          </div>
          <small id="uts-olcu" class="muted"></small>

          <div class="uts-grid2">
            <label class="field"><span>Baştan atla</span><input id="uts-atla" type="number" min="0" max="200" value="0" inputmode="numeric" title="Yarım kullanılmış etiket kâğıdında boş geçilecek hücre sayısı"></label>
            <label class="field"><span>Etiket başlığı</span><input id="uts-baslik" maxlength="24" placeholder="Otomatik (CAM, LENS…)"></label>
          </div>
          <label class="check"><input id="uts-adet" type="checkbox" checked> <span>Lot takipli ürünlerde <b>adet kadar</b> etiket</span></label>
          <label class="check"><input id="uts-skt-yaz" type="checkbox" checked> SKT'yi etikete yaz</label>
          <label class="check"><input id="uts-cerceve" type="checkbox" checked> Kesim çerçevesi çiz</label>

          <div class="uts-out">
            <b id="uts-ozet">0 etiket</b>
            <button id="uts-pdf" type="button" class="btn btn-primary btn-block" disabled><?= icon('print') ?> PDF oluştur</button>
            <div class="btn-row">
              <button id="uts-csv" type="button" class="btn btn-sm" disabled>CSV al</button>
              <button id="uts-xlsx" type="button" class="btn btn-sm" disabled>XLSX al</button>
            </div>
            <small class="muted">Yazıcıda ölçek <b>%100 / gerçek boyut</b> seçin.</small>
          </div>
          <?php if (is_super()): ?>
            <form method="post" class="uts-def">
              <?= csrf_field() ?>
              <input type="hidden" name="eylem" value="varsayilan">
              <select name="duzen" aria-label="Varsayılan düzen">
                <?php foreach ($duzenler as $k => $d): ?><option value="<?= e($k) ?>" <?= $k === $varsayilan ? 'selected' : '' ?>><?= e($d['ad']) ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-sm btn-ghost">Varsayılan yap</button>
            </form>
          <?php endif; ?>
        </div>
      </section>

      <section class="card uts-imha">
        <div class="card-head"><h2>SKT geçmiş → ÜTS imha bildirimi</h2></div>
        <p class="hint">Seçimdeki SKT'si geçmiş ürünler için ÜTS toplu bildirim şablonunda
          <b>imha-bertaraf</b> dosyası (.xls) hazırlanır; gerekçe “Son kullanma tarihi geçmiş”. 1000 satırda bir dosya bölünür.</p>
        <div class="stack">
          <label class="field"><span>Belge numarası</span><input id="uts-belge" maxlength="60" placeholder="İmha tutanağı / belge no" data-need-file disabled></label>
          <label class="field"><span>Gerçek işlem tarihi</span><input id="uts-tarih" type="date" data-need-file disabled></label>
          <button id="uts-imha" type="button" class="btn btn-block uts-danger" disabled>İmha XLS hazırla · <span id="uts-imha-say">0 ürün</span></button>
        </div>
      </section>
    </aside>
  </div>
</div>

<script type="module" src="<?= e(asset('uts-karekod.js')) ?>"></script>
<?php page_end(); ?>
