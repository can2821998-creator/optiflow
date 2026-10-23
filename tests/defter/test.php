<?php
/* 4.45.0 Veresiye defteri (app/defter.php): taksit planı, ödeme dağıtımı, söz eşitleme, ekstre, borçlu müşteriler, kasa. */
declare(strict_types=1);
require dirname(__DIR__) . '/uts/ortam.php';
require_once dirname(__DIR__, 2) . '/app/defter.php';

// migrate_v37_defter ile aynı sütunlar + kullanılan sipariş/ödeme/kasa sütunları
$p = db();
$p->exec('ALTER TABLE orders ADD COLUMN balance_promise_date TEXT NULL');
$p->exec("CREATE TABLE payments (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, amount REAL NOT NULL, method TEXT NOT NULL DEFAULT 'nakit',
    note TEXT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL)");
$p->exec('CREATE TABLE odeme_plani (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, sira INTEGER NOT NULL, vade TEXT NOT NULL, tutar REAL NOT NULL,
    created_by INTEGER NULL, created_at TEXT NOT NULL, UNIQUE (order_id, sira))');
$p->exec('CREATE TABLE cash_counts (id INTEGER PRIMARY KEY AUTOINCREMENT, count_date TEXT NOT NULL, counted_amount REAL NOT NULL, expected_amount REAL NOT NULL,
    difference REAL NOT NULL, note TEXT NULL, created_by INTEGER NULL, created_at TEXT NOT NULL, birakilan REAL NULL, kupurler TEXT NULL)');

$musteri = insert('customers', ['first_name' => 'Ayşe', 'last_name' => 'Demir', 'phone' => '905321112233']);
$siparis = static fn(float $toplam, float $sgk = 0, string $tarih = '2026-10-01 10:00:00', string $asama = 'teslim_edildi', ?int $cid = null): int =>
    insert('orders', ['customer_id' => $cid ?? $musteri, 'total_amount' => $toplam, 'sgk_amount' => $sgk, 'order_stage' => $asama, 'created_at' => $tarih]);
$ode = static fn(int $oid, float $tutar, string $tarih = '2026-10-02 12:00:00', string $not = ''): int =>
    insert('payments', ['order_id' => $oid, 'amount' => $tutar, 'method' => 'nakit', 'note' => $not, 'created_at' => $tarih]);
$hata = static function (callable $f): string {
    try {
        $f();
    } catch (DomainException $e) {
        return $e->getMessage();
    }
    return '';
};

echo "1) Ay ekleme ve bakiye\n";
esit('2026-11-15', defter_ay_ekle('2026-10-15', 1), 'bir ay sonra');
esit('2027-02-28', defter_ay_ekle('2027-01-31', 1), '31 Ocak + 1 ay = 28 Şubat');
esit('2028-02-29', defter_ay_ekle('2028-01-31', 1), 'artık yıl');
esit('2027-01-10', defter_ay_ekle('2026-12-10', 1), 'yıl devri');
$a = $siparis(1000.0, 100.0);
$ode($a, 200.0, '2026-10-01 10:05:00', 'Kapora');
esit(700.0, defter_bakiye($a), 'toplam − SGK − ödeme');

echo "2) Taksit planı kurma\n";
ok(str_contains($hata(static fn() => defter_plan_kur($a, 0, '2026-11-01', 1)), 'arasında'), '0 taksit reddedilir');
ok(str_contains($hata(static fn() => defter_plan_kur($a, 13, '2026-11-01', 1)), 'arasında'), '13 taksit reddedilir');
ok(str_contains($hata(static fn() => defter_plan_kur($a, 3, '2026-13-01', 1)), 'tarihi'), 'geçersiz tarih');
$plan = defter_plan_kur($a, 3, '2026-11-15', 1);
esit([233.33, 233.33, 233.34], array_column($plan, 'tutar'), 'kuruş farkı son taksitte');
esit(700.0, round(array_sum(array_column($plan, 'tutar')), 2), 'toplam = bakiye');
esit(['2026-11-15', '2026-12-15', '2027-01-15'], array_column($plan, 'vade'), 'aylık vadeler');
esit('2026-11-15', scalar('SELECT balance_promise_date FROM orders WHERE id = ?', [$a]), 'ödeme sözü ilk taksite eşitlendi');
esit(3, count(defter_plan_kur($a, 3, '2026-11-15', 1)), 'yeniden kurma eski planı siler');
esit(3, (int) scalar('SELECT COUNT(*) FROM odeme_plani WHERE order_id = ?', [$a]), 'tabloda 3 satır');
$b = $siparis(500.0, 0, '2026-10-01 10:00:00', 'teslim_edildi');
$ode($b, 500.0);
ok(str_contains($hata(static fn() => defter_plan_kur($b, 2, '2026-11-01', 1)), 'kalan bakiyesi yok'), 'borcu olmayan sipariş taksitlenmez');

