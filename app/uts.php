<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — ÜTS bildirimleri (4.13.0)

   Ürün Takip Sistemi (Sağlık Bakanlığı / TİTCK) "Takip ve İzleme" REST
   servisleri. İstekler JSON, POST; kimlik doğrulama mağazanın ÜTS'de
   e-imza/mobil imzayla ürettiği "Sistem Token"ı ile (utsToken başlığı).

   OPTİSYEN İÇİN KURALLAR
   • SGK'lı satış: karekod Medula'ya okutulunca ürün ÜTS'den Medula
     tarafından düşülür. OptiFlow bu ürünler için HİÇBİR bildirim yapmaz;
     yalnızca stokta "SGK (Medula)" diye çıkışını kaydeder. Aksi hâlde
     aynı ürün iki kez bildirilir ve ÜTS reddeder.
   • Ücretli satış: teslimde "tüketiciye verme" bildirimi (GIT = teslim tarihi).
   • Mal kabul: tedarikçinin "verme" bildirimi → mağazanın "alma" bildirimi.
   • Satış iptali/iade: "tüketiciden iade alma" (TID = tüketiciye verme ID).
   • SKT geçmiş / hasarlı: "imha / bertaraf" (BNO = tutanak no).
   • Tedarikçiye iade: "verme" (KUN = tedarikçinin ÜTS kurum no, BNO = belge no).

   ORTAMLAR
   • deneme : ÜTS'ye hiçbir istek gitmez; bildirim başarılı sayılır. Token
              alınana kadar ekranları ve akışı denemek içindir.
   • test   : utstest.saglik.gov.tr   • canli : utsuygulama.saglik.gov.tr

   GÜVENLİK
   • Token veritabanına şifreli yazılır (gizli_ayar, storage/.gizli-anahtar).
   • İstek yalnızca *.saglik.gov.tr alan adına, https ile gider; adres
     ayarlardan değiştirilse bile token başka sunucuya gönderilemez.
   • Müşterinin adı / T.C. no ÜTS'ye varsayılan olarak GÖNDERİLMEZ (optik
     ürünlerde zorunlu değil). Ürün kimlik isterse ayardan açılır.

   Bu dosyadaki SQL, taşınabilir (MySQL + testlerdeki SQLite) tutulmuştur:
   NOW()/INTERVAL yerine zaman PHP'de hesaplanır.
   ========================================================================== */

const UTS_ORTAMLAR = [
    'deneme' => 'Deneme — ÜTS\'ye gönderilmez',
    'test'   => 'ÜTS test ortamı',
    'canli'  => 'Canlı ÜTS',
];

const UTS_TABAN_VARSAYILAN = [
    'test'  => 'https://utstest.saglik.gov.tr/UTS/uh/rest',
    'canli' => 'https://utsuygulama.saglik.gov.tr/UTS/uh/rest',
];

/** Bildirim türleri: ad, ÜTS yolu (tabana göre). Yollar Ayarlar › ÜTS › Gelişmiş'ten değiştirilebilir. */
function uts_turler(): array
{
    return [
        'alma'             => ['ad' => 'Alma (mal kabul)',          'yol' => '/bildirim/alma/ekle'],
        'tuketiciye_verme' => ['ad' => 'Tüketiciye verme (satış)',  'yol' => '/bildirim/tuketiciyeVerme/ekle'],
        'tuketiciden_iade' => ['ad' => 'Tüketiciden iade alma',     'yol' => '/bildirim/tuketicidenIadeAlma/ekle'],
        'verme'            => ['ad' => 'Verme (tedarikçiye iade)',  'yol' => '/bildirim/verme/ekle'],
        'imha'             => ['ad' => 'İmha / bertaraf',           'yol' => '/bildirim/imhaBertaraf/ekle'],
    ];
}

/** Sorgulama servisleri. */
function uts_sorgular(): array
{
    return [
        'alma_bekleyen' => ['ad' => 'Kabul edilecek (alma bekleyen) ürünler', 'yol' => '/bildirim/sorgulama/kabulEdilecekTekilUrun'],
    ];
}

/** İmha / bertaraf gerekçeleri (ÜTS GRK). */
function uts_imha_gerekceleri(): array
{
    return [
        'SON_KULLANMA_TARIHI_GECMIS'           => 'Son kullanma tarihi geçmiş',
        'TASIMA_VE_SAKLAMA_KOSULLARI_BOZULMUS' => 'Taşıma / saklama koşulları bozulmuş (kırık, hasarlı)',
        'GONULLU_GERI_CEKME'                   => 'Gönüllü geri çekme',
        'KURUM_KARARI'                         => 'Kurum kararı',
        'SAHTE_KACAK'                          => 'Sahte / kaçak',
        'DIGER'                                => 'Diğer',
    ];
}

function uts_kategoriler(): array
{
    return [
        'cerceve' => 'Çerçeve',
        'cam'     => 'Cam',
        'lens'    => 'Kontakt lens',
        'gunes'   => 'Güneş gözlüğü',
        'diger'   => 'Diğer',
    ];
}

/** Tekil ürün durumları: [etiket, renk]. */
function uts_urun_durumlari(): array
{
    return [
        'gelen'         => ['ÜTS\'de kabul bekliyor', 'blue'],
        'alma_bekliyor' => ['Alma bildirimi gidiyor', 'amber'],
        'stokta'        => ['Stokta', 'green'],
        'satildi'       => ['Satıldı (ücretli)', 'gray'],
        'sgk'           => ['SGK · Medula düştü', 'gray'],
        'iade'          => ['Tedarikçiye iade', 'gray'],
        'imha'          => ['İmha edildi', 'gray'],
    ];
}

/** Bildirim durumları: [etiket, renk]. */
function uts_bildirim_durumlari(): array
{
    return [
        'onay_bekliyor' => ['Onay bekliyor', 'amber'],
        'bekliyor'      => ['Sırada', 'blue'],
        'gonderiliyor'  => ['Gönderiliyor', 'blue'],
        'gonderildi'    => ['ÜTS\'ye iletildi', 'green'],
        'elle'          => ['ÜTS\'de elle yapıldı', 'green'],
        'hata'          => ['Hata', 'red'],
        'iptal'         => ['İptal', 'gray'],
    ];
}

function uts_simdi(int $ekSaniye = 0): string
{
    return date('Y-m-d H:i:s', time() + $ekSaniye);
}

/* ---------------- Ayarlar ---------------- */

function uts_ortam(): string
{
    $o = setting('uts_ortam', 'deneme');
    return isset(UTS_ORTAMLAR[$o]) ? $o : 'deneme';
}

/** 'otomatik': teslimde kuyruğa girer ve arka planda gider. 'onayli': personel "Bildirimler"den onaylayınca gider. */
function uts_gonderim_modu(): string
{
    return setting('uts_gonderim', 'otomatik') === 'onayli' ? 'onayli' : 'otomatik';
}

function uts_kurum_no(): string
{
    return preg_replace('/\D/', '', setting('uts_kurum_no', '')) ?? '';
}

/** ÜTS alan adı güvenlik kontrolü: https + *.saglik.gov.tr. */
function uts_adres_guvenli_mi(string $url): bool
{
    $p = parse_url($url);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['port'])) {
        return false;
    }
    $host = strtolower((string) ($p['host'] ?? ''));
    return $host === 'saglik.gov.tr' || str_ends_with($host, '.saglik.gov.tr');
}

function uts_taban_url(?string $ortam = null): string
{
    $ortam ??= uts_ortam();
    if (!isset(UTS_TABAN_VARSAYILAN[$ortam])) {
        return '';
    }
    $ozel = rtrim(trim(setting('uts_taban_' . $ortam, '')), '/');
    return $ozel !== '' && uts_adres_guvenli_mi($ozel) ? $ozel : UTS_TABAN_VARSAYILAN[$ortam];
}

/** Yol ayar değerini temizler: yalnızca /harf/rakam/_- ; geçersizse ''. */
function uts_yol_temizle(string $yol): string
{
    $yol = '/' . ltrim(trim($yol), '/');
    return preg_match('~^(/[A-Za-z0-9_\-]+){1,8}$~', $yol) ? $yol : '';
}

function uts_yol(string $anahtar): string
{
    $tanim = uts_turler()[$anahtar] ?? uts_sorgular()[$anahtar] ?? null;
    if (!$tanim) {
        return '';
    }
    $ozel = uts_yol_temizle(setting('uts_yol_' . $anahtar, ''));
    return $ozel !== '/' && $ozel !== '' ? $ozel : $tanim['yol'];
}

function uts_hazir_mi(): bool
{
    if (uts_ortam() === 'deneme') {
        return true;
    }
    return gizli_ayar('uts_token') !== '';
}

/* ---------------- İstemci ---------------- */

/**
 * ÜTS'ye tek istek. Dönüş:
 *   ['ok' => bool, 'id' => ?string (ÜTS bildirim ID), 'mesaj' => string, 'tur' => 'basarili'|'yetki'|'kalici'|'gecici',
 *    'yanit' => string (kısaltılmış ham yanıt), 'veri' => mixed (çözülmüş JSON)]
 *
 * Testlerde $GLOBALS['__uts_tasiyici'] (callable: fn(string $url, array $basliklar, string $govde): array{durum,govde,hata})
 * ile ağ katmanı değiştirilebilir. Bu değişken yalnızca kodla atanabilir; ayarlardan gelmez.
 */
