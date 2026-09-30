<?php
/* Müşteri kartındaki "Aile" kartı. Değişkenler app/pages/customer.php'den gelir:
   $aile (family_overview çıktısı), $aileAra (arama sonuçları), $aramaMetni, $id */
?><section class="card">
  <div class="card-head"><h2><?= icon('user') ?> Aile</h2><small class="muted"><?= e($aile['uyeSayisi']) ?> kişi</small></div>
  <ul class="kv">
    <?php foreach ($aile['uyeler'] as $u): ?>
      <li>
        <span>
          <?php if ($u['ben']): ?><b><?= e($u['ad']) ?></b> <small class="muted">(bu kişi)</small>
          <?php else: ?><a class="link" href="customer.php?id=<?= e($u['id']) ?>"><?= e($u['ad']) ?></a><?php endif; ?>
          <br><small class="muted"><?= e($u['alt']) ?></small>
          <?php if ($u['oneri']): ?><br><small class="muted">Aynı telefonla kayıtlı — aileye eklemek ister misiniz?</small><?php endif; ?>
        </span>
        <b style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end">
          <?php foreach ($u['rozetler'] as $r): ?><span class="badge sm tone-amber"><?= e($r) ?></span><?php endforeach; ?>
          <?php if ($u['oneri']): ?>
            <form method="post" class="inline" style="display:flex;gap:6px">
              <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= e($id) ?>"><input type="hidden" name="action" value="aile_ekle"><input type="hidden" name="member_id" value="<?= e($u['id']) ?>">
              <select name="relation" aria-label="Aile rolü">
                <option value="">Rol</option>
                <?php foreach ($aile['iliskiler'] as $rl): ?><option value="<?= e($rl) ?>"><?= e($rl) ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-sm">Ekle</button>
            </form>
          <?php elseif (!$u['ben']): ?>
            <form method="post" class="inline">
              <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= e($id) ?>"><input type="hidden" name="action" value="aile_cikar"><input type="hidden" name="member_id" value="<?= e($u['id']) ?>">
              <button class="btn btn-sm" title="Aileden çıkar" aria-label="Aileden çıkar">×</button>
            </form>
          <?php endif; ?>
        </b>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($aile['wa'] !== ''): ?>
    <div class="push-note" style="margin-top:12px"><b>Ailede zamanı gelenler var</b><p><?= e($aile['vadesi_metin']) ?></p></div>
    <div class="btn-row" style="margin-top:10px">
      <a class="btn btn-wa btn-sm" href="<?= e($aile['wa']) ?>" target="_blank" rel="noopener"><?= icon('chat') ?> Aileye tek mesaj</a>
      <form method="post" class="inline">
        <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= e($id) ?>"><input type="hidden" name="action" value="aile_bildirildi">
        <button class="btn btn-sm" title="Bu kişileri Hatırlatmalar listesinden düşürür">Haber verildi</button>
      </form>
    </div>
  <?php endif; ?>

  <form method="get" class="inline" style="margin-top:14px;display:flex;gap:8px">
    <input type="hidden" name="id" value="<?= e($id) ?>">
    <input name="aile_ara" value="<?= e($aramaMetni) ?>" placeholder="Aileye eklemek için isim / telefon ara" aria-label="Aileye kişi ara">
    <button class="btn btn-sm">Ara</button>
  </form>
  <?php if ($aramaMetni !== '' && !$aileAra): ?><p class="hint">Sonuç bulunamadı.</p><?php endif; ?>
  <?php if ($aileAra): ?>
    <ul class="kv" style="margin-top:8px">
      <?php foreach ($aileAra as $s): ?>
        <li>
          <span><?= e($s['first_name'] . ' ' . $s['last_name']) ?><br><small class="muted"><?= e($s['phone']) ?></small></span>
          <b>
            <form method="post" class="inline" style="display:flex;gap:6px">
              <?= csrf_field() ?><input type="hidden" name="customer_id" value="<?= e($id) ?>"><input type="hidden" name="action" value="aile_ekle"><input type="hidden" name="member_id" value="<?= e($s['id']) ?>">
              <select name="relation" aria-label="Aile rolü">
                <option value="">Rol</option>
                <?php foreach ($aile['iliskiler'] as $rl): ?><option value="<?= e($rl) ?>"><?= e($rl) ?></option><?php endforeach; ?>
              </select>
              <button class="btn btn-sm">Ekle</button>
            </form>
          </b>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
