<?php
declare(strict_types=1);

/* ------------------------------------------------------------------ */
/*  Sabit listeler                                                      */
/* ------------------------------------------------------------------ */

/** Sipariş aşamaları: anahtar => [etiket, renk tonu]. Sıra iş akışını gösterir. */
function stages(): array
{
    return [
        'siparis_verildi'    => ['Sipariş alındı', 'blue'],
        'bekliyor'           => ['Bekliyor', 'amber'],
        'rx_siparis_verildi' => ['RX sipariş verildi', 'violet'],
        'atolyede'           => ['Atölyede', 'teal'],
        'hazirlandi'         => ['Hazır', 'green'],
        'teslim_edildi'      => ['Teslim edildi', 'gray'],
        'iptal'              => ['İptal', 'red'],
    ];
}

function stage_label(?string $key): string
{
    return stages()[(string) $key][0] ?? (string) $key;
}

/**
 * Basit SVG halka (donut) grafik. $segments: [['label'=>, 'value'=>, 'color'=>hex], ...]
 * Harici kütüphane yok — çubuk grafiklerimizle aynı ruhta, tek dosyalık inline SVG.
 */
function svg_donut(array $segments, int $size = 168, int $thickness = 22): string
{
    $segments = array_values(array_filter($segments, static fn($s) => (float) $s['value'] > 0));
    $total = array_sum(array_map(static fn($s) => (float) $s['value'], $segments));
    $r = ($size - $thickness) / 2;
    $c = 2 * M_PI * $r;
    $cx = $size / 2;
    $cy = $size / 2;
    if ($total <= 0) {
        return '<svg class="donut" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '">'
            . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" stroke="var(--line)" stroke-width="' . $thickness . '"/></svg>';
    }
    $offset = 0.0;
    $html = '<svg class="donut" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" role="img" aria-label="Dağılım grafiği">'
        . '<g transform="rotate(-90 ' . $cx . ' ' . $cy . ')">';
    foreach ($segments as $s) {
        $frac = (float) $s['value'] / $total;
        $len = $frac * $c;
        $html .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '" fill="none" style="stroke:' . e($s['color']) . '" stroke-width="' . $thickness . '"'
            . ' stroke-dasharray="' . round($len, 2) . ' ' . round($c - $len, 2) . '" stroke-dashoffset="-' . round($offset, 2) . '">'
            . '<title>' . e($s['label']) . ' — %' . round($frac * 100) . '</title></circle>';
        $offset += $len;
    }
    $html .= '</g></svg>';
    return $html;
}

/** Grafik/rozet paletinden döngüsel renk seçer (durum/ödeme dağılımları için). */
function chart_palette(int $i): string
{
    static $colors = ['var(--brand)', 'var(--accent)', 'var(--teal)', 'var(--red)', 'var(--violet)', 'var(--green)', 'var(--gray)', 'var(--blue)'];
    return $colors[$i % count($colors)];
}

/**
 * Rozet tanımları. Her rozet: ikon, başlık, açıklama, hedef (threshold) ve
 * o kullanıcı için GERÇEK sayının nasıl hesaplanacağını belirten bir SQL.
 * Yeni tablo gerektirmez — hepsi var olan verilerden anlık hesaplanır.
 */
/**
 * Efsane statüsü: burada adı geçen personel, hesaplanan sayılara bakılmaksızın
 * her zaman tüm rozetleri (mevcut ve ileride eklenecek yenileri dahil) açık
 * ve en yüksek unvanda görünür. Yeni bir isim eklemek için sadece bu listeye
 * ekleyin — rozet/unvan mantığının geri kalanına hiç dokunmanız gerekmez.
 */
function legendary_staff_names(): array
{
    return ['Can Aydın'];
}

function is_legendary_staff(int $userId): bool
{
    static $cache = [];
    if (!array_key_exists($userId, $cache)) {
        $name = (string) (scalar('SELECT full_name FROM user_accounts WHERE id = ?', [$userId]) ?: '');
        $cache[$userId] = in_array($name, legendary_staff_names(), true);
    }
    return $cache[$userId];
}

function achievement_defs(): array
{
    return [
        'ilk_teslimat'  => ['icon' => '🥉', 'title' => 'İlk Adım', 'desc' => 'İlk teslimatını tamamladı', 'goal' => 1],
        'elli_teslimat' => ['icon' => '🥈', 'title' => 'Yarı Yüzler', 'desc' => '50 teslimat tamamladı', 'goal' => 50],
        'yuz_teslimat'  => ['icon' => '🥇', 'title' => 'Yüzler Kulübü', 'desc' => '100 teslimat tamamladı', 'goal' => 100],
        'ikiyuz_teslimat' => ['icon' => '💎', 'title' => 'İki Yüzler', 'desc' => '200 teslimat tamamladı', 'goal' => 200],
        'hizli_eller'   => ['icon' => '⚡', 'title' => 'Hızlı Eller', 'desc' => 'Aynı gün 10 teslimat yaptı', 'goal' => 10],
        'nokta_atisi'   => ['icon' => '🎯', 'title' => 'Nokta Atışı', 'desc' => '5 kez kasa tam uydu', 'goal' => 5],
        'yuz_siparis'   => ['icon' => '📦', 'title' => 'Yüz Sipariş', 'desc' => '100 sipariş aldı', 'goal' => 100],
        'kirk_tahsilat' => ['icon' => '💰', 'title' => 'Tahsilat Ustası', 'desc' => '40 tahsilat işledi', 'goal' => 40],
    ];
}

/** Bir personelin rozet ilerlemesi: [key => ['def'=>.., 'current'=>int, 'unlocked'=>bool]]. */
function staff_achievements(int $userId): array
{
    $legendary = is_legendary_staff($userId);
    $counts = [
        'ilk_teslimat'    => (int) scalar("SELECT COUNT(*) FROM orders WHERE delivered_by = ? AND order_stage = 'teslim_edildi'", [$userId]),
        'hizli_eller'     => (int) scalar("SELECT COUNT(*) FROM orders WHERE delivered_by = ? AND order_stage = 'teslim_edildi' AND DATE(created_at) = DATE(delivered_at)", [$userId]),
        'nokta_atisi'     => (int) scalar('SELECT COUNT(*) FROM cash_counts WHERE created_by = ? AND ABS(difference) < 0.01', [$userId]),
        'yuz_siparis'     => (int) scalar('SELECT COUNT(*) FROM orders WHERE created_by = ?', [$userId]),
        'kirk_tahsilat'   => (int) scalar('SELECT COUNT(*) FROM payments WHERE created_by = ?', [$userId]),
    ];
    $counts['elli_teslimat'] = $counts['ilk_teslimat'];
    $counts['yuz_teslimat'] = $counts['ilk_teslimat'];
    $counts['ikiyuz_teslimat'] = $counts['ilk_teslimat'];

    $out = [];
    foreach (achievement_defs() as $key => $def) {
        $current = $legendary ? $def['goal'] : ($counts[$key] ?? 0);
        $out[$key] = ['def' => $def, 'current' => $current, 'unlocked' => $legendary || $current >= $def['goal']];
    }
    return $out;
}

