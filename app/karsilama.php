<?php
declare(strict_types=1);

require_once __DIR__ . '/pazarlama.php';

/**
 * OptiFlow karşılama (tanıtım) sayfası — v4.1
 * Tenant/veritabanı bağlamı yoktur (oturumu olmayan herkes görür) — bu yüzden hiçbir DB fonksiyonu
 * (setting(), db(), current_user()...) çağırmaz. İletişim, fiyat ve kampanya bilgileri app/pazarlama.php'den gelir;
 * boş bırakılan alanlar sayfada hiç görünmez.
 * CSP 'script-src self' olduğundan sayfada çalışan satır içi JavaScript YOKTUR (SSS <details> ile çalışır).
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
        ['Medula köprüsü nasıl çalışıyor, SGK şifremi istiyor mu?',
         "Hayır. Köprü, mağaza bilgisayarınızdaki Chrome'a eklenen küçük bir eklentidir. Medula Optik'te reçeteyi açtığınızda sağ altta \"Atölyeye aktar\" düğmesi belirir; yalnızca siz bastığınızda, o an ekranda görünen reçete metnini OptiFlow'a gönderir. SGK kullanıcı adı ve şifrenizi okumaz, saklamaz, otomatik giriş yapmaz."],
        ['SGK katkı payı hesabı kesin mi?',
         'Reçetenin kullanım şekline göre otomatik bir tutar önerilir ve sipariş ekranında düzenlenebilir. Kesin tutarı her zaman MEDULA belirler.'],
        ['Verilerimiz güvende mi?',
         'Her mağaza kendi ayrı veritabanında çalışır; bir mağazanın müşteri, reçete ya da SGK bilgisi başka bir mağazayla asla paylaşılmaz. İstediğiniz an veritabanınızın yedeğini tek tıkla indirebilirsiniz.'],
        ['Başka bir programdan ya da defterden geçiyorum. Eski müşterilerim ne olacak?',
         $p['veri_aktarim_destegi']
            ? "Müşteri listenizi Excel ya da başka bir biçimde bize gönderin, OptiFlow'a biz aktaralım. İlk günden eski müşterilerinizle çalışmaya başlarsınız."
            : 'Müşterileriniz ilk siparişlerinde sisteme eklenir; reçete ve gözlük geçmişi o andan itibaren birikir.'],
        ['Birden fazla şubem var, her biri ayrı mı çalışır?',
         'Evet. Her şube kendi kullanıcılarıyla giriş yapar ve yalnızca kendi siparişlerini görür; siz merkezden hepsini takip edersiniz.'],
        ['Telefon ve tablette çalışır mı? Bir şey kurmam gerekir mi?',
         'OptiFlow tarayıcıda çalışır; bilgisayar, tablet ve telefonda açılır. İsterseniz telefonunuzun ana ekranına uygulama gibi ekleyip bildirim alabilirsiniz. Kurmanız gereken tek şey, Medula köprüsünü kullanacaksanız Chrome eklentisidir.'],
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
        'bolt'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>',
        'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 5 3.4 8.4 8 9 4.6-.6 8-4 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
        'store'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10v10h16V10"/><path d="M2 10 4 4h16l2 6"/><path d="M2 10a3 3 0 0 0 5 0 3 3 0 0 0 5 0 3 3 0 0 0 5 0 3 3 0 0 0 5 0"/><path d="M10 20v-5h4v5"/></svg>',
        'phone'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/></svg>',
        'bell'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 8 3 8H3s3-1 3-8"/><path d="M10 20a2 2 0 0 0 4 0"/></svg>',
        'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg>',
        'users'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8M22 21a7 7 0 0 0-4-6.3"/></svg>',
        'doc'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h5"/></svg>',
        'chart'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/></svg>',
        'wallet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H5a2 2 0 0 1-2-2V5"/><path d="M16 14h.01"/></svg>',
        'clock'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
        'truck'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 4h14v12H1zM15 9h4l3 4v3h-7"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/></svg>',
        'qr'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/></svg>',
        'screen' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
        'star'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 2 3 6.5 7 .9-5.1 4.8 1.3 7L12 17.8 5.8 21.2l1.3-7L2 9.4l7-.9L12 2Z"/></svg>',
        'palette'=> '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a10 10 0 1 1 10-10c0 3-2.5 4-4.5 4H15a2 2 0 0 0-1.4 3.4A1.9 1.9 0 0 1 12 22Z"/><circle cx="7.5" cy="10.5" r="1"/><circle cx="12" cy="7" r="1"/><circle cx="16.5" cy="10.5" r="1"/></svg>',
        'heart'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>',
        'lock'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
        'db'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg>',
        'eye'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>',
        'down'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>',
        'wa'     => '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.3.8 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg>',
    ];
    $grup = [
        ['g-blue', 'Müşteriniz geri gelsin', 'Satıştan sonra da müşterinizle bağınız kopmasın; kimi, ne zaman arayacağınızı sistem söylesin.', 'Büyüme', [
            ['bell', 'Akıllı hatırlatmalar', 'Gözlük yenileme zamanı gelen, SGK hakkı yeniden doğan ya da hazır gözlüğünü almaya gelmeyen müşteriler her gün kendiliğinden listelenir.'],
            ['search', '"Siparişim nerede?" sayfası', 'Müşteri sipariş numarası ve telefonunun son 4 hanesiyle (ya da fişteki karekodla) durumu kendisi görür. "Gözlüğüm hazır mı?" telefonları azalır.'],
            ['users', 'Aile kartı', 'Aynı ailenin bireyleri birbirine bağlıdır; kimin gözlük zamanı geldiğini tek kartta görür, aileyi birlikte ararsınız.'],
            ['doc', 'Teklif → sipariş', 'Karar veremeyen müşteriye teklif hazırlayın; geri döndüğünde tek tıkla siparişe çevirin, hiçbir bilgiyi yeniden yazmayın.'],
            ['bolt', 'WhatsApp bildirimleri', 'Gözlük hazır olduğunda müşteriye takip bağlantısıyla birlikte mesaj gider; hatırlatma metinlerini kendiniz düzenlersiniz.'],
            ['eye', 'Müşteri ve reçete geçmişi', 'Aldığı gözlükler, numara değişimi, ödemeleri ve iletişim bilgisi tek ekranda.'],
        ]],
        ['g-mid', 'Paranın nerede olduğunu görün', 'Gün sonunda "bugün ne kazandık, kim ne borçlu, hangi tedarikçi bekletiyor?" sorularının cevabı hazır.', 'Kârlılık', [
            ['chart', 'Kâr raporu', 'Sipariş bazında ve cam tipine göre kâr. Hangi ürünün gerçekten kazandırdığını görün.'],
            ['wallet', 'Gün sonu kasa', 'Nakit sayımı, sipariş dışı küçük satışlar ve kasadan yapılan harcamalar; kasa farkının nedeni ortada.'],
            ['clock', 'Bakiye takibi', 'Kalan ödemeler ve söz verilen ödeme tarihi geçmiş müşteriler ayrı listede; alacak unutulmaz.'],
            ['truck', 'Tedarikçi karnesi', 'Hangi tedarikçinin camı kaç günde getirdiğini ve en uzun bekleyen camları görün; pazarlığı veriyle yapın.'],
            ['qr', 'Stok ve karekodlu etiket', 'Çerçeve ve cam stoğu, kritik stok uyarısı, barkodla giriş-çıkış ve etiket yazdırma. Etiketi okutunca kayıt açılır.'],
            ['users', 'Personel ve prim', 'Kim ne sattı, kim ne tahsil etti; prim oranını girin, hesap kendiliğinden çıksın.'],
        ]],
        ['g-pop', 'Ekibiniz ve şubeleriniz için', 'Tezgâhtan atölyeye, tek dükkândan zincire kadar herkes aynı ekrana bakar.', 'Operasyon', [
            ['screen', 'Atölye ekranı', 'İkinci monitörde ya da duvardaki ekranda canlı iş listesi. Fiyat, telefon ve reçete göstermez; yalnızca sipariş, aşama ve teslim günü.'],
            ['store', 'Çok şube, tek panel', 'Her şubenin kendi kullanıcıları ve verisi vardır; siz merkezden hepsini görürsünüz.'],
            ['star', 'Başarılar ve rozetler', 'Ekibiniz için oyunlaştırma: sipariş yoğunluğu, rozetler ve beklenmedik anlarda açılan gizli ödüller.'],
            ['palette', 'Kendi adınız, kendi renginiz', 'Müşteri sayfaları, fişler ve bildirimler dükkânınızın adını ve rengini taşır; OptiFlow arka planda kalır.'],
            ['phone', 'Telefona uygulama gibi', 'Ana ekrana ekleyin, bildirimleri telefonunuzdan alın. Mağazada değilken de işler gözünüzün önünde.'],
            ['heart', 'Gözlük bağışı sayacı', 'Bağış çerçevelerini kaydedin, verilen gözlükleri sayın; sosyal sorumluluk çalışmanızı görünür kılın.'],
        ]],
    ];
    $hasBar = $iletisimUrl !== '';
    ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="index,follow">
<meta name="theme-color" content="#3346ff">
<title>Gözlükçü Programı OptiFlow | Medula Köprüsü ve SGK Katkı Payı</title>
<meta name="description" content="Gözlükçüler için yönetim programı: Medula'daki reçeteyi tek tıkla siparişe aktarın, SGK katkı payı otomatik hesaplansın. Atölye panosu, WhatsApp bildirimi, kasa, kâr raporu ve çok şube. 30 gün ücretsiz.">
<link rel="canonical" href="https://optiflow.com.tr/">
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="preload" as="font" type="font/woff2" href="assets/fonts/manrope-latin-wght-normal.woff2" crossorigin>
<link rel="preload" as="font" type="font/woff2" href="assets/fonts/manrope-latin-ext-wght-normal.woff2" crossorigin>
<meta property="og:type" content="website">
<meta property="og:site_name" content="OptiFlow">
<meta property="og:title" content="OptiFlow — Medula'daki reçete tek tıkla siparişte">
<meta property="og:description" content="Gözlükçüler için reçeteden teslimata yönetim sistemi. SGK katkı payı otomatik, atölye takibi ve WhatsApp bildirimi tek panelde. 30 gün ücretsiz.">
<meta property="og:url" content="https://optiflow.com.tr/">
<meta property="og:image" content="https://optiflow.com.tr/assets/og-optiflow.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="tr_TR">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="https://optiflow.com.tr/assets/og-optiflow.png">
<script type="application/ld+json"><?= $ldJson(['@context' => 'https://schema.org', '@type' => 'SoftwareApplication', 'name' => 'OptiFlow', 'applicationCategory' => 'BusinessApplication', 'operatingSystem' => 'Web', 'inLanguage' => 'tr', 'description' => 'Gözlükçüler için Medula köprüsü, SGK katkı payı, sipariş, atölye, kasa ve çok şube yönetim sistemi.', 'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'TRY', 'description' => '30 gün ücretsiz deneme'], 'url' => 'https://optiflow.com.tr/']) ?></script>
<script type="application/ld+json"><?= $ldJson(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $faqLd]) ?></script>
<style>
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-wght-normal.woff2") format("woff2");
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122; }
@font-face { font-family: "Manrope"; font-style: normal; font-display: swap; font-weight: 200 800;
  src: url("assets/fonts/manrope-latin-ext-wght-normal.woff2") format("woff2");
  unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20C0, U+2C60-2C7F, U+A720-A7FF; }
/* Metrikleri Manrope'a yaklaştırılmış yedek yüz: font yüklenince oluşan sıçramayı (CLS) neredeyse sıfırlar. */
@font-face { font-family: "Manrope Fallback"; src: local("Arial"); size-adjust: 102%; ascent-override: 96%; descent-override: 24%; line-gap-override: 0%; }

