<?php
/**
 * OptiFlow — uygulama çekirdeği.
 * Her sayfa yalnızca bu dosyayı yükler. PHP 8.0+ gerekir.
 */
declare(strict_types=1);

const APP_VERSION = '4.16.2';
const APP_ROOT = __DIR__ . '/..';

/**
 * Erken hata yakalama: yapılandırma veya eklenti sorunlarında boş "500" yerine
 * ne yapılacağını anlatan sade bir sayfa gösterir (parola vb. gizli bilgi göstermez).
 */
function early_fail(string $title, string $detail): void
{
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $h($title) . '</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#faf8f4;font:15px/1.6 system-ui,Arial,sans-serif;color:#1a1723;padding:16px}main{max-width:620px;background:#fff;border:1px solid #e7e1d8;border-radius:16px;padding:28px}h1{margin:0 0 10px;font-size:21px;color:#122f4d}pre{white-space:pre-wrap;background:#eef4f9;padding:12px;border-radius:8px;font-size:13px}</style></head>'
        . '<body><main><h1>' . $h($title) . '</h1><pre>' . $h($detail) . '</pre><p style="color:#6b6673;font-size:13px">Ayrıntılı kontrol için: <b>kontrol.php</b> · Sürüm ' . APP_VERSION . ' · PHP ' . PHP_VERSION . '</p></main></body></html>';
    exit;
}

set_exception_handler(static function (Throwable $e): void {
    $msg = get_class($e) . ': ' . $e->getMessage() . "\n" . basename($e->getFile()) . ':' . $e->getLine();
    @error_log('[atolye] ' . $msg);
    if (function_exists('handle_fatal')) {
        handle_fatal($e);
        return;
    }
    early_fail('Sistem başlatılamadı', $msg);
});
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $msg = $err['message'] . "\n" . basename((string) $err['file']) . ':' . $err['line'];
        @error_log('[atolye] ' . $msg);
        if (function_exists('app_log')) {
            app_log('FATAL ' . $msg);
        }
        early_fail('Sistem hatası', $msg);
    }
});

$missing = array_values(array_filter(['pdo_mysql', 'json'], static fn($x) => !extension_loaded($x)));
if ($missing) {
    early_fail('Eksik PHP eklentisi', 'Plesk › PHP Ayarları bölümünde şu eklentileri etkinleştirin: ' . implode(', ', $missing));
}
require __DIR__ . '/polyfill.php';

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    early_fail('config.php bulunamadı', 'Tarayıcıda bu kurulumun kurulum.php dosyasını açıp veritabanı bilgilerini girin; config.php otomatik oluşturulur.');
}
try {
    $loaded = require $configFile;
} catch (ParseError $e) {
    early_fail('config.php içinde yazım hatası', 'Satır ' . $e->getLine() . ' hatalı (güvenlik için ayrıntı gösterilmez).' . "\n\nEn kolay çözüm: tarayıcıda bu kurulumun kurulum.php dosyasını açın, veritabanı bilgilerini girin; config.php otomatik ve hatasız oluşturulur.");
}
if (!is_array($loaded) || !isset($loaded['db']) || !is_array($loaded['db'])) {
    early_fail('config.php okunamadı', "Dosya 'return [ 'db' => [ ... ] ];' biçiminde bir dizi döndürmeli. Eski sistemdeki config.php dosyanızı aynen kullanabilirsiniz.");
}
$GLOBALS['config'] = $loaded;

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

date_default_timezone_set((string) config('timezone', 'Europe/Istanbul'));
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

$debug = (bool) config('debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

require __DIR__ . '/helpers.php';
require __DIR__ . '/domain.php';
require __DIR__ . '/db.php';
require __DIR__ . '/migrations.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/layout.php';
require __DIR__ . '/webpush.php';
require __DIR__ . '/qr.php';
require __DIR__ . '/sgk.php';
require __DIR__ . '/reminders.php';
require __DIR__ . '/frames.php';
require __DIR__ . '/backup.php';
require __DIR__ . '/labels.php';
require_once __DIR__ . '/rehber.php';
require __DIR__ . '/merkez.php';
require __DIR__ . '/paket.php';
require __DIR__ . '/ozellik.php';
require __DIR__ . '/entegrasyon.php';
require __DIR__ . '/whatsapp.php';
require __DIR__ . '/lens.php';
require __DIR__ . '/gorevler.php';
require __DIR__ . '/odeme.php';
require __DIR__ . '/tedarik.php';
require __DIR__ . '/sgk-mutabakat.php';
require __DIR__ . '/barkod.php';
require __DIR__ . '/uts.php';      // 4.13.0 ÜTS bildirimleri
require __DIR__ . '/alis.php';     // 4.14.0 alış faturası (e-Fatura XML)
require __DIR__ . '/senet.php';    // 4.14.0 tedarikçi senetleri, ödeme takvimi
require __DIR__ . '/cam-hata.php'; // 4.15.0 hatalı cam / yeniden yapım
require __DIR__ . '/sgk-hak.php';  // 4.15.0 SGK hak kontrolü
require __DIR__ . '/garanti.php';  // 4.16.0 garanti kaydı ve garanti kartı
require __DIR__ . '/karsilama.php';

set_exception_handler('handle_fatal');

send_security_headers();
start_session();

/*
 * ÜRÜNLEŞTİRME — çok mağazalı çalışma: her mağaza kendi veritabanında, hepsi bu tek sunucuda.
 * "Merkez sayfaları" (mağaza kaydı/girişi) henüz hangi mağaza olduğunu bilmeden, doğrudan merkez
 * veritabanına bakar. Diğer TÜM sayfalar önce mağaza oturumunu ister; bu satırdan sonra config('db')
 * artık o mağazanın veritabanını gösterir ve geri kalan her şey (db(), run_migrations(), tüm app/pages/*)
 * DEĞİŞMEDEN, tek mağazalıymış gibi çalışmaya devam eder.
 */
$merkezSayfasi = in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['magaza-giris.php', 'kayit.php', 'merkez-panel.php', 'tesekkurler.php'], true);
$anaSayfaMisafir = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'index.php' && !tenant_oturum();

