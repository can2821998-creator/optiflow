<?php
declare(strict_types=1);

/* ==========================================================================
   Gözlük bağışı — müşterilerin kullanmadığı çerçeveleri toplayıp gözlüğe ulaşmakta
   zorlanan insanlarla buluşturma (camlarını mağaza karşılar) çalışmasının kaydı.

   Yıllardır yürüyen bu çalışmayı sisteme bağlar: bağışlanan çerçeve, verilen gözlük ve
   karşılanan cam maliyeti kaydedilir; sitede ve müşteri sayfalarında sayaç gösterilir.

   TASARIM İLKESİ — ALICININ ONURU: Gözlüğü alan kişinin adı, telefonu, fotoğrafı ya da
   herhangi bir kimlik bilgisi KAYDEDİLMEZ; forma yazılacak alan bile yoktur. Yalnızca
   tarih, çerçevenin tanımı, maliyet ve isteğe bağlı iç not tutulur. Herkese açık sayaç
   yalnızca iki toplam sayıdır.

   Sistemden önceki yıllara ait toplamlar Bağış ekranında elle girilir ve sayaca eklenir.
   Ana şemaya dokunmaz: donation_frames tablosunu ilk kullanımda oluşturur.
   ========================================================================== */

function donation_ensure_table(): void
{
    $opts = function_exists('t_opts') ? t_opts() : 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
    db()->exec("CREATE TABLE IF NOT EXISTS donation_frames (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        description VARCHAR(160) NOT NULL,
        donor VARCHAR(80) NOT NULL DEFAULT '',
        received_on DATE NOT NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'stokta',
        given_on DATE NULL,
        lens_cost DECIMAL(10,2) NULL,
        order_id INT UNSIGNED NULL,
        note VARCHAR(240) NOT NULL DEFAULT '',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_donation_status (status, received_on)
    ) $opts");
}

/** Sistemden önceki yıllara ait toplamlar (Ayarlar tablosunda tutulur). */
function donation_offsets(): array
{
    $yil = (int) setting('bagis_baslangic_yili', '0');
    return [
        'cerceve' => max(0, (int) setting('bagis_onceki_cerceve', '0')),
        'gozluk'  => max(0, (int) setting('bagis_onceki_gozluk', '0')),
        'yil'     => ($yil >= 1980 && $yil <= (int) date('Y')) ? $yil : null,
    ];
}

/**
 * Herkese açık sayaç: yalnızca iki toplam ve başlangıç yılı. Kişisel/parasal bilgi içermez.
 * Tablo yoksa yalnızca elle girilen önceki toplamlar döner.
 */
function donation_public_stats(): array
{
    $o = donation_offsets();
    $cerceve = $o['cerceve'];
    $gozluk = $o['gozluk'];
    try {
        $cerceve += (int) scalar('SELECT COUNT(*) FROM donation_frames');
        $gozluk  += (int) scalar("SELECT COUNT(*) FROM donation_frames WHERE status = 'verildi'");
    } catch (Throwable $e) {
        // tablo henüz yok
    }
    return ['cerceve' => $cerceve, 'gozluk' => $gozluk, 'yil' => $o['yil']];
}

/** Yöneticiye özel: karşılanan cam maliyeti dahil. */
function donation_totals(): array
{
    $s = donation_public_stats();
    $s['stokta'] = 0;
    $s['maliyet'] = 0.0;
    try {
        $s['stokta'] = (int) scalar("SELECT COUNT(*) FROM donation_frames WHERE status = 'stokta'");
        $s['maliyet'] = (float) scalar("SELECT COALESCE(SUM(lens_cost), 0) FROM donation_frames WHERE status = 'verildi'");
    } catch (Throwable $e) {
        // tablo henüz yok
    }
    return $s;
}

function donation_stock(): array
{
    try {
        return rows("SELECT * FROM donation_frames WHERE status = 'stokta' ORDER BY received_on ASC, id ASC LIMIT 200");
    } catch (Throwable $e) {
        return [];
    }
}

function donation_recent(int $limit = 12): array
{
    try {
        return rows("SELECT * FROM donation_frames WHERE status = 'verildi' ORDER BY given_on DESC, id DESC LIMIT " . (int) $limit);
    } catch (Throwable $e) {
        return [];
    }
}

function donation_add(string $description, string $donor, string $note, int $userId): void
{
    $description = mb_substr(trim($description), 0, 160);
    if ($description === '') {
        throw new RuntimeException('Çerçevenin kısa tanımı zorunlu (marka, renk vb.).');
    }
    donation_ensure_table();
    q(
        'INSERT INTO donation_frames (description, donor, received_on, note, created_by) VALUES (?, ?, ?, ?, ?)',
        [$description, mb_substr(trim($donor), 0, 80), date('Y-m-d'), mb_substr(trim($note), 0, 240), $userId ?: null]
    );
}

/** Siparişteki camların bilinen birim maliyetleri toplamı (bağış gözlüğü normal sipariş akışıyla yapıldıysa). */
function donation_lens_cost(int $orderId): float
{
    return (float) scalar(
        'SELECT COALESCE(SUM(i.unit_cost), 0) FROM prescription_lens_items i
           JOIN prescription_records r ON r.id = i.prescription_id WHERE r.order_id = ?',
        [$orderId]
    );
}

/**
 * Stoktaki bir çerçeveyi "gözlük olarak verildi" işaretler.
 * Maliyet boşsa ve sipariş no verildiyse, siparişteki cam maliyetlerinden hesaplanır.
 * Dönen: kullanıcıya gösterilecek özet metin.
 */
function donation_give(int $id, string $costRaw, int $orderId, string $note): string
{
    $fr = row("SELECT id FROM donation_frames WHERE id = ? AND status = 'stokta'", [$id]);
    if (!$fr) {
        throw new RuntimeException('Bu çerçeve stokta görünmüyor.');
    }
    $cost = parse_money($costRaw);
    if ($cost === null || $cost < 0) {
        throw new RuntimeException('Cam maliyeti geçersiz.');
    }
    $kaynak = '';
    if ($orderId > 0) {
        if (!find_order($orderId)) {
            throw new RuntimeException('Bu numarada bir sipariş bulunamadı.');
        }
        if ($cost <= 0.009) {
            $cost = donation_lens_cost($orderId);
            $kaynak = $cost > 0.009 ? ' (maliyet sipariş camlarından hesaplandı)' : '';
        }
    }
    q(
        "UPDATE donation_frames SET status = 'verildi', given_on = ?, lens_cost = ?, order_id = ?, note = ? WHERE id = ?",
        [date('Y-m-d'), $cost > 0.009 ? $cost : null, $orderId > 0 ? $orderId : null, mb_substr(trim($note), 0, 240), $id]
    );
    return 'Gözlük verildi olarak kaydedildi' . ($cost > 0.009 ? ' · cam maliyeti ' . money($cost) : '') . $kaynak . '.';
}

function donation_delete(int $id): void
{
    q('DELETE FROM donation_frames WHERE id = ?', [$id]);
}
