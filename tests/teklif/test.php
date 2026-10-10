<?php
/* 4.21.0 Katalogdan teklif (app/teklif.php): hesap, iskonto yetkisi, doğrulama, müşteri kaydı, siparişe ön dolum. */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';

// app/domain.php'den (testte yüklenmez) gereken üçü — aynı tanımlar
function product_tiers(): array { return ['ekonomik' => 'Ekonomik', 'dengeli' => 'Dengeli', 'premium' => 'Premium']; }
function lens_designs(): array { return ['tek_odak_uzak' => 'Tek odak · uzak', 'tek_odak_yakin' => 'Tek odak · yakın', 'ayri_uzak_yakin' => 'Uzak + yakın', 'progressive' => 'Progressive', 'ofis' => 'Ofis', 'bifokal' => 'Bifokal']; }
function sgk_katki_tahmini(array $rx): float { return in_array($rx['lens_design'] ?? '', ['ayri_uzak_yakin', 'progressive', 'ofis', 'bifokal'], true) ? 320.0 : 160.0; }

$personel = ['id' => 2, 'full_name' => 'Personel', 'role' => 'personel'];
$patron = ['id' => 1, 'full_name' => 'Patron', 'role' => 'super'];

echo "1) Hesap kuralı: önce SGK, sonra iskonto (kullanıcı kararı)\n";
$h = teklif_hesapla(8000, 2000, 1200, 10);
esit(10000.0, $h['ara'], 'ara = cam + çerçeve');
esit(8800.0, $h['kalan'], 'kalan = ara − SGK');
esit(880.0, $h['iskonto'], 'iskonto kalan tutara uygulanır');
esit(7920.0, $h['odenecek'], 'ödenecek = (10.000 − 1.200) × 0,90');
esit(500.0, teklif_hesapla(300, 200, 900, 0)['sgk'], 'SGK payı ara toplamı aşamaz');
esit(0.0, teklif_hesapla(300, 200, 900, 50)['odenecek'], 'SGK her şeyi karşılarsa ödenecek 0');
esit(1234.57, teklif_hesapla(1234.567, 0, 0, 0)['ara'], 'kuruşa yuvarlanır');
esit(100.0, teklif_hesapla(100, 0, 0, 150)['oran'], 'oran en çok 100');

echo "2) İskonto yetkisi\n";
$GLOBALS['__kullanici'] = $personel;
esit(10.0, teklif_iskonto_siniri(), 'personel: varsayılan %10');
setting_set('teklif_iskonto_max', '15');
esit(15.0, teklif_iskonto_siniri(), 'personel: Ayarlar\'daki sınır');
$GLOBALS['__kullanici'] = $patron;
esit(100.0, teklif_iskonto_siniri(), 'süper yetkili sınırsız');

