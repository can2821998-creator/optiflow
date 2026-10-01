<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Garanti kaydı ve garanti kartı (4.16.0)

   • Teslim edilen gözlük / güneş gözlüğü siparişinden kalem bazında garanti
     (çerçeve, cam, güneş gözlüğü) açılır. "Otomatik" ayarı açıksa teslimde
     kendiliğinden oluşur. Süreler Garantiler › Ayarlar'dan (varsayılan 24 ay).
   • Garanti kartı karekodludur: müşteri okutunca garanti.php'de (oturumsuz)
     kalan süreyi ve talep durumunu görür; personel Barkod okut ile okutunca
     garanti kaydı açılır.
   • Garanti talebi = tamir geçmişi: şikâyet → (tedarikçiye gönderildi) →
     tamamlandı (tamir / değişim / iade) ya da reddedildi (kapsam dışı).
     Tedarikçiye gönderimde yazdırılabilir / WhatsApp'la gönderilebilir form.
   SQL taşınabilir (MySQL + testlerdeki SQLite); zaman PHP'de üretilir.
   ========================================================================== */

function garanti_kalemleri(): array
{
    return ['cerceve' => 'Çerçeve', 'cam' => 'Cam', 'gunes' => 'Güneş gözlüğü', 'diger' => 'Diğer'];
}

function garanti_talep_durumlari(): array
{
    return [
        'acik'        => ['Mağazada', 'amber'],
        'tedarikcide' => ['Tedarikçide', 'blue'],
        'tamamlandi'  => ['Tamamlandı', 'green'],
        'reddedildi'  => ['Kapsam dışı', 'red'],
    ];
}

function garanti_sonuclari(): array
{
    return ['tamir' => 'Tamir edildi', 'degisim' => 'Yenisiyle değiştirildi', 'iade' => 'Ücret iadesi', 'diger' => 'Diğer'];
}

function garanti_varsayilan_ay(string $kalem): int
{
    $ay = (int) setting('garanti_' . (isset(garanti_kalemleri()[$kalem]) ? $kalem : 'diger') . '_ay', '24');
    return $ay >= 1 && $ay <= 120 ? $ay : 24;
}

/** Kartın arkasındaki koşullar. Ayarlardan değiştirilebilir. */
function garanti_kosullari(): string
{
    $v = trim(setting('garanti_kosullari', ''));
    if ($v !== '') {
        return $v;
    }
    return "Garanti, üretim ve malzeme kaynaklı arızaları kapsar.\n"
        . "Kırılma, çizilme, düşürme, ısı ve kimyasal hasar ile yetkisiz müdahale garanti dışıdır.\n"
        . "Garanti talebinde bu kart ve ürün mağazamıza getirilmelidir.\n"
        . "Tamir veya değişimde geçen süre garanti süresine eklenir.";
}

function garanti_no(int $id): string
{
    return 'G' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/** 22 karakter, tahmin edilemez anahtar (karekod adresinde). */
function garanti_token_uret(): string
{
    $alfabe = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $t = '';
    for ($i = 0; $i < 22; $i++) {
        $t .= $alfabe[random_int(0, strlen($alfabe) - 1)];
    }
    return $t;
}

function garanti_tarih_temizle(?string $v): ?string
{
    $v = trim((string) $v);
    if (preg_match('/^(\d{2})[.\/](\d{2})[.\/](\d{4})$/', $v, $m)) {
        $v = "$m[3]-$m[2]-$m[1]";
    }
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
        return null;
    }
    return $v;
}

function garanti_ay_ekle(string $tarih, int $ay): string
{
    return (new DateTimeImmutable($tarih))->modify('+' . $ay . ' months')->format('Y-m-d');
}

/**
 * Garanti açar.
 * $v: order_id?, customer_id?, kalem, urun, seri_no?, supplier_id?, baslangic? (bugün), ay? | bitis?, kapsam?
 */
