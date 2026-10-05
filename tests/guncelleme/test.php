<?php
/* Canlıya yükleme sırasında bakım ekranı (app/guncelleme.php + bootstrap + iş akışı). Ağ ve veritabanı kullanmaz. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/guncelleme.php';
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

$kok = sys_get_temp_dir() . '/optiflow-guncelleme-' . getmypid();
@mkdir($kok);
dogru('işaret yok', !guncelleme_suruyor($kok));
touch($kok . '/.guncelleniyor');
dogru('taze işaret', guncelleme_suruyor($kok));
touch($kok . '/.guncelleniyor', time() - 1300);
dogru('20 dk eski işaret yok sayılır', !guncelleme_suruyor($kok));
@unlink($kok . '/.guncelleniyor');
@rmdir($kok);

$cagir = function (string $betik, string $accept = '', string $sorgu = ''): string {
    $kod = '$_SERVER["SCRIPT_NAME"]=' . var_export('/' . $betik, true) . '; $_SERVER["HTTP_ACCEPT"]=' . var_export($accept, true)
        . '; parse_str(' . var_export($sorgu, true) . ', $_GET);'
        . 'require ' . var_export(dirname(__DIR__, 2) . '/app/guncelleme.php', true) . '; guncelleme_yaniti();';
    $p = proc_open([PHP_BINARY, '-r', $kod], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $b);
    $out = (string) stream_get_contents($b[1]);
    stream_get_contents($b[2]);
    proc_close($p);
    return $out;
};
$o = $cagir('index.php');
dogru('sayfa: bakım metni + kendini yenileme', str_contains($o, 'OptiFlow güncelleniyor') && str_contains($o, 'http-equiv="refresh"'));
dogru('sayfa: satır içi betik yok (CSP)', !str_contains($o, '<script'));
$o = $cagir('masaustu.php');
dogru('masaüstü köprüsü: JSON', (json_decode($o, true)['kod'] ?? '') === 'guncelleniyor', $o);
dogru('eklenti köprüsü: JSON', (json_decode($cagir('sgk-aktar.php', '', 'action=kopru'), true)['kod'] ?? '') === 'guncelleniyor');
dogru('SGK sayfası: HTML', str_contains($cagir('sgk-aktar.php'), '<!doctype html>'));
dogru('Accept JSON: JSON', (json_decode($cagir('order.php', 'application/json'), true)['kod'] ?? '') === 'guncelleniyor');

$bs = (string) file_get_contents(dirname(__DIR__, 2) . '/app/bootstrap.php');
$i = strpos($bs, 'guncelleme_suruyor(APP_ROOT)');
dogru('bootstrap en başta kontrol eder', $i !== false && $i < (int) strpos($bs, 'function early_fail'));
$wf = (string) file_get_contents(dirname(__DIR__, 2) . '/.github/workflows/canliya-al.yml');
dogru('iş akışı: geçici dosyayla yükleme', str_contains($wf, 'xfer:use-temp-file yes'));
dogru('iş akışı: işaret yüklemeden önce konur', strpos($wf, 'Bakım işaretini koy') < strpos($wf, 'Sunucu dosyalarını yükle'));
dogru('iş akışı: işaret her durumda kaldırılır', (bool) preg_match('/Bakım işaretini kaldır\s*\n\s*if: always\(\)/u', $wf));
echo "Güncelleme bakım ekranı testleri: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
