<?php
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';

$ornek = (string) file_get_contents(__DIR__ . '/ornek-efatura.xml');
/** Basit UBL üretici (önek kullanmadan, varsayılan ad alanlarıyla) */
$ubl = static function (array $o): string {
    $o += ['no' => 'ABC2026000000001', 'uuid' => strtolower(uts_uuid4()), 'tarih' => '2026-09-30', 'tip' => 'SATIS', 'para' => 'TRY', 'vkn' => '1112223334',
           'unvan' => 'Lens Dünyası A.Ş.', 'vade' => '', 'odenecek' => '1100.00', 'satirlar' => [['ad' => 'Aylık lens', 'miktar' => '10', 'tutar' => '1000.00', 'kdv' => '100.00', 'kod' => 'LNS-1']]];
    $satir = '';
    foreach ($o['satirlar'] as $i => $s) {
        $satir .= '<InvoiceLine xmlns="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:b="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
            . '<b:ID>' . ($i + 1) . '</b:ID><b:InvoicedQuantity unitCode="C62">' . $s['miktar'] . '</b:InvoicedQuantity><b:LineExtensionAmount currencyID="TRY">' . $s['tutar'] . '</b:LineExtensionAmount>'
            . '<TaxTotal><b:TaxAmount>' . $s['kdv'] . '</b:TaxAmount><TaxSubtotal><b:Percent>10</b:Percent></TaxSubtotal></TaxTotal>'
            . '<Item><b:Name>' . htmlspecialchars($s['ad']) . '</b:Name>' . (!empty($s['kod']) ? '<SellersItemIdentification><b:ID>' . $s['kod'] . '</b:ID></SellersItemIdentification>' : '')
            . (!empty($s['gtin']) ? '<StandardItemIdentification><b:ID>' . $s['gtin'] . '</b:ID></StandardItemIdentification>' : '') . '</Item>'
            . '<Price><b:PriceAmount>1</b:PriceAmount></Price></InvoiceLine>';
    }
    return '<?xml version="1.0"?><x:Invoice xmlns:x="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:a="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:b="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">'
        . '<b:ProfileID>TEMELFATURA</b:ProfileID><b:ID>' . $o['no'] . '</b:ID><b:UUID>' . $o['uuid'] . '</b:UUID><b:IssueDate>' . $o['tarih'] . '</b:IssueDate>'
        . '<b:InvoiceTypeCode>' . $o['tip'] . '</b:InvoiceTypeCode><b:DocumentCurrencyCode>' . $o['para'] . '</b:DocumentCurrencyCode>'
        . '<a:AccountingSupplierParty><a:Party><a:PartyIdentification><b:ID schemeID="' . (strlen($o['vkn']) === 11 ? 'TCKN' : 'VKN') . '">' . $o['vkn'] . '</b:ID></a:PartyIdentification>'
        . ($o['unvan'] !== '' ? '<a:PartyName><b:Name>' . htmlspecialchars($o['unvan']) . '</b:Name></a:PartyName>' : '<a:Person><b:FirstName>Ahmet</b:FirstName><b:FamilyName>Usta</b:FamilyName></a:Person>')
        . '</a:Party></a:AccountingSupplierParty>'
        . ($o['vade'] ? '<a:PaymentTerms><b:PaymentDueDate>' . $o['vade'] . '</b:PaymentDueDate></a:PaymentTerms>' : '')
        . '<a:TaxTotal><b:TaxAmount>100.00</b:TaxAmount></a:TaxTotal>'
        . '<a:LegalMonetaryTotal><b:TaxExclusiveAmount>1000.00</b:TaxExclusiveAmount><b:PayableAmount>' . $o['odenecek'] . '</b:PayableAmount></a:LegalMonetaryTotal>'
        . $satir . '</x:Invoice>';
};

