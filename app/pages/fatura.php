<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/fatura.php';

/* ==========================================================================
   4.12.0 — Fatura taslağı düzenleme / kontrol / UBL-TR önizleme
   ========================================================================== */

$me = require_login();
ozellik_gereksin('efatura');
if (!can_see_amounts()) {
    render_error_page('Yetkiniz yok', 'Tutarları görme yetkiniz olmadığı için faturalar açılamaz.');
}

$id = is_post() ? post_int('id') : query_int('id');
$f = row('SELECT * FROM faturalar WHERE id = ?', [$id]);
if (!$f) {
    http_response_code(404);
    render_error_page('Fatura bulunamadı', 'Silinmiş olabilir.');
}
$self = 'fatura.php?id=' . $id;
$duzenlenebilir = $f['durum'] === 'taslak';

/* XML indirme (önizleme) */
if (!is_post() && query('xml') === '1') {
    $satirlar = rows('SELECT * FROM fatura_satirlari WHERE fatura_id = ? ORDER BY sira, id', [$id]);
    $xml = $f['xml'] ?: fatura_ubl_xml($f, $satirlar);
    header('Content-Type: application/xml; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . ($f['fatura_no'] ?: 'TASLAK-' . $id) . '.xml"');
    header('X-Content-Type-Options: nosniff');
    echo $xml;
    exit;
}

if (is_post()) {
    $eylem = post('eylem');
    try {
        if (!$duzenlenebilir && !in_array($eylem, ['geri_al', 'iptal'], true)) {
            throw new DomainException('Yalnızca taslak faturalar düzenlenebilir.');
        }
        if ($eylem === 'baslik') {
            $tip = post('alici_tip') === 'kurum' ? 'kurum' : 'kisi';
            $kimlik = preg_replace('/\D/', '', post('alici_kimlik')) ?? '';
            update('faturalar', [
                'profil'              => isset(fatura_profilleri()[post('profil')]) ? post('profil') : 'EARSIVFATURA',
                'tip'                 => isset(fatura_tipleri()[post('tip')]) ? post('tip') : 'SATIS',
                'gonderim_sekli'      => post('gonderim_sekli') === 'KAGIT' ? 'KAGIT' : 'ELEKTRONIK',
                'alici_tip'           => $tip,
                'alici_ad'            => mb_substr(tr_title(post('alici_ad')), 0, 80) ?: null,
                'alici_soyad'         => mb_substr(tr_title(post('alici_soyad')), 0, 80) ?: null,
                'alici_unvan'         => mb_substr(trim(post('alici_unvan')), 0, 200) ?: null,
                'alici_kimlik'        => $kimlik !== '' ? mb_substr($kimlik, 0, 11) : null,
                'alici_vergi_dairesi' => mb_substr(trim(post('alici_vergi_dairesi')), 0, 80) ?: null,
                'alici_adres'         => mb_substr(trim(post('alici_adres')), 0, 255) ?: null,
                'alici_ilce'          => mb_substr(tr_title(post('alici_ilce')), 0, 60) ?: null,
                'alici_il'            => mb_substr(tr_title(post('alici_il')), 0, 60) ?: null,
                'alici_eposta'        => filter_var(post('alici_eposta'), FILTER_VALIDATE_EMAIL) ?: null,
                'alici_telefon'       => mb_substr(trim(post('alici_telefon')), 0, 20) ?: null,
                'notlar'              => mb_substr(trim(post('notlar')), 0, 500) ?: null,
            ], 'id = ?', [$id]);
            flash('Fatura bilgileri kaydedildi.');
        } elseif ($eylem === 'satir_kaydet') {
            $sid = post_int('satir_id');
            $miktar = (float) str_replace(',', '.', post('miktar'));
            $fiyatHam = parse_money(post('fiyat')) ?? 0.0;
            $kdv = (float) post('kdv_orani');
            if (!isset(fatura_kdv_oranlari()[(int) $kdv])) {
                throw new DomainException('KDV oranı geçersiz.');
            }
            if ($miktar <= 0 || $fiyatHam < 0) {
                throw new DomainException('Miktar ve fiyat geçersiz.');
            }
            // Personel fiyatı KDV dahil girer (mağazadaki etiket fiyatı); birim fiyat KDV hariç saklanır.
            $birim = post('fiyat_tur') === 'haric' ? $fiyatHam : round($fiyatHam / (1 + $kdv / 100), 4);
            $veri = [
                'ad'          => mb_substr(trim(post('ad')), 0, 200) ?: 'Ürün',
                'miktar'      => $miktar,
                'birim_fiyat' => $birim,
                'iskonto'     => max(0, parse_money(post('iskonto')) ?? 0.0),
                'kdv_orani'   => $kdv,
            ];
            if ($sid > 0) {
                update('fatura_satirlari', $veri, 'id = ? AND fatura_id = ?', [$sid, $id]);
            } else {
                $sira = (int) scalar('SELECT COALESCE(MAX(sira), 0) + 1 FROM fatura_satirlari WHERE fatura_id = ?', [$id]);
                insert('fatura_satirlari', $veri + ['fatura_id' => $id, 'sira' => $sira, 'birim' => 'C62']);
            }
            fatura_hesapla($id);
            flash('Satır kaydedildi.');
        } elseif ($eylem === 'satir_sil') {
            q('DELETE FROM fatura_satirlari WHERE id = ? AND fatura_id = ?', [post_int('satir_id'), $id]);
            fatura_hesapla($id);
            flash('Satır silindi.');
        } elseif ($eylem === 'hazir') {
            $guncel = row('SELECT * FROM faturalar WHERE id = ?', [$id]);
            $satirlar = rows('SELECT * FROM fatura_satirlari WHERE fatura_id = ? ORDER BY sira, id', [$id]);
            $hatalar = fatura_kontrol($guncel, $satirlar);
            if ($hatalar) {
                throw new DomainException('Hazır işaretlenemedi: ' . implode(' ', $hatalar));
            }
            $guncel['duzenleme'] = date('Y-m-d H:i:s');
            update('faturalar', [
                'durum'     => 'hazir',
                'duzenleme' => $guncel['duzenleme'],
                'xml'       => fatura_ubl_xml($guncel, $satirlar),
            ], 'id = ?', [$id]);
            audit('fatura_hazir', 'fatura', $id, ['toplam' => (float) $guncel['genel_toplam']]);
            flash('Fatura kontrol edildi ve hazır olarak işaretlendi. Entegratör bağlanınca gönderilebilecek.');
        } elseif ($eylem === 'geri_al' && $f['durum'] === 'hazir') {
            update('faturalar', ['durum' => 'taslak', 'xml' => null, 'duzenleme' => null], 'id = ?', [$id]);
            flash('Fatura yeniden taslağa alındı.');
        } elseif ($eylem === 'iptal' && in_array($f['durum'], ['taslak', 'hazir'], true)) {
            update('faturalar', ['durum' => 'iptal'], 'id = ?', [$id]);
            audit('fatura_iptal', 'fatura', $id);
            flash('Taslak iptal edildi.');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect($self);
}

$satirlar = rows('SELECT * FROM fatura_satirlari WHERE fatura_id = ? ORDER BY sira, id', [$id]);
$hatalar = $duzenlenebilir ? fatura_kontrol($f, $satirlar) : [];
$dagilim = fatura_kdv_dagilimi($id);
[$dAd, $dTon] = fatura_durumlari()[$f['durum']] ?? [$f['durum'], 'gray'];
$firma = fatura_firma();
$ro = $duzenlenebilir ? '' : 'disabled';

page_start('Fatura', 'faturalar');
?>
<a class="back-link" href="faturalar.php"><?= icon('arrow-left') ?> Faturalar</a>
<?php page_header(
    $f['fatura_no'] ?: 'Fatura taslağı #' . $id,
    fatura_profilleri()[$f['profil']] . ($f['order_id'] ? ' · ' . order_no((int) $f['order_id']) : ''),
    '<span class="badge tone-' . e($dTon) . '">' . e($dAd) . '</span> <a class="btn btn-sm" href="' . e($self) . '&xml=1">' . icon('download') . ' UBL-TR XML</a>',
    '',
    'Fatura'
); ?>

<?php if ($hatalar): ?>
  <div class="alert alert-warn"><div><b>Hazır işaretlemeden önce:</b><ul style="margin:6px 0 0 18px"><?php foreach ($hatalar as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul></div></div>
<?php endif; ?>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2>Satırlar</h2><small class="muted">Fiyatlar KDV dahil girilir; birim fiyat KDV hariç hesaplanır.</small></div>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>#</th><th>Açıklama</th><th class="num">Miktar</th><th class="num">Birim (KDV hariç)</th><th class="num">KDV</th><th class="num">Tutar</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($satirlar as $s): ?>
          <tr>
            <td><?= (int) $s['sira'] ?></td>
            <td><?= e($s['ad']) ?><?php if ((float) $s['iskonto'] > 0): ?><small class="block muted">İskonto <?= e(money($s['iskonto'])) ?></small><?php endif; ?></td>
            <td class="num"><?= e(rtrim(rtrim(number_format((float) $s['miktar'], 3, ',', '.'), '0'), ',')) ?></td>
            <td class="num"><?= e(number_format((float) $s['birim_fiyat'], 4, ',', '.')) ?></td>
            <td class="num">%<?= e((string) (float) $s['kdv_orani']) ?><small class="block muted"><?= e(money($s['kdv_tutar'])) ?></small></td>
            <td class="num"><b><?= e(money((float) $s['tutar'] + (float) $s['kdv_tutar'])) ?></b></td>
            <td class="row-actions">
              <?php if ($duzenlenebilir): ?>
                <form method="post" data-confirm="Satır silinsin mi?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="eylem" value="satir_sil"><input type="hidden" name="satir_id" value="<?= (int) $s['id'] ?>"><button class="btn btn-sm btn-ghost"><?= icon('trash') ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if ($duzenlenebilir): ?>
        <details <?= $satirlar ? '' : 'open' ?> style="margin-top:12px">
          <summary class="btn btn-sm"><?= icon('plus') ?> Satır ekle</summary>
          <form method="post" class="grid cols-4" style="margin-top:10px;align-items:end">
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="eylem" value="satir_kaydet">
            <label class="field span-2"><span>Açıklama</span><input name="ad" required maxlength="200" placeholder="örn. Progresif cam (çift)"></label>
            <label class="field"><span>Miktar</span><input name="miktar" value="1" inputmode="decimal"></label>
            <label class="field"><span>KDV</span><select name="kdv_orani"><?php foreach (fatura_kdv_oranlari() as $k => $v): ?><option value="<?= $k ?>" <?= (float) $k === fatura_varsayilan_kdv() ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Birim fiyat</span><input name="fiyat" required inputmode="decimal" placeholder="1.250,00"></label>
            <label class="field"><span>Fiyat</span><select name="fiyat_tur"><option value="dahil">KDV dahil</option><option value="haric">KDV hariç</option></select></label>
            <label class="field"><span>İskonto (TL)</span><input name="iskonto" inputmode="decimal" placeholder="0"></label>
            <div class="form-actions"><button class="btn btn-primary btn-sm">Ekle</button></div>
          </form>
        </details>
      <?php endif; ?>
      <ul class="kv" style="margin-top:14px">
        <li><span>Mal/hizmet toplamı</span><b><?= e(money($f['ara_toplam'])) ?></b></li>
        <?php if ((float) $f['iskonto_toplam'] > 0): ?><li><span>İskonto</span><b>− <?= e(money($f['iskonto_toplam'])) ?></b></li><?php endif; ?>
        <?php foreach ($dagilim as $oran => $dd): ?><li><span>KDV %<?= e($oran) ?> (matrah <?= e(money($dd['matrah'])) ?>)</span><b><?= e(money($dd['kdv'])) ?></b></li><?php endforeach; ?>
        <li><span><b>Ödenecek tutar</b></span><b style="font-size:1.15em"><?= e(money($f['genel_toplam'])) ?></b></li>
        <li><span class="muted mini"><?= e(tutar_yaziyla((float) $f['genel_toplam'])) ?></span></li>
      </ul>
    </section>

    <section class="card">
      <div class="card-head"><h2>İşlemler</h2></div>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <?php if ($duzenlenebilir): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="eylem" value="hazir"><button class="btn btn-primary" <?= $hatalar ? 'disabled' : '' ?>><?= icon('check') ?> Kontrol et, hazır işaretle</button></form>
        <?php elseif ($f['durum'] === 'hazir'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="eylem" value="geri_al"><button class="btn">Taslağa geri al</button></form>
          <button class="btn" disabled title="Entegratör bağlanınca açılır">GİB'e gönder</button>
        <?php endif; ?>
        <?php if (in_array($f['durum'], ['taslak', 'hazir'], true)): ?>
          <form method="post" data-confirm="Taslak iptal edilsin mi?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="eylem" value="iptal"><button class="btn btn-ghost danger">İptal et</button></form>
        <?php endif; ?>
      </div>
      <p class="hint">Hazır işaretlenen fatura kilitlenir ve UBL-TR belgesi saklanır. Numara ve e-imza, entegratör bağlandığında gönderim sırasında verilir.</p>
    </section>
  </div>

  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2>Alıcı ve belge</h2></div>
      <form method="post" class="stack" style="gap:8px" data-guard>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="eylem" value="baslik">
        <fieldset <?= $ro ?> style="border:0;padding:0;margin:0;display:grid;gap:8px">
          <label class="field"><span>Profil</span><select name="profil"><?php foreach (fatura_profilleri() as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['profil'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
          <div class="grid cols-2" style="gap:8px">
            <label class="field"><span>Tür</span><select name="tip"><?php foreach (fatura_tipleri() as $k => $v): ?><option value="<?= e($k) ?>" <?= $f['tip'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Teslim</span><select name="gonderim_sekli"><option value="ELEKTRONIK">Elektronik</option><option value="KAGIT" <?= $f['gonderim_sekli'] === 'KAGIT' ? 'selected' : '' ?>>Kâğıt</option></select></label>
          </div>
          <label class="field"><span>Alıcı</span><select name="alici_tip"><option value="kisi">Kişi</option><option value="kurum" <?= $f['alici_tip'] === 'kurum' ? 'selected' : '' ?>>Kurum / şirket</option></select></label>
          <div class="grid cols-2" style="gap:8px">
            <label class="field"><span>Ad</span><input name="alici_ad" value="<?= e((string) $f['alici_ad']) ?>"></label>
            <label class="field"><span>Soyad</span><input name="alici_soyad" value="<?= e((string) $f['alici_soyad']) ?>"></label>
          </div>
          <label class="field"><span>Unvan (kurum)</span><input name="alici_unvan" value="<?= e((string) $f['alici_unvan']) ?>"></label>
          <label class="field"><span>T.C. kimlik / VKN</span><input name="alici_kimlik" inputmode="numeric" maxlength="11" value="<?= e((string) $f['alici_kimlik']) ?>" placeholder="bilinmiyorsa 11111111111" autocomplete="off"></label>
          <label class="field"><span>Vergi dairesi</span><input name="alici_vergi_dairesi" value="<?= e((string) $f['alici_vergi_dairesi']) ?>"></label>
          <label class="field"><span>Adres</span><input name="alici_adres" value="<?= e((string) $f['alici_adres']) ?>"></label>
          <div class="grid cols-2" style="gap:8px">
            <label class="field"><span>İlçe</span><input name="alici_ilce" value="<?= e((string) $f['alici_ilce']) ?>"></label>
            <label class="field"><span>İl</span><input name="alici_il" value="<?= e((string) $f['alici_il']) ?>"></label>
          </div>
          <div class="grid cols-2" style="gap:8px">
            <label class="field"><span>E-posta</span><input type="email" name="alici_eposta" value="<?= e((string) $f['alici_eposta']) ?>"></label>
            <label class="field"><span>Telefon</span><input name="alici_telefon" value="<?= e((string) $f['alici_telefon']) ?>"></label>
          </div>
          <label class="field"><span>Not</span><textarea name="notlar" rows="2" maxlength="500"><?= e((string) $f['notlar']) ?></textarea></label>
          <?php if ($duzenlenebilir): ?><button class="btn btn-primary btn-sm">Kaydet</button><?php endif; ?>
        </fieldset>
      </form>
    </section>
    <section class="card">
      <div class="card-head"><h2>Satıcı</h2><?php if (is_super()): ?><a class="link" href="settings.php?tab=efatura">Düzenle</a><?php endif; ?></div>
      <p class="mini" style="margin:0"><b><?= e($firma['unvan'] ?: trim($firma['ad'] . ' ' . $firma['soyad']) ?: '—') ?></b><br>
        <?= e($firma['kimlik'] ? (strlen($firma['kimlik']) === 11 ? 'TCKN ' : 'VKN ') . $firma['kimlik'] : 'VKN girilmemiş') ?> · <?= e($firma['vergi_dairesi'] ?: 'VD yok') ?><br>
        <?= e(trim($firma['adres'] . ' ' . $firma['ilce'] . '/' . $firma['il'], ' /')) ?></p>
    </section>
  </aside>
</div>
<?php page_end();
