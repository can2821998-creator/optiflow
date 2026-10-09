<?php
declare(strict_types=1);

/* ==========================================================================
   4.21.0 — Katalogdan teklif (quotes.tip = 'katalog')
   --------------------------------------------------------------------------
   Akış: müşteri seçilir/kaydedilir → çerçeve (stoktan, elle ya da müşterinin kendi) → 1–3 alternatif cam
   (Ayarlar › Cam kataloğu; marka, ad, tasarım, indeks, kaplama, çift fiyatı) → Medula (SGK) payı →
   iskonto % → döküm (yazdır / WhatsApp) → seçilen seçenekle siparişe çevir.

   Hesap (kullanıcı kararı 06.10: önce SGK, sonra iskonto):
       ara      = cam + çerçeve
       sgk      = min(SGK payı, ara)
       kalan    = ara − sgk
       iskonto  = kalan × oran / 100
       ödenecek = kalan − iskonto
   Siparişe çevrilince: orders.total_amount = sgk + ödenecek, orders.sgk_amount = sgk (bakiye = ödenecek).

   İskonto sınırı: personel en çok setting('teklif_iskonto_max', '10') %; süper yetkili sınırsız (en çok %100).
   Cam ve çerçeve bilgisi teklif anında kopyalanır: katalog fiyatı sonradan değişse de teklif değişmez.
   Taşınabilir SQL (testler SQLite'ta).

   4.26.0 — Bir teklifte 1–3 gözlük (ör. uzak + yakın): gözlük 1 quotes satırında (adı gozluk_ad), 2. ve 3. gözlük
   quote_gozlukler'de; her gözlüğün kendi çerçevesi, cam seçenekleri, kullanım şekli ve SGK payı vardır, iskonto ve not
   teklif genelidir. Her gözlük ayrı siparişe çevrilir (atölyede ayrı iş). Siparişe dönmemiş teklif düzenlenebilir.
   ========================================================================== */

const TEKLIF_SECENEK_SAYISI = 3;
const TEKLIF_GOZLUK_SAYISI = 3;

/** Adı yazılmamış gözlüğün adı: tek gözlükse "Gözlük", birden çoksa sırayla uzak / yakın / 3. */
function teklif_gozluk_varsayilan_ad(int $sira, int $adet): string
{
    if ($adet <= 1) {
        return 'Gözlük';
    }
    return [1 => 'Uzak gözlük', 2 => 'Yakın gözlük'][$sira] ?? $sira . '. gözlük';
}

/** Seçenek başına hesap. Tutarlar kuruşa yuvarlanır. */
function teklif_hesapla(float $cam, float $cerceve, float $sgk, float $oran): array
{
    $ara = round(max(0.0, $cam) + max(0.0, $cerceve), 2);
    $sgk = round(min(max(0.0, $sgk), $ara), 2);
    $kalan = round($ara - $sgk, 2);
    $oran = min(max(0.0, $oran), 100.0);
    $iskonto = round($kalan * $oran / 100, 2);
    return [
        'cam'      => round(max(0.0, $cam), 2),
        'cerceve'  => round(max(0.0, $cerceve), 2),
        'ara'      => $ara,
        'sgk'      => $sgk,
        'kalan'    => $kalan,
        'oran'     => $oran,
        'iskonto'  => $iskonto,
        'odenecek' => round($kalan - $iskonto, 2),
    ];
}

/** Bu kullanıcının girebileceği en yüksek iskonto oranı (%). */
function teklif_iskonto_siniri(): float
{
    if (is_super()) {
        return 100.0;
    }
    $s = (float) str_replace(',', '.', setting('teklif_iskonto_max', '10'));
    return min(max(0.0, $s), 100.0);
}

/** Teklifte seçilebilecek aktif cam ürünleri (tasarıma göre gruplu sırada). */
function teklif_cam_urunleri(): array
{
    return rows('SELECT * FROM lens_products WHERE is_active = 1 ORDER BY design, tier, brand, name');
}

/** Ürünün teklifte görünen adı ve özellik satırı. */
function teklif_cam_etiketi(array $p): array
{
    // Odak tipi · hammadde · indeks · yüzey · kaplamalar (4.21.0 katalog detayları); not ayrı (4.21.1)
    $ozellik = array_values(array_filter([
        teklif_tasarim_adi((string) $p['design']),
        function_exists('lens_hammaddeleri') ? (lens_hammaddeleri()[(string) ($p['hammadde'] ?? '')] ?? null) : null,
        $p['lens_index'] ? 'İndeks ' . $p['lens_index'] : null,
        function_exists('lens_yuzeyleri') ? (lens_yuzeyleri()[(string) ($p['yuzey'] ?? '')] ?? null) : null,
        $p['coating'] ?: null,
    ]));
    // Katalogda ad çoğu zaman markayla başlıyor ("Nikon" + "Nikon Presio First"): marka tekrar yazılmaz
    $marka = trim((string) $p['brand']);
    $urunAd = trim((string) $p['name']);
    return [
        'ad'      => $marka !== '' && !str_starts_with(mb_strtolower($urunAd), mb_strtolower($marka)) ? $marka . ' ' . $urunAd : $urunAd,
        'ozellik' => implode(' · ', $ozellik),
        'not'     => trim((string) ($p['note'] ?? '')),   // dökümde özellik etiketi sayılmaz
    ];
}

