<?php
// Sürüm kapısı: eski PHP'de boş 500 yerine açıklayıcı uyarı gösterir (bu dosya PHP 5 ile de okunabilir).
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    require dirname(__FILE__) . '/app/php-surum.php';
    exit;
}
require dirname(__FILE__) . '/app/pages/uts.php';
