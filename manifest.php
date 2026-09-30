<?php
declare(strict_types=1);
/**
 * PWA uygulama bildirimi. Mağaza adı ayarlardan gelir; ayarlar okunamazsa
 * varsayılan ada düşer, böylece bildirim her koşulda geçerli JSON döner.
 */
require __DIR__ . '/app/bootstrap.php';

$shop = 'OptiFlow';
try {
    $shop = (string) setting('shop_name', 'OptiFlow');
} catch (Throwable $e) {
    // ayarlar okunamadı: varsayılan ad kullanılır
}
$short = mb_substr($shop, 0, 12);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');

echo json_encode([
    'id'                => '/',
    'name'              => $shop . ' · Atölye',
    'short_name'        => $short,
    'description'       => 'Reçeteden teslimata optik atölye takip sistemi',
    'lang'              => 'tr',
    'dir'               => 'ltr',
    'start_url'         => 'index.php',
    'scope'             => './',
    'display'           => 'standalone',
    'display_override'  => ['standalone', 'minimal-ui'],
    'orientation'       => 'portrait-primary',
    'background_color'  => '#faf8f4',
    'theme_color'       => '#0a2038',
    'categories'        => ['business', 'productivity', 'medical'],
    'icons' => [
        ['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ['src' => 'assets/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
    ],
    'shortcuts' => [
        [
            'name'        => 'Yeni sipariş',
            'short_name'  => 'Yeni sipariş',
            'description' => 'Doğrudan yeni sipariş ekranını aç',
            'url'         => 'order-new.php',
            'icons'       => [['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192']],
        ],
        [
            'name'        => 'Atölye panosu',
            'short_name'  => 'Atölye',
            'description' => 'Bekleyen ve hazır işleri gör',
            'url'         => 'workshop.php',
            'icons'       => [['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192']],
        ],
        [
            'name'        => 'Bugün teslim',
            'short_name'  => 'Teslimat',
            'description' => 'Teslim sözü verilenler',
            'url'         => 'deliveries.php',
            'icons'       => [['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192']],
        ],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