/** Katalog tasarım anahtarları (lens_products.design) → okunur ad. */
function teklif_tasarim_adi(string $d): string
{
    if (function_exists('lens_odak_tipleri')) {
        return lens_odak_tipleri()[$d] ?? $d;
    }
    return [
        'tek_odak' => 'Tek odak', 'progressive' => 'Progressive', 'ofis' => 'Ofis / ara mesafe', 'bifokal' => 'Bifokal',
    ][$d] ?? $d;
}

/** Ürün tasarımından SGK kullanım şekli (reçete lens_design anahtarı) önerisi. */
function teklif_kullanim_onerisi(string $urunTasarim): string
{
    return match ($urunTasarim) {
        'progressive' => 'progressive',
        'ofis'        => 'ofis',
        'bifokal'     => 'bifokal',
        default       => 'tek_odak_uzak',
    };
}

/** Kullanım şekline göre tahmini SGK payı (Ayarlar › SGK taban tutarı × uzak/yakın). */
function teklif_sgk_tahmini(string $kullanim): float
{
    return function_exists('sgk_katki_tahmini') ? sgk_katki_tahmini(['lens_design' => $kullanim]) : 0.0;
}

/**
 * Bir gözlüğün çerçeve, cam seçenekleri ve SGK alanlarını doğrular. $g: düz alanlar (gözlük 1 için formun kökü,
 * ek gözlükler için ek[n]); $on: hata iletilerinin başı ("Yakın gözlük: " ya da tek gözlükte boş).
 * Alanlar: cerceve_tur (stok|elle|kendi), frame_item_id, frame_desc, frame_price ; urun[1..3], cam_fiyat[1..3],
 * secenek_ad[1..3] ; sgk_var, lens_design, sgk_amount
 */