function garanti_ekle(array $v): int
{
    $siparis = null;
    if (!empty($v['order_id'])) {
        $siparis = row('SELECT id, customer_id, order_stage FROM orders WHERE id = ?', [(int) $v['order_id']]);
        if (!$siparis) {
            throw new DomainException('Sipariş bulunamadı.');
        }
        if ($siparis['order_stage'] === 'iptal') {
            throw new DomainException('İptal edilmiş siparişe garanti açılmaz.');
        }
    }
    $musteri = (int) ($v['customer_id'] ?? 0) ?: (int) ($siparis['customer_id'] ?? 0);
    if ($musteri && !row('SELECT id FROM customers WHERE id = ?', [$musteri])) {
        $musteri = 0;
    }
    if (!$siparis && !$musteri) {
        throw new DomainException('Garanti bir siparişe ya da müşteriye bağlı olmalı.');
    }
    $kalem = (string) ($v['kalem'] ?? '');
    if (!isset(garanti_kalemleri()[$kalem])) {
        throw new DomainException('Garanti kalemini seçin.');
    }
    $urun = mb_substr(trim((string) ($v['urun'] ?? '')), 0, 160);
    if ($urun === '') {
        throw new DomainException('Ürün adını yazın (ör. marka ve model).');
    }
    $bas = isset($v['baslangic']) && $v['baslangic'] !== '' ? garanti_tarih_temizle((string) $v['baslangic']) : date('Y-m-d');
    if ($bas === null) {
        throw new DomainException('Başlangıç tarihi geçersiz.');
    }
    if (!empty($v['bitis'])) {
        $bit = garanti_tarih_temizle((string) $v['bitis']);
        if ($bit === null || $bit <= $bas) {
            throw new DomainException('Bitiş tarihi başlangıçtan sonra olmalı.');
        }
    } else {
        $ay = (int) ($v['ay'] ?? 0) ?: garanti_varsayilan_ay($kalem);
        if ($ay < 1 || $ay > 120) {
            throw new DomainException('Garanti süresi 1–120 ay olmalı.');
        }
        $bit = garanti_ay_ekle($bas, $ay);
    }
    $ted = (int) ($v['supplier_id'] ?? 0) ?: null;
    if ($ted !== null && !row('SELECT id FROM suppliers WHERE id = ?', [$ted])) {
        $ted = null;
    }
    $simdi = date('Y-m-d H:i:s');
    for ($deneme = 0; ; $deneme++) {
        try {
            return insert('garantiler', [
                'order_id'    => $siparis ? (int) $siparis['id'] : null,
                'customer_id' => $musteri ?: null,
                'kalem'       => $kalem,
                'urun'        => $urun,
                'seri_no'     => mb_substr(trim((string) ($v['seri_no'] ?? '')), 0, 80) ?: null,
                'supplier_id' => $ted,
                'baslangic'   => $bas,
                'bitis'       => $bit,
                'kapsam'      => mb_substr(trim((string) ($v['kapsam'] ?? '')), 0, 500) ?: null,
                'token'       => garanti_token_uret(),
                'durum'       => 'aktif',
                'created_by'  => (int) (current_user()['id'] ?? 0) ?: null,
                'created_at'  => $simdi,
                'updated_at'  => $simdi,
            ]);
        } catch (PDOException $e) {
            if ($deneme >= 3) {   // anahtar çakışması (pratikte olmaz)
                throw $e;
            }
        }
    }
}

/**
 * Siparişten önerilen garanti kalemleri (henüz garantisi olmayanlar).
 * Dönüş: [['kalem','urun','supplier_id'], …]
 */
