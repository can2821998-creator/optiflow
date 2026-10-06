<?php
declare(strict_types=1);

/* ==========================================================================
   4.22.0 — Ortak döküm (yazdırılan belge) yapı taşları.
   Tüm belgeler 4.21.1 teklif dökümünün dilini konuşur: koyu-bordo üst bant, "Sayın …" karşılama,
   ikonlu bilgi şeridi, başlıklı kutular, imza ve alt bilgi. Stil: assets/dokum.css (A4, renkler
   print-color-adjust: exact ile tarayıcının "arka planları yazdır" ayarından bağımsız basılır).

   Kural: yardımcılar düz metni KENDİSİ kaçırır (e()); adı *_html olan parametreler hazır HTML'dir.
   ========================================================================== */

function dokum_ikon(string $ad): string
{
    static $y = [
        'cerceve' => '<circle cx="6.5" cy="14" r="3.5"/><circle cx="17.5" cy="14" r="3.5"/><path d="M10 14c.7-.8 3.3-.8 4 0"/><path d="M3 14l1.5-6h2M21 14l-1.5-6h-2"/>',
        'goz'     => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'sgk'     => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'yuzde'   => '<path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/>',
        'tel'     => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
        'konum'   => '<path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'kisi'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'takvim'  => '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'cuzdan'  => '<path d="M19 7V5.5A1.5 1.5 0 0 0 17.5 4h-12A2.5 2.5 0 0 0 3 6.5v11A2.5 2.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V16"/><path d="M21 9h-5a3 3 0 0 0 0 6h5z"/><circle cx="16" cy="12" r=".6" fill="currentColor"/>',
        'kart'    => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/>',
        'kutu'    => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
        'belge'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'saat'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'onay'    => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
        'carpi'   => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
        'ayar'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
        'kamyon'  => '<path d="M2 6h11v10H2zM13 9h4l4 4v3h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'grafik'  => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'etiket'  => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'kasa'    => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="12" cy="12" r="3.5"/><path d="M12 8.5V7M12 17v-1.5M7 20v1.5M17 20v1.5"/>',
        'yildiz'  => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
        'anahtar' => '<path d="M14.7 6.3a4 4 0 1 0-5.4 5.4L3 18v3h3l1-1v-2h2l1-1v-2h2l1.3-1.3a4 4 0 0 0 1.4-7.4z"/>',
        'eksi'    => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'arti'    => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
        'para'    => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v5M18 9.5v5"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($y[$ad] ?? $y['belge']) . '</svg>';
}

/** Belge numarası: #00012 */
function dokum_no(int $id, string $onek = '#'): string
{
    return $onek . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/**
 * Üst bant: solda mağaza (logo, ad, adres, telefon), sağda belge etiketi ve künye.
 * $meta: [[başlık, değer], …] (en çok 3–4). $marka/$satirlar: SGK dökümü gibi firma unvanıyla basılan belgeler için.
 */
function dokum_ust(string $etiket, array $meta, ?string $marka = null, ?array $satirlar = null): string
{
    $marka ??= setting('shop_name', 'OptiFlow');
    if ($satirlar === null) {
        $satirlar = [];
        if (setting('shop_address', '') !== '') { $satirlar[] = ['konum', setting('shop_address', '')]; }
        if (setting('shop_phone', '') !== '') { $satirlar[] = ['tel', setting('shop_phone', '')]; }
    }
    $h = '<header class="ust"><div class="marka"><span class="logo">' . brand_mark() . '</span><div><b>' . e($marka) . '</b>';
    foreach ($satirlar as [$ikon, $metin]) {
        if (trim((string) $metin) !== '') {
            $h .= '<small>' . dokum_ikon((string) $ikon) . e((string) $metin) . '</small>';
        }
    }
    $h .= '</div></div><div class="belge"><span class="etiket">' . e($etiket) . '</span><dl style="--n:' . max(1, count($meta)) . '">';
    foreach ($meta as [$dt, $dd]) {
        $h .= '<div><dt>' . e((string) $dt) . '</dt><dd>' . e((string) $dd) . '</dd></div>';
    }
    return $h . '</dl></div></header>';
}

/** Durum rozeti (karşılamada başlığın altında): $ton = '' (bordo) | ok | uyari | gri | bilgi */
function dokum_rozet(string $ton, string $ikon, string $metin): string
{
    return '<span class="rozet' . ($ton !== '' ? ' ' . e($ton) : '') . '">' . dokum_ikon($ikon) . e($metin) . '</span>';
}

/**
 * Karşılama satırı: küçük üst yazı, büyük başlık (kişi / kurum / tarih), açıklama; sağda öne çıkan tutar.
 * $metin_html ve $sag_html hazır HTML'dir (çağıran kaçırır).
 */
function dokum_selam(string $kucuk, string $baslik, string $metin_html = '', string $sag_html = '', string $rozet_html = ''): string
{
    return '<section class="selam"><div>' . ($kucuk !== '' ? '<span class="kucuk">' . e($kucuk) . '</span>' : '')
        . '<h1>' . e($baslik) . '</h1>' . $rozet_html
        . ($metin_html !== '' ? '<p>' . $metin_html . '</p>' : '') . '</div>' . $sag_html . '</section>';
}

/** Öne çıkan tutar kutusu: $ton = '' (bordo degrade) | ok | acik | koyu */
function dokum_vurgu(string $etiket, string $deger, string $alt = '', string $ton = ''): string
{
    return '<div class="vurgu' . ($ton !== '' ? ' ' . e($ton) : '') . '"><small>' . e($etiket) . '</small><b>' . e($deger) . '</b>'
        . ($alt !== '' ? '<em>' . e($alt) . '</em>' : '') . '</div>';
}

/** Durum bandı (tam genişlik): $ton = '' | ok | uyari | gri */
function dokum_durum(string $ton, string $ikon, string $baslik, string $metin = ''): string
{
    return '<section class="durum' . ($ton !== '' ? ' ' . e($ton) : '') . '"><span class="ic">' . dokum_ikon($ikon) . '</span><div><b>'
        . e($baslik) . '</b>' . ($metin !== '' ? '<span>' . e($metin) . '</span>' : '') . '</div></section>';
}

/**
 * İkonlu bilgi şeridi. $hucreler: [[ikon, başlık, değer, alt-not?], …]; boş değer "—" basılır.
 * $genisIlk: ilk hücre daha geniş (uzun metin: çerçeve, ürün).
 */
function dokum_bilgi(array $hucreler, bool $genisIlk = false): string
{
    $hucreler = array_values(array_filter($hucreler));
    $h = '<section class="bilgi n' . count($hucreler) . ($genisIlk ? ' genis-ilk' : '') . '">';
    foreach ($hucreler as $c) {
        $deger = trim((string) ($c[2] ?? ''));
        $h .= '<div><span class="ic">' . dokum_ikon((string) $c[0]) . '</span><small>' . e((string) $c[1]) . '</small><b>' . e($deger !== '' ? $deger : '—') . '</b>'
            . (($c[3] ?? '') !== '' ? '<em>' . e((string) $c[3]) . '</em>' : '') . '</div>';
    }
    return $h . '</section>';
}

/** Başlıklı kutu. $ic_html hazır HTML; $sag: başlık satırının sağındaki küçük not. */
function dokum_kutu(string $baslik, string $ic_html, string $sinif = '', string $sag = ''): string
{
    return '<section class="kutu' . ($sinif !== '' ? ' ' . e($sinif) : '') . '"><h3>' . e($baslik)
        . ($sag !== '' ? '<small>' . e($sag) . '</small>' : '') . '</h3>' . $ic_html . '</section>';
}

/** Anahtar–değer listesi (kutu içinde). $satirlar: [[başlık, değer], …] */
function dokum_liste(array $satirlar): string
{
    $h = '<dl class="liste">';
    foreach ($satirlar as [$dt, $dd]) {
        $dd = trim((string) $dd);
        $h .= '<div><dt>' . e((string) $dt) . '</dt><dd>' . e($dd !== '' ? $dd : '—') . '</dd></div>';
    }
    return $h . '</dl>';
}

/** İmza kutuları. $kutular: [[rol, ad (boşsa çizgi), açıklama], …] */
function dokum_imzalar(array $kutular): string
{
    $h = '<section class="imzalar n' . count($kutular) . '">';
    foreach ($kutular as [$rol, $ad, $aciklama]) {
        $ad = trim((string) $ad);
        $h .= '<div class="ik"><small>' . e((string) $rol) . '</small><b' . ($ad === '' ? ' class="bos"' : '') . '>' . e($ad !== '' ? $ad : 'Ad soyad') . '</b>'
            . '<p>' . e((string) $aciklama) . '</p><span class="cizgi"></span></div>';
    }
    return $h . '</section>';
}

/** Karekodlu bilgi kartları (takip, bakım, çerçeve dene…). $kartlar: [[adres, başlık, açıklama], …] */
function dokum_kareler(array $kartlar): string
{
    $kartlar = array_values(array_filter($kartlar, static fn($k) => is_array($k) && ($k[0] ?? '') !== ''));
    if (!$kartlar) {
        return '';
    }
    $h = '<section class="kareler n' . count($kartlar) . '">';
    foreach ($kartlar as [$url, $baslik, $aciklama]) {
        $h .= '<div class="kare"><span class="kod">' . qr_svg((string) $url, 120, 'M') . '</span><div><b>' . e((string) $baslik) . '</b><p>' . e((string) $aciklama)
            . '</p><small>' . e((string) preg_replace('#^https?://#', '', (string) $url)) . '</small></div></div>';
    }
    return $h . '</section>';
}

/** Alt bilgi: solda notlar, sağda mağaza damgası ve basım zamanı. $notlar_html hazır HTML (her biri <p>). */
function dokum_son(string $notlar_html, string $sag_html = ''): string
{
    if ($sag_html === '') {
        $sag_html = '<div class="damga"><div><b>' . e(setting('shop_name', 'OptiFlow')) . '</b><span>Basım ' . e(date('d.m.Y H:i')) . '</span></div><span class="logo-k">' . brand_mark() . '</span></div>';
    }
    return '<footer class="son"><div>' . $notlar_html . '</div>' . $sag_html . '</footer>';
}

/** Sayfa başı: <head>, ekran araç çubuğu ve A4 sayfanın açılışı. $ekCss: ek stil dosyaları (assets/ altında). */
function dokum_sayfa_bas(string $baslik, array $ekCss = []): string
{
    $h = '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . e($baslik) . '</title>'
        . '<link rel="stylesheet" href="' . e(asset('dokum.css')) . '">';
    foreach ($ekCss as $css) {
        $h .= '<link rel="stylesheet" href="' . e(asset($css)) . '">';
    }
    return $h . '</head><body><div class="arac no-print"><button type="button" data-print>Yazdır / PDF kaydet</button>'
        . '<button type="button" class="ghost" data-close>Kapat</button>'
        . '<span>PDF için yazdırma penceresinde hedef olarak <b>"PDF olarak kaydet"</b>i seçin; <b>Üst bilgi / alt bilgi</b> kutusunu kapatın.</span></div>'
        . '<main class="sayfa">';
}

function dokum_sayfa_son(): string
{
    return '</main><script src="' . e(asset('print.js')) . '" defer></script></body></html>';
}
