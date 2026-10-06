<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!current_user()) {
    http_response_code(401);
    echo json_encode(['error' => 'Oturum kapalı']);
    exit;
}

$action = query('action');

if ($action === 'customers') {
    $term = mb_substr(query('q'), 0, 60);
    if (mb_strlen($term) < 2) {
        echo json_encode(['items' => []]);
        exit;
    }
    $digits = preg_replace('/\D+/', '', $term) ?? '';
    $sql = "SELECT c.id, c.first_name, c.last_name, c.phone, c.birth_year,
                   (SELECT MAX(created_at) FROM orders WHERE customer_id = c.id) AS last_order,
                   (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) AS order_count
            FROM customers c WHERE CONCAT(c.first_name, ' ', c.last_name) LIKE ?";
    $params = ['%' . $term . '%'];
    if (strlen($digits) >= 4) {
        $sql .= ' OR c.phone LIKE ?';
        $params[] = '%' . ltrim($digits, '0') . '%';
    }
    $sql .= ' ORDER BY last_order DESC LIMIT 8';
    $items = array_map(static fn($c) => [
        'id'     => (int) $c['id'],
        'name'   => $c['first_name'] . ' ' . $c['last_name'],
        'phone'  => phone_display($c['phone']),
        'meta'   => ((int) $c['order_count']) . ' sipariş' . ($c['last_order'] ? ' · son ' . date_tr($c['last_order']) : ''),
        'birth_year' => $c['birth_year'] ? (int) $c['birth_year'] : null,
    ], rows($sql, $params));
    echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'global_search') {
    $term = trim(mb_substr(query('q'), 0, 60));
    if (mb_strlen($term) < 2) {
        echo json_encode(['items' => []]);
        exit;
    }
    $digits = preg_replace('/\D+/', '', $term) ?? '';
    $items = [];

    if ($digits !== '' && (int) $digits > 0) {
        $o = row(
            "SELECT o.id, o.order_stage, c.first_name, c.last_name FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?",
            [(int) $digits]
        );
        if ($o) {
            $items[] = ['type' => 'Sipariş', 'label' => order_no((int) $o['id']) . ' · ' . $o['first_name'] . ' ' . $o['last_name'], 'meta' => stage_label($o['order_stage']), 'url' => 'order.php?id=' . $o['id']];
        }
    }

    $custWhere = "CONCAT(first_name, ' ', last_name) LIKE ?";
    $custParams = ['%' . $term . '%'];
    if (strlen($digits) >= 4) {
        $custWhere .= ' OR phone LIKE ?';
        $custParams[] = '%' . ltrim($digits, '0') . '%';
    }
    foreach (rows("SELECT id, first_name, last_name, phone FROM customers WHERE $custWhere ORDER BY id DESC LIMIT 6", $custParams) as $c) {
        $items[] = ['type' => 'Müşteri', 'label' => $c['first_name'] . ' ' . $c['last_name'], 'meta' => phone_display($c['phone']) ?: '', 'url' => 'customer.php?id=' . $c['id']];
    }

    foreach (rows("SELECT id, customer_name, converted_order_id FROM quotes WHERE customer_name LIKE ? ORDER BY id DESC LIMIT 4", ['%' . $term . '%']) as $q) {
        $items[] = ['type' => 'Teklif', 'label' => $q['customer_name'], 'meta' => $q['converted_order_id'] ? 'Siparişe döndü' : '', 'url' => 'quote.php?id=' . $q['id']];
    }

    if (is_super()) {
        foreach (rows('SELECT id, name FROM suppliers WHERE name LIKE ? ORDER BY name LIMIT 4', ['%' . $term . '%']) as $s) {
            $items[] = ['type' => 'Tedarikçi', 'label' => $s['name'], 'meta' => '', 'url' => 'supplier.php?id=' . $s['id']];
        }
    }

    echo json_encode(['items' => array_slice($items, 0, 15)], JSON_UNESCAPED_UNICODE);
    exit;
}

/* 4.20.3 — Telefonda çevrimdışı kopya (özellik: cevrimdisi_tel). assets/pwa.js en çok 15 dakikada bir alır,
   tarayıcıda şifreleyip saklar; internet yokken offline.html gösterir. İçerik masaüstü kopyasıyla aynı. */
if ($action === 'cevrimdisi') {
    if (!ozellik_acik('cevrimdisi_tel')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'kod' => 'ozellik_kapali']);
        exit;
    }
    $magaza = tenant_oturum();
    echo json_encode(['ok' => true, 'gecerlilik_sn' => 86400] + cevrimdisi_ozet((int) ($magaza['id'] ?? 0), current_user()), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'wa_log' && is_post()) {
    $orderId = post_int('order_id');
    $name = mb_substr(post('template'), 0, 60);
    if ($orderId > 0) {
        audit('whatsapp', 'order', $orderId, ['şablon' => $name]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Bilinmeyen işlem']);
