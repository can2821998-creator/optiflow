<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$me = current_user();
if (!$me) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum kapalı']);
    exit;
}

function push_json(array $data): never
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = query('action');

/* --- Sunucu durumu ve genel anahtar: istemci aboneliği bununla kurar --- */
if ($action === 'key') {
    if (!push_supported()) {
        push_json(['ok' => false, 'reason' => 'Sunucuda openssl desteği yok']);
    }
    $vapid = push_vapid();
    if (!$vapid) {
        push_json(['ok' => false, 'reason' => 'Anahtar üretilemedi']);
    }
    $mine = 0;
    try {
        $mine = (int) scalar('SELECT COUNT(*) FROM push_subscriptions WHERE user_id = ?', [(int) $me['id']]);
    } catch (Throwable $e) { /* yoksay */ }
    push_json(['ok' => true, 'key' => $vapid['public'], 'devices' => $mine]);
}

if (!is_post()) {
    http_response_code(405);
    push_json(['error' => 'Yalnızca POST']);
}
csrf_check();

$raw = file_get_contents('php://input') ?: '';
$in = json_decode($raw, true);
$in = is_array($in) ? $in : [];

/* --- Aboneliği kaydet --- */
if ($action === 'subscribe') {
    $endpoint = (string) ($in['endpoint'] ?? '');
    $p256 = (string) ($in['p256dh'] ?? '');
    $auth = (string) ($in['auth'] ?? '');
    if (!preg_match('#^https://#', $endpoint) || strlen($endpoint) > 500 || $p256 === '' || $auth === '') {
        http_response_code(422);
        push_json(['error' => 'Abonelik bilgisi eksik']);
    }

    $device = mb_substr(trim((string) ($in['device'] ?? '')), 0, 120);
    $hash = hash('sha256', $endpoint);

    try {
        q(
            'INSERT INTO push_subscriptions (user_id, endpoint, endpoint_hash, p256dh, auth_secret, device)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh),
               auth_secret = VALUES(auth_secret), device = VALUES(device), fail_count = 0',
            [(int) $me['id'], $endpoint, $hash, $p256, $auth, $device ?: null]
        );
    } catch (Throwable $e) {
        http_response_code(500);
        push_json(['error' => 'Kaydedilemedi']);
    }
    audit('push_subscribe', 'user', (int) $me['id'], ['cihaz' => $device ?: 'bilinmiyor']);
    push_json(['ok' => true]);
}

/* --- Aboneliği sil --- */
if ($action === 'unsubscribe') {
    $endpoint = (string) ($in['endpoint'] ?? '');
    if ($endpoint === '') {
        push_json(['ok' => true]);
    }
    try {
        q('DELETE FROM push_subscriptions WHERE endpoint_hash = ? AND user_id = ?', [hash('sha256', $endpoint), (int) $me['id']]);
    } catch (Throwable $e) { /* yoksay */ }
    push_json(['ok' => true]);
}

/* --- Deneme bildirimi: yalnızca kendi cihazlarına --- */
if ($action === 'test') {
    $r = push_send([
        'title' => setting('shop_name', 'OptiFlow'),
        'body'  => 'Bildirimler çalışıyor. Sipariş hareketleri artık telefonunuza düşecek.',
        'url'   => 'index.php',
        'tag'   => 'deneme',
    ], [(int) $me['id']]);
    $neden = $r['reason'];
    if ($neden === '' && $r['sent'] === 0 && !empty($r['detail'])) {
        $ilk = $r['detail'][0];
        $neden = ($ilk['code'] > 0 ? 'HTTP ' . $ilk['code'] . ' · ' : '') . ($ilk['error'] ?: 'bilinmeyen hata');
    }
    push_json(['ok' => $r['sent'] > 0, 'sent' => $r['sent'], 'failed' => $r['failed'], 'reason' => $neden]);
}

http_response_code(400);
push_json(['error' => 'Bilinmeyen işlem']);
