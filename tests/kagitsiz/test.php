<?php
/* 4.32.0 Kâğıtsız tezgâh (app/kagitsiz.php): gün sonu depo listesi, WhatsApp metni, gönderim ve geri alma. */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';
if (!function_exists('wa_url')) {
    require dirname(__DIR__, 2) . '/app/reminders.php';
}
require_once dirname(__DIR__, 2) . '/app/kagitsiz.php';

// gerçek tablodaki numara sütunları (test düzeneğinde yok)
foreach (['lens_value TEXT NULL', 'sph TEXT NULL', 'cyl TEXT NULL', 'axis TEXT NULL', 'add_power TEXT NULL'] as $s) {
    try {
        db()->exec("ALTER TABLE prescription_lens_items ADD COLUMN $s");
    } catch (Throwable $e) {
        // zaten var
    }
}
setting_set('shop_name', 'Poyraz Optik');

echo "1) Numara biçimi\n";
esit('−2,00', depo_numara('-2'), 'eksi tam sayı');
esit('+1,25', depo_numara('1.25'), 'artı ondalık');
esit('−0,50', depo_numara('-0,50'), 'virgüllü giriş');
esit('0,00', depo_numara('0'), 'sıfır');
esit('', depo_numara(''), 'boş');
esit('PLAN', depo_numara('PLAN'), 'sayı olmayan olduğu gibi');

echo "2) Eksik camlar ve gruplama\n";
$s1 = insert('orders', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'order_stage' => 'alindi']);
$s2 = insert('orders', ['first_name' => 'Ali', 'last_name' => 'Kaya', 'order_stage' => 'alindi']);
$s3 = insert('orders', ['first_name' => 'İptal', 'last_name' => 'Edilen', 'order_stage' => 'iptal']);
$r1 = insert('prescription_records', ['order_id' => $s1]);
$r2 = insert('prescription_records', ['order_id' => $s2]);
$r3 = insert('prescription_records', ['order_id' => $s3]);
$k = static fn(int $rx, string $tur, string $sph, string $cyl, string $st = 'stokta_yok', string $eye = 'R', string $grup = 'uzak') =>
    insert('prescription_lens_items', ['prescription_id' => $rx, 'lens_type' => $tur, 'sph' => $sph, 'cyl' => $cyl, 'axis' => '90', 'stock_status' => $st, 'eye' => $eye, 'item_group' => $grup]);
$a = $k($r1, '1.50 HMC', '-2.00', '-0.50');
$b = $k($r1, '1.50 HMC', '-2.00', '-0.50', 'stokta_yok', 'L');
$c = $k($r2, '1.50 HMC', '+1.25', '');
$d = $k($r2, '1.67 Blue', '-4.00', '-1.00');
$e = $k($r2, '1.50 HMC', '-1.00', '', 'stokta_var');      // stokta: listeye girmez
$f = $k($r3, '1.50 HMC', '-3.00', '');                     // iptal sipariş: girmez
$g = $k($r1, '1.50 HMC', '+2.50', '', 'stokta_yok', 'R', 'yakin');
$liste = depo_eksik_kalemler();
esit(5, count($liste), 'yalnız eksik ve iptal olmayan camlar');
$gr = depo_gruplari($liste);
esit('1.50 HMC', $gr[0]['tur'], 'en kalabalık tür önce');
esit(4, $gr[0]['adet'], '1.50 HMC adedi');
esit(['−2,00 −0,50', 2], [$gr[0]['satirlar'][0]['numara'], $gr[0]['satirlar'][0]['adet']], 'aynı numara birleşir, eksiden artıya');
ok(in_array('+2,50 (yakın)', array_column($gr[0]['satirlar'], 'numara'), true), 'yakın cam işaretlenir');
esit('1.67 Blue', $gr[1]['tur'], 'ikinci tür');

echo "3) WhatsApp metni\n";
$metin = depo_metni($gr);
ok(str_starts_with($metin, 'Poyraz Optik — cam siparişi ' . date('d.m.Y')), 'başlık');
ok(str_contains($metin, "1.50 HMC (4)\n  −2,00 −0,50  × 2"), 'tür başlığı ve adet');
ok(str_contains($metin, 'Toplam 5 cam.'), 'toplam');
foreach (['Ayşe', 'Yılmaz', 'Ali', 'Kaya'] as $isim) {
    ok(!str_contains($metin, $isim), "müşteri adı yok: $isim");
}
ok(!str_contains($metin, '90'), 'aks yazılmaz');

