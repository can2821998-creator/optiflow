<?php
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';

/* 4.16.1 — SGK ay sonu toplu faturası:
   • hasta faturasında SGK payı düşülür, SGK'ya sipariş başına fatura AÇILMAZ;
   • reçete "Medula'ya işlendi" işaretlenir, işlendiği ayın faturasına girer;
   • ay sonunda tek fatura + reçete dökümü, aynı reçete iki kez faturalanmaz;
   • Medula PDF dökümü okunup OptiFlow işaretleriyle karşılaştırılır. */
function find_order(int $id): ?array
{
    return row("SELECT o.*, c.first_name AS c_first, c.last_name AS c_last, c.phone AS c_phone FROM orders o LEFT JOIN customers c ON c.id = o.customer_id WHERE o.id = ?", [$id]);
}
function transaction_type_label(?string $k): string { return $k === 'gunes_gozlugu' ? 'Güneş gözlüğü satışı' : 'Gözlük siparişi'; }

setting_set('fatura_kdv', '10');
$mus = insert('customers', ['first_name' => 'Ayşe', 'last_name' => 'Yılmaz', 'phone' => '05321234567']);
$mus2 = insert('customers', ['first_name' => 'Mehmet', 'last_name' => 'Demir', 'phone' => '05329876543']);
$sip = static function (array $v) use ($mus): int {
    return insert('orders', $v + ['customer_id' => $mus, 'transaction_type' => 'gozluk', 'order_stage' => 'teslim_edildi', 'total_amount' => 1000]);
};
$eylul1  = $sip(['sgk_amount' => 150, 'medula_islendi_at' => '2026-09-05 10:00:00', 'sgk_erecete' => '1A2B3C4']);
$eylul2  = $sip(['sgk_amount' => 150, 'medula_islendi_at' => '2026-09-28 16:00:00', 'sgk_erecete' => '5D6E7F8', 'customer_id' => $mus2, 'order_stage' => 'hazirlandi']);
$agustos = $sip(['sgk_amount' => 150, 'medula_islendi_at' => '2026-08-30 12:00:00']);   // önceki aydan faturalanmamış
$ekim    = $sip(['sgk_amount' => 150, 'medula_islendi_at' => '2026-10-02 09:00:00']);   // sonraki ay: girmez
$islenmedi = $sip(['sgk_amount' => 150, 'delivered_at' => '2026-09-15 10:00:00']);      // teslim edilmiş ama Medula işaretsiz
$sgksiz  = $sip(['sgk_amount' => 0, 'medula_islendi_at' => '2026-09-10 10:00:00']);
$iptal   = $sip(['sgk_amount' => 150, 'order_stage' => 'iptal', 'medula_islendi_at' => '2026-09-11 10:00:00']);
$gunes   = $sip(['sgk_amount' => 200, 'medula_islendi_at' => '2026-09-12 10:00:00', 'transaction_type' => 'gunes_gozlugu']);

echo "1) Sipariş faturası yalnızca hasta payı\n";
$ids = fatura_siparisten_taslak($eylul1);
esit(1, count($ids), 'sipariş başına TEK taslak (SGK için ayrı taslak yok)');
$f = row('SELECT * FROM faturalar WHERE id = ?', [$ids[0]]);
ok($f['alici_tip'] === 'kisi' && (float) $f['genel_toplam'] === 850.0, 'hasta faturası 1000 − 150 = 850');
ok(str_contains((string) $f['notlar'], 'ay sonu toplu SGK faturasına'), 'notta SGK payının ay sonu faturaya gireceği yazar');
esit(0, (int) scalar("SELECT COUNT(*) FROM faturalar WHERE alici_tip = 'kurum'"), 'SGK\'ya sipariş bazında fatura açılmadı');
$tam = $sip(['sgk_amount' => 1000, 'medula_islendi_at' => '2026-09-20 10:00:00']);
hata_bekle(fn() => fatura_siparisten_taslak($tam), 'tamamı SGK\'lı siparişte hasta faturası yok', 'Hasta payı yok');
$ids2 = fatura_siparisten_taslak($eylul2, false);
esit(1000.0, (float) scalar('SELECT genel_toplam FROM faturalar WHERE id = ?', [$ids2[0]]), 'SGK düşülmeden de kesilebilir');

