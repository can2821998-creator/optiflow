<?php
declare(strict_types=1);
/* Hızlı satış (app/satis.php): katalog, sepet hesabı, satış, stok, ödemeler, indirim sınırı, iptal, özetler. */
require dirname(__DIR__) . '/uts/ortam.php';

$simdi = date('Y-m-d H:i:s');
$bugun = date('Y-m-d');
$cer = insert('frame_items', ['brand' => 'Ray-Ban', 'model' => 'RB3025', 'color' => 'Altın', 'barcode' => '8053672000011', 'qty' => 2, 'min_qty' => 1,
    'cost' => 1800, 'price' => 3500, 'created_at' => $simdi, 'updated_at' => $simdi]);
$cerFiyatsiz = insert('frame_items', ['brand' => 'Vintage', 'model' => 'V1', 'qty' => 1, 'created_at' => $simdi, 'updated_at' => $simdi]);
$mus = insert('customers', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567']);

echo "1) Ürün kataloğu\n";
hata_bekle(fn() => urun_kaydet(['ad' => '  ']), 'ad zorunlu', 'Ürün adı');
$sol = urun_kaydet(['ad' => 'Solüsyon 360 ml', 'kategori' => 'solusyon', 'barkod' => '8690000000017', 'fiyat' => '250', 'maliyet' => '120', 'kdv' => 10, 'stok' => 5, 'min_stok' => 2]);
esit(5, (int) scalar('SELECT stok FROM urunler WHERE id = ?', [$sol]), 'ilk stok girişi');
esit(1, (int) scalar("SELECT COUNT(*) FROM urun_hareketleri WHERE urun_id = ? AND sebep = 'giris'", [$sol]), 'giriş hareketi');
hata_bekle(fn() => urun_kaydet(['ad' => 'Kopya', 'barkod' => '8690000000017']), 'aynı barkod', 'başka bir üründe');
hata_bekle(fn() => urun_kaydet(['ad' => 'Çerçeve barkodu', 'barkod' => '8053672000011']), 'çerçeve barkoduyla çakışma', 'çerçevede');
hata_bekle(fn() => urun_kaydet(['ad' => 'Boşluklu', 'barkod' => 'AB 12']), 'geçersiz barkod', 'Barkod');
$kilif = urun_kaydet(['ad' => 'Gözlük kılıfı', 'kategori' => 'aksesuar', 'fiyat' => '150,00', 'maliyet' => '40']);
esit(150.0, (float) scalar('SELECT fiyat FROM urunler WHERE id = ?', [$kilif]), 'Türkçe fiyat okunur');
$montaj = urun_kaydet(['ad' => 'Montaj', 'kategori' => 'hizmet', 'stok' => 9]);
esit(0, (int) scalar('SELECT stok_takip FROM urunler WHERE id = ?', [$montaj]), 'hizmette stok takibi yok');
esit(0, (int) scalar('SELECT stok FROM urunler WHERE id = ?', [$montaj]), 'hizmete stok girmez');
ok(str_starts_with(urun_yeni_barkod(), 'PU'), 'üretilen barkod PU ile başlar');

echo "2) Arama\n";
$a = satis_urun_ara('8053672000011');
esit(['cerceve', $cer], [$a[0]['tur'] ?? '', $a[0]['id'] ?? 0], 'çerçeve barkodu tam eşleşme');
esit(1, count($a), 'tam barkodda tek sonuç');
$a = satis_urun_ara('8690000000017');
esit(['urun', $sol], [$a[0]['tur'] ?? '', $a[0]['id'] ?? 0], 'ürün barkodu');
$a = satis_urun_ara('solüs');
esit($sol, $a[0]['id'] ?? 0, 'adla arama');
$a = satis_urun_ara('ray');
ok(in_array($cer, array_column($a, 'id'), true), 'çerçeve markasıyla arama');
esit([], satis_urun_ara('  '), 'boş arama');

echo "3) Sepet hesabı\n";
hata_bekle(fn() => satis_sepet_hesapla([]), 'boş sepet', 'boş');
hata_bekle(fn() => satis_sepet_hesapla([['tur' => 'urun', 'id' => 999]]), 'olmayan ürün', 'bulunamadı');
hata_bekle(fn() => satis_sepet_hesapla([['tur' => 'urun', 'id' => $sol, 'adet' => 0]]), 'adet 0', 'Adet');
hata_bekle(fn() => satis_sepet_hesapla([['tur' => 'cerceve', 'id' => $cerFiyatsiz]]), 'fiyatsız çerçeve fiyat ister', 'fiyat girin');
hata_bekle(fn() => satis_sepet_hesapla([['tur' => 'serbest', 'ad' => '', 'fiyat' => 10]]), 'serbest kalem adı', 'adı');
hata_bekle(fn() => satis_sepet_hesapla([['tur' => 'urun', 'id' => $kilif, 'indirim' => 200]]), 'indirim tutarı aşamaz', 'indirimi');
hata_bekle(fn() => satis_sepet_hesapla([['tur' => 'hack', 'id' => 1]]), 'geçersiz tür', 'Geçersiz');
$h = satis_sepet_hesapla([
    ['tur' => 'cerceve', 'id' => $cer, 'adet' => 1, 'fiyat' => 1],           // katalog fiyatı varken tarayıcı fiyatı yok sayılır
    ['tur' => 'urun', 'id' => $sol, 'adet' => 2, 'indirim' => 50],
    ['tur' => 'serbest', 'ad' => 'Burun pedi değişimi', 'fiyat' => '75,50'],
]);
esit(3500.0, $h['kalemler'][0]['birim_fiyat'], 'fiyat sunucudan (tarayıcı fiyatı yok sayıldı)');
esit(4075.5, $h['ara_toplam'], 'ara toplam');
esit(50.0, $h['kalem_indirim'], 'kalem indirimi');
esit(2040.0, $h['maliyet'], 'maliyet (1800 + 2×120)');
esit(10, $h['kalemler'][1]['kdv'], 'ürün KDV oranı');

echo "4) Satış\n";
$sepet = [['tur' => 'cerceve', 'id' => $cer, 'adet' => 1], ['tur' => 'urun', 'id' => $sol, 'adet' => 2]];
hata_bekle(fn() => satis_kaydet($sepet, [['method' => 'nakit', 'amount' => 100]]), 'eksik ödeme', 'eşit olmalı');
hata_bekle(fn() => satis_kaydet($sepet, [['method' => 'bitcoin', 'amount' => 4000]]), 'geçersiz yöntem', 'yöntemi');
hata_bekle(fn() => satis_kaydet($sepet, [['method' => 'nakit', 'amount' => 4000]], 5000), 'indirim tutarı aşamaz', 'büyük olamaz');
esit(0, (int) scalar('SELECT COUNT(*) FROM satislar'), 'hatalı denemeler kayıt bırakmadı');
$s1 = satis_kaydet($sepet, [['method' => 'nakit', 'amount' => '1.000,00'], ['method' => 'kart', 'amount' => 2900], ['method' => 'havale', 'amount' => 0]], 100, $mus, 'Vitrin');
$s = satis_bul($s1);
esit(['tamam', 4000.0, 3900.0, 100.0], [$s['durum'], (float) $s['ara_toplam'], (float) $s['toplam'], (float) $s['indirim']], 'satış tutarları');
esit(2, count($s['odemeler']), 'sıfır tutarlı ödeme yazılmaz');
esit(2, count($s['kalemler']), 'kalemler');
esit($mus, (int) $s['customer_id'], 'müşteri bağlandı');
esit(1, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$cer]), 'çerçeve stoğu düştü');
esit($s1, (int) scalar("SELECT satis_id FROM frame_moves WHERE frame_item_id = ? AND reason = 'satis'", [$cer]), 'çerçeve hareketi satışa bağlı');
esit(3, (int) scalar('SELECT stok FROM urunler WHERE id = ?', [$sol]), 'ürün stoğu düştü');
esit(-2, (int) scalar("SELECT delta FROM urun_hareketleri WHERE urun_id = ? AND satis_id = ?", [$sol, $s1]), 'ürün hareketi');
// Hizmet: stok takibi yok, hareket yazılır ama stok değişmez
$s2 = satis_kaydet([['tur' => 'urun', 'id' => $montaj, 'adet' => 1, 'fiyat' => 100]], [['method' => 'nakit', 'amount' => 100]]);
esit(0, (int) scalar('SELECT stok FROM urunler WHERE id = ?', [$montaj]), 'hizmet stoğu değişmez');
// Stok yetmese de satış durmaz (eksi stok sayımda düzeltilir)
$s3 = satis_kaydet([['tur' => 'urun', 'id' => $sol, 'adet' => 5]], [['method' => 'kart', 'amount' => 1250]]);
esit(-2, (int) scalar('SELECT stok FROM urunler WHERE id = ?', [$sol]), 'ürün stoğu eksiye düşebilir');

