<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — SGK gözlük hak kontrolü (4.15.0)

   1) OptiFlow kaydından tahmin (her mağaza): müşterinin son SGK'lı siparişi
      (e-reçete no ya da SGK katkısı olan, iptal olmayan) + yaşa göre süre:
        • 14 yaş altı: 12 ay, diğerleri: 24 ay (Ayarlar'dan değiştirilebilir)
      Başka optikte kullanılan haklar OptiFlow'da görünmez: kesin bilgi Medula'dadır.
   2) Medula / e-Devlet doğrulaması: "hak sorgulama / cam ve çerçeve bilgisi"
      ekranının metni (OptiFlow Pro'da mevcut "Aktar" düğmesiyle ya da
      kopyala-yapıştır) çözülür: son alım tarihi, hak var/yok ifadesi,
      sonraki hak tarihi. Ekran düzeni bilinmediği için satır bazlı ve
      anahtar kelimeyle okunur; okunan satırlar personele gösterilir.
   SGK'ya hiçbir istek atılmaz; kimlik bilgisi saklanmaz (T.C. no kaydedilmez).
   ========================================================================== */

function sgk_hak_ayarlari(): array
{
    return [
        'ay'        => max(1, min(120, (int) setting('sgk_hak_ay', '24'))),
        'cocuk_ay'  => max(1, min(120, (int) setting('sgk_hak_cocuk_ay', '12'))),
        'cocuk_yas' => max(0, min(25, (int) setting('sgk_hak_cocuk_yas', '14'))),
    ];
}

/** Tarih + ay (ay sonu taşmasını düzelterek: 31.01 + 1 ay = 28/29.02). */
function sgk_hak_ay_ekle(string $tarih, int $ay): string
{
    $d = new DateTimeImmutable($tarih);
    $hedef = $d->modify('first day of this month')->modify('+' . $ay . ' months');
    $gun = min((int) $d->format('j'), (int) $hedef->format('t'));
    return $hedef->setDate((int) $hedef->format('Y'), (int) $hedef->format('n'), $gun)->format('Y-m-d');
}

/**
 * Müşterinin SGK gözlük hak durumu.
 * Dönüş: [
 *   'durum' => 'var'|'yok'|'bilinmiyor', 'son' => ?'Y-m-d', 'kaynak' => 'optiflow'|'medula'|'',
 *   'hak_tarihi' => ?'Y-m-d', 'kalan_gun' => ?int, 'ay' => int, 'yas' => ?int, 'siparis_id' => ?int,
 *   'medula' => ?array (son sorgu), 'mesaj' => string ]
 */