echo "3) Katalog ve çerçeve\n";
$ted = insert('suppliers', ['name' => 'Toptancı', 'created_at' => uts_simdi(), 'updated_at' => uts_simdi()]);
$cam1 = insert('lens_products', ['brand' => 'Essilor', 'name' => 'Eyezen', 'design' => 'tek_odak', 'tier' => 'dengeli', 'lens_index' => '1.60', 'coating' => 'Blue (mavi ışık)', 'price' => 4200]);
$cam2 = insert('lens_products', ['brand' => 'Zeiss', 'name' => 'SmartLife Progressive', 'design' => 'progressive', 'tier' => 'premium', 'lens_index' => '1.67', 'coating' => 'Antirefle', 'price' => 12500]);
$fiyatsiz = insert('lens_products', ['brand' => 'Hoya', 'name' => 'Özel', 'design' => 'tek_odak', 'tier' => 'ekonomik', 'price' => null]);
$pasif = insert('lens_products', ['brand' => 'Eski', 'name' => 'Cam', 'design' => 'tek_odak', 'tier' => 'ekonomik', 'price' => 100, 'is_active' => 0]);
$cer = insert('frame_items', ['brand' => 'Ray-Ban', 'model' => 'RB5154', 'color' => 'Siyah', 'qty' => 2, 'price' => 3500, 'supplier_id' => $ted, 'created_at' => uts_simdi(), 'updated_at' => uts_simdi()]);
esit(3, count(teklif_cam_urunleri()), 'pasif ürün teklifte listelenmez');
$et = teklif_cam_etiketi(row('SELECT * FROM lens_products WHERE id = ?', [$cam1]));
esit('Essilor Eyezen', $et['ad'], 'cam adı = marka + ad');
esit('Tek odak · İndeks 1.60 · Blue (mavi ışık)', $et['ozellik'], 'özellik satırı');
esit('progressive', teklif_kullanim_onerisi('progressive'), 'progressive cam → SGK uzak+yakın');
// 4.21.0 katalog detayları: app/domain.php'deki listeler (testte yüklenmez) — aynı tanımlar
function lens_odak_tipleri(): array { return ['tek_odak' => 'Tek odak', 'tek_odak_destekli' => 'Tek odak · yakın destekli (yorgunluk)', 'miyopi_kontrol' => 'Tek odak · miyopi kontrol', 'progressive' => 'Progressive (çok odaklı)', 'ofis' => 'Ofis / ara mesafe', 'bifokal' => 'Bifokal']; }
function lens_hammaddeleri(): array { return ['organik' => 'Organik (CR-39)', 'polikarbonat' => 'Polikarbonat', 'mr8' => 'MR-8 (inceltilmiş)']; }
function lens_yuzeyleri(): array { return ['kuresel' => 'Küresel', 'asferik' => 'Asferik']; }
$detayli = ['brand' => 'Essilor', 'name' => 'Eyezen Start', 'design' => 'tek_odak_destekli', 'hammadde' => 'mr8', 'lens_index' => '1.60', 'yuzey' => 'asferik',
    'coating' => 'Antirefle, Blue (mavi ışık)', 'note' => null];
esit('Tek odak · yakın destekli (yorgunluk) · MR-8 (inceltilmiş) · İndeks 1.60 · Asferik · Antirefle, Blue (mavi ışık)', teklif_cam_etiketi($detayli)['ozellik'], 'detaylı cam: odak · hammadde · indeks · yüzey · kaplamalar');
esit('tek_odak_uzak', teklif_kullanim_onerisi('tek_odak_destekli'), 'yakın destekli cam SGK\'da tek odak');
esit('Nikon Presio First', teklif_cam_etiketi(['brand' => 'Nikon', 'name' => 'Nikon Presio First', 'design' => 'progressive', 'lens_index' => null, 'coating' => null, 'note' => null])['ad'], 'ad markayla başlıyorsa marka tekrarlanmaz');

echo "4) Doğrulamalar\n";
$GLOBALS['__kullanici'] = $personel;
$temel = ['first_name' => 'ayşe', 'last_name' => 'YILMAZ', 'phone' => '0532 111 22 33', 'cerceve_tur' => 'stok', 'frame_item_id' => (string) $cer,
    'urun' => [1 => (string) $cam1, 2 => (string) $cam2], 'cam_fiyat' => [1 => '', 2 => ''], 'secenek_ad' => [1 => '', 2 => 'En iyisi'],
    'sgk_var' => '1', 'lens_design' => 'tek_odak_uzak', 'sgk_amount' => '', 'discount_rate' => '10'];
