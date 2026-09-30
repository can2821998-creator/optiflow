<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.12.0 — WhatsApp mesaj kuyruğu
   Link kanalında personel her mesajı tek tıkla WhatsApp'ta açıp gönderir;
   Cloud kanalında mesajlar arka planda kendiliğinden gider, burada izlenir.
   ========================================================================== */

$me = require_login();
ozellik_gereksin('whatsapp');

$sekmeler = ['bekleyen' => 'Gönderilecek', 'gonderilen' => 'Gönderilenler', 'tumu' => 'Tümü'];
$sekme = query('s', 'bekleyen');
if (!isset($sekmeler[$sekme])) {
    $sekme = 'bekleyen';
}
$kanal = wa_kanal();

if (is_post()) {
    $eylem = post('eylem');
    $id = post_int('id');
    $ajax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    if ($eylem === 'gonderildi' && $id > 0) {
        $ok = wa_gonderildi_isaretle($id);
        if ($ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $ok]);
            exit;
        }
        flash($ok ? 'Gönderildi olarak işaretlendi.' : 'Mesaj zaten işlenmiş.');
    } elseif ($eylem === 'iptal' && $id > 0) {
        wa_iptal($id);
        flash('Mesaj iptal edildi.');
    } elseif ($eylem === 'tekrar' && $id > 0) {
        q("UPDATE wa_mesajlar SET durum = 'bekliyor', deneme = 0, son_hata = NULL, planlanan = NOW() WHERE id = ? AND durum = 'hata'", [$id]);
        flash('Mesaj yeniden sıraya alındı.');
    } elseif ($eylem === 'simdi' && $kanal === 'cloud') {
        $n = wa_kuyrugu_isle(50);
        flash($n . ' mesaj gönderildi.' . ($n === 0 ? ' Hata alan mesajların ayrıntısı listede.' : ''));
    } elseif ($eylem === 'izin' && post_int('customer_id') > 0) {
        $v = post('izin');
        wa_izin_kaydet(post_int('customer_id'), $v === '1' ? true : ($v === '0' ? false : null), 'personel');
        flash('Mesaj izni güncellendi.');
    }
    redirect('mesajlar.php?s=' . urlencode(post('s') ?: $sekme));
}

$kosul = match ($sekme) {
    'gonderilen' => "m.durum = 'gonderildi'",
    'tumu'       => '1 = 1',
    default      => "m.durum IN ('bekliyor','hata','gonderiliyor')",
};
$liste = rows(
    "SELECT m.*, c.first_name, c.last_name, c.wa_izin
       FROM wa_mesajlar m LEFT JOIN customers c ON c.id = m.customer_id
      WHERE $kosul
      ORDER BY " . ($sekme === 'bekleyen' ? 'm.planlanan, m.id' : 'm.id DESC') . "
      LIMIT 300"
);
$sayilar = row(
    "SELECT SUM(durum IN ('bekliyor','hata','gonderiliyor')) AS bekleyen,
            SUM(durum = 'gonderildi' AND gonderilme >= CURDATE()) AS bugun,
            SUM(durum = 'hata') AS hata
       FROM wa_mesajlar"
) ?: ['bekleyen' => 0, 'bugun' => 0, 'hata' => 0];
$olaylar = wa_olaylar();

page_start('WhatsApp mesajları', 'mesajlar');
page_header(
    'WhatsApp mesajları',
    $kanal === 'cloud'
        ? 'Mesajlar WhatsApp Business Platform üzerinden kendiliğinden gönderilir (' . gorev_saat_metni() . ').'
        : 'Mesajlar WhatsApp\'ta hazır metinle açılır; siz "Gönder"e basarsınız.',
    is_super() ? '<a class="btn btn-sm" href="settings.php?tab=whatsapp">' . icon('settings') . ' Ayarlar</a>' : '',
    '',
    'Müşteri ilişkileri'
);
?>

<section class="stats">
  <div class="stat <?= (int) $sayilar['bekleyen'] > 0 ? 'tone-amber' : '' ?>"><small>Gönderilecek</small><b><?= (int) $sayilar['bekleyen'] ?></b><span>kuyrukta</span></div>
  <div class="stat tone-green"><small>Bugün gönderilen</small><b><?= (int) $sayilar['bugun'] ?></b><span>mesaj</span></div>
  <div class="stat <?= (int) $sayilar['hata'] > 0 ? 'tone-red' : '' ?>"><small>Hata</small><b><?= (int) $sayilar['hata'] ?></b><span>tekrar denenecek</span></div>
  <div class="stat"><small>Kanal</small><b><?= $kanal === 'cloud' ? 'Otomatik' : 'Tek tık' ?></b><span><?= $kanal === 'cloud' ? 'Business Platform' : 'WhatsApp uygulaması' ?></span></div>
</section>

<?php if ($kanal === 'cloud' && !wa_cloud_hazir_mi()): ?>
  <div class="alert alert-error">WhatsApp Business Platform bilgileri eksik; mesajlar gönderilemez. <?= is_super() ? '<a class="link" href="settings.php?tab=whatsapp">Ayarları tamamlayın</a>.' : 'Yöneticinize haber verin.' ?></div>
