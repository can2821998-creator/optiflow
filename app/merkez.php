<?php
declare(strict_types=1);

/**
 * Merkez (mağaza kayıt/oturum) katmanı — ÜRÜNLEŞTİRME (OptiFlow).
 *
 * Bu dosya, tek bir kurulumun tek bir dükkâna ait olduğu eski modeli, aynı sunucuda çalışan ve HER BİRİ
 * KENDİ VERİTABANINA sahip birden çok mağazaya genişletir. Mevcut app/ altındaki tüm sayfalar ve sorgular
 * DEĞİŞMEDEN kalır — her mağaza fiziksel olarak ayrı bir veritabanında olduğu için bir mağazanın verisinin
 * başka bir mağazaya sızması yapısal olarak mümkün değildir.
 *
 * Merkez kayıt (magazalar tablosu) AYRI bir veritabanı DEĞİLDİR — bu kurulumun kendi config('db')'sinde,
 * sıradan bir tablo olarak durur (böylece kurulum tek bir veritabanıyla çalışabilir; sunucu izin verirse
 * yeni mağazalar için ek veritabanları da AYNI bağlantı bilgileriyle otomatik açılabilir).
 *
 * İKİ SAĞLAMA MODU (sunucunun izin verdiğine göre kendiliğinden seçilir):
 *   OTOMATİK  — MySQL kullanıcısının CREATE DATABASE yetkisi varsa (kendi VPS/kök erişimli sunucu gibi):
 *               kayıt formu tek adımda yeni veritabanını açar, kurar, ilk yöneticiyi oluşturur; mağaza
 *               anında aktif olur.
 *   BEKLEMEDE — MySQL kullanıcısının bu yetkisi yoksa (paylaşımlı hosting/Plesk aboneliği gibi, tipik
 *               MySQL hata kodu 1044): kayıt formu bilgileri saklar ama veritabanı açmaz; ürün sahibi
 *               (siz) Plesk panelinden yeni bir veritabanı oluşturup merkez panelden (merkez-panel.php)
 *               birkaç bilgiyi girerek mağazayı birkaç dakika içinde etkinleştirir.
 * Hangi modun kullanılacağını kod kendisi dener/anlar; sunucu değişse (ör. VPS'e taşınsa) kod değişmez.
 *
 * Giriş iki aşamalıdır:
 *   1) Mağaza girişi (bu dosya): e-posta + mağaza şifresi → hangi veritabanına bağlanılacağını belirler.
 *   2) Kullanıcı girişi (app/auth.php, değişmedi): o mağazanın kendi user_accounts tablosundaki hesap.
 * Her iki adım da HER oturumda sorulur; kalıcı bir "mağazayı hatırla" çerezi YOKTUR — logout_session()
 * ikisini de aynı anda temizler ($_SESSION = [] zaten böyle yapıyor).
 */