echo "3) Ödeme dağıtımı ve durumlar\n";
$ode($a, 300.0, '2026-11-14 09:00:00');
$plan = defter_plan($a, '2026-11-20');
esit(['odendi', 'kismi', 'bekliyor'], array_column($plan, 'durum'), '300 ödeme: 1. taksit kapandı, 2. kısmen');
esit([0.0, 166.66, 233.34], array_column($plan, 'kalan'), 'kalanlar');
esit(['sira' => 2, 'kalan' => 166.66], array_intersect_key(defter_siradaki($plan), ['sira' => 1, 'kalan' => 1]), 'sıradaki taksit 2');
esit('2026-12-15', defter_plan_esitle($a), 'söz sıradaki taksite kaydı');
$gec = defter_plan($a, '2026-12-20');
esit('gecikti', $gec[1]['durum'], 'vadesi geçen ödenmemiş taksit: gecikti');
esit('Taksit 2/3 · ' . date_tr('2026-12-15') . ' · ' . money(166.66), defter_plan_ozet($plan), 'tek satır özet');
$ode($a, 400.0, '2026-12-14 09:00:00');
$plan = defter_plan($a);
esit(['odendi', 'odendi', 'odendi'], array_column($plan, 'durum'), 'borç bitti: hepsi ödendi');
esit('Taksitler ödendi', defter_plan_ozet($plan), 'özet: ödendi');
ok(defter_plan_esitle($a) === null && scalar('SELECT balance_promise_date FROM orders WHERE id = ?', [$a]) === null, 'borç bitince söz tarihi temizlenir');
esit([], defter_plan(9999), 'planı olmayan sipariş');
ok(defter_plan_esitle($b) === null, 'plansız siparişte söz tarihine dokunulmaz');
$c = $siparis(900.0);
defter_plan_kur($c, 3, '2026-11-01', 1);
q('UPDATE orders SET total_amount = 1200 WHERE id = ?', [$c]);
$pc = defter_plan($c);
esit(900.0, round(array_sum(array_column($pc, 'kalan')), 2), 'tutar sonradan artarsa plan kendi toplamını korur (fark plan dışı)');
$t = defter_planlar_toplu([$c => defter_bakiye($c), $b => 0.0]);
ok(isset($t[$c]) && !isset($t[$b]) && count($t[$c]) === 3, 'toplu plan okuma');
defter_plan_kaldir($c);
esit([], defter_plan($c), 'plan kaldırılır');

echo "4) Ekstre\n";
$e = defter_ekstre($musteri);
$iptal = $siparis(5000.0, 0, '2026-10-03 10:00:00', 'iptal');
$ode($iptal, 1000.0);
$e2 = defter_ekstre($musteri);
esit(count($e['hareketler']), count($e2['hareketler']), 'iptal edilen sipariş ve ödemesi ekstreye girmez');
$h = $e['hareketler'];
esit(['#00001 · sipariş', '#00001 · SGK payı'], [$h[0]['aciklama'], $h[1]['aciklama']], 'sipariş borç, SGK payı alacak');
ok(in_array('#00001 · Nakit (Kapora)', array_column($h, 'aciklama'), true), 'ödeme notu (Kapora) görünür');
$sistem = $siparis(100.0, 0, '2026-10-20 10:00:00');
$ode($sistem, 50.0, '2026-10-20 11:00:00', 'Veresiye defteri');
$e = defter_ekstre($musteri);
$son = end($e['hareketler']);
ok(!str_contains($son['aciklama'], '('), 'ekranın kendi notu ekstrede gösterilmez');
esit(round(array_sum(array_column($e['hareketler'], 'borc')) - array_sum(array_column($e['hareketler'], 'alacak')), 2), $e['bakiye'], 'yürüyen bakiye = borç − alacak');
esit($son['bakiye'], $e['bakiye'], 'son satır bakiyesi = kalan borç');
esit(1250.0, $e['bakiye'], 'kalan borç: c 1200 + yeni 50 (a ve b kapandı)');
ok(isset($e['planlar'][$a]) && !isset($e['planlar'][$c]), 'ekstre planları');
$wa = defter_wa_metni(['first_name' => 'Ayşe'], $e);
ok(str_starts_with($wa, 'Merhaba Ayşe,') && str_contains($wa, 'Kalan borç: ' . money($e['bakiye'])), 'WhatsApp hesap özeti');

