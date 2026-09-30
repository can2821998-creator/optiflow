<?php
declare(strict_types=1);

/* ==========================================================================
   Çocuk göz gelişim eğrisi — reçete geçmişinden, zaman içindeki değişimi gösterir.

   Her reçetede her göz için "küre eşdeğeri" (SE = küre + silindir/2) hesaplanır ve
   tarih sırasıyla çizilir. Son ≤24 ayda yıllık değişim eşiği aşıyorsa personele
   "veliye göz doktoruna görünmesini hatırlatın" uyarısı çıkar.

   ÖNEMLİ: Bu bir TANI ya da tıbbi görüş DEĞİLDİR; yalnızca kayıtlı reçetelerin görsel
   özetidir. Uyarı eşiği (yılda 0,75 D) varsayılandır; bir göz doktoruna danışarak
   Ayarlar'daki 'gelisim_esik' değeriyle değiştirilebilir. Farklı hekimlerin/cihazların
   ölçümleri arasında küçük farklar olabileceği ekranda da yazar.

   Yalnızca 18 yaş altı müşterilerde gösterilir; ana şemaya dokunmaz (yalnızca okur).
   ========================================================================== */

/** "-2,25", "+1.00", " -0.5 " → float. "PL", "plano", "0", "-" → 0.0 / null. Ayrıştırılamazsa null. */
function gelisim_sayi(?string $ham): ?float
{
    $s = strtolower(trim((string) $ham));
    if ($s === '' || $s === '-' || $s === '—') {
        return null;
    }
    if (in_array($s, ['pl', 'plano', 'plan', 'nötr', 'notr'], true)) {
        return 0.0;
    }
    $s = str_replace(',', '.', str_replace([' ', '−', '–'], ['', '-', '-'], $s));   // Word'den yapıştırılan tipografik eksi de kabul
    return preg_match('/^[+-]?\d+(?:\.\d+)?$/', $s) === 1 ? (float) $s : null;
}

/** Küre eşdeğeri; küre yoksa null, silindir yoksa 0 sayılır. */
function gelisim_se(?string $sph, ?string $cyl): ?float
{
    $s = gelisim_sayi($sph);
    if ($s === null) {
        return null;
    }
    return $s + (gelisim_sayi($cyl) ?? 0.0) / 2;
}

/** Müşterinin reçetelerinden tarih sıralı noktalar: [['tarih'=>'Y-m-d','sag'=>?float,'sol'=>?float], ...] */
function gelisim_noktalar(int $customerId): array
{
    $out = [];
    foreach (rows(
        'SELECT prescription_date, right_sph, right_cyl, left_sph, left_cyl FROM prescription_records
          WHERE customer_id = ? ORDER BY prescription_date ASC, id ASC',
        [$customerId]
    ) as $r) {
        $sag = gelisim_se($r['right_sph'] ?? null, $r['right_cyl'] ?? null);
        $sol = gelisim_se($r['left_sph'] ?? null, $r['left_cyl'] ?? null);
        if (($sag === null && $sol === null) || !strtotime((string) $r['prescription_date'])) {
            continue;   // değeri olmayan ya da tarihi bozuk (0000-00-00) reçete atlanır
        }
        $out[] = ['tarih' => (string) $r['prescription_date'], 'sag' => $sag, 'sol' => $sol];
    }
    return $out;
}

/**
 * Bir göz için son ≤24 aydaki yıllık değişim (D/yıl). En az 90 gün aralıklı iki ölçüm gerekir; yoksa null.
 */
function gelisim_yillik(array $noktalar, string $goz): ?float
{
    $dolu = array_values(array_filter($noktalar, static fn(array $n): bool => $n[$goz] !== null));
    if (count($dolu) < 2) {
        return null;
    }
    $son = $dolu[count($dolu) - 1];
    $sinir = strtotime($son['tarih'] . ' -24 months');
    $pencere = array_values(array_filter($dolu, static fn(array $n): bool => strtotime($n['tarih']) >= $sinir));
    if (count($pencere) < 2) {
        $pencere = $dolu;
    }
    $ilk = $pencere[0];
    $gun = (strtotime($son['tarih']) - strtotime($ilk['tarih'])) / 86400;
    if ($gun < 90) {
        return null;
    }
    return ($son[$goz] - $ilk[$goz]) / ($gun / 365.25);
}

