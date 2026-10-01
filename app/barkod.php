<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — Barkod / karekod çözümleme + çevrimdışı kopya (4.12.0)

   barkod_coz(): USB okuyucudan gelen metni ilgili kayda çevirir:
     • GS1 / ÜTS karekodu  (01)GTIN (17)SKT (10)Parti (21)Seri
     • Çerçeve barkodu (EAN-13 vb.) → çerçeve kartı
     • Sipariş durum bağlantısı (durum.php?k=…) veya sipariş no (#00123) → sipariş
   Sonuç her zaman bu kurulumun içindeki bir sayfadır; dış adrese yönlendirmez.

   cevrimdisi_ozet(): Masaüstü uygulamasının internet kesildiğinde göstereceği
   salt okunur kopya: açık siparişler (no, ad, telefon, aşama, teslim sözü,
   yetki varsa kalan bakiye). Masaüstü bunu Windows kullanıcı hesabına bağlı
   şifreyle (DPAPI) diskte saklar; çıkışta ve mağaza değişince siler.
   ========================================================================== */

const GS1_AYIRICI = "\x1D";

/** GS1 öğe ayrıştırma. Dönüş: ['gtin','skt','parti','seri','urt'] (bulunamayan ''). urt: üretim tarihi (AI 11, 4.13.0). */
function gs1_coz(string $kod): array
{
    $k = str_replace(['(', ')'], '', $kod);   // "(01)…" yazımı
    $k = ltrim($k, "]C1d2Q3 ");              // okuyucu ön ekleri (AIM)
    $s = ['gtin' => '', 'skt' => '', 'parti' => '', 'seri' => '', 'urt' => ''];
    $i = 0;
    $n = strlen($k);
    $sabit = ['01' => 14, '17' => 6, '11' => 6, '15' => 6];
    while ($i < $n - 1) {
        if ($k[$i] === GS1_AYIRICI) {
            $i++;
            continue;
        }
        $ai = substr($k, $i, 2);
        $i += 2;
        if (isset($sabit[$ai])) {
            $v = substr($k, $i, $sabit[$ai]);
            $i += $sabit[$ai];
            if ($ai === '01') {
                $s['gtin'] = $v;
            } elseif ($ai === '17') {
                $s['skt'] = $v;
            } elseif ($ai === '11') {
                $s['urt'] = $v;
            }
            continue;
        }
        if ($ai === '10' || $ai === '21') {
            // Değişken uzunluk: ayırıcıya kadar; ayırıcı yoksa (okuyucu göndermediyse) sonraki bilinen AI'ye kadar değil, sona kadar.
            $son = strpos($k, GS1_AYIRICI, $i);
            $v = $son === false ? substr($k, $i) : substr($k, $i, $son - $i);
            $i = $son === false ? $n : $son + 1;
            $s[$ai === '10' ? 'parti' : 'seri'] = mb_substr($v, 0, 40);
            continue;
        }
        break;   // tanınmayan AI: dur
    }
    if ($s['gtin'] !== '' && !preg_match('/^\d{14}$/', $s['gtin'])) {
        $s['gtin'] = '';
    }
    $s['skt'] = gs1_tarih($s['skt']);
    $s['urt'] = gs1_tarih($s['urt']);
    return $s;
}

/** GS1 YYMMDD → YYYY-MM-DD (gün 00 = ayın son günü); geçersizse ''. */
function gs1_tarih(string $v): string
{
    if (!preg_match('/^(\d{2})(\d{2})(\d{2})$/', $v, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
        return '';
    }
    $gun = $m[3] === '00' ? (int) date('t', (int) strtotime("20$m[1]-$m[2]-01")) : (int) $m[3];
    return checkdate((int) $m[2], $gun, 2000 + (int) $m[1]) ? sprintf('20%s-%s-%02d', $m[1], $m[2], $gun) : '';
}

/**
 * Okutulan kodu çözer. Dönüş:
 *   ['tur' => 'cerceve'|'siparis'|'uts'|'bilinmiyor', 'hedef' => göreli adres, 'etiket' => string, 'ayrinti' => array]
 */
function barkod_coz(string $ham): array
{
    $kod = trim(preg_replace('/[\x00-\x1C\x1E-\x1F\x7F]/', '', $ham) ?? '');
    $kod = mb_substr($kod, 0, 200);
    if ($kod === '') {
        return ['tur' => 'bilinmiyor', 'hedef' => '', 'etiket' => 'Boş kod', 'ayrinti' => []];
    }
    $duz = str_replace(GS1_AYIRICI, '', $kod);

    // Sipariş durum bağlantısı (sipariş fişindeki karekod)
    if (preg_match('~durum\.php\?(?:m=\d+&)?k=([A-Za-z0-9]{8,24})~', $duz, $m)) {
        $o = row('SELECT id FROM orders WHERE public_token = ?', [$m[1]]);
        if ($o) {
            return ['tur' => 'siparis', 'hedef' => 'order.php?id=' . (int) $o['id'], 'etiket' => order_no((int) $o['id']), 'ayrinti' => []];
        }
    }
    // 4.16.0 — Garanti kartı karekodu → garanti kaydı
    if (preg_match('~garanti\.php\?(?:m=\d+&)?k=([A-Za-z0-9]{10,24})~', $duz, $m) && function_exists('garanti_bul_token')) {
        try {
            $g = garanti_bul_token($m[1]);
        } catch (Throwable) {
            $g = null;
        }
        if ($g) {
            return ['tur' => 'garanti', 'hedef' => 'garantiler.php?id=' . (int) $g['id'], 'etiket' => garanti_no((int) $g['id']) . ' · ' . $g['urun'], 'ayrinti' => []];
        }
    }
    // Garanti no: G00012
    if (preg_match('/^G0*(\d{1,9})$/i', $duz, $m) && function_exists('garanti_bul')) {
        try {
            $g = garanti_bul((int) $m[1]);
        } catch (Throwable) {
            $g = null;
        }
        if ($g) {
            return ['tur' => 'garanti', 'hedef' => 'garantiler.php?id=' . (int) $g['id'], 'etiket' => garanti_no((int) $g['id']) . ' · ' . $g['urun'], 'ayrinti' => []];
        }
    }
    // Sipariş no: #00123 / 00123
    if (preg_match('/^#?0*(\d{1,9})$/', $duz, $m) && strlen($duz) <= 7) {
        $o = row('SELECT id FROM orders WHERE id = ?', [(int) $m[1]]);
        if ($o) {
            return ['tur' => 'siparis', 'hedef' => 'order.php?id=' . (int) $o['id'], 'etiket' => order_no((int) $o['id']), 'ayrinti' => []];
        }
    }
    // Çerçeve barkodu (tam eşleşme)
    $f = row('SELECT id, brand, model FROM frame_items WHERE barcode = ?', [$duz]);
    if ($f) {
        return ['tur' => 'cerceve', 'hedef' => 'cerceve.php?duzenle=' . (int) $f['id'], 'etiket' => trim($f['brand'] . ' ' . $f['model']), 'ayrinti' => []];
    }
    // GS1 / ÜTS karekodu
    if (preg_match('/^(\]?[A-Za-z]\d)?\(?01\)?\d{14}/', $kod)) {
        $g = gs1_coz($kod);
        if ($g['gtin'] !== '') {
            // 4.13.0 — ÜTS tekil ürün kaydı varsa ürün kartı açılır (fiyat, durum, sipariş)
            if (function_exists('uts_karekod_coz') && ozellik_acik('uts_bildirim')) {
                $uk = uts_karekod_coz($kod);
                if ($uk['sno'] !== '' || $uk['lno'] !== '') {
                    $uu = uts_urun_karekodla($uk);
                    if ($uu) {
                        return ['tur' => 'uts_urun', 'hedef' => 'uts.php?urun=' . (int) $uu['id'], 'etiket' => uts_urun_etiketi($uu), 'ayrinti' => $g];
                    }
                }
            }
            $adaylar = [$g['gtin'], ltrim($g['gtin'], '0'), substr($g['gtin'], 1)];
            $f = row('SELECT id, brand, model FROM frame_items WHERE barcode IN (' . in_placeholders($adaylar) . ') LIMIT 1', $adaylar);
            if ($f) {
                return ['tur' => 'cerceve', 'hedef' => 'cerceve.php?duzenle=' . (int) $f['id'], 'etiket' => trim($f['brand'] . ' ' . $f['model']), 'ayrinti' => $g];
            }
            // 4.15.1: GS ayırıcısı adreste KORUNUR; silinirse sonuç sayfası parti/seriyi yanlış böler.
            return ['tur' => 'uts', 'hedef' => 'barkod.php?kod=' . rawurlencode($kod), 'etiket' => 'ÜTS ürünü ' . $g['gtin'], 'ayrinti' => $g];
        }
    }
    return ['tur' => 'bilinmiyor', 'hedef' => 'barkod.php?kod=' . rawurlencode($kod), 'etiket' => 'Kayıt bulunamadı', 'ayrinti' => []];
}

/* ---------------- Çevrimdışı kopya ---------------- */

function cevrimdisi_ozet(int $magazaId, array $kullanici): array
{
    $tutar = can_see_amounts();
    $satirlar = rows(
        "SELECT o.id, o.order_stage, o.promised_date, o.created_at, o.delivered_at, o.lens_type, o.frame_info,
                o.total_amount - COALESCE(p.paid, 0) - o.sgk_amount AS kalan,
                c.first_name, c.last_name, c.phone
           FROM orders o " . PAID_JOIN . "
           JOIN customers c ON c.id = o.customer_id
          WHERE (o.order_stage NOT IN ('teslim_edildi','iptal') AND o.created_at >= DATE_SUB(NOW(), INTERVAL 180 DAY))
             OR (o.order_stage = 'teslim_edildi' AND o.delivered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
          ORDER BY o.promised_date IS NULL, o.promised_date, o.id DESC
          LIMIT 500"
    );
    $siparisler = [];
    foreach ($satirlar as $r) {
        $siparisler[] = [
            'no'     => order_no((int) $r['id']),
            'ad'     => trim($r['first_name'] . ' ' . $r['last_name']),
            'tel'    => phone_display($r['phone']),
            'asama'  => stage_label($r['order_stage']),
            'soz'    => $r['promised_date'] ? date_tr($r['promised_date']) : '',
            'tarih'  => date_tr($r['created_at']),
            'cam'    => mb_substr((string) $r['lens_type'], 0, 80),
            'cerceve' => mb_substr((string) $r['frame_info'], 0, 80),
            'kalan'  => $tutar && (float) $r['kalan'] > 0.009 ? money($r['kalan']) : '',
        ];
    }
    return [
        'olusturma' => date('c'),
        'magaza'    => ['id' => $magazaId, 'isim' => (string) (tenant_oturum()['isim'] ?? '')],
        'kullanici' => ['id' => (int) $kullanici['id'], 'ad' => (string) $kullanici['full_name']],
        'magaza_telefon' => setting('shop_phone', ''),
        'siparisler' => $siparisler,
    ];
}
