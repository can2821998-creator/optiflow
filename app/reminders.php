<?php
declare(strict_types=1);

/* ==========================================================================
   Hatırlatma merkezi
   --------------------------------------------------------------------------
   Dört liste üretir:
     yenileme — son gözlüğünün üzerinden N ay geçmiş müşteriler
     sgk      — son reçetesinin üzerinden SGK hak süresi geçmiş müşteriler
     teslim   — hazır olduğu halde N gündür alınmamış gözlükler
     manuel   — personelin elle koyduğu "şu tarihte ara" kayıtları

   İlk üçü her açılışta veriden hesaplanır; `reminders` tablosu yalnızca
   "arandı / ertelendi / bir daha çıkmasın" kararlarını ve elle eklenen
   hatırlatmaları saklar. Böylece liste kendini günceller, kimse listeyi
   elle beslemek zorunda kalmaz.
   ========================================================================== */

function reminder_kinds(): array
{
    return [
        'yenileme' => 'Gözlük yenileme',
        'sgk'      => 'SGK hakkı',
        'teslim'   => 'Teslim alınmadı',
        'manuel'   => 'Planlı arama',
    ] + (function_exists('ozellik_acik') && ozellik_acik('lens_takip') ? ['lens' => 'Kontakt lens'] : []);
}

function reminder_kind_label(?string $k): string
{
    return reminder_kinds()[(string) $k] ?? 'Hatırlatma';
}

/** Ayarlardan süreler (ay / gün). */
function reminder_settings(): array
{
    return [
        'renew'     => max(3, min(120, (int) setting('reminder_renew_months', '24'))),
        'sgk'       => max(3, min(120, (int) setting('reminder_sgk_months', '36'))),
        'sgk_child' => max(3, min(120, (int) setting('reminder_sgk_child_months', '12'))),
        'pickup'    => max(1, min(120, (int) setting('reminder_pickup_days', '7'))),
    ];
}

/** Arandıktan sonra listeden ne kadar uzak durulur. */
function reminder_cooldown(string $kind): string
{
    return match ($kind) {
        'teslim' => '+10 days',
        'sgk'    => '+6 months',
        'lens'   => '+1 month',
        default  => '+6 months',
    };
}

/**
 * Otomatik listeler için "bu müşteri bu tür için susturulmuş mu?" haritası.
 * @return array<string, array> anahtar: "musteriId:tur"
 */
function reminder_state(array $customerIds, string $kind): array
{
    $customerIds = array_values(array_unique(array_map('intval', $customerIds)));
    if (!$customerIds) {
        return [];
    }
    $yer = implode(',', array_fill(0, count($customerIds), '?'));
    $satirlar = rows(
        "SELECT * FROM reminders WHERE kind = ? AND customer_id IN ($yer) ORDER BY id DESC",
        array_merge([$kind], $customerIds)
    );
    $harita = [];
    foreach ($satirlar as $r) {
        $anahtar = (int) $r['customer_id'] . ':' . $kind;
        if (!isset($harita[$anahtar])) {
            $harita[$anahtar] = $r;
        }
    }
    return $harita;
}

/** Kayıt listeden gizlenmeli mi? [gizle, sebep] */
function reminder_hidden(?array $r, string $kind): array
{
    if (!$r) {
        return [false, ''];
    }
    $bugun = date('Y-m-d');
    if ($r['status'] === 'kapali') {
        return [true, 'kapatıldı'];
    }
    if ($r['status'] === 'yapildi' && $r['done_at']) {
        $bitis = date('Y-m-d', strtotime($r['done_at'] . ' ' . reminder_cooldown($kind)));
        if ($bitis > $bugun) {
            return [true, date_tr($r['done_at']) . ' tarihinde arandı'];
        }
    }
    if ($r['status'] === 'bekliyor' && $r['due_date'] && $r['due_date'] > $bugun) {
        return [true, date_tr($r['due_date']) . ' tarihine ertelendi'];
    }
    return [false, ''];
}

/** İki tarih arası tam ay farkı. */
function reminder_months(string $tarih): int
{
    $t = strtotime($tarih);
    if (!$t) {
        return 0;
    }
    $fark = (new DateTime(date('Y-m-d', $t)))->diff(new DateTime(date('Y-m-d')));
    return max(0, $fark->y * 12 + $fark->m);
}

/**
 * Gözlüğünü yenileme zamanı gelenler.
 * Tamir/servis işlemleri sayılmaz; iptaller sayılmaz.
 */
function reminder_renewals(int $limit = 200): array
{
    $ay = reminder_settings()['renew'];
    $satirlar = rows(
        "SELECT c.id, c.first_name, c.last_name, c.phone, c.birth_year,
                MAX(o.created_at) AS son_tarih,
                COUNT(o.id) AS siparis_sayisi,
                SUBSTRING_INDEX(GROUP_CONCAT(o.id ORDER BY o.created_at DESC), ',', 1) AS son_siparis
           FROM customers c
           JOIN orders o ON o.customer_id = c.id
          WHERE o.order_stage <> 'iptal'
            AND COALESCE(o.transaction_type, 'gozluk') <> 'tamir'
          GROUP BY c.id
         HAVING son_tarih < DATE_SUB(NOW(), INTERVAL $ay MONTH)
          ORDER BY son_tarih
          LIMIT " . (int) $limit
    );
    return reminder_decorate($satirlar, 'yenileme');
}

