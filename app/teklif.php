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
   ========================================================================== */

const TEKLIF_SECENEK_SAYISI = 3;

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
    // Odak tipi · hammadde · indeks · yüzey · kaplamalar · not (4.21.0 katalog detayları)
    $ozellik = array_values(array_filter([
        teklif_tasarim_adi((string) $p['design']),
        function_exists('lens_hammaddeleri') ? (lens_hammaddeleri()[(string) ($p['hammadde'] ?? '')] ?? null) : null,
        $p['lens_index'] ? 'İndeks ' . $p['lens_index'] : null,
        function_exists('lens_yuzeyleri') ? (lens_yuzeyleri()[(string) ($p['yuzey'] ?? '')] ?? null) : null,
        $p['coating'] ?: null,
        $p['note'] ?: null,
    ]));
    // Katalogda ad çoğu zaman markayla başlıyor ("Nikon" + "Nikon Presio First"): marka tekrar yazılmaz
    $marka = trim((string) $p['brand']);
    $urunAd = trim((string) $p['name']);
    return [
        'ad'      => $marka !== '' && !str_starts_with(mb_strtolower($urunAd), mb_strtolower($marka)) ? $marka . ' ' . $urunAd : $urunAd,
        'ozellik' => implode(' · ', $ozellik),
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
 * Formdan teklifi doğrular ve kaydeder. Döner: teklif no. Hata: DomainException (kullanıcıya gösterilir).
 * $g: customer_id | first_name, last_name, phone ; cerceve_tur (stok|elle|kendi), frame_item_id, frame_desc,
 *     frame_price ; urun[1..3], cam_fiyat[1..3], secenek_ad[1..3] ; sgk_var, lens_design, sgk_amount ;
 *     discount_rate ; note
 */
function teklif_kaydet(array $g, array $kullanici): int
{
    $p = static fn(string $k, string $d = ''): string => is_string($g[$k] ?? null) ? trim($g[$k]) : $d;
    $dizi = static fn(string $k): array => is_array($g[$k] ?? null) ? $g[$k] : [];
    $para = static function (string $ham, string $alan): ?float {
        if (trim($ham) === '') {
            return null;
        }
        $v = parse_money($ham);
        if ($v === null || $v < 0) {
            throw new DomainException($alan . ' geçersiz. Örnek: 4.250,00');
        }
        return $v;
    };

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

    // --- Çerçeve ---
    $cerceveTur = $p('cerceve_tur', 'stok');
    $frameItemId = null;
    $frameDesc = '';
    $framePrice = 0.0;
    if ($cerceveTur === 'stok') {
        $fid = (int) ($g['frame_item_id'] ?? 0);
        if ($fid <= 0) {
            throw new DomainException('Stoktan bir çerçeve seçin ya da "Elle yaz" / "Müşterinin kendi çerçevesi"ni seçin.');
        }
        $f = row('SELECT * FROM frame_items WHERE id = ? AND is_active = 1', [$fid]);
        if (!$f) {
            throw new DomainException('Seçilen çerçeve stokta bulunamadı.');
        }
        $frameItemId = $fid;
        $frameDesc = frame_item_label($f);
        $fiyat = $para($p('frame_price'), 'Çerçeve fiyatı');
        $framePrice = $fiyat ?? (float) ($f['price'] ?? 0);
    } elseif ($cerceveTur === 'elle') {
        $frameDesc = mb_substr($p('frame_desc'), 0, 255);
        if ($frameDesc === '') {
            throw new DomainException('Çerçeve açıklamasını yazın (örn. Ray-Ban RB5154 52□21).');
        }
        $framePrice = $para($p('frame_price'), 'Çerçeve fiyatı') ?? 0.0;
    } elseif ($cerceveTur === 'kendi') {
        $frameDesc = 'Müşterinin kendi çerçevesi';
    } else {
        throw new DomainException('Çerçeve seçimi geçersiz.');
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
            throw new DomainException('Seçenek ' . $i . ': cam katalogda bulunamadı ya da pasif.');
        }
        $fiyat = $para(is_string($fiyatlar[$i] ?? null) ? $fiyatlar[$i] : '', 'Seçenek ' . $i . ' cam fiyatı');
        if ($fiyat === null) {
            $fiyat = $u['price'] !== null ? (float) $u['price'] : null;
        }
        if ($fiyat === null) {
            throw new DomainException('Seçenek ' . $i . ': "' . trim($u['brand'] . ' ' . $u['name']) . '" için katalogda fiyat yok; fiyatı yazın.');
        }
        $et = teklif_cam_etiketi($u);
        $baslik = mb_substr(trim(is_string($adlar[$i] ?? null) ? $adlar[$i] : ''), 0, 60);
        $secenekler[] = [
            'baslik' => $baslik !== '' ? $baslik : (product_tiers()[$u['tier']] ?? 'Seçenek ' . (count($secenekler) + 1)),
            'urun'   => $u,
            'ad'     => mb_substr($et['ad'], 0, 160),
            'desc'   => mb_substr($et['ozellik'], 0, 500),
            'fiyat'  => $fiyat,
        ];
    }
    if (!$secenekler) {
        throw new DomainException('Katalogdan en az bir cam seçin.');
    }

    // --- SGK ---
    $kullanim = $p('lens_design', 'tek_odak_uzak');
    if (function_exists('lens_designs') && !isset(lens_designs()[$kullanim])) {
        throw new DomainException('Kullanım şekli geçersiz.');
    }
    $sgk = 0.0;
    if (($g['sgk_var'] ?? '') === '1') {
        $sgk = $para($p('sgk_amount'), 'Medula (SGK) payı') ?? teklif_sgk_tahmini($kullanim);
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

    return transaction(function () use ($musteri, $p, $kullanici, $frameItemId, $frameDesc, $framePrice, $secenekler, $kullanim, $sgk, $oran): int {
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
        $veri = [
            'tip'            => 'katalog',
            'customer_id'    => $cid,
            'customer_name'  => mb_substr(trim($musteri['first_name'] . ' ' . $musteri['last_name']), 0, 160),
            'customer_phone' => $musteri['phone'] ?: null,
            'note'           => mb_substr($p('note'), 0, 255) ?: null,
            'frame_item_id'  => $frameItemId,
            'frame_desc'     => $frameDesc,
            'frame_price'    => round($framePrice, 2),
            'lens_design'    => $kullanim,
            'sgk_amount'     => round($sgk, 2),
            'discount_rate'  => round($oran, 2),
            'created_by'     => $kullanici['id'],
            'created_at'     => date('Y-m-d H:i:s'),
        ];
        foreach (range(1, TEKLIF_SECENEK_SAYISI) as $i) {
            $s = $secenekler[$i - 1] ?? null;
            $veri["opt{$i}_name"] = $s['baslik'] ?? null;
            $veri["opt{$i}_desc"] = $s ? $s['ad'] . ($s['desc'] !== '' ? "\n" . $s['desc'] : '') : null;
            $veri["opt{$i}_price"] = $s ? round($s['fiyat'], 2) : null;
            $veri["opt{$i}_product_id"] = $s ? (int) $s['urun']['id'] : null;
        }
        $id = insert('quotes', $veri);
        audit('quote_create', 'quote', $id, ['müşteri' => $veri['customer_name'], 'seçenek' => count($secenekler), 'iskonto %' => $oran, 'sgk' => $sgk]);
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
        $satirlar = explode("\n", (string) ($q["opt{$i}_desc"] ?? ''), 2);
        $s = [
            'no'      => $i,
            'baslik'  => (string) $q["opt{$i}_name"],
            'cam'     => $katalog ? $satirlar[0] : '',
            'ozellik' => $katalog ? ($satirlar[1] ?? '') : (string) ($q["opt{$i}_desc"] ?? ''),
            'fiyat'   => $q["opt{$i}_price"] !== null ? (float) $q["opt{$i}_price"] : null,
        ];
        if ($katalog) {
            $s['hesap'] = teklif_hesapla((float) $s['fiyat'], (float) $q['frame_price'], (float) $q['sgk_amount'], (float) $q['discount_rate']);
        }
        $liste[] = $s;
    }
    return $liste;
}

/** Siparişe çevirirken ön dolum: seçilen seçeneğin tutarı, SGK payı, çerçeve ve not. Katalog teklifi değilse null. */
function teklif_siparis_on_dolum(array $q, int $secenek): ?array
{
    if (($q['tip'] ?? 'serbest') !== 'katalog') {
        return null;
    }
    foreach (teklif_secenekleri($q) as $s) {
        if ($s['no'] !== $secenek) {
            continue;
        }
        $h = $s['hesap'];
        $not = 'Teklif #' . (int) $q['id'] . ' · ' . $s['baslik'] . ': ' . $s['cam'] . ($s['ozellik'] !== '' ? ' (' . $s['ozellik'] . ')' : '')
            . "\nCam " . money($h['cam']) . ' + çerçeve ' . money($h['cerceve'])
            . ($h['sgk'] > 0 ? ' − SGK ' . money($h['sgk']) : '')
            . ($h['iskonto'] > 0 ? ' − iskonto %' . rtrim(rtrim(number_format($h['oran'], 2, ',', ''), '0'), ',') . ' (' . money($h['iskonto']) . ')' : '')
            . ' = müşteriden ' . money($h['odenecek']);
        return [
            'total_amount'  => round($h['sgk'] + $h['odenecek'], 2),   // bakiye = toplam − SGK − ödenen = ödenecek
            'sgk_amount'    => $h['sgk'],
            'frame_item_id' => $q['frame_item_id'] ? (int) $q['frame_item_id'] : null,
            'frame_info'    => (string) $q['frame_desc'],
            'notes'         => $not,
        ];
    }
    return null;
}

/** WhatsApp ile gönderilecek döküm metni. */
function teklif_whatsapp_metni(array $q, string $magaza): string
{
    $m = 'Merhaba ' . $q['customer_name'] . ",\nfiyat teklifimiz:\n";
    if (($q['tip'] ?? 'serbest') === 'katalog') {
        $m .= "\nÇerçeve: " . $q['frame_desc'] . ((float) $q['frame_price'] > 0 ? ' — ' . money($q['frame_price']) : '') . "\n";
        foreach (teklif_secenekleri($q) as $s) {
            $h = $s['hesap'];
            $m .= "\n• " . $s['baslik'] . ': ' . $s['cam'] . ($s['ozellik'] !== '' ? "\n  " . $s['ozellik'] : '')
                . "\n  Cam " . money($h['cam']) . ' + çerçeve ' . money($h['cerceve']) . ' = ' . money($h['ara'])
                . ($h['sgk'] > 0 ? "\n  SGK (Medula) payı −" . money($h['sgk']) : '')
                . ($h['iskonto'] > 0 ? "\n  İskonto %" . rtrim(rtrim(number_format($h['oran'], 2, ',', ''), '0'), ',') . ' −' . money($h['iskonto']) : '')
                . "\n  *Ödenecek: " . money($h['odenecek']) . "*\n";
        }
        if ((float) $q['sgk_amount'] > 0) {
            $m .= "\nSGK payı tahminidir; kesin tutar Medula'da reçete işlenince belli olur.\n";
        }
    } else {
        foreach (teklif_secenekleri($q) as $s) {
            $m .= "\n• " . $s['baslik'] . ($s['fiyat'] !== null ? ' — ' . money($s['fiyat']) : '') . ($s['ozellik'] !== '' ? "\n  " . $s['ozellik'] : '') . "\n";
        }
    }
    return $m . "\n" . $magaza;
}
