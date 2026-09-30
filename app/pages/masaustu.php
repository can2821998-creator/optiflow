<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   OptiFlow Masaüstü — köprü API'si (4.10.0)

   Chrome eklentisinin yerini alan masaüstü uygulaması, Medula ekranından
   okuduğu reçeteyi buraya gönderir. Eklentiden farkları:

   • Kimlik: köprü anahtarı YOK. İstek, masaüstü uygulamasının OptiFlow
     oturum çereziyle gelir (kullanıcı uygulamada normal giriş yapmıştır).
     Mağaza bağlamı ve kullanıcı oturumdan gelir; bootstrap tenant_gereksin()
     ile mağazanın kendi veritabanını seçer → mağazalar arası sızıntı olmaz.
   • CSRF: POST, X-CSRF-Token başlığıyla doğrulanır (bootstrap csrf_check).
   • CORS başlığı YOK: yalnızca aynı köken / masaüstü ana süreci çağırabilir.
   • Hesap bağlama: istemci, aktarımdan hemen önce 'durum' ile gördüğü mağaza
     ve kullanıcıyı 'beklenen' olarak gönderir; oturum arada değiştiyse 409.
   • Merkez destek (impersonate) oturumunda aktarım kapalıdır (403).
   • 4.11.0: aktarım yalnızca Pro paketli mağazada açıktır (403 pro_gerekli).

   SGK'ya hiçbir istek atılmaz; kimlik bilgisi alınmaz/saklanmaz. Aktarılan
   metin mevcut sgk_parse() ile çözümlenir ve sgk_incoming'e yazılır; siparişe
   yazma yine sgk-aktar.php önizlemesinde personelin onayıyla olur.
   ========================================================================== */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$cevap = static function (int $http, array $veri): void {
    http_response_code($http);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
};

$me = current_user();
if (!$me) {
    $cevap(401, ['ok' => false, 'kod' => 'oturum_yok', 'hata' => 'Oturum kapalı']);
}
$magaza = tenant_oturum();
if (!$magaza) {
    $cevap(401, ['ok' => false, 'kod' => 'magaza_oturumu_yok', 'hata' => 'Mağaza oturumu yok']);
}
$destekModu = !empty($_SESSION['merkez_impersonate']);
$yontem = $_SERVER['REQUEST_METHOD'] ?? '';
$eylem = query('action');

/* ---------- Durum: kim giriş yapmış + taze CSRF anahtarı ---------- */
if ($eylem === 'durum') {
    if ($yontem !== 'GET') {
        $cevap(405, ['ok' => false, 'kod' => 'yontem', 'hata' => 'Yalnızca GET']);
    }
    $cevap(200, [
        'ok'          => true,
        'api'         => 1,
        'surum'       => APP_VERSION,
        'kullanici'   => ['id' => (int) $me['id'], 'ad' => (string) $me['full_name']],
        'magaza'      => ['id' => (int) $magaza['id'], 'isim' => (string) $magaza['isim']],
        'destek_modu' => $destekModu,
        'paket'       => magaza_paketi(),          // 4.11.0 'lite' | 'pro'
        'pro'         => pro_ozellik_haritasi(),   // özellik → açık mı (Pro paket + masaüstü)
        'ozellikler'  => ozellik_haritasi(),       // 4.12.0 merkezden açılan özellikler
        'csrf'        => csrf_token(),
    ]);
}

