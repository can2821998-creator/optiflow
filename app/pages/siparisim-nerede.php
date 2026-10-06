<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';

/* ==========================================================================
   Müşterinin sipariş numarası + telefon son 4 hanesiyle kendi takip
   bağlantısını bulmasını sağlar. Oturum gerektirmez.
   GÜVENLİK: Sipariş numarası tek başına tahmin edilebilir (ardışık) olduğu
   için TEK BAŞINA arama anahtarı olarak kullanılmaz — telefon son 4 hane ile
   birlikte doğrulanır. Böylece biri sırayla numara denese bile, doğru
   telefonu bilmediği sürece hiçbir sonuca ulaşamaz. Ayrıca oturum bazlı
   basit bir deneme sınırı vardır.
   ========================================================================== */

header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');

$shop    = setting('shop_name', 'OptiFlow');
$telefon = setting('shop_phone', '');
$telHam  = preg_replace('/\D+/', '', $telefon) ?? '';

$hata = '';
$denemeAnahtari = 'siparis_arama_deneme';
$_SESSION[$denemeAnahtari] ??= ['adet' => 0, 'ilk' => time()];

/* IP bazlı fren: oturum sayacı çerez atılarak sıfırlanabildiği için asıl sınır burada.
   Aynı IP'den 15 dakikada 25 denemeden sonra kilitlenir. login_attempts tablosu (tenant DB)
   yeniden kullanılır; özel kullanıcı adı işaretiyle (SIPARIS_SORGU_ANAHTAR, app/auth.php) ayrılır. */
const SIPARIS_SORGU_MAX = 25;

function siparis_sorgu_kilitli(): bool
{
    try {
        $since = date('Y-m-d H:i:s', time() - 900);
        return (int) scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ? AND attempted_at > ?',
            [SIPARIS_SORGU_ANAHTAR, client_ip(), $since]
        ) >= SIPARIS_SORGU_MAX;
    } catch (Throwable $e) {
        return false;
    }
}

if (is_post()) {
    // Oturumda 10 dakikada 8 denemeden fazlasına izin verme (kaba kuvvet fren).
    if (time() - $_SESSION[$denemeAnahtari]['ilk'] > 600) {
        $_SESSION[$denemeAnahtari] = ['adet' => 0, 'ilk' => time()];
    }
    $_SESSION[$denemeAnahtari]['adet']++;
    try {
        insert('login_attempts', ['ip' => client_ip(), 'username' => SIPARIS_SORGU_ANAHTAR]);
    } catch (Throwable $e) {
        // deneme kaydı tutulamadı: akış engellenmesin
    }

    if ($_SESSION[$denemeAnahtari]['adet'] > 8 || siparis_sorgu_kilitli()) {
        $hata = 'Çok fazla deneme yapıldı. Lütfen birkaç dakika sonra tekrar deneyin, ya da bizi arayın.';
    } else {
        $siparisNo = preg_replace('/\D+/', '', (string) post('siparis_no')) ?? '';
        $siparisNo = ltrim($siparisNo, '0');
        $son4 = preg_replace('/\D+/', '', (string) post('telefon_son4')) ?? '';

        if ($siparisNo === '' || strlen($son4) !== 4) {
            $hata = 'Lütfen sipariş numaranızı ve telefon numaranızın son 4 hanesini eksiksiz girin.';
        } else {
            $order = row('SELECT id, phone, order_stage FROM orders WHERE id = ?', [(int) $siparisNo]);
            $eslesti = false;
            if ($order) {
                $kayitliTel = preg_replace('/\D+/', '', (string) $order['phone']) ?? '';
                $eslesti = $kayitliTel !== '' && substr($kayitliTel, -4) === $son4;
            }
            if ($eslesti && $order['order_stage'] !== 'iptal') {
                $token = order_public_token((int) $order['id']);
                if ($token !== '') {
                    redirect(musteri_link('durum.php', ['k' => $token]));
                }
            }
            // Kasıtlı olarak belirsiz mesaj: numara mı telefon mu yanlış, söylenmez.
            $hata = 'Bu bilgilerle bir sipariş bulunamadı. Sipariş numaranızı ve telefon son 4 hanenizi kontrol edip tekrar deneyin.';
        }
    }
}

/* ---------- Şablon değişkenleri ---------- */
$telUrl     = $telHam !== '' ? 'tel:' . $telHam : '';
$girilenNo  = post('siparis_no');
$csrf       = csrf_field();
$sayfaBasligi = 'Siparişim nerede? · ' . $shop;
?><!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#faf6ef" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#150f0d" media="(prefers-color-scheme: dark)">
<title><?= e($sayfaBasligi) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="stylesheet" href="<?= e(asset('musteri.css')) ?>">
<?= ga_head() ?>
</head>
<body class="tk-page">

