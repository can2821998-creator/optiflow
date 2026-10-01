<?php
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';

echo "1) Hatalı cam: kayıt, yeniden yapım, lab iadesi, cari\n";
$ted = insert('suppliers', ['name' => 'Lab A.Ş.', 'created_at' => uts_simdi(), 'updated_at' => uts_simdi()]);
$mus = insert('customers', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'birth_year' => 1980]);
$sip = insert('orders', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'customer_id' => $mus, 'order_stage' => 'hazirlandi']);
$rx = insert('prescription_records', ['order_id' => $sip]);
$camR = insert('prescription_lens_items', ['prescription_id' => $rx, 'lens_no' => 1, 'eye' => 'R', 'lens_type' => 'Progresif 1.6', 'stock_status' => 'stokta_var', 'supplier_id' => $ted, 'unit_cost' => 800, 'arrived_at' => uts_simdi(), 'cam_siparis_id' => 5]);
$camL = insert('prescription_lens_items', ['prescription_id' => $rx, 'lens_no' => 2, 'eye' => 'L', 'lens_type' => 'Progresif 1.6', 'stock_status' => 'stokta_var', 'supplier_id' => $ted]);
$baska = insert('orders', ['first_name' => 'X', 'last_name' => 'Y']);
$rx2 = insert('prescription_records', ['order_id' => $baska]);
$yabanci = insert('prescription_lens_items', ['prescription_id' => $rx2, 'lens_no' => 1]);
hata_bekle(fn() => cam_hata_ekle($sip, ['neden' => '']), 'sebep zorunlu', 'sebebini');
hata_bekle(fn() => cam_hata_ekle($sip, ['neden' => 'laboratuvar', 'camlar' => [$yabanci]]), 'başka siparişin camı seçilemez', 'ait değil');
hata_bekle(fn() => cam_hata_ekle(999, ['neden' => 'olcu']), 'olmayan sipariş', 'bulunamadı');
$h1 = cam_hata_ekle($sip, ['neden' => 'laboratuvar', 'goz' => 'R', 'maliyet' => 800, 'camlar' => [$camR], 'alacak' => true, 'aciklama' => 'aks ters']);
$r = row('SELECT * FROM cam_hatalari WHERE id = ?', [$h1]);
ok((int) $r['supplier_id'] === $ted && $r['alacak_durum'] === 'bekliyor' && (float) $r['alacak_tutar'] === 800.0, 'tedarikçi camdan bulundu, iade bekleniyor');
$cr = row('SELECT * FROM prescription_lens_items WHERE id = ?', [$camR]);
ok($cr['stock_status'] === 'stokta_yok' && $cr['arrived_at'] === null && $cr['cam_siparis_id'] === null, 'cam yeniden "Eksik": geldi/fiş bilgisi temizlendi');
esit('stokta_var', scalar('SELECT stock_status FROM prescription_lens_items WHERE id = ?', [$camL]), 'seçilmeyen cam değişmedi');
hata_bekle(fn() => cam_hata_ekle($baska, ['neden' => 'laboratuvar', 'alacak' => true]), 'tedarikçisiz iade alacağı açılmaz', 'tedarikçi');
$h2 = cam_hata_ekle($sip, ['neden' => 'olcu', 'maliyet' => 300, 'sorumlu_id' => 2, 'alacak' => true]);
esit('yok', scalar('SELECT alacak_durum FROM cam_hatalari WHERE id = ?', [$h2]), 'mağaza hatasında iade alacağı açılmaz');
esit(1100.0, cam_hata_siparis_maliyeti($sip), 'sipariş yeniden yapım maliyeti');

