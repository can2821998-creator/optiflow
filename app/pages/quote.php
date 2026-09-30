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

$options = [];
foreach ([1, 2, 3] as $i) {
    if ($quote["opt{$i}_name"]) {
        $options[] = ['name' => $quote["opt{$i}_name"], 'desc' => $quote["opt{$i}_desc"], 'price' => $quote["opt{$i}_price"]];
    }
}

$waPhone = $quote['customer_phone'] ? normalize_phone($quote['customer_phone']) : '';
$waText = '';
if ($waPhone) {
    $waText = 'Merhaba ' . $quote['customer_name'] . ", talep ettiğiniz fiyat teklifimiz:\n\n";
    foreach ($options as $o) {
        $waText .= '• ' . $o['name'] . ($o['price'] !== null ? ' — ' . number_format((float) $o['price'], 2, ',', '.') . ' ₺' : '') . ($o['desc'] ? "\n  " . $o['desc'] : '') . "\n";
    }
    $waText .= "\n" . setting('shop_name', 'OptiFlow');
}

page_start('Teklif · ' . $quote['customer_name'], 'quotes');
?>
<a class="back-link" href="quotes.php"><?= icon('arrow-left') ?> Teklifler</a>

<section class="hero">
  <div class="hero-main">
    <span class="avatar lg"><?= e(mb_substr($quote['customer_name'], 0, 2)) ?></span>
    <div>
      <small class="eyebrow">Teklif · <?= date_tr($quote['created_at'], true) ?><?= $quote['created_by_name'] ? ' · ' . e($quote['created_by_name']) : '' ?></small>
      <h1><?= e($quote['customer_name']) ?></h1>
      <p><?= $quote['customer_phone'] ? e(phone_display($quote['customer_phone'])) : 'Telefon girilmemiş' ?><?= $quote['converted_order_id'] ? ' · <span class="badge sm tone-green">Siparişe döndü</span>' : '' ?></p>
    </div>
  </div>
  <div class="hero-actions">
    <a class="btn" href="print.php?type=quote&id=<?= $id ?>" target="_blank"><?= icon('print') ?> Yazdır / PDF</a>
    <?php if ($waPhone): ?>
      <a class="btn btn-wa" href="https://wa.me/<?= e($waPhone) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> WhatsApp ile gönder</a>
    <?php endif; ?>
    <?php if (!$quote['converted_order_id']): ?>
      <a class="btn btn-primary" href="order-new.php?quote_id=<?= $id ?>"><?= icon('plus') ?> Siparişe çevir</a>
    <?php else: ?>
      <a class="btn btn-ghost" href="order.php?id=<?= (int) $quote['converted_order_id'] ?>">Siparişi görüntüle</a>
    <?php endif; ?>
  </div>
</section>

<?php if ($quote['note']): ?><p class="muted"><?= e($quote['note']) ?></p><?php endif; ?>

<div class="quote-options quote-options-view">
  <?php foreach ($options as $i => $o): ?>
    <section class="card quote-option-card <?= $i === count($options) - 1 && count($options) > 1 ? 'is-best' : '' ?>">
      <div class="card-head"><h2><?= e($o['name']) ?></h2></div>
      <?php if ($o['price'] !== null): ?><div class="quote-price"><?= money($o['price']) ?></div><?php endif; ?>
      <?php if ($o['desc']): ?><p><?= nl2br(e($o['desc'])) ?></p><?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>

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
