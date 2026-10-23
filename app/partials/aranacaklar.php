<?php
/* 4.47.0 — Bugün aranacaklar listesi (hatirlatma.php?tur=bugun). Girdi: $liste (aranacak_listesi()).
   Satır = kâğıt listedeki bir satır: kim, neden, telefon, kaçıncı deneme; sonuç tek dokunuşla. */
declare(strict_types=1);

$turTon = ['haber' => 'green', 'teslim' => 'amber', 'manuel' => 'blue', 'bakiye' => 'red'];
$yarin = date('Y-m-d', strtotime('+1 day'));
?>
<?php if (!$liste): ?>
  <?= empty_state('Bugün aranacak kimse yok', 'Gözlüğü hazırlanan, sözü geçen ya da planlı araması gelen müşteri burada kendiliğinden belirir.') ?>
<?php else: ?>
  <ol class="arama-listesi">
    <?php foreach ($liste as $i => $s):
      $tel = preg_replace('/\D/', '', (string) $s['phone']);
      $wa = wa_url($s['phone'] ?? '', aranacak_mesaj($s));
      $gizli = csrf_field() . '<input type="hidden" name="eylem" value="arama">'
          . '<input type="hidden" name="arama_tur" value="' . e($s['tur']) . '">'
          . '<input type="hidden" name="customer_id" value="' . (int) $s['customer_id'] . '">'
          . '<input type="hidden" name="order_id" value="' . (int) ($s['order_id'] ?? 0) . '">'
          . '<input type="hidden" name="reminder_id" value="' . (int) ($s['reminder_id'] ?? 0) . '">';
      $son = $s['son_sonuc']; ?>
      <li class="arama-satir tur-<?= e($s['tur']) ?>">
        <span class="arama-no"><?= sprintf('%02d', $i + 1) ?></span>
        <div class="arama-kim">
          <span class="badge sm tone-<?= $turTon[$s['tur']] ?>"><?= e(ARANACAK_TURLERI[$s['tur']]) ?></span>
          <a class="arama-ad" href="customer.php?id=<?= (int) $s['customer_id'] ?>"><?= e($s['ad']) ?></a>
          <?php if ($tel !== ''): ?><a class="arama-tel" href="tel:<?= e($tel) ?>"><?= e(phone_display($s['phone'])) ?></a><?php else: ?><span class="arama-tel muted">telefon yok</span><?php endif; ?>
        </div>
        <div class="arama-neden">
          <?php if (!empty($s['order_id'])): ?><a class="link mono" href="order.php?id=<?= (int) $s['order_id'] ?>"><?= e($s['baslik']) ?></a><?php else: ?><b class="mono"><?= e($s['baslik']) ?></b><?php endif; ?>
          <small><?= e($s['detay']) ?><?php if ($s['tur'] === 'bakiye' && can_see_amounts()): ?> · kalan <?= e(money($s['kalan'])) ?><?php endif; ?></small>
          <?php if ($s['deneme'] > 0 || $son): ?>
            <small class="arama-deneme <?= $s['deneme'] >= 3 ? 'cok' : '' ?>">
              <?= $s['deneme'] > 0 ? ($s['deneme'] + 1) . '. deneme' : '' ?><?= $son ? ($s['deneme'] > 0 ? ' · ' : '') . 'son: ' . e(mb_strtolower(ARANACAK_SONUCLARI[$son['sonuc']] ?? $son['sonuc'])) . ' ' . e(date('d.m H:i', strtotime((string) $son['created_at']))) . ($son['full_name'] ? ' · ' . e((string) $son['full_name']) : '') : '' ?>
            </small>
          <?php endif; ?>
        </div>
        <div class="arama-eylem">
          <?php if ($wa !== ''): ?>
            <form method="post" target="_blank" rel="noopener"><?= $gizli ?><input type="hidden" name="sonuc" value="whatsapp"><input type="hidden" name="wa" value="<?= e($wa) ?>">
              <button class="btn btn-sm btn-wa" title="<?= e(aranacak_mesaj($s)) ?>"><?= icon('chat') ?> WhatsApp</button></form>
          <?php endif; ?>
          <form method="post"><?= $gizli ?><input type="hidden" name="sonuc" value="ulasildi"><button class="btn btn-sm btn-primary"><?= icon('check') ?> Ulaşıldı</button></form>
          <form method="post"><?= $gizli ?><input type="hidden" name="sonuc" value="acmadi"><button class="btn btn-sm" title="Yarın yeniden listeye düşer">Açmadı</button></form>
          <details class="rem-more">
            <summary aria-label="Ertele ya da not">⋯</summary>
            <div class="rem-menu">
              <form method="post" class="stack" style="gap:8px"><?= $gizli ?><input type="hidden" name="sonuc" value="ertele">
                <label class="field"><span>Ne zaman tekrar</span><input type="date" name="tarih" min="<?= e($yarin) ?>" value="<?= e(date('Y-m-d', strtotime('+3 days'))) ?>" required></label>
                <label class="field"><span>Not</span><input name="not" maxlength="255" placeholder="örn. hafta sonu gelecek"></label>
                <button class="btn btn-sm">Ertele</button>
              </form>
            </div>
          </details>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
  <div class="list-foot"><span class="muted"><?= count($liste) ?> kişi · "Açmadı" yarın yeniden listeye düşer; "Ulaşıldı" hazır gözlükte atölyedeki "Müşteriye haber" adımını da işaretler.</span></div>
<?php endif; ?>
