<?php
/* ==========================================================================
   4.21.1 — Katalog teklifi dökümü (print.php?type=quote, quotes.tip = 'katalog').
   4.22.0: ortak döküm başlığı/kartları (app/dokum.php, iki tasarım) + teklife özel seçenek kartları (assets/teklif-dokum.css).
   Renkler print-color-adjust: exact ile tarayıcının "arka planları yazdır" ayarı kapalıyken de basılır.
   Girdi: $qt (quotes satırı), $shop. İş mantığı app/teklif.php.
   ========================================================================== */
declare(strict_types=1);

$secenekler = teklif_secenekleri($qt);
$sozluk = teklif_dokum_sozluk($secenekler);
$hazirlayan = $qt['created_by'] ? (string) scalar('SELECT full_name FROM user_accounts WHERE id = ?', [(int) $qt['created_by']]) : '';
$adres = setting('shop_address', '');
$telefon = setting('shop_phone', '');
$olusturma = strtotime((string) $qt['created_at']) ?: time();
$gecerlilik = date('d.m.Y', $olusturma + TEKLIF_GECERLILIK_GUN * 86400);
$oranMetni = static fn(float $o): string => rtrim(rtrim(number_format($o, 2, ',', ''), '0'), ',');
$sgk = (float) $qt['sgk_amount'];
$oran = (float) $qt['discount_rate'];
$enUcuz = $secenekler ? min(array_map(static fn($s) => $s['hesap']['odenecek'], $secenekler)) : 0.0;
$onerilen = count($secenekler) > 1 ? count($secenekler) - 1 : -1;   // son seçenek "Önerimiz"
$secilen = (int) ($qt['secilen'] ?? 0);
?><?= dokum_sayfa_bas('Fiyat teklifi #' . (int) $qt['id'] . ' · ' . (string) $qt['customer_name'], ['teklif-dokum.css']) ?>
<?= dokum_bas([
    'etiket' => 'Fiyat teklifi', 'no' => dokum_no((int) $qt['id']), 'tarih' => date('d.m.Y', $olusturma),
    'kucuk' => 'Sayın', 'baslik' => (string) $qt['customer_name'], 'rozet' => ['', 'saat', 'Geçerlilik ' . $gecerlilik],
    'metin' => e('Gözlüğünüz için hazırladığımız ' . (count($secenekler) > 1 ? count($secenekler) . ' seçeneği yan yana karşılaştırabilirsiniz' : 'teklifin ayrıntıları aşağıdadır') . '. Fiyatlara cam (çift) ve çerçeve dahildir' . ($sgk > 0 ? '; Medula (SGK) katkı payınız düşülmüştür' : '') . '.'),
    'rota' => [['Teklif', date('d.m', $olusturma), date('Y', $olusturma)], ['Geçerli', substr($gecerlilik, 0, 5), 'son gün']],
    'vurgu' => count($secenekler) > 1 ? ['Başlayan fiyatlarla', money($enUcuz), count($secenekler) . ' seçenek', 'acik'] : null,
    'alanlar' => [
        ['cerceve', 'Çerçeve', trim((string) $qt['frame_desc'] . ((float) $qt['frame_price'] > 0 ? ' · ' . money($qt['frame_price']) : ''))],
        ['goz', 'Kullanım', lens_designs()[(string) $qt['lens_design']] ?? '—'],
        ['sgk', 'Medula (SGK) payı', $sgk > 0 ? money($sgk) . ' (tahmini)' : 'Uygulanmadı'],
        ['yuzde', 'Size özel iskonto', $oran > 0 ? '%' . $oranMetni($oran) : '—'],
    ],
]) ?>
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

  <div class="alt-iki">
    <section class="kutu nasil">
      <h3>Hesap nasıl yapıldı?</h3>
      <ol>
        <li><b>Cam + çerçeve</b> fiyatları toplanır (ara toplam).</li>
        <?php if ($sgk > 0): ?><li>Medula (SGK) katkı payınız <b><?= money($sgk) ?></b> düşülür.</li><?php endif; ?>
        <?php if ($oran > 0): ?><li>Kalan tutara <b>%<?= e($oranMetni($oran)) ?></b> iskonto uygulanır.</li><?php endif; ?>
        <li>Sonuç, mağazamıza <b>ödeyeceğiniz tutardır</b>.</li>
      </ol>
      <?php if ($sgk > 0): ?><p>SGK payı tahminidir; reçeteniz Medula'da işlendiğinde kesinleşir. Hakkınız son 2 yılda kullanılmışsa uygulanmaz.</p><?php endif; ?>
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
