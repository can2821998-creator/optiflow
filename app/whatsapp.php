<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — WhatsApp mesaj otomasyonu (4.12.0)

   Her mesaj önce KUYRUĞA (wa_mesajlar) yazılır; iki kanal vardır:

   • 'link'  — WhatsApp Business uygulaması (ek hesap/ücret gerekmez).
               Kuyruk ekranında tek tıkla hazır mesajla açılır; personel
               "Gönder"e basar. Kayıt "gönderildi" olarak işaretlenir.
   • 'cloud' — WhatsApp Business Platform (Meta Cloud API). Mesajlar Meta'da
               ONAYLANMIŞ şablonlarla, arka planda kendiliğinden gider.

   İzin: Bilgilendirme mesajları (gözlüğünüz hazır, teslim, ödeme linki)
   müşterinin kendi işlemiyle ilgilidir. Yenileme, SGK hakkı, lens ve yorum
   isteği gibi mesajlar yalnızca müşteri İZİN VERMİŞSE (customers.wa_izin = 1)
   kuyruğa girer. İzin kaynağı ve tarihi saklanır, işlem geçmişine yazılır.
   (Hukuki değerlendirme mağazanın sorumluluğundadır; ayarlarda uyarılır.)
   ========================================================================== */

const WA_API_SURUM_VARSAYILAN = 'v21.0';

/**
 * Olaylar. 'degiskenler' Cloud şablonundaki {{1}}, {{2}}… sırasıdır — Meta'da şablonu
 * bu sırayla oluşturun. 'izin' true ise müşteri izni gerekir.
 */
function wa_olaylar(): array
{
    return [
        'hazir' => [
            'ad' => 'Gözlük hazır', 'izin' => false,
            'degiskenler' => ['ad', 'siparis_no', 'magaza', 'takip_linki'],
            'metin' => "Merhaba {ad}, {siparis_no} numaralı gözlüğünüz hazır. Dilediğiniz zaman {magaza}'dan teslim alabilirsiniz.\n\nSipariş durumu: {takip_linki}",
        ],
        'teslim' => [
            'ad' => 'Teslim alınmadı hatırlatması', 'izin' => false,
            'degiskenler' => ['ad', 'siparis_no', 'magaza'],
            'metin' => "Merhaba {ad}, {siparis_no} numaralı gözlüğünüz {magaza}'da sizi bekliyor. Uygun olduğunuzda teslim alabilirsiniz.",
        ],
        'odeme' => [
            'ad' => 'Ödeme linki', 'izin' => false,
            'degiskenler' => ['ad', 'siparis_no', 'tutar', 'odeme_linki'],
            'metin' => "Merhaba {ad}, {siparis_no} numaralı siparişinizin kalan {tutar} tutarını kartla güvenle ödeyebilirsiniz: {odeme_linki}",
        ],
        'yenileme' => [
            'ad' => 'Gözlük yenileme', 'izin' => true,
            'degiskenler' => ['ad', 'magaza', 'magaza_telefon'],
            'metin' => "Merhaba {ad}, son gözlüğünüzün üzerinden epey zaman geçti. Göz numaranızı kontrol ettirmek için {magaza}'ya bekleriz. {magaza_telefon}",
        ],
        'sgk' => [
            'ad' => 'SGK hakkı', 'izin' => true,
            'degiskenler' => ['ad', 'hak_tarihi', 'magaza'],
            'metin' => "Merhaba {ad}, {hak_tarihi} itibarıyla SGK'dan yeni gözlük hakkınız doğmuş olabilir. Bilgi için {magaza}'ya bekleriz.",
        ],
        'lens' => [
            'ad' => 'Kontakt lens bitiyor', 'izin' => true,
            'degiskenler' => ['ad', 'urun', 'bitis', 'magaza'],
            'metin' => "Merhaba {ad}, {urun} lensleriniz {bitis} tarihinde bitiyor. Yeni kutunuzu {magaza}'dan hazırlatabiliriz.",
        ],
        'yorum' => [
            'ad' => 'Google yorum isteği', 'izin' => true,
            'degiskenler' => ['ad', 'magaza', 'yorum_linki'],
            'metin' => "Merhaba {ad}, {magaza}'yı tercih ettiğiniz için teşekkürler. Deneyiminizi paylaşırsanız çok seviniriz: {yorum_linki}",
        ],
    ];
}

