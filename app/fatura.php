<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — e-Arşiv / e-Fatura altyapısı (4.12.0, HAZIRLIK AŞAMASI)

   Bu sürümde:
     • Fatura veri modeli (faturalar + fatura_satirlari), satıcı bilgileri,
     • Siparişten fatura TASLAĞI (hasta payı); SGK payı ay sonunda TEK toplu SGK faturası (4.16.1),
     • Kontroller (TCKN/VKN algoritması, zorunlu alanlar, tutar tutarlılığı),
     • UBL-TR 1.2 XML önizleme (Invoice-2, EARSIVFATURA / TEMELFATURA / TICARIFATURA),
     • Entegratör SÜRÜCÜ ARAYÜZÜ (EFaturaSurucu) — bu sürümde yalnızca "bağlı değil".

   Bir sonraki sürümde yalnızca bir sürücü sınıfı eklenir (ör. entegratörün
   REST/SOAP servisi): fatura numarası ve imza entegratörde verilir, gönderim
   ve durum sorgusu açılır. Veri modeli ve ekranlar değişmez.

   ÖNEMLİ: Burada üretilen XML resmi fatura DEĞİLDİR; GİB'e gönderilmez.
   Fatura numarası (ID) taslakta "TASLAK" önekiyle gösterilir. KDV oranları
   ve SGK fatura bilgileri mali müşavirle doğrulanmalıdır.
   ========================================================================== */

const UBL_NS_INVOICE = 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2';
const UBL_NS_CAC     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
const UBL_NS_CBC     = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

/** TCKN'si bilinmeyen bireysel alıcı için kullanılan genel numara (uygulamadaki yaygın kullanım). */
const TCKN_BILINMIYOR = '11111111111';

function fatura_profilleri(): array
{
    return [
        'EARSIVFATURA' => 'e-Arşiv fatura (e-Fatura mükellefi olmayan alıcı)',
        'TEMELFATURA'  => 'e-Fatura · Temel (alıcı e-Fatura mükellefi)',
        'TICARIFATURA' => 'e-Fatura · Ticari (alıcı e-Fatura mükellefi)',
    ];
}

function fatura_tipleri(): array
{
    return ['SATIS' => 'Satış', 'IADE' => 'İade'];
}

function fatura_durumlari(): array
{
    return [
        'taslak'     => ['Taslak', 'amber'],
        'hazir'      => ['Hazır (kontrol edildi)', 'blue'],
        'gonderildi' => ['GİB\'e gönderildi', 'green'],
        'iptal'      => ['İptal', 'gray'],
        'hata'       => ['Hata', 'red'],
    ];
}

/** KDV oranı seçenekleri (%). Varsayılan ayardan gelir. */
function fatura_kdv_oranlari(): array
{
    return [0 => '%0', 1 => '%1', 10 => '%10', 20 => '%20'];
}

function fatura_varsayilan_kdv(): float
{
    $v = (float) setting('fatura_kdv', '10');
    return isset(fatura_kdv_oranlari()[(int) $v]) ? $v : 10.0;
}

/** Satıcı (mağaza) bilgileri — Ayarlar › e-Fatura. */
function fatura_firma(): array
{
    return [
        'unvan'         => setting('firma_unvan', ''),
        'kimlik'        => preg_replace('/\D/', '', setting('firma_vkn', '')) ?? '',
        'ad'            => setting('firma_ad', ''),       // şahıs şirketi: vergi kimliği TCKN ise
        'soyad'         => setting('firma_soyad', ''),
        'vergi_dairesi' => setting('firma_vergi_dairesi', ''),
        'adres'         => setting('firma_adres', ''),
        'ilce'          => setting('firma_ilce', ''),
        'il'            => setting('firma_il', ''),
        'posta_kodu'    => setting('firma_posta_kodu', ''),
        'eposta'        => setting('firma_eposta', ''),
        'telefon'       => setting('firma_telefon', setting('shop_phone', '')),
        'web'           => setting('firma_web', ''),
        'mersis'        => preg_replace('/\D/', '', setting('firma_mersis', '')) ?? '',
        'seri'          => fatura_seri(),
    ];
}

function fatura_seri(): string
{
    $s = strtoupper(setting('fatura_seri', 'OPT'));
    return preg_match('/^[A-Z0-9]{3}$/', $s) ? $s : 'OPT';
}

/* ---------------- Kimlik numarası kontrolleri ---------------- */

/** T.C. kimlik numarası algoritma kontrolü. */
function tckn_gecerli(string $t): bool
{
    if (!preg_match('/^[1-9]\d{10}$/', $t)) {
        return false;
    }
    $d = array_map('intval', str_split($t));
    $tek = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
    $cift = $d[1] + $d[3] + $d[5] + $d[7];
    if (((($tek * 7) - $cift) % 10 + 10) % 10 !== $d[9]) {
        return false;
    }
    return array_sum(array_slice($d, 0, 10)) % 10 === $d[10];
}

/** Vergi kimlik numarası (10 hane) algoritma kontrolü. */
function vkn_gecerli(string $v): bool
{
    if (!preg_match('/^\d{10}$/', $v)) {
        return false;
    }
    $toplam = 0;
    for ($i = 0; $i < 9; $i++) {
        $tmp = ((int) $v[$i] + 9 - $i) % 10;
        $ara = ($tmp * (2 ** (9 - $i))) % 9;
        if ($tmp !== 0 && $ara === 0) {
            $ara = 9;
        }
        $toplam += $ara;
    }
    $kontrol = (10 - ($toplam % 10)) % 10;
    return $kontrol === (int) $v[9];
}