function uts_istek(string $yolAnahtari, array $govde, ?string $ortam = null): array
{
    $ortam ??= uts_ortam();
    if ($ortam === 'deneme') {
        return uts_deneme_yaniti($yolAnahtari, $govde);
    }
    $token = gizli_ayar('uts_token');
    if ($token === '') {
        return ['ok' => false, 'id' => null, 'mesaj' => 'ÜTS sistem token\'ı girilmemiş (Ayarlar › ÜTS).', 'tur' => 'yetki', 'yanit' => '', 'veri' => null];
    }
    $url = uts_taban_url($ortam) . uts_yol($yolAnahtari);
    if (!uts_adres_guvenli_mi($url)) {
        return ['ok' => false, 'id' => null, 'mesaj' => 'ÜTS adresi geçersiz: yalnızca https://…saglik.gov.tr adreslerine istek yapılır.', 'tur' => 'kalici', 'yanit' => '', 'veri' => null];
    }
    $basliklar = [
        'utsToken'     => $token,
        'Content-Type' => 'application/json; charset=utf-8',
        'Accept'       => 'application/json',
    ];
    $json = (string) json_encode($govde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $tasiyici = $GLOBALS['__uts_tasiyici'] ?? null;
    $r = is_callable($tasiyici) ? $tasiyici($url, $basliklar, $json) : dis_istek('POST', $url, $basliklar, $json, 30);
    return uts_yanit_coz((int) ($r['durum'] ?? 0), (string) ($r['govde'] ?? ''), (string) ($r['hata'] ?? ''));
}

/** Deneme ortamı: ağ yok; sorgular boş liste, bildirimler başarılı. */
function uts_deneme_yaniti(string $yolAnahtari, array $govde): array
{
    if (isset(uts_sorgular()[$yolAnahtari])) {
        return ['ok' => true, 'id' => null, 'mesaj' => 'Deneme modu: ÜTS\'den liste çekilmez.', 'tur' => 'basarili', 'yanit' => '{"deneme":true}', 'veri' => []];
    }
    $id = uts_uuid4();
    return ['ok' => true, 'id' => $id, 'mesaj' => 'Deneme modu: ÜTS\'ye gönderilmedi.', 'tur' => 'basarili', 'yanit' => '{"deneme":true,"BID":"' . $id . '"}', 'veri' => ['deneme' => true]];
}

function uts_uuid4(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function uts_uuid_mi(string $s): bool
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $s);
}

/**
 * ÜTS yanıtını yorumlar. ÜTS yanıtları sürümden sürüme küçük farklar gösterdiği için
 * sağlam ayrıştırılır:
 *  • Mesajlar: MSJ / msj / SNC (dizi) içindeki TIP/tip + MET/mesaj/KOD alanları.
 *  • Bildirim ID: BID / SNC / bildirimId / id alanı; yoksa yanıttaki ilk UUID.
 *  • Başarı: HTTP 2xx ve TIP = HATA olan mesaj yok.
 */
function uts_yanit_coz(int $durum, string $govde, string $agHatasi = ''): array
{
    $kisa = mb_substr($govde, 0, 4000);
    if ($durum === 0) {
        // Bağlantı kurulamadıysa istek ÜTS'ye hiç ulaşmamıştır (güvenle tekrar denenir). Zaman aşımı gibi
        // durumlarda ise ÜTS isteği işlemiş olabilir: 'belirsiz' işaretlenir.
        $kurulamadi = (bool) preg_match('/resolve|Failed to connect|Connection refused|Connection timed out after|Could not connect|No route|SSL connect/i', $agHatasi);
        return ['ok' => false, 'id' => null, 'mesaj' => 'ÜTS\'ye bağlanılamadı' . ($agHatasi !== '' ? ': ' . mb_substr($agHatasi, 0, 150) : '.'), 'tur' => 'gecici', 'belirsiz' => !$kurulamadi, 'yanit' => $kisa, 'veri' => null];
    }
    $veri = json_decode($govde, true);
    $mesajlar = [];
    $hataVar = false;
    uts_mesajlari_topla($veri, $mesajlar, $hataVar);
    $metin = implode(' · ', array_unique(array_filter($mesajlar)));
    $id = uts_bildirim_id_bul($veri);

    if ($durum === 401 || $durum === 403 || ($durum >= 400 && preg_match('/\btoken\b/iu', $metin))) {
        return ['ok' => false, 'id' => null, 'mesaj' => 'ÜTS yetki hatası: ' . ($metin !== '' ? mb_substr($metin, 0, 300) : 'HTTP ' . $durum) . ' — sistem token\'ını kontrol edin.', 'tur' => 'yetki', 'belirsiz' => false, 'yanit' => $kisa, 'veri' => $veri];
    }
    if ($durum >= 200 && $durum < 300 && !$hataVar) {
        return ['ok' => true, 'id' => $id, 'mesaj' => mb_substr($metin, 0, 500), 'tur' => 'basarili', 'belirsiz' => false, 'yanit' => $kisa, 'veri' => $veri];
    }
    if ($durum === 429 || $durum >= 500) {
        // 502/504 ağ geçidi zaman aşımı: ÜTS arkada işlemiş olabilir.
        return ['ok' => false, 'id' => null, 'mesaj' => 'ÜTS geçici hata (HTTP ' . $durum . ')' . ($metin !== '' ? ': ' . mb_substr($metin, 0, 300) : ''), 'tur' => 'gecici', 'belirsiz' => in_array($durum, [502, 504], true), 'yanit' => $kisa, 'veri' => $veri];
    }
    // 2xx + HATA mesajı ya da 4xx: aynı istek tekrar gönderilse de düzelmez → personel bakmalı.
    return ['ok' => false, 'id' => null, 'mesaj' => $metin !== '' ? mb_substr($metin, 0, 500) : 'ÜTS isteği reddetti (HTTP ' . $durum . ').', 'tur' => 'kalici', 'belirsiz' => false, 'yanit' => $kisa, 'veri' => $veri];
}

/** @internal */
function uts_mesajlari_topla(mixed $d, array &$mesajlar, bool &$hataVar, int $derinlik = 0): void
{
    if (!is_array($d) || $derinlik > 6) {
        return;
    }
    $tip = null;
    foreach (['TIP', 'tip', 'type', 'MTP'] as $k) {
        if (isset($d[$k]) && is_string($d[$k])) {
            $tip = strtoupper($d[$k]);
            break;
        }
    }
    $met = null;
    foreach (['MET', 'met', 'mesaj', 'message', 'ACK'] as $k) {
        if (isset($d[$k]) && is_string($d[$k]) && $d[$k] !== '') {
            $met = $d[$k];
            break;
        }
    }
    if ($tip !== null || $met !== null) {
        $kod = isset($d['KOD']) && is_scalar($d['KOD']) ? (string) $d['KOD'] : (isset($d['kod']) && is_scalar($d['kod']) ? (string) $d['kod'] : '');
        if ($met !== null) {
            $mesajlar[] = ($kod !== '' ? $kod . ': ' : '') . $met;
        }
        if ($tip !== null && in_array($tip, ['HATA', 'ERROR', 'E'], true)) {
            $hataVar = true;
        }
    }
    foreach ($d as $v) {
        if (is_array($v)) {
            uts_mesajlari_topla($v, $mesajlar, $hataVar, $derinlik + 1);
        }
    }
}

/** @internal Yanıttan bildirim ID'si (UUID). */
function uts_bildirim_id_bul(mixed $d, int $derinlik = 0): ?string
{
    if (!is_array($d) || $derinlik > 6) {
        return null;
    }
    foreach (['BID', 'SNC', 'bildirimId', 'BILDIRIM_ID', 'id', 'ID'] as $k) {
        if (isset($d[$k]) && is_string($d[$k]) && uts_uuid_mi($d[$k])) {
            return strtolower($d[$k]);
        }
    }
    foreach ($d as $v) {
        if (is_string($v) && uts_uuid_mi($v)) {
            return strtolower($v);
        }
    }
    foreach ($d as $v) {
        if (is_array($v)) {
            $b = uts_bildirim_id_bul($v, $derinlik + 1);
            if ($b !== null) {
                return $b;
            }
        }
    }
    return null;
}

/* ---------------- Karekod → tekil ürün ---------------- */

/**
 * Okutulan ÜTS karekodunu (GS1) çözer.
 * Dönüş: ['uno','lno','sno','skt','urt'] — uno boşsa karekod değildir.
 *
 * Klavye gibi çalışan okuyucular GS ayırıcısını çoğu zaman göndermez; o zaman
 * değişken uzunluklu seri/parti alanı sonraki "17YYAAGG" (SKT) ya da "11YYAAGG"
 * alanını yutar. Sonda geçerli tarihli bir 17/11 bloğu varsa ayrılır.
 */
function uts_karekod_coz(string $ham): array
{
    $kod = trim(preg_replace('/[\x00-\x1C\x1E-\x1F\x7F]/', '', $ham) ?? '');
    $bos = ['uno' => '', 'lno' => '', 'sno' => '', 'skt' => '', 'urt' => ''];
    if ($kod === '' || !function_exists('gs1_coz')) {
        return $bos;
    }
    // Yalnızca 13/14 haneli barkod (GTIN) okutulmuşsa: seri yok, ÜTS tekil ürünü değil.
    $g = gs1_coz($kod);
    if ($g['gtin'] === '') {
        return $bos;
    }
    $gsVar = str_contains($kod, "\x1D");
    foreach (['seri', 'parti'] as $alan) {
        if ($gsVar || $g[$alan] === '') {
            continue;
        }
        // Sondan tarih blokları: …17YYAAGG, …11YYAAGG (en çok iki kez)
        for ($i = 0; $i < 2; $i++) {
            if (preg_match('/^(.+?)(1[17])(\d{6})$/', $g[$alan], $m) && gs1_tarih($m[3]) !== '') {
                $g[$alan] = $m[1];
                if ($m[2] === '17' && $g['skt'] === '') {
                    $g['skt'] = gs1_tarih($m[3]);
                } elseif ($m[2] === '11' && $g['urt'] === '') {
                    $g['urt'] = gs1_tarih($m[3]);
                }
                continue;
            }
            break;
        }
        // Seri alanı ayırıcısız "…10PARTI" içeriyorsa ayırmayız: belirsiz (seri no'nun kendisi "10" içerebilir).
    }
    return [
        'uno' => $g['gtin'],
        'lno' => mb_substr(trim($g['parti']), 0, 40),
        'sno' => mb_substr(trim($g['seri']), 0, 40),
        'skt' => $g['skt'],
        'urt' => $g['urt'],
    ];
}

/**
 * Tekil ürün anahtarı. Seri takipli: "S|UNO|SNO" (her ürün tek satır).
 * Lot takipli ürünlerde aynı lot birden çok satırda olabilir (her sevkiyat ve her sipariş parçası
 * ayrı satır); onların anahtarı uts_lot_anahtari() ile üretilir, bu fonksiyon yalnızca taban değeri verir.
 */
function uts_anahtar(string $uno, string $lno, string $sno): string
{
    return $sno !== '' ? 'S|' . $uno . '|' . $sno : 'L|' . $uno . '|' . $lno;
}

