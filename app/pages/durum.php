<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   Müşteri sipariş takip sayfası — oturum gerektirmez.
   Fişteki karekod ve "Siparişim nerede?" sayfası bu sayfayı açar. Anahtar 22
   karakter rastgeledir; sipariş numarasıyla tahmin edilemez. Sayfada fiyat,
   reçete ve iletişim bilgisi gösterilmez; yalnızca durum ve teslim bilgisi
   paylaşılır.

   Görünüm assets/musteri.css içindedir (atölye stilinden bağımsız).
   ========================================================================== */

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$anahtar = (string) ($_GET['k'] ?? '');
$order = null;

if ($anahtar !== '' && preg_match('/^[A-Za-z0-9]{10,24}$/', $anahtar)) {
    try {
        $order = row(
            'SELECT o.id, o.order_stage, o.transaction_type, o.promised_date, o.created_at,
                    o.updated_at, o.delivered_at, o.first_name, o.last_name
               FROM orders o WHERE o.public_token = ? LIMIT 1',
            [$anahtar]
        );
    } catch (Throwable $e) {
        $order = null;
    }
}

/* Ek ayrıntılar (atölye alt aşaması, kalite kontrol saati). Ayrı sorgu: bu
   sütunlar herhangi bir nedenle okunamazsa sayfa yine de açılır. */
$ek = [];
if ($order) {
    try {
        $ek = row('SELECT workshop_stage, qc_at FROM orders WHERE id = ?', [(int) $order['id']]) ?? [];
    } catch (Throwable $e) {
        $ek = [];
    }
}

/* ---------- Mağaza bilgileri ---------- */
$shop     = setting('shop_name', 'OptiFlow');
$adres    = setting('shop_address', '');
$telefon  = setting('shop_phone', '');
$telHam   = preg_replace('/\D+/', '', $telefon) ?? '';
$waNumara = $telHam !== '' ? (str_starts_with($telHam, '90') ? $telHam : '90' . ltrim($telHam, '0')) : '';
$telUrl     = $telHam !== '' ? 'tel:' . $telHam : '';
/* Yol tarifi: Ayarlar'da "Harita bağlantısı" girilmişse doğrudan o nokta açılır.
   Girilmemişse adres + işletme adı aranır (ana sitedeki harita ile aynı yöntem):
   işletme adı, adres metnindeki küçük hatalara rağmen doğru yeri buldurur. */
$haritaOzel = trim(setting('shop_map_url', ''));
if ($haritaOzel !== '' && preg_match('#^https://[^\s]+$#i', $haritaOzel)) {
    $haritaUrl = $haritaOzel;
} elseif ($adres !== '') {
    $haritaUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim($adres . ' ' . $shop));
} else {
    $haritaUrl = '';
}
$karoVar     = $telUrl !== '' || $waNumara !== '' || $haritaUrl !== '';

/* Çalışma saatleri (Ayarlar › Genel): her satır ayrı gösterilir; boşsa hiçbir şey çıkmaz */
$saatler = [];
foreach (explode("\n", str_replace("\r", '', setting('shop_hours', ''))) as $satir) {
    $satir = trim($satir);
    if ($satir !== '') {
        $saatler[] = $satir;
    }
}
$saatler = array_slice($saatler, 0, 7);
$iletisimVar = $karoVar || count($saatler) > 0;

/* ---------- Türkçe tarih yardımcısı: "Cuma, 26 Eylül" ---------- */
$aylar  = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
           7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
$gunler = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
$tarihUzun = static function (?string $deger, bool $gunAdi = true, bool $saat = false) use ($aylar, $gunler): string {
    if (!$deger || str_starts_with($deger, '0000')) {
        return '';
    }
    $ts = strtotime($deger);
    if (!$ts) {
        return '';
    }
    $s = (int) date('j', $ts) . ' ' . $aylar[(int) date('n', $ts)];
    if (date('Y', $ts) !== date('Y')) {
        $s .= ' ' . date('Y', $ts);
    }
    if ($gunAdi) {
        $s = $gunler[(int) date('N', $ts)] . ', ' . $s;
    }
    if ($saat) {
        $s .= ' · ' . date('H:i', $ts);
    }
    return $s;
};

