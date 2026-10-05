<?php
/* ==========================================================================
   OptiFlow Pro indirme bilgisi (4.16.5)
   indir/masaustu/latest.yml'den (Canlıya al iş akışının yüklediği, kurulu
   uygulamaların güncelleme için okuduğu dosya) sürüm, dosya adı ve boyutu okur.
   Bootstrap'e / veritabanına bağlı değildir (tanıtım sayfası da kullanır).
   ========================================================================== */
declare(strict_types=1);

const INDIR_SAYFA = 'indir.php';               // kalıcı, paylaşılabilir indirme sayfası
const INDIR_KLASOR = 'indir/masaustu';

/**
 * @return array{surum:string,dosya:string,url:string,boyut_mb:int,tarih:string}|null
 *         latest.yml yoksa / okunamıyorsa null.
 */
function indir_masaustu_bilgi(?string $kok = null): ?array
{
    static $onbellek = [];
    $kok ??= dirname(__DIR__);
    if (array_key_exists($kok, $onbellek)) {
        return $onbellek[$kok];
    }
    $yml = $kok . '/' . INDIR_KLASOR . '/latest.yml';
    $metin = is_file($yml) ? (string) @file_get_contents($yml, false, null, 0, 8192) : '';
    $bilgi = null;
    if ($metin !== ''
        && preg_match('/^version:\s*[\'"]?([0-9]+\.[0-9]+\.[0-9]+[0-9A-Za-z.\-]*)/m', $metin, $v)
        && preg_match('/^path:\s*[\'"]?([A-Za-z0-9._\-]+\.exe)/m', $metin, $d)) {
        $boyut = preg_match('/^\s+size:\s*(\d+)/m', $metin, $b) ? (int) $b[1] : 0;
        if ($boyut === 0 && is_file($kok . '/' . INDIR_KLASOR . '/' . $d[1])) {
            $boyut = (int) filesize($kok . '/' . INDIR_KLASOR . '/' . $d[1]);
        }
        $tarih = preg_match('/^releaseDate:\s*[\'"]?(\d{4}-\d{2}-\d{2})/m', $metin, $t) ? $t[1] : '';
        $bilgi = [
            'surum'    => $v[1],
            'dosya'    => $d[1],
            'url'      => INDIR_KLASOR . '/' . rawurlencode($d[1]),
            'boyut_mb' => $boyut > 0 ? max(1, (int) round($boyut / 1048576)) : 0,
            'tarih'    => $tarih,
        ];
    }
    return $onbellek[$kok] = $bilgi;
}

/** "5.3.0 · 98 MB" gibi kısa etiket; bilgi yoksa ''. */
function indir_etiket(?array $b): string
{
    if (!$b) {
        return '';
    }
    return $b['surum'] . ($b['boyut_mb'] ? ' · ' . $b['boyut_mb'] . ' MB' : '');
}
