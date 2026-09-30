<?php
/* ==========================================================================
   OptiFlow — SEO veri köprüsü (4.8.0)
   --------------------------------------------------------------------------
   Günlük SEO görevinin okuduğu, SALT-OKUNUR bir JSON uç noktası:
     • Google Search Console: son 28 gün arama sorguları (sıra, tıklama,
       gösterim, TO), sayfalar ve günlük seri.
     • PageSpeed Insights (mobil): performans/SEO puanı, LCP, CLS, TBT.

   Güvenlik:
     • config.php'deki 'seo_token' olmadan hiçbir şey döndürmez (403).
     • Google hizmet hesabı anahtarı web'e KAPALI storage/ klasöründe durur
       (storage/.htaccess "Require all denied"); bu dosya anahtarı asla yazdırmaz.
     • Hasta/mağaza verisine dokunmaz; veritabanına bağlanmaz.
     • Sonuç 3 saat önbelleklenir (Google kotası + kötüye kullanım).

   Kurulum (config.php'ye):
     'seo_token'    => 'uzun-rastgele-bir-dize',          // zorunlu
     'gsc_key_file' => __DIR__ . '/storage/gsc-anahtar.json', // hizmet hesabı JSON
     'gsc_site'     => 'sc-domain:optiflow.com.tr',       // Search Console mülkü
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

$root = __DIR__;
$cfg = is_file($root . '/config.php') ? (require $root . '/config.php') : [];
$cfg = is_array($cfg) ? $cfg : [];
date_default_timezone_set((string) ($cfg['timezone'] ?? 'Europe/Istanbul'));

function cik(int $kod, array $veri): never
{
    http_response_code($kod);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/* ---------- Yetki ---------- */
$token = (string) ($cfg['seo_token'] ?? '');
$gelen = (string) ($_GET['t'] ?? '');
if (strlen($token) < 24) {
    cik(503, ['hata' => "config.php içinde 'seo_token' (en az 24 karakter) tanımlı değil."]);
}
if (!hash_equals($token, $gelen)) {
    usleep(700000);                                   // tahmin denemelerini yavaşlat
    cik(403, ['hata' => 'yetkisiz']);
}

$site = (string) ($cfg['gsc_site'] ?? 'sc-domain:optiflow.com.tr');
$keyFile = (string) ($cfg['gsc_key_file'] ?? ($root . '/storage/gsc-anahtar.json'));
$pageUrl = (string) ($cfg['seo_site_url'] ?? 'https://optiflow.com.tr/');
@set_time_limit(90);

/* ---------- Önbellek ---------- */
$cacheDir = $root . '/storage/seo';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0750, true);
}
$cacheFile = $cacheDir . '/son.json';
$taze = isset($_GET['yenile']) && $_GET['yenile'] === '1';
if (!$taze && is_file($cacheFile) && filemtime($cacheFile) > time() - 3 * 3600) {
    $c = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($c)) {
        $c['onbellek'] = true;
        cik(200, $c);
    }
}

/* ---------- HTTP ---------- */
function istek(string $url, string $yontem = 'GET', ?string $govde = null, array $basliklar = [], int $sure = 45): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $sure, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_CUSTOMREQUEST => $yontem, CURLOPT_HTTPHEADER => $basliklar,
        ]);
        if ($govde !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $govde);
        }
        $cevap = curl_exec($ch);
        $kod = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$kod, $cevap === false ? '' : (string) $cevap, $err];
    }
    $ctx = stream_context_create(['http' => [
        'method' => $yontem, 'header' => implode("\r\n", $basliklar), 'content' => $govde ?? '',
        'timeout' => $sure, 'ignore_errors' => true,
    ]]);
    $cevap = @file_get_contents($url, false, $ctx);
    $kod = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $kod = (int) $m[1];
        }
    }
    return [$kod, $cevap === false ? '' : (string) $cevap, $cevap === false ? 'bağlantı hatası' : ''];
}

