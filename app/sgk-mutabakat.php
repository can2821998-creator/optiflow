<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — SGK mutabakat ve bekleyen reçeteler (4.12.0)

   1) Dönem özeti: ayın aktarılan reçeteleri, SGK katkılı siparişleri,
      siparişe dönüşmemiş aktarımlar, e-reçete numarası eksik SGK siparişleri,
      teslim edilmemiş (henüz faturalanamayacak) SGK siparişleri.
   2) Medula listesiyle karşılaştırma: Medula'daki reçete listesinin metni
      (masaüstünde "Listeyi kontrol et" ile okunur ya da kopyala-yapıştır)
      OptiFlow'daki e-reçete numaralarıyla karşılaştırılır; Medula'da olup
      OptiFlow'a hiç aktarılmamış reçeteler "bekleyen" olarak listelenir.
      Liste metni SUNUCUDA SAKLANMAZ; yalnızca numaralar oturumda 30 dk tutulur.
   ========================================================================== */

/** Dönem sınırları: 'YYYY-MM' → [başlangıç, bitiş+1 gün]. */
function sgk_donem(string $ay): array
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $ay, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
        $ay = date('Y-m');
    }
    $bas = $ay . '-01';
    return [$ay, $bas, date('Y-m-d', strtotime($bas . ' +1 month'))];
}

function sgk_donem_ozeti(string $ay): array
{
    [$ay, $bas, $son] = sgk_donem($ay);
    $gelen = rows(
        "SELECT g.id, g.erecete, g.recete_tarihi, g.baslik, g.kaynak, g.created_at, g.used_order_id, u.full_name
           FROM sgk_incoming g LEFT JOIN user_accounts u ON u.id = g.user_id
          WHERE g.created_at >= ? AND g.created_at < ? ORDER BY g.id DESC LIMIT 500",
        [$bas, $son]
    );
    $siparisler = rows(
        "SELECT o.id, o.created_at, o.order_stage, o.delivered_at, o.sgk_amount, o.total_amount, o.sgk_erecete,
                c.first_name, c.last_name,
                (SELECT MAX(g.erecete) FROM sgk_incoming g WHERE g.used_order_id = o.id) AS gelen_erecete
           FROM orders o JOIN customers c ON c.id = o.customer_id
          WHERE o.order_stage <> 'iptal' AND (o.sgk_amount > 0 OR o.sgk_erecete IS NOT NULL)
            AND o.created_at >= ? AND o.created_at < ?
          ORDER BY o.id DESC LIMIT 500",
        [$bas, $son]
    );
    $ozet = ['aktarim' => count($gelen), 'kullanilmayan' => 0, 'siparis' => count($siparisler), 'sgk_toplam' => 0.0,
             'teslim_edilen' => 0, 'teslim_sgk' => 0.0, 'erecetesiz' => 0, 'bekleyen_teslim' => 0];
    foreach ($gelen as $g) {
        if (!$g['used_order_id']) {
            $ozet['kullanilmayan']++;
        }
    }
    foreach ($siparisler as &$o) {
        $o['erecete'] = $o['sgk_erecete'] ?: $o['gelen_erecete'];
        $ozet['sgk_toplam'] += (float) $o['sgk_amount'];
        if (!$o['erecete']) {
            $ozet['erecetesiz']++;
        }
        if ($o['order_stage'] === 'teslim_edildi') {
            $ozet['teslim_edilen']++;
            $ozet['teslim_sgk'] += (float) $o['sgk_amount'];
        } else {
            $ozet['bekleyen_teslim']++;
        }
    }
    unset($o);
    return ['ay' => $ay, 'gelen' => $gelen, 'siparisler' => $siparisler, 'ozet' => $ozet];
}