/** Bu ayın en çok satan personeli (id) — dinamik "Ayın Yıldızı" rozeti için. */
function star_of_month(): ?int
{
    $row = row(
        "SELECT created_by AS id FROM orders WHERE order_stage <> 'iptal' AND created_by IS NOT NULL
         AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
         GROUP BY created_by ORDER BY SUM(total_amount) DESC LIMIT 1"
    );
    return $row ? (int) $row['id'] : null;
}

/** Basit XP: sipariş alma, teslimat ve kusursuz kasa kapanışının ağırlıklı toplamı. */
function staff_xp(int $userId): int
{
    if (is_legendary_staff($userId)) {
        return 99999;
    }
    $orders = (int) scalar('SELECT COUNT(*) FROM orders WHERE created_by = ?', [$userId]);
    $delivered = (int) scalar("SELECT COUNT(*) FROM orders WHERE delivered_by = ? AND order_stage = 'teslim_edildi'", [$userId]);
    $perfectCash = (int) scalar('SELECT COUNT(*) FROM cash_counts WHERE created_by = ? AND ABS(difference) < 0.01', [$userId]);
    $payments = (int) scalar('SELECT COUNT(*) FROM payments WHERE created_by = ?', [$userId]);
    return $orders * 5 + $delivered * 8 + $perfectCash * 12 + $payments * 3;
}

/** XP'den unvan hesaplar — atölye temalı, çıraktan ustaya. */
function staff_title(int $xp): array
{
    $levels = [
        ['min' => 0,    'icon' => '🧵', 'name' => 'Çırak'],
        ['min' => 250,  'icon' => '🔧', 'name' => 'Kalfa'],
        ['min' => 750,  'icon' => '🛠️', 'name' => 'Usta'],
        ['min' => 1800, 'icon' => '👑', 'name' => 'Vitrin Ustası'],
        ['min' => 3500, 'icon' => '🏛️', 'name' => 'Atölye Efsanesi'],
    ];
    $current = $levels[0];
    $next = null;
    foreach ($levels as $i => $lvl) {
        if ($xp >= $lvl['min']) {
            $current = $lvl;
            $next = $levels[$i + 1] ?? null;
        }
    }
    $pct = $next ? min(100, round(($xp - $current['min']) / ($next['min'] - $current['min']) * 100)) : 100;
    return ['name' => $current['name'], 'icon' => $current['icon'], 'xp' => $xp, 'next' => $next, 'pct' => $pct];
}

/**
 * Gizli/sürpriz rozetler: sayıya dayalı değil, koşula dayalı. Keşfedilmesi
 * eğlenceli olsun diye açık listede değil, sadece açıldığında görünürler.
 */
function secret_achievement_defs(): array
{
    return [
        'gece_kusu'   => ['icon' => '🦉', 'title' => 'Gece Kuşu', 'desc' => 'Saat 20:00’den sonra sipariş kaydetti'],
        'erkenci_kus' => ['icon' => '🌅', 'title' => 'Erkenci Kuş', 'desc' => 'Saat 08:00’den önce sipariş kaydetti'],
        'kurucu'      => ['icon' => '🏛️', 'title' => 'Kurucu', 'desc' => 'Sistemdeki ilk siparişi oluşturdu'],
        'hafta_sonu'  => ['icon' => '🎪', 'title' => 'Hafta Sonu Kahramanı', 'desc' => 'Hafta sonu 5+ sipariş aldı'],
    ];
}

function staff_secret_achievements(int $userId): array
{
    if (is_legendary_staff($userId)) {
        return secret_achievement_defs();
    }
    $founder = (int) scalar('SELECT created_by FROM orders ORDER BY created_at ASC, id ASC LIMIT 1');
    $checks = [
        'gece_kusu'   => (bool) scalar('SELECT COUNT(*) FROM orders WHERE created_by = ? AND HOUR(created_at) >= 20', [$userId]),
        'erkenci_kus' => (bool) scalar('SELECT COUNT(*) FROM orders WHERE created_by = ? AND HOUR(created_at) < 8', [$userId]),
        'kurucu'      => $founder === $userId,
        'hafta_sonu'  => (int) scalar('SELECT COUNT(*) FROM orders WHERE created_by = ? AND DAYOFWEEK(created_at) IN (1,7)', [$userId]) >= 5,
    ];
    $out = [];
    foreach (secret_achievement_defs() as $key => $def) {
        if ($checks[$key] ?? false) {
            $out[$key] = $def;
        }
    }
    return $out;
}

/** Takımın bu ayki ortak hedefi — bireysel değil, herkesin birlikte gördüğü çubuk. */
function team_monthly_goal(): array
{
    $current = (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage <> 'iptal' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $lastMonth = (int) scalar(
        "SELECT COUNT(*) FROM orders WHERE order_stage <> 'iptal'
         AND created_at >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01')
         AND created_at < DATE_FORMAT(CURDATE(), '%Y-%m-01')"
    );
    $goal = max(20, (int) round($lastMonth * 1.1));
    return ['current' => $current, 'goal' => $goal, 'pct' => min(100, round($current / $goal * 100))];
}

/** Son N gün için basit etkinlik yoğunluğu (sipariş sayısı) — seri takvimi için. */
function staff_streak_calendar(int $userId, int $days = 28): array
{
    $rows = rows(
        "SELECT DATE(created_at) AS d, COUNT(*) AS cnt FROM orders
         WHERE created_by = ? AND created_at >= CURDATE() - INTERVAL ? DAY
         GROUP BY d",
        [$userId, $days]
    );
    $byDate = array_column($rows, 'cnt', 'd');
    $out = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $out[] = ['date' => $d, 'cnt' => (int) ($byDate[$d] ?? 0)];
    }
    return $out;
}

function stage_badge(?string $key): string
{
    $s = stages()[(string) $key] ?? [(string) $key, 'gray'];
    return '<span class="badge tone-' . e($s[1]) . '">' . e($s[0]) . '</span>';
}

/** Camların gelmesini bekleyen aşamalar (tüm camlar gelince otomatik "Atölyede"ye geçer). */
function waiting_stages(): array
{
    return ['siparis_verildi', 'bekliyor', 'rx_siparis_verildi'];
}

