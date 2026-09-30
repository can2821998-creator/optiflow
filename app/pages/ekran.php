<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_login();

/* ==========================================================================
   Atölye ekranı — bilgisayarda ayrı bir sekme/pencere olarak açılan canlı iş listesi.

   Sipariş sayfasındaki Atölye panosu › "Ayrı ekranda aç" ile yeni sekmede açılır;
   ikinci monitöre taşınıp tam ekran yapılabilir. Normal giriş oturumuyla çalışır
   (gizli bağlantı yoktur). Sayfa kendini 45 saniyede bir tazeler; bu istekler
   oturumu da açık tutar.
   Ekranda fiyat, telefon, reçete YOKTUR: yalnızca sipariş no, ad baş harfleri,
   aşama ve teslim günü. Görünüm: app/partials/ekran-gorunum.php + assets/ekran.css
   ========================================================================== */

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$shop   = setting('shop_name', 'OptiFlow');
$goster = true;   // adları gizlemek ekrandaki düğmeyle yapılır (tarayıcıda hatırlanır)
$bugun  = date('Y-m-d');
$yarin  = date('Y-m-d', strtotime($bugun . ' +1 day'));
$enFazla = 30;  // sütun başına tarayıcıya gönderilen en fazla satır; ekrana sığanı JS gösterir, gerisi "+N daha"

$aylar  = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
           7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
$ayKisa = [1 => 'Oca', 2 => 'Şub', 3 => 'Mar', 4 => 'Nis', 5 => 'May', 6 => 'Haz',
           7 => 'Tem', 8 => 'Ağu', 9 => 'Eyl', 10 => 'Eki', 11 => 'Kas', 12 => 'Ara'];
$gunler = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];

$saat        = date('H:i');
$tarihMetni  = $gunler[(int) date('N')] . ', ' . (int) date('j') . ' ' . $aylar[(int) date('n')];
$guncelleme  = date('H:i:s');

/* Teslim günü: "3 gün gecikti" / "Bugün" / "Yarın" / "26 Eyl" */
$vade = static function (?string $tarih) use ($bugun, $ayKisa): array {
    if (!$tarih || str_starts_with($tarih, '0000')) {
        return ['', ''];
    }
    $t = DateTimeImmutable::createFromFormat('Y-m-d', substr($tarih, 0, 10));
    if (!$t) {
        return ['', ''];
    }
    $fark = (int) (new DateTimeImmutable($bugun))->diff($t->setTime(0, 0))->format('%r%a');
    if ($fark < 0) {
        return [abs($fark) . ' gün gecikti', 'late'];
    }
    if ($fark === 0) {
        return ['Bugün', 'today'];
    }
    if ($fark === 1) {
        return ['Yarın', 'soon'];
    }
    return [(int) $t->format('j') . ' ' . $ayKisa[(int) $t->format('n')], ''];
};

/* "Ayşe K." — ayar kapalıysa boş (yalnızca sipariş no görünür) */
$adiGoster = static function (array $r) use ($goster): string {
    if (!$goster) {
        return '';
    }
    $ad = trim((string) ($r['first_name'] ?? ''));
    $so = trim((string) ($r['last_name'] ?? ''));
    return $ad . ($so !== '' ? ' ' . mb_substr($so, 0, 1) . '.' : '');
};

$satir = static function (array $r, string $alt = '') use ($adiGoster, $vade): array {
    [$vm, $vs] = $vade($r['promised_date'] ?? null);
    return ['no' => order_no((int) $r['id']), 'ad' => $adiGoster($r), 'alt' => $alt, 'vade' => $vm, 'vsinif' => $vs];
};

$ilkAd = static function (?string $tamAd): string {
    $parca = explode(' ', trim((string) $tamAd));
    return $parca[0] ?? '';
};

$kolon = static function (string $baslik, string $ton, array $satirlar) use ($enFazla): array {
    return [
        'baslik'   => $baslik,
        'ton'      => $ton,
        'sayi'     => count($satirlar),
        'satirlar' => array_slice($satirlar, 0, $enFazla),
        'fazla'    => max(0, count($satirlar) - $enFazla),
    ];
};

$hata       = '';
$kolonlar   = [];
$ozet       = [];
$tamirSatir = '';

