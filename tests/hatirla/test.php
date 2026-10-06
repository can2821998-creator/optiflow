<?php
/* 4.18.0 "Beni hatırla" (app/hatirla.php): mağaza + personel çerezi. İki SQLite veritabanı (merkez + mağaza). */
declare(strict_types=1);
date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');
$gecen = 0;
$kalan = 0;
function dogru(string $ad, bool $k, string $ek = ''): void
{
    global $gecen, $kalan;
    if ($k) {
        $gecen++;
    } else {
        $kalan++;
        echo "  ✗ $ad" . ($ek !== '' ? " — $ek" : '') . "\n";
    }
}

/* --- düzenek --------------------------------------------------------- */
$GLOBALS['__ayar'] = [];
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0) Chrome/140.0 Safari/537.36';
function sqlite(): PDO
{
    return new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
}
$GLOBALS['__merkez'] = sqlite();
$GLOBALS['__merkez']->exec("CREATE TABLE magazalar (id INTEGER PRIMARY KEY, isim TEXT, email TEXT, sifre_hash TEXT, plan TEXT DEFAULT 'pro', deneme_bitis TEXT NULL, durum TEXT DEFAULT 'aktif', surum TEXT DEFAULT 'pro', ozellikler TEXT NULL)");
$GLOBALS['__merkez']->exec("CREATE TABLE magaza_hatirla (id INTEGER PRIMARY KEY AUTOINCREMENT, magaza_id INTEGER NOT NULL, secici TEXT NOT NULL UNIQUE, dogrulayici_hash TEXT NOT NULL,
    sifre_damga TEXT NOT NULL, cihaz TEXT NOT NULL DEFAULT '', olusturma TEXT NOT NULL, son_kullanim TEXT NULL, bitis TEXT NOT NULL)");
$GLOBALS['__merkez']->exec("INSERT INTO magazalar (id, isim, email, sifre_hash) VALUES (7, 'Poyraz Optik', 'a@b.c', 'HASH-1'), (8, 'Başka Optik', 'x@y.z', 'HASH-X')");
$GLOBALS['__magaza'] = sqlite();
$GLOBALS['__magaza']->exec("CREATE TABLE user_accounts (id INTEGER PRIMARY KEY, full_name TEXT, username TEXT, role TEXT, is_active INTEGER DEFAULT 1, password_changed_at TEXT NULL, last_login_at TEXT NULL)");
$GLOBALS['__magaza']->exec("INSERT INTO user_accounts (id, full_name, username, role, password_changed_at) VALUES (1, 'Patron', 'patron', 'super_yetkili', '2026-09-01 10:00:00'), (2, 'Personel', 'per', 'personel', NULL)");
// migrate_v30_beni_hatirla ile aynı sütunlar
$GLOBALS['__magaza']->exec("CREATE TABLE oturum_hatirla (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, magaza_id INTEGER NOT NULL DEFAULT 0, secici TEXT NOT NULL UNIQUE,
    dogrulayici_hash TEXT NOT NULL, pw_stamp TEXT NOT NULL DEFAULT '', cihaz TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL, last_used_at TEXT NULL, expires_at TEXT NOT NULL)");