function wa_kanal(): string
{
    return setting('wa_kanal', 'link') === 'cloud' ? 'cloud' : 'link';
}

/** Mağazanın o olay için metni (ayarlardan; boşsa varsayılan). */
function wa_metin_sablonu(string $olay): string
{
    $v = trim(setting('wa_metin_' . $olay, ''));
    return $v !== '' ? $v : (string) (wa_olaylar()[$olay]['metin'] ?? '');
}

/** Türkiye cep telefonu → 90XXXXXXXXXX; geçersizse ''. */
function wa_telefon(?string $ham): string
{
    $t = preg_replace('/\D/', '', (string) $ham) ?? '';
    if (str_starts_with($t, '0')) {
        $t = '9' . $t;
    }
    if (strlen($t) === 10) {
        $t = '90' . $t;
    }
    return preg_match('/^90[5]\d{9}$/', $t) ? $t : '';
}

/** Müşterinin izin durumu: 'var' | 'yok' | 'sorulmadi'. */
function wa_izin_durumu(?array $musteri): string
{
    if (!$musteri || $musteri['wa_izin'] === null || $musteri['wa_izin'] === '') {
        return 'sorulmadi';
    }
    return (int) $musteri['wa_izin'] === 1 ? 'var' : 'yok';
}

/** İzin kaydı (personel ekrandan). $izin: true/false/null (sıfırla). */
function wa_izin_kaydet(int $musteriId, ?bool $izin, string $kaynak): void
{
    update('customers', [
        'wa_izin'        => $izin === null ? null : ($izin ? 1 : 0),
        'wa_izin_tarihi' => $izin === null ? null : date('Y-m-d H:i:s'),
        'wa_izin_kaynak' => $izin === null ? null : mb_substr($kaynak, 0, 40),
    ], 'id = ?', [$musteriId]);
    audit('wa_izin', 'customer', $musteriId, ['izin' => $izin === null ? 'sıfırlandı' : ($izin ? 'verdi' : 'vermedi'), 'kaynak' => $kaynak]);
}

/**
 * Kuyruğa mesaj ekler. Dönüş: ['ok' => bool, 'id' => ?int, 'neden' => string].
 * $tekil aynı mesajın iki kez kuyruğa girmesini engeller (ör. "hazir:o123").
 */
