<?php
/* 4.33.0 Tezgâh (app/tezgah.php): reçete no, Medula metninden ön dolum, bekleyen aktarımlar, teslim sözü. */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';
require_once dirname(__DIR__, 2) . '/app/tezgah.php';

$ornek = static fn(string $ad): string => (string) file_get_contents(dirname(__DIR__, 2) . '/desktop/tests/fixtures/' . $ad);

echo "1) e-Reçete no\n";
esit('1A2B3C4', tezgah_recete_no(' 1a2b-3c4 '), 'boşluk/tire atılır, büyük harf');
esit('9Z8Y7X', tezgah_recete_no('9z8y7x'), 'küçük harf');
esit('3IK4', tezgah_recete_no('3ık4'), 'Türkçe ı → I');
esit('', tezgah_recete_no('ABCDEFG'), 'rakamsız reddedilir');
esit('', tezgah_recete_no('12'), 'kısa reddedilir');
esit('', tezgah_recete_no('1A2B3C4D5E6F7'), 'uzun reddedilir');
esit('', tezgah_recete_no('1A2B"><x'), 'özel karakter reddedilir');

echo "2) Medula metninden ön dolum (uzak + yakın)\n";
$d = tezgah_on_dolum(sgk_parse($ornek('recete-yakin.metin.txt')));
esit('ayri_uzak_yakin', $d['lens_design'], 'hasta tercihi: ayrı uzak yakın');
esit('2026-09-01', $d['prescription_date'], 'reçete tarihi');
esit('9Z8Y7X', $d['erecete'], 'e-reçete no');
esit(['İsmail Şükrü', 'Çağlayangöz'], [$d['ad'], $d['soyad']], 'ad soyad Türkçe büyük/küçük');
ok($d['yakin'], 'yakın değerler var');
ok($d['right_sph'] !== '' && $d['near_right_sph'] !== '', 'uzak ve yakın SPH dolu');
ok(!array_key_exists('tc', $d) && !in_array('12345678901', $d, true), 'T.C. kimlik no ön dolumda yok (KVKK)');
foreach (['right_sph', 'right_cyl', 'right_axis', 'right_add', 'left_sph', 'left_cyl', 'left_axis', 'left_add', 'pd', 'doctor', 'near_left_sph'] as $k) {
    ok(array_key_exists($k, $d), "reçete ekranı alanı: $k");
}

echo "3) Yalnız uzak reçete\n";
$u = tezgah_on_dolum(sgk_parse($ornek('recete-uzak.metin.txt')));
esit('tek_odak_uzak', $u['lens_design'], 'tek odak uzak');
ok(!$u['yakin'] && $u['near_right_sph'] === '', 'yakın yok');

echo "3b) 4.39.1 Tek yakın (okuma gözlüğü): CYL/AKS'li yakın, ADD yok\n";
$tyc = sgk_parse((string) file_get_contents(__DIR__ . '/ornek/recete-tek-yakin.metin.txt'));
esit('tek_odak_yakin', sgk_lens_design($tyc), 'yalnız YAKIN bölümü → tek odak yakın (ayrı uzak+yakın değil)');
$ty = tezgah_on_dolum($tyc);
esit(['+2.75', '-0.75', '170', ''], [$ty['right_sph'], $ty['right_cyl'], $ty['right_axis'], $ty['right_add']], 'sağ yakın SPH/CYL/AKS reçete satırında, ADD boş');
esit(['+2.50', '-0.50', '15'], [$ty['left_sph'], $ty['left_cyl'], $ty['left_axis']], 'sol yakın CYL/AKS geçti');
esit('ayri_uzak_yakin', sgk_lens_design(sgk_parse($ornek('recete-yakin.metin.txt'))), 'uzak + yakın birlikte → ayrı uzak yakın (değişmedi)');
$dom = (string) file_get_contents(dirname(__DIR__, 2) . '/app/domain.php');
ok(str_contains($dom, "(\$design === 'ayri_uzak_yakin' && !\$yakinYazili)") && !str_contains($dom, "'ayri_uzak_yakin', 'tek_odak_yakin'], true)\n        && \$data['right_add']"), 'tek odak yakında ADD zorunlu değil');

