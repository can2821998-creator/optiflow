<?php
declare(strict_types=1);

/* 4.45.0 — Veresiye defteri: kâğıttaki "müşteri sayfası" ve "taksit sözü" ekranda.
   • Taksit planı (odeme_plani): kalan bakiye N eşit taksite, aylık vadeyle bölünür. Ödeme hangi kanaldan gelirse gelsin
     en eski taksitten başlayarak kapatır (ödeme tarihi tutulmaz; plan toplamı − güncel bakiye = ödenen).
   • orders.balance_promise_date her ödemeden sonra sıradaki ödenmemiş taksitle eşitlenir; böylece mevcut "sözü geçmiş"
     uyarıları (menü rozeti, bakiye takibi) plana göre çalışır.
   • Ekstre: müşterinin tüm siparişleri (borç), SGK payı ve ödemeleri (alacak) tarih sırasıyla, yürüyen bakiyeyle.
   SQL taşınabilir (testte SQLite): tarih hesapları PHP'de. */

const DEFTER_EN_COK_TAKSIT = 12;

/** Siparişin kalan bakiyesi (toplam − SGK payı − ödemeler). */
function defter_bakiye(int $orderId): float
{
    $o = row(
        'SELECT o.total_amount, o.sgk_amount, (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = o.id) AS paid
         FROM orders o WHERE o.id = ?',
        [$orderId]
    );
    return $o ? round((float) $o['total_amount'] - (float) $o['sgk_amount'] - (float) $o['paid'], 2) : 0.0;
}

/** $tarih'ten $ay ay sonrası; gün o ayda yoksa ayın son günü (31 Ocak + 1 ay = 28/29 Şubat). */
function defter_ay_ekle(string $tarih, int $ay): string
{
    [$y, $m, $d] = array_map('intval', explode('-', $tarih));
    $m += $ay;
    $y += intdiv($m - 1, 12);
    $m = ($m - 1) % 12 + 1;
    $son = (int) date('t', mktime(0, 0, 0, $m, 1, $y));
    return sprintf('%04d-%02d-%02d', $y, $m, min($d, $son));
}

/**
 * Kalan bakiyeyi $adet taksite böler (kuruş farkı son taksitte), eski planı siler. Dönüş: yeni plan (defter_plan biçimi).
 * Kurala uymayan istek DomainException.
 */
function defter_plan_kur(int $orderId, int $adet, string $ilkVade, int $userId): array
{
    if ($adet < 1 || $adet > DEFTER_EN_COK_TAKSIT) {
        throw new DomainException('Taksit sayısı 1 ile ' . DEFTER_EN_COK_TAKSIT . ' arasında olmalı.');
    }
    if (!valid_date($ilkVade)) {
        throw new DomainException('İlk taksit tarihi geçersiz.');
    }
    $bakiye = defter_bakiye($orderId);
    if ($bakiye <= 0.009) {
        throw new DomainException('Bu siparişin kalan bakiyesi yok.');
    }
    $kurus = (int) round($bakiye * 100);
    $parca = intdiv($kurus, $adet);
    $simdi = date('Y-m-d H:i:s');
    transaction(static function () use ($orderId, $adet, $ilkVade, $userId, $kurus, $parca, $simdi): void {
        q('DELETE FROM odeme_plani WHERE order_id = ?', [$orderId]);
        for ($i = 0; $i < $adet; $i++) {
            insert('odeme_plani', [
                'order_id'   => $orderId,
                'sira'       => $i + 1,
                'vade'       => defter_ay_ekle($ilkVade, $i),
                'tutar'      => ($i === $adet - 1 ? $kurus - $parca * ($adet - 1) : $parca) / 100,
                'created_by' => $userId,
                'created_at' => $simdi,
            ]);
        }
    });
    defter_plan_esitle($orderId);
    return defter_plan($orderId);
}

/**
 * Taksitler ve durumları. Ödenen, en eski taksitten başlayarak dağıtılır.
 * Her satır: sira, vade, tutar, odenen, kalan, durum (odendi | kismi | gecikti | bekliyor).
 */