:root{
  --bg:#fafafa; --card:#fff; --ink:#14131f; --ink-soft:#5b5a6c; --line:#ececf1;
  --primary:#3346ff; --primary-deep:#1c2ecc; --primary-tint:#eceffe;
  --mid:#c026d3; --mid-deep:#9b1cab; --mid-tint:#fbeafd;
  --pop:#db2f20; --pop-deep:#b8261a; --pop-tint:#ffece9;
  --ok:#1c7a4d; --ok-tint:#e6f4ea;
  --grad:linear-gradient(115deg,#3346ff 0%,#c026d3 52%,#ff4433 100%);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth;scroll-padding-top:88px}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Manrope,"Manrope Fallback",system-ui,Arial,sans-serif;line-height:1.5;font-weight:500;-webkit-font-smoothing:antialiased;text-rendering:optimizeLegibility}
section[id],main [id]{scroll-margin-top:88px}
.wrap{max-width:1160px;margin:0 auto;padding:0 24px}
a{color:inherit}
h1,h2,h3,h4{font-family:Manrope,"Manrope Fallback",sans-serif;font-weight:800;letter-spacing:-.02em;margin:0}
.kicker{display:inline-flex;align-items:center;gap:8px;font-size:12.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--primary-deep);margin-bottom:14px}
.kicker::before{content:"";width:18px;height:3px;border-radius:9px;background:var(--grad)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:15px 26px;border-radius:99px;font-weight:800;font-size:15.5px;text-decoration:none;border:2px solid transparent;cursor:pointer;white-space:nowrap;transition:transform .15s ease,box-shadow .15s ease,background .15s ease}
.btn svg{width:18px;height:18px;flex:none}
.btn-pop{background:var(--pop);color:#fff;box-shadow:0 12px 24px -10px rgba(224,71,42,.55)}
.btn-pop:hover{background:var(--pop-deep);transform:translateY(-1px)}
.btn-outline{border-color:currentColor;color:inherit}
.btn-outline:hover{background:rgba(255,255,255,.12)}
.btn-outline.on-light{border-color:var(--ink)}
.btn-outline.on-light:hover{background:var(--ink);color:#fff}
.btn-wa{background:#fff;color:var(--ink)}
.btn-wa svg{color:#1faa53}
.btn-wa:hover{transform:translateY(-1px)}

header{position:sticky;top:0;z-index:30;background:rgba(250,250,250,.88);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
.nav{display:flex;align-items:center;justify-content:space-between;padding:14px 0;gap:16px}
.wordmark{display:flex;align-items:center;gap:10px;font-weight:800;font-size:20px;text-decoration:none;color:var(--ink);letter-spacing:-.02em}
.wordmark svg{width:30px;height:30px}
.nav-links{display:flex;align-items:center;gap:22px;font-size:14.5px;font-weight:700}
.nav-anchor{text-decoration:none;color:var(--ink-soft)}
.nav-anchor:hover{color:var(--ink)}
.nav-links .btn{padding:11px 18px;font-size:14px}

.hero{position:relative;background:linear-gradient(150deg,#4a5aff 0%,#3346ff 45%,#1c2ecc 100%);color:#fff;overflow:hidden;padding:72px 0 0}
.hero::after{content:"";position:absolute;right:-180px;bottom:-260px;width:620px;height:620px;border-radius:50%;background:radial-gradient(circle,rgba(192,38,211,.45),rgba(192,38,211,0) 65%);pointer-events:none}
.hero-shape{position:absolute;border-radius:50%;border:2px solid rgba(255,255,255,.2)}
.hero-shape.s1{width:460px;height:460px;top:-200px;right:-160px}
.hero-shape.s2{width:280px;height:280px;bottom:-140px;right:60px;border-color:rgba(255,122,92,.45)}
@media (prefers-reduced-motion:no-preference){
  .hero-shape{animation:optiflow-spin 40s linear infinite}
  .hero-shape.s2{animation-duration:28s;animation-direction:reverse}
}
@keyframes optiflow-spin{to{transform:rotate(360deg)}}
.hero-inner{position:relative;z-index:1;padding-bottom:84px}
.hero-grid{display:grid;grid-template-columns:1.12fr .98fr;gap:48px;align-items:center}
.hero-pill{display:inline-flex;align-items:center;gap:9px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28);padding:7px 14px 7px 8px;border-radius:99px;font-size:13.5px;font-weight:800;margin-bottom:22px}
.hero-pill b{background:#fff;color:var(--primary-deep);border-radius:99px;padding:3px 9px;font-size:11.5px;letter-spacing:.04em}
.hero h1{font-size:clamp(2.3rem,5vw,3.7rem);line-height:1.04;letter-spacing:-.035em;max-width:15ch}
.hero h1 em{font-style:normal;color:#ffd3c9}
.hero p.sub{margin:22px 0 0;font-size:1.12rem;max-width:46ch;opacity:.94;font-weight:600;line-height:1.55}
.hero-ctas{display:flex;gap:12px;flex-wrap:wrap;margin-top:32px}
.hero-note{display:flex;flex-wrap:wrap;gap:6px 18px;margin:18px 0 0;font-size:13.5px;font-weight:700;opacity:.9;padding:0;list-style:none}
.hero-note li::before{content:"✓";margin-right:6px;color:#b9ffd7}

.mock-wrap{position:relative;max-width:400px;margin:0 auto;width:100%}
.mock{position:relative;background:#fff;color:var(--ink);border-radius:20px;padding:20px;box-shadow:0 30px 60px -20px rgba(11,17,60,.5),0 8px 20px -8px rgba(11,17,60,.3);transform:rotate(-2deg)}
.mock-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px}
.mock-src{display:flex;align-items:center;gap:8px;font-size:12px;font-weight:800;color:var(--ink-soft)}
.mock-src i{width:8px;height:8px;border-radius:50%;background:var(--ok);box-shadow:0 0 0 4px var(--ok-tint)}
.mock-badge{background:var(--primary-tint);color:var(--primary-deep);font-size:11.5px;font-weight:800;padding:5px 11px;border-radius:99px}
.mock .mock-h{margin:0 0 3px;font-size:15.5px;font-weight:800;letter-spacing:-.02em}
.mock .who{font-size:12.5px;color:var(--ink-soft);font-weight:700;margin-bottom:12px}
.rx{width:100%;border-collapse:collapse;font-size:12.5px;font-weight:800;margin-bottom:12px}
.rx th{font-size:10.5px;color:var(--ink-soft);font-weight:800;text-align:center;padding:0 0 6px;letter-spacing:.04em}
.rx td{text-align:center;padding:8px 0;border-top:1px solid var(--line)}
.rx td:first-child{text-align:left;color:var(--ink-soft)}
.mock-sum{display:flex;justify-content:space-between;align-items:center;padding:11px 12px;border-radius:12px;background:var(--pop-tint);font-size:13px;font-weight:800;margin-bottom:12px}
.mock-sum span{color:var(--ink-soft);font-weight:700}
.mock-sum b{color:var(--pop-deep)}
.mock-btn{display:flex;align-items:center;justify-content:center;gap:8px;background:var(--primary);color:#fff;font-weight:800;font-size:13.5px;padding:11px;border-radius:12px}
.mock-float{position:absolute;background:#fff;color:var(--ink);border-radius:14px;padding:10px 14px;box-shadow:0 16px 32px -12px rgba(11,17,60,.4);font-size:12.5px;font-weight:800;display:flex;align-items:center;gap:8px;z-index:2}
.mock-float .sw{width:8px;height:8px;border-radius:50%;background:var(--ok)}
.mock-float.f1{top:-18px;right:-8px;transform:rotate(4deg)}
.mock-float.f2{bottom:-16px;left:-22px;transform:rotate(-4deg)}
.mock-float.f2 .sw{background:#1faa53}

.strip{background:#fff;border-bottom:1px solid var(--line)}
.strip-row{display:grid;grid-template-columns:repeat(4,1fr)}
.strip-item{padding:22px 20px;display:flex;gap:12px;align-items:flex-start;border-left:1px solid var(--line)}
.strip-item:first-child{border-left:0;padding-left:0}
.strip-ico{flex:none;width:36px;height:36px;border-radius:11px;display:grid;place-items:center;background:var(--primary-tint);color:var(--primary-deep)}
.strip-ico svg{width:19px;height:19px}
.strip-item div b{display:block;font-size:14.5px;font-weight:800}
.strip-item div span{display:block;font-size:13px;color:var(--ink-soft);font-weight:600;line-height:1.4;margin-top:2px}
.strip-ico svg{display:block}

section{padding:96px 0}
section.flush{padding-top:0}
.head{max-width:60ch;margin-bottom:44px}
.head h2{font-size:clamp(1.85rem,3.6vw,2.7rem);line-height:1.1}
.head p{color:var(--ink-soft);margin:14px 0 0;font-size:1.06rem;font-weight:600}
.head.center{text-align:center;margin-left:auto;margin-right:auto}

.medula{background:var(--ink);color:#fff;position:relative;overflow:hidden}
.medula::before{content:"";position:absolute;left:-200px;top:-200px;width:560px;height:560px;border-radius:50%;background:radial-gradient(circle,rgba(51,70,255,.4),rgba(51,70,255,0) 65%)}
.medula .wrap{position:relative}
.medula .kicker{color:#aab3ff}
.medula .head{margin-bottom:28px}
.medula .head p{color:#c9c8d6}
.med-grid{display:grid;grid-template-columns:1fr 1fr;gap:56px;align-items:center}
.med-steps{list-style:none;margin:0;padding:0;display:grid;gap:14px}
.med-steps li{display:grid;grid-template-columns:44px 1fr;gap:16px;align-items:start;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:18px;padding:18px}
.med-num{width:44px;height:44px;border-radius:13px;display:grid;place-items:center;font-weight:800;background:var(--grad)}
.med-steps h3{font-size:1.05rem;margin-bottom:4px}
.med-steps p{margin:0;color:#c9c8d6;font-size:14.5px;font-weight:600;line-height:1.5}
.med-flow{display:grid;gap:14px}
.flow-card{background:#fff;color:var(--ink);border-radius:18px;padding:18px 20px;box-shadow:0 24px 48px -24px rgba(0,0,0,.6)}
.flow-card small{display:block;font-size:11.5px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--ink-soft);margin-bottom:6px}
.flow-card b{font-size:15px}
.flow-card .row{display:flex;justify-content:space-between;gap:12px;font-size:13.5px;font-weight:700;margin-top:8px}
.flow-card .row span{color:var(--ink-soft)}
.flow-arrow{display:flex;align-items:center;gap:10px;justify-content:center;color:#aab3ff;font-size:13px;font-weight:800}
.flow-arrow svg{width:22px;height:22px}
.privacy{margin-top:26px;display:flex;gap:12px;align-items:flex-start;background:rgba(28,122,77,.18);border:1px solid rgba(118,224,168,.3);border-radius:16px;padding:16px 18px;font-size:14px;font-weight:600;color:#d6f5e4;line-height:1.5}
.privacy svg{flex:none;width:20px;height:20px;margin-top:1px;color:#76e0a8}

.timeline{position:relative;display:grid;grid-template-columns:repeat(4,1fr);gap:28px}
.tl-line{position:absolute;top:19px;left:0;right:0;height:3px;background:var(--grad);opacity:.25;border-radius:99px;z-index:0}
.tl-step{position:relative;z-index:1}
.tl-node{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:14.5px;color:#fff;margin-bottom:18px;box-shadow:0 10px 20px -6px rgba(20,19,31,.35)}
.tl-node.n1{background:var(--primary)}
.tl-node.n2{background:#7a2de6}
.tl-node.n3{background:var(--mid)}
.tl-node.n4{background:var(--pop)}
.tl-step h3{font-size:1.1rem;margin-bottom:8px}
.tl-step p{font-size:14.5px;color:var(--ink-soft);margin:0 0 12px;font-weight:600;line-height:1.5}
.tl-tag{display:inline-block;font-size:12px;font-weight:800;color:var(--primary-deep);background:var(--primary-tint);padding:5px 11px;border-radius:99px}

.groups{display:grid;gap:64px}
.group-head{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;margin-bottom:22px;flex-wrap:wrap}
.group-head h3{font-size:clamp(1.35rem,2.4vw,1.7rem)}
.group-head p{margin:6px 0 0;color:var(--ink-soft);font-weight:600;max-width:52ch}
.group-tag{font-size:12.5px;font-weight:800;padding:6px 12px;border-radius:99px}
.g-blue .group-tag,.g-blue .feat-ico{background:var(--primary-tint);color:var(--primary-deep)}
.g-mid .group-tag,.g-mid .feat-ico{background:var(--mid-tint);color:var(--mid-deep)}
.g-pop .group-tag,.g-pop .feat-ico{background:var(--pop-tint);color:var(--pop-deep)}
.feat-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.feat{background:var(--card);border:2px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 16px 32px -24px rgba(20,19,31,.18);transition:border-color .15s ease,transform .15s ease}
.feat:hover{border-color:#dcdcf0;transform:translateY(-2px)}
.feat-ico{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;margin-bottom:14px}
.feat-ico svg{width:21px;height:21px}
.feat h4{font-size:1.04rem;margin:0 0 7px;letter-spacing:-.01em}
.feat p{font-size:14px;color:var(--ink-soft);margin:0;font-weight:600;line-height:1.55}

.story{background:var(--grad);color:#fff;border-radius:32px;padding:64px 56px;display:grid;grid-template-columns:auto 1fr;gap:48px;align-items:center}
.story-mark{width:150px;height:150px}
.story-mark svg{width:100%;height:100%}
.story .kicker{color:#fff}
.story .kicker::before{background:#fff}
.story h2{font-size:clamp(1.8rem,3.4vw,2.6rem);line-height:1.1;margin-bottom:16px}
.story p{margin:0 0 12px;font-size:1.06rem;font-weight:600;line-height:1.6;opacity:.95;max-width:62ch}
.story p:last-child{margin:0}

.camp{display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;margin:0 auto 28px;max-width:720px;background:var(--pop-tint);color:var(--pop-deep);border:1.5px solid #ffcfc6;border-radius:16px;padding:14px 18px;font-weight:800;font-size:14.5px;text-align:center}
.camp b{background:var(--pop);color:#fff;border-radius:99px;padding:4px 11px;font-size:12px;letter-spacing:.04em;text-transform:uppercase}
.price-grid{display:grid;grid-template-columns:repeat(2,minmax(0,420px));gap:22px;justify-content:center}
.plan{background:#fff;border:2px solid var(--line);border-radius:26px;padding:32px;display:flex;flex-direction:column;box-shadow:0 20px 40px -28px rgba(20,19,31,.2);position:relative}
.plan.hl{border-color:var(--primary);box-shadow:0 26px 50px -24px rgba(51,70,255,.45)}
.plan.hl::before{content:"Zincirler için";position:absolute;top:-13px;left:32px;background:var(--primary);color:#fff;font-size:12px;font-weight:800;padding:5px 12px;border-radius:99px}
.plan h3{font-size:1.25rem}
.plan .desc{color:var(--ink-soft);font-weight:600;font-size:14.5px;margin:6px 0 20px}
.plan .amount{font-size:2.3rem;font-weight:800;letter-spacing:-.03em;line-height:1}
.plan .per{font-size:14px;color:var(--ink-soft);font-weight:700;margin-left:4px;letter-spacing:0}
.plan .ask{font-size:1.2rem;font-weight:800;color:var(--primary-deep);line-height:1.25}
.plan ul{list-style:none;padding:0;margin:22px 0 26px;display:grid;gap:10px;font-size:14.5px;font-weight:700}
.plan li{display:flex;gap:10px;align-items:flex-start}
.plan li::before{content:"";flex:none;width:18px;height:18px;margin-top:2px;border-radius:50%;background:var(--primary-tint) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3E%3Cpath d='M5 10.5l3 3 7-7' fill='none' stroke='%231c2ecc' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E") center/13px no-repeat}
.plan .btn{margin-top:auto;width:100%}
.included{display:flex;flex-wrap:wrap;justify-content:center;gap:10px 22px;margin-top:28px;font-size:14px;font-weight:700;color:var(--ink-soft)}
.included span::before{content:"✓";color:var(--ok);margin-right:6px;font-weight:800}

.trust-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.trust{background:#fff;border:2px solid var(--line);border-radius:20px;padding:24px}
.trust .feat-ico{background:var(--ok-tint);color:var(--ok)}
.trust h3{font-size:1.02rem;margin:0 0 7px}
.trust p{font-size:14px;color:var(--ink-soft);margin:0;font-weight:600;line-height:1.55}

.faq{max-width:820px;margin:0 auto}
.faq details{border-top:2px solid var(--line)}
.faq details:last-child{border-bottom:2px solid var(--line)}
.faq summary{list-style:none;cursor:pointer;padding:22px 48px 22px 0;font-size:1.06rem;font-weight:800;position:relative}
.faq summary::-webkit-details-marker{display:none}
.faq summary::after{content:"+";position:absolute;right:4px;top:50%;transform:translateY(-50%);width:30px;height:30px;border-radius:50%;background:var(--primary-tint);color:var(--primary-deep);display:grid;place-items:center;font-size:20px;font-weight:700;line-height:1}
.faq details[open] summary::after{content:"–"}
.faq details p{margin:0 0 22px;color:var(--ink-soft);font-weight:600;font-size:.99rem;line-height:1.6;max-width:70ch}

.cta-band{background:var(--pop);color:#fff;text-align:center;padding:88px 0}
.cta-band h2{font-size:clamp(2rem,5vw,3.2rem);max-width:20ch;margin:0 auto;line-height:1.08}
.cta-band p{margin:18px auto 0;max-width:48ch;font-weight:700;opacity:.95;font-size:1.05rem}
.cta-band .hero-ctas{justify-content:center}
.cta-band .btn-pop{background:#fff;color:var(--pop-deep);box-shadow:0 14px 28px -12px rgba(120,20,10,.5)}
.cta-band .btn-pop:hover{background:#fff0ec}

footer{background:var(--ink);color:#fff;padding:56px 0 32px}
.foot-grid{display:grid;grid-template-columns:1.4fr 1fr 1fr;gap:32px}
.foot-grid .wordmark{color:#fff}
.foot-grid p{color:#b6b5c4;font-size:14px;font-weight:600;max-width:36ch;margin:14px 0 0;line-height:1.55}
.foot-col .foot-h{margin:0 0 12px;font-size:12.5px;letter-spacing:.08em;text-transform:uppercase;color:#8d8c9e;font-weight:800}
.foot-col a,.foot-col span{display:block;color:#e4e3ee;text-decoration:none;font-size:14.5px;font-weight:700;padding:4px 0}
.foot-col a:hover{color:#fff;text-decoration:underline}
.foot-bottom{display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px;border-top:1px solid rgba(255,255,255,.12);margin-top:40px;padding-top:22px;font-size:13px;color:#8d8c9e;font-weight:700}

.mbar{display:none}

@media (max-width:980px){
  .strip-row{grid-template-columns:repeat(2,1fr)}
  .strip-item{border-top:1px solid var(--line)}
  .strip-item:nth-child(-n+2){border-top:0}
  .strip-item:nth-child(3){border-left:0;padding-left:0}
  .feat-grid,.trust-grid{grid-template-columns:repeat(2,1fr)}
}
@media (max-width:860px){
  .nav-links a:not(.btn){display:none}
  .nav-links{gap:8px}
  .nav-links .btn{padding:9px 14px;font-size:13.5px}
  .wordmark{font-size:18px}
  .hero{padding-top:48px}
  .hero-shape{display:none}
  .hero-grid,.med-grid{grid-template-columns:1fr}
  .hero-inner{padding-bottom:88px}
  .mock-wrap{margin-top:16px;max-width:360px}
  .mock{transform:rotate(-1deg)}
  .mock-float.f1{right:0}
  .mock-float.f2{left:0}
  section{padding:72px 0}
  .timeline{grid-template-columns:1fr;gap:32px}
  .tl-line{display:none}
  .story{grid-template-columns:1fr;padding:44px 28px;gap:24px;border-radius:26px}
  .story-mark{width:84px;height:84px}
  .price-grid{grid-template-columns:1fr}
  .foot-grid{grid-template-columns:1fr 1fr}
  .foot-grid > div:first-child{grid-column:1 / -1}
}
@media (max-width:620px){
  .wrap{padding-left:16px;padding-right:16px}
  .nav-links .btn-outline{display:none}
  .strip-row{grid-template-columns:1fr}
  .strip-item,.strip-item:nth-child(3){border-left:0;padding-left:0;border-top:1px solid var(--line)}
  .strip-item:first-child{border-top:0}
  .strip-item:nth-child(2){border-top:1px solid var(--line)}
  .feat-grid,.trust-grid{grid-template-columns:1fr}
  .hero-ctas .btn{width:100%}
  .plan{padding:26px 22px}
  .story{padding:36px 22px}
  body.has-mbar{padding-bottom:76px}
  .mbar{display:flex;gap:8px;position:fixed;left:0;right:0;bottom:0;z-index:40;padding:10px 12px calc(10px + env(safe-area-inset-bottom));background:rgba(250,250,250,.94);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);border-top:1px solid var(--line)}
  .mbar .btn{flex:1;padding:13px 10px;font-size:14.5px}
  .mbar .btn-wa{border-color:var(--line)}
}

/* ============================================================
   v4.4 — Görsel yükseltme katmanı (yalnızca CSS; JS/CSP güvenli)
   ============================================================ */

/* Erişilebilir, tutarlı odak halkası (klavye/erişilebilirlik + cila) */
a:focus-visible,.btn:focus-visible,summary:focus-visible{outline:none;box-shadow:0 0 0 3px #fff,0 0 0 6px var(--primary);border-radius:14px}
.hero a:focus-visible,.cta-band a:focus-visible{box-shadow:0 0 0 3px rgba(255,255,255,.35),0 0 0 6px #fff}

/* Birincil düğmeye ince ışık/derinlik ve daha canlı hover */
.btn-pop{background:linear-gradient(180deg,#ff5a49,#ff4433);box-shadow:0 10px 22px -8px rgba(224,71,42,.55),inset 0 1px 0 rgba(255,255,255,.28)}
.btn-pop:hover{background:linear-gradient(180deg,#ff4433,#db2f20);transform:translateY(-2px);box-shadow:0 18px 34px -10px rgba(224,71,42,.6),inset 0 1px 0 rgba(255,255,255,.28)}
.btn-pop:active{transform:translateY(0)}
.btn-wa:hover{box-shadow:0 14px 28px -12px rgba(11,17,60,.35)}

/* Kartlarda tutarlı derinlik ve yumuşak, hızlı hover */
.feat,.trust,.plan,.who-card{transition:transform .2s cubic-bezier(.2,.7,.3,1),box-shadow .2s ease,border-color .2s ease}
.feat:hover{border-color:#d4d4f2;transform:translateY(-4px);box-shadow:0 26px 44px -28px rgba(20,19,31,.32)}
.trust:hover{transform:translateY(-4px);box-shadow:0 26px 44px -28px rgba(20,19,31,.28)}
.g-blue .feat:hover{box-shadow:0 26px 44px -26px rgba(51,70,255,.35)}
.g-mid .feat:hover{box-shadow:0 26px 44px -26px rgba(192,38,211,.32)}
.g-pop .feat:hover{box-shadow:0 26px 44px -26px rgba(255,68,51,.3)}
.feat-ico,.strip-ico{transition:transform .2s cubic-bezier(.2,.7,.3,1)}
.feat:hover .feat-ico{transform:scale(1.08) rotate(-3deg)}

/* Kanıt şeridinde üstte ince marka çizgisi */
.strip{position:relative}
.strip::before{content:"";position:absolute;top:0;left:0;right:0;height:3px;background:var(--grad);opacity:.9}

/* Hero'ya ince nokta dokusu (derinlik) */
.hero::before{content:"";position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.13) 1px,transparent 1.4px);background-size:22px 22px;mask-image:linear-gradient(180deg,rgba(0,0,0,.5),transparent 62%);-webkit-mask-image:linear-gradient(180deg,rgba(0,0,0,.5),transparent 62%);pointer-events:none;z-index:0}
.hero .wrap{position:relative;z-index:1}

/* Bölüm başlıklarının üst çizgisi biraz daha belirgin/animasyonlu */
.kicker::before{transition:width .3s ease}
.head:hover .kicker::before{width:30px}

/* SSS: açılırken içerik yumuşak insin */
@media (prefers-reduced-motion:no-preference){
  .faq details[open] p{animation:of-fade .28s ease both}
  @keyframes of-fade{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:none}}
}

/* Kaydırınca beliren bölümler — saf CSS (scroll-driven).
   Desteklemeyen tarayıcıda (@supports başarısız) içerik olduğu gibi görünür;
   hareket azaltma açıksa hiç oynatılmaz. Hero'ya UYGULANMAZ (ilk ekran anında görünür). */
@media (prefers-reduced-motion:no-preference){
  @supports (animation-timeline: view()){
    .strip-item,.head,.med-steps li,.flow-card,.flow-arrow,.tl-step,.feat,.story,.plan,.included,.trust,.faq details,.cta-band .wrap{
      animation:of-rise linear both;
      animation-timeline:view();
      animation-range:entry 2% cover 26%;
    }
    @keyframes of-rise{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}
    /* Kart ızgaralarında hafif kademeli giriş */
    .feat-grid .feat:nth-child(2),.trust-grid .trust:nth-child(2){animation-range:entry 6% cover 30%}
    .feat-grid .feat:nth-child(3),.trust-grid .trust:nth-child(3){animation-range:entry 10% cover 34%}
  }
}
</style>
<?= ga_head() ?>
</head>
<body<?= $hasBar ? ' class="has-mbar"' : '' ?>>
<header>
  <div class="wrap nav">
    <a class="wordmark" href="/" aria-label="OptiFlow ana sayfa"><?= pz_logo('lgNav') ?>OptiFlow</a>
    <nav class="nav-links" aria-label="Ana menü">
      <a class="nav-anchor" href="#medula">Medula köprüsü</a>
      <a class="nav-anchor" href="#ozellikler">Özellikler</a>
      <a class="nav-anchor" href="#fiyatlar">Fiyatlar</a>
      <a class="nav-anchor" href="#sss">Sorular</a>
      <a class="nav-anchor" href="rehber.php">Rehber</a>
      <a class="btn btn-outline on-light" href="magaza-giris.php">Giriş yap</a>
      <a class="btn btn-pop" href="kayit.php">Ücretsiz deneyin</a>
    </nav>
  </div>
</header>

<div class="hero">
  <div class="hero-shape s1" aria-hidden="true"></div>
  <div class="hero-shape s2" aria-hidden="true"></div>
  <div class="wrap hero-inner">
    <div class="hero-grid">
      <div>
        <span class="hero-pill"><b>YENİ</b> Medula Optik köprüsüyle reçete aktarımı</span>
        <h1>Medula'daki reçete, <em>tek tıkla</em> siparişte.</h1>
        <p class="sub">OptiFlow, gözlükçüler için yapılmış yönetim sistemidir. Reçeteyi elle yazmazsınız, SGK katkı payı kendiliğinden hesaplanır, atölye her siparişi panodan takip eder, gözlük hazır olunca müşteriye WhatsApp gider.</p>
        <div class="hero-ctas">
          <a class="btn btn-pop" href="kayit.php">30 gün ücretsiz deneyin</a>
          <?php if ($iletisimUrl !== ''): ?>
            <a class="btn btn-wa" href="<?= pz_e($iletisimUrl) ?>" target="_blank" rel="noopener"><?= $wa ? $ico['wa'] : '' ?><?= pz_e($iletisimAd) ?></a>
          <?php elseif ($video !== ''): ?>
            <a class="btn btn-outline" href="<?= pz_e($video) ?>" target="_blank" rel="noopener">1 dakikalık videoyu izleyin</a>
          <?php else: ?>
            <a class="btn btn-outline" href="#medula">Nasıl çalıştığını görün</a>
          <?php endif; ?>
        </div>
        <ul class="hero-note">
          <li>Kredi kartı gerekmez</li>
          <li><?= $p['kurulum_destegi'] ? 'Kurulumu sizinle birlikte yapıyoruz' : 'Teknik bilgi gerekmez' ?></li>
          <li>Her mağazaya ayrı veritabanı</li>
        </ul>
      </div>
      <div class="mock-wrap" aria-hidden="true">
        <div class="mock">
          <div class="mock-top">
            <span class="mock-src"><i></i>Medula'dan aktarıldı</span>
            <span class="mock-badge">Yeni reçete</span>
          </div>
          <div class="mock-h">Uzak gözlük</div>
          <div class="who">Merve K. · E-reçete</div>
          <table class="rx">
            <tr><th></th><th>SFERİK</th><th>SİLENDİRİK</th><th>AKS</th></tr>
            <tr><td>Sağ</td><td>+1,25</td><td>−0,75</td><td>45°</td></tr>
            <tr><td>Sol</td><td>+1,00</td><td>−0,50</td><td>130°</td></tr>
          </table>
          <div class="mock-sum"><span>SGK katkı payı</span><b>Otomatik hesaplandı</b></div>
          <div class="mock-btn">Siparişe aktar →</div>
        </div>
        <div class="mock-float f1"><span class="sw"></span>Elle yazım yok</div>
        <div class="mock-float f2"><span class="sw"></span>WhatsApp: "Gözlüğünüz hazır"</div>
      </div>
    </div>
  </div>
</div>

<div class="strip">
  <div class="wrap strip-row">
    <div class="strip-item"><span class="strip-ico"><?= $ico['bolt'] ?></span><div><b>Medula köprüsü</b><span>Reçete değerleri tek tuşla aktarılır</span></div></div>
    <div class="strip-item"><span class="strip-ico"><?= $ico['wallet'] ?></span><div><b>SGK katkı payı</b><span>Kullanım şekline göre otomatik önerilir</span></div></div>
    <div class="strip-item"><span class="strip-ico"><?= $ico['shield'] ?></span><div><b>Ayrı veritabanı</b><span>Verileriniz başka mağazayla karışmaz</span></div></div>
    <div class="strip-item"><span class="strip-ico"><?= $ico['phone'] ?></span><div><b>Her cihazda</b><span>Bilgisayar, tablet ve telefonda çalışır</span></div></div>
  </div>
</div>

<main>
  <section class="medula" id="medula">
    <div class="wrap">
      <div class="med-grid">
        <div>
          <div class="head">
            <span class="kicker">Medula köprüsü</span>
            <h2>Reçeteyi bir daha elle yazmayın.</h2>
            <p>Medula Optik ekranındaki sferik, silendirik ve aks değerleri kutuların içinde durduğu için kopyala-yapıştırla gelmez. OptiFlow köprüsü bu değerleri doğru sırayla alır ve siparişe hazır hale getirir.</p>
          </div>
          <ol class="med-steps">
            <li><span class="med-num">1</span><div><h3>Medula'da reçeteyi açın</h3><p>Her zamanki gibi. Ekranın sağ altında "Atölyeye aktar" düğmesi belirir.</p></div></li>
            <li><span class="med-num">2</span><div><h3>Düğmeye basın</h3><p>Hasta, reçete ve uzak/yakın cam değerleri OptiFlow'a düşer; yakın değerlerden ADD de hesaplanır.</p></div></li>
            <li><span class="med-num">3</span><div><h3>Kontrol edin, siparişe çevirin</h3><p>Değerleri önizlemede görürsünüz. Siz onaylamadan hiçbir şey siparişe yazılmaz; SGK katkı payı otomatik eklenir.</p></div></li>
          </ol>
          <div class="privacy"><?= $ico['lock'] ?><span>Köprü SGK kullanıcı adınızı ve şifrenizi <b>okumaz, saklamaz</b>, otomatik giriş yapmaz ve arka planda veri göndermez. Yalnızca siz düğmeye bastığınızda, o an ekranda görünen reçeteyi aktarır.</span></div>
        </div>
        <div class="med-flow" aria-hidden="true">
          <div class="flow-card"><small>Medula Optik</small><b>E-reçete · Uzak + yakın</b><div class="row"><span>Sağ</span>+1,25 / −0,75 / 45°</div><div class="row"><span>Sol</span>+1,00 / −0,50 / 130°</div></div>
          <div class="flow-arrow"><?= $ico['down'] ?>Atölyeye aktar</div>
          <div class="flow-card"><small>OptiFlow · Sipariş</small><b>Cam, çerçeve ve fiyat tek ekranda</b><div class="row"><span>ADD</span>+2,00 (otomatik)</div><div class="row"><span>SGK katkı payı</span>Otomatik önerildi</div></div>
          <div class="flow-arrow"><?= $ico['down'] ?>Atölye panosu</div>
          <div class="flow-card"><small>Müşteri</small><b>"Gözlüğünüz hazır" · WhatsApp</b><div class="row"><span>Takip bağlantısı</span>Siparişim nerede?</div></div>
        </div>
      </div>
    </div>
  </section>

  <section id="nasil-calisir">
    <div class="wrap">
      <div class="head">
        <span class="kicker">Bir siparişin yolculuğu</span>
        <h2>Reçeteden teslimata, hiçbir adım elle hesaplanmaz.</h2>
      </div>
      <div class="timeline">
        <div class="tl-line" aria-hidden="true"></div>
        <div class="tl-step"><div class="tl-node n1">01</div><h3>Reçete gelir</h3><p>Medula köprüsüyle tek tıkla ya da elle; müşterinin eski reçeteleriyle yan yana.</p><span class="tl-tag">Köprü ya da elle</span></div>
        <div class="tl-step"><div class="tl-node n2">02</div><h3>Sipariş oluşur</h3><p>Cam, çerçeve ve fiyat tek ekranda; SGK katkı payı ve kalan bakiye otomatik hesaplanır.</p><span class="tl-tag">Otomatik fiyat</span></div>
        <div class="tl-step"><div class="tl-node n3">03</div><h3>Atölye takip eder</h3><p>Cam bekleniyor → montajda → kontrolde → hazır. Panoda ve atölye ekranında tek bakışta.</p><span class="tl-tag">4 aşamalı pano</span></div>
        <div class="tl-step"><div class="tl-node n4">04</div><h3>Müşteriye haber gider</h3><p>Hazır olunca WhatsApp mesajı takip bağlantısıyla gider; almaya gelmezse hatırlatma listesine düşer.</p><span class="tl-tag">Otomatik WhatsApp</span></div>
      </div>
    </div>
  </section>

  <section id="ozellikler" class="flush">
    <div class="wrap">
      <div class="head">
        <span class="kicker">Panelin içinde</span>
        <h2>Sadece sipariş takibi değil: dükkânın tamamı.</h2>
        <p>Her modül, bir optik atölyesindeki gerçek bir günlük işi otomatikleştirmek için yapıldı.</p>
      </div>
      <div class="groups">
        <?php foreach ($grup as [$cls, $baslik, $acik, $etiket, $ozellikler]): ?>
        <div class="<?= $cls ?>">
          <div class="group-head">
            <div><h3><?= pz_e($baslik) ?></h3><p><?= pz_e($acik) ?></p></div>
            <span class="group-tag"><?= pz_e($etiket) ?></span>
          </div>
          <div class="feat-grid">
            <?php foreach ($ozellikler as [$i, $h, $t]): ?>
            <div class="feat"><span class="feat-ico"><?= $ico[$i] ?></span><h4><?= pz_e($h) ?></h4><p><?= pz_e($t) ?></p></div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section id="hikaye" class="flush">
    <div class="wrap">
      <div class="story">
        <div class="story-mark"><?= pz_logo('lgStory', '#fff') ?></div>
        <div>
          <span class="kicker">Neden OptiFlow</span>
          <h2>Bir yazılım ofisinde değil, bir optik atölyesinde doğdu.</h2>
          <p>OptiFlow; her gün reçete girilen, cam beklenen, müşterinin "gözlüğüm hazır mı?" diye aradığı gerçek bir gözlükçü dükkânında, o tezgâhın ihtiyaçlarına göre geliştirildi.</p>
          <p>Medula köprüsünden kasa sayımına kadar her özellik, o dükkânda yaşanan bir soruna verilmiş cevaptır. Şimdi aynı sistemi sizin mağazanız için açıyoruz.</p>
        </div>
      </div>
    </div>
  </section>

  <section id="fiyatlar" class="flush">
    <div class="wrap">
      <div class="head center">
        <span class="kicker">Fiyatlar</span>
        <h2>Sade fiyat, sürpriz yok.</h2>
        <p>30 gün boyunca her şey açık ve ücretsiz. Beğenirseniz devam edersiniz.</p>
      </div>
      <?php if (!empty($kamp['aktif'])): ?>
        <div class="camp"><b><?= pz_e($kamp['baslik']) ?></b><span><?= pz_e($kamp['metin']) ?></span></div>
      <?php endif; ?>
      <div class="price-grid">
        <?php foreach ($p['paketler'] as $pk): $vurgu = !empty($pk['vurgu']); ?>
        <div class="plan<?= $vurgu ? ' hl' : '' ?>">
          <h3><?= pz_e($pk['ad']) ?></h3>
          <p class="desc"><?= pz_e($pk['aciklama']) ?></p>
          <?php if (trim((string) $pk['fiyat']) !== ''): ?>
            <div class="amount"><?= pz_e($pk['fiyat']) ?><span class="per"><?= pz_e($pk['donem']) ?></span></div>
          <?php else: ?>
            <div class="ask">Size özel fiyat için bize yazın</div>
          <?php endif; ?>
          <ul><?php foreach ($pk['ozellikler'] as $oz): ?><li><?= pz_e($oz) ?></li><?php endforeach; ?></ul>
          <?php if ($vurgu && $iletisimUrl !== ''): ?>
            <a class="btn btn-pop" href="<?= pz_e($iletisimUrl) ?>" target="_blank" rel="noopener">Görüşelim</a>
          <?php else: ?>
            <a class="btn <?= $vurgu ? 'btn-pop' : 'btn-outline on-light' ?>" href="kayit.php">30 gün ücretsiz deneyin</a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="included">
        <span>Kurulum ücreti yok</span>
        <?php if ($p['kurulum_destegi']): ?><span>Kurulumu birlikte yapıyoruz</span><?php endif; ?>
        <?php if ($p['veri_aktarim_destegi']): ?><span>Eski müşteri listenizi biz aktarıyoruz</span><?php endif; ?>
        <span>Sınırsız müşteri ve sipariş</span>
      </div>
    </div>
  </section>

  <section id="guven" class="flush">
    <div class="wrap">
      <div class="head">
        <span class="kicker">Güven ve KVKK</span>
        <h2>Reçete sağlık verisidir. Öyle korunur.</h2>
        <p>Müşterilerinizin göz numarası ve SGK bilgisi en hassas verinizdir. OptiFlow bunu en baştan hesaba katarak tasarlandı.</p>
      </div>
      <div class="trust-grid">
        <div class="trust"><span class="feat-ico"><?= $ico['db'] ?></span><h3>Her mağazaya ayrı veritabanı</h3><p>Verileriniz başka mağazaların verisiyle aynı yerde durmaz; bir mağazanın bilgisi başka bir mağazaya asla görünmez.</p></div>
        <div class="trust"><span class="feat-ico"><?= $ico['lock'] ?></span><h3>SGK şifreniz bizde değil</h3><p>Medula köprüsü şifre okumaz, saklamaz, otomatik giriş yapmaz. Yalnızca siz düğmeye bastığınızda çalışır.</p></div>
        <div class="trust"><span class="feat-ico"><?= $ico['shield'] ?></span><h3>Güvenli oturum</h3><p>Art arda hatalı girişte hesap geçici olarak kilitlenir, işlem yapılmayan oturum kapanır, parola değişince diğer cihazlardan çıkış yapılır.</p></div>
        <div class="trust"><span class="feat-ico"><?= $ico['users'] ?></span><h3>Rol ve şube yetkileri</h3><p>Personel yalnızca kendi şubesini görür; tutarları kimin görebileceğini siz belirlersiniz.</p></div>
        <div class="trust"><span class="feat-ico"><?= $ico['screen'] ?></span><h3>Ekranda kişisel veri yok</h3><p>Atölye ekranı yalnızca sipariş numarası, baş harfler ve aşamayı gösterir; fiyat, telefon ve reçete görünmez.</p></div>
        <div class="trust"><span class="feat-ico"><?= $ico['down'] ?></span><h3>Yedeğiniz elinizde</h3><p>Veritabanınızın yedeğini istediğiniz an tek tıkla indirirsiniz. Veriniz sizindir.</p></div>
      </div>
    </div>
  </section>

  <section id="sss" class="flush">
    <div class="wrap">
      <div class="head center"><span class="kicker">Sorular</span><h2>Sık sorulan sorular</h2></div>
      <div class="faq">
        <?php foreach ($sss as $n => [$s, $c]): ?>
        <details<?= $n === 0 ? ' open' : '' ?>><summary><?= pz_e($s) ?></summary><p><?= pz_e($c) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<div class="cta-band">
  <div class="wrap">
    <h2>Yarın sabah dükkânı OptiFlow ile açın.</h2>
    <p><?= $p['kurulum_destegi'] ? 'Başvurunuzu bırakın; mağazanızı açıp sizi arayalım, ilk ayarları birlikte yapalım. Kredi kartı istemiyoruz.' : 'Mağazanızı birkaç dakikada kurun; kredi kartı istemiyoruz.' ?></p>
    <div class="hero-ctas">
      <a class="btn btn-pop" href="kayit.php">Mağazamı oluştur</a>
      <?php if ($iletisimUrl !== ''): ?><a class="btn btn-outline" href="<?= pz_e($iletisimUrl) ?>" target="_blank" rel="noopener"><?= pz_e($iletisimAd) ?></a><?php endif; ?>
    </div>
  </div>
</div>

<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <a class="wordmark" href="/"><?= pz_logo('lgFoot') ?>OptiFlow</a>
        <p>Gözlükçüler için reçeteden satışa yönetim sistemi. Medula köprüsü, SGK katkı payı, atölye, kasa ve çok şube tek panelde.</p>
      </div>
      <div class="foot-col">
        <p class="foot-h">Ürün</p>
        <a href="#medula">Medula köprüsü</a>
        <a href="#ozellikler">Özellikler</a>
        <a href="#fiyatlar">Fiyatlar</a>
        <a href="#guven">Güven ve KVKK</a>
        <a href="rehber.php">Gözlükçüler için rehber</a>
        <a href="magaza-giris.php">Mağaza girişi</a>
      </div>
      <div class="foot-col">
        <p class="foot-h">İletişim</p>
        <?php if ($wa): ?><a href="<?= pz_e($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php if ($tel): ?><a href="<?= pz_e($tel) ?>"><?= pz_e($p['telefon']) ?></a><?php endif; ?>
        <?php if ($mail !== ''): ?><a href="mailto:<?= pz_e($mail) ?>"><?= pz_e($mail) ?></a><?php endif; ?>
        <?php if ($p['instagram'] !== ''): ?><a href="https://instagram.com/<?= pz_e($p['instagram']) ?>" target="_blank" rel="noopener">Instagram</a><?php endif; ?>
        <?php if ($p['adres'] !== ''): ?><span><?= pz_e($p['adres']) ?></span><?php endif; ?>
        <a href="kvkk.php">KVKK aydınlatma metni</a>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> <?= pz_e($p['sirket_unvani'] !== '' ? $p['sirket_unvani'] : 'OptiFlow') ?></span>
      <span>Türkiye'deki gözlükçüler için geliştirildi.</span>
    </div>
  </div>
</footer>

<?php if ($hasBar): ?>
<div class="mbar">
  <a class="btn btn-pop" href="kayit.php">Ücretsiz deneyin</a>
  <a class="btn btn-wa" href="<?= pz_e($iletisimUrl) ?>" target="_blank" rel="noopener"><?= $wa ? $ico['wa'] . 'WhatsApp' : 'Bize ulaşın' ?></a>
</div>
<?php endif; ?>
</body>
</html><?php
    exit;
}
