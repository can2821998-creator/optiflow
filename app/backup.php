<?php
declare(strict_types=1);

/* ==========================================================================
   Yedekleme ve günün özeti
   --------------------------------------------------------------------------
   Yedek: veritabanının tamamını .sql (varsa .sql.gz) olarak dışa yazar.
   Dosya satır satır üretilir; büyük tablolarda bile bellek şişmez.
   Günün özeti: akşam saatinden sonra sisteme ilk giren kişinin isteğinde
   bir kez gönderilir — hosting'de zamanlanmış görev (cron) gerekmez.
   ========================================================================== */

/** Son yedek zamanı (boş olabilir). */
function backup_last_at(): string
{
    return setting('last_backup_at', '');
}

/** Son yedeğin üzerinden kaç gün geçti? Hiç yedek yoksa null. */
function backup_age_days(): ?int
{
    $son = backup_last_at();
    if ($son === '') {
        return null;
    }
    $t = strtotime($son);
    return $t ? (int) floor((time() - $t) / 86400) : null;
}

/** Yedek uyarısı gösterilmeli mi? */
function backup_overdue(): bool
{
    $sinir = max(1, (int) setting('backup_warn_days', '7'));
    $yas = backup_age_days();
    return $yas === null || $yas >= $sinir;
}

/** SQL değeri kaçışlama. */
function backup_quote(?string $v): string
{
    if ($v === null) {
        return 'NULL';
    }
    return db()->quote($v);
}

/**
 * Veritabanını dosyaya yazar. Dönen dizi: [dosya, tablo sayısı, satır sayısı].
 * Zlib varsa çıktı gzip'lenir (uzantı .sql.gz).
 */
function backup_write(string $klasor): array
{
    if (!is_dir($klasor)) {
        @mkdir($klasor, 0775, true);
    }
    $gz = function_exists('gzopen');
    $dosya = rtrim($klasor, '/') . '/yedek-' . date('Y-m-d-His') . '-' . bin2hex(random_bytes(4)) . '.sql' . ($gz ? '.gz' : '');

    $fh = $gz ? gzopen($dosya, 'wb6') : fopen($dosya, 'wb');
    if (!$fh) {
        throw new RuntimeException('Yedek dosyası oluşturulamadı: ' . $klasor);
    }
    $yaz = static function (string $s) use ($fh, $gz): void {
        $gz ? gzwrite($fh, $s) : fwrite($fh, $s);
    };

    $pdo = db();
    $vt = (string) scalar('SELECT DATABASE()');
    $yaz("-- OptiFlow yedeği\n-- Veritabanı: $vt\n-- Tarih: " . date('d.m.Y H:i:s') . "\n"
        . '-- Sürüm: ' . APP_VERSION . ' · şema ' . SCHEMA_VERSION . "\n\n"
        . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n");

    $tablolar = [];
    foreach (rows('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $satir) {
        $tablolar[] = (string) array_values($satir)[0];
    }

    $satirSayisi = 0;
    foreach ($tablolar as $t) {
        $create = row('SHOW CREATE TABLE `' . str_replace('`', '', $t) . '`');
        $yaz("\n--\n-- Tablo: $t\n--\nDROP TABLE IF EXISTS `$t`;\n" . ($create['Create Table'] ?? '') . ";\n");

        $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '', $t) . '`');
        $sutunlar = null;
        $tampon = [];
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($sutunlar === null) {
                $sutunlar = '`' . implode('`,`', array_keys($r)) . '`';
            }
            $degerler = [];
            foreach ($r as $v) {
                $degerler[] = $v === null ? 'NULL' : backup_quote((string) $v);
            }
            $tampon[] = '(' . implode(',', $degerler) . ')';
            $satirSayisi++;
            if (count($tampon) >= 200) {
                $yaz("INSERT INTO `$t` ($sutunlar) VALUES\n" . implode(",\n", $tampon) . ";\n");
                $tampon = [];
            }
        }
        if ($tampon) {
            $yaz("INSERT INTO `$t` ($sutunlar) VALUES\n" . implode(",\n", $tampon) . ";\n");
        }
        $stmt->closeCursor();
    }

    $yaz("\nSET FOREIGN_KEY_CHECKS=1;\n-- Yedek sonu\n");
    $gz ? gzclose($fh) : fclose($fh);

    setting_set('last_backup_at', date('Y-m-d H:i:s'));

    return ['dosya' => $dosya, 'tablo' => count($tablolar), 'satir' => $satirSayisi, 'boyut' => (int) @filesize($dosya)];
}