function teklif_gozluk_oku(array $g, string $on = ''): array
{
    $p = static fn(string $k, string $d = ''): string => is_string($g[$k] ?? null) ? trim($g[$k]) : $d;
    $dizi = static fn(string $k): array => is_array($g[$k] ?? null) ? $g[$k] : [];
    $para = static function (string $ham, string $alan) use ($on): ?float {
        if (trim($ham) === '') {
            return null;
        }
        $v = parse_money($ham);
        if ($v === null || $v < 0) {
            throw new DomainException($on . $alan . ' geçersiz. Örnek: 4.250,00');
        }
        return $v;
    };

    // --- Çerçeve ---
    $cerceveTur = $p('cerceve_tur', 'stok');
    $frameItemId = null;
    $frameDesc = '';
    $framePrice = 0.0;
    if ($cerceveTur === 'stok') {
        $fid = (int) ($g['frame_item_id'] ?? 0);
        if ($fid <= 0) {
            throw new DomainException($on . 'Stoktan bir çerçeve seçin ya da "Elle yaz" / "Müşterinin kendi çerçevesi"ni seçin.');
        }
        $f = row('SELECT * FROM frame_items WHERE id = ? AND is_active = 1', [$fid]);
        if (!$f) {
            throw new DomainException($on . 'Seçilen çerçeve stokta bulunamadı.');
        }
        $frameItemId = $fid;
        $frameDesc = frame_item_label($f);
        $fiyat = $para($p('frame_price'), 'Çerçeve fiyatı');
        $framePrice = $fiyat ?? (float) ($f['price'] ?? 0);
    } elseif ($cerceveTur === 'elle') {
        $frameDesc = mb_substr($p('frame_desc'), 0, 255);
        if ($frameDesc === '') {
            throw new DomainException($on . 'Çerçeve açıklamasını yazın (örn. Ray-Ban RB5154 52□21).');
        }
        $framePrice = $para($p('frame_price'), 'Çerçeve fiyatı') ?? 0.0;
    } elseif ($cerceveTur === 'kendi') {
        $frameDesc = 'Müşterinin kendi çerçevesi';
    } else {
        throw new DomainException($on . 'Çerçeve seçimi geçersiz.');
    }

    // --- Cam seçenekleri ---
    $urunler = $dizi('urun');
    $fiyatlar = $dizi('cam_fiyat');
    $adlar = $dizi('secenek_ad');
    $secenekler = [];
    for ($i = 1; $i <= TEKLIF_SECENEK_SAYISI; $i++) {
        $pid = (int) ($urunler[$i] ?? 0);
        if ($pid <= 0) {
            continue;
        }
        $u = row('SELECT * FROM lens_products WHERE id = ? AND is_active = 1', [$pid]);
        if (!$u) {
            throw new DomainException($on . 'Seçenek ' . $i . ': cam katalogda bulunamadı ya da pasif.');
        }
        $fiyat = $para(is_string($fiyatlar[$i] ?? null) ? $fiyatlar[$i] : '', 'Seçenek ' . $i . ' cam fiyatı');
        if ($fiyat === null) {
            $fiyat = $u['price'] !== null ? (float) $u['price'] : null;
        }
        if ($fiyat === null) {
            throw new DomainException($on . 'Seçenek ' . $i . ': "' . trim($u['brand'] . ' ' . $u['name']) . '" için katalogda fiyat yok; fiyatı yazın.');
        }
        $et = teklif_cam_etiketi($u);
        $baslik = mb_substr(trim(is_string($adlar[$i] ?? null) ? $adlar[$i] : ''), 0, 60);
        $secenekler[] = [
            'baslik' => $baslik !== '' ? $baslik : (product_tiers()[$u['tier']] ?? 'Seçenek ' . (count($secenekler) + 1)),
            'urun'   => $u,
            'ad'     => mb_substr($et['ad'], 0, 160),
            'desc'   => mb_substr($et['ozellik'], 0, 400),
            'not'    => mb_substr($et['not'], 0, 90),
            'fiyat'  => $fiyat,
        ];
    }
    if (!$secenekler) {
        throw new DomainException($on . 'Katalogdan en az bir cam seçin.');
    }

    // --- SGK ---
    $kullanim = $p('lens_design', 'tek_odak_uzak');
    if (function_exists('lens_designs') && !isset(lens_designs()[$kullanim])) {
        throw new DomainException($on . 'Kullanım şekli geçersiz.');
    }
    $sgk = 0.0;
    if (($g['sgk_var'] ?? '') === '1') {
        $sgk = $para($p('sgk_amount'), 'Medula (SGK) payı') ?? teklif_sgk_tahmini($kullanim);
    }

    // quotes / quote_gozlukler sütunları
    $sutun = [
        'frame_item_id' => $frameItemId,
        'frame_desc'    => $frameDesc,
        'frame_price'   => round($framePrice, 2),
        'lens_design'   => $kullanim,
        'sgk_amount'    => round($sgk, 2),
    ];
    foreach (range(1, TEKLIF_SECENEK_SAYISI) as $i) {
        $s = $secenekler[$i - 1] ?? null;
        $sutun["opt{$i}_name"] = $s['baslik'] ?? null;
        // satır 1: cam adı · satır 2: özellikler · satır 3: katalog notu (varsa)
        $sutun["opt{$i}_desc"] = $s ? $s['ad'] . "\n" . $s['desc'] . ($s['not'] !== '' ? "\n" . $s['not'] : '') : null;
        $sutun["opt{$i}_price"] = $s ? round($s['fiyat'], 2) : null;
        $sutun["opt{$i}_product_id"] = $s ? (int) $s['urun']['id'] : null;
    }
    return ['sutun' => $sutun, 'secenek' => count($secenekler)];
}