/* ---------- Sipariş varsa: ekranda gösterilecek her şeyi hazırla ---------- */
$durum = $order ? public_stage((string) $order['order_stage']) : null;
$adim  = $durum ? (int) $durum['adim'] : 0;           // 1..4; 0 = iptal
$devam = $order && $adim >= 1 && $adim <= 2;          // hazırlık sürüyor
$hazir = $order && $adim === 3;
$bitti = $order && $adim === 4;
$iptal = $order && $adim === 0;

/* Google yorum bağlantısı (Ayarlar › Genel, isteğe bağlı): yalnızca teslimden sonra gösterilir */
$yorumUrl = trim(setting('shop_review_url', ''));
if ($yorumUrl !== '' && !preg_match('#^https://[^\s]+$#i', $yorumUrl)) {
    $yorumUrl = '';
}
$yorumGoster = $bitti && $yorumUrl !== '';

/* "Gözlüğünüzü kim yaptı?": yalnızca kendi izniyle görünmek isteyen personel + atölyenin notu.
   Modül yüklenemez ya da tablo yoksa bu bölüm hiç çıkmaz; sayfanın kalanı etkilenmez. */
$usta = ['kisiler' => [], 'not' => null, 'baslik' => ''];
if ($order && $adim >= 2) {
    try {
        require_once dirname(__DIR__) . '/maker.php';
        $usta = maker_for_order((int) $order['id'], $adim);
    } catch (Throwable $e) {
        $usta = ['kisiler' => [], 'not' => null, 'baslik' => ''];
    }
}
$ustaVar = count($usta['kisiler']) > 0 || $usta['not'] !== null;

/* Gözlük dayanışması kartı: yalnızca iki toplam sayı (kişisel bilgi yok). Modül yüklenemezse sayı gösterilmez. */
$bagis = ['cerceve' => 0, 'gozluk' => 0, 'yil' => null];
try {
    require_once dirname(__DIR__) . '/donation.php';
    $bagis = donation_public_stats();
} catch (Throwable $e) {
    $bagis = ['cerceve' => 0, 'gozluk' => 0, 'yil' => null];
}
$bagisSayi = $bagis['cerceve'] > 0 || $bagis['gozluk'] > 0;
$bagisWa   = $waNumara !== '' ? 'https://wa.me/' . $waNumara . '?text=' . rawurlencode('Merhaba, kullanmadığım gözlük çerçevesini bağışlamak istiyorum.') : '';
$bagisGoster = ($hazir || $bitti) && $bagisWa !== '';   // müşteri mağazaya geldiği/gözlüğünü aldığı zaman

/* "Çerçeveleri telefonunuzda deneyin" (sitedeki sanal deneme): uygulama yüklü değilse kart hiç çıkmaz */
try {
    require_once dirname(__DIR__) . '/deneme.php';
    $denemeUrl = deneme_url();
} catch (Throwable $e) {
    $denemeUrl = '';
}
$denemeGoster = $denemeUrl !== '' && $adim >= 1;

$tip       = $order ? (string) ($order['transaction_type'] ?? 'gozluk') : 'gozluk';
$tamir     = $tip === 'tamir';
$tipEtiket = transaction_type_label($tip);
$siparisNo = $order ? order_no((int) $order['id']) : '';
$ilkAd     = $order ? trim((string) $order['first_name']) : '';