/** Atölye içi alt aşama (yalnızca order_stage = 'atolyede' iken anlamlı). */
function workshop_substages(): array
{
    return [
        'montajda' => ['Montajda', 'teal'],
        'kontrol'  => ['Kalite kontrolde', 'violet'],
    ];
}

function workshop_stage_label(?string $key): string
{
    return workshop_substages()[(string) $key][0] ?? 'Montajda';
}

function workshop_stage_badge(?string $key): string
{
    $s = workshop_substages()[(string) $key] ?? workshop_substages()['montajda'];
    return '<span class="badge tone-' . e($s[1]) . '">' . e($s[0]) . '</span>';
}

/** Atölyede çalışan / kontrol edebilecek personel listesi. */
function assignable_staff(): array
{
    return rows("SELECT id, full_name FROM user_accounts WHERE is_active = 1 ORDER BY full_name");
}

/** Tek "işlem" çatısı altındaki dört tür. */
function transaction_types(): array
{
    return [
        'gozluk'         => 'Gözlük siparişi',
        'gunes_gozlugu'  => 'Güneş gözlüğü satışı',
        'tamir'          => 'Tamir / Bakım',
    ];
}

function transaction_type_label(?string $key): string
{
    return transaction_types()[(string) $key] ?? transaction_types()['gozluk'];
}

/** Tamir / bakım işleminde yapılan iş türü. */
function service_types(): array
{
    return [
        'vida'        => 'Vida sıkma / değişimi',
        'burunluk'    => 'Burunluk değişimi',
        'ayar'        => 'Ayar (kulak / köprü)',
        'lehim'       => 'Lehim / kaynak',
        'cam_degisim' => 'Cam değişimi',
        'temizlik'    => 'Temizlik / bakım',
        'diger'       => 'Diğer',
    ];
}

function service_type_label(?string $key): string
{
    return service_types()[(string) $key] ?? '—';
}

function staff_name(?int $id): string
{
    if (!$id) {
        return '—';
    }
    return (string) (scalar('SELECT full_name FROM user_accounts WHERE id = ?', [$id]) ?: '—');
}

/** Bir teslimata (delivery) dahil cam satırları — hangi siparişten/müşteriden, hangi cam. */
function delivery_items(int $deliveryId): array
{
    return rows(
        "SELECT i.id, i.lens_type, i.eye, i.sph, i.cyl, i.axis, i.add_power, i.unit_cost,
                o.id AS order_id, c.first_name, c.last_name
         FROM prescription_lens_items i
         JOIN prescription_records r ON r.id = i.prescription_id
         JOIN orders o ON o.id = r.order_id
         JOIN customers c ON c.id = o.customer_id
         WHERE i.delivery_id = ?
         ORDER BY o.id, i.eye DESC",
        [$deliveryId]
    );
}

/** Cam tasarımı / kullanım şekli. */
function lens_designs(): array
{
    return [
        'tek_odak_uzak'   => 'Tek odak · uzak',
        'tek_odak_yakin'  => 'Tek odak · yakın (okuma)',
        'ayri_uzak_yakin' => 'Uzak + yakın · ayrı iki gözlük',
        'progressive'     => 'Progressive (çok odaklı)',
        'ofis'            => 'Ofis / ara mesafe',
        'bifokal'         => 'Bifokal',
    ];
}

function is_multifocal(string $design): bool
{
    return in_array($design, ['progressive', 'ofis', 'bifokal'], true);
}

/* ---------- SGK katkı payı (tahmini) ----------
   SUT'a göre SGK, gözlük camı için "gözlük" başına (uzak ve yakın ayrı ayrı sayılır) 2 yılda bir sabit bir tutar öder;
   diyoptri yükseldikçe bu tutar SUT eki listesinde kademeli olarak artar. Kademeli tablo bu sürümde yok; tek bir taban
   tutar (Ayarlar → Genel) kullanılır. Gerçek tutarı yalnızca MEDULA sorgusu verir — burası yalnızca ÖN DOLUM/TAHMİNdir. */

/** Reçetenin kullanım şekline göre SGK'nın ayrı ayrı ödediği "gözlük" sayısı (uzak / yakın). */
function sgk_lens_grup_sayisi(string $lensDesign): int
{
    return match ($lensDesign) {
        'ayri_uzak_yakin', 'progressive', 'ofis', 'bifokal' => 2,   // SGK bunları "uzak + yakın, ayrı ayrı" gibi karşılar
        default => 1,                                               // tek_odak_uzak / tek_odak_yakin
    };
}

/** Bir reçete için tahmini SGK katkı payı (TL). $rx: prescription_records satırı (en az 'lens_design' alanı). */
function sgk_katki_tahmini(array $rx): float
{
    $taban = (float) str_replace(',', '.', setting('sgk_lens_amount', '150'));
    if ($taban <= 0) {
        return 0.0;
    }
    return round($taban * sgk_lens_grup_sayisi((string) ($rx['lens_design'] ?? 'tek_odak_uzak')), 2);
}

/** Bu müşteri son 2 yıl (730 gün) içinde başka bir siparişte SGK katkısı almış mı? Varsa o siparişin özetini döner (hak tekrar
    kullanılıyor olabilir uyarısı için) — kesin cevabı yalnızca MEDULA verir, bu yalnızca dikkat çekmek içindir. */
function sgk_son_kullanim(int $customerId, int $haricOrderId): ?array
{
    if ($customerId <= 0) {
        return null;
    }
    return row(
        "SELECT id, sgk_amount, created_at FROM orders
         WHERE customer_id = ? AND id <> ? AND sgk_amount > 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 730 DAY)
         ORDER BY created_at DESC LIMIT 1",
        [$customerId, $haricOrderId]
    );
}

/** Reçete kaydedilince (rx.php veya sgk-aktar.php'den) SGK katkısını hesaplayıp siparişe ön dolum olarak yazar; tek akışın
    kalbi budur. Dönen 'uyari', varsa "son 2 yılda kullanılmış" uyarı metnidir (yoksa null). */
function sgk_katki_uygula(int $orderId, int $customerId, array $rx): array
{
    $tutar = sgk_katki_tahmini($rx);
    q('UPDATE orders SET sgk_amount = ? WHERE id = ?', [$tutar, $orderId]);
    $onceki = sgk_son_kullanim($customerId, $orderId);
    $uyari = $onceki
        ? 'Dikkat: bu müşteri ' . date_tr($onceki['created_at']) . ' tarihli siparişte de SGK katkısı almış görünüyor '
          . '(son 2 yıl içinde). Aynı hak tekrar kullanılamayabilir — tutarı MEDULA\'dan teyit edin.'
        : null;
    return ['tutar' => $tutar, 'uyari' => $uyari];
}

