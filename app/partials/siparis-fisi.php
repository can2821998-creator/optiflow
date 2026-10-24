<?php
declare(strict_types=1);

/* ==========================================================================
   Sipariş fişi, yarım sayfa. print.php?type=order&id=<sipariş> (ayrıntılı A4 müşteri fişi: &tam=1).
   Kâğıt (&kagit=):
     a4    (varsayılan) A4 dikey; ÜST YARI atölye iş emri, ortada kesme çizgisi, ALT YARI müşteri nüshası.
     yarim kesilmiş yarım sayfa (A5 yatay) yazıcıya konunca; müşteri nüshası ikinci sayfa.
   &nusha=0 müşteri nüshasını kapatır. &tezgah=1 (akışın ⑤. adımı): yazdırma penceresi kendiliğinden açılır.
   Kurumsal kimlik: docs/kurumsal-kimlik/project/README.md.
     İş emri (4.36.0): kesildikten sonra DİK kullanılan 148,5 × 210 mm kart; yarım sayfaya 90° döndürülerek basılır
       (CSS .is-emri). Gece bandı (sipariş no, teslim sözü, "Siparişi aç" karekodu), numaralı bölümler: 01 reçete
       (büyük mono), 02 montaj ölçüleri (boşsa usta yazar), 03 camlar + çerçeve (göz, grup, stok), 04 atölye adımları
       (tamam / paraf / saat), not. Kalabalık reçetede (3–4 cam ya da yakın + ADD) .sik ile sıkılaşır.
     Müşteri nüshası (yatay): göz tablosu (satır satır küçülen harfler "GÖZLÜĞÜNÜZ HAZIRLANIYOR" diye okunur),
       "Sırada ne var?" (kâğıtta değişmeyen üç bilgi), gözlük kartı; sağ sütunda teslim sözü, nişangâh içinde karekod, ödeme.
   Siyah-beyaz yazıcıda da okunur (renk yalnız vurgu). Tek dosya: assets/siparis-fisi.css + print.js.
   Tutarlar yalnız tutar görme yetkisi olana basılır.
   ========================================================================== */

