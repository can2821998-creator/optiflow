<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   Çerçeve stoğu — vitrindeki çerçevelerin adedi, maliyeti, barkodu.
   Adet her değiştiğinde hareket kaydı yazılır.
   ========================================================================== */

$me = require_login();

/* ---------- Eski etiket bağlantısı: sihirbaza yönlendir ---------- */
if (query('yazdir') !== '') {
    redirect('etiket.php?kapsam=secili&kacar=' . max(1, query_int('adet') ?: 1) . '&ids=' . urlencode(query('ids')));
}

/* ---------- İşlemler ---------- */
if (is_post()) {
    csrf_check();
    $eylem = post('eylem');
    $id = post_int('id');
    $don = 'cerceve.php' . (post('q') !== '' ? '?q=' . urlencode(post('q')) : '');

    if ($eylem === 'kaydet') {
        $marka = mb_substr(trim(post('brand')), 0, 80);
        if ($marka === '') {
            flash('Marka zorunlu.', 'error');
            redirect($don);
        }
        $barkod = mb_substr(trim(post('barcode')), 0, 64);
        if ($barkod !== '' && (int) scalar('SELECT COUNT(*) FROM frame_items WHERE barcode = ? AND id <> ?', [$barkod, $id])) {
            flash('Bu barkod başka bir çerçevede kullanılıyor.', 'error');
            redirect($don);
        }
        $sayi = static fn(string $k, int $alt = 0): int => max($alt, (int) post($k));
        $para = static function (string $k): ?float {
            $v = parse_money(post($k));
            return ($v === null || $v <= 0) ? null : $v;
        };

        $veri = [
            'brand'       => $marka,
            'model'       => mb_substr(trim(post('model')), 0, 80) ?: null,
            'color'       => mb_substr(trim(post('color')), 0, 60) ?: null,
            'size'        => mb_substr(trim(post('size')), 0, 40) ?: null,
            'barcode'     => $barkod ?: null,
            'min_qty'     => $sayi('min_qty'),
            'cost'        => $para('cost'),
            'price'       => $para('price'),
            'supplier_id' => post_int('supplier_id') ?: null,
            'shelf'       => mb_substr(trim(post('shelf')), 0, 40) ?: null,
            'note'        => mb_substr(trim(post('note')), 0, 255) ?: null,
            'is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id > 0) {
            $eski = row('SELECT * FROM frame_items WHERE id = ?', [$id]);
            if (!$eski) {
                flash('Çerçeve bulunamadı.', 'error');
                redirect($don);
            }
            update('frame_items', $veri + ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            $yeniAdet = max(0, (int) post('qty'));
            if ($yeniAdet !== (int) $eski['qty']) {
                frame_move($id, $yeniAdet - (int) $eski['qty'], 'sayim', null, 'Kayıt düzenlendi');
            }
            audit('frame_update', 'frame', $id, ['çerçeve' => frame_item_label($veri)]);
            flash(frame_item_label($veri) . ' güncellendi.');
        } else {
            if ($veri['barcode'] === null) {
                $veri['barcode'] = frame_new_barcode();
            }
            $id = insert('frame_items', $veri + [
                'qty'        => 0,
                'created_by' => (int) $me['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $ilkAdet = max(0, (int) post('qty'));
            if ($ilkAdet > 0) {
                frame_move($id, $ilkAdet, 'giris', null, 'İlk stok girişi');
            }
            audit('frame_create', 'frame', $id, ['çerçeve' => frame_item_label($veri), 'adet' => $ilkAdet]);
            flash(frame_item_label($veri) . ' stoğa eklendi.');
        }
        redirect('cerceve.php?duzenle=' . $id);
    }

    if ($eylem === 'hareket' && $id > 0) {
        $delta = (int) post('delta');
        $sebep = post('sebep') ?: ($delta > 0 ? 'giris' : 'satis');
        if ($delta !== 0) {
            $yeni = frame_move($id, $delta, $sebep, null, mb_substr(post('not'), 0, 255));
            audit('frame_move', 'frame', $id, ['değişim' => $delta, 'sebep' => frame_reason_label($sebep), 'yeni adet' => $yeni]);
            flash('Stok güncellendi: ' . ($delta > 0 ? '+' : '') . $delta . ' → ' . $yeni . ' adet.');
        }
        redirect($don);
    }

    if ($eylem === 'sil' && $id > 0 && is_super()) {
        $kalem = row('SELECT * FROM frame_items WHERE id = ?', [$id]);
        if ($kalem) {
            if ((int) scalar('SELECT COUNT(*) FROM orders WHERE frame_item_id = ?', [$id])) {
                update('frame_items', ['is_active' => 0], 'id = ?', [$id]);
                flash('Bu çerçeve siparişlerde kullanıldığı için silinmedi, pasife alındı.');
            } else {
                q('DELETE FROM frame_moves WHERE frame_item_id = ?', [$id]);
                q('DELETE FROM frame_items WHERE id = ?', [$id]);
                audit('frame_delete', 'frame', $id, ['çerçeve' => frame_item_label($kalem)]);
                flash('Çerçeve silindi.');
            }
        }
        redirect('cerceve.php');
    }
}

/* ---------- Liste ---------- */
$filtreler = ['hepsi' => 'Tümü', 'kritik' => 'Kritik / biten', 'vitrin' => 'Vitrinde var', 'pasif' => 'Pasif'];
$f = query('f', 'hepsi');
if (!isset($filtreler[$f])) {
    $f = 'hepsi';
}
$q = trim(query('q'));

$kosul = match ($f) {
    'kritik' => 'AND i.is_active = 1 AND i.qty <= i.min_qty',
    'vitrin' => 'AND i.is_active = 1 AND i.qty > 0',
    'pasif'  => 'AND i.is_active = 0',
    default  => '',
};
$params = [];
$arama = '';
if ($q !== '') {
    $arama = "AND (i.brand LIKE ? OR i.model LIKE ? OR i.color LIKE ? OR i.barcode = ? OR i.shelf LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $q, $like];
}

$kalemler = rows(
    "SELECT i.*, s.name AS supplier_name
       FROM frame_items i
       LEFT JOIN suppliers s ON s.id = i.supplier_id
      WHERE 1 = 1 $kosul $arama
      ORDER BY i.qty <= i.min_qty DESC, i.brand, i.model
      LIMIT 400",
    $params
);

/* Barkod tam eşleşmesi tek sonuç verdiyse doğrudan o kalemi aç */
$duzenle = null;
$duzenleId = query_int('duzenle');
if ($duzenleId > 0) {
    $duzenle = frame_item_with_moves($duzenleId);
} elseif ($q !== '' && count($kalemler) === 1 && ($kalemler[0]['barcode'] ?? '') === $q) {
    $duzenle = frame_item_with_moves((int) $kalemler[0]['id']);
}

$ozet = frame_summary();
$tedarikciler = rows('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name');

page_start('Çerçeve stoğu', 'cerceve');
page_header(
    'Çerçeve stoğu',
    'Vitrindeki çerçeveler, adetleri ve barkodları',
    '<a class="btn" href="etiket.php?kapsam=secili&ids=' . e(implode(',', array_map('intval', array_column($kalemler, 'id')))) . '">' . icon('print') . ' Etiket sihirbazı</a>',
    '',
    'Depo · Vitrin'
);
?>

<section class="stats">
  <div class="stat"><small>Vitrindeki çerçeve</small><b><?= (int) $ozet['adet'] ?></b><span><?= (int) $ozet['kalem'] ?> ayrı model</span></div>
  <?php if (can_see_amounts()): ?>
    <div class="stat"><small>Maliyet değeri</small><b><?= e(money($ozet['maliyet'])) ?></b><span>Stoğa bağlı para</span></div>
    <div class="stat tone-green"><small>Satış değeri</small><b><?= e(money($ozet['satis'])) ?></b><span>Etiket fiyatlarıyla</span></div>
  <?php endif; ?>
  <div class="stat <?= (int) $ozet['kritik'] + (int) $ozet['biten'] > 0 ? 'tone-amber' : '' ?>">
    <small>Kritik / biten</small><b><?= (int) $ozet['kritik'] + (int) $ozet['biten'] ?></b><span><?= (int) $ozet['biten'] ?> model tükendi</span>
  </div>
</section>

<nav class="tabs" aria-label="Stok filtresi">
  <?php foreach ($filtreler as $k => $ad): ?>
    <a class="tab <?= $f === $k ? 'active' : '' ?>" href="cerceve.php?f=<?= e($k) ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>"><?= e($ad) ?></a>
  <?php endforeach; ?>
</nav>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="toolbar">
        <form class="search" method="get" role="search" data-barcode-form>
          <input type="hidden" name="f" value="<?= e($f) ?>">
          <?= icon('search') ?>
          <input type="search" name="q" value="<?= e($q) ?>" placeholder="Marka, model, renk, raf veya barkod" aria-label="Çerçeve ara" data-barcode-input>
          <button type="button" class="icon-btn" data-barcode-scan hidden title="Kameradan barkod okut"><?= icon('eye') ?></button>
        </form>
      </div>

      <?php if (!$kalemler): ?>
        <?= empty_state('Çerçeve bulunamadı', $q !== '' ? '“' . e($q) . '” için kayıt yok. Sağdaki formdan ekleyebilirsiniz.' : 'Sağdaki formdan ilk çerçeveyi ekleyin.') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table rem-table">
            <thead>
              <tr>
                <th>Çerçeve</th>
                <th class="hide-sm">Raf / barkod</th>
                <th class="num">Adet</th>
                <?php if (can_see_amounts()): ?><th class="num hide-md">Fiyat</th><?php endif; ?>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($kalemler as $k):
              $kritik = (int) $k['qty'] <= (int) $k['min_qty'];
              $bitti = (int) $k['qty'] === 0; ?>
              <tr>
                <td>
                  <a class="cell-link" href="cerceve.php?duzenle=<?= (int) $k['id'] ?>">
                    <span class="cell-main">
                      <b><?= e((string) $k['brand']) ?><?= $k['model'] ? ' ' . e((string) $k['model']) : '' ?></b>
                      <small class="block muted">
                        <?= e(trim(((string) $k['color']) . ' ' . ((string) $k['size']))) ?: 'Renk/beden girilmemiş' ?>
                        <?php if (!(int) $k['is_active']): ?> · <span class="badge sm tone-gray">pasif</span><?php endif; ?>
                      </small>
                    </span>
                  </a>
                </td>
                <td class="hide-sm">
                  <?= $k['shelf'] ? e((string) $k['shelf']) : '<span class="muted">—</span>' ?>
                  <small class="block muted"><?= e((string) $k['barcode']) ?></small>
                </td>
                <td class="num">
                  <b class="<?= $bitti ? 'text-danger' : ($kritik ? 'text-warn' : '') ?>"><?= (int) $k['qty'] ?></b>
                  <small class="block muted">en az <?= (int) $k['min_qty'] ?></small>
                </td>
                <?php if (can_see_amounts()): ?>
                  <td class="num hide-md">
                    <?= $k['price'] !== null ? e(money($k['price'])) : '<span class="muted">—</span>' ?>
                    <small class="block muted"><?= $k['cost'] !== null ? 'alış ' . e(money($k['cost'])) : '' ?></small>
                  </td>
                <?php endif; ?>
                <td class="row-actions rem-actions">
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="eylem" value="hareket">
                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <input type="hidden" name="delta" value="1">
                    <input type="hidden" name="sebep" value="giris">
                    <button class="btn btn-sm" title="Stok girişi (+1)">+1</button>
                  </form>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="eylem" value="hareket">
                    <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                    <input type="hidden" name="q" value="<?= e($q) ?>">
                    <input type="hidden" name="delta" value="-1">
                    <input type="hidden" name="sebep" value="satis">
                    <button class="btn btn-sm" title="Satış (−1)" <?= $bitti ? 'disabled' : '' ?>>−1</button>
                  </form>
                  <a class="btn btn-sm btn-primary" href="cerceve.php?duzenle=<?= (int) $k['id'] ?>">Aç</a>
                  <a class="btn btn-sm" href="etiket.php?kapsam=secili&ids=<?= (int) $k['id'] ?>"
                     title="Etiket bas (<?= max(1, (int) $k['qty']) ?> adet)"><?= icon('print') ?></a>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="list-foot"><span class="muted"><?= count($kalemler) ?> kalem · kritik olanlar üstte</span></div>
      <?php endif; ?>
    </section>

    <?php if ($duzenle && $duzenle['moves']): ?>
      <section class="card">
        <div class="card-head"><h2>Stok hareketleri</h2><small class="muted"><?= e(frame_item_label($duzenle)) ?></small></div>
        <div class="table-wrap">
          <table class="table compact">
            <thead><tr><th>Zaman</th><th>Hareket</th><th>Sebep</th><th class="hide-sm">Kim / not</th></tr></thead>
            <tbody>
              <?php foreach ($duzenle['moves'] as $m): ?>
                <tr>
                  <td class="small muted nowrap"><?= e(date_tr($m['created_at'], true)) ?></td>
                  <td><b class="<?= (int) $m['delta'] > 0 ? 'text-ok' : 'text-danger' ?>"><?= (int) $m['delta'] > 0 ? '+' : '' ?><?= (int) $m['delta'] ?></b></td>
                  <td><?= e(frame_reason_label($m['reason'])) ?><?php if ($m['order_id']): ?> · <a class="link" href="order.php?id=<?= (int) $m['order_id'] ?>"><?= e(order_no((int) $m['order_id'])) ?></a><?php endif; ?></td>
                  <td class="hide-sm small muted"><?= e((string) $m['kullanici']) ?><?= $m['note'] ? ' · ' . e((string) $m['note']) : '' ?></td>
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
      <div class="card-head">
        <h2><?= $duzenle ? 'Çerçeveyi düzenle' : 'Yeni çerçeve' ?></h2>
        <?php if ($duzenle): ?><a class="btn btn-sm" href="cerceve.php">Yeni ekle</a><?php endif; ?>
      </div>
      <form method="post" class="grid cols-2" data-guard>
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="kaydet">
        <input type="hidden" name="id" value="<?= (int) ($duzenle['id'] ?? 0) ?>">
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <label class="field"><span>Marka</span><input name="brand" value="<?= e((string) ($duzenle['brand'] ?? '')) ?>" required></label>
        <label class="field"><span>Model</span><input name="model" value="<?= e((string) ($duzenle['model'] ?? '')) ?>"></label>
        <label class="field"><span>Renk</span><input name="color" value="<?= e((string) ($duzenle['color'] ?? '')) ?>"></label>
        <label class="field"><span>Beden</span><input name="size" value="<?= e((string) ($duzenle['size'] ?? '')) ?>" placeholder="52□21-140"></label>
        <label class="field"><span>Adet</span><input name="qty" inputmode="numeric" value="<?= (int) ($duzenle['qty'] ?? 1) ?>"></label>
        <label class="field"><span>Kritik adet</span><input name="min_qty" inputmode="numeric" value="<?= (int) ($duzenle['min_qty'] ?? 1) ?>"></label>
        <?php if (can_see_amounts()): ?>
          <label class="field"><span>Alış fiyatı</span><input name="cost" inputmode="decimal" value="<?= $duzenle && $duzenle['cost'] !== null ? e(number_format((float) $duzenle['cost'], 2, ',', '.')) : '' ?>"></label>
          <label class="field"><span>Satış fiyatı</span><input name="price" inputmode="decimal" value="<?= $duzenle && $duzenle['price'] !== null ? e(number_format((float) $duzenle['price'], 2, ',', '.')) : '' ?>"></label>
        <?php endif; ?>
        <label class="field"><span>Raf / yer</span><input name="shelf" value="<?= e((string) ($duzenle['shelf'] ?? '')) ?>" placeholder="Vitrin 2"></label>
        <label class="field"><span>Barkod</span><input name="barcode" value="<?= e((string) ($duzenle['barcode'] ?? '')) ?>" placeholder="boş bırakın, üretilsin"></label>
        <label class="field"><span>Tedarikçi</span>
          <select name="supplier_id">
            <option value="">— seçilmedi —</option>
            <?php foreach ($tedarikciler as $t): ?>
              <option value="<?= (int) $t['id'] ?>" <?= (int) ($duzenle['supplier_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field check-field"><input type="checkbox" name="is_active" <?= !$duzenle || (int) $duzenle['is_active'] ? 'checked' : '' ?>> Satışta</label>
        <label class="field span-all"><span>Not</span><input name="note" value="<?= e((string) ($duzenle['note'] ?? '')) ?>"></label>
        <div class="form-actions span-all">
          <?php if ($duzenle && is_super()): ?>
            <button class="btn btn-ghost danger" form="cerceve-sil">Sil</button>
          <?php endif; ?>
          <button class="btn btn-primary"><?= icon('check') ?> <?= $duzenle ? 'Kaydet' : 'Stoğa ekle' ?></button>
        </div>
      </form>
      <?php if ($duzenle && is_super()): ?>
        <form method="post" id="cerceve-sil" data-confirm="Bu çerçeve silinsin mi?">
          <?= csrf_field() ?>
          <input type="hidden" name="eylem" value="sil">
          <input type="hidden" name="id" value="<?= (int) $duzenle['id'] ?>">
        </form>
      <?php endif; ?>
      <?php if ($duzenle): ?>
        <p class="hint" style="margin-top:12px">
          <a class="link" href="etiket.php?kapsam=secili&kacar=2&ids=<?= (int) $duzenle['id'] ?>">Etiket bas</a> ·
          barkodu arama kutusuna okutunca bu kayıt açılır.
        </p>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Nasıl kullanılır?</h2></div>
      <ul class="kv">
        <li><span>Çerçeveyi ekleyip <b>etiketini bastırın</b>; etiketteki karekodu bu sayfadaki arama kutusuna okuttuğunuzda kayıt doğrudan açılır.</span></li>
        <li><span>Satışta çerçeveyi <b>sipariş ekranından seçin</b> — stoktan kendiliğinden düşer, hareket kaydı oluşur.</span></li>
        <li><span>Sayım günü arama kutusuna barkodu okutun, adedi düzeltin; fark <b>sayım düzeltmesi</b> olarak kaydedilir.</span></li>
        <li><span>Adet kritik seviyeye inince menüdeki rozet ve bu sayfadaki kutu uyarır.</span></li>
      </ul>
    </section>
  </aside>
</div>

<?php page_end(['cerceve.js']);
