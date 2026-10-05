<?php
/* ==========================================================================
   OptiFlow — canlıya yükleme sırasında bakım ekranı (4.16.8)
   --------------------------------------------------------------------------
   GitHub Actions "Canlıya al" iş akışı dosyaları FTP ile yüklemeden ÖNCE kök
   klasöre .guncelleniyor dosyasını koyar, bitince siler. Bu sürede sayfalar
   yarım yüklenmiş / henüz gelmemiş dosyalara denk gelip "Beklenmeyen bir hata"
   vermesin diye kısa bir "1 dakika içinde hazır" sayfası (503) gösterilir.
   20 dakikadan eski işaret yok sayılır (iş akışı yarıda kalırsa site kilitlenmez).
   Bağımlılığı yoktur; bootstrap'in en başında yüklenir.
   ========================================================================== */
declare(strict_types=1);

const GUNCELLEME_ISARETI = '.guncelleniyor';
const GUNCELLEME_AZAMI_SN = 1200;

function guncelleme_suruyor(string $kok, ?int $simdi = null): bool
{
    $f = $kok . '/' . GUNCELLEME_ISARETI;
    clearstatcache(true, $f);           // dosya zamanı önbellekten okunmasın (PHP 8.2'de touch sonrası eski kalıyordu)
    if (!is_file($f)) {
        return false;
    }
    $mt = @filemtime($f);
    return $mt !== false && $mt > ($simdi ?? time()) - GUNCELLEME_AZAMI_SN;
}

/** Makine istemcileri (masaüstü köprüsü, API, zamanlanmış görev, ödeme bildirimi) JSON alır. */
function guncelleme_json_mu(): bool
{
    $betik = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return in_array($betik, ['api.php', 'masaustu.php', 'cron.php', 'odeme-bildirim.php', 'push.php', 'sgk-aktar.php'], true)
        && ($betik !== 'sgk-aktar.php' || isset($_GET['action']))
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

function guncelleme_yaniti(): never
{
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 30');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
    if (guncelleme_json_mu()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'kod' => 'guncelleniyor', 'hata' => 'OptiFlow güncelleniyor. 1 dakika içinde yeniden deneyin.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta http-equiv="refresh" content="20"><meta name="robots" content="noindex"><title>OptiFlow güncelleniyor</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f5fb;font:16px/1.6 system-ui,Arial,sans-serif;color:#0a1033;padding:16px}'
        . 'main{max-width:460px;background:#fff;border:1px solid #e3e6f2;border-radius:16px;padding:28px;text-align:center}'
        . 'h1{margin:0 0 8px;font-size:21px}p{margin:0;color:#454b6b}'
        . '.c{width:34px;height:34px;margin:0 auto 14px;border-radius:50%;border:4px solid #e3e6f2;border-top-color:#2a36ff;animation:d 1s linear infinite}'
        . '@keyframes d{to{transform:rotate(360deg)}}@media (prefers-reduced-motion:reduce){.c{animation:none}}</style></head>'
        . '<body><main><div class="c" aria-hidden="true"></div><h1>OptiFlow güncelleniyor</h1>'
        . '<p>Yeni sürüm yükleniyor; 1 dakika içinde hazır olur. Bu sayfa kendiliğinden yenilenir. Verileriniz etkilenmez.</p></main></body></html>';
    exit;
}
