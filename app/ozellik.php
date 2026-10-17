<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Özellik anahtarları (4.12.0)

   Yeni modüller mağaza bazında, MERKEZ PANELİNDEN tek tek açılır/kapatılır.
   Açık özellikler merkez veritabanında magazalar.ozellikler sütununda
   (JSON dizi) tutulur ve her istekte tenant_gereksin() ile TAZE okunur;
   tarayıcıdan değiştirilemez. Varsayılan: KAPALI.

   'masaustu' => true olan özellikler ayrıca OptiFlow Pro masaüstü
   uygulamasından girilmesini ister (ör. barkod okuyucu, çevrimdışı mod).

   Lite/Pro paket kuralları (paket.php: SGK aktarımı, ÜTS) değişmedi.

   4.32.0: 'askida' => true olan özellik ASKIDADIR: hiçbir mağazada açık sayılmaz (menü, sayfa, uç nokta kapanır),
   merkez panelinde listelenmez. Mağazaların kayıtlı seçimi silinmez; askı kalkınca kaldığı yerden devam eder.
   ========================================================================== */

/** Yönetilebilir özellikler. Yeni özellik eklemek için buraya satır ekleyin. */
function ozellik_tanimlari(): array
{
    return [
        'whatsapp' => [
            'ad'       => 'WhatsApp mesaj otomasyonu',
            'kisa'     => 'Gözlük hazır, hatırlatma, yorum isteği ve lens mesajları kuyruktan gider.',
            'masaustu' => false,
        ],
        'odeme_linki' => [
            'ad'       => 'PayTR ödeme linki',
            'kisa'     => 'Kalan bakiye için müşteriye kartla ödeme linki; ödeme gelince bakiye kendiliğinden kapanır.',
            'masaustu' => false,
        ],
        'lens_takip' => [
            'ad'       => 'Kontakt lens takibi',
            'kisa'     => 'Lens kutusu bitmeden müşteriye tekrar sipariş hatırlatması.',
            'masaustu' => false,
        ],
        // 4.37.0: 'cam_siparis' kaldırıldı — cam sipariş fişi her mağazada, "Cam siparişleri · depo" (stock.php) içinde.
        'kagitsiz' => [
            'ad'       => 'Kâğıtsız tezgâh',
            'kisa'     => 'Tezgâh: e-reçete no → Medula (Pro) → tek ekranda müşteri, cam, çerçeve, kapora → yarım sayfa fiş + müşteri nüshası; gün sonunda eksik camlar tek tuşla depoya WhatsApp ile (müşteri bilgisi olmadan).',
            'masaustu' => false,
        ],
        'stok_oneri' => [
            'ad'       => 'Stok satın alma önerisi',
            'kisa'     => 'Eksik camlar ve kritik seviyedeki çerçevelerden tedarikçi bazında sipariş listesi.',
            'masaustu' => false,
        ],
        'efatura' => [
            'ad'       => 'e-Arşiv / e-Fatura (hazırlık)',
            'kisa'     => 'Siparişten fatura taslağı ve UBL-TR 1.2 belge. GİB\'e gönderim entegratör bağlanınca açılır.',
            'masaustu' => false,
        ],
        'sgk_mutabakat' => [
            'ad'       => 'SGK mutabakat ve bekleyen reçeteler',
            'kisa'     => 'Aktarılan reçeteler, SGK siparişleri ve Medula listesinde henüz aktarılmamış reçeteler.',
            'masaustu' => false,
        ],
        'cam_hata' => [
            'ad'       => 'Hatalı cam / yeniden yapım kaydı',
            'kisa'     => 'Yeniden yapılan camların sebebi, maliyeti, laboratuvardan iade alacağı ve aylık rapor; tedarikçi karnesinde hata oranı.',
            'masaustu' => false,
        ],
        'hizli_satis' => [
            'ad'       => 'Hızlı satış ve ürün kataloğu',
            'kisa'     => 'Güneş gözlüğü, aksesuar, solüsyon ve lensi sipariş açmadan barkodla sat; parçalı ödeme, fiş, stok düşümü. Kasa, rapor ve prime yansır.',
            'masaustu' => false,
        ],
        'garanti' => [
            'ad'       => 'Garanti kaydı ve garanti kartı',
            'kisa'     => 'Teslimde kalem bazında garanti; karekodlu garanti kartı (müşteri telefonundan kalan süreyi görür), garanti talepleri, tamir geçmişi ve tedarikçiye gönderim.',
            'masaustu' => false,
        ],
        'sgk_hak' => [
            'ad'       => 'SGK hak kontrolü',
            'kisa'     => 'Müşterinin SGK gözlük hak tarihi siparişte görünür; Medula / e-Devlet hak ekranı OptiFlow Pro\'da "Aktar" ile ya da yapıştırarak doğrulanır.',
            'masaustu' => false,
        ],
        'tedarik_finans' => [
            'ad'       => 'Alış faturası ve senet takibi',
            'kisa'     => 'Tedarikçi e-Fatura XML\'ini yükleyince stok, maliyet ve cari kendiliğinden işlenir; verilen senetler ve vade takvimi.',
            'masaustu' => false,
        ],
        'uts_bildirim' => [
            'ad'       => 'ÜTS bildirimleri',
            'kisa'     => 'Mal kabul (alma), ücretli satışta tüketiciye verme, iade, imha ve tedarikçiye iade bildirimleri; karekodla satış. SGK\'lı satışlarda bildirim Medula\'ya bırakılır.',
            'masaustu' => false,
        ],
        'barkod' => [
            'ad'       => 'Barkod / karekod okuyucu modu',
            'kisa'     => 'USB okuyucuyla ÜTS karekodu, çerçeve barkodu veya sipariş fişi okutunca ilgili kayıt açılır.',
            'masaustu' => true,
        ],
        'cevrimdisi' => [
            'ad'       => 'Çevrimdışı salt okunur mod',
            'kisa'     => 'İnternet kesilince açık siparişler ve müşteri telefonları bilgisayarda şifreli kopyadan görüntülenir.',
            'masaustu' => true,
        ],
        // 4.30.0 — mağazanın Android telefonundaki "OptiFlow Asistan" uygulaması (app/asistan.php)
        // 4.32.0 — kullanıcı kararı (12.10): şimdilik askıda; menüde ve merkez panelinde görünmez.
        'telefon_asistan' => [
            'ad'       => 'Telefon asistanı',
            'kisa'     => 'Mağaza telefonu çalarken arayanın sipariş durumu ekranda; açılamayan aramaya otomatik SMS ve geri aranacaklar listesi (Android uygulaması).',
            'masaustu' => false,
            'askida'   => true,
        ],
        // 4.20.3 — telefon/tarayıcı (PWA) karşılığı: assets/pwa.js kopyayı tarayıcıda şifreli tutar, offline.html gösterir
        'cevrimdisi_tel' => [
            'ad'       => 'Telefonda çevrimdışı kopya',
            'kisa'     => 'İnternet kesilince açık siparişler ve müşteri telefonları telefonda/tarayıcıda şifreli kopyadan görüntülenir. Kopya en çok 24 saat geçerlidir; çıkışta silinir.',
            'masaustu' => false,
        ],
    ];
}