/** 'VKN' | 'TCKN' | '' (geçersiz). */
function kimlik_turu(string $no): string
{
    if (strlen($no) === 10 && vkn_gecerli($no)) {
        return 'VKN';
    }
    if (strlen($no) === 11 && ($no === TCKN_BILINMIYOR || tckn_gecerli($no))) {
        return 'TCKN';
    }
    return '';
}

/* ---------------- Taslak oluşturma ---------------- */

/** SGK payı faturası için alıcı ön bilgisi (MALİ MÜŞAVİRLE DOĞRULAYIN — ayarlardan değiştirilebilir). */
function fatura_sgk_alici(): array
{
    return [
        'alici_tip'           => 'kurum',
        'alici_unvan'         => setting('sgk_fatura_unvan', 'SOSYAL GÜVENLİK KURUMU GENEL SAĞLIK SİGORTASI GENEL MÜDÜRLÜĞÜ'),
        'alici_kimlik'        => preg_replace('/\D/', '', setting('sgk_fatura_vkn', '7750409379')) ?? '',
        'alici_vergi_dairesi' => setting('sgk_fatura_vergi_dairesi', 'Başkent'),
        'alici_adres'         => setting('sgk_fatura_adres', 'Ziyabey Cad. No: 6 Balgat'),
        'alici_ilce'          => setting('sgk_fatura_ilce', 'Çankaya'),
        'alici_il'            => setting('sgk_fatura_il', 'Ankara'),
    ];
}

/** Siparişin satır açıklaması (cam tipi, çerçeve, işlem türü). */
function fatura_siparis_aciklamasi(array $o): string
{
    $parca = [transaction_type_label($o['transaction_type'] ?? 'gozluk')];
    if (!empty($o['lens_type'])) {
        $parca[] = (string) $o['lens_type'];
    }
    if (!empty($o['frame_info'])) {
        $parca[] = 'Çerçeve: ' . $o['frame_info'];
    }
    return mb_substr(implode(' · ', $parca) . ' (' . order_no((int) $o['id']) . ')', 0, 200);
}

/**
 * Siparişten MÜŞTERİ (hasta payı) faturası taslağı.
 * 4.16.1: SGK payı sipariş bazında faturalanmaz; ay sonunda tüm reçeteler TEK faturada SGK'ya
 * kesilir (fatura_sgk_donem_taslagi). $sgkDus true ise SGK payı hasta faturasından düşülür.
 * Dönüş: oluşturulan fatura id'leri (tek eleman).
 */
function fatura_siparisten_taslak(int $siparisId, bool $sgkDus = true): array
{
    $o = find_order($siparisId);
    if (!$o) {
        throw new DomainException('Sipariş bulunamadı.');
    }
    if ($o['order_stage'] === 'iptal') {
        throw new DomainException('İptal edilmiş sipariş faturalanamaz.');
    }
    $acik = (int) scalar("SELECT COUNT(*) FROM faturalar WHERE order_id = ? AND durum IN ('taslak','hazir','gonderildi')", [$siparisId]);
    if ($acik > 0) {
        throw new DomainException('Bu siparişin zaten bir faturası/taslağı var.');
    }
    $toplam = round((float) $o['total_amount'], 2);
    $sgk = round((float) $o['sgk_amount'], 2);
    $hasta = $sgkDus ? round($toplam - $sgk, 2) : $toplam;
    if ($toplam <= 0) {
        throw new DomainException('Sipariş tutarı sıfır; faturalanacak tutar yok.');
    }
    if ($hasta <= 0.009) {
        throw new DomainException('Hasta payı yok (tutarın tamamı SGK\'dan). SGK payı ay sonu toplu SGK faturasına girer.');
    }
    $kdv = ($o['transaction_type'] ?? '') === 'gunes_gozlugu' ? 20.0 : fatura_varsayilan_kdv();
    $aciklama = fatura_siparis_aciklamasi($o);
    // Müşterinin önceki faturasındaki alıcı bilgileri (adres, TCKN) yeniden kullanılır.
    $onceki = row("SELECT * FROM faturalar WHERE customer_id = ? AND alici_tip = 'kisi' ORDER BY id DESC LIMIT 1", [(int) $o['customer_id']]) ?: [];
    $id = fatura_taslak_yaz([
        'order_id'    => $siparisId,
        'customer_id' => (int) $o['customer_id'],
        'alici_tip'   => 'kisi',
        'alici_ad'    => $o['c_first'],
        'alici_soyad' => $o['c_last'],
        'alici_telefon' => $o['c_phone'],
        'alici_kimlik'  => $onceki['alici_kimlik'] ?? null,
        'alici_adres'   => $onceki['alici_adres'] ?? null,
        'alici_ilce'    => $onceki['alici_ilce'] ?? null,
        'alici_il'      => $onceki['alici_il'] ?? null,
        'alici_eposta'  => $onceki['alici_eposta'] ?? null,
        'notlar'        => $sgkDus && $sgk > 0.009 ? 'SGK katkı payı (' . money($sgk) . ') ay sonu toplu SGK faturasına girer.' : null,
    ], [['ad' => $aciklama, 'miktar' => 1, 'kdv_dahil' => $hasta, 'kdv_orani' => $kdv]]);
    audit('fatura_taslak', 'order', $siparisId, ['taslak' => 1]);
    return [$id];
}

/* ---------------- SGK ay sonu toplu faturası (4.16.1) ---------------- */

