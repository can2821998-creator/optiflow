<?php
declare(strict_types=1);

/* 4.47.0 — Bugün aranacaklar: kâğıttaki "arama listesi" ekranda.
   Dört kaynak tek listede, öncelik sırasıyla:
     haber  — gözlüğü hazır, atölyede "Müşteriye haber" adımı atılmamış
     teslim — haber verilmiş ama ayarlardaki gün kadar alınmamış
     manuel — vadesi gelmiş planlı arama (reminders, kind=manuel)
     bakiye — ödeme sözü tarihi geçmiş veresiye
   Her denemenin sonucu arama_kayitlari'na yazılır (ulaşıldı / açmadı / WhatsApp / ertele); kararlar mevcut
   reminders düzeniyle (reminder_mark / reminder_hidden) saklanır, böylece "açmadı" yarın tekrar listeye düşer.
   "Ulaşıldı" ya da WhatsApp, hazır gözlükte atölyedeki "Müşteriye haber" adımını da atar.
   SQL taşınabilir (testte SQLite): tarih hesapları PHP'de. */

const ARANACAK_TURLERI = [
    'haber'  => 'Gözlüğü hazır',
    'teslim' => 'Haber verildi, gelmedi',
    'manuel' => 'Planlı arama',
    'bakiye' => 'Ödeme sözü geçti',
];

const ARANACAK_SONUCLARI = [
    'ulasildi' => 'Ulaşıldı',
    'whatsapp' => 'WhatsApp gönderildi',
    'acmadi'   => 'Açmadı',
    'ertele'   => 'Ertelendi',
];

/**
 * Bugün aranacaklar. Her satır: tur, customer_id, order_id, reminder_id (manuel), ad, first_name, last_name, phone,
 * baslik, detay, gun, raf_yeri, kalan, deneme (son 30 gündeki "açmadı" sayısı), son_sonuc.
 */
