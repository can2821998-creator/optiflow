<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Alış faturası (4.14.0)

   Tedarikçinin e-Fatura / e-Arşiv belgesi (UBL-TR 1.2 XML ya da XML içeren ZIP)
   yüklenir; önizlemede:
     • tedarikçi VKN/TCKN ile tanınır (yoksa tek tıkla oluşturulur),
     • aynı fatura (ETTN ya da tedarikçi + fatura no) ikinci kez kaydedilmez,
     • her kalem çerçeve kartına eşlenir: GTIN/barkod → tedarikçinin ürün kodu
       hafızası (urun_eslesmeleri) → elle seçim / yeni kart. Eşleme hatırlanır.
   Onayda: fatura cariye borç yazılır (vade dahil), seçilen kalemler çerçeve
   stoğuna girer ve kartın maliyeti (KDV dahil birim) güncellenir.

   GÜVENLİK: XML dış varlık (XXE) ve ağ erişimi kapalı okunur; dosya / ZIP boyutu
   ve ZIP içi açılmış boyut sınırlıdır. Yüklenen dosyalar önizleme ile onay arasında
   storage/tmp altında, oturuma bağlı rastgele adla tutulur ve 24 saatte silinir.

   SQL taşınabilir tutulmuştur (MySQL + testlerdeki SQLite).
   ========================================================================== */

const ALIS_DOSYA_SINIRI = 8_000_000;        // tek XML
const ALIS_ZIP_SINIRI = 25_000_000;         // yüklenen ZIP
const ALIS_ZIP_ACIK_SINIRI = 60_000_000;    // ZIP içindeki XML'lerin toplam açılmış boyutu
const ALIS_EN_FAZLA_BELGE = 40;

/** Tedarikçi ödeme yöntemleri: müşteri yöntemleri + senet (yalnızca tedarikçi tarafında). */
function tedarik_odeme_yontemleri(): array
{
    return payment_methods() + ['senet' => 'Senet'];
}

/* ---------------- Dosya okuma ---------------- */

/**
 * Yüklenen dosyalardan (XML ya da ZIP) XML metinlerini çıkarır.
 * $dosyalar: [['ad' => string, 'yol' => string (geçici dosya)], …]
 * Dönüş: [['ad' => string, 'xml' => string], …] — en çok ALIS_EN_FAZLA_BELGE.
 */
function alis_dosyalari_oku(array $dosyalar): array
{
    $sonuc = [];
    foreach ($dosyalar as $d) {
        $ad = mb_substr(basename((string) $d['ad']), 0, 120);
        $yol = (string) $d['yol'];
        if (!is_file($yol)) {
            continue;
        }
        $boyut = (int) filesize($yol);
        $bas = (string) file_get_contents($yol, false, null, 0, 4);
        if ($bas === "PK\x03\x04") {
            if ($boyut > ALIS_ZIP_SINIRI) {
                throw new DomainException($ad . ': ZIP dosyası çok büyük (en çok 25 MB).');
            }
            if (!class_exists('ZipArchive')) {
                throw new DomainException('Sunucuda PHP zip eklentisi yok; XML dosyalarını ZIP\'ten çıkarıp tek tek yükleyin.');
            }
            $z = new ZipArchive();
            if ($z->open($yol) !== true) {
                throw new DomainException($ad . ': ZIP açılamadı.');
            }
            $toplam = 0;
            for ($i = 0; $i < $z->numFiles; $i++) {
                $st = $z->statIndex($i);
                if (!$st || !preg_match('/\.xml$/i', (string) $st['name']) || str_ends_with((string) $st['name'], '/')) {
                    continue;
                }
                $toplam += (int) $st['size'];
                if ((int) $st['size'] > ALIS_DOSYA_SINIRI || $toplam > ALIS_ZIP_ACIK_SINIRI) {
                    $z->close();
                    throw new DomainException($ad . ': ZIP içindeki XML dosyaları çok büyük.');
                }
                $icerik = $z->getFromIndex($i, ALIS_DOSYA_SINIRI + 1);
                if (is_string($icerik) && strlen($icerik) <= ALIS_DOSYA_SINIRI) {
                    $sonuc[] = ['ad' => $ad . ' › ' . mb_substr(basename((string) $st['name']), 0, 120), 'xml' => $icerik];
                }
                if (count($sonuc) >= ALIS_EN_FAZLA_BELGE) {
                    break;
                }
            }
            $z->close();
        } else {
            if ($boyut > ALIS_DOSYA_SINIRI) {
                throw new DomainException($ad . ': dosya çok büyük (en çok 8 MB).');
            }
            $sonuc[] = ['ad' => $ad, 'xml' => (string) file_get_contents($yol)];
        }
        if (count($sonuc) >= ALIS_EN_FAZLA_BELGE) {
            break;
        }
    }
    return array_slice($sonuc, 0, ALIS_EN_FAZLA_BELGE);
}

