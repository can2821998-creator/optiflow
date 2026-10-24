<?php
/* 4.51.0 Merkez e-postası (app/eposta.php): ayar, şablon, kuyruk, olaylar, cron, panel gönderimi, çıkma bağlantısı, SMTP. */
declare(strict_types=1);
date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');
$gecen = 0;
$kalan = 0;
function dogru(string $ad, bool $k, string $ek = ''): void
{
    global $gecen, $kalan;
    if ($k) { $gecen++; } else { $kalan++; echo "  ✗ $ad" . ($ek !== '' ? " — $ek" : '') . "\n"; }
}

$kok = sys_get_temp_dir() . '/of-eposta-' . getmypid();
@mkdir($kok . '/storage', 0777, true);
putenv('UTS_TEST_ROOT=' . $kok);
define('APP_ROOT', $kok);

$GLOBALS['__merkez'] = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$GLOBALS['__merkez']->exec("CREATE TABLE magazalar (id INTEGER PRIMARY KEY, isim TEXT, email TEXT, admin_kullanici TEXT, plan TEXT DEFAULT 'deneme', deneme_bitis TEXT NULL, durum TEXT DEFAULT 'aktif', eposta_ret INTEGER NOT NULL DEFAULT 0)");
$GLOBALS['__merkez']->exec("CREATE TABLE eposta_kuyruk (id INTEGER PRIMARY KEY AUTOINCREMENT, magaza_id INTEGER NULL, kime TEXT NOT NULL, konu TEXT NOT NULL, metin TEXT NOT NULL,
    tur TEXT NOT NULL DEFAULT 'islem', sablon TEXT NOT NULL DEFAULT 'serbest', anahtar TEXT NULL UNIQUE, durum TEXT NOT NULL DEFAULT 'bekliyor', deneme INTEGER NOT NULL DEFAULT 0,
    hata TEXT NULL, created_at TEXT NOT NULL, guncelleme TEXT NULL, gonderildi_at TEXT NULL, gorunum TEXT NULL)");
function mq(string $sql, array $a): PDOStatement { $s = $GLOBALS['__merkez']->prepare($sql); $s->execute(array_values($a)); return $s; }
function merkez_row(string $sql, array $a = []): ?array { $r = mq($sql, $a)->fetch(); return $r === false ? null : $r; }
function merkez_rows(string $sql, array $a = []): array { return mq($sql, $a)->fetchAll(); }
function merkez_q(string $sql, array $a = []): void { mq($sql, $a); }
function merkez_scalar(string $sql, array $a = []): mixed { $v = mq($sql, $a)->fetchColumn(); return $v === false ? null : $v; }
function optiflow_pazarlama(): array { return ['telefon' => '0546 743 82 99']; }

require dirname(__DIR__, 2) . '/app/entegrasyon.php';
require dirname(__DIR__, 2) . '/app/eposta.php';