$cari = static fn(): float => round((float) scalar('SELECT COALESCE(SUM(amount),0) FROM supplier_invoices WHERE supplier_id = ?', [$ted]) - (float) scalar('SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE supplier_id = ?', [$ted]), 2);
insert('supplier_invoices', ['supplier_id' => $ted, 'invoice_no' => 'L1', 'invoice_date' => '2026-09-01', 'amount' => 5000, 'created_at' => uts_simdi()]);
hata_bekle(fn() => cam_hata_alacak_kapat($h1, 'alindi', 0), 'sıfır iade olmaz', '0');
cam_hata_alacak_kapat($h1, 'alindi', 750);
esit(4250.0, $cari(), 'alınan iade tedarikçi borcunu düşürdü');
$pay = row('SELECT * FROM supplier_payments WHERE id = ?', [(int) scalar('SELECT alacak_payment_id FROM cam_hatalari WHERE id = ?', [$h1])]);
ok($pay['method'] === 'iade' && (float) $pay['amount'] === 750.0, 'cari kaydı: yöntem iade, 750');
ok(cam_hata_odeme_bagli_mi((int) $pay['id']) !== null, 'iade kaydı ekstreden silinemez (bağlı)');
esit(350.0, cam_hata_siparis_maliyeti($sip), 'kârlılıktan düşülen: 1100 − 750 iade');
hata_bekle(fn() => cam_hata_alacak_kapat($h1, 'alindi', 1), 'kapanmış iade tekrar kapanmaz', 'bekleyen');
cam_hata_alacak_geri_al($h1);
esit(5000.0, $cari(), 'iade geri alınınca cari eski haline döndü');
cam_hata_alacak_kapat($h1, 'reddedildi');
esit('reddedildi', scalar('SELECT alacak_durum FROM cam_hatalari WHERE id = ?', [$h1]), 'reddedildi');
esit(5000.0, $cari(), 'reddedilen iade cariye yazılmaz');
cam_hata_alacak_geri_al($h1);
cam_hata_alacak_kapat($h1, 'alindi');
esit(4250.0, $cari(), 'varsayılan tutar: son girilen iade tutarı (750)');
cam_hata_sil($h1);
esit(5000.0, $cari(), 'kayıt silinince iade cari kaydı da silindi');
esit('Ödeme · İade / alacak (hatalı cam)', 'Ödeme · ' . tedarik_odeme_yontemleri()['iade'], 'ekstrede yöntem etiketi');

echo "2) Hatalı cam raporu ve karne\n";
cam_hata_ekle($sip, ['neden' => 'laboratuvar', 'maliyet' => 500, 'supplier_id' => $ted, 'alacak' => true]);
cam_hata_ekle($sip, ['neden' => 'montaj', 'maliyet' => 200, 'sorumlu_id' => 1]);
$rp = cam_hata_rapor(date('Y-m-01'), date('Y-m-d'));
esit(3, $rp['adet'], 'dönemde 3 kayıt');
esit(1000.0, $rp['maliyet'], 'toplam maliyet');
esit(500.0, $rp['iade_bekleyen'], 'bekleyen iade');
$nedenler = array_column($rp['neden'], 'adet', 'k');
ok((int) $nedenler['laboratuvar'] === 1 && (int) $nedenler['olcu'] === 1 && (int) $nedenler['montaj'] === 1, 'sebep dağılımı');
esit(2, count($rp['sorumlu']), 'mağaza kaynaklı (ölçü + montaj) personel dağılımı');
esit([$ted => 1], cam_hata_lab_sayilari(null), 'karne: tedarikçinin lab hatası sayısı');
esit(1, cam_hata_rozet(), 'rozet: bekleyen iade');
esit(0, cam_hata_rapor('2020-01-01', '2020-12-31')['adet'], 'boş dönem');

