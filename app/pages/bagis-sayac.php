<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* Herkese açık bağış sayacı (giriş gerektirmez). Yalnızca iki toplam sayı ve başlangıç yılı döner;
   kişisel ya da parasal hiçbir bilgi içermez. Sitedeki "Gözlük Dayanışması" bölümü bunu okur. */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=600');
header('X-Robots-Tag: noindex');

$cikti = ['cerceve' => null, 'gozluk' => null, 'yil' => null];
try {
    require_once dirname(__DIR__) . '/donation.php';
    $cikti = donation_public_stats();
} catch (Throwable $e) {
    // sayaç okunamadı: site sabit metni gösterir
}
echo json_encode($cikti, JSON_UNESCAPED_UNICODE);
