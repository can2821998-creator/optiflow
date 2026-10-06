<?php
/* ==========================================================================
   4.21.1 — Katalog teklifi dökümü (print.php?type=quote, quotes.tip = 'katalog').
   Kendi sayfası ve stili (assets/teklif-dokum.css): A4 tek sayfa, siyah-bordo kimlik.
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
$ikon = [
    'cerceve' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.5" cy="14" r="3.5"/><circle cx="17.5" cy="14" r="3.5"/><path d="M10 14c.7-.8 3.3-.8 4 0"/><path d="M3 14l1.5-6h2M21 14l-1.5-6h-2"/></svg>',
    'goz'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>',
    'sgk'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"/><path d="M9 12l2 2 4-4"/></svg>',
    'yuzde'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.5"/><circle cx="17" cy="17" r="2.5"/></svg>',
    'tel'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/></svg>',
    'konum'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.1-7-11.5A7 7 0 0 1 19 9.5C19 14.9 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>',
];
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Fiyat teklifi #<?= (int) $qt['id'] ?> · <?= e($qt['customer_name']) ?></title>
<link rel="stylesheet" href="<?= e(asset('teklif-dokum.css')) ?>">
</head>
<body>
<div class="arac no-print">
  <button type="button" data-print>Yazdır / PDF kaydet</button>
  <button type="button" class="ghost" data-close>Kapat</button>
  <span>PDF için yazdırma penceresinde hedef olarak <b>"PDF olarak kaydet"</b>i seçin.</span>
</div>

<main class="sayfa">
  <header class="ust">
    <div class="marka">
      <span class="logo"><?= brand_mark() ?></span>
      <div>
        <b><?= e($shop) ?></b>
        <?php if ($adres !== ''): ?><small><?= $ikon['konum'] ?><?= e($adres) ?></small><?php endif; ?>
        <?php if ($telefon !== ''): ?><small><?= $ikon['tel'] ?><?= e($telefon) ?></small><?php endif; ?>
      </div>
    </div>
    <div class="belge">
      <span class="etiket">Fiyat teklifi</span>
      <dl>
        <div><dt>Teklif no</dt><dd>#<?= str_pad((string) (int) $qt['id'], 5, '0', STR_PAD_LEFT) ?></dd></div>
        <div><dt>Tarih</dt><dd><?= e(date('d.m.Y', $olusturma)) ?></dd></div>
        <div><dt>Geçerlilik</dt><dd><?= e($gecerlilik) ?></dd></div>
      </dl>
    </div>
  </header>

  <section class="selam">
    <div>
      <span class="kucuk">Sayın</span>
      <h1><?= e($qt['customer_name']) ?></h1>
      <p>Gözlüğünüz için hazırladığımız <?= count($secenekler) > 1 ? count($secenekler) . ' seçeneği yan yana karşılaştırabilirsiniz' : 'teklifin ayrıntıları aşağıdadır' ?>.
        Tüm fiyatlara cam (çift) ve çerçeve dahildir<?= $sgk > 0 ? '; Medula (SGK) katkı payınız düşülmüştür' : '' ?>.</p>
    </div>
    <?php if (count($secenekler) > 1): ?>
      <div class="baslayan"><small>Başlayan fiyatlarla</small><b><?= money($enUcuz) ?></b></div>
    <?php endif; ?>
  </section>

  <section class="bilgi">
    <div><span class="ic"><?= $ikon['cerceve'] ?></span><small>Çerçeve</small><b><?= e((string) $qt['frame_desc']) ?></b><?= (float) $qt['frame_price'] > 0 ? '<em>' . money($qt['frame_price']) . '</em>' : '' ?></div>
    <div><span class="ic"><?= $ikon['goz'] ?></span><small>Kullanım</small><b><?= e(lens_designs()[(string) $qt['lens_design']] ?? '—') ?></b></div>
    <div><span class="ic"><?= $ikon['sgk'] ?></span><small>Medula (SGK) payı</small><b><?= $sgk > 0 ? money($sgk) : 'Uygulanmadı' ?></b><?= $sgk > 0 ? '<em>tahmini</em>' : '' ?></div>
    <div><span class="ic"><?= $ikon['yuzde'] ?></span><small>Size özel iskonto</small><b><?= $oran > 0 ? '%' . e($oranMetni($oran)) : '—' ?></b><?= $oran > 0 ? '<em>SGK sonrası tutara</em>' : '' ?></div>
  </section>

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
        <dl class="hesap">
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

  <footer class="son">
    <div>
      <?php if ($qt['note']): ?><p class="musteri-not"><b>Not:</b> <?= e((string) $qt['note']) ?></p><?php endif; ?>
      <p>Bu teklif <b><?= e($gecerlilik) ?></b> tarihine kadar geçerlidir ve bilgilendirme amaçlıdır; fiyatlar stok ve tedarikçi koşullarına göre değişebilir.
        Siparişiniz, seçtiğiniz seçenek ve reçetenizle birlikte kesinleşir.</p>
    </div>
    <div class="imza">
      <small>Hazırlayan</small>
      <b><?= e($hazirlayan !== '' ? $hazirlayan : $shop) ?></b>
      <span><?= e($shop) ?></span>
    </div>
  </footer>
</main>
<script src="<?= e(asset('print.js')) ?>" defer></script>
</body>
</html>
