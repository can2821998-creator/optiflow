<?php
declare(strict_types=1);

/* ==========================================================================
   Eski sistemden veri taşıma (4.17.1)
   --------------------------------------------------------------------------
   Poyraz Optik Atölye (OptiFlow'un eski sürümü) ya da OptiFlow'un kendi
   "Yedekleme" ekranından alınmış .sql / .sql.gz yedeğini bir mağazanın
   veritabanına aktarır. Merkez panel › mağaza › "Eski sistemden veri taşı".

   Güvenlik: dosya tırnak/yorum farkında ayrıştırılır ve YALNIZCA yedeğin
   içerdiği komut türlerine izin verilir:
     SET NAMES / FOREIGN_KEY_CHECKS / SQL_MODE / time_zone
     DROP TABLE IF EXISTS `tablo`
     CREATE TABLE `tablo` ( … ) ENGINE=…          (SELECT / dosya işlemi yok)
     INSERT INTO `tablo` (`sütunlar`) VALUES (…)  (değerler yalnızca sabit)
   Başka her komut (DROP DATABASE, USE, GRANT, tetikleyici, prosedür, LOAD
   DATA, INTO OUTFILE …) dosyanın tamamını reddettirir.

   Akış: tasima_coz() → önizleme → tasima_uygula() (önce mevcut verinin
   yedeği alınır) → eski şema numarası sıfırlanır → run_migrations().
   Geri alma: alınan ön yedek aynı yolla geri yüklenir.
   ========================================================================== */

const TASIMA_AZAMI_BAYT = 64 * 1024 * 1024;   // açılmış SQL en fazla 64 MB

/** Dosya içeriğini (gzip ise açarak) metne çevirir. */
function tasima_metin(string $ham): string
{
    if (strncmp($ham, "\x1f\x8b", 2) === 0) {
        if (!function_exists('gzdecode')) {
            throw new DomainException('Sunucuda zlib yok; .sql.gz yerine açılmış .sql dosyasını yükleyin.');
        }
        $acik = @gzdecode($ham, TASIMA_AZAMI_BAYT + 1);
        if ($acik === false) {
            throw new DomainException('Sıkıştırılmış dosya açılamadı (bozuk ya da eksik indirilmiş olabilir).');
        }
        $ham = $acik;
    }
    if (strlen($ham) > TASIMA_AZAMI_BAYT) {
        throw new DomainException('Yedek çok büyük (en fazla 64 MB).');
    }
    if (str_starts_with($ham, "\xEF\xBB\xBF")) {
        $ham = substr($ham, 3);
    }
    if (!mb_check_encoding($ham, 'UTF-8')) {
        throw new DomainException('Dosya UTF-8 değil; yedeği OptiFlow ya da Poyraz yedekleme ekranından yeniden alın.');
    }
    return $ham;
}

/**
 * SQL metnini komutlara böler (tek/çift tırnak, ters tırnak, --, #, /* *\/ farkında; MySQL ters bölü kaçışı).
 * Her komut için [ham, iskelet] döner; iskelette dize sabitlerinin içi boşaltılmıştır (doğrulama için).
 * @return list<array{0:string,1:string}>
 */