function aranacak_listesi(?string $bugun = null): array
{
    $bugun ??= date('Y-m-d');
    $teslimGun = function_exists('reminder_settings') ? reminder_settings()['pickup'] : 7;
    $satirlar = [];

    // haber + teslim: hazır siparişler (haber adımı atıldı mı?)
    foreach (rows(
        "SELECT o.id AS order_id, o.customer_id, o.raf_yeri, o.transaction_type, COALESCE(o.qc_at, o.updated_at, o.created_at) AS hazir_at,
                (SELECT MAX(a.created_at) FROM atolye_adimlari a WHERE a.order_id = o.id AND a.adim = 'haber') AS haber_at,
                c.first_name, c.last_name, c.phone
           FROM orders o JOIN customers c ON c.id = o.customer_id
          WHERE o.order_stage = 'hazirlandi'" . (function_exists('sube_tablo_var') && sube_tablo_var() ? sube_kosul_hazir('o') . " AND (o.tasima_durum IS NULL OR o.tasima_durum = 'geldi')" : '') . "
          ORDER BY hazir_at"
    ) as $r) {
        $gun = aranacak_gun_farki((string) $r['hazir_at'], $bugun);
        $tamir = $r['transaction_type'] === 'tamir';
        if ($r['haber_at'] === null) {
            $tur = 'haber';
            $detay = ($tamir ? 'Tamiri bitti' : 'Gözlüğü hazır') . ($gun > 0 ? ' · ' . $gun . ' gündür bekliyor' : ' · bugün hazırlandı');
        } elseif (($haberGun = aranacak_gun_farki((string) $r['haber_at'], $bugun)) >= $teslimGun) {
            // teslim süresi haberden sayılır: müşteriye haber verildikten sonra ayardaki gün kadar beklenir
            $tur = 'teslim';
            $detay = $haberGun . ' gün önce haber verildi, alınmadı';
        } else {
            continue;
        }
        $satirlar[] = $r + [
            'tur' => $tur, 'gun' => $gun, 'reminder_id' => null, 'kalan' => null,
            'baslik' => order_no((int) $r['order_id']) . ($r['raf_yeri'] ? ' · raf ' . $r['raf_yeri'] : ''),
            'detay' => $detay,
        ];
    }

    // manuel: vadesi gelmiş planlı aramalar
    foreach (rows(
        "SELECT r.id AS reminder_id, r.customer_id, r.order_id, r.due_date, r.note, c.first_name, c.last_name, c.phone
           FROM reminders r JOIN customers c ON c.id = r.customer_id
          WHERE r.kind = 'manuel' AND r.status = 'bekliyor' AND (r.due_date IS NULL OR r.due_date <= ?)
          ORDER BY r.due_date, r.id",
        [$bugun]
    ) as $r) {
        $satirlar[] = $r + [
            'tur' => 'manuel', 'gun' => $r['due_date'] ? aranacak_gun_farki((string) $r['due_date'], $bugun) : 0, 'raf_yeri' => null, 'kalan' => null,
            'baslik' => $r['due_date'] ? 'Planlanan ' . date_tr((string) $r['due_date']) : 'Planlı arama',
            'detay' => trim((string) $r['note']) !== '' ? (string) $r['note'] : 'Not yok',
        ];
    }

    // bakiye: ödeme sözü geçmiş (sipariş başına bir satır)
    foreach (rows(
        "SELECT o.id AS order_id, o.customer_id, o.balance_promise_date, c.first_name, c.last_name, c.phone,
                o.total_amount - o.sgk_amount - (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = o.id) AS kalan
           FROM orders o JOIN customers c ON c.id = o.customer_id
          WHERE o.order_stage <> 'iptal' AND o.balance_promise_date IS NOT NULL AND o.balance_promise_date < ?" . (function_exists('sube_kosul') ? sube_kosul('o') : '') . "
          ORDER BY o.balance_promise_date",
        [$bugun]
    ) as $r) {
        if ((float) $r['kalan'] <= 0.009) {
            continue;
        }
        $gun = aranacak_gun_farki((string) $r['balance_promise_date'], $bugun);
        $satirlar[] = $r + [
            'tur' => 'bakiye', 'gun' => $gun, 'reminder_id' => null, 'raf_yeri' => null,
            'baslik' => order_no((int) $r['order_id']) . ' · söz ' . date_tr((string) $r['balance_promise_date']),
            'detay' => 'Ödeme sözü ' . $gun . ' gün geçti',
        ];
    }

    // haber/teslim/bakiye kararları reminders'tan (açmadı → yarına, ulaşıldı → bekleme süresi)
    $sonuc = [];
    $durumlar = [];
    foreach (['haber', 'teslim', 'bakiye'] as $t) {
        $durumlar[$t] = reminder_state(array_column(array_filter($satirlar, static fn($s) => $s['tur'] === $t), 'customer_id'), $t);
    }
    foreach ($satirlar as $s) {
        if ($s['tur'] !== 'manuel') {
            if (aranacak_gizli($durumlar[$s['tur']][(int) $s['customer_id'] . ':' . $s['tur']] ?? null, $s['tur'], $bugun)) {
                continue;
            }
        }
        $s['ad'] = trim($s['first_name'] . ' ' . $s['last_name']);
        $sonuc[] = $s;
    }

    // öncelik: hazır → gelmedi → planlı → söz geçti; aynı türde en çok bekleyen önce
    $sira = array_flip(array_keys(ARANACAK_TURLERI));
    usort($sonuc, static fn($a, $b) => [$sira[$a['tur']], -$a['gun']] <=> [$sira[$b['tur']], -$b['gun']]);

    // deneme sayısı ve son sonuç
    $denemeler = aranacak_denemeler(array_column($sonuc, 'customer_id'), $bugun);
    foreach ($sonuc as &$s) {
        $d = $denemeler[(int) $s['customer_id'] . ':' . $s['tur']] ?? ['acmadi' => 0, 'son' => null];
        $s['deneme'] = $d['acmadi'];
        $s['son_sonuc'] = $d['son'];
    }
    unset($s);
    return $sonuc;
}

/** reminder_hidden'ın verilen güne göre olanı (liste ileri bir gün için de hesaplanabilsin). */
function aranacak_gizli(?array $r, string $tur, string $bugun): bool
{
    if (!$r) {
        return false;
    }
    if ($r['status'] === 'kapali') {
        return true;
    }
    if ($r['status'] === 'yapildi' && $r['done_at']) {
        return date('Y-m-d', strtotime($r['done_at'] . ' ' . reminder_cooldown($tur))) > $bugun;
    }
    return $r['status'] === 'bekliyor' && $r['due_date'] && $r['due_date'] > $bugun;
}

/** Tarihten bugüne gün (gelecekse 0). */
function aranacak_gun_farki(string $tarih, string $bugun): int
{
    $t = strtotime(substr($tarih, 0, 10));
    return $t ? max(0, (int) floor((strtotime($bugun) - $t) / 86400)) : 0;
}

/** "müşteri:tür" => ['acmadi' => son 30 günde açmadı sayısı, 'son' => son kayıt (sonuc, created_at, kullanıcı)]. */
function aranacak_denemeler(array $customerIds, ?string $bugun = null): array
{
    $customerIds = array_values(array_unique(array_map('intval', $customerIds)));
    if (!$customerIds) {
        return [];
    }
    $sinir = date('Y-m-d', strtotime(($bugun ?? date('Y-m-d')) . ' -30 days')) . ' 00:00:00';
    $harita = [];
    foreach (rows(
        'SELECT k.customer_id, k.tur, k.sonuc, k.created_at, u.full_name FROM arama_kayitlari k
         LEFT JOIN user_accounts u ON u.id = k.user_id
         WHERE k.customer_id IN (' . in_placeholders($customerIds) . ') AND k.created_at >= ? ORDER BY k.id',
        array_merge($customerIds, [$sinir])
    ) as $r) {
        $a = (int) $r['customer_id'] . ':' . $r['tur'];
        $harita[$a] ??= ['acmadi' => 0, 'son' => null];
        if ($r['sonuc'] === 'acmadi') {
            $harita[$a]['acmadi']++;
        }
        $harita[$a]['son'] = $r;
    }
    return $harita;
}