/* Durum tonu: eski ton adları ana sitenin paletine eşlenir */
$tonHarita = ['blue' => 'gold', 'teal' => 'brand', 'green' => 'green', 'gray' => 'gray', 'red' => 'red'];
$ton    = $durum ? ($tonHarita[$durum['ton']] ?? 'brand') : 'brand';
$baslik = $durum ? (string) $durum['baslik'] : '';
$metin  = $durum ? (string) $durum['metin'] : '';
if ($tamir && $adim === 1) {
    $baslik = 'Tamir kaydınız alındı';
    $metin  = 'Gözlüğünüz tamir için kaydedildi, en kısa sürede ilgileneceğiz.';
} elseif ($tamir && $adim === 2) {
    $baslik = 'Gözlüğünüz onarımda';
    $metin  = 'Ustamız gözlüğünüzle ilgileniyor. İşlem bitince burada göreceksiniz.';
}

/* Büyük durum simgesi */
$svgAc   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">';
$ikonlar = [
    0 => '<circle cx="12" cy="12" r="9"/><path d="m15 9-6 6M9 9l6 6"/>',
    1 => '<rect x="5" y="5" width="14" height="16" rx="2.5"/><rect x="9" y="3" width="6" height="4" rx="1.2"/><path d="m8.8 14 2.3 2.3 4.2-4.6"/>',
    2 => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    3 => '<circle cx="6.5" cy="15" r="3.6"/><circle cx="17.5" cy="15" r="3.6"/><path d="M10.1 14.8c1.2-.9 2.6-.9 3.8 0"/><path d="M2.9 13.6 2 10.4M21.1 13.6l.9-3.2"/><path d="M12 3v3.4M10.3 4.7h3.4"/>',
    4 => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>',
];
$ikon = $svgAc . ($ikonlar[$adim] ?? $ikonlar[1]) . '</svg>';

/* Kalan gün: yalnızca hazırlık sürerken ve geçerli bir teslim tarihi varsa */
$vaadTarih = $order ? $tarihUzun((string) ($order['promised_date'] ?? '')) : '';
$kalanGun  = null;
if ($devam && $vaadTarih !== '') {
    $hedef = DateTimeImmutable::createFromFormat('Y-m-d', (string) $order['promised_date']);
    if ($hedef) {
        $bugun    = new DateTimeImmutable('today');
        $kalanGun = (int) $bugun->diff($hedef->setTime(0, 0))->format('%r%a');
    }
}

/* Teslim bilgisi paneli */
$teslimEtiket = '';
$teslimDeger  = '';
$teslimNot    = '';
$teslimIkon   = $svgAc . '<rect x="3.5" y="5" width="17" height="15.5" rx="2.5"/><path d="M8 3v4M16 3v4M3.5 10h17"/></svg>';
$kalanBuyuk   = '';
$kalanKucuk   = '';
$kalanSinif   = '';

if ($devam && $vaadTarih !== '') {
    $teslimEtiket = ($kalanGun !== null && $kalanGun < 0) ? 'Planlanan teslim günü' : 'Tahmini teslim günü';
    $teslimDeger  = $vaadTarih;
    if ($kalanGun !== null && $kalanGun > 1) {
        $kalanBuyuk = (string) $kalanGun;
        $kalanKucuk = 'gün kaldı';
    } elseif ($kalanGun === 1) {
        $kalanBuyuk = 'Yarın';
        $kalanKucuk = 'teslim';
        $kalanSinif = 'is-word';
    } elseif ($kalanGun === 0) {
        $kalanBuyuk = 'Bugün';
        $kalanKucuk = 'teslim';
        $kalanSinif = 'is-word';
    } elseif ($kalanGun !== null) {
        $kalanSinif = 'is-late';
        $teslimNot  = 'Planlanan günü biraz geçtik, bekletiğimiz için özür dileriz. En kısa sürede tamamlıyoruz; dilerseniz bizi arayabilirsiniz.';
    }
} elseif ($devam) {
    $teslimEtiket = 'Tahmini teslim günü';
    $teslimDeger  = 'Henüz belirlenmedi';
    $teslimNot    = 'Teslim günü netleştiğinde bu sayfada görünecek.';
} elseif ($hazir) {
    $teslimEtiket = 'Teslim alırken';
    $teslimDeger  = 'Fişinizi veya bu ekranı gösterin';
    $teslimIkon   = $svgAc . '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9.5 8h5M9.5 12h5"/></svg>';
} elseif ($bitti && $order['delivered_at']) {
    $teslimTarihi = $tarihUzun((string) $order['delivered_at']);
    if ($teslimTarihi !== '') {
        $teslimEtiket = 'Teslim tarihi';
        $teslimDeger  = $teslimTarihi;
    }
}