/* ---------------- UBL-TR çözümleme ---------------- */

/**
 * UBL-TR fatura XML'ini çözer. Ad alanı öneklerinden bağımsızdır (local-name ile okunur).
 * Dönüş: [
 *   'ettn','no','tarih','tip','profil','para',
 *   'satici' => ['vkn','unvan','vergi_dairesi','adres'], 'alici_vkn',
 *   'vade', 'ara_toplam','kdv_toplam','odenecek', 'kalemler' => [[sira, ad, kod, gtin, miktar, birim, birim_fiyat, kdv_orani, tutar, kdv_tutar], …]
 * ]
 */
function alis_ubl_coz(string $xml): array
{
    $xml = ltrim($xml, "\xEF\xBB\xBF \t\r\n");
    if ($xml === '' || !str_contains(substr($xml, 0, 4000), '<')) {
        throw new DomainException('Dosya bir XML değil.');
    }
    if (preg_match('/<!DOCTYPE/i', substr($xml, 0, 4000))) {
        throw new DomainException('XML içinde DOCTYPE bulunan dosyalar güvenlik nedeniyle okunmaz.');
    }
    $eski = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_COMPACT | LIBXML_PARSEHUGE);
    libxml_clear_errors();
    libxml_use_internal_errors($eski);
    if (!$ok) {
        throw new DomainException('XML okunamadı (bozuk dosya).');
    }
    $xp = new DOMXPath($dom);
    $faturalar = $xp->query("//*[local-name()='Invoice']");
    if (!$faturalar || $faturalar->length === 0) {
        throw new DomainException('Bu XML bir e-Fatura / e-Arşiv faturası (UBL Invoice) değil.');
    }
    $kok = $faturalar->item(0);
    // Yalnızca doğrudan çocuk elemanlar (kalemlerin içindeki aynı adlı alanlar karışmasın)
    $ilk = static function (string $yol, ?DOMNode $baglam = null) use ($xp, $kok): string {
        $parcalar = array_map(static fn($p) => "*[local-name()='$p']", explode('/', $yol));
        $l = $xp->query(implode('/', $parcalar), $baglam ?? $kok);
        return $l && $l->length ? trim((string) $l->item(0)->textContent) : '';
    };
    $tumu = static function (string $yol, ?DOMNode $baglam = null) use ($xp, $kok): array {
        $parcalar = array_map(static fn($p) => "*[local-name()='$p']", explode('/', $yol));
        $l = $xp->query(implode('/', $parcalar), $baglam ?? $kok);
        return $l ? iterator_to_array($l) : [];
    };
    $sayi = static function (string $v): float {
        $v = trim($v);
        return is_numeric($v) ? round((float) $v, 4) : 0.0;
    };
    $tarih = static fn(string $v): string => preg_match('/^\d{4}-\d{2}-\d{2}/', $v) ? substr($v, 0, 10) : '';

    // Satıcı
    $vkn = '';
    foreach ($tumu('AccountingSupplierParty/Party/PartyIdentification/ID') as $n) {
        $sema = strtoupper((string) ($n instanceof DOMElement ? $n->getAttribute('schemeID') : ''));
        if (in_array($sema, ['VKN', 'TCKN'], true)) {
            $vkn = preg_replace('/\D/', '', $n->textContent) ?? '';
            break;
        }
    }
    $unvan = $ilk('AccountingSupplierParty/Party/PartyName/Name');
    if ($unvan === '') {
        $unvan = trim($ilk('AccountingSupplierParty/Party/Person/FirstName') . ' ' . $ilk('AccountingSupplierParty/Party/Person/FamilyName'));
    }
    $adres = trim(implode(' ', array_filter([
        $ilk('AccountingSupplierParty/Party/PostalAddress/StreetName'),
        $ilk('AccountingSupplierParty/Party/PostalAddress/BuildingNumber'),
        $ilk('AccountingSupplierParty/Party/PostalAddress/CitySubdivisionName'),
        $ilk('AccountingSupplierParty/Party/PostalAddress/CityName'),
    ])));
    $aliciVkn = '';
    foreach ($tumu('AccountingCustomerParty/Party/PartyIdentification/ID') as $n) {
        $sema = strtoupper((string) ($n instanceof DOMElement ? $n->getAttribute('schemeID') : ''));
        if (in_array($sema, ['VKN', 'TCKN'], true)) {
            $aliciVkn = preg_replace('/\D/', '', $n->textContent) ?? '';
            break;
        }
    }

    // Vade: PaymentMeans/PaymentDueDate, yoksa PaymentTerms/PaymentDueDate
    $vade = $tarih($ilk('PaymentMeans/PaymentDueDate'));
    if ($vade === '') {
        $vade = $tarih($ilk('PaymentTerms/PaymentDueDate'));
    }

    $kalemler = [];
    $sira = 0;
    foreach ($tumu('InvoiceLine') as $satir) {
        $sira++;
        $miktarNode = $tumu('InvoicedQuantity', $satir);
        $miktar = $miktarNode ? $sayi($miktarNode[0]->textContent) : 1.0;
        $birim = $miktarNode && $miktarNode[0] instanceof DOMElement ? mb_substr($miktarNode[0]->getAttribute('unitCode'), 0, 8) : '';
        $tutar = $sayi($ilk('LineExtensionAmount', $satir));
        $kdvTutar = $sayi($ilk('TaxTotal/TaxAmount', $satir));
        $kdvOran = $sayi($ilk('TaxTotal/TaxSubtotal/Percent', $satir));
        $gtin = preg_replace('/\D/', '', $ilk('Item/StandardItemIdentification/ID', $satir)) ?? '';
        $kod = $ilk('Item/SellersItemIdentification/ID', $satir);
        if ($kod === '') {
            $kod = $ilk('Item/ManufacturersItemIdentification/ID', $satir);
        }
        $ad = trim($ilk('Item/Name', $satir));
        $marka = $ilk('Item/BrandName', $satir);
        $model = $ilk('Item/ModelName', $satir);
        if ($ad === '') {
            $ad = trim($marka . ' ' . $model) ?: 'Kalem ' . $sira;
        }
        $kalemler[] = [
            'sira'        => $sira,
            'ad'          => mb_substr($ad, 0, 255),
            'kod'         => mb_substr(trim($kod), 0, 80),
            'gtin'        => strlen($gtin) >= 8 && strlen($gtin) <= 14 ? $gtin : '',
            'marka'       => mb_substr($marka, 0, 80),
            'model'       => mb_substr($model, 0, 80),
            'miktar'      => $miktar > 0 ? $miktar : 1.0,
            'birim'       => $birim,
            'birim_fiyat' => $sayi($ilk('Price/PriceAmount', $satir)),
            'kdv_orani'   => $kdvOran,
            'tutar'       => round($tutar, 2),
            'kdv_tutar'   => round($kdvTutar, 2),
        ];
    }

    $tip = strtoupper($ilk('InvoiceTypeCode'));
    $f = [
        'ettn'       => strtolower($ilk('UUID')),
        'no'         => mb_substr($ilk('ID'), 0, 60),
        'tarih'      => $tarih($ilk('IssueDate')),
        'tip'        => $tip,
        'profil'     => strtoupper($ilk('ProfileID')),
        'para'       => strtoupper($ilk('DocumentCurrencyCode')) ?: 'TRY',
        'satici'     => ['vkn' => $vkn, 'unvan' => mb_substr($unvan, 0, 120), 'vergi_dairesi' => mb_substr($ilk('AccountingSupplierParty/Party/PartyTaxScheme/TaxScheme/Name'), 0, 80), 'adres' => mb_substr($adres, 0, 255)],
        'alici_vkn'  => $aliciVkn,
        'vade'       => $vade,
        'ara_toplam' => round($sayi($ilk('LegalMonetaryTotal/TaxExclusiveAmount')), 2),
        'kdv_toplam' => round($sayi($ilk('TaxTotal/TaxAmount')), 2),
        'odenecek'   => round($sayi($ilk('LegalMonetaryTotal/PayableAmount')), 2),
        'kalemler'   => $kalemler,
    ];
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $f['ettn'])) {
        $f['ettn'] = '';
    }
    if ($f['no'] === '' || $f['tarih'] === '') {
        throw new DomainException('Faturada numara ya da tarih bulunamadı.');
    }
    if ($f['odenecek'] <= 0) {
        $f['odenecek'] = round($sayi($ilk('LegalMonetaryTotal/TaxInclusiveAmount')), 2);
    }
    return $f;
}