/** Eski yedek dosyalarını temizler (indirme sonrası artık kalmasın). */
function backup_cleanup(string $klasor, int $enFazlaSaat = 2): void
{
    foreach (glob(rtrim($klasor, '/') . '/yedek-*.sql*') ?: [] as $f) {
        if (@filemtime($f) < time() - $enFazlaSaat * 3600) {
            @unlink($f);
        }
    }
}

/* ------------------------------------------------------------------ */
/*  Günün özeti                                                         */
/* ------------------------------------------------------------------ */

/** Bugünün rakamları. */
function daily_summary_data(?string $gun = null): array
{
    $gun ??= date('Y-m-d');
    $bas = $gun . ' 00:00:00';
    $son = date('Y-m-d', strtotime($gun . ' +1 day')) . ' 00:00:00';

    $d = [
        'gun'       => $gun,
        'siparis'   => (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage <> 'iptal' AND created_at >= ? AND created_at < ?", [$bas, $son]),
        'ciro'      => (float) scalar("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE order_stage <> 'iptal' AND created_at >= ? AND created_at < ?", [$bas, $son]),
        'tahsilat'  => (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM payments WHERE created_at >= ? AND created_at < ?', [$bas, $son]),
        'teslim'    => (int) scalar("SELECT COUNT(*) FROM orders WHERE delivered_at >= ? AND delivered_at < ?", [$bas, $son]),
        'hazir'     => (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage = 'hazirlandi'"),
        'atolyede'  => (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage = 'atolyede'"),
        'eksik_cam' => (int) scalar("SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id JOIN orders o ON o.id = r.order_id WHERE i.stock_status = 'stokta_yok' AND o.order_stage <> 'iptal'"),
    ];
    try {
        $d['gider'] = (float) scalar('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = ?', [$gun]);
    } catch (Throwable) {
        $d['gider'] = 0.0;
    }
    try {
        $d['hatirlatma'] = reminder_badge();
    } catch (Throwable) {
        $d['hatirlatma'] = 0;
    }
    try {
        $d['kritik_cerceve'] = frame_alert_count();
    } catch (Throwable) {
        $d['kritik_cerceve'] = 0;
    }
    return $d;
}

/** Özeti bildirim metnine çevirir: [başlık, gövde]. */
function daily_summary_text(array $d): array
{
    $baslik = 'Günün özeti · ' . date_tr($d['gun']);
    $satir = [];
    $satir[] = $d['siparis'] . ' yeni sipariş (' . money($d['ciro']) . ')';
    $satir[] = 'tahsilat ' . money($d['tahsilat']);
    if ($d['teslim']) {
        $satir[] = $d['teslim'] . ' teslim';
    }
    if ($d['hazir']) {
        $satir[] = $d['hazir'] . ' gözlük teslim bekliyor';
    }
    if ($d['eksik_cam']) {
        $satir[] = $d['eksik_cam'] . ' cam eksik';
    }
    if (!empty($d['kritik_cerceve'])) {
        $satir[] = $d['kritik_cerceve'] . ' çerçeve kritik stokta';
    }
    if (!empty($d['hatirlatma'])) {
        $satir[] = $d['hatirlatma'] . ' hatırlatma bekliyor';
    }
    return [$baslik, implode(' · ', $satir)];
}

/** Özeti süper yetkililere gönderir. */
function daily_summary_send(?string $gun = null): array
{
    $d = daily_summary_data($gun);
    [$baslik, $govde] = daily_summary_text($d);
    $hedef = array_map('intval', array_column(rows("SELECT id FROM user_accounts WHERE role = 'super_yetkili' AND is_active = 1"), 'id'));
    $sonuc = push_send(['title' => $baslik, 'body' => $govde, 'url' => 'index.php', 'tag' => 'gunluk-ozet'], $hedef);
    $sonuc['ozet'] = $baslik . ' — ' . $govde;
    return $sonuc;
}

/**
 * Ayarlanan saat geldiyse ve bugün gönderilmediyse özeti gönderir.
 * Sayfa açılışlarında çağrılır; günde bir kez çalışır, hata yutulur.
 */
function daily_summary_maybe_send(): void
{
    try {
        if (setting('daily_summary', '0') !== '1') {
            return;
        }
        $saat = max(0, min(23, (int) setting('daily_summary_hour', '19')));
        if ((int) date('G') < $saat) {
            return;
        }
        if (setting('daily_summary_last', '') === date('Y-m-d')) {
            return;
        }
        setting_set('daily_summary_last', date('Y-m-d'));   // önce işaretle: ikinci istek tekrar göndermesin
        daily_summary_send();
    } catch (Throwable $e) {
        // özet gönderilemedi: sessizce geç
    }
}
