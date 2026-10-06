<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$user = require_login();

if (is_post()) {
    $name = mb_substr(trim(post('customer_name')), 0, 160);
    if ($name === '') {
        flash('Müşteri adı zorunlu.', 'error');
        redirect('quotes.php');
    }
    $data = [
        'customer_id'    => post_int('customer_id') ?: null,
        'customer_name'  => $name,
        'customer_phone' => mb_substr(post('customer_phone'), 0, 20) ?: null,
        'note'           => mb_substr(post('note'), 0, 255) ?: null,
        'created_by'     => $user['id'],
    ];
    $hasOption = false;
    foreach ([1, 2, 3] as $i) {
        $oname = mb_substr(trim(post("opt{$i}_name")), 0, 60);
        $odesc = mb_substr(trim(post("opt{$i}_desc")), 0, 500);
        $oprice = post("opt{$i}_price") !== '' ? parse_money(post("opt{$i}_price")) : null;
        $data["opt{$i}_name"] = $oname ?: null;
        $data["opt{$i}_desc"] = $odesc ?: null;
        $data["opt{$i}_price"] = $oprice;
        if ($oname !== '') { $hasOption = true; }
    }
    if (!$hasOption) {
        flash('En az bir seçenek girin (örn. “İyi”).', 'error');
        redirect('quotes.php');
    }
    $qid = insert('quotes', $data);
    audit('quote_create', 'quote', $qid, ['müşteri' => $name]);
    flash('Teklif oluşturuldu.');
    redirect('quote.php?id=' . $qid);
}

$search = mb_substr(query('q'), 0, 80);
$where = '1=1';
$params = [];
if ($search !== '') {
    $where = '(q.customer_name LIKE ? OR q.customer_phone LIKE ?)';
    $params = ['%' . $search . '%', '%' . $search . '%'];
}
$quotes = rows(
    "SELECT q.*, u.full_name AS created_by_name
     FROM quotes q LEFT JOIN user_accounts u ON u.id = q.created_by
     WHERE $where ORDER BY q.created_at DESC LIMIT 100",
    $params
);

page_start('Teklifler', 'quotes');
page_header('Teklifler', 'Müşteriye katalogdaki camlarla 1–3 seçenekli teklif hazırlayın: çerçeve, Medula payı ve iskonto düşülür, döküm verilir.', '<a class="btn btn-primary" href="teklif-yeni.php">' . icon('plus') . ' Yeni teklif</a>', '', 'Satış');
?>
<details class="card">
  <summary class="card-head" style="cursor:pointer"><h2><?= icon('plus') ?> Serbest metinle teklif</h2><small class="muted">Katalogda olmayan bir iş için; fiyatları elle yazılır</small></summary>
  <form method="post" class="stack" style="margin-top:16px">
    <?= csrf_field() ?>
    <div class="grid cols-3">
      <label class="field"><span>Müşteri adı *</span><input name="customer_name" required placeholder="Ad Soyad"></label>
      <label class="field"><span>Telefon</span><input name="customer_phone" type="tel" placeholder="05XX XXX XX XX"></label>
      <label class="field"><span>Not</span><input name="note" placeholder="örn. progressive cam karşılaştırması"></label>
    </div>
    <div class="quote-options">
      <?php foreach (['İyi', 'Daha iyi', 'En iyi'] as $i => $def): $n = $i + 1; ?>
        <div class="quote-option-edit">
          <label class="field"><span>Seçenek <?= $n ?> adı</span><input name="opt<?= $n ?>_name" value="<?= e($def) ?>" placeholder="<?= e($def) ?>"></label>
          <label class="field"><span>Açıklama</span><textarea name="opt<?= $n ?>_desc" rows="3" placeholder="örn. İnce indeksli tek odak cam + standart çerçeve"></textarea></label>
          <label class="field"><span>Fiyat</span><input name="opt<?= $n ?>_price" inputmode="decimal" placeholder="0,00"></label>
        </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-primary">Teklifi oluştur</button>
  </form>
</details>

<section class="card">
  <div class="toolbar">
    <form class="search" method="get" role="search">
      <?= icon('search') ?>
      <input type="search" name="q" value="<?= e($search) ?>" placeholder="Müşteri adı veya telefon" aria-label="Teklif ara">
    </form>
  </div>
  <?php if (!$quotes): ?>
    <?= empty_state('Henüz teklif yok', 'Yukarıdan ilk teklifinizi hazırlayın.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>Müşteri</th><th class="hide-sm">Seçenekler</th><th class="hide-md">Hazırlayan</th><th class="hide-md">Tarih</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($quotes as $q): ?>
            <tr>
              <td><a class="cell-link" href="quote.php?id=<?= (int) $q['id'] ?>"><b><?= e($q['customer_name']) ?></b><small class="block muted"><?= $q['customer_phone'] ? e(phone_display($q['customer_phone'])) : '—' ?></small></a></td>
              <td class="hide-sm">
                <?php foreach (teklif_secenekleri($q) as $s): // 4.21.0: katalog teklifinde ödenecek tutar ?>
                  <span class="badge sm <?= (int) ($q['secilen'] ?? 0) === $s['no'] ? 'tone-green' : 'tone-gray' ?>"><?= e($s['baslik']) ?><?= isset($s['hesap']) ? ' · ' . money($s['hesap']['odenecek']) : ($s['fiyat'] !== null ? ' · ' . money($s['fiyat']) : '') ?></span>
                <?php endforeach; ?>
                <?php if ($q['converted_order_id']): ?><span class="badge sm tone-green">Siparişe döndü</span><?php endif; ?>
              </td>
              <td class="hide-md"><?= e($q['created_by_name'] ?: '—') ?></td>
              <td class="hide-md"><?= date_tr($q['created_at']) ?></td>
              <td><a class="btn btn-ghost btn-sm" href="quote.php?id=<?= (int) $q['id'] ?>">Aç</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php page_end();