function garanti_siparis_onerileri(int $siparisId): array
{
    $o = row('SELECT id, transaction_type, frame_info, frame_item_id, lens_type, order_stage FROM orders WHERE id = ?', [$siparisId]);
    if (!$o || $o['order_stage'] === 'iptal') {
        return [];
    }
    $cerceve = trim((string) $o['frame_info']);
    $cerceveTed = null;
    if ((int) $o['frame_item_id']) {
        $f = row('SELECT brand, model, color, supplier_id FROM frame_items WHERE id = ?', [(int) $o['frame_item_id']]);
        if ($f) {
            $cerceve = $cerceve !== '' ? $cerceve : trim($f['brand'] . ' ' . $f['model'] . ' ' . $f['color']);
            $cerceveTed = (int) $f['supplier_id'] ?: null;
        }
    }
    $oneri = [];
    if ($o['transaction_type'] === 'gozluk') {
        $oneri[] = ['kalem' => 'cerceve', 'urun' => $cerceve !== '' ? $cerceve : 'Gözlük çerçevesi', 'supplier_id' => $cerceveTed];
        $cam = row('SELECT i.lens_type, i.lens_label, i.supplier_id FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE r.order_id = ? ORDER BY i.id LIMIT 1', [$siparisId]);
        $camAd = trim((string) ($o['lens_type'] ?: ($cam['lens_type'] ?? '') ?: ($cam['lens_label'] ?? '')));
        if ($cam || $camAd !== '') {
            $oneri[] = ['kalem' => 'cam', 'urun' => $camAd !== '' ? $camAd : 'Gözlük camı', 'supplier_id' => isset($cam['supplier_id']) ? ((int) $cam['supplier_id'] ?: null) : null];
        }
    } elseif ($o['transaction_type'] === 'gunes_gozlugu') {
        $oneri[] = ['kalem' => 'gunes', 'urun' => $cerceve !== '' ? $cerceve : 'Güneş gözlüğü', 'supplier_id' => $cerceveTed];
    }
    $var = array_column(rows("SELECT kalem FROM garantiler WHERE order_id = ? AND durum = 'aktif'", [$siparisId]), 'kalem');
    return array_values(array_filter($oneri, static fn(array $k): bool => !in_array($k['kalem'], $var, true)));
}

/** Önerilen kalemlerin hepsine garanti açar. Başlangıç: teslim tarihi (yoksa bugün). Dönüş: yeni id'ler. */
function garanti_siparisten_olustur(int $siparisId): array
{
    $o = row('SELECT delivered_at FROM orders WHERE id = ?', [$siparisId]);
    if (!$o) {
        throw new DomainException('Sipariş bulunamadı.');
    }
    $bas = $o['delivered_at'] ? substr((string) $o['delivered_at'], 0, 10) : date('Y-m-d');
    $idler = [];
    foreach (garanti_siparis_onerileri($siparisId) as $k) {
        $idler[] = garanti_ekle(['order_id' => $siparisId, 'baslangic' => $bas] + $k);
    }
    return $idler;
}

/** Düzenleme: ürün, seri no, tedarikçi, bitiş, kapsam. */
function garanti_guncelle(int $id, array $v): void
{
    $g = row('SELECT * FROM garantiler WHERE id = ?', [$id]);
    if (!$g) {
        throw new DomainException('Garanti bulunamadı.');
    }
    $urun = mb_substr(trim((string) ($v['urun'] ?? $g['urun'])), 0, 160);
    if ($urun === '') {
        throw new DomainException('Ürün adı boş olamaz.');
    }
    $bit = garanti_tarih_temizle((string) ($v['bitis'] ?? $g['bitis']));
    if ($bit === null || $bit <= $g['baslangic']) {
        throw new DomainException('Bitiş tarihi başlangıçtan sonra olmalı.');
    }
    $ted = array_key_exists('supplier_id', $v) ? ((int) $v['supplier_id'] ?: null) : ($g['supplier_id'] !== null ? (int) $g['supplier_id'] : null);
    if ($ted !== null && !row('SELECT id FROM suppliers WHERE id = ?', [$ted])) {
        $ted = null;
    }
    update('garantiler', [
        'urun'        => $urun,
        'seri_no'     => mb_substr(trim((string) ($v['seri_no'] ?? $g['seri_no'])), 0, 80) ?: null,
        'supplier_id' => $ted,
        'bitis'       => $bit,
        'kapsam'      => mb_substr(trim((string) ($v['kapsam'] ?? $g['kapsam'])), 0, 500) ?: null,
        'updated_at'  => date('Y-m-d H:i:s'),
    ], 'id = ?', [$id]);
}

