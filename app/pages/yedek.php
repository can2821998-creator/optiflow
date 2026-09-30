<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$me = require_super();

/* ==========================================================================
   Yedekleme ve günün özeti
   ========================================================================== */

$klasor = APP_ROOT . '/storage/backups';

/* Otomatik günlük yedek modülü: yüklenemezse bu bölüm gizlenir, sayfanın kalanı çalışır */
$otoYuklu = false;
try {
    require_once dirname(__DIR__) . '/autobackup.php';
    $otoYuklu = function_exists('autobackup_run');
} catch (Throwable $e) {
    $otoYuklu = false;
}

if (is_post()) {
    csrf_check();
    $eylem = post('eylem');

    if ($eylem === 'yedek') {
        @set_time_limit(600);
        backup_cleanup($klasor);
        try {
            $sonuc = backup_write($klasor);
        } catch (Throwable $e) {
            app_log('Yedek hatası: ' . $e->getMessage());
            flash('Yedek alınamadı: ' . $e->getMessage(), 'error');
            redirect('yedek.php');
        }
        audit('backup', 'settings', null, ['tablo' => $sonuc['tablo'], 'satır' => $sonuc['satir'], 'boyut' => $sonuc['boyut']]);

        $ad = basename($sonuc['dosya']);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $ad . '"');
        header('Content-Length: ' . $sonuc['boyut']);
        header('Cache-Control: no-store');
        readfile($sonuc['dosya']);
        @unlink($sonuc['dosya']);
        exit;
    }

    if ($otoYuklu && $eylem === 'oto_ac') {
        setting_set('auto_backup', '1');
        setting_set('auto_backup_keep', (string) max(3, min(60, (int) post('saklanacak', '14'))));
        flash('Otomatik günlük yedek açıldı. İlk yedek bugün sisteme ilk girişte alınır; hemen denemek için "Şimdi çalıştır"a basın.');
        redirect('yedek.php');
    }

    if ($otoYuklu && $eylem === 'oto_kapat') {
        setting_set('auto_backup', '0');
        flash('Otomatik günlük yedek kapatıldı. Mevcut dosyalar silinmedi.');
        redirect('yedek.php');
    }

    if ($otoYuklu && $eylem === 'oto_simdi') {
        $r = autobackup_run();
        if ($r['ok']) {
            flash('Otomatik yedek alındı ve doğrulandı (' . number_format($r['boyut'] / 1048576, 2, ',', '.') . ' MB, ' . (int) $r['satir'] . ' satır).');
        } else {
            flash('Otomatik yedek alınamadı: ' . $r['mesaj'], 'error');
        }
        redirect('yedek.php');
    }

    if ($otoYuklu && $eylem === 'oto_indir') {
        $ad = post('dosya');
        $yol = autobackup_dir() . '/' . $ad;
        if (!autobackup_ad_ok($ad) || !is_file($yol)) {
            flash('Yedek dosyası bulunamadı.', 'error');
            redirect('yedek.php');
        }
        $bayt = (int) filesize($yol);
        audit('backup', 'settings', null, ['boyut' => $bayt, 'not' => 'otomatik yedek indirildi']);
        setting_set('last_backup_at', date('Y-m-d H:i:s'));   // bilgisayara indirildi: dış kopya sayılır
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $ad . '"');
        header('Content-Length: ' . $bayt);
        header('Cache-Control: no-store');
        readfile($yol);
        exit;
    }

    if ($eylem === 'ozet') {
        $sonuc = daily_summary_send();
        if (($sonuc['sent'] ?? 0) > 0) {
            flash('Özet ' . (int) $sonuc['sent'] . ' cihaza gönderildi: ' . ($sonuc['ozet'] ?? ''));
        } else {
            flash('Özet gönderilemedi. ' . ($sonuc['reason'] ?? 'Kayıtlı cihaz yok — profil sayfasından bildirimi açın.'), 'error');
        }
        redirect('yedek.php');
    }
}

$sonYedek = backup_last_at();
$yas = backup_age_days();
$gecikti = backup_overdue();
$ozet = daily_summary_data();
[$ozetBaslik, $ozetGovde] = daily_summary_text($ozet);
$gz = function_exists('gzopen');

$otoAcik   = $otoYuklu && setting('auto_backup', '0') === '1';
$otoListe  = $otoYuklu ? autobackup_list() : [];
$otoSon    = setting('auto_backup_last_at', '');
$otoHata   = setting('auto_backup_last_error', '');
$otoKeep   = $otoYuklu ? autobackup_keep() : 14;
$otoToplam = 0;
foreach ($otoListe as $o) {
    $otoToplam += (int) $o['boyut'];
}
$otoEski = $otoAcik && $otoSon !== '' && (time() - (int) strtotime($otoSon)) > 3 * 86400;

