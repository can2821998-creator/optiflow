<?php
declare(strict_types=1);

/* "Çerçeve Dene" (sitedeki /deneme/ sanal deneme uygulaması) adresi.

   Deneme uygulaması sitenin köküne yüklenen ayrı bir klasördür (atölyenin yanında, atolye/ dışında).
   Sunucuda o klasör yoksa boş döner; böylece müşteri sayfasında ve fişte ölü bağlantı/karekod çıkmaz.
   Başka bir adres kullanacaksanız Ayarlar tablosuna 'shop_try_url' anahtarı eklenebilir.
   $mutlak = true ise karekod için tam adres (https://alanadi/deneme/) döner. */
function deneme_url(bool $mutlak = false): string
{
    $ozel = trim((string) setting('shop_try_url', ''));
    $yol = $ozel;
    if ($yol === '') {
        /* ÖNEMLİ — çok mağazalı kurulum: bu otomatik algılama yalnızca klasik tek-mağaza kurulumu (mağaza
           oturumu yok) içindir. Çok mağazalı kurulumda (mağazalar/ altında) sunucudaki TEK bir paylaşılan
           /deneme/ klasörü, kendi "Çerçeve Dene" sayfası olmayan her mağazaya otomatik bağlanır ve o
           mağazanın müşterisini BAŞKA bir mağazanın (ör. sistemi ilk kuran mağazanın) deneme sayfasına gönderir.
           Bu yüzden mağaza oturumu varsa otomatik algılama kapalıdır; her mağaza kendi 'shop_try_url'
           değerini Ayarlar'dan girmedikçe bu kart/karekod hiç çıkmaz. */
        if (function_exists('tenant_oturum') && tenant_oturum() !== null) {
            return '';
        }
        if (!is_file(dirname(APP_ROOT) . '/deneme/index.html')) {
            return '';
        }
        $yol = '/deneme/';
    }
    if ($mutlak && !preg_match('#^https?://#i', $yol)) {
        $kok = app_base_url();
        $host = $kok !== '' ? (string) preg_replace('#^(https?://[^/]+).*$#', '$1', $kok) : '';
        return $host !== '' ? $host . '/' . ltrim($yol, '/') : '';
    }
    return $yol;
}
