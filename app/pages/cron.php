<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.12.0 — Zamanlanmış görev uç noktası (tüm mağazalar).
   Plesk › Zamanlanmış Görevler › "URL getir": https://<site>/cron.php?anahtar=<anahtar>
   Anahtar merkez panelinde gösterilir. Yanıtta mağaza verisi yoktur; yalnızca sayılar.
   ========================================================================== */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

$beklenen = cron_anahtar();
$gelen = (string) ($_GET['anahtar'] ?? $_SERVER['HTTP_X_CRON_ANAHTAR'] ?? '');
if ($beklenen === '' || !hash_equals($beklenen, $gelen)) {
    http_response_code(403);
    echo "yetkisiz\n";
    exit;
}
// Aynı anda iki cron çalışmasın (merkez veritabanında kilit).
if (!(int) merkez_scalar("SELECT GET_LOCK('optiflow_cron', 1)")) {
    echo "zaten çalışıyor\n";
    exit;
}
@set_time_limit(240);
$bas = microtime(true);
$sonuc = cron_tum_magazalar();
merkez_scalar("SELECT RELEASE_LOCK('optiflow_cron')");
$toplamGonderilen = 0;
$toplamKuyruk = 0;
foreach ($sonuc as $r) {
    if (is_array($r)) {
        $toplamGonderilen += $r['gonderilen'];
        $toplamKuyruk += $r['kuyruga'];
    }
}
echo 'tamam · mağaza ' . count($sonuc) . ' · kuyruğa ' . $toplamKuyruk . ' · gönderilen ' . $toplamGonderilen
    . ' · ' . round(microtime(true) - $bas, 2) . " sn\n";
