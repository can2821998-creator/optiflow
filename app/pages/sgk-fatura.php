<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/fatura.php';
require_once dirname(__DIR__) . '/pdf-metin.php';

/* ==========================================================================
   4.16.1 — SGK ay sonu toplu faturası
   • Reçete siparişte "Medula'ya işlendi" işaretlenir; hangi ayda işlendiyse o
     ayın SGK faturasına girer (önceki aydan faturalanmamış kalanlar da gelir).
   • Ay sonunda Medula'dan alınan döküm (PDF ya da metin) yüklenir: adet ve
     e-reçete numaraları OptiFlow'daki işaretlerle karşılaştırılır. Döküm
     SUNUCUDA SAKLANMAZ; yalnızca bulunan numaralar oturumda 30 dk tutulur.
   • Seçilen reçetelerle SGK'ya TEK fatura (KDV oranına göre satır) + reçete dökümü.
   ========================================================================== */

$me = require_login();
ozellik_gereksin('efatura');
if (!can_see_amounts()) {
    render_error_page('Yetkiniz yok', 'Tutarları görme yetkiniz olmadığı için faturalar açılamaz.');
}

$ay = fatura_sgk_ay(is_post() ? post('ay') : query('ay'));
$geri = 'sgk-fatura.php?ay=' . $ay;

