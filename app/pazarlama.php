<?php
declare(strict_types=1);

/**
 * OptiFlow — PAZARLAMA AYARLARI
 * ---------------------------------------------------------------------------
 * Karşılama sayfası (ana sayfa), teşekkür sayfası ve KVKK sayfası bu dosyadaki
 * bilgileri kullanır. Veritabanına dokunmaz; yalnızca bu dosyayı düzenleyin.
 *
 * KURAL: Boş bırakılan alan sayfada HİÇ görünmez (ör. whatsapp boşsa WhatsApp
 * düğmeleri çıkmaz, fiyat boşsa "Fiyat için bize yazın" yazar). Böylece
 * yarım/yer tutucu bilgi hiçbir zaman ziyaretçiye gösterilmez.
 */
function optiflow_pazarlama(): array
{
    static $ayar = null;
    if ($ayar !== null) {
        return $ayar;
    }
    $ayar = [
        // --- İLETİŞİM --------------------------------------------------------
        'sirket_unvani' => 'Poyraz Optik',     // Yasal unvan — KVKK sayfasında "veri sorumlusu" olarak geçer. Örn: 'OptiFlow Yazılım Ltd. Şti.'
        'adres'         => 'Dumlupınar Mahallesi, Ulucami Caddesi No: 2/B', // Açık adres (KVKK sayfası ve alt bilgi)
        'telefon'       => '0546 743 82 99',   // Görünen biçim. Örn: '0850 000 00 00'
        'whatsapp'      => '905467438299',     // Yalnızca rakam, ülke koduyla. Örn: '905321112233'
        'eposta'        => '',                 // Örn: 'merhaba@optiflow.com.tr'
        'kvkk_eposta'   => '',                 // KVKK başvuruları için (boşsa 'eposta' kullanılır)
        'instagram'     => 'optiflowtr',       // Kullanıcı adı, @ olmadan. Örn: 'optiflow.tr'
        'demo_video'    => '',                 // YouTube vb. tam bağlantı. Boşsa "Videoyu izleyin" düğmesi çıkmaz.

        // --- ÖLÇÜMLEME -------------------------------------------------------
        'ga4_id'        => 'G-MYLZP3G755',     // Google Analytics 4 ölçüm kimliği. Boşsa hiçbir yere GA kodu eklenmez.
        'google_dogrulama' => '',              // Google Search Console › HTML etiketi yöntemi: content="..." içindeki kod (yalnızca kod)
        'bing_dogrulama'   => '',              // Bing Webmaster Tools › meta etiketi (msvalidate.01) kodu
                                               // NOT: Yalnızca genel (giriş öncesi) sayfalara eklenir; hasta/reçete verisi
                                               // gösteren panel sayfalarına KVKK gereği eklenmez.

        // --- HİZMET SÖZLERİ (ekibin gerçekten verebildiği kadarını açık tutun) --
        'kurulum_destegi'     => true,         // "Kurulumu sizinle birlikte yapıyoruz" vurgusu
        'veri_aktarim_destegi'=> true,         // "Eski müşteri listenizi (Excel) biz aktaralım" vurgusu

        // --- KURUCU MAĞAZA KAMPANYASI -------------------------------------------
        'kampanya' => [
            'aktif'  => false,
            'baslik' => 'Kurucu mağaza avantajı',
            'metin'  => 'İlk 50 mağazaya, abonelik süresince geçerli indirimli fiyat.',
        ],

        // --- FİYATLAR --------------------------------------------------------
        // 'fiyat' boşsa kartta "Fiyat için bize yazın" görünür.
        // 'aylik' (rakam, TL) doluysa kart onu gösterir; 'yillik_indirim' (%) ile yıllık tutar hesaplanır.
        'yillik_indirim' => 20,                 // Yıllık ödemede indirim yüzdesi. 0 ise yıllık satırı çıkmaz.
        'paketler' => [
            [
                'ad'       => 'Lite',
                'aylik'    => 499,
                'fiyat'    => '',
                'donem'    => '/ ay · KDV dahil',
                'aciklama' => 'Tarayıcıda, her cihazda. Kurulum yok; bilgisayar, tablet ve telefondan açılır.',
                'vurgu'    => false,
                'ozellikler' => [
                    'Sipariş, müşteri, reçete geçmişi, atölye panosu',
                    'SGK katkı payı ve ay sonu SGK faturası',
                    'WhatsApp bildirimleri ve "Siparişim nerede?"',
                    'Kasa, bakiye, kâr raporu, stok ve etiket',
                    'Garanti kartı, hızlı satış, ödeme linki',
                    'Sınırsız müşteri ve sipariş',
                ],
            ],
            [
                'ad'       => 'Pro',
                'aylik'    => 999,
                'fiyat'    => '',
                'donem'    => '/ ay · KDV dahil',
                'aciklama' => "Windows'ta, Medula'nın yanında. Reçete tek tuşla siparişe gelir.",
                'vurgu'    => true,
                'ozellikler' => [
                    "Lite'taki her şey",
                    'Medula reçetesini tek tuşla siparişe aktarma',
                    'Medula ekranından tek tuşla SGK hak kontrolü',
                    'ÜTS karekod etiketi ve imha dosyası',
                    'USB barkod / karekod okuyucu',
                    'İnternet kesilince açık siparişleri görme',
                ],
            ],
            [
                'ad'       => 'Çok Şubeli',
                'fiyat'    => '',               // Örn: '990 ₺'
                'donem'    => '/ şube / ay · KDV dahil',
                'aciklama' => 'Zincirler için şube yönetimi geliştiriliyor. Ön kayıt için bize yazın; hazır olunca ilk siz kullanın.',
                'vurgu'    => false,
                'yakinda'  => true,             // şube özelliği yayına girince kaldırın
                'ozellikler' => [
                    "Pro paketindeki her şey",
                    'Şube başına ayrı kullanıcı ve yetki',
                    'Merkezden tüm siparişleri görme',
                    'Şube ve personel bazında raporlar',
                    'Personel prim hesabı',
                    'Öncelikli destek',
                ],
            ],
        ],
    ];
    return $ayar;
}