/** OptiFlow'da bilinen tüm e-reçete numaraları (büyük harf) → [no => ['gelen' => id|null, 'siparis' => id|null]]. */
function sgk_bilinen_receteler(): array
{
    $b = [];
    foreach (rows("SELECT id, erecete, used_order_id FROM sgk_incoming WHERE erecete IS NOT NULL AND erecete <> ''") as $r) {
        $k = strtoupper((string) $r['erecete']);
        $b[$k]['gelen'] = (int) $r['id'];
        $b[$k]['siparis'] = $b[$k]['siparis'] ?? ($r['used_order_id'] ? (int) $r['used_order_id'] : null);
    }
    foreach (rows("SELECT id, sgk_erecete FROM orders WHERE sgk_erecete IS NOT NULL AND sgk_erecete <> '' AND order_stage <> 'iptal'") as $r) {
        $k = strtoupper((string) $r['sgk_erecete']);
        $b[$k]['siparis'] = (int) $r['id'];
        $b[$k]['gelen'] = $b[$k]['gelen'] ?? null;
    }
    return $b;
}

/**
 * Medula liste metninden olası e-reçete numaraları.
 * e-Reçete numarası: 7 karakter, büyük harf ve rakam, en az bir rakam ve bir harf içerir (ör. 1A2B3C4).
 * Tarih, T.C. no, tutar gibi yalnız rakamlı değerler bu yüzden karışmaz.
 */
function sgk_liste_numaralari(string $metin): array
{
    $metin = mb_substr($metin, 0, 400000);
    preg_match_all('/(?<![0-9A-Za-zÇĞİÖŞÜçğıöşü])([0-9A-Z]{7})(?![0-9A-Za-zÇĞİÖŞÜçğıöşü])/u', $metin, $m);
    $sonuc = [];
    foreach ($m[1] as $t) {
        if (preg_match('/\d/', $t) && preg_match('/[A-Z]/', $t)) {
            $sonuc[$t] = true;
        }
    }
    return array_slice(array_keys($sonuc), 0, 1000);
}

/** Numaraları normalleştirir (yapısal okumadan gelen liste). */
function sgk_numara_listesi_temizle(array $liste): array
{
    $s = [];
    foreach ($liste as $n) {
        $n = strtoupper(trim((string) $n));
        if (preg_match('/^[0-9A-Z]{4,12}$/', $n) && preg_match('/\d/', $n)) {
            $s[$n] = true;
        }
    }
    return array_slice(array_keys($s), 0, 1000);
}

/** Karşılaştırma: ['var' => [no => bilgi], 'yok' => [no, …]]. */
function sgk_liste_karsilastir(array $numaralar): array
{
    $bilinen = sgk_bilinen_receteler();
    $var = [];
    $yok = [];
    foreach ($numaralar as $n) {
        if (isset($bilinen[$n])) {
            $var[$n] = $bilinen[$n];
        } else {
            $yok[] = $n;
        }
    }
    return ['var' => $var, 'yok' => $yok];
}

/** Karşılaştırma sonucunu oturumda tutar (liste metni değil, yalnızca numaralar). */
function sgk_kontrol_kaydet(array $numaralar, string $kaynak): void
{
    $_SESSION['sgk_kontrol'] = ['numaralar' => array_values($numaralar), 'zaman' => time(), 'kaynak' => $kaynak];
}

function sgk_kontrol_oku(): ?array
{
    $k = $_SESSION['sgk_kontrol'] ?? null;
    if (!is_array($k) || (time() - (int) ($k['zaman'] ?? 0)) > 1800) {
        return null;
    }
    return $k;
}

/**
 * Metinden karşılaştırılacak numaralar: e-reçete biçimine uyan adaylar + metinde geçen,
 * OptiFlow'da zaten kayıtlı numaralar (biçim kuralı dışında kalsalar bile).
 */
function sgk_metinden_numaralar(string $metin): array
{
    $adaylar = array_fill_keys(sgk_liste_numaralari($metin), true);
    $bilinen = sgk_bilinen_receteler();
    if ($bilinen) {
        preg_match_all('/[0-9A-Za-z]{4,12}/', mb_substr($metin, 0, 400000), $m);
        foreach (array_unique($m[0]) as $t) {
            $t = strtoupper($t);
            if (isset($bilinen[$t])) {
                $adaylar[$t] = true;
            }
        }
    }
    return array_slice(array_keys($adaylar), 0, 1000);
}
