<?php
/* 4.50.0 Şubeler (app/sube.php): yetki ve aktif şube, SQL süzgeçleri, başka şubede teslim, stok transferi, şube stoku,
   karşılaştırma; kayıtta telefon (app/merkez.php tenant_basvuru). */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';
$_SESSION = [];

// migrate_v40_subeler + v41 ile aynı yapı
$p = db();
$p->exec('CREATE TABLE subeler (id INTEGER PRIMARY KEY AUTOINCREMENT, ad TEXT NOT NULL, kisa TEXT NOT NULL, adres TEXT NULL, telefon TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1, sira INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL)');
$p->exec('CREATE TABLE kullanici_subeleri (user_id INTEGER NOT NULL, sube_id INTEGER NOT NULL, PRIMARY KEY (user_id, sube_id))');
$p->exec('CREATE TABLE urun_stok (urun_id INTEGER NOT NULL, sube_id INTEGER NOT NULL, stok INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (urun_id, sube_id))');
$p->exec("CREATE TABLE sube_transferleri (id INTEGER PRIMARY KEY AUTOINCREMENT, kaynak_sube_id INTEGER NOT NULL, hedef_sube_id INTEGER NOT NULL, durum TEXT NOT NULL DEFAULT 'yolda',
    not_metni TEXT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL, teslim_by INTEGER NULL, teslim_at TEXT NULL)");
$p->exec('CREATE TABLE sube_transfer_kalemleri (id INTEGER PRIMARY KEY AUTOINCREMENT, transfer_id INTEGER NOT NULL, tur TEXT NOT NULL, kaynak_id INTEGER NOT NULL,
    hedef_id INTEGER NULL, ad TEXT NOT NULL, adet INTEGER NOT NULL)');
$p->exec('ALTER TABLE satislar ADD COLUMN sube_id INTEGER NULL');
foreach (['sube_id INTEGER NULL', 'teslim_sube_id INTEGER NULL', 'tasima_durum TEXT NULL', 'tasima_at TEXT NULL', 'promised_date TEXT NULL', 'updated_at TEXT NULL'] as $s) {
    $p->exec('ALTER TABLE orders ADD COLUMN ' . $s);
}
$p->exec('DROP TABLE frame_items');
$p->exec("CREATE TABLE frame_items (id INTEGER PRIMARY KEY AUTOINCREMENT, brand TEXT NOT NULL, model TEXT NULL, color TEXT NULL, size TEXT NULL, barcode TEXT NULL,
    qty INTEGER NOT NULL DEFAULT 0, min_qty INTEGER NOT NULL DEFAULT 1, cost REAL NULL, price REAL NULL, supplier_id INTEGER NULL, shelf TEXT NULL, note TEXT NULL,
    is_active INTEGER NOT NULL DEFAULT 1, created_by INTEGER NULL, created_at TEXT, updated_at TEXT, sube_id INTEGER NULL, UNIQUE (barcode, sube_id))");
$p->exec("CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, amount REAL NOT NULL, method TEXT NOT NULL DEFAULT 'nakit', note TEXT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL)");
$p->exec('CREATE TABLE cash_counts (id INTEGER PRIMARY KEY AUTOINCREMENT, count_date TEXT NOT NULL, counted_amount REAL NOT NULL, sube_id INTEGER NULL, created_at TEXT NOT NULL)');
$p->exec("INSERT INTO subeler (id, ad, kisa, is_active, sira, created_at) VALUES (1, 'Merkez', 'MRK', 1, 1, '2026-10-24')");
$hata = static function (callable $f): string {
    try { $f(); } catch (DomainException $e) { return $e->getMessage(); }
    return '';
};
$yonetici = ['id' => 1, 'full_name' => 'Patron', 'role' => 'super_yetkili'];
$personel = ['id' => 2, 'full_name' => 'Personel', 'role' => 'personel'];
$GLOBALS['__kullanici'] = $yonetici;

echo "1) Şube kaydı\n";
$kdk = sube_kaydet(['ad' => 'Kadıköy', 'kisa' => 'k-d k!']);
esit('KDK', scalar('SELECT kisa FROM subeler WHERE id = ?', [$kdk]), 'kısa kod: harf/rakam, büyük, en çok 4');
$ist = sube_kaydet(['ad' => 'İstinye']);
esit('İST', scalar('SELECT kisa FROM subeler WHERE id = ?', [$ist]), 'kısa kod boşsa adın ilk üç harfi');
ok(str_contains($hata(static fn() => sube_kaydet(['ad' => 'Kadıköy'])), 'zaten var'), 'aynı ad iki kez olmaz');
ok(str_contains($hata(static fn() => sube_kaydet(['ad' => ' '])), 'gerekli'), 'ad gerekli');
// sube_listesi statik önbelleği bu istekte dolmamıştı: üç şube görünür
esit([1, $kdk, $ist], array_keys(sube_listesi()), 'şubeler sırayla');

