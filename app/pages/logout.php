<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

// Çıkış yalnızca POST + CSRF ile yapılır (bootstrap doğrular).
if (is_post() && current_user()) {
    audit('logout', 'user', (int) current_user()['id']);
    logout_session();
    flash('Oturum kapatıldı.', 'info');
}
redirect('login.php');