echo "3) SGK hak: OptiFlow kaydından\n";
setting_set('sgk_hak_ay', '24'); setting_set('sgk_hak_cocuk_ay', '12'); setting_set('sgk_hak_cocuk_yas', '14');
$d = sgk_hak_durumu($mus);
esit('bilinmiyor', $d['durum'], 'kayıt yok: bilinmiyor');
ok(str_contains($d['mesaj'], 'Medula'), 'Medula\'dan doğrulama önerilir');
$eski = insert('orders', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'customer_id' => $mus, 'order_stage' => 'teslim_edildi', 'sgk_amount' => 300, 'delivered_at' => date('Y-m-d H:i:s', strtotime('-10 months')), 'created_at' => date('Y-m-d H:i:s', strtotime('-11 months'))]);
$d = sgk_hak_durumu($mus, $sip);
ok($d['durum'] === 'yok' && $d['kaynak'] === 'optiflow' && $d['siparis_id'] === $eski, '10 ay önce SGK\'lı alım: hak yok');
esit(substr(date('Y-m-d', strtotime('-10 months')), 0, 10), $d['son'], 'son alım = teslim tarihi');
ok($d['kalan_gun'] > 0 && $d['kalan_gun'] < 450, 'kalan gün ~14 ay');
esit(24, $d['ay'], 'yetişkin 24 ay');
esit('bilinmiyor', sgk_hak_durumu($mus, $eski)['durum'] === 'bilinmiyor' ? 'bilinmiyor' : 'x', 'hesap yapılan sipariş hariç tutulur');
q("UPDATE orders SET order_stage = 'iptal' WHERE id = ?", [$eski]);
esit('bilinmiyor', sgk_hak_durumu($mus)['durum'], 'iptal edilen SGK siparişi sayılmaz');
q("UPDATE orders SET order_stage = 'teslim_edildi', sgk_amount = 0, sgk_erecete = 'ABC' , delivered_at = ? WHERE id = ?", [date('Y-m-d H:i:s', strtotime('-25 months')), $eski]);
esit('var', sgk_hak_durumu($mus)['durum'], 'yalnız e-reçete no olan, 25 ay önceki alım: hak var');
$cocuk = insert('customers', ['first_name' => 'Ece', 'last_name' => 'Ak', 'birth_year' => (int) date('Y') - 9]);
insert('orders', ['first_name' => 'Ece', 'last_name' => 'Ak', 'customer_id' => $cocuk, 'order_stage' => 'teslim_edildi', 'sgk_amount' => 100, 'delivered_at' => date('Y-m-d H:i:s', strtotime('-13 months'))]);
$dc = sgk_hak_durumu($cocuk);
ok($dc['ay'] === 12 && $dc['durum'] === 'var', '9 yaş: 12 ay, 13 ay önce alım → hak var');
esit('2027-02-28', sgk_hak_ay_ekle('2025-02-28', 24), 'ay ekleme');
esit('2026-02-28', sgk_hak_ay_ekle('2025-01-31', 13), 'ay sonu taşması (31 Ocak + 13 ay = 28 Şubat)');
esit('2028-02-29', sgk_hak_ay_ekle('2026-02-28', 24) === '2028-02-28' ? '2028-02-29' : 'x', 'artık yıl dışı gün korunur');

echo "4) Medula / e-Devlet ekranı okuma\n";
$edevlet = "Medula Optik Cam ve Çerçeve Bilgisi Sorgulama\nT.C. Kimlik No: 1234*******\nAdı Soyadı: AYŞE YILMAZ\nDoğum Tarihi: 01.02.1980\n"
    . "Reçete Tarihi\tMalzeme\tTeslim Tarihi\tOptisyenlik Müessesesi\n"
    . "12.03.2025\tUZAK GÖZLÜK CAMI SAĞ\t15.03.2025\tPOYRAZ OPTİK\n"
    . "12.03.2025\tUZAK GÖZLÜK ÇERÇEVESİ\t15.03.2025\tPOYRAZ OPTİK\n"
    . "03.01.2023\tYAKIN GÖZLÜK CAMI\t05.01.2023\tX OPTİK\n"
    . "Sorgu Tarihi: " . date('d.m.Y') . "\n";
ok(sgk_hak_metni_mi($edevlet), 'e-Devlet cam/çerçeve ekranı tanındı');
$c = sgk_hak_coz($edevlet);
esit('2025-03-15', $c['son_alim'], 'son alım: en yeni teslim (doğum ve sorgu tarihi sayılmaz)');
esit(3, count($c['satirlar']), '3 alım satırı');
esit('belirsiz', $c['hak'], 'hak ifadesi yok');
$medula = "HASTA HAK SORGULAMA\nHasta Adı Soyadı : AYŞE YILMAZ\nGözlük hakkı bulunmamaktadır.\nSon teslim tarihi : 15.03.2025\nBir sonraki hak tarihi : 15.03.2027\n";
$c2 = sgk_hak_coz($medula);
ok($c2['hak'] === 'yok' && $c2['sonraki_hak'] === '2027-03-15' && $c2['son_alim'] === '2025-03-15', 'Medula hak yok + sonraki hak tarihi');
ok(sgk_hak_metni_mi($medula), 'Medula hak sorgu ekranı tanındı');
$c3 = sgk_hak_coz("Hak Sorgulama\nHastanın gözlük hakkı vardır.");
esit('var', $c3['hak'], 'hak var ifadesi');
$recete = "E-REÇETE\nHasta Adı Soyadı: AYŞE YILMAZ\nReçete Tarihi: 12.03.2025\nSAĞ SPH -1.25 CYL -0.50 AKS 180\nSOL SPH -1.00\nADD +2.00\n";
ok(!sgk_hak_metni_mi($recete), 'reçete ekranı hak ekranı sanılmaz');
$c4 = sgk_hak_coz("Optik geçmiş\n" . date('d.m.Y', strtotime('+5 days')) . " ÇERÇEVE teslim\n31.02.2025 CAM\n");
esit(null, $c4['son_alim'], 'gelecek ve geçersiz tarihler alım sayılmaz');

