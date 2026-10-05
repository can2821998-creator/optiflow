<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

// Çıkış yalnızca POST + CSRF ile yapılır (bootstrap doğrular).
if (is_post() && current_user()) {
    audit('logout', 'user', (int) current_user()['id']);
    kullanici_hatirla_unut();   // 4.18.0: bu cihaz artık hatırlanmaz (mağaza hatırlanmaya devam eder)
    logout_session();
    flash('Oturum kapatıldı.', 'info');
}
redirect('login.php');
