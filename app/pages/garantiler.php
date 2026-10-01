<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.16.0 — Garantiler: liste / arama, garanti ayrıntısı, talepler (tamir
   geçmişi), tedarikçiye gönderim ve garanti ayarları.
   ========================================================================== */

$me = require_login();
ozellik_gereksin('garanti');

$gid = is_post() ? post_int('garanti_id') : query_int('id');
$self = $gid ? 'garantiler.php?id=' . $gid : 'garantiler.php';

if (is_post()) {
    $eylem = post('eylem');
    try {
        $talep = post_int('talep_id') ? row('SELECT * FROM garanti_talepleri WHERE id = ? AND garanti_id = ?', [post_int('talep_id'), $gid]) : null;
        $talepGerekli = static function () use ($talep): array {
            if (!$talep) {
                throw new DomainException('Talep bulunamadı.');
            }
            return $talep;
        };
        switch ($eylem) {
            case 'talep_ekle':
                $tid = garanti_talep_ekle($gid, post('sikayet'));
                audit('garanti', 'garanti', $gid, ['işlem' => 'talep açıldı', 'şikâyet' => post('sikayet')]);
                flash('Garanti talebi açıldı (' . garanti_no($gid) . '-' . $tid . ').');
                break;
            case 'talep_tedarikci':
                $t = $talepGerekli();
                garanti_talep_tedarikciye((int) $t['id'], post_int('supplier_id'), post('gonderim'));
                audit('garanti', 'garanti', $gid, ['işlem' => 'tedarikçiye gönderildi', 'talep' => (int) $t['id']]);
                flash('Talep tedarikçiye gönderildi olarak işaretlendi. Formu yazdırabilir ya da WhatsApp\'la gönderebilirsiniz.');
                break;
            case 'talep_kapat':
                $t = $talepGerekli();
                $maliyet = post('maliyet') === '' ? 0.0 : parse_money(post('maliyet'));
                if ($maliyet === null || $maliyet < 0) {
                    throw new DomainException('Maliyet geçersiz. Örnek: 250,00');
                }
                garanti_talep_kapat((int) $t['id'], post('durum'), post('sonuc_tur'), post('sonuc'), $maliyet);
                audit('garanti', 'garanti', $gid, ['işlem' => post('durum') === 'reddedildi' ? 'kapsam dışı' : 'talep tamamlandı', 'sonuç' => garanti_sonuclari()[post('sonuc_tur')] ?? '', 'talep' => (int) $t['id']]);
                flash(post('durum') === 'reddedildi' ? 'Talep kapsam dışı olarak kapatıldı.' : 'Talep tamamlandı.');
                break;
            case 'talep_yeniden':
                $t = $talepGerekli();
                garanti_talep_yeniden_ac((int) $t['id']);
                audit('garanti', 'garanti', $gid, ['işlem' => 'talep yeniden açıldı', 'talep' => (int) $t['id']]);
                flash('Talep yeniden açıldı.');
                break;
            case 'talep_sil':
                if (!is_super()) {
                    throw new DomainException('Bu işlem için yetkiniz yok.');
                }
                $t = $talepGerekli();
                garanti_talep_sil((int) $t['id']);
                audit('garanti', 'garanti', $gid, ['işlem' => 'talep silindi', 'talep' => (int) $t['id']]);
                flash('Talep silindi.');
                break;
            case 'guncelle':
                garanti_guncelle($gid, ['urun' => post('urun'), 'seri_no' => post('seri_no'), 'supplier_id' => post_int('supplier_id'), 'bitis' => post('bitis'), 'kapsam' => post('kapsam')]);
                audit('garanti', 'garanti', $gid, ['işlem' => 'güncellendi', 'bitiş' => post('bitis')]);
                flash('Garanti güncellendi.');
                break;
            case 'durum':
                garanti_durum_degistir($gid, post('durum'));
                audit('garanti', 'garanti', $gid, ['işlem' => post('durum') === 'iptal' ? 'iptal edildi' : 'yeniden etkin']);
                flash(post('durum') === 'iptal' ? 'Garanti iptal edildi.' : 'Garanti yeniden etkin.');
                break;
            case 'sil':
                if (!is_super()) {
                    throw new DomainException('Bu işlem için yetkiniz yok.');
                }
                garanti_sil($gid);
                audit('garanti', 'garanti', $gid, ['işlem' => 'silindi']);
                flash('Garanti kaydı silindi.');
                redirect('garantiler.php');
            case 'ayar':
                if (!is_super()) {
                    throw new DomainException('Bu işlem için yetkiniz yok.');
                }
                foreach (array_keys(garanti_kalemleri()) as $k) {
                    $ay = (int) post('ay_' . $k);
                    if ($ay < 1 || $ay > 120) {
                        throw new DomainException(garanti_kalemleri()[$k] . ' için süre 1–120 ay olmalı.');
                    }
                    setting_set('garanti_' . $k . '_ay', (string) $ay);
                }
                setting_set('garanti_otomatik', post('otomatik') === '1' ? '1' : '0');
                setting_set('garanti_sure_uzat', post('sure_uzat') === '1' ? '1' : '0');
                $kosul = mb_substr(trim(str_replace("\r", '', post('kosullar'))), 0, 2000);
                setting_set('garanti_kosullari', $kosul === garanti_kosullari() ? '' : $kosul);
                audit('garanti', 'ayar', null, ['işlem' => 'ayarlar kaydedildi']);
                flash('Garanti ayarları kaydedildi.');
                redirect('garantiler.php#ayarlar');
            default:
                throw new DomainException('Bilinmeyen işlem.');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect($self . ($gid ? '#talepler' : ''));
}

$shop = setting('shop_name', 'OptiFlow');
$tedarikciler = rows('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name');

/* ---------------- Ayrıntı ---------------- */
if ($gid) {
    $g = garanti_bul($gid);
    if (!$g) {
        flash('Garanti bulunamadı.', 'error');
        redirect('garantiler.php');
    }
    $d = garanti_durumu($g);
    $talepler = garanti_talepleri($gid);
    $acikTalep = array_values(array_filter($talepler, static fn(array $t): bool => in_array($t['durum'], ['acik', 'tedarikcide'], true)))[0] ?? null;
    $tamirler = garanti_musteri_tamirleri((int) $g['customer_id'], (string) $g['baslangic']);
    $url = garanti_url($g);

    page_start(garanti_no($gid), 'garantiler');
    page_header(garanti_no($gid) . ' · ' . (garanti_kalemleri()[$g['kalem']] ?? $g['kalem']), (string) $g['urun'], '<a class="btn btn-sm" href="print.php?type=garanti&amp;id=' . (int) $gid . '" target="_blank" rel="noopener">' . icon('print') . ' Garanti kartı</a>', '', 'Atölye');
    ?>
    <p><a class="link" href="garantiler.php"><?= icon('arrow-left') ?> Garantiler</a></p>
    <section class="stats">
      <div class="stat tone-<?= e($d['ton']) ?>"><small>Durum</small><b><?= e(['gecerli' => 'Geçerli', 'bitti' => 'Doldu', 'iptal' => 'İptal'][$d['kod']]) ?></b><span>bitiş <?= e(date_tr((string) $g['bitis'])) ?></span></div>
      <div class="stat"><small>Kalan</small><b><?= $d['kod'] === 'gecerli' ? e(garanti_kalan_metni($d['kalan_gun'])) : '—' ?></b><span>başlangıç <?= e(date_tr((string) $g['baslangic'])) ?></span></div>
      <div class="stat <?= $acikTalep ? 'tone-amber' : '' ?>"><small>Talepler</small><b><?= count($talepler) ?></b><span><?= $acikTalep ? e(garanti_talep_durumlari()[$acikTalep['durum']][0]) : 'açık talep yok' ?></span></div>
    </section>

    <div class="grid cols-2" style="gap:14px;align-items:start">
      <section class="card">
        <div class="card-head"><h2>Bilgiler</h2></div>
        <div class="table-wrap"><table class="table"><tbody>
          <tr><th>Müşteri</th><td><?php if ($g['customer_id']): ?><a class="link" href="customer.php?id=<?= (int) $g['customer_id'] ?>"><?= e(trim($g['first_name'] . ' ' . $g['last_name'])) ?></a> · <?= e(phone_display($g['phone'])) ?><?php else: ?>—<?php endif; ?></td></tr>
          <tr><th>Sipariş</th><td><?= $g['order_id'] ? '<a class="link" href="order.php?id=' . (int) $g['order_id'] . '#garanti">' . e(order_no((int) $g['order_id'])) . '</a>' : '—' ?></td></tr>
          <tr><th>Seri no</th><td><?= e((string) ($g['seri_no'] ?: '—')) ?></td></tr>
          <tr><th>Tedarikçi</th><td><?= e((string) ($g['tedarikci'] ?: '—')) ?></td></tr>
          <?php if ($g['kapsam']): ?><tr><th>Kapsam notu</th><td><?= e((string) $g['kapsam']) ?></td></tr><?php endif; ?>
        </tbody></table></div>
        <details style="margin-top:10px">
          <summary class="linkish"><?= icon('edit') ?> Düzenle</summary>
          <form method="post" class="stack" style="gap:10px;margin-top:10px">
            <?= csrf_field() ?><input type="hidden" name="garanti_id" value="<?= (int) $gid ?>"><input type="hidden" name="eylem" value="guncelle">
            <label class="field"><span>Ürün</span><input name="urun" maxlength="160" required value="<?= e((string) $g['urun']) ?>"></label>
            <div class="grid cols-2">
              <label class="field"><span>Seri no</span><input name="seri_no" maxlength="80" value="<?= e((string) $g['seri_no']) ?>"></label>
              <label class="field"><span>Bitiş</span><input name="bitis" type="date" required value="<?= e((string) $g['bitis']) ?>"></label>
            </div>
            <label class="field"><span>Tedarikçi</span><select name="supplier_id"><option value="">—</option><?php foreach ($tedarikciler as $t): ?><option value="<?= (int) $t['id'] ?>"<?= (int) $g['supplier_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Kapsam notu (karta basılır)</span><input name="kapsam" maxlength="500" value="<?= e((string) $g['kapsam']) ?>" placeholder="örn. menteşe ve vida dahil"></label>
            <div><button class="btn btn-primary btn-sm">Kaydet</button></div>
          </form>
          <div class="btn-row" style="margin-top:10px">
            <form method="post" class="inline" data-confirm="<?= $g['durum'] === 'iptal' ? 'Garanti yeniden etkinleştirilsin mi?' : 'Garanti iptal edilsin mi? Müşteri karekodu okutunca "iptal edildi" görür.' ?>"><?= csrf_field() ?><input type="hidden" name="garanti_id" value="<?= (int) $gid ?>"><input type="hidden" name="eylem" value="durum"><input type="hidden" name="durum" value="<?= $g['durum'] === 'iptal' ? 'aktif' : 'iptal' ?>"><button class="linkish"><?= $g['durum'] === 'iptal' ? 'Yeniden etkinleştir' : 'Garantiyi iptal et' ?></button></form>
            <?php if (is_super()): ?>
              <form method="post" class="inline" data-confirm="Garanti kaydı ve tüm talepleri silinsin mi? Bu geri alınamaz."><?= csrf_field() ?><input type="hidden" name="garanti_id" value="<?= (int) $gid ?>"><input type="hidden" name="eylem" value="sil"><button class="linkish danger">Kaydı sil</button></form>
            <?php endif; ?>
          </div>
        </details>
      </section>
      <section class="card">
        <div class="card-head"><h2>Garanti kartı karekodu</h2></div>
        <?php if ($url !== ''): ?>
          <div style="display:flex;gap:14px;align-items:center">
            <div style="width:120px;flex:none"><?= qr_svg($url, 120, 'Q') ?></div>
            <p class="muted small">Müşteri bu karekodu okutunca garantinin kalan süresini ve talebinin durumunu görür (fiyat ve iletişim bilgisi gösterilmez). Barkod okuyucuyla okutunca bu kayıt açılır.</p>
          </div>
          <p class="small" style="word-break:break-all"><a class="link" href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e(preg_replace('#^https?://#', '', $url)) ?></a></p>
        <?php else: ?>
          <p class="muted small">Sitenin adresi henüz öğrenilmedi; sayfayı tarayıcıdan bir kez açınca karekod oluşur.</p>
        <?php endif; ?>
      </section>
    </div>

    <section class="card" id="talepler">
      <div class="card-head"><h2>Garanti talepleri · tamir geçmişi</h2></div>
      <?php if (!$talepler): ?><p class="muted small">Henüz talep yok.</p><?php endif; ?>
      <?php foreach ($talepler as $t): [$tAd, $tTon] = garanti_talep_durumlari()[$t['durum']] ?? [$t['durum'], 'gray']; $acik = in_array($t['durum'], ['acik', 'tedarikcide'], true); ?>
        <div class="stack" style="gap:6px;padding:10px 0;border-bottom:1px solid var(--line)">
          <div><b><?= e(garanti_no($gid) . '-' . (int) $t['id']) ?></b> <span class="badge tone-<?= e($tTon) ?>"><?= e($tAd) ?></span>
            <?php if ($t['sonuc_tur']): ?> · <?= e(garanti_sonuclari()[$t['sonuc_tur']] ?? $t['sonuc_tur']) ?><?php endif; ?>
            <?= can_see_amounts() && (float) $t['maliyet'] > 0 ? ' · maliyet ' . money($t['maliyet']) : '' ?></div>
          <div><?= e((string) $t['sikayet']) ?></div>
          <small class="muted"><?= e(date_tr((string) $t['created_at'], true)) ?><?= $t['acan'] ? ' · ' . e((string) $t['acan']) : '' ?><?= $t['tedarikci'] ? ' · ' . e((string) $t['tedarikci']) . ($t['gonderim'] ? ' (' . e(date_tr((string) $t['gonderim'])) . ' gönderildi)' : '') : '' ?><?= $t['kapanis'] ? ' · kapandı ' . e(date_tr((string) $t['kapanis'])) : '' ?></small>
          <?php if ($t['sonuc']): ?><small><?= e((string) $t['sonuc']) ?></small><?php endif; ?>
          <div class="btn-row">
            <?php if ($t['supplier_id'] || $t['durum'] === 'acik'): ?><a class="linkish" href="print.php?type=garanti_talep&amp;id=<?= (int) $t['id'] ?>" target="_blank" rel="noopener"><?= icon('print') ?> Tedarikçi formu</a><?php endif; ?>
            <?php if ($t['durum'] === 'tedarikcide' && function_exists('wa_url')): $tTel = (string) scalar('SELECT phone FROM suppliers WHERE id = ?', [(int) $t['supplier_id']]); $wa = wa_url($tTel, garanti_tedarikci_metni($g, $t)); if ($wa !== ''): ?>
              <a class="linkish" href="<?= e($wa) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> WhatsApp'la gönder</a>
            <?php endif; endif; ?>
            <?php if (!$acik): ?>
              <form method="post" class="inline" data-confirm="Talep yeniden açılsın mı?"><?= csrf_field() ?><input type="hidden" name="garanti_id" value="<?= (int) $gid ?>"><input type="hidden" name="talep_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="eylem" value="talep_yeniden"><button class="linkish">Yeniden aç</button></form>
            <?php endif; ?>
            <?php if (is_super()): ?>
              <form method="post" class="inline" data-confirm="Talep silinsin mi?"><?= csrf_field() ?><input type="hidden" name="garanti_id" value="<?= (int) $gid ?>"><input type="hidden" name="talep_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="eylem" value="talep_sil"><button class="linkish danger">Sil</button></form>
            <?php endif; ?>
          </div>
          <?php if ($t['durum'] === 'acik'): ?>
            <form method="post" class="btn-row" style="align-items:flex-end">
              <?= csrf_field() ?><input type="hidden" name="garanti_id" value="<?= (int) $gid ?>"><input type="hidden" name="talep_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="eylem" value="talep_tedarikci">
              <label class="field"><span>Tedarikçi</span><select name="supplier_id"><option value="">Garantinin tedarikçisi</option><?php foreach ($tedarikciler as $s): ?><option value="<?= (int) $s['id'] ?>"<?= (int) $g['supplier_id'] === (int) $s['id'] ? ' selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?></select></label>
              <label class="field"><span>Gönderim</span><input type="date" name="gonderim" value="<?= date('Y-m-d') ?>"></label>
              <button class="btn btn-sm"><?= icon('truck') ?> Tedarikçiye gönderildi</button>
            </form>
          <?php endif; ?>
          <?php if ($acik): ?>
            <form method="post" class="stack" style="gap:8px">
              <?= csrf_field() ?><input type="hidden" name="garanti_id" value="<?= (int) $gid ?>"><input type="hidden" name="talep_id" value="<?= (int) $t['id'] ?>"><input type="hidden" name="eylem" value="talep_kapat">
              <div class="grid cols-2">
                <label class="field"><span>Sonuç</span><select name="durum"><option value="tamamlandi">Tamamlandı</option><option value="reddedildi">Kapsam dışı (reddedildi)</option></select></label>
                <label class="field"><span>Yapılan</span><select name="sonuc_tur"><option value="">—</option><?= select_options(garanti_sonuclari(), null) ?></select></label>
                <label class="field"><span>Açıklama (müşteri görür)</span><input name="sonuc" maxlength="500" placeholder="örn. menteşe değiştirildi"></label>
                <?php if (can_see_amounts()): ?><label class="field"><span>Mağazaya maliyeti</span><input name="maliyet" inputmode="decimal" placeholder="0,00"></label><?php endif; ?>
              </div>
              <div><button class="btn btn-primary btn-sm">Talebi kapat</button></div>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if (!$acikTalep && $d['kod'] === 'gecerli'): ?>
        <form method="post" class="stack" style="gap:8px;margin-top:12px">
          <?= csrf_field() ?><input type="hidden" name="garanti_id" value="<?= (int) $gid ?>"><input type="hidden" name="eylem" value="talep_ekle">
          <label class="field"><span>Yeni garanti talebi — arıza / şikâyet</span><input name="sikayet" maxlength="500" required placeholder="örn. sol menteşe gevşedi, sap kırıldı"></label>
          <div><button class="btn btn-primary btn-sm"><?= icon('plus') ?> Talep aç</button></div>
        </form>
      <?php elseif ($d['kod'] === 'bitti'): ?>
        <p class="muted small" style="margin-top:10px">Garanti süresi dolmuş. Ücretli tamir için <a class="link" href="order-new.php<?= $g['customer_id'] ? '?customer_id=' . (int) $g['customer_id'] : '' ?>">yeni tamir siparişi</a> açın.</p>
      <?php endif; ?>
      <?php if ($tamirler): ?>
        <h3 style="margin-top:14px">Bu müşterinin tamir / bakım siparişleri</h3>
        <div class="table-wrap"><table class="table"><tbody>
          <?php foreach ($tamirler as $o): ?><tr><td><a class="link" href="order.php?id=<?= (int) $o['id'] ?>"><?= e(order_no((int) $o['id'])) ?></a></td><td><?= e((string) ($o['frame_info'] ?: '—')) ?></td><td class="hide-sm"><?= e(date_tr((string) $o['created_at'])) ?></td><td><?= e(stage_label((string) $o['order_stage'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </section>
    <?php
    page_end();
    return;
}

/* ---------------- Liste ---------------- */
$filtreler = ['aktif' => 'Geçerli', 'bitiyor' => '30 günde bitecek', 'talep' => 'Açık talepler', 'tedarikcide' => 'Tedarikçide', 'tumu' => 'Tümü'];
$filtre = query('f', 'aktif');
if (!isset($filtreler[$filtre])) {
    $filtre = 'aktif';
}
$ara = mb_substr(query('q'), 0, 80);
$liste = garanti_ara($ara, $filtre);
$acikSayi = garanti_rozet();

page_start('Garantiler', 'garantiler');
page_header('Garantiler', 'Garanti kayıtları, garanti talepleri ve tedarikçiye gönderilenler', '', '', 'Atölye');
?>
<form method="get" class="btn-row" style="margin-bottom:12px">
  <input type="hidden" name="f" value="<?= e($filtre) ?>">
  <input name="q" value="<?= e($ara) ?>" placeholder="Müşteri, telefon, ürün, seri no, G00012, #00123" style="min-width:260px" aria-label="Ara">
  <button class="btn btn-sm"><?= icon('search') ?> Ara</button>
</form>
<div class="btn-row" style="margin-bottom:14px">
  <?php foreach ($filtreler as $k => $ad): ?><a class="btn btn-sm <?= $k === $filtre ? 'btn-primary' : '' ?>" href="garantiler.php?f=<?= e($k) ?><?= $ara !== '' ? '&amp;q=' . e(rawurlencode($ara)) : '' ?>"><?= e($ad) ?><?= $k === 'talep' && $acikSayi ? ' (' . $acikSayi . ')' : '' ?></a><?php endforeach; ?>
</div>

<section class="card">
  <?php if (!$liste): ?>
    <?= empty_state($ara !== '' ? 'Eşleşen garanti yok' : 'Bu listede garanti yok', 'Garanti, siparişin "Garanti" bölümünden ya da teslimde kendiliğinden açılır.') ?>
  <?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Garanti</th><th>Müşteri</th><th>Ürün</th><th>Bitiş</th><th>Talep</th></tr></thead>
    <tbody><?php foreach ($liste as $g): $d = garanti_durumu($g); $st = $g['son_talep'] ? (garanti_talep_durumlari()[$g['son_talep']] ?? null) : null; ?>
      <tr>
        <td><a class="link" href="garantiler.php?id=<?= (int) $g['id'] ?>"><b><?= e(garanti_no((int) $g['id'])) ?></b></a><small class="block muted"><?= e(garanti_kalemleri()[$g['kalem']] ?? $g['kalem']) ?></small></td>
        <td><?= e(trim((string) $g['first_name'] . ' ' . (string) $g['last_name']) ?: '—') ?><small class="block muted"><?= e(phone_display($g['phone'] ?? '')) ?></small></td>
        <td><?= e((string) $g['urun']) ?><?= $g['seri_no'] ? '<small class="block muted">seri ' . e((string) $g['seri_no']) . '</small>' : '' ?></td>
        <td><?= e(date_tr((string) $g['bitis'])) ?> <span class="badge tone-<?= e($d['ton']) ?>"><?= e($d['kod'] === 'gecerli' ? garanti_kalan_metni($d['kalan_gun']) : $d['etiket']) ?></span></td>
        <td><?= $st && in_array($g['son_talep'], ['acik', 'tedarikcide'], true) ? '<span class="badge tone-' . e($st[1]) . '">' . e($st[0]) . '</span>' : '—' ?></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>

<?php if (is_super()): ?>
<section class="card" id="ayarlar">
  <div class="card-head"><h2><?= icon('settings') ?> Garanti ayarları</h2></div>
  <form method="post" class="stack" style="gap:10px">
    <?= csrf_field() ?><input type="hidden" name="eylem" value="ayar">
    <div class="grid cols-2">
      <?php foreach (garanti_kalemleri() as $k => $ad): ?>
        <label class="field"><span><?= e($ad) ?> garantisi (ay)</span><input name="ay_<?= e($k) ?>" type="number" min="1" max="120" required value="<?= garanti_varsayilan_ay($k) ?>"></label>
      <?php endforeach; ?>
    </div>
    <label class="check"><input type="checkbox" name="otomatik" value="1"<?= setting('garanti_otomatik', '1') === '1' ? ' checked' : '' ?>> Sipariş teslim edilince garantiyi kendiliğinden aç</label>
    <label class="check"><input type="checkbox" name="sure_uzat" value="1"<?= setting('garanti_sure_uzat', '1') === '1' ? ' checked' : '' ?>> Tamamlanan talepte geçen gün sayısını garanti süresine ekle</label>
    <label class="field"><span>Garanti koşulları (kartın altına basılır; her satır bir madde)</span><textarea name="kosullar" rows="5"><?= e(garanti_kosullari()) ?></textarea></label>
    <p class="hint">6502 sayılı Kanun'a göre satıcı ayıplı maldan teslimden itibaren 2 yıl sorumludur; süreyi kısaltmadan önce danışmanınıza sorun.</p>
    <div><button class="btn btn-primary btn-sm">Kaydet</button></div>
  </form>
</section>
<?php endif; ?>
<?php page_end();
