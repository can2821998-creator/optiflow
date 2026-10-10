<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.30.0 — Telefon asistanı: geri aranacaklar, son aramalar, ayarlar ve telefonu bağlama.
   Konuşma mantığı app/asistan.php; Android uygulamasının uç noktası asistan.php.
   ========================================================================== */

$me = require_login();
ozellik_gereksin('telefon_asistan');

$yeniKod = '';
if (is_post()) {
    $eylem = post('eylem');
    try {
        switch ($eylem) {
            case 'arandi':
                update('asistan_aramalar', ['tamamlandi_at' => date('Y-m-d H:i:s'), 'tamamlayan' => (int) $me['id']], 'id = ? AND geri_ara = 1', [post_int('id')]);
                flash('Geri arandı olarak işaretlendi.');
                redirect('telefon-asistani.php');
            case 'ayar':
                require_super();
                setting_set('asistan_acik', isset($_POST['acik']) ? '1' : '0');
                setting_set('asistan_mesaj', isset($_POST['mesaj']) ? '1' : '0');
                setting_set('asistan_mesaj_kayitsiz', isset($_POST['mesaj_kayitsiz']) ? '1' : '0');
                audit('asistan', 'ayar', null, ['açık' => isset($_POST['acik']) ? 'evet' : 'hayır', 'sms' => isset($_POST['mesaj']) ? 'evet' : 'hayır']);
                flash('Telefon asistanı ayarları kaydedildi. Telefon en geç birkaç dakika içinde yeni ayarı alır.');
                redirect('telefon-asistani.php');
            case 'kod':
                require_super();
                $yeniKod = asistan_eslesme_kodu_uret();
                audit('asistan', 'eşleştirme kodu', null, []);
                break;
            case 'cihaz_kaldir':
                require_super();
                update('asistan_cihazlar', ['aktif' => 0], 'id = ?', [post_int('id')]);
                audit('asistan', 'cihaz kaldırıldı', post_int('id'), []);
                flash('Telefonun bağlantısı kaldırıldı. Uygulama artık arama açmaz.');
                redirect('telefon-asistani.php');
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
        redirect('telefon-asistani.php');
    }
}

$bekleyen = rows('SELECT a.*, c.first_name, c.last_name FROM asistan_aramalar a LEFT JOIN customers c ON c.id = a.customer_id
                  WHERE a.geri_ara = 1 AND a.tamamlandi_at IS NULL ORDER BY a.created_at DESC LIMIT 50');
$son = rows('SELECT a.*, c.first_name, c.last_name FROM asistan_aramalar a LEFT JOIN customers c ON c.id = a.customer_id ORDER BY a.created_at DESC LIMIT 40');
$cihazlar = rows('SELECT * FROM asistan_cihazlar WHERE aktif = 1 ORDER BY id DESC');
$bugun = (int) scalar('SELECT COUNT(*) FROM asistan_aramalar WHERE created_at >= ?', [date('Y-m-d 00:00:00')]);
$bilgi7 = (int) scalar("SELECT COUNT(*) FROM asistan_aramalar WHERE sonuc IN ('bilgi', 'mesaj') AND created_at >= ?", [date('Y-m-d H:i:s', time() - 7 * 86400)]);
$olaylar = json_decode(setting('asistan_olaylar', '[]'), true) ?: [];
$magazaId = (int) (tenant_oturum()['id'] ?? 0);
$apkVar = is_file(APP_ROOT . '/indir/asistan/OptiFlow-Asistan.apk');
$apkUrl = $apkVar ? 'indir/asistan/OptiFlow-Asistan.apk' : 'https://github.com/can2821998-creator/optiflow/releases/latest';
$kim = static fn(array $a): string => trim((string) ($a['first_name'] ?? '') . ' ' . (string) ($a['last_name'] ?? ''));
$tel = static fn(array $a): string => phone_display(normalize_phone((string) $a['numara']) ?: (string) $a['numara']) ?: 'Gizli numara';

page_start('Telefon asistanı', 'telefon-asistani');
page_header('Telefon asistanı', 'Mağaza telefonu çalarken arayanın sipariş durumu ekranda görünür; açılamayan aramaya otomatik SMS gider ve geri aranacaklar listesine düşer.', '', '', 'Atölye');
?>
<section class="stats">
  <div class="stat <?= $bekleyen ? 'tone-amber' : '' ?>"><small>Geri aranacak</small><b><?= count($bekleyen) ?></b><span>bekleyen not</span></div>
  <div class="stat"><small>Bugün</small><b><?= $bugun ?></b><span>cevapsız arama</span></div>
  <div class="stat"><small>Son 7 gün</small><b><?= $bilgi7 ?></b><span>SMS ile bilgi verildi</span></div>
  <div class="stat <?= $cihazlar ? 'tone-green' : '' ?>"><small>Telefon</small><b><?= $cihazlar ? 'Bağlı' : 'Bağlı değil' ?></b><span><?= asistan_acik_mi() ? (asistan_mesaj_acik() ? 'açık · SMS gönderir' : 'açık · SMS kapalı') : 'kapalı' ?></span></div>
</section>

<section class="card">
  <div class="card-head"><h2><?= icon('bell') ?> Geri aranacaklar</h2></div>
  <?php if (!$bekleyen): ?>
    <?= empty_state('Geri aranacak kimse yok', 'Mağaza telefonu açılamadığında arayan burada görünür ve size bildirim gelir.') ?>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Zaman</th><th>Arayan</th><th>Not</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($bekleyen as $a): $n = normalize_phone((string) $a['numara']); ?>
        <tr>
          <td><?= e(date_tr((string) $a['created_at'], true)) ?></td>
          <td><?php if ($a['customer_id']): ?><a class="link" href="customer.php?id=<?= (int) $a['customer_id'] ?>"><b><?= e($kim($a)) ?></b></a><br><?php endif; ?>
            <?php if ($n): ?><a class="link" href="tel:+<?= e($n) ?>"><?= e($tel($a)) ?></a><?php else: ?><?= e($tel($a)) ?><?php endif; ?></td>
          <td><?= e((string) ($a['not_metni'] ?: '—')) ?><br><small class="muted"><?= e(trim((string) $a['ozet'], ' ·')) ?></small></td>
          <td class="row-actions"><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="eylem" value="arandi"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>"><button class="btn btn-sm btn-primary"><?= icon('check') ?> Arandı</button></form></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php endif; ?>
</section>

<div class="grid cols-2">
  <section class="card">
    <div class="card-head"><h2><?= icon('chat') ?> Son cevapsız aramalar</h2><small class="muted"><?= ASISTAN_KAYIT_GUN ?> gün saklanır</small></div>
    <?php if (!$son): ?>
      <?= empty_state('Henüz kayıt yok', 'Telefon bağlanınca açılamayan aramalar burada listelenir.') ?>
    <?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Zaman</th><th>Arayan</th><th>Sonuç</th></tr></thead>
        <tbody>
        <?php foreach ($son as $a): [$etiket, $ton] = asistan_sonuc_etiketi((string) $a['sonuc']); ?>
          <tr><td><?= e(date_tr((string) $a['created_at'], true)) ?><?= $a['sure'] ? '<br><small class="muted">' . (int) $a['sure'] . ' sn</small>' : '' ?></td>
            <td><?= $a['customer_id'] ? '<b>' . e($kim($a)) . '</b><br>' : '' ?><small class="muted"><?= e($tel($a)) ?></small></td>
            <td><span class="badge tone-<?= e($ton) ?>"><?= e($etiket) ?></span><br><small class="muted"><?= e(trim((string) $a['ozet'], ' ·')) ?></small></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>

  <div class="stack">
    <?php if (is_super()): ?>
    <section class="card">
      <div class="card-head"><h2><?= icon('settings') ?> Ayarlar</h2></div>
      <form method="post" class="stack" data-guard>
        <?= csrf_field() ?><input type="hidden" name="eylem" value="ayar">
        <label class="check-line"><input type="checkbox" name="acik" <?= asistan_acik_mi() ? 'checked' : '' ?>> Telefon asistanı açık</label>
        <label class="check-line"><input type="checkbox" name="mesaj" <?= asistan_mesaj_acik() ? 'checked' : '' ?>> Açılamayan aramaya SMS gönder (kayıtlı müşteriye sipariş durumu)</label>
        <label class="check-line"><input type="checkbox" name="mesaj_kayitsiz" <?= asistan_mesaj_kayitsiz() ? 'checked' : '' ?>> Kayıtlı olmayan cep numaralarına da "sizi geri arayacağız" SMS'i gönder</label>
        <small class="muted">SMS mağaza telefonunun kendi hattından gider (tarifenizdeki SMS). Aynı numaraya <?= ASISTAN_MESAJ_ARALIK_SAAT ?> saatte en fazla bir SMS. Örnek: "<?= e(asistan_mesaj_metni(null)) ?>"</small>
        <div><button class="btn btn-primary">Kaydet</button></div>
      </form>
    </section>

    <section class="card" id="bagla">
      <div class="card-head"><h2><?= icon('lock') ?> Telefonu bağla</h2></div>
      <?php if ($yeniKod !== ''): $qrUrl = rtrim(app_base_url(), '/') . '/asistan.php?' . http_build_query(['eylem' => 'qr', 'm' => $magazaId, 'k' => $yeniKod]); ?>
        <div class="split" style="align-items:center;gap:16px">
          <div style="width:170px;flex:none;background:#fff;padding:8px;border-radius:12px"><?= qr_svg($qrUrl, 154, 'M') ?></div>
          <div class="stack" style="gap:6px">
            <p>Mağaza telefonunun kamerasıyla karekodu okutun, açılan sayfada <b>Uygulamayı bağla</b>ya dokunun.</p>
            <p class="muted small">Ya da uygulamada "Kodla bağla": mağaza no <b><?= $magazaId ?></b>, kod <b style="font-size:1.3em;letter-spacing:3px"><?= e($yeniKod) ?></b> · <?= ASISTAN_ESLESME_DK ?> dakika geçerli, tek kullanımlık.</p>
          </div>
        </div>
      <?php else: ?>
        <ol class="small" style="margin:0 0 12px;padding-left:18px">
          <li>Mağaza telefonuna <a class="link" href="<?= e($apkUrl) ?>"<?= $apkVar ? ' download' : ' target="_blank" rel="noopener"' ?>>OptiFlow Asistan uygulamasını indirin</a> ve kurun (bilinmeyen kaynaklara izin isteyebilir).</li>
          <li>Uygulamadaki izinleri verin: telefon, SMS, arama tanıma ve "diğer uygulamaların üzerinde gösterme".</li>
          <li>Aşağıdaki düğmeyle kod alın ve karekodu telefonla okutun.</li>
        </ol>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="eylem" value="kod"><button class="btn btn-primary"><?= icon('plus') ?> Bağlama kodu al</button></form>
      <?php endif; ?>
      <?php foreach ($cihazlar as $c): ?>
        <div class="split" style="margin-top:12px;padding-top:10px;border-top:1px solid var(--line)">
          <div><b><?= e((string) $c['ad']) ?></b><br><small class="muted">Bağlandı <?= e(date_tr((string) $c['created_at'])) ?> · son görülme <?= e(date_tr((string) $c['son_gorulme'], true)) ?><?= $c['son_surum'] ? ' · sürüm ' . e((string) $c['son_surum']) : '' ?></small></div>
          <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="eylem" value="cihaz_kaldir"><input type="hidden" name="id" value="<?= (int) $c['id'] ?>"><button class="btn btn-sm btn-ghost"><?= icon('x') ?> Bağlantıyı kaldır</button></form>
        </div>
      <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head"><h2><?= icon('shield') ?> Nasıl çalışır?</h2></div>
      <ul class="small" style="margin:0;padding-left:18px">
        <li><b>Telefon çalarken</b> ekranın üstünde kart: arayanın adı, siparişlerinin durumu (camlar geldi mi, atölyede mi, hazır mı, tahmini teslim) ve kalan tutar. Kim açarsa cevabı bilir.</li>
        <li><b>Açılamazsa</b> kayıtlı müşteriye mağaza hattından SMS: sipariş durumu, tahmini teslim ve takip bağlantısı. SMS'te tutar ve bakiye <b>yazmaz</b>.</li>
        <li>Her cevapsız arama <b>Geri aranacaklar</b>a düşer ve bildirim gelir; aynı kişi tekrar ararsa aynı kayda eklenir.</li>
        <li>Kayıtlar <?= ASISTAN_KAYIT_GUN ?> gün saklanır.</li>
      </ul>
    </section>

    <?php if ($olaylar && is_super()): ?>
    <details class="card">
      <summary class="linkish">Uygulama olayları (tanılama)</summary>
      <ul class="small mono" style="margin:8px 0 0;padding-left:18px">
        <?php foreach ($olaylar as [$z, $m]): ?><li><?= e((string) $z) ?> · <?= e((string) $m) ?></li><?php endforeach; ?>
      </ul>
    </details>
    <?php endif; ?>
  </div>
</div>
<?php page_end();
