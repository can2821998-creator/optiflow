<?php
declare(strict_types=1);

/* ==========================================================================
   SGK / Medula Optik köprüsü — metin çözümleyici
   --------------------------------------------------------------------------
   Bu dosya SGK'ya hiçbir istek atmaz, hiçbir kimlik bilgisi saklamaz.
   Yalnızca personelin SGK ekranında GÖRDÜĞÜ ve kopyaladığı (ya da köprü
   eklentisinin o ekrandan aktardığı) metni çözümleyip reçete alanlarına
   dönüştürür. Çözümleme sonucu her zaman personele önizleme olarak gösterilir;
   onaylamadan hiçbir değer siparişe yazılmaz.

   Medula Optik ekranının yapısı (gerçek ekrandan):

     HASTA BİLGİLERİ
       T.C. Kimlik No ⇥ 12345678901   ⇥ E-Reçete No ⇥ 1A2B3C
       Adı            ⇥ AYŞE          ⇥ Soyadı      ⇥ YILMAZ
     REÇETE BİLGİLERİ
       Reçete Tarihi  ⇥ 16/09/2026    ⇥ Protokol No ⇥ «12345»
       Tesis Adı      ⇥ … DEVLET HASTANESİ ⇥ Doktor Adı ⇥ MEHMET DEMİR
     UZAK GÖZLÜK
       SAĞ CAM
         Cam ⇥ +/- ⇥ Sferik ⇥ +/- ⇥ Silendirik ⇥ Aks      ← başlık satırı
         «evet» ⇥ «+» ⇥ «1,25» ⇥ «-» ⇥ «0,75» ⇥ «45»      ← kutu değerleri
       SOL CAM …
     YAKIN GÖZLÜK  (aynı düzen; yakın sferik − uzak sferik = ADD)

   Değerler <input>/<select> kutularının içinde olduğu için köprü eklentisi
   onları «…» işaretiyle, hücreleri sekmeyle, satırları satırbaşıyla aktarır.
   Çözümleyici hem bu biçimi hem de düz kopyala-yapıştır metnini anlar.
   ========================================================================== */

