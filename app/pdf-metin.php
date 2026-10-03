<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — PDF'ten düz metin (4.16.1)

   Dış kütüphane / komut gerektirmez (paylaşımlı hosting). Medula'nın "fatura
   dökümü" gibi programla üretilmiş PDF'ler içindir; taranmış (resim) PDF'ten
   metin çıkmaz. Desteklenen: FlateDecode akışları, nesne akışları (ObjStm),
   sayfa kaynaklarındaki yazı tipleri ve ToUnicode eşlemeleri (Identity-H dahil),
   Tj / TJ / ' / " işleçleri, satır ayrımı (Td, TD, T*, Tm).
   Şifreli PDF desteklenmez (boş metin döner).
   ========================================================================== */

final class PdfMetin
{
    /** @var array<int, array{dict: string, stream: ?string}> */
    private array $nesneler = [];
    /** @var array<int, array<int, string>> önbellek: nesne no → ToUnicode eşlemesi */
    private array $cmapOnbellek = [];

    public static function oku(string $pdf, int $sayfaSiniri = 200): string
    {
        if (!str_starts_with(ltrim(substr($pdf, 0, 1024)), '%PDF')) {
            return '';
        }
        $o = new self();
        $o->nesneleriTopla($pdf);
        if ($o->sifreliMi($pdf)) {
            return '';
        }
        return $o->metin($sayfaSiniri);
    }

    private function sifreliMi(string $pdf): bool
    {
        return (bool) preg_match('~/Encrypt\s+\d+\s+\d+\s+R~', substr($pdf, -4096)) || (bool) preg_match('~/Encrypt\s*<<~', $pdf);
    }

    /* ---------------- Nesneler ---------------- */

