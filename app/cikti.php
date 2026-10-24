<?php
declare(strict_types=1);

/* ==========================================================================
   4.53.0 — Çıktı görünümü: mağazaya özel kâğıt çıktılar.
   Kullanıcı (24.10): "her kâğıt çıktıya farklı varyasyonlar; renkler vs. mağaza için seçilebilsin, bizim verdiğimiz
   seçeneklerle; yan yana iki mağazanın müşterisinin çıktısı aynı görünmesin."

   Seçenekler (Ayarlar › Çıktı görünümü; app_settings cikti_*):
     renk    13 hazır palet + "marka" (Ayarlar › Genel'deki müşteri sayfası rengi)
     baslik  başlık bandı: gece · renkli · acik · cizgi · cerceve · sade
     yazi    başlıkların yazı karakteri: otomatik (tasarımın kendi) · modern · klasik · karakter · genis
     kose    yuvarlak · keskin
     desen   başlık deseni: yok · mercek · cizgi · nokta · kareli · dalga
     isaret  mağaza işareti: mercek · monogram (baş harfler) · gozluk · yok
     slogan  mağaza adının altında kısa satır;  alt_yazi  müşteri nüshasının altında teşekkür / sosyal medya satırı
   Uygulama: cikti_stil_tag() her çıktının <head>'ine, CSS bağlantılarından SONRA eklenir; renkleri ve başlık bandını
   CSS değişkenleriyle (--brand…, --bas-*) değiştirir. Çıktı CSS'leri (fis, siparis-fisi, dokum, teklif-dokum) bu
   değişkenleri kullanır; tanımsızsa kendi varsayılanlarına düşer.
   ========================================================================== */

/** [ad, ana renk] — sıra Ayarlar ekranındaki sıradır. */
function cikti_paletler(): array
{
    return [
        'marka'   => ['Mağaza rengim', ''],
        'bordo'   => ['Bordo', '#b4233c'],
        'murdum'  => ['Mürdüm', '#7a2650'],
        'gul'     => ['Gül', '#c2477a'],
        'mercan'  => ['Mercan', '#d0573f'],
        'hardal'  => ['Hardal', '#b8860b'],
        'kahve'   => ['Kahve', '#7a4a2a'],
        'zeytin'  => ['Zeytin', '#5f6b2b'],
        'zumrut'  => ['Zümrüt', '#137a52'],
        'petrol'  => ['Petrol', '#0e6b73'],
        'turkuaz' => ['Turkuaz', '#128aa3'],
        'lacivert' => ['Lacivert', '#23407a'],
        'mor'     => ['Mor', '#6b3fa0'],
        'antrasit' => ['Antrasit', '#3b3f46'],
    ];
}

function cikti_secenekler(): array
{
    return [
        'baslik' => ['gece' => 'Gece (koyu bant)', 'renkli' => 'Renkli bant', 'acik' => 'Açık ton', 'cizgi' => 'Beyaz + alt çizgi', 'cerceve' => 'Çerçeveli', 'sade' => 'Sade (az mürekkep)'],
        'yazi'   => ['otomatik' => 'Tasarımın kendi yazısı', 'modern' => 'Modern', 'klasik' => 'Klasik (tırnaklı)', 'karakter' => 'Karakterli', 'genis' => 'Geniş'],
        'kose'   => ['yuvarlak' => 'Yuvarlak köşe', 'keskin' => 'Keskin köşe'],
        'desen'  => ['mercek' => 'Mercek halkaları', 'cizgi' => 'Eğik çizgi', 'nokta' => 'Nokta', 'kareli' => 'Kareli', 'dalga' => 'Dalga', 'yok' => 'Desensiz'],
        'isaret' => ['mercek' => 'Mercek işareti', 'monogram' => 'Baş harfler', 'gozluk' => 'Gözlük', 'yok' => 'İşaretsiz'],
        // 4.54.0 yarım sayfa fişlerin YAPISI: müşteri yarısı ve atölye/mağaza yarısı ayrı ayrı
        'musteri' => ['bant' => 'Üst bant', 'serit' => 'Yan şerit', 'mektup' => 'Antetli mektup', 'kocan' => 'Koparılır koçan', 'damga' => 'Mühür'],
        'atolye'  => ['bant' => 'Üst bant', 'etiket' => 'Askı etiketi', 'serit' => 'Yan şerit', 'sade' => 'Mürekkepsiz'],
    ];
}

