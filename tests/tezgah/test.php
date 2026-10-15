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

echo "7) Sayfa ve fiş kaynakları\n";
$sayfa = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/tezgah.php');
ok(str_contains($sayfa, "ozellik_gereksin('kagitsiz')"), 'özellik kapısı');
ok(str_contains($sayfa, 'csrf_check()'), 'CSRF denetimi');
ok(str_contains($sayfa, 'rx_from_post()'), 'reçete doğrulaması reçete ekranıyla aynı');
ok(!preg_match('/<script>|onclick=/i', $sayfa), 'satır içi betik yok (CSP)');
ok(!str_contains($sayfa, "['tc']"), 'sayfa T.C. no kullanmaz');
$fis = (string) file_get_contents(dirname(__DIR__, 2) . '/app/partials/siparis-fisi.php');
ok(str_contains($fis, 'Müşteri nüshası') && str_contains($fis, "query('nusha')"), 'fişte müşteri nüshası');
ok(str_contains((string) file_get_contents(dirname(__DIR__, 2) . '/assets/print.js'), 'data-otomatik-yazdir'), 'tezgâhtan gelince otomatik yazdır');

bitir();
