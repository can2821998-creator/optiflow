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

/** Tek bir otomatik e-postayı açar/kapatır (merkez panel › Otomatik e-postalar). */
function eposta_otomatik_ayarla(string $sablon, bool $acik): void
{
    if (!isset(eposta_sablon_tanimlari()[$sablon])) {
        throw new DomainException('Bilinmeyen şablon.');
    }
    $f = eposta_dizin() . '/ayar.json';
    $p = is_file($f) ? json_decode((string) file_get_contents($f), true) : [];
    $p = is_array($p) ? $p : [];
    $p['otomatik'] = array_merge(eposta_ayar()['otomatik'], [$sablon => $acik]);
    file_put_contents($f, json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    @chmod($f, 0640);
}

function eposta_hazir(?array $a = null): bool
{
    $a ??= eposta_ayar();
    return $a['acik'] && $a['host'] !== '' && $a['gonderen_adres'] !== '';
}

/* ---------------- Şablonlar ---------------- */

/** Otomatik e-postalar: anahtar => [ad, ne zaman, alıcı, grup]. */
function eposta_sablon_tanimlari(): array
{
    return [
        'kayit_alindi'        => ['Kaydınız alındı', 'Kayıt formu gönderilince (mağaza onay bekliyorsa)', 'Mağaza', 'Kayıt'],
        'hesap_hazir'         => ['Hesabınız hazır', 'Kayıtta hesap hemen açılınca ya da merkez panelden etkinleştirilince', 'Mağaza', 'Kayıt'],
        'yonetici_yeni_kayit' => ['Yeni kayıt bildirimi', 'Her yeni kayıtta', 'Siz', 'Kayıt'],
        'deneme_7'            => ['Deneme bitiyor: 7 gün', 'Deneme bitişinden 7 gün önce', 'Mağaza', 'Deneme'],
        'deneme_1'            => ['Deneme bitiyor: yarın', 'Deneme bitişinden 1 gün önce', 'Mağaza', 'Deneme'],
        'deneme_bitti'        => ['Deneme sona erdi', 'Deneme bitişinin ertesi günü', 'Mağaza', 'Deneme'],
        'deneme_uzatildi'     => ['Deneme uzatıldı', 'Merkez panelden deneme süresi uzatılınca', 'Mağaza', 'Deneme'],
        'plan_ucretli'        => ['Abonelik başladı', 'Plan merkez panelden "Ücretli" yapılınca', 'Mağaza', 'Hesap'],
        'hesap_donduruldu'    => ['Hesap donduruldu', 'Merkez panelden donduruldu', 'Mağaza', 'Hesap'],
        'hesap_acildi'        => ['Hesap yeniden açıldı', 'Dondurulan hesap yeniden aktif yapılınca', 'Mağaza', 'Hesap'],
        'sifre_degisti'       => ['Giriş şifresi değişti', 'Merkez panelden mağaza giriş şifresi sıfırlanınca', 'Mağaza', 'Hesap'],
    ];
}

/** Varsayılan metinler. İmza ve alt bilgi tasarımda kendiliğinden eklenir; metne yazmayın. */
function eposta_varsayilan_sablonlar(): array
{
    return [
        'kayit_alindi' => [
            'konu' => 'Kaydınız alındı · OptiFlow',
            'baslik' => 'Hoş geldiniz, {magaza}!',
            'metin' => "Merhaba,\n\nOptiFlow'a kaydınızı aldık, aramıza katıldığınız için teşekkür ederiz. Mağazanızın hesabını şu anda hazırlıyoruz; hazır olduğunda giriş bilgilerinizle size hemen yeni bir e-posta göndereceğiz.\n\nBu arada bir sorunuz olursa bu e-postayı yanıtlamanız yeterli.",
        ],
        'hesap_hazir' => [
            'konu' => 'Hesabınız hazır, giriş yapabilirsiniz · OptiFlow',
            'baslik' => 'Hesabınız kullanıma hazır',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow hesabınız açıldı. Müşteri kartından reçeteye, siparişten SGK faturasına kadar her şey artık tek ekranda.\n\nDeneme süreniz boyunca bütün özellikler açık; kart bilgisi istemiyoruz. Kurulumda yardım isterseniz bu e-postayı yanıtlayın ya da bizi arayın, birlikte bakalım.",
        ],
        'yonetici_yeni_kayit' => [
            'konu' => 'Yeni kayıt: {magaza}',
            'baslik' => '{magaza} kayıt oldu',
            'metin' => 'Yeni bir mağaza kaydı geldi. Ayrıntılar aşağıda.',
        ],
        'deneme_7' => [
            'konu' => 'Deneme sürenizin bitmesine {kalan_gun} gün kaldı · OptiFlow',
            'baslik' => 'Deneme sürenizin bitmesine {kalan_gun} gün kaldı',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow deneme süreniz {deneme_bitis} tarihinde bitiyor. Kullanmaya devam etmek isterseniz bu e-postayı yanıtlamanız ya da bizi aramanız yeterli; verileriniz olduğu gibi kalır.\n\nHenüz denemediğiniz bir özellik varsa (Medula aktarımı, SGK ay sonu faturası, atölye takibi) kısa bir görüşmede birlikte bakabiliriz.",
        ],
        'deneme_1' => [
            'konu' => 'Deneme süreniz yarın bitiyor · OptiFlow',
            'baslik' => 'Deneme süreniz yarın bitiyor',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow deneme süreniz yarın ({deneme_bitis}) bitiyor.\n\nDevam etmek için bu e-postayı yanıtlayın ya da bizi arayın; hesabınız ve bütün kayıtlarınız kesintisiz devam eder.",
        ],
        'deneme_bitti' => [
            'konu' => 'Deneme süreniz sona erdi · OptiFlow',
            'baslik' => 'Deneme süreniz sona erdi',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow deneme süreniz {deneme_bitis} tarihinde sona erdi. Merak etmeyin: müşterileriniz, siparişleriniz ve bütün kayıtlarınız silinmedi.\n\nDevam etmek isterseniz bu e-postayı yanıtlamanız yeterli; hesabınızı hemen yeniden açarız.",
        ],
        'deneme_uzatildi' => [
            'konu' => 'Deneme süreniz uzatıldı · OptiFlow',
            'baslik' => 'Deneme süreniz uzatıldı',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow deneme sürenizi uzattık. Yeni bitiş tarihi {deneme_bitis}; o güne kadar bütün özellikler açık.\n\nİyi kullanımlar!",
        ],
        'plan_ucretli' => [
            'konu' => 'Aboneliğiniz başladı, teşekkürler · OptiFlow',
            'baslik' => 'Teşekkürler, {magaza}!',
            'metin' => "Merhaba,\n\nOptiFlow aboneliğiniz başladı; bizi tercih ettiğiniz için teşekkür ederiz. Hesabınız ve bütün kayıtlarınız olduğu gibi devam ediyor.\n\nYeni özellikler ve güncellemeler kendiliğinden gelir. Bir isteğiniz ya da sorunuz olursa bu e-postayı yanıtlamanız yeterli.",
        ],
        'hesap_donduruldu' => [
            'konu' => 'Hesabınız donduruldu · OptiFlow',
            'baslik' => 'Hesabınız donduruldu',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow hesabınız donduruldu; şu anda giriş yapılamıyor. Kayıtlarınız silinmedi, hesap yeniden açıldığında her şey kaldığı yerden devam eder.\n\nBunun bir hata olduğunu düşünüyorsanız ya da hesabı yeniden açmak istiyorsanız bu e-postayı yanıtlayın ya da bizi arayın.",
        ],
        'hesap_acildi' => [
            'konu' => 'Hesabınız yeniden açıldı · OptiFlow',
            'baslik' => 'Tekrar hoş geldiniz!',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow hesabınız yeniden açıldı. Bütün kayıtlarınız kaldığı yerde; hemen giriş yapabilirsiniz.",
        ],
        'sifre_degisti' => [
            'konu' => 'Mağaza giriş şifreniz değiştirildi · OptiFlow',
            'baslik' => 'Giriş şifreniz değiştirildi',
            'metin' => "Merhaba,\n\n{magaza} için OptiFlow mağaza giriş şifresi {tarih} tarihinde değiştirildi. Yeni şifre güvenliğiniz için bu e-postada yer almaz.\n\nBu değişikliği siz istemediyseniz hemen bizi arayın.",
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
        $sec = static fn(string $alan) => trim((string) ($o[$alan] ?? '')) !== '' ? (string) $o[$alan] : $s[$alan];
        $out[$k] = ['konu' => $sec('konu'), 'baslik' => $sec('baslik'), 'metin' => $sec('metin'), 'ozel' => isset($ozel[$k])];
    }
    return $out;
}

/** Şablonu kaydeder; boş konu/metin ya da $varsayilan=true → varsayılana döner. */
function eposta_sablon_kaydet(string $anahtar, string $konu, string $metin, bool $varsayilan = false, string $baslik = ''): void
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
        if (mb_strlen($konu) > 200 || mb_strlen($baslik) > 200 || mb_strlen($metin) > 10000) {
            throw new DomainException('Konu, başlık ya da metin çok uzun.');
        }
        $ozel[$anahtar] = ['konu' => str_replace(["\r", "\n"], ' ', $konu), 'baslik' => str_replace(["\r", "\n"], ' ', $baslik), 'metin' => str_replace("\r\n", "\n", $metin)];
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
    $durumlar = ['aktif' => 'Aktif', 'beklemede' => 'Onay bekliyor', 'dondu' => 'Donduruldu'];
    $mTel = (string) ($m['telefon'] ?? '');
    return [
        'magaza'         => (string) ($m['isim'] ?? ''),
        'email'          => (string) ($m['email'] ?? ''),
        'kullanici'      => (string) ($m['admin_kullanici'] ?? ''),
        'magaza_telefon' => $mTel !== '' && function_exists('phone_display') ? phone_display($mTel) : $mTel,
        'deneme_bitis'   => $bitis !== '' ? date('d.m.Y', (int) strtotime($bitis)) : '',
        'kalan_gun'      => (string) max(0, $kalan),
        'durum'          => $durumlar[(string) ($m['durum'] ?? '')] ?? (string) ($m['durum'] ?? ''),
        'id'             => (string) ($m['id'] ?? ''),
        'giris_adresi'   => $a['site_url'] . '/magaza-giris.php',
        'site'           => $a['site_url'],
        'telefon'        => $tel,
        'iletisim'       => $a['yanit_adres'] ?: $a['gonderen_adres'],
        'tarih'          => date('d.m.Y H:i'),
    ];
}

function eposta_doldur(string $metin, array $d): string
{
    return preg_replace_callback('/\{([a-z_]+)\}/', static fn($x) => array_key_exists($x[1], $d) ? (string) $d[$x[1]] : $x[0], $metin) ?? $metin;
}

/* ---------------- Görünüm (e-posta türüne göre tasarım) ---------------- */

/** Renk temaları: [ana, koyu, açık zemin, adı]. */
function eposta_temalar(): array
{
    return [
        'bordo'   => ['#b4233c', '#6e1324', '#fbe8ec', 'Duyuru (bordo)'],
        'yesil'   => ['#1f8a57', '#0f5233', '#e3f3ea', 'Müjde (yeşil)'],
        'mavi'    => ['#2c5aa0', '#17345f', '#e6eefb', 'Bilgi (mavi)'],
        'amber'   => ['#c47f17', '#7a4c0c', '#fcf0dc', 'Hatırlatma (turuncu)'],
        'kirmizi' => ['#b3312a', '#6b1712', '#fde9e8', 'Önemli (kırmızı)'],
        'koyu'    => ['#3a3236', '#141012', '#f3efef', 'Sade (siyah)'],
    ];
}

/**
 * Otomatik e-postanın tasarım parçaları (doldurulmamış; {değişken}ler eposta_gorunum_doldur ile dolar).
 * tema, rozet, alt (başlık altı), vurgu [büyük, küçük], adimlar [[başlık, açıklama, tamam]], bilgiler [[etiket, değer]], buton [yazı, adres].
 */
function eposta_sablon_gorunum(string $sablon): array
{
    $giris = ['Mağazama giriş yap', '{giris_adresi}'];
    $yaz = ['Bize yazın', 'mailto:{iletisim}?subject=OptiFlow%20-%20{id}'];
    return match ($sablon) {
        'kayit_alindi' => ['tema' => 'amber', 'rozet' => 'Kaydınız alındı', 'alt' => 'Mağazanızın hesabını hazırlıyoruz.',
            'adimlar' => [['Kayıt alındı', 'Bilgileriniz bize ulaştı.', true], ['Hesap hazırlanıyor', 'Mağazanıza özel veritabanını kuruyoruz.', false], ['Giriş bilgileri', 'Hazır olunca e-postayla haber veriyoruz.', false]],
            'bilgiler' => [['Mağaza', '{magaza}'], ['E-posta', '{email}'], ['Yönetici kullanıcı', '{kullanici}']]],
        'hesap_hazir' => ['tema' => 'yesil', 'rozet' => 'Hesabınız açıldı', 'alt' => 'İki adımda giriş yapın, hemen başlayın.',
            'adimlar' => [['Mağaza girişi', 'E-posta: {email} ve kayıtta belirlediğiniz mağaza şifresi', false], ['Kullanıcı girişi', 'Kullanıcı adı: {kullanici} ve yönetici şifreniz', false], ['İlk adımlar', 'Medula, SGK ve atölye rehberleri: {site}/rehber.php', false]],
            'bilgiler' => [['Deneme bitişi', '{deneme_bitis}']], 'buton' => $giris],
        'yonetici_yeni_kayit' => ['tema' => 'koyu', 'rozet' => 'Merkez bildirimi', 'alt' => '{tarih}',
            'bilgiler' => [['Mağaza', '{magaza}'], ['E-posta', '{email}'], ['Telefon', '{magaza_telefon}'], ['Yönetici', '{kullanici}'], ['Durum', '{durum}'], ['Deneme bitişi', '{deneme_bitis}']],
            'buton' => ['Merkez panelde aç', '{site}/merkez-panel.php?magaza={id}']],
        'deneme_7' => ['tema' => 'mavi', 'rozet' => 'Deneme süresi', 'vurgu' => ['{kalan_gun} gün', 'Deneme bitişi: {deneme_bitis}'], 'buton' => $yaz],
        'deneme_1' => ['tema' => 'amber', 'rozet' => 'Son gün yaklaştı', 'vurgu' => ['Yarın', 'Deneme bitişi: {deneme_bitis}'], 'buton' => $yaz],
        'deneme_bitti' => ['tema' => 'kirmizi', 'rozet' => 'Deneme bitti', 'vurgu' => ['Verileriniz güvende', 'Hiçbir kayıt silinmedi'], 'buton' => ['Hesabımı yeniden açın', 'mailto:{iletisim}?subject=OptiFlow%20devam%20-%20{id}']],
        'deneme_uzatildi' => ['tema' => 'mavi', 'rozet' => 'Güzel haber', 'vurgu' => ['{deneme_bitis}', 'Yeni deneme bitiş tarihi'], 'buton' => $giris],
        'plan_ucretli' => ['tema' => 'bordo', 'rozet' => 'Aboneliğiniz başladı', 'alt' => 'OptiFlow ailesine hoş geldiniz.', 'buton' => $giris],
        'hesap_donduruldu' => ['tema' => 'kirmizi', 'rozet' => 'Hesap durumu', 'alt' => 'Kayıtlarınız silinmedi.', 'buton' => $yaz],
        'hesap_acildi' => ['tema' => 'yesil', 'rozet' => 'Hesabınız açık', 'alt' => 'Her şey kaldığı yerde.', 'buton' => $giris],
        'sifre_degisti' => ['tema' => 'koyu', 'rozet' => 'Güvenlik bildirimi', 'bilgiler' => [['Mağaza', '{magaza}'], ['Değişiklik zamanı', '{tarih}']]],
        default => [],
    };
}

/** Görünümdeki bütün metinlere değişkenleri yerleştirir; boş kalan bilgi satırlarını atar. */
function eposta_gorunum_doldur(array $g, array $d): array
{
    $dol = static fn($x) => is_string($x) ? eposta_doldur($x, $d) : $x;
    foreach (['rozet', 'baslik', 'alt'] as $k) {
        if (isset($g[$k])) {
            $g[$k] = $dol($g[$k]);
        }
    }
    foreach (['vurgu', 'buton'] as $k) {
        if (isset($g[$k])) {
            $g[$k] = array_map($dol, $g[$k]);
        }
    }
    if (isset($g['adimlar'])) {
        $g['adimlar'] = array_map(static fn($s) => [$dol($s[0]), $dol($s[1]), !empty($s[2])], $g['adimlar']);
    }
    if (isset($g['bilgiler'])) {
        $g['bilgiler'] = array_values(array_filter(array_map(static fn($s) => [$dol($s[0]), $dol($s[1])], $g['bilgiler']), static fn($s) => trim($s[1]) !== ''));
    }
    return $g;
}

/** Düz metin sürümü (HTML gösteremeyen programlar ve önizleme satırı için). */
function eposta_duz(string $metin, array $g = [], ?array $a = null): string
{
    $a ??= eposta_ayar();
    $t = trim(str_replace("\r\n", "\n", $metin));
    if (!empty($g['adimlar'])) {
        $t .= "\n";
        foreach ($g['adimlar'] as $i => $s) {
            $t .= "\n" . ($i + 1) . '. ' . $s[0] . ($s[1] !== '' ? ': ' . $s[1] : '');
        }
    }
    if (!empty($g['bilgiler'])) {
        $t .= "\n";
        foreach ($g['bilgiler'] as $s) {
            $t .= "\n" . $s[0] . ': ' . $s[1];
        }
    }
    if (!empty($g['buton'][1]) && !str_contains($t, (string) $g['buton'][1])) {
        $t .= "\n\n" . $g['buton'][0] . ': ' . preg_replace('/^mailto:([^?]+).*$/', '$1', (string) $g['buton'][1]);
    }
    if (($g['tur'] ?? '') !== 'yonetici') {
        $tel = function_exists('optiflow_pazarlama') ? (string) (optiflow_pazarlama()['telefon'] ?? '') : '';
        $t .= "\n\nKolaylıklar dileriz,\nOptiFlow ekibi\n" . preg_replace('#^https?://#', '', $a['site_url']) . ($tel !== '' ? ' · ' . $tel : '');
    }
    return $t;
}

/* ---------------- Gövde (düz metin → markalı HTML) ---------------- */

function eposta_html(string $metin, string $retUrl = '', ?array $a = null, array $g = []): string
{
    $a ??= eposta_ayar();
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $temalar = eposta_temalar();
    [$ana, $koyu, $acik] = $temalar[$g['tema'] ?? ''] ?? $temalar[($g['tur'] ?? '') === 'duyuru' ? 'bordo' : 'koyu'];
    $font = "font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
    $site = preg_replace('#^https?://#', '', $a['site_url']);
    $tel = function_exists('optiflow_pazarlama') ? (string) (optiflow_pazarlama()['telefon'] ?? '') : '';
    $butonUrl = (string) ($g['buton'][1] ?? '');

    // Metin paragrafları (bağlantılar tıklanır; düğmeyle aynı tek başına bağlantı paragrafı atılır)
    $govde = '';
    foreach (preg_split("/\n{2,}/", trim(str_replace("\r\n", "\n", $metin))) ?: [] as $p) {
        if ($butonUrl !== '' && trim($p) === $butonUrl) {
            continue;
        }
        $p = $h($p);
        $p = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:' . $ana . ';font-weight:600">$1</a>', $p) ?? $p;
        $govde .= '<p style="margin:0 0 16px;font-size:15.5px;line-height:1.65;color:#2b2326">' . nl2br($p, false) . '</p>';
    }

    // Önizleme satırı (gelen kutusunda konunun yanında görünen metin)
    $ozet = mb_substr(trim(preg_replace('/\s+/', ' ', preg_replace('/^Merhaba,?\s*/u', '', trim($metin)) ?? '') ?? ''), 0, 140);
    $html = '<!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . $h((string) ($g['baslik'] ?? 'OptiFlow')) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#efeaeb;-webkit-text-size-adjust:100%">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#efeaeb">' . $h($ozet) . str_repeat('&#8199;&#65279;&#847; ', 30) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#efeaeb"><tr><td align="center" style="padding:28px 12px 36px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px">';

    // Üst şerit: logo
    $html .= '<tr><td style="padding:0 6px 14px;' . $font . '"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td style="font-size:22px;font-weight:800;letter-spacing:-.4px;color:#141012;' . $font . '">'
        . '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' . $ana . ';margin-right:8px;vertical-align:middle"></span>Opti<span style="color:#b4233c">Flow</span></td>'
        . '<td align="right" style="font-size:11px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;color:#8a7d80;' . $font . '">Optik mağaza yönetimi</td>'
        . '</tr></table></td></tr>';

    // Kart
    $html .= '<tr><td style="background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 18px 40px -28px rgba(60,14,28,.45)">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';

    // Kahraman alanı: türüne göre renk
    if (!empty($g['baslik']) || !empty($g['rozet'])) {
        $html .= '<tr><td style="background-color:' . $koyu . ';background-image:linear-gradient(135deg,' . $ana . ' 0%,' . $koyu . ' 100%);padding:34px 34px 30px;border-radius:20px 20px 0 0;' . $font . '">';
        if (!empty($g['rozet'])) {
            $html .= '<span style="display:inline-block;padding:6px 12px;border-radius:99px;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.28);color:#ffffff;font-size:11px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase">' . $h((string) $g['rozet']) . '</span>';
        }
        if (!empty($g['baslik'])) {
            $html .= '<h1 style="margin:16px 0 0;font-size:28px;line-height:1.22;font-weight:800;letter-spacing:-.5px;color:#ffffff;' . $font . '">' . $h((string) $g['baslik']) . '</h1>';
        }
        if (!empty($g['alt'])) {
            $html .= '<p style="margin:10px 0 0;font-size:15px;line-height:1.5;color:rgba(255,255,255,.82)">' . $h((string) $g['alt']) . '</p>';
        }
        $html .= '</td></tr>';
    } else {
        $html .= '<tr><td style="height:6px;background:' . $ana . ';border-radius:20px 20px 0 0;font-size:0;line-height:0">&nbsp;</td></tr>';
    }

    $html .= '<tr><td style="padding:30px 34px 8px;' . $font . '">';

    // Büyük vurgu kutusu (deneme günü vb.)
    if (!empty($g['vurgu'][0])) {
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px"><tr><td align="center" style="background:' . $acik . ';border-radius:16px;padding:22px 16px;' . $font . '">'
            . '<div style="font-size:34px;line-height:1.1;font-weight:800;letter-spacing:-1px;color:' . $koyu . '">' . $h((string) $g['vurgu'][0]) . '</div>'
            . (!empty($g['vurgu'][1]) ? '<div style="margin-top:6px;font-size:13px;font-weight:700;color:' . $ana . ';letter-spacing:.3px">' . $h((string) $g['vurgu'][1]) . '</div>' : '')
            . '</td></tr></table>';
    }

    $html .= $govde;

    // Adımlar (numaralı ya da tamamlandı işaretli)
    if (!empty($g['adimlar'])) {
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 18px">';
        foreach ($g['adimlar'] as $i => $s) {
            $tamam = !empty($s[2]);
            $daire = $tamam ? 'background:' . $ana . ';color:#ffffff' : 'background:' . $acik . ';color:' . $koyu;
            $cizgi = $i < count($g['adimlar']) - 1 ? 'border-left:2px solid ' . $acik . ';' : '';
            $aciklama = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:' . $ana . ';font-weight:600">$1</a>', $h((string) $s[1])) ?? '';
            $html .= '<tr><td width="34" valign="top" style="padding:0">'
                . '<div style="width:30px;height:30px;line-height:30px;border-radius:50%;text-align:center;font-size:13px;font-weight:800;' . $daire . ';' . $font . '">' . ($tamam ? '&#10003;' : ($i + 1)) . '</div>'
                . ($cizgi !== '' ? '<div style="margin:2px 0 0 14px;height:20px;' . $cizgi . '"></div>' : '')
                . '</td><td valign="top" style="padding:4px 0 12px 12px;' . $font . '">'
                . '<div style="font-size:15px;font-weight:800;color:#1b1416">' . $h((string) $s[0]) . '</div>'
                . ($s[1] !== '' ? '<div style="font-size:13.5px;line-height:1.5;color:#6b5f62;margin-top:2px">' . $aciklama . '</div>' : '')
                . '</td></tr>';
        }
        $html .= '</table>';
    }

    // Bilgi tablosu
    if (!empty($g['bilgiler'])) {
        $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:4px 0 22px;background:#faf7f7;border:1px solid #eee5e6;border-radius:14px">';
        $son = count($g['bilgiler']) - 1;
        foreach ($g['bilgiler'] as $i => $s) {
            $alt = $i < $son ? 'border-bottom:1px solid #eee5e6;' : '';
            $html .= '<tr><td style="padding:12px 16px;' . $alt . 'font-size:12px;font-weight:700;letter-spacing:.6px;text-transform:uppercase;color:#8a7d80;' . $font . '" width="40%">' . $h((string) $s[0]) . '</td>'
                . '<td style="padding:12px 16px;' . $alt . 'font-size:14.5px;font-weight:700;color:#1b1416;word-break:break-word;' . $font . '">' . $h((string) $s[1]) . '</td></tr>';
        }
        $html .= '</table>';
    }

    // Düğme (Outlook dahil çalışan tablo düğmesi)
    if ($butonUrl !== '') {
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 26px"><tr>'
            . '<td align="center" bgcolor="' . $ana . '" style="border-radius:12px;background:' . $ana . ';background-image:linear-gradient(135deg,' . $ana . ',' . $koyu . ');box-shadow:0 10px 22px -12px ' . $ana . '">'
            . '<a href="' . $h($butonUrl) . '" style="display:inline-block;padding:15px 28px;font-size:15px;font-weight:800;color:#ffffff;text-decoration:none;border-radius:12px;' . $font . '">' . $h((string) $g['buton'][0]) . ' &rarr;</a>'
            . '</td></tr></table>';
    }

    // İmza
    if (($g['tur'] ?? '') !== 'yonetici') {
        $html .= '<p style="margin:4px 0 26px;font-size:15px;line-height:1.6;color:#2b2326">Kolaylıklar dileriz,<br><b style="color:#1b1416">OptiFlow ekibi</b></p>';
    }
    $html .= '</td></tr></table></td></tr>';

    // Alt bilgi
    $iletisim = $h($a['yanit_adres'] ?: $a['gonderen_adres']);
    $html .= '<tr><td align="center" style="padding:22px 20px 0;font-size:12.5px;line-height:1.7;color:#8a7d80;' . $font . '">'
        . '<b style="color:#4a3f42">OptiFlow</b> · Optik mağazaları için müşteri, reçete, sipariş ve SGK takibi<br>'
        . '<a href="' . $h($a['site_url']) . '" style="color:#8a7d80;text-decoration:underline">' . $h($site) . '</a>'
        . ($tel !== '' ? ' · <a href="tel:' . $h(preg_replace('/[^\d+]/', '', $tel) ?? '') . '" style="color:#8a7d80;text-decoration:none">' . $h($tel) . '</a>' : '')
        . ($iletisim !== '' ? ' · <a href="mailto:' . $iletisim . '" style="color:#8a7d80;text-decoration:none">' . $iletisim . '</a>' : '');
    if ($retUrl !== '') {
        $html .= '<br><span style="font-size:11.5px">Bu e-postayı OptiFlow kullanıcısı olduğunuz için aldınız. <a href="' . $h($retUrl) . '" style="color:#8a7d80;text-decoration:underline">Duyuru e-postalarını almak istemiyorum</a></span>';
    } elseif (($g['tur'] ?? '') !== 'yonetici') {
        $html .= '<br><span style="font-size:11.5px">Bu e-posta OptiFlow hesabınızla ilgili olduğu için gönderildi.</span>';
    }
    $html .= '</td></tr></table></td></tr></table></body></html>';
    return $html;
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
 * Kuyruğa ekler. $e: kime, konu, metin, tur (islem|duyuru|yonetici), sablon, magaza_id, anahtar (tekrarı önler),
 * gorunum (tasarım: tema, rozet, baslik, alt, vurgu, adimlar, bilgiler, buton — doldurulmuş).
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
        'INSERT INTO eposta_kuyruk (magaza_id, kime, konu, metin, tur, sablon, anahtar, durum, deneme, created_at, gorunum) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [isset($e['magaza_id']) ? (int) $e['magaza_id'] : null, $kime, mb_substr($konu, 0, 200), $metin, $tur, (string) ($e['sablon'] ?? 'serbest'),
         $anahtar !== '' ? $anahtar : null, 'bekliyor', 0, date('Y-m-d H:i:s'),
         !empty($e['gorunum']) && is_array($e['gorunum']) ? json_encode($e['gorunum'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]
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
        $g = json_decode((string) ($r['gorunum'] ?? ''), true);
        $g = is_array($g) ? $g : [];
        $g['tur'] = (string) $r['tur'];
        $ileti = eposta_ileti($a, (string) $r['kime'], (string) $r['konu'], eposta_duz((string) $r['metin'], $g, $a), eposta_html((string) $r['metin'], $retUrl, $a, $g), $ek);
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
        if ($id && eposta_hazir() && empty($GLOBALS['eposta_ertele'])) {
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
        'gorunum' => eposta_gorunum_doldur(eposta_sablon_gorunum($sablon) + ['baslik' => $s['baslik']], $d),
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

/**
 * Merkez panelde mağaza değişince (4.51.1): önceki ve sonraki satıra bakıp uygun e-postayı seçer.
 * dondu → hesap_donduruldu, dondu→aktif → hesap_acildi, plan ücretli olunca → plan_ucretli,
 * deneme bitişi ileri alınınca (deneme planında) → deneme_uzatildi. Her olay o güne bir kez.
 */
function eposta_olay_magaza_degisti(?array $once, int $magazaId): void
{
    try {
        $m = merkez_row('SELECT * FROM magazalar WHERE id = ?', [$magazaId]);
        if (!$m || !$once) {
            return;
        }
        $gun = date('Y-m-d');
        if ($once['durum'] !== 'dondu' && $m['durum'] === 'dondu') {
            eposta_sablonla('hesap_donduruldu', $m, 'hesap_donduruldu:' . $magazaId . ':' . $gun);
        } elseif ($once['durum'] === 'dondu' && $m['durum'] === 'aktif') {
            eposta_sablonla('hesap_acildi', $m, 'hesap_acildi:' . $magazaId . ':' . $gun);
        }
        if (($once['plan'] ?? '') !== 'ucretli' && ($m['plan'] ?? '') === 'ucretli' && $m['durum'] === 'aktif') {
            eposta_sablonla('plan_ucretli', $m, 'plan_ucretli:' . $magazaId . ':' . $gun);
        } elseif (($m['plan'] ?? '') === 'deneme' && $m['durum'] === 'aktif' && !empty($m['deneme_bitis'])
            && (string) $m['deneme_bitis'] > (string) ($once['deneme_bitis'] ?? '') && (string) $m['deneme_bitis'] >= $gun) {
            eposta_sablonla('deneme_uzatildi', $m, 'deneme_uzatildi:' . $magazaId . ':' . $m['deneme_bitis']);
        }
    } catch (Throwable $e) {
        if (function_exists('app_log')) {
            app_log('eposta_olay_magaza_degisti: ' . $e->getMessage());
        }
    }
}

/** Merkez panelden mağaza giriş şifresi sıfırlandı → güvenlik bildirimi (şifre e-postada yer almaz). */
function eposta_olay_sifre_degisti(int $magazaId): void
{
    try {
        $m = merkez_row('SELECT * FROM magazalar WHERE id = ?', [$magazaId]);
        if ($m) {
            eposta_sablonla('sifre_degisti', $m, 'sifre_degisti:' . $magazaId . ':' . date('YmdHis') . bin2hex(random_bytes(3)));
        }
    } catch (Throwable $e) {
        if (function_exists('app_log')) {
            app_log('eposta_olay_sifre_degisti: ' . $e->getMessage());
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
    // Tasarım: tema + isteğe bağlı başlık ve düğme
    $tema = isset(eposta_temalar()[$v['tema'] ?? '']) ? (string) $v['tema'] : ($tur === 'duyuru' ? 'bordo' : 'koyu');
    $baslik = trim(str_replace(["\r", "\n"], ' ', (string) ($v['baslik'] ?? '')));
    $butonYazi = trim(str_replace(["\r", "\n"], ' ', (string) ($v['buton_yazi'] ?? '')));
    $butonUrl = trim((string) ($v['buton_url'] ?? ''));
    if ($butonUrl !== '' && !preg_match('#^(https?://|mailto:)[^\s"<>]+$#i', $butonUrl)) {
        throw new DomainException('Düğme bağlantısı https:// ya da mailto: ile başlamalı.');
    }
    if (mb_strlen($baslik) > 200 || mb_strlen($butonYazi) > 60) {
        throw new DomainException('Başlık ya da düğme yazısı çok uzun.');
    }
    $gorunumHam = ['tema' => $tema, 'rozet' => $tur === 'duyuru' ? 'Duyuru' : 'Bilgilendirme', 'baslik' => $baslik !== '' ? $baslik : $konu];
    if ($butonUrl !== '') {
        $gorunumHam['buton'] = [$butonYazi !== '' ? $butonYazi : 'Ayrıntılar', $butonUrl];
    }
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
            if (eposta_kuyruga(['kime' => $x, 'konu' => eposta_doldur($konu, $d), 'metin' => eposta_doldur($metin, $d), 'tur' => 'islem', 'sablon' => 'serbest',
                'gorunum' => eposta_gorunum_doldur($gorunumHam, $d)])) {
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
                'tur' => $tur, 'sablon' => 'serbest', 'magaza_id' => (int) $m['id'], 'gorunum' => eposta_gorunum_doldur($gorunumHam, $d)])) {
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
    $metin = "Merhaba,\n\nBu, OptiFlow merkez panelinden gönderilen bir deneme e-postasıdır. Bu e-postayı görüyorsanız e-posta ayarlarınız çalışıyor.";
    $g = ['tema' => 'yesil', 'rozet' => 'Deneme', 'baslik' => 'E-posta ayarlarınız çalışıyor', 'alt' => 'Mağazalarınıza giden e-postalar bu tasarımla ulaşır.',
          'bilgiler' => [['Gönderen', $a['gonderen_adres']], ['Sunucu', $a['host'] . ':' . $a['port']], ['Zaman', date('d.m.Y H:i')]], 'tur' => 'islem'];
    try {
        eposta_tasi($a, $kime, eposta_ileti($a, $kime, 'OptiFlow e-posta denemesi', eposta_duz($metin, $g, $a), eposta_html($metin, '', $a, $g)));
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

/**
 * Merkez panelde önizleme: bir şablonun (ya da kayıtlı bir gönderimin) e-postada görüneceği HTML.
 * Mağaza seçilmezse örnek bir mağazayla doldurulur.
 */
function eposta_onizleme(string $sablon, ?array $m = null): string
{
    $a = eposta_ayar();
    if ($a['gonderen_adres'] === '') {
        $a['gonderen_adres'] = 'info@optiflow.com.tr';
    }
    $m ??= ['id' => 0, 'isim' => 'Örnek Optik', 'email' => 'ornek@optik.com', 'admin_kullanici' => 'patron', 'telefon' => '05321234567',
            'durum' => 'aktif', 'plan' => 'deneme', 'deneme_bitis' => date('Y-m-d', strtotime('+7 days'))];
    $s = eposta_sablonlar()[$sablon] ?? null;
    if (!$s) {
        throw new DomainException('Bilinmeyen şablon.');
    }
    $d = eposta_degiskenler($m, $a);
    $g = eposta_gorunum_doldur(eposta_sablon_gorunum($sablon) + ['baslik' => $s['baslik']], $d);
    $g['tur'] = $sablon === 'yonetici_yeni_kayit' ? 'yonetici' : 'islem';
    return eposta_html(eposta_doldur($s['metin'], $d), '', $a, $g);
}

/** Kuyruktaki bir gönderimin HTML'i (gönderim kaydında "Gör"). */
function eposta_kayit_html(int $id): ?string
{
    $r = merkez_row('SELECT * FROM eposta_kuyruk WHERE id = ?', [$id]);
    if (!$r) {
        return null;
    }
    $g = json_decode((string) ($r['gorunum'] ?? ''), true);
    $g = is_array($g) ? $g : [];
    $g['tur'] = (string) $r['tur'];
    $ret = $r['tur'] === 'duyuru' && !empty($r['magaza_id']) ? eposta_ret_url((int) $r['magaza_id']) : '';
    return eposta_html((string) $r['metin'], $ret, null, $g);
}