/* Hazır olunca ana eylem: yol tarifi (yoksa arama) */
$anaUrl   = $haritaUrl !== '' ? $haritaUrl : $telUrl;
$anaEtiket = $haritaUrl !== '' ? 'Yol tarifi al' : 'Mağazayı ara';

/* Adım çubuğu (küçük) ve ayrıntılı adım listesi */
$cubuk = [];
for ($i = 1; $i <= 4; $i++) {
    $cubuk[] = $i <= $adim ? 'on' : '';
}

$adimAdlari = $tamir
    ? [1 => 'Kayıt alındı', 2 => 'Onarımda', 3 => 'Hazır', 4 => 'Teslim edildi']
    : [1 => 'Sipariş alındı', 2 => 'Atölyede', 3 => 'Hazır', 4 => 'Teslim edildi'];

if ($tamir) {
    $atolyeAlt = 'Ustamız tamir işlemini yapıyor';
} elseif ((string) ($ek['workshop_stage'] ?? '') === 'kontrol') {
    $atolyeAlt = 'Son kalite kontrolü yapılıyor';
} else {
    $atolyeAlt = 'Gözlüğünüz hazırlanıyor';
}
$sipTarih   = $order ? $tarihUzun((string) $order['created_at'], false) : '';
$hazirTarih = ($adim >= 3 && !empty($ek['qc_at'])) ? $tarihUzun((string) $ek['qc_at'], false) : '';
$teslimGun  = ($bitti && $order['delivered_at']) ? $tarihUzun((string) $order['delivered_at'], false) : '';

$adimlar = [];
if ($order && $adim > 0) {
    for ($no = 1; $no <= 4; $no++) {
        $guncel = $no === $adim;
        if ($no < $adim || ($guncel && $adim >= 3)) {
            $sinif = 'is-done';
        } elseif ($guncel) {
            $sinif = 'is-now';
        } else {
            $sinif = 'is-todo';
        }
        if ($guncel && $adim >= 3) {
            $sinif .= ' is-last';
        }

        $tik   = $no < $adim || ($guncel && $adim >= 3);   // tamamlandı: tik simgesi
        $sayi  = !$tik && !$guncel;                       // henüz sırası gelmedi: adım numarası
        $alt = '';
        $etiket = '';
        if ($no === 1) {
            $alt = $guncel ? ($tamir ? 'Kaydınız alındı, sıra bekleniyor' : 'Camlarınız tedarik ediliyor') : $sipTarih;
        } elseif ($no === 2) {
            if ($guncel) {
                $alt = $atolyeAlt;
            } elseif ($no < $adim) {
                $alt = $tamir ? 'Onarım tamamlandı' : 'Gözlüğünüz hazırlandı';
            } else {
                $alt = $tamir ? 'Sıra gelince başlar' : 'Camlarınız gelince başlar';
            }
        } elseif ($no === 3) {
            if ($guncel) {
                $alt = 'Teslim alabilirsiniz' . ($hazirTarih !== '' ? ' · ' . $hazirTarih : '');
            } elseif ($no < $adim) {
                $alt = $hazirTarih !== '' ? $hazirTarih : 'Hazırlandı';
            } else {
                $alt = 'Hazır olunca burada görünür';
            }
        } else {
            $alt = $guncel ? ($teslimGun !== '' ? $teslimGun : 'Sağlıkla kullanın') : 'Mağazamızdan teslim alırsınız';
        }
        if ($sinif === 'is-now') {
            $etiket = 'Şu an';
        }

        $adimlar[] = ['no' => (string) $no, 'sinif' => $sinif, 'tik' => $tik, 'sayi' => $sayi, 'baslik' => $adimAdlari[$no], 'alt' => $alt, 'etiket' => $etiket];
    }
}