/** Türkçe büyük harfe çevirir (ı/İ sorunsuz). */
function sgk_upper(string $s): string
{
    return mb_strtoupper(strtr($s, ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
}

/** Etiket karşılaştırması için sadeleştirir: "Reçete Tarihi" → "RECETE TARIHI". */
function sgk_norm(string $s): string
{
    $s = strtr($s, [
        'İ' => 'I', 'ı' => 'I', 'i' => 'I', 'I' => 'I',
        'Ş' => 'S', 'ş' => 'S', 'Ğ' => 'G', 'ğ' => 'G',
        'Ü' => 'U', 'ü' => 'U', 'Ö' => 'O', 'ö' => 'O',
        'Ç' => 'C', 'ç' => 'C', 'Â' => 'A', 'â' => 'A',
    ]);
    $s = mb_strtoupper($s, 'UTF-8');
    $s = preg_replace('/[^A-Z0-9+\/?-]+/u', ' ', $s) ?? $s;
    return trim(preg_replace('/\s{2,}/u', ' ', $s) ?? $s);
}

/** Sayıyı normalleştirir: "−1,50" → "-1.50" */
function sgk_num(string $s): string
{
    $s = str_replace(["\u{2212}", "\u{2013}", "\u{2014}"], '-', trim($s));
    $s = str_replace(',', '.', $s);
    return preg_replace('/[^0-9.\-+]/', '', $s) ?? '';
}

/** Diyoptri değeri mi? (-30 .. +30) */
function sgk_is_diyoptri(string $s): bool
{
    if ($s === '' || !is_numeric($s)) {
        return false;
    }
    $v = (float) $s;
    return $v >= -30 && $v <= 30;
}

/** Aks değeri mi? (0–180 tam sayı) */
function sgk_is_aks(string $s): bool
{
    if ($s === '' || !preg_match('/^\d{1,3}$/', $s)) {
        return false;
    }
    return (int) $s >= 0 && (int) $s <= 180;
}

/** İşaret kutusu + değer → "+1.25" / "-0.75" (geçersizse ''). */
function sgk_diyoptri(string $isaret, string $deger): string
{
    $n = sgk_num($deger);
    if ($n === '' || !is_numeric($n)) {
        return '';
    }
    $v = (float) $n;
    if ($isaret === '-' && $v > 0) {
        $v = -$v;
    }
    if ($v < -30 || $v > 30) {
        return '';
    }
    if (abs($v) < 0.001) {
        return '0.00';
    }
    return ($v > 0 ? '+' : '-') . number_format(abs($v), 2, '.', '');
}

/** "16/09/2026" → "16.09.2026" (tanınmazsa ''). */
function sgk_tarih_tr(string $s): string
{
    if (preg_match('/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', trim($s), $m)) {
        return sprintf('%02d.%02d.%04d', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    return '';
}

/**
 * Bir hücrenin hangi etiket olduğunu söyler; etiket değilse ''.
 * 'atla' = tanınan ama kullanılmayan etiket (değerinin başka alana yazılmaması için).
 */
function sgk_etiket_kodu(string $hucre): string
{
    $n = sgk_norm($hucre);
    if ($n === '' || mb_strlen($n) > 40) {
        return '';
    }
    static $tablo = null;
    if ($tablo === null) {
        $tablo = [
            '/KAREKOD/'                                  => 'atla',
            '/^MALZEME TESLIM/'                          => 'atla',
            '/^(MURACAAT|PROVIZYON|RECETE TIPI)/'        => 'atla',
            '/^(TESIS KODU|DOKTOR TESCIL)/'              => 'atla',
            '/^(SOSYAL GUVENLIK|CINSIYET|YAKINLIK)/'     => 'atla',
            '/^(SIGORTALI TURU|KAPSAM|EMEKLI|KATILIM)/'  => 'atla',
            '/CERCEVE/'                                  => 'atla',
            '/^T ?C KIMLIK NO$/'                         => 'tc',
            '/^E ?-? ?RECETE NO$/'                       => 'erecete',
            '/^(HASTA ADI SOYADI|HASTA ADI|ADI SOYADI|AD SOYAD)$/' => 'hasta',
            '/^ADI$/'                                    => 'ad',
            '/^SOYADI$/'                                 => 'soyad',
            '/^RECETE TARIHI$/'                          => 'recete_tarihi',
            '/^(RECETE TESHIS|TESHIS|TANI)$/'            => 'teshis',
            '/^TESIS ADI$/'                              => 'tesis',
            '/^(DOKTOR ADI|HEKIM|DOKTOR)$/'              => 'doktor',
            '/^PROTOKOL NO$/'                            => 'protokol',
            '/^HASTA TERCIHI$/'                          => 'tercih',
            '/^GOZLUK TURU$/'                            => 'gozluk_turu',
            '/^RAPOR (TAKIP )?NO$/'                      => 'rapor_no',
            '/^RAPOR TARIHI$/'                           => 'rapor_tarihi',
            '/^YAS$/'                                    => 'yas',
            '/^(PD|PUPILLA MESAFESI)$/'                  => 'pd',
            '/^SAG (CAM|GOZ)$/'                          => 'goz_sag',
            '/^SOL (CAM|GOZ)$/'                          => 'goz_sol',
            '/^UZAK( GOZLUK)?$/'                         => 'bolum_uzak',
            '/^YAKIN( GOZLUK)?$/'                        => 'bolum_yakin',
            '/^\+ ?\/ ?-$/'                              => 'isaret',
            '/^SFER/'                                    => 'sferik',
            '/^(SILENDIRIK|SILINDIRIK|SILENDRIK|CYL)$/'  => 'silendirik',
            '/^(AKS|EKSEN|AXIS)$/'                       => 'aks',
            '/^(ADD|ILAVE)$/'                            => 'add',
            '/^CAM$/'                                    => 'cam',
        ];
    }
    foreach ($tablo as $desen => $kod) {
        if (preg_match($desen, $n)) {
            return $kod;
        }
    }
    return '';
}

/** Düz metin içindeki etiketleri ayırmak için (elle yapıştırılan metinler). */
function sgk_etiket_deseni(): string
{
    $harf = 'A-Za-zÇĞİÖŞÜçğıöşü';
    $etiketler = 'T\.?\s?C\.?\s?Kimlik\s?No|E\s?-?\s?Reçete\s?No|Reçete\s?Tarihi|Reçete\s?Teşhis'
        . '|Tesis\s?Adı|Tesis\s?Kodu|Doktor\s?Adı|Doktor\s?Tescil\s?No|Protokol\s?No'
        . '|Hasta\s?Tercihi|Gözlük\s?Türü|Hasta\s?Adı\s?Soyadı|Hasta\s?Adı|Adı\s?Soyadı'
        . '|Rapor\s?Takip\s?No|Rapor\s?Tarihi|Rapor\s?No|Sağ\s?Cam|Sol\s?Cam|Sağ\s?Göz|Sol\s?Göz'
        . '|Uzak\s?Gözlük|Yakın\s?Gözlük|Sferik|Silendirik|Silindirik|Eksen|Aks|Adı|Soyadı|Cam|PD'
        . '|\+\s?\/\s?\-';
    return '/(?<![' . $harf . '])(' . $etiketler . ')(?![' . $harf . '])/u';
}

/** Bir metin parçasını etiket/değer belirteçlerine böler. */
function sgk_metin_hucreleri(string $parca): array
{
    // "PD: 62" gibi yapıştırmalarda etiketten sonra kalan iki nokta/çizgi
    // Not: "-1,50" bozulmasın diye çizgi yalnızca boşluk geldiğinde ayraç sayılır.
    $deger = static fn(string $s): string => trim(preg_replace('/^(?::\s*|[-–—]\s+)/u', '', trim($s)) ?? $s);

    $cikti = [];
    foreach (preg_split('/\t|\s{2,}/u', $parca) ?: [] as $hucre) {
        $hucre = trim($hucre);
        if ($hucre === '') {
            continue;
        }
        $kod = sgk_etiket_kodu($hucre);
        if ($kod !== '') {
            $cikti[] = ['tip' => 'e', 'kod' => $kod, 'ham' => $hucre];
            continue;
        }
        // Etiket ve değer tek hücrede bitişikse ("Adı AYŞE Soyadı YILMAZ")
        $bolum = preg_split(sgk_etiket_deseni(), $hucre, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $bolum = array_values(array_filter(array_map('trim', $bolum), static fn($s) => $s !== ''));
        if (count($bolum) > 1) {
            foreach ($bolum as $p) {
                $k = sgk_etiket_kodu($p);
                if ($k !== '') {
                    $cikti[] = ['tip' => 'e', 'kod' => $k, 'ham' => $p];
                } elseif ($deger($p) !== '') {
                    $cikti[] = ['tip' => 'v', 'deger' => $deger($p)];
                }
            }
            continue;
        }
        $cikti[] = ['tip' => 'v', 'deger' => $deger($hucre)];
    }
    return $cikti;
}

/** Tüm metni sıralı etiket/değer akışına çevirir. */
function sgk_akis(string $metin): array
{
    $akis = [];
    foreach (explode("\n", $metin) as $satir) {
        if (trim($satir) === '') {
            continue;
        }
        // «…» köprü işaretleri: içerikleri her zaman değerdir (boş olsa bile).
        $parcalar = preg_split('/«([^»]*)»/u', $satir, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        foreach ($parcalar as $i => $p) {
            if ($i % 2 === 1) {
                $akis[] = ['tip' => 'v', 'deger' => trim($p), 'kutu' => true];
            } else {
                foreach (sgk_metin_hucreleri($p) as $t) {
                    $akis[] = $t;
                }
            }
        }
    }
    return $akis;
}

/** Bir göz kutusundaki etiket sırası + değer sırasını eşleyip [sph,cyl,aks] üretir. */
function sgk_blok_coz(array $etiketler, array $degerler): array
{
    $sonuc = ['sph' => '', 'cyl' => '', 'aks' => '', 'add' => '', 'cam' => ''];
    if (!$degerler) {
        return $sonuc;
    }
    $n = min(count($etiketler), count($degerler));
    $isaret = '';
    for ($i = 0; $i < $n; $i++) {
        $kod = $etiketler[$i];
        $v = trim((string) $degerler[$i]);
        switch ($kod) {
            case 'isaret':
                $isaret = str_contains($v, '-') ? '-' : (str_contains($v, '+') ? '+' : '');
                break;
            case 'sferik':
                $sonuc['sph'] = sgk_diyoptri($isaret, $v);
                $isaret = '';
                break;
            case 'silendirik':
                $sonuc['cyl'] = sgk_diyoptri($isaret, $v);
                $isaret = '';
                break;
            case 'add':
                $sonuc['add'] = sgk_diyoptri($isaret, $v);
                $isaret = '';
                break;
            case 'aks':
                $a = sgk_num($v);
                if (sgk_is_aks($a)) {
                    $sonuc['aks'] = (string) (int) $a;
                }
                break;
            case 'cam':
                $sonuc['cam'] = $v;
                break;
        }
    }
    // Etiket yakalanamadıysa satırdaki sayılardan tahmin et.
    if ($sonuc['sph'] === '' && $degerler) {
        [$a, $b, $c] = sgk_goz_satiri(implode(' ', $degerler));
        $sonuc['sph'] = $a;
        $sonuc['cyl'] = $sonuc['cyl'] !== '' ? $sonuc['cyl'] : $b;
        $sonuc['aks'] = $sonuc['aks'] !== '' ? $sonuc['aks'] : $c;
    }
    return $sonuc;
}

/**
 * Bir satırdan göz değerlerini çıkarır: [sph, cyl, aks, add]
 * (Yalnızca yedek yol: etiketsiz, düz yapıştırılmış satırlar için.)
 */
function sgk_goz_satiri(string $satir): array
{
    $temiz = str_replace(['°', 'DPT', 'dpt', 'Dpt'], ' ', $satir);
    preg_match_all('/[+\-\x{2212}]?\s?\d{1,3}(?:[.,]\d{1,2})?/u', $temiz, $m);

    $sayilar = [];
    foreach ($m[0] as $ham) {
        $n = sgk_num($ham);
        if ($n === '' || $n === '-' || $n === '+') {
            continue;
        }
        $sayilar[] = $n;
    }
    if (!$sayilar) {
        return ['', '', '', ''];
    }

    $sph = $cyl = $aks = $add = '';
    $ondalikli = [];
    $tam = [];
    foreach ($sayilar as $n) {
        if (str_contains($n, '.')) {
            $ondalikli[] = $n;
        } else {
            $tam[] = $n;
        }
    }
    if (isset($ondalikli[0]) && sgk_is_diyoptri($ondalikli[0])) {
        $sph = $ondalikli[0];
    }
    if (isset($ondalikli[1]) && sgk_is_diyoptri($ondalikli[1])) {
        $cyl = $ondalikli[1];
    }
    if (isset($ondalikli[2]) && sgk_is_diyoptri($ondalikli[2])) {
        $add = $ondalikli[2];
    }
    foreach ($tam as $n) {
        if ($aks === '' && sgk_is_aks($n) && (int) $n > 0) {
            $aks = (string) (int) $n;
        }
    }
    if ($sph === '') {
        $sph = $sayilar[0];
        if (isset($sayilar[1]) && sgk_is_diyoptri($sayilar[1])) {
            $cyl = $sayilar[1];
        }
        if (isset($sayilar[2]) && sgk_is_aks($sayilar[2])) {
            $aks = (string) (int) $sayilar[2];
        }
    }
    return [$sph, $cyl, $aks, $add];
}

/** Etiketli alan yakalar (yedek yol): "Rapor No : 123456" */
function sgk_etiket(string $metin, array $etiketler, string $desen = '(.+)'): string
{
    foreach ($etiketler as $et) {
        $q = preg_quote($et, '/');
        if (preg_match('/' . $q . '\s*[:\-]?\s*' . $desen . '/ui', $metin, $m)) {
            $deger = trim($m[count($m) - 1]);
            $deger = trim(preg_split('/\s{2,}|\t|\r|\n/u', $deger)[0] ?? '');
            if ($deger !== '') {
                return mb_substr($deger, 0, 80);
            }
        }
    }
    return '';
}

/**
 * SGK ekranından gelen metni reçete alanlarına çevirir.
 *
 * @return array{
 *   sag: array{sph:string,cyl:string,aks:string,add:string},
 *   sol: array{sph:string,cyl:string,aks:string,add:string},
 *   yakin_sag: array{sph:string,cyl:string,aks:string},
 *   yakin_sol: array{sph:string,cyl:string,aks:string},
 *   pd:string, hasta:string, ad:string, soyad:string, tc:string, erecete:string,
 *   doktor:string, tesis:string, teshis:string, tercih:string, yas:string,
 *   rapor_no:string, rapor_tarihi:string, recete_tarihi:string, tani:string,
 *   bulunan:int, satirlar:array
 * }
 */
function sgk_parse(string $metin): array
{
    $metin = str_replace(["\r\n", "\r"], "\n", $metin);
    $satirlar = array_values(array_filter(
        array_map(static fn($s) => trim(preg_replace('/[ ]{2,}/u', '  ', $s) ?? $s), explode("\n", $metin)),
        static fn($s) => $s !== ''
    ));

    $sonuc = [
        'sag' => ['sph' => '', 'cyl' => '', 'aks' => '', 'add' => ''],
        'sol' => ['sph' => '', 'cyl' => '', 'aks' => '', 'add' => ''],
        'yakin_sag' => ['sph' => '', 'cyl' => '', 'aks' => ''],
        'yakin_sol' => ['sph' => '', 'cyl' => '', 'aks' => ''],
        'pd' => '', 'hasta' => '', 'ad' => '', 'soyad' => '', 'tc' => '', 'erecete' => '',
        'doktor' => '', 'tesis' => '', 'teshis' => '', 'tercih' => '', 'yas' => '',
        'rapor_no' => '', 'rapor_tarihi' => '', 'recete_tarihi' => '', 'tani' => '',
        'bulunan' => 0, 'satirlar' => $satirlar,
    ];

    $akis = sgk_akis($metin);

    /* ---------- 1) Etiketli tekil alanlar ----------
       Aynı etiket sayfada birden çok kez geçebilir (sol menüdeki "E-Reçete No
       Sorgula" gibi). Bu yüzden her etiket için tüm adaylar toplanır ve alanın
       biçimine uyan ilk aday seçilir. */
    $alan = [];
    foreach ($akis as $i => $t) {
        if ($t['tip'] !== 'e') {
            continue;
        }
        $kod = $t['kod'];
        if (in_array($kod, ['atla', 'isaret', 'cam', 'sferik', 'silendirik', 'aks', 'add', 'goz_sag', 'goz_sol', 'bolum_uzak', 'bolum_yakin'], true)) {
            continue;
        }
        // Etiketten sonraki ilk belirteç değer ise al; etiket geldiyse alan boştur.
        $sonraki = $akis[$i + 1] ?? null;
        if ($sonraki && $sonraki['tip'] === 'v' && $sonraki['deger'] !== '') {
            $alan[$kod][] = mb_substr($sonraki['deger'], 0, 120);
        }
    }

    /** İlk geçerli adayı seçer. */
    $sec = static function (string $kod, callable $gecerli) use ($alan): string {
        foreach ($alan[$kod] ?? [] as $aday) {
            $aday = trim($aday);
            if ($aday !== '' && $gecerli($aday)) {
                return $aday;
            }
        }
        return '';
    };
    $isim = static fn(string $s): bool => (bool) preg_match('/^[^\d]{2,40}$/u', $s) && !preg_match('/[:?]/u', $s);
    $dolu = static fn(string $s): bool => $s !== '';

    $tcAday = $sec('tc', static fn($s) => mb_strlen(preg_replace('/\D/', '', $s) ?? '') === 11);
    $sonuc['tc']       = preg_replace('/\D/', '', $tcAday) ?: '';
    $sonuc['erecete']  = sgk_upper($sec('erecete', static fn($s) => (bool) preg_match('/^(?=[^\d]*\d)[A-Za-z0-9]{4,12}$/', $s)));
    $sonuc['ad']       = $sec('ad', $isim);
    $sonuc['soyad']    = $sec('soyad', $isim);
    $sonuc['doktor']   = $sec('doktor', $isim);
    $sonuc['tesis']    = $sec('tesis', $dolu);
    $sonuc['teshis']   = $sec('teshis', static fn($s) => mb_strlen($s) <= 60);
    $sonuc['tani']     = $sonuc['teshis'];
    $sonuc['tercih']   = $sec('tercih', $dolu);
    $sonuc['rapor_no'] = $sec('rapor_no', static fn($s) => (bool) preg_match('/^[0-9]{3,20}$/', $s));
    $sonuc['yas']      = $sec('yas', static fn($s) => (bool) preg_match('/^\d{1,3}$/', $s));
    $sonuc['pd']       = str_replace(',', '.', $sec('pd', static fn($s) => (bool) preg_match('/^\d{2,3}([.,]\d)?$/', $s)));
    $sonuc['recete_tarihi'] = sgk_tarih_tr($sec('recete_tarihi', static fn($s) => sgk_tarih_tr($s) !== ''));
    $sonuc['rapor_tarihi']  = sgk_tarih_tr($sec('rapor_tarihi', static fn($s) => sgk_tarih_tr($s) !== ''));

    if ($sonuc['ad'] !== '' || $sonuc['soyad'] !== '') {
        $sonuc['hasta'] = trim($sonuc['ad'] . ' ' . $sonuc['soyad']);
    } elseif ($sec('hasta', $isim) !== '') {
        $sonuc['hasta'] = $sec('hasta', $isim);
        $p = preg_split('/\s+/u', $sonuc['hasta']) ?: [];
        if (count($p) > 1) {
            $sonuc['soyad'] = (string) array_pop($p);
            $sonuc['ad'] = implode(' ', $p);
        } else {
            $sonuc['ad'] = $sonuc['hasta'];
        }
    }

    /* ---------- 2) Göz kutuları (UZAK / YAKIN × SAĞ / SOL) ---------- */
    $bloklar = [];
    $bolum = 'uzak';
    $acik = null;                       // ['bolum','goz','etiketler','degerler']
    $sonEtiket = '';
    $gozEtiketiVar = false;

    $kapat = static function () use (&$acik, &$bloklar): void {
        if ($acik && $acik['degerler']) {
            $bloklar[] = $acik;
        }
        $acik = null;
    };

    foreach ($akis as $t) {
        if ($t['tip'] === 'e') {
            $kod = $t['kod'];
            $sonEtiket = $kod;
            if ($kod === 'bolum_uzak' || $kod === 'bolum_yakin') {
                $kapat();
                $bolum = $kod === 'bolum_yakin' ? 'yakin' : 'uzak';
                continue;
            }
            if ($kod === 'goz_sag' || $kod === 'goz_sol') {
                $kapat();
                $gozEtiketiVar = true;
                $acik = ['bolum' => $bolum, 'goz' => $kod === 'goz_sag' ? 'sag' : 'sol', 'etiketler' => [], 'degerler' => []];
                continue;
            }
            if ($acik && in_array($kod, ['cam', 'isaret', 'sferik', 'silendirik', 'aks', 'add'], true)) {
                $acik['etiketler'][] = $kod;
            }
            continue;
        }
        if ($acik && $sonEtiket !== 'atla') {
            $acik['degerler'][] = $t['deger'];
        }
    }
    $kapat();

    foreach ($bloklar as $b) {
        $c = sgk_blok_coz($b['etiketler'], $b['degerler']);
        if ($b['bolum'] === 'uzak') {
            $hedef = $b['goz'] === 'sag' ? 'sag' : 'sol';
            if ($sonuc[$hedef]['sph'] === '') {
                $sonuc[$hedef]['sph'] = $c['sph'];
                $sonuc[$hedef]['cyl'] = $c['cyl'];
                $sonuc[$hedef]['aks'] = $c['aks'];
                if ($c['add'] !== '') {
                    $sonuc[$hedef]['add'] = $c['add'];
                }
            }
        } else {
            $hedef = $b['goz'] === 'sag' ? 'yakin_sag' : 'yakin_sol';
            if ($sonuc[$hedef]['sph'] === '') {
                $sonuc[$hedef]['sph'] = $c['sph'];
                $sonuc[$hedef]['cyl'] = $c['cyl'];
                $sonuc[$hedef]['aks'] = $c['aks'];
            }
        }
    }

    /* ---------- 3) ADD: yakın sferik − uzak sferik ---------- */
    foreach ([['sag', 'yakin_sag'], ['sol', 'yakin_sol']] as [$uzak, $yakin]) {
        if ($sonuc[$uzak]['add'] !== '' || $sonuc[$uzak]['sph'] === '' || $sonuc[$yakin]['sph'] === '') {
            continue;
        }
        $fark = (float) $sonuc[$yakin]['sph'] - (float) $sonuc[$uzak]['sph'];
        if ($fark > 0.124 && $fark <= 4.0 && fmod(round($fark * 100), 25) === 0.0) {
            $sonuc[$uzak]['add'] = '+' . number_format($fark, 2, '.', '');
        }
    }

    /* ---------- 4) Yedek yollar (etiketsiz, elle yapıştırılmış metin) ----------
       Ekranda SAĞ/SOL CAM kutuları varken değer çıkmadıysa, sayfada gerçekten
       değer yok demektir (kutular kopyalanmaz). Rastgele sayı toplamak yerine
       alanı boş bırakırız; kullanıcıya köprü eklentisi önerilir. */
    if (!$gozEtiketiVar && $sonuc['sag']['sph'] === '' && $sonuc['sol']['sph'] === '') {
        foreach ($satirlar as $i => $satir) {
            $b = sgk_norm($satir);
            $sayiVar = (bool) preg_match('/\d/', $satir);
            if ($sonuc['sag']['sph'] === '' && preg_match('/\bSAG\b|\bOD\b/u', $b)) {
                [$a1, $b1, $c1, $d1] = sgk_goz_satiri($sayiVar ? $satir : ($satirlar[$i + 1] ?? ''));
                if ($a1 !== '') {
                    $sonuc['sag'] = ['sph' => $a1, 'cyl' => $b1, 'aks' => $c1, 'add' => $d1];
                }
            }
            if ($sonuc['sol']['sph'] === '' && preg_match('/\bSOL\b|\bOS\b/u', $b)) {
                [$a2, $b2, $c2, $d2] = sgk_goz_satiri($sayiVar ? $satir : ($satirlar[$i + 1] ?? ''));
                if ($a2 !== '') {
                    $sonuc['sol'] = ['sph' => $a2, 'cyl' => $b2, 'aks' => $c2, 'add' => $d2];
                }
            }
        }
    }
    if ($sonuc['hasta'] === '') {
        $sonuc['hasta'] = sgk_etiket($metin, ['Hasta Adı Soyadı', 'Ad Soyad', 'Hasta Adı', 'Adı Soyadı']);
    }
    if ($sonuc['doktor'] === '') {
        $sonuc['doktor'] = sgk_etiket($metin, ['Doktor Adı', 'Hekim', 'Doktor']);
    }
    if ($sonuc['rapor_no'] === '') {
        $sonuc['rapor_no'] = sgk_etiket($metin, ['Rapor Takip No', 'Rapor No', 'Rapor Numarası'], '([0-9]{3,20})');
    }
    if ($sonuc['recete_tarihi'] === '' && preg_match('/(REÇETE|RECETE)[^\n]{0,40}?(\d{2}[.\/]\d{2}[.\/]\d{4})/ui', $metin, $m)) {
        $sonuc['recete_tarihi'] = sgk_tarih_tr($m[2]);
    }

    /* ---------- 5) Ne kadarını bulduk? ---------- */
    $say = 0;
    foreach (['sag', 'sol'] as $goz) {
        foreach (['sph', 'cyl', 'aks', 'add'] as $k) {
            if ($sonuc[$goz][$k] !== '') {
                $say++;
            }
        }
    }
    foreach (['yakin_sag', 'yakin_sol'] as $goz) {
        if ($sonuc[$goz]['sph'] !== '') {
            $say++;
        }
    }
    foreach (['hasta', 'tc', 'erecete', 'doktor', 'tesis', 'recete_tarihi', 'teshis', 'rapor_no', 'pd'] as $k) {
        if ($sonuc[$k] !== '') {
            $say++;
        }
    }
    $sonuc['bulunan'] = $say;

    return $sonuc;
}

/** Reçetedeki "Hasta Tercihi" → sistemdeki cam tasarımı anahtarı. */
function sgk_lens_design(array $cozum): string
{
    $t = sgk_norm($cozum['tercih'] ?? '');
    $yakinVar = ($cozum['yakin_sag']['sph'] ?? '') !== '' || ($cozum['yakin_sol']['sph'] ?? '') !== '';
    if (str_contains($t, 'PROGRES') || str_contains($t, 'COK ODAK')) {
        return 'progressive';
    }
    if (str_contains($t, 'BIFOKAL') || str_contains($t, 'CIFT ODAK')) {
        return 'bifokal';
    }
    return $yakinVar ? 'ayri_uzak_yakin' : 'tek_odak_uzak';
}

/** İsim karşılaştırma anahtarı: "AYŞE  Yılmaz" → "AYSE YILMAZ". */
function sgk_ad_anahtar(string $s): string
{
    return sgk_norm($s);
}

/**
 * Türkçe harfleri joker yapan LIKE deseni: "ŞİRİN" → "__R_N".
 * MySQL'in harf katlama kuralları İ/ı için güvenilir olmadığından gerekli.
 */
function sgk_like_deseni(string $s): string
{
    $s = trim($s);
    if ($s === '') {
        return '';
    }
    $s = str_replace(['%', '_'], ['\\%', '\\_'], $s);
    $out = '';
    $n = mb_strlen($s);
    for ($i = 0; $i < $n; $i++) {
        $ch = mb_substr($s, $i, 1);
        $out .= (mb_strpos('İIıiŞşĞğÜüÖöÇç', $ch) !== false) ? '_' : $ch;
    }
    return $out;
}

/**
 * Reçetedeki hastaya uyan müşterileri bulur (en olası önce).
 * Her kayda açık siparişleri de eklenir. Eşleşme kesin değildir; ekranda
 * personel onaylar.
 *
 * @return array<int, array{id:int,first_name:string,last_name:string,phone:string,puan:int,siparisler:array}>
 */
function sgk_musteri_bul(string $ad, string $soyad): array
{
    $ad = trim($ad);
    $soyad = trim($soyad);
    if ($ad === '' && $soyad === '') {
        return [];
    }

    $adaylar = [];
    if ($soyad !== '') {
        $adaylar = rows(
            'SELECT id, first_name, last_name, phone, birth_year FROM customers WHERE last_name LIKE ? ORDER BY id DESC LIMIT 80',
            [sgk_like_deseni($soyad)]
        );
    }
    if (!$adaylar && $ad !== '') {
        $adaylar = rows(
            'SELECT id, first_name, last_name, phone, birth_year FROM customers WHERE first_name LIKE ? ORDER BY id DESC LIMIT 80',
            [sgk_like_deseni($ad)]
        );
    }

    $adK = sgk_ad_anahtar($ad);
    $soyadK = sgk_ad_anahtar($soyad);
    $bulunan = [];
    foreach ($adaylar as $c) {
        $puan = 0;
        if ($soyadK !== '' && sgk_ad_anahtar((string) $c['last_name']) === $soyadK) {
            $puan += 2;
        }
        $cAd = sgk_ad_anahtar((string) $c['first_name']);
        if ($adK !== '' && $cAd === $adK) {
            $puan += 2;
        } elseif ($adK !== '' && $cAd !== '' && (str_starts_with($cAd, $adK) || str_starts_with($adK, $cAd))) {
            $puan += 1;
        }
        if ($puan >= 3) {
            $c['puan'] = $puan;
            $bulunan[] = $c;
        }
    }
    usort($bulunan, static fn($a, $b) => $b['puan'] <=> $a['puan'] ?: $b['id'] <=> $a['id']);
    $bulunan = array_slice($bulunan, 0, 8);

    foreach ($bulunan as &$c) {
        $c['siparisler'] = rows(
            "SELECT id, order_stage, lens_type, created_at
               FROM orders
              WHERE customer_id = ? AND order_stage NOT IN ('teslim_edildi', 'iptal')
              ORDER BY id DESC LIMIT 5",
            [(int) $c['id']]
        );
    }
    unset($c);

    return $bulunan;
}

/** "18.09.2026" → "2026-09-18" (geçersizse boş) */
function sgk_tarih_db(string $tr): string
{
    $tr = sgk_tarih_tr($tr);
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $tr, $m)) {
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }
    return '';
}

/** Kullanıcının köprü anahtarı — yoksa üretilir. */
function sgk_bridge_token(int $userId): string
{
    $t = (string) scalar('SELECT bridge_token FROM user_accounts WHERE id = ?', [$userId]);
    if ($t !== '') {
        return $t;
    }
    $yeni = bin2hex(random_bytes(20));
    q('UPDATE user_accounts SET bridge_token = ? WHERE id = ?', [$yeni, $userId]);
    return $yeni;
}

/** Köprü anahtarını yeniler. */
function sgk_bridge_yenile(int $userId): string
{
    $yeni = bin2hex(random_bytes(20));
    q('UPDATE user_accounts SET bridge_token = ? WHERE id = ?', [$yeni, $userId]);
    return $yeni;
}

/** 4.12.0 — Ayrıştırmadan e-reçete no ve reçete tarihi (Y-m-d); sgk_incoming satırına eklenir. */
function sgk_gelen_ek_alanlar(array $cozum): array
{
    $no = strtoupper(trim((string) ($cozum['erecete'] ?? '')));
    $tr = (string) ($cozum['recete_tarihi'] ?? '');
    $tarih = preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $tr, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3]) ? "$m[3]-$m[2]-$m[1]" : null;
    return ['erecete' => $no !== '' ? mb_substr($no, 0, 20) : null, 'recete_tarihi' => $tarih];
}
