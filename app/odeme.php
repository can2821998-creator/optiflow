<?php
declare(strict_types=1);

/* ==========================================================================
   OptiFlow — PayTR ödeme linki (4.12.0)

   Kalan bakiye için PayTR "Link ile Ödeme" API'siyle tek kullanımlık link
   üretilir (link_type=product, min_count=1, max_count=1). Müşteri kartla
   öder; PayTR, bildirim adresine (odeme-bildirim.php) sunucudan sunucuya
   haber verir. Bildirimin HMAC imzası mağazanın kendi PayTR anahtarıyla
   doğrulanır; geçerliyse siparişe "Kredi kartı" tahsilatı KENDİLİĞİNDEN
   yazılır. Aynı bildirim birden çok gelirse ikinci kez yazılmaz.

   Her mağaza kendi PayTR hesabını kullanır (para doğrudan mağazaya geçer).
   Mağaza anahtarları şifreli saklanır (entegrasyon.php).

   Belgeler: dev.paytr.com › Link API (create / delete / callback).
   ========================================================================== */

const PAYTR_CREATE_URL = 'https://www.paytr.com/odeme/api/link/create';
const PAYTR_DELETE_URL = 'https://www.paytr.com/odeme/api/link/delete';

function paytr_bilgileri(): array
{
    return [
        'merchant_id' => preg_replace('/\D/', '', setting('paytr_merchant_id', '')) ?? '',
        'key'         => gizli_ayar('paytr_merchant_key'),
        'salt'        => gizli_ayar('paytr_merchant_salt'),
    ];
}

function paytr_hazir_mi(): bool
{
    $b = paytr_bilgileri();
    return $b['merchant_id'] !== '' && $b['key'] !== '' && $b['salt'] !== '';
}

/** PayTR belirteci: base64(HMAC-SHA256(metin . salt, key)) — ham ikili HMAC. */
function paytr_token(string $metin, string $key, string $salt): string
{
    return base64_encode(hash_hmac('sha256', $metin . $salt, $key, true));
}

/** Bildirim imzası: base64(HMAC-SHA256(callback_id . merchant_oid . salt . status . total_amount, key)). */
function paytr_bildirim_imzasi(array $p, string $key, string $salt): string
{
    return base64_encode(hash_hmac(
        'sha256',
        (string) ($p['callback_id'] ?? '') . (string) ($p['merchant_oid'] ?? '') . $salt . (string) ($p['status'] ?? '') . (string) ($p['total_amount'] ?? ''),
        $key,
        true
    ));
}

/** Bildirim adresi (PayTR localhost / port kabul etmez). */
function odeme_bildirim_url(): string
{
    $kok = function_exists('app_base_url') ? app_base_url() : '';
    return $kok === '' ? '' : rtrim($kok, '/') . '/odeme-bildirim.php';
}

function odeme_bildirim_url_gecerli(string $url): bool
{
    $u = parse_url($url);
    return is_array($u) && ($u['scheme'] ?? '') === 'https' && !isset($u['port'])
        && !empty($u['host']) && !preg_match('/^(localhost|127\.|\[?::1)/i', (string) $u['host']);
}

/** callback_id: "OF<mağaza no>T<rastgele>" — yalnızca harf/rakam, ≤ 64 (PayTR kuralı). */
function odeme_callback_id(int $magazaId): string
{
    return 'OF' . $magazaId . 'T' . bin2hex(random_bytes(12));
}

/** callback_id'den mağaza numarası; biçim tutmazsa 0. */
function odeme_callback_magaza(string $cid): int
{
    return preg_match('/^OF(\d{1,9})T[0-9a-f]{24}$/', $cid, $m) ? (int) $m[1] : 0;
}

/** Siparişin açık (ödenmemiş, süresi dolmamış) linki. */
function odeme_aktif_link(int $siparisId): ?array
{
    return row(
        "SELECT * FROM odeme_linkleri WHERE order_id = ? AND durum = 'olusturuldu'
            AND (son_kullanim IS NULL OR son_kullanim > NOW()) ORDER BY id DESC LIMIT 1",
        [$siparisId]
    );
}

/**
 * Link oluşturur. Dönüş: odeme_linkleri satırı. Hata: DomainException (kullanıcıya gösterilir).
 */
