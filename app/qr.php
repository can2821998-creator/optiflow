<?php
declare(strict_types=1);

/* ==========================================================================
   Karekod (QR) üreteci — saf PHP, dış kütüphane yok.
   Bayt kipi (UTF-8), hata düzeltme M veya Q, sürüm 1–10.
   Çıktı: ölçeklenebilir SVG (yazdırmada da keskin, zemin rengine bağlı değil).
   ========================================================================== */

/** Sürüm/hata düzeltme tabloları: [toplam kod sözcüğü, blok başına EC, [blok sayısı, veri/blok], …] */
function qr_tables(): array
{
    return [
        // sürüm => ['M' => [toplamCS, ecPerBlok, [[blokSayısı, veriPerBlok], …], veriKapasitesiBayt], 'Q' => …]
        1  => ['M' => [26,  10, [[1, 16]], 14],  'Q' => [26,  13, [[1, 13]], 11]],
        2  => ['M' => [44,  16, [[1, 28]], 26],  'Q' => [44,  22, [[1, 22]], 20]],
        3  => ['M' => [70,  26, [[1, 44]], 42],  'Q' => [70,  18, [[2, 17]], 32]],
        4  => ['M' => [100, 18, [[2, 32]], 62],  'Q' => [100, 26, [[2, 24]], 46]],
        5  => ['M' => [134, 24, [[2, 43]], 84],  'Q' => [134, 18, [[2, 15], [2, 16]], 60]],
        6  => ['M' => [172, 16, [[4, 27]], 106], 'Q' => [172, 24, [[4, 19]], 74]],
        7  => ['M' => [196, 18, [[4, 31]], 122], 'Q' => [196, 18, [[2, 14], [4, 15]], 86]],
        8  => ['M' => [242, 22, [[2, 38], [2, 39]], 152], 'Q' => [242, 22, [[4, 18], [2, 19]], 108]],
        9  => ['M' => [292, 22, [[3, 36], [2, 37]], 180], 'Q' => [292, 20, [[4, 16], [4, 17]], 130]],
        10 => ['M' => [346, 26, [[4, 43], [1, 44]], 213], 'Q' => [346, 24, [[6, 19], [2, 20]], 151]],
    ];
}

/** Hizalama deseni merkez koordinatları (sürüm başına) */
function qr_align(int $ver): array
{
    $t = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
          6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    return $t[$ver] ?? [];
}

/* ---------- Galois cismi GF(256) ---------- */

function qr_gf(): array
{
    static $tab = null;
    if ($tab === null) {
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11d;
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        $tab = [$exp, $log];
    }
    return $tab;
}

function qr_mul(int $a, int $b): int
{
    if ($a === 0 || $b === 0) {
        return 0;
    }
    [$exp, $log] = qr_gf();
    return $exp[$log[$a] + $log[$b]];
}

/** Reed-Solomon hata düzeltme kod sözcükleri */
function qr_rs(array $data, int $ecLen): array
{
    [$exp, $log] = qr_gf();
    // üreteç polinomu
    $gen = [1];
    for ($i = 0; $i < $ecLen; $i++) {
        $yeni = array_fill(0, count($gen) + 1, 0);
        foreach ($gen as $j => $g) {
            $yeni[$j] ^= qr_mul($g, 1);
            $yeni[$j + 1] ^= qr_mul($g, $exp[$i]);
        }
        // ilk terim: gen[j] * x  → kaydırma zaten yukarıda yapıldı
        $gen = $yeni;
    }

    $kalan = array_merge($data, array_fill(0, $ecLen, 0));
    $n = count($data);
    for ($i = 0; $i < $n; $i++) {
        $katsayi = $kalan[$i];
        if ($katsayi === 0) {
            continue;
        }
        foreach ($gen as $j => $g) {
            $kalan[$i + $j] ^= qr_mul($g, $katsayi);
        }
    }
    return array_slice($kalan, $n, $ecLen);
}

/* ---------- Biçim ve sürüm bilgisi ---------- */

function qr_format_bits(string $ecc, int $mask): int
{
    $seviye = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2][$ecc] ?? 0;
    $veri = ($seviye << 3) | $mask;
    $rem = $veri;
    for ($i = 0; $i < 10; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
    }
    return (($veri << 10) | $rem) ^ 0x5412;
}