echo "1) UBL okuma (gerçekçi örnek)\n";
$f = alis_ubl_coz($ornek);
esit('OPT2026000000123', $f['no'], 'fatura no (imza içindeki ID karışmaz)');
esit('3f2504e0-4f89-41d3-9a0c-0305e82c3301', $f['ettn'], 'ETTN küçük harfe çevrildi');
esit('2026-09-28', $f['tarih'], 'tarih');
esit('2026-11-28', $f['vade'], 'vade (PaymentMeans)');
esit(['vkn' => '1234567890', 'unvan' => 'Gözlük Toptan Optik Ltd. Şti.'], ['vkn' => $f['satici']['vkn'], 'unvan' => $f['satici']['unvan']], 'satıcı VKN (MERSİS değil) ve unvan');
esit('9876543210', $f['alici_vkn'], 'alıcı VKN');
esit(12600.0, $f['odenecek'], 'ödenecek');
esit(1145.45, $f['kdv_toplam'], 'KDV toplamı (kalem KDV\'leriyle karışmaz)');
esit(3, count($f['kalemler']), '3 kalem');
$k1 = $f['kalemler'][0];
esit(['RB5154-2000', '08680000000017', 'Ray-Ban', 'RB5154 51-21', 5.0, 'C62'], [$k1['kod'], $k1['gtin'], $k1['marka'], $k1['model'], $k1['miktar'], $k1['birim']], 'kalem kodu, GTIN, marka/model, miktar, birim');
esit(1400.0, alis_birim_maliyet($k1), 'KDV dahil birim maliyet (6363,64 + 636,36) / 5');
esit([], alis_engeller($f), 'engel yok');

echo "2) Önek kullanmayan / farklı önekli UBL, PaymentTerms vadesi, kişi satıcı\n";
$g = alis_ubl_coz($ubl(['vade' => '2026-12-15', 'unvan' => '', 'vkn' => '12345678901']));
ok($g['vade'] === '2026-12-15' && $g['satici']['unvan'] === 'Ahmet Usta' && $g['satici']['vkn'] === '12345678901', 'PaymentTerms vadesi, şahıs satıcı, TCKN');
esit('LNS-1', $g['kalemler'][0]['kod'], 'kalem kodu');

echo "3) Güvenlik ve hatalı dosyalar\n";
hata_bekle(fn() => alis_ubl_coz('<?xml version="1.0"?><!DOCTYPE x [<!ENTITY a SYSTEM "file:///etc/passwd">]><Invoice>&a;</Invoice>'), 'DOCTYPE/XXE reddedilir', 'DOCTYPE');
hata_bekle(fn() => alis_ubl_coz('bu bir pdf değil'), 'XML olmayan dosya', 'XML değil');
hata_bekle(fn() => alis_ubl_coz('<?xml version="1.0"?><CreditNote/>'), 'Invoice olmayan XML', 'değil');
hata_bekle(fn() => alis_ubl_coz('<Invoice><a>'), 'bozuk XML', 'okunamadı');
esit(1, count(alis_engeller(alis_ubl_coz($ubl(['para' => 'USD'])))), 'dövizli fatura engellenir');
esit(1, count(alis_engeller(alis_ubl_coz($ubl(['tip' => 'IADE'])))), 'iade faturası engellenir');
setting_set('firma_vkn', '9876543210');
esit(0, count(array_filter(alis_uyarilar($f), fn($u) => str_contains($u, 'alıcı'))), 'alıcı VKN bizim VKN ile aynı: uyarı yok');
setting_set('firma_vkn', '5555555555');
ok(count(array_filter(alis_uyarilar($f), fn($u) => str_contains($u, 'alıcı'))) === 1, 'farklı alıcı VKN uyarısı');
setting_set('firma_vkn', '');

