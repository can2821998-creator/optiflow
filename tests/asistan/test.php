<?php
declare(strict_types=1);
/* 4.30.0 — Telefon asistanı: konuşma akışı (app/asistan.php), eşleştirme, KVKK kuralları. */
require dirname(__DIR__) . '/uts/ortam.php';

$p = db();
$p->exec("ALTER TABLE orders ADD COLUMN promised_date TEXT NULL");
$p->exec("ALTER TABLE orders ADD COLUMN phone TEXT NULL");
$p->exec("CREATE TABLE asistan_cihazlar (id INTEGER PRIMARY KEY AUTOINCREMENT, ad TEXT NOT NULL, anahtar_ozet TEXT NOT NULL UNIQUE, aktif INTEGER NOT NULL DEFAULT 1,
    son_gorulme TEXT NULL, son_surum TEXT NULL, created_at TEXT NOT NULL)");
$p->exec("CREATE TABLE asistan_aramalar (id INTEGER PRIMARY KEY AUTOINCREMENT, anahtar TEXT NOT NULL UNIQUE, cihaz_id INTEGER NULL, numara TEXT NOT NULL DEFAULT '',
    customer_id INTEGER NULL, sonuc TEXT NOT NULL DEFAULT 'basladi', ozet TEXT NOT NULL DEFAULT '', not_metni TEXT NULL, geri_ara INTEGER NOT NULL DEFAULT 0,
    tamamlandi_at TEXT NULL, tamamlayan INTEGER NULL, sure INTEGER NULL, durum_json TEXT NOT NULL DEFAULT '{}', created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");

insert('user_accounts', ['full_name' => 'Selin', 'is_active' => 1, 'role' => 'super_yetkili']);
setting_set('shop_name', 'Poyraz Optik');
setting_set('shop_address', 'Bağdat Caddesi 214, Kadıköy');
setting_set('shop_hours', "Pazartesi – Cumartesi 09:30 – 19:30\nPazar kapalı");

$bugun = date('Y-m-d');
$ileri = date('Y-m-d', strtotime('+5 days'));

echo "1) Yardımcılar\n";
esit("Poyraz Optik'e", asistan_yonelme('Poyraz Optik'), 'yönelme: Optik → Optik\'e');
esit("Ada'ya", asistan_yonelme('Ada'), 'ünlüyle biten → \'ya');
esit("Işık Optik'e", asistan_yonelme('Işık Optik'), 'büyük I/İ doğru küçülür');
esit("Vizyon'a", asistan_yonelme('Vizyon'), 'kalın ünlü → \'a');
ok(str_starts_with(asistan_karsilama(), "Poyraz Optik'e hoş geldiniz."), 'varsayılan karşılama');
setting_set('asistan_karsilama', '{magaza} hattına hoş geldiniz.');
esit('Poyraz Optik hattına hoş geldiniz.', asistan_karsilama(), 'özel karşılama + {magaza}');
setting_set('asistan_karsilama', '');
esit('bugün', asistan_tarih($bugun, $bugun), 'tarih: bugün');
esit('yarın', asistan_tarih(date('Y-m-d', strtotime('+1 day')), $bugun), 'tarih: yarın');
esit('12 Ekim Pazartesi', asistan_tarih('2026-10-12', '2026-10-01'), 'tarih: gün ay haftanın günü');
esit('5321234567', asistan_numara('+90 532 123 45 67'), 'numara: son 10 hane');
esit('', asistan_numara('123'), 'kısa numara tanınmaz');
esit(20, asistan_bekleme_sn(), 'varsayılan çaldırma 20 sn');
setting_set('asistan_bekleme', '200');
esit(60, asistan_bekleme_sn(), 'çaldırma en çok 60 sn');
setting_set('asistan_bekleme', '20');

echo "2) Niyet\n";
foreach ([['gözlüğüm hazır mı', 'siparis'], ['camlarım geldi mi acaba', 'siparis'], ['ne zaman hazır olur', 'siparis'], ['saat kaçta hazır olur', 'siparis'],
          ['adresiniz nerede', 'adres'], ['kaçta kapanıyorsunuz', 'adres'], ['yetkiliyle görüşmek istiyorum', 'not'], ['fiyat soracaktım', 'not'],
          ['teşekkür ederim', 'bitir'], ['hayır', 'bitir'], ['bir', 'siparis'], ['iki', 'adres'], ['üç', 'not'], ['merhaba', ''], ['', '']] as [$m, $bek]) {
    esit($bek, asistan_niyet($m, null), 'söz: "' . $m . '"');
}
esit('siparis', asistan_niyet('', '1'), 'tuş 1');
esit('adres', asistan_niyet('gözlük', '2#'), 'tuş sözden önce gelir');
esit('not', asistan_niyet(null, '0'), 'tuş 0 → not');

echo "3) Sipariş cümleleri\n";
$mus = insert('customers', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '0532 123 45 67']);
$o1 = insert('orders', ['customer_id' => $mus, 'transaction_type' => 'gozluk', 'order_stage' => 'siparis_verildi', 'promised_date' => $ileri, 'total_amount' => 4650]);
$rx = insert('prescription_records', ['order_id' => $o1]);
$cam = insert('prescription_lens_items', ['prescription_id' => $rx, 'eye' => 'R', 'stock_status' => 'siparis_verildi']);
insert('prescription_lens_items', ['prescription_id' => $rx, 'eye' => 'L', 'stock_status' => 'stokta_var']);
$o = row('SELECT * FROM orders WHERE id = ?', [$o1]);
ok(str_contains(asistan_siparis_cumlesi($o), 'depodan gelmesi bekleniyor') && str_contains(asistan_siparis_cumlesi($o), 'Tahmini teslim ' . asistan_tarih($ileri)), 'cam depoda: bekleniyor + tahmini tarih');
update('prescription_lens_items', ['stock_status' => 'stokta_yok'], 'id = ?', [$cam]);
ok(str_contains(asistan_siparis_cumlesi($o), 'tedarik ediliyor'), 'eksik cam: tedarik ediliyor');
update('prescription_lens_items', ['stock_status' => 'stokta_var'], 'id = ?', [$cam]);
ok(str_contains(asistan_siparis_cumlesi($o), 'Camlarınız geldi'), 'camlar geldi');
update('orders', ['order_stage' => 'atolyede'], 'id = ?', [$o1]);
ok(str_contains(asistan_siparis_cumlesi(row('SELECT * FROM orders WHERE id = ?', [$o1])), 'atölyede hazırlanıyor'), 'atölyede');
update('orders', ['order_stage' => 'hazirlandi'], 'id = ?', [$o1]);
ok(str_contains(asistan_siparis_cumlesi(row('SELECT * FROM orders WHERE id = ?', [$o1])), 'Gözlüğünüz hazır'), 'hazır');
update('orders', ['order_stage' => 'atolyede', 'promised_date' => date('Y-m-d', strtotime('-2 days'))], 'id = ?', [$o1]);
ok(str_contains(asistan_siparis_cumlesi(row('SELECT * FROM orders WHERE id = ?', [$o1])), 'En kısa sürede'), 'geçmiş söz tarihi söylenmez');
update('orders', ['promised_date' => $ileri], 'id = ?', [$o1]);
ok(!str_contains(asistan_durum_metni(asistan_siparisler($mus)), '4650') && !str_contains(asistan_durum_metni(asistan_siparisler($mus)), '₺'), 'tutar söylenmez');
$tamir = insert('orders', ['customer_id' => $mus, 'transaction_type' => 'tamir', 'order_stage' => 'siparis_verildi']);
ok(str_starts_with(asistan_durum_metni(asistan_siparisler($mus)), '2 siparişiniz var.') && str_contains(asistan_durum_metni(asistan_siparisler($mus)), 'Onarım kaydınız alındı'), 'iki sipariş: numaralı, tamir cümlesi');
update('orders', ['order_stage' => 'iptal'], 'id = ?', [$tamir]);
$eski = insert('orders', ['customer_id' => $mus, 'order_stage' => 'teslim_edildi', 'delivered_at' => date('Y-m-d H:i:s', strtotime('-20 days'))]);
esit(1, count(asistan_siparisler($mus)), 'iptal ve eski teslimler okunmaz');

