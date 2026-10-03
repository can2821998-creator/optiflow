<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/fatura.php';

/* ==========================================================================
   4.12.0 — Faturalar (e-Arşiv / e-Fatura hazırlık)
   ========================================================================== */

$me = require_login();
ozellik_gereksin('efatura');
if (!can_see_amounts()) {
    render_error_page('Yetkiniz yok', 'Tutarları görme yetkiniz olmadığı için faturalar açılamaz.');
}

if (is_post()) {
    $eylem = post('eylem');
    try {
        if ($eylem === 'siparisten') {
            $no = (int) preg_replace('/\D/', '', post('siparis'));
            $idler = fatura_siparisten_taslak($no, post('sgk_dus', post('sgk_ayri')) === '1');
            flash('Hasta payı fatura taslağı oluşturuldu.');
            redirect('fatura.php?id=' . $idler[0]);
        }
        if ($eylem === 'bos') {
            $id = fatura_bos_taslak();
            audit('fatura_taslak', 'fatura', $id, ['kaynak' => 'boş']);
            redirect('fatura.php?id=' . $id);
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('faturalar.php');
}

$durumlar = fatura_durumlari();
$d = query('d', '');
$kosul = isset($durumlar[$d]) ? 'WHERE f.durum = ?' : '';
$liste = rows(
    "SELECT f.* FROM faturalar f $kosul ORDER BY f.id DESC LIMIT 300",
    $kosul ? [$d] : []
);
$sayilar = [];
foreach (rows('SELECT durum, COUNT(*) AS n FROM faturalar GROUP BY durum') as $r) {
    $sayilar[$r['durum']] = (int) $r['n'];
}
$surucu = efatura_surucu();
$firmaEksik = fatura_firma_eksikleri();

page_start('Faturalar', 'faturalar');
page_header('Faturalar', 'e-Arşiv / e-Fatura taslakları — GİB gönderimi entegratör bağlanınca açılır.', '', '', 'Kasa');
?>

<div class="alert alert-info">Bu sürümde faturalar <b>taslak</b> olarak hazırlanır ve kontrol edilir; resmi fatura değildir ve GİB'e gönderilmez.
  Entegratör: <b><?= e($surucu->ad()) ?></b>. Bir sonraki sürümde seçtiğiniz özel entegratör bağlanınca aynı taslaklar tek tuşla gönderilecek.</div>

<?php if ($firmaEksik): ?>
  <div class="alert alert-warn">Satıcı bilgileri eksik: <?= e(implode(' ', $firmaEksik)) ?> <?= is_super() ? '<a class="link" href="settings.php?tab=efatura">Tamamla</a>' : '' ?></div>
<?php endif; ?>

<div class="split">
  <div class="split-main">
    <nav class="tabs" aria-label="Fatura durumu">
      <a class="tab <?= $d === '' ? 'active' : '' ?>" href="faturalar.php">Tümü</a>
      <?php foreach ($durumlar as $k => [$ad]): ?>
        <a class="tab <?= $d === $k ? 'active' : '' ?>" href="faturalar.php?d=<?= e($k) ?>"><?= e($ad) ?><?php if (!empty($sayilar[$k])): ?><em><?= (int) $sayilar[$k] ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <section class="card">
      <?php if (!$liste): ?>
        <?= empty_state('Fatura yok', 'Sipariş sayfasındaki “Fatura taslağı” düğmesiyle ya da yandan sipariş numarasıyla taslak oluşturun.') ?>
      <?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>No / tarih</th><th>Alıcı</th><th class="hide-sm">Profil</th><th class="num">Toplam</th><th>Durum</th></tr></thead>
          <tbody>
          <?php foreach ($liste as $f): [$dAd, $dTon] = $durumlar[$f['durum']] ?? [$f['durum'], 'gray']; ?>
            <tr>
              <td><a class="link" href="fatura.php?id=<?= (int) $f['id'] ?>"><b><?= e($f['fatura_no'] ?: 'Taslak #' . (int) $f['id']) ?></b></a>
                <small class="block muted"><?= e(date_tr($f['duzenleme'] ?: $f['created_at'])) ?><?= $f['order_id'] ? ' · ' . e(order_no((int) $f['order_id'])) : '' ?></small></td>
              <td><?= e($f['alici_tip'] === 'kurum' ? (string) $f['alici_unvan'] : trim($f['alici_ad'] . ' ' . $f['alici_soyad'])) ?: '<span class="muted">—</span>' ?></td>
              <td class="hide-sm"><small><?= e($f['profil'] === 'EARSIVFATURA' ? 'e-Arşiv' : ($f['profil'] === 'TICARIFATURA' ? 'e-Fatura (Ticari)' : 'e-Fatura (Temel)')) ?></small></td>
              <td class="num"><b><?= e(money($f['genel_toplam'])) ?></b><small class="block muted">KDV <?= e(money($f['kdv_toplam'])) ?></small></td>
              <td><span class="badge tone-<?= e($dTon) ?>"><?= e($dAd) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>
  </div>
  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2>Siparişten taslak</h2></div>
      <form method="post" class="stack" style="gap:8px">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="siparisten">
        <label class="field"><span>Sipariş no</span><input name="siparis" required placeholder="örn. 1245" inputmode="numeric"></label>
        <label class="check"><input type="checkbox" name="sgk_dus" value="1" checked> SGK katkı payını düş (yalnızca hasta payı)</label>
        <button class="btn btn-primary btn-sm">Taslak oluştur</button>
      </form>
      <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="eylem" value="bos"><button class="btn btn-sm btn-ghost">Boş taslak</button></form>
    </section>
    <section class="card">
      <div class="card-head"><h2>SGK ay sonu faturası</h2></div>
      <p class="muted small">Ay içindeki SGK'lı reçeteler biriktirilir, ay sonunda SGK'ya tek fatura kesilir (reçete dökümüyle).</p>
      <a class="btn btn-primary btn-sm" href="sgk-fatura.php"><?= icon('receipt') ?> Dönem faturasını hazırla</a>
    </section>
    <section class="card">
      <div class="card-head"><h2>Bilgi</h2></div>
      <ul class="kv">
        <li><span>Gözlük, çerçeve ve numaralı cam/lens için varsayılan KDV <b>%<?= e((string) (float) fatura_varsayilan_kdv()) ?></b>; güneş gözlüğü satışında %20 önerilir. Oranları mali müşavirinizle doğrulayın.</span></li>
        <li><span>Sipariş faturası yalnızca hasta payını içerir. SGK payları ay sonunda tek faturada SGK'ya düzenlenir; alıcı bilgilerini Ayarlar › e-Fatura'dan kontrol edin.</span></li>
        <li><span>Müşterinin T.C. kimlik numarası bilinmiyorsa 11111111111 yazılabilir.</span></li>
      </ul>
    </section>
  </aside>
</div>
<?php page_end();
