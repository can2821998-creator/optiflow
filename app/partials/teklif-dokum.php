<?php
/* ==========================================================================
   4.21.1 — Katalog teklifi dökümü (print.php?type=quote, quotes.tip = 'katalog').
   4.22.0: ortak döküm başlığı/kartları (app/dokum.php, iki tasarım) + teklife özel seçenek kartları (assets/teklif-dokum.css).
   4.26.0: çok gözlüklü teklif (ör. uzak + yakın) — her gözlük kendi başlığı (çerçeve, kullanım, SGK) ve seçenek kartlarıyla,
   sonda toplam. Tek gözlükte görünüm eskisi gibi.
   Renkler print-color-adjust: exact ile tarayıcının "arka planları yazdır" ayarı kapalıyken de basılır.
   Girdi: $qt (quotes satırı), $shop. İş mantığı app/teklif.php.
   ========================================================================== */
declare(strict_types=1);

$gozlukler = teklif_gozlukleri($qt);
$cok = count($gozlukler) > 1;
$tumSecenekler = array_merge(...array_map(static fn($g) => $g['secenekler'], $gozlukler ?: [['secenekler' => []]]));
$sozluk = teklif_dokum_sozluk($tumSecenekler);
$hazirlayan = $qt['created_by'] ? (string) scalar('SELECT full_name FROM user_accounts WHERE id = ?', [(int) $qt['created_by']]) : '';
$olusturma = strtotime((string) $qt['created_at']) ?: time();
$gecerlilik = date('d.m.Y', $olusturma + TEKLIF_GECERLILIK_GUN * 86400);
$oranMetni = static fn(float $o): string => rtrim(rtrim(number_format($o, 2, ',', ''), '0'), ',');
$sgkToplam = array_sum(array_map(static fn($g) => (float) $g['sgk_amount'], $gozlukler));
$oran = (float) $qt['discount_rate'];
[$enAz, $enCok] = teklif_toplam_aralik($gozlukler);
$g1 = $gozlukler[0] ?? ['secenekler' => [], 'frame_desc' => '', 'frame_price' => 0, 'lens_design' => '', 'ad' => 'Gözlük'];
$seceneSayisi = count($tumSecenekler);
$adlar = implode(' + ', array_map(static fn($g) => $g['ad'], $gozlukler));

