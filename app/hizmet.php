<?php
declare(strict_types=1);

/* 4.48.0 — Tamir / hizmet fişi: tezgâhta bir dakikada kayıt, yarım A4 fiş (üst müşteri, alt mağaza — app/fis.php).
   Kayıt bir sipariştir (transaction_type = tamir; atölye panosunun "Tamir ve bakım" akışı aynen çalışır).
   orders.hizmet_detay (JSON): islemler (birden çok, service_types anahtarları), durumlar (teslim alırken görülenler —
   sonradan "bu çizik sizde oldu" tartışmasını önler). service_type = ilk işlem (eski ekranlar için).
   "Hemen yapıldı": vida / burunluk gibi bekletilmeyen iş; teslim edildi olarak açılır, ücreti varsa tahsil edilir. */

const HIZMET_DURUMLARI = [
    'cam_cizik'      => 'Camda çizik',
    'kaplama'        => 'Cam kaplaması bozuk',
    'cerceve_cizik'  => 'Çerçevede çizik / boya atığı',
    'catlak'         => 'Çatlak / kırık parça',
    'sap_gevsek'     => 'Sap gevşek / eğri',
    'eksik_parca'    => 'Eksik vida / burunluk',
];

/** Kayıt doğrulama ve sipariş açma. Dönüş: sipariş no (int). Kurala uymayan istek DomainException. */
function hizmet_kaydet(array $v, int $userId, string $kullaniciAd = ''): int
{
    $islemler = array_values(array_unique(array_filter((array) ($v['islemler'] ?? []), static fn($k) => isset(service_types()[$k]))));
    if (!$islemler) {
        throw new DomainException('En az bir işlem seçin.');
    }
    $durumlar = array_values(array_unique(array_filter((array) ($v['durumlar'] ?? []), static fn($k) => isset(HIZMET_DURUMLARI[$k]))));
    $hemen = !empty($v['hemen']);
    $ucretsiz = !empty($v['ucretsiz']);
    $ucret = $ucretsiz ? 0.0 : (float) ($v['ucret'] ?? 0);
    if ($ucret < 0) {
        throw new DomainException('Ücret geçersiz.');
    }
    $soz = trim((string) ($v['soz'] ?? ''));
    if (!$hemen && $soz !== '' && !valid_date($soz)) {
        throw new DomainException('Hazır olacağı tarih geçersiz.');
    }
    $yontem = (string) ($v['yontem'] ?? 'nakit');
    if (!isset(payment_methods()[$yontem])) {
        $yontem = 'nakit';
    }

    $cid = (int) ($v['customer_id'] ?? 0);
    if ($cid <= 0) {
        $ad = tr_title(trim((string) ($v['ad'] ?? '')));
        $soyad = tr_title(trim((string) ($v['soyad'] ?? '')));
        $tel = normalize_phone((string) ($v['telefon'] ?? ''));
        if ($ad === '' || $soyad === '') {
            throw new DomainException('Müşteri adı ve soyadı gerekli.');
        }
        if ($tel === null || $tel === '') {   // normalize_phone('') boş döner: tamirde telefon şart (hazır olunca aranır)
            throw new DomainException('Telefon numarası geçersiz. Örnek: 0532 111 22 33');
        }
    } elseif (!row('SELECT id FROM customers WHERE id = ?', [$cid])) {
        throw new DomainException('Müşteri bulunamadı.');
    }

    $simdi = date('Y-m-d H:i:s');
    return transaction(static function () use ($v, $cid, $islemler, $durumlar, $hemen, $ucretsiz, $ucret, $soz, $yontem, $userId, $kullaniciAd, $simdi): int {
        if ($cid <= 0) {
            $ad = tr_title(trim((string) $v['ad']));
            $soyad = tr_title(trim((string) $v['soyad']));
            $tel = (string) normalize_phone((string) $v['telefon']);
            $cid = (int) scalar('SELECT id FROM customers WHERE phone = ? AND first_name = ? AND last_name = ? LIMIT 1', [$tel, $ad, $soyad]);
            if (!$cid) {
                $cid = insert('customers', ['first_name' => $ad, 'last_name' => $soyad, 'phone' => $tel, 'created_by' => $userId, 'created_at' => $simdi]);
            }
        }
        $c = row('SELECT first_name, last_name, phone FROM customers WHERE id = ?', [$cid]);
        $asama = $hemen ? 'teslim_edildi' : 'siparis_verildi';
        $id = insert('orders', [
            'customer_id'      => $cid,
            'first_name'       => $c['first_name'],
            'last_name'        => $c['last_name'],
            'phone'            => $c['phone'],
            'total_amount'     => $ucret,
            'status'           => $asama,
            'order_stage'      => $asama,
            'transaction_type' => 'tamir',
            'service_type'     => $islemler[0],
            'is_free'          => $ucretsiz ? 1 : 0,
            'lens_type'        => '',
            'frame_info'       => mb_substr(trim((string) ($v['gozluk'] ?? '')), 0, 255),
            'notes'            => mb_substr(trim((string) ($v['sikayet'] ?? '')), 0, 2000) ?: null,
            'promised_date'    => $hemen ? null : ($soz !== '' ? $soz : null),
            'hizmet_detay'     => json_encode(['islemler' => $islemler, 'durumlar' => $durumlar, 'hemen' => $hemen], JSON_UNESCAPED_UNICODE),
            'sales_person'     => $kullaniciAd,
            'created_by'       => $userId,
            'created_at'       => $simdi,
            'delivered_at'     => $hemen ? $simdi : null,
            'delivered_by'     => $hemen ? $userId : null,
            'updated_at'       => $simdi,
        ]);
        $odeme = $hemen ? $ucret : min($ucret, max(0.0, (float) ($v['kapora'] ?? 0)));
        if ($odeme > 0.009) {
            insert('payments', ['order_id' => $id, 'amount' => $odeme, 'method' => $yontem, 'note' => $hemen ? 'Hizmet' : 'Kapora',
                'created_by' => $userId, 'created_at' => $simdi]);
        }
        return $id;
    });
}

