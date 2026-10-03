<?php
declare(strict_types=1);

/* ÜTS testleri için çalışma ortamı: SQLite bellek veritabanı + gerçek uygulama dosyaları.
   MySQL'e özgü göçler burada çalışmaz; tablolar migrate_v23_uts ile AYNI sütunlarla SQLite'ta kurulur. */

const APP_VERSION = 'test';
define('APP_ROOT', getenv('UTS_TEST_ROOT') ?: sys_get_temp_dir() . '/optiflow-uts-test-' . getmypid());
@mkdir(APP_ROOT . '/storage/logs', 0700, true);
date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');
$GLOBALS['config'] = ['db' => []];
$GLOBALS['__kullanici'] = ['id' => 1, 'full_name' => 'Test Personel', 'role' => 'super'];
$GLOBALS['__loglar'] = [];

function config(string $key, mixed $default = null): mixed { return $default; }
function current_user(): ?array { return $GLOBALS['__kullanici']; }
function is_super(): bool { return true; }
function can_see_amounts(): bool { return true; }
function audit(string $a, string $e = '', ?int $id = null, array $d = []): void {}
function ozellik_acik(string $k): bool { return true; }
function ozellik_acik_arka_plan(string $k): bool { return true; }
function order_track_url(int $id): string { return ''; }
function musteri_link(string $s, array $p = []): string { return $s . '?' . http_build_query(['m' => 7] + $p); }
function musteri_url(string $s, array $p = []): string { return 'https://test.local/' . musteri_link($s, $p); }
function payment_methods(): array { return ['nakit' => 'Nakit', 'kart' => 'Kredi kartı', 'havale' => 'Havale / EFT', 'diger' => 'Diğer']; }
$GLOBALS['__push'] = [];
function push_send(array $m, array $ids = [], ?int $haric = null): array { $GLOBALS['__push'][] = [$m, $ids]; return ['sent' => count($ids), 'failed' => 0, 'detail' => [], 'reason' => '']; }

require dirname(__DIR__, 2) . '/app/helpers.php';

function db(): PDO
{
    if (!isset($GLOBALS['__pdo'])) {
        $dosya = getenv('UTS_TEST_DB') ?: '';
        $yeni = $dosya === '' || !is_file($dosya);
        $pdo = new PDO($dosya !== '' ? 'sqlite:' . $dosya : 'sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $GLOBALS['__pdo'] = $pdo;
        if ($yeni) {
            sema_kur($pdo);
        }
    }
    return $GLOBALS['__pdo'];
}
function q(string $sql, array $p = []): PDOStatement { $st = db()->prepare($sql); $st->execute(array_values($p)); return $st; }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r === false ? null : $r; }
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function scalar(string $sql, array $p = []): mixed { $v = q($sql, $p)->fetchColumn(); return $v === false ? null : $v; }
function insert(string $t, array $d): int { $c = array_keys($d); q('INSERT INTO `' . $t . '` (`' . implode('`,`', $c) . '`) VALUES (' . implode(',', array_fill(0, count($c), '?')) . ')', array_values($d)); return (int) db()->lastInsertId(); }
function update(string $t, array $d, string $w, array $wp): int { $set = implode(',', array_map(static fn($c) => "`$c`=?", array_keys($d))); return q("UPDATE `$t` SET $set WHERE $w", array_merge(array_values($d), $wp))->rowCount(); }
function in_placeholders(array $v): string { return implode(',', array_fill(0, max(1, count($v)), '?')); }
function transaction(callable $fn): mixed { $p = db(); $p->beginTransaction(); try { $r = $fn(); $p->commit(); return $r; } catch (Throwable $e) { $p->rollBack(); throw $e; } }
function setting(string $k, string $d = ''): string { $v = scalar('SELECT setting_value FROM app_settings WHERE setting_key = ?', [$k]); return $v === null ? $d : (string) $v; }
function setting_set(string $k, string $v): void { q('INSERT OR REPLACE INTO app_settings (setting_key, setting_value) VALUES (?, ?)', [$k, $v]); }