/**
 * Kaydetmeye engel durumlar (önizlemede gösterilir). Dönüş: hata metinleri.
 */
function alis_engeller(array $f): array
{
    $e = [];
    if ($f['para'] !== 'TRY' && $f['para'] !== 'TL') {
        $e[] = 'Fatura dövizli (' . $f['para'] . '). Cari TL tuttuğu için bu fatura otomatik işlenmez; TL karşılığıyla elle girin.';
    }
    if (in_array($f['tip'], ['IADE', 'TEVKIFATIADE'], true)) {
        $e[] = 'Bu bir iade faturası. Alış faturası olarak işlenmez; tedarikçi kartından ödeme/düzeltme olarak girin.';
    }
    if ($f['odenecek'] <= 0) {
        $e[] = 'Ödenecek tutar bulunamadı.';
    }
    return $e;
}

/** Uyarılar (kaydetmeye engel değil). */
function alis_uyarilar(array $f): array
{
    $u = [];
    $bizim = preg_replace('/\D/', '', setting('firma_vkn', '')) ?? '';
    if ($bizim !== '' && $f['alici_vkn'] !== '' && $bizim !== $f['alici_vkn']) {
        $u[] = 'Faturadaki alıcı VKN/TCKN (' . $f['alici_vkn'] . ') sizin kayıtlı numaranızla (' . $bizim . ') aynı değil.';
    }
    $kalemToplam = 0.0;
    foreach ($f['kalemler'] as $k) {
        $kalemToplam += $k['tutar'] + $k['kdv_tutar'];
    }
    if ($f['kalemler'] && abs($kalemToplam - $f['odenecek']) > 1.0) {
        $u[] = 'Kalem toplamı (' . money($kalemToplam) . ') ödenecek tutardan (' . money($f['odenecek']) . ') farklı (iskonto, tevkifat ya da yuvarlama). Cariye ödenecek tutar yazılır.';
    }
    if ($f['vade'] === '') {
        $u[] = 'Faturada vade tarihi yok; isterseniz aşağıdan girin.';
    }
    return $u;
}

