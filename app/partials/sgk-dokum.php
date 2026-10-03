<?php
declare(strict_types=1);

/* 4.16.1 — SGK dönem faturasının reçete dökümü (faturaya ek). Beklenen: $f (fatura), $shop. T.C. kimlik no YOK (saklanmaz). */
$dokum = fatura_sgk_dokum((int) $f['id']);
$dokumToplam = round(array_sum(array_column($dokum, 'tutar')), 2);
$firma = fatura_firma();
?>
<div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">SGK FATURA EKİ · REÇETE DÖKÜMÜ</p><h1><?= e($firma['unvan'] ?: $shop) ?></h1><p><?= e($firma['kimlik'] ? 'VKN/TCKN ' . $firma['kimlik'] : '') ?><?= $firma['vergi_dairesi'] ? ' · ' . e($firma['vergi_dairesi']) : '' ?></p></div></div>
  <div class="doc-ref"><b><?= e(fatura_sgk_ay_adi((string) $f['sgk_donem'])) ?></b><?= e($f['fatura_no'] ?: 'Taslak #' . (int) $f['id']) ?> · <?= count($dokum) ?> reçete</div></div>

<table class="kv-table">
  <tr><th>Alıcı</th><td colspan="3"><?= e((string) $f['alici_unvan']) ?></td></tr>
  <tr><th>Fatura tutarı</th><td><?= e(money($f['genel_toplam'])) ?></td><th>Reçete toplamı</th><td><?= e(money($dokumToplam)) ?></td></tr>
</table>

<table class="lines">
  <thead><tr><th>#</th><th>Medula</th><th>Hasta</th><th>e-Reçete no</th><th>Sipariş</th><th class="num">SGK payı</th></tr></thead>
  <tbody>
    <?php foreach ($dokum as $i => $r): ?>
      <tr><td><?= $i + 1 ?></td><td><?= e(date_tr((string) ($r['medula_islendi_at'] ?: $r['delivered_at']))) ?></td><td><?= e(trim((string) $r['first_name'] . ' ' . (string) $r['last_name'])) ?></td><td><?= e((string) ($r['sgk_erecete'] ?: '—')) ?></td><td><?= e(order_no((int) $r['order_id'])) ?></td><td class="num"><?= e(money($r['tutar'])) ?></td></tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot><tr><th colspan="5">Toplam</th><th class="num"><?= e(money($dokumToplam)) ?></th></tr></tfoot>
</table>
<div class="foot"><span class="foot-mark"><?= brand_mark() ?></span><span><?= e($shop) ?></span><span class="foot-note">Bu döküm <?= e(fatura_sgk_ay_adi((string) $f['sgk_donem'])) ?> SGK faturasının ekidir.</span></div>
