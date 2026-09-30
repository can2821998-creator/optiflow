<?php
declare(strict_types=1);
/*
 * mbstring eklentisi kapalı sunucular için basit yedekler (UTF-8).
 * Eklenti açıksa bu fonksiyonlar tanımlanmaz, gerçekleri kullanılır.
 */
if (!function_exists('mb_strlen')) {
    function mb_strlen(string $s, ?string $enc = null): int
    {
        return (int) preg_match_all('/./us', $s);
    }
    function mb_substr(string $s, int $start, ?int $length = null, ?string $enc = null): string
    {
        preg_match_all('/./us', $s, $m);
        return implode('', array_slice($m[0], $start, $length));
    }
    function mb_strtolower(string $s, ?string $enc = null): string
    {
        return strtolower(strtr($s, ['Ç' => 'ç', 'Ğ' => 'ğ', 'İ' => 'i', 'I' => 'ı', 'Ö' => 'ö', 'Ş' => 'ş', 'Ü' => 'ü', 'Â' => 'â', 'Î' => 'î', 'Û' => 'û']));
    }
    function mb_strtoupper(string $s, ?string $enc = null): string
    {
        return strtoupper(strtr($s, ['ç' => 'Ç', 'ğ' => 'Ğ', 'i' => 'İ', 'ı' => 'I', 'ö' => 'Ö', 'ş' => 'Ş', 'ü' => 'Ü', 'â' => 'Â', 'î' => 'Î', 'û' => 'Û']));
    }
    function mb_strimwidth(string $s, int $start, int $width, string $trim = '', ?string $enc = null): string
    {
        $s = mb_substr($s, $start);
        return mb_strlen($s) > $width ? mb_substr($s, 0, max(0, $width - mb_strlen($trim))) . $trim : $s;
    }
    function mb_internal_encoding(?string $enc = null): string|bool
    {
        return true;
    }
}
