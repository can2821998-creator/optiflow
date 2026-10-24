<?php
/* 4.53.0 Çıktı görünümü (app/cikti.php): seçenek doğrulama, palet, stil etiketi, işaret, çıktı dosyalarının bağlantısı. */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
$gecen = 0;
$kalan = 0;
function ok(bool $k, string $ad, string $ek = ''): void
{
    global $gecen, $kalan;
    if ($k) { $gecen++; } else { $kalan++; echo "  ✗ $ad" . ($ek !== '' ? " — $ek" : '') . "\n"; }
}
$GLOBALS['ayar'] = ['shop_name' => 'Poyraz Optik'];
function setting(string $k, string $d = ''): string { return (string) ($GLOBALS['ayar'][$k] ?? $d); }
function brand_color(): string { return '#123456'; }
function brand_mark(): string { return '<span class="brand-mark">MERCEK</span>'; }
$kok = dirname(__DIR__, 2);
require $kok . '/app/cikti.php';

/* 1. Doğrulama */
$d = cikti_ayar();
ok($d === cikti_varsayilan(), 'ayar yokken varsayılan');
$t = cikti_temizle(['renk' => 'yok', 'baslik' => '<b>', 'yazi' => 'klasik', 'kose' => 'keskin', 'desen' => 'dalga', 'isaret' => 'monogram', 'slogan' => "  çok   uzun\nsatır " . str_repeat('x', 80), 'alt_yazi' => 'Teşekkürler']);
ok($t['renk'] === 'marka' && $t['baslik'] === 'gece' && $t['yazi'] === 'klasik' && $t['kose'] === 'keskin', 'bilinmeyen seçenek varsayılana iner');
ok(mb_strlen($t['slogan']) === 60 && !str_contains($t['slogan'], "\n") && str_starts_with($t['slogan'], 'çok uzun satır'), 'slogan tek satır, 60 karakter');
$GLOBALS['ayar'] += ['cikti_renk' => 'zumrut', 'cikti_baslik' => 'acik', 'cikti_isaret' => 'yok'];
ok(cikti_ayar()['renk'] === 'zumrut' && cikti_ayar()['baslik'] === 'acik', 'ayarlardan okunur');
ok(cikti_ayar(['renk' => 'mor'])['renk'] === 'mor' && cikti_ayar()['renk'] === 'mor', 'önizleme geçici ayarı bu istekte geçerli');
for ($i = 0; $i < 30; $i++) {
    $k = cikti_karistir(['slogan' => 'S']);
    if ($k !== cikti_temizle($k) || $k['renk'] === 'marka' || $k['yazi'] === 'otomatik' || $k['slogan'] !== 'S') { break; }
}
ok($i === 30, 'karıştır hep geçerli, mağaza rengi/otomatik yazı önermez, slogan korunur');

/* 2. Palet */
ok(cikti_karis('#000000', '#ffffff', .5) === '#808080', 'renk karışımı');
foreach (array_keys(cikti_paletler()) as $ad) {
    $p = cikti_palet($ad);
    $hepsi = true;
    foreach ($p as $x) { $hepsi = $hepsi && (bool) preg_match('/^#[0-9a-f]{6}$/', $x); }
    ok($hepsi && count($p) === 9, "palet $ad tam");
}
ok(cikti_palet('marka')['ana'] === '#b4233c', 'Genel\'de renk seçilmemişse mağaza rengi = sistem bordosu');
$GLOBALS['ayar']['brand_color'] = '#123456';
ok(cikti_palet('marka')['ana'] === '#123456', 'Genel\'deki renk kullanılır');
$GLOBALS['ayar']['brand_color'] = '#050505';
ok(cikti_palet('marka')['ana'] !== '#050505', 'çok koyu marka rengi açılır');
$lum = static function (string $h): float { [$r, $g, $b] = cikti_rgb($h); return ($r * 299 + $g * 587 + $b * 114) / 1000; };
$p = cikti_palet('hardal');
ok($lum($p['tint']) > $lum($p['gul']) && $lum($p['gul']) > $lum($p['ana']) && $lum($p['ana']) > $lum($p['koyu']) && $lum($p['koyu']) > $lum($p['gece']), 'tonlar açıktan koyuya sıralı');