$giden = [];
$GLOBALS['eposta_tasiyici'] = function (array $a, string $kimden, string $kime, string $ileti) use (&$giden) {
    if (!empty($GLOBALS['__tasima_hatasi'])) { throw new RuntimeException('SMTP sahte hata'); }
    $giden[] = ['kime' => $kime, 'ileti' => $ileti];
};
$coz = function (string $ileti): array {   // konu + düz metin + html
    preg_match('/^Subject: (.*)$/m', $ileti, $s);
    $konu = trim($s[1] ?? '');
    if (preg_match('/^=\?UTF-8\?B\?(.*)\?=$/', $konu, $x)) { $konu = base64_decode($x[1]); }
    preg_match_all("#Content-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n--#s", $ileti, $p);
    return [$konu, base64_decode(str_replace("\r\n", '', $p[1][0] ?? '')), base64_decode(str_replace("\r\n", '', $p[1][1] ?? ''))];
};
$bugun = date('Y-m-d');
$gun = fn(int $n) => date('Y-m-d', strtotime("$bugun $n days"));
merkez_q("INSERT INTO magazalar (id, isim, email, admin_kullanici, plan, deneme_bitis, durum) VALUES
  (1, 'Poyraz Optik', 'poyraz@ornek.com', 'patron', 'deneme', ?, 'aktif'),
  (2, 'Deniz Optik', 'deniz@ornek.com', 'deniz', 'deneme', ?, 'aktif'),
  (3, 'Bitti Optik', 'bitti@ornek.com', 'b', 'deneme', ?, 'aktif'),
  (4, 'Ücretli Optik', 'ucretli@ornek.com', 'u', 'ucretli', NULL, 'aktif'),
  (5, 'Bekleyen Optik', 'bekle@ornek.com', 'k', 'deneme', ?, 'beklemede')", [$gun(7), $gun(1), $gun(-1), $gun(14)]);

/* --- 1. Ayar ------------------------------------------------------------------------------------------------------ */
$a = eposta_ayar();
dogru('varsayılan: kapalı, gmail 587 tls', !$a['acik'] && $a['host'] === 'smtp.gmail.com' && $a['port'] === 587 && $a['guvenlik'] === 'tls');
dogru('ayar yokken hazır değil', !eposta_hazir());
dogru('kapalıyken şablon gönderimi yapılmaz', eposta_sablonla('kayit_alindi', merkez_row('SELECT * FROM magazalar WHERE id = 1')) === 0 && !$giden);
$hata = '';
try { eposta_ayar_kaydet(['acik' => 1, 'host' => 'smtp gmail', 'site_url' => 'https://optiflow.com.tr']); } catch (DomainException $e) { $hata = $e->getMessage(); }
dogru('geçersiz sunucu reddedilir', str_contains($hata, 'SMTP sunucusu'));
$hata = '';
try { eposta_ayar_kaydet(['acik' => 1, 'gonderen_adres' => 'info@', 'site_url' => 'https://optiflow.com.tr']); } catch (DomainException $e) { $hata = $e->getMessage(); }
dogru('geçersiz adres reddedilir', str_contains($hata, 'geçersiz'));
$hata = '';
try { eposta_ayar_kaydet(['acik' => 1, 'gonderen_adres' => '', 'site_url' => 'https://optiflow.com.tr']); } catch (DomainException $e) { $hata = $e->getMessage(); }
dogru('açmak için gönderen adres gerekir', str_contains($hata, 'gönderen'));
eposta_ayar_kaydet(['acik' => 1, 'host' => 'smtp.gmail.com', 'port' => 587, 'guvenlik' => 'tls', 'kullanici' => 'info@optiflow.com.tr', 'sifre' => 'abcd efgh ijkl mnop',
    'gonderen_adres' => 'info@optiflow.com.tr', 'gonderen_ad' => 'OptiFlow', 'bildirim_adres' => 'patron@optiflow.com.tr', 'site_url' => 'https://optiflow.com.tr/',
    'otomatik' => array_fill_keys(array_keys(eposta_sablon_tanimlari()), '1')]);
$a = eposta_ayar();
$dosya = (string) file_get_contents($kok . '/storage/eposta/ayar.json');
dogru('ayar kaydedildi ve hazır', eposta_hazir() && $a['site_url'] === 'https://optiflow.com.tr');
dogru('şifre dosyada açık metin değil', !str_contains($dosya, 'abcd') && !str_contains($dosya, 'abcdefghijklmnop') && $a['sifre_sifreli'] !== '');
dogru('şifre boşlukları atılıp çözülür', eposta_sifre($a) === 'abcdefghijklmnop');
eposta_ayar_kaydet(['acik' => 1, 'host' => 'smtp.gmail.com', 'gonderen_adres' => 'info@optiflow.com.tr', 'site_url' => 'https://optiflow.com.tr', 'sifre' => '']);
dogru('boş şifre eskisini korur', eposta_sifre(eposta_ayar()) === 'abcdefghijklmnop');
dogru('otomatik dizisi gelmezse anahtarlar korunur', !empty(eposta_ayar()['otomatik']['deneme_7']));

/* --- 2. Şablon ve ileti ---------------------------------------------------------------------------------------------- */
$m1 = merkez_row('SELECT * FROM magazalar WHERE id = 1');
$d = eposta_degiskenler($m1);
dogru('değişkenler', $d['magaza'] === 'Poyraz Optik' && $d['kullanici'] === 'patron' && $d['kalan_gun'] === '7'
    && $d['deneme_bitis'] === date('d.m.Y', strtotime($gun(7))) && $d['giris_adresi'] === 'https://optiflow.com.tr/magaza-giris.php' && $d['telefon'] === '0546 743 82 99');
dogru('doldur: bilinmeyen değişken olduğu gibi kalır', eposta_doldur('{magaza} {yok}', $d) === 'Poyraz Optik {yok}');
foreach (eposta_sablonlar() as $k => $s) {
    $dolu = eposta_doldur($s['konu'] . "\n" . $s['metin'], $d);
    dogru("şablon $k dolu ve değişkenleri tanınıyor", $s['konu'] !== '' && $s['metin'] !== '' && !preg_match('/\{[a-z_]+\}/', $dolu), $dolu);
}
eposta_sablon_kaydet('kayit_alindi', 'Hoş geldin {magaza}', "Merhaba {magaza}\n\nÖzel metin");
$s = eposta_sablonlar()['kayit_alindi'];
dogru('özel şablon kaydedilir', $s['ozel'] && $s['konu'] === 'Hoş geldin {magaza}');
eposta_sablon_kaydet('kayit_alindi', '', '', true);
dogru('varsayılana dön', !eposta_sablonlar()['kayit_alindi']['ozel']);
$hata = '';
try { eposta_sablon_kaydet('yok_boyle', 'a', 'b'); } catch (DomainException $e) { $hata = 'ok'; }
dogru('bilinmeyen şablon reddedilir', $hata === 'ok');

$ileti = eposta_ileti(eposta_ayar(), 'x@y.com', "Çok önemli\r\nBcc: kotu@x.com", "Merhaba\n\nhttps://optiflow.com.tr", eposta_html("<b>x</b> https://optiflow.com.tr", 'https://optiflow.com.tr/eposta-ret.php?m=1&t=a'), ["X-Ek: 1\r\nBcc: kotu@x.com"]);
[$konu, $duz, $html] = $coz($ileti);
dogru('konu UTF-8 kodlanır', str_contains($ileti, 'Subject: =?UTF-8?B?') && str_starts_with($konu, 'Çok önemli'));
dogru('başlık enjeksiyonu yok', !preg_match('/^Bcc:/mi', $ileti));
dogru('From adı ve adresi', str_contains($ileti, 'From: OptiFlow <info@optiflow.com.tr>'));
dogru('düz metin + html parçaları', $duz === "Merhaba\n\nhttps://optiflow.com.tr" && str_contains($html, '&lt;b&gt;x&lt;/b&gt;') && str_contains($html, '<a href="https://optiflow.com.tr"'));
dogru('html çıkma bağlantısı', str_contains($html, 'eposta-ret.php?m=1&amp;t=a'));

/* --- 3. Kayıt olayı ------------------------------------------------------------------------------------------------------ */
$m5 = merkez_row('SELECT * FROM magazalar WHERE id = 5');
eposta_olay_kayit($m5);
dogru('beklemede kayıt: mağazaya "başvuru alındı" + size bildirim', count($giden) === 2 && $giden[0]['kime'] === 'bekle@ornek.com' && $giden[1]['kime'] === 'patron@optiflow.com.tr');
dogru('bildirim içeriğinde mağaza adı', str_contains($coz($giden[1]['ileti'])[1], 'Bekleyen Optik'));
eposta_olay_kayit($m5);
dogru('aynı kayıt için tekrar gönderilmez', count($giden) === 2);
merkez_q("UPDATE magazalar SET durum = 'aktif' WHERE id = 5");
eposta_olay_etkinlestirildi(5);
dogru('etkinleştirilince "hesap hazır"', count($giden) === 3 && str_contains($coz($giden[2]['ileti'])[1], 'magaza-giris.php'));
eposta_olay_etkinlestirildi(5);
dogru('"hesap hazır" bir kez', count($giden) === 3);
merkez_q("UPDATE magazalar SET durum = 'beklemede' WHERE id = 5");
$ay = eposta_ayar();
eposta_ayar_kaydet(['acik' => 1, 'host' => 'smtp.gmail.com', 'gonderen_adres' => 'info@optiflow.com.tr', 'site_url' => 'https://optiflow.com.tr', 'otomatik' => ['kayit_alindi' => '1', '_' => '0']]);
eposta_olay_kayit(['id' => 99, 'isim' => 'Yeni', 'email' => 'yeni@ornek.com', 'durum' => 'beklemede']);
dogru('kapatılan otomatik e-posta gitmez (yönetici bildirimi kapalı)', count($giden) === 4 && $giden[3]['kime'] === 'yeni@ornek.com');
eposta_ayar_kaydet(['acik' => 1, 'host' => 'smtp.gmail.com', 'gonderen_adres' => 'info@optiflow.com.tr', 'site_url' => 'https://optiflow.com.tr', 'otomatik' => array_fill_keys(array_keys(eposta_sablon_tanimlari()), '1')]);
eposta_olay_kayit(null);
dogru('boş kayıt hata vermez', true);

/* --- 4. Cron: deneme hatırlatmaları ------------------------------------------------------------------------------------- */
$giden = [];
$c = eposta_cron($bugun);
$kimler = array_column($giden, 'kime');
sort($kimler);
dogru('cron: 7 gün / 1 gün / bitti hatırlatmaları', $c['hatirlatma'] === 3 && $kimler === ['bitti@ornek.com', 'deniz@ornek.com', 'poyraz@ornek.com'], json_encode($c) . json_encode($kimler));
dogru('ücretli ve beklemedekine hatırlatma yok', !in_array('ucretli@ornek.com', $kimler, true) && !in_array('bekle@ornek.com', $kimler, true));
$c2 = eposta_cron($bugun);
dogru('cron aynı gün tekrar göndermez', $c2['hatirlatma'] === 0 && count($giden) === 3);
$deniz = '';
foreach ($giden as $g) { if ($g['kime'] === 'deniz@ornek.com') { $deniz = $coz($g['ileti'])[0] . ' ' . $coz($g['ileti'])[1]; } }
dogru('1 gün kala metni kalan günü içerir', str_contains($deniz, 'Deniz Optik') && (str_contains($deniz, 'yarın') || str_contains($deniz, '1 gün')), $deniz);

/* --- 5. Hata ve yeniden deneme ------------------------------------------------------------------------------------------- */
$GLOBALS['__tasima_hatasi'] = true;
$id = eposta_hemen(['kime' => 'hata@ornek.com', 'konu' => 'K', 'metin' => 'M', 'tur' => 'islem']);
$r = merkez_row('SELECT * FROM eposta_kuyruk WHERE id = ?', [$id]);
dogru('başarısız gönderim kuyrukta bekler, hata yazılır', $r['durum'] === 'bekliyor' && (int) $r['deneme'] === 1 && str_contains((string) $r['hata'], 'sahte hata'));
eposta_kuyruk_isle();
eposta_kuyruk_isle();
dogru('3 denemeden sonra "hata"', merkez_scalar('SELECT durum FROM eposta_kuyruk WHERE id = ?', [$id]) === 'hata');
eposta_kuyruk_isle();
dogru('hatalı satır bir daha denenmez', (int) merkez_scalar('SELECT deneme FROM eposta_kuyruk WHERE id = ?', [$id]) === 3);
$GLOBALS['__tasima_hatasi'] = false;
eposta_yeniden_dene($id);
$k = eposta_kuyruk_isle();
dogru('yeniden dene → gönderildi', $k['gonderilen'] === 1 && merkez_scalar('SELECT durum FROM eposta_kuyruk WHERE id = ?', [$id]) === 'gonderildi');
$id2 = eposta_kuyruga(['kime' => 'iptal@ornek.com', 'konu' => 'K', 'metin' => 'M']);
eposta_iptal($id2);
eposta_kuyruk_isle();
dogru('iptal edilen gitmez', merkez_scalar('SELECT durum FROM eposta_kuyruk WHERE id = ?', [$id2]) === 'iptal');
$hata = '';
try { eposta_kuyruga(['kime' => "a@b.com\r\nBcc: x@y.z", 'konu' => 'K', 'metin' => 'M']); } catch (DomainException $e) { $hata = 'ok'; }
dogru('alıcıda satır sonu reddedilir', $hata === 'ok');

/* --- 6. Panel gönderimi + çıkma ------------------------------------------------------------------------------------------ */
merkez_q('DELETE FROM eposta_kuyruk');
$giden = [];
merkez_q('UPDATE magazalar SET eposta_ret = 1 WHERE id = 2');
$n = eposta_panel_gonder(['hedef' => 'aktif', 'tur' => 'duyuru', 'konu' => 'Yenilik: {magaza}', 'metin' => "Merhaba {magaza},\n\nYeni özellik."]);
dogru('duyuru: çıkmış mağaza atlanır', $n === 3, (string) $n);   // 1, 3, 4 (5 beklemede, 2 çıkmış)
eposta_kuyruk_isle();
$ilk = $giden[0]['ileti'] ?? '';
dogru('duyuruda List-Unsubscribe + tek tık başlığı', str_contains($ilk, 'List-Unsubscribe: <https://optiflow.com.tr/eposta-ret.php?m=1&t=' . eposta_ret_imza(1) . '>') && str_contains($ilk, 'List-Unsubscribe-Post: List-Unsubscribe=One-Click'));
dogru('duyuru konusu mağazaya göre doldu', $coz($ilk)[0] === 'Yenilik: Poyraz Optik');
$n = eposta_panel_gonder(['hedef' => 'aktif', 'tur' => 'islem', 'konu' => 'Bakım', 'metin' => 'Gece bakım var.']);
dogru('işlem e-postası çıkmış mağazaya da gider', $n === 4);
eposta_kuyruk_isle();
dogru('işlem e-postasında çıkma başlığı yok', !str_contains(end($giden)['ileti'], 'List-Unsubscribe'));
// kuyruğa girdikten sonra çıkarsa
eposta_panel_gonder(['hedef' => 'tek', 'magaza_id' => 1, 'tur' => 'duyuru', 'konu' => 'K', 'metin' => 'M']);
merkez_q('UPDATE magazalar SET eposta_ret = 1 WHERE id = 1');
$once = count($giden);
eposta_kuyruk_isle();
dogru('kuyruktayken çıkan mağazaya duyuru gitmez', count($giden) === $once && merkez_scalar("SELECT COUNT(*) FROM eposta_kuyruk WHERE durum = 'iptal'") == 1);
merkez_q('UPDATE magazalar SET eposta_ret = 0 WHERE id = 1');
$n = eposta_panel_gonder(['hedef' => 'adres', 'adres' => "a@x.com, b@x.com\nc@x.com", 'konu' => 'K', 'metin' => 'M']);
dogru('elle adresler', $n === 3);
$hata = '';
try { eposta_panel_gonder(['hedef' => 'adres', 'adres' => implode(',', array_map(fn($i) => "k$i@x.com", range(1, 51))), 'konu' => 'K', 'metin' => 'M']); } catch (DomainException $e) { $hata = 'ok'; }
dogru('elle en fazla 50 adres', $hata === 'ok');
$hata = '';
try { eposta_panel_gonder(['hedef' => 'adres', 'adres' => 'bozuk', 'konu' => 'K', 'metin' => 'M']); } catch (DomainException $e) { $hata = 'ok'; }
dogru('bozuk adres reddedilir', $hata === 'ok');
$hata = '';
try { eposta_panel_gonder(['hedef' => 'boyle_yok', 'konu' => 'K', 'metin' => 'M']); } catch (DomainException $e) { $hata = 'ok'; }
dogru('geçersiz grup reddedilir', $hata === 'ok');
dogru('gruplar', count(eposta_hedef_magazalar('deneme')) === 3 && count(eposta_hedef_magazalar('ucretli')) === 1 && count(eposta_hedef_magazalar('beklemede')) === 1 && count(eposta_hedef_magazalar('hepsi')) === 5);
$oz = eposta_ozet();
dogru('özet', $oz['gonderildi'] > 0 && $oz['iptal'] === 1);

dogru('çıkma: yanlış imza reddedilir', eposta_ret_uygula(3, 'yanlis') === null && (int) merkez_scalar('SELECT eposta_ret FROM magazalar WHERE id = 3') === 0);
dogru('çıkma: başka mağazanın imzası çalışmaz', eposta_ret_uygula(3, eposta_ret_imza(4)) === null);
dogru('çıkma: doğru imza', eposta_ret_uygula(3, eposta_ret_imza(3)) === 'Bitti Optik' && (int) merkez_scalar('SELECT eposta_ret FROM magazalar WHERE id = 3') === 1);
dogru('çıkma: geri al', eposta_ret_uygula(3, eposta_ret_imza(3), true) === 'Bitti Optik' && (int) merkez_scalar('SELECT eposta_ret FROM magazalar WHERE id = 3') === 0);

/* --- 7. Gerçek SMTP istemcisi (sahte sunucuya, şifrelemesiz) --------------------------------------------------------------- */
$smtpDene = function (string $mod) use ($kok): array {
    $pf = "$kok/port-$mod"; $kf = "$kok/kayit-$mod";
    @unlink($pf); @unlink($kf);
    $p = proc_open([PHP_BINARY, __DIR__ . '/sahte-smtp.php', $pf, $kf, $mod], [], $pipes);
    for ($i = 0; $i < 100 && !is_file($pf); $i++) { usleep(50000); }
    $a = eposta_ayar();
    $a['host'] = '127.0.0.1'; $a['port'] = (int) file_get_contents($pf); $a['guvenlik'] = 'yok';
    $a['kullanici'] = 'info@optiflow.com.tr'; $a['sifre_sifreli'] = gizli_sifrele('gizli-sifre');
    $hata = '';
    try {
        eposta_smtp($a, 'alici@ornek.com', eposta_ileti($a, 'alici@ornek.com', 'Deneme', ".nokta ile başlayan satır\n\nMerhaba", '<p>x</p>'));
    } catch (RuntimeException $e) { $hata = $e->getMessage(); }
    proc_close($p);
    return [$hata, (string) @file_get_contents($kf)];
};
[$hata, $kayit] = $smtpDene('kabul');
dogru('SMTP: hatasız gönderim', $hata === '', $hata);
dogru('SMTP: EHLO alan adı', str_contains($kayit, 'EHLO optiflow.com.tr'));
dogru('SMTP: AUTH LOGIN kullanıcı + şifre base64', str_contains($kayit, base64_encode('info@optiflow.com.tr')) && str_contains($kayit, base64_encode('gizli-sifre')));
dogru('SMTP: zarf adresleri', str_contains($kayit, 'MAIL FROM:<info@optiflow.com.tr>') && str_contains($kayit, 'RCPT TO:<alici@ornek.com>'));
dogru('SMTP: ileti sonlandırıldı ve QUIT', str_contains($kayit, "\r\n.\r\n") && str_contains($kayit, 'QUIT'));
[$hata, $kayit] = $smtpDene('reddet');
dogru('SMTP: şifre reddi anlaşılır hata, şifre mesajda yok', str_contains($hata, 'uygulama şifresi') && str_contains($hata, '535') && !str_contains($hata, 'gizli-sifre') && !str_contains($hata, base64_encode('gizli-sifre')), $hata);
$hata = '';
$a = eposta_ayar(); $a['host'] = '127.0.0.1'; $a['port'] = 1; $a['guvenlik'] = 'yok';
try { eposta_smtp($a, 'x@y.com', 'x'); } catch (RuntimeException $e) { $hata = $e->getMessage(); }
dogru('SMTP: bağlanamama anlaşılır hata', str_contains($hata, 'bağlanılamadı'), $hata);

/* --- 8. Sayfa / bağlantı dosyaları --------------------------------------------------------------------------------------- */
$kokDepo = dirname(__DIR__, 2);
$boot = (string) file_get_contents($kokDepo . '/app/bootstrap.php');
dogru('bootstrap eposta.php yükler', str_contains($boot, "require __DIR__ . '/eposta.php'"));
dogru('eposta-ret.php oturumsuz uç nokta', substr_count($boot, "'eposta-ret.php'") >= 2);
dogru('eposta-ret.php kökte', is_file($kokDepo . '/eposta-ret.php') && is_file($kokDepo . '/app/pages/eposta-ret.php'));
$panel = (string) file_get_contents($kokDepo . '/app/pages/merkez-panel.php');
dogru('merkez panelde e-posta görünümü ve eylemler', str_contains($panel, "\$gorunum === 'eposta'") && str_contains($panel, "case 'eposta_gonder'") && str_contains($panel, "case 'eposta_ayar'"));
dogru('panelde satır içi script yok', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>/i', $panel) && !preg_match('/\son(click|change|submit)=/i', $panel));
dogru('cron e-postayı işler', str_contains((string) file_get_contents($kokDepo . '/app/pages/cron.php'), 'eposta_cron('));
dogru('kayıt olayı bağlı', substr_count((string) file_get_contents($kokDepo . '/app/merkez.php'), 'eposta_olay_kayit(') >= 2);


/* --- 9. Tasarım + yeni olaylar (4.52.0) --------------------------------------------------------------------------------- */
merkez_q('DELETE FROM eposta_kuyruk');
$giden = [];
merkez_q("ALTER TABLE magazalar ADD COLUMN telefon TEXT NULL");
foreach (array_keys(eposta_sablon_tanimlari()) as $k) {
    $h = eposta_onizleme($k);
    dogru("önizleme $k: başlık + tema rengi, doldurulmamış değişken yok", str_contains($h, '<h1') && str_contains($h, eposta_temalar()[eposta_sablon_gorunum($k)['tema']][0]) && !preg_match('/\{[a-z_]+\}/', $h));
}
$h = eposta_onizleme('hesap_hazir');
dogru('hesap hazır: giriş düğmesi + adımlar', str_contains($h, 'Mağazama giriş yap') && str_contains($h, 'href="https://optiflow.com.tr/magaza-giris.php"') && str_contains($h, 'Kullanıcı girişi'));
dogru('deneme 7: büyük gün vurgusu', str_contains(eposta_onizleme('deneme_7'), '>7 gün<'));
dogru('işlem e-postasında çıkma bağlantısı yok', !str_contains($h, 'almak istemiyorum'));
dogru('html kaçışlı (mağaza adı)', str_contains(eposta_onizleme('kayit_alindi', ['id' => 9, 'isim' => '<b>X</b>', 'email' => 'x@y.z']), '&lt;b&gt;X&lt;/b&gt;'));
dogru('yönetici bildiriminde imza yok', !str_contains(eposta_onizleme('yonetici_yeni_kayit'), 'OptiFlow ekibi'));
$d = eposta_duz('Metin', ['bilgiler' => [['Mağaza', 'A']], 'buton' => ['Giriş', 'https://x.y/z']]);
dogru('düz metin: bilgiler + düğme bağlantısı + imza', str_contains($d, 'Mağaza: A') && str_contains($d, 'Giriş: https://x.y/z') && str_contains($d, 'OptiFlow ekibi'));
$g = eposta_gorunum_doldur(['bilgiler' => [['Telefon', '{magaza_telefon}'], ['Mağaza', '{magaza}']]], eposta_degiskenler(['isim' => 'Q']));
dogru('boş bilgi satırı atılır', count($g['bilgiler']) === 1 && $g['bilgiler'][0][1] === 'Q');

$once = merkez_row('SELECT * FROM magazalar WHERE id = 4');
merkez_q("UPDATE magazalar SET durum = 'dondu' WHERE id = 4");
eposta_olay_magaza_degisti($once, 4);
$son = end($giden);
dogru('dondurulunca e-posta', count($giden) === 1 && $son['kime'] === 'ucretli@ornek.com' && str_contains($coz($son['ileti'])[0], 'donduruldu'));
$once = merkez_row('SELECT * FROM magazalar WHERE id = 4');
merkez_q("UPDATE magazalar SET durum = 'aktif' WHERE id = 4");
eposta_olay_magaza_degisti($once, 4);
dogru('yeniden açılınca e-posta', count($giden) === 2 && str_contains($coz(end($giden)['ileti'])[0], 'yeniden açıldı'));
$once = merkez_row('SELECT * FROM magazalar WHERE id = 1');
merkez_q("UPDATE magazalar SET plan = 'ucretli' WHERE id = 1");
eposta_olay_magaza_degisti($once, 1);
dogru('ücretliye geçince "abonelik başladı"', count($giden) === 3 && str_contains($coz(end($giden)['ileti'])[0], 'Aboneliğiniz'));
eposta_olay_magaza_degisti($once, 1);
dogru('aynı gün tekrar gitmez', count($giden) === 3);
$once = merkez_row('SELECT * FROM magazalar WHERE id = 2');
merkez_q('UPDATE magazalar SET deneme_bitis = ? WHERE id = 2', [$gun(31)]);
eposta_olay_magaza_degisti($once, 2);
dogru('deneme uzatılınca yeni tarih', count($giden) === 4 && str_contains($coz(end($giden)['ileti'])[1], date('d.m.Y', strtotime($gun(31)))));
eposta_olay_magaza_degisti(merkez_row('SELECT * FROM magazalar WHERE id = 2'), 2);
dogru('değişiklik yoksa e-posta yok', count($giden) === 4);
eposta_olay_sifre_degisti(3);
$p = $coz(end($giden)['ileti']);
dogru('şifre değişti bildirimi (şifre yok)', count($giden) === 5 && str_contains($p[0], 'şifre') && end($giden)['kime'] === 'bitti@ornek.com');
eposta_otomatik_ayarla('sifre_degisti', false);
eposta_olay_sifre_degisti(3);
dogru('kapatılan otomatik gitmez', count($giden) === 5 && empty(eposta_ayar()['otomatik']['sifre_degisti']) && !empty(eposta_ayar()['otomatik']['hesap_hazir']));
$GLOBALS['eposta_ertele'] = true;
eposta_olay_sifre_degisti(3);
eposta_otomatik_ayarla('sifre_degisti', true);
eposta_olay_sifre_degisti(3);
dogru('ertele: kuyruğa girer, hemen gitmez', count($giden) === 5 && (int) merkez_scalar("SELECT COUNT(*) FROM eposta_kuyruk WHERE durum = 'bekliyor'") === 1);
unset($GLOBALS['eposta_ertele']);
eposta_kuyruk_isle();
dogru('ertelenen cron ile gider', count($giden) === 6);

$n = eposta_panel_gonder(['hedef' => 'tek', 'magaza_id' => 1, 'tur' => 'duyuru', 'tema' => 'yesil', 'konu' => 'K', 'baslik' => 'Büyük başlık {magaza}', 'metin' => 'M', 'buton_yazi' => 'Bak', 'buton_url' => 'https://optiflow.com.tr/x']);
eposta_kuyruk_isle();
$html = $coz(end($giden)['ileti'])[2];
dogru('panel: tema + başlık + düğme', $n === 1 && str_contains($html, '#1f8a57') && str_contains($html, 'Büyük başlık Poyraz Optik') && str_contains($html, '>Bak &rarr;<') && str_contains($html, 'almak istemiyorum'));
$hata = '';
try { eposta_panel_gonder(['hedef' => 'tek', 'magaza_id' => 1, 'konu' => 'K', 'metin' => 'M', 'buton_url' => 'javascript:alert(1)']); } catch (DomainException $e) { $hata = 'ok'; }
dogru('panel: zararlı düğme bağlantısı reddedilir', $hata === 'ok');
$kid = (int) merkez_scalar('SELECT MAX(id) FROM eposta_kuyruk');
dogru('gönderim kaydı HTML', str_contains((string) eposta_kayit_html($kid), 'Büyük başlık') && eposta_kayit_html(999999) === null);
$panel = (string) file_get_contents($kokDepo . '/app/pages/merkez-panel.php');
dogru('panel olayları bağlı', substr_count($panel, 'eposta_olay_magaza_degisti(') >= 4 && str_contains($panel, 'eposta_olay_sifre_degisti(') && str_contains($panel, "case 'eposta_otomatik'"));

exec('rm -rf ' . escapeshellarg($kok));
echo "eposta: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