echo "4) Tanınan arayan\n";
$r = asistan_basla('+905321234567', 1);
ok($r['ok'] && str_starts_with($r['soyle'], "Poyraz Optik'e hoş geldiniz.") && str_contains($r['soyle'], 'Merhaba Ayşe Yılmaz') && str_contains($r['soyle'], 'atölyede'), 'karşılama + isim + durum');
esit('menu', $r['mod'], 'menüde bekler');
$a = asistan_arama_bul($r['oturum']);
esit($mus, (int) $a['customer_id'], 'arama müşteriye bağlandı');
esit('bilgi', $a['sonuc'], 'sonuç: bilgi verildi');
$r2 = asistan_cevap($a, 'adresiniz neresi', null);
ok(str_contains($r2['soyle'], 'Bağdat Caddesi 214') && str_contains($r2['soyle'], 'Pazar kapalı'), 'adres + çalışma saatleri');
$r3 = asistan_cevap(asistan_arama_bul($r['oturum']), 'teşekkürler', null);
ok($r3['bitir'] && str_contains($r3['soyle'], 'teşekkür ederiz'), 'teşekkür → kapanış');
asistan_bitti(asistan_arama_bul($r['oturum']), 47);
esit('47', (string) scalar('SELECT sure FROM asistan_aramalar WHERE anahtar = ?', [$r['oturum']]), 'süre kaydedildi');
ok(!str_contains((string) scalar('SELECT ozet FROM asistan_aramalar WHERE anahtar = ?', [$r['oturum']]), 'adresiniz'), 'konuşma metni saklanmaz');