/* 3. Stil etiketi */
$hepsiFarkli = [];
foreach (array_keys(cikti_secenekler()['baslik']) as $b) {
    $css = cikti_stil_tag(['renk' => 'zumrut', 'baslik' => $b, 'yazi' => 'otomatik', 'kose' => 'yuvarlak', 'desen' => 'nokta', 'isaret' => 'mercek', 'slogan' => '', 'alt_yazi' => '']);
    $hepsiFarkli[$css] = 1;
    ok(str_contains($css, '--bas-bg:') && str_contains($css, '--bas-yazi:') && str_contains($css, '--brand:#137a52'), "bant $b: değişkenler");
}
ok(count($hepsiFarkli) === count(cikti_secenekler()['baslik']), 'her bant stili farklı CSS üretir');
$css = cikti_stil_tag(cikti_temizle(['baslik' => 'gece']));
ok(str_contains($css, '--bas-yazi:#ffffff') && str_contains($css, '--bas-golge:none'), 'gece: beyaz yazı');
$css = cikti_stil_tag(cikti_temizle(['baslik' => 'cizgi', 'renk' => 'mor']));
ok(str_contains($css, '--bas-yazi:#1b1416') && str_contains($css, 'inset 0 -1.3mm 0 #6b3fa0'), 'çizgi: koyu yazı + renkli alt çizgi');
$css = cikti_stil_tag(cikti_temizle(['kose' => 'keskin']));
ok(str_contains($css, 'border-radius:0 !important') && str_contains($css, '.fis-bant') && str_contains($css, '.ie-bant') && str_contains($css, '.ust'), 'keskin köşe bütün çıktılarda');
ok(!str_contains(cikti_stil_tag(cikti_temizle([])), 'border-radius:0'), 'yuvarlakta köşe kuralı yok');
foreach (['modern' => 'manrope', 'klasik' => 'fraunces', 'karakter' => 'bricolage', 'genis' => 'unbounded'] as $y => $dosya) {
    $css = cikti_stil_tag(cikti_temizle(['yazi' => $y]));
    ok(str_contains($css, 'assets/fonts/' . $dosya) && is_file($kok . '/assets/fonts/' . (preg_match('~assets/fonts/([^"]+)"~', $css, $m) ? $m[1] : 'x')) && str_contains($css, '--display:"CiktiBaslik"'), "yazı $y: yerel dosya + döküm değişkeni");
}
ok(!str_contains(cikti_stil_tag(cikti_temizle(['yazi' => 'otomatik'])), '@font-face'), 'otomatik yazıda font yok');
foreach (array_keys(cikti_secenekler()['desen']) as $ds) {
    $x = cikti_desen_css($ds, '255,255,255', .07);
    ok($ds === 'yok' ? $x === 'none' : ($x !== 'none' && !str_contains($x, '"' . "'") && !str_contains($x, ';')), "desen $ds");
}
ok(!preg_match('/<\/?script|expression\(|javascript:/i', cikti_stil_tag(cikti_temizle(['renk' => 'zumrut', 'desen' => 'dalga']))), 'stil etiketinde zararlı içerik yok');

/* 4. İşaret */
ok(cikti_bas_harfler('Poyraz Optik') === 'P', 'baş harf: "Optik" atılır');
ok(cikti_bas_harfler('ışık gözlük evi') === 'IE', 'baş harf: Türkçe büyük harf, "gözlük" atılır');
ok(cikti_bas_harfler('istanbul Göz Merkezi') === 'İG', 'baş harf: i → İ');
ok(cikti_bas_harfler('Optik') !== '', 'yalnız "Optik" → boş kalmaz');
ok(cikti_isaret(cikti_temizle(['isaret' => 'yok'])) === '', 'işaretsiz');
ok(str_contains(cikti_isaret(cikti_temizle(['isaret' => 'mercek'])), 'MERCEK'), 'mercek = OptiFlow işareti');
$GLOBALS['ayar']['shop_name'] = '<b>X</b> Optik';
$m = cikti_isaret(cikti_temizle(['isaret' => 'monogram']));
ok(str_contains($m, 'ck-monogram') && str_contains($m, '&lt;') && !str_contains($m, '<b>'), 'monogram kaçışlı');
ok(str_contains(cikti_isaret(cikti_temizle(['isaret' => 'gozluk'])), 'ck-gozluk'), 'gözlük işareti');

/* 5. Çıktı dosyaları görünüme bağlı */
$oku = static fn(string $f): string => (string) file_get_contents($kok . '/' . $f);
ok(str_contains($oku('app/fis.php'), 'cikti_stil_tag()') && str_contains($oku('app/fis.php'), 'cikti_isaret()') && str_contains($oku('app/fis.php'), 'tesekkur'), 'yarım A4 fiş (hizmet, makbuz, satış)');
ok(str_contains($oku('app/partials/siparis-fisi.php'), 'cikti_stil_tag($ckAyar)') && str_contains($oku('app/partials/siparis-fisi.php'), "\$ckAyar['alt_yazi']"), 'sipariş fişi');
ok(str_contains($oku('app/dokum.php'), 'cikti_stil_tag()') && str_contains($oku('app/dokum.php'), 'cikti_isaret()') && str_contains($oku('app/dokum.php'), 'cikti_alt_yazi()'), 'A4 dökümler (teklif dahil)');
foreach (['assets/fis.css' => ['var(--bas-bg', 'var(--bas-yazi', 'var(--bas-desen'], 'assets/siparis-fisi.css' => ['var(--bas-bg', 'var(--bas-vurgu'], 'assets/dokum.css' => ['var(--bas-bg', 'var(--bas-desen', 'var(--bas-dev', 'var(--gul-2)']] as $f => $ler) {
    $c = $oku($f);
    ok(count(array_filter($ler, static fn($x) => str_contains($c, $x))) === count($ler), "$f değişkenlere bağlı");
}
$dc = $oku('assets/dokum.css');
ok(!preg_match('/#(c8264a|8f1a2e|160b0f|5c0f1f|a8203a|ff95a6)\b/i', substr($dc, strpos($dc, '}', strpos($dc, ':root{')))), 'dokum.css: sabit bordo tonu kalmadı');
ok(str_contains($oku('app/bootstrap.php'), "require __DIR__ . '/cikti.php'"), 'bootstrap yükler');
ok(is_file($kok . '/cikti-onizle.php') && str_contains($oku('app/pages/cikti-onizle.php'), 'require_login()'), 'önizleme oturum ister');
ok(str_contains($oku('app/pages/settings.php'), "'cikti'       => 'Çıktı görünümü'") && str_contains($oku('app/pages/settings.php'), "cikti_kaydet"), 'Ayarlar sekmesi');
$js = $oku('assets/cikti-ayar.js');
ok($js !== '' && !preg_match('/<script/i', $oku('app/pages/settings.php')) && !preg_match('/\son(click|change|input)=/i', $oku('app/pages/settings.php')), 'satır içi script yok (CSP)');

echo "Çıktı görünümü: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