function odeme_linki_olustur(array $siparis, float $tutar): array
{
    if (!paytr_hazir_mi()) {
        throw new DomainException('PayTR bilgileri girilmemiş (Ayarlar › Ödeme linki).');
    }
    $bakiye = round((float) $siparis['balance'], 2);
    $tutar = round($tutar, 2);
    if ($tutar < 1) {
        throw new DomainException('Ödeme linki tutarı en az 1,00 TL olmalı.');
    }
    if ($tutar > $bakiye + 0.001) {
        throw new DomainException('Link tutarı kalan bakiyeden (' . money($bakiye) . ') büyük olamaz.');
    }
    if (odeme_aktif_link((int) $siparis['id'])) {
        throw new DomainException('Bu sipariş için açık bir ödeme linki zaten var. Önce onu iptal edin.');
    }
    $bildirim = odeme_bildirim_url();
    if (!odeme_bildirim_url_gecerli($bildirim)) {
        throw new DomainException('Ödeme bildirimi için sitenin https adresi gerekli (localhost veya port içeren adres kabul edilmez).');
    }
    $magazaId = (int) (tenant_oturum()['id'] ?? 0);
    $b = paytr_bilgileri();
    $gun = max(1, min(30, (int) setting('paytr_gecerlilik_gun', '7')));
    $taksit = max(1, min(12, (int) setting('paytr_max_taksit', '1')));
    $test = setting('paytr_test', '0') === '1';

    $ad = mb_substr(setting('shop_name', 'OptiFlow') . ' - ' . order_no((int) $siparis['id']) . ' bakiye ödemesi', 0, 200);
    if (mb_strlen($ad) < 4) {
        $ad = 'Sipariş ödemesi';
    }
    $fiyat = (string) (int) round($tutar * 100);   // kuruş
    $para = 'TL';
    $tur = 'product';
    $dil = 'tr';
    $adet = '1';
    $cid = odeme_callback_id($magazaId);
    $sonKullanim = date('Y-m-d H:i:s', strtotime('+' . $gun . ' days'));

    $linkId = insert('odeme_linkleri', [
        'order_id'     => (int) $siparis['id'],
        'customer_id'  => (int) $siparis['customer_id'] ?: null,
        'tutar'        => $tutar,
        'saglayici'    => 'paytr',
        'callback_id'  => $cid,
        'durum'        => 'hazirlaniyor',
        'test'         => $test ? 1 : 0,
        'son_kullanim' => $sonKullanim,
        'created_by'   => (int) (current_user()['id'] ?? 0) ?: null,
        'created_at'   => date('Y-m-d H:i:s'),
    ]);

    $govde = [
        'merchant_id'     => $b['merchant_id'],
        'name'            => $ad,
        'price'           => $fiyat,
        'currency'        => $para,
        'max_installment' => (string) $taksit,
        'link_type'       => $tur,
        'lang'            => $dil,
        'min_count'       => $adet,
        'max_count'       => '1',
        'expiry_date'     => $sonKullanim,
        'callback_link'   => $bildirim,
        'callback_id'     => $cid,
        'debug_on'        => $test ? '1' : '0',
        'paytr_token'     => paytr_token($ad . $fiyat . $para . $taksit . $tur . $dil . $adet, $b['key'], $b['salt']),
    ];
    $r = dis_istek('POST', PAYTR_CREATE_URL, ['Content-Type' => 'application/x-www-form-urlencoded'], $govde);
    $j = json_decode($r['govde'], true);
    if ($r['durum'] === 200 && is_array($j) && ($j['status'] ?? '') === 'success' && !empty($j['link'])) {
        $link = (string) $j['link'];
        if (!str_starts_with($link, 'https://')) {
            $link = '';
        }
        update('odeme_linkleri', [
            'durum'  => $link !== '' ? 'olusturuldu' : 'hata',
            'dis_id' => mb_substr((string) ($j['id'] ?? ''), 0, 40) ?: null,
            'link'   => $link ?: null,
            'hata'   => $link !== '' ? null : 'PayTR geçersiz link döndürdü',
        ], 'id = ?', [$linkId]);
        audit('odeme_linki', 'order', (int) $siparis['id'], ['tutar' => $tutar, 'test' => $test ? 'evet' : 'hayır']);
        if ($link === '') {
            throw new DomainException('PayTR geçersiz bir link döndürdü.');
        }
        return row('SELECT * FROM odeme_linkleri WHERE id = ?', [$linkId]);
    }
    $hata = $r['durum'] === 0
        ? 'PayTR\'ye bağlanılamadı: ' . $r['hata']
        : 'PayTR: ' . (string) ($j['reason'] ?? $j['err_msg'] ?? ('HTTP ' . $r['durum']));
    update('odeme_linkleri', ['durum' => 'hata', 'hata' => mb_substr($hata, 0, 255)], 'id = ?', [$linkId]);
    throw new DomainException($hata);
}

/** Linki PayTR'de siler ve iptal eder. Ödenmişse dokunmaz. */
function odeme_linki_iptal(int $linkId): void
{
    $l = row('SELECT * FROM odeme_linkleri WHERE id = ?', [$linkId]);
    if (!$l || $l['durum'] === 'odendi') {
        throw new DomainException('Link bulunamadı ya da ödenmiş.');
    }
    if ($l['dis_id'] && paytr_hazir_mi()) {
        $b = paytr_bilgileri();
        $r = dis_istek('POST', PAYTR_DELETE_URL, ['Content-Type' => 'application/x-www-form-urlencoded'], [
            'merchant_id' => $b['merchant_id'],
            'id'          => (string) $l['dis_id'],
            'debug_on'    => (int) $l['test'] === 1 ? '1' : '0',
            'paytr_token' => paytr_token((string) $l['dis_id'] . $b['merchant_id'], $b['key'], $b['salt']),
        ]);
        $j = json_decode($r['govde'], true);
        if (!is_array($j) || ($j['status'] ?? '') !== 'success') {
            // PayTR'de silinemese de (ör. süresi dolmuş) OptiFlow'da iptal edilir; hata kayda geçer.
            app_log('paytr delete: ' . mb_substr((string) ($j['reason'] ?? $j['err_msg'] ?? $r['hata']), 0, 120));
        }
    }
    q("UPDATE odeme_linkleri SET durum = 'iptal' WHERE id = ? AND durum <> 'odendi'", [$linkId]);
    audit('odeme_linki_iptal', 'order', (int) $l['order_id'], ['tutar' => (float) $l['tutar']]);
}