function cikti_varsayilan(): array
{
    return ['renk' => 'marka', 'baslik' => 'gece', 'yazi' => 'otomatik', 'kose' => 'yuvarlak', 'desen' => 'mercek', 'isaret' => 'mercek',
            'musteri' => 'bant', 'atolye' => 'bant', 'slogan' => '', 'alt_yazi' => ''];
}

/** Gelen değerleri geçerli seçeneklere indirger (bilinmeyen → varsayılan). */
function cikti_temizle(array $v): array
{
    $d = cikti_varsayilan();
    $s = cikti_secenekler();
    $out = [];
    $out['renk'] = isset(cikti_paletler()[(string) ($v['renk'] ?? '')]) ? (string) $v['renk'] : $d['renk'];
    foreach (['baslik', 'yazi', 'kose', 'desen', 'isaret', 'musteri', 'atolye'] as $k) {
        $out[$k] = isset($s[$k][(string) ($v[$k] ?? '')]) ? (string) $v[$k] : $d[$k];
    }
    $tek = static fn($x, int $n): string => mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $x) ?? ''), 0, $n);
    $out['slogan'] = $tek($v['slogan'] ?? '', 60);
    $out['alt_yazi'] = $tek($v['alt_yazi'] ?? '', 140);
    return $out;
}

/** Geçerli ayar. $gecici verilirse (önizleme) yalnız bu istek için onu kullanır. */
function cikti_ayar(?array $gecici = null): array
{
    static $ozel = null;
    if ($gecici !== null) {
        $ozel = cikti_temizle($gecici);
    }
    if ($ozel !== null) {
        return $ozel;
    }
    $v = [];
    foreach (array_keys(cikti_varsayilan()) as $k) {
        $v[$k] = function_exists('setting') ? setting('cikti_' . $k, cikti_varsayilan()[$k]) : cikti_varsayilan()[$k];
    }
    return cikti_temizle($v);
}

/** Rastgele bir birleşim ("Karıştır"); slogan ve alt yazı korunur. */
function cikti_karistir(array $mevcut = []): array
{
    $s = cikti_secenekler();
    $renkler = array_keys(cikti_paletler());
    $rastgele = static fn(array $a) => $a[array_rand($a)];
    return cikti_temizle([
        'renk' => $rastgele(array_slice($renkler, 1)), 'baslik' => $rastgele(array_keys($s['baslik'])), 'yazi' => $rastgele(array_slice(array_keys($s['yazi']), 1)),
        'kose' => $rastgele(array_keys($s['kose'])), 'desen' => $rastgele(array_keys($s['desen'])), 'isaret' => $rastgele(array_keys($s['isaret'])),
        'musteri' => $rastgele(array_keys($s['musteri'])), 'atolye' => $rastgele(array_keys($s['atolye'])),
        'slogan' => $mevcut['slogan'] ?? '', 'alt_yazi' => $mevcut['alt_yazi'] ?? '',
    ]);
}

/* ---------------- Renk hesabı ---------------- */

function cikti_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** $a rengini $b'ye $oran kadar karıştırır (0 = $a, 1 = $b). */
function cikti_karis(string $a, string $b, float $oran): string
{
    [$r1, $g1, $b1] = cikti_rgb($a);
    [$r2, $g2, $b2] = cikti_rgb($b);
    $k = static fn($x, $y) => (int) round($x + ($y - $x) * $oran);
    return sprintf('#%02x%02x%02x', $k($r1, $r2), $k($g1, $g2), $k($b1, $b2));
}