hata_bekle(fn() => teklif_kaydet(['first_name' => '', 'last_name' => ''] + $temel, $personel), 'müşteri zorunlu', 'Müşteri seçin');
hata_bekle(fn() => teklif_kaydet(['phone' => '12'] + $temel, $personel), 'geçersiz telefon', 'Telefon');
hata_bekle(fn() => teklif_kaydet(['customer_id' => '999'] + $temel, $personel), 'olmayan müşteri', 'bulunamadı');
hata_bekle(fn() => teklif_kaydet(['frame_item_id' => ''] + $temel, $personel), 'stok seçilmedi', 'Stoktan');
hata_bekle(fn() => teklif_kaydet(['cerceve_tur' => 'elle', 'frame_desc' => ''] + $temel, $personel), 'elle çerçeve açıklaması', 'açıklamasını');
hata_bekle(fn() => teklif_kaydet(['cerceve_tur' => 'x'] + $temel, $personel), 'geçersiz çerçeve türü', 'geçersiz');
hata_bekle(fn() => teklif_kaydet(['urun' => []] + $temel, $personel), 'en az bir cam', 'en az bir cam');
hata_bekle(fn() => teklif_kaydet(['urun' => [1 => (string) $pasif]] + $temel, $personel), 'pasif cam seçilemez', 'pasif');
hata_bekle(fn() => teklif_kaydet(['urun' => [1 => (string) $fiyatsiz]] + $temel, $personel), 'fiyatsız ürün fiyat ister', 'fiyat yok');
hata_bekle(fn() => teklif_kaydet(['cam_fiyat' => [1 => 'abc']] + $temel, $personel), 'geçersiz fiyat', 'geçersiz');
hata_bekle(fn() => teklif_kaydet(['lens_design' => 'yok'] + $temel, $personel), 'kullanım şekli', 'Kullanım');
hata_bekle(fn() => teklif_kaydet(['discount_rate' => '16'] + $temel, $personel), 'personel sınırı aşılamaz (%15)', 'en çok %15');
hata_bekle(fn() => teklif_kaydet(['discount_rate' => '-5'] + $temel, $personel), 'negatif iskonto', '0 ile 100');
hata_bekle(fn() => teklif_kaydet(['discount_rate' => 'on'] + $temel, $personel), 'sayı olmayan iskonto', '0 ile 100');
esit(0, (int) scalar('SELECT COUNT(*) FROM quotes'), 'hatalı girişte kayıt yok');
esit(0, (int) scalar('SELECT COUNT(*) FROM customers'), 'hatalı girişte müşteri açılmaz');

echo "5) Kayıt: yeni müşteri + stok çerçeve + iki seçenek\n";
$id = teklif_kaydet($temel, $personel);
$q = row('SELECT * FROM quotes WHERE id = ?', [$id]);
$mus = row('SELECT * FROM customers');
esit('Ayşe Yılmaz', $mus['first_name'] . ' ' . $mus['last_name'], 'müşteri adı düzeltilerek kaydedildi');
esit('905321112233', $mus['phone'], 'telefon 90… biçiminde');
esit((int) $mus['id'], (int) $q['customer_id'], 'teklif müşteriye bağlı');
esit('katalog', $q['tip'], 'tip katalog');
esit(3500.0, (float) $q['frame_price'], 'çerçeve fiyatı stoktan');
esit('Ray-Ban · RB5154 · Siyah', $q['frame_desc'], 'çerçeve adı stoktan (frame_item_label)');
esit(160.0, (float) $q['sgk_amount'], 'SGK payı boşsa tahmin (tek odak uzak)');
esit(10.0, (float) $q['discount_rate'], 'iskonto oranı');
esit('Dengeli', $q['opt1_name'], 'başlık boşsa segment adı');
esit('En iyisi', $q['opt2_name'], 'elle başlık');
esit(4200.0, (float) $q['opt1_price'], 'cam fiyatı katalogdan');
esit($cam2, (int) $q['opt2_product_id'], 'ürün numarası saklandı');
esit(null, $q['opt3_name'], 'kullanılmayan seçenek boş');

echo "6) Fiyat kopyası: katalog değişse de teklif değişmez\n";
q('UPDATE lens_products SET price = 9999, name = ? WHERE id = ?', ['Değişti', $cam1]);
$sec = teklif_secenekleri(row('SELECT * FROM quotes WHERE id = ?', [$id]));
esit(2, count($sec), 'iki seçenek');
esit('Essilor Eyezen', $sec[0]['cam'], 'cam adı teklif anındaki');
esit(4200.0, $sec[0]['hesap']['cam'], 'cam fiyatı teklif anındaki');
esit(round((4200 + 3500 - 160) * 0.9, 2), $sec[0]['hesap']['odenecek'], 'seçenek 1 ödenecek');
esit(round((12500 + 3500 - 160) * 0.9, 2), $sec[1]['hesap']['odenecek'], 'seçenek 2 ödenecek');

