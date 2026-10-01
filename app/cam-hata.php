<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Hatalı cam / yeniden yapım kaydı (4.15.0)

   Bir siparişin camı yeniden yapıldığında: sebep, göz, sorumlu, maliyet.
   • "Yeniden yapılacak" seçilen camlar Depo · Stok'ta yeniden "Eksik" olur
     (cam siparişi akışı aynen işler; eski "geldi" bilgileri temizlenir).
   • Laboratuvar hatasında tedarikçiden iade alacağı açılır; "alındı" denince
     tedarikçi carisine "İade / alacak" kaydı düşer (borç azalır).
   • Rapor: dönem bazında sebep, maliyet, tedarikçi ve personel dağılımı;
     tedarikçi karnesinde laboratuvar hata oranı.
   SQL taşınabilir (MySQL + testlerdeki SQLite).
   ========================================================================== */

function cam_hata_nedenleri(): array
{
    return [
        'laboratuvar' => 'Laboratuvar hatası (tedarikçi)',
        'olcu'        => 'Ölçü / reçete yazımı (mağaza)',
        'montaj'      => 'Montaj / kesim hatası',
        'kirilma'     => 'Kırılma / hasar',
        'memnuniyet'  => 'Müşteri uyum sorunu / memnuniyetsizlik',
        'diger'       => 'Diğer',
    ];
}

function cam_hata_alacak_durumlari(): array
{
    return [
        'yok'        => ['—', 'gray'],
        'bekliyor'   => ['İade bekleniyor', 'amber'],
        'alindi'     => ['İade alındı', 'green'],
        'reddedildi' => ['İade reddedildi', 'red'],
    ];
}

/** Siparişin cam satırları (yeniden yapım seçimi için). */
function cam_hata_siparis_camlari(int $siparisId): array
{
    return rows(
        'SELECT i.id, i.eye, i.lens_label, i.lens_type, i.stock_status, i.supplier_id, i.unit_cost, i.item_group
           FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id
          WHERE r.order_id = ? ORDER BY i.prescription_id, i.lens_no',
        [$siparisId]
    );
}

/**
 * Hatalı cam kaydı açar.
 * $v: neden, goz ('R'|'L'|'cift'), sorumlu_id, maliyet (float), supplier_id, aciklama,
 *     camlar (int[] — yeniden yapılacak cam satırları), alacak (bool — lab hatasında tedarikçiden iade beklenir)
 * Dönüş: kayıt id
 */