function sema_kur(PDO $p): void
{
    $p->exec("CREATE TABLE app_settings (setting_key TEXT PRIMARY KEY, setting_value TEXT)");
    $p->exec("CREATE TABLE orders (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT DEFAULT '', last_name TEXT DEFAULT '', order_stage TEXT DEFAULT 'siparis_verildi',
        frame_item_id INTEGER NULL, frame_info TEXT NULL, sgk_erecete TEXT NULL, sgk_amount REAL NOT NULL DEFAULT 0, delivered_at TEXT NULL,
        customer_id INTEGER NULL, transaction_type TEXT DEFAULT 'gozluk', lens_type TEXT NULL, public_token TEXT NULL, total_amount REAL NOT NULL DEFAULT 0, created_at TEXT DEFAULT CURRENT_TIMESTAMP)");
    $p->exec("CREATE TABLE customers (id INTEGER PRIMARY KEY AUTOINCREMENT, first_name TEXT NOT NULL, last_name TEXT NOT NULL, phone TEXT NOT NULL DEFAULT '', birth_year INTEGER NULL)");
    $p->exec("CREATE TABLE prescription_records (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NULL)");
    $p->exec("CREATE TABLE prescription_lens_items (id INTEGER PRIMARY KEY AUTOINCREMENT, prescription_id INTEGER NOT NULL, lens_no INTEGER NOT NULL DEFAULT 1, lens_label TEXT NOT NULL DEFAULT '',
        stock_status TEXT NOT NULL DEFAULT 'stokta_var', eye TEXT NOT NULL DEFAULT 'R', lens_type TEXT NOT NULL DEFAULT '', item_group TEXT NOT NULL DEFAULT 'uzak', supplier_id INTEGER NULL,
        unit_cost REAL NULL, ordered_at TEXT NULL, arrived_at TEXT NULL, cam_siparis_id INTEGER NULL, delivery_id INTEGER NULL)");
    $p->exec("CREATE TABLE cam_hatalari (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, supplier_id INTEGER NULL, neden TEXT NOT NULL, goz TEXT NOT NULL DEFAULT 'cift',
        sorumlu_id INTEGER NULL, maliyet REAL NOT NULL DEFAULT 0, yeniden_yapim INTEGER NOT NULL DEFAULT 1, alacak_durum TEXT NOT NULL DEFAULT 'yok', alacak_tutar REAL NULL,
        alacak_payment_id INTEGER NULL, aciklama TEXT NULL, created_by INTEGER NULL, created_at TEXT, updated_at TEXT)");
    $p->exec("CREATE TABLE sgk_hak_sorgulari (id INTEGER PRIMARY KEY AUTOINCREMENT, customer_id INTEGER NULL, order_id INTEGER NULL, gelen_id INTEGER NULL, kaynak TEXT NOT NULL DEFAULT 'yapistir',
        ad TEXT NULL, son_alim TEXT NULL, sonraki_hak TEXT NULL, hak TEXT NOT NULL DEFAULT 'belirsiz', satirlar TEXT NULL, created_by INTEGER NULL, created_at TEXT)");
    // migrate_v26_garanti ile aynı sütunlar
    $p->exec("CREATE TABLE garantiler (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NULL, customer_id INTEGER NULL, kalem TEXT NOT NULL DEFAULT 'cerceve',
        urun TEXT NOT NULL, seri_no TEXT NULL, supplier_id INTEGER NULL, baslangic TEXT NOT NULL, bitis TEXT NOT NULL, kapsam TEXT NULL, token TEXT NOT NULL UNIQUE,
        durum TEXT NOT NULL DEFAULT 'aktif', created_by INTEGER NULL, created_at TEXT, updated_at TEXT)");
    $p->exec("CREATE TABLE garanti_talepleri (id INTEGER PRIMARY KEY AUTOINCREMENT, garanti_id INTEGER NOT NULL, sikayet TEXT NOT NULL, durum TEXT NOT NULL DEFAULT 'acik',
        supplier_id INTEGER NULL, gonderim TEXT NULL, sonuc_tur TEXT NULL, sonuc TEXT NULL, maliyet REAL NOT NULL DEFAULT 0, kapanis TEXT NULL,
        created_by INTEGER NULL, created_at TEXT, updated_at TEXT)");
    // migrate_v22 (faturalar, fatura_satirlari) + v27 (sgk_donem, fatura_sgk_siparisleri)
    $p->exec("CREATE TABLE faturalar (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NULL, customer_id INTEGER NULL, fatura_no TEXT NULL UNIQUE, uuid TEXT NOT NULL,
        profil TEXT NOT NULL DEFAULT 'EARSIVFATURA', tip TEXT NOT NULL DEFAULT 'SATIS', durum TEXT NOT NULL DEFAULT 'taslak', duzenleme TEXT NULL, alici_tip TEXT NOT NULL DEFAULT 'kisi',
        alici_ad TEXT NULL, alici_soyad TEXT NULL, alici_unvan TEXT NULL, alici_kimlik TEXT NULL, alici_vergi_dairesi TEXT NULL, alici_adres TEXT NULL, alici_ilce TEXT NULL,
        alici_il TEXT NULL, alici_eposta TEXT NULL, alici_telefon TEXT NULL, gonderim_sekli TEXT NOT NULL DEFAULT 'ELEKTRONIK', ara_toplam REAL NOT NULL DEFAULT 0,
        iskonto_toplam REAL NOT NULL DEFAULT 0, kdv_toplam REAL NOT NULL DEFAULT 0, genel_toplam REAL NOT NULL DEFAULT 0, notlar TEXT NULL, entegrator TEXT NULL,
        entegrator_ref TEXT NULL, entegrator_hata TEXT NULL, xml TEXT NULL, created_by INTEGER NULL, created_at TEXT, updated_at TEXT, sgk_donem TEXT NULL)");
    $p->exec("CREATE TABLE fatura_satirlari (id INTEGER PRIMARY KEY AUTOINCREMENT, fatura_id INTEGER NOT NULL, sira INTEGER NOT NULL, ad TEXT NOT NULL, miktar REAL NOT NULL DEFAULT 1,
        birim TEXT NOT NULL DEFAULT 'C62', birim_fiyat REAL NOT NULL DEFAULT 0, iskonto REAL NOT NULL DEFAULT 0, kdv_orani REAL NOT NULL DEFAULT 10, tutar REAL NOT NULL DEFAULT 0,
        kdv_tutar REAL NOT NULL DEFAULT 0)");
    $p->exec("CREATE TABLE fatura_sgk_siparisleri (fatura_id INTEGER NOT NULL, order_id INTEGER NOT NULL, tutar REAL NOT NULL DEFAULT 0, PRIMARY KEY (fatura_id, order_id))");
    $p->exec("CREATE TABLE sgk_incoming (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, kaynak TEXT, raw_text TEXT, parsed TEXT, used_at TEXT NULL, created_at TEXT)");
    $p->exec("CREATE TABLE suppliers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, contact_name TEXT NULL, phone TEXT NULL, address TEXT NULL, tax_no TEXT NULL, note TEXT NULL,
        uts_kurum_no TEXT NULL, email TEXT NULL, is_active INTEGER DEFAULT 1, created_at TEXT, updated_at TEXT)");
    $p->exec("CREATE TABLE user_accounts (id INTEGER PRIMARY KEY AUTOINCREMENT, full_name TEXT, role TEXT, is_active INTEGER DEFAULT 1)");
    $p->exec("INSERT INTO user_accounts (full_name, role) VALUES ('Patron', 'super_yetkili'), ('Personel', 'personel')");
    $p->exec("CREATE TABLE supplier_invoices (id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_id INTEGER NOT NULL, invoice_no TEXT NOT NULL, invoice_date TEXT NOT NULL,
        amount REAL NOT NULL, note TEXT NULL, created_by INTEGER NULL, created_at TEXT, due_date TEXT NULL, ettn TEXT NULL, kaynak TEXT NOT NULL DEFAULT 'elle',
        ara_toplam REAL NULL, kdv_toplam REAL NULL)");
    $p->exec("CREATE UNIQUE INDEX uq_supplier_invoices_ettn ON supplier_invoices (ettn)");
    $p->exec("CREATE TABLE supplier_invoice_xml (invoice_id INTEGER PRIMARY KEY, xml TEXT NOT NULL)");
    $p->exec("CREATE TABLE supplier_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_id INTEGER NOT NULL, amount REAL NOT NULL, method TEXT NOT NULL DEFAULT 'havale',
        note TEXT NULL, created_by INTEGER NULL, created_at TEXT)");
    $p->exec("CREATE TABLE supplier_deliveries (id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_id INTEGER NOT NULL, invoice_id INTEGER NULL)");
    $p->exec("CREATE TABLE supplier_invoice_lines (id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL, sira INTEGER NOT NULL, ad TEXT NOT NULL, kod TEXT NULL,
        gtin TEXT NULL, miktar REAL NOT NULL DEFAULT 1, birim TEXT NULL, birim_fiyat REAL NOT NULL DEFAULT 0, kdv_orani REAL NOT NULL DEFAULT 0, tutar REAL NOT NULL DEFAULT 0,
        kdv_tutar REAL NOT NULL DEFAULT 0, frame_item_id INTEGER NULL, stok_adet INTEGER NOT NULL DEFAULT 0)");
    $p->exec("CREATE TABLE urun_eslesmeleri (id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_id INTEGER NOT NULL, kod TEXT NOT NULL, frame_item_id INTEGER NOT NULL, created_at TEXT,
        UNIQUE (supplier_id, kod))");
    $p->exec("CREATE TABLE tedarikci_senetleri (id INTEGER PRIMARY KEY AUTOINCREMENT, supplier_id INTEGER NOT NULL, invoice_id INTEGER NULL, payment_id INTEGER NULL,
        senet_no TEXT NULL, tutar REAL NOT NULL, duzenleme TEXT NOT NULL, vade TEXT NOT NULL, duzenleme_yeri TEXT NULL, odeme_yeri TEXT NULL,
        durum TEXT NOT NULL DEFAULT 'bekliyor', odeme_tarihi TEXT NULL, odeme_yontemi TEXT NULL, notlar TEXT NULL, created_by INTEGER NULL, created_at TEXT, updated_at TEXT)");
    $p->exec("CREATE TABLE frame_items (id INTEGER PRIMARY KEY AUTOINCREMENT, brand TEXT NOT NULL, model TEXT NULL, color TEXT NULL, size TEXT NULL,
        barcode TEXT NULL UNIQUE, qty INTEGER NOT NULL DEFAULT 0, min_qty INTEGER NOT NULL DEFAULT 1, cost REAL NULL, price REAL NULL, supplier_id INTEGER NULL,
        shelf TEXT NULL, note TEXT NULL, is_active INTEGER NOT NULL DEFAULT 1, created_by INTEGER NULL, created_at TEXT, updated_at TEXT)");
    $p->exec("CREATE TABLE frame_moves (id INTEGER PRIMARY KEY AUTOINCREMENT, frame_item_id INTEGER NOT NULL, delta INTEGER NOT NULL, reason TEXT NOT NULL DEFAULT 'giris',
        order_id INTEGER NULL, note TEXT NULL, created_by INTEGER NULL, created_at TEXT)");
    // migrate_v23_uts ile birebir aynı sütunlar
    $p->exec("CREATE TABLE uts_urunler (id INTEGER PRIMARY KEY AUTOINCREMENT, anahtar TEXT NOT NULL UNIQUE, uno TEXT NOT NULL, lno TEXT NULL, sno TEXT NULL,
        adet INTEGER NOT NULL DEFAULT 1, kaynak TEXT NOT NULL DEFAULT 'uts', skt TEXT NULL, urt TEXT NULL, kategori TEXT NOT NULL DEFAULT 'diger', marka_model TEXT NULL,
        gonderen TEXT NULL, gonderen_kurum TEXT NULL, belge_no TEXT NULL, vbi TEXT NULL, durum TEXT NOT NULL DEFAULT 'stokta', frame_item_id INTEGER NULL,
        order_id INTEGER NULL, satis_turu TEXT NULL, alma_at TEXT NULL, cikis_at TEXT NULL, created_by INTEGER NULL, created_at TEXT, updated_at TEXT)");
    $p->exec("CREATE TABLE uts_bildirimler (id INTEGER PRIMARY KEY AUTOINCREMENT, tur TEXT NOT NULL, urun_id INTEGER NULL, order_id INTEGER NULL, adet INTEGER NOT NULL DEFAULT 1,
        govde TEXT NOT NULL, durum TEXT NOT NULL DEFAULT 'bekliyor', ortam TEXT NOT NULL DEFAULT 'deneme', uts_id TEXT NULL, ilgili_id INTEGER NULL,
        deneme INTEGER NOT NULL DEFAULT 0, son_hata TEXT NULL, yanit TEXT NULL, tekil TEXT NULL UNIQUE, planlanan TEXT NOT NULL, gonderilme TEXT NULL,
        created_by INTEGER NULL, created_at TEXT)");
}