echo "4) Dosya okuma: XML ve ZIP\n";
$tmp = APP_ROOT . '/yuk';
@mkdir($tmp);
file_put_contents("$tmp/a.xml", $ornek);
$zipYol = "$tmp/paket.zip";
$z = new ZipArchive();
$z->open($zipYol, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$z->addFromString('fatura1.xml', $ornek);
$z->addFromString('alt/fatura2.xml', $ubl([]));
$z->addFromString('stil.xslt', '<x/>');
$z->addFromString('fatura.pdf', '%PDF');
$z->close();
$okunan = alis_dosyalari_oku([['ad' => 'a.xml', 'yol' => "$tmp/a.xml"], ['ad' => 'paket.zip', 'yol' => $zipYol]]);
esit(3, count($okunan), 'tek XML + ZIP içindeki 2 XML (xslt/pdf atlanır)');
ok(str_contains($okunan[2]['ad'], 'paket.zip › fatura2.xml'), 'ZIP içi dosya adı');
// zip bombası koruması
$z = new ZipArchive();
$z->open("$tmp/bomba.zip", ZipArchive::CREATE | ZipArchive::OVERWRITE);
$z->addFromString('buyuk.xml', str_repeat('A', ALIS_DOSYA_SINIRI + 10));
$z->close();
hata_bekle(fn() => alis_dosyalari_oku([['ad' => 'bomba.zip', 'yol' => "$tmp/bomba.zip"]]), 'açılmış boyutu büyük ZIP reddedilir', 'büyük');

echo "5) Kaydetme: yeni tedarikçi, eşleme, stok, maliyet, cari\n";
$rb = insert('frame_items', ['brand' => 'Ray-Ban', 'model' => 'RB5154', 'barcode' => '8680000000017', 'qty' => 2, 'cost' => 900.0, 'price' => 2450.0, 'created_at' => uts_simdi(), 'updated_at' => uts_simdi()]);
esit(null, alis_tedarikci_bul('1234567890'), 'tedarikçi henüz yok');
$o1 = alis_kalem_onerisi(null, $f['kalemler'][0]);
esit(['frame_item_id' => $rb, 'neden' => 'gtin'], $o1, 'GTIN (14 hane) → 13 haneli kart barkodu');
esit(['frame_item_id' => null, 'neden' => ''], alis_kalem_onerisi(null, $f['kalemler'][1]), 'eşleşmeyen kalem');
esit(0, alis_stok_adedi(['miktar' => 2.5]), 'kesirli miktar stoğa işlenmez');
$sonuc = alis_kaydet($f, ['yeni' => true], [1 => ['islem' => 'stok', 'frame_item_id' => $rb], 2 => ['islem' => 'stok', 'frame_item_id' => 'yeni'], 3 => ['islem' => 'yok']], '', $ornek);
$ted = row('SELECT * FROM suppliers WHERE id = ?', [$sonuc['supplier_id']]);
ok($ted['name'] === 'Gözlük Toptan Optik Ltd. Şti.' && $ted['tax_no'] === '1234567890', 'yeni tedarikçi XML\'deki unvan ve VKN ile açıldı');
$inv = row('SELECT * FROM supplier_invoices WHERE id = ?', [$sonuc['invoice_id']]);
ok((float) $inv['amount'] === 12600.0 && $inv['due_date'] === '2026-11-28' && $inv['kaynak'] === 'xml' && $inv['ettn'] === $f['ettn'], 'fatura: tutar, vade, kaynak, ETTN');
esit(7, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$rb]), 'eşleşen karta 5 adet girdi (2 + 5)');
esit(1400.0, (float) scalar('SELECT cost FROM frame_items WHERE id = ?', [$rb]), 'kartın maliyeti KDV dahil birim fiyatla güncellendi');
$vogue = row("SELECT * FROM frame_items WHERE brand = 'Vogue'");
ok($vogue && $vogue['model'] === 'VO5286 Havana 52' && (int) $vogue['qty'] === 3 && $vogue['barcode'] === 'VO5286-W656' && (int) $vogue['supplier_id'] === $sonuc['supplier_id'], 'yeni kart: ad bölündü, adet 3, kod barkod oldu, tedarikçi bağlı');
esit(3, (int) scalar('SELECT COUNT(*) FROM supplier_invoice_lines WHERE invoice_id = ?', [$sonuc['invoice_id']]), '3 kalem kaydedildi');
esit(0, (int) scalar('SELECT stok_adet FROM supplier_invoice_lines WHERE invoice_id = ? AND sira = 3', [$sonuc['invoice_id']]), 'kargo stoğa girmedi');
esit(2, (int) scalar('SELECT COUNT(*) FROM urun_eslesmeleri WHERE supplier_id = ?', [$sonuc['supplier_id']]), 'iki ürün kodu hatırlandı');
esit(['frame_item_id' => (int) $vogue['id'], 'neden' => 'hafiza'], alis_kalem_onerisi($sonuc['supplier_id'], $f['kalemler'][1]), 'sonraki faturada Vogue kodu hafızadan eşleşir');
esit(['frame_item_id' => (int) $vogue['id'], 'neden' => 'kod'], alis_kalem_onerisi(null, $f['kalemler'][1]), 'başka tedarikçide kod barkod olarak eşleşir');
esit(2, (int) scalar("SELECT COUNT(*) FROM frame_moves WHERE reason = 'giris' AND note LIKE 'Alış faturası%'"), 'stok hareketleri yazıldı');