    private function nesneleriTopla(string $pdf): void
    {
        if (!preg_match_all('~(?<![0-9])(\d+)\s+(\d+)\s+obj\b~', $pdf, $m, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $n = count($m[0]);
        for ($i = 0; $i < $n; $i++) {
            $no = (int) $m[1][$i][0];
            $bas = $m[0][$i][1] + strlen($m[0][$i][0]);
            $son = $i + 1 < $n ? $m[0][$i + 1][1] : strlen($pdf);
            $govde = substr($pdf, $bas, $son - $bas);
            $e = strrpos($govde, 'endobj');
            if ($e !== false) {
                $govde = substr($govde, 0, $e);
            }
            $akis = null;
            $dict = $govde;
            if (preg_match('~stream\r?\n~', $govde, $sm, PREG_OFFSET_CAPTURE)) {
                $dict = substr($govde, 0, $sm[0][1]);
                $veri = substr($govde, $sm[0][1] + strlen($sm[0][0]));
                $uz = $this->sayiAl($dict, 'Length');
                if ($uz !== null && $uz > 0 && $uz <= strlen($veri)) {
                    $veri = substr($veri, 0, $uz);
                } else {
                    $es = strrpos($veri, 'endstream');
                    $veri = $es !== false ? rtrim(substr($veri, 0, $es), "\r\n") : $veri;
                }
                $akis = $this->coz($dict, $veri);
            }
            $this->nesneler[$no] = ['dict' => $dict, 'stream' => $akis];
        }
        // Nesne akışları (PDF 1.5+): içlerindeki nesneleri de ekle
        foreach ($this->nesneler as $kayit) {
            if ($kayit['stream'] === null || !preg_match('~/Type\s*/ObjStm~', $kayit['dict'])) {
                continue;
            }
            $adet = (int) $this->sayiAl($kayit['dict'], 'N');
            $ilk = (int) $this->sayiAl($kayit['dict'], 'First');
            $basliklar = preg_split('~\s+~', trim(substr($kayit['stream'], 0, $ilk))) ?: [];
            $ciftler = [];
            for ($k = 0; $k + 1 < count($basliklar) && count($ciftler) < $adet; $k += 2) {
                $ciftler[] = [(int) $basliklar[$k], (int) $basliklar[$k + 1]];
            }
            foreach ($ciftler as $j => [$no, $ofs]) {
                $sonraki = $ciftler[$j + 1][1] ?? (strlen($kayit['stream']) - $ilk);
                if (!isset($this->nesneler[$no])) {
                    $this->nesneler[$no] = ['dict' => substr($kayit['stream'], $ilk + $ofs, $sonraki - $ofs), 'stream' => null];
                }
            }
        }
    }

    private function coz(string $dict, string $veri): ?string
    {
        if (!preg_match('~/Filter\s*(\[[^\]]*\]|/\w+)~', $dict, $m)) {
            return $veri;
        }
        preg_match_all('~/(\w+)~', $m[1], $f);
        foreach ($f[1] as $suzgec) {
            if ($suzgec === 'FlateDecode' || $suzgec === 'Fl') {
                $c = @gzuncompress($veri);
                if ($c === false) {
                    $c = @zlib_decode($veri);
                }
                if ($c === false) {
                    $c = @gzinflate(substr($veri, 2));
                }
                if ($c === false) {
                    return null;
                }
                $veri = $c;
            } elseif ($suzgec === 'ASCII85Decode' || $suzgec === 'A85') {
                $veri = self::ascii85($veri);
            } elseif ($suzgec === 'ASCIIHexDecode' || $suzgec === 'AHx') {
                $h = preg_replace('~[^0-9A-Fa-f]~', '', explode('>', $veri)[0]) ?? '';
                $veri = (string) hex2bin(strlen($h) % 2 ? $h . '0' : $h);
            } else {
                return null;   // DCT (resim), LZW vb.: metin değil
            }
        }
        return $veri;
    }

    private static function ascii85(string $s): string
    {
        $s = preg_replace('~\s+~', '', $s) ?? '';
        if (str_starts_with($s, '<~')) {
            $s = substr($s, 2);
        }
        $e = strpos($s, '~>');
        if ($e !== false) {
            $s = substr($s, 0, $e);
        }
        $out = '';
        $grup = [];
        $n = strlen($s);
        for ($i = 0; $i < $n; $i++) {
            if ($s[$i] === 'z' && !$grup) {
                $out .= "\0\0\0\0";
                continue;
            }
            $grup[] = ord($s[$i]) - 33;
            if (count($grup) === 5) {
                $v = 0;
                foreach ($grup as $g) {
                    $v = $v * 85 + $g;
                }
                $out .= pack('N', $v & 0xFFFFFFFF);
                $grup = [];
            }
        }
        if ($grup) {
            $eksik = 5 - count($grup);
            $grup = array_pad($grup, 5, 84);
            $v = 0;
            foreach ($grup as $g) {
                $v = $v * 85 + $g;
            }
            $out .= substr(pack('N', $v & 0xFFFFFFFF), 0, 4 - $eksik);
        }
        return $out;
    }

    private function sayiAl(string $dict, string $anahtar): ?int
    {
        if (preg_match('~/' . $anahtar . '\s+(\d+)(?!\s+\d+\s+R)~', $dict, $m)) {
            return (int) $m[1];
        }
        if (preg_match('~/' . $anahtar . '\s+(\d+)\s+\d+\s+R~', $dict, $m)) {   // dolaylı uzunluk
            $hedef = $this->nesneler[(int) $m[1]]['dict'] ?? null;
            return $hedef !== null && preg_match('~^\s*(\d+)~', $hedef, $s) ? (int) $s[1] : null;
        }
        return null;
    }

    /** Sözlükte /Anahtar değeri: ya satır içi << … >> ya da "N 0 R" ile başvurulan nesnenin sözlüğü. */
    private function sozlukDegeri(string $dict, string $anahtar): ?string
    {
        $p = strpos($dict, '/' . $anahtar);
        while ($p !== false) {
            $sonraki = $dict[$p + strlen($anahtar) + 1] ?? '';
            if (!ctype_alnum($sonraki)) {
                break;
            }
            $p = strpos($dict, '/' . $anahtar, $p + 1);
        }
        if ($p === false) {
            return null;
        }
        $kalan = ltrim(substr($dict, $p + strlen($anahtar) + 1));
        if (str_starts_with($kalan, '<<')) {
            return $this->dengeliSozluk($kalan);
        }
        if (preg_match('~^(\d+)\s+\d+\s+R~', $kalan, $m)) {
            return $this->nesneler[(int) $m[1]]['dict'] ?? null;
        }
        return null;
    }

    private function dengeliSozluk(string $s): string
    {
        $derinlik = 0;
        $n = strlen($s);
        for ($i = 0; $i < $n - 1; $i++) {
            $iki = $s[$i] . $s[$i + 1];
            if ($iki === '<<') {
                $derinlik++;
                $i++;
            } elseif ($iki === '>>') {
                $derinlik--;
                $i++;
                if ($derinlik === 0) {
                    return substr($s, 0, $i + 1);
                }
            }
        }
        return $s;
    }

    /* ---------------- Sayfalar ---------------- */

    private function metin(int $sayfaSiniri): string
    {
        $sayfalar = $this->sayfalar();
        $cikti = [];
        foreach (array_slice($sayfalar, 0, $sayfaSiniri) as $no) {
            $dict = $this->nesneler[$no]['dict'];
            $fontlar = $this->sayfaFontlari($no);
            $icerik = '';
            if (preg_match('~/Contents\s*\[([^\]]*)\]~', $dict, $m)) {
                preg_match_all('~(\d+)\s+\d+\s+R~', $m[1], $r);
                foreach ($r[1] as $ref) {
                    $icerik .= ($this->nesneler[(int) $ref]['stream'] ?? '') . "\n";
                }
            } elseif (preg_match('~/Contents\s+(\d+)\s+\d+\s+R~', $dict, $m)) {
                $icerik = $this->nesneler[(int) $m[1]]['stream'] ?? '';
            }
            $cikti[] = $this->icerikMetni($icerik, $fontlar);
        }
        if (!$sayfalar) {   // sayfa ağacı okunamadıysa: tüm içerik akışları
            foreach ($this->nesneler as $k) {
                if ($k['stream'] !== null && str_contains($k['stream'], 'BT') && preg_match('~T[jJ]~', $k['stream'])) {
                    $cikti[] = $this->icerikMetni($k['stream'], []);
                }
            }
        }
        return trim(implode("\n\f\n", $cikti));
    }

    /** Sayfa nesneleri, belge sırasıyla (Pages ağacı izlenir). */
    private function sayfalar(): array
    {
        $kok = null;
        foreach ($this->nesneler as $no => $k) {
            if (preg_match('~/Type\s*/Catalog~', $k['dict']) && preg_match('~/Pages\s+(\d+)\s+\d+\s+R~', $k['dict'], $m)) {
                $kok = (int) $m[1];
                break;
            }
        }
        $liste = [];
        $gez = function (int $no, int $derinlik) use (&$gez, &$liste): void {
            if ($derinlik > 30 || !isset($this->nesneler[$no])) {
                return;
            }
            $d = $this->nesneler[$no]['dict'];
            if (preg_match('~/Type\s*/Pages\b~', $d) && preg_match('~/Kids\s*\[([^\]]*)\]~', $d, $m)) {
                preg_match_all('~(\d+)\s+\d+\s+R~', $m[1], $r);
                foreach ($r[1] as $c) {
                    $gez((int) $c, $derinlik + 1);
                }
            } elseif (preg_match('~/Type\s*/Page\b~', $d)) {
                $liste[] = $no;
            }
        };
        if ($kok !== null) {
            $gez($kok, 0);
        }
        if (!$liste) {
            foreach ($this->nesneler as $no => $k) {
                if (preg_match('~/Type\s*/Page\b~', $k['dict'])) {
                    $liste[] = $no;
                }
            }
        }
        return $liste;
    }

    /** Sayfanın yazı tipleri: kaynak adı (F1) → ['cmap' => [kod => metin], 'bayt' => 1|2]. */
    private function sayfaFontlari(int $sayfa): array
    {
        $res = null;
        $no = $sayfa;
        for ($i = 0; $i < 30 && $res === null; $i++) {   // Resources üst düğümden miras alınabilir
            $d = $this->nesneler[$no]['dict'] ?? '';
            $res = $this->sozlukDegeri($d, 'Resources');
            if ($res === null) {
                if (!preg_match('~/Parent\s+(\d+)\s+\d+\s+R~', $d, $m)) {
                    break;
                }
                $no = (int) $m[1];
            }
        }
        $fontSozluk = $res !== null ? $this->sozlukDegeri($res, 'Font') : null;
        if ($fontSozluk === null) {
            return [];
        }
        $fontlar = [];
        preg_match_all('~/([^\s/<>\[\]()]+)\s+(\d+)\s+\d+\s+R~', $fontSozluk, $m, PREG_SET_ORDER);
        foreach ($m as [, $ad, $ref]) {
            $fd = $this->nesneler[(int) $ref]['dict'] ?? '';
            $kayit = ['cmap' => [], 'bayt' => 1, 'kodlama' => 'win'];
            if (preg_match('~/ToUnicode\s+(\d+)\s+\d+\s+R~', $fd, $t)) {
                [$kayit['cmap'], $kayit['bayt']] = $this->cmap((int) $t[1]);
            }
            if (preg_match('~/Subtype\s*/Type0~', $fd) || preg_match('~/Encoding\s*/Identity-[HV]~', $fd)) {
                $kayit['bayt'] = 2;
            }
            $fontlar[$ad] = $kayit;
        }
        return $fontlar;
    }

    /** ToUnicode CMap: [kod => metin], kod bayt uzunluğu. */
    private function cmap(int $no): array
    {
        if (isset($this->cmapOnbellek[$no])) {
            return $this->cmapOnbellek[$no];
        }
        $s = $this->nesneler[$no]['stream'] ?? '';
        $harita = [];
        $bayt = 1;
        if (preg_match('~begincodespacerange\s*<([0-9A-Fa-f]+)>~', $s, $m)) {
            $bayt = max(1, intdiv(strlen($m[1]), 2));
        }
        $hex = static fn(string $h): string => (string) @mb_convert_encoding((string) hex2bin(strlen($h) % 2 ? '0' . $h : $h), 'UTF-8', 'UTF-16BE');
        if (preg_match_all('~beginbfchar(.*?)endbfchar~s', $s, $bl)) {
            foreach ($bl[1] as $blok) {
                preg_match_all('~<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]*)>~', $blok, $c, PREG_SET_ORDER);
                foreach ($c as [, $kod, $hedef]) {
                    $harita[hexdec($kod)] = $hex($hedef);
                }
            }
        }
        if (preg_match_all('~beginbfrange(.*?)endbfrange~s', $s, $bl)) {
            foreach ($bl[1] as $blok) {
                preg_match_all('~<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<[0-9A-Fa-f]*>|\[[^\]]*\])~', $blok, $c, PREG_SET_ORDER);
                foreach ($c as [, $a, $b, $hedef]) {
                    $bas = hexdec($a);
                    $son = min(hexdec($b), $bas + 65535);
                    if ($hedef[0] === '[') {
                        preg_match_all('~<([0-9A-Fa-f]*)>~', $hedef, $h);
                        foreach ($h[1] as $j => $hh) {
                            if ($bas + $j <= $son) {
                                $harita[$bas + $j] = $hex($hh);
                            }
                        }
                    } else {
                        $taban = hexdec(trim($hedef, '<>'));
                        for ($k = $bas; $k <= $son; $k++) {
                            $harita[$k] = (string) @mb_convert_encoding(pack('n', ($taban + $k - $bas) & 0xFFFF), 'UTF-8', 'UTF-16BE');
                        }
                    }
                }
            }
        }
        return $this->cmapOnbellek[$no] = [$harita, $bayt];
    }

    /* ---------------- İçerik akışı ---------------- */

    private function icerikMetni(string $akis, array $fontlar): string
    {
        $satirlar = [];
        $satir = '';
        $font = null;
        $fs = 10.0;           // yazı boyu (Tf)
        $sc = 1.0;            // metin matrisi ölçeği (Tm a)
        $x = 0.0; $y = 0.0;   // geçerli metin konumu
        $lx = 0.0;            // satır başı x
        $sonX = null;         // son yazılan metnin tahmini bitişi
        $sonY = null;
        $yigin = [];
        $n = strlen($akis);
        $i = 0;
        $bitir = function () use (&$satir, &$satirlar): void {
            if (trim($satir) !== '') {
                $satirlar[] = rtrim($satir);
            }
            $satir = '';
        };
        // Yeni konuma geçerken satır sonu mu, sütun arası mı, bitişik mi?
        $konum = function (float $nx, float $ny) use (&$satir, &$sonX, &$sonY, &$fs, &$sc, $bitir): void {
            $em = max(1.0, abs($fs * $sc));
            if ($sonY !== null && abs($ny - $sonY) > $em * 0.4) {
                $bitir();
                $sonX = null;
            } elseif ($sonX !== null && $satir !== '' && $nx - $sonX > $em * 0.25 && !str_ends_with($satir, ' ') && !str_ends_with($satir, "\t")) {
                $satir .= $nx - $sonX > $em * 1.2 ? "\t" : ' ';
            }
        };
        $yazildi = function (string $metin, float $ekBirim = 0.0) use (&$x, &$y, &$sonX, &$sonY, &$fs, &$sc): void {
            $uz = mb_strlen($metin) * 0.45 * $fs * $sc - $ekBirim / 1000 * $fs * $sc;
            $sonX = $x + $uz;
            $sonY = $y;
            $x = $sonX;
        };
        while ($i < $n) {
            $c = $akis[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            if ($c === '%') {
                $e = strpos($akis, "\n", $i);
                $i = $e === false ? $n : $e + 1;
                continue;
            }
            if ($c === '(') {
                [$str, $i] = $this->literal($akis, $i);
                $yigin[] = ['s', $str];
                continue;
            }
            if ($c === '<' && ($akis[$i + 1] ?? '') !== '<') {
                $e = strpos($akis, '>', $i);
                $e = $e === false ? $n : $e;
                $h = preg_replace('~[^0-9A-Fa-f]~', '', substr($akis, $i + 1, $e - $i - 1)) ?? '';
                $yigin[] = ['s', (string) hex2bin(strlen($h) % 2 ? $h . '0' : $h)];
                $i = $e + 1;
                continue;
            }
            if ($c === '[') {
                $yigin[] = ['['];
                $i++;
                continue;
            }
            if ($c === ']') {
                $dizi = [];
                while ($yigin && end($yigin)[0] !== '[') {
                    array_unshift($dizi, array_pop($yigin));
                }
                array_pop($yigin);
                $yigin[] = ['a', $dizi];
                $i++;
                continue;
            }
            if ($c === '<' || $c === '>') {   // satır içi sözlük << >>
                $i += 2;
                continue;
            }
            if ($c === '/') {
                preg_match('~/[^\s/\[\]()<>{}%]*~A', $akis, $m, 0, $i);
                $yigin[] = ['n', substr($m[0], 1)];
                $i += strlen($m[0]);
                continue;
            }
            if (preg_match('~[+\-]?(?:\d+\.?\d*|\.\d+)~A', $akis, $m, 0, $i)) {
                $yigin[] = ['d', (float) $m[0]];
                $i += strlen($m[0]);
                continue;
            }
            preg_match('~[A-Za-z\'"*]+~A', $akis, $m, 0, $i);
            $op = $m[0] ?? $c;
            $i += max(1, strlen($op));
            if ($op === 'BI') {   // satır içi resim: EI'ye kadar atla
                $e = strpos($akis, 'EI', $i);
                $i = $e === false ? $n : $e + 2;
                $yigin = [];
                continue;
            }
            $sayi = static fn(int $geri): float => (float) ($yigin[count($yigin) - $geri][1] ?? 0);
            switch ($op) {
                case 'BT':
                    $x = $y = $lx = 0.0;
                    $sc = 1.0;
                    break;
                case 'Tf':
                    $font = $fontlar[$yigin[count($yigin) - 2][1] ?? ''] ?? null;
                    $fs = $sayi(1) ?: $fs;
                    break;
                case 'Tm':
                    $sc = abs($sayi(6)) ?: 1.0;
                    $x = $lx = $sayi(2);
                    $y = $sayi(1);
                    break;
                case 'Td':
                case 'TD':
                    $lx += $sayi(2) * $sc;
                    $y += $sayi(1) * $sc;
                    $x = $lx;
                    break;
                case 'T*':
                    $y -= $fs * $sc * 1.2;
                    $x = $lx;
                    break;
                case 'Tj':
                case "'":
                case '"':
                    if ($op !== 'Tj') {
                        $y -= $fs * $sc * 1.2;
                        $x = $lx;
                    }
                    $s = end($yigin);
                    if ($s && $s[0] === 's') {
                        $konum($x, $y);
                        $t = $this->yaz($s[1], $font);
                        $satir .= $t;
                        $yazildi($t);
                    }
                    break;
                case 'TJ':
                    $a = end($yigin);
                    if ($a && $a[0] === 'a') {
                        $konum($x, $y);
                        $t = '';
                        $ek = 0.0;
                        foreach ($a[1] as $p) {
                            if ($p[0] === 's') {
                                $t .= $this->yaz($p[1], $font);
                            } elseif ($p[0] === 'd') {
                                $ek += $p[1];
                                if ($p[1] < -250 && $t !== '' && !str_ends_with($t, ' ')) {
                                    $t .= ' ';
                                }
                            }
                        }
                        $satir .= $t;
                        $yazildi($t, $ek);
                    }
                    break;
            }
            $yigin = [];
        }
        $bitir();
        return implode("\n", $satirlar);
    }

    private function literal(string $s, int $i): array
    {
        $out = '';
        $derinlik = 0;
        $n = strlen($s);
        for ($i++; $i < $n; $i++) {
            $c = $s[$i];
            if ($c === '\\') {
                $d = $s[++$i] ?? '';
                if (ctype_digit($d) && $d < '8') {
                    $oct = $d;
                    for ($k = 0; $k < 2 && isset($s[$i + 1]) && $s[$i + 1] >= '0' && $s[$i + 1] <= '7'; $k++) {
                        $oct .= $s[++$i];
                    }
                    $out .= chr(octdec($oct) & 0xFF);
                } else {
                    $out .= match ($d) { 'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", "\r", "\n" => '', default => $d };
                    if ($d === "\r" && ($s[$i + 1] ?? '') === "\n") {
                        $i++;
                    }
                }
            } elseif ($c === '(') {
                $derinlik++;
                $out .= $c;
            } elseif ($c === ')') {
                if ($derinlik === 0) {
                    return [$out, $i + 1];
                }
                $derinlik--;
                $out .= $c;
            } else {
                $out .= $c;
            }
        }
        return [$out, $n];
    }

    private function yaz(string $bayt, ?array $font): string
    {
        if ($font && $font['cmap']) {
            $b = $font['bayt'];
            $out = '';
            for ($k = 0; $k + $b <= strlen($bayt); $k += $b) {
                $kod = $b === 2 ? (ord($bayt[$k]) << 8) | ord($bayt[$k + 1]) : ord($bayt[$k]);
                $out .= $font['cmap'][$kod] ?? '';
            }
            return $out;
        }
        if ($font && $font['bayt'] === 2) {
            return '';   // eşlemesiz CID yazı tipi: çözülemez
        }
        // Basit yazı tipi: WinAnsi / Türkçe kod sayfası varsayımı
        $u = @iconv('Windows-1254', 'UTF-8//IGNORE', $bayt);
        return $u === false ? '' : $u;
    }
}

/** Kısayol. */
function pdf_metin(string $pdf): string
{
    try {
        return PdfMetin::oku($pdf);
    } catch (Throwable $e) {
        return '';
    }
}
