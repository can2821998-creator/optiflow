<?php
declare(strict_types=1);

/* ==========================================================================
   Web Push — saf PHP (composer / dış kütüphane yok)
   RFC 8291 (aes128gcm şifreleme) + RFC 8292 (VAPID kimliği).
   Gerekenler: openssl eklentisi (EC P-256), PHP 8.0+, curl ya da akış sarmalayıcı.
   ========================================================================== */

/** URL-güvenli base64 (dolgusuz) */
function b64url(string $bin): string
{
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

function b64url_decode(string $txt): string
{
    $txt = strtr($txt, '-_', '+/');
    $pad = strlen($txt) % 4;
    if ($pad) {
        $txt .= str_repeat('=', 4 - $pad);
    }
    return (string) base64_decode($txt, true);
}

/** Sunucu bu makinede web push gönderebilir mi? */
function push_supported(): bool
{
    return extension_loaded('openssl')
        && function_exists('openssl_pkey_derive')
        && function_exists('hash_hkdf')
        && in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
}

/**
 * VAPID anahtar çifti. İlk çağrıda üretilip ayarlara yazılır.
 * Dönen: ['public' => ham noktanın base64url'ü, 'private' => PEM]
 */
function push_vapid(): ?array
{
    $pub = setting('vapid_public_key');
    $pem = setting('vapid_private_pem');
    if ($pub !== '' && $pem !== '') {
        return ['public' => $pub, 'private' => $pem];
    }
    if (!push_supported()) {
        return null;
    }

    $key = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($key === false) {
        return null;
    }
    $pemOut = '';
    if (!openssl_pkey_export($key, $pemOut)) {
        return null;
    }
    $d = openssl_pkey_get_details($key);
    if (!$d || !isset($d['ec']['x'], $d['ec']['y'])) {
        return null;
    }
    $raw = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);

    setting_set('vapid_public_key', b64url($raw));
    setting_set('vapid_private_pem', $pemOut);

    return ['public' => b64url($raw), 'private' => $pemOut];
}

/** Ham 65 baytlık P-256 açık noktasından PEM açık anahtar üretir. */
function push_ec_public_pem(string $raw65): string
{
    // SubjectPublicKeyInfo başlığı: prime256v1, uncompressed point
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw65;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/** openssl'in DER imzasını JWT'nin beklediği 64 baytlık r||s biçimine çevirir. */
function push_der_to_raw(string $der): string
{
    $off = 0;
    if (($der[$off++] ?? '') !== "\x30") {
        return '';
    }
    $len = ord($der[$off++]);
    if ($len & 0x80) {
        $off += ($len & 0x7f);
    }
    $parts = [];
    for ($i = 0; $i < 2; $i++) {
        if (($der[$off++] ?? '') !== "\x02") {
            return '';
        }
        $l = ord($der[$off++]);
        $v = substr($der, $off, $l);
        $off += $l;
        $v = ltrim($v, "\0");
        $parts[] = str_pad($v, 32, "\0", STR_PAD_LEFT);
    }
    return $parts[0] . $parts[1];
}

/** VAPID 'sub' alanı için site adresi. Web isteğinde bir kez öğrenilip saklanır. */
function push_site_url(): string
{
    $kayitli = setting('site_url');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '' && preg_match('/^[a-z0-9.\-:]+$/i', $host)) {
        $url = 'https://' . $host;
        if ($kayitli !== $url) {
            try { setting_set('site_url', $url); } catch (Throwable $e) { /* yoksay */ }
        }
        return $url;
    }
    return $kayitli !== '' ? $kayitli : 'https://localhost';
}