function sgk_hak_durumu(int $musteriId, int $haricSiparis = 0): array
{
    $a = sgk_hak_ayarlari();
    $m = $musteriId > 0 ? row('SELECT id, birth_year FROM customers WHERE id = ?', [$musteriId]) : null;
    $yas = $m && $m['birth_year'] ? (int) date('Y') - (int) $m['birth_year'] : null;
    $ay = $yas !== null && $yas < $a['cocuk_yas'] ? $a['cocuk_ay'] : $a['ay'];
    $sonuc = ['durum' => 'bilinmiyor', 'son' => null, 'kaynak' => '', 'hak_tarihi' => null, 'kalan_gun' => null, 'ay' => $ay, 'yas' => $yas, 'siparis_id' => null, 'medula' => null, 'mesaj' => ''];
    if (!$m) {
        $sonuc['mesaj'] = 'Müşteri seçilmemiş.';
        return $sonuc;
    }
    // 1) OptiFlow: son SGK'lı sipariş (bu sipariş hariç)
    $sip = row(
        "SELECT id, COALESCE(delivered_at, created_at) AS tarih FROM orders
          WHERE customer_id = ? AND id <> ? AND order_stage <> 'iptal'
            AND (sgk_amount > 0 OR (sgk_erecete IS NOT NULL AND sgk_erecete <> ''))
          ORDER BY COALESCE(delivered_at, created_at) DESC LIMIT 1",
        [$musteriId, $haricSiparis]
    );
    $optSon = $sip ? substr((string) $sip['tarih'], 0, 10) : null;
    $optZaman = $sip ? (string) $sip['tarih'] : '';
    $sonuc['siparis_id'] = $sip ? (int) $sip['id'] : null;

    // 2) Medula / e-Devlet sorguları (süre sınırı yok: hak süresi yıllarca sürebilir)
    $medSonRow = null;
    $medSonrakiRow = null;
    $medHakRow = null;
    if (table_var_mi('sgk_hak_sorgulari')) {
        $medSonRow = row('SELECT * FROM sgk_hak_sorgulari WHERE customer_id = ? AND son_alim IS NOT NULL ORDER BY son_alim DESC, id DESC LIMIT 1', [$musteriId]);
        $medSonrakiRow = row('SELECT * FROM sgk_hak_sorgulari WHERE customer_id = ? AND sonraki_hak IS NOT NULL ORDER BY id DESC LIMIT 1', [$musteriId]);
        $medHakRow = row("SELECT * FROM sgk_hak_sorgulari WHERE customer_id = ? AND hak IN ('var','yok') AND created_at >= ? ORDER BY id DESC LIMIT 1", [$musteriId, date('Y-m-d H:i:s', strtotime('-30 days'))]);
        $sonuc['medula'] = row('SELECT * FROM sgk_hak_sorgulari WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$musteriId]);
    }
    // Sorgudan SONRA OptiFlow'da SGK'lı satış yapıldıysa o sorgunun hükmü artık geçersizdir.
    $sorgudanSonraSatis = static fn(?array $q): bool => $q !== null && $optZaman !== '' && $optZaman > (string) $q['created_at'];

    $son = $optSon;
    $kaynak = $optSon ? 'optiflow' : '';
    if ($medSonRow && (!$son || $medSonRow['son_alim'] >= $son)) {
        $son = (string) $medSonRow['son_alim'];
        $kaynak = 'medula';
    }
    $hak = $son ? sgk_hak_ay_ekle($son, $ay) : null;
    if ($medSonrakiRow && !$sorgudanSonraSatis($medSonrakiRow)) {
        $hak = (string) $medSonrakiRow['sonraki_hak'];   // Medula'nın bildirdiği tarih önceliklidir
        $kaynak = 'medula';
    }
    if ($hak) {
        $kalan = (int) floor((strtotime($hak) - strtotime(date('Y-m-d'))) / 86400);
        $sonuc = array_merge($sonuc, ['son' => $son, 'kaynak' => $kaynak, 'hak_tarihi' => $hak, 'kalan_gun' => $kalan, 'durum' => $kalan > 0 ? 'yok' : 'var']);
    }
    // Son 30 gündeki açık "hak var / yok" ifadesi (ondan sonra OptiFlow'da SGK'lı satış yoksa) geçerlidir.
    $acik = false;
    if ($medHakRow && !$sorgudanSonraSatis($medHakRow)) {
        $sonuc['durum'] = (string) $medHakRow['hak'];
        $sonuc['kaynak'] = 'medula';
        $acik = true;
    }
    if ($acik) {
        $sonuc['mesaj'] = 'Medula\'ya göre (' . date_tr(substr((string) $medHakRow['created_at'], 0, 10)) . ') SGK gözlük hakkı ' . ($sonuc['durum'] === 'var' ? 'VAR' : 'YOK')
            . ($sonuc['durum'] === 'yok' && $sonuc['hak_tarihi'] ? '; hak tarihi ' . date_tr($sonuc['hak_tarihi']) : '') . '.';
        return $sonuc;
    }
    $sonuc['mesaj'] = match ($sonuc['durum']) {
        'var'   => 'SGK gözlük hakkı var' . ($son ? ' (son alım ' . date_tr($son) . ')' : '') . '.',
        'yok'   => 'SGK hakkı ' . date_tr((string) $sonuc['hak_tarihi']) . ' tarihinde dolar' . ($sonuc['kalan_gun'] > 0 ? ' (' . $sonuc['kalan_gun'] . ' gün)' : '') . '. Numara 0,50 D ve üzeri değiştiyse doktor raporuyla erken yenilenebilir.',
        default => 'OptiFlow\'da bu müşterinin SGK\'lı alımı yok; başka optikte kullanmış olabilir. Medula\'dan doğrulayın.',
    };
    if ($sonuc['kaynak'] === 'optiflow') {
        $sonuc['mesaj'] .= ' (OptiFlow kaydına göre; başka optikteki alımlar görünmez.)';
    }
    return $sonuc;
}

/* ---------------- Medula / e-Devlet ekranı ---------------- */

/** Metin bir hak sorgu / cam-çerçeve geçmişi ekranına mı benziyor (reçete ekranı değil)? */
function sgk_hak_metni_mi(string $metin): bool
{
    $u = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $metin), 'UTF-8');
    // Not: "HAKKI" tek başına aranmaz (yaygın ad: Hakkı); yalnızca hak ifadesi kalıpları.
    $hak = preg_match_all('/HAK\s*(SORGU|DURUM|TAR[İI]H|SAH[İI]B)|HAKKI\s*(VAR|YOK|BULUN|MEVCUT|DOL)|ÇERÇEVE\s*B[İI]LG[İI]S[İI]|CAM\s*VE\s*ÇERÇEVE\s*B[İI]LG|SON\s*ALIM|ÖNCEK[İI]\s*(ALIM|TESL[İI]M)|TESL[İI]M\s*GEÇM[İI]Ş/u', $u);
    $recete = preg_match_all('/\bSPH\b|SFER[İI]K|S[İI]L[İI]ND[İI]R[İI]K|S[İI]LEND[İI]R[İI]K|\bAKS\b|\bCYL\b|\bADD\b|E-?REÇETE\s*NO/u', $u);
    if ($hak < 1 || $hak < $recete) {
        return false;
    }
    // İşaretli diyoptri değerleri (-1.50, +2,00) ya da e-reçete no varsa bu bir reçete ekranıdır.
    // (Tarihler işaretsiz olduğu için karışmaz.)
    if (preg_match_all('/(?<![\d.,])[+-]\s?\d{1,2}[.,]\d{2}\b/u', $metin) >= 2) {
        return false;
    }
    if (function_exists('sgk_parse') && trim((string) (sgk_parse($metin)['erecete'] ?? '')) !== '') {
        return false;
    }
    return true;
}

