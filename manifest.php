<?php
declare(strict_types=1);
/**
 * PWA uygulama bildirimi. Mağaza adı ayarlardan gelir; ayarlar okunamazsa
 * varsayılan ada düşer, böylece bildirim her koşulda geçerli JSON döner.
 * 4.20.3: mağaza oturumu yokken de (mağaza girişi ekranı) genel "OptiFlow" bildirimi döner.
 */
require __DIR__ . '/app/bootstrap.php';

$shop = 'OptiFlow';
if (tenant_oturum()) {
    try {
        $shop = (string) setting('shop_name', 'OptiFlow');
    } catch (Throwable $e) {
        // ayarlar okunamadı: varsayılan ad kullanılır
    }
}
$short = mb_substr($shop, 0, 12);

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: private, max-age=3600');   // içerik oturuma (mağaza adına) göre değişir
header('Vary: Cookie');

echo json_encode([
    'id'                => '/',
    'name'              => $shop === 'OptiFlow' ? 'OptiFlow · Optik atölye' : $shop . ' · Atölye',
    'short_name'        => $short,
    'description'       => 'Reçeteden teslimata optik atölye takip sistemi',
    'lang'              => 'tr',
    'dir'               => 'ltr',
    'start_url'         => 'index.php',
    'scope'             => './',
    'display'           => 'standalone',
    'display_override'  => ['standalone', 'minimal-ui'],
    'orientation'       => 'portrait-primary',
    // 4.20.3: açılış ekranı tema rengiyle aynı (siyah); açık zeminden siyaha geçişteki parlama yok
    'background_color'  => '#141012',
    'theme_color'       => '#141012',
    'categories'        => ['business', 'productivity', 'medical'],
    'icons' => [
        ['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => 'assets/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ['src' => 'assets/favicon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
    ],
    // Android kurulum penceresinde uygulama görselleri (tanıtım sayfasındaki örnek mağaza ekranları)
    'screenshots' => [
        ['src' => 'assets/onizleme/telefon.webp', 'sizes' => '780x1560', 'type' => 'image/webp', 'form_factor' => 'narrow', 'label' => 'Telefonda sipariş takibi'],
        ['src' => 'assets/onizleme/liste.webp', 'sizes' => '1280x800', 'type' => 'image/webp', 'form_factor' => 'wide', 'label' => 'Sipariş listesi'],
        ['src' => 'assets/onizleme/atolye.webp', 'sizes' => '1280x800', 'type' => 'image/webp', 'form_factor' => 'wide', 'label' => 'Atölye panosu'],
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
