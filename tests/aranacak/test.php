<?php
/* 4.47.0 Bugün aranacaklar (app/aranacak.php): kaynaklar, öncelik, açmadı / ulaşıldı / ertele, deneme sayısı, haber adımı. */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';
require_once dirname(__DIR__, 2) . '/app/reminders.php';
require_once dirname(__DIR__, 2) . '/app/atolye.php';
require_once dirname(__DIR__, 2) . '/app/aranacak.php';

// migrate_v38_arama_kayitlari + kullanılan sütun/tablolar (reminders: migrate_v20, atolye_adimlari: v36)
$p = db();
foreach (['workshop_stage TEXT NULL', 'qc_at TEXT NULL', 'updated_at TEXT NULL', 'raf_yeri TEXT NULL', 'balance_promise_date TEXT NULL'] as $s) {
    $p->exec('ALTER TABLE orders ADD COLUMN ' . $s);
}
$p->exec("CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, amount REAL NOT NULL, method TEXT NOT NULL DEFAULT 'nakit', note TEXT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL)");
$p->exec('CREATE TABLE atolye_adimlari (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, adim TEXT NOT NULL, user_id INTEGER NULL, created_at TEXT NOT NULL, UNIQUE (order_id, adim))');
$p->exec("CREATE TABLE reminders (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER NOT NULL, order_id INTEGER NULL, kind TEXT NOT NULL DEFAULT 'manuel', due_date TEXT NULL,
    note TEXT NULL, status TEXT NOT NULL DEFAULT 'bekliyor', channel TEXT NULL, done_at TEXT NULL, done_by INTEGER NULL, created_by INTEGER NULL, created_at TEXT NULL, updated_at TEXT NULL)");
$p->exec('CREATE TABLE arama_kayitlari (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER NOT NULL, order_id INTEGER NULL, tur TEXT NOT NULL, sonuc TEXT NOT NULL,
    not_metni TEXT NULL, user_id INTEGER NULL, created_at TEXT NOT NULL)');
$p->exec("UPDATE user_accounts SET full_name = 'Deniz Yılmaz' WHERE id = 1");
setting_set('reminder_pickup_days', '7');

$bugun = date('Y-m-d');
$gunOnce = static fn(int $n): string => date('Y-m-d H:i:s', strtotime("-$n days"));
$musteri = static fn(string $ad, string $tel = '05321112233'): int => insert('customers', ['first_name' => $ad, 'last_name' => 'Test', 'phone' => $tel]);
$hata = static function (callable $f): string {
    try { $f(); } catch (DomainException $e) { return $e->getMessage(); }
    return '';
};

$ayse = $musteri('Ayşe');      // gözlüğü 2 gün önce hazır, haber yok → haber
$can = $musteri('Can');        // 10 gün önce hazır, 9 gün önce haber verildi → teslim
$ali = $musteri('Ali');        // 10 gün önce hazır, dün haber verildi → listede değil
$ece = $musteri('Ece', '');    // ödeme sözü 4 gün geçti → bakiye
$mert = $musteri('Mert');      // planlı arama bugün
$ozan = $musteri('Ozan');      // planlı arama yarın → yok
$o1 = insert('orders', ['customer_id' => $ayse, 'order_stage' => 'hazirlandi', 'qc_at' => $gunOnce(2), 'raf_yeri' => '7']);
$o2 = insert('orders', ['customer_id' => $can, 'order_stage' => 'hazirlandi', 'qc_at' => $gunOnce(10), 'transaction_type' => 'tamir']);
insert('atolye_adimlari', ['order_id' => $o2, 'adim' => 'haber', 'user_id' => 1, 'created_at' => $gunOnce(9)]);
$o3 = insert('orders', ['customer_id' => $ali, 'order_stage' => 'hazirlandi', 'qc_at' => $gunOnce(10)]);
insert('atolye_adimlari', ['order_id' => $o3, 'adim' => 'haber', 'user_id' => 1, 'created_at' => $gunOnce(1)]);
$o4 = insert('orders', ['customer_id' => $ece, 'order_stage' => 'teslim_edildi', 'total_amount' => 1000, 'balance_promise_date' => date('Y-m-d', strtotime('-4 days'))]);
insert('payments', ['order_id' => $o4, 'amount' => 400, 'created_at' => $gunOnce(20)]);
$o5 = insert('orders', ['customer_id' => $ece, 'order_stage' => 'teslim_edildi', 'total_amount' => 500, 'balance_promise_date' => date('Y-m-d', strtotime('-4 days'))]);
insert('payments', ['order_id' => $o5, 'amount' => 500, 'created_at' => $gunOnce(1)]);   // ödenmiş: söz geçse de aranmaz
insert('orders', ['customer_id' => $ece, 'order_stage' => 'iptal', 'total_amount' => 900, 'balance_promise_date' => date('Y-m-d', strtotime('-4 days'))]);
$r1 = insert('reminders', ['customer_id' => $mert, 'kind' => 'manuel', 'due_date' => $bugun, 'note' => 'cam fiyatı sorulacak', 'status' => 'bekliyor']);
insert('reminders', ['customer_id' => $ozan, 'kind' => 'manuel', 'due_date' => date('Y-m-d', strtotime('+1 day')), 'status' => 'bekliyor']);

