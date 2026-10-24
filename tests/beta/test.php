<?php
/* 4.56.0 Beta ortamı (beta.optiflow.com.tr): ortam tespiti, canlı adres, kapı / cron / kayıt korumaları, yayın akışı. */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';
require dirname(__DIR__, 2) . '/app/ortam.php';

$kok = dirname(__DIR__, 2);
$oku = static fn(string $yol): string => (string) file_get_contents($kok . '/' . $yol);

echo "1) ortam_beta / ortam_canli_host\n";
$_SERVER['HTTP_HOST'] = 'optiflow.com.tr';
ok(!ortam_beta(), 'canlı alan adı beta değil');
esit('optiflow.com.tr', ortam_canli_host(), 'canlıda canlı host kendisi');
$_SERVER['HTTP_HOST'] = 'BETA.optiflow.com.tr';
ok(ortam_beta(), 'beta. ile başlayan alan adı beta (büyük harf de)');
esit('optiflow.com.tr', ortam_canli_host(), 'beta → canlı host');
$_SERVER['HTTP_HOST'] = 'beta.ornek.test:8080';
ok(ortam_beta(), 'portlu beta');
esit('optiflow.com.tr', ortam_canli_host(), 'geçersiz host (port) yerine varsayılan');
$_SERVER['HTTP_HOST'] = 'beta.evil.com/"><x';
esit('optiflow.com.tr', ortam_canli_host(), 'zararlı host yerine varsayılan');
$_SERVER['HTTP_HOST'] = 'betaoptik.com.tr';
ok(!ortam_beta(), '"beta" ile başlayan ama "beta." olmayan alan adı beta değil');
$_SERVER['HTTP_HOST'] = 'test.optiflow.com.tr';
ok(!ortam_beta(), 'test ortamı beta değil');

echo "2) Müşteri bağlantıları beta'da canlı adresle\n";
// domain.php düzenekte yüklenemiyor (çakışan taslaklar); app_base_url'in ilk işi beta kontrolü olmalı
$dom = $oku('app/domain.php');
$bas = strpos($dom, 'function app_base_url(): string');
$govde = substr($dom, $bas, 900);
$b = strpos($govde, "return 'https://' . ortam_canli_host();");
$y = strpos($govde, '$host = ');
ok($bas !== false && $b !== false && $y !== false && $b < $y, 'app_base_url beta\'da canlı adresi döndürür, site_base_url yazmadan');

echo "3) Kapı göçlerden önce, korumalar yerinde\n";
$boot = $oku('app/bootstrap.php');
$k = strpos($boot, 'beta_kapisi();');
$g = strpos($boot, 'run_migrations();');
ok($k !== false && $g !== false && $k < $g, 'bootstrap: beta_kapisi() run_migrations()\'dan önce');
ok(str_contains($boot, "require __DIR__ . '/ortam.php';"), 'bootstrap ortam.php\'yi yükler');
ok(str_contains($oku('app/ortam.php'), "ozellik_acik('beta')"), 'kapı "beta" özelliğine bakar');
ok(preg_match('/if \(ortam_beta\(\)\) \{[^}]*exit;/s', $oku('app/pages/cron.php')) === 1, 'cron beta\'da çalışmaz');
ok(str_contains($oku('app/pages/kayit.php'), "if (ortam_beta()) {"), 'kayıt beta\'da canlıya yönlenir');
ok(str_contains($oku('app/helpers.php'), "X-Robots-Tag: noindex, nofollow"), 'beta noindex başlığı');
ok(str_contains($oku('robots-beta.txt'), 'Disallow: /'), 'robots-beta.txt her şeyi kapatır');
ok(str_contains($oku('.htaccess'), 'RewriteRule ^robots\.txt$ robots-beta.txt'), '.htaccess beta\'da robots-beta.txt verir');
ok(str_contains($oku('app/layout.php'), 'beta-serit'), 'beta şeridi');
$ent = $oku('app/entegrasyon.php');
ok(strpos($ent, 'ortam_beta()') !== false && strpos($ent, 'ortam_beta()') < strpos($ent, '$yeni = random_bytes(32);'), 'beta şifreleme anahtarını kendisi üretmez');

echo "4) Özellik tanımı\n";
ok(preg_match("/'beta' => \[[^\]]*'ad'\s*=> 'Beta kanalı/", $oku('app/ozellik.php')) === 1, '"beta" özelliği tanımlı');

echo "5) Yayın akışı: beta önce, canlı gece ve koşullu\n";
$wf = $oku('.github/workflows/canliya-al.yml');
ok(str_contains($wf, "cron: '30 23 * * *'"), 'gece 02:30 (TR) çalışır');
ok(str_contains($wf, 'workflow_run) HEDEF=beta'), 'sürüm yayınlanınca beta\'ya');
ok(str_contains($wf, '[ "$BETA_ACIK" != "1" ]'), 'BETA_ACIK yokken eski düzen');
ok(str_contains($wf, '[ "$CANLI_DURDUR" = "1" ]'), 'CANLI_DURDUR ile gece durdurulur');
ok(str_contains($wf, '[ "$BETADA" != "$BEKLENEN" ]'), 'beta\'da denenmemiş sürüm canlıya geçmez');
ok(str_contains($wf, "steps.ayar.outputs.hedef == 'canli'"), 'masaüstü kurulumu yalnız canlıya');
ok(str_contains($oku('.github/workflows/surum-yayinla.yml'), 'tests/beta/test.php'), 'test sürüm akışında çalışır');

bitir();