function cam_hata_ekle(int $siparisId, array $v): int
{
    if (!row('SELECT id FROM orders WHERE id = ?', [$siparisId])) {
        throw new DomainException('Sipariş bulunamadı.');
    }
    $neden = (string) ($v['neden'] ?? '');
    if (!isset(cam_hata_nedenleri()[$neden])) {
        throw new DomainException('Hata sebebini seçin.');
    }
    $goz = in_array($v['goz'] ?? '', ['R', 'L', 'cift'], true) ? (string) $v['goz'] : 'cift';
    $maliyet = round(max(0.0, (float) ($v['maliyet'] ?? 0)), 2);
    if ($maliyet > 1_000_000) {
        throw new DomainException('Maliyet geçersiz.');
    }
    $camlar = array_values(array_unique(array_filter(array_map('intval', (array) ($v['camlar'] ?? [])))));
    $siparisCamlari = array_column(cam_hata_siparis_camlari($siparisId), null, 'id');
    foreach ($camlar as $cid) {
        if (!isset($siparisCamlari[$cid])) {
            throw new DomainException('Seçilen cam bu siparişe ait değil.');
        }
    }
    $tedarikci = (int) ($v['supplier_id'] ?? 0) ?: null;
    if ($tedarikci === null) {
        foreach ($camlar as $cid) {   // seçilen camın tedarikçisi
            if (!empty($siparisCamlari[$cid]['supplier_id'])) {
                $tedarikci = (int) $siparisCamlari[$cid]['supplier_id'];
                break;
            }
        }
    }
    if ($tedarikci !== null && !row('SELECT id FROM suppliers WHERE id = ?', [$tedarikci])) {
        $tedarikci = null;
    }
    $alacak = $neden === 'laboratuvar' && !empty($v['alacak']);
    if ($alacak && $tedarikci === null) {
        throw new DomainException('İade alacağı için tedarikçiyi seçin.');
    }
    $sorumlu = (int) ($v['sorumlu_id'] ?? 0) ?: null;
    return transaction(static function () use ($siparisId, $neden, $goz, $maliyet, $camlar, $tedarikci, $alacak, $sorumlu, $v): int {
        $id = insert('cam_hatalari', [
            'order_id'      => $siparisId,
            'supplier_id'   => $tedarikci,
            'neden'         => $neden,
            'goz'           => $goz,
            'sorumlu_id'    => $sorumlu,
            'maliyet'       => $maliyet,
            'yeniden_yapim' => $camlar ? 1 : 0,
            'alacak_durum'  => $alacak ? 'bekliyor' : 'yok',
            'alacak_tutar'  => $alacak ? $maliyet : null,
            'aciklama'      => mb_substr(trim((string) ($v['aciklama'] ?? '')), 0, 500) ?: null,
            'created_by'    => (int) (current_user()['id'] ?? 0) ?: null,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        // Yeniden yapılacak camlar depo listesine "Eksik" olarak döner (cam siparişi fişine yeniden girebilir).
        foreach ($camlar as $cid) {
            q("UPDATE prescription_lens_items SET stock_status = 'stokta_yok', ordered_at = NULL, arrived_at = NULL, cam_siparis_id = NULL, delivery_id = NULL WHERE id = ?", [$cid]);
        }
        return $id;
    });
}

/** Laboratuvar iadesini kapatır. 'alindi': tedarikçi carisine iade/alacak kaydı (borcu azaltır). */
function cam_hata_alacak_kapat(int $id, string $durum, ?float $tutar = null): void
{
    if (!in_array($durum, ['alindi', 'reddedildi'], true)) {
        throw new DomainException('Geçersiz durum.');
    }
    $h = row('SELECT * FROM cam_hatalari WHERE id = ?', [$id]);
    if (!$h || $h['alacak_durum'] !== 'bekliyor') {
        throw new DomainException('Bu kayıtta bekleyen iade yok.');
    }
    $tutar = round($tutar ?? (float) $h['alacak_tutar'], 2);
    if ($durum === 'alindi' && $tutar <= 0) {
        throw new DomainException('İade tutarı 0\'dan büyük olmalı.');
    }
    transaction(static function () use ($h, $durum, $tutar): void {
        $n = q("UPDATE cam_hatalari SET alacak_durum = ?, alacak_tutar = ?, updated_at = ? WHERE id = ? AND alacak_durum = 'bekliyor'", [$durum, $durum === 'alindi' ? $tutar : $h['alacak_tutar'], date('Y-m-d H:i:s'), (int) $h['id']])->rowCount();
        if ($n === 0) {
            throw new DomainException('Kayıt az önce değişti.');
        }
        if ($durum === 'alindi') {
            $pid = insert('supplier_payments', [
                'supplier_id' => (int) $h['supplier_id'],
                'amount'      => $tutar,
                'method'      => 'iade',
                'note'        => mb_substr('Hatalı cam iadesi · ' . order_no((int) $h['order_id']), 0, 255),
                'created_by'  => (int) (current_user()['id'] ?? 0) ?: null,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            q('UPDATE cam_hatalari SET alacak_payment_id = ? WHERE id = ?', [$pid, (int) $h['id']]);
        }
    });
}

/** Kapatılmış iadeyi yeniden "bekliyor"a alır (cari kaydı silinir). */
function cam_hata_alacak_geri_al(int $id): void
{
    $h = row('SELECT * FROM cam_hatalari WHERE id = ?', [$id]);
    if (!$h || !in_array($h['alacak_durum'], ['alindi', 'reddedildi'], true)) {
        throw new DomainException('Geri alınacak iade kaydı yok.');
    }
    transaction(static function () use ($h): void {
        if ($h['alacak_payment_id']) {
            q("DELETE FROM supplier_payments WHERE id = ? AND method = 'iade'", [(int) $h['alacak_payment_id']]);
        }
        q("UPDATE cam_hatalari SET alacak_durum = 'bekliyor', alacak_payment_id = NULL, updated_at = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int) $h['id']]);
    });
}

/** Kaydı siler (yanlış girildiyse). Alınmış iadenin cari kaydı da silinir. Camların stok durumu değişmez. */
function cam_hata_sil(int $id): void
{
    $h = row('SELECT * FROM cam_hatalari WHERE id = ?', [$id]);
    if (!$h) {
        return;
    }
    transaction(static function () use ($h): void {
        if ($h['alacak_payment_id']) {
            q("DELETE FROM supplier_payments WHERE id = ? AND method = 'iade'", [(int) $h['alacak_payment_id']]);
        }
        q('DELETE FROM cam_hatalari WHERE id = ?', [(int) $h['id']]);
    });
}

/** Cari ekstreden silinmek istenen ödeme bir cam iadesine mi ait? */
function cam_hata_odeme_bagli_mi(int $paymentId): ?array
{
    if (!table_var_mi('cam_hatalari')) {
        return null;
    }
    return row('SELECT * FROM cam_hatalari WHERE alacak_payment_id = ?', [$paymentId]);
}

function cam_hata_siparis_listesi(int $siparisId): array
{
    return rows('SELECT h.*, s.name AS tedarikci, u.full_name AS sorumlu FROM cam_hatalari h LEFT JOIN suppliers s ON s.id = h.supplier_id LEFT JOIN user_accounts u ON u.id = h.sorumlu_id WHERE h.order_id = ? ORDER BY h.id DESC', [$siparisId]);
}

/** Siparişin yeniden yapım maliyeti (kârlılıktan düşülür). */
function cam_hata_siparis_maliyeti(int $siparisId): float
{
    if (!table_var_mi('cam_hatalari')) {
        return 0.0;
    }
    // Tedarikçiden alınan iade maliyeti karşılar.
    return (float) scalar("SELECT COALESCE(SUM(maliyet - CASE WHEN alacak_durum = 'alindi' THEN COALESCE(alacak_tutar, 0) ELSE 0 END), 0) FROM cam_hatalari WHERE order_id = ?", [$siparisId]);
}

/**
 * Dönem raporu. $bas/$bit: 'YYYY-AA-GG' (dahil).
 * Dönüş: ['adet','maliyet','iade_alinan','iade_bekleyen','neden' => [k => [adet, maliyet]], 'tedarikci' => [...], 'sorumlu' => [...], 'ay' => [...]]
 */
function cam_hata_rapor(string $bas, string $bit): array
{
    $p = [$bas . ' 00:00:00', $bit . ' 23:59:59'];
    $k = 'h.created_at >= ? AND h.created_at <= ?';
    $r = row("SELECT COUNT(*) AS adet, COALESCE(SUM(maliyet), 0) AS maliyet,
                     COALESCE(SUM(CASE WHEN alacak_durum = 'alindi' THEN alacak_tutar ELSE 0 END), 0) AS iade_alinan,
                     COALESCE(SUM(CASE WHEN alacak_durum = 'bekliyor' THEN alacak_tutar ELSE 0 END), 0) AS iade_bekleyen
                FROM cam_hatalari h WHERE $k", $p) ?: [];
    $grup = static fn(string $sql): array => rows($sql, $p);
    return [
        'adet'          => (int) ($r['adet'] ?? 0),
        'maliyet'       => (float) ($r['maliyet'] ?? 0),
        'iade_alinan'   => (float) ($r['iade_alinan'] ?? 0),
        'iade_bekleyen' => (float) ($r['iade_bekleyen'] ?? 0),
        'neden'         => $grup("SELECT h.neden AS k, COUNT(*) AS adet, COALESCE(SUM(h.maliyet), 0) AS maliyet FROM cam_hatalari h WHERE $k GROUP BY h.neden ORDER BY adet DESC"),
        'tedarikci'     => $grup("SELECT s.name AS k, h.supplier_id AS id, COUNT(*) AS adet, SUM(CASE WHEN h.neden = 'laboratuvar' THEN 1 ELSE 0 END) AS lab, COALESCE(SUM(h.maliyet), 0) AS maliyet FROM cam_hatalari h JOIN suppliers s ON s.id = h.supplier_id WHERE $k GROUP BY h.supplier_id, s.name ORDER BY lab DESC, adet DESC"),
        'sorumlu'       => $grup("SELECT COALESCE(u.full_name, '—') AS k, COUNT(*) AS adet, COALESCE(SUM(h.maliyet), 0) AS maliyet FROM cam_hatalari h LEFT JOIN user_accounts u ON u.id = h.sorumlu_id WHERE $k AND h.neden IN ('olcu','montaj','kirilma') GROUP BY h.sorumlu_id, u.full_name ORDER BY adet DESC"),
    ];
}

/** Tedarikçi karnesi için: dönemde laboratuvar hatası adedi (tedarikçi id → adet). */
function cam_hata_lab_sayilari(?string $bas): array
{
    if (!table_var_mi('cam_hatalari')) {
        return [];
    }
    $s = [];
    foreach (rows("SELECT supplier_id, COUNT(*) AS n FROM cam_hatalari WHERE neden = 'laboratuvar' AND supplier_id IS NOT NULL" . ($bas ? ' AND created_at >= ?' : '') . ' GROUP BY supplier_id', $bas ? [$bas . ' 00:00:00'] : []) as $r) {
        $s[(int) $r['supplier_id']] = (int) $r['n'];
    }
    return $s;
}

/** Menü rozeti: bekleyen laboratuvar iadeleri. */
function cam_hata_rozet(): int
{
    try {
        return (int) scalar("SELECT COUNT(*) FROM cam_hatalari WHERE alacak_durum = 'bekliyor'");
    } catch (Throwable) {
        return 0;
    }
}
