<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   4.50.0 — Duyuru e-postalarından çıkma. Bağlantı HMAC imzalıdır (eposta_ret_imza); oturum ve CSRF yoktur.
   GET → onay sayfası (bağlantı tarayıcıları tek başına çıkarmasın diye); POST → uygula.
   RFC 8058 tek tıkla çıkma: e-posta istemcisi "List-Unsubscribe=One-Click" gövdesiyle POST eder.
   İşlem e-postaları (hesap hazır, deneme bitişi) bundan etkilenmez.
   ========================================================================== */

header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
$id = (int) ($_GET['m'] ?? $_POST['m'] ?? 0);
$imza = (string) ($_GET['t'] ?? $_POST['t'] ?? '');
$durum = 'onay';
$isim = null;
if (is_post()) {
    $geriAl = ($_POST['geri'] ?? '') === '1';
    $isim = eposta_ret_uygula($id, $imza, $geriAl);
    if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {
        http_response_code($isim === null ? 400 : 200);
        header('Content-Type: text/plain; charset=utf-8');
        echo $isim === null ? "gecersiz\n" : "tamam\n";
        exit;
    }
    $durum = $isim === null ? 'gecersiz' : ($geriAl ? 'geri' : 'cikti');
} else {
    $m = $id > 0 && hash_equals(eposta_ret_imza($id), $imza) ? merkez_row('SELECT isim FROM magazalar WHERE id = ?', [$id]) : null;
    $isim = $m['isim'] ?? null;
    $durum = $isim === null ? 'gecersiz' : 'onay';
}
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$form = static function (string $dugme, bool $geri) use ($id, $imza, $h): string {
    return '<form method="post"><input type="hidden" name="m" value="' . $id . '"><input type="hidden" name="t" value="' . $h($imza) . '">'
        . ($geri ? '<input type="hidden" name="geri" value="1">' : '') . '<button>' . $h($dugme) . '</button></form>';
};
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>E-posta tercihleri · OptiFlow</title>
<style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f3f3;font:16px/1.6 system-ui,Arial,sans-serif;color:#1b1416;padding:16px}
main{max-width:460px;background:#fff;border:1px solid #e8e1e2;border-radius:16px;padding:28px}h1{margin:0 0 10px;font-size:21px}p{margin:0 0 16px;color:#463b3e}
button{border:0;border-radius:999px;padding:12px 20px;font:700 15px system-ui;color:#fff;background:linear-gradient(135deg,#d0334f,#b4233c 55%,#7e1528);cursor:pointer}
.ince{background:#fff;color:#8f1a2e;border:1.5px solid #e8e1e2}</style></head><body><main>
<?php if ($durum === 'gecersiz'): ?>
  <h1>Bağlantı geçersiz</h1><p>Bu bağlantı eksik ya da değiştirilmiş görünüyor. E-postadaki bağlantıyı yeniden açın ya da bize yazın.</p>
<?php elseif ($durum === 'onay'): ?>
  <h1>Duyuru e-postalarından çıkmak</h1><p><b><?= $h((string) $isim) ?></b> için OptiFlow duyuru ve kampanya e-postaları artık gönderilmesin mi? Hesabınızla ilgili zorunlu bildirimler (ör. deneme süresinin bitişi) gelmeye devam eder.</p>
  <?= $form('Evet, duyuru e-postası istemiyorum', false) ?>
<?php elseif ($durum === 'cikti'): ?>
  <h1>Tamam, çıkarıldınız</h1><p><b><?= $h((string) $isim) ?></b> artık OptiFlow duyuru e-postası almayacak. Yanlışlıkla bastıysanız geri alabilirsiniz.</p>
  <?= str_replace('<button>', '<button class="ince">', $form('Geri al', true)) ?>
<?php else: ?>
  <h1>Yeniden eklendiniz</h1><p><b><?= $h((string) $isim) ?></b> OptiFlow duyuru e-postalarını yeniden alacak.</p>
<?php endif; ?>
</main></body></html>
