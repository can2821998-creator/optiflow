<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Kontakt lens takibi (4.12.0)

   Lens satışında kutu sayısı ve kutu başına kullanım süresi girilir; bitiş
   tarihi hesaplanır. Bitişe X gün kala müşteri "Hatırlatmalar › Kontakt lens"
   listesine düşer; WhatsApp otomasyonu açıksa (ve müşteri izinliyse) mesaj
   kendiliğinden kuyruğa girer.
   ========================================================================== */

/** Yaygın kullanım süreleri (gün) — kutu başına. */
function lens_kullanim_secenekleri(): array
{
    return [
        1   => 'Günlük (1 gün)',
        14  => '15 günlük',
        30  => 'Aylık',
        90  => '3 aylık',
        180 => '6 aylık',
        365 => 'Yıllık',
    ];
}

function lens_goz_secenekleri(): array
{
    return ['cift' => 'İki göz', 'sag' => 'Sağ', 'sol' => 'Sol'];
}

/**
 * Bitiş tarihi. $kutuGun: bir kutunun (tek göz) kaç gün yettiği — ör. 6'lı aylık kutu = 180.
 * İki göz için aynı kutudan kullanılıyorsa süre yarıya iner.
 */
function lens_bitis_hesapla(string $baslangic, int $kutuAdet, int $kutuGun, string $goz): string
{
    $kutuAdet = max(1, min(99, $kutuAdet));
    $kutuGun = max(1, min(3650, $kutuGun));
    $toplam = $kutuAdet * $kutuGun;
    if ($goz === 'cift') {
        $toplam = (int) floor($toplam / 2);
    }
    $toplam = max(1, $toplam);
    return date('Y-m-d', strtotime($baslangic . ' +' . $toplam . ' days'));
}

function lens_hatirlatma_gun(): int
{
    return max(1, min(60, (int) setting('lens_hatirlat_gun', '10')));
}

/** Kayıt ekle. Dönüş: yeni id. */
function lens_ekle(array $v): int
{
    $cid = (int) ($v['customer_id'] ?? 0);
    $urun = trim((string) ($v['urun'] ?? ''));
    $bas = (string) ($v['baslangic'] ?? date('Y-m-d'));
    $goz = isset(lens_goz_secenekleri()[$v['goz'] ?? '']) ? (string) $v['goz'] : 'cift';
    if ($cid <= 0 || !row('SELECT id FROM customers WHERE id = ?', [$cid])) {
        throw new DomainException('Müşteri bulunamadı.');
    }
    if ($urun === '') {
        throw new DomainException('Lens ürün adını yazın.');
    }
    if (!valid_date($bas)) {
        throw new DomainException('Başlangıç tarihi geçersiz.');
    }
    $adet = max(1, min(99, (int) ($v['kutu_adet'] ?? 1)));
    $gun = max(1, min(3650, (int) ($v['kutu_gun'] ?? 30)));
    // Aynı müşterinin aynı göz için önceki aktif kaydı "yenilendi" olur.
    q("UPDATE lens_takip SET durum = 'yenilendi' WHERE customer_id = ? AND durum = 'aktif' AND (goz = ? OR goz = 'cift' OR ? = 'cift')", [$cid, $goz, $goz]);
    $id = insert('lens_takip', [
        'customer_id' => $cid,
        'order_id'    => !empty($v['order_id']) ? (int) $v['order_id'] : null,
        'urun'        => mb_substr($urun, 0, 160),
        'goz'         => $goz,
        'kutu_adet'   => $adet,
        'kutu_gun'    => $gun,
        'baslangic'   => $bas,
        'bitis'       => lens_bitis_hesapla($bas, $adet, $gun, $goz),
        'durum'       => 'aktif',
        'not_metin'   => mb_substr(trim((string) ($v['not'] ?? '')), 0, 255) ?: null,
        'created_by'  => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
    audit('lens_ekle', 'customer', $cid, ['ürün' => mb_substr($urun, 0, 60)]);
    return $id;
}

/** Bitişi yaklaşan (veya geçen) aktif lensler — WhatsApp günlük görevi için. */
function lens_bitenler(int $limit = 200): array
{
    $gun = lens_hatirlatma_gun();
    return rows(
        "SELECT l.*, c.first_name, c.last_name, c.phone
           FROM lens_takip l JOIN customers c ON c.id = l.customer_id
          WHERE l.durum = 'aktif' AND l.bitis <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND l.bitis >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
          ORDER BY l.bitis LIMIT " . (int) $limit,
        [$gun]
    );
}

/**
 * Hatırlatma ekranı için: reminder_* listeleriyle aynı biçim (id = müşteri id).
 * "Arandı / ertele / kapat" kararları reminders tablosunda 'lens' türüyle tutulur.
 */
function reminder_lens(int $limit = 200): array
{
    $satirlar = [];
    foreach (lens_bitenler($limit) as $l) {
        $satirlar[] = [
            'id'          => (int) $l['customer_id'],
            'first_name'  => $l['first_name'],
            'last_name'   => $l['last_name'],
            'phone'       => $l['phone'],
            'birth_year'  => null,
            'son_tarih'   => $l['baslangic'],
            'son_siparis' => $l['order_id'],
            'bitis'       => $l['bitis'],
            'urun'        => $l['urun'],
            'lens_id'     => (int) $l['id'],
        ];
    }
    return function_exists('reminder_decorate') ? reminder_decorate($satirlar, 'lens') : $satirlar;
}

function lens_musteri_kayitlari(int $musteriId): array
{
    return rows('SELECT * FROM lens_takip WHERE customer_id = ? ORDER BY baslangic DESC, id DESC LIMIT 20', [$musteriId]);
}