function qr_version_bits(int $ver): int
{
    $rem = $ver;
    for ($i = 0; $i < 12; $i++) {
        $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1f25);
    }
    return ($ver << 12) | $rem;
}

/* ---------- Maske işlevleri ---------- */

function qr_mask_test(int $m, int $y, int $x): bool
{
    return match ($m) {
        0 => ($y + $x) % 2 === 0,
        1 => $y % 2 === 0,
        2 => $x % 3 === 0,
        3 => ($y + $x) % 3 === 0,
        4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
        5 => (($y * $x) % 2) + (($y * $x) % 3) === 0,
        6 => (((($y * $x) % 2) + (($y * $x) % 3)) % 2) === 0,
        7 => (((($y + $x) % 2) + (($y * $x) % 3)) % 2) === 0,
        default => false,
    };
}

/** Maske cezası (ISO/IEC 18004 §8.8.2) */
function qr_penalty(array $m, int $n): int
{
    $ceza = 0;

    // 1. kural: aynı renkten 5+ ardışık
    for ($i = 0; $i < $n; $i++) {
        for ($yon = 0; $yon < 2; $yon++) {
            $say = 1;
            $onceki = -1;
            for ($j = 0; $j < $n; $j++) {
                $v = $yon === 0 ? $m[$i][$j] : $m[$j][$i];
                if ($v === $onceki) {
                    $say++;
                } else {
                    if ($say >= 5) {
                        $ceza += 3 + ($say - 5);
                    }
                    $say = 1;
                    $onceki = $v;
                }
            }
            if ($say >= 5) {
                $ceza += 3 + ($say - 5);
            }
        }
    }

    // 2. kural: 2×2 aynı renk blokları
    for ($y = 0; $y < $n - 1; $y++) {
        for ($x = 0; $x < $n - 1; $x++) {
            $v = $m[$y][$x];
            if ($v === $m[$y][$x + 1] && $v === $m[$y + 1][$x] && $v === $m[$y + 1][$x + 1]) {
                $ceza += 3;
            }
        }
    }

    // 3. kural: 1:1:3:1:1 deseni (bulucu benzeri)
    $desen1 = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
    $desen2 = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
    for ($i = 0; $i < $n; $i++) {
        for ($j = 0; $j <= $n - 11; $j++) {
            $satir = [];
            $sutun = [];
            for ($k = 0; $k < 11; $k++) {
                $satir[] = $m[$i][$j + $k];
                $sutun[] = $m[$j + $k][$i];
            }
            if ($satir === $desen1 || $satir === $desen2) {
                $ceza += 40;
            }
            if ($sutun === $desen1 || $sutun === $desen2) {
                $ceza += 40;
            }
        }
    }

    // 4. kural: koyu modül oranı
    $koyu = 0;
    foreach ($m as $satir) {
        $koyu += array_sum($satir);
    }
    $oran = (int) (abs($koyu * 100 / ($n * $n) - 50) / 5);
    $ceza += $oran * 10;

    return $ceza;
}

/**
 * Metni karekod matrisine çevirir.
 * @return array<int, array<int, int>> 0/1 matrisi
 */