/* ---------------- Eşleme ---------------- */

/** VKN/TCKN ile tedarikçi. */
function alis_tedarikci_bul(string $vkn): ?array
{
    $vkn = preg_replace('/\D/', '', $vkn) ?? '';
    if ($vkn === '') {
        return null;
    }
    foreach (rows("SELECT * FROM suppliers WHERE tax_no IS NOT NULL AND tax_no <> '' ORDER BY is_active DESC, id") as $s) {
        if ((preg_replace('/\D/', '', (string) $s['tax_no']) ?? '') === $vkn) {
            return $s;
        }
    }
    return null;
}

/** Aynı fatura daha önce kaydedildi mi? (ETTN ya da tedarikçi + fatura no) */
function alis_mukerrer(array $f, ?int $tedarikciId): ?array
{
    if ($f['ettn'] !== '') {
        $r = row('SELECT * FROM supplier_invoices WHERE ettn = ?', [$f['ettn']]);
        if ($r) {
            return $r;
        }
    }
    if ($tedarikciId) {
        return row('SELECT * FROM supplier_invoices WHERE supplier_id = ? AND invoice_no = ?', [$tedarikciId, $f['no']]);
    }
    return null;
}

/** Barkod adayları: GTIN-14 → 13 haneli EAN ve baştaki sıfırlar. */
function alis_barkod_adaylari(string $kod): array
{
    $kod = trim($kod);
    if ($kod === '') {
        return [];
    }
    return array_values(array_unique(array_filter([$kod, ltrim($kod, '0'), strlen($kod) === 14 ? substr($kod, 1) : '', strlen($kod) === 13 ? '0' . $kod : ''])));
}