/** Özellik askıda mı? (Askıdaki özellik her mağazada kapalı sayılır, panelde listelenmez.) */
function ozellik_askida(string $k): bool
{
    return !empty(ozellik_tanimlari()[$k]['askida']);
}

/** Merkez panelinde açılıp kapatılabilen özellikler (askıdakiler hariç). */
function ozellik_yonetilenler(): array
{
    return array_filter(ozellik_tanimlari(), static fn(array $t) => empty($t['askida']));
}

/** JSON/dizi → geçerli, tekrarsız özellik anahtarları. Bilinmeyenler atılır. */
function ozellik_listesi_temizle(mixed $ham): array
{
    if (is_string($ham)) {
        $ham = $ham === '' ? [] : json_decode($ham, true);
    }
    if (!is_array($ham)) {
        return [];
    }
    $tanim = ozellik_tanimlari();
    $temiz = [];
    foreach ($ham as $k) {
        if (is_string($k) && isset($tanim[$k]) && !in_array($k, $temiz, true)) {
            $temiz[] = $k;
        }
    }
    return $temiz;
}

/** Oturumdaki mağazada merkezin açtığı özellikler. */
function magaza_ozellikleri(): array
{
    return ozellik_listesi_temizle(tenant_oturum()['ozellikler'] ?? []);
}

