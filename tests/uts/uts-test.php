<?php
declare(strict_types=1);
require __DIR__ . '/ortam.php';
require __DIR__ . '/sahte-uts.php';

$GS = "\x1D";
$kk = static fn(string $gtin, string $seri, ?string $skt = null, bool $gs = true): string =>
    '01' . $gtin . '21' . $seri . ($skt ? ($gs ? "\x1D" : '') . '17' . $skt : '');

echo "1) Karekod çözümleme\n";
$k = uts_karekod_coz($kk('08680000000017', 'SN12345', '281231'));
esit('08680000000017', $k['uno'], 'GS ile UNO');
esit('SN12345', $k['sno'], 'GS ile SNO');
esit('2028-12-31', $k['skt'], 'GS ile SKT');
$k = uts_karekod_coz($kk('08680000000017', 'SN12345', '281231', false));
esit('SN12345', $k['sno'], 'GS yokken seri sondaki 17YYAAGG bloğunu yutmaz');
esit('2028-12-31', $k['skt'], 'GS yokken SKT ayrılır');
$k = uts_karekod_coz('(01)08680000000024(21)AB-9');
esit(['08680000000024', 'AB-9'], [$k['uno'], $k['sno']], 'parantezli yazım');
$k = uts_karekod_coz('0108680000000031' . '10LOT77' . $GS . '17270630');
esit(['LOT77', '', '2027-06-30'], [$k['lno'], $k['sno'], $k['skt']], 'lot takipli (lens)');
esit('', uts_karekod_coz('8680000000017')['uno'], 'düz EAN-13 ÜTS karekodu sayılmaz');
esit('', uts_karekod_coz('')['uno'], 'boş kod');
$k = uts_karekod_coz('0108680000000017' . '21' . 'X1711' . $GS . '17281231');
esit('X1711', $k['sno'], 'seri içinde "17" geçse de GS varsa bozulmaz');

echo "2) Yanıt ayrıştırma\n";
$id = uts_uuid4();
$r = uts_yanit_coz(200, json_encode(['SNC' => ['BID' => $id], 'MSJ' => [['TIP' => 'BILGI', 'MET' => 'İşlem başarılı']]]));
ok($r['ok'] && $r['id'] === $id, 'başarı + BID');
$r = uts_yanit_coz(200, json_encode(['sonuc' => ['liste' => [['x' => 1]], 'kayit' => ['id' => strtoupper($id)]]]));
ok($r['ok'] && $r['id'] === $id, 'iç içe id, büyük harf UUID küçültülür');
$r = uts_yanit_coz(200, json_encode(['MSJ' => [['TIP' => 'HATA', 'KOD' => 'UTS-1', 'MET' => 'Ürün bulunamadı']]]));
ok(!$r['ok'] && $r['tur'] === 'kalici' && str_contains($r['mesaj'], 'Ürün bulunamadı'), '200 + HATA = kalıcı');
esit('yetki', uts_yanit_coz(401, '{}')['tur'], '401 = yetki');
esit('yetki', uts_yanit_coz(400, json_encode(['MSJ' => [['TIP' => 'HATA', 'MET' => 'Token geçersiz']]]))['tur'], 'token mesajı = yetki');
esit('kalici', uts_yanit_coz(400, json_encode(['MSJ' => [['TIP' => 'HATA', 'MET' => 'Kurum yetkili değil']]]))['tur'], '"yetkili" kelimesi yetki hatası sayılmaz');
esit('gecici', uts_yanit_coz(503, 'Service Unavailable')['tur'], '503 = geçici');
esit('gecici', uts_yanit_coz(0, '', 'timeout')['tur'], 'bağlantı hatası = geçici');
esit('kalici', uts_yanit_coz(404, '<html>')['tur'], '404 = kalıcı');

echo "3) Adres güvenliği\n";
ok(uts_adres_guvenli_mi('https://utsuygulama.saglik.gov.tr/UTS/uh/rest/bildirim/alma/ekle'), 'resmi adres');
ok(!uts_adres_guvenli_mi('http://utsuygulama.saglik.gov.tr/x'), 'http reddedilir');
ok(!uts_adres_guvenli_mi('https://saglik.gov.tr.kotu.com/x'), 'sahte alt alan reddedilir');
ok(!uts_adres_guvenli_mi('https://kotusaglik.gov.tr/x'), 'sonek hilesi reddedilir');
ok(!uts_adres_guvenli_mi('https://a@utsuygulama.saglik.gov.tr/x'), 'kullanıcı bilgili adres reddedilir');
ok(!uts_adres_guvenli_mi('https://utsuygulama.saglik.gov.tr:8443/x'), 'özel port reddedilir');
setting_set('uts_taban_canli', 'https://kotu.example.com/UTS');
esit(UTS_TABAN_VARSAYILAN['canli'], uts_taban_url('canli'), 'güvensiz özel taban adres yok sayılır');
setting_set('uts_taban_canli', '');
setting_set('uts_yol_alma', '/../../etc');
esit('/bildirim/alma/ekle', uts_yol('alma'), 'geçersiz yol yok sayılır');
setting_set('uts_yol_alma', '/v2/bildirim/alma/ekle');
esit('/v2/bildirim/alma/ekle', uts_yol('alma'), 'geçerli özel yol kullanılır');
setting_set('uts_yol_alma', '');

echo "4) Token şifreli saklanır\n";
gizli_ayar_yaz('uts_token', 'dogru-token');
ok(str_starts_with(setting('uts_token'), 'g1:') || str_starts_with(setting('uts_token'), 'g2:'), 'veritabanında şifreli');
ok(!str_contains(setting('uts_token'), 'dogru-token'), 'açık metin yok');
esit('dogru-token', gizli_ayar('uts_token'), 'çözülür');

