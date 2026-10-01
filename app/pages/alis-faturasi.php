<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.14.0 — Alış faturası yükle (e-Fatura / e-Arşiv XML ya da ZIP)
   Akış: yükle → önizle (tedarikçi, mükerrer, kalem eşleme) → fatura fatura onayla
   ========================================================================== */

$me = require_login();
ozellik_gereksin('tedarik_finans');
if (!is_super()) {
    render_error_page('Yetki gerekli', 'Alış faturalarını yalnızca yönetici yükleyebilir.');
}

$anahtar = is_post() ? post('anahtar') : query('onizle');

if (is_post()) {
    $eylem = post('eylem');
    try {
        if ($eylem === 'yukle') {
            $dosyalar = [];
            $f = $_FILES['dosya'] ?? null;
            if (is_array($f) && isset($f['name'])) {
                $adlar = (array) $f['name'];
                foreach ($adlar as $i => $ad) {
                    $hata = (int) ((array) $f['error'])[$i];
                    if ($hata === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }
                    if ($hata !== UPLOAD_ERR_OK) {
                        throw new DomainException((string) $ad . ': yüklenemedi (dosya çok büyük olabilir).');
                    }
                    $dosyalar[] = ['ad' => (string) $ad, 'yol' => (string) ((array) $f['tmp_name'])[$i]];
                }
            }
            if (!$dosyalar) {
                throw new DomainException('XML ya da ZIP dosyası seçin.');
            }
            $belgeler = alis_dosyalari_oku($dosyalar);
            if (!$belgeler) {
                throw new DomainException('Yüklenen dosyalarda XML bulunamadı.');
            }
            $k = alis_gecici_kaydet($belgeler);
            redirect('alis-faturasi.php?onizle=' . $k);
        }
        if ($eylem === 'kaydet') {
            $belgeler = alis_gecici_oku($anahtar);
            $i = post_int('belge');
            if (!$belgeler || !isset($belgeler[$i])) {
                throw new DomainException('Önizleme süresi doldu; dosyayı yeniden yükleyin.');
            }
            $fat = alis_ubl_coz((string) $belgeler[$i]['xml']);
            $secim = post('tedarikci');
            $tedarikci = $secim === 'yeni' ? ['yeni' => true] : ['id' => (int) $secim];
            $kararlar = [];
            foreach ((array) ($_POST['kalem'] ?? []) as $sira => $k) {
                if (!is_array($k)) {
                    continue;
                }
                $kararlar[(int) $sira] = [
                    'islem'         => ($k['stok'] ?? '') === '1' ? 'stok' : 'yok',
                    'frame_item_id' => ($k['cerceve'] ?? '') === 'yeni' ? 'yeni' : (int) ($k['cerceve'] ?? 0),
                ];
            }
            $s = alis_kaydet($fat, $tedarikci, $kararlar, post('vade'), (string) $belgeler[$i]['xml']);
            audit('alis_fatura', 'supplier', $s['supplier_id'], ['fatura' => $fat['no'], 'tutar' => $fat['odenecek'], 'stok' => $s['stok']]);
            flash('Fatura ' . $fat['no'] . ' kaydedildi: cariye ' . money($fat['odenecek']) . ' borç yazıldı' . ($s['stok'] ? ', ' . $s['stok'] . ' adet çerçeve stoğa girdi' : '') . ($s['kart'] ? ' (' . $s['kart'] . ' yeni kart — fiyatlarını çerçeve stoğundan girin)' : '') . '.');
            if (post('senet_ver') === '1') {
                try {
                    senet_ver($s['supplier_id'], $fat['odenecek'], post('senet_vade'), post('senet_no'), date('Y-m-d'), $s['invoice_id']);
                    audit('senet_ver', 'supplier', $s['supplier_id'], ['fatura' => $fat['no'], 'tutar' => $fat['odenecek'], 'vade' => post('senet_vade')]);
                    flash('Fatura tutarında senet kaydedildi (vade ' . date_tr(post('senet_vade')) . ').');
                } catch (DomainException $e) {
                    flash('Senet kaydedilemedi: ' . $e->getMessage() . ' Tedarikçi kartından ekleyebilirsiniz.', 'error');
                }
            }
            $uts = alis_uts_bekleyen($fat['no']);
            if ($uts > 0) {
                flash('Bu faturanın ' . $uts . ' ürünü ÜTS\'de kabul bekliyor: ÜTS bildirimleri › Mal kabul ekranından kabul edin.', 'warn');
            }
            redirect('alis-faturasi.php?onizle=' . $anahtar . '#belge-' . $i);
        }
        if ($eylem === 'bitir') {
            alis_gecici_sil($anahtar);
            redirect('alis-faturasi.php');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect($anahtar !== '' && alis_gecici_oku($anahtar) ? 'alis-faturasi.php?onizle=' . $anahtar : 'alis-faturasi.php');
}

$tedarikciler = rows('SELECT id, name, tax_no FROM suppliers WHERE is_active = 1 ORDER BY name');

/* ---------- Önizleme ---------- */
if ($anahtar !== '') {
    $belgeler = alis_gecici_oku($anahtar);
    if (!$belgeler) {
        flash('Önizleme süresi doldu; dosyayı yeniden yükleyin.', 'error');
        redirect('alis-faturasi.php');
    }
    $cerceveler = [];
    $kartEtiketi = static fn(array $c): string => frame_item_label($c) . ($c['barcode'] ? ' · ' . $c['barcode'] : '') . ' (' . (int) $c['qty'] . ')' . ((int) $c['is_active'] === 1 ? '' : ' — pasif');
    foreach (rows('SELECT id, brand, model, color, size, barcode, qty, is_active FROM frame_items WHERE is_active = 1 ORDER BY brand, model, color') as $c) {
        $cerceveler[(int) $c['id']] = $kartEtiketi($c);
    }
    page_start('Alış faturası önizleme', 'alis-faturasi');
    page_header('Alış faturası önizleme', count($belgeler) . ' belge · kalemleri kontrol edip fatura fatura kaydedin.', '', '', 'Tedarik');
    foreach ($belgeler as $i => $b):
        try {
            $f = alis_ubl_coz((string) $b['xml']);
            $hata = '';
        } catch (DomainException $e) {
            $f = null;
            $hata = $e->getMessage();
        }
        ?>
        <section class="card" id="belge-<?= (int) $i ?>">
          <?php if (!$f): ?>
            <div class="card-head"><h2><?= e((string) $b['ad']) ?></h2><span class="badge tone-red">Okunamadı</span></div>
            <p class="text-danger"><?= e($hata) ?></p>
          <?php else:
              $ted = alis_tedarikci_bul($f['satici']['vkn']);
              $muk = alis_mukerrer($f, $ted ? (int) $ted['id'] : null);
              $engel = alis_engeller($f);
              $uyari = alis_uyarilar($f); ?>
            <div class="card-head">
              <h2><?= e($f['satici']['unvan'] ?: 'Satıcı adı yok') ?> · <?= e($f['no']) ?></h2>
              <?php if ($muk): ?><span class="badge tone-green">Kayıtlı</span><?php elseif ($engel): ?><span class="badge tone-red">İşlenemez</span><?php else: ?><span class="badge tone-amber">Onay bekliyor</span><?php endif; ?>
            </div>
            <ul class="kv">
              <li><span>Tarih</span><b><?= e(date_tr($f['tarih'])) ?></b></li>
              <li><span>Satıcı VKN/TCKN</span><b><?= e($f['satici']['vkn'] ?: '—') ?></b></li>
              <li><span>Ödenecek</span><b><?= money($f['odenecek']) ?></b><?= $f['kdv_toplam'] ? ' <small class="muted">KDV ' . money($f['kdv_toplam']) . '</small>' : '' ?></li>
              <li><span>Vade</span><b><?= $f['vade'] ? e(date_tr($f['vade'])) : '—' ?></b></li>
              <?php if ($f['ettn']): ?><li><span>ETTN</span><b><small><code><?= e($f['ettn']) ?></code></small></b></li><?php endif; ?>
            </ul>
            <?php foreach ($engel as $m): ?><div class="alert alert-error"><?= e($m) ?></div><?php endforeach; ?>
            <?php if ($muk): ?>
              <p class="hint">Bu fatura <?= e(date_tr((string) $muk['invoice_date'])) ?> tarihli kayıtla zaten işlenmiş. <a class="link" href="supplier.php?id=<?= (int) $muk['supplier_id'] ?>">Tedarikçi carisi</a></p>
            <?php elseif (!$engel): ?>
              <?php foreach ($uyari as $m): ?><div class="alert alert-warn"><?= e($m) ?></div><?php endforeach; ?>
              <form method="post" class="stack" style="gap:12px">
                <?= csrf_field() ?><input type="hidden" name="eylem" value="kaydet"><input type="hidden" name="anahtar" value="<?= e($anahtar) ?>"><input type="hidden" name="belge" value="<?= (int) $i ?>">
                <div class="grid cols-3">
                  <label class="field"><span>Tedarikçi</span>
                    <select name="tedarikci" required>
                      <?php if (!$ted): ?><option value="yeni" selected>+ Yeni tedarikçi: <?= e($f['satici']['unvan']) ?></option>
                      <?php elseif ((int) $ted['is_active'] !== 1): ?><option value="<?= (int) $ted['id'] ?>" selected><?= e($ted['name']) ?> — pasif (VKN eşleşti)</option><?php endif; ?>
                      <?php foreach ($tedarikciler as $t): ?><option value="<?= (int) $t['id'] ?>" <?= $ted && (int) $ted['id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?>
                    </select>
                    <?php if ($ted): ?><small class="muted">VKN <?= e($f['satici']['vkn']) ?> ile eşleşti<?= (int) $ted['is_active'] !== 1 ? '; tedarikçi pasif, kaydedince aktif olur' : '' ?>.</small><?php endif; ?></label>
                  <label class="field"><span>Vade</span><input type="date" name="vade" value="<?= e($f['vade']) ?>"></label>
                </div>
                <?php if ($f['kalemler']): ?>
                  <div class="table-wrap"><table class="table">
                    <thead><tr><th class="selc" title="Çerçeve stoğuna işle">Stok</th><th>Kalem</th><th class="num">Miktar</th><th class="num">Birim maliyet<br><small>KDV dahil</small></th><th>Çerçeve kartı</th></tr></thead>
                    <tbody>
                    <?php foreach ($f['kalemler'] as $k):
                        $oneri = alis_kalem_onerisi($ted ? (int) $ted['id'] : null, $k);
                        if ($oneri['frame_item_id'] !== null && !isset($cerceveler[$oneri['frame_item_id']])) {
                            $oc = row('SELECT id, brand, model, color, size, barcode, qty, is_active FROM frame_items WHERE id = ?', [$oneri['frame_item_id']]);
                            if ($oc) {
                                $cerceveler = [(int) $oc['id'] => $kartEtiketi($oc)] + $cerceveler;   // pasif kart da listede (kaydedince aktif olur)
                            }
                        }
                        $adet = alis_stok_adedi($k);
                        $varsayilanStok = $oneri['frame_item_id'] !== null && $adet > 0; ?>
                      <tr>
                        <td class="selc"><?php if ($adet > 0): ?><input type="checkbox" name="kalem[<?= (int) $k['sira'] ?>][stok]" value="1" <?= $varsayilanStok ? 'checked' : '' ?> aria-label="Stoğa işle"><?php else: ?><small class="muted" title="Kesirli ya da sıfır miktar">—</small><?php endif; ?></td>
                        <td><?= e($k['ad']) ?><small class="block muted"><?= $k['kod'] ? 'Kod ' . e($k['kod']) : '' ?><?= $k['gtin'] ? ' · GTIN ' . e($k['gtin']) : '' ?></small></td>
                        <td class="num"><?= e(rtrim(rtrim(number_format((float) $k['miktar'], 3, ',', '.'), '0'), ',')) ?> <small class="muted"><?= e($k['birim']) ?></small></td>
                        <td class="num"><?= money(alis_birim_maliyet($k)) ?><small class="block muted">%<?= e(rtrim(rtrim(number_format((float) $k['kdv_orani'], 2, ',', ''), '0'), ',')) ?></small></td>
                        <td>
                          <select name="kalem[<?= (int) $k['sira'] ?>][cerceve]" aria-label="Çerçeve kartı" style="max-width:340px">
                            <option value="yeni" <?= $oneri['frame_item_id'] === null ? 'selected' : '' ?>>+ Yeni kart oluştur</option>
                            <?php foreach ($cerceveler as $cid => $cad): ?><option value="<?= $cid ?>" <?= $oneri['frame_item_id'] === $cid ? 'selected' : '' ?>><?= e($cad) ?></option><?php endforeach; ?>
                          </select>
                          <?php if ($oneri['neden']): ?><small class="block muted"><?= e(['gtin' => 'Barkod/GTIN ile eşleşti', 'hafiza' => 'Önceki eşlemeden', 'kod' => 'Ürün kodu barkodla eşleşti'][$oneri['neden']]) ?></small><?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    </tbody>
                  </table></div>
                  <p class="hint">İşaretli kalemler çerçeve stoğuna girer, kartın maliyeti KDV dahil birim fiyatla güncellenir ve tedarikçinin ürün kodu bu karta bağlanır (sonraki faturalarda kendiliğinden eşleşir). Cam, hizmet, kargo gibi kalemleri işaretlemeyin.</p>
                <?php endif; ?>
                <details class="card inset">
                  <summary>Bu fatura için senet verildi mi?</summary>
                  <div class="grid cols-3" style="margin-top:10px">
                    <label class="check" style="align-self:end"><input type="checkbox" name="senet_ver" value="1"> <?= money($f['odenecek']) ?> tutarında senet verildi</label>
                    <label class="field"><span>Senet vadesi</span><input type="date" name="senet_vade" value="<?= e($f['vade']) ?>"></label>
                    <label class="field"><span>Senet no</span><input name="senet_no" maxlength="40"></label>
                  </div>
                </details>
                <div><button class="btn btn-primary"><?= icon('check') ?> Faturayı kaydet</button></div>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </section>
    <?php endforeach; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="bitir"><input type="hidden" name="anahtar" value="<?= e($anahtar) ?>"><button class="btn"><?= icon('check') ?> Bitti, yüklemeyi kapat</button></form>
    <?php
    page_end();
    exit;
}

/* ---------- Yükleme ekranı ---------- */
$sonlar = rows(
    "SELECT i.*, s.name AS tedarikci,
            (SELECT COUNT(*) FROM supplier_invoice_lines l WHERE l.invoice_id = i.id) AS kalem,
            (SELECT COALESCE(SUM(stok_adet), 0) FROM supplier_invoice_lines l WHERE l.invoice_id = i.id) AS stok
       FROM supplier_invoices i JOIN suppliers s ON s.id = i.supplier_id
      WHERE i.kaynak = 'xml' ORDER BY i.id DESC LIMIT 25"
);
page_start('Alış faturası yükle', 'alis-faturasi');
page_header('Alış faturası yükle', 'Tedarikçinin e-Fatura / e-Arşiv XML dosyasını (ya da XML\'li ZIP\'i) yükleyin; cari, vade, stok ve maliyet kendiliğinden işlenir.', '', '', 'Tedarik');
?>
<section class="card">
  <form method="post" enctype="multipart/form-data" class="stack" style="gap:10px;max-width:640px">
    <?= csrf_field() ?><input type="hidden" name="eylem" value="yukle">
    <label class="field"><span>XML ya da ZIP (birden çok seçilebilir)</span><input type="file" name="dosya[]" accept=".xml,.zip,application/xml,text/xml,application/zip" multiple required></label>
    <div><button class="btn btn-primary"><?= icon('download') ?> Yükle ve önizle</button></div>
  </form>
  <p class="hint">XML'i e-Fatura entegratörünüzün ya da GİB e-Arşiv portalının "gelen faturalar" ekranından indirebilirsiniz. Kağıt faturalar için tedarikçi kartındaki "Fatura ekle" formunu kullanın.</p>
</section>
<section class="card">
  <div class="card-head"><h2>Son yüklenen e-faturalar</h2></div>
  <?php if (!$sonlar): ?>
    <?= empty_state('Henüz e-fatura yüklenmedi', 'Yüklediğiniz faturalar burada listelenir.') ?>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Fatura</th><th>Tedarikçi</th><th class="num">Tutar</th><th class="hide-sm">Vade</th><th class="num hide-sm">Stoğa giren</th></tr></thead>
      <tbody>
      <?php foreach ($sonlar as $s): ?>
        <tr>
          <td><?= e((string) $s['invoice_no']) ?><small class="block muted"><?= e(date_tr((string) $s['invoice_date'])) ?> · <?= (int) $s['kalem'] ?> kalem</small></td>
          <td><a class="link" href="supplier.php?id=<?= (int) $s['supplier_id'] ?>"><?= e((string) $s['tedarikci']) ?></a></td>
          <td class="num"><?= money($s['amount']) ?></td>
          <td class="hide-sm"><?= $s['due_date'] ? e(date_tr((string) $s['due_date'])) : '—' ?></td>
          <td class="num hide-sm"><?= (int) $s['stok'] ?: '—' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>
<?php page_end();