function b64u(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/* ---------- Google hizmet hesabı → erişim anahtarı (JWT, RS256) ---------- */
function google_token(string $keyFile): string
{
    if (!is_file($keyFile)) {
        throw new RuntimeException('Hizmet hesabı anahtar dosyası yok: storage/gsc-anahtar.json yükleyin.');
    }
    $k = json_decode((string) file_get_contents($keyFile), true);
    if (!is_array($k) || empty($k['client_email']) || empty($k['private_key'])) {
        throw new RuntimeException('Anahtar dosyası geçersiz (client_email / private_key yok).');
    }
    if (!function_exists('openssl_sign')) {
        throw new RuntimeException('PHP openssl eklentisi kapalı (Plesk › PHP ayarları).');
    }
    $simdi = time();
    $bas = b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $yuk = b64u(json_encode([
        'iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $simdi, 'exp' => $simdi + 3600,
    ]));
    $imza = '';
    if (!openssl_sign($bas . '.' . $yuk, $imza, $k['private_key'], 'sha256WithRSAEncryption')) {
        throw new RuntimeException('Anahtar imzalanamadı (private_key bozuk olabilir).');
    }
    [$kod, $gov, $err] = istek('https://oauth2.googleapis.com/token', 'POST', http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $bas . '.' . $yuk . '.' . b64u($imza),
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    $j = json_decode($gov, true);
    if ($kod !== 200 || empty($j['access_token'])) {
        throw new RuntimeException('Google yetkilendirme hatası (' . $kod . '): ' . ($j['error_description'] ?? $j['error'] ?? ($err ?: 'bilinmiyor')));
    }
    return (string) $j['access_token'];
}

function gsc_sorgu(string $tok, string $site, array $govde): array
{
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query';
    [$kod, $gov] = istek($url, 'POST', json_encode($govde), ['Authorization: Bearer ' . $tok, 'Content-Type: application/json']);
    $j = json_decode($gov, true);
    if ($kod !== 200) {
        $m = $j['error']['message'] ?? ('HTTP ' . $kod);
        if ($kod === 403 && stripos($m, 'has not been used') === false && stripos($m, 'disabled') === false) {
            $m .= ' — Search Console › Ayarlar › Kullanıcılar ve izinler bölümüne hizmet hesabı e-postasını ekleyin.';
        }
        throw new RuntimeException('Search Console: ' . $m);
    }
    return $j['rows'] ?? [];
}

$sonuc = [
    'uretildi' => date('c'),
    'site' => $site,
    'sayfa' => $pageUrl,
    'onbellek' => false,
    'gsc' => null,
    'psi' => null,
    'hatalar' => [],
];

/* ---------- Search Console ---------- */
try {
    $tok = google_token($keyFile);
    // GSC verisi ~2-3 gün gecikmeli gelir
    $bitis = date('Y-m-d', strtotime('-2 days'));
    $baslangic = date('Y-m-d', strtotime('-29 days'));
    $onceBas = date('Y-m-d', strtotime('-57 days'));
    $onceBit = date('Y-m-d', strtotime('-30 days'));
    $yuvarla = static fn(array $r): array => [
        'tiklama' => (int) $r['clicks'], 'gosterim' => (int) $r['impressions'],
        'to' => round((float) $r['ctr'] * 100, 2), 'sira' => round((float) $r['position'], 1),
    ];

    $sorgular = array_map(static fn($r) => ['sorgu' => $r['keys'][0]] + $yuvarla($r),
        gsc_sorgu($tok, $site, ['startDate' => $baslangic, 'endDate' => $bitis, 'dimensions' => ['query'], 'rowLimit' => 250]));
    $onceki = [];
    foreach (gsc_sorgu($tok, $site, ['startDate' => $onceBas, 'endDate' => $onceBit, 'dimensions' => ['query'], 'rowLimit' => 250]) as $r) {
        $onceki[$r['keys'][0]] = round((float) $r['position'], 1);
    }
    foreach ($sorgular as &$s) {
        $s['onceki_sira'] = $onceki[$s['sorgu']] ?? null;
    }
    unset($s);

    $sayfalar = array_map(static fn($r) => ['sayfa' => $r['keys'][0]] + $yuvarla($r),
        gsc_sorgu($tok, $site, ['startDate' => $baslangic, 'endDate' => $bitis, 'dimensions' => ['page'], 'rowLimit' => 100]));
    $gunluk = array_map(static fn($r) => ['tarih' => $r['keys'][0]] + $yuvarla($r),
        gsc_sorgu($tok, $site, ['startDate' => $baslangic, 'endDate' => $bitis, 'dimensions' => ['date'], 'rowLimit' => 60]));
    $cihaz = array_map(static fn($r) => ['cihaz' => $r['keys'][0]] + $yuvarla($r),
        gsc_sorgu($tok, $site, ['startDate' => $baslangic, 'endDate' => $bitis, 'dimensions' => ['device']]));

    $top = ['tiklama' => 0, 'gosterim' => 0];
    foreach ($gunluk as $g) {
        $top['tiklama'] += $g['tiklama'];
        $top['gosterim'] += $g['gosterim'];
    }
    $top['to'] = $top['gosterim'] ? round($top['tiklama'] / $top['gosterim'] * 100, 2) : 0;

    $sonuc['gsc'] = [
        'aralik' => [$baslangic, $bitis], 'toplam' => $top,
        'sorgular' => $sorgular, 'sayfalar' => $sayfalar, 'gunluk' => $gunluk, 'cihaz' => $cihaz,
    ];
} catch (Throwable $e) {
    $sonuc['hatalar'][] = $e->getMessage();
}

/* ---------- PageSpeed Insights (mobil) ---------- */
if (($_GET['psi'] ?? '1') !== '0') {
    $psiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=mobile&locale=tr'
        . '&category=performance&category=seo&category=accessibility&category=best-practices&url=' . rawurlencode($pageUrl)
        . (!empty($cfg['psi_api_key']) ? '&key=' . rawurlencode((string) $cfg['psi_api_key']) : '');
    [$kod, $gov] = istek($psiUrl, 'GET', null, [], 75);
    $j = json_decode($gov, true);
    if ($kod === 200 && isset($j['lighthouseResult'])) {
        $lh = $j['lighthouseResult'];
        $puan = static fn(string $c) => isset($lh['categories'][$c]['score']) ? (int) round($lh['categories'][$c]['score'] * 100) : null;
        $olc = static fn(string $a) => $lh['audits'][$a]['displayValue'] ?? null;
        $basarisiz = [];
        foreach ($lh['audits'] ?? [] as $id => $a) {
            if (isset($a['score']) && $a['score'] !== null && $a['score'] < 0.9 && ($a['scoreDisplayMode'] ?? '') !== 'informative' && ($a['scoreDisplayMode'] ?? '') !== 'notApplicable') {
                $basarisiz[] = ['id' => $id, 'baslik' => $a['title'] ?? $id, 'deger' => $a['displayValue'] ?? null];
            }
        }
        $sonuc['psi'] = [
            'performans' => $puan('performance'), 'seo' => $puan('seo'),
            'erisilebilirlik' => $puan('accessibility'), 'en_iyi_uygulama' => $puan('best-practices'),
            'lcp' => $olc('largest-contentful-paint'), 'cls' => $olc('cumulative-layout-shift'),
            'tbt' => $olc('total-blocking-time'), 'fcp' => $olc('first-contentful-paint'),
            'sorunlar' => array_slice($basarisiz, 0, 25),
        ];
    } else {
        $m = $j['error']['message'] ?? ('HTTP ' . $kod);
        if (stripos($m, 'Quota exceeded') !== false && empty($cfg['psi_api_key'])) {
            $m .= " — config.php'ye 'psi_api_key' ekleyin (Google Cloud › Kimlik bilgileri › API anahtarı).";
        }
        $sonuc['hatalar'][] = 'PageSpeed: ' . $m;
    }
}

if (!$sonuc['hatalar']) {
    @file_put_contents($cacheFile, json_encode($sonuc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}
cik(200, $sonuc);
