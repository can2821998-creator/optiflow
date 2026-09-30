<?php
declare(strict_types=1);

/* ==========================================================================
   Çerçeve stoğu
   --------------------------------------------------------------------------
   Vitrindeki her çerçeve modeli bir "kalem"dir: marka, model, renk, beden,
   adet, maliyet ve satış fiyatı. Adet her değiştiğinde `frame_moves`
   tablosuna bir hareket yazılır; böylece "bu çerçeve nereye gitti"
   sorusunun cevabı hep vardır.
   ========================================================================== */

/** Stok hareket sebepleri. */
function frame_reasons(): array
{
    return [
        'giris'  => 'Stok girişi',
        'satis'  => 'Satış',
        'iade'   => 'İade / geri geldi',
        'sayim'  => 'Sayım düzeltmesi',
        'fire'   => 'Fire / kırık',
        'tedarikci_iade' => 'Tedarikçiye iade',   // 4.13.0 ÜTS
        'imha'   => 'İmha / bertaraf',            // 4.13.0 ÜTS
    ];
}

function frame_reason_label(?string $k): string
{
    return frame_reasons()[(string) $k] ?? (string) $k;
}

/** "Ray-Ban · RB5154 · Siyah · 52-21" */
function frame_item_label(array $i): string
{
    $p = array_filter([
        (string) ($i['brand'] ?? ''),
        (string) ($i['model'] ?? ''),
        (string) ($i['color'] ?? ''),
        (string) ($i['size'] ?? ''),
    ], static fn($s) => trim($s) !== '');
    return implode(' · ', $p);
}

/** Barkodu olmayan kaleme benzersiz barkod üretir: PO + 8 hane. */
function frame_new_barcode(): string
{
    for ($i = 0; $i < 20; $i++) {
        $aday = 'PO' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
        if (!scalar('SELECT id FROM frame_items WHERE barcode = ?', [$aday])) {
            return $aday;
        }
    }
    return 'PO' . time();
}

function frame_find_by_barcode(string $barkod): ?array
{
    $barkod = trim($barkod);
    return $barkod === '' ? null : row('SELECT * FROM frame_items WHERE barcode = ?', [$barkod]);
}

/**
 * Stok hareketi yazar ve adedi günceller. Adet eksiye düşmez.
 * @return int hareketten sonraki adet
 */
function frame_move(int $itemId, int $delta, string $reason, ?int $orderId = null, string $note = ''): int
{
    $item = row('SELECT id, qty FROM frame_items WHERE id = ?', [$itemId]);
    if (!$item) {
        return 0;
    }
    if (!isset(frame_reasons()[$reason])) {
        $reason = 'sayim';
    }
    $yeni = max(0, (int) $item['qty'] + $delta);
    $gercekDelta = $yeni - (int) $item['qty'];
    if ($gercekDelta === 0 && $delta !== 0) {
        // Stok zaten 0 ve daha da düşürülmek isteniyor: hareket yazma.
        return $yeni;
    }
    update('frame_items', ['qty' => $yeni, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$itemId]);
    insert('frame_moves', [
        'frame_item_id' => $itemId,
        'delta'         => $gercekDelta,
        'reason'        => $reason,
        'order_id'      => $orderId ?: null,
        'note'          => mb_substr($note, 0, 255) ?: null,
        'created_by'    => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at'    => date('Y-m-d H:i:s'),
    ]);
    return $yeni;
}

/** Vitrin özeti: kalem, adet, maliyet değeri, satış değeri, kritik/biten sayısı. */
function frame_summary(): array
{
    $r = row(
        "SELECT COUNT(*) AS kalem,
                COALESCE(SUM(qty), 0) AS adet,
                COALESCE(SUM(qty * COALESCE(cost, 0)), 0) AS maliyet,
                COALESCE(SUM(qty * COALESCE(price, 0)), 0) AS satis,
                COALESCE(SUM(qty > 0 AND qty <= min_qty), 0) AS kritik,
                COALESCE(SUM(qty = 0), 0) AS biten
           FROM frame_items WHERE is_active = 1"
    );
    return $r ?: ['kalem' => 0, 'adet' => 0, 'maliyet' => 0, 'satis' => 0, 'kritik' => 0, 'biten' => 0];
}

/** Menü rozeti: kritik ve biten kalem sayısı. */
function frame_alert_count(): int
{
    static $n = null;
    if ($n !== null) {
        return $n;
    }
    try {
        $n = (int) scalar('SELECT COUNT(*) FROM frame_items WHERE is_active = 1 AND qty <= min_qty');
    } catch (Throwable) {
        $n = 0;
    }
    return $n;
}

/** Sipariş ekranındaki "stoktaki çerçeve" listesi (seçili olan tükenmişse yine görünür). */
function frame_stock_options(?int $seciliId = null): array
{
    $satirlar = rows(
        'SELECT id, brand, model, color, size, qty, price, barcode
           FROM frame_items
          WHERE is_active = 1 AND (qty > 0 OR id = ?)
          ORDER BY brand, model, color
          LIMIT 500',
        [$seciliId ?: 0]
    );
    $liste = [];
    foreach ($satirlar as $s) {
        $etiket = frame_item_label($s);
        if ((int) $s['qty'] <= 0) {
            $etiket .= ' (stok bitti)';
        } elseif ($s['price'] !== null) {
            $etiket .= ' — ' . money($s['price']);
        }
        $liste[(string) $s['id']] = $etiket;
    }
    return $liste;
}

/**
 * Siparişin stok çerçevesini değiştirir: eskisini geri koyar, yenisini düşer.
 * Sipariş kaydındaki frame_item_id'yi de günceller.
 */
function order_set_frame_item(int $orderId, ?int $yeniId, ?int $eskiId): void
{
    $yeniId = $yeniId ?: null;
    $eskiId = $eskiId ?: null;
    if ($yeniId === $eskiId) {
        return;
    }
    if ($eskiId) {
        frame_move($eskiId, +1, 'iade', $orderId, 'Siparişten çıkarıldı · ' . order_no($orderId));
    }
    if ($yeniId) {
        frame_move($yeniId, -1, 'satis', $orderId, 'Siparişe verildi · ' . order_no($orderId));
    }
    q('UPDATE orders SET frame_item_id = ? WHERE id = ?', [$yeniId, $orderId]);
}

/** Bir çerçeve kaleminin özet bilgisi + son hareketleri. */
function frame_item_with_moves(int $id): ?array
{
    $item = row('SELECT * FROM frame_items WHERE id = ?', [$id]);
    if (!$item) {
        return null;
    }
    $item['moves'] = rows(
        'SELECT m.*, u.full_name AS kullanici
           FROM frame_moves m LEFT JOIN user_accounts u ON u.id = m.created_by
          WHERE m.frame_item_id = ? ORDER BY m.id DESC LIMIT 30',
        [$id]
    );
    return $item;
}