echo "5) İndirim sınırı (personel)\n";
setting_set('hizli_satis_indirim_yuzde', '10');
$GLOBALS['__kullanici'] = ['id' => 2, 'full_name' => 'Personel', 'role' => 'personel'];
esit(10.0, satis_azami_indirim_yuzde(), 'personel sınırı ayardan');
hata_bekle(fn() => satis_kaydet([['tur' => 'urun', 'id' => $kilif, 'adet' => 1, 'indirim' => 30]], [['method' => 'nakit', 'amount' => 120]]), '%20 indirim personelde reddedilir', 'en yüksek oranı');
$s4 = satis_kaydet([['tur' => 'urun', 'id' => $kilif, 'adet' => 1, 'indirim' => 15]], [['method' => 'nakit', 'amount' => 135]]);
ok($s4 > 0, '%10 indirim personelde geçer');
hata_bekle(fn() => satis_iptal($s4, 'deneme'), 'personel iptal edemez', 'yönetici');
$liste = satis_listesi($bugun, $bugun);
esit([$s4], array_map('intval', array_column($liste, 'id')), 'personel yalnızca kendi satışını görür');
$GLOBALS['__kullanici'] = ['id' => 1, 'full_name' => 'Test Personel', 'role' => 'super'];
esit(100.0, satis_azami_indirim_yuzde(), 'yönetici sınırsız');