function uts_seri_mi(array $u): bool
{
    return trim((string) ($u['sno'] ?? '')) !== '';
}

/** Lot satırı anahtarı: "L|UNO|LNO|<ek>" (ek: sevkiyat VBI'si, okutma ya da sipariş parçası). */
function uts_lot_anahtari(string $uno, string $lno, string $ek): string
{
    return mb_substr('L|' . $uno . '|' . $lno . '|' . $ek, 0, 120);
}

/** Aynı lotun stokta ve siparişe ayrılmamış satırı (adedi en büyük olan). */
function uts_lot_serbest(string $uno, string $lno, int $haricId = 0): ?array
{
    return row(
        "SELECT * FROM uts_urunler WHERE uno = ? AND COALESCE(lno, '') = ? AND COALESCE(sno, '') = '' AND durum = 'stokta' AND order_id IS NULL AND id <> ? ORDER BY adet DESC, id LIMIT 1",
        [$uno, $lno, $haricId]
    );
}

/**
 * Okutulan karekodun kaydı. Seri: tek kayıt. Lot: önce satılabilir (serbest) satır; yoksa
 * hata mesajı için lotun en son kaydı. Kabul bekleyen ("gelen") satırlar dikkate alınmaz.
 */
function uts_urun_karekodla(array $k): ?array
{
    if (($k['sno'] ?? '') !== '') {
        return uts_urun_anahtarla(uts_anahtar($k['uno'], $k['lno'], $k['sno']));
    }
    return uts_lot_serbest($k['uno'], $k['lno'])
        ?? row(
            "SELECT * FROM uts_urunler WHERE uno = ? AND COALESCE(lno, '') = ? AND COALESCE(sno, '') = '' AND durum <> 'gelen' ORDER BY (durum = 'stokta') DESC, id DESC LIMIT 1",
            [$k['uno'], $k['lno']]
        );
}

/** Arşivlenmiş (aynı seri sonradan yeniden mal kabul edilmiş) eski kayıt mı? Arşiv kaydı stoğa geri döndürülmez. */
function uts_arsivde_mi(array $u): bool
{
    return str_contains((string) $u['anahtar'], '|arsiv');
}

/** Çıkış yapmış seri kaydı, aynı ürün yeniden kabul edildiğinde arşiv anahtarına taşınır (geçmiş bildirimleri korunur). */
function uts_seri_arsivle(array $u): void
{
    q('UPDATE uts_urunler SET anahtar = ?, updated_at = ? WHERE id = ?', [mb_substr($u['anahtar'] . '|arsiv' . (int) $u['id'], 0, 120), uts_simdi(), (int) $u['id']]);
}

function uts_urun(int $id): ?array
{
    return row('SELECT * FROM uts_urunler WHERE id = ?', [$id]);
}

function uts_urun_anahtarla(string $anahtar): ?array
{
    return row('SELECT * FROM uts_urunler WHERE anahtar = ?', [$anahtar]);
}

/** Ekranda: "Ray-Ban RB5154 · seri 12345" */
function uts_urun_etiketi(array $u): string
{
    $ad = trim((string) ($u['marka_model'] ?? ''));
    if ($ad === '' && !empty($u['cerceve_ad'])) {
        $ad = (string) $u['cerceve_ad'];
    }
    $kimlik = uts_seri_mi($u) ? 'seri ' . $u['sno'] : ('lot ' . ($u['lno'] ?: '—') . ' · ' . (int) $u['adet'] . ' adet');
    return ($ad !== '' ? $ad : 'ÜTS ' . $u['uno']) . ' · ' . $kimlik;
}

/** GTIN'e göre çerçeve kartı (barkod alanı GTIN, başında 0 olmadan ya da 13 hane). */
function uts_cerceve_bul(string $uno): ?array
{
    if ($uno === '') {
        return null;
    }
    $adaylar = array_values(array_unique([$uno, ltrim($uno, '0'), substr($uno, 1)]));
    return row('SELECT * FROM frame_items WHERE barcode IN (' . in_placeholders($adaylar) . ') ORDER BY is_active DESC, id LIMIT 1', $adaylar);
}

/**
 * Ürünü çerçeve kartına bağlar (GTIN = kart barkodu). Kart yoksa ve $kartOlustur ise
 * ÜTS'deki marka/model adıyla yeni kart açar (fiyatı sonra çerçeve stoğundan girilir).
 * $stokArtir: kartın adedini ürün adedi kadar artırır (mal kabul).
 * Dönüş: bağlanan frame_item_id ya da null.
 */
function uts_cerceve_bagla(int $urunId, bool $stokArtir, bool $kartOlustur): ?int
{
    $u = uts_urun($urunId);
    if (!$u || !in_array($u['kategori'], ['cerceve', 'gunes'], true)) {
        return null;
    }
    $f = $u['frame_item_id'] ? row('SELECT * FROM frame_items WHERE id = ?', [(int) $u['frame_item_id']]) : uts_cerceve_bul((string) $u['uno']);
    if (!$f && $kartOlustur) {
        $mm = trim((string) $u['marka_model']);
        $parca = preg_split('/\s+/u', $mm, 2) ?: [];
        $fid = insert('frame_items', [
            'brand'      => mb_substr($parca[0] ?? '', 0, 80) ?: 'ÜTS ürünü',
            'model'      => mb_substr($parca[1] ?? '', 0, 80) ?: null,
            'barcode'    => (string) $u['uno'],
            'qty'        => 0,
            'min_qty'    => 0,
            'note'       => 'ÜTS mal kabulünden oluşturuldu',
            'is_active'  => 1,
            'created_by' => (int) (current_user()['id'] ?? 0) ?: null,
            'created_at' => uts_simdi(),
            'updated_at' => uts_simdi(),
        ]);
        $f = row('SELECT * FROM frame_items WHERE id = ?', [$fid]);
    }
    if (!$f) {
        return null;
    }
    q('UPDATE uts_urunler SET frame_item_id = ?, updated_at = ? WHERE id = ?', [(int) $f['id'], uts_simdi(), $urunId]);
    if ($stokArtir) {
        frame_move((int) $f['id'], max(1, (int) $u['adet']), 'giris', null, 'ÜTS mal kabul · ' . uts_urun_etiketi($u));
    }
    return (int) $f['id'];
}

/**
 * Karekodu okutulan ürünü stoğa kaydeder (ÜTS'de zaten kabul edilmiş ürünler için; bildirim YAPMAZ).
 * Lot takipli üründe aynı lot varsa adedi artırır.
 * Dönüş: ürün satırı.
 */
function uts_stoga_okut(string $kod, string $kategori, int $adet = 1, bool $cerceveStokArtir = false): array
{
    $k = uts_karekod_coz($kod);
    if ($k['uno'] === '') {
        throw new DomainException('Okutulan kod bir ÜTS karekodu değil (GTIN bulunamadı).');
    }
    if ($k['sno'] === '' && $k['lno'] === '') {
        throw new DomainException('Karekodda seri ya da parti (lot) numarası yok; ÜTS tekil ürünü okunamadı. Ürünün üzerindeki karekodu (düz barkodu değil) okutun.');
    }
    $kategori = isset(uts_kategoriler()[$kategori]) ? $kategori : 'diger';
    $seri = $k['sno'] !== '';
    $adet = $seri ? 1 : max(1, min(999, $adet));
    if ($seri) {
        $anahtar = uts_anahtar($k['uno'], $k['lno'], $k['sno']);
        $var = uts_urun_anahtarla($anahtar);
        if ($var && $var['durum'] === 'iade') {
            uts_seri_arsivle($var);   // tedarikçiye iade edilmiş ürün geri geldi
            $var = null;
        }
        if ($var) {
            $d = uts_urun_durumlari()[$var['durum']][0] ?? $var['durum'];
            throw new DomainException('Bu ürün zaten kayıtlı (' . $d . ').');
        }
    } else {
        $var = uts_lot_serbest($k['uno'], $k['lno']);
        if ($var) {
            q("UPDATE uts_urunler SET adet = adet + ?, updated_at = ? WHERE id = ? AND durum = 'stokta' AND order_id IS NULL", [$adet, uts_simdi(), (int) $var['id']]);
            if ($cerceveStokArtir && $var['frame_item_id']) {
                frame_move((int) $var['frame_item_id'], $adet, 'giris', null, 'ÜTS karekod okutma');
            }
            return uts_urun((int) $var['id']);
        }
        $anahtar = uts_lot_anahtari($k['uno'], $k['lno'], 'k' . bin2hex(random_bytes(4)));
    }
    $f = in_array($kategori, ['cerceve', 'gunes'], true) ? uts_cerceve_bul($k['uno']) : null;
    $id = insert('uts_urunler', [
        'anahtar'       => $anahtar,
        'uno'           => $k['uno'],
        'lno'           => $k['lno'] ?: null,
        'sno'           => $k['sno'] ?: null,
        'adet'          => $adet,
        'kaynak'        => 'okutma',
        'skt'           => $k['skt'] ?: null,
        'urt'           => $k['urt'] ?: null,
        'kategori'      => $kategori,
        'marka_model'   => $f ? mb_substr(frame_item_label($f), 0, 200) : null,
        'durum'         => 'stokta',
        'frame_item_id' => $f ? (int) $f['id'] : null,
        'alma_at'       => uts_simdi(),
        'created_by'    => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at'    => uts_simdi(),
        'updated_at'    => uts_simdi(),
    ]);
    if ($f && $cerceveStokArtir) {
        frame_move((int) $f['id'], $adet, 'giris', null, 'ÜTS karekod okutma');
    }
    return uts_urun($id);
}

/* ---------------- Bildirim kuyruğu ---------------- */

/** Ürün kimlik alanları (UNO + LNO/SNO + lot ise ADT). */
function uts_govde_urun(array $u, ?int $adet = null): array
{
    $g = ['UNO' => (string) $u['uno']];
    if ((string) ($u['lno'] ?? '') !== '') {
        $g['LNO'] = (string) $u['lno'];
    }
    if (uts_seri_mi($u)) {
        $g['SNO'] = (string) $u['sno'];
    } else {
        $g['ADT'] = max(1, (int) ($adet ?? $u['adet']));
    }
    return $g;
}

