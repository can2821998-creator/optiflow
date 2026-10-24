<?php
declare(strict_types=1);

/* 4.50.0 — Şubeler (özellik 'sube', merkez panelinden açılır; kapalıyken hiçbir şey değişmez).
   Model (kullanıcı kararı 24.10): TEK HESAP, ORTAK MÜŞTERİ. Müşteri kartı ve reçeteler ortak; sipariş, hızlı satış,
   kasa (gider, gelir, sayım), çerçeve stoku ve ürün stoku (urun_stok) şubeye bağlı.
   Yetki: personel yalnız atandığı şube(ler)i görür (kullanici_subeleri); yönetici hepsini, "Tüm şubeler" dahil.
   Aktif şube oturumda ($_SESSION['sube']; 0 = tüm şubeler, yalnız yönetici). Yeni kayıt aktif şubeye yazılır; "tüm
   şubeler" açıkken yazılacak şube, kullanıcının ilk şubesi (yoksa ana şube).
   Göç v40 her mevcut kaydı 1 numaralı "Merkez" şubesine yazar. SQL taşınabilir (testte SQLite). */

/** Şube özelliği bu mağazada açık mı? (kapalıyken bütün süzgeçler boş döner) */
function sube_acik(): bool
{
    return function_exists('ozellik_acik') && ozellik_acik('sube') && sube_tablo_var();
}

/** Göç v40 çalıştı mı (subeler tablosu var mı)? Yoksa şube kodu hiçbir şey yapmaz (ilk istek, şubesiz test düzenekleri). */
function sube_tablo_var(): bool
{
    return function_exists('table_var_mi') && table_var_mi('subeler');
}

/** Etkin şubeler: id => satır (ad, kisa, adres, telefon). */
function sube_listesi(bool $pasifDahil = false): array
{
    static $onbellek = [];
    $k = $pasifDahil ? 1 : 0;
    if (!isset($onbellek[$k])) {
        $onbellek[$k] = [];
        foreach (rows('SELECT * FROM subeler' . ($pasifDahil ? '' : ' WHERE is_active = 1') . ' ORDER BY sira, id') as $s) {
            $onbellek[$k][(int) $s['id']] = $s;
        }
    }
    return $onbellek[$k];
}

/** Şube adı ('' = bilinmiyor). */
function sube_adi(?int $id): string
{
    return $id ? (string) (sube_listesi(true)[$id]['ad'] ?? '') : '';
}

/** Kullanıcının görebildiği şube numaraları. Yönetici: hepsi; personel: atandıkları (atanmamışsa ana şube). */
function sube_izinli(?array $u = null): array
{
    $u ??= current_user();
    $hepsi = array_keys(sube_listesi());
    if (!$u || ($u['role'] ?? '') === 'super_yetkili') {
        return $hepsi;
    }
    $atanan = array_map('intval', array_column(rows('SELECT sube_id FROM kullanici_subeleri WHERE user_id = ?', [(int) $u['id']]), 'sube_id'));
    $atanan = array_values(array_intersect($atanan, $hepsi));
    return $atanan ?: array_slice($hepsi, 0, 1);
}

/** Aktif şube: 0 = tüm şubeler (yalnız yönetici ve birden çok şube görebilen). Özellik kapalıysa 0. */
function aktif_sube(): int
{
    if (!sube_acik()) {
        return 0;
    }
    $izinli = sube_izinli();
    $s = (int) ($_SESSION['sube'] ?? -1);
    if ($s === 0 && is_super()) {
        return 0;
    }
    if (in_array($s, $izinli, true)) {
        return $s;
    }
    return is_super() && count($izinli) > 1 ? 0 : (int) ($izinli[0] ?? 0);
}

/** Şube seçimi (seçici formu). Geçersiz seçim yok sayılır. */
function sube_sec(int $id): bool
{
    if ($id === 0 ? is_super() : in_array($id, sube_izinli(), true)) {
        $_SESSION['sube'] = $id;
        return true;
    }
    return false;
}

/** Yeni kayda yazılacak şube: aktif şube; "tüm şubeler" açıksa kullanıcının ilk şubesi (yoksa 1). */
function sube_yaz(): ?int
{
    if (!sube_acik()) {
        return null;
    }
    return aktif_sube() ?: ((int) (sube_izinli()[0] ?? 1) ?: 1);
}

