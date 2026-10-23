<?php
declare(strict_types=1);

/* 4.44.0 — Zarfsız atölye: kâğıt iş emrindeki "Adım · Tamam · Paraf · Saat" tablosu ekrana taşındı.
   • Her adım bir dokunuşla kapanır; paraf = giriş yapan personel + saat (atolye_adimlari).
   • Adımlar aşamayı yürütür: Montaj → kontrole, Kontrol → Hazır (kutu/raf zorunlu), Haber → hazırdaki işe not.
   • Kutu / raf (orders.raf_yeri): zarfın yerine işi taşıyan numaralı tepsi; hazır olunca gözlüğün durduğu yer.
   • Cam geldi adımı elle atılmaz: camların geliş saatinden (arrived_at) okunur, paraf "Depo".
   Geri alma: yalnız SON adım, yalnız atan kişi ya da yönetici; aşama etkisi de geri döner. */

const ATOLYE_ADIMLARI = [
    'kesim'   => 'Kesim',
    'montaj'  => 'Montaj',
    'kontrol' => 'Kontrol · PD / aks',
    'haber'   => 'Müşteriye haber',
];

/** Kutu / raf yazımı: boşluklar sadeleşir, büyük harf (Türkçe i → İ), en çok 20 karakter. '' = yok. */
function atolye_raf(string $s): string
{
    $s = trim((string) preg_replace('/\s+/u', ' ', $s));
    $s = mb_strtoupper(strtr($s, ['i' => 'İ', 'ı' => 'I']));
    return mb_substr($s, 0, 20);
}

/** Açık işlerde kullanılmayan en küçük kutu numarası (yalnız rakamdan oluşan raf değerlerine bakar).
    $ayrilan: aynı ekranda başka işe önerilmiş numaralar (pano iki işe aynı kutuyu önermesin). */
function atolye_bos_kutu(array $ayrilan = []): int
{
    $dolu = array_fill_keys(array_map('intval', $ayrilan), true);
    foreach (rows("SELECT raf_yeri FROM orders WHERE raf_yeri IS NOT NULL AND order_stage NOT IN ('teslim_edildi','iptal')") as $r) {
        if (ctype_digit((string) $r['raf_yeri'])) {
            $dolu[(int) $r['raf_yeri']] = true;
        }
    }
    $n = 1;
    while (isset($dolu[$n])) {
        $n++;
    }
    return $n;
}

/** Siparişin atılmış adımları: adim => [user_id, ad, paraf (baş harfler), saat (Y-m-d H:i:s)]. */
function atolye_adimlari(int $orderId): array
{
    $sonuc = [];
    foreach (rows(
        'SELECT a.adim, a.user_id, a.created_at, u.full_name FROM atolye_adimlari a
         LEFT JOIN user_accounts u ON u.id = a.user_id WHERE a.order_id = ? ORDER BY a.id',
        [$orderId]
    ) as $r) {
        $ad = trim((string) ($r['full_name'] ?? ''));
        $parca = preg_split('/\s+/u', $ad) ?: [];
        $sonuc[$r['adim']] = [
            'user_id' => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'ad'      => $ad,
            'paraf'   => $ad !== '' ? initials((string) ($parca[0] ?? ''), (string) (count($parca) > 1 ? end($parca) : '')) : '—',
            'saat'    => (string) $r['created_at'],
        ];
    }
    return $sonuc;
}

/** Birden çok sipariş için adımlar (pano kartları): order_id => atolye_adimlari biçimi. */
function atolye_adimlari_toplu(array $orderIds): array
{
    $orderIds = array_values(array_unique(array_map('intval', $orderIds)));
    if (!$orderIds) {
        return [];
    }
    $sonuc = [];
    foreach (rows(
        'SELECT a.order_id, a.adim, a.user_id, a.created_at, u.full_name FROM atolye_adimlari a
         LEFT JOIN user_accounts u ON u.id = a.user_id WHERE a.order_id IN (' . in_placeholders($orderIds) . ') ORDER BY a.id',
        $orderIds
    ) as $r) {
        $ad = trim((string) ($r['full_name'] ?? ''));
        $parca = preg_split('/\s+/u', $ad) ?: [];
        $sonuc[(int) $r['order_id']][$r['adim']] = [
            'user_id' => $r['user_id'] !== null ? (int) $r['user_id'] : null,
            'ad'      => $ad,
            'paraf'   => $ad !== '' ? initials((string) ($parca[0] ?? ''), (string) (count($parca) > 1 ? end($parca) : '')) : '—',
            'saat'    => (string) $r['created_at'],
        ];
    }
    return $sonuc;
}