function garanti_durum_degistir(int $id, string $durum): void
{
    if (!in_array($durum, ['aktif', 'iptal'], true)) {
        throw new DomainException('Geçersiz durum.');
    }
    if ($durum === 'iptal' && (int) scalar("SELECT COUNT(*) FROM garanti_talepleri WHERE garanti_id = ? AND durum IN ('acik','tedarikcide')", [$id]) > 0) {
        throw new DomainException('Açık talebi olan garanti iptal edilemez.');
    }
    q('UPDATE garantiler SET durum = ?, updated_at = ? WHERE id = ?', [$durum, date('Y-m-d H:i:s'), $id]);
}

/** Kaydı talepleriyle birlikte siler (yanlış girildiyse). */
function garanti_sil(int $id): void
{
    transaction(static function () use ($id): void {
        q('DELETE FROM garanti_talepleri WHERE garanti_id = ?', [$id]);
        q('DELETE FROM garantiler WHERE id = ?', [$id]);
    });
}

/**
 * Garantinin bugünkü durumu.
 * Dönüş: ['kod' => 'gecerli'|'bitti'|'iptal', 'etiket', 'ton', 'kalan_gun']
 */
function garanti_durumu(array $g, ?string $bugun = null): array
{
    $bugun ??= date('Y-m-d');
    if ($g['durum'] === 'iptal') {
        return ['kod' => 'iptal', 'etiket' => 'İptal edildi', 'ton' => 'gray', 'kalan_gun' => 0];
    }
    $kalan = (int) ((strtotime((string) $g['bitis']) - strtotime($bugun)) / 86400);
    if ($kalan < 0) {
        return ['kod' => 'bitti', 'etiket' => 'Süresi doldu', 'ton' => 'gray', 'kalan_gun' => 0];
    }
    return ['kod' => 'gecerli', 'etiket' => 'Garanti geçerli', 'ton' => $kalan <= 30 ? 'amber' : 'green', 'kalan_gun' => $kalan];
}

/** "1 yıl 3 ay" / "12 gün" */
function garanti_kalan_metni(int $gun): string
{
    if ($gun < 31) {
        return $gun . ' gün';
    }
    $ay = intdiv($gun, 30);
    $yil = intdiv($ay, 12);
    $ay %= 12;
    return trim(($yil ? $yil . ' yıl ' : '') . ($ay ? $ay . ' ay' : ''));
}

/* ---------------- Talepler (tamir geçmişi) ---------------- */

function garanti_talep_ekle(int $garantiId, string $sikayet): int
{
    $g = row('SELECT * FROM garantiler WHERE id = ?', [$garantiId]);
    if (!$g) {
        throw new DomainException('Garanti bulunamadı.');
    }
    $d = garanti_durumu($g);
    if ($d['kod'] === 'iptal') {
        throw new DomainException('İptal edilmiş garantiye talep açılmaz.');
    }
    if ($d['kod'] === 'bitti') {
        throw new DomainException('Garanti süresi dolmuş; ücretli tamir siparişi açın.');
    }
    $sikayet = mb_substr(trim($sikayet), 0, 500);
    if ($sikayet === '') {
        throw new DomainException('Şikâyeti / arızayı yazın.');
    }
    if ((int) scalar("SELECT COUNT(*) FROM garanti_talepleri WHERE garanti_id = ? AND durum IN ('acik','tedarikcide')", [$garantiId]) > 0) {
        throw new DomainException('Bu garantide zaten açık bir talep var.');
    }
    $simdi = date('Y-m-d H:i:s');
    return insert('garanti_talepleri', [
        'garanti_id' => $garantiId,
        'sikayet'    => $sikayet,
        'durum'      => 'acik',
        'maliyet'    => 0,
        'created_by' => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at' => $simdi,
        'updated_at' => $simdi,
    ]);
}

