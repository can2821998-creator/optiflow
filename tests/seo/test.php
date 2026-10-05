<?php
/* SEO bağlantısı (app/seo.php): ayarlar, anahtar yükleme, doğrulama kodu, Google verisi (sahte HTTP),
   seo-veri.php uç noktası ve tanıtım sayfası doğrulama etiketi. Ağ kullanmaz. */
declare(strict_types=1);

$kok = sys_get_temp_dir() . '/optiflow-seo-test-' . getmypid();
@mkdir($kok . '/storage', 0777, true);
putenv('UTS_TEST_ROOT=' . $kok);
date_default_timezone_set('Europe/Istanbul');
require dirname(__DIR__, 2) . '/app/seo.php';

$gecen = 0;
$kalan = 0;
function dogru(string $ad, bool $kosul, string $ek = ''): void
{
    global $gecen, $kalan;
    if ($kosul) {
        $gecen++;
    } else {
        $kalan++;
        echo "  ✗ $ad" . ($ek !== '' ? " — $ek" : '') . "\n";
    }
}
function hata_verir(string $ad, callable $f, string $parca): void
{
    try {
        $f();
        dogru($ad, false, 'hata beklenirdi');
    } catch (RuntimeException $e) {
        dogru($ad, str_contains($e->getMessage(), $parca), $e->getMessage());
    }
}

echo "1) Varsayılanlar ve ayar kaydı\n";
$a = seo_ayar();
dogru('varsayılan mülk', $a['gsc_site'] === 'sc-domain:optiflow.com.tr');
dogru('token yok', $a['token'] === '');
$a = seo_ayar_kaydet(['gsc_site' => 'https://optiflow.com.tr', 'google_dogrulama' => '<meta name="google-site-verification" content="AbCdEf_123-xyz987" />']);
dogru('URL ön eki / ile biter', $a['gsc_site'] === 'https://optiflow.com.tr/');
dogru('etiketten kod çıkarılır', $a['google_dogrulama'] === 'AbCdEf_123-xyz987');
dogru('ilk kayıtta token üretilir', strlen($a['token']) === 48);
$t1 = $a['token'];
$a = seo_ayar_kaydet(['gsc_site' => 'sc-domain:OptiFlow.com.tr']);
dogru('token korunur', $a['token'] === $t1);
dogru('alan adı mülkü küçük harf', $a['gsc_site'] === 'sc-domain:optiflow.com.tr');
dogru('doğrulama kodu korunur', $a['google_dogrulama'] === 'AbCdEf_123-xyz987');
$a = seo_ayar_kaydet(['token_uret' => true]);
dogru('token yenilenir', $a['token'] !== $t1 && strlen($a['token']) === 48);
hata_verir('geçersiz mülk', fn() => seo_ayar_kaydet(['gsc_site' => 'optiflow']), 'Mülk');
hata_verir('geçersiz doğrulama', fn() => seo_ayar_kaydet(['google_dogrulama' => '"><script>']), 'doğrulama kodu');
hata_verir('geçersiz PSI anahtarı', fn() => seo_ayar_kaydet(['psi_api_key' => 'kısa']), 'PageSpeed');
$a = seo_ayar_kaydet(['psi_api_key' => 'AIzaSyD-test_anahtar_1234567890']);
dogru('PSI anahtarı kaydedilir', $a['psi_api_key'] === 'AIzaSyD-test_anahtar_1234567890');
$a = seo_ayar_kaydet(['psi_api_key' => '']);
dogru('boş PSI alanı anahtarı silmez', $a['psi_api_key'] !== '');
$a = seo_ayar_kaydet(['psi_api_key_sil' => true]);
dogru('PSI anahtarı kaldırılır', $a['psi_api_key'] === '');
dogru('ayar dosyası 0600', (fileperms($kok . '/storage/seo/ayar.json') & 0777) === 0600);

echo "2) Hizmet hesabı anahtarı\n";
dogru('anahtar yok', seo_anahtar_bilgi() === null);
hata_verir('JSON değil', fn() => seo_anahtar_kaydet('merhaba'), 'hizmet hesabı');
hata_verir('yanlış tür', fn() => seo_anahtar_kaydet('{"type":"authorized_user","client_email":"a@b.co","private_key":"x"}'), 'hizmet hesabı');
hata_verir('bozuk özel anahtar', fn() => seo_anahtar_kaydet('{"type":"service_account","client_email":"seo@p.iam.gserviceaccount.com","private_key":"bozuk"}'), 'private_key');
$pk = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
openssl_pkey_export($pk, $pem);
$json = json_encode(['type' => 'service_account', 'project_id' => 'optiflow-seo', 'private_key_id' => 'abc', 'private_key' => $pem,
    'client_email' => 'seo-okuyucu@optiflow-seo.iam.gserviceaccount.com', 'client_id' => '1', 'auth_uri' => 'x', 'universe_domain' => 'googleapis.com']);
