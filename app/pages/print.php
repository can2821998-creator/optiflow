<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

/* 4.22.0 — Tüm yazdırılan belgeler (dökümler) tek tasarım dilinde: app/dokum.php yapı taşları + assets/dokum.css.
   Her tür kendi gövdesini üretir (üst bant → karşılama → bilgi şeridi → kutular → imzalar → alt bilgi);
   sayfa iskeleti en altta. Katalog teklifi kendi şablonuyla (app/partials/teklif-dokum.php). */

$type = query('type');
$shop = setting('shop_name', 'OptiFlow');
$title = '';
$ekCss = [];
ob_start();

if ($type === 'order') {
    $o = find_order(query_int('id'));
    if (!$o) { render_error_page('Sipariş bulunamadı', ''); }
    $rx = row('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph FROM prescription_records r LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.order_id = ? ORDER BY r.prescription_date DESC, r.id DESC LIMIT 1', [$o['id']]);
    $tutarlar = can_see_amounts();
    $payments = $tutarlar ? rows('SELECT * FROM payments WHERE order_id = ? ORDER BY created_at', [$o['id']]) : [];
    $isDelivered = $o['order_stage'] === 'teslim_edildi';
    $isCancelled = $o['order_stage'] === 'iptal';
    $hasDebt = $isDelivered && $tutarlar && (float) $o['balance'] > 0.009;
    $custName = trim($o['c_first'] . ' ' . $o['c_last']);
    $tur = transaction_type_label($o['transaction_type']);

    // Durum: rozet tonu, ikon, kısa başlık ve kurumsal dilde açıklama.
    if ($isCancelled) {
        $durum = ['gri', 'carpi', 'Sipariş iptal edildi', 'İlgili sipariş kaydı iptal edilmiş olup herhangi bir işlem gerektirmemektedir.'];
    } elseif ($hasDebt) {
        $durum = ['uyari', 'cuzdan', 'Teslim edildi · bakiye var', 'Ürün tarafınıza teslim edilmiştir. Kalan bakiyenin ' . ($o['balance_promise_date'] ? date_tr($o['balance_promise_date']) . ' tarihine kadar' : 'en kısa sürede') . ' ödenmesini rica ederiz.'];
    } elseif ($isDelivered) {
        $durum = ['ok', 'onay', 'Teslim edildi', 'Ürün eksiksiz olarak tarafınıza teslim edilmiştir. Bizi tercih ettiğiniz için teşekkür ederiz.'];
    } elseif ($o['order_stage'] === 'hazirlandi') {
        $durum = ['ok', 'goz', 'Teslime hazır', 'Siparişiniz hazırlanmıştır; mağazamızdan teslim alabilirsiniz.'];
    } elseif ($o['order_stage'] === 'atolyede') {
        $durum = ['bilgi', 'ayar', 'Atölyede hazırlanıyor', 'Siparişiniz atölyemizde özenle hazırlanmaktadır.'];
    } else {
        $durum = ['', 'belge', 'Sipariş alındı', 'Siparişiniz kayıt altına alınmıştır; en kısa sürede hazırlanmaya başlanacaktır.'];
    }

    $title = $tur . ' fişi ' . order_no((int) $o['id']);
    $meta = [['Fiş no', order_no((int) $o['id'])], ['Tarih', date_tr($o['created_at'])]];
    if ($isDelivered) {
        $meta[] = ['Teslim', date_tr($o['delivered_at'])];
    } elseif ($o['transaction_type'] === 'gozluk' && $o['promised_date']) {
        $meta[] = ['Söz verilen', date_tr($o['promised_date'])];
    }
    echo dokum_ust($tur . ' fişi', $meta);

    $sag = '';
    if ($tutarlar && !$isCancelled) {
        if ($hasDebt) {
            $sag = dokum_vurgu('Kalan bakiye', money($o['balance']), $o['balance_promise_date'] ? 'Ödeme sözü ' . date_tr($o['balance_promise_date']) : 'Ödeme sözü belirtilmedi');
        } elseif ((float) $o['balance'] <= 0.009) {
            $sag = dokum_vurgu('Sipariş tutarı', money($o['total_amount']), 'Tamamı ödendi', 'ok');
        } else {
            $sag = dokum_vurgu('Sipariş tutarı', money($o['total_amount']), 'Kalan ' . money($o['balance']));
        }
    }
    echo dokum_selam('Sayın', $custName, e($durum[3]), $sag, dokum_rozet($durum[0], $durum[1], $durum[2]));

    $tel = phone_display($o['c_phone']);
    if ($o['transaction_type'] === 'gozluk') {
        echo dokum_bilgi([
            ['cerceve', 'Çerçeve', (string) $o['frame_info']],
            ['goz', 'Cam tipi', (string) $o['lens_type']],
            ['takvim', $isDelivered ? 'Teslim tarihi' : 'Söz verilen teslim', date_tr($isDelivered ? $o['delivered_at'] : $o['promised_date'])],
            ['tel', 'Telefon', $tel],
        ], true);
    } elseif ($o['transaction_type'] === 'tamir') {
        echo dokum_bilgi([
            ['cerceve', 'Ürün', (string) $o['frame_info']],
            ['ayar', 'Yapılan işlem', service_type_label($o['service_type'])],
            ['cuzdan', 'Ücret', (int) $o['is_free'] ? 'Ücretsiz' : ($tutarlar ? money($o['total_amount']) : '')],
            ['tel', 'Telefon', $tel],
        ], true);
    } else {
        echo dokum_bilgi([
            ['etiket', 'Açıklama', (string) $o['frame_info']],
            ['takvim', 'Tarih', date_tr($o['created_at'])],
            ['tel', 'Telefon', $tel],
        ], true);
    }

    if ($rx) {
        ob_start();
        include dirname(__DIR__, 2) . '/app/partials/rx-table.php';
        $rxHtml = (string) ob_get_clean();
        echo '<div class="bolum">' . dokum_kutu('Reçete', $rxHtml, '', trim(date_tr($rx['prescription_date']) . ' · ' . (lens_designs()[$rx['lens_design']] ?? ''), ' ·')) . '</div>';
    }

    $yurutenler = dokum_liste([
        ['Siparişi alan', (string) ($o['created_by_name'] ?: '')],
        ['Hazırlayan (atölye)', staff_name($o['assigned_to'])],
        ['Kalite kontrolü', staff_name($o['qc_by'])],
        ['Teslim eden', staff_name($o['delivered_by'])],
    ]);
    if ($tutarlar) {
        $satir = '<tr><td>' . e(date_tr($o['created_at'])) . '</td><td>Sipariş tutarı</td><td class="num">' . e(money($o['total_amount'])) . '</td></tr>';
        if ((float) $o['sgk_amount'] > 0) {
            $satir .= '<tr><td></td><td>SGK katkısı <small>tahmini</small></td><td class="num eksi">−' . e(money($o['sgk_amount'])) . '</td></tr>';
        }
        foreach ($payments as $p) {
            $satir .= '<tr><td>' . e(date_tr($p['created_at'])) . '</td><td>Ödeme · ' . e(payment_methods()[$p['method']] ?? $p['method']) . ($p['note'] ? '<small>' . e($p['note']) . '</small>' : '') . '</td><td class="num eksi">−' . e(money($p['amount'])) . '</td></tr>';
        }
        $odeme = '<table class="tablo"><thead><tr><th>Tarih</th><th>Açıklama</th><th class="num">Tutar</th></tr></thead><tbody>' . $satir . '</tbody>'
            . '<tfoot><tr class="genel"><th colspan="2">Kalan bakiye</th><th class="num">' . e(money(max(0, (float) $o['balance']))) . '</th></tr></tfoot></table>';
        echo '<div class="iki gen-dar">' . dokum_kutu('Ödeme', $odeme) . dokum_kutu('İşlemi yürütenler', $yurutenler) . '</div>';
    } else {
        echo '<div class="bolum">' . dokum_kutu('İşlemi yürütenler', $yurutenler) . '</div>';
    }

    /* Karekodlar: takip (teslim öncesi), bakım kartı (teslimde), çerçeve dene. */
    $kareler = [];
    $takipUrl = order_track_url((int) $o['id']);
    if ($takipUrl !== '' && !$isDelivered && !$isCancelled && track_page_exists()) {
        $kareler[] = [$takipUrl, 'Siparişinizi telefonunuzdan takip edin', 'Karekodu okutun; gözlüğünüzün hangi aşamada olduğunu ve teslime hazır olup olmadığını anında görün.'];
    }
    if ($isDelivered && is_file(APP_ROOT . '/bakim.php') && is_file(APP_ROOT . '/app/pages/bakim.php')) {
        $kareler[] = [musteri_url('bakim.php'), 'Gözlüğünüzün bakım kartı', 'Temizlik ve kullanımda dikkat edilecekler; karekodu okutarak öğrenin.'];
    }
    if (!$isCancelled) {
        try {
            require_once dirname(__DIR__) . '/deneme.php';
            $denemeUrl = deneme_url(true);
            if ($denemeUrl !== '') {
                $kareler[] = [$denemeUrl, 'Çerçeveleri telefonunuzda deneyin', 'Farklı çerçeve stillerini kameranızla yüzünüzde deneyin; görüntü telefonunuzdan çıkmaz.'];
            }
        } catch (Throwable $e) {
            // deneme uygulaması yüklü değil
        }
    }
    echo dokum_kareler($kareler);

    if (!$isCancelled) {
        $personel = $isDelivered ? staff_name($o['delivered_by']) : (string) ($o['created_by_name'] ?: '');
        $musteriBeyan = $hasDebt
            ? 'Ürünü teslim aldım; kalan bakiyeyi belirtilen tarihe kadar ödeyeceğimi beyan ve kabul ederim.'
            : ($isDelivered ? 'Ürünü eksiksiz ve hasarsız olarak teslim aldığımı beyan ederim.' : 'Yukarıdaki sipariş bilgilerini incelediğimi ve onayladığımı beyan ederim.');
        echo dokum_imzalar([
            [$isDelivered ? 'Teslim eden yetkili' : 'Siparişi alan yetkili', $personel === '—' ? '' : $personel, 'İşbu belgeyi düzenlemiştir.'],
            ['Müşteri', $custName, $musteriBeyan],
        ]);
    }
    echo dokum_son('<p>Bu fiş işlem takibi içindir, <b>fatura yerine geçmez</b>.</p><p>Sorularınız için ' . e(setting('shop_phone', '') !== '' ? setting('shop_phone', '') : 'mağazamıza') . ' · Sipariş ' . e(order_no((int) $o['id'])) . '</p>');

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
    $adet = array_sum(array_map(static fn($k) => (int) $k['adet'], $s['kalemler']));
    $iptal = $s['durum'] === 'iptal';

    echo dokum_ust('SATIŞ FİŞİ', [['Fiş no', dokum_no((int) $s['id'], 'S')], ['Tarih', date_tr($s['created_at'], true)], ['Satışı yapan', (string) ($s['personel'] ?: '—')]]);
    echo dokum_selam($mus !== '' ? 'Sayın' : 'Perakende satış', $mus !== '' ? $mus : 'Değerli müşterimiz',
        'Alışverişiniz için teşekkür ederiz. Ürünlerinizi faturanızla birlikte saklamanızı öneririz.',
        dokum_vurgu('Toplam', money($s['toplam']), count($s['kalemler']) . ' kalem · ' . $adet . ' adet', $iptal ? 'koyu' : ''));
    if ($iptal) {
        echo dokum_durum('gri', 'carpi', 'İPTAL EDİLMİŞTİR', date_tr($s['iptal_at'], true) . ' · ' . (string) $s['iptal_sebep']);
    }
    $satir = '';
    foreach ($s['kalemler'] as $i => $k) {
        $satir .= '<tr><td><span class="sira">' . ($i + 1) . '</span></td><td><b>' . e($k['ad']) . '</b>' . ((float) $k['indirim'] > 0 ? '<small>İndirim −' . e(money($k['indirim'])) . '</small>' : '') . '</td>'
            . '<td class="num">' . (int) $k['adet'] . '</td><td class="num">' . e(money($k['birim_fiyat'])) . '</td><td class="num"><b>' . e(money($k['tutar'])) . '</b></td></tr>';
    }
    $alt = '';
    if ($genelInd > 0) {
        $alt .= '<tr><th colspan="4">Ara toplam</th><th class="num">' . e(money((float) $s['toplam'] + $genelInd)) . '</th></tr>';
        $alt .= '<tr><th colspan="4">İndirim</th><th class="num eksi">−' . e(money($genelInd)) . '</th></tr>';
    }
    $alt .= '<tr class="genel"><th colspan="4">Toplam</th><th class="num">' . e(money($s['toplam'])) . '</th></tr>';
    echo '<div class="bolum">' . dokum_kutu('Ürünler', '<table class="tablo"><thead><tr><th>#</th><th>Ürün</th><th class="num">Adet</th><th class="num">Birim</th><th class="num">Tutar</th></tr></thead><tbody>' . $satir . '</tbody><tfoot>' . $alt . '</tfoot></table>', 'uzun') . '</div>';
    $odemeler = array_map(static fn($od) => [payment_methods()[$od['method']] ?? $od['method'], money($od['amount'])], $s['odemeler']);
    $sagKutu = $s['not_metni']
        ? dokum_kutu('Not', '<p>' . e((string) $s['not_metni']) . '</p>')
        : dokum_kutu('Değişim ve iade', '<p>Değişim ve iade için ürünü, ambalajı ve bu fişle birlikte 14 gün içinde mağazamıza getirin. Hijyen ürünlerinde (solüsyon, damla) ambalajı açılmış ürün iade alınmaz.</p>');
    echo '<div class="iki">' . dokum_kutu('Ödeme', dokum_liste($odemeler ?: [['Ödeme', '—']])) . $sagKutu . '</div>';
    echo dokum_son('<p>Bu fiş işlem takibi içindir, <b>fatura yerine geçmez</b>.</p><p>Satış ' . e(dokum_no((int) $s['id'], 'S')) . ' · ' . e((string) $s['personel']) . '</p>');

} elseif ($type === 'payment') {
    $p = row('SELECT p.*, o.id AS order_id, o.transaction_type, o.total_amount, o.sgk_amount, c.first_name, c.last_name, c.phone, u.full_name AS by_name
              FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id
              LEFT JOIN user_accounts u ON u.id = p.created_by
              WHERE p.id = ?', [query_int('id')]);
    if (!$p || !can_see_amounts()) { render_error_page('Makbuz bulunamadı', ''); }
    // Bu ödemeye kadarki hareketler (bu ödeme dahil) ve sonrasındaki kalan.
    $gecmis = rows('SELECT * FROM payments WHERE order_id = ? AND (created_at < ? OR (created_at = ? AND id <= ?)) ORDER BY created_at, id',
        [$p['order_id'], $p['created_at'], $p['created_at'], $p['id']]);
    $balanceAfter = (float) $p['total_amount'] - (float) $p['sgk_amount'] - array_sum(array_map(static fn($x) => (float) $x['amount'], $gecmis));
    $custName = trim($p['first_name'] . ' ' . $p['last_name']);
    $yontem = payment_methods()[$p['method']] ?? $p['method'];
    $title = 'Tahsilat makbuzu ' . order_no((int) $p['order_id']);

    echo dokum_ust('Tahsilat makbuzu', [['Makbuz no', dokum_no((int) $p['id'], 'M')], ['Sipariş', order_no((int) $p['order_id'])], ['Tarih', date_tr($p['created_at'], true)]]);
    echo dokum_selam('Sayın', $custName,
        e(transaction_type_label($p['transaction_type'])) . ' (' . e(order_no((int) $p['order_id'])) . ') için aşağıda belirtilen tutar tarafımızca <b>tahsil edilmiştir</b>. Teşekkür ederiz.',
        dokum_vurgu('Tahsil edilen', money($p['amount']), $yontem, 'ok'),
        dokum_rozet('ok', 'onay', 'Ödeme alındı'));
    echo '<p class="yazi">Yalnız: <b>' . e(tutar_yaziyla((float) $p['amount'])) . '</b></p>';
    echo dokum_bilgi([
        ['kisi', 'Ödemeyi yapan', $custName],
        [$p['method'] === 'kart' ? 'kart' : 'para', 'Ödeme yöntemi', $yontem],
        ['tel', 'Telefon', phone_display($p['phone'])],
        ['cuzdan', 'Kalan bakiye', money(max(0, $balanceAfter)), $balanceAfter <= 0.009 ? 'tamamı ödendi' : ''],
    ], true);
    $satir = '<tr><td>' . e(date_tr($p['created_at'])) . '</td><td>Sipariş tutarı · ' . e(order_no((int) $p['order_id'])) . '</td><td class="num">' . e(money($p['total_amount'])) . '</td></tr>';
    if ((float) $p['sgk_amount'] > 0) {
        $satir .= '<tr><td></td><td>SGK katkısı <small>tahmini</small></td><td class="num eksi">−' . e(money($p['sgk_amount'])) . '</td></tr>';
    }
    foreach ($gecmis as $x) {
        $bu = (int) $x['id'] === (int) $p['id'];
        $satir .= '<tr' . ($bu ? ' class="bu"' : '') . '><td>' . e(date_tr($x['created_at'])) . '</td><td>' . ($bu ? 'Bu makbuz · ' : 'Ödeme · ') . e(payment_methods()[$x['method']] ?? $x['method']) . ($x['note'] ? '<small>' . e($x['note']) . '</small>' : '') . '</td><td class="num eksi">−' . e(money($x['amount'])) . '</td></tr>';
    }
    $tablo = '<table class="tablo"><thead><tr><th>Tarih</th><th>Hareket</th><th class="num">Tutar</th></tr></thead><tbody>' . $satir . '</tbody>'
        . '<tfoot><tr class="genel"><th colspan="2">Bu ödemeden sonra kalan</th><th class="num">' . e(money(max(0, $balanceAfter))) . '</th></tr></tfoot></table>';
    echo '<div class="bolum">' . dokum_kutu('Hesap özeti', $tablo, 'uzun') . '</div>';
    echo dokum_imzalar([
        ['Tahsilatı yapan yetkili', (string) ($p['by_name'] ?? ''), 'İşbu makbuzu düzenlemiştir.'],
        ['Ödemeyi yapan', $custName, 'Belirtilen tutarı ödediğimi beyan ederim.'],
    ]);
    echo dokum_son('<p>Bu makbuz işlem takibi içindir, <b>fatura yerine geçmez</b>.</p><p>Makbuz ' . e(dokum_no((int) $p['id'], 'M')) . ' · Sipariş ' . e(order_no((int) $p['order_id'])) . '</p>');

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
            $options[] = ['name' => $qt["opt{$i}_name"], 'desc' => (string) $qt["opt{$i}_desc"], 'price' => $qt["opt{$i}_price"]];
        }
    }
    $title = 'Teklif ' . $qt['customer_name'];
    $ekCss = ['teklif-dokum.css'];
    $olusturma = strtotime((string) $qt['created_at']) ?: time();
    $gecerlilik = date('d.m.Y', $olusturma + (defined('TEKLIF_GECERLILIK_GUN') ? TEKLIF_GECERLILIK_GUN : 15) * 86400);
    $fiyatlar = array_values(array_filter(array_map(static fn($o) => $o['price'] !== null ? (float) $o['price'] : null, $options), static fn($v) => $v !== null));
    $hazirlayan = $qt['created_by'] ? (string) scalar('SELECT full_name FROM user_accounts WHERE id = ?', [(int) $qt['created_by']]) : '';

    echo dokum_ust('Fiyat teklifi', [['Teklif no', dokum_no((int) $qt['id'])], ['Tarih', date('d.m.Y', $olusturma)], ['Geçerlilik', $gecerlilik]]);
    echo dokum_selam('Sayın', (string) $qt['customer_name'],
        count($options) > 1 ? 'Talebiniz doğrultusunda hazırladığımız ' . count($options) . ' seçeneği yan yana karşılaştırabilirsiniz.' : 'Talebiniz doğrultusunda hazırladığımız teklifin ayrıntıları aşağıdadır.',
        count($fiyatlar) > 1 ? '<div class="baslayan"><small>Başlayan fiyatlarla</small><b>' . e(money(min($fiyatlar))) . '</b></div>' : '');
    echo '<section class="secenekler n' . max(1, count($options)) . '">';
    foreach ($options as $i => $o) {
        $one = $i === count($options) - 1 && count($options) > 1;
        $satirlar = array_values(array_filter(array_map('trim', explode("\n", str_replace("\r", '', $o['desc'])))));
        echo '<article class="kart' . ($one ? ' one' : '') . '">' . ($one ? '<span class="serit">Önerimiz</span>' : '')
            . '<header><span class="no">Seçenek ' . ($i + 1) . '</span><h2>' . e($o['name']) . '</h2></header><div class="cam">';
        if ($satirlar) {
            echo '<ul class="satirlar">';
            foreach ($satirlar as $st) { echo '<li>' . e($st) . '</li>'; }
            echo '</ul>';
        }
        echo '</div><footer><small>Fiyat</small><b>' . ($o['price'] !== null ? e(money($o['price'])) : 'Sorunuz') . '</b></footer></article>';
    }
    echo '</section>';
    if ($qt['note']) {
        echo '<div class="alt-iki">' . dokum_kutu('Not', '<p>' . nl2br(e((string) $qt['note'])) . '</p>') . '</div>';
    }
    echo dokum_son('<p>Bu teklif <b>' . e($gecerlilik) . '</b> tarihine kadar geçerlidir ve bilgilendirme amaçlıdır; fiyatlar stok ve tedarikçi koşullarına göre değişebilir.</p>',
        '<div class="imza"><small>Hazırlayan</small><b>' . e($hazirlayan !== '' ? $hazirlayan : $shop) . '</b><span>' . e($shop) . '</span></div>');

} elseif ($type === 'rx') {
    $rx = row('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph, c.first_name, c.last_name, c.birth_year FROM prescription_records r JOIN customers c ON c.id = r.customer_id LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.id = ?', [query_int('id')]);
    if (!$rx) { render_error_page('Reçete bulunamadı', ''); }
    $ad = trim($rx['first_name'] . ' ' . $rx['last_name']);
    $title = 'Reçete ' . $ad;
    $yas = (int) $rx['birth_year'] > 1900 ? ((int) date('Y') - (int) $rx['birth_year']) . ' yaş' : '';

    echo dokum_ust('Reçete kartı', [['Sipariş', $rx['order_id'] ? order_no((int) $rx['order_id']) : '—'], ['Reçete', date_tr($rx['prescription_date'])], ['Basım', date('d.m.Y')]]);
    echo dokum_selam('Hasta', $ad, 'Atölye çalışma kartı' . ($yas !== '' ? ' · ' . e($yas) : '') . '. Ölçüler kesim ve montajdan önce reçeteyle karşılaştırılır.',
        isset(lens_designs()[$rx['lens_design']]) ? dokum_vurgu('Kullanım', lens_designs()[$rx['lens_design']], (string) ($rx['lens_type'] ?: ''), 'koyu')
            : ($rx['lens_type'] ? dokum_vurgu('Cam tipi', (string) $rx['lens_type'], '', 'koyu') : ''));
    echo dokum_bilgi([
        ['goz', 'Cam tipi', (string) $rx['lens_type']],
        ['kisi', 'Doktor', (string) $rx['doctor']],
        ['etiket', 'Toplam PD', ($rx['pd'] ?? '') !== '' ? $rx['pd'] . ' mm' : ''],
        ['takvim', 'Reçete tarihi', date_tr($rx['prescription_date'])],
    ]);
    ob_start();
    include dirname(__DIR__, 2) . '/app/partials/rx-table.php';
    echo '<div class="bolum">' . dokum_kutu('Ölçüler', (string) ob_get_clean(), '', 'Sağ (R) · Sol (L)') . '</div>';
    $not = $rx['prescription_note'] ? dokum_kutu('Not', '<p class="buyuk">' . nl2br(e($rx['prescription_note'])) . '</p>') : '';
    $kontrol = '<ul class="kontrol tek">' . implode('', array_map(static fn($m) => '<li>' . e($m) . '<span></span></li>', ['Cam teslim alındı', 'Eksen işaretlendi', 'Kesim', 'Montaj', 'PD / yükseklik kontrolü', 'Temizlik ve paket'])) . '</ul>';
    echo '<div class="iki">' . dokum_kutu('Atölye kontrol listesi', $kontrol, '', 'işaretleyip paraf atın') . ($not !== '' ? $not : dokum_kutu('Atölye notu', '<div class="cizgiler"><span></span><span></span><span></span><span></span></div>')) . '</div>';
    echo dokum_imzalar([['Hazırlayan (atölye)', '', 'Cam kesim ve montajı yapmıştır.'], ['Kalite kontrol', '', 'Ölçüleri reçeteyle karşılaştırmıştır.']]);
    echo dokum_son('<p>Atölye çalışma kartıdır; <b>hekim reçetesinin yerine geçmez</b>.</p>');

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
    $tipler = count(array_unique(array_column($list, 'lens_type')));
    $listeAdi = $tab === 'siparis' ? 'Sipariş verilenler' : 'Eksik camlar';

    echo dokum_ust('Depo cam talebi', [['Liste', $listeAdi], ['Tarih', date('d.m.Y H:i')], ['Toplam', $total . ' cam']]);
    echo dokum_selam($supplierName ? 'Tedarikçi' : 'Cam deposu', $supplierName ?: 'Cam talep listesi',
        $tab === 'siparis' ? 'Siparişi verilmiş, teslimi beklenen camlar. Liste müşteri bilgisi içermez.' : 'Aşağıdaki camların temin edilmesini rica ederiz. Liste <b>müşteri bilgisi içermez</b>.',
        dokum_vurgu('Toplam', $total . ' cam', count($list) . ' çeşit · ' . $tipler . ' cam tipi', 'acik'));
    $satir = '';
    foreach ($list as $i => $r) {
        $satir .= '<tr><td><span class="sira">' . ($i + 1) . '</span></td><td><b>' . e($r['lens_type']) . '</b>' . ($r['lens_value'] ? '<small>' . e($r['lens_value']) . '</small>' : '') . '</td>'
            . '<td class="mono">' . e($r['sph'] ?: 'PL') . '</td><td class="mono">' . e($r['cyl'] ?: '—') . '</td><td class="mono">' . e($r['axis'] ?: '—') . '</td><td class="mono">' . e($r['add_power'] ?: '—') . '</td>'
            . '<td class="num"><b>' . (int) $r['cnt'] . '</b></td></tr>';
    }
    if (!$list) { $satir = '<tr><td colspan="7" class="bos">Listede cam yok.</td></tr>'; }
    echo '<div class="bolum">' . dokum_kutu($listeAdi, '<table class="tablo"><thead><tr><th>#</th><th>Cam tipi</th><th>SPH</th><th>CYL</th><th>AKS</th><th>ADD</th><th class="num">Adet</th></tr></thead><tbody>' . $satir
        . '</tbody><tfoot><tr class="genel"><th colspan="6">Toplam</th><th class="num">' . $total . '</th></tr></tfoot></table>', 'uzun', count($list) . ' satır') . '</div>';
    echo dokum_imzalar([
        ['Hazırlayan yetkili', (string) (current_user()['full_name'] ?? ''), 'İşbu listeyi düzenlemiştir.'],
        ['Depo onayı', '', 'Bu listeye göre sipariş verilmiştir.'],
    ]);
    echo dokum_son('<p>Depo / tedarikçi siparişi içindir; <b>müşteri bilgisi içermez</b>.</p>');

} elseif ($type === 'supplier' && is_super()) {
    $sp = row('SELECT * FROM suppliers WHERE id = ?', [query_int('id')]);
    if (!$sp) { render_error_page('Tedarikçi bulunamadı', ''); }
    $from = valid_date(query('from')) ? query('from') : '';
    $until = valid_date(query('until')) ? query('until') : '';
    $invoices = rows('SELECT * FROM supplier_invoices WHERE supplier_id = ? ORDER BY invoice_date, id', [$sp['id']]);
    $payments = rows('SELECT * FROM supplier_payments WHERE supplier_id = ? ORDER BY created_at, id', [$sp['id']]);
    $ledger = [];
    foreach ($invoices as $inv) {
        $ledger[] = ['date' => $inv['invoice_date'], 'sort' => $inv['invoice_date'] . ' 00:00:01', 'amount' => (float) $inv['amount'], 'label' => 'Fatura ' . $inv['invoice_no'], 'note' => $inv['note'], 'vade' => $inv['due_date'] ?? null];
    }
    foreach ($payments as $p) {
        $ledger[] = ['date' => substr($p['created_at'], 0, 10), 'sort' => $p['created_at'], 'amount' => -(float) $p['amount'], 'label' => 'Ödeme · ' . (tedarik_odeme_yontemleri()[$p['method']] ?? $p['method']), 'note' => $p['note'], 'vade' => null];
    }
    usort($ledger, static fn($a, $b) => $a['sort'] <=> $b['sort']);
    $balance = 0.0;
    foreach ($ledger as &$r) { $balance += $r['amount']; $r['balance'] = $balance; }
    unset($r);
    $devir = null;
    if ($from !== '' || $until !== '') {
        $once = array_values(array_filter($ledger, static fn($r) => $from !== '' && $r['date'] < $from));
        $devir = $once ? (float) end($once)['balance'] : 0.0;
        $ledger = array_values(array_filter($ledger, static function ($r) use ($from, $until) {
            if ($from !== '' && $r['date'] < $from) { return false; }
            if ($until !== '' && $r['date'] > $until) { return false; }
            return true;
        }));
    }
    $donemFatura = array_sum(array_map(static fn($r) => max(0, $r['amount']), $ledger));
    $donemOdeme = array_sum(array_map(static fn($r) => max(0, -$r['amount']), $ledger));
    $donemMetni = $from || $until ? date_tr($from ?: null) . ' – ' . date_tr($until ?: null) : 'Tüm zamanlar';
    $title = 'Cari ekstre ' . $sp['name'];

    echo dokum_ust('Cari hesap ekstresi', [['Dönem', $donemMetni], ['Hareket', (string) count($ledger)], ['Tarih', date('d.m.Y')]]);
    echo dokum_selam('Tedarikçi', (string) $sp['name'],
        e(trim(($sp['contact_name'] ? 'Yetkili ' . $sp['contact_name'] : '') . ($sp['phone'] ? ' · ' . phone_display($sp['phone']) : ''), ' ·')) . ($sp['contact_name'] || $sp['phone'] ? '<br>' : '') . 'İç kayıtlarımıza göre cari hesap hareketleriniz aşağıdadır.',
        dokum_vurgu('Güncel bakiye', money($balance), $balance > 0.009 ? 'borcumuz' : ($balance < -0.009 ? 'alacağımız' : 'hesap kapalı')));
    echo dokum_bilgi([
        ['belge', 'Dönem faturaları', money($donemFatura)],
        ['cuzdan', 'Dönem ödemeleri', money($donemOdeme)],
        ['takvim', 'Dönem', $donemMetni],
        ['tel', 'İletişim', trim((string) ($sp['email'] ?: phone_display($sp['phone'])))],
    ]);
    $satir = '';
    if ($devir !== null && $from !== '') {
        $satir .= '<tr><td>' . e(date_tr($from)) . '</td><td><b>Devir</b><small>dönem öncesi bakiye</small></td><td></td><td class="num"></td><td class="num"></td><td class="num"><b>' . e(money($devir)) . '</b></td></tr>';
    }
    foreach ($ledger as $r) {
        $satir .= '<tr><td>' . e(date_tr($r['date'])) . '</td><td><b>' . e($r['label']) . '</b>' . ($r['vade'] ? '<small>Vade ' . e(date_tr($r['vade'])) . '</small>' : '') . '</td><td>' . e($r['note'] ?: '—') . '</td>'
            . '<td class="num arti">' . ($r['amount'] > 0 ? e(money($r['amount'])) : '') . '</td><td class="num eksi">' . ($r['amount'] < 0 ? e(money(-$r['amount'])) : '') . '</td>'
            . '<td class="num"><b>' . e(money($r['balance'])) . '</b></td></tr>';
    }
    if (!$ledger) { $satir .= '<tr><td colspan="6" class="bos">Bu dönemde hareket yok.</td></tr>'; }
    echo '<div class="bolum">' . dokum_kutu('Hesap hareketleri', '<table class="tablo"><thead><tr><th>Tarih</th><th>Hareket</th><th>Not</th><th class="num">Fatura</th><th class="num">Ödeme</th><th class="num">Bakiye</th></tr></thead><tbody>' . $satir . '</tbody>'
        . '<tfoot><tr><th colspan="3">Dönem toplamı</th><th class="num">' . e(money($donemFatura)) . '</th><th class="num">' . e(money($donemOdeme)) . '</th><th class="num"></th></tr>'
        . '<tr class="genel"><th colspan="5">Güncel bakiye' . ($balance > 0.009 ? ' (borcumuz)' : ($balance < -0.009 ? ' (alacağımız)' : '')) . '</th><th class="num">' . e(money($balance)) . '</th></tr></tfoot></table>', 'uzun') . '</div>';
    echo dokum_imzalar([
        ['Yetkilimiz', (string) (current_user()['full_name'] ?? ''), 'İşbu ekstreyi düzenlemiştir.'],
        ['Tedarikçi yetkilisi', (string) ($sp['contact_name'] ?? ''), 'Ekstre karşılaştırılmış ve mutabık kalınmıştır.'],
    ]);
    echo dokum_son('<p>Bu ekstre iç kayıtlarımıza göre hazırlanmıştır; <b>tedarikçi ekstresiyle karşılaştırma</b> (mutabakat) için kullanılır.</p>');

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
    $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    $net = $totalIn + $totalRevenues - $totalExpenses;

    echo dokum_ust('Kasa dökümü', [['Gün', date_tr($date)], ['Hazırlanma', date('d.m.Y H:i')], ['Hazırlayan', (string) (current_user()['full_name'] ?? '—')]]);
    if ($count) {
        $ok = abs((float) $count['difference']) < 0.01;
        $rozet = dokum_rozet($ok ? 'ok' : 'uyari', $ok ? 'onay' : 'cuzdan', $ok ? 'Kasa kapatıldı · mutabık' : 'Kasa kapatıldı · fark var');
        $aciklama = 'Sayılan ' . e(money($count['counted_amount'])) . ' · beklenen ' . e(money($count['expected_amount'])) . ($ok ? '' : ' · fark <b>' . (((float) $count['difference'] > 0) ? '+' : '') . e(money($count['difference'])) . '</b>');
    } else {
        $rozet = dokum_rozet('gri', 'kasa', 'Kasa henüz kapatılmadı');
        $aciklama = 'Bu gün için kasa sayımı yapılmamıştır.';
    }
    echo dokum_selam('Gün sonu · ' . $gunler[(int) date('w', strtotime($date))], date_tr($date), $aciklama, dokum_vurgu('Toplam tahsilat', money($totalIn), array_sum(array_map(static fn($m) => (int) $m['cnt'], $byMethod)) . ' işlem'), $rozet);
    echo dokum_bilgi([
        ['para', 'Tahsilat', money($totalIn)],
        ['arti', 'Diğer gelir', money($totalRevenues)],
        ['eksi', 'Gider', money($totalExpenses)],
        ['kasa', 'Gün neti', money($net), 'tüm yöntemler'],
    ]);
    $tablo3 = static function (array $baslik, array $satirlar, string $bos, ?array $toplam = null): string {
        $h = '<table class="tablo"><thead><tr><th>' . e($baslik[0]) . '</th><th' . ($baslik[1] === 'Adet' ? ' class="num"' : '') . '>' . e($baslik[1]) . '</th><th class="num">' . e($baslik[2]) . '</th></tr></thead><tbody>';
        foreach ($satirlar as $s) {
            $h .= '<tr><td>' . e($s[0]) . '</td><td' . ($baslik[1] === 'Adet' ? ' class="num"' : '') . '>' . e($s[1]) . '</td><td class="num">' . e($s[2]) . '</td></tr>';
        }
        if (!$satirlar) { $h .= '<tr><td colspan="3" class="bos">' . e($bos) . '</td></tr>'; }
        $h .= '</tbody>';
        if ($toplam) { $h .= '<tfoot><tr><th colspan="2">' . e($toplam[0]) . '</th><th class="num">' . e($toplam[1]) . '</th></tr></tfoot>'; }
        return $h . '</table>';
    };
    echo '<div class="iki">'
        . dokum_kutu('Yöntem bazında tahsilat', $tablo3(['Yöntem', 'Adet', 'Tutar'], array_map(static fn($m) => [payment_methods()[$m['method']] ?? $m['method'], (string) (int) $m['cnt'], money($m['total'])], $byMethod), 'Tahsilat yok.', ['Toplam', money($totalIn)]))
        . dokum_kutu('Personel bazında', $tablo3(['Personel', 'Adet', 'Tutar'], array_map(static fn($s) => [$s['name'], (string) (int) $s['cnt'], money($s['total'])], $byStaff), 'Kayıt yok.'))
        . '</div><div class="iki">'
        . dokum_kutu('Diğer gelirler', $tablo3(['Açıklama', 'Yöntem', 'Tutar'], array_map(static fn($r) => [$r['description'], payment_methods()[$r['method']] ?? $r['method'], money($r['amount'])], $revenues), 'Ek gelir kaydı yok.', ['Toplam', money($totalRevenues)]))
        . dokum_kutu('Günlük giderler', $tablo3(['Açıklama', 'Yöntem', 'Tutar'], array_map(static fn($x) => [$x['description'], payment_methods()[$x['method']] ?? $x['method'], money($x['amount'])], $expenses), 'Gider kaydı yok.', ['Toplam', money($totalExpenses)]))
        . '</div>';
    if ($count && $count['note']) {
        echo '<div class="bolum">' . dokum_kutu('Sayım notu', '<p>' . e((string) $count['note']) . '</p>') . '</div>';
    }
    echo dokum_imzalar([
        ['Sayan', (string) ($count['by_name'] ?? ''), 'Kasayı sayan personel.'],
        ['Onaylayan', '', 'İşletme yetkilisi.'],
    ]);
    echo dokum_son('<p>Bu döküm iç kayıt amaçlıdır, <b>fatura yerine geçmez</b>. Tahsilata hızlı satışlar dahildir.</p>');

} elseif ($type === 'report' && is_super()) {
    $from = valid_date(query('from')) ? query('from') : date('Y-m-01');
    $to = valid_date(query('to')) ? query('to') : date('Y-m-d');
    $range = [$from . ' 00:00:00', date('Y-m-d', strtotime($to . ' +1 day')) . ' 00:00:00'];
    $s = row("SELECT COUNT(*) AS orders, COALESCE(SUM(o.total_amount), 0) AS turnover, COALESCE(SUM(o.total_amount - COALESCE(p.paid, 0)), 0) AS bal FROM orders o " . PAID_JOIN . " WHERE o.order_stage <> 'iptal' AND o.created_at >= ? AND o.created_at < ?", $range);
    $collected = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE created_at >= ? AND created_at < ?', $range);
    $lens = rows("SELECT COALESCE(NULLIF(lens_type, ''), 'Seçilmemiş') AS name, COUNT(*) AS cnt, SUM(total_amount) AS total FROM orders WHERE order_stage <> 'iptal' AND created_at >= ? AND created_at < ? GROUP BY name ORDER BY cnt DESC", $range);
    $stagesList = rows('SELECT order_stage AS name, COUNT(*) AS cnt FROM orders WHERE created_at >= ? AND created_at < ? GROUP BY order_stage ORDER BY cnt DESC', $range);
    $title = 'Rapor ' . date_tr($from) . ' – ' . date_tr($to);
    $gun = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
    $ortalama = (int) $s['orders'] > 0 ? (float) $s['turnover'] / (int) $s['orders'] : 0.0;

    echo dokum_ust('Dönem raporu', [['Başlangıç', date_tr($from)], ['Bitiş', date_tr($to)], ['Hazırlanma', date('d.m.Y H:i')]]);
    echo dokum_selam('Dönem · ' . $gun . ' gün', date_tr($from) . ' – ' . date_tr($to), 'Siparişler (iptal hariç), tahsilat ve sipariş durumlarının dönem özeti.', dokum_vurgu('Ciro', money($s['turnover']), 'Sipariş başına ' . money($ortalama)));
    echo dokum_bilgi([
        ['belge', 'Sipariş', (string) (int) $s['orders'], 'iptal hariç'],
        ['grafik', 'Ciro', money($s['turnover'])],
        ['cuzdan', 'Dönem tahsilatı', money($collected)],
        ['saat', 'Siparişlerin kalanı', money($s['bal'])],
    ]);
    $enCok = max(1, ...array_map(static fn($l) => (int) $l['cnt'], $lens ?: [['cnt' => 1]]));
    $satir = '';
    foreach ($lens as $l) {
        $satir .= '<tr><td><b>' . e($l['name']) . '</b><span class="cubuk"><i style="width:' . round((int) $l['cnt'] / $enCok * 100) . '%"></i></span></td><td class="num">' . (int) $l['cnt'] . '</td><td class="num">' . e(money($l['total'])) . '</td></tr>';
    }
    if (!$lens) { $satir = '<tr><td colspan="3" class="bos">Kayıt yok.</td></tr>'; }
    $camTablo = '<table class="tablo"><thead><tr><th>Cam tipi</th><th class="num">Adet</th><th class="num">Tutar</th></tr></thead><tbody>' . $satir . '</tbody></table>';
    $enCokD = max(1, ...array_map(static fn($l) => (int) $l['cnt'], $stagesList ?: [['cnt' => 1]]));
    $satir = '';
    foreach ($stagesList as $st) {
        $satir .= '<tr><td><b>' . e(stage_label($st['name'])) . '</b><span class="cubuk"><i style="width:' . round((int) $st['cnt'] / $enCokD * 100) . '%"></i></span></td><td class="num">' . (int) $st['cnt'] . '</td></tr>';
    }
    if (!$stagesList) { $satir = '<tr><td colspan="2" class="bos">Kayıt yok.</td></tr>'; }
    $durumTablo = '<table class="tablo"><thead><tr><th>Durum</th><th class="num">Adet</th></tr></thead><tbody>' . $satir . '</tbody></table>';
    echo '<div class="iki gen-dar">' . dokum_kutu('Cam tipi', $camTablo, 'uzun') . dokum_kutu('Sipariş durumları', $durumTablo, 'uzun') . '</div>';
    echo dokum_son('<p>Ciro: dönemde açılan siparişlerin tutarı (iptal hariç). Tahsilat: dönemde alınan ödemeler (önceki siparişler dahil).</p>');

} else {
    ob_end_clean();
    render_error_page('Belge bulunamadı', 'Yazdırılacak belge türü geçersiz veya yetkiniz yok.');
}
$body = ob_get_clean();

echo dokum_sayfa_bas($title, $ekCss) . $body . dokum_sayfa_son();
