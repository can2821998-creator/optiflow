<?php
declare(strict_types=1);
/* Eski sistemden veri taşıma (app/tasima.php): ayrıştırma, güvenlik doğrulaması, özet. Veritabanı kullanmaz. */
require dirname(__DIR__, 2) . '/app/helpers.php';
require dirname(__DIR__, 2) . '/app/tasima.php';

$gecen = 0;
$kalan = 0;
function dogru(string $ad, bool $k, string $ek = ''): void
{
    global $gecen, $kalan;
    if ($k) {
        $gecen++;
    } else {
        $kalan++;
        echo "  ✗ $ad" . ($ek !== '' ? " — $ek" : '') . "\n";
    }
}
function reddet(string $ad, callable $f, string $parca = ''): void
{
    try {
        $f();
        dogru($ad, false, 'reddedilmeliydi');
    } catch (DomainException $e) {
        dogru($ad, $parca === '' || str_contains($e->getMessage(), $parca), $e->getMessage());
    }
}

$ornek = (string) file_get_contents(__DIR__ . '/ornek-poyraz-yedek.sql');

echo "1) Bölme (tırnak / yorum farkında)\n";
$k = tasima_bol("SET NAMES utf8mb4;\n-- yorum; burada\nINSERT INTO `a` (`x`) VALUES ('noktalı; virgül'),('tırnak \\' kaçış'),('çift '' tırnak'),('-- değil'),('/* değil */');\n# diyez yorum\nDROP TABLE IF EXISTS `b`;");
dogru('üç komut', count($k) === 3, (string) count($k));
dogru('dize içindeki ; bölmez', str_contains($k[1][0], 'noktalı; virgül'));
dogru('kaçışlı tırnak korunur', str_contains($k[1][0], "tırnak \\' kaçış") && str_contains($k[1][0], "çift '' tırnak"));
dogru('dize içi yorum işaretleri korunur', str_contains($k[1][0], "'-- değil'") && str_contains($k[1][0], "'/* değil */'"));
dogru('iskelette dize içeriği yok', !str_contains($k[1][1], 'noktalı'));
reddet('kapanmamış tırnak', fn() => tasima_bol("INSERT INTO `a` VALUES ('yarım"), 'tırnak');
reddet('koşullu MySQL komutu', fn() => tasima_bol('/*!50003 CREATE TRIGGER x */;'), 'koşullu');

echo "2) Güvenlik: yalnızca yedek komutları\n";
$dene = static fn(string $s) => tasima_komut_dogrula(tasima_bol($s)[0][1]);
dogru('DROP TABLE', $dene('DROP TABLE IF EXISTS `orders`')[0] === 'drop');
dogru('CREATE TABLE', $dene("CREATE TABLE `t` (\n `id` int NOT NULL DEFAULT '0',\n PRIMARY KEY (`id`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci")[0] === 'create');
$ins = $dene("INSERT INTO `t` (`a`,`b`) VALUES (1,'x'),(NULL,'y'),(-2.5,'z')");
dogru('INSERT satır sayısı', $ins === ['insert', 't', 3], json_encode($ins));
foreach ([
    'DROP DATABASE x', 'DROP TABLE `orders`', 'USE mysql', 'GRANT ALL ON *.* TO x', 'SET GLOBAL general_log = 1', 'SET @a = 1',
    'CREATE TABLE `x` (a int) SELECT * FROM user_accounts', "CREATE TABLE `x` (a int) DATA DIRECTORY='/tmp'",
    'INSERT INTO `a` VALUES (SLEEP(5))', 'INSERT INTO `a` (`x`) VALUES ((SELECT password_hash FROM user_accounts))',
    "INSERT INTO `a` (`x`) VALUES (LOAD_FILE('/etc/passwd'))", 'INSERT INTO `a` SELECT * FROM b',
    "SELECT * INTO OUTFILE '/tmp/x' FROM a", 'UPDATE customers SET phone = 1', 'DELETE FROM orders', 'CREATE TRIGGER t BEFORE INSERT ON a FOR EACH ROW SET @x=1',
    'ALTER TABLE orders DROP COLUMN phone', 'LOAD DATA INFILE "x" INTO TABLE a', 'CALL p()', 'TRUNCATE TABLE orders',
] as $kotu) {
    reddet('reddedilir: ' . $kotu, fn() => $dene($kotu));
}

echo "3) Dosya düzeyi\n";
$p = tasima_coz($ornek);
dogru('kaynak Poyraz, eski', $p['kaynak'] === 'poyraz' && $p['eski'] === true);
dogru('sürüm / şema / tarih', $p['surum'] === '3.43.0' && $p['sema'] === 21 && $p['tarih'] === '01.10.2026 10:00:00', json_encode([$p['surum'], $p['sema'], $p['tarih']]));
dogru('tablo sayıları', ($p['tablolar']['customers'] ?? 0) === 3 && ($p['tablolar']['orders'] ?? 0) === 2 && ($p['tablolar']['payments'] ?? 0) === 2);
dogru('boş tablolar da listede', array_key_exists('frame_items', $p['tablolar']) && $p['tablolar']['frame_items'] === 0);
$gz = gzencode($ornek);
dogru('gzip okunur', tasima_coz($gz)['tablolar']['customers'] === 3);
reddet('bozuk gzip', fn() => tasima_coz(substr($gz, 0, 40)), 'açılamadı');
reddet('başlıksız dosya', fn() => tasima_coz("SET NAMES utf8mb4;\n-- Yedek sonu\n"), 'yedeği gibi görünmüyor');
reddet('yarım dosya (sonu yok)', fn() => tasima_coz(substr($ornek, 0, (int) (strlen($ornek) * 0.6))), '');
reddet('araya zararlı komut', fn() => tasima_coz(str_replace("SET FOREIGN_KEY_CHECKS=1;", "DROP DATABASE optiflow;\nSET FOREIGN_KEY_CHECKS=1;", $ornek)), 'izin verilmeyen');
reddet('tablo oluşturulmadan veri', fn() => tasima_coz(str_replace('CREATE TABLE `payments`', 'CREATE TABLE `payments_x`', $ornek)), 'oluşturulmadan');
reddet('sipariş/müşteri yoksa', fn() => tasima_coz("-- OptiFlow yedeği\n-- Sürüm: 4.17.0 · şema 29\nCREATE TABLE `a` (`x` int) ENGINE=InnoDB;\n-- Yedek sonu\n"), 'sipariş ve müşteri');
reddet('UTF-8 olmayan', fn() => tasima_coz("-- OptiFlow yedeği\n\xff\xfe-- Yedek sonu"), 'UTF-8');
reddet('izinsiz SQL_MODE', fn() => tasima_coz(str_replace("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';", "SET SQL_MODE='x'; SET NAMES latin1;", $ornek)), '');
$opt = "-- OptiFlow yedeği\n-- Veritabanı: x\n-- Tarih: 05.10.2026 17:00:00\n-- Sürüm: 4.17.0 · şema 29\n" . substr($ornek, strpos($ornek, 'SET NAMES'));
$po = tasima_coz($opt);
dogru('OptiFlow yedeği: eski değil', $po['kaynak'] === 'optiflow' && $po['eski'] === false);

echo "4) Onay metni\n";
dogru('geri al → GERİ AL', tasima_onay(' geri  al ') === 'GERİ AL');
dogru('taşı → TAŞI', tasima_onay('taşı') === 'TAŞI');

echo "Veri taşıma testleri: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