function qr_matrix(string $metin, string $ecc = 'Q'): array
{
    $ecc = in_array($ecc, ['M', 'Q'], true) ? $ecc : 'Q';
    $bayt = array_values(unpack('C*', $metin));
    $uzunluk = count($bayt);

    // uygun sürümü seç
    $tablolar = qr_tables();
    $ver = 0;
    foreach ($tablolar as $v => $seviyeler) {
        if ($uzunluk <= $seviyeler[$ecc][3]) {
            $ver = $v;
            break;
        }
    }
    if ($ver === 0) {
        // veri çok uzun: daha düşük hata düzeltmeyle son bir deneme
        foreach ($tablolar as $v => $seviyeler) {
            if ($uzunluk <= $seviyeler['M'][3]) {
                $ver = $v;
                $ecc = 'M';
                break;
            }
        }
    }
    if ($ver === 0) {
        throw new RuntimeException('Karekod için veri çok uzun');
    }

    [$toplamCS, $ecPerBlok, $bloklar] = $tablolar[$ver][$ecc];
    $veriCS = $toplamCS - $ecPerBlok * array_sum(array_column($bloklar, 0));

    /* --- bit dizisi --- */
    $bits = '';
    $bits .= '0100';                                            // bayt kipi
    $bits .= str_pad(decbin($uzunluk), $ver <= 9 ? 8 : 16, '0', STR_PAD_LEFT);
    foreach ($bayt as $b) {
        $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
    }
    $hedef = $veriCS * 8;
    $bits .= str_repeat('0', min(4, max(0, $hedef - strlen($bits))));   // sonlandırıcı
    if (strlen($bits) % 8) {
        $bits .= str_repeat('0', 8 - strlen($bits) % 8);
    }
    $dolgu = ['11101100', '00010001'];
    $i = 0;
    while (strlen($bits) < $hedef) {
        $bits .= $dolgu[$i++ % 2];
    }

    $kodlar = [];
    for ($i = 0; $i < strlen($bits); $i += 8) {
        $kodlar[] = bindec(substr($bits, $i, 8));
    }

    /* --- blokları ayır, hata düzeltme üret --- */
    $veriBloklari = [];
    $ecBloklari = [];
    $p = 0;
    foreach ($bloklar as [$adet, $uzunlukBlok]) {
        for ($k = 0; $k < $adet; $k++) {
            $blok = array_slice($kodlar, $p, $uzunlukBlok);
            $p += $uzunlukBlok;
            $veriBloklari[] = $blok;
            $ecBloklari[] = qr_rs($blok, $ecPerBlok);
        }
    }

    // sıralı birleştirme
    $sira = [];
    $enUzun = max(array_map('count', $veriBloklari));
    for ($i = 0; $i < $enUzun; $i++) {
        foreach ($veriBloklari as $b) {
            if (isset($b[$i])) {
                $sira[] = $b[$i];
            }
        }
    }
    for ($i = 0; $i < $ecPerBlok; $i++) {
        foreach ($ecBloklari as $b) {
            if (isset($b[$i])) {
                $sira[] = $b[$i];
            }
        }
    }

    /* --- matris --- */
    $n = 17 + 4 * $ver;
    $m = array_fill(0, $n, array_fill(0, $n, 0));
    $ayrilmis = array_fill(0, $n, array_fill(0, $n, false));

    $koy = function (int $y, int $x, int $v) use (&$m, &$ayrilmis, $n): void {
        if ($y >= 0 && $y < $n && $x >= 0 && $x < $n) {
            $m[$y][$x] = $v;
            $ayrilmis[$y][$x] = true;
        }
    };

    // bulucu desenleri + ayırıcılar
    foreach ([[0, 0], [0, $n - 7], [$n - 7, 0]] as [$oy, $ox]) {
        for ($y = -1; $y <= 7; $y++) {
            for ($x = -1; $x <= 7; $x++) {
                $icinde = $y >= 0 && $y <= 6 && $x >= 0 && $x <= 6;
                $koyu = $icinde && ($y === 0 || $y === 6 || $x === 0 || $x === 6
                    || ($y >= 2 && $y <= 4 && $x >= 2 && $x <= 4));
                $koy($oy + $y, $ox + $x, $koyu ? 1 : 0);
            }
        }
    }

    // zamanlama desenleri
    for ($i = 8; $i < $n - 8; $i++) {
        $koy(6, $i, $i % 2 === 0 ? 1 : 0);
        $koy($i, 6, $i % 2 === 0 ? 1 : 0);
    }

    // hizalama desenleri
    $merkezler = qr_align($ver);
    foreach ($merkezler as $cy) {
        foreach ($merkezler as $cx) {
            // bulucu desenleriyle çakışanları atla
            if (($cy <= 8 && $cx <= 8) || ($cy <= 8 && $cx >= $n - 9) || ($cy >= $n - 9 && $cx <= 8)) {
                continue;
            }
            for ($y = -2; $y <= 2; $y++) {
                for ($x = -2; $x <= 2; $x++) {
                    $koyu = abs($y) === 2 || abs($x) === 2 || ($y === 0 && $x === 0);
                    $koy($cy + $y, $cx + $x, $koyu ? 1 : 0);
                }
            }
        }
    }

    // koyu modül + biçim alanı ayrımı
    $koy($n - 8, 8, 1);
    for ($i = 0; $i < 9; $i++) {
        $koy(8, $i, $m[8][$i] ?? 0);
        $koy($i, 8, $m[$i][8] ?? 0);
    }
    for ($i = 0; $i < 8; $i++) {
        $koy(8, $n - 1 - $i, 0);
        $koy($n - 1 - $i, 8, 0);
    }

    // sürüm bilgisi alanı (v7+)
    if ($ver >= 7) {
        $vb = qr_version_bits($ver);
        for ($i = 0; $i < 18; $i++) {
            $bit = ($vb >> $i) & 1;
            $koy(intdiv($i, 3), $n - 11 + ($i % 3), $bit);
            $koy($n - 11 + ($i % 3), intdiv($i, 3), $bit);
        }
    }

    /* --- veriyi zikzak yerleştir --- */
    $veriBit = '';
    foreach ($sira as $cs) {
        $veriBit .= str_pad(decbin($cs), 8, '0', STR_PAD_LEFT);
    }
    $bitDizi = str_split($veriBit);
    $idx = 0;
    $yukari = true;
    for ($sutun = $n - 1; $sutun > 0; $sutun -= 2) {
        if ($sutun === 6) {
            $sutun = 5;   // zamanlama sütunu atlanır
        }
        for ($k = 0; $k < $n; $k++) {
            $y = $yukari ? $n - 1 - $k : $k;
            foreach ([$sutun, $sutun - 1] as $x) {
                if ($ayrilmis[$y][$x]) {
                    continue;
                }
                $m[$y][$x] = ($bitDizi[$idx] ?? '0') === '1' ? 1 : 0;
                $idx++;
            }
        }
        $yukari = !$yukari;
    }

    /* --- maskeyi seç --- */
    $enIyi = null;
    $enIyiCeza = PHP_INT_MAX;
    for ($mask = 0; $mask < 8; $mask++) {
        $aday = $m;
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if (!$ayrilmis[$y][$x] && qr_mask_test($mask, $y, $x)) {
                    $aday[$y][$x] ^= 1;
                }
            }
        }
        // biçim bilgisini yaz (ISO/IEC 18004 §8.9 — iki kopya)
        $fb = qr_format_bits($ecc, $mask);
        for ($i = 0; $i < 15; $i++) {
            $bit = ($fb >> $i) & 1;
            // 1. kopya: sol üst köşenin çevresi
            if ($i <= 5) {
                $aday[$i][8] = $bit;
            } elseif ($i === 6) {
                $aday[7][8] = $bit;
            } elseif ($i === 7) {
                $aday[8][8] = $bit;
            } elseif ($i === 8) {
                $aday[8][7] = $bit;
            } else {
                $aday[8][14 - $i] = $bit;
            }
            // 2. kopya: sağ üst ve sol alt
            if ($i < 8) {
                $aday[8][$n - 1 - $i] = $bit;
            } else {
                $aday[$n - 15 + $i][8] = $bit;
            }
        }
        $aday[$n - 8][8] = 1;   // daima koyu modül

        $ceza = qr_penalty($aday, $n);
        if ($ceza < $enIyiCeza) {
            $enIyiCeza = $ceza;
            $enIyi = $aday;
        }
    }

    return $enIyi;
}

/**
 * Karekodu SVG olarak döndürür.
 * @param int $kenar Piksel cinsinden hedef genişlik
 */
function qr_svg(string $metin, int $kenar = 160, string $ecc = 'Q', string $renk = '#12101a'): string
{
    $m = qr_matrix($metin, $ecc);
    $n = count($m);
    $sessiz = 4;                       // zorunlu sessiz alan
    $toplam = $n + $sessiz * 2;

    $yol = '';
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if ($m[$y][$x]) {
                $yol .= 'M' . ($x + $sessiz) . ' ' . ($y + $sessiz) . 'h1v1h-1z';
            }
        }
    }

    return '<svg class="qr" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $toplam . ' ' . $toplam . '"'
        . ' width="' . $kenar . '" height="' . $kenar . '" shape-rendering="crispEdges" role="img"'
        . ' aria-label="Sipariş durumu karekodu">'
        . '<rect width="' . $toplam . '" height="' . $toplam . '" fill="#ffffff"/>'
        . '<path d="' . $yol . '" fill="' . $renk . '"/>'
        . '</svg>';
}