/* Veritabanı büyüklüğü — yedek süresi hakkında fikir verir */
$boyut = row(
    "SELECT COALESCE(SUM(data_length + index_length), 0) AS bayt, COUNT(*) AS tablo
       FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
) ?: ['bayt' => 0, 'tablo' => 0];
$mb = (float) $boyut['bayt'] / 1048576;

page_start('Yedekleme', 'yedek');
page_header('Yedekleme ve günün özeti', 'Verinin tamamını indirin, akşam özetini ayarlayın', '', '', 'Yönetim');
?>

<?php if ($otoYuklu && $otoHata !== ''): ?>
  <div class="alert alert-error">
    <span><b>Son otomatik yedek başarısız oldu.</b> <?= e($otoHata) ?></span>
  </div>
<?php elseif ($otoEski): ?>
  <div class="alert alert-warn">
    <span><b>Otomatik yedek 3 günden uzun süredir alınmadı.</b> Sisteme bu süredir girilmemiş olabilir; girince yedek alınır.</span>
  </div>
<?php endif; ?>

<?php if ($gecikti): ?>
  <div class="alert alert-warn">
    <span><b><?= $sonYedek === '' ? 'Hiç yedek alınmamış.' : 'Son yedeğin üzerinden ' . (int) $yas . ' gün geçti.' ?></b>
    Hosting'de bir sorun olursa müşteri geçmişiniz buna bakar — aşağıdaki düğmeyle bir dakikada indirin.</span>
  </div>
<?php endif; ?>