/** Düzenleme ekranı için kayıtlı katalog teklifini form alanlarına çevirir (teklif_kaydet'in beklediği biçim). */
function teklif_form_degerleri(array $q): array
{
    $tl = static fn($x): string => $x === null ? '' : number_format((float) $x, 2, ',', '.');
    $gozluk = static function (array $r) use ($tl): array {
        $tur = !empty($r['frame_item_id']) ? 'stok' : ((string) $r['frame_desc'] === 'Müşterinin kendi çerçevesi' ? 'kendi' : 'elle');
        $v = [
            'cerceve_tur'   => $tur,
            'frame_item_id' => $tur === 'stok' ? (string) (int) $r['frame_item_id'] : '',
            'frame_desc'    => $tur === 'elle' ? (string) $r['frame_desc'] : '',
            'frame_price'   => $tur === 'kendi' ? '' : $tl($r['frame_price']),
            'lens_design'   => (string) ($r['lens_design'] ?: 'tek_odak_uzak'),
            'sgk_var'       => (float) $r['sgk_amount'] > 0 ? '1' : '',
            'sgk_amount'    => (float) $r['sgk_amount'] > 0 ? $tl($r['sgk_amount']) : '',
            'urun' => [], 'cam_fiyat' => [], 'secenek_ad' => [],
        ];
        $n = 1;
        for ($i = 1; $i <= TEKLIF_SECENEK_SAYISI; $i++) {
            if (empty($r["opt{$i}_name"])) {
                continue;
            }
            $v['urun'][$n] = (string) (int) $r["opt{$i}_product_id"];
            $v['cam_fiyat'][$n] = $tl($r["opt{$i}_price"]);
            $v['secenek_ad'][$n] = (string) $r["opt{$i}_name"];
            $n++;
        }
        return $v;
    };
    $oran = (float) $q['discount_rate'];
    $g = $gozluk($q) + [
        'customer_id'   => (string) (int) $q['customer_id'],
        'gozluk_ad'     => (string) ($q['gozluk_ad'] ?? ''),
        'discount_rate' => $oran > 0 ? rtrim(rtrim(number_format($oran, 2, ',', ''), '0'), ',') : '',
        'note'          => (string) ($q['note'] ?? ''),
        'ek'            => [],
    ];
    foreach (rows('SELECT * FROM quote_gozlukler WHERE quote_id = ? ORDER BY sira', [(int) $q['id']]) as $e) {
        $g['ek'][(int) $e['sira']] = ['aktif' => '1', 'ad' => (string) $e['ad']] + $gozluk($e);
    }
    return $g;
}

/** Teklif herhangi bir gözlüğüyle siparişe dönmüş mü? (Dönmüşse düzenlenemez.) */
function teklif_siparise_donmus(array $q): bool
{
    return !empty($q['converted_order_id'])
        || (int) scalar('SELECT COUNT(*) FROM quote_gozlukler WHERE quote_id = ? AND converted_order_id IS NOT NULL', [(int) $q['id']]) > 0;
}

/**
 * Formdan teklifi doğrular ve kaydeder; $duzenleId verilirse o teklifi günceller. Döner: teklif no.
 * Hata: DomainException (kullanıcıya gösterilir).
 * $g: customer_id | first_name, last_name, phone ; gözlük 1 alanları (bkz. teklif_gozluk_oku) + gozluk_ad ;
 *     ek[2..3][aktif=1, ad, …gözlük alanları] ; discount_rate ; note
 */
