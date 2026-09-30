<?php
declare(strict_types=1);

/* ==========================================================================
   Otomatik günlük yedek (sunucuda, dönüşümlü)

   - Açıkken, günün ilk personel sayfa açılışında yedek alır; sayfa yanıtı
     gönderildikten SONRA çalıştığı için kimse beklemez. Cron gerekmez.
   - storage/backups-auto/ altında oto-YYYY-AA-GG-SSDDSS-xxxxxxxx.sql.gz olarak
     saklanır; en yeni N tanesi tutulur (varsayılan 14), eskiler silinir.
   - Her yedek yazıldıktan sonra doğrulanır (dosya sonu işareti + gzip okunur);
     bozuk çıkarsa silinir ve hata Yedekleme ekranında görünür.
   - Bu dosya yalnızca gerektiğinde ve try/catch içinde yüklenir: burada bir
     hata olsa bile sistemin geri kalanı çalışmaya devam eder.

   ÖNEMLİ: Bu kopya AYNI SUNUCUDA durur. Yanlış bir güncellemeye, silinen veya
   bozulan kayda karşı korur; sunucunun kendisi kaybolursa korumaz. Bilgisayara
   indirilen yedek (ve hosting panelinin yedeği) bunun yerine değil, yanına geçer.
   ========================================================================== */

function autobackup_dir(): string
{
    return APP_ROOT . '/storage/backups-auto';
}

function autobackup_keep(): int
{
    return max(3, min(60, (int) setting('auto_backup_keep', '14')));
}

/** İndirme/silme için dosya adı doğrulaması (yol gezintisine karşı). */
function autobackup_ad_ok(string $ad): bool
{
    return (bool) preg_match('/^oto-\d{4}-\d{2}-\d{2}-\d{6}-[0-9a-f]{8}\.sql(\.gz)?$/', $ad);
}

/** Saklanan otomatik yedekler, yeniden eskiye: [['ad', 'boyut', 'zaman'], ...] */
function autobackup_list(): array
{
    $out = [];
    foreach (glob(autobackup_dir() . '/oto-*.sql*') ?: [] as $f) {
        $ad = basename($f);
        if (!autobackup_ad_ok($ad)) {
            continue;
        }
        $out[] = ['ad' => $ad, 'boyut' => (int) @filesize($f), 'zaman' => (int) @filemtime($f)];
    }
    usort($out, static fn(array $a, array $b): int => $b['zaman'] <=> $a['zaman']);
    return $out;
}

/** Yedek eksiksiz mi? backup_write() en sona "-- Yedek sonu" yazar; gzip de sonuna kadar okunur. */
function autobackup_sonu_tamam(string $dosya): bool
{
    $son = '';
    if (substr($dosya, -3) === '.gz') {
        $gz = @gzopen($dosya, 'rb');
        if (!$gz) {
            return false;
        }
        while (!gzeof($gz)) {
            $parca = gzread($gz, 65536);
            if ($parca === false) {
                gzclose($gz);
                return false;
            }
            $son = substr($son . $parca, -300);
        }
        gzclose($gz);
    } else {
        $boyut = (int) @filesize($dosya);
        $son = (string) @file_get_contents($dosya, false, null, max(0, $boyut - 300));
    }
    return strpos($son, '-- Yedek sonu') !== false;
}

/**
 * Yedeği şimdi alır ve doğrular. Dönen: ['ok' => bool, 'mesaj' => string, 'boyut' => int].
 * Bilgisayara indirilen yedeğin "son yedek" zamanını DEĞİŞTİRMEZ.
 */
function autobackup_run(): array
{
    $klasor = autobackup_dir();
    $oncekiIndirme = setting('last_backup_at', '');
    $dosya = '';
    try {
        @set_time_limit(600);
        if (!is_dir($klasor) && !@mkdir($klasor, 0775, true) && !is_dir($klasor)) {
            throw new RuntimeException('storage/backups-auto klasörü oluşturulamadı (yazma izni?)');
        }
        if (!is_file($klasor . '/.htaccess')) {
            @file_put_contents(
                $klasor . '/.htaccess',
                "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
            );
        }
        foreach (glob($klasor . '/yedek-*') ?: [] as $yarim) {   // önceki yarım kalmış denemeler
            @unlink($yarim);
        }

        $sonuc = backup_write($klasor);
        $gecici = (string) $sonuc['dosya'];
        $dosya = $klasor . '/oto-' . substr(basename($gecici), strlen('yedek-'));
        if (!@rename($gecici, $dosya)) {
            $dosya = $gecici;
            throw new RuntimeException('Yedek dosyası yeniden adlandırılamadı');
        }

        $boyut = (int) @filesize($dosya);
        if ($boyut < 500) {
            throw new RuntimeException('Yedek dosyası beklenenden küçük çıktı (' . $boyut . ' bayt)');
        }
        if (!autobackup_sonu_tamam($dosya)) {
            throw new RuntimeException('Yedek dosyası eksik ya da bozuk çıktı (doğrulama başarısız)');
        }

        foreach (array_slice(autobackup_list(), autobackup_keep()) as $eski) {
            @unlink($klasor . '/' . $eski['ad']);
        }

        setting_set('last_backup_at', $oncekiIndirme);
        setting_set('auto_backup_last_at', date('Y-m-d H:i:s'));
        setting_set('auto_backup_last_error', '');
        return ['ok' => true, 'mesaj' => 'Yedek alındı ve doğrulandı.', 'boyut' => $boyut, 'tablo' => (int) $sonuc['tablo'], 'satir' => (int) $sonuc['satir']];
    } catch (Throwable $e) {
        if ($dosya !== '' && is_file($dosya)) {
            @unlink($dosya);
        }
        foreach (glob($klasor . '/yedek-*') ?: [] as $yarim) {
            @unlink($yarim);
        }
        try {
            setting_set('last_backup_at', $oncekiIndirme);
            setting_set('auto_backup_last_error', date('d.m.Y H:i') . ' — ' . mb_substr($e->getMessage(), 0, 240));
        } catch (Throwable $x) {
            // veritabanı yazılamıyor: yapacak bir şey yok
        }
        if (function_exists('app_log')) {
            app_log('Otomatik yedek hatası: ' . $e->getMessage());
        }
        return ['ok' => false, 'mesaj' => $e->getMessage(), 'boyut' => 0, 'tablo' => 0, 'satir' => 0];
    }
}

/**
 * Açıksa ve bugün denenmediyse yedeği, sayfa yanıtı gönderildikten sonra çalıştırır.
 * Önce "bugün denendi" işaretlenir: eşzamanlı ikinci istek tekrar başlatmasın.
 * Başarısız olursa ertesi güne kadar yeniden denenmez; hata Yedekleme ekranında görünür.
 */
function autobackup_maybe_run(): void
{
    try {
        if (setting('auto_backup', '0') !== '1') {
            return;
        }
        $bugun = date('Y-m-d');
        if (setting('auto_backup_last', '') === $bugun) {
            return;
        }
        setting_set('auto_backup_last', $bugun);
        register_shutdown_function(static function (): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                if (session_status() === PHP_SESSION_ACTIVE) {
                    @session_write_close();   // oturum kilidini bırak: aynı kullanıcının diğer sayfaları beklemesin
                }
                autobackup_run();
            } catch (Throwable $e) {
                // sessizce geç
            }
        });
    } catch (Throwable $e) {
        // başlatılamadı: sayfa açılışını engellemesin
    }
}