/** Siparişin hizmet ayrıntısı: islemler, durumlar, hemen. Eski tamir kayıtlarında service_type tek işlem sayılır. */
function hizmet_detay(array $o): array
{
    $d = json_decode((string) ($o['hizmet_detay'] ?? ''), true);
    $d = is_array($d) ? $d : [];
    $islemler = array_values(array_filter((array) ($d['islemler'] ?? []), static fn($k) => isset(service_types()[$k])));
    if (!$islemler && !empty($o['service_type'])) {
        $islemler = [(string) $o['service_type']];
    }
    return [
        'islemler' => $islemler,
        'durumlar' => array_values(array_filter((array) ($d['durumlar'] ?? []), static fn($k) => isset(HIZMET_DURUMLARI[$k]))),
        'hemen'    => !empty($d['hemen']),
    ];
}

/** "Vida sıkma / değişimi + Burunluk değişimi" */
function hizmet_islem_ozeti(array $islemler): string
{
    return implode(' + ', array_map('service_type_label', $islemler)) ?: '—';
}

/** Bugünkü hizmet sayıları (bant): alınan, hemen yapılan, atölyede, hazır bekleyen. */
function hizmet_sayilar(?string $bugun = null): array
{
    $bugun ??= date('Y-m-d');
    $yarin = date('Y-m-d', strtotime($bugun . ' +1 day'));
    $s = static fn(string $kosul, array $p = []): int => (int) scalar("SELECT COUNT(*) FROM orders WHERE transaction_type = 'tamir' AND $kosul", $p);
    return [
        'bugun'    => $s('created_at >= ? AND created_at < ?', [$bugun . ' 00:00:00', $yarin . ' 00:00:00']),
        'hemen'    => $s("created_at >= ? AND created_at < ? AND order_stage = 'teslim_edildi' AND delivered_at = created_at", [$bugun . ' 00:00:00', $yarin . ' 00:00:00']),
        'bekleyen' => $s("order_stage IN ('siparis_verildi','bekliyor','rx_siparis_verildi','atolyede')"),
        'hazir'    => $s("order_stage = 'hazirlandi'"),
    ];
}
