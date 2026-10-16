<?php
declare(strict_types=1);

/* ==========================================================================
   Sipariş fişi, yarım sayfa. print.php?type=order&id=<sipariş> (ayrıntılı A4 müşteri fişi: &tam=1).
   Kâğıt (&kagit=):
     a4    (varsayılan) A4 dikey; ÜST YARI atölye iş emri (210 × 148,5 mm), ortada kesme çizgisi, ALT YARI müşteri nüshası.
     yarim kesilmiş yarım sayfa (A5 yatay) yazıcıya konunca; müşteri nüshası ikinci sayfa.
   &nusha=0 müşteri nüshasını kapatır. &tezgah=1 (akışın ⑤. adımı): yazdırma penceresi kendiliğinden açılır.
   4.35.0 — Kurumsal kimlikle yeniden tasarım (docs/kurumsal-kimlik/project/README.md):
     İş emri: montaj personeli için büyük mono dioptri, göz başına PD + yükseklik, cam kalemleri (göz, grup, stok),
       çerçeve ölçüsü, seçilen katalog camı, imzalı atölye adımları; "Siparişi aç" karekodu.
     Müşteri nüshası: göz eşeli selamlama (markanın tipografik imzası), lensmetre nişangâhı içinde takip karekodu,
       dört aşamalı durum çizgisi, gözlük kartı ve ödeme özeti.
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
$rx = row('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph FROM prescription_records r LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.order_id = ? ORDER BY r.prescription_date DESC, r.id DESC LIMIT 1', [$oid]);
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

/* --- Reçete tablosu: göz başına SPH CYL AKS ADD (montaj ölçüleri ayrı kutuda) --- */
$yukVar = $v('right_height') !== '' || $v('left_height') !== '';
$satir = '';
if ($rx) {
    foreach (['right' => ['R', 'Sağ'], 'left' => ['L', 'Sol']] as $t => [$kod, $gozAd]) {
        $satir .= '<tr><th><span class="goz">' . $kod . '</span>' . $gozAd . '</th>'
            . '<td>' . $hucre($dio($v($t . '_sph'))) . '</td><td>' . $hucre($dio($v($t . '_cyl'))) . '</td>'
            . '<td>' . $hucre($v($t . '_axis') !== '' ? $v($t . '_axis') . '°' : '') . '</td>'
            . ($addVar ? '<td>' . $hucre($dio($v($t . '_add'))) . '</td>' : '')
            . '</tr>';
    }
}
$sutun = 4 + ($addVar ? 1 : 0);