/** Talebi tedarikçiye gönderildi olarak işaretler. Tedarikçi boşsa garantinin tedarikçisi. */
function garanti_talep_tedarikciye(int $talepId, int $supplierId = 0, ?string $tarih = null): void
{
    $t = row('SELECT t.*, g.supplier_id AS g_supplier FROM garanti_talepleri t JOIN garantiler g ON g.id = t.garanti_id WHERE t.id = ?', [$talepId]);
    if (!$t || $t['durum'] !== 'acik') {
        throw new DomainException('Yalnızca mağazadaki talep tedarikçiye gönderilebilir.');
    }
    $ted = $supplierId ?: (int) $t['g_supplier'];
    if (!$ted || !row('SELECT id FROM suppliers WHERE id = ?', [$ted])) {
        throw new DomainException('Tedarikçiyi seçin.');
    }
    $gun = $tarih !== null && $tarih !== '' ? garanti_tarih_temizle($tarih) : date('Y-m-d');
    if ($gun === null) {
        throw new DomainException('Gönderim tarihi geçersiz.');
    }
    q("UPDATE garanti_talepleri SET durum = 'tedarikcide', supplier_id = ?, gonderim = ?, updated_at = ? WHERE id = ? AND durum = 'acik'", [$ted, $gun, date('Y-m-d H:i:s'), $talepId]);
}

/**
 * Talebi kapatır. $durum: 'tamamlandi' (sonuç türü zorunlu) | 'reddedildi' (kapsam dışı).
 * Tamamlanınca tamirde/değişimde geçen süre garanti süresine eklenir (ayar: garanti_sure_uzat).
 */
function garanti_talep_kapat(int $talepId, string $durum, string $sonucTur = '', string $sonuc = '', float $maliyet = 0.0): void
{
    $t = row('SELECT * FROM garanti_talepleri WHERE id = ?', [$talepId]);
    if (!$t || !in_array($t['durum'], ['acik', 'tedarikcide'], true)) {
        throw new DomainException('Bu talep zaten kapalı.');
    }
    if (!in_array($durum, ['tamamlandi', 'reddedildi'], true)) {
        throw new DomainException('Geçersiz durum.');
    }
    if ($durum === 'tamamlandi' && !isset(garanti_sonuclari()[$sonucTur])) {
        throw new DomainException('Sonucu seçin (tamir, değişim, iade).');
    }
    $sonuc = mb_substr(trim($sonuc), 0, 500);
    if ($durum === 'reddedildi' && $sonuc === '') {
        throw new DomainException('Kapsam dışı sebebini yazın (müşteriye gösterilir).');
    }
    $maliyet = round(max(0.0, $maliyet), 2);
    $simdi = date('Y-m-d H:i:s');
    transaction(static function () use ($t, $durum, $sonucTur, $sonuc, $maliyet, $simdi): void {
        q('UPDATE garanti_talepleri SET durum = ?, sonuc_tur = ?, sonuc = ?, maliyet = ?, kapanis = ?, updated_at = ? WHERE id = ?',
            [$durum, $durum === 'tamamlandi' ? $sonucTur : null, $sonuc ?: null, $maliyet, $simdi, $simdi, (int) $t['id']]);
        if ($durum === 'tamamlandi' && setting('garanti_sure_uzat', '1') === '1') {
            $gun = (int) floor((strtotime(substr($simdi, 0, 10)) - strtotime(substr((string) $t['created_at'], 0, 10))) / 86400);
            if ($gun > 0) {
                $g = row('SELECT bitis FROM garantiler WHERE id = ?', [(int) $t['garanti_id']]);
                $yeni = (new DateTimeImmutable((string) $g['bitis']))->modify('+' . $gun . ' days')->format('Y-m-d');
                q('UPDATE garantiler SET bitis = ?, updated_at = ? WHERE id = ?', [$yeni, $simdi, (int) $t['garanti_id']]);
            }
        }
    });
}

