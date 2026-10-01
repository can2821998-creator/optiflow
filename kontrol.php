<?php
// Kurulum kontrolü: gizli bilgi göstermez. Sorun çözülünce silebilirsiniz.
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
$rows = array();
$ok = version_compare(PHP_VERSION, '8.0.0', '>=');
$rows[] = array('PHP sürümü', PHP_VERSION, $ok);
foreach (array('pdo_mysql', 'mbstring', 'json', 'openssl') as $ext) {
    $rows[] = array('Eklenti: ' . $ext, extension_loaded($ext) ? 'açık' : 'KAPALI', extension_loaded($ext) || $ext === 'mbstring' || $ext === 'openssl');
}
$base = dirname(__FILE__);

/* ---- Sürüm ve "yeni dosyalar gerçekten yüklendi mi?" denetimi ----
   Dosyaları çalıştırmadan, içeriklerinden okur. Yükleme yarım kaldıysa
   (FTP klasörü yanlış yere açıldıysa) burada hemen görünür. */
$icerik = function ($yol) use ($base) {
    $t = $base . '/' . $yol;
    return is_file($t) ? (string) @file_get_contents($t) : '';
};
$surum = '—';
if (preg_match("/const APP_VERSION\s*=\s*'([^']+)'/", $icerik('app/bootstrap.php'), $m)) {
    $surum = $m[1];
}
$rows[] = array('Yüklü sürüm (app/bootstrap.php)', $surum, $surum !== '—');
$vt = trim($icerik('VERSION.txt'));
if (preg_match('/Release:\s*([0-9.]+)/', $vt, $m)) {
    $rows[] = array('VERSION.txt', $m[1], $m[1] === $surum);
}
foreach (array(
    'app/sgk.php'             => array('sgk_musteri_bul', 'yeni çözümleyici + hasta eşleştirme'),
    'app/reminders.php'       => array('reminder_renewals', 'hatırlatma merkezi'),
    'app/frames.php'          => array('frame_move', 'çerçeve stoğu'),
    'app/backup.php'          => array('backup_write', 'yedekleme + günün özeti'),
    'app/labels.php'          => array('label_layouts', 'etiket sihirbazı'),
    'app/pages/tahsilat.php'  => array('Bakiye ve tahsilat', 'bakiye takibi'),
    'app/pages/sgk-aktar.php' => array('Nereye aktarılsın', 'yeni aktarım ekranı'),
    'app/uts.php'             => array('uts_kuyrugu_isle', 'ÜTS bildirimleri (4.13.0)'),
    'app/alis.php'            => array('alis_ubl_coz', 'alış faturası e-Fatura (4.14.0)'),
    'app/senet.php'           => array('vadesi_acik_faturalar', 'senetler ve ödeme takvimi (4.14.0)'),
    'app/pages/uts.php'       => array('ÜTS bildirimleri', 'ÜTS ekranı (4.13.0)'),
    'assets/app.css'          => array('.pick-list', 'yeni seçim listesi biçimi'),
    'kopru-eklenti/icerik.js' => array('kutuSayilirMi', 'yeni köprü betiği'),
    'assets/uts-karekod.js'   => array('OptiFlow ÜTS Karekod 4.7.0', 'ÜTS karekod modülü'),
) as $dosya => $bilgi) {
    $var = $icerik($dosya) !== '' && strpos($icerik($dosya), $bilgi[0]) !== false;
    $tarih = is_file($base . '/' . $dosya) ? date('d.m.Y H:i', (int) filemtime($base . '/' . $dosya)) : '—';
    $rows[] = array('Güncel mi: ' . $dosya, $var ? $bilgi[1] . ' · ' . $tarih : 'ESKİ DOSYA — bu dosya yüklenmemiş (' . $tarih . ')', $var);
}
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    $acik = is_array($st) && !empty($st['opcache_enabled']);
    $rows[] = array('PHP opcache', $acik ? 'açık — dosya yenilendiği halde eski kod çalışıyorsa hosting panelinden temizleyin' : 'kapalı', true);
}