function teklif_kaydet(array $g, array $kullanici, int $duzenleId = 0): int
{
    $p = static fn(string $k, string $d = ''): string => is_string($g[$k] ?? null) ? trim($g[$k]) : $d;

    // --- Düzenleme ---
    $eski = null;
    if ($duzenleId > 0) {
        $eski = row('SELECT * FROM quotes WHERE id = ?', [$duzenleId]);
        if (!$eski || ($eski['tip'] ?? 'serbest') !== 'katalog') {
            throw new DomainException('Düzenlenecek teklif bulunamadı.');
        }
        if (teklif_siparise_donmus($eski)) {
            throw new DomainException('Bu teklif siparişe dönmüş; düzenlenemez. Gerekirse yeni teklif hazırlayın.');
        }
    }

    // --- Müşteri ---
    $musteriId = (int) ($g['customer_id'] ?? 0);
    $musteri = $musteriId > 0 ? find_customer($musteriId) : null;
    if ($musteriId > 0 && !$musteri) {
        throw new DomainException('Seçilen müşteri bulunamadı.');
    }
    if (!$musteri) {
        $ad = tr_title($p('first_name'));
        $soyad = tr_title($p('last_name'));
        if ($ad === '' || $soyad === '') {
            throw new DomainException('Müşteri seçin ya da yeni müşterinin adını ve soyadını girin.');
        }
        $tel = normalize_phone($p('phone'));
        if ($tel === null) {
            throw new DomainException('Telefon numarası geçersiz. Örnek: 0532 111 22 33');
        }
    }

    // --- Gözlükler: 1 (formun kökü) + etkin ek gözlükler (ek[2], ek[3]) ---
    $ekler = [];
    foreach (is_array($g['ek'] ?? null) ? $g['ek'] : [] as $n => $e) {
        if ((int) $n >= 2 && (int) $n <= TEKLIF_GOZLUK_SAYISI && is_array($e) && ($e['aktif'] ?? '') === '1') {
            $ekler[(int) $n] = $e;
        }
    }
    ksort($ekler);
    $adet = 1 + count($ekler);
    $adYaz = static fn($ham, int $sira): string => mb_substr(trim(is_string($ham) ? $ham : ''), 0, 40) ?: teklif_gozluk_varsayilan_ad($sira, $adet);
    $ad1 = $adYaz($g['gozluk_ad'] ?? '', 1);
    $gozlukler = [1 => ['ad' => $ad1] + teklif_gozluk_oku($g, $adet > 1 ? $ad1 . ': ' : '')];
    $sira = 1;
    foreach ($ekler as $e) {
        $sira++;
        $ad = $adYaz($e['ad'] ?? '', $sira);
        $gozlukler[$sira] = ['ad' => $ad] + teklif_gozluk_oku($e, $ad . ': ');
    }

    // --- İskonto ---
    $oranHam = str_replace(['%', ' '], '', $p('discount_rate'));
    $oran = $oranHam === '' ? 0.0 : (float) str_replace(',', '.', $oranHam);
    if (!is_numeric(str_replace(',', '.', $oranHam === '' ? '0' : $oranHam)) || $oran < 0 || $oran > 100) {
        throw new DomainException('İskonto oranı 0 ile 100 arasında olmalı.');
    }
    $sinir = teklif_iskonto_siniri();
    if ($oran > $sinir + 0.0001) {
        throw new DomainException('İskonto yetkiniz en çok %' . rtrim(rtrim(number_format($sinir, 2, ',', ''), '0'), ',') . '. Daha yüksek iskonto için yöneticinize başvurun.');
    }

    return transaction(function () use ($musteri, $p, $kullanici, $gozlukler, $adet, $oran, $eski, $g): int {
        if ($musteri) {
            $cid = (int) $musteri['id'];
        } else {
            $ad = tr_title($p('first_name'));
            $soyad = tr_title($p('last_name'));
            $tel = (string) normalize_phone($p('phone'));
            // Aynı telefon + aynı ad soyad zaten kayıtlıysa yeni müşteri açılmaz (order-new.php ile aynı kural).
            $cid = $tel !== '' ? (int) scalar('SELECT id FROM customers WHERE phone = ? AND first_name = ? AND last_name = ? LIMIT 1', [$tel, $ad, $soyad]) : 0;
            if (!$cid) {
                $cid = insert('customers', ['first_name' => $ad, 'last_name' => $soyad, 'phone' => $tel, 'created_by' => $kullanici['id'], 'created_at' => date('Y-m-d H:i:s')]);
                audit('customer_create', 'customer', $cid, ['ad' => "$ad $soyad", 'kaynak' => 'teklif']);
            }
            $musteri = find_customer($cid);
        }
        $adElle = trim(is_string($g['gozluk_ad'] ?? null) ? $g['gozluk_ad'] : '') !== '';
        $veri = [
            'tip'            => 'katalog',
            'customer_id'    => $cid,
            'customer_name'  => mb_substr(trim($musteri['first_name'] . ' ' . $musteri['last_name']), 0, 160),
            'customer_phone' => $musteri['phone'] ?: null,
            'note'           => mb_substr($p('note'), 0, 255) ?: null,
            'gozluk_ad'      => $adet > 1 || $adElle ? $gozlukler[1]['ad'] : null,
            'discount_rate'  => round($oran, 2),
        ] + $gozlukler[1]['sutun'];
        if ($eski) {
            $id = (int) $eski['id'];
            update('quotes', $veri + ['secilen' => null, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $kullanici['id']], 'id = ?', [$id]);
            q('DELETE FROM quote_gozlukler WHERE quote_id = ?', [$id]);
        } else {
            $id = insert('quotes', $veri + ['created_by' => $kullanici['id'], 'created_at' => date('Y-m-d H:i:s')]);
        }
        foreach ($gozlukler as $sira => $gz) {
            if ($sira === 1) {
                continue;
            }
            insert('quote_gozlukler', ['quote_id' => $id, 'sira' => $sira, 'ad' => $gz['ad']] + $gz['sutun']);
        }
        $ozet = ['müşteri' => $veri['customer_name'], 'gözlük' => $adet, 'seçenek' => array_sum(array_column($gozlukler, 'secenek')), 'iskonto %' => $oran,
            'sgk' => array_sum(array_map(static fn($gz) => $gz['sutun']['sgk_amount'], $gozlukler))];
        audit($eski ? 'quote_update' : 'quote_create', 'quote', $id, $ozet);
        return $id;
    });
}

/** Katalog teklifinin seçenekleri, her biri hesabıyla. Eski (serbest) teklifte opt fiyatı toplam fiyattır. */
function teklif_secenekleri(array $q): array
{
    $katalog = ($q['tip'] ?? 'serbest') === 'katalog';
    $liste = [];
    for ($i = 1; $i <= TEKLIF_SECENEK_SAYISI; $i++) {
        if (empty($q["opt{$i}_name"])) {
            continue;
        }
        $satirlar = explode("\n", (string) ($q["opt{$i}_desc"] ?? ''), 3);
        $s = [
            'no'      => $i,
            'baslik'  => (string) $q["opt{$i}_name"],
            'cam'     => $katalog ? $satirlar[0] : '',
            'ozellik' => $katalog ? ($satirlar[1] ?? '') : (string) ($q["opt{$i}_desc"] ?? ''),
            'not'     => $katalog ? ($satirlar[2] ?? '') : '',
            'fiyat'   => $q["opt{$i}_price"] !== null ? (float) $q["opt{$i}_price"] : null,
        ];
        if ($katalog) {
            $s['hesap'] = teklif_hesapla((float) $s['fiyat'], (float) $q['frame_price'], (float) $q['sgk_amount'], (float) $q['discount_rate']);
        }
        $liste[] = $s;
    }
    return $liste;
}