/** "-2,25" biçimi (işaretli, virgüllü). */
function gelisim_yaz(float $v): string
{
    return ($v > 0 ? '+' : '') . number_format($v, 2, ',', '');
}

/**
 * Grafik + uyarı için her şey. En az iki reçete yoksa null.
 * Dönen: w,h, ytick[], xtick[], sag/sol (polyline noktaları), sagNokta/solNokta, yillik, hizli, esik
 */
function gelisim_hazirla(int $customerId): ?array
{
    $n = gelisim_noktalar($customerId);
    if (count($n) < 2) {
        return null;
    }
    $esik = max(0.25, min(3.0, (float) str_replace(',', '.', (string) setting('gelisim_esik', '0.75'))));

    $degerler = [];
    foreach ($n as $p) {
        foreach (['sag', 'sol'] as $g) {
            if ($p[$g] !== null) {
                $degerler[] = $p[$g];
            }
        }
    }
    $lo = floor(min($degerler) * 2) / 2 - 0.5;
    $hi = ceil(max($degerler) * 2) / 2 + 0.5;
    $adim = ($hi - $lo) > 4 ? 1.0 : 0.5;

    $W = 320; $H = 176; $pl = 44; $pr = 12; $pt = 12; $pb = 28;
    $t0 = strtotime($n[0]['tarih']);
    $t1 = strtotime($n[count($n) - 1]['tarih']);
    $xOf = static function (int $t) use ($t0, $t1, $W, $pl, $pr): float {
        return $t1 > $t0 ? $pl + ($t - $t0) / ($t1 - $t0) * ($W - $pl - $pr) : $pl + ($W - $pl - $pr) / 2;
    };
    $yOf = static function (float $v) use ($lo, $hi, $H, $pt, $pb): float {
        return $pt + ($hi - $v) / ($hi - $lo) * ($H - $pt - $pb);
    };

    $ytick = [];
    for ($v = ceil($lo / $adim) * $adim; $v <= $hi + 1e-9; $v += $adim) {
        $ytick[] = ['y' => round($yOf($v), 1), 'etiket' => gelisim_yaz($v)];
    }
    $xtick = [
        ['x' => round($xOf($t0), 1), 'etiket' => date('m.Y', $t0), 'ank' => 'start'],
        ['x' => round($xOf($t1), 1), 'etiket' => date('m.Y', $t1), 'ank' => 'end'],
    ];

    $cizgi = ['sag' => [], 'sol' => []];
    $nokta = ['sag' => [], 'sol' => []];
    foreach ($n as $p) {
        foreach (['sag', 'sol'] as $g) {
            if ($p[$g] !== null) {
                $x = round($xOf(strtotime($p['tarih'])), 1);
                $y = round($yOf($p[$g]), 1);
                $cizgi[$g][] = $x . ',' . $y;
                $nokta[$g][] = ['x' => $x, 'y' => $y, 'etiket' => date('d.m.Y', strtotime($p['tarih'])) . ' · ' . gelisim_yaz($p[$g])];
            }
        }
    }

    $yillik = ['sag' => gelisim_yillik($n, 'sag'), 'sol' => gelisim_yillik($n, 'sol')];
    $hizli = [];
    foreach (['sag' => 'Sağ göz', 'sol' => 'Sol göz'] as $g => $ad) {
        if ($yillik[$g] !== null && abs($yillik[$g]) >= $esik) {
            $hizli[] = $ad . ' yılda yaklaşık ' . gelisim_yaz($yillik[$g]) . ' D';
        }
    }
    $son = $n[count($n) - 1];

    return [
        'w' => $W, 'h' => $H,
        'ytick' => $ytick, 'xtick' => $xtick,
        'sag' => implode(' ', $cizgi['sag']), 'sol' => implode(' ', $cizgi['sol']),
        'sagNokta' => $nokta['sag'], 'solNokta' => $nokta['sol'],
        'sagSon' => $son['sag'] !== null ? gelisim_yaz($son['sag']) : '—',
        'solSon' => $son['sol'] !== null ? gelisim_yaz($son['sol']) : '—',
        'olcum' => count($n),
        'hizli' => $hizli,
        'esik' => number_format($esik, 2, ',', ''),
    ];
}