foreach (array('app/bootstrap.php', 'app/helpers.php', 'app/db.php', 'app/pages/login.php', 'app/partials/rx-table.php', 'assets/app.css', 'assets/musteri.css', 'durum.php', 'app/pages/durum.php', 'siparisim-nerede.php', 'app/pages/siparisim-nerede.php', 'app/autobackup.php', 'app/donation.php', 'app/deneme.php', 'app/merkez.php', 'bagis.php', 'app/pages/bagis.php', 'bagis-sayac.php', 'app/pages/bagis-sayac.php', 'app/maker.php', 'app/family.php', 'app/gelisim.php', 'app/partials/gelisim-karti.php', 'app/partials/aile-karti.php', 'tedarikci-karne.php', 'app/pages/tedarikci-karne.php', 'bakim.php', 'app/pages/bakim.php', 'ekran.php', 'app/pages/ekran.php', 'app/partials/ekran-gorunum.php', 'assets/ekran.css', 'app/qr.php', 'push.php', 'app/webpush.php', 'sw.js', 'manifest.php', 'app/sgk.php', 'sgk-aktar.php', 'kopru-eklenti/icerik.js', 'app/reminders.php', 'hatirlatma.php', 'tahsilat.php', 'app/frames.php', 'cerceve.php', 'kar.php', 'yedek.php', 'app/backup.php', 'app/labels.php', 'etiket.php', 'uts-karekod.php', 'app/pages/uts-karekod.php', 'assets/uts-karekod.css', 'assets/uts-karekod.js', 'seo-veri.php', 'rehber.php', 'app/pages/rehber.php', 'app/rehber.php', 'sitemap.php', 'app/pages/sitemap.php') as $f) {
    $rows[] = array('Dosya: ' . $f, is_file($base . '/' . $f) ? 'var' : 'YOK — paketi eksiksiz yükleyin', is_file($base . '/' . $f));
}
$cfgFile = $base . '/config.php';
$cfg = null;
if (!is_file($cfgFile)) {
    $rows[] = array('config.php', 'YOK', false);
} elseif ($ok) {
    try {
        $cfg = include $cfgFile;
        $valid = is_array($cfg) && isset($cfg['db']['name'], $cfg['db']['user']);
        $rows[] = array('config.php', $valid ? 'okundu' : 'okundu ama db bilgileri eksik', $valid);
    } catch (Throwable $e) {
        $rows[] = array('config.php', 'yazım hatası, satır ' . $e->getLine() . ' (tırnak / virgül kontrol edin)', false);
    }
}
if (is_array($cfg) && isset($cfg['db']) && extension_loaded('pdo_mysql')) {
    $d = $cfg['db'];
    try {
        $pdo = new PDO('mysql:host=' . (isset($d['host']) ? $d['host'] : 'localhost') . ';dbname=' . $d['name'] . ';charset=utf8mb4', $d['user'], isset($d['password']) ? $d['password'] : '', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $rows[] = array('Veritabanı bağlantısı', 'başarılı (' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . ')', true);
        $t = $pdo->query("SHOW TABLES LIKE 'orders'")->fetchColumn();
        $rows[] = array('orders tablosu', $t ? 'var (' . $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() . ' sipariş)' : 'YOK — yanlış veritabanı adı olabilir', (bool) $t);
    } catch (Throwable $e) {
        $rows[] = array('Veritabanı bağlantısı', 'BAŞARISIZ: ' . preg_replace('/using password: YES/i', '', $e->getMessage()), false);
    }
}
// Müşteri takip sayfasının gerçek adresi (alt klasör kurulumlarında da doğru)
$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
if ($host !== '') {
    $guvenli = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
    $klasor = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $klasor = ($klasor === '/' || $klasor === '.' || $klasor === '') ? '' : '/' . trim($klasor, '/');
    $takipAdres = ($guvenli ? 'https' : 'http') . '://' . $host . $klasor . '/durum.php';
    $rows[] = array('Müşteri takip sayfası adresi', $takipAdres, is_file($base . '/durum.php'));
}

$logDir = $base . '/storage/logs';
$rows[] = array('storage/logs yazılabilir', is_dir($logDir) && is_writable($logDir) ? 'evet' : 'hayır (kritik değil)', true);
$logs = is_dir($logDir) ? glob($logDir . '/app-*.log') : array();
$last = '';
if ($logs) { rsort($logs); $lines = @file($logs[0]); if ($lines) { $last = implode('', array_slice($lines, -12)); } }
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Kurulum kontrolü</title>
<style>body{margin:0;background:#faf8f4;font:14px/1.5 system-ui,Arial,sans-serif;color:#1a1723;padding:20px}main{max-width:760px;margin:auto;background:#fff;border:1px solid #e7e1d8;border-radius:14px;padding:22px}h1{margin:0 0 14px;font-size:20px;color:#122f4d}table{width:100%;border-collapse:collapse}td{padding:8px;border-bottom:1px solid #eee;vertical-align:top}.ok{color:#12733e;font-weight:700}.no{color:#bd1c33;font-weight:700}pre{white-space:pre-wrap;background:#f6f3ee;padding:10px;border-radius:8px;font-size:12px}</style></head><body><main>
<h1>OptiFlow · Kurulum kontrolü</h1>
<table><?php foreach ($rows as $r): ?><tr><td><?php echo htmlspecialchars($r[0]); ?></td><td class="<?php echo $r[2] ? 'ok' : 'no'; ?>"><?php echo $r[2] ? '✓' : '✗'; ?></td><td><?php echo htmlspecialchars($r[1]); ?></td></tr><?php endforeach; ?></table>
<?php if ($last): ?><h2 style="font-size:15px;margin-top:18px">Son hata kayıtları</h2><pre><?php echo htmlspecialchars($last); ?></pre><?php endif; ?>
</main></body></html>