/**
 * SGK hakkı doğanlar (4.12.0: gerçek hak tarihiyle).
 * Müşterinin SGK'lı son siparişi varsa (SGK katkısı girilmiş ya da Medula'dan aktarılmış),
 * hak tarihi = o reçetenin TARİHİ (Medula'daki reçete tarihi, yoksa reçete kaydı tarihi) + süre.
 * Hiç SGK kullanımı kaydı yoksa eskisi gibi son reçete tarihinden hesaplanır.
 * 18 yaş altı için ayrı (daha kısa) süre kullanılır.
 */
function reminder_sgk(int $limit = 200): array
{
    $a = reminder_settings();
    $enKisa = min($a['sgk'], $a['sgk_child']);
    $sgkVar = column_exists('orders', 'sgk_erecete');
    $sgkKosul = $sgkVar ? "(o.sgk_amount > 0 OR o.sgk_erecete IS NOT NULL)" : "o.sgk_amount > 0";
    $gelenTarih = column_exists('sgk_incoming', 'recete_tarihi')
        ? "(SELECT MAX(g.recete_tarihi) FROM sgk_incoming g WHERE g.used_order_id = o.id)"
        : "NULL";
    // Toplama takma adları ORDER BY/HAVING ifadesinde kullanılamadığı (MariaDB 1247) için türetilmiş tablo.
    $satirlar = rows(
        "SELECT x.* FROM (
            SELECT c.id, c.first_name, c.last_name, c.phone, c.birth_year,
                   MAX(r.prescription_date) AS son_tarih,
                   MAX(CASE WHEN $sgkKosul THEN COALESCE($gelenTarih, r.prescription_date, DATE(o.created_at)) END) AS son_sgk_tarih,
                   SUBSTRING_INDEX(GROUP_CONCAT(r.order_id ORDER BY r.prescription_date DESC), ',', 1) AS son_siparis
              FROM customers c
              JOIN prescription_records r ON r.customer_id = c.id
              LEFT JOIN orders o ON o.id = r.order_id AND o.order_stage <> 'iptal'
             GROUP BY c.id
          ) x
         WHERE COALESCE(x.son_sgk_tarih, x.son_tarih) < DATE_SUB(CURDATE(), INTERVAL $enKisa MONTH)
         ORDER BY COALESCE(x.son_sgk_tarih, x.son_tarih)
         LIMIT " . (int) $limit
    );
    $yil = (int) date('Y');
    $bugun = date('Y-m-d');
    $uygun = [];
    foreach ($satirlar as $s) {
        $yas = $s['birth_year'] ? $yil - (int) $s['birth_year'] : null;
        $gereken = ($yas !== null && $yas < 18) ? $a['sgk_child'] : $a['sgk'];
        $esas = (string) ($s['son_sgk_tarih'] ?: $s['son_tarih']);
        $hak = date('Y-m-d', strtotime($esas . ' +' . $gereken . ' months'));
        if ($hak <= $bugun) {
            $s['gereken_ay'] = $gereken;
            $s['hak_tarihi'] = $hak;
            $s['sgk_kaydi'] = $s['son_sgk_tarih'] ? 1 : 0;
            $s['son_tarih'] = $esas;
            $uygun[] = $s;
        }
    }
    return reminder_decorate($uygun, 'sgk');
}

/** Hazır olduğu halde alınmamış gözlükler. */
function reminder_pickups(int $limit = 200): array
{
    $gun = reminder_settings()['pickup'];
    $satirlar = rows(
        "SELECT c.id, c.first_name, c.last_name, c.phone, c.birth_year,
                o.id AS son_siparis, COALESCE(o.updated_at, o.created_at) AS son_tarih,
                o.total_amount, COALESCE(p.paid, 0) AS paid,
                o.total_amount - COALESCE(p.paid, 0) AS balance
           FROM orders o
           JOIN customers c ON c.id = o.customer_id
           " . PAID_JOIN . "
          WHERE o.order_stage = 'hazirlandi'
            AND COALESCE(o.updated_at, o.created_at) < DATE_SUB(NOW(), INTERVAL $gun DAY)
          ORDER BY son_tarih
          LIMIT " . (int) $limit
    );
    return reminder_decorate($satirlar, 'teslim');
}

/** Elle eklenen hatırlatmalar (bekleyenler; gecikmişler önce). */
function reminder_manual(bool $sadeceVadesiGelen = true, int $limit = 200): array
{
    $kosul = $sadeceVadesiGelen ? 'AND (r.due_date IS NULL OR r.due_date <= CURDATE())' : '';
    return rows(
        "SELECT r.*, c.first_name, c.last_name, c.phone, c.birth_year, u.full_name AS created_by_name
           FROM reminders r
           JOIN customers c ON c.id = r.customer_id
           LEFT JOIN user_accounts u ON u.id = r.created_by
          WHERE r.kind = 'manuel' AND r.status = 'bekliyor' $kosul
          ORDER BY r.due_date IS NULL, r.due_date, r.id
          LIMIT " . (int) $limit
    );
}

