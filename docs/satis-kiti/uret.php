<?php
/**
 * OptiFlow saha satış kiti — A4 sayfaları üretir (render/*.html). PDF'e başsız Chrome ile basılır (Page.printToPDF).
 * Çalıştırma (depo kökünden): php docs/satis-kiti/uret.php
 * İçerik kaynakları: app/pazarlama.php (iletişim, fiyat), app/karsilama.php (özellik ve güven metinleri).
 */
declare(strict_types=1);
chdir(__DIR__ . '/../..');
require 'app/qr.php';
require 'app/pazarlama.php';

$D = __DIR__;
@mkdir("$D/render", 0777, true);
$p = optiflow_pazarlama();
$tel = $p['telefon'];
$fiyat = [];
foreach ($p['paketler'] as $pk) { if (($f = pz_paket_fiyat($pk)) !== null) { $fiyat[$pk['ad']] = $f; } }

/* Kampanya bağlantıları: GA4 bunları kaynak olarak kaydeder (Edinme › Trafik edinme). */
$URL_BROSUR = 'https://optiflow.com.tr/?utm_source=brosur&utm_medium=baski&utm_campaign=saha';
$URL_LAB    = 'https://optiflow.com.tr/?utm_source=laboratuvar&utm_medium=baski&utm_campaign=ortaklik';
$qr = fn (string $u, int $k) => str_replace('aria-label="', 'aria-label="Karekod: ' . htmlspecialchars($u) . ' — ', qr_svg($u, $k, 'Q', '#1b1416'));
$isaret = fn (string $id) => '<svg viewBox="0 0 100 100" aria-hidden="true"><defs><linearGradient id="' . $id . '" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#ff6b81"/><stop offset=".55" stop-color="#d0334f"/><stop offset="1" stop-color="#8f1a2e"/></linearGradient></defs><g transform="rotate(-10 50 50)" fill="url(#' . $id . ')"><path fill-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z"/><circle cx="50" cy="50" r="7"/></g></svg>';
$logo = fn (string $id, string $renk = '#1b1416') => '<div class="logo" style="color:' . $renk . '">' . $isaret($id) . '<span>OptiFlow</span></div>';
$e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

