<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.30.0 — Telefon asistanı uç noktası (Android "OptiFlow Asistan" uygulaması konuşur).
   Oturum ve CSRF YOK (bootstrap: sunucu uç noktası). Mağaza &m= ile seçilir; her istek
   cihaza özel anahtarla (X-Asistan-Anahtar) doğrulanır. Eşleştirme: tek kullanımlık 6 haneli kod.
     GET  ?eylem=qr&m=&k=      telefonda açılan eşleştirme sayfası (uygulamayı açan bağlantı)
     POST ?eylem=bagla&m=      kod, cihaz            → anahtar
     GET  ?eylem=ayar&m=                              → açık mı, çaldırma süresi, mağaza adı
     POST ?eylem=basla&m=      numara                 → söylenecek metin + oturum
     POST ?eylem=cevap&m=      oturum, metin, tus     → söylenecek metin
     POST ?eylem=bitti&m=      oturum, sure, olay
     POST ?eylem=olay&m=       metin (uygulama tanılama kaydı; sayfada son olaylar)
   ========================================================================== */

$eylem = (string) ($_GET['eylem'] ?? '');
$mid = (int) ($_GET['m'] ?? 0);

$json = static function (array $v, int $kod = 200): never {
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($v, JSON_UNESCAPED_UNICODE);
    exit;
};

$magaza = magaza_baglan_id($mid);
$ozellikVar = $magaza && in_array('telefon_asistan', ozellik_listesi_temizle($magaza['ozellikler'] ?? ''), true);

if ($eylem === 'qr') {
    // Telefonun kamerasıyla okutulan karekod buraya gelir; düğme uygulamayı açar.
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    $k = preg_match('/^\d{6}$/', (string) ($_GET['k'] ?? '')) ? (string) $_GET['k'] : '';
    $u = rtrim(app_base_url(), '/');
    $link = 'optiflow-asistan://bagla?' . http_build_query(['u' => $u, 'm' => $mid, 'k' => $k]);
    $ad = $magaza ? (string) $magaza['isim'] : 'OptiFlow';
    echo '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>Telefon asistanını bağla</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#160b0f;color:#fff;font:16px/1.5 system-ui,sans-serif;text-align:center;padding:24px}'
        . 'a{display:inline-block;margin-top:18px;padding:16px 26px;border-radius:14px;background:linear-gradient(140deg,#d0334f,#8f1a2e);color:#fff;font-weight:700;text-decoration:none}'
        . 'b{font-size:28px;letter-spacing:4px}small{display:block;margin-top:18px;opacity:.7}</style></head><body><main>'
        . '<h1 style="font-size:22px">' . e($ad) . ' · Telefon asistanı</h1>'
        . ($ozellikVar && $k !== '' ? '<p>Bu telefonda <b>OptiFlow Asistan</b> uygulaması kuruluysa aşağıdaki düğmeye dokunun.</p><a href="' . e($link) . '">Uygulamayı bağla</a>'
            . '<small>Olmazsa uygulamada "Kodla bağla"ya mağaza no <b style="font-size:16px;letter-spacing:0">' . $mid . '</b> ve kod <b style="font-size:16px">' . e($k) . '</b> girin.</small>'
            : '<p>Bağlantı geçersiz ya da telefon asistanı bu mağazada açık değil.</p>')
        . '</main></body></html>';
    exit;
}

if (!$magaza) {
    $json(['ok' => false, 'hata' => 'Mağaza bulunamadı ya da hesap kapalı.'], 404);
}
if (!$ozellikVar) {
    $json(['ok' => false, 'hata' => 'Telefon asistanı bu mağazada açık değil (merkez panelden açılır).', 'kapali' => true], 403);
}
db();
run_migrations();

if ($eylem === 'bagla') {
    if (merkez_hiz_asildi('asistan_bagla', 'm' . $mid, 10, 10, 900)) {
        $json(['ok' => false, 'hata' => 'Çok fazla deneme. 15 dakika sonra tekrar deneyin.'], 429);
    }
    $anahtar = asistan_bagla((string) ($_POST['kod'] ?? ''), (string) ($_POST['cihaz'] ?? ''));
    if ($anahtar === null) {
        merkez_hiz_kaydet('asistan_bagla', 'm' . $mid);
        $json(['ok' => false, 'hata' => 'Kod yanlış ya da süresi dolmuş. OptiFlow › Telefon asistanı sayfasından yeni kod alın.'], 401);
    }
    $json(['ok' => true, 'anahtar' => $anahtar, 'magaza' => setting('shop_name', 'OptiFlow')]);
}

$basliklar = function_exists('getallheaders') ? array_change_key_case((array) getallheaders(), CASE_LOWER) : [];
$cihazAnahtar = (string) ($basliklar['x-asistan-anahtar'] ?? ($_SERVER['HTTP_X_ASISTAN_ANAHTAR'] ?? ''));
$cihaz = asistan_cihaz_dogrula($cihazAnahtar);
if (!$cihaz) {
    $json(['ok' => false, 'hata' => 'Telefon bu mağazaya bağlı değil ya da bağlantısı kaldırıldı. Yeniden bağlayın.', 'yeniden_bagla' => true], 401);
}
if (isset($_SERVER['HTTP_X_ASISTAN_SURUM'])) {
    update('asistan_cihazlar', ['son_surum' => mb_substr((string) $_SERVER['HTTP_X_ASISTAN_SURUM'], 0, 20)], 'id = ?', [(int) $cihaz['id']]);
}

switch ($eylem) {
    case 'ayar':
        $json(['ok' => true, 'acik' => asistan_acik_mi(), 'bekleme_sn' => asistan_bekleme_sn(), 'magaza' => setting('shop_name', 'OptiFlow'),
            'karsilama' => asistan_karsilama()]);
    case 'basla':
        if (!asistan_acik_mi()) {
            $json(['ok' => false, 'kapali' => true, 'hata' => 'Asistan OptiFlow\'da kapalı.']);
        }
        $json(asistan_basla((string) ($_POST['numara'] ?? ''), (int) $cihaz['id']));
    case 'cevap':
    case 'bitti':
        $arama = asistan_arama_bul((string) ($_POST['oturum'] ?? ''));
        if (!$arama) {
            $json(['ok' => false, 'hata' => 'Görüşme bulunamadı.'], 404);
        }
        if ($eylem === 'bitti') {
            asistan_bitti($arama, (int) ($_POST['sure'] ?? 0), (string) ($_POST['olay'] ?? ''));
            $json(['ok' => true]);
        }
        $metin = isset($_POST['metin']) ? mb_substr((string) $_POST['metin'], 0, 500) : null;
        $tus = isset($_POST['tus']) ? (preg_replace('/[^\d#*]/', '', (string) $_POST['tus']) ?? '') : null;
        $json(asistan_cevap($arama, $metin, $tus));
    case 'olay':
        $kayit = json_decode(setting('asistan_olaylar', '[]'), true) ?: [];
        array_unshift($kayit, [date('d.m H:i:s'), mb_substr((string) ($_POST['metin'] ?? ''), 0, 200)]);
        setting_set('asistan_olaylar', json_encode(array_slice($kayit, 0, 30), JSON_UNESCAPED_UNICODE));
        $json(['ok' => true]);
}
$json(['ok' => false, 'hata' => 'Bilinmeyen istek.'], 400);
