<?php
declare(strict_types=1);

/* ==========================================================================
   Etiket düzenleri
   --------------------------------------------------------------------------
   Amaç: elde yalnızca A4 kâğıt ve sıradan bir yazıcı varken çerçeveleri
   barkodlayabilmek. Üç yol:

     kesme  — düz A4'e basılır, makasla kesilir, çift taraflı bantla ya da
              ataşla sapa tutturulur. Etiketler bitişik dizilir: her kesim
              sayfanın bir ucundan öbür ucuna düz bir çizgidir, önce yatay
              şeritlere kesip sonra şeritleri dikey bölersiniz.
     katlamali — kes-yapıştırın iki yüzlü hâli: ortadaki dikey çizgiden
              katlanıp sapa geçirilir; ön yüzde marka ve fiyat, arka yüzde
              karekod olur. Yazıların hiçbiri ters değildir (dikey katlamada
              yazı dönmez).
     asma   — katlanır askı etiketi: sapın üzerine katlanır, iki yüzü de
              okunur (üst yarı 180° döndürülmüştür), delik yeri işaretlidir.
     yapiskanli — kırtasiyeden alınan A4 yapışkanlı etiket kâğıdı. Marka
              marka ölçü değiştiği için bütün ölçüler milimetre olarak
              ayarlanabilir; hazır ölçüler yalnızca başlangıç noktasıdır.

   Bütün ölçüler milimetredir. Yazıcı "%100 / gerçek boyut" ile basmalıdır;
   deneme sayfasındaki cetvel bunu denetler.
   ========================================================================== */

/** Hazır düzenler. */
function label_layouts(): array
{
    return [
        'kesme-50' => [
            'ad' => 'Düz A4 · kes-yapıştır 50×32 mm (4×9 = 36 adet)',
            'tip' => 'kesme', 'w' => 50, 'h' => 32, 'cols' => 4, 'rows' => 9,
            'ml' => 5, 'mt' => 4.5, 'gx' => 0, 'gy' => 0,
        ],
        'katlamali-64' => [
            'ad' => 'Düz A4 · sapa katlanan iki yüzlü 64×26 mm (3×11 = 33 adet)',
            'tip' => 'katlamali', 'w' => 64, 'h' => 26, 'cols' => 3, 'rows' => 11,
            'ml' => 9, 'mt' => 5, 'gx' => 0, 'gy' => 0,
        ],
        'kesme-40' => [
            'ad' => 'Düz A4 · küçük kes-yapıştır 40×26 mm (5×11 = 55 adet)',
            'tip' => 'kesme', 'w' => 40, 'h' => 26, 'cols' => 5, 'rows' => 11,
            'ml' => 5, 'mt' => 5, 'gx' => 0, 'gy' => 0,
        ],
        'asma-40' => [
            'ad' => 'Askı etiketi · katlanır 40×46 mm (4×6 = 24 adet)',
            'tip' => 'asma', 'w' => 40, 'h' => 46, 'cols' => 4, 'rows' => 6,
            'ml' => 12, 'mt' => 8, 'gx' => 5, 'gy' => 2,
        ],
        'yapiskanli-70x37' => [
            'ad' => 'Yapışkanlı A4 · 70×37 mm (3×8 = 24 adet)',
            'tip' => 'yapiskanli', 'w' => 70, 'h' => 37, 'cols' => 3, 'rows' => 8,
            'ml' => 0, 'mt' => 0, 'gx' => 0, 'gy' => 0,
        ],
        'yapiskanli-63x38' => [
            'ad' => 'Yapışkanlı A4 · 63,5×38,1 mm (3×7 = 21 adet)',
            'tip' => 'yapiskanli', 'w' => 63.5, 'h' => 38.1, 'cols' => 3, 'rows' => 7,
            'ml' => 7.2, 'mt' => 15.1, 'gx' => 2.5, 'gy' => 0,
        ],
        'yapiskanli-48x25' => [
            'ad' => 'Yapışkanlı A4 · 48,5×25,4 mm (4×11 = 44 adet)',
            'tip' => 'yapiskanli', 'w' => 48.5, 'h' => 25.4, 'cols' => 4, 'rows' => 11,
            'ml' => 8, 'mt' => 8, 'gx' => 0, 'gy' => 0,
        ],
        'yapiskanli-38x21' => [
            'ad' => 'Yapışkanlı A4 · 38×21,2 mm (5×13 = 65 adet)',
            'tip' => 'yapiskanli', 'w' => 38, 'h' => 21.2, 'cols' => 5, 'rows' => 13,
            'ml' => 5, 'mt' => 11, 'gx' => 2.5, 'gy' => 0,
        ],
    ];
}