if ($cok) {
    $metin = mb_strtolower($adlar, 'UTF-8') . ' için hazırladığımız seçenekleri gözlük gözlük karşılaştırabilirsiniz. Her gözlüğün fiyatına cam (çift) ve çerçevesi dahildir'
        . ($sgkToplam > 0 ? '; Medula (SGK) katkı payınız her gözlükte ayrı düşülmüştür' : '') . '.';
    $alanlar = [
        ['goz', 'Gözlükler', count($gozlukler) . ' gözlük: ' . $adlar],
        ['sgk', 'Medula (SGK) payı', $sgkToplam > 0 ? money($sgkToplam) . ' (tahmini, toplam)' : 'Uygulanmadı'],
        ['yuzde', 'Size özel iskonto', $oran > 0 ? '%' . $oranMetni($oran) : '—'],
        ['cerceve', 'Toplam', $enAz === $enCok ? money($enAz) : money($enAz) . ' – ' . money($enCok)],
    ];
    $vurgu = ['Toplam, başlayan fiyatlarla', money($enAz), count($gozlukler) . ' gözlük', 'acik'];
} else {
    $sayi = count($g1['secenekler']);
    $metin = 'Gözlüğünüz için hazırladığımız ' . ($sayi > 1 ? $sayi . ' seçeneği yan yana karşılaştırabilirsiniz' : 'teklifin ayrıntıları aşağıdadır')
        . '. Fiyatlara cam (çift) ve çerçeve dahildir' . ($sgkToplam > 0 ? '; Medula (SGK) katkı payınız düşülmüştür' : '') . '.';
    $alanlar = [
        ['cerceve', 'Çerçeve', trim((string) $g1['frame_desc'] . ((float) $g1['frame_price'] > 0 ? ' · ' . money($g1['frame_price']) : ''))],
        ['goz', 'Kullanım', lens_designs()[(string) $g1['lens_design']] ?? '—'],
        ['sgk', 'Medula (SGK) payı', $sgkToplam > 0 ? money($sgkToplam) . ' (tahmini)' : 'Uygulanmadı'],
        ['yuzde', 'Size özel iskonto', $oran > 0 ? '%' . $oranMetni($oran) : '—'],
    ];
    $vurgu = $sayi > 1 ? ['Başlayan fiyatlarla', money($enAz), $sayi . ' seçenek', 'acik'] : null;
}
?><?= dokum_sayfa_bas('Fiyat teklifi #' . (int) $qt['id'] . ' · ' . (string) $qt['customer_name'], ['teklif-dokum.css']) ?>
<?= dokum_bas([
    'etiket' => 'Fiyat teklifi', 'no' => dokum_no((int) $qt['id']), 'tarih' => date('d.m.Y', $olusturma),
    'kucuk' => 'Sayın', 'baslik' => (string) $qt['customer_name'], 'rozet' => ['', 'saat', 'Geçerlilik ' . $gecerlilik],
    'metin' => e(mb_strtoupper(mb_substr($metin, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($metin, 1, null, 'UTF-8')),
    'rota' => [['Teklif', date('d.m', $olusturma), date('Y', $olusturma)], ['Geçerli', substr($gecerlilik, 0, 5), 'son gün']],
    'vurgu' => $vurgu,
    'alanlar' => $alanlar,
]) ?>
  <?php foreach ($gozlukler as $gn => $gz):
      $secenekler = $gz['secenekler'];
      $onerilen = count($secenekler) > 1 ? count($secenekler) - 1 : -1;   // son seçenek "Önerimiz"
      $secilen = (int) ($gz['secilen'] ?? 0);
  ?>
    <?php if ($cok): ?>
      <div class="gozluk-bas">
        <span class="gozluk-no"><?= $gn + 1 ?></span>
        <div>
          <h2><?= e($gz['ad']) ?></h2>
          <p><?= e(trim((string) $gz['frame_desc'] . ((float) $gz['frame_price'] > 0 ? ' · ' . money($gz['frame_price']) : ''))) ?>
            · <?= e(lens_designs()[(string) $gz['lens_design']] ?? '—') ?>
            · <?= (float) $gz['sgk_amount'] > 0 ? 'SGK payı ' . e(money($gz['sgk_amount'])) : 'SGK\'sız' ?></p>
        </div>
      </div>
    <?php endif; ?>
    <section class="secenekler n<?= count($secenekler) ?>">
      <?php foreach ($secenekler as $i => $s): $h = $s['hesap']; $one = $i === $onerilen; $sec = $secilen === $s['no']; ?>
        <article class="kart<?= $one ? ' one' : '' ?><?= $sec ? ' secilen' : '' ?>">
          <?php if ($sec): ?><span class="serit">Seçiminiz</span><?php elseif ($one): ?><span class="serit">Önerimiz</span><?php endif; ?>
          <header>
            <span class="no">Seçenek <?= $i + 1 ?></span>
            <h2><?= e($s['baslik']) ?></h2>
          </header>
          <div class="cam">
            <b><?= e($s['cam']) ?></b>
            <ul class="ozellik">
              <?php foreach (teklif_ozellik_etiketleri((string) $s['ozellik']) as $et): ?><li><?= e($et) ?></li><?php endforeach; ?>
            </ul>
            <?php if (($s['not'] ?? '') !== ''): ?><p class="not"><?= e($s['not']) ?></p><?php endif; ?>
          </div>
          <dl class="k-hesap">
            <div><dt>Cam (çift)</dt><dd><?= money($h['cam']) ?></dd></div>
            <div><dt>Çerçeve</dt><dd><?= money($h['cerceve']) ?></dd></div>
            <div class="ara"><dt>Ara toplam</dt><dd><?= money($h['ara']) ?></dd></div>
            <?php if ($h['sgk'] > 0): ?><div class="eksi"><dt>Medula (SGK) payı</dt><dd>−<?= money($h['sgk']) ?></dd></div><?php endif; ?>
            <?php if ($h['iskonto'] > 0): ?><div class="eksi"><dt>İskonto %<?= e($oranMetni($h['oran'])) ?></dt><dd>−<?= money($h['iskonto']) ?></dd></div><?php endif; ?>
          </dl>
          <footer>
            <small>Ödeyeceğiniz tutar</small>
            <b><?= money($h['odenecek']) ?></b>
            <?php if ($h['sgk'] + $h['iskonto'] > 0.009): ?><em><?= money($h['sgk'] + $h['iskonto']) ?> avantaj</em><?php endif; ?>
          </footer>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <?php if ($cok): ?>
    <div class="toplam-serit">
      <span><b>Toplam · <?= count($gozlukler) ?> gözlük</b><small>Her gözlükten seçtiğiniz seçeneklerin toplamı</small></span>
      <strong><?= $enAz === $enCok ? money($enAz) : money($enAz) . ' – ' . money($enCok) ?></strong>
    </div>
  <?php endif; ?>

  <div class="alt-iki">
    <section class="kutu nasil">
      <h3>Hesap nasıl yapıldı?</h3>
      <ol>
        <li><b>Cam + çerçeve</b> fiyatları toplanır (ara toplam)<?= $cok ? ', her gözlük için ayrı' : '' ?>.</li>
        <?php if ($sgkToplam > 0): ?><li>Medula (SGK) katkı payınız <?= $cok ? 'her gözlükte ayrı' : '<b>' . money($sgkToplam) . '</b>' ?> düşülür.</li><?php endif; ?>
        <?php if ($oran > 0): ?><li>Kalan tutara <b>%<?= e($oranMetni($oran)) ?></b> iskonto uygulanır.</li><?php endif; ?>
        <li>Sonuç, mağazamıza <b>ödeyeceğiniz tutardır</b><?= $cok ? '; toplam, gözlüklerin tutarlarının toplamıdır' : '' ?>.</li>
      </ol>
      <?php if ($sgkToplam > 0): ?><p>SGK payı tahminidir; reçeteniz Medula'da işlendiğinde kesinleşir. Hakkınız son 2 yılda kullanılmışsa uygulanmaz.</p><?php endif; ?>
    </section>
    <?php if ($sozluk): ?>
      <section class="kutu sozluk">
        <h3>Camınızın özellikleri ne işe yarar?</h3>
        <dl>
          <?php foreach ($sozluk as $ad => $aciklama): ?><div><dt><?= e($ad) ?></dt><dd><?= e($aciklama) ?></dd></div><?php endforeach; ?>
        </dl>
      </section>
    <?php endif; ?>
  </div>

  <?= dokum_son(($qt['note'] ? '<p class="musteri-not"><b>Not:</b> ' . e((string) $qt['note']) . '</p>' : '') . '<p>Bu teklif <b>' . e($gecerlilik) . '</b> tarihine kadar geçerlidir ve bilgilendirme amaçlıdır; fiyatlar stok ve tedarikçi koşullarına göre değişebilir. Siparişiniz, seçtiğiniz seçenek ve reçetenizle birlikte kesinleşir.</p>', '<div class="imza"><small>Hazırlayan</small><b>' . e($hazirlayan !== '' ? $hazirlayan : $shop) . '</b><span>' . e($shop) . '</span></div>') ?>
<?= dokum_sayfa_son() ?>