/** Süzgeç için şube numaraları: null = süzgeç yok (özellik kapalı ya da yönetici "tüm şubeler"), [] = hiçbiri. */
function sube_degerler(): ?array
{
    if (!sube_acik()) {
        return null;
    }
    $aktif = aktif_sube();
    if ($aktif > 0) {
        return [$aktif];
    }
    return is_super() ? null : array_map('intval', sube_izinli());
}

/** " AND <ifade> = 3" / " AND <ifade> IN (1,3)" / '' (süzgeç yok) / " AND 1 = 0". Sayılar int'e çevrilir (enjeksiyon yok). */
function sube_kosul_ifade(string $ifade): string
{
    $d = sube_degerler();
    if ($d === null) {
        return '';
    }
    if (!$d) {
        return ' AND 1 = 0';
    }
    return count($d) === 1 ? " AND $ifade = " . $d[0] : " AND $ifade IN (" . implode(',', $d) . ')';
}

/** Kaydın açıldığı şubeye göre süzgeç (kasa, satış, stok, rapor). */
function sube_kosul(string $alias = '', string $sutun = 'sube_id'): string
{
    return sube_kosul_ifade(($alias !== '' ? $alias . '.' : '') . $sutun);
}

/** Sipariş listeleri: açıldığı şube YA DA teslim edileceği şube bu şubeyse görünür. */
function sube_kosul_siparis(string $a = 'o'): string
{
    $d = sube_degerler();
    if ($d === null) {
        return '';
    }
    if (!$d) {
        return ' AND 1 = 0';
    }
    $in = count($d) === 1 ? ' = ' . $d[0] : ' IN (' . implode(',', $d) . ')';
    return " AND ($a.sube_id$in OR $a.teslim_sube_id$in)";
}

/** Gözlüğün fiziksel olarak beklediği şube (hazır, aranacak, raf): teslim şubesi, yoksa açıldığı şube. */
function sube_kosul_hazir(string $a = 'o'): string
{
    return sube_kosul_ifade("COALESCE($a.teslim_sube_id, $a.sube_id)");
}

/** Kayıt bu kullanıcının görebileceği şubede mi? (sipariş sayfası gibi doğrudan bağlantılar için) */
function sube_erisim(?int $subeId): bool
{
    if (!sube_acik() || $subeId === null || is_super()) {
        return true;
    }
    return in_array($subeId, sube_izinli(), true);
}

/** Şube kaydı / güncelleme. Dönüş: id. */
function sube_kaydet(array $v, ?int $id = null): int
{
    $ad = mb_substr(trim((string) ($v['ad'] ?? '')), 0, 80);
    if ($ad === '') {
        throw new DomainException('Şube adı gerekli.');
    }
    $kisa = mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', (string) ($v['kisa'] ?? '')) ?? '', 0, 4));
    if ($kisa === '') {
        $kisa = mb_strtoupper(mb_substr($ad, 0, 3));
    }
    $veri = [
        'ad' => $ad, 'kisa' => $kisa,
        'adres' => mb_substr(trim((string) ($v['adres'] ?? '')), 0, 255) ?: null,
        'telefon' => mb_substr(trim((string) ($v['telefon'] ?? '')), 0, 30) ?: null,
        'is_active' => !empty($v['is_active']) || $id === null ? 1 : 0,
    ];
    $ayni = scalar('SELECT id FROM subeler WHERE ad = ?' . ($id ? ' AND id <> ' . (int) $id : ''), [$ad]);
    if ($ayni) {
        throw new DomainException('Bu adla bir şube zaten var.');
    }
    if ($id) {
        if (!$veri['is_active'] && (int) scalar('SELECT COUNT(*) FROM subeler WHERE is_active = 1 AND id <> ?', [$id]) === 0) {
            throw new DomainException('Son etkin şube kapatılamaz.');
        }
        update('subeler', $veri, 'id = ?', [$id]);
        return $id;
    }
    return insert('subeler', $veri + ['sira' => (int) scalar('SELECT COALESCE(MAX(sira), 0) + 1 FROM subeler'), 'created_at' => date('Y-m-d H:i:s')]);
}