/** Sayı okuma: "70,5" → 70.5, sınırlar içinde. */
function label_num(mixed $v, float $alt, float $ust, float $varsayilan): float
{
    $s = str_replace(',', '.', trim((string) $v));
    if ($s === '' || !is_numeric($s)) {
        return $varsayilan;
    }
    return max($alt, min($ust, round((float) $s, 2)));
}

/**
 * İstekten (ya da kayıtlı ayardan) düzen çözer ve doğrular.
 * Kullanıcı ölçüleri elle değiştirebilir; A4 dışına taşan düzen kırpılır.
 */
function label_layout_from(array $istek): array
{
    $hazir = label_layouts();
    $anahtar = (string) ($istek['duzen'] ?? setting('label_layout', 'kesme-50'));
    if (!isset($hazir[$anahtar])) {
        $anahtar = 'kesme-50';
    }
    $d = $hazir[$anahtar];
    $d['anahtar'] = $anahtar;

    // Elle ölçü girildiyse onu kullan
    foreach (['w' => [15, 210], 'h' => [10, 297], 'ml' => [0, 100], 'mt' => [0, 100], 'gx' => [0, 40], 'gy' => [0, 40]] as $k => [$alt, $ust]) {
        if (isset($istek[$k]) && trim((string) $istek[$k]) !== '') {
            $d[$k] = label_num($istek[$k], $alt, $ust, (float) $d[$k]);
        }
    }
    foreach (['cols' => [1, 8], 'rows' => [1, 20]] as $k => [$alt, $ust]) {
        if (isset($istek[$k]) && trim((string) $istek[$k]) !== '') {
            $d[$k] = (int) label_num($istek[$k], $alt, $ust, (float) $d[$k]);
        }
    }

    // A4'e sığmayan sütun/satır sayısını kırp (210×297 mm)
    $d['cols'] = max(1, min($d['cols'], (int) floor((210 - $d['ml'] + $d['gx'] + 0.4) / ($d['w'] + $d['gx']))));
    $d['rows'] = max(1, min($d['rows'], (int) floor((297 - $d['mt'] + $d['gy'] + 0.4) / ($d['h'] + $d['gy']))));
    $d['sayfada'] = $d['cols'] * $d['rows'];

    /* Karekod kenarı: yazı için yer bırakacak kadar küçük, telefonla
       okunacak kadar büyük. En az 10 mm — altında okuma zorlaşır. */
    $ic = $d['tip'] === 'asma' ? ($d['h'] - 3) / 2 : $d['h'];
    $gen = $d['tip'] === 'katlamali' ? $d['w'] / 2 : $d['w'];   // katlamalıda yarım yüz
    $d['qr'] = $d['tip'] === 'katlamali'
        ? round(max(10.0, min($gen - 4, $ic - 7, 18.0)), 1)
        : round(max(10.0, min($gen * 0.38, $ic - 6.5, 18.0)), 1);
    // Dar etikette mağaza adı ve renk/beden satırı basılmaz, yazılar küçülür
    $d['dar'] = $gen < 45 || $ic < 26;

    return $d;
}

/** Bir çerçeve için etiket içeriği (baskı görünümünde kullanılır). */
function label_lines(array $k): array
{
    return [
        'ust'   => (string) ($k['brand'] ?? ''),
        'model' => trim((string) ($k['model'] ?? '')),
        'alt'   => trim(((string) ($k['color'] ?? '')) . ' ' . ((string) ($k['size'] ?? ''))),
        'fiyat' => $k['price'] !== null ? money($k['price']) : '',
        'kod'   => (string) ($k['barcode'] ?? ''),
    ];
}
