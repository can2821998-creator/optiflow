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

echo "Arayüz/merkez düzeltme testleri: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