echo "2) Yetki ve aktif şube\n";
esit([1, $kdk, $ist], sube_izinli($yonetici), 'yönetici bütün şubeleri görür');
esit([1], sube_izinli($personel), 'atanmamış personel ana şubeyi görür');
sube_personel_ata(2, [$kdk, 999, $kdk]);
esit([$kdk], sube_izinli($personel), 'atama: geçersiz ve tekrar süzülür');
esit(0, aktif_sube(), 'yönetici varsayılanı: tüm şubeler');
esit('', sube_kosul('o'), 'tüm şubeler: süzgeç yok');
ok(sube_sec($kdk) && aktif_sube() === $kdk, 'yönetici şube seçer');
esit(" AND o.sube_id = $kdk", sube_kosul('o'), 'süzgeç: tek şube');
esit(" AND (o.sube_id = $kdk OR o.teslim_sube_id = $kdk)", sube_kosul_siparis('o'), 'sipariş: açıldığı ya da teslim şubesi');
esit(" AND COALESCE(o.teslim_sube_id, o.sube_id) = $kdk", sube_kosul_hazir('o'), 'hazır gözlük: beklediği şube');
esit(['sube_id' => $kdk], sube_alan(), 'yeni kayıt aktif şubeye');
$GLOBALS['__kullanici'] = $personel;
$_SESSION['sube'] = 1;
esit($kdk, aktif_sube(), 'personel yetkisiz şubeye geçemez (kendi şubesine düşer)');
ok(!sube_sec(0) && !sube_sec(1), 'personel "tüm şubeler"i ve başka şubeyi seçemez');
ok(sube_erisim($kdk) && !sube_erisim(1), 'personel yalnız kendi şubesinin kaydını açar');
sube_personel_ata(2, [$kdk, $ist]);
$_SESSION['sube'] = 0;
esit($kdk, aktif_sube(), 'çok şubeli personel: "tüm şubeler" yok, ilk şubesi');
$GLOBALS['__kullanici'] = $yonetici;
$_SESSION['sube'] = 0;

echo "3) Başka şubede teslim\n";
$c = insert('customers', ['first_name' => 'Ayşe', 'last_name' => 'Yıldız', 'phone' => '905321112233']);
$o = insert('orders', ['customer_id' => $c, 'sube_id' => 1, 'order_stage' => 'atolyede', 'total_amount' => 1000, 'created_at' => '2026-10-24 10:00:00']);
sube_teslim_ayarla($o, $kdk);
esit([$kdk, null], array_values(array_map(static fn($v) => $v === null ? null : (int) $v, row('SELECT teslim_sube_id, tasima_durum FROM orders WHERE id = ?', [$o]) ?? [])), 'teslim şubesi yazıldı, henüz yolda değil');
ok(sube_tasima_baslat($o), 'hazır olunca yola çıkar');
esit('yolda', scalar('SELECT tasima_durum FROM orders WHERE id = ?', [$o]), 'yolda');
q("UPDATE orders SET order_stage = 'hazirlandi' WHERE id = ?", [$o]);
sube_sec($kdk);
esit([$o], array_map('intval', array_column(sube_yoldaki_gozlukler(), 'id')), 'hedef şubede "gelecek gözlükler"de');
sube_sec(1);
esit([], sube_yoldaki_gozlukler(), 'açılan şubede gelecekler listesinde değil');
$GLOBALS['__kullanici'] = $personel;
$_SESSION['sube'] = $ist;
ok(str_contains($hata(static fn() => sube_tasima_teslim($o, 2)), 'teslim şubesi') === false, 'çok şubeli personel (KDK yetkili) teslim alabilir');
esit('geldi', scalar('SELECT tasima_durum FROM orders WHERE id = ?', [$o]), 'teslim alındı: geldi');
$GLOBALS['__kullanici'] = $yonetici;
ok(str_contains($hata(static fn() => sube_tasima_teslim($o, 1)), 'yolda görünmüyor'), 'iki kez teslim alınmaz');
sube_teslim_ayarla($o, 1);
esit([null, null], array_values(row('SELECT teslim_sube_id, tasima_durum FROM orders WHERE id = ?', [$o])), 'aynı şubeye geri: teslim şubesi boşalır');
q("UPDATE orders SET order_stage = 'teslim_edildi' WHERE id = ?", [$o]);
ok(str_contains($hata(static fn() => sube_teslim_ayarla($o, $kdk)), 'Teslim edilmiş'), 'teslim edilmiş siparişin şubesi değişmez');