/** Karşılama/KVKK sayfaları için güvenli yazdırma (uygulamanın e() fonksiyonundan bağımsız). */
function pz_e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** TL tutarını Türkçe biçimde yazar: 4790 → '4.790 ₺'. */
function pz_tl(int $tutar): string
{
    return number_format($tutar, 0, ',', '.') . ' ₺';
}

/**
 * Paketin fiyat bilgisi: ['aylik' => 499, 'yillik' => 4790, 'yillik_aylik' => 399] ya da aylık yoksa null.
 * Yıllık tutar aşağı yuvarlanır (4.790,40 → 4.790) ki ilan edilen indirim her zaman en az söylenen kadar olsun.
 */
function pz_paket_fiyat(array $pk): ?array
{
    $aylik = (int) ($pk['aylik'] ?? 0);
    if ($aylik <= 0) {
        return null;
    }
    $indirim = max(0, min(90, (int) (optiflow_pazarlama()['yillik_indirim'] ?? 0)));
    $yillik  = $indirim > 0 ? (int) floor($aylik * 12 * (100 - $indirim) / 100) : 0;
    return ['aylik' => $aylik, 'yillik' => $yillik, 'yillik_aylik' => $yillik > 0 ? (int) floor($yillik / 12) : 0, 'indirim' => $indirim];
}

/** WhatsApp bağlantısı (numara ayarlı değilse boş döner). */
function pz_whatsapp(string $mesaj = 'Merhaba, OptiFlow için kısa bir demo istiyorum.'): string
{
    $no = preg_replace('/\D+/', '', optiflow_pazarlama()['whatsapp']);
    return $no === '' ? '' : 'https://wa.me/' . $no . '?text=' . rawurlencode($mesaj);
}

