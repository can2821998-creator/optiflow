<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

if (current_user()) {
    redirect('index.php');
}

$error = '';
$username = '';
$hatirlaAcik = hatirla_acik();
$hatirla = $hatirlaAcik && is_optiflow_desktop();   // masaüstü: varsayılan işaretli
if (is_post()) {
    $username = post('username');
    $hatirla = $hatirlaAcik && isset($_POST['hatirla']);
    $result = attempt_login($username, (string) ($_POST['password'] ?? ''));
    if (is_array($result)) {
        if ($hatirla) {
            kullanici_hatirla_ver($result);
        }
        audit('login', 'user', (int) $result['id'], $hatirla ? ['beni hatırla' => 'evet'] : []);
        flash('Hoş geldiniz, ' . $result['full_name'] . '.');
        $r = query('r');
        redirect(preg_match('/^[a-z\-]+\.php(\?[\w=&%\-.]*)?$/i', $r) ? $r : 'index.php');
    }
    $error = $result;
}
$shop = setting('shop_name', 'OptiFlow');

/**
 * Giriş ekranı natürmortu: masada duran bir çift gözlük; camlardan geçen ışık
 * mor ve amber lekeler düşürüyor. Tamamı SVG — dış dosya veya istek yok.
 */
function login_art(): string
{
    $dots = '';
    for ($x = 30; $x < 1000; $x += 46) {
        for ($y = 30; $y < 700; $y += 46) {
            $dots .= '<circle cx="' . $x . '" cy="' . $y . '" r="1.1"/>';
        }
    }

    return <<<SVG
<svg class="art" viewBox="0 0 1000 1000" preserveAspectRatio="xMidYMid slice" fill="none" aria-hidden="true">
  <defs>
    <linearGradient id="pa-beam-v" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffd6dd" stop-opacity=".00"/>
      <stop offset=".42" stop-color="#ff8fa0" stop-opacity=".20"/>
      <stop offset="1" stop-color="#8f1a2e" stop-opacity=".02"/>
    </linearGradient>
    <linearGradient id="pa-beam-a" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#ffc2cc" stop-opacity=".00"/>
      <stop offset=".45" stop-color="#ff6b81" stop-opacity=".16"/>
      <stop offset="1" stop-color="#b4233c" stop-opacity=".02"/>
    </linearGradient>
    <linearGradient id="pa-frame" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="#ff9aaa"/>
      <stop offset=".5" stop-color="#ff6b81"/>
      <stop offset="1" stop-color="#ffd6dd"/>
    </linearGradient>
    <radialGradient id="pa-glass" cx=".34" cy=".28" r=".85">
      <stop offset="0" stop-color="#ffffff" stop-opacity=".18"/>
      <stop offset=".55" stop-color="#ffd6dd" stop-opacity=".07"/>
      <stop offset="1" stop-color="#ffffff" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="pa-spot-v">
      <stop offset="0" stop-color="#ffb3bf" stop-opacity=".85"/>
      <stop offset="1" stop-color="#8f1a2e" stop-opacity="0"/>
    </radialGradient>
    <radialGradient id="pa-spot-a">
      <stop offset="0" stop-color="#ff8fa0" stop-opacity=".9"/>
      <stop offset="1" stop-color="#b4233c" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="pa-floor" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#8f1a2e" stop-opacity=".16"/>
      <stop offset=".55" stop-color="#5a1424" stop-opacity=".07"/>
      <stop offset="1" stop-color="#8f1a2e" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="pa-horizon" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0" stop-color="#ffc9d2" stop-opacity="0"/>
      <stop offset=".5" stop-color="#ffc9d2" stop-opacity=".34"/>
      <stop offset="1" stop-color="#ffc9d2" stop-opacity="0"/>
    </linearGradient>
    <linearGradient id="pa-vignette" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0" stop-color="#141012" stop-opacity="0"/>
      <stop offset=".55" stop-color="#141012" stop-opacity=".45"/>
      <stop offset="1" stop-color="#141012" stop-opacity=".62"/>
    </linearGradient>
    <filter id="pa-soft" x="-40%" y="-60%" width="180%" height="220%">
      <feGaussianBlur stdDeviation="22"/>
    </filter>
    <filter id="pa-soft-sm" x="-40%" y="-60%" width="180%" height="220%">
      <feGaussianBlur stdDeviation="9"/>
    </filter>
  </defs>

  <!-- ince nokta ızgarası -->
  <g fill="#ffc9d2" opacity=".10">{$dots}</g>

  <!-- masa düzlemi -->
  <rect x="0" y="702" width="1000" height="298" fill="url(#pa-floor)"/>
  <path d="M0 702H1000" stroke="url(#pa-horizon)" stroke-width="1.4"/>

  <!-- camlardan süzülen ışık huzmeleri -->
  <g class="pa-beams" filter="url(#pa-soft-sm)">
    <path class="pa-beam" d="M120-60h190L455 742H250Z" fill="url(#pa-beam-v)"/>
    <path class="pa-beam pa-beam-2" d="M430-60h200L780 742H560Z" fill="url(#pa-beam-a)"/>
  </g>

  <!-- masaya düşen renkli lekeler -->
  <g filter="url(#pa-soft)" class="pa-spots">
    <ellipse class="pa-spot" cx="336" cy="748" rx="104" ry="25" fill="url(#pa-spot-v)" opacity=".8"/>
    <ellipse class="pa-spot pa-spot-2" cx="660" cy="758" rx="96" ry="23" fill="url(#pa-spot-a)" opacity=".8"/>
  </g>

  <!-- gölge -->
  <ellipse cx="500" cy="704" rx="322" ry="24" fill="#141012" opacity=".55" filter="url(#pa-soft-sm)"/>

  <!-- gözlük -->
  <g stroke="url(#pa-frame)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="350" cy="520" r="140" fill="url(#pa-glass)"/>
    <circle cx="650" cy="520" r="140" fill="url(#pa-glass)"/>
    <path d="M489 494q11-31 22 0"/>
    <path d="M210 500l-88-38q-34-14-52 17l-38 63" stroke-width="6"/>
    <path d="M790 500l88-38q34-14 52 17l38 63" stroke-width="6"/>
    <path d="M204 482v36M796 482v36" stroke-width="9" stroke-opacity=".9"/>
  </g>

  <!-- cam parlamaları -->
  <g stroke="#ffffff" fill="none" stroke-linecap="round">
    <path d="M258 466a140 140 0 0 1 64-72" stroke-width="7" stroke-opacity=".30"/>
    <path d="M247 524a140 140 0 0 1 10-42" stroke-width="5" stroke-opacity=".16"/>
    <path d="M558 466a140 140 0 0 1 64-72" stroke-width="7" stroke-opacity=".22"/>
  </g>

  <!-- camların masadaki soluk yansıması -->
  <g filter="url(#pa-soft-sm)" opacity=".28">
    <ellipse cx="350" cy="742" rx="132" ry="30" fill="url(#pa-glass)"/>
    <ellipse cx="650" cy="746" rx="132" ry="28" fill="url(#pa-glass)"/>
  </g>

  <!-- alt vinyet: tipografi için koyu taban -->
  <rect x="0" y="560" width="1000" height="440" fill="url(#pa-vignette)"/>

  <!-- ışıltılar -->
  <g fill="#ffc2cc" class="pa-sparks">
    <path class="pa-spark" d="M806 250c3 22 8 27 30 30-22 3-27 8-30 30-3-22-8-27-30-30 22-3 27-8 30-30Z" opacity=".75"/>
    <path class="pa-spark pa-spark-2" d="M168 300c2 15 5 18 20 20-15 2-18 5-20 20-2-15-5-18-20-20 15-2 18-5 20-20Z" opacity=".5"/>
    <path class="pa-spark pa-spark-3" d="M880 630c2 13 4 15 17 17-13 2-15 4-17 17-2-13-4-15-17-17 13-2 15-4 17-17Z" opacity=".45"/>
  </g>
</svg>
SVG;
}

?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#141012">
<title>Giriş · <?= e($shop) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="assets/fonts/manrope-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="manifest" href="manifest.php">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($shop) ?>">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<?= ga_head() ?>
</head>
<body class="auth-page">
<div class="auth-split">
  <section class="auth-art" aria-hidden="true">
    <?= login_art() ?>
    <?php
      $shopWords = preg_split('/\s+/u', trim($shop)) ?: [$shop];
      $shopLast = count($shopWords) > 1 ? (string) array_pop($shopWords) : '';
      $shopFirst = implode(' ', $shopWords);
    ?>
    <div class="art-plate">
      <?= brand_mark() ?>
      <h2 class="art-title"><?= e($shopFirst) ?><?= $shopLast !== '' ? ' <em>' . e($shopLast) . '</em>' : '' ?></h2>
      <span class="art-rule"></span>
      <p class="art-sub">Reçeteden teslimata · tek masada</p>
    </div>
  </section>
  <main class="auth-form">
    <div class="auth-card">
      <span class="kicker"><?= e(greeting()) ?></span>
      <h1>OptiFlow'a <em>hoş geldiniz</em></h1>
      <p class="muted"><?= e(date_long()) ?></p>
      <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>"><?= e($msg) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
      <form method="post" class="stack" autocomplete="on">
        <?= csrf_field() ?>
        <label class="field"><span>Kullanıcı adı</span>
          <input name="username" value="<?= e($username) ?>" autocomplete="username" autocapitalize="none" required autofocus>
        </label>
        <label class="field"><span>Parola</span>
          <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <?php if ($hatirlaAcik): ?>
        <label class="check-line remember-line"><input type="checkbox" name="hatirla" value="1" <?= $hatirla ? 'checked' : '' ?>>
          <span>Beni hatırla <small class="muted">· bu bilgisayarda 30 gün parola sorulmaz</small></span></label>
        <?php endif; ?>
        <button class="btn btn-primary btn-block btn-lg">Giriş yap</button>
      </form>
      <form method="post" action="magaza-giris.php" class="foot">
        <?= csrf_field() ?><input type="hidden" name="action" value="farkli">
        <small><?= e($shop) ?> · <button type="submit" class="link-btn">Farklı mağaza</button></small>
      </form>
    </div>
  </main>
</div>
<script src="<?= e(asset('pwa.js')) ?>" defer></script>
</body>
</html>