/* ---------------- DENEME MODU AKIŞI ---------------- */
echo "5) Deneme modu: okut → sipariş → teslim → bildirim → geri al\n";
setting_set('uts_ortam', 'deneme');
$cerceve = insert('frame_items', ['brand' => 'Ray-Ban', 'model' => 'RB5154', 'barcode' => '8680000000017', 'qty' => 3, 'price' => 2450.0, 'created_at' => uts_simdi(), 'updated_at' => uts_simdi()]);
$u = uts_stoga_okut($kk('08680000000017', 'RB001', null), 'cerceve');
esit($cerceve, (int) $u['frame_item_id'], 'GTIN (başında 0 olmadan) çerçeve kartına bağlandı');
esit('stokta', $u['durum'], 'okutulan ürün stokta');
hata_bekle(fn() => uts_stoga_okut($kk('08680000000017', 'RB001', null), 'cerceve'), 'aynı seri iki kez kaydedilemez', 'zaten kayıtlı');

$sip = insert('orders', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);
$r = uts_siparise_okut($sip, $kk('08680000000017', 'RB001', null));
esit(2450.0, $r['fiyat'], 'okutunca fiyat gelir');
esit($cerceve, (int) scalar('SELECT frame_item_id FROM orders WHERE id = ?', [$sip]), 'siparişin çerçevesi işlendi');
esit(2, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$cerceve]), 'çerçeve adedi 1 düştü');
$sip2 = insert('orders', ['first_name' => 'Ali', 'last_name' => 'Kaya']);
hata_bekle(fn() => uts_siparise_okut($sip2, $kk('08680000000017', 'RB001', null)), 'ayrılmış ürün başka siparişe okutulamaz', 'ayrılmış');

// Kayıtsız ürün siparişte okutulunca otomatik stoğa kaydedilir
$r2 = uts_siparise_okut($sip, $kk('08680000000017', 'RB002', null));
ok(str_contains(implode(' ', $r2['mesajlar']), 'kaydedildi'), 'kayıtsız ürün okutunca stoğa kaydedilir ve uyarılır');
esit(2, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$cerceve]), 'ikinci çerçeve siparişin çerçevesini değiştirmez / adet düşmez');
uts_siparisten_cikar($sip, (int) $r2['urun']['id']);
esit(null, uts_urun((int) $r2['urun']['id'])['order_id'], 'siparişten çıkarılan ürün serbest');
esit($cerceve, (int) scalar('SELECT frame_item_id FROM orders WHERE id = ?', [$sip]), 'aynı karttan diğer ürün hâlâ siparişte: çerçeve korunur');