echo "6) Özet ve kasa\n";
$oz = satis_ozeti($bugun, $bugun);
esit(4, $oz['adet'], 'satış adedi');
esit(3900.0 + 100 + 1250 + 135, $oz['ciro'], 'ciro');
esit(1000.0 + 100 + 135, $oz['yontem']['nakit'] ?? 0.0, 'nakit toplamı');
esit(2900.0 + 1250, $oz['yontem']['kart'] ?? 0.0, 'kart toplamı');
esit(1235.0, satis_odeme_toplami($bugun, 'nakit'), 'gün sonu nakit');
esit(135.0, satis_odeme_toplami($bugun, 'nakit', 2), 'personel bazında nakit');
esit(4150.0, satis_odeme_toplami($bugun, 'kart'), 'gün sonu kart');

echo "7) İptal\n";
hata_bekle(fn() => satis_iptal($s1, '  '), 'sebep zorunlu', 'sebebini');
satis_iptal($s1, 'Müşteri vazgeçti');
esit('iptal', satis_bul($s1)['durum'], 'durum iptal');
esit(2, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$cer]), 'çerçeve stoğa döndü');
esit(0, (int) scalar('SELECT stok FROM urunler WHERE id = ?', [$sol]), 'ürün stoğa döndü (-2 + 2)');
hata_bekle(fn() => satis_iptal($s1, 'tekrar'), 'iki kez iptal edilemez', 'zaten');
esit(235.0, satis_odeme_toplami($bugun, 'nakit'), 'iptal edilen satışın nakdi kasadan çıktı');
$oz = satis_ozeti($bugun, $bugun);
esit(3, $oz['adet'], 'iptal özete girmez');
esit(1, count(array_filter(satis_listesi($bugun, $bugun), fn($x) => $x['durum'] === 'iptal')), 'iptal listede görünür');
esit(2, count(satis_listesi($bugun, $bugun, false)) - 1, 'iptaller hariç liste');

echo "8) Kasa birleştirme ve kâr\n";
$aralik = [$bugun . ' 00:00:00', date('Y-m-d', strtotime('+1 day')) . ' 00:00:00'];
$y = array_column(satis_kasa_yontemleri($aralik), 'total', 'method');
esit(235.0, (float) ($y['nakit'] ?? 0), 'kasa: hızlı satış nakdi (iptal hariç)');
$b = satis_kasa_birlestir([['method' => 'nakit', 'cnt' => 2, 'total' => 500.0], ['method' => 'havale', 'cnt' => 1, 'total' => 50.0]], satis_kasa_yontemleri($aralik));
$b = array_column($b, null, 'method');
esit([735.0, 4], [$b['nakit']['total'], $b['nakit']['cnt']], 'nakit satırları toplandı');
esit(50.0, $b['havale']['total'], 'yalnızca tahsilattaki yöntem korunur');
ok(isset($b['kart']), 'yalnızca satıştaki yöntem eklenir');
$p = satis_kasa_birlestir([['name' => 'Patron', 'method' => 'nakit', 'cnt' => 1, 'total' => 10.0]], satis_kasa_personel($aralik), ['name', 'method']);
ok(count(array_filter($p, fn($r) => $r['name'] === 'Personel' && $r['method'] === 'nakit' && abs($r['total'] - 135.0) < 0.001)) === 1, 'personel × yöntem');
$k = satis_personel_kar($bugun, $bugun);
esit(135.0, $k[2]['ciro'] ?? 0.0, 'kâr: personel cirosu');
esit(40.0, $k[2]['maliyet'] ?? 0.0, 'kâr: personel ürün maliyeti (kılıf alış 40)');
esit(false, isset($k[1]) && $k[1]['ciro'] >= 3900, 'kâr: iptal edilen satış sayılmaz');

bitir();