function mq(PDO $p, string $sql, array $a): PDOStatement { $s = $p->prepare($sql); $s->execute(array_values($a)); return $s; }
function merkez_row(string $sql, array $a = []): ?array { $r = mq($GLOBALS['__merkez'], $sql, $a)->fetch(); return $r === false ? null : $r; }
function merkez_q(string $sql, array $a = []): void { mq($GLOBALS['__merkez'], $sql, $a); }
function merkez_scalar(string $sql, array $a = []): mixed { $v = mq($GLOBALS['__merkez'], $sql, $a)->fetchColumn(); return $v === false ? null : $v; }
function q(string $sql, array $a = []): PDOStatement { return mq($GLOBALS['__magaza'], $sql, $a); }
function row(string $sql, array $a = []): ?array { $r = q($sql, $a)->fetch(); return $r === false ? null : $r; }
function rows(string $sql, array $a = []): array { return q($sql, $a)->fetchAll(); }
function scalar(string $sql, array $a = []): mixed { $v = q($sql, $a)->fetchColumn(); return $v === false ? null : $v; }
function insert(string $t, array $d): int { $c = array_keys($d); q('INSERT INTO ' . $t . ' (' . implode(',', $c) . ') VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($d)); return (int) $GLOBALS['__magaza']->lastInsertId(); }
function setting(string $k, string $d = ''): string { return $GLOBALS['__ayar'][$k] ?? $d; }
function is_optiflow_desktop(): bool { return str_contains($_SERVER['HTTP_USER_AGENT'], 'OptiFlowDesktop/'); }
function tenant_oturum(): ?array { return $_SESSION['magaza'] ?? null; }
function tenant_oturum_ac(array $m): void { $_SESSION['magaza'] = ['id' => (int) $m['id'], 'isim' => $m['isim']]; }
function oturum_kullanici_yaz(array $u): void
{
    $m = $_SESSION['magaza'] ?? null;
    $_SESSION = ['user_id' => (int) $u['id'], 'pw_stamp' => (string) $u['password_changed_at'], 'csrf' => 'x'];
    if ($m) { $_SESSION['magaza'] = $m; }
}
$_SESSION = [];
$_COOKIE = [];
require dirname(__DIR__, 2) . '/app/hatirla.php';

$magaza = merkez_row('SELECT * FROM magazalar WHERE id = 7');
$patron = row('SELECT * FROM user_accounts WHERE id = 1');
$tazeCihaz = function (): void { $_SESSION = []; };   // tarayıcı kapandı: oturum yok, çerezler duruyor

/* --- mağaza ---------------------------------------------------------- */
dogru('çerez yokken bir şey yapmaz', magaza_hatirla_dene() === null && tenant_oturum() === null);
magaza_hatirla_ver($magaza);
$c = (string) ($_COOKIE[HATIRLA_MAGAZA_CEREZ] ?? '');
dogru('mağaza çerezi biçimi seçici.doğrulayıcı', (bool) preg_match('/^[a-f0-9]{24}\.[a-f0-9]{64}$/', $c), $c);
$k = merkez_row('SELECT * FROM magaza_hatirla');
dogru('sunucuda doğrulayıcının kendisi YOK, yalnızca özeti', $k && !str_contains(json_encode($k), substr($c, 25)) && $k['dogrulayici_hash'] === hash('sha256', substr($c, 25)));
dogru('çerez 30 gün', abs($GLOBALS['__cerez_yazilan'][HATIRLA_MAGAZA_CEREZ][1] - (time() + 30 * 86400)) < 5);
dogru('cihaz adı kaydedildi', $k['cihaz'] === 'Chrome · Windows', $k['cihaz']);
$tazeCihaz();
$m = magaza_hatirla_dene();
dogru('mağaza oturumu çerezle kuruldu', $m && (int) tenant_oturum()['id'] === 7);
dogru('son kullanım yazıldı', merkez_scalar('SELECT son_kullanim FROM magaza_hatirla') !== null);

// sahte doğrulayıcı → kayıt silinir
$tazeCihaz();
$_COOKIE[HATIRLA_MAGAZA_CEREZ] = substr($c, 0, 25) . str_repeat('0', 64);
dogru('yanlış doğrulayıcı reddedilir', magaza_hatirla_dene() === null && tenant_oturum() === null);
dogru('…ve o kayıt silinir (çalınmış çerez)', (int) merkez_scalar('SELECT COUNT(*) FROM magaza_hatirla') === 0);
dogru('…çerez de silinir', !isset($_COOKIE[HATIRLA_MAGAZA_CEREZ]));
dogru('bozuk çerez reddedilir', ($_COOKIE[HATIRLA_MAGAZA_CEREZ] = 'abc') && magaza_hatirla_dene() === null && !isset($_COOKIE[HATIRLA_MAGAZA_CEREZ]));

// mağaza şifresi değişirse geçersiz
magaza_hatirla_ver($magaza);
$tazeCihaz();
merkez_q("UPDATE magazalar SET sifre_hash = 'HASH-2' WHERE id = 7");
dogru('mağaza şifresi değişince çerez geçmez', magaza_hatirla_dene() === null && tenant_oturum() === null);
$magaza = merkez_row('SELECT * FROM magazalar WHERE id = 7');

// süresi dolmuş
magaza_hatirla_ver($magaza);
merkez_q("UPDATE magaza_hatirla SET bitis = '2020-01-01 00:00:00'");
$tazeCihaz();
dogru('süresi dolan çerez geçmez', magaza_hatirla_dene() === null);

// farklı mağaza / hepsini unut
magaza_hatirla_ver($magaza);
magaza_hatirla_unut();
dogru('"Farklı mağaza": kayıt ve çerez silinir', (int) merkez_scalar('SELECT COUNT(*) FROM magaza_hatirla') === 0 && !isset($_COOKIE[HATIRLA_MAGAZA_CEREZ]));
magaza_hatirla_ver($magaza);
dogru('merkez: mağazanın tüm cihazlarını unut', magaza_hatirla_hepsini_unut(7) === 1 && (int) merkez_scalar('SELECT COUNT(*) FROM magaza_hatirla') === 0);

/* --- personel -------------------------------------------------------- */
$_COOKIE = [];
$tazeCihaz();
tenant_oturum_ac($magaza);
kullanici_hatirla_ver($patron);
$kc = (string) ($_COOKIE[HATIRLA_KULLANICI_CEREZ] ?? '');
dogru('personel çerezi verildi', (bool) preg_match('/^[a-f0-9]{24}\.[a-f0-9]{64}$/', $kc));
$satir = row('SELECT * FROM oturum_hatirla');
dogru('mağaza no ve şifre damgası tutuldu', (int) $satir['magaza_id'] === 7 && $satir['pw_stamp'] === '2026-09-01 10:00:00');
$tazeCihaz();
dogru('mağaza oturumu yokken personel çerezi işe yaramaz', kullanici_hatirla_dene() === null && empty($_SESSION['user_id']));
tenant_oturum_ac($magaza);
$u = kullanici_hatirla_dene();
dogru('personel oturumu çerezle kuruldu (mağaza korunur)', $u && (int) $_SESSION['user_id'] === 1 && (int) $_SESSION['magaza']['id'] === 7 && $_SESSION['pw_stamp'] === '2026-09-01 10:00:00');
dogru('son giriş yazıldı', scalar('SELECT last_login_at FROM user_accounts WHERE id = 1') !== null);

// başka mağazada aynı çerez geçmez
$tazeCihaz();
$_SESSION['magaza'] = ['id' => 8, 'isim' => 'Başka'];
dogru('başka mağazanın oturumunda çerez geçmez', kullanici_hatirla_dene() === null && empty($_SESSION['user_id']));

// şifre değişince
kullanici_hatirla_ver($patron);
q("UPDATE user_accounts SET password_changed_at = '2026-10-05 12:00:00' WHERE id = 1");
$tazeCihaz();
tenant_oturum_ac($magaza);
dogru('personel şifresi değişince geçmez', kullanici_hatirla_dene() === null);
$patron = row('SELECT * FROM user_accounts WHERE id = 1');

// pasif kullanıcı
kullanici_hatirla_ver($patron);
q('UPDATE user_accounts SET is_active = 0 WHERE id = 1');
$tazeCihaz();
tenant_oturum_ac($magaza);
dogru('pasif kullanıcı geri gelmez', kullanici_hatirla_dene() === null);
q('UPDATE user_accounts SET is_active = 1 WHERE id = 1');

// mağaza ayarı kapalı
kullanici_hatirla_ver($patron);
$GLOBALS['__ayar']['beni_hatirla'] = '0';
$tazeCihaz();
tenant_oturum_ac($magaza);
dogru('mağaza ayarı kapalıysa çerez geçmez', kullanici_hatirla_dene() === null);
dogru('ayar kapalıyken yeni çerez verilmez', (function () use ($patron) { $once = $_COOKIE[HATIRLA_KULLANICI_CEREZ] ?? ''; kullanici_hatirla_ver($patron); return ($_COOKIE[HATIRLA_KULLANICI_CEREZ] ?? '') === $once; })());
$GLOBALS['__ayar']['beni_hatirla'] = '1';

// çıkış: bu cihaz unutulur
kullanici_hatirla_ver($patron);
$n0 = (int) scalar('SELECT COUNT(*) FROM oturum_hatirla');
kullanici_hatirla_unut();
dogru('"Çıkış": yalnızca bu cihazın kaydı ve çerezi gider', (int) scalar('SELECT COUNT(*) FROM oturum_hatirla') === $n0 - 1 && !isset($_COOKIE[HATIRLA_KULLANICI_CEREZ]));

// iki cihaz, profil listesi, kendi şifresini değiştirme
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0) Chrome/140 OptiFlowDesktop/5.4.0';
kullanici_hatirla_ver($patron);
$masaustu = $_COOKIE[HATIRLA_KULLANICI_CEREZ];
unset($_COOKIE[HATIRLA_KULLANICI_CEREZ]);
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone) Safari/604.1';
kullanici_hatirla_ver($patron);
$liste = kullanici_hatirla_cihazlar(1);
dogru('profil: iki cihaz listelenir', count($liste) === 2 && in_array('OptiFlow Pro (masaüstü)', array_column($liste, 'cihaz'), true), json_encode(array_column($liste, 'cihaz')));
dogru('profil: "bu cihaz" tanınır', count(array_filter($liste, fn($h) => hatirla_bu_cihaz_mi((string) $h['secici']))) === 1);
$_SESSION['user_id'] = 1;
q("UPDATE user_accounts SET password_changed_at = '2026-10-06 09:00:00' WHERE id = 1");
kullanici_hatirla_sifre_degisti(1);
$kalanlar = rows('SELECT * FROM oturum_hatirla WHERE user_id = 1');
dogru('kendi şifresini değiştirince öteki cihaz unutulur, bu cihaz yeni damgayla kalır', count($kalanlar) === 1 && $kalanlar[0]['pw_stamp'] === '2026-10-06 09:00:00' && hatirla_bu_cihaz_mi((string) $kalanlar[0]['secici']));
$_COOKIE[HATIRLA_KULLANICI_CEREZ] = $masaustu;
$tazeCihaz();
tenant_oturum_ac($magaza);
dogru('…masaüstündeki eski çerez artık geçmez', kullanici_hatirla_dene() === null);
dogru('tüm cihazlarda unut', (function () { $patron = row('SELECT * FROM user_accounts WHERE id = 1'); kullanici_hatirla_ver($patron); return kullanici_hatirla_hepsini_unut(1) >= 1 && (int) scalar('SELECT COUNT(*) FROM oturum_hatirla WHERE user_id = 1') === 0; })());

