<?php
declare(strict_types=1);

/* ==========================================================================
   4.30.0 — Telefon asistanı (özellik: telefon_asistan).
   Mağazanın Android telefonundaki "OptiFlow Asistan" uygulaması, kimse açmayan aramayı birkaç çalıştan
   sonra açar, "… hoş geldiniz" der ve arayanla konuşur. Uygulama yalnızca SES TERMİNALİDİR: ne
   söyleneceğine burada, sunucuda karar verilir (asistan.php uç noktası). Böylece cevaplar uygulama
   güncellemeden iyileştirilebilir ve test edilebilir.

   Arayan → (tuş ya da konuşma) → uygulama metne çevirir → asistan_cevap() → söylenecek metin.

   GÜVENLİK / KVKK:
   - Uygulama mağazaya tek kullanımlık eşleştirme koduyla bağlanır; sonra cihaza özel 40 haneli anahtar
     (veritabanında yalnızca SHA-256 özeti) kullanır. Anahtar mağaza veritabanındadır; başka mağazada geçmez.
   - Arayan numara taklit edilebilir. Bu yüzden telefonda TUTAR / BAKİYE / ADRES söylenmez; yalnızca
     sipariş aşaması ve tahmini tarih. Numara tuşlanarak sorulursa isim de söylenmez.
   - Konuşma metni saklanmaz; yalnızca kısa özet ve arayanın bıraktığı not (90 gün sonra silinir).
   ========================================================================== */

const ASISTAN_TUR_SINIRI = 6;          // bir aramada en çok kaç soru-cevap
const ASISTAN_KAYIT_GUN = 90;          // arama kayıtları bu kadar gün saklanır
const ASISTAN_ESLESME_DK = 15;         // eşleştirme kodu geçerlilik süresi

function asistan_ayar(string $k, string $varsayilan = ''): string
{
    return setting('asistan_' . $k, $varsayilan);
}

function asistan_acik_mi(): bool
{
    return asistan_ayar('acik', '1') === '1';
}

/** Çaldırma süresi (saniye): kimse açmazsa asistan bu kadar sonra açar. */
function asistan_bekleme_sn(): int
{
    return max(5, min(60, (int) asistan_ayar('bekleme', '20')));
}

/** Türkçe yönelme eki: "Poyraz Optik" → "Poyraz Optik'e", "Ada" → "Ada'ya". */
function asistan_yonelme(string $ad): string
{
    $ad = trim($ad);
    if ($ad === '') {
        return '';
    }
    $kucuk = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], $ad), 'UTF-8');
    preg_match_all('/[aeıioöuü]/u', $kucuk, $m);
    $son = end($m[0]) ?: 'e';
    $ince = in_array($son, ['e', 'i', 'ö', 'ü'], true);
    $sonHarf = mb_substr($kucuk, -1, 1, 'UTF-8');
    $unluyleBiter = in_array($sonHarf, ['a', 'e', 'ı', 'i', 'o', 'ö', 'u', 'ü'], true);
    return $ad . "'" . ($unluyleBiter ? 'y' : '') . ($ince ? 'e' : 'a');
}

function asistan_karsilama(): string
{
    $magaza = setting('shop_name', 'OptiFlow');
    $ozel = trim(asistan_ayar('karsilama'));
    if ($ozel !== '') {
        return str_replace('{magaza}', $magaza, $ozel);
    }
    return asistan_yonelme($magaza) . ' hoş geldiniz. Ben mağazamızın dijital asistanıyım.';
}

/** "2026-10-12" → "12 Ekim Pazartesi" (bugün / yarın da söylenir) */
function asistan_tarih(string $ymd, ?string $bugun = null): string
{
    $ts = strtotime(substr($ymd, 0, 10));
    if (!$ts) {
        return '';
    }
    $bugun ??= date('Y-m-d');
    $gun = date('Y-m-d', $ts);
    if ($gun === $bugun) {
        return 'bugün';
    }
    if ($gun === date('Y-m-d', strtotime($bugun . ' +1 day'))) {
        return 'yarın';
    }
    $aylar = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $gunler = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    return (int) date('j', $ts) . ' ' . $aylar[(int) date('n', $ts)] . ' ' . $gunler[(int) date('w', $ts)];
}

/** Arayan numaranın son 10 hanesi (5XXXXXXXXX / 2XXXXXXXXX); tanınmazsa ''. */
function asistan_numara(string $ham): string
{
    $d = preg_replace('/\D+/', '', $ham) ?? '';
    return strlen($d) >= 10 ? substr($d, -10) : '';
}