$o = find_order(query_int('id'));
if (!$o) {
    render_error_page('Sipariş bulunamadı', '');
}
$yarim = query('kagit') === 'yarim';
$nusha = query('nusha') !== '0';
$tezgah = query('tezgah') === '1';
$oid = (int) $o['id'];
$rx = row('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph,
                  n.right_cyl AS near_right_cyl, n.right_axis AS near_right_axis, n.left_cyl AS near_left_cyl, n.left_axis AS near_left_axis
             FROM prescription_records r LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.order_id = ? ORDER BY r.prescription_date DESC, r.id DESC LIMIT 1', [$oid]);
$kalemler = $rx ? rows('SELECT lens_label, lens_type, lens_value, eye, item_group, stock_status FROM prescription_lens_items WHERE prescription_id = ? ORDER BY item_group, lens_no, id', [$rx['id']]) : [];
$tutarlar = can_see_amounts();
$v = static fn(string $k) => trim((string) ($rx[$k] ?? ''));
$no = order_no($oid);
$dukkan = setting('shop_name', 'OptiFlow');
$takip = (track_page_exists() && ($u = order_track_url($oid)) !== '') ? $u : '';
$siparisUrl = musteri_url('order.php', ['id' => $oid]);
$ad = trim($o['c_first'] . ' ' . $o['c_last']);
$kullanim = $rx ? (lens_designs()[(string) $rx['lens_design']] ?? '') : '';
$yakinVar = $rx && ($v('near_right_sph') !== '' || $v('near_left_sph') !== '');
$addVar = $rx && ($v('right_add') !== '' || $v('left_add') !== '');
$durumlar = stock_statuses();

/** Dioptri: eksi işareti (U+2212) ve ondalık virgül (kimlik kuralı). */
$dio = static fn(string $s): string => $s === '' ? '' : str_replace(['-', '.'], ['−', ','], $s);
$hucre = static fn(string $d) => $d !== '' ? e($d) : '<span class="bos">—</span>';
$gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
$aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
$soz = $o['promised_date'] ? strtotime((string) $o['promised_date']) : false;
$sozGun = $soz ? date('j', $soz) . ' ' . $aylar[(int) date('n', $soz)] : '';
$sozHafta = $soz ? $gunler[(int) date('w', $soz)] : '';

/* --- Reçete tablosu: UZAK ve YAKIN ayrı bloklar (4.39.2), göz başına SPH CYL AKS (+ADD çok odakta).
       Tek odak yakın: yalnız YAKIN; ayrı uzak + yakın: iki blok; diğerleri: UZAK (ADD'li). Montaj ölçüleri ayrı kutuda. --- */
$yukVar = $v('right_height') !== '' || $v('left_height') !== '';
$tasarim = $rx ? (string) $rx['lens_design'] : '';
$bloklar = [];
if ($rx) {
    $uzakDeger = static fn(string $t): array => [$v($t . '_sph'), $v($t . '_cyl'), $v($t . '_axis'), $v($t . '_add')];
    // yakın: yakın tablosu doluysa o (eski aktarımlar), yoksa reçete satırı; boşsa SPH + ADD
    $yakinDeger = static function (string $t) use ($v): array {
        $sph = $v('near_' . $t . '_sph') !== '' ? $v('near_' . $t . '_sph') : near_sphere($v($t . '_sph'), $v($t . '_add'));
        return [$sph, $v('near_' . $t . '_cyl') !== '' ? $v('near_' . $t . '_cyl') : $v($t . '_cyl'), $v('near_' . $t . '_axis') !== '' ? $v('near_' . $t . '_axis') : $v($t . '_axis'), ''];
    };
    if ($tasarim !== 'tek_odak_yakin') {
        $bloklar[] = ['UZAK', 'uzak', $uzakDeger, $tasarim !== 'ayri_uzak_yakin' && $addVar];
    }
    if ($tasarim === 'tek_odak_yakin' || $tasarim === 'ayri_uzak_yakin' || ($yakinVar && $tasarim !== 'progressive')) {
        $bloklar[] = ['YAKIN', 'yakin', $yakinDeger, false];
    }
}
$addSutun = (bool) array_filter($bloklar, static fn($b) => $b[3]);
$satir = '';
foreach ($bloklar as [$etiket, $sinif, $deger, $addli]) {
    foreach (['right' => ['R', 'Sağ'], 'left' => ['L', 'Sol']] as $t => [$kod, $gozAd]) {
        [$sph, $cyl, $aks, $add] = $deger($t);
        $satir .= '<tr class="blok-' . $sinif . ($t === 'right' ? ' blok-ilk' : '') . '">'
            . ($t === 'right' ? '<th class="blok-ad" rowspan="2"><span>' . $etiket . '</span></th>' : '')
            . '<th><span class="goz">' . $kod . '</span>' . $gozAd . '</th>'
            . '<td>' . $hucre($dio($sph)) . '</td><td>' . $hucre($dio($cyl)) . '</td>'
            . '<td>' . $hucre($aks !== '' ? $aks . '°' : '') . '</td>'
            . ($addSutun ? '<td>' . ($addli ? $hucre($dio($add)) : '<span class="bos">—</span>') . '</td>' : '')
            . '</tr>';
    }
}

/* --- Cam kalemleri --- */
$gruplar = ['uzak' => 'UZAK', 'yakin' => 'YAKIN', 'cok_odak' => 'ÇOK ODAK'];   // 4.39.2: rozet olarak, belirgin
$camlar = '';
foreach ($kalemler as $k) {
    $stokta = $k['stock_status'] === 'stokta_var';
    $camlar .= '<li class="cam-' . e((string) $k['item_group']) . '"><span class="grup g-' . e((string) $k['item_group']) . '">' . e($gruplar[(string) $k['item_group']] ?? '') . '</span><span class="goz">' . e((string) $k['eye']) . '</span>'
        . '<span class="cam-ad"><b>' . e(trim((string) ($k['lens_type'] ?: 'Cam'))) . '</b><small>' . ($k['lens_value'] ? e($dio((string) $k['lens_value'])) : '') . '</small></span>'
        . '<span class="kutucuk' . ($stokta ? ' dolu' : '') . '">Stokta</span><span class="kutucuk' . (!$stokta ? ' dolu' : '') . '">' . e($stokta ? 'Depodan' : ($durumlar[$k['stock_status']][0] ?? 'Depodan')) . '</span></li>';
}
if ($camlar === '' && trim((string) $o['lens_type']) !== '') {
    $camlar = '<li><span class="goz">—</span><span class="cam-ad"><b>' . e((string) $o['lens_type']) . '</b></span></li>';
}

/* --- Tekliften seçilen katalog camı (marka · ürün · özellik): montajda doğru camı bulmak için --- */
$urun = '';
$teklif = row('SELECT * FROM quotes WHERE converted_order_id = ? LIMIT 1', [$oid]);
if (!$teklif && ($qg = row('SELECT quote_id FROM quote_gozlukler WHERE converted_order_id = ? LIMIT 1', [$oid]))) {
    $teklif = row('SELECT * FROM quotes WHERE id = ?', [(int) $qg['quote_id']]);
}
if ($teklif && ($teklif['tip'] ?? '') === 'katalog') {
    foreach (teklif_gozlukleri($teklif) as $gz) {
        if ((int) ($gz['converted_order_id'] ?? 0) !== $oid) {
            continue;
        }
        foreach ($gz['secenekler'] as $s) {
            if ((int) $s['no'] === (int) ($gz['secilen'] ?? 0)) {
                $urun = trim($s['cam'] . ($s['ozellik'] !== '' ? ' · ' . $s['ozellik'] : ''));
            }
        }
    }
}

/* --- Çerçeve: stoktaki kayıt varsa marka/model/renk ayrı, ölçü (kalibre□köprü) öne çıkar --- */
$cerceve = trim((string) $o['frame_info']);
$cerceveKayit = !empty($o['frame_item_id']) ? row('SELECT brand, model, color, size, shelf, barcode FROM frame_items WHERE id = ?', [(int) $o['frame_item_id']]) : null;
$cerceveOlcu = $cerceveKayit ? trim((string) $cerceveKayit['size']) : (preg_match('/\d{2}\s*[□x×-]\s*\d{2}/u', $cerceve, $m) ? $m[0] : '');

/* --- Notlar: reçete notu + sipariş notu (tekliften gelen fiyat satırları atölyeye basılmaz) --- */
$notlar = [];
foreach ([($rx ? $v('prescription_note') : ''), (string) ($o['notes'] ?? '')] as $n) {
    foreach (preg_split('/\R/u', trim($n)) ?: [] as $s) {
        $s = trim($s);
        if ($s !== '' && !preg_match('/^(Teklif #\d+|Cam .*₺)/u', $s)) {
            $notlar[] = $s;
        }
    }
}

$hesap = '';
if ($tutarlar && (string) $o['order_stage'] !== 'iptal') {
    $hesap = '<dl class="hesap"><div><dt>Tutar</dt><dd>' . money($o['total_amount']) . '</dd></div>'
        . ((float) $o['sgk_amount'] > 0 ? '<div><dt>SGK payı</dt><dd>−' . money($o['sgk_amount']) . '</dd></div>' : '')
        . '<div><dt>Alınan</dt><dd>' . money($o['paid']) . '</dd></div>'
        . '<div class="kalan"><dt>Kalan</dt><dd>' . money($o['balance']) . '</dd></div></dl>';
}
$qrSip = $siparisUrl !== '' ? qr_svg($siparisUrl, 80, 'M', '#141012') : '';
$qr = $takip !== '' ? qr_svg($takip, 120, 'M', '#141012') : '';
$secim = static fn(string $kagit, string $ad3) => '<a href="' . e(url_with(['kagit' => $kagit === 'a4' ? null : $kagit, 'tam' => null, 'tezgah' => null, 'fis' => '1'])) . '"' . (($kagit === 'yarim') === $yarim ? ' class="secili" aria-current="true"' : '') . '>' . $ad3 . '</a>';

/** OptiFlow işareti (pz_logo ile aynı geometri; renkli degrade ya da tek renk). */
$ckAyar = cikti_ayar();   // 4.53.0 çıktı görünümü: renk, işaret, slogan, alt yazı
$ckPalet = cikti_palet($ckAyar['renk']);
$isaret = static function (string $id, string $tek = '') use ($ckAyar, $ckPalet): string {
    if ($ckAyar['isaret'] !== 'mercek') {
        $i = cikti_isaret($ckAyar);
        return $i === '' ? '' : '<span class="isaret ck-isaret">' . $i . '</span>';
    }
    $fill = $tek !== '' ? $tek : 'url(#' . $id . ')';
    return '<svg class="isaret" viewBox="0 0 100 100" aria-hidden="true">'
        . ($tek === '' ? '<defs><linearGradient id="' . $id . '" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="' . $ckPalet['vurgu'] . '"/><stop offset=".55" stop-color="' . $ckPalet['parlak'] . '"/><stop offset="1" stop-color="' . $ckPalet['koyu'] . '"/></linearGradient></defs>' : '')
        . '<g transform="rotate(-10 50 50)"><path fill-rule="evenodd" clip-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z" fill="' . $fill . '"/><circle cx="50" cy="50" r="7" fill="' . $fill . '"/></g></svg>';
};
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Fiş <?= e($no) ?> · <?= e($ad) ?></title>
<link rel="stylesheet" href="<?= e(asset('siparis-fisi.css')) ?>">
<?= cikti_stil_tag($ckAyar) ?>
<style>@page { size: <?= $yarim ? '210mm 148.5mm' : 'A4 portrait' ?>; margin: 0; }</style>
</head>
<body class="<?= $yarim ? 'kagit-yarim' : 'kagit-a4' ?>"<?= $tezgah ? ' data-otomatik-yazdir' : '' ?>>
<div class="arac no-print">
  <button type="button" data-print>Yazdır</button><?php if ($tezgah): ?><a class="dugme" href="tezgah.php">Sıradaki müşteri</a><a class="dugme ghost" href="order.php?id=<?= $oid ?>">Siparişi aç</a><?php else: ?><button type="button" class="ghost" data-close>Kapat</button><?php endif; ?>
  <nav class="kagit-sec" aria-label="Kâğıt"><?= $secim('a4', 'A4 · üst yarı') ?><?= $secim('yarim', 'Kesilmiş yarım sayfa') ?></nav>
  <nav class="kagit-sec" aria-label="Müşteri nüshası"><a href="<?= e(url_with(['nusha' => null, 'tezgah' => null, 'fis' => '1'])) ?>"<?= $nusha ? ' class="secili" aria-current="true"' : '' ?>>Müşteri nüshalı</a><a href="<?= e(url_with(['nusha' => '0', 'tezgah' => null, 'fis' => '1'])) ?>"<?= !$nusha ? ' class="secili" aria-current="true"' : '' ?>>Yalnız iş emri</a></nav>
  <span><?= $yarim ? 'Kesilmiş yarım sayfayı (210 × 148 mm) yazıcıya <b>uzun kenarı önde</b> koyun; yazdırma ayarında kâğıt boyutu <b>A5</b>, yön <b>yatay</b>, ölçek <b>%100</b>.' . ($nusha ? ' Müşteri nüshası ikinci sayfadır.' : '') : ($nusha ? 'Üst yarı atölye iş emri (zarfa), alt yarı müşteri nüshası; kesme çizgisinden kesin.' : 'İş emri A4\'ün üst yarısına basılır; kesme çizgisinden kesin. Alt yarıyı bir sonraki fiş için saklayın (yukarıdan "Kesilmiş yarım sayfa").') ?> Renkli yazıcı gerekmez.</span>
  <a class="tam" href="<?= e(url_with(['tam' => 1, 'kagit' => null, 'tezgah' => null])) ?>">Ayrıntılı A4 müşteri fişi</a>
</div>
<div class="kagit">
<?php /* İŞ EMRİ — 148,5 × 210 mm dikey kart; A4'ün üst yarısına (ya da kesilmiş yarım sayfaya) 90° döndürülerek basılır.
         Kesince kâğıdı saat yönünde çevirin: kart dik okunur. */ ?>
<main class="fis is-emri-yuva">
  <div class="is-emri d-<?= e($ckAyar['atolye']) ?><?= count($kalemler) > 2 || count($bloklar) > 1 ? ' sik' : '' ?>">
    <header class="ie-bant">
      <div class="ie-sol">
        <div class="marka acik"><?= $isaret('isIsaret') ?><span><b><?= e($dukkan) ?></b><small>Atölye iş emri</small></span></div>
        <div class="ie-no"><small>Sipariş</small><b><?= e($no) ?></b><?php if (trim((string) ($o['raf_yeri'] ?? '')) !== ''): ?><small>Kutu</small><b class="ie-kutu"><?= e((string) $o['raf_yeri']) ?></b><?php endif; ?></div>
      </div>
      <div class="ie-soz"><small>Teslim sözü</small><b><?= e($sozGun ?: '—') ?></b><i><?= e($sozHafta) ?></i></div>
      <?php if ($qrSip !== ''): ?><div class="ie-qr"><?= $qrSip ?></div><?php endif; ?>
    </header>

    <section class="ie-kisi">
      <div class="ad"><?= e($ad) ?></div>
      <div class="bilgi">
        <span><small>Telefon</small><b><?= e(phone_display($o['c_phone'])) ?></b></span>
        <span><small>Alındı</small><b><?= e(date_tr($o['created_at'])) ?><?= $o['created_by_name'] ? ' · ' . e((string) $o['created_by_name']) : '' ?></b></span>
        <?php if ((string) $o['sgk_erecete'] !== ''): ?><span><small>e-Reçete</small><b class="mono"><?= e((string) $o['sgk_erecete']) ?></b></span><?php endif; ?>
        <?php if ($rx && $v('doctor') !== ''): ?><span><small>Doktor</small><b><?= e($v('doctor')) ?></b></span><?php endif; ?>
      </div>
    </section>

    <?php if ($rx): ?>
    <section class="ie-blok recete">
      <h2><i>01</i>Reçete<?php if ($kullanim !== ''): ?> <span class="etiket"><?= e($kullanim) ?></span><?php endif; ?><small><?= e(date_tr($rx['prescription_date'])) ?></small></h2>
      <table>
        <thead><tr><th colspan="2"></th><th>SPH</th><th>CYL</th><th>AKS</th><?= $addSutun ? '<th>ADD</th>' : '' ?></tr></thead>
        <tbody><?= $satir ?></tbody>
      </table>
    </section>
    <?php endif; ?>

    <section class="ie-blok">
      <h2><i>02</i>Montaj ölçüleri<small>boşsa ölçüp yazın</small></h2>
      <div class="olculer">
        <div><small>PD</small><?php if ($v('right_pd') !== '' || $v('left_pd') !== ''): ?><b>R <?= $hucre($dio($v('right_pd'))) ?> · L <?= $hucre($dio($v('left_pd'))) ?></b><?php elseif ($v('pd') !== ''): ?><b><?= e($dio($v('pd'))) ?><i>mm</i></b><?php else: ?><span class="yaz"></span><?php endif; ?></div>
        <div><small>Yükseklik</small><?php if ($yukVar): ?><b>R <?= $hucre($dio($v('right_height'))) ?> · L <?= $hucre($dio($v('left_height'))) ?></b><?php else: ?><span class="yaz"></span><?php endif; ?></div>
        <div><small>Çerçeve ölçüsü</small><?= $cerceveOlcu !== '' ? '<b>' . e($cerceveOlcu) . '</b>' : '<span class="yaz"></span>' ?></div>
      </div>
    </section>

    <section class="ie-blok">
      <h2><i>03</i>Camlar ve çerçeve</h2>
      <?php if ($urun !== ''): ?><p class="urun"><?= e($urun) ?></p><?php endif; ?>
      <ul class="camlar"><?= $camlar ?: '<li class="bos">—</li>' ?></ul>
      <p class="cerceve"><span class="goz kare-goz">Ç</span><b><?= e($cerceveKayit ? trim(implode(' · ', array_filter([(string) $cerceveKayit['brand'], (string) $cerceveKayit['model'], (string) $cerceveKayit['color']]))) : ($cerceve ?: 'Belirtilmedi')) ?></b><?php if ($cerceveKayit && (trim((string) $cerceveKayit['shelf']) !== '' || trim((string) $cerceveKayit['barcode']) !== '')): ?><small><?= e(trim(($cerceveKayit['shelf'] ? 'Raf ' . $cerceveKayit['shelf'] : '') . ($cerceveKayit['barcode'] ? ' · ' . $cerceveKayit['barcode'] : ''))) ?></small><?php endif; ?></p>
    </section>

    <section class="ie-blok adimlar">
      <h2><i>04</i>Atölye</h2>
      <table>
        <thead><tr><th>Adım</th><th>Tamam</th><th>Paraf</th><th>Saat</th></tr></thead>
        <tbody>
          <?php foreach (['Cam geldi', 'Kesim', 'Montaj', 'Kontrol · PD / aks', 'Müşteriye haber'] as $adim): ?>
            <tr><th><?= e($adim) ?></th><td><span class="kare"></span></td><td></td><td></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section class="ie-not">
      <small>Not</small>
      <p><?= $notlar ? nl2br(e(implode("\n", $notlar))) : '' ?></p>
    </section>
    <footer class="ie-son"><?php if ($tutarlar && (string) $o['order_stage'] !== 'iptal' && (float) $o['balance'] > 0.009): ?><span class="kalan-not">Kalan <?= money($o['balance']) ?></span><?php endif; ?><span><?= e($no) ?> · basım <?= e(date('d.m.Y H:i')) ?></span></footer>
  </div>
</main>
<?php if (!$yarim): ?>
<div class="kesim" aria-hidden="true"></div>
<?php endif; ?>
<?php if ($nusha):
  $tel = trim(setting('shop_phone'));
  $adres = trim(setting('shop_address'));
  $camOzet = $urun !== '' ? $urun : (implode(' · ', array_unique(array_filter(array_map(static fn($k) => trim((string) $k['lens_type']), $kalemler)))) ?: trim((string) $o['lens_type']));
  $ilkAd = tr_title(explode(' ', trim((string) $o['c_first']))[0] ?? '');
  $hazir = public_stage((string) $o['order_stage'])['adim'] >= 3;
  /* Göz eşeli: gerçek bir göz tablosu gibi satır satır küçülen harfler; yukarıdan aşağı okununca mesaj çıkar. */
  $eselSatir = [['G', '0,1'], ['ÖZ', '0,2'], ['LÜĞ', '0,3'], ['ÜNÜZ', '0,5'], ['HAZIR', '0,7']];
  if (!$hazir) {
      $eselSatir[] = ['LANIYOR', '1,0'];
  }
  $harfler = static fn(string $s): string => implode(' ', mb_str_split($s));
  $kontrol = strtotime('+1 year', strtotime((string) $o['created_at']) ?: time());
  $kontrolAy = $aylar[(int) date('n', $kontrol)] . ' ' . date('Y', $kontrol);
  if ($yarim): ?></div><div class="kagit"><?php endif; ?>
<section class="fis nusha d-<?= e($ckAyar['musteri']) ?>">
  <svg class="filigran" viewBox="0 0 200 200" aria-hidden="true">
    <circle cx="100" cy="100" r="98" /><circle cx="100" cy="100" r="74" /><circle cx="100" cy="100" r="50" /><circle cx="100" cy="100" r="26" />
    <line x1="100" y1="0" x2="100" y2="200" /><line x1="0" y1="100" x2="200" y2="100" />
  </svg>
  <div class="n-sol">
    <header class="n-ust">
      <div class="marka"><?= $isaret('nIsaret') ?><span><b><?= e($dukkan) ?></b><small>Müşteri nüshası</small><?= $ckAyar['slogan'] !== '' ? '<em>' . e($ckAyar['slogan']) . '</em>' : '' ?></span></div>
      <div class="n-no"><small>Sipariş</small><b><?= e($no) ?></b></div>
    </header>
    <div class="n-orta">
      <figure class="tablo" aria-label="<?= e($hazir ? 'Gözlüğünüz hazır.' : 'Gözlüğünüz hazırlanıyor.') ?>">
        <figcaption><?= e(trim($ilkAd . ' ' . tr_title((string) $o['c_last']))) ?> için</figcaption>
        <?php foreach ($eselSatir as $i => [$s, $deger]): ?>
          <div class="t-<?= $i + 1 ?>"><i><?= e($deger) ?></i><b><?= e($harfler($s)) ?></b></div>
        <?php endforeach; ?>
        <p>Yukarıdan aşağı okuyun.</p>
      </figure>
      <div class="sirada">
        <h3>Sırada ne var?</h3>
        <ol>
          <li><i>01</i><b>Hazır olunca haber veriyoruz</b><span><?= $o['c_phone'] ? e(phone_display($o['c_phone'])) . ' numarasına mesaj gelir.' : 'Size telefonla ulaşırız.' ?></span></li>
          <li><i>02</i><b>Bu nüshayla teslim alın</b><span>Gözlüğü birlikte takıp ayarını yapıyoruz.</span></li>
          <li><i>03</i><b>Bir yıl sonra göz kontrolü</b><span>Önerilen: <em><?= e($kontrolAy) ?></em>. Numaranız değişmiş olabilir.</span></li>
        </ol>
      </div>
    </div>
    <dl class="kart">
      <?php $gozlukTuru = match ($tasarim) { 'tek_odak_yakin' => 'Yakın (okuma) gözlüğü', 'tek_odak_uzak' => 'Uzak gözlüğü', 'ayri_uzak_yakin' => 'Uzak ve yakın · iki ayrı gözlük', 'progressive' => 'Çok odaklı (progresif)', default => $kullanim }; ?>
      <?php if ($gozlukTuru !== ''): ?><div class="tur-satir"><dt>Gözlük</dt><dd><?= e($gozlukTuru) ?></dd></div><?php endif; ?>
      <?php if ($camOzet !== ''): ?><div><dt>Cam</dt><dd><?= e($camOzet) ?></dd></div><?php endif; ?>
      <div><dt>Çerçeve</dt><dd><?= e($cerceve ?: '—') ?></dd></div>
      <?php if ((string) $o['sgk_erecete'] !== ''): ?><div><dt>e-Reçete</dt><dd class="mono"><?= e((string) $o['sgk_erecete']) ?></dd></div><?php endif; ?>
    </dl>
    <footer class="n-son">
      <p><b><?= e($dukkan) ?></b><?= $tel !== '' ? ' · ' . e(phone_display($tel) ?: $tel) : '' ?><?= $adres !== '' ? ' · ' . e(preg_replace('/\s*\R\s*/u', ', ', $adres)) : '' ?></p>
      <p class="ipucu">Teslim alırken bu nüshayı getirin. Gözlüğü iki elle takıp çıkarın; camları yalnız mikrofiber bezle silin.</p>
      <?php if ($ckAyar['alt_yazi'] !== ''): ?><p class="tesekkur"><?= e($ckAyar['alt_yazi']) ?></p><?php endif; ?>
    </footer>
  </div>
  <aside class="n-sag">
    <div class="kocan-no"><small>Sipariş</small><b><?= e($no) ?></b><i>Teslim alırken bu koçanı gösterin</i></div>
    <div class="teslim"><small>Teslim sözümüz</small><b><?= e($sozGun ?: '—') ?></b><i><?= e($sozHafta) ?></i></div>
    <div class="nisangah">
      <svg class="halka" viewBox="0 0 200 200" aria-hidden="true">
        <circle cx="100" cy="100" r="96" /><circle cx="100" cy="100" r="84" class="ince" />
        <?php for ($a = 0; $a < 360; $a += 10): $r1 = $a % 90 === 0 ? 82 : ($a % 30 === 0 ? 88 : 92); $rad = deg2rad($a); ?>
          <line x1="<?= round(100 + $r1 * cos($rad), 2) ?>" y1="<?= round(100 + $r1 * sin($rad), 2) ?>" x2="<?= round(100 + 96 * cos($rad), 2) ?>" y2="<?= round(100 + 96 * sin($rad), 2) ?>" />
        <?php endfor; ?>
      </svg>
      <?php if ($qr !== ''): ?><div class="qr-merkez"><?= $qr ?></div><?php else: ?><div class="qr-merkez yok"><b><?= e($no) ?></b></div><?php endif; ?>
    </div>
    <p class="okut"><?= $qr !== '' ? 'Telefonunuzla okutun,<br>siparişinizi anında görün.' : 'Sipariş numaranızla sorabilirsiniz.' ?></p>
    <?= $hesap !== '' ? '<div class="odeme">' . $hesap . '</div>' : '' ?>
  </aside>
</section>
<?php endif; ?>
</div>
<script src="<?= e(asset('print.js')) ?>" defer></script>
</body>
</html>
