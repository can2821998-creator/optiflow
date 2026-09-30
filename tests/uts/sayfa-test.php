<?php
declare(strict_types=1);
/* Sayfa şablonlarının SQLite üzerinde uyarısız (notice/warning) render edildiğini ve POST işlemlerini sınar.
   Kullanım: UTS_TEST_DB=… UTS_TEST_ROOT=… php sayfa-test.php <senaryo> [GET/POST JSON] */
set_error_handler(static function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) {
        return false;   // @ ile bastırılmış
    }
    fwrite(STDERR, "UYARI: $msg @ " . basename($file) . ":$line\n");
    exit(3);
});
require __DIR__ . '/ortam.php';
session_start();

// Arayüz yardımcıları (layout.php'nin sade karşılıkları)
function icon(string $n, string $c = 'ic'): string { return '<svg data-icon="' . e($n) . '"></svg>'; }
function page_start(string $t, string $a = '', array $o = []): void { echo "<main data-sayfa=\"" . e($t) . "\">"; }
function page_header(string $t, string $s = '', string $a = '', string $b = '', string $k = ''): void { echo '<h1>' . e($t) . '</h1>' . $a; }
function page_end(array $s = []): void { echo '</main>'; }
function empty_state(string $t, string $x = '', string $a = ''): string { return '<div class="empty"><b>' . e($t) . '</b></div>'; }
function select_options(array $options, ?string $selected, bool $assoc = true): string
{
    $h = '';
    foreach ($options as $k => $v) {
        $value = $assoc ? (string) $k : (string) $v;
        $h .= '<option value="' . e($value) . '"' . ($value === (string) $selected ? ' selected' : '') . '>' . e(is_array($v) ? $v[0] : $v) . '</option>';
    }
    return $h;
}
function require_login(): array { return current_user(); }
function ozellik_gereksin(string $k): void {}

$senaryo = $argv[1] ?? '';
$istek = json_decode($argv[2] ?? '{}', true) ?: [];
$_GET = $istek['get'] ?? [];
$_POST = $istek['post'] ?? [];
$_SERVER['REQUEST_METHOD'] = $_POST ? 'POST' : 'GET';
$_SERVER['REQUEST_URI'] = '/uts.php';
if (isset($istek['tasiyici'])) {
    require __DIR__ . '/sahte-uts.php';
    $GLOBALS['__uts_tasiyici'] = new SahteUts();
}
$kaynak = static function (string $yol): string {
    $k = (string) file_get_contents($yol);
    $k = preg_replace('~^require dirname\(__DIR__, 2\) \. \'/app/bootstrap\.php\';~m', '', $k, 1);
    $gecici = APP_ROOT . '/sayfa-' . md5($yol) . '.php';
    file_put_contents($gecici, $k);
    return $gecici;
};
register_shutdown_function(static function (): void {
    echo "\n__FLASH__" . json_encode($_SESSION['flash'] ?? [], JSON_UNESCAPED_UNICODE);
});
ob_start();
switch ($senaryo) {
    case 'hazirla':
        setting_set('uts_ortam', 'deneme');
        insert('frame_items', ['brand' => 'Ray-Ban', 'model' => 'RB5154', 'barcode' => '8680000000017', 'qty' => 3, 'price' => 2450.0, 'created_at' => uts_simdi(), 'updated_at' => uts_simdi()]);
        insert('suppliers', ['name' => 'Toptancı', 'uts_kurum_no' => '9988776655']);
        insert('orders', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz']);
        break;
    case 'uts':
        require $kaynak(dirname(__DIR__, 2) . '/app/pages/uts.php');
        break;
    case 'siparis':
        $id = (int) ($istek['id'] ?? 1);
        $order = row('SELECT o.*, \'gozluk\' AS transaction_type FROM orders o WHERE id = ?', [$id]);
        require dirname(__DIR__, 2) . '/app/partials/uts-siparis-karti.php';
        break;
    case 'ayar':
        require dirname(__DIR__, 2) . '/app/partials/ayarlar-moduller.php';
        if ($_POST) {
            echo moduller_ayar_post('uts', (string) $_POST['action']) ? 'ISLENDI' : 'ISLENMEDI';
        } else {
            moduller_ayar_goster('uts');
        }
        break;
    case 'okut':
        try {
            $r = uts_siparise_okut((int) $istek['id'], (string) $istek['kod']);
            echo 'OKUNDU ' . uts_urun_etiketi($r['urun']) . ' ' . json_encode($r['fiyat']);
        } catch (DomainException $e) {
            echo 'HATA ' . $e->getMessage();
        }
        break;
    case 'sql':
        echo json_encode(rows((string) $istek['sql']), JSON_UNESCAPED_UNICODE);
        break;
}
echo ob_get_clean();
