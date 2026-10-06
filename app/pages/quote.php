<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

$id = query_int('id');
$quote = row('SELECT q.*, u.full_name AS created_by_name FROM quotes q LEFT JOIN user_accounts u ON u.id = q.created_by WHERE q.id = ?', [$id]);
if (!$quote) {
    http_response_code(404);
    render_error_page('Teklif bulunamadı', 'Silinmiş veya hatalı bir bağlantı olabilir.');
}

if (is_post() && post('action') === 'delete' && is_super()) {
    q('DELETE FROM quotes WHERE id = ?', [$id]);
    flash('Teklif silindi.', 'info');
    redirect('quotes.php');
}

$katalog = ($quote['tip'] ?? 'serbest') === 'katalog';   // 4.21.0 katalogdan teklif (app/teklif.php)
$options = teklif_secenekleri($quote);
$secilen = (int) ($quote['secilen'] ?? 0);
$waPhone = $quote['customer_phone'] ? normalize_phone($quote['customer_phone']) : '';
$waText = $waPhone ? teklif_whatsapp_metni($quote, setting('shop_name', 'OptiFlow')) : '';
$oranMetni = static fn(float $o): string => rtrim(rtrim(number_format($o, 2, ',', ''), '0'), ',');

page_start('Teklif · ' . $quote['customer_name'], 'quotes');
?>
<a class="back-link" href="quotes.php"><?= icon('arrow-left') ?> Teklifler</a>

<section class="hero">
  <div class="hero-main">
    <span class="avatar lg"><?= e(mb_substr($quote['customer_name'], 0, 2)) ?></span>
    <div>
      <small class="eyebrow">Teklif #<?= $id ?> · <?= date_tr($quote['created_at'], true) ?><?= $quote['created_by_name'] ? ' · ' . e($quote['created_by_name']) : '' ?></small>
      <h1><?php if ($quote['customer_id']): ?><a href="customer.php?id=<?= (int) $quote['customer_id'] ?>"><?= e($quote['customer_name']) ?></a><?php else: ?><?= e($quote['customer_name']) ?><?php endif; ?></h1>
      <p><?= $quote['customer_phone'] ? e(phone_display($quote['customer_phone'])) : 'Telefon girilmemiş' ?><?= $quote['converted_order_id'] ? ' · <span class="badge sm tone-green">Siparişe döndü</span>' : '' ?></p>
    </div>
  </div>
  <div class="hero-actions">
    <a class="btn" href="print.php?type=quote&id=<?= $id ?>" target="_blank"><?= icon('print') ?> Döküm / PDF</a>
    <?php if ($waPhone): ?>
      <a class="btn btn-wa" href="https://wa.me/<?= e($waPhone) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> WhatsApp ile gönder</a>
    <?php endif; ?>
    <?php if ($quote['converted_order_id']): ?>
      <a class="btn btn-ghost" href="order.php?id=<?= (int) $quote['converted_order_id'] ?>">Siparişi görüntüle</a>
    <?php elseif (!$katalog): ?>
      <a class="btn btn-primary" href="order-new.php?quote_id=<?= $id ?>"><?= icon('plus') ?> Siparişe çevir</a>
    <?php endif; ?>
  </div>
</section>

<?php if ($quote['note']): ?><p class="muted"><?= e($quote['note']) ?></p><?php endif; ?>

<?php if ($katalog): ?>
  <section class="card">
    <ul class="kv">
      <li><span class="muted">Çerçeve</span><b><?= e((string) $quote['frame_desc']) ?><?= (float) $quote['frame_price'] > 0 ? ' — ' . money($quote['frame_price']) : '' ?></b></li>
      <li><span class="muted">Kullanım şekli</span><b><?= e(lens_designs()[(string) $quote['lens_design']] ?? '—') ?></b></li>
      <li><span class="muted">Medula (SGK) payı</span><b><?= (float) $quote['sgk_amount'] > 0 ? money($quote['sgk_amount']) . ' <small class="muted">(tahmini)</small>' : 'SGK\'sız' ?></b></li>
      <li><span class="muted">İskonto</span><b><?= (float) $quote['discount_rate'] > 0 ? '%' . e($oranMetni((float) $quote['discount_rate'])) . ' <small class="muted">(SGK payı düşüldükten sonra)</small>' : 'Yok' ?></b></li>
    </ul>
  </section>

  <div class="teklif-karsilastir">
    <?php foreach ($options as $o): $h = $o['hesap']; ?>
      <section class="card teklif-kart <?= $secilen === $o['no'] ? 'is-secilen' : '' ?>">
        <h3><?= e($o['baslik']) ?><?php if ($secilen === $o['no']): ?> <span class="badge sm tone-green">Seçildi</span><?php endif; ?></h3>
        <span class="cam"><?= e($o['cam']) ?></span>
        <?php if ($o['ozellik'] !== ''): ?><small class="muted"><?= e($o['ozellik']) ?></small><?php endif; ?>
        <dl>
          <dt>Cam (çift)</dt><dd><?= money($h['cam']) ?></dd>
          <dt>Çerçeve</dt><dd><?= money($h['cerceve']) ?></dd>
          <dt>Ara toplam</dt><dd><?= money($h['ara']) ?></dd>
          <?php if ($h['sgk'] > 0): ?><dt>Medula (SGK) payı</dt><dd>−<?= money($h['sgk']) ?></dd><dt>Kalan</dt><dd><?= money($h['kalan']) ?></dd><?php endif; ?>
          <?php if ($h['iskonto'] > 0): ?><dt>İskonto %<?= e($oranMetni($h['oran'])) ?></dt><dd>−<?= money($h['iskonto']) ?></dd><?php endif; ?>
        </dl>
        <div class="odenecek"><span class="muted">Ödenecek</span><b><?= money($h['odenecek']) ?></b></div>
        <?php if (!$quote['converted_order_id']): ?>
          <a class="btn btn-primary btn-block" href="order-new.php?quote_id=<?= $id ?>&amp;secenek=<?= $o['no'] ?>"><?= icon('plus') ?> Bu seçenekle siparişe çevir</a>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="quote-options quote-options-view">
    <?php foreach ($options as $i => $o): ?>
      <section class="card quote-option-card <?= $i === count($options) - 1 && count($options) > 1 ? 'is-best' : '' ?>">
        <div class="card-head"><h2><?= e($o['baslik']) ?></h2></div>
        <?php if ($o['fiyat'] !== null): ?><div class="quote-price"><?= money($o['fiyat']) ?></div><?php endif; ?>
        <?php if ($o['ozellik'] !== ''): ?><p><?= nl2br(e($o['ozellik'])) ?></p><?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (is_super()): ?>
<details class="card danger-zone">
  <summary class="card-head" style="cursor:pointer"><h2><?= icon('trash') ?> Tehlikeli bölge</h2></summary>
  <form method="post" class="stack" data-confirm="Bu teklif silinsin mi?" style="margin-top:10px">
    <?= csrf_field() ?><input type="hidden" name="action" value="delete">
    <button class="btn btn-ghost danger">Teklifi sil</button>
  </form>
</details>
<?php endif; ?>
<?php page_end();