<?php endif; ?>

<nav class="tabs" aria-label="Mesaj filtresi">
  <?php foreach ($sekmeler as $k => $ad): ?>
    <a class="tab <?= $sekme === $k ? 'active' : '' ?>" href="mesajlar.php?s=<?= e($k) ?>"><?= e($ad) ?><?php if ($k === 'bekleyen'): ?><em><?= (int) $sayilar['bekleyen'] ?></em><?php endif; ?></a>
  <?php endforeach; ?>
</nav>

<section class="card">
  <div class="card-head">
    <h2><?= icon('chat') ?> <?= e($sekmeler[$sekme]) ?></h2>
    <?php if ($kanal === 'cloud' && $sekme === 'bekleyen' && $liste): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="simdi"><button class="btn btn-sm btn-primary">Şimdi gönder</button></form>
    <?php endif; ?>
  </div>
  <?php if (!$liste): ?>
    <?= empty_state($sekme === 'bekleyen' ? 'Gönderilecek mesaj yok' : 'Kayıt yok', 'Gözlük hazır olunca, ödeme linki oluşturulunca ya da hatırlatma listesinden eklenince mesajlar burada belirir.') ?>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table rem-table wa-tablo">
        <thead><tr><th>Müşteri</th><th>Mesaj</th><th class="hide-sm">Durum</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($liste as $m):
          [$dEt, $dTon] = wa_durum_etiketi((string) $m['durum']);
          $bekliyor = in_array($m['durum'], ['bekliyor', 'hata'], true);
        ?>
          <tr data-wa-satir="<?= (int) $m['id'] ?>">
            <td>
              <?php if ($m['customer_id']): ?>
                <a class="link" href="customer.php?id=<?= (int) $m['customer_id'] ?>"><b><?= e(trim($m['first_name'] . ' ' . $m['last_name'])) ?></b></a>
              <?php else: ?><b>—</b><?php endif; ?>
              <small class="block muted"><?= e(phone_display('0' . substr((string) $m['telefon'], 2))) ?></small>
              <?php if ($m['order_id']): ?><small class="block"><a class="link" href="order.php?id=<?= (int) $m['order_id'] ?>"><?= e(order_no((int) $m['order_id'])) ?></a></small><?php endif; ?>
            </td>
            <td>
              <b><?= e($olaylar[$m['olay']]['ad'] ?? $m['olay']) ?></b>
              <small class="block muted wa-metin"><?= nl2br(e(mb_strimwidth((string) $m['metin'], 0, 220, '…'))) ?></small>
              <?php if ($m['son_hata']): ?><small class="block text-danger"><?= e((string) $m['son_hata']) ?></small><?php endif; ?>
            </td>
            <td class="hide-sm">
              <span class="badge tone-<?= e($dTon) ?>"><?= e($dEt) ?></span>
              <small class="block muted"><?= e(date_tr($m['gonderilme'] ?: $m['planlanan'], true)) ?></small>
            </td>
            <td class="row-actions rem-actions">
              <?php if ($bekliyor && $m['kanal'] === 'link'): ?>
                <a class="btn btn-sm btn-wa" href="<?= e(wa_mesaj_linki($m)) ?>" target="_blank" rel="noopener" data-wa-id="<?= (int) $m['id'] ?>"><?= icon('chat') ?> WhatsApp'ta aç</a>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="gonderildi"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="s" value="<?= e($sekme) ?>">
                  <button class="btn btn-sm" title="Gönderdim olarak işaretle"><?= icon('check') ?></button></form>
              <?php elseif ($m['durum'] === 'hata'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="tekrar"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="s" value="<?= e($sekme) ?>">
                  <button class="btn btn-sm">Tekrar dene</button></form>
              <?php endif; ?>
              <?php if ($bekliyor): ?>
                <form method="post" data-confirm="Mesaj iptal edilsin mi?"><?= csrf_field() ?><input type="hidden" name="eylem" value="iptal"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="s" value="<?= e($sekme) ?>">
                  <button class="btn btn-sm btn-ghost" title="İptal"><?= icon('x') ?></button></form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card-head"><h2>Mesaj izni</h2></div>
  <ul class="kv">
    <li><span><b>İzin gerekmeyenler:</b> gözlük hazır, teslim hatırlatması, ödeme linki — müşterinin kendi siparişiyle ilgili bilgilendirme.</span></li>
    <li><span><b>İzin gerekenler:</b> yenileme, SGK hakkı, kontakt lens ve yorum isteği. Bunlar yalnızca müşteri kartında izni “Verdi” olan kişilere gider.</span></li>
    <li><span>İzni müşteri kartından kaydedin; kim, ne zaman, hangi kaynaktan kaydetti işlem geçmişine yazılır.</span></li>
  </ul>
</section>

<?php page_end(['mesajlar.js']);
