<?php
declare(strict_types=1);

/* 4.48.0 — Ortak yarım A4 fiş: "A4'ün yarısı müşteriye, yarısı bize, kesilecek" (kullanıcı, 23.10).
   Üst yarı MÜŞTERİ NÜSHASI, ortada kesme çizgisi, alt yarı MAĞAZA NÜSHASI. Her yarı 210 × 148,5 mm.
   &kagit=yarim → önceden kesilmiş yarım sayfa: iki ayrı sayfa (müşteri, mağaza). &yazdir=1 → pencere kendiliğinden açılır.
   Dil: iş emriyle aynı (gece bandı, büyük mono numara, numaralı kutular); siyah-beyaz yazıcıda okunur.
   Kullananlar: hizmet / tamir fişi, tahsilat makbuzu, hızlı satış fişi (print.php). Sipariş fişi kendi düzeninde
   (app/partials/siparis-fisi.php) aynı kalıbı zaten izler: üst iş emri, alt müşteri nüshası. */

/**
 * Sayfayı basar ve çıkar. $f: baslik (sekme), etiket (FİŞ TÜRÜ), no, tarih, musteri (HTML), magaza (HTML).
 */
function fis_sayfa(array $f): never
{
    $yarim = query('kagit') === 'yarim';
    $dukkan = setting('shop_name', 'OptiFlow');
    $tel = trim(setting('shop_phone'));
    $yarimHtml = static function (string $nusha, string $govde) use ($f, $dukkan, $tel): string {
        return '<section class="fis-yari ' . ($nusha === 'MÜŞTERİ NÜSHASI' ? 'musteri' : 'magaza') . '">'
            . '<header class="fis-bant"><div class="fis-marka">' . brand_mark() . '<span><b>' . e($dukkan) . '</b><small>'
            . e($nusha) . '</small></span></div>'
            . '<div class="fis-tur"><small>' . e($f['etiket']) . '</small><b>' . e($f['no']) . '</b><i>' . e($f['tarih']) . '</i></div></header>'
            . '<div class="fis-govde">' . $govde . '</div>'
            . '<footer class="fis-alt"><span>' . e($dukkan) . ($tel !== '' ? ' · ' . e(phone_display($tel)) : '') . '</span><span>' . e($f['no']) . ' · basım ' . e(date('d.m.Y H:i')) . '</span></footer>'
            . '</section>';
    };
    ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($f['baslik']) ?> · <?= e($dukkan) ?></title>
<link rel="stylesheet" href="<?= e(asset('fis.css')) ?>">
<?= brand_style_tag() ?>
<style>@page { size: <?= $yarim ? '210mm 148.5mm' : 'A4 portrait' ?>; margin: 0; }</style>
</head>
<body class="<?= $yarim ? 'kagit-yarim' : 'kagit-a4' ?>"<?= query('yazdir') === '1' ? ' data-otomatik-yazdir' : '' ?>>
<div class="fis-arac no-print">
  <button type="button" class="ghost" data-close>← Geri</button>
  <span><?= $yarim ? 'Kesilmiş yarım sayfa (2 sayfa)' : 'A4 — ortadan kesin: üst yarı müşteriye, alt yarı mağazaya' ?></span>
  <a href="<?= e(url_with(['kagit' => $yarim ? null : 'yarim'])) ?>"><?= $yarim ? 'A4 tek sayfa' : 'Yarım sayfaya bas' ?></a>
  <button type="button" data-print>Yazdır</button>
</div>
<main class="fis-kagit">
  <?= $yarimHtml('MÜŞTERİ NÜSHASI', (string) $f['musteri']) ?>
  <div class="fis-kesim" aria-hidden="true"><span>✂ buradan kesin · üst yarı müşteriye, alt yarı mağazada kalır</span></div>
  <?= $yarimHtml('MAĞAZA NÜSHASI', (string) $f['magaza']) ?>
</main>
<script src="<?= e(asset('print.js')) ?>" defer></script>
</body>
</html>
<?php
    exit;
}

/** Numaralı kutu (iş emrindeki 01, 02…). */
function fis_kutu(string $no, string $baslik, string $icerik, string $sinif = ''): string
{
    return '<section class="fis-kutu' . ($sinif !== '' ? ' ' . e($sinif) : '') . '"><h2><i>' . e($no) . '</i>' . e($baslik) . '</h2>' . $icerik . '</section>';
}

/** Etiket–değer satırları. Değer HTML'dir (çağıran kaçırır). */
function fis_satirlar(array $satirlar): string
{
    $h = '<dl class="fis-dl">';
    foreach ($satirlar as [$etiket, $deger]) {
        if ($deger === '' || $deger === null) {
            continue;
        }
        $h .= '<dt>' . e($etiket) . '</dt><dd>' . $deger . '</dd>';
    }
    return $h . '</dl>';
}

/** Büyük vurgu kutusu (tutar, hazır tarihi): etiket, büyük değer, alt yazı. */
function fis_vurgu(string $etiket, string $deger, string $alt = '', string $ton = ''): string
{
    return '<div class="fis-vurgu' . ($ton !== '' ? ' ' . e($ton) : '') . '"><small>' . e($etiket) . '</small><b>' . e($deger) . '</b>'
        . ($alt !== '' ? '<i>' . e($alt) . '</i>' : '') . '</div>';
}

/** İşaret kutulu liste (teslim alırken görülenler, işlemler). */
function fis_isaretler(array $hepsi, array $secili): string
{
    $h = '<ul class="fis-isaret">';
    foreach ($hepsi as $k => $ad) {
        $var = in_array($k, $secili, true);
        $h .= '<li class="' . ($var ? 'var' : 'yok') . '"><span class="kare">' . ($var ? '✕' : '') . '</span>' . e($ad) . '</li>';
    }
    return $h . '</ul>';
}

/** İmza / paraf satırı. */
function fis_imzalar(array $imzalar): string
{
    $h = '<div class="fis-imzalar">';
    foreach ($imzalar as [$baslik, $alt]) {
        $h .= '<div><span></span><b>' . e($baslik) . '</b><small>' . e($alt) . '</small></div>';
    }
    return $h . '</div>';
}

/** Karekod kutusu ('' adres → boş). */
function fis_karekod(string $adres, string $yazi): string
{
    return $adres === '' ? '' : '<div class="fis-qr">' . qr_svg($adres, 96, 'M', '#141012') . '<small>' . e($yazi) . '</small></div>';
}