/** Numaraya kayıtlı müşteri (müşteri kartı ya da sipariş telefonu). */
function asistan_musteri_bul(string $numara): ?array
{
    $son10 = asistan_numara($numara);
    if ($son10 === '') {
        return null;
    }
    $temiz = static fn(string $kolon): string => "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($kolon, ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '')";
    $m = row('SELECT * FROM customers WHERE ' . $temiz('phone') . ' LIKE ? ORDER BY id DESC LIMIT 1', ['%' . $son10]);
    if ($m) {
        return $m;
    }
    try {
        $o = row("SELECT customer_id FROM orders WHERE customer_id IS NOT NULL AND " . $temiz('phone') . " LIKE ? ORDER BY id DESC LIMIT 1", ['%' . $son10]);
    } catch (Throwable $e) {
        $o = null;   // eski şemada orders.phone yoksa
    }
    return $o ? find_customer((int) $o['customer_id']) : null;
}

/** Müşterinin konuşulacak siparişleri: iptal olmayan, teslim edilmemiş ya da son 3 günde teslim edilmiş (en çok 3). */
function asistan_siparisler(int $musteriId, ?string $simdi = null): array
{
    $simdi ??= date('Y-m-d H:i:s');
    $sinir = date('Y-m-d H:i:s', strtotime($simdi) - 3 * 86400);
    return rows(
        "SELECT * FROM orders WHERE customer_id = ? AND order_stage <> 'iptal'
           AND (order_stage <> 'teslim_edildi' OR delivered_at >= ?)
         ORDER BY created_at DESC, id DESC LIMIT 3",
        [$musteriId, $sinir]
    );
}

/** Siparişin camları: 'yok' (cam kaydı yok) | 'eksik' | 'siparis' (depodan bekleniyor) | 'geldi' */
function asistan_cam_durumu(int $orderId): string
{
    try {
        $kalemler = rows(
            'SELECT i.stock_status FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE r.order_id = ?',
            [$orderId]
        );
    } catch (Throwable $e) {
        return 'yok';
    }
    if (!$kalemler) {
        return 'yok';
    }
    $d = array_column($kalemler, 'stock_status');
    if (in_array('stokta_yok', $d, true)) {
        return 'eksik';
    }
    if (in_array('siparis_verildi', $d, true)) {
        return 'siparis';
    }
    return 'geldi';
}

/** Bir siparişin telefonda söylenecek durumu (tutar söylenmez). */
function asistan_siparis_cumlesi(array $o, ?string $bugun = null): string
{
    $bugun ??= date('Y-m-d');
    $tamir = ($o['transaction_type'] ?? '') === 'tamir';
    $asama = (string) $o['order_stage'];
    $soz = !empty($o['promised_date']) ? substr((string) $o['promised_date'], 0, 10) : '';
    $sozGecerli = $soz !== '' && $soz >= $bugun;
    $tahmin = $sozGecerli ? ' Tahmini teslim ' . asistan_tarih($soz, $bugun) . '.' : ' En kısa sürede hazır olacak.';

    if ($asama === 'teslim_edildi') {
        return ($tamir ? 'Onarım için bıraktığınız ürün ' : 'Gözlüğünüz ') . asistan_tarih((string) $o['delivered_at'], $bugun) . ' teslim edilmiş görünüyor.';
    }
    if ($asama === 'hazirlandi') {
        return ($tamir ? 'Onarımınız tamamlandı.' : 'Gözlüğünüz hazır.') . ' Mağazamızdan teslim alabilirsiniz.';
    }
    if ($tamir) {
        return ($asama === 'atolyede' ? 'Onarımınız ustamızda devam ediyor.' : 'Onarım kaydınız alındı, sıraya girdi.') . $tahmin;
    }
    if ($asama === 'atolyede') {
        return 'Camlarınız geldi, gözlüğünüz atölyede hazırlanıyor.' . $tahmin;
    }
    $cam = asistan_cam_durumu((int) $o['id']);
    $metin = match ($cam) {
        'eksik'   => 'Camlarınız tedarik ediliyor.',
        'siparis' => 'Camlarınız sipariş edildi, depodan gelmesi bekleniyor.',
        'geldi'   => 'Camlarınız geldi, gözlüğünüz kısa süre içinde hazırlanacak.',
        default   => 'Siparişiniz alındı, hazırlanıyor.',
    };
    return $metin . $tahmin;
}

