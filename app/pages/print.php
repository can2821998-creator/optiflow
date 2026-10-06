<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$type = query('type');
$shop = setting('shop_name', 'OptiFlow');
$title = '';
ob_start();

if ($type === 'order') {
    $o = find_order(query_int('id'));
    if (!$o) { render_error_page('Sipariş bulunamadı', ''); }
    $rx = row('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph FROM prescription_records r LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.order_id = ? ORDER BY r.prescription_date DESC, r.id DESC LIMIT 1', [$o['id']]);
    $payments = can_see_amounts() ? rows('SELECT * FROM payments WHERE order_id = ? ORDER BY created_at', [$o['id']]) : [];
    $isDelivered = $o['order_stage'] === 'teslim_edildi';
    $isCancelled = $o['order_stage'] === 'iptal';
    $hasDebt = $isDelivered && can_see_amounts() && (float) $o['balance'] > 0.009;
    $custName = trim($o['c_first'] . ' ' . $o['c_last']);

    // Durum şeridi: senaryoya göre ikon, renk, başlık ve kurumsal dilde açıklama.
    if ($isCancelled) {
        $banner = ['x', 'tone-gray', 'Sipariş İptal Edilmiştir', 'İlgili sipariş kaydı iptal edilmiş olup herhangi bir işlem gerektirmemektedir.'];
    } elseif ($hasDebt) {
        $banner = ['wallet', 'tone-amber', 'Teslim Edilmiştir · Bakiye Mevcuttur', 'Ürün tarafınıza teslim edilmiştir. Bakiyenin ' . ($o['balance_promise_date'] ? date_tr($o['balance_promise_date']) . ' tarihine kadar' : 'en kısa sürede') . ' tarafımıza ödenmesini rica ederiz.'];
    } elseif ($isDelivered) {
        $banner = ['check', 'tone-green', 'Teslim Edilmiştir', 'Ürün eksiksiz olarak tarafınıza teslim edilmiştir. Bizi tercih ettiğiniz için teşekkür ederiz.'];
    } elseif ($o['order_stage'] === 'hazirlandi') {
        $banner = ['glasses', 'tone-gold', 'Hazırlanmıştır', 'Siparişiniz hazırlanmış olup mağazamızdan teslim alınabilir durumdadır.'];
    } elseif ($o['order_stage'] === 'atolyede') {
        $banner = ['settings', 'tone-teal', 'Atölyede Hazırlanmaktadır', 'Siparişiniz atölyemizde özenle hazırlanmaktadır.'];
    } else {
        $banner = ['glasses', 'tone-wine', 'Sipariş Alınmıştır', 'Siparişiniz kayıt altına alınmış olup en kısa sürede hazırlanmaya başlanacaktır.'];
    }

    $title = transaction_type_label($o['transaction_type']) . ' fişi ' . order_no((int) $o['id']);
    ?>
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">SİPARİŞ BELGESİ</p><h1><?= e($shop) ?></h1><p><?= e(setting('shop_address')) ?><?= setting('shop_phone') ? ' · ' . e(setting('shop_phone')) : '' ?></p></div></div>
      <div class="doc-ref"><b><?= e(transaction_type_label($o['transaction_type'])) ?> · <?= order_no((int) $o['id']) ?></b><?= date_tr($isDelivered ? $o['delivered_at'] : $o['created_at'], true) ?></div></div>

    <div class="status-banner <?= e($banner[1]) ?>"><?= icon($banner[0]) ?><div class="txt"><b><?= e($banner[2]) ?></b><span><?= e($banner[3]) ?></span></div></div>

    <table class="kv-table">
      <tr><th>Müşteri</th><td><?= e($custName) ?></td><th>Telefon</th><td><?= e(phone_display($o['c_phone']) ?: '—') ?></td></tr>
      <?php if ($o['transaction_type'] === 'gozluk'): ?>
      <tr><th>Cam tipi</th><td><?= e($o['lens_type'] ?: '—') ?></td><th>Teslim tarihi</th><td><?= date_tr($o['promised_date']) ?></td></tr>
      <tr><th>Çerçeve</th><td colspan="3"><?= e($o['frame_info'] ?: '—') ?></td></tr>
      <?php elseif ($o['transaction_type'] === 'tamir'): ?>
      <tr><th>Yapılan işlem</th><td><?= e(service_type_label($o['service_type'])) ?></td><th>Ücret</th><td><?= (int) $o['is_free'] ? 'Ücretsiz' : money($o['total_amount']) ?></td></tr>
      <tr><th>Ürün açıklaması</th><td colspan="3"><?= e($o['frame_info'] ?: '—') ?></td></tr>
      <?php else: ?>
      <tr><th>Açıklama</th><td colspan="3"><?= e($o['frame_info'] ?: '—') ?></td></tr>
      <?php endif; ?>
    </table>
    <?php if ($rx): ?>
      <h2>Reçete · <?= date_tr($rx['prescription_date']) ?> · <?= e(lens_designs()[$rx['lens_design']] ?? '') ?></h2>
      <?php include dirname(__DIR__, 2) . '/app/partials/rx-table.php'; ?>
    <?php endif; ?>
    <?php if (can_see_amounts()): ?>
      <h2>Ödeme</h2>
      <table class="lines">
        <thead><tr><th>Tarih</th><th>Açıklama</th><th class="num">Tutar</th></tr></thead>
        <tbody>
          <tr><td><?= date_tr($o['created_at']) ?></td><td>Sipariş tutarı</td><td class="num"><?= money($o['total_amount']) ?></td></tr>
          <?php if ((float) $o['sgk_amount'] > 0): ?><tr><td></td><td>SGK katkısı (tahmini)</td><td class="num">− <?= money($o['sgk_amount']) ?></td></tr><?php endif; ?>
          <?php foreach ($payments as $p): ?><tr><td><?= date_tr($p['created_at']) ?></td><td>Ödeme · <?= e(payment_methods()[$p['method']] ?? $p['method']) ?><?= $p['note'] ? ' · ' . e($p['note']) : '' ?></td><td class="num">− <?= money($p['amount']) ?></td></tr><?php endforeach; ?>
        </tbody>
        <?php if (!$hasDebt): ?><tfoot><tr><th colspan="2">Kalan</th><th class="num"><?= money($o['balance']) ?></th></tr></tfoot><?php endif; ?>
      </table>
    <?php endif; ?>
    <?php if ($hasDebt): ?>
      <div class="due-plate">
        <div><div class="label">Kalan bakiye</div><div class="amount"><?= money($o['balance']) ?></div></div>
        <div class="promise"><small>Ödeme sözü</small><b><?= $o['balance_promise_date'] ? date_tr($o['balance_promise_date']) : 'Belirtilmedi' ?></b></div>
      </div>
    <?php endif; ?>
    <h2>İşlemi yürütenler</h2>
    <table class="kv-table">
      <tr><th>Siparişi alan</th><td><?= e($o['created_by_name'] ?: '—') ?></td><th>Hazırlayan (atölye)</th><td><?= e(staff_name($o['assigned_to'])) ?></td></tr>
      <tr><th>Kalite kontrolü yapan</th><td><?= e(staff_name($o['qc_by'])) ?></td><th>Teslim eden</th><td><?= e(staff_name($o['delivered_by'])) ?></td></tr>
    </table>
    <?php
    $staffSide = $isDelivered ? staff_name($o['delivered_by']) : ($o['created_by_name'] ?: '');
    $staffRole = $isDelivered ? 'Teslim Eden Yetkili' : 'Siparişi Alan Yetkili';
    if ($hasDebt) {
        $custP = 'Ürünü teslim aldım; kalan bakiyeyi yukarıda belirtilen tarihe kadar ödeyeceğimi beyan ve kabul ederim.';
    } elseif ($isDelivered) {
        $custP = 'Ürünü eksiksiz ve hasarsız olarak teslim aldığımı beyan ederim.';
    } else {
        $custP = 'Yukarıdaki sipariş bilgilerini incelediğimi ve onayladığımı beyan ederim.';
    }
    ?>
    <?php if (!$isCancelled): ?>
      <div class="sign-box">
        <div class="box"><small><?= e($staffRole) ?></small><div class="who <?= $staffSide ? '' : 'empty' ?>"><?= $staffSide ? e($staffSide) : 'Atanmadı' ?></div><p>İşbu belgeyi düzenlemiştir.</p><div class="pen-line"></div></div>
        <div class="box"><small>Müşteri</small><div class="who"><?= e($custName) ?></div><p><?= e($custP) ?></p><div class="pen-line"></div></div>
      </div>
    <?php endif; ?>
    <?php
    /* Müşteri takip karekodu: evinde okutunca siparişinin durumunu görür. */
    $takipUrl = order_track_url((int) $o['id']);
    if ($takipUrl !== '' && !$isDelivered && track_page_exists()):
    ?>
      <div class="track-qr">
        <div class="track-qr-code"><?= qr_svg($takipUrl, 118, 'Q') ?></div>
        <div class="track-qr-txt">
          <b>Siparişinizi telefonunuzdan takip edin</b>
          <p>Kamerayla bu karekodu okuttuğunuzda gözlüğünüzün hangi aşamada olduğunu
             ve teslime hazır olup olmadığını anında görürsünüz.</p>
          <small><?= e(preg_replace('#^https?://#', '', $takipUrl)) ?></small>
        </div>
      </div>
    <?php endif; ?>
    <?php
    /* Teslim fişinde: gözlük bakım kartı karekodu (takip karekodundan ayrı, küçük).
       Yalnızca teslim edilmiş siparişlerde ve bakım sayfası yüklüyse basılır. */
    $bakimUrl = '';
    if ($isDelivered && !$isCancelled && is_file(APP_ROOT . '/bakim.php') && is_file(APP_ROOT . '/app/pages/bakim.php')) {
        $bakimUrl = musteri_url('bakim.php');
    }
    if ($bakimUrl !== ''):
    ?>
      <div class="care-qr">
        <div class="care-qr-code"><?= qr_svg($bakimUrl, 96, 'M') ?></div>
        <div class="care-qr-txt">
          <b>Gözlüğünüzün bakım kartı</b>
          <p>Gözlüğünüzü nasıl temizleyeceğinizi ve nelere dikkat etmeniz gerektiğini karekodu okutarak öğrenin.</p>
          <small><?= e(preg_replace('#^https?://#', '', $bakimUrl)) ?></small>
        </div>
      </div>
    <?php endif; ?>
    <?php
    /* Çerçeve Dene karekodu: müşteri evde telefonuyla çerçeve stillerini yüzünde dener.
       Deneme uygulaması sunucuda yüklü değilse ya da sipariş iptalse basılmaz. */
    $denemeUrl = '';
    if (!$isCancelled) {
        try {
            require_once dirname(__DIR__) . '/deneme.php';
            $denemeUrl = deneme_url(true);
        } catch (Throwable $e) {
            $denemeUrl = '';
        }
    }
    if ($denemeUrl !== ''):
    ?>
      <div class="try-qr">
        <div class="try-qr-code"><?= qr_svg($denemeUrl, 96, 'M') ?></div>
        <div class="try-qr-txt">
          <b>Çerçeveleri telefonunuzda deneyin</b>
          <p>Karekodu okutun, telefonunuzun kamerasıyla farklı çerçeve stillerini yüzünüzde deneyin. Görüntü telefonunuzdan çıkmaz.</p>
          <small><?= e(preg_replace('#^https?://#', '', $denemeUrl)) ?></small>
        </div>
      </div>
    <?php endif; ?>
    <div class="foot"><span class="foot-mark"><?= brand_mark() ?></span><span><?= e($shop) ?></span><span class="foot-note">Bu fiş işlem takibi içindir, fatura yerine geçmez.</span></div>
    <?php
} elseif ($type === 'garanti' && ozellik_acik('garanti')) {   // 4.16.0 garanti kartı (siparişin tüm garantileri ya da tek garanti)
    $gIdler = query_int('order')
        ? array_column(rows("SELECT id FROM garantiler WHERE order_id = ? AND durum = 'aktif' ORDER BY id", [query_int('order')]), 'id')
        : [query_int('id')];
    $gBelge = array_values(array_filter(array_map(static fn($gi): ?array => garanti_bul((int) $gi), $gIdler)));
    if (!$gBelge) { render_error_page('Garanti bulunamadı', ''); }
    $title = 'Garanti kartı ' . garanti_no((int) $gBelge[0]['id']);
    require dirname(__DIR__) . '/partials/garanti-belgesi.php';
} elseif ($type === 'garanti_talep' && ozellik_acik('garanti')) {   // 4.16.0 tedarikçiye garanti talebi
    $t = row('SELECT * FROM garanti_talepleri WHERE id = ?', [query_int('id')]);
    $g = $t ? garanti_bul((int) $t['garanti_id']) : null;
    if (!$g) { render_error_page('Garanti talebi bulunamadı', ''); }
    $title = 'Garanti talebi ' . garanti_no((int) $g['id']) . '-' . (int) $t['id'];
    require dirname(__DIR__) . '/partials/garanti-talep-formu.php';
} elseif ($type === 'sgk_dokum' && ozellik_acik('efatura') && can_see_amounts()) {   // 4.16.1 SGK dönem faturası reçete dökümü
    require_once dirname(__DIR__) . '/fatura.php';
    $f = row('SELECT * FROM faturalar WHERE id = ? AND sgk_donem IS NOT NULL', [query_int('id')]);
    if (!$f) { render_error_page('SGK dönem faturası bulunamadı', ''); }
    $title = 'SGK reçete dökümü ' . fatura_sgk_ay_adi((string) $f['sgk_donem']);
    require dirname(__DIR__) . '/partials/sgk-dokum.php';
} elseif ($type === 'satis' && ozellik_acik('hizli_satis') && can_see_amounts()) {   // 4.17.0 hızlı satış fişi
    $s = satis_bul(query_int('id'));
    if (!$s || (!is_super() && (int) $s['created_by'] !== (int) (current_user()['id'] ?? 0))) { render_error_page('Satış bulunamadı', ''); }
    $title = 'Satış fişi #' . (int) $s['id'];
    $mus = $s['musteri'] ? trim($s['musteri']['first_name'] . ' ' . $s['musteri']['last_name']) : '';
    $kalemInd = array_sum(array_map(static fn($k) => (float) $k['indirim'], $s['kalemler']));
    $genelInd = round((float) $s['indirim'] - $kalemInd, 2);
    ?>
    <div class="ticket">
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">SATIŞ FİŞİ</p><h1><?= e($shop) ?></h1><p><?= e(setting('shop_address')) ?><?= setting('shop_phone') ? ' · ' . e(setting('shop_phone')) : '' ?></p></div></div>
      <div class="doc-ref"><b>Satış #<?= (int) $s['id'] ?></b><?= date_tr($s['created_at'], true) ?></div></div>
    <?php if ($s['durum'] === 'iptal'): ?>
      <div class="status-banner tone-gold"><?= icon('x') ?><div class="txt"><b>İPTAL EDİLMİŞTİR</b><span><?= e(date_tr($s['iptal_at'], true)) ?> · <?= e((string) $s['iptal_sebep']) ?></span></div></div>
    <?php endif; ?>
    <?php if ($mus !== '' || $s['not_metni']): ?>
    <table class="kv-table">
      <?php if ($mus !== ''): ?><tr><th>Müşteri</th><td colspan="3"><?= e($mus) ?></td></tr><?php endif; ?>
      <?php if ($s['not_metni']): ?><tr><th>Not</th><td colspan="3"><?= e((string) $s['not_metni']) ?></td></tr><?php endif; ?>
    </table>
    <?php endif; ?>
    <table class="lines">
      <thead><tr><th>Ürün</th><th class="num">Adet</th><th class="num">Birim</th><th class="num">Tutar</th></tr></thead>
      <tbody>
      <?php foreach ($s['kalemler'] as $k): ?>
        <tr><td><?= e($k['ad']) ?><?= (float) $k['indirim'] > 0 ? '<br><small>İndirim −' . money($k['indirim']) . '</small>' : '' ?></td><td class="num"><?= (int) $k['adet'] ?></td><td class="num"><?= money($k['birim_fiyat']) ?></td><td class="num"><?= money($k['tutar']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <?php if ($genelInd > 0): ?><tr><th colspan="3">İndirim</th><th class="num">−<?= money($genelInd) ?></th></tr><?php endif; ?>
        <tr><th colspan="3">Toplam</th><th class="num"><?= money($s['toplam']) ?></th></tr>
        <?php foreach ($s['odemeler'] as $o): ?><tr><td colspan="3"><?= e(payment_methods()[$o['method']] ?? $o['method']) ?></td><td class="num"><?= money($o['amount']) ?></td></tr><?php endforeach; ?>
      </tfoot>
    </table>
    <div class="foot"><span class="foot-mark"><?= brand_mark() ?></span><span><?= e($shop) ?> · <?= e((string) $s['personel']) ?></span><span class="foot-note">Bu fiş işlem takibi içindir, fatura yerine geçmez.</span></div>
    </div>
    <?php
} elseif ($type === 'payment') {
    $p = row('SELECT p.*, o.id AS order_id, o.transaction_type, c.first_name, c.last_name, c.phone, u.full_name AS by_name
              FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id
              LEFT JOIN user_accounts u ON u.id = p.created_by
              WHERE p.id = ?', [query_int('id')]);
    if (!$p || !can_see_amounts()) { render_error_page('Makbuz bulunamadı', ''); }
    $balanceAfter = (float) scalar(
        "SELECT o.total_amount - COALESCE((SELECT SUM(amount) FROM payments WHERE order_id = o.id AND (created_at < ? OR (created_at = ? AND id <= ?))), 0)
         FROM orders o WHERE o.id = ?",
        [$p['created_at'], $p['created_at'], $p['id'], $p['order_id']]
    );
    $custName = trim($p['first_name'] . ' ' . $p['last_name']);
    $title = 'Tahsilat makbuzu ' . order_no((int) $p['order_id']);
    ?>
    <div class="ticket">
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">TAHSİLAT MAKBUZU</p><h1><?= e($shop) ?></h1><p><?= e(setting('shop_address')) ?><?= setting('shop_phone') ? ' · ' . e(setting('shop_phone')) : '' ?></p></div></div>
      <div class="doc-ref"><b>Tahsilat · <?= order_no((int) $p['order_id']) ?></b><?= date_tr($p['created_at'], true) ?></div></div>
    <div class="status-banner tone-gold"><?= icon('wallet') ?><div class="txt"><b>Ödeme Tahsil Edilmiştir</b><span>Aşağıda belirtilen tutar tarafımızca tahsil edilmiştir.</span></div></div>
    <table class="kv-table">
      <tr><th>Müşteri</th><td><?= e($custName) ?></td><th>Telefon</th><td><?= e(phone_display($p['phone']) ?: '—') ?></td></tr>
      <tr><th>İşlem</th><td><?= e(transaction_type_label($p['transaction_type'])) ?></td><th>Ödeme yöntemi</th><td><?= e(payment_methods()[$p['method']] ?? $p['method']) ?></td></tr>
      <?php if ($p['note']): ?><tr><th>Not</th><td colspan="3"><?= e($p['note']) ?></td></tr><?php endif; ?>
    </table>
    <table class="lines">
      <thead><tr><th>Açıklama</th><th class="num">Tutar</th></tr></thead>
      <tbody><tr><td>Tahsil edilen tutar</td><td class="num"><?= money($p['amount']) ?></td></tr></tbody>
      <tfoot><tr><th>Kalan bakiye</th><th class="num"><?= money(max(0, $balanceAfter)) ?></th></tr></tfoot>
    </table>
    <div class="sign-box">
      <div class="box"><small>Tahsilatı Yapan Yetkili</small><div class="who <?= $p['by_name'] ? '' : 'empty' ?>"><?= $p['by_name'] ? e($p['by_name']) : 'Atanmadı' ?></div><p>İşbu belgeyi düzenlemiştir.</p><div class="pen-line"></div></div>
      <div class="box"><small>Ödemeyi Yapan</small><div class="who"><?= e($custName) ?></div><p>Belirtilen tutarı nakden/kart ile ödediğimi beyan ederim.</p><div class="pen-line"></div></div>
    </div>
    <div class="foot"><span class="foot-mark"><?= brand_mark() ?></span><span><?= e($shop) ?></span><span class="foot-note">Bu makbuz işlem takibi içindir, fatura yerine geçmez.</span></div>
    </div>
    <?php
} elseif ($type === 'quote') {
    $qt = row('SELECT * FROM quotes WHERE id = ?', [query_int('id')]);
    if (!$qt) { render_error_page('Teklif bulunamadı', ''); }
    // 4.21.1: katalog teklifi kendine özel döküm tasarımıyla (app/partials/teklif-dokum.php, assets/teklif-dokum.css)
    if (($qt['tip'] ?? 'serbest') === 'katalog') {
        ob_end_clean();
        require dirname(__DIR__) . '/partials/teklif-dokum.php';
        exit;
    }
    $options = [];
    foreach ([1, 2, 3] as $i) {
        if ($qt["opt{$i}_name"]) {
            $options[] = ['name' => $qt["opt{$i}_name"], 'desc' => $qt["opt{$i}_desc"], 'price' => $qt["opt{$i}_price"]];
        }
    }
    $title = 'Teklif ' . $qt['customer_name'];
    ?>
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">FİYAT TEKLİFİ</p><h1><?= e($shop) ?></h1><p><?= e(setting('shop_address')) ?><?= setting('shop_phone') ? ' · ' . e(setting('shop_phone')) : '' ?></p></div></div>
      <div class="doc-ref"><b>Fiyat teklifi</b><?= date_tr($qt['created_at'], true) ?></div></div>
    <div class="status-banner tone-wine"><?= icon('spark') ?><div class="txt"><b>Sayın <?= e($qt['customer_name']) ?></b><span>Talebiniz doğrultusunda hazırladığımız fiyat teklifimiz aşağıda sunulmuştur.</span></div></div>
    <?php if ($qt['note']): ?><p class="muted"><?= e($qt['note']) ?></p><?php endif; ?>
    <div class="quote-grid">
      <?php foreach ($options as $i => $o): $isBest = $i === count($options) - 1 && count($options) > 1; ?>
        <div class="quote-col <?= $isBest ? 'is-best' : '' ?>">
          <?php if ($isBest): ?><div class="quote-flag">Önerimiz</div><?php endif; ?>
          <h3><?= e($o['name']) ?></h3>
          <?php if ($o['price'] !== null): ?><div class="quote-amt"><?= money($o['price']) ?></div><?php endif; ?>
          <?php if ($o['desc']): ?><p><?= nl2br(e($o['desc'])) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="foot"><span class="foot-mark"><?= brand_mark() ?></span><span><?= e($shop) ?></span><span class="foot-note">Bu teklif niteliğinde olup bağlayıcı değildir; fiyatlar önceden bildirilmeksizin değişebilir.</span></div>
    <?php
} elseif ($type === 'rx') {
    $rx = row('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph, c.first_name, c.last_name, c.birth_year FROM prescription_records r JOIN customers c ON c.id = r.customer_id LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.id = ?', [query_int('id')]);
    if (!$rx) { render_error_page('Reçete bulunamadı', ''); }
    $title = 'Reçete ' . $rx['first_name'] . ' ' . $rx['last_name'];
    ?>
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">REÇETE KARTI</p><h1><?= e($shop) ?></h1><p>Reçete kartı</p></div></div>
      <div class="doc-ref"><b><?= e($rx['first_name'] . ' ' . $rx['last_name']) ?></b><?= date_tr($rx['prescription_date']) ?> · Sipariş <?= order_no((int) $rx['order_id']) ?></div></div>
    <table class="kv-table">
      <tr><th>Kullanım</th><td><?= e(lens_designs()[$rx['lens_design']] ?? '') ?></td><th>Cam tipi</th><td><?= e($rx['lens_type'] ?: '—') ?></td></tr>
      <tr><th>Doktor</th><td><?= e($rx['doctor'] ?: '—') ?></td><th>Toplam PD</th><td><?= e($rx['pd'] ?: '—') ?></td></tr>
    </table>
    <?php include dirname(__DIR__, 2) . '/app/partials/rx-table.php'; ?>
    <?php if ($rx['prescription_note']): ?><p><b>Not:</b> <?= nl2br(e($rx['prescription_note'])) ?></p><?php endif; ?>
    <p class="small muted" style="text-align:center;margin-top:28px">Atölye çalışma kartıdır; hekim reçetesinin yerine geçmez.</p>
    <?php
} elseif ($type === 'depot') {
    $tab = query('tab', 'eksik');
    $status = $tab === 'siparis' ? 'siparis_verildi' : 'stokta_yok';
    $where = ["i.stock_status = ?", "o.order_stage <> 'iptal'"];
    $params = [$status];
    if (query('lens') !== '') { $where[] = 'i.lens_type = ?'; $params[] = query('lens'); }
    if (valid_date(query('from'))) { $where[] = 'i.created_at >= ?'; $params[] = query('from') . ' 00:00:00'; }
    if (valid_date(query('until'))) { $where[] = 'i.created_at < ?'; $params[] = date('Y-m-d', strtotime(query('until') . ' +1 day')); }
    $supplierId = query_int('supplier');
    $supplierName = null;
    if ($supplierId) {
        $where[] = 'i.supplier_id = ?';
        $params[] = $supplierId;
        $supplierName = supplier_label($supplierId);
    }
    $list = rows(
        'SELECT i.lens_type, i.lens_value, i.sph, i.cyl, i.axis, i.add_power, COUNT(*) AS cnt
         FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id JOIN orders o ON o.id = r.order_id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY i.lens_type, i.lens_value, i.sph, i.cyl, i.axis, i.add_power
         ORDER BY i.lens_type, i.sph, i.cyl',
        $params
    );
    $title = 'Depo cam talebi ' . date('d.m.Y');
    $total = array_sum(array_column($list, 'cnt'));
    ?>
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">DEPO TALEBİ</p><h1><?= e($shop) ?></h1><p>Depo cam talebi<?= $supplierName ? ' · ' . e($supplierName) : '' ?> · müşteri bilgisi içermez</p></div></div>
      <div class="doc-ref"><b><?= $tab === 'siparis' ? 'Sipariş verilenler' : 'Eksik camlar' ?></b><?= date('d.m.Y H:i') ?> · <?= $total ?> cam</div></div>
    <table class="lines">
      <thead><tr><th>#</th><th>Cam tipi</th><th>SPH</th><th>CYL</th><th>AKS</th><th>ADD</th><th class="num">Adet</th></tr></thead>
      <tbody>
        <?php foreach ($list as $i => $r): ?>
          <tr><td><?= $i + 1 ?></td><td><?= e($r['lens_type']) ?></td><td><?= e($r['sph'] ?: 'PL') ?></td><td><?= e($r['cyl'] ?: '—') ?></td><td><?= e($r['axis'] ?: '—') ?></td><td><?= e($r['add_power'] ?: '—') ?></td><td class="num"><b><?= (int) $r['cnt'] ?></b></td></tr>
        <?php endforeach; ?>
        <?php if (!$list): ?><tr><td colspan="7">Listede cam yok.</td></tr><?php endif; ?>
      </tbody>
      <tfoot><tr><th colspan="6">Toplam</th><th class="num"><?= $total ?></th></tr></tfoot>
    </table>
    <div class="sign-box"><div class="box"><small>Hazırlayan Yetkili</small><div class="who"><?= e((string) (current_user()['full_name'] ?? '')) ?></div><p>İşbu listeyi düzenlemiştir.</p><div class="pen-line"></div></div><div class="box"><small>Depo Onayı</small><div class="who empty">—</div><p>Bu listeye göre sipariş verilmiştir.</p><div class="pen-line"></div></div></div>
    <?php
} elseif ($type === 'supplier' && is_super()) {
    $sp = row('SELECT * FROM suppliers WHERE id = ?', [query_int('id')]);
    if (!$sp) { render_error_page('Tedarikçi bulunamadı', ''); }
    $from = valid_date(query('from')) ? query('from') : '';
    $until = valid_date(query('until')) ? query('until') : '';
    $invoices = rows('SELECT * FROM supplier_invoices WHERE supplier_id = ? ORDER BY invoice_date, id', [$sp['id']]);
    $payments = rows('SELECT * FROM supplier_payments WHERE supplier_id = ? ORDER BY created_at, id', [$sp['id']]);
    $ledger = [];
    foreach ($invoices as $inv) {
        $ledger[] = ['date' => $inv['invoice_date'], 'sort' => $inv['invoice_date'] . ' 00:00:01', 'amount' => (float) $inv['amount'], 'label' => 'Fatura ' . $inv['invoice_no'], 'note' => $inv['note']];
    }
    foreach ($payments as $p) {
        $ledger[] = ['date' => substr($p['created_at'], 0, 10), 'sort' => $p['created_at'], 'amount' => -(float) $p['amount'], 'label' => 'Ödeme · ' . (tedarik_odeme_yontemleri()[$p['method']] ?? $p['method']), 'note' => $p['note']];
    }
    usort($ledger, static fn($a, $b) => $a['sort'] <=> $b['sort']);
    $balance = 0.0;
    foreach ($ledger as &$r) { $balance += $r['amount']; $r['balance'] = $balance; }
    unset($r);
    if ($from !== '' || $until !== '') {
        $ledger = array_values(array_filter($ledger, static function ($r) use ($from, $until) {
            if ($from !== '' && $r['date'] < $from) { return false; }
            if ($until !== '' && $r['date'] > $until) { return false; }
            return true;
        }));
    }
    $title = 'Cari ekstre ' . $sp['name'];
    ?>
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">CARİ HESAP EKSTRESİ</p><h1><?= e($shop) ?></h1><p><?= e(setting('shop_address')) ?><?= setting('shop_phone') ? ' · ' . e(setting('shop_phone')) : '' ?></p></div></div>
      <div class="doc-ref"><b>Tedarikçi cari ekstresi</b><?= e($sp['name']) ?> · <?= date('d.m.Y') ?></div></div>
    <table class="kv-table">
      <tr><th>Tedarikçi</th><td><?= e($sp['name']) ?></td><th>Yetkili</th><td><?= e($sp['contact_name'] ?: '—') ?></td></tr>
      <tr><th>Dönem</th><td colspan="3"><?= $from || $until ? date_tr($from ?: null) . ' – ' . date_tr($until ?: null) : 'Tüm zamanlar' ?></td></tr>
    </table>
    <table class="lines">
      <thead><tr><th>Tarih</th><th>Hareket</th><th>Not</th><th class="num">Tutar</th><th class="num">Bakiye</th></tr></thead>
      <tbody>
        <?php foreach ($ledger as $r): ?>
          <tr><td><?= date_tr($r['date']) ?></td><td><?= e($r['label']) ?></td><td><?= e($r['note'] ?: '—') ?></td>
            <td class="num"><?= $r['amount'] > 0 ? '+' : '− ' ?><?= money(abs($r['amount'])) ?></td>
            <td class="num"><b><?= money($r['balance']) ?></b></td></tr>
        <?php endforeach; ?>
        <?php if (!$ledger): ?><tr><td colspan="5">Bu dönemde hareket yok.</td></tr><?php endif; ?>
      </tbody>
      <tfoot><tr><th colspan="4">Güncel bakiye (borcumuz)</th><th class="num"><?= money($balance) ?></th></tr></tfoot>
    </table>
    <div class="sign-box"><div class="box"><small>Yetkilimiz</small><div class="who"><?= e((string) (current_user()['full_name'] ?? '')) ?></div><p>İşbu ekstreyi düzenlemiştir.</p><div class="pen-line"></div></div><div class="box"><small>Tedarikçi Yetkilisi</small><div class="who empty">—</div><p>Ekstre karşılaştırılmış ve mutabık kalınmıştır.</p><div class="pen-line"></div></div></div>
    <p class="small muted" style="text-align:center;margin-top:28px">Bu ekstre iç kayıtlarımıza göre hazırlanmıştır, tedarikçi ekstresiyle karşılaştırma için kullanılır.</p>
    <?php
} elseif ($type === 'kasa' && can_see_amounts()) {
    $date = valid_date(query('date')) ? query('date') : date('Y-m-d');
    $range = [$date . ' 00:00:00', date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00'];
    $byMethod = rows("SELECT method, COUNT(*) AS cnt, SUM(amount) AS total FROM payments WHERE created_at >= ? AND created_at < ? GROUP BY method ORDER BY total DESC", $range);
    $byMethod = satis_kasa_birlestir($byMethod, satis_kasa_yontemleri($range));   // 4.17.0 hızlı satış dahil
    $totalIn = (float) array_sum(array_column($byMethod, 'total'));
    $byStaff = rows(
        "SELECT COALESCE(u.full_name, 'Bilinmiyor') AS name, SUM(p.amount) AS total, COUNT(*) AS cnt
         FROM payments p LEFT JOIN user_accounts u ON u.id = p.created_by
         WHERE p.created_at >= ? AND p.created_at < ? GROUP BY name ORDER BY total DESC", $range
    );
    $byStaff = satis_kasa_birlestir($byStaff, array_map(static fn($r) => ['name' => $r['name'], 'cnt' => $r['cnt'], 'total' => $r['total']], satis_kasa_personel($range)), ['name']);
    $expenses = rows('SELECT * FROM expenses WHERE expense_date = ? ORDER BY created_at', [$date]);
    $totalExpenses = (float) array_sum(array_column($expenses, 'amount'));
    $revenues = rows('SELECT * FROM revenues WHERE revenue_date = ? ORDER BY created_at', [$date]);
    $totalRevenues = (float) array_sum(array_column($revenues, 'amount'));
    $count = row('SELECT c.*, u.full_name AS by_name FROM cash_counts c LEFT JOIN user_accounts u ON u.id = c.created_by WHERE c.count_date = ? ORDER BY c.created_at DESC LIMIT 1', [$date]);
    $title = 'Kasa dökümü ' . $date;
    ?>
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">KASA DÖKÜMÜ</p><h1><?= e($shop) ?></h1><p>Gün sonu kasa dökümü</p></div></div>
      <div class="doc-ref"><b><?= date_tr($date) ?></b>Hazırlanma: <?= date('d.m.Y H:i') ?></div></div>
    <?php if ($count): $ok = abs((float) $count['difference']) < 0.01; ?>
      <div class="status-banner <?= $ok ? 'tone-green' : 'tone-amber' ?>"><?= icon($ok ? 'check' : 'wallet') ?><div class="txt"><b><?= $ok ? 'Kasa Kapatılmıştır · Mutabıktır' : 'Kasa Kapatılmıştır · Fark Tespit Edilmiştir' ?></b><span>Sayılan: <?= money($count['counted_amount']) ?> · Beklenen: <?= money($count['expected_amount']) ?><?= !$ok ? ' · Fark: ' . (((float) $count['difference'] > 0) ? '+' : '') . money($count['difference']) : '' ?></span></div></div>
    <?php else: ?>
      <div class="status-banner tone-gray"><?= icon('wallet') ?><div class="txt"><b>Kasa Henüz Kapatılmamıştır</b><span>Belirtilen tarih için kasa sayımı yapılmamıştır.</span></div></div>
    <?php endif; ?>
    <h2>Yöntem bazında tahsilat</h2>
    <table class="lines">
      <thead><tr><th>Yöntem</th><th class="num">Adet</th><th class="num">Tutar</th></tr></thead>
      <tbody>
        <?php foreach ($byMethod as $m): ?><tr><td><?= e(payment_methods()[$m['method']] ?? $m['method']) ?></td><td class="num"><?= (int) $m['cnt'] ?></td><td class="num"><?= money($m['total']) ?></td></tr><?php endforeach; ?>
        <?php if (!$byMethod): ?><tr><td colspan="3">Tahsilat yok.</td></tr><?php endif; ?>
      </tbody>
      <tfoot><tr><th colspan="2">Toplam</th><th class="num"><?= money($totalIn) ?></th></tr></tfoot>
    </table>
    <h2>Personel bazında</h2>
    <table class="lines">
      <thead><tr><th>Personel</th><th class="num">Adet</th><th class="num">Tutar</th></tr></thead>
      <tbody>
        <?php foreach ($byStaff as $s): ?><tr><td><?= e($s['name']) ?></td><td class="num"><?= (int) $s['cnt'] ?></td><td class="num"><?= money($s['total']) ?></td></tr><?php endforeach; ?>
        <?php if (!$byStaff): ?><tr><td colspan="3">Kayıt yok.</td></tr><?php endif; ?>
      </tbody>
    </table>
    <h2>Diğer gelirler</h2>
    <table class="lines">
      <thead><tr><th>Açıklama</th><th>Yöntem</th><th class="num">Tutar</th></tr></thead>
      <tbody>
        <?php foreach ($revenues as $r): ?><tr><td><?= e($r['description']) ?></td><td><?= e(payment_methods()[$r['method']] ?? $r['method']) ?></td><td class="num"><?= money($r['amount']) ?></td></tr><?php endforeach; ?>
        <?php if (!$revenues): ?><tr><td colspan="3">Ek gelir kaydı yok.</td></tr><?php endif; ?>
      </tbody>
      <tfoot><tr><th colspan="2">Toplam diğer gelir</th><th class="num"><?= money($totalRevenues) ?></th></tr></tfoot>
    </table>
    <h2>Günlük giderler</h2>
    <table class="lines">
      <thead><tr><th>Açıklama</th><th>Yöntem</th><th class="num">Tutar</th></tr></thead>
      <tbody>
        <?php foreach ($expenses as $e): ?><tr><td><?= e($e['description']) ?></td><td><?= e(payment_methods()[$e['method']] ?? $e['method']) ?></td><td class="num"><?= money($e['amount']) ?></td></tr><?php endforeach; ?>
        <?php if (!$expenses): ?><tr><td colspan="3">Gider kaydı yok.</td></tr><?php endif; ?>
      </tbody>
      <tfoot><tr><th colspan="2">Toplam gider</th><th class="num"><?= money($totalExpenses) ?></th></tr></tfoot>
    </table>
    <div class="sign-box">
      <div class="box"><small>Sayan</small><div class="who <?= ($count['by_name'] ?? '') ? '' : 'empty' ?>"><?= ($count['by_name'] ?? '') ? e($count['by_name']) : 'Atanmadı' ?></div><p>Kasayı sayan personel</p><div class="pen-line"></div></div>
      <div class="box"><small>Onaylayan</small><div class="who empty">—</div><p>İşletme yetkilisi</p><div class="pen-line"></div></div>
    </div>
    <div class="foot"><span class="foot-mark"><?= brand_mark() ?></span><span><?= e($shop) ?></span><span class="foot-note">Bu döküm iç kayıt amaçlıdır, fatura yerine geçmez.</span></div>
    <?php
} elseif ($type === 'report' && is_super()) {
    $from = valid_date(query('from')) ? query('from') : date('Y-m-01');
    $to = valid_date(query('to')) ? query('to') : date('Y-m-d');
    $range = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
    $s = row("SELECT COUNT(*) AS orders, COALESCE(SUM(o.total_amount), 0) AS turnover, COALESCE(SUM(o.total_amount - COALESCE(p.paid, 0)), 0) AS bal FROM orders o " . PAID_JOIN . " WHERE o.order_stage <> 'iptal' AND o.created_at >= ? AND o.created_at < ?", $range);
    $collected = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE created_at >= ? AND created_at < ?', $range);
    $lens = rows("SELECT COALESCE(NULLIF(lens_type, ''), 'Seçilmemiş') AS name, COUNT(*) AS cnt, SUM(total_amount) AS total FROM orders WHERE order_stage <> 'iptal' AND created_at >= ? AND created_at < ? GROUP BY name ORDER BY cnt DESC", $range);
    $stagesList = rows('SELECT order_stage AS name, COUNT(*) AS cnt FROM orders WHERE created_at >= ? AND created_at < ? GROUP BY order_stage ORDER BY cnt DESC', $range);
    $title = 'Rapor ' . date_tr($from) . ' – ' . date_tr($to);
    ?>
    <div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">DÖNEM RAPORU</p><h1><?= e($shop) ?></h1><p>Dönem raporu</p></div></div>
      <div class="doc-ref"><b><?= date_tr($from) ?> – <?= date_tr($to) ?></b>Hazırlanma: <?= date('d.m.Y H:i') ?></div></div>
    <table class="kv-table">
      <tr><th>Sipariş (iptal hariç)</th><td><?= (int) $s['orders'] ?></td><th>Ciro</th><td><?= money($s['turnover']) ?></td></tr>
      <tr><th>Dönem tahsilatı</th><td><?= money($collected) ?></td><th>Dönem siparişlerinin kalanı</th><td><?= money($s['bal']) ?></td></tr>
    </table>
    <h2>Cam tipi</h2>
    <table class="lines"><thead><tr><th>Cam tipi</th><th class="num">Adet</th><th class="num">Tutar</th></tr></thead>
      <tbody><?php foreach ($lens as $l): ?><tr><td><?= e($l['name']) ?></td><td class="num"><?= (int) $l['cnt'] ?></td><td class="num"><?= money($l['total']) ?></td></tr><?php endforeach; ?></tbody></table>
    <h2>Sipariş durumları</h2>
    <table class="lines"><thead><tr><th>Durum</th><th class="num">Adet</th></tr></thead>
      <tbody><?php foreach ($stagesList as $st): ?><tr><td><?= e(stage_label($st['name'])) ?></td><td class="num"><?= (int) $st['cnt'] ?></td></tr><?php endforeach; ?></tbody></table>
    <?php
} else {
    ob_end_clean();
    render_error_page('Belge bulunamadı', 'Yazdırılacak belge türü geçersiz veya yetkiniz yok.');
}
$body = ob_get_clean();

/**
 * Belge süslemeleri: köşe filigranları, mercek mührü ve alt flöron.
 * Tamamı inline SVG (çizgi) — tarayıcının "arka planları yazdır" ayarı
 * kapalı olsa bile basılır, çünkü zemin değil içeriktir.
 */
function doc_ornaments(string $shop = ''): string
{
    $corner = '<svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-linecap="round">'
        . '<path d="M0 24V9A9 9 0 0 1 9 0h15" stroke-width="1.5"/>'
        . '<path d="M0 34V11A11 11 0 0 1 11 0h23" stroke-width=".7" opacity=".7"/>'
        . '<path d="M6 44c12-2 21-11 23-23" stroke-width=".7" stroke-dasharray="1.5 3.5"/>'
        . '<circle cx="27" cy="39" r="3.4" stroke-width="1.1"/>'
        . '<circle cx="39" cy="27" r="3.4" stroke-width="1.1"/>'
        . '<path d="M29.6 36.4 36.4 29.6" stroke-width="1.1"/>'
        . '</svg>';

    // Mühür: ışın çelengi + iç içe halkalar + çevresinde dönen mağaza adı
    $rays = '';
    for ($a = 0; $a < 360; $a += 7.5) {
        $r = deg2rad($a);
        $long = fmod($a, 30.0) < 0.1;
        $r1 = $long ? 104 : 108;
        $rays .= '<path d="M' . round(120 + $r1 * cos($r), 1) . ' ' . round(120 + $r1 * sin($r), 1)
            . 'L' . round(120 + 115 * cos($r), 1) . ' ' . round(120 + 115 * sin($r), 1) . '"/>';
    }
    $ringText = '';
    if ($shop !== '') {
        $up = function_exists('mb_strtoupper') ? mb_strtoupper($shop, 'UTF-8') : strtoupper($shop);
        $loop = htmlspecialchars(str_repeat($up . '  ·  ', 3), ENT_QUOTES, 'UTF-8');
        $ringText = '<text font-family="Manrope,Arial,sans-serif" font-size="11" font-weight="700"'
            . ' letter-spacing="3.4" fill="currentColor" stroke="none">'
            . '<textPath href="#pa-ring" xlink:href="#pa-ring" startOffset="0">' . $loop . '</textPath></text>';
    }

    $seal = '<svg viewBox="0 0 240 240" fill="none" stroke="currentColor" stroke-linecap="round"'
        . ' xmlns:xlink="http://www.w3.org/1999/xlink">'
        . '<defs><path id="pa-ring" fill="none" d="M120 120m-86 0a86 86 0 1 1 172 0a86 86 0 1 1 -172 0"/></defs>'
        . '<circle cx="120" cy="120" r="118" stroke-width=".8" stroke-dasharray="2 7"/>'
        . '<g stroke-width=".8">' . $rays . '</g>'
        . '<circle cx="120" cy="120" r="97" stroke-width="1.4"/>'
        . '<circle cx="120" cy="120" r="74" stroke-width=".6"/>'
        . $ringText
        . '<circle cx="100" cy="122" r="32" stroke-width="1.6"/>'
        . '<circle cx="140" cy="122" r="32" stroke-width="1.6"/>'
        . '<path d="M130 118q10-8 20 0" stroke-width="1.6"/>'
        . '<path d="M52 104h136" stroke-width=".6" opacity=".55"/>'
        . '<path d="M84 74h72" stroke-width=".8"/>'
        . '<path d="M90 172h60" stroke-width=".8"/>'
        . '</svg>';

    // Giyoş bandı: iç içe geçen ince dalgalar (kıymetli evrak dokusu)
    $waves = '';
    foreach ([[0, 1.0, '1'], [9, 1.0, '.75'], [18, 1.0, '.5'], [27, 1.0, '.32']] as [$sh, $amp, $op]) {
        $d = 'M0 16';
        for ($x = 0; $x <= 320; $x += 8) {
            $y = 16 + 10 * sin(($x + $sh) / 18) * $amp + 3.2 * sin(($x + $sh) / 6.5);
            $d .= 'L' . $x . ' ' . round($y, 1);
        }
        $waves .= '<path d="' . $d . '" opacity="' . $op . '"/>';
    }
    $band = '<svg viewBox="0 0 320 32" fill="none" stroke="currentColor" stroke-width=".7">'
        . $waves . '</svg>';

    return '<div class="doc-deco" aria-hidden="true">'
        . '<span class="deco-corner deco-tl">' . $corner . '</span>'
        . '<span class="deco-corner deco-tr">' . $corner . '</span>'
        . '<span class="deco-corner deco-bl">' . $corner . '</span>'
        . '<span class="deco-corner deco-br">' . $corner . '</span>'
        . '<span class="deco-band deco-band-top">' . $band . '</span>'
        . '<span class="deco-band deco-band-bottom">' . $band . '</span>'
        . '<span class="deco-seal">' . $seal . '</span>'
        . '</div>';
}

function doc_flourish(): string
{
    return '<div class="doc-flourish" aria-hidden="true"><svg viewBox="0 0 220 16" fill="none" stroke="currentColor" stroke-linecap="round" stroke-width="1.1">'
        . '<path d="M2 8h72"/><path d="M146 8h72"/>'
        . '<path d="M86 8q12-9 24 0-12 9-24 0Z"/><path d="M134 8q-12-9-24 0 12 9 24 0Z"/>'
        . '<circle cx="110" cy="8" r="2.6"/>'
        . '</svg></div>';
}

?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="<?= e(asset('print.css')) ?>">
<?= brand_style_tag() ?>
</head>
<body>
<div class="toolbar"><button type="button" data-print>Yazdır / PDF kaydet</button><button type="button" class="ghost" data-close>Kapat</button></div>
<p class="print-tip">Belge, tarayıcının &laquo;arka planları yazdır&raquo; ayarı kapalı olsa da eksiksiz basılır. Sayfanın üstündeki adres/tarih satırını kaldırmak için yazdırma penceresinde <b>Üst bilgi / Alt bilgi</b> seçeneklerini <b>&mdash;boş&mdash;</b> yapın.</p>
<main class="doc"><?= doc_ornaments($shop) ?><?= $body ?><?= doc_flourish() ?></main>
<script src="<?= e(asset('print.js')) ?>" defer></script>
</body>
</html>
