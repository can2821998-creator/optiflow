<?php
declare(strict_types=1);

/* ==========================================================================
   4.13.0 — Sipariş sayfası: ÜTS ürünleri kartı
   Beklenen değişkenler: $order, $id
   ========================================================================== */

$utsUrunler = uts_siparis_urunleri((int) $id);
$utsSgk = uts_siparis_sgk_mi($order);
$utsKapali = in_array($order['order_stage'], ['teslim_edildi', 'iptal'], true);
$utsBildirim = [];
foreach ($utsUrunler as $u) {
    $utsBildirim[(int) $u['id']] = row("SELECT durum, tur, son_hata FROM uts_bildirimler WHERE urun_id = ? AND tur IN ('tuketiciye_verme','tuketiciden_iade') ORDER BY id DESC LIMIT 1", [(int) $u['id']]);
}
?>
<section class="card" id="uts">
  <div class="card-head">
    <h2><?= icon('barcode') ?> ÜTS ürünleri</h2>
    <span class="badge tone-<?= $utsSgk ? 'blue' : 'gray' ?>" title="<?= $utsSgk ? 'e-reçete / SGK katkısı var: ÜTS düşümünü Medula yapar' : 'Teslimde ÜTS tüketiciye verme bildirimi yapılır' ?>"><?= $utsSgk ? 'SGK\'lı satış · Medula düşer' : 'Ücretli satış' ?></span>
  </div>
  <?php if ($utsUrunler): ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Ürün</th><th class="hide-sm">Karekod</th><th>Durum</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($utsUrunler as $u):
          [$dAd, $dTon] = uts_urun_durumlari()[$u['durum']] ?? [$u['durum'], 'gray'];
          $b = $utsBildirim[(int) $u['id']] ?? null; ?>
        <tr>
          <td><?= e(uts_urun_etiketi($u)) ?>
            <small class="block muted"><?= e(uts_kategoriler()[$u['kategori']] ?? '') ?><?php if ($u['f_price'] !== null && can_see_amounts()): ?> · <?= money($u['f_price']) ?><?php endif; ?><?php if ($u['skt']): ?> · SKT <?= e(date_tr((string) $u['skt'])) ?><?php endif; ?></small></td>
          <td class="hide-sm"><small><code><?= e((string) $u['uno']) ?></code></small></td>
          <td><span class="badge tone-<?= e($dTon) ?>"><?= e($dAd) ?></span>
            <?php if ($b): [$bAd, $bTon] = uts_bildirim_durumlari()[$b['durum']] ?? [$b['durum'], 'gray']; ?>
              <small class="block"><a class="link" href="uts.php?tab=bildirimler&amp;siparis=<?= (int) $id ?>"><?= e($b['tur'] === 'tuketiciden_iade' ? 'İade' : 'Bildirim') ?>: <?= e($bAd) ?></a></small>
            <?php endif; ?></td>
          <td class="num">
            <?php if (!$utsKapali && $u['durum'] === 'stokta'): ?>
              <form method="post" data-confirm="Ürün siparişten çıkarılsın mı? Stoğa döner."><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="uts_cikar"><input type="hidden" name="urun_id" value="<?= (int) $u['id'] ?>"><button class="linkish danger" title="Siparişten çıkar"><?= icon('x') ?></button></form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
  <?php if (!$utsKapali): ?>
    <form method="post" class="grid cols-3" style="align-items:end;margin-top:<?= $utsUrunler ? '12px' : '0' ?>" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="order_id" value="<?= (int) $id ?>"><input type="hidden" name="action" value="uts_okut">
      <label class="field"><span>Karekodu okutun</span><input name="kod" required maxlength="200" data-barkod-alani placeholder="Okuyucuyla okutun (Enter)"></label>
      <label class="field"><span>Ürün türü <small class="muted">(ilk kez okutulursa)</small></span><select name="kategori"><?= select_options(uts_kategoriler(), 'cerceve') ?></select></label>
      <div class="form-actions" style="gap:8px"><label class="field" style="max-width:90px"><span>Adet (lot)</span><input type="number" name="adet" value="1" min="1" max="99"></label><button class="btn btn-primary"><?= icon('check') ?> Ekle</button></div>
    </form>
    <p class="hint"><?= $utsSgk
        ? 'Bu sipariş SGK\'lı: karekodları Medula\'ya da okutun. Teslimde OptiFlow ÜTS\'ye bildirim göndermez, yalnızca stoktan çıkışı kaydeder.'
        : 'Teslim edildiğinde okutulan ürünler için ÜTS\'ye "tüketiciye verme" bildirimi ' . (uts_gonderim_modu() === 'onayli' ? 'hazırlanır ve ÜTS › Bildirimler\'de onayınızı bekler.' : 'kendiliğinden gönderilir.') ?>
       Çerçeve kartına bağlı karekod okutulursa siparişin çerçevesi ve fiyatı kendiliğinden gelir.</p>
  <?php elseif (!$utsUrunler): ?>
    <p class="muted small">Bu siparişe ÜTS ürünü okutulmamış.</p>
  <?php endif; ?>
</section>
