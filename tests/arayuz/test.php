<?php
/* 4.20.2 arayüz kayması ve merkez bağlantı düzeltmeleri — kaynak denetimleri (ağ ve veritabanı kullanmaz). */
declare(strict_types=1);
$gecen = 0;
$kalan = 0;
function dogru(string $ad, bool $k): void
{
    global $gecen, $kalan;
    if ($k) {
        $gecen++;
    } else {
        $kalan++;
        echo "  ✗ $ad\n";
    }
}
$kok = dirname(__DIR__, 2);
$css = (string) file_get_contents($kok . '/assets/app.css');

/* Açılır menü (sipariş › WhatsApp): bant içinde kesilmez, beyaz yazıyı devralmaz */
dogru('menü listesi kendi yazı rengini taşır', (bool) preg_match('/\.menu-list \{[^}]*color: var\(--ink\)/', $css));
dogru('bant, açık menüde taşmaya izin verir', str_contains($css, '.hero:has(.menu[open]) { overflow: visible'));
dogru('bant içindeki menü sola hizalı', str_contains($css, '.hero-actions .menu-list { left: 0; right: auto; }'));

/* Telefon düzeni tema katmanından SONRA gelmeli (yoksa tema katmanı ezer) */
$tema = strpos($css, '.card { border-radius: var(--radius); border: 1px solid var(--line); box-shadow: var(--shadow); padding: 26px 28px; }');
$tel = strpos($css, '4.20.1 — Telefon düzeni');
$koyu = strpos($css, '4.20.0 — KOYU GÖRÜNÜM');
dogru('telefon düzeni tema katmanından sonra, koyu görünümden önce', $tema !== false && $tel !== false && $tel > $tema && $tel < $koyu);
dogru('telefonda liste tablosu: avatar gizli, rozet kırılabilir', str_contains($css, '.table .cell-link > .avatar { display: none; }') && str_contains($css, '.table td .badge { white-space: normal;'));
dogru('telefonda kart dolgusu ile tablo kenar payı aynı (16px)', (bool) preg_match('/\.card \{ padding: 18px 16px;[^}]*\}\s*\.table-wrap \{ margin: 0 -16px; padding: 0 16px; \}/', $css));

/* Hata sayfası koyu görünümü izler */
dogru('hata sayfası tema.js yükler', str_contains((string) file_get_contents($kok . '/app/helpers.php'), 'assets/tema.js?v='));

/* Merkez: mağaza veritabanı portu ve adı */
$mg = (string) file_get_contents($kok . '/app/merkez.php');
dogru('mağaza bağlantılarında port atılmıyor', !str_contains($mg, "'port' => null") && substr_count($mg, 'magaza_db_ayari(') >= 4);
dogru('db_port sütunu ve kaydı', str_contains($mg, "'magazalar', 'db_port'") && str_contains($mg, 'db_port = ?'));
dogru('taşıma da aynı bağlantı ayarını kullanır', str_contains((string) file_get_contents($kok . '/app/tasima.php'), 'db_baglanti_degistir(magaza_db_ayari($m))'));
dogru('veritabanı adında büyük Türkçe harfler küçültülür', str_contains($mg, "mb_strtolower(strtr(\$isim, ['İ' => 'i', 'I' => 'ı']))"));

/* 4.20.3 PWA: mağaza girişinden kurulum, manifest oturumsuz, çevrimdışı ekranı */
$bs = (string) file_get_contents($kok . '/app/bootstrap.php');
dogru('manifest oturumsuz da açılır (veritabanı seçilmez)', str_contains($bs, "=== 'manifest.php' && !tenant_oturum()") && str_contains($bs, '!$anaSayfaMisafir && !$manifestMisafir'));
$mf = (string) file_get_contents($kok . '/manifest.php');
dogru('manifest: mağaza adı yalnızca mağaza oturumunda okunur', str_contains($mf, 'if (tenant_oturum()) {'));
dogru('manifest: açılış rengi tema rengiyle aynı', str_contains($mf, "'background_color'  => '#141012'") && str_contains($mf, "'theme_color'       => '#141012'"));
dogru('manifest: ortak önbelleğe alınmaz', str_contains($mf, 'Cache-Control: private'));
foreach (['telefon.webp', 'liste.webp', 'atolye.webp'] as $g) {
    dogru("manifest ekran görüntüsü var: $g", str_contains($mf, 'assets/onizleme/' . $g) && is_file($kok . '/assets/onizleme/' . $g));
}
$mgz = (string) file_get_contents($kok . '/app/pages/magaza-giris.php');
dogru('mağaza girişi: manifest + iPhone simgesi + pwa.js', str_contains($mgz, 'rel="manifest"') && str_contains($mgz, 'apple-touch-icon') && str_contains($mgz, "asset('pwa.js')"));
$off = (string) file_get_contents($kok . '/offline.html');
dogru('çevrimdışı ekranı: satır içi betik/olay yok (CSP)', !preg_match('/<script>|onclick=/i', $off) && str_contains($off, 'assets/offline.js') && str_contains($off, 'assets/tema.js'));
dogru('çevrimdışı ekranı: koyu görünümü uygulama seçimiyle izler', str_contains($off, 'html.tema-koyu{'));
$sw = (string) file_get_contents($kok . '/sw.js');
dogru('servis çalışanı çevrimdışı betiklerini önceden saklar', str_contains($sw, "'assets/offline.js'") && str_contains($sw, "'assets/tema.js'"));
dogru('servis çalışanı ağ yokken tüm önbelleklere bakar', str_contains($sw, '(await caches.match(istek))'));

