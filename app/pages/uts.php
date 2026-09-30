<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.13.0 — ÜTS bildirimleri
   Sekmeler: Mal kabul (gelen) · Stok · Bildirimler ; ?urun=ID tek ürün kartı
   ========================================================================== */

$me = require_login();
ozellik_gereksin('uts_bildirim');

$tab = query('tab', 'gelen');
$sekmeler = ['gelen' => 'Mal kabul', 'stok' => 'ÜTS stoğu', 'bildirimler' => 'Bildirimler'];
if (!isset($sekmeler[$tab])) {
    $tab = 'gelen';
}

/* ---------- İşlemler ---------- */
if (is_post()) {
    $eylem = post('eylem');
    $don = 'uts.php?tab=' . rawurlencode(post('tab', $tab));
    $idler = array_map('intval', (array) ($_POST['id'] ?? []));
    $sadeceYonetici = ['imha', 'iade', 'elle', 'iptal', 'deneme_kuyruga', 'urun_duzenle'];
    if (in_array($eylem, $sadeceYonetici, true) && !is_super()) {
        flash('Bu işlem için yönetici yetkisi gerekir.', 'error');
        redirect($don);
    }
    try {
        switch ($eylem) {
            case 'gelen_getir':
                $r = uts_gelenleri_getir();
                flash($r['ok'] ? 'ÜTS\'den ' . $r['sayi'] . ' kabul bekleyen ürün alındı.' . ($r['mesaj'] !== '' ? ' ' . $r['mesaj'] : '') : $r['mesaj'], $r['ok'] ? 'ok' : 'error');
                break;
            case 'deneme_ornek':
                flash(uts_deneme_ornek_gelen() . ' örnek gelen ürün eklendi (deneme modu).');
                break;
            case 'kabul':
                if (!$idler) {
                    throw new DomainException('Kabul edilecek ürün seçin.');
                }
                $n = uts_gelenleri_kabul_et($idler, (array) ($_POST['kat'] ?? []), post('cerceve_stok') === '1', post('kart_olustur') === '1');
                audit('uts_kabul', 'uts', null, ['adet' => $n]);
                uts_kuyrugu_isle(10, 20);
                flash($n . ' ürün kabul edildi; ÜTS alma bildirimi gönderildi / sıraya alındı.');
                break;
            case 'stok_okut':
                // Çerçeve adedini artırmak stok sayısını değiştirir: yalnızca yönetici.
                $u = uts_stoga_okut((string) ($_POST['kod'] ?? ''), post('kategori', 'cerceve'), max(1, post_int('adet')), is_super() && post('cerceve_stok') === '1');
                audit('uts_stok', 'uts', (int) $u['id'], ['ürün' => uts_urun_etiketi($u)]);
                flash('Stoğa kaydedildi: ' . uts_urun_etiketi($u) . ($u['frame_item_id'] ? ' — çerçeve kartına bağlandı.' : '.'));
                $don = 'uts.php?tab=stok&son=' . (int) $u['id'];
                break;
            case 'imha':
                $n = uts_imha_et($idler, post('grk'), post('dga'), post('bno'));
                audit('uts_imha', 'uts', null, ['adet' => $n, 'gerekçe' => post('grk'), 'belge' => post('bno')]);
                flash($n . ' ürün için imha bildirimi sıraya alındı.');
                break;
            case 'iade':
                $n = uts_tedarikciye_iade($idler, post_int('supplier_id'), post('iade_bno'));
                audit('uts_iade', 'uts', null, ['adet' => $n, 'tedarikçi' => post_int('supplier_id'), 'belge' => post('iade_bno')]);
                flash($n . ' ürün için tedarikçiye verme (iade) bildirimi sıraya alındı.');
                break;
            case 'onayla':
                $n = uts_bildirimleri_onayla($idler);
                audit('uts_bildirim', 'uts', null, ['işlem' => 'onayla', 'adet' => $n]);
                $o = uts_kuyrugu_isle(max(10, min(25, $n)), 20);
                flash($n . ' bildirim onaylandı; ' . $o['gonderilen'] . ' tanesi ÜTS\'ye iletildi.' . ($o['durdu'] ? ' ' . $o['durdu'] : ''), $o['durdu'] ? 'warn' : 'ok');
                break;
            case 'simdi_gonder':
                $o = uts_kuyrugu_isle(25, 25);
                flash($o['gonderilen'] . ' bildirim iletildi' . ($o['hata'] ? ', ' . $o['hata'] . ' hata' : '') . '.' . ($o['durdu'] ? ' ' . $o['durdu'] : ''), $o['hata'] ? 'warn' : 'ok');
                break;
            case 'tekrar':
                flash(uts_bildirim_tekrar(post_int('bid')) ? 'Bildirim yeniden sıraya alındı.' : 'Bu bildirim yeniden denenemez.', 'info');
                uts_kuyrugu_isle(5, 15);
                break;
            case 'elle':
                $ok = uts_bildirim_elle_tamam(post_int('bid'));
                audit('uts_bildirim', 'uts', post_int('bid'), ['işlem' => 'ÜTS\'de elle yapıldı']);
                flash($ok ? 'Bildirim "ÜTS\'de elle yapıldı" olarak işaretlendi.' : 'Bu bildirim işaretlenemez.', $ok ? 'ok' : 'error');
                break;
            case 'iptal':
                $ok = uts_bildirim_iptal(post_int('bid'));
                audit('uts_bildirim', 'uts', post_int('bid'), ['işlem' => 'iptal']);
                flash($ok ? 'Bildirim iptal edildi.' : 'Bu bildirim iptal edilemez (gönderilmiş ya da mal kabul bildirimi).', $ok ? 'ok' : 'error');
                break;
            case 'deneme_kuyruga':
                $n = uts_deneme_kayitlarini_kuyruga_al();
                audit('uts_bildirim', 'uts', null, ['işlem' => 'deneme kayıtlarını gönder', 'adet' => $n]);
                flash($n . ' deneme kaydı gerçek ÜTS\'ye gönderilmek üzere sıraya alındı.');
                break;
            case 'urun_duzenle':
                $uid = post_int('urun_id');
                $kat = post('kategori');
                if (isset(uts_kategoriler()[$kat])) {
                    q('UPDATE uts_urunler SET kategori = ?, updated_at = ? WHERE id = ?', [$kat, uts_simdi(), $uid]);
                }
                if (post('cerceve_bagla') === '1') {
                    $fid = uts_cerceve_bagla($uid, false, post('kart_olustur') === '1');
                    flash($fid ? 'Çerçeve kartına bağlandı.' : 'Bu GTIN ile çerçeve kartı bulunamadı.', $fid ? 'ok' : 'warn');
                } else {
                    flash('Ürün güncellendi.');
                }
                $don = 'uts.php?urun=' . $uid;
                break;
            default:
                flash('İşlem tanınmadı.', 'error');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
        if ($eylem === 'urun_duzenle') {
            $don = 'uts.php?urun=' . post_int('urun_id');
        }
    }
    redirect($don);
}

$ortam = uts_ortam();
$ozet = uts_ozet();
$yetkiHatasi = setting('uts_yetki_hatasi', '');
$ortamRozeti = '<span class="badge tone-' . ($ortam === 'canli' ? 'green' : ($ortam === 'test' ? 'blue' : 'amber')) . '">' . e(UTS_ORTAMLAR[$ortam]) . '</span>';

/* ---------- Tek ürün kartı ---------- */
if (query_int('urun') > 0) {
    $u = row('SELECT u.*, f.brand, f.model, f.color, f.size, f.price FROM uts_urunler u LEFT JOIN frame_items f ON f.id = u.frame_item_id WHERE u.id = ?', [query_int('urun')]);
    if (!$u) {
        render_error_page('Ürün bulunamadı', 'Silinmiş olabilir.');
    }
    [$dAd, $dTon] = uts_urun_durumlari()[$u['durum']] ?? [$u['durum'], 'gray'];
    $bl = rows('SELECT * FROM uts_bildirimler WHERE urun_id = ? ORDER BY id DESC', [(int) $u['id']]);
    page_start('ÜTS ürünü', 'uts-bildirim');
    ?>
    <a class="back-link" href="uts.php?tab=stok"><?= icon('arrow-left') ?> ÜTS stoğu</a>
    <?php page_header(uts_urun_etiketi($u), 'ÜTS ürünü · ' . (uts_kategoriler()[$u['kategori']] ?? ''), '<span class="badge tone-' . e($dTon) . '">' . e($dAd) . '</span>', '', 'ÜTS'); ?>
    <div class="split">
      <div class="split-main">
        <section class="card">
          <ul class="kv">
            <li><span>Ürün numarası (UNO / GTIN)</span><b><code><?= e((string) $u['uno']) ?></code></b></li>
            <?php if ($u['sno']): ?><li><span>Seri no</span><b><code><?= e((string) $u['sno']) ?></code></b></li><?php endif; ?>
            <?php if ($u['lno']): ?><li><span>Parti / lot</span><b><code><?= e((string) $u['lno']) ?></code></b></li><?php endif; ?>
            <?php if (!uts_seri_mi($u)): ?><li><span>Adet</span><b><?= (int) $u['adet'] ?></b></li><?php endif; ?>
            <?php if ($u['skt']): ?><li><span>Son kullanma</span><b class="<?= $u['skt'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(date_tr((string) $u['skt'])) ?></b></li><?php endif; ?>
            <?php if ($u['gonderen']): ?><li><span>Gönderen</span><b><?= e((string) $u['gonderen']) ?></b></li><?php endif; ?>
            <?php if ($u['belge_no']): ?><li><span>Belge no</span><b><?= e((string) $u['belge_no']) ?></b></li><?php endif; ?>
            <?php if ($u['frame_item_id']): ?><li><span>Çerçeve kartı</span><b><a class="link" href="cerceve.php?duzenle=<?= (int) $u['frame_item_id'] ?>"><?= e(frame_item_label($u)) ?></a><?= $u['price'] !== null && can_see_amounts() ? ' · ' . money($u['price']) : '' ?></b></li><?php endif; ?>
            <?php if ($u['order_id']): ?><li><span>Sipariş</span><b><a class="link" href="order.php?id=<?= (int) $u['order_id'] ?>#uts"><?= e(order_no((int) $u['order_id'])) ?></a><?= $u['satis_turu'] === 'sgk' ? ' · SGK' : ($u['satis_turu'] === 'ucretli' ? ' · ücretli' : '') ?></b></li><?php endif; ?>
            <li><span>Kayıt</span><b><?= e($u['kaynak'] === 'okutma' ? 'Karekod okutularak' : 'ÜTS mal kabul') ?> · <?= e(date_tr((string) $u['created_at'], true)) ?></b></li>
          </ul>
        </section>
        <section class="card">
          <div class="card-head"><h2>ÜTS bildirimleri</h2></div>
          <?php if (!$bl): ?><p class="muted small">Bu ürün için bildirim yok.</p><?php else: ?>
            <div class="table-wrap"><table class="table"><thead><tr><th>Tür</th><th>Durum</th><th class="hide-sm">Tarih</th></tr></thead><tbody>
            <?php foreach ($bl as $b): [$bAd, $bTon] = uts_bildirim_durumlari()[$b['durum']] ?? [$b['durum'], 'gray']; ?>
              <tr><td><?= e(uts_turler()[$b['tur']]['ad'] ?? $b['tur']) ?></td><td><span class="badge tone-<?= e($bTon) ?>"><?= e($bAd) ?></span><?php if ($b['son_hata']): ?><small class="block muted"><?= e((string) $b['son_hata']) ?></small><?php endif; ?></td><td class="hide-sm"><small><?= e(date_tr((string) ($b['gonderilme'] ?: $b['created_at']), true)) ?></small></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
          <?php endif; ?>
        </section>
      </div>
      <aside class="split-side">
        <?php if (is_super()): ?>
        <section class="card">
          <div class="card-head"><h2>Düzenle</h2></div>
          <form method="post" class="stack" style="gap:10px">
            <?= csrf_field() ?><input type="hidden" name="eylem" value="urun_duzenle"><input type="hidden" name="urun_id" value="<?= (int) $u['id'] ?>">
            <label class="field"><span>Ürün türü</span><select name="kategori"><?= select_options(uts_kategoriler(), (string) $u['kategori']) ?></select></label>
            <?php if (!$u['frame_item_id']): ?>
              <label class="check"><input type="checkbox" name="cerceve_bagla" value="1"> Çerçeve kartına bağla (GTIN ile)</label>
              <label class="check"><input type="checkbox" name="kart_olustur" value="1"> Kart yoksa oluştur</label>
            <?php endif; ?>
            <button class="btn btn-sm btn-primary" style="align-self:flex-start">Kaydet</button>
          </form>
        </section>
        <?php endif; ?>
      </aside>
    </div>
    <?php
    page_end();
    exit;
}

page_start('ÜTS bildirimleri', 'uts-bildirim');
page_header('ÜTS bildirimleri', 'Mal kabul, ücretli satış, iade ve imha bildirimleri. SGK\'lı satışlarda ÜTS düşümünü Medula yapar.', $ortamRozeti, '', 'ÜTS');
?>
<?php if ($ortam === 'deneme'): ?>
  <div class="alert alert-warn">Deneme modundasınız: bildirimler <b>ÜTS'ye gönderilmez</b>, başarılı sayılır. Sistem token'ını aldıktan sonra <?= is_super() ? '<a class="link" href="settings.php?tab=uts">Ayarlar › ÜTS</a>' : 'Ayarlar › ÜTS' ?> bölümünden Test ya da Canlı ortama geçin.</div>
<?php elseif (!uts_hazir_mi()): ?>
  <div class="alert alert-error">ÜTS sistem token'ı girilmemiş; bildirimler sırada bekliyor. <?= is_super() ? '<a class="link" href="settings.php?tab=uts">Ayarlar › ÜTS</a>' : 'Yöneticinize bildirin.' ?></div>
<?php elseif ($yetkiHatasi !== ''): ?>
  <div class="alert alert-error">Son ÜTS isteği yetki hatası verdi: <?= e($yetkiHatasi) ?></div>
<?php endif; ?>

<section class="stats">
  <div class="stat"><small>ÜTS stoğu</small><b><?= $ozet['stokta'] ?></b><span><?= $ozet['ayrilmis'] ?> tanesi siparişe ayrılmış</span></div>
  <div class="stat <?= $ozet['gelen'] ? 'tone-amber' : '' ?>"><small>Kabul bekleyen</small><b><?= $ozet['gelen'] ?></b><span><?= $ozet['alma'] ?> alma bildirimi gidiyor</span></div>
  <div class="stat <?= $ozet['hata'] ? 'tone-red' : ($ozet['onay'] ? 'tone-amber' : '') ?>"><small>Bildirim</small><b><?= $ozet['hata'] + $ozet['onay'] ?></b><span><?= $ozet['hata'] ?> hata · <?= $ozet['onay'] ?> onay · <?= $ozet['sirada'] ?> sırada</span></div>
  <div class="stat <?= $ozet['skt_gecmis'] ? 'tone-red' : '' ?>"><small>SKT geçmiş</small><b><?= $ozet['skt_gecmis'] ?></b><span><?= $ozet['skt_yakin'] ?> ürün 30 gün içinde doluyor</span></div>
</section>

<nav class="tabs" aria-label="ÜTS">
  <?php foreach ($sekmeler as $k => $ad):
      $sayi = match ($k) { 'gelen' => $ozet['gelen'], 'stok' => $ozet['stokta'], default => $ozet['hata'] + $ozet['onay'] }; ?>
    <a class="tab <?= $tab === $k ? 'active' : '' ?>" href="uts.php?tab=<?= e($k) ?>"><?= e($ad) ?> <em><?= (int) $sayi ?></em></a>
  <?php endforeach; ?>
</nav>

<?php if ($tab === 'gelen'):
    $gelenler = rows("SELECT * FROM uts_urunler WHERE durum = 'gelen' OR (durum = 'stokta' AND vbi IS NOT NULL AND kaynak = 'okutma' AND id NOT IN (SELECT urun_id FROM uts_bildirimler WHERE tur = 'alma' AND urun_id IS NOT NULL)) ORDER BY gonderen, belge_no, id LIMIT 500");
    $almada = rows("SELECT u.*, b.durum AS b_durum, b.son_hata FROM uts_urunler u LEFT JOIN uts_bildirimler b ON b.urun_id = u.id AND b.tur = 'alma' WHERE u.durum = 'alma_bekliyor' ORDER BY u.id DESC LIMIT 100");
    $sonGetir = setting('uts_gelen_son', '');
?>
  <section class="card">
    <div class="card-head">
      <h2>ÜTS'de size verilen ürünler</h2>
      <small class="muted"><?= $sonGetir !== '' ? 'Son sorgu: ' . e(date_tr($sonGetir, true)) : 'Henüz sorgulanmadı' ?></small>
    </div>
    <div class="btn-row" style="margin-bottom:12px">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="gelen_getir"><button class="btn btn-primary btn-sm"><?= icon('download') ?> ÜTS'den getir</button></form>
      <?php if ($ortam === 'deneme'): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="deneme_ornek"><button class="btn btn-sm">Örnek gelen ürün ekle (deneme)</button></form>
      <?php endif; ?>
    </div>
    <?php if (!$gelenler): ?>
      <?= empty_state('Kabul bekleyen ürün yok', 'Tedarikçiniz ÜTS\'de size "verme" bildirimi yaptığında ürünler burada listelenir. Faturası gelen ürünler için "ÜTS\'den getir"e basın.') ?>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="eylem" value="kabul"><input type="hidden" name="tab" value="gelen">
        <div class="table-wrap"><table class="table">
          <thead><tr><th class="selc"><input type="checkbox" data-hepsini-sec="id[]" aria-label="Hepsini seç"></th><th>Ürün</th><th class="hide-sm">Gönderen · belge</th><th>Tür</th></tr></thead>
          <tbody>
          <?php foreach ($gelenler as $g): ?>
            <tr>
              <td class="selc"><input type="checkbox" name="id[]" value="<?= (int) $g['id'] ?>" aria-label="Seç"></td>
              <td><?= e(uts_urun_etiketi($g)) ?><small class="block muted"><code><?= e((string) $g['uno']) ?></code><?= $g['skt'] ? ' · SKT ' . e(date_tr((string) $g['skt'])) : '' ?><?= $g['durum'] === 'stokta' ? ' · <b>stoğa okutulmuş, ÜTS\'de kabul edilmemiş</b>' : '' ?></small></td>
              <td class="hide-sm"><small><?= e((string) ($g['gonderen'] ?: '—')) ?><?= $g['belge_no'] ? '<br>Belge ' . e((string) $g['belge_no']) : '' ?></small></td>
              <td><select name="kat[<?= (int) $g['id'] ?>]" aria-label="Ürün türü"><?= select_options(uts_kategoriler(), (string) $g['kategori']) ?></select></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <div class="stack" style="gap:6px;margin-top:12px">
          <label class="check"><input type="checkbox" name="cerceve_stok" value="1" checked> Çerçeve / güneş gözlüğü adedini çerçeve stoğuna ekle <small class="muted">(bu ürünleri çerçeve stoğuna elle de girdiyseniz işareti kaldırın)</small></label>
          <label class="check"><input type="checkbox" name="kart_olustur" value="1" checked> Çerçeve kartı yoksa ÜTS'deki marka/model adıyla oluştur <small class="muted">(fiyatı çerçeve stoğundan girin)</small></label>
          <div><button class="btn btn-primary"><?= icon('check') ?> Seçilenleri kabul et (ÜTS alma bildirimi)</button></div>
        </div>
      </form>
    <?php endif; ?>
  </section>
  <?php if ($almada): ?>
    <section class="card">
      <div class="card-head"><h2>Alma bildirimi gidiyor</h2><small class="muted">Bildirim tamamlanınca ürün stoğa geçer ve satılabilir.</small></div>
      <div class="table-wrap"><table class="table"><tbody>
      <?php foreach ($almada as $a): [$bAd, $bTon] = uts_bildirim_durumlari()[$a['b_durum'] ?? 'bekliyor'] ?? ['—', 'gray']; ?>
        <tr><td><a class="link" href="uts.php?urun=<?= (int) $a['id'] ?>"><?= e(uts_urun_etiketi($a)) ?></a></td><td><span class="badge tone-<?= e($bTon) ?>"><?= e($bAd) ?></span><?php if ($a['son_hata']): ?><small class="block muted"><?= e((string) $a['son_hata']) ?></small><?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    </section>
  <?php endif; ?>

<?php elseif ($tab === 'stok'):
    $durum = query('durum', 'stokta');
    $durumlar = ['stokta' => 'Stokta', 'ayrilmis' => 'Siparişe ayrılmış', 'skt' => 'SKT geçmiş / yakın', 'cikis' => 'Çıkış yapmış', 'hepsi' => 'Tümü'];
    if (!isset($durumlar[$durum])) {
        $durum = 'stokta';
    }
    $ara = trim(query('q'));
    $kat = query('kategori');
    $where = ["u.durum NOT IN ('gelen')"];
    $p = [];
    switch ($durum) {
        case 'stokta':   $where[] = "u.durum = 'stokta'"; break;
        case 'ayrilmis': $where[] = "u.durum = 'stokta' AND u.order_id IS NOT NULL"; break;
        case 'skt':      $where[] = "u.durum = 'stokta' AND u.skt IS NOT NULL AND u.skt <= ?"; $p[] = date('Y-m-d', strtotime('+30 days')); break;
        case 'cikis':    $where[] = "u.durum IN ('satildi','sgk','iade','imha')"; break;
    }
    if ($ara !== '') {
        $k = uts_karekod_coz($ara);
        if ($k['uno'] !== '') {
            $where[] = 'u.uno = ?';
            $p[] = $k['uno'];
            if ($k['sno'] !== '') {
                $where[] = 'u.sno = ?';
                $p[] = $k['sno'];
            }
        } else {
            $where[] = '(u.uno LIKE ? OR u.sno LIKE ? OR u.lno LIKE ? OR u.marka_model LIKE ? OR u.gonderen LIKE ?)';
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $ara) . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }
    }
    if (isset(uts_kategoriler()[$kat])) {
        $where[] = 'u.kategori = ?';
        $p[] = $kat;
    }
    $w = implode(' AND ', $where);
    $sayfa = paginate((int) scalar("SELECT COUNT(*) FROM uts_urunler u WHERE $w", $p), 50);
    $liste = rows("SELECT u.*, f.price AS f_price FROM uts_urunler u LEFT JOIN frame_items f ON f.id = u.frame_item_id WHERE $w ORDER BY (u.skt IS NULL), u.skt, u.id DESC LIMIT " . (int) $sayfa['per'] . ' OFFSET ' . (int) $sayfa['offset'], $p);
    $tedarikciler = rows('SELECT id, name, uts_kurum_no FROM suppliers WHERE is_active = 1 ORDER BY name');
    $okutKod = query('kod');
