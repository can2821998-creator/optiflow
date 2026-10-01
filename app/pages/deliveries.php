<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$pending = rows(
    "SELECT d.*, s.name AS supplier_name
     FROM supplier_deliveries d JOIN suppliers s ON s.id = d.supplier_id
     WHERE d.invoice_id IS NULL
     ORDER BY d.delivered_at DESC"
);

page_start('Fatura bekleyen teslimatlar', 'deliveries');
page_header(
    'Fatura bekleyen teslimatlar',
    'Camlar “Geldi” işaretlendiğinde tedarikçisi belliyse burada listelenir. Aynı faturada farklı sipariş/cam tipi olabileceği için maliyeti cam cam girin.',
    '', '', 'Depo · Tedarik'
);
?>
<?php if (!$pending): ?>
  <section class="card"><?= empty_state('Bekleyen fatura yok', 'Şu anda fatura girilmesini bekleyen bir teslimat yok.') ?></section>
<?php else: ?>
  <div class="stack">
    <?php foreach ($pending as $d): $dItems = delivery_items((int) $d['id']); ?>
      <section class="card delivery-card">
        <div class="card-head">
          <h2><?= icon('box') ?> <?= e($d['supplier_name']) ?></h2>
          <span class="badge tone-amber"><?= (int) $d['item_count'] ?> cam</span>
        </div>
        <p class="muted" style="margin-top:-8px"><?= date_tr($d['delivered_at'], true) ?> tarihinde geldi olarak işaretlendi.</p>
        <form method="post" action="supplier.php" class="stack" data-delivery-form>
          <?= csrf_field() ?><input type="hidden" name="supplier_id" value="<?= (int) $d['supplier_id'] ?>"><input type="hidden" name="action" value="invoice_delivery"><input type="hidden" name="delivery_id" value="<?= (int) $d['id'] ?>">
          <?php if ($dItems): ?>
          <div class="table-wrap">
            <table class="table">
              <thead><tr><th>Sipariş</th><th>Cam</th><th class="num">Maliyet</th></tr></thead>
              <tbody>
                <?php foreach ($dItems as $it): ?>
                  <tr>
                    <td><a class="link" href="order.php?id=<?= (int) $it['order_id'] ?>" target="_blank"><?= e($it['first_name'] . ' ' . $it['last_name']) ?></a><small class="block muted"><?= order_no((int) $it['order_id']) ?></small></td>
                    <td><?= e($it['lens_type']) ?><small class="block muted"><?= $it['eye'] === 'R' ? 'Sağ' : 'Sol' ?> · SPH <?= e($it['sph']) ?><?= $it['cyl'] ? ' CYL ' . e($it['cyl']) : '' ?></small></td>
                    <td class="num"><input name="item_cost[<?= (int) $it['id'] ?>]" inputmode="decimal" placeholder="0,00" class="cost-input" data-cost-input></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot><tr><th colspan="2">Toplam</th><th class="num" data-cost-total>0,00 ₺</th></tr></tfoot>
            </table>
          </div>
          <?php else: ?>
            <p class="muted small">Bu teslimattaki camlar sistemde bulunamadı (silinmiş/değişmiş olabilir). Aşağıya fatura tutarını “Ek kalemler” olarak girebilirsiniz.</p>
          <?php endif; ?>
          <div class="extra-lines">
            <b class="extra-lines-label">Ek kalemler <small class="muted">(kargo, boyama vb. — camdan bağımsız)</small></b>
            <?php for ($i = 0; $i < 3; $i++): ?>
              <div class="grid cols-3">
                <label class="field span-2"><span>Açıklama</span><input name="extra_desc[]" placeholder="örn. Kargo"></label>
                <label class="field"><span>Tutar</span><input name="extra_amount[]" inputmode="decimal" placeholder="0,00" data-cost-input></label>
              </div>
            <?php endfor; ?>
          </div>
          <div class="grid cols-3">
            <label class="field"><span>Fatura no *</span><input name="invoice_no" required></label>
            <label class="field"><span>Fatura tarihi</span><input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>"></label>
          </div>
          <button class="btn btn-primary">Faturayı kaydet</button>
        </form>
      </section>
    <?php endforeach; ?>
  </div>
  <?php /* 4.15.1: maliyet toplamı assets/moduller.js içinde (CSP: satır içi betik yok) */ ?>
<?php endif; ?>
<?php page_end();