/** Merkez veritabanı bağlantısı: bu kurulumun KENDİ config('db')'si — ayrı bir veritabanı değildir. */
function merkez_db(): PDO
{
    static $pdo = null;
    static $hazirlandi = false;
    if (!($pdo instanceof PDO)) {
        $c = config('db', []);
        $dsn = 'mysql:host=' . ($c['host'] ?? 'localhost') . (!empty($c['port']) ? ';port=' . (int) $c['port'] : '')
            . ';dbname=' . ($c['name'] ?? '') . ';charset=utf8mb4';
        $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['password'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    if (!$hazirlandi) {
        merkez_sema_hazirla($pdo);
        $hazirlandi = true;
    }
    return $pdo;
}

/** magazalar tablosunu (yoksa) kurar — yalnızca CREATE TABLE; bu kurulumun kendi veritabanı için her zaman izin vardır. */
function merkez_sema_hazirla(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS magazalar (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            isim VARCHAR(120) NOT NULL,
            email VARCHAR(190) NOT NULL,
            sifre_hash VARCHAR(255) NOT NULL,
            admin_ad VARCHAR(120) NOT NULL,
            admin_kullanici VARCHAR(60) NOT NULL,
            admin_sifre_hash VARCHAR(255) NOT NULL,
            db_host VARCHAR(120) NULL,
            db_name VARCHAR(64) NULL,
            db_user VARCHAR(64) NULL,
            db_sifre VARCHAR(190) NULL,
            plan VARCHAR(20) NOT NULL DEFAULT 'deneme',
            deneme_bitis DATE NULL,
            durum VARCHAR(20) NOT NULL DEFAULT 'beklemede',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_magaza_email (email),
            UNIQUE KEY uq_magaza_db (db_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    // Merkez panel girişine kaba kuvvet freni (IP bazlı). Merkez şifresi TÜM mağazaları yönetir;
    // tenant girişinden farklı olarak burada eskiden hiç sınır yoktu.
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS merkez_giris_denemeleri (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            basarili TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mgd_ip (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    // Merkez işlem günlüğü (kim neyi ne zaman yaptı)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS merkez_islem_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            eylem VARCHAR(40) NOT NULL,
            magaza_id INT UNSIGNED NULL,
            magaza_isim VARCHAR(120) NULL,
            detay VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_mil_t (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    // 4.18.0 — "Beni hatırla" (mağaza girişi): yalnızca doğrulayıcının SHA-256 özeti tutulur (bkz. app/hatirla.php)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS magaza_hatirla (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            magaza_id INT UNSIGNED NOT NULL,
            secici CHAR(24) NOT NULL,
            dogrulayici_hash CHAR(64) NOT NULL,
            sifre_damga CHAR(64) NOT NULL,
            cihaz VARCHAR(120) NOT NULL DEFAULT '',
            olusturma DATETIME NOT NULL,
            son_kullanim DATETIME NULL,
            bitis DATETIME NOT NULL,
            UNIQUE KEY uq_mh_secici (secici),
            INDEX idx_mh_magaza (magaza_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    // 4.20.1 — Mağaza girişi ve yeni kayıt için hız sınırı (bkz. merkez_hiz_asildi)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS merkez_hiz_siniri (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tur VARCHAR(20) NOT NULL,
            ip VARCHAR(45) NOT NULL,
            anahtar VARCHAR(190) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL,
            INDEX idx_mhs_ip (tur, ip, created_at),
            INDEX idx_mhs_anahtar (tur, anahtar, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    // Sonradan eklenen kolonlar (geriye dönük uyumlu)
    merkez_kolon_ekle($pdo, 'magazalar', 'notlar', 'TEXT NULL');
    merkez_kolon_ekle($pdo, 'magazalar', 'guncelleme', 'DATETIME NULL');
    // 4.11.0 — OptiFlow Lite / Pro paketi (bkz. app/paket.php). Varsayılan Lite.
    merkez_kolon_ekle($pdo, 'magazalar', 'surum', "VARCHAR(10) NOT NULL DEFAULT 'lite'");
    merkez_kolon_ekle($pdo, 'magazalar', 'ozellikler', 'TEXT NULL');   // 4.12.0 özellik anahtarları (JSON)
}

/** Bir kolon yoksa ekler (merkez veritabanı için; information_schema ile güvenli). */
function merkez_kolon_ekle(PDO $pdo, string $tablo, string $kolon, string $tanim): void
{
    try {
        $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $st->execute([$tablo, $kolon]);
        if (!(int) $st->fetchColumn()) {
            $pdo->exec("ALTER TABLE `$tablo` ADD COLUMN `$kolon` $tanim");
        }
    } catch (Throwable $e) {
        // kolon eklenemezse panel yine çalışsın
    }
}

function merkez_scalar(string $sql, array $params = []): mixed
{
    $st = merkez_db()->prepare($sql);
    $st->execute(array_values($params));
    $v = $st->fetchColumn();
    return $v === false ? null : $v;
}

function merkez_row(string $sql, array $params = []): ?array
{
    $st = merkez_db()->prepare($sql);
    $st->execute(array_values($params));
    $r = $st->fetch();
    return $r === false ? null : $r;
}

function merkez_rows(string $sql, array $params = []): array
{
    $st = merkez_db()->prepare($sql);
    $st->execute(array_values($params));
    return $st->fetchAll();
}

function merkez_q(string $sql, array $params = []): void
{
    $st = merkez_db()->prepare($sql);
    $st->execute(array_values($params));
}

/** Mağaza adından güvenli, benzersiz bir veritabanı adı üretir (yalnızca harf/rakam/alt çizgi, MySQL sınırı 64 karakter). */
function tenant_db_adi_uret(string $isim): string
{
    $harfler = ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u'];
    $temel = strtolower(strtr($isim, $harfler));
    $temel = preg_replace('/[^a-z0-9]+/', '_', $temel) ?? '';
    $temel = trim($temel, '_');
    $temel = $temel !== '' ? mb_substr($temel, 0, 40) : 'magaza';
    do {
        $aday = 'mgz_' . $temel . '_' . random_int(1000, 9999);
    } while (merkez_row('SELECT id FROM magazalar WHERE db_name = ?', [$aday]));
    return $aday;
}

/**
 * Yeni mağaza başvurusu. Önce OTOMATİK sağlamayı dener (CREATE DATABASE + run_migrations() + ilk yönetici);
 * sunucu izin vermiyorsa (MySQL 1044 gibi bir yetki hatası) sessizce BEKLEMEDE moduna düşer — başvuru
 * kaydedilir, veritabanı açılmaz, ürün sahibi merkez panelden etkinleştirir. Döndürülen dizide 'durum'
 * anahtarı 'aktif' ya da 'beklemede' olur; çağıran (kayit.php) buna göre farklı bir mesaj gösterir.
 */
function tenant_basvuru(string $isim, string $email, string $magazaSifre, string $adminAd, string $adminKullanici, string $adminSifre): array
{
    $isim = trim($isim);
    $email = trim(strtolower($email));
    if ($isim === '' || mb_strlen($isim) > 120) {
        throw new DomainException('Mağaza adı geçersiz.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('E-posta adresi geçersiz.');
    }
    if (mb_strlen($magazaSifre) < 8) {
        throw new DomainException('Mağaza şifresi en az 8 karakter olmalı.');
    }
    if (trim($adminKullanici) === '' || mb_strlen($adminSifre) < 8) {
        throw new DomainException('Yönetici kullanıcı adı ve en az 8 karakterlik bir şifre gerekli.');
    }
    if (merkez_row('SELECT id FROM magazalar WHERE email = ?', [$email])) {
        throw new DomainException('Bu e-posta adresiyle zaten bir mağaza kayıtlı.');
    }

    $denemeBitis = date('Y-m-d', strtotime('+30 days'));
    try {
        merkez_q(
            'INSERT INTO magazalar (isim, email, sifre_hash, admin_ad, admin_kullanici, admin_sifre_hash, plan, deneme_bitis, durum)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$isim, $email, password_hash($magazaSifre, PASSWORD_DEFAULT), $adminAd !== '' ? $adminAd : $adminKullanici,
             $adminKullanici, password_hash($adminSifre, PASSWORD_DEFAULT), 'deneme', $denemeBitis, 'beklemede']
        );
    } catch (PDOException $e) {
        throw new DomainException(
            str_contains($e->getMessage(), 'uq_magaza_email') ? 'Bu e-posta adresiyle zaten bir mağaza kayıtlı.' : 'Kayıt oluşturulamadı, lütfen tekrar deneyin.'
        );
    }
    $magazaId = (int) merkez_db()->lastInsertId();

    // Otomatik sağlamayı dene: sunucu izin veriyorsa mağaza bu adımda anında aktif olur.
    $dbAdi = tenant_db_adi_uret($isim);
    $sunucu = config('db', []);
    try {
        merkez_db()->exec("CREATE DATABASE IF NOT EXISTS `$dbAdi` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e) {
        // 1044/1045: yetki yok — beklemede kalır, ürün sahibi merkez panelden elle etkinleştirir.
        return merkez_row('SELECT * FROM magazalar WHERE id = ?', [$magazaId]);
    }
    try {
        tenant_etkinlestir($magazaId, (string) ($sunucu['host'] ?? 'localhost'), $dbAdi, (string) ($sunucu['user'] ?? ''), (string) ($sunucu['password'] ?? ''), (int) ($sunucu['port'] ?? 0) ?: null);
    } catch (DomainException $e) {
        // 4.20.1: veritabanı hata ayrıntısı ziyaretçiye gösterilmez; başvuru beklemede kalır, merkez panelden etkinleştirilir.
        app_log('tenant_basvuru #' . $magazaId . ': ' . $e->getMessage());
    }
    return merkez_row('SELECT * FROM magazalar WHERE id = ?', [$magazaId]);
}

/**
 * BEKLEMEDE bir mağazayı etkinleştirir: verilen veritabanı bilgilerine bağlanıp mevcut (değişmeyen) göç
 * sistemini (run_migrations) çalıştırır, ilk yöneticiyi (kayıtta saklanan bilgilerle) oluşturur, mağaza
 * kaydını 'aktif' yapar. Otomatik modda tenant_basvuru() kendisi çağırır; yarı-otomatik modda merkez-panel.php
 * bu veritabanını SİZİN Plesk'te önceden oluşturmuş olmanız üzerine çağırır.
 */
function tenant_etkinlestir(int $magazaId, string $dbHost, string $dbName, string $dbUser, string $dbSifre, ?int $dbPort = null): void
{
    $m = merkez_row('SELECT * FROM magazalar WHERE id = ?', [$magazaId]);
    if (!$m) {
        throw new DomainException('Mağaza bulunamadı.');
    }
    $baglanti = ['host' => $dbHost, 'port' => $dbPort, 'name' => $dbName, 'user' => $dbUser, 'password' => $dbSifre];
    db_baglanti_degistir($baglanti);
    try {
        run_migrations();
        if (!(int) scalar('SELECT COUNT(*) FROM user_accounts WHERE username = ?', [$m['admin_kullanici']])) {
            insert('user_accounts', [
                'full_name'     => $m['admin_ad'],
                'username'      => $m['admin_kullanici'],
                'password_hash' => $m['admin_sifre_hash'],   // kayıt anında zaten hash'lenmişti
                'role'          => 'super_yetkili',
            ]);
        }
        setting_set('shop_name', $m['isim']);
    } catch (Throwable $e) {
        throw new DomainException('Veritabanına bağlanılamadı ya da kurulamadı: ' . $e->getMessage());
    }
    merkez_q(
        'UPDATE magazalar SET db_host = ?, db_name = ?, db_user = ?, db_sifre = ?, durum = ? WHERE id = ?',
        [$dbHost, $dbName, $dbUser, $dbSifre, 'aktif', $magazaId]
    );
}

const MERKEZ_GIRIS_MAX = 8;          // pencere başına en çok hatalı deneme
const MERKEZ_GIRIS_PENCERE_DK = 15;  // dakika

/** Bu IP merkez panel girişinde geçici olarak kilitli mi? */
function merkez_admin_kilitli(): bool
{
    try {
        $since = date('Y-m-d H:i:s', time() - MERKEZ_GIRIS_PENCERE_DK * 60);
        $n = (int) merkez_scalar(
            'SELECT COUNT(*) FROM merkez_giris_denemeleri WHERE ip = ? AND basarili = 0 AND created_at > ?',
            [client_ip(), $since]
        );
        return $n >= MERKEZ_GIRIS_MAX;
    } catch (Throwable $e) {
        return false; // tablo/DB sorunu girişleri tümden engellemesin
    }
}

/**
 * Ürün sahibinin (sizin) merkez panele girişi. Şifre config.php'de tanımlanır:
 *  - 'merkez_admin_password_hash' (password_hash ile üretilmiş) TERCİH EDİLİR, ya da
 *  - 'merkez_admin_password' (düz metin — geriye dönük uyumluluk için).
 * IP bazlı kaba kuvvet freni uygulanır; başarıda oturum kimliği yenilenir.
 */
function merkez_admin_giris(string $sifre): bool
{
    if (merkez_admin_kilitli()) {
        return false;
    }
    $hash = (string) config('merkez_admin_password_hash', '');
    $duz  = (string) config('merkez_admin_password', '');
    $ok = false;
    if ($hash !== '') {
        $ok = password_verify($sifre, $hash);
    } elseif ($duz !== '') {
        $ok = hash_equals($duz, $sifre);
    }
    try {
        merkez_q('INSERT INTO merkez_giris_denemeleri (ip, basarili) VALUES (?, ?)', [client_ip(), $ok ? 1 : 0]);
        if ($ok) {
            merkez_q('DELETE FROM merkez_giris_denemeleri WHERE ip = ? OR created_at < ?',
                [client_ip(), date('Y-m-d H:i:s', time() - 86400)]);
        }
    } catch (Throwable $e) {
        // deneme kaydı tutulamadı: giriş kararını etkilemesin
    }
    if (!$ok) {
        return false;
    }
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true); // oturum sabitlemeye (session fixation) karşı
    }
    $_SESSION['merkez_admin'] = true;
    merkez_log('giris');
    return true;
}

function merkez_admin_mi(): bool
{
    return ($_SESSION['merkez_admin'] ?? false) === true;
}

/** Tüm mağazalar, en yeni üstte. */
function merkez_magazalar(): array
{
    return merkez_db()->query('SELECT * FROM magazalar ORDER BY (durum = "beklemede") DESC, created_at DESC')->fetchAll();
}

function merkez_magaza_durum_degistir(int $id, string $durum): void
{
    if (!in_array($durum, ['aktif', 'dondu'], true)) {
        throw new DomainException('Geçersiz durum.');
    }
    $mevcut = merkez_row('SELECT durum FROM magazalar WHERE id = ?', [$id]);
    if (!$mevcut || $mevcut['durum'] === 'beklemede') {
        throw new DomainException('Bu mağaza henüz etkinleştirilmedi.');
    }
    merkez_q('UPDATE magazalar SET durum = ? WHERE id = ?', [$durum, $id]);
}

function merkez_magaza_plan_guncelle(int $id, string $plan, ?string $denemeBitis): void
{
    if (!in_array($plan, ['deneme', 'ucretli'], true)) {
        throw new DomainException('Geçersiz plan.');
    }
    if ($denemeBitis !== null && $denemeBitis !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $denemeBitis)) {
        throw new DomainException('Tarih biçimi geçersiz.');
    }
    merkez_q('UPDATE magazalar SET plan = ?, deneme_bitis = ?, guncelleme = NOW() WHERE id = ?', [$plan, $denemeBitis ?: null, $id]);
}

/** 4.11.0 — Mağazanın paketini (Lite / Pro) değiştirir. */
function merkez_magaza_surum_guncelle(int $id, string $surum): void
{
    if (!isset(PAKETLER[$surum])) {
        throw new DomainException('Geçersiz paket.');
    }
    merkez_q('UPDATE magazalar SET surum = ?, guncelleme = NOW() WHERE id = ?', [$surum, $id]);
}

/* ==========================================================================
   MERKEZ PANEL — gelişmiş işlemler
   ========================================================================== */

/** Tek mağaza (yoksa null). */
function merkez_magaza(int $id): ?array
{
    return merkez_row('SELECT * FROM magazalar WHERE id = ?', [$id]);
}

/** Panodaki özet sayılar. */
function merkez_istatistik(): array
{
    $bugun = date('Y-m-d');
    $hafta = date('Y-m-d', strtotime('+7 days'));
    $g = static fn(string $w, array $p = []): int => (int) merkez_scalar("SELECT COUNT(*) FROM magazalar WHERE $w", $p);
    return [
        'toplam'      => $g('1=1'),
        'aktif'       => $g('durum = ?', ['aktif']),
        'beklemede'   => $g('durum = ?', ['beklemede']),
        'dondu'       => $g('durum = ?', ['dondu']),
        'deneme'      => $g('plan = ? AND durum = ?', ['deneme', 'aktif']),
        'ucretli'     => $g('plan = ? AND durum = ?', ['ucretli', 'aktif']),
        'pro'         => $g('surum = ? AND durum = ?', ['pro', 'aktif']),
        'bitiyor'     => $g('plan = ? AND durum = ? AND deneme_bitis IS NOT NULL AND deneme_bitis >= ? AND deneme_bitis <= ?', ['deneme', 'aktif', $bugun, $hafta]),
        'dolmus'      => $g('plan = ? AND durum = ? AND deneme_bitis IS NOT NULL AND deneme_bitis < ?', ['deneme', 'aktif', $bugun]),
        'bu_ay_kayit' => $g('created_at >= ?', [date('Y-m-01 00:00:00')]),
    ];
}

/** Arama + filtre + sıralama ile mağaza listesi. */
function merkez_magaza_listele(array $f = []): array
{
    $where = ['1=1'];
    $p = [];
    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(isim LIKE ? OR email LIKE ? OR db_name LIKE ? OR admin_kullanici LIKE ?)';
        $like = '%' . $q . '%';
        array_push($p, $like, $like, $like, $like);
    }
    if (in_array($f['durum'] ?? '', ['aktif', 'beklemede', 'dondu'], true)) {
        $where[] = 'durum = ?';
        $p[] = $f['durum'];
    }
    if (in_array($f['plan'] ?? '', ['deneme', 'ucretli'], true)) {
        $where[] = 'plan = ?';
        $p[] = $f['plan'];
    }
    if (($f['ozel'] ?? '') === 'bitiyor') {
        $where[] = "plan = 'deneme' AND durum = 'aktif' AND deneme_bitis IS NOT NULL AND deneme_bitis >= ? AND deneme_bitis <= ?";
        array_push($p, date('Y-m-d'), date('Y-m-d', strtotime('+7 days')));
    } elseif (($f['ozel'] ?? '') === 'dolmus') {
        $where[] = "plan = 'deneme' AND durum = 'aktif' AND deneme_bitis IS NOT NULL AND deneme_bitis < ?";
        $p[] = date('Y-m-d');
    }
    $siralar = [
        'yeni'    => '(durum = "beklemede") DESC, created_at DESC',
        'eski'    => 'created_at ASC',
        'isim'    => 'isim ASC',
        'bitis'   => 'deneme_bitis IS NULL, deneme_bitis ASC',
    ];
    $order = $siralar[$f['sirala'] ?? 'yeni'] ?? $siralar['yeni'];
    $sql = 'SELECT * FROM magazalar WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order;
    $st = merkez_db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}

/** Denemeyi N gün uzatır (bugünden ya da mevcut bitişten, hangisi ileriyse). */
function merkez_deneme_uzat(int $id, int $gun): void
{
    if ($gun < 1 || $gun > 3650) {
        throw new DomainException('Gün sayısı geçersiz.');
    }
    $m = merkez_magaza($id);
    if (!$m) {
        throw new DomainException('Mağaza bulunamadı.');
    }
    $baslangic = ($m['deneme_bitis'] && $m['deneme_bitis'] >= date('Y-m-d')) ? $m['deneme_bitis'] : date('Y-m-d');
    $yeni = date('Y-m-d', strtotime($baslangic . ' +' . $gun . ' days'));
    merkez_q('UPDATE magazalar SET plan = ?, deneme_bitis = ?, guncelleme = NOW() WHERE id = ?', ['deneme', $yeni, $id]);
}

/** Mağaza adı ve e-posta düzenle. */
function merkez_magaza_duzenle(int $id, string $isim, string $email): void
{
    $isim = trim($isim);
    $email = trim(strtolower($email));
    if ($isim === '' || mb_strlen($isim) > 120) {
        throw new DomainException('Mağaza adı geçersiz.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('E-posta adresi geçersiz.');
    }
    $baska = merkez_row('SELECT id FROM magazalar WHERE email = ? AND id <> ?', [$email, $id]);
    if ($baska) {
        throw new DomainException('Bu e-posta başka bir mağazada kullanılıyor.');
    }
    merkez_q('UPDATE magazalar SET isim = ?, email = ?, guncelleme = NOW() WHERE id = ?', [$isim, $email, $id]);
}

/** Mağaza notu kaydet. */
function merkez_not_kaydet(int $id, string $not): void
{
    merkez_q('UPDATE magazalar SET notlar = ?, guncelleme = NOW() WHERE id = ?', [mb_substr($not, 0, 4000), $id]);
}

/** Mağazanın GİRİŞ şifresini (magaza-giris.php'de kullanılan) sıfırlar. */
function merkez_giris_sifresi_sifirla(int $id, string $yeni): void
{
    if (mb_strlen($yeni) < 8) {
        throw new DomainException('Yeni şifre en az 8 karakter olmalı.');
    }
    if (!merkez_magaza($id)) {
        throw new DomainException('Mağaza bulunamadı.');
    }
    merkez_q('UPDATE magazalar SET sifre_hash = ?, guncelleme = NOW() WHERE id = ?', [password_hash($yeni, PASSWORD_DEFAULT), $id]);
    magaza_hatirla_hepsini_unut($id);   // 4.18.0: hatırlanan cihazlar yeni şifreyle yeniden girer
}

/** Aktif bir mağazanın veritabanı bağlantı bilgilerini günceller (bağlanarak doğrular). */
function merkez_db_guncelle(int $id, string $host, string $name, string $user, string $sifre, ?int $port = null): void
{
    $m = merkez_magaza($id);
    if (!$m) {
        throw new DomainException('Mağaza bulunamadı.');
    }
    if (trim($name) === '' || trim($user) === '') {
        throw new DomainException('Veritabanı adı ve kullanıcı zorunlu.');
    }
    // Bağlantıyı dene
    $dsn = 'mysql:host=' . ($host ?: 'localhost') . ($port ? ';port=' . $port : '') . ';dbname=' . $name . ';charset=utf8mb4';
    try {
        new PDO($dsn, $user, $sifre, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
    } catch (Throwable $e) {
        throw new DomainException('Bu bilgilerle veritabanına bağlanılamadı; kontrol edip tekrar deneyin.');
    }
    merkez_q('UPDATE magazalar SET db_host = ?, db_name = ?, db_user = ?, db_sifre = ?, guncelleme = NOW() WHERE id = ?',
        [$host ?: 'localhost', $name, $user, $sifre, $id]);
}

/** Mağaza kaydını siler. DİKKAT: yalnızca merkez kaydını siler; mağazanın kendi VERİTABANINI SİLMEZ. */
function merkez_magaza_sil(int $id): void
{
    if (!merkez_magaza($id)) {
        throw new DomainException('Mağaza bulunamadı.');
    }
    merkez_q('DELETE FROM magazalar WHERE id = ?', [$id]);
    magaza_hatirla_hepsini_unut($id);
}

/**
 * Mağazanın kendi veritabanına bağlanıp canlı sağlık bilgisi döndürür (bağlanabilir mi, kaç sipariş/kullanıcı,
 * son giriş, yaklaşık boyut). Salt-okunur; merkez DB'sini değiştirmez. Hataya dayanıklıdır.
 */
function merkez_saglik(array $m): array
{
    $sonuc = ['ok' => false, 'mesaj' => '', 'siparis' => null, 'kullanici' => null, 'son_giris' => null, 'boyut_mb' => null, 'musteri' => null];
    if (($m['durum'] ?? '') === 'beklemede' || empty($m['db_name'])) {
        $sonuc['mesaj'] = 'Mağaza henüz etkinleştirilmemiş.';
        return $sonuc;
    }
    $dsn = 'mysql:host=' . ($m['db_host'] ?: 'localhost') . ';dbname=' . $m['db_name'] . ';charset=utf8mb4';
    try {
        $pdo = new PDO($dsn, (string) $m['db_user'], (string) $m['db_sifre'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 4,
        ]);
        $say = static function (PDO $pdo, string $tablo) {
            try { return (int) $pdo->query("SELECT COUNT(*) FROM `$tablo`")->fetchColumn(); }
            catch (Throwable $e) { return null; }
        };
        $sonuc['siparis']   = $say($pdo, 'orders');
        $sonuc['musteri']   = $say($pdo, 'customers');
        $sonuc['kullanici'] = $say($pdo, 'user_accounts');
        try { $sonuc['son_giris'] = $pdo->query('SELECT MAX(last_login_at) FROM user_accounts')->fetchColumn() ?: null; }
        catch (Throwable $e) {}
        try {
            $st = $pdo->prepare('SELECT ROUND(SUM(data_length + index_length)/1048576, 1) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
            $st->execute([$m['db_name']]);
            $sonuc['boyut_mb'] = $st->fetchColumn();
        } catch (Throwable $e) {}
        $sonuc['ok'] = true;
        $sonuc['mesaj'] = 'Bağlantı başarılı.';
    } catch (Throwable $e) {
        $sonuc['mesaj'] = 'Bağlanılamadı: ' . mb_substr($e->getMessage(), 0, 120);
    }
    return $sonuc;
}

/** Mağaza listesini CSV (metin) olarak döndürür — şifre alanları HARİÇ. */
function merkez_csv(): string
{
    $basliklar = ['id', 'isim', 'email', 'plan', 'durum', 'deneme_bitis', 'db_host', 'db_name', 'db_user', 'kayit', 'admin_kullanici'];
    $ci = fopen('php://temp', 'r+');
    fputcsv($ci, $basliklar, ',', '"', '\\');
    foreach (merkez_db()->query('SELECT * FROM magazalar ORDER BY created_at DESC')->fetchAll() as $m) {
        fputcsv($ci, [
            $m['id'], $m['isim'], $m['email'], $m['plan'], $m['durum'], $m['deneme_bitis'],
            $m['db_host'], $m['db_name'], $m['db_user'], $m['created_at'], $m['admin_kullanici'],
        ], ',', '"', '\\');
    }
    rewind($ci);
    return "\xEF\xBB\xBF" . stream_get_contents($ci); // UTF-8 BOM (Excel Türkçe uyumu)
}

/* ==========================================================================
   İşlem günlüğü (audit)
   ========================================================================== */

/** Merkez panelde yapılan bir işlemi günlüğe yazar (hataya dayanıklı). */
function merkez_log(string $eylem, ?int $magazaId = null, string $detay = '', ?string $isim = null): void
{
    try {
        if ($magazaId && $isim === null) {
            $isim = (string) merkez_scalar('SELECT isim FROM magazalar WHERE id = ?', [$magazaId]);
        }
        merkez_q(
            'INSERT INTO merkez_islem_log (ip, eylem, magaza_id, magaza_isim, detay) VALUES (?, ?, ?, ?, ?)',
            [client_ip(), $eylem, $magazaId ?: null, $isim ?: null, mb_substr($detay, 0, 255)]
        );
    } catch (Throwable $e) {
        // günlük tutulamazsa işlem yine de sürsün
    }
}

function merkez_audit_listele(int $limit = 40): array
{
    try {
        $st = merkez_db()->prepare('SELECT * FROM merkez_islem_log ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)));
        $st->execute();
        return $st->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

function merkez_eylem_etiket(string $e): string
{
    return [
        'giris' => 'Panele giriş', 'etkinlestir' => 'Mağaza etkinleştirildi', 'durum' => 'Durum değişti',
        'plan' => 'Plan güncellendi', 'surum' => 'Paket değiştirildi', 'ozellik' => 'Özellikler değiştirildi', 'uzat' => 'Deneme uzatıldı', 'duzenle' => 'Bilgiler düzenlendi',
        'not' => 'Not güncellendi', 'sifre' => 'Giriş şifresi sıfırlandı', 'db' => 'Veritabanı güncellendi',
        'sil' => 'Mağaza silindi', 'olustur' => 'Mağaza oluşturuldu', 'gir' => 'Mağaza paneline girildi',
        'kul_sifre' => 'Personel şifresi sıfırlandı', 'kul_durum' => 'Personel durumu değişti',
        'toplu' => 'Toplu işlem', 'rehber' => 'Rehber yazısı', 'seo' => 'SEO · Google', 'tasima' => 'Veri taşıma',
    ][$e] ?? $e;
}

/* ==========================================================================
   Tenant (mağaza) personel kullanıcı yönetimi — mağazanın kendi veritabanında
   ========================================================================== */

/** Mağazanın veritabanına bağlanır (yoksa istisna). */
function merkez_tenant_pdo(array $m): PDO
{
    if (($m['durum'] ?? '') === 'beklemede' || empty($m['db_name'])) {
        throw new DomainException('Mağaza henüz etkinleştirilmemiş.');
    }
    $dsn = 'mysql:host=' . ($m['db_host'] ?: 'localhost') . ';dbname=' . $m['db_name'] . ';charset=utf8mb4';
    try {
        return new PDO($dsn, (string) $m['db_user'], (string) $m['db_sifre'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    } catch (Throwable $e) {
        throw new DomainException('Mağaza veritabanına bağlanılamadı.');
    }
}

/** Mağazanın personel kullanıcıları. */
function merkez_tenant_kullanicilar(array $m): array
{
    try {
        $pdo = merkez_tenant_pdo($m);
        return $pdo->query('SELECT id, full_name, username, role, is_active, last_login_at FROM user_accounts ORDER BY (role = "super_yetkili") DESC, id')->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}

/** Bir personel kullanıcının şifresini sıfırlar (mağaza veritabanında). */
function merkez_tenant_sifre_sifirla(array $m, int $userId, string $yeni): void
{
    if (mb_strlen($yeni) < 8) {
        throw new DomainException('Yeni şifre en az 8 karakter olmalı.');
    }
    $pdo = merkez_tenant_pdo($m);
    $st = $pdo->prepare('UPDATE user_accounts SET password_hash = ?, password_changed_at = ? WHERE id = ?');
    $st->execute([password_hash($yeni, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $userId]);
    if (!$st->rowCount()) {
        throw new DomainException('Kullanıcı bulunamadı.');
    }
}

/** Bir personel kullanıcıyı aktif/pasif yapar. Süper yetkili pasife alınmaz (kilitlenmeyi önler). */
function merkez_tenant_kullanici_durum(array $m, int $userId, bool $aktif): void
{
    $pdo = merkez_tenant_pdo($m);
    $u = $pdo->prepare('SELECT role, is_active FROM user_accounts WHERE id = ?');
    $u->execute([$userId]);
    $row = $u->fetch();
    if (!$row) {
        throw new DomainException('Kullanıcı bulunamadı.');
    }
    if (!$aktif && $row['role'] === 'super_yetkili') {
        $kalan = (int) $pdo->query('SELECT COUNT(*) FROM user_accounts WHERE role = "super_yetkili" AND is_active = 1')->fetchColumn();
        if ($kalan <= 1) {
            throw new DomainException('Son aktif süper yetkiliyi pasife alamazsınız.');
        }
    }
    $st = $pdo->prepare('UPDATE user_accounts SET is_active = ? WHERE id = ?');
    $st->execute([$aktif ? 1 : 0, $userId]);
}

/**
 * DESTEK: merkez yöneticisini o mağazanın paneline sokar (mağazanın süper yetkilisi olarak).
 * Mağaza oturumunu ve ilk aktif süper yetkili kullanıcının oturumunu kurar; merkez yetkisi de korunur
 * (merkez-panel.php'ye dönebilirsiniz). Salt-okunur DEĞİLDİR: dikkatli kullanın.
 */
function merkez_magaza_gir(int $id): void
{
    $m = merkez_magaza($id);
    if (!$m) {
        throw new DomainException('Mağaza bulunamadı.');
    }
    if ($m['durum'] !== 'aktif') {
        throw new DomainException('Yalnızca aktif mağazanın paneline girebilirsiniz.');
    }
    $pdo = merkez_tenant_pdo($m);
    $u = $pdo->query('SELECT * FROM user_accounts WHERE role = "super_yetkili" AND is_active = 1 ORDER BY id LIMIT 1')->fetch();
    if (!$u) {
        throw new DomainException('Mağazada aktif bir süper yetkili kullanıcı yok.');
    }
    merkez_log('gir', $id, 'kullanıcı: ' . $u['username'], $m['isim']);
    // Oturumu kur: hem mağaza bağlamı hem de o kullanıcının girişi (auth.php current_user ile uyumlu)
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION = [
        'merkez_admin' => true,            // merkez yetkisi korunur → panele dönülebilir
        'merkez_impersonate' => (int) $id, // hangi mağazaya girildiğini işaretle
        'user_id'   => (int) $u['id'],
        'user_magaza' => (int) $m['id'],
        'pw_stamp'  => (string) $u['password_changed_at'],
        'last_seen' => time(),
        'csrf'      => bin2hex(random_bytes(32)),
        'magaza'    => [
            'id'    => (int) $m['id'],
            'isim'  => $m['isim'],
            'plan'  => $m['plan'],
            'bitis' => $m['deneme_bitis'],
            'durum' => $m['durum'],
            'surum' => (string) ($m['surum'] ?? 'lite'),
            'ozellikler' => ozellik_listesi_temizle($m['ozellikler'] ?? ''),
        ],
    ];
}

/**
 * Merkez panelden elle yeni mağaza oluşturur. tenant_basvuru() akışını kullanır:
 * sunucu izin veriyorsa otomatik aktif olur, vermiyorsa beklemede kalır.
 */
function merkez_magaza_olustur(string $isim, string $email, string $girisSifre, string $adminAd, string $adminKullanici, string $adminSifre): array
{
    $m = tenant_basvuru($isim, $email, $girisSifre, $adminAd, $adminKullanici, $adminSifre);
    merkez_log('olustur', (int) ($m['id'] ?? 0), 'durum: ' . ($m['durum'] ?? '?'), $isim);
    return $m;
}

const MAGAZA_GIRIS_PENCERE_SN = 15 * 60;
const MAGAZA_GIRIS_MAX_IP = 20;       // bir IP'den 15 dakikada en çok hatalı mağaza girişi
const MAGAZA_GIRIS_MAX_EPOSTA = 10;   // bir mağaza e-postasına 15 dakikada en çok hatalı deneme
const KAYIT_MAX_IP_GUN = 3;           // bir IP'den 24 saatte en çok yeni mağaza

/**
 * 4.20.1 — Hız sınırı: $tur için bu IP'den (ve verildiyse bu anahtardan) pencere içinde sınır aşıldı mı?
 * Tablo/veritabanı sorunu girişleri tümden engellemesin diye hata durumunda false döner.
 */
function merkez_hiz_asildi(string $tur, string $anahtar, int $azamiIp, int $azamiAnahtar, int $pencereSn): bool
{
    try {
        $since = date('Y-m-d H:i:s', time() - $pencereSn);
        $ip = (int) merkez_scalar('SELECT COUNT(*) FROM merkez_hiz_siniri WHERE tur = ? AND ip = ? AND created_at > ?', [$tur, client_ip(), $since]);
        if ($ip >= $azamiIp) {
            return true;
        }
        return $azamiAnahtar > 0 && $anahtar !== ''
            && (int) merkez_scalar('SELECT COUNT(*) FROM merkez_hiz_siniri WHERE tur = ? AND anahtar = ? AND created_at > ?', [$tur, $anahtar, $since]) >= $azamiAnahtar;
    } catch (Throwable $e) {
        return false;
    }
}

function merkez_hiz_kaydet(string $tur, string $anahtar = ''): void
{
    try {
        merkez_q('INSERT INTO merkez_hiz_siniri (tur, ip, anahtar, created_at) VALUES (?, ?, ?, ?)', [$tur, client_ip(), mb_substr($anahtar, 0, 190), date('Y-m-d H:i:s')]);
        if (random_int(1, 50) === 1) {
            merkez_q('DELETE FROM merkez_hiz_siniri WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 2 * 86400)]);
        }
    } catch (Throwable $e) {
        // kayıt tutulamadı: akış engellenmesin
    }
}

/**
 * Mağaza girişi (1. adım). Başarılıysa oturuma yazar ve mağaza satırını döner; değilse null.
 * 4.20.1: kaba kuvvete karşı IP ve e-posta başına sınır; sınır aşıldıysa doğru şifre de kabul edilmez.
 */
function tenant_giris(string $email, string $sifre): ?array
{
    $email = mb_substr(trim(strtolower($email)), 0, 190);
    if (merkez_hiz_asildi('magaza_giris', $email, MAGAZA_GIRIS_MAX_IP, MAGAZA_GIRIS_MAX_EPOSTA, MAGAZA_GIRIS_PENCERE_SN)) {
        return null;
    }
    $m = merkez_row('SELECT * FROM magazalar WHERE email = ?', [$email]);
    if (!$m || !password_verify($sifre, $m['sifre_hash'])) {
        merkez_hiz_kaydet('magaza_giris', $email);
        return null;
    }
    tenant_oturum_ac($m);
    return $m;
}

/** Mağaza girişi şu an kilitli mi (giriş ekranında açıklayıcı mesaj için)? */
function tenant_giris_kilitli(string $email): bool
{
    return merkez_hiz_asildi('magaza_giris', mb_substr(trim(strtolower($email)), 0, 190), MAGAZA_GIRIS_MAX_IP, MAGAZA_GIRIS_MAX_EPOSTA, MAGAZA_GIRIS_PENCERE_SN);
}

function tenant_oturum_ac(array $magaza): void
{
    $_SESSION['magaza'] = [
        'id'    => (int) $magaza['id'],
        'isim'  => $magaza['isim'],
        'plan'  => $magaza['plan'],
        'bitis' => $magaza['deneme_bitis'],
        'durum' => $magaza['durum'],
        'surum' => (string) ($magaza['surum'] ?? 'lite'),   // 4.11.0 Lite/Pro
        'ozellikler' => ozellik_listesi_temizle($magaza['ozellikler'] ?? ''),   // 4.12.0
    ];
}

/** Oturumdaki mağaza bilgisi (yalnızca session'dan; güncel durum kontrolü tenant_gereksin() içinde). */
function tenant_oturum(): ?array
{
    return $_SESSION['magaza'] ?? null;
}

/**
 * 4.10.0 — Chrome eklentisi köprü uç noktası (sgk-aktar.php?action=kopru) için mağaza seçimi.
 * Eklenti oturum çerezi taşımaz; bu yüzden eskiden istek mağaza girişine yönleniyor ve köprü
 * çok mağazalı yapıda HİÇ çalışmıyordu. Artık köprü adresi mağaza numarasını taşır (&m=ID);
 * bu fonksiyon o mağazanın veritabanını OTURUM AÇMADAN seçer. Kimlik doğrulama değişmedi:
 * uç nokta yine kullanıcıya özel 40 haneli anahtarı BU mağazanın kendi user_accounts
 * tablosunda arar (başka mağazanın anahtarı burada eşleşemez).
 * Mağazanın var olup olmadığı sızdırılmaz: bulunamayan / kapalı mağaza da "anahtar tanınmadı" alır.
 */
function kopru_tenant_bagla(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
    $red = static function (string $hata): void {
        http_response_code(401);
        echo json_encode(['ok' => false, 'hata' => $hata], JSON_UNESCAPED_UNICODE);
        exit;
    };
    $id = (int) ($_GET['m'] ?? 0);
    if ($id <= 0) {
        $red('Köprü adresi eski: OptiFlow › SGK reçete aktar sayfasındaki güncel köprü adresini eklentiye girin.');
    }
    $m = merkez_row('SELECT * FROM magazalar WHERE id = ?', [$id]);
    $acik = $m
        && $m['durum'] === 'aktif'
        && !($m['plan'] === 'deneme' && $m['deneme_bitis'] !== null && $m['deneme_bitis'] < date('Y-m-d'));
    if (!$acik) {
        $red('Köprü anahtarı tanınmadı');
    }
    // 4.11.0 — Medula aktarımı OptiFlow Pro özelliğidir. Eklenti, Pro mağazalar için geçiş
    // süresince çalışır; Lite mağazaya açıklayıcı bir yanıt döner.
    if (($m['surum'] ?? 'lite') !== 'pro') {
        http_response_code(403);
        echo json_encode(['ok' => false, 'hata' => 'Medula aktarımı OptiFlow Pro paketindedir.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    db_baglanti_degistir(['host' => $m['db_host'], 'port' => null, 'name' => $m['db_name'], 'user' => $m['db_user'], 'password' => $m['db_sifre']]);
}

/**
 * Her "mağaza sayfası" isteğinin en başında çağrılır (bootstrap.php). Oturumda mağaza yoksa giriş sayfasına
 * yönlendirir. Varsa güncel durumunu merkezden TAZE okur (plan/dondurma anında etkili olsun diye); beklemede,
 * dondurulmuş ya da deneme süresi geçmiş mağazalar uygulama yerine açıklayıcı bir sayfa görür. Her şey
 * yolundaysa config('db')'yi bu mağazanın veritabanına çevirir.
 */
function tenant_gereksin(): void
{
    $s = tenant_oturum();
    if (!$s) {
        redirect('magaza-giris.php');
    }
    $guncel = merkez_row('SELECT * FROM magazalar WHERE id = ?', [$s['id']]);
    if (!$guncel) {
        logout_session();
        redirect('magaza-giris.php');
    }
    tenant_oturum_ac($guncel);
    if ($guncel['durum'] === 'beklemede') {
        render_error_page('Hesabınız hazırlanıyor', 'Mağaza kaydınız alındı; kısa süre içinde etkinleştirilip size haber verilecek. Birkaç saat içinde tekrar deneyin.');
    }
    if ($guncel['durum'] !== 'aktif') {
        render_error_page('Hesap dondurulmuş', 'Mağaza hesabınız şu anda kullanıma kapalı. Bilgi için bizimle iletişime geçin.');
    }
    if ($guncel['plan'] === 'deneme' && $guncel['deneme_bitis'] !== null && $guncel['deneme_bitis'] < date('Y-m-d')) {
        render_error_page(
            'Deneme süreniz doldu',
            $guncel['deneme_bitis'] . ' tarihinde sona eren 30 günlük ücretsiz deneme süreniz doldu. '
            . 'Devam etmek için bizimle iletişime geçin; verileriniz saklanmaya devam ediyor.'
        );
    }
    db_baglanti_degistir(['host' => $guncel['db_host'], 'port' => null, 'name' => $guncel['db_name'], 'user' => $guncel['db_user'], 'password' => $guncel['db_sifre']]);
}

/**
 * 4.12.0 — Oturumsuz, sunucudan sunucuya çağrılar (PayTR ödeme bildirimi, zamanlanmış görev)
 * için mağaza veritabanını seçer. Mağaza yoksa / kapalıysa null döner ve HİÇBİR şeyi değiştirmez.
 * Oturum AÇMAZ; çağıran uç nokta kendi doğrulamasını (imza/hash) yapmak zorundadır.
 */
function magaza_baglan_id(int $id, bool $sadeceAktif = true): ?array
{
    if ($id <= 0) {
        return null;
    }
    $m = merkez_row('SELECT * FROM magazalar WHERE id = ?', [$id]);
    // Ödeme bildirimi gibi "para zaten alındı" kayıtları dondurulmuş mağazada da işlenir.
    if (!$m || !in_array($m['durum'], $sadeceAktif ? ['aktif'] : ['aktif', 'dondu'], true) || empty($m['db_name'])) {
        return null;
    }
    db_baglanti_degistir(['host' => $m['db_host'], 'port' => null, 'name' => $m['db_name'], 'user' => $m['db_user'], 'password' => $m['db_sifre']]);
    return $m;
}