/** Camların durumu: ['durum' => 'yok' (cam kaydı yok) | 'eksik' | 'geldi', 'saat' => son gelen camın saati ya da '']. */
function atolye_cam_geldi(int $orderId): array
{
    $r = row(
        "SELECT COUNT(*) AS n, SUM(CASE WHEN i.stock_status = 'stokta_var' THEN 1 ELSE 0 END) AS gelen, MAX(i.arrived_at) AS son
         FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE r.order_id = ?",
        [$orderId]
    );
    $n = (int) ($r['n'] ?? 0);
    return [
        'durum' => $n === 0 ? 'yok' : ((int) $r['gelen'] < $n ? 'eksik' : 'geldi'),
        'saat'  => $n > 0 && $r['son'] ? (string) $r['son'] : '',
    ];
}

/** Sıradaki adım ('' = atölyede yapılacak adım kalmadı). Aşamaya ve atılmış adımlara bakar. */
function atolye_siradaki(array $order, array $adimlar): string
{
    $asama = (string) $order['order_stage'];
    $ws = (string) ($order['workshop_stage'] ?? '') ?: 'montajda';
    if ($asama === 'atolyede' && $ws === 'montajda') {
        return isset($adimlar['kesim']) ? 'montaj' : 'kesim';
    }
    if ($asama === 'atolyede' && $ws === 'kontrol') {
        return 'kontrol';
    }
    if ($asama === 'hazirlandi' && !isset($adimlar['haber'])) {
        return 'haber';
    }
    return '';
}

/**
 * Adımı atar (paraf). Dönüş: aşama etkisi — 'kayit' (yalnız paraf), 'kontrole', 'hazir'.
 * Kurala uymayan istek DomainException (kullanıcıya gösterilecek Türkçe ileti).
 */
function atolye_adim_at(array $order, string $adim, int $userId, string $raf = ''): string
{
    if (!isset(ATOLYE_ADIMLARI[$adim])) {
        throw new DomainException('Bilinmeyen atölye adımı.');
    }
    $id = (int) $order['id'];
    $adimlar = atolye_adimlari($id);
    if (isset($adimlar[$adim])) {
        throw new DomainException('“' . ATOLYE_ADIMLARI[$adim] . '” zaten işaretlenmiş (' . $adimlar[$adim]['paraf'] . ').');
    }
    $asama = (string) $order['order_stage'];
    $ws = (string) ($order['workshop_stage'] ?? '') ?: 'montajda';
    $simdi = date('Y-m-d H:i:s');
    $raf = atolye_raf($raf) ?: atolye_raf((string) ($order['raf_yeri'] ?? ''));

    if (in_array($adim, ['kesim', 'montaj'], true) && !($asama === 'atolyede' && $ws === 'montajda')) {
        throw new DomainException('Bu iş montaj aşamasında değil.');
    }
    if ($adim === 'montaj' && (int) ($order['own_frame_pending'] ?? 0) === 1) {
        throw new DomainException('Müşterinin çerçevesi henüz gelmedi; montaj işaretlenemez.');
    }
    if ($adim === 'kontrol' && !($asama === 'atolyede' && $ws === 'kontrol')) {
        throw new DomainException('Kontrol için önce montajın işaretlenmesi gerekir.');
    }
    if ($adim === 'kontrol' && $raf === '') {
        throw new DomainException('Hazır gözlüğün konduğu kutu / raf yazılmalı.');
    }
    if ($adim === 'haber' && $asama !== 'hazirlandi') {
        throw new DomainException('Müşteriye haber, gözlük hazır olunca işaretlenir.');
    }

    return transaction(static function () use ($id, $adim, $userId, $simdi, $raf, $order): string {
        insert('atolye_adimlari', ['order_id' => $id, 'adim' => $adim, 'user_id' => $userId, 'created_at' => $simdi]);
        if ($adim === 'montaj') {
            update('orders', [
                'workshop_stage' => 'kontrol',
                'assigned_to'    => ($order['assigned_to'] ?? null) ?: $userId,
                'updated_at'     => $simdi,
            ], 'id = ?', [$id]);
            return 'kontrole';
        }
        if ($adim === 'kontrol') {
            update('orders', [
                'order_stage'    => 'hazirlandi',
                'status'         => 'hazirlandi',
                'workshop_stage' => null,
                'qc_by'          => $userId,
                'qc_at'          => $simdi,
                'raf_yeri'       => $raf,
                'updated_at'     => $simdi,
            ], 'id = ?', [$id]);
            return 'hazir';
        }
        return 'kayit';
    });
}

