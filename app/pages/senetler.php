<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.14.0 — Senetler ve ödeme takvimi
   ?yazdir=ID : senedi (bono) A4 düz kâğıda yazdırma görünümü
   ========================================================================== */

$me = require_login();
ozellik_gereksin('tedarik_finans');
if (!is_super()) {
    render_error_page('Yetki gerekli', 'Senet ve ödeme takvimini yalnızca yönetici görür.');
}

if (is_post()) {
    $eylem = post('eylem');
    try {
        if ($eylem === 'ver') {
            $tutar = parse_money(post('tutar'));
            if ($tutar === null || $tutar <= 0) {
                throw new DomainException('Tutar geçersiz.');
            }
            $id = senet_ver(post_int('supplier_id'), $tutar, post('vade'), post('senet_no'), post('duzenleme') ?: date('Y-m-d'), null, post('not'), post('duzenleme_yeri'), post('odeme_yeri'));
            audit('senet_ver', 'supplier', post_int('supplier_id'), ['tutar' => $tutar, 'vade' => post('vade'), 'no' => post('senet_no')]);
            flash('Senet kaydedildi; tedarikçinin carisi ' . money($tutar) . ' kapandı.');
            redirect('senetler.php#s-' . $id);
        }
        if ($eylem === 'ode') {
            senet_ode(post_int('senet_id'), post('tarih') ?: date('Y-m-d'), post('yontem') ?: 'havale');
            audit('senet_ode', 'supplier', null, ['senet' => post_int('senet_id')]);
            flash('Senet ödendi olarak işaretlendi.');
        }
        if ($eylem === 'iptal') {
            senet_iptal(post_int('senet_id'));
            audit('senet_iptal', 'supplier', null, ['senet' => post_int('senet_id')]);
            flash('Senet iptal edildi; tutar tedarikçinin carisine borç olarak döndü.', 'info');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect('senetler.php');
}

/* ---------- Senet yazdırma (bono) ---------- */
if (query_int('yazdir') > 0) {
    $s = senet(query_int('yazdir'));
    if (!$s) {
        render_error_page('Senet bulunamadı', '');
    }
    $firma = trim(setting('firma_unvan', '')) ?: setting('shop_name', 'OptiFlow');
    $firmaVkn = preg_replace('/\D/', '', setting('firma_vkn', '')) ?? '';
    $firmaAdres = trim(setting('firma_adres', '') . ' ' . setting('firma_ilce', '') . ' ' . setting('firma_il', ''));
    $odemeYeri = $s['odeme_yeri'] ?: setting('firma_il', '');
    $duzenlemeYeri = $s['duzenleme_yeri'] ?: setting('firma_il', '');
    header('Content-Type: text/html; charset=utf-8');
    ?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Senet <?= e($s['senet_no'] ?: '#' . $s['id']) ?></title>
<style>
  @page { size: A4; margin: 14mm; }
  body { font: 13px/1.5 "Times New Roman", Georgia, serif; color: #000; margin: 0; padding: 16px; background: #fff; }
  .senet { border: 2px solid #000; padding: 18px 22px; max-width: 760px; margin: 0 auto; }
  .ust { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; border-bottom: 1px solid #000; padding-bottom: 10px; margin-bottom: 12px; }
  .ust div { border: 1px solid #000; padding: 6px 8px; }
  .ust small, .alt small { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: .04em; }
  .ust b { font-size: 15px; }
  h1 { text-align: center; font-size: 20px; letter-spacing: .12em; margin: 4px 0 12px; }
  .metin { text-align: justify; font-size: 14px; }
  .alt { display: grid; grid-template-columns: 1.4fr 1fr; gap: 14px; margin-top: 18px; }
  .alt div { border: 1px solid #000; padding: 8px 10px; min-height: 64px; }
  .imza { min-height: 90px !important; }
  .not { max-width: 760px; margin: 12px auto 0; font: 12px/1.4 system-ui, Arial, sans-serif; color: #555; }
  .not button { font: inherit; padding: 6px 12px; }
  @media print { .not { display: none; } body { padding: 0; } }
</style></head>
<body>
<div class="senet">
  <div class="ust">
    <div><small>Ödeme günü (vade)</small><b><?= e(date_tr((string) $s['vade'])) ?></b></div>
    <div><small>Türk Lirası</small><b>#<?= e(number_format((float) $s['tutar'], 2, ',', '.')) ?>#</b></div>
    <div><small>Seri / no</small><b><?= e($s['senet_no'] ?: (string) $s['id']) ?></b></div>
  </div>
  <h1>BONO</h1>
  <p class="metin">İşbu emre muharrer senedin mukabilinde <b><?= e(date_tr((string) $s['vade'])) ?></b> tarihinde
    Sayın <b><?= e((string) $s['tedarikci']) ?></b><?= $s['tedarikci_vkn'] ? ' (VKN/TCKN ' . e((string) $s['tedarikci_vkn']) . ')' : '' ?> veya emrühavalesine
    yukarıda yazılı yalnız <b><?= e(tutar_yaziyla((float) $s['tutar'])) ?></b> ödeyeceğiz.
    Bedeli malen ahzolunmuştur.</p>
  <div class="alt">
    <div><small>Ödeme yeri</small><?= e($odemeYeri ?: '………………………………') ?></div>
    <div><small>Düzenleme yeri ve tarihi</small><?= e(($duzenlemeYeri ?: '…………………') . ' · ' . date_tr((string) $s['duzenleme'])) ?></div>
    <div><small>Borçlu (düzenleyen)</small><b><?= e($firma) ?></b><?= $firmaVkn ? '<br>VKN/TCKN ' . e($firmaVkn) : '' ?><?= $firmaAdres !== '' ? '<br>' . e($firmaAdres) : '' ?></div>
    <div class="imza"><small>İmza / kaşe</small></div>
  </div>
</div>
<div class="not">
  Bono; "bono" ya da "emre muharrer senet" ibaresi, kayıtsız şartsız belli bir bedeli ödeme vaadi, vade, ödeme yeri, lehtar,
  düzenleme yeri ve tarihi ile düzenleyenin imzasını içermelidir. Firma bilgileri Ayarlar › e-Fatura'daki satıcı bilgilerinden alınır.
  Islak imza ile imzalayın. <button type="button" data-yazdir>Yazdır</button>
</div>
<script src="<?= e(asset('moduller.js')) ?>" defer></script>
</body></html>
    <?php
    exit;
}

/* ---------- Takvim ---------- */
$takvim = odeme_takvimi();
$basliklar = ['gecikmis' => ['Vadesi geçmiş', 'red'], 'yakin' => ['7 gün içinde', 'amber'], 'bu_ay' => ['Bu ay', 'blue'], 'sonra' => ['Sonraki aylar', 'gray']];
$toplam = static fn(array $g): float => array_sum(array_column($g, 'tutar'));
$senetToplam = senet_bekleyen_toplam();
$tedarikciler = rows('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name');
$sonOdenen = rows("SELECT t.*, s.name AS tedarikci FROM tedarikci_senetleri t JOIN suppliers s ON s.id = t.supplier_id WHERE t.durum = 'odendi' ORDER BY t.odeme_tarihi DESC, t.id DESC LIMIT 10");

page_start('Senetler · ödeme takvimi', 'senetler');
page_header('Senetler · ödeme takvimi', 'Tedarikçilere verilen senetler ve vadesi gelen faturalar. Vadeye ' . SENET_UYARI_GUN . ' gün kala menüde ve telefonda uyarı gelir.', '', '', 'Tedarik');
?>
<section class="stats">
  <?php foreach ($basliklar as $k => [$ad, $ton]): ?>
    <div class="stat <?= $takvim[$k] && $k !== 'sonra' ? 'tone-' . $ton : '' ?>"><small><?= e($ad) ?></small><b><?= money($toplam($takvim[$k])) ?></b><span><?= count($takvim[$k]) ?> ödeme</span></div>
  <?php endforeach; ?>
</section>
<p class="hint">Ödenmemiş senet toplamı: <b><?= money($senetToplam) ?></b>. Faturalar, tedarikçiye yapılan ödemeler en eski faturadan başlayarak düşüldükten sonra kalan tutarlarıyla listelenir (yalnızca vade girilmiş faturalar).</p>

<?php foreach ($basliklar as $k => [$ad, $ton]): if (!$takvim[$k]) { continue; } ?>
  <section class="card">
    <div class="card-head"><h2><?= e($ad) ?></h2><small class="muted"><?= money($toplam($takvim[$k])) ?></small></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Vade</th><th>Tedarikçi</th><th>Belge</th><th class="num">Tutar</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($takvim[$k] as $o): ?>
        <tr id="<?= $o['tur'] === 'senet' ? 's-' . $o['id'] : 'f-' . $o['id'] ?>">
          <td class="<?= $k === 'gecikmis' ? 'text-danger' : '' ?>"><b><?= e(date_tr($o['vade'])) ?></b><?php if ($k === 'gecikmis'): ?><small class="block"><?= days_since($o['vade']) ?> gün gecikti</small><?php endif; ?></td>
          <td><a class="link" href="supplier.php?id=<?= (int) $o['supplier_id'] ?>#senetler"><?= e($o['tedarikci']) ?></a></td>
          <td><?= $o['tur'] === 'senet' ? '<span class="badge tone-violet">Senet</span> ' . e($o['no'] ?: '#' . $o['id']) : '<span class="badge tone-gray">Fatura</span> ' . e($o['no']) ?>
            <?php if ($o['tur'] === 'fatura' && abs(($o['fatura_tutar'] ?? 0) - $o['tutar']) > 0.009): ?><small class="block muted">Fatura <?= money($o['fatura_tutar']) ?>, kalan</small><?php endif; ?></td>
          <td class="num"><b><?= money($o['tutar']) ?></b></td>
          <td class="num">
            <?php if ($o['tur'] === 'senet'): ?>
              <div class="btn-row" style="justify-content:flex-end">
                <a class="btn btn-sm btn-ghost" href="senetler.php?yazdir=<?= (int) $o['id'] ?>" target="_blank" title="Yazdır"><?= icon('print') ?></a>
                <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="eylem" value="ode"><input type="hidden" name="senet_id" value="<?= (int) $o['id'] ?>">
                  <select name="yontem" aria-label="Ödeme yöntemi"><?= select_options(payment_methods(), 'havale') ?></select>
                  <button class="btn btn-sm btn-primary">Ödendi</button></form>
              </div>
            <?php else: ?>
              <a class="btn btn-sm" href="supplier.php?id=<?= (int) $o['supplier_id'] ?>">Ödeme gir</a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
<?php endforeach; ?>
<?php if (!array_filter($takvim)): ?>
  <section class="card"><?= empty_state('Bekleyen ödeme yok', 'Verdiğiniz senetler ve vadesi girilmiş açık faturalar burada vade sırasıyla listelenir.') ?></section>
<?php endif; ?>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2><?= icon('plus') ?> Senet ver</h2></div>
      <?php if (!$tedarikciler): ?>
        <p class="hint">Önce <a class="link" href="suppliers.php">tedarikçi</a> ekleyin.</p>
      <?php else: ?>
      <form method="post" class="grid cols-3">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="ver">
        <label class="field"><span>Tedarikçi *</span><select name="supplier_id" required><option value="">Seçin</option><?php foreach ($tedarikciler as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>Tutar *</span><input name="tutar" inputmode="decimal" required placeholder="0,00"></label>
        <label class="field"><span>Vade *</span><input type="date" name="vade" required></label>
        <label class="field"><span>Senet no</span><input name="senet_no" maxlength="40"></label>
        <label class="field"><span>Düzenleme tarihi</span><input type="date" name="duzenleme" value="<?= date('Y-m-d') ?>"></label>
        <label class="field"><span>Not</span><input name="not" maxlength="255"></label>
        <label class="field"><span>Düzenleme yeri</span><input name="duzenleme_yeri" maxlength="80" value="<?= e(setting('firma_il', '')) ?>"></label>
        <label class="field"><span>Ödeme yeri</span><input name="odeme_yeri" maxlength="80" value="<?= e(setting('firma_il', '')) ?>"></label>
        <div class="form-actions"><button class="btn btn-primary">Senedi kaydet</button></div>
      </form>
      <p class="hint">Senet verilince tedarikçinin carisi senet tutarı kadar kapanır, borç senete geçer. Birden çok vadeli senet için formu her senet için ayrı doldurun.</p>
      <?php endif; ?>
    </section>
  </div>
  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2>Son ödenen senetler</h2></div>
      <?php if (!$sonOdenen): ?><p class="muted small">Henüz yok.</p><?php else: ?>
        <ul class="kv">
          <?php foreach ($sonOdenen as $o): ?><li><span><?= e((string) $o['tedarikci']) ?><small class="block muted"><?= e(date_tr((string) $o['odeme_tarihi'])) ?> · <?= e(payment_methods()[$o['odeme_yontemi']] ?? '') ?></small></span><b><?= money($o['tutar']) ?></b></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
<?php page_end();
