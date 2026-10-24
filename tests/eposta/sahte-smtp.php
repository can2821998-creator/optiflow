<?php
/* Testler için tek bağlantılık sahte SMTP sunucusu. Kullanım: php sahte-smtp.php <port-dosyası> <kayıt-dosyası> [reddet-şifre] */
$srv = stream_socket_server('tcp://127.0.0.1:0', $no, $msj);
$ad = stream_socket_get_name($srv, false);
file_put_contents($argv[1], substr($ad, strrpos($ad, ':') + 1));
$c = @stream_socket_accept($srv, 20);
if (!$c) { exit(1); }
$kayit = '';
$yaz = function (string $s) use ($c) { fwrite($c, $s . "\r\n"); };
$yaz('220 sahte ESMTP');
$data = false;
$adim = '';
while (($l = fgets($c)) !== false) {
    $kayit .= $l;
    if ($data) {
        if (rtrim($l, "\r\n") === '.') { $data = false; $yaz('250 2.0.0 OK kuyrukta'); }
        continue;
    }
    $k = strtoupper(substr(trim($l), 0, 10));
    if ($adim === 'kullanici') { $adim = 'sifre'; $yaz('334 UGFzc3dvcmQ6'); continue; }
    if ($adim === 'sifre') {
        $adim = '';
        if (($argv[3] ?? '') === 'reddet') { $yaz('535 5.7.8 Username and Password not accepted'); break; }
        $yaz('235 2.7.0 Accepted'); continue;
    }
    if (str_starts_with($k, 'EHLO')) { fwrite($c, "250-sahte merhaba\r\n250-AUTH LOGIN\r\n250 8BITMIME\r\n"); }
    elseif (str_starts_with($k, 'AUTH LOGIN')) { $adim = 'kullanici'; $yaz('334 VXNlcm5hbWU6'); }
    elseif (str_starts_with($k, 'MAIL FROM') || str_starts_with($k, 'RCPT TO')) { $yaz('250 OK'); }
    elseif ($k === 'DATA') { $data = true; $yaz('354 devam'); }
    elseif ($k === 'QUIT') { $yaz('221 hoşça kal'); break; }
    else { $yaz('500 bilinmiyor'); }
}
file_put_contents($argv[2], $kayit);
