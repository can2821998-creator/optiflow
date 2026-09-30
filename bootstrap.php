<?php
// v28 eski çekirdeğinin yerine konmuştur. Yeni çekirdek: app/bootstrap.php
if (PHP_SAPI !== 'cli') { header('Location: index.php', true, 302); }
exit;
