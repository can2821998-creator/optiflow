<?php
declare(strict_types=1);

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_WINDOW_MINUTES = 15;
/** "Siparişim nerede?" sorguları da login_attempts'e bu işaretle yazılır (siparisim-nerede.php). */
const SIPARIS_SORGU_ANAHTAR = '__siparis_sorgu__';

/** Oturumdaki kullanıcı; her istekte veritabanından tazelenir (pasif/yetki değişikliği anında geçerli olur). */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $id = (int) ($_SESSION['user_id'] ?? 0);
    if ($id <= 0) {
        // 4.18.0 "Beni hatırla": bu cihaz hatırlanıyorsa oturum sessizce yeniden kurulur.
        if (isset($_COOKIE[HATIRLA_KULLANICI_CEREZ]) && ($u = kullanici_hatirla_dene())) {
            $id = (int) $u['id'];
        } else {
            return null;
        }
    }
    $idle = max(15, (int) setting('session_idle_minutes', '480')) * 60;
    if (isset($_SESSION['last_seen']) && time() - (int) $_SESSION['last_seen'] > $idle) {
        // Hatırlanan cihazda zaman aşımı yeniden girişe dönüşür (mağaza bağlamı korunur).
        $magaza = $_SESSION['magaza'] ?? null;
        logout_session();
        if (is_array($magaza)) {
            $_SESSION['magaza'] = $magaza;
        }
        if (isset($_COOKIE[HATIRLA_KULLANICI_CEREZ]) && ($u = kullanici_hatirla_dene())) {
            $id = (int) $u['id'];
        } else {
            unset($_SESSION['magaza']);
            flash('Uzun süre işlem yapılmadığı için oturum kapandı.', 'info');
            return null;
        }
    }
    // 4.20.1: personel oturumu girişin yapıldığı mağazaya bağlıdır. Oturumdaki mağaza değişirse (ya da müşteri
    // sayfası ?m= ile başka mağazanın veritabanını seçtiyse) aynı kullanıcı numarası başka mağazada geçmez.
    $magazaId = (int) (tenant_oturum()['id'] ?? 0);
    if (!isset($_SESSION['user_magaza'])) {
        $_SESSION['user_magaza'] = $magazaId;   // 4.20.1 öncesi açılmış oturumlar: bir kez bağlanır
    }
    if ((int) $_SESSION['user_magaza'] !== $magazaId || !empty($GLOBALS['__musteri_magaza'])) {
        return null;
    }
    $u = row('SELECT id, full_name, username, role, is_active, password_changed_at FROM user_accounts WHERE id = ?', [$id]);
    if (!$u || !(int) $u['is_active'] || ($_SESSION['pw_stamp'] ?? '') !== (string) $u['password_changed_at']) {
        logout_session();
        return null;
    }
    $_SESSION['last_seen'] = time();
    $user = $u;
    return $user;
}

function is_super(): bool
{
    return (current_user()['role'] ?? '') === 'super_yetkili';
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $back = basename((string) ($_SERVER['REQUEST_URI'] ?? ''));
        redirect('login.php' . ($back && !str_starts_with($back, 'login.php') && $back !== 'index.php' ? '?r=' . rawurlencode($back) : ''));
    }
    return $u;
}

function require_super(): array
{
    $u = require_login();
    if ($u['role'] !== 'super_yetkili') {
        http_response_code(403);
        render_error_page('Yetkiniz yok', 'Bu alan yalnızca süper yetkili kullanıcılar içindir.');
    }
    return $u;
}

function login_locked(string $username): bool
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    // Müşteri sipariş sorguları sayılmaz: mağazanın Wi-Fi'ındaki müşteri personel girişini kilitlemesin.
    $byIp = (int) scalar('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ? AND username <> ?', [client_ip(), $since, SIPARIS_SORGU_ANAHTAR]);
    $byUser = (int) scalar('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at > ?', [$username, $since]);
    return $byIp >= LOGIN_MAX_ATTEMPTS * 3 || $byUser >= LOGIN_MAX_ATTEMPTS;
}

/** Başarılıysa kullanıcıyı, değilse hata mesajını döner. */
function attempt_login(string $username, string $password): array|string
{
    $username = mb_substr(trim($username), 0, 60);
    if (login_locked($username)) {
        return 'Çok fazla hatalı deneme. ' . LOGIN_WINDOW_MINUTES . ' dakika sonra tekrar deneyin.';
    }
    $u = row('SELECT * FROM user_accounts WHERE username = ? LIMIT 1', [$username]);
    $ok = $u && password_verify($password, $u['password_hash']);
    if (!$ok || !(int) $u['is_active']) {
        insert('login_attempts', ['ip' => client_ip(), 'username' => $username]);
        if (!$ok) {
            return 'Kullanıcı adı veya parola hatalı.';
        }
        return 'Bu hesap pasif durumda. Yöneticinize başvurun.';
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE user_accounts SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    q('DELETE FROM login_attempts WHERE username = ? OR attempted_at < ?', [$username, date('Y-m-d H:i:s', time() - 86400)]);
    q('UPDATE user_accounts SET last_login_at = NOW() WHERE id = ?', [$u['id']]);

    oturum_kullanici_yaz($u);
    return $u;
}

/** Doğrulanmış kullanıcı için oturumu kurar (şifreyle giriş ve "beni hatırla" aynı yapıyı kullanır). */
function oturum_kullanici_yaz(array $u): void
{
    /* 4.10.0 DÜZELTME: Mağaza bağlamı (çok mağazalı yapı) kullanıcı girişinde korunur.
       Eskiden $_SESSION tamamen yeniden yazıldığı için 'magaza' anahtarı siliniyor, girişten
       hemen sonraki istek tanıtım sayfasına / mağaza girişine düşüyordu. Kullanıcı, bu istekte
       tenant_gereksin() ile seçilmiş OLAN mağazanın kendi veritabanında doğrulandı; aynı mağaza
       bağlamını taşımak başka mağazaya erişim vermez. */
    $magaza = $_SESSION['magaza'] ?? null;
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION = [
        'user_id'   => (int) $u['id'],
        'user_magaza' => (int) (is_array($magaza) ? ($magaza['id'] ?? 0) : 0),
        'pw_stamp'  => (string) $u['password_changed_at'],
        'last_seen' => time(),
        'csrf'      => bin2hex(random_bytes(32)),
    ];
    if (is_array($magaza)) {
        $_SESSION['magaza'] = $magaza;
    }
}

function logout_session(): void
{
    $_SESSION = [];
    if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function set_password(int $userId, string $password): void
{
    $stamp = date('Y-m-d H:i:s');
    q('UPDATE user_accounts SET password_hash = ?, password_changed_at = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $stamp, $userId]);
    if ((int) ($_SESSION['user_id'] ?? 0) === $userId) {
        $_SESSION['pw_stamp'] = $stamp; // kendi oturumu açık kalsın, diğer cihazlar kapanır
    }
    kullanici_hatirla_sifre_degisti($userId);   // 4.18.0: hatırlanan cihazlar da kapanır (bu cihaz hariç)
}

function password_problem(string $password): ?string
{
    if (mb_strlen($password) < 8) {
        return 'Parola en az 8 karakter olmalı.';
    }
    if (!preg_match('/\d/', $password) || !preg_match('/\pL/u', $password)) {
        return 'Parola en az bir harf ve bir rakam içermeli.';
    }
    return null;
}
