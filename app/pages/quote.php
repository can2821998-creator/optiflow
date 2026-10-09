<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

$id = query_int('id');
$quote = row('SELECT q.*, u.full_name AS created_by_name, uu.full_name AS updated_by_name FROM quotes q
              LEFT JOIN user_accounts u ON u.id = q.created_by LEFT JOIN user_accounts uu ON uu.id = q.updated_by WHERE q.id = ?', [$id]);
if (!$quote) {
    http_response_code(404);
    render_error_page('Teklif bulunamadı', 'Silinmiş veya hatalı bir bağlantı olabilir.');
}

$katalog = ($quote['tip'] ?? 'serbest') === 'katalog';   // 4.21.0 katalogdan teklif (app/teklif.php)
$donmus = teklif_siparise_donmus($quote);                // 4.26.0: siparişe dönmüş teklif düzenlenmez

if (is_post() && post('action') === 'delete' && is_super()) {
    q('DELETE FROM quote_gozlukler WHERE quote_id = ?', [$id]);
    q('DELETE FROM quotes WHERE id = ?', [$id]);
    flash('Teklif silindi.', 'info');
    redirect('quotes.php');
}
// 4.26.0: serbest metinli teklifi düzenleme (katalog teklifi teklif-yeni.php?id=… ile düzenlenir)
if (is_post() && post('action') === 'duzenle_serbest' && !$katalog && !$donmus) {
    $name = mb_substr(trim(post('customer_name')), 0, 160);
    $veri = ['customer_name' => $name, 'customer_phone' => mb_substr(post('customer_phone'), 0, 20) ?: null, 'note' => mb_substr(post('note'), 0, 255) ?: null,
        'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user['id']];
    $secenekVar = false;
    $hatali = false;
    foreach ([1, 2, 3] as $i) {
        $oname = mb_substr(trim(post("opt{$i}_name")), 0, 60);
        $fiyatHam = trim(post("opt{$i}_price"));
        $fiyat = $fiyatHam !== '' ? parse_money($fiyatHam) : null;
        $hatali = $hatali || ($fiyatHam !== '' && ($fiyat === null || $fiyat < 0));
        $veri["opt{$i}_name"] = $oname ?: null;
        $veri["opt{$i}_desc"] = mb_substr(trim(post("opt{$i}_desc")), 0, 500) ?: null;
        $veri["opt{$i}_price"] = $fiyat;
        $secenekVar = $secenekVar || $oname !== '';
    }
    if ($name === '' || !$secenekVar || $hatali) {
        flash($name === '' ? 'Müşteri adı zorunlu.' : (!$secenekVar ? 'En az bir seçenek girin.' : 'Fiyat geçersiz. Örnek: 4.250,00'), 'error');
    } else {
        update('quotes', $veri, 'id = ?', [$id]);
        audit('quote_update', 'quote', $id, ['müşteri' => $name]);
        flash('Teklif güncellendi.');
    }
    redirect('quote.php?id=' . $id);
}

$gozlukler = $katalog ? teklif_gozlukleri($quote) : [];
$cokGozluk = count($gozlukler) > 1;
$options = teklif_secenekleri($quote);
$waPhone = $quote['customer_phone'] ? normalize_phone($quote['customer_phone']) : '';
$waText = $waPhone ? teklif_whatsapp_metni($quote, setting('shop_name', 'OptiFlow')) : '';
$oranMetni = static fn(float $o): string => rtrim(rtrim(number_format($o, 2, ',', ''), '0'), ',');
$tlMetni = static fn($x): string => $x === null ? '' : number_format((float) $x, 2, ',', '.');

page_start('Teklif · ' . $quote['customer_name'], 'quotes');
?>
<a class="back-link" href="quotes.php"><?= icon('arrow-left') ?> Teklifler</a>

<section class="hero">
  <div class="hero-main">
    <span class="avatar lg"><?= e(mb_substr($quote['customer_name'], 0, 2)) ?></span>
    <div>
      <small class="eyebrow">Teklif #<?= $id ?> · <?= date_tr($quote['created_at'], true) ?><?= $quote['created_by_name'] ? ' · ' . e($quote['created_by_name']) : '' ?><?= !empty($quote['updated_at']) ? ' · düzenlendi ' . date_tr($quote['updated_at'], true) . ($quote['updated_by_name'] ? ' (' . e($quote['updated_by_name']) . ')' : '') : '' ?></small>
      <h1><?php if ($quote['customer_id']): ?><a href="customer.php?id=<?= (int) $quote['customer_id'] ?>"><?= e($quote['customer_name']) ?></a><?php else: ?><?= e($quote['customer_name']) ?><?php endif; ?></h1>
      <p><?= $quote['customer_phone'] ? e(phone_display($quote['customer_phone'])) : 'Telefon girilmemiş' ?><?= $cokGozluk ? ' · ' . count($gozlukler) . ' gözlük' : '' ?><?= $donmus ? ' · <span class="badge sm tone-green">Siparişe döndü</span>' : '' ?></p>
    </div>
  </div>
  <div class="hero-actions">
    <?php if (!$donmus): ?>
      <?php if ($katalog): ?>
        <a class="btn" href="teklif-yeni.php?id=<?= $id ?>"><?= icon('edit') ?> Düzenle</a>
      <?php else: ?>
        <a class="btn" href="quote.php?id=<?= $id ?>&amp;duzenle=1#duzenle"><?= icon('edit') ?> Düzenle</a>
      <?php endif; ?>
    <?php endif; ?>
    <a class="btn" href="print.php?type=quote&id=<?= $id ?>" target="_blank"><?= icon('print') ?> Döküm / PDF</a>
    <?php if ($waPhone): ?>
      <a class="btn btn-wa" href="https://wa.me/<?= e($waPhone) ?>?text=<?= rawurlencode($waText) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> WhatsApp ile gönder</a>
    <?php endif; ?>
    <?php if ($quote['converted_order_id'] && !$cokGozluk): ?>
      <a class="btn btn-ghost" href="order.php?id=<?= (int) $quote['converted_order_id'] ?>">Siparişi görüntüle</a>
    <?php elseif (!$katalog && !$quote['converted_order_id']): ?>
      <a class="btn btn-primary" href="order-new.php?quote_id=<?= $id ?>"><?= icon('plus') ?> Siparişe çevir</a>
    <?php endif; ?>
  </div>
</section>

<?php if ($quote['note']): ?><p class="muted"><?= e($quote['note']) ?></p><?php endif; ?>

<?php if ($katalog): ?>
  <?php if ((float) $quote['discount_rate'] > 0 || $cokGozluk): ?>
    <section class="card">
      <?php if ($cokGozluk): [$az, $cok] = teklif_toplam_aralik($gozlukler); ?>
        <div class="teklif-toplam">
          <span><span class="muted">Toplam · <?= count($gozlukler) ?> gözlük</span><br><small class="muted">her gözlüğün en uygun – en kapsamlı seçeneğiyle</small></span>
          <b><?= $az === $cok ? money($az) : money($az) . ' – ' . money($cok) ?></b>
        </div>
      <?php endif; ?>
      <?php if ((float) $quote['discount_rate'] > 0): ?>
        <p class="muted" style="margin:<?= $cokGozluk ? '10px' : '0' ?> 0 0">İskonto: <b>%<?= e($oranMetni((float) $quote['discount_rate'])) ?></b> (her gözlükte SGK payı düşüldükten sonra)</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php foreach ($gozlukler as $gz): $secilen = (int) ($gz['secilen'] ?? 0); ?>
    <div class="teklif-gozluk">
      <?php if ($cokGozluk || !empty($quote['gozluk_ad'])): ?>
        <h2><span class="gozluk-rozet" aria-hidden="true"><?= icon('glasses') ?></span><?= e($gz['ad']) ?>
          <?php if ($gz['converted_order_id']): ?><a class="badge sm tone-green" href="order.php?id=<?= (int) $gz['converted_order_id'] ?>">Siparişe döndü · <?= e(order_no((int) $gz['converted_order_id'])) ?></a><?php endif; ?></h2>
      <?php endif; ?>
      <section class="card">
        <ul class="kv">
          <li><span class="muted">Çerçeve</span><b><?= e((string) $gz['frame_desc']) ?><?= (float) $gz['frame_price'] > 0 ? ' — ' . money($gz['frame_price']) : '' ?></b></li>
          <li><span class="muted">Kullanım şekli</span><b><?= e(lens_designs()[(string) $gz['lens_design']] ?? '—') ?></b></li>
          <li><span class="muted">Medula (SGK) payı</span><b><?= (float) $gz['sgk_amount'] > 0 ? money($gz['sgk_amount']) . ' <small class="muted">(tahmini)</small>' : 'SGK\'sız' ?></b></li>
          <?php if (!$cokGozluk): ?>
            <li><span class="muted">İskonto</span><b><?= (float) $quote['discount_rate'] > 0 ? '%' . e($oranMetni((float) $quote['discount_rate'])) . ' <small class="muted">(SGK payı düşüldükten sonra)</small>' : 'Yok' ?></b></li>
          <?php endif; ?>
        </ul>
      </section>
      <div class="teklif-karsilastir">
        <?php foreach ($gz['secenekler'] as $o): $h = $o['hesap']; ?>
          <section class="card teklif-kart <?= $secilen === $o['no'] ? 'is-secilen' : '' ?>">
            <h3><?= e($o['baslik']) ?><?php if ($secilen === $o['no']): ?> <span class="badge sm tone-green">Seçildi</span><?php endif; ?></h3>
            <span class="cam"><?= e($o['cam']) ?></span>
            <?php if ($o['ozellik'] !== ''): ?><small class="muted"><?= e($o['ozellik']) ?></small><?php endif; ?><?php if ($o['not'] !== ''): ?><small class="muted"><i><?= e($o['not']) ?></i></small><?php endif; ?>
            <dl>
              <dt>Cam (çift)</dt><dd><?= money($h['cam']) ?></dd>
              <dt>Çerçeve</dt><dd><?= money($h['cerceve']) ?></dd>
              <dt>Ara toplam</dt><dd><?= money($h['ara']) ?></dd>
              <?php if ($h['sgk'] > 0): ?><dt>Medula (SGK) payı</dt><dd>−<?= money($h['sgk']) ?></dd><dt>Kalan</dt><dd><?= money($h['kalan']) ?></dd><?php endif; ?>
              <?php if ($h['iskonto'] > 0): ?><dt>İskonto %<?= e($oranMetni($h['oran'])) ?></dt><dd>−<?= money($h['iskonto']) ?></dd><?php endif; ?>
            </dl>
            <div class="odenecek"><span class="muted">Ödenecek</span><b><?= money($h['odenecek']) ?></b></div>
            <?php if (!$gz['converted_order_id']): ?>
              <a class="btn btn-primary btn-block" href="order-new.php?quote_id=<?= $id ?>&amp;secenek=<?= $o['no'] ?><?= $gz['sira'] > 1 ? '&amp;gozluk=' . (int) $gz['sira'] : '' ?>"><?= icon('plus') ?> Bu seçenekle siparişe çevir</a>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
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
  <?php if (!$donmus): ?>
    <details class="card" id="duzenle"<?= query('duzenle') === '1' ? ' open' : '' ?>>
      <summary class="card-head" style="cursor:pointer"><h2><?= icon('edit') ?> Teklifi düzenle</h2></summary>
      <form method="post" class="stack" style="margin-top:16px">
        <?= csrf_field() ?><input type="hidden" name="action" value="duzenle_serbest">
        <div class="grid cols-3">
          <label class="field"><span>Müşteri adı *</span><input name="customer_name" required value="<?= e($quote['customer_name']) ?>"></label>
          <label class="field"><span>Telefon</span><input name="customer_phone" type="tel" value="<?= e((string) $quote['customer_phone']) ?>"></label>
          <label class="field"><span>Not</span><input name="note" value="<?= e((string) $quote['note']) ?>"></label>
        </div>
        <div class="quote-options">
          <?php foreach ([1, 2, 3] as $n): ?>
            <div class="quote-option-edit">
              <label class="field"><span>Seçenek <?= $n ?> adı</span><input name="opt<?= $n ?>_name" value="<?= e((string) $quote["opt{$n}_name"]) ?>"></label>
              <label class="field"><span>Açıklama</span><textarea name="opt<?= $n ?>_desc" rows="3"><?= e((string) $quote["opt{$n}_desc"]) ?></textarea></label>
              <label class="field"><span>Fiyat</span><input name="opt<?= $n ?>_price" inputmode="decimal" value="<?= e($tlMetni($quote["opt{$n}_price"])) ?>" placeholder="0,00"></label>
            </div>
          <?php endforeach; ?>
        </div>
        <button class="btn btn-primary"><?= icon('check') ?> Değişiklikleri kaydet</button>
      </form>
    </details>
  <?php endif; ?>
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