// Teslim (ücretli)
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", ['2026-09-30 15:00:00', $sip]);
$m = uts_siparis_asama_degisti($sip, 'hazirlandi', 'teslim_edildi');
ok(str_contains(implode(' ', $m), 'tüketiciye verme'), 'teslim mesajı');
$tv = row("SELECT * FROM uts_bildirimler WHERE tur = 'tuketiciye_verme' AND order_id = ?", [$sip]);
esit('bekliyor', $tv['durum'], 'otomatik modda sıraya girer');
$g = json_decode($tv['govde'], true);
esit(['UNO' => '08680000000017', 'SNO' => 'RB001', 'GIT' => '2026-09-30', 'BEN' => 'HAYIR'], $g, 'gövde: UNO, SNO, GIT (teslim tarihi), BEN; ad/TC yok');
esit('satildi', uts_urun((int) $tv['urun_id'])['durum'], 'ürün satıldı');
$o = uts_kuyrugu_isle();
esit(1, $o['gonderilen'], 'deneme modunda gönderildi sayılır');
$tv = row('SELECT * FROM uts_bildirimler WHERE id = ?', [(int) $tv['id']]);
ok($tv['durum'] === 'gonderildi' && uts_uuid_mi((string) $tv['uts_id']) && $tv['ortam'] === 'deneme', 'deneme kaydı: uuid + ortam=deneme');
// Aynı teslim ikinci kez tetiklenirse çift bildirim olmaz
uts_siparis_asama_degisti($sip, 'hazirlandi', 'teslim_edildi');
esit(1, (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE tur = 'tuketiciye_verme' AND order_id = ?", [$sip]), 'tekrar tetiklenen teslim çift bildirim üretmez');

// Teslim geri alındı → tüketiciden iade
$m = uts_siparis_asama_degisti($sip, 'teslim_edildi', 'hazirlandi');
$ti = row("SELECT * FROM uts_bildirimler WHERE tur = 'tuketiciden_iade' AND order_id = ?", [$sip]);
ok($ti && (int) $ti['ilgili_id'] === (int) $tv['id'], 'gönderilmiş satış için iade bildirimi, satışa bağlı');
esit('stokta', uts_urun((int) $tv['urun_id'])['durum'], 'ürün stoğa döndü (siparişte kalır)');
uts_kuyrugu_isle();
$ti = row('SELECT * FROM uts_bildirimler WHERE id = ?', [(int) $ti['id']]);
esit('gonderildi', $ti['durum'], 'iade gönderildi');
esit($tv['uts_id'], json_decode($ti['govde'], true)['TID'] ?? null, 'iade gövdesinde TID = satış bildirim no');
// Yeniden teslim → yeni satış bildirimi
uts_siparis_asama_degisti($sip, 'hazirlandi', 'teslim_edildi');
esit(2, (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE tur = 'tuketiciye_verme' AND order_id = ?", [$sip]), 'yeniden teslimde yeni satış bildirimi');

echo "6) SGK'lı sipariş: bildirim YOK\n";
$sgk = insert('orders', ['first_name' => 'Veli', 'last_name' => 'Er', 'sgk_erecete' => '1A2B3C4', 'sgk_amount' => 0]);
$u3 = uts_stoga_okut($kk('08680000000017', 'RB003', null), 'cerceve');
uts_siparise_okut($sgk, $kk('08680000000017', 'RB003', null));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $sgk]);
$m = uts_siparis_asama_degisti($sgk, 'hazirlandi', 'teslim_edildi');
esit(0, (int) scalar('SELECT COUNT(*) FROM uts_bildirimler WHERE order_id = ?', [$sgk]), 'SGK siparişinde hiç bildirim yok');
esit(['sgk', 'sgk'], [uts_urun((int) $u3['id'])['durum'], uts_urun((int) $u3['id'])['satis_turu']], 'ürün SGK · Medula çıkışı');
ok(str_contains(implode(' ', $m), 'Medula'), 'personele Medula mesajı');
$sgk2 = insert('orders', ['first_name' => 'Can', 'last_name' => 'Su', 'sgk_amount' => 450.0]);
ok(uts_siparis_sgk_mi(row('SELECT * FROM orders WHERE id = ?', [$sgk2])), 'yalnızca SGK katkısı olan sipariş de SGK sayılır');
uts_siparis_asama_degisti($sgk, 'teslim_edildi', 'hazirlandi');
esit('stokta', uts_urun((int) $u3['id'])['durum'], 'SGK teslimi geri alınınca stoğa döner, bildirim yok');
esit(0, (int) scalar('SELECT COUNT(*) FROM uts_bildirimler WHERE order_id = ?', [$sgk]), 'SGK geri almada da bildirim yok');

echo "7) Onaylı mod\n";
setting_set('uts_gonderim', 'onayli');
$s3 = insert('orders', ['first_name' => 'Deniz', 'last_name' => 'Ak']);
uts_stoga_okut($kk('08680000000017', 'RB004', null), 'cerceve');
uts_siparise_okut($s3, $kk('08680000000017', 'RB004', null));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $s3]);
uts_siparis_asama_degisti($s3, 'hazirlandi', 'teslim_edildi');
$b = row("SELECT * FROM uts_bildirimler WHERE order_id = ?", [$s3]);
esit('onay_bekliyor', $b['durum'], 'onaylı modda onay bekler');
uts_kuyrugu_isle();
esit('onay_bekliyor', scalar('SELECT durum FROM uts_bildirimler WHERE id = ?', [(int) $b['id']]), 'kuyruk onaysızı göndermez');
// Onaydan önce teslim geri alınırsa bildirim iptal olur, iade gerekmez
uts_siparis_asama_degisti($s3, 'teslim_edildi', 'hazirlandi');
esit('iptal', scalar('SELECT durum FROM uts_bildirimler WHERE id = ?', [(int) $b['id']]), 'gönderilmemiş satış geri almada iptal');
esit(0, (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE tur = 'tuketiciden_iade' AND order_id = ?", [$s3]), 'gönderilmemiş satış için iade yok');
uts_siparis_asama_degisti($s3, 'hazirlandi', 'teslim_edildi');
$b2 = row("SELECT * FROM uts_bildirimler WHERE order_id = ? AND durum = 'onay_bekliyor'", [$s3]);
esit(1, uts_bildirimleri_onayla([(int) $b2['id']]), 'onayla');
uts_kuyrugu_isle();
esit('gonderildi', scalar('SELECT durum FROM uts_bildirimler WHERE id = ?', [(int) $b2['id']]), 'onaydan sonra gönderilir');
setting_set('uts_gonderim', 'otomatik');

echo "8) Sipariş iptali ürünleri serbest bırakır\n";
$s4 = insert('orders', ['first_name' => 'Ece', 'last_name' => 'Nur']);
$u5 = uts_stoga_okut($kk('08680000000017', 'RB005', null), 'cerceve');
uts_siparise_okut($s4, $kk('08680000000017', 'RB005', null));
uts_siparis_asama_degisti($s4, 'atolyede', 'iptal');
ok(uts_urun((int) $u5['id'])['order_id'] === null && uts_urun((int) $u5['id'])['durum'] === 'stokta', 'iptalde ürün stokta ve serbest');
hata_bekle(fn() => uts_siparise_okut($sip, $kk('08680000000017', 'RB005', null)), 'teslim edilmiş siparişe okutma yapılamaz', 'Teslim');

echo "9) Lot takipli lens: bölme ve birleştirme\n";
$lot = '0108680000000048' . '10L2026A' . $GS . '17271231';
$ul = uts_stoga_okut($lot, 'lens', 10);
esit(10, (int) $ul['adet'], 'lot 10 adet');
uts_stoga_okut($lot, 'lens', 5);
esit(15, (int) uts_urun((int) $ul['id'])['adet'], 'aynı lot okutunca adet eklenir');
$s5 = insert('orders', ['first_name' => 'Lens', 'last_name' => 'Müşteri']);
$r = uts_siparise_okut($s5, $lot, 'lens', 4);
esit(4, (int) $r['urun']['adet'], 'siparişe 4 adet ayrıldı (ayrı satır)');
esit(11, (int) uts_urun((int) $ul['id'])['adet'], 'ana lotta 11 kaldı');
hata_bekle(fn() => uts_siparise_okut($s5, $lot, 'lens', 50), 'stoktan fazla lot satılamaz', 'adet var');
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $s5]);
uts_siparis_asama_degisti($s5, 'hazirlandi', 'teslim_edildi');
$lb = json_decode((string) scalar("SELECT govde FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciye_verme'", [$s5]), true);
esit(['UNO' => '08680000000048', 'LNO' => 'L2026A', 'ADT' => 4, 'GIT' => date('Y-m-d'), 'BEN' => 'HAYIR'], $lb, 'lot satış gövdesi: LNO + ADT');
$s6 = insert('orders', ['first_name' => 'X', 'last_name' => 'Y']);
$r6 = uts_siparise_okut($s6, $lot, 'lens', 3);
uts_siparisten_cikar($s6, (int) $r6['urun']['id']);
esit(11, (int) uts_urun((int) $ul['id'])['adet'], 'siparişten çıkan lot parçası ana lota birleşir');
esit(null, uts_urun((int) $r6['urun']['id']), 'parça satırı silindi');

