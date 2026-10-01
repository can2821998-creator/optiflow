<?php
declare(strict_types=1);

/* 4.15.0 — Sipariş sayfası: hatalı cam / yeniden yapım kartı. Beklenen: $order, $id */
$chListe = cam_hata_siparis_listesi((int) $id);
$chCamlar = cam_hata_siparis_camlari((int) $id);
$chTedarikciler = rows('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name');
$chPersonel = rows('SELECT id, full_name FROM user_accounts WHERE is_active = 1 ORDER BY full_name');
?>
<section class="card" id="cam-hata">
  <div class="card-head"><h2><?= icon('glasses') ?> Hatalı cam / yeniden yapım</h2><?php if ($chListe): ?><span class="badge tone-amber"><?= count($chListe) ?></span><?php endif; ?></div>
  <?php foreach ($chListe as $h): [$aAd, $aTon] = cam_hata_alacak_durumlari()[$h['alacak_durum']] ?? ['—', 'gray']; ?>
    <div class="stack" style="gap:4px;padding:8px 0;border-bottom:1px solid var(--line)">
      <div><b><?= e(cam_hata_nedenleri()[$h['neden']] ?? $h['neden']) ?></b> · <?= e(['R' => 'Sağ', 'L' => 'Sol', 'cift' => 'Çift'][$h['goz']] ?? '') ?><?= can_see_amounts() && (float) $h['maliyet'] > 0 ? ' · ' . money($h['maliyet']) : '' ?>
        <?php if ($h['alacak_durum'] !== 'yok'): ?> <span class="badge tone-<?= e($aTon) ?>"><?= e($aAd) ?></span><?php endif; ?></div>
      <small class="muted"><?= e(date_tr((string) $h['created_at'], true)) ?><?= $h['tedarikci'] ? ' · ' . e((string) $h['tedarikci']) : '' ?><?= $h['sorumlu'] ? ' · sorumlu ' . e((string) $h['sorumlu']) : '' ?><?= (int) $h['yeniden_yapim'] ? ' · cam yeniden sipariş listesinde' : '' ?></small>
      <?php if ($h['aciklama']): ?><small><?= e((string) $h['aciklama']) ?></small><?php endif; ?>
      <?php if (is_super()): ?>
        <div class="btn-row">
          <?php if ($h['alacak_durum'] === 'bekliyor'): ?>
            <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="cam_hata_alacak"><input type="hidden" name="hata_id" value="<?= (int) $h['id'] ?>"><input type="hidden" name="durum" value="alindi">
              <input name="tutar" inputmode="decimal" style="width:90px" value="<?= e(number_format((float) $h['alacak_tutar'], 2, ',', '.')) ?>" aria-label="İade tutarı"> <button class="btn btn-sm btn-primary">İade alındı</button></form>
            <form method="post" class="inline" data-confirm="Tedarikçi iadeyi reddetti olarak işaretlensin mi?"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="cam_hata_alacak"><input type="hidden" name="hata_id" value="<?= (int) $h['id'] ?>"><input type="hidden" name="durum" value="reddedildi"><button class="linkish">Reddedildi</button></form>
          <?php elseif (in_array($h['alacak_durum'], ['alindi', 'reddedildi'], true)): ?>
            <form method="post" class="inline" data-confirm="İade kaydı geri alınsın mı? Cari kaydı silinir."><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="cam_hata_alacak_geri"><input type="hidden" name="hata_id" value="<?= (int) $h['id'] ?>"><button class="linkish">İadeyi geri al</button></form>
          <?php endif; ?>
          <form method="post" class="inline" data-confirm="Kayıt silinsin mi? (Camların stok durumu değişmez.)"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="cam_hata_sil"><input type="hidden" name="hata_id" value="<?= (int) $h['id'] ?>"><button class="linkish danger">Sil</button></form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <details style="margin-top:10px" <?= $chListe ? '' : 'open' ?>>
    <summary class="linkish"><?= icon('plus') ?> Hatalı cam kaydet</summary>
    <form method="post" class="stack" style="gap:10px;margin-top:10px">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="cam_hata_ekle">
      <label class="field"><span>Sebep *</span><select name="neden" required><option value="">Seçin</option><?= select_options(cam_hata_nedenleri(), null) ?></select></label>
      <div class="grid cols-2">
        <label class="field"><span>Göz</span><select name="goz"><?= select_options(['cift' => 'Çift', 'R' => 'Sağ', 'L' => 'Sol'], 'cift') ?></select></label>
        <label class="field"><span>Maliyet (yeniden yapım)</span><input name="maliyet" inputmode="decimal" placeholder="0,00"></label>
        <label class="field"><span>Tedarikçi / laboratuvar</span><select name="supplier_id"><option value="">Camın tedarikçisi</option><?php foreach ($chTedarikciler as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Sorumlu (mağaza hatasıysa)</span><select name="sorumlu_id"><option value="">—</option><?php foreach ($chPersonel as $p): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['full_name']) ?></option><?php endforeach; ?></select></label>
      </div>
      <?php if ($chCamlar): ?>
        <fieldset class="stack" style="gap:4px;border:0;padding:0;margin:0">
          <legend class="muted small">Yeniden yapılacak camlar (Depo · Stok'ta "Eksik" olur, cam siparişine yeniden girer)</legend>
          <?php foreach ($chCamlar as $cm): ?>
            <label class="check"><input type="checkbox" name="camlar[]" value="<?= (int) $cm['id'] ?>"> <?= e(($cm['eye'] === 'R' ? 'Sağ' : 'Sol') . ' · ' . ($cm['lens_type'] ?: $cm['lens_label'])) ?> <?= stock_badge((string) $cm['stock_status']) ?></label>
          <?php endforeach; ?>
        </fieldset>
      <?php endif; ?>
      <label class="check"><input type="checkbox" name="alacak" value="1"> Laboratuvar hatası: tedarikçiden iade (alacak) bekleniyor</label>
      <label class="field"><span>Açıklama</span><input name="aciklama" maxlength="500" placeholder="örn. sağ cam aks 90 yerine 180 gelmiş"></label>
      <div><button class="btn btn-primary btn-sm">Kaydet</button></div>
    </form>
  </details>
</section>
