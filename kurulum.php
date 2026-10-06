<?php
/*
 * Veritabanı bağlantı sihirbazı.
 * YALNIZCA config.php yoksa, hatalıysa veya veritabanına bağlanamıyorsa çalışır.
 * Sistem çalışır durumdayken kendini kapatır (bu sayfadan hiçbir bilgi okunamaz/değiştirilemez).
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Istanbul');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex');

$configFile = __DIR__ . '/config.php';

function try_connect(array $d): ?string
{
    try {
        $pdo = new PDO('mysql:host=' . ($d['host'] ?: 'localhost') . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        $pdo->query('SELECT 1');
        return null;
    } catch (Throwable $e) {
        $m = $e->getMessage();
        if (str_contains($m, '1045')) return 'Kullanıcı adı veya parola hatalı.';
        if (str_contains($m, '1049')) return 'Bu isimde bir veritabanı yok.';
        if (str_contains($m, '1044')) return 'Bu kullanıcının bu veritabanına yetkisi yok.';
        if (str_contains($m, '2002')) return 'Veritabanı sunucusuna ulaşılamadı (sunucu adı yanlış olabilir).';
        return 'Bağlantı kurulamadı.';
    }
}

// Mevcut yapılandırma çalışıyorsa sihirbaz kapalı.
$existing = null;
$state = 'yok';
if (is_file($configFile)) {
    try {
        $existing = include $configFile;
        $state = (is_array($existing) && isset($existing['db']['name'])) ? 'okundu' : 'bozuk';
    } catch (Throwable $e) {
        $state = 'bozuk';
        $existing = null;
    }
}
if ($state === 'okundu' && try_connect($existing['db'] + ['host' => 'localhost', 'user' => '', 'password' => '']) === null) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><title>Kurulum kapalı</title><body style="font:15px system-ui;padding:40px;text-align:center"><h2>Kurulum tamamlanmış</h2><p>Sistem veritabanına bağlı. Bu sayfa güvenlik için kapalıdır.</p><p><a href="index.php">Atölyeye git →</a></p>';
    exit;
}
/*
 * 4.20.1 GÜVENLİK: config.php sağlamken veritabanına o an bağlanılamıyorsa (sunucu kesintisi, bağlantı sınırı
 * dolması…) sihirbaz eskiden HERKESE açılıyordu: biri kendi veritabanı sunucusunu ve merkez şifresini yazıp siteyi
 * ele geçirebilirdi. Artık bu durumda yalnızca hosting'e erişimi olan kişi açabilir: Plesk Dosya Yöneticisi'nde
 * storage/ klasörüne "kurulum-izni" adlı (boş) bir dosya oluşturulur; kayıttan sonra dosya kendiliğinden silinir.
 * config.php hiç yoksa (ilk kurulum) ya da yazım hatalıysa sihirbaz eskisi gibi açıktır.
 */
$izinDosyasi = __DIR__ . '/storage/kurulum-izni';
if ($state === 'okundu' && !is_file($izinDosyasi)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Kurulum kilitli</title><body style="font:15px/1.6 system-ui;padding:40px;max-width:620px;margin:auto">'
        . '<h2>Veritabanına şu an bağlanılamıyor</h2>'
        . '<p>Kısa süreli bir sunucu sorunu olabilir; birkaç dakika sonra <a href="index.php">tekrar deneyin</a>.</p>'
        . '<p>Veritabanı bilgileri gerçekten değiştiyse: Plesk › Dosya Yöneticisi\'nde <b>storage</b> klasörüne <b>kurulum-izni</b> adlı boş bir dosya oluşturun ve bu sayfayı yenileyin. Güvenlik için bu sayfa o dosya olmadan açılmaz; kayıttan sonra dosya kendiliğinden silinir.</p>';
    exit;
}

session_name('optiflow_kurulum');
session_start();
if (empty($_SESSION['k_csrf'])) {
    $_SESSION['k_csrf'] = bin2hex(random_bytes(16));
}

