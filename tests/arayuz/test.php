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

echo "Arayüz/merkez düzeltme testleri: $gecen geçti, $kalan kaldı\n";
exit($kalan ? 1 : 0);