function stock_statuses(): array
{
    return [
        'stokta_var'      => ['Stokta', 'green'],
        'stokta_yok'      => ['Eksik', 'red'],
        'siparis_verildi' => ['Depoya sipariş verildi', 'amber'],
    ];
}

function stock_badge(string $key): string
{
    $s = stock_statuses()[$key] ?? [$key, 'gray'];
    return '<span class="badge tone-' . e($s[1]) . '">' . e($s[0]) . '</span>';
}

function payment_methods(): array
{
    return ['nakit' => 'Nakit', 'kart' => 'Kredi kartı', 'havale' => 'Havale / EFT', 'diger' => 'Diğer'];
}

function roles(): array
{
    return ['super_yetkili' => 'Süper yetkili', 'personel' => 'Personel'];
}

function coatings(): array
{
    return ['Antirefle', 'Blue (mavi ışık)', 'Drive (gece sürüş)', 'Fotokromik', 'Polarize', 'UV420', 'Sert kaplama'];
}

function lens_indexes(): array
{
    return ['1.50', '1.56', '1.59', '1.60', '1.67', '1.74'];
}

function product_tiers(): array
{
    return ['ekonomik' => 'Ekonomik', 'dengeli' => 'Dengeli', 'premium' => 'Premium'];
}

/** Aktif cam tipleri (Ayarlar'dan yönetilir). $include eski kayıttaki değeri listede tutar. */
function lens_type_options(?string $include = null): array
{
    $list = array_column(rows('SELECT name FROM lens_types WHERE is_active = 1 ORDER BY sort_order, name'), 'name');
    if ($include !== null && $include !== '' && !in_array($include, $list, true)) {
        $list[] = $include;
    }
    return $list;
}

function valid_lens_type(string $value): bool
{
    return $value === '' || (bool) scalar('SELECT COUNT(*) FROM lens_types WHERE name = ?', [$value]);
}

/* ------------------------------------------------------------------ */
/*  Yetki kuralları                                                     */
/* ------------------------------------------------------------------ */

/** Personel sipariş tutarlarını görebilir mi? (Ayarlar > Genel) */
function can_see_amounts(): bool
{
    return is_super() || setting('staff_see_amounts', '1') === '1';
}

/** Tahsilat girebilir mi? (Süper yetkili her zaman; personel Ayarlar'dan izin verilirse.) */
function can_take_payments(): bool
{
    return is_super() || (can_see_amounts() && setting('staff_take_payments', '0') === '1');
}

/* ------------------------------------------------------------------ */
/*  Reçete değerleri                                                    */
/* ------------------------------------------------------------------ */

/**
 * Diyoptri değeri okur: "-2,25" "2.5" "+1" → "-2.25" "+2.50" "+1.00".
 * 0.25'in katı ve aralıkta olmalı. Boş → '' ; geçersiz → null.
 */
function parse_diopter(string $raw, float $min, float $max): ?string
{
    $s = str_replace([',', ' '], ['.', ''], trim($raw));
    if ($s === '') {
        return '';
    }
    if (!preg_match('/^[+-]?\d{0,2}(\.\d{1,2})?$/', $s) || $s === '+' || $s === '-') {
        return null;
    }
    $v = (float) $s;
    if ($v < $min || $v > $max || fmod(round($v * 100), 25) !== 0.0) {
        return null;
    }
    if (abs($v) < 0.001) {
        return '0.00';
    }
    return ($v > 0 ? '+' : '-') . number_format(abs($v), 2, '.', '');
}

function parse_axis(string $raw): ?string
{
    $s = trim($raw);
    if ($s === '') {
        return '';
    }
    if (!ctype_digit($s) || (int) $s > 180) {
        return null;
    }
    return (string) ((int) $s === 0 ? 180 : (int) $s);
}

function parse_mm(string $raw, float $min, float $max): ?string
{
    $s = str_replace(',', '.', trim($raw));
    if ($s === '') {
        return '';
    }
    if (!is_numeric($s) || (float) $s < $min || (float) $s > $max) {
        return null;
    }
    return rtrim(rtrim(number_format((float) $s, 1, '.', ''), '0'), '.');
}

/** Reçete formunu doğrular. [veri, hatalar] döner. */
function rx_from_post(): array
{
    $errors = [];
    $data = [];

    $date = post('prescription_date');
    if (!valid_date($date)) {
        $errors[] = 'Reçete tarihi geçersiz.';
    }
    $data['prescription_date'] = $date;

    $design = post('lens_design', 'tek_odak_uzak');
    if (!isset(lens_designs()[$design])) {
        $errors[] = 'Kullanım şekli geçersiz.';
    }
    $data['lens_design'] = $design;

    $eyes = post('lens_eyes', 'both');
    $data['lens_eyes'] = in_array($eyes, ['both', 'right', 'left'], true) ? $eyes : 'both';

    $lens = post('lens_type');
    if (!valid_lens_type($lens)) {
        $errors[] = 'Cam tipi listede yok.';
    }
    $data['lens_type'] = $lens;

    $labels = ['right' => 'Sağ', 'left' => 'Sol'];
    foreach ($labels as $side => $label) {
        $sph = parse_diopter(post($side . '_sph'), -30, 30);
        $cyl = parse_diopter(post($side . '_cyl'), -10, 10);
        $axis = parse_axis(post($side . '_axis'));
        $add = parse_diopter(post($side . '_add'), 0, 4);
        if ($sph === null) { $errors[] = "$label SPH geçersiz (-30 … +30, 0.25 adım)."; }
        if ($cyl === null) { $errors[] = "$label CYL geçersiz (-10 … +10, 0.25 adım)."; }
        if ($axis === null) { $errors[] = "$label AKS 0–180 arası tam sayı olmalı."; }
        if ($add === null) { $errors[] = "$label ADD geçersiz (0 … +4, 0.25 adım)."; }
        if ($cyl !== null && $cyl !== '' && $cyl !== '0.00' && $axis === '') {
            $errors[] = "$label gözde CYL girildi, AKS boş.";
        }
        $pd = parse_mm(post($side . '_pd'), 20, 45);
        $height = parse_mm(post($side . '_height'), 8, 40);
        if ($pd === null) { $errors[] = "$label PD 20–45 mm olmalı (tek göz)."; }
        if ($height === null) { $errors[] = "$label montaj yüksekliği 8–40 mm olmalı."; }
        $data[$side . '_sph'] = $sph ?? '';
        $data[$side . '_cyl'] = $cyl ?? '';
        $data[$side . '_axis'] = $axis ?? '';
        $data[$side . '_add'] = $add ?? '';
        $data[$side . '_pd'] = $pd ?? '';
        $data[$side . '_height'] = $height ?? '';
    }
    if (in_array($design, ['progressive', 'ofis', 'bifokal', 'ayri_uzak_yakin', 'tek_odak_yakin'], true)
        && $data['right_add'] === '' && $data['left_add'] === '') {
        $errors[] = 'Seçilen kullanım şekli için ADD değeri gerekli.';
    }

    $pd = parse_mm(post('pd'), 40, 80);
    if ($pd === null) {
        $errors[] = 'Toplam PD 40–80 mm olmalı.';
    }
    $data['pd'] = $pd ?? '';
    $data['prescription_note'] = mb_substr(post('prescription_note'), 0, 1000);
    $data['doctor'] = mb_substr(post('doctor'), 0, 120);

    // Yakın değerleri: ayrı yakın gözlük / sadece okuma gözlüğü için.
    $near = null;
    if (in_array($design, ['ayri_uzak_yakin', 'tek_odak_yakin'], true)) {
        $near = ['usage_type' => $design, 'lens_type' => post('near_lens_type')];
        if (!valid_lens_type($near['lens_type'])) {
            $errors[] = 'Yakın cam tipi listede yok.';
        }
        foreach ($labels as $side => $label) {
            $computed = near_sphere($data[$side . '_sph'], $data[$side . '_add']);
            $sph = parse_diopter(post('near_' . $side . '_sph', $computed), -30, 30);
            if ($sph === null) {
                $errors[] = "Yakın $label SPH geçersiz.";
            }
            $near[$side . '_sph'] = ($sph === '' || $sph === null) ? $computed : $sph;
            $near[$side . '_cyl'] = $data[$side . '_cyl'];
            $near[$side . '_axis'] = $data[$side . '_axis'];
        }
    }

    // Öneri asistanının kaydedilen özeti.
    $advisor = post('advisor_summary');
    $data['advisor_summary'] = $advisor !== '' ? mb_substr($advisor, 0, 2000) : null;
    $data['advisor_product_id'] = post_int('advisor_product_id') ?: null;

    return [$data, $near, $errors];
}