function defter_plan(int $orderId, ?string $bugun = null): array
{
    $satirlar = rows('SELECT sira, vade, tutar FROM odeme_plani WHERE order_id = ? ORDER BY sira', [$orderId]);
    return $satirlar ? defter_plan_hesapla($satirlar, defter_bakiye($orderId), $bugun ?? date('Y-m-d')) : [];
}

/** Listeler için: [order_id => güncel bakiye] → [order_id => plan] (tek sorgu). */
function defter_planlar_toplu(array $bakiyeler, ?string $bugun = null): array
{
    if (!$bakiyeler) {
        return [];
    }
    $idler = array_map('intval', array_keys($bakiyeler));
    $grup = [];
    foreach (rows('SELECT order_id, sira, vade, tutar FROM odeme_plani WHERE order_id IN (' . in_placeholders($idler) . ') ORDER BY order_id, sira', $idler) as $r) {
        $grup[(int) $r['order_id']][] = $r;
    }
    $sonuc = [];
    foreach ($grup as $oid => $satirlar) {
        $sonuc[$oid] = defter_plan_hesapla($satirlar, (float) $bakiyeler[$oid], $bugun ?? date('Y-m-d'));
    }
    return $sonuc;
}

function defter_plan_hesapla(array $satirlar, float $bakiye, string $bugun): array
{
    $planToplam = array_sum(array_map(static fn($s) => (float) $s['tutar'], $satirlar));
    $odenen = max(0.0, round($planToplam - max(0.0, $bakiye), 2));
    foreach ($satirlar as &$s) {
        $tutar = (float) $s['tutar'];
        $pay = min($tutar, $odenen);
        $odenen = round($odenen - $pay, 2);
        $s['sira'] = (int) $s['sira'];
        $s['tutar'] = $tutar;
        $s['odenen'] = round($pay, 2);
        $s['kalan'] = round($tutar - $pay, 2);
        $s['durum'] = $s['kalan'] <= 0.009 ? 'odendi' : ($s['vade'] < $bugun ? 'gecikti' : ($pay > 0.009 ? 'kismi' : 'bekliyor'));
    }
    unset($s);
    return $satirlar;
}

/** Sıradaki ödenmemiş taksit (yoksa null). */
function defter_siradaki(array $plan): ?array
{
    foreach ($plan as $s) {
        if ($s['durum'] !== 'odendi') {
            return $s;
        }
    }
    return null;
}

/** Ödeme sözü tarihini plana eşitler (plan yoksa dokunmaz). Dönüş: yazılan tarih ya da null. */
function defter_plan_esitle(int $orderId): ?string
{
    $plan = defter_plan($orderId);
    if (!$plan) {
        return null;
    }
    $s = defter_siradaki($plan);
    $tarih = $s['vade'] ?? null;
    update('orders', ['balance_promise_date' => $tarih], 'id = ?', [$orderId]);
    return $tarih;
}

function defter_plan_kaldir(int $orderId): void
{
    q('DELETE FROM odeme_plani WHERE order_id = ?', [$orderId]);
}

/** Planın tek satırlık özeti: "Taksit 2/4 · 15.11.2026 · 500,00 ₺" (bitmişse "Taksitler ödendi"). */
function defter_plan_ozet(array $plan): string
{
    if (!$plan) {
        return '';
    }
    $s = defter_siradaki($plan);
    if (!$s) {
        return 'Taksitler ödendi';
    }
    return 'Taksit ' . $s['sira'] . '/' . count($plan) . ' · ' . date_tr($s['vade']) . ' · ' . money($s['kalan']);
}

/**
 * Müşterinin ekstresi: hareketler (tarih, aciklama, borc, alacak, bakiye, order_id) eski → yeni, son bakiye,
 * açık siparişlerin taksit planları. İptal edilen siparişler ve ödemeleri ekstreye girmez.
 */
