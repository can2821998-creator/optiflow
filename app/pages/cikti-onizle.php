<?php
/* 4.53.0 — Çıktı görünümü önizlemesi (Ayarlar › Çıktı görünümü'ndeki çerçeve). Örnek verilerle yarım A4 fiş ya da
   A4 döküm basar; seçenekler adres satırından gelir (kaydedilmez). Gerçek müşteri verisi kullanılmaz. */
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

$v = [];
foreach (array_keys(cikti_varsayilan()) as $k) {
    $v[$k] = isset($_GET[$k]) ? (string) $_GET[$k] : cikti_ayar()[$k];
}
cikti_ayar($v);   // yalnız bu istek için
$_GET['onizleme'] = '1';
$tarih = date('d.m.Y');
$hazir = date('d.m.Y', strtotime('+3 days'));

if (query('tur') === 'dokum') {
    echo dokum_sayfa_bas('Önizleme · Sipariş fişi');
    echo dokum_bas([
        'etiket' => 'Gözlük sipariş fişi', 'no' => '#00128', 'tarih' => $tarih, 'kucuk' => 'Sayın', 'baslik' => 'Ayşe Yılmaz',
        'rozet' => ['', 'saat', 'Hazırlanıyor'], 'metin' => 'Gözlüğünüz <b>' . e($hazir) . '</b> tarihinde hazır olacak.',
        'rota' => [['Sipariş', $tarih, 'bugün'], ['Teslim', $hazir, '3 gün sonra']],
        'yol' => [['Sipariş alındı', $tarih, 'bitti'], ['Cam siparişi', 'depoya iletildi', 'bitti'], ['Atölye', 'montaj', 'simdi'], ['Teslim', $hazir, 'bekliyor']],
        'alanlar' => [['cerceve', 'Çerçeve', 'Ray-Ban RB5154'], ['goz', 'Cam', 'Progresif 1.6'], ['sgk', 'SGK', 'Raporlu'], ['takvim', 'Teslim', $hazir]],
        'vurgu' => ['Kalan', '1.450,00 ₺', 'teslimde ödenir'],
    ]);
    echo '<div class="iki">' . dokum_kutu('Hesap', dokum_hesap([['Çerçeve', '2.400,00 ₺'], ['Camlar', '3.100,00 ₺'], ['SGK payı', '−150,00 ₺', 'eksi'], ['Alınan', '3.900,00 ₺', 'ara']]))
        . dokum_kutu('Not', '<p>Örnek belge: gerçek siparişlerinizde müşteri ve sipariş bilgileri burada görünür.</p>') . '</div>';
    echo dokum_son('<p><b>Bu bir önizlemedir.</b> Renk, başlık bandı, yazı ve işaret Ayarlar › Çıktı görünümü\'nden.</p>');
    echo dokum_sayfa_son();
    exit;
}

$musteri = '<div>'
    . fis_kutu('01', 'Yapılacak', '<p><b>Ray-Ban RB5154 · siyah</b></p><ul class="fis-islem"><li>Vida ve menteşe</li><li>Burunluk değişimi</li></ul><p class="not">Sol sap gevşek, burunluk sarardı.</p>')
    . fis_kutu('02', 'Teslim alırken görülen', fis_isaretler(['cizik' => 'Camda çizik', 'kirik' => 'Kırık', 'boya' => 'Boya atması', 'egri' => 'Eğrilik'], ['cizik']), 'bosluk-ust')
    . '</div><div>'
    . fis_vurgu('Ne zaman hazır', $hazir, date_long(date('Y-m-d', strtotime('+3 days'))), 'koyu')
    . fis_vurgu('Ücret', '350,00 ₺', 'Teslimde ödenir')
    . '<p class="fis-kural">Gözlüğünüzü <b>bu fişle</b> teslim alırsınız.</p>'
    . '</div>';
$magaza = '<div>'
    . '<div class="fis-serit"><b>T-0042</b><span><strong>Ayşe Yılmaz</strong>0532 000 00 00</span></div>'
    . fis_kutu('01', 'İşlemler', '<ul class="fis-islem"><li>Vida ve menteşe</li><li>Burunluk değişimi</li></ul>', 'bosluk-ust')
    . '</div><div>'
    . fis_vurgu('Söz verilen', $hazir, 'Önizleme', 'koyu')
    . fis_imzalar([['Teslim eden', 'personel'], ['Teslim alan', 'müşteri']])
    . '</div>';
fis_sayfa(['baslik' => 'Önizleme · Hizmet fişi', 'etiket' => 'Hizmet fişi', 'no' => 'T-0042', 'tarih' => $tarih, 'musteri' => $musteri, 'magaza' => $magaza]);
