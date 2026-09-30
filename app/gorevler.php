<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Zamanlanmış görevler (4.12.0)

   İki yoldan çalışır, ikisi de aynı işi yapar ve birbirini bozmaz:

   1) Personel sayfaları açıldıkça (page_start): yanıt gönderildikten SONRA,
      en çok 5 dakikada bir. Ek kurulum gerekmez; mağaza açıkken yeterlidir.
   2) cron.php (isteğe bağlı): Plesk › Zamanlanmış Görevler'den 5–15 dakikada
      bir çağrılır; mağaza kapalıyken de (ör. sabah 10:00'da) mesajlar gider.
      Adres, merkez panelinde gösterilir; gizli anahtar ister.

   Görevler: WhatsApp Cloud kuyruğunu göndermek, günde bir kez otomatik
   mesajları (yorum isteği, lens, isteğe bağlı hatırlatmalar) kuyruğa eklemek,
   süresi dolan ödeme linklerini kapatmak.
   ========================================================================== */

const GOREV_ARALIK_SN = 300;

/** Arka plan görevi gerektiren özellikler (biri açıksa görevler çalışır). */
const GOREV_OZELLIKLERI = ['whatsapp', 'odeme_linki', 'uts_bildirim'];

/** Mesaj gönderimi için izinli saat aralığı (gece 21:00 – 09:00 arası otomatik mesaj gitmez). */
function gorev_mesaj_saati_mi(): bool
{
    $bas = max(0, min(23, (int) setting('wa_saat_bas', '9')));
    $bit = max(1, min(24, (int) setting('wa_saat_bit', '21')));
    $saat = (int) date('G');
    return $saat >= $bas && $saat < $bit;
}

/** Bu mağaza için görevleri çalıştırır. Hata olsa da sessizce devam eder; özet döner. */
function gorev_magaza_calistir(): array
{
    $ozet = ['kuyruga' => 0, 'gonderilen' => 0, 'link_kapatilan' => 0, 'uts' => 0];
    $simdi = time();
    $son = (int) setting('gorev_son', '0');
    if ($simdi - $son < GOREV_ARALIK_SN - 5) {
        return $ozet;
    }
    setting_set('gorev_son', (string) $simdi);

    if (function_exists('wa_gunluk_gorevler') && ozellik_acik_arka_plan('whatsapp') && gorev_mesaj_saati_mi()) {
        $bugun = date('Y-m-d');
        if (setting('gorev_gun', '') !== $bugun) {
            setting_set('gorev_gun', $bugun);
            try {
                $ozet['kuyruga'] = wa_gunluk_gorevler();
            } catch (Throwable $e) {
                app_log('gorev wa_gunluk: ' . $e->getMessage());
            }
        }
        try {
            $ozet['gonderilen'] = wa_kuyrugu_isle(25);
        } catch (Throwable $e) {
            app_log('gorev wa_kuyruk: ' . $e->getMessage());
        }
    }
    if (function_exists('odeme_suresi_dolanlari_kapat') && ozellik_acik_arka_plan('odeme_linki')) {
        try {
            $ozet['link_kapatilan'] = odeme_suresi_dolanlari_kapat();
        } catch (Throwable $e) {
            app_log('gorev odeme: ' . $e->getMessage());
        }
    }
    if (function_exists('uts_gorev') && ozellik_acik_arka_plan('uts_bildirim')) {
        try {
            $ozet['uts'] = uts_gorev()['gonderilen'];
        } catch (Throwable $e) {
            app_log('gorev uts: ' . $e->getMessage());
        }
    }
    return $ozet;
}

/** page_start'tan çağrılır: gerekiyorsa görevleri yanıt gönderildikten sonra çalıştırır. */
function gorev_belki_calistir(): void
{
    try {
        $acik = array_intersect(GOREV_OZELLIKLERI, magaza_ozellikleri());
        if (!$acik || time() - (int) setting('gorev_son', '0') < GOREV_ARALIK_SN) {
            return;
        }
        register_shutdown_function(static function (): void {
            try {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                }
                if (session_status() === PHP_SESSION_ACTIVE) {
                    @session_write_close();
                }
                @set_time_limit(60);
                gorev_magaza_calistir();
            } catch (Throwable $e) {
                // görev hatası sayfayı etkilemez
            }
        });
    } catch (Throwable $e) {
        // başlatılamadı: sayfa açılışını engellemesin
    }
}

/* ---------------- cron.php (tüm mağazalar) ---------------- */

function cron_anahtar_yolu(): string
{
    return APP_ROOT . '/storage/.cron-anahtar';
}

/** Cron anahtarı: config.php 'cron_anahtar' ya da storage'da bir kez üretilen değer. */
function cron_anahtar(): string
{
    $c = (string) config('cron_anahtar', '');
    if (strlen($c) >= 24) {
        return $c;
    }
    $yol = cron_anahtar_yolu();
    if (is_file($yol)) {
        $v = trim((string) file_get_contents($yol));
        if (strlen($v) >= 24) {
            return $v;
        }
    }
    $v = bin2hex(random_bytes(20));
    $gecici = $yol . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($gecici, $v, LOCK_EX) === false) {
        return '';
    }
    @chmod($gecici, 0600);
    $tamam = @link($gecici, $yol);   // hedef varsa başarısız: eşzamanlı ikinci istek diğerinin anahtarını kullanır
    @unlink($gecici);
    clearstatcache(true, $yol);
    if (!$tamam && !is_file($yol)) {
        return @file_put_contents($yol, $v, LOCK_EX) === false ? '' : $v;
    }
    $okunan = trim((string) @file_get_contents($yol));
    return strlen($okunan) >= 24 ? $okunan : '';
}

/**
 * Tüm aktif mağazalarda görevleri çalıştırır (cron.php). Göç (şema güncellemesi) YAPMAZ:
 * şeması 4.12.0'a güncellenmemiş mağaza atlanır; ilk personel girişinde güncellenir.
 */
function cron_tum_magazalar(): array
{
    $sonuc = [];
    $magazalar = merkez_rows("SELECT id, ozellikler FROM magazalar WHERE durum = 'aktif' AND ozellikler IS NOT NULL AND ozellikler <> '[]' ORDER BY id");
    foreach ($magazalar as $m) {
        $oz = ozellik_listesi_temizle($m['ozellikler']);
        if (!array_intersect(GOREV_OZELLIKLERI, $oz)) {
            continue;
        }
        try {
            if (!magaza_baglan_id((int) $m['id'])) {
                continue;
            }
            setting('__reload__');   // ayar önbelleği önceki mağazadan kalmasın
            if ((int) setting('schema_version', '0') < SCHEMA_VERSION) {
                $sonuc[(int) $m['id']] = 'şema eski, atlandı';
                continue;
            }
            $GLOBALS['__gorev_ozellikler'] = $oz;
            $sonuc[(int) $m['id']] = gorev_magaza_calistir();
        } catch (Throwable $e) {
            $sonuc[(int) $m['id']] = 'hata';
            app_log('cron mağaza ' . (int) $m['id'] . ': ' . $e->getMessage());
        } finally {
            unset($GLOBALS['__gorev_ozellikler']);
        }
    }
    return $sonuc;
}

function gorev_saat_metni(): string
{
    $bas = max(0, min(23, (int) setting('wa_saat_bas', '9')));
    $bit = max(1, min(24, (int) setting('wa_saat_bit', '21')));
    return sprintf('%02d:00–%02d:00 arası', $bas, $bit);
}
