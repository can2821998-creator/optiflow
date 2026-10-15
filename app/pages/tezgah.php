<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_login();
ozellik_gereksin('kagitsiz');

/* ==========================================================================
   4.33.0 — Tezgâh: reçete → Medula → tek ekran kayıt → fiş (app/tezgah.php).
   1) e-Reçete no yazılır; OptiFlow Pro Medula'da sorguyu doldurur (assets/tezgah.js).
   2) Personel Medula'da kontrol edip "Reçeteyi aktar"a basar → bu sayfa ?gelen=ID ile açılır.
   3) Müşteri, cam + stok, çerçeve, tutar + kapora tek formda → kaydet → fiş + müşteri nüshası.
   Pro yoksa ?elle=1: aynı form boş açılır.
   ========================================================================== */

$gelenId = is_post() ? post_int('gelen_id') : query_int('gelen');
$elle = !is_post() && query('elle') === '1';
$geriUrl = $gelenId > 0 ? 'tezgah.php?gelen=' . $gelenId : 'tezgah.php?elle=1';

if (is_post()) {
    csrf_check();
    $errors = [];

    if ($gelenId > 0) {
        $kullanildi = scalar('SELECT used_order_id FROM sgk_incoming WHERE id = ? AND user_id = ?', [$gelenId, (int) $me['id']]);
        if ($kullanildi) {
            flash('Bu reçete zaten kaydedildi.', 'warn');
            redirect('order.php?id=' . (int) $kullanildi);
        }
    }

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

    // --- Reçete + cam (reçete ekranıyla aynı doğrulama) ---
    if (post('lens_type') === '') {
        $errors[] = 'Cam tipini seçin.';
    }
    if (post('near_lens_type') === '') {
        $_POST['near_lens_type'] = post('lens_type');
    }
    [$rxData, $nearData, $rxErrors] = rx_from_post();
    $errors = array_merge($errors, $rxErrors);
    $stock = [];
    foreach (['uzak', 'yakin'] as $g) {
        $v = post('stock_' . $g, 'stokta_var');
        if (!in_array($v, ['stokta_var', 'stokta_yok'], true)) {
            $errors[] = 'Stok durumu geçersiz.';
        }
        $stock[$g] = $v;
    }
    $erecete = tezgah_recete_no(post('erecete'));
    if (post('erecete') !== '' && $erecete === '') {
        $errors[] = 'e-Reçete numarası geçersiz.';
    }

    // --- Tutar ---
    $amount = can_see_amounts() ? parse_money(post('total_amount')) : 0.0;
    if ($amount === null || $amount < 0) {
        $errors[] = 'Sipariş tutarı geçersiz. Örnek: 4.250,00';
    }
    $deposit = can_take_payments() ? parse_money(post('deposit')) : 0.0;
    if ($deposit === null || $deposit < 0) {
        $errors[] = 'Kapora tutarı geçersiz.';
    } elseif ($amount !== null && $amount > 0 && $deposit > $amount + 0.001) {
        $errors[] = 'Kapora sipariş tutarından büyük olamaz.';
    }
    $method = post('deposit_method', 'nakit');
    if (!isset(payment_methods()[$method])) {
        $method = 'nakit';
    }
    $promised = post('promised_date');
    if ($promised !== '' && !valid_date($promised)) {
        $errors[] = 'Teslim tarihi geçersiz.';
    }

    if ($errors) {
        remember_input();
        foreach (array_unique($errors) as $err) {
            flash($err, 'error');
        }
        redirect($geriUrl);
    }

    $sonuc = transaction(function () use ($customer, $me, $rxData, $nearData, $stock, $erecete, $amount, $deposit, $method, $promised, $gelenId) {
        $simdi = date('Y-m-d H:i:s');
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
                    'created_at' => $simdi,
                ]);
                audit('customer_create', 'customer', $cid, ['ad' => "$first $last", 'kaynak' => 'tezgâh']);
                $yeniMusteri = true;
            }
        }
        $c = find_customer($cid);

        $orderId = insert('orders', [
            'customer_id'      => $cid,
            'first_name'       => $c['first_name'],
            'last_name'        => $c['last_name'],
            'phone'            => (string) $c['phone'],
            'total_amount'     => $amount,
            'status'           => 'siparis_verildi',
            'order_stage'      => 'siparis_verildi',
            'transaction_type' => 'gozluk',
            'lens_type'        => $rxData['lens_type'],
            'frame_info'       => mb_substr(post('frame_info'), 0, 255),
            'notes'            => mb_substr(post('notes'), 0, 2000) ?: null,
            'promised_date'    => $promised ?: null,
            'sales_person'     => $me['full_name'],
            'created_by'       => (int) $me['id'],
            'created_at'       => $simdi,
            'updated_at'       => $simdi,
        ]);
        audit('order_create', 'order', $orderId, ['müşteri' => $c['first_name'] . ' ' . $c['last_name'], 'tutar' => $amount, 'kaynak' => 'tezgâh']);

        $rxId = insert('prescription_records', $rxData + [
            'order_id'         => $orderId,
            'customer_id'      => $cid,
            'sgk_rapor_no'     => $erecete !== '' ? $erecete : null,
            'sgk_rapor_tarihi' => $erecete !== '' ? $rxData['prescription_date'] : null,
            'created_by'       => (int) $me['id'],
            'created_at'       => $simdi,
        ]);
        if ($nearData) {
            insert('near_prescription_details', $nearData + ['prescription_id' => $rxId]);
        }
        sync_lens_items($rxId, $rxData, $nearData, $stock);
        audit('rx_create', 'order', $orderId, ['reçete' => $rxId, 'sağ' => rx_line($rxData, 'right'), 'sol' => rx_line($rxData, 'left'), 'cam' => $rxData['lens_type']]);

        if ($erecete !== '') {
            q('UPDATE orders SET sgk_erecete = ? WHERE id = ?', [$erecete, $orderId]);   // 4.12.0 mutabakat
        }
        $stokCerceve = post_int('frame_item_id');
        if ($stokCerceve > 0) {
            order_set_frame_item($orderId, $stokCerceve, null);
            if (trim(post('frame_info')) === '') {
                $sc = row('SELECT * FROM frame_items WHERE id = ?', [$stokCerceve]);
                if ($sc) {
                    q('UPDATE orders SET frame_info = ? WHERE id = ?', [mb_substr(frame_item_label($sc), 0, 255), $orderId]);
                }
            }
        }
        if ($deposit > 0) {
            $pid = insert('payments', ['order_id' => $orderId, 'amount' => $deposit, 'method' => $method, 'note' => 'Kapora', 'created_by' => (int) $me['id'], 'created_at' => $simdi]);
            audit('payment_create', 'order', $orderId, ['tutar' => $deposit, 'yöntem' => $method, 'ödeme' => $pid]);
        }
        if ($gelenId > 0) {
            q('UPDATE sgk_incoming SET used_order_id = ?, used_at = ? WHERE id = ? AND user_id = ?', [$orderId, $simdi, $gelenId, (int) $me['id']]);
            audit('sgk_aktar', 'order', $orderId, ['e-reçete' => $erecete ?: '—', 'kaynak' => 'tezgâh']);
        }
        $sgk = $erecete !== '' ? sgk_katki_uygula($orderId, $cid, $rxData) : ['tutar' => 0.0, 'uyari' => ''];
        return ['order' => $orderId, 'customer' => $cid, 'yeni_musteri' => $yeniMusteri, 'sgk' => $sgk];
    });

    if (!empty($sonuc['sgk']['uyari'])) {
        flash($sonuc['sgk']['uyari'], 'warn');
    }
    if ($erecete !== '' && ozellik_acik('sgk_hak')) {
        $hd = sgk_hak_durumu((int) $sonuc['customer'], (int) $sonuc['order']);
        if ($hd['durum'] !== 'var') {
            flash('SGK hakkı: ' . $hd['mesaj'], $hd['durum'] === 'yok' ? 'warn' : 'info');
        }
    }
    $eksik = (int) scalar("SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE r.order_id = ? AND i.stock_status = 'stokta_yok'", [(int) $sonuc['order']]);
    flash('Sipariş ' . order_no((int) $sonuc['order']) . ' kaydedildi.' . ($eksik ? " $eksik cam gün sonu depo listesine eklendi." : '')
        . (($sonuc['sgk']['tutar'] ?? 0) > 0 ? ' SGK katkısı tahmini ' . money($sonuc['sgk']['tutar']) . '.' : ''));
    redirect('print.php?type=order&id=' . (int) $sonuc['order'] . '&tezgah=1');
}