function tasima_bol(string $sql): array
{
    $n = strlen($sql);
    $i = 0;
    $ham = '';
    $iskelet = '';
    $sonuc = [];
    while ($i < $n) {
        $adim = strcspn($sql, "'\"`;-#/", $i);
        if ($adim > 0) {
            $parca = substr($sql, $i, $adim);
            $ham .= $parca;
            $iskelet .= $parca;
            $i += $adim;
            continue;
        }
        $c = $sql[$i];
        if ($c === ';') {
            if (trim($ham) !== '') {
                $sonuc[] = [trim($ham), trim($iskelet)];
            }
            $ham = $iskelet = '';
            $i++;
        } elseif ($c === '-' && ($sql[$i + 1] ?? '') === '-' && in_array($sql[$i + 2] ?? "\n", [' ', "\t", "\n", "\r"], true)) {
            $son = strpos($sql, "\n", $i);
            $i = $son === false ? $n : $son + 1;
            $ham .= "\n";
            $iskelet .= "\n";
        } elseif ($c === '#') {
            $son = strpos($sql, "\n", $i);
            $i = $son === false ? $n : $son + 1;
            $ham .= "\n";
            $iskelet .= "\n";
        } elseif ($c === '/' && ($sql[$i + 1] ?? '') === '*') {
            $son = strpos($sql, '*/', $i + 2);
            if ($son === false) {
                throw new DomainException('Dosyada kapanmamış bir yorum var; yedek eksik olabilir.');
            }
            // /*! … */ gibi koşullu komutlar çalıştırılabilir kod taşır: reddedilir.
            if (($sql[$i + 2] ?? '') === '!') {
                throw new DomainException('Dosyada izin verilmeyen MySQL koşullu komutu (/*! … */) var.');
            }
            $i = $son + 2;
            $ham .= ' ';
            $iskelet .= ' ';
        } elseif ($c === '-' || $c === '/') {
            $ham .= $c;
            $iskelet .= $c;
            $i++;
        } else {
            // Tırnaklı alan: ', ", `
            $j = $i + 1;
            while (true) {
                $k = strcspn($sql, $c . '\\', $j);
                $j += $k;
                if ($j >= $n) {
                    throw new DomainException('Dosyada kapanmamış bir tırnak var; yedek eksik ya da bozuk.');
                }
                if ($sql[$j] === '\\' && $c !== '`') {
                    $j += 2;
                    continue;
                }
                if (($sql[$j + 1] ?? '') === $c) {   // '' kaçışı
                    $j += 2;
                    continue;
                }
                break;
            }
            $parca = substr($sql, $i, $j - $i + 1);
            $ham .= $parca;
            $iskelet .= $c === '`' ? $parca : $c . $c;   // dize içeriği iskelete girmez
            $i = $j + 1;
        }
    }
    if (trim($ham) !== '') {
        $sonuc[] = [trim($ham), trim($iskelet)];
    }
    return $sonuc;
}

/** Bir komutu doğrular; [tur, tablo, satir] döner. İzin verilmeyen komutta DomainException. */
function tasima_komut_dogrula(string $iskelet): array
{
    $tek = preg_replace('/\s+/', ' ', $iskelet) ?? '';
    $tablo = '`([a-zA-Z0-9_]{1,64})`';
    if (preg_match("/^SET (NAMES utf8mb4( COLLATE [a-z0-9_]+)?|FOREIGN_KEY_CHECKS ?= ?[01]|SQL_MODE ?= ?''|time_zone ?= ?'')$/i", $tek)) {
        return ['set', '', 0];
    }
    if (preg_match("/^DROP TABLE IF EXISTS $tablo$/i", $tek, $m)) {
        return ['drop', $m[1], 0];
    }
    if (preg_match("/^CREATE TABLE (IF NOT EXISTS )?$tablo \(.*\)( [A-Z_=0-9a-z ']*)?$/is", $tek, $m)) {
        if (preg_match('/\b(SELECT|OUTFILE|DUMPFILE|LOAD_FILE|INFILE|DATA DIRECTORY|INDEX DIRECTORY|CONNECTION)\b/i', $tek)) {
            throw new DomainException('`' . $m[2] . '` tablosunun tanımında izin verilmeyen ifade var.');
        }
        return ['create', $m[2], 0];
    }
    if (preg_match("/^INSERT INTO $tablo ?(\((`[^`]{1,64}`, ?)*`[^`]{1,64}`\) ?)?VALUES ?(.*)$/is", $iskelet, $m)) {
        $degerler = $m[4];
        // Değerler yalnızca sabit olabilir: '' (dize), sayı, NULL, virgül, parantez, boşluk.
        if (!preg_match("/^[\s0-9(),.\-+eE'\"NULnul]*$/", $degerler) || preg_match('/[a-zA-Z]{2,}/', str_ireplace('NULL', '', $degerler))) {
            throw new DomainException('`' . $m[1] . '` verisinde sabit olmayan ifade var.');
        }
        $satir = 0;
        $derinlik = 0;
        $uz = strlen($degerler);
        for ($i = 0; $i < $uz; $i++) {
            if ($degerler[$i] === '(') {
                if ($derinlik === 0) {
                    $satir++;
                }
                $derinlik++;
            } elseif ($degerler[$i] === ')') {
                $derinlik--;
            }
        }
        return ['insert', $m[1], $satir];
    }
    $bas = mb_substr($tek, 0, 60);
    throw new DomainException('Dosyada izin verilmeyen komut var: "' . $bas . (mb_strlen($tek) > 60 ? '…' : '') . '". Yalnızca OptiFlow / Poyraz yedekleri aktarılabilir.');
}

/**
 * Yedeği ayrıştırır ve doğrular (veritabanına dokunmaz).
 * @return array{kaynak:string, surum:string, sema:int, tarih:string, tablolar:array<string,int>, komutlar:list<string>, eski:bool}
 */
