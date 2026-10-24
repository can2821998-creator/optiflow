<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Merkez e-postası (4.51.0)
   info@optiflow.com.tr gibi bir adresten SMTP ile e-posta gönderir (Google Workspace / Gmail: smtp.gmail.com:587,
   STARTTLS, Google "uygulama şifresi"). Dış kütüphane yok; küçük bir SMTP istemcisi bu dosyadadır.
     • Ayarlar: storage/eposta/ayar.json (SMTP şifresi gizli_sifrele ile şifreli; anahtar storage/.gizli-anahtar)
     • Şablonlar: varsayılanlar bu dosyada, panelden değiştirilenler storage/eposta/sablonlar.json
     • Kuyruk: merkez veritabanında eposta_kuyruk. Her gönderim önce kuyruğa girer; işlem e-postaları hemen
       denenir, toplu duyurular cron.php ile parça parça gider (Gmail günlük sınırı). Başarısız olan 3 kez denenir.
     • Duyurularda "e-posta almak istemiyorum" bağlantısı (eposta-ret.php, HMAC'li) ve List-Unsubscribe başlığı
       bulunur; işlem e-postaları (kayıt, hesap hazır, deneme bitişi) her zaman gider.
   SQL taşınabilir (MySQL + testlerdeki SQLite): NOW()/INTERVAL yok, zaman PHP'de.
   ========================================================================== */

const EPOSTA_DENEME_AZAMI = 3;
const EPOSTA_CRON_PARTI = 40;      // cron başına en çok gönderim (10 dk'da bir → saatte 240)

function eposta_kok(): string
{
    $t = getenv('UTS_TEST_ROOT');
    return $t !== false && $t !== '' ? $t : dirname(__DIR__);
}

function eposta_dizin(): string
{
    $d = eposta_kok() . '/storage/eposta';
    if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) {
        throw new RuntimeException('storage/eposta klasörü oluşturulamadı (yazma izni).');
    }
    return $d;
}

/* ---------------- Ayarlar ---------------- */

function eposta_ayar(): array
{
    $f = eposta_kok() . '/storage/eposta/ayar.json';
    $p = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
    $p = is_array($p) ? $p : [];
    return [
        'acik'           => !empty($p['acik']),
        'host'           => (string) ($p['host'] ?? 'smtp.gmail.com'),
        'port'           => (int) ($p['port'] ?? 587),
        'guvenlik'       => in_array($p['guvenlik'] ?? 'tls', ['tls', 'ssl', 'yok'], true) ? (string) ($p['guvenlik'] ?? 'tls') : 'tls',
        'kullanici'      => (string) ($p['kullanici'] ?? ''),
        'sifre_sifreli'  => (string) ($p['sifre_sifreli'] ?? ''),
        'gonderen_adres' => (string) ($p['gonderen_adres'] ?? ''),
        'gonderen_ad'    => (string) ($p['gonderen_ad'] ?? 'OptiFlow'),
        'yanit_adres'    => (string) ($p['yanit_adres'] ?? ''),
        'bildirim_adres' => (string) ($p['bildirim_adres'] ?? ''),
        'site_url'       => rtrim((string) ($p['site_url'] ?? 'https://optiflow.com.tr'), '/'),
        'otomatik'       => array_merge(array_fill_keys(array_keys(eposta_sablon_tanimlari()), true), (array) ($p['otomatik'] ?? [])),
    ];
}

function eposta_sifre(array $a): string
{
    if ($a['sifre_sifreli'] === '' || !function_exists('gizli_coz')) {
        return '';
    }
    return gizli_coz($a['sifre_sifreli']);
}

