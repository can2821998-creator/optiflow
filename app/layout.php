<?php
declare(strict_types=1);

function icon(string $name, string $class = 'ic'): string
{
    $paths = [
        'orders'   => '<path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1Z"/><path d="M8 5H6a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><path d="M8 12h8M8 16h5"/>',
        'users'    => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6.5 6.5 0 0 1 3.5 6"/>',
        'barcode'  => '<path d="M4 6v12M7 6v12M10 6v12M14 6v12M17 6v12M20 6v12" stroke-width="1.6"/><path d="M3 4h3M18 4h3M3 20h3M18 20h3"/>',
        'receipt'  => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
        'card'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
        'box'      => '<path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5z"/><path d="m3.5 7.5 8.5 4.5 8.5-4.5M12 12v9"/>',
        'chart'    => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'history'  => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
        'plus'     => '<path d="M12 5v14M5 12h14"/>',
        'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'logout'   => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'print'    => '<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v7H6z"/>',
        'chat'     => '<path d="M21 12a8.5 8.5 0 0 1-12.6 7.4L3 21l1.6-5.2A8.5 8.5 0 1 1 21 12Z"/>',
        'eye'      => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
        'edit'     => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'trash'    => '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
        'check'    => '<path d="m5 12 5 5L20 7"/>',
        'glasses'  => '<circle cx="6.5" cy="14.5" r="3.5"/><circle cx="17.5" cy="14.5" r="3.5"/><path d="M10 14.5h4M3 14.5 5 6h2M21 14.5 19 6h-2"/>',
        'spark'    => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/>',
        'wallet'   => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v11a2 2 0 0 0 2 2h15V9H5a2 2 0 0 1-2-2Z"/><circle cx="16" cy="14.5" r="1"/>',
        'x'        => '<path d="M18 6 6 18M6 6l12 12"/>',
        'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'download' => '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>',
        'bell'     => '<path d="M18 9a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16S18 14 18 9Z"/><path d="M13.7 20a2 2 0 0 1-3.4 0"/>',
        'home'     => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9.7V20h13V9.7"/><path d="M9.8 20v-5.5h4.4V20"/>',
        'grid'     => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6"/>',
        'truck'    => '<path d="M2.5 7.5h11v9h-11z"/><path d="M13.5 11h4l3 3v2.5h-7z"/><circle cx="7" cy="18" r="1.8"/><circle cx="17" cy="18" r="1.8"/>',
        'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/** Bu mağazanın marka rengi (Ayarlar → Genel'den değiştirilebilir). Varsayılan, sistemin özgün bordo tonu. */
function brand_color(): string
{
    $v = setting('brand_color', '#7a0a16');
    return preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : '#7a0a16';
}

function brand_color_deep(): string
{
    $v = setting('brand_color_deep', '#530711');
    return preg_match('/^#[0-9a-f]{6}$/i', $v) ? $v : '#530711';
}

/** Müşteri sayfaları / fiş gibi ayrı stil dosyası kullanan sayfaların <head>'ine, o dosyanın <link>'inden HEMEN
    SONRA eklenir; CSS'teki --brand/--brand-deep (veya print.css'teki --marka/--marka-koyu) değişkenlerini bu
    mağazanın rengiyle geçersiz kılar. Ana atölye arayüzü (app.css) de aynı mekanizmayı kullanır. */
function brand_style_tag(): string
{
    $c = e(brand_color());
    $d = e(brand_color_deep());
    return "<style>:root{--brand:$c;--brand-deep:$d;--marka:$c;--marka-koyu:$d}</style>";
}

/** Marka işareti: iki kesişen mercek ve ışık çizgisi (ince altın çizgi). */
function brand_mark(): string
{
    return '<span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">'
        . '<defs><linearGradient id="logoGrad" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse">'
        . '<stop offset="0" stop-color="#3346ff"/><stop offset=".5" stop-color="#c026d3"/><stop offset="1" stop-color="#ff4433"/>'
        . '</linearGradient></defs>'
        . '<g transform="rotate(-10 50 50)"><path fill-rule="evenodd" clip-rule="evenodd" '
        . 'd="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z" '
        . 'fill="url(#logoGrad)"/></g>'
        . '</svg></span>';
}

/** Boş durum çizimi: tek çizgiyle gözlük ve parıltı. */
function empty_art(): string
{
    return '<svg class="empty-art" viewBox="0 0 160 90" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M8 40c10-3 20-3 30 0" stroke-width="1" opacity=".4"/>'
        . '<circle cx="52" cy="52" r="22" stroke-width="1.4"/><circle cx="108" cy="52" r="22" stroke-width="1.4"/>'
        . '<path d="M74 48c4-4 8-4 12 0" stroke-width="1.4"/><path d="M30 46 18 34M130 46l12-12" stroke-width="1.4"/>'
        . '<path d="M44 42a12 12 0 0 1 9-5" stroke-width="1" opacity=".6"/><path d="M100 42a12 12 0 0 1 9-5" stroke-width="1" opacity=".6"/>'
        . '<path d="M136 12v10M131 17h10" stroke-width="1"/><path d="M22 72v6M19 75h6" stroke-width="1" opacity=".6"/>'
        . '</svg>';
}

function date_long(?string $date = null): string
{
    $ts = $date ? strtotime($date) : time();
    $months = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    $days = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
    return (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts) . ', ' . $days[(int) date('w', $ts)];
}

function greeting(): string
{
    $h = (int) date('G');
    return $h < 6 ? 'İyi geceler' : ($h < 12 ? 'Günaydın' : ($h < 18 ? 'İyi günler' : 'İyi akşamlar'));
}

function asset(string $path): string
{
    $file = APP_ROOT . '/assets/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : APP_VERSION;
    return 'assets/' . $path . '?v=' . $v;
}

function page_start(string $title, string $active = '', array $opts = []): void
{
    $u = current_user();
    $missing = (int) scalar(
        "SELECT COUNT(*) FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id
         JOIN orders o ON o.id = r.order_id WHERE i.stock_status = 'stokta_yok' AND o.order_stage <> 'iptal'"
    );
    $atolyede = (int) scalar("SELECT COUNT(*) FROM orders WHERE order_stage = 'atolyede'");
    $pendingInvoices = (int) scalar("SELECT COUNT(*) FROM supplier_deliveries WHERE invoice_id IS NULL");
    $hatirlatma = function_exists('reminder_badge') ? reminder_badge() : 0;
    $gecikenBakiye = 0;
    if (can_see_amounts()) {
        try {
            $gecikenBakiye = (int) scalar(
                "SELECT COUNT(*) FROM orders o " . PAID_JOIN . "
                  WHERE o.order_stage <> 'iptal'
                    AND o.total_amount - COALESCE(p.paid, 0) > 0.009
                    AND o.balance_promise_date IS NOT NULL AND o.balance_promise_date < CURDATE()"
            );
        } catch (Throwable) {
            $gecikenBakiye = 0;
        }
    }
    $nav = [
        'Atölye' => [
            ['orders', 'index.php', 'Siparişler', 'orders', 0],
            ['quotes', 'quotes.php', 'Teklifler', 'spark', 0],
            ['workshop', 'workshop.php', 'Atölye panosu', 'glasses', $atolyede],
            ['customers', 'customers.php', 'Müşteriler', 'users', 0],
            ['basarilar', 'basarilar.php', 'Başarılar', 'spark', 0],
            ['hatirlatma', 'hatirlatma.php', 'Hatırlatmalar', 'bell', $hatirlatma],
            ['sgk', 'sgk-aktar.php', 'SGK reçete aktar', 'download', 0],
            ['stock', 'stock.php', 'Depo · Stok', 'box', $missing],
            ['uts', 'uts-karekod.php', 'ÜTS karekod', 'print', 0, 'uts'],
            ['cerceve', 'cerceve.php', 'Çerçeve stoğu', 'glasses', function_exists('frame_alert_count') ? frame_alert_count() : 0],
            ['bagis', 'bagis.php', 'Gözlük bağışı', 'glasses', 0],
            ['deliveries', 'deliveries.php', 'Fatura bekleyen teslimatlar', 'wallet', $pendingInvoices],
            ['kasa', 'kasa.php', 'Gün sonu kasa', 'wallet', 0],
        ],
    ];
    if (can_see_amounts()) {
        // Bakiye takibi, "Gün sonu kasa"nın hemen üstünde dursun
        array_splice($nav['Atölye'], count($nav['Atölye']) - 1, 0, [
            ['tahsilat', 'tahsilat.php', 'Bakiye takibi', 'wallet', $gecikenBakiye],
        ]);
    }
    ozellik_menusu_ekle($nav);   // 4.12.0 merkezden açılan modüller
    if (is_super()) {
        $nav['Yönetim'] = [
            ['suppliers', 'suppliers.php', 'Tedarikçiler', 'wallet', 0],
            ['push', 'push-admin.php', 'Bildirim gönder', 'bell', 0],
            ['reports', 'reports.php', 'Raporlar', 'chart', 0],
            ['kar', 'kar.php', 'Kârlılık ve prim', 'chart', 0],
            ['yedek', 'yedek.php', 'Yedekleme', 'download', function_exists('backup_overdue') && backup_overdue() ? 1 : 0],
            ['logs', 'logs.php', 'İşlem geçmişi', 'history', 0],
            ['settings', 'settings.php', 'Ayarlar', 'settings', 0],
        ];
        if (function_exists('ozellik_acik') && ozellik_acik('tedarik_finans')) {   // 4.14.0
            array_splice($nav['Yönetim'], 1, 0, [
                ['alis-faturasi', 'alis-faturasi.php', 'Alış faturası yükle', 'download', 0],
                ['senetler', 'senetler.php', 'Senetler · ödeme takvimi', 'receipt', function_exists('senet_rozet') ? senet_rozet() : 0],
            ]);
        }
    }
    $shop = setting('shop_name', 'OptiFlow');
    if (function_exists('gorev_belki_calistir')) {
        gorev_belki_calistir();   // 4.12.0 WhatsApp kuyruğu / ödeme linkleri (yanıttan sonra)
    }
    if (function_exists('daily_summary_maybe_send')) {
        daily_summary_maybe_send();
    }
    /* Otomatik günlük yedek: yalnızca açıksa ve bugün denenmediyse dosya yüklenir; hata sayfayı etkilemez */
    try {
        if (setting('auto_backup', '0') === '1' && setting('auto_backup_last', '') !== date('Y-m-d')) {
            require_once __DIR__ . '/autobackup.php';
            autobackup_maybe_run();
        }
    } catch (Throwable $e) {
        // otomatik yedek başlatılamadı: sayfa açılışını engellemesin
    }
    bottom_nav($active !== '' ? $active : 'yok');
    ?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#0a2038">
<title><?= e($title) ?> · <?= e($shop) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="preload" href="assets/fonts/fraunces-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/fonts/manrope-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="manifest" href="manifest.php">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= e($shop) ?>">
<link rel="stylesheet" href="<?= e(asset('app.css')) ?>">
<?= brand_style_tag() ?>
</head>
<body class="<?= e($opts['body'] ?? '') ?>">
<?php if (config('ortam', '') === 'test'): ?>
<div role="note" style="position:sticky;top:0;z-index:9999;background:#b3261e;color:#fff;text-align:center;padding:7px 12px;font:700 13px/1.35 system-ui,Arial,sans-serif">TEST ORTAMI — gerçek müşteri kaydı girmeyin, buradaki bilgiler canlı sisteme geçmez</div>
<?php endif; ?>
<a class="skip" href="#main">İçeriğe geç</a>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="index.php">
      <?= brand_mark() ?>
      <span class="brand-text"><b><?= e($shop) ?></b><small>OptiFlow</small></span>
    </a>
    <a class="btn btn-block new-order" href="order-new.php"><?= icon('plus') ?> Yeni sipariş</a>
    <div class="global-search" data-global-search>
      <?= icon('search') ?>
      <input type="search" placeholder="Müşteri, sipariş no, tedarikçi ara…" autocomplete="off" data-global-search-input aria-label="Genel arama">
      <ul class="suggest" data-global-search-list hidden></ul>
    </div>
    <?php foreach ($nav as $group => $items): ?>
      <div class="nav-label"><?= e($group) ?></div>
      <nav class="nav" aria-label="<?= e($group) ?>">
        <?php foreach ($items as $item):
          [$key, $href, $label, $ic, $count] = $item;
          $proOz = $item[5] ?? null;                                   // 4.11.0 Pro özellik anahtarı
          $kilitli = $proOz !== null && !pro_ozellik_acik($proOz);
          if ($kilitli) { $href = 'pro.php?ozellik=' . $proOz; $ic = 'lock'; } ?>
          <a href="<?= e($href) ?>" class="<?= $active === $key ? 'active' : '' ?><?= $kilitli ? ' nav-kilitli' : '' ?>" <?= $active === $key ? 'aria-current="page"' : '' ?><?= $kilitli ? ' title="OptiFlow Pro özelliği"' : '' ?>>
            <?= icon($ic) ?><span><?= e($label) ?></span><?php if ($proOz !== null && !$kilitli): ?><em class="pro-rozet">PRO</em><?php endif; ?>
            <?php if ($count > 0): ?><em class="count" title="<?= $key === 'workshop' ? 'Atölyede bekleyen sipariş' : ($key === 'deliveries' ? 'Fatura bekleyen teslimat' : 'Eksik cam') ?>"><?= $count ?></em><?php endif; ?>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endforeach; ?>
    <div class="sidebar-user">
      <span class="avatar sm"><?= e(initials(...array_pad(explode(' ', (string) $u['full_name'], 2), 2, ''))) ?></span>
      <span class="who"><b><?= e($u['full_name']) ?></b><small><?php $title = staff_title(staff_xp((int) $u['id'])); ?><?= e($title['icon'] . ' ' . $title['name']) ?> · <?= e(roles()[$u['role']] ?? $u['role']) ?></small></span>
      <a class="icon-btn" href="profile.php" title="Profilim" aria-label="Profilim"><?= icon('user') ?></a>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="icon-btn" title="Çıkış yap" aria-label="Çıkış yap"><?= icon('logout') ?></button></form>
    </div>
  </aside>
  <div class="scrim" data-close-sidebar></div>
  <div class="main-col">
    <header class="topbar">
      <a class="brand compact" href="index.php"><?= brand_mark() ?><b><?= e($shop) ?></b></a>
      <a class="icon-btn" href="profile.php" aria-label="Profilim"><?= icon('user') ?></a>
    </header>
    <main id="main" class="content">
      <?php foreach (flashes() as [$type, $msg]): ?>
        <div class="alert alert-<?= e($type) ?>" role="status"><?= e($msg) ?><button type="button" class="alert-close" aria-label="Kapat" data-dismiss>×</button></div>
      <?php endforeach; ?>
<?php
}

/**
 * Telefonda alt gezinme çubuğu. Ortadaki yuvarlak düğme yeni sipariş açar,
 * son düğme kenar çubuğunu (tüm menü) getirir. Masaüstünde gizlidir.
 */
function bottom_nav(string $active = ''): string
{
    static $son = '';
    if ($active !== '') { $son = $active; $active = ''; }
    $aktif = $son;

    $ogeler = [
        ['orders',   'index.php',    'Siparişler', 'orders'],
        ['workshop', 'workshop.php', 'Atölye',     'glasses'],
        null, // orta: yeni sipariş
        ['customers','customers.php','Müşteriler', 'users'],
    ];

    $h = '<nav class="tabbar" aria-label="Alt gezinme">';
    foreach ($ogeler as $o) {
        if ($o === null) {
            $h .= '<a class="tabbar-fab" href="order-new.php" aria-label="Yeni sipariş">' . icon('plus') . '</a>';
            continue;
        }
        [$key, $href, $label, $ic] = $o;
        $cls = $aktif === $key ? ' class="is-on" aria-current="page"' : '';
        $h .= '<a href="' . e($href) . '"' . $cls . '>' . icon($ic) . '<span>' . e($label) . '</span></a>';
    }
    $h .= '<button type="button" data-toggle-sidebar aria-controls="sidebar">' . icon('grid') . '<span>Menü</span></button>';
    $h .= '</nav>';
    return $h;
}

function page_end(array $scripts = []): void
{
    ?>
    </main>
  </div>
  <?= bottom_nav() ?>
</div>
<script src="<?= e(asset('app.js')) ?>" defer></script>
<?php foreach ($scripts as $s): ?><script src="<?= e(asset($s)) ?>" defer></script><?php endforeach; ?>
<script src="<?= e(asset('moduller.js')) ?>" defer></script>
<?php if (function_exists('ozellik_acik') && ozellik_acik('barkod')): ?><script src="<?= e(asset('barkod.js')) ?>" defer></script><?php endif; ?>
<script src="<?= e(asset('pwa.js')) ?>" defer></script>
</body>
</html>
<?php
}

/** Sayfa başlığı bloğu. $actions: HTML. */
function page_header(string $title, string $subtitle = '', string $actions = '', string $back = '', string $kicker = ''): void
{
    echo '<div class="page-head">';
    echo '<div>';
    if ($back !== '') {
        echo '<a class="back-link" href="' . e($back) . '">' . icon('arrow-left') . ' Geri</a><br>';
    }
    if ($kicker !== '') {
        echo '<span class="kicker">' . e($kicker) . '</span>';
    }
    echo '<h1>' . e($title) . '</h1>';
    if ($subtitle !== '') {
        echo '<p>' . $subtitle . '</p>';
    }
    echo '</div>';
    if ($actions !== '') {
        echo '<div class="page-actions">' . $actions . '</div>';
    }
    echo '</div>';
}

function select_options(array $options, ?string $selected, bool $assoc = true): string
{
    $html = '';
    foreach ($options as $k => $v) {
        $value = $assoc ? (string) $k : (string) $v;
        $label = is_array($v) ? $v[0] : $v;
        $html .= '<option value="' . e($value) . '"' . ($value === (string) $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

function empty_state(string $title, string $text = '', string $action = ''): string
{
    return '<div class="empty">' . empty_art() . '<b>' . e($title) . '</b>' . ($text ? '<p>' . e($text) . '</p>' : '') . $action . '</div>';
}

/** 4.12.0 — Merkezden açılan özelliklerin menü öğeleri, ilgili öğelerin hemen arkasına eklenir. */
function ozellik_menusu_ekle(array &$nav): void
{
    if (!function_exists('ozellik_acik')) {
        return;
    }
    $ekle = static function (array &$liste, string $sonra, array $oge): void {
        $konum = count($liste);
        foreach ($liste as $i => $x) {
            if ($x[0] === $sonra) {
                $konum = $i + 1;
                break;
            }
        }
        array_splice($liste, $konum, 0, [$oge]);
    };
    $sayi = static function (string $sql): int {
        try {
            return (int) scalar($sql);
        } catch (Throwable) {
            return 0;
        }
    };
    $a = &$nav['Atölye'];
    if (ozellik_acik('barkod')) {
        $ekle($a, 'orders', ['barkod', 'barkod.php', 'Barkod okut', 'barcode', 0]);
    }
    if (ozellik_acik('whatsapp')) {
        $ekle($a, 'hatirlatma', ['mesajlar', 'mesajlar.php', 'WhatsApp mesajları', 'chat', wa_bekleyen_sayisi()]);
    }
    if (ozellik_acik('sgk_mutabakat')) {
        $ekle($a, 'sgk', ['sgk-mutabakat', 'sgk-mutabakat.php', 'SGK mutabakat', 'chart', 0]);
    }
    if (ozellik_acik('cam_siparis')) {
        $ekle($a, 'stock', ['cam-siparis', 'cam-siparis.php', 'Cam siparişleri', 'truck',
            $sayi("SELECT COUNT(*) FROM cam_siparisleri WHERE durum = 'taslak'")]);
    }
    if (ozellik_acik('cam_hata') && function_exists('cam_hata_rozet')) {   // 4.15.0
        $ekle($a, 'stock', ['cam-hatalari', 'cam-hatalari.php', 'Hatalı camlar', 'glasses', is_super() ? cam_hata_rozet() : 0]);
    }
    if (ozellik_acik('sgk_hak')) {
        $ekle($a, 'sgk', ['sgk-hak', 'sgk-hak.php', 'SGK hak kontrolü', 'download', 0]);
    }
    if (ozellik_acik('uts_bildirim') && function_exists('uts_menu_rozeti')) {
        $ekle($a, 'stock', ['uts-bildirim', 'uts.php', 'ÜTS bildirimleri', 'barcode', uts_menu_rozeti()]);
    }
    if (ozellik_acik('stok_oneri')) {
        $ekle($a, 'stock', ['stok-oneri', 'stok-oneri.php', 'Satın alma önerisi', 'box', 0]);
    }
    if (ozellik_acik('efatura') && can_see_amounts()) {
        $ekle($a, 'kasa', ['faturalar', 'faturalar.php', 'Faturalar', 'receipt',
            $sayi("SELECT COUNT(*) FROM faturalar WHERE durum = 'taslak'")]);
    }
}
