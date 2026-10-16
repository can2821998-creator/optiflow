<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_login();

/* ==========================================================================
   4.34.0 — Tek akışın girişi (app/tezgah.php): ① Reçete → ② Müşteri → ③ Teklif → ④ Sipariş → ⑤ Döküm + gönder.
   ① e-Reçete no yazılır; OptiFlow Pro Medula'da sorguyu doldurur (assets/tezgah.js). Personel Medula'da kontrol
     edip "Reçeteyi aktar"a basar → bu sayfa ?gelen=ID ile açılır. Pro yoksa ?elle=1: aynı form boş açılır.
   ② Müşteri (eşleşen kayıt ya da yeni) + reçete değerleri → "bekleyen reçete" olarak müşteriye bağlanır.
     Sipariş burada AÇILMAZ: teklif-yeni.php (③) ya da teklifsizse doğrudan order-new.php (④) açılır.
   ========================================================================== */

$gelenId = is_post() ? post_int('gelen_id') : query_int('gelen');
$elle = !is_post() && query('elle') === '1';
$geriUrl = $gelenId > 0 ? 'tezgah.php?gelen=' . $gelenId : 'tezgah.php?elle=1';

/** Reçete müşteriye bağlandıktan sonraki adım: teklif (③) ya da teklifsiz sipariş (④). */
$sonrakiAdim = static function (int $customerId, int $receteId, string $sonra): string {
    $q = http_build_query(['customer_id' => $customerId, 'recete' => $receteId]);
    return ($sonra === 'siparis' ? 'order-new.php?' : 'teklif-yeni.php?') . $q;
};

/** Bu aktarım zaten müşteriye bağlandıysa / siparişe yazıldıysa oraya git. */
$oncekiKayit = static function (int $gelenId) use ($me, $sonrakiAdim): void {
    if ($gelenId <= 0) {
        return;
    }
    $kullanildi = (int) scalar('SELECT used_order_id FROM sgk_incoming WHERE id = ? AND user_id = ?', [$gelenId, (int) $me['id']]);
    if ($kullanildi > 0) {
        flash('Bu reçete zaten siparişe yazıldı.', 'info');
        redirect('order.php?id=' . $kullanildi);
    }
    $b = row('SELECT id, customer_id FROM bekleyen_receteler WHERE gelen_id = ? ORDER BY id DESC LIMIT 1', [$gelenId]);
    if ($b) {
        flash('Bu reçete müşteriye kaydedilmiş; teklifle devam edin.', 'info');
        redirect($sonrakiAdim((int) $b['customer_id'], (int) $b['id'], 'teklif'));
    }
};