/*
 * SGK köprü uç noktası, oturum çerezine değil kullanıcıya özel köprü
 * anahtarına dayanır (mağaza bilgisayarındaki eklenti gönderir). Çerez
 * kullanılmadığı için CSRF anahtarı beklenmez; yetkilendirme uç noktanın
 * kendi içinde yapılır.
 */
$kopruUcNoktasi = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'sgk-aktar.php'
    && ($_GET['action'] ?? '') === 'kopru';

/*
 * 4.12.0 — Sunucudan sunucuya uç noktalar: oturum ve CSRF YOK; her biri kendi
 * doğrulamasını yapar (PayTR: HMAC imzası; cron: gizli anahtar). Mağaza veritabanını
 * uç noktanın kendisi, doğrulamadan SONRA seçer.
 */
$sunucuUcNoktasi = in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['cron.php', 'odeme-bildirim.php'], true);

/*
 * 4.16.0 — Müşteriye verilen sayfalar (fiş / garanti kartı karekodu): müşterinin mağaza oturumu
 * yoktur. Adreste m=<mağaza no> varsa o mağazanın veritabanı oturum AÇMADAN seçilir; kayıt yalnızca
 * tahmin edilemez anahtarla (k) bulunur. m yoksa eski davranış (personel oturumu) sürer.
 */
$musteriSayfasi = in_array(basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')), ['durum.php', 'bakim.php', 'siparisim-nerede.php', 'garanti.php'], true)
    && ctype_digit((string) ($_GET['m'] ?? '')) && (int) $_GET['m'] > 0;

/* 4.10.0 — OptiFlow Masaüstü JSON uç noktası: hata durumunda HTML/yönlendirme yerine JSON. */
$masaustuApi = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'masaustu.php';
if ($masaustuApi) {
    $GLOBALS['__json_api'] = true;
}

if ($merkezSayfasi || $sunucuUcNoktasi) {
    merkez_db();   // yalnızca merkez (mağazalar) veritabanına bağlanır; henüz hiçbir mağaza/tenant veritabanı seçilmemiştir
} elseif ($kopruUcNoktasi && !tenant_oturum()) {
    kopru_tenant_bagla();   // 4.10.0 eklenti: mağaza, köprü adresindeki &m= ile seçilir (oturumsuz)
    db();
    run_migrations();
} elseif ($musteriSayfasi) {
    $GLOBALS['__musteri_magaza'] = magaza_baglan_id((int) $_GET['m']);
    if (!$GLOBALS['__musteri_magaza']) {
        http_response_code(404);
        render_error_page('Sayfa bulunamadı', 'Bağlantı geçersiz ya da mağaza hesabı şu anda kapalı. Lütfen mağazayla iletişime geçin.');
    }
    db();   // göç YAPILMAZ: şeması eski mağazada yeni tablo yoksa sayfa "bulunamadı" der
} elseif ($masaustuApi && !tenant_oturum()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    http_response_code(401);
    echo json_encode(['ok' => false, 'kod' => 'magaza_oturumu_yok', 'hata' => 'Mağaza oturumu yok'], JSON_UNESCAPED_UNICODE);
    exit;
} elseif (!$anaSayfaMisafir) {
    tenant_gereksin();   // config('db')'yi oturumdaki mağazanın veritabanına çevirir, yoksa magaza-giris.php'ye yönlendirir
    db();
    run_migrations();
}
// $anaSayfaMisafir: oturumu olmayan ziyaretçi kök sayfayı (index.php) açtı — db/tenant hiç gerekmez,
// app/pages/index.php kendisi render_karsilama() ile tanıtım sayfasını gösterip çıkar.

if (is_post() && !$kopruUcNoktasi && !$sunucuUcNoktasi) {
    csrf_check();
}