echo "10) SKT geçmiş ürün satılamaz, imha edilir\n";
$eski = uts_stoga_okut('0108680000000055' . '21' . 'EXP1' . $GS . '17200101', 'lens');
hata_bekle(fn() => uts_siparise_okut($s6, '0108680000000055' . '21' . 'EXP1' . $GS . '17200101'), 'SKT geçmiş satılamaz', 'son kullanma');
hata_bekle(fn() => uts_imha_et([(int) $eski['id']], 'SON_KULLANMA_TARIHI_GECMIS', '', ''), 'imhada belge no zorunlu', 'zorunlu');
hata_bekle(fn() => uts_imha_et([(int) $eski['id']], 'DIGER', '', 'T-1'), 'Diğer gerekçesinde açıklama zorunlu', 'açıklama');
esit(1, uts_imha_et([(int) $eski['id']], 'SON_KULLANMA_TARIHI_GECMIS', '', 'TUT-2026-1'), 'imha edildi');
$ib = row("SELECT * FROM uts_bildirimler WHERE tur = 'imha' AND urun_id = ?", [(int) $eski['id']]);
esit(['UNO' => '08680000000055', 'SNO' => 'EXP1', 'GRK' => 'SON_KULLANMA_TARIHI_GECMIS', 'BNO' => 'TUT-2026-1'], json_decode($ib['govde'], true), 'imha gövdesi');
ok(uts_bildirim_iptal((int) $ib['id']), 'gönderilmemiş imha iptal edilebilir');
esit('stokta', uts_urun((int) $eski['id'])['durum'], 'imha iptalinde ürün stoğa döner');
esit(0, uts_imha_et([(int) $u5['id'] + 999], 'SON_KULLANMA_TARIHI_GECMIS', '', 'X'), 'olmayan ürün imha edilmez');

echo "11) Tedarikçiye iade\n";
$ted = insert('suppliers', ['name' => 'Toptancı']);
$u7 = uts_stoga_okut($kk('08680000000017', 'RB007', null), 'cerceve', 1, true);
$qOnce = (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$cerceve]);
hata_bekle(fn() => uts_tedarikciye_iade([(int) $u7['id']], $ted, 'IADE-1'), 'ÜTS kurum no yoksa iade yapılamaz', 'kurum numarası');
q('UPDATE suppliers SET uts_kurum_no = ? WHERE id = ?', ['9988776655', $ted]);
esit(1, uts_tedarikciye_iade([(int) $u7['id']], $ted, 'IADE-1'), 'iade edildi');
esit($qOnce - 1, (int) scalar('SELECT qty FROM frame_items WHERE id = ?', [$cerceve]), 'çerçeve adedi iade kadar düştü');
$vb = json_decode((string) scalar("SELECT govde FROM uts_bildirimler WHERE tur = 'verme' AND urun_id = ?", [(int) $u7['id']]), true);
esit(['KUN' => '9988776655', 'BNO' => 'IADE-1'], ['KUN' => $vb['KUN'], 'BNO' => $vb['BNO']], 'verme gövdesi KUN + BNO');
esit(1, (int) scalar("SELECT COUNT(*) FROM frame_moves WHERE reason = 'tedarikci_iade'"), 'çerçeve hareketi "tedarikçiye iade"');

/* ---------------- GERÇEK ORTAM (sahte ÜTS) ---------------- */
echo "12) Test ortamı: sahte ÜTS ile mal kabul → satış\n";
$uts = new SahteUts();
$GLOBALS['__uts_tasiyici'] = $uts;
q("DELETE FROM uts_bildirimler WHERE durum IN ('bekliyor','hata','onay_bekliyor')");
setting_set('uts_ortam', 'test');
setting_set('uts_kurum_no', '1112223334');
$v1 = $uts->gonder('08690000000019', 'VG100', 'Vogue VO5286 Havana 52');
$v2 = $uts->gonder('08690000000026', 'CAM55', 'Essilor Varilux Organik Cam');
$r = uts_gelenleri_getir();
ok($r['ok'] && $r['sayi'] === 2, 'ÜTS\'den 2 gelen ürün');
esit('1112223334', $uts->istekler[count($uts->istekler) - 1]['govde']['KUN'] ?? null, 'sorguda kurum no gönderildi');
esit('dogru-token', $uts->istekler[0]['basliklar']['utsToken'] ?? null, 'utsToken başlığı');
ok(str_starts_with($uts->istekler[0]['url'], 'https://utstest.saglik.gov.tr/UTS/uh/rest/'), 'test ortamı adresi');
$g1 = row("SELECT * FROM uts_urunler WHERE sno = 'VG100'");
ok($g1['durum'] === 'gelen' && $g1['vbi'] === $v1 && $g1['gonderen'] === 'Toptancı A.Ş.' && $g1['gonderen_kurum'] === '1234567890', 'gelen kaydı alanları');
esit('cerceve', $g1['kategori'], 'kategori tahmini: çerçeve');
esit('cam', scalar("SELECT kategori FROM uts_urunler WHERE sno = 'CAM55'"), 'kategori tahmini: cam');
uts_gelenleri_getir();
esit(2, (int) scalar("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'gelen'"), 'tekrar getirmek çift kayıt açmaz');