/** Müşterinin tüm aktif siparişleri için tek konuşma metni. */
function asistan_durum_metni(array $siparisler, ?string $bugun = null): string
{
    if (!$siparisler) {
        return 'Şu anda hazırlanmakta olan bir siparişiniz görünmüyor.';
    }
    if (count($siparisler) === 1) {
        return asistan_siparis_cumlesi($siparisler[0], $bugun);
    }
    $p = [];
    foreach ($siparisler as $o) {
        $p[] = (int) $o['id'] . ' numaralı siparişiniz: ' . asistan_siparis_cumlesi($o, $bugun);
    }
    return count($siparisler) . ' siparişiniz var. ' . implode(' ', $p);
}

function asistan_adres_metni(): string
{
    $adres = trim(setting('shop_address', ''));
    $saat = trim(str_replace(["\r\n", "\n"], '. ', setting('shop_hours', '')));
    $p = [];
    if ($adres !== '') {
        $p[] = 'Adresimiz: ' . $adres . '.';
    }
    if ($saat !== '') {
        $p[] = 'Çalışma saatlerimiz: ' . rtrim($saat, '. ') . '.';
    }
    return $p ? implode(' ', $p) : 'Adres ve çalışma saatleri bilgisi için sizi mağazamız arayacak.';
}

const ASISTAN_MENU = "Sipariş durumu için 1'e, adres ve çalışma saatleri için 2'ye, mağazaya not bırakmak için 3'e basın. Dilerseniz sorunuzu söyleyebilirsiniz.";
const ASISTAN_DEVAM = "Başka bir konuda yardımcı olabilir miyim? Sipariş durumu için 1, adres ve saatler için 2, not bırakmak için 3. İşiniz bittiyse kapatabilirsiniz.";

/** Konuşma ya da tuştan niyet: siparis | adres | not | bitir | '' (anlaşılmadı) */
function asistan_niyet(?string $metin, ?string $tus): string
{
    $tus = trim((string) $tus, " #*");
    if ($tus !== '') {
        return match ($tus[0]) {
            '1' => 'siparis', '2' => 'adres', '3', '0' => 'not', '9' => 'bitir', default => '',
        };
    }
    $t = mb_strtolower(str_replace(['I', 'İ'], ['ı', 'i'], (string) $metin), 'UTF-8');
    if (trim($t) === '') {
        return '';
    }
    if (preg_match('/\b(bir|1)\b/u', $t) && mb_strlen($t) < 8) {
        return 'siparis';
    }
    if (preg_match('/\b(iki|2)\b/u', $t) && mb_strlen($t) < 8) {
        return 'adres';
    }
    if (preg_match('/\b(üç|3)\b/u', $t) && mb_strlen($t) < 8) {
        return 'not';
    }
    if (preg_match('/(yetkili|görüş|konuş|not|mesaj|beni ara|geri ara|arayın|personel|biriyle|insan|şikayet|şikâyet|randevu|fiyat|kaç para|ücret)/u', $t)) {
        return 'not';
    }
    if (preg_match('/(hazır|geldi|gelmedi|gelecek|ne zaman|cam|gözlü|sipariş|durum|bitti|teslim|tamir|onarım)/u', $t)) {
        return 'siparis';
    }
    if (preg_match('/(adres|nerede|neredesiniz|konum|yol tarif|saat|kaçta|kaça kadar|açık mı|açık mısınız|kapan|mesai|cumartesi|pazar)/u', $t)) {
        return 'adres';
    }
    if (preg_match('/(hayır|yok|teşekkür|sağ ?ol|sağolun|tamam|görüşürüz|iyi günler|kapat|bu kadar)/u', $t)) {
        return 'bitir';
    }
    return '';
}

/** Tuşlanan / söylenen telefon numarası (rakamlar) */
function asistan_rakamlar(?string $metin, ?string $tus): string
{
    $kaynak = trim((string) $tus) !== '' ? (string) $tus : (string) $metin;
    return preg_replace('/\D+/', '', $kaynak) ?? '';
}

function asistan_kayit_temizle(): void
{
    try {
        q('DELETE FROM asistan_aramalar WHERE created_at < ?', [date('Y-m-d H:i:s', time() - ASISTAN_KAYIT_GUN * 86400)]);
    } catch (Throwable $e) {
        // tablo yoksa önemli değil
    }
}

