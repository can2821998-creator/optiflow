<?php
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    require dirname(__FILE__) . '/app/php-surum.php';
    exit;
}
require dirname(__FILE__) . '/app/pages/basarilar.php';
