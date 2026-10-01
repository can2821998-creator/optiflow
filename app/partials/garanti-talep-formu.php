<?php
declare(strict_types=1);

/* 4.16.0 — Tedarikçiye garanti talebi formu (yazdırılır). Beklenen: $g (garanti_bul), $t (talep), $shop. Müşteri iletişim bilgisi YOK. */
$tTed = $t['supplier_id'] ? row('SELECT name, phone, email FROM suppliers WHERE id = ?', [(int) $t['supplier_id']]) : null;
$tTed ??= $g['tedarikci'] ? ['name' => $g['tedarikci'], 'phone' => $g['tedarikci_tel'], 'email' => $g['tedarikci_eposta']] : null;
?>
<div class="doc-brand"><div class="brand-row"><span class="doc-brand-mark"><?= brand_mark() ?></span><div><p class="doc-kicker">GARANTİ TALEBİ</p><h1><?= e($shop) ?></h1><p><?= e(setting('shop_address')) ?><?= setting('shop_phone') ? ' · ' . e(setting('shop_phone')) : '' ?></p></div></div>
  <div class="doc-ref"><b><?= e(garanti_no((int) $g['id']) . '-' . (int) $t['id']) ?></b><?= e(date_tr((string) ($t['gonderim'] ?: $t['created_at']))) ?></div></div>

<table class="kv-table">
  <tr><th>Tedarikçi</th><td colspan="3"><?= e((string) ($tTed['name'] ?? '—')) ?><?= !empty($tTed['phone']) ? ' · ' . e(phone_display((string) $tTed['phone'])) : '' ?></td></tr>
  <tr><th>Ürün</th><td colspan="3"><?= e((string) $g['urun']) ?> (<?= e(garanti_kalemleri()[$g['kalem']] ?? $g['kalem']) ?>)</td></tr>
  <tr><th>Seri no</th><td><?= e((string) ($g['seri_no'] ?: '—')) ?></td><th>Sipariş</th><td><?= $g['order_id'] ? e(order_no((int) $g['order_id'])) : '—' ?></td></tr>
  <tr><th>Satış tarihi</th><td><?= e(date_tr((string) $g['baslangic'])) ?></td><th>Garanti bitişi</th><td><?= e(date_tr((string) $g['bitis'])) ?></td></tr>
  <tr><th>Arıza / şikâyet</th><td colspan="3"><?= e((string) $t['sikayet']) ?></td></tr>
</table>

<div class="sign-box">
  <div class="box"><small>Gönderen</small><div class="who"><?= e((string) (current_user()['full_name'] ?? $shop)) ?></div><p>Ürün yukarıdaki arıza ile garanti kapsamında gönderilmiştir.</p><div class="pen-line"></div></div>
  <div class="box"><small>Teslim alan (tedarikçi)</small><div class="who empty">—</div><p>Ürünü teslim aldım.</p><div class="pen-line"></div></div>
</div>
<div class="foot"><span class="foot-mark"><?= brand_mark() ?></span><span><?= e($shop) ?></span><span class="foot-note">Müşteri bilgisi içermez.</span></div>
