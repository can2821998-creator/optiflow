<?php
declare(strict_types=1);

/* ==========================================================================
   4.18.0 — "Beni hatırla": mağaza ve personel girişi için kalıcı oturum
   --------------------------------------------------------------------------
   İki ayrı, isteğe bağlı çerez (her biri ilgili giriş ekranındaki kutuyla):
     of_mh  mağaza girişi   → merkez veritabanı, magaza_hatirla
     of_kh  personel girişi → mağazanın veritabanı, oturum_hatirla (şema v30)

   Çerez değeri "seçici.doğrulayıcı" (24 + 64 onaltılık). Sunucuda yalnızca doğrulayıcının
   SHA-256 özeti tutulur; veritabanı sızsa da çerez üretilemez. Karşılaştırma hash_equals ile.
   Süre 30 gün; çerez her kullanıldığında (oturum yeniden kurulduğunda) 30 gün uzar.

   Geçersiz olur:
     • mağaza şifresi değişince (sifre_damga = sha256(sifre_hash) tutmaz),
     • personel şifresi değişince (pw_stamp tutmaz), kullanıcı pasifleşince,
     • "Çıkış"ta (yalnızca o cihazın personel çerezi), "Farklı mağaza"da (mağaza çerezi),
     • profildeki "Tüm cihazlarda unut" ile, mağaza ayarı "beni_hatirla" kapatılınca.
   Doğrulayıcısı tutmayan bir seçici (çalınmış/eski çerez) görülürse o kayıt silinir.

   Taşınabilir SQL (testler SQLite'ta çalışır): NOW()/INTERVAL yok, zaman PHP'de.
   ========================================================================== */

const HATIRLA_GUN = 30;
const HATIRLA_MAGAZA_CEREZ = 'of_mh';
const HATIRLA_KULLANICI_CEREZ = 'of_kh';

function hatirla_simdi(): string
{
    return date('Y-m-d H:i:s');
}

function hatirla_bitis(): int
{
    return time() + HATIRLA_GUN * 86400;
}

/** Mağaza ayarı: süper yetkili kapatabilir (varsayılan açık). */
function hatirla_acik(): bool
{
    try {
        return setting('beni_hatirla', '1') !== '0';
    } catch (Throwable $e) {
        return true;
    }
}