function tasima_coz(string $ham): array
{
    $sql = tasima_metin($ham);
    $bas = substr($sql, 0, 600);
    $kaynak = str_contains($bas, 'Poyraz') ? 'poyraz' : (str_contains($bas, 'OptiFlow yedeği') ? 'optiflow' : 'bilinmiyor');
    $surum = preg_match('/^-- Sürüm: ([0-9.]+)/mu', $bas, $m) ? $m[1] : '';
    $sema = preg_match('/şema (\d+)/u', $bas, $m) ? (int) $m[1] : 0;
    $tarih = preg_match('/^-- Tarih: ([0-9.: ]+)$/mu', $bas, $m) ? trim($m[1]) : '';
    if ($kaynak === 'bilinmiyor') {
        throw new DomainException('Bu dosya bir OptiFlow ya da Poyraz Optik Atölye yedeği gibi görünmüyor (başlık satırı yok).');
    }
    if (!str_contains(substr($sql, -200), '-- Yedek sonu')) {
        throw new DomainException('Yedek eksik: dosyanın sonu ("Yedek sonu") yok. Yedeği yeniden alıp tekrar deneyin.');
    }
    $tablolar = [];
    $komutlar = [];
    $olusturulan = [];
    foreach (tasima_bol($sql) as [$h, $isk]) {
        [$tur, $tablo, $satir] = tasima_komut_dogrula($isk);
        if ($tur === 'set' && !preg_match("/^SET\s+(NAMES\s+utf8mb4(\s+COLLATE\s+[a-z0-9_]+)?|FOREIGN_KEY_CHECKS\s*=\s*[01]|SQL_MODE\s*=\s*'[A-Z_,]*'|time_zone\s*=\s*'[+\-0-9:]{1,6}')$/i", $h)) {
            throw new DomainException('Dosyada izin verilmeyen ayar komutu var.');
        }
        if ($tur === 'create') {
            $olusturulan[$tablo] = true;
            $tablolar[$tablo] ??= 0;
        } elseif ($tur === 'insert') {
            if (!isset($olusturulan[$tablo])) {
                throw new DomainException('`' . $tablo . '` tablosuna, tablosu oluşturulmadan veri ekleniyor; dosya beklenen biçimde değil.');
            }
            $tablolar[$tablo] = ($tablolar[$tablo] ?? 0) + $satir;
        }
        $komutlar[] = $h;
    }
    if (!isset($tablolar['orders'], $tablolar['customers'])) {
        throw new DomainException('Yedekte sipariş ve müşteri tabloları yok; bu bir OptiFlow mağaza yedeği değil.');
    }
    // Poyraz yedeklerindeki "şema" numarası OptiFlow'unkiyle aynı değil: göçler baştan çalışmalı.
    return ['kaynak' => $kaynak, 'surum' => $surum, 'sema' => $sema, 'tarih' => $tarih, 'tablolar' => $tablolar, 'komutlar' => $komutlar, 'eski' => $kaynak === 'poyraz'];
}

/** Önizlemede gösterilecek başlıca tablolar ve adları. */
function tasima_ozet_tablolari(): array
{
    return [
        'customers' => 'Müşteri', 'orders' => 'Sipariş', 'prescription_records' => 'Reçete', 'payments' => 'Tahsilat',
        'frame_items' => 'Çerçeve', 'suppliers' => 'Tedarikçi', 'quotes' => 'Teklif', 'user_accounts' => 'Kullanıcı',
    ];
}

/** Hedef veritabanındaki başlıca kayıt sayıları (tablo yoksa 0). */
function tasima_hedef_sayilari(PDO $pdo): array
{
    $sonuc = [];
    foreach (array_keys(tasima_ozet_tablolari()) as $t) {
        try {
            $sonuc[$t] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
        } catch (Throwable) {
            $sonuc[$t] = 0;
        }
    }
    return $sonuc;
}

/**
 * Doğrulanmış komutları verilen bağlantıda çalıştırır. Hata olursa hangi tabloda olduğunu söyler.
 * DDL komutları MySQL'de kendiliğinden işlenir (geri alınamaz) — bu yüzden çağıran önce yedek alır.
 */
function tasima_calistir(PDO $pdo, array $komutlar): int
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $sayi = 0;
    try {
        foreach ($komutlar as $k) {
            try {
                $pdo->exec($k);
            } catch (PDOException $e) {
                $t = preg_match('/`([a-zA-Z0-9_]+)`/', $k, $m) ? $m[1] : '?';
                throw new DomainException('Aktarım `' . $t . '` tablosunda durdu: ' . mb_substr($e->getMessage(), 0, 160));
            }
            $sayi++;
        }
    } finally {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    return $sayi;
}

