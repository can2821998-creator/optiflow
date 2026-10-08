<?php
declare(strict_types=1);

require_once __DIR__ . '/pazarlama.php';
require_once __DIR__ . '/indir.php';

/**
 * OptiFlow karşılama (tanıtım) sayfası — v5 (4.17.0)
 * Tasarım fikri: göz eşeli. Başlık, muayenedeki Snellen tablosu gibi satır satır küçülür;
 * yanında ondalık görme keskinliği (0,1 … 1,0) yazar ve sayfa açılırken satırlar sırayla netleşir.
 *
 * Tenant/veritabanı bağlamı yoktur (oturumu olmayan herkes görür) — DB fonksiyonu çağırmaz.
 * İletişim, fiyat ve kampanya bilgileri app/pazarlama.php'den gelir; boş alanlar sayfada görünmez.
 * CSP 'script-src self': satır içi JavaScript YOK (SSS <details> ile çalışır, netleşme CSS animasyonudur).
 * Tek dış betik: assets/karsilama-sahne.js (giriş efektleri, kaydırmaya bağlı bölümler, foropter sahnesi, sesli izleme).
 */
function render_karsilama(): void
{
    $p     = optiflow_pazarlama();
    $wa    = pz_whatsapp();
    $tel   = pz_tel();
    $mail  = $p['eposta'];
    $kamp  = $p['kampanya'];
    $video = $p['demo_video'];
    $indir = indir_masaustu_bilgi();       // OptiFlow Pro kurulum dosyası (indir/masaustu/latest.yml)

    // İletişim düğmesi önceliği: WhatsApp → telefon → e-posta
    $iletisimUrl = $wa ?: ($tel ?: ($mail !== '' ? 'mailto:' . $mail . '?subject=' . rawurlencode('OptiFlow demo') : ''));
    $iletisimAd  = $wa ? "WhatsApp'tan demo isteyin" : ($tel ? 'Arayın, gösterelim' : ($mail !== '' ? 'Demo isteyin' : ''));

    $sss = [
        ['Medula ile nasıl çalışıyor, SGK şifremi istiyor mu?',
         "Hayır. OptiFlow Pro, Medula Optik'i ve OptiFlow'u tek pencerede açan Windows uygulamasıdır. Medula'ya kendiniz girersiniz; isterseniz Chrome'daki gibi \"Kaydet\" dersiniz, sonraki girişlerde kullanıcı adı ve şifre kendiliğinden yazılır, güvenlik kodunu siz girersiniz. Şifre yalnızca o bilgisayarda, Windows şifrelemesiyle saklanır; OptiFlow sunucusuna gönderilmez. Reçeteyi açıp \"Aktar\"a bastığınızda yalnızca o an ekranda görünen reçete OptiFlow'a gelir."],
        ['SGK katkı payı ve ay sonu faturası kesin mi?',
         "Reçetenin kullanım şekline göre katkı payı önerilir, siparişte düzenlenebilir. Ay sonunda Medula'ya işlenen reçeteler tek SGK faturasında toplanır; Medula'dan aldığınız dökümü yükleyip adetleri karşılaştırırsınız. Kesin tutarı her zaman Medula belirler."],
        ['Verilerimiz güvende mi?',
         'Her mağaza kendi ayrı veritabanında çalışır; bir mağazanın müşteri, reçete ya da SGK bilgisi başka bir mağazayla asla paylaşılmaz. T.C. kimlik numarası saklanmaz. Veritabanınızın yedeğini istediğiniz an tek tıkla indirebilirsiniz.'],
        ['Başka bir programdan ya da defterden geçiyorum. Eski müşterilerim ne olacak?',
         $p['veri_aktarim_destegi']
            ? "Müşteri listenizi Excel ya da başka bir biçimde bize gönderin, OptiFlow'a biz aktaralım. İlk günden eski müşterilerinizle çalışmaya başlarsınız."
            : 'Müşterileriniz ilk siparişlerinde sisteme eklenir; reçete ve gözlük geçmişi o andan itibaren birikir.'],
        ['Birden fazla şubem var, her biri ayrı mı çalışır?',
         'Şube yönetimi geliştiriliyor: şubeye göre kullanıcı ve yetki, merkezden tüm şubeleri görme ve şubeler arası stok transferi. Şimdilik her şube ayrı bir mağaza hesabıyla çalışır.'],
        ['Telefon ve tablette çalışır mı? Bir şey kurmam gerekir mi?',
         "OptiFlow tarayıcıda çalışır; bilgisayar, tablet ve telefonda açılır, telefonun ana ekranına uygulama gibi eklenir. Medula aktarımı ve ÜTS karekod okuyucu için mağaza bilgisayarına OptiFlow Pro'yu kurarsınız; güncellemeleri kendisi alır."],
        ['Kredi kartı bilgisi vermem gerekiyor mu?',
         'Hayır. 30 günlük deneme kart bilgisi olmadan başlar. Deneme bittiğinde erişim durur; devam etmek isterseniz sizinle iletişime geçeriz.'],
        ['Kurulum ne kadar sürer?',
         $p['kurulum_destegi']
            ? 'Başvurunuzu bıraktıktan sonra mağazanızı biz açar, sizi arayıp ilk ayarları birlikte yaparız: mağaza adınız, renginiz ve kullanıcılarınız. Teknik bilgi gerekmez.'
            : 'Mağaza adı, e-posta ve bir yönetici hesabı yeterli; hesabınız etkinleştirildiğinde kendi panelinize girersiniz.'],
    ];

    $faqLd = [];
    foreach ($sss as [$s, $c]) {
        $faqLd[] = ['@type' => 'Question', 'name' => $s, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $c]];
    }
    $ldJson = static fn (array $v): string => (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

    $ico = [
        'lock'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
        'db'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
        'users'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/></svg>',
        'screen' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
        'down'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M6 11l6 6 6-6M4 21h16"/></svg>',
        'wa'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg>',
    ];

    // Göz eşeli: [metin, ondalık görme keskinliği]
    $esel = [
        ['Reçete', '0,1'],
        ["Medula'dan", '0,2'],
        ['tek tıkla siparişe,', '0,4'],
        ['sipariş atölyeye,', '0,6'],
        ['gözlük müşteriye.', '0,8'],
    ];

    // Bir siparişin yolu (gerçek sıra)
    $yol = [
        ['Reçete gelir', "OptiFlow Pro'da Medula'nın hemen yanında \"Aktar\". Kutulardaki sferik, silendirik ve aks doğru sırayla okunur, yakından ADD hesaplanır."],
        ['Sipariş açılır', 'Cam, çerçeve ve fiyat tek ekranda. SGK katkı payı ve kalan bakiye kendiliğinden hesaplanır, SGK hakkı tarihiyle görünür.'],
        ['Atölye çalışır', 'Cam bekleniyor, montajda, kontrolde, hazır. Atölye panosunda ve duvardaki ekranda tek bakışta.'],
        ['Müşteri haber alır', 'Gözlük hazır olunca WhatsApp gider; fişteki karekodla müşteri durumu telefonundan görür. Teslimde garanti kartı basılır.'],
        ['Ay kapanır', "Medula'ya işlenen reçeteler tek SGK faturasında toplanır. Medula dökümünü yükleyip adetleri karşılaştırırsınız."],
    ];

    // Neler yapar: işe göre gruplar
    $gruplar = [
        ['SGK ve mevzuat', [
            ['Medula aktarımı', 'Reçete OptiFlow Pro ile tek tuşla siparişe gelir; elle yazım yok.'],
            ['SGK hak kontrolü', 'Müşterinin bir sonraki gözlük hakkı tarihi siparişte görünür.'],
            ['SGK ay sonu faturası', 'Ayın reçeteleri tek faturada, reçete dökümüyle birlikte.'],
            ['ÜTS karekod', 'Çerçeve ve camın karekodunu okutun; mal kabul ve satış bildirimi hazır.'],
            ['e-Fatura hazırlığı', 'Siparişten fatura taslağı ve UBL-TR belgesi; entegratör bağlanınca gönderilir.'],
        ]],
        ['Müşteri geri gelsin', [
            ['Akıllı hatırlatmalar', 'Gözlük zamanı gelen, SGK hakkı doğan, hazır gözlüğünü almayan müşteriler her gün listede.'],
            ['WhatsApp mesajları', '"Gözlüğünüz hazır" ve hatırlatmalar kuyruktan gider; metinleri siz yazarsınız.'],
            ['Garanti kartı', 'Karekodlu kart; müşteri kalan süreyi telefonundan görür, tamir geçmişi kayıtlı.'],
            ['Siparişim nerede?', 'Fişteki karekodu okutan müşteri, gözlüğünün hangi aşamada olduğunu görür.'],
            ['Kontakt lens takibi', 'Kutu bitmeden müşteriye tekrar sipariş hatırlatması.'],
        ]],
        ['Paranın nerede olduğu', [
            ['Hızlı satış', 'Güneş gözlüğü, solüsyon, lens, aksesuar: barkodu okutun, nakit + kart ödemeyi alın; sipariş açmadan, stok kendiliğinden düşer.'],
            ['Kâr raporu', 'Sipariş ve cam tipine göre gerçek kâr; hatalı camın maliyeti düşülmüş.'],
            ['Gün sonu kasa', 'Nakit sayımı, küçük satışlar ve harcamalar; kasa farkının nedeni ortada.'],
            ['Bakiye takibi', 'Söz verilen ödeme tarihi geçen müşteriler ayrı listede.'],
            ['Tedarikçi ve alış faturası', "e-Fatura XML'i yükleyin; stok, maliyet ve cari kendiliğinden işlensin. Senet vadeleri takvimde."],
            ['Ödeme linki', 'Kalan bakiye için müşteriye kartla ödeme linki gönderin.'],
        ]],
        ['Ekip ve şubeler', [
            ['Atölye ekranı', 'Duvardaki ekranda canlı iş listesi; fiyat, telefon ve reçete göstermez.'],
            ['Çok şube (yakında)', 'Şubeye göre kullanıcı ve yetki, merkezden tüm şubeleri görme. Şu an geliştiriliyor.'],
            ['Personel ve prim', 'Kim ne sattı, kim ne tahsil etti; prim kendiliğinden hesaplanır.'],
            ['Stok ve etiket', 'Çerçeve ve cam stoğu, kritik stok uyarısı, barkodlu etiket.'],
            ['Kendi adınız', 'Müşteri sayfaları ve fişler dükkânınızın adını ve rengini taşır.'],
        ]],
    ];

    $hasBar = $iletisimUrl !== '';
    ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="index,follow">
<meta name="theme-color" content="#141012">
<title>Gözlükçü Programı OptiFlow | Medula Aktarımı, SGK Faturası ve Atölye</title>
<meta name="description" content="Gözlükçüler için yönetim programı: Medula'daki reçete tek tıkla siparişe, SGK katkı payı ve ay sonu SGK faturası hazır. Atölye panosu, WhatsApp bildirimi, garanti kartı, ÜTS karekod, kasa ve stok. 30 gün ücretsiz.">
<link rel="canonical" href="https://optiflow.com.tr/">
<?= pz_dogrulama_meta() ?>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="preload" as="font" type="font/woff2" href="assets/fonts/manrope-latin-wght-normal.woff2" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="assets/fonts/manrope-latin-ext-wght-normal.woff2" crossorigin>
<meta property="og:type" content="website">
<meta property="og:site_name" content="OptiFlow">
<meta property="og:title" content="OptiFlow — Reçete Medula'dan tek tıkla siparişe">
<meta property="og:description" content="Gözlükçüler için reçeteden teslimata yönetim sistemi. Medula aktarımı, SGK ay sonu faturası, atölye takibi ve WhatsApp bildirimi tek panelde. 30 gün ücretsiz.">
<meta property="og:url" content="https://optiflow.com.tr/">
<meta property="og:image" content="https://optiflow.com.tr/assets/og-optiflow.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="tr_TR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="https://optiflow.com.tr/assets/og-optiflow.png">
<script type="application/ld+json"><?= $ldJson(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'OptiFlow', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web, Windows', 'inLanguage' => 'tr', 'description' => 'Gözlükçüler için Medula aktarımı, SGK katkı payı ve ay sonu SGK faturası, sipariş, atölye, garanti, kasa ve stok yönetim sistemi.', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'TRY', 'description' => '30 gün ücretsiz deneme'], 'url' => 'https://optiflow.com.tr/']) ?></script>
<script type="application/ld+json"><?= $ldJson(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqLd]) ?></script>
<script type="application/ld+json"><?= $ldJson(['@context' => 'https://schema.org', '@graph' => [
    ['@type' => 'Organization', '@id' => 'https://optiflow.com.tr/#org', 'name' => 'OptiFlow', 'url' => 'https://optiflow.com.tr/', 'logo' => 'https://optiflow.com.tr/assets/icons/icon-512.png']
        + ($p['eposta'] !== '' || $p['telefon'] !== '' ? ['contactPoint' => array_filter(['@type' => 'ContactPoint', 'contactType' => 'customer support', 'email' => $p['eposta'] ?: null, 'telephone' => $p['telefon'] ?: null, 'areaServed' => 'TR', 'availableLanguage' => 'tr'])] : [])
        + ($p['instagram'] !== '' ? ['sameAs' => ['https://instagram.com/' . $p['instagram']]] : []),
    ['@type' => 'WebSite', '@id' => 'https://optiflow.com.tr/#site', 'name' => 'OptiFlow', 'url' => 'https://optiflow.com.tr/', 'inLanguage' => 'tr', 'publisher' => ['@id' => 'https://optiflow.com.tr/#org']],
]]) ?></script>
<style>
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-wght-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-ext-wght-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: "Fraunces"; font-style: normal; font-display: swap; font-weight: 500;
  src: url("assets/fonts/fraunces-latin-500-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Fraunces"; font-style: normal; font-display: swap; font-weight: 500;
  src: url("assets/fonts/fraunces-latin-ext-500-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }
@font-face { font-family: "IBM Plex Mono"; font-style: normal; font-display: swap; font-weight: 500;
  src: url("assets/fonts/ibm-plex-mono-latin-500-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+2000-206F, U+2212; }
@font-face { font-family: "IBM Plex Mono"; font-style: normal; font-display: swap; font-weight: 500;
  src: url("assets/fonts/ibm-plex-mono-latin-ext-500-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+1E00-1E9F; }
@font-face { font-family: "Manrope Fallback"; src: local("Arial"); size-adjust: 102%; ascent-override: 96%; descent-override: 24%; line-gap-override: 0%; }

/* Göz eşeli: başlık muayene tablosu gibi ortalanır ve satır satır küçülür; gerisi sola hizalı, sakin.
   Lacivert koyu bantlar (Pro penceresi, fiyat vurgusu) muayene odasının karanlık kısmı. */
:root{
  /* 4.19.0: uygulamayla aynı dil — siyah, bordo, sıcak açık gri. Eski ad kalıpları korunur:
     --blue = bordo mürekkep (vurgu metni/çizgi), --red = eylem rengi, --magenta = Pro vurgusu. */
  --paper:#f5f3f3;
  --card:#ffffff;
  --ink:#1b1416;
  --ink-2:#55494c;
  --line:#e8e1e2;
  --blue:#a01f36;
  --blue-deep:#7e1528;
  --magenta:#d0334f;
  --lens:#fbe4e8;
  --red:#b4233c;
  --red-deep:#8f1a2e;
  --ok:#157347;
  --night:#141012;
  --night-2:#221a1d;
  --night-ink:#c9bcc0;
  --display:"Manrope","Manrope Fallback",system-ui,Arial,sans-serif;
  --serif:"Manrope","Manrope Fallback",system-ui,Arial,sans-serif;
  --mono:"IBM Plex Mono",ui-monospace,"Cascadia Mono",Consolas,monospace;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:84px}
body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--display);font-size:16.5px;line-height:1.55;font-weight:500;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
a{color:inherit}
.wrap{max-width:1160px;margin:0 auto;padding-inline:24px}
h1,h2,h3{margin:0;text-wrap:balance}
h2{font-family:var(--serif);font-weight:800;font-size:clamp(1.9rem,3.6vw,2.9rem);line-height:1.08;letter-spacing:-.015em;max-width:22ch}
h3{font-size:1.06rem;font-weight:800;letter-spacing:-.01em}
p{margin:0}
.lead{color:var(--ink-2);font-size:1.08rem;max-width:58ch;margin-top:14px}
:focus-visible{outline:3px solid var(--blue);outline-offset:3px;border-radius:6px}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:15px 26px;border-radius:12px;font-weight:800;font-size:15.5px;text-decoration:none;border:2px solid transparent;cursor:pointer;white-space:nowrap;transition:background .15s ease,color .15s ease,border-color .15s ease}
.btn svg{width:18px;height:18px;flex:none}
.btn-red{background:linear-gradient(135deg,#d0334f,#8f1a2e);color:#fff;box-shadow:0 12px 26px -14px rgba(180,35,60,.9)}
.btn-red:hover{background:var(--red-deep)}
.btn-line{border-color:var(--ink);color:var(--ink)}
.btn-line:hover{background:var(--ink);color:#fff}
.btn-wa svg{color:#1faa53}
.on-night .btn-line{border-color:#fff;color:#fff}
.on-night .btn-line:hover{background:#fff;color:var(--night)}

/* ---------- Üst çubuk ---------- */
header{position:sticky;top:env(safe-area-inset-top,0px);z-index:30;background:rgba(245,243,243,.92);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.nav{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-block:13px}
.wordmark{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px;text-decoration:none;color:var(--ink);letter-spacing:-.02em}
.wordmark svg{width:30px;height:30px}
.nav-links{display:flex;align-items:center;gap:22px;font-size:14.5px;font-weight:700}
.nav-links a:not(.btn){text-decoration:none;color:var(--ink-2)}
.nav-links a:not(.btn):hover{color:var(--ink)}
.nav-links .btn{padding:10px 16px;font-size:14px}

/* ---------- Tanıtım videosu (4.24.0): foropter sahnesi ----------
   Bölüm uzun, sahne yapışkan; kaydırma ilerlemesi --p (0 → 1) assets/karsilama-sahne.js'ten gelir.
   0–0,40 : video iki merceğin ardında bulanık; kadranlar tık tık döner (--kl 0…5), bulanıklık --b iner.
   0,46–0,74 : mercekler büyür, foropter gövdesi açılır, ekran 3B eğimden düzleşir (--a).
   0,70–0,95 : uygulama ekranları videodan fırlayıp yörüngeye oturur (--c).  0,86+ : sesli izle düğmesi.
   JS yoksa: --p = 1, bölüm kısa ve son hâlinde. Hareket azaltmada sahne kaydırmayla yine ilerler, süsler sakinleşir. */
.film{--p:1;--kl:5;--b:0;position:relative;isolation:isolate;background:var(--night);color:#fff;
  --a:clamp(0,calc((var(--p) - .46) * 3.6),1);
  --c:clamp(0,calc((var(--p) - .70) * 4),1);
  --q:clamp(0,calc((var(--p) - .86) * 8),1)}
.film-sahneli{height:360vh}
.film-sahne{position:relative;overflow:hidden;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:clamp(18px,3.4svh,36px);
  padding-block:96px 104px;padding-inline:16px;
  --kw:min(980px,64vw,calc((100svh - 470px) * 16 / 9))}
.film-sahneli .film-sahne{position:sticky;top:0;height:100vh;height:100svh;padding-block:88px 20px}
.film-sahne::before{content:"";position:absolute;inset:0;z-index:-2;pointer-events:none;
  background:radial-gradient(55% 50% at 50% 55%,rgba(208,51,79,calc(.08 + var(--a) * .26)),transparent 70%),
             radial-gradient(40% 40% at 8% 0%,rgba(143,26,46,.35),transparent 70%),radial-gradient(40% 40% at 94% 100%,rgba(143,26,46,.3),transparent 70%)}
/* göz eşeli harfleri */
.film-harfler{position:absolute;inset:0;z-index:-1;pointer-events:none}
.film-harfler span{position:absolute;left:var(--x);top:var(--y);font-weight:800;line-height:1;font-size:calc(var(--s) * 1vmin);color:rgba(255,255,255,.045);
  filter:blur(1px);translate:0 calc(var(--p) * var(--d) * -140px);will-change:translate}
/* keskinlik ölçeği */
.film-olcek{position:absolute;left:clamp(16px,3vw,48px);top:50%;transform:translateY(-50%);margin:0;padding:0;list-style:none;display:grid;gap:14px;
  font-family:var(--mono);font-size:13px;color:rgba(255,255,255,.28);font-variant-numeric:tabular-nums}
.film-olcek::before{content:"Keskinlik";display:block;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.4);margin-bottom:4px}
.film-olcek li{transition:color .3s ease,transform .3s cubic-bezier(.3,1.6,.5,1);transform-origin:left center}
.film-olcek li.etkin{color:#ff95a6;transform:scale(1.35)}
/* başlıklar: üçü aynı yerde, sırayla bulanıklaşarak yer değiştirir */
.film-basliklar{display:grid;text-align:center;min-height:clamp(96px,15svh,150px);align-items:center}
.fb{grid-area:1/1;display:grid;gap:10px;justify-items:center;opacity:var(--o);filter:blur(calc((1 - var(--o)) * 10px));transform:translateY(calc((1 - var(--o)) * 16px))}
.fb1{--o:clamp(0,calc((.22 - var(--p)) * 10),1)}
.fb2{--o:clamp(0,min(calc((var(--p) - .2) * 10),calc((.5 - var(--p)) * 10)),1)}
.fb3{--o:clamp(0,calc((var(--p) - .52) * 7),1)}
.fb b,.fb h2{display:block;color:#fff;font-weight:800;font-size:clamp(1.9rem,4.4vw,3.6rem);line-height:1.02;letter-spacing:-.035em;max-width:none}
.fb em{font-style:normal;background:linear-gradient(90deg,#ff95a6,#ff6b81 55%,#e0405c);-webkit-background-clip:text;background-clip:text;color:transparent}
.fb span{color:var(--night-ink);font-size:clamp(.98rem,1.4vw,1.12rem)}
/* foropter + ekran */
.film-kutu{position:relative;width:var(--kw);max-width:100%;aspect-ratio:16/9;container-type:inline-size;will-change:transform;
  transform:perspective(1400px) rotateX(calc((1 - var(--a)) * 16deg)) scale(calc(.88 + var(--a) * .12))}
.film-isik{position:absolute;inset:-6%;z-index:-1;pointer-events:none;filter:blur(60px) saturate(1.6);opacity:calc(var(--a) * .62)}
.film-isik img{display:block;width:100%;height:100%;object-fit:cover;border-radius:40%}
.film-ekran{position:absolute;inset:0;margin:0;border-radius:28px;overflow:hidden;background:#0b0809 center/cover no-repeat}
.film-ekran video{display:block;width:100%;height:100%;object-fit:cover;filter:blur(calc(var(--b) * 1px));transition:filter .45s ease}
.film-govde{position:absolute;inset:-1px;border-radius:28px;pointer-events:none;
  background:radial-gradient(120% 150% at 50% -10%,#43363b,#1a1316 55%,#0f0b0d);
  box-shadow:inset 0 2px 0 rgba(255,255,255,.1),inset 0 -24px 50px rgba(0,0,0,.55);
  --r:calc(19 + var(--a) * var(--a) * (3 - 2 * var(--a)) * 76);
  -webkit-mask:radial-gradient(circle calc(var(--r) * 1cqw) at 28% 50%,transparent 99.3%,#000 100%),radial-gradient(circle calc(var(--r) * 1cqw) at 72% 50%,transparent 99.3%,#000 100%);
  -webkit-mask-composite:source-in;mask:radial-gradient(circle calc(var(--r) * 1cqw) at 28% 50%,transparent 99.3%,#000 100%),radial-gradient(circle calc(var(--r) * 1cqw) at 72% 50%,transparent 99.3%,#000 100%);
  mask-composite:intersect}
.film-govde::after{content:"OptiFlow · Foropter";position:absolute;left:50%;bottom:4cqw;transform:translateX(-50%);font-family:var(--mono);font-size:max(10px,1.1cqw);
  letter-spacing:.32em;text-transform:uppercase;color:rgba(255,255,255,.32);opacity:calc(1 - var(--a) * 3)}
.film-kopru{position:absolute;left:50%;top:50%;width:5cqw;height:11cqw;border-radius:2cqw;transform:translate(-50%,-50%);pointer-events:none;
  background:linear-gradient(180deg,#4a3c42,#1d1518);box-shadow:inset 0 1px 0 rgba(255,255,255,.15),0 6px 14px rgba(0,0,0,.5);opacity:calc(1 - var(--a) * 3)}
.film-kadran{position:absolute;top:50%;width:47.5cqw;aspect-ratio:1;pointer-events:none;color:#fff;
  transform:translate(-50%,-50%) scale(calc(1 + var(--a) * 2.6));opacity:calc(1 - var(--a) * 2.2)}
.film-kadran.k-sag{left:28%}.film-kadran.k-sol{left:72%}
.film-kadran::before{content:"";position:absolute;inset:10%;border-radius:50%;
  background:radial-gradient(55% 45% at 34% 28%,rgba(255,255,255,.2),transparent 62%);box-shadow:inset 0 0 4cqw rgba(208,51,79,.35),inset 0 0 0 .4cqw rgba(0,0,0,.6)}
.kd-ic{width:100%;height:100%;transform:rotate(calc(var(--kl) * 36deg));transition:transform .5s cubic-bezier(.3,1.8,.45,1)}
.kd-ic svg{display:block;width:100%;height:100%}
.kd-ic text{font-family:var(--mono);font-size:6.5px;fill:rgba(255,255,255,.72)}
.film-lcd{position:absolute;top:calc(50% + 20.6cqw);transform:translateX(-50%);display:inline-flex;gap:.8cqw;align-items:center;white-space:nowrap;
  padding:.5cqw 1.2cqw;border-radius:.8cqw;background:#0b0809;box-shadow:inset 0 0 0 1px rgba(255,149,166,.3);
  font-family:var(--mono);font-size:max(10px,1.25cqw);letter-spacing:.1em;color:rgba(255,255,255,.5);opacity:calc(1 - var(--a) * 3)}
.film-lcd b{font-weight:500;color:#ff95a6;font-variant-numeric:tabular-nums;min-width:5ch;text-align:right}
.film-lcd.l-sag{left:28%}.film-lcd.l-sol{left:72%}
/* videodan fırlayan ekranlar */
.film-kart{position:absolute;z-index:4;will-change:transform,opacity;margin:0;padding:.55cqw;border-radius:1.4cqw;background:#fff;box-shadow:0 30px 60px -24px rgba(0,0,0,.85);
  --g:clamp(0,calc((var(--c) - var(--e)) * 2.6),1);opacity:var(--g);
  transform:translate(calc((1 - var(--g)) * var(--dx) * 1cqw),calc((1 - var(--g)) * var(--dy) * 1cqw)) rotate(calc(var(--g) * var(--rot) * 1deg)) scale(calc(.2 + var(--g) * .8))}
.film-kart img{display:block;width:100%;height:auto;border-radius:1cqw}
.film-kart figcaption{position:absolute;left:1.4cqw;bottom:-1.6cqw;padding:.5cqw 1.2cqw;border-radius:99px;background:linear-gradient(135deg,#d0334f,#8f1a2e);
  color:#fff;font-size:max(11px,1.3cqw);font-weight:800;white-space:nowrap;box-shadow:0 10px 20px -8px rgba(143,26,46,.8)}
.film-kart.k1{left:-17cqw;top:-4cqw;width:30cqw;--e:0;--dx:52;--dy:23;--rot:-7}
.film-kart.k2{right:-16cqw;top:2cqw;width:30cqw;--e:.12;--dx:-51;--dy:17;--rot:6}
.film-kart.k3{left:-14cqw;bottom:-11cqw;width:28cqw;--e:.24;--dx:50;--dy:-30;--rot:5}
.film-kart.k4{right:-8cqw;bottom:-15cqw;width:12cqw;--e:.36;--dx:-52;--dy:-30;--rot:-5}
/* son: sesli izle + Instagram */
.film-son{position:relative;z-index:5;display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:12px 22px;margin-top:clamp(28px,calc(var(--kw) * .12),90px);
  opacity:var(--q);transform:translateY(calc((1 - var(--q)) * 20px))}
.film-oynat{display:inline-flex;align-items:center;gap:14px;padding:11px 26px 11px 11px;border-radius:99px;
  background:linear-gradient(135deg,#ff6b81,#d0334f 45%,#8f1a2e);color:#fff;font-weight:800;font-size:16px;text-decoration:none;white-space:nowrap;box-shadow:0 24px 50px -16px rgba(208,51,79,.95)}
.film-oynat i{position:relative;display:grid;place-items:center;width:44px;height:44px;border-radius:50%;background:#fff;color:#b4233c}
.film-oynat i svg{width:17px;height:17px;margin-left:3px}
.film-oynat i::before,.film-oynat i::after{content:"";position:absolute;inset:0;border-radius:50%;box-shadow:0 0 0 2px rgba(255,255,255,.7);animation:film-nabiz 2.2s ease-out infinite}
.film-oynat i::after{animation-delay:1.1s}
.film-oynat:hover{filter:brightness(1.08)}
.film-oynat:focus-visible{outline:3px solid #fff;outline-offset:4px}
@keyframes film-nabiz{0%{transform:scale(1);opacity:.8}100%{transform:scale(1.9);opacity:0}}
.film-insta{display:inline-flex;align-items:center;gap:8px;font-weight:800;color:#fff;text-decoration:none}
.film-insta:hover{color:#ff95a6}
.film-insta svg{width:20px;height:20px}
.film-dialog{padding:0;border:0;background:transparent;width:min(1100px,94vw);max-width:none;overflow:visible}
.film-dialog::backdrop{background:rgba(12,8,10,.86);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px)}
.film-dialog video{display:block;width:100%;border-radius:18px;background:#000;box-shadow:0 40px 90px -30px rgba(0,0,0,.9)}
.film-kapat{position:absolute;right:0;top:-52px;width:42px;height:42px;border-radius:50%;border:0;background:rgba(255,255,255,.14);color:#fff;font-size:24px;line-height:1;cursor:pointer}
.film-kapat:hover{background:rgba(255,255,255,.26)}
@media (max-width:900px){.film-olcek{display:none}}
@media (max-width:760px){
  .film-sahne{--kw:min(92vw,calc((100svh - 430px) * 16 / 9))}
  .film-kart.k3,.film-kart.k4{display:none}
  .film-kart.k1{left:2cqw;top:calc(100% + 6cqw);width:44cqw;--dx:28;--dy:-48;--rot:-4}
  .film-kart.k2{right:2cqw;left:auto;top:calc(100% + 9cqw);width:44cqw;--dx:-28;--dy:-50;--rot:4}
  .film-son{margin-top:calc(var(--kw) * .42)}
  .film-ekran,.film-govde{border-radius:16px}
  .film-sahneli .film-sahne{padding-block:84px 100px}
  .film-govde::after{display:none}
}
@media (prefers-reduced-motion:reduce){.film-oynat i::before,.film-oynat i::after{animation:none}.kd-ic{transition:transform .25s ease}.film-olcek li{transition:color .2s ease}}

/* ---------- Sahne sistemi (4.25.0): giriş efektleri + kaydırmaya bağlı bölümler ----------
   assets/karsilama-sahne.js öğelere data-gir / .gorunur ve bölümlere --i (0 → 1) yazar. JS yoksa hiçbir şey gizlenmez.
   Kaydırmaya bağlı her şey transform/opacity ile (akıcı); kendiliğinden dönen süsler hareket azaltmada durur. */
.sayfa-ilerleme{position:fixed;left:0;right:0;top:0;height:3px;z-index:60;pointer-events:none;transform-origin:left center;transform:scaleX(0);
  background:linear-gradient(90deg,#8f1a2e,#d0334f 55%,#ff6b81);box-shadow:0 0 12px rgba(208,51,79,.6)}
.js-sahne [data-gir]{transition:opacity .8s cubic-bezier(.2,.7,.2,1),translate .9s cubic-bezier(.2,.8,.2,1),scale .9s cubic-bezier(.2,.8,.2,1),rotate .9s cubic-bezier(.2,.8,.2,1),filter .9s ease;
  transition-delay:calc(var(--sira,0) * 90ms)}
.js-sahne [data-gir]:not(.gorunur){opacity:0}
.js-sahne [data-gir="yukari"]:not(.gorunur){translate:0 42px}
.js-sahne [data-gir="soldan"]:not(.gorunur){translate:-56px 0}
.js-sahne [data-gir="olcek"]:not(.gorunur){scale:.86;translate:0 30px}
.js-sahne [data-gir="netles"]:not(.gorunur){filter:blur(14px);scale:1.04}
.js-sahne [data-gir="dondur"]:not(.gorunur){translate:0 70px;rotate:x 24deg}
.js-sahne [data-gir="satir"]:not(.gorunur){translate:-30px 0}
/* hero ve son çağrı: imleci izleyen ışık */
.hero{position:relative;isolation:isolate}
.hero::before,.son::before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;opacity:0;transition:opacity .5s ease;
  background:radial-gradient(380px circle at var(--mx,50%) var(--my,40%),rgba(208,51,79,.14),transparent 70%)}
.son::before{background:radial-gradient(460px circle at var(--mx,50%) var(--my,50%),rgba(208,51,79,.38),transparent 70%)}
.hero.isikli::before,.son.isikli::before{opacity:1}
/* Lite / Pro: katmanlı derinlik */
#surumler{--i:.5}
.surum-grid{perspective:1400px}
.sahne .tarayici{transform:translateY(calc((.5 - var(--i)) * 34px))}
.sahne .cep{transform:translateY(calc((.5 - var(--i)) * -96px)) rotate(calc((.5 - var(--i)) * 10deg))}
.masaustu{transform:perspective(1200px) rotateY(calc((.5 - var(--i)) * -16deg)) rotateX(calc((.5 - var(--i)) * 6deg))}
/* fark tablosu: eski yöntemin üstü çizilir, OptiFlow hücresi dolar */
.fark td.once{transition:text-decoration-color .6s ease calc(var(--sira,0) * 90ms + .4s),color .6s ease calc(var(--sira,0) * 90ms + .4s)}
.js-sahne .fark tr:not(.gorunur) td.once{text-decoration-color:transparent;color:var(--ink)}
.fark td.ile{padding-left:12px;background:linear-gradient(90deg,rgba(208,51,79,.12),rgba(208,51,79,0)) no-repeat 0 0/100% 100%}
.js-sahne .fark td.ile{background-size:0% 100%;transition:background-size 1s cubic-bezier(.2,.8,.2,1) calc(var(--sira,0) * 90ms + .6s)}
.js-sahne .fark tr.gorunur td.ile{background-size:100% 100%}
/* gözlüğün yolu: çizgi dolar, gözlük ilerler, geçilen adımlar yanar */
#yol{--i:1}
.yol-kap{position:relative}
.yol li{--on:clamp(0,calc((var(--i) * 1.3 - .06 - var(--k) / var(--n)) * 9),1)}
#yol .yol li::before{background:color-mix(in srgb,var(--magenta) calc(var(--on) * 100%),var(--paper));color:color-mix(in srgb,#fff calc(var(--on) * 100%),var(--ink));
  border-color:color-mix(in srgb,var(--magenta) calc(var(--on) * 100%),var(--ink));scale:calc(1 + var(--on) * .2);
  box-shadow:0 0 0 calc(var(--on) * 6px) rgba(208,51,79,.15)}
.yol-dolum{position:absolute;left:0;top:-1px;height:3px;width:calc(var(--i) * 100%);border-radius:3px;z-index:1;pointer-events:none;
  background:linear-gradient(90deg,#8f1a2e,#d0334f 60%,#ff6b81);box-shadow:0 0 12px rgba(208,51,79,.55)}
.yol-gezgin{position:absolute;top:-58px;left:calc(var(--i) * 100%);translate:-50% 0;z-index:2;display:grid;place-items:center;width:52px;height:34px;border-radius:12px;
  background:linear-gradient(135deg,#d0334f,#8f1a2e);color:#fff;box-shadow:0 12px 24px -10px rgba(143,26,46,.9);pointer-events:none}
.yol-gezgin::after{content:"";position:absolute;left:50%;bottom:-6px;width:12px;height:12px;margin-left:-6px;background:#8f1a2e;rotate:45deg;border-radius:2px;z-index:-1}
.yol-gezgin svg{width:30px;height:30px}
/* atölye + telefon */
#icerde{--i:.5}
.telefon{position:relative;transform:translateY(calc((.5 - var(--i)) * 50px))}
.tel-bildirim{position:absolute;left:-34px;right:-34px;top:-30px;z-index:2;display:flex;gap:10px;align-items:center;padding:10px 12px;border-radius:16px;background:#fff;color:var(--ink);
  box-shadow:0 22px 40px -14px rgba(27,20,22,.55),0 0 0 1px rgba(27,20,22,.06);font-size:12.5px;line-height:1.35;
  transition:opacity .5s ease 1.1s,translate .7s cubic-bezier(.3,1.7,.5,1) 1.1s,scale .7s cubic-bezier(.3,1.7,.5,1) 1.1s}
.tel-bildirim i{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#1faa53;color:#fff;flex:none}
.tel-bildirim i svg{width:20px;height:20px}
.tel-bildirim b{display:block;font-size:12px}
.js-sahne .telefon:not(.gorunur) .tel-bildirim{opacity:0;translate:0 -24px;scale:.88}
.adimlar span.on{transition:background-color .4s ease}
.adimlar span:nth-child(1){transition-delay:.45s}.adimlar span:nth-child(2){transition-delay:.7s}.adimlar span:nth-child(3){transition-delay:.95s}
.js-sahne .telefon:not(.gorunur) .adimlar span.on{background:var(--line)}
/* galeri: şerit kaydırmayla yana akar */
#galeri{--i:.5}
.serit{perspective:1400px}
.serit figure{transform:translateX(calc((.5 - var(--i)) * 180px))}
/* ay sonu: tarama çizgisi, eksik reçete yanar, satırlar sırayla */
.kontrol{position:relative}
.kontrol::after{content:"";position:absolute;left:0;right:0;top:0;height:3px;opacity:0;pointer-events:none;
  background:linear-gradient(90deg,transparent,#ff6b81 30%,#ff6b81 70%,transparent);box-shadow:0 0 18px 4px rgba(255,107,129,.5)}
.js-sahne .kontrol.gorunur::after{animation:tara 1.7s cubic-bezier(.45,0,.2,1) .6s 1 both}
@keyframes tara{0%{top:24%;opacity:0}12%{opacity:1}88%{opacity:1}100%{top:100%;opacity:0}}
.js-sahne .kontrol.gorunur tr.eksik td{animation:eksik-yan .9s ease 2.3s 3}
@keyframes eksik-yan{0%,100%{background:#fbe4e8}50%{background:#ffb3c1}}
.dokum tr{transition:opacity .5s ease,translate .5s ease}
.dokum tr:nth-child(1){transition-delay:.35s}.dokum tr:nth-child(2){transition-delay:.5s}.dokum tr:nth-child(3){transition-delay:.65s}.dokum tr:nth-child(4){transition-delay:.8s}
.js-sahne .kontrol:not(.gorunur) .dokum tr{opacity:0;translate:-14px 0}
/* hikâye: kelime kelime koyulaşır */
.hikaye{--i:1}
.hikaye .kl{opacity:clamp(.16,calc((var(--i) * 1.55 - .2 - var(--k) / var(--n)) * 5 + .16),1)}
/* neler: grup başlığının altı çizilir */
.grup h3::after{content:"";display:block;width:56px;height:3px;margin-top:12px;border-radius:3px;background:linear-gradient(90deg,#d0334f,#ff6b81);transform-origin:left;transition:scale .8s cubic-bezier(.2,.8,.2,1) .4s}
.js-sahne .grup h3:not(.gorunur)::after{scale:0 1}
/* fiyatlar: öne çıkan pakette dönen ışık kenarı */
@property --aci{syntax:"<angle>";inherits:false;initial-value:0deg}
#fiyatlar .paket.vurgu{position:relative;isolation:isolate;border-color:transparent;box-shadow:0 34px 80px -34px rgba(208,51,79,.75)}
.paket.vurgu::before{content:"";position:absolute;inset:-2px;border-radius:18px;z-index:-1;
  background:conic-gradient(from var(--aci),#ff6b81,#8f1a2e 25%,#141012 45%,#141012 60%,#d0334f 80%,#ff6b81);animation:aci-don 6s linear infinite}
.paket.vurgu::after{content:"";position:absolute;inset:0;border-radius:16px;z-index:-1;background:var(--night)}
@keyframes aci-don{to{--aci:360deg}}
/* güven: simgeler çizilerek gelir */
.guven svg *{stroke-dasharray:120;stroke-dashoffset:0;transition:stroke-dashoffset 1.5s cubic-bezier(.6,0,.2,1) calc(var(--sira,0) * 90ms + .25s)}
.js-sahne .guven > div:not(.gorunur) svg *{stroke-dashoffset:120}
/* son çağrı: dev gözlük çizgisi */
.son{position:relative;overflow:hidden;isolation:isolate;--i:.5}
.son-gozluk{position:absolute;left:50%;top:50%;width:min(1100px,130vw);translate:-50% -50%;z-index:-1;pointer-events:none;color:rgba(255,107,129,.16);
  scale:calc(.85 + var(--i) * .3)}
.son-gozluk path,.son-gozluk circle{stroke-dasharray:6 10;animation:yuru 14s linear infinite}
@keyframes yuru{to{stroke-dashoffset:-320}}
@media (max-width:980px){
  .yol-dolum{top:0;left:-1px;width:3px;height:calc(var(--i) * 100%)}
  .yol-gezgin{display:none}
  .tel-bildirim{left:-14px;right:-14px}
}
@media (prefers-reduced-motion:reduce){.paket.vurgu::before,.son-gozluk path,.son-gozluk circle{animation:none}}

/* ---------- Göz eşeli (hero) ---------- */
.hero{padding-block:56px 72px;overflow:hidden}
.esel{position:relative;margin:0 auto;max-width:980px;text-align:center}
.esel-satir{display:grid;grid-template-columns:1fr auto;align-items:baseline;gap:18px;border-bottom:1px solid var(--line);padding-block:.14em .2em;margin:0}
.e6{border-bottom:0}
.esel h1{font-size:inherit}
.esel-satir .harf{font-weight:800;letter-spacing:.035em;line-height:1.02;white-space:nowrap}
.esel-satir .keskin{font-size:13px;font-weight:700;color:var(--ink-2);min-width:2.8em;text-align:right;font-variant-numeric:tabular-nums}
.e1 .harf{font-size:clamp(3.4rem,13vw,10.4rem);letter-spacing:.05em}
.e2 .harf{font-size:clamp(2.5rem,8.6vw,7rem)}
.e3 .harf{font-size:clamp(1.65rem,5.2vw,4.25rem)}
.e4 .harf{font-size:clamp(1.3rem,3.6vw,2.9rem)}
.e5 .harf{font-size:clamp(1.1rem,2.5vw,2rem)}
.e6{padding-top:.8em}
.e6 .harf{font-size:clamp(1rem,1.5vw,1.2rem);font-weight:600;letter-spacing:0;white-space:normal;color:var(--ink-2);line-height:1.55;max-width:62ch;justify-self:center}
.e1 .harf{color:var(--blue)}
.e6 .keskin{color:var(--magenta)}
.esel-satir{border-bottom-color:var(--line)}
.esel h1 .esel-satir:first-child{border-top:3px solid var(--ink)}
.esel-satir .harf{animation:netles 1.1s cubic-bezier(.2,.7,.2,1) both}
.e1 .harf{animation-delay:.05s}.e2 .harf{animation-delay:.2s}.e3 .harf{animation-delay:.35s}
.e4 .harf{animation-delay:.5s}.e5 .harf{animation-delay:.65s}.e6 .harf{animation-delay:.8s}
@keyframes netles{from{filter:blur(9px);opacity:.35}to{filter:blur(0);opacity:1}}
.hero-alt{display:flex;flex-direction:column;align-items:center;gap:16px;margin-top:34px;text-align:center}
.hero-ctas{display:flex;gap:12px;flex-wrap:wrap;justify-content:center}
.hero-not{display:flex;flex-wrap:wrap;justify-content:center;gap:4px 20px;margin:0;padding:0;list-style:none;font-size:14px;font-weight:700;color:var(--ink-2)}
.hero-not li::before{content:"✓";color:var(--blue);margin-right:6px}

/* ---------- Bölümler ---------- */
section{padding-block:96px}
.bas{margin-bottom:44px}

/* İki sürüm */
.surum-grid{display:grid;grid-template-columns:1fr 1fr;gap:28px;align-items:stretch}
.surum{min-width:0;display:flex;flex-direction:column;gap:24px;padding:28px;border-radius:20px;background:var(--night-2);border:1px solid rgba(255,255,255,.1)}
.surum.pro{background:linear-gradient(180deg,#3a1520 0%,var(--night-2) 60%);border-color:rgba(255,107,129,.45)}
.surum-bas p{color:var(--night-ink);margin-top:8px;max-width:52ch}
.surum-bas h3{font-family:var(--serif);font-weight:800;font-size:1.65rem;margin-top:12px}
.surum-eylem{display:flex;flex-wrap:wrap;align-items:center;gap:10px 16px;margin-top:18px}
.surum-eylem .btn{padding:12px 20px;font-size:15px}
.surum-eylem .btn svg{width:18px;height:18px}
.surum-eylem small{color:var(--night-ink);font-size:13px;font-weight:600}
.surum-eylem small a{color:#fff;font-weight:800}
.rozet{display:inline-block;font-weight:800;font-size:13px;padding:5px 12px;border-radius:999px}
.rozet.lite{background:#fff;color:var(--blue-deep)}
.rozet.pro{background:var(--magenta);color:#fff}
.sahne{position:relative;padding-right:70px;padding-bottom:30px}
.tarayici{margin:0;border-radius:12px;overflow:hidden;background:#fff;border:1px solid var(--line);box-shadow:0 30px 60px -30px rgba(0,0,0,.55)}
.tarayici-ust{display:flex;align-items:center;gap:6px;padding:8px 10px;background:#f1ecec;border-bottom:1px solid var(--line)}
.tarayici-ust .nokta{width:9px;height:9px;border-radius:50%;background:#d6cccd}
.tarayici-ust .adres{margin-left:8px;flex:1;min-width:0;font-size:11.5px;color:var(--ink-2);background:#fff;border-radius:6px;padding:3px 10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tarayici img,.masaustu img,.cep img{display:block;width:100%;height:auto}
.cep{position:absolute;right:0;bottom:0;width:30%;max-width:150px;margin:0;border:6px solid #141012;border-radius:22px;overflow:hidden;box-shadow:0 20px 40px -10px rgba(0,0,0,.6);background:#fff}
.masaustu{margin:0;border-radius:12px;overflow:hidden;background:#1e171a;border:1px solid rgba(255,255,255,.18);box-shadow:0 30px 60px -30px rgba(0,0,0,.6)}
.ms-ust{display:flex;justify-content:space-between;align-items:center;padding:7px 12px;background:#100c0d;font-size:11.5px;color:var(--night-ink)}
.ms-dug{display:flex;gap:10px}.ms-dug i{width:10px;height:2px;background:var(--night-ink);display:block}
.ms-arac{display:flex;align-items:center;gap:6px;padding:7px 10px;background:#221a1d;font-size:11.5px;font-weight:700;color:var(--night-ink);flex-wrap:wrap}
.ms-arac .sekme{padding:5px 10px;border-radius:7px 7px 0 0;background:rgba(255,255,255,.06)}
.ms-arac .sekme.acik{background:#fff;color:var(--ink)}
.ms-arac .bosluk{flex:1}
.ms-arac .dug{padding:5px 9px;border-radius:7px;background:rgba(255,255,255,.1);color:#fff}
.ms-arac .dug.kirmizi{background:var(--red);color:#fff}
.ms-icerik{display:grid;grid-template-columns:.42fr .58fr;background:#fff}
.ms-icerik > *{min-width:0}
.ms-medula{padding:12px;background:#f2f2f2;color:#222;font-size:11.5px;border-right:3px solid var(--red);display:flex;flex-direction:column;gap:6px;font-family:Arial,sans-serif}
.ms-medula small{color:#555}
.ms-medula .kutular{grid-template-columns:auto repeat(3,1fr);gap:4px}
.ms-medula .kutular i{color:#333;font-size:10.5px}
.ms-medula .kutu{border:1px solid #999;font-size:11px;padding:2px 4px}
.ms-medula .ok{margin-top:auto;align-self:flex-start;background:var(--red);color:#fff;font-family:var(--display);font-weight:800;padding:5px 10px;border-radius:6px}
.karsi-kap{margin-top:36px}
.karsi{width:100%;border-collapse:collapse;font-size:15px;color:#fff}
.karsi th{text-align:left;padding:0 14px 12px 0;font-size:15px;border-bottom:2px solid #fff}
.karsi th small,.karsi td small{color:var(--night-ink);font-weight:600;font-size:12px;margin-left:4px}
.karsi th.p{color:#ff8fa0}
.karsi td{padding:12px 14px 12px 0;border-bottom:1px solid rgba(255,255,255,.12)}
.karsi td:first-child{width:52%}
.karsi td:not(:first-child){font-weight:800}
.karsi tr.grupcuk td{padding-top:22px;font-family:var(--serif);font-weight:800;font-size:1.15rem;color:var(--night-ink);border-bottom:0;width:auto}
.karsi tr.fark-satir td:last-child{color:#ff8fa0}
.karsi td.yok{color:var(--night-ink);font-weight:600}
#surumler .gizlilik{margin-top:30px}

.surum-isaret{display:inline-flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:8px;margin-top:6px;padding:10px 16px;border-radius:999px;background:var(--card);border:1px solid var(--line);text-decoration:none;font-size:14px;font-weight:700;color:var(--ink-2)}
.surum-isaret:hover{border-color:var(--ink)}
.surum-isaret .rozet.lite{background:var(--lens)}
.surum-isaret .ayrac{width:1px;height:16px;background:var(--line)}

/* Galeri şeridi */
.serit{display:flex;gap:24px;overflow-x:auto;scroll-snap-type:x mandatory;padding:4px max(24px,calc((100vw - 1112px)/2)) 24px;scrollbar-width:thin}
.serit figure{flex:0 0 min(78vw,640px);scroll-snap-align:center}
.serit figcaption{padding:12px 14px;font-size:14px;color:var(--ink-2);background:#fff;border-top:1px solid var(--line)}
.serit figcaption b{color:var(--ink);margin-right:6px}

/* Önce / OptiFlow ile */
.fark{width:100%;border-collapse:collapse;font-size:15.5px}
.fark th{text-align:left;font-size:14px;font-weight:800;padding:0 18px 12px 0;border-bottom:3px solid var(--ink)}
.fark th.ile{color:var(--blue)}
.fark td{padding:16px 18px 16px 0;border-bottom:1px solid var(--line);vertical-align:top}
.fark td:first-child{font-weight:800;width:22%}
.fark td.once{color:var(--ink-2);text-decoration:line-through;text-decoration-color:rgba(255,107,129,.55);text-decoration-thickness:2px}
.fark td.ile{font-weight:600}
.tablo-kap{overflow-x:auto}

/* Dükkânın içi, müşterinin cebi */
.iki{display:grid;grid-template-columns:1.35fr .65fr;gap:40px;align-items:start}
.iki > *{min-width:0}
.pano{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;box-shadow:0 30px 60px -40px rgba(15,14,13,.45)}
.pano-ust{display:flex;justify-content:space-between;gap:10px;font-size:13px;font-weight:800;margin-bottom:14px}
.pano-ust span{color:var(--ink-2);font-weight:600}
.sutunlar{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.sutun{background:var(--paper);border-radius:10px;padding:10px;display:flex;flex-direction:column;gap:8px;min-width:0}
.sutun b{font-size:12.5px;display:flex;justify-content:space-between}
.sutun b i{font-style:normal;color:var(--ink-2);font-family:var(--mono);font-weight:500}
.is{background:var(--card);border:1px solid var(--line);border-left:4px solid var(--ink-2);border-radius:8px;padding:8px 9px;font-size:12px;line-height:1.35}
.is strong{display:block;font-family:var(--mono);font-size:12px}
.is span{color:var(--ink-2)}
.is.gec{border-left-color:var(--red)}
.is.gec em{color:var(--red);font-style:normal;font-weight:800}
.is.hazir{border-left-color:var(--ok)}
.is.mon{border-left-color:var(--blue)}
.is.kon{border-left-color:var(--magenta)}
.alt-not{margin-top:14px;color:var(--ink-2);font-size:14.5px}
.telefon{border:10px solid var(--ink);border-radius:34px;background:var(--paper);padding:18px 14px;max-width:290px;margin-inline:auto;box-shadow:0 30px 60px -30px rgba(15,14,13,.55)}
.tel-ust{display:flex;align-items:center;gap:8px;font-weight:800;font-size:14px;margin-bottom:14px}
.tel-ust i{width:22px;height:22px;border-radius:7px;background:var(--blue);display:block}
.tel-kart{background:var(--card);border-radius:16px;padding:16px;border:1px solid var(--line)}
.tel-kart small{display:inline-block;font-family:var(--mono);font-size:11px;color:var(--ink-2);border:1px solid var(--line);border-radius:6px;padding:2px 6px}
.tel-kart h4{margin:12px 0 4px;font-family:var(--serif);font-weight:800;font-size:1.6rem;line-height:1.1}
.tel-kart p{font-size:13.5px;color:var(--ink-2)}
.adimlar{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:14px}
.adimlar span{height:6px;border-radius:3px;background:var(--line)}
.adimlar span.on{background:var(--ok)}
.tel-adim{font-size:12px;color:var(--ink-2);margin-top:6px}
.tel-dugme{margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:8px}
.tel-dugme span{text-align:center;font-size:12.5px;font-weight:800;padding:9px;border-radius:10px;background:var(--lens);color:var(--blue-deep)}
.tel-garanti{margin-top:10px;display:flex;justify-content:space-between;font-size:12.5px;padding:10px 12px;border-radius:12px;background:var(--card);border:1px solid var(--line)}
.tel-garanti b{color:var(--ok)}

/* SGK ay sonu */
.ay-grid{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center}
.ay-grid > *{min-width:0}
.kontrol{background:var(--card);border:1px solid var(--line);border-radius:16px;overflow:hidden}
.kontrol-ust{display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid var(--line)}
.kontrol-ust div{padding:16px 18px}
.kontrol-ust div + div{border-left:1px solid var(--line)}
.kontrol-ust small{display:block;font-size:12.5px;color:var(--ink-2);font-weight:700}
.kontrol-ust b{font-size:1.9rem;font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:-.02em}
.kontrol-ust .fark-hucre b{color:var(--red)}
.dokum{width:100%;border-collapse:collapse;font-size:13.5px;font-variant-numeric:tabular-nums}
.dokum td{padding:10px 18px;border-bottom:1px solid var(--line)}
.dokum td.no{font-family:var(--mono)}
.dokum td.tl{text-align:right;font-family:var(--mono)}
.dokum tr.eksik td{background:#fbe4e8}
.dokum tr.eksik td:last-child{color:var(--red);font-weight:800;font-family:var(--display);text-align:right}
.ay-liste{list-style:none;margin:22px 0 0;padding:0;display:grid;gap:12px}
.ay-liste li{display:grid;grid-template-columns:22px 1fr;gap:10px;color:var(--ink-2)}
.ay-liste li::before{content:"";width:12px;height:7px;margin-top:.45em;border-left:2px solid var(--blue);border-bottom:2px solid var(--blue);transform:rotate(-45deg)}
.ay-liste b{color:var(--ink)}

/* Rehber */
.rehber{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px;padding:28px 32px;border:2px solid var(--ink);border-radius:16px}
.rehber p{color:var(--ink-2);max-width:60ch;margin-top:6px}
.rehber-liste{margin:14px 0 0;padding-left:18px;display:grid;gap:6px;font-weight:700}
.rehber-liste a{color:var(--blue-deep)}

/* Bir siparişin yolu */
.yol{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(5,1fr);gap:0;counter-reset:adim}
.yol li{position:relative;padding:22px 20px 0 0;border-top:2px solid var(--ink)}
.yol li::before{counter-increment:adim;content:counter(adim);position:absolute;top:-15px;left:0;width:28px;height:28px;border-radius:50%;background:var(--paper);border:2px solid var(--ink);display:grid;place-items:center;font-size:13px;font-weight:800;font-family:var(--mono)}
.yol li:last-child{border-top-color:var(--magenta)}
.yol li:last-child::before{border-color:var(--magenta);color:var(--magenta)}
.yol h3{margin:10px 0 8px}
.yol p{color:var(--ink-2);font-size:15px}

.gece{background:var(--night);color:#fff}
.gece .lead{color:var(--night-ink)}
.kutular{display:grid;grid-template-columns:auto repeat(3,1fr);gap:6px;align-items:center}
.kutular i{font-style:normal;color:var(--night-ink);font-size:12px}
.kutu{font-family:var(--mono);background:#fff;color:var(--night);border-radius:4px;padding:4px 6px;text-align:right;font-size:12.5px}
.gizlilik{display:flex;gap:12px;margin-top:26px;padding-top:20px;border-top:1px solid rgba(255,255,255,.14);font-size:14.5px;color:var(--night-ink)}
.gizlilik svg{width:22px;height:22px;flex:none;color:#fff}
.gizlilik b{color:#fff}

/* Neler yapar */
.grup{display:grid;grid-template-columns:260px 1fr;gap:32px;padding-block:30px;border-top:1px solid var(--line)}
.grup:last-child{border-bottom:1px solid var(--line)}
.grup > *{min-width:0}
.grup h3{font-family:var(--serif);font-weight:800;font-size:1.55rem;letter-spacing:-.01em}
.grup dl{margin:0;display:grid;grid-template-columns:1fr 1fr;gap:20px 36px}
.grup dt{font-weight:800}
.grup dd{margin:4px 0 0;color:var(--ink-2);font-size:15px}

/* Hikâye */
.hikaye{padding-block:88px;background:var(--lens)}
.hikaye p.buyuk{font-family:var(--serif);font-weight:800;font-size:clamp(1.5rem,2.8vw,2.2rem);line-height:1.3;max-width:34ch;letter-spacing:-.01em}
.hikaye p.ek{margin-top:20px;color:var(--ink-2);max-width:60ch}

/* Fiyatlar */
.kamp{display:flex;flex-wrap:wrap;gap:6px 14px;padding:14px 18px;border:2px dashed var(--blue);border-radius:12px;margin-bottom:24px}
.kamp b{color:var(--blue-deep)}
.paketler{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}
.paket{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:30px;display:flex;flex-direction:column;gap:14px}
.paket.vurgu{background:var(--night);color:#fff;border-color:var(--night)}
.paket h3 .yakinda{display:inline-block;vertical-align:middle;font-family:var(--sans,inherit);font-size:12px;font-weight:800;padding:3px 10px;border-radius:999px;background:var(--magenta);color:#fff;margin-left:6px}
.paket .acik{color:var(--ink-2)}
.paket.vurgu .acik,.paket.vurgu li{color:var(--night-ink)}
.paket h3{font-family:var(--serif);font-weight:800;font-size:1.7rem}
.tutar{font-size:2.4rem;font-weight:800;letter-spacing:-.02em;font-variant-numeric:tabular-nums}
.tutar span{font-size:.95rem;font-weight:600;color:var(--ink-2);margin-left:6px}
.paket.vurgu .tutar span{color:var(--night-ink)}
.sor{font-weight:800}
.paket ul{list-style:none;margin:0;padding:0;display:grid;gap:8px;flex:1}
.paket li{padding-left:24px;position:relative;color:var(--ink-2)}
.paket li::before{content:"";position:absolute;left:0;top:.5em;width:12px;height:7px;border-left:2px solid var(--blue);border-bottom:2px solid var(--blue);transform:rotate(-45deg)}
.paket.vurgu li::before{border-color:#fff}
.paket .btn{align-self:flex-start}
.dahil{display:flex;flex-wrap:wrap;gap:8px 22px;margin-top:22px;color:var(--ink-2);font-weight:700;font-size:14.5px}
.dahil span::before{content:"✓";color:var(--blue);margin-right:6px}

/* Güven */
.guven{display:grid;grid-template-columns:repeat(3,1fr);gap:30px 36px}
.guven > div{display:grid;grid-template-columns:28px 1fr;gap:4px 14px}
.guven svg{width:24px;height:24px;color:var(--blue);grid-row:span 2}
.guven p{color:var(--ink-2);font-size:15px}

/* SSS */
.sss{max-width:820px}
.sss details{border-top:1px solid var(--line);padding-block:18px}
.sss details:last-child{border-bottom:1px solid var(--line)}
.sss summary{cursor:pointer;font-weight:800;font-size:1.08rem;list-style:none;display:flex;justify-content:space-between;gap:16px}
.sss summary::-webkit-details-marker{display:none}
.sss summary::after{content:"+";font-family:var(--mono);font-size:1.3rem;line-height:1;color:var(--blue)}
.sss details[open] summary::after{content:"−"}
.sss details p{margin-top:12px;color:var(--ink-2);max-width:68ch}

/* Son çağrı */
.son{background:var(--night);color:#fff;text-align:center;padding-block:96px}
.son h2{margin:0 auto;max-width:20ch}
.son p{color:var(--night-ink);margin:16px auto 0;max-width:52ch}
.son .hero-ctas{margin-top:30px}

footer{padding-block:56px 40px;font-size:14.5px;color:var(--ink-2)}
.alt{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:32px}
.alt p{max-width:44ch;margin-top:12px}
.alt-kol{display:flex;flex-direction:column;gap:8px}
.alt-kol b{color:var(--ink);margin-bottom:4px}
.alt-kol a{text-decoration:none}
.alt-kol a:hover{color:var(--ink);text-decoration:underline}
.alt-son{display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;margin-top:40px;padding-top:20px;border-top:1px solid var(--line);font-size:13.5px}

.mbar{display:none}

@media (max-width:980px){
  .nav-links a:not(.btn){display:none}
    .yol{grid-template-columns:1fr;gap:28px}
  .yol li{border-top:0;border-left:2px solid var(--ink);padding:0 0 0 26px}
  .yol li:last-child{border-left-color:var(--magenta)}
  .yol li::before{top:0;left:-15px}
  .yol h3{margin-top:2px}
  .grup{grid-template-columns:1fr;gap:18px}
  .iki,.ay-grid,.surum-grid{grid-template-columns:1fr;gap:40px}
  .sutunlar{grid-template-columns:repeat(2,1fr)}
  .guven{grid-template-columns:1fr 1fr}
  .alt{grid-template-columns:1fr 1fr}
}
@media (max-width:640px){
  body{font-size:16px}
  section{padding-block:68px}
  .hero{padding-block:36px 56px}
  .esel-satir{gap:10px;padding-block:.2em .26em}
  .esel-satir .keskin{font-size:11px}
  .e2 .harf{font-size:2.6rem}.e3 .harf{font-size:1.75rem}.e4 .harf{font-size:1.45rem}.e5 .harf{font-size:1.2rem}
  .e3 .harf,.e4 .harf,.e5 .harf{white-space:normal}
  .nav-links .giris{display:none}
  .grup dl{grid-template-columns:1fr}
  .guven{grid-template-columns:1fr}
        .alt{grid-template-columns:1fr}
  .kontrol-ust b{font-size:1.5rem}
  .kontrol-ust div{padding:12px}
  .fark{font-size:14.5px}
  .rehber{padding:22px}
  .surum{padding:18px}
  .sahne{padding-right:40px}
  .ms-icerik{grid-template-columns:1fr}
  .ms-icerik img{display:none}
  .karsi{font-size:13.5px}
  .karsi td:first-child{width:46%}
  .mbar{display:flex;gap:10px;position:fixed;left:0;right:0;bottom:0;z-index:40;padding:10px 16px calc(10px + env(safe-area-inset-bottom,0px));background:rgba(245,243,243,.96);border-top:1px solid var(--line)}
  .mbar .btn{flex:1;padding:13px 10px}
  body.bar{padding-bottom:76px}
}

/* ---------- 4.19.0 tipografi: tüm başlıklar sans, sıkı ---------- */
h2{font-weight:800;letter-spacing:-.03em}
h3{font-weight:750}
.btn{font-weight:750;border-radius:12px}
.btn-red:hover{filter:brightness(1.08);background:linear-gradient(135deg,#d0334f,#8f1a2e)}
.wordmark{font-weight:800;letter-spacing:-.02em}
.e1 .harf{background:linear-gradient(135deg,#d0334f,#7e1528);-webkit-background-clip:text;background-clip:text;color:transparent}
.gece,.son{background:radial-gradient(70% 120% at 100% 0%,rgba(208,51,79,.22),transparent 55%),var(--night)}
</style>
<?= ga_head() ?>
</head>
<body<?= $hasBar ? ' class="bar"' : '' ?>>

<header>
  <div class="wrap nav">
    <a class="wordmark" href="/" aria-label="OptiFlow ana sayfa"><?= pz_logo('lgNav') ?>OptiFlow</a>
    <nav class="nav-links" aria-label="Ana menü">
      <a href="#yol">Nasıl çalışır</a>
      <a href="#surumler">Lite ve Pro</a>
      <a href="#ay-sonu">SGK faturası</a>
      <a href="#neler">Neler yapar</a>
      <a href="#fiyatlar">Fiyatlar</a>
      <a href="#sss">Sorular</a>
      <a href="indir.php">Pro'yu indir</a>
      <?php if (tenant_oturum() || isset($_COOKIE[HATIRLA_MAGAZA_CEREZ])): ?>
      <a class="btn btn-line giris" href="index.php">Uygulamaya git</a>
      <?php else: ?>
      <a class="btn btn-line giris" href="magaza-giris.php">Giriş yap</a>
      <?php endif; ?>
      <a class="btn btn-red" href="kayit.php">Ücretsiz deneyin</a>
    </nav>
  </div>
</header>

<main>
  <div class="hero">
    <div class="wrap">
      <div class="esel">
        <h1 aria-label="Reçete Medula'dan tek tıkla siparişe, sipariş atölyeye, gözlük müşteriye.">
          <?php foreach ($esel as $i => [$metin, $keskin]): ?>
            <span class="esel-satir e<?= $i + 1 ?>" aria-hidden="true"><span class="harf"><?= pz_e($metin) ?></span><span class="keskin"><?= pz_e($keskin) ?></span></span>
          <?php endforeach; ?>
        </h1>
        <p class="esel-satir e6"><span class="harf">OptiFlow, gözlükçüler için yapılmış yönetim sistemidir. Reçeteyi elle yazmazsınız, SGK katkı payı ve ay sonu faturası hazırdır, atölye her siparişi panodan izler.</span><span class="keskin" aria-hidden="true">1,0</span></p>
      </div>
      <div class="hero-alt">
        <div class="hero-ctas">
          <a class="btn btn-red" href="kayit.php">30 gün ücretsiz deneyin</a>
          <?php if ($iletisimUrl !== ''): ?>
            <a class="btn btn-line btn-wa" href="<?= pz_e($iletisimUrl) ?>" target="_blank" rel="noopener"><?= $wa ? $ico['wa'] : '' ?><?= pz_e($iletisimAd) ?></a>
          <?php elseif ($video !== ''): ?>
            <a class="btn btn-line" href="<?= pz_e($video) ?>" target="_blank" rel="noopener">1 dakikalık videoyu izleyin</a>
          <?php else: ?>
            <a class="btn btn-line" href="#yol">Nasıl çalıştığını görün</a>
          <?php endif; ?>
        </div>
        <ul class="hero-not">
          <li>Kredi kartı gerekmez</li>
          <li><?= $p['kurulum_destegi'] ? 'Kurulumu sizinle birlikte yapıyoruz' : 'Teknik bilgi gerekmez' ?></li>
          <li>Her mağazaya ayrı veritabanı</li>
        </ul>
        <a class="surum-isaret" href="#surumler"><span class="rozet lite">Lite</span> tarayıcıda, her cihazda <span class="ayrac"></span> <span class="rozet pro">Pro</span> Windows'ta, Medula'nın yanında</a>
      </div>
    </div>
  </div>

  <section id="film" class="film on-night" data-film aria-labelledby="film-baslik">
    <div class="film-sahne">
      <div class="film-harfler" aria-hidden="true"><span style="--x:4%;--y:10%;--s:30;--d:1.4">E</span><span style="--x:82%;--y:8%;--s:18;--d:0.9">F</span><span style="--x:89%;--y:30%;--s:12;--d:1.2">P</span><span style="--x:6%;--y:62%;--s:14;--d:0.7">T</span><span style="--x:14%;--y:78%;--s:9;--d:1.6">O</span><span style="--x:78%;--y:70%;--s:16;--d:1.1">Z</span><span style="--x:47%;--y:4%;--s:8;--d:0.6">L</span><span style="--x:92%;--y:84%;--s:10;--d:1.3">D</span><span style="--x:30%;--y:88%;--s:7;--d:0.8">C</span><span style="--x:64%;--y:90%;--s:8;--d:1.5">E</span></div>
      <ol class="film-olcek" data-keskinlik aria-hidden="true"><li>0,1</li><li>0,2</li><li>0,4</li><li>0,6</li><li>0,8</li><li class="etkin">1,0</li></ol>
      <div class="film-basliklar">
        <div class="fb fb1" aria-hidden="true"><b>Dükkânınız biraz <em>bulanık</em> mı?</b><span>Defter, Excel, telefon… hepsi ayrı yerde.</span></div>
        <div class="fb fb2" aria-hidden="true"><b>Doğru camı <em>takalım.</em></b><span>Tık. Tık. Tık.</span></div>
        <div class="fb fb3"><h2 id="film-baslik">Bir dakikada <em>OptiFlow</em>.</h2><span>Reçeteden teslime, bir gözlüğün dükkândaki yolu. Sessiz izleyin ya da sesi açın.</span></div>
      </div>
      <div class="film-kutu">
        <div class="film-isik" aria-hidden="true"><img src="<?= e(asset('video/optiflow-kesit.webp')) ?>" width="1280" height="720" alt=""></div>
        <figure class="film-ekran" style="background-image:url('<?= e(asset('video/optiflow-kesit.webp')) ?>')">
          <video src="<?= e(asset('video/optiflow-kesit.mp4')) ?>" poster="<?= e(asset('video/optiflow-kesit.webp')) ?>" width="1280" height="720" autoplay muted loop playsinline preload="auto" aria-label="OptiFlow tanıtım videosundan kesit: sipariş listesi ve üç adımda teklif"></video>
        </figure>
        <div class="film-govde" aria-hidden="true"></div>
        <span class="film-kopru" aria-hidden="true"></span>
        <div class="film-kadran k-sag" aria-hidden="true"><div class="kd-ic"><svg viewBox="-100 -100 200 200" aria-hidden="true"><circle r="80.5" fill="none" stroke="#5c4b52" stroke-width="3"/><circle r="99" fill="none" stroke="currentColor" stroke-opacity=".25" stroke-width="1"/><line x1="95.0" y1="0.0" x2="99.0" y2="0.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="94.9" y1="5.0" x2="98.9" y2="5.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="94.5" y1="9.9" x2="98.5" y2="10.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="93.8" y1="14.9" x2="97.8" y2="15.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="92.9" y1="19.8" x2="96.8" y2="20.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="91.8" y1="24.6" x2="95.6" y2="25.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="90.4" y1="29.4" x2="94.2" y2="30.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="88.7" y1="34.0" x2="92.4" y2="35.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="86.8" y1="38.6" x2="90.4" y2="40.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="84.6" y1="43.1" x2="88.2" y2="44.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="82.3" y1="47.5" x2="85.7" y2="49.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="79.7" y1="51.7" x2="83.0" y2="53.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="76.9" y1="55.8" x2="80.1" y2="58.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="73.8" y1="59.8" x2="76.9" y2="62.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="70.6" y1="63.6" x2="73.6" y2="66.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="67.2" y1="67.2" x2="70.0" y2="70.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="63.6" y1="70.6" x2="66.2" y2="73.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="59.8" y1="73.8" x2="62.3" y2="76.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="55.8" y1="76.9" x2="58.2" y2="80.1" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="51.7" y1="79.7" x2="53.9" y2="83.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="47.5" y1="82.3" x2="49.5" y2="85.7" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="43.1" y1="84.6" x2="44.9" y2="88.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="38.6" y1="86.8" x2="40.3" y2="90.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="34.0" y1="88.7" x2="35.5" y2="92.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="29.4" y1="90.4" x2="30.6" y2="94.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="24.6" y1="91.8" x2="25.6" y2="95.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="19.8" y1="92.9" x2="20.6" y2="96.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="14.9" y1="93.8" x2="15.5" y2="97.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="9.9" y1="94.5" x2="10.3" y2="98.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="5.0" y1="94.9" x2="5.2" y2="98.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="0.0" y1="95.0" x2="0.0" y2="99.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-5.0" y1="94.9" x2="-5.2" y2="98.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-9.9" y1="94.5" x2="-10.3" y2="98.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-14.9" y1="93.8" x2="-15.5" y2="97.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-19.8" y1="92.9" x2="-20.6" y2="96.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-24.6" y1="91.8" x2="-25.6" y2="95.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-29.4" y1="90.4" x2="-30.6" y2="94.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-34.0" y1="88.7" x2="-35.5" y2="92.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-38.6" y1="86.8" x2="-40.3" y2="90.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-43.1" y1="84.6" x2="-44.9" y2="88.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-47.5" y1="82.3" x2="-49.5" y2="85.7" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-51.7" y1="79.7" x2="-53.9" y2="83.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-55.8" y1="76.9" x2="-58.2" y2="80.1" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-59.8" y1="73.8" x2="-62.3" y2="76.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-63.6" y1="70.6" x2="-66.2" y2="73.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-67.2" y1="67.2" x2="-70.0" y2="70.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-70.6" y1="63.6" x2="-73.6" y2="66.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-73.8" y1="59.8" x2="-76.9" y2="62.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-76.9" y1="55.8" x2="-80.1" y2="58.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-79.7" y1="51.7" x2="-83.0" y2="53.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-82.3" y1="47.5" x2="-85.7" y2="49.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-84.6" y1="43.1" x2="-88.2" y2="44.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-86.8" y1="38.6" x2="-90.4" y2="40.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-88.7" y1="34.0" x2="-92.4" y2="35.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-90.4" y1="29.4" x2="-94.2" y2="30.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-91.8" y1="24.6" x2="-95.6" y2="25.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-92.9" y1="19.8" x2="-96.8" y2="20.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-93.8" y1="14.9" x2="-97.8" y2="15.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-94.5" y1="9.9" x2="-98.5" y2="10.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-94.9" y1="5.0" x2="-98.9" y2="5.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-95.0" y1="0.0" x2="-99.0" y2="0.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-94.9" y1="-5.0" x2="-98.9" y2="-5.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-94.5" y1="-9.9" x2="-98.5" y2="-10.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-93.8" y1="-14.9" x2="-97.8" y2="-15.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-92.9" y1="-19.8" x2="-96.8" y2="-20.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-91.8" y1="-24.6" x2="-95.6" y2="-25.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-90.4" y1="-29.4" x2="-94.2" y2="-30.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-88.7" y1="-34.0" x2="-92.4" y2="-35.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-86.8" y1="-38.6" x2="-90.4" y2="-40.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-84.6" y1="-43.1" x2="-88.2" y2="-44.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-82.3" y1="-47.5" x2="-85.7" y2="-49.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-79.7" y1="-51.7" x2="-83.0" y2="-53.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-76.9" y1="-55.8" x2="-80.1" y2="-58.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-73.8" y1="-59.8" x2="-76.9" y2="-62.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-70.6" y1="-63.6" x2="-73.6" y2="-66.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-67.2" y1="-67.2" x2="-70.0" y2="-70.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-63.6" y1="-70.6" x2="-66.2" y2="-73.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-59.8" y1="-73.8" x2="-62.3" y2="-76.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-55.8" y1="-76.9" x2="-58.2" y2="-80.1" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-51.7" y1="-79.7" x2="-53.9" y2="-83.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-47.5" y1="-82.3" x2="-49.5" y2="-85.7" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-43.1" y1="-84.6" x2="-44.9" y2="-88.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-38.6" y1="-86.8" x2="-40.3" y2="-90.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-34.0" y1="-88.7" x2="-35.5" y2="-92.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-29.4" y1="-90.4" x2="-30.6" y2="-94.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-24.6" y1="-91.8" x2="-25.6" y2="-95.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-19.8" y1="-92.9" x2="-20.6" y2="-96.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-14.9" y1="-93.8" x2="-15.5" y2="-97.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-9.9" y1="-94.5" x2="-10.3" y2="-98.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-5.0" y1="-94.9" x2="-5.2" y2="-98.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-0.0" y1="-95.0" x2="-0.0" y2="-99.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="5.0" y1="-94.9" x2="5.2" y2="-98.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="9.9" y1="-94.5" x2="10.3" y2="-98.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="14.9" y1="-93.8" x2="15.5" y2="-97.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="19.8" y1="-92.9" x2="20.6" y2="-96.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="24.6" y1="-91.8" x2="25.6" y2="-95.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="29.4" y1="-90.4" x2="30.6" y2="-94.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="34.0" y1="-88.7" x2="35.5" y2="-92.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="38.6" y1="-86.8" x2="40.3" y2="-90.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="43.1" y1="-84.6" x2="44.9" y2="-88.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="47.5" y1="-82.3" x2="49.5" y2="-85.7" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="51.7" y1="-79.7" x2="53.9" y2="-83.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="55.8" y1="-76.9" x2="58.2" y2="-80.1" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="59.8" y1="-73.8" x2="62.3" y2="-76.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="63.6" y1="-70.6" x2="66.2" y2="-73.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="67.2" y1="-67.2" x2="70.0" y2="-70.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="70.6" y1="-63.6" x2="73.6" y2="-66.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="73.8" y1="-59.8" x2="76.9" y2="-62.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="76.9" y1="-55.8" x2="80.1" y2="-58.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="79.7" y1="-51.7" x2="83.0" y2="-53.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="82.3" y1="-47.5" x2="85.7" y2="-49.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="84.6" y1="-43.1" x2="88.2" y2="-44.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="86.8" y1="-38.6" x2="90.4" y2="-40.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="88.7" y1="-34.0" x2="92.4" y2="-35.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="90.4" y1="-29.4" x2="94.2" y2="-30.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="91.8" y1="-24.6" x2="95.6" y2="-25.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="92.9" y1="-19.8" x2="96.8" y2="-20.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="93.8" y1="-14.9" x2="97.8" y2="-15.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="94.5" y1="-9.9" x2="98.5" y2="-10.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="94.9" y1="-5.0" x2="98.9" y2="-5.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="83.0" y1="0.0" x2="91.0" y2="0.0" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="82.5" y1="8.7" x2="86.5" y2="9.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="81.2" y1="17.3" x2="85.1" y2="18.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="78.9" y1="25.6" x2="82.7" y2="26.9" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="75.8" y1="33.8" x2="79.5" y2="35.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="71.9" y1="41.5" x2="78.8" y2="45.5" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="67.1" y1="48.8" x2="70.4" y2="51.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="61.7" y1="55.5" x2="64.7" y2="58.2" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="55.5" y1="61.7" x2="58.2" y2="64.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="48.8" y1="67.1" x2="51.1" y2="70.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="41.5" y1="71.9" x2="45.5" y2="78.8" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="33.8" y1="75.8" x2="35.4" y2="79.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="25.6" y1="78.9" x2="26.9" y2="82.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="17.3" y1="81.2" x2="18.1" y2="85.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="8.7" y1="82.5" x2="9.1" y2="86.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="0.0" y1="83.0" x2="0.0" y2="91.0" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-8.7" y1="82.5" x2="-9.1" y2="86.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-17.3" y1="81.2" x2="-18.1" y2="85.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-25.6" y1="78.9" x2="-26.9" y2="82.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-33.8" y1="75.8" x2="-35.4" y2="79.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-41.5" y1="71.9" x2="-45.5" y2="78.8" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-48.8" y1="67.1" x2="-51.1" y2="70.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-55.5" y1="61.7" x2="-58.2" y2="64.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-61.7" y1="55.5" x2="-64.7" y2="58.2" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-67.1" y1="48.8" x2="-70.4" y2="51.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-71.9" y1="41.5" x2="-78.8" y2="45.5" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-75.8" y1="33.8" x2="-79.5" y2="35.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-78.9" y1="25.6" x2="-82.7" y2="26.9" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-81.2" y1="17.3" x2="-85.1" y2="18.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-82.5" y1="8.7" x2="-86.5" y2="9.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-83.0" y1="0.0" x2="-91.0" y2="0.0" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-82.5" y1="-8.7" x2="-86.5" y2="-9.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-81.2" y1="-17.3" x2="-85.1" y2="-18.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-78.9" y1="-25.6" x2="-82.7" y2="-26.9" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-75.8" y1="-33.8" x2="-79.5" y2="-35.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-71.9" y1="-41.5" x2="-78.8" y2="-45.5" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-67.1" y1="-48.8" x2="-70.4" y2="-51.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-61.7" y1="-55.5" x2="-64.7" y2="-58.2" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-55.5" y1="-61.7" x2="-58.2" y2="-64.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-48.8" y1="-67.1" x2="-51.1" y2="-70.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-41.5" y1="-71.9" x2="-45.5" y2="-78.8" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-33.8" y1="-75.8" x2="-35.4" y2="-79.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-25.6" y1="-78.9" x2="-26.9" y2="-82.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-17.3" y1="-81.2" x2="-18.1" y2="-85.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-8.7" y1="-82.5" x2="-9.1" y2="-86.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-0.0" y1="-83.0" x2="-0.0" y2="-91.0" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="8.7" y1="-82.5" x2="9.1" y2="-86.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="17.3" y1="-81.2" x2="18.1" y2="-85.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="25.6" y1="-78.9" x2="26.9" y2="-82.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="33.8" y1="-75.8" x2="35.4" y2="-79.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="41.5" y1="-71.9" x2="45.5" y2="-78.8" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="48.8" y1="-67.1" x2="51.1" y2="-70.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="55.5" y1="-61.7" x2="58.2" y2="-64.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="61.7" y1="-55.5" x2="64.7" y2="-58.2" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="67.1" y1="-48.8" x2="70.4" y2="-51.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="71.9" y1="-41.5" x2="78.8" y2="-45.5" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="75.8" y1="-33.8" x2="79.5" y2="-35.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="78.9" y1="-25.6" x2="82.7" y2="-26.9" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="81.2" y1="-17.3" x2="85.1" y2="-18.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="82.5" y1="-8.7" x2="86.5" y2="-9.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><text x="22.9" y="-83.3" text-anchor="middle">0</text><text x="62.6" y="-60.4" text-anchor="middle">+1</text><text x="85.5" y="-20.7" text-anchor="middle">+2</text><text x="85.5" y="25.1" text-anchor="middle">+3</text><text x="62.6" y="64.8" text-anchor="middle">+4</text><text x="22.9" y="87.7" text-anchor="middle">+5</text><text x="-22.9" y="87.7" text-anchor="middle">±6</text><text x="-62.6" y="64.8" text-anchor="middle">−5</text><text x="-85.5" y="25.1" text-anchor="middle">−4</text><text x="-85.5" y="-20.7" text-anchor="middle">−3</text><text x="-62.6" y="-60.4" text-anchor="middle">−2</text><text x="-22.9" y="-83.3" text-anchor="middle">−1</text></svg></div></div>
        <div class="film-kadran k-sol" aria-hidden="true"><div class="kd-ic"><svg viewBox="-100 -100 200 200" aria-hidden="true"><circle r="80.5" fill="none" stroke="#5c4b52" stroke-width="3"/><circle r="99" fill="none" stroke="currentColor" stroke-opacity=".25" stroke-width="1"/><line x1="95.0" y1="0.0" x2="99.0" y2="0.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="94.9" y1="5.0" x2="98.9" y2="5.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="94.5" y1="9.9" x2="98.5" y2="10.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="93.8" y1="14.9" x2="97.8" y2="15.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="92.9" y1="19.8" x2="96.8" y2="20.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="91.8" y1="24.6" x2="95.6" y2="25.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="90.4" y1="29.4" x2="94.2" y2="30.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="88.7" y1="34.0" x2="92.4" y2="35.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="86.8" y1="38.6" x2="90.4" y2="40.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="84.6" y1="43.1" x2="88.2" y2="44.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="82.3" y1="47.5" x2="85.7" y2="49.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="79.7" y1="51.7" x2="83.0" y2="53.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="76.9" y1="55.8" x2="80.1" y2="58.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="73.8" y1="59.8" x2="76.9" y2="62.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="70.6" y1="63.6" x2="73.6" y2="66.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="67.2" y1="67.2" x2="70.0" y2="70.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="63.6" y1="70.6" x2="66.2" y2="73.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="59.8" y1="73.8" x2="62.3" y2="76.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="55.8" y1="76.9" x2="58.2" y2="80.1" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="51.7" y1="79.7" x2="53.9" y2="83.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="47.5" y1="82.3" x2="49.5" y2="85.7" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="43.1" y1="84.6" x2="44.9" y2="88.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="38.6" y1="86.8" x2="40.3" y2="90.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="34.0" y1="88.7" x2="35.5" y2="92.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="29.4" y1="90.4" x2="30.6" y2="94.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="24.6" y1="91.8" x2="25.6" y2="95.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="19.8" y1="92.9" x2="20.6" y2="96.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="14.9" y1="93.8" x2="15.5" y2="97.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="9.9" y1="94.5" x2="10.3" y2="98.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="5.0" y1="94.9" x2="5.2" y2="98.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="0.0" y1="95.0" x2="0.0" y2="99.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-5.0" y1="94.9" x2="-5.2" y2="98.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-9.9" y1="94.5" x2="-10.3" y2="98.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-14.9" y1="93.8" x2="-15.5" y2="97.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-19.8" y1="92.9" x2="-20.6" y2="96.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-24.6" y1="91.8" x2="-25.6" y2="95.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-29.4" y1="90.4" x2="-30.6" y2="94.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-34.0" y1="88.7" x2="-35.5" y2="92.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-38.6" y1="86.8" x2="-40.3" y2="90.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-43.1" y1="84.6" x2="-44.9" y2="88.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-47.5" y1="82.3" x2="-49.5" y2="85.7" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-51.7" y1="79.7" x2="-53.9" y2="83.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-55.8" y1="76.9" x2="-58.2" y2="80.1" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-59.8" y1="73.8" x2="-62.3" y2="76.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-63.6" y1="70.6" x2="-66.2" y2="73.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-67.2" y1="67.2" x2="-70.0" y2="70.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-70.6" y1="63.6" x2="-73.6" y2="66.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-73.8" y1="59.8" x2="-76.9" y2="62.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-76.9" y1="55.8" x2="-80.1" y2="58.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-79.7" y1="51.7" x2="-83.0" y2="53.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-82.3" y1="47.5" x2="-85.7" y2="49.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-84.6" y1="43.1" x2="-88.2" y2="44.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-86.8" y1="38.6" x2="-90.4" y2="40.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-88.7" y1="34.0" x2="-92.4" y2="35.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-90.4" y1="29.4" x2="-94.2" y2="30.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-91.8" y1="24.6" x2="-95.6" y2="25.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-92.9" y1="19.8" x2="-96.8" y2="20.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-93.8" y1="14.9" x2="-97.8" y2="15.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-94.5" y1="9.9" x2="-98.5" y2="10.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-94.9" y1="5.0" x2="-98.9" y2="5.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-95.0" y1="0.0" x2="-99.0" y2="0.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-94.9" y1="-5.0" x2="-98.9" y2="-5.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-94.5" y1="-9.9" x2="-98.5" y2="-10.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-93.8" y1="-14.9" x2="-97.8" y2="-15.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-92.9" y1="-19.8" x2="-96.8" y2="-20.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-91.8" y1="-24.6" x2="-95.6" y2="-25.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-90.4" y1="-29.4" x2="-94.2" y2="-30.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-88.7" y1="-34.0" x2="-92.4" y2="-35.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-86.8" y1="-38.6" x2="-90.4" y2="-40.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-84.6" y1="-43.1" x2="-88.2" y2="-44.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-82.3" y1="-47.5" x2="-85.7" y2="-49.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-79.7" y1="-51.7" x2="-83.0" y2="-53.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-76.9" y1="-55.8" x2="-80.1" y2="-58.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-73.8" y1="-59.8" x2="-76.9" y2="-62.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-70.6" y1="-63.6" x2="-73.6" y2="-66.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-67.2" y1="-67.2" x2="-70.0" y2="-70.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-63.6" y1="-70.6" x2="-66.2" y2="-73.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-59.8" y1="-73.8" x2="-62.3" y2="-76.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-55.8" y1="-76.9" x2="-58.2" y2="-80.1" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-51.7" y1="-79.7" x2="-53.9" y2="-83.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-47.5" y1="-82.3" x2="-49.5" y2="-85.7" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-43.1" y1="-84.6" x2="-44.9" y2="-88.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-38.6" y1="-86.8" x2="-40.3" y2="-90.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-34.0" y1="-88.7" x2="-35.5" y2="-92.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-29.4" y1="-90.4" x2="-30.6" y2="-94.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-24.6" y1="-91.8" x2="-25.6" y2="-95.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-19.8" y1="-92.9" x2="-20.6" y2="-96.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-14.9" y1="-93.8" x2="-15.5" y2="-97.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-9.9" y1="-94.5" x2="-10.3" y2="-98.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-5.0" y1="-94.9" x2="-5.2" y2="-98.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="-0.0" y1="-95.0" x2="-0.0" y2="-99.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="5.0" y1="-94.9" x2="5.2" y2="-98.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="9.9" y1="-94.5" x2="10.3" y2="-98.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="14.9" y1="-93.8" x2="15.5" y2="-97.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="19.8" y1="-92.9" x2="20.6" y2="-96.8" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="24.6" y1="-91.8" x2="25.6" y2="-95.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="29.4" y1="-90.4" x2="30.6" y2="-94.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="34.0" y1="-88.7" x2="35.5" y2="-92.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="38.6" y1="-86.8" x2="40.3" y2="-90.4" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="43.1" y1="-84.6" x2="44.9" y2="-88.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="47.5" y1="-82.3" x2="49.5" y2="-85.7" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="51.7" y1="-79.7" x2="53.9" y2="-83.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="55.8" y1="-76.9" x2="58.2" y2="-80.1" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="59.8" y1="-73.8" x2="62.3" y2="-76.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="63.6" y1="-70.6" x2="66.2" y2="-73.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="67.2" y1="-67.2" x2="70.0" y2="-70.0" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="70.6" y1="-63.6" x2="73.6" y2="-66.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="73.8" y1="-59.8" x2="76.9" y2="-62.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="76.9" y1="-55.8" x2="80.1" y2="-58.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="79.7" y1="-51.7" x2="83.0" y2="-53.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="82.3" y1="-47.5" x2="85.7" y2="-49.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="84.6" y1="-43.1" x2="88.2" y2="-44.9" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="86.8" y1="-38.6" x2="90.4" y2="-40.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="88.7" y1="-34.0" x2="92.4" y2="-35.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="90.4" y1="-29.4" x2="94.2" y2="-30.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="91.8" y1="-24.6" x2="95.6" y2="-25.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="92.9" y1="-19.8" x2="96.8" y2="-20.6" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="93.8" y1="-14.9" x2="97.8" y2="-15.5" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="94.5" y1="-9.9" x2="98.5" y2="-10.3" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="94.9" y1="-5.0" x2="98.9" y2="-5.2" stroke="currentColor" stroke-opacity=".22" stroke-width=".8"/><line x1="83.0" y1="0.0" x2="91.0" y2="0.0" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="82.5" y1="8.7" x2="86.5" y2="9.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="81.2" y1="17.3" x2="85.1" y2="18.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="78.9" y1="25.6" x2="82.7" y2="26.9" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="75.8" y1="33.8" x2="79.5" y2="35.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="71.9" y1="41.5" x2="78.8" y2="45.5" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="67.1" y1="48.8" x2="70.4" y2="51.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="61.7" y1="55.5" x2="64.7" y2="58.2" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="55.5" y1="61.7" x2="58.2" y2="64.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="48.8" y1="67.1" x2="51.1" y2="70.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="41.5" y1="71.9" x2="45.5" y2="78.8" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="33.8" y1="75.8" x2="35.4" y2="79.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="25.6" y1="78.9" x2="26.9" y2="82.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="17.3" y1="81.2" x2="18.1" y2="85.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="8.7" y1="82.5" x2="9.1" y2="86.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="0.0" y1="83.0" x2="0.0" y2="91.0" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-8.7" y1="82.5" x2="-9.1" y2="86.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-17.3" y1="81.2" x2="-18.1" y2="85.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-25.6" y1="78.9" x2="-26.9" y2="82.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-33.8" y1="75.8" x2="-35.4" y2="79.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-41.5" y1="71.9" x2="-45.5" y2="78.8" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-48.8" y1="67.1" x2="-51.1" y2="70.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-55.5" y1="61.7" x2="-58.2" y2="64.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-61.7" y1="55.5" x2="-64.7" y2="58.2" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-67.1" y1="48.8" x2="-70.4" y2="51.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-71.9" y1="41.5" x2="-78.8" y2="45.5" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-75.8" y1="33.8" x2="-79.5" y2="35.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-78.9" y1="25.6" x2="-82.7" y2="26.9" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-81.2" y1="17.3" x2="-85.1" y2="18.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-82.5" y1="8.7" x2="-86.5" y2="9.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-83.0" y1="0.0" x2="-91.0" y2="0.0" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-82.5" y1="-8.7" x2="-86.5" y2="-9.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-81.2" y1="-17.3" x2="-85.1" y2="-18.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-78.9" y1="-25.6" x2="-82.7" y2="-26.9" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-75.8" y1="-33.8" x2="-79.5" y2="-35.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-71.9" y1="-41.5" x2="-78.8" y2="-45.5" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-67.1" y1="-48.8" x2="-70.4" y2="-51.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-61.7" y1="-55.5" x2="-64.7" y2="-58.2" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-55.5" y1="-61.7" x2="-58.2" y2="-64.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-48.8" y1="-67.1" x2="-51.1" y2="-70.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-41.5" y1="-71.9" x2="-45.5" y2="-78.8" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="-33.8" y1="-75.8" x2="-35.4" y2="-79.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-25.6" y1="-78.9" x2="-26.9" y2="-82.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-17.3" y1="-81.2" x2="-18.1" y2="-85.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-8.7" y1="-82.5" x2="-9.1" y2="-86.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="-0.0" y1="-83.0" x2="-0.0" y2="-91.0" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="8.7" y1="-82.5" x2="9.1" y2="-86.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="17.3" y1="-81.2" x2="18.1" y2="-85.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="25.6" y1="-78.9" x2="26.9" y2="-82.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="33.8" y1="-75.8" x2="35.4" y2="-79.5" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="41.5" y1="-71.9" x2="45.5" y2="-78.8" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="48.8" y1="-67.1" x2="51.1" y2="-70.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="55.5" y1="-61.7" x2="58.2" y2="-64.7" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="61.7" y1="-55.5" x2="64.7" y2="-58.2" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="67.1" y1="-48.8" x2="70.4" y2="-51.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="71.9" y1="-41.5" x2="78.8" y2="-45.5" stroke="currentColor" stroke-opacity="0.85" stroke-width="1.2"/><line x1="75.8" y1="-33.8" x2="79.5" y2="-35.4" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="78.9" y1="-25.6" x2="82.7" y2="-26.9" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="81.2" y1="-17.3" x2="85.1" y2="-18.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><line x1="82.5" y1="-8.7" x2="86.5" y2="-9.1" stroke="currentColor" stroke-opacity="0.45" stroke-width="0.7"/><text x="22.9" y="-83.3" text-anchor="middle">0</text><text x="62.6" y="-60.4" text-anchor="middle">+1</text><text x="85.5" y="-20.7" text-anchor="middle">+2</text><text x="85.5" y="25.1" text-anchor="middle">+3</text><text x="62.6" y="64.8" text-anchor="middle">+4</text><text x="22.9" y="87.7" text-anchor="middle">+5</text><text x="-22.9" y="87.7" text-anchor="middle">±6</text><text x="-62.6" y="64.8" text-anchor="middle">−5</text><text x="-85.5" y="25.1" text-anchor="middle">−4</text><text x="-85.5" y="-20.7" text-anchor="middle">−3</text><text x="-62.6" y="-60.4" text-anchor="middle">−2</text><text x="-22.9" y="-83.3" text-anchor="middle">−1</text></svg></div></div>
        <span class="film-lcd l-sag" aria-hidden="true">SAĞ <b data-diyopter-sag>NET</b></span>
        <span class="film-lcd l-sol" aria-hidden="true">SOL <b data-diyopter-sol>NET</b></span>
            <figure class="film-kart k1"><img src="<?= e(asset('onizleme/siparis.webp')) ?>" width="1140" height="800" loading="lazy" decoding="async" alt=""><figcaption>Sipariş listesi</figcaption></figure>
            <figure class="film-kart k2"><img src="<?= e(asset('onizleme/atolye.webp')) ?>" width="1280" height="800" loading="lazy" decoding="async" alt=""><figcaption>Atölye panosu</figcaption></figure>
            <figure class="film-kart k3"><img src="<?= e(asset('onizleme/sgk.webp')) ?>" width="1280" height="800" loading="lazy" decoding="async" alt=""><figcaption>SGK faturası</figcaption></figure>
            <figure class="film-kart k4"><img src="<?= e(asset('onizleme/telefon.webp')) ?>" width="780" height="1560" loading="lazy" decoding="async" alt=""><figcaption>Telefonda takip</figcaption></figure>
      </div>
      <div class="film-son">
        <a class="film-oynat" href="<?= e(asset('video/optiflow-tanitim.mp4')) ?>" target="_blank" rel="noopener" data-film-ac><i><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M7 4.5v15l12.5-7.5z"/></svg></i>Sesli izle · 57 sn</a>
        <?php if ($p['instagram'] !== ''): ?><a class="film-insta" href="https://instagram.com/<?= pz_e($p['instagram']) ?>" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor"/></svg>@<?= pz_e($p['instagram']) ?></a><?php endif; ?>
      </div>
    </div>
  </section>
  <dialog class="film-dialog" data-film-dialog aria-label="OptiFlow tanıtım videosu">
    <button type="button" class="film-kapat" data-film-kapat aria-label="Kapat">&times;</button>
    <video controls playsinline preload="none" data-src="<?= e(asset('video/optiflow-tanitim.mp4')) ?>" poster="<?= e(asset('video/optiflow-kesit.webp')) ?>"></video>
  </dialog>

  <section id="surumler" class="gece on-night">
    <div class="wrap">
      <div class="bas">
        <h2>İki sürüm, tek sistem.</h2>
        <p class="lead">Aynı mağaza, aynı veriler. Lite tarayıcıda her cihazda açılır; Pro, Windows'ta Medula'yı yanına alır. Pro'da Lite'ın her şeyi vardır.</p>
      </div>
      <div class="surum-grid">
        <article class="surum">
          <div class="surum-bas">
            <span class="rozet lite">Lite</span>
            <h3>Tarayıcıda, her cihazda</h3>
            <p>Kurulum yok. Bilgisayarda, tablette ve telefonda optiflow.com.tr'den açılır; telefonun ana ekranına uygulama gibi eklenir.</p>
            <div class="surum-eylem">
              <a class="btn btn-line" href="magaza-giris.php">Tarayıcıda giriş yap</a>
              <small>Hesabınız yok mu? <a href="kayit.php">30 gün ücretsiz deneyin</a></small>
            </div>
          </div>
          <div class="sahne">
            <figure class="tarayici">
              <div class="tarayici-ust"><span class="nokta"></span><span class="nokta"></span><span class="nokta"></span><span class="adres">optiflow.com.tr</span></div>
              <img src="<?= e(asset('onizleme/liste.webp')) ?>" width="1280" height="800" loading="lazy" decoding="async" alt="OptiFlow Lite, tarayıcıda sipariş listesi ve günün özeti">
            </figure>
            <figure class="cep">
              <img src="<?= e(asset('onizleme/telefon.webp')) ?>" width="390" height="780" loading="lazy" decoding="async" alt="OptiFlow Lite, telefonda siparişler ekranı">
            </figure>
          </div>
        </article>
        <article class="surum pro">
          <div class="surum-bas">
            <span class="rozet pro">Pro</span>
            <h3>Windows'ta, Medula'nın yanında</h3>
            <p>Medula Optik ve OptiFlow aynı pencerede. Reçete tek tuşla siparişe gelir, SGK hakkı Medula ekranından sorgulanır, karekod okuyucu doğrudan çalışır.</p>
            <div class="surum-eylem">
              <a class="btn btn-red" href="indir.php"><?= $ico['down'] ?>Windows için indir</a>
              <small><?= $indir ? 'Sürüm ' . pz_e(indir_etiket($indir)) . ' · ' : '' ?>Windows 10 / 11 · ücretsiz</small>
            </div>
          </div>
          <figure class="masaustu" role="img" aria-label="OptiFlow Pro penceresi: solda Medula reçetesi, sağda OptiFlow sipariş ekranı">
            <div class="ms-ust"><span class="ms-ad">OptiFlow Pro · Örnek Optik</span><span class="ms-dug"><i></i><i></i><i></i></span></div>
            <div class="ms-arac">
              <span class="sekme">Medula Optik</span><span class="sekme acik">OptiFlow</span>
              <span class="bosluk"></span>
              <span class="dug">Barkod</span><span class="dug">Hak sorgula</span><span class="dug kirmizi">Aktar</span>
            </div>
            <div class="ms-icerik">
              <div class="ms-medula">
                <b>Reçete İşlemleri</b>
                <small>e-Reçete 1A2B3C4 · Uzak gözlük</small>
                <div class="kutular">
                  <span></span><i>Sferik</i><i>Silend.</i><i>Aks</i>
                  <i>Sağ</i><span class="kutu">+1,25</span><span class="kutu">−0,75</span><span class="kutu">45</span>
                  <i>Sol</i><span class="kutu">+1,00</span><span class="kutu">−0,50</span><span class="kutu">130</span>
                </div>
                <div class="ok">Aktar</div>
              </div>
              <img src="<?= e(asset('onizleme/siparis.webp')) ?>" width="1140" height="800" loading="lazy" decoding="async" alt="OptiFlow sipariş ekranı: aktarılan reçetenin siparişi">
            </div>
          </figure>
        </article>
      </div>

      <div class="tablo-kap karsi-kap">
        <table class="karsi">
          <thead><tr><th scope="col"></th><th scope="col">Lite <small>tarayıcı</small></th><th scope="col" class="p">Pro <small>Windows</small></th></tr></thead>
          <tbody>
            <tr class="grupcuk"><td colspan="3">İkisinde de</td></tr>
            <tr><td>Sipariş, müşteri, reçete geçmişi, atölye panosu</td><td>✓</td><td>✓</td></tr>
            <tr><td>WhatsApp mesajları, hatırlatmalar, "Siparişim nerede?"</td><td>✓</td><td>✓</td></tr>
            <tr><td>Kasa, bakiye, kâr raporu, stok ve etiket</td><td>✓</td><td>✓</td></tr>
            <tr><td>SGK ay sonu faturası, Medula PDF dökümüyle karşılaştırma</td><td>✓</td><td>✓</td></tr>
            <tr><td>Garanti kartı, alış faturası, ödeme linki</td><td>✓</td><td>✓</td></tr>
            <tr><td>Hızlı satış: barkodla sepet, parçalı ödeme, fiş</td><td>✓ <small>kamerayla</small></td><td>✓ <small>USB okuyucuyla</small></td></tr>
            <tr><td>Telefon ve tabletten erişim</td><td>✓</td><td>✓ <small>aynı hesapla</small></td></tr>
            <tr class="grupcuk"><td colspan="3">Farklar</td></tr>
            <tr class="fark-satir"><td>Medula reçetesini siparişe aktarma</td><td class="yok">Elle yazılır</td><td>Tek tuş</td></tr>
            <tr class="fark-satir"><td>SGK hak kontrolü</td><td class="yok">Ekranı yapıştırarak</td><td>Medula ekranından tek tuş</td></tr>
            <tr class="fark-satir"><td>ÜTS karekod etiketi ve imha dosyası</td><td class="yok">—</td><td>✓</td></tr>
            <tr class="fark-satir"><td>USB barkod / karekod okuyucu modu</td><td class="yok">—</td><td>✓</td></tr>
            <tr class="fark-satir"><td>İnternet kesilince açık siparişleri görme</td><td class="yok">—</td><td>✓ <small>şifreli kopya</small></td></tr>
            <tr class="fark-satir"><td>Medula listesini OptiFlow'la karşılaştırma</td><td class="yok">Yapıştırarak</td><td>Ekrandan tek tuş</td></tr>
          </tbody>
        </table>
      </div>
      <div class="gizlilik"><?= $ico['lock'] ?><span>SGK şifreniz <b>sunucumuza hiç gelmez</b>. İsterseniz yalnızca kendi bilgisayarınızda, Windows şifrelemesiyle saklanır ve Medula girişine kendiliğinden yazılır. Reçete yalnızca "Aktar"a bastığınızda gelir.</span></div>
    </div>
  </section>

  <section id="fark" style="padding-bottom:0">
    <div class="wrap">
      <div class="bas">
        <h2>Tezgâhta her gün tekrar eden işler, artık kendiliğinden.</h2>
      </div>
      <div class="tablo-kap">
        <table class="fark">
          <thead><tr><th scope="col">İş</th><th scope="col">Bugüne kadar</th><th scope="col" class="ile">OptiFlow ile</th></tr></thead>
          <tbody>
            <tr><td>Reçete</td><td class="once">Medula ekranından değerler tek tek okunup yazılır</td><td class="ile">"Aktar"a basılır, değerler sıra hatası olmadan siparişe gelir</td></tr>
            <tr><td>SGK katkı payı</td><td class="once">Kullanım şekline göre hesap makinesiyle bulunur</td><td class="ile">Reçeteye göre önerilir, kalan bakiye kendiliğinden çıkar</td></tr>
            <tr><td>"Gözlüğüm hazır mı?"</td><td class="once">Telefon çalar, sipariş defterde aranır</td><td class="ile">Müşteri fişteki karekodu okutur ya da WhatsApp'tan haber alır</td></tr>
            <tr><td>Gelmeyen cam</td><td class="once">Hangi camın kaç gündür beklediği akılda tutulur</td><td class="ile">Atölye panosunda geciken iş kırmızıyla öne çıkar</td></tr>
            <tr><td>Ay sonu SGK faturası</td><td class="once">Reçeteler tek tek sayılır, Medula listesiyle elle karşılaştırılır</td><td class="ile">Ayın reçeteleri tek faturada toplanır, Medula dökümüyle adet kontrol edilir</td></tr>
            <tr><td>Garanti</td><td class="once">Kâğıt garanti belgesi kaybolur, tarih bilinmez</td><td class="ile">Karekodlu kart: müşteri kalan süreyi telefonundan görür</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section id="yol">
    <div class="wrap">
      <div class="bas">
        <h2>Bir gözlüğün dükkândaki yolu, baştan sona tek ekranda.</h2>
        <p class="lead">Hiçbir adımda değer yeniden yazılmaz, hesap elle yapılmaz.</p>
      </div>
      <div class="yol-kap">
      <span class="yol-dolum" aria-hidden="true"></span>
      <span class="yol-gezgin" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.5" cy="14" r="3.6"/><circle cx="17.5" cy="14" r="3.6"/><path d="M10.1 14c1.2-1 2.6-1 3.8 0M2.9 14 4.5 8.5M21.1 14 19.5 8.5"/></svg></span>
      <ol class="yol" style="--n:<?= count($yol) ?>">
        <?php foreach ($yol as $k => [$b, $t]): ?>
          <li style="--k:<?= (int) $k ?>"><h3><?= pz_e($b) ?></h3><p><?= pz_e($t) ?></p></li>
        <?php endforeach; ?>
      </ol>
      </div>
    </div>
  </section>

  <section id="icerde">
    <div class="wrap">
      <div class="bas">
        <h2>Atölye neyin beklediğini görür, müşteri telefonundan takip eder.</h2>
        <p class="lead">Aynı sipariş iki ayrı ekranda: arkada iş listesi, müşterinin cebinde sade bir durum sayfası.</p>
      </div>
      <div class="iki">
        <div>
          <figure class="tarayici">
            <div class="tarayici-ust"><span class="nokta"></span><span class="nokta"></span><span class="nokta"></span><span class="adres">optiflow.com.tr/workshop.php</span></div>
            <img src="<?= e(asset('onizleme/atolye.webp')) ?>" width="1280" height="800" loading="lazy" decoding="async" alt="Atölye panosu: cam bekliyor, montajda, kalite kontrol ve hazır sütunları">
          </figure>
          <p class="alt-not">Duvardaki atölye ekranında da aynı liste döner; fiyat, telefon ve reçete görünmez.</p>
        </div>
        <div class="telefon" role="img" aria-label="Müşterinin telefonunda sipariş durumu ve garanti bilgisi örneği">
          <div class="tel-bildirim" aria-hidden="true"><i><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm5.3 14.2c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.3-.7-2.8-1.1-4.6-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.9s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.3.4c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.2 1 2.1 1.4 2.4 1.5.3.1.5.1.6-.1l.9-1.1c.2-.3.4-.2.6-.1l1.9.9c.3.1.5.2.5.3.1.2.1.7-.1 1.4z"/></svg></i><span><b>Örnek Optik · WhatsApp</b>Gözlüğünüz hazır, teslim alabilirsiniz.</span></div>
          <div class="tel-ust"><i></i>Örnek Optik</div>
          <div class="tel-kart">
            <small>#01270</small>
            <h4>Gözlüğünüz hazır</h4>
            <p>Siparişiniz tamamlandı, mağazamızdan teslim alabilirsiniz.</p>
            <div class="adimlar"><span class="on"></span><span class="on"></span><span class="on"></span><span></span></div>
            <div class="tel-adim">Adım 3 / 4</div>
            <div class="tel-dugme"><span>Ara</span><span>Yol tarifi</span></div>
          </div>
          <div class="tel-garanti"><span>Garanti · çerçeve</span><b>1 yıl 11 ay</b></div>
        </div>
      </div>
    </div>
  </section>

  <section id="galeri" style="padding-top:0">
    <div class="wrap">
      <div class="bas">
        <h2>Uygulamanın içinden.</h2>
        <p class="lead">Örnek verili bir deneme mağazasından alınmış gerçek ekranlar. Yana kaydırın.</p>
      </div>
    </div>
    <div class="serit" tabindex="0" aria-label="Uygulama ekranları">
      <figure class="tarayici">
        <div class="tarayici-ust"><span class="nokta"></span><span class="nokta"></span><span class="nokta"></span><span class="adres">Sipariş #00001</span></div>
        <img src="<?= e(asset('onizleme/siparis.webp')) ?>" width="1140" height="800" loading="lazy" decoding="async" alt="Sipariş ekranı: müşteri, aşamalar, durum değiştirme">
        <figcaption><b>Sipariş</b> Aşamalar, kim yaptı, reçete ve camlar, WhatsApp ve fiş tek ekranda.</figcaption>
      </figure>
      <figure class="tarayici">
        <div class="tarayici-ust"><span class="nokta"></span><span class="nokta"></span><span class="nokta"></span><span class="adres">SGK ay sonu faturası</span></div>
        <img src="<?= e(asset('onizleme/sgk.webp')) ?>" width="1280" height="800" loading="lazy" decoding="async" alt="SGK ay sonu faturası ekranı">
        <figcaption><b>SGK ay sonu</b> Faturalanacak reçeteler, Medula'ya işlenmemişler ve döküm karşılaştırması.</figcaption>
      </figure>
      <figure class="tarayici">
        <div class="tarayici-ust"><span class="nokta"></span><span class="nokta"></span><span class="nokta"></span><span class="adres">Garantiler</span></div>
        <img src="<?= e(asset('onizleme/garanti.webp')) ?>" width="1280" height="800" loading="lazy" decoding="async" alt="Garantiler listesi">
        <figcaption><b>Garantiler</b> Geçerli, bitecek ve talep açılmış garantiler; karekodlu kart.</figcaption>
      </figure>
      <figure class="tarayici">
        <div class="tarayici-ust"><span class="nokta"></span><span class="nokta"></span><span class="nokta"></span><span class="adres">Siparişler</span></div>
        <img src="<?= e(asset('onizleme/liste.webp')) ?>" width="1280" height="800" loading="lazy" decoding="async" alt="Siparişler ana ekranı">
        <figcaption><b>Günün özeti</b> Bugün teslim sözü verilen, geciken, haber verilecek ve tahsilat bekleyenler.</figcaption>
      </figure>
    </div>
  </section>

  <section id="ay-sonu" style="padding-top:0">
    <div class="wrap ay-grid">
      <div>
        <h2>Ay sonunda SGK'ya tek fatura, sayısı Medula'yla tutarak.</h2>
        <p class="lead">Reçeteyi Medula'ya işlediğinizde siparişte işaretlersiniz. Ay sonunda o ayın reçeteleri tek faturada toplanır.</p>
        <ul class="ay-liste">
          <li><span><b>Medula dökümünü yükleyin.</b> PDF'teki reçete numaraları OptiFlow'daki işaretlerle karşılaştırılır.</span></li>
          <li><span><b>Eksik kalanı görün.</b> Medula'da olup işaretlenmemiş reçete siparişiyle birlikte listelenir.</span></li>
          <li><span><b>Faturayı ekiyle alın.</b> Fatura taslağı ve hasta, e-reçete no ve tutarlı reçete dökümü hazır.</span></li>
        </ul>
      </div>
      <div class="kontrol" role="img" aria-label="Ay sonu kontrol örneği: Medula dökümü ile OptiFlow sayıları">
        <div class="kontrol-ust">
          <div><small>Medula dökümü</small><b>48</b></div>
          <div><small>OptiFlow'da işaretli</small><b>47</b></div>
          <div class="fark-hucre"><small>Fark</small><b>1</b></div>
        </div>
        <table class="dokum">
          <tr><td class="no">2K7M4QX</td><td>Ayşe Y.</td><td class="tl">150,00</td></tr>
          <tr><td class="no">5D6E7F8</td><td>Mehmet D.</td><td class="tl">150,00</td></tr>
          <tr class="eksik"><td class="no">9G8H7J6</td><td>Zeynep Ç.</td><td>işaretlenmemiş</td></tr>
          <tr><td class="no">3R8T1WB</td><td>Ali K.</td><td class="tl">200,00</td></tr>
        </table>
      </div>
    </div>
  </section>

  <section id="neler">
    <div class="wrap">
      <div class="bas">
        <h2>Sipariş takibinden fazlası: dükkânın tamamı.</h2>
        <p class="lead">Her modül, bir optik atölyesindeki gerçek bir günlük işi üstlenmek için yapıldı. İhtiyacınız olanı açarsınız.</p>
      </div>
      <?php foreach ($gruplar as [$baslik, $ozellikler]): ?>
        <div class="grup">
          <h3><?= pz_e($baslik) ?></h3>
          <dl>
            <?php foreach ($ozellikler as [$ad, $acik]): ?>
              <div><dt><?= pz_e($ad) ?></dt><dd><?= pz_e($acik) ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section id="rehber" style="padding-top:0">
    <div class="wrap">
      <div class="rehber">
        <div>
          <h3 style="font-family:var(--serif);font-weight:800;font-size:1.6rem">Gözlükçüler için rehber</h3>
          <p>Medula reçetesi, SGK ay sonu faturası, atölye takibi ve garanti üzerine tezgâhtan yazılmış sade yazılar.</p>
          <?php $sonYazilar = function_exists('rehber_hepsi') ? array_slice(rehber_hepsi(true), 0, 4) : []; if ($sonYazilar): ?>
            <ul class="rehber-liste"><?php foreach ($sonYazilar as $ry): ?><li><a href="<?= pz_e(rehber_url($ry['slug'])) ?>"><?= pz_e($ry['baslik']) ?></a></li><?php endforeach; ?></ul>
          <?php endif; ?>
        </div>
        <a class="btn btn-line" href="rehber.php">Rehberi okuyun</a>
      </div>
    </div>
  </section>

  <div class="hikaye">
    <div class="wrap">
      <p class="buyuk" data-kelime>OptiFlow bir yazılım ofisinde değil, her gün reçete girilen, cam beklenen ve "gözlüğüm hazır mı?" diye aranan bir optik atölyesinde doğdu.</p>
      <p class="ek">Medula aktarımından kasa sayımına kadar her özellik, o tezgâhta yaşanmış bir soruna verilmiş cevaptır. Şimdi aynı sistemi sizin mağazanız için açıyoruz.</p>
    </div>
  </div>

  <section id="fiyatlar">
    <div class="wrap">
      <div class="bas">
        <h2>Sade fiyat, sürpriz yok.</h2>
        <p class="lead">30 gün boyunca her şey açık ve ücretsiz. Beğenirseniz devam edersiniz.</p>
      </div>
      <?php if (!empty($kamp['aktif'])): ?>
        <div class="kamp"><b><?= pz_e($kamp['baslik']) ?></b><span><?= pz_e($kamp['metin']) ?></span></div>
      <?php endif; ?>
      <div class="paketler">
        <?php foreach ($p['paketler'] as $pk): $vurgu = !empty($pk['vurgu']); ?>
          <div class="paket<?= $vurgu ? ' vurgu on-night' : '' ?>">
            <h3><?= pz_e($pk['ad']) ?><?php if (!empty($pk['yakinda'])): ?> <span class="yakinda">Yakında</span><?php endif; ?></h3>
            <p class="acik"><?= pz_e($pk['aciklama']) ?></p>
            <?php if (trim((string) $pk['fiyat']) !== ''): ?>
              <div class="tutar"><?= pz_e($pk['fiyat']) ?><span><?= pz_e($pk['donem']) ?></span></div>
            <?php else: ?>
              <div class="sor">Size özel fiyat için bize yazın</div>
            <?php endif; ?>
            <ul><?php foreach ($pk['ozellikler'] as $oz): ?><li><?= pz_e($oz) ?></li><?php endforeach; ?></ul>
            <?php if ($vurgu && $iletisimUrl !== ''): ?>
              <a class="btn btn-red" href="<?= pz_e($iletisimUrl) ?>" target="_blank" rel="noopener">Görüşelim</a>
            <?php else: ?>
              <a class="btn <?= $vurgu ? 'btn-red' : 'btn-line' ?>" href="kayit.php">30 gün ücretsiz deneyin</a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="dahil">
        <span>Kurulum ücreti yok</span>
        <?php if ($p['kurulum_destegi']): ?><span>Kurulumu birlikte yapıyoruz</span><?php endif; ?>
        <?php if ($p['veri_aktarim_destegi']): ?><span>Eski müşteri listenizi biz aktarıyoruz</span><?php endif; ?>
        <span>Sınırsız müşteri ve sipariş</span>
      </div>
    </div>
  </section>

  <section id="guven" style="padding-top:0">
    <div class="wrap">
      <div class="bas">
        <h2>Reçete sağlık verisidir. Öyle korunur.</h2>
        <p class="lead">Müşterilerinizin göz numarası ve SGK bilgisi en hassas verinizdir; OptiFlow bunu en baştan hesaba katarak tasarlandı.</p>
      </div>
      <div class="guven">
        <div><?= $ico['db'] ?><h3>Her mağazaya ayrı veritabanı</h3><p>Bir mağazanın bilgisi başka bir mağazaya asla görünmez.</p></div>
        <div><?= $ico['lock'] ?><h3>SGK şifreniz bizde değil</h3><p>Şifre isterseniz yalnızca sizin bilgisayarınızda, Windows şifrelemesiyle durur; sunucumuza gelmez. Aktarım yalnızca siz "Aktar"a bastığınızda çalışır.</p></div>
        <div><?= $ico['shield'] ?><h3>T.C. kimlik no saklanmaz</h3><p>Müşteri sayfaları soyadın tamamını ve telefonu göstermez.</p></div>
        <div><?= $ico['users'] ?><h3>Rol ve yetkiler</h3><p>Yönetici ve personel ayrı; tutarları ve raporları kimin göreceğini siz belirlersiniz.</p></div>
        <div><?= $ico['screen'] ?><h3>Ekranda kişisel veri yok</h3><p>Atölye ekranı yalnızca sipariş numarası, baş harfler ve aşamayı gösterir.</p></div>
        <div><?= $ico['down'] ?><h3>Yedeğiniz elinizde</h3><p>Veritabanınızın yedeğini istediğiniz an tek tıkla indirirsiniz. Veriniz sizindir.</p></div>
      </div>
    </div>
  </section>

  <section id="sss" style="padding-top:0">
    <div class="wrap">
      <div class="bas"><h2>Sık sorulan sorular</h2></div>
      <div class="sss">
        <?php foreach ($sss as $n => [$s, $c]): ?>
          <details<?= $n === 0 ? ' open' : '' ?>><summary><?= pz_e($s) ?></summary><p><?= pz_e($c) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<div class="son on-night">
  <svg class="son-gozluk" viewBox="0 0 400 160" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="110" cy="92" r="62"/><circle cx="290" cy="92" r="62"/><path d="M172 86c16-14 40-14 56 0M48 86 26 40M352 86l22-46"/></svg>
  <div class="wrap">
    <h2>Yarın sabah dükkânı OptiFlow ile açın.</h2>
    <p><?= $p['kurulum_destegi'] ? 'Başvurunuzu bırakın; mağazanızı açıp sizi arayalım, ilk ayarları birlikte yapalım. Kredi kartı istemiyoruz.' : 'Mağazanızı birkaç dakikada kurun; kredi kartı istemiyoruz.' ?></p>
    <div class="hero-ctas">
      <a class="btn btn-red" href="kayit.php">Mağazamı oluştur</a>
      <?php if ($iletisimUrl !== ''): ?><a class="btn btn-line" href="<?= pz_e($iletisimUrl) ?>" target="_blank" rel="noopener"><?= pz_e($iletisimAd) ?></a><?php endif; ?>
    </div>
  </div>
</div>

<footer>
  <div class="wrap">
    <div class="alt">
      <div>
        <a class="wordmark" href="/"><?= pz_logo('lgFoot') ?>OptiFlow</a>
        <p>Gözlükçüler için reçeteden teslimata yönetim sistemi. Medula aktarımı, SGK faturası, atölye, kasa ve stok tek panelde.</p>
      </div>
      <div class="alt-kol">
        <b>Ürün</b>
        <a href="#yol">Nasıl çalışır</a>
        <a href="#surumler">Lite ve Pro</a>
        <a href="#neler">Neler yapar</a>
        <a href="#fiyatlar">Fiyatlar</a>
        <a href="#guven">Güven ve KVKK</a>
        <a href="rehber.php">Gözlükçüler için rehber</a>
        <a href="indir.php">OptiFlow Pro'yu indir (Windows)</a>
        <a href="magaza-giris.php">Mağaza girişi</a>
      </div>
      <div class="alt-kol">
        <b>İletişim</b>
        <?php if ($wa): ?><a href="<?= pz_e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php if ($tel): ?><a href="<?= pz_e($tel) ?>"><?= pz_e($p['telefon']) ?></a><?php endif; ?>
        <?php if ($mail !== ''): ?><a href="mailto:<?= pz_e($mail) ?>"><?= pz_e($mail) ?></a><?php endif; ?>
        <?php if ($p['instagram'] !== ''): ?><a href="https://instagram.com/<?= pz_e($p['instagram']) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        <?php if ($p['adres'] !== ''): ?><span><?= pz_e($p['adres']) ?></span><?php endif; ?>
        <a href="kvkk.php">KVKK aydınlatma metni</a>
      </div>
    </div>
    <div class="alt-son">
      <span>© <?= date('Y') ?> <?= pz_e($p['sirket_unvani'] !== '' ? $p['sirket_unvani'] : 'OptiFlow') ?></span>
      <span>Türkiye'deki gözlükçüler için geliştirildi.</span>
    </div>
  </div>
</footer>

<?php if ($hasBar): ?>
<div class="mbar">
  <a class="btn btn-red" href="kayit.php">Ücretsiz deneyin</a>
  <a class="btn btn-line btn-wa" href="<?= pz_e($iletisimUrl) ?>" target="_blank" rel="noopener"><?= $wa ? $ico['wa'] . 'WhatsApp' : 'Bize ulaşın' ?></a>
</div>
<?php endif; ?>
<script src="<?= e(asset('karsilama-sahne.js')) ?>" defer></script>
</body>
</html><?php
    exit;
}