function defter_ekstre(int $customerId): array
{
    $siparisler = rows(
        "SELECT id, created_at, total_amount, sgk_amount, transaction_type FROM orders
         WHERE customer_id = ? AND order_stage <> 'iptal' ORDER BY created_at, id",
        [$customerId]
    );
    $h = [];
    $idler = [];
    foreach ($siparisler as $o) {
        $oid = (int) $o['id'];
        $idler[] = $oid;
        $h[] = ['tarih' => (string) $o['created_at'], 'sira' => 0, 'order_id' => $oid,
                'aciklama' => order_no($oid) . ($o['transaction_type'] === 'tamir' ? ' · tamir / bakım' : ' · sipariş'),
                'borc' => (float) $o['total_amount'], 'alacak' => 0.0];
        if ((float) $o['sgk_amount'] > 0.009) {
            $h[] = ['tarih' => (string) $o['created_at'], 'sira' => 1, 'order_id' => $oid,
                    'aciklama' => order_no($oid) . ' · SGK payı', 'borc' => 0.0, 'alacak' => (float) $o['sgk_amount']];
        }
    }
    if ($idler) {
        $yontem = payment_methods();
        foreach (rows('SELECT order_id, amount, method, note, created_at FROM payments WHERE order_id IN (' . in_placeholders($idler) . ') ORDER BY created_at, id', $idler) as $p) {
            $not = trim((string) $p['note']);
            if (in_array($not, ['Bakiye takibinden', 'Veresiye defteri'], true)) {
                $not = '';   // ekranın kendi notu: ekstrede gürültü
            }
            $h[] = ['tarih' => (string) $p['created_at'], 'sira' => 2, 'order_id' => (int) $p['order_id'],
                    'aciklama' => order_no((int) $p['order_id']) . ' · ' . ($yontem[$p['method']] ?? $p['method']) . ($not !== '' ? ' (' . $not . ')' : ''),
                    'borc' => 0.0, 'alacak' => (float) $p['amount']];
        }
    }
    usort($h, static fn($a, $b) => [$a['tarih'], $a['order_id'], $a['sira']] <=> [$b['tarih'], $b['order_id'], $b['sira']]);   // SGK payı kendi siparişinin altında
    $bakiye = 0.0;
    foreach ($h as &$x) {
        $bakiye = round($bakiye + $x['borc'] - $x['alacak'], 2);
        $x['bakiye'] = $bakiye;
    }
    unset($x);
    $planlar = [];
    foreach ($idler as $oid) {
        if ($p = defter_plan($oid)) {
            $planlar[$oid] = $p;
        }
    }
    return ['hareketler' => $h, 'bakiye' => $bakiye, 'planlar' => $planlar];
}

/**
 * Borçlu müşteriler (veresiye defterinin sayfaları): customer_id, ad, telefon, borc, siparis (açık sipariş sayısı),
 * en_eski (gün), sonraki_soz, geciken (sözü geçmiş bakiye). Gecikenler önce, sonra borç büyükten küçüğe.
 */