function wa_kuyruga_ekle(string $olay, ?int $musteriId, ?int $siparisId, array $degerler, ?string $tekil = null, ?string $planlanan = null): array
{
    if (!function_exists('ozellik_acik') || !ozellik_acik_arka_plan('whatsapp')) {
        return ['ok' => false, 'id' => null, 'neden' => 'WhatsApp otomasyonu bu mağazada kapalı.'];
    }
    $tanim = wa_olaylar()[$olay] ?? null;
    if (!$tanim) {
        return ['ok' => false, 'id' => null, 'neden' => 'Bilinmeyen mesaj türü.'];
    }
    $musteri = $musteriId ? row('SELECT id, first_name, last_name, phone, wa_izin FROM customers WHERE id = ?', [$musteriId]) : null;
    $telefon = wa_telefon((string) ($degerler['telefon'] ?? $musteri['phone'] ?? ''));
    if ($telefon === '') {
        return ['ok' => false, 'id' => null, 'neden' => 'Geçerli bir cep telefonu yok.'];
    }
    if ($tanim['izin'] && wa_izin_durumu($musteri) !== 'var') {
        return ['ok' => false, 'id' => null, 'neden' => 'Müşterinin mesaj izni yok.'];
    }
    $degerler += [
        'ad'             => (string) ($musteri['first_name'] ?? ''),
        'soyad'          => (string) ($musteri['last_name'] ?? ''),
        'magaza'         => setting('shop_name', 'OptiFlow'),
        'magaza_telefon' => setting('shop_phone', ''),
    ];
    $degerler = array_map(static fn($v) => trim((string) $v), $degerler);
    $vars = [];
    foreach ($degerler as $k => $v) {
        $vars['{' . $k . '}'] = $v;
    }
    $metin = trim(strtr(wa_metin_sablonu($olay), $vars));
    $param = [];
    foreach ($tanim['degiskenler'] as $k) {
        // Cloud şablon parametreleri boş olamaz; yoksa "-" yazılır.
        $param[] = ($degerler[$k] ?? '') !== '' ? mb_substr($degerler[$k], 0, 900) : '-';
    }
    $veri = [
        'customer_id'  => $musteriId ?: null,
        'order_id'     => $siparisId ?: null,
        'olay'         => $olay,
        'telefon'      => $telefon,
        'metin'        => mb_substr($metin, 0, 4000),
        'parametreler' => json_encode($param, JSON_UNESCAPED_UNICODE),
        'durum'        => 'bekliyor',
        'kanal'        => wa_kanal(),
        'tekil'        => $tekil !== null ? mb_substr($tekil, 0, 80) : null,
        'planlanan'    => $planlanan ?? date('Y-m-d H:i:s'),
        'created_by'   => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at'   => date('Y-m-d H:i:s'),
    ];
    if ($tekil !== null && scalar('SELECT id FROM wa_mesajlar WHERE tekil = ?', [$veri['tekil']])) {
        return ['ok' => false, 'id' => null, 'neden' => 'Bu mesaj zaten kuyrukta.'];
    }
    try {
        $id = insert('wa_mesajlar', $veri);
    } catch (PDOException $e) {
        // eşzamanlı ikinci ekleme: benzersiz anahtar
        return ['ok' => false, 'id' => null, 'neden' => 'Bu mesaj zaten kuyrukta.'];
    }
    return ['ok' => true, 'id' => $id, 'neden' => ''];
}

/**
 * Arka plan (zamanlanmış görev) bağlamında da çalışan özellik kontrolü.
 * Web isteğinde oturumdaki mağazanın listesine, görevde $GLOBALS['__gorev_ozellikler']'e bakar.
 */
function ozellik_acik_arka_plan(string $k): bool
{
    if (isset($GLOBALS['__gorev_ozellikler']) && is_array($GLOBALS['__gorev_ozellikler'])) {
        return in_array($k, $GLOBALS['__gorev_ozellikler'], true) && empty(ozellik_tanimlari()[$k]['masaustu']);
    }
    return ozellik_acik($k);
}

/** Link kanalı: WhatsApp'ta hazır mesajla açılan adres. */
function wa_mesaj_linki(array $m): string
{
    return 'https://wa.me/' . rawurlencode((string) $m['telefon']) . '?text=' . rawurlencode((string) $m['metin']);
}

/** Link kanalında personel "gönderdim" dediğinde. */
function wa_gonderildi_isaretle(int $id): bool
{
    $st = q("UPDATE wa_mesajlar SET durum = 'gonderildi', gonderilme = NOW(), gonderen = ? WHERE id = ? AND durum IN ('bekliyor','hata')", [(int) (current_user()['id'] ?? 0) ?: null, $id]);
    return $st->rowCount() > 0;
}

function wa_iptal(int $id): void
{
    q("UPDATE wa_mesajlar SET durum = 'iptal' WHERE id = ? AND durum IN ('bekliyor','hata')", [$id]);
}

/* ---------------- Meta Cloud API ---------------- */

function wa_cloud_hazir_mi(): bool
{
    return setting('wa_cloud_telefon_id', '') !== '' && gizli_ayar('wa_cloud_token') !== '';
}