/** 'YYYY-MM' doğrula; geçersizse geçen ay. */
function fatura_sgk_ay(string $ay): string
{
    if (preg_match('/^(\d{4})-(\d{2})$/', $ay, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12 && (int) $m[1] >= 2000) {
        return $ay;
    }
    return date('Y-m', strtotime('first day of last month'));
}

function fatura_sgk_ay_adi(string $ay): string
{
    $aylar = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    return $aylar[(int) substr($ay, 5, 2)] . ' ' . substr($ay, 0, 4);
}

/** Eski usul (4.12–4.16.0) sipariş bazında açılmış, iptal edilmemiş SGK taslakları. */
function fatura_sgk_eski_taslaklar(): array
{
    $vkn = fatura_sgk_alici()['alici_kimlik'];
    return rows("SELECT id, order_id, genel_toplam, durum FROM faturalar
                  WHERE order_id IS NOT NULL AND sgk_donem IS NULL AND alici_tip = 'kurum' AND alici_kimlik = ? AND durum IN ('taslak','hazir')
                  ORDER BY id", [$vkn]);
}

/** Eski usul SGK taslaklarını iptal eder. Dönüş: iptal edilen adet. */
function fatura_sgk_eski_taslaklari_iptal(): int
{
    $n = 0;
    foreach (fatura_sgk_eski_taslaklar() as $f) {
        $n += q("UPDATE faturalar SET durum = 'iptal', xml = NULL WHERE id = ? AND durum IN ('taslak','hazir')", [(int) $f['id']])->rowCount();
    }
    return $n;
}

/**
 * Dönemin SGK faturasına girebilecek siparişler: SGK payı olan, MEDULA'YA İŞLENDİ işaretli,
 * işlem tarihi dönemin sonundan önce olan ve henüz iptal edilmemiş bir SGK faturasına girmemiş
 * siparişler. Önceki aylardan faturalanmamış kalanlar da gelir ('onceki' = 1).
 */
function fatura_sgk_donem_siparisleri(string $ay): array
{
    $ay = fatura_sgk_ay($ay);
    $bas = $ay . '-01 00:00:00';
    $son = date('Y-m-d', strtotime($ay . '-01 +1 month')) . ' 00:00:00';
    $vkn = fatura_sgk_alici()['alici_kimlik'];
    $r = rows(
        "SELECT o.id, o.delivered_at, o.medula_islendi_at, o.sgk_amount, o.sgk_erecete, o.transaction_type, c.first_name, c.last_name
           FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
          WHERE o.order_stage <> 'iptal' AND o.sgk_amount > 0 AND o.medula_islendi_at IS NOT NULL AND o.medula_islendi_at < ?
            AND NOT EXISTS (SELECT 1 FROM fatura_sgk_siparisleri x JOIN faturalar f ON f.id = x.fatura_id WHERE x.order_id = o.id AND f.durum <> 'iptal')
            AND NOT EXISTS (SELECT 1 FROM faturalar f2 WHERE f2.order_id = o.id AND f2.sgk_donem IS NULL AND f2.alici_tip = 'kurum' AND f2.alici_kimlik = ? AND f2.durum = 'gonderildi')
          ORDER BY o.medula_islendi_at, o.id",
        [$son, $vkn]
    );
    foreach ($r as &$o) {
        $o['onceki'] = (string) $o['medula_islendi_at'] < $bas ? 1 : 0;
        $o['kdv'] = ($o['transaction_type'] ?? '') === 'gunes_gozlugu' ? 20.0 : fatura_varsayilan_kdv();
    }
    unset($o);
    return $r;
}

/**
 * Dönemin SGK faturası taslağı: seçilen siparişler TEK faturada, KDV oranına göre satır.
 * $medulaToplam verilirse (Medula'nın dönem fatura tutarı) tek KDV oranlı dönemde satır tutarı odur.
 * Dönüş: fatura id.
 */
function fatura_sgk_donem_taslagi(string $ay, array $siparisIdler, ?float $medulaToplam = null): int
{
    $ay = fatura_sgk_ay($ay);
    $adaylar = array_column(fatura_sgk_donem_siparisleri($ay), null, 'id');
    $secilen = [];
    foreach (array_unique(array_map('intval', $siparisIdler)) as $sid) {
        if (!isset($adaylar[$sid])) {
            throw new DomainException('Sipariş ' . order_no($sid) . ' bu döneme eklenemez (teslim edilmemiş, SGK payı yok ya da başka SGK faturasında).');
        }
        $secilen[] = $adaylar[$sid];
    }
    if (!$secilen) {
        throw new DomainException('Faturaya girecek reçete seçin.');
    }
    $gruplar = [];
    foreach ($secilen as $o) {
        $k = (string) (float) $o['kdv'];
        $gruplar[$k]['kdv'] = (float) $o['kdv'];
        $gruplar[$k]['tutar'] = round(($gruplar[$k]['tutar'] ?? 0) + (float) $o['sgk_amount'], 2);
        $gruplar[$k]['adet'] = ($gruplar[$k]['adet'] ?? 0) + 1;
    }
    $hesap = round(array_sum(array_column($gruplar, 'tutar')), 2);
    if ($medulaToplam !== null) {
        if (count($gruplar) > 1) {
            throw new DomainException('Farklı KDV oranlı reçeteler var; Medula toplamını satırlarda elle düzeltin.');
        }
        if ($medulaToplam <= 0) {
            throw new DomainException('Medula toplamı 0\'dan büyük olmalı.');
        }
        $gruplar[array_key_first($gruplar)]['tutar'] = round($medulaToplam, 2);
    }
    $adi = fatura_sgk_ay_adi($ay);
    $satirlar = [];
    foreach ($gruplar as $g) {
        $satirlar[] = ['ad' => 'Optik reçete bedeli (SGK katkı payı) · ' . $adi . ' · ' . $g['adet'] . ' reçete', 'miktar' => 1, 'kdv_dahil' => $g['tutar'], 'kdv_orani' => $g['kdv']];
    }
    $not = $adi . ' dönemi · ' . count($secilen) . ' reçete · reçete dökümü ektedir.';
    if ($medulaToplam !== null && abs($medulaToplam - $hesap) > 0.009) {
        $not .= ' (OptiFlow toplamı ' . money($hesap) . ', Medula toplamı esas alındı.)';
    }
    return transaction(static function () use ($ay, $secilen, $satirlar, $not): int {
        $id = fatura_taslak_yaz(fatura_sgk_alici() + ['profil' => 'TEMELFATURA', 'notlar' => mb_substr($not, 0, 500)], $satirlar);
        q('UPDATE faturalar SET sgk_donem = ? WHERE id = ?', [$ay, $id]);
        $vkn = fatura_sgk_alici()['alici_kimlik'];
        foreach ($secilen as $o) {
            insert('fatura_sgk_siparisleri', ['fatura_id' => $id, 'order_id' => (int) $o['id'], 'tutar' => round((float) $o['sgk_amount'], 2)]);
            // Eski usul (sipariş bazlı) SGK taslağı varsa iptal: aynı reçete iki faturada kalmasın.
            q("UPDATE faturalar SET durum = 'iptal', xml = NULL WHERE order_id = ? AND sgk_donem IS NULL AND alici_tip = 'kurum' AND alici_kimlik = ? AND durum IN ('taslak','hazir')", [(int) $o['id'], $vkn]);
        }
        return $id;
    });
}

/** Faturaya giren reçeteler (döküm). */
function fatura_sgk_dokum(int $faturaId): array
{
    return rows(
        'SELECT x.order_id, x.tutar, o.delivered_at, o.medula_islendi_at, o.sgk_erecete, c.first_name, c.last_name
           FROM fatura_sgk_siparisleri x JOIN orders o ON o.id = x.order_id LEFT JOIN customers c ON c.id = o.customer_id
          WHERE x.fatura_id = ? ORDER BY o.medula_islendi_at, x.order_id',
        [$faturaId]
    );
}

/**
 * Medula dökümüyle (PDF ya da yapıştırılan metin) karşılaştırma.
 * $numaralar: dökümde bulunan e-reçete numaraları. Dönemin OptiFlow kümesi: o ay Medula'ya işlendi
 * işaretlenen (iptal olmayan, SGK'lı) siparişler.
 */
function fatura_sgk_medula_karsilastir(string $ay, array $numaralar): array
{
    $ay = fatura_sgk_ay($ay);
    $bas = $ay . '-01 00:00:00';
    $son = date('Y-m-d', strtotime($ay . '-01 +1 month')) . ' 00:00:00';
    $donem = rows(
        "SELECT o.id, o.sgk_erecete, o.medula_islendi_at, c.first_name, c.last_name FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
          WHERE o.order_stage <> 'iptal' AND o.medula_islendi_at >= ? AND o.medula_islendi_at < ? AND (o.sgk_amount > 0 OR (o.sgk_erecete IS NOT NULL AND o.sgk_erecete <> ''))
          ORDER BY o.medula_islendi_at, o.id",
        [$bas, $son]
    );
    $pdf = array_fill_keys(array_map(static fn($n): string => strtoupper((string) $n), $numaralar), true);
    $eslesen = [];
    $optiflowdaFazla = [];
    $numarasiz = [];
    $donemNo = [];
    foreach ($donem as $o) {
        $no = strtoupper(trim((string) $o['sgk_erecete']));
        if ($no === '') {
            $numarasiz[] = $o;
        } elseif (isset($pdf[$no])) {
            $eslesen[$no] = $o;
            $donemNo[$no] = true;
        } else {
            $optiflowdaFazla[] = $o;
            $donemNo[$no] = true;
        }
    }
    $pdfteFazla = [];
    foreach (array_keys($pdf) as $no) {
        if (!isset($donemNo[$no])) {
            $pdfteFazla[$no] = row(
                "SELECT o.id, o.medula_islendi_at, o.order_stage, c.first_name, c.last_name FROM orders o LEFT JOIN customers c ON c.id = o.customer_id
                  WHERE UPPER(o.sgk_erecete) = ? AND o.order_stage <> 'iptal' ORDER BY o.id DESC LIMIT 1",
                [$no]
            );
        }
    }
    return [
        'pdf_adet'       => count($pdf),
        'optiflow_adet'  => count($donem),
        'eslesen'        => $eslesen,
        'pdfte_fazla'    => $pdfteFazla,      // no => sipariş (başka ayda işaretli / işaretsiz) ya da null (OptiFlow'da yok)
        'optiflowda_fazla' => $optiflowdaFazla,
        'numarasiz'      => $numarasiz,
    ];
}

/** Dönemin (iptal edilmemiş) SGK faturaları. */
function fatura_sgk_donem_faturalari(string $ay): array
{
    return rows("SELECT f.*, (SELECT COUNT(*) FROM fatura_sgk_siparisleri x WHERE x.fatura_id = f.id) AS adet
                   FROM faturalar f WHERE f.sgk_donem = ? AND f.durum <> 'iptal' ORDER BY f.id", [fatura_sgk_ay($ay)]);
}

/** Boş taslak (siparişsiz). */
function fatura_bos_taslak(): int
{
    return fatura_taslak_yaz(['alici_tip' => 'kisi'], []);
}

/** Taslak + satırlar yazar; satırlarda 'kdv_dahil' verilirse birim fiyat KDV hariç hesaplanır. */
function fatura_taslak_yaz(array $baslik, array $satirlar): int
{
    $veri = array_intersect_key($baslik, array_flip([
        'order_id', 'customer_id', 'profil', 'tip', 'alici_tip', 'alici_ad', 'alici_soyad', 'alici_unvan', 'alici_kimlik',
        'alici_vergi_dairesi', 'alici_adres', 'alici_ilce', 'alici_il', 'alici_eposta', 'alici_telefon', 'notlar',
    ]));
    $id = insert('faturalar', $veri + [
        'uuid'       => fatura_uuid(),
        'durum'      => 'taslak',
        'created_by' => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    $sira = 1;
    foreach ($satirlar as $s) {
        $kdv = (float) ($s['kdv_orani'] ?? fatura_varsayilan_kdv());
        $birim = isset($s['kdv_dahil'])
            ? round((float) $s['kdv_dahil'] / max(0.0001, (float) ($s['miktar'] ?? 1)) / (1 + $kdv / 100), 4)
            : (float) ($s['birim_fiyat'] ?? 0);
        insert('fatura_satirlari', [
            'fatura_id'   => $id,
            'sira'        => $sira++,
            'ad'          => mb_substr((string) $s['ad'], 0, 200),
            'miktar'      => (float) ($s['miktar'] ?? 1),
            'birim'       => 'C62',
            'birim_fiyat' => $birim,
            'iskonto'     => 0,
            'kdv_orani'   => $kdv,
        ]);
    }
    fatura_hesapla($id);
    return $id;
}

function fatura_uuid(): string
{
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

/**
 * Satır ve toplamları yeniden hesaplar (kuruş yuvarlaması satır bazında).
 * KDV-dahil tutarı tam tutturmak için son satırda 1 kuruşluk fark matrahta düzeltilmez;
 * yuvarlama farkı genel toplamda görünür ve kullanıcıya gösterilir.
 */
function fatura_hesapla(int $id): array
{
    $ara = 0.0;
    $isk = 0.0;
    $kdvT = 0.0;
    foreach (rows('SELECT * FROM fatura_satirlari WHERE fatura_id = ? ORDER BY sira, id', [$id]) as $s) {
        $brut = round((float) $s['miktar'] * (float) $s['birim_fiyat'], 2);
        $iskonto = min($brut, round((float) $s['iskonto'], 2));
        $tutar = round($brut - $iskonto, 2);
        $kdv = round($tutar * (float) $s['kdv_orani'] / 100, 2);
        q('UPDATE fatura_satirlari SET tutar = ?, kdv_tutar = ? WHERE id = ?', [$tutar, $kdv, (int) $s['id']]);
        $ara += $brut;
        $isk += $iskonto;
        $kdvT += $kdv;
    }
    $toplamlar = [
        'ara_toplam'     => round($ara, 2),
        'iskonto_toplam' => round($isk, 2),
        'kdv_toplam'     => round($kdvT, 2),
        'genel_toplam'   => round($ara - $isk + $kdvT, 2),
    ];
    update('faturalar', $toplamlar, 'id = ?', [$id]);
    return $toplamlar;
}

/** KDV oranına göre alt toplamlar: [oran => ['matrah' => , 'kdv' => ]] */
function fatura_kdv_dagilimi(int $id): array
{
    $d = [];
    foreach (rows('SELECT kdv_orani, SUM(tutar) AS matrah, SUM(kdv_tutar) AS kdv FROM fatura_satirlari WHERE fatura_id = ? GROUP BY kdv_orani ORDER BY kdv_orani', [$id]) as $r) {
        $d[(string) (float) $r['kdv_orani']] = ['matrah' => round((float) $r['matrah'], 2), 'kdv' => round((float) $r['kdv'], 2)];
    }
    return $d;
}

/** Satıcı bilgilerindeki eksikler. */
function fatura_firma_eksikleri(): array
{
    $h = [];
    $firma = fatura_firma();
    if ($firma['unvan'] === '' && ($firma['ad'] === '' || $firma['soyad'] === '')) {
        $h[] = 'Satıcı unvanı (ya da şahıs şirketi için ad/soyad) girilmemiş — Ayarlar › e-Fatura.';
    }
    if (kimlik_turu($firma['kimlik']) === '' || $firma['kimlik'] === TCKN_BILINMIYOR) {
        $h[] = 'Satıcı VKN/TCKN geçersiz — Ayarlar › e-Fatura.';
    }
    if ($firma['vergi_dairesi'] === '' || $firma['il'] === '' || $firma['ilce'] === '' || $firma['adres'] === '') {
        $h[] = 'Satıcı vergi dairesi, adres, ilçe ve il zorunlu — Ayarlar › e-Fatura.';
    }
    return $h;
}

/**
 * "Hazır" olmadan önce eksik/yanlışları listeler. Boş dizi = sorun yok.
 */
function fatura_kontrol(array $f, array $satirlar): array
{
    $h = fatura_firma_eksikleri();
    $kimlik = (string) ($f['alici_kimlik'] ?? '');
    $tur = kimlik_turu($kimlik);
    if ($f['alici_tip'] === 'kurum') {
        if (trim((string) $f['alici_unvan']) === '') {
            $h[] = 'Alıcı unvanı zorunlu.';
        }
        if ($tur !== 'VKN') {
            $h[] = 'Kurum alıcı için geçerli 10 haneli VKN gerekli.';
        }
        if (trim((string) $f['alici_vergi_dairesi']) === '') {
            $h[] = 'Kurum alıcı için vergi dairesi zorunlu.';
        }
    } else {
        if (trim((string) $f['alici_ad']) === '' || trim((string) $f['alici_soyad']) === '') {
            $h[] = 'Alıcı ad ve soyadı zorunlu.';
        }
        if ($tur !== 'TCKN') {
            $h[] = 'Alıcı T.C. kimlik numarası geçersiz (bilinmiyorsa 11111111111 yazılabilir).';
        }
    }
    if (trim((string) $f['alici_il']) === '' || trim((string) $f['alici_ilce']) === '') {
        $h[] = 'Alıcı il ve ilçe zorunlu.';
    }
    if ($f['profil'] !== 'EARSIVFATURA' && $f['alici_tip'] !== 'kurum' && $kimlik === TCKN_BILINMIYOR) {
        $h[] = 'e-Fatura profili kayıtlı mükellef alıcı ister; bireysel alıcıda e-Arşiv seçin.';
    }
    if (!$satirlar) {
        $h[] = 'En az bir fatura satırı gerekli.';
    }
    foreach ($satirlar as $s) {
        if ((float) $s['miktar'] <= 0 || (float) $s['birim_fiyat'] < 0) {
            $h[] = 'Satır ' . (int) $s['sira'] . ': miktar ve birim fiyat geçersiz.';
        }
        if (!isset(fatura_kdv_oranlari()[(int) $s['kdv_orani']])) {
            $h[] = 'Satır ' . (int) $s['sira'] . ': KDV oranı geçersiz.';
        }
    }
    if ((float) $f['genel_toplam'] <= 0) {
        $h[] = 'Fatura toplamı sıfırdan büyük olmalı.';
    }
    return $h;
}

/* ---------------- UBL-TR 1.2 XML ---------------- */

/** Taslak ID'si: numara entegratörde verilir; önizlemede seri + yıl + sıfırlar. */
function fatura_gorunen_no(array $f): string
{
    if (!empty($f['fatura_no'])) {
        return (string) $f['fatura_no'];
    }
    $yil = substr((string) ($f['duzenleme'] ?: date('Y-m-d')), 0, 4);
    return fatura_seri() . $yil . '000000000';
}

function ubl_tutar(float $v): string
{
    return number_format($v, 2, '.', '');
}

/**
 * UBL-TR 1.2 Invoice XML'i üretir (imzasız). Eleman sırası UBL 2.1 XSD sırasını izler.
 * Taslakta ID sıfırlıdır ve "TASLAK" notu eklenir.
 */
function fatura_ubl_xml(array $f, array $satirlar): string
{
    $firma = fatura_firma();
    $tarih = (string) ($f['duzenleme'] ?: date('Y-m-d H:i:s'));
    $x = new XMLWriter();
    $x->openMemory();
    $x->setIndent(true);
    $x->setIndentString('  ');
    $x->startDocument('1.0', 'UTF-8');
    $x->startElementNs(null, 'Invoice', UBL_NS_INVOICE);
    $x->writeAttribute('xmlns:cac', UBL_NS_CAC);
    $x->writeAttribute('xmlns:cbc', UBL_NS_CBC);

    $cbc = static function (string $ad, string $deger, array $oz = []) use ($x): void {
        $x->startElement('cbc:' . $ad);
        foreach ($oz as $k => $v) {
            $x->writeAttribute($k, $v);
        }
        $x->text($deger);
        $x->endElement();
    };
    $tutar = static function (string $ad, float $v) use ($cbc): void {
        $cbc($ad, ubl_tutar($v), ['currencyID' => 'TRY']);
    };

    $cbc('UBLVersionID', '2.1');
    $cbc('CustomizationID', 'TR1.2');
    $cbc('ProfileID', (string) $f['profil']);
    $cbc('ID', fatura_gorunen_no($f));
    $cbc('CopyIndicator', 'false');
    $cbc('UUID', (string) $f['uuid']);
    $cbc('IssueDate', substr($tarih, 0, 10));
    $cbc('IssueTime', substr($tarih, 11, 8) ?: '00:00:00');
    $cbc('InvoiceTypeCode', (string) $f['tip']);
    if (empty($f['fatura_no'])) {
        $cbc('Note', 'TASLAK - Bu belge GİB\'e gönderilmemiştir, resmi fatura değildir.');
    }
    if (!empty($f['notlar'])) {
        $cbc('Note', (string) $f['notlar']);
    }
    $cbc('Note', 'Yalnız: ' . fatura_tutar_yaziyla((float) $f['genel_toplam']));
    $cbc('DocumentCurrencyCode', 'TRY');
    $cbc('LineCountNumeric', (string) count($satirlar));

    if ($f['profil'] === 'EARSIVFATURA') {
        $x->startElement('cac:AdditionalDocumentReference');
        $cbc('ID', 'SendType');
        $cbc('IssueDate', substr($tarih, 0, 10));
        $cbc('DocumentType', (string) ($f['gonderim_sekli'] ?: 'ELEKTRONIK'));
        $x->endElement();
    }

    // Satıcı
    $x->startElement('cac:AccountingSupplierParty');
    $x->startElement('cac:Party');
    if ($firma['web'] !== '') {
        $cbc('WebsiteURI', $firma['web']);
    }
    $x->startElement('cac:PartyIdentification');
    $cbc('ID', $firma['kimlik'], ['schemeID' => strlen($firma['kimlik']) === 11 ? 'TCKN' : 'VKN']);
    $x->endElement();
    if ($firma['mersis'] !== '') {
        $x->startElement('cac:PartyIdentification');
        $cbc('ID', $firma['mersis'], ['schemeID' => 'MERSISNO']);
        $x->endElement();
    }
    if ($firma['unvan'] !== '') {
        $x->startElement('cac:PartyName');
        $cbc('Name', $firma['unvan']);
        $x->endElement();
    }
    ubl_adres($x, $cbc, $firma['adres'], $firma['ilce'], $firma['il'], $firma['posta_kodu']);
    ubl_vergi_dairesi($x, $cbc, $firma['vergi_dairesi']);
    ubl_iletisim($x, $cbc, $firma['telefon'], $firma['eposta']);
    if (strlen($firma['kimlik']) === 11) {
        $x->startElement('cac:Person');
        $cbc('FirstName', $firma['ad']);
        $cbc('FamilyName', $firma['soyad']);
        $x->endElement();
    }
    $x->endElement(); // Party
    $x->endElement(); // AccountingSupplierParty

    // Alıcı
    $kurum = $f['alici_tip'] === 'kurum';
    $kimlik = (string) ($f['alici_kimlik'] ?: TCKN_BILINMIYOR);
    $x->startElement('cac:AccountingCustomerParty');
    $x->startElement('cac:Party');
    $x->startElement('cac:PartyIdentification');
    $cbc('ID', $kimlik, ['schemeID' => strlen($kimlik) === 10 ? 'VKN' : 'TCKN']);
    $x->endElement();
    if ($kurum) {
        $x->startElement('cac:PartyName');
        $cbc('Name', (string) $f['alici_unvan']);
        $x->endElement();
    }
    ubl_adres($x, $cbc, (string) $f['alici_adres'], (string) $f['alici_ilce'], (string) $f['alici_il'], '');
    ubl_vergi_dairesi($x, $cbc, (string) $f['alici_vergi_dairesi']);
    ubl_iletisim($x, $cbc, (string) $f['alici_telefon'], (string) $f['alici_eposta']);
    if (!$kurum) {
        $x->startElement('cac:Person');
        $cbc('FirstName', (string) $f['alici_ad']);
        $cbc('FamilyName', (string) $f['alici_soyad']);
        $x->endElement();
    }
    $x->endElement();
    $x->endElement();

    // Vergi toplamı
    $dagilim = [];
    foreach ($satirlar as $s) {
        $k = (string) (float) $s['kdv_orani'];
        $dagilim[$k]['matrah'] = ($dagilim[$k]['matrah'] ?? 0) + (float) $s['tutar'];
        $dagilim[$k]['kdv'] = ($dagilim[$k]['kdv'] ?? 0) + (float) $s['kdv_tutar'];
    }
    $x->startElement('cac:TaxTotal');
    $tutar('TaxAmount', (float) $f['kdv_toplam']);
    foreach ($dagilim as $oran => $d) {
        ubl_vergi_alt($x, $cbc, $tutar, (float) $d['matrah'], (float) $d['kdv'], (float) $oran);
    }
    $x->endElement();

    $x->startElement('cac:LegalMonetaryTotal');
    $tutar('LineExtensionAmount', (float) $f['ara_toplam']);
    $tutar('TaxExclusiveAmount', (float) $f['ara_toplam'] - (float) $f['iskonto_toplam']);
    $tutar('TaxInclusiveAmount', (float) $f['genel_toplam']);
    $tutar('AllowanceTotalAmount', (float) $f['iskonto_toplam']);
    $tutar('PayableAmount', (float) $f['genel_toplam']);
    $x->endElement();

    foreach ($satirlar as $s) {
        $x->startElement('cac:InvoiceLine');
        $cbc('ID', (string) (int) $s['sira']);
        $cbc('InvoicedQuantity', rtrim(rtrim(number_format((float) $s['miktar'], 3, '.', ''), '0'), '.'), ['unitCode' => (string) $s['birim']]);
        $tutar('LineExtensionAmount', (float) $s['tutar']);
        if ((float) $s['iskonto'] > 0) {
            $x->startElement('cac:AllowanceCharge');
            $cbc('ChargeIndicator', 'false');
            $tutar('Amount', (float) $s['iskonto']);
            $tutar('BaseAmount', round((float) $s['miktar'] * (float) $s['birim_fiyat'], 2));
            $x->endElement();
        }
        $x->startElement('cac:TaxTotal');
        $tutar('TaxAmount', (float) $s['kdv_tutar']);
        ubl_vergi_alt($x, $cbc, $tutar, (float) $s['tutar'], (float) $s['kdv_tutar'], (float) $s['kdv_orani']);
        $x->endElement();
        $x->startElement('cac:Item');
        $cbc('Name', (string) $s['ad']);
        $x->endElement();
        $x->startElement('cac:Price');
        $cbc('PriceAmount', number_format((float) $s['birim_fiyat'], 4, '.', ''), ['currencyID' => 'TRY']);
        $x->endElement();
        $x->endElement();
    }

    $x->endElement(); // Invoice
    $x->endDocument();
    return $x->outputMemory();
}

function ubl_adres(XMLWriter $x, callable $cbc, string $adres, string $ilce, string $il, string $posta): void
{
    $x->startElement('cac:PostalAddress');
    if ($adres !== '') {
        $cbc('StreetName', $adres);
    }
    $cbc('CitySubdivisionName', $ilce !== '' ? $ilce : '-');
    $cbc('CityName', $il !== '' ? $il : '-');
    if ($posta !== '') {
        $cbc('PostalZone', $posta);
    }
    $x->startElement('cac:Country');
    $cbc('Name', 'Türkiye');
    $x->endElement();
    $x->endElement();
}

function ubl_vergi_dairesi(XMLWriter $x, callable $cbc, string $vd): void
{
    if ($vd === '') {
        return;
    }
    $x->startElement('cac:PartyTaxScheme');
    $x->startElement('cac:TaxScheme');
    $cbc('Name', $vd);
    $x->endElement();
    $x->endElement();
}

function ubl_iletisim(XMLWriter $x, callable $cbc, string $tel, string $eposta): void
{
    if ($tel === '' && $eposta === '') {
        return;
    }
    $x->startElement('cac:Contact');
    if ($tel !== '') {
        $cbc('Telephone', $tel);
    }
    if ($eposta !== '') {
        $cbc('ElectronicMail', $eposta);
    }
    $x->endElement();
}

function ubl_vergi_alt(XMLWriter $x, callable $cbc, callable $tutar, float $matrah, float $kdv, float $oran): void
{
    $x->startElement('cac:TaxSubtotal');
    $tutar('TaxableAmount', $matrah);
    $tutar('TaxAmount', $kdv);
    $cbc('Percent', rtrim(rtrim(number_format($oran, 2, '.', ''), '0'), '.'));
    $x->startElement('cac:TaxCategory');
    $x->startElement('cac:TaxScheme');
    $cbc('Name', 'KDV');
    $cbc('TaxTypeCode', '0015');
    $x->endElement();
    $x->endElement();
    $x->endElement();
}

/** Fatura notu için okunuş (büyük harf). 4.15.1: senet.php'deki tutar_yaziyla() ile çakışmasın diye ayrı ad. */
function fatura_tutar_yaziyla(float $tutar): string
{
    $tl = (int) floor(round($tutar, 2));
    $kr = (int) round(($tutar - $tl) * 100);
    if ($kr === 100) {
        $tl++;
        $kr = 0;
    }
    $metin = ($tl > 0 ? fatura_sayi_yaziyla($tl) : 'SIFIR') . ' TÜRK LİRASI';
    if ($kr > 0) {
        $metin .= ' ' . fatura_sayi_yaziyla($kr) . ' KURUŞ';
    }
    return $metin;
}

function fatura_sayi_yaziyla(int $n): string
{
    $birler = ['', 'BİR', 'İKİ', 'ÜÇ', 'DÖRT', 'BEŞ', 'ALTI', 'YEDİ', 'SEKİZ', 'DOKUZ'];
    $onlar = ['', 'ON', 'YİRMİ', 'OTUZ', 'KIRK', 'ELLİ', 'ALTMIŞ', 'YETMİŞ', 'SEKSEN', 'DOKSAN'];
    $basamak = ['', 'BİN', 'MİLYON', 'MİLYAR'];
    if ($n === 0) {
        return 'SIFIR';
    }
    $parcalar = [];
    $i = 0;
    while ($n > 0 && $i < count($basamak)) {
        $uc = $n % 1000;
        $n = intdiv($n, 1000);
        if ($uc > 0) {
            $y = intdiv($uc, 100);
            $o = intdiv($uc % 100, 10);
            $b = $uc % 10;
            $s = ($y > 0 ? ($y > 1 ? $birler[$y] : '') . 'YÜZ' : '') . $onlar[$o] . $birler[$b];
            if ($i === 1 && $uc === 1) {
                $s = '';   // "BİRBİN" değil "BİN"
            }
            array_unshift($parcalar, $s . $basamak[$i]);
        }
        $i++;
    }
    return implode('', $parcalar);
}

/* ---------------- Entegratör sürücüsü (bir sonraki sürüm) ---------------- */

/**
 * Özel entegratör bağlantısı. Sonraki sürümde her entegratör (ör. REST veya SOAP servisi
 * sunan GİB onaylı özel entegratörler) bu arayüzü uygulayan tek bir sınıf olarak eklenir.
 */
interface EFaturaSurucu
{
    /** Ekranda görünen ad. */
    public function ad(): string;

    /** Bağlantı bilgileri tam ve servis erişilebilir mi? */
    public function hazir(): bool;

    /** Alıcı e-Fatura kayıtlı kullanıcısı mı? null = sorgulanamadı. */
    public function mukellefMi(string $vknTckn): ?bool;

    /**
     * Faturayı gönderir. Dönüş: ['ok' => bool, 'fatura_no' => ?string, 'ref' => ?string, 'hata' => ?string].
     * Numara ve imza entegratörde verilir.
     */
    public function gonder(array $fatura, string $ublXml): array;

    /** Gönderilmiş faturanın durumu: ['durum' => string, 'aciklama' => string]. */
    public function durum(array $fatura): array;
}

/** Bu sürümdeki tek sürücü: bağlı değil. Hiçbir şey göndermez. */
final class EFaturaBagliDegil implements EFaturaSurucu
{
    public function ad(): string { return 'Entegratör bağlı değil'; }
    public function hazir(): bool { return false; }
    public function mukellefMi(string $vknTckn): ?bool { return null; }
    public function gonder(array $fatura, string $ublXml): array
    {
        return ['ok' => false, 'fatura_no' => null, 'ref' => null, 'hata' => 'e-Fatura entegratörü henüz bağlanmadı. Bu sürümde faturalar taslak olarak hazırlanır.'];
    }
    public function durum(array $fatura): array { return ['durum' => (string) $fatura['durum'], 'aciklama' => 'Entegratör yok']; }
}

/** Kayıtlı sürücüler: anahtar → sınıf. Sonraki sürümde buraya satır eklenir. */
function efatura_suruculer(): array
{
    return ['yok' => EFaturaBagliDegil::class];
}

function efatura_surucu(): EFaturaSurucu
{
    $k = setting('efatura_entegrator', 'yok');
    $sinif = efatura_suruculer()[$k] ?? EFaturaBagliDegil::class;
    return new $sinif();
}