/** Metindeki tarihleri 'Y-m-d' olarak döner (geçersizler atılır). */
function sgk_hak_tarihler(string $s): array
{
    $t = [];
    if (preg_match_all('/\b(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})\b/', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            if (checkdate((int) $x[2], (int) $x[1], (int) $x[3])) {
                $t[] = sprintf('%04d-%02d-%02d', $x[3], $x[2], $x[1]);
            }
        }
    }
    if (preg_match_all('/\b(\d{4})-(\d{2})-(\d{2})\b/', $s, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            if (checkdate((int) $x[2], (int) $x[3], (int) $x[1])) {
                $t[] = $x[0];
            }
        }
    }
    return $t;
}

/**
 * Hak / geçmiş ekranını çözer.
 * Dönüş: ['ad','soyad','hasta','son_alim' => ?Y-m-d,'sonraki_hak' => ?Y-m-d,'hak' => 'var'|'yok'|'belirsiz','satirlar' => [[tarih, metin], …]]
 */
function sgk_hak_coz(string $metin): array
{
    $metin = str_replace(["\r\n", "\r"], "\n", $metin);
    $kimlik = function_exists('sgk_parse') ? sgk_parse($metin) : ['ad' => '', 'soyad' => '', 'hasta' => ''];
    $sonuc = ['ad' => (string) ($kimlik['ad'] ?? ''), 'soyad' => (string) ($kimlik['soyad'] ?? ''), 'hasta' => (string) ($kimlik['hasta'] ?? ''), 'son_alim' => null, 'sonraki_hak' => null, 'hak' => 'belirsiz', 'satirlar' => []];
    $bugun = date('Y-m-d');
    foreach (explode("\n", $metin) as $satir) {
        $satir = trim(preg_replace('/[ \t]+/u', ' ', $satir) ?? '');
        if ($satir === '' || mb_strlen($satir) > 400) {
            continue;
        }
        $u = mb_strtoupper(str_replace(['i', 'ı'], ['İ', 'I'], $satir), 'UTF-8');
        // Hak ifadesi
        if (preg_match('/HAK(KI)?\s*(BULUNMAMAKTADIR|YOKTUR|YOK\b|DOLMAMIŞ|DOLMAMIS|KULLANILMIŞ)|HAK\s*SAH[İI]B[İI]\s*DEĞ[İI]L/u', $u)) {
            $sonuc['hak'] = 'yok';
        } elseif ($sonuc['hak'] === 'belirsiz' && preg_match('/HAK(KI)?\s*(BULUNMAKTADIR|VARDIR|MEVCUT|VAR\b)|HAK\s*SAH[İI]B[İI]D[İI]R|KULLANAB[İI]L[İI]R/u', $u)) {
            $sonuc['hak'] = 'var';
        }
        $tarihler = sgk_hak_tarihler($satir);
        if (!$tarihler) {
            continue;
        }
        // Sonraki hak tarihi
        if (preg_match('/(SONRAK[İI]|YEN[İI]|B[İI]R\s*SONRAK[İI])\s*HAK|HAK\s*(TAR[İI]H|BAŞLANGIÇ)|YEN[İI]LEME\s*TAR[İI]H|ALAB[İI]LECEĞ[İI]/u', $u)) {
            $sonuc['sonraki_hak'] = max($tarihler);
            continue;
        }
        // Alım satırı: malzeme anahtar kelimesi olmalı; doğum / sorgu / rapor geçerlilik satırları sayılmaz.
        if (preg_match('/DOĞUM|DOGUM|SORGU\w*\s*TAR|RAPOR\s*GEÇERL|GEÇERL[İI]L[İI]K|BASKI\s*TAR|YAZDIRMA/u', $u)) {
            continue;
        }
        if (!preg_match('/ÇERÇEVE|CERCEVE|\bCAM|GÖZLÜK|GOZLUK|LENS|TESL[İI]M|UZAK|YAKIN|REÇETE|RECETE|PROV[İI]ZYON/u', $u)) {
            continue;
        }
        $gecerli = array_values(array_filter($tarihler, static fn($t) => $t <= $bugun));
        if (!$gecerli) {
            continue;
        }
        $t = max($gecerli);
        $sonuc['satirlar'][] = ['tarih' => $t, 'metin' => mb_substr($satir, 0, 200)];
        if ($sonuc['son_alim'] === null || $t > $sonuc['son_alim']) {
            $sonuc['son_alim'] = $t;
        }
    }
    $sonuc['satirlar'] = array_slice($sonuc['satirlar'], 0, 40);
    return $sonuc;
}