echo "2) Medula'ya işlendi işareti\n";
esit([$islenmedi], array_map('intval', array_column(sgk_medula_bekleyenler(), 'id')), 'işaretsiz SGK\'lı sipariş bekleyenlerde (SGK\'sız ve iptal yok)');
hata_bekle(fn() => sgk_medula_isaretle($sgksiz), 'SGK\'sız sipariş işaretlenmez', 'SGK reçetesi yok');
hata_bekle(fn() => sgk_medula_isaretle($iptal), 'iptal sipariş işaretlenmez', 'İptal');
hata_bekle(fn() => sgk_medula_isaretle($islenmedi, date('Y-m-d', strtotime('+2 days'))), 'ileri tarih olmaz', 'ileri');
hata_bekle(fn() => sgk_medula_isaretle($islenmedi, '2026-02-30'), 'geçersiz tarih', 'geçersiz');
sgk_medula_isaretle($islenmedi, '2026-09-15');
esit('2026-09-15 12:00:00', scalar('SELECT medula_islendi_at FROM orders WHERE id = ?', [$islenmedi]), 'geçmiş tarihle işaretlendi');
esit(1, (int) scalar('SELECT medula_islendi_by FROM orders WHERE id = ?', [$islenmedi]), 'işaretleyen kaydedildi');
esit([], sgk_medula_bekleyenler(), 'bekleyen kalmadı');
sgk_medula_geri_al($islenmedi);
esit(null, scalar('SELECT medula_islendi_at FROM orders WHERE id = ?', [$islenmedi]), 'geri alındı');
sgk_medula_isaretle($islenmedi);
ok(str_starts_with((string) scalar('SELECT medula_islendi_at FROM orders WHERE id = ?', [$islenmedi]), date('Y-m-d')), 'tarihsiz → bugün');
q("UPDATE orders SET medula_islendi_at = '2026-09-15 12:00:00' WHERE id = ?", [$islenmedi]);

echo "3) Dönem adayları (Medula işlem ayına göre)\n";
$aday = fatura_sgk_donem_siparisleri('2026-09');
$adayId = array_map('intval', array_column($aday, 'id'));
sort($adayId);
$bek = [$eylul1, $eylul2, $agustos, $islenmedi, $gunes, $tam];
sort($bek);
esit($bek, $adayId, 'eylül + önceki aydan kalan; ekim, SGK\'sız ve iptal girmez; teslim şartı yok');
$a = array_column($aday, null, 'id');
ok($a[$agustos]['onceki'] === 1 && $a[$eylul1]['onceki'] === 0, 'önceki ay işareti');
esit(20.0, $a[$gunes]['kdv'], 'güneş gözlüğü KDV %20');
esit('2026-09', fatura_sgk_ay('2026-09'), 'ay doğrulama');
esit(date('Y-m', strtotime('first day of last month')), fatura_sgk_ay('2026-13'), 'geçersiz ay → geçen ay');
esit('Eylül 2026', fatura_sgk_ay_adi('2026-09'), 'ay adı');

echo "4) Tek toplu fatura\n";
hata_bekle(fn() => fatura_sgk_donem_taslagi('2026-09', []), 'boş seçim', 'seçin');
hata_bekle(fn() => fatura_sgk_donem_taslagi('2026-09', [$ekim]), 'dönem dışı sipariş eklenemez', 'eklenemez');
hata_bekle(fn() => fatura_sgk_donem_taslagi('2026-09', [$eylul1, $gunes], 900.0), 'karışık KDV\'de Medula toplamı yok', 'KDV');
$fid = fatura_sgk_donem_taslagi('2026-09', [$eylul1, $eylul2, $agustos, $gunes]);
$f = row('SELECT * FROM faturalar WHERE id = ?', [$fid]);
ok($f['alici_tip'] === 'kurum' && $f['alici_kimlik'] === '7750409379' && $f['profil'] === 'TEMELFATURA' && $f['sgk_donem'] === '2026-09', 'SGK alıcılı, dönemli tek fatura');
esit(650.0, (float) $f['genel_toplam'], 'genel toplam 150×3 + 200');
$satir = rows('SELECT * FROM fatura_satirlari WHERE fatura_id = ? ORDER BY sira', [$fid]);
esit(2, count($satir), 'KDV oranına göre iki satır');
ok(str_contains($satir[0]['ad'], 'Eylül 2026') && str_contains($satir[0]['ad'], '3 reçete'), 'satır adı: dönem + reçete sayısı');
esit(4, count(fatura_sgk_dokum($fid)), 'dökümde 4 reçete');
hata_bekle(fn() => sgk_medula_geri_al($eylul1), 'faturadaki reçetenin işareti kaldırılamaz', 'faturasında');
esit([$islenmedi, $tam], array_map('intval', array_column(fatura_sgk_donem_siparisleri('2026-09'), 'id')), 'faturalanan reçeteler tekrar listelenmez');
hata_bekle(fn() => fatura_sgk_donem_taslagi('2026-09', [$eylul1]), 'aynı reçete iki kez faturalanamaz', 'eklenemez');
esit(1, count(fatura_sgk_donem_faturalari('2026-09')), 'dönem faturası listesi');

