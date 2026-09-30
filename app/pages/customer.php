<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$id = is_post() ? post_int('customer_id') : query_int('id');
$c = find_customer($id);
if (!$c) {
    http_response_code(404);
    render_error_page('Müşteri bulunamadı', 'Birleştirilmiş veya silinmiş olabilir.');
}
$self = 'customer.php?id=' . $id;

if (is_post()) {
    $action = post('action');
    if ($action === 'update') {
        $first = tr_title(post('first_name'));
        $last = tr_title(post('last_name'));
        $phone = normalize_phone(post('phone'));
        $birth = post('birth_year');
        $errors = [];
        if ($first === '' || $last === '') { $errors[] = 'Ad ve soyad zorunlu.'; }
        if ($phone === null) { $errors[] = 'Telefon numarası geçersiz.'; }
        if ($birth !== '' && (!ctype_digit($birth) || (int) $birth < 1900 || (int) $birth > (int) date('Y'))) { $errors[] = 'Doğum yılı geçersiz.'; }
        if ($errors) {
            foreach ($errors as $err) { flash($err, 'error'); }
            redirect($self);
        }
        $data = ['first_name' => $first, 'last_name' => $last, 'phone' => (string) $phone, 'birth_year' => $birth !== '' ? (int) $birth : null, 'notes' => mb_substr(post('notes'), 0, 2000) ?: null];
        $changes = [];
        foreach ($data as $k => $v) {
            if ((string) $c[$k] !== (string) $v) { $changes[$k] = ['önce' => $c[$k], 'sonra' => $v]; }
        }
        update('customers', $data, 'id = ?', [$id]);
        sync_customer_to_orders($id);
        if ($changes) { audit('customer_update', 'customer', $id, $changes); }
        flash('Müşteri bilgileri kaydedildi.');
        redirect($self);
    }
    if (in_array($action, ['aile_ekle', 'aile_cikar', 'aile_bildirildi'], true)) {
        try {
            require_once dirname(__DIR__) . '/family.php';
            if ($action === 'aile_ekle') {
                $mid = post_int('member_id');
                if ($mid > 0 && $mid !== $id && find_customer($mid)) {
                    family_link($id, $mid, post('relation'));
                    flash('Aileye eklendi.');
                } else {
                    flash('Kişi bulunamadı.', 'error');
                }
            } elseif ($action === 'aile_cikar') {
                family_unlink(post_int('member_id'));
                flash('Aileden çıkarıldı.', 'info');
            } else {
                $n = family_mark_notified($c);
                flash($n > 0 ? 'Aileye haber verildi olarak işaretlendi; bu kişiler Hatırlatmalar listesinden düştü.' : 'İşaretlenecek kayıt yok.', $n > 0 ? 'ok' : 'info');
            }
        } catch (Throwable $e) {
            flash('Aile işlemi yapılamadı: ' . $e->getMessage(), 'error');
        }
        redirect($self);
    }
    /* 4.12.0 — WhatsApp mesaj izni */
    if ($action === 'wa_izin' && function_exists('ozellik_acik') && ozellik_acik('whatsapp')) {
        $v = post('izin');
        $kaynak = in_array(post('kaynak'), ['sozlu', 'yazili', 'form', 'whatsapp'], true) ? post('kaynak') : 'sozlu';
        wa_izin_kaydet($id, $v === '1' ? true : ($v === '0' ? false : null), $kaynak);
        flash($v === '1' ? 'Mesaj izni kaydedildi.' : ($v === '0' ? 'Müşteri mesaj almak istemiyor olarak kaydedildi.' : 'İzin bilgisi sıfırlandı.'));
        redirect($self);
    }
    /* 4.12.0 — Kontakt lens takibi */
    if ($action === 'lens_ekle' && function_exists('ozellik_acik') && ozellik_acik('lens_takip')) {
        try {
            lens_ekle([
                'customer_id' => $id, 'order_id' => post_int('order_id') ?: null, 'urun' => post('urun'), 'goz' => post('goz'),
                'kutu_adet' => post_int('kutu_adet'), 'kutu_gun' => post_int('kutu_gun'), 'baslangic' => post('baslangic') ?: date('Y-m-d'), 'not' => post('not'),
            ]);
            flash('Lens kaydı eklendi; bitişe ' . lens_hatirlatma_gun() . ' gün kala hatırlatılacak.');
        } catch (DomainException $e) {
            flash($e->getMessage(), 'error');
        }
        redirect($self);
    }
    if ($action === 'lens_birakti' && function_exists('ozellik_acik') && ozellik_acik('lens_takip')) {
        q("UPDATE lens_takip SET durum = 'birakti' WHERE id = ? AND customer_id = ?", [post_int('lens_id'), $id]);
        flash('Lens takibi durduruldu.');
        redirect($self);
    }
    if ($action === 'delete' && is_super()) {
        if ((int) scalar('SELECT COUNT(*) FROM orders WHERE customer_id = ?', [$id])) {
            flash('Siparişi olan müşteri silinemez.', 'error');
            redirect($self);
        }
        q('DELETE FROM customers WHERE id = ?', [$id]);
        audit('customer_update', 'customer', $id, ['silindi' => $c['first_name'] . ' ' . $c['last_name']]);
        flash('Müşteri silindi.', 'info');
        redirect('customers.php');
    }
    redirect($self);
}