?>
  <section class="card">
    <div class="card-head"><h2>Karekod okutarak stoğa kaydet</h2><small class="muted">ÜTS'de daha önce kabul ettiğiniz ürünler için (bildirim yapılmaz)</small></div>
    <form method="post" class="grid cols-3" style="align-items:end" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="eylem" value="stok_okut"><input type="hidden" name="tab" value="stok">
      <label class="field"><span>Karekod</span><input name="kod" required maxlength="200" value="<?= e($okutKod) ?>" data-barkod-alani placeholder="Okutun (Enter)" <?= $okutKod === '' ? 'autofocus' : '' ?>></label>
      <label class="field"><span>Ürün türü</span><select name="kategori"><?= select_options(uts_kategoriler(), 'cerceve') ?></select></label>
      <div class="form-actions" style="gap:8px;flex-wrap:wrap">
        <label class="field" style="max-width:90px"><span>Adet (lot)</span><input type="number" name="adet" value="1" min="1" max="999"></label>
        <?php if (is_super()): ?><label class="check"><input type="checkbox" name="cerceve_stok" value="1"> Çerçeve adedini artır</label><?php endif; ?>
        <button class="btn btn-primary"><?= icon('plus') ?> Kaydet</button>
      </div>
    </form>
  </section>

  <section class="card">
    <form method="get" class="filters">
      <input type="hidden" name="tab" value="stok">
      <label class="field"><span>Durum</span><select name="durum" data-auto-submit><?= select_options($durumlar, $durum) ?></select></label>
      <label class="field"><span>Tür</span><select name="kategori" data-auto-submit><option value="">Tümü</option><?= select_options(uts_kategoriler(), $kat) ?></select></label>
      <label class="field"><span>Ara</span><input type="search" name="q" value="<?= e($ara) ?>" placeholder="Karekod, seri, marka, gönderen"></label>
      <div class="filter-actions"><button class="btn">Filtrele</button></div>
    </form>
    <?php if (!$liste): ?>
      <?= empty_state('Kayıt yok', 'Mal kabul ettiğiniz ya da karekodunu okuttuğunuz ürünler burada görünür.') ?>
    <?php else: ?>
      <form method="post" id="stok-form">
        <?= csrf_field() ?><input type="hidden" name="tab" value="stok">
        <div class="table-wrap"><table class="table">
          <thead><tr><?php if (is_super()): ?><th class="selc"><input type="checkbox" data-hepsini-sec="id[]" aria-label="Hepsini seç"></th><?php endif; ?><th>Ürün</th><th>Tür</th><th>SKT</th><th>Durum</th></tr></thead>
          <tbody>
          <?php foreach ($liste as $u): [$dAd, $dTon] = uts_urun_durumlari()[$u['durum']] ?? [$u['durum'], 'gray'];
              $sktGecti = $u['skt'] && $u['skt'] < date('Y-m-d'); ?>
            <tr class="<?= query_int('son') === (int) $u['id'] ? 'row-highlight' : '' ?>">
              <?php if (is_super()): ?><td class="selc"><?php if ($u['durum'] === 'stokta' && !$u['order_id']): ?><input type="checkbox" name="id[]" value="<?= (int) $u['id'] ?>" aria-label="Seç"><?php endif; ?></td><?php endif; ?>
              <td><a class="link" href="uts.php?urun=<?= (int) $u['id'] ?>"><?= e(uts_urun_etiketi($u)) ?></a><small class="block muted"><code><?= e((string) $u['uno']) ?></code><?= $u['f_price'] !== null && can_see_amounts() ? ' · ' . money($u['f_price']) : '' ?></small></td>
              <td><small><?= e(uts_kategoriler()[$u['kategori']] ?? '') ?></small></td>
              <td><small class="<?= $sktGecti ? 'text-danger' : '' ?>"><?= $u['skt'] ? e(date_tr((string) $u['skt'])) : '—' ?></small></td>
              <td><span class="badge tone-<?= e($dTon) ?>"><?= e($dAd) ?></span><?php if ($u['order_id']): ?><small class="block"><a class="link" href="order.php?id=<?= (int) $u['order_id'] ?>#uts"><?= e(order_no((int) $u['order_id'])) ?></a></small><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?= pagination_links($sayfa) ?>
      </form>
      <?php if (is_super()): ?>
        <div class="grid cols-2" style="margin-top:14px;gap:14px">
          <details class="card inset">
            <summary class="linkish"><?= icon('trash') ?> Seçilenleri imha et (ÜTS imha / bertaraf)</summary>
            <div class="stack" style="gap:8px;margin-top:10px">
              <label class="field"><span>Gerekçe</span><select name="grk" form="stok-form"><?= select_options(uts_imha_gerekceleri(), 'SON_KULLANMA_TARIHI_GECMIS') ?></select></label>
              <label class="field"><span>Açıklama (Diğer seçilirse)</span><input name="dga" form="stok-form" maxlength="200"></label>
              <label class="field"><span>Tutanak / belge no</span><input name="bno" form="stok-form" maxlength="40"></label>
              <button class="btn btn-sm danger" form="stok-form" name="eylem" value="imha" data-onay="Seçilen ürünler için ÜTS'ye imha bildirimi yapılsın mı?">İmha bildirimi yap</button>
            </div>
          </details>
          <details class="card inset">
            <summary class="linkish"><?= icon('truck') ?> Seçilenleri tedarikçiye iade et (ÜTS verme)</summary>
            <div class="stack" style="gap:8px;margin-top:10px">
              <label class="field"><span>Tedarikçi</span><select name="supplier_id" form="stok-form"><option value="">Seçin</option><?php foreach ($tedarikciler as $t): ?><option value="<?= (int) $t['id'] ?>"<?= $t['uts_kurum_no'] ? '' : ' disabled' ?>><?= e($t['name']) ?><?= $t['uts_kurum_no'] ? '' : ' (ÜTS no yok)' ?></option><?php endforeach; ?></select></label>
              <label class="field"><span>İade faturası / irsaliye no</span><input name="iade_bno" form="stok-form" maxlength="40"></label>
              <button class="btn btn-sm" form="stok-form" name="eylem" value="iade" data-onay="Seçilen ürünler için tedarikçiye ÜTS verme (iade) bildirimi yapılsın mı?">İade bildirimi yap</button>
              <p class="hint">Tedarikçinin ÜTS kurum numarası tedarikçi kartına girilmelidir.</p>
            </div>
          </details>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