echo "1) Kaynaklar ve öncelik\n";
$l = aranacak_listesi();
esit(['haber', 'teslim', 'manuel', 'bakiye'], array_column($l, 'tur'), 'sıra: hazır, gelmedi, planlı, söz geçti');
esit(['Ayşe Test', 'Can Test', 'Mert Test', 'Ece Test'], array_column($l, 'ad'), 'doğru kişiler');
ok(!in_array($ali, array_column($l, 'customer_id'), true), 'dün haber verilen listede yok (teslim süresi haberden sayılır)');
ok(!in_array($ozan, array_column($l, 'customer_id'), true), 'yarınki planlı arama bugün yok');
esit('#' . sprintf('%05d', $o1) . ' · raf 7', $l[0]['baslik'], 'hazır gözlükte raf görünür');
esit('Gözlüğü hazır · 2 gündür bekliyor', $l[0]['detay'], 'hazır detayı');
esit('9 gün önce haber verildi, alınmadı', $l[1]['detay'], 'teslim detayı haberden');
esit(600.0, round((float) $l[3]['kalan'], 2), 'bakiye: kalan tutar (ödenmiş ve iptal sayılmaz)');
esit('cam fiyatı sorulacak', $l[2]['detay'], 'planlı aramanın notu');
esit(4, count(array_filter(array_column($l, 'tur'), static fn($t) => isset(ARANACAK_TURLERI[$t]))), 'her satırın türü tanımlı');

echo "2) Açmadı → yarın, deneme sayısı\n";
esit('Açmadı — yarın yeniden listede.', aranacak_sonuc('haber', $ayse, $o1, null, 'acmadi', 1), 'ileti');
ok(!in_array($ayse, array_column(aranacak_listesi(), 'customer_id'), true), 'bugün listeden düşer');
$yarin = aranacak_listesi(date('Y-m-d', strtotime('+1 day')));
$a = array_values(array_filter($yarin, static fn($s) => (int) $s['customer_id'] === $ayse))[0] ?? null;
ok($a !== null && $a['tur'] === 'haber', 'yarın yeniden listede');
esit(1, $a['deneme'] ?? -1, 'deneme sayısı 1');
esit(['acmadi', 'Deniz Yılmaz'], [$a['son_sonuc']['sonuc'] ?? '', $a['son_sonuc']['full_name'] ?? ''], 'son sonuç ve kim aradı');

echo "3) Ulaşıldı → haber adımı, hazırda listeden çıkar\n";
aranacak_sonuc('haber', $ayse, $o1, null, 'ulasildi', 1, 'yarın gelecek');
esit(1, (int) scalar("SELECT COUNT(*) FROM atolye_adimlari WHERE order_id = ? AND adim = 'haber'", [$o1]), 'atölyedeki "Müşteriye haber" adımı atıldı');
ok(!in_array($ayse, array_column(aranacak_listesi(date('Y-m-d', strtotime('+3 days'))), 'customer_id'), true), 'haber verilen, teslim süresi dolana dek listede yok');
ok(in_array($ayse, array_column(aranacak_listesi(date('Y-m-d', strtotime('+8 days'))), 'customer_id'), true), 'süre dolunca "haber verildi, gelmedi" olarak döner');
esit(2, (int) scalar('SELECT COUNT(*) FROM arama_kayitlari WHERE customer_id = ?', [$ayse]), 'her deneme kayıtlı');
esit('yarın gelecek', scalar("SELECT not_metni FROM arama_kayitlari WHERE customer_id = ? AND sonuc = 'ulasildi'", [$ayse]), 'not saklanır');