/** Panelden gelen ayarı doğrular ve yazar. Şifre boş gelirse eskisi korunur. */
function eposta_ayar_kaydet(array $v): array
{
    $a = eposta_ayar();
    $tek = static fn($s): string => trim(str_replace(["\r", "\n"], '', (string) $s));
    $host = $tek($v['host'] ?? $a['host']);
    if ($host !== '' && !preg_match('/^[a-z0-9.\-]+$/i', $host)) {
        throw new DomainException('SMTP sunucusu geçersiz (ör. smtp.gmail.com).');
    }
    $port = (int) ($v['port'] ?? $a['port']);
    if ($port < 1 || $port > 65535) {
        throw new DomainException('Port geçersiz (Gmail için 587).');
    }
    foreach (['gonderen_adres', 'yanit_adres', 'bildirim_adres'] as $k) {
        $x = $tek($v[$k] ?? $a[$k]);
        if ($x !== '' && !filter_var($x, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('E-posta adresi geçersiz: ' . $x);
        }
        $a[$k] = $x;
    }
    $site = rtrim($tek($v['site_url'] ?? $a['site_url']), '/');
    if (!preg_match('#^https?://[a-z0-9.\-]+(:\d+)?(/[^\s]*)?$#i', $site)) {
        throw new DomainException('Site adresi geçersiz (ör. https://optiflow.com.tr).');
    }
    $a['acik'] = !empty($v['acik']);
    $a['host'] = $host;
    $a['port'] = $port;
    $a['guvenlik'] = in_array($v['guvenlik'] ?? $a['guvenlik'], ['tls', 'ssl', 'yok'], true) ? (string) ($v['guvenlik'] ?? $a['guvenlik']) : 'tls';
    $a['kullanici'] = $tek($v['kullanici'] ?? $a['kullanici']);
    $a['gonderen_ad'] = mb_substr($tek($v['gonderen_ad'] ?? $a['gonderen_ad']), 0, 80) ?: 'OptiFlow';
    $a['site_url'] = $site;
    if (isset($v['otomatik']) && is_array($v['otomatik'])) {
        foreach (array_keys(eposta_sablon_tanimlari()) as $k) {
            $a['otomatik'][$k] = !empty($v['otomatik'][$k]);
        }
    }
    $sifre = (string) ($v['sifre'] ?? '');
    if (!empty($v['sifre_sil'])) {
        $a['sifre_sifreli'] = '';
    } elseif ($sifre !== '') {
        $a['sifre_sifreli'] = gizli_sifrele(str_replace(' ', '', $sifre));   // Google uygulama şifresi boşluklu gösterilir
    }
    if ($a['acik'] && ($a['host'] === '' || $a['gonderen_adres'] === '')) {
        throw new DomainException('E-postayı açmak için SMTP sunucusu ve gönderen adresi gerekli.');
    }
    $yaz = $a;
    file_put_contents(eposta_dizin() . '/ayar.json', json_encode($yaz, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod(eposta_dizin() . '/ayar.json', 0640);
    return $a;
}

function eposta_hazir(?array $a = null): bool
{
    $a ??= eposta_ayar();
    return $a['acik'] && $a['host'] !== '' && $a['gonderen_adres'] !== '';
}

/* ---------------- Şablonlar ---------------- */

/** Otomatik e-postalar: anahtar => [ad, ne zaman, alıcı]. */
function eposta_sablon_tanimlari(): array
{
    return [
        'kayit_alindi'        => ['Başvuru alındı', 'Kayıt formu gönderildi, mağaza beklemede', 'Mağaza'],
        'hesap_hazir'         => ['Hesap hazır', 'Mağaza aktif oldu (otomatik ya da merkez panelden etkinleştirildi)', 'Mağaza'],
        'yonetici_yeni_kayit' => ['Yeni kayıt bildirimi', 'Her yeni kayıtta', 'Bildirim adresi (siz)'],
        'deneme_7'            => ['Deneme bitiyor (7 gün)', 'Deneme bitişinden 7 gün önce', 'Mağaza'],
        'deneme_1'            => ['Deneme bitiyor (yarın)', 'Deneme bitişinden 1 gün önce', 'Mağaza'],
        'deneme_bitti'        => ['Deneme sona erdi', 'Deneme bitişinin ertesi günü', 'Mağaza'],
    ];
}

function eposta_varsayilan_sablonlar(): array
{
    $imza = "Kolaylıklar dileriz,\nOptiFlow\n{site} · {telefon}";
    return [
        'kayit_alindi' => [
            'konu' => 'OptiFlow başvurunuz alındı',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow başvurunuzu aldık. Hesabınızı hazırlıyoruz; hazır olduğunda size ayrıca e-posta göndereceğiz.\n\nBaşvurudaki e-posta adresi: {email}\nYönetici kullanıcı adı: {kullanici}\n\nSorunuz olursa bu e-postayı yanıtlamanız yeterli.\n\n$imza",
        ],
        'hesap_hazir' => [
            'konu' => 'OptiFlow hesabınız hazır',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow hesabınız hazır. Aşağıdaki adresten giriş yapabilirsiniz:\n{giris_adresi}\n\n1. Mağaza girişi: {email} ve kayıtta belirlediğiniz mağaza şifresi\n2. Kullanıcı girişi: {kullanici} ve yönetici şifreniz\n\nDeneme süreniz {deneme_bitis} tarihine kadar sürer; kart bilgisi istenmez.\n\nİlk adımlar ve Medula, SGK, atölye rehberleri: {site}/rehber.php\n\nKurulumda yardım isterseniz bu e-postayı yanıtlayın ya da bizi arayın.\n\n$imza",
        ],
        'yonetici_yeni_kayit' => [
            'konu' => 'Yeni kayıt: {magaza}',
            'metin' => "Yeni mağaza kaydı geldi.\n\nMağaza: {magaza}\nE-posta: {email}\nYönetici: {kullanici}\nDurum: {durum}\nDeneme bitişi: {deneme_bitis}\n\nMerkez panel: {site}/merkez-panel.php?magaza={id}",
        ],
        'deneme_7' => [
            'konu' => 'OptiFlow deneme sürenizin bitmesine 7 gün kaldı',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow deneme süreniz {deneme_bitis} tarihinde bitiyor.\n\nKullanmaya devam etmek isterseniz bu e-postayı yanıtlamanız ya da bizi aramanız yeterli; verileriniz olduğu gibi kalır.\n\nHenüz denemediğiniz bir özellik varsa kısa bir görüşmede birlikte bakabiliriz.\n\n$imza",
        ],
        'deneme_1' => [
            'konu' => 'OptiFlow deneme süreniz yarın bitiyor',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow deneme süreniz yarın ({deneme_bitis}) bitiyor.\n\nDevam etmek için bu e-postayı yanıtlayın ya da bizi arayın; hesabınız ve verileriniz kesintisiz devam eder.\n\n$imza",
        ],
        'deneme_bitti' => [
            'konu' => 'OptiFlow deneme süreniz sona erdi',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow deneme süreniz {deneme_bitis} tarihinde sona erdi. Verileriniz silinmedi.\n\nDevam etmek isterseniz bu e-postayı yanıtlamanız yeterli; hesabınızı hemen yeniden açarız.\n\n$imza",
        ],
    ];
}

function eposta_sablonlar(): array
{
    $f = eposta_kok() . '/storage/eposta/sablonlar.json';
    $ozel = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
    $ozel = is_array($ozel) ? $ozel : [];
    $out = [];
    foreach (eposta_varsayilan_sablonlar() as $k => $s) {
        $o = $ozel[$k] ?? [];
        $out[$k] = [
            'konu' => trim((string) ($o['konu'] ?? '')) !== '' ? (string) $o['konu'] : $s['konu'],
            'metin' => trim((string) ($o['metin'] ?? '')) !== '' ? (string) $o['metin'] : $s['metin'],
            'ozel' => isset($ozel[$k]),
        ];
    }
    return $out;
}

/** Şablonu kaydeder; boş konu/metin ya da $varsayilan=true → varsayılana döner. */
function eposta_sablon_kaydet(string $anahtar, string $konu, string $metin, bool $varsayilan = false): void
{
    if (!isset(eposta_sablon_tanimlari()[$anahtar])) {
        throw new DomainException('Bilinmeyen şablon.');
    }
    $f = eposta_dizin() . '/sablonlar.json';
    $ozel = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
    $ozel = is_array($ozel) ? $ozel : [];
    if ($varsayilan || (trim($konu) === '' && trim($metin) === '')) {
        unset($ozel[$anahtar]);
    } else {
        if (mb_strlen($konu) > 200 || mb_strlen($metin) > 10000) {
            throw new DomainException('Konu ya da metin çok uzun.');
        }
        $ozel[$anahtar] = ['konu' => str_replace(["\r", "\n"], ' ', $konu), 'metin' => str_replace("\r\n", "\n", $metin)];
    }
    file_put_contents($f, json_encode($ozel, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/** Bir mağaza için şablon değişkenleri. */
function eposta_degiskenler(?array $m, ?array $a = null): array
{
    $a ??= eposta_ayar();
    $tel = '';
    if (function_exists('optiflow_pazarlama')) {
        $tel = (string) (optiflow_pazarlama()['telefon'] ?? '');
    }
    $bitis = (string) ($m['deneme_bitis'] ?? '');
    $kalan = $bitis !== '' ? (int) round((strtotime($bitis) - strtotime(date('Y-m-d'))) / 86400) : 0;
    $durumlar = ['aktif' => 'Aktif', 'beklemede' => 'Beklemede (elle etkinleştirilecek)', 'dondu' => 'Donduruldu'];
    return [
        'magaza'       => (string) ($m['isim'] ?? ''),
        'email'        => (string) ($m['email'] ?? ''),
        'kullanici'    => (string) ($m['admin_kullanici'] ?? ''),
        'deneme_bitis' => $bitis !== '' ? date('d.m.Y', (int) strtotime($bitis)) : '',
        'kalan_gun'    => (string) max(0, $kalan),
        'durum'        => $durumlar[(string) ($m['durum'] ?? '')] ?? (string) ($m['durum'] ?? ''),
        'id'           => (string) ($m['id'] ?? ''),
        'giris_adresi' => $a['site_url'] . '/magaza-giris.php',
        'site'         => $a['site_url'],
        'telefon'      => $tel,
    ];
}

function eposta_doldur(string $metin, array $d): string
{
    return preg_replace_callback('/\{([a-z_]+)\}/', static fn($x) => array_key_exists($x[1], $d) ? (string) $d[$x[1]] : $x[0], $metin) ?? $metin;
}

/* ---------------- Gövde (düz metin → markalı HTML) ---------------- */

function eposta_html(string $metin, string $retUrl = '', ?array $a = null): string
{
    $a ??= eposta_ayar();
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $paragraflar = preg_split("/\n{2,}/", trim(str_replace("\r\n", "\n", $metin))) ?: [];
    $govde = '';
    foreach ($paragraflar as $p) {
        $p = $h($p);
        $p = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:#8f1a2e">$1</a>', $p) ?? $p;
        $govde .= '<p style="margin:0 0 14px;line-height:1.6">' . nl2br($p, false) . '</p>';
    }
    $alt = $h('OptiFlow · ' . preg_replace('#^https?://#', '', $a['site_url']));
    $ret = $retUrl !== '' ? '<br><a href="' . $h($retUrl) . '" style="color:#776b6e">Bu tür e-postaları almak istemiyorum</a>' : '';
    return '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;background:#f5f3f3;font-family:Arial,Helvetica,sans-serif;color:#1b1416">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f3f3"><tr><td align="center" style="padding:24px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid #e8e1e2">'
        . '<tr><td style="background:#141012;padding:18px 26px;color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:-.3px">Opti<span style="color:#ff6b81">Flow</span></td></tr>'
        . '<tr><td style="height:4px;background:#b4233c;font-size:0;line-height:0">&nbsp;</td></tr>'
        . '<tr><td style="padding:26px;font-size:15px">' . $govde . '</td></tr>'
        . '<tr><td style="padding:14px 26px 22px;font-size:12px;color:#776b6e;border-top:1px solid #e8e1e2">' . $alt . $ret . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/* ---------------- MIME iletisi ---------------- */

function eposta_basliga(string $s): string
{
    $s = trim(str_replace(["\r", "\n"], ' ', $s));
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/** RFC 5322 ileti (başlık + gövde), CRLF satır sonlarıyla. */
function eposta_ileti(array $a, string $kime, string $konu, string $metin, string $html, array $ekBaslik = []): string
{
    $gonderen = $a['gonderen_adres'];
    $alan = substr((string) strrchr($gonderen, '@'), 1) ?: 'optiflow.com.tr';
    $sinir = 'of-' . bin2hex(random_bytes(12));
    $b = [
        'Date: ' . date('r'),
        'From: ' . eposta_basliga($a['gonderen_ad']) . ' <' . $gonderen . '>',
        'To: <' . $kime . '>',
        'Subject: ' . eposta_basliga($konu),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $alan . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $sinir . '"',
    ];
    if ($a['yanit_adres'] !== '') {
        $b[] = 'Reply-To: <' . $a['yanit_adres'] . '>';
    }
    foreach ($ekBaslik as $x) {
        $b[] = str_replace(["\r", "\n"], '', $x);
    }
    $parca = static fn(string $tur, string $icerik): string => '--' . $sinir . "\r\n"
        . 'Content-Type: ' . $tur . '; charset=UTF-8' . "\r\n" . 'Content-Transfer-Encoding: base64' . "\r\n\r\n"
        . rtrim(chunk_split(base64_encode($icerik), 76, "\r\n")) . "\r\n";
    return implode("\r\n", $b) . "\r\n\r\n" . $parca('text/plain', $metin) . $parca('text/html', $html) . '--' . $sinir . "--\r\n";
}

/* ---------------- SMTP istemcisi ---------------- */

/**
 * İletiyi SMTP ile gönderir. Hata → RuntimeException (mesajda şifre yer almaz).
 * Testlerde $GLOBALS['eposta_tasiyici'] = fn(array $a, string $kimden, string $kime, string $ileti) ile değiştirilir.
 */
function eposta_tasi(array $a, string $kime, string $ileti): void
{
    if (isset($GLOBALS['eposta_tasiyici']) && is_callable($GLOBALS['eposta_tasiyici'])) {
        ($GLOBALS['eposta_tasiyici'])($a, $a['gonderen_adres'], $kime, $ileti);
        return;
    }
    eposta_smtp($a, $kime, $ileti);
}

function eposta_smtp(array $a, string $kime, string $ileti): void
{
    $host = $a['host'];
    $ssl = $a['guvenlik'] === 'ssl';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host, 'SNI_enabled' => true]]);
    $s = @stream_socket_client(($ssl ? 'ssl://' : 'tcp://') . $host . ':' . $a['port'], $no, $msj, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) {
        throw new RuntimeException("SMTP sunucusuna bağlanılamadı ($host:{$a['port']}): " . trim((string) $msj));
    }
    stream_set_timeout($s, 25);
    $oku = static function () use ($s): string {
        $t = '';
        while (($l = fgets($s, 2048)) !== false) {
            $t .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        return $t;
    };
    $komut = static function (?string $c, array $bekle, string $gizli = '') use ($s, $oku): string {
        if ($c !== null) {
            fwrite($s, $c . "\r\n");
        }
        $y = $oku();
        if (!in_array((int) substr($y, 0, 3), $bekle, true)) {
            $gor = $gizli !== '' ? $gizli : (string) $c;
            throw new RuntimeException('SMTP ' . ($gor !== '' ? '"' . $gor . '"' : 'karşılama') . ' reddedildi: ' . trim(mb_substr($y, 0, 300)));
        }
        return $y;
    };
    try {
        $komut(null, [220]);
        $ad = substr((string) strrchr($a['gonderen_adres'], '@'), 1) ?: 'localhost';
        $ehlo = $komut('EHLO ' . $ad, [250]);
        if ($a['guvenlik'] === 'tls') {
            $komut('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0))) {
                throw new RuntimeException('TLS başlatılamadı (sertifika doğrulanamadı ya da sunucu desteklemiyor).');
            }
            $ehlo = $komut('EHLO ' . $ad, [250]);
        }
        $sifre = eposta_sifre($a);
        if ($a['kullanici'] !== '') {
            $komut('AUTH LOGIN', [334]);
            $komut(base64_encode($a['kullanici']), [334], 'kullanıcı adı');
            $komut(base64_encode($sifre), [235], 'şifre (Google için "uygulama şifresi" gerekir)');
        }
        $komut('MAIL FROM:<' . $a['gonderen_adres'] . '>', [250]);
        $komut('RCPT TO:<' . $kime . '>', [250, 251]);
        $komut('DATA', [354]);
        $govde = preg_replace("/(?<!\r)\n/", "\r\n", $ileti) ?? $ileti;
        $govde = preg_replace('/^\./m', '..', $govde) ?? $govde;   // nokta doldurma
        fwrite($s, $govde . "\r\n.\r\n");
        $komut(null, [250], 'ileti');
        @fwrite($s, "QUIT\r\n");
    } finally {
        @fclose($s);
    }
}

/* ---------------- Kuyruk ---------------- */

/**
 * Kuyruğa ekler. $e: kime, konu, metin, tur (islem|duyuru|yonetici), sablon, magaza_id, anahtar (tekrarı önler).
 * Döner: kuyruk kimliği; aynı anahtarla daha önce eklenmişse 0.
 */
function eposta_kuyruga(array $e): int
{
    $kime = strtolower(trim((string) ($e['kime'] ?? '')));
    if (!filter_var($kime, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n<>]/', $kime)) {
        throw new DomainException('Alıcı e-posta adresi geçersiz: ' . $kime);
    }
    $konu = trim(str_replace(["\r", "\n"], ' ', (string) ($e['konu'] ?? '')));
    $metin = trim(str_replace("\r\n", "\n", (string) ($e['metin'] ?? '')));
    if ($konu === '' || $metin === '') {
        throw new DomainException('Konu ve metin boş olamaz.');
    }
    $anahtar = (string) ($e['anahtar'] ?? '');
    if ($anahtar !== '' && merkez_scalar('SELECT id FROM eposta_kuyruk WHERE anahtar = ?', [$anahtar])) {
        return 0;
    }
    $tur = in_array($e['tur'] ?? 'islem', ['islem', 'duyuru', 'yonetici'], true) ? (string) ($e['tur'] ?? 'islem') : 'islem';
    merkez_q(
        'INSERT INTO eposta_kuyruk (magaza_id, kime, konu, metin, tur, sablon, anahtar, durum, deneme, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [isset($e['magaza_id']) ? (int) $e['magaza_id'] : null, $kime, mb_substr($konu, 0, 200), $metin, $tur, (string) ($e['sablon'] ?? 'serbest'),
         $anahtar !== '' ? $anahtar : null, 'bekliyor', 0, date('Y-m-d H:i:s')]
    );
    return (int) merkez_scalar('SELECT MAX(id) FROM eposta_kuyruk WHERE kime = ?', [$kime]);
}

/** Duyuru e-postasından çıkma bağlantısı için imza. */
function eposta_ret_imza(int $magazaId): string
{
    return substr(hash_hmac('sha256', 'eposta-ret:' . $magazaId, gizli_anahtar()), 0, 24);
}

function eposta_ret_url(int $magazaId, ?array $a = null): string
{
    $a ??= eposta_ayar();
    return $a['site_url'] . '/eposta-ret.php?m=' . $magazaId . '&t=' . eposta_ret_imza($magazaId);
}

/** Tek bir kuyruk satırını gönderir; sonucu satıra yazar. Döner: true = gitti. */
function eposta_satir_gonder(array $r, ?array $a = null): bool
{
    $a ??= eposta_ayar();
    $simdi = date('Y-m-d H:i:s');
    if (!eposta_hazir($a)) {
        merkez_q('UPDATE eposta_kuyruk SET hata = ? WHERE id = ?', ['E-posta ayarı kapalı ya da eksik (Merkez panel › E-posta).', $r['id']]);
        return false;
    }
    $ek = [];
    $retUrl = '';
    if ($r['tur'] === 'duyuru' && !empty($r['magaza_id'])) {
        if ((int) merkez_scalar('SELECT eposta_ret FROM magazalar WHERE id = ?', [(int) $r['magaza_id']])) {
            merkez_q('UPDATE eposta_kuyruk SET durum = ?, hata = ?, guncelleme = ? WHERE id = ?', ['iptal', 'Mağaza duyuru e-postalarından çıkmış.', $simdi, $r['id']]);
            return false;
        }
        $retUrl = eposta_ret_url((int) $r['magaza_id'], $a);
        $ek[] = 'List-Unsubscribe: <' . $retUrl . '>';
        $ek[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    try {
        $ileti = eposta_ileti($a, (string) $r['kime'], (string) $r['konu'], (string) $r['metin'], eposta_html((string) $r['metin'], $retUrl, $a), $ek);
        eposta_tasi($a, (string) $r['kime'], $ileti);
        merkez_q('UPDATE eposta_kuyruk SET durum = ?, deneme = ?, hata = NULL, gonderildi_at = ?, guncelleme = ? WHERE id = ?',
            ['gonderildi', (int) $r['deneme'] + 1, $simdi, $simdi, $r['id']]);
        return true;
    } catch (Throwable $e) {
        $deneme = (int) $r['deneme'] + 1;
        merkez_q('UPDATE eposta_kuyruk SET durum = ?, deneme = ?, hata = ?, guncelleme = ? WHERE id = ?',
            [$deneme >= EPOSTA_DENEME_AZAMI ? 'hata' : 'bekliyor', $deneme, mb_substr($e->getMessage(), 0, 250), $simdi, $r['id']]);
        if (function_exists('app_log')) {
            app_log('eposta #' . $r['id'] . ': ' . $e->getMessage());
        }
        return false;
    }
}

/** Bekleyenleri gönderir. Döner: ['gonderilen' => n, 'hata' => n]. */
function eposta_kuyruk_isle(int $limit = EPOSTA_CRON_PARTI): array
{
    $a = eposta_ayar();
    $out = ['gonderilen' => 0, 'hata' => 0];
    if (!eposta_hazir($a)) {
        return $out;
    }
    $limit = max(1, min(200, $limit));
    foreach (merkez_rows('SELECT * FROM eposta_kuyruk WHERE durum = ? AND deneme < ? ORDER BY id LIMIT ' . $limit, ['bekliyor', EPOSTA_DENEME_AZAMI]) as $r) {
        eposta_satir_gonder($r, $a) ? $out['gonderilen']++ : $out['hata']++;
    }
    return $out;
}

/** Kuyruğa ekleyip hemen göndermeyi dener (işlem e-postaları). Hata fırlatmaz; döner: kuyruk kimliği ya da 0. */
function eposta_hemen(array $e): int
{
    try {
        $id = eposta_kuyruga($e);
        if ($id && eposta_hazir()) {
            $r = merkez_row('SELECT * FROM eposta_kuyruk WHERE id = ?', [$id]);
            if ($r) {
                eposta_satir_gonder($r);
            }
        }
        return $id;
    } catch (Throwable $e2) {
        if (function_exists('app_log')) {
            app_log('eposta_hemen: ' . $e2->getMessage());
        }
        return 0;
    }
}

/** Şablonla mağazaya (ya da bildirim adresine) e-posta. Otomatik gönderimi kapalıysa ya da e-posta ayarı yoksa hiçbir şey yapmaz. */
function eposta_sablonla(string $sablon, array $m, string $anahtar = ''): int
{
    $a = eposta_ayar();
    if (!eposta_hazir($a) || empty($a['otomatik'][$sablon])) {
        return 0;
    }
    $s = eposta_sablonlar()[$sablon] ?? null;
    if (!$s) {
        return 0;
    }
    $yonetici = $sablon === 'yonetici_yeni_kayit';
    $kime = $yonetici ? ($a['bildirim_adres'] ?: $a['gonderen_adres']) : (string) ($m['email'] ?? '');
    $d = eposta_degiskenler($m, $a);
    return eposta_hemen([
        'kime' => $kime, 'konu' => eposta_doldur($s['konu'], $d), 'metin' => eposta_doldur($s['metin'], $d),
        'tur' => $yonetici ? 'yonetici' : 'islem', 'sablon' => $sablon, 'magaza_id' => $m['id'] ?? null, 'anahtar' => $anahtar,
    ]);
}

/* ---------------- Olaylar ---------------- */

/** Kayıt sonrası: mağazaya "başvuru alındı" ya da "hesap hazır", size "yeni kayıt". Kayıt akışını asla bozmaz. */
function eposta_olay_kayit(?array $m): void
{
    if (!$m) {
        return;
    }
    try {
        $id = (int) $m['id'];
        eposta_sablonla(($m['durum'] ?? '') === 'aktif' ? 'hesap_hazir' : 'kayit_alindi', $m, ($m['durum'] ?? '') === 'aktif' ? 'hesap_hazir:' . $id : 'kayit_alindi:' . $id);
        eposta_sablonla('yonetici_yeni_kayit', $m, 'yonetici_yeni_kayit:' . $id);
    } catch (Throwable $e) {
        if (function_exists('app_log')) {
            app_log('eposta_olay_kayit: ' . $e->getMessage());
        }
    }
}

/** Merkez panelden etkinleştirildi → "hesap hazır" (bir kez). */
function eposta_olay_etkinlestirildi(int $magazaId): void
{
    try {
        $m = merkez_row('SELECT * FROM magazalar WHERE id = ?', [$magazaId]);
        if ($m && $m['durum'] === 'aktif') {
            eposta_sablonla('hesap_hazir', $m, 'hesap_hazir:' . $magazaId);
        }
    } catch (Throwable $e) {
        if (function_exists('app_log')) {
            app_log('eposta_olay_etkinlestirildi: ' . $e->getMessage());
        }
    }
}

/** Cron: deneme hatırlatmaları (7 gün, 1 gün, bitti) + kuyruğu işle. */
function eposta_cron(?string $bugun = null): array
{
    $out = ['hatirlatma' => 0, 'gonderilen' => 0, 'hata' => 0];
    if (!eposta_hazir()) {
        return $out;
    }
    $bugun ??= date('Y-m-d');
    $plan = [
        'deneme_7' => date('Y-m-d', (int) strtotime($bugun . ' +7 days')),
        'deneme_1' => date('Y-m-d', (int) strtotime($bugun . ' +1 day')),
        'deneme_bitti' => date('Y-m-d', (int) strtotime($bugun . ' -1 day')),
    ];
    foreach ($plan as $sablon => $tarih) {
        foreach (merkez_rows("SELECT * FROM magazalar WHERE plan = 'deneme' AND durum = 'aktif' AND deneme_bitis = ?", [$tarih]) as $m) {
            if (eposta_sablonla($sablon, $m, $sablon . ':' . $m['id'] . ':' . $tarih)) {
                $out['hatirlatma']++;
            }
        }
    }
    $k = eposta_kuyruk_isle();
    $out['gonderilen'] = $k['gonderilen'];
    $out['hata'] = $k['hata'];
    return $out;
}

/* ---------------- Toplu / serbest gönderim (merkez panel) ---------------- */

/** Hedef mağazalar: tek | aktif | deneme | beklemede | dondu | hepsi. */
function eposta_hedef_magazalar(string $hedef, int $magazaId = 0): array
{
    return match ($hedef) {
        'tek'       => array_filter([merkez_row('SELECT * FROM magazalar WHERE id = ?', [$magazaId])]),
        'aktif'     => merkez_rows("SELECT * FROM magazalar WHERE durum = 'aktif' ORDER BY id"),
        'deneme'    => merkez_rows("SELECT * FROM magazalar WHERE durum = 'aktif' AND plan = 'deneme' ORDER BY id"),
        'ucretli'   => merkez_rows("SELECT * FROM magazalar WHERE durum = 'aktif' AND plan = 'ucretli' ORDER BY id"),
        'beklemede' => merkez_rows("SELECT * FROM magazalar WHERE durum = 'beklemede' ORDER BY id"),
        'dondu'     => merkez_rows("SELECT * FROM magazalar WHERE durum = 'dondu' ORDER BY id"),
        'hepsi'     => merkez_rows('SELECT * FROM magazalar ORDER BY id'),
        default     => throw new DomainException('Alıcı grubu geçersiz.'),
    };
}

/**
 * Panelden gönderim: şablonlu ya da serbest metin, tek mağaza / grup / elle adres.
 * $v: hedef, magaza_id, adres (elle), tur (islem|duyuru), konu, metin. Döner: kuyruğa giren sayı.
 */
function eposta_panel_gonder(array $v): int
{
    $konu = (string) ($v['konu'] ?? '');
    $metin = (string) ($v['metin'] ?? '');
    $tur = ($v['tur'] ?? 'duyuru') === 'islem' ? 'islem' : 'duyuru';
    $hedef = (string) ($v['hedef'] ?? 'tek');
    $a = eposta_ayar();
    if (!eposta_hazir($a)) {
        throw new DomainException('Önce e-posta ayarlarını kaydedip açın.');
    }
    $sayi = 0;
    if ($hedef === 'adres') {
        $adresler = array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) ($v['adres'] ?? '')) ?: []));
        if (!$adresler) {
            throw new DomainException('En az bir e-posta adresi yazın.');
        }
        if (count($adresler) > 50) {
            throw new DomainException('Elle en fazla 50 adres yazılabilir; mağazalara toplu gönderim için alıcı grubunu seçin.');
        }
        foreach ($adresler as $x) {
            $d = eposta_degiskenler(['email' => $x], $a);
            if (eposta_kuyruga(['kime' => $x, 'konu' => eposta_doldur($konu, $d), 'metin' => eposta_doldur($metin, $d), 'tur' => 'islem', 'sablon' => 'serbest'])) {
                $sayi++;
            }
        }
    } else {
        $liste = eposta_hedef_magazalar($hedef, (int) ($v['magaza_id'] ?? 0));
        if (!$liste) {
            throw new DomainException('Seçilen grupta mağaza yok.');
        }
        foreach ($liste as $m) {
            if ($tur === 'duyuru' && !empty($m['eposta_ret'])) {
                continue;
            }
            $d = eposta_degiskenler($m, $a);
            if (eposta_kuyruga(['kime' => (string) $m['email'], 'konu' => eposta_doldur($konu, $d), 'metin' => eposta_doldur($metin, $d),
                'tur' => $tur, 'sablon' => 'serbest', 'magaza_id' => (int) $m['id']])) {
                $sayi++;
            }
        }
    }
    return $sayi;
}

/** Test e-postası: kuyruğa girmeden doğrudan gönderir; hata mesajını olduğu gibi fırlatır. */
function eposta_test_gonder(string $kime): void
{
    $a = eposta_ayar();
    if ($a['host'] === '' || $a['gonderen_adres'] === '') {
        throw new DomainException('Önce SMTP sunucusu ve gönderen adresini kaydedin.');
    }
    if (!filter_var($kime, FILTER_VALIDATE_EMAIL)) {
        throw new DomainException('Test adresi geçersiz.');
    }
    $metin = "Merhaba,\n\nBu, OptiFlow merkez panelinden gönderilen bir deneme e-postasıdır. Bu e-postayı görüyorsanız e-posta ayarlarınız çalışıyor.\n\nGönderen: {$a['gonderen_adres']}\nSunucu: {$a['host']}:{$a['port']}\n\nOptiFlow";
    try {
        eposta_tasi($a, $kime, eposta_ileti($a, $kime, 'OptiFlow e-posta denemesi', $metin, eposta_html($metin, '', $a)));
    } catch (RuntimeException $e) {
        throw new DomainException($e->getMessage());
    }
}

function eposta_son_kayitlar(int $limit = 50): array
{
    return merkez_rows('SELECT k.*, m.isim AS magaza_isim FROM eposta_kuyruk k LEFT JOIN magazalar m ON m.id = k.magaza_id ORDER BY k.id DESC LIMIT ' . max(1, min(200, $limit)));
}

function eposta_ozet(): array
{
    $out = ['bekliyor' => 0, 'gonderildi' => 0, 'hata' => 0, 'iptal' => 0];
    foreach (merkez_rows('SELECT durum, COUNT(*) AS n FROM eposta_kuyruk GROUP BY durum') as $r) {
        $out[$r['durum']] = (int) $r['n'];
    }
    return $out;
}

/** Hatalı satırı yeniden kuyruğa al. */
function eposta_yeniden_dene(int $id): void
{
    merkez_q("UPDATE eposta_kuyruk SET durum = 'bekliyor', deneme = 0 WHERE id = ? AND durum IN ('hata', 'bekliyor')", [$id]);
}

/** Bekleyen bir satırı iptal et. */
function eposta_iptal(int $id): void
{
    merkez_q("UPDATE eposta_kuyruk SET durum = 'iptal' WHERE id = ? AND durum = 'bekliyor'", [$id]);
}

/** Duyurulardan çıkma (eposta-ret.php). Döner: mağaza adı ya da null (imza geçersiz). */
function eposta_ret_uygula(int $magazaId, string $imza, bool $geriAl = false): ?string
{
    if ($magazaId < 1 || !hash_equals(eposta_ret_imza($magazaId), $imza)) {
        return null;
    }
    $m = merkez_row('SELECT id, isim FROM magazalar WHERE id = ?', [$magazaId]);
    if (!$m) {
        return null;
    }
    merkez_q('UPDATE magazalar SET eposta_ret = ? WHERE id = ?', [$geriAl ? 0 : 1, $magazaId]);
    return (string) $m['isim'];
}
