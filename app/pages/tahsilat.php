<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   Bakiye ve tahsilat takibi
   Açık bakiyesi olan siparişler tek ekranda: vadesi geçenler önce, hızlı
   tahsilat girişi, ödeme sözü tarihi ve hatırlatma mesajı.
   ========================================================================== */

$me = require_login();
if (!can_see_amounts()) {
    render_error_page('Yetkiniz yok', 'Tutarları görme yetkiniz olmadığı için bakiye takibi ekranı açılamaz.');
}

$filtreler = [
    'acik'     => 'Tüm açık bakiyeler',
    'geciken'  => 'Vadesi geçenler',
    'teslim'   => 'Teslim edildi, borçlu',
    'sozsuz'   => 'Söz tarihi girilmemiş',
];
$f = query('f', 'acik');
if (!isset($filtreler[$f])) {
    $f = 'acik';
}

if (is_post()) {
    csrf_check();
    $eylem = post('eylem');
    $orderId = post_int('order_id');
    $order = $orderId > 0 ? find_order($orderId) : null;
    $don = 'tahsilat.php?f=' . urlencode(post('f') ?: $f);

    if (!$order) {
        flash('Sipariş bulunamadı.', 'error');
        redirect($don);
    }

    if ($eylem === 'tahsilat') {
        if (!can_take_payments()) {
            flash('Tahsilat girme yetkiniz yok.', 'error');
            redirect($don);
        }
        $tutar = parse_money(post('amount'));
        $yontem = post('method', 'nakit');
        $bakiye = (float) $order['balance'];
        if ($tutar === null || $tutar <= 0) {
            flash('Tahsilat tutarı geçersiz. Örnek: 1.500,00', 'error');
        } elseif ($tutar > $bakiye + 0.001) {
            flash('Tahsilat, kalan bakiyeden (' . money($bakiye) . ') büyük olamaz.', 'error');
        } elseif (!isset(payment_methods()[$yontem])) {
            flash('Ödeme yöntemi geçersiz.', 'error');
        } else {
            $pid = insert('payments', [
                'order_id'   => $orderId,
                'amount'     => $tutar,
                'method'     => $yontem,
                'note'       => 'Bakiye takibinden',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => (int) $me['id'],
            ]);
            audit('payment_create', 'order', $orderId, ['tutar' => $tutar, 'yöntem' => payment_methods()[$yontem], 'ödeme' => $pid, 'kaynak' => 'bakiye takibi']);
            flash(money($tutar) . ' tahsilat kaydedildi — ' . order_no($orderId) . '.');
        }
        redirect($don);
    }

    if ($eylem === 'soz') {
        $tarih = post('promise');
        if ($tarih !== '' && !valid_date($tarih)) {
            flash('Tarih geçersiz.', 'error');
            redirect($don);
        }
        update('orders', ['balance_promise_date' => $tarih ?: null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$orderId]);
        audit('order_update', 'order', $orderId, ['ödeme sözü' => $tarih ?: 'kaldırıldı']);
        flash($tarih ? 'Ödeme sözü ' . date_tr($tarih) . ' olarak kaydedildi.' : 'Ödeme sözü kaldırıldı.');
        redirect($don);
    }

    /* --- 4.12.0 PayTR ödeme linki --- */
    if (in_array($eylem, ['odeme_linki', 'odeme_iptal'], true) && !can_take_payments()) {
        flash('Ödeme linki için tahsilat yetkiniz yok.', 'error');
        redirect($don);
    }
    if ($eylem === 'odeme_linki' && ozellik_acik('odeme_linki')) {
        try {
            $tutar = parse_money(post('tutar')) ?? 0.0;
            $l = odeme_linki_olustur($order, $tutar);
            $mesajId = null;
            if (ozellik_acik('whatsapp')) {
                $k = wa_kuyruga_ekle('odeme', (int) $order['customer_id'], $orderId, [
                    'siparis_no'  => order_no($orderId),
                    'tutar'       => money($l['tutar']),
                    'odeme_linki' => (string) $l['link'],
                ], 'odeme:l' . (int) $l['id']);
                $mesajId = $k['ok'] ? $k['id'] : null;
            }
            flash('Ödeme linki oluşturuldu (' . money($l['tutar']) . ')' . ($mesajId ? ' ve WhatsApp kuyruğuna eklendi.' : '. Linki müşteriye gönderin.'));
        } catch (DomainException $e) {
            flash($e->getMessage(), 'error');
        }
        redirect($don);
    }
    if ($eylem === 'odeme_iptal' && ozellik_acik('odeme_linki')) {
        $lid = post_int('link_id');
        $l = row('SELECT * FROM odeme_linkleri WHERE id = ? AND order_id = ?', [$lid, $orderId]);
        try {
            if (!$l) {
                throw new DomainException('Link bulunamadı.');
            }
            odeme_linki_iptal($lid);
            flash('Ödeme linki iptal edildi.');
        } catch (DomainException $e) {
            flash($e->getMessage(), 'error');
        }
        redirect($don);
    }

    if ($eylem === 'hatirlat') {
        reminder_mark('manuel', (int) $order['customer_id'], 'yapildi', [
            'order_id' => $orderId,
            'channel'  => 'whatsapp',
            'note'     => 'Bakiye hatırlatması',
        ]);
        audit('whatsapp', 'order', $orderId, ['şablon' => 'Bakiye hatırlatması']);
        $wa = post('wa');
        if ($wa !== '' && str_starts_with($wa, 'https://wa.me/')) {
            header('Location: ' . $wa, true, 303);
            exit;
        }
        redirect($don);
    }
}

