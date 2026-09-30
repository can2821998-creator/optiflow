<?php
header('HTTP/1.1 503 Service Unavailable');
header('Content-Type: text/html; charset=utf-8');
$v = PHP_VERSION;
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PHP sürümü yükseltilmeli</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#faf8f4;font:15px/1.6 system-ui,Arial,sans-serif;color:#1a1723;padding:16px}main{max-width:560px;background:#fff;border:1px solid #e7e1d8;border-radius:16px;padding:28px}h1{margin:0 0 8px;font-size:22px;color:#122f4d}code{background:#eef4f9;padding:2px 6px;border-radius:5px}ol{padding-left:20px}</style></head>
<body><main>
<h1>PHP sürümü yükseltilmeli</h1>
<p>Atölye sistemi v3 <b>PHP 8.0 veya üzeri</b> gerektirir. Bu sitede şu an <code>PHP <?php echo htmlspecialchars($v); ?></code> çalışıyor.</p>
<p><b>Verileriniz güvende:</b> sistem bu sürümde hiç çalışmadığı için veritabanında değişiklik yapılmadı.</p>
<ol>
<li>Plesk panelinde <b>Web Siteleri ve Alan Adları › alan adınız › PHP</b> (PHP Ayarları) bölümünü açın.</li>
<li><b>PHP sürümü</b> listesinden <b>8.2</b> veya <b>8.3</b>'ü seçin (işleyici: FPM veya FastCGI).</li>
<li>En alttaki <b>Tamam / Uygula</b>'ya basın, bu sayfayı yenileyin.</li>
</ol>
<p style="color:#6b6673;font-size:13px">Listede 8.x yoksa hosting firmanızdan PHP 8 etkinleştirmesini isteyin.</p>
</main></body></html>
