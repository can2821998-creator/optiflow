<?php
/* 4.42.0 KVKK: T.C. kimlik no Medula metinlerinden kaydetmeden önce gizlenir; eski kayıtlar göç v35 ile temizlenir. */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';

$ornek = static fn(string $ad): string => (string) file_get_contents(dirname(__DIR__, 2) . '/desktop/tests/fixtures/' . $ad);
$tc = '12345678901';

echo "1) tc_maskele\n";
esit('T.C. Kimlik No	' . TC_MASKE . '	E-Reçete No	9Z8Y7X', tc_maskele("T.C. Kimlik No	$tc	E-Reçete No	9Z8Y7X"), 'sekmeli satırda');
esit('«' . TC_MASKE . '»', tc_maskele("«{$tc}»"), 'kutu değeri içinde');
esit('TC:' . TC_MASKE . '.', tc_maskele("TC:$tc."), 'noktalama bitişik');
esit('Tel 05321234567', tc_maskele('Tel 05321234567'), 'cep telefonu (0 ile başlar) kalır');
esit('No 123456789012', tc_maskele('No 123456789012'), '12 haneli sayı kalır');
esit('No 1234567890', tc_maskele('No 1234567890'), '10 haneli sayı kalır');
esit('1A2B3C4 · 01.09.2026', tc_maskele('1A2B3C4 · 01.09.2026'), 'e-reçete no ve tarih kalır');
esit(TC_MASKE . ' ' . TC_MASKE, tc_maskele("$tc 98765432109"), 'birden çok');
ok(!preg_match('/\d/', TC_MASKE), 'işarette rakam yok');

echo "2) sgk_parse T.C. döndürmez, diğer alanlar bozulmaz\n";
$c = sgk_parse($ornek('recete-yakin.metin.txt'));
esit('', $c['tc'], 'tc boş');
ok(!str_contains(json_encode($c, JSON_UNESCAPED_UNICODE), $tc), 'çözümde hiçbir yerde T.C. yok');
esit('9Z8Y7X', $c['erecete'], 'e-reçete no okunur');
ok($c['ad'] !== '' && $c['soyad'] !== '', 'ad soyad okunur');
ok($c['sag']['sph'] !== '' && $c['sol']['sph'] !== '', 'numaralar okunur');
$u = sgk_parse($ornek('recete-uzak.metin.txt'));
ok($u['sag']['sph'] !== '' && $u['tc'] === '', 'uzak örnek de');

echo "3) Hak sorgusu satırları\n";
$h = sgk_hak_coz("Hasta T.C. $tc\n01.03.2025 Uzak gözlük camı teslim TC $tc\nHakkı bulunmaktadır");
ok($h['satirlar'] !== [], 'alım satırı bulundu');
ok(!str_contains(json_encode($h, JSON_UNESCAPED_UNICODE), $tc), 'satırlarda T.C. yok');
esit('2025-03-01', $h['son_alim'], 'tarih bozulmadı');
$id = sgk_hak_kaydet($h, 'yapistir', null, null);
ok(!str_contains((string) scalar('SELECT satirlar FROM sgk_hak_sorgulari WHERE id = ?', [$id]), $tc), 'kayıtta T.C. yok');

echo "4) Eski kayıtların temizlenmesi (göç v35)\n";
$ham = $ornek('recete-yakin.metin.txt');
$eskiParsed = json_encode(['tc' => $tc, 'hasta' => 'İSMAİL', 'satirlar' => ["T.C. Kimlik No $tc"]], JSON_UNESCAPED_UNICODE);
$g1 = insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'masaustu', 'raw_text' => $ham, 'parsed' => $eskiParsed, 'created_at' => date('Y-m-d H:i:s')]);
$g2 = insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'masaustu', 'raw_text' => 'T.C. yok, tel 05321234567', 'parsed' => '{}', 'created_at' => date('Y-m-d H:i:s')]);
$g3 = insert('sgk_incoming', ['user_id' => 1, 'kaynak' => 'kopru', 'raw_text' => "TC $tc", 'parsed' => 'bozuk json', 'created_at' => date('Y-m-d H:i:s')]);
$h2 = insert('sgk_hak_sorgulari', ['kaynak' => 'yapistir', 'satirlar' => json_encode([['tarih' => '2025-01-01', 'metin' => "Cam teslim $tc"]], JSON_UNESCAPED_UNICODE), 'created_at' => date('Y-m-d H:i:s')]);
esit(3, tc_eski_kayitlari_maskele(), 'üç kayıt değişti (T.C.siz kayda dokunulmaz)');
$hepsi = json_encode(rows('SELECT raw_text, parsed FROM sgk_incoming'), JSON_UNESCAPED_UNICODE) . json_encode(rows('SELECT satirlar FROM sgk_hak_sorgulari'), JSON_UNESCAPED_UNICODE);
ok(!str_contains($hepsi, $tc), 'veritabanında T.C. kalmadı');
esit('', json_decode((string) scalar('SELECT parsed FROM sgk_incoming WHERE id = ?', [$g1]), true)['tc'], 'parsed.tc boşaltıldı');
esit('T.C. yok, tel 05321234567', scalar('SELECT raw_text FROM sgk_incoming WHERE id = ?', [$g2]), 'telefon dokunulmadı');
esit('9Z8Y7X', sgk_parse((string) scalar('SELECT raw_text FROM sgk_incoming WHERE id = ?', [$g1]))['erecete'], 'gizlenmiş metin yine çözülür');
esit(0, tc_eski_kayitlari_maskele(), 'ikinci çalıştırma bir şey değiştirmez');

echo "5) Kayıt yerleri ve göç\n";
$kok = dirname(__DIR__, 2);
foreach (['app/pages/masaustu.php', 'app/pages/sgk-aktar.php'] as $f) {
    ok(str_contains((string) file_get_contents("$kok/$f"), "'raw_text'   => tc_maskele(\$metin)"), "$f ham metni gizleyerek yazar");
}
ok(!str_contains((string) file_get_contents("$kok/app/pages/sgk-aktar.php"), "\$cozum['tc']"), 'aktarım ekranı T.C. göstermez');
$goc = (string) file_get_contents("$kok/app/migrations.php");
ok(str_contains($goc, 'const SCHEMA_VERSION = 35;') && str_contains($goc, 'migrate_v35_tc_maskele()'), 'göç v35 bağlı');

bitir();