echo "6) Mükerrer koruması ve hafıza\n";
hata_bekle(fn() => alis_kaydet($f, ['id' => $sonuc['supplier_id']], [], '', $ornek), 'aynı ETTN ikinci kez kaydedilmez', 'zaten kayıtlı');
$fBaskaEttn = $f;
$fBaskaEttn['ettn'] = '';
hata_bekle(fn() => alis_kaydet($fBaskaEttn, ['id' => $sonuc['supplier_id']], [], '', $ornek), 'ETTN yoksa tedarikçi + fatura no ile yakalanır', 'zaten kayıtlı');
// Hafıza: kod barkod olmayan kart
$kart2 = insert('frame_items', ['brand' => 'Lens', 'model' => 'Aylık', 'qty' => 0, 'created_at' => uts_simdi(), 'updated_at' => uts_simdi()]);
$g2 = alis_ubl_coz($ubl(['no' => 'ABC1', 'vkn' => '1234567890']));
alis_kaydet($g2, ['id' => $sonuc['supplier_id']], [1 => ['islem' => 'stok', 'frame_item_id' => $kart2]], '2026-10-30', '');
esit(['frame_item_id' => $kart2, 'neden' => 'hafiza'], alis_kalem_onerisi($sonuc['supplier_id'], $g2['kalemler'][0]), 'elle seçilen eşleme hafızadan önerilir');
esit('2026-10-30', scalar("SELECT due_date FROM supplier_invoices WHERE invoice_no = 'ABC1'"), 'elle girilen vade XML\'de yokken kaydedildi');
esit(null, alis_kalem_onerisi(999, $g2['kalemler'][0])['frame_item_id'], 'hafıza tedarikçiye özel');

echo "7) Fatura silme stoğu geri alır\n";
$once = (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$rb]);
alis_fatura_sil($sonuc['invoice_id']);
esit($once - 5, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$rb]), 'silinen faturanın stoğu geri alındı');
esit(0, (int) scalar('SELECT COUNT(*) FROM supplier_invoice_lines WHERE invoice_id = ?', [$sonuc['invoice_id']]), 'kalemler silindi');
$yeniden = alis_kaydet($f, ['id' => $sonuc['supplier_id']], [1 => ['islem' => 'stok', 'frame_item_id' => $rb]], '', $ornek);
ok($yeniden['invoice_id'] > 0, 'silindikten sonra yeniden yüklenebilir');
$tid = $sonuc['supplier_id'];