/**
 * Bu istekteki durum:
 *   'acik'             — merkez açmış (ve gerekiyorsa masaüstünden girilmiş)
 *   'kapali'           — merkez açmamış / bilinmeyen özellik
 *   'masaustu_gerekli' — açık ama yalnızca OptiFlow Pro masaüstünde çalışır
 */
function ozellik_durumu(string $k): string
{
    $tanim = ozellik_tanimlari()[$k] ?? null;
    if (!$tanim || !empty($tanim['askida']) || !in_array($k, magaza_ozellikleri(), true)) {
        return 'kapali';
    }
    if ($tanim['masaustu'] && !is_optiflow_desktop()) {
        return 'masaustu_gerekli';
    }
    return 'acik';
}

function ozellik_acik(string $k): bool
{
    return ozellik_durumu($k) === 'acik';
}

/** Sayfa/uç nokta başında: kapalıysa açıklayıcı sayfa (JSON API'de JSON 403). */
function ozellik_gereksin(string $k): void
{
    $d = ozellik_durumu($k);
    if ($d === 'acik') {
        return;
    }
    $ad = ozellik_tanimlari()[$k]['ad'] ?? $k;
    if (!empty($GLOBALS['__json_api'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'kod' => 'ozellik_kapali', 'hata' => $ad . ' bu mağazada açık değil.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    render_error_page(
        $ad,
        $d === 'masaustu_gerekli'
            ? 'Bu özellik yalnızca OptiFlow Pro masaüstü uygulamasında çalışır. Uygulamayı optiflow.com.tr/indir.php adresinden indirebilirsiniz.'
            : 'Bu özellik mağazanızda henüz açılmamış. Açtırmak için OptiFlow destek ile iletişime geçin.'
    );
}

/** Masaüstü API'si için: anahtar → açık mı. */
function ozellik_haritasi(): array
{
    $h = [];
    foreach (array_keys(ozellik_tanimlari()) as $k) {
        $h[$k] = ozellik_acik($k);
    }
    return $h;
}

/* ---------------- Merkez tarafı ---------------- */

function merkez_magaza_ozellikleri(array $magaza): array
{
    return ozellik_listesi_temizle($magaza['ozellikler'] ?? '');
}

/** Tek özelliği aç/kapat. */
function merkez_ozellik_ayarla(int $magazaId, string $k, bool $acik): void
{
    if (!isset(ozellik_tanimlari()[$k])) {
        throw new DomainException('Bilinmeyen özellik.');
    }
    if (ozellik_askida($k)) {
        throw new DomainException('Bu özellik şimdilik askıda.');
    }
    $m = merkez_row('SELECT ozellikler FROM magazalar WHERE id = ?', [$magazaId]);
    if (!$m) {
        throw new DomainException('Mağaza bulunamadı.');
    }
    $liste = merkez_magaza_ozellikleri($m);
    $liste = array_values(array_filter($liste, static fn($x) => $x !== $k));
    if ($acik) {
        $liste[] = $k;
    }
    merkez_q('UPDATE magazalar SET ozellikler = ?, guncelleme = NOW() WHERE id = ?', [json_encode($liste), $magazaId]);
}

/** Tüm listeyi birden kaydet (detay sayfasındaki onay kutuları). */
function merkez_ozellikleri_kaydet(int $magazaId, array $acikAnahtarlar): void
{
    // Askıdaki özellikler formda yok: mağazanın kayıtlı seçimi korunur, formdan gelen değer yok sayılır
    $eski = merkez_row('SELECT ozellikler FROM magazalar WHERE id = ?', [$magazaId]);
    $askida = array_filter($eski ? merkez_magaza_ozellikleri($eski) : [], 'ozellik_askida');
    $form = array_filter(ozellik_listesi_temizle(array_values($acikAnahtarlar)), static fn($k) => !ozellik_askida($k));
    $liste = array_values(array_unique(array_merge($form, $askida)));
    merkez_q('UPDATE magazalar SET ozellikler = ?, guncelleme = NOW() WHERE id = ?', [json_encode($liste), $magazaId]);
}