/** Uzak SPH + ADD = yakın SPH. */
function near_sphere(string $sph, string $add): string
{
    if ($add === '' || $add === '0.00') {
        return $sph;
    }
    $v = (float) ($sph === '' ? 0 : $sph) + (float) $add;
    return abs($v) < 0.001 ? '0.00' : ($v > 0 ? '+' : '-') . number_format(abs($v), 2, '.', '');
}

function rx_line(array $r, string $side): string
{
    $parts = [];
    $sph = $r[$side . '_sph'] ?? '';
    $cyl = $r[$side . '_cyl'] ?? '';
    $parts[] = $sph !== '' ? $sph : 'PL';
    if ($cyl !== '' && $cyl !== '0.00') {
        $parts[] = $cyl . ' × ' . (($r[$side . '_axis'] ?? '') !== '' ? $r[$side . '_axis'] . '°' : '?');
    }
    if (($r[$side . '_add'] ?? '') !== '') {
        $parts[] = 'ADD ' . $r[$side . '_add'];
    }
    return implode('  ', $parts);
}

/* ------------------------------------------------------------------ */
/*  Cam (depo) satırları                                               */
/* ------------------------------------------------------------------ */

/**
 * Reçeteye göre cam satırlarını oluşturur/günceller.
 * Var olan satırlar yerinde güncellenir: oluşturulma tarihi ve "geldi" bilgisi korunur.
 * $stock: ['uzak' => durum, 'yakin' => durum]; null ise mevcut durum korunur (yeni satır: stokta_var).
 */
function sync_lens_items(int $rxId, array $rx, ?array $near, array $stock): void
{
    $design = $rx['lens_design'];
    $wanted = []; // lens_no => satır
    $eyes = match ($rx['lens_eyes'] ?? 'both') {
        'right' => ['R' => 'right'],
        'left'  => ['L' => 'left'],
        default => ['R' => 'right', 'L' => 'left'],
    };

    $farLens = $rx['lens_type'];
    if ($design !== 'tek_odak_yakin' && $farLens !== '') {
        $group = is_multifocal($design) ? 'cok_odak' : 'uzak';
        foreach ($eyes as $eye => $side) {
            $wanted[$eye === 'R' ? 1 : 2] = [
                'item_group' => $group,
                'eye'        => $eye,
                'lens_type'  => $farLens,
                'sph'        => $rx[$side . '_sph'],
                'cyl'        => $rx[$side . '_cyl'],
                'axis'       => $rx[$side . '_axis'],
                'add_power'  => is_multifocal($design) ? $rx[$side . '_add'] : '',
                'stock_key'  => 'uzak',
            ];
        }
    }
    if ($near !== null) {
        $nearLens = $near['lens_type'] !== '' ? $near['lens_type'] : $farLens;
        if ($nearLens !== '') {
            foreach ($eyes as $eye => $side) {
                $wanted[$eye === 'R' ? 3 : 4] = [
                    'item_group' => 'yakin',
                    'eye'        => $eye,
                    'lens_type'  => $nearLens,
                    'sph'        => $near[$side . '_sph'],
                    'cyl'        => $near[$side . '_cyl'],
                    'axis'       => $near[$side . '_axis'],
                    'add_power'  => '',
                    'stock_key'  => 'yakin',
                ];
            }
        }
    }

    $existing = [];
    foreach (rows('SELECT * FROM prescription_lens_items WHERE prescription_id = ?', [$rxId]) as $it) {
        $existing[(int) $it['lens_no']] = $it;
    }

    foreach ($wanted as $no => $w) {
        $stockKey = $w['stock_key'];
        unset($w['stock_key']);
        $w['lens_label'] = lens_item_label($w);
        $w['lens_value'] = lens_item_value($w);
        $requested = $stock[$stockKey] ?? null;
        if (isset($existing[$no])) {
            $old = $existing[$no];
            $changed = $old['lens_type'] !== $w['lens_type'] || $old['sph'] !== $w['sph'] || $old['cyl'] !== $w['cyl']
                || $old['axis'] !== $w['axis'] || $old['add_power'] !== $w['add_power'];
            if ($requested !== null && $requested !== $old['stock_status']) {
                $w['stock_status'] = $requested;
                $w['arrived_at'] = null;
                $w['ordered_at'] = $requested === 'siparis_verildi' ? date('Y-m-d H:i:s') : null;
            } elseif ($changed && $old['stock_status'] !== 'stokta_var') {
                // Numara değişti ve cam henüz gelmediyse depo siparişi yeniden verilmeli.
                $w['stock_status'] = 'stokta_yok';
                $w['ordered_at'] = null;
            }
            update('prescription_lens_items', $w, 'id = ?', [$old['id']]);
            unset($existing[$no]);
        } else {
            $w['prescription_id'] = $rxId;
            $w['lens_no'] = $no;
            $w['stock_status'] = $requested ?? 'stokta_var';
            if ($w['stock_status'] === 'siparis_verildi') {
                $w['ordered_at'] = date('Y-m-d H:i:s');
            }
            insert('prescription_lens_items', $w);
        }
    }
    // Artık gerekmeyen satırlar (ör. progressive'e geçildi, yakın satırları kalktı).
    foreach ($existing as $old) {
        q('DELETE FROM prescription_lens_items WHERE id = ?', [$old['id']]);
    }
}