dogru('anahtar kaydedilir', seo_anahtar_kaydet($json) === 'seo-okuyucu@optiflow-seo.iam.gserviceaccount.com');
$b = seo_anahtar_bilgi();
dogru('bilgi: e-posta + proje', $b['e_posta'] === 'seo-okuyucu@optiflow-seo.iam.gserviceaccount.com' && $b['proje'] === 'optiflow-seo');
dogru('bilgi gizli alan içermez', !isset($b['private_key']));
$kayitli = json_decode((string) file_get_contents($kok . '/storage/gsc-anahtar.json'), true);
dogru('gereksiz alanlar atılır', !isset($kayitli['auth_uri']) && !isset($kayitli['universe_domain']) && isset($kayitli['private_key']));
dogru('anahtar dosyası 0600', (fileperms($kok . '/storage/gsc-anahtar.json') & 0777) === 0600);

echo "3) Google verisi (sahte HTTP)\n";
$istekler = [];
$GLOBALS['seo_http_sahte'] = function (string $url, string $yontem, ?string $govde, array $bas) use (&$istekler, $pem): array {
    $istekler[] = $url;
    if (str_starts_with($url, 'https://oauth2.googleapis.com/token')) {
        parse_str((string) $govde, $q);
        [$h, $y, $imza] = explode('.', $q['assertion']);
        $pub = openssl_pkey_get_details(openssl_pkey_get_private($pem))['key'];
        $ok = openssl_verify("$h.$y", base64_decode(strtr($imza, '-_', '+/')), $pub, 'sha256WithRSAEncryption') === 1;
        $yuk = json_decode(base64_decode(strtr($y, '-_', '+/')), true);
        return $ok && $yuk['scope'] === 'https://www.googleapis.com/auth/webmasters.readonly'
            ? [200, '{"access_token":"ya29.sahte","expires_in":3600}', ''] : [400, '{"error":"invalid_grant"}', ''];
    }
    if ($url === 'https://www.googleapis.com/webmasters/v3/sites') {
        return [200, '{"siteEntry":[{"siteUrl":"sc-domain:optiflow.com.tr","permissionLevel":"siteRestrictedUser"}]}', ''];
    }
    if (str_contains($url, '/searchAnalytics/query')) {
        dogru('yetki başlığı', in_array('Authorization: Bearer ya29.sahte', $bas, true));
        $g = json_decode((string) $govde, true);
        $boyut = $g['dimensions'][0];
        $satir = match ($boyut) {
            'query' => [['keys' => ['gözlükçü programı'], 'clicks' => 4, 'impressions' => 120, 'ctr' => 0.0333, 'position' => str_contains($g['startDate'], date('Y-m', strtotime('-57 days'))) && $g['endDate'] === date('Y-m-d', strtotime('-30 days')) ? 14.2 : 8.4]],
            'page' => [['keys' => ['https://optiflow.com.tr/'], 'clicks' => 4, 'impressions' => 120, 'ctr' => 0.0333, 'position' => 8.4]],
            'date' => [['keys' => ['2026-10-01'], 'clicks' => 3, 'impressions' => 70, 'ctr' => 0.04, 'position' => 9], ['keys' => ['2026-10-02'], 'clicks' => 1, 'impressions' => 50, 'ctr' => 0.02, 'position' => 8]],
            default => [['keys' => ['MOBILE'], 'clicks' => 3, 'impressions' => 90, 'ctr' => 0.03, 'position' => 9]],
        };
        return [200, (string) json_encode(['rows' => $satir]), ''];
    }
    if (str_contains($url, 'pagespeedonline')) {
        return [200, (string) json_encode(['lighthouseResult' => [
            'categories' => ['performance' => ['score' => 0.87], 'seo' => ['score' => 1], 'accessibility' => ['score' => 0.95], 'best-practices' => ['score' => 0.96]],
            'audits' => ['largest-contentful-paint' => ['displayValue' => '2,9 sn', 'score' => 0.7, 'title' => 'LCP', 'scoreDisplayMode' => 'numeric'],
                'cumulative-layout-shift' => ['displayValue' => '0', 'score' => 1, 'title' => 'CLS', 'scoreDisplayMode' => 'numeric']],
        ]]), ''];
    }
    return [404, '', ''];
};
$s = seo_veri_topla(true);
dogru('hata yok', $s['hatalar'] === [], implode(' | ', $s['hatalar']));
dogru('mülk listesi', ($s['mulkler'][0]['site'] ?? '') === 'sc-domain:optiflow.com.tr');
dogru('toplam tıklama', $s['gsc']['toplam']['tiklama'] === 4 && $s['gsc']['toplam']['gosterim'] === 120);
dogru('sorgu + önceki sıra', $s['gsc']['sorgular'][0]['sorgu'] === 'gözlükçü programı' && $s['gsc']['sorgular'][0]['sira'] === 8.4 && $s['gsc']['sorgular'][0]['onceki_sira'] === 14.2);
dogru('mülk adresi kodlanır', (bool) array_filter($istekler, fn($u) => str_contains($u, '/sites/sc-domain%3Aoptiflow.com.tr/searchAnalytics')));
dogru('PSI puanları', $s['psi']['performans'] === 87 && $s['psi']['seo'] === 100);
dogru('PSI sorunları', count($s['psi']['sorunlar']) === 1 && $s['psi']['sorunlar'][0]['id'] === 'largest-contentful-paint');
dogru('önbelleğe yazıldı', seo_onbellek_oku() !== null);

