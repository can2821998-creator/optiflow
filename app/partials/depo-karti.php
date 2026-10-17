<?php
declare(strict_types=1);

/* Gün sonu "depoya WhatsApp" kartı (app/kagitsiz.php). Ana ekranda (özellik kagitsiz) ve 4.38.1 itibarıyla
   "Cam siparişleri · depo" › Eksik sekmesinde. Gönderim bir cam sipariş fişi olarak kaydedilir (Sipariş fişleri sekmesi).
   Gereken: $depoGruplar, $depoToplam, $depoTedarikci, $depoTedarikciler, $depoSon; isteğe bağlı $depoGeri (geri dönüş: ana | stok). */
$depoGeri = $depoGeri ?? 'ana';
?>
<?php if ($depoToplam > 0): ?>
<section class="card depo-kart">
  <div class="depo-ust">
    <div>
      <span class="depo-etiket">Gün sonu</span>
      <h2>Depoya gidecek <?= $depoToplam ?> eksik cam</h2>
      <p class="muted small">Türe ve numaraya göre gruplandı; depoya müşteri bilgisi gitmez. Gönderince camlar "Depoya sipariş verildi" olur.</p>
    </div>
    <form method="post" action="stock.php" target="_blank" class="depo-gonder" data-sonra-yenile>
      <?= csrf_field() ?><input type="hidden" name="eylem" value="depo_whatsapp"><input type="hidden" name="geri" value="<?= e($depoGeri) ?>">
      <?php if ($depoTedarikciler): ?>
        <label class="field"><span>Depo</span><select name="supplier_id"><option value="">WhatsApp'ta kişi seçeceğim</option><?php foreach ($depoTedarikciler as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
      <?php endif; ?>
      <button class="btn btn-primary"><?= icon('chat') ?> WhatsApp'ta depoya gönder</button>
      <?php if ($depoTedarikci): ?><small class="muted"><?= e($depoTedarikci['name']) ?> · <?= e(phone_display($depoTedarikci['phone'])) ?></small><?php endif; ?>
    </form>
  </div>
  <div class="depo-liste">
    <?php foreach ($depoGruplar as $g): ?>
      <div class="depo-grup"><b><?= e($g['tur']) ?> <span>(<?= (int) $g['adet'] ?>)</span></b>
        <ul><?php foreach ($g['satirlar'] as $r): ?><li><code><?= e($r['numara']) ?></code><?= $r['adet'] > 1 ? ' <span>× ' . (int) $r['adet'] . '</span>' : '' ?></li><?php endforeach; ?></ul>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($depoGeri === 'ana'): ?><a class="link small" href="stock.php">Eksik cam listesini aç →</a><?php endif; ?>
</section>
<?php endif; ?>
<?php if ($depoSon): ?>
<form method="post" action="stock.php" class="depo-son" data-confirm="Son depo gönderimi geri alınsın mı? Camlar yeniden &quot;Eksik&quot; olur (WhatsApp mesajı geri alınmaz).">
  <?= csrf_field() ?><input type="hidden" name="eylem" value="depo_geri"><input type="hidden" name="geri" value="<?= e($depoGeri) ?>">
  <span><?= icon('check') ?> Son depo gönderimi: <?= e(date('H:i', (int) $depoSon['at'])) ?> · <?= count((array) $depoSon['ids']) ?> cam<?= ($depoSon['tedarikci'] ?? '') !== '' ? ' · ' . e((string) $depoSon['tedarikci']) : '' ?></span>
  <button class="btn btn-sm btn-ghost">Geri al</button>
</form>
<?php endif; ?>