echo "5) İptal ve Medula toplamı\n";
q("UPDATE faturalar SET durum = 'iptal' WHERE id = ?", [$fid]);
esit(6, count(fatura_sgk_donem_siparisleri('2026-09')), 'iptal edilen faturanın reçeteleri serbest');
$fid2 = fatura_sgk_donem_taslagi('2026-09', [$eylul1, $eylul2, $agustos], 448.5);
$f2 = row('SELECT * FROM faturalar WHERE id = ?', [$fid2]);
esit(448.5, (float) $f2['genel_toplam'], 'Medula toplamı esas alınır');
ok(str_contains((string) $f2['notlar'], 'Medula toplamı esas alındı'), 'farkı notta yazar');
esit(450.0, round(array_sum(array_column(fatura_sgk_dokum($fid2), 'tutar')), 2), 'dökümde OptiFlow tutarları korunur');
esit([$gunes, $islenmedi, $tam, $ekim], array_map('intval', array_column(fatura_sgk_donem_siparisleri('2026-10'), 'id')), 'ekim dönemi: kalanlar + ekim');

echo "6) Eski usul sipariş bazlı SGK taslakları\n";
$eskiId = fatura_taslak_yaz(fatura_sgk_alici() + ['order_id' => $tam, 'profil' => 'TEMELFATURA'], [['ad' => 'x', 'miktar' => 1, 'kdv_dahil' => 1000, 'kdv_orani' => 10]]);
esit([$eskiId], array_map('intval', array_column(fatura_sgk_eski_taslaklar(), 'id')), 'eski taslak bulunur (dönem faturası sayılmaz)');
esit(1, fatura_sgk_eski_taslaklari_iptal(), 'eski taslak iptal');
esit([], fatura_sgk_eski_taslaklar(), 'kalmadı');
esit('taslak', scalar('SELECT durum FROM faturalar WHERE id = ?', [$fid2]), 'dönem faturasına dokunulmaz');
esit('taslak', scalar('SELECT durum FROM faturalar WHERE id = ?', [$ids[0]]), 'hasta faturasına dokunulmaz');
$eski2 = fatura_taslak_yaz(fatura_sgk_alici() + ['order_id' => $ekim, 'profil' => 'TEMELFATURA'], [['ad' => 'x', 'miktar' => 1, 'kdv_dahil' => 150, 'kdv_orani' => 10]]);
fatura_sgk_donem_taslagi('2026-10', [$ekim]);
esit('iptal', scalar('SELECT durum FROM faturalar WHERE id = ?', [$eski2]), 'dönem faturası, reçetenin eski SGK taslağını kendiliğinden iptal eder');
q("UPDATE faturalar SET durum = 'gonderildi' WHERE id = ?", [$eskiId]);
ok(!in_array($tam, array_map('intval', array_column(fatura_sgk_donem_siparisleri('2026-10'), 'id')), true), 'eskiden GİB\'e gönderilmiş reçete tekrar faturalanmaz');

echo "7) Medula PDF dökümü\n";
$metin = pdf_metin((string) file_get_contents(__DIR__ . '/ornek-medula-dokum.pdf'));
ok(str_contains($metin, 'MEHMET') && str_contains($metin, 'ÇELİK') && str_contains($metin, 'Reçete Sayısı: 3'), 'PDF metni Türkçe karakterlerle okundu');
$no = sgk_metinden_numaralar($metin);
sort($no);
esit(['1A2B3C4', '5D6E7F8', '9G8H7J6'], $no, 'dökümdeki üç e-reçete numarası');
esit('', pdf_metin('düz metin'), 'PDF olmayan veri → boş');
esit('', pdf_metin("%PDF-1.4\n bozuk"), 'bozuk PDF → boş, hata yok');
$k = fatura_sgk_medula_karsilastir('2026-09', $no);
esit(3, $k['pdf_adet'], 'dökümde 3 reçete');
esit(5, $k['optiflow_adet'], 'OptiFlow\'da eylülde işaretli 5 SGK\'lı reçete (SGK\'sız ve iptal sayılmaz)');
esit(['1A2B3C4', '5D6E7F8'], array_keys($k['eslesen']), 'iki tarafta olanlar');
ok(array_key_exists('9G8H7J6', $k['pdfte_fazla']) && $k['pdfte_fazla']['9G8H7J6'] === null, 'dökümde olup OptiFlow\'da hiç olmayan');
esit(3, count($k['numarasiz']), 'e-reçete numarası olmayan işaretli siparişler ayrıca listelenir');
q("UPDATE orders SET sgk_erecete = '9G8H7J6', medula_islendi_at = NULL WHERE id = ?", [$islenmedi]);
$k = fatura_sgk_medula_karsilastir('2026-09', $no);
ok(($k['pdfte_fazla']['9G8H7J6']['id'] ?? 0) == $islenmedi, 'dökümdeki reçete OptiFlow\'da işaretsiz → sipariş gösterilir');
q("UPDATE orders SET sgk_erecete = 'Q1W2E3R' WHERE id = ?", [$tam]);
$k = fatura_sgk_medula_karsilastir('2026-09', $no);
esit(['Q1W2E3R'], array_column($k['optiflowda_fazla'], 'sgk_erecete'), 'OptiFlow\'da işaretli, dökümde olmayan');

bitir();