echo "4) Boş (elle giriş) formu\n";
$b = tezgah_on_dolum(['sag' => [], 'sol' => [], 'yakin_sag' => [], 'yakin_sol' => []]);
esit(date('Y-m-d'), $b['prescription_date'], 'tarih bugün');
esit(['', '', 'tek_odak_uzak'], [$b['right_sph'], $b['erecete'], $b['lens_design']], 'alanlar boş');

echo "5) Bekleyen aktarımlar\n";
$simdi = date('Y-m-d H:i:s');
$g1 = insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'masaustu', 'raw_text' => 'x', 'parsed' => json_encode(['hasta' => 'AYŞE YILMAZ', 'erecete' => '1a2b3c4', 'tc' => '12345678901']), 'created_at' => $simdi]);
insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'masaustu', 'raw_text' => 'x', 'parsed' => '{}', 'created_at' => $simdi, 'used_order_id' => 9]);
insert('sgk_incoming', ['user_id' => 2, 'kaynak' => 'masaustu', 'raw_text' => 'x', 'parsed' => '{}', 'created_at' => $simdi]);
insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'masaustu', 'raw_text' => 'x', 'parsed' => '{}', 'created_at' => date('Y-m-d H:i:s', time() - 3 * 86400)]);
$bek = tezgah_bekleyenler(1);
esit(1, count($bek), 'yalnız kendi, kullanılmamış, son 2 gün');
esit([$g1, 'AYŞE YILMAZ', '1A2B3C4'], [$bek[0]['id'], $bek[0]['hasta'], $bek[0]['erecete']], 'hasta ve no');
ok(!str_contains(json_encode($bek), '12345678901'), 'T.C. no listede yok');

echo "6) Teslim sözü (iş günü, pazar atlanır)\n";
$cuma = strtotime('2026-10-16 10:00');   // cuma
esit('2026-10-20', tezgah_teslim_tarihi($cuma), 'cuma + 3 iş günü = salı (pazar atlanır)');
setting_set('tezgah_teslim_gun', '0');
esit('2026-10-16', tezgah_teslim_tarihi($cuma), '0 gün = bugün');
setting_set('tezgah_teslim_gun', '1');
esit('2026-10-17', tezgah_teslim_tarihi($cuma), 'cumartesi iş günü sayılır');

echo "7) 4.34.0 tek akış: bekleyen reçete → sipariş\n";
// app/domain.php testte yüklenmez: kullanılan yardımcıların sade eşleri (cam satırı stok durumuyla yazılır)
if (!function_exists('lens_designs')) {
    function lens_designs(): array { return ['tek_odak_uzak' => 'Tek odak uzak', 'tek_odak_yakin' => 'Tek odak yakın', 'ayri_uzak_yakin' => 'Ayrı uzak + yakın', 'progressive' => 'Progressive']; }
    function rx_line(array $r, string $s): string { return trim(($r[$s . '_sph'] ?? '') . ' ' . ($r[$s . '_cyl'] ?? '')); }
    function near_sphere(string $sph, string $add): string { return $add === '' ? $sph : sprintf('%+.2f', (float) $sph + (float) $add); }
    function stage_label(?string $k): string { return (string) $k; }
    function sync_lens_items(int $rxId, array $rx, ?array $near, array $stock): void {
        if ($rx['lens_design'] !== 'tek_odak_yakin') {
            insert('prescription_lens_items', ['prescription_id' => $rxId, 'lens_no' => 1, 'stock_status' => $stock['uzak'] ?? 'stokta_var', 'lens_type' => $rx['lens_type']]);
        }
        if ($near !== null) {
            insert('prescription_lens_items', ['prescription_id' => $rxId, 'lens_no' => 3, 'stock_status' => $stock['yakin'] ?? 'stokta_var', 'lens_type' => $near['lens_type'], 'item_group' => 'yakin']);
        }
    }
    function sgk_katki_uygula(int $o, int $c, array $rx): array { $GLOBALS['__sgk'][] = $o; return ['tutar' => 120.0, 'uyari' => null, 'korundu' => false]; }
}
foreach (['prescription_date', 'lens_design', 'lens_eyes', 'lens_type', 'right_sph', 'right_cyl', 'right_axis', 'right_add', 'right_pd', 'right_height',
          'left_sph', 'left_cyl', 'left_axis', 'left_add', 'left_pd', 'left_height', 'pd', 'prescription_note', 'doctor', 'customer_id', 'sgk_rapor_no', 'sgk_rapor_tarihi', 'created_by', 'created_at'] as $s) {
    db()->exec("ALTER TABLE prescription_records ADD COLUMN $s TEXT NULL");
}
db()->exec('CREATE TABLE near_prescription_details (id INTEGER PRIMARY KEY AUTOINCREMENT, prescription_id INTEGER, usage_type TEXT, lens_type TEXT, right_sph TEXT, right_cyl TEXT, right_axis TEXT, left_sph TEXT, left_cyl TEXT, left_axis TEXT)');
db()->exec('ALTER TABLE orders ADD COLUMN status TEXT NULL');
db()->exec('ALTER TABLE orders ADD COLUMN updated_at TEXT NULL');