function odeme_suresi_dolanlari_kapat(): int
{
    return q("UPDATE odeme_linkleri SET durum = 'suresi_doldu' WHERE durum IN ('olusturuldu','hazirlaniyor') AND son_kullanim IS NOT NULL AND son_kullanim < NOW()")->rowCount();
}

/**
 * PayTR bildirimini işler (mağaza veritabanı SEÇİLMİŞ olmalı). Dönüş: PayTR'ye yazılacak yanıt.
 * İmza geçersizse 'IMZA' döner (PayTR tekrar dener; saldırgan kayıt oluşturamaz).
 */
function odeme_bildirim_isle(array $p): string
{
    $cid = (string) ($p['callback_id'] ?? '');
    $l = row('SELECT * FROM odeme_linkleri WHERE callback_id = ?', [$cid]);
    if (!$l) {
        return 'OK';   // bilinmeyen link: PayTR'nin sonsuza dek tekrar denemesini önle
    }
    $b = paytr_bilgileri();
    if ($b['key'] === '' || $b['salt'] === '') {
        return 'ANAHTAR';
    }
    if (!hash_equals(paytr_bildirim_imzasi($p, $b['key'], $b['salt']), (string) ($p['hash'] ?? ''))) {
        app_log('paytr bildirim: imza geçersiz (link ' . (int) $l['id'] . ')');
        return 'IMZA';
    }
    $oid = mb_substr((string) ($p['merchant_oid'] ?? ''), 0, 64);
    if (($p['status'] ?? '') !== 'success') {
        q("UPDATE odeme_linkleri SET hata = ? WHERE id = ? AND durum <> 'odendi'", ['Ödeme başarısız: ' . mb_substr((string) ($p['failed_reason_msg'] ?? $p['status'] ?? ''), 0, 200), (int) $l['id']]);
        return 'OK';
    }
    return transaction(static function () use ($l, $oid, $p): string {
        $guncel = row('SELECT * FROM odeme_linkleri WHERE id = ? FOR UPDATE', [(int) $l['id']]);
        if (!$guncel || $guncel['durum'] === 'odendi') {
            return 'OK';   // aynı bildirim ikinci kez: zaten işlendi
        }
        if ($oid !== '' && scalar('SELECT id FROM odeme_linkleri WHERE merchant_oid = ? AND id <> ?', [$oid, (int) $l['id']])) {
            return 'OK';
        }
        $odenen = round(((int) ($p['total_amount'] ?? 0)) / 100, 2);
        // TEST modu: gerçek para çekilmedi → siparişin bakiyesine tahsilat YAZILMAZ; yalnızca link işaretlenir.
        if ((int) $guncel['test'] === 1 || (string) ($p['test_mode'] ?? '0') === '1') {
            update('odeme_linkleri', [
                'durum'        => 'odendi',
                'odenen'       => $odenen ?: null,
                'merchant_oid' => $oid ?: null,
                'odeme_at'     => date('Y-m-d H:i:s'),
                'hata'         => 'Test ödemesi — tahsilat yazılmadı',
            ], 'id = ?', [(int) $guncel['id']]);
            audit('odeme_linki_odendi', 'order', (int) $guncel['order_id'], ['tutar' => (float) $guncel['tutar'], 'test' => 'evet']);
            return 'OK';
        }
        // Siparişe yazılan tutar, linkin kendi tutarıdır (taksit vade farkı mağazanın bakiyesine eklenmez).
        $pid = insert('payments', [
            'order_id'   => (int) $guncel['order_id'],
            'amount'     => (float) $guncel['tutar'],
            'method'     => 'kart',
            'note'       => 'PayTR ödeme linki',
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => null,
        ]);
        update('odeme_linkleri', [
            'durum'        => 'odendi',
            'odenen'       => $odenen ?: null,
            'merchant_oid' => $oid ?: null,
            'payment_id'   => $pid,
            'odeme_at'     => date('Y-m-d H:i:s'),
            'hata'         => null,
        ], 'id = ?', [(int) $guncel['id']]);
        audit('odeme_linki_odendi', 'order', (int) $guncel['order_id'], ['tutar' => (float) $guncel['tutar'], 'ödeme' => $pid]);
        return 'OK';
    });
}

function odeme_durum_etiketi(string $d): array
{
    return match ($d) {
        'olusturuldu'  => ['Bekliyor', 'amber'],
        'odendi'       => ['Ödendi', 'green'],
        'iptal'        => ['İptal', 'gray'],
        'suresi_doldu' => ['Süresi doldu', 'gray'],
        'hata'         => ['Hata', 'red'],
        default        => [$d, 'gray'],
    };
}