/**
 * Tek mesajı Cloud API ile gönderir (şablon mesajı).
 * Dönüş: ['ok' => bool, 'dis_id' => string, 'hata' => string, 'kalici' => bool]
 * 'kalici' true ise tekrar denemek anlamsızdır (şablon yok, numara geçersiz vb.).
 */
function wa_cloud_gonder(array $m): array
{
    $telefonId = preg_replace('/\D/', '', setting('wa_cloud_telefon_id', '')) ?? '';
    $token = gizli_ayar('wa_cloud_token');
    $sablon = trim(setting('wa_sablon_' . $m['olay'], ''));
    $dil = trim(setting('wa_sablon_dil', 'tr')) ?: 'tr';
    $surum = preg_match('/^v\d{1,2}\.\d$/', setting('wa_api_surum', '')) ? setting('wa_api_surum') : WA_API_SURUM_VARSAYILAN;
    if ($telefonId === '' || $token === '') {
        return ['ok' => false, 'dis_id' => '', 'hata' => 'WhatsApp Cloud API bilgileri eksik (Ayarlar › WhatsApp).', 'kalici' => false];
    }
    if ($sablon === '' || !preg_match('/^[a-z0-9_]{1,512}$/', $sablon)) {
        return ['ok' => false, 'dis_id' => '', 'hata' => '"' . (wa_olaylar()[$m['olay']]['ad'] ?? $m['olay']) . '" için onaylı şablon adı girilmemiş.', 'kalici' => true];
    }
    $param = json_decode((string) $m['parametreler'], true);
    $govde = [
        'messaging_product' => 'whatsapp',
        'to'                => (string) $m['telefon'],
        'type'              => 'template',
        'template'          => [
            'name'     => $sablon,
            'language' => ['code' => $dil],
        ],
    ];
    if (is_array($param) && $param) {
        $govde['template']['components'] = [[
            'type'       => 'body',
            'parameters' => array_map(static fn($v) => ['type' => 'text', 'text' => (string) $v], array_values($param)),
        ]];
    }
    $r = dis_istek('POST', "https://graph.facebook.com/$surum/$telefonId/messages", [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/json',
    ], json_encode($govde, JSON_UNESCAPED_UNICODE));
    if ($r['durum'] === 0) {
        return ['ok' => false, 'dis_id' => '', 'hata' => 'Bağlantı hatası: ' . mb_substr($r['hata'], 0, 150), 'kalici' => false];
    }
    $j = json_decode($r['govde'], true);
    if ($r['durum'] >= 200 && $r['durum'] < 300 && isset($j['messages'][0]['id'])) {
        return ['ok' => true, 'dis_id' => (string) $j['messages'][0]['id'], 'hata' => '', 'kalici' => false];
    }
    $hata = (string) ($j['error']['error_data']['details'] ?? $j['error']['message'] ?? ('HTTP ' . $r['durum']));
    $kod = (int) ($j['error']['code'] ?? 0);
    // 4xx (yetki hariç) kalıcı sayılır: şablon/numara/parametre hatası tekrar denenerek düzelmez.
    $kalici = $r['durum'] >= 400 && $r['durum'] < 500 && !in_array($r['durum'], [401, 429], true) && $kod !== 130429;
    return ['ok' => false, 'dis_id' => '', 'hata' => mb_substr($hata, 0, 240), 'kalici' => $kalici];
}