/** Telefon bağlantısı (tel:) — numara ayarlı değilse boş döner. */
function pz_tel(): string
{
    $no = preg_replace('/[^\d+]/', '', optiflow_pazarlama()['telefon']);
    return $no === '' ? '' : 'tel:' . $no;
}

/** OptiFlow monogramı (inline SVG). $tek: tek renk (ör. 'currentColor'); boşsa bordo gradyan (4.19.0). */
function pz_logo(string $id = 'pzLogo', string $tek = ''): string
{
    $fill = $tek !== '' ? $tek : 'url(#' . $id . ')';
    $defs = $tek !== '' ? '' : '<defs><linearGradient id="' . $id . '" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse">'
        . '<stop offset="0" stop-color="#ff6b81"/><stop offset=".55" stop-color="#d0334f"/><stop offset="1" stop-color="#8f1a2e"/></linearGradient></defs>';
    return '<svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' . $defs
        . '<g transform="rotate(-10 50 50)"><path fill-rule="evenodd" clip-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z" fill="' . $fill . '"/><circle cx="50" cy="50" r="7" fill="' . $fill . '"/></g></svg>';
}

/** Arama motoru site doğrulama etiketleri (Search Console / Bing). Kod ayarlı değilse boş. */
function pz_dogrulama_meta(): string
{
    $p = optiflow_pazarlama();
    require_once __DIR__ . '/seo.php';
    $panel = seo_dogrulama_kodlari();       // Merkez panel › SEO · Google
    $out = '';
    foreach (['google-site-verification' => 'google_dogrulama', 'msvalidate.01' => 'bing_dogrulama'] as $ad => $k) {
        $kod = trim((string) ($p[$k] ?? '')) ?: $panel[$k];
        if ($kod !== '' && preg_match('/^[A-Za-z0-9_\-]{10,100}$/', $kod)) {
            $out .= '<meta name="' . $ad . '" content="' . pz_e($kod) . '">' . "\n";
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/*  Google Analytics 4 (ölçümleme)                                     */
/* ------------------------------------------------------------------ */

/** Ayarlı GA4 ölçüm kimliği (yoksa boş). Yalnızca G-XXXX biçimini kabul eder. */
function ga4_id(): string
{
    $id = trim((string) (optiflow_pazarlama()['ga4_id'] ?? ''));
    return preg_match('/^G-[A-Z0-9]{4,20}$/', $id) ? $id : '';
}

/**
 * GA4 için Content-Security-Policy'ye eklenmesi gereken kaynaklar.
 * Dönen: ['script'=>..., 'connect'=>..., 'img'=>...] (id ayarlı değilse hepsi boş).
 * Bu sayede CSP tek yerde, tutarlı biçimde genişletilir; 'unsafe-inline' gerekmez.
 */
function ga_csp(): array
{
    if (ga4_id() === '') {
        return ['script' => '', 'connect' => '', 'img' => ''];
    }
    return [
        'script'  => ' https://www.googletagmanager.com',
        'connect' => ' https://www.googletagmanager.com https://www.google-analytics.com https://region1.google-analytics.com https://analytics.google.com',
        'img'     => ' https://www.googletagmanager.com https://www.google-analytics.com https://region1.google-analytics.com',
    ];
}

/**
 * <head> içine yerleştirilecek GA4 etiketleri. Başlatma kodu harici assets/ga4.js'tedir
 * (satır içi JavaScript YOK → sıkı CSP korunur). Kimlik ayarlı değilse boş döner.
 */
function ga_head(): string
{
    $id = ga4_id();
    if ($id === '') {
        return '';
    }
    $v = defined('APP_VERSION') ? APP_VERSION : '1';
    return "\n<!-- Google tag (gtag.js) -->\n"
        . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . rawurlencode($id) . '"></script>' . "\n"
        . '<script defer src="assets/ga4.js?v=' . pz_e($v) . '" data-ga4="' . pz_e($id) . '"></script>' . "\n";
}
