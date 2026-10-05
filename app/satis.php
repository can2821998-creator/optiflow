<?php
declare(strict_types=1);

/* ==========================================================================
   Hızlı satış (4.17.0) — sipariş açmadan, barkodla satış
   --------------------------------------------------------------------------
   Güneş gözlüğü, aksesuar, solüsyon, kontakt lens gibi hazır ürünler sepete
   okutulur, parçalı ödemeyle (nakit / kart / havale …) tek seferde satılır.
   - Çerçeveler frame_items'tan (stok: frame_moves, sebep "satis", satis_id),
     diğer ürünler urunler kataloğundan (stok: urun_hareketleri) gelir;
     katalogda olmayan şeyler "serbest kalem" olarak yazılabilir.
   - Fiyat ve maliyet SUNUCUDA katalogdan alınır; tarayıcıdan gelen yalnızca
     ürün kimliği, adet ve indirimdir (serbest kalemde fiyat da).
   - Ödemeler satis_odemeleri'ne yazılır: gün sonu kasası, yöntem toplamları,
     raporlar ve kâr/prim ekranı bunları okur.
   - İptal yalnızca yöneticidedir: stok geri girer, satış "iptal" olur ve
     hiçbir toplamda sayılmaz (kayıt silinmez).
   SQL taşınabilir yazılır (testler SQLite'ta çalışır).
   ========================================================================== */

const SATIS_ODEME_FARK = 0.009;   // kuruş yuvarlama toleransı

/** Ürün kategorileri (çerçeveler ayrı: frame_items). */
function urun_kategorileri(): array
{
    return [
        'gunes'    => 'Güneş gözlüğü',
        'lens'     => 'Kontakt lens',
        'solusyon' => 'Solüsyon / damla',
        'aksesuar' => 'Aksesuar (kılıf, kordon, bez …)',
        'hizmet'   => 'Hizmet (montaj, tamir …)',
        'diger'    => 'Diğer',
    ];
}

function urun_kategori_adi(?string $k): string
{
    return urun_kategorileri()[(string) $k] ?? (string) $k;
}

function urun_hareket_sebepleri(): array
{
    return ['giris' => 'Stok girişi', 'satis' => 'Satış', 'iade' => 'İade / iptal', 'sayim' => 'Sayım düzeltmesi', 'fire' => 'Fire / kırık'];
}

/** Hızlı satış kullanabilir mi: tutarları görebilen herkes. İptal yalnızca yönetici. */
function satis_yetkili(): bool
{
    return can_see_amounts();
}

/** Personelin yapabileceği en yüksek indirim (%). Yönetici sınırsız. */
function satis_azami_indirim_yuzde(): float
{
    return is_super() ? 100.0 : max(0.0, min(100.0, (float) setting('hizli_satis_indirim_yuzde', '20')));
}

/* ---------------- Ürün kataloğu ---------------- */

function urun_bul(int $id): ?array
{
    return row('SELECT * FROM urunler WHERE id = ?', [$id]);
}

function urun_barkodla(string $barkod): ?array
{
    $barkod = trim($barkod);
    return $barkod === '' ? null : row('SELECT * FROM urunler WHERE barkod = ? AND is_active = 1', [$barkod]);
}

/** Barkodu olmayan ürüne benzersiz barkod: PU + 8 hane (çerçevedeki PO ile karışmaz). */
function urun_yeni_barkod(): string
{
    for ($i = 0; $i < 20; $i++) {
        $aday = 'PU' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        if (!scalar('SELECT id FROM urunler WHERE barkod = ?', [$aday]) && !scalar('SELECT id FROM frame_items WHERE barcode = ?', [$aday])) {
            return $aday;
        }
    }
    return 'PU' . time();
}

/**
 * Ürün ekler / günceller. $veri: ad, kategori, barkod, fiyat, maliyet, kdv, min_stok, stok_takip, not_metni.
 * Yeni üründe 'stok' verilirse giriş hareketi yazılır.
 */
