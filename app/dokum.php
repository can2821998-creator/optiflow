<?php
declare(strict_types=1);

/* ==========================================================================
   4.22.0 — Ortak döküm (yazdırılan belge) yapı taşları, İKİ TASARIM:
     · "gozluk" — koyu-bordo büyük başlık, arkada dev belge numarası, aşama çizgisi, reçete iki mercek
                  (aks açısı iletkiyle çizili, köprüde PD), kartlar, degrade tutar blokları.
     · "bilet"  — koçanlı bilet: tarih → tarih rotası, koparılan koçanda belge no + karekod / tutar.
   Seçim: Ayarlar › Genel › "Belge tasarımı" (setting dokum_tema); belge ekranındaki düğmeyle ?tema= ile anlık.
   Stil: assets/dokum.css (body.tema-gozluk / body.tema-bilet). A4; renkler print-color-adjust: exact ile
   tarayıcının "arka planları yazdır" ayarından bağımsız basılır.

   Kural: yardımcılar düz metni KENDİSİ kaçırır (e()); adı *_html olan parametreler hazır HTML'dir.
   ========================================================================== */

const DOKUM_TEMALARI = ['gozluk' => 'Gözlük', 'bilet' => 'Bilet'];

function dokum_tema(): string
{
    static $t = null;
    if ($t !== null) {
        return $t;
    }
    $q = (string) ($_GET['tema'] ?? '');
    if (isset(DOKUM_TEMALARI[$q])) {
        return $t = $q;
    }
    $s = setting('dokum_tema', 'gozluk');
    return $t = isset(DOKUM_TEMALARI[$s]) ? $s : 'gozluk';
}

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
        'cuzdan'  => '<path d="M19 7V5.5A1.5 1.5 0 0 0 17.5 4h-12A2.5 2.5 0 0 0 3 6.5v11A2.5 2.5 0 0 0 5.5 20h13a1.5 1.5 0 0 0 1.5-1.5V16"/><path d="M21 9h-5a3 3 0 0 0 0 6h5z"/>',
        'kart'    => '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M2.5 10h19M6.5 15h4"/>',
        'kutu'    => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/>',
        'belge'   => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        'saat'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'onay'    => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
        'tik'     => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'carpi'   => '<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>',
        'ayar'    => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9z"/>',
        'kamyon'  => '<path d="M2 6h11v10H2zM13 9h4l4 4v3h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'grafik'  => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>',
        'etiket'  => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'kasa'    => '<rect x="3" y="4" width="18" height="16" rx="2.5"/><circle cx="12" cy="12" r="3.5"/><path d="M12 8.5V7M12 17v-1.5"/>',
        'eksi'    => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>',
        'arti'    => '<circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/>',
        'para'    => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.6"/><path d="M6 9.5v5M18 9.5v5"/>',
        'kalkan'  => '<path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/>',
        'yildiz'  => '<path d="M12 3l2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . ($y[$ad] ?? $y['belge']) . '</svg>';
}

