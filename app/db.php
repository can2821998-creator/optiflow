<?php
declare(strict_types=1);

$GLOBALS['__db_pdo'] ??= null;

function db(): PDO
{
    if ($GLOBALS['__db_pdo'] instanceof PDO) {
        return $GLOBALS['__db_pdo'];
    }
    $c = config('db', []);
    $dsn = 'mysql:host=' . ($c['host'] ?? 'localhost')
        . (!empty($c['port']) ? ';port=' . (int) $c['port'] : '')
        . ';dbname=' . ($c['name'] ?? '') . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['password'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        app_log('DB bağlantı hatası: ' . $e->getMessage());
        http_response_code(500);
        render_error_page('Veritabanına bağlanılamadı', 'config.php içindeki veritabanı bilgilerini kontrol edin.');
    }
    // MySQL saatini PHP ile eşitle (CURRENT_TIMESTAMP ve DATE() filtreleri için).
    $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    $GLOBALS['__db_pdo'] = $pdo;
    return $pdo;
}

/** Aktif veritabanı bağlantısını kapatıp bir sonraki db() çağrısında YENİDEN bağlanmaya zorlar.
    Mağaza sağlama (yeni mağaza veritabanı açma) sırasında, aynı istek içinde önce merkez veritabanına,
    sonra yeni açılan mağaza veritabanına geçmek için kullanılır. Normal sayfa isteklerinde gerekmez. */
function db_baglanti_degistir(array $yeniDbAyari): void
{
    $GLOBALS['__db_pdo'] = null;
    $GLOBALS['config']['db'] = $yeniDbAyari;
}

/** Hazırlanmış sorgu çalıştırır. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute(array_values($params));
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function update(string $table, array $data, string $where, array $whereParams): int
{
    $set = implode(',', array_map(static fn($c) => "`$c`=?", array_keys($data)));
    return q("UPDATE `$table` SET $set WHERE $where", array_merge(array_values($data), $whereParams))->rowCount();
}

function in_placeholders(array $values): string
{
    return implode(',', array_fill(0, max(1, count($values)), '?'));
}

function transaction(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function table_exists(string $table): bool
{
    return (bool) scalar('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?', [$table]);
}

function column_exists(string $table, string $column): bool
{
    return (bool) scalar('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$table, $column]);
}

function index_exists(string $table, string $index): bool
{
    return (bool) scalar('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?', [$table, $index]);
}

/* ------------------------------------------------------------------ */
/*  Ayarlar (anahtar/değer)                                             */
/* ------------------------------------------------------------------ */

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null || $key === '__reload__') {
        $cache = [];
        try {
            foreach (rows('SELECT setting_key, setting_value FROM app_settings') as $r) {
                $cache[$r['setting_key']] = (string) $r['setting_value'];
            }
        } catch (PDOException) {
            $cache = [];
        }
    }
    return $cache[$key] ?? $default;
}

function setting_set(string $key, string $value): void
{
    q('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $value]);
    setting('__reload__');
}

/* ------------------------------------------------------------------ */
/*  İşlem geçmişi                                                       */
/* ------------------------------------------------------------------ */

function audit(string $action, string $entity = '', ?int $entityId = null, array $details = []): void
{
    $u = current_user();
    insert('audit_log', [
        'user_id'     => $u['id'] ?? null,
        'user_name'   => $u['full_name'] ?? ($details['_user'] ?? 'Sistem'),
        'action'      => $action,
        'entity'      => $entity,
        'entity_id'   => $entityId,
        'details'     => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        'ip'          => client_ip(),
    ]);
}