function lens_item_label(array $it): string
{
    $group = ['uzak' => 'Uzak', 'yakin' => 'Yakın', 'cok_odak' => 'Çok odak'][$it['item_group']] ?? '';
    $eye = ($it['eye'] ?? '') === 'R' ? 'Sağ' : 'Sol';
    return trim($group . ' ' . $eye . ' · ' . $it['lens_type']);
}

function lens_item_value(array $it): string
{
    $v = 'SPH ' . ($it['sph'] !== '' ? $it['sph'] : 'PL');
    if ($it['cyl'] !== '' && $it['cyl'] !== '0.00') {
        $v .= ' / CYL ' . $it['cyl'] . ($it['axis'] !== '' ? ' × ' . $it['axis'] : '');
    }
    if ($it['add_power'] !== '') {
        $v .= ' / ADD ' . $it['add_power'];
    }
    return $v;
}

/**
 * Camlar geldiğinde: siparişin tüm cam satırları stoktaysa ve sipariş bekleme aşamasındaysa
 * siparişi "Atölyede"ye taşır. Hazır/teslim/iptal siparişlere dokunmaz.
 * Taşınan sipariş ID'lerini döner.
 */
function advance_orders_if_lenses_ready(array $orderIds): array
{
    $moved = [];
    foreach (array_unique(array_map('intval', $orderIds)) as $orderId) {
        $o = row('SELECT id, order_stage FROM orders WHERE id = ?', [$orderId]);
        if (!$o || !in_array($o['order_stage'], waiting_stages(), true)) {
            continue;
        }
        $missing = (int) scalar(
            "SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id
             WHERE r.order_id = ? AND i.stock_status <> 'stokta_var'",
            [$orderId]
        );
        if ($missing === 0) {
            q("UPDATE orders SET order_stage = 'atolyede', status = 'atolyede', workshop_stage = 'montajda' WHERE id = ?", [$orderId]);
            $moved[] = $orderId;
            push_order_event($orderId, 'cam_geldi');
        }
    }
    return $moved;
}

/* ------------------------------------------------------------------ */
/*  Sipariş / müşteri                                                   */
/* ------------------------------------------------------------------ */

/** Ödenen tutar alt sorgusu. */
const PAID_JOIN = 'LEFT JOIN (SELECT order_id, SUM(amount) AS paid FROM payments GROUP BY order_id) p ON p.order_id = o.id';

/** Tedarikçi bakiyesi (borcumuz) için: fatura toplamı − ödeme toplamı. */
const SUPPLIER_BALANCE_JOIN = "LEFT JOIN (SELECT supplier_id, SUM(amount) AS invoiced FROM supplier_invoices GROUP BY supplier_id) si ON si.supplier_id = s.id
     LEFT JOIN (SELECT supplier_id, SUM(amount) AS paid FROM supplier_payments GROUP BY supplier_id) sp ON sp.supplier_id = s.id";

function supplier_options(): array
{
    return rows('SELECT id, name FROM suppliers WHERE is_active = 1 ORDER BY name');
}

function supplier_label(?int $id): string
{
    if (!$id) {
        return '—';
    }
    return (string) (scalar('SELECT name FROM suppliers WHERE id = ?', [$id]) ?: '—');
}

function frame_options(): array
{
    return rows("SELECT id, CONCAT(brand, IF(model <> '', CONCAT(' · ', model), '')) AS label FROM frame_products WHERE is_active = 1 ORDER BY brand, model");
}

function frame_label(?int $id): string
{
    if (!$id) {
        return '—';
    }
    $f = row('SELECT brand, model FROM frame_products WHERE id = ?', [$id]);
    return $f ? trim($f['brand'] . ' ' . $f['model']) : '—';
}

/** Bir fatura tutarını marka bazlı ortalama maliyete işler; marka yoksa oluşturur. */
function record_frame_purchase(?int $supplierId, string $brand, ?string $model, int $qty, float $amount): int
{
    $brand = trim($brand);
    $model = trim((string) $model);
    $existing = row('SELECT * FROM frame_products WHERE brand = ? AND model <=> ?', [$brand, $model !== '' ? $model : null]);
    if ($existing) {
        $newQty = (int) $existing['total_qty'] + $qty;
        $newSpent = (float) $existing['total_spent'] + $amount;
        update('frame_products', [
            'total_qty'   => $newQty,
            'total_spent' => $newSpent,
            'avg_cost'    => $newQty > 0 ? round($newSpent / $newQty, 2) : null,
            'supplier_id' => $existing['supplier_id'] ?: $supplierId,
        ], 'id = ?', [$existing['id']]);
        return (int) $existing['id'];
    }
    return insert('frame_products', [
        'supplier_id' => $supplierId,
        'brand'       => $brand,
        'model'       => $model !== '' ? $model : null,
        'total_qty'   => $qty,
        'total_spent' => $amount,
        'avg_cost'    => $qty > 0 ? round($amount / $qty, 2) : null,
    ]);
}

/** Maliyet bilinmeden, sadece marka adı kaydetmek için (satış anında). Fatura girilince maliyet sonradan oluşur. */
function find_or_create_frame_brand(string $brand, ?string $model = null): int
{
    $brand = trim($brand);
    $model = trim((string) $model);
    $existing = row('SELECT id FROM frame_products WHERE brand = ? AND model <=> ?', [$brand, $model !== '' ? $model : null]);
    if ($existing) {
        return (int) $existing['id'];
    }
    return insert('frame_products', ['brand' => $brand, 'model' => $model !== '' ? $model : null]);
}

function find_order(int $id): ?array
{
    return row(
        'SELECT o.*, COALESCE(p.paid, 0) AS paid, o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount AS balance,
                c.first_name AS c_first, c.last_name AS c_last, c.phone AS c_phone, c.birth_year AS c_birth_year,
                u.full_name AS created_by_name
         FROM orders o ' . PAID_JOIN . '
         JOIN customers c ON c.id = o.customer_id
         LEFT JOIN user_accounts u ON u.id = o.created_by
         WHERE o.id = ?',
        [$id]
    );
}

function find_customer(int $id): ?array
{
    return row('SELECT * FROM customers WHERE id = ?', [$id]);
}

