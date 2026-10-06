<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/*  Çıktı ve istek yardımcıları                                        */
/* ------------------------------------------------------------------ */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** POST değeri (string, kırpılmış). */
function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/** GET değeri (string, kırpılmış). */
function query(string $key, string $default = ''): string
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

function post_int(string $key): int
{
    return (int) ($_POST[$key] ?? 0);
}

function query_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return is_numeric($v) ? (int) $v : $default;
}

function redirect(string $path): void
{
    header('Location: ' . $path, true, 303);
    exit;
}

/** Aynı sayfaya, verilen GET parametreleriyle bağlantı. */
function url_with(array $params): string
{
    $q = array_merge($_GET, $params);
    $q = array_filter($q, static fn($v) => $v !== null && $v !== '');
    $path = strtok($_SERVER['REQUEST_URI'] ?? '', '?') ?: '';
    return basename($path) . ($q ? '?' . http_build_query($q) : '');
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $message];
}

function flashes(): array
{
    $list = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $list;
}

/** Form hatasında girilen değerleri geri doldurmak için. */
function remember_input(): void
{
    $_SESSION['old_input'] = $_POST;
    unset($_SESSION['old_input']['csrf'], $_SESSION['old_input']['password'], $_SESSION['old_input']['new_password']);
}

function old(string $key, mixed $default = ''): string
{
    static $old = null;
    if ($old === null) {
        $old = $_SESSION['old_input'] ?? [];
        unset($_SESSION['old_input']);
    }
    $v = $old[$key] ?? $default;
    return is_scalar($v) ? (string) $v : (string) $default;
}

/* ------------------------------------------------------------------ */
/*  Güvenlik                                                            */
/* ------------------------------------------------------------------ */

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    // GA4 ayarlıysa yalnızca Google ölçümleme alan adlarına izin ver (satır içi kod hâlâ yasak).
    $ga = function_exists('ga_csp') ? ga_csp() : ['script' => '', 'connect' => '', 'img' => ''];
    header(
        "Content-Security-Policy: default-src 'self'; "
        . "img-src 'self' data:" . $ga['img'] . "; "
        . "style-src 'self' 'unsafe-inline'; "
        . "script-src 'self'" . $ga['script'] . "; "
        . "connect-src 'self'" . $ga['connect'] . "; "
        . "frame-ancestors 'self'; form-action 'self'; base-uri 'self'"
    );
}

function start_session(): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_name('optiflow');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', '43200');
    if (PHP_SAPI !== 'cli') {
        session_start();
    } elseif (!isset($_SESSION)) {
        $_SESSION = [];
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $sent)) {
        http_response_code(419);
        render_error_page('Oturum süresi doldu', 'Güvenlik anahtarı geçersiz. Sayfayı yenileyip tekrar deneyin.');
    }
}

/**
 * OptiFlow Masaüstü (Electron) içinde mi açıldı? Kullanıcı aracısındaki
 * "OptiFlowDesktop/x.y" işaretine bakar. YALNIZCA arayüz farkları için
 * kullanılır (eklenti yönergesi yerine masaüstü düğmeleri, PWA istemi yok);
 * kullanıcı aracısı taklit edilebileceği için ASLA yetki kararı için kullanılmaz.
 */
