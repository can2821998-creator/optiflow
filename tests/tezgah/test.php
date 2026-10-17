<?php
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
/* 4.33.0 Tezgâh (app/tezgah.php): reçete no, Medula metninden ön dolum, bekleyen aktarımlar, teslim sözü. */
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
declare(strict_types=1);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
require dirname(__DIR__) . '/uts/ortam.php';
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
require_once dirname(__DIR__, 2) . '/app/tezgah.php';
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$ornek = static fn(string $ad): string => (string) file_get_contents(dirname(__DIR__, 2) . '/desktop/tests/fixtures/' . $ad);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "1) e-Reçete no\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('1A2B3C4', tezgah_recete_no(' 1a2b-3c4 '), 'boşluk/tire atılır, büyük harf');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('9Z8Y7X', tezgah_recete_no('9z8y7x'), 'küçük harf');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('3IK4', tezgah_recete_no('3ık4'), 'Türkçe ı → I');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('', tezgah_recete_no('ABCDEFG'), 'rakamsız reddedilir');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('', tezgah_recete_no('12'), 'kısa reddedilir');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('', tezgah_recete_no('1A2B3C4D5E6F7'), 'uzun reddedilir');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('', tezgah_recete_no('1A2B"><x'), 'özel karakter reddedilir');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "2) Medula metninden ön dolum (uzak + yakın)\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$d = tezgah_on_dolum(sgk_parse($ornek('recete-yakin.metin.txt')));
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('ayri_uzak_yakin', $d['lens_design'], 'hasta tercihi: ayrı uzak yakın');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('2026-09-01', $d['prescription_date'], 'reçete tarihi');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('9Z8Y7X', $d['erecete'], 'e-reçete no');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(['İsmail Şükrü', 'Çağlayangöz'], [$d['ad'], $d['soyad']], 'ad soyad Türkçe büyük/küçük');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok($d['yakin'], 'yakın değerler var');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok($d['right_sph'] !== '' && $d['near_right_sph'] !== '', 'uzak ve yakın SPH dolu');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(!array_key_exists('tc', $d) && !in_array('12345678901', $d, true), 'T.C. kimlik no ön dolumda yok (KVKK)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
foreach (['right_sph', 'right_cyl', 'right_axis', 'right_add', 'left_sph', 'left_cyl', 'left_axis', 'left_add', 'pd', 'doctor', 'near_left_sph'] as $k) {
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    ok(array_key_exists($k, $d), "reçete ekranı alanı: $k");
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
}
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "3) Yalnız uzak reçete\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$u = tezgah_on_dolum(sgk_parse($ornek('recete-uzak.metin.txt')));
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('tek_odak_uzak', $u['lens_design'], 'tek odak uzak');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(!$u['yakin'] && $u['near_right_sph'] === '', 'yakın yok');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "3b) 4.39.1 Tek yakın (okuma gözlüğü): CYL/AKS'li yakın, ADD yok\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$tyc = sgk_parse((string) file_get_contents(__DIR__ . '/ornek/recete-tek-yakin.metin.txt'));
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('tek_odak_yakin', sgk_lens_design($tyc), 'yalnız YAKIN bölümü → tek odak yakın (ayrı uzak+yakın değil)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$ty = tezgah_on_dolum($tyc);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(['+2.75', '-0.75', '170', ''], [$ty['right_sph'], $ty['right_cyl'], $ty['right_axis'], $ty['right_add']], 'sağ yakın SPH/CYL/AKS reçete satırında, ADD boş');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(['+2.50', '-0.50', '15'], [$ty['left_sph'], $ty['left_cyl'], $ty['left_axis']], 'sol yakın CYL/AKS geçti');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('ayri_uzak_yakin', sgk_lens_design(sgk_parse($ornek('recete-yakin.metin.txt'))), 'uzak + yakın birlikte → ayrı uzak yakın (değişmedi)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$dom = (string) file_get_contents(dirname(__DIR__, 2) . '/app/domain.php');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($dom, "(\$design === 'ayri_uzak_yakin' && !\$yakinYazili)") && !str_contains($dom, "'ayri_uzak_yakin', 'tek_odak_yakin'], true)\n        && \$data['right_add']"), 'tek odak yakında ADD zorunlu değil');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "4) Boş (elle giriş) formu\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$b = tezgah_on_dolum(['sag' => [], 'sol' => [], 'yakin_sag' => [], 'yakin_sol' => []]);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(date('Y-m-d'), $b['prescription_date'], 'tarih bugün');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(['', '', 'tek_odak_uzak'], [$b['right_sph'], $b['erecete'], $b['lens_design']], 'alanlar boş');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "5) Bekleyen aktarımlar\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$simdi = date('Y-m-d H:i:s');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$g1 = insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'masaustu', 'raw_text' => 'x', 'parsed' => json_encode(['hasta' => 'AYŞE YILMAZ', 'erecete' => '1a2b3c4', 'tc' => '12345678901']), 'created_at' => $simdi]);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'masaustu', 'raw_text' => 'x', 'parsed' => '{}', 'created_at' => $simdi, 'used_order_id' => 9]);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
insert('sgk_incoming', ['user_id' => 2, 'kaynak' => 'masaustu', 'raw_text' => 'x', 'parsed' => '{}', 'created_at' => $simdi]);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'masaustu', 'raw_text' => 'x', 'parsed' => '{}', 'created_at' => date('Y-m-d H:i:s', time() - 3 * 86400)]);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$bek = tezgah_bekleyenler(1);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(1, count($bek), 'yalnız kendi, kullanılmamış, son 2 gün');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit([$g1, 'AYŞE YILMAZ', '1A2B3C4'], [$bek[0]['id'], $bek[0]['hasta'], $bek[0]['erecete']], 'hasta ve no');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(!str_contains(json_encode($bek), '12345678901'), 'T.C. no listede yok');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "6) Teslim sözü (iş günü, pazar atlanır)\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$cuma = strtotime('2026-10-16 10:00');   // cuma
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('2026-10-20', tezgah_teslim_tarihi($cuma), 'cuma + 3 iş günü = salı (pazar atlanır)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
setting_set('tezgah_teslim_gun', '0');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('2026-10-16', tezgah_teslim_tarihi($cuma), '0 gün = bugün');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
setting_set('tezgah_teslim_gun', '1');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('2026-10-17', tezgah_teslim_tarihi($cuma), 'cumartesi iş günü sayılır');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "7) 4.34.0 tek akış: bekleyen reçete → sipariş\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
// app/domain.php testte yüklenmez: kullanılan yardımcıların sade eşleri (cam satırı stok durumuyla yazılır)
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
if (!function_exists('lens_designs')) {
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    function lens_designs(): array { return ['tek_odak_uzak' => 'Tek odak uzak', 'tek_odak_yakin' => 'Tek odak yakın', 'ayri_uzak_yakin' => 'Ayrı uzak + yakın', 'progressive' => 'Progressive']; }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    function rx_line(array $r, string $s): string { return trim(($r[$s . '_sph'] ?? '') . ' ' . ($r[$s . '_cyl'] ?? '')); }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    function near_sphere(string $sph, string $add): string { return $add === '' ? $sph : sprintf('%+.2f', (float) $sph + (float) $add); }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    function stage_label(?string $k): string { return (string) $k; }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    function sync_lens_items(int $rxId, array $rx, ?array $near, array $stock): void {
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
        if ($rx['lens_design'] !== 'tek_odak_yakin') {
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
            insert('prescription_lens_items', ['prescription_id' => $rxId, 'lens_no' => 1, 'stock_status' => $stock['uzak'] ?? 'stokta_var', 'lens_type' => $rx['lens_type']]);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
        }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
        if ($near !== null) {
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
            insert('prescription_lens_items', ['prescription_id' => $rxId, 'lens_no' => 3, 'stock_status' => $stock['yakin'] ?? 'stokta_var', 'lens_type' => $near['lens_type'], 'item_group' => 'yakin']);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
        }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    function sgk_katki_uygula(int $o, int $c, array $rx): array { $GLOBALS['__sgk'][] = $o; return ['tutar' => 120.0, 'uyari' => null, 'korundu' => false]; }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
}
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
foreach (['prescription_date', 'lens_design', 'lens_eyes', 'lens_type', 'right_sph', 'right_cyl', 'right_axis', 'right_add', 'right_pd', 'right_height',
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
          'left_sph', 'left_cyl', 'left_axis', 'left_add', 'left_pd', 'left_height', 'pd', 'prescription_note', 'doctor', 'customer_id', 'sgk_rapor_no', 'sgk_rapor_tarihi', 'created_by', 'created_at'] as $s) {
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    db()->exec("ALTER TABLE prescription_records ADD COLUMN $s TEXT NULL");
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
}
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
db()->exec('CREATE TABLE near_prescription_details (id INTEGER PRIMARY KEY AUTOINCREMENT, prescription_id INTEGER, usage_type TEXT, lens_type TEXT, right_sph TEXT, right_cyl TEXT, right_axis TEXT, left_sph TEXT, left_cyl TEXT, left_axis TEXT)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
db()->exec('ALTER TABLE orders ADD COLUMN status TEXT NULL');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
db()->exec('ALTER TABLE orders ADD COLUMN updated_at TEXT NULL');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$rxVeri = ['prescription_date' => '2026-10-16', 'lens_design' => 'ayri_uzak_yakin', 'lens_eyes' => 'both', 'lens_type' => 'X', 'advisor_summary' => 'x', 'advisor_product_id' => null,
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    'right_sph' => '-2.00', 'right_cyl' => '-0.50', 'right_axis' => '90', 'right_add' => '+2.00', 'right_pd' => '', 'right_height' => '',
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
    'left_sph' => '-1.75', 'left_cyl' => '', 'left_axis' => '', 'left_add' => '+2.00', 'left_pd' => '', 'left_height' => '', 'pd' => '62', 'prescription_note' => '', 'doctor' => 'Dr. Test'];
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$yakinVeri = ['usage_type' => 'ayri_uzak_yakin', 'lens_type' => 'X', 'right_sph' => '+0.00', 'right_cyl' => '-0.50', 'right_axis' => '90', 'left_sph' => '+0.25', 'left_cyl' => '', 'left_axis' => ''];
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$mus = insert('customers', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321112233']);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$rc = recete_bekleyen_kaydet($mus, $rxVeri, $yakinVeri, '1A2B3C4', $g1, 1);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$r = recete_bekleyen($rc);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit([$mus, '1A2B3C4', $g1, 0], [$r['customer_id'], $r['erecete'], $r['gelen_id'], $r['used_order_id']], 'müşteriye bağlı, e-reçete ve aktarım no');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(!array_key_exists('lens_type', $r['rx']) && !array_key_exists('advisor_summary', $r['rx']), 'cam tipi reçetede saklanmaz (siparişte seçilir)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('-2.00', $r['rx']['right_sph'], 'değerler aynen');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(!str_contains((string) scalar('SELECT veri FROM bekleyen_receteler WHERE id = ?', [$rc]), '12345678901'), 'T.C. no saklanmaz');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(0, count(tezgah_bekleyenler(1)), 'müşteriye bağlanan aktarım "kaydedilmeyenler" listesinden düşer');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit($rc, recete_son_bekleyen($mus)['id'] ?? 0, 'müşterinin son bekleyen reçetesi');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains(recete_ozet($r), 'PD 62'), 'özet satırı');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
[$uzakRx, $uzakYakin] = recete_kullanim_icin($r, 'tek_odak_uzak');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok($uzakRx['lens_design'] === 'tek_odak_uzak' && $uzakYakin === null, 'uzak gözlüğü: yakın değer yok');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
[$yRx, $yY] = recete_kullanim_icin($r, 'tek_odak_yakin');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(['tek_odak_yakin', '+0.25'], [$yRx['lens_design'], $yY['left_sph']], 'yakın gözlüğü: reçetedeki yakın SPH');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
[, $hesap] = recete_kullanim_icin(['rx' => ['right_sph' => '-1.00', 'right_add' => '+2.50', 'left_sph' => '', 'left_add' => ''] + $rxVeri, 'near' => null], 'tek_odak_yakin');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('+1.50', $hesap['right_sph'], 'yakın değeri yoksa SPH + ADD');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$s1 = insert('orders', ['customer_id' => $mus, 'first_name' => 'Ayşe', 'order_stage' => 'siparis_verildi']);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$u1 = recete_siparise_uygula($rc, $s1, 'tek_odak_uzak', 'Organik 1.60', '', ['uzak' => 'stokta_var'], 1);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit([0, true], [$u1['eksik'], $u1['atolye']], 'camlar stokta → atölyeye');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('atolyede', scalar('SELECT order_stage FROM orders WHERE id = ?', [$s1]), 'sipariş atölyede');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(['Organik 1.60', '1A2B3C4'], array_values(row('SELECT lens_type, sgk_erecete FROM orders WHERE id = ?', [$s1])), 'cam tipi ve e-reçete siparişte');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(['1A2B3C4', 'tek_odak_uzak', (string) $mus], array_values(row('SELECT sgk_rapor_no, lens_design, customer_id FROM prescription_records WHERE id = ?', [$u1['rx']])), 'reçete siparişe yazıldı');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit([$s1, $s1], [(int) scalar('SELECT used_order_id FROM bekleyen_receteler WHERE id = ?', [$rc]), (int) scalar('SELECT used_order_id FROM sgk_incoming WHERE id = ?', [$g1])], 'reçete ve aktarım kullanıldı');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(recete_son_bekleyen($mus) === null, 'kullanılan reçete bekleyenlerde yok');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit([$s1], $GLOBALS['__sgk'], 'e-reçeteli siparişte SGK katkısı işlenir');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$s2 = insert('orders', ['customer_id' => $mus, 'first_name' => 'Ayşe', 'order_stage' => 'siparis_verildi']);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$u2 = recete_siparise_uygula($rc, $s2, 'tek_odak_yakin', 'Organik 1.50', '', ['yakin' => 'stokta_yok'], 1);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit([1, false], [$u2['eksik'], $u2['atolye']], 'eksik cam → atölyeye geçmez, depo listesinde');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit('siparis_verildi', scalar('SELECT order_stage FROM orders WHERE id = ?', [$s2]), 'eksik camlı sipariş beklemede');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit(1, (int) scalar('SELECT COUNT(*) FROM near_prescription_details WHERE prescription_id = ?', [$u2['rx']]), 'yakın gözlüğün yakın değerleri');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
esit($s1, (int) scalar('SELECT used_order_id FROM bekleyen_receteler WHERE id = ?', [$rc]), 'ikinci gözlük ilk kullanımı ezmez');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$baska = insert('customers', ['first_name' => 'Ali', 'last_name' => 'Kaya', 'phone' => '05320000000']);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$s3 = insert('orders', ['customer_id' => $baska, 'order_stage' => 'siparis_verildi']);
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$hata = '';
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
try { recete_siparise_uygula($rc, $s3, '', 'X', '', [], 1); } catch (DomainException $e) { $hata = $e->getMessage(); }
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($hata, 'müşterisine ait değil'), 'başka müşterinin siparişine yazılmaz');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(substr_count(akis_cubugu([2, 3]), 'aria-current="step"') === 2 && str_contains(akis_cubugu(3, 'teklif'), '<small>teklif</small>') && count(akis_adimlari()) === 4, 'adım çubuğu: 4 adım, aynı ekranda iki adım, altyazı');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
echo "8) Sayfa ve fiş kaynakları\n";
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$sayfa = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/tezgah.php');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(!str_contains($sayfa, "ozellik_gereksin('kagitsiz')"), '4.34.0: akışın girişi her mağazada açık');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($sayfa, 'recete_bekleyen_kaydet(') && str_contains($sayfa, 'recete_siparise_uygula($receteId, $orderId'), '4.38.0: reçete müşteriye bağlanır; sözlü anlaşmada sipariş aynı ekrandan');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($sayfa, "post('sonra') === 'teklif' ? 'teklif' : 'siparis'") && str_contains($sayfa, 'value="teklif" formnovalidate') && str_contains($sayfa, 'Siparişi kaydet, fişi yazdır'), 'teklif zorunlu değil: varsayılan sipariş, teklif isteğe bağlı');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($sayfa, '<details class="ek-alan"') && str_contains($sayfa, 'data-yakin-alan') && str_contains($sayfa, 'data-kapora-yontem'), 'isteğe bağlı alanlar katlanır / gerektiğinde görünür');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$lay = (string) file_get_contents(dirname(__DIR__, 2) . '/app/layout.php');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($lay, 'function menu_gruplari(') && str_contains($lay, "'Atölye ve stok'"), 'menü iş gruplarına ayrılır');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$sip = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/order.php');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($sip, 'class="hizli-asama"') && str_contains($sip, '<details class="card stage-changer">') && str_contains($sip, '<details class="card siparis-bilgi">') && str_contains($sip, '<details class="card gecmis">'), '4.39.0 sipariş sayfası: tek tık sonraki aşama, katlanır kartlar');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$tk = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/teklif-yeni.php');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($tk, 'data-secenek-gizli') && str_contains($tk, 'data-secenek-ekle') && str_contains($tk, '<summary class="card-head"><h2>İskonto ve not</h2>'), '4.39.0 teklif formu: ek seçenekler istenince, iskonto katlanır');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($lay, 'href="tezgah.php"><?= icon(\'plus\') ?> Yeni müşteri · reçete') && !str_contains($lay, "'SGK reçete aktar'"), 'menü: tek giriş, SGK reçete aktar kalktı');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/sgk-aktar.php'), "redirect('tezgah.php?gelen='"), 'Pro aktarımı akışta açılır');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($sayfa, 'csrf_check()'), 'CSRF denetimi');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($sayfa, 'rx_from_post()'), 'reçete doğrulaması reçete ekranıyla aynı');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(!preg_match('/<script>|onclick=/i', $sayfa), 'satır içi betik yok (CSP)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(!str_contains($sayfa, "['tc']"), 'sayfa T.C. no kullanmaz');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
$fis = (string) file_get_contents(dirname(__DIR__, 2) . '/app/partials/siparis-fisi.php');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($fis, 'Müşteri nüshası') && str_contains($fis, "query('nusha')"), 'fişte müşteri nüshası');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($fis, 'Montaj ölçüleri') && str_contains($fis, "'Kontrol · PD / aks'") && str_contains($fis, '<th>Paraf</th>'), 'iş emri: montaj ölçüleri, parafli atölye adımları');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($fis, 'class="is-emri') && str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/assets/siparis-fisi.css'), 'rotate(-90deg)'), '4.36.0 iş emri 148,5 × 210 dikey kart, döndürülerek basılır');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($fis, "preg_match('/^(Teklif #\\d+|Cam .*₺)/u'"), 'iş emri notunda teklifin fiyat satırları basılmaz');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($fis, 'class="tablo"') && str_contains($fis, "['LÜĞ', '0,3']") && str_contains($fis, 'Sırada ne var?') && !str_contains($fis, 'class="yol"'), '4.36.0 müşteri nüshası: göz tablosu, sırada ne var (ilerleme çubuğu yok)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains($fis, "str_replace(['-', '.'], ['−', ','], \$s)"), 'dioptri eksi işareti ve virgülle (kimlik kuralı)');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
ok(str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/assets/print.js'), 'data-otomatik-yazdir'), 'tezgâhtan gelince otomatik yazdır');
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');

ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
bitir();
ok(str_contains($fis, "['UZAK', 'uzak',") && str_contains($fis, "['YAKIN', 'yakin',") && str_contains($fis, 'class="blok-ad" rowspan="2"') && str_contains($fis, 'class="grup g-'), '4.39.2 fiş: UZAK ve YAKIN ayrı bloklar, cam satırında rozet');