/** Bekleyen Cloud mesajlarını gönderir. En çok $limit mesaj; üstel geri çekilmeyle tekrar dener. */
function wa_kuyrugu_isle(int $limit = 20): int
{
    if (wa_kanal() !== 'cloud' || !wa_cloud_hazir_mi()) {
        return 0;
    }
    $gonderilen = 0;
    foreach (rows("SELECT * FROM wa_mesajlar WHERE durum IN ('bekliyor','hata') AND kanal = 'cloud' AND deneme < 5 AND planlanan <= NOW() ORDER BY planlanan, id LIMIT " . (int) $limit) as $m) {
        // Aynı satırı iki süreç birden göndermesin: önce "gönderiliyor" diye kilitle.
        $kilit = q("UPDATE wa_mesajlar SET durum = 'gonderiliyor' WHERE id = ? AND durum IN ('bekliyor','hata')", [(int) $m['id']]);
        if ($kilit->rowCount() === 0) {
            continue;
        }
        $s = wa_cloud_gonder($m);
        if ($s['ok']) {
            q("UPDATE wa_mesajlar SET durum = 'gonderildi', gonderilme = NOW(), dis_id = ?, son_hata = NULL, deneme = deneme + 1 WHERE id = ?", [$s['dis_id'], (int) $m['id']]);
            $gonderilen++;
            continue;
        }
        $deneme = (int) $m['deneme'] + 1;
        $bekle = $s['kalici'] ? 0 : min(360, 5 * (2 ** $deneme));   // dakika
        q(
            "UPDATE wa_mesajlar SET durum = 'hata', son_hata = ?, deneme = ?, planlanan = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?",
            [$s['hata'], $s['kalici'] ? 5 : $deneme, $bekle, (int) $m['id']]
        );
    }
    // Bir süreç çökerse "gönderiliyor"da kalan satırları 15 dk sonra yeniden sıraya al.
    q("UPDATE wa_mesajlar SET durum = 'hata', son_hata = 'Gönderim yarıda kaldı' WHERE durum = 'gonderiliyor' AND planlanan < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
    return $gonderilen;
}

/** Menü rozeti: link kanalında personelin göndermesi beklenen mesajlar. */
function wa_bekleyen_sayisi(): int
{
    try {
        return (int) scalar("SELECT COUNT(*) FROM wa_mesajlar WHERE durum IN ('bekliyor','hata') AND planlanan <= NOW() AND (kanal = 'link' OR deneme >= 5)");
    } catch (Throwable) {
        return 0;
    }
}

/* ---------------- Olay tetikleyicileri ---------------- */

/** Sipariş "Hazır" aşamasına geçince (ayar açıksa) mesaj kuyruğa girer. */
function wa_siparis_hazir(int $siparisId): void
{
    try {
        if (!ozellik_acik_arka_plan('whatsapp') || setting('wa_oto_hazir', '1') !== '1') {
            return;
        }
        $o = row('SELECT id, customer_id, phone FROM orders WHERE id = ?', [$siparisId]);
        if (!$o || !$o['customer_id']) {
            return;
        }
        wa_kuyruga_ekle('hazir', (int) $o['customer_id'], $siparisId, [
            'siparis_no'  => order_no($siparisId),
            'takip_linki' => order_track_url($siparisId),
        ], 'hazir:o' . $siparisId);
    } catch (Throwable $e) {
        app_log('wa_siparis_hazir: ' . $e->getMessage());
    }
}

/**
 * Günlük otomatik mesajlar (günde bir kez, zamanlanmış görevden):
 *  - Teslimden N gün sonra Google yorum isteği (izinli müşteriler)
 *  - Bitişi yaklaşan kontakt lensler
 *  - İsteğe bağlı: yenileme / SGK hakkı / teslim alınmadı listeleri
 * Dönüş: kuyruğa eklenen mesaj sayısı.
 */
function wa_gunluk_gorevler(): int
{
    if (!ozellik_acik_arka_plan('whatsapp')) {
        return 0;
    }
    $n = 0;
    // Google yorum isteği — yalnızca son 7 günlük pencere (özellik ilk açıldığında eski müşterilere toplu mesaj gitmesin)
    $yorumLink = trim(setting('shop_review_url', ''));
    $gun = max(1, min(30, (int) setting('wa_yorum_gun', '2')));
    if (setting('wa_oto_yorum', '0') === '1' && str_starts_with($yorumLink, 'https://')) {
        foreach (rows(
            "SELECT o.id, o.customer_id FROM orders o
              WHERE o.order_stage = 'teslim_edildi' AND o.customer_id IS NOT NULL
                AND o.delivered_at BETWEEN DATE_SUB(NOW(), INTERVAL ? DAY) AND DATE_SUB(NOW(), INTERVAL ? DAY)
                AND COALESCE(o.transaction_type, 'gozluk') <> 'tamir'
              LIMIT 200",
            [$gun + 7, $gun]
        ) as $o) {
            // Aynı müşteriye 180 gün içinde ikinci yorum isteği gitmez.
            if (scalar("SELECT id FROM wa_mesajlar WHERE olay = 'yorum' AND customer_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 180 DAY) LIMIT 1", [(int) $o['customer_id']])) {
                continue;
            }
            $r = wa_kuyruga_ekle('yorum', (int) $o['customer_id'], (int) $o['id'], ['yorum_linki' => $yorumLink], 'yorum:o' . (int) $o['id']);
            $n += $r['ok'] ? 1 : 0;
        }
    }
    // Kontakt lens
    if (function_exists('lens_bitenler') && ozellik_acik_arka_plan('lens_takip') && setting('wa_oto_lens', '1') === '1') {
        foreach (lens_bitenler() as $l) {
            $r = wa_kuyruga_ekle('lens', (int) $l['customer_id'], $l['order_id'] ? (int) $l['order_id'] : null, [
                'urun'  => (string) $l['urun'],
                'bitis' => date_tr((string) $l['bitis']),
            ], 'lens:' . (int) $l['id'] . ':' . $l['bitis']);
            $n += $r['ok'] ? 1 : 0;
        }
    }
    // Hatırlatma listeleri (isteğe bağlı; varsayılan kapalı)
    $listeler = [
        'teslim'   => ['wa_oto_teslim', 'reminder_pickups'],
        'yenileme' => ['wa_oto_yenileme', 'reminder_renewals'],
        'sgk'      => ['wa_oto_sgk', 'reminder_sgk'],
    ];
    foreach ($listeler as $tur => [$ayar, $fn]) {
        if (setting($ayar, '0') !== '1' || !function_exists($fn)) {
            continue;
        }
        foreach ($fn(100) as $r) {
            $sonuc = wa_hatirlatma_kuyruga($tur, $r);
            $n += $sonuc['ok'] ? 1 : 0;
        }
    }
    return $n;
}

/**
 * Hatırlatma listesindeki bir satırı kuyruğa ekler ve listede "arandı (WhatsApp)" olarak işaretler.
 * Hatırlatma ekranındaki "Hepsini kuyruğa ekle" ve günlük görev bunu kullanır.
 */
function wa_hatirlatma_kuyruga(string $tur, array $r): array
{
    $musteriId = (int) ($r['customer_id'] ?? $r['id']);
    $siparis = !empty($r['son_siparis']) ? (int) $r['son_siparis'] : null;
    $degerler = [
        'siparis_no' => $siparis ? order_no($siparis) : '',
        'hak_tarihi' => !empty($r['hak_tarihi']) ? date_tr((string) $r['hak_tarihi']) : '',
        'urun'       => (string) ($r['urun'] ?? ''),
        'bitis'      => !empty($r['bitis']) ? date_tr((string) $r['bitis']) : '',
    ];
    $olay = $tur === 'teslim' ? 'teslim' : $tur;
    $sonuc = wa_kuyruga_ekle($olay, $musteriId, $siparis, $degerler, $tur . ':c' . $musteriId . ':' . date('Y-m'));
    if ($sonuc['ok'] && $tur !== 'lens') {
        reminder_mark($tur, $musteriId, 'yapildi', ['order_id' => $siparis, 'channel' => 'whatsapp', 'note' => 'WhatsApp kuyruğu']);
    }
    return $sonuc;
}

function wa_durum_etiketi(string $d): array
{
    return match ($d) {
        'bekliyor'     => ['Bekliyor', 'amber'],
        'gonderiliyor' => ['Gönderiliyor', 'blue'],
        'gonderildi'   => ['Gönderildi', 'green'],
        'hata'         => ['Hata', 'red'],
        'iptal'        => ['İptal', 'gray'],
        default        => [$d, 'gray'],
    };
}