/* ---------- Görünüm ---------- */
$form = null;        // ön değerler (rx_from_post alan adlarıyla)
$eslesenler = [];
$cozum = null;
if ($gelenId > 0) {
    $gelen = row('SELECT raw_text, used_order_id FROM sgk_incoming WHERE id = ? AND user_id = ?', [$gelenId, (int) $me['id']]);
    if (!$gelen) {
        flash('Aktarılan reçete bulunamadı.', 'error');
        redirect('tezgah.php');
    }
    if ($gelen['used_order_id']) {
        flash('Bu reçete zaten kaydedildi.', 'info');
        redirect('order.php?id=' . (int) $gelen['used_order_id']);
    }
    $cozum = sgk_parse((string) $gelen['raw_text']);
    $form = tezgah_on_dolum($cozum);
    $eslesenler = sgk_musteri_bul((string) $cozum['ad'], (string) $cozum['soyad']);
} elseif ($elle) {
    $form = tezgah_on_dolum(['sag' => [], 'sol' => [], 'yakin_sag' => [], 'yakin_sol' => []]);
}

$bekleyenler = $form === null ? tezgah_bekleyenler((int) $me['id']) : [];
$o = static fn(string $k): string => old($k, (string) ($form[$k] ?? ''));

page_start('Tezgâh', 'tezgah');
page_header('Tezgâh', 'Reçete → Medula → kayıt → fiş, tek akışta', '', '', 'Kâğıtsız tezgâh');

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
      <p class="hint" data-tezgah-durum>OptiFlow Pro numarayı Medula'nın reçete sorgu ekranına yazar. Reçeteyi Medula'da kontrol edin, sonra üstteki <b>“Reçeteyi aktar”</b>a basın; kayıt formu burada açılır.</p>
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
        <p class="muted small">Medula'dan aktarılan her reçete kaydedilene kadar burada bekler.</p>
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
    <div class="card-head"><h2><span class="step">1</span> Müşteri</h2>
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
    <div class="card-head"><h2><span class="step">2</span> Reçete</h2></div>
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

  <section class="card">
    <div class="card-head"><h2><span class="step">3</span> Cam ve çerçeve</h2></div>
    <div class="grid cols-4">
      <label class="field span-2"><span>Cam tipi *</span>
        <select name="lens_type" required><option value="">Seçin</option><?= select_options(lens_type_options(), old('lens_type'), false) ?></select>
      </label>
      <label class="field"><span>Uzak camlar</span><select name="stock_uzak"><?= select_options(['stokta_var' => 'Stokta', 'stokta_yok' => 'Eksik — depodan'], old('stock_uzak', 'stokta_var')) ?></select></label>
      <?php if ($nearGoster): ?>
        <label class="field"><span>Yakın camlar</span><select name="stock_yakin"><?= select_options(['stokta_var' => 'Stokta', 'stokta_yok' => 'Eksik — depodan'], old('stock_yakin', 'stokta_var')) ?></select></label>
      <?php endif; ?>
      <label class="field span-2"><span>Stoktaki çerçeve <small class="muted">(seçince vitrinden düşer)</small></span>
        <select name="frame_item_id"><option value="">— müşterinin kendi çerçevesi / stok dışı —</option><?= select_options(frame_stock_options(), old('frame_item_id')) ?></select>
      </label>
      <label class="field span-2"><span>Çerçeve açıklaması</span><input name="frame_info" value="<?= e(old('frame_info')) ?>" placeholder="ör. Ray-Ban 5154 52□21 ya da “kendi çerçevesi”"></label>
    </div>
    <p class="hint">Eksik işaretlenen camlar gün sonu “Depoya gidecek eksik camlar” listesine düşer.</p>
  </section>

  <section class="card">
    <div class="card-head"><h2><span class="step">4</span> Ödeme ve teslim</h2></div>
    <div class="grid cols-4">
      <?php if (can_see_amounts()): ?>
        <label class="field"><span>Tutar</span><input name="total_amount" inputmode="decimal" value="<?= e(old('total_amount')) ?>" placeholder="0,00" data-money></label>
      <?php endif; ?>
      <?php if (can_take_payments()): ?>
        <label class="field"><span>Kapora</span><input name="deposit" inputmode="decimal" value="<?= e(old('deposit')) ?>" placeholder="0,00" data-money></label>
        <label class="field"><span>Ödeme yöntemi</span><select name="deposit_method"><?= select_options(payment_methods(), old('deposit_method', 'nakit')) ?></select></label>
      <?php endif; ?>
      <label class="field"><span>Teslim sözü</span><input type="date" name="promised_date" value="<?= e(old('promised_date', tezgah_teslim_tarihi())) ?>"></label>
      <label class="field span-all"><span>Not</span><textarea name="notes" rows="2" placeholder="Atölye için notlar"><?= e(old('notes')) ?></textarea></label>
    </div>
    <?php if ($form['erecete'] !== '' || $cozum !== null): ?><p class="hint">SGK katkı payı kayıttan sonra reçeteye göre tahmini işlenir; kalan tutar fişte görünür.</p><?php endif; ?>
  </section>

  <div class="form-actions sticky-actions">
    <a class="btn btn-ghost" href="tezgah.php">Vazgeç</a>
    <button class="btn btn-primary btn-lg"><?= icon('print') ?> Kaydet ve fişi yazdır</button>
  </div>
</form>
<?php endif; ?>
<?php page_end(['tezgah.js']);