echo "5) Medula sonucu hak hesabını günceller\n";
$m2 = insert('customers', ['first_name' => 'Ali', 'last_name' => 'Veli', 'birth_year' => 1975]);
esit('bilinmiyor', sgk_hak_durumu($m2)['durum'], 'önce bilinmiyor');
sgk_hak_kaydet($c, 'yapistir', $m2, null);
$dm = sgk_hak_durumu($m2);
ok($dm['kaynak'] === 'medula' && $dm['son'] === '2025-03-15' && $dm['hak_tarihi'] === '2027-03-15', 'başka optikteki alım Medula\'dan öğrenildi');
esit('yok', $dm['durum'], '2027\'ye kadar hak yok');
sgk_hak_kaydet(['ad' => '', 'soyad' => '', 'hasta' => '', 'son_alim' => null, 'sonraki_hak' => null, 'hak' => 'var', 'satirlar' => []], 'masaustu', $m2, null);
esit('var', sgk_hak_durumu($m2)['durum'], 'son 30 gündeki açık "hak var" ifadesi esas alınır');
$sip3 = insert('orders', ['first_name' => 'Ali', 'last_name' => 'Veli', 'customer_id' => $m2]);
hata_bekle(fn() => sgk_hak_kaydet($c, 'yapistir', $mus, $sip3), 'sipariş başka müşterinin', 'ait değil');
hata_bekle(fn() => sgk_hak_elle($m2, null, date('Y-m-d', strtotime('+1 day')), 'belirsiz'), 'ileri tarih elle girilemez', 'geçersiz');
$e = sgk_hak_elle($m2, $sip3, '2026-01-10', 'belirsiz');
esit('2026-01-10', scalar('SELECT son_alim FROM sgk_hak_sorgulari WHERE id = ?', [$e]), 'elle tarih kaydedildi');