<main class="tk tk-tone-brand">
  <header class="tk-head">
    <span class="tk-mark" aria-hidden="true">
      <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round"><path d="M12 36c0-6.5 4.5-11 10-11s10 4.5 10 11-4.5 10-10 10-10-4.5-10-10Zm20 0c0-6.5 4.5-11 10-11s10 4.5 10 11-4.5 10-10 10-10-4.5-10-10Zm-10-3h10M12 31l-5-2m45 2 5-2"/></svg>
    </span>
    <div class="tk-brand">
      <b><?= e($shop) ?></b>
      <small>Siparişim nerede?</small>
    </div>
    <?php if ($telUrl !== ''): ?>
      <a class="tk-call" href="<?= e($telUrl) ?>" aria-label="Mağazayı ara">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </a>
    <?php endif; ?>
  </header>

  <section class="tk-card tk-center">
    <span class="tk-medal" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/></svg>
    </span>
    <h1>Siparişinizi bulalım</h1>
    <p>Sipariş numaranızı ve siparişte kayıtlı telefon numaranızın son 4 hanesini girin, durumu hemen gösterelim.</p>

    <?php if ($hata !== ''): ?>
      <div class="tk-alert" role="alert">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.4v.01"/></svg>
        <span><?= e($hata) ?></span>
      </div>
    <?php endif; ?>

    <form method="post" class="tk-form" autocomplete="off">
      <?= $csrf ?>
      <div class="tk-field">
        <label for="siparis_no">Sipariş numarası</label>
        <div class="tk-input-wrap">
          <i aria-hidden="true">#</i>
          <input class="tk-input" id="siparis_no" type="text" inputmode="numeric" enterkeyhint="next" name="siparis_no" placeholder="00073" required maxlength="10" autocomplete="off" value="<?= e($girilenNo) ?>" aria-describedby="no-ipucu">
        </div>
        <span class="tk-hint" id="no-ipucu">Fişinizde “Gözlük siparişi · #00073” gibi yazar.</span>
      </div>
      <div class="tk-field">
        <label for="telefon_son4">Telefon numaranızın son 4 hanesi</label>
        <input class="tk-input" id="telefon_son4" type="text" inputmode="numeric" enterkeyhint="go" name="telefon_son4" placeholder="4299" required maxlength="4" pattern="[0-9]{4}" autocomplete="off" aria-describedby="tel-ipucu">
        <span class="tk-hint" id="tel-ipucu">Siparişi verirken bize bıraktığınız numara.</span>
      </div>
      <button class="tk-btn" type="submit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.2-4.2"/></svg>
        Durumu göster
      </button>
    </form>

    <details class="tk-help">
      <summary>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.6 9.4a2.5 2.5 0 0 1 4.9.6c0 1.6-2.5 2-2.5 3.6M12 17v.01"/></svg>
        Sipariş numaramı nerede bulurum?
      </summary>
      <div class="tk-help-body">
        <svg class="tk-receipt" viewBox="0 0 240 176" role="img" aria-label="Fiş örneği: üstte sipariş numarası, altta karekod">
          <rect x="20" y="4" width="200" height="168" rx="10" fill="#ffffff" stroke="#d6c8b3"/>
          <rect x="34" y="18" width="64" height="8" rx="4" fill="#e7ddcd"/>
          <rect x="34" y="34" width="172" height="28" rx="8" fill="#f8e9ea" stroke="<?= e(brand_color()) ?>" stroke-width="1.5" stroke-dasharray="4 3"/>
          <text x="120" y="53" text-anchor="middle" font-size="13" font-weight="800" fill="<?= e(brand_color()) ?>" font-family="Manrope, system-ui, sans-serif">Gözlük siparişi · #00073</text>
          <rect x="34" y="74" width="120" height="6" rx="3" fill="#e7ddcd"/>
          <rect x="34" y="88" width="90" height="6" rx="3" fill="#e7ddcd"/>
          <rect x="34" y="102" width="106" height="6" rx="3" fill="#e7ddcd"/>
          <g transform="translate(150 84)" fill="#241a15">
            <rect width="20" height="20" fill="none" stroke="#241a15" stroke-width="3"/><rect x="6" y="6" width="8" height="8"/>
            <rect x="30" width="20" height="20" fill="none" stroke="#241a15" stroke-width="3"/><rect x="36" y="6" width="8" height="8"/>
            <rect y="30" width="20" height="20" fill="none" stroke="#241a15" stroke-width="3"/><rect x="6" y="36" width="8" height="8"/>
            <rect x="30" y="30" width="8" height="8"/><rect x="42" y="30" width="8" height="8"/><rect x="36" y="42" width="8" height="8"/><rect x="24" y="24" width="4" height="4"/>
          </g>
          <rect x="34" y="150" width="96" height="6" rx="3" fill="#e7ddcd"/>
        </svg>
        <p>Numara, fişinizin üst kısmında işlem türünün yanında yazar. Fişte bir de karekod varsa telefonunuzun kamerasıyla okutarak bu adımı tamamen atlayabilirsiniz.</p>
      </div>
    </details>
  </section>

  <?php if ($telUrl !== ''): ?>
    <p class="tk-lookup-foot">Bilgilerinizi bulamadıysanız <a href="<?= e($telUrl) ?>"><?= e($telefon) ?></a> numarasından bize ulaşabilirsiniz.</p>
  <?php endif; ?>
</main>

</body>
</html>
