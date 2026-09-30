<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Tedarikçiye cam siparişi + stok satın alma önerisi (4.12.0)

   Cam sipariş fişi: "Eksik" (stokta yok) camlar tedarikçi seçilerek bir fişte
   toplanır. Fiş yazdırılır ya da WhatsApp / e-posta metni olarak gönderilir;
   "Gönderildi" denince camlar "Depoya sipariş verildi" olur. Camların gelişi
   mevcut Depo · Stok akışıyla işaretlenir (teslimat kaydı, siparişi atölyeye
   alma gibi mevcut kurallar aynen çalışır).

   Satın alma önerisi: kritik seviyedeki çerçeveler (son 90 günlük satış
   hızına göre) ve fişe girmemiş eksik camlar, tedarikçi bazında.
   ========================================================================== */

function cam_fis_durumlari(): array
{
    return [
        'taslak'     => ['Taslak', 'amber'],
        'gonderildi' => ['Gönderildi', 'blue'],
        'tamamlandi' => ['Tamamlandı', 'green'],
        'iptal'      => ['İptal', 'gray'],
    ];
}

/** Fişe girmemiş eksik camlar (sipariş ve müşteri bilgisiyle). */
function cam_eksikler(?int $tedarikciId = null): array
{
    $ek = $tedarikciId ? ' AND (i.supplier_id = ? OR i.supplier_id IS NULL)' : '';
    return rows(
        "SELECT i.*, r.order_id, r.lens_design, r.pd, r.right_pd, r.left_pd, r.right_height, r.left_height,
                o.first_name, o.last_name, o.promised_date
           FROM prescription_lens_items i
           JOIN prescription_records r ON r.id = i.prescription_id
           JOIN orders o ON o.id = r.order_id
          WHERE i.stock_status = 'stokta_yok' AND i.cam_siparis_id IS NULL AND o.order_stage <> 'iptal'$ek
          ORDER BY o.promised_date IS NULL, o.promised_date, o.id, i.lens_no
          LIMIT 500",
        $tedarikciId ? [$tedarikciId] : []
    );
}

function cam_fis_kalemleri(int $fisId): array
{
    return rows(
        "SELECT i.*, r.order_id, r.lens_design, r.pd, r.right_pd, r.left_pd, r.right_height, r.left_height,
                o.first_name, o.last_name, o.promised_date
           FROM prescription_lens_items i
           JOIN prescription_records r ON r.id = i.prescription_id
           JOIN orders o ON o.id = r.order_id
          WHERE i.cam_siparis_id = ?
          ORDER BY o.id, i.lens_no",
        [$fisId]
    );
}