echo "7) Aynı müşteri tekrar gelirse yeni kayıt açılmaz; elle çerçeve, elle SGK, SGK'sız\n";
$id2 = teklif_kaydet(['cerceve_tur' => 'elle', 'frame_desc' => 'Vogue VO5286', 'frame_price' => '2.750,50', 'sgk_amount' => '400', 'cam_fiyat' => [1 => '4.000', 2 => '']] + $temel, $personel);
esit(1, (int) scalar('SELECT COUNT(*) FROM customers'), 'aynı ad + telefon: müşteri tekrar açılmadı');
$q2 = row('SELECT * FROM quotes WHERE id = ?', [$id2]);
esit(2750.5, (float) $q2['frame_price'], 'elle çerçeve fiyatı (Türkçe biçim)');
esit(400.0, (float) $q2['sgk_amount'], 'elle SGK payı');
esit(4000.0, (float) $q2['opt1_price'], 'elle cam fiyatı katalogdakini ezer');
$id3 = teklif_kaydet(['customer_id' => (string) $mus['id'], 'cerceve_tur' => 'kendi', 'sgk_var' => '', 'discount_rate' => ''] + $temel, $personel);
$q3 = row('SELECT * FROM quotes WHERE id = ?', [$id3]);
esit(0.0, (float) $q3['sgk_amount'], 'SGK\'sız');
esit(0.0, (float) $q3['frame_price'], 'müşterinin kendi çerçevesi: 0');
esit(null, $q3['frame_item_id'], 'stok çerçevesi yok');
$GLOBALS['__kullanici'] = $patron;
$id4 = teklif_kaydet(['customer_id' => (string) $mus['id'], 'discount_rate' => '%40'] + $temel, $patron);
esit(40.0, (float) row('SELECT discount_rate FROM quotes WHERE id = ?', [$id4])['discount_rate'], 'süper yetkili %40 girebilir ("%" işaretiyle)');

echo "8) Siparişe ön dolum (seçenek 2)\n";
$od = teklif_siparis_on_dolum(row('SELECT * FROM quotes WHERE id = ?', [$id]), 2);
$beklenenOdenecek = round((12500 + 3500 - 160) * 0.9, 2);
esit(round(160 + $beklenenOdenecek, 2), $od['total_amount'], 'sipariş tutarı = SGK + ödenecek');
esit(160.0, $od['sgk_amount'], 'siparişin SGK payı');
esit($beklenenOdenecek, round($od['total_amount'] - $od['sgk_amount'], 2), 'bakiye = müşteriye söylenen tutar');
esit($cer, $od['frame_item_id'], 'stok çerçevesi siparişe');
ok(str_contains($od['notes'], 'Zeiss SmartLife Progressive') && str_contains($od['notes'], 'iskonto %10'), 'not: cam ve hesap dökümü');
esit(null, teklif_siparis_on_dolum(row('SELECT * FROM quotes WHERE id = ?', [$id]), 3), 'olmayan seçenek: ön dolum yok');
$serbest = insert('quotes', ['customer_name' => 'Eski', 'opt1_name' => 'İyi', 'opt1_price' => 5000]);
esit(null, teklif_siparis_on_dolum(row('SELECT * FROM quotes WHERE id = ?', [$serbest]), 1), 'eski (serbest) teklif ön dolum vermez');
$sv = teklif_secenekleri(row('SELECT * FROM quotes WHERE id = ?', [$serbest]));
ok(!isset($sv[0]['hesap']) && $sv[0]['fiyat'] === 5000.0, 'eski teklif: fiyat = toplam, hesap yok');

echo "9) WhatsApp metni\n";
$wa = teklif_whatsapp_metni(row('SELECT * FROM quotes WHERE id = ?', [$id]), 'Örnek Optik');
ok(str_contains($wa, 'Merhaba Ayşe Yılmaz') && str_contains($wa, 'SGK (Medula) payı') && str_contains($wa, '*Ödenecek: ' . money($beklenenOdenecek) . '*') && str_ends_with($wa, 'Örnek Optik'), 'döküm metni');

echo "9b) Döküm (4.21.1): etiketler, sözlük, not ayrımı\n";
esit(['Tek odak · yakın destekli (yorgunluk)', 'MR-8 (inceltilmiş)', 'İndeks 1.60', 'Asferik', 'Antirefle', 'Blue (mavi ışık)'],
    teklif_ozellik_etiketleri('Tek odak · yakın destekli (yorgunluk) · MR-8 (inceltilmiş) · İndeks 1.60 · Asferik · Antirefle, Blue (mavi ışık)'),
    'birleşik odak adı bölünmez, kaplamalar ayrı etiket');
