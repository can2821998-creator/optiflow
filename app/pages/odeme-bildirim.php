<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.12.0 — PayTR ödeme bildirimi (sunucudan sunucuya, oturumsuz).
   Mağaza, callback_id içindeki numaradan bulunur; bildirim o mağazanın
   PayTR anahtarıyla (HMAC) doğrulanmadan HİÇBİR kayıt yazılmaz.
   PayTR yalnızca düz "OK" yanıtını kabul eder; başka yanıtta tekrar dener.
   ========================================================================== */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'POST';
    exit;
}
$p = [];
foreach (['callback_id', 'merchant_oid', 'status', 'total_amount', 'hash', 'payment_amount', 'payment_type', 'currency', 'merchant_id', 'test_mode', 'failed_reason_msg'] as $k) {
    if (isset($_POST[$k]) && is_string($_POST[$k])) {
        $p[$k] = mb_substr($_POST[$k], 0, 255);
    }
}
$magazaId = odeme_callback_magaza((string) ($p['callback_id'] ?? ''));
if ($magazaId <= 0) {
    echo 'OK';   // bize ait olmayan biçim: tekrar denenmesin
    exit;
}
$m = magaza_baglan_id($magazaId, false);
if (!$m) {
    echo 'OK';
    exit;
}
setting('__reload__');
if ((int) setting('schema_version', '0') < 22) {
    http_response_code(503);
    echo 'GUNCELLENIYOR';   // PayTR tekrar dener; ilk personel girişinde şema güncellenir
    exit;
}
try {
    $yanit = odeme_bildirim_isle($p);
} catch (Throwable $e) {
    app_log('odeme-bildirim: ' . $e->getMessage());
    http_response_code(500);
    $yanit = 'HATA';
}
echo $yanit;