/** Personelin şubelerini yazar (boş dizi = atanmamış → ana şube). */
function sube_personel_ata(int $userId, array $subeIdler): void
{
    $gecerli = array_keys(sube_listesi(true));
    $subeIdler = array_values(array_unique(array_filter(array_map('intval', $subeIdler), static fn($s) => in_array($s, $gecerli, true))));
    transaction(static function () use ($userId, $subeIdler): void {
        q('DELETE FROM kullanici_subeleri WHERE user_id = ?', [$userId]);
        foreach ($subeIdler as $s) {
            insert('kullanici_subeleri', ['user_id' => $userId, 'sube_id' => $s]);
        }
    });
}

/** Personelin şubeleri (id listesi). */
function sube_personel(int $userId): array
{
    return array_map('intval', array_column(rows('SELECT sube_id FROM kullanici_subeleri WHERE user_id = ? ORDER BY sube_id', [$userId]), 'sube_id'));
}

/** Şube rozeti (listelerde kayıt hangi şubede): özellik kapalıysa ''. */
function sube_rozet(?int $id): string
{
    if (!sube_acik() || !$id || count(sube_listesi()) < 2) {
        return '';
    }
    $s = sube_listesi(true)[$id] ?? null;
    return $s ? '<span class="sube-rozet" title="' . e((string) $s['ad']) . '">' . e((string) $s['kisa']) . '</span>' : '';
}

/** Yeni kayda eklenecek alan: ['sube_id' => …]. Özellik kapalıyken de ana şube (1) yazılır; sonradan açılınca kayıt sahipsiz kalmaz. */
function sube_alan(): array
{
    if (!sube_tablo_var()) {
        return [];
    }
    return ['sube_id' => sube_acik() ? (sube_yaz() ?? 1) : 1];
}

/**
 * Şubeler karşılaştırması (yönetici): şube başına bugünkü sipariş, tahsilat (sipariş ödemeleri + hızlı satış),
 * atölyede, hazır bekleyen (teslim şubesine göre), geciken, açık bakiye, yoldaki transfer, kasa kapandı mı.
 */