function urun_kaydet(array $veri, ?int $id = null): int
{
    $ad = trim((string) ($veri['ad'] ?? ''));
    if ($ad === '') {
        throw new DomainException('Ürün adı gerekli.');
    }
    $kat = (string) ($veri['kategori'] ?? 'aksesuar');
    if (!isset(urun_kategorileri()[$kat])) {
        $kat = 'diger';
    }
    $barkod = trim((string) ($veri['barkod'] ?? ''));
    if ($barkod !== '') {
        if (!preg_match('/^[\x21-\x7E]{3,64}$/', $barkod)) {
            throw new DomainException('Barkod 3–64 karakter olmalı, boşluk içermemeli.');
        }
        $cakisan = scalar('SELECT id FROM urunler WHERE barkod = ? AND id <> ?', [$barkod, $id ?? 0]);
        if ($cakisan || scalar('SELECT id FROM frame_items WHERE barcode = ?', [$barkod])) {
            throw new DomainException('Bu barkod başka bir üründe ya da çerçevede kayıtlı.');
        }
    }
    $kdv = (int) ($veri['kdv'] ?? 20);
    if (!in_array($kdv, [0, 1, 10, 20], true)) {
        $kdv = 20;
    }
    $sayi = static fn($v): ?float => $v === null || $v === '' ? null : (is_numeric($v) ? round((float) $v, 2) : parse_money((string) $v));
    $alanlar = [
        'ad'         => mb_substr($ad, 0, 160),
        'kategori'   => $kat,
        'barkod'     => $barkod !== '' ? $barkod : null,
        'fiyat'      => $sayi($veri['fiyat'] ?? null),
        'maliyet'    => $sayi($veri['maliyet'] ?? null),
        'kdv'        => $kdv,
        'min_stok'   => max(0, (int) ($veri['min_stok'] ?? 0)),
        'stok_takip' => !empty($veri['stok_takip']) || !array_key_exists('stok_takip', $veri) ? 1 : 0,
        'not_metni'  => mb_substr(trim((string) ($veri['not_metni'] ?? '')), 0, 255) ?: null,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    if ($kat === 'hizmet') {
        $alanlar['stok_takip'] = 0;
    }
    if ($id) {
        if (!urun_bul($id)) {
            throw new DomainException('Ürün bulunamadı.');
        }
        update('urunler', $alanlar, 'id = ?', [$id]);
        audit('urun_guncelle', 'urun', $id, ['ad' => $alanlar['ad']]);
        return $id;
    }
    $alanlar['created_at'] = date('Y-m-d H:i:s');
    $alanlar['stok'] = 0;
    $yeni = insert('urunler', $alanlar);
    $ilk = (int) ($veri['stok'] ?? 0);
    if ($ilk > 0 && $alanlar['stok_takip']) {
        urun_hareket($yeni, $ilk, 'giris', null, 'İlk stok');
    }
    audit('urun_ekle', 'urun', $yeni, ['ad' => $alanlar['ad']]);
    return $yeni;
}

/**
 * Ürün stok hareketi. Satışta stok eksiye düşebilir (sayımda düzeltilir) — tezgâhta satışı durdurmayız.
 * @return int hareketten sonraki stok
 */
function urun_hareket(int $urunId, int $delta, string $sebep, ?int $satisId = null, string $not = ''): int
{
    $u = row('SELECT id, stok, stok_takip FROM urunler WHERE id = ?', [$urunId]);
    if (!$u || $delta === 0) {
        return (int) ($u['stok'] ?? 0);
    }
    if (!isset(urun_hareket_sebepleri()[$sebep])) {
        $sebep = 'sayim';
    }
    $yeni = (int) $u['stok'] + $delta;
    if ((int) $u['stok_takip'] === 1) {
        update('urunler', ['stok' => $yeni, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$urunId]);
    } else {
        $yeni = (int) $u['stok'];
    }
    insert('urun_hareketleri', [
        'urun_id'    => $urunId,
        'delta'      => $delta,
        'sebep'      => $sebep,
        'satis_id'   => $satisId,
        'not_metni'  => mb_substr($not, 0, 255) ?: null,
        'created_by' => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return $yeni;
}

/** Sepete eklenecek ürünü barkod ya da metinle bulur (çerçeve + katalog). En fazla $limit sonuç. */
function satis_urun_ara(string $metin, int $limit = 12): array
{
    $metin = trim($metin);
    if ($metin === '') {
        return [];
    }
    $sonuc = [];
    // Tam barkod önce
    if ($f = frame_find_by_barcode($metin)) {
        $sonuc[] = satis_cerceve_satiri($f);
    }
    if ($u = urun_barkodla($metin)) {
        $sonuc[] = satis_urun_satiri($u);
    }
    if ($sonuc) {
        return $sonuc;
    }
    $like = '%' . mb_strtolower($metin) . '%';
    foreach (rows("SELECT * FROM urunler WHERE is_active = 1 AND (LOWER(ad) LIKE ? OR LOWER(COALESCE(barkod, '')) LIKE ?) ORDER BY ad LIMIT " . (int) $limit, [$like, $like]) as $u) {
        $sonuc[] = satis_urun_satiri($u);
    }
    $kalan = $limit - count($sonuc);
    if ($kalan > 0) {
        $f = rows(
            "SELECT * FROM frame_items WHERE is_active = 1
               AND (LOWER(COALESCE(brand, '')) LIKE ? OR LOWER(COALESCE(model, '')) LIKE ? OR LOWER(COALESCE(color, '')) LIKE ? OR LOWER(COALESCE(barcode, '')) LIKE ?)
             ORDER BY qty > 0 DESC, brand, model LIMIT " . (int) $kalan,
            [$like, $like, $like, $like]
        );
        foreach ($f as $x) {
            $sonuc[] = satis_cerceve_satiri($x);
        }
    }
    return $sonuc;
}

function satis_cerceve_satiri(array $f): array
{
    return [
        'tur' => 'cerceve', 'id' => (int) $f['id'], 'ad' => frame_item_label($f) ?: 'Çerçeve #' . $f['id'],
        'kategori' => 'Çerçeve', 'barkod' => (string) ($f['barcode'] ?? ''), 'fiyat' => $f['price'] !== null ? (float) $f['price'] : null,
        'stok' => (int) $f['qty'], 'stok_takip' => true,
    ];
}

function satis_urun_satiri(array $u): array
{
    return [
        'tur' => 'urun', 'id' => (int) $u['id'], 'ad' => (string) $u['ad'], 'kategori' => urun_kategori_adi($u['kategori']),
        'barkod' => (string) ($u['barkod'] ?? ''), 'fiyat' => $u['fiyat'] !== null ? (float) $u['fiyat'] : null,
        'stok' => (int) $u['stok'], 'stok_takip' => (int) $u['stok_takip'] === 1,
    ];
}

/* ---------------- Satış ---------------- */

/**
 * Tarayıcıdan gelen sepeti doğrular ve fiyatları sunucudan hesaplar.
 * $kalemler: [['tur'=>'cerceve|urun|serbest','id'=>int,'adet'=>int,'indirim'=>float,'ad'=>str,'fiyat'=>float(serbest)], ...]
 * @return array{kalemler: array, ara_toplam: float, kalem_indirim: float, maliyet: float}
 */
function satis_sepet_hesapla(array $kalemler): array
{
    if (!$kalemler) {
        throw new DomainException('Sepet boş.');
    }
    if (count($kalemler) > 60) {
        throw new DomainException('Bir satışta en fazla 60 kalem olabilir.');
    }
    $sonuc = [];
    $ara = 0.0;
    $indirimToplam = 0.0;
    $maliyet = 0.0;
    foreach ($kalemler as $k) {
        $tur = (string) ($k['tur'] ?? '');
        $adet = (int) ($k['adet'] ?? 1);
        if ($adet < 1 || $adet > 999) {
            throw new DomainException('Adet 1 ile 999 arasında olmalı.');
        }
        $id = (int) ($k['id'] ?? 0);
        $birimMaliyet = null;
        $kdv = 20;
        if ($tur === 'cerceve') {
            $f = row('SELECT * FROM frame_items WHERE id = ?', [$id]);
            if (!$f) {
                throw new DomainException('Sepetteki bir çerçeve artık stokta kayıtlı değil.');
            }
            $ad = frame_item_label($f) ?: 'Çerçeve';
            $birim = $f['price'] !== null ? (float) $f['price'] : null;
            $birimMaliyet = $f['cost'] !== null ? (float) $f['cost'] : null;
        } elseif ($tur === 'urun') {
            $u = urun_bul($id);
            if (!$u) {
                throw new DomainException('Sepetteki bir ürün katalogda bulunamadı.');
            }
            $ad = (string) $u['ad'];
            $birim = $u['fiyat'] !== null ? (float) $u['fiyat'] : null;
            $birimMaliyet = $u['maliyet'] !== null ? (float) $u['maliyet'] : null;
            $kdv = (int) $u['kdv'];
        } elseif ($tur === 'serbest') {
            $id = 0;
            $ad = mb_substr(trim((string) ($k['ad'] ?? '')), 0, 200);
            if ($ad === '') {
                throw new DomainException('Serbest kalemin adı boş olamaz.');
            }
            $birim = null;
        } else {
            throw new DomainException('Geçersiz sepet kalemi.');
        }
        // Katalogda fiyatı olmayan ürün ve serbest kalem: fiyat tezgâhta girilir.
        if ($birim === null) {
            $girilen = $k['fiyat'] ?? null;
            $birim = is_numeric($girilen) ? round((float) $girilen, 2)
                : (trim((string) $girilen) === '' ? null : parse_money((string) $girilen));   // boş fiyat 0 sayılmaz
            if ($birim === null) {
                throw new DomainException('"' . $ad . '" için fiyat girin.');
            }
        }
        if ($birim < 0 || $birim > 1_000_000) {
            throw new DomainException('"' . $ad . '" fiyatı geçersiz.');
        }
        $brut = round($birim * $adet, 2);
        $ind = round(max(0.0, (float) ($k['indirim'] ?? 0)), 2);
        if ($ind > $brut) {
            throw new DomainException('"' . $ad . '" indirimi tutarından büyük olamaz.');
        }
        $sonuc[] = [
            'tur' => $tur, 'ref_id' => $id ?: null, 'ad' => $ad, 'adet' => $adet, 'birim_fiyat' => $birim,
            'indirim' => $ind, 'tutar' => round($brut - $ind, 2), 'birim_maliyet' => $birimMaliyet, 'kdv' => $kdv,
        ];
        $ara += $brut;
        $indirimToplam += $ind;
        $maliyet += ($birimMaliyet ?? 0) * $adet;
    }
    return ['kalemler' => $sonuc, 'ara_toplam' => round($ara, 2), 'kalem_indirim' => round($indirimToplam, 2), 'maliyet' => round($maliyet, 2)];
}

/**
 * Satışı kaydeder (tek işlem): kalemler, ödemeler, stok düşümü, denetim kaydı.
 * $odemeler: [['method'=>'nakit','amount'=>float], ...] toplamı satış tutarına eşit olmalı.
 * $genelIndirim: sepet toplamından düşülen ek indirim (TL).
 * @return int satış no
 */
function satis_kaydet(array $kalemler, array $odemeler, float $genelIndirim = 0.0, ?int $musteriId = null, string $not = ''): int
{
    if (!satis_yetkili()) {
        throw new DomainException('Satış yapma yetkiniz yok.');
    }
    $s = satis_sepet_hesapla($kalemler);
    $genelIndirim = round(max(0.0, $genelIndirim), 2);
    $kalemToplam = round($s['ara_toplam'] - $s['kalem_indirim'], 2);
    if ($genelIndirim > $kalemToplam) {
        throw new DomainException('İndirim satış tutarından büyük olamaz.');
    }
    $toplamIndirim = round($s['kalem_indirim'] + $genelIndirim, 2);
    $toplam = round($s['ara_toplam'] - $toplamIndirim, 2);
    if ($s['ara_toplam'] > 0 && $toplamIndirim / $s['ara_toplam'] * 100 > satis_azami_indirim_yuzde() + 0.001) {
        throw new DomainException('İndirim, size tanımlı en yüksek oranı (%' . rtrim(rtrim(number_format(satis_azami_indirim_yuzde(), 1, ',', ''), '0'), ',') . ') aşıyor. Yöneticiye onaylatın.');
    }
    $yontemler = payment_methods();
    $temiz = [];
    $odenen = 0.0;
    foreach ($odemeler as $o) {
        $m = (string) ($o['method'] ?? '');
        $tutar = is_numeric($o['amount'] ?? null) ? round((float) $o['amount'], 2) : (parse_money((string) ($o['amount'] ?? '')) ?? 0.0);
        if ($tutar <= 0) {
            continue;
        }
        if (!isset($yontemler[$m])) {
            throw new DomainException('Geçersiz ödeme yöntemi.');
        }
        $temiz[] = ['method' => $m, 'amount' => $tutar];
        $odenen += $tutar;
    }
    if (abs(round($odenen, 2) - $toplam) > SATIS_ODEME_FARK) {
        throw new DomainException('Ödemelerin toplamı (' . money($odenen) . ') satış tutarına (' . money($toplam) . ') eşit olmalı.');
    }
    if ($musteriId && !find_customer($musteriId)) {
        $musteriId = null;
    }
    $kim = (int) (current_user()['id'] ?? 0) ?: null;
    $simdi = date('Y-m-d H:i:s');

    return (int) transaction(static function () use ($s, $temiz, $toplam, $toplamIndirim, $musteriId, $not, $kim, $simdi): int {
        $id = insert('satislar', [
            'customer_id' => $musteriId ?: null,
            'ara_toplam'  => $s['ara_toplam'],
            'indirim'     => $toplamIndirim,
            'toplam'      => $toplam,
            'maliyet'     => $s['maliyet'],
            'durum'       => 'tamam',
            'not_metni'   => mb_substr(trim($not), 0, 255) ?: null,
            'created_by'  => $kim,
            'created_at'  => $simdi,
        ]);
        foreach ($s['kalemler'] as $k) {
            insert('satis_kalemleri', ['satis_id' => $id] + $k);
            if ($k['tur'] === 'cerceve') {
                satis_cerceve_hareketi((int) $k['ref_id'], -$k['adet'], 'satis', $id);
            } elseif ($k['tur'] === 'urun') {
                urun_hareket((int) $k['ref_id'], -$k['adet'], 'satis', $id, 'Hızlı satış #' . $id);
            }
        }
        foreach ($temiz as $o) {
            insert('satis_odemeleri', ['satis_id' => $id, 'method' => $o['method'], 'amount' => $o['amount'], 'created_by' => $kim, 'created_at' => $simdi]);
        }
        audit('satis_create', 'satis', $id, ['toplam' => $toplam, 'kalem' => count($s['kalemler']), 'odeme' => implode('+', array_map(static fn($o) => $o['method'], $temiz))]);
        return $id;
    });
}

/** Çerçeve stok hareketi (satış/iptal) — frame_move + satis_id bağı. */
function satis_cerceve_hareketi(int $itemId, int $delta, string $sebep, int $satisId): void
{
    $once = (int) scalar('SELECT COUNT(*) FROM frame_moves WHERE frame_item_id = ?', [$itemId]);
    frame_move($itemId, $delta, $sebep, null, 'Hızlı satış #' . $satisId);
    $sonra = (int) scalar('SELECT COUNT(*) FROM frame_moves WHERE frame_item_id = ?', [$itemId]);
    if ($sonra > $once) {
        $son = (int) scalar('SELECT MAX(id) FROM frame_moves WHERE frame_item_id = ?', [$itemId]);
        update('frame_moves', ['satis_id' => $satisId], 'id = ?', [$son]);
    }
}

function satis_bul(int $id): ?array
{
    $s = row('SELECT s.*, u.full_name AS personel FROM satislar s LEFT JOIN user_accounts u ON u.id = s.created_by WHERE s.id = ?', [$id]);
    if (!$s) {
        return null;
    }
    $s['kalemler'] = rows('SELECT * FROM satis_kalemleri WHERE satis_id = ? ORDER BY id', [$id]);
    $s['odemeler'] = rows('SELECT * FROM satis_odemeleri WHERE satis_id = ? ORDER BY id', [$id]);
    $s['musteri'] = $s['customer_id'] ? find_customer((int) $s['customer_id']) : null;
    return $s;
}

/** İptal (yalnızca yönetici): stok geri girer, satış toplamlardan çıkar. Kayıt silinmez. */
function satis_iptal(int $id, string $sebep): void
{
    if (!is_super()) {
        throw new DomainException('Satışı yalnızca yönetici iptal edebilir.');
    }
    $sebep = trim($sebep);
    if ($sebep === '') {
        throw new DomainException('İptal sebebini yazın.');
    }
    $s = satis_bul($id);
    if (!$s) {
        throw new DomainException('Satış bulunamadı.');
    }
    if ($s['durum'] === 'iptal') {
        throw new DomainException('Bu satış zaten iptal edilmiş.');
    }
    transaction(static function () use ($s, $id, $sebep): void {
        foreach ($s['kalemler'] as $k) {
            if ($k['tur'] === 'cerceve' && $k['ref_id']) {
                satis_cerceve_hareketi((int) $k['ref_id'], (int) $k['adet'], 'iade', $id);
            } elseif ($k['tur'] === 'urun' && $k['ref_id']) {
                urun_hareket((int) $k['ref_id'], (int) $k['adet'], 'iade', $id, 'İptal: hızlı satış #' . $id);
            }
        }
        update('satislar', [
            'durum' => 'iptal', 'iptal_by' => (int) (current_user()['id'] ?? 0) ?: null,
            'iptal_at' => date('Y-m-d H:i:s'), 'iptal_sebep' => mb_substr($sebep, 0, 255),
        ], 'id = ?', [$id]);
        audit('satis_iptal', 'satis', $id, ['toplam' => (float) $s['toplam'], 'sebep' => mb_substr($sebep, 0, 120)]);
    });
}

/** Bir günün (ya da aralığın) satışları; personel yalnızca kendi satışlarını görür. */
function satis_listesi(string $bas, string $bit, bool $iptallerDahil = true): array
{
    $sql = 'SELECT s.*, u.full_name AS personel,
                   (SELECT COUNT(*) FROM satis_kalemleri k WHERE k.satis_id = s.id) AS kalem_sayisi
              FROM satislar s LEFT JOIN user_accounts u ON u.id = s.created_by
             WHERE s.created_at >= ? AND s.created_at <= ?';
    $p = [$bas . ' 00:00:00', $bit . ' 23:59:59'];
    if (!$iptallerDahil) {
        $sql .= " AND s.durum <> 'iptal'";
    }
    if (!is_super()) {
        $sql .= ' AND s.created_by = ?';
        $p[] = (int) (current_user()['id'] ?? 0);
    }
    return rows($sql . ' ORDER BY s.id DESC LIMIT 300', $p);
}

/** Dönem özeti: adet, ciro, maliyet, yönteme göre ödemeler (iptaller hariç). */
function satis_ozeti(string $bas, string $bit, ?int $personel = null): array
{
    $p = [$bas . ' 00:00:00', $bit . ' 23:59:59'];
    $ek = '';
    if ($personel) {
        $ek = ' AND s.created_by = ?';
        $p[] = $personel;
    }
    $r = row("SELECT COUNT(*) AS adet, COALESCE(SUM(s.toplam), 0) AS ciro, COALESCE(SUM(s.maliyet), 0) AS maliyet, COALESCE(SUM(s.indirim), 0) AS indirim
                FROM satislar s WHERE s.durum <> 'iptal' AND s.created_at >= ? AND s.created_at <= ?" . $ek, $p) ?: [];
    $yontem = [];
    foreach (rows("SELECT o.method, COALESCE(SUM(o.amount), 0) AS t FROM satis_odemeleri o JOIN satislar s ON s.id = o.satis_id
                    WHERE s.durum <> 'iptal' AND o.created_at >= ? AND o.created_at <= ?" . $ek . ' GROUP BY o.method', $p) as $y) {
        $yontem[$y['method']] = (float) $y['t'];
    }
    return [
        'adet' => (int) ($r['adet'] ?? 0), 'ciro' => (float) ($r['ciro'] ?? 0), 'maliyet' => (float) ($r['maliyet'] ?? 0),
        'indirim' => (float) ($r['indirim'] ?? 0), 'yontem' => $yontem,
    ];
}

/** Gün sonu kasası için: belirli gün, belirli yöntemle alınan hızlı satış ödemeleri (iptaller hariç). */
function satis_odeme_toplami(string $gun, ?string $yontem = null, ?int $personel = null): float
{
    $sql = "SELECT COALESCE(SUM(o.amount), 0) FROM satis_odemeleri o JOIN satislar s ON s.id = o.satis_id
             WHERE s.durum <> 'iptal' AND o.created_at >= ? AND o.created_at <= ?";
    $p = [$gun . ' 00:00:00', $gun . ' 23:59:59'];
    if ($yontem !== null) {
        $sql .= ' AND o.method = ?';
        $p[] = $yontem;
    }
    if ($personel !== null) {
        $sql .= ' AND o.created_by = ?';
        $p[] = $personel;
    }
    try {
        return (float) scalar($sql, $p);
    } catch (PDOException) {
        return 0.0;   // göç henüz çalışmadıysa kasa ekranı bozulmasın
    }
}

/* ---------------- Gün sonu kasası ---------------- */

/** [$bas, $bit) aralığında hızlı satış ödemeleri, yöntem bazında: [method, cnt, total] (iptaller hariç). */
function satis_kasa_yontemleri(array $aralik): array
{
    try {
        return rows("SELECT o.method, COUNT(*) AS cnt, SUM(o.amount) AS total FROM satis_odemeleri o JOIN satislar s ON s.id = o.satis_id
                      WHERE s.durum <> 'iptal' AND o.created_at >= ? AND o.created_at < ? GROUP BY o.method", $aralik);
    } catch (PDOException) {
        return [];
    }
}

/** Personel × yöntem: [name, method, total, cnt] (iptaller hariç). */
function satis_kasa_personel(array $aralik): array
{
    try {
        return rows("SELECT COALESCE(u.full_name, 'Bilinmiyor') AS name, o.method, SUM(o.amount) AS total, COUNT(*) AS cnt
                       FROM satis_odemeleri o JOIN satislar s ON s.id = o.satis_id LEFT JOIN user_accounts u ON u.id = o.created_by
                      WHERE s.durum <> 'iptal' AND o.created_at >= ? AND o.created_at < ? GROUP BY u.full_name, o.method", $aralik);
    } catch (PDOException) {
        return [];
    }
}

/** Tahsilat satırlarına (method/cnt/total, isteğe bağlı name) hızlı satış satırlarını ekler; aynı anahtarlar toplanır. */
function satis_kasa_birlestir(array $satirlar, array $ekler, array $anahtar = ['method']): array
{
    $harita = [];
    foreach (array_merge($satirlar, $ekler) as $r) {
        $k = implode("\x1f", array_map(static fn($a) => (string) ($r[$a] ?? ''), $anahtar));
        if (!isset($harita[$k])) {
            $harita[$k] = $r;
            $harita[$k]['cnt'] = (int) ($r['cnt'] ?? 0);
            $harita[$k]['total'] = (float) ($r['total'] ?? 0);
        } else {
            $harita[$k]['cnt'] += (int) ($r['cnt'] ?? 0);
            $harita[$k]['total'] += (float) ($r['total'] ?? 0);
        }
    }
    $sonuc = array_values($harita);
    usort($sonuc, static fn($a, $b) => $b['total'] <=> $a['total']);
    return $sonuc;
}

/* ---------------- Raporlar ve kâr/prim ---------------- */

/**
 * Raporlar ekranı için hızlı satış verisi ([$bas, $bit) aralığı, iptaller hariç).
 * $fmt: MySQL DATE_FORMAT kalıbı (seri için; raporlar ekranı MySQL'e özeldir).
 */
function satis_rapor(array $aralik, string $fmt): array
{
    $bos = ['adet' => 0, 'ciro' => 0.0, 'tahsilat' => 0.0, 'seri_satis' => [], 'seri_odeme' => [], 'personel' => [], 'yontem' => []];
    try {
        $r = row("SELECT COUNT(*) AS adet, COALESCE(SUM(toplam), 0) AS ciro FROM satislar WHERE durum <> 'iptal' AND created_at >= ? AND created_at < ?", $aralik) ?: [];
        $bos['adet'] = (int) ($r['adet'] ?? 0);
        $bos['ciro'] = (float) ($r['ciro'] ?? 0);
        if (!$bos['adet']) {
            return $bos;
        }
        $bos['tahsilat'] = (float) scalar("SELECT COALESCE(SUM(o.amount), 0) FROM satis_odemeleri o JOIN satislar s ON s.id = o.satis_id
                                            WHERE s.durum <> 'iptal' AND o.created_at >= ? AND o.created_at < ?", $aralik);
        foreach (rows("SELECT DATE_FORMAT(created_at, '$fmt') AS k, SUM(toplam) AS v FROM satislar WHERE durum <> 'iptal' AND created_at >= ? AND created_at < ? GROUP BY k", $aralik) as $x) {
            $bos['seri_satis'][$x['k']] = (float) $x['v'];
        }
        foreach (rows("SELECT DATE_FORMAT(o.created_at, '$fmt') AS k, SUM(o.amount) AS v FROM satis_odemeleri o JOIN satislar s ON s.id = o.satis_id
                        WHERE s.durum <> 'iptal' AND o.created_at >= ? AND o.created_at < ? GROUP BY k", $aralik) as $x) {
            $bos['seri_odeme'][$x['k']] = (float) $x['v'];
        }
        $bos['personel'] = rows("SELECT COALESCE(u.full_name, '—') AS name, COUNT(*) AS cnt, SUM(s.toplam) AS total FROM satislar s LEFT JOIN user_accounts u ON u.id = s.created_by
                                  WHERE s.durum <> 'iptal' AND s.created_at >= ? AND s.created_at < ? GROUP BY u.full_name", $aralik);
        $bos['yontem'] = satis_kasa_yontemleri($aralik);
    } catch (PDOException) {
        // göç henüz çalışmadıysa raporlar bozulmasın
    }
    return $bos;
}

/** Kâr/prim ekranı: personel bazında hızlı satış cirosu ve maliyeti. [created_by => ['adet','ciro','maliyet']] */
function satis_personel_kar(string $bas, string $bit): array
{
    $sonuc = [];
    try {
        foreach (rows("SELECT created_by, COUNT(*) AS adet, COALESCE(SUM(toplam), 0) AS ciro, COALESCE(SUM(maliyet), 0) AS maliyet
                         FROM satislar WHERE durum <> 'iptal' AND created_at >= ? AND created_at <= ? GROUP BY created_by",
                       [$bas . ' 00:00:00', $bit . ' 23:59:59']) as $r) {
            $sonuc[(int) $r['created_by']] = ['adet' => (int) $r['adet'], 'ciro' => (float) $r['ciro'], 'maliyet' => (float) $r['maliyet']];
        }
    } catch (PDOException) {
    }
    return $sonuc;
}