/**
 * Katalog teklifinin gözlükleri (gözlük 1 quotes satırından, 2–3 quote_gozlukler'den), her biri seçenekleri ve
 * hesabıyla: [sira, ad, frame_item_id, frame_desc, frame_price, lens_design, sgk_amount, secilen, converted_order_id, secenekler].
 */
function teklif_gozlukleri(array $q): array
{
    if (($q['tip'] ?? 'serbest') !== 'katalog') {
        return [];
    }
    $ekler = !empty($q['id']) ? rows('SELECT * FROM quote_gozlukler WHERE quote_id = ? ORDER BY sira', [(int) $q['id']]) : [];
    $adet = 1 + count($ekler);
    $alanlar = ['frame_item_id', 'frame_desc', 'frame_price', 'lens_design', 'sgk_amount', 'secilen', 'converted_order_id'];
    $liste = [];
    $g1 = ['sira' => 1, 'ad' => (string) (($q['gozluk_ad'] ?? '') ?: teklif_gozluk_varsayilan_ad(1, $adet))];
    foreach ($alanlar as $a) {
        $g1[$a] = $q[$a] ?? null;
    }
    $liste[] = $g1 + ['secenekler' => teklif_secenekleri($q)];
    foreach ($ekler as $e) {
        $gz = ['sira' => (int) $e['sira'], 'ad' => (string) $e['ad']];
        foreach ($alanlar as $a) {
            $gz[$a] = $e[$a] ?? null;
        }
        // seçenek hesabı teklifin iskontosuyla
        $liste[] = $gz + ['secenekler' => teklif_secenekleri(['tip' => 'katalog', 'discount_rate' => $q['discount_rate']] + $e)];
    }
    return $liste;
}

/** Gözlüklerin en uygun ve en kapsamlı seçeneklerle toplam ödenecek tutarı: [en_az, en_cok]. */
function teklif_toplam_aralik(array $gozlukler): array
{
    $az = 0.0;
    $cok = 0.0;
    foreach ($gozlukler as $gz) {
        $tutarlar = array_map(static fn($s) => $s['hesap']['odenecek'], $gz['secenekler']);
        if ($tutarlar) {
            $az += min($tutarlar);
            $cok += max($tutarlar);
        }
    }
    return [round($az, 2), round($cok, 2)];
}

/** Siparişe çevirirken ön dolum: seçilen gözlüğün seçilen seçeneği → tutar, SGK payı, çerçeve ve not. Katalog teklifi değilse null. */
function teklif_siparis_on_dolum(array $q, int $secenek, int $gozluk = 1): ?array
{
    if (($q['tip'] ?? 'serbest') !== 'katalog') {
        return null;
    }
    $gozlukler = teklif_gozlukleri($q);
    $gz = null;
    foreach ($gozlukler as $aday) {
        if ($aday['sira'] === $gozluk) {
            $gz = $aday;
        }
    }
    if (!$gz) {
        return null;
    }
    foreach ($gz['secenekler'] as $s) {
        if ($s['no'] !== $secenek) {
            continue;
        }
        $h = $s['hesap'];
        $not = 'Teklif #' . (int) $q['id'] . (count($gozlukler) > 1 ? ' · ' . $gz['ad'] : '') . ' · ' . $s['baslik'] . ': ' . $s['cam'] . ($s['ozellik'] !== '' ? ' (' . $s['ozellik'] . ')' : '')
            . "\nCam " . money($h['cam']) . ' + çerçeve ' . money($h['cerceve'])
            . ($h['sgk'] > 0 ? ' − SGK ' . money($h['sgk']) : '')
            . ($h['iskonto'] > 0 ? ' − iskonto %' . rtrim(rtrim(number_format($h['oran'], 2, ',', ''), '0'), ',') . ' (' . money($h['iskonto']) . ')' : '')
            . ' = müşteriden ' . money($h['odenecek']);
        return [
            'total_amount'  => round($h['sgk'] + $h['odenecek'], 2),   // bakiye = toplam − SGK − ödenen = ödenecek
            'sgk_amount'    => $h['sgk'],
            'frame_item_id' => $gz['frame_item_id'] ? (int) $gz['frame_item_id'] : null,
            'frame_info'    => (string) $gz['frame_desc'],
            'notes'         => $not,
            'gozluk_ad'     => $gz['ad'],
            'lens_design'   => (string) $gz['lens_design'],
        ];
    }
    return null;
}

