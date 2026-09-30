<?php
declare(strict_types=1);

/** Dinamik site haritası: ana sayfa, rehber listesi, yayındaki rehber yazıları, KVKK. Bootstrap yüklemez. */
require_once dirname(__DIR__) . '/rehber.php';

header('Content-Type: application/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$kok = dirname(__DIR__, 2);
$mt = static fn(string $f): string => is_file($kok . '/' . $f) ? date('Y-m-d', (int) filemtime($kok . '/' . $f)) : date('Y-m-d');
$yazilar = rehber_hepsi(true);
$sonYazi = '';
foreach ($yazilar as $y) {
    $sonYazi = max($sonYazi, (string) ($y['guncelleme'] ?: $y['yayin_tarihi']));
}
$urls = [
    ['https://optiflow.com.tr/', $mt('app/karsilama.php'), 'weekly', '1.0'],
    [rehber_url(), $sonYazi ?: $mt('app/pages/rehber.php'), 'weekly', '0.8'],
];
foreach ($yazilar as $y) {
    $urls[] = [rehber_url($y['slug']), (string) ($y['guncelleme'] ?: $y['yayin_tarihi']), 'monthly', '0.7'];
}
$urls[] = ['https://optiflow.com.tr/kvkk.php', $mt('app/pages/kvkk.php'), 'yearly', '0.2'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $lm, $cf, $pr]) {
    echo '  <url><loc>' . htmlspecialchars($loc, ENT_XML1) . '</loc>'
        . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $lm) ? '<lastmod>' . $lm . '</lastmod>' : '')
        . '<changefreq>' . $cf . '</changefreq><priority>' . $pr . '</priority></url>' . "\n";
}
echo '</urlset>' . "\n";