$sz = teklif_dokum_sozluk([['ozellik' => 'Progressive (çok odaklı) · İndeks 1.67 · Fotokromik'], ['ozellik' => 'Tek odak · Fotokromik']]);
esit(['Progressive (çok odaklı)', 'Fotokromik', 'Tek odak', 'Kırılma indeksi'], array_keys($sz), 'sözlük: yalnızca teklifte geçenler, tekrarsız, indeks açıklaması');
$notlu = insert('lens_products', ['brand' => 'Hoya', 'name' => 'Nulux', 'design' => 'tek_odak', 'tier' => 'premium', 'price' => 6000, 'note' => '3 gün teslim']);
$idN = teklif_kaydet(['customer_id' => (string) $mus['id'], 'urun' => [1 => (string) $notlu]] + $temel, $patron);
$sn = teklif_secenekleri(row('SELECT * FROM quotes WHERE id = ?', [$idN]))[0];
ok($sn['not'] === '3 gün teslim' && !str_contains($sn['ozellik'], 'teslim'), 'katalog notu ayrı satır, özellik etiketi sayılmaz');
$pr = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/print.php');
ok(str_contains($pr, "require dirname(__DIR__) . '/partials/teklif-dokum.php';"), 'katalog teklifi kendi döküm şablonuyla basılır');
$dk = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/dokum.css');
ok(str_contains($dk, 'print-color-adjust:exact') && str_contains($dk, '@page{size:A4;') && str_contains($dk, '@page :first{margin-top:0}'), 'döküm: A4 ve renkler arka plan ayarından bağımsız basılır');
$td = (string) file_get_contents(dirname(__DIR__, 2) . '/app/partials/teklif-dokum.php');
ok(str_contains($td, "dokum_sayfa_bas(") && str_contains($td, "'teklif-dokum.css'") && str_contains($td, 'dokum_bas(['), 'teklif dökümü ortak başlık/stil + teklife özel stil');

echo "11) Çoklu gözlük (4.26.0): uzak + yakın, gözlük başına SGK ve sipariş\n";
$GLOBALS['__kullanici'] = $personel;
$ikili = ['customer_id' => (string) $mus['id'], 'discount_rate' => '10', 'gozluk_ad' => '',
    'ek' => [2 => ['aktif' => '1', 'ad' => '', 'cerceve_tur' => 'elle', 'frame_desc' => 'Okuma çerçevesi', 'frame_price' => '1.500',
        'urun' => [1 => (string) $cam1], 'cam_fiyat' => [1 => '3.000'], 'secenek_ad' => [1 => ''], 'sgk_var' => '1', 'lens_design' => 'tek_odak_yakin', 'sgk_amount' => ''],
        3 => ['aktif' => '0', 'urun' => [1 => (string) $pasif]]]] + $temel;