/** WhatsApp ile gönderilecek döküm metni. */
function teklif_whatsapp_metni(array $q, string $magaza): string
{
    $m = 'Merhaba ' . $q['customer_name'] . ",\nfiyat teklifimiz:\n";
    if (($q['tip'] ?? 'serbest') === 'katalog') {
        $gozlukler = teklif_gozlukleri($q);
        $sgkVar = false;
        foreach ($gozlukler as $gz) {
            if (count($gozlukler) > 1) {
                $m .= "\n*" . mb_strtoupper($gz['ad'], 'UTF-8') . "*";
            }
            $m .= "\nÇerçeve: " . $gz['frame_desc'] . ((float) $gz['frame_price'] > 0 ? ' — ' . money($gz['frame_price']) : '') . "\n";
            $sgkVar = $sgkVar || (float) $gz['sgk_amount'] > 0;
            foreach ($gz['secenekler'] as $s) {
                $h = $s['hesap'];
                $m .= "\n• " . $s['baslik'] . ': ' . $s['cam'] . ($s['ozellik'] !== '' ? "\n  " . $s['ozellik'] : '')
                    . "\n  Cam " . money($h['cam']) . ' + çerçeve ' . money($h['cerceve']) . ' = ' . money($h['ara'])
                    . ($h['sgk'] > 0 ? "\n  SGK (Medula) payı −" . money($h['sgk']) : '')
                    . ($h['iskonto'] > 0 ? "\n  İskonto %" . rtrim(rtrim(number_format($h['oran'], 2, ',', ''), '0'), ',') . ' −' . money($h['iskonto']) : '')
                    . "\n  *Ödenecek: " . money($h['odenecek']) . "*\n";
            }
        }
        if (count($gozlukler) > 1) {
            [$az, $cok] = teklif_toplam_aralik($gozlukler);
            $m .= "\n*Toplam (" . count($gozlukler) . ' gözlük): ' . ($az === $cok ? money($az) : money($az) . ' – ' . money($cok)) . "*\n";
        }
        if ($sgkVar) {
            $m .= "\nSGK payı tahminidir; kesin tutar Medula'da reçete işlenince belli olur.\n";
        }
    } else {
        foreach (teklif_secenekleri($q) as $s) {
            $m .= "\n• " . $s['baslik'] . ($s['fiyat'] !== null ? ' — ' . money($s['fiyat']) : '') . ($s['ozellik'] !== '' ? "\n  " . $s['ozellik'] : '') . "\n";
        }
    }
    return $m . "\n" . $magaza;
}

/* ---------- 4.21.1 Teklif dökümü (app/partials/teklif-dokum.php) ---------- */

const TEKLIF_GECERLILIK_GUN = 15;

/** Seçeneğin özellik satırı ("Tek odak · MR-8 · İndeks 1.60 · Antirefle, Blue") → etiketler (kaplamalar ayrı ayrı). */
function teklif_ozellik_etiketleri(string $ozellik): array
{
    $etiket = [];
    // Adında " · " geçen odak tipleri ("Tek odak · yakın destekli (yorgunluk)") bölünmeden tek etiket kalır
    $bilesik = array_filter(
        array_merge(array_keys(teklif_ozellik_sozlugu()), function_exists('lens_odak_tipleri') ? array_values(lens_odak_tipleri()) : []),
        static fn($ad) => str_contains($ad, ' · ')
    );
    $koru = [];
    foreach ($bilesik as $k => $ad) {
        if (str_contains($ozellik, $ad)) {
            $koru["\x01$k\x01"] = $ad;
            $ozellik = str_replace($ad, "\x01$k\x01", $ozellik);
        }
    }
    foreach (array_filter(array_map(static fn($p) => strtr(trim($p), $koru), explode(' · ', $ozellik))) as $parca) {
        if (in_array($parca, $koru, true)) {
            $etiket[] = $parca;   // bileşik ad: kaplama listesi gibi virgülle bölünmez
            continue;
        }
        foreach (array_filter(array_map('trim', explode(',', $parca))) as $p) {
            $etiket[] = $p;
        }
    }
    return array_values(array_unique($etiket));
}