/* Sipariş bilgileri */
$sonGuncelleme = $order ? $tarihUzun((string) ($order['updated_at'] ?: $order['created_at']), false, true) : '';
$bilgiler = [];
if ($order) {
    $bilgiler[] = ['etiket' => 'Sipariş numarası', 'deger' => $siparisNo, 'kopyala' => true];
    $bilgiler[] = ['etiket' => 'İşlem', 'deger' => $tipEtiket, 'kopyala' => false];
    $bilgiler[] = ['etiket' => 'Sipariş tarihi', 'deger' => $sipTarih !== '' ? $sipTarih : '—', 'kopyala' => false];
    $bilgiler[] = ['etiket' => 'Son güncelleme', 'deger' => $sonGuncelleme !== '' ? $sonGuncelleme : '—', 'kopyala' => false];
}

/* İletişim kartı metni */
if ($bitti || $iptal) {
    $iletisimBaslik = 'Bize ulaşın';
    $iletisimMetin  = $iptal ? 'Ayrıntı için bizi arayabilirsiniz.' : 'Gözlüğünüzle ilgili her konuda yardımcı olmaktan memnuniyet duyarız.';
} else {
    $iletisimBaslik = 'Bir sorunuz mu var?';
    $iletisimMetin  = 'Sipariş numaranızı (' . $siparisNo . ') söylemeniz yeterli.';
}
$waUrl = $waNumara !== ''
    ? 'https://wa.me/' . $waNumara . '?text=' . rawurlencode('Merhaba, ' . $siparisNo . ' numaralı siparişim hakkında bilgi almak istiyorum.')
    : '';

$yenile        = $devam;   // hazır değilse sayfa kendini tazeler
$sayfaBasligi  = $order ? 'Sipariş durumu · ' . $shop : 'Sipariş bulunamadı · ' . $shop;
$tonSinifi     = 'tk-tone-' . $ton;
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#faf6ef" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#150f0d" media="(prefers-color-scheme: dark)">
<title><?= e($sayfaBasligi) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="stylesheet" href="<?= e(asset('musteri.css')) ?>">
<?= brand_style_tag() ?>
</head>
<body class="tk-page"<?= $yenile ? ' data-yenile="1"' : '' ?>>

<main class="tk <?= e($tonSinifi) ?>">
  <header class="tk-head">
    <span class="tk-mark" aria-hidden="true">
      <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round"><path d="M12 36c0-6.5 4.5-11 10-11s10 4.5 10 11-4.5 10-10 10-10-4.5-10-10Zm20 0c0-6.5 4.5-11 10-11s10 4.5 10 11-4.5 10-10 10-10-4.5-10-10Zm-10-3h10M12 31l-5-2m45 2 5-2"/></svg>
    </span>
    <div class="tk-brand">
      <b><?= e($shop) ?></b>
      <small>Sipariş takibi</small>
    </div>
    <?php if ($telUrl !== ''): ?>
      <a class="tk-call" href="<?= e($telUrl) ?>" aria-label="Mağazayı ara">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </a>
    <?php endif; ?>
  </header>