<?php else: /* bildirimler */
    $fDurum = query('durum', 'dikkat');
    $fDurumlar = ['dikkat' => 'Hata ve onay bekleyen', 'sirada' => 'Sırada', 'gonderildi' => 'İletilen', 'hepsi' => 'Tümü'];
    if (!isset($fDurumlar[$fDurum])) {
        $fDurum = 'dikkat';
    }
    $where = ['1=1'];
    $p = [];
    switch ($fDurum) {
        case 'dikkat':     $where[] = "b.durum IN ('hata','onay_bekliyor')"; break;
        case 'sirada':     $where[] = "b.durum IN ('bekliyor','gonderiliyor')"; break;
        case 'gonderildi': $where[] = "b.durum IN ('gonderildi','elle')"; break;
    }
    if (query_int('siparis') > 0) {
        $where[] = 'b.order_id = ?';
        $p[] = query_int('siparis');
    }
    $w = implode(' AND ', $where);
    $sayfa = paginate((int) scalar("SELECT COUNT(*) FROM uts_bildirimler b WHERE $w", $p), 50);
    $liste = rows(
        "SELECT b.*, u.uno, u.sno, u.lno, u.adet AS u_adet, u.marka_model, u.kategori FROM uts_bildirimler b LEFT JOIN uts_urunler u ON u.id = b.urun_id WHERE $w ORDER BY b.id DESC LIMIT " . (int) $sayfa['per'] . ' OFFSET ' . (int) $sayfa['offset'],
        $p
    );
    $denemeKayit = $ortam !== 'deneme' ? (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE ortam = 'deneme' AND durum = 'gonderildi' AND tur <> 'alma'") : 0;
?>
  <?php if ($denemeKayit && is_super()): ?>
    <div class="alert alert-warn">
      Deneme modundayken "iletildi" sayılan <b><?= $denemeKayit ?></b> bildirim gerçekte ÜTS'ye gitmedi.
      <form method="post" style="display:inline" data-confirm="Deneme kayıtları gerçek ÜTS'ye gönderilmek üzere sıraya alınsın mı?"><?= csrf_field() ?><input type="hidden" name="eylem" value="deneme_kuyruga"><input type="hidden" name="tab" value="bildirimler"><button class="linkish">Şimdi ÜTS'ye gönder</button></form>
    </div>
  <?php endif; ?>
  <section class="card">
    <div class="toolbar" style="justify-content:space-between;gap:8px;flex-wrap:wrap">
      <nav class="tabs" aria-label="Bildirim durumu" style="margin:0">
        <?php foreach ($fDurumlar as $k => $ad): ?>
          <a class="tab <?= $fDurum === $k ? 'active' : '' ?>" href="uts.php?tab=bildirimler&amp;durum=<?= e($k) ?>"><?= e($ad) ?></a>
        <?php endforeach; ?>
      </nav>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="simdi_gonder"><input type="hidden" name="tab" value="bildirimler"><button class="btn btn-sm"><?= icon('download') ?> Sıradakileri şimdi gönder</button></form>
    </div>
    <?php if (!$liste): ?>
      <?= empty_state($fDurum === 'dikkat' ? 'Bakmanız gereken bildirim yok' : 'Bildirim yok', 'Teslim edilen ücretli satışlar, mal kabul, iade ve imha bildirimleri burada listelenir.') ?>
    <?php else: ?>
      <form method="post" id="bildirim-form"><?= csrf_field() ?><input type="hidden" name="tab" value="bildirimler"></form>
      <div class="table-wrap"><table class="table">
        <thead><tr><th class="selc"><input type="checkbox" form="bildirim-form" data-hepsini-sec="id[]" aria-label="Hepsini seç"></th><th>Bildirim</th><th>Ürün</th><th>Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($liste as $b): [$bAd, $bTon] = uts_bildirim_durumlari()[$b['durum']] ?? [$b['durum'], 'gray']; ?>
          <tr>
            <td class="selc"><?php if ($b['durum'] === 'onay_bekliyor'): ?><input type="checkbox" form="bildirim-form" name="id[]" value="<?= (int) $b['id'] ?>" aria-label="Seç"><?php endif; ?></td>
            <td><?= e(uts_turler()[$b['tur']]['ad'] ?? $b['tur']) ?>
              <small class="block muted">#<?= (int) $b['id'] ?> · <?= e(date_tr((string) $b['created_at'], true)) ?><?= $b['order_id'] ? ' · <a class="link" href="order.php?id=' . (int) $b['order_id'] . '#uts">' . e(order_no((int) $b['order_id'])) . '</a>' : '' ?><?= $b['ortam'] === 'deneme' ? ' · <b>deneme</b>' : '' ?></small></td>
            <td><?php if ($b['urun_id']): ?><a class="link" href="uts.php?urun=<?= (int) $b['urun_id'] ?>"><?= e(uts_urun_etiketi(['marka_model' => $b['marka_model'], 'uno' => $b['uno'], 'sno' => $b['sno'], 'lno' => $b['lno'], 'adet' => $b['adet']])) ?></a><?php else: ?>—<?php endif; ?></td>
            <td><span class="badge tone-<?= e($bTon) ?>"><?= e($bAd) ?></span>
              <?php if ($b['son_hata']): ?><small class="block <?= $b['durum'] === 'hata' ? 'text-danger' : 'muted' ?>"><?= e((string) $b['son_hata']) ?></small><?php endif; ?>
              <?php if ($b['uts_id']): ?><small class="block muted" title="ÜTS bildirim numarası"><code><?= e((string) $b['uts_id']) ?></code></small><?php endif; ?>
              <?php if ($b['durum'] === 'hata' && (int) $b['deneme'] < 6): ?><small class="block muted">Otomatik tekrar: <?= e(date_tr((string) $b['planlanan'], true)) ?></small><?php endif; ?></td>
            <td class="num">
              <div class="btn-row" style="justify-content:flex-end">
                <?php if ($b['durum'] === 'hata'): ?>
                  <form method="post"><?= csrf_field() ?><input type="hidden" name="tab" value="bildirimler"><input type="hidden" name="eylem" value="tekrar"><input type="hidden" name="bid" value="<?= (int) $b['id'] ?>"><button class="btn btn-sm">Tekrar dene</button></form>
                <?php endif; ?>
                <?php if (is_super() && in_array($b['durum'], ['hata', 'onay_bekliyor', 'bekliyor'], true)): ?>
                  <form method="post" data-confirm="Bu işlemi ÜTS web ekranından kendiniz yaptınız mı? Kayıt tamamlandı sayılacak."><?= csrf_field() ?><input type="hidden" name="tab" value="bildirimler"><input type="hidden" name="eylem" value="elle"><input type="hidden" name="bid" value="<?= (int) $b['id'] ?>"><button class="linkish">ÜTS'de elle yapıldı</button></form>
                  <?php if ($b['tur'] !== 'alma'): ?>
                    <form method="post" data-confirm="Bildirim iptal edilsin mi? ÜTS'ye gönderilmeyecek."><?= csrf_field() ?><input type="hidden" name="tab" value="bildirimler"><input type="hidden" name="eylem" value="iptal"><input type="hidden" name="bid" value="<?= (int) $b['id'] ?>"><button class="linkish danger">İptal</button></form>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?= pagination_links($sayfa) ?>
      <?php if ($ozet['onay']): ?>
        <div style="margin-top:12px"><button class="btn btn-primary" form="bildirim-form" name="eylem" value="onayla"><?= icon('check') ?> Seçilenleri onayla ve ÜTS'ye gönder</button></div>
      <?php endif; ?>
    <?php endif; ?>
  </section>
<?php endif; ?>
<?php page_end();