/** Yanıt kalıbı */
function asistan_yanit(array $arama, string $soyle, string $mod, bool $bitir = false): array
{
    return ['ok' => true, 'oturum' => $arama['anahtar'], 'soyle' => $soyle, 'mod' => $bitir ? 'bitti' : $mod, 'bitir' => $bitir];
}

function asistan_arama_guncelle(array &$arama, array $durum, array $ek = []): void
{
    $arama['durum'] = $durum;
    $ozet = (string) ($arama['ozet'] ?? '');
    $veri = ['durum_json' => json_encode($durum, JSON_UNESCAPED_UNICODE), 'ozet' => mb_substr($ozet, 0, 1000), 'updated_at' => date('Y-m-d H:i:s')] + $ek;
    update('asistan_aramalar', $veri, 'id = ?', [(int) $arama['id']]);
    foreach ($ek as $k => $v) {
        $arama[$k] = $v;
    }
}

/** Yeni arama: karşılama + (tanınan müşteriye) sipariş durumu + menü. */
function asistan_basla(string $numara, ?int $cihazId = null): array
{
    asistan_kayit_temizle();
    $musteri = asistan_musteri_bul($numara);
    $simdi = date('Y-m-d H:i:s');
    $anahtar = bin2hex(random_bytes(12));
    $id = insert('asistan_aramalar', [
        'anahtar' => $anahtar, 'cihaz_id' => $cihazId, 'numara' => mb_substr(preg_replace('/[^\d+]/', '', $numara) ?? '', 0, 20),
        'customer_id' => $musteri ? (int) $musteri['id'] : null, 'sonuc' => 'basladi', 'ozet' => '', 'geri_ara' => 0,
        'durum_json' => '{}', 'created_at' => $simdi, 'updated_at' => $simdi,
    ]);
    $arama = ['id' => $id, 'anahtar' => $anahtar, 'ozet' => '', 'customer_id' => $musteri['id'] ?? null, 'numara' => $numara];
    $soz = asistan_karsilama();
    $durum = ['adim' => 'menu', 'tur' => 0, 'anlasilmadi' => 0, 'numara_deneme' => 0];
    if ($musteri) {
        $siparisler = asistan_siparisler((int) $musteri['id']);
        $ad = trim((string) $musteri['first_name'] . ' ' . (string) $musteri['last_name']);
        $soz .= ' Merhaba ' . $ad . '. ' . asistan_durum_metni($siparisler);
        $arama['ozet'] = 'Tanındı: ' . $ad . ' · durum söylendi';
        $durum['bilgi_verildi'] = true;
        $soz .= ' ' . ASISTAN_DEVAM;
    } else {
        $arama['ozet'] = 'Numara kayıtlı değil';
        $soz .= ' ' . ASISTAN_MENU;
    }
    asistan_arama_guncelle($arama, $durum, ['sonuc' => $musteri ? 'bilgi' : 'basladi']);
    return asistan_yanit($arama, $soz, 'menu');
}

function asistan_arama_bul(string $anahtar): ?array
{
    if (!preg_match('/^[a-f0-9]{24}$/', $anahtar)) {
        return null;
    }
    $a = row('SELECT * FROM asistan_aramalar WHERE anahtar = ?', [$anahtar]);
    if (!$a) {
        return null;
    }
    $a['durum'] = json_decode((string) $a['durum_json'], true) ?: [];
    return $a;
}

/** Not bırakıldı / anlaşılamadı → geri arama kaydı + yetkililere bildirim. */
function asistan_geri_ara(array &$arama, array $durum, string $not): void
{
    asistan_arama_guncelle($arama, $durum, ['geri_ara' => 1, 'sonuc' => 'not', 'not_metni' => $not !== '' ? mb_substr($not, 0, 500) : null]);
    if (!function_exists('push_send')) {
        return;
    }
    try {
        $kim = '';
        if (!empty($arama['customer_id'])) {
            $c = row('SELECT first_name, last_name FROM customers WHERE id = ?', [(int) $arama['customer_id']]);
            $kim = $c ? trim($c['first_name'] . ' ' . $c['last_name']) : '';
        }
        $ids = array_map('intval', array_column(rows("SELECT id FROM user_accounts WHERE is_active = 1"), 'id'));
        if ($ids) {
            push_send([
                'title' => 'Geri aranacak · ' . ($kim !== '' ? $kim : phone_display(normalize_phone((string) $arama['numara']) ?: (string) $arama['numara'])),
                'body'  => $not !== '' ? mb_substr($not, 0, 140) : 'Telefon asistanı not aldı.',
                'url'   => 'telefon-asistani.php',
                'tag'   => 'asistan-' . (int) $arama['id'],
            ], $ids);
        }
    } catch (Throwable $e) {
        // bildirim gidemedi: kayıt yine listede
    }
}