/** Poyraz yedeğinden sonra (ya da şema numarası tutmuyorsa) göçlerin baştan çalışmasını sağlar. */
function tasima_sema_sifirla(PDO $pdo): void
{
    $pdo->exec("UPDATE app_settings SET setting_value = '0' WHERE setting_key = 'schema_version'");
}

/* ---------------- Merkez panel akışı (mağaza veritabanı üzerinde) ---------------- */

/** Onay kutusu: Türkçe büyük harfe çevirir ("geri al" → "GERİ AL", "taşı" → "TAŞI"). */
function tasima_onay(string $s): string
{
    return mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], trim(preg_replace('/\s+/u', ' ', $s) ?? '')), 'UTF-8');
}

function tasima_klasor(): string
{
    $d = APP_ROOT . '/storage/tasima';
    if (!is_dir($d)) {
        @mkdir($d, 0750, true);
    }
    return $d;
}

/** db() bağlantısını mağazanın veritabanına çevirir (aynı istek içinde). */
function tasima_magaza_baglan(array $m): void
{
    if (($m['durum'] ?? '') === 'beklemede' || empty($m['db_name'])) {
        throw new DomainException('Mağaza henüz etkinleştirilmemiş; önce veritabanını bağlayın.');
    }
    db_baglanti_degistir(magaza_db_ayari($m));
}