/** Paletin bütün tonları. */
function cikti_palet(string $ad): array
{
    $ana = cikti_paletler()[$ad][1] ?? '';
    if ($ad === 'marka' || $ana === '') {
        // Genel'de renk seçilmemişse (eski varsayılan koyu bordo) sistemin bordosu
        $ana = function_exists('setting') && setting('brand_color', '') !== '' && function_exists('brand_color') ? brand_color() : '#b4233c';
        // çok koyu seçilmiş marka rengi bantta kaybolmasın: biraz aç
        [$r, $g, $b] = cikti_rgb($ana);
        if (($r * 299 + $g * 587 + $b * 114) / 1000 < 45) {
            $ana = cikti_karis($ana, '#ffffff', .25);
        }
    }
    return [
        'ana'    => $ana,
        'parlak' => cikti_karis($ana, '#ffffff', .14),
        'koyu'   => cikti_karis($ana, '#000000', .3),
        'derin'  => cikti_karis($ana, '#000000', .6),
        'gece'   => cikti_karis($ana, '#0b0809', .88),
        'vurgu'  => cikti_karis($ana, '#ffffff', .55),
        'gul'    => cikti_karis($ana, '#ffffff', .87),
        'gul2'   => cikti_karis($ana, '#ffffff', .76),
        'tint'   => cikti_karis($ana, '#ffffff', .955),
    ];
}

/* ---------------- Desen ---------------- */

function cikti_desen_css(string $desen, string $rgb, float $a): string
{
    $c = static fn(float $x): string => 'rgba(' . $rgb . ',' . rtrim(rtrim(number_format($x, 3, '.', ''), '0'), '.') . ')';
    switch ($desen) {
        case 'cizgi':
            return 'repeating-linear-gradient(115deg,' . $c($a) . ' 0 .5mm,transparent .5mm 2.4mm)';
        case 'nokta':
            return 'radial-gradient(' . $c($a * 2.4) . ' .32mm,transparent .42mm) 0 0/2.8mm 2.8mm';
        case 'kareli':
            return 'linear-gradient(' . $c($a * 1.4) . ' .18mm,transparent .18mm) 0 0/4mm 4mm,linear-gradient(90deg,' . $c($a * 1.4) . ' .18mm,transparent .18mm) 0 0/4mm 4mm';
        case 'dalga':
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="48" height="16" viewBox="0 0 48 16"><path d="M0 8c6-6 18-6 24 0s18 6 24 0" fill="none" stroke="rgb(' . $rgb . ')" stroke-opacity="' . round($a * 2.2, 3) . '" stroke-width="1.4"/></svg>';
            return 'url("data:image/svg+xml,' . rawurlencode($svg) . '") 0 0/12mm 4mm';
        case 'mercek':
            return 'repeating-radial-gradient(circle at 92% -20%,' . $c($a) . ' 0 .3mm,transparent .3mm 3.8mm)';
        default:
            return 'none';
    }
}

/* ---------------- Stil etiketi ---------------- */