/**
 * Kalem için çerçeve kartı önerisi. Dönüş: ['frame_item_id' => ?int, 'neden' => 'gtin'|'hafiza'|'kod'|'']
 */
function alis_kalem_onerisi(?int $tedarikciId, array $k): array
{
    foreach (['gtin' => $k['gtin'], 'kod' => $k['kod']] as $neden => $deger) {
        $aday = alis_barkod_adaylari((string) $deger);
        if ($aday) {
            $f = row('SELECT id FROM frame_items WHERE barcode IN (' . in_placeholders($aday) . ') ORDER BY is_active DESC, id LIMIT 1', $aday);
            if ($f) {
                return ['frame_item_id' => (int) $f['id'], 'neden' => $neden];
            }
        }
        if ($neden === 'gtin' && $tedarikciId && $k['kod'] !== '') {
            $h = row('SELECT e.frame_item_id FROM urun_eslesmeleri e JOIN frame_items f ON f.id = e.frame_item_id WHERE e.supplier_id = ? AND e.kod = ?', [$tedarikciId, $k['kod']]);
            if ($h) {
                return ['frame_item_id' => (int) $h['frame_item_id'], 'neden' => 'hafiza'];
            }
        }
    }
    return ['frame_item_id' => null, 'neden' => ''];
}

/** Stok adedi: tam sayı miktar (çerçeve adedi). Kesirli / sıfır miktar stoğa işlenmez. */
function alis_stok_adedi(array $k): int
{
    $m = (float) $k['miktar'];
    return abs($m - round($m)) < 0.0001 && $m >= 1 && $m <= 10000 ? (int) round($m) : 0;
}

/** KDV dahil birim maliyet. */
function alis_birim_maliyet(array $k): float
{
    $m = max(0.001, (float) $k['miktar']);
    return round(((float) $k['tutar'] + (float) $k['kdv_tutar']) / $m, 2);
}

/* ---------------- Kaydetme ---------------- */

/**
 * Faturayı kaydeder.
 * $tedarikci: ['id' => int] ya da ['yeni' => true] (XML'deki satıcı bilgisiyle oluşturulur)
 * $kararlar: [sira => ['islem' => 'stok'|'yok', 'frame_item_id' => int|'yeni']]
 * $vade: 'YYYY-AA-GG' ya da '' (XML'deki vadeyi değiştirmek için)
 * Dönüş: ['invoice_id' => int, 'supplier_id' => int, 'stok' => int (giren adet), 'kart' => int (açılan kart)]
 */
