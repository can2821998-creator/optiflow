<?php
/* 4.48.0 Tamir / hizmet fişi (app/hizmet.php) ve ortak yarım A4 fiş parçaları (app/fis.php). */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';
// app/domain.php ile aynı (ortam.php domain.php'yi yüklemez)
function service_types(): array
{
    return ['vida' => 'Vida sıkma / değişimi', 'burunluk' => 'Burunluk değişimi', 'ayar' => 'Ayar (kulak / köprü)', 'lehim' => 'Lehim / kaynak',
        'cam_degisim' => 'Cam değişimi', 'temizlik' => 'Temizlik / bakım', 'diger' => 'Diğer'];
}
function service_type_label(?string $key): string { return service_types()[(string) $key] ?? '—'; }
require_once dirname(__DIR__, 2) . '/app/hizmet.php';
require_once dirname(__DIR__, 2) . '/app/fis.php';

$p = db();
foreach (['status TEXT NULL', 'service_type TEXT NULL', 'is_free INTEGER NOT NULL DEFAULT 0', 'notes TEXT NULL', 'promised_date TEXT NULL', 'hizmet_detay TEXT NULL',
          'sales_person TEXT NULL', 'created_by INTEGER NULL', 'delivered_by INTEGER NULL', 'updated_at TEXT NULL', 'phone TEXT NULL'] as $s) {
    $p->exec('ALTER TABLE orders ADD COLUMN ' . $s);
}
$p->exec("CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, amount REAL NOT NULL, method TEXT NOT NULL DEFAULT 'nakit', note TEXT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL)");
$hata = static function (callable $f): string {
    try { $f(); } catch (DomainException $e) { return $e->getMessage(); }
    return '';
};
$taban = ['ad' => 'selin', 'soyad' => 'arslan', 'telefon' => '0532 777 88 99', 'islemler' => ['vida', 'burunluk'], 'gozluk' => 'Ray-Ban siyah', 'sikayet' => 'Sol sap düşüyor'];

echo "1) Doğrulama\n";
ok(str_contains($hata(static fn() => hizmet_kaydet(['islemler' => []] + $taban, 1)), 'En az bir işlem'), 'işlemsiz kayıt yok');
ok(str_contains($hata(static fn() => hizmet_kaydet(['islemler' => ['uydurma']] + $taban, 1)), 'En az bir işlem'), 'bilinmeyen işlem süzülür');
ok(str_contains($hata(static fn() => hizmet_kaydet(['telefon' => ''] + $taban, 1)), 'Telefon'), 'telefonsuz kayıt yok (hazır olunca aranır)');
ok(str_contains($hata(static fn() => hizmet_kaydet(['soyad' => ' '] + $taban, 1)), 'adı ve soyadı'), 'ad soyad şart');
ok(str_contains($hata(static fn() => hizmet_kaydet(['soz' => '2026-13-40'] + $taban, 1)), 'tarih'), 'geçersiz hazır tarihi');
ok(str_contains($hata(static fn() => hizmet_kaydet(['customer_id' => 999] + $taban, 1)), 'bulunamadı'), 'olmayan müşteri');
ok(str_contains($hata(static fn() => hizmet_kaydet(['ucret' => -5] + $taban, 1)), 'Ücret'), 'eksi ücret');

echo "2) Bırakılan tamir\n";
$soz = date('Y-m-d', strtotime('+1 day'));
$id = hizmet_kaydet(['durumlar' => ['cam_cizik', 'sap_gevsek', 'uydurma'], 'ucret' => 350, 'kapora' => 100, 'yontem' => 'kart', 'soz' => $soz] + $taban, 1, 'Deniz Yılmaz');
$o = row('SELECT * FROM orders WHERE id = ?', [$id]);
esit(['tamir', 'siparis_verildi', 'vida', 0, $soz], [$o['transaction_type'], $o['order_stage'], $o['service_type'], (int) $o['is_free'], $o['promised_date']], 'tamir siparişi, ilk işlem service_type');
esit(350.0, (float) $o['total_amount'], 'ücret');
esit(['islemler' => ['vida', 'burunluk'], 'durumlar' => ['cam_cizik', 'sap_gevsek'], 'hemen' => false], hizmet_detay($o), 'ayrıntı: çoklu işlem, durumlar (bilinmeyen süzülür)');
esit([100.0, 'kart', 'Kapora'], array_values(array_map(static fn($v) => is_numeric($v) ? (float) $v : $v, row('SELECT amount, method, note FROM payments WHERE order_id = ?', [$id]))), 'ön ödeme kapora olarak');
$c = row('SELECT * FROM customers WHERE id = ?', [(int) $o['customer_id']]);
esit(['Selin', 'Arslan', '905327778899'], [$c['first_name'], $c['last_name'], $c['phone']], 'yeni müşteri: ad düzgün, telefon tek biçim');
esit('Vida sıkma / değişimi + Burunluk değişimi', hizmet_islem_ozeti(hizmet_detay($o)['islemler']), 'işlem özeti');