echo "4) Ürün stoku şubede\n";
$u = insert('urunler', ['ad' => 'Solüsyon', 'stok' => 0, 'created_at' => '2026-10-24']);
esit(10, urun_stok_degis($u, 10, 1), 'Merkez +10');
esit(3, urun_stok_degis($u, 3, $kdk), 'Kadıköy +3');
esit(13, (int) scalar('SELECT stok FROM urunler WHERE id = ?', [$u]), 'urunler.stok = şubelerin toplamı');
sube_sec($kdk);
esit(3, urun_sube_stok(['id' => $u, 'stok' => 13]), 'ekranda aktif şubenin stoku');
sube_sec(0);
esit(13, urun_sube_stok(['id' => $u, 'stok' => 13]), 'tüm şubelerde toplam');

echo "5) Stok transferi\n";
$f = insert('frame_items', ['brand' => 'Carrera', 'model' => 'CA1', 'barcode' => '8690001', 'qty' => 5, 'sube_id' => 1, 'created_at' => '2026-10-24']);
ok(str_contains($hata(static fn() => sube_transfer_olustur(1, 1, [['tur' => 'cerceve', 'id' => $f, 'adet' => 1]], 1)), 'aynı olamaz'), 'kaynak = hedef olmaz');
ok(str_contains($hata(static fn() => sube_transfer_olustur(1, $kdk, [['tur' => 'cerceve', 'id' => $f, 'adet' => 9]], 1)), 'yetersiz'), 'stok yetersiz');
ok(str_contains($hata(static fn() => sube_transfer_olustur(1, $kdk, [['tur' => 'cerceve', 'id' => $f, 'adet' => 0]], 1)), 'en az bir kalem'), 'boş transfer yok');
ok(str_contains($hata(static fn() => sube_transfer_olustur($kdk, 1, [['tur' => 'cerceve', 'id' => $f, 'adet' => 1]], 1)), 'bu şubenin stokunda değil'), 'başka şubenin çerçevesi gönderilmez');
$t = sube_transfer_olustur(1, $kdk, [['tur' => 'cerceve', 'id' => $f, 'adet' => 2], ['tur' => 'urun', 'id' => $u, 'adet' => 4]], 1, 'servis');
esit(3, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$f]), 'gönderince kaynaktan düşer (çerçeve 5 → 3)');
esit(6, (int) scalar('SELECT stok FROM urun_stok WHERE urun_id = ? AND sube_id = 1', [$u]), 'ürün Merkez 10 → 6');
esit('yolda', scalar('SELECT durum FROM sube_transferleri WHERE id = ?', [$t]), 'fiş yolda');
$GLOBALS['__kullanici'] = $personel;
$_SESSION['sube'] = $kdk;
ok(str_contains($hata(static fn() => sube_transfer_iptal($t, 2)), 'gönderen şube'), 'hedef şube personeli geri alamaz');
sube_transfer_teslim($t, 2);
$GLOBALS['__kullanici'] = $yonetici;
$hedefF = (int) scalar('SELECT id FROM frame_items WHERE barcode = ? AND sube_id = ?', ['8690001', $kdk]);
ok($hedefF > 0 && $hedefF !== $f, 'hedefte aynı barkodla ayrı satır açıldı');
esit(2, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$hedefF]), 'hedef çerçeve 2 adet');
esit(7, (int) scalar('SELECT stok FROM urun_stok WHERE urun_id = ? AND sube_id = ?', [$u, $kdk]), 'ürün Kadıköy 3 → 7');
esit(13, (int) scalar('SELECT stok FROM urunler WHERE id = ?', [$u]), 'toplam stok değişmedi');
$t2 = sube_transfer_olustur(1, $kdk, [['tur' => 'cerceve', 'id' => $f, 'adet' => 1]], 1);
sube_transfer_teslim($t2, 1);
esit([2, 3], [(int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$f]), (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$hedefF])], 'ikinci transfer aynı hedef satıra eklenir');
$t3 = sube_transfer_olustur(1, $ist, [['tur' => 'cerceve', 'id' => $f, 'adet' => 2]], 1);
sube_transfer_iptal($t3, 1);
esit([2, 'iptal'], [(int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$f]), scalar('SELECT durum FROM sube_transferleri WHERE id = ?', [$t3])], 'geri alınan transfer stoğu iade eder');
ok(str_contains($hata(static fn() => sube_transfer_teslim($t3, 1)), 'yolda değil'), 'geri alınmış transfer teslim alınmaz');
sube_sec(0);
esit(3, count(sube_transferler()), 'transfer listesi (tüm şubeler)');
sube_sec($ist);
esit([$t3], array_map('intval', array_column(sube_transferler(), 'id')), 'şube yalnız kendine giden/gelen transferleri görür');
sube_sec(0);