echo "8) Senet: ver → cari kapanır → öde → kasa\n";
$cari = static fn(int $s): float => round((float) scalar('SELECT COALESCE(SUM(amount),0) FROM supplier_invoices WHERE supplier_id = ?', [$s]) - (float) scalar('SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE supplier_id = ?', [$s]), 2);
$cariOnce = $cari($tid);
$sid = senet_ver($tid, 12600.0, '2026-11-28', 'S-001', '2026-09-30', $yeniden['invoice_id']);
esit(round($cariOnce - 12600.0, 2), $cari($tid), 'senet verilince cari senet tutarı kadar kapandı');
esit(12600.0, senet_bekleyen_toplam($tid), 'ödenmemiş senet toplamı');
$pay = row('SELECT * FROM supplier_payments WHERE id = ?', [(int) senet($sid)['payment_id']]);
ok($pay['method'] === 'senet' && str_starts_with((string) $pay['created_at'], '2026-09-30'), 'cari kapama kaydı: yöntem senet, düzenleme tarihli');
hata_bekle(fn() => senet_ver($tid, 100, '2026-11-01', 'S-001'), 'aynı tedarikçiye aynı senet no', 'aynı numaralı');
hata_bekle(fn() => senet_ver($tid, 100, '2026-01-01', '', '2026-09-30'), 'vade düzenlemeden önce olamaz', 'önce');
hata_bekle(fn() => senet_ver($tid, 0, '2026-12-01'), 'sıfır tutar', '0');
hata_bekle(fn() => senet_ver($tid, 100, '2026-02-30'), 'geçersiz tarih', 'geçersiz');
ok(senet_odeme_bagli_mi((int) $pay['id']) !== null, 'senede bağlı cari kaydı tanınır (silinemez)');
hata_bekle(fn() => senet_ode($sid, date('Y-m-d', strtotime('+2 days')), 'nakit'), 'ileri tarihli ödeme olmaz', 'ileri');
senet_ode($sid, date('Y-m-d'), 'nakit');
esit('odendi', senet($sid)['durum'], 'senet ödendi');
esit(round($cariOnce - 12600.0, 2), $cari($tid), 'ödeme cariyi ikinci kez değiştirmedi');
esit(12600.0, (float) scalar("SELECT COALESCE(SUM(tutar),0) FROM tedarikci_senetleri WHERE durum = 'odendi' AND odeme_yontemi = 'nakit' AND odeme_tarihi = ?", [date('Y-m-d')]), 'kasa sorgusu nakit ödenen senedi bulur');
hata_bekle(fn() => senet_ode($sid, date('Y-m-d'), 'nakit'), 'ödenmiş senet tekrar ödenmez', 'durumda değil');
hata_bekle(fn() => senet_iptal($sid), 'ödenmiş senet iptal edilmez', 'ödenmemiş');
senet_odeme_geri_al($sid);
esit('bekliyor', senet($sid)['durum'], 'ödeme geri alındı');
hata_bekle(fn() => alis_fatura_sil($yeniden['invoice_id']), 'senede bağlı fatura silinmez', 'senet');
senet_iptal($sid);
esit($cariOnce, $cari($tid), 'iptal edilen senet cariye borç olarak döndü');
esit(null, row('SELECT id FROM supplier_payments WHERE id = ?', [(int) $pay['id']]), 'cari kapama kaydı silindi');