$GLOBALS['seo_http_sahte'] = fn(string $url) => str_starts_with($url, 'https://oauth2')
    ? [200, '{"access_token":"t"}', '']
    : (str_contains($url, 'searchAnalytics') ? [403, '{"error":{"message":"User does not have sufficient permission for site"}}', ''] : [429, '{"error":{"message":"Quota exceeded for quota metric"}}', '']);
seo_onbellek_sil();
$s = seo_veri_topla(true);
dogru('403 ipucu: kullanıcı ekleyin', str_contains($s['hatalar'][0] ?? '', 'Kullanıcılar ve izinler'), $s['hatalar'][0] ?? '');
dogru('kota ipucu: PSI anahtarı', str_contains($s['hatalar'][1] ?? '', 'PageSpeed API anahtarı'), $s['hatalar'][1] ?? '');
dogru('hatalıda önbellek yazılmaz', seo_onbellek_oku() === null);
$GLOBALS['seo_http_sahte'] = fn(string $url) => str_starts_with($url, 'https://oauth2')
    ? [200, '{"access_token":"t"}', ''] : [403, '{"error":{"message":"Google Search Console API has not been used in project 1 before or it is disabled."}}', ''];
$s = seo_veri_topla(false);
dogru('API kapalı ipucu', str_contains($s['hatalar'][0] ?? '', 'Etkinleştir') && !str_contains($s['hatalar'][0] ?? '', 'Kullanıcılar ve izinler'), $s['hatalar'][0] ?? '');
seo_anahtar_sil();
$s = seo_veri_topla(false);
dogru('anahtarsız: yönlendiren hata', str_contains($s['hatalar'][0] ?? '', 'SEO · Google'));
unset($GLOBALS['seo_http_sahte']);

echo "4) seo-veri.php uç noktası\n";
$uc = function (string $sorgu) use ($kok): array {
    $kod = 'putenv("UTS_TEST_ROOT=' . $kok . '"); parse_str(' . var_export($sorgu, true) . ', $_GET); '
        . 'register_shutdown_function(function(){ fwrite(STDERR, "KOD:" . http_response_code()); }); '
        . 'require ' . var_export(dirname(__DIR__, 2) . '/seo-veri.php', true) . ';';
    $p = proc_open([PHP_BINARY, '-d', 'display_errors=stderr', '-r', $kod], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $borular);
    $out = stream_get_contents($borular[1]);
    $err = stream_get_contents($borular[2]);
    proc_close($p);
    preg_match('/KOD:(\d+)/', $err, $m);
    return [(int) ($m[1] ?? 0), json_decode($out, true), $err];
};
[$k, $j] = $uc('t=yanlis');
dogru('yanlış token → 403', $k === 403 && ($j['hata'] ?? '') === 'yetkisiz', "kod $k");
seo_onbellek_yaz(['uretildi' => 'x', 'gsc' => ['toplam' => ['tiklama' => 9]], 'psi' => null, 'hatalar' => []]);
[$k, $j, $err] = $uc('t=' . seo_ayar()['token']);
dogru('doğru token → önbellek', $k === 200 && ($j['onbellek'] ?? false) === true && ($j['gsc']['toplam']['tiklama'] ?? 0) === 9, "kod $k " . substr($err, 0, 200));
@unlink($kok . '/storage/seo/ayar.json');
[$k, $j] = $uc('t=x');
dogru('kurulmamış → 503', $k === 503 && str_contains($j['hata'] ?? '', 'Merkez panel'), "kod $k");

echo "5) Tanıtım sayfası doğrulama etiketi\n";
seo_ayar_kaydet(['google_dogrulama' => 'PanelKodu_1234567890', 'bing_dogrulama' => 'ABCDEF0123456789ABCDEF0123456789']);
require_once dirname(__DIR__, 2) . '/app/pazarlama.php';
$m = pz_dogrulama_meta();
dogru('google meta', str_contains($m, '<meta name="google-site-verification" content="PanelKodu_1234567890">'), $m);
dogru('bing meta', str_contains($m, '<meta name="msvalidate.01" content="ABCDEF0123456789ABCDEF0123456789">'), $m);

exec('rm -rf ' . escapeshellarg($kok));
echo "SEO testleri: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
