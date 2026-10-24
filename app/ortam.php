<?php
declare(strict_types=1);

/* ==========================================================================
   4.56.0 — Beta ortamı (beta.optiflow.com.tr)

   Yayın düzeni: main'e sürüm push'u → "Sürüm yayınla" → BETA'ya yüklenir (pilot mağaza gün içinde dener) →
   her gece (kullanılmayan saatte) en son sürüm CANLIYA geçer (.github/workflows/canliya-al.yml). Rehber: docs/BETA.md.

   Beta ile canlı AYNI veritabanlarını ve aynı şifreleme anahtarını (storage/.gizli-anahtar) kullanır; pilot gerçek
   verisiyle çalışır. Bu yüzden beta'da:
     - yalnız merkez panelinden "Beta kanalı" açılmış mağazalar girebilir (göçler başka mağazanın veritabanına
       canlı kod hazır olmadan uygulanmasın diye kontrol GÖÇTEN ÖNCE yapılır, bootstrap.php),
     - zamanlanmış görevler çalışmaz (WhatsApp / e-posta iki kez gitmesin; cron yalnız canlıda),
     - müşteriye giden bağlantılar (fiş karekodu, WhatsApp) canlı adresle üretilir ve site_base_url ayarı yazılmaz,
     - yeni kayıt alınmaz (canlıya yönlenir), arama motorlarına kapalıdır (noindex, robots-beta.txt).
   Göç kuralı: beta'daki göç canlı veritabanını da değiştirir → göçler GERİYE UYUMLU olmalı (yalnız ekle; sütun
   silme / ad değiştirme iki sürüme bölünür: önce yeni sütunu ekle ve kullan, eskisini sonraki sürümde kaldır).
   ========================================================================== */

/** Bu istek beta ortamında mı? (alan adı beta. ile başlar ya da config.php'de 'ortam' => 'beta') */
function ortam_beta(): bool
{
    if ((string) config('ortam', '') === 'beta') {
        return true;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return str_starts_with($host, 'beta.');
}

/** Canlı alan adı: beta.optiflow.com.tr → optiflow.com.tr (config 'canli_host' önceliklidir). */
function ortam_canli_host(): string
{
    $c = trim((string) config('canli_host', ''));
    if ($c !== '') {
        return $c;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'optiflow.com.tr'));
    $canli = (string) preg_replace('/^beta\./', '', $host);
    return preg_match('/^[a-z0-9][a-z0-9.\-]*\.[a-z]{2,}$/', $canli) ? $canli : 'optiflow.com.tr';
}

/** Beta'da bu mağaza beta kanalında değilse açıklayıcı sayfa (göçlerden ÖNCE çağrılır). */
function beta_kapisi(): void
{
    if (!ortam_beta() || ozellik_acik('beta')) {
        return;
    }
    $canli = 'https://' . ortam_canli_host() . '/';
    if (!empty($GLOBALS['__json_api'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'kod' => 'beta_kapali', 'hata' => 'Bu mağaza beta kanalında değil'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    render_error_page('Bu mağaza beta kanalında değil', 'beta.optiflow.com.tr yalnızca yeni sürümleri önceden deneyen pilot mağazalar içindir. Lütfen ' . $canli . ' adresinden giriş yapın.');
    exit;
}
