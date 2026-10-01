<?php
declare(strict_types=1);

/* 4.15.0 — Sipariş sayfası: SGK hak durumu kartı. Beklenen: $order, $id */
$hd = sgk_hak_durumu((int) $order['customer_id'], (int) $id);
[$hdAd, $hdTon] = sgk_hak_etiketi($hd);
?>
<section class="card" id="sgk-hak">
  <div class="card-head"><h2><?= icon('download') ?> SGK hakkı</h2><span class="badge tone-<?= e($hdTon) ?>"><?= e($hdAd) ?></span></div>
  <p class="small" style="margin-top:0"><?= e($hd['mesaj']) ?></p>
  <ul class="kv">
    <?php if ($hd['son']): ?><li><span>Son SGK'lı alım</span><b><?= e(date_tr($hd['son'])) ?><?= $hd['siparis_id'] && $hd['kaynak'] === 'optiflow' ? ' · <a class="link" href="order.php?id=' . (int) $hd['siparis_id'] . '">' . e(order_no((int) $hd['siparis_id'])) . '</a>' : '' ?></b></li><?php endif; ?>
    <?php if ($hd['hak_tarihi']): ?><li><span>Hak tarihi</span><b><?= e(date_tr($hd['hak_tarihi'])) ?></b></li><?php endif; ?>
    <li><span>Kural</span><b><?= (int) $hd['ay'] ?> ayda bir<?= $hd['yas'] !== null ? ' · ' . (int) $hd['yas'] . ' yaş' : ' · yaş bilinmiyor' ?></b></li>
    <li><span>Kaynak</span><b><?= $hd['kaynak'] === 'medula' ? 'Medula / e-Devlet (' . e(date_tr((string) $hd['medula']['created_at'])) . ')' : ($hd['kaynak'] === 'optiflow' ? 'OptiFlow kaydı' : '—') ?></b></li>
  </ul>
  <details style="margin-top:8px">
    <summary class="linkish">Medula'dan doğrula</summary>
    <p class="hint">OptiFlow Pro'da Medula'nın hak sorgulama / cam-çerçeve geçmişi ekranını açıp <b>Aktar</b>'a basın; ya da ekranı (veya e-Devlet "Medula Optik Cam ve Çerçeve Bilgisi" sonucunu) kopyalayıp buraya yapıştırın.</p>
    <form method="post" class="stack" style="gap:8px">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="sgk_hak_yapistir">
      <textarea name="metin" rows="4" maxlength="100000" placeholder="Medula / e-Devlet ekranını buraya yapıştırın" required></textarea>
      <div><button class="btn btn-sm">Oku ve kaydet</button></div>
    </form>
    <form method="post" class="grid cols-2" style="margin-top:10px;align-items:end">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="sgk_hak_elle">
      <label class="field"><span>ya da son alım tarihini yazın</span><input type="date" name="son_alim" max="<?= date('Y-m-d') ?>" required></label>
      <div class="form-actions"><button class="btn btn-sm">Kaydet</button></div>
    </form>
  </details>
</section>
