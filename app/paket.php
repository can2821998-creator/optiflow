<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow Lite / Pro paketleri (4.11.0)

   KURAL: Pro özellik, yalnızca
     1) mağazanın paketi 'pro' ise (merkez panelden atanır; sunucuda, her istekte
        merkez veritabanından taze okunur — tarayıcıdan değiştirilemez), VE
     2) istek OptiFlow Pro masaüstü uygulamasından geliyorsa (is_optiflow_desktop())
   açıktır.

   (1) asıl kilittir. (2) yalnızca ürün ayrımıdır: kullanıcı aracısı taklit
   edilebildiği için tek başına bir güvenlik sınırı DEĞİLDİR; ancak zaten Pro
   paketi olan bir mağazanın içindeki bir ayrımdır, ücretli kilidi aşmaya yaramaz.

   Lite kullanıcı Pro özellikleri menüde kilitli görür; tıklayınca pro.php
   tanıtım sayfası açılır.
   ========================================================================== */

const PAKETLER = ['lite' => 'OptiFlow Lite', 'pro' => 'OptiFlow Pro'];

/** Pro özellikleri: anahtar → tanıtım bilgisi. Yeni Pro özellik eklemek için buraya satır ekleyin. */
function pro_ozellikleri(): array
{
    return [
        'sgk_kopru' => [
            'ad'     => 'SGK / Medula reçete aktarımı',
            'kisa'   => 'Medula\'daki reçete tek tuşla siparişe.',
            'ikon'   => 'download',
            'sayfa'  => 'sgk-aktar.php',
            'madde'  => [
                'Medula, OptiFlow Pro uygulamasının içinde açılır; tarayıcı eklentisi gerekmez.',
                'Sferik, silendirik, aks ve ADD değerleri kutuların içinden doğru okunur.',
                'Reçete önce önizlemede gösterilir; siz onaylamadan siparişe yazılmaz.',
                'SGK kullanıcı adı ve şifreniz okunmaz, saklanmaz.',
            ],
        ],
        'uts' => [
            'ad'     => 'ÜTS karekod',
            'kisa'   => 'ÜTS listesinden okutulabilir karekod etiketleri ve imha bildirimi.',
            'ikon'   => 'print',
            'sayfa'  => 'uts-karekod.php',
            'madde'  => [
                'ÜTS stok/bildirim listesini (XLS, XLSX, CSV, XML) okur.',
                'Medula\'ya okutulabilir karekodlarla A4 etiket PDF\'i hazırlar.',
                'SKT geçmiş ürünler için ÜTS toplu imha dosyası üretir.',
                'Dosyanız sunucuya gönderilmez; tüm işlem bilgisayarınızda yapılır.',
            ],
        ],
    ];
}

/** Oturumdaki mağazanın paketi. Bilinmeyen değer Lite sayılır (varsayılan kapalı). */
function magaza_paketi(): string
{
    $p = (string) (tenant_oturum()['surum'] ?? 'lite');
    return isset(PAKETLER[$p]) ? $p : 'lite';
}

function magaza_pro_mu(): bool
{
    return magaza_paketi() === 'pro';
}

/**
 * Bir Pro özelliğin bu istekteki durumu:
 *   'acik'             — Pro paket + masaüstü uygulaması
 *   'paket_gerekli'    — mağaza Lite
 *   'masaustu_gerekli' — mağaza Pro ama tarayıcıdan girilmiş
 */
function pro_ozellik_durumu(string $ozellik): string
{
    if (!isset(pro_ozellikleri()[$ozellik])) {
        return 'paket_gerekli'; // bilinmeyen özellik: kapalı
    }
    if (!magaza_pro_mu()) {
        return 'paket_gerekli';
    }
    return is_optiflow_desktop() ? 'acik' : 'masaustu_gerekli';
}

function pro_ozellik_acik(string $ozellik): bool
{
    return pro_ozellik_durumu($ozellik) === 'acik';
}

/** Sayfa başında: özellik kapalıysa tanıtım sayfasına gönderir. */
function pro_gereksin(string $ozellik): void
{
    if (!pro_ozellik_acik($ozellik)) {
        redirect('pro.php?ozellik=' . rawurlencode($ozellik));
    }
}

/** Masaüstü API'si ve sayfalar için özet: hangi Pro özellik açık. */
function pro_ozellik_haritasi(): array
{
    $h = [];
    foreach (array_keys(pro_ozellikleri()) as $k) {
        $h[$k] = pro_ozellik_acik($k);
    }
    return $h;
}