echo "5) Borçlu müşteriler\n";
$diger = insert('customers', ['first_name' => 'Can', 'last_name' => 'Yıldız', 'phone' => '']);
$d = $siparis(300.0, 0, '2026-09-01 10:00:00', 'teslim_edildi', $diger);
q('UPDATE orders SET balance_promise_date = ? WHERE id = ?', ['2026-10-01', $d]);
$m = defter_musteriler('2026-10-23');
esit([$diger, $musteri], array_column($m, 'customer_id'), 'sözü geçmiş müşteri üstte (borcu küçük olsa da)');
esit([300.0, 300.0], [$m[0]['borc'], $m[0]['geciken']], 'borç ve geciken');
esit(52, $m[0]['en_eski'], 'en eski borç günü');
esit([1250.0, 2], [$m[1]['borc'], $m[1]['siparis']], 'müşteri toplamı ve açık sipariş sayısı');
ok(!in_array($iptal, array_column($m, 'customer_id'), true), 'iptal sayılmaz');

echo "6) Gün sonu kasa\n";
esit([651.0, '200:3,50:1,0.25:4'], kasa_kupur_say(['200' => 3, '50' => 1, '0.25' => 4, '10' => -2]), 'küpür toplamı (eksi adet yok sayılır)');
esit([0.15, '0.10:1,0.05:1'], kasa_kupur_say(['0.10' => '1', '0.05' => '1']), 'kuruşlar kayan nokta hatasız');
esit([0.0, ''], kasa_kupur_say([]), 'boş sayım');
esit(0.0, kasa_devir('2026-10-23'), 'önceki kapanış yoksa devir 0');
insert('cash_counts', ['count_date' => '2026-10-21', 'counted_amount' => 800, 'expected_amount' => 800, 'difference' => 0, 'created_at' => '2026-10-21 19:00:00', 'birakilan' => 300]);
insert('cash_counts', ['count_date' => '2026-10-22', 'counted_amount' => 900, 'expected_amount' => 900, 'difference' => 0, 'created_at' => '2026-10-22 19:00:00', 'birakilan' => 450]);
insert('cash_counts', ['count_date' => '2026-10-22', 'counted_amount' => 950, 'expected_amount' => 900, 'difference' => 50, 'created_at' => '2026-10-22 19:30:00', 'birakilan' => 500]);
esit(500.0, kasa_devir('2026-10-23'), 'devir: önceki günün SON sayımı');
esit(300.0, kasa_devir('2026-10-22'), 'aynı günün kendi sayımı devre girmez');
insert('cash_counts', ['count_date' => '2026-10-23', 'counted_amount' => 100, 'expected_amount' => 100, 'difference' => 0, 'created_at' => '2026-10-23 19:00:00', 'birakilan' => null]);
esit(0.0, kasa_devir('2026-10-24'), 'bırakılan girilmemişse devir 0 (eski davranış)');

echo "7) Kaynak bağlantıları\n";
$kok = dirname(__DIR__, 2);
$mig = (string) file_get_contents($kok . '/app/migrations.php');
ok((bool) preg_match('/const SCHEMA_VERSION = (3[7-9]|[4-9]\d);/', $mig) && str_contains($mig, 'migrate_v37_defter()') && str_contains($mig, "add_column('cash_counts', 'birakilan'"), 'göç v37');
ok(str_contains((string) file_get_contents($kok . '/app/bootstrap.php'), "require __DIR__ . '/defter.php';"), 'modül yüklenir');
$tah = (string) file_get_contents($kok . '/app/pages/tahsilat.php');
ok(str_contains($tah, "'musteri'  => 'Müşteri defteri'") && str_contains($tah, "query('f', 'musteri')") && str_contains($tah, 'defter_plan_kur('), 'veresiye defteri: müşteri sekmesi varsayılan, taksit eylemi');
ok(str_contains($tah, "preg_match('/^defter\\.php\\?musteri=\\d+\$/', post('don'))"), 'ekstreye dönüş adresi sınırlı (açık yönlendirme yok)');
foreach (['app/pages/order.php', 'app/pages/tahsilat.php', 'app/odeme.php'] as $dosya) {
    ok(str_contains((string) file_get_contents($kok . '/' . $dosya), 'defter_plan_esitle('), "ödemeden sonra söz eşitlenir: $dosya");
}
ok(str_contains((string) file_get_contents($kok . '/app/pages/order.php'), 'defter_plan_kaldir($id)'), 'iptalde plan düşer');
ok(is_file($kok . '/defter.php') && str_contains((string) file_get_contents($kok . '/app/pages/defter.php'), 'defter_ekstre($cid)'), 'ekstre sayfası');
$kasa = (string) file_get_contents($kok . '/app/pages/kasa.php');
ok(str_contains($kasa, 'data-kupur-form') && str_contains($kasa, "page_end(['kasa.js'])") && str_contains($kasa, 'kasa_devir($date)'), 'kasa: küpür formu, devir');
ok(!preg_match('/<script(?![^>]*\bsrc=)/i', $kasa . (string) file_get_contents($kok . '/app/pages/defter.php')), 'satır içi betik yok (CSP)');

bitir();
