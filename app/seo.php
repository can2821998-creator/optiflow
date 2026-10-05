<?php
/* ==========================================================================
   OptiFlow — SEO bağlantısı (4.16.4)
   --------------------------------------------------------------------------
   Google Search Console + PageSpeed Insights verisi ve site doğrulama kodları.
   Ayarlar Plesk'te config.php düzenlemeden, MERKEZ PANEL › "SEO · Google"
   ekranından girilir ve web'e kapalı storage/ klasöründe durur:
     storage/seo/ayar.json      → token, mülk, PSI anahtarı, doğrulama kodları
     storage/gsc-anahtar.json   → Google hizmet hesabı anahtarı (JSON)
     storage/seo/son.json       → son başarılı veri (3 saat önbellek)
   config.php'deki eski anahtarlar (seo_token, gsc_site, gsc_key_file,
   psi_api_key, seo_site_url) hâlâ geçerlidir; panelde girilen değer önce gelir.

   Bu dosya bootstrap'e/veritabanına bağlı DEĞİLDİR (seo-veri.php ve tanıtım
   sayfaları da kullanır). Hasta/mağaza verisine dokunmaz.
   ========================================================================== */
declare(strict_types=1);

if (!defined('SEO_ONBELLEK_SN')) {
    define('SEO_ONBELLEK_SN', 3 * 3600);
}

function seo_kok(): string
{
    $test = getenv('UTS_TEST_ROOT');
    return is_string($test) && $test !== '' ? $test : dirname(__DIR__);
}

function seo_dizin(): string
{
    return seo_kok() . '/storage/seo';
}

/** config.php (varsa) — bootstrap yüklüyse config() kullanılır. */
function seo_config(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    $c = [];
    foreach (['seo_token', 'gsc_site', 'gsc_key_file', 'psi_api_key', 'seo_site_url'] as $k) {
        $v = function_exists('config') ? config($k) : null;
        if ($v !== null && $v !== '') {
            $c[$k] = $v;
        }
    }
    if (!$c && !function_exists('config') && is_file(seo_kok() . '/config.php')) {
        $d = require seo_kok() . '/config.php';
        $c = is_array($d) ? $d : [];
    }
    return $c;
}

/** Geçerli ayarlar: panel (storage) > config.php > varsayılan. */
function seo_ayar(): array
{
    $cfg = seo_config();
    $dosya = seo_dizin() . '/ayar.json';
    $p = is_file($dosya) ? json_decode((string) file_get_contents($dosya), true) : [];
    $p = is_array($p) ? $p : [];
    $al = static fn(string $k, string $ck, string $vars = ''): string
        => trim((string) (($p[$k] ?? '') !== '' ? $p[$k] : ($cfg[$ck] ?? $vars)));
    return [
        'token'            => $al('token', 'seo_token'),
        'gsc_site'         => $al('gsc_site', 'gsc_site', 'sc-domain:optiflow.com.tr'),
        'psi_api_key'      => $al('psi_api_key', 'psi_api_key'),
        'site_url'         => $al('site_url', 'seo_site_url', 'https://optiflow.com.tr/'),
        'google_dogrulama' => trim((string) ($p['google_dogrulama'] ?? '')),
        'bing_dogrulama'   => trim((string) ($p['bing_dogrulama'] ?? '')),
        'anahtar_dosyasi'  => (string) ($cfg['gsc_key_file'] ?? (seo_kok() . '/storage/gsc-anahtar.json')),
    ];
}

function seo_dizin_hazirla(): void
{
    $d = seo_dizin();
    if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) {
        throw new RuntimeException('storage/seo klasörü oluşturulamadı (yazma izni).');
    }
}