/** Yüklenen dosyayı doğrular ve saklar (web'e kapalı storage/tasima). Önizleme özeti döner. */
function tasima_yukle(int $magazaId, array $dosya): array
{
    if (($dosya['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new DomainException('Yedek dosyasını seçin (.sql ya da .sql.gz).');
    }
    if (($dosya['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($dosya['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
        throw new DomainException('Dosya sunucunun yükleme sınırını aşıyor. Plesk › PHP ayarlarında upload_max_filesize ve post_max_size değerini artırın.');
    }
    if (($dosya['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $dosya['tmp_name'])) {
        throw new DomainException('Dosya yüklenemedi; tekrar deneyin.');
    }
    $ad = (string) ($dosya['name'] ?? 'yedek.sql');
    if (!preg_match('/\.sql(\.gz)?$/i', $ad)) {
        throw new DomainException('Yalnızca .sql ya da .sql.gz yedek dosyası yüklenebilir.');
    }
    $ham = (string) file_get_contents((string) $dosya['tmp_name']);
    $plan = tasima_coz($ham);
    tasima_temizle();
    $hedef = tasima_klasor() . '/yukleme-' . $magazaId . '-' . bin2hex(random_bytes(6)) . (str_starts_with($ham, "\x1f\x8b") ? '.sql.gz' : '.sql');
    if (file_put_contents($hedef, $ham, LOCK_EX) === false) {
        throw new DomainException('Dosya sunucuya kaydedilemedi (storage yazma izni).');
    }
    @chmod($hedef, 0600);
    unset($plan['komutlar']);
    return ['dosya' => basename($hedef), 'ad' => mb_substr($ad, 0, 120), 'boyut' => strlen($ham)] + $plan;
}

/** 24 saatten eski yüklemeleri siler (ön yedeklere dokunmaz). */
function tasima_temizle(): void
{
    foreach (glob(tasima_klasor() . '/yukleme-*') ?: [] as $f) {
        if (@filemtime($f) < time() - 86400) {
            @unlink($f);
        }
    }
}

function tasima_gecmis(int $magazaId): array
{
    $f = tasima_klasor() . '/gecmis-' . $magazaId . '.json';
    $j = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
    return is_array($j) ? $j : [];
}

function tasima_gecmis_ekle(int $magazaId, array $kayit): void
{
    $g = tasima_gecmis($magazaId);
    array_unshift($g, $kayit + ['zaman' => date('Y-m-d H:i:s')]);
    file_put_contents(tasima_klasor() . '/gecmis-' . $magazaId . '.json', json_encode(array_slice($g, 0, 20), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/** Mağaza veritabanındaki tablo sayısı. */
function tasima_tablo_sayisi(): int
{
    return (int) scalar("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
}

/**
 * Yüklenmiş yedeği mağazaya aktarır: (1) mağazanın şu anki halini yedekler, (2) aktarır,
 * (3) eski sürümse şema numarasını sıfırlar, (4) göçleri çalıştırır. Dönen: sonuç özeti.
 */
function tasima_uygula_magaza(array $m, string $dosyaAdi): array
{
    if (!preg_match('/^yukleme-' . (int) $m['id'] . '-[0-9a-f]{12}\.sql(\.gz)?$/', $dosyaAdi)) {
        throw new DomainException('Yükleme bulunamadı; dosyayı yeniden yükleyin.');
    }
    $yol = tasima_klasor() . '/' . $dosyaAdi;
    if (!is_file($yol)) {
        throw new DomainException('Yüklenen dosyanın süresi dolmuş; yeniden yükleyin.');
    }
    $plan = tasima_coz((string) file_get_contents($yol));
    @set_time_limit(600);
    tasima_magaza_baglan($m);

    // 1) Mağazanın şu anki hali (boş değilse) geri alınabilsin diye yedeklenir.
    $onceki = null;
    if (tasima_tablo_sayisi() > 0) {
        try {
            $y = backup_write(tasima_klasor());
            $onceki = basename($y['dosya']);
            $hedefOnceki = tasima_klasor() . '/once-' . (int) $m['id'] . '-' . date('Ymd-His') . (str_ends_with($y['dosya'], '.gz') ? '.sql.gz' : '.sql');
            @rename($y['dosya'], $hedefOnceki);
            @chmod($hedefOnceki, 0600);
            $onceki = basename($hedefOnceki);
        } catch (Throwable $e) {
            throw new DomainException('Taşımadan önce mağazanın şu anki verisi yedeklenemedi; işlem yapılmadı. (' . mb_substr($e->getMessage(), 0, 120) . ')');
        }
    }

    // Geçmişe aktarımdan ÖNCE yazılır: yarıda kesilirse "geri al" bu ön yedeği bulur.
    tasima_gecmis_ekle((int) $m['id'], ['tur' => 'tasima', 'durum' => 'basladi', 'kaynak' => $plan['kaynak'], 'surum' => $plan['surum'],
        'tarih' => $plan['tarih'], 'onceki_yedek' => $onceki, 'sayilar' => []]);

    // 2) Aktarım
    $komut = tasima_calistir(db(), $plan['komutlar']);

    // 3) Eski sürüm: göçler baştan (Poyraz "şema" numarası OptiFlow'unkiyle aynı değil)
    if ($plan['eski'] || !column_exists('orders', 'sgk_amount')) {
        tasima_sema_sifirla(db());
    }

    // 4) Güncelleme
    run_migrations();
    $sonuc = [
        'kaynak' => $plan['kaynak'], 'surum' => $plan['surum'], 'tarih' => $plan['tarih'], 'komut' => $komut,
        'sema' => (int) scalar("SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'"),
        'sayilar' => tasima_hedef_sayilari(db()), 'onceki_yedek' => $onceki, 'tur' => 'tasima', 'durum' => 'tamam',
    ];
    $g = tasima_gecmis((int) $m['id']);
    $g[0] = $sonuc + ['zaman' => $g[0]['zaman'] ?? date('Y-m-d H:i:s')];
    file_put_contents(tasima_klasor() . '/gecmis-' . (int) $m['id'] . '.json', json_encode($g, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    @unlink($yol);
    return $sonuc;
}

/**
 * Son taşımayı geri alır: taşımadan önce alınan yedek geri yüklenir. Taşımadan önce mağaza boşsa
 * mağaza veritabanındaki tablolar silinir (boş hale döner; ilk girişte yeniden kurulur).
 */
function tasima_geri_al(array $m): array
{
    $son = null;
    foreach (tasima_gecmis((int) $m['id']) as $g) {
        if (($g['tur'] ?? '') === 'tasima') {
            $son = $g;
            break;
        }
        if (($g['tur'] ?? '') === 'geri_al') {
            break;   // son taşıma zaten geri alınmış
        }
    }
    if (!$son) {
        throw new DomainException('Geri alınacak bir taşıma yok.');
    }
    @set_time_limit(600);
    tasima_magaza_baglan($m);
    if ($son['onceki_yedek']) {
        $yol = tasima_klasor() . '/' . basename((string) $son['onceki_yedek']);
        if (!is_file($yol)) {
            throw new DomainException('Taşımadan önceki yedek dosyası bulunamadı.');
        }
        $plan = tasima_coz((string) file_get_contents($yol));
        tasima_calistir(db(), $plan['komutlar']);
        run_migrations();
    } else {
        // Taşımadan önce mağaza boştu: boş hale döndür.
        db()->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (rows("SELECT TABLE_NAME AS t FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'") as $r) {
            db()->exec('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $r['t']) . '`');
        }
        db()->exec('SET FOREIGN_KEY_CHECKS=1');
    }
    $sonuc = ['tur' => 'geri_al', 'sayilar' => tasima_hedef_sayilari(db()), 'onceki_yedek' => $son['onceki_yedek']];
    tasima_gecmis_ekle((int) $m['id'], $sonuc);
    return $sonuc;
}