/** Çıktı sayfasının <head>'ine, CSS bağlantılarından sonra. */
function cikti_stil_tag(?array $ayar = null): string
{
    $a = $ayar ?? cikti_ayar();
    $p = cikti_palet($a['renk']);
    $rgb = static fn(string $h): string => implode(',', cikti_rgb($h));
    $v = [
        '--brand' => $p['ana'], '--brand-deep' => $p['koyu'], '--brand-bright' => $p['parlak'], '--brand-ink' => $p['koyu'], '--brand-soft' => $p['gul'],
        '--marka' => $p['ana'], '--marka-koyu' => $p['koyu'], '--parlak' => $p['parlak'], '--tint' => $p['tint'], '--gul' => $p['gul'], '--gul-2' => $p['gul2'],
        '--derin' => $p['derin'], '--gece' => $p['gece'], '--night' => $p['gece'], '--coral' => $p['vurgu'],
        '--parlak-rgb' => $rgb($p['parlak']), '--koyu-rgb' => $rgb($p['koyu']),
        '--grad' => 'linear-gradient(140deg,' . $p['parlak'] . ' 0%,' . $p['koyu'] . ' 60%,' . $p['derin'] . ' 100%)',
    ];
    $koyuBant = in_array($a['baslik'], ['gece', 'renkli'], true);
    $desenRgb = $koyuBant ? '255,255,255' : $rgb($p['koyu']);
    $v['--bas-desen'] = cikti_desen_css($a['desen'], $desenRgb, $koyuBant ? .07 : .09);
    if ($koyuBant) {
        $v += [
            '--bas-bg' => $a['baslik'] === 'gece'
                ? 'radial-gradient(70% 160% at 100% 0%,rgba(' . $rgb($p['parlak']) . ',.5),transparent 60%),linear-gradient(115deg,' . $p['gece'] . ',' . $p['derin'] . ')'
                : 'radial-gradient(80% 140% at 100% 0%,rgba(255,255,255,.2),transparent 60%),linear-gradient(125deg,' . $p['parlak'] . ',' . $p['koyu'] . ' 70%,' . $p['derin'] . ')',
            '--bas-yazi' => '#ffffff', '--bas-soluk' => 'rgba(255,255,255,.72)', '--bas-vurgu' => $a['baslik'] === 'gece' ? $p['vurgu'] : $p['gul'],
            '--bas-cip' => 'rgba(255,255,255,.13)', '--bas-cizgi' => 'rgba(255,255,255,.24)', '--bas-golge' => 'none',
            '--bas-logo-bg' => '#ffffff', '--bas-logo' => $p['koyu'], '--bas-dev' => 'rgba(255,255,255,.2)',
        ];
    } else {
        $v += [
            '--bas-bg' => $a['baslik'] === 'acik' ? 'linear-gradient(125deg,' . $p['tint'] . ',' . $p['gul'] . ')' : '#ffffff',
            '--bas-yazi' => '#1b1416', '--bas-soluk' => '#6b5f62', '--bas-vurgu' => $p['koyu'],
            '--bas-cip' => 'rgba(' . $rgb($p['koyu']) . ',.07)', '--bas-cizgi' => $p['gul2'],
            '--bas-golge' => match ($a['baslik']) {
                'cizgi' => 'inset 0 -1.3mm 0 ' . $p['ana'],
                'cerceve' => 'inset 0 0 0 .6mm ' . $p['ana'],
                'sade' => 'inset 0 -.3mm 0 #cbbfc2',
                default => 'none',
            },
            '--bas-logo-bg' => $a['baslik'] === 'sade' ? 'transparent' : $p['koyu'], '--bas-logo' => $a['baslik'] === 'sade' ? $p['ana'] : '#ffffff',
            '--bas-dev' => 'rgba(' . $rgb($p['koyu']) . ',.13)',
        ];
    }
    $css = ':root{';
    foreach ($v as $k => $x) {
        $css .= $k . ':' . $x . ';';
    }
    $css .= '}';

    // Yazı karakteri
    $yazilar = [
        'modern'   => ['Manrope', 'manrope-latin-wght-normal', 'manrope-latin-ext-wght-normal', '200 800', '100%', '-.02em'],
        'klasik'   => ['Fraunces', 'fraunces-latin-500-normal', 'fraunces-latin-ext-500-normal', '500', '100%', '-.01em'],
        'karakter' => ['Bricolage', 'bricolage-grotesque-latin-standard-normal', 'bricolage-grotesque-latin-ext-standard-normal', '200 800', '85%', '-.01em'],
        'genis'    => ['Unbounded', 'unbounded-latin-wght-normal', 'unbounded-latin-ext-wght-normal', '200 900', '100%', '-.03em'],
    ];
    if (isset($yazilar[$a['yazi']])) {
        [$aile, $d1, $d2, $agirlik, $gen, $ls] = $yazilar[$a['yazi']];
        $f = '"CiktiBaslik"';
        $css .= '@font-face{font-family:' . $f . ';font-display:swap;font-weight:' . $agirlik . ';src:url("assets/fonts/' . $d1 . '.woff2") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC,U+20BA,U+2122}'
            . '@font-face{font-family:' . $f . ';font-display:swap;font-weight:' . $agirlik . ';src:url("assets/fonts/' . $d2 . '.woff2") format("woff2");unicode-range:U+0100-02BA,U+1E00-1E9F,U+20A0-20C0,U+2C60-2C7F,U+A720-A7FF}'
            . ':root,body.tema-mercek,body.tema-antet{--disp:' . $f . ',Manrope,sans-serif;--display:' . $f . ',Manrope,sans-serif;--display-w:' . $gen . ';--display-ls:' . $ls . '}';
        if ($a['yazi'] === 'klasik') {
            $css .= ':root{--disp-agirlik:500}';
        }
    }
    // Köşeler
    if ($a['kose'] === 'keskin') {
        $css .= '.fis-bant,.fis-vurgu,.fis-serit,.fis-kutu .not,.ie-bant,.ie-soz,.ie-qr,.kart,.kalan-not,.hesap .kalan,.ust,.yol,.kk,.kutu,.vurgu,.buyuk,.bilet,'
            . '.kare,.kare .kod,.a-bilgi,.d-etiket .fis-bant,.is-emri.d-etiket .ie-bant,.durum,.garanti-kalem,.u-qr span,.etiket,.rozet,.cubuk,.cubuk2,.yazi,.secenekler .kart,'
            . '.n-sag,.n-ust{border-radius:0 !important}';
    }
    return '<style id="cikti-gorunum">' . $css . '</style>';
}