/** Uç noktanın kökü için VAPID JWT üretir. */
function push_vapid_jwt(string $endpoint, array $vapid): string
{
    $u = parse_url($endpoint);
    $aud = ($u['scheme'] ?? 'https') . '://' . ($u['host'] ?? '');
    $sub = setting('shop_email');
    if ($sub === '' || !filter_var($sub, FILTER_VALIDATE_EMAIL)) {
        $sub = push_site_url();
    } else {
        $sub = 'mailto:' . $sub;
    }

    $header  = b64url(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_UNESCAPED_SLASHES));
    $payload = b64url(json_encode([
        'aud' => $aud,
        'exp' => time() + 12 * 3600,
        'sub' => $sub,
    ], JSON_UNESCAPED_SLASHES));

    $key = openssl_pkey_get_private($vapid['private']);
    if ($key === false) {
        return '';
    }
    $sig = '';
    if (!openssl_sign($header . '.' . $payload, $sig, $key, OPENSSL_ALGO_SHA256)) {
        return '';
    }
    $raw = push_der_to_raw($sig);
    if ($raw === '') {
        return '';
    }
    return $header . '.' . $payload . '.' . b64url($raw);
}

/**
 * Yükü RFC 8291'e göre şifreler (aes128gcm).
 * @return string HTTP gövdesi
 */
function push_encrypt(string $plain, string $uaPublicRaw, string $authSecret): string
{
    $eph = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
    if ($eph === false) {
        return '';
    }
    $d = openssl_pkey_get_details($eph);
    $asPublic = "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);

    $peer = openssl_pkey_get_public(push_ec_public_pem($uaPublicRaw));
    if ($peer === false) {
        return '';
    }
    $shared = openssl_pkey_derive($peer, $eph, 32);
    if ($shared === false) {
        return '';
    }

    $prk  = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublicRaw . $asPublic, $authSecret);
    $salt = random_bytes(16);
    $cek  = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\0", $salt);
    $non  = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\0", $salt);

    $tag = '';
    $enc = openssl_encrypt($plain . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $non, $tag);
    if ($enc === false) {
        return '';
    }

    // salt(16) | kayıt boyu(4) | anahtar uzunluğu(1) | açık anahtar(65) | şifreli veri
    return $salt . pack('N', 4096) . chr(strlen($asPublic)) . $asPublic . $enc . $tag;
}

/**
 * Tek bir aboneliğe bildirim gönderir.
 * @return array{code:int, error:string} code 0 = istek hiç kurulamadı
 */
function push_send_one(array $sub, array $message): array
{
    $vapid = push_vapid();
    if (!$vapid) {
        return ['code' => 0, 'error' => 'VAPID anahtarı üretilemedi (openssl?)'];
    }
    $p256 = b64url_decode((string) $sub['p256dh']);
    $auth = b64url_decode((string) $sub['auth_secret']);
    if (strlen($p256) !== 65 || strlen($auth) < 16) {
        return ['code' => 400, 'error' => 'Abonelik anahtarları bozuk'];
    }

    $body = push_encrypt(json_encode($message, JSON_UNESCAPED_UNICODE), $p256, $auth);
    if ($body === '') {
        return ['code' => 0, 'error' => 'Yük şifrelenemedi'];
    }
    $jwt = push_vapid_jwt((string) $sub['endpoint'], $vapid);
    if ($jwt === '') {
        return ['code' => 0, 'error' => 'VAPID imzası üretilemedi'];
    }

    $headers = [
        'TTL: 86400',
        'Urgency: normal',
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Authorization: vapid t=' . $jwt . ',k=' . $vapid['public'],
    ];

    if (function_exists('curl_init')) {
        $ch = curl_init((string) $sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $out = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($code === 0) {
            return ['code' => 0, 'error' => 'Sunucu dışarı bağlanamadı: ' . ($err ?: 'bilinmeyen curl hatası')];
        }
        if ($code >= 200 && $code < 300) {
            return ['code' => $code, 'error' => ''];
        }
        return ['code' => $code, 'error' => trim(mb_substr((string) $out, 0, 300))];
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $body,
        'timeout'       => 8,
        'ignore_errors' => true,
    ]]);
    $res = @file_get_contents((string) $sub['endpoint'], false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $code = (int) $m[1];
        }
    }
    if ($code === 0) {
        return ['code' => 0, 'error' => 'Sunucu dışarı bağlanamadı (curl yok, akış da başarısız)'];
    }
    if ($code >= 200 && $code < 300) {
        return ['code' => $code, 'error' => ''];
    }
    return ['code' => $code, 'error' => trim(mb_substr((string) $res, 0, 300))];
}