echo "3) Hemen yapılan, aynı müşteri\n";
$id2 = hizmet_kaydet(['hemen' => true, 'ucret' => 50, 'islemler' => ['vida'], 'soz' => 'boş geçer'] + $taban, 1);
$o2 = row('SELECT * FROM orders WHERE id = ?', [$id2]);
esit((int) $o['customer_id'], (int) $o2['customer_id'], 'aynı ad + telefon: yeni müşteri açılmaz');
esit(['teslim_edildi', null], [$o2['order_stage'], $o2['promised_date']], 'hemen: teslim edildi, söz yok');
ok($o2['delivered_at'] === $o2['created_at'], 'teslim anı = kayıt anı');
esit([50.0, 'Hizmet'], [(float) scalar('SELECT amount FROM payments WHERE order_id = ?', [$id2]), scalar('SELECT note FROM payments WHERE order_id = ?', [$id2])], 'hemen: ücret tahsil edildi');
$id3 = hizmet_kaydet(['hemen' => true, 'ucretsiz' => true, 'ucret' => 80, 'customer_id' => (int) $o['customer_id']] + $taban, 1);
esit([0.0, 1, 0], [(float) scalar('SELECT total_amount FROM orders WHERE id = ?', [$id3]), (int) scalar('SELECT is_free FROM orders WHERE id = ?', [$id3]),
    (int) scalar('SELECT COUNT(*) FROM payments WHERE order_id = ?', [$id3])], 'ücretsiz: tutar 0, ödeme yok');
esit(['vida'], hizmet_detay(['hizmet_detay' => null, 'service_type' => 'vida'])['islemler'], 'eski tamir kaydı: service_type tek işlem');
$say = hizmet_sayilar();
esit([3, 2, 1, 0], [$say['bugun'], $say['hemen'], $say['bekleyen'], $say['hazir']], 'bant sayıları');

echo "4) Fiş parçaları\n";
esit('<section class="fis-kutu"><h2><i>01</i>Yapılacak</h2><p>x</p></section>', fis_kutu('01', 'Yapılacak', '<p>x</p>'), 'numaralı kutu');
ok(str_contains(fis_satirlar([['Müşteri', '<b>A</b>'], ['Boş', ''], ['<x>', 'y']]), '<dt>&lt;x&gt;</dt><dd>y</dd>') && !str_contains(fis_satirlar([['Boş', '']]), 'Boş'), 'satırlar: boş atlanır, etiket kaçışlı');
esit('<div class="fis-vurgu koyu"><small>Ücret</small><b>350,00 &lt;₺&gt;</b><i>alt</i></div>', fis_vurgu('Ücret', '350,00 <₺>', 'alt', 'koyu'), 'vurgu kaçışlı');
$is = fis_isaretler(HIZMET_DURUMLARI, ['cam_cizik']);
ok(substr_count($is, 'class="var"') === 1 && substr_count($is, 'class="yok"') === count(HIZMET_DURUMLARI) - 1 && str_contains($is, '✕'), 'işaretli durum ✕, diğerleri boş kare');
esit('', fis_karekod('', 'x'), 'adres yoksa karekod yok');

echo "5) Kaynak bağlantıları\n";
$kok = dirname(__DIR__, 2);
$mig = (string) file_get_contents($kok . '/app/migrations.php');
ok((bool) preg_match('/const SCHEMA_VERSION = (39|[4-9]\d);/', $mig) && str_contains($mig, 'migrate_v39_hizmet()') && str_contains($mig, "add_column('orders', 'hizmet_detay'"), 'göç v39');
$pr = (string) file_get_contents($kok . '/app/pages/print.php');
ok(str_contains($pr, "partials/hizmet-fisi.php") && str_contains($pr, "partials/makbuz-fisi.php") && str_contains($pr, "partials/satis-fisi.php"), 'print: hizmet, makbuz, satış yarım A4');
ok(str_contains($pr, "=== 'tamir') {\n    \$type = 'hizmet';"), 'tamir siparişinin fişi hizmet fişi');
$css = (string) file_get_contents($kok . '/assets/fis.css');
ok(str_contains($css, 'height: 148.5mm') && str_contains($css, '.fis-kesim { position: relative; height: 0; }') && str_contains($css, "size: <?= ") === false, 'yarım = 148,5 mm, kesme çizgisi yer kaplamaz');
ok(str_contains((string) file_get_contents($kok . '/app/fis.php'), "size: <?= \$yarim ? '210mm 148.5mm' : 'A4 portrait' ?>"), 'A4 dikey ya da kesilmiş yarım sayfa');
foreach (['app/fis.php', 'app/partials/hizmet-fisi.php', 'app/partials/makbuz-fisi.php', 'app/partials/satis-fisi.php', 'app/pages/tamir.php'] as $d) {
    ok(!preg_match('/<script(?![^>]*\bsrc=)|javascript:|\son(click|change|submit)=/i', (string) file_get_contents($kok . '/' . $d)), "CSP: satır içi betik yok — $d");
}
ok(str_contains((string) file_get_contents($kok . '/app/layout.php'), "['tamir', 'tamir.php', 'Tamir · hizmet'"), 'menü: Tezgâh › Tamir · hizmet');

bitir();