/* ---------- Liste ---------- */
$kosul = match ($f) {
    'geciken' => "AND o.balance_promise_date IS NOT NULL AND o.balance_promise_date < CURDATE()",
    'teslim'  => "AND o.order_stage = 'teslim_edildi'",
    'sozsuz'  => "AND o.balance_promise_date IS NULL",
    default   => '',
};

$liste = rows(
    "SELECT o.id, o.order_stage, o.created_at, o.delivered_at, o.promised_date, o.balance_promise_date,
            o.total_amount, COALESCE(p.paid, 0) AS paid, o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount AS balance,
            c.id AS customer_id, c.first_name, c.last_name, c.phone,
            DATEDIFF(CURDATE(), DATE(COALESCE(o.delivered_at, o.created_at))) AS yas
       FROM orders o " . PAID_JOIN . "
       JOIN customers c ON c.id = o.customer_id
      WHERE o.order_stage <> 'iptal'
        AND o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount > 0.009
        $kosul
      ORDER BY (o.balance_promise_date IS NOT NULL AND o.balance_promise_date < CURDATE()) DESC,
               o.balance_promise_date IS NULL, o.balance_promise_date,
               yas DESC
      LIMIT 300"
);

/* 4.12.0 — listedeki siparişlerin son ödeme linkleri */
$odemeAcik = ozellik_acik('odeme_linki');
$linkler = [];
if ($odemeAcik && $liste) {
    $idler = array_map(static fn($o) => (int) $o['id'], $liste);
    foreach (rows('SELECT * FROM odeme_linkleri WHERE order_id IN (' . in_placeholders($idler) . ") AND durum IN ('olusturuldu','hata') ORDER BY id", $idler) as $l) {
        $linkler[(int) $l['order_id']] = $l;
    }
}

/* Özet: yaşlandırma (teslim/oluşturma tarihine göre) */
$ozet = row(
    "SELECT COUNT(*) AS adet,
            COALESCE(SUM(b.bakiye), 0) AS toplam,
            COALESCE(SUM(CASE WHEN b.yas <= 30 THEN b.bakiye END), 0) AS d30,
            COALESCE(SUM(CASE WHEN b.yas BETWEEN 31 AND 60 THEN b.bakiye END), 0) AS d60,
            COALESCE(SUM(CASE WHEN b.yas BETWEEN 61 AND 90 THEN b.bakiye END), 0) AS d90,
            COALESCE(SUM(CASE WHEN b.yas > 90 THEN b.bakiye END), 0) AS d90p,
            COALESCE(SUM(CASE WHEN b.soz IS NOT NULL AND b.soz < CURDATE() THEN b.bakiye END), 0) AS geciken
       FROM (
            SELECT o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount AS bakiye,
                   DATEDIFF(CURDATE(), DATE(COALESCE(o.delivered_at, o.created_at))) AS yas,
                   o.balance_promise_date AS soz
              FROM orders o " . PAID_JOIN . "
             WHERE o.order_stage <> 'iptal' AND o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount > 0.009
       ) b"
) ?: ['adet' => 0, 'toplam' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'd90p' => 0, 'geciken' => 0];

page_start('Bakiye takibi', 'tahsilat');
page_header('Bakiye ve tahsilat takibi', 'Açık bakiyeler, ödeme sözleri ve hızlı tahsilat', '', '', 'Kasa');
?>