if (is_post()) {
    $eylem = post('eylem');
    try {
        if ($eylem === 'olustur') {
            $medula = post('medula_toplam') === '' ? null : parse_money(post('medula_toplam'));
            if (post('medula_toplam') !== '' && $medula === null) {
                throw new DomainException('Medula toplamı geçersiz. Örnek: 12.450,00');
            }
            $fid = fatura_sgk_donem_taslagi($ay, array_map('intval', (array) ($_POST['siparis'] ?? [])), $medula);
            audit('fatura_taslak', 'fatura', $fid, ['kaynak' => 'SGK dönem ' . $ay, 'reçete' => count((array) ($_POST['siparis'] ?? []))]);
            flash(fatura_sgk_ay_adi($ay) . ' SGK faturası taslağı oluşturuldu.');
            redirect('fatura.php?id=' . $fid);
        } elseif ($eylem === 'eski_iptal') {
            $n = fatura_sgk_eski_taslaklari_iptal();
            audit('fatura_iptal', 'fatura', null, ['kaynak' => 'eski sipariş bazlı SGK taslakları', 'adet' => $n]);
            flash($n . ' eski SGK taslağı iptal edildi.');
        } elseif ($eylem === 'toplu_isaretle') {
            $secilen = array_map('intval', (array) ($_POST['bekleyen'] ?? []));
            if (!$secilen) {
                throw new DomainException('İşaretlenecek reçeteleri seçin.');
            }
            $n = 0;
            $hatalar = [];
            foreach ($secilen as $sid) {
                try {
                    sgk_medula_isaretle($sid, post('tarih'));
                    $n++;
                } catch (DomainException $e) {
                    $hatalar[] = order_no($sid) . ': ' . $e->getMessage();
                }
            }
            audit('sgk_medula', 'sgk', null, ['durum' => 'toplu Medula\'ya işlendi', 'adet' => $n, 'tarih' => post('tarih') ?: date('Y-m-d')]);
            flash($n . ' reçete Medula\'ya işlendi olarak işaretlendi.');
            foreach (array_slice($hatalar, 0, 5) as $h) {
                flash($h, 'error');
            }
        } elseif ($eylem === 'dokum') {
            $metin = trim(str_replace("\r", '', post('metin')));
            $kaynak = 'yapıştırma';
            $f = $_FILES['dosya'] ?? null;
            if (is_array($f) && isset($f['name'])) {
                $hata = (int) ((array) $f['error'])[0];
                if ($hata !== UPLOAD_ERR_NO_FILE) {
                    if ($hata !== UPLOAD_ERR_OK) {
                        throw new DomainException('PDF yüklenemedi (dosya çok büyük olabilir).');
                    }
                    $yol = (string) ((array) $f['tmp_name'])[0];
                    if (filesize($yol) > 15 * 1024 * 1024) {
                        throw new DomainException('PDF 15 MB\'tan büyük olamaz.');
                    }
                    $bayt = (string) file_get_contents($yol);
                    if (!str_starts_with(ltrim(substr($bayt, 0, 1024)), '%PDF')) {
                        throw new DomainException('Seçilen dosya PDF değil.');
                    }
                    $metin = pdf_metin($bayt);
                    $kaynak = 'PDF (' . mb_substr((string) ((array) $f['name'])[0], 0, 60) . ')';
                    if ($metin === '') {
                        throw new DomainException('PDF\'ten metin okunamadı (taranmış/resim ya da şifreli olabilir). Medula ekranındaki listeyi kopyalayıp yapıştırın ya da adedi elle girin.');
                    }
                }
            }
            $elle = post('adet') === '' ? null : (int) post('adet');
            if ($metin === '' && $elle === null) {
                throw new DomainException('Medula dökümünü (PDF) seçin, metnini yapıştırın ya da reçete adedini yazın.');
            }
            $numaralar = $metin !== '' ? sgk_metinden_numaralar($metin) : [];
            $_SESSION['sgk_dokum_kontrol'] = ['ay' => $ay, 'numaralar' => $numaralar, 'adet' => $elle, 'kaynak' => $kaynak, 'zaman' => time()];
            audit('sgk_liste_kontrol', 'sgk', null, ['dönem' => $ay, 'kaynak' => $kaynak, 'numara' => count($numaralar), 'adet' => $elle]);
            if ($metin !== '' && !$numaralar && $elle === null) {
                flash('Dökümde e-reçete numarası bulunamadı. Adet karşılaştırması için dökümdeki reçete sayısını elle yazın.', 'warn');
            }
        } elseif ($eylem === 'dokum_temizle') {
            unset($_SESSION['sgk_dokum_kontrol']);
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
    }
    redirect($geri . ($eylem === 'dokum' ? '#dokum' : ''));
}

$liste = fatura_sgk_donem_siparisleri($ay);
$faturalar = fatura_sgk_donem_faturalari($ay);
$eski = fatura_sgk_eski_taslaklar();
$bekleyen = sgk_medula_bekleyenler();
$toplam = round(array_sum(array_column($liste, 'sgk_amount')), 2);
$onceki = count(array_filter($liste, static fn(array $o): bool => (bool) $o['onceki']));
$kontrol = $_SESSION['sgk_dokum_kontrol'] ?? null;
if (!is_array($kontrol) || ($kontrol['ay'] ?? '') !== $ay || time() - (int) ($kontrol['zaman'] ?? 0) > 1800) {
    $kontrol = null;
}
$sonuc = $kontrol ? fatura_sgk_medula_karsilastir($ay, $kontrol['numaralar']) : null;
$aylar = [];
for ($i = 0; $i < 12; $i++) {
    $k = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
    $aylar[$k] = fatura_sgk_ay_adi($k);
}
if (!isset($aylar[$ay])) {
    $aylar[$ay] = fatura_sgk_ay_adi($ay);
}
$kisi = static fn(array $o): string => trim((string) ($o['first_name'] ?? '') . ' ' . (string) ($o['last_name'] ?? ''));

page_start('SGK ay sonu faturası', 'faturalar');
page_header('SGK ay sonu faturası', 'Medula\'ya işlenen reçeteler ay sonunda tek faturada SGK\'ya', '', '', 'Kasa');
?>
<form method="get" class="btn-row" style="margin-bottom:14px">
  <label class="field" style="min-width:200px"><span>Dönem</span><select name="ay" data-auto-submit><?= select_options($aylar, $ay) ?></select></label>
  <button class="btn btn-sm">Göster</button>
</form>

<?php if ($eski): ?>
  <div class="alert alert-warn">
    Eski usulde sipariş bazında açılmış <b><?= count($eski) ?></b> SGK fatura taslağı var (toplam <?= e(money(array_sum(array_column($eski, 'genel_toplam')))) ?>).
    Dönem faturası oluşturulunca ilgili reçetelerinkiler kendiliğinden iptal edilir.
    <form method="post" class="inline" data-confirm="Sipariş bazındaki <?= count($eski) ?> SGK taslağı iptal edilsin mi? (Hasta faturaları etkilenmez.)"><?= csrf_field() ?><input type="hidden" name="ay" value="<?= e($ay) ?>"><input type="hidden" name="eylem" value="eski_iptal"><button class="btn btn-sm">Hepsini iptal et</button></form>
  </div>
<?php endif; ?>

<section class="stats">
  <div class="stat"><small>Faturalanacak</small><b><?= count($liste) ?></b><span><?= e(money($toplam)) ?><?= $onceki ? ' · ' . $onceki . ' önceki aydan' : '' ?></span></div>
  <div class="stat <?= $bekleyen ? 'tone-amber' : '' ?>"><small>Medula'ya işlenmemiş</small><b><?= count($bekleyen) ?></b><span>SGK'lı sipariş</span></div>
  <?php if ($sonuc): $fark = $kontrol['adet'] !== null ? (int) $kontrol['adet'] - $sonuc['optiflow_adet'] : ($sonuc['pdf_adet'] ? $sonuc['pdf_adet'] - $sonuc['optiflow_adet'] : null); ?>
    <div class="stat <?= $fark === 0 ? 'tone-green' : 'tone-red' ?>"><small>Medula dökümü</small><b><?= $kontrol['adet'] !== null ? (int) $kontrol['adet'] : $sonuc['pdf_adet'] ?></b><span>OptiFlow'da bu ay <?= $sonuc['optiflow_adet'] ?><?= $fark === 0 ? ' · tutuyor' : ($fark !== null ? ' · fark ' . ($fark > 0 ? '+' : '') . $fark : '') ?></span></div>
  <?php endif; ?>
</section>

<?php if ($faturalar): ?>
<section class="card">
  <div class="card-head"><h2><?= e(fatura_sgk_ay_adi($ay)) ?> SGK faturası</h2></div>
  <div class="table-wrap"><table class="table"><tbody>
    <?php foreach ($faturalar as $f): [$dAd, $dTon] = fatura_durumlari()[$f['durum']] ?? [$f['durum'], 'gray']; ?>
      <tr>
        <td><a class="link" href="fatura.php?id=<?= (int) $f['id'] ?>"><b><?= e($f['fatura_no'] ?: 'Taslak #' . (int) $f['id']) ?></b></a></td>
        <td><?= (int) $f['adet'] ?> reçete</td>
        <td class="num"><b><?= e(money($f['genel_toplam'])) ?></b></td>
        <td><span class="badge tone-<?= e($dTon) ?>"><?= e($dAd) ?></span></td>
        <td><a class="link" href="print.php?type=sgk_dokum&amp;id=<?= (int) $f['id'] ?>" target="_blank" rel="noopener">Döküm</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody></table></div>
</section>
<?php endif; ?>

<section class="card" id="dokum">
  <div class="card-head"><h2><?= icon('search') ?> Medula dökümüyle karşılaştır</h2>
    <?php if ($kontrol): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="ay" value="<?= e($ay) ?>"><input type="hidden" name="eylem" value="dokum_temizle"><button class="btn btn-sm btn-ghost">Temizle</button></form><?php endif; ?></div>
  <?php if ($sonuc): ?>
    <p class="hint" style="margin-top:0"><?= e($kontrol['kaynak']) ?> · <?= count($kontrol['numaralar']) ?> e-reçete numarası bulundu<?= $kontrol['adet'] !== null ? ' · elle girilen adet ' . (int) $kontrol['adet'] : '' ?>. Döküm saklanmadı.</p>
    <?php if ($kontrol['numaralar']): ?>
    <div class="grid cols-2" style="gap:14px">
      <div>
        <h3 class="<?= $sonuc['pdfte_fazla'] ? 'text-danger' : '' ?>">Medula'da var, OptiFlow'da bu ay işaretli değil (<?= count($sonuc['pdfte_fazla']) ?>)</h3>
        <?php if (!$sonuc['pdfte_fazla']): ?><p class="muted small">Yok.</p><?php endif; ?>
        <ul class="kv">
          <?php foreach ($sonuc['pdfte_fazla'] as $no => $o): ?>
            <li><span><b><?= e($no) ?></b> <?php if ($o): ?>· <a class="link" href="order.php?id=<?= (int) $o['id'] ?>#medula"><?= e(order_no((int) $o['id'])) ?></a> <?= e($kisi($o)) ?><?php endif; ?></span>
              <small class="muted"><?= !$o ? 'OptiFlow\'da bu e-reçete yok' : ($o['medula_islendi_at'] ? date_tr((string) $o['medula_islendi_at']) . ' tarihinde işaretli (başka ay)' : 'işaretlenmemiş') ?></small></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div>
        <h3 class="<?= $sonuc['optiflowda_fazla'] ? 'text-danger' : '' ?>">OptiFlow'da işaretli, Medula dökümünde yok (<?= count($sonuc['optiflowda_fazla']) ?>)</h3>
        <?php if (!$sonuc['optiflowda_fazla']): ?><p class="muted small">Yok.</p><?php endif; ?>
        <ul class="kv">
          <?php foreach ($sonuc['optiflowda_fazla'] as $o): ?>
            <li><span><b><?= e((string) $o['sgk_erecete']) ?></b> · <a class="link" href="order.php?id=<?= (int) $o['id'] ?>#medula"><?= e(order_no((int) $o['id'])) ?></a> <?= e($kisi($o)) ?></span><small class="muted"><?= e(date_tr((string) $o['medula_islendi_at'])) ?></small></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <p class="small" style="margin-top:8px"><b><?= count($sonuc['eslesen']) ?></b> reçete iki tarafta da var.</p>
    <?php endif; ?>
    <?php if ($sonuc['numarasiz']): ?>
      <p class="small text-danger">e-Reçete numarası girilmemiş <?= count($sonuc['numarasiz']) ?> işaretli sipariş numarayla karşılaştırılamadı:
        <?php foreach ($sonuc['numarasiz'] as $j => $o): ?><?= $j ? ', ' : '' ?><a class="link" href="order.php?id=<?= (int) $o['id'] ?>"><?= e(order_no((int) $o['id'])) ?></a><?php endforeach; ?></p>
    <?php endif; ?>
  <?php else: ?>
    <p class="muted small">Ay sonunda Medula'dan aldığınız fatura / reçete dökümünü (PDF) yükleyin: dökümdeki reçete adedi ve e-reçete numaraları,
      OptiFlow'da bu ay "Medula'ya işlendi" işaretlenen reçetelerle karşılaştırılır. Döküm sunucuda saklanmaz.</p>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="stack" style="gap:10px;margin-top:10px">
    <?= csrf_field() ?><input type="hidden" name="ay" value="<?= e($ay) ?>"><input type="hidden" name="eylem" value="dokum">
    <div class="grid cols-2">
      <label class="field"><span>Medula dökümü (PDF)</span><input type="file" name="dosya[]" accept="application/pdf,.pdf"></label>
      <label class="field"><span>ya da dökümdeki reçete adedi</span><input name="adet" type="number" min="0" max="100000" placeholder="örn. 48"></label>
    </div>
    <details><summary class="linkish">PDF okunmazsa: Medula ekranındaki listeyi yapıştırın</summary>
      <label class="field" style="margin-top:8px"><span>Liste metni</span><textarea name="metin" rows="4" placeholder="Medula'daki reçete listesini seçip kopyalayın, buraya yapıştırın"></textarea></label></details>
    <div><button class="btn btn-sm btn-primary"><?= icon('search') ?> Karşılaştır</button></div>
  </form>
</section>

<section class="card">
  <div class="card-head"><h2>Faturalanacak reçeteler</h2><span class="badge"><?= count($liste) ?></span></div>
  <?php if (!$liste): ?>
    <?= empty_state('Faturalanacak reçete yok', 'Bu dönemin sonuna kadar Medula\'ya işlendi işaretlenmiş ve henüz SGK faturasına girmemiş SGK\'lı reçete yok.') ?>
  <?php else: ?>
  <form method="post" class="stack" style="gap:12px">
    <?= csrf_field() ?><input type="hidden" name="ay" value="<?= e($ay) ?>"><input type="hidden" name="eylem" value="olustur">
    <div class="table-wrap"><table class="table">
      <thead><tr><th></th><th>Sipariş</th><th>Hasta</th><th class="hide-sm">e-Reçete</th><th>Medula</th><th class="num">SGK payı</th></tr></thead>
      <tbody><?php foreach ($liste as $o): ?>
        <tr>
          <td><input type="checkbox" name="siparis[]" value="<?= (int) $o['id'] ?>" checked aria-label="Faturaya ekle"></td>
          <td><a class="link" href="order.php?id=<?= (int) $o['id'] ?>"><?= e(order_no((int) $o['id'])) ?></a></td>
          <td><?= e($kisi($o)) ?></td>
          <td class="hide-sm"><?= $o['sgk_erecete'] ? e((string) $o['sgk_erecete']) : '<span class="text-danger small">yok</span>' ?></td>
          <td><?= e(date_tr((string) $o['medula_islendi_at'])) ?><?= $o['onceki'] ? ' <span class="badge tone-amber">önceki ay</span>' : '' ?></td>
          <td class="num"><?= e(money($o['sgk_amount'])) ?><?= (float) $o['kdv'] !== fatura_varsayilan_kdv() ? '<small class="block muted">KDV %' . e((string) (float) $o['kdv']) . '</small>' : '' ?></td>
        </tr>
      <?php endforeach; ?></tbody>
      <tfoot><tr><th colspan="5">Toplam (<?= count($liste) ?> reçete<?= $onceki ? ', ' . $onceki . ' önceki aydan' : '' ?>)</th><th class="num"><?= e(money($toplam)) ?></th></tr></tfoot>
    </table></div>
    <label class="field" style="max-width:320px"><span>Medula dönem toplamı (isteğe bağlı)</span><input name="medula_toplam" inputmode="decimal" placeholder="<?= e(number_format($toplam, 2, ',', '.')) ?>" data-money></label>
    <p class="hint">Medula'nın bu dönem için hesapladığı fatura tutarı OptiFlow toplamından farklıysa buraya yazın; fatura o tutarla kesilir, fark notta görünür.</p>
    <div><button class="btn btn-primary"><?= icon('receipt') ?> Seçilenlerle SGK faturası taslağı oluştur</button></div>
  </form>
  <?php endif; ?>
</section>

<?php if ($bekleyen): ?>
<section class="card">
  <div class="card-head"><h2>Medula'ya işlendi işaretlenmemiş SGK'lı siparişler</h2><span class="badge tone-amber"><?= count($bekleyen) ?></span></div>
  <p class="muted small">Bunlar SGK faturasına girmez. Medula'ya işlediklerinizi seçip işaretleyin (siparişin SGK kartından tek tek de işaretlenebilir).</p>
  <form method="post" class="stack" style="gap:10px">
    <?= csrf_field() ?><input type="hidden" name="ay" value="<?= e($ay) ?>"><input type="hidden" name="eylem" value="toplu_isaretle">
    <div class="table-wrap"><table class="table">
      <thead><tr><th></th><th>Sipariş</th><th>Hasta</th><th class="hide-sm">e-Reçete</th><th>Durum</th><th class="num">SGK payı</th></tr></thead>
      <tbody><?php foreach ($bekleyen as $o): ?>
        <tr>
          <td><input type="checkbox" name="bekleyen[]" value="<?= (int) $o['id'] ?>" aria-label="Seç"></td>
          <td><a class="link" href="order.php?id=<?= (int) $o['id'] ?>#medula"><?= e(order_no((int) $o['id'])) ?></a></td>
          <td><?= e($kisi($o)) ?></td>
          <td class="hide-sm"><?= $o['sgk_erecete'] ? e((string) $o['sgk_erecete']) : '—' ?></td>
          <td><small><?= e(stage_label((string) $o['order_stage'])) ?><?= $o['delivered_at'] ? ' · ' . e(date_tr((string) $o['delivered_at'])) : '' ?></small></td>
          <td class="num"><?= e(money($o['sgk_amount'])) ?></td>
        </tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <div class="btn-row" style="align-items:flex-end">
      <label class="field" style="max-width:170px"><span>Medula işlem tarihi</span><input type="date" name="tarih" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>"></label>
      <button class="btn btn-sm"><?= icon('check') ?> Seçilenleri Medula'ya işlendi işaretle</button>
    </div>
  </form>
</section>
<?php endif; ?>
<?php page_end();