/**
 * Kuyruğa bildirim ekler. Aynı $tekil ile ikinci kez çağrılırsa yeni kayıt açmaz (mevcut ID döner).
 * $onayGerekir: 'onayli' gönderim modunda satış/iade bildirimleri personel onayını bekler.
 */
function uts_bildirim_ekle(string $tur, array $govde, ?int $urunId, ?int $siparisId, int $adet, string $tekil, ?int $ilgiliId = null, bool $onayGerekir = false): int
{
    if (!isset(uts_turler()[$tur])) {
        throw new DomainException('Bilinmeyen ÜTS bildirim türü.');
    }
    $var = scalar('SELECT id FROM uts_bildirimler WHERE tekil = ?', [$tekil]);
    if ($var) {
        return (int) $var;
    }
    try {
        return insert('uts_bildirimler', [
            'tur'        => $tur,
            'urun_id'    => $urunId,
            'order_id'   => $siparisId,
            'adet'       => max(1, $adet),
            'govde'      => (string) json_encode($govde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'durum'      => $onayGerekir && uts_gonderim_modu() === 'onayli' ? 'onay_bekliyor' : 'bekliyor',
            'ortam'      => uts_ortam(),
            'ilgili_id'  => $ilgiliId,
            'tekil'      => mb_substr($tekil, 0, 80),
            'planlanan'  => uts_simdi(),
            'created_by' => (int) (current_user()['id'] ?? 0) ?: null,
            'created_at' => uts_simdi(),
        ]);
    } catch (PDOException $e) {
        // Eşzamanlı ikinci istek aynı tekil anahtarla eklediyse onu döndür.
        $var = scalar('SELECT id FROM uts_bildirimler WHERE tekil = ?', [$tekil]);
        if ($var) {
            return (int) $var;
        }
        throw $e;
    }
}

/** Başarılı (ya da ÜTS'de elle yapılmış) bildirimin stoktaki etkisi. */
function uts_bildirim_basari_etkisi(array $b): void
{
    if ($b['tur'] === 'alma' && $b['urun_id']) {
        q("UPDATE uts_urunler SET durum = 'stokta', alma_at = ?, updated_at = ? WHERE id = ? AND durum = 'alma_bekliyor'", [uts_simdi(), uts_simdi(), (int) $b['urun_id']]);
    }
}

/**
 * Sıradaki bildirimleri ÜTS'ye gönderir. En çok $limit kayıt.
 * Dönüş: ['gonderilen' => int, 'hata' => int, 'durdu' => string ('' ya da yetki hatası metni)]
 */
function uts_kuyrugu_isle(int $limit = 20, int $sureSn = 40): array
{
    $ozet = ['gonderilen' => 0, 'hata' => 0, 'durdu' => ''];
    $bitis = microtime(true) + max(5, $sureSn);
    // Çöken süreçten "gönderiliyor"da kalanlar: ÜTS'ye ulaşmış olabilir → otomatik yeniden GÖNDERİLMEZ, personel bakar.
    q(
        "UPDATE uts_bildirimler SET durum = 'hata', deneme = 99, son_hata = ? WHERE durum = 'gonderiliyor' AND planlanan < ?",
        ['Gönderim yarıda kaldı; ÜTS\'ye ulaşmış olabilir. ÜTS\'den kontrol edin: oluşmuşsa "ÜTS\'de elle yapıldı", oluşmamışsa "Tekrar dene".', uts_simdi(-900)]
    );
    if (!uts_hazir_mi()) {
        return $ozet;
    }
    $liste = rows(
        "SELECT * FROM uts_bildirimler WHERE durum IN ('bekliyor','hata') AND deneme < 6 AND planlanan <= ? ORDER BY id LIMIT " . max(1, min(200, $limit)),
        [uts_simdi()]
    );
    foreach ($liste as $b) {
        if (microtime(true) > $bitis) {
            break;   // web isteğinde zaman aşımına düşmemek için; kalanlar sonraki turda
        }
        // Kilit: satırı yalnızca hâlâ gönderilebilir durumdaysa al (eşzamanlı ikinci süreç aynı satırı gönderemez).
        $kilit = q(
            "UPDATE uts_bildirimler SET durum = 'gonderiliyor', planlanan = ? WHERE id = ? AND durum IN ('bekliyor','hata') AND deneme < 6 AND planlanan <= ?",
            [uts_simdi(), (int) $b['id'], uts_simdi()]
        );
        if ($kilit->rowCount() === 0) {
            continue;
        }
        $govde = json_decode((string) $b['govde'], true) ?: [];

        // Tüketiciden iade: önce ilgili satış bildirimi ÜTS'ye iletilmiş olmalı (TID).
        if ($b['tur'] === 'tuketiciden_iade') {
            $ilgili = $b['ilgili_id'] ? row('SELECT * FROM uts_bildirimler WHERE id = ?', [(int) $b['ilgili_id']]) : null;
            if (!$ilgili || $ilgili['durum'] === 'iptal') {
                q("UPDATE uts_bildirimler SET durum = 'iptal', son_hata = ? WHERE id = ?", ['Satış bildirimi hiç gönderilmediği için iade gerekmedi.', (int) $b['id']]);
                continue;
            }
            if ($ilgili['durum'] === 'elle' && !$ilgili['uts_id']) {
                q("UPDATE uts_bildirimler SET durum = 'hata', deneme = 99, son_hata = ? WHERE id = ?", ['Satış bildirimi ÜTS\'de elle yapılmıştı; ÜTS bildirim numarası bilinmediği için iadeyi de ÜTS\'de elle yapın.', (int) $b['id']]);
                $ozet['hata']++;
                continue;
            }
            if ($ilgili['durum'] === 'hata' && (int) $ilgili['deneme'] >= 6) {
                q("UPDATE uts_bildirimler SET durum = 'hata', deneme = 99, son_hata = ? WHERE id = ?", ['Bağlı satış bildirimi (#' . (int) $ilgili['id'] . ') hatalı. Önce onu çözün, sonra bu iadeyi "Tekrar dene" ile gönderin.', (int) $b['id']]);
                $ozet['hata']++;
                continue;
            }
            if ($ilgili['durum'] !== 'gonderildi' || !$ilgili['uts_id']) {
                q("UPDATE uts_bildirimler SET durum = 'bekliyor', planlanan = ? WHERE id = ?", [uts_simdi(600), (int) $b['id']]);
                continue;
            }
            $govde = ['TID' => (string) $ilgili['uts_id']] + $govde;
        }

        $ortam = uts_ortam();
        $s = uts_istek((string) $b['tur'], $govde, $ortam);
        if ($s['ok']) {
            q(
                "UPDATE uts_bildirimler SET durum = 'gonderildi', ortam = ?, uts_id = ?, gonderilme = ?, son_hata = ?, yanit = ?, govde = ?, deneme = deneme + 1 WHERE id = ?",
                [$ortam, $s['id'], uts_simdi(), $s['id'] ? null : 'ÜTS yanıtında bildirim numarası bulunamadı.', $s['yanit'], (string) json_encode($govde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $b['id']]
            );
            uts_bildirim_basari_etkisi($b);
            $ozet['gonderilen']++;
            if (setting('uts_yetki_hatasi', '') !== '') {
                setting_set('uts_yetki_hatasi', '');
            }
            continue;
        }
        $ozet['hata']++;
        if ($s['tur'] === 'yetki') {
            // Token sorunu tüm kuyruğu etkiler: bu turu durdur, 30 dk sonra yeniden dene (deneme sayılmaz).
            q("UPDATE uts_bildirimler SET durum = 'hata', ortam = ?, son_hata = ?, yanit = ?, planlanan = ? WHERE id = ?", [$ortam, mb_substr($s['mesaj'], 0, 500), $s['yanit'], uts_simdi(1800), (int) $b['id']]);
            setting_set('uts_yetki_hatasi', uts_simdi() . ' · ' . mb_substr($s['mesaj'], 0, 300));
            $ozet['durdu'] = $s['mesaj'];
            break;
        }
        $deneme = (int) $b['deneme'] + 1;
        $kalici = $s['tur'] === 'kalici';
        $mesaj = $s['mesaj'];
        // ÜTS isteği almış olabilir (zaman aşımı): adetli (lot) bildirim tekrar gönderilirse iki kez düşebilir → personel bakar.
        // Seri takipli üründe tekrar güvenlidir: ÜTS aynı ürünü ikinci kez kabul etmez.
        if (!empty($s['belirsiz']) && isset($govde['ADT'])) {
            $kalici = true;
            $mesaj .= ' — ÜTS isteği almış olabilir. ÜTS\'den kontrol edin: oluşmuşsa "ÜTS\'de elle yapıldı", oluşmamışsa "Tekrar dene".';
        }
        $bekleDk = $kalici ? 0 : min(360, 5 * (2 ** $deneme));
        q(
            "UPDATE uts_bildirimler SET durum = 'hata', ortam = ?, son_hata = ?, yanit = ?, deneme = ?, planlanan = ? WHERE id = ?",
            [$ortam, mb_substr($mesaj, 0, 500), $s['yanit'], $kalici ? 99 : $deneme, uts_simdi($bekleDk * 60), (int) $b['id']]
        );
    }
    return $ozet;
}

/** Hatalı bildirimi yeniden sıraya alır. */
function uts_bildirim_tekrar(int $id): bool
{
    return q("UPDATE uts_bildirimler SET durum = 'bekliyor', deneme = 0, planlanan = ? WHERE id = ? AND durum = 'hata'", [uts_simdi(), $id])->rowCount() > 0;
}

/** Onay bekleyenleri sıraya alır. Dönüş: sıraya alınan sayı. */
function uts_bildirimleri_onayla(array $idler): int
{
    $n = 0;
    foreach (array_unique(array_map('intval', $idler)) as $id) {
        $n += q("UPDATE uts_bildirimler SET durum = 'bekliyor', planlanan = ? WHERE id = ? AND durum = 'onay_bekliyor'", [uts_simdi(), $id])->rowCount();
    }
    return $n;
}

/** "ÜTS'de elle yapıldı": bildirim ÜTS web ekranından yapılmışsa. Stok etkisi başarıyla aynıdır. */
function uts_bildirim_elle_tamam(int $id): bool
{
    $b = row('SELECT * FROM uts_bildirimler WHERE id = ?', [$id]);
    if (!$b || !in_array($b['durum'], ['onay_bekliyor', 'bekliyor', 'hata'], true)) {
        return false;
    }
    $n = q("UPDATE uts_bildirimler SET durum = 'elle', gonderilme = ?, son_hata = NULL WHERE id = ? AND durum IN ('onay_bekliyor','bekliyor','hata')", [uts_simdi(), $id])->rowCount();
    if ($n > 0) {
        uts_bildirim_basari_etkisi($b);
    }
    return $n > 0;
}

/**
 * Gönderilmemiş bildirimi iptal eder.
 *  • Satış / iade: yalnızca bildirim düşer (ürünün fiziksel durumu değişmez).
 *  • İmha / tedarikçiye iade: ürün stoğa, çerçeve adedi geri döner.
 *  • Alma iptal edilemez (ürün ÜTS'de kabul edilmeden satılamaz): "ÜTS'de elle yapıldı" kullanılır.
 */
function uts_bildirim_iptal(int $id): bool
{
    $b = row('SELECT * FROM uts_bildirimler WHERE id = ?', [$id]);
    if (!$b || $b['tur'] === 'alma' || !in_array($b['durum'], ['onay_bekliyor', 'bekliyor', 'hata'], true)) {
        return false;
    }
    return (bool) transaction(static function () use ($b): bool {
        $n = q("UPDATE uts_bildirimler SET durum = 'iptal' WHERE id = ? AND durum IN ('onay_bekliyor','bekliyor','hata')", [(int) $b['id']])->rowCount();
        if ($n === 0) {
            return false;
        }
        if (in_array($b['tur'], ['imha', 'verme'], true) && $b['urun_id']) {
            $u = uts_urun((int) $b['urun_id']);
            if ($u && in_array($u['durum'], ['imha', 'iade'], true) && !uts_arsivde_mi($u)) {
                q("UPDATE uts_urunler SET durum = 'stokta', cikis_at = NULL, updated_at = ? WHERE id = ?", [uts_simdi(), (int) $u['id']]);
                if ($u['frame_item_id']) {
                    frame_move((int) $u['frame_item_id'], max(1, (int) $u['adet']), 'sayim', null, 'ÜTS bildirimi iptal edildi, ürün stoğa döndü');
                }
            }
        }
        return true;
    });
}

/* ---------------- Sipariş (satış) ---------------- */

/** SGK'lı sipariş mi? (e-reçete no ya da SGK katkı payı var) — bu siparişin ÜTS düşümünü Medula yapar. */
function uts_siparis_sgk_mi(array $o): bool
{
    return trim((string) ($o['sgk_erecete'] ?? '')) !== '' || (float) ($o['sgk_amount'] ?? 0) > 0.009;
}

function uts_siparis_urunleri(int $siparisId): array
{
    return rows(
        'SELECT u.*, f.brand AS f_brand, f.model AS f_model, f.color AS f_color, f.size AS f_size, f.price AS f_price
           FROM uts_urunler u LEFT JOIN frame_items f ON f.id = u.frame_item_id
          WHERE u.order_id = ? ORDER BY u.id',
        [$siparisId]
    );
}

/**
 * Siparişte karekod okutma: ürünü siparişe bağlar (rezerve eder).
 *  • Ürün stok kaydında yoksa önce stoğa kaydedilir (ÜTS'de kabul edilmiş sayılır).
 *  • Çerçeve kartına bağlıysa ve siparişte henüz stok çerçevesi yoksa, sipariş çerçevesi
 *    olarak işlenir (çerçeve adedi 1 düşer) ve fiyatı gösterilir.
 * Dönüş: ['urun' => array, 'mesajlar' => string[], 'fiyat' => ?float]
 */
function uts_siparise_okut(int $siparisId, string $kod, string $kategori = 'cerceve', int $adet = 1): array
{
    $o = row('SELECT * FROM orders WHERE id = ?', [$siparisId]);
    if (!$o) {
        throw new DomainException('Sipariş bulunamadı.');
    }
    if (in_array($o['order_stage'], ['teslim_edildi', 'iptal'], true)) {
        throw new DomainException('Teslim edilmiş ya da iptal edilmiş siparişe ürün eklenemez.');
    }
    $k = uts_karekod_coz($kod);
    if ($k['uno'] === '') {
        throw new DomainException('Okutulan kod bir ÜTS karekodu değil.');
    }
    $mesajlar = [];
    $u = uts_urun_karekodla($k);
    if (!$u) {
        if ($k['sno'] === '' && $k['lno'] === '') {
            throw new DomainException('Karekodda seri / lot numarası okunamadı; ürünün ÜTS karekodunu okutun.');
        }
        $u = uts_stoga_okut($kod, $kategori, uts_seri_mi($k) ? 1 : max(1, $adet));
        $mesajlar[] = 'Ürün ÜTS stok kaydınızda yoktu, kaydedildi. ÜTS\'de alma bildiriminin yapılmış olduğundan emin olun.';
    }
    if ($u['skt'] && $u['skt'] < date('Y-m-d')) {
        throw new DomainException('Bu ürünün son kullanma tarihi geçmiş (' . date_tr((string) $u['skt']) . '); satılamaz. ÜTS › Stok ekranından imha bildirimi yapın.');
    }
    if ((int) ($u['order_id'] ?? 0) === $siparisId) {
        throw new DomainException('Bu ürün zaten bu siparişte.');
    }
    if ($u['durum'] !== 'stokta' || $u['order_id']) {
        $d = $u['order_id'] ? 'başka bir siparişe (' . order_no((int) $u['order_id']) . ') ayrılmış' : (uts_urun_durumlari()[$u['durum']][0] ?? $u['durum']);
        throw new DomainException('Bu ürün satılamaz: ' . $d . '.');
    }

    return transaction(static function () use ($u, $o, $siparisId, $adet, $mesajlar): array {
        $urunId = (int) $u['id'];
        // Lot takipli üründen bir kısmı satılıyorsa satır bölünür: satılan kısım ayrı satır olur.
        if (!uts_seri_mi($u)) {
            $adet = max(1, $adet);
            if ($adet > (int) $u['adet']) {
                throw new DomainException('Stokta bu lottan ' . (int) $u['adet'] . ' adet var.');
            }
            if ($adet < (int) $u['adet']) {
                $dustu = q("UPDATE uts_urunler SET adet = adet - ?, updated_at = ? WHERE id = ? AND adet > ? AND order_id IS NULL AND durum = 'stokta'", [$adet, uts_simdi(), $urunId, $adet])->rowCount();
                if ($dustu === 0) {
                    throw new DomainException('Lot adedi az önce değişti; yeniden okutun.');
                }
                $yeni = $u;
                unset($yeni['id']);
                $yeni['anahtar'] = uts_lot_anahtari((string) $u['uno'], (string) $u['lno'], 'o' . $siparisId . '-' . bin2hex(random_bytes(4)));
                $yeni['adet'] = $adet;
                $yeni['created_at'] = $yeni['updated_at'] = uts_simdi();
                $urunId = insert('uts_urunler', $yeni);
            }
        }
        $ayrildi = q("UPDATE uts_urunler SET order_id = ?, updated_at = ? WHERE id = ? AND order_id IS NULL AND durum = 'stokta'", [$siparisId, uts_simdi(), $urunId])->rowCount();
        if ($ayrildi === 0) {
            throw new DomainException('Bu ürün az önce başka bir siparişe ayrıldı.');
        }
        $fiyat = null;
        $f = $u['frame_item_id'] ? row('SELECT * FROM frame_items WHERE id = ?', [(int) $u['frame_item_id']]) : null;
        if ($f) {
            $fiyat = $f['price'] !== null ? (float) $f['price'] : null;
            if (!$o['frame_item_id']) {
                order_set_frame_item($siparisId, (int) $f['id'], null);
                if (trim((string) $o['frame_info']) === '') {
                    q('UPDATE orders SET frame_info = ? WHERE id = ?', [mb_substr(frame_item_label($f), 0, 255), $siparisId]);
                }
                $mesajlar[] = 'Siparişin çerçevesi olarak işlendi: ' . frame_item_label($f) . ($fiyat !== null ? ' — ' . money($fiyat) : '') . '.';
            } elseif ((int) $o['frame_item_id'] !== (int) $f['id']) {
                $mesajlar[] = 'Siparişte başka bir stok çerçevesi seçili; çerçeve stoğu değiştirilmedi. Gerekirse sipariş bilgilerinden düzeltin.';
            }
        }
        return ['urun' => uts_urun($urunId), 'mesajlar' => $mesajlar, 'fiyat' => $fiyat];
    });
}

/** Ürünü siparişten çıkarır (teslimden önce). Çerçeve olarak işlendiyse çerçeve stoğa döner. */
function uts_siparisten_cikar(int $siparisId, int $urunId): void
{
    $o = row('SELECT * FROM orders WHERE id = ?', [$siparisId]);
    $u = uts_urun($urunId);
    if (!$o || !$u || (int) $u['order_id'] !== $siparisId) {
        throw new DomainException('Ürün bu siparişte değil.');
    }
    if ($u['durum'] !== 'stokta') {
        throw new DomainException('Teslim edilmiş siparişin ürünü çıkarılamaz; önce sipariş durumunu geri alın.');
    }
    transaction(static function () use ($o, $u, $siparisId): void {
        q('UPDATE uts_urunler SET order_id = NULL, updated_at = ? WHERE id = ?', [uts_simdi(), (int) $u['id']]);
        if ($u['frame_item_id'] && (int) $o['frame_item_id'] === (int) $u['frame_item_id']) {
            $baska = (int) scalar('SELECT COUNT(*) FROM uts_urunler WHERE order_id = ? AND frame_item_id = ?', [$siparisId, (int) $u['frame_item_id']]);
            if ($baska === 0) {
                order_set_frame_item($siparisId, null, (int) $u['frame_item_id']);
            }
        }
        uts_lot_birlestir((int) $u['id']);
    });
}

/** Bölünmüş lot satırı stoğa dönünce aynı lotun ana satırıyla birleşir. */
function uts_lot_birlestir(int $urunId): void
{
    $u = uts_urun($urunId);
    if (!$u || uts_seri_mi($u) || $u['durum'] !== 'stokta' || $u['order_id']) {
        return;
    }
    $diger = uts_lot_serbest((string) $u['uno'], (string) ($u['lno'] ?? ''), $urunId);
    if (!$diger) {
        return;
    }
    // Bildirim geçmişi olmayan satır silinir, adedi diğerine eklenir. İkisinin de geçmişi varsa ayrı kalırlar.
    $gecmis = static fn(int $id): bool => (bool) scalar('SELECT COUNT(*) FROM uts_bildirimler WHERE urun_id = ?', [$id]);
    [$kalan, $silinen] = !$gecmis($urunId) ? [$diger, $u] : (!$gecmis((int) $diger['id']) ? [$u, $diger] : [null, null]);
    if ($kalan === null) {
        return;
    }
    $n = q("DELETE FROM uts_urunler WHERE id = ? AND durum = 'stokta' AND order_id IS NULL", [(int) $silinen['id']])->rowCount();
    if ($n > 0) {
        q('UPDATE uts_urunler SET adet = adet + ?, updated_at = ? WHERE id = ?', [(int) $silinen['adet'], uts_simdi(), (int) $kalan['id']]);
    }
}

/** Sipariş iptal edildi / silindi: teslim edilmemiş (stoktaki) ürünler siparişten çözülür. */
function uts_siparis_urunlerini_coz(int $siparisId): void
{
    foreach (rows("SELECT id FROM uts_urunler WHERE order_id = ? AND durum = 'stokta'", [$siparisId]) as $r) {
        q("UPDATE uts_urunler SET order_id = NULL, updated_at = ? WHERE id = ? AND durum = 'stokta'", [uts_simdi(), (int) $r['id']]);
        uts_lot_birlestir((int) $r['id']);
    }
}

/**
 * Sipariş aşaması değişince çağrılır (order.php).
 *  • → teslim_edildi : SGK'lı ise "SGK · Medula" çıkışı (bildirim yok); değilse tüketiciye verme.
 *  • teslim_edildi → başka aşama / iptal : gönderilmemiş satış bildirimi iptal; gönderilmişse tüketiciden iade.
 *  • → iptal : ürünler siparişten çözülür, stoğa döner.
 * Dönüş: ekranda gösterilecek kısa mesajlar.
 */
function uts_siparis_asama_degisti(int $siparisId, string $eski, string $yeni): array
{
    if ($eski === $yeni) {
        return [];
    }
    $o = row('SELECT * FROM orders WHERE id = ?', [$siparisId]);
    if (!$o) {
        return [];
    }
    $mesaj = [];
    if ($yeni === 'teslim_edildi') {
        $mesaj = array_merge($mesaj, uts_siparis_teslim($o));
    } elseif ($eski === 'teslim_edildi') {
        $mesaj = array_merge($mesaj, uts_siparis_teslim_geri($o));
    }
    if ($yeni === 'iptal') {
        uts_siparis_urunlerini_coz($siparisId);
    }
    return $mesaj;
}

/**
 * Teslim edilmiş siparişe SONRADAN SGK bilgisi (e-reçete / SGK katkısı) girildi.
 * Ücretli satış bildirimi gönderilmemişse iptal edilir; gönderilmişse "tüketiciden iade alma"
 * sıraya girer (aksi hâlde Medula aynı ürünü ikinci kez düşmeye çalışır).
 */
function uts_siparis_sgk_guncellendi(int $siparisId): array
{
    $o = row('SELECT * FROM orders WHERE id = ?', [$siparisId]);
    if (!$o || !uts_siparis_sgk_mi($o)) {
        return [];
    }
    $mesaj = [];
    foreach (rows("SELECT * FROM uts_urunler WHERE order_id = ? AND durum = 'satildi'", [$siparisId]) as $u) {
        if (q("UPDATE uts_urunler SET durum = 'sgk', satis_turu = 'sgk', updated_at = ? WHERE id = ? AND durum = 'satildi'", [uts_simdi(), (int) $u['id']])->rowCount() === 0) {
            continue;
        }
        $tv = row("SELECT * FROM uts_bildirimler WHERE tur = 'tuketiciye_verme' AND urun_id = ? AND durum <> 'iptal' ORDER BY id DESC LIMIT 1", [(int) $u['id']]);
        if (!$tv) {
            continue;
        }
        if (in_array($tv['durum'], ['onay_bekliyor', 'bekliyor', 'hata'], true)) {
            q("UPDATE uts_bildirimler SET durum = 'iptal', son_hata = ? WHERE id = ? AND durum IN ('onay_bekliyor','bekliyor','hata')", ['Sipariş SGK\'lı oldu; ÜTS düşümünü Medula yapar.', (int) $tv['id']]);
            $mesaj[] = 'Sipariş SGK\'lı oldu: gönderilmemiş ücretli satış bildirimi iptal edildi; ÜTS düşümünü Medula yapar.';
        } elseif ($tv['durum'] === 'elle') {
            $mesaj[] = 'Bu ürün ÜTS\'de elle ücretli satış olarak bildirilmişti: Medula\'ya okutmadan önce ÜTS\'den tüketiciden iade alma yapın.';
        } else {
            $govde = uts_seri_mi($u) ? [] : ['ADT' => max(1, (int) $u['adet'])];
            uts_bildirim_ekle('tuketiciden_iade', $govde, (int) $u['id'], $siparisId, (int) $u['adet'], 'ti:b' . (int) $tv['id'], (int) $tv['id'], true);
            $mesaj[] = 'Sipariş SGK\'lı oldu ama ürün ÜTS\'ye ücretli satış olarak bildirilmişti: "tüketiciden iade alma" sıraya alındı. İade ÜTS\'ye iletilmeden karekod Medula\'da "stokta yok" hatası verebilir.';
        }
    }
    return array_values(array_unique($mesaj));
}

/** @internal */
function uts_siparis_teslim(array $o): array
{
    $sid = (int) $o['id'];
    $urunler = rows("SELECT * FROM uts_urunler WHERE order_id = ? AND durum = 'stokta'", [$sid]);
    if (!$urunler) {
        return [];
    }
    $sgk = uts_siparis_sgk_mi($o);
    $tarih = substr((string) ($o['delivered_at'] ?: uts_simdi()), 0, 10);
    $n = 0;
    foreach ($urunler as $u) {
        if ($sgk) {
            q("UPDATE uts_urunler SET durum = 'sgk', satis_turu = 'sgk', cikis_at = ?, updated_at = ? WHERE id = ? AND durum = 'stokta'", [uts_simdi(), uts_simdi(), (int) $u['id']]);
            continue;
        }
        // Koşullu güncelleme: aynı teslim iki istekle (çift tıklama) gelirse yalnızca biri bildirim üretir.
        $degisti = q("UPDATE uts_urunler SET durum = 'satildi', satis_turu = 'ucretli', cikis_at = ?, updated_at = ? WHERE id = ? AND durum = 'stokta'", [uts_simdi(), uts_simdi(), (int) $u['id']])->rowCount();
        if ($degisti !== 1) {
            continue;
        }
        $govde = uts_govde_urun($u) + ['GIT' => $tarih, 'BEN' => 'HAYIR'];
        if (setting('uts_ad_gonder', '0') === '1') {
            $govde['TUA'] = mb_substr(trim((string) $o['first_name']), 0, 60);
            $govde['TUS'] = mb_substr(trim((string) $o['last_name']), 0, 60);
        }
        // Aynı ürün aynı siparişte yeniden teslim edilirse (geri al → tekrar teslim) yeni bildirim gerekir: sayaçlı tekil anahtar.
        $kacinci = (int) scalar("SELECT COUNT(*) FROM uts_bildirimler WHERE tur = 'tuketiciye_verme' AND urun_id = ?", [(int) $u['id']]);
        uts_bildirim_ekle('tuketiciye_verme', $govde, (int) $u['id'], $sid, (int) $u['adet'], 'tv:u' . (int) $u['id'] . ':' . ($kacinci + 1), null, true);
        $n++;
    }
    if ($sgk) {
        return [count($urunler) . ' ÜTS ürünü SGK\'lı satış olarak çıkış yaptı; ÜTS düşümünü Medula yapar (OptiFlow bildirim göndermez).'];
    }
    return [$n . ' ürün için ÜTS tüketiciye verme bildirimi ' . (uts_gonderim_modu() === 'onayli' ? 'onayınızı bekliyor (ÜTS › Bildirimler).' : 'sıraya alındı.')];
}

/** @internal Teslim geri alındı / teslimden sonra iptal. */
function uts_siparis_teslim_geri(array $o): array
{
    $sid = (int) $o['id'];
    $mesaj = [];
    foreach (rows("SELECT * FROM uts_urunler WHERE order_id = ? AND durum IN ('satildi','sgk')", [$sid]) as $u) {
        if (uts_arsivde_mi($u)) {
            $mesaj[] = uts_urun_etiketi($u) . ' daha sonra yeniden mal kabul edilmiş; stok ve ÜTS kaydı otomatik değiştirilmedi, elle kontrol edin.';
            continue;
        }
        if ($u['durum'] === 'sgk') {
            q("UPDATE uts_urunler SET durum = 'stokta', satis_turu = NULL, cikis_at = NULL, updated_at = ? WHERE id = ? AND durum = 'sgk'", [uts_simdi(), (int) $u['id']]);
            $mesaj[] = 'SGK\'lı satış geri alındı: Medula\'da reçete iptal edildiyse ÜTS kaydı da geri döner; kontrol edin.';
            continue;
        }
        if (q("UPDATE uts_urunler SET durum = 'stokta', satis_turu = NULL, cikis_at = NULL, updated_at = ? WHERE id = ? AND durum = 'satildi'", [uts_simdi(), (int) $u['id']])->rowCount() === 0) {
            continue;
        }
        $tv = row("SELECT * FROM uts_bildirimler WHERE tur = 'tuketiciye_verme' AND urun_id = ? AND durum <> 'iptal' ORDER BY id DESC LIMIT 1", [(int) $u['id']]);
        if (!$tv) {
            continue;
        }
        if (in_array($tv['durum'], ['onay_bekliyor', 'bekliyor', 'hata'], true)) {
            q("UPDATE uts_bildirimler SET durum = 'iptal', son_hata = ? WHERE id = ? AND durum IN ('onay_bekliyor','bekliyor','hata')", ['Teslim geri alındı; bildirim gönderilmeden iptal edildi.', (int) $tv['id']]);
            $mesaj[] = 'Gönderilmemiş ÜTS satış bildirimi iptal edildi.';
            continue;
        }
        // Gönderilmiş (ya da gönderiliyor): tüketiciden iade alma. Kuyruk, satış bildirimi tamamlanınca gönderir.
        $govde = uts_seri_mi($u) ? [] : ['ADT' => max(1, (int) $u['adet'])];
        uts_bildirim_ekle('tuketiciden_iade', $govde, (int) $u['id'], $sid, (int) $u['adet'], 'ti:b' . (int) $tv['id'], (int) $tv['id'], true);
        $mesaj[] = 'ÜTS\'ye iletilmiş satış için "tüketiciden iade alma" bildirimi sıraya alındı.';
    }
    return array_values(array_unique($mesaj));
}

/* ---------------- Mal kabul (alma) ---------------- */

/**
 * ÜTS'den bu mağazaya verilmiş, henüz kabul edilmemiş ürünleri çeker ve "gelen" olarak saklar.
 * Listede artık olmayan eski "gelen" kayıtlar silinir.
 * Dönüş: ['ok' => bool, 'sayi' => int, 'mesaj' => string]
 */
function uts_gelenleri_getir(): array
{
    $s = uts_istek('alma_bekleyen', array_filter(['KUN' => uts_kurum_no()]));
    if (!$s['ok']) {
        return ['ok' => false, 'sayi' => 0, 'mesaj' => $s['mesaj']];
    }
    $ogeler = uts_liste_ogeleri($s['veri']);
    $gorulen = [];
    $sayi = 0;
    foreach ($ogeler as $x) {
        if ($x['vbi'] === '') {
            continue;   // verme bildirim numarası olmadan alma yapılamaz
        }
        $sayi++;
        $iz = $x['vbi'] . '|' . $x['uno'] . '|' . $x['lno'] . '|' . $x['sno'];
        $gorulen[$iz] = true;
        $alanlar = [
            'adet'           => $x['sno'] !== '' ? 1 : max(1, $x['adet']),
            'skt'            => $x['skt'] ?: null,
            'urt'            => $x['urt'] ?: null,
            'marka_model'    => $x['marka_model'] ?: null,
            'gonderen'       => $x['gonderen'] ?: null,
            'gonderen_kurum' => $x['gonderen_kurum'] ?: null,
            'belge_no'       => $x['belge_no'] ?: null,
            'updated_at'     => uts_simdi(),
        ];
        // Aynı sevkiyat (VBI) daha önce getirildiyse güncelle.
        $var = row(
            "SELECT * FROM uts_urunler WHERE vbi = ? AND uno = ? AND COALESCE(lno, '') = ? AND COALESCE(sno, '') = ? ORDER BY id DESC LIMIT 1",
            [$x['vbi'], $x['uno'], $x['lno'], $x['sno']]
        );
        if ($var) {
            if ($var['durum'] === 'gelen') {
                update('uts_urunler', $alanlar, 'id = ?', [(int) $var['id']]);
            }
            continue;
        }
        if ($x['sno'] !== '') {
            $anahtar = uts_anahtar($x['uno'], $x['lno'], $x['sno']);
            $seri = uts_urun_anahtarla($anahtar);
            if ($seri && $seri['durum'] === 'stokta' && !$seri['vbi']) {
                // Karekodu okutularak stoğa girmiş ama ÜTS'de henüz kabul edilmemiş: kabul için VBI'yi sakla.
                update('uts_urunler', ['vbi' => $x['vbi'], 'gonderen' => $x['gonderen'] ?: $seri['gonderen'], 'belge_no' => $x['belge_no'] ?: $seri['belge_no'], 'updated_at' => uts_simdi()], 'id = ?', [(int) $seri['id']]);
                continue;
            }
            if ($seri && in_array($seri['durum'], ['satildi', 'sgk', 'iade', 'imha'], true)) {
                // Aynı ürün yeniden gönderildi (ör. iade edilip geri geldi). Eski kayıt KABUL anında arşivlenir;
                // o zamana kadar gelen satır geçici anahtarla durur.
                $anahtar = mb_substr('G|' . $x['uno'] . '|' . $x['sno'] . '|v' . $x['vbi'], 0, 120);
                $seri = null;
            }
            if ($seri) {
                continue;   // zaten izleniyor (stokta / alma bekliyor / başka sevkiyattan gelen)
            }
        } else {
            $anahtar = uts_lot_anahtari($x['uno'], $x['lno'], 'v' . $x['vbi']);
        }
        insert('uts_urunler', $alanlar + [
            'anahtar'    => $anahtar,
            'uno'        => $x['uno'],
            'lno'        => $x['lno'] ?: null,
            'sno'        => $x['sno'] ?: null,
            'kaynak'     => 'uts',
            'kategori'   => uts_kategori_tahmini($x['marka_model']),
            'vbi'        => $x['vbi'],
            'durum'      => 'gelen',
            'created_at' => uts_simdi(),
        ]);
    }
    // ÜTS listesinden kalkmış (gönderen iptal etmiş / başka yerden kabul edilmiş) "gelen" kayıtları temizle.
    foreach (rows("SELECT id, vbi, uno, lno, sno FROM uts_urunler WHERE durum = 'gelen'") as $r) {
        $iz = $r['vbi'] . '|' . $r['uno'] . '|' . ($r['lno'] ?? '') . '|' . ($r['sno'] ?? '');
        if (!isset($gorulen[$iz])) {
            q("DELETE FROM uts_urunler WHERE id = ? AND durum = 'gelen'", [(int) $r['id']]);
        }
    }
    setting_set('uts_gelen_son', uts_simdi());
    return ['ok' => true, 'sayi' => $sayi, 'mesaj' => $s['mesaj']];
}

/** @internal ÜTS sorgu yanıtındaki ürün listesini (UNO içeren nesneler) düz diziye çevirir. */
function uts_liste_ogeleri(mixed $veri, int $derinlik = 0): array
{
    $sonuc = [];
    if (!is_array($veri) || $derinlik > 6) {
        return $sonuc;
    }
    $al = static function (array $d, array $anahtarlar): string {
        foreach ($anahtarlar as $k) {
            if (isset($d[$k]) && is_scalar($d[$k]) && trim((string) $d[$k]) !== '') {
                return trim((string) $d[$k]);
            }
        }
        return '';
    };
    if (isset($veri['UNO']) && is_scalar($veri['UNO'])) {
        $uno = preg_replace('/\s+/', '', (string) $veri['UNO']) ?? '';
        $vbi = $al($veri, ['VBI', 'BID', 'bildirimId']);
        if ($uno !== '' && strlen($uno) <= 23) {
            $tarih = static function (string $v): string {
                $v = substr($v, 0, 10);
                return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
            };
            $sonuc[] = [
                'uno'            => $uno,
                'lno'            => mb_substr($al($veri, ['LNO']), 0, 40),
                'sno'            => mb_substr($al($veri, ['SNO']), 0, 40),
                'adet'           => (int) ($al($veri, ['ADT', 'KAD']) ?: 1),
                'skt'            => $tarih($al($veri, ['SKT'])),
                'urt'            => $tarih($al($veri, ['URT'])),
                'marka_model'    => mb_substr($al($veri, ['MME', 'MRK', 'UAD', 'urunAdi']), 0, 200),
                'gonderen'       => mb_substr($al($veri, ['AKU', 'GKA', 'KUA', 'gonderenKurumAdi']), 0, 200),
                'gonderen_kurum' => mb_substr(preg_replace('/\D/', '', $al($veri, ['GKN', 'GKU', 'KUN'])) ?? '', 0, 20),
                'belge_no'       => mb_substr($al($veri, ['BNO']), 0, 40),
                'vbi'            => uts_uuid_mi($vbi) ? strtolower($vbi) : '',
            ];
        }
        return $sonuc;
    }
    foreach ($veri as $v) {
        if (is_array($v)) {
            $sonuc = array_merge($sonuc, uts_liste_ogeleri($v, $derinlik + 1));
        }
    }
    return $sonuc;
}

function uts_kategori_tahmini(string $ad): string
{
    $a = tr_lower($ad);
    return match (true) {
        (bool) preg_match('/kontakt|lens|lent/u', $a) && !preg_match('/gözlük camı|cam/u', $a) => 'lens',
        (bool) preg_match('/güneş|gunes|sun/u', $a)                                             => 'gunes',
        (bool) preg_match('/cam|glass|organik|mineral/u', $a)                                   => 'cam',
        (bool) preg_match('/çerçeve|cerceve|frame|numaralı gözlük|optik/u', $a)                 => 'cerceve',
        default                                                                                  => 'cerceve',
    };
}

/**
 * Seçilen gelen ürünleri kabul eder: alma bildirimi sıraya girer, çerçeveler karta bağlanır.
 * $kategoriler: [urun_id => kategori]. Dönüş: kabul edilen sayı.
 */
function uts_gelenleri_kabul_et(array $idler, array $kategoriler, bool $cerceveStokArtir, bool $kartOlustur): int
{
    $n = 0;
    foreach (array_unique(array_map('intval', $idler)) as $id) {
        $u = uts_urun($id);
        if (!$u || !in_array($u['durum'], ['gelen', 'stokta'], true)) {
            continue;
        }
        if (!$u['vbi']) {
            continue;   // ÜTS'den gelmemiş (okutulmuş) ürün: kabul edilecek verme bildirimi yok
        }
        if (str_starts_with((string) $u['anahtar'], 'G|')) {
            // Yeniden gelen seri: eski kayıt çıkış yapmışsa arşivlenir, bu satır asıl anahtarı alır.
            $asil = uts_anahtar((string) $u['uno'], (string) ($u['lno'] ?? ''), (string) $u['sno']);
            $eski = uts_urun_anahtarla($asil);
            if ($eski && !in_array($eski['durum'], ['satildi', 'sgk', 'iade', 'imha'], true)) {
                continue;   // aynı seri hâlâ stokta / yolda: ikinci kayıt açılmaz
            }
            if ($eski) {
                uts_seri_arsivle($eski);
            }
            q('UPDATE uts_urunler SET anahtar = ?, updated_at = ? WHERE id = ?', [$asil, uts_simdi(), (int) $u['id']]);
            $u['anahtar'] = $asil;
        }
        transaction(static function () use ($u, $kategoriler, $cerceveStokArtir, $kartOlustur, &$n): void {
            $id = (int) $u['id'];
            $kat = (string) ($kategoriler[$id] ?? $u['kategori']);
            $kat = isset(uts_kategoriler()[$kat]) ? $kat : 'diger';
            $zatenStokta = $u['durum'] === 'stokta';
            q("UPDATE uts_urunler SET kategori = ?, durum = ?, updated_at = ? WHERE id = ?", [$kat, $zatenStokta ? 'stokta' : 'alma_bekliyor', uts_simdi(), $id]);
            if (!$zatenStokta) {
                // Aynı belge (fatura) e-Fatura içe aktarmayla stoğa girdiyse adet ikinci kez artırılmaz.
                $faturaylaGirdi = function_exists('alis_faturadan_stoga_girdi') && alis_faturadan_stoga_girdi((string) ($u['belge_no'] ?? ''));
                uts_cerceve_bagla($id, $cerceveStokArtir && !$faturaylaGirdi, $kartOlustur);
            }
            $govde = ['VBI' => (string) $u['vbi']];
            if (!uts_seri_mi($u)) {
                $govde['ADT'] = max(1, (int) $u['adet']);
            }
            uts_bildirim_ekle('alma', $govde, $id, null, (int) $u['adet'], 'alma:' . $u['vbi'] . ':' . mb_substr((string) $u['anahtar'], 0, 30));
            $n++;
        });
    }
    return $n;
}

/** Deneme modu: ekranı denemek için örnek "gelen" ürünler oluşturur. */
function uts_deneme_ornek_gelen(): int
{
    if (uts_ortam() !== 'deneme') {
        return 0;
    }
    $ornekler = [
        ['DENEME Ray-Ban RB5154 Siyah 51-21', 'cerceve'],
        ['DENEME Essilor Varilux 1.6 Organik Cam', 'cam'],
        ['DENEME Vogue VO5286 Havana', 'cerceve'],
    ];
    $n = 0;
    foreach ($ornekler as $i => [$ad, $kat]) {
        $uno = '0' . str_pad((string) (8690000000000 + random_int(1000, 999999)), 13, '0', STR_PAD_LEFT);
        $sno = 'DN' . strtoupper(bin2hex(random_bytes(4)));
        insert('uts_urunler', [
            'anahtar' => uts_anahtar($uno, '', $sno), 'uno' => $uno, 'sno' => $sno, 'adet' => 1, 'kaynak' => 'uts',
            'kategori' => $kat, 'marka_model' => $ad, 'gonderen' => 'DENEME Optik Toptan A.Ş.', 'gonderen_kurum' => '9999999999',
            'belge_no' => 'DNM' . date('ymd') . $i, 'vbi' => uts_uuid4(), 'durum' => 'gelen',
            'skt' => $kat === 'cam' ? date('Y-m-d', strtotime('+3 years')) : null,
            'created_at' => uts_simdi(), 'updated_at' => uts_simdi(),
        ]);
        $n++;
    }
    return $n;
}

/* ---------------- Stoktan çıkış: imha, tedarikçiye iade ---------------- */

/** @internal Stokta ve siparişe bağlı olmayan ürünler. */
function uts_cikisa_uygun(array $idler): array
{
    $liste = [];
    foreach (array_unique(array_map('intval', $idler)) as $id) {
        $u = uts_urun($id);
        if ($u && $u['durum'] === 'stokta' && !$u['order_id']) {
            $liste[] = $u;
        }
    }
    return $liste;
}

function uts_imha_et(array $idler, string $gerekce, string $aciklama, string $belgeNo): int
{
    $gerekce = isset(uts_imha_gerekceleri()[$gerekce]) ? $gerekce : 'SON_KULLANMA_TARIHI_GECMIS';
    $belgeNo = mb_substr(trim($belgeNo), 0, 40);
    $aciklama = mb_substr(trim($aciklama), 0, 200);
    if ($belgeNo === '') {
        throw new DomainException('İmha / bertaraf tutanak (belge) numarası zorunludur.');
    }
    if ($gerekce === 'DIGER' && $aciklama === '') {
        throw new DomainException('"Diğer" gerekçesi için açıklama yazın.');
    }
    $n = 0;
    foreach (uts_cikisa_uygun($idler) as $u) {
        transaction(static function () use ($u, $gerekce, $aciklama, $belgeNo, &$n): void {
            q("UPDATE uts_urunler SET durum = 'imha', cikis_at = ?, updated_at = ? WHERE id = ? AND durum = 'stokta'", [uts_simdi(), uts_simdi(), (int) $u['id']]);
            if ($u['frame_item_id']) {
                frame_move((int) $u['frame_item_id'], -max(1, (int) $u['adet']), 'imha', null, 'ÜTS imha · ' . $belgeNo);
            }
            $g = uts_govde_urun($u) + ['GRK' => $gerekce, 'BNO' => $belgeNo];
            if ($gerekce === 'DIGER') {
                $g['DGA'] = $aciklama;
            }
            uts_bildirim_ekle('imha', $g, (int) $u['id'], null, (int) $u['adet'], 'imha:u' . (int) $u['id'] . ':' . bin2hex(random_bytes(3)));
            $n++;
        });
    }
    return $n;
}

function uts_tedarikciye_iade(array $idler, int $tedarikciId, string $belgeNo): int
{
    $t = row('SELECT id, name, uts_kurum_no FROM suppliers WHERE id = ?', [$tedarikciId]);
    $kun = preg_replace('/\D/', '', (string) ($t['uts_kurum_no'] ?? '')) ?? '';
    if (!$t || $kun === '') {
        throw new DomainException('Tedarikçinin ÜTS kurum numarası girilmemiş (Tedarikçiler › tedarikçi kartı).');
    }
    $belgeNo = mb_substr(trim($belgeNo), 0, 40);
    if ($belgeNo === '') {
        throw new DomainException('İade faturası / irsaliye numarası zorunludur.');
    }
    $n = 0;
    foreach (uts_cikisa_uygun($idler) as $u) {
        transaction(static function () use ($u, $kun, $belgeNo, &$n): void {
            q("UPDATE uts_urunler SET durum = 'iade', cikis_at = ?, updated_at = ? WHERE id = ? AND durum = 'stokta'", [uts_simdi(), uts_simdi(), (int) $u['id']]);
            if ($u['frame_item_id']) {
                frame_move((int) $u['frame_item_id'], -max(1, (int) $u['adet']), 'tedarikci_iade', null, 'ÜTS tedarikçiye iade · ' . $belgeNo);
            }
            uts_bildirim_ekle('verme', uts_govde_urun($u) + ['KUN' => $kun, 'BNO' => $belgeNo, 'BEN' => 'HAYIR'], (int) $u['id'], null, (int) $u['adet'], 'verme:u' . (int) $u['id'] . ':' . bin2hex(random_bytes(3)));
            $n++;
        });
    }
    return $n;
}

/**
 * Deneme modunda "gönderilmiş" sayılan bildirimleri, gerçek ortama geçtikten sonra yeniden sıraya alır.
 * (Alma hariç: deneme modunda ÜTS'den gelen ürün listesi çekilmez.)
 */
function uts_deneme_kayitlarini_kuyruga_al(): int
{
    if (uts_ortam() === 'deneme') {
        return 0;
    }
    $n = 0;
    foreach (rows("SELECT id, tur FROM uts_bildirimler WHERE ortam = 'deneme' AND durum = 'gonderildi' AND tur <> 'alma' ORDER BY id") as $b) {
        $n += q("UPDATE uts_bildirimler SET durum = 'bekliyor', uts_id = NULL, deneme = 0, son_hata = NULL, planlanan = ? WHERE id = ? AND ortam = 'deneme'", [uts_simdi(), (int) $b['id']])->rowCount();
    }
    // Deneme modunda iade edilen satışlar: satış ve iade birlikte tekrar gider; sıralama id ile korunur.
    return $n;
}

/* ---------------- Özet, menü, arka plan ---------------- */

function uts_ozet(): array
{
    $bugun = date('Y-m-d');
    $ay = date('Y-m-d', strtotime('+30 days'));
    $say = static fn(string $sql, array $p = []): int => (int) scalar($sql, $p);
    return [
        'stokta'      => $say("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'stokta'"),
        'ayrilmis'    => $say("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'stokta' AND order_id IS NOT NULL"),
        'skt_gecmis'  => $say("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'stokta' AND skt IS NOT NULL AND skt < ?", [$bugun]),
        'skt_yakin'   => $say("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'stokta' AND skt IS NOT NULL AND skt >= ? AND skt <= ?", [$bugun, $ay]),
        'gelen'       => $say("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'gelen'"),
        'alma'        => $say("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'alma_bekliyor'"),
        'onay'        => $say("SELECT COUNT(*) FROM uts_bildirimler WHERE durum = 'onay_bekliyor'"),
        'sirada'      => $say("SELECT COUNT(*) FROM uts_bildirimler WHERE durum IN ('bekliyor','gonderiliyor')"),
        'hata'        => $say("SELECT COUNT(*) FROM uts_bildirimler WHERE durum = 'hata'"),
    ];
}

/** Menü rozeti: personelin bakması gereken iş (hata + onay + gelen). */
function uts_menu_rozeti(): int
{
    try {
        return (int) scalar("SELECT (SELECT COUNT(*) FROM uts_bildirimler WHERE durum IN ('hata','onay_bekliyor')) + (SELECT COUNT(*) FROM uts_urunler WHERE durum = 'gelen')");
    } catch (Throwable) {
        return 0;
    }
}

/** Zamanlanmış görev: kuyruğu işler. Gelen listesi günde en çok birkaç kez otomatik tazelenir. */
function uts_gorev(): array
{
    $ozet = uts_kuyrugu_isle(30, 30);
    if (uts_ortam() !== 'deneme' && uts_hazir_mi() && time() - (int) strtotime(setting('uts_gelen_son', '2000-01-01')) > 3 * 3600) {
        try {
            uts_gelenleri_getir();
        } catch (Throwable $e) {
            app_log('uts gelen: ' . $e->getMessage());
        }
    }
    return $ozet;
}