echo "9) Ödeme takvimi ve vadesi açık faturalar (FIFO)\n";
$t2 = insert('suppliers', ['name' => 'Cam Lab', 'created_at' => uts_simdi(), 'updated_at' => uts_simdi()]);
$bugun = date('Y-m-d');
insert('supplier_invoices', ['supplier_id' => $t2, 'invoice_no' => 'C1', 'invoice_date' => date('Y-m-d', strtotime('-60 days')), 'amount' => 1000, 'due_date' => date('Y-m-d', strtotime('-30 days')), 'created_at' => uts_simdi()]);
insert('supplier_invoices', ['supplier_id' => $t2, 'invoice_no' => 'C2', 'invoice_date' => date('Y-m-d', strtotime('-20 days')), 'amount' => 2000, 'due_date' => date('Y-m-d', strtotime('+3 days')), 'created_at' => uts_simdi()]);
insert('supplier_invoices', ['supplier_id' => $t2, 'invoice_no' => 'C3', 'invoice_date' => date('Y-m-d', strtotime('-5 days')), 'amount' => 500, 'created_at' => uts_simdi()]);
insert('supplier_payments', ['supplier_id' => $t2, 'amount' => 1500, 'method' => 'havale', 'created_at' => uts_simdi()]);
$acik = array_values(array_filter(vadesi_acik_faturalar(), fn($i) => (int) $i['supplier_id'] === $t2));
esit(1, count($acik), 'C1 ödemeyle kapandı, C3 vadesiz: listede yalnız C2');
esit(['C2', 1500.0], [$acik[0]['invoice_no'], $acik[0]['kalan']], 'C2\'nin 500\'ü ödendi, kalan 1500');
$s3 = senet_ver($t2, 750, date('Y-m-d', strtotime('-2 days')), 'G-1', date('Y-m-d', strtotime('-10 days')));
$s4 = senet_ver($t2, 250, date('Y-m-d', strtotime('+40 days')), 'G-2');
$tk = odeme_takvimi();
ok(in_array($s3, array_column(array_filter($tk['gecikmis'], fn($o) => $o['tur'] === 'senet'), 'id'), true), 'vadesi geçen senet "gecikmiş"te');
$c2 = array_values(array_filter($tk['yakin'], fn($o) => $o['tur'] === 'fatura' && $o['no'] === 'C2'));
esit(500.0, $c2[0]['tutar'] ?? null, 'senetle kapanan kısım faturadan da düştü (1500 - 750 - 250)');
ok(in_array($s4, array_column($tk['sonra'], 'id'), true) || in_array($s4, array_column($tk['bu_ay'], 'id'), true), '40 gün sonraki senet ileri grupta');
ok(senet_rozet() >= 1, 'menü rozeti gecikmiş senedi sayar');

echo "10) Vade uyarısı (günde bir kez, yalnız yöneticilere)\n";
$GLOBALS['__push'] = [];
ok(senet_uyari_gorev(), 'uyarı gönderildi');
esit([1], $GLOBALS['__push'][0][1] ?? null, 'yalnız süper yetkiliye');
ok(str_contains($GLOBALS['__push'][0][0]['title'], 'Vadesi geçmiş'), 'gecikme başlığı');
ok(!senet_uyari_gorev(), 'aynı gün ikinci uyarı yok');

echo "11) Tutar yazıyla\n";
esit('Bin İki Yüz Otuz Dört Türk Lirası Elli Kuruş', tutar_yaziyla(1234.50), '1.234,50');
esit('Yüz Türk Lirası', tutar_yaziyla(100), '100');
esit('On İki Bin Altı Yüz Türk Lirası', tutar_yaziyla(12600), '12.600');
esit('Bir Milyon Bin Bir Türk Lirası Bir Kuruş', tutar_yaziyla(1001001.01), '1.001.001,01 (bir milyon, bin)');
esit('İki Yüz Bin Türk Lirası', tutar_yaziyla(200000), '200.000');
esit('Sıfır Türk Lirası Doksan Dokuz Kuruş', tutar_yaziyla(0.99), '0,99');
esit('Yüz Bir Bin Yüz On Türk Lirası', tutar_yaziyla(101110), '101.110');
esit('Bir Türk Lirası', tutar_yaziyla(0.999), 'yuvarlama 0,999 → 1');

echo "12) Geçici yükleme oturuma bağlı\n";
$_SESSION = [];
$a = alis_gecici_kaydet([['ad' => 'x.xml', 'xml' => $ornek]]);
esit(1, count(alis_gecici_oku($a) ?? []), 'aynı oturum okur');
$_SESSION = [];
esit(null, alis_gecici_oku($a), 'başka oturum okuyamaz');
esit(null, alis_gecici_oku('../../etc/passwd'), 'anahtar biçimi denetlenir');

bitir();