/**
 * Bildirim gönderir. $userIds boşsa bildirim açmış herkese gider.
 * $message: ['title' => …, 'body' => …, 'url' => …, 'tag' => …]
 * @return array{sent:int, failed:int, detail:array, reason:string}
 */
function push_send(array $message, array $userIds = [], ?int $exceptUserId = null): array
{
    if (!push_supported()) {
        return ['sent' => 0, 'failed' => 0, 'detail' => [], 'reason' => 'Sunucuda openssl/EC desteği yok'];
    }
    if (setting('push_enabled', '1') !== '1') {
        return ['sent' => 0, 'failed' => 0, 'detail' => [], 'reason' => 'Bildirimler ayarlardan kapatılmış'];
    }

    $sql = 'SELECT * FROM push_subscriptions';
    $where = [];
    $args = [];
    if ($userIds) {
        $where[] = 'user_id IN (' . implode(',', array_fill(0, count($userIds), '?')) . ')';
        $args = array_merge($args, array_map('intval', $userIds));
    }
    if ($exceptUserId !== null) {
        $where[] = 'user_id <> ?';
        $args[] = $exceptUserId;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' LIMIT 300';

    try {
        $subs = rows($sql, $args);
    } catch (Throwable $e) {
        return ['sent' => 0, 'failed' => 0, 'detail' => [], 'reason' => 'push_subscriptions tablosu yok — kontrol.php ile göçü çalıştırın'];
    }
    if (!$subs) {
        return ['sent' => 0, 'failed' => 0, 'detail' => [], 'reason' => 'Kayıtlı cihaz yok — önce Profilim sayfasından bildirimi açın'];
    }

    $message += ['title' => setting('shop_name', 'OptiFlow'), 'body' => '', 'url' => 'index.php'];
    $sent = 0;
    $failed = 0;
    $detail = [];

    foreach ($subs as $s) {
        $r = push_send_one($s, $message);
        $host = parse_url((string) $s['endpoint'], PHP_URL_HOST) ?: '';
        $detail[] = [
            'device' => (string) ($s['device'] ?? ''),
            'user_id' => (int) $s['user_id'],
            'host'   => $host,
            'code'   => $r['code'],
            'error'  => $r['error'],
        ];

        if ($r['code'] >= 200 && $r['code'] < 300) {
            $sent++;
            try {
                q('UPDATE push_subscriptions SET last_ok_at = NOW(), fail_count = 0 WHERE id = ?', [(int) $s['id']]);
            } catch (Throwable $e) { /* yoksay */ }
        } else {
            $failed++;
            try {
                if ($r['code'] === 404 || $r['code'] === 410) {
                    q('DELETE FROM push_subscriptions WHERE id = ?', [(int) $s['id']]);
                } else {
                    q('UPDATE push_subscriptions SET fail_count = fail_count + 1 WHERE id = ?', [(int) $s['id']]);
                    q('DELETE FROM push_subscriptions WHERE id = ? AND fail_count > 20', [(int) $s['id']]);
                }
            } catch (Throwable $e) { /* yoksay */ }
        }
    }

    return ['sent' => $sent, 'failed' => $failed, 'detail' => $detail, 'reason' => ''];
}

/**
 * Kurulum teşhisi: neyin çalışıp neyin çalışmadığını tek tek söyler.
 * @return array<int, array{ad:string, ok:bool, not:string}>
 */
function push_diagnostics(): array
{
    $c = [];
    $c[] = ['ad' => 'openssl eklentisi', 'ok' => extension_loaded('openssl'), 'not' => extension_loaded('openssl') ? 'var' : 'PHP openssl eklentisi kurulu değil'];
    $c[] = ['ad' => 'EC anahtar türetme (openssl_pkey_derive)', 'ok' => function_exists('openssl_pkey_derive'), 'not' => function_exists('openssl_pkey_derive') ? 'var' : 'PHP 7.3+ ve openssl gerekir'];
    $aes = in_array('aes-128-gcm', openssl_get_cipher_methods(), true);
    $c[] = ['ad' => 'aes-128-gcm şifreleme', 'ok' => $aes, 'not' => $aes ? 'var' : 'openssl bu şifrelemeyi desteklemiyor'];
    $c[] = ['ad' => 'curl eklentisi', 'ok' => function_exists('curl_init'), 'not' => function_exists('curl_init') ? 'var' : 'yok — akış sarmalayıcı denenecek (allow_url_fopen gerekir)'];

    $v = push_vapid();
    $c[] = ['ad' => 'VAPID anahtar çifti', 'ok' => (bool) $v, 'not' => $v ? 'üretildi ve kayıtlı' : 'üretilemedi'];

    $semaDb = 0;
    try {
        $semaDb = (int) scalar("SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'");
    } catch (Throwable $e) { /* yoksay */ }
    $semaOk = defined('SCHEMA_VERSION') ? $semaDb >= SCHEMA_VERSION : true;
    $c[] = [
        'ad'  => 'Veritabanı şema sürümü',
        'ok'  => $semaOk,
        'not' => $semaOk
            ? 'güncel (v' . $semaDb . ')'
            : 'v' . $semaDb . ' — beklenen v' . (defined('SCHEMA_VERSION') ? SCHEMA_VERSION : '?') . '; herhangi bir sayfayı yenileyin, göç kendiliğinden çalışır',
    ];

    $tablo = true;
    $adet = 0;
    try {
        $adet = (int) scalar('SELECT COUNT(*) FROM push_subscriptions');
    } catch (Throwable $e) {
        $tablo = false;
    }
    $c[] = ['ad' => 'push_subscriptions tablosu', 'ok' => $tablo, 'not' => $tablo ? $adet . ' kayıtlı cihaz' : 'tablo yok — kontrol.php açıp göçü çalıştırın'];

    // Dışarı çıkış izni: push servislerine gerçekten bağlanabiliyor muyuz?
    foreach ([
        'Android / Chrome servisi (fcm.googleapis.com)' => 'https://fcm.googleapis.com/',
        'iPhone / Safari servisi (web.push.apple.com)'   => 'https://web.push.apple.com/',
    ] as $ad => $url) {
        $net = false;
        $netNot = 'curl yok — denenemedi';
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 4]);
            curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            $net = $code > 0;
            $netNot = $net
                ? 'bağlanıyor (HTTP ' . $code . ')'
                : 'BAĞLANAMIYOR: ' . ($err ?: 'bilinmeyen hata') . ' — hosting dış bağlantıyı engelliyor olabilir';
        }
        $c[] = ['ad' => $ad, 'ok' => $net, 'not' => $netNot];
    }

    $c[] = ['ad' => 'Site adresi (VAPID sub)', 'ok' => true, 'not' => push_site_url()];

    $acik = setting('push_enabled', '1') === '1';
    $c[] = ['ad' => 'Bildirimler açık (push_enabled)', 'ok' => $acik, 'not' => $acik ? 'açık' : 'app_settings.push_enabled = 0'];

    return $c;
}

/** Bildirimi yalnızca yöneticilere ve atölye personeline gönderir. */
function push_send_staff(array $message, ?int $exceptUserId = null): array
{
    try {
        $ids = array_map(
            static fn($r) => (int) $r['id'],
            rows("SELECT id FROM user_accounts WHERE is_active = 1")
        );
    } catch (Throwable $e) {
        $ids = [];
    }
    return push_send($message, $ids, $exceptUserId);
}
