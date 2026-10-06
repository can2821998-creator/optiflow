<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

/* 4.22.0 — Tüm yazdırılan belgeler (dökümler): app/dokum.php yapı taşları + assets/dokum.css, iki tasarım
   ("Gözlük" / "Bilet"; Ayarlar › Genel ya da belge ekranındaki düğme). Her tür kendi gövdesini üretir:
   baş (dokum_bas) → gösteri parçası (mercekler, halka, eğri…) → kutular → imzalar → alt bilgi.
   Katalog teklifi: app/partials/teklif-dokum.php. */

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
    $asama = (string) $o['order_stage'];
    $isDelivered = $asama === 'teslim_edildi';
    $isCancelled = $asama === 'iptal';
    $hasDebt = $isDelivered && $tutarlar && (float) $o['balance'] > 0.009;
    $custName = trim($o['c_first'] . ' ' . $o['c_last']);
    $tur = transaction_type_label($o['transaction_type']);

    if ($isCancelled) {
        $durum = ['gri', 'carpi', 'Sipariş iptal edildi', 'Bu sipariş kaydı iptal edilmiştir; herhangi bir işlem gerektirmez.'];
    } elseif ($hasDebt) {
        $durum = ['uyari', 'cuzdan', 'Teslim edildi · bakiyesi var', 'Gözlüğünüz teslim edildi. Kalan tutarı ' . ($o['balance_promise_date'] ? date_tr($o['balance_promise_date']) . ' tarihine kadar' : 'en kısa sürede') . ' ödemenizi rica ederiz.'];
    } elseif ($isDelivered) {
        $durum = ['ok', 'onay', 'Teslim edildi', 'Gözlüğünüz eksiksiz teslim edildi. Bizi tercih ettiğiniz için teşekkür ederiz.'];
    } elseif ($asama === 'hazirlandi') {
        $durum = ['ok', 'goz', 'Teslime hazır', 'Siparişiniz hazır; mağazamızdan teslim alabilirsiniz.'];
    } elseif ($asama === 'atolyede') {
        $durum = ['bilgi', 'ayar', 'Atölyede hazırlanıyor', 'Siparişiniz atölyemizde özenle hazırlanıyor.'];
    } else {
        $durum = ['', 'belge', 'Sipariş alındı', 'Siparişiniz kayıt altına alındı; en kısa sürede hazırlanmaya başlanacak.'];
    }

    // Aşama çizgisi: alındı → atölye → hazır → teslim
    $sira = ['alindi' => 0, 'atolyede' => 1, 'hazirlandi' => 2, 'teslim_edildi' => 3];
    $simdi = $sira[$asama] ?? 0;
    $ad = static fn($id) => $id ? staff_name((int) $id) : '';
    $yol = [];
    if (!$isCancelled) {
        $adimlar = [
            ['Sipariş alındı', trim(date('d.m', strtotime((string) $o['created_at'])) . ' · ' . (string) ($o['created_by_name'] ?? ''), ' ·')],
            ['Atölyede', ($n = $ad($o['assigned_to'])) && $n !== '—' ? $n : ''],
            ['Hazır', ($n = $ad($o['qc_by'])) && $n !== '—' ? 'kontrol · ' . $n : ($o['promised_date'] && !$isDelivered ? 'söz ' . date_tr($o['promised_date']) : '')],
            ['Teslim edildi', $isDelivered ? date_tr($o['delivered_at']) : ($o['promised_date'] ? 'söz ' . date_tr($o['promised_date']) : '')],
        ];
        foreach ($adimlar as $i => $a) {
            $yol[] = [$a[0], $a[1], $i < $simdi ? 'bitti' : ($i === $simdi ? ($i === 3 ? 'bitti' : 'simdi') : 'bekliyor')];
        }
    }

    // Başlıktaki karekod: teslimden önce takip sayfası, teslimde gözlük bakım kartı.
    $karekod = null;
    if (!$isCancelled && !$isDelivered && ($tu = order_track_url((int) $o['id'])) !== '' && track_page_exists()) {
        $karekod = [$tu, 'Okutun, siparişinizi takip edin'];
    } elseif ($isDelivered && is_file(APP_ROOT . '/bakim.php') && is_file(APP_ROOT . '/app/pages/bakim.php')) {
        $karekod = [musteri_url('bakim.php'), 'Okutun, gözlüğünüzün bakım kartı açılsın'];
    }

    $title = $tur . ' fişi ' . order_no((int) $o['id']);
    $tel = phone_display($o['c_phone']);
    if ($o['transaction_type'] === 'tamir') {
        $alanlar = [['cerceve', 'Ürün', (string) $o['frame_info']], ['ayar', 'Yapılan işlem', service_type_label($o['service_type'])],
            ['cuzdan', 'Ücret', (int) $o['is_free'] ? 'Ücretsiz' : ($tutarlar ? money($o['total_amount']) : '')], ['tel', 'Telefon', $tel]];
    } elseif ($o['transaction_type'] === 'gozluk') {
        $alanlar = [['cerceve', 'Çerçeve', (string) $o['frame_info']], ['goz', 'Cam', (string) $o['lens_type']], ['tel', 'Telefon', $tel]];
    } else {
        $alanlar = [['etiket', 'Açıklama', (string) $o['frame_info']], ['tel', 'Telefon', $tel]];
    }
    echo dokum_bas([
        'etiket' => $tur . ' fişi', 'no' => order_no((int) $o['id']), 'tarih' => date_tr($o['created_at']),
        'kucuk' => 'Sayın', 'baslik' => $custName, 'rozet' => [$durum[0], $durum[1], $durum[2]], 'metin' => e($durum[3]),
        'rota' => [['Sipariş', date('d.m', strtotime((string) $o['created_at'])), date('Y', strtotime((string) $o['created_at']))],
                   [$isDelivered ? 'Teslim' : 'Söz verilen', $isDelivered ? date('d.m', strtotime((string) $o['delivered_at'])) : ($o['promised_date'] ? date('d.m', strtotime((string) $o['promised_date'])) : '—'), $isDelivered ? 'teslim edildi' : $durum[2]]],
        'alanlar' => $alanlar, 'yol' => $yol,
        'qr' => $karekod,
    ]);

    if ($rx) {
        echo '<section class="bolum">' . dokum_baslik('Gözlüğünüzün ölçüleri', trim('Reçete ' . date_tr($rx['prescription_date']) . (($d = lens_designs()[$rx['lens_design']] ?? '') !== '' ? ' · ' . $d : '') . ($rx['doctor'] ? ' · ' . $rx['doctor'] : ''))) . dokum_recete($rx) . '</section>';
    }

    $kareler = [];
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
    if ($tutarlar && !$isCancelled) {
        $hs = [['Sipariş tutarı', money($o['total_amount']), '', date_tr($o['created_at'])]];
        if ((float) $o['sgk_amount'] > 0) {
            $hs[] = ['SGK katkısı', '−' . money($o['sgk_amount']), 'eksi', 'tahmini'];
        }
        foreach ($payments as $p) {
            $hs[] = [date_tr($p['created_at']) . ' · ' . (payment_methods()[$p['method']] ?? $p['method']), '−' . money($p['amount']), 'eksi', (string) ($p['note'] ?? '')];
        }
        $odenen = array_sum(array_map(static fn($p) => (float) $p['amount'], $payments));
        $hs[] = ['Ödenen toplam', money($odenen), 'ara'];
        if ($hasDebt) {
            $buyuk = dokum_buyuk('Kalan bakiye', money($o['balance']), 'Gözlüğünüz teslim edildi. Kalan tutarı söz verilen tarihe kadar ödemenizi rica ederiz.', $o['balance_promise_date'] ? 'Ödeme sözü ' . date_tr($o['balance_promise_date']) : 'Ödeme sözü belirtilmedi');
        } elseif ((float) $o['balance'] <= 0.009) {
            $buyuk = dokum_buyuk('Ödeme durumu', 'Tamamı ödendi', 'Sipariş tutarının tamamı tahsil edilmiştir. Teşekkür ederiz.', money($o['total_amount']), 'ok');
        } else {
            $buyuk = dokum_buyuk('Kalan tutar', money($o['balance']), 'Kalan tutar teslimde tahsil edilir.', $o['promised_date'] ? 'Teslim sözü ' . date_tr($o['promised_date']) : '');
        }
        echo '<div class="iki">' . '<section class="bolum">' . dokum_baslik('Ödeme') . dokum_hesap($hs) . '</section><div>' . $buyuk . dokum_kareler($kareler) . '</div></div>';
    } else {
        echo dokum_kareler($kareler);
    }


    if (!$isCancelled) {
        $personel = $isDelivered ? staff_name($o['delivered_by']) : (string) ($o['created_by_name'] ?: '');
        $beyan = $hasDebt ? 'Ürünü teslim aldım; kalan bakiyeyi belirtilen tarihe kadar ödeyeceğimi kabul ederim.'
            : ($isDelivered ? 'Ürünü eksiksiz ve hasarsız teslim aldım.' : 'Sipariş bilgilerini inceledim ve onaylıyorum.');
        echo dokum_imzalar([
            [$isDelivered ? 'Teslim eden' : 'Siparişi alan', $personel === '—' ? '' : $personel, 'İşbu belgeyi düzenlemiştir.'],
            ['Müşteri', $custName, $beyan],
        ]);
    }
    echo dokum_son('<p>Bu fiş işlem takibi içindir, <b>fatura yerine geçmez</b>.</p>');

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
    $odemeler = array_map(static fn($od) => [payment_methods()[$od['method']] ?? $od['method'], (float) $od['amount'], money($od['amount'])], $s['odemeler']);

    echo dokum_bas([
        'etiket' => 'SATIŞ FİŞİ', 'no' => dokum_no((int) $s['id'], 'S'), 'tarih' => date_tr($s['created_at'], true),
        'kucuk' => $mus !== '' ? 'Sayın' : 'Perakende satış', 'baslik' => $mus !== '' ? $mus : 'Değerli müşterimiz',
        'rozet' => $iptal ? ['gri', 'carpi', 'İptal edildi'] : ['ok', 'onay', 'Ödendi'],
        'metin' => 'Alışverişiniz için teşekkür ederiz. Ürünlerinizi bu fişle birlikte saklayın.',
        'vurgu' => ['Toplam', money($s['toplam']), count($s['kalemler']) . ' kalem · ' . $adet . ' adet', $iptal ? 'koyu' : ''],
        'alanlar' => [['kisi', 'Satışı yapan', (string) $s['personel']], ['saat', 'Tarih', date_tr($s['created_at'], true)], ['kutu', 'Ürün', $adet . ' adet']],
    ]);
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
    echo '<section class="bolum">' . dokum_baslik('Ürünler', count($s['kalemler']) . ' kalem') . '<table class="tablo"><thead><tr><th>#</th><th>Ürün</th><th class="num">Adet</th><th class="num">Birim</th><th class="num">Tutar</th></tr></thead><tbody>' . $satir . '</tbody><tfoot>' . $alt . '</tfoot></table></section>';
    $sag = $s['not_metni'] ? dokum_kutu('Not', '<p>' . e((string) $s['not_metni']) . '</p>')
        : dokum_kutu('Değişim ve iade', '<p>Değişim ve iade için ürünü ambalajı ve bu fişle birlikte 14 gün içinde mağazamıza getirin. Ambalajı açılmış hijyen ürünleri (solüsyon, damla) iade alınmaz.</p>');
    echo '<div class="iki"><section class="kutu"><h3>Ödeme</h3>' . ($odemeler ? dokum_halka(array_map(static fn($od) => [$od[0], $od[1], $od[2]], $odemeler), money($s['toplam']), count($odemeler) . ' ödeme') : '<p>—</p>') . '</section>' . $sag . '</div>';
    echo dokum_son('<p>Bu fiş işlem takibi içindir, <b>fatura yerine geçmez</b>.</p>');

} elseif ($type === 'payment') {
    $p = row('SELECT p.*, o.id AS order_id, o.transaction_type, o.total_amount, o.sgk_amount, c.first_name, c.last_name, c.phone, u.full_name AS by_name
              FROM payments p JOIN orders o ON o.id = p.order_id JOIN customers c ON c.id = o.customer_id
              LEFT JOIN user_accounts u ON u.id = p.created_by
              WHERE p.id = ?', [query_int('id')]);
    if (!$p || !can_see_amounts()) { render_error_page('Makbuz bulunamadı', ''); }
    $gecmis = rows('SELECT * FROM payments WHERE order_id = ? AND (created_at < ? OR (created_at = ? AND id <= ?)) ORDER BY created_at, id',
        [$p['order_id'], $p['created_at'], $p['created_at'], $p['id']]);
    $net = (float) $p['total_amount'] - (float) $p['sgk_amount'];
    $odenen = array_sum(array_map(static fn($x) => (float) $x['amount'], $gecmis));
    $balanceAfter = $net - $odenen;
    $custName = trim($p['first_name'] . ' ' . $p['last_name']);
    $yontem = payment_methods()[$p['method']] ?? $p['method'];
    $title = 'Tahsilat makbuzu ' . order_no((int) $p['order_id']);

    echo dokum_bas([
        'etiket' => 'Tahsilat makbuzu', 'no' => dokum_no((int) $p['id'], 'M'), 'tarih' => date_tr($p['created_at'], true),
        'kucuk' => 'Sayın', 'baslik' => $custName, 'rozet' => ['ok', 'onay', 'Ödeme alındı'],
        'metin' => e(transaction_type_label($p['transaction_type'])) . ' (' . e(order_no((int) $p['order_id'])) . ') için aşağıdaki tutar tarafımızca <b>tahsil edilmiştir</b>. Teşekkür ederiz.',
        'vurgu' => ['Tahsil edilen', money($p['amount']), $yontem, 'ok'],
        'alanlar' => [[$p['method'] === 'kart' ? 'kart' : 'para', 'Ödeme yöntemi', $yontem], ['belge', 'Sipariş', order_no((int) $p['order_id'])], ['tel', 'Telefon', phone_display($p['phone'])],
            ['cuzdan', 'Kalan bakiye', money(max(0, $balanceAfter))]],
    ]);
    $hs = [['Sipariş tutarı', money($p['total_amount'])]];
    if ((float) $p['sgk_amount'] > 0) {
        $hs[] = ['SGK katkısı', '−' . money($p['sgk_amount']), 'eksi', 'tahmini'];
    }
    foreach ($gecmis as $x) {
        $hs[] = [((int) $x['id'] === (int) $p['id'] ? 'Bu makbuz · ' : '') . date_tr($x['created_at']) . ' · ' . (payment_methods()[$x['method']] ?? $x['method']), '−' . money($x['amount']), 'eksi', (string) ($x['note'] ?? '')];
    }
    $hs[] = ['Bu ödemeden sonra kalan', money(max(0, $balanceAfter)), 'ara'];
    $oran = $net > 0 ? min(1, $odenen / $net) : 1;
    echo '<div class="iki"><section class="bolum">' . dokum_baslik('Hesap özeti') . dokum_hesap($hs) . '<p class="yazi">Yalnız: <b>' . e(tutar_yaziyla((float) $p['amount'])) . '</b></p></section>'
        . '<section class="kutu"><h3>Ödeme ilerlemesi<small>' . e(order_no((int) $p['order_id'])) . '</small></h3>'
        . dokum_halka([['Ödenen', $odenen, money($odenen)], ['Kalan', max(0, $balanceAfter), money(max(0, $balanceAfter))]], '%' . (int) round($oran * 100), 'ödendi') . '</section></div>';
    echo dokum_imzalar([
        ['Tahsilatı yapan', (string) ($p['by_name'] ?? ''), 'İşbu makbuzu düzenlemiştir.'],
        ['Ödemeyi yapan', $custName, 'Belirtilen tutarı ödediğimi beyan ederim.'],
    ]);
    echo dokum_son('<p>Bu makbuz işlem takibi içindir, <b>fatura yerine geçmez</b>.</p>');

} elseif ($type === 'quote') {
    $qt = row('SELECT * FROM quotes WHERE id = ?', [query_int('id')]);
    if (!$qt) { render_error_page('Teklif bulunamadı', ''); }
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

    echo dokum_bas([
        'etiket' => 'Fiyat teklifi', 'no' => dokum_no((int) $qt['id']), 'tarih' => date('d.m.Y', $olusturma),
        'kucuk' => 'Sayın', 'baslik' => (string) $qt['customer_name'], 'rozet' => ['', 'saat', 'Geçerlilik ' . $gecerlilik],
        'metin' => count($options) > 1 ? 'Talebiniz için hazırladığımız ' . count($options) . ' seçeneği yan yana karşılaştırabilirsiniz.' : 'Talebiniz için hazırladığımız teklif aşağıdadır.',
        'rota' => [['Teklif', date('d.m', $olusturma), date('Y', $olusturma)], ['Geçerli', substr($gecerlilik, 0, 5), 'son gün']],
        'vurgu' => count($fiyatlar) > 1 ? ['Başlayan fiyatlarla', money(min($fiyatlar)), count($options) . ' seçenek', 'acik'] : null,
    ]);
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
        echo '<div class="bolum">' . dokum_kutu('Not', '<p>' . nl2br(e((string) $qt['note'])) . '</p>') . '</div>';
    }
    echo dokum_son('<p>Bu teklif <b>' . e($gecerlilik) . '</b> tarihine kadar geçerlidir ve bilgilendirme amaçlıdır; fiyatlar stok ve tedarikçi koşullarına göre değişebilir.</p>',
        '<div class="imza"><small>Hazırlayan</small><b>' . e($hazirlayan !== '' ? $hazirlayan : $shop) . '</b><span>' . e($shop) . '</span></div>');

} elseif ($type === 'rx') {
    $rx = row('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph, c.first_name, c.last_name, c.birth_year FROM prescription_records r JOIN customers c ON c.id = r.customer_id LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.id = ?', [query_int('id')]);
    if (!$rx) { render_error_page('Reçete bulunamadı', ''); }
    $ad = trim($rx['first_name'] . ' ' . $rx['last_name']);
    $title = 'Reçete ' . $ad;
    $yas = (int) $rx['birth_year'] > 1900 ? ((int) date('Y') - (int) $rx['birth_year']) . ' yaş' : '';
    $kullanim = lens_designs()[$rx['lens_design']] ?? '';

    echo dokum_bas([
        'etiket' => 'Reçete kartı', 'no' => $rx['order_id'] ? order_no((int) $rx['order_id']) : dokum_no((int) $rx['id'], 'R'), 'tarih' => date_tr($rx['prescription_date']),
        'kucuk' => 'Hasta', 'baslik' => $ad, 'rozet' => ['bilgi', 'ayar', 'Atölye çalışma kartı'],
        'metin' => e(trim(($yas !== '' ? $yas . ' · ' : '') . 'Ölçüler kesim ve montajdan önce reçeteyle karşılaştırılır.')),
        'alanlar' => [['goz', 'Cam tipi', (string) $rx['lens_type']], ['etiket', 'Kullanım', $kullanim], ['kisi', 'Doktor', (string) $rx['doctor']], ['takvim', 'Reçete', date_tr($rx['prescription_date'])]],
    ]);
    echo '<section class="bolum">' . dokum_baslik('Ölçüler', 'TABO aks gösterimi') . dokum_recete($rx) . '</section>';
    $not = $rx['prescription_note'] ? dokum_kutu('Not', '<p class="buyuk-metin">' . nl2br(e($rx['prescription_note'])) . '</p>') : dokum_kutu('Atölye notu', '<div class="cizgiler"><span></span><span></span><span></span><span></span></div>');
    $kontrol = '<ul class="kontrol tek">' . implode('', array_map(static fn($m) => '<li>' . e($m) . '<span></span></li>', ['Cam teslim alındı', 'Eksen işaretlendi', 'Kesim', 'Montaj', 'PD / yükseklik kontrolü', 'Temizlik ve paket'])) . '</ul>';
    echo '<div class="iki">' . dokum_kutu('Atölye kontrol listesi', $kontrol, '', 'işaretleyip paraf atın') . $not . '</div>';
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
    $tipler = [];
    foreach ($list as $r) { $tipler[$r['lens_type']] = ($tipler[$r['lens_type']] ?? 0) + (int) $r['cnt']; }
    arsort($tipler);
    $listeAdi = $tab === 'siparis' ? 'Sipariş verilenler' : 'Eksik camlar';

    echo dokum_bas([
        'etiket' => 'Depo cam talebi', 'no' => dokum_no((int) date('md'), 'D'), 'tarih' => date('d.m.Y H:i'),
        'kucuk' => $supplierName ? 'Tedarikçi' : 'Cam deposu', 'baslik' => $supplierName ?: 'Cam talep listesi',
        'rozet' => ['', 'kutu', $listeAdi], 'metin' => 'Aşağıdaki camların temin edilmesini rica ederiz. Liste <b>müşteri bilgisi içermez</b>.',
        'vurgu' => ['Toplam', $total . ' cam', count($list) . ' çeşit', 'acik'],
    ]);
    if ($tipler) {
        echo '<div class="iki dar-gen"><section class="kutu"><h3>Cam tipine göre</h3>' . dokum_halka(array_map(static fn($k, $v) => [$k, $v, $v . ' cam'], array_keys(array_slice($tipler, 0, 6, true)), array_slice($tipler, 0, 6)), (string) $total, 'cam') . '</section>';
    } else {
        echo '<div class="bolum">';
    }
    $satir = '';
    foreach ($list as $i => $r) {
        $satir .= '<tr><td><span class="sira">' . ($i + 1) . '</span></td><td><b>' . e($r['lens_type']) . '</b>' . ($r['lens_value'] ? '<small>' . e($r['lens_value']) . '</small>' : '') . '</td>'
            . '<td class="mono">' . e($r['sph'] ?: 'PL') . '</td><td class="mono">' . e($r['cyl'] ?: '—') . '</td><td class="mono">' . e($r['axis'] ?: '—') . '</td><td class="mono">' . e($r['add_power'] ?: '—') . '</td>'
            . '<td class="num"><b>' . (int) $r['cnt'] . '</b></td></tr>';
    }
    if (!$list) { $satir = '<tr><td colspan="7" class="bos">Listede cam yok.</td></tr>'; }
    echo '<section class="bolum">' . dokum_baslik($listeAdi, count($list) . ' satır') . '<table class="tablo"><thead><tr><th>#</th><th>Cam tipi</th><th>SPH</th><th>CYL</th><th>AKS</th><th>ADD</th><th class="num">Adet</th></tr></thead><tbody>' . $satir
        . '</tbody><tfoot><tr class="genel"><th colspan="6">Toplam</th><th class="num">' . $total . '</th></tr></tfoot></table></section></div>';
    echo dokum_imzalar([
        ['Hazırlayan', (string) (current_user()['full_name'] ?? ''), 'İşbu listeyi düzenlemiştir.'],
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
    $bakiyeAdi = $balance > 0.009 ? 'borcumuz' : ($balance < -0.009 ? 'alacağımız' : 'hesap kapalı');
    $title = 'Cari ekstre ' . $sp['name'];

    echo dokum_bas([
        'etiket' => 'Cari hesap ekstresi', 'no' => dokum_no((int) $sp['id'], 'T'), 'tarih' => date('d.m.Y'),
        'kucuk' => 'Tedarikçi', 'baslik' => (string) $sp['name'], 'rozet' => ['', 'takvim', $donemMetni],
        'metin' => e(trim(($sp['contact_name'] ? 'Yetkili ' . $sp['contact_name'] : '') . ($sp['phone'] ? ' · ' . phone_display($sp['phone']) : ''), ' ·')),
        'vurgu' => ['Güncel bakiye', money($balance), $bakiyeAdi],
        'alanlar' => [['belge', 'Dönem faturaları', money($donemFatura)], ['cuzdan', 'Dönem ödemeleri', money($donemOdeme)], ['grafik', 'Hareket', (string) count($ledger)], ['tel', 'İletişim', (string) ($sp['email'] ?: phone_display($sp['phone']))]],
    ]);
    if (count($ledger) > 1) {
        echo '<section class="bolum">' . dokum_baslik('Bakiye seyri', 'her hareket sonrası') . dokum_egri(array_map(static fn($r) => [date_tr($r['date']), $r['balance']], $ledger), 'son bakiye ' . money($balance)) . '</section>';
    }
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
    echo '<section class="bolum">' . dokum_baslik('Hesap hareketleri', count($ledger) . ' hareket') . '<table class="tablo"><thead><tr><th>Tarih</th><th>Hareket</th><th>Not</th><th class="num">Fatura</th><th class="num">Ödeme</th><th class="num">Bakiye</th></tr></thead><tbody>' . $satir . '</tbody>'
        . '<tfoot><tr><th colspan="3">Dönem toplamı</th><th class="num">' . e(money($donemFatura)) . '</th><th class="num">' . e(money($donemOdeme)) . '</th><th class="num"></th></tr>'
        . '<tr class="genel"><th colspan="5">Güncel bakiye (' . e($bakiyeAdi) . ')</th><th class="num">' . e(money($balance)) . '</th></tr></tfoot></table></section>';
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
    $islem = array_sum(array_map(static fn($m) => (int) $m['cnt'], $byMethod));
    if ($count) {
        $ok = abs((float) $count['difference']) < 0.01;
        $rozet = [$ok ? 'ok' : 'uyari', $ok ? 'onay' : 'cuzdan', $ok ? 'Kasa kapatıldı · mutabık' : 'Kasa kapatıldı · fark var'];
        $aciklama = 'Sayılan ' . e(money($count['counted_amount'])) . ' · beklenen ' . e(money($count['expected_amount'])) . ($ok ? '' : ' · fark <b>' . (((float) $count['difference'] > 0) ? '+' : '') . e(money($count['difference'])) . '</b>');
    } else {
        $rozet = ['gri', 'kasa', 'Kasa henüz kapatılmadı'];
        $aciklama = 'Bu gün için kasa sayımı yapılmamıştır.';
    }

    echo dokum_bas([
        'etiket' => 'Kasa dökümü', 'no' => date('d.m', strtotime($date)), 'tarih' => $gunler[(int) date('w', strtotime($date))],
        'kucuk' => 'Gün sonu · ' . $gunler[(int) date('w', strtotime($date))], 'baslik' => date_tr($date), 'rozet' => $rozet, 'metin' => $aciklama,
        'vurgu' => ['Toplam tahsilat', money($totalIn), $islem . ' işlem'],
        'alanlar' => [['para', 'Tahsilat', money($totalIn)], ['arti', 'Diğer gelir', money($totalRevenues)], ['eksi', 'Gider', money($totalExpenses)], ['kasa', 'Gün neti', money($net)]],
    ]);
    $tablo3 = static function (array $baslik, array $satirlar, string $bos, ?array $toplam = null): string {
        $sayi = $baslik[1] === 'Adet';
        $h = '<table class="tablo"><thead><tr><th>' . e($baslik[0]) . '</th><th' . ($sayi ? ' class="num"' : '') . '>' . e($baslik[1]) . '</th><th class="num">' . e($baslik[2]) . '</th></tr></thead><tbody>';
        foreach ($satirlar as $s) {
            $h .= '<tr><td>' . e($s[0]) . '</td><td' . ($sayi ? ' class="num"' : '') . '>' . e($s[1]) . '</td><td class="num">' . e($s[2]) . '</td></tr>';
        }
        if (!$satirlar) { $h .= '<tr><td colspan="3" class="bos">' . e($bos) . '</td></tr>'; }
        $h .= '</tbody>';
        if ($toplam) { $h .= '<tfoot><tr><th colspan="2">' . e($toplam[0]) . '</th><th class="num">' . e($toplam[1]) . '</th></tr></tfoot>'; }
        return $h . '</table>';
    };
    echo '<div class="iki"><section class="kutu"><h3>Yöntemlere göre tahsilat</h3>'
        . ($byMethod ? dokum_halka(array_map(static fn($m) => [payment_methods()[$m['method']] ?? $m['method'], (float) $m['total'], money($m['total'])], $byMethod), money($totalIn), $islem . ' işlem') : '<p>Tahsilat yok.</p>') . '</section>'
        . dokum_kutu('Personele göre', $tablo3(['Personel', 'Adet', 'Tutar'], array_map(static fn($s) => [$s['name'], (string) (int) $s['cnt'], money($s['total'])], $byStaff), 'Kayıt yok.'))
        . '</div><div class="iki">'
        . dokum_kutu('Diğer gelirler', $tablo3(['Açıklama', 'Yöntem', 'Tutar'], array_map(static fn($r) => [$r['description'], payment_methods()[$r['method']] ?? $r['method'], money($r['amount'])], $revenues), 'Ek gelir kaydı yok.', ['Toplam', money($totalRevenues)]))
        . dokum_kutu('Günlük giderler', $tablo3(['Açıklama', 'Yöntem', 'Tutar'], array_map(static fn($x) => [$x['description'], payment_methods()[$x['method']] ?? $x['method'], money($x['amount'])], $expenses), 'Gider kaydı yok.', ['Toplam', money($totalExpenses)]))
        . '</div>';
    if ($count && $count['note']) {
        echo '<div class="bolum">' . dokum_kutu('Sayım notu', '<p>' . e((string) $count['note']) . '</p>') . '</div>';
    }
    echo dokum_imzalar([['Sayan', (string) ($count['by_name'] ?? ''), 'Kasayı sayan personel.'], ['Onaylayan', '', 'İşletme yetkilisi.']]);
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

    echo dokum_bas([
        'etiket' => 'Dönem raporu', 'no' => (string) $gun, 'tarih' => date('d.m.Y H:i'),
        'kucuk' => $gun . ' günlük dönem', 'baslik' => date_tr($from) . ' – ' . date_tr($to), 'rozet' => ['', 'grafik', (int) $s['orders'] . ' sipariş'],
        'metin' => 'Siparişler (iptal hariç), tahsilat ve sipariş durumlarının dönem özeti.',
        'rota' => [['Başlangıç', date('d.m', strtotime($from)), date('Y', strtotime($from))], ['Bitiş', date('d.m', strtotime($to)), date('Y', strtotime($to))]],
        'vurgu' => ['Ciro', money($s['turnover']), 'sipariş başına ' . money($ortalama)],
        'alanlar' => [['belge', 'Sipariş', (string) (int) $s['orders']], ['grafik', 'Ciro', money($s['turnover'])], ['cuzdan', 'Tahsilat', money($collected)], ['saat', 'Siparişlerin kalanı', money($s['bal'])]],
    ]);
    $enCok = max(1, ...array_map(static fn($l) => (int) $l['cnt'], $lens ?: [['cnt' => 1]]));
    $satir = '';
    foreach ($lens as $l) {
        $satir .= '<tr><td><b>' . e($l['name']) . '</b><span class="cubuk"><i style="width:' . round((int) $l['cnt'] / $enCok * 100) . '%"></i></span></td><td class="num">' . (int) $l['cnt'] . '</td><td class="num">' . e(money($l['total'])) . '</td></tr>';
    }
    if (!$lens) { $satir = '<tr><td colspan="3" class="bos">Kayıt yok.</td></tr>'; }
    echo '<div class="iki gen-dar"><section class="bolum">' . dokum_baslik('Cam tipi') . '<table class="tablo"><thead><tr><th>Cam tipi</th><th class="num">Adet</th><th class="num">Tutar</th></tr></thead><tbody>' . $satir . '</tbody></table></section>'
        . '<section class="kutu"><h3>Sipariş durumları</h3>' . ($stagesList ? dokum_halka(array_map(static fn($st) => [stage_label($st['name']), (int) $st['cnt'], (int) $st['cnt'] . ' sipariş'], array_slice($stagesList, 0, 6)), (string) array_sum(array_map(static fn($st) => (int) $st['cnt'], $stagesList)), 'sipariş') : '<p>Kayıt yok.</p>') . '</section></div>';
    echo dokum_son('<p>Ciro: dönemde açılan siparişlerin tutarı (iptal hariç). Tahsilat: dönemde alınan ödemeler (önceki siparişler dahil).</p>');

} else {
    ob_end_clean();
    render_error_page('Belge bulunamadı', 'Yazdırılacak belge türü geçersiz veya yetkiniz yok.');
}
$body = ob_get_clean();

echo dokum_sayfa_bas($title, $ekCss) . $body . dokum_sayfa_son();
