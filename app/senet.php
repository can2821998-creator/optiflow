<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Tedarikçiye verilen senetler ve ödeme takvimi (4.14.0)

   Muhasebe mantığı (ticari teamül):
     • Senet verildiğinde tedarikçinin carisi senet tutarı kadar KAPANIR
       (supplier_payments'a yöntemi "senet" olan bir kayıt yazılır).
       Borç artık senettedir: "ödenmemiş senetler".
     • Vadede ödenince senet "ödendi" olur (cariye ikinci kez yazılmaz).
       Nakit ödenirse gün sonu kasasında tedarikçi ödemesi olarak görünür.
     • Ödenmemiş senet iptal edilirse cari kapama kaydı silinir, borç cariye döner.
   Toplam borç = cari bakiye + ödenmemiş senetler.

   Ödeme takvimi: bekleyen senetler + vadesi olan ama ödenmemiş faturalar
   (ödemeler faturalara en eskiden başlayarak dağıtılır — FIFO).
   ========================================================================== */

const SENET_UYARI_GUN = 3;

function senet_durumlari(): array
{
    return [
        'bekliyor' => ['Ödenecek', 'amber'],
        'odendi'   => ['Ödendi', 'green'],
        'iptal'    => ['İptal', 'gray'],
    ];
}

function senet(int $id): ?array
{
    return row('SELECT t.*, s.name AS tedarikci, s.tax_no AS tedarikci_vkn, s.address AS tedarikci_adres FROM tedarikci_senetleri t JOIN suppliers s ON s.id = t.supplier_id WHERE t.id = ?', [$id]);
}

/**
 * Tedarikçiye senet verir. Cari aynı tutarda kapanır.
 * Dönüş: senet id
 */
function senet_ver(int $tedarikciId, float $tutar, string $vade, string $senetNo = '', ?string $duzenleme = null, ?int $faturaId = null, string $notlar = '', string $duzenlemeYeri = '', string $odemeYeri = ''): int
{
    $tutar = round($tutar, 2);
    $duzenleme = $duzenleme ?: date('Y-m-d');
    if (!row('SELECT id FROM suppliers WHERE id = ?', [$tedarikciId])) {
        throw new DomainException('Tedarikçi bulunamadı.');
    }
    if ($tutar <= 0) {
        throw new DomainException('Senet tutarı 0\'dan büyük olmalı.');
    }
    if (!senet_tarih_gecerli($vade) || !senet_tarih_gecerli($duzenleme)) {
        throw new DomainException('Tarih geçersiz.');
    }
    if ($vade < $duzenleme) {
        throw new DomainException('Vade, düzenleme tarihinden önce olamaz.');
    }
    if ($faturaId && !row('SELECT id FROM supplier_invoices WHERE id = ? AND supplier_id = ?', [$faturaId, $tedarikciId])) {
        throw new DomainException('Seçilen fatura bu tedarikçiye ait değil.');
    }
    $senetNo = mb_substr(trim($senetNo), 0, 40);
    if ($senetNo !== '' && row("SELECT id FROM tedarikci_senetleri WHERE supplier_id = ? AND senet_no = ? AND durum <> 'iptal'", [$tedarikciId, $senetNo])) {
        throw new DomainException('Bu tedarikçiye aynı numaralı bir senet zaten verilmiş.');
    }
    return transaction(static function () use ($tedarikciId, $tutar, $vade, $senetNo, $duzenleme, $faturaId, $notlar, $duzenlemeYeri, $odemeYeri): int {
        $kim = (int) (current_user()['id'] ?? 0) ?: null;
        $pid = insert('supplier_payments', [
            'supplier_id' => $tedarikciId,
            'amount'      => $tutar,
            'method'      => 'senet',
            'note'        => mb_substr('Senet' . ($senetNo !== '' ? ' no ' . $senetNo : '') . ' · vade ' . date_tr($vade), 0, 255),
            'created_by'  => $kim,
            'created_at'  => $duzenleme . ' 12:00:00',
        ]);
        return insert('tedarikci_senetleri', [
            'supplier_id'    => $tedarikciId,
            'invoice_id'     => $faturaId ?: null,
            'payment_id'     => $pid,
            'senet_no'       => $senetNo !== '' ? $senetNo : null,
            'tutar'          => $tutar,
            'duzenleme'      => $duzenleme,
            'vade'           => $vade,
            'duzenleme_yeri' => mb_substr(trim($duzenlemeYeri), 0, 80) ?: null,
            'odeme_yeri'     => mb_substr(trim($odemeYeri), 0, 80) ?: null,
            'durum'          => 'bekliyor',
            'notlar'         => mb_substr(trim($notlar), 0, 255) ?: null,
            'created_by'     => $kim,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
    });
}

function senet_tarih_gecerli(string $t): bool
{
    return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

/** Senedi ödendi işaretler. Cariye yazılmaz (cari senet verilirken kapanmıştı). */
function senet_ode(int $id, string $tarih, string $yontem): void
{
    if (!senet_tarih_gecerli($tarih) || $tarih > date('Y-m-d')) {
        throw new DomainException('Ödeme tarihi geçersiz (ileri tarih olamaz).');
    }
    if (!isset(payment_methods()[$yontem])) {
        throw new DomainException('Ödeme yöntemi geçersiz.');
    }
    $n = q("UPDATE tedarikci_senetleri SET durum = 'odendi', odeme_tarihi = ?, odeme_yontemi = ?, updated_at = ? WHERE id = ? AND durum = 'bekliyor'", [$tarih, $yontem, date('Y-m-d H:i:s'), $id])->rowCount();
    if ($n === 0) {
        throw new DomainException('Senet ödenecek durumda değil.');
    }
}

/** Ödenmiş senedi geri alır (yanlış işaretleme). */
function senet_odeme_geri_al(int $id): void
{
    $n = q("UPDATE tedarikci_senetleri SET durum = 'bekliyor', odeme_tarihi = NULL, odeme_yontemi = NULL, updated_at = ? WHERE id = ? AND durum = 'odendi'", [date('Y-m-d H:i:s'), $id])->rowCount();
    if ($n === 0) {
        throw new DomainException('Senet ödenmiş durumda değil.');
    }
}

/** Ödenmemiş senedi iptal eder; cari kapama kaydı silinir, borç cariye döner. */
function senet_iptal(int $id): void
{
    $s = row('SELECT * FROM tedarikci_senetleri WHERE id = ?', [$id]);
    if (!$s || $s['durum'] !== 'bekliyor') {
        throw new DomainException('Yalnızca ödenmemiş senet iptal edilebilir.');
    }
    transaction(static function () use ($s): void {
        $n = q("UPDATE tedarikci_senetleri SET durum = 'iptal', updated_at = ? WHERE id = ? AND durum = 'bekliyor'", [date('Y-m-d H:i:s'), (int) $s['id']])->rowCount();
        if ($n === 0) {
            throw new DomainException('Senet az önce değişti.');
        }
        if ($s['payment_id']) {
            q("DELETE FROM supplier_payments WHERE id = ? AND method = 'senet'", [(int) $s['payment_id']]);
        }
    });
}

/** Cari ekstreden bir ödeme silinmek istendiğinde: senede bağlıysa engelle. */
function senet_odeme_bagli_mi(int $paymentId): ?array
{
    if (!table_var_mi('tedarikci_senetleri')) {
        return null;
    }
    return row("SELECT * FROM tedarikci_senetleri WHERE payment_id = ? AND durum <> 'iptal'", [$paymentId]);
}

/* ---------------- Özetler ---------------- */

/** Ödenmemiş senet toplamı (tedarikçi verilirse yalnızca onun). */
function senet_bekleyen_toplam(?int $tedarikciId = null): float
{
    if (!table_var_mi('tedarikci_senetleri')) {
        return 0.0;
    }
    return (float) ($tedarikciId
        ? scalar("SELECT COALESCE(SUM(tutar), 0) FROM tedarikci_senetleri WHERE durum = 'bekliyor' AND supplier_id = ?", [$tedarikciId])
        : scalar("SELECT COALESCE(SUM(tutar), 0) FROM tedarikci_senetleri WHERE durum = 'bekliyor'"));
}

/** Menü rozeti: gecikmiş + SENET_UYARI_GUN içinde vadesi gelen senet sayısı. */
function senet_rozet(): int
{
    try {
        return (int) scalar("SELECT COUNT(*) FROM tedarikci_senetleri WHERE durum = 'bekliyor' AND vade <= ?", [date('Y-m-d', strtotime('+' . SENET_UYARI_GUN . ' days'))]);
    } catch (Throwable) {
        return 0;
    }
}

/**
 * Ödeme takvimi grupları. Dönüş: ['gecikmis' => [...], 'yakin' => [...], 'bu_ay' => [...], 'sonra' => [...]]
 * Her öğe: ['tur' => 'senet'|'fatura', 'id', 'supplier_id', 'tedarikci', 'tutar', 'vade', 'no', ...]
 */
function odeme_takvimi(): array
{
    $bugun = date('Y-m-d');
    $yakin = date('Y-m-d', strtotime('+7 days'));
    $ayson = date('Y-m-t');
    $gruplar = ['gecikmis' => [], 'yakin' => [], 'bu_ay' => [], 'sonra' => []];
    $ekle = static function (array $o) use (&$gruplar, $bugun, $yakin, $ayson): void {
        $g = $o['vade'] < $bugun ? 'gecikmis' : ($o['vade'] <= $yakin ? 'yakin' : ($o['vade'] <= $ayson ? 'bu_ay' : 'sonra'));
        $gruplar[$g][] = $o;
    };
    foreach (rows("SELECT t.*, s.name AS tedarikci FROM tedarikci_senetleri t JOIN suppliers s ON s.id = t.supplier_id WHERE t.durum = 'bekliyor' ORDER BY t.vade, t.id") as $s) {
        $ekle(['tur' => 'senet', 'id' => (int) $s['id'], 'supplier_id' => (int) $s['supplier_id'], 'tedarikci' => $s['tedarikci'], 'tutar' => (float) $s['tutar'], 'vade' => $s['vade'], 'no' => (string) $s['senet_no']]);
    }
    foreach (vadesi_acik_faturalar() as $f) {
        $ekle(['tur' => 'fatura', 'id' => (int) $f['id'], 'supplier_id' => (int) $f['supplier_id'], 'tedarikci' => $f['tedarikci'], 'tutar' => (float) $f['kalan'], 'vade' => $f['due_date'], 'no' => (string) $f['invoice_no'], 'fatura_tutar' => (float) $f['amount']]);
    }
    foreach ($gruplar as &$g) {
        usort($g, static fn($a, $b) => [$a['vade'], $a['tur']] <=> [$b['vade'], $b['tur']]);
    }
    unset($g);
    return $gruplar;
}

/**
 * Vadesi girilmiş ve henüz ödenmemiş (kısmen ödenmiş) faturalar.
 * Her tedarikçinin tüm ödemeleri (senetle kapama dahil) faturalara en eskiden başlayarak dağıtılır.
 */
function vadesi_acik_faturalar(): array
{
    $odenen = [];
    foreach (rows('SELECT supplier_id, COALESCE(SUM(amount), 0) AS t FROM supplier_payments GROUP BY supplier_id') as $r) {
        $odenen[(int) $r['supplier_id']] = (float) $r['t'];
    }
    $sonuc = [];
    foreach (rows('SELECT i.*, s.name AS tedarikci FROM supplier_invoices i JOIN suppliers s ON s.id = i.supplier_id ORDER BY i.supplier_id, i.invoice_date, i.id') as $i) {
        $sid = (int) $i['supplier_id'];
        $kalanOdeme = $odenen[$sid] ?? 0.0;
        $tutar = (float) $i['amount'];
        $dusen = min($tutar, max(0.0, $kalanOdeme));
        $odenen[$sid] = $kalanOdeme - $dusen;
        $kalan = round($tutar - $dusen, 2);
        if ($kalan > 0.009 && !empty($i['due_date'])) {
            $i['kalan'] = $kalan;
            $sonuc[] = $i;
        }
    }
    return $sonuc;
}

/**
 * Zamanlanmış görev: günde bir kez, gecikmiş ya da SENET_UYARI_GUN içinde vadesi gelen senet varsa
 * süper yetkililere telefon bildirimi gönderir. Dönüş: bildirim gönderildiyse true.
 */
function senet_uyari_gorev(): bool
{
    $bugun = date('Y-m-d');
    if (setting('senet_uyari_gun', '') === $bugun) {
        return false;
    }
    setting_set('senet_uyari_gun', $bugun);
    $sinir = date('Y-m-d', strtotime('+' . SENET_UYARI_GUN . ' days'));
    $r = row("SELECT COUNT(*) AS adet, COALESCE(SUM(tutar), 0) AS toplam, MIN(vade) AS ilk FROM tedarikci_senetleri WHERE durum = 'bekliyor' AND vade <= ?", [$sinir]);
    if (!$r || (int) $r['adet'] === 0 || !function_exists('push_send')) {
        return false;
    }
    $gecikmis = (string) $r['ilk'] < $bugun;
    $ids = array_map('intval', array_column(rows("SELECT id FROM user_accounts WHERE role = 'super_yetkili' AND is_active = 1"), 'id'));
    if (!$ids) {
        return false;
    }
    try {
        push_send([
            'title' => ($gecikmis ? 'Vadesi geçmiş senet var' : 'Senet vadesi yaklaşıyor') . ' · ' . (int) $r['adet'] . ' adet',
            'body'  => 'Toplam ' . money($r['toplam']) . ' · ilk vade ' . date_tr((string) $r['ilk']),
            'url'   => 'senetler.php',
            'tag'   => 'senet-vade',
        ], $ids);
    } catch (Throwable $e) {
        app_log('senet uyarı: ' . $e->getMessage());
        return false;
    }
    return true;
}

/* ---------------- Tutar yazıyla (senet / bono için) ---------------- */

/** 1234,50 → "Bin İki Yüz Otuz Dört Türk Lirası Elli Kuruş" */
function tutar_yaziyla(float $tutar): string
{
    $tutar = round(abs($tutar), 2);
    $lira = (int) floor($tutar + 0.000001);
    $kurus = (int) round(($tutar - $lira) * 100);
    if ($kurus === 100) {
        $lira++;
        $kurus = 0;
    }
    $metin = ($lira === 0 ? 'Sıfır' : sayi_yaziyla($lira)) . ' Türk Lirası';
    if ($kurus > 0) {
        $metin .= ' ' . sayi_yaziyla($kurus) . ' Kuruş';
    }
    return $metin;
}

function sayi_yaziyla(int $n): string
{
    $birler = ['', 'Bir', 'İki', 'Üç', 'Dört', 'Beş', 'Altı', 'Yedi', 'Sekiz', 'Dokuz'];
    $onlar = ['', 'On', 'Yirmi', 'Otuz', 'Kırk', 'Elli', 'Altmış', 'Yetmiş', 'Seksen', 'Doksan'];
    $basamaklar = ['', 'Bin', 'Milyon', 'Milyar', 'Trilyon'];
    if ($n === 0) {
        return 'Sıfır';
    }
    $parcalar = [];
    $i = 0;
    while ($n > 0 && $i < count($basamaklar)) {
        $uc = $n % 1000;
        $n = intdiv($n, 1000);
        if ($uc > 0) {
            $y = intdiv($uc, 100);
            $o = intdiv($uc % 100, 10);
            $b = $uc % 10;
            $k = [];
            if ($y > 0) {
                $k[] = ($y > 1 ? $birler[$y] . ' ' : '') . 'Yüz';
            }
            if ($o > 0) {
                $k[] = $onlar[$o];
            }
            if ($b > 0) {
                $k[] = $birler[$b];
            }
            $grup = implode(' ', $k);
            if ($i === 1 && $uc === 1) {
                $grup = '';   // "Bir Bin" değil "Bin"
            }
            $parcalar[] = trim($grup . ' ' . $basamaklar[$i]);
        }
        $i++;
    }
    return trim(implode(' ', array_reverse($parcalar)));
}