echo "4) Varsayılan depo\n";
esit(null, depo_tedarikci(), 'tedarikçi yoksa null');
$t1 = insert('suppliers', ['name' => 'Merkez Depo', 'phone' => '0532 111 22 33', 'is_active' => 1]);
esit($t1, (int) (depo_tedarikci()['id'] ?? 0), 'telefonu olan tek tedarikçi kendiliğinden');
$t2 = insert('suppliers', ['name' => 'İkinci Depo', 'phone' => '0532 999 88 77', 'is_active' => 1]);
esit(null, depo_tedarikci(), 'iki tedarikçi varsa seçim gerekir');
setting_set('depo_tedarikci_id', (string) $t2);
esit($t2, (int) (depo_tedarikci()['id'] ?? 0), 'ayardaki depo');

echo "5) Gönder ve geri al\n";
$son = depo_gonder();
esit(5, $son['adet'], '5 cam gönderildi');
ok(str_starts_with($son['url'], 'https://wa.me/905329998877?text='), 'depo numarasına WhatsApp: ' . substr($son['url'], 0, 40));
esit(0, (int) scalar("SELECT COUNT(*) FROM prescription_lens_items WHERE stock_status = 'stokta_yok' AND id IN ($a,$b,$c,$d,$g)"), 'eksikler sipariş verildi oldu');
esit($t2, (int) scalar('SELECT supplier_id FROM prescription_lens_items WHERE id = ?', [$a]), 'tedarikçi yazıldı');
ok((string) scalar('SELECT ordered_at FROM prescription_lens_items WHERE id = ?', [$a]) !== '', 'sipariş zamanı yazıldı');
esit('stokta_var', (string) scalar('SELECT stock_status FROM prescription_lens_items WHERE id = ?', [$e]), 'stoktaki cama dokunulmadı');
esit('stokta_yok', (string) scalar('SELECT stock_status FROM prescription_lens_items WHERE id = ?', [$f]), 'iptal siparişin camına dokunulmadı');
esit(0, count(depo_eksik_kalemler()), 'liste boşaldı');
esit(0, depo_gonder()['adet'], 'ikinci gönderim boş');
ok(depo_son_gonderim() !== null, 'son gönderim kayıtlı');
q("UPDATE prescription_lens_items SET stock_status = 'stokta_var' WHERE id = ?", [$d]);   // biri bu arada geldi
esit(4, depo_geri_al(), 'geri al: gelmiş cam hariç');
esit('stokta_yok', (string) scalar('SELECT stock_status FROM prescription_lens_items WHERE id = ?', [$a]), 'cam yeniden eksik');
esit('stokta_var', (string) scalar('SELECT stock_status FROM prescription_lens_items WHERE id = ?', [$d]), 'gelmiş cam korunur');
esit(null, depo_son_gonderim(), 'geri alınca kayıt silinir');
esit(0, depo_geri_al(), 'ikinci geri alma etkisiz');
setting_set('depo_son_gonderim', (string) json_encode(['ids' => [$a], 'at' => time() - (DEPO_GERI_AL_SAAT * 3600 + 5)]));
esit(null, depo_son_gonderim(), 'süresi geçen gönderim geri alınamaz');

echo "6) Numarasız depo\n";
setting_set('depo_tedarikci_id', '0');
q('UPDATE suppliers SET phone = NULL');
$son = depo_gonder();
ok(str_starts_with($son['url'], 'https://wa.me/?text='), 'numara yoksa WhatsApp kişi seçtirir');

echo "7) Kaynak denetimi\n";
$kaynak = (string) file_get_contents(dirname(__DIR__, 2) . '/app/kagitsiz.php');
ok(!preg_match('/NOW\(\)|INTERVAL/', $kaynak), 'taşınabilir SQL (NOW/INTERVAL yok)');
$zarf = (string) file_get_contents(dirname(__DIR__, 2) . '/app/partials/zarf-formu.php');
ok(!preg_match('/<script(?![^>]*src=)/i', $zarf) && !preg_match('/\son[a-z]+=/i', $zarf), 'zarf formunda satır içi betik yok (CSP)');
ok(str_contains($zarf, 'can_see_amounts()'), 'tutarlar yetkiye bağlı');
$index = (string) file_get_contents(dirname(__DIR__, 2) . '/app/pages/index.php');
ok(str_contains($index, "ozellik_acik('kagitsiz')"), 'ana ekran kartı özellik anahtarına bağlı');

bitir();