function alis_kaydet(array $f, array $tedarikci, array $kararlar, string $vade, string $xmlHam): array
{
    if ($e = alis_engeller($f)) {
        throw new DomainException(implode(' ', $e));
    }
    if ($vade !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $vade)) {
        throw new DomainException('Vade tarihi geçersiz.');
    }
    return transaction(static function () use ($f, $tedarikci, $kararlar, $vade, $xmlHam): array {
        if (!empty($tedarikci['yeni'])) {
            if ($f['satici']['unvan'] === '') {
                throw new DomainException('Faturada satıcı unvanı yok; tedarikçiyi listeden seçin.');
            }
            $var = alis_tedarikci_bul($f['satici']['vkn']);
            $sid = $var ? (int) $var['id'] : insert('suppliers', [
                'name'       => mb_substr($f['satici']['unvan'], 0, 120),
                'tax_no'     => $f['satici']['vkn'] ?: null,
                'address'    => $f['satici']['adres'] ?: null,
                'is_active'  => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $sid = (int) ($tedarikci['id'] ?? 0);
            if (!$sid || !row('SELECT id FROM suppliers WHERE id = ?', [$sid])) {
                throw new DomainException('Tedarikçi seçin.');
            }
        }
        if ($m = alis_mukerrer($f, $sid)) {
            throw new DomainException('Bu fatura zaten kayıtlı (' . $m['invoice_no'] . ', ' . date_tr((string) $m['invoice_date']) . ').');
        }
        $kalemSayisi = count($f['kalemler']);
        $iid = insert('supplier_invoices', [
            'supplier_id'  => $sid,
            'invoice_no'   => $f['no'],
            'invoice_date' => $f['tarih'],
            'amount'       => $f['odenecek'],
            'note'         => mb_substr('e-Fatura · ' . $kalemSayisi . ' kalem' . ($f['profil'] ? ' · ' . $f['profil'] : ''), 0, 255),
            'created_by'   => (int) (current_user()['id'] ?? 0) ?: null,
            'created_at'   => date('Y-m-d H:i:s'),
            'due_date'     => ($vade !== '' ? $vade : $f['vade']) ?: null,
            'ettn'         => $f['ettn'] ?: null,
            'kaynak'       => 'xml',
            'ara_toplam'   => $f['ara_toplam'] ?: null,
            'kdv_toplam'   => $f['kdv_toplam'] ?: null,
            'xml'          => strlen($xmlHam) <= ALIS_DOSYA_SINIRI ? $xmlHam : null,
        ]);
        $stok = 0;
        $kart = 0;
        foreach ($f['kalemler'] as $k) {
            $karar = $kararlar[$k['sira']] ?? ['islem' => 'yok'];
            $fid = null;
            $adet = 0;
            if (($karar['islem'] ?? 'yok') === 'stok' && ($adet = alis_stok_adedi($k)) > 0) {
                $secim = $karar['frame_item_id'] ?? '';
                if ($secim === 'yeni') {
                    $fid = alis_kart_olustur($sid, $k);
                    $kart++;
                } elseif ((int) $secim > 0 && row('SELECT id FROM frame_items WHERE id = ?', [(int) $secim])) {
                    $fid = (int) $secim;
                }
                if ($fid) {
                    frame_move($fid, $adet, 'giris', null, 'Alış faturası ' . $f['no']);
                    q('UPDATE frame_items SET cost = ?, supplier_id = COALESCE(supplier_id, ?), is_active = 1, updated_at = ? WHERE id = ?', [alis_birim_maliyet($k), $sid, date('Y-m-d H:i:s'), $fid]);
                    if ($k['kod'] !== '') {
                        alis_eslesme_hatirla($sid, $k['kod'], $fid);
                    }
                    $stok += $adet;
                } else {
                    $adet = 0;
                }
            }
            insert('supplier_invoice_lines', [
                'invoice_id'    => $iid,
                'sira'          => $k['sira'],
                'ad'            => $k['ad'],
                'kod'           => $k['kod'] ?: null,
                'gtin'          => $k['gtin'] ?: null,
                'miktar'        => $k['miktar'],
                'birim'         => $k['birim'] ?: null,
                'birim_fiyat'   => $k['birim_fiyat'],
                'kdv_orani'     => $k['kdv_orani'],
                'tutar'         => $k['tutar'],
                'kdv_tutar'     => $k['kdv_tutar'],
                'frame_item_id' => $fid,
                'stok_adet'     => $adet,
            ]);
        }
        return ['invoice_id' => $iid, 'supplier_id' => $sid, 'stok' => $stok, 'kart' => $kart];
    });
}

/** Kalemden yeni çerçeve kartı: marka/model alanları varsa onlar, yoksa ürün adı ikiye bölünür. */
function alis_kart_olustur(int $tedarikciId, array $k): int
{
    $marka = trim((string) ($k['marka'] ?? ''));
    $model = trim((string) ($k['model'] ?? ''));
    if ($marka === '') {
        $p = preg_split('/\s+/u', trim((string) $k['ad']), 2) ?: [];
        $marka = (string) ($p[0] ?? 'Ürün');
        $model = $model !== '' ? $model : (string) ($p[1] ?? '');
    }
    $barkod = null;
    foreach ([(string) $k['gtin'], (string) $k['kod']] as $aday) {
        $aday = trim($aday);
        if ($aday !== '' && strlen($aday) <= 64 && !row('SELECT id FROM frame_items WHERE barcode = ?', [$aday])) {
            $barkod = $aday;
            break;
        }
    }
    return insert('frame_items', [
        'brand'       => mb_substr($marka, 0, 80),
        'model'       => mb_substr($model, 0, 80) ?: null,
        'barcode'     => $barkod,
        'qty'         => 0,
        'min_qty'     => 1,
        'cost'        => alis_birim_maliyet($k),
        'supplier_id' => $tedarikciId,
        'note'        => 'Alış faturasından oluşturuldu',
        'is_active'   => 1,
        'created_by'  => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at'  => date('Y-m-d H:i:s'),
        'updated_at'  => date('Y-m-d H:i:s'),
    ]);
}

function alis_eslesme_hatirla(int $tedarikciId, string $kod, int $frameId): void
{
    $kod = mb_substr(trim($kod), 0, 80);
    if ($kod === '') {
        return;
    }
    $var = row('SELECT id FROM urun_eslesmeleri WHERE supplier_id = ? AND kod = ?', [$tedarikciId, $kod]);
    if ($var) {
        q('UPDATE urun_eslesmeleri SET frame_item_id = ? WHERE id = ?', [$frameId, (int) $var['id']]);
    } else {
        insert('urun_eslesmeleri', ['supplier_id' => $tedarikciId, 'kod' => $kod, 'frame_item_id' => $frameId, 'created_at' => date('Y-m-d H:i:s')]);
    }
}

/**
 * XML'den kaydedilmiş faturayı siler: stoğa girmiş adetler geri alınır, kalemler silinir.
 * (Senede bağlıysa önce senet iptal edilmelidir.)
 */
function alis_fatura_sil(int $invoiceId): void
{
    $inv = row('SELECT * FROM supplier_invoices WHERE id = ?', [$invoiceId]);
    if (!$inv) {
        return;
    }
    if (table_var_mi('tedarikci_senetleri') && (int) scalar("SELECT COUNT(*) FROM tedarikci_senetleri WHERE invoice_id = ? AND durum <> 'iptal'", [$invoiceId]) > 0) {
        throw new DomainException('Bu faturaya bağlı senet var; önce senedi iptal edin.');
    }
    transaction(static function () use ($inv, $invoiceId): void {
        if (table_var_mi('supplier_invoice_lines')) {
            foreach (rows('SELECT * FROM supplier_invoice_lines WHERE invoice_id = ? AND frame_item_id IS NOT NULL AND stok_adet > 0', [$invoiceId]) as $l) {
                frame_move((int) $l['frame_item_id'], -(int) $l['stok_adet'], 'sayim', null, 'Alış faturası silindi · ' . $inv['invoice_no']);
            }
            q('DELETE FROM supplier_invoice_lines WHERE invoice_id = ?', [$invoiceId]);
        }
        q('UPDATE supplier_deliveries SET invoice_id = NULL WHERE invoice_id = ?', [$invoiceId]);
        q('DELETE FROM supplier_invoices WHERE id = ?', [$invoiceId]);
    });
}

/** Şema güncellenmeden önce de güvenle çağrılabilsin diye küçük önbellekli tablo kontrolü. */
function table_var_mi(string $tablo): bool
{
    static $c = [];
    if (!isset($c[$tablo])) {
        try {
            q('SELECT 1 FROM `' . str_replace('`', '', $tablo) . '` LIMIT 1');
            $c[$tablo] = true;
        } catch (Throwable) {
            $c[$tablo] = false;
        }
    }
    return $c[$tablo];
}

/** ÜTS'de bu faturanın (belge no) kabul bekleyen ürünleri. */
function alis_uts_bekleyen(string $faturaNo): int
{
    if ($faturaNo === '' || !function_exists('ozellik_acik') || !ozellik_acik('uts_bildirim') || !table_var_mi('uts_urunler')) {
        return 0;
    }
    return (int) scalar("SELECT COUNT(*) FROM uts_urunler WHERE durum = 'gelen' AND belge_no = ?", [$faturaNo]);
}

/* ---------------- Önizleme ile onay arası geçici saklama ---------------- */

function alis_gecici_dizin(): string
{
    $d = APP_ROOT . '/storage/tmp/alis';
    if (!is_dir($d)) {
        @mkdir($d, 0700, true);
    }
    return $d;
}

/** XML'leri geçici olarak saklar, oturuma bağlı anahtar döner. */
function alis_gecici_kaydet(array $belgeler): string
{
    alis_gecici_temizle();
    $anahtar = bin2hex(random_bytes(16));
    $yol = alis_gecici_dizin() . '/' . $anahtar . '.json';
    if (@file_put_contents($yol, (string) json_encode($belgeler, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), LOCK_EX) === false) {
        throw new DomainException('Geçici dosya yazılamadı (storage/ klasörü yazılabilir olmalı).');
    }
    @chmod($yol, 0600);
    $_SESSION['alis_yukleme'][$anahtar] = time();
    return $anahtar;
}

/** Oturuma ait geçici yüklemeyi okur. */
function alis_gecici_oku(string $anahtar): ?array
{
    if (!preg_match('/^[0-9a-f]{32}$/', $anahtar) || empty($_SESSION['alis_yukleme'][$anahtar])) {
        return null;
    }
    $yol = alis_gecici_dizin() . '/' . $anahtar . '.json';
    if (!is_file($yol)) {
        return null;
    }
    $v = json_decode((string) file_get_contents($yol), true);
    return is_array($v) ? $v : null;
}

function alis_gecici_sil(string $anahtar): void
{
    if (preg_match('/^[0-9a-f]{32}$/', $anahtar)) {
        @unlink(alis_gecici_dizin() . '/' . $anahtar . '.json');
        unset($_SESSION['alis_yukleme'][$anahtar]);
    }
}

function alis_gecici_temizle(): void
{
    foreach (glob(alis_gecici_dizin() . '/*.json') ?: [] as $d) {
        if (filemtime($d) < time() - 86400) {
            @unlink($d);
        }
    }
}