function is_optiflow_desktop(): bool
{
    return (bool) preg_match('#\bOptiFlowDesktop/\d+\.\d+#', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/* ------------------------------------------------------------------ */
/*  Hata yönetimi                                                       */
/* ------------------------------------------------------------------ */

function app_log(string $message): void
{
    $dir = APP_ROOT . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . '/app-' . date('Y-m') . '.log', '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND | LOCK_EX);
}

function handle_fatal(Throwable $e): void
{
    $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    app_log("#$ref " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (config('debug', false)) {
        render_error_page('Hata #' . $ref, $e->getMessage() . ' — ' . basename($e->getFile()) . ':' . $e->getLine());
    }
    render_error_page('Beklenmeyen bir hata oluştu', 'İşlem tamamlanamadı. Sorun devam ederse bu kodu iletin: #' . $ref);
}

function render_error_page(string $title, string $message): void
{
    /* 4.10.0 — JSON uç noktaları (masaustu.php) hata sayfası yerine JSON alır: masaüstü
       uygulaması HTML'i ayrıştırmaya çalışmaz, kullanıcıya anlamlı mesaj gösterir. */
    if (!empty($GLOBALS['__json_api'])) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            if (http_response_code() === 200) {
                http_response_code(503);
            }
        }
        echo json_encode([
            'ok'   => false,
            'kod'  => http_response_code() === 419 ? 'csrf' : 'hata',
            'hata' => $title . ': ' . $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($title) . '</title><link rel="stylesheet" href="assets/app.css?v=' . APP_VERSION . '"><script src="assets/tema.js?v=' . APP_VERSION . '"></script></head><body class="auth-page"><main class="auth-single"><div class="auth-card"><span class="kicker">OptiFlow</span><h1>' . e($title) . '</h1><p class="muted">' . e($message) . '</p><a class="btn btn-primary" href="index.php">Ana sayfaya dön</a></div></main></body></html>';
    exit;
}

/* ------------------------------------------------------------------ */
/*  Biçimlendirme ve ayrıştırma                                         */
/* ------------------------------------------------------------------ */

/**
 * Türkçe ve uluslararası tutar girişlerini doğru okur.
 * "1.250,50" → 1250.50 · "1250.5" → 1250.50 · "1.250" → 1250 · "12,5" → 12.50
 * Geçersizse null döner.
 */
function parse_money(string $raw): ?float
{
    $s = str_replace([' ', '₺', 'TL', 'tl', "\u{00A0}"], '', trim($raw));
    if ($s === '') {
        return 0.0;
    }
    if (!preg_match('/^-?[0-9.,]+$/', $s)) {
        return null;
    }
    $lastComma = strrpos($s, ',');
    $lastDot = strrpos($s, '.');
    if ($lastComma !== false && $lastDot !== false) {
        // İkisi de var: sonda olan ondalık ayırıcıdır.
        $decimal = $lastComma > $lastDot ? ',' : '.';
        $thousand = $decimal === ',' ? '.' : ',';
        $s = str_replace($thousand, '', $s);
        $s = str_replace($decimal, '.', $s);
    } elseif ($lastComma !== false) {
        // Sadece virgül: Türkçe ondalık. Birden fazla virgül → binlik.
        $s = substr_count($s, ',') > 1 ? str_replace(',', '', $s) : str_replace(',', '.', $s);
    } elseif ($lastDot !== false) {
        // Sadece nokta: "1.250" / "12.500.000" binlik; "12.5" / "12.50" ondalık.
        if (substr_count($s, '.') > 1 || preg_match('/\.\d{3}$/', $s)) {
            $s = str_replace('.', '', $s);
        }
    }
    if (!is_numeric($s)) {
        return null;
    }
    return round((float) $s, 2);
}

function money(mixed $value): string
{
    return number_format((float) $value, 2, ',', '.') . ' ₺';
}

/** Telefonu 90XXXXXXXXXX biçimine getirir. Boşsa '' döner, geçersizse null. */
function normalize_phone(string $raw): ?string
{
    $d = preg_replace('/\D+/', '', $raw) ?? '';
    if ($d === '') {
        return '';
    }
    if (str_starts_with($d, '0090')) {
        $d = substr($d, 2);
    }
    if (strlen($d) === 11 && $d[0] === '0') {
        $d = '90' . substr($d, 1);
    }
    if (strlen($d) === 10 && $d[0] === '5') {
        $d = '90' . $d;
    }
    if (preg_match('/^90[2-5]\d{9}$/', $d)) {
        return $d;
    }
    // Yurt dışı numaralar: 8-15 hane kabul edilir.
    if (!str_starts_with($d, '90') && strlen($d) >= 8 && strlen($d) <= 15) {
        return $d;
    }
    return null;
}

function phone_display(?string $phone): string
{
    $p = (string) $phone;
    if (preg_match('/^90(\d{3})(\d{3})(\d{2})(\d{2})$/', $p, $m)) {
        return "0{$m[1]} {$m[2]} {$m[3]} {$m[4]}";
    }
    return $p;
}

function valid_date(string $value): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
}

function date_tr(?string $value, bool $withTime = false): string
{
    if (!$value || str_starts_with($value, '0000')) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date($withTime ? 'd.m.Y H:i' : 'd.m.Y', $ts) : '—';
}

/** Bir tarihten bugüne kaç tam gün geçtiğini döner (negatif olamaz). */
function days_since(?string $value): int
{
    if (!$value) {
        return 0;
    }
    $ts = strtotime($value);
    return $ts ? max(0, (int) floor((time() - $ts) / 86400)) : 0;
}

/** Söz verilen teslim tarihinin kaç gün geçtiğini döner; henüz gelmediyse veya tarih yoksa 0. */
function days_overdue(?string $promisedDate): int
{
    if (!$promisedDate) {
        return 0;
    }
    $ts = strtotime($promisedDate . ' 00:00:00');
    return $ts && $ts < strtotime('today') ? (int) floor((strtotime('today') - $ts) / 86400) : 0;
}

function order_no(int $id): string
{
    return '#' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

function initials(string $first, string $last): string
{
    return mb_strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
}

/** Türkçe duyarlı küçük harf (İ/I sorunu). */
function tr_lower(string $s): string
{
    return mb_strtolower(strtr($s, ['I' => 'ı', 'İ' => 'i']));
}

function tr_title(string $s): string
{
    $s = preg_replace('/\s+/u', ' ', trim($s)) ?? '';
    $words = explode(' ', $s);
    foreach ($words as &$w) {
        if ($w === '') {
            continue;
        }
        $first = mb_substr($w, 0, 1);
        $rest = mb_substr($w, 1);
        $first = strtr($first, ['i' => 'İ', 'ı' => 'I']);
        $w = mb_strtoupper($first) . tr_lower($rest);
    }
    return implode(' ', $words);
}

/* ------------------------------------------------------------------ */
/*  Sayfalama                                                           */
/* ------------------------------------------------------------------ */

function paginate(int $total, int $perPage = 25): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, query_int('sayfa', 1)));
    return ['page' => $page, 'pages' => $pages, 'per' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

function pagination_links(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $html = '<nav class="pager" aria-label="Sayfalar">';
    $html .= $p['page'] > 1 ? '<a href="' . e(url_with(['sayfa' => $p['page'] - 1])) . '">‹ Önceki</a>' : '<span class="disabled">‹ Önceki</span>';
    $html .= '<span class="pager-info">' . $p['page'] . ' / ' . $p['pages'] . '</span>';
    $html .= $p['page'] < $p['pages'] ? '<a href="' . e(url_with(['sayfa' => $p['page'] + 1])) . '">Sonraki ›</a>' : '<span class="disabled">Sonraki ›</span>';
    return $html . '</nav>';
}