echo "5) Tanınmayan arayan: numara tuşlama\n";
$r = asistan_basla('05559998877');
ok(!str_contains($r['soyle'], 'Merhaba') && str_contains($r['soyle'], "1'e"), 'kayıtsız: menü');
$r = asistan_cevap(asistan_arama_bul($r['oturum']), null, '1');
esit('numara', $r['mod'], '1 → numara istenir');
$r = asistan_cevap(asistan_arama_bul($r['oturum']), null, '5321234567#');
ok(str_contains($r['soyle'], 'Bu numaraya kayıtlı') && !str_contains($r['soyle'], 'Ayşe'), 'tuşlanan numarada durum söylenir, isim söylenmez');
$r = asistan_basla('05559998877');
asistan_cevap(asistan_arama_bul($r['oturum']), 'siparişim ne durumda', null);
$r2 = asistan_cevap(asistan_arama_bul($r['oturum']), 'beş yüz', '5550000000');
ok(str_contains($r2['soyle'], 'bulamadım') && $r2['mod'] === 'numara', 'bulunamayan numara: bir kez daha');
$r3 = asistan_cevap(asistan_arama_bul($r['oturum']), null, '5550000001');
ok($r3['bitir'] && (int) scalar('SELECT geri_ara FROM asistan_aramalar WHERE anahtar = ?', [$r['oturum']]) === 1, 'ikinci kez bulunamadı → geri arama kaydı');
ok((bool) $GLOBALS['__push'], 'yetkiliye bildirim gitti');

echo "6) Not bırakma\n";
$GLOBALS['__push'] = [];
$r = asistan_basla('05321234567');
$r = asistan_cevap(asistan_arama_bul($r['oturum']), 'yetkiliyle görüşmek istiyorum', null);
esit('not', $r['mod'], 'not modu');
$r = asistan_cevap(asistan_arama_bul($r['oturum']), 'Ben Ayşe, çerçeve vidası gevşedi, yarın gelebilir miyim', null);
ok($r['bitir'] && str_contains($r['soyle'], 'Notunuzu aldım'), 'not alındı, kapanış');
$k = row('SELECT * FROM asistan_aramalar WHERE anahtar = ?', [$r['oturum']]);
ok((int) $k['geri_ara'] === 1 && str_contains((string) $k['not_metni'], 'vidası gevşedi') && $k['sonuc'] === 'not', 'not kaydı');
ok(str_contains($GLOBALS['__push'][0][0]['title'], 'Ayşe Yılmaz') && str_contains($GLOBALS['__push'][0][0]['body'], 'vidası'), 'bildirimde isim ve not');
esit(2, asistan_rozet(), 'menü rozeti: bekleyen 2 geri arama');

