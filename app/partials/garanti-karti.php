<?php
declare(strict_types=1);

/* 4.16.0 — Sipariş sayfası: garanti kartı bölümü. Beklenen: $order, $id */
$gListe = garanti_siparis_listesi((int) $id);
$gOneri = garanti_siparis_onerileri((int) $id);
$gTeslim = $order['order_stage'] === 'teslim_edildi';
$gTedarikciler = rows('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name');
?>
<section class="card" id="garanti">
  <div class="card-head"><h2><?= icon('shield') ?> Garanti</h2><?php if ($gListe): ?><a class="link" href="print.php?type=garanti&amp;order=<?= (int) $id ?>" target="_blank" rel="noopener"><?= icon('print') ?> Garanti kartı</a><?php endif; ?></div>
  <?php foreach ($gListe as $g): $gd = garanti_durumu($g); ?>
    <div class="stack" style="gap:2px;padding:8px 0;border-bottom:1px solid var(--line)">
      <div><a class="link" href="garantiler.php?id=<?= (int) $g['id'] ?>"><b><?= e(garanti_no((int) $g['id'])) ?></b></a> · <?= e(garanti_kalemleri()[$g['kalem']] ?? $g['kalem']) ?> · <?= e((string) $g['urun']) ?>
        <span class="badge tone-<?= e($gd['ton']) ?>"><?= e($gd['etiket']) ?></span>
        <?php if ((int) $g['acik_talep']): ?><span class="badge tone-amber">Açık talep</span><?php endif; ?></div>
      <small class="muted"><?= e(date_tr((string) $g['baslangic'])) ?> – <?= e(date_tr((string) $g['bitis'])) ?><?= $gd['kod'] === 'gecerli' ? ' · ' . e(garanti_kalan_metni($gd['kalan_gun'])) . ' kaldı' : '' ?><?= $g['seri_no'] ? ' · seri ' . e((string) $g['seri_no']) : '' ?><?= (int) $g['talep_sayisi'] ? ' · ' . (int) $g['talep_sayisi'] . ' talep' : '' ?></small>
    </div>
  <?php endforeach; ?>
  <?php if (!$gListe && !$gTeslim): ?>
    <p class="muted small">Garanti, sipariş teslim edilince <?= setting('garanti_otomatik', '1') === '1' ? 'kendiliğinden açılır' : 'buradan açılır' ?>. Şimdi açmak isterseniz aşağıdan ekleyin.</p>
  <?php endif; ?>
  <?php if ($gOneri): ?>
    <form method="post" class="inline" style="margin-top:10px">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="garanti_olustur">
      <button class="btn btn-sm <?= $gTeslim ? 'btn-primary' : '' ?>"><?= icon('shield') ?> Garanti aç: <?= e(implode(', ', array_map(static fn(array $k): string => garanti_kalemleri()[$k['kalem']] . ' (' . garanti_varsayilan_ay($k['kalem']) . ' ay)', $gOneri))) ?></button>
    </form>
  <?php endif; ?>
  <details style="margin-top:10px">
    <summary class="linkish"><?= icon('plus') ?> Başka kalem / farklı süre</summary>
    <form method="post" class="stack" style="gap:10px;margin-top:10px">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="garanti_ekle">
      <div class="grid cols-2">
        <label class="field"><span>Kalem *</span><select name="kalem" required><?= select_options(garanti_kalemleri(), $order['transaction_type'] === 'gunes_gozlugu' ? 'gunes' : 'cerceve') ?></select></label>
        <label class="field"><span>Ürün *</span><input name="urun" maxlength="160" required value="<?= e((string) ($gOneri[0]['urun'] ?? $order['frame_info'] ?? '')) ?>" placeholder="marka, model, renk"></label>
        <label class="field"><span>Seri no</span><input name="seri_no" maxlength="80" placeholder="çerçevenin içindeki numara"></label>
        <label class="field"><span>Süre (ay)</span><input name="ay" type="number" min="1" max="120" placeholder="<?= garanti_varsayilan_ay('cerceve') ?>"></label>
        <label class="field"><span>Başlangıç</span><input name="baslangic" type="date" value="<?= e($order['delivered_at'] ? substr((string) $order['delivered_at'], 0, 10) : date('Y-m-d')) ?>"></label>
        <label class="field"><span>Tedarikçi</span><select name="supplier_id"><option value="">—</option><?php foreach ($gTedarikciler as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
      </div>
      <div><button class="btn btn-primary btn-sm">Garanti aç</button></div>
    </form>
  </details>
</section>