/** Kapalı talebi yeniden açar (yanlış kapatıldıysa). Garanti süresine eklenen gün geri alınmaz. */
function garanti_talep_yeniden_ac(int $talepId): void
{
    $t = row('SELECT * FROM garanti_talepleri WHERE id = ?', [$talepId]);
    if (!$t || !in_array($t['durum'], ['tamamlandi', 'reddedildi'], true)) {
        throw new DomainException('Yeniden açılacak kapalı talep yok.');
    }
    if ((int) scalar("SELECT COUNT(*) FROM garanti_talepleri WHERE garanti_id = ? AND durum IN ('acik','tedarikcide')", [(int) $t['garanti_id']]) > 0) {
        throw new DomainException('Bu garantide zaten açık bir talep var.');
    }
    q('UPDATE garanti_talepleri SET durum = ?, sonuc_tur = NULL, kapanis = NULL, updated_at = ? WHERE id = ?',
        [$t['supplier_id'] ? 'tedarikcide' : 'acik', date('Y-m-d H:i:s'), $talepId]);
}

function garanti_talep_sil(int $talepId): void
{
    q('DELETE FROM garanti_talepleri WHERE id = ?', [$talepId]);
}

function garanti_talepleri(int $garantiId): array
{
    return rows('SELECT t.*, s.name AS tedarikci, u.full_name AS acan FROM garanti_talepleri t LEFT JOIN suppliers s ON s.id = t.supplier_id LEFT JOIN user_accounts u ON u.id = t.created_by WHERE t.garanti_id = ? ORDER BY t.id DESC', [$garantiId]);
}

/** Garanti + müşteri + sipariş + tedarikçi bilgisi. */
function garanti_bul(int $id): ?array
{
    return row(
        'SELECT g.*, c.first_name, c.last_name, c.phone, s.name AS tedarikci, s.phone AS tedarikci_tel, s.email AS tedarikci_eposta
           FROM garantiler g LEFT JOIN customers c ON c.id = g.customer_id LEFT JOIN suppliers s ON s.id = g.supplier_id
          WHERE g.id = ?',
        [$id]
    );
}

function garanti_bul_token(string $token): ?array
{
    if (!preg_match('/^[A-Za-z0-9]{10,24}$/', $token)) {
        return null;
    }
    $g = row('SELECT id FROM garantiler WHERE token = ?', [$token]);
    return $g ? garanti_bul((int) $g['id']) : null;
}