$CSS = <<<'CSS'
@font-face{font-family:Manrope;font-weight:200 800;src:url("../../../assets/fonts/manrope-latin-wght-normal.woff2") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+02C6,U+02DA,U+02DC,U+2000-206F,U+20AC,U+20BA,U+2122,U+2190-2193,U+2212}
@font-face{font-family:Manrope;font-weight:200 800;src:url("../../../assets/fonts/manrope-latin-ext-wght-normal.woff2") format("woff2");unicode-range:U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+1E00-1E9F}
@font-face{font-family:"IBM Plex Mono";font-weight:500;src:url("../../../assets/fonts/ibm-plex-mono-latin-500-normal.woff2") format("woff2");unicode-range:U+0000-00FF,U+2000-206F,U+2212}
@font-face{font-family:"IBM Plex Mono";font-weight:500;src:url("../../../assets/fonts/ibm-plex-mono-latin-ext-500-normal.woff2") format("woff2");unicode-range:U+0100-02BA,U+1E00-1E9F}
@page{size:A4;margin:0}
:root{--paper:#f5f3f3;--ink:#1b1416;--ink-2:#463b3e;--muted:#776b6e;--line:#e8e1e2;--brand:#b4233c;--brand-ink:#8f1a2e;--brand-bright:#d0334f;--brand-soft:#fbe4e8;--coral:#ff6b81;--night:#141012;--night-2:#221a1d;--on-night:#f3eef0;--on-night-muted:#ab9fa3}
*{box-sizing:border-box}
html,body{margin:0;background:#fff}
body{font-family:Manrope,Arial,sans-serif;color:var(--ink);font-size:10.5pt;line-height:1.5;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.sayfa{position:relative;width:210mm;height:297mm;overflow:hidden;padding:16mm 16mm 14mm;page-break-after:always;break-after:page;background:#fff}
.sayfa:last-child{page-break-after:auto;break-after:auto}
.mono,.etiket{font-family:"IBM Plex Mono",Consolas,monospace;font-weight:500}
.etiket{font-size:8pt;letter-spacing:.14em;text-transform:uppercase;color:var(--brand-ink)}
h1,h2,h3{margin:0;font-weight:800;letter-spacing:-.025em;line-height:1.08}
h1{font-size:30pt}h2{font-size:19pt}h3{font-size:12pt;letter-spacing:-.01em}
p{margin:0}
.logo{display:flex;align-items:center;gap:3mm;font-weight:800;font-size:15pt;letter-spacing:-.02em}
.logo svg{width:9mm;height:9mm}
.ust{display:flex;justify-content:space-between;align-items:center;padding-bottom:5mm;border-bottom:.4mm solid var(--line);margin-bottom:8mm}
.alt{position:absolute;left:16mm;right:16mm;bottom:9mm;display:flex;justify-content:space-between;font-family:"IBM Plex Mono",monospace;font-size:7.5pt;color:var(--muted)}
.kutu{border:.35mm solid var(--line);border-radius:4mm;padding:5mm 6mm}
.gece{background:var(--night);color:var(--on-night)}
.izgara2{display:grid;grid-template-columns:1fr 1fr;gap:5mm}
.izgara3{display:grid;grid-template-columns:repeat(3,1fr);gap:5mm}
ul.tik{list-style:none;margin:0;padding:0;display:grid;gap:1.6mm}
ul.tik li{padding-left:5mm;position:relative}
ul.tik li::before{content:"";position:absolute;left:0;top:1.6mm;width:2.6mm;height:1.5mm;border-left:.5mm solid var(--brand);border-bottom:.5mm solid var(--brand);transform:rotate(-45deg)}
.gece ul.tik li::before{border-color:var(--coral)}
.sure{font-family:"IBM Plex Mono",monospace;font-weight:500;color:var(--brand-ink);font-size:9pt;white-space:nowrap}
table{width:100%;border-collapse:collapse}
CSS;

$sayfalar = [];

/* ================= 1. BROŞÜR — ÖN ================= */
$sayfalar['brosur'][] = <<<HTML
<section class="sayfa on" style="padding:0">
  <style>
  .on .bas{padding:16mm 16mm 0}
  .on .esel{margin:12mm 16mm 0;border-top:1.6mm solid var(--ink)}
  .on .s{display:grid;grid-template-columns:1fr auto;align-items:baseline;border-bottom:.35mm solid var(--line);padding:.6mm 0 1.4mm}
  .on .s b{font-weight:800;letter-spacing:.03em;line-height:1;white-space:nowrap}
  .on .s i{font-style:normal;font-family:"IBM Plex Mono",monospace;font-size:8pt;color:var(--ink-2)}
  .on .s1 b{font-size:58pt;background:linear-gradient(135deg,#d0334f,#8f1a2e);-webkit-background-clip:text;background-clip:text;color:transparent}
  .on .s2 b{font-size:40pt}.on .s3 b{font-size:28pt}.on .s4 b{font-size:19pt}.on .s4 i{color:var(--brand-ink)}
  .on .ozet{margin:7mm 16mm 0;font-size:12pt;line-height:1.45;color:var(--ink-2);max-width:150mm}
  .on .fayda{margin:9mm 16mm 0;display:grid;grid-template-columns:repeat(3,1fr);gap:5mm}
  .on .fayda div{border-top:1mm solid var(--brand);padding-top:3mm}
  .on .fayda h3{margin-bottom:1.5mm}
  .on .fayda p{font-size:9.5pt;color:var(--ink-2)}
  .on .bant{position:absolute;left:0;right:0;bottom:0;height:62mm;padding:10mm 16mm;display:grid;grid-template-columns:1fr auto;gap:10mm;align-items:center;
    background:radial-gradient(120mm 80mm at 100% 0%,rgba(208,51,79,.35),transparent 70%),var(--night);color:var(--on-night)}
  .on .bant h2{font-size:21pt;color:#fff}
  .on .bant h2 em{font-style:normal;color:var(--coral)}
  .on .bant p{margin-top:2.5mm;color:var(--on-night-muted);font-size:10pt}
  .on .bant .koken{margin-top:4mm;font-family:"IBM Plex Mono",monospace;font-size:8.5pt;color:var(--on-night-muted)}
  .on .bant .koken s{text-decoration-color:#ff3d5e;text-decoration-thickness:.5mm}
  .on .bant .koken b{color:var(--coral)}
  .on .bant .qrk svg{display:block;width:100%;height:100%}
  .on .ekran{margin:8mm 16mm 0;border-radius:3mm;overflow:hidden;border:.35mm solid var(--line);box-shadow:0 6mm 14mm -8mm rgba(27,20,22,.45)}
  .on .ekran .cubuk{display:flex;align-items:center;gap:1.4mm;padding:1.6mm 2.4mm;background:#f1ecec;border-bottom:.35mm solid var(--line)}
  .on .ekran .cubuk i{width:2mm;height:2mm;border-radius:50%;background:#d6cccd}
  .on .ekran .cubuk span{margin-left:2mm;font-family:"IBM Plex Mono",monospace;font-size:7pt;color:var(--muted)}
  .on .ekran img{display:block;width:100%;height:50mm;object-fit:cover;object-position:top left}
  </style>
  <div class="bas ust" style="margin:0 16mm;padding:16mm 0 5mm">{$logo('b1')}<span class="etiket">Gözlükçüler için yönetim sistemi</span></div>
  <div class="esel">
    <div class="s s1"><b>Reçete</b><i>0,1</i></div>
    <div class="s s2"><b>Medula'dan</b><i>0,4</i></div>
    <div class="s s3"><b>tek tuşla siparişe,</b><i>0,6</i></div>
    <div class="s s4"><b>sipariş atölyeye, gözlük müşteriye.</b><i>1,0</i></div>
  </div>
  <p class="ozet">Reçeteyi elle yazmazsınız. SGK katkı payı ve ay sonu faturası hazırdır. Atölye her siparişi panodan izler, müşteri gözlüğünün durumunu telefonundan görür.</p>
  <div class="fayda">
    <div><h3>Medula'dan tek tuş</h3><p>OptiFlow Pro, Medula'nın yanında açılır. "Aktar"a basarsınız; sferik, silendirik ve aks sıra hatası olmadan siparişe gelir.</p></div>
    <div><h3>Ay sonu tek SGK faturası</h3><p>Ayın reçeteleri tek faturada toplanır. Medula dökümünü yükleyin; işaretlemeyi unuttuğunuz reçete listelenir.</p></div>
    <div><h3>"Gözlüğüm hazır mı?" bitti</h3><p>Gözlük hazır olunca WhatsApp gider. Müşteri fişteki karekodla durumu ve garantisini telefonundan görür.</p></div>
  </div>
  <div class="ekran"><div class="cubuk"><i></i><i></i><i></i><span>optiflow.com.tr · Siparişler (örnek verili deneme mağazası)</span></div><img src="../../../assets/onizleme/liste.webp" alt="OptiFlow siparişler ekranı"></div>
  <div class="bant">
    <div>
      <h2>30 gün <em>ücretsiz</em> deneyin.</h2>
      <p>Kredi kartı gerekmez. Kurulumu sizinle birlikte yapıyoruz; eski müşteri listenizi biz aktarıyoruz.</p>
      <p class="koken">OptiFlow bir <s>yazılım ofisinde</s> değil, <b>bir optik atölyesinde</b> doğdu.</p>
    </div>
    <div style="text-align:center"><div class="qrk" style="background:#fff;padding:2.5mm;border-radius:3mm;width:40mm;height:40mm">{$qr($URL_BROSUR, 300)}</div><div class="mono" style="margin-top:2mm;font-size:8pt;color:var(--on-night-muted)">Okutun, deneyin</div></div>
  </div>
</section>
HTML;

/* ================= 1. BROŞÜR — ARKA ================= */
$lite = $fiyat['Lite'] ?? null; $pro = $fiyat['Pro'] ?? null;
$fiyatKart = function (string $ad, ?array $f, string $alt, array $maddeler, bool $vurgu) use ($e) {
    $tutar = $f ? pz_tl($f['aylik']) . '<small> / ay · KDV dahil</small>' : 'Bize yazın';
    $yil = $f && $f['yillik'] ? '<p class="yil">Yıllık ' . pz_tl($f['yillik']) . ' · aylık ' . pz_tl($f['yillik_aylik']) . ' karşılığı · %' . $f['indirim'] . ' indirim</p>' : '';
    $li = implode('', array_map(fn ($m) => '<li>' . $e($m) . '</li>', $maddeler));
    return '<div class="fk' . ($vurgu ? ' vurgu' : '') . '"><div class="fk-ust"><h3>OptiFlow ' . $e($ad) . '</h3><span>' . $e($alt) . '</span></div><div class="tutar">' . $tutar . '</div>' . $yil . '<ul class="tik">' . $li . '</ul></div>';
};
$kartLite = $fiyatKart('Lite', $lite, 'Tarayıcıda, her cihazda', ['Sipariş, müşteri, reçete geçmişi, atölye panosu', 'SGK katkı payı ve ay sonu SGK faturası', 'WhatsApp bildirimleri, "Siparişim nerede?"', 'Kasa, bakiye, kâr raporu, stok ve etiket', 'Garanti kartı, hızlı satış, teklif'], false);
$kartPro = $fiyatKart('Pro', $pro, "Windows'ta, Medula'nın yanında", ["Lite'taki her şey", 'Medula reçetesini tek tuşla siparişe aktarma', 'Medula ekranından tek tuşla SGK hak kontrolü', 'ÜTS karekod etiketi, USB barkod okuyucu', 'İnternet kesilince açık siparişleri görme'], true);
$sayfalar['brosur'][] = <<<HTML
<section class="sayfa arka">
  <style>
  .arka .fiyatlar{display:grid;grid-template-columns:1fr 1fr;gap:5mm;margin-top:5mm}
  .arka .fk{border:.35mm solid var(--line);border-radius:4mm;padding:4mm 5mm}
  .arka .fk ul.tik{font-size:9.5pt;gap:1.2mm}
  .arka .fk.vurgu{background:var(--night);color:var(--on-night);border-color:var(--night)}
  .arka .fk-ust{display:flex;justify-content:space-between;align-items:baseline;gap:3mm}
  .arka .fk-ust span{font-size:8.5pt;color:var(--muted)}
  .arka .fk.vurgu .fk-ust span,.arka .fk.vurgu .yil{color:var(--on-night-muted)}
  .arka .tutar{margin:3mm 0 1mm;font-size:24pt;font-weight:800;letter-spacing:-.02em}
  .arka .tutar small{font-size:9pt;font-weight:600;color:var(--muted)}
  .arka .fk.vurgu .tutar small{color:var(--on-night-muted)}
  .arka .yil{font-size:8.5pt;color:var(--ink-2);margin-bottom:3.5mm}
  .arka .fk.vurgu ul.tik li::before{border-color:var(--coral)}
  .arka .dahil{display:flex;flex-wrap:wrap;gap:2mm 6mm;margin-top:4mm;font-size:9pt;font-weight:700;color:var(--ink-2)}
  .arka .dahil span::before{content:"✓ ";color:var(--brand)}
  .arka .adimlar{display:grid;grid-template-columns:repeat(3,1fr);gap:5mm;margin-top:3.5mm;counter-reset:a}
  .arka .adimlar div{counter-increment:a;padding-top:9mm;position:relative}
  .arka .adimlar div::before{content:counter(a);position:absolute;top:0;left:0;width:7mm;height:7mm;border-radius:50%;background:var(--brand-bright);color:#fff;font-weight:800;font-size:10pt;display:grid;place-items:center}
  .arka .adimlar p{font-size:9.5pt;color:var(--ink-2)}
  .arka .guven{display:grid;grid-template-columns:repeat(4,1fr);gap:2.5mm 4mm;margin-top:3mm;font-size:8.5pt;line-height:1.4}
  .arka .guven b{display:block}
  .arka .guven span{color:var(--ink-2)}
  .arka .iletisim{margin-top:5mm;display:grid;grid-template-columns:auto 1fr;gap:7mm;align-items:center;padding:4.5mm 6mm;border-radius:5mm;background:var(--brand-soft)}
  .arka .iletisim .qr svg{display:block;width:26mm;height:26mm}
  .arka .iletisim dl{margin:0;display:grid;grid-template-columns:auto 1fr;gap:1.5mm 5mm;font-size:10.5pt}
  .arka .iletisim dt{font-family:"IBM Plex Mono",monospace;font-size:8pt;letter-spacing:.12em;text-transform:uppercase;color:var(--brand-ink);padding-top:.8mm}
  .arka .iletisim dd{margin:0;font-weight:800}
  </style>
  <div class="ust">{$logo('b2')}<span class="etiket">Sade fiyat, sürpriz yok</span></div>
  <h2>İki sürüm, tek sistem.</h2>
  <p style="margin-top:2mm;color:var(--ink-2)">Aynı mağaza, aynı veriler. Lite tarayıcıda her cihazda açılır; Pro, Windows'ta Medula'yı yanına alır. İlk 30 gün ikisi de ücretsiz.</p>
  <div class="fiyatlar">{$kartLite}{$kartPro}</div>
  <div class="dahil"><span>Kurulum ücreti yok</span><span>Kurulumu birlikte yapıyoruz</span><span>Eski müşteri listenizi biz aktarıyoruz</span><span>Sınırsız müşteri ve sipariş</span></div>

  <h2 style="margin-top:6mm;font-size:15pt">Nasıl başlarım?</h2>
  <div class="adimlar">
    <div><h3>Karekodu okutun</h3><p>optiflow.com.tr'de "30 gün ücretsiz deneyin". Mağaza adı ve e-posta yeterli.</p></div>
    <div><h3>Sizi arayalım</h3><p>Mağazanızı açıp ilk ayarları birlikte yapalım; Pro'yu Medula bilgisayarınıza kuralım.</p></div>
    <div><h3>Yarın sabah başlayın</h3><p>İlk reçeteyi aktarın, ilk WhatsApp'ı gönderin. Beğenirseniz devam edersiniz.</p></div>
  </div>

  <h2 style="margin-top:6mm;font-size:15pt">Reçete sağlık verisidir. Öyle korunur.</h2>
  <div class="guven">
    <div><b>Her mağazaya ayrı veritabanı</b><span>Bir mağazanın bilgisi başka mağazaya görünmez.</span></div>
    <div><b>SGK şifreniz sunucumuza gelmez</b><span>İsterseniz yalnızca kendi bilgisayarınızda saklanır.</span></div>
    <div><b>T.C. kimlik no saklanmaz</b><span>Müşteri sayfalarında soyad ve telefon gizlenir.</span></div>
    <div><b>Yedeğiniz elinizde</b><span>Veritabanınızın yedeğini tek tıkla indirirsiniz.</span></div>
  </div>

  <div class="iletisim">
    <div class="qr">{$qr($URL_BROSUR, 260)}</div>
    <dl>
      <dt>WhatsApp</dt><dd>{$e($tel)}</dd>
      <dt>Web</dt><dd>optiflow.com.tr</dd>
      <dt>Instagram</dt><dd>@{$e($p['instagram'])}</dd>
      <dt>Adres</dt><dd style="font-weight:600;font-size:9.5pt">{$e($p['sirket_unvani'])} · {$e($p['adres'])}</dd>
    </dl>
  </div>
</section>
HTML;

/* ================= 2. DEMO SENARYOSU (iç kullanım) ================= */
$demo = [
    ['0–2 dk', 'Önce dinleyin', ['"Ayda kaç sipariş, kaçı SGK\'lı?" "Şu an reçeteyi nasıl giriyorsunuz?" "Ay sonu faturayı kim, kaç saatte hazırlıyor?"', 'Cevapları not edin; demonun geri kalanını bu acıya bağlayın.'], 'Soru sormadan ekran açmayın.'],
    ['2–5 dk', "Medula'dan tek tuş (Pro)", ['Medula\'da örnek reçeteyi açın, OptiFlow Pro\'da "Aktar".', 'Sferik, silendirik, aks ve yakın için ADD\'in kendiliğinden geldiğini gösterin.', '"Bunu şu an elle kaç dakikada yazıyorsunuz?" diye sorun.'], 'Pro yoksa: sitedeki "Kendiniz deneyin" simülatörünü açın.'],
    ['5–8 dk', 'Sipariş → atölye → müşteri', ['Cam, çerçeve, SGK katkı payı ve kalan bakiye tek ekranda.', 'Atölye panosu: cam bekliyor → montaj → kontrol → hazır.', '"Hazır"da WhatsApp mesajı; fişteki karekodla müşterinin göreceği sayfayı telefonda açın.'], 'Müşterinin telefonundaki sayfa en çok etkileyen andır; mutlaka gösterin.'],
    ['8–11 dk', 'Ay sonu SGK faturası', ['SGK ay sonu ekranı: faturalanacak reçeteler ve tutar.', 'Medula dökümü karşılaştırması: "48 reçete Medula\'da, 47 OptiFlow\'da işaretli, fark 1".', 'Eksik reçetenin siparişiyle birlikte listelendiğini gösterin.'], 'Ay sonuna yakın günlerde bu bölümü uzatın.'],
    ['11–13 dk', 'Dükkânın geri kalanı', ['Kasa ve bakiye takibi, kâr raporu.', 'Karekodlu garanti kartı, hızlı satış, teklif.', 'Telefonda ve tablette aynı hesap.'], 'Müşterinin sorduğu modüle göre seçin; hepsini göstermeye çalışmayın.'],
    ['13–15 dk', 'Kapanış: orada başlatın', ['"30 gün ücretsiz, kredi kartı istemiyoruz. Şimdi mağazanızı açalım mı?"', 'kayit.php\'de mağazayı birlikte açın (2 dakika).', 'Eski müşteri listesini (Excel) almak için gün belirleyin; Pro kurulumu için randevu verin.'], 'Kayıt olmadan ayrılmayın; olmuyorsa demo tarihini telefona yazın.'],
];
$satirlar = '';
foreach ($demo as [$sure, $baslik, $maddeler, $ipucu]) {
    $satirlar .= '<tr><td class="sure">' . $e($sure) . '</td><td><h3>' . $e($baslik) . '</h3><ul class="tik">' . implode('', array_map(fn ($m) => '<li>' . $e($m) . '</li>', $maddeler)) . '</ul><p class="ipucu">' . $e($ipucu) . '</p></td></tr>';
}
$sayfalar['kit'][] = <<<HTML
<section class="sayfa demo">
  <style>
  .demo table td{vertical-align:top;padding:2.2mm 0;border-bottom:.35mm solid var(--line)}
  .demo td.sure{width:22mm;padding-top:4.2mm}
  .demo h3{margin-bottom:1.5mm}
  .demo ul.tik{font-size:9pt;gap:1mm}
  .demo h3{font-size:11pt}
  .demo .ipucu{margin-top:1.6mm;font-size:8.5pt;color:var(--brand-ink);font-weight:700}
  .demo .hazir{display:grid;grid-template-columns:1fr 1fr;gap:1mm 6mm;font-size:8.8pt;margin-top:2mm}
  .demo .hazir span::before{content:"☐ ";color:var(--brand)}
  </style>
  <div class="ust">{$logo('d1')}<span class="etiket">İç kullanım · demo senaryosu</span></div>
  <h2>15 dakikada OptiFlow demosu</h2>
  <p style="margin-top:2mm;color:var(--ink-2)">Amaç özellik saymak değil; dinlediğiniz acıyı ekranda çözmek ve kaydı orada açmak.</p>
  <table style="margin-top:2mm">{$satirlar}</table>
  <div class="kutu" style="margin-top:3mm;padding:3.5mm 5mm">
    <h3>Gitmeden önce</h3>
    <div class="hazir"><span>"Örnek Optik" deneme mağazası açık, giriş yapılmış</span><span>Dizüstünde OptiFlow Pro kurulu</span><span>Telefonda müşteri takip sayfası hazır</span><span>Yanınızda 5 broşür ve ziyaret formu</span><span>Medula örnek reçete ekranı (ya da simülatör)</span><span>WhatsApp: {$e($tel)} telefonunuzda açık</span></div>
  </div>
  <div class="alt"><span>OptiFlow saha satış kiti</span><span>Demo senaryosu</span></div>
</section>
HTML;

/* ================= 3. İTİRAZLAR ================= */
$itiraz = [
    ['"Medula şifremi mi istiyorsunuz?"', 'Hayır. Medula\'ya siz girersiniz. İsterseniz şifre yalnızca sizin bilgisayarınızda, Windows şifrelemesiyle saklanır; sunucumuza hiç gelmez. Reçete yalnızca siz "Aktar"a bastığınızda gelir.'],
    ['"Zaten bir programım var."', 'Mevcut programınız Medula reçetesini tek tuşla siparişe alıyor mu, ay sonu SGK faturasını Medula dökümüyle karşılaştırıyor mu? 30 gün yan yana deneyin; eski müşteri listenizi biz aktarırız.'],
    ['"Pahalı."', 'Lite aylık ' . ($lite ? pz_tl($lite['aylik']) : '—') . ', Pro ' . ($pro ? pz_tl($pro['aylik']) : '—') . ' (KDV dahil). Sitedeki kazanç hesabıyla birlikte bakalım: ayda 120 siparişte (%60\'ı SGK\'lı) reçete yazma, katkı payı hesabı, telefon ve ay sonu sayımından tahminen 10 saat kazanılır. Kendi rakamlarınızla birlikte hesaplayalım.'],
    ['"Bilgisayardan pek anlamam."', 'Kurulumu sizinle birlikte yapıyoruz. Mağaza adı, kullanıcılar ve ilk ayarlar için sizi arıyoruz; teknik bilgi gerekmez.'],
    ['"Eski müşterilerim ne olacak?"', 'Müşteri listenizi Excel ya da başka bir biçimde gönderin, biz aktaralım. İlk günden eski müşterilerinizle çalışırsınız.'],
    ['"Verilerim güvende mi?"', 'Her mağaza kendi ayrı veritabanında çalışır. T.C. kimlik numarası saklanmaz. Veritabanınızın yedeğini istediğiniz an tek tıkla indirirsiniz; veri sizindir.'],
    ['"İnternet giderse?"', 'Pro, açık siparişlerin şifreli bir kopyasını bilgisayarda tutar; internet kesilince de görürsünüz.'],
    ['"İki şubem var."', 'Şube yönetimi geliştiriliyor; şimdilik her şube ayrı mağaza hesabıyla çalışır. Ön kayıt yaptırırsanız hazır olunca ilk siz kullanırsınız.'],
    ['"Düşüneyim."', 'Tabii. Deneme 30 gün ve kart istemiyor; düşünürken kullanın. Şimdi mağazanızı açalım, karar vermek için ay sonunu bekleyin.'],
];
$satir = '';
foreach ($itiraz as [$s, $c]) { $satir .= '<tr><td class="s">' . $e($s) . '</td><td>' . $e($c) . '</td></tr>'; }
$sayfalar['kit'][] = <<<HTML
<section class="sayfa itiraz">
  <style>
  .itiraz td{vertical-align:top;padding:3mm 0;border-bottom:.35mm solid var(--line);font-size:9.8pt}
  .itiraz td.s{width:52mm;padding-right:5mm;font-weight:800;color:var(--brand-ink)}
  </style>
  <div class="ust">{$logo('i1')}<span class="etiket">İç kullanım · itirazlara cevaplar</span></div>
  <h2>Sık duyulan itirazlar ve cevapları</h2>
  <p style="margin-top:2mm;color:var(--ink-2)">Cevaplar sitede ve uygulamada yazan gerçek bilgilerdir. Bilmediğiniz bir soruya söz vermeyin: "Öğrenip size WhatsApp'tan yazayım" deyin.</p>
  <table style="margin-top:4mm">{$satir}</table>
  <div class="alt"><span>OptiFlow saha satış kiti</span><span>İtirazlara cevaplar</span></div>
</section>
HTML;

/* ================= 4. WHATSAPP ŞABLONLARI ================= */
$wa = [
    ['İlk mesaj (tanımadığınız optik)', 'Merhaba {Ad} Bey/Hanım, ben {Adınız}, Poyraz Optik\'ten. Kendi atölyemiz için Medula reçetesini tek tuşla siparişe alan ve ay sonu SGK faturasını hazırlayan bir program yaptık: OptiFlow. Dükkânınıza uğrayıp 15 dakika gösterebilir miyim? 30 gün ücretsiz, kart istemiyor. optiflow.com.tr'],
    ['Tanıdık üzerinden', 'Merhaba {Ad} Bey/Hanım, {Tanıdık} sizden bahsetti. Gözlükçüler için yaptığımız OptiFlow\'u size de göstermek isterim. Bu hafta hangi gün uygun?'],
    ['Ziyaretten sonra (aynı gün)', 'Bugün vakit ayırdığınız için teşekkürler {Ad} Bey/Hanım. Konuştuğumuz gibi mağazanızı açtım/açabiliriz; Excel müşteri listenizi buraya gönderirseniz aktarırız. Medula bilgisayarına Pro kurulumu için {gün} uygun mu?'],
    ['Deneme 3. gün', 'Merhaba {Ad} Bey/Hanım, OptiFlow\'da ilk siparişleri girebildiniz mi? Takıldığınız bir yer varsa 10 dakikalık görüntülü arama ile birlikte bakalım.'],
    ['Deneme 25. gün', '{Ad} Bey/Hanım, denemenizin bitmesine 5 gün kaldı. Bu ay OptiFlow\'da {N} sipariş işlediniz. Devam etmek isterseniz Lite aylık ' . ($lite ? pz_tl($lite['aylik']) : '') . ', Pro ' . ($pro ? pz_tl($pro['aylik']) : '') . ' (KDV dahil); yıllıkta %20 indirim var.'],
    ['Cam laboratuvarı / distribütör', 'Merhaba {Ad} Bey/Hanım, ben {Adınız}, Poyraz Optik\'ten. Optiklerin sipariş ve SGK işini kolaylaştıran OptiFlow\'u yaptık. Müşterilerinize tanıtmanız karşılığında bir iş birliği önermek istiyorum; kısa bir görüşme yapabilir miyiz?'],
];
$kartlar = '';
foreach ($wa as [$b, $m]) { $kartlar .= '<div class="wa"><h3>' . $e($b) . '</h3><p>' . $e($m) . '</p></div>'; }
$sayfalar['kit'][] = <<<HTML
<section class="sayfa wap">
  <style>
  .wap .liste{display:grid;grid-template-columns:1fr 1fr;gap:4.5mm;margin-top:5mm}
  .wap .wa{border-radius:0 4mm 4mm 4mm;background:#e7f6e9;padding:4.5mm 5mm;border:.35mm solid #cfe9d3}
  .wap .wa h3{font-size:10pt;margin-bottom:2mm;color:#075e54}
  .wap .wa p{font-size:9.4pt;line-height:1.5}
  .wap .kural{margin-top:6mm;font-size:9.4pt}
  </style>
  <div class="ust">{$logo('w1')}<span class="etiket">İç kullanım · WhatsApp şablonları</span></div>
  <h2>WhatsApp mesaj şablonları</h2>
  <p style="margin-top:2mm;color:var(--ink-2)">Süslü parantezleri doldurun. Mesajı kısa tutun, tek bir soru sorun, cevap gelmezse 3 gün sonra bir kez hatırlatın.</p>
  <div class="liste">{$kartlar}</div>
  <div class="kutu kural">
    <h3 style="margin-bottom:2mm">Kurallar</h3>
    <ul class="tik">
      <li>Toplu ve izinsiz mesaj atmayın; her mesaj kişiye özel olsun.</li>
      <li>Fiyatı ilk mesajda değil, demodan sonra konuşun.</li>
      <li>Her yeni temasın kaynağını ziyaret formuna yazın (broşür, tanıdık, Instagram, laboratuvar).</li>
    </ul>
  </div>
  <div class="alt"><span>OptiFlow saha satış kiti</span><span>WhatsApp şablonları</span></div>
</section>
HTML;

/* ================= 5. LABORATUVAR / DİSTRİBÜTÖR ================= */
$sayfalar['kit'][] = <<<HTML
<section class="sayfa lab">
  <style>
  .lab .neden{display:grid;grid-template-columns:repeat(3,1fr);gap:5mm;margin-top:5mm}
  .lab .neden div{border-top:1mm solid var(--brand);padding-top:3mm;font-size:9.5pt;color:var(--ink-2)}
  .lab .neden h3{color:var(--ink);margin-bottom:1.5mm}
  .lab .model{margin-top:6mm;display:grid;gap:3.5mm}
  .lab .model .kutu{display:grid;grid-template-columns:auto 1fr;gap:5mm;align-items:start}
  .lab .model .no{font-family:"IBM Plex Mono",monospace;font-size:16pt;color:var(--brand-ink);line-height:1}
  .lab .model p{font-size:9.5pt;color:var(--ink-2);margin-top:1mm}
  .lab .bos{display:inline-block;min-width:22mm;border-bottom:.35mm solid var(--ink);text-align:center}
  .lab .imza{position:absolute;left:16mm;right:16mm;bottom:20mm;display:grid;grid-template-columns:1fr 1fr auto;gap:10mm;align-items:end;font-size:9pt}
  .lab .imza div{border-top:.35mm solid var(--ink);padding-top:2mm}
  .lab .imza svg{display:block;width:26mm;height:26mm}
  </style>
  <div class="ust">{$logo('l1')}<span class="etiket">İş birliği teklifi</span></div>
  <h2>Cam laboratuvarları ve distribütörler için iş birliği</h2>
  <p style="margin-top:2.5mm;font-size:11pt;color:var(--ink-2);max-width:165mm">Her hafta onlarca optiğe uğruyor, sipariş alıyorsunuz. OptiFlow o optiklerin reçete, sipariş ve SGK işini hızlandırır. Tanıttığınız her mağaza sizin için ölçülebilir bir kazanca dönüşür.</p>
  <div class="neden">
    <div><h3>Müşterinize değer</h3>Medula'dan tek tuş reçete, ay sonu tek SGK faturası, WhatsApp ile "gözlüğünüz hazır". Optiğin işi hızlanır.</div>
    <div><h3>Size kazanç</h3>Tanıttığınız ve ödemeye geçen her mağaza için aşağıdaki modellerden biriyle kazanırsınız.</div>
    <div><h3>Takip edilebilir</h3>Size özel karekodla gelen ziyaretleri ayrı izler, sizin aracılığınızla gelen mağazaları birlikte doğrularız.</div>
  </div>
  <div class="model">
    <div class="kutu"><span class="no">A</span><div><h3>Komisyon</h3><p>Ödemeye geçen her mağazanın ilk yıl ödemelerinden %<span class="bos"></span> pay.</p></div></div>
    <div class="kutu"><span class="no">B</span><div><h3>Sabit tanıtım ödemesi</h3><p>Ödemeye geçen her mağaza için tek seferlik <span class="bos"></span> ₺.</p></div></div>
    <div class="kutu"><span class="no">C</span><div><h3>Ortak kampanya</h3><p>Sizin müşterilerinize özel: ilk <span class="bos"></span> ay indirimli OptiFlow; broşürler ve karekod bizden.</p></div></div>
  </div>
  <p style="margin-top:5mm;font-size:9pt;color:var(--muted)">Oranlar görüşmede birlikte belirlenir. Teslimat sırasında broşür bırakmanız yeterli; demo ve kurulumu biz yapıyoruz.</p>
  <div class="imza"><div>OptiFlow / {$e($p['sirket_unvani'])} · {$e($tel)}</div><div>İş ortağı (ad, firma, tarih)</div>{$qr($URL_LAB, 200)}</div>
  <div class="alt"><span>optiflow.com.tr</span><span>İş birliği teklifi</span></div>
</section>
HTML;

/* ================= 6. ZİYARET TAKİP FORMU ================= */
$bas = ['Tarih', 'Optik adı · ilçe', 'Yetkili · telefon', 'Kaynak', "Medula'yı kim, nasıl kullanıyor?", 'Şu anki program', 'İlgi 1–5', 'Sonraki adım · tarih'];
$genis = [14, 30, 30, 15, 30, 22, 11, 26];
$th = ''; foreach ($bas as $i => $b) { $th .= '<th style="width:' . $genis[$i] . 'mm">' . $e($b) . '</th>'; }
$tr = str_repeat('<tr>' . str_repeat('<td></td>', count($bas)) . '</tr>', 11);
$sayfalar['kit'][] = <<<HTML
<section class="sayfa form" style="width:297mm;height:210mm;padding:12mm 14mm">
  <style>
  .form table{margin-top:4mm;table-layout:fixed}
  .form th{font-family:"IBM Plex Mono",monospace;font-weight:500;font-size:7pt;letter-spacing:.06em;text-transform:uppercase;text-align:left;color:var(--brand-ink);padding:2mm 1.6mm;border-bottom:.6mm solid var(--ink)}
  .form td{height:11.6mm;border-bottom:.3mm solid var(--line);border-right:.3mm solid var(--line)}
  .form td:last-child{border-right:0}
  .form .not{margin-top:3mm;font-size:8pt;color:var(--muted)}
  </style>
  <div class="ust" style="margin-bottom:3mm">{$logo('f1')}<span class="etiket">Saha ziyaret takip formu</span></div>
  <table><tr>{$th}</tr>{$tr}</table>
  <p class="not">Kaynak: broşür · tanıdık · Instagram · laboratuvar · fuar · diğer. İlgi: 1 ilgisiz … 5 "mağazayı açalım". Her ziyaretten sonra aynı gün WhatsApp şablonu 3'ü gönderin.</p>
</section>
HTML;

/* ---------- yazdır ---------- */
$belge = fn (string $govde, string $baslik, string $ekCss = '') => "<!doctype html><html lang=\"tr\"><head><meta charset=\"utf-8\"><title>$baslik</title><style>$CSS\n$ekCss</style></head><body>$govde</body></html>";
file_put_contents("$D/render/brosur.html", $belge(implode("\n", $sayfalar['brosur']), 'OptiFlow broşür'));
// Ziyaret formu yatay: kit'te son sayfa; PDF'de ayrı dosya olarak da üretilir (sayfa yönü tek belgede karışmasın)
$form = array_pop($sayfalar['kit']);
file_put_contents("$D/render/kit.html", $belge(implode("\n", $sayfalar['kit']), 'OptiFlow satış kiti'));
file_put_contents("$D/render/ziyaret-formu.html", $belge($form, 'OptiFlow ziyaret formu', '@page{size:A4 landscape;margin:0}'));
echo "tamam: brosur (2 sayfa), kit (" . count($sayfalar['kit']) . " sayfa), ziyaret formu\n";
