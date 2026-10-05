<?php
/* ==========================================================================
   OptiFlow — SEO veri köprüsü (4.8.0; 4.16.4'ten beri ayarlar merkez panelde)
   --------------------------------------------------------------------------
   Günlük SEO görevinin okuduğu, SALT-OKUNUR bir JSON uç noktası:
     • Google Search Console: son 28 gün arama sorguları (sıra, tıklama,
       gösterim, TO), sayfalar, günlük seri, cihaz.
     • PageSpeed Insights (mobil): performans/SEO puanı, LCP, CLS, TBT.

   Kurulum: Merkez panel › "SEO · Google" (anahtar dosyası, mülk, erişim
   anahtarı). Eski config.php anahtarları (seo_token, gsc_site, gsc_key_file,
   psi_api_key) hâlâ geçerlidir.

   Güvenlik:
     • Erişim anahtarı (?t=) olmadan hiçbir şey döndürmez (403).
     • Google hizmet hesabı anahtarı web'e KAPALI storage/ klasöründe durur;
       bu dosya anahtarı asla yazdırmaz.
     • Hasta/mağaza verisine dokunmaz; veritabanına bağlanmaz.
     • Sonuç 3 saat önbelleklenir (Google kotası + kötüye kullanım).
   ========================================================================== */
declare(strict_types=1);

if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    http_response_code(500);
    exit('PHP 8 gerekli');
}

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

require __DIR__ . '/app/seo.php';

$cfg = seo_config();
date_default_timezone_set((string) ($cfg['timezone'] ?? 'Europe/Istanbul'));

function cik(int $kod, array $veri): never
{
    http_response_code($kod);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$ayar = seo_ayar();
$gelen = (string) ($_GET['t'] ?? '');
if (strlen($ayar['token']) < 24) {
    cik(503, ['hata' => 'SEO bağlantısı kurulmamış: Merkez panel › SEO · Google.']);
}
if (!hash_equals($ayar['token'], $gelen)) {
    usleep(700000);                                   // tahmin denemelerini yavaşlat
    cik(403, ['hata' => 'yetkisiz']);
}

$taze = ($_GET['yenile'] ?? '') === '1';
if (!$taze && ($c = seo_onbellek_oku()) !== null) {
    $c['onbellek'] = true;
    cik(200, $c);
}

@set_time_limit(90);
cik(200, seo_veri_topla(($_GET['psi'] ?? '1') !== '0', $ayar));