/* --- Cam kalemleri --- */
$gruplar = ['uzak' => 'Uzak', 'yakin' => 'Yakın', 'cok_odak' => 'Çok odak'];
$camlar = '';
foreach ($kalemler as $k) {
    $stokta = $k['stock_status'] === 'stokta_var';
    $camlar .= '<li><span class="goz">' . e((string) $k['eye']) . '</span>'
        . '<span class="cam-ad"><b>' . e(trim((string) ($k['lens_type'] ?: 'Cam'))) . '</b><small>' . e($gruplar[(string) $k['item_group']] ?? '') . ($k['lens_value'] ? ' · ' . e($dio((string) $k['lens_value'])) : '') . '</small></span>'
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
$isaret = static function (string $id, string $tek = ''): string {
    $fill = $tek !== '' ? $tek : 'url(#' . $id . ')';
    return '<svg class="isaret" viewBox="0 0 100 100" aria-hidden="true">'
        . ($tek === '' ? '<defs><linearGradient id="' . $id . '" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#ff6b81"/><stop offset=".55" stop-color="#d0334f"/><stop offset="1" stop-color="#8f1a2e"/></linearGradient></defs>' : '')
        . '<g transform="rotate(-10 50 50)"><path fill-rule="evenodd" clip-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z" fill="' . $fill . '"/><circle cx="50" cy="50" r="7" fill="' . $fill . '"/></g></svg>';
};
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Fiş <?= e($no) ?> · <?= e($ad) ?></title>
<link rel="stylesheet" href="<?= e(asset('siparis-fisi.css')) ?>">
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
<main class="fis is-emri">
  <header class="bant">
    <div class="marka"><?= $isaret('isIsaret') ?><span><b><?= e($dukkan) ?></b><small>Atölye iş emri</small></span></div>
    <div class="no-kutu"><small>Sipariş</small><b><?= e($no) ?></b></div>
    <div class="soz-kutu"><small>Teslim sözü</small><b><?= e($sozGun ?: '—') ?></b><span><?= e($sozHafta) ?></span></div>
    <?php if ($qrSip !== ''): ?><div class="qr-kucuk"><?= $qrSip ?><small>Siparişi aç</small></div><?php endif; ?>
  </header>
  <div class="govde">
    <div class="sol">
      <section class="kisi">
        <div class="ad"><?= e($ad) ?></div>
        <div class="bilgi">
          <span><small>Telefon</small><b><?= e(phone_display($o['c_phone'])) ?></b></span>
          <span><small>Alındı</small><b><?= e(date_tr($o['created_at'])) ?></b></span>
          <span><small>Satış</small><b><?= e((string) ($o['created_by_name'] ?: '—')) ?></b></span>
          <?php if ((string) $o['sgk_erecete'] !== ''): ?><span><small>e-Reçete</small><b class="mono"><?= e((string) $o['sgk_erecete']) ?></b></span><?php endif; ?>
        </div>
      </section>
      <?php if ($rx): ?>
      <section class="recete">
        <h2>Reçete<?php if ($kullanim !== ''): ?> <span class="etiket"><?= e($kullanim) ?></span><?php endif; ?></h2>
        <table>
          <thead><tr><th></th><th>SPH</th><th>CYL</th><th>AKS</th><?= $addVar ? '<th>ADD</th>' : '' ?></tr></thead>
          <tbody><?= $satir ?>
            <?php if ($yakinVar): ?><tr class="yakin"><th><span class="goz">Y</span>Yakın</th><td colspan="<?= $sutun - 1 ?>">Sağ <b><?= $hucre($dio($v('near_right_sph'))) ?></b> · Sol <b><?= $hucre($dio($v('near_left_sph'))) ?></b><?= $v('near_lens_type') !== '' ? ' · ' . e($v('near_lens_type')) : '' ?></td></tr><?php endif; ?>
          </tbody>
        </table>
        <div class="alt-bilgi">
          <span><small>Reçete</small><b><?= e(date_tr($rx['prescription_date'])) ?></b></span>
          <?php if ($v('doctor') !== ''): ?><span><small>Doktor</small><b><?= e($v('doctor')) ?></b></span><?php endif; ?>
        </div>
      </section>
      <?php endif; ?>
      <section class="montaj">
        <h2>Montaj ölçüleri <small>boşsa ölçüp yazın</small></h2>
        <div class="olculer">
          <div><small>PD</small><?php if ($v('right_pd') !== '' || $v('left_pd') !== ''): ?><b>R <?= $hucre($dio($v('right_pd'))) ?> · L <?= $hucre($dio($v('left_pd'))) ?></b><?php elseif ($v('pd') !== ''): ?><b><?= e($dio($v('pd'))) ?> <i>mm</i></b><?php else: ?><span class="yaz"></span><?php endif; ?></div>
          <div><small>Yükseklik</small><?php if ($yukVar): ?><b>R <?= $hucre($dio($v('right_height'))) ?> · L <?= $hucre($dio($v('left_height'))) ?></b><?php else: ?><span class="yaz"></span><?php endif; ?></div>
          <div><small>Çerçeve ölçüsü</small><?= $cerceveOlcu !== '' ? '<b>' . e($cerceveOlcu) . '</b>' : '<span class="yaz"></span>' ?></div>
        </div>
      </section>
      <section class="cerceve-blok">
        <h2>Çerçeve</h2>
        <div class="cerceve-satir">
          <p class="cerceve"><?= e($cerceveKayit ? trim(implode(' · ', array_filter([(string) $cerceveKayit['brand'], (string) $cerceveKayit['model'], (string) $cerceveKayit['color']]))) : ($cerceve ?: '—')) ?></p>
        </div>
        <?php if ($cerceveKayit && (trim((string) $cerceveKayit['shelf']) !== '' || trim((string) $cerceveKayit['barcode']) !== '')): ?><small class="kucuk"><?= e(trim(($cerceveKayit['shelf'] ? 'Raf ' . $cerceveKayit['shelf'] : '') . ($cerceveKayit['barcode'] ? '  ·  Barkod ' . $cerceveKayit['barcode'] : ''))) ?></small><?php endif; ?>
      </section>
    </div>
    <div class="sag">
      <section class="cam-blok">
        <h2>Camlar</h2>
        <?php if ($urun !== ''): ?><p class="urun"><?= e($urun) ?></p><?php endif; ?>
        <ul class="camlar"><?= $camlar ?: '<li class="bos">—</li>' ?></ul>
      </section>
      <section class="adimlar">
        <h2>Atölye <small>işaretleyin · paraf</small></h2>
        <ol>
          <?php foreach (['Cam geldi', 'Kesim', 'Montaj', 'Kontrol · PD/aks', 'Haber verildi'] as $adim): ?>
            <li><span class="kare"></span><b><?= e($adim) ?></b><span class="paraf"></span></li>
          <?php endforeach; ?>
        </ol>
      </section>
      <section class="not">
        <small>Not</small>
        <p><?= $notlar ? nl2br(e(implode("\n", $notlar))) : '' ?></p>
      </section>
      <footer><?php if ($tutarlar && (string) $o['order_stage'] !== 'iptal' && (float) $o['balance'] > 0.009): ?><span class="kalan-not">Kalan <?= money($o['balance']) ?></span><?php endif; ?><span><?= e($no) ?> · <?= e(date('d.m.Y H:i')) ?></span></footer>
    </div>
  </div>
</main>
<?php if (!$yarim): ?>
<div class="kesim" aria-hidden="true"><span>✂ Buradan kesin<?= $nusha ? ' · alt yarı müşteriye' : ' · alt yarıyı bir sonraki fiş için yazıcıya koyabilirsiniz' ?></span></div>
<?php endif; ?>
<?php if ($nusha):
  $tel = trim(setting('shop_phone'));
  $adres = trim(setting('shop_address'));
  $camOzet = $urun !== '' ? $urun : (implode(' · ', array_unique(array_filter(array_map(static fn($k) => trim((string) $k['lens_type']), $kalemler)))) ?: trim((string) $o['lens_type']));
  $ilkAd = tr_title(explode(' ', trim((string) $o['c_first']))[0] ?? '');
  $asama = public_stage((string) $o['order_stage']);
  $asamalar = [1 => 'Alındı', 2 => 'Atölyede', 3 => 'Hazır', 4 => 'Teslim'];
  if ($yarim): ?></div><div class="kagit"><?php endif; ?>
<section class="fis nusha">
  <div class="serit" aria-hidden="true"><span><?= e($dukkan) ?> · <?= e($no) ?></span></div>
  <div class="n-govde">
    <header class="n-ust">
      <div class="marka"><?= $isaret('nIsaret') ?><span><b><?= e($dukkan) ?></b><small>Müşteri nüshası</small></span></div>
      <div class="n-no"><small>Sipariş</small><b><?= e($no) ?></b></div>
    </header>
    <div class="n-orta">
      <div class="esel" aria-label="<?= e(($ilkAd !== '' ? $ilkAd . ', ' : '') . 'gözlüğünüz hazırlanıyor.') ?>">
        <div class="esel-1"><span><?= e($ilkAd !== '' ? $ilkAd . ',' : 'Merhaba,') ?></span><i>0,1</i></div>
        <div class="esel-2"><span>gözlüğünüz</span><i>0,3</i></div>
        <div class="esel-3"><span><?= e($asama['adim'] >= 3 ? 'hazır.' : 'hazırlanıyor.') ?></span><i>0,6</i></div>
        <div class="esel-4"><span><?= $sozGun !== '' ? 'Teslim sözümüz <b>' . e($sozGun . ' ' . $sozHafta) . '</b>.' : 'Hazır olunca size haber veriyoruz.' ?></span><i>1,0</i></div>
      </div>
      <div class="nisangah">
        <svg class="halka" viewBox="0 0 200 200" aria-hidden="true">
          <circle cx="100" cy="100" r="96" /><circle cx="100" cy="100" r="84" class="ince" /><circle cx="100" cy="100" r="72" class="ince" />
          <?php for ($a = 0; $a < 360; $a += 15): $r1 = $a % 90 === 0 ? 84 : ($a % 45 === 0 ? 88 : 91); $rad = deg2rad($a); ?>
            <line x1="<?= round(100 + $r1 * cos($rad), 2) ?>" y1="<?= round(100 + $r1 * sin($rad), 2) ?>" x2="<?= round(100 + 96 * cos($rad), 2) ?>" y2="<?= round(100 + 96 * sin($rad), 2) ?>" />
          <?php endfor; ?>
          <line x1="100" y1="0" x2="100" y2="20" class="arti" /><line x1="100" y1="180" x2="100" y2="200" class="arti" /><line x1="0" y1="100" x2="20" y2="100" class="arti" /><line x1="180" y1="100" x2="200" y2="100" class="arti" />
        </svg>
        <?php if ($qr !== ''): ?>
          <div class="qr-merkez"><?= $qr ?></div>
        <?php else: ?>
          <div class="qr-merkez yok"><b><?= e($no) ?></b></div>
        <?php endif; ?>
        <small><?= $qr !== '' ? 'Okutun: siparişiniz nerede?' : 'Sipariş numaranız' ?></small>
      </div>
    </div>
    <ol class="yol" aria-label="Sipariş durumu">
      <?php foreach ($asamalar as $n => $etiket): $cls = $asama['adim'] > $n ? 'bitti' : ($asama['adim'] === $n ? 'simdi' : ''); ?>
        <li class="<?= $cls ?>"><span class="nokta"><?= $asama['adim'] > $n ? '✓' : $n ?></span><b><?= e($etiket) ?></b><?php if ($cls === 'simdi'): ?><em class="damga">Şu an</em><?php endif; ?></li>
      <?php endforeach; ?>
    </ol>
    <div class="n-alt">
      <dl class="kart">
        <?php if ($camOzet !== ''): ?><div><dt>Cam</dt><dd><?= e($camOzet) ?></dd></div><?php endif; ?>
        <div><dt>Çerçeve</dt><dd><?= e($cerceve ?: '—') ?></dd></div>
        <?php if ((string) $o['sgk_erecete'] !== ''): ?><div><dt>e-Reçete</dt><dd class="mono"><?= e((string) $o['sgk_erecete']) ?></dd></div><?php endif; ?>
      </dl>
      <?= $hesap !== '' ? '<div class="odeme">' . $hesap . '</div>' : '' ?>
    </div>
    <footer class="n-son">
      <p><b><?= e($dukkan) ?></b><?= $tel !== '' ? ' · ' . e(phone_display($tel) ?: $tel) : '' ?><?= $adres !== '' ? ' · ' . e(preg_replace('/\s*\R\s*/u', ', ', $adres)) : '' ?></p>
      <p class="ipucu">Teslim alırken bu nüshayı getirmeniz yeterli. Gözlüğünüzü iki elinizle takıp çıkarın; camları yalnız mikrofiber bezle silin.</p>
    </footer>
  </div>
</section>
<?php endif; ?>
</div>
<script src="<?= e(asset('print.js')) ?>" defer></script>
</body>
</html>