$n = uts_gelenleri_kabul_et([(int) $g1['id']], [(int) $g1['id'] => 'cerceve'], true, true);
esit(1, $n, 'kabul edildi');
esit('alma_bekliyor', uts_urun((int) $g1['id'])['durum'], 'alma bildirimi bekliyor');
$yeniKart = row("SELECT * FROM frame_items WHERE barcode = '08690000000019'");
ok($yeniKart && $yeniKart['brand'] === 'Vogue' && $yeniKart['model'] === 'VO5286 Havana 52' && (int) $yeniKart['qty'] === 1, 'yeni çerçeve kartı: marka/model ayrıldı, adet 1');
$s7 = insert('orders', ['first_name' => 'Gerçek', 'last_name' => 'Müşteri']);
hata_bekle(fn() => uts_siparise_okut($s7, $kk('08690000000019', 'VG100')), 'alma bildirimi tamamlanmadan satılamaz', 'satılamaz');
$o = uts_kuyrugu_isle();
esit(1, $o['gonderilen'], 'alma gönderildi');
esit('stokta', uts_urun((int) $g1['id'])['durum'], 'alma sonrası stokta');
esit(1, (int) scalar("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'gelen'"), 'kalan gelen: cam');
uts_gelenleri_getir();
esit(1, (int) scalar("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'gelen'"), 'kabul edilen ÜTS listesinden kalktı, cam hâlâ gelen');

uts_siparise_okut($s7, $kk('08690000000019', 'VG100'));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $s7]);
uts_siparis_asama_degisti($s7, 'hazirlandi', 'teslim_edildi');
$o = uts_kuyrugu_isle();
esit(1, $o['gonderilen'], 'tüketiciye verme ÜTS\'ye iletildi');
$tvGercek = row("SELECT * FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciye_verme'", [$s7]);
ok(isset($uts->tv[$tvGercek['uts_id']]), 'ÜTS\'nin verdiği bildirim no saklandı');

echo "13) Hata türleri\n";
// Kalıcı: ürün ÜTS stoğunda yok (ör. Medula zaten düşmüş)
$u8 = uts_stoga_okut($kk('08690000000019', 'VG200'), 'cerceve');   // okutma: ÜTS'de alma yapılmamış
$s8 = insert('orders', ['first_name' => 'H', 'last_name' => 'K']);
uts_siparise_okut($s8, $kk('08690000000019', 'VG200'));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $s8]);
uts_siparis_asama_degisti($s8, 'hazirlandi', 'teslim_edildi');
$o = uts_kuyrugu_isle();
$hb = row("SELECT * FROM uts_bildirimler WHERE order_id = ?", [$s8]);
ok($hb['durum'] === 'hata' && (int) $hb['deneme'] === 99 && str_contains((string) $hb['son_hata'], 'UTS-1305'), 'kalıcı hata: otomatik tekrar yok, ÜTS mesajı görünür');
$istekSayisi = count($uts->istekler);
uts_kuyrugu_isle();
esit($istekSayisi, count($uts->istekler), 'kalıcı hatalı kayıt tekrar gönderilmez');
ok(uts_bildirim_elle_tamam((int) $hb['id']), 'ÜTS\'de elle yapıldı işaretlenir');
esit('elle', scalar('SELECT durum FROM uts_bildirimler WHERE id = ?', [(int) $hb['id']]), 'durum elle');
// Elle yapılmış satışın iadesi otomatik yapılamaz (TID yok)
uts_siparis_asama_degisti($s8, 'teslim_edildi', 'hazirlandi');
uts_kuyrugu_isle();
$ihb = row("SELECT * FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciden_iade'", [$s8]);
ok($ihb['durum'] === 'hata' && (int) $ihb['deneme'] === 99 && str_contains((string) $ihb['son_hata'], 'elle'), 'elle yapılmış satışın iadesi elle yapılmalı uyarısı');

// Geçici: 503 → geri çekilme
$v3 = $uts->gonder('08690000000033', 'GC1', 'Test Çerçeve');
uts_gelenleri_getir();
$g3 = row("SELECT * FROM uts_urunler WHERE sno = 'GC1'");
uts_gelenleri_kabul_et([(int) $g3['id']], [], false, false);
$uts->zorla[] = [503, 'Service Unavailable'];
uts_kuyrugu_isle();
$ab = row("SELECT * FROM uts_bildirimler WHERE tur = 'alma' AND urun_id = ?", [(int) $g3['id']]);
ok($ab['durum'] === 'hata' && (int) $ab['deneme'] === 1 && $ab['planlanan'] > uts_simdi(), 'geçici hata: deneme 1, ileri tarihe ertelendi');
uts_kuyrugu_isle();
esit('hata', scalar('SELECT durum FROM uts_bildirimler WHERE id = ?', [(int) $ab['id']]), 'erteleme süresi dolmadan tekrar denenmez');
ok(uts_bildirim_tekrar((int) $ab['id']), 'elle "tekrar dene"');
uts_kuyrugu_isle();
esit('gonderildi', scalar('SELECT durum FROM uts_bildirimler WHERE id = ?', [(int) $ab['id']]), 'tekrar denemede gönderildi');