echo "7) Anlaşılamayan konuşma ve tur sınırı\n";
$r = asistan_basla('05559998800');
$r = asistan_cevap(asistan_arama_bul($r['oturum']), 'hmm şey', null);
ok(str_starts_with($r['soyle'], 'Kusura bakmayın') && !$r['bitir'], 'bir kez anlaşılmadı: menü tekrar');
$r = asistan_cevap(asistan_arama_bul($r['oturum']), '', null);
ok($r['bitir'] && str_contains($r['soyle'], 'geri arayacak'), 'iki kez: geri arama + kapanış');
$r = asistan_basla('05321234567');
for ($i = 0; $i < ASISTAN_TUR_SINIRI + 1; $i++) {
    $r = asistan_cevap(asistan_arama_bul($r['oturum']), null, '2');
}
ok($r['bitir'], 'tur sınırında görüşme biter');

echo "8) Eşleştirme\n";
ok(asistan_bagla('123456', 'X') === null, 'kod üretilmeden bağlanılamaz');
$kod = asistan_eslesme_kodu_uret();
ok(preg_match('/^\d{6}$/', $kod) === 1 && !str_contains(setting('asistan_eslesme'), $kod), '6 haneli kod, ayarda yalnızca özeti');
ok(asistan_bagla('000000', 'X') === null || $kod === '000000', 'yanlış kod reddedilir');
$anahtar = asistan_bagla($kod, 'Samsung A54');
ok(is_string($anahtar) && strlen($anahtar) === 40, 'doğru kod → 40 haneli anahtar');
ok(asistan_bagla($kod, 'Y') === null, 'kod tek kullanımlık');
ok(asistan_cihaz_dogrula((string) $anahtar) !== null, 'anahtar doğrulanır');
ok((string) scalar('SELECT anahtar_ozet FROM asistan_cihazlar') !== $anahtar, 'anahtarın kendisi saklanmaz');
ok(asistan_cihaz_dogrula(str_repeat('a', 40)) === null && asistan_cihaz_dogrula('x') === null, 'yanlış anahtar reddedilir');
update('asistan_cihazlar', ['aktif' => 0], '1 = 1', []);
ok(asistan_cihaz_dogrula((string) $anahtar) === null, 'kaldırılan cihaz çalışmaz');
$kod2 = asistan_eslesme_kodu_uret();
setting_set('asistan_eslesme', explode('|', setting('asistan_eslesme'))[0] . '|' . (time() - 1));
ok(asistan_bagla($kod2, 'Z') === null, 'süresi dolan kod reddedilir');

echo "9) Kaynak denetimleri\n";
$kok = dirname(__DIR__, 2);
$mig = (string) file_get_contents($kok . '/app/migrations.php');
ok(str_contains($mig, 'const SCHEMA_VERSION = 33;') && str_contains($mig, 'migrate_v33_telefon_asistani'), 'göç v33');
$api = (string) file_get_contents($kok . '/app/pages/asistan-api.php');
ok(str_contains($api, "in_array('telefon_asistan'") && str_contains($api, 'asistan_cihaz_dogrula(') && str_contains($api, "merkez_hiz_asildi('asistan_bagla'"), 'uç nokta: özellik + cihaz anahtarı + deneme sınırı');
$bs = (string) file_get_contents($kok . '/app/bootstrap.php');
ok(str_contains($bs, "'odeme-bildirim.php', 'asistan.php'], true);"), 'asistan.php oturumsuz sunucu uç noktası (CSRF yok, mağaza &m=)');
ok(str_contains((string) file_get_contents($kok . '/app/ozellik.php'), "'telefon_asistan' => ["), 'özellik anahtarı tanımlı (varsayılan kapalı)');

bitir();