/** Hak sorgusunu kaydeder; müşteri / sipariş seçilmişse bağlanır. Dönüş: id */
function sgk_hak_kaydet(array $c, string $kaynak, ?int $musteriId, ?int $siparisId, ?int $gelenId = null): int
{
    if ($musteriId && !row('SELECT id FROM customers WHERE id = ?', [$musteriId])) {
        throw new DomainException('Müşteri bulunamadı.');
    }
    if ($siparisId) {
        $o = row('SELECT id, customer_id FROM orders WHERE id = ?', [$siparisId]);
        if (!$o) {
            throw new DomainException('Sipariş bulunamadı.');
        }
        $musteriId = $musteriId ?: ((int) $o['customer_id'] ?: null);
        if ($musteriId && (int) $o['customer_id'] && (int) $o['customer_id'] !== $musteriId) {
            throw new DomainException('Sipariş bu müşteriye ait değil.');
        }
    }
    return insert('sgk_hak_sorgulari', [
        'customer_id' => $musteriId ?: null,
        'order_id'    => $siparisId ?: null,
        'gelen_id'    => $gelenId ?: null,
        'kaynak'      => in_array($kaynak, ['masaustu', 'yapistir', 'elle'], true) ? $kaynak : 'yapistir',
        'ad'          => mb_substr(trim($c['hasta'] ?: trim($c['ad'] . ' ' . $c['soyad'])), 0, 120) ?: null,
        'son_alim'    => $c['son_alim'],
        'sonraki_hak' => $c['sonraki_hak'],
        'hak'         => in_array($c['hak'], ['var', 'yok'], true) ? $c['hak'] : 'belirsiz',
        'satirlar'    => json_encode(array_slice($c['satirlar'], 0, 40), JSON_UNESCAPED_UNICODE),
        'created_by'  => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}

/** Elle kayıt: personel Medula'da gördüğü son alım tarihini yazar. */
function sgk_hak_elle(int $musteriId, ?int $siparisId, string $sonAlim, string $hak): int
{
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $sonAlim, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1]) || $sonAlim > date('Y-m-d')) {
        throw new DomainException('Son alım tarihi geçersiz.');
    }
    return sgk_hak_kaydet(['ad' => '', 'soyad' => '', 'hasta' => '', 'son_alim' => $sonAlim, 'sonraki_hak' => null, 'hak' => $hak, 'satirlar' => []], 'elle', $musteriId, $siparisId);
}

/** Rozet / renk için durum etiketi. */
function sgk_hak_etiketi(array $d): array
{
    return match ($d['durum']) {
        'var'   => ['SGK hakkı var', 'green'],
        'yok'   => ['SGK hakkı yok', 'red'],
        default => ['SGK hakkı bilinmiyor', 'amber'],
    };
}