function hatirla_cerez_yaz(string $ad, string $deger, int $bitis): void
{
    if (PHP_SAPI === 'cli') {
        $GLOBALS['__cerez_yazilan'][$ad] = [$deger, $bitis];
        if ($deger === '') {
            unset($_COOKIE[$ad]);
        } else {
            $_COOKIE[$ad] = $deger;
        }
        return;
    }
    if (headers_sent()) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    setcookie($ad, $deger, [
        'expires'  => $bitis,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if ($deger === '') {
        unset($_COOKIE[$ad]);
    } else {
        $_COOKIE[$ad] = $deger;
    }
}

function hatirla_cerez_sil(string $ad): void
{
    if (isset($_COOKIE[$ad])) {
        hatirla_cerez_yaz($ad, '', time() - 3600);
    }
}

/** @return array{0:string,1:string}|null [seçici, doğrulayıcı] */
function hatirla_cerez_oku(string $ad): ?array
{
    $v = (string) ($_COOKIE[$ad] ?? '');
    if (!preg_match('/^([a-f0-9]{24})\.([a-f0-9]{64})$/', $v, $m)) {
        return null;
    }
    return [$m[1], $m[2]];
}

/** @return array{0:string,1:string,2:string} [seçici, doğrulayıcı, doğrulayıcı özeti] */
function hatirla_uret(): array
{
    $secici = bin2hex(random_bytes(12));
    $dogrulayici = bin2hex(random_bytes(32));
    return [$secici, $dogrulayici, hash('sha256', $dogrulayici)];
}

/** Kayıtta gösterilecek kısa cihaz adı (yalnızca kullanıcıya bilgi; güvenlik kararı DEĞİL). */
function hatirla_cihaz(): string
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (is_optiflow_desktop()) {
        return 'OptiFlow Pro (masaüstü)';
    }
    $tarayici = match (true) {
        str_contains($ua, 'Edg/') => 'Edge',
        str_contains($ua, 'OPR/') => 'Opera',
        str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Chrome/') => 'Chrome',
        str_contains($ua, 'Safari/') => 'Safari',
        default => 'Tarayıcı',
    };
    $sistem = match (true) {
        str_contains($ua, 'Windows') => 'Windows',
        str_contains($ua, 'Android') => 'Android',
        str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Mac OS') => 'macOS',
        str_contains($ua, 'Linux') => 'Linux',
        default => '',
    };
    return trim($tarayici . ($sistem !== '' ? ' · ' . $sistem : ''));
}

/* ------------------------------------------------------------------ */
/*  Mağaza girişi (merkez veritabanı)                                   */
/* ------------------------------------------------------------------ */

function magaza_sifre_damga(array $m): string
{
    return hash('sha256', (string) ($m['sifre_hash'] ?? ''));
}

/** Mağaza girişinden sonra "Bu bilgisayarda mağazayı hatırla" işaretliyse. */
function magaza_hatirla_ver(array $m): void
{
    magaza_hatirla_unut();   // bu cihazın eski kaydı
    [$secici, $dogrulayici, $ozet] = hatirla_uret();
    $bitis = hatirla_bitis();
    merkez_q(
        'INSERT INTO magaza_hatirla (magaza_id, secici, dogrulayici_hash, sifre_damga, cihaz, olusturma, bitis) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [(int) $m['id'], $secici, $ozet, magaza_sifre_damga($m), hatirla_cihaz(), hatirla_simdi(), date('Y-m-d H:i:s', $bitis)]
    );
    // Arada bir eskileri temizle
    merkez_q('DELETE FROM magaza_hatirla WHERE bitis < ?', [hatirla_simdi()]);
    hatirla_cerez_yaz(HATIRLA_MAGAZA_CEREZ, $secici . '.' . $dogrulayici, $bitis);
}

/**
 * Oturumda mağaza yokken çerezle mağaza oturumunu yeniden kurar. Başarılıysa mağaza satırını döner.
 * Mağaza kapalı/beklemede olsa da oturum kurulur; o durumda tenant_gereksin() açıklayıcı sayfayı gösterir.
 */
function magaza_hatirla_dene(): ?array
{
    $c = hatirla_cerez_oku(HATIRLA_MAGAZA_CEREZ);
    if (!$c) {
        hatirla_cerez_sil(HATIRLA_MAGAZA_CEREZ);
        return null;
    }
    [$secici, $dogrulayici] = $c;
    try {
            $k = merkez_row('SELECT * FROM magaza_hatirla WHERE secici = ?', [$secici]);
        if (!$k) {
            hatirla_cerez_sil(HATIRLA_MAGAZA_CEREZ);
            return null;
        }
        if (!hash_equals((string) $k['dogrulayici_hash'], hash('sha256', $dogrulayici))) {
            merkez_q('DELETE FROM magaza_hatirla WHERE id = ?', [$k['id']]);   // çalınmış/bozuk çerez
            hatirla_cerez_sil(HATIRLA_MAGAZA_CEREZ);
            return null;
        }
        $m = merkez_row('SELECT * FROM magazalar WHERE id = ?', [(int) $k['magaza_id']]);
        if (!$m || (string) $k['bitis'] < hatirla_simdi() || !hash_equals((string) $k['sifre_damga'], magaza_sifre_damga($m))) {
            merkez_q('DELETE FROM magaza_hatirla WHERE id = ?', [$k['id']]);
            hatirla_cerez_sil(HATIRLA_MAGAZA_CEREZ);
            return null;
        }
        $bitis = hatirla_bitis();
        merkez_q('UPDATE magaza_hatirla SET son_kullanim = ?, bitis = ? WHERE id = ?', [hatirla_simdi(), date('Y-m-d H:i:s', $bitis), $k['id']]);
        hatirla_cerez_yaz(HATIRLA_MAGAZA_CEREZ, $secici . '.' . $dogrulayici, $bitis);
    } catch (Throwable $e) {
        return null;   // merkez veritabanı geçici olarak yoksa normal girişe düşülür
    }
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    tenant_oturum_ac($m);
    $_SESSION['hatirla_magaza'] = 1;
    return $m;
}

/** Bu cihazın mağaza çerezini (ve kaydını) siler: "Farklı mağaza". */
function magaza_hatirla_unut(): void
{
    $c = hatirla_cerez_oku(HATIRLA_MAGAZA_CEREZ);
    if ($c) {
        try {
                    merkez_q('DELETE FROM magaza_hatirla WHERE secici = ?', [$c[0]]);
        } catch (Throwable $e) {
            // kayıt silinemese de çerez gider
        }
    }
    hatirla_cerez_sil(HATIRLA_MAGAZA_CEREZ);
}

/** Mağazanın hatırlanan tüm cihazları (merkez panel / şifre sıfırlama). */
function magaza_hatirla_hepsini_unut(int $magazaId): int
{
    try {
            $n = (int) merkez_scalar('SELECT COUNT(*) FROM magaza_hatirla WHERE magaza_id = ?', [$magazaId]);
        merkez_q('DELETE FROM magaza_hatirla WHERE magaza_id = ?', [$magazaId]);
        return $n;
    } catch (Throwable $e) {
        return 0;
    }
}

/* ------------------------------------------------------------------ */
/*  Personel girişi (mağazanın veritabanı)                              */
/* ------------------------------------------------------------------ */

function kullanici_hatirla_ver(array $u): void
{
    if (!hatirla_acik() || !table_exists_safe('oturum_hatirla')) {
        return;
    }
    kullanici_hatirla_unut();
    [$secici, $dogrulayici, $ozet] = hatirla_uret();
    $bitis = hatirla_bitis();
    insert('oturum_hatirla', [
        'user_id'          => (int) $u['id'],
        'magaza_id'        => (int) (tenant_oturum()['id'] ?? 0),
        'secici'           => $secici,
        'dogrulayici_hash' => $ozet,
        'pw_stamp'         => (string) ($u['password_changed_at'] ?? ''),
        'cihaz'            => hatirla_cihaz(),
        'created_at'       => hatirla_simdi(),
        'expires_at'       => date('Y-m-d H:i:s', $bitis),
    ]);
    q('DELETE FROM oturum_hatirla WHERE expires_at < ?', [hatirla_simdi()]);
    hatirla_cerez_yaz(HATIRLA_KULLANICI_CEREZ, $secici . '.' . $dogrulayici, $bitis);
}

/** Tablo yoksa (göç henüz çalışmamış / eski şema) sessizce false. */
function table_exists_safe(string $t): bool
{
    try {
        q('SELECT 1 FROM ' . $t . ' WHERE 1 = 0');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Oturumda kullanıcı yokken çerezle personel oturumunu yeniden kurar (attempt_login ile aynı oturum yapısı).
 * Mağaza oturumu (tenant) ZORUNLU: çerez yalnızca kendi mağazasında geçer.
 */
function kullanici_hatirla_dene(): ?array
{
    $c = hatirla_cerez_oku(HATIRLA_KULLANICI_CEREZ);
    $magaza = tenant_oturum();
    if (!$c || !$magaza) {
        if (!$c) {
            hatirla_cerez_sil(HATIRLA_KULLANICI_CEREZ);
        }
        return null;
    }
    [$secici, $dogrulayici] = $c;
    try {
        if (!hatirla_acik() || !table_exists_safe('oturum_hatirla')) {
            return null;
        }
        $k = row('SELECT * FROM oturum_hatirla WHERE secici = ?', [$secici]);
        if (!$k) {
            hatirla_cerez_sil(HATIRLA_KULLANICI_CEREZ);
            return null;
        }
        if (!hash_equals((string) $k['dogrulayici_hash'], hash('sha256', $dogrulayici))) {
            q('DELETE FROM oturum_hatirla WHERE id = ?', [$k['id']]);
            hatirla_cerez_sil(HATIRLA_KULLANICI_CEREZ);
            return null;
        }
        $u = row('SELECT * FROM user_accounts WHERE id = ?', [(int) $k['user_id']]);
        $gecerli = $u
            && (int) $u['is_active']
            && (int) $k['magaza_id'] === (int) $magaza['id']
            && (string) $k['expires_at'] >= hatirla_simdi()
            && hash_equals((string) $k['pw_stamp'], (string) ($u['password_changed_at'] ?? ''));
        if (!$gecerli) {
            q('DELETE FROM oturum_hatirla WHERE id = ?', [$k['id']]);
            hatirla_cerez_sil(HATIRLA_KULLANICI_CEREZ);
            return null;
        }
        $bitis = hatirla_bitis();
        q('UPDATE oturum_hatirla SET last_used_at = ?, expires_at = ? WHERE id = ?', [hatirla_simdi(), date('Y-m-d H:i:s', $bitis), $k['id']]);
        q('UPDATE user_accounts SET last_login_at = ? WHERE id = ?', [hatirla_simdi(), $u['id']]);
        hatirla_cerez_yaz(HATIRLA_KULLANICI_CEREZ, $secici . '.' . $dogrulayici, $bitis);
    } catch (Throwable $e) {
        return null;
    }
    oturum_kullanici_yaz($u);
    $_SESSION['hatirla'] = 1;
    return $u;
}

/** Bu cihazın personel çerezini siler ("Çıkış"). */
function kullanici_hatirla_unut(): void
{
    $c = hatirla_cerez_oku(HATIRLA_KULLANICI_CEREZ);
    if ($c) {
        try {
            q('DELETE FROM oturum_hatirla WHERE secici = ?', [$c[0]]);
        } catch (Throwable $e) {
            // tablo yoksa da çerez gider
        }
    }
    hatirla_cerez_sil(HATIRLA_KULLANICI_CEREZ);
}

/** Kullanıcının hatırlanan tüm cihazları. */
function kullanici_hatirla_hepsini_unut(int $userId): int
{
    try {
        $n = (int) scalar('SELECT COUNT(*) FROM oturum_hatirla WHERE user_id = ?', [$userId]);
        q('DELETE FROM oturum_hatirla WHERE user_id = ?', [$userId]);
        return $n;
    } catch (Throwable $e) {
        return 0;
    }
}

/** Profil ekranı için: kullanıcının hatırlanan cihazları (en yeni önce). */
function kullanici_hatirla_cihazlar(int $userId): array
{
    try {
        return rows('SELECT id, cihaz, created_at, last_used_at, expires_at, secici FROM oturum_hatirla WHERE user_id = ? AND expires_at >= ? ORDER BY COALESCE(last_used_at, created_at) DESC', [$userId, hatirla_simdi()]);
    } catch (Throwable $e) {
        return [];
    }
}

/** Bu cihaz mı? (profilde "bu cihaz" etiketi) */
function hatirla_bu_cihaz_mi(string $secici): bool
{
    $c = hatirla_cerez_oku(HATIRLA_KULLANICI_CEREZ);
    return $c !== null && hash_equals($c[0], $secici);
}

/**
 * Şifre değişti: tüm cihazlar unutulur. Kendi şifresini değiştiren kullanıcının BU cihazı hatırlıyorsa
 * yeni şifre damgasıyla yeniden verilir (aksi halde bu cihazda da hemen tekrar şifre sorulurdu).
 */
function kullanici_hatirla_sifre_degisti(int $userId): void
{
    $buCihaz = hatirla_cerez_oku(HATIRLA_KULLANICI_CEREZ) !== null
        && (int) ($_SESSION['user_id'] ?? 0) === $userId;
    $vardi = false;
    if ($buCihaz) {
        try {
            $vardi = (bool) row('SELECT id FROM oturum_hatirla WHERE secici = ? AND user_id = ?', [hatirla_cerez_oku(HATIRLA_KULLANICI_CEREZ)[0], $userId]);
        } catch (Throwable $e) {
            $vardi = false;
        }
    }
    kullanici_hatirla_hepsini_unut($userId);
    if ($buCihaz && $vardi) {
        $u = row('SELECT * FROM user_accounts WHERE id = ?', [$userId]);
        if ($u) {
            kullanici_hatirla_ver($u);
        }
    } elseif ($buCihaz) {
        hatirla_cerez_sil(HATIRLA_KULLANICI_CEREZ);
    }
}