require dirname(__DIR__, 2) . '/app/entegrasyon.php';
require dirname(__DIR__, 2) . '/app/frames.php';
require dirname(__DIR__, 2) . '/app/barkod.php';
require dirname(__DIR__, 2) . '/app/uts.php';
require dirname(__DIR__, 2) . '/app/alis.php';
require dirname(__DIR__, 2) . '/app/senet.php';
require dirname(__DIR__, 2) . '/app/sgk.php';
require dirname(__DIR__, 2) . '/app/cam-hata.php';
require dirname(__DIR__, 2) . '/app/sgk-hak.php';
require dirname(__DIR__, 2) . '/app/garanti.php';
require dirname(__DIR__, 2) . '/app/fatura.php';

/* ---------- Küçük test çatısı ---------- */
$GLOBALS['__gecen'] = 0;
$GLOBALS['__kalan'] = [];
function ok(bool $kosul, string $ad): void
{
    if ($kosul) {
        $GLOBALS['__gecen']++;
    } else {
        $GLOBALS['__kalan'][] = $ad;
        fwrite(STDERR, "  ✗ $ad\n");
    }
}
function esit(mixed $bek, mixed $gel, string $ad): void
{
    ok($bek === $gel, $ad . ($bek === $gel ? '' : ' — beklenen ' . var_export($bek, true) . ', gelen ' . var_export($gel, true)));
}
function hata_bekle(callable $fn, string $ad, string $icerir = ''): void
{
    try {
        $fn();
        ok(false, $ad . ' (hata beklenirdi)');
    } catch (DomainException $e) {
        ok($icerir === '' || str_contains($e->getMessage(), $icerir), $ad . ' — mesaj: ' . $e->getMessage());
    }
}
function bitir(): never
{
    $k = count($GLOBALS['__kalan']);
    echo ($k ? "BAŞARISIZ" : "TAMAM") . ': ' . $GLOBALS['__gecen'] . ' / ' . ($GLOBALS['__gecen'] + $k) . "\n";
    @array_map('unlink', glob(APP_ROOT . '/storage/{,.}*', GLOB_BRACE) ?: []);
    exit($k ? 1 : 0);
}