/** Fiş oluşturur; seçilen camlar fişe bağlanır. Dönüş: fiş id. */
function cam_fis_olustur(int $tedarikciId, array $kalemIdler, string $not = ''): int
{
    $kalemIdler = array_values(array_unique(array_filter(array_map('intval', $kalemIdler))));
    if (!$kalemIdler) {
        throw new DomainException('En az bir cam seçin.');
    }
    if (!row('SELECT id FROM suppliers WHERE id = ? AND is_active = 1', [$tedarikciId])) {
        throw new DomainException('Tedarikçi seçin.');
    }
    return transaction(static function () use ($tedarikciId, $kalemIdler, $not): int {
        $id = insert('cam_siparisleri', [
            'supplier_id' => $tedarikciId,
            'durum'       => 'taslak',
            'notlar'      => mb_substr(trim($not), 0, 500) ?: null,
            'created_by'  => (int) (current_user()['id'] ?? 0) ?: null,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        $yer = in_placeholders($kalemIdler);
        $n = q(
            "UPDATE prescription_lens_items SET cam_siparis_id = ?, supplier_id = ?
              WHERE id IN ($yer) AND stock_status = 'stokta_yok' AND cam_siparis_id IS NULL",
            array_merge([$id, $tedarikciId], $kalemIdler)
        )->rowCount();
        if ($n === 0) {
            throw new DomainException('Seçilen camlar başka bir fişe girmiş ya da artık eksik değil.');
        }
        audit('cam_fis', 'cam_siparis', $id, ['tedarikçi' => supplier_label($tedarikciId), 'cam' => $n]);
        return $id;
    });
}

/** Fiş gönderildi: camlar "Depoya sipariş verildi" olur. */
function cam_fis_gonderildi(int $fisId, string $kanal, string $ref = ''): int
{
    $f = row('SELECT * FROM cam_siparisleri WHERE id = ?', [$fisId]);
    if (!$f || $f['durum'] !== 'taslak') {
        throw new DomainException('Yalnızca taslak fiş gönderilebilir.');
    }
    return transaction(static function () use ($fisId, $kanal, $ref): int {
        $n = q(
            "UPDATE prescription_lens_items SET stock_status = 'siparis_verildi', ordered_at = NOW(), arrived_at = NULL
              WHERE cam_siparis_id = ? AND stock_status = 'stokta_yok'",
            [$fisId]
        )->rowCount();
        update('cam_siparisleri', [
            'durum'         => 'gonderildi',
            'kanal'         => in_array($kanal, ['whatsapp', 'eposta', 'yazdir', 'portal', 'telefon'], true) ? $kanal : 'diger',
            'tedarikci_ref' => mb_substr(trim($ref), 0, 60) ?: null,
            'gonderim_at'   => date('Y-m-d H:i:s'),
        ], 'id = ?', [$fisId]);
        audit('cam_fis_gonder', 'cam_siparis', $fisId, ['kanal' => $kanal, 'cam' => $n]);
        return $n;
    });
}

/** Taslak fiş iptal: camlar fişten çıkar, yeniden "eksik" listesine döner. */
function cam_fis_iptal(int $fisId): void
{
    $f = row('SELECT * FROM cam_siparisleri WHERE id = ?', [$fisId]);
    if (!$f || $f['durum'] !== 'taslak') {
        throw new DomainException('Yalnızca taslak fiş iptal edilebilir.');
    }
    transaction(static function () use ($fisId): void {
        q('UPDATE prescription_lens_items SET cam_siparis_id = NULL WHERE cam_siparis_id = ? AND stock_status = \'stokta_yok\'', [$fisId]);
        update('cam_siparisleri', ['durum' => 'iptal'], 'id = ?', [$fisId]);
    });
}

/** Gelen camlar işaretlendikçe fişi kendiliğinden "tamamlandı" yapar. */
function cam_fisleri_tamamla(): void
{
    q(
        "UPDATE cam_siparisleri c SET durum = 'tamamlandi', teslim_at = NOW()
          WHERE c.durum = 'gonderildi'
            AND NOT EXISTS (SELECT 1 FROM prescription_lens_items i WHERE i.cam_siparis_id = c.id AND i.stock_status <> 'stokta_var')"
    );
}

/** Tek camın okunur tarifi: "Sağ · SPH -2,00 CYL -0,75 AKS 180 ADD +2,00". */
function cam_kalem_tarifi(array $i): string
{
    $goz = match ((string) $i['eye']) { 'R', 'S', 'r', 's' => 'Sağ', 'L', 'l' => 'Sol', default => (string) ($i['lens_label'] ?: '') };
    $p = [];
    foreach (['sph' => 'SPH', 'cyl' => 'CYL', 'axis' => 'AKS', 'add_power' => 'ADD'] as $k => $e) {
        if (trim((string) ($i[$k] ?? '')) !== '') {
            $p[] = $e . ' ' . $i[$k];
        }
    }
    $grup = ($i['item_group'] ?? '') === 'yakin' ? ' (yakın)' : '';
    return trim($goz . $grup . ($p ? ' · ' . implode(' ', $p) : ''));
}

/** WhatsApp / e-posta için düz metin fiş. */
function cam_fis_metni(array $fis, array $kalemler): string
{
    $satir = [];
    $satir[] = setting('shop_name', 'OptiFlow') . ' — Cam siparişi #' . (int) $fis['id'] . ' (' . date_tr($fis['created_at']) . ')';
    $satir[] = '';
    $sonSiparis = 0;
    foreach ($kalemler as $i) {
        if ((int) $i['order_id'] !== $sonSiparis) {
            $sonSiparis = (int) $i['order_id'];
            $pd = trim((string) ($i['right_pd'] ?? '')) !== '' ? 'PD ' . $i['right_pd'] . '/' . $i['left_pd'] : (trim((string) $i['pd']) !== '' ? 'PD ' . $i['pd'] : '');
            $yuk = trim((string) ($i['right_height'] ?? '')) !== '' ? 'Yük. ' . $i['right_height'] . '/' . $i['left_height'] : '';
            $satir[] = '▪ ' . order_no($sonSiparis) . ' · ' . mb_substr((string) $i['first_name'], 0, 1) . '. ' . $i['last_name'] . ($pd || $yuk ? ' · ' . trim($pd . ' ' . $yuk) : '');
        }
        $satir[] = '   ' . trim((string) ($i['lens_type'] ?: $i['lens_value'])) . ' — ' . cam_kalem_tarifi($i);
    }
    if (!empty($fis['notlar'])) {
        $satir[] = '';
        $satir[] = 'Not: ' . $fis['notlar'];
    }
    $satir[] = '';
    $satir[] = 'Toplam ' . count($kalemler) . ' cam. ' . setting('shop_phone', '');
    return implode("\n", $satir);
}

/* ---------------- Satın alma önerisi ---------------- */

/**
 * Çerçeve önerisi: kritik seviyedeki (qty <= min_qty) aktif çerçeveler.
 * Önerilen adet = 30 günlük satış tahmini (son 90 gün / 3) + asgari stok − eldeki; en az asgari stoğa tamamlar.
 */
function stok_cerceve_onerileri(): array
{
    $satirlar = rows(
        "SELECT f.*, s.name AS tedarikci,
                COALESCE((SELECT -SUM(m.delta) FROM frame_moves m
                           WHERE m.frame_item_id = f.id AND m.reason = 'satis' AND m.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)), 0) AS satis90
           FROM frame_items f LEFT JOIN suppliers s ON s.id = f.supplier_id
          WHERE f.is_active = 1 AND f.qty <= f.min_qty
          ORDER BY s.name IS NULL, s.name, f.brand, f.model
          LIMIT 500"
    );
    foreach ($satirlar as &$r) {
        $aylik = (int) ceil(max(0, (int) $r['satis90']) / 3);
        $r['oneri'] = max((int) $r['min_qty'] - (int) $r['qty'], $aylik + (int) $r['min_qty'] - (int) $r['qty'], 1);
        $r['aylik_satis'] = $aylik;
    }
    unset($r);
    return $satirlar;
}

/** Eksik camların lens tipine göre özeti (fişe girmemişler). */
function stok_cam_ozeti(): array
{
    return rows(
        "SELECT COALESCE(NULLIF(i.lens_type, ''), 'Belirtilmemiş') AS lens_type, i.supplier_id, s.name AS tedarikci, COUNT(*) AS adet,
                MIN(o.promised_date) AS en_yakin
           FROM prescription_lens_items i
           JOIN prescription_records r ON r.id = i.prescription_id
           JOIN orders o ON o.id = r.order_id
           LEFT JOIN suppliers s ON s.id = i.supplier_id
          WHERE i.stock_status = 'stokta_yok' AND i.cam_siparis_id IS NULL AND o.order_stage <> 'iptal'
          GROUP BY lens_type, i.supplier_id, s.name
          ORDER BY MIN(o.promised_date) IS NULL, MIN(o.promised_date), COUNT(*) DESC"
    );
}

/** Tedarikçiye gidecek çerçeve listesi metni. */
function stok_cerceve_metni(string $tedarikci, array $kalemler): string
{
    $s = [setting('shop_name', 'OptiFlow') . ' — çerçeve siparişi (' . date_tr(date('Y-m-d')) . ')', 'Sayın ' . $tedarikci . ',', ''];
    foreach ($kalemler as $r) {
        $s[] = '▪ ' . trim($r['brand'] . ' ' . $r['model'] . ' ' . $r['color'] . ' ' . $r['size']) . ' — ' . (int) $r['oneri'] . ' adet';
    }
    $s[] = '';
    $s[] = setting('shop_phone', '');
    return trim(implode("\n", $s));
}