echo "6) İnceleme bulguları (regresyon)\n";
// (4) yeniden yapımda delivery_id temizlenir
$cD = insert('prescription_lens_items', ['prescription_id' => $rx, 'lens_no' => 3, 'eye' => 'R', 'delivery_id' => 7, 'stock_status' => 'stokta_var']);
cam_hata_ekle($sip, ['neden' => 'kirilma', 'camlar' => [$cD]]);
esit(null, scalar('SELECT delivery_id FROM prescription_lens_items WHERE id = ?', [$cD]), 'yeniden yapılan camın eski teslimat bağı kalktı');
// (1) 13 ay önceki Medula sorgusu hâlâ geçerli
$m3 = insert('customers', ['first_name' => 'Uzun', 'last_name' => 'Süre', 'birth_year' => 1970]);
insert('orders', ['first_name' => 'U', 'last_name' => 'S', 'customer_id' => $m3, 'order_stage' => 'teslim_edildi', 'sgk_amount' => 100, 'delivered_at' => date('Y-m-d H:i:s', strtotime('-30 months'))]);
insert('sgk_hak_sorgulari', ['customer_id' => $m3, 'kaynak' => 'yapistir', 'son_alim' => date('Y-m-d', strtotime('-14 months')), 'hak' => 'belirsiz', 'created_at' => date('Y-m-d H:i:s', strtotime('-13 months'))]);
$d3 = sgk_hak_durumu($m3);
ok($d3['durum'] === 'yok' && $d3['kaynak'] === 'medula', '13 ay önceki Medula sorgusundaki başka optik alımı hâlâ sayılır');
// (2) Medula "var" dedikten sonra OptiFlow'da SGK'lı satış yapıldı
$m4 = insert('customers', ['first_name' => 'Yeni', 'last_name' => 'Satis', 'birth_year' => 1990]);
insert('sgk_hak_sorgulari', ['customer_id' => $m4, 'kaynak' => 'masaustu', 'hak' => 'var', 'created_at' => date('Y-m-d H:i:s', strtotime('-20 days'))]);
esit('var', sgk_hak_durumu($m4)['durum'], 'Medula "var" (satıştan önce)');
insert('orders', ['first_name' => 'Y', 'last_name' => 'S', 'customer_id' => $m4, 'order_stage' => 'teslim_edildi', 'sgk_amount' => 100, 'delivered_at' => date('Y-m-d H:i:s', strtotime('-5 days'))]);
$d4 = sgk_hak_durumu($m4);
ok($d4['durum'] === 'yok' && $d4['kaynak'] === 'optiflow', 'sorgudan sonraki SGK satışı "var" hükmünü geçersiz kılar');
// (3) yalnız "sonraki hak tarihi" okunan ekran
$m5 = insert('customers', ['first_name' => 'Sadece', 'last_name' => 'Tarih', 'birth_year' => 1985]);
sgk_hak_kaydet(sgk_hak_coz("HAK SORGULAMA\nBir sonraki hak tarihi: 15.03.2027\n"), 'yapistir', $m5, null);
$d5 = sgk_hak_durumu($m5);
ok($d5['hak_tarihi'] === '2027-03-15' && $d5['durum'] === 'yok', 'alım tarihi olmadan sonraki hak tarihi kullanılır');
// (6) "Hakkı" adı ve Silendirik yazımı
$rc = "E-Reçete No: 3K8F2A1\nHasta: HAKKI YILMAZ\nReçete Tarihi: 25.09.2026\nSağ: -1.50 -0.75 90\nSol: -1.25";
ok(!sgk_hak_metni_mi($rc), '"Hakkı" adlı hastanın reçetesi hak ekranı sanılmaz');
ok(!sgk_hak_metni_mi("Hasta Adı: HAKKI DEMİR\nSferik -1.00 Silendirik -0.50 Aks 90\nTeslim tarihi 01.10.2026"), 'Silendirik yazımı reçete sayılır');
// (7) "Sorgulama Tarihi" alım sayılmaz
$c7 = sgk_hak_coz("Cam ve Çerçeve Bilgisi\nCam ve Çerçeve Sorgulama Tarihi: " . date('d.m.Y') . "\n15.03.2025 ÇERÇEVE teslim\n");
esit('2025-03-15', $c7['son_alim'], '"Sorgulama Tarihi" satırı alım sayılmaz');

// 2. tur: eski "sonraki hak" ve eski "var" hükmü yeni alımı ezmez
$m6 = insert('customers', ['first_name' => 'Iki', 'last_name' => 'Sorgu', 'birth_year' => 1980]);
insert('sgk_hak_sorgulari', ['customer_id' => $m6, 'kaynak' => 'yapistir', 'son_alim' => date('Y-m-d', strtotime('-30 months')), 'sonraki_hak' => date('Y-m-d', strtotime('-6 months')), 'hak' => 'belirsiz', 'created_at' => date('Y-m-d H:i:s', strtotime('-18 months'))]);
insert('sgk_hak_sorgulari', ['customer_id' => $m6, 'kaynak' => 'yapistir', 'son_alim' => date('Y-m-d', strtotime('-2 months')), 'hak' => 'belirsiz', 'created_at' => date('Y-m-d H:i:s', strtotime('-1 month'))]);
$d6 = sgk_hak_durumu($m6);
ok($d6['durum'] === 'yok' && $d6['hak_tarihi'] > date('Y-m-d'), 'eski sonraki-hak tarihi yeni alımı ezmez');
$m7 = insert('customers', ['first_name' => 'Var', 'last_name' => 'Sonra', 'birth_year' => 1980]);
insert('sgk_hak_sorgulari', ['customer_id' => $m7, 'kaynak' => 'masaustu', 'hak' => 'var', 'created_at' => date('Y-m-d H:i:s', strtotime('-20 days'))]);
sgk_hak_elle($m7, null, date('Y-m-d', strtotime('-1 day')), 'belirsiz');
esit('yok', sgk_hak_durumu($m7)['durum'], 'elle girilen yeni alım eski "var" hükmünü geçersiz kılar');
ok(!sgk_hak_metni_mi("Hasta: HAKKI VAROL\nReçete Tarihi 01.10.2026\nSağ 1.50 0.75 90"), '"Hakkı Varol" adı hak ifadesi sayılmaz');
ok(sgk_hak_metni_mi("Hak sorgulama\nHastanın gözlük hakkı bulunmamaktadır."), '"hakkı bulunmamaktadır" tanınır');

bitir();