/** Müşteri adı değişince eski "orders.first_name/last_name/phone" alanlarını da eşitle (geriye uyum). */
function sync_customer_to_orders(int $customerId): void
{
    q('UPDATE orders o JOIN customers c ON c.id = o.customer_id SET o.first_name = c.first_name, o.last_name = c.last_name, o.phone = c.phone WHERE c.id = ?', [$customerId]);
}

/* ------------------------------------------------------------------ */
/*  WhatsApp şablonları                                                 */
/* ------------------------------------------------------------------ */

function template_placeholders(): array
{
    return [
        '{ad}'             => 'Müşteri adı',
        '{soyad}'          => 'Müşteri soyadı',
        '{ad_soyad}'       => 'Ad soyad',
        '{siparis_no}'     => 'Sipariş numarası',
        '{durum}'          => 'Sipariş durumu',
        '{tutar}'          => 'Sipariş tutarı',
        '{kalan}'          => 'Kalan bakiye',
        '{teslim_tarihi}'  => 'Söz verilen teslim tarihi',
        '{magaza}'         => 'Mağaza adı',
        '{magaza_telefon}' => 'Mağaza telefonu',
        '{takip_linki}'    => 'Müşterinin sipariş durumu sayfası bağlantısı',
    ];
}

/** Sipariş için hazır WhatsApp bağlantıları. Tutar yetkisi yoksa tutar içeren şablonlar gizlenir. */
function whatsapp_links(array $order): array
{
    $phone = (string) $order['c_phone'];
    if ($phone === '') {
        return [];
    }
    $vars = [
        '{ad}'             => $order['c_first'],
        '{soyad}'          => $order['c_last'],
        '{ad_soyad}'       => $order['c_first'] . ' ' . $order['c_last'],
        '{siparis_no}'     => order_no((int) $order['id']),
        '{durum}'          => stage_label($order['order_stage']),
        '{tutar}'          => money($order['total_amount']),
        '{kalan}'          => money($order['balance']),
        '{teslim_tarihi}'  => date_tr($order['promised_date'] ?? null),
        '{magaza}'         => setting('shop_name', 'OptiFlow'),
        '{magaza_telefon}' => setting('shop_phone', ''),
        '{takip_linki}'    => order_track_url((int) $order['id']),
    ];
    $links = [];
    foreach (rows('SELECT * FROM message_templates WHERE is_active = 1 ORDER BY sort_order, id') as $t) {
        if (!can_see_amounts() && (str_contains($t['body'], '{tutar}') || str_contains($t['body'], '{kalan}'))) {
            continue;
        }
        $text = strtr($t['body'], $vars);
        $links[] = [
            'id'   => (int) $t['id'],
            'name' => $t['name'],
            'text' => $text,
            'url'  => 'https://wa.me/' . $phone . '?text=' . rawurlencode($text),
        ];
    }
    return $links;
}

/* ------------------------------------------------------------------ */
/*  İşlem geçmişi etiketleri                                            */
/* ------------------------------------------------------------------ */

function audit_actions(): array
{
    return [
        'login'            => 'Giriş yaptı',
        'wa_izin'            => 'WhatsApp mesaj iznini güncelledi',
        'wa_toplu'           => 'Hatırlatma listesini WhatsApp kuyruğuna ekledi',
        'lens_ekle'          => 'Kontakt lens kaydı ekledi',
        'odeme_linki'        => 'Ödeme linki oluşturdu',
        'odeme_linki_iptal'  => 'Ödeme linkini iptal etti',
        'odeme_linki_odendi' => 'Ödeme linkiyle ödeme alındı',
        'fatura_taslak'      => 'Fatura taslağı oluşturdu',
        'fatura_hazir'       => 'Faturayı hazır işaretledi',
        'fatura_iptal'       => 'Fatura taslağını iptal etti',
        'cam_fis'            => 'Cam sipariş fişi oluşturdu',
        'cam_fis_gonder'     => 'Cam siparişini tedarikçiye gönderdi',
        'sgk_liste_kontrol'  => 'Medula listesini kontrol etti',
        'alis_fatura'        => 'e-Fatura (alış) içe aktardı',
        'senet_ver'          => 'Tedarikçiye senet verdi',
        'senet_ode'          => 'Senedi ödendi işaretledi',
        'senet_iptal'        => 'Senedi iptal etti',
        'senet_geri'         => 'Senet ödemesini geri aldı',
        'uts_okut'           => 'Siparişe ÜTS karekodu okuttu',
        'uts_cikar'          => 'ÜTS ürününü siparişten çıkardı',
        'uts_stok'           => 'ÜTS ürününü stoğa okuttu',
        'uts_kabul'          => 'ÜTS gelen ürünleri kabul etti',
        'uts_imha'           => 'ÜTS imha bildirimi yaptı',
        'uts_iade'           => 'ÜTS tedarikçiye iade bildirimi yaptı',
        'uts_bildirim'       => 'ÜTS bildirimini yönetti',
        'logout'           => 'Çıkış yaptı',
        'order_create'     => 'Sipariş oluşturdu',
        'order_update'     => 'Sipariş bilgilerini değiştirdi',
        'order_stage'      => 'Sipariş durumunu değiştirdi',
        'payment_create'   => 'Tahsilat girdi',
        'payment_delete'   => 'Tahsilat sildi',
        'rx_create'        => 'Reçete ekledi',
        'rx_update'        => 'Reçete düzenledi',
        'rx_delete'        => 'Reçete sildi',
        'stock_update'     => 'Cam stok durumunu değiştirdi',
        'customer_create'  => 'Müşteri ekledi',
        'customer_update'  => 'Müşteri bilgilerini değiştirdi',
        'customer_merge'   => 'Müşterileri birleştirdi',
        'whatsapp'         => 'WhatsApp mesajı açtı',
        'reminder_create'  => 'Hatırlatma ekledi',
        'reminder_done'    => 'Hatırlatmayı arandı olarak işaretledi',
        'reminder_close'   => 'Hatırlatma listesinden çıkardı',
        'frame_create'     => 'Çerçeve stoğa ekledi',
        'frame_update'     => 'Çerçeve kaydını düzenledi',
        'frame_move'       => 'Çerçeve stoğunu değiştirdi',
        'frame_delete'     => 'Çerçeve kaydını sildi',
        'backup'           => 'Veritabanı yedeği aldı',
        'user_create'      => 'Kullanıcı oluşturdu',
        'user_update'      => 'Kullanıcıyı düzenledi',
        'user_password'    => 'Parola değiştirdi',
        'settings_update'  => 'Ayarları değiştirdi',
        'catalog_update'   => 'Ürün kataloğunu değiştirdi',
        'template_update'  => 'Mesaj şablonunu değiştirdi',
        'lens_type_update' => 'Cam tiplerini değiştirdi',
        'report_export'    => 'Rapor dışa aktardı',
    ];
}