/* ---------- Aktar: Medula ekranından okunan metin ---------- */
if ($eylem === 'aktar') {
    if ($yontem !== 'POST') {
        $cevap(405, ['ok' => false, 'kod' => 'yontem', 'hata' => 'Yalnızca POST']);
    }
    if (!str_starts_with(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'application/json')) {
        $cevap(415, ['ok' => false, 'kod' => 'icerik_turu', 'hata' => 'JSON bekleniyor']);
    }
    if ($destekModu) {
        $cevap(403, ['ok' => false, 'kod' => 'destek_modu', 'hata' => 'Destek oturumunda Medula aktarımı kapalıdır']);
    }
    // 4.11.0 — Medula aktarımı OptiFlow Pro özelliğidir (paket sunucuda, merkez veritabanından kontrol edilir).
    if (!pro_ozellik_acik('sgk_kopru')) {
        $cevap(403, ['ok' => false, 'kod' => 'pro_gerekli', 'hata' => 'Medula aktarımı OptiFlow Pro paketindedir']);
    }

    $ham = file_get_contents('php://input', false, null, 0, 300000) ?: '';
    $in = json_decode($ham, true);
    if (!is_array($in)) {
        $cevap(400, ['ok' => false, 'kod' => 'gecersiz', 'hata' => 'Geçersiz istek']);
    }

    // Aktarım, kullanıcının az önce gördüğü mağaza/kullanıcıya bağlıdır.
    $beklenen = is_array($in['beklenen'] ?? null) ? $in['beklenen'] : [];
    if ((int) ($beklenen['magaza_id'] ?? 0) !== (int) $magaza['id'] || (int) ($beklenen['kullanici_id'] ?? 0) !== (int) $me['id']) {
        $cevap(409, ['ok' => false, 'kod' => 'hesap_degisti', 'hata' => 'Oturumdaki hesap değişti']);
    }

    $metin = is_string($in['metin'] ?? null) ? $in['metin'] : '';
    $baslik = mb_substr(trim(is_string($in['baslik'] ?? null) ? $in['baslik'] : ''), 0, 160);
    if (strlen($metin) < 10 || strlen($metin) > 200000 || !mb_check_encoding($metin, 'UTF-8')) {
        $cevap(422, ['ok' => false, 'kod' => 'metin', 'hata' => 'Metin boş ya da çok uzun']);
    }

    // Saatlik kayıt sınırı — eklenti uç noktasıyla aynı (kazara döngüye karşı)
    $sonSaat = (int) scalar('SELECT COUNT(*) FROM sgk_incoming WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [(int) $me['id']]);
    if ($sonSaat > 120) {
        $cevap(429, ['ok' => false, 'kod' => 'cok_fazla', 'hata' => 'Çok fazla aktarım, biraz bekleyin']);
    }

    $cozum = sgk_parse($metin);
    $id = insert('sgk_incoming', [
        'user_id'    => (int) $me['id'],
        'kaynak'     => 'masaustu',
        'baslik'     => $baslik ?: null,
        'raw_text'   => $metin,
        'parsed'     => json_encode($cozum, JSON_UNESCAPED_UNICODE),
        'created_at' => date('Y-m-d H:i:s'),
    ] + sgk_gelen_ek_alanlar($cozum));
    $istemci = is_array($in['istemci'] ?? null) ? $in['istemci'] : [];
    // İşlem geçmişine hasta bilgisi YAZILMAZ; yalnızca kaç alan bulunduğu.
    audit('sgk_masaustu', 'sgk_incoming', $id, [
        'alan'  => (int) $cozum['bulunan'],
        'surum' => mb_substr((string) ($istemci['surum'] ?? ''), 0, 20),
    ]);

    $cevap(200, [
        'ok'       => true,
        'gelen_id' => $id,
        'bulunan'  => $cozum['bulunan'],
        'ozet'     => ($cozum['hasta'] !== '' ? $cozum['hasta'] . ' · ' : '')
            . 'Sağ ' . ($cozum['sag']['sph'] ?: '—') . ' / Sol ' . ($cozum['sol']['sph'] ?: '—'),
        'hedef'    => 'sgk-aktar.php?gelen=' . $id,
    ]);
}

/* ---------- 4.12.0 Medula reçete listesi kontrolü (bekleyen reçeteler) ---------- */
if ($eylem === 'recete_kontrol') {
    if ($yontem !== 'POST') {
        $cevap(405, ['ok' => false, 'kod' => 'yontem', 'hata' => 'Yalnızca POST']);
    }
    if (!ozellik_acik('sgk_mutabakat')) {
        $cevap(403, ['ok' => false, 'kod' => 'ozellik_kapali', 'hata' => 'SGK mutabakat bu mağazada açık değil']);
    }
    $in = json_decode(file_get_contents('php://input', false, null, 0, 500000) ?: '', true);
    if (!is_array($in)) {
        $cevap(400, ['ok' => false, 'kod' => 'gecersiz', 'hata' => 'Geçersiz istek']);
    }
    $beklenen = is_array($in['beklenen'] ?? null) ? $in['beklenen'] : [];
    if ((int) ($beklenen['magaza_id'] ?? 0) !== (int) $magaza['id'] || (int) ($beklenen['kullanici_id'] ?? 0) !== (int) $me['id']) {
        $cevap(409, ['ok' => false, 'kod' => 'hesap_degisti', 'hata' => 'Oturumdaki hesap değişti']);
    }
    // Yapısal okuma (tablodaki "Reçete No" sütunu) varsa o; yoksa metinden biçime göre.
    $numaralar = is_array($in['numaralar'] ?? null) && $in['numaralar']
        ? sgk_numara_listesi_temizle($in['numaralar'])
        : sgk_metinden_numaralar(is_string($in['metin'] ?? null) ? $in['metin'] : '');
    $sonuc = sgk_liste_karsilastir($numaralar);
    sgk_kontrol_kaydet($numaralar, 'masaustu');
    audit('sgk_liste_kontrol', 'sgk', null, ['numara' => count($numaralar), 'kaynak' => 'masaüstü']);
    $cevap(200, [
        'ok'     => true,
        'toplam' => count($numaralar),
        'yok'    => count($sonuc['yok']),
        'var'    => count($sonuc['var']),
        'hedef'  => 'sgk-mutabakat.php?kontrol=1',
    ]);
}

/* ---------- 4.12.0 Çevrimdışı salt okunur kopya ---------- */
if ($eylem === 'ozet') {
    if ($yontem !== 'GET') {
        $cevap(405, ['ok' => false, 'kod' => 'yontem', 'hata' => 'Yalnızca GET']);
    }
    if (!ozellik_acik('cevrimdisi')) {
        $cevap(403, ['ok' => false, 'kod' => 'ozellik_kapali', 'hata' => 'Çevrimdışı mod bu mağazada açık değil']);
    }
    $cevap(200, ['ok' => true] + cevrimdisi_ozet((int) $magaza['id'], $me));
}

$cevap(404, ['ok' => false, 'kod' => 'bilinmeyen', 'hata' => 'Bilinmeyen işlem']);