<?php if (!$order): ?>

  <section class="tk-card tk-center tk-tone-gray">
    <span class="tk-medal" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.5" cy="15" r="3.6"/><circle cx="17.5" cy="15" r="3.6"/><path d="M10.1 14.8c1.2-.9 2.6-.9 3.8 0"/><path d="M2.9 13.6 2 10.4M21.1 13.6l.9-3.2"/><path d="M11 4.2c.2-1 1.7-1.4 2.4-.7.8.8.2 1.5-.5 2-.5.4-.9.7-.9 1.4M12 9.6v.01"/></svg>
    </span>
    <h1>Sipariş bulunamadı</h1>
    <p>Bu bağlantı geçersiz ya da süresi dolmuş olabilir. Sipariş numaranızla ve telefonunuzun son 4 haneyle siparişinizi kolayca bulabilirsiniz.</p>
    <div class="tk-stack">
      <a class="tk-btn" href="<?= e(musteri_link('siparisim-nerede.php')) ?>">Siparişimi bul</a>
      <?php if ($telUrl !== ''): ?>
        <a class="tk-btn is-ghost" href="<?= e($telUrl) ?>">Mağazayı ara</a>
      <?php endif; ?>
    </div>
  </section>

<?php else: ?>

  <section class="tk-card tk-hero" aria-labelledby="durum-baslik">
    <svg class="tk-hero-art" viewBox="0 0 220 120" fill="none" stroke="currentColor" stroke-linecap="round" aria-hidden="true">
      <circle cx="78" cy="62" r="34" stroke-width="3"/><circle cx="146" cy="62" r="34" stroke-width="3"/>
      <path d="M112 56q10-9 20 0" stroke-width="3"/><path d="M44 56 14 40" stroke-width="2.4"/><path d="M180 56l30-16" stroke-width="2.4"/>
      <circle cx="78" cy="62" r="24" stroke-width="1" opacity=".5"/><circle cx="146" cy="62" r="24" stroke-width="1" opacity=".5"/>
    </svg>

    <?php if ($hazir): ?>
      <span class="tk-spark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c.7 6.3 5.7 11.3 12 12-6.3.7-11.3 5.7-12 12-.7-6.3-5.7-11.3-12-12C6.3 11.3 11.3 6.3 12 0Z"/></svg>
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c.7 6.3 5.7 11.3 12 12-6.3.7-11.3 5.7-12 12-.7-6.3-5.7-11.3-12-12C6.3 11.3 11.3 6.3 12 0Z"/></svg>
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0c.7 6.3 5.7 11.3 12 12-6.3.7-11.3 5.7-12 12-.7-6.3-5.7-11.3-12-12C6.3 11.3 11.3 6.3 12 0Z"/></svg>
      </span>
    <?php endif; ?>

    <div class="tk-hero-top">
      <span class="tk-medal" aria-hidden="true"><?= $ikon ?></span>
      <span class="tk-pill"><span><?= e($tipEtiket) ?></span><b><?= e($siparisNo) ?></b></span>
    </div>

    <?php if ($ilkAd !== ''): ?>
      <span class="tk-hi">Merhaba <?= e($ilkAd) ?>,</span>
    <?php endif; ?>
    <h1 id="durum-baslik"><?= e($baslik) ?></h1>
    <p class="tk-lead"><?= e($metin) ?></p>

    <?php if (!$iptal): ?>
      <span class="tk-step-of">
        <i aria-hidden="true"><?php foreach ($cubuk as $c): ?><span class="<?= e($c) ?>"></span><?php endforeach; ?></i>
        Adım <?= e($adim) ?> / 4
      </span>
    <?php endif; ?>

    <?php if ($teslimEtiket !== ''): ?>
      <div class="tk-when">
        <div class="tk-when-row">
          <?php if ($kalanBuyuk !== ''): ?>
            <span class="tk-left <?= e($kalanSinif) ?>"><b><?= e($kalanBuyuk) ?></b><em><?= e($kalanKucuk) ?></em></span>
          <?php else: ?>
            <span class="tk-when-ic <?= e($kalanSinif) ?>" aria-hidden="true"><?= $teslimIkon ?></span>
          <?php endif; ?>
          <div class="tk-when-txt">
            <small><?= e($teslimEtiket) ?></small>
            <b><?= e($teslimDeger) ?></b>
          </div>
        </div>
        <?php if ($teslimNot !== ''): ?>
          <p class="tk-when-note"><?= e($teslimNot) ?></p>
        <?php endif; ?>
        <?php if ($hazir && $anaUrl !== ''): ?>
          <a class="tk-btn" href="<?= e($anaUrl) ?>"<?php if ($haritaUrl !== ''): ?> target="_blank" rel="noopener"<?php endif; ?>>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <?= e($anaEtiket) ?>
          </a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($ustaVar): ?>
    <section class="tk-card tk-maker" aria-label="Gözlüğünüzü hazırlayanlar">
      <div class="tk-eyebrow">Atölyemizden</div>
      <h2><?= e($usta['baslik']) ?></h2>
      <?php if ($usta['kisiler']): ?>
        <ul class="tk-maker-list">
          <?php foreach ($usta['kisiler'] as $k): ?>
            <li>
              <span class="tk-avatar" aria-hidden="true"><?= e($k['harf']) ?></span>
              <span class="tk-maker-txt">
                <b><?= e($k['ad']) ?><?php if ($k['unvan'] !== ''): ?> <small><?= e($k['unvan']) ?></small><?php endif; ?></b>
                <?php if ($k['bio'] !== ''): ?><span><?= e($k['bio']) ?></span><?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <?php if ($usta['not'] !== null): ?>
        <blockquote class="tk-note"><p><?= e($usta['not']['metin']) ?></p><footer>— <?= e($usta['not']['ad']) ?></footer></blockquote>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php if ($yorumGoster): ?>
    <section class="tk-card tk-review">
      <div class="tk-stars" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z"/></svg>
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z"/></svg>
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z"/></svg>
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z"/></svg>
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5l2.9 6.1 6.6.8-4.9 4.6 1.3 6.6L12 17.3l-5.9 3.3 1.3-6.6L2.5 9.4l6.6-.8z"/></svg>
      </div>
      <h2>Deneyiminizi paylaşır mısınız?</h2>
      <p>Bizi tercih ettiğiniz için teşekkür ederiz. Görüşünüz bize ve yeni müşterilerimize yardımcı olur.</p>
      <a class="tk-btn" href="<?= e($yorumUrl) ?>" target="_blank" rel="noopener">Google'da değerlendirin</a>
    </section>
  <?php endif; ?>

  <?php if ($adimlar): ?>
    <section class="tk-card tk-steps-card" aria-label="Sipariş adımları">
      <div class="tk-eyebrow">Siparişinizin yolculuğu</div>
      <ol class="tk-steps">
        <?php foreach ($adimlar as $a): ?>
          <li class="<?= e($a['sinif']) ?>">
            <span class="tk-dot" aria-hidden="true">
              <?php if ($a['tik']): ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
              <?php elseif ($a['sayi']): ?>
                <?= e($a['no']) ?>
              <?php endif; ?>
            </span>
            <span class="tk-step">
              <b><?= e($a['baslik']) ?><?php if ($a['etiket'] !== ''): ?><em class="tk-tag"><?= e($a['etiket']) ?></em><?php endif; ?></b>
              <?php if ($a['alt'] !== ''): ?><span><?= e($a['alt']) ?></span><?php endif; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>

  <?php if ($denemeGoster): ?>
    <section class="tk-card tk-try">
      <div class="tk-eyebrow">Yeni</div>
      <h2>Çerçeveleri yüzünüzde deneyin</h2>
      <p>Telefonunuzun kamerasıyla farklı çerçeve stillerini deneyin. Görüntü telefonunuzdan çıkmaz.</p>
      <a class="tk-btn" href="<?= e($denemeUrl) ?>" target="_blank" rel="noopener">Denemeye başla</a>
    </section>
  <?php endif; ?>

  <?php if ($bagisGoster): ?>
    <section class="tk-card tk-give">
      <div class="tk-eyebrow">Gözlük dayanışması</div>
      <h2>Eski gözlüğünüz başkasının ışığı olsun</h2>
      <p>Kullanmadığınız gözlük çerçevelerini toplar, temizler ve gözlüğe ulaşmakta zorlanan insanlarla buluştururuz; camlarını biz karşılarız.</p>
      <?php if ($bagisSayi): ?><p class="tk-give-count"><b><?= e($bagis['cerceve']) ?></b> çerçeve bağışlandı · <b><?= e($bagis['gozluk']) ?></b> gözlük sahibini buldu</p><?php endif; ?>
      <a class="tk-btn is-ghost" href="<?= e($bagisWa) ?>" target="_blank" rel="noopener">Bağış yapmak istiyorum</a>
    </section>
  <?php endif; ?>

  <?php if ($iletisimVar): ?>
    <section class="tk-card tk-contact">
      <h2><?= e($iletisimBaslik) ?></h2>
      <p><?= e($iletisimMetin) ?></p>
      <?php if ($karoVar): ?>
      <div class="tk-tiles">
        <?php if ($telUrl !== ''): ?>
          <a class="tk-tile is-call" href="<?= e($telUrl) ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            Ara
          </a>
        <?php endif; ?>
        <?php if ($waUrl !== ''): ?>
          <a class="tk-tile is-wa" href="<?= e($waUrl) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.9 9.9 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2Zm5.8 14.13c-.25.7-1.24 1.28-2.02 1.45-.55.11-1.26.2-3.66-.78-2.97-1.23-4.88-4.24-5.03-4.44-.15-.2-1.2-1.6-1.2-3.05 0-1.45.75-2.16 1.02-2.46.27-.3.58-.37.78-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.25.6.85 2.07.92 2.22.07.15.12.33.02.53-.1.2-.15.33-.3.5-.15.18-.31.4-.45.54-.15.15-.3.31-.13.61.17.3.76 1.25 1.63 2.03 1.12 1 2.06 1.31 2.36 1.46.3.15.48.13.65-.08.18-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.28.1 1.76.83 2.06.98.3.15.5.22.57.35.08.13.08.73-.17 1.43Z"/></svg>
            WhatsApp
          </a>
        <?php endif; ?>
        <?php if ($haritaUrl !== ''): ?>
          <a class="tk-tile is-map" href="<?= e($haritaUrl) ?>" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Yol tarifi
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <?php if ($saatler): ?>
        <div class="tk-hours">
          <b>Çalışma saatleri</b>
          <?php foreach ($saatler as $sa): ?><span><?= e($sa) ?></span><?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <section class="tk-card tk-info" aria-label="Sipariş bilgileri">
    <div class="tk-eyebrow">Sipariş bilgileri</div>
    <dl class="tk-kv">
      <?php foreach ($bilgiler as $b): ?>
        <div>
          <dt><?= e($b['etiket']) ?></dt>
          <dd>
            <?= e($b['deger']) ?>
            <?php if ($b['kopyala']): ?>
              <button type="button" class="tk-copy" data-kopyala="<?= e($b['deger']) ?>" aria-label="Sipariş numarasını kopyala">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2.5"/><path d="M5 15V6.5A2.5 2.5 0 0 1 7.5 4H15"/></svg>
                <span>Kopyala</span>
              </button>
            <?php endif; ?>
          </dd>
        </div>
      <?php endforeach; ?>
    </dl>
  </section>

<?php endif; ?>

  <footer class="tk-foot">
    <b><?= e($shop) ?></b>
    <?php if ($adres !== ''): ?><span><?= e($adres) ?></span><?php endif; ?>
    <?php if ($telefon !== ''): ?><a href="<?= e($telUrl) ?>"><?= e($telefon) ?></a><?php endif; ?>
    <small>Bu sayfa yalnızca siparişinizin durumunu gösterir; fiyat ve kişisel bilgi içermez.</small>
  </footer>
</main>

<script src="<?= e(asset('durum.js')) ?>" defer></script>
</body>
</html>