/** Belge numarası: 00012 (önek isteğe bağlı) */
function dokum_no(int $id, string $onek = '#'): string
{
    return $onek . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/** Durum rozeti: $ton = '' (bordo) | ok | uyari | gri | bilgi */
function dokum_rozet(string $ton, string $ikon, string $metin): string
{
    return '<span class="rozet' . ($ton !== '' ? ' ' . e($ton) : '') . '"><i></i>' . e($metin) . '</span>';
}

/** Öne çıkan tutar (başlıkta / koçanda / gövdede): $ton = '' | ok | acik | koyu */
function dokum_vurgu(string $etiket, string $deger, string $alt = '', string $ton = ''): string
{
    return '<div class="vurgu' . ($ton !== '' ? ' ' . e($ton) : '') . '"><small>' . e($etiket) . '</small><b>' . e($deger) . '</b>'
        . ($alt !== '' ? '<em>' . e($alt) . '</em>' : '') . '</div>';
}

/** Mağaza işareti + adı + adres/telefon (başlık için) */
function dokum_marka_html(?string $marka = null, ?array $satirlar = null): string
{
    $marka ??= setting('shop_name', 'OptiFlow');
    if ($satirlar === null) {
        $satirlar = [setting('shop_address', ''), setting('shop_phone', '')];
    }
    $satirlar = array_values(array_filter(array_map(static fn($s) => trim((string) $s), $satirlar)));
    return '<div class="marka"><span class="logo">' . brand_mark() . '</span><div><b>' . e($marka) . '</b>'
        . ($satirlar ? '<small>' . e(implode(' · ', $satirlar)) . '</small>' : '') . '</div></div>';
}

/**
 * Belgenin başı. İki tasarımda da aynı veriyle çalışır:
 *  etiket   belge türü ("Gözlük sipariş fişi")      no     belge numarası (dev rakam / koçan)
 *  tarih    belge tarihi                              kucuk  "Sayın" gibi üst yazı;  baslik: kişi / kurum / dönem
 *  rozet    [ton, ikon, metin]                        metin  kısa açıklama (HTML)
 *  rota     [[üst, BÜYÜK, alt], [üst, BÜYÜK, alt]]    bilet: tarih → tarih; gözlük: başlık altında küçük
 *  alanlar  [[ikon, etiket, değer], …] (en çok 4)     gözlük: kartlar; bilet: bilet içinde satır
 *  yol      [[başlık, alt, 'bitti'|'simdi'|'bekliyor'], …]   aşama çizgisi
 *  vurgu    [etiket, değer, alt, ton]                 öne çıkan tutar
 *  qr       [adres, açıklama]                         bilet: koçanda; gözlük: başlıkta küçük
 *  marka / marka_satir: SGK dökümünde firma unvanı.
 */
function dokum_bas(array $b): string
{
    $marka = dokum_marka_html($b['marka'] ?? null, $b['marka_satir'] ?? null);
    $etiket = (string) ($b['etiket'] ?? '');
    $no = (string) ($b['no'] ?? '');
    $tarih = (string) ($b['tarih'] ?? '');
    $baslik = (string) ($b['baslik'] ?? '');
    $kucuk = (string) ($b['kucuk'] ?? '');
    $rozet = isset($b['rozet']) ? dokum_rozet(...$b['rozet']) : '';
    $metin = (string) ($b['metin'] ?? '');
    $alanlar = array_values(array_filter($b['alanlar'] ?? [], static fn($a) => is_array($a) && trim((string) ($a[2] ?? '')) !== ''));
    $vurgu = isset($b['vurgu']) ? dokum_vurgu((string) $b['vurgu'][0], (string) $b['vurgu'][1], (string) ($b['vurgu'][2] ?? ''), (string) ($b['vurgu'][3] ?? '')) : '';
    $qr = $b['qr'] ?? null;
    $qr = is_array($qr) && ($qr[0] ?? '') !== '' ? $qr : null;
    $yol = !empty($b['yol']) ? dokum_yol($b['yol']) : '';
    $rota = $b['rota'] ?? [];

    if (dokum_tema() === 'bilet') {
        $h = '<section class="bilet"><div class="gov"><div class="b-ust">' . $marka . '<span class="etiket">' . e($etiket) . '</span></div>';
        if (count($rota) === 2) {
            $h .= '<div class="rota">';
            foreach ($rota as $i => $r) {
                $h .= ($i === 1 ? '<div class="cizgi"><span>' . dokum_ikon('cerceve') . '</span></div>' : '')
                    . '<div class="d' . $i . '"><small>' . e((string) $r[0]) . '</small><strong>' . e((string) $r[1]) . '</strong><em>' . e((string) ($r[2] ?? '')) . '</em></div>';
            }
            $h .= '</div><div class="kim kucuk-kim">' . ($kucuk !== '' ? '<small>' . e($kucuk) . '</small>' : '') . '<b>' . e($baslik) . '</b>' . $rozet . '</div>';
        } else {
            $h .= '<div class="kim">' . ($kucuk !== '' ? '<small>' . e($kucuk) . '</small>' : '') . '<h1>' . e($baslik) . '</h1>' . $rozet . ($metin !== '' ? '<p>' . $metin . '</p>' : '') . '</div>';
        }
        if ($alanlar) {
            $h .= '<div class="yolcu n' . count($alanlar) . '">';
            foreach ($alanlar as $a) {
                $h .= '<div><small>' . e((string) $a[1]) . '</small><b>' . e((string) $a[2]) . '</b></div>';
            }
            $h .= '</div>';
        }
        $h .= '</div><aside class="kocan"><span class="delik"></span><small>' . e($etiket !== '' ? 'Belge no' : '') . '</small><div class="no">' . e(ltrim($no, '#')) . '</div>'
            . ($tarih !== '' ? '<span class="tarih">' . e($tarih) . '</span>' : '');
        if ($qr) {
            $h .= '<div class="qr">' . qr_svg((string) $qr[0], 120, 'M') . '</div><p>' . e((string) ($qr[1] ?? '')) . '</p>';
        } elseif ($vurgu !== '') {
            $h .= $vurgu;
            $vurgu = '';
        }
        $h .= '</aside></section>';
        if (count($rota) === 2 && $metin !== '') {
            $h .= '<p class="aciklama">' . $metin . '</p>';
        }
        if ($vurgu !== '') {
            $h .= '<div class="b-vurgu">' . $vurgu . '</div>';
        }
        return $h . $yol;
    }

    // Gözlük
    $h = '<header class="ust">' . ($no !== '' ? '<div class="dev" aria-hidden="true">' . e(ltrim($no, '#')) . '</div>' : '')
        . '<div class="u-ust">' . $marka . '<div class="belge"><span class="etiket">' . e($etiket) . '</span><p>'
        . e(trim(($no !== '' ? 'No ' . ltrim($no, '#') : '') . ($no !== '' && $tarih !== '' ? ' · ' : '') . $tarih)) . '</p></div></div>'
        . '<div class="u-alt"><div class="kim">' . ($kucuk !== '' ? '<small>' . e($kucuk) . '</small>' : '') . '<h1>' . e($baslik) . '</h1>' . $rozet
        . ($metin !== '' ? '<p>' . $metin . '</p>' : '') . '</div>' . $vurgu
        . ($qr ? '<div class="u-qr"><span>' . qr_svg((string) $qr[0], 120, 'M') . '</span><small>' . e((string) ($qr[1] ?? '')) . '</small></div>' : '')
        . '</div></header>';
    $h .= $yol !== '' ? $yol : '<div class="bosluk"></div>';
    if ($alanlar) {
        $h .= dokum_kartlar($alanlar);
    }
    return $h;
}

/** Aşama çizgisi: [[başlık, alt, 'bitti'|'simdi'|'bekliyor'], …] */
function dokum_yol(array $adimlar): string
{
    $h = '<section class="yol n' . count($adimlar) . '">';
    foreach ($adimlar as $a) {
        $d = (string) ($a[2] ?? 'bitti');
        $h .= '<div class="' . e($d) . '"><b>' . ($d === 'bekliyor' ? '' : dokum_ikon('tik')) . '</b><strong>' . e((string) $a[0]) . '</strong><small>' . e((string) ($a[1] ?? '')) . '</small></div>';
    }
    return $h . '</section>';
}

/** Kartlar: [[ikon, etiket, değer, alt?], …] */
function dokum_kartlar(array $kartlar): string
{
    $kartlar = array_values(array_filter($kartlar, static fn($a) => is_array($a)));
    $h = '<section class="kartlar n' . count($kartlar) . '">';
    foreach ($kartlar as $k) {
        $deger = trim((string) ($k[2] ?? ''));
        $h .= '<div class="kk"><span class="ic">' . dokum_ikon((string) $k[0]) . '</span><small>' . e((string) $k[1]) . '</small><b>' . e($deger !== '' ? $deger : '—') . '</b>'
            . (($k[3] ?? '') !== '' ? '<em>' . e((string) $k[3]) . '</em>' : '') . '</div>';
    }
    return $h . '</section>';
}

/** Eski ad (4.22.0 ilk taslak) — kartlarla aynı */
function dokum_bilgi(array $hucreler, bool $genisIlk = false): string
{
    return dokum_kartlar($hucreler);
}

/** Bölüm başlığı (sağda küçük not) */
function dokum_baslik(string $baslik, string $sag = ''): string
{
    return '<h2 class="bb"><span>' . e($baslik) . '</span>' . ($sag !== '' ? '<small>' . e($sag) . '</small>' : '') . '</h2>';
}

/** Başlıklı kutu. $ic_html hazır HTML. */
function dokum_kutu(string $baslik, string $ic_html, string $sinif = '', string $sag = ''): string
{
    return '<section class="kutu' . ($sinif !== '' ? ' ' . e($sinif) : '') . '"><h3>' . e($baslik)
        . ($sag !== '' ? '<small>' . e($sag) . '</small>' : '') . '</h3>' . $ic_html . '</section>';
}

/** Anahtar–değer listesi */
function dokum_liste(array $satirlar): string
{
    $h = '<dl class="liste">';
    foreach ($satirlar as [$dt, $dd]) {
        $dd = trim((string) $dd);
        $h .= '<div><dt>' . e((string) $dt) . '</dt><dd>' . e($dd !== '' ? $dd : '—') . '</dd></div>';
    }
    return $h . '</dl>';
}

/** Hesap fişi: [[etiket, tutar-metni, 'eksi'|''|'ara', alt?], …] */
function dokum_hesap(array $satirlar): string
{
    $h = '<div class="hesap">';
    foreach ($satirlar as $s) {
        $h .= '<div class="' . e((string) ($s[2] ?? '')) . '"><span>' . e((string) $s[0]) . (($s[3] ?? '') !== '' ? '<small>' . e((string) $s[3]) . '</small>' : '') . '</span><b>' . e((string) $s[1]) . '</b></div>';
    }
    return $h . '</div>';
}

/** Büyük tutar bloğu (kalan bakiye, tahsil edilen…): $ton = '' (bordo) | ok | koyu */
function dokum_buyuk(string $etiket, string $deger, string $metin = '', string $rozet = '', string $ton = ''): string
{
    return '<div class="buyuk' . ($ton !== '' ? ' ' . e($ton) : '') . '"><small>' . e($etiket) . '</small><b>' . e($deger) . '</b>'
        . ($metin !== '' ? '<p>' . e($metin) . '</p>' : '') . ($rozet !== '' ? '<span>' . e($rozet) . '</span>' : '') . '</div>';
}

/** Durum bandı (tam genişlik): $ton = '' | ok | uyari | gri */
function dokum_durum(string $ton, string $ikon, string $baslik, string $metin = ''): string
{
    return '<section class="durum' . ($ton !== '' ? ' ' . e($ton) : '') . '"><span class="ic">' . dokum_ikon($ikon) . '</span><div><b>'
        . e($baslik) . '</b>' . ($metin !== '' ? '<span>' . e($metin) . '</span>' : '') . '</div></section>';
}

/* ---------------- Gösteri parçaları (SVG) ---------------- */

/** Tek mercek: iletki (TABO 0–180°), aks çizgisi, ortada SPH; altında CYL/AKS/ADD. */
function dokum_mercek(string $kimlik, ?string $sph, ?string $cyl, ?string $aks, ?string $add, bool $kapali = false): string
{
    $c = 120;
    $r = 96;
    $s = '<svg viewBox="0 0 240 240" class="mercek' . ($kapali ? ' kapali' : '') . '" aria-hidden="true"><defs>'
        . '<radialGradient id="mc' . $kimlik . '" cx="38%" cy="30%" r="78%"><stop offset="0" stop-color="#fff"/><stop offset=".72" stop-color="#fdf3f5"/><stop offset="1" stop-color="#f1d4db"/></radialGradient>'
        . '<linearGradient id="mr' . $kimlik . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#e2405f"/><stop offset=".5" stop-color="#8f1a2e"/><stop offset="1" stop-color="#2a0a12"/></linearGradient></defs>'
        . '<circle cx="120" cy="120" r="' . ($r + 10) . '" fill="none" stroke="url(#mr' . $kimlik . ')" stroke-width="13"/>'
        . '<circle cx="120" cy="120" r="' . ($r + 2) . '" fill="url(#mc' . $kimlik . ')"/>'
        . '<path d="M40 70 A92 92 0 0 1 120 28" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" opacity=".7"/>';
    for ($a = 0; $a <= 180; $a += 5) {
        $t = deg2rad($a);
        $uz = $a % 30 === 0 ? 14 : ($a % 10 === 0 ? 8 : 4);
        $s .= sprintf('<path d="M%.1f %.1fL%.1f %.1f" stroke="#8f1a2e" stroke-width="%s" opacity="%s"/>',
            $c + ($r - 1) * cos($t), $c - ($r - 1) * sin($t), $c + ($r - 1 - $uz) * cos($t), $c - ($r - 1 - $uz) * sin($t),
            $a % 30 === 0 ? '1.4' : '.7', $a % 30 === 0 ? '1' : '.5');
        if ($a % 30 === 0) {
            $s .= sprintf('<text x="%.1f" y="%.1f" text-anchor="middle" font-size="8.5" font-weight="700" fill="#9b6b75" font-family="Manrope,sans-serif">%d</text>',
                $c + ($r - 26) * cos($t), $c - ($r - 26) * sin($t) + 3, $a);
        }
    }
    $aksSayi = is_numeric(trim((string) $aks)) ? (float) $aks : null;
    if ($aksSayi !== null) {
        $t = deg2rad($aksSayi);
        $x1 = $c + ($r + 2) * cos($t);
        $y1 = $c - ($r + 2) * sin($t);
        $s .= sprintf('<path d="M%.1f %.1fL%.1f %.1f" stroke="#c8264a" stroke-width="2.2" stroke-dasharray="5 4"/><circle cx="%.1f" cy="%.1f" r="4.5" fill="#c8264a"/>',
            $x1, $y1, $c - ($r + 2) * cos($t), $c + ($r + 2) * sin($t), $x1, $y1);
    }
    $sphM = trim((string) $sph) !== '' ? trim((string) $sph) : 'PL';
    $alt = [];
    if (trim((string) $cyl) !== '') { $alt[] = 'CYL ' . trim((string) $cyl); }
    if ($aksSayi !== null) { $alt[] = 'AKS ' . trim((string) $aks) . '°'; }
    $s .= '<text x="120" y="126" text-anchor="middle" class="m-sph">' . e($sphM) . '</text>'
        . '<text x="120" y="146" text-anchor="middle" class="m-et">SPH</text>'
        . ($alt ? '<text x="120" y="172" text-anchor="middle" class="m-alt">' . e(implode('  ·  ', $alt)) . '</text>' : '')
        . (trim((string) $add) !== '' ? '<text x="120" y="190" text-anchor="middle" class="m-add">ADD ' . e(trim((string) $add)) . '</text>' : '');
    return $s . '</svg>';
}

/**
 * Reçete gösterimi. Gözlük: iki mercek + köprüde PD. Bilet: sağ/sol satırları kutucuklarla.
 * $rx: prescription_records satırı (near_* isteğe bağlı).
 */
function dokum_recete(array $rx): string
{
    $v = static fn($k) => trim((string) ($rx[$k] ?? ''));
    $goz = (string) ($rx['lens_eyes'] ?? 'both');
    $kapali = static fn(string $s) => $goz !== '' && $goz !== 'both' && $goz !== $s;
    $pd = $v('pd');
    $pdAlt = $v('right_pd') !== '' || $v('left_pd') !== '' ? 'R ' . ($v('right_pd') ?: '—') . ' · L ' . ($v('left_pd') ?: '—') : 'göz bebekleri arası';
    $yuk = $v('right_height') !== '' || $v('left_height') !== '' ? 'Yükseklik R ' . ($v('right_height') ?: '—') . ' · L ' . ($v('left_height') ?: '—') : '';
    $yakin = $v('near_right_sph') !== '' || $v('near_left_sph') !== '' ? 'Yakın: R ' . ($v('near_right_sph') ?: '—') . ' · L ' . ($v('near_left_sph') ?: '—') . ($v('near_lens_type') !== '' ? ' · ' . $v('near_lens_type') : '') : '';
    $notlar = array_values(array_filter([$yuk, $yakin]));

    if (dokum_tema() === 'bilet') {
        $kolon = [['sph', 'SPH'], ['cyl', 'CYL'], ['axis', 'AKS'], ['add', 'ADD']];
        $kolon = array_values(array_filter($kolon, static fn($k) => $k[0] !== 'add' || $v('right_add') !== '' || $v('left_add') !== ''));
        $h = '<div class="rx-bilet n' . count($kolon) . '">';
        foreach (['right' => ['R', 'Sağ'], 'left' => ['L', 'Sol']] as $taraf => [$harf, $ad]) {
            $h .= '<div class="satir' . ($kapali($taraf) ? ' kapali' : '') . '"><b><i>' . $harf . '</i>' . $ad . '</b>';
            foreach ($kolon as [$k, $et]) {
                $deger = $v($taraf . '_' . $k);
                $h .= '<div class="m"><b>' . e($deger !== '' ? $deger . ($k === 'axis' ? '°' : '') : '—') . '</b><span>' . $et . '</span></div>';
            }
            $h .= '</div>';
        }
        $h .= '<div class="pd"><div><small>Göz bebekleri arası</small><b>' . e($pd !== '' ? $pd . ' mm' : '—') . '</b></div>'
            . '<div><small>Reçete</small><b>' . e(date_tr($rx['prescription_date'] ?? null)) . '</b></div>'
            . '<div><small>Kullanım</small><b>' . e(lens_designs()[(string) ($rx['lens_design'] ?? '')] ?? ($v('lens_type') ?: '—')) . '</b></div></div>';
        return $h . ($notlar ? '<p class="rx-not">' . e(implode(' · ', $notlar)) . '</p>' : '') . '</div>';
    }

    $id = substr(md5((string) ($rx['id'] ?? mt_rand())), 0, 6);
    $h = '<div class="gozluk"><div class="g-goz">' . dokum_mercek($id . 'r', $v('right_sph'), $v('right_cyl'), $v('right_axis'), $v('right_add'), $kapali('right')) . '<span>Sağ göz</span></div>'
        . '<div class="kopru"><svg viewBox="0 0 120 40" aria-hidden="true"><path d="M2 24 Q60 -8 118 24" fill="none" stroke="#8f1a2e" stroke-width="6" stroke-linecap="round"/></svg>'
        . '<b>' . e($pd !== '' ? $pd . ' mm' : '— mm') . '</b><small>' . e($pdAlt) . '</small></div>'
        . '<div class="g-goz">' . dokum_mercek($id . 'l', $v('left_sph'), $v('left_cyl'), $v('left_axis'), $v('left_add'), $kapali('left')) . '<span>Sol göz</span></div></div>';
    return $h . ($notlar ? '<p class="rx-not">' . e(implode(' · ', $notlar)) . '</p>' : '');
}

/** Halka grafik: $dilimler [[etiket, değer, alt-metin?], …]; ortada $ortaUst / $ortaAlt. */
function dokum_halka(array $dilimler, string $ortaUst, string $ortaAlt = ''): string
{
    $renk = ['#c8264a', '#2a0a12', '#e88a9c', '#8f1a2e', '#f3c6cf', '#5c4a50'];
    $top = array_sum(array_map(static fn($d) => max(0, (float) $d[1]), $dilimler));
    $r = 70;
    $cevre = 2 * M_PI * $r;
    $s = '<svg viewBox="0 0 200 200" class="halka-svg" aria-hidden="true"><circle cx="100" cy="100" r="' . $r . '" fill="none" stroke="#f6e6ea" stroke-width="26"/>';
    $bas = 0.0;
    foreach (array_values($dilimler) as $i => $d) {
        if ($top <= 0) {
            break;
        }
        $pay = max(0, (float) $d[1]) / $top;
        if ($pay <= 0) {
            continue;
        }
        $s .= sprintf('<circle cx="100" cy="100" r="%d" fill="none" stroke="%s" stroke-width="26" stroke-dasharray="%.2f %.2f" stroke-dashoffset="%.2f" transform="rotate(-90 100 100)"/>',
            $r, $renk[$i % count($renk)], max(0.01, $pay * $cevre - ($pay < 1 ? 1.5 : 0)), $cevre, -$bas * $cevre);
        $bas += $pay;
    }
    $uz = mb_strlen($ortaUst);
    $boy = $uz <= 5 ? 26 : ($uz <= 8 ? 19 : ($uz <= 11 ? 15 : 12));
    $s .= '<text x="100" y="' . (100 + (int) round($boy / 3)) . '" text-anchor="middle" class="h-ust" style="font-size:' . $boy . 'px">' . e($ortaUst) . '</text>'
        . ($ortaAlt !== '' ? '<text x="100" y="128" text-anchor="middle" class="h-alt">' . e($ortaAlt) . '</text>' : '') . '</svg>';
    $l = '<ul class="lejant">';
    foreach (array_values($dilimler) as $i => $d) {
        $l .= '<li><i style="background:' . $renk[$i % count($renk)] . '"></i><span>' . e((string) $d[0]) . '</span><b>' . e((string) ($d[2] ?? $d[1])) . '</b>'
            . ($top > 0 ? '<em>%' . (int) round(max(0, (float) $d[1]) / $top * 100) . '</em>' : '') . '</li>';
    }
    return '<div class="halka">' . $s . $l . '</ul></div>';
}

/** Eğri (bakiye seyri): $noktalar [[etiket, değer], …] */
function dokum_egri(array $noktalar, string $altMetin = ''): string
{
    $n = count($noktalar);
    if ($n < 2) {
        return '';
    }
    $deg = array_map(static fn($p) => (float) $p[1], $noktalar);
    $min = min(0.0, min($deg));
    $max = max($deg);
    $aralik = $max - $min ?: 1.0;
    $w = 600;
    $hgt = 120;
    $pts = [];
    foreach ($deg as $i => $d) {
        $pts[] = [round($i / ($n - 1) * $w, 1), round($hgt - 8 - ($d - $min) / $aralik * ($hgt - 24), 1)];
    }
    $yol = 'M' . implode(' L', array_map(static fn($p) => $p[0] . ' ' . $p[1], $pts));
    $son = end($pts);
    $s = '<svg viewBox="0 -6 ' . $w . ' ' . ($hgt + 10) . '" preserveAspectRatio="none" class="egri" aria-hidden="true"><defs><linearGradient id="egriD" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#c8264a" stop-opacity=".35"/><stop offset="1" stop-color="#c8264a" stop-opacity="0"/></linearGradient></defs>'
        . '<path d="' . $yol . ' L' . $w . ' ' . $hgt . ' L0 ' . $hgt . 'Z" fill="url(#egriD)"/>'
        . '<path d="' . $yol . '" fill="none" stroke="#8f1a2e" stroke-width="2.5" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>'
        . '<circle cx="' . $son[0] . '" cy="' . $son[1] . '" r="5" fill="#c8264a"/></svg>';
    return '<div class="egri-kap">' . $s . '<div class="egri-alt"><span>' . e((string) $noktalar[0][0]) . '</span><span>' . e($altMetin) . '</span><span>' . e((string) end($noktalar)[0]) . '</span></div></div>';
}

/** İlerleme çubuğu (garanti süresi vb.) */
function dokum_ilerleme(float $oran, string $sol, string $sag): string
{
    $oran = max(0.0, min(1.0, $oran));
    return '<div class="ilerleme"><div class="cubuk2"><i style="width:' . round($oran * 100, 1) . '%"></i></div><div class="ii"><span>' . e($sol) . '</span><span>' . e($sag) . '</span></div></div>';
}

/** İmza kutuları: [[rol, ad (boşsa çizgi), açıklama], …] */
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

/** Karekodlu bilgi kartları: [[adres, başlık, açıklama], …] */
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

/** Alt bilgi: solda notlar (HTML), sağda mağaza damgası ve basım zamanı. */
function dokum_son(string $notlar_html, string $sag_html = ''): string
{
    if ($sag_html === '') {
        $sag_html = '<div class="damga"><div><b>' . e(setting('shop_name', 'OptiFlow')) . '</b><span>Basım ' . e(date('d.m.Y H:i')) . '</span></div><span class="logo-k">' . brand_mark() . '</span></div>';
    }
    return '<footer class="son"><div>' . $notlar_html . '</div>' . $sag_html . '</footer>';
}

/** Sayfa başı: <head>, ekran araç çubuğu (yazdır, kapat, tasarım seçimi) ve A4 sayfanın açılışı. */
function dokum_sayfa_bas(string $baslik, array $ekCss = []): string
{
    $tema = dokum_tema();
    $h = '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . e($baslik) . '</title>'
        . '<link rel="stylesheet" href="' . e(asset('dokum.css')) . '">';
    foreach ($ekCss as $css) {
        $h .= '<link rel="stylesheet" href="' . e(asset($css)) . '">';
    }
    $q = $_GET;
    $secim = '';
    foreach (DOKUM_TEMALARI as $k => $ad) {
        $q['tema'] = $k;
        $secim .= '<a href="?' . e(http_build_query($q)) . '"' . ($k === $tema ? ' class="secili" aria-current="true"' : '') . '>' . e($ad) . '</a>';
    }
    return $h . '</head><body class="tema-' . e($tema) . '"><div class="arac no-print"><button type="button" data-print>Yazdır / PDF kaydet</button>'
        . '<button type="button" class="ghost" data-close>Kapat</button><nav class="tema-sec" aria-label="Belge tasarımı">' . $secim . '</nav>'
        . '<span>PDF için hedefte <b>"PDF olarak kaydet"</b>i seçin; <b>Üst bilgi / alt bilgi</b>yi kapatın. Varsayılan tasarım: Ayarlar › Genel.</span></div>'
        . '<main class="sayfa">';
}

function dokum_sayfa_son(): string
{
    return '</main><script src="' . e(asset('print.js')) . '" defer></script></body></html>';
}
