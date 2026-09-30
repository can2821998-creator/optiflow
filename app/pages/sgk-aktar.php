<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   SGK / Medula Optik → Atölye aktarımı
   Köprü uç noktası (eklentiden gelen POST) oturum gerektirmez; kullanıcıya
   özel köprü anahtarıyla doğrulanır. Mağaza, köprü adresindeki &m= ile
   seçilir (app/merkez.php kopru_tenant_bagla). Sayfanın kendisi oturum ister.
   OptiFlow Masaüstü eklenti kullanmaz: masaustu.php'ye oturumla gönderir ve
   bu sayfayı ?gelen=ID ile açar.
   ========================================================================== */

/* ---------- Köprü uç noktası: mağaza bilgisayarındaki eklenti buraya yazar ---------- */
if (query('action') === 'kopru') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'hata' => 'Yalnızca POST']);
        exit;
    }

    $ham = file_get_contents('php://input') ?: '';
    $in = json_decode($ham, true);
    $in = is_array($in) ? $in : [];

    $token = (string) ($in['token'] ?? '');
    $metin = (string) ($in['metin'] ?? '');
    $baslik = mb_substr(trim((string) ($in['baslik'] ?? '')), 0, 160);

    if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'hata' => 'Köprü anahtarı geçersiz']);
        exit;
    }
    if (strlen($metin) < 10 || strlen($metin) > 200000) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'hata' => 'Metin boş ya da çok uzun']);
        exit;
    }

    $uid = (int) scalar('SELECT id FROM user_accounts WHERE bridge_token = ? AND is_active = 1', [$token]);
    if ($uid <= 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'hata' => 'Köprü anahtarı tanınmadı']);
        exit;
    }

    // Saatlik kayıt sınırı — kazara döngüye karşı
    $sonSaat = (int) scalar('SELECT COUNT(*) FROM sgk_incoming WHERE user_id = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$uid]);
    if ($sonSaat > 120) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'hata' => 'Çok fazla aktarım, biraz bekleyin']);
        exit;
    }

    $cozum = sgk_parse($metin);
    insert('sgk_incoming', [
        'user_id'    => $uid,
        'kaynak'     => 'kopru',
        'baslik'     => $baslik ?: null,
        'raw_text'   => $metin,
        'parsed'     => json_encode($cozum, JSON_UNESCAPED_UNICODE),
        'created_at' => date('Y-m-d H:i:s'),
    ] + sgk_gelen_ek_alanlar($cozum));

    echo json_encode([
        'ok' => true,
        'bulunan' => $cozum['bulunan'],
        'ozet' => ($cozum['hasta'] !== '' ? $cozum['hasta'] . ' · ' : '')
            . 'Sağ ' . ($cozum['sag']['sph'] ?: '—') . ' / Sol ' . ($cozum['sol']['sph'] ?: '—'),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/* ---------- Normal sayfa ---------- */
$me = require_login();
/* 4.11.0 — Medula aktarımı OptiFlow Pro özelliğidir (app/paket.php):
     'acik'             → Pro paket + masaüstü: masaüstü kartı
     'masaustu_gerekli' → Pro paket, tarayıcı: uygulamayı açın (+ geçiş dönemi eklentisi)
     'paket_gerekli'    → Lite: kilitli tanıtım kartı
   Elle yapıştırıp çözümleme her pakette çalışır. */
$kopruDurum = pro_ozellik_durumu('sgk_kopru');
// Köprü anahtarı yalnızca eklentinin çalıştığı durumda (Pro, tarayıcı) üretilir/gösterilir.
$token = $kopruDurum === 'masaustu_gerekli' ? sgk_bridge_token((int) $me['id']) : '';

$cozum = null;
$hamMetin = '';
$gelenId = 0;
$eslesenler = [];
$hedefSiparis = query_int('order');
$masaustu = $kopruDurum === 'acik';   // masaüstü kartı ve düğmeleri yalnızca Pro + masaüstünde

/* Masaüstü uygulaması (ya da bağlantı) ?gelen=ID ile açtıysa: o kaydı doğrudan önizlemede göster.
   Yalnızca kullanıcının KENDİ kaydı açılır (POST 'coz' akışıyla aynı kural); salt okumadır. */
if (!is_post() && query_int('gelen') > 0) {
    $gelenId = query_int('gelen');
    $hamMetin = (string) scalar('SELECT raw_text FROM sgk_incoming WHERE id = ? AND user_id = ?', [$gelenId, (int) $me['id']]);
    if (trim($hamMetin) === '') {
        flash('Aktarılan kayıt bulunamadı.', 'error');
        redirect('sgk-aktar.php');
    }
    $cozum = sgk_parse($hamMetin);
    $eslesenler = sgk_musteri_bul((string) $cozum['ad'], (string) $cozum['soyad']);
}

/** Reçete için yeni sipariş açar (SGK aktarımı kaynaklı). */
$siparisAc = static function (array $c) use ($me): int {
    $simdi = date('Y-m-d H:i:s');
    $id = insert('orders', [
        'customer_id'   => (int) $c['id'],
        'first_name'    => $c['first_name'],
        'last_name'     => $c['last_name'],
        'phone'         => (string) ($c['phone'] ?? ''),
        'total_amount'  => 0,
        'status'        => 'siparis_verildi',
        'order_stage'   => 'siparis_verildi',
        'transaction_type' => 'gozluk',
        'stock_status'  => 'stokta_var',
        'notes'         => 'SGK e-reçetesinden açıldı.',
        'sales_person'  => $me['full_name'],
        'created_by'    => (int) $me['id'],
        'created_at'    => $simdi,
        'updated_at'    => $simdi,
    ]);
    audit('order_create', 'order', $id, ['müşteri' => $c['first_name'] . ' ' . $c['last_name'], 'kaynak' => 'SGK reçete']);
    return $id;
};

if (is_post()) {
    csrf_check();
    $eylem = post('eylem');

    if ($eylem === 'anahtar-yenile') {
        sgk_bridge_yenile((int) $me['id']);
        flash('Köprü anahtarı yenilendi. Eklentideki anahtarı da güncelleyin.');
        redirect('sgk-aktar.php');
    }

    if ($eylem === 'sil') {
        q('DELETE FROM sgk_incoming WHERE id = ? AND user_id = ?', [post_int('id'), (int) $me['id']]);
        redirect('sgk-aktar.php');
    }

    if ($eylem === 'coz') {
        $hamMetin = (string) ($_POST['metin'] ?? '');
        $gelenId = post_int('gelen_id');
        if (trim($hamMetin) === '' && $gelenId > 0) {
            $hamMetin = (string) scalar('SELECT raw_text FROM sgk_incoming WHERE id = ? AND user_id = ?', [$gelenId, (int) $me['id']]);
        }
        if (trim($hamMetin) === '') {
            flash('Çözümlenecek metin yok.', 'error');
            redirect('sgk-aktar.php');
        }
        $cozum = sgk_parse($hamMetin);
        $eslesenler = sgk_musteri_bul((string) $cozum['ad'], (string) $cozum['soyad']);
    }

    if ($eylem === 'uygula') {
        $hedef = post('hedef');

        /* Doğrulama hataları DomainException ile bildirilir: işlem geri alınır,
           yarım müşteri/sipariş kaydı kalmaz. */
        try {
            $sonuc = transaction(function () use ($hedef, $me, $siparisAc) {
                /* --- 1) Hangi siparişe yazacağız? --- */
                $orderId = 0;
                $yeniMusteri = 0;
                $yeniSiparis = false;

                if (str_starts_with($hedef, 'siparis:')) {
                    $orderId = (int) substr($hedef, 8);
                } elseif ($hedef === 'liste') {
                    $orderId = post_int('order_id');
                } elseif (str_starts_with($hedef, 'musteri:')) {
                    $c = find_customer((int) substr($hedef, 8));
                    if (!$c) {
                        throw new DomainException('Müşteri bulunamadı.');
                    }
                    $orderId = $siparisAc($c);
                    $yeniSiparis = true;
                } elseif ($hedef === 'yeni') {
                    $ad = tr_title(post('yeni_ad'));
                    $soyad = tr_title(post('yeni_soyad'));
                    if ($ad === '' || $soyad === '') {
                        throw new DomainException('Yeni müşteri için ad ve soyad gerekli.');
                    }
                    $tel = normalize_phone(post('yeni_telefon'));
                    if ($tel === null) {
                        throw new DomainException('Telefon numarası geçersiz. Örnek: 0532 111 22 33');
                    }
                    $dogum = post('yeni_dogum');
                    $dogumYili = ($dogum !== '' && ctype_digit($dogum) && (int) $dogum >= 1900 && (int) $dogum <= (int) date('Y')) ? (int) $dogum : null;

                    // Aynı ad-soyad + telefon zaten varsa yeni kayıt açma.
                    $cid = (int) scalar('SELECT id FROM customers WHERE first_name = ? AND last_name = ? AND phone = ? LIMIT 1', [$ad, $soyad, (string) $tel]);
                    if (!$cid) {
                        $cid = insert('customers', [
                            'first_name' => $ad,
                            'last_name'  => $soyad,
                            'phone'      => (string) $tel,
                            'birth_year' => $dogumYili,
                            'notes'      => 'SGK e-reçetesinden oluşturuldu.',
                            'created_by' => (int) $me['id'],
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                        audit('customer_create', 'customer', $cid, ['ad' => "$ad $soyad", 'kaynak' => 'SGK reçete']);
                        $yeniMusteri = $cid;
                    }
                    $c = find_customer($cid);
                    $orderId = $siparisAc($c);
                    $yeniSiparis = true;
                }

                $order = $orderId > 0 ? row('SELECT id, customer_id, order_stage FROM orders WHERE id = ?', [$orderId]) : null;
                if (!$order) {
                    throw new DomainException('Reçetenin yazılacağı sipariş seçilmedi.');
                }

                /* --- 2) Reçete değerleri --- */
                $alan = static fn(string $k): string => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, 16);
                $bos = static fn(string $s): ?string => $s !== '' ? $s : null;

                $design = post('lens_design', 'tek_odak_uzak');
                if (!isset(lens_designs()[$design])) {
                    $design = 'tek_odak_uzak';
                }

                $veri = [
                    'right_sph' => $bos($alan('sag_sph')), 'right_cyl' => $bos($alan('sag_cyl')),
                    'right_axis' => $bos($alan('sag_aks')), 'right_add' => $bos($alan('sag_add')),
                    'left_sph' => $bos($alan('sol_sph')), 'left_cyl' => $bos($alan('sol_cyl')),
                    'left_axis' => $bos($alan('sol_aks')), 'left_add' => $bos($alan('sol_add')),
                    'pd' => $bos($alan('pd')),
                    'lens_design' => $design,
                    'doctor' => $bos(mb_substr(trim(post('doktor')), 0, 120)),
                    'sgk_rapor_no' => $bos(mb_substr(trim(post('rapor_no')) ?: trim(post('erecete')), 0, 40)),
                    'sgk_rapor_tarihi' => $bos(sgk_tarih_db(post('rapor_tarihi')) ?: sgk_tarih_db(post('recete_tarihi'))),
                    'updated_by' => (int) $me['id'],
                ];

                $rxId = (int) scalar('SELECT id FROM prescription_records WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$orderId]);
                if ($rxId > 0) {
                    update('prescription_records', $veri, 'id = ?', [$rxId]);
                } else {
                    $rxId = insert('prescription_records', $veri + [
                        'order_id' => $orderId,
                        'customer_id' => $order['customer_id'],
                        'prescription_date' => sgk_tarih_db(post('recete_tarihi')) ?: date('Y-m-d'),
                        'created_by' => (int) $me['id'],
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                /* --- 3) Yakın gözlük değerleri (varsa) --- */
                $yakin = [
                    'right_sph' => $alan('yakin_sag_sph'), 'right_cyl' => $alan('yakin_sag_cyl'), 'right_axis' => $alan('yakin_sag_aks'),
                    'left_sph'  => $alan('yakin_sol_sph'), 'left_cyl'  => $alan('yakin_sol_cyl'), 'left_axis'  => $alan('yakin_sol_aks'),
                ];
                if (in_array($design, ['ayri_uzak_yakin', 'tek_odak_yakin'], true) && ($yakin['right_sph'] !== '' || $yakin['left_sph'] !== '')) {
                    $yakinVeri = $yakin + ['usage_type' => $design];
                    $var = (int) scalar('SELECT id FROM near_prescription_details WHERE prescription_id = ?', [$rxId]);
                    $var ? update('near_prescription_details', $yakinVeri, 'prescription_id = ?', [$rxId])
                         : insert('near_prescription_details', $yakinVeri + ['prescription_id' => $rxId]);
                }

                if (post_int('gelen_id') > 0) {
                    q('UPDATE sgk_incoming SET used_order_id = ?, used_at = NOW() WHERE id = ? AND user_id = ?',
                      [$orderId, post_int('gelen_id'), (int) $me['id']]);
                }
                $eReceteNo = strtoupper(mb_substr(trim(post('erecete')), 0, 20));
                if ($eReceteNo !== '') {
                    q('UPDATE orders SET sgk_erecete = ? WHERE id = ?', [$eReceteNo, $orderId]);   // 4.12.0 mutabakat
                }

                audit('sgk_aktar', 'order', $orderId, [
                    'e-reçete' => mb_substr(trim(post('erecete')), 0, 20) ?: '—',
                    'hasta'    => mb_substr(trim(post('hasta')), 0, 60) ?: '—',
                    'kaynak'   => post_int('gelen_id') > 0 ? 'köprü' : 'yapıştırma',
                ]);

                $sgk = sgk_katki_uygula($orderId, (int) $order['customer_id'], $veri);

                return ['order' => $orderId, 'rx' => $rxId, 'yeni_siparis' => $yeniSiparis, 'yeni_musteri' => $yeniMusteri, 'sgk' => $sgk];
            });
        } catch (DomainException $e) {
            flash($e->getMessage(), 'error');
            redirect('sgk-aktar.php');
        }

        if ($sonuc['sgk']['uyari']) {
            flash($sonuc['sgk']['uyari'], 'warn');
        }
        $sgkMesaj = $sonuc['sgk']['tutar'] > 0 ? ' SGK katkısı tahmini ' . money($sonuc['sgk']['tutar']) . ' olarak işlendi.' : '';
        $not = $sonuc['yeni_musteri'] ? 'Yeni müşteri ve sipariş açıldı. ' : ($sonuc['yeni_siparis'] ? 'Yeni sipariş açıldı. ' : '');
        flash($not . 'SGK değerleri ' . order_no($sonuc['order']) . ' siparişine yazıldı — cam tipini seçip kaydedin.' . $sgkMesaj);
        redirect('rx.php?id=' . $sonuc['rx']);
    }
}

/* Köprüden gelen, henüz kullanılmamış kayıtlar */
$gelenler = [];
try {
    $gelenler = rows(
        'SELECT id, baslik, kaynak, parsed, created_at, used_order_id
           FROM sgk_incoming WHERE user_id = ? ORDER BY id DESC LIMIT 15',
        [(int) $me['id']]
    );
} catch (Throwable $e) {
    $gelenler = [];
}

/* Aktarım için sipariş seçenekleri */
$siparisler = rows(
    "SELECT o.id, o.first_name, o.last_name, o.order_stage, o.created_at
       FROM orders o
      WHERE o.order_stage NOT IN ('teslim_edildi', 'iptal')
      ORDER BY o.created_at DESC LIMIT 60"
);

// &m= : eklentinin hangi mağazaya yazacağını bilmesi için (eklenti oturum çerezi taşımaz). 4.10.0
$koprUrl = rtrim(app_base_url(), '/') . '/sgk-aktar.php?action=kopru&m=' . (int) (tenant_oturum()['id'] ?? 0);

page_start('SGK reçete aktar', 'sgk');
page_header('SGK reçete aktar', 'Medula Optik ekranındaki reçeteyi siparişe taşıyın', '', '', 'Atölye');
?>

<div class="split">
  <div class="split-main">

    <section class="card">
      <div class="card-head">
        <h2><?= icon('download') ?> Reçeteyi yapıştır</h2>
        <small class="muted"><?= $masaustu ? 'Medula\'dan doğrudan aktarım için üstteki “Reçeteyi aktar” düğmesi daha doğru sonuç verir' : 'OptiFlow Pro ile reçete Medula\'dan tek tuşla, kutulardaki değerlerle birlikte gelir' ?></small>
      </div>
      <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="coz">
        <label class="field">
          <span>SGK ekranından kopyalanan metin</span>
          <textarea name="metin" rows="7" placeholder="Medula Optik ekranında Ctrl+A → Ctrl+C&#10;(Sferik / Silendirik / Aks kutu içinde olduğu için kopyalamayla gelmez; <?= $masaustu ? 'uygulamadaki “Reçeteyi aktar”' : 'OptiFlow Pro' ?> bunları da taşır.)"><?= e($hamMetin) ?></textarea>
        </label>
        <div class="form-actions">
          <button class="btn btn-primary"><?= icon('spark') ?> Çözümle</button>
        </div>
      </form>
    </section>

    <?php if ($cozum !== null):
      $yakinVar = $cozum['yakin_sag']['sph'] !== '' || $cozum['yakin_sol']['sph'] !== '';
      $design = sgk_lens_design($cozum);
      $dogumTahmin = $cozum['yas'] !== '' ? (string) ((int) date('Y') - (int) $cozum['yas']) : '';
      $camYok = $cozum['sag']['sph'] === '' && $cozum['sol']['sph'] === '';
      ?>
      <section class="card" id="onizleme">
        <div class="card-head">
          <h2>Çözümlenen reçete</h2>
          <span class="badge sm tone-<?= $cozum['bulunan'] >= 10 ? 'green' : ($cozum['bulunan'] >= 5 ? 'amber' : 'red') ?>">
            <?= (int) $cozum['bulunan'] ?> alan bulundu
          </span>
        </div>

        <?php if ($camYok): ?>
          <div class="alert alert-error" style="margin-bottom:18px">
            <span><b>Cam değerleri okunamadı.</b> SGK ekranında sferik/silendirik/aks değerleri
            kutuların içinde durur ve kopyalamayla gelmez. <?php if ($masaustu): ?>Uygulamadaki
            <b>“Reçeteyi aktar”</b> düğmesini kullanın<?php else: ?><a href="pro.php?ozellik=sgk_kopru">OptiFlow Pro</a> bunları
            otomatik aktarır<?php endif; ?> ya da değerleri aşağıya elle yazın.</span>
          </div>
        <?php endif; ?>

        <div class="alert alert-warn" style="margin-bottom:18px">
          <span>Değerleri <b>siparişe yazmadan önce SGK ekranıyla karşılaştırın.</b>
          Bu çözümleme yardımcıdır; kesin kaynak SGK'nın kendi ekranıdır.</span>
        </div>

        <form method="post" class="stack">
          <?= csrf_field() ?>
          <input type="hidden" name="eylem" value="uygula">
          <input type="hidden" name="gelen_id" value="<?= (int) $gelenId ?>">
          <input type="hidden" name="recete_tarihi" value="<?= e($cozum['recete_tarihi']) ?>">
          <input type="hidden" name="hasta" value="<?= e($cozum['hasta']) ?>">
          <input type="hidden" name="lens_design" value="<?= e($design) ?>">
          <?php foreach (['sag', 'sol'] as $g): foreach (['sph', 'cyl', 'aks'] as $k): ?>
            <input type="hidden" name="yakin_<?= $g ?>_<?= $k ?>" value="<?= e($cozum['yakin_' . $g][$k]) ?>">
          <?php endforeach; endforeach; ?>

          <h3 class="sub">Hasta ve reçete</h3>
          <ul class="kv">
            <li><span>Hasta</span><b><?= e($cozum['hasta'] ?: '—') ?></b></li>
            <li><span>T.C. Kimlik</span><b><?= e($cozum['tc'] ?: '—') ?></b></li>
            <li><span>E-Reçete no</span><b><?= e($cozum['erecete'] ?: '—') ?></b></li>
            <li><span>Reçete tarihi</span><b><?= e($cozum['recete_tarihi'] ?: '—') ?></b></li>
            <li><span>Tesis</span><b><?= e($cozum['tesis'] ?: '—') ?></b></li>
            <li><span>Doktor</span><b><?= e($cozum['doktor'] ?: '—') ?></b></li>
            <?php if ($cozum['teshis'] !== ''): ?><li><span>Teşhis</span><b><?= e($cozum['teshis']) ?></b></li><?php endif; ?>
            <?php if ($cozum['tercih'] !== ''): ?><li><span>Hasta tercihi</span><b><?= e($cozum['tercih']) ?> → <?= e(lens_designs()[$design]) ?></b></li><?php endif; ?>
          </ul>

          <h3 class="sub">Uzak gözlük</h3>
          <div class="table-wrap">
            <table class="rx-input">
              <thead><tr><th></th><th>SPH</th><th>CYL</th><th>AKS</th><th>ADD</th></tr></thead>
              <tbody>
                <tr>
                  <th><span class="eye-tag">R</span>Sağ</th>
                  <td data-label="SPH"><input name="sag_sph" value="<?= e($cozum['sag']['sph']) ?>" inputmode="decimal"></td>
                  <td data-label="CYL"><input name="sag_cyl" value="<?= e($cozum['sag']['cyl']) ?>" inputmode="decimal"></td>
                  <td data-label="AKS"><input name="sag_aks" value="<?= e($cozum['sag']['aks']) ?>" inputmode="numeric"></td>
                  <td data-label="ADD"><input name="sag_add" value="<?= e($cozum['sag']['add']) ?>" inputmode="decimal"></td>
                </tr>
                <tr>
                  <th><span class="eye-tag">L</span>Sol</th>
                  <td data-label="SPH"><input name="sol_sph" value="<?= e($cozum['sol']['sph']) ?>" inputmode="decimal"></td>
                  <td data-label="CYL"><input name="sol_cyl" value="<?= e($cozum['sol']['cyl']) ?>" inputmode="decimal"></td>
                  <td data-label="AKS"><input name="sol_aks" value="<?= e($cozum['sol']['aks']) ?>" inputmode="numeric"></td>
                  <td data-label="ADD"><input name="sol_add" value="<?= e($cozum['sol']['add']) ?>" inputmode="decimal"></td>
                </tr>
              </tbody>
            </table>
          </div>
          <?php if ($yakinVar): ?>
            <p class="hint">
              <b>Yakın gözlük:</b>
              Sağ <?= e($cozum['yakin_sag']['sph'] ?: '—') ?> · Sol <?= e($cozum['yakin_sol']['sph'] ?: '—') ?>
              — ADD değerleri bu farktan hesaplandı, yakın değerler de reçeteye yazılacak.
            </p>
          <?php endif; ?>

          <div class="grid cols-4">
            <label class="field"><span>PD</span><input name="pd" value="<?= e($cozum['pd']) ?>"></label>
            <label class="field"><span>E-Reçete no</span><input name="erecete" value="<?= e($cozum['erecete']) ?>"></label>
            <label class="field"><span>Rapor no</span><input name="rapor_no" value="<?= e($cozum['rapor_no']) ?>"></label>
            <label class="field"><span>Doktor</span><input name="doktor" value="<?= e($cozum['doktor']) ?>"></label>
          </div>
          <input type="hidden" name="rapor_tarihi" value="<?= e($cozum['rapor_tarihi']) ?>">

          <h3 class="sub">Nereye aktarılsın?</h3>
          <div class="pick-list">
            <?php $ilk = true; ?>
            <?php foreach ($eslesenler as $c): ?>
              <div class="pick-group">
                <div class="pick-group-head">
                  <b><?= e($c['first_name'] . ' ' . $c['last_name']) ?></b>
                  <?php if ($c['phone']): ?><small class="muted"><?= e($c['phone']) ?></small><?php endif; ?>
                  <span class="badge sm tone-<?= $c['puan'] >= 4 ? 'green' : 'amber' ?>"><?= $c['puan'] >= 4 ? 'adı birebir uyuyor' : 'benzer isim' ?></span>
                </div>
                <?php foreach ($c['siparisler'] as $s): ?>
                  <label class="pick">
                    <input type="radio" name="hedef" value="siparis:<?= (int) $s['id'] ?>" <?= $ilk ? 'checked' : '' ?> required>
                    <span>
                      <b>Açık sipariş <?= e(order_no((int) $s['id'])) ?></b>
                      <small class="block muted"><?= e(stage_label($s['order_stage'])) ?> · <?= e(date_tr($s['created_at'])) ?><?= $s['lens_type'] ? ' · ' . e($s['lens_type']) : '' ?> — reçete bu siparişe yazılsın</small>
                    </span>
                  </label>
                  <?php $ilk = false; ?>
                <?php endforeach; ?>
                <label class="pick">
                  <input type="radio" name="hedef" value="musteri:<?= (int) $c['id'] ?>" <?= $ilk ? 'checked' : '' ?> required>
                  <span>
                    <b>Bu müşteriye yeni sipariş aç</b>
                    <small class="block muted">Sipariş oluşturulur, reçete içine yazılır</small>
                  </span>
                </label>
                <?php $ilk = false; ?>
              </div>
            <?php endforeach; ?>

            <?php if ($cozum['hasta'] !== ''): ?>
              <div class="pick-group">
                <div class="pick-group-head">
                  <b>Yeni müşteri</b>
                  <?php if (!$eslesenler): ?><span class="badge sm tone-amber">bu isimde kayıt yok</span><?php endif; ?>
                </div>
                <label class="pick">
                  <input type="radio" name="hedef" value="yeni" <?= $ilk ? 'checked' : '' ?> required>
                  <span>
                    <b><?= e($cozum['hasta']) ?> adına yeni müşteri + sipariş oluştur</b>
                    <small class="block muted">SGK'daki ad soyad kullanılır; telefonu siz eklersiniz</small>
                  </span>
                </label>
                <div class="grid cols-4 pick-form">
                  <label class="field"><span>Ad</span><input name="yeni_ad" value="<?= e(tr_title($cozum['ad'])) ?>"></label>
                  <label class="field"><span>Soyad</span><input name="yeni_soyad" value="<?= e(tr_title($cozum['soyad'])) ?>"></label>
                  <label class="field"><span>Telefon</span><input name="yeni_telefon" inputmode="tel" placeholder="0532 111 22 33"></label>
                  <label class="field"><span>Doğum yılı<?= $dogumTahmin !== '' ? ' (≈)' : '' ?></span><input name="yeni_dogum" inputmode="numeric" value="<?= e($dogumTahmin) ?>"></label>
                </div>
              </div>
              <?php $ilk = false; ?>
            <?php endif; ?>

            <div class="pick-group">
              <div class="pick-group-head"><b>Listeden seç</b></div>
              <label class="pick">
                <input type="radio" name="hedef" value="liste" <?= $ilk ? 'checked' : '' ?> required>
                <span><b>Açık siparişler arasından kendim seçeyim</b></span>
              </label>
              <label class="field pick-form">
                <span>Sipariş</span>
                <select name="order_id">
                  <option value="">— sipariş seçin —</option>
                  <?php foreach ($siparisler as $s): ?>
                    <option value="<?= (int) $s['id'] ?>" <?= $hedefSiparis === (int) $s['id'] ? 'selected' : '' ?>>
                      <?= e(order_no((int) $s['id'])) ?> · <?= e($s['first_name'] . ' ' . $s['last_name']) ?> · <?= e(stage_label($s['order_stage'])) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          </div>

          <div class="form-actions">
            <button class="btn btn-primary btn-lg"><?= icon('check') ?> Aktar ve reçeteyi aç</button>
          </div>
        </form>
      </section>

      <?php if ($cozum['bulunan'] < 5): ?>
        <section class="card">
          <div class="card-head"><h2>Ham metin</h2><small class="muted">Çözümleme zayıf kaldı, buradan elle okuyabilirsiniz</small></div>
          <pre style="white-space:pre-wrap;font:13px/1.5 var(--mono);max-height:320px;overflow:auto"><?= e(mb_substr($hamMetin, 0, 4000)) ?></pre>
        </section>
      <?php endif; ?>
    <?php endif; ?>

    <section class="card">
      <div class="card-head">
        <h2>Köprüden gelenler</h2>
        <small class="muted">OptiFlow Pro'dan aktarılanlar</small>
      </div>
      <?php if (!$gelenler): ?>
        <div class="empty">
          <b>Henüz aktarım yok</b>
          <?php if ($kopruDurum === 'paket_gerekli'): ?>
          <p>OptiFlow Pro ile Medula'daki reçete tek tuşla buraya gelir.
             <a href="pro.php?ozellik=sgk_kopru">Nasıl çalışır?</a></p>
          <?php else: ?>
          <p>Medula'da reçete detayını açıp “Reçeteyi aktar” düğmesine
             bastığınızda reçete burada belirir.</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table compact">
            <thead><tr><th>Zaman</th><th>Özet</th><th class="hide-sm">Durum</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($gelenler as $g):
                $p = json_decode((string) $g['parsed'], true) ?: []; ?>
                <tr>
                  <td class="small muted nowrap"><?= e(date_tr($g['created_at'], true)) ?></td>
                  <td>
                    <b><?= e($p['hasta'] ?? '') ?: 'Reçete' ?></b>
                    <small class="block muted">Sağ <?= e($p['sag']['sph'] ?? '—') ?> · Sol <?= e($p['sol']['sph'] ?? '—') ?><?= !empty($p['erecete']) ? ' · ' . e($p['erecete']) : '' ?><?= $g['kaynak'] === 'masaustu' ? ' · masaüstü' : '' ?></small>
                  </td>
                  <td class="hide-sm">
                    <?php if ($g['used_order_id']): ?>
                      <span class="badge sm tone-green"><?= e(order_no((int) $g['used_order_id'])) ?></span>
                    <?php else: ?>
                      <span class="badge sm tone-amber">bekliyor</span>
                    <?php endif; ?>
                  </td>
                  <td class="row-actions">
                    <form method="post">
                      <?= csrf_field() ?>
                      <input type="hidden" name="eylem" value="coz">
                      <input type="hidden" name="gelen_id" value="<?= (int) $g['id'] ?>">
                      <button class="btn btn-sm btn-primary">Aç</button>
                    </form>
                    <form method="post" data-confirm="Bu kayıt silinsin mi?">
                      <?= csrf_field() ?>
                      <input type="hidden" name="eylem" value="sil">
                      <input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
                      <button class="icon-btn danger sm" title="Sil"><?= icon('trash') ?></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="split-side">
    <?php if ($kopruDurum === 'acik'): ?>
    <section class="card" data-masaustu-kart>
      <div class="card-head"><h2>Medula · OptiFlow Pro</h2><span class="pro-rozet">PRO</span></div>
      <p class="muted small">
        Medula bu uygulamanın içinde açılır. Medula'ya <b>siz</b> giriş yaparsınız;
        reçete detayını açıp <b>“Reçeteyi aktar”</b> dediğinizde yalnızca o anda
        ekranda görünen reçete buraya gelir. Eklenti, köprü adresi veya anahtar gerekmez;
        SGK kullanıcı adı/şifresi okunmaz, saklanmaz.
      </p>
      <div class="form-actions" style="margin-top:12px">
        <a class="btn" href="https://gss.sgk.gov.tr/Optik_Firma2_Web/login.faces" data-masaustu="medula-ac">Medula'yı aç</a>
        <button type="button" class="btn btn-primary" data-masaustu="aktar"><?= icon('download') ?> Reçeteyi aktar</button>
      </div>
      <p class="hint" data-masaustu-durum style="margin-top:10px">Son aktarım: —</p>
    </section>
    <?php elseif ($kopruDurum === 'masaustu_gerekli'): ?>
    <section class="card pro-kart">
      <div class="card-head"><h2>Medula aktarımı</h2><span class="pro-rozet">PRO</span></div>
      <p class="muted small">
        Mağazanız <b>OptiFlow Pro</b> paketinde. Medula'dan tek tuşla aktarım için
        bilgisayarınızdaki <b>OptiFlow Pro</b> uygulamasını açın: Medula uygulamanın içinde
        açılır, eklenti veya anahtar gerekmez.
      </p>
      <details style="margin-top:12px">
        <summary class="small">Chrome eklentisini kullanmaya devam et (geçiş dönemi)</summary>
        <p class="hint" style="margin-top:8px">
          Paketteki <code>kopru-eklenti</code> klasörü → Chrome → <code>chrome://extensions</code> →
          <b>Geliştirici modu</b> → <b>Paketlenmemiş öğe yükle</b>. Ardından aşağıdaki adres ve anahtarı
          eklentinin ayarlarına yapıştırın.
        </p>
        <label class="field"><span>Köprü adresi</span>
          <input type="text" readonly value="<?= e($koprUrl) ?>" data-kopya aria-label="Köprü adresi"></label>
        <label class="field" style="margin-top:10px"><span>Köprü anahtarı (size özel)</span>
          <input type="text" readonly value="<?= e($token) ?>" data-kopya aria-label="Köprü anahtarı"></label>
        <form method="post" style="margin-top:10px">
          <?= csrf_field() ?>
          <input type="hidden" name="eylem" value="anahtar-yenile">
          <button class="btn btn-sm">Anahtarı yenile</button>
        </form>
      </details>
    </section>
    <?php else: ?>
    <section class="card pro-kart pro-kilitli">
      <div class="card-head"><h2><?= icon('lock') ?> Medula'dan tek tuşla aktarım <span class="pro-rozet">PRO</span></h2></div>
      <p class="muted small">
        OptiFlow Pro uygulamasında Medula ekranı programın içinde açılır; reçetedeki sferik,
        silendirik, aks ve ADD değerleri tek tuşla buraya gelir. Elle kopyalama ve yazım hatası olmaz.
      </p>
      <a class="btn btn-primary" href="pro.php?ozellik=sgk_kopru" style="margin-top:12px">OptiFlow Pro'yu inceleyin</a>
    </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head"><h2>Neden böyle?</h2></div>
      <ul class="kv">
        <li><span>SGK ekranı mağazanızın <b>statik IP</b>'sine tanımlı; sunucumuz oraya bağlanamaz. Köprü, işlemi mağazadaki bilgisayarda bırakır.</span></li>
        <li><span>Sistem SGK'ya <b>hiç istek atmaz</b>, şifre saklamaz, otomatik giriş yapmaz.</span></li>
        <li><span>Aktarılan değerler <b>önce önizlemede</b> gösterilir; siz onaylamadan siparişe yazılmaz.</span></li>
        <li><span>Aktarımdan sonra reçete ekranı açılır: <b>cam tipini seçip kaydedin</b>, camlar o zaman atölye listesine düşer.</span></li>
      </ul>
    </section>
  </aside>
</div>

<?php page_end($masaustu ? ['sgk.js', 'masaustu.js'] : ['sgk.js']);
