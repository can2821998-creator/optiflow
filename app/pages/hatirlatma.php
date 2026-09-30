<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   Hatırlatma merkezi — aranacak müşteriler tek ekranda.
   Listeler verinin kendisinden hesaplanır; "arandı / ertele / kapat"
   kararları reminders tablosuna yazılır.
   ========================================================================== */

$me = require_login();

$turler = reminder_kinds();
$tur = query('tur', 'yenileme');
if (!isset($turler[$tur])) {
    $tur = 'yenileme';
}

if (is_post()) {
    csrf_check();
    $eylem = post('eylem');
    $kind = post('kind');
    if (!isset($turler[$kind])) {
        $kind = 'manuel';
    }
    $cid = post_int('customer_id');
    $don = 'hatirlatma.php?tur=' . urlencode(post('tur') ?: $kind);

    /* --- Otomatik listeler: arandı / ertele / kapat --- */
    if ($eylem === 'arandi' && $cid > 0) {
        reminder_mark($kind, $cid, 'yapildi', [
            'order_id' => post_int('order_id'),
            'channel'  => post('kanal') === 'whatsapp' ? 'whatsapp' : 'telefon',
            'note'     => post('not'),
        ]);
        audit('reminder_done', 'customer', $cid, ['tür' => reminder_kind_label($kind), 'kanal' => post('kanal') ?: 'telefon']);
        $wa = post('wa');
        if ($wa !== '' && str_starts_with($wa, 'https://wa.me/')) {
            header('Location: ' . $wa, true, 303);
            exit;
        }
        flash('Arandı olarak işaretlendi.');
        redirect($don);
    }

    /* --- 4.12.0 WhatsApp: listedeki izinli müşterileri kuyruğa ekle --- */
    if ($eylem === 'kuyruga' && ozellik_acik('whatsapp') && in_array($kind, ['yenileme', 'sgk', 'teslim', 'lens'], true)) {
        $fn = ['yenileme' => 'reminder_renewals', 'sgk' => 'reminder_sgk', 'teslim' => 'reminder_pickups', 'lens' => 'reminder_lens'][$kind];
        $eklenen = 0;
        $atlanan = 0;
        foreach ($fn(300) as $satir) {
            $sonuc = wa_hatirlatma_kuyruga($kind, $satir);
            $sonuc['ok'] ? $eklenen++ : $atlanan++;
        }
        audit('wa_toplu', 'reminder', null, ['tür' => reminder_kind_label($kind), 'eklenen' => $eklenen, 'atlanan' => $atlanan]);
        flash($eklenen . ' mesaj WhatsApp kuyruğuna eklendi' . ($atlanan ? ', ' . $atlanan . ' kişi atlandı (izin yok, telefon geçersiz ya da bu ay zaten gönderildi)' : '') . '.');
        redirect($eklenen ? 'mesajlar.php' : $don);
    }

    if ($eylem === 'ertele' && $cid > 0) {
        $ay = max(1, min(24, post_int('ay') ?: 3));
        reminder_mark($kind, $cid, 'bekliyor', [
            'order_id' => post_int('order_id'),
            'due_date' => date('Y-m-d', strtotime("+$ay months")),
        ]);
        flash($ay . ' ay sonraya ertelendi.');
        redirect($don);
    }

    if ($eylem === 'kapat' && $cid > 0) {
        reminder_mark($kind, $cid, 'kapali', ['order_id' => post_int('order_id')]);
        audit('reminder_close', 'customer', $cid, ['tür' => reminder_kind_label($kind)]);
        flash('Bu müşteri bu listede bir daha görünmeyecek.');
        redirect($don);
    }

    /* --- Elle hatırlatma --- */
    if ($eylem === 'ekle' && $cid > 0) {
        $tarih = post('due_date');
        if ($tarih !== '' && !valid_date($tarih)) {
            flash('Tarih geçersiz.', 'error');
            redirect($don);
        }
        insert('reminders', [
            'customer_id' => $cid,
            'order_id'    => post_int('order_id') ?: null,
            'kind'        => 'manuel',
            'due_date'    => $tarih ?: date('Y-m-d'),
            'note'        => mb_substr(trim(post('not')), 0, 255) ?: null,
            'status'      => 'bekliyor',
            'created_by'  => (int) $me['id'],
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        audit('reminder_create', 'customer', $cid, ['tarih' => $tarih, 'not' => mb_substr(post('not'), 0, 60)]);
        flash('Hatırlatma eklendi.');
        redirect('hatirlatma.php?tur=manuel');
    }

    if ($eylem === 'manuel-yapildi' && post_int('id') > 0) {
        update('reminders', [
            'status'  => 'yapildi',
            'done_at' => date('Y-m-d H:i:s'),
            'done_by' => (int) $me['id'],
            'channel' => post('kanal') === 'whatsapp' ? 'whatsapp' : 'telefon',
        ], 'id = ? AND kind = ?', [post_int('id'), 'manuel']);
        $wa = post('wa');
        if ($wa !== '' && str_starts_with($wa, 'https://wa.me/')) {
            header('Location: ' . $wa, true, 303);
            exit;
        }
        flash('Hatırlatma tamamlandı.');
        redirect('hatirlatma.php?tur=manuel');
    }

    if ($eylem === 'manuel-ertele' && post_int('id') > 0) {
        $ay = max(1, min(24, post_int('ay') ?: 1));
        update('reminders', ['due_date' => date('Y-m-d', strtotime("+$ay months"))], 'id = ? AND kind = ?', [post_int('id'), 'manuel']);
        flash($ay . ' ay sonraya ertelendi.');
        redirect('hatirlatma.php?tur=manuel');
    }

    if ($eylem === 'manuel-sil' && post_int('id') > 0) {
        q('DELETE FROM reminders WHERE id = ? AND kind = ?', [post_int('id'), 'manuel']);
        redirect('hatirlatma.php?tur=manuel');
    }
}

/* ---------- Listeler ---------- */
$liste = match ($tur) {
    'sgk'    => reminder_sgk(),
    'teslim' => reminder_pickups(),
    'manuel' => reminder_manual(false),
    'lens'   => reminder_lens(),
    default  => reminder_renewals(),
};

$sayilar = [
    'yenileme' => count($tur === 'yenileme' ? $liste : reminder_renewals(300)),
    'sgk'      => count($tur === 'sgk' ? $liste : reminder_sgk(300)),
    'teslim'   => count($tur === 'teslim' ? $liste : reminder_pickups(300)),
    'manuel'   => count(reminder_manual(true, 300)),
];
if (isset($turler['lens'])) {
    $sayilar['lens'] = count($tur === 'lens' ? $liste : reminder_lens(300));
}
$a = reminder_settings();
$aciklama = [
    'yenileme' => 'Son gözlüğünün üzerinden ' . $a['renew'] . ' aydan fazla geçmiş müşteriler.',
    'sgk'      => 'Son reçetesinin üzerinden ' . $a['sgk'] . ' ay (18 yaş altı ' . $a['sgk_child'] . ' ay) geçmiş müşteriler — SGK hakkı yenilenmiş olabilir.',
    'teslim'   => 'Hazır olduğu halde ' . $a['pickup'] . ' gündür teslim alınmamış gözlükler.',
    'manuel'   => 'Elle eklenen "şu tarihte ara" kayıtları.',
    'lens'     => 'Kontakt lensi ' . (function_exists('lens_hatirlatma_gun') ? lens_hatirlatma_gun() : 10) . ' gün içinde bitecek ya da bitmiş müşteriler.',
];
$aciklama['sgk'] = 'SGK hakkı doğmuş müşteriler. Hak tarihi, son SGK\'lı reçetenin tarihinden hesaplanır (' . $a['sgk'] . ' ay; 18 yaş altı ' . $a['sgk_child'] . ' ay). SGK kaydı olmayanlarda son reçete esas alınır.';
$waAcik = function_exists('ozellik_acik') && ozellik_acik('whatsapp');

/* Elle hatırlatma eklerken müşteri arama */
$ara = trim(query('ara'));
$adaylar = [];
if ($ara !== '' && $tur === 'manuel') {
    $like = '%' . $ara . '%';
    $adaylar = rows(
        "SELECT id, first_name, last_name, phone FROM customers
          WHERE CONCAT(first_name, ' ', last_name) LIKE ? OR phone LIKE ?
          ORDER BY id DESC LIMIT 12",
        [$like, $like]
    );
}

page_start('Hatırlatma merkezi', 'hatirlatma');
page_header('Hatırlatma merkezi', 'Aranacak müşteriler tek listede — gözlüğü eskiyenler, SGK hakkı doğanlar, gözlüğünü almayanlar ve planlı aramalar', '', '', 'Müşteri ilişkileri');
?>

<nav class="tabs" aria-label="Hatırlatma türü">
  <?php foreach ($turler as $k => $ad): ?>
    <a class="tab <?= $tur === $k ? 'active' : '' ?>" href="hatirlatma.php?tur=<?= e($k) ?>">
      <?= e($ad) ?><em><?= (int) $sayilar[$k] ?></em>
    </a>
  <?php endforeach; ?>
</nav>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head">
        <h2><?= icon('bell') ?> <?= e($turler[$tur]) ?></h2>
        <small class="muted"><?= e($aciklama[$tur]) ?></small>
      </div>

      <?php if ($waAcik && $liste && in_array($tur, ['yenileme', 'sgk', 'teslim', 'lens'], true)): ?>
        <form method="post" class="wa-toplu" style="margin:0 0 12px" data-confirm="Listedeki mesaj izni olan müşteriler WhatsApp kuyruğuna eklensin mi?">
          <?= csrf_field() ?><input type="hidden" name="eylem" value="kuyruga"><input type="hidden" name="kind" value="<?= e($tur) ?>"><input type="hidden" name="tur" value="<?= e($tur) ?>">
          <button class="btn btn-sm btn-wa"><?= icon('chat') ?> Listeyi WhatsApp kuyruğuna ekle</button>
          <small class="muted"><?= $tur === 'teslim' ? 'Bilgilendirme mesajı; tüm müşterilere gider.' : 'Yalnızca mesaj izni olan müşterilere gider.' ?></small>
        </form>
      <?php endif; ?>
      <?php if (!$liste): ?>
        <?= empty_state('Şu an aranacak kimse yok', $tur === 'manuel'
            ? 'Bir müşteriyi sonra aramak için aşağıdan hatırlatma ekleyebilir ya da müşteri kartındaki “Hatırlatma ekle” düğmesini kullanabilirsiniz.'
            : 'Liste her açılışta kendini günceller; zamanı gelen müşteriler burada belirir.') ?>
      <?php else: ?>
        <div class="table-wrap">
          <table class="table rem-table">
            <thead>
              <tr>
                <th>Müşteri</th>
                <th class="hide-sm">Telefon</th>
                <th><?= $tur === 'manuel' ? 'Tarih / not' : ($tur === 'teslim' ? 'Hazır olalı' : 'Son işlem') ?></th>
                <th></th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($liste as $r):
              $cid = (int) ($tur === 'manuel' ? $r['customer_id'] : $r['id']);
              $mesaj = reminder_message($tur === 'manuel' ? 'manuel' : $tur, $r);
              $wa = wa_url($r['phone'] ?? '', $mesaj);
              $gecikme = $tur === 'manuel' && $r['due_date'] && $r['due_date'] < date('Y-m-d');
            ?>
              <tr>
                <td>
                  <a class="cell-link" href="customer.php?id=<?= $cid ?>">
                    <span class="avatar"><?= e(initials((string) $r['first_name'], (string) $r['last_name'])) ?></span>
                    <span class="cell-main">
                      <b><?= e($r['first_name'] . ' ' . $r['last_name']) ?></b>
                      <small class="show-sm"><?= e(phone_display($r['phone'])) ?></small>
                    </span>
                  </a>
                </td>
                <td class="hide-sm"><?= $r['phone'] ? e(phone_display($r['phone'])) : '<span class="muted">—</span>' ?></td>
                <td>
                  <?php if ($tur === 'manuel'): ?>
                    <b class="<?= $gecikme ? 'text-danger' : '' ?>"><?= e(date_tr($r['due_date'])) ?></b>
                    <small class="block muted"><?= e($r['note'] ?: 'Not yok') ?><?= $r['created_by_name'] ? ' · ' . e($r['created_by_name']) : '' ?></small>
                  <?php elseif ($tur === 'teslim'): ?>
                    <b><?= (int) $r['gun'] ?> gün</b>
                    <small class="block muted">
                      <?= e(order_no((int) $r['son_siparis'])) ?> · <?= e(date_tr($r['son_tarih'])) ?>
                      <?php if (can_see_amounts() && (float) $r['balance'] > 0.009): ?> · kalan <?= e(money($r['balance'])) ?><?php endif; ?>
                    </small>
                  <?php elseif ($tur === 'lens'): ?>
                    <b class="<?= $r['bitis'] < date('Y-m-d') ? 'text-danger' : '' ?>"><?= e(date_tr($r['bitis'])) ?> bitiş</b>
                    <small class="block muted"><?= e($r['urun']) ?></small>
                  <?php elseif ($tur === 'sgk' && !empty($r['hak_tarihi'])): ?>
                    <b>Hak: <?= e(date_tr($r['hak_tarihi'])) ?></b>
                    <small class="block muted">
                      <?= !empty($r['sgk_kaydi']) ? 'Son SGK reçetesi ' : 'Son reçete ' ?><?= e(date_tr($r['son_tarih'])) ?>
                      <?php if (!empty($r['son_siparis'])): ?> · <?= e(order_no((int) $r['son_siparis'])) ?><?php endif; ?>
                    </small>
                  <?php else: ?>
                    <b><?= (int) $r['ay'] ?> ay önce</b>
                    <small class="block muted">
                      <?= e(date_tr($r['son_tarih'])) ?>
                      <?php if (!empty($r['siparis_sayisi'])): ?> · <?= (int) $r['siparis_sayisi'] ?> sipariş<?php endif; ?>
                      <?php if (!empty($r['son_siparis'])): ?> · <?= e(order_no((int) $r['son_siparis'])) ?><?php endif; ?>
                    </small>
                  <?php endif; ?>
                </td>
                <td class="row-actions rem-actions">
                  <?php if ($wa !== ''): ?>
                    <form method="post" target="_blank" rel="noopener">
                      <?= csrf_field() ?>
                      <input type="hidden" name="eylem" value="<?= $tur === 'manuel' ? 'manuel-yapildi' : 'arandi' ?>">
                      <input type="hidden" name="kind" value="<?= e($tur) ?>">
                      <input type="hidden" name="tur" value="<?= e($tur) ?>">
                      <input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>">
                      <input type="hidden" name="customer_id" value="<?= $cid ?>">
                      <input type="hidden" name="order_id" value="<?= (int) ($r['son_siparis'] ?? $r['order_id'] ?? 0) ?>">
                      <input type="hidden" name="kanal" value="whatsapp">
                      <input type="hidden" name="wa" value="<?= e($wa) ?>">
                      <button class="btn btn-sm btn-primary" title="<?= e($mesaj) ?>"><?= icon('chat') ?> WhatsApp</button>
                    </form>
                  <?php endif; ?>
                  <?php if ($r['phone']): ?>
                    <a class="btn btn-sm" href="tel:<?= e(preg_replace('/\D/', '', (string) $r['phone'])) ?>">Ara</a>
                  <?php endif; ?>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="eylem" value="<?= $tur === 'manuel' ? 'manuel-yapildi' : 'arandi' ?>">
                    <input type="hidden" name="kind" value="<?= e($tur) ?>">
                    <input type="hidden" name="tur" value="<?= e($tur) ?>">
                    <input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>">
                    <input type="hidden" name="customer_id" value="<?= $cid ?>">
                    <input type="hidden" name="order_id" value="<?= (int) ($r['son_siparis'] ?? $r['order_id'] ?? 0) ?>">
                    <input type="hidden" name="kanal" value="telefon">
                    <button class="btn btn-sm" title="Arandı olarak işaretle"><?= icon('check') ?> Arandı</button>
                  </form>
                  <details class="rem-more">
                    <summary aria-label="Diğer seçenekler">⋯</summary>
                    <div class="rem-menu">
                      <?php foreach ([1, 3, 6] as $ay): ?>
                        <form method="post">
                          <?= csrf_field() ?>
                          <input type="hidden" name="eylem" value="<?= $tur === 'manuel' ? 'manuel-ertele' : 'ertele' ?>">
                          <input type="hidden" name="kind" value="<?= e($tur) ?>">
                          <input type="hidden" name="tur" value="<?= e($tur) ?>">
                          <input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>">
                          <input type="hidden" name="customer_id" value="<?= $cid ?>">
                          <input type="hidden" name="ay" value="<?= $ay ?>">
                          <button class="linkish"><?= $ay ?> ay ertele</button>
                        </form>
                      <?php endforeach; ?>
                      <form method="post" data-confirm="<?= $tur === 'manuel' ? 'Hatırlatma silinsin mi?' : 'Bu müşteri bu listede bir daha görünmesin mi?' ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="eylem" value="<?= $tur === 'manuel' ? 'manuel-sil' : 'kapat' ?>">
                        <input type="hidden" name="kind" value="<?= e($tur) ?>">
                        <input type="hidden" name="tur" value="<?= e($tur) ?>">
                        <input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>">
                        <input type="hidden" name="customer_id" value="<?= $cid ?>">
                        <button class="linkish danger"><?= $tur === 'manuel' ? 'Sil' : 'Listeden çıkar' ?></button>
                      </form>
                    </div>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="list-foot"><span class="muted"><?= count($liste) ?> kayıt</span></div>
      <?php endif; ?>
    </section>

    <?php if ($tur === 'manuel'): ?>
      <section class="card">
        <div class="card-head"><h2>Hatırlatma ekle</h2><small class="muted">Müşteriyi arayıp tarihi ve notu yazın</small></div>
        <form class="search" method="get" role="search" style="margin-bottom:14px">
          <input type="hidden" name="tur" value="manuel">
          <?= icon('search') ?>
          <input type="search" name="ara" value="<?= e($ara) ?>" placeholder="Müşteri adı veya telefon" aria-label="Müşteri ara">
        </form>
        <?php if ($ara !== '' && !$adaylar): ?>
          <p class="hint">“<?= e($ara) ?>” için müşteri bulunamadı.</p>
        <?php endif; ?>
        <?php foreach ($adaylar as $c): ?>
          <form method="post" class="grid cols-4 pick-form" style="align-items:end;margin-bottom:10px">
            <?= csrf_field() ?>
            <input type="hidden" name="eylem" value="ekle">
            <input type="hidden" name="customer_id" value="<?= (int) $c['id'] ?>">
            <label class="field"><span>Müşteri</span><input value="<?= e($c['first_name'] . ' ' . $c['last_name']) ?>" readonly></label>
            <label class="field"><span>Ne zaman aranacak</span><input type="date" name="due_date" value="<?= e(date('Y-m-d', strtotime('+1 month'))) ?>"></label>
            <label class="field span-2"><span>Not</span><input name="not" placeholder="örn. yeni cam için tekrar konuşulacak"></label>
            <div class="form-actions"><button class="btn btn-primary btn-sm"><?= icon('plus') ?> Ekle</button></div>
          </form>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </div>

  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2>Bugünün özeti</h2></div>
      <ul class="kv">
        <li><span>Gözlük yenileme</span><b><?= (int) $sayilar['yenileme'] ?> kişi</b></li>
        <li><span>SGK hakkı</span><b><?= (int) $sayilar['sgk'] ?> kişi</b></li>
        <li><span>Teslim alınmadı</span><b><?= (int) $sayilar['teslim'] ?> gözlük</b></li>
        <li><span>Planlı arama</span><b><?= (int) $sayilar['manuel'] ?> kayıt</b></li>
        <?php if (isset($sayilar['lens'])): ?><li><span>Kontakt lens</span><b><?= (int) $sayilar['lens'] ?> kişi</b></li><?php endif; ?>
      </ul>
      <?php if (is_super()): ?>
        <p class="hint" style="margin-top:12px">Süreleri ve mesaj metinlerini <a class="link" href="settings.php?tab=hatirlatma">Ayarlar › Hatırlatma</a> bölümünden değiştirebilirsiniz.</p>
      <?php endif; ?>
    </section>

    <section class="card">
      <div class="card-head"><h2>Nasıl çalışır?</h2></div>
      <ul class="kv">
        <li><span><b>WhatsApp</b> düğmesi mesajı hazır metinle açar ve kaydı "arandı" olarak işaretler.</span></li>
        <li><span><b>Arandı</b> dedikten sonra müşteri 6 ay boyunca (teslim listesinde 10 gün) tekrar çıkmaz.</span></li>
        <li><span><b>Ertele</b>, seçtiğiniz süre kadar listeden gizler; <b>Listeden çıkar</b> kalıcıdır.</span></li>
        <li><span>Listeler her açılışta yeniden hesaplanır; yeni sipariş giren müşteri kendiliğinden listeden düşer.</span></li>
      </ul>
    </section>
  </aside>
</div>

<?php page_end();
