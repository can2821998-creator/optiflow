<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once APP_ROOT . '/app/seo.php';
require_once APP_ROOT . '/app/tasima.php';

if (!config('merkez_admin_password') && !config('merkez_admin_password_hash')) {
    render_error_page('Panel kapalı', "config.php içine 'merkez_admin_password_hash' (önerilir) ya da 'merkez_admin_password' anahtarı eklenmeden bu panel açılmaz.");
}

/* ---------------- Giriş ---------------- */
$hata = '';
if (is_post() && post('action') === 'giris') {
    if (merkez_admin_kilitli()) {
        $hata = 'Çok fazla hatalı deneme. ' . MERKEZ_GIRIS_PENCERE_DK . ' dakika sonra tekrar deneyin.';
    } elseif (!merkez_admin_giris((string) ($_POST['sifre'] ?? ''))) {
        $hata = 'Şifre hatalı.';
    } else {
        redirect('merkez-panel.php');
    }
}

if (!merkez_admin_mi()) {
    ?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Merkez panel girişi</title>
    <style>
    body{margin:0;min-height:100vh;display:grid;place-items:center;background:linear-gradient(150deg,#4a5aff,#1c2ecc);font:15px system-ui,Arial,sans-serif;padding:20px}
    .card{background:#fff;border-radius:18px;padding:32px 28px;width:100%;max-width:360px;box-shadow:0 30px 60px -20px rgba(11,17,60,.5)}
    .mk{width:46px;height:46px;margin:0 auto 14px;display:block}
    h2{margin:0 0 4px;text-align:center;font-size:19px;color:#14131f}
    .sub{margin:0 0 20px;text-align:center;color:#5b5a6c;font-size:13px}
    label{font-size:12px;font-weight:700;color:#5b5a6c;letter-spacing:.03em;text-transform:uppercase}
    input{width:100%;padding:12px;border:1.5px solid #e5e2ea;border-radius:10px;margin:7px 0 16px;box-sizing:border-box;font-size:15px}
    input:focus{outline:0;border-color:#3346ff;box-shadow:0 0 0 3px rgba(51,70,255,.15)}
    button{width:100%;padding:13px;border:0;border-radius:99px;background:linear-gradient(180deg,#4a5aff,#1c2ecc);color:#fff;font-weight:800;font-size:15px;cursor:pointer}
    .err{background:#fdeceb;color:#9c2b23;padding:9px 11px;border-radius:9px;font-size:13px;margin-bottom:14px}</style>
    </head><body><form class="card" method="post">
      <svg class="mk" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs><linearGradient id="g" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#3346ff"/><stop offset=".5" stop-color="#c026d3"/><stop offset="1" stop-color="#ff4433"/></linearGradient></defs><g transform="rotate(-10 50 50)"><path fill-rule="evenodd" clip-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z" fill="url(#g)"/></g></svg>
      <?= csrf_field() ?><input type="hidden" name="action" value="giris">
      <h2>OptiFlow Merkez</h2>
      <p class="sub">Yönetim paneli</p>
      <?php if ($hata): ?><div class="err"><?= e($hata) ?></div><?php endif; ?>
      <label>Yönetici şifresi</label><input type="password" name="sifre" autofocus required autocomplete="current-password">
      <button>Panele gir</button>
    </form></body></html><?php
    exit;
}

/* ---------------- İşlemler (POST) ---------------- */
if (is_post()) {
    $action = post('action');
    $id = post_int('id');
    $geri = post('geri') === 'detay' && $id ? 'merkez-panel.php?magaza=' . $id : 'merkez-panel.php';
    try {
        switch ($action) {
            case 'durum':
                merkez_magaza_durum_degistir($id, post('durum'));
                merkez_log('durum', $id, post('durum'));
                flash('Durum güncellendi.');
                break;
            case 'surum':
                merkez_magaza_surum_guncelle($id, post('surum'));
                merkez_log('surum', $id, post('surum'));
                flash('Paket güncellendi: ' . (PAKETLER[post('surum')] ?? post('surum')) . '.');
                break;
            case 'ozellikler':
                merkez_ozellikleri_kaydet($id, (array) ($_POST['ozellik'] ?? []));
                merkez_log('ozellik', $id, implode(', ', ozellik_listesi_temizle((array) ($_POST['ozellik'] ?? []))) ?: 'hepsi kapalı');
                flash('Özellikler güncellendi.');
                break;
            case 'plan':
                merkez_magaza_plan_guncelle($id, post('plan'), post('deneme_bitis') ?: null);
                merkez_log('plan', $id, post('plan') . ' · ' . post('deneme_bitis'));
                flash('Plan güncellendi.');
                break;
            case 'uzat':
                merkez_deneme_uzat($id, post_int('gun'));
                merkez_log('uzat', $id, '+' . post_int('gun') . ' gün');
                flash('Deneme süresi uzatıldı.');
                break;
            case 'etkinlestir':
                $port = post('db_port');
                tenant_etkinlestir($id, post('db_host'), post('db_name'), post('db_user'), (string) ($_POST['db_sifre'] ?? ''), $port !== '' ? (int) $port : null);
                merkez_log('etkinlestir', $id, post('db_name'));
                flash('Mağaza etkinleştirildi.');
                break;
            case 'duzenle':
                merkez_magaza_duzenle($id, post('isim'), post('email'));
                merkez_log('duzenle', $id);
                flash('Mağaza bilgileri güncellendi.');
                break;
            case 'not':
                merkez_not_kaydet($id, post('notlar'));
                flash('Not kaydedildi.');
                break;
            case 'sifre':
                merkez_giris_sifresi_sifirla($id, (string) ($_POST['yeni_sifre'] ?? ''));
                merkez_log('sifre', $id);
                flash('Mağaza giriş şifresi sıfırlandı.');
                break;
            case 'db':
                $port = post('db_port');
                merkez_db_guncelle($id, post('db_host'), post('db_name'), post('db_user'), (string) ($_POST['db_sifre'] ?? ''), $port !== '' ? (int) $port : null);
                merkez_log('db', $id, post('db_name'));
                flash('Veritabanı bağlantısı güncellendi ve doğrulandı.');
                break;
            case 'sil':
                if (post('onay') !== 'SİL') {
                    throw new DomainException('Silme onayı için kutuya SİL yazın.');
                }
                $silIsim = (string) merkez_scalar('SELECT isim FROM magazalar WHERE id = ?', [$id]);
                merkez_magaza_sil($id);
                merkez_log('sil', null, 'silindi', $silIsim);
                flash('Mağaza kaydı silindi. (Not: mağazanın kendi veritabanı Plesk\'te duruyor; gerekiyorsa oradan silin.)');
                redirect('merkez-panel.php');
                break;
            case 'kul_sifre':
                $mag = merkez_magaza($id) ?? throw new DomainException('Mağaza bulunamadı.');
                merkez_tenant_sifre_sifirla($mag, post_int('user_id'), (string) ($_POST['yeni_sifre'] ?? ''));
                merkez_log('kul_sifre', $id, 'user#' . post_int('user_id'));
                flash('Personel şifresi sıfırlandı.');
                break;
            case 'kul_durum':
                $mag = merkez_magaza($id) ?? throw new DomainException('Mağaza bulunamadı.');
                merkez_tenant_kullanici_durum($mag, post_int('user_id'), post('aktif') === '1');
                merkez_log('kul_durum', $id, 'user#' . post_int('user_id') . ' → ' . (post('aktif') === '1' ? 'aktif' : 'pasif'));
                flash('Personel durumu güncellendi.');
                break;
            case 'tasima_yukle':   // 4.17.1 eski sistemden veri taşıma: 1) yükle ve önizle
                $mag = merkez_magaza($id) ?? throw new DomainException('Mağaza bulunamadı.');
                $ozet = tasima_yukle($id, $_FILES['yedek'] ?? []);
                $_SESSION['tasima'][$id] = $ozet;
                merkez_log('tasima', $id, 'yedek yüklendi: ' . $ozet['ad'] . ' (' . $ozet['kaynak'] . ' ' . $ozet['surum'] . ')');
                flash('Yedek okundu. Aşağıdaki özeti kontrol edip taşımayı onaylayın.');
                $geri = 'merkez-panel.php?magaza=' . $id . '#tasima';
                break;
            case 'tasima_vazgec':
                $dosya = (string) ($_SESSION['tasima'][$id]['dosya'] ?? '');
                if ($dosya !== '' && preg_match('/^yukleme-' . $id . '-[0-9a-f]{12}\.sql(\.gz)?$/', $dosya)) {
                    @unlink(tasima_klasor() . '/' . $dosya);
                }
                unset($_SESSION['tasima'][$id]);
                flash('Taşıma iptal edildi; yüklenen dosya silindi.');
                $geri = 'merkez-panel.php?magaza=' . $id . '#tasima';
                break;
            case 'tasima_uygula':
                $mag = merkez_magaza($id) ?? throw new DomainException('Mağaza bulunamadı.');
                if (tasima_onay(post('onay')) !== 'TAŞI') {
                    throw new DomainException('Onaylamak için kutuya TAŞI yazın.');
                }
                $bekleyen = $_SESSION['tasima'][$id] ?? null;
                if (!$bekleyen) {
                    throw new DomainException('Önce yedek dosyasını yükleyin.');
                }
                $sonuc = tasima_uygula_magaza($mag, (string) $bekleyen['dosya']);
                unset($_SESSION['tasima'][$id]);
                merkez_log('tasima', $id, 'taşındı: ' . $bekleyen['ad'] . ' → ' . $sonuc['sayilar']['customers'] . ' müşteri, ' . $sonuc['sayilar']['orders'] . ' sipariş, şema ' . $sonuc['sema']);
                flash('Taşıma tamamlandı: ' . $sonuc['sayilar']['customers'] . ' müşteri, ' . $sonuc['sayilar']['orders'] . ' sipariş, ' . $sonuc['sayilar']['payments'] . ' tahsilat aktarıldı. Veritabanı güncel sürüme (şema ' . $sonuc['sema'] . ') yükseltildi.');
                $geri = 'merkez-panel.php?magaza=' . $id . '#tasima';
                break;
            case 'tasima_geri_al':
                $mag = merkez_magaza($id) ?? throw new DomainException('Mağaza bulunamadı.');
                if (tasima_onay(post('onay')) !== 'GERİ AL') {
                    throw new DomainException('Onaylamak için kutuya GERİ AL yazın.');
                }
                $sonuc = tasima_geri_al($mag);
                merkez_log('tasima', $id, 'son taşıma geri alındı');
                flash('Son taşıma geri alındı; mağaza taşımadan önceki haline döndü.');
                $geri = 'merkez-panel.php?magaza=' . $id . '#tasima';
                break;
            case 'gir':
                merkez_magaza_gir($id);
                redirect('index.php');
                break;
            case 'olustur':
                $yeni = merkez_magaza_olustur(post('isim'), post('email'), (string) ($_POST['giris_sifre'] ?? ''), post('admin_ad'), post('admin_kullanici'), (string) ($_POST['admin_sifre'] ?? ''));
                flash(($yeni['durum'] ?? '') === 'aktif' ? 'Mağaza oluşturuldu ve etkinleştirildi.' : 'Mağaza oluşturuldu (beklemede) — veritabanını girip etkinleştirin.');
                redirect('merkez-panel.php?magaza=' . (int) ($yeni['id'] ?? 0));
                break;
            case 'toplu':
                $ids = array_values(array_filter(array_map('intval', (array) ($_POST['sec'] ?? []))));
                $eylem = post('toplu_eylem');
                if (!$ids) {
                    throw new DomainException('Hiç mağaza seçilmedi.');
                }
                $sayac = 0;
                foreach ($ids as $mid) {
                    try {
                        if ($eylem === 'dondur') { merkez_magaza_durum_degistir($mid, 'dondu'); $sayac++; }
                        elseif ($eylem === 'aktif') { merkez_magaza_durum_degistir($mid, 'aktif'); $sayac++; }
                        elseif ($eylem === 'uzat30') { merkez_deneme_uzat($mid, 30); $sayac++; }
                        elseif ($eylem === 'plan_ucretli') { $mm = merkez_magaza($mid); merkez_magaza_plan_guncelle($mid, 'ucretli', $mm['deneme_bitis'] ?? null); $sayac++; }
                        elseif ($eylem === 'plan_deneme') { $mm = merkez_magaza($mid); merkez_magaza_plan_guncelle($mid, 'deneme', $mm['deneme_bitis'] ?? null); $sayac++; }
                        elseif ($eylem === 'surum_pro') { merkez_magaza_surum_guncelle($mid, 'pro'); $sayac++; }
                        elseif ($eylem === 'surum_lite') { merkez_magaza_surum_guncelle($mid, 'lite'); $sayac++; }
                        elseif (preg_match('/^ozellik_(ac|kapat):([a-z_]+)$/', $eylem, $om)) { merkez_ozellik_ayarla($mid, $om[2], $om[1] === 'ac'); $sayac++; }
                    } catch (Throwable $e) { /* tek tek atla */ }
                }
                merkez_log('toplu', null, $eylem . ' × ' . $sayac);
                flash($sayac . ' mağazada işlem uygulandı.');
                break;
            case 'rehber_kaydet':
                try {
                    $k = rehber_kaydet([
                        'slug' => post('slug'), 'baslik' => post('baslik'), 'seo_baslik' => post('seo_baslik'),
                        'meta' => post('meta'), 'hedef_kelime' => post('hedef_kelime'),
                        'govde' => (string) ($_POST['govde'] ?? ''), 'yayinda' => post('yayinda') === '1',
                    ], post('eski_slug'));
                } catch (RuntimeException $e) {
                    throw new DomainException($e->getMessage());
                }
                merkez_log('rehber', null, $k['slug'] . ($k['yayinda'] ? ' · yayında' : ' · taslak'));
                flash($k['yayinda'] ? 'Yazı kaydedildi ve yayında.' : 'Yazı taslak olarak kaydedildi.');
                $geri = 'merkez-panel.php?gorunum=rehber';
                break;
            case 'rehber_yayin':
                try {
                    rehber_yayin_degistir(post('slug'), post('yayinda') === '1');
                } catch (RuntimeException $e) {
                    throw new DomainException($e->getMessage());
                }
                merkez_log('rehber', null, post('slug') . (post('yayinda') === '1' ? ' · yayına alındı' : ' · yayından kaldırıldı'));
                flash(post('yayinda') === '1' ? 'Yazı yayına alındı.' : 'Yazı yayından kaldırıldı.');
                $geri = 'merkez-panel.php?gorunum=rehber';
                break;
            case 'rehber_sil':
                if (post('onay') !== 'SİL') {
                    throw new DomainException('Silmek için kutuya SİL yazın.');
                }
                rehber_sil(post('slug'));
                merkez_log('rehber', null, post('slug') . ' · silindi');
                flash('Yazı silindi.');
                $geri = 'merkez-panel.php?gorunum=rehber';
                break;
            case 'seo_kaydet':
                try {
                    $yuklu = $_FILES['anahtar'] ?? null;
                    if (is_array($yuklu) && ($yuklu['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        if ($yuklu['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $yuklu['tmp_name'])) {
                            throw new RuntimeException('Anahtar dosyası yüklenemedi.');
                        }
                        if ((int) $yuklu['size'] > 20000) {
                            throw new RuntimeException('Anahtar dosyası çok büyük; Google\'ın verdiği .json dosyasını seçin.');
                        }
                        $eposta = seo_anahtar_kaydet((string) file_get_contents((string) $yuklu['tmp_name']));
                        merkez_log('seo', null, 'hizmet hesabı anahtarı yüklendi · ' . $eposta);
                    }
                    seo_ayar_kaydet([
                        'gsc_site' => post('gsc_site'), 'site_url' => post('site_url'),
                        'psi_api_key' => post('psi_api_key'), 'psi_api_key_sil' => post('psi_api_key_sil') === '1',
                        'google_dogrulama' => (string) ($_POST['google_dogrulama'] ?? ''),
                        'bing_dogrulama' => (string) ($_POST['bing_dogrulama'] ?? ''),
                    ]);
                } catch (RuntimeException $e) {
                    throw new DomainException($e->getMessage());
                }
                merkez_log('seo', null, 'ayarlar kaydedildi');
                flash('SEO ayarları kaydedildi.');
                $geri = 'merkez-panel.php?gorunum=seo';
                break;
            case 'seo_token':
                try {
                    seo_ayar_kaydet(['token_uret' => true]);
                } catch (RuntimeException $e) {
                    throw new DomainException($e->getMessage());
                }
                merkez_log('seo', null, 'erişim anahtarı yenilendi');
                flash('Yeni erişim anahtarı üretildi. Eski bağlantı artık çalışmaz.');
                $geri = 'merkez-panel.php?gorunum=seo';
                break;
            case 'seo_anahtar_sil':
                seo_anahtar_sil();
                merkez_log('seo', null, 'hizmet hesabı anahtarı silindi');
                flash('Google anahtarı silindi.');
                $geri = 'merkez-panel.php?gorunum=seo';
                break;
            case 'seo_yenile':
                @set_time_limit(120);
                $sonuc = seo_veri_topla(post('psi') === '1');
                $_SESSION['seo_mulkler'] = $sonuc['mulkler'];
                if ($sonuc['hatalar']) {
                    foreach ($sonuc['hatalar'] as $h) {
                        flash($h, 'error');
                    }
                } else {
                    flash('Google verisi alındı.');
                }
                if ($sonuc['gsc'] !== null || $sonuc['psi'] !== null) {
                    seo_onbellek_yaz($sonuc);   // kısmi sonuç da gösterilsin
                }
                $geri = 'merkez-panel.php?gorunum=seo';
                break;
            case 'cikis':
                unset($_SESSION['merkez_admin'], $_SESSION['merkez_impersonate']);
                redirect('merkez-panel.php');
                break;
        }
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
        if (str_starts_with($action, 'seo_')) {
            $geri = 'merkez-panel.php?gorunum=seo';
        }
        if (str_starts_with($action, 'rehber_')) {
            $geri = 'merkez-panel.php?gorunum=rehber' . (post('eski_slug') !== '' ? '&yazi=' . rawurlencode(post('eski_slug')) : '');
        }
        if (str_starts_with($action, 'tasima_')) {
            $geri = 'merkez-panel.php?magaza=' . $id . '#tasima';
        }
    } catch (Throwable $e) {
        if (!str_starts_with((string) $action, 'tasima_')) {
            throw $e;
        }
        app_log('Veri taşıma hatası (mağaza #' . $id . '): ' . $e->getMessage());
        flash('Taşıma sırasında beklenmeyen bir hata oluştu: ' . mb_substr($e->getMessage(), 0, 200) . ' — Mağaza verisi taşımadan önce yedeklendi; "Son taşımayı geri al" ile eski haline döndürebilirsiniz.', 'error');
        $geri = 'merkez-panel.php?magaza=' . $id . '#tasima';
    }
    redirect($geri);
}

/* ---------------- CSV dışa aktar ---------------- */
if (query('disaAktar') === '1') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="optiflow-magazalar-' . date('Ymd') . '.csv"');
    header('Cache-Control: no-store');
    echo merkez_csv();
    exit;
}

/* ---------------- Ortak yardımcılar ---------------- */
$durumEtiket = ['aktif' => 'Aktif', 'dondu' => 'Donduruldu', 'beklemede' => 'Beklemede'];
$planEtiket  = ['deneme' => 'Deneme', 'ucretli' => 'Ücretli'];
function kalan_gun(?string $bitis): ?int
{
    return $bitis ? (int) floor((strtotime($bitis) - strtotime(date('Y-m-d'))) / 86400) : null;
}
function kalan_sinif(?int $g): string
{
    if ($g === null) return '';
    if ($g < 0) return 'kg-kirmizi';
    if ($g <= 7) return 'kg-amber';
    return 'kg-yesil';
}

$stil = <<<'CSS'
@font-face{font-family:"Manrope";font-style:normal;font-display:swap;font-weight:200 800;src:url("assets/fonts/manrope-latin-wght-normal.woff2") format("woff2");unicode-range:U+0000-00FF,U+0131,U+0152-0153,U+2000-206F,U+20AC,U+2122}
@font-face{font-family:"Manrope";font-style:normal;font-display:swap;font-weight:200 800;src:url("assets/fonts/manrope-latin-ext-wght-normal.woff2") format("woff2");unicode-range:U+0100-02BA,U+1E00-1E9F,U+2C60-2C7F,U+A720-A7FF}
:root{--bg:#f4f5fb;--card:#fff;--ink:#14131f;--soft:#5b5a6c;--line:#e7e7ef;--primary:#3346ff;--primary-deep:#1c2ecc;--primary-tint:#eceffe;--mid:#c026d3;--pop:#ff4433;--pop-deep:#db2f20;--ok:#1c7a4d;--ok-tint:#e6f4ea;--amber:#8a5a12;--amber-tint:#fdf1e0}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font-family:Manrope,system-ui,Arial,sans-serif;font-size:14.5px;line-height:1.5}
a{color:var(--primary-deep)}
.wrap{max-width:1200px;margin:0 auto;padding:22px}
.topbar{position:sticky;top:0;z-index:10;background:rgba(244,245,251,.9);backdrop-filter:blur(8px);border-bottom:1px solid var(--line)}
.topbar .wrap{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:14px;padding-bottom:14px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:17px}
.brand svg{width:30px;height:30px}
.brand small{display:block;font-size:11px;color:var(--soft);font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.tb-actions{display:flex;gap:8px;align-items:center}
.tb-nav{display:flex;gap:4px;align-items:center;flex:1;justify-content:center;flex-wrap:wrap}
.tb-nav a{padding:7px 13px;border-radius:99px;text-decoration:none;color:var(--soft);font-weight:700;font-size:13.5px}
.tb-nav a:hover{background:#ececf5;color:var(--ink)}
.tb-nav a.on{background:var(--ink);color:#fff}
.impbar{background:linear-gradient(180deg,#fff7ea,#fff);border:1px solid #f3d9a8;border-radius:12px;padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;font-size:13.5px;font-weight:600;color:var(--amber)}
.bulkbar{display:none;gap:8px;align-items:center;flex-wrap:wrap;background:var(--ink);color:#fff;border-radius:12px;padding:10px 14px;margin-bottom:12px}
.bulkbar select{color:var(--ink)}
tbody tr:has(.selbox:checked){background:#eef1ff}
.selc{width:34px;text-align:center}
.utable td,.utable th{padding:9px 12px}
@media(max-width:760px){.tb-nav{order:3;flex-basis:100%;justify-content:flex-start}}
h1{font-size:22px;margin:0 0 2px;letter-spacing:-.02em}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 15px;border-radius:99px;border:0;background:var(--ink);color:#fff;font-weight:700;font-size:13.5px;cursor:pointer;text-decoration:none;white-space:nowrap;font-family:inherit}
.btn:hover{filter:brightness(1.08)}
.btn-primary{background:linear-gradient(180deg,#4a5aff,#1c2ecc)}
.btn-ok{background:var(--ok)}
.btn-amber{background:var(--amber)}
.btn-danger{background:var(--pop-deep)}
.btn-ghost{background:#fff;color:var(--ink);border:1.5px solid var(--line)}
.btn-ghost:hover{border-color:#cfcfe0;filter:none}
.btn-sm{padding:6px 11px;font-size:12.5px}
.flash{padding:11px 14px;border-radius:11px;margin:0 0 14px;font-size:13.5px;font-weight:600}
.flash-ok{background:var(--ok-tint);color:var(--ok)}
.flash-error{background:#fdeceb;color:#9c2b23}
/* KPI */
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:6px 0 20px}
.kpi{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:14px 16px;text-decoration:none;color:inherit;transition:transform .15s,box-shadow .15s,border-color .15s;display:block}
.kpi:hover{transform:translateY(-2px);box-shadow:0 16px 30px -22px rgba(20,19,31,.4);border-color:#d7d7ea}
.kpi .n{font-size:26px;font-weight:800;letter-spacing:-.02em;line-height:1}
.kpi .l{font-size:12.5px;color:var(--soft);font-weight:600;margin-top:5px}
.kpi.hot{background:linear-gradient(160deg,#fff,#fff6ea);border-color:#f3d9a8}
.kpi.hot .n{color:var(--amber)}
.kpi.act .n{color:var(--primary-deep)}
.kpi.red .n{color:var(--pop-deep)}
/* Araç çubuğu */
.toolbar{display:flex;flex-wrap:wrap;gap:8px;align-items:center;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:12px;margin-bottom:16px}
.toolbar input[type=search],.toolbar select{padding:9px 11px;border:1.4px solid var(--line);border-radius:9px;font-size:13.5px;font-family:inherit;background:#fff}
.toolbar input[type=search]{min-width:220px;flex:1}
.chips{display:flex;gap:6px;flex-wrap:wrap}
.chip{padding:6px 12px;border-radius:99px;border:1.4px solid var(--line);background:#fff;font-size:12.5px;font-weight:700;text-decoration:none;color:var(--soft)}
.chip.on{background:var(--ink);color:#fff;border-color:var(--ink)}
/* Tablo/kartlar */
.card{background:var(--card);border:1px solid var(--line);border-radius:14px}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:12px 14px;border-bottom:1px solid var(--line);vertical-align:middle}
th{background:#faf9fd;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;color:var(--soft);position:sticky;top:0}
tr:last-child td{border-bottom:0}
tr.bekle{background:#fffaf0}
.badge{display:inline-block;padding:3px 10px;border-radius:99px;font-size:12px;font-weight:800}
.b-aktif{background:var(--ok-tint);color:var(--ok)}
.b-dondu{background:#fdeceb;color:#9c2b23}
.b-beklemede{background:var(--amber-tint);color:var(--amber)}
.b-deneme{background:var(--amber-tint);color:var(--amber)}
.b-ucretli{background:var(--primary-tint);color:var(--primary-deep)}
.b-pro{background:#1a1723;color:#e3bd66}.b-lite{background:#eef0f4;color:#5b5a6c}
.kg-yesil{color:var(--ok);font-weight:700}
.kg-amber{color:var(--amber);font-weight:800}
.kg-kirmizi{color:var(--pop-deep);font-weight:800}
.muted{color:var(--soft)}
.mini{font-size:12.5px}
form.inline{display:inline-flex;gap:6px;align-items:center;flex-wrap:wrap;margin:0}
.row-act{display:flex;gap:6px;align-items:center;flex-wrap:wrap;justify-content:flex-end}
input[type=text],input[type=date],input[type=email],input[type=password],input[type=number],select,textarea{padding:8px 10px;border:1.4px solid var(--line);border-radius:8px;font-size:13px;font-family:inherit;background:#fff}
input:focus,select:focus,textarea:focus{outline:0;border-color:var(--primary);box-shadow:0 0 0 3px rgba(51,70,255,.14)}
/* Detay */
.crumb{display:inline-flex;align-items:center;gap:6px;color:var(--soft);text-decoration:none;font-weight:700;font-size:13px;margin-bottom:12px}
.dhead{display:flex;flex-wrap:wrap;align-items:center;gap:10px 14px;margin-bottom:16px}
.dhead h1{margin:0}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.panel{padding:18px}
.panel h3{margin:0 0 4px;font-size:15px}
.panel .desc{margin:0 0 14px;color:var(--soft);font-size:13px}
.field{display:block;margin-bottom:12px}
.field span{display:block;font-size:12px;font-weight:700;color:var(--soft);margin-bottom:5px}
.field input,.field textarea,.field select{width:100%}
.health{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:8px}
.hstat{background:var(--bg);border-radius:11px;padding:12px}
.hstat .n{font-size:20px;font-weight:800}
.hstat .l{font-size:11.5px;color:var(--soft);font-weight:600;margin-top:3px}
.hbar{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:99px;font-weight:700;font-size:13px}
.hbar.ok{background:var(--ok-tint);color:var(--ok)}
.hbar.no{background:#fdeceb;color:#9c2b23}
.danger{border-color:#f3c9c4;background:#fff8f7}
.danger h3{color:var(--pop-deep)}
.two{display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end}
details.acc{border:1px solid var(--line);border-radius:12px;margin-bottom:10px;background:#fff;overflow:hidden}
details.acc>summary{list-style:none;cursor:pointer;padding:13px 16px;font-weight:700;display:flex;justify-content:space-between;align-items:center}
details.acc>summary::-webkit-details-marker{display:none}
details.acc>summary::after{content:"+";color:var(--soft);font-size:18px}
details.acc[open]>summary::after{content:"–"}
details.acc .body{padding:0 16px 16px}
@media(max-width:900px){.kpis{grid-template-columns:repeat(2,1fr)}.grid2{grid-template-columns:1fr}.health{grid-template-columns:repeat(2,1fr)}.hide-sm{display:none}}
@media(max-width:560px){.wrap{padding:14px}.kpis{grid-template-columns:repeat(2,1fr)}}
CSS;

$logo = '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs><linearGradient id="ml" x1="8" y1="10" x2="92" y2="90" gradientUnits="userSpaceOnUse"><stop offset="0" stop-color="#3346ff"/><stop offset=".5" stop-color="#c026d3"/><stop offset="1" stop-color="#ff4433"/></linearGradient></defs><g transform="rotate(-10 50 50)"><path fill-rule="evenodd" clip-rule="evenodd" d="M50 5a45 45 0 1 1 0 90 45 45 0 0 1 0-90Z M50 30c13 0 24.5 8.5 29 20-4.5 11.5-16 20-29 20s-24.5-8.5-29-20c4.5-11.5 16-20 29-20Z" fill="url(#ml)"/></g></svg>';

$detayId = query_int('magaza');
$detay = $detayId ? merkez_magaza($detayId) : null;
$gorunum = $detay ? 'detay' : (in_array(query('gorunum'), ['log', 'saglik', 'yeni', 'rehber', 'seo'], true) ? query('gorunum') : 'liste');

?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $detay ? e($detay['isim']) . ' · ' : '' ?>Merkez panel</title>
<style><?= $stil ?></style>
</head><body>

<div class="topbar"><div class="wrap">
  <a class="brand" href="merkez-panel.php"><?= $logo ?><span>OptiFlow<small>Merkez yönetim</small></span></a>
  <nav class="tb-nav">
    <a class="<?= $gorunum === 'liste' || $gorunum === 'detay' ? 'on' : '' ?>" href="merkez-panel.php">Mağazalar</a>
    <a class="<?= $gorunum === 'saglik' ? 'on' : '' ?>" href="merkez-panel.php?gorunum=saglik">Sağlık</a>
    <a class="<?= $gorunum === 'log' ? 'on' : '' ?>" href="merkez-panel.php?gorunum=log">İşlem günlüğü</a>
    <a class="<?= $gorunum === 'rehber' ? 'on' : '' ?>" href="merkez-panel.php?gorunum=rehber">Rehber</a>
    <a class="<?= $gorunum === 'seo' ? 'on' : '' ?>" href="merkez-panel.php?gorunum=seo">SEO · Google</a>
    <a class="<?= $gorunum === 'yeni' ? 'on' : '' ?>" href="merkez-panel.php?gorunum=yeni">+ Yeni mağaza</a>
  </nav>
  <div class="tb-actions">
    <a class="btn btn-ghost btn-sm" href="merkez-panel.php?disaAktar=1">CSV</a>
    <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="cikis"><button class="btn btn-ghost btn-sm">Çıkış</button></form>
  </div>
</div></div>

<div class="wrap">
<?php foreach (flashes() as [$type, $msg]): ?><div class="flash flash-<?= e($type) ?>"><?= e($msg) ?></div><?php endforeach; ?>
<?php if (!empty($_SESSION['merkez_impersonate'])): $imp = merkez_magaza((int) $_SESSION['merkez_impersonate']); ?>
  <div class="impbar">
    <span>⚠ Şu an <b><?= e($imp['isim'] ?? 'bir mağaza') ?></b> mağazasının paneline girmiş durumdasınız.</span>
    <a class="btn btn-amber btn-sm" href="index.php">Mağaza paneline dön</a>
    <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="cikis"><button class="btn btn-ghost btn-sm">Oturumu kapat</button></form>
  </div>
<?php endif; ?>

<?php if ($detay): /* ==================== DETAY GÖRÜNÜMÜ ==================== */
    $s = merkez_saglik($detay);
    $kg = kalan_gun($detay['deneme_bitis']);
?>
  <a class="crumb" href="merkez-panel.php">← Tüm mağazalar</a>
  <div class="dhead">
    <h1><?= e($detay['isim']) ?></h1>
    <span class="badge b-<?= e($detay['durum']) ?>"><?= e($durumEtiket[$detay['durum']] ?? $detay['durum']) ?></span>
    <span class="badge b-<?= e($detay['plan']) ?>"><?= e($planEtiket[$detay['plan']] ?? $detay['plan']) ?></span>
    <span class="muted mini"><?= e($detay['email']) ?> · Kayıt: <?= e(date_tr($detay['created_at'])) ?><?= $detay['guncelleme'] ? ' · Güncelleme: ' . e(date_tr($detay['guncelleme'])) : '' ?></span>
    <?php if ($detay['durum'] === 'aktif'): ?>
    <form method="post" style="margin:0 0 0 auto" onsubmit="return true">
      <?= csrf_field() ?><input type="hidden" name="action" value="gir"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>">
      <button class="btn btn-primary btn-sm" title="Bu mağazanın paneline destek amacıyla girin">⇥ Mağaza paneline gir</button>
    </form>
    <?php endif; ?>
  </div>

  <!-- Canlı sağlık -->
  <div class="card panel" style="margin-bottom:16px">
    <h3>Canlı durum</h3>
    <p class="desc">Mağazanın kendi veritabanına anlık bağlanılarak okunur.</p>
    <div style="margin-bottom:12px"><span class="hbar <?= $s['ok'] ? 'ok' : 'no' ?>"><?= $s['ok'] ? '● Bağlantı sağlıklı' : '● ' . e($s['mesaj']) ?></span></div>
    <?php if ($s['ok']): ?>
    <div class="health">
      <div class="hstat"><div class="n"><?= $s['siparis'] !== null ? (int) $s['siparis'] : '—' ?></div><div class="l">Sipariş</div></div>
      <div class="hstat"><div class="n"><?= $s['musteri'] !== null ? (int) $s['musteri'] : '—' ?></div><div class="l">Müşteri</div></div>
      <div class="hstat"><div class="n"><?= $s['kullanici'] !== null ? (int) $s['kullanici'] : '—' ?></div><div class="l">Kullanıcı</div></div>
      <div class="hstat"><div class="n"><?= $s['boyut_mb'] !== null ? e((string) $s['boyut_mb']) . ' MB' : '—' ?></div><div class="l">Veritabanı boyutu</div></div>
    </div>
    <p class="muted mini" style="margin:10px 0 0">Son personel girişi: <?= $s['son_giris'] ? e(date_tr($s['son_giris'])) : 'kayıt yok' ?></p>
    <?php endif; ?>
  </div>

  <div class="grid2">
    <!-- Paket (4.11.0) -->
    <div class="card panel">
      <h3>Paket</h3>
      <p class="desc">Mevcut: <b><?= e(PAKETLER[$detay['surum'] ?? 'lite'] ?? 'OptiFlow Lite') ?></b>. Pro özellikler
        (<?= e(implode(', ', array_column(pro_ozellikleri(), 'ad'))) ?>) yalnızca Pro pakette ve OptiFlow Pro masaüstü uygulamasında açılır.</p>
      <form method="post" class="two">
        <?= csrf_field() ?><input type="hidden" name="action" value="surum"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
        <label class="field" style="margin:0"><span>Paket</span>
          <select name="surum"><?php foreach (PAKETLER as $k => $v): ?><option value="<?= e($k) ?>" <?= ($detay['surum'] ?? 'lite') === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select>
        </label>
        <button class="btn btn-primary">Kaydet</button>
      </form>
    </div>

    <!-- Özellikler (4.12.0) -->
    <div class="card panel">
      <h3>Özellikler</h3>
      <p class="desc">Yeni modüller mağaza bazında açılır. İşaretli olmayanlar mağazanın menüsünde görünmez.
        <b>Masaüstü</b> etiketliler yalnızca OptiFlow Pro uygulamasında çalışır.</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="ozellikler"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
        <?php $acikOz = merkez_magaza_ozellikleri($detay); foreach (ozellik_tanimlari() as $ok => $ot): ?>
          <label class="ozellik-satir" style="display:flex;gap:10px;align-items:flex-start;padding:7px 0;border-bottom:1px solid #eee">
            <input type="checkbox" name="ozellik[]" value="<?= e($ok) ?>" <?= in_array($ok, $acikOz, true) ? 'checked' : '' ?> style="margin-top:4px">
            <span><b><?= e($ot['ad']) ?></b><?php if ($ot['masaustu']): ?> <span class="badge b-pro">Masaüstü</span><?php endif; ?><br><span class="muted mini"><?= e($ot['kisa']) ?></span></span>
          </label>
        <?php endforeach; ?>
        <button class="btn btn-primary" style="margin-top:10px">Kaydet</button>
      </form>
    </div>

    <!-- Plan & deneme -->
    <div class="card panel">
      <h3>Plan ve deneme</h3>
      <p class="desc">Mevcut: <b><?= e($planEtiket[$detay['plan']] ?? $detay['plan']) ?></b><?php if ($detay['deneme_bitis']): ?> · bitiş <?= e(date_tr($detay['deneme_bitis'])) ?> <span class="<?= kalan_sinif($kg) ?>">(<?= $kg >= 0 ? $kg . ' gün' : 'doldu' ?>)</span><?php endif; ?></p>
      <form method="post" class="two">
        <?= csrf_field() ?><input type="hidden" name="action" value="plan"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
        <label class="field" style="margin:0"><span>Plan</span>
          <select name="plan"><option value="deneme" <?= $detay['plan'] === 'deneme' ? 'selected' : '' ?>>Deneme</option><option value="ucretli" <?= $detay['plan'] === 'ucretli' ? 'selected' : '' ?>>Ücretli</option></select>
        </label>
        <label class="field" style="margin:0"><span>Deneme bitiş</span><input type="date" name="deneme_bitis" value="<?= e($detay['deneme_bitis'] ?? '') ?>"></label>
        <button class="btn btn-primary">Kaydet</button>
      </form>
      <div style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap">
        <?php foreach ([15, 30, 90] as $g): ?>
        <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="uzat"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="gun" value="<?= $g ?>"><input type="hidden" name="geri" value="detay"><button class="btn btn-ghost btn-sm">+<?= $g ?> gün</button></form>
        <?php endforeach; ?>
        <?php if ($detay['durum'] !== 'beklemede'): ?>
        <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="durum"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
          <?php if ($detay['durum'] === 'aktif'): ?><input type="hidden" name="durum" value="dondu"><button class="btn btn-danger btn-sm">Dondur</button>
          <?php else: ?><input type="hidden" name="durum" value="aktif"><button class="btn btn-ok btn-sm">Aktifleştir</button><?php endif; ?>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Bilgiler -->
    <div class="card panel">
      <h3>Mağaza bilgileri</h3>
      <p class="desc">Ad ve e-posta (giriş e-postası).</p>
      <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="duzenle"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
        <label class="field"><span>Mağaza adı</span><input type="text" name="isim" value="<?= e($detay['isim']) ?>" required maxlength="120"></label>
        <label class="field"><span>E-posta</span><input type="email" name="email" value="<?= e($detay['email']) ?>" required></label>
        <p class="muted mini" style="margin:-4px 0 12px">Yönetici kullanıcı adı: <b><?= e($detay['admin_kullanici']) ?></b> (<?= e($detay['admin_ad']) ?>)</p>
        <button class="btn btn-primary">Kaydet</button>
      </form>
    </div>
  </div>

  <!-- Not -->
  <div class="card panel" style="margin-top:16px">
    <h3>Notlar</h3>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="not"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
      <label class="field"><textarea name="notlar" rows="3" placeholder="Bu mağazayla ilgili notlar (yalnızca siz görürsünüz)…"><?= e($detay['notlar'] ?? '') ?></textarea></label>
      <button class="btn btn-ghost btn-sm">Notu kaydet</button>
    </form>
  </div>

  <!-- Personel kullanıcıları -->
  <?php if ($detay['durum'] !== 'beklemede'): $kullanicilar = merkez_tenant_kullanicilar($detay); ?>
  <div class="card panel" style="margin-top:16px">
    <h3>Personel kullanıcıları</h3>
    <p class="desc">Mağazanın kendi veritabanındaki kullanıcılar. Şifre sıfırlama ve aktif/pasif buradan yapılır.</p>
    <?php if (!$kullanicilar): ?>
      <p class="muted mini">Kullanıcı okunamadı (mağaza veritabanına bağlanılamadı ya da hiç kullanıcı yok).</p>
    <?php else: ?>
    <div style="overflow-x:auto"><table class="utable">
      <thead><tr><th>Ad / kullanıcı</th><th>Rol</th><th>Durum</th><th>Son giriş</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($kullanicilar as $u): ?>
        <tr>
          <td><b><?= e($u['full_name']) ?></b><br><small class="muted"><?= e($u['username']) ?></small></td>
          <td class="mini"><?= $u['role'] === 'super_yetkili' ? 'Süper yetkili' : 'Personel' ?></td>
          <td><span class="badge <?= (int) $u['is_active'] ? 'b-aktif' : 'b-dondu' ?>"><?= (int) $u['is_active'] ? 'Aktif' : 'Pasif' ?></span></td>
          <td class="muted mini"><?= $u['last_login_at'] ? e(date_tr($u['last_login_at'])) : '—' ?></td>
          <td><div class="row-act">
            <details class="acc" style="margin:0;border:0;background:transparent">
              <summary style="padding:4px 0;color:var(--primary-deep);font-size:12.5px">Şifre sıfırla</summary>
              <div class="body" style="padding:8px 0 0">
                <form method="post" class="two">
                  <?= csrf_field() ?><input type="hidden" name="action" value="kul_sifre"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="geri" value="detay">
                  <input type="text" name="yeni_sifre" placeholder="yeni şifre (min 8)" minlength="8" required autocomplete="off" style="width:170px">
                  <button class="btn btn-amber btn-sm">Sıfırla</button>
                </form>
              </div>
            </details>
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="kul_durum"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>"><input type="hidden" name="geri" value="detay">
              <?php if ((int) $u['is_active']): ?><input type="hidden" name="aktif" value="0"><button class="btn btn-ghost btn-sm">Pasifleştir</button>
              <?php else: ?><input type="hidden" name="aktif" value="1"><button class="btn btn-ok btn-sm">Aktifleştir</button><?php endif; ?>
            </form>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Gelişmiş -->
  <div style="margin-top:16px">
    <?php if ($detay['durum'] === 'beklemede'): ?>
    <details class="acc" open><summary>Mağazayı etkinleştir</summary><div class="body">
      <p class="muted mini">Önce Plesk'te bu mağaza için yeni bir veritabanı oluşturun; sonra bilgileri girip etkinleştirin.</p>
      <form method="post" class="two">
        <?= csrf_field() ?><input type="hidden" name="action" value="etkinlestir"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
        <label class="field" style="margin:0"><span>Host</span><input type="text" name="db_host" value="localhost"></label>
        <label class="field" style="margin:0"><span>Veritabanı adı</span><input type="text" name="db_name" required></label>
        <label class="field" style="margin:0"><span>Kullanıcı</span><input type="text" name="db_user" required></label>
        <label class="field" style="margin:0"><span>Şifre</span><input type="password" name="db_sifre" required autocomplete="new-password"></label>
        <label class="field" style="margin:0"><span>Port</span><input type="number" name="db_port" placeholder="3306" style="width:90px"></label>
        <button class="btn btn-ok">Etkinleştir</button>
      </form>
    </div></details>
    <?php else: ?>
    <details class="acc"><summary>Veritabanı bağlantısı</summary><div class="body">
      <p class="muted mini">Mağazanın veritabanı taşındıysa buradan güncelleyin. Kaydetmeden önce bağlantı test edilir.</p>
      <form method="post" class="two">
        <?= csrf_field() ?><input type="hidden" name="action" value="db"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
        <label class="field" style="margin:0"><span>Host</span><input type="text" name="db_host" value="<?= e($detay['db_host'] ?? 'localhost') ?>"></label>
        <label class="field" style="margin:0"><span>Veritabanı adı</span><input type="text" name="db_name" value="<?= e($detay['db_name'] ?? '') ?>" required></label>
        <label class="field" style="margin:0"><span>Kullanıcı</span><input type="text" name="db_user" value="<?= e($detay['db_user'] ?? '') ?>" required></label>
        <label class="field" style="margin:0"><span>Şifre</span><input type="password" name="db_sifre" value="<?= e($detay['db_sifre'] ?? '') ?>" required autocomplete="new-password"></label>
        <button class="btn btn-primary">Test et ve kaydet</button>
      </form>
    </div></details>
    <?php endif; ?>

    <?php if (($detay['durum'] ?? '') !== 'beklemede' && !empty($detay['db_name'])):   /* 4.17.1 eski sistemden veri taşıma */
      $tBekleyen = $_SESSION['tasima'][(int) $detay['id']] ?? null;
      $tGecmis = tasima_gecmis((int) $detay['id']);
      $tSon = $tGecmis[0] ?? null;
      $tGeriAlinabilir = $tSon && ($tSon['tur'] ?? '') === 'tasima';
      $tHedef = [];
      if ($tBekleyen) {
          try { $tHedef = tasima_hedef_sayilari(merkez_tenant_pdo($detay)); } catch (Throwable) { $tHedef = []; }
      }
      $tAdlar = tasima_ozet_tablolari(); ?>
    <details class="acc" id="tasima" <?= $tBekleyen || ($tSon && strtotime((string) ($tSon['zaman'] ?? '')) > time() - 3600) ? 'open' : '' ?>><summary>Eski sistemden veri taşı</summary><div class="body">
      <?php if (!$tBekleyen): ?>
        <p class="muted mini" style="margin-top:0">Eski <b>Poyraz Optik Atölye</b> sisteminin ya da başka bir OptiFlow mağazasının <b>Yedekleme</b> ekranından indirdiğiniz <code>.sql.gz</code> dosyasını yükleyin. Önce bir özet gösterilir; onaylarsanız:</p>
        <ol class="muted mini" style="margin:0 0 12px;padding-left:18px;line-height:1.7">
          <li>mağazanın şu anki verisi otomatik yedeklenir,</li>
          <li>yedekteki müşteri, sipariş, reçete, tahsilat, stok ve kullanıcılar aktarılır,</li>
          <li>veritabanı güncel OptiFlow sürümüne yükseltilir.</li>
        </ol>
        <form method="post" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
          <?= csrf_field() ?><input type="hidden" name="action" value="tasima_yukle"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>">
          <label class="field" style="margin:0"><span>Yedek dosyası (.sql / .sql.gz)</span><input type="file" name="yedek" accept=".sql,.gz,application/gzip,application/sql" required></label>
          <button class="btn btn-primary">Yükle ve önizle</button>
        </form>
        <p class="muted mini" style="margin:10px 0 0">Dosya web'e kapalı klasörde tutulur, 24 saat içinde silinir. Yalnızca yedek komutları (tablo oluşturma ve veri ekleme) kabul edilir.</p>
      <?php else: $tVar = (int) ($tHedef['orders'] ?? 0) + (int) ($tHedef['customers'] ?? 0); ?>
        <p style="margin-top:0"><b><?= e($tBekleyen['ad']) ?></b> · <?= e($tBekleyen['kaynak'] === 'poyraz' ? 'Poyraz Optik Atölye' : 'OptiFlow') ?> <?= e($tBekleyen['surum']) ?> · yedek tarihi <?= e($tBekleyen['tarih']) ?></p>
        <table class="utable" style="max-width:520px;margin-bottom:12px">
          <thead><tr><th style="text-align:left">Kayıt</th><th style="text-align:right">Yedekte</th><th style="text-align:right">Şu an mağazada</th></tr></thead>
          <tbody>
          <?php foreach ($tAdlar as $tk => $tad): ?>
            <tr><td><?= e($tad) ?></td><td style="text-align:right"><b><?= (int) ($tBekleyen['tablolar'][$tk] ?? 0) ?></b></td><td style="text-align:right" class="muted"><?= (int) ($tHedef[$tk] ?? 0) ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php if ($tBekleyen['kaynak'] === 'poyraz'): ?>
          <p class="muted mini">Eski sürüm yedeği: aktarımdan sonra veritabanı baştan güncellenecek (şema <?= (int) $tBekleyen['sema'] ?> → <?= SCHEMA_VERSION ?>). Eski şifreli parolalar korunur; personel eski kullanıcı adı ve şifresiyle girer.</p>
        <?php endif; ?>
        <?php if ($tVar > 0): ?>
          <div class="flash flash-error" style="margin:0 0 12px">Bu mağazada şu an <b><?= (int) ($tHedef['customers'] ?? 0) ?> müşteri ve <?= (int) ($tHedef['orders'] ?? 0) ?> sipariş</b> var. Taşıma bu kayıtları silip yerine yedektekileri yazar. Önce otomatik yedek alınır ve işlem geri alınabilir, ama en güvenlisi boş bir mağazaya taşımaktır.</div>
        <?php endif; ?>
        <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
          <?= csrf_field() ?><input type="hidden" name="action" value="tasima_uygula"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>">
          <label class="field" style="margin:0"><span>Onaylamak için TAŞI yazın</span><input type="text" name="onay" placeholder="TAŞI" autocomplete="off" required style="width:140px"></label>
          <button class="btn btn-ok">Taşımayı başlat</button>
        </form>
        <form method="post" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="tasima_vazgec"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><button class="btn btn-ghost btn-sm">Vazgeç</button></form>
      <?php endif; ?>

      <?php if ($tGecmis): ?>
        <h3 style="margin:18px 0 6px;font-size:14px">Geçmiş</h3>
        <ul class="muted mini" style="margin:0;padding-left:18px;line-height:1.7">
          <?php foreach (array_slice($tGecmis, 0, 5) as $tg): ?>
            <li><?= e(date('d.m.Y H:i', (int) strtotime((string) ($tg['zaman'] ?? '')))) ?> ·
              <?php if (($tg['tur'] ?? '') === 'geri_al'): ?>geri alındı<?php elseif (($tg['durum'] ?? '') === 'basladi'): ?><b style="color:var(--pop-deep)">yarıda kaldı</b><?php else: ?>taşındı (<?= e(($tg['kaynak'] ?? '') === 'poyraz' ? 'Poyraz' : 'OptiFlow') ?> <?= e((string) ($tg['surum'] ?? '')) ?>)<?php endif; ?>
              <?php if (!empty($tg['sayilar'])): ?> · <?= (int) ($tg['sayilar']['customers'] ?? 0) ?> müşteri, <?= (int) ($tg['sayilar']['orders'] ?? 0) ?> sipariş<?php endif; ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if ($tGeriAlinabilir): ?>
          <details style="margin-top:10px"><summary class="muted mini" style="cursor:pointer;font-weight:700">Son taşımayı geri al</summary>
            <p class="muted mini"><?= !empty($tSon['onceki_yedek']) ? 'Taşımadan önce alınan yedek geri yüklenir.' : 'Taşımadan önce mağaza boştu; mağaza veritabanı boşaltılır.' ?></p>
            <form method="post" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
              <?= csrf_field() ?><input type="hidden" name="action" value="tasima_geri_al"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>">
              <label class="field" style="margin:0"><span>GERİ AL yazın</span><input type="text" name="onay" placeholder="GERİ AL" autocomplete="off" required style="width:140px"></label>
              <button class="btn btn-amber">Geri al</button>
            </form>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </div></details>
    <?php endif; ?>

    <details class="acc"><summary>Mağaza giriş şifresini sıfırla</summary><div class="body">
      <p class="muted mini">Mağazanın <b>giriş</b> şifresini (magaza-giris.php) değiştirir. Personel kullanıcı şifreleri ayrıdır.</p>
      <form method="post" class="two">
        <?= csrf_field() ?><input type="hidden" name="action" value="sifre"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>"><input type="hidden" name="geri" value="detay">
        <label class="field" style="margin:0"><span>Yeni giriş şifresi (min 8)</span><input type="text" name="yeni_sifre" minlength="8" required autocomplete="off"></label>
        <button class="btn btn-amber">Şifreyi sıfırla</button>
      </form>
    </div></details>

    <details class="acc danger"><summary>Tehlikeli bölge</summary><div class="body">
      <h3 style="margin:4px 0 6px">Mağaza kaydını sil</h3>
      <p class="muted mini">Bu işlem mağazanın merkez kaydını siler ve geri alınamaz. Mağazanın <b>kendi veritabanı silinmez</b> — gerekiyorsa Plesk'ten ayrıca silmelisiniz. Onaylamak için kutuya <b>SİL</b> yazın.</p>
      <form method="post" class="two">
        <?= csrf_field() ?><input type="hidden" name="action" value="sil"><input type="hidden" name="id" value="<?= (int) $detay['id'] ?>">
        <label class="field" style="margin:0"><span>Onay</span><input type="text" name="onay" placeholder="SİL" autocomplete="off" style="width:120px"></label>
        <button class="btn btn-danger">Kaydı kalıcı sil</button>
      </form>
    </div></details>
  </div>

<?php elseif ($gorunum === 'liste'): /* ==================== LİSTE GÖRÜNÜMÜ ==================== */
    $ist = merkez_istatistik();
    $f = ['q' => query('q'), 'durum' => query('durum'), 'plan' => query('plan'), 'ozel' => query('ozel'), 'sirala' => query('sirala', 'yeni')];
    $liste = merkez_magaza_listele($f);
?>
  <h1>Mağazalar</h1>
  <p class="muted" style="margin:2px 0 16px"><?= (int) $ist['toplam'] ?> mağaza kayıtlı<?= $ist['beklemede'] ? ' · ' . (int) $ist['beklemede'] . ' tanesi etkinleştirme bekliyor' : '' ?></p>

  <div class="kpis">
    <a class="kpi act" href="merkez-panel.php"><div class="n"><?= (int) $ist['toplam'] ?></div><div class="l">Toplam mağaza</div></a>
    <a class="kpi" href="merkez-panel.php?durum=aktif"><div class="n"><?= (int) $ist['aktif'] ?></div><div class="l">Aktif</div></a>
    <a class="kpi <?= $ist['beklemede'] ? 'hot' : '' ?>" href="merkez-panel.php?durum=beklemede"><div class="n"><?= (int) $ist['beklemede'] ?></div><div class="l">Beklemede</div></a>
    <a class="kpi" href="merkez-panel.php?durum=dondu"><div class="n"><?= (int) $ist['dondu'] ?></div><div class="l">Donduruldu</div></a>
    <a class="kpi" href="merkez-panel.php?plan=ucretli"><div class="n"><?= (int) $ist['ucretli'] ?></div><div class="l">Ücretli abone</div></a>
    <a class="kpi" href="merkez-panel.php?plan=deneme"><div class="n"><?= (int) $ist['deneme'] ?></div><div class="l">Denemede</div></a>
    <div class="kpi"><div class="n"><?= (int) ($ist['pro'] ?? 0) ?></div><div class="l">Pro paket</div></div>
    <a class="kpi <?= $ist['bitiyor'] ? 'hot' : '' ?>" href="merkez-panel.php?ozel=bitiyor"><div class="n"><?= (int) $ist['bitiyor'] ?></div><div class="l">7 günde bitiyor</div></a>
    <a class="kpi <?= $ist['dolmus'] ? 'red' : '' ?>" href="merkez-panel.php?ozel=dolmus"><div class="n"><?= (int) $ist['dolmus'] ?></div><div class="l">Denemesi dolmuş</div></a>
  </div>

  <form class="toolbar" method="get">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Ad, e-posta, veritabanı ya da kullanıcı ara…">
    <select name="durum"><option value="">Tüm durumlar</option>
      <?php foreach ($durumEtiket as $k => $v): ?><option value="<?= $k ?>" <?= $f['durum'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
    </select>
    <select name="plan"><option value="">Tüm planlar</option>
      <?php foreach ($planEtiket as $k => $v): ?><option value="<?= $k ?>" <?= $f['plan'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
    </select>
    <select name="sirala">
      <option value="yeni" <?= $f['sirala'] === 'yeni' ? 'selected' : '' ?>>En yeni</option>
      <option value="eski" <?= $f['sirala'] === 'eski' ? 'selected' : '' ?>>En eski</option>
      <option value="isim" <?= $f['sirala'] === 'isim' ? 'selected' : '' ?>>Ada göre</option>
      <option value="bitis" <?= $f['sirala'] === 'bitis' ? 'selected' : '' ?>>Deneme bitişine göre</option>
    </select>
    <button class="btn btn-primary btn-sm">Uygula</button>
    <?php if ($f['q'] || $f['durum'] || $f['plan'] || $f['ozel']): ?><a class="chip" href="merkez-panel.php">Temizle ✕</a><?php endif; ?>
  </form>

  <form id="bulkform" method="post" class="bulkbar" style="display:flex">
    <?= csrf_field() ?><input type="hidden" name="action" value="toplu">
    <span style="font-weight:700">Seçili mağazalara:</span>
    <select name="toplu_eylem">
      <option value="uzat30">Denemeyi +30 gün uzat</option>
      <option value="dondur">Dondur</option>
      <option value="aktif">Aktifleştir</option>
      <option value="plan_ucretli">Planı Ücretli yap</option>
      <option value="plan_deneme">Planı Deneme yap</option>
      <option value="surum_pro">Paketi Pro yap</option>
      <option value="surum_lite">Paketi Lite yap</option>
      <optgroup label="Özellik aç">
        <?php foreach (ozellik_tanimlari() as $ok => $ot): ?><option value="ozellik_ac:<?= e($ok) ?>"><?= e($ot['ad']) ?> — aç</option><?php endforeach; ?>
      </optgroup>
      <optgroup label="Özellik kapat">
        <?php foreach (ozellik_tanimlari() as $ok => $ot): ?><option value="ozellik_kapat:<?= e($ok) ?>"><?= e($ot['ad']) ?> — kapat</option><?php endforeach; ?>
      </optgroup>
    </select>
    <button class="btn btn-ghost btn-sm" style="background:#fff">Uygula</button>
    <span class="muted mini" style="color:#c9c8dd">Satırlardaki kutuları işaretleyip uygulayın.</span>
  </form>

  <?php $cronUrl = (function_exists('app_base_url') ? rtrim(app_base_url(), '/') : '') . '/cron.php?anahtar=' . cron_anahtar(); ?>
  <details class="card" style="padding:12px 16px;margin-bottom:12px">
    <summary style="cursor:pointer;font-weight:700">Zamanlanmış görev (WhatsApp otomatik gönderim, ödeme linkleri)</summary>
    <p class="muted mini" style="margin:8px 0">Plesk › Web Siteleri › Zamanlanmış Görevler › <b>URL getir</b>, her 10 dakikada bir. Bu adres gizlidir; paylaşmayın.</p>
    <input readonly value="<?= e($cronUrl) ?>" style="width:100%;font-family:monospace" aria-label="Zamanlanmış görev adresi" data-cron-url>
  </details>

  <div class="card" style="overflow-x:auto">
  <table>
    <thead><tr><th class="selc"></th><th>Mağaza</th><th class="hide-sm">E-posta</th><th class="hide-sm">Veritabanı</th><th>Plan</th><th>Paket</th><th>Deneme bitiş</th><th>Durum</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($liste as $m): $kg = kalan_gun($m['deneme_bitis']); ?>
      <tr class="<?= $m['durum'] === 'beklemede' ? 'bekle' : '' ?>">
        <td class="selc"><input class="selbox" type="checkbox" form="bulkform" name="sec[]" value="<?= (int) $m['id'] ?>" aria-label="Seç"></td>
        <td><a href="merkez-panel.php?magaza=<?= (int) $m['id'] ?>" style="font-weight:800;text-decoration:none;color:var(--ink)"><?= e($m['isim']) ?></a><br><small class="muted"><?= e($m['admin_kullanici']) ?></small></td>
        <td class="muted hide-sm"><?= e($m['email']) ?></td>
        <td class="muted hide-sm mini"><?= e($m['db_name'] ?? '—') ?></td>
        <td><span class="badge b-<?= e($m['plan']) ?>"><?= e($planEtiket[$m['plan']] ?? $m['plan']) ?></span></td>
        <td><span class="badge b-<?= ($m['surum'] ?? 'lite') === 'pro' ? 'pro' : 'lite' ?>"><?= ($m['surum'] ?? 'lite') === 'pro' ? 'Pro' : 'Lite' ?></span></td>
        <td><?php if ($m['deneme_bitis']): ?><?= e(date_tr($m['deneme_bitis'])) ?> <small class="<?= kalan_sinif($kg) ?>">(<?= $kg >= 0 ? $kg . 'g' : 'doldu' ?>)</small><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><span class="badge b-<?= e($m['durum']) ?>"><?= e($durumEtiket[$m['durum']] ?? $m['durum']) ?></span></td>
        <td><div class="row-act">
          <?php if ($m['durum'] === 'beklemede'): ?>
            <a class="btn btn-ok btn-sm" href="merkez-panel.php?magaza=<?= (int) $m['id'] ?>">Etkinleştir →</a>
          <?php else: ?>
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="uzat"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="gun" value="30"><button class="btn btn-ghost btn-sm" title="Denemeyi 30 gün uzat">+30g</button></form>
            <form method="post" style="margin:0"><?= csrf_field() ?><input type="hidden" name="action" value="durum"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <?php if ($m['durum'] === 'aktif'): ?><input type="hidden" name="durum" value="dondu"><button class="btn btn-danger btn-sm">Dondur</button>
              <?php else: ?><input type="hidden" name="durum" value="aktif"><button class="btn btn-ok btn-sm">Aktif</button><?php endif; ?>
            </form>
            <a class="btn btn-ghost btn-sm" href="merkez-panel.php?magaza=<?= (int) $m['id'] ?>">Detay</a>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$liste): ?><tr><td colspan="8" class="muted" style="padding:24px;text-align:center">Eşleşen mağaza yok.</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>

<?php elseif ($gorunum === 'saglik'): /* ==================== SAĞLIK TARAMASI ==================== */
    $hepsi = merkez_magaza_listele(['sirala' => 'isim']);
?>
  <h1>Sağlık taraması</h1>
  <p class="muted" style="margin:2px 0 16px">Her aktif mağazanın veritabanına anlık bağlanılarak kontrol edilir. Çok mağazada birkaç saniye sürebilir.</p>
  <div class="card" style="overflow-x:auto"><table>
    <thead><tr><th>Mağaza</th><th>Bağlantı</th><th>Sipariş</th><th>Müşteri</th><th>Kullanıcı</th><th>Boyut</th><th>Son giriş</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($hepsi as $m): $s = merkez_saglik($m); ?>
      <tr>
        <td><a href="merkez-panel.php?magaza=<?= (int) $m['id'] ?>" style="font-weight:700;text-decoration:none;color:var(--ink)"><?= e($m['isim']) ?></a></td>
        <td><?php if ($m['durum'] === 'beklemede'): ?><span class="badge b-beklemede">Beklemede</span><?php elseif ($s['ok']): ?><span class="badge b-aktif">● Sağlıklı</span><?php else: ?><span class="badge b-dondu">● Erişilemedi</span><?php endif; ?></td>
        <td><?= $s['siparis'] !== null ? (int) $s['siparis'] : '—' ?></td>
        <td><?= $s['musteri'] !== null ? (int) $s['musteri'] : '—' ?></td>
        <td><?= $s['kullanici'] !== null ? (int) $s['kullanici'] : '—' ?></td>
        <td class="mini"><?= $s['boyut_mb'] !== null ? e((string) $s['boyut_mb']) . ' MB' : '—' ?></td>
        <td class="muted mini"><?= $s['son_giris'] ? e(date_tr($s['son_giris'])) : '—' ?></td>
        <td><a class="btn btn-ghost btn-sm" href="merkez-panel.php?magaza=<?= (int) $m['id'] ?>">Detay</a></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$hepsi): ?><tr><td colspan="8" class="muted" style="padding:20px;text-align:center">Mağaza yok.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

<?php elseif ($gorunum === 'log'): /* ==================== İŞLEM GÜNLÜĞÜ ==================== */
    $loglar = merkez_audit_listele(100);
?>
  <h1>İşlem günlüğü</h1>
  <p class="muted" style="margin:2px 0 16px">Merkez panelde yapılan son 100 işlem. En yeni üstte.</p>
  <div class="card" style="overflow-x:auto"><table>
    <thead><tr><th>Zaman</th><th>İşlem</th><th>Mağaza</th><th>Ayrıntı</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($loglar as $l): ?>
      <tr>
        <td class="mini muted"><?= e(date_tr($l['created_at'])) ?></td>
        <td><b><?= e(merkez_eylem_etiket($l['eylem'])) ?></b></td>
        <td class="mini"><?php if ($l['magaza_id'] && merkez_magaza((int) $l['magaza_id'])): ?><a href="merkez-panel.php?magaza=<?= (int) $l['magaza_id'] ?>"><?= e($l['magaza_isim'] ?? ('#' . $l['magaza_id'])) ?></a><?php else: ?><?= e($l['magaza_isim'] ?? '—') ?><?php endif; ?></td>
        <td class="mini muted"><?= e($l['detay'] ?? '') ?></td>
        <td class="mini muted"><?= e($l['ip']) ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$loglar): ?><tr><td colspan="5" class="muted" style="padding:20px;text-align:center">Henüz kayıt yok.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

<?php elseif ($gorunum === 'rehber'): /* ==================== REHBER (SEO) ==================== */
  $tum = rehber_hepsi(false);
  $secSlug = query('yazi');
  $yeni = query('yeni') === '1';
  $sec = $secSlug !== '' ? ($tum[$secSlug] ?? null) : null;
  $duzen = $sec ?: ($yeni ? ['slug' => '', 'baslik' => '', 'seo_baslik' => '', 'meta' => '', 'hedef_kelime' => '', 'govde' => '', 'yayinda' => false] : null);
?>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <h1>Rehber yazıları</h1>
      <p class="muted" style="margin:2px 0 0">Sitede <a href="rehber.php" target="_blank" rel="noopener">optiflow.com.tr/rehber.php</a> altında yayınlanır, site haritasına kendiliğinden eklenir. Aramadaki sonuçları <a href="merkez-panel.php?gorunum=seo">SEO · Google</a> ekranında izleyin.</p>
    </div>
    <a class="btn btn-primary" href="merkez-panel.php?gorunum=rehber&amp;yeni=1">+ Yeni yazı</a>
  </div>

  <?php if ($duzen !== null): ?>
  <div class="card panel" style="margin-bottom:18px">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="rehber_kaydet">
      <input type="hidden" name="eski_slug" value="<?= e($sec['slug'] ?? '') ?>">
      <div class="grid2">
        <label class="field"><span>Başlık (sayfadaki H1)</span><input type="text" name="baslik" required maxlength="140" value="<?= e($duzen['baslik']) ?>"></label>
        <label class="field"><span>Adres (slug) — boşsa başlıktan üretilir</span><input type="text" name="slug" maxlength="80" pattern="[a-z0-9-]*" value="<?= e($duzen['slug']) ?>" placeholder="gozlukcu-programi-secerken"></label>
      </div>
      <div class="grid2">
        <label class="field"><span>Google başlığı (en fazla 60 karakter önerilir)</span><input type="text" name="seo_baslik" maxlength="70" value="<?= e($duzen['seo_baslik']) ?>"></label>
        <label class="field"><span>Hedef anahtar kelime</span><input type="text" name="hedef_kelime" maxlength="80" value="<?= e($duzen['hedef_kelime']) ?>"></label>
      </div>
      <label class="field"><span>Google açıklaması (meta, en fazla 155 karakter önerilir)</span><textarea name="meta" rows="2" maxlength="170"><?= e($duzen['meta']) ?></textarea></label>
      <label class="field"><span>Yazı gövdesi — HTML (izinli: h2, h3, p, ul, ol, li, strong, em, a, blockquote, table). SEO panosundaki taslağı buraya yapıştırabilirsiniz.</span><textarea name="govde" rows="18" style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:12.5px" required><?= e($duzen['govde']) ?></textarea></label>
      <label style="display:flex;gap:8px;align-items:center;font-weight:700;margin:4px 0 14px"><input type="checkbox" name="yayinda" value="1" <?= !empty($duzen['yayinda']) ? 'checked' : '' ?>> Yayında (sitede görünsün)</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap">
        <button class="btn btn-primary">Kaydet</button>
        <a class="btn btn-ghost" href="merkez-panel.php?gorunum=rehber">Vazgeç</a>
        <?php if ($sec && !empty($sec['yayinda'])): ?><a class="btn btn-ghost" href="rehber.php?y=<?= e($sec['slug']) ?>" target="_blank" rel="noopener">Sitede gör ↗</a><?php endif; ?>
      </div>
    </form>
    <?php if ($sec): ?>
    <details style="margin-top:16px"><summary class="muted" style="cursor:pointer;font-weight:700">Yazıyı sil</summary>
      <form method="post" style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
        <?= csrf_field() ?><input type="hidden" name="action" value="rehber_sil"><input type="hidden" name="slug" value="<?= e($sec['slug']) ?>">
        <input type="text" name="onay" placeholder="SİL yazın" autocomplete="off" style="max-width:140px">
        <button class="btn btn-danger btn-sm">Kalıcı sil</button>
      </form>
    </details>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="card" style="overflow-x:auto"><table class="utable">
    <thead><tr><th style="text-align:left">Yazı</th><th style="text-align:left">Hedef kelime</th><th style="text-align:left">Durum</th><th style="text-align:left">Tarih</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tum as $y): ?>
      <tr>
        <td><a href="merkez-panel.php?gorunum=rehber&amp;yazi=<?= e($y['slug']) ?>" style="font-weight:800;text-decoration:none;color:var(--ink)"><?= e($y['baslik']) ?></a><br><small class="muted">rehber.php?y=<?= e($y['slug']) ?><?= !empty($y['_pakette']) ? ' · paketle geldi' : '' ?></small></td>
        <td class="muted"><?= e($y['hedef_kelime']) ?></td>
        <td><?= !empty($y['yayinda']) ? '<span class="badge" style="background:var(--ok-tint);color:var(--ok)">Yayında</span>' : '<span class="badge" style="background:var(--amber-tint);color:var(--amber)">Taslak</span>' ?></td>
        <td class="muted"><?= e($y['yayin_tarihi'] ?: $y['guncelleme']) ?></td>
        <td><div class="row-act">
          <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="rehber_yayin"><input type="hidden" name="slug" value="<?= e($y['slug']) ?>"><input type="hidden" name="yayinda" value="<?= !empty($y['yayinda']) ? '0' : '1' ?>">
            <button class="btn btn-sm <?= !empty($y['yayinda']) ? 'btn-ghost' : 'btn-ok' ?>"><?= !empty($y['yayinda']) ? 'Yayından kaldır' : 'Yayına al' ?></button></form>
          <a class="btn btn-ghost btn-sm" href="merkez-panel.php?gorunum=rehber&amp;yazi=<?= e($y['slug']) ?>">Düzenle</a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$tum): ?><tr><td colspan="5" class="muted" style="padding:20px;text-align:center">Henüz yazı yok.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

<?php elseif ($gorunum === 'seo'): /* ==================== SEO · GOOGLE ==================== */
  $sa = seo_ayar();
  $anahtar = seo_anahtar_bilgi();
  $veri = seo_onbellek_oku(true);
  $mulkler = $_SESSION['seo_mulkler'] ?? ($veri['mulkler'] ?? null);
  $ucNokta = rtrim($sa['site_url'], '/') . '/seo-veri.php?t=' . $sa['token'];
  $adimTamam = [
      'anahtar' => $anahtar !== null,
      'mulk'    => $veri !== null && $veri['gsc'] !== null,
      'dogrula' => $sa['google_dogrulama'] !== '' || ($veri !== null && $veri['gsc'] !== null),
  ];
  $puanRenk = static fn(?int $p): string => $p === null ? 'var(--soft)' : ($p >= 90 ? 'var(--ok)' : ($p >= 50 ? 'var(--amber)' : 'var(--pop-deep)'));
  $sayi = static fn($n): string => number_format((float) $n, 0, ',', '.');
?>
  <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:12px;flex-wrap:wrap;margin-bottom:14px">
    <div>
      <h1>SEO · Google bağlantısı</h1>
      <p class="muted" style="margin:2px 0 0">Search Console arama verisi ve PageSpeed hız ölçümü bu ekranda görünür. Hasta ya da mağaza verisi Google'a gönderilmez.</p>
    </div>
    <?php if ($anahtar): ?>
    <form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0">
      <?= csrf_field() ?><input type="hidden" name="action" value="seo_yenile">
      <label class="muted" style="display:flex;gap:6px;align-items:center;font-weight:700;font-size:13px"><input type="checkbox" name="psi" value="1" checked> Hız ölçümü de (≈30 sn)</label>
      <button class="btn btn-primary">Google'dan verileri al</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($veri && ($veri['gsc'] || $veri['psi'])): $g = $veri['gsc']; $p = $veri['psi']; ?>
  <p class="muted" style="margin:0 0 8px;font-size:13px">Son veri: <?= e(date('d.m.Y H:i', (int) strtotime((string) $veri['uretildi']))) ?><?= $g ? ' · ' . e(date('d.m', (int) strtotime($g['aralik'][0]))) . '–' . e(date('d.m.Y', (int) strtotime($g['aralik'][1]))) . ' (son 28 gün)' : '' ?> · <?= e($veri['site']) ?></p>
  <div class="kpis">
    <div class="kpi act"><div class="n"><?= $g ? $sayi($g['toplam']['tiklama']) : '—' ?></div><div class="l">Tıklama (28 gün)</div></div>
    <div class="kpi"><div class="n"><?= $g ? $sayi($g['toplam']['gosterim']) : '—' ?></div><div class="l">Gösterim</div></div>
    <div class="kpi"><div class="n"><?= $g ? e(str_replace('.', ',', (string) $g['toplam']['to'])) . '%' : '—' ?></div><div class="l">Tıklama oranı</div></div>
    <div class="kpi"><div class="n" style="color:<?= $puanRenk($p['performans'] ?? null) ?>"><?= $p ? (int) $p['performans'] : '—' ?><small style="font-size:14px;color:var(--soft)"> / <?= $p ? (int) $p['seo'] : '—' ?></small></div><div class="l">Mobil hız / SEO puanı</div></div>
  </div>

  <div class="grid2" style="align-items:start;margin-bottom:18px">
    <div class="card" style="overflow-x:auto">
      <div style="padding:14px 16px 4px;font-weight:800">Arama sorguları</div>
      <table class="utable">
        <thead><tr><th style="text-align:left">Sorgu</th><th>Tık</th><th>Gösterim</th><th>Sıra</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($g['sorgular'] ?? [], 0, 15) as $s):
            $fark = $s['onceki_sira'] !== null ? round($s['onceki_sira'] - $s['sira'], 1) : null; ?>
          <tr><td><?= e($s['sorgu']) ?></td><td style="text-align:center"><?= (int) $s['tiklama'] ?></td><td style="text-align:center"><?= $sayi($s['gosterim']) ?></td>
            <td style="text-align:center;white-space:nowrap"><?= e(str_replace('.', ',', (string) $s['sira'])) ?><?php if ($fark !== null && abs($fark) >= 0.5): ?> <small style="color:<?= $fark > 0 ? 'var(--ok)' : 'var(--pop-deep)' ?>;font-weight:800"><?= $fark > 0 ? '▲' : '▼' ?><?= e(str_replace('.', ',', (string) abs($fark))) ?></small><?php endif; ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($g['sorgular'])): ?><tr><td colspan="4" class="muted" style="padding:18px;text-align:center"><?= $g ? 'Henüz arama verisi yok. Yeni mülklerde ilk veri 2–7 günde gelir.' : 'Search Console verisi alınamadı.' ?></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card" style="overflow-x:auto">
      <div style="padding:14px 16px 4px;font-weight:800">Sayfalar</div>
      <table class="utable">
        <thead><tr><th style="text-align:left">Sayfa</th><th>Tık</th><th>Gösterim</th><th>Sıra</th></tr></thead>
        <tbody>
        <?php foreach (array_slice($g['sayfalar'] ?? [], 0, 15) as $s): $yol = (string) (parse_url($s['sayfa'], PHP_URL_PATH) ?: '/') . (parse_url($s['sayfa'], PHP_URL_QUERY) ? '?' . parse_url($s['sayfa'], PHP_URL_QUERY) : ''); ?>
          <tr><td><a href="<?= e($s['sayfa']) ?>" target="_blank" rel="noopener" style="color:var(--ink)"><?= e($yol) ?></a></td><td style="text-align:center"><?= (int) $s['tiklama'] ?></td><td style="text-align:center"><?= $sayi($s['gosterim']) ?></td><td style="text-align:center"><?= e(str_replace('.', ',', (string) $s['sira'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (empty($g['sayfalar'])): ?><tr><td colspan="4" class="muted" style="padding:18px;text-align:center">—</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <?php if ($p): ?>
  <div class="card panel" style="margin-bottom:18px">
    <div style="font-weight:800;margin-bottom:8px">Mobil hız ölçümü (PageSpeed) · <?= e($veri['sayfa']) ?></div>
    <div style="display:flex;gap:18px;flex-wrap:wrap;margin-bottom:10px">
      <?php foreach (['performans' => 'Performans', 'erisilebilirlik' => 'Erişilebilirlik', 'en_iyi_uygulama' => 'En iyi uygulama', 'seo' => 'SEO'] as $k => $ad): ?>
        <div><b style="font-size:22px;color:<?= $puanRenk($p[$k]) ?>"><?= $p[$k] === null ? '—' : (int) $p[$k] ?></b> <span class="muted" style="font-size:13px"><?= e($ad) ?></span></div>
      <?php endforeach; ?>
    </div>
    <p class="muted" style="margin:0 0 8px;font-size:13px">LCP <?= e((string) ($p['lcp'] ?? '—')) ?> · CLS <?= e((string) ($p['cls'] ?? '—')) ?> · TBT <?= e((string) ($p['tbt'] ?? '—')) ?> · FCP <?= e((string) ($p['fcp'] ?? '—')) ?></p>
    <?php if ($p['sorunlar']): ?>
    <details><summary class="muted" style="cursor:pointer;font-weight:700"><?= count($p['sorunlar']) ?> iyileştirme önerisi</summary>
      <ul style="margin:8px 0 0;padding-left:18px;font-size:13.5px"><?php foreach ($p['sorunlar'] as $s): ?><li><?= e($s['baslik']) ?><?= $s['deger'] ? ' <span class="muted">(' . e((string) $s['deger']) . ')</span>' : '' ?></li><?php endforeach; ?></ul>
    </details>
    <?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>

  <div class="card panel" style="margin-bottom:18px">
    <div style="font-weight:800;margin-bottom:4px">Bağlantı ayarları</div>
    <p class="muted" style="margin:0 0 14px;font-size:13.5px">Bir kez yapılır. Adımlar: <a href="https://github.com/can2821998-creator/optiflow/blob/main/docs/SEO-SEARCH-CONSOLE.md" target="_blank" rel="noopener">docs/SEO-SEARCH-CONSOLE.md</a></p>

    <ol style="margin:0 0 16px;padding-left:20px;line-height:1.7;font-size:14px">
      <li><?= $adimTamam['anahtar'] ? '✅' : '⬜' ?> Google Cloud'da hizmet hesabı açıp <b>JSON anahtarını</b> aşağıdan yükleyin (Search Console API etkin olmalı).</li>
      <li><?= $adimTamam['mulk'] ? '✅' : '⬜' ?> Search Console › Ayarlar › <b>Kullanıcılar ve izinler</b> › Kullanıcı ekle: hizmet hesabı e-postası, izin <b>Kısıtlı</b>.
        <?php if ($anahtar): ?><br><code style="user-select:all;background:#f2f2fa;padding:3px 7px;border-radius:6px;font-size:13px"><?= e($anahtar['e_posta']) ?></code><?php endif; ?></li>
      <li><?= $adimTamam['mulk'] ? '✅' : '⬜' ?> Aşağıda mülkü seçip <b>Google'dan verileri al</b>'a basın.</li>
    </ol>

    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="seo_kaydet">
      <div class="grid2">
        <label class="field"><span>Google hizmet hesabı anahtarı (.json)<?= $anahtar ? ' — yüklü: ' . e($anahtar['e_posta']) : '' ?></span><input type="file" name="anahtar" accept=".json,application/json"></label>
        <label class="field"><span>Search Console mülkü</span>
          <input type="text" name="gsc_site" list="seo-mulkler" value="<?= e($sa['gsc_site']) ?>" required>
          <datalist id="seo-mulkler"><option value="sc-domain:optiflow.com.tr"><option value="https://optiflow.com.tr/"><?php foreach ((array) $mulkler as $m): ?><option value="<?= e($m['site']) ?>"><?php endforeach; ?></datalist>
        </label>
      </div>
      <?php if (is_array($mulkler)): ?>
        <p class="muted" style="margin:-4px 0 12px;font-size:13px"><?= $mulkler ? 'Hizmet hesabının gördüğü mülkler: ' . e(implode(', ', array_map(static fn($m) => $m['site'], $mulkler))) : '⚠ Hizmet hesabı henüz hiçbir mülkü görmüyor: 2. adımı yapın (eklemeden sonra birkaç dakika sürebilir).' ?></p>
      <?php endif; ?>
      <p class="muted" style="margin:-4px 0 12px;font-size:12.5px">"Alan adı" mülkü için <code>sc-domain:optiflow.com.tr</code>, "URL ön eki" mülkü için <code>https://optiflow.com.tr/</code> yazın.</p>
      <div class="grid2">
        <label class="field"><span>Hız ölçümü yapılacak sayfa</span><input type="text" inputmode="url" name="site_url" value="<?= e($sa['site_url']) ?>"></label>
        <label class="field"><span>PageSpeed API anahtarı (isteğe bağlı<?= $sa['psi_api_key'] !== '' ? ' — kayıtlı ••••' . e(substr($sa['psi_api_key'], -4)) : '' ?>)</span><input type="password" name="psi_api_key" autocomplete="off" placeholder="<?= $sa['psi_api_key'] !== '' ? 'değiştirmek için yazın' : 'kota hatası alırsanız girin' ?>"></label>
      </div>
      <div class="grid2">
        <label class="field"><span>Google doğrulama kodu (HTML etiketi yöntemi — gerekirse)</span><input type="text" name="google_dogrulama" value="<?= e($sa['google_dogrulama']) ?>" placeholder='<meta name="google-site-verification" content="…"> ya da yalnızca kod'></label>
        <label class="field"><span>Bing doğrulama kodu (msvalidate.01 — gerekirse)</span><input type="text" name="bing_dogrulama" value="<?= e($sa['bing_dogrulama']) ?>" placeholder="isteğe bağlı"></label>
      </div>
      <p class="muted" style="margin:-4px 0 12px;font-size:12.5px">Doğrulama kodları yalnızca tanıtım sayfası ve rehberin <code>&lt;head&gt;</code> bölümüne eklenir; panel sayfalarına eklenmez.</p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <button class="btn btn-primary">Kaydet</button>
        <?php if ($sa['psi_api_key'] !== ''): ?><label class="muted" style="display:flex;gap:6px;align-items:center;font-size:13px"><input type="checkbox" name="psi_api_key_sil" value="1"> PageSpeed anahtarını kaldır</label><?php endif; ?>
      </div>
    </form>

    <?php if ($anahtar): ?>
    <details style="margin-top:16px"><summary class="muted" style="cursor:pointer;font-weight:700">Google anahtarını sil</summary>
      <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="seo_anahtar_sil"><button class="btn btn-danger btn-sm">Anahtarı sil</button>
      <span class="muted" style="font-size:12.5px">Google Cloud'daki anahtarı da devre dışı bırakmayı unutmayın.</span></form>
    </details>
    <?php endif; ?>
  </div>

  <div class="card panel">
    <div style="font-weight:800;margin-bottom:4px">Veri uç noktası (otomatik raporlar için)</div>
    <?php if (strlen($sa['token']) >= 24): ?>
      <p class="muted" style="margin:0 0 8px;font-size:13.5px">Günlük SEO raporu ya da başka bir araç bu adresten JSON okur. Adresi yalnızca güvendiğiniz yerde paylaşın.</p>
      <code style="display:block;user-select:all;background:#f2f2fa;padding:9px 11px;border-radius:8px;font-size:12.5px;word-break:break-all"><?= e($ucNokta) ?></code>
      <form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="seo_token"><button class="btn btn-ghost btn-sm">Erişim anahtarını yenile</button></form>
    <?php else: ?>
      <p class="muted" style="margin:0;font-size:13.5px">Ayarları bir kez kaydedince erişim anahtarı kendiliğinden üretilir.</p>
    <?php endif; ?>
  </div>

<?php elseif ($gorunum === 'yeni'): /* ==================== YENİ MAĞAZA ==================== */ ?>
  <a class="crumb" href="merkez-panel.php">← Tüm mağazalar</a>
  <h1>Yeni mağaza oluştur</h1>
  <p class="muted" style="margin:2px 0 16px">Sunucu yeni veritabanı açmaya izin veriyorsa mağaza anında aktif olur; vermiyorsa "beklemede" olarak eklenir ve veritabanını girip etkinleştirirsiniz.</p>
  <div class="card panel" style="max-width:640px">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="olustur">
      <div class="grid2">
        <label class="field"><span>Mağaza adı</span><input type="text" name="isim" required maxlength="120"></label>
        <label class="field"><span>E-posta (giriş)</span><input type="email" name="email" required></label>
      </div>
      <label class="field"><span>Mağaza giriş şifresi (min 8)</span><input type="text" name="giris_sifre" minlength="8" required autocomplete="off"></label>
      <hr style="border:0;border-top:1px solid var(--line);margin:8px 0 16px">
      <p class="desc" style="margin:0 0 12px">İlk yönetici (mağaza içi personel girişi):</p>
      <div class="grid2">
        <label class="field"><span>Yönetici adı</span><input type="text" name="admin_ad" maxlength="120"></label>
        <label class="field"><span>Yönetici kullanıcı adı</span><input type="text" name="admin_kullanici" required></label>
      </div>
      <label class="field"><span>Yönetici şifresi (min 8)</span><input type="text" name="admin_sifre" minlength="8" required autocomplete="off"></label>
      <button class="btn btn-primary">Mağazayı oluştur</button>
    </form>
  </div>
<?php endif; ?>
</div>
</body></html>
