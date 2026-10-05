<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   Hızlı satış (4.17.0) — sipariş açmadan, barkodla, parçalı ödemeyle satış.
   Sepet tarayıcıda (assets/hizli-satis.js) tutulur; fiyat ve maliyet sunucuda
   katalogdan hesaplanır (app/satis.php). Fiş: print.php?type=satis&id=…
   ========================================================================== */

$me = require_login();
ozellik_gereksin('hizli_satis');
if (!satis_yetkili()) {
    render_error_page('Yetki yok', 'Hızlı satış için tutarları görme yetkisi gerekir. Yöneticinizden açmasını isteyin.');
}

/* ---------- JSON: ürün ve müşteri arama ---------- */
if (isset($_GET['ara'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['sonuclar' => satis_urun_ara(mb_substr(query('ara'), 0, 80))], JSON_UNESCAPED_UNICODE);
    exit;
}
if (isset($_GET['musteri'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $m = trim(mb_substr(query('musteri'), 0, 60));
    $sonuc = [];
    if (mb_strlen($m) >= 2) {
        $tel = preg_replace('/\D+/', '', $m) ?? '';
        $kosul = [];
        $p = [];
        if (strlen($tel) >= 4) {
            $kosul[] = 'phone LIKE ?';
            $p[] = '%' . $tel . '%';
        } else {
            // Her kelime adda ya da soyadda geçmeli (MySQL'de || birleştirme değildir; taşınabilir yazılır)
            foreach (array_slice(preg_split('/\s+/u', mb_strtolower($m)) ?: [], 0, 3) as $kelime) {
                $kosul[] = '(LOWER(first_name) LIKE ? OR LOWER(last_name) LIKE ?)';
                $p[] = '%' . $kelime . '%';
                $p[] = '%' . $kelime . '%';
            }
        }
        $sonuc = rows('SELECT id, first_name, last_name, phone FROM customers WHERE ' . implode(' AND ', $kosul) . ' ORDER BY id DESC LIMIT 8', $p);
        $sonuc = array_map(static fn($c) => ['id' => (int) $c['id'], 'ad' => trim($c['first_name'] . ' ' . $c['last_name']), 'tel' => (string) $c['phone']], $sonuc);
    }
    echo json_encode(['sonuclar' => $sonuc], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- İşlemler ---------- */
if (is_post()) {
    $eylem = post('eylem');
    try {
        if ($eylem === 'sat') {
            $sepet = json_decode((string) ($_POST['sepet'] ?? ''), true);
            $odemeler = json_decode((string) ($_POST['odemeler'] ?? ''), true);
            if (!is_array($sepet) || !is_array($odemeler)) {
                throw new DomainException('Sepet okunamadı; sayfayı yenileyip tekrar deneyin.');
            }
            $indirim = parse_money(post('indirim')) ?? 0.0;
            $id = satis_kaydet($sepet, $odemeler, $indirim, post_int('musteri_id') ?: null, post('not'));
            flash('Satış #' . $id . ' tamamlandı.');
            redirect('hizli-satis.php?satis=' . $id . '&yeni=1');
        }
        if ($eylem === 'iptal') {
            $id = post_int('id');
            satis_iptal($id, post('sebep'));
            flash('Satış #' . $id . ' iptal edildi; stok geri girdi.');
            redirect('hizli-satis.php?satis=' . $id);
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
        redirect('hizli-satis.php' . ($eylem === 'iptal' ? '?satis=' . post_int('id') : ''));
    }
}

/* ---------- Görünüm ---------- */
$bugun = date('Y-m-d');
$secili = query_int('satis') ? satis_bul(query_int('satis')) : null;
if ($secili && !is_super() && (int) $secili['created_by'] !== (int) $me['id']) {
    $secili = null;   // personel yalnızca kendi satışını açar
}
$liste = satis_listesi($bugun, $bugun);
$ozet = satis_ozeti($bugun, $bugun, is_super() ? null : (int) $me['id']);
$yontemler = payment_methods();
$azami = satis_azami_indirim_yuzde();

page_start('Hızlı satış', 'hizli-satis');
page_header(
    'Hızlı satış',
    'Barkodu okutun, ödemeyi alın: sipariş açmadan satış',
    '<a class="btn" href="urunler.php">' . icon('box') . ' Ürün kataloğu</a>',
    '',
    'Kasa'
);
?>
<section class="stats">
  <div class="stat"><small><?= is_super() ? 'Bugünkü hızlı satış' : 'Bugünkü satışlarınız' ?></small><b><?= (int) $ozet['adet'] ?></b><span><?= e(money($ozet['ciro'])) ?></span></div>
  <?php foreach (['nakit', 'kart'] as $y): ?>
    <div class="stat"><small><?= e($yontemler[$y]) ?></small><b><?= e(money($ozet['yontem'][$y] ?? 0)) ?></b><span>bugün, hızlı satıştan</span></div>
  <?php endforeach; ?>
</section>

<?php if ($secili): ?>
<section class="card satis-detay">
  <div class="card-head">
    <h2>Satış #<?= (int) $secili['id'] ?> <?php if ($secili['durum'] === 'iptal'): ?><span class="badge tone-red">İptal</span><?php else: ?><span class="badge tone-green">Tamamlandı</span><?php endif; ?></h2>
    <div class="row-actions">
      <a class="btn btn-sm" href="print.php?type=satis&id=<?= (int) $secili['id'] ?>" target="_blank" rel="noopener"><?= icon('print') ?> Fiş</a>
      <a class="btn btn-sm btn-primary" href="hizli-satis.php">Yeni satış</a>
    </div>
  </div>
  <p class="muted small"><?= e(date_tr($secili['created_at'], true)) ?> · <?= e((string) $secili['personel']) ?><?= $secili['musteri'] ? ' · ' . e(trim($secili['musteri']['first_name'] . ' ' . $secili['musteri']['last_name'])) : '' ?><?= $secili['not_metni'] ? ' · ' . e((string) $secili['not_metni']) : '' ?></p>
  <div class="table-wrap">
    <table class="table compact">
      <thead><tr><th>Kalem</th><th class="num">Adet</th><th class="num">Birim</th><th class="num">İndirim</th><th class="num">Tutar</th></tr></thead>
      <tbody>
      <?php foreach ($secili['kalemler'] as $k): ?>
        <tr><td><?= e($k['ad']) ?></td><td class="num"><?= (int) $k['adet'] ?></td><td class="num"><?= e(money($k['birim_fiyat'])) ?></td>
          <td class="num"><?= (float) $k['indirim'] > 0 ? e(money($k['indirim'])) : '—' ?></td><td class="num"><b><?= e(money($k['tutar'])) ?></b></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <?php $genel = round((float) $secili['indirim'] - array_sum(array_map(static fn($k) => (float) $k['indirim'], $secili['kalemler'])), 2); ?>
        <?php if ($genel > 0): ?><tr><td colspan="4">Sepet indirimi</td><td class="num">−<?= e(money($genel)) ?></td></tr><?php endif; ?>
        <tr><td colspan="4"><b>Toplam</b></td><td class="num"><b><?= e(money($secili['toplam'])) ?></b></td></tr>
        <tr><td colspan="4" class="muted">Ödeme</td><td class="num small"><?= e(implode(' + ', array_map(static fn($o) => ($yontemler[$o['method']] ?? $o['method']) . ' ' . money($o['amount']), $secili['odemeler']))) ?></td></tr>
      </tfoot>
    </table>
  </div>
  <?php if ($secili['durum'] === 'iptal'): ?>
    <p class="alert alert-warn" style="margin-top:12px">İptal edildi: <?= e(date_tr($secili['iptal_at'], true)) ?> · <?= e((string) $secili['iptal_sebep']) ?></p>
  <?php elseif (is_super()): ?>
    <details style="margin-top:12px"><summary class="small">Satışı iptal et</summary>
      <form method="post" class="grid cols-2" style="margin-top:10px" data-confirm="Satış iptal edilsin mi? Stok geri girer, satış kasadan çıkar.">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="iptal"><input type="hidden" name="id" value="<?= (int) $secili['id'] ?>">
        <label class="field span-all"><span>İptal sebebi</span><input name="sebep" required maxlength="255" placeholder="ör. müşteri iade etti"></label>
        <div class="form-actions span-all"><button class="btn btn-ghost danger">İptal et</button></div>
      </form>
    </details>
  <?php endif; ?>
</section>
<?php endif; ?>

<noscript><p class="alert alert-warn">Hızlı satış ekranı için tarayıcıda JavaScript açık olmalı.</p></noscript>

<form method="post" class="split satis-ekrani" id="satis-form" data-azami-indirim="<?= e((string) $azami) ?>" hidden>
  <?= csrf_field() ?>
  <input type="hidden" name="eylem" value="sat">
  <input type="hidden" name="sepet" value="[]" data-sepet-json>
  <input type="hidden" name="odemeler" value="[]" data-odeme-json>
  <input type="hidden" name="musteri_id" value="" data-musteri-id>

  <div class="split-main">
    <section class="card">
      <div class="toolbar satis-arama">
        <div class="search">
          <?= icon('barcode') ?>
          <input type="search" placeholder="Barkodu okutun ya da ürün adı yazın" aria-label="Barkod ya da ürün ara" autocomplete="off" autofocus
                 data-barkod-hedef data-ara>
          <button type="button" class="icon-btn" data-kamera hidden title="Kameradan barkod okut"><?= icon('eye') ?></button>
        </div>
        <button type="button" class="btn btn-sm" data-serbest><?= icon('plus') ?> Serbest kalem</button>
      </div>
      <ul class="satis-oneri" data-oneriler hidden></ul>

      <div class="table-wrap">
        <table class="table satis-sepet">
          <thead><tr><th>Ürün</th><th class="num">Adet</th><th class="num">Birim</th><th class="num">İndirim</th><th class="num">Tutar</th><th></th></tr></thead>
          <tbody data-sepet></tbody>
        </table>
      </div>
      <div class="satis-bos" data-bos>
        <?= icon('barcode') ?>
        <p><b>Sepet boş.</b> Ürünün barkodunu okutun ya da adını yazın. Katalogda olmayan bir şey için <b>Serbest kalem</b>'e basın.</p>
      </div>
    </section>
  </div>

  <aside class="split-side">
    <section class="card satis-odeme">
      <div class="card-head"><h2>Ödeme</h2><small class="muted" data-kalem-sayisi>0 kalem</small></div>
      <ul class="kv satis-toplamlar">
        <li><span>Ara toplam</span><b data-ara-toplam>0,00 ₺</b></li>
        <li><span>Kalem indirimleri</span><b data-kalem-indirim>0,00 ₺</b></li>
        <li><label for="genel-indirim">Sepet indirimi (₺)</label><input id="genel-indirim" name="indirim" inputmode="decimal" placeholder="0,00" data-genel-indirim></li>
      </ul>
      <div class="satis-toplam"><span>Toplam</span><b data-toplam>0,00 ₺</b></div>
      <p class="hint" data-indirim-uyari hidden></p>

      <div class="satis-yontemler">
        <?php foreach ($yontemler as $k => $ad): ?>
          <label class="field satis-yontem"><span><?= e($ad) ?></span>
            <span class="satis-yontem-giris"><input inputmode="decimal" placeholder="0,00" data-odeme="<?= e($k) ?>" aria-label="<?= e($ad) ?> tutarı">
            <button type="button" class="btn btn-sm" data-tamami="<?= e($k) ?>" title="Kalanın tamamı <?= e(mb_strtolower($ad)) ?>">Kalanı</button></span>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="satis-kalan" data-kalan>Ödeme bekleniyor</p>

      <details class="satis-paraustu">
        <summary>Para üstü hesapla</summary>
        <label class="field"><span>Müşteriden alınan nakit</span><input inputmode="decimal" data-alinan placeholder="0,00"></label>
        <p class="satis-paraustu-sonuc" data-paraustu></p>
      </details>

      <details class="satis-musteri">
        <summary>Müşteriye bağla <span class="muted small">(isteğe bağlı)</span></summary>
        <label class="field"><span>Ad ya da telefon</span><input type="search" autocomplete="off" data-musteri-ara placeholder="en az 2 harf"></label>
        <ul class="satis-oneri" data-musteri-oneri hidden></ul>
        <p class="small" data-musteri-secili hidden></p>
      </details>
      <label class="field"><span>Not <span class="muted small">(isteğe bağlı)</span></span><input name="not" maxlength="255"></label>

      <button class="btn btn-primary btn-lg satis-tamamla" data-tamamla disabled><?= icon('check') ?> Satışı tamamla</button>
      <p class="hint">Fiyatlar katalogdan gelir. <?= is_super() ? 'Yönetici olarak indirim sınırınız yok.' : 'En fazla %' . e(rtrim(rtrim(number_format($azami, 1, ',', ''), '0'), ',')) . ' indirim yapabilirsiniz.' ?></p>
    </section>
  </aside>
</form>

<section class="card">
  <div class="card-head"><h2><?= is_super() ? 'Bugünkü hızlı satışlar' : 'Bugünkü satışlarınız' ?></h2><small class="muted"><?= count($liste) ?> satış</small></div>
  <?php if (!$liste): ?>
    <?= empty_state('Bugün henüz satış yok', 'Tamamlanan satışlar burada listelenir; fişini buradan yeniden yazdırabilirsiniz.') ?>
  <?php else: ?>
  <div class="table-wrap">
    <table class="table compact">
      <thead><tr><th>Satış</th><th class="hide-sm">Saat</th><th class="hide-sm">Personel</th><th class="num">Kalem</th><th class="num">Toplam</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($liste as $s): ?>
        <tr class="<?= $s['durum'] === 'iptal' ? 'muted' : '' ?>">
          <td><a class="link" href="hizli-satis.php?satis=<?= (int) $s['id'] ?>">#<?= (int) $s['id'] ?></a><?= $s['durum'] === 'iptal' ? ' <span class="badge sm tone-red">iptal</span>' : '' ?></td>
          <td class="hide-sm small"><?= e(substr((string) $s['created_at'], 11, 5)) ?></td>
          <td class="hide-sm small"><?= e((string) $s['personel']) ?></td>
          <td class="num"><?= (int) $s['kalem_sayisi'] ?></td>
          <td class="num"><b><?= e(money($s['toplam'])) ?></b></td>
          <td class="row-actions"><a class="btn btn-sm" href="print.php?type=satis&id=<?= (int) $s['id'] ?>" target="_blank" rel="noopener"><?= icon('print') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
<?php page_end(['hizli-satis.js']);