<section class="stats">
  <div class="stat"><small>Açık bakiye</small><b><?= e(money($ozet['toplam'])) ?></b><span><?= (int) $ozet['adet'] ?> sipariş</span></div>
  <div class="stat <?= (float) $ozet['geciken'] > 0.009 ? 'tone-red' : '' ?>"><small>Sözü geçmiş</small><b><?= e(money($ozet['geciken'])) ?></b><span>Tarihi geçen ödeme sözleri</span></div>
  <div class="stat tone-green"><small>0–30 gün</small><b><?= e(money($ozet['d30'])) ?></b><span>Yeni bakiye</span></div>
  <div class="stat <?= (float) $ozet['d90p'] > 0.009 ? 'tone-amber' : '' ?>"><small>90 gün ve üzeri</small><b><?= e(money($ozet['d90p'])) ?></b><span>Eski alacak</span></div>
</section>

<nav class="tabs" aria-label="Bakiye filtresi">
  <?php foreach ($filtreler as $k => $ad): ?>
    <a class="tab <?= $f === $k ? 'active' : '' ?>" href="tahsilat.php?f=<?= e($k) ?>"><?= e($ad) ?></a>
  <?php endforeach; ?>
</nav>

<section class="card">
  <div class="card-head">
    <h2><?= icon('wallet') ?> <?= e($filtreler[$f]) ?></h2>
    <small class="muted"><?= count($liste) ?> sipariş</small>
  </div>

  <?php if (!$liste): ?>
    <?= empty_state('Açık bakiye yok', 'Bu filtrede bakiyesi kalan sipariş bulunmuyor.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table rem-table">
        <thead>
          <tr>
            <th>Müşteri</th>
            <th class="hide-sm">Sipariş</th>
            <th class="num">Kalan</th>
            <th class="hide-md">Yaş / söz</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($liste as $o):
          $gecikti = $o['balance_promise_date'] && $o['balance_promise_date'] < date('Y-m-d');
          $mesaj = reminder_message('bakiye', [
              'first_name' => $o['first_name'], 'last_name' => $o['last_name'],
              'son_siparis' => $o['id'], 'balance' => $o['balance'], 'total_amount' => $o['total_amount'],
          ]);
          $wa = wa_url($o['phone'], $mesaj);
        ?>
          <tr>
            <td>
              <a class="cell-link" href="customer.php?id=<?= (int) $o['customer_id'] ?>">
                <span class="avatar"><?= e(initials((string) $o['first_name'], (string) $o['last_name'])) ?></span>
                <span class="cell-main">
                  <b><?= e($o['first_name'] . ' ' . $o['last_name']) ?></b>
                  <small class="show-sm"><?= e(order_no((int) $o['id'])) ?> · <?= e(money($o['balance'])) ?></small>
                </span>
              </a>
            </td>
            <td class="hide-sm">
              <a class="link" href="order.php?id=<?= (int) $o['id'] ?>"><?= e(order_no((int) $o['id'])) ?></a>
              <small class="block muted"><?= e(stage_label($o['order_stage'])) ?> · <?= e(date_tr($o['created_at'])) ?></small>
            </td>
            <td class="num">
              <b class="<?= $gecikti ? 'text-danger' : '' ?>"><?= e(money($o['balance'])) ?></b>
              <small class="block muted"><?= e(money($o['paid'])) ?> / <?= e(money($o['total_amount'])) ?></small>
            </td>
            <td class="hide-md">
              <b><?= (int) $o['yas'] ?> gün</b>
              <small class="block <?= $gecikti ? 'text-danger' : 'muted' ?>">
                <?= $o['balance_promise_date'] ? 'söz: ' . e(date_tr($o['balance_promise_date'])) : 'söz yok' ?>
              </small>
            </td>
            <td class="row-actions rem-actions">
              <?php if ($wa !== ''): ?>
                <form method="post" target="_blank" rel="noopener">
                  <?= csrf_field() ?>
                  <input type="hidden" name="eylem" value="hatirlat">
                  <input type="hidden" name="f" value="<?= e($f) ?>">
                  <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                  <input type="hidden" name="wa" value="<?= e($wa) ?>">
                  <button class="btn btn-sm" title="<?= e($mesaj) ?>"><?= icon('chat') ?> Hatırlat</button>
                </form>
              <?php endif; ?>

              <?php if ($odemeAcik && can_take_payments()):
                $lk = $linkler[(int) $o['id']] ?? null; ?>
                <details class="rem-more">
                  <summary class="btn btn-sm <?= $lk && $lk['durum'] === 'olusturuldu' ? 'btn-ghost' : '' ?>" title="PayTR ödeme linki"><?= icon('card') ?> <?= $lk && $lk['durum'] === 'olusturuldu' ? 'Link gönderildi' : 'Ödeme linki' ?></summary>
                  <div class="rem-menu" style="min-width:260px">
                    <?php if ($lk && $lk['durum'] === 'olusturuldu'): ?>
                      <p class="mini" style="margin:0 0 6px"><b><?= e(money($lk['tutar'])) ?></b> · son gün <?= e(date_tr($lk['son_kullanim'])) ?><?= (int) $lk['test'] ? ' · <b>TEST</b>' : '' ?></p>
                      <input class="kopyala" readonly data-kopyala value="<?= e((string) $lk['link']) ?>" aria-label="Ödeme linki">
                      <?php $waL = wa_url($o['phone'], 'Merhaba ' . $o['first_name'] . ', ' . order_no((int) $o['id']) . ' numaralı siparişinizin ' . money($lk['tutar']) . ' tutarındaki ödemesini bu linkten kartla yapabilirsiniz: ' . $lk['link']); ?>
                      <?php if ($waL !== ''): ?><a class="btn btn-sm btn-wa" href="<?= e($waL) ?>" target="_blank" rel="noopener" style="margin-top:6px"><?= icon('chat') ?> WhatsApp'ta gönder</a><?php endif; ?>
                      <form method="post" style="margin-top:6px" data-confirm="Ödeme linki iptal edilsin mi?">
                        <?= csrf_field() ?><input type="hidden" name="eylem" value="odeme_iptal"><input type="hidden" name="f" value="<?= e($f) ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>"><input type="hidden" name="link_id" value="<?= (int) $lk['id'] ?>">
                        <button class="linkish danger">Linki iptal et</button>
                      </form>
                    <?php else: ?>
                      <?php if ($lk && $lk['durum'] === 'hata'): ?><p class="mini text-danger" style="margin:0 0 6px"><?= e((string) $lk['hata']) ?></p><?php endif; ?>
                      <form method="post" class="stack" style="gap:8px">
                        <?= csrf_field() ?><input type="hidden" name="eylem" value="odeme_linki"><input type="hidden" name="f" value="<?= e($f) ?>">
                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                        <label class="field"><span>Link tutarı</span>
                          <input name="tutar" inputmode="decimal" value="<?= e(number_format((float) $o['balance'], 2, ',', '.')) ?>"></label>
                        <button class="btn btn-sm btn-primary"><?= icon('card') ?> Link oluştur</button>
                        <small class="muted">Müşteri kartla öder; ödeme gelince tahsilat kendiliğinden yazılır.</small>
                      </form>
                    <?php endif; ?>
                  </div>
                </details>
              <?php endif; ?>

              <?php if (can_take_payments()): ?>
                <details class="rem-more">
                  <summary class="btn btn-sm btn-primary"><?= icon('wallet') ?> Tahsilat</summary>
                  <div class="rem-menu" style="min-width:230px">
                    <form method="post" class="stack" style="gap:8px">
                      <?= csrf_field() ?>
                      <input type="hidden" name="eylem" value="tahsilat">
                      <input type="hidden" name="f" value="<?= e($f) ?>">
                      <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                      <label class="field"><span>Tutar</span>
                        <input name="amount" inputmode="decimal" value="<?= e(number_format((float) $o['balance'], 2, ',', '.')) ?>"></label>
                      <label class="field"><span>Yöntem</span>
                        <select name="method">
                          <?php foreach (payment_methods() as $k => $ad): ?>
                            <option value="<?= e($k) ?>"><?= e($ad) ?></option>
                          <?php endforeach; ?>
                        </select></label>
                      <button class="btn btn-sm btn-primary"><?= icon('check') ?> Kaydet</button>
                    </form>
                  </div>
                </details>
              <?php endif; ?>

              <details class="rem-more">
                <summary aria-label="Diğer seçenekler">⋯</summary>
                <div class="rem-menu" style="min-width:210px">
                  <form method="post" class="stack" style="gap:8px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="eylem" value="soz">
                    <input type="hidden" name="f" value="<?= e($f) ?>">
                    <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                    <label class="field"><span>Ödeme sözü</span>
                      <input type="date" name="promise" value="<?= e((string) $o['balance_promise_date']) ?>"></label>
                    <button class="btn btn-sm">Kaydet</button>
                  </form>
                  <a class="linkish" href="order.php?id=<?= (int) $o['id'] ?>">Siparişi aç</a>
                  <a class="linkish" href="print.php?type=order&id=<?= (int) $o['id'] ?>" target="_blank">Fişi yazdır</a>
                </div>
              </details>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="list-foot"><span class="muted">En eski ve sözü geçmiş bakiyeler üstte listelenir.</span></div>
  <?php endif; ?>
</section>

<?php page_end();