$rxVeri = ['prescription_date' => '2026-10-16', 'lens_design' => 'ayri_uzak_yakin', 'lens_eyes' => 'both', 'lens_type' => 'X', 'advisor_summary' => 'x', 'advisor_product_id' => null,
    'right_sph' => '-2.00', 'right_cyl' => '-0.50', 'right_axis' => '90', 'right_add' => '+2.00', 'right_pd' => '', 'right_height' => '',
    'left_sph' => '-1.75', 'left_cyl' => '', 'left_axis' => '', 'left_add' => '+2.00', 'left_pd' => '', 'left_height' => '', 'pd' => '62', 'prescription_note' => '', 'doctor' => 'Dr. Test'];
$yakinVeri = ['usage_type' => 'ayri_uzak_yakin', 'lens_type' => 'X', 'right_sph' => '+0.00', 'right_cyl' => '-0.50', 'right_axis' => '90', 'left_sph' => '+0.25', 'left_cyl' => '', 'left_axis' => ''];
$mus = insert('customers', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321112233']);
$rc = recete_bekleyen_kaydet($mus, $rxVeri, $yakinVeri, '1A2B3C4', $g1, 1);
$r = recete_bekleyen($rc);
esit([$mus, '1A2B3C4', $g1, 0], [$r['customer_id'], $r['erecete'], $r['gelen_id'], $r['used_order_id']], 'müşteriye bağlı, e-reçete ve aktarım no');
ok(!array_key_exists('lens_type', $r['rx']) && !array_key_exists('advisor_summary', $r['rx']), 'cam tipi reçetede saklanmaz (siparişte seçilir)');
esit('-2.00', $r['rx']['right_sph'], 'değerler aynen');
ok(!str_contains((string) scalar('SELECT veri FROM bekleyen_receteler WHERE id = ?', [$rc]), '12345678901'), 'T.C. no saklanmaz');
esit(0, count(tezgah_bekleyenler(1)), 'müşteriye bağlanan aktarım "kaydedilmeyenler" listesinden düşer');
esit($rc, recete_son_bekleyen($mus)['id'] ?? 0, 'müşterinin son bekleyen reçetesi');
ok(str_contains(recete_ozet($r), 'PD 62'), 'özet satırı');

[$uzakRx, $uzakYakin] = recete_kullanim_icin($r, 'tek_odak_uzak');
ok($uzakRx['lens_design'] === 'tek_odak_uzak' && $uzakYakin === null, 'uzak gözlüğü: yakın değer yok');
[$yRx, $yY] = recete_kullanim_icin($r, 'tek_odak_yakin');
esit(['tek_odak_yakin', '+0.25'], [$yRx['lens_design'], $yY['left_sph']], 'yakın gözlüğü: reçetedeki yakın SPH');
[, $hesap] = recete_kullanim_icin(['rx' => ['right_sph' => '-1.00', 'right_add' => '+2.50', 'left_sph' => '', 'left_add' => ''] + $rxVeri, 'near' => null], 'tek_odak_yakin');
esit('+1.50', $hesap['right_sph'], 'yakın değeri yoksa SPH + ADD');

$s1 = insert('orders', ['customer_id' => $mus, 'first_name' => 'Ayşe', 'order_stage' => 'siparis_verildi']);
$u1 = recete_siparise_uygula($rc, $s1, 'tek_odak_uzak', 'Organik 1.60', '', ['uzak' => 'stokta_var'], 1);
esit([0, true], [$u1['eksik'], $u1['atolye']], 'camlar stokta → atölyeye');
esit('atolyede', scalar('SELECT order_stage FROM orders WHERE id = ?', [$s1]), 'sipariş atölyede');
esit(['Organik 1.60', '1A2B3C4'], array_values(row('SELECT lens_type, sgk_erecete FROM orders WHERE id = ?', [$s1])), 'cam tipi ve e-reçete siparişte');
esit(['1A2B3C4', 'tek_odak_uzak', (string) $mus], array_values(row('SELECT sgk_rapor_no, lens_design, customer_id FROM prescription_records WHERE id = ?', [$u1['rx']])), 'reçete siparişe yazıldı');
esit([$s1, $s1], [(int) scalar('SELECT used_order_id FROM bekleyen_receteler WHERE id = ?', [$rc]), (int) scalar('SELECT used_order_id FROM sgk_incoming WHERE id = ?', [$g1])], 'reçete ve aktarım kullanıldı');
ok(recete_son_bekleyen($mus) === null, 'kullanılan reçete bekleyenlerde yok');
esit([$s1], $GLOBALS['__sgk'], 'e-reçeteli siparişte SGK katkısı işlenir');

$s2 = insert('orders', ['customer_id' => $mus, 'first_name' => 'Ayşe', 'order_stage' => 'siparis_verildi']);
$u2 = recete_siparise_uygula($rc, $s2, 'tek_odak_yakin', 'Organik 1.50', '', ['yakin' => 'stokta_yok'], 1);
esit([1, false], [$u2['eksik'], $u2['atolye']], 'eksik cam → atölyeye geçmez, depo listesinde');
esit('siparis_verildi', scalar('SELECT order_stage FROM orders WHERE id = ?', [$s2]), 'eksik camlı sipariş beklemede');
esit(1, (int) scalar('SELECT COUNT(*) FROM near_prescription_details WHERE prescription_id = ?', [$u2['rx']]), 'yakın gözlüğün yakın değerleri');
esit($s1, (int) scalar('SELECT used_order_id FROM bekleyen_receteler WHERE id = ?', [$rc]), 'ikinci gözlük ilk kullanımı ezmez');

$baska = insert('customers', ['first_name' => 'Ali', 'last_name' => 'Kaya', 'phone' => '05320000000']);
$s3 = insert('orders', ['customer_id' => $baska, 'order_stage' => 'siparis_verildi']);
$hata = '';
try { recete_siparise_uygula($rc, $s3, '', 'X', '', [], 1); } catch (DomainException $e) { $hata = $e->getMessage(); }
ok(str_contains($hata, 'müşterisine ait değil'), 'başka müşterinin siparişine yazılmaz');
ok(substr_count(akis_cubugu([2, 3]), 'aria-current="step"') === 2 && str_contains(akis_cubugu(3, 'teklif'), '<small>teklif</small>') && count(akis_adimlari()) === 4, 'adım çubuğu: 4 adım, aynı ekranda iki adım, altyazı');

echo "8) Sayfa ve fiş kaynakları\n";
$sayfa = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/tezgah.php');
ok(!str_contains($sayfa, "ozellik_gereksin('kagitsiz')"), '4.34.0: akışın girişi her mağazada açık');
ok(str_contains($sayfa, 'recete_bekleyen_kaydet(') && str_contains($sayfa, 'recete_siparise_uygula($receteId, $orderId'), '4.38.0: reçete müşteriye bağlanır; sözlü anlaşmada sipariş aynı ekrandan');
ok(str_contains($sayfa, "post('sonra') === 'teklif' ? 'teklif' : 'siparis'") && str_contains($sayfa, 'value="teklif" formnovalidate') && str_contains($sayfa, 'Siparişi kaydet, fişi yazdır'), 'teklif zorunlu değil: varsayılan sipariş, teklif isteğe bağlı');
ok(str_contains($sayfa, '<details class="ek-alan"') && str_contains($sayfa, 'data-yakin-alan') && str_contains($sayfa, 'data-kapora-yontem'), 'isteğe bağlı alanlar katlanır / gerektiğinde görünür');
$lay = (string) file_get_contents(dirname(__DIR__, 2) . '/app/layout.php');
ok(str_contains($lay, 'function menu_gruplari(') && str_contains($lay, "'Atölye ve stok'"), 'menü iş gruplarına ayrılır');
$sip = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/order.php');
ok(str_contains($sip, 'class="hizli-asama"') && str_contains($sip, '<details class="card stage-changer">') && str_contains($sip, '<details class="card siparis-bilgi">') && str_contains($sip, '<details class="card gecmis">'), '4.39.0 sipariş sayfası: tek tık sonraki aşama, katlanır kartlar');
$tk = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/teklif-yeni.php');
ok(str_contains($tk, 'data-secenek-gizli') && str_contains($tk, 'data-secenek-ekle') && str_contains($tk, '<summary class="card-head"><h2>İskonto ve not</h2>'), '4.39.0 teklif formu: ek seçenekler istenince, iskonto katlanır');
$ix = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/index.php');
ok(!str_contains($ix, 'data-fortune-box') && !str_contains($ix, '<section class="stats">') && str_contains($ix, '$chipSayi') && str_contains($ix, 'bakiye-gizli'), '4.40.0 ana ekran: sayı kutuları çiplerde, fal kutusu yok');
$ws = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/workshop.php');
ok(!str_contains($ws, 'Cevap bekleyen teklif') && !str_contains($ws, 'href="teklif-yeni.php"'), '4.40.0 atölye panosu: atölyeye ait olmayan sayılar ve düğmeler yok');
$cu = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/customer.php');
ok(str_contains($cu, '<summary class="card-head"><h2>Bilgiler</h2>') && str_contains($cu, 'Hatırlatma ekle</summary>'), '4.40.0 müşteri kartı: formlar katlanır');
$rxs = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/rx.php');
ok(!str_contains($rxs, '<th>PD</th>') && str_contains($rxs, 'Göz başına PD ve montaj yüksekliği') && str_contains($rxs, '<details class="advisor"'), '4.41.0 reçete sayfası: PD/yükseklik katlanır, öneri asistanı katlanır');
$qs = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/quote.php');
ok(str_contains($qs, 'class="gozluk-ozet"') && str_contains($qs, 'Hesap ayrıntısı'), '4.41.0 teklif görüntüleme: gözlük özeti tek satır, döküm katlanır');
$st = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/settings.php');
ok(str_contains($st, 'class="ayar-sekmeleri"') && str_contains($st, '<h3 class="ayar-grup span-all">Hesaplama</h3>'), '4.41.0 ayarlar: gruplu sekmeler, bölüm başlıkları');
ok(str_contains($lay, 'href="tezgah.php"><?= icon(\'plus\') ?> Yeni müşteri · reçete') && !str_contains($lay, "'SGK reçete aktar'"), 'menü: tek giriş, SGK reçete aktar kalktı');
ok(str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/sgk-aktar.php'), "redirect('tezgah.php?gelen='"), 'Pro aktarımı akışta açılır');
ok(str_contains($sayfa, 'csrf_check()'), 'CSRF denetimi');
ok(str_contains($sayfa, 'rx_from_post()'), 'reçete doğrulaması reçete ekranıyla aynı');
ok(!preg_match('/<script>|onclick=/i', $sayfa), 'satır içi betik yok (CSP)');
ok(!str_contains($sayfa, "['tc']"), 'sayfa T.C. no kullanmaz');
$fis = (string) file_get_contents(dirname(__DIR__, 2) . '/app/partials/siparis-fisi.php');
ok(str_contains($fis, 'Müşteri nüshası') && str_contains($fis, "query('nusha')"), 'fişte müşteri nüshası');
ok(str_contains($fis, 'Montaj ölçüleri') && str_contains($fis, "'Kontrol · PD / aks'") && str_contains($fis, '<th>Paraf</th>'), 'iş emri: montaj ölçüleri, parafli atölye adımları');
ok(str_contains($fis, 'class="is-emri') && str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/assets/siparis-fisi.css'), 'rotate(-90deg)'), '4.36.0 iş emri 148,5 × 210 dikey kart, döndürülerek basılır');
ok(str_contains($fis, "preg_match('/^(Teklif #\\d+|Cam .*₺)/u'"), 'iş emri notunda teklifin fiyat satırları basılmaz');
ok(str_contains($fis, 'class="tablo"') && str_contains($fis, "['LÜĞ', '0,3']") && str_contains($fis, 'Sırada ne var?') && !str_contains($fis, 'class="yol"'), '4.36.0 müşteri nüshası: göz tablosu, sırada ne var (ilerleme çubuğu yok)');
ok(str_contains($fis, "str_replace(['-', '.'], ['−', ','], \$s)"), 'dioptri eksi işareti ve virgülle (kimlik kuralı)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/assets/print.js'), 'data-otomatik-yazdir'), 'tezgâhtan gelince otomatik yazdır');

bitir();