echo "4) Planlı arama ve bakiye\n";
aranacak_sonuc('manuel', $mert, null, $r1, 'whatsapp', 1);
esit(['yapildi', 'whatsapp'], array_values(row('SELECT status, channel FROM reminders WHERE id = ?', [$r1])), 'planlı arama tamamlandı (WhatsApp)');
ok(str_contains($hata(static fn() => aranacak_sonuc('manuel', $mert, null, null, 'ulasildi', 1)), 'bulunamadı'), 'planlı aramada kayıt no şart');
aranacak_sonuc('bakiye', $ece, $o4, null, 'ulasildi', 1);
ok(!in_array($ece, array_column(aranacak_listesi(date('Y-m-d', strtotime('+2 days'))), 'customer_id'), true), 'sözü geçen ödeme: ulaşıldıysa 3 gün susar');
ok(in_array($ece, array_column(aranacak_listesi(date('Y-m-d', strtotime('+4 days'))), 'customer_id'), true), '3 gün sonra hâlâ ödemediyse döner');

echo "5) Ertele ve kurallar\n";
ok(str_contains($hata(static fn() => aranacak_sonuc('teslim', $can, $o2, null, 'ertele', 1, '', $bugun)), 'ileri'), 'bugüne ertelenmez');
esit(date_tr(date('Y-m-d', strtotime('+5 days'))) . ' tarihine ertelendi.', aranacak_sonuc('teslim', $can, $o2, null, 'ertele', 1, 'hafta sonu', date('Y-m-d', strtotime('+5 days'))), 'ertele iletisi');
ok(!in_array($can, array_column(aranacak_listesi(date('Y-m-d', strtotime('+4 days'))), 'customer_id'), true), 'ertelenen tarihine kadar yok');
ok(str_contains($hata(static fn() => aranacak_sonuc('uydurma', $can, null, null, 'acmadi', 1)), 'Bilinmeyen'), 'bilinmeyen tür');
ok(str_contains($hata(static fn() => aranacak_sonuc('haber', $can, null, null, 'belki', 1)), 'Bilinmeyen'), 'bilinmeyen sonuç');
esit('+1 day', reminder_cooldown('haber'), 'haber bekleme süresi');
esit('+3 days', reminder_cooldown('bakiye'), 'bakiye bekleme süresi');

echo "6) Mesaj ve kaynak bağlantıları\n";
setting_set('reminder_msg_teslim', 'Merhaba {ad}, {siparis_no} hazır.');
esit('Merhaba Ayşe, #' . sprintf('%05d', $o1) . ' hazır.', aranacak_mesaj(['tur' => 'haber', 'first_name' => 'Ayşe', 'last_name' => 'Test', 'gun' => 2, 'order_id' => $o1]), 'hazır gözlükte teslim şablonu');
$kok = dirname(__DIR__, 2);
$mig = (string) file_get_contents($kok . '/app/migrations.php');
ok((bool) preg_match('/const SCHEMA_VERSION = (3[8-9]|[4-9]\d);/', $mig) && str_contains($mig, 'migrate_v38_arama_kayitlari()'), 'göç v38');
ok(str_contains((string) file_get_contents($kok . '/app/bootstrap.php'), "require __DIR__ . '/aranacak.php';"), 'modül yüklenir');
$hp = (string) file_get_contents($kok . '/app/pages/hatirlatma.php');
ok(str_contains($hp, "['bugun' => 'Bugün aranacaklar'] + reminder_kinds()") && str_contains($hp, "query('tur', 'bugun')") && str_contains($hp, "\$eylem === 'arama'"), 'hatırlatma: bugün sekmesi varsayılan, sonuç eylemi');
ok(str_contains((string) file_get_contents($kok . '/app/reminders.php'), "return \$n = aranacak_sayisi();"), 'menü rozeti = bugün aranacak');
ok(!preg_match('/<script(?![^>]*\bsrc=)/i', (string) file_get_contents($kok . '/app/partials/aranacaklar.php')), 'satır içi betik yok (CSP)');

bitir();