// şifresi hiç değişmemiş kullanıcı (password_changed_at NULL)
$per = row('SELECT * FROM user_accounts WHERE id = 2');
$tazeCihaz();
tenant_oturum_ac($magaza);
kullanici_hatirla_ver($per);
$tazeCihaz();
tenant_oturum_ac($magaza);
dogru('şifre damgası boş kullanıcı da hatırlanır', ($u = kullanici_hatirla_dene()) && (int) $u['id'] === 2);

/* --- kaynak denetimleri ---------------------------------------------- */
$kok = dirname(__DIR__, 2);
$auth = (string) file_get_contents($kok . '/app/auth.php');
dogru('current_user çerezi yalnızca oturum yokken dener', str_contains($auth, 'kullanici_hatirla_dene()'));
dogru('set_password tüm cihazları unutturur', str_contains($auth, 'kullanici_hatirla_sifre_degisti($userId)'));
dogru('çıkış bu cihazı unutur', str_contains((string) file_get_contents($kok . '/app/pages/logout.php'), 'kullanici_hatirla_unut()'));
$mg = (string) file_get_contents($kok . '/app/merkez.php');
dogru('merkez: mağaza şifresi sıfırlanınca hatırlananlar silinir', str_contains($mg, 'magaza_hatirla_hepsini_unut($id)'));
dogru('merkez şeması: magaza_hatirla tablosu', str_contains($mg, 'CREATE TABLE IF NOT EXISTS magaza_hatirla'));
$bs = (string) file_get_contents($kok . '/app/bootstrap.php');
dogru('bootstrap: mağaza çerezi cron/ödeme bildiriminde denenmez', str_contains($bs, "magaza_hatirla_dene();") && str_contains($bs, "['cron.php', 'odeme-bildirim.php'"));
$mig = (string) file_get_contents($kok . '/app/migrations.php');
dogru('göç v30', (bool) preg_match('/const SCHEMA_VERSION = (3[0-9]);/', $mig) && str_contains($mig, 'migrate_v30_beni_hatirla'));
$lp = (string) file_get_contents($kok . '/app/pages/login.php');
dogru('giriş sayfası: kutu + "Farklı mağaza" POST ile', str_contains($lp, 'name="hatirla"') && str_contains($lp, 'value="farkli"') && !str_contains($lp, '<script>'));

echo "Beni hatırla: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