$orders = rows('SELECT o.*, COALESCE(p.paid, 0) AS paid, o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount AS balance FROM orders o ' . PAID_JOIN . ' WHERE o.customer_id = ? ORDER BY o.created_at DESC', [$id]);
$rxList = rows('SELECT r.*, n.lens_type AS near_lens_type, n.right_sph AS near_right_sph, n.left_sph AS near_left_sph FROM prescription_records r LEFT JOIN near_prescription_details n ON n.prescription_id = r.id WHERE r.customer_id = ? ORDER BY r.prescription_date DESC, r.id DESC', [$id]);
$sum = ['total' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
foreach ($orders as $o) {
    if ($o['order_stage'] !== 'iptal') {
        $sum['total'] += (float) $o['total_amount'];
        $sum['paid'] += (float) $o['paid'];
        $sum['balance'] += (float) $o['balance'];
    }
}
$hatirlatmalar = rows(
    "SELECT r.*, u.full_name AS created_by_name
       FROM reminders r LEFT JOIN user_accounts u ON u.id = r.created_by
      WHERE r.customer_id = ? AND r.kind = 'manuel' AND r.status = 'bekliyor'
      ORDER BY r.due_date IS NULL, r.due_date LIMIT 10",
    [$id]
);
$name = $c['first_name'] . ' ' . $c['last_name'];
$age = $c['birth_year'] ? (int) date('Y') - (int) $c['birth_year'] : null;

/* Aile kartı: modül yüklenemez ya da bir hata olursa kart hiç çıkmaz, müşteri kartı bozulmaz */
$aile = null;
$aileAra = [];
$aramaMetni = trim(query('aile_ara'));
try {
    require_once dirname(__DIR__) . '/family.php';
    $aile = family_overview($c);
    if ($aramaMetni !== '') {
        $aileAra = family_search($aramaMetni, $id, $aile['tum_idler']);
    }
} catch (Throwable $e) {
    $aile = null;
}

/* Çocuk göz gelişim eğrisi (18 yaş altı, en az 2 reçete): hata olursa kart hiç çıkmaz */
$gelisim = null;
try {
    if ($age !== null && $age < 18) {
        require_once dirname(__DIR__) . '/gelisim.php';
        $gelisim = gelisim_hazirla($id);
    }
} catch (Throwable $e) {
    $gelisim = null;
}

$waAcik = function_exists('ozellik_acik') && ozellik_acik('whatsapp');
$lensAcik = function_exists('ozellik_acik') && ozellik_acik('lens_takip');
$lensler = $lensAcik ? lens_musteri_kayitlari($id) : [];
$waMesajlar = $waAcik ? rows('SELECT olay, durum, created_at, gonderilme FROM wa_mesajlar WHERE customer_id = ? ORDER BY id DESC LIMIT 5', [$id]) : [];

page_start($name, 'customers');
?>
<a class="back-link" href="customers.php"><?= icon('arrow-left') ?> Müşteriler</a>
<section class="hero">
  <div class="hero-main">
    <span class="avatar lg"><?= e(initials($c['first_name'], $c['last_name'])) ?></span>
    <div>
      <small class="eyebrow">Müşteri · kayıt <?= date_tr($c['created_at']) ?></small>
      <h1><?= e($name) ?></h1>
      <p><?= $c['phone'] ? e(phone_display($c['phone'])) : 'Telefon girilmemiş' ?><?= $age ? ' · ' . $age . ' yaş' : '' ?> · <?= count($orders) ?> sipariş</p>
    </div>
  </div>
  <div class="hero-actions">
    <?php if ($c['phone']): ?><a class="btn btn-wa" href="https://wa.me/<?= e($c['phone']) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> WhatsApp</a><?php endif; ?>
    <a class="btn btn-primary" href="order-new.php?customer_id=<?= $id ?>"><?= icon('plus') ?> Yeni sipariş</a>
  </div>
</section>

<?php if (can_see_amounts() && $orders): ?>
<section class="stats three">
  <div class="stat"><small>Toplam alışveriş</small><b><?= money($sum['total']) ?></b></div>
  <div class="stat"><small>Ödenen</small><b class="text-ok"><?= money($sum['paid']) ?></b></div>
  <div class="stat <?= $sum['balance'] > 0.009 ? 'tone-red' : '' ?>"><small>Açık bakiye</small><b><?= money($sum['balance']) ?></b></div>
</section>
<?php endif; ?>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head"><h2>Siparişler</h2></div>
      <?php if (!$orders): ?>
        <?= empty_state('Sipariş yok', '', '<a class="btn" href="order-new.php?customer_id=' . $id . '">' . icon('plus') . ' Sipariş oluştur</a>') ?>
      <?php else: ?>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>No</th><th>Tarih</th><th class="hide-sm">Cam</th><th>Durum</th><?php if (can_see_amounts()): ?><th class="num">Tutar</th><?php endif; ?></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><a class="link strong" href="order.php?id=<?= (int) $o['id'] ?>"><?= order_no((int) $o['id']) ?></a></td>
              <td><?= date_tr($o['created_at']) ?></td>
              <td class="hide-sm"><?= e($o['lens_type'] ?: '—') ?></td>
              <td><?= stage_badge($o['order_stage']) ?></td>
              <?php if (can_see_amounts()): ?><td class="num"><?= money($o['total_amount']) ?><?php if ((float) $o['balance'] > 0.009 && $o['order_stage'] !== 'iptal'): ?><small class="text-danger">Kalan <?= money($o['balance']) ?></small><?php endif; ?></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </section>

    <?php if ($gelisim) { include dirname(__DIR__) . '/partials/gelisim-karti.php'; } ?>
    <section class="card">
      <div class="card-head"><h2>Reçete geçmişi</h2><small class="muted">En yeni üstte</small></div>
      <?php if (!$rxList): ?><p class="muted">Reçete kaydı yok.</p><?php endif; ?>
      <?php foreach ($rxList as $rx): ?>
        <article class="rx-card">
          <header>
            <div><b><?= date_tr($rx['prescription_date']) ?> · <?= e(lens_designs()[$rx['lens_design']] ?? '') ?></b>
              <small><?= e($rx['lens_type'] ?: 'Cam tipi yok') ?> · <a class="link" href="order.php?id=<?= (int) $rx['order_id'] ?>"><?= order_no((int) $rx['order_id']) ?></a></small></div>
            <div class="row-actions"><a class="icon-btn" href="print.php?type=rx&id=<?= (int) $rx['id'] ?>" target="_blank" aria-label="Yazdır"><?= icon('print') ?></a></div>
          </header>
          <?php include dirname(__DIR__, 2) . '/app/partials/rx-table.php'; ?>
        </article>
      <?php endforeach; ?>
    </section>
  </div>

  <aside class="split-side">
    <?php if ($aile) { include dirname(__DIR__) . '/partials/aile-karti.php'; } ?>
    <section class="card">
      <div class="card-head"><h2><?= icon('bell') ?> Hatırlatma</h2><small class="muted">Sonra aramak için</small></div>
      <?php foreach ($hatirlatmalar as $h): ?>
        <div class="kv-line">
          <b class="<?= $h['due_date'] && $h['due_date'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= date_tr($h['due_date']) ?></b>
          <span class="muted small"><?= e($h['note'] ?: 'Not yok') ?></span>
        </div>
      <?php endforeach; ?>
      <form method="post" action="hatirlatma.php" class="stack" style="gap:10px;margin-top:<?= $hatirlatmalar ? '14px' : '0' ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="ekle">
        <input type="hidden" name="customer_id" value="<?= $id ?>">
        <label class="field"><span>Ne zaman aranacak</span>
          <input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+1 month'))) ?>"></label>
        <label class="field"><span>Not</span>
          <input name="not" placeholder="örn. güneş gözlüğü için tekrar konuşulacak"></label>
        <button class="btn btn-sm"><?= icon('plus') ?> Hatırlatma ekle</button>
      </form>
      <?php if ($hatirlatmalar): ?>
        <p class="hint" style="margin-top:10px"><a class="link" href="hatirlatma.php?tur=manuel">Hatırlatma merkezinde gör</a></p>
      <?php endif; ?>
    </section>

    <?php if ($waAcik): $izin = wa_izin_durumu($c); ?>
    <section class="card">
      <div class="card-head"><h2><?= icon('chat') ?> WhatsApp izni</h2>
        <span class="badge tone-<?= $izin === 'var' ? 'green' : ($izin === 'yok' ? 'red' : 'gray') ?>"><?= $izin === 'var' ? 'Verdi' : ($izin === 'yok' ? 'Vermedi' : 'Sorulmadı') ?></span></div>
      <p class="hint" style="margin-top:0">Yenileme, SGK hakkı, lens ve yorum mesajları yalnızca izin veren müşteriye gider. Gözlük hazır ve ödeme linki gibi sipariş bilgilendirmeleri izinden bağımsızdır.</p>
      <?php if ($c['wa_izin_tarihi']): ?><p class="mini muted" style="margin:0 0 8px">Son kayıt: <?= e(date_tr($c['wa_izin_tarihi'], true)) ?> · <?= e(['sozlu' => 'sözlü', 'yazili' => 'yazılı form', 'form' => 'form', 'whatsapp' => 'WhatsApp', 'personel' => 'personel'][$c['wa_izin_kaynak']] ?? (string) $c['wa_izin_kaynak']) ?></p><?php endif; ?>
      <form method="post" class="stack" style="gap:8px">
        <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>"><input type="hidden" name="action" value="wa_izin">
        <label class="field"><span>İzin nasıl alındı</span>
          <select name="kaynak"><option value="sozlu">Sözlü (mağazada)</option><option value="yazili">Yazılı form</option><option value="whatsapp">WhatsApp yazışması</option></select></label>
        <div class="row" style="display:flex;gap:6px;flex-wrap:wrap">
          <button class="btn btn-sm btn-primary" name="izin" value="1"><?= icon('check') ?> İzin verdi</button>
          <button class="btn btn-sm" name="izin" value="0">İstemiyor</button>
          <?php if ($izin !== 'sorulmadi'): ?><button class="btn btn-sm btn-ghost" name="izin" value="">Sıfırla</button><?php endif; ?>
        </div>
      </form>
      <?php if ($waMesajlar): ?>
        <ul class="kv" style="margin-top:10px">
          <?php foreach ($waMesajlar as $wm): [$wEt] = wa_durum_etiketi((string) $wm['durum']); ?>
            <li><span><?= e(wa_olaylar()[$wm['olay']]['ad'] ?? $wm['olay']) ?></span><b class="mini"><?= e($wEt) ?> · <?= e(date_tr($wm['gonderilme'] ?: $wm['created_at'])) ?></b></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($lensAcik): ?>
    <section class="card">
      <div class="card-head"><h2><?= icon('eye') ?> Kontakt lens</h2><small class="muted">Bitişte hatırlatılır</small></div>
      <?php foreach ($lensler as $l): $aktif = $l['durum'] === 'aktif'; ?>
        <div class="kv-line" style="<?= $aktif ? '' : 'opacity:.55' ?>">
          <b><?= e($l['urun']) ?></b>
          <span class="muted small"><?= e(lens_goz_secenekleri()[$l['goz']] ?? $l['goz']) ?> · <?= (int) $l['kutu_adet'] ?> kutu · <?= e(date_tr($l['baslangic'])) ?> → <b class="<?= $aktif && $l['bitis'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(date_tr($l['bitis'])) ?></b><?= $aktif ? '' : ' · ' . e($l['durum'] === 'yenilendi' ? 'yenilendi' : 'bıraktı') ?></span>
          <?php if ($aktif): ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>"><input type="hidden" name="action" value="lens_birakti"><input type="hidden" name="lens_id" value="<?= (int) $l['id'] ?>"><button class="linkish">Takibi durdur</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <details <?= $lensler ? '' : 'open' ?> style="margin-top:<?= $lensler ? '10px' : '0' ?>">
        <summary class="btn btn-sm"><?= icon('plus') ?> Lens satışı ekle</summary>
        <form method="post" class="stack" style="gap:8px;margin-top:10px">
          <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>"><input type="hidden" name="action" value="lens_ekle">
          <label class="field"><span>Ürün</span><input name="urun" required maxlength="160" placeholder="örn. Acuvue Oasys 6'lı"></label>
          <div class="grid cols-2" style="gap:8px">
            <label class="field"><span>Göz</span><select name="goz"><?php foreach (lens_goz_secenekleri() as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?></select></label>
            <label class="field"><span>Kutu adedi</span><input name="kutu_adet" type="number" min="1" max="99" value="1"></label>
            <label class="field"><span>Bir kutu (tek göz) kaç gün yeter</span>
              <input name="kutu_gun" type="number" min="1" max="3650" value="180" list="lens-gunler"></label>
            <label class="field"><span>Başlangıç</span><input type="date" name="baslangic" value="<?= e(date('Y-m-d')) ?>"></label>
          </div>
          <datalist id="lens-gunler"><?php foreach ([30, 90, 180, 360] as $g): ?><option value="<?= $g ?>"><?php endforeach; ?></datalist>
          <?php if ($orders): ?>
            <label class="field"><span>Sipariş (isteğe bağlı)</span><select name="order_id"><option value="">—</option><?php foreach (array_slice($orders, 0, 10) as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e(order_no((int) $o['id'])) ?> · <?= e(date_tr($o['created_at'])) ?></option><?php endforeach; ?></select></label>
          <?php endif; ?>
          <small class="muted">Örnek: 6'lı aylık kutu, tek göz 180 gün; iki göz aynı kutudan kullanıyorsa süre yarıya iner.</small>
          <button class="btn btn-sm btn-primary">Kaydet</button>
        </form>
      </details>
    </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head"><h2>Bilgiler</h2></div>
      <form method="post" class="grid cols-2" data-guard>
        <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>"><input type="hidden" name="action" value="update">
        <label class="field"><span>Ad</span><input name="first_name" value="<?= e($c['first_name']) ?>" required></label>
        <label class="field"><span>Soyad</span><input name="last_name" value="<?= e($c['last_name']) ?>" required></label>
        <label class="field"><span>Telefon</span><input name="phone" type="tel" value="<?= e(phone_display($c['phone'])) ?>"></label>
        <label class="field"><span>Doğum yılı</span><input name="birth_year" inputmode="numeric" maxlength="4" value="<?= e($c['birth_year']) ?>"></label>
        <label class="field span-all"><span>Not</span><textarea name="notes" rows="3" placeholder="Tercihler, hassasiyetler…"><?= e($c['notes']) ?></textarea></label>
        <div class="form-actions span-all">
          <?php if (is_super() && !$orders): ?><button class="btn btn-ghost danger" form="del-customer">Sil</button><?php endif; ?>
          <button class="btn btn-primary">Kaydet</button>
        </div>
      </form>
      <?php if (is_super() && !$orders): ?>
        <form method="post" id="del-customer" data-confirm="Müşteri kaydı silinsin mi?"><?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= $id ?>"><input type="hidden" name="action" value="delete"></form>
      <?php endif; ?>
    </section>
  </aside>
</div>
<?php page_end();