try {
    /* 1) Cam bekliyor */
    $camSatirlar = [];
    foreach (rows(
        "SELECT o.id, o.promised_date, c.first_name, c.last_name,
                (SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id
                  WHERE r.order_id = o.id AND i.stock_status <> 'stokta_var') AS missing
           FROM orders o JOIN customers c ON c.id = o.customer_id
          WHERE o.order_stage IN ('siparis_verildi','bekliyor','rx_siparis_verildi') AND o.transaction_type <> 'tamir'
          ORDER BY o.promised_date IS NULL, o.promised_date ASC, o.created_at ASC LIMIT 200"
    ) as $r) {
        $eksik = (int) $r['missing'];
        $camSatirlar[] = $satir($r, $eksik > 0 ? $eksik . ' cam eksik' : '');
    }

    /* 2) Montajda */
    $montajSatirlar = [];
    foreach (rows(
        "SELECT o.id, o.promised_date, o.own_frame_pending, c.first_name, c.last_name, u.full_name AS usta
           FROM orders o JOIN customers c ON c.id = o.customer_id
           LEFT JOIN user_accounts u ON u.id = o.assigned_to
          WHERE o.order_stage = 'atolyede' AND (o.workshop_stage IS NULL OR o.workshop_stage = 'montajda') AND o.transaction_type <> 'tamir'
          ORDER BY o.promised_date IS NULL, o.promised_date ASC, o.created_at ASC LIMIT 200"
    ) as $r) {
        $alt = [];
        if ($ilkAd($r['usta']) !== '') {
            $alt[] = $ilkAd($r['usta']);
        }
        if ((int) $r['own_frame_pending'] === 1) {
            $alt[] = 'çerçeve bekleniyor';
        }
        $montajSatirlar[] = $satir($r, implode(' · ', $alt));
    }

    /* 3) Kalite kontrol */
    $kontrolSatirlar = [];
    foreach (rows(
        "SELECT o.id, o.promised_date, c.first_name, c.last_name, u.full_name AS usta
           FROM orders o JOIN customers c ON c.id = o.customer_id
           LEFT JOIN user_accounts u ON u.id = o.assigned_to
          WHERE o.order_stage = 'atolyede' AND o.workshop_stage = 'kontrol' AND o.transaction_type <> 'tamir'
          ORDER BY o.promised_date IS NULL, o.promised_date ASC, o.created_at ASC LIMIT 200"
    ) as $r) {
        $kontrolSatirlar[] = $satir($r, $ilkAd($r['usta']));
    }

    /* 4) Teslime hazır (en uzun bekleyen üstte) */
    $hazirSatirlar = [];
    foreach (rows(
        "SELECT o.id, o.updated_at, c.first_name, c.last_name
           FROM orders o JOIN customers c ON c.id = o.customer_id
          WHERE o.order_stage = 'hazirlandi' AND o.transaction_type <> 'tamir'
          ORDER BY o.updated_at ASC LIMIT 200"
    ) as $r) {
        $ts = strtotime((string) $r['updated_at']);
        $gun = $ts ? (int) floor((strtotime($bugun) - strtotime(date('Y-m-d', $ts))) / 86400) : 0;
        $hazirSatirlar[] = $satir($r + ['promised_date' => null], $gun >= 1 ? $gun . ' gündür bekliyor' : 'bugün hazır oldu');
    }

    $kolonlar = [
        $kolon('Cam bekliyor', 'amber', $camSatirlar),
        $kolon('Montajda', 'blue', $montajSatirlar),
        $kolon('Kalite kontrol', 'violet', $kontrolSatirlar),
        $kolon('Teslime hazır', 'green', $hazirSatirlar),
    ];

    /* Özet kutuları (tamir dahil tüm açık işler) */
    $acik = "('siparis_verildi','bekliyor','rx_siparis_verildi','atolyede')";
    $bugunTeslim = (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage IN $acik AND promised_date = ?", [$bugun]);
    $geciken     = (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage IN $acik AND promised_date >= '2000-01-01' AND promised_date < ?", [$bugun]);
    $teslimEdilen = (int) scalar(
        "SELECT COUNT(*) FROM orders WHERE order_stage = 'teslim_edildi' AND delivered_at >= ? AND delivered_at < ?",
        [$bugun . ' 00:00:00', $yarin . ' 00:00:00']
    );

    $ozet = [
        ['deger' => (string) (count($montajSatirlar) + count($kontrolSatirlar)), 'etiket' => 'Atölyede', 'sinif' => ''],
        ['deger' => (string) $bugunTeslim, 'etiket' => 'Bugün teslim edilecek', 'sinif' => $bugunTeslim > 0 ? 'warn' : ''],
        ['deger' => (string) $geciken, 'etiket' => 'Geciken', 'sinif' => $geciken > 0 ? 'bad' : 'good'],
        ['deger' => (string) count($hazirSatirlar), 'etiket' => 'Teslim bekleyen', 'sinif' => ''],
        ['deger' => (string) $teslimEdilen, 'etiket' => 'Bugün teslim edilen', 'sinif' => 'good'],
    ];

    /* Tamir / bakım şeridi */
    $sayi = ['alindi' => 0, 'onarim' => 0, 'hazir' => 0];
    foreach (rows(
        "SELECT order_stage, COUNT(*) AS n FROM orders
          WHERE transaction_type = 'tamir' AND order_stage IN ('siparis_verildi','bekliyor','rx_siparis_verildi','atolyede','hazirlandi')
          GROUP BY order_stage"
    ) as $r) {
        if ($r['order_stage'] === 'atolyede') {
            $sayi['onarim'] += (int) $r['n'];
        } elseif ($r['order_stage'] === 'hazirlandi') {
            $sayi['hazir'] += (int) $r['n'];
        } else {
            $sayi['alindi'] += (int) $r['n'];
        }
    }
    if ($sayi['alindi'] + $sayi['onarim'] + $sayi['hazir'] > 0) {
        $tamirSatir = 'Tamir / bakım: ' . $sayi['alindi'] . ' alındı · ' . $sayi['onarim'] . ' onarımda · ' . $sayi['hazir'] . ' hazır';
    }
} catch (Throwable $e) {
    if (function_exists('app_log')) {
        app_log('Atölye ekranı veri hatası: ' . $e->getMessage());
    }
    $hata = 'Bilgiler şu anda okunamıyor.';
}

include dirname(__DIR__) . '/partials/ekran-gorunum.php';