$idG = teklif_kaydet($ikili, $personel);
$qG = row('SELECT * FROM quotes WHERE id = ?', [$idG]);
$gz = teklif_gozlukleri($qG);
esit(2, count($gz), 'iki gözlük (etkin olmayan 3. yok sayılır)');
esit('Uzak gözlük', $gz[0]['ad'], 'adı boş gözlük 1: Uzak gözlük');
esit('Yakın gözlük', $gz[1]['ad'], 'adı boş gözlük 2: Yakın gözlük');
esit('Uzak gözlük', $qG['gozluk_ad'], 'çoklu teklifte gözlük 1 adı saklanır');
esit(160.0, (float) $gz[1]['sgk_amount'], 'yakın gözlüğün kendi SGK payı (tahmin)');
esit(round((3000 + 1500 - 160) * 0.9, 2), $gz[1]['secenekler'][0]['hesap']['odenecek'], 'yakın gözlük: teklifin iskontosuyla hesap');
[$az, $cok] = teklif_toplam_aralik($gz);
$tut = static fn(array $g): array => array_map(static fn($x) => $x['hesap']['odenecek'], $g['secenekler']);
esit(round(min($tut($gz[0])) + min($tut($gz[1])), 2), $az, 'toplam: her gözlüğün en uygun seçeneği');
esit(round(max($tut($gz[0])) + max($tut($gz[1])), 2), $cok, 'toplam: her gözlüğün en kapsamlı seçeneği');
ok($az < $cok, 'toplam aralığı: en uygun < en kapsamlı');
$odY = teklif_siparis_on_dolum($qG, 1, 2);
esit('Okuma çerçevesi', $odY['frame_info'], 'yakın gözlüğün siparişi kendi çerçevesiyle');
esit('tek_odak_yakin', $odY['lens_design'], 'yakın gözlüğün kullanım şekli');
esit(round(160 + (3000 + 1500 - 160) * 0.9, 2), $odY['total_amount'], 'yakın gözlük sipariş tutarı = SGK + ödenecek');
ok(str_contains($odY['notes'], 'Yakın gözlük'), 'sipariş notunda gözlük adı');
esit(null, teklif_siparis_on_dolum($qG, 1, 3), 'olmayan gözlük: ön dolum yok');
esit('Uzak gözlük', teklif_siparis_on_dolum($qG, 1)['gozluk_ad'], 'gözlük verilmezse gözlük 1 (eski çağrı biçimi)');
$waG = teklif_whatsapp_metni($qG, 'Örnek Optik');
ok(str_contains($waG, 'YAKIN GÖZLÜK') && str_contains($waG, 'Toplam (2 gözlük)'), 'WhatsApp: gözlük başlıkları ve toplam');
hata_bekle(fn() => teklif_kaydet(['ek' => [2 => ['aktif' => '1', 'ad' => 'Güneş', 'cerceve_tur' => 'kendi', 'urun' => []]]] + $ikili, $personel), 'ek gözlükte cam zorunlu, hata gözlük adıyla', 'Güneş: Katalogdan en az bir cam');
esit(1, count(teklif_gozlukleri(row('SELECT * FROM quotes WHERE id = ?', [$id]))), 'eski tek gözlüklü teklif: 1 gözlük');
esit('Gözlük', teklif_gozlukleri(row('SELECT * FROM quotes WHERE id = ?', [$id]))[0]['ad'], 'tek gözlük adı: Gözlük');

echo "12) Teklif düzenleme\n";
$idF = teklif_kaydet($ikili, $personel);
$form = teklif_form_degerleri(row('SELECT * FROM quotes WHERE id = ?', [$idF]));
esit('stok', $form['cerceve_tur'], 'form: stok çerçeve');
esit('1', $form['ek'][2]['aktif'], 'form: ek gözlük etkin');
esit('elle', $form['ek'][2]['cerceve_tur'], 'form: ek gözlüğün elle çerçevesi');
esit('3.000,00', $form['ek'][2]['cam_fiyat'][1], 'form: fiyat Türkçe biçimde');
$onceF = teklif_gozlukleri(row('SELECT * FROM quotes WHERE id = ?', [$idF]));
teklif_kaydet($form, $personel, $idF);
$sonraF = teklif_gozlukleri(row('SELECT * FROM quotes WHERE id = ?', [$idF]));
esit(array_map(static fn($g) => [$g['ad'], $g['frame_desc'], (float) $g['sgk_amount'], array_map(static fn($s) => $s['hesap']['odenecek'], $g['secenekler'])], $onceF),
    array_map(static fn($g) => [$g['ad'], $g['frame_desc'], (float) $g['sgk_amount'], array_map(static fn($s) => $s['hesap']['odenecek'], $g['secenekler'])], $sonraF),
    'formdan değiştirmeden kaydetmek teklifi aynen korur');