<div class="split">
  <div class="split-main">
    <section class="card">
      <div class="card-head">
        <h2><?= icon('download') ?> Veritabanı yedeği</h2>
        <small class="muted"><?= number_format($mb, 1, ',', '.') ?> MB · <?= (int) $boyut['tablo'] ?> tablo</small>
      </div>
      <p class="muted">
        Bütün tablolar (müşteriler, siparişler, reçeteler, tahsilatlar, stok, ayarlar) tek bir
        <code>.sql<?= $gz ? '.gz' : '' ?></code> dosyasına yazılır ve bilgisayarınıza iner.
        Dosya sunucuda bırakılmaz. Geri yüklemek gerekirse hosting panelindeki
        phpMyAdmin → <b>İçe aktar</b> ile bu dosyayı yüklemeniz yeterli.
      </p>
      <ul class="kv">
        <li><span>Son yedek</span><b><?= $sonYedek !== '' ? e(date_tr($sonYedek, true)) . ' · ' . (int) $yas . ' gün önce' : 'Hiç alınmadı' ?></b></li>
        <li><span>Uyarı eşiği</span><b><?= (int) setting('backup_warn_days', '7') ?> gün</b></li>
        <li><span>Sıkıştırma</span><b><?= $gz ? 'gzip (küçük dosya)' : 'düz .sql' ?></b></li>
      </ul>
      <form method="post" style="margin-top:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="yedek">
        <button class="btn btn-primary btn-lg"><?= icon('download') ?> Yedek al ve indir</button>
      </form>
      <p class="hint">Büyük veritabanlarında indirme birkaç saniye sürebilir; sayfayı kapatmayın.</p>
    </section>

    <?php if ($otoYuklu): ?>
    <section class="card">
      <div class="card-head">
        <h2><?= icon('history') ?> Otomatik günlük yedek</h2>
        <small class="muted"><?= $otoAcik ? 'Açık' : 'Kapalı' ?></small>
      </div>
      <p class="muted">
        Açıkken günün ilk girişinde, sayfa açıldıktan sonra arka planda veritabanı yedeği alınır;
        kimse beklemez. Son <b><?= (int) $otoKeep ?></b> yedek sunucuda saklanır, eskileri otomatik silinir.
        Yanlış bir güncelleme ya da yanlışlıkla silinen kayıt olursa dünkü hâle dönebilirsiniz.
      </p>
      <div class="alert alert-warn" style="margin-top:12px">
        <span><b>Bu kopya aynı sunucuda durur.</b> Sunucunun kendisi kaybolursa onunla birlikte gider.
        Bu yüzden ayda bir "Yedek al ve indir" ile bilgisayarınıza da alın.</span>
      </div>
      <ul class="kv">
        <li><span>Son otomatik yedek</span><b><?= $otoSon !== '' ? e(date_tr($otoSon, true)) : 'Henüz alınmadı' ?></b></li>
        <li><span>Saklanan dosya</span><b><?= count($otoListe) ?> · <?= number_format($otoToplam / 1048576, 1, ',', '.') ?> MB</b></li>
      </ul>
      <div class="btn-row" style="margin-top:14px">
        <?php if (!$otoAcik): ?>
          <form method="post" class="btn-row">
            <?= csrf_field() ?>
            <input type="hidden" name="eylem" value="oto_ac">
            <label class="muted small">Kaç yedek saklansın?
              <input type="number" name="saklanacak" min="3" max="60" value="<?= (int) $otoKeep ?>" style="width:5em">
            </label>
            <button class="btn btn-primary">Otomatik yedeği aç</button>
          </form>
        <?php else: ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="eylem" value="oto_simdi">
            <button class="btn btn-primary">Şimdi çalıştır</button>
          </form>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="eylem" value="oto_kapat">
            <button class="btn">Kapat</button>
          </form>
        <?php endif; ?>
      </div>
      <?php if ($otoListe): ?>
        <ul class="kv" style="margin-top:14px">
          <?php foreach ($otoListe as $o): ?>
            <li>
              <span><?= e(date('d.m.Y H:i', (int) $o['zaman'])) ?> · <?= number_format(((int) $o['boyut']) / 1048576, 2, ',', '.') ?> MB</span>
              <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="eylem" value="oto_indir">
                <input type="hidden" name="dosya" value="<?= e($o['ad']) ?>">
                <button class="btn btn-sm"><?= icon('download') ?> İndir</button>
              </form>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head"><h2>Yedeği nereye saklamalı?</h2></div>
      <ul class="kv">
        <li><span>Ayda bir indirip <b>bilgisayarınızda + bir bulut klasöründe</b> (Drive, iCloud) tutun. Tek kopya yedek sayılmaz.</span></li>
        <li><span>Dosyanın içinde <b>müşteri adları, telefonları ve reçeteleri</b> vardır; kişisel veridir, paylaşmayın, şifresiz ortak klasöre koymayın.</span></li>
        <li><span>Hosting panelinizde otomatik yedek varsa bu onun yerine değil, <b>yanına</b> geçer.</span></li>
      </ul>
    </section>
  </div>

  <aside class="split-side">
    <section class="card">
      <div class="card-head"><h2><?= icon('bell') ?> Günün özeti</h2></div>
      <p class="muted small">
        <?= setting('daily_summary', '0') === '1'
            ? 'Açık — her gün saat ' . (int) setting('daily_summary_hour', '19') . ':00\'dan sonra sisteme ilk girişte gönderilir.'
            : 'Kapalı. Ayarlar › Genel bölümünden açabilirsiniz.' ?>
      </p>
      <div class="push-note">
        <b><?= e($ozetBaslik) ?></b>
        <p><?= e($ozetGovde) ?></p>
      </div>
      <ul class="kv" style="margin-top:12px">
        <li><span>Bugün</span><b><?= (int) $ozet['siparis'] ?> sipariş · <?= e(money($ozet['ciro'])) ?></b></li>
        <li><span>Tahsilat</span><b><?= e(money($ozet['tahsilat'])) ?></b></li>
        <li><span>Teslim edilen</span><b><?= (int) $ozet['teslim'] ?></b></li>
        <li><span>Teslim bekleyen</span><b><?= (int) $ozet['hazir'] ?></b></li>
        <li><span>Atölyede</span><b><?= (int) $ozet['atolyede'] ?></b></li>
        <li><span>Eksik cam</span><b><?= (int) $ozet['eksik_cam'] ?></b></li>
      </ul>
      <form method="post" style="margin-top:14px">
        <?= csrf_field() ?>
        <input type="hidden" name="eylem" value="ozet">
        <button class="btn"><?= icon('bell') ?> Şimdi telefonuma gönder</button>
      </form>
      <p class="hint">Bildirim için telefonunuzda <a class="link" href="profile.php">Profilim</a> sayfasından bildirimlerin açık olması gerekir.</p>
    </section>

    <section class="card">
      <div class="card-head"><h2>Son yedek kayıtları</h2></div>
      <?php $kayitlar = rows("SELECT * FROM audit_log WHERE action = 'backup' ORDER BY id DESC LIMIT 8"); ?>
      <?php if (!$kayitlar): ?>
        <p class="muted small">Henüz yedek alınmamış.</p>
      <?php else: ?>
        <ul class="kv">
          <?php foreach ($kayitlar as $k): $d = json_decode((string) $k['details'], true) ?: []; ?>
            <li><span><?= e(date_tr($k['created_at'], true)) ?></span><b><?= number_format(((int) ($d['boyut'] ?? 0)) / 1048576, 2, ',', '.') ?> MB</b></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>

<?php page_end();