echo "6) Karşılaştırma\n";
q("INSERT INTO orders (customer_id, sube_id, order_stage, total_amount, promised_date, created_at) VALUES (?, ?, 'atolyede', 500, '2026-10-01', ?)", [$c, $kdk, date('Y-m-d') . ' 09:00:00']);
$kdkSip = (int) db()->lastInsertId();
insert('payments', ['order_id' => $kdkSip, 'amount' => 200, 'created_at' => date('Y-m-d') . ' 09:05:00']);
insert('cash_counts', ['count_date' => date('Y-m-d'), 'counted_amount' => 200, 'sube_id' => $kdk, 'created_at' => date('Y-m-d H:i:s')]);
$k = sube_karsilastirma()[$kdk];
esit([1, 200.0, 1, 1, 300.0, true], [$k['siparis'], $k['tahsilat'], $k['atolyede'], $k['geciken'], $k['bakiye'], $k['kasa']], 'Kadıköy: bugünkü sipariş, tahsilat, atölye, geciken, bakiye, kasa');
esit(false, sube_karsilastirma()[1]['kasa'], 'Merkez kasası açık');

echo "7) Kaynak bağlantıları\n";
$kok = dirname(__DIR__, 2);
$mig = (string) file_get_contents($kok . '/app/migrations.php');
ok((bool) preg_match('/const SCHEMA_VERSION = (4[1-9]|[5-9]\d);/', $mig) && str_contains($mig, 'migrate_v40_subeler()') && str_contains($mig, 'migrate_v41_cerceve_barkod_sube()'), 'göç v40 + v41');
ok(str_contains($mig, "UPDATE \$t SET sube_id = 1 WHERE sube_id IS NULL") && str_contains($mig, "VALUES (1, ?, ?, 1, 1, ?)"), 'göç: mevcut kayıtlar ana şubeye');
ok(str_contains((string) file_get_contents($kok . '/app/ozellik.php'), "'sube' => ["), 'özellik anahtarı (varsayılan kapalı)');
foreach (['app/pages/order-new.php', 'app/pages/tezgah.php', 'app/pages/sgk-aktar.php', 'app/pages/cerceve.php', 'app/pages/kasa.php'] as $d) {
    ok(str_contains((string) file_get_contents($kok . '/' . $d), 'sube_alan() +'), "yeni kayıt şubeye yazılır: $d");
}
foreach (['app/pages/index.php' => 'sube_kosul_siparis', 'app/pages/workshop.php' => 'sube_kosul_hazir', 'app/pages/kasa.php' => "sube_kosul('o')", 'app/pages/tahsilat.php' => "sube_kosul('o')",
          'app/pages/stock.php' => "sube_kosul('o')", 'app/aranacak.php' => 'sube_kosul_hazir', 'app/defter.php' => "sube_kosul('o')"] as $d => $iz) {
    ok(str_contains((string) file_get_contents($kok . '/' . $d), $iz), "süzgeç: $d");
}
$sip = (string) file_get_contents($kok . '/app/pages/order.php');
ok(str_contains($sip, "render_error_page('Bu sipariş başka şubede'") && str_contains($sip, 'sube_tasima_baslat($id)'), 'sipariş: şube erişimi ve taşıma');
ok(is_file($kok . '/subeler.php') && is_file($kok . '/transfer.php'), 'Şubeler ve Şube transferi sayfaları');
foreach (['app/pages/subeler.php', 'app/pages/transfer.php'] as $d) {
    ok(!preg_match('/<script(?![^>]*\bsrc=)|javascript:|\son(click|change|submit)=/i', (string) file_get_contents($kok . '/' . $d)), "CSP: satır içi betik yok — $d");
}
$sp = (string) file_get_contents($kok . '/app/pages/subeler.php');
ok(str_contains($sp, "preg_match('/^[a-z0-9-]+\\.php(\\?[^\\s<>\"]*)?\$/i', \$don)"), 'şube seçimi dönüş adresi sınırlı (açık yönlendirme yok)');

echo "8) Kayıtta telefon\n";
$mz = (string) file_get_contents($kok . '/app/merkez.php');
ok(str_contains($mz, "merkez_kolon_ekle(\$pdo, 'magazalar', 'telefon', 'VARCHAR(20) NULL')") && str_contains($mz, 'INSERT INTO magazalar (isim, email, telefon,'), 'merkez: telefon sütunu ve kayıt');
ok(str_contains($mz, "throw new DomainException('Telefon numarası geçersiz. Örnek: 0532 111 22 33');"), 'geçersiz telefon reddedilir');
$ky = (string) file_get_contents($kok . '/app/pages/kayit.php');
ok(str_contains($ky, 'name="telefon" type="tel"') && str_contains($ky, 'required placeholder="0532 111 22 33"') && str_contains($ky, "\$adminSifre, \$v['telefon'])"), 'kayıt formu telefonu zorunlu ister');
ok(str_contains((string) file_get_contents($kok . '/app/pages/merkez-panel.php'), "phone_display(\$m['telefon'])"), 'merkez panel listesinde telefon');

bitir();
