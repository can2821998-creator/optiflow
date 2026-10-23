<?php
/* 4.44.0 Zarfsız atölye (app/atolye.php): adım parafı, aşama etkisi, kutu / raf, geri alma. */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';
require_once dirname(__DIR__, 2) . '/app/atolye.php';

// migrate_v36_atolye_adimlari ile aynı sütunlar + orders'ın atölye sütunları
$p = db();
foreach (['workshop_stage TEXT NULL', 'assigned_to INTEGER NULL', 'own_frame_pending INTEGER NOT NULL DEFAULT 0', 'qc_by INTEGER NULL', 'qc_at TEXT NULL',
          'status TEXT NULL', 'updated_at TEXT NULL', 'raf_yeri TEXT NULL'] as $sutun) {
    $p->exec('ALTER TABLE orders ADD COLUMN ' . $sutun);
}
$p->exec('CREATE TABLE atolye_adimlari (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, adim TEXT NOT NULL, user_id INTEGER NULL,
    created_at TEXT NOT NULL, UNIQUE (order_id, adim))');
$p->exec("UPDATE user_accounts SET full_name = 'Deniz Yılmaz' WHERE id = 1");
$p->exec("UPDATE user_accounts SET full_name = 'Ali Can Kaya' WHERE id = 2");

$yeni = static function (array $alan = []): array {
    $id = insert('orders', $alan + ['order_stage' => 'atolyede', 'workshop_stage' => 'montajda']);
    return row('SELECT * FROM orders WHERE id = ?', [$id]);
};
$tazele = static fn(array $o): array => row('SELECT * FROM orders WHERE id = ?', [(int) $o['id']]);
$hata = static function (callable $f): string {
    try {
        $f();
    } catch (DomainException $e) {
        return $e->getMessage();
    }
    return '';
};

echo "1) Kutu / raf yazımı ve boş kutu önerisi\n";
esit('RAF A-3', atolye_raf('  raf   a-3 '), 'boşluk sadeleşir, büyük harf');
esit('VİTRİN', atolye_raf('vitrin'), 'Türkçe i → İ (tam)');
esit(20, mb_strlen(atolye_raf(str_repeat('x', 40))), 'en çok 20 karakter');
esit('', atolye_raf('   '), 'boş');
$yeni(['raf_yeri' => '1']);
$yeni(['raf_yeri' => '3', 'order_stage' => 'hazirlandi', 'workshop_stage' => null]);
$yeni(['raf_yeri' => '2', 'order_stage' => 'teslim_edildi', 'workshop_stage' => null]);
$yeni(['raf_yeri' => 'VİTRİN']);
esit(2, atolye_bos_kutu(), 'teslim edilmiş işin kutusu (2) boş sayılır; 1 ve 3 dolu');
esit(4, atolye_bos_kutu([2]), 'aynı ekranda önerilen 2 atlanır');

echo "2) Sıradaki adım ve aşama akışı\n";
$o = $yeni();
esit('kesim', atolye_siradaki($o, []), 'montajda, hiç adım yok → kesim');
ok(str_contains($hata(static fn() => atolye_adim_at($o, 'kontrol', 1, '5')), 'montajın'), 'montajsız kontrol reddedilir');
esit('kayit', atolye_adim_at($o, 'kesim', 1), 'kesim yalnız paraf');
ok(str_contains($hata(static fn() => atolye_adim_at($tazele($o), 'kesim', 2)), 'zaten işaretlenmiş (DY)'), 'aynı adım ikinci kez atılmaz, parafı söylenir');
$o = $tazele($o);
esit('montaj', atolye_siradaki($o, atolye_adimlari((int) $o['id'])), 'kesimden sonra montaj');
esit('kontrole', atolye_adim_at($o, 'montaj', 2), 'montaj → kontrole');
$o = $tazele($o);
esit(['atolyede', 'kontrol', 2], [$o['order_stage'], $o['workshop_stage'], (int) $o['assigned_to']], 'aşama kontrol, atanmamış iş montajcıya yazılır');
ok(str_contains($hata(static fn() => atolye_adim_at($o, 'kontrol', 1, '  ')), 'kutu / raf'), 'raf yazılmadan hazır olmaz');
esit('hazir', atolye_adim_at($o, 'kontrol', 1, 'raf b2'), 'kontrol → hazır');
$o = $tazele($o);
esit(['hazirlandi', null, 1, 'RAF B2'], [$o['order_stage'], $o['workshop_stage'], (int) $o['qc_by'], $o['raf_yeri']], 'hazır, kontrol eden ve raf yazıldı');
ok($o['qc_at'] !== null, 'kontrol saati');
esit('haber', atolye_siradaki($o, atolye_adimlari((int) $o['id'])), 'hazırda sıradaki: müşteriye haber');
esit('kayit', atolye_adim_at($o, 'haber', 2), 'haber verildi');
esit('', atolye_siradaki($o, atolye_adimlari((int) $o['id'])), 'atölyede iş kalmadı');