/* ---------------- İşaret, slogan, alt yazı ---------------- */

/** Mağaza adının baş harfleri (en çok 2, Türkçe büyük harf). */
function cikti_bas_harfler(string $ad): string
{
    $ad = trim(preg_replace('/(?<!\pL)(optik|gözlük|gozluk|optical|ltd|şti|sti|a\.?ş)(?!\pL)\.?/iu', '', $ad) ?? $ad) ?: $ad;
    $parca = preg_split('/[\s\-&.]+/u', $ad, -1, PREG_SPLIT_NO_EMPTY) ?: ['O'];
    $h = '';
    foreach (array_slice($parca, 0, 2) as $p) {
        $ilk = mb_substr($p, 0, 1);
        $h .= $ilk === 'i' ? 'İ' : ($ilk === 'ı' ? 'I' : mb_strtoupper($ilk));
    }
    return $h !== '' ? $h : 'O';
}

/** Mağaza işareti (çıktılarda brand_mark() yerine). currentColor ile boyanır; 'yok' → ''. */
function cikti_isaret(?array $ayar = null): string
{
    $a = $ayar ?? cikti_ayar();
    switch ($a['isaret']) {
        case 'yok':
            return '';
        case 'monogram':
            $h = cikti_bas_harfler(function_exists('setting') ? setting('shop_name', 'OptiFlow') : 'OptiFlow');
            $boy = mb_strlen($h) > 1 ? 38 : 48;
            return '<span class="brand-mark ck-monogram" aria-hidden="true"><svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">'
                . '<circle cx="50" cy="50" r="45" fill="none" stroke="currentColor" stroke-width="6"/>'
                . '<text x="50" y="52" text-anchor="middle" dominant-baseline="central" fill="currentColor" font-size="' . $boy . '" font-weight="800" '
                . 'font-family="var(--disp, Manrope), Manrope, Arial, sans-serif" letter-spacing="-1">' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</text></svg></span>';
        case 'gozluk':
            return '<span class="brand-mark ck-gozluk" aria-hidden="true"><svg viewBox="0 0 100 100" fill="none" stroke="currentColor" stroke-width="7" stroke-linecap="round" xmlns="http://www.w3.org/2000/svg">'
                . '<circle cx="28" cy="56" r="18"/><circle cx="72" cy="56" r="18"/><path d="M46 52c2.5-4 5.5-4 8 0"/><path d="M10 50 4 30M90 50l6-20"/></svg></span>';
        default:
            return function_exists('brand_mark') ? brand_mark() : '';
    }
}

function cikti_slogan(): string
{
    return cikti_ayar()['slogan'];
}

function cikti_alt_yazi(): string
{
    return cikti_ayar()['alt_yazi'];
}