/** Pano sürükle-bırak ve eski düğmeler aşamayı paraf atmadan ilerletir: o adım yoksa yine de kaydedilir. */
function atolye_adim_kaydet(int $orderId, string $adim, int $userId): void
{
    if (isset(ATOLYE_ADIMLARI[$adim]) && !scalar('SELECT COUNT(*) FROM atolye_adimlari WHERE order_id = ? AND adim = ?', [$orderId, $adim])) {
        insert('atolye_adimlari', ['order_id' => $orderId, 'adim' => $adim, 'user_id' => $userId, 'created_at' => date('Y-m-d H:i:s')]);
    }
}

/** Son adımı geri alır; aşama etkisi de geri döner. Yalnız adımı atan ya da yönetici. */
function atolye_adim_geri_al(array $order, string $adim, int $userId, bool $yonetici): void
{
    $id = (int) $order['id'];
    $son = row('SELECT id, adim, user_id FROM atolye_adimlari WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$id]);
    if (!$son || $son['adim'] !== $adim) {
        throw new DomainException('Yalnız son atılan adım geri alınabilir.');
    }
    if (!$yonetici && (int) $son['user_id'] !== $userId) {
        throw new DomainException('Bu adımı başka biri işaretledi; yalnız o kişi ya da yönetici geri alabilir.');
    }
    transaction(static function () use ($id, $son, $order): void {
        q('DELETE FROM atolye_adimlari WHERE id = ?', [(int) $son['id']]);
        $simdi = date('Y-m-d H:i:s');
        if ($son['adim'] === 'montaj' && $order['order_stage'] === 'atolyede' && ($order['workshop_stage'] ?? '') === 'kontrol') {
            update('orders', ['workshop_stage' => 'montajda', 'updated_at' => $simdi], 'id = ?', [$id]);
        }
        if ($son['adim'] === 'kontrol' && $order['order_stage'] === 'hazirlandi') {
            update('orders', [
                'order_stage' => 'atolyede', 'status' => 'atolyede', 'workshop_stage' => 'kontrol',
                'qc_by' => null, 'qc_at' => null, 'updated_at' => $simdi,
            ], 'id = ?', [$id]);
        }
    });
}

/** Kutu / raf kaydı ('' siler). Dönüş: kaydedilen değer. */
function atolye_raf_kaydet(int $orderId, string $raf): string
{
    $raf = atolye_raf($raf);
    update('orders', ['raf_yeri' => $raf !== '' ? $raf : null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$orderId]);
    return $raf;
}

/** Paraf damgası: "DY · 14:20" (bugün değilse "DY · 21.10 14:20"). */
function atolye_damga(array $a): string
{
    $ts = strtotime($a['saat']) ?: time();
    $saat = date('Y-m-d', $ts) === date('Y-m-d') ? date('H:i', $ts) : date('d.m H:i', $ts);
    return $a['paraf'] . ' · ' . $saat;
}