/** Arayanın tuşu / sözü → sıradaki cümle. */
function asistan_cevap(array $arama, ?string $metin, ?string $tus): array
{
    $durum = $arama['durum'] + ['adim' => 'menu', 'tur' => 0, 'anlasilmadi' => 0, 'numara_deneme' => 0];
    $durum['tur']++;
    $metin = trim((string) $metin);
    $kapanis = 'Aradığınız için teşekkür ederiz, iyi günler dileriz.';

    if ($durum['tur'] > ASISTAN_TUR_SINIRI) {
        asistan_arama_guncelle($arama, $durum);
        return asistan_yanit($arama, $kapanis, 'bitti', true);
    }

    // Not bekleniyordu: söylenen her şey not olarak kaydedilir.
    if ($durum['adim'] === 'not') {
        $durum['adim'] = 'menu';
        $arama['ozet'] .= ' · not bırakıldı';
        asistan_geri_ara($arama, $durum, $metin);
        return asistan_yanit($arama, 'Notunuzu aldım. Mağazamız sizi en kısa sürede arayacak. ' . $kapanis, 'bitti', true);
    }

    // Telefon numarası bekleniyordu (numarası kayıtlı olmayan arayan).
    if ($durum['adim'] === 'numara') {
        $rakam = asistan_rakamlar($metin, $tus);
        if (strlen($rakam) >= 10) {
            $durum['numara_deneme']++;
            $m = asistan_musteri_bul($rakam);
            $durum['adim'] = 'menu';
            if ($m) {
                $arama['ozet'] .= ' · numara tuşlandı, durum söylendi';
                asistan_arama_guncelle($arama, $durum, ['customer_id' => (int) $m['id'], 'sonuc' => 'bilgi']);
                // İsim söylenmez: tuşlanan numara başkasına ait olabilir.
                return asistan_yanit($arama, 'Bu numaraya kayıtlı siparişin durumu: ' . asistan_durum_metni(asistan_siparisler((int) $m['id'])) . ' ' . ASISTAN_DEVAM, 'menu');
            }
            if ($durum['numara_deneme'] < 2) {
                $durum['adim'] = 'numara';
                asistan_arama_guncelle($arama, $durum);
                return asistan_yanit($arama, 'Bu numarayla bir sipariş bulamadım. Siparişte kayıtlı numarayı başında sıfır olmadan tekrar tuşlayın.', 'numara');
            }
            $arama['ozet'] .= ' · numara bulunamadı';
            asistan_geri_ara($arama, $durum, '');
            return asistan_yanit($arama, 'Kaydınızı bulamadım. Numaranızı not aldım, mağazamız sizi geri arayacak. ' . $kapanis, 'bitti', true);
        }
        $durum['adim'] = 'menu';   // numara yerine başka bir şey söyledi → menüye düş
    }

    $niyet = asistan_niyet($metin, $tus);
    if ($niyet === 'siparis') {
        $durum['anlasilmadi'] = 0;
        if (!empty($arama['customer_id'])) {
            $arama['ozet'] .= ' · durum tekrar';
            asistan_arama_guncelle($arama, $durum, ['sonuc' => 'bilgi']);
            return asistan_yanit($arama, asistan_durum_metni(asistan_siparisler((int) $arama['customer_id'])) . ' ' . ASISTAN_DEVAM, 'menu');
        }
        $durum['adim'] = 'numara';
        asistan_arama_guncelle($arama, $durum);
        return asistan_yanit($arama, 'Siparişinizde kayıtlı cep telefonu numarasını, başında sıfır olmadan tuşlayın ya da söyleyin.', 'numara');
    }
    if ($niyet === 'adres') {
        $durum['anlasilmadi'] = 0;
        $arama['ozet'] .= ' · adres/saat';
        asistan_arama_guncelle($arama, $durum, ['sonuc' => ($arama['sonuc'] ?? '') === 'basladi' ? 'bilgi' : ($arama['sonuc'] ?? 'bilgi')]);
        return asistan_yanit($arama, asistan_adres_metni() . ' ' . ASISTAN_DEVAM, 'menu');
    }
    if ($niyet === 'not') {
        $durum['adim'] = 'not';
        asistan_arama_guncelle($arama, $durum);
        return asistan_yanit($arama, 'Mağazamız şu anda telefona çıkamıyor. Adınızı ve konuyu kısaca söyleyin, sizi en kısa sürede arayalım.', 'not');
    }
    if ($niyet === 'bitir') {
        asistan_arama_guncelle($arama, $durum, ['sonuc' => ($arama['sonuc'] ?? 'basladi') === 'basladi' ? 'kapandi' : $arama['sonuc']]);
        return asistan_yanit($arama, $kapanis, 'bitti', true);
    }

    // Anlaşılamadı
    $durum['anlasilmadi']++;
    if ($durum['anlasilmadi'] >= 2) {
        $arama['ozet'] .= ' · anlaşılamadı';
        asistan_geri_ara($arama, $durum, $metin !== '' ? 'Asistan anlayamadı: "' . $metin . '"' : '');
        return asistan_yanit($arama, 'Sizi anlayamadım. Numaranızı not aldım, mağazamız sizi geri arayacak. ' . $kapanis, 'bitti', true);
    }
    asistan_arama_guncelle($arama, $durum);
    return asistan_yanit($arama, 'Kusura bakmayın, anlayamadım. ' . ASISTAN_MENU, 'menu');
}