/** Panelden gelen ayarları doğrular ve kaydeder. Boş bırakılan gizli alanlar (PSI anahtarı) korunur. */
function seo_ayar_kaydet(array $yeni): array
{
    $dosya = seo_dizin() . '/ayar.json';
    $eski = is_file($dosya) ? json_decode((string) file_get_contents($dosya), true) : [];
    $eski = is_array($eski) ? $eski : [];
    $a = $eski;

    if (array_key_exists('gsc_site', $yeni)) {
        $site = seo_mulk_normalle((string) $yeni['gsc_site']);
        if ($site === null) {
            throw new RuntimeException('Mülk "sc-domain:alanadi.com" ya da "https://alanadi.com/" biçiminde olmalı.');
        }
        $a['gsc_site'] = $site;
    }
    if (array_key_exists('site_url', $yeni) && trim((string) $yeni['site_url']) !== '') {
        $u = trim((string) $yeni['site_url']);
        if (!preg_match('#^https://[a-z0-9.\-]+(:\d+)?/.*$#i', $u)) {
            throw new RuntimeException('Hız ölçümü adresi https:// ile başlayıp / ile bitmeli.');
        }
        $a['site_url'] = $u;
    }
    if (isset($yeni['psi_api_key']) && trim((string) $yeni['psi_api_key']) !== '') {
        $k = trim((string) $yeni['psi_api_key']);
        if (!preg_match('/^[A-Za-z0-9_\-]{20,80}$/', $k)) {
            throw new RuntimeException('PageSpeed API anahtarı geçersiz görünüyor.');
        }
        $a['psi_api_key'] = $k;
    }
    if (!empty($yeni['psi_api_key_sil'])) {
        unset($a['psi_api_key']);
    }
    foreach (['google_dogrulama', 'bing_dogrulama'] as $k) {
        if (array_key_exists($k, $yeni)) {
            $kod = seo_dogrulama_kodu((string) $yeni[$k]);
            if ($kod === null) {
                throw new RuntimeException(($k === 'google_dogrulama' ? 'Google' : 'Bing') . ' doğrulama kodu geçersiz. Etiketin tamamını ya da yalnızca content="…" içindeki kodu yapıştırın.');
            }
            $a[$k] = $kod;
        }
    }
    if (!empty($yeni['token_uret']) || empty($a['token'])) {
        $a['token'] = bin2hex(random_bytes(24));
    }
    seo_dizin_hazirla();
    if (file_put_contents($dosya, json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) {
        throw new RuntimeException('SEO ayarları kaydedilemedi (storage yazma izni).');
    }
    @chmod($dosya, 0600);
    seo_onbellek_sil();
    return seo_ayar();
}

/** "sc-domain:x.com" ya da "https://x.com/" — başka her şey null. */
function seo_mulk_normalle(string $s): ?string
{
    $s = trim($s);
    if (preg_match('/^sc-domain:([a-z0-9.\-]+\.[a-z]{2,})$/i', $s, $m)) {
        return 'sc-domain:' . strtolower($m[1]);
    }
    if (preg_match('#^https?://[a-z0-9.\-]+\.[a-z]{2,}(:\d+)?(/[^\s]*)?$#i', $s)) {
        return str_ends_with($s, '/') ? $s : $s . '/';
    }
    return null;
}

/** Tam <meta …> etiketi ya da yalnızca kod kabul edilir; boş → '' (kaldır), geçersiz → null. */
function seo_dogrulama_kodu(string $girdi): ?string
{
    $g = trim($girdi);
    if ($g === '') {
        return '';
    }
    if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $g, $m)) {
        $g = trim($m[1]);
    }
    return preg_match('/^[A-Za-z0-9_\-]{10,100}$/', $g) ? $g : null;
}

/* ---------- Hizmet hesabı anahtarı ---------- */