/** Müşterinin anlayacağı dilde kısa açıklamalar (dökümde yalnızca teklifte geçenler gösterilir). */
function teklif_ozellik_sozlugu(): array
{
    return [
        // Kaplamalar
        'Antirefle'                    => 'Camdaki yansımaları azaltır; gece araba farları ve ekranlarda parlama olmaz, gözleriniz daha net görünür.',
        'Süper antirefle (hidrofobik)' => 'Yansıma önlemenin yanında su, yağ ve tozu iter; cam daha az kirlenir, kolay silinir.',
        'Blue (mavi ışık)'             => 'Bilgisayar, telefon ve LED ışığındaki mavi ışığın bir kısmını süzer; uzun ekran kullanımında göz yorgunluğunu azaltır.',
        'UV420'                        => 'Güneşin zararlı UV ışınlarını ve yüksek enerjili mavi ışığı 420 nm\'ye kadar keser.',
        'Drive (gece sürüş)'           => 'Gece karşıdan gelen far parlamasını azaltır; araç kullananlar için daha konforlu görüş sağlar.',
        'Fotokromik'                   => 'Güneşte koyulaşır, kapalı alanda şeffaflaşır; ayrı güneş gözlüğü taşıma ihtiyacını azaltır.',
        'Polarize'                     => 'Yol, su ve kar yüzeyinden yansıyan parlamayı keser; güneş altında kontrast artar.',
        'Sert kaplama'                 => 'Cam yüzeyini çizilmelere karşı güçlendirir; gözlük daha uzun süre yeni gibi kalır.',
        'Ayna (mirror)'                => 'Dış yüzeyde ayna etkisi verir; güneşte göz konforunu artırır ve şık bir görünüm sağlar.',
        'Buğu önleyici'                => 'Maske takarken ya da sıcak-soğuk ortam değişiminde camın buğulanmasını azaltır.',
        // Odak tipleri
        'Tek odak'                              => 'Tek bir mesafe (uzak ya da yakın) için net görüş sağlayan klasik cam.',
        'Tek odak · yakın destekli (yorgunluk)' => 'Uzağı net gösterirken alt kısmındaki hafif destekle yakın çalışmada gözü dinlendirir.',
        'Tek odak · miyopi kontrol'             => 'Çocuklarda miyopinin ilerleme hızını yavaşlatmaya yardımcı özel tasarım.',
        'Progressive (çok odaklı)'              => 'Tek camda uzak, ara ve yakın mesafeyi kesintisiz birleştirir; gözlük değiştirmeye gerek kalmaz.',
        'Ofis / ara mesafe'                     => 'Masa başı ve ekran mesafesine (ara mesafe) özel geniş görüş alanı sunar.',
        'Bifokal'                               => 'Üst kısmı uzak, alt kısmı yakın için iki ayrı bölgeli cam.',
        // Hammaddeler
        'Organik (CR-39)'     => 'Hafif ve optik kalitesi yüksek standart plastik cam.',
        'Polikarbonat'        => 'Darbeye çok dayanıklı ve hafif; çocuk ve spor gözlükleri için idealdir.',
        'Trivex'              => 'Hem çok hafif hem darbeye dayanıklı; netlik kalitesi yüksektir.',
        'MR-8 (inceltilmiş)'  => 'İnce, hafif ve dayanıklı; orta ve yüksek numaralarda estetik sonuç verir.',
        'MR-7'                => 'Yüksek numaralarda daha ince cam sağlayan dayanıklı hammadde.',
        'MR-174 (ultra ince)' => 'En ince plastik cam hammaddesi; çok yüksek numaralar için.',
        'Mineral (cam)'       => 'Çizilmeye çok dayanıklı gerçek cam; plastik camlara göre daha ağırdır.',
        // Yüzeyler
        'Asferik'             => 'Daha düz yüzey tasarımı; cam daha ince görünür, kenarlarda bozulma azalır.',
        'Çift asferik'        => 'İki yüzü de asferik; en geniş net görüş alanı ve en ince görünüm.',
        'Free-form (dijital)' => 'Bilgisayarla noktasal işlenmiş kişisel yüzey; kenarlara kadar keskin görüş.',
    ];
}

/** Teklifte geçen özelliklerin açıklamaları (indeks ayrıca): [etiket => açıklama]. */
function teklif_dokum_sozluk(array $secenekler): array
{
    $sozluk = teklif_ozellik_sozlugu();
    $cikti = [];
    $indeksVar = false;
    foreach ($secenekler as $s) {
        foreach (teklif_ozellik_etiketleri((string) $s['ozellik']) as $e) {
            if (isset($sozluk[$e])) {
                $cikti[$e] = $sozluk[$e];
            } elseif (str_starts_with($e, 'İndeks ')) {
                $indeksVar = true;
            }
        }
    }
    if ($indeksVar) {
        $cikti['Kırılma indeksi'] = 'Sayı büyüdükçe cam incelir ve hafifler: 1.50 standart · 1.60 ince · 1.67 çok ince · 1.74 en ince.';
    }
    return $cikti;
}