echo "3) Paraf\n";
$a = atolye_adimlari((int) $o['id']);
esit(['kesim', 'montaj', 'kontrol', 'haber'], array_keys($a), 'adımlar atılış sırasıyla');
esit(['DY', 'AK'], [$a['kesim']['paraf'], $a['montaj']['paraf']], 'paraf: ilk ve son ad baş harfi (Ali Can Kaya → AK)');
ok((bool) preg_match('/^DY · \d\d:\d\d$/', atolye_damga($a['kesim'])), 'bugünkü damga: "DY · 14:20"');
esit('DY · 21.10 09:05', atolye_damga(['paraf' => 'DY', 'saat' => '2026-10-21 09:05:00']), 'eski gün tarihli');
$toplu = atolye_adimlari_toplu([(int) $o['id'], 999]);
esit(4, count($toplu[(int) $o['id']] ?? []), 'toplu okuma');
esit([], atolye_adimlari_toplu([]), 'boş liste sorgusuz');

echo "4) Geri alma\n";
ok(str_contains($hata(static fn() => atolye_adim_geri_al($tazele($o), 'kontrol', 1, true)), 'son atılan'), 'son olmayan adım geri alınmaz');
ok(str_contains($hata(static fn() => atolye_adim_geri_al($tazele($o), 'haber', 1, false)), 'başka biri'), 'başkasının parafını personel geri alamaz');
atolye_adim_geri_al($tazele($o), 'haber', 2, false);
atolye_adim_geri_al($tazele($o), 'kontrol', 1, false);
$o = $tazele($o);
esit(['atolyede', 'kontrol', null], [$o['order_stage'], $o['workshop_stage'], $o['qc_by']], 'kontrol geri → kontrolde, kontrol eden silinir');
esit('RAF B2', $o['raf_yeri'], 'raf korunur');
atolye_adim_geri_al($o, 'montaj', 1, true);
esit('montajda', $tazele($o)['workshop_stage'], 'montaj geri (yönetici) → montajda');

echo "5) Kurallar\n";
$c = $yeni(['own_frame_pending' => 1]);
atolye_adim_at($c, 'kesim', 1);
ok(str_contains($hata(static fn() => atolye_adim_at($tazele($c), 'montaj', 1)), 'çerçevesi'), 'müşteri çerçevesi gelmeden montaj yok');
$h = $yeni(['order_stage' => 'bekliyor', 'workshop_stage' => null]);
ok(str_contains($hata(static fn() => atolye_adim_at($h, 'kesim', 1)), 'montaj aşamasında değil'), 'cam bekleyen işte kesim yok');
ok(str_contains($hata(static fn() => atolye_adim_at($h, 'haber', 1)), 'hazır olunca'), 'hazır olmayan işe haber yok');
ok(str_contains($hata(static fn() => atolye_adim_at($h, 'uydurma', 1)), 'Bilinmeyen'), 'bilinmeyen adım');
atolye_adim_kaydet((int) $h['id'], 'montaj', 1);
atolye_adim_kaydet((int) $h['id'], 'montaj', 2);
esit(1, (int) scalar("SELECT COUNT(*) FROM atolye_adimlari WHERE order_id = ? AND adim = 'montaj'", [(int) $h['id']]), 'sürükle-bırak izi tek kayıt');
esit('K7', atolye_raf_kaydet((int) $h['id'], ' k7 '), 'raf kaydı');
atolye_raf_kaydet((int) $h['id'], '');
ok($tazele($h)['raf_yeri'] === null, 'boş raf NULL olur');

echo "6) Kaynak bağlantıları\n";
$kok = dirname(__DIR__, 2);
$mig = (string) file_get_contents($kok . '/app/migrations.php');
ok((bool) preg_match('/const SCHEMA_VERSION = (3[6-9]|[4-9]\d);/', $mig) && str_contains($mig, 'migrate_v36_atolye_adimlari()') && str_contains($mig, "add_column('orders', 'raf_yeri'"), 'göç v36');
ok(str_contains((string) file_get_contents($kok . '/app/bootstrap.php'), "require __DIR__ . '/atolye.php';"), 'modül yüklenir');
$sip = (string) file_get_contents($kok . '/app/pages/order.php');
ok(str_contains($sip, "['atolye_adim', 'atolye_geri', 'raf_kaydet']") && str_contains($sip, "/partials/atolye-adimlar.php"), 'sipariş sayfası: iş kartı ve eylemler');
ok(str_contains($sip, "atolye_adim_kaydet(\$id, 'kontrol'") && str_contains($sip, "atolye_adim_kaydet(\$id, 'montaj'"), 'sürükle-bırak da paraf izi bırakır');
$pano = (string) file_get_contents($kok . '/app/pages/workshop.php');
ok(str_contains($pano, 'atolye_adimlari_toplu(') && str_contains($pano, 'wf_adim_formu(') && !str_contains($pano, 'data-form="move"'), 'pano: paraf, sıradaki adım, gizli form yok');
$js = (string) file_get_contents($kok . '/assets/workshop.js');
ok(str_contains($js, "['action', 'workshop_move']") && str_contains($js, 'raf.reportValidity'), 'sürükle-bırak formu kurar, hazırda raf ister');
ok(str_contains((string) file_get_contents($kok . '/app/partials/siparis-fisi.php'), 'class="ie-kutu"'), 'iş emrinde kutu no');

bitir();