$duz = teklif_kaydet(['discount_rate' => '5', 'gozluk_ad' => 'Bilgisayar gözlüğü', 'ek' => []] + $ikili, $personel, $idG);
esit($idG, $duz, 'aynı teklif güncellendi');
$qD = row('SELECT * FROM quotes WHERE id = ?', [$idG]);
esit(5.0, (float) $qD['discount_rate'], 'iskonto güncellendi');
esit('Bilgisayar gözlüğü', $qD['gozluk_ad'], 'elle yazılan gözlük adı');
esit(0, (int) scalar('SELECT COUNT(*) FROM quote_gozlukler WHERE quote_id = ?', [$idG]), 'kaldırılan ek gözlük silindi');
esit((int) $qG['created_by'], (int) $qD['created_by'], 'hazırlayan değişmez');
ok($qD['updated_at'] !== null && (int) $qD['updated_by'] === 2, 'güncelleyen ve zaman saklanır');
esit($idF, (int) scalar('SELECT MAX(id) FROM quotes'), 'düzenleme yeni teklif açmaz');
q('UPDATE quotes SET converted_order_id = 77 WHERE id = ?', [$idG]);
hata_bekle(fn() => teklif_kaydet($ikili, $personel, $idG), 'siparişe dönmüş teklif düzenlenemez', 'düzenlenemez');
$idH = teklif_kaydet($ikili, $personel);
q('UPDATE quote_gozlukler SET converted_order_id = 78 WHERE quote_id = ?', [$idH]);
hata_bekle(fn() => teklif_kaydet($ikili, $personel, $idH), 'ek gözlüğü siparişe dönen teklif de düzenlenemez', 'düzenlenemez');
hata_bekle(fn() => teklif_kaydet($ikili, $personel, $serbest), 'serbest teklif bu ekrandan düzenlenmez', 'bulunamadı');

echo "10) Kaynak denetimleri\n";
$kok = dirname(__DIR__, 2);
$mig = (string) file_get_contents($kok . '/app/migrations.php');
ok(preg_match('/const SCHEMA_VERSION = (3[2-9]|[4-9]\d);/', $mig) === 1 && str_contains($mig, 'migrate_v31_katalog_teklif') && str_contains($mig, 'migrate_v32_teklif_gozlukler'), 'göç v31 + v32 (çoklu gözlük)');
$dom = (string) file_get_contents($kok . '/app/domain.php');
ok(str_contains($dom, "FROM quotes WHERE converted_order_id = ? AND tip = 'katalog'"), 'reçete kaydı teklifteki SGK payını ezmez');
$on = (string) file_get_contents($kok . '/app/pages/order-new.php');
ok(str_contains($on, 'teklif_siparis_on_dolum($quote, $secenek, $gozlukNo)') && str_contains($on, "'quote_id' => \$quote ? (int) \$quote['id'] : null"), 'sipariş ekranı ön dolum + hata sonrası teklif bağlamı');
ok(str_contains($on, "update('quote_gozlukler', ['converted_order_id' => \$orderId]") && str_contains($on, 'name="gozluk"'), 'ek gözlük kendi siparişine bağlanır');
ok(str_contains($dom, 'FROM quote_gozlukler WHERE converted_order_id = ?'), 'ek gözlük siparişinde de teklifteki SGK payı korunur');
$ty = (string) file_get_contents($kok . '/app/pages/teklif-yeni.php');
ok(!preg_match('/<script>|onclick=|onchange=/', $ty) && str_contains($ty, "page_end(['teklif.js'])"), 'teklif ekranı: satır içi betik yok (CSP)');
$st = (string) file_get_contents($kok . '/app/pages/settings.php');
ok(str_contains($st, "!isset(lens_odak_tipleri()[\$data['design']])") && str_contains($st, "lens_hammaddeleri()[\$data['hammadde']]") && str_contains($st, "lens_yuzeyleri()[\$data['yuzey']]"), 'katalog kaydı: odak tipi, hammadde, yüzey doğrulanır');
ok(str_contains($st, "array_intersect(coatings(), array_filter((array) (\$_POST['kaplamalar'] ?? [])"), 'katalog kaydı: yalnızca listedeki kaplamalar, birden çok');
ok(str_contains($mig, "add_column('lens_products', 'hammadde'") && str_contains($mig, 'MODIFY coating VARCHAR(255)'), 'göç: hammadde, yüzey, uzun kaplama alanı');
$katalogSekme = substr($st, (int) strpos($st, "elseif (\$tab === 'katalog'):"), 9000);
ok(!str_contains($katalogSekme, 'onchange=') && str_contains($katalogSekme, 'data-auto-submit'), 'katalog filtresi CSP uyumlu (satır içi onchange yok)');
$rxs = (string) file_get_contents($kok . '/assets/rx.js');
ok(str_contains($rxs, 'p.coatings && p.coatings.length'), 'öneri asistanı çoklu kaplamayı puanlar');

bitir();