function sube_karsilastirma(?string $bugun = null): array
{
    $bugun ??= date('Y-m-d');
    $bas = $bugun . ' 00:00:00';
    $son = date('Y-m-d', strtotime($bugun . ' +1 day')) . ' 00:00:00';
    $sonuc = [];
    foreach (sube_listesi() as $id => $s) {
        $say = static fn(string $sql, array $p = []): float => (float) (scalar($sql, $p) ?? 0);
        $sonuc[$id] = [
            'sube'     => $s,
            'siparis'  => (int) $say("SELECT COUNT(*) FROM orders WHERE sube_id = ? AND order_stage <> 'iptal' AND created_at >= ? AND created_at < ?", [$id, $bas, $son]),
            'tahsilat' => $say('SELECT COALESCE(SUM(p.amount), 0) FROM payments p JOIN orders o ON o.id = p.order_id WHERE o.sube_id = ? AND p.created_at >= ? AND p.created_at < ?', [$id, $bas, $son])
                        + (table_var_mi('satis_odemeleri') ? $say("SELECT COALESCE(SUM(so.amount), 0) FROM satis_odemeleri so JOIN satislar s ON s.id = so.satis_id WHERE s.sube_id = ? AND s.durum <> 'iptal' AND so.created_at >= ? AND so.created_at < ?", [$id, $bas, $son]) : 0),
            'atolyede' => (int) $say("SELECT COUNT(*) FROM orders WHERE sube_id = ? AND order_stage IN ('siparis_verildi','bekliyor','rx_siparis_verildi','atolyede')", [$id]),
            'hazir'    => (int) $say("SELECT COUNT(*) FROM orders WHERE COALESCE(teslim_sube_id, sube_id) = ? AND order_stage = 'hazirlandi'", [$id]),
            'geciken'  => (int) $say("SELECT COUNT(*) FROM orders WHERE sube_id = ? AND promised_date < ? AND order_stage NOT IN ('hazirlandi','teslim_edildi','iptal')", [$id, $bugun]),
            'bakiye'   => $say("SELECT COALESCE(SUM(o.total_amount - o.sgk_amount - (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = o.id)), 0) FROM orders o
                                WHERE o.sube_id = ? AND o.order_stage <> 'iptal' AND o.total_amount - o.sgk_amount - (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = o.id) > 0.009", [$id]),
            'yolda'    => (int) $say("SELECT COUNT(*) FROM sube_transferleri WHERE hedef_sube_id = ? AND durum = 'yolda'", [$id])
                        + (int) $say("SELECT COUNT(*) FROM orders WHERE teslim_sube_id = ? AND tasima_durum = 'yolda'", [$id]),
            'kasa'     => (bool) $say('SELECT COUNT(*) FROM cash_counts WHERE sube_id = ? AND count_date = ?', [$id, $bugun]),
        ];
    }
    return $sonuc;
}

/** Şubesiz giderlerin (tedarikçi nakit ödemesi) düşüleceği görünüm mü: özellik kapalı, tüm şubeler ya da ana şube. */
function sube_merkez_mi(): bool
{
    $d = sube_degerler();
    return $d === null || in_array((int) array_key_first(sube_listesi()), $d, true);
}

/* ---------------- Stok şubede ---------------- */

/** Ürün stok hareketi: hareketin şubesindeki urun_stok satırı + urunler.stok = şubelerin toplamı. Dönüş: o şubedeki stok. */
function urun_stok_degis(int $urunId, int $delta, ?int $subeId = null): int
{
    $subeId ??= (int) sube_alan()['sube_id'];
    $var = scalar('SELECT stok FROM urun_stok WHERE urun_id = ? AND sube_id = ?', [$urunId, $subeId]);
    if ($var === null) {
        insert('urun_stok', ['urun_id' => $urunId, 'sube_id' => $subeId, 'stok' => $delta]);
    } else {
        q('UPDATE urun_stok SET stok = stok + ? WHERE urun_id = ? AND sube_id = ?', [$delta, $urunId, $subeId]);
    }
    q('UPDATE urunler SET stok = (SELECT COALESCE(SUM(stok), 0) FROM urun_stok WHERE urun_id = ?), updated_at = ? WHERE id = ?', [$urunId, date('Y-m-d H:i:s'), $urunId]);
    return (int) scalar('SELECT stok FROM urun_stok WHERE urun_id = ? AND sube_id = ?', [$urunId, $subeId]);
}

/** Ekranda gösterilecek ürün stoku: aktif şubenin (izinli şubelerin toplamı); süzgeç yoksa toplam. */
function urun_sube_stok(array $u): int
{
    $d = sube_degerler();
    if ($d === null) {
        return (int) $u['stok'];
    }
    if (!$d) {
        return 0;
    }
    return (int) scalar('SELECT COALESCE(SUM(stok), 0) FROM urun_stok WHERE urun_id = ? AND sube_id IN (' . implode(',', $d) . ')', [(int) $u['id']]);
}

/** SQL ifadesi: ürünün aktif şube(ler)deki stoku ($a = urunler takma adı). */
function urun_stok_ifade(string $a = 'urunler'): string
{
    $d = sube_degerler();
    if ($d === null) {
        return "$a.stok";
    }
    return $d ? "(SELECT COALESCE(SUM(us.stok), 0) FROM urun_stok us WHERE us.urun_id = $a.id AND us.sube_id IN (" . implode(',', $d) . '))' : '0';
}

/* ---------------- Başka şubede teslim ---------------- */

/** Teslim şubesini yazar (null/aynı şube = açıldığı şubede). Hazır sipariş başka şubeye çevrilirse yola çıkar. */
function sube_teslim_ayarla(int $orderId, ?int $teslimSube): void
{
    $o = row('SELECT id, sube_id, order_stage FROM orders WHERE id = ?', [$orderId]);
    if (!$o || in_array($o['order_stage'], ['teslim_edildi', 'iptal'], true)) {
        throw new DomainException('Teslim edilmiş ya da iptal edilmiş siparişin teslim şubesi değişmez.');
    }
    if ($teslimSube !== null && !isset(sube_listesi()[$teslimSube])) {
        throw new DomainException('Şube bulunamadı.');
    }
    $farkli = $teslimSube !== null && $teslimSube !== (int) $o['sube_id'];
    update('orders', [
        'teslim_sube_id' => $farkli ? $teslimSube : null,
        'tasima_durum'   => $farkli && $o['order_stage'] === 'hazirlandi' ? 'yolda' : null,
        'tasima_at'      => null,
    ], 'id = ?', [$orderId]);
}

/** Sipariş hazır olduğunda çağrılır: teslim şubesi farklıysa gözlük yola çıkar. Dönüş: yola çıktı mı. */
function sube_tasima_baslat(int $orderId): bool
{
    $o = row('SELECT sube_id, teslim_sube_id FROM orders WHERE id = ?', [$orderId]);
    if (!$o || !$o['teslim_sube_id'] || (int) $o['teslim_sube_id'] === (int) $o['sube_id']) {
        return false;
    }
    update('orders', ['tasima_durum' => 'yolda', 'tasima_at' => null], 'id = ?', [$orderId]);
    return true;
}

/** Teslim şubesi gözlüğü teslim aldı. */
function sube_tasima_teslim(int $orderId, int $userId): void
{
    $o = row('SELECT teslim_sube_id, tasima_durum FROM orders WHERE id = ?', [$orderId]);
    if (!$o || $o['tasima_durum'] !== 'yolda') {
        throw new DomainException('Bu gözlük yolda görünmüyor.');
    }
    if (!sube_erisim((int) $o['teslim_sube_id'])) {
        throw new DomainException('Gözlüğü yalnız teslim şubesi teslim alabilir.');
    }
    update('orders', ['tasima_durum' => 'geldi', 'tasima_at' => date('Y-m-d H:i:s')], 'id = ?', [$orderId]);
}

/** Şubeye gelecek / yoldaki gözlükler (teslim şubesine göre süzülür). */
function sube_yoldaki_gozlukler(): array
{
    return rows(
        "SELECT o.id, o.sube_id, o.teslim_sube_id, o.updated_at, c.first_name, c.last_name
           FROM orders o JOIN customers c ON c.id = o.customer_id
          WHERE o.tasima_durum = 'yolda' AND o.order_stage = 'hazirlandi'" . sube_kosul('o', 'teslim_sube_id') . '
          ORDER BY o.updated_at'
    );
}

/* ---------------- Şubeler arası stok transferi ---------------- */

/**
 * Transfer fişi açar: stok kaynaktan hemen düşer, fiş "yolda". $kalemler: [['tur' => 'cerceve'|'urun', 'id' => …, 'adet' => …], …].
 * Dönüş: transfer no. Kurala uymayan istek DomainException.
 */
function sube_transfer_olustur(int $kaynak, int $hedef, array $kalemler, int $userId, string $not = ''): int
{
    if ($kaynak === $hedef) {
        throw new DomainException('Kaynak ve hedef şube aynı olamaz.');
    }
    if (!isset(sube_listesi()[$kaynak]) || !isset(sube_listesi()[$hedef])) {
        throw new DomainException('Şube bulunamadı.');
    }
    if (!sube_erisim($kaynak)) {
        throw new DomainException('Yalnız kendi şubenizden transfer gönderebilirsiniz.');
    }
    $temiz = [];
    foreach ($kalemler as $k) {
        $adet = (int) ($k['adet'] ?? 0);
        $id = (int) ($k['id'] ?? 0);
        $tur = (string) ($k['tur'] ?? '');
        if ($adet <= 0 || $id <= 0) {
            continue;
        }
        if ($tur === 'cerceve') {
            $f = row('SELECT * FROM frame_items WHERE id = ?', [$id]);
            if (!$f || (int) ($f['sube_id'] ?? 1) !== $kaynak) {
                throw new DomainException('Çerçeve bu şubenin stokunda değil.');
            }
            if ((int) $f['qty'] < $adet) {
                throw new DomainException('"' . frame_item_label($f) . '" için stok yetersiz (' . (int) $f['qty'] . ' adet).');
            }
            $temiz[] = ['tur' => 'cerceve', 'id' => $id, 'adet' => $adet, 'ad' => frame_item_label($f) ?: 'Çerçeve #' . $id];
        } elseif ($tur === 'urun') {
            $u = row('SELECT id, ad, stok_takip FROM urunler WHERE id = ?', [$id]);
            if (!$u || (int) $u['stok_takip'] !== 1) {
                throw new DomainException('Ürün bulunamadı ya da stoku takip edilmiyor.');
            }
            $var = (int) (scalar('SELECT stok FROM urun_stok WHERE urun_id = ? AND sube_id = ?', [$id, $kaynak]) ?? 0);
            if ($var < $adet) {
                throw new DomainException('"' . $u['ad'] . '" için stok yetersiz (' . $var . ' adet).');
            }
            $temiz[] = ['tur' => 'urun', 'id' => $id, 'adet' => $adet, 'ad' => (string) $u['ad']];
        }
    }
    if (!$temiz) {
        throw new DomainException('Transfer için en az bir kalem ve adet seçin.');
    }
    $simdi = date('Y-m-d H:i:s');
    $hedefAd = sube_adi($hedef);
    return transaction(static function () use ($kaynak, $hedef, $temiz, $userId, $not, $simdi, $hedefAd): int {
        $tid = insert('sube_transferleri', ['kaynak_sube_id' => $kaynak, 'hedef_sube_id' => $hedef, 'durum' => 'yolda',
            'not_metni' => mb_substr(trim($not), 0, 255) ?: null, 'created_by' => $userId, 'created_at' => $simdi]);
        foreach ($temiz as $k) {
            insert('sube_transfer_kalemleri', ['transfer_id' => $tid, 'tur' => $k['tur'], 'kaynak_id' => $k['id'], 'ad' => mb_substr($k['ad'], 0, 200), 'adet' => $k['adet']]);
            if ($k['tur'] === 'cerceve') {
                frame_move($k['id'], -$k['adet'], 'transfer', null, 'Transfer #' . $tid . ' → ' . $hedefAd);
            } else {
                urun_stok_degis($k['id'], -$k['adet'], $kaynak);
                insert('urun_hareketleri', ['urun_id' => $k['id'], 'delta' => -$k['adet'], 'sebep' => 'transfer', 'satis_id' => null,
                    'not_metni' => 'Transfer #' . $tid . ' → ' . $hedefAd, 'created_by' => $userId, 'created_at' => $simdi]);
            }
        }
        return $tid;
    });
}

/** Hedef şube transferi teslim alır: stok hedefe eklenir (çerçevede hedef şubedeki aynı kalem, yoksa yeni satır). */
function sube_transfer_teslim(int $transferId, int $userId): void
{
    $t = row('SELECT * FROM sube_transferleri WHERE id = ?', [$transferId]);
    if (!$t || $t['durum'] !== 'yolda') {
        throw new DomainException('Bu transfer yolda değil.');
    }
    $hedef = (int) $t['hedef_sube_id'];
    if (!sube_erisim($hedef)) {
        throw new DomainException('Transferi yalnız hedef şube teslim alabilir.');
    }
    $simdi = date('Y-m-d H:i:s');
    $kaynakAd = sube_adi((int) $t['kaynak_sube_id']);
    transaction(static function () use ($hedef, $userId, $simdi, $transferId, $kaynakAd): void {
        foreach (rows('SELECT * FROM sube_transfer_kalemleri WHERE transfer_id = ?', [$transferId]) as $k) {
            if ($k['tur'] === 'cerceve') {
                $f = row('SELECT * FROM frame_items WHERE id = ?', [(int) $k['kaynak_id']]);
                $hedefId = $f ? sube_cerceve_karsiligi($f, $hedef) : 0;
                if ($hedefId) {
                    frame_move($hedefId, (int) $k['adet'], 'transfer', null, 'Transfer #' . $transferId . ' ← ' . $kaynakAd);
                    update('sube_transfer_kalemleri', ['hedef_id' => $hedefId], 'id = ?', [(int) $k['id']]);
                }
            } else {
                urun_stok_degis((int) $k['kaynak_id'], (int) $k['adet'], $hedef);
                insert('urun_hareketleri', ['urun_id' => (int) $k['kaynak_id'], 'delta' => (int) $k['adet'], 'sebep' => 'transfer', 'satis_id' => null,
                    'not_metni' => 'Transfer #' . $transferId . ' ← ' . $kaynakAd, 'created_by' => $userId, 'created_at' => $simdi]);
            }
        }
        update('sube_transferleri', ['durum' => 'teslim', 'teslim_by' => $userId, 'teslim_at' => $simdi], 'id = ?', [$transferId]);
    });
}

/** Kaynak şube yoldaki transferi geri alır: stok kaynağa döner. */
function sube_transfer_iptal(int $transferId, int $userId): void
{
    $t = row('SELECT * FROM sube_transferleri WHERE id = ?', [$transferId]);
    if (!$t || $t['durum'] !== 'yolda') {
        throw new DomainException('Yalnız yoldaki transfer geri alınabilir.');
    }
    $kaynak = (int) $t['kaynak_sube_id'];
    if (!sube_erisim($kaynak)) {
        throw new DomainException('Transferi yalnız gönderen şube geri alabilir.');
    }
    $simdi = date('Y-m-d H:i:s');
    transaction(static function () use ($transferId, $kaynak, $userId, $simdi): void {
        foreach (rows('SELECT * FROM sube_transfer_kalemleri WHERE transfer_id = ?', [$transferId]) as $k) {
            if ($k['tur'] === 'cerceve') {
                frame_move((int) $k['kaynak_id'], (int) $k['adet'], 'transfer', null, 'Transfer #' . $transferId . ' geri alındı');
            } else {
                urun_stok_degis((int) $k['kaynak_id'], (int) $k['adet'], $kaynak);
                insert('urun_hareketleri', ['urun_id' => (int) $k['kaynak_id'], 'delta' => (int) $k['adet'], 'sebep' => 'transfer', 'satis_id' => null,
                    'not_metni' => 'Transfer #' . $transferId . ' geri alındı', 'created_by' => $userId, 'created_at' => $simdi]);
            }
        }
        update('sube_transferleri', ['durum' => 'iptal', 'teslim_by' => $userId, 'teslim_at' => $simdi], 'id = ?', [$transferId]);
    });
}

/** Çerçevenin hedef şubedeki karşılığı (aynı barkod; barkodsuzsa marka/model/renk/boy); yoksa 0 adetle açılır. */
function sube_cerceve_karsiligi(array $f, int $hedef): int
{
    if (trim((string) $f['barcode']) !== '') {
        $id = scalar('SELECT id FROM frame_items WHERE barcode = ? AND sube_id = ? ORDER BY id LIMIT 1', [$f['barcode'], $hedef]);
    } else {
        $id = scalar("SELECT id FROM frame_items WHERE sube_id = ? AND brand = ? AND COALESCE(model, '') = ? AND COALESCE(color, '') = ? AND COALESCE(size, '') = ? AND COALESCE(barcode, '') = '' ORDER BY id LIMIT 1",
            [$hedef, $f['brand'], (string) $f['model'], (string) $f['color'], (string) $f['size']]);
    }
    if ($id) {
        return (int) $id;
    }
    $kopya = array_intersect_key($f, array_flip(['brand', 'model', 'color', 'size', 'barcode', 'min_qty', 'cost', 'price', 'supplier_id', 'note']));
    return insert('frame_items', $kopya + ['sube_id' => $hedef, 'qty' => 0, 'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
}

/** Transfer listesi (kullanıcının şubelerine giden ya da gelen; yönetici tümü), kalemleriyle. */
function sube_transferler(int $limit = 50): array
{
    $d = sube_degerler();
    if ($d === null) {
        $kosul = '';
    } elseif (!$d) {
        $kosul = ' WHERE 1 = 0';
    } else {
        $in = implode(',', $d);
        $kosul = " WHERE (t.kaynak_sube_id IN ($in) OR t.hedef_sube_id IN ($in))";
    }
    $liste = rows("SELECT t.*, u.full_name AS gonderen FROM sube_transferleri t LEFT JOIN user_accounts u ON u.id = t.created_by$kosul
                    ORDER BY CASE WHEN t.durum = 'yolda' THEN 0 ELSE 1 END, t.id DESC LIMIT " . (int) $limit);
    foreach ($liste as &$t) {
        $t['kalemler'] = rows('SELECT * FROM sube_transfer_kalemleri WHERE transfer_id = ? ORDER BY id', [(int) $t['id']]);
    }
    unset($t);
    return $liste;
}