/** Uygulama aramanın bittiğini bildirir (süre, hata). */
function asistan_bitti(array $arama, int $sure, string $olay = ''): void
{
    $ek = ['sure' => max(0, min(3600, $sure))];
    if (($arama['sonuc'] ?? '') === 'basladi') {
        $ek['sonuc'] = 'kapandi';
    }
    if ($olay !== '') {
        $arama['ozet'] = trim(($arama['ozet'] ?? '') . ' · ' . mb_substr($olay, 0, 120));
    }
    asistan_arama_guncelle($arama, $arama['durum'] ?? [], $ek);
}

/* ---------------- Cihaz eşleştirme ---------------- */

/** Yeni 6 haneli eşleştirme kodu (15 dk geçerli; ayarlarda yalnızca özeti tutulur). */
function asistan_eslesme_kodu_uret(): string
{
    $kod = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    setting_set('asistan_eslesme', hash('sha256', $kod) . '|' . (time() + ASISTAN_ESLESME_DK * 60));
    return $kod;
}

/** Kod doğruysa cihazı kaydeder ve kalıcı anahtarı döndürür (kod tek kullanımlıktır). */
function asistan_bagla(string $kod, string $cihazAdi): ?string
{
    $kayit = explode('|', setting('asistan_eslesme', ''));
    if (count($kayit) !== 2 || (int) $kayit[1] < time() || !preg_match('/^\d{6}$/', $kod) || !hash_equals($kayit[0], hash('sha256', $kod))) {
        return null;
    }
    setting_set('asistan_eslesme', '');
    $anahtar = bin2hex(random_bytes(20));
    insert('asistan_cihazlar', [
        'ad' => mb_substr(trim($cihazAdi) !== '' ? trim($cihazAdi) : 'Android telefon', 0, 80),
        'anahtar_ozet' => hash('sha256', $anahtar), 'aktif' => 1,
        'created_at' => date('Y-m-d H:i:s'), 'son_gorulme' => date('Y-m-d H:i:s'),
    ]);
    return $anahtar;
}

function asistan_cihaz_dogrula(string $anahtar): ?array
{
    if (!preg_match('/^[a-f0-9]{40}$/', $anahtar)) {
        return null;
    }
    $c = row('SELECT * FROM asistan_cihazlar WHERE anahtar_ozet = ? AND aktif = 1', [hash('sha256', $anahtar)]);
    if ($c) {
        update('asistan_cihazlar', ['son_gorulme' => date('Y-m-d H:i:s')], 'id = ?', [(int) $c['id']]);
    }
    return $c;
}

/** Menü rozeti: bekleyen geri aramalar */
function asistan_rozet(): int
{
    try {
        return (int) scalar('SELECT COUNT(*) FROM asistan_aramalar WHERE geri_ara = 1 AND tamamlandi_at IS NULL');
    } catch (Throwable $e) {
        return 0;
    }
}

function asistan_sonuc_etiketi(string $s): array
{
    return match ($s) {
        'bilgi'   => ['Bilgi verildi', 'green'],
        'not'     => ['Geri aranacak', 'amber'],
        'kapandi' => ['Kapandı', 'gray'],
        default   => ['Yarım kaldı', 'gray'],
    };
}
