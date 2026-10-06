<?php
/* 4.20.1 güvenlik düzeltmeleri: kurulum.php kilidi, kontrol.php ayrıntı kapısı, oturum/mağaza bağı, hız sınırları.
   Gerçek veritabanı kullanmaz: config.php kapalı bir porta (127.0.0.1:1) işaret eder, bağlantı hemen düşer. */
declare(strict_types=1);
$gecen = 0;
$kalan = 0;
function dogru(string $ad, bool $k, string $ek = ''): void
{
    global $gecen, $kalan;
    if ($k) {
        $gecen++;
    } else {
        $kalan++;
        echo "  ✗ $ad" . ($ek !== '' ? " — " . mb_substr($ek, 0, 300) : '') . "\n";
    }
}

$depo = dirname(__DIR__, 2);
$kok = sys_get_temp_dir() . '/optiflow-guvenlik-' . getmypid();
@mkdir($kok . '/storage/logs', 0777, true);
copy($depo . '/kurulum.php', $kok . '/kurulum.php');
copy($depo . '/kontrol.php', $kok . '/kontrol.php');
file_put_contents($kok . '/config.php', "<?php return ['db' => ['host' => '127.0.0.1;port=1', 'name' => 'x', 'user' => 'u', 'password' => 'p']];\n");
file_put_contents($kok . '/storage/logs/app-2026-10.log', "[2026-10-06 10:00:00] GIZLI-LOG-SATIRI\n");

$cagir = function (string $betik) use ($kok): string {
    $kod = '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["SCRIPT_NAME"]="/' . $betik . '"; require ' . var_export($kok . '/' . $betik, true) . ';';
    $p = proc_open([PHP_BINARY, '-d', 'display_errors=0', '-r', $kod], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $b);
    $out = (string) stream_get_contents($b[1]);
    stream_get_contents($b[2]);
    proc_close($p);
    return $out;
};

/* --- kurulum.php: config sağlam ama veritabanına ulaşılamıyor ---------- */
$o = $cagir('kurulum.php');
dogru('kurulum: izin dosyası yokken kilitli', str_contains($o, 'Kurulum kilitli') && !str_contains($o, 'name="merkez_sifre"'), $o);
touch($kok . '/storage/kurulum-izni');
$o = $cagir('kurulum.php');
dogru('kurulum: storage/kurulum-izni varken form açılır', str_contains($o, 'name="merkez_sifre"'), $o);
dogru('kurulum: eski parola formda gösterilmez', !str_contains($o, 'value="p"'));
unlink($kok . '/storage/kurulum-izni');

/* config.php hiç yokken (ilk kurulum) sihirbaz açık kalır */
rename($kok . '/config.php', $kok . '/config.yedek');
dogru('kurulum: ilk kurulumda (config yok) açık', str_contains($cagir('kurulum.php'), 'name="merkez_sifre"'));
rename($kok . '/config.yedek', $kok . '/config.php');

/* --- kontrol.php: ayrıntılar yalnızca izin dosyasıyla ------------------- */
$o = $cagir('kontrol.php');
dogru('kontrol: dosya/sürüm denetimi herkese açık', str_contains($o, 'Kurulum kontrolü') && str_contains($o, 'PHP sürümü'), $o);
dogru('kontrol: izinsiz hata kaydı gösterilmez', !str_contains($o, 'GIZLI-LOG-SATIRI'));
dogru('kontrol: izinsiz veritabanı hata metni gösterilmez', !str_contains($o, 'SQLSTATE') && !str_contains($o, 'could not find driver'));
touch($kok . '/storage/kurulum-izni');
dogru('kontrol: izin dosyasıyla hata kaydı görünür', str_contains($cagir('kontrol.php'), 'GIZLI-LOG-SATIRI'));

foreach (['kurulum.php', 'kontrol.php', 'config.php', 'storage/kurulum-izni', 'storage/logs/app-2026-10.log'] as $f) {
    @unlink($kok . '/' . $f);
}
@rmdir($kok . '/storage/logs');
@rmdir($kok . '/storage');
@rmdir($kok);

/* --- kaynak denetimleri --------------------------------------------------- */
$auth = (string) file_get_contents($depo . '/app/auth.php');
dogru('oturum: kullanıcı girişin yapıldığı mağazaya bağlı', str_contains($auth, "'user_magaza' =>") && str_contains($auth, "(int) \$_SESSION['user_magaza'] !== \$magazaId"));
dogru('oturum: müşteri sayfasında (?m=) personel oturumu geçmez', str_contains($auth, "\$GLOBALS['__musteri_magaza']"));
dogru('giriş kilidi: müşteri sipariş sorguları IP sayısına girmez', (bool) preg_match('/FROM login_attempts WHERE ip = \? AND attempted_at > \? AND username <> \?/', $auth));
$mg = (string) file_get_contents($depo . '/app/merkez.php');
dogru('merkez: destek girişi de mağazaya bağlı', str_contains($mg, "'user_magaza' => (int) \$m['id']"));
dogru('mağaza girişi: hız sınırı', (bool) preg_match('/function tenant_giris\(.*?merkez_hiz_asildi\(\'magaza_giris\'.*?merkez_hiz_kaydet\(\'magaza_giris\'/s', $mg));
dogru('merkez şeması: merkez_hiz_siniri tablosu', str_contains($mg, 'CREATE TABLE IF NOT EXISTS merkez_hiz_siniri'));
dogru('kayıt: veritabanı hatası ziyaretçiye gösterilmez', str_contains($mg, "app_log('tenant_basvuru #'"));
$ky = (string) file_get_contents($depo . '/app/pages/kayit.php');
dogru('kayıt: IP başına günlük sınır', str_contains($ky, "merkez_hiz_asildi('kayit'") && str_contains($ky, "merkez_hiz_kaydet('kayit'"));

echo "Güvenlik testleri: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