$error = '';
$manual = '';
$form = ['host' => 'localhost', 'name' => '', 'user' => '', 'password' => ''];
// 4.11.0 — eski dosyadaki veritabanı adı/kullanıcısı formu önceden doldurur (parola asla gösterilmez).
if (is_array($existing) && isset($existing['db']) && is_array($existing['db'])) {
    $form['host'] = (string) ($existing['db']['host'] ?? 'localhost');
    $form['name'] = (string) ($existing['db']['name'] ?? '');
    $form['user'] = (string) ($existing['db']['user'] ?? '');
}
$merkezVar = is_array($existing) && (!empty($existing['merkez_admin_password_hash']) || !empty($existing['merkez_admin_password']));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['k_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $error = 'Oturum süresi doldu, sayfayı yenileyin.';
    } else {
        foreach ($form as $k => $v) {
            $form[$k] = trim((string) ($_POST[$k] ?? ''));
        }
        $form['password'] = (string) ($_POST['password'] ?? '');
        $merkezSifre = (string) ($_POST['merkez_sifre'] ?? '');
        if ($form['name'] === '' || $form['user'] === '') {
            $error = 'Veritabanı adı ve kullanıcı adı zorunlu.';
        } elseif ($merkezSifre !== '' && mb_strlen($merkezSifre) < 8) {
            $error = 'Merkez panel şifresi en az 8 karakter olmalı.';
        } elseif ($msg = try_connect($form)) {
            $error = $msg;
        } else {
            /* 4.11.0 — Eski dosyadaki diğer ayarlar (merkez şifresi, SEO anahtarları, ortam…) korunur;
               yalnızca veritabanı bilgisi yenilenir. Yeni merkez şifresi girildiyse HASH olarak yazılır. */
            $config = is_array($existing) ? $existing : [];
            $config['db'] = ['host' => $form['host'] ?: 'localhost', 'name' => $form['name'], 'user' => $form['user'], 'password' => $form['password']];
            $config['timezone'] = $config['timezone'] ?? 'Europe/Istanbul';
            $config['debug'] = false;
            if ($merkezSifre !== '') {
                $config['merkez_admin_password_hash'] = password_hash($merkezSifre, PASSWORD_DEFAULT);
                unset($config['merkez_admin_password']);
            }
            $content = "<?php\n// kurulum.php tarafından " . date('d.m.Y H:i') . " tarihinde oluşturuldu.\nreturn " . var_export($config, true) . ";\n";
            if (is_file($configFile)) {
                @copy($configFile, __DIR__ . '/storage/config-eski-' . date('Ymd-His') . '.php.bak');
            }
            if (@file_put_contents($configFile, $content, LOCK_EX) !== false) {
                @chmod($configFile, 0640);
                @unlink($izinDosyasi);
                header('Location: index.php', true, 303);
                exit;
            }
            $manual = $content;
        }
    }
}
$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Veritabanı bağlantısı · OptiFlow</title>
<style>
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#faf8f4;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Arial,sans-serif;color:#1a1723;padding:16px}
main{width:min(460px,100%);background:#fff;border:1px solid #e7e1d8;border-radius:16px;padding:28px;box-shadow:0 12px 40px rgba(0,0,0,.08)}
h1{margin:0 0 4px;font-size:21px;color:#122f4d}p{margin:0 0 14px;color:#5f5a68}
label{display:grid;gap:5px;margin-bottom:12px;font-size:13px;font-weight:600;color:#45414f}
input{width:100%;min-height:42px;padding:9px 11px;border:1px solid #d5cdc0;border-radius:8px;font:inherit}input:focus{outline:0;border-color:#15395e;box-shadow:0 0 0 3px rgba(21,57,94,.25)}
button{width:100%;min-height:46px;border:0;border-radius:8px;background:#15395e;color:#fff;font:600 15px system-ui;cursor:pointer}
.err{background:#fce2e5;color:#bd1c33;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-weight:600}
.info{background:#fbe9cd;color:#9a5a08;padding:10px 12px;border-radius:8px;margin-bottom:14px;font-size:13px}
textarea{width:100%;height:220px;font:12px ui-monospace,Menlo,monospace;border:1px solid #d5cdc0;border-radius:8px;padding:10px}
small{color:#6b6673}
</style></head>
<body><main>
<h1>Veritabanı bağlantısı</h1>
<p>Plesk › <b>Veritabanları</b> ekranındaki bilgileri girin. Bilgiler doğrulanır ve <code>config.php</code> otomatik, hatasız oluşturulur.</p>
<?php if ($state === 'bozuk'): ?><div class="info">Mevcut config.php hatalı olduğu için bu sayfa açıldı. Kaydedince eski dosyanın yedeği storage klasörüne alınır.</div><?php endif; ?>
<?php if ($error): ?><div class="err"><?= $h($error) ?></div><?php endif; ?>
<?php if ($manual): ?>
  <div class="err">Bağlantı başarılı ama sunucu dosyayı yazmaya izin vermedi.</div>
  <p>Aşağıdaki metnin tamamını kopyalayıp Plesk Dosya Yöneticisi'nde <b><?= $h(basename(__DIR__)) ?>/config.php</b> dosyasının içeriğiyle <b>değiştirin</b>:</p>
  <textarea readonly onclick="this.select()"><?= $h($manual) ?></textarea>
<?php else: ?>
<form method="post" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= $h($_SESSION['k_csrf']) ?>">
  <label>Veritabanı sunucusu<input name="host" value="<?= $h($form['host']) ?>"></label>
  <label>Veritabanı adı<input name="name" value="<?= $h($form['name']) ?>" required autocapitalize="none"></label>
  <label>Veritabanı kullanıcı adı<input name="user" value="<?= $h($form['user']) ?>" required autocapitalize="none"></label>
  <label>Veritabanı parolası<input name="password" type="password" autocomplete="new-password"></label>
  <label>Merkez panel şifresi <?= $merkezVar ? '(boş bırakırsanız mevcut şifre kalır)' : '(merkez paneli açmak için belirleyin)' ?>
    <input name="merkez_sifre" type="password" autocomplete="new-password" minlength="8"></label>
  <button>Bağlantıyı test et ve kaydet</button>
  <p style="margin-top:12px"><small>Eski siparişlerinizin durduğu veritabanını seçin. Sistem çalışmaya başlayınca bu sayfa kendiliğinden kapanır.</small></p>
</form>
<?php endif; ?>
</main></body></html>