/** Otomatik listeye susturma durumunu ve ay farkını işler. */
function reminder_decorate(array $satirlar, string $kind): array
{
    if (!$satirlar) {
        return [];
    }
    $durum = reminder_state(array_column($satirlar, 'id'), $kind);
    $cikti = [];
    foreach ($satirlar as $s) {
        $r = $durum[(int) $s['id'] . ':' . $kind] ?? null;
        [$gizli, $sebep] = reminder_hidden($r, $kind);
        if ($gizli) {
            continue;
        }
        $s['kind'] = $kind;
        $s['ay'] = reminder_months((string) $s['son_tarih']);
        $s['gun'] = max(0, (int) floor((time() - (int) strtotime((string) $s['son_tarih'])) / 86400));
        $s['gecmis_kayit'] = $r;
        $s['sebep'] = $sebep;
        $cikti[] = $s;
    }
    return $cikti;
}

/**
 * Menü rozeti: yalnızca "bugün yapılacak" olanlar sayılır — vadesi gelmiş
 * planlı aramalar ve alınmamış gözlükler. Yenileme/SGK listeleri ağır
 * sorgular olduğu için her sayfa açılışında hesaplanmaz.
 */
function reminder_badge(): int
{
    static $n = null;
    if ($n !== null) {
        return $n;
    }
    try {
        $gun = reminder_settings()['pickup'];
        $bekleyen = (int) scalar(
            "SELECT COUNT(*) FROM reminders WHERE kind = 'manuel' AND status = 'bekliyor' AND (due_date IS NULL OR due_date <= CURDATE())"
        );
        $n = $bekleyen + count(reminder_pickups(300));
    } catch (Throwable) {
        $n = 0;
    }
    return $n;
}

/** Bir tür + müşteri için karar kaydı (arandı / ertelendi / kapatıldı). */
function reminder_mark(string $kind, int $customerId, string $status, array $ek = []): void
{
    $kayit = row('SELECT id FROM reminders WHERE kind = ? AND customer_id = ? ORDER BY id DESC LIMIT 1', [$kind, $customerId]);
    $veri = [
        'status'   => $status,
        'order_id' => isset($ek['order_id']) && $ek['order_id'] ? (int) $ek['order_id'] : null,
        'due_date' => $ek['due_date'] ?? null,
        'note'     => isset($ek['note']) ? mb_substr((string) $ek['note'], 0, 255) : null,
        'channel'  => $ek['channel'] ?? null,
        'done_at'  => $status === 'yapildi' ? date('Y-m-d H:i:s') : null,
        'done_by'  => $status === 'yapildi' ? (int) (current_user()['id'] ?? 0) : null,
    ];
    if ($kayit) {
        update('reminders', $veri, 'id = ?', [(int) $kayit['id']]);
        return;
    }
    insert('reminders', $veri + [
        'customer_id' => $customerId,
        'kind'        => $kind,
        'created_by'  => (int) (current_user()['id'] ?? 0),
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}

/** Hatırlatma mesajı: ayarlardaki metin + değişkenler. */
function reminder_message(string $kind, array $v): string
{
    $sablon = setting('reminder_msg_' . $kind, '');
    if (trim($sablon) === '') {
        $sablon = 'Merhaba {ad}, {magaza}\'tan yazıyoruz. Size ulaşmak istedik.';
    }
    return strtr($sablon, [
        '{ad}'             => (string) ($v['first_name'] ?? ''),
        '{soyad}'          => (string) ($v['last_name'] ?? ''),
        '{ad_soyad}'       => trim(($v['first_name'] ?? '') . ' ' . ($v['last_name'] ?? '')),
        '{ay}'             => (string) ($v['ay'] ?? ''),
        '{gun}'            => (string) ($v['gun'] ?? ''),
        '{siparis_no}'     => !empty($v['son_siparis']) ? order_no((int) $v['son_siparis']) : '',
        '{tutar}'          => isset($v['total_amount']) ? money($v['total_amount']) : '',
        '{kalan}'          => isset($v['balance']) ? money($v['balance']) : '',
        '{magaza}'         => setting('shop_name', 'OptiFlow'),
        '{magaza_telefon}' => setting('shop_phone', ''),
        '{hak_tarihi}'     => !empty($v['hak_tarihi']) ? date_tr((string) $v['hak_tarihi']) : '',
        '{urun}'           => (string) ($v['urun'] ?? ''),
        '{bitis}'          => !empty($v['bitis']) ? date_tr((string) $v['bitis']) : '',
    ]);
}

/** WhatsApp bağlantısı (telefon boşsa ''). */
function wa_url(?string $phone, string $text): string
{
    $t = preg_replace('/\D/', '', (string) $phone) ?? '';
    if ($t === '') {
        return '';
    }
    if (str_starts_with($t, '0')) {
        $t = '9' . $t;
    }
    if (strlen($t) === 10) {
        $t = '90' . $t;
    }
    return 'https://wa.me/' . $t . '?text=' . rawurlencode($text);
}
