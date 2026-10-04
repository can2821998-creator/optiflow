<?php
declare(strict_types=1);

require_once __DIR__ . '/pazarlama.php';

/**
 * OptiFlow karşılama (tanıtım) sayfası — v5 (4.17.0)
 * Tasarım fikri: göz eşeli. Başlık, muayenedeki Snellen tablosu gibi satır satır küçülür;
 * yanında ondalık görme keskinliği (0,1 … 1,0) yazar ve sayfa açılırken satırlar sırayla netleşir.
 *
 * Tenant/veritabanı bağlamı yoktur (oturumu olmayan herkes görür) — DB fonksiyonu çağırmaz.
 * İletişim, fiyat ve kampanya bilgileri app/pazarlama.php'den gelir; boş alanlar sayfada görünmez.
 * CSP 'script-src self': satır içi JavaScript YOK (SSS <details> ile çalışır, netleşme CSS animasyonudur).
 */
function render_karsilama(): void
{
    $p     = optiflow_pazarlama();
    $wa    = pz_whatsapp();
    $tel   = pz_tel();
    $mail  = $p['eposta'];
    $kamp  = $p['kampanya'];
    $video = $p['demo_video'];

    // İletişim düğmesi önceliği: WhatsApp → telefon → e-posta
    $iletisimUrl = $wa ?: ($tel ?: ($mail !== '' ? 'mailto:' . $mail . '?subject=' . rawurlencode('OptiFlow demo') : ''));
    $iletisimAd  = $wa ? "WhatsApp'tan demo isteyin" : ($tel ? 'Arayın, gösterelim' : ($mail !== '' ? 'Demo isteyin' : ''));

    $sss = [
        ['Medula ile nasıl çalışıyor, SGK şifremi istiyor mu?',
         "Hayır. OptiFlow Pro, Medula Optik'i ve OptiFlow'u tek pencerede açan Windows uygulamasıdır. Medula'ya her zamanki gibi kendiniz girersiniz; reçeteyi açıp \"Aktar\"a bastığınızda yalnızca o an ekranda görünen reçete OptiFlow'a gelir. SGK kullanıcı adı ve şifrenizi okumaz, saklamaz, başka yere göndermez."],
        ['SGK katkı payı ve ay sonu faturası kesin mi?',
         "Reçetenin kullanım şekline göre katkı payı önerilir, siparişte düzenlenebilir. Ay sonunda Medula'ya işlenen reçeteler tek SGK faturasında toplanır; Medula'dan aldığınız dökümü yükleyip adetleri karşılaştırırsınız. Kesin tutarı her zaman Medula belirler."],
        ['Verilerimiz güvende mi?',
         'Her mağaza kendi ayrı veritabanında çalışır; bir mağazanın müşteri, reçete ya da SGK bilgisi başka bir mağazayla asla paylaşılmaz. T.C. kimlik numarası saklanmaz. Veritabanınızın yedeğini istediğiniz an tek tıkla indirebilirsiniz.'],
        ['Başka bir programdan ya da defterden geçiyorum. Eski müşterilerim ne olacak?',
         $p['veri_aktarim_destegi']
            ? "Müşteri listenizi Excel ya da başka bir biçimde bize gönderin, OptiFlow'a biz aktaralım. İlk günden eski müşterilerinizle çalışmaya başlarsınız."
            : 'Müşterileriniz ilk siparişlerinde sisteme eklenir; reçete ve gözlük geçmişi o andan itibaren birikir.'],
        ['Birden fazla şubem var, her biri ayrı mı çalışır?',
         'Evet. Her şube kendi kullanıcılarıyla giriş yapar ve yalnızca kendi siparişlerini görür; siz merkezden hepsini takip edersiniz.'],
        ['Telefon ve tablette çalışır mı? Bir şey kurmam gerekir mi?',
         "OptiFlow tarayıcıda çalışır; bilgisayar, tablet ve telefonda açılır, telefonun ana ekranına uygulama gibi eklenir. Medula aktarımı ve ÜTS karekod okuyucu için mağaza bilgisayarına OptiFlow Pro'yu kurarsınız; güncellemeleri kendisi alır."],
        ['Kredi kartı bilgisi vermem gerekiyor mu?',
         'Hayır. 30 günlük deneme kart bilgisi olmadan başlar. Deneme bittiğinde erişim durur; devam etmek isterseniz sizinle iletişime geçeriz.'],
        ['Kurulum ne kadar sürer?',
         $p['kurulum_destegi']
            ? 'Başvurunuzu bıraktıktan sonra mağazanızı biz açar, sizi arayıp ilk ayarları birlikte yaparız: mağaza adınız, renginiz, şubeleriniz ve kullanıcılarınız. Teknik bilgi gerekmez.'
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
            ['Kâr raporu', 'Sipariş ve cam tipine göre gerçek kâr; hatalı camın maliyeti düşülmüş.'],
            ['Gün sonu kasa', 'Nakit sayımı, küçük satışlar ve harcamalar; kasa farkının nedeni ortada.'],
            ['Bakiye takibi', 'Söz verilen ödeme tarihi geçen müşteriler ayrı listede.'],
            ['Tedarikçi ve alış faturası', "e-Fatura XML'i yükleyin; stok, maliyet ve cari kendiliğinden işlensin. Senet vadeleri takvimde."],
            ['Ödeme linki', 'Kalan bakiye için müşteriye kartla ödeme linki gönderin.'],
        ]],
        ['Ekip ve şubeler', [
            ['Atölye ekranı', 'Duvardaki ekranda canlı iş listesi; fiyat, telefon ve reçete göstermez.'],
            ['Çok şube, tek panel', 'Her şubenin kendi kullanıcıları; siz merkezden hepsini görürsünüz.'],
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
<meta name="theme-color" content="#f3f5fb">
<title>Gözlükçü Programı OptiFlow | Medula Aktarımı, SGK Faturası ve Atölye</title>
<meta name="description" content="Gözlükçüler için yönetim programı: Medula'daki reçete tek tıkla siparişe, SGK katkı payı ve ay sonu SGK faturası hazır. Atölye panosu, WhatsApp bildirimi, garanti kartı, ÜTS karekod, kasa ve çok şube. 30 gün ücretsiz.">
<link rel="canonical" href="https://optiflow.com.tr/">
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
<script type="application/ld+json"><?= $ldJson(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'OptiFlow', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web, Windows', 'inLanguage' => 'tr', 'description' => 'Gözlükçüler için Medula aktarımı, SGK katkı payı ve ay sonu SGK faturası, sipariş, atölye, garanti, kasa ve çok şube yönetim sistemi.', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'TRY', 'description' => '30 gün ücretsiz deneme'], 'url' => 'https://optiflow.com.tr/']) ?></script>
<script type="application/ld+json"><?= $ldJson(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqLd]) ?></script>
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
  --paper:#f3f5fb;      /* soğuk, muayene odası beyazı */
  --card:#ffffff;
  --ink:#0a1033;        /* derin optik lacivert */
  --ink-2:#454d73;
  --line:#d3d9eb;
  --blue:#2a36ff;       /* marka mavisi, doygun */
  --blue-deep:#1822d6;
  --magenta:#c414d8;    /* logodaki ikinci renk: yalnızca durum ve vurgu noktaları */
  --lens:#e3e8ff;       /* cam tonu */
  --red:#f2301f;        /* tek eylem rengi */
  --red-deep:#c8200f;
  --ok:#0f8a5f;
  --night:#080d2b;      /* koyu bant */
  --night-2:#141a46;
  --night-ink:#c3cbf5;
  --display:"Manrope","Manrope Fallback",system-ui,Arial,sans-serif;
  --serif:"Fraunces",Georgia,"Times New Roman",serif;
  --mono:"IBM Plex Mono",ui-monospace,"Cascadia Mono",Consolas,monospace;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:84px}
body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--display);font-size:16.5px;line-height:1.55;font-weight:500;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
a{color:inherit}
.wrap{max-width:1160px;margin:0 auto;padding-inline:24px}
h1,h2,h3{margin:0;text-wrap:balance}
h2{font-family:var(--serif);font-weight:500;font-size:clamp(1.9rem,3.6vw,2.9rem);line-height:1.08;letter-spacing:-.015em;max-width:22ch}
h3{font-size:1.06rem;font-weight:800;letter-spacing:-.01em}
p{margin:0}
.lead{color:var(--ink-2);font-size:1.08rem;max-width:58ch;margin-top:14px}
:focus-visible{outline:3px solid var(--blue);outline-offset:3px;border-radius:6px}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:15px 26px;border-radius:12px;font-weight:800;font-size:15.5px;text-decoration:none;border:2px solid transparent;cursor:pointer;white-space:nowrap;transition:background .15s ease,color .15s ease,border-color .15s ease}
.btn svg{width:18px;height:18px;flex:none}
.btn-red{background:var(--red);color:#fff}
.btn-red:hover{background:var(--red-deep)}
.btn-line{border-color:var(--ink);color:var(--ink)}
.btn-line:hover{background:var(--ink);color:#fff}
.btn-wa svg{color:#1faa53}
.on-night .btn-line{border-color:#fff;color:#fff}
.on-night .btn-line:hover{background:#fff;color:var(--night)}

/* ---------- Üst çubuk ---------- */
header{position:sticky;top:env(safe-area-inset-top,0px);z-index:30;background:rgba(243,245,251,.9);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border-bottom:1px solid var(--line)}
.nav{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-block:13px}
.wordmark{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px;text-decoration:none;color:var(--ink);letter-spacing:-.02em}
.wordmark svg{width:30px;height:30px}
.nav-links{display:flex;align-items:center;gap:22px;font-size:14.5px;font-weight:700}
.nav-links a:not(.btn){text-decoration:none;color:var(--ink-2)}
.nav-links a:not(.btn):hover{color:var(--ink)}
.nav-links .btn{padding:10px 16px;font-size:14px}

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
@media (prefers-reduced-motion:no-preference){
  .esel-satir .harf{animation:netles 1.1s cubic-bezier(.2,.7,.2,1) both}
  .e1 .harf{animation-delay:.05s}.e2 .harf{animation-delay:.2s}.e3 .harf{animation-delay:.35s}
  .e4 .harf{animation-delay:.5s}.e5 .harf{animation-delay:.65s}.e6 .harf{animation-delay:.8s}
}
@keyframes netles{from{filter:blur(9px);opacity:.35}to{filter:blur(0);opacity:1}}
.hero-alt{display:flex;flex-direction:column;align-items:center;gap:16px;margin-top:34px;text-align:center}
.hero-ctas{display:flex;gap:12px;flex-wrap:wrap;justify-content:center}
.hero-not{display:flex;flex-wrap:wrap;justify-content:center;gap:4px 20px;margin:0;padding:0;list-style:none;font-size:14px;font-weight:700;color:var(--ink-2)}
.hero-not li::before{content:"✓";color:var(--blue);margin-right:6px}

/* ---------- Bölümler ---------- */
section{padding-block:96px}
.bas{margin-bottom:44px}

/* Önce / OptiFlow ile */
.fark{width:100%;border-collapse:collapse;font-size:15.5px}
.fark th{text-align:left;font-size:14px;font-weight:800;padding:0 18px 12px 0;border-bottom:3px solid var(--ink)}
.fark th.ile{color:var(--blue)}
.fark td{padding:16px 18px 16px 0;border-bottom:1px solid var(--line);vertical-align:top}
.fark td:first-child{font-weight:800;width:22%}
.fark td.once{color:var(--ink-2);text-decoration:line-through;text-decoration-color:rgba(242,48,31,.55);text-decoration-thickness:2px}
.fark td.ile{font-weight:600}
.tablo-kap{overflow-x:auto}

/* Dükkânın içi, müşterinin cebi */
.iki{display:grid;grid-template-columns:1.35fr .65fr;gap:40px;align-items:start}
.iki > *{min-width:0}
.pano{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;box-shadow:0 30px 60px -40px rgba(10,16,51,.45)}
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
.telefon{border:10px solid var(--ink);border-radius:34px;background:var(--paper);padding:18px 14px;max-width:290px;margin-inline:auto;box-shadow:0 30px 60px -30px rgba(10,16,51,.55)}
.tel-ust{display:flex;align-items:center;gap:8px;font-weight:800;font-size:14px;margin-bottom:14px}
.tel-ust i{width:22px;height:22px;border-radius:7px;background:var(--blue);display:block}
.tel-kart{background:var(--card);border-radius:16px;padding:16px;border:1px solid var(--line)}
.tel-kart small{display:inline-block;font-family:var(--mono);font-size:11px;color:var(--ink-2);border:1px solid var(--line);border-radius:6px;padding:2px 6px}
.tel-kart h4{margin:12px 0 4px;font-family:var(--serif);font-weight:500;font-size:1.6rem;line-height:1.1}
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
.dokum tr.eksik td{background:#fff3f2}
.dokum tr.eksik td:last-child{color:var(--red);font-weight:800;font-family:var(--display);text-align:right}
.ay-liste{list-style:none;margin:22px 0 0;padding:0;display:grid;gap:12px}
.ay-liste li{display:grid;grid-template-columns:22px 1fr;gap:10px;color:var(--ink-2)}
.ay-liste li::before{content:"";width:12px;height:7px;margin-top:.45em;border-left:2px solid var(--blue);border-bottom:2px solid var(--blue);transform:rotate(-45deg)}
.ay-liste b{color:var(--ink)}

/* Rehber */
.rehber{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px;padding:28px 32px;border:2px solid var(--ink);border-radius:16px}
.rehber p{color:var(--ink-2);max-width:60ch;margin-top:6px}

/* Bir siparişin yolu */
.yol{list-style:none;margin:0;padding:0;display:grid;grid-template-columns:repeat(5,1fr);gap:0;counter-reset:adim}
.yol li{position:relative;padding:22px 20px 0 0;border-top:2px solid var(--ink)}
.yol li::before{counter-increment:adim;content:counter(adim);position:absolute;top:-15px;left:0;width:28px;height:28px;border-radius:50%;background:var(--paper);border:2px solid var(--ink);display:grid;place-items:center;font-size:13px;font-weight:800;font-family:var(--mono)}
.yol li:last-child{border-top-color:var(--magenta)}
.yol li:last-child::before{border-color:var(--magenta);color:var(--magenta)}
.yol h3{margin:10px 0 8px}
.yol p{color:var(--ink-2);font-size:15px}

/* Pro penceresi (koyu bant) */
.gece{background:var(--night);color:#fff}
.gece .lead{color:var(--night-ink)}
.pro-grid{display:grid;grid-template-columns:.9fr 1.1fr;gap:56px;align-items:center}
.pro-grid > *{min-width:0}
.pencere{background:var(--night-2);border:1px solid rgba(255,255,255,.12);border-radius:14px;overflow:hidden;box-shadow:0 40px 80px -30px rgba(0,0,0,.6)}
.pencere-ust{display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid rgba(255,255,255,.1);font-size:12.5px;color:var(--night-ink)}
.pencere-ust .nokta{width:10px;height:10px;border-radius:50%;background:rgba(255,255,255,.22)}
.pencere-ust b{color:#fff;margin-left:6px}
.arac{margin-left:auto;display:flex;gap:6px}
.arac span{padding:4px 10px;border-radius:7px;background:rgba(255,255,255,.08);font-weight:700}
.arac .aktar{background:var(--red);color:#fff}
.bolmeler{display:grid;grid-template-columns:1fr 1fr}
.bolme{padding:18px;font-size:13px}
.bolme + .bolme{border-left:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.03)}
.bolme small{display:block;color:var(--night-ink);margin-bottom:10px;font-weight:700}
.kutular{display:grid;grid-template-columns:auto repeat(3,1fr);gap:6px;align-items:center}
.kutular i{font-style:normal;color:var(--night-ink);font-size:12px}
.kutu{font-family:var(--mono);background:#fff;color:var(--night);border-radius:4px;padding:4px 6px;text-align:right;font-size:12.5px}
.rx{width:100%;border-collapse:collapse;font-family:var(--mono);font-size:13px;font-variant-numeric:tabular-nums}
.rx th{font-family:var(--display);font-size:11px;color:var(--night-ink);font-weight:700;text-align:right;padding:0 4px 6px}
.rx th:first-child,.rx td:first-child{text-align:left}
.rx td{padding:5px 4px;border-top:1px solid rgba(255,255,255,.1);text-align:right}
.sgk{margin-top:12px;display:flex;justify-content:space-between;gap:10px;padding:9px 10px;border-radius:8px;background:rgba(42,54,255,.35);font-size:12.5px}
.sgk b{font-family:var(--mono)}
.gizlilik{display:flex;gap:12px;margin-top:26px;padding-top:20px;border-top:1px solid rgba(255,255,255,.14);font-size:14.5px;color:var(--night-ink)}
.gizlilik svg{width:22px;height:22px;flex:none;color:#fff}
.gizlilik b{color:#fff}
.pro-ctas{display:flex;gap:12px;flex-wrap:wrap;margin-top:28px}

/* Neler yapar */
.grup{display:grid;grid-template-columns:260px 1fr;gap:32px;padding-block:30px;border-top:1px solid var(--line)}
.grup:last-child{border-bottom:1px solid var(--line)}
.grup > *{min-width:0}
.grup h3{font-family:var(--serif);font-weight:500;font-size:1.55rem;letter-spacing:-.01em}
.grup dl{margin:0;display:grid;grid-template-columns:1fr 1fr;gap:20px 36px}
.grup dt{font-weight:800}
.grup dd{margin:4px 0 0;color:var(--ink-2);font-size:15px}

/* Hikâye */
.hikaye{padding-block:88px;background:var(--lens)}
.hikaye p.buyuk{font-family:var(--serif);font-weight:500;font-size:clamp(1.5rem,2.8vw,2.2rem);line-height:1.3;max-width:34ch;letter-spacing:-.01em}
.hikaye p.ek{margin-top:20px;color:var(--ink-2);max-width:60ch}

/* Fiyatlar */
.kamp{display:flex;flex-wrap:wrap;gap:6px 14px;padding:14px 18px;border:2px dashed var(--blue);border-radius:12px;margin-bottom:24px}
.kamp b{color:var(--blue-deep)}
.paketler{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px}
.paket{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:30px;display:flex;flex-direction:column;gap:14px}
.paket.vurgu{background:var(--night);color:#fff;border-color:var(--night)}
.paket .acik{color:var(--ink-2)}
.paket.vurgu .acik,.paket.vurgu li{color:var(--night-ink)}
.paket h3{font-family:var(--serif);font-weight:500;font-size:1.7rem}
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
  .pro-grid{grid-template-columns:1fr;gap:40px}
  .yol{grid-template-columns:1fr;gap:28px}
  .yol li{border-top:0;border-left:2px solid var(--ink);padding:0 0 0 26px}
  .yol li:last-child{border-left-color:var(--magenta)}
  .yol li::before{top:0;left:-15px}
  .yol h3{margin-top:2px}
  .grup{grid-template-columns:1fr;gap:18px}
  .iki,.ay-grid{grid-template-columns:1fr;gap:40px}
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
  .bolmeler{grid-template-columns:1fr}
  .bolme + .bolme{border-left:0;border-top:1px solid rgba(255,255,255,.1)}
  .arac span:not(.aktar){display:none}
  .alt{grid-template-columns:1fr}
  .kontrol-ust b{font-size:1.5rem}
  .kontrol-ust div{padding:12px}
  .fark{font-size:14.5px}
  .rehber{padding:22px}
  .mbar{display:flex;gap:10px;position:fixed;left:0;right:0;bottom:0;z-index:40;padding:10px 16px calc(10px + env(safe-area-inset-bottom,0px));background:rgba(243,245,251,.96);border-top:1px solid var(--line)}
  .mbar .btn{flex:1;padding:13px 10px}
  body.bar{padding-bottom:76px}
}
</style>
<?= ga_head() ?>
</head>
<body<?= $hasBar ? ' class="bar"' : '' ?>>

<header>
  <div class="wrap nav">
    <a class="wordmark" href="/" aria-label="OptiFlow ana sayfa"><?= pz_logo('lgNav') ?>OptiFlow</a>
    <nav class="nav-links" aria-label="Ana menü">
      <a href="#yol">Nasıl çalışır</a>
      <a href="#pro">OptiFlow Pro</a>
      <a href="#ay-sonu">SGK faturası</a>
      <a href="#neler">Neler yapar</a>
      <a href="#fiyatlar">Fiyatlar</a>
      <a href="#sss">Sorular</a>
      <a class="btn btn-line giris" href="magaza-giris.php">Giriş yap</a>
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
      </div>
    </div>
  </div>

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
      <ol class="yol">
        <?php foreach ($yol as [$b, $t]): ?>
          <li><h3><?= pz_e($b) ?></h3><p><?= pz_e($t) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <section id="pro" class="gece on-night">
    <div class="wrap pro-grid">
      <div>
        <h2>Medula ve OptiFlow aynı pencerede.</h2>
        <p class="lead">Medula Optik'teki sferik, silendirik ve aks değerleri kutuların içinde durduğu için kopyala-yapıştırla gelmez. OptiFlow Pro bu değerleri doğru sırayla okur; siz kontrol edip onaylayınca siparişe yazılır.</p>
        <p class="lead">Aynı pencereden müşterinin SGK hakkını sorgular, ÜTS karekodunu okutursunuz. İnternet kesilse bile açık siparişler ekranda kalır.</p>
        <div class="gizlilik"><?= $ico['lock'] ?><span>SGK kullanıcı adınızı ve şifrenizi <b>okumaz, saklamaz, göndermez</b>. Medula'ya her zamanki gibi kendiniz girersiniz; yalnızca "Aktar"a bastığınızda ekrandaki reçete gelir.</span></div>
        <div class="pro-ctas"><a class="btn btn-red" href="kayit.php">Mağazamı oluştur</a></div>
      </div>
      <div class="pencere" role="img" aria-label="OptiFlow Pro penceresi: solda Medula reçetesi, sağda OptiFlow siparişi">
        <div class="pencere-ust"><span class="nokta"></span><span class="nokta"></span><span class="nokta"></span><b>OptiFlow Pro</b>
          <span class="arac"><span>Hak sorgula</span><span class="aktar">Aktar</span></span></div>
        <div class="bolmeler">
          <div class="bolme">
            <small>Medula Optik · e-Reçete 1A2B3C4</small>
            <div class="kutular">
              <span></span><i>Sferik</i><i>Silendirik</i><i>Aks</i>
              <i>Sağ</i><span class="kutu">+1,25</span><span class="kutu">−0,75</span><span class="kutu">45</span>
              <i>Sol</i><span class="kutu">+1,00</span><span class="kutu">−0,50</span><span class="kutu">130</span>
              <i>Yakın</i><span class="kutu">+3,25</span><span class="kutu">−0,75</span><span class="kutu">45</span>
            </div>
          </div>
          <div class="bolme">
            <small>OptiFlow · Yeni sipariş</small>
            <table class="rx">
              <tr><th></th><th>SPH</th><th>CYL</th><th>AKS</th><th>ADD</th></tr>
              <tr><td>Sağ</td><td>+1,25</td><td>−0,75</td><td>45</td><td>+2,00</td></tr>
              <tr><td>Sol</td><td>+1,00</td><td>−0,50</td><td>130</td><td>+2,00</td></tr>
            </table>
            <div class="sgk"><span>SGK katkı payı</span><b>hesaplandı</b></div>
          </div>
        </div>
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
          <div class="pano" role="img" aria-label="Atölye panosu örneği: cam bekleniyor, montajda, kontrolde ve hazır sütunları">
            <div class="pano-ust">Atölye panosu <span>Örnek görünüm</span></div>
            <div class="sutunlar">
              <div class="sutun"><b>Cam bekleniyor <i>3</i></b>
                <div class="is gec"><strong>#01284</strong><span>Progresif 1.6 · <em>5. gün</em></span></div>
                <div class="is"><strong>#01291</strong><span>Tek odak 1.5 · 1. gün</span></div>
                <div class="is"><strong>#01293</strong><span>Antirefle · bugün</span></div>
              </div>
              <div class="sutun"><b>Montajda <i>2</i></b>
                <div class="is mon"><strong>#01279</strong><span>Metal çerçeve · M. K.</span></div>
                <div class="is mon"><strong>#01286</strong><span>Nilor · A. Y.</span></div>
              </div>
              <div class="sutun"><b>Kontrolde <i>1</i></b>
                <div class="is kon"><strong>#01277</strong><span>Aks ve PD ölçüldü</span></div>
              </div>
              <div class="sutun"><b>Hazır <i>2</i></b>
                <div class="is hazir"><strong>#01270</strong><span>WhatsApp gitti</span></div>
                <div class="is hazir"><strong>#01272</strong><span>Teslim: yarın</span></div>
              </div>
            </div>
          </div>
          <p class="alt-not">Duvardaki atölye ekranında da aynı liste döner; fiyat, telefon ve reçete görünmez.</p>
        </div>
        <div class="telefon" role="img" aria-label="Müşterinin telefonunda sipariş durumu ve garanti bilgisi örneği">
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
          <h3 style="font-family:var(--serif);font-weight:500;font-size:1.6rem">Gözlükçüler için rehber</h3>
          <p>Gözlükçü programı seçerken nelere bakılmalı, dükkânın günlük işleyişi nasıl kolaylaşır: tezgâhtan yazılmış sade yazılar.</p>
        </div>
        <a class="btn btn-line" href="rehber.php">Rehberi okuyun</a>
      </div>
    </div>
  </section>

  <div class="hikaye">
    <div class="wrap">
      <p class="buyuk">OptiFlow bir yazılım ofisinde değil, her gün reçete girilen, cam beklenen ve "gözlüğüm hazır mı?" diye aranan bir optik atölyesinde doğdu.</p>
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
            <h3><?= pz_e($pk['ad']) ?></h3>
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
        <div><?= $ico['lock'] ?><h3>SGK şifreniz bizde değil</h3><p>OptiFlow Pro şifre okumaz, saklamaz; yalnızca siz "Aktar"a bastığınızda çalışır.</p></div>
        <div><?= $ico['shield'] ?><h3>T.C. kimlik no saklanmaz</h3><p>Müşteri sayfaları soyadın tamamını ve telefonu göstermez.</p></div>
        <div><?= $ico['users'] ?><h3>Rol ve şube yetkileri</h3><p>Personel yalnızca kendi şubesini görür; tutarları kimin göreceğini siz belirlersiniz.</p></div>
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
        <p>Gözlükçüler için reçeteden teslimata yönetim sistemi. Medula aktarımı, SGK faturası, atölye, kasa ve çok şube tek panelde.</p>
      </div>
      <div class="alt-kol">
        <b>Ürün</b>
        <a href="#yol">Nasıl çalışır</a>
        <a href="#pro">OptiFlow Pro</a>
        <a href="#neler">Neler yapar</a>
        <a href="#fiyatlar">Fiyatlar</a>
        <a href="#guven">Güven ve KVKK</a>
        <a href="rehber.php">Gözlükçüler için rehber</a>
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
</body>
</html><?php
    exit;
}