function defter_musteriler(?string $bugun = null): array
{
    $bugun ??= date('Y-m-d');
    $satirlar = rows(
        "SELECT c.id AS customer_id, c.first_name, c.last_name, c.phone, o.id, o.created_at, o.delivered_at, o.balance_promise_date,
                o.total_amount - o.sgk_amount - (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = o.id) AS bakiye
           FROM orders o JOIN customers c ON c.id = o.customer_id
          WHERE o.order_stage <> 'iptal'
            AND o.total_amount - o.sgk_amount - (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE order_id = o.id) > 0.009"
            . (function_exists('sube_kosul') ? sube_kosul('o') : '')
    );
    $m = [];
    $bugunTs = strtotime($bugun);
    foreach ($satirlar as $r) {
        $cid = (int) $r['customer_id'];
        $m[$cid] ??= ['customer_id' => $cid, 'ad' => trim($r['first_name'] . ' ' . $r['last_name']), 'first_name' => $r['first_name'],
                      'last_name' => $r['last_name'], 'phone' => $r['phone'], 'borc' => 0.0, 'siparis' => 0, 'en_eski' => 0,
                      'sonraki_soz' => null, 'geciken' => 0.0];
        $x = &$m[$cid];
        $x['borc'] = round($x['borc'] + (float) $r['bakiye'], 2);
        $x['siparis']++;
        $yas = (int) floor(($bugunTs - strtotime(substr((string) ($r['delivered_at'] ?: $r['created_at']), 0, 10))) / 86400);
        $x['en_eski'] = max($x['en_eski'], $yas);
        $soz = $r['balance_promise_date'] ? (string) $r['balance_promise_date'] : null;
        if ($soz !== null && ($x['sonraki_soz'] === null || $soz < $x['sonraki_soz'])) {
            $x['sonraki_soz'] = $soz;
        }
        if ($soz !== null && $soz < $bugun) {
            $x['geciken'] = round($x['geciken'] + (float) $r['bakiye'], 2);
        }
        unset($x);
    }
    $m = array_values($m);
    usort($m, static fn($a, $b) => [$b['geciken'] > 0, $b['borc']] <=> [$a['geciken'] > 0, $a['borc']]);
    return $m;
}

/** Müşteriye gönderilecek hesap özeti (WhatsApp). */
function defter_wa_metni(array $musteri, array $ekstre): string
{
    $dukkan = setting('shop_name', 'OptiFlow');
    $satir = ['Merhaba ' . trim((string) $musteri['first_name']) . ', ' . $dukkan . ' hesap özetiniz:', 'Kalan borç: ' . money($ekstre['bakiye'])];
    foreach ($ekstre['planlar'] as $oid => $plan) {
        $s = defter_siradaki($plan);
        if ($s) {
            $satir[] = order_no((int) $oid) . ' sıradaki taksit: ' . date_tr($s['vade']) . ' · ' . money($s['kalan'])
                . ($s['durum'] === 'gecikti' ? ' (vadesi geçti)' : '');
        }
    }
    $satir[] = 'Teşekkür ederiz.';
    return implode("\n", $satir);
}

/* ---------------- Gün sonu kasa (kasa.php) ---------------- */

/** 4.45.0 — Dünden devir: bu tarihten önceki son kapanışta kasada bırakılan nakit (girilmemişse 0). */
function kasa_devir(string $date): float
{
    // 4.50.0 şube: devir şubenin kendi kasasından; "tüm şubeler"de şubelerin devirleri toplanır
    if (function_exists('sube_degerler') && sube_acik() && sube_degerler() === null) {
        $t = 0.0;
        foreach (array_keys(sube_listesi()) as $sid) {
            $t += (float) (scalar('SELECT birakilan FROM cash_counts WHERE count_date < ? AND sube_id = ? ORDER BY count_date DESC, created_at DESC, id DESC LIMIT 1', [$date, $sid]) ?? 0);
        }
        return $t;
    }
    $sk = function_exists('sube_kosul') ? sube_kosul() : '';
    return (float) (scalar(
        'SELECT birakilan FROM cash_counts WHERE count_date < ?' . $sk . ' ORDER BY count_date DESC, created_at DESC, id DESC LIMIT 1',
        [$date]
    ) ?? 0);
}

/** 4.45.0 — Küpür sayımı: banknot ve bozuk para adetleri → toplam. Dönüş: [toplam, 'küpür:adet,…' özeti]. */
const KASA_KUPURLER = ['200', '100', '50', '20', '10', '5', '1', '0.50', '0.25', '0.10', '0.05'];
function kasa_kupur_say(array $adetler): array
{
    $kurus = 0;
    $ozet = [];
    foreach (KASA_KUPURLER as $k) {
        $n = max(0, (int) ($adetler[$k] ?? 0));
        if ($n > 0) {
            $kurus += $n * (int) round((float) $k * 100);
            $ozet[] = $k . ':' . $n;
        }
    }
    return [(float) ($kurus / 100), implode(',', $ozet)];
}