/**
 * Arama sonucunu yazar. ulasildi / whatsapp: tamam (haber/teslim'de atölyedeki haber adımı da atılır);
 * acmadi: yarın yeniden listeye; ertele: $tarih'e kadar gizli. Dönüş: kullanıcıya gösterilecek ileti.
 */
function aranacak_sonuc(string $tur, int $customerId, ?int $orderId, ?int $reminderId, string $sonuc, int $userId, string $not = '', string $tarih = '', ?string $bugun = null): string
{
    if (!isset(ARANACAK_TURLERI[$tur]) || !isset(ARANACAK_SONUCLARI[$sonuc])) {
        throw new DomainException('Bilinmeyen arama türü ya da sonucu.');
    }
    $bugun ??= date('Y-m-d');
    if ($sonuc === 'ertele' && (!valid_date($tarih) || $tarih <= $bugun)) {
        throw new DomainException('Erteleme için ileri bir tarih seçin.');
    }
    $not = mb_substr(trim($not), 0, 255);
    $yarin = date('Y-m-d', strtotime($bugun . ' +1 day'));
    $kanal = $sonuc === 'whatsapp' ? 'whatsapp' : 'telefon';
    $tamam = in_array($sonuc, ['ulasildi', 'whatsapp'], true);

    transaction(static function () use ($tur, $customerId, $orderId, $reminderId, $sonuc, $userId, $not, $tarih, $yarin, $kanal, $tamam): void {
        insert('arama_kayitlari', [
            'customer_id' => $customerId, 'order_id' => $orderId ?: null, 'tur' => $tur, 'sonuc' => $sonuc,
            'not_metni' => $not !== '' ? $not : null, 'user_id' => $userId, 'created_at' => date('Y-m-d H:i:s'),
        ]);
        if ($tur === 'manuel') {
            if (!$reminderId) {
                throw new DomainException('Planlı arama kaydı bulunamadı.');
            }
            $alan = $tamam
                ? ['status' => 'yapildi', 'done_at' => date('Y-m-d H:i:s'), 'done_by' => $userId, 'channel' => $kanal]
                : ['due_date' => $sonuc === 'ertele' ? $tarih : $yarin];
            update('reminders', $alan, "id = ? AND kind = 'manuel'", [$reminderId]);
            return;
        }
        if ($tamam) {
            reminder_mark($tur, $customerId, 'yapildi', ['order_id' => $orderId, 'channel' => $kanal, 'note' => $not]);
            if (in_array($tur, ['haber', 'teslim'], true) && $orderId && function_exists('atolye_adim_kaydet')) {
                atolye_adim_kaydet($orderId, 'haber', $userId);
            }
            return;
        }
        reminder_mark($tur, $customerId, 'bekliyor', [
            'order_id' => $orderId, 'due_date' => $sonuc === 'ertele' ? $tarih : $yarin,
            'note' => $not !== '' ? $not : ($sonuc === 'acmadi' ? 'Açmadı' : null),
        ]);
    });

    return match ($sonuc) {
        'ulasildi' => 'Ulaşıldı olarak kaydedildi.',
        'whatsapp' => 'WhatsApp gönderildi olarak kaydedildi.',
        'acmadi'   => 'Açmadı — yarın yeniden listede.',
        default    => date_tr($tarih) . ' tarihine ertelendi.',
    };
}

/** Satırın WhatsApp metni (mevcut hatırlatma şablonları; bakiye için bakiye şablonu). */
function aranacak_mesaj(array $s): string
{
    $kind = match ($s['tur']) {
        'haber', 'teslim' => 'teslim',
        'bakiye' => 'bakiye',
        default => 'manuel',
    };
    return reminder_message($kind, [
        'first_name' => $s['first_name'], 'last_name' => $s['last_name'], 'gun' => $s['gun'],
        'son_siparis' => $s['order_id'] ?? null, 'balance' => $s['kalan'] ?? null,
    ]);
}

/** Menü rozeti / bant sayısı: bugün aranacak kişi sayısı. */
function aranacak_sayisi(): int
{
    static $n = null;
    if ($n === null) {
        try {
            $n = count(aranacak_listesi());
        } catch (Throwable) {
            $n = 0;
        }
    }
    return $n;
}