if (is_post()) {
    csrf_check();
    $oncekiKayit($gelenId);
    $errors = [];

    // --- Müşteri ---
    $musteri = post('musteri', 'yeni');
    $customer = null;
    if ($musteri !== 'yeni') {
        $customer = find_customer((int) $musteri);
        if (!$customer) {
            $errors[] = 'Seçilen müşteri bulunamadı.';
        }
    } else {
        if (tr_title(post('first_name')) === '' || tr_title(post('last_name')) === '') {
            $errors[] = 'Müşteri adı ve soyadı zorunlu.';
        }
        if (in_array(normalize_phone(post('phone')), [null, ''], true)) {   // Medula'da telefon yok: haber verebilmek için zorunlu
            $errors[] = 'Telefon numarası geçersiz. Örnek: 0532 111 22 33';
        }
        $birth = post('birth_year');
        if ($birth !== '' && (!ctype_digit($birth) || (int) $birth < 1900 || (int) $birth > (int) date('Y'))) {
            $errors[] = 'Doğum yılı geçersiz.';
        }
    }

    // --- Reçete (reçete ekranıyla aynı doğrulama; cam tipi siparişte seçilir) ---
    $_POST['lens_type'] = '';
    $_POST['near_lens_type'] = '';
    [$rxData, $nearData, $rxErrors] = rx_from_post();
    $errors = array_merge($errors, $rxErrors);
    $erecete = tezgah_recete_no(post('erecete'));
    if (post('erecete') !== '' && $erecete === '') {
        $errors[] = 'e-Reçete numarası geçersiz.';
    }

    if ($errors) {
        remember_input();
        foreach (array_unique($errors) as $err) {
            flash($err, 'error');
        }
        redirect($geriUrl);
    }

    $sonuc = transaction(function () use ($customer, $me, $rxData, $nearData, $erecete, $gelenId) {
        $yeniMusteri = false;
        if ($customer) {
            $cid = (int) $customer['id'];
        } else {
            $first = tr_title(post('first_name'));
            $last = tr_title(post('last_name'));
            $phone = (string) normalize_phone(post('phone'));
            // Aynı ad soyad + telefon zaten kayıtlıysa yeni müşteri açma.
            $cid = (int) scalar('SELECT id FROM customers WHERE phone = ? AND first_name = ? AND last_name = ? LIMIT 1', [$phone, $first, $last]);
            if (!$cid) {
                $cid = insert('customers', [
                    'first_name' => $first,
                    'last_name'  => $last,
                    'phone'      => $phone,
                    'birth_year' => post('birth_year') !== '' ? (int) post('birth_year') : null,
                    'notes'      => $gelenId > 0 ? 'SGK e-reçetesinden oluşturuldu.' : null,
                    'created_by' => (int) $me['id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                audit('customer_create', 'customer', $cid, ['ad' => "$first $last", 'kaynak' => 'tezgâh']);
                $yeniMusteri = true;
            }
        }
        $receteId = recete_bekleyen_kaydet($cid, $rxData, $nearData, $erecete, $gelenId, (int) $me['id']);
        audit('recete_bekleyen', 'customer', $cid, ['e-reçete' => $erecete ?: '—', 'sağ' => rx_line($rxData, 'right'), 'sol' => rx_line($rxData, 'left'), 'kaynak' => $gelenId > 0 ? 'Medula' : 'elle']);
        return ['customer' => $cid, 'recete' => $receteId, 'yeni_musteri' => $yeniMusteri];
    });

    if ($erecete !== '' && ozellik_acik('sgk_hak')) {
        $hd = sgk_hak_durumu((int) $sonuc['customer'], 0);
        if ($hd['durum'] !== 'var') {
            flash('SGK hakkı: ' . $hd['mesaj'], $hd['durum'] === 'yok' ? 'warn' : 'info');
        }
    }
    $sonra = post('sonra') === 'siparis' ? 'siparis' : 'teklif';
    flash(($sonuc['yeni_musteri'] ? 'Müşteri kaydı açıldı, reçete' : 'Reçete') . ' müşteriye kaydedildi. '
        . ($sonra === 'siparis' ? 'Cam tipini ve stok durumunu seçip siparişi kaydedin.' : 'Şimdi teklifi hazırlayın.'));
    redirect($sonrakiAdim((int) $sonuc['customer'], (int) $sonuc['recete'], $sonra));
}

/* ---------- Görünüm ---------- */
$form = null;        // ön değerler (rx_from_post alan adlarıyla)
$eslesenler = [];
$cozum = null;
if ($gelenId > 0) {
    $gelen = row('SELECT raw_text FROM sgk_incoming WHERE id = ? AND user_id = ?', [$gelenId, (int) $me['id']]);
    if (!$gelen) {
        flash('Aktarılan reçete bulunamadı.', 'error');
        redirect('tezgah.php');
    }
    $oncekiKayit($gelenId);
    $cozum = sgk_parse((string) $gelen['raw_text']);
    $form = tezgah_on_dolum($cozum);
    $eslesenler = sgk_musteri_bul((string) $cozum['ad'], (string) $cozum['soyad']);
} elseif ($elle) {
    $form = tezgah_on_dolum(['sag' => [], 'sol' => [], 'yakin_sag' => [], 'yakin_sol' => []]);
}

$bekleyenler = $form === null ? tezgah_bekleyenler((int) $me['id']) : [];
$o = static fn(string $k): string => old($k, (string) ($form[$k] ?? ''));

page_start('Yeni müşteri · reçete', 'tezgah');
page_header('Yeni müşteri · reçete', 'Reçete → müşteri → teklif → sipariş → döküm, tek yolda', '', '', 'Satış');
echo akis_cubugu($form === null ? 1 : 2);

if ($form === null): ?>
<div class="split">
  <div class="split-main">
    <section class="card tezgah-sorgu" data-tezgah>
      <div class="card-head"><h2><span class="step">1</span> Müşterinin reçetesi</h2></div>
      <form class="tezgah-no" data-tezgah-sorgu autocomplete="off">
        <label class="field">
          <span>e-Reçete numarası</span>
          <input name="no" required maxlength="20" inputmode="text" autocapitalize="characters" placeholder="ör. 1A2B3C4" data-tezgah-no autofocus>
        </label>
        <button class="btn btn-primary btn-lg"><?= icon('search') ?> Medula'da aç</button>
      </form>
      <p class="hint" data-tezgah-durum>OptiFlow Pro numarayı Medula'nın reçete sorgu ekranına yazar. Reçeteyi Medula'da kontrol edin, sonra üstteki <b>“Reçeteyi aktar”</b>a basın; müşteri kaydı burada açılır.</p>
      <div class="form-actions">
        <button type="button" class="btn" data-tezgah-aktar hidden><?= icon('download') ?> Medula'daki reçeteyi aktar</button>
        <a class="btn btn-ghost" href="tezgah.php?elle=1">Reçeteyi elle gir</a>
      </div>
    </section>
  </div>
  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2>Aktarılıp kaydedilmeyenler</h2></div>
      <?php if (!$bekleyenler): ?>
        <p class="muted small">Medula'dan aktarılan her reçete müşteriye kaydedilene kadar burada bekler.</p>
      <?php else: ?>
        <ul class="list">
          <?php foreach ($bekleyenler as $b): ?>
            <li><a href="tezgah.php?gelen=<?= (int) $b['id'] ?>"><b><?= e($b['hasta'] ?: 'Hasta adı okunamadı') ?></b></a>
              <small class="block muted"><?= e(trim(($b['erecete'] !== '' ? 'e-Reçete ' . $b['erecete'] . ' · ' : '') . date_tr($b['zaman']))) ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
<?php else:
  $camYok = $cozum !== null && $form['right_sph'] === '' && $form['left_sph'] === '';
  $ilkMusteri = $eslesenler ? (string) $eslesenler[0]['id'] : 'yeni';
  $secili = old('musteri', $ilkMusteri);
  $nearGoster = $form['yakin'] || in_array($o('lens_design'), ['ayri_uzak_yakin', 'tek_odak_yakin'], true);
  ?>
<?php if ($cozum !== null): ?>
  <div class="alert alert-warn"><span>Değerleri <b>Medula ekranıyla karşılaştırın</b>; kesin kaynak Medula'dır.<?= $camYok ? ' <b>Cam değerleri okunamadı</b> — aşağıya elle yazın.' : '' ?></span></div>
<?php endif; ?>
<form method="post" class="form-layout tezgah-form" data-guard>
  <?= csrf_field() ?>
  <input type="hidden" name="gelen_id" value="<?= (int) $gelenId ?>">

  <section class="card">
    <div class="card-head"><h2>Müşteri</h2>
      <?php if ($cozum !== null && $cozum['hasta'] !== ''): ?><small class="muted">Medula: <b><?= e($cozum['hasta']) ?></b></small><?php endif; ?>
    </div>
    <div class="pick-list">
      <?php foreach ($eslesenler as $c): ?>
        <label class="pick">
          <input type="radio" name="musteri" value="<?= (int) $c['id'] ?>" <?= $secili === (string) $c['id'] ? 'checked' : '' ?>>
          <span><b><?= e($c['first_name'] . ' ' . $c['last_name']) ?></b>
            <small class="block muted"><?= e(phone_display((string) $c['phone']) ?: 'Telefon yok') ?><?= $c['birth_year'] ? ' · ' . (int) $c['birth_year'] : '' ?> · <?= $c['puan'] >= 4 ? 'adı birebir uyuyor' : 'benzer isim' ?></small></span>
        </label>
      <?php endforeach; ?>
      <label class="pick">
        <input type="radio" name="musteri" value="yeni" <?= $secili === 'yeni' ? 'checked' : '' ?>>
        <span><b>Yeni müşteri</b><small class="block muted"><?= $eslesenler ? 'Listedekilerden biri değilse' : 'Bu isimde kayıt yok' ?></small></span>
      </label>
      <div class="grid cols-4 pick-form">
        <label class="field"><span>Ad *</span><input name="first_name" value="<?= e(old('first_name', $form['ad'])) ?>" autocomplete="off"></label>
        <label class="field"><span>Soyad *</span><input name="last_name" value="<?= e(old('last_name', $form['soyad'])) ?>" autocomplete="off"></label>
        <label class="field"><span>Telefon *</span><input name="phone" type="tel" inputmode="tel" value="<?= e(old('phone')) ?>" placeholder="05XX XXX XX XX"></label>
        <label class="field"><span>Doğum yılı<?= $form['dogum'] !== '' ? ' (≈)' : '' ?></span><input name="birth_year" inputmode="numeric" maxlength="4" value="<?= e(old('birth_year', $form['dogum'])) ?>"></label>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2>Reçete</h2></div>
    <div class="grid cols-4">
      <label class="field"><span>e-Reçete no</span><input name="erecete" value="<?= e($o('erecete')) ?>" autocapitalize="characters"></label>
      <label class="field"><span>Reçete tarihi</span><input type="date" name="prescription_date" value="<?= e($o('prescription_date')) ?>" max="<?= date('Y-m-d') ?>"></label>
      <label class="field span-2"><span>Kullanım şekli</span><select name="lens_design"><?= select_options(lens_designs(), $o('lens_design')) ?></select></label>
    </div>
    <div class="table-wrap">
      <table class="rx-input">
        <thead><tr><th></th><th>SPH</th><th>CYL</th><th>AKS</th><th>ADD</th></tr></thead>
        <tbody>
          <?php foreach (['right' => ['R', 'Sağ'], 'left' => ['L', 'Sol']] as $t => [$kod, $ad]): ?>
            <tr>
              <th><span class="eye-tag"><?= $kod ?></span><?= $ad ?></th>
              <td data-label="SPH"><input name="<?= $t ?>_sph" value="<?= e($o($t . '_sph')) ?>" inputmode="decimal"></td>
              <td data-label="CYL"><input name="<?= $t ?>_cyl" value="<?= e($o($t . '_cyl')) ?>" inputmode="decimal"></td>
              <td data-label="AKS"><input name="<?= $t ?>_axis" value="<?= e($o($t . '_axis')) ?>" inputmode="numeric"></td>
              <td data-label="ADD"><input name="<?= $t ?>_add" value="<?= e($o($t . '_add')) ?>" inputmode="decimal"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($nearGoster): ?>
      <div class="grid cols-4">
        <label class="field"><span>Yakın SPH · sağ</span><input name="near_right_sph" value="<?= e($o('near_right_sph')) ?>" inputmode="decimal"></label>
        <label class="field"><span>Yakın SPH · sol</span><input name="near_left_sph" value="<?= e($o('near_left_sph')) ?>" inputmode="decimal"></label>
      </div>
    <?php endif; ?>
    <div class="grid cols-4">
      <label class="field"><span>PD (toplam)</span><input name="pd" value="<?= e($o('pd')) ?>" inputmode="decimal"></label>
      <label class="field"><span>PD sağ / sol</span><span class="ikili"><input name="right_pd" value="<?= e(old('right_pd')) ?>" inputmode="decimal" aria-label="Sağ PD"><input name="left_pd" value="<?= e(old('left_pd')) ?>" inputmode="decimal" aria-label="Sol PD"></span></label>
      <label class="field"><span>Yükseklik sağ / sol</span><span class="ikili"><input name="right_height" value="<?= e(old('right_height')) ?>" inputmode="decimal" aria-label="Sağ yükseklik"><input name="left_height" value="<?= e(old('left_height')) ?>" inputmode="decimal" aria-label="Sol yükseklik"></span></label>
      <label class="field"><span>Doktor</span><input name="doctor" value="<?= e($o('doctor')) ?>"></label>
    </div>
  </section>

  <div class="form-actions sticky-actions">
    <a class="btn btn-ghost" href="tezgah.php">Vazgeç</a>
    <button class="btn" name="sonra" value="siparis">Teklifsiz siparişe geç</button>
    <button class="btn btn-primary btn-lg" name="sonra" value="teklif">Kaydet, teklif hazırla</button>
  </div>
</form>
<?php endif; ?>
<?php page_end(['tezgah.js']);