/** Yüklenen JSON'u doğrular ve storage'a yazar. Dönen: client_email. */
function seo_anahtar_kaydet(string $json): string
{
    $k = json_decode($json, true);
    if (!is_array($k) || ($k['type'] ?? '') !== 'service_account' || empty($k['client_email']) || empty($k['private_key'])) {
        throw new RuntimeException('Bu dosya bir Google hizmet hesabı anahtarı değil. Google Cloud › Hizmet hesapları › Anahtarlar › "JSON" ile indirilen dosyayı seçin.');
    }
    if (!filter_var($k['client_email'], FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Anahtardaki client_email geçersiz.');
    }
    if (function_exists('openssl_pkey_get_private') && @openssl_pkey_get_private((string) $k['private_key']) === false) {
        throw new RuntimeException('Anahtardaki private_key okunamadı (dosya bozuk olabilir).');
    }
    $hedef = seo_ayar()['anahtar_dosyasi'];
    $dir = dirname($hedef);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('storage klasörüne yazılamıyor.');
    }
    // Yalnızca gerekli alanlar saklanır.
    $sakla = array_intersect_key($k, array_flip(['type', 'project_id', 'private_key_id', 'private_key', 'client_email', 'client_id', 'token_uri']));
    if (file_put_contents($hedef, json_encode($sakla, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) {
        throw new RuntimeException('Anahtar kaydedilemedi (storage yazma izni).');
    }
    @chmod($hedef, 0600);
    seo_onbellek_sil();
    return (string) $k['client_email'];
}

/** Anahtar yüklüyse {e_posta, proje}; değilse null. Gizli alanlar asla dönmez. */
function seo_anahtar_bilgi(): ?array
{
    $f = seo_ayar()['anahtar_dosyasi'];
    if (!is_file($f)) {
        return null;
    }
    $k = json_decode((string) file_get_contents($f), true);
    if (!is_array($k) || empty($k['client_email'])) {
        return null;
    }
    return ['e_posta' => (string) $k['client_email'], 'proje' => (string) ($k['project_id'] ?? ''), 'tarih' => date('Y-m-d H:i', (int) filemtime($f))];
}

function seo_anahtar_sil(): void
{
    $f = seo_ayar()['anahtar_dosyasi'];
    if (is_file($f)) {
        @unlink($f);
    }
    seo_onbellek_sil();
}

/* ---------- Önbellek ---------- */

function seo_onbellek_oku(bool $sureyeBakma = false): ?array
{
    $f = seo_dizin() . '/son.json';
    if (!is_file($f) || (!$sureyeBakma && filemtime($f) <= time() - SEO_ONBELLEK_SN)) {
        return null;
    }
    $c = json_decode((string) file_get_contents($f), true);
    return is_array($c) ? $c : null;
}

function seo_onbellek_yaz(array $s): void
{
    try {
        seo_dizin_hazirla();
        @file_put_contents(seo_dizin() . '/son.json', json_encode($s, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
    } catch (Throwable $e) {
        // önbellek yazılamazsa veri yine döner
    }
}

function seo_onbellek_sil(): void
{
    @unlink(seo_dizin() . '/son.json');
}

/* ---------- HTTP ---------- */

/**
 * @return array{0:int,1:string,2:string} [kod, gövde, hata]
 * Testlerde $GLOBALS['seo_http_sahte'] (callable) ile değiştirilebilir.
 */
function seo_istek(string $url, string $yontem = 'GET', ?string $govde = null, array $basliklar = [], int $sure = 45): array
{
    if (isset($GLOBALS['seo_http_sahte']) && is_callable($GLOBALS['seo_http_sahte'])) {
        return ($GLOBALS['seo_http_sahte'])($url, $yontem, $govde, $basliklar);
    }
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

function seo_b64u(string $s): string
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

/** Hizmet hesabı → erişim anahtarı (JWT, RS256). */
function seo_google_token(string $keyFile): string
{
    if (!is_file($keyFile)) {
        throw new RuntimeException('Google hizmet hesabı anahtarı yüklenmemiş (Merkez panel › SEO · Google).');
    }
    $k = json_decode((string) file_get_contents($keyFile), true);
    if (!is_array($k) || empty($k['client_email']) || empty($k['private_key'])) {
        throw new RuntimeException('Anahtar dosyası geçersiz (client_email / private_key yok).');
    }
    if (!function_exists('openssl_sign')) {
        throw new RuntimeException('PHP openssl eklentisi kapalı (Plesk › PHP ayarları).');
    }
    $simdi = time();
    $bas = seo_b64u((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $yuk = seo_b64u((string) json_encode([
        'iss' => $k['client_email'], 'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
        'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $simdi, 'exp' => $simdi + 3600,
    ]));
    $imza = '';
    if (!openssl_sign($bas . '.' . $yuk, $imza, (string) $k['private_key'], 'sha256WithRSAEncryption')) {
        throw new RuntimeException('Anahtar imzalanamadı (private_key bozuk olabilir).');
    }
    [$kod, $gov, $err] = seo_istek('https://oauth2.googleapis.com/token', 'POST', http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $bas . '.' . $yuk . '.' . seo_b64u($imza),
    ]), ['Content-Type: application/x-www-form-urlencoded']);
    $j = json_decode($gov, true);
    if ($kod !== 200 || empty($j['access_token'])) {
        throw new RuntimeException('Google yetkilendirme hatası (' . $kod . '): ' . ($j['error_description'] ?? $j['error'] ?? ($err ?: 'bilinmiyor')));
    }
    return (string) $j['access_token'];
}

/** Hizmet hesabının erişebildiği Search Console mülkleri. */
function seo_gsc_mulkler(string $tok): array
{
    [$kod, $gov] = seo_istek('https://www.googleapis.com/webmasters/v3/sites', 'GET', null, ['Authorization: Bearer ' . $tok]);
    $j = json_decode($gov, true);
    if ($kod !== 200) {
        throw new RuntimeException('Search Console: ' . seo_gsc_hata_metni($kod, $j));
    }
    $out = [];
    foreach ($j['siteEntry'] ?? [] as $s) {
        $out[] = ['site' => (string) ($s['siteUrl'] ?? ''), 'yetki' => (string) ($s['permissionLevel'] ?? '')];
    }
    return $out;
}

function seo_gsc_hata_metni(int $kod, $j): string
{
    $m = is_array($j) ? ($j['error']['message'] ?? ('HTTP ' . $kod)) : ('HTTP ' . $kod);
    if (stripos($m, 'has not been used') !== false || stripos($m, 'disabled') !== false) {
        $m .= ' — Google Cloud › API\'ler ve Hizmetler › Kitaplık › "Google Search Console API" › Etkinleştir.';
    } elseif ($kod === 403) {
        $m .= ' — Search Console › Ayarlar › Kullanıcılar ve izinler bölümüne hizmet hesabı e-postasını ekleyin.';
    }
    return $m;
}

function seo_gsc_sorgu(string $tok, string $site, array $govde): array
{
    $url = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode($site) . '/searchAnalytics/query';
    [$kod, $gov] = seo_istek($url, 'POST', (string) json_encode($govde), ['Authorization: Bearer ' . $tok, 'Content-Type: application/json']);
    $j = json_decode($gov, true);
    if ($kod !== 200) {
        throw new RuntimeException('Search Console: ' . seo_gsc_hata_metni($kod, $j));
    }
    return $j['rows'] ?? [];
}

/** Search Console + PageSpeed verisini toplar. Hata olursa 'hatalar' dolar, diğer kısım yine döner. */
function seo_veri_topla(bool $psi = true, ?array $ayar = null): array
{
    $a = $ayar ?? seo_ayar();
    $sonuc = [
        'uretildi' => date('c'),
        'site' => $a['gsc_site'],
        'sayfa' => $a['site_url'],
        'onbellek' => false,
        'gsc' => null,
        'mulkler' => null,
        'psi' => null,
        'hatalar' => [],
    ];

    try {
        $tok = seo_google_token($a['anahtar_dosyasi']);
        try {
            $sonuc['mulkler'] = seo_gsc_mulkler($tok);
        } catch (Throwable $e) {
            // mülk listesi yalnızca yardımcı bilgi
        }
        // GSC verisi ~2-3 gün gecikmeli gelir
        $bitis = date('Y-m-d', strtotime('-2 days'));
        $baslangic = date('Y-m-d', strtotime('-29 days'));
        $onceBas = date('Y-m-d', strtotime('-57 days'));
        $onceBit = date('Y-m-d', strtotime('-30 days'));
        $yuvarla = static fn(array $r): array => [
            'tiklama' => (int) ($r['clicks'] ?? 0), 'gosterim' => (int) ($r['impressions'] ?? 0),
            'to' => round((float) ($r['ctr'] ?? 0) * 100, 2), 'sira' => round((float) ($r['position'] ?? 0), 1),
        ];
        $site = $a['gsc_site'];

        $sorgular = array_map(static fn($r) => ['sorgu' => $r['keys'][0]] + $yuvarla($r),
            seo_gsc_sorgu($tok, $site, ['startDate' => $baslangic, 'endDate' => $bitis, 'dimensions' => ['query'], 'rowLimit' => 250]));
        $onceki = [];
        foreach (seo_gsc_sorgu($tok, $site, ['startDate' => $onceBas, 'endDate' => $onceBit, 'dimensions' => ['query'], 'rowLimit' => 250]) as $r) {
            $onceki[$r['keys'][0]] = round((float) $r['position'], 1);
        }
        foreach ($sorgular as &$s) {
            $s['onceki_sira'] = $onceki[$s['sorgu']] ?? null;
        }
        unset($s);

        $sayfalar = array_map(static fn($r) => ['sayfa' => $r['keys'][0]] + $yuvarla($r),
            seo_gsc_sorgu($tok, $site, ['startDate' => $baslangic, 'endDate' => $bitis, 'dimensions' => ['page'], 'rowLimit' => 100]));
        $gunluk = array_map(static fn($r) => ['tarih' => $r['keys'][0]] + $yuvarla($r),
            seo_gsc_sorgu($tok, $site, ['startDate' => $baslangic, 'endDate' => $bitis, 'dimensions' => ['date'], 'rowLimit' => 60]));
        $cihaz = array_map(static fn($r) => ['cihaz' => $r['keys'][0]] + $yuvarla($r),
            seo_gsc_sorgu($tok, $site, ['startDate' => $baslangic, 'endDate' => $bitis, 'dimensions' => ['device']]));

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

    if ($psi) {
        $psiUrl = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=mobile&locale=tr'
            . '&category=performance&category=seo&category=accessibility&category=best-practices&url=' . rawurlencode($a['site_url'])
            . ($a['psi_api_key'] !== '' ? '&key=' . rawurlencode($a['psi_api_key']) : '');
        [$kod, $gov] = seo_istek($psiUrl, 'GET', null, [], 75);
        $j = json_decode($gov, true);
        if ($kod === 200 && isset($j['lighthouseResult'])) {
            $lh = $j['lighthouseResult'];
            $puan = static fn(string $c) => isset($lh['categories'][$c]['score']) ? (int) round($lh['categories'][$c]['score'] * 100) : null;
            $olc = static fn(string $x) => $lh['audits'][$x]['displayValue'] ?? null;
            $basarisiz = [];
            foreach ($lh['audits'] ?? [] as $id => $au) {
                $mod = $au['scoreDisplayMode'] ?? '';
                if (isset($au['score']) && $au['score'] !== null && $au['score'] < 0.9 && $mod !== 'informative' && $mod !== 'notApplicable') {
                    $basarisiz[] = ['id' => $id, 'baslik' => $au['title'] ?? $id, 'deger' => $au['displayValue'] ?? null];
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
            $m = is_array($j) ? ($j['error']['message'] ?? ('HTTP ' . $kod)) : ('HTTP ' . $kod);
            if (stripos($m, 'Quota exceeded') !== false && $a['psi_api_key'] === '') {
                $m .= ' — Merkez panel › SEO · Google bölümüne PageSpeed API anahtarı girin.';
            }
            $sonuc['hatalar'][] = 'PageSpeed: ' . $m;
        }
    }

    if (!$sonuc['hatalar']) {
        seo_onbellek_yaz($sonuc);
    }
    return $sonuc;
}

/* ---------- Tanıtım sayfaları: doğrulama etiketleri ---------- */

/** Panelde girilen Google/Bing doğrulama kodları (pazarlama.php'deki sabit kod boşsa kullanılır). */
function seo_dogrulama_kodlari(): array
{
    try {
        $a = seo_ayar();
    } catch (Throwable $e) {
        return ['google_dogrulama' => '', 'bing_dogrulama' => ''];
    }
    return ['google_dogrulama' => $a['google_dogrulama'], 'bing_dogrulama' => $a['bing_dogrulama']];
}