// Yetki: 401 → tur durur, deneme sayılmaz
$v4 = $uts->gonder('08690000000040', 'YT1', 'Test Çerçeve 2');
$v5 = $uts->gonder('08690000000057', 'YT2', 'Test Çerçeve 3');
uts_gelenleri_getir();
$idler = array_map('intval', array_column(rows("SELECT id FROM uts_urunler WHERE sno IN ('YT1','YT2')"), 'id'));
uts_gelenleri_kabul_et($idler, [], false, false);
$uts->token = 'yeni-token';
$once = count($uts->istekler);
$o = uts_kuyrugu_isle();
ok($o['durdu'] !== '' && count($uts->istekler) === $once + 1, 'yetki hatasında ilk istekten sonra durur');
ok(setting('uts_yetki_hatasi') !== '', 'yetki hatası ekranda gösterilmek üzere saklandı');
$yb = row("SELECT * FROM uts_bildirimler WHERE tur = 'alma' AND urun_id = ?", [$idler[0]]);
esit(0, (int) $yb['deneme'], 'yetki hatası deneme sayısını artırmaz');
gizli_ayar_yaz('uts_token', 'yeni-token');
q("UPDATE uts_bildirimler SET planlanan = ? WHERE durum = 'hata' AND deneme < 6", [uts_simdi(-1)]);
$o = uts_kuyrugu_isle();
esit(2, $o['gonderilen'], 'token düzelince ikisi de gönderildi');
esit('', setting('uts_yetki_hatasi'), 'yetki uyarısı temizlendi');

// Token yoksa hiç istek gitmez
gizli_ayar_yaz('uts_token', '');
$once = count($uts->istekler);
ok(!uts_hazir_mi(), 'token yok: hazır değil');
uts_kuyrugu_isle();
esit($once, count($uts->istekler), 'token yokken istek yok');
gizli_ayar_yaz('uts_token', 'yeni-token');

// Yarıda kalan "gönderiliyor" 15 dk sonra hata olur
$yk = uts_bildirim_ekle('imha', ['UNO' => '1'], null, null, 1, 'yarida:1');
q("UPDATE uts_bildirimler SET durum = 'gonderiliyor', planlanan = ? WHERE id = ?", [uts_simdi(-3600), $yk]);
$once = count($uts->istekler);
uts_kuyrugu_isle();
$ykr = row('SELECT * FROM uts_bildirimler WHERE id = ?', [$yk]);
ok($ykr['durum'] === 'hata' && (int) $ykr['deneme'] === 99 && str_contains((string) $ykr['son_hata'], 'yarıda'), 'yarıda kalan kayıt personele bırakılır (deneme 99)');
esit($once, count($uts->istekler), 'yarıda kalan kayıt otomatik yeniden gönderilmez (çift bildirim riski)');
q('DELETE FROM uts_bildirimler WHERE id = ?', [$yk]);

// Zaman aşımı (belirsiz): lot adetli bildirim otomatik tekrar edilmez; seri takipli tekrar edilir
$bl = uts_bildirim_ekle('imha', ['UNO' => '1', 'LNO' => 'L', 'ADT' => 3, 'GRK' => 'DIGER', 'BNO' => '1'], null, null, 3, 'belirsiz:lot');
$uts->zorla[] = [0, ''];
uts_kuyrugu_isle();
$blr = row('SELECT * FROM uts_bildirimler WHERE id = ?', [$bl]);
ok((int) $blr['deneme'] === 99 && str_contains((string) $blr['son_hata'], 'almış olabilir'), 'lot + zaman aşımı: elle kontrol istenir');
$bs = uts_bildirim_ekle('imha', ['UNO' => '1', 'SNO' => 'S', 'GRK' => 'DIGER', 'BNO' => '1'], null, null, 1, 'belirsiz:seri');
$uts->zorla[] = [0, ''];
uts_kuyrugu_isle();
esit(1, (int) scalar('SELECT deneme FROM uts_bildirimler WHERE id = ?', [$bs]), 'seri + zaman aşımı: otomatik tekrar (deneme 1)');
$r = uts_yanit_coz(0, '', 'Failed to connect to utsuygulama.saglik.gov.tr port 443: Connection refused');
ok($r['belirsiz'] === false, 'bağlantı kurulamadı = belirsiz değil');
esit(true, uts_yanit_coz(0, '', 'Operation timed out after 30001 milliseconds with 0 bytes received')['belirsiz'], 'işlem zaman aşımı = belirsiz');
q("DELETE FROM uts_bildirimler WHERE tekil LIKE 'belirsiz:%'");

echo "14) İade bağımlılığı: satış gönderilmeden iade gitmez\n";
$u9 = uts_stoga_okut($kk('08690000000019', 'VG300'), 'cerceve');
$uts->stok['08690000000019|VG300'] = true;
$s9 = insert('orders', ['first_name' => 'B', 'last_name' => 'C']);
uts_siparise_okut($s9, $kk('08690000000019', 'VG300'));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $s9]);
uts_siparis_asama_degisti($s9, 'hazirlandi', 'teslim_edildi');
$tv9 = row("SELECT * FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciye_verme'", [$s9]);
q("UPDATE uts_bildirimler SET durum = 'gonderiliyor', planlanan = ? WHERE id = ?", [uts_simdi(), (int) $tv9['id']]);   // başka süreç gönderiyor
uts_siparis_asama_degisti($s9, 'teslim_edildi', 'hazirlandi');
$ti9 = row("SELECT * FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciden_iade'", [$s9]);
ok($ti9 !== null, 'gönderilmekte olan satış için iade sıraya girdi');
uts_kuyrugu_isle();
$ti9 = row('SELECT * FROM uts_bildirimler WHERE id = ?', [(int) $ti9['id']]);
ok($ti9['durum'] === 'bekliyor' && $ti9['planlanan'] > uts_simdi(), 'satış tamamlanmadan iade ertelenir');
q("UPDATE uts_bildirimler SET durum = 'bekliyor' WHERE id = ?", [(int) $tv9['id']]);
uts_kuyrugu_isle();
q("UPDATE uts_bildirimler SET planlanan = ? WHERE id = ?", [uts_simdi(-1), (int) $ti9['id']]);
uts_kuyrugu_isle();
esit('gonderildi', scalar('SELECT durum FROM uts_bildirimler WHERE id = ?', [(int) $ti9['id']]), 'satıştan sonra iade gönderildi');
esit(true, $uts->stok['08690000000019|VG300'] ?? null, 'sahte ÜTS\'de ürün mağaza stoğuna döndü');