function audit_label(string $action): string
{
    return audit_actions()[$action] ?? $action;
}


/* ------------------------------------------------------------------ */
/*  Telefon bildirimleri (web push) — sipariş olayları                  */
/*  Bildirim gönderimi hiçbir zaman işlemi kesmez: hata olursa sessizce  */
/*  yutulur, kullanıcı akışı etkilenmez.                                */
/* ------------------------------------------------------------------ */

function push_order_event(int $orderId, string $event, array $ek = []): void
{
    // 4.12.0 — sipariş hazır olunca müşteriye WhatsApp mesajı (özellik ve ayar açıksa kuyruğa)
    if ($event === 'hazir' && function_exists('wa_siparis_hazir')) {
        wa_siparis_hazir($orderId);
    }
    if (!function_exists('push_send') || setting('push_enabled', '1') !== '1') {
        return;
    }
    try {
        $o = row(
            'SELECT o.id, o.first_name, o.last_name FROM orders o WHERE o.id = ?',
            [$orderId]
        );
        if (!$o) {
            return;
        }
        $kim = trim(($o['first_name'] ?? '') . ' ' . ($o['last_name'] ?? ''));
        $no  = order_no((int) $o['id']);

        $metin = match ($event) {
            'cam_geldi'   => ['Camlar geldi · ' . $no, $kim . ' — iş atölyeye düştü.'],
            'hazir'       => ['Sipariş hazır · ' . $no, $kim . ' aranabilir, gözlük teslime hazır.'],
            'teslim'      => ['Teslim edildi · ' . $no, $kim . ' siparişini teslim aldı.'],
            'atolyede'    => ['Atölyeye alındı · ' . $no, $kim . ' — montaj başladı.'],
            'geciken'     => ['Teslim gecikti · ' . $no, $kim . ' için verilen teslim günü geçti.'],
            default       => ['Sipariş güncellendi · ' . $no, $kim],
        };

        push_send([
            'title' => $metin[0],
            'body'  => $metin[1],
            'url'   => 'order.php?id=' . (int) $o['id'],
            'tag'   => 'order-' . (int) $o['id'],
        ], [], $ek['except'] ?? (current_user()['id'] ?? null));
    } catch (Throwable $e) {
        // bildirim gönderilemedi: sessizce geç
    }
}


/* ------------------------------------------------------------------ */
/*  Müşteri takip sayfası                                               */
/* ------------------------------------------------------------------ */

/**
 * Siparişin genel (herkese açık) takip anahtarı. Yoksa üretilip saklanır.
 * 22 karakter, 128 bitten fazla rastgelelik — tahmin edilemez.
 */
function order_public_token(int $orderId): string
{
    $mevcut = (string) scalar('SELECT public_token FROM orders WHERE id = ?', [$orderId]);
    if ($mevcut !== '') {
        return $mevcut;
    }
    $alfabe = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    for ($deneme = 0; $deneme < 5; $deneme++) {
        $t = '';
        for ($i = 0; $i < 22; $i++) {
            $t .= $alfabe[random_int(0, strlen($alfabe) - 1)];
        }
        try {
            q('UPDATE orders SET public_token = ? WHERE id = ? AND (public_token IS NULL OR public_token = \'\')', [$t, $orderId]);
            $yeni = (string) scalar('SELECT public_token FROM orders WHERE id = ?', [$orderId]);
            if ($yeni !== '') {
                return $yeni;
            }
        } catch (Throwable $e) {
            // benzersizlik çakışması: yeniden dene
        }
    }
    return '';
}

/**
 * Uygulamanın kök adresi — alt klasöre kurulmuş olsa bile doğru çalışır.
 * Örn. https://ornekoptik.com.tr  ya da  https://ornekoptik.com.tr/atolye
 * Web isteğinde bir kez öğrenilip ayarlara yazılır (fiş, cron vb. için).
 */
function app_base_url(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '' && preg_match('/^[a-z0-9.\-:]+$/i', $host)) {
        $guvenli = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https'
            || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

        // Uygulama alt klasördeyse (örn. /atolye/order.php) o klasör de adrese girer
        $klasor = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
        $klasor = ($klasor === '/' || $klasor === '.' || $klasor === '') ? '' : '/' . trim($klasor, '/');

        $url = ($guvenli ? 'https' : 'http') . '://' . $host . $klasor;
        try {
            if (setting('site_base_url') !== $url) {
                setting_set('site_base_url', $url);
            }
        } catch (Throwable $e) { /* yoksay */ }
        return $url;
    }

    $kayitli = setting('site_base_url');
    return $kayitli !== '' ? $kayitli : (function_exists('push_site_url') ? push_site_url() : '');
}

/** durum.php sunucuya yüklenmiş mi? (eksik yüklemede ölü karekod basılmasın) */
function track_page_exists(): bool
{
    return is_file(APP_ROOT . '/durum.php') && is_file(APP_ROOT . '/app/pages/durum.php');
}

/** Müşteriye verilecek tam takip adresi. */
function order_track_url(int $orderId): string
{
    $t = order_public_token($orderId);
    if ($t === '') {
        return '';
    }
    $kok = app_base_url();
    if ($kok === '') {
        return '';
    }
    return rtrim($kok, '/') . '/durum.php?k=' . $t;
}

/** Müşteriye gösterilecek dört aşamalı sadeleştirilmiş durum. */
function public_stage(string $stage): array
{
    return match ($stage) {
        'hazirlandi'    => ['adim' => 3, 'baslik' => 'Gözlüğünüz hazır', 'metin' => 'Siparişiniz tamamlandı, mağazamızdan teslim alabilirsiniz.', 'ton' => 'green'],
        'teslim_edildi' => ['adim' => 4, 'baslik' => 'Teslim edildi', 'metin' => 'Siparişiniz teslim edildi. Bizi tercih ettiğiniz için teşekkür ederiz.', 'ton' => 'gray'],
        'atolyede'      => ['adim' => 2, 'baslik' => 'Atölyede hazırlanıyor', 'metin' => 'Camlarınız geldi, gözlüğünüz atölyemizde hazırlanıyor.', 'ton' => 'teal'],
        'iptal'         => ['adim' => 0, 'baslik' => 'Sipariş iptal edildi', 'metin' => 'Bu sipariş iptal edilmiştir. Ayrıntı için bizi arayabilirsiniz.', 'ton' => 'red'],
        default         => ['adim' => 1, 'baslik' => 'Siparişiniz alındı', 'metin' => 'Siparişiniz kayıt altına alındı, camlarınız tedarik ediliyor.', 'ton' => 'blue'],
    };
}