function garanti_siparis_listesi(int $siparisId): array
{
    return rows('SELECT g.*, (SELECT COUNT(*) FROM garanti_talepleri t WHERE t.garanti_id = g.id) AS talep_sayisi,
                        (SELECT COUNT(*) FROM garanti_talepleri t WHERE t.garanti_id = g.id AND t.durum IN (\'acik\',\'tedarikcide\')) AS acik_talep
                   FROM garantiler g WHERE g.order_id = ? ORDER BY g.id', [$siparisId]);
}

/**
 * Personel listesi. $filtre: 'aktif' | 'bitiyor' (30 gün) | 'talep' (açık talep) | 'tedarikcide' | 'tumu'
 * $ara: müşteri adı / telefon / ürün / seri no / garanti no (G00012) / sipariş no (#00123)
 */
function garanti_ara(string $ara = '', string $filtre = 'aktif', int $limit = 200): array
{
    $bugun = date('Y-m-d');
    $w = [];
    $p = [];
    switch ($filtre) {
        case 'bitiyor':
            $w[] = "g.durum = 'aktif' AND g.bitis >= ? AND g.bitis <= ?";
            array_push($p, $bugun, date('Y-m-d', strtotime('+30 days')));
            break;
        case 'talep':
            $w[] = "EXISTS (SELECT 1 FROM garanti_talepleri t WHERE t.garanti_id = g.id AND t.durum IN ('acik','tedarikcide'))";
            break;
        case 'tedarikcide':
            $w[] = "EXISTS (SELECT 1 FROM garanti_talepleri t WHERE t.garanti_id = g.id AND t.durum = 'tedarikcide')";
            break;
        case 'tumu':
            break;
        default:
            $w[] = "g.durum = 'aktif' AND g.bitis >= ?";
            $p[] = $bugun;
    }
    $ara = trim($ara);
    if ($ara !== '') {
        if (preg_match('/^G0*(\d{1,9})$/i', $ara, $m)) {
            $w[] = 'g.id = ?';
            $p[] = (int) $m[1];
        } elseif (preg_match('/^#0*(\d{1,9})$/', $ara, $m)) {
            $w[] = 'g.order_id = ?';
            $p[] = (int) $m[1];
        } else {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $ara) . '%';
            $tel = preg_replace('/\D/', '', $ara) ?? '';
            $kosul = "(g.urun LIKE ? OR g.seri_no LIKE ? OR c.first_name || ' ' || c.last_name LIKE ?";
            array_push($p, $like, $like, $like);
            if (strlen($tel) >= 4) {
                $kosul .= ' OR c.phone LIKE ?';
                $p[] = '%' . $tel . '%';
            }
            $w[] = $kosul . ')';
        }
    }
    $sql = "SELECT g.*, c.first_name, c.last_name, c.phone,
                   (SELECT t.durum FROM garanti_talepleri t WHERE t.garanti_id = g.id ORDER BY t.id DESC LIMIT 1) AS son_talep
              FROM garantiler g LEFT JOIN customers c ON c.id = g.customer_id"
        . ($w ? ' WHERE ' . implode(' AND ', $w) : '')
        . ' ORDER BY ' . ($filtre === 'bitiyor' ? 'g.bitis ASC' : 'g.id DESC') . ' LIMIT ' . max(1, min(500, $limit));
    return rows(garanti_sql_birlestir($sql), $p);
}

/** SQLite '||' ↔ MySQL CONCAT (MySQL'de || varsayılan olarak VEYA'dır). */
function garanti_sql_birlestir(string $sql): string
{
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        return str_replace("c.first_name || ' ' || c.last_name", "CONCAT(c.first_name, ' ', c.last_name)", $sql);
    }
    return $sql;
}

/** Müşterinin garanti başlangıcından sonraki tamir / bakım siparişleri (tamir geçmişi). */
function garanti_musteri_tamirleri(int $musteriId, string $bas): array
{
    if (!$musteriId) {
        return [];
    }
    return rows("SELECT id, order_stage, frame_info, created_at FROM orders WHERE customer_id = ? AND transaction_type = 'tamir' AND created_at >= ? ORDER BY id DESC LIMIT 20", [$musteriId, $bas . ' 00:00:00']);
}

/** Müşteriye gösterilecek kısaltılmış ad: "Ayşe Y." (KVKK: soyadın tamamı ve telefon gösterilmez). */
function garanti_musteri_kisa(?string $ad, ?string $soyad): string
{
    $ad = trim((string) $ad);
    $soyad = trim((string) $soyad);
    return trim($ad . ($soyad !== '' ? ' ' . mb_substr($soyad, 0, 1) . '.' : ''));
}

/** Karekod adresi (müşteri sayfası). */
function garanti_url(array $g): string
{
    return musteri_url('garanti.php', ['k' => (string) $g['token']]);
}

/** Tedarikçiye gönderilecek talep metni (WhatsApp / e-posta). */
function garanti_tedarikci_metni(array $g, array $t): string
{
    $satir = [
        setting('shop_name', 'OptiFlow') . ' — garanti talebi ' . garanti_no((int) $g['id']) . '-' . (int) $t['id'],
        'Ürün: ' . $g['urun'] . ($g['seri_no'] ? ' (seri: ' . $g['seri_no'] . ')' : ''),
        'Satış / garanti başlangıcı: ' . date_tr((string) $g['baslangic']) . ' · bitiş: ' . date_tr((string) $g['bitis']),
        'Arıza: ' . $t['sikayet'],
    ];
    if (!empty($g['order_id'])) {
        $satir[] = 'Sipariş: ' . order_no((int) $g['order_id']);
    }
    return implode("\n", $satir);
}

/** Menü rozeti: açık talepler. */
function garanti_rozet(): int
{
    try {
        return (int) scalar("SELECT COUNT(*) FROM garanti_talepleri WHERE durum IN ('acik','tedarikcide')");
    } catch (Throwable) {
        return 0;
    }
}
