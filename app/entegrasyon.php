<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Dış servis ortak yardımcıları (4.12.0)

   1) Gizli ayarlar: PayTR anahtarı, WhatsApp erişim anahtarı gibi değerler
      veritabanına AÇIK METİN yazılmaz. storage/.gizli-anahtar dosyasındaki
      kurulum anahtarıyla şifrelenir (libsodium secretbox; yoksa AES-256-GCM).
      Veritabanı yedeği ele geçse bile bu değerler anahtar dosyası olmadan
      okunamaz. storage/ klasörü web'den erişime kapalıdır (.htaccess) ve
      güncellemelerde korunur.

   2) dis_istek(): TLS doğrulaması AÇIK, kısa zaman aşımı, yanıt boyutu sınırlı
      tek HTTP istemcisi. Tüm dış çağrılar buradan geçer.
   ========================================================================== */

function gizli_anahtar_yolu(): string
{
    return APP_ROOT . '/storage/.gizli-anahtar';
}

/** 32 baytlık kurulum anahtarı; yoksa bir kez üretilir. */
function gizli_anahtar(): string
{
    static $anahtar = null;
    if ($anahtar !== null) {
        return $anahtar;
    }
    $yol = gizli_anahtar_yolu();
    if (is_file($yol)) {
        $ham = base64_decode(trim((string) file_get_contents($yol)), true);
        if ($ham !== false && strlen($ham) === 32) {
            return $anahtar = $ham;
        }
        throw new RuntimeException('storage/.gizli-anahtar bozuk. Dosyayı yedekten geri yükleyin (silerseniz kayıtlı entegrasyon şifrelerini yeniden girmeniz gerekir).');
    }
    $dizin = dirname($yol);
    if (!is_dir($dizin) || !is_writable($dizin)) {
        throw new RuntimeException('storage/ klasörü yazılabilir değil; entegrasyon anahtarları kaydedilemez.');
    }
    $yeni = random_bytes(32);
    // Atomik oluşturma: önce geçici dosyaya tam yazılır, sonra link() ile yerine konur (hedef varsa başarısız olur).
    // Böylece eşzamanlı ikinci istek hiçbir zaman yarım yazılmış dosya okumaz.
    $gecici = $yol . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($gecici, base64_encode($yeni), LOCK_EX) === false) {
        throw new RuntimeException('storage/ klasörüne yazılamadı; entegrasyon anahtarları kaydedilemez.');
    }
    @chmod($gecici, 0600);
    $tamam = @link($gecici, $yol);
    @unlink($gecici);
    clearstatcache(true, $yol);
    if (!$tamam) {
        if (is_file($yol)) {
            return gizli_anahtar();   // başka istek önce oluşturdu
        }
        // link() desteklenmeyen dosya sistemi: rename (kısa yarış penceresi kabul)
        if (@file_put_contents($yol, base64_encode($yeni), LOCK_EX) === false) {
            throw new RuntimeException('storage/.gizli-anahtar yazılamadı.');
        }
        @chmod($yol, 0600);
    }
    return $anahtar = $yeni;
}

/** Açık metni şifreler → "g1:" önekiyle base64 metin. */
function gizli_sifrele(string $acik): string
{
    $k = gizli_anahtar();
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 'g1:' . base64_encode($nonce . sodium_crypto_secretbox($acik, $nonce, $k));
    }
    $iv = random_bytes(12);
    $etiket = '';
    $sifreli = openssl_encrypt($acik, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $etiket);
    if ($sifreli === false) {
        throw new RuntimeException('Şifreleme yapılamadı.');
    }
    return 'g2:' . base64_encode($iv . $etiket . $sifreli);
}

/** Şifreyi çözer; çözülemezse '' (anahtar değişmiş / veri bozuk). */
function gizli_coz(string $sifreli): string
{
    if ($sifreli === '') {
        return '';
    }
    try {
        $k = gizli_anahtar();
    } catch (Throwable $e) {
        return '';
    }
    $ham = base64_decode(substr($sifreli, 3), true);
    if ($ham === false) {
        return '';
    }
    if (str_starts_with($sifreli, 'g1:') && function_exists('sodium_crypto_secretbox_open')) {
        if (strlen($ham) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return '';
        }
        $acik = sodium_crypto_secretbox_open(substr($ham, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($ham, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $k);
        return $acik === false ? '' : $acik;
    }
    if (str_starts_with($sifreli, 'g2:') && strlen($ham) > 28) {
        $acik = openssl_decrypt(substr($ham, 28), 'aes-256-gcm', $k, OPENSSL_RAW_DATA, substr($ham, 0, 12), substr($ham, 12, 16));
        return $acik === false ? '' : $acik;
    }
    return '';
}

/** Gizli ayarı okur (çözülmüş). */
function gizli_ayar(string $anahtar): string
{
    return gizli_coz(setting($anahtar, ''));
}

/** Gizli ayarı yazar. Boş değer = sil. */
function gizli_ayar_yaz(string $anahtar, string $deger): void
{
    setting_set($anahtar, $deger === '' ? '' : gizli_sifrele($deger));
}

/** Ekranda gösterim: yalnızca son 4 karakter. */
function gizli_maske(string $deger): string
{
    if ($deger === '') {
        return '';
    }
    return str_repeat('•', 8) . mb_substr($deger, -4);
}

/**
 * Dış HTTP isteği. Dönüş: ['durum' => int (0 = bağlantı hatası), 'govde' => string, 'hata' => string].
 * $govde dizi ise form (x-www-form-urlencoded), metin ise olduğu gibi gönderilir.
 */
function dis_istek(string $yontem, string $url, array $basliklar = [], array|string|null $govde = null, int $zamanAsimi = 20): array
{
    if (!str_starts_with($url, 'https://')) {
        return ['durum' => 0, 'govde' => '', 'hata' => 'Yalnızca https adreslerine istek yapılır.'];
    }
    if (!function_exists('curl_init')) {
        return ['durum' => 0, 'govde' => '', 'hata' => 'Sunucuda PHP cURL eklentisi yok (Plesk › PHP Ayarları).'];
    }
    $ch = curl_init($url);
    $h = [];
    foreach ($basliklar as $ad => $deger) {
        $h[] = $ad . ': ' . $deger;
    }
    $alinan = '';
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => strtoupper($yontem),
        CURLOPT_HTTPHEADER     => $h,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => $zamanAsimi,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_USERAGENT      => 'OptiFlow/' . APP_VERSION,
        CURLOPT_WRITEFUNCTION  => static function ($c, string $parca) use (&$alinan): int {
            if (strlen($alinan) + strlen($parca) > 2_000_000) {
                return 0; // 2 MB üstü yanıtı kes
            }
            $alinan .= $parca;
            return strlen($parca);
        },
    ]);
    if ($govde !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($govde) ? http_build_query($govde) : $govde);
    }
    $ok = curl_exec($ch);
    $durum = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hata = $ok === false ? curl_error($ch) : '';
    curl_close($ch);
    return ['durum' => $ok === false ? 0 : $durum, 'govde' => $alinan, 'hata' => $hata];
}