echo "15) Deneme kayıtlarını gerçek ÜTS'ye gönder\n";
$denemeSayisi = (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE ortam = 'deneme' AND durum = 'gonderildi' AND tur <> 'alma'");
ok($denemeSayisi > 0, 'deneme kayıtları var');
esit($denemeSayisi, uts_deneme_kayitlarini_kuyruga_al(), 'hepsi sıraya alındı');
esit(0, (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE ortam = 'deneme' AND durum = 'gonderildi' AND tur <> 'alma'"), 'deneme "gönderildi" kalmadı');

echo "16) Deneme modu ağ kullanmaz\n";
setting_set('uts_ortam', 'deneme');
$once = count($uts->istekler);
uts_bildirim_ekle('imha', ['UNO' => '1', 'SNO' => 'x', 'GRK' => 'DIGER', 'BNO' => '1'], null, null, 1, 'deneme:ag');
uts_kuyrugu_isle();
esit($once, count($uts->istekler), 'deneme modunda sahte ÜTS\'ye bile istek gitmedi');
$r = uts_gelenleri_getir();
ok($r['ok'] && $r['sayi'] === 0, 'deneme modunda gelen listesi boş');
esit(3, uts_deneme_ornek_gelen(), 'deneme örnekleri');

echo "17) Barkod okuyucu ÜTS ürününü açar\n";
$bk = barkod_coz($kk('08690000000019', 'VG100'));
esit('uts_urun', $bk['tur'], 'okutulan ÜTS ürünü tanındı');
ok(str_starts_with($bk['hedef'], 'uts.php?urun='), 'ürün kartına yönlendirir');
esit('cerceve', barkod_coz('08690000000019')['tur'] === 'cerceve' ? 'cerceve' : barkod_coz('8690000000019')['tur'], 'düz GTIN hâlâ çerçeve kartını açar');

echo "19) İnceleme bulguları (regresyon)\n";
setting_set('uts_ortam', 'deneme');
// (1)+(3) Lot: ayrılmış parçaya okutmayla adet eklenmez; serbest parça satılabilir
$lot2 = '0108680000000062' . '10LOTZ' . $GS . '17291231';
$ana = uts_stoga_okut($lot2, 'lens', 5);
$oa = insert('orders', ['first_name' => 'A', 'last_name' => 'A']);
$ob = insert('orders', ['first_name' => 'B', 'last_name' => 'B']);
$pa = uts_siparise_okut($oa, $lot2, 'lens', 2);
$pb = uts_siparise_okut($ob, $lot2, 'lens', 3);   // ana satırın tamamı B'ye geçer
esit($ob, (int) uts_urun((int) $pb['urun']['id'])['order_id'], 'B lotun kalanını aldı');
uts_siparis_asama_degisti($oa, 'atolyede', 'iptal');
$yeniOkut = uts_stoga_okut($lot2, 'lens', 4);
esit(3, (int) uts_urun((int) $pb['urun']['id'])['adet'], 'B\'ye ayrılmış lot satırına adet eklenmedi');
esit(6, (int) $yeniOkut['adet'], 'yeni okutma serbest parçaya eklendi (2 + 4)');
$oc = insert('orders', ['first_name' => 'C', 'last_name' => 'C']);
$pc = uts_siparise_okut($oc, $lot2, 'lens', 1);
esit(1, (int) $pc['urun']['adet'], 'serbest lot parçası başka siparişe okutulabildi');
uts_siparis_urunlerini_coz($oc);
esit(6, (int) uts_urun((int) $yeniOkut['id'])['adet'], 'çözülen parça serbest satıra birleşti');

// (2) Aynı lot ikinci kez geliyor (iki sevkiyat) ve daha önce satılmış seri geri geliyor
setting_set('uts_ortam', 'test');
$uts->bekleyen = [];
$va = uts_uuid4(); $vb = uts_uuid4();
$uts->bekleyen[$va] = ['UNO' => '08690000000064', 'LNO' => 'LX1', 'ADT' => 10, 'VBI' => $va, 'AKU' => 'Lens A.Ş.', 'MME' => 'Kontakt lens aylık'];
$uts->bekleyen[$vb] = ['UNO' => '08690000000064', 'LNO' => 'LX1', 'ADT' => 5, 'VBI' => $vb, 'AKU' => 'Lens A.Ş.', 'MME' => 'Kontakt lens aylık'];
$vs = $uts->gonder('08690000000019', 'VG100', 'Vogue geri geldi');   // VG100 daha önce satılmıştı
uts_gelenleri_getir();
esit(2, (int) scalar("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'gelen' AND uno = '08690000000064'"), 'aynı lotun iki sevkiyatı ayrı satır');
esit(15, (int) scalar("SELECT SUM(adet) FROM uts_urunler WHERE durum = 'gelen' AND uno = '08690000000064'"), 'adetler korunur (10 + 5)');
$geriGelen = row("SELECT * FROM uts_urunler WHERE durum = 'gelen' AND sno = 'VG100'");
ok($geriGelen !== null && $geriGelen['vbi'] === $vs, 'satılmış seri yeniden gelince yeni kayıt açıldı');
esit(1, (int) scalar("SELECT COUNT(*) FROM uts_urunler WHERE sno = 'VG100' AND anahtar LIKE '%|arsiv%'"), 'eski kayıt arşivde, geçmişi korunuyor');
$lotIdler = array_map('intval', array_column(rows("SELECT id FROM uts_urunler WHERE durum = 'gelen' AND uno = '08690000000064'"), 'id'));
uts_gelenleri_kabul_et($lotIdler, [], false, false);
uts_kuyrugu_isle();
esit(2, (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE tur = 'alma' AND urun_id IN (" . implode(',', $lotIdler) . ") AND durum = 'gonderildi'"), 'iki sevkiyat için iki alma bildirimi');
ok(!isset($uts->bekleyen[$va]) && !isset($uts->bekleyen[$vb]), 'sahte ÜTS iki sevkiyatı da kabul etti');

// (5) Eşzamanlılık: ertelenmiş hatalı kaydı ikinci süreç kilitleyemez
$ez = uts_bildirim_ekle('imha', ['UNO' => '9', 'SNO' => 'Z', 'GRK' => 'DIGER', 'BNO' => '1'], null, null, 1, 'ez:1');
q("UPDATE uts_bildirimler SET durum = 'hata', deneme = 2, planlanan = ? WHERE id = ?", [uts_simdi(600), $ez]);
$once = count($uts->istekler);
uts_kuyrugu_isle();
esit($once, count($uts->istekler), 'ertelenmiş kayıt gönderilmedi');
q("DELETE FROM uts_bildirimler WHERE id = ?", [$ez]);

// (6) Satış kalıcı hatalıysa iade sessizce beklemez
$su = uts_stoga_okut($kk('08690000000071', 'IA1'), 'cerceve');
$so = insert('orders', ['first_name' => 'I', 'last_name' => 'A']);
uts_siparise_okut($so, $kk('08690000000071', 'IA1'));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $so]);
uts_siparis_asama_degisti($so, 'hazirlandi', 'teslim_edildi');
$stv = row("SELECT * FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciye_verme'", [$so]);
q("UPDATE uts_bildirimler SET durum = 'gonderiliyor', planlanan = ? WHERE id = ?", [uts_simdi(), (int) $stv['id']]);
uts_siparis_asama_degisti($so, 'teslim_edildi', 'hazirlandi');
q("UPDATE uts_bildirimler SET durum = 'hata', deneme = 99 WHERE id = ?", [(int) $stv['id']]);
uts_kuyrugu_isle();
$sti = row("SELECT * FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciden_iade'", [$so]);
ok($sti['durum'] === 'hata' && (int) $sti['deneme'] === 99 && str_contains((string) $sti['son_hata'], '#' . $stv['id']), 'bağlı satış hatalıysa iade hata listesine düşer');

// (7) Teslimden sonra SGK girildi
setting_set('uts_ortam', 'deneme');
$su2 = uts_stoga_okut($kk('08690000000071', 'SG1'), 'cerceve');
$so2 = insert('orders', ['first_name' => 'S', 'last_name' => 'G']);
uts_siparise_okut($so2, $kk('08690000000071', 'SG1'));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $so2]);
uts_siparis_asama_degisti($so2, 'hazirlandi', 'teslim_edildi');
q("UPDATE orders SET sgk_erecete = 'ERX1' WHERE id = ?", [$so2]);
uts_siparis_sgk_guncellendi($so2);
esit('iptal', scalar("SELECT durum FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciye_verme'", [$so2]), 'gönderilmemiş ücretli satış bildirimi iptal');
esit('sgk', uts_urun((int) $su2['id'])['durum'], 'ürün SGK çıkışına döndü');
$su3 = uts_stoga_okut($kk('08690000000071', 'SG2'), 'cerceve');
$so3 = insert('orders', ['first_name' => 'S', 'last_name' => 'H']);
uts_siparise_okut($so3, $kk('08690000000071', 'SG2'));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $so3]);
uts_siparis_asama_degisti($so3, 'hazirlandi', 'teslim_edildi');
uts_kuyrugu_isle();
q("UPDATE orders SET sgk_amount = 300 WHERE id = ?", [$so3]);
$m = uts_siparis_sgk_guncellendi($so3);
ok((int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciden_iade'", [$so3]) === 1 && str_contains(implode(' ', $m), 'iade'), 'gönderilmiş ücretli satış için iade sıraya girdi');
esit([], uts_siparis_sgk_guncellendi($so3), 'ikinci çağrı bir şey yapmaz');

// (9) Çift teslim isteği tek bildirim üretir (iki istek aynı sipariş satırını okumuş)
$su4 = uts_stoga_okut($kk('08690000000071', 'CT1'), 'cerceve');
$so4 = insert('orders', ['first_name' => 'C', 'last_name' => 'T']);
uts_siparise_okut($so4, $kk('08690000000071', 'CT1'));
q("UPDATE orders SET order_stage = 'teslim_edildi', delivered_at = ? WHERE id = ?", [uts_simdi(), $so4]);
$eskiO = row('SELECT * FROM orders WHERE id = ?', [$so4]);
uts_siparis_teslim($eskiO);
uts_siparis_teslim($eskiO);
esit(1, (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE order_id = ? AND tur = 'tuketiciye_verme'", [$so4]), 'çift teslim tek bildirim');

echo "18) Özet ve rozet\n";
$oz = uts_ozet();
ok($oz['stokta'] > 0 && $oz['gelen'] === (int) scalar("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'gelen'") && $oz['gelen'] >= 1, 'özet sayıları');
ok(uts_menu_rozeti() >= $oz['gelen'] + $oz['hata'], 'menü rozeti');

bitir();