/* 4.20.3 Telefonda çevrimdışı kopya (cevrimdisi_tel) */
$oz = (string) file_get_contents($kok . '/app/ozellik.php');
dogru('özellik anahtarı cevrimdisi_tel (masaüstü gerekmez)', (bool) preg_match("/'cevrimdisi_tel' => \[[^\]]*'masaustu' => false/s", $oz));
$api = (string) file_get_contents($kok . '/app/pages/api.php');
dogru('uç nokta özellik kapalıyken 403', (bool) preg_match("/action === 'cevrimdisi'.*?ozellik_acik\('cevrimdisi_tel'\).*?http_response_code\(403\)/s", $api));
dogru('uç nokta masaüstü özetiyle aynı içerik + 24 saat', str_contains($api, "'gecerlilik_sn' => 86400] + cevrimdisi_ozet("));
dogru('sayfa işareti: yalnızca girişli + özellik açıkken 1', str_contains((string) file_get_contents($kok . '/app/layout.php'), "data-cevrimdisi=\"<?= current_user() && ozellik_acik('cevrimdisi_tel') ? '1' : '0' ?>\""));
$pwa = (string) file_get_contents($kok . '/assets/pwa.js');
dogru('kopya dışa aktarılamaz anahtarla AES-GCM şifrelenir', str_contains($pwa, "generateKey({ name: 'AES-GCM', length: 256 }, false,") && str_contains($pwa, 'crypto.subtle.encrypt('));
dogru('işaretsiz/kapalı sayfada (giriş, çıkış sonrası) kopya silinir', str_contains($pwa, "if (isaret === '1') kopyaKaydet();") && str_contains($pwa, 'else kopyaSil();'));
$ojs = (string) file_get_contents($kok . '/assets/offline.js');
dogru('süresi dolan kopya gösterilmez ve silinir', str_contains($ojs, 'Date.now() > k.kopya.bitis) { kopyaSil(); return; }'));
dogru('kopya içeriği HTML olarak yorumlanmaz (textContent)', !str_contains($ojs, '.innerHTML =') && str_contains($ojs, 'el.textContent = metin'));
dogru('çevrimdışı ekranı körlemesine yenilemez, sunucuyu yoklar', str_contains($ojs, "fetch('manifest.php', { cache: 'no-store' })") && !str_contains($ojs, 'if (navigator.onLine) location.reload()'));
dogru('servis çalışanı api.php\'ye dokunmaz', str_contains($sw, "url.pathname.endsWith('/api.php')) return;"));

/* 4.20.3 Telefonda kullanım turu bulguları */
$css = (string) file_get_contents($kok . '/assets/app.css');
dogru('telefonda sticky öğeler çalışır (body overflow-x: clip)', str_contains($css, 'html, body { max-width: 100%; overflow-x: hidden; overflow-x: clip; }'));
dogru('dokunmatikte küçük düğme/çip/sekme 40 px', (bool) preg_match('/@media \(pointer: coarse\) \{\s*\.btn-sm \{ min-height: 40px; \}\s*\.chip, \.tab \{ min-height: 40px; \}/', $css));
dogru('kaydet çubuğu: ana düğme tam genişlik', str_contains($css, '.sticky-actions .btn-primary { flex: 1 1 100%; order: -1; }'));
dogru('sipariş başlığı düğmeleri yazısından dar sıkışmaz', str_contains($css, '.hero-actions > * { flex: 1 1 auto; min-width: max-content; }') && str_contains($css, '.hero-actions > .btn { width: auto; }'));
dogru('atölye istatistikleri telefonda yana kayan şerit', str_contains($css, '.pano-stats > .stat { flex: 0 0 44%;'));
dogru('mağaza telefonu alanı telefon klavyesi açar', str_contains((string) file_get_contents($kok . '/app/pages/settings.php'), 'name="shop_phone" type="tel" inputmode="tel"'));

echo "Arayüz/merkez düzeltme testleri: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
