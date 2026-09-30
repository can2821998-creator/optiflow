<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Rehber (blog) yazıları (4.9.0)
   --------------------------------------------------------------------------
   Veritabanı KULLANMAZ; yazılar JSON dosyasıdır:
     • app/rehber-hazir/<slug>.json  → paketle gelen yazılar (salt-okunur)
     • storage/rehber/<slug>.json    → merkez panelden eklenen/düzenlenenler
   Aynı slug iki yerde varsa storage/ kazanır (panelden düzenleme böyle çalışır).
   Alanlar: slug, baslik, seo_baslik, meta, hedef_kelime, govde (HTML),
            yayinda (bool), yayin_tarihi (YYYY-AA-GG), guncelleme (YYYY-AA-GG), silindi (bool)
   ========================================================================== */

const REHBER_ETIKETLER = ['h2', 'h3', 'p', 'ul', 'ol', 'li', 'strong', 'em', 'b', 'i', 'a', 'blockquote', 'br', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];

function rehber_kok(): string
{
    return dirname(__DIR__);
}

function rehber_dizin_yazilabilir(): string
{
    $d = rehber_kok() . '/storage/rehber';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

/** Türkçe metinden URL kısaltması üretir: "Gözlükçü Programı" → "gozlukcu-programi". */
function rehber_slug(string $s): string
{
    $s = strtr($s, ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'I' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u']);
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return substr(trim($s, '-'), 0, 80);
}

function rehber_gecerli_slug(string $s): bool
{
    return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $s) && strlen($s) <= 80;
}

/** Yazı gövdesini beyaz listeye göre temizler (yalnızca izinli etiketler; a[href] dışında öznitelik yok). */
function rehber_temizle(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    if (!class_exists('DOMDocument')) {
        $html = strip_tags($html, '<' . implode('><', REHBER_ETIKETLER) . '>');
        return preg_replace('/<(\w+)\s[^>]*>/', '<$1>', $html) ?? '';
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $eski = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="kok">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($eski);
    $kok = $doc->getElementById('kok');
    if (!$kok) {
        return '';
    }
    $gez = static function (DOMNode $n) use (&$gez, $doc): void {
        foreach (iterator_to_array($n->childNodes) as $c) {
            if ($c instanceof DOMComment || $c instanceof DOMProcessingInstruction) {
                $n->removeChild($c);
                continue;
            }
            if (!$c instanceof DOMElement) {
                continue;
            }
            $ad = strtolower($c->tagName);
            if (in_array($ad, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'svg', 'math'], true)) {
                $n->removeChild($c);
                continue;
            }
            if (!in_array($ad, REHBER_ETIKETLER, true)) {
                while ($c->firstChild) {
                    $n->insertBefore($c->firstChild, $c);
                }
                $n->removeChild($c);
                continue;
            }
            $href = $ad === 'a' ? trim($c->getAttribute('href')) : '';
            foreach (iterator_to_array($c->attributes) as $a) {
                $c->removeAttribute($a->nodeName);
            }
            if ($ad === 'a') {
                if (preg_match('#^(https?://|/|\#|[a-z0-9-]+\.php)#i', $href)) {
                    $c->setAttribute('href', $href);
                    if (preg_match('#^https?://#i', $href) && !preg_match('#^https?://(www\.)?optiflow\.com\.tr#i', $href)) {
                        $c->setAttribute('rel', 'noopener');
                        $c->setAttribute('target', '_blank');
                    }
                }
            }
            $gez($c);
        }
    };
    $gez($kok);
    $out = '';
    foreach ($kok->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return trim($out);
}

function rehber_oku_dosya(string $f): ?array
{
    $j = json_decode((string) @file_get_contents($f), true);
    if (!is_array($j) || empty($j['slug']) || !rehber_gecerli_slug((string) $j['slug'])) {
        return null;
    }
    return $j + ['baslik' => '', 'seo_baslik' => '', 'meta' => '', 'hedef_kelime' => '', 'govde' => '',
        'yayinda' => false, 'yayin_tarihi' => '', 'guncelleme' => '', 'silindi' => false];
}

/** Tüm yazılar (slug => yazı), yeni tarihli önce. $yayindakiler=true ise yalnızca yayındakiler. */
function rehber_hepsi(bool $yayindakiler = true): array
{
    $liste = [];
    foreach ([rehber_kok() . '/app/rehber-hazir', rehber_kok() . '/storage/rehber'] as $i => $dizin) {
        foreach (glob($dizin . '/*.json') ?: [] as $f) {
            $y = rehber_oku_dosya($f);
            if ($y) {
                $y['_pakette'] = $i === 0 || !empty($liste[$y['slug']]['_pakette']);
                $liste[$y['slug']] = $y;
            }
        }
    }
    $liste = array_filter($liste, static fn($y) => empty($y['silindi']) && (!$yayindakiler || !empty($y['yayinda'])));
    uasort($liste, static fn($a, $b) => strcmp((string) ($b['yayin_tarihi'] ?: $b['guncelleme']), (string) ($a['yayin_tarihi'] ?: $a['guncelleme'])));
    return $liste;
}

function rehber_bul(string $slug, bool $yayindakiler = true): ?array
{
    return rehber_hepsi($yayindakiler)[$slug] ?? null;
}

/** Panelden kaydet (storage/rehber). Hata varsa RuntimeException. */
function rehber_kaydet(array $y, string $eskiSlug = ''): array
{
    $slug = rehber_slug((string) ($y['slug'] ?? '') !== '' ? (string) $y['slug'] : (string) ($y['baslik'] ?? ''));
    if (!rehber_gecerli_slug($slug)) {
        throw new RuntimeException('Geçerli bir adres (slug) girin: yalnızca küçük harf, rakam ve tire.');
    }
    $baslik = trim((string) ($y['baslik'] ?? ''));
    if (mb_strlen($baslik) < 5) {
        throw new RuntimeException('Başlık en az 5 karakter olmalı.');
    }
    $govde = rehber_temizle((string) ($y['govde'] ?? ''));
    if (mb_strlen(strip_tags($govde)) < 200) {
        throw new RuntimeException('Yazı gövdesi çok kısa (en az 200 karakter).');
    }
    $mevcut = rehber_bul($eskiSlug !== '' ? $eskiSlug : $slug, false);
    $bugun = date('Y-m-d');
    $yayinda = !empty($y['yayinda']);
    $kayit = [
        'slug' => $slug,
        'baslik' => mb_substr($baslik, 0, 140),
        'seo_baslik' => mb_substr(trim((string) ($y['seo_baslik'] ?? '')) ?: $baslik, 0, 70),
        'meta' => mb_substr(trim((string) ($y['meta'] ?? '')), 0, 170),
        'hedef_kelime' => mb_substr(trim((string) ($y['hedef_kelime'] ?? '')), 0, 80),
        'govde' => $govde,
        'yayinda' => $yayinda,
        'yayin_tarihi' => $yayinda ? ((string) ($mevcut['yayin_tarihi'] ?? '') ?: $bugun) : (string) ($mevcut['yayin_tarihi'] ?? ''),
        'guncelleme' => $bugun,
        'silindi' => false,
    ];
    $dizin = rehber_dizin_yazilabilir();
    if (@file_put_contents($dizin . '/' . $slug . '.json', json_encode($kayit, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) {
        throw new RuntimeException('storage/rehber klasörüne yazılamadı (klasör izinlerini kontrol edin).');
    }
    if ($eskiSlug !== '' && $eskiSlug !== $slug) {
        rehber_sil($eskiSlug);
    }
    return $kayit;
}

function rehber_yayin_degistir(string $slug, bool $yayinda): void
{
    $y = rehber_bul($slug, false);
    if (!$y) {
        throw new RuntimeException('Yazı bulunamadı.');
    }
    $y['yayinda'] = $yayinda;
    rehber_kaydet($y, $slug);
}

/** Siler: panel dosyasını kaldırır; paketle gelen yazıysa gizleme kaydı bırakır. */
function rehber_sil(string $slug): void
{
    if (!rehber_gecerli_slug($slug)) {
        return;
    }
    $dizin = rehber_dizin_yazilabilir();
    @unlink($dizin . '/' . $slug . '.json');
    if (is_file(rehber_kok() . '/app/rehber-hazir/' . $slug . '.json')) {
        @file_put_contents($dizin . '/' . $slug . '.json', json_encode(['slug' => $slug, 'silindi' => true]), LOCK_EX);
    }
}

function rehber_url(string $slug = ''): string
{
    return 'https://optiflow.com.tr/rehber.php' . ($slug !== '' ? '?y=' . rawurlencode($slug) : '');
}

/** Okuma süresi (dk). */
function rehber_sure(string $govde): int
{
    $k = str_word_count(strip_tags(str_replace(['ç','ğ','ı','ö','ş','ü','Ç','Ğ','İ','Ö','Ş','Ü'], 'a', $govde)));
    return max(1, (int) round($k / 200));
}
