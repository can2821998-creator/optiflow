<?php
declare(strict_types=1);

/**
 * Sürümlü, tekrar çalıştırılabilir (idempotent) şema göçleri.
 * Normal isteklerde yalnızca tek bir SELECT yapılır. Göç sadece sürüm eskiyse çalışır
 * ve eşzamanlı istekler için MySQL kilidi kullanılır. Hiçbir adım mevcut veriyi silmez
 * (tek istisna: v28'in progressive siparişlerde hatalı ürettiği fazladan yakın cam satırları).
 */
const SCHEMA_VERSION = 24;

function run_migrations(): void
{
    $current = 0;
    try {
        $current = (int) scalar("SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'");
    } catch (PDOException) {
        $current = 0;
    }
    if ($current >= SCHEMA_VERSION) {
        return;
    }
    if (!(int) scalar("SELECT GET_LOCK('optiflow_migrate', 60)")) {
        render_error_page('Güncelleme sürüyor', 'Sistem veritabanını güncelliyor. Birkaç saniye sonra sayfayı yenileyin.');
    }
    try {
        // Kilidi beklerken başka istek bitirmiş olabilir.
        try {
            $current = (int) scalar("SELECT setting_value FROM app_settings WHERE setting_key = 'schema_version'");
        } catch (PDOException) {
            $current = 0;
        }
        @set_time_limit(300);
        if ($current < 1) { migrate_v1_tables(); set_schema_version(1); }
        if ($current < 2) { migrate_v2_data(); set_schema_version(2); }
        if ($current < 3) { migrate_v3_seed(); set_schema_version(3); }
        if ($current < 4) { migrate_v4_message_templates_fix(); set_schema_version(4); }
        if ($current < 5) { migrate_v5_workshop_board(); set_schema_version(5); }
        if ($current < 6) { migrate_v6_own_frame(); set_schema_version(6); }
        if ($current < 7) { migrate_v7_suppliers(); set_schema_version(7); }
        if ($current < 8) { migrate_v8_supplier_deliveries(); set_schema_version(8); }
        if ($current < 9) { migrate_v9_frames(); set_schema_version(9); }
        if ($current < 10) { migrate_v10_transaction_roles(); set_schema_version(10); }
        if ($current < 11) { migrate_v11_payment_promise(); set_schema_version(11); }
        if ($current < 12) { migrate_v12_quotes(); set_schema_version(12); }
        if ($current < 13) { migrate_v13_cash_counts(); set_schema_version(13); }
        if ($current < 14) { migrate_v14_expenses(); set_schema_version(14); }
        if ($current < 15) { migrate_v15_service_orders(); set_schema_version(15); }
        if ($current < 16) { migrate_v16_revenues(); set_schema_version(16); }
        if ($current < 17) { migrate_v17_push(); set_schema_version(17); }
        if ($current < 18) { migrate_v18_public_token(); set_schema_version(18); }
        if ($current < 19) { migrate_v19_sgk(); set_schema_version(19); }
        if ($current < 20) { migrate_v20_reminders(); set_schema_version(20); }
        if ($current < 21) { migrate_v21_frames_stock(); set_schema_version(21); }
        if ($current < 22) { migrate_v22_moduller(); set_schema_version(22); }
        if ($current < 23) { migrate_v23_uts(); set_schema_version(23); }
        if ($current < 24) { migrate_v24_alis_senet(); set_schema_version(24); }
        app_log('Şema sürümü ' . $current . ' → ' . SCHEMA_VERSION . ' güncellendi.');
    } finally {
        scalar("SELECT RELEASE_LOCK('optiflow_migrate')");
    }
}

function set_schema_version(int $v): void
{
    q("INSERT INTO app_settings (setting_key, setting_value) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [(string) $v]);
}

function add_column(string $table, string $column, string $definition): void
{
    if (!column_exists($table, $column)) {
        db()->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function add_index(string $table, string $name, string $columns): void
{
    if (!index_exists($table, $name)) {
        db()->exec("ALTER TABLE `$table` ADD INDEX `$name` ($columns)");
    }
}

/**
 * Yeni tablolar mevcut "orders" tablosuyla aynı karşılaştırma kuralını (collation) kullanır;
 * aksi halde tablolar arası JOIN/karşılaştırmalar "Illegal mix of collations" hatası verir.
 */
function t_opts(): string
{
    static $opts = null;
    if ($opts === null) {
        $coll = (string) scalar("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders'");
        if (!str_starts_with($coll, 'utf8mb4_')) {
            $coll = (string) scalar('SELECT @@collation_database');
        }
        $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' . (preg_match('/^utf8mb4_[a-z0-9_]+$/', $coll) ? ' COLLATE=' . $coll : '');
    }
    return $opts;
}

/* ------------------------------------------------------------------ */
/*  v1 — tablolar ve sütunlar                                           */
/* ------------------------------------------------------------------ */

function migrate_v1_tables(): void
{
    $pdo = db();

    $pdo->exec('CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
        setting_value TEXT NULL
    ) ' . t_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS user_accounts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        full_name VARCHAR(120) NOT NULL,
        username VARCHAR(60) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'personel',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) " . t_opts());
    add_column('user_accounts', 'last_login_at', 'DATETIME NULL');
    add_column('user_accounts', 'password_changed_at', 'DATETIME NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS customers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(80) NOT NULL,
        last_name VARCHAR(80) NOT NULL,
        phone VARCHAR(20) NOT NULL DEFAULT '',
        birth_year SMALLINT UNSIGNED NULL,
        notes TEXT NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_customers_phone (phone),
        INDEX idx_customers_name (last_name, first_name)
    ) " . t_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(80) NOT NULL DEFAULT '',
        last_name VARCHAR(80) NOT NULL DEFAULT '',
        phone VARCHAR(20) NULL,
        total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
        status VARCHAR(32) NOT NULL DEFAULT 'siparis_verildi',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    // v28'de sonradan eklenen / yeni sütunlar
    add_column('orders', 'order_stage', "VARCHAR(32) NULL");
    add_column('orders', 'lens_type', 'VARCHAR(80) NULL');
    add_column('orders', 'stock_status', "VARCHAR(20) NOT NULL DEFAULT 'stokta_var'");
    add_column('orders', 'sales_person', 'VARCHAR(80) NULL');
    add_column('orders', 'customer_id', 'INT UNSIGNED NULL');
    add_column('orders', 'created_by', 'INT UNSIGNED NULL');
    add_column('orders', 'frame_info', 'VARCHAR(255) NULL');
    add_column('orders', 'notes', 'TEXT NULL');
    add_column('orders', 'promised_date', 'DATE NULL');
    add_column('orders', 'delivered_at', 'DATETIME NULL');
    add_column('orders', 'updated_at', 'DATETIME NULL');
    add_column('orders', 'sgk_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');   // SGK'nın karşılayacağı tahmini/gerçek katkı payı (reçeteden otomatik önerilir, elle düzeltilebilir)
    add_index('orders', 'idx_orders_customer', 'customer_id');
    add_index('orders', 'idx_orders_stage', 'order_stage, created_at');
    add_index('orders', 'idx_orders_created', 'created_at');
    // Eski kurulumlarda status ENUM olabilir: yeni aşamalar sığsın diye metne çevir.
    $statusType = (string) scalar("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status'");
    if ($statusType !== '' && $statusType !== 'varchar') {
        $pdo->exec("ALTER TABLE orders MODIFY status VARCHAR(32) NOT NULL DEFAULT 'siparis_verildi'");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_payments_order (order_id),
        CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) " . t_opts());
    add_column('payments', 'method', "VARCHAR(20) NOT NULL DEFAULT 'nakit'");
    add_column('payments', 'created_by', 'INT UNSIGNED NULL');
    add_index('payments', 'idx_payments_created', 'created_at');

    $pdo->exec("CREATE TABLE IF NOT EXISTS prescription_records (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        prescription_date DATE NOT NULL,
        lens_type VARCHAR(80) NULL,
        right_sph VARCHAR(16) NULL, right_cyl VARCHAR(16) NULL, right_axis VARCHAR(16) NULL, right_add VARCHAR(16) NULL,
        left_sph VARCHAR(16) NULL, left_cyl VARCHAR(16) NULL, left_axis VARCHAR(16) NULL, left_add VARCHAR(16) NULL,
        pd VARCHAR(16) NULL,
        prescription_note TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_rx_order (order_id),
        CONSTRAINT fk_rx_record_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) " . t_opts());
    add_column('prescription_records', 'customer_id', 'INT UNSIGNED NULL');
    add_column('prescription_records', 'lens_design', "VARCHAR(20) NOT NULL DEFAULT 'tek_odak_uzak'");
    add_column('prescription_records', 'lens_eyes', "VARCHAR(6) NOT NULL DEFAULT 'both'");
    add_column('prescription_records', 'right_pd', 'VARCHAR(8) NULL');
    add_column('prescription_records', 'left_pd', 'VARCHAR(8) NULL');
    add_column('prescription_records', 'right_height', 'VARCHAR(8) NULL');
    add_column('prescription_records', 'left_height', 'VARCHAR(8) NULL');
    add_column('prescription_records', 'doctor', 'VARCHAR(120) NULL');
    add_column('prescription_records', 'advisor_summary', 'TEXT NULL');
    add_column('prescription_records', 'advisor_product_id', 'INT UNSIGNED NULL');
    add_column('prescription_records', 'created_by', 'INT UNSIGNED NULL');
    add_column('prescription_records', 'updated_by', 'INT UNSIGNED NULL');
    add_index('prescription_records', 'idx_rx_customer', 'customer_id, prescription_date');

    $pdo->exec("CREATE TABLE IF NOT EXISTS near_prescription_details (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        prescription_id INT UNSIGNED NOT NULL UNIQUE,
        usage_type VARCHAR(40) NOT NULL DEFAULT 'ayri_cerceve',
        lens_type VARCHAR(80) NULL,
        right_sph VARCHAR(16) NULL, right_cyl VARCHAR(16) NULL, right_axis VARCHAR(16) NULL,
        left_sph VARCHAR(16) NULL, left_cyl VARCHAR(16) NULL, left_axis VARCHAR(16) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_near_rx FOREIGN KEY (prescription_id) REFERENCES prescription_records(id) ON DELETE CASCADE
    ) " . t_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS prescription_lens_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        prescription_id INT UNSIGNED NOT NULL,
        lens_no TINYINT UNSIGNED NOT NULL,
        lens_label VARCHAR(120) NOT NULL,
        lens_value VARCHAR(255) NULL,
        stock_status VARCHAR(20) NOT NULL DEFAULT 'stokta_var',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_rx_lens (prescription_id, lens_no),
        INDEX idx_lens_stock (stock_status),
        CONSTRAINT fk_lens_item_rx FOREIGN KEY (prescription_id) REFERENCES prescription_records(id) ON DELETE CASCADE
    ) " . t_opts());
    $pdo->exec('ALTER TABLE prescription_lens_items MODIFY lens_label VARCHAR(120) NOT NULL');
    add_column('prescription_lens_items', 'item_group', "VARCHAR(10) NOT NULL DEFAULT 'uzak'");
    add_column('prescription_lens_items', 'eye', "CHAR(1) NOT NULL DEFAULT 'R'");
    add_column('prescription_lens_items', 'lens_type', "VARCHAR(80) NOT NULL DEFAULT ''");
    add_column('prescription_lens_items', 'sph', "VARCHAR(16) NOT NULL DEFAULT ''");
    add_column('prescription_lens_items', 'cyl', "VARCHAR(16) NOT NULL DEFAULT ''");
    add_column('prescription_lens_items', 'axis', "VARCHAR(16) NOT NULL DEFAULT ''");
    add_column('prescription_lens_items', 'add_power', "VARCHAR(16) NOT NULL DEFAULT ''");
    add_column('prescription_lens_items', 'ordered_at', 'DATETIME NULL');
    add_column('prescription_lens_items', 'arrived_at', 'DATETIME NULL');

    $pdo->exec("CREATE TABLE IF NOT EXISTS lens_types (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL UNIQUE,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) " . t_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS lens_products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        brand VARCHAR(60) NOT NULL,
        name VARCHAR(120) NOT NULL,
        design VARCHAR(20) NOT NULL DEFAULT 'tek_odak',
        tier VARCHAR(12) NOT NULL DEFAULT 'dengeli',
        lens_index VARCHAR(5) NULL,
        coating VARCHAR(40) NULL,
        price DECIMAL(12,2) NULL,
        note VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_products_design (design, is_active)
    ) " . t_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS message_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(60) NOT NULL,
        body TEXT NOT NULL,
        sort_order INT NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1
    ) " . t_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        user_name VARCHAR(120) NOT NULL DEFAULT '',
        action VARCHAR(40) NOT NULL,
        entity VARCHAR(30) NOT NULL DEFAULT '',
        entity_id INT UNSIGNED NULL,
        details TEXT NULL,
        ip VARCHAR(45) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_created (created_at),
        INDEX idx_audit_entity (entity, entity_id),
        INDEX idx_audit_user (user_id)
    ) " . t_opts());

    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        username VARCHAR(60) NOT NULL DEFAULT '',
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_ip (ip, attempted_at),
        INDEX idx_login_user (username, attempted_at)
    ) " . t_opts());
}

/* ------------------------------------------------------------------ */
/*  v2 — v28 verisini yeni yapıya taşı                                  */
/* ------------------------------------------------------------------ */

function migrate_v2_data(): void
{
    // 1) Aşama alanını doldur (v28 bunu her istekte yapıyordu; artık bir kez).
    q("UPDATE orders SET order_stage = COALESCE(NULLIF(order_stage, ''), NULLIF(status, ''), 'siparis_verildi') WHERE order_stage IS NULL OR order_stage = ''");
    q("UPDATE orders SET updated_at = created_at WHERE updated_at IS NULL");

    // 2) Müşterileri siparişlerden ayır.
    //    Aynı telefon + aynı ad soyad → tek müşteri. Telefonu olmayan kayıtlar birleştirilmez
    //    (yanlış birleştirme riski); Ayarlar > Mükerrer müşteriler ekranından elle birleştirilebilir.
    $map = [];
    $st = q('SELECT id, first_name, last_name, phone, created_at, created_by FROM orders WHERE customer_id IS NULL ORDER BY id');
    while ($o = $st->fetch()) {
        $phone = normalize_phone((string) $o['phone']) ?? preg_replace('/\D+/', '', (string) $o['phone']);
        $first = tr_title((string) $o['first_name']);
        $last = tr_title((string) $o['last_name']);
        $key = $phone !== '' ? $phone . '|' . tr_lower($first) . '|' . tr_lower($last) : null;
        if ($key !== null && isset($map[$key])) {
            $cid = $map[$key];
        } else {
            $cid = insert('customers', [
                'first_name' => $first !== '' ? $first : '—',
                'last_name'  => $last,
                'phone'      => (string) $phone,
                'created_at' => $o['created_at'],
            ]);
            if ($key !== null) {
                $map[$key] = $cid;
            }
        }
        q('UPDATE orders SET customer_id = ?, phone = ? WHERE id = ?', [$cid, (string) $phone, $o['id']]);
    }
    if (!constraint_exists('orders', 'fk_orders_customer')) {
        db()->exec('ALTER TABLE orders MODIFY customer_id INT UNSIGNED NOT NULL');
        db()->exec('ALTER TABLE orders ADD CONSTRAINT fk_orders_customer FOREIGN KEY (customer_id) REFERENCES customers(id)');
    }

    // 3) Reçeteleri müşteriye bağla, kullanım şeklini çıkar, değerleri normalleştir.
    q('UPDATE prescription_records r JOIN orders o ON o.id = r.order_id SET r.customer_id = o.customer_id WHERE r.customer_id IS NULL');
    $rxs = rows('SELECT r.*, n.usage_type FROM prescription_records r LEFT JOIN near_prescription_details n ON n.prescription_id = r.id');
    foreach ($rxs as $r) {
        $design = 'tek_odak_uzak';
        $lens = tr_lower((string) $r['lens_type']);
        if ($r['usage_type'] === 'progressive' || str_contains($lens, 'progressive')) {
            $design = 'progressive';
        } elseif (str_contains($lens, 'bifokal')) {
            $design = 'bifokal';
        } elseif ($r['usage_type'] === 'ayri_cerceve') {
            $design = 'ayri_uzak_yakin';
        }
        $set = ['lens_design' => $design];
        foreach (['right', 'left'] as $side) {
            foreach (['sph' => [-30, 30], 'cyl' => [-10, 10], 'add' => [0, 4]] as $f => $range) {
                $raw = (string) $r[$side . '_' . $f];
                $norm = parse_diopter($raw, $range[0], $range[1]);
                if ($raw !== '' && $norm !== null) {
                    $set[$side . '_' . $f] = $norm;
                }
            }
            $ax = parse_axis((string) $r[$side . '_axis']);
            if ($ax !== null && $r[$side . '_axis'] !== null) {
                $set[$side . '_axis'] = $ax;
            }
        }
        update('prescription_records', $set, 'id = ?', [$r['id']]);
        if ($design === 'progressive') {
            q("UPDATE near_prescription_details SET usage_type = 'progressive' WHERE prescription_id = ?", [$r['id']]);
            // v28 hatası: progressive için ayrıca yakın cam satırı açılmış (depoya 4 cam). Fazlalığı kaldır.
            q("DELETE FROM prescription_lens_items WHERE prescription_id = ? AND lens_no IN (3, 4) AND stock_status <> 'stokta_var'", [$r['id']]);
        } elseif ($design === 'ayri_uzak_yakin') {
            q("UPDATE near_prescription_details SET usage_type = 'ayri_uzak_yakin' WHERE prescription_id = ?", [$r['id']]);
        }
    }

    // 4) Cam satırlarını ayrıştır: "Uzak Sağ göz · Blue" + "SPH -2,00 / CYL -0.75"
    foreach (rows("SELECT i.*, r.lens_design AS d FROM prescription_lens_items i JOIN prescription_records r ON r.id = i.prescription_id WHERE i.lens_type = ''") as $it) {
        $label = (string) $it['lens_label'];
        $group = str_starts_with(tr_lower($label), 'yakın') ? 'yakin' : ($it['d'] === 'progressive' || $it['d'] === 'bifokal' ? 'cok_odak' : 'uzak');
        $eye = (str_contains($label, 'Sol') || (int) $it['lens_no'] % 2 === 0) ? 'L' : 'R';
        $lensType = str_contains($label, '·') ? trim(explode('·', $label, 2)[1]) : $label;
        $sph = $cyl = '';
        if (preg_match('/SPH\s*([^\s\/]*)/u', (string) $it['lens_value'], $m)) {
            $sph = parse_diopter($m[1], -30, 30) ?? mb_substr($m[1], 0, 16);
        }
        if (preg_match('/CYL\s*([^\s\/]*)/u', (string) $it['lens_value'], $m)) {
            $cyl = parse_diopter($m[1], -10, 10) ?? mb_substr($m[1], 0, 16);
        }
        update('prescription_lens_items', [
            'item_group' => $group,
            'eye'        => $eye,
            'lens_type'  => mb_substr($lensType, 0, 80),
            'sph'        => (string) $sph,
            'cyl'        => (string) $cyl,
        ], 'id = ?', [$it['id']]);
    }
    foreach (rows('SELECT * FROM prescription_lens_items') as $it) {
        update('prescription_lens_items', ['lens_label' => lens_item_label($it), 'lens_value' => lens_item_value($it)], 'id = ?', [$it['id']]);
    }
    q("UPDATE prescription_lens_items SET arrived_at = updated_at WHERE stock_status = 'stokta_var' AND arrived_at IS NULL AND updated_at > created_at");

    // 5) Satış personeli metnini kullanıcıya bağla.
    q('UPDATE orders o JOIN user_accounts u ON u.full_name = o.sales_person SET o.created_by = u.id WHERE o.created_by IS NULL');
}

function constraint_exists(string $table, string $name): bool
{
    return (bool) scalar('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?', [$table, $name]);
}

/* ------------------------------------------------------------------ */
/*  v3 — başlangıç verileri                                             */
/* ------------------------------------------------------------------ */

function migrate_v3_seed(): void
{
    $defaults = [
        'shop_name'         => 'OptiFlow',
        'shop_phone'        => '',
        'shop_address'      => '',
        'staff_see_amounts' => '1',
        'session_idle_minutes' => '480',
    ];
    foreach ($defaults as $k => $v) {
        q('INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)', [$k, $v]);
    }

    if (!(int) scalar('SELECT COUNT(*) FROM lens_types')) {
        $legacy = ['Organik', 'Colormatik', 'Antirefle', 'Blue', 'Progressive beyaz', 'Progressive colormatic', 'Colormatic blue', 'Bifokal', 'Anti glare', 'UV420', 'RX Üretim', 'Ara Depo Özel Cam', 'Ana Depo Özel Stok Cam'];
        // Kayıtlarda geçen ama listede olmayan tipler de eklenir (eski veri seçilebilir kalsın).
        foreach (rows("SELECT DISTINCT lens_type FROM orders WHERE lens_type <> '' UNION SELECT DISTINCT lens_type FROM prescription_records WHERE lens_type <> ''") as $r) {
            if (!in_array($r['lens_type'], $legacy, true)) {
                $legacy[] = $r['lens_type'];
            }
        }
        foreach ($legacy as $i => $name) {
            q('INSERT IGNORE INTO lens_types (name, sort_order) VALUES (?, ?)', [$name, ($i + 1) * 10]);
        }
    }

    if (!(int) scalar('SELECT COUNT(*) FROM lens_products')) {
        $note = 'v28 asistanından aktarıldı · tasarım, indeks ve fiyatı kontrol edin';
        $products = [
            ['Smart Vision', 'Smart ID', 'tek_odak', 'ekonomik', null],
            ['Kodak', 'Kodak Atlas', 'tek_odak', 'dengeli', 16750],
            ['VisionArt', 'VisionArt Single Vision', 'tek_odak', 'dengeli', null],
            ['Opak Lens', 'Opak Lens tek odak', 'tek_odak', 'ekonomik', null],
            ['Neo', 'Neo tek odak', 'tek_odak', 'ekonomik', null],
            ['TORA', 'TORA RX temel seri', 'tek_odak', 'ekonomik', 4150],
            ['Smart Vision', 'Smart ID Progressive', 'progressive', 'ekonomik', null],
            ['Nikon', 'Nikon Presio First', 'progressive', 'dengeli', 12250],
            ['Kodak', 'Kodak Intro / Precise', 'progressive', 'ekonomik', 7750],
            ['Nikon', 'Nikon Z Suite', 'progressive', 'premium', 23750],
            ['Nikon', 'Nikon Seemax Ultimate Z', 'progressive', 'premium', 26700],
            ['Kodak', 'Kodak Unique DRO', 'progressive', 'premium', 29500],
            ['VisionArt', 'VisionArt Progressive', 'progressive', 'dengeli', null],
            ['Sky Vision', 'Sky Vision Progressive', 'progressive', 'dengeli', null],
        ];
        foreach ($products as $p) {
            insert('lens_products', ['brand' => $p[0], 'name' => $p[1], 'design' => $p[2], 'tier' => $p[3], 'price' => $p[4], 'coating' => 'Antirefle', 'note' => $note]);
        }
    }

    if (!(int) scalar('SELECT COUNT(*) FROM message_templates')) {
        $templates = [
            ['Sipariş alındı', "Merhaba {ad}, {siparis_no} numaralı gözlük siparişiniz alınmıştır. Hazır olduğunda size haber vereceğiz.\n{magaza}"],
            ['Sipariş hazır', "Merhaba {ad}, {siparis_no} numaralı gözlüğünüz hazırlandı. Dilediğiniz zaman {magaza}'dan teslim alabilirsiniz."],
            ['Bakiye hatırlatma', "Merhaba {ad}, {siparis_no} numaralı siparişinizde {kalan} tutarında bakiye bulunmaktadır. Uygun olduğunuzda ödemenizi rica ederiz.\n{magaza}"],
            ['Kontrol hatırlatma', "Merhaba {ad}, gözlüğünüzü kullanırken bir sorun yaşıyorsanız ücretsiz kontrol için {magaza}'ya uğrayabilirsiniz."],
        ];
        foreach ($templates as $i => $t) {
            insert('message_templates', ['name' => $t[0], 'body' => $t[1], 'sort_order' => ($i + 1) * 10]);
        }
    }

    // İlk kurulum: hiç kullanıcı yoksa config.php'deki yönetici bir kez oluşturulur.
    if (!(int) scalar('SELECT COUNT(*) FROM user_accounts') && config('admin_username') && config('admin_password')) {
        insert('user_accounts', [
            'full_name'     => 'Yönetici',
            'username'      => (string) config('admin_username'),
            'password_hash' => password_hash((string) config('admin_password'), PASSWORD_DEFAULT),
            'role'          => 'super_yetkili',
        ]);
    }
}

/* ------------------------------------------------------------------ */
/*  v4 — bazı kurulumlarda "message_templates" tablosu v1'den önce zaten     */
/*  var olduğu için (eski/kısmi bir denemeden kalma), CREATE TABLE IF NOT   */
/*  EXISTS içindeki "is_active" ve "sort_order" sütunları hiç eklenmemiş    */
/*  olabilir. Bu, telefonu kayıtlı müşterilerin sipariş sayfasını açarken   */
/*  "Unknown column 'is_active'" hatasına yol açar. Eksikse ekle, tablo     */
/*  boşsa varsayılan şablonları bir kez ekle.                               */
/* ------------------------------------------------------------------ */

function migrate_v4_message_templates_fix(): void
{
    if (!table_exists('message_templates')) {
        return;
    }
    // Sütunları normalde eksik olabilecek şekilde tamamla.
    add_column('message_templates', 'sort_order', 'INT NOT NULL DEFAULT 0');
    add_column('message_templates', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');

    // Bazı kurulumlarda "message_templates" adında tamamen uyumsuz (id/name/body
    // içermeyen) bir tablo önceden var olabilir. Bu durumda sütun eklemek yetmez;
    // eski tabloyu silmeden kenara alıp doğru yapıda yenisini oluştur.
    $hasId = column_exists('message_templates', 'id');
    $hasName = column_exists('message_templates', 'name');
    $hasBody = column_exists('message_templates', 'body');
    if (!$hasId || !$hasName || !$hasBody) {
        $backup = 'message_templates_eski_yedek';
        $suffix = 1;
        while (table_exists($backup)) {
            $suffix++;
            $backup = 'message_templates_eski_yedek_' . $suffix;
        }
        db()->exec("RENAME TABLE `message_templates` TO `$backup`");
        db()->exec("CREATE TABLE `message_templates` (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(60) NOT NULL,
            body TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) " . t_opts());
        app_log("message_templates tablosu uyumsuzdu (id/name/body eksik), '$backup' adıyla yedeklendi ve yeniden oluşturuldu.");
    }

    if (!(int) scalar('SELECT COUNT(*) FROM message_templates')) {
        $templates = [
            ['Sipariş alındı', "Merhaba {ad}, {siparis_no} numaralı gözlük siparişiniz alınmıştır. Hazır olduğunda size haber vereceğiz.\n{magaza}"],
            ['Sipariş hazır', "Merhaba {ad}, {siparis_no} numaralı gözlüğünüz hazırlandı. Dilediğiniz zaman {magaza}'dan teslim alabilirsiniz."],
            ['Bakiye hatırlatma', "Merhaba {ad}, {siparis_no} numaralı siparişinizde {kalan} tutarında bakiye bulunmaktadır. Uygun olduğunuzda ödemenizi rica ederiz.\n{magaza}"],
            ['Kontrol hatırlatma', "Merhaba {ad}, gözlüğünüzü kullanırken bir sorun yaşıyorsanız ücretsiz kontrol için {magaza}'ya uğrayabilirsiniz."],
        ];
        foreach ($templates as $i => $t) {
            insert('message_templates', ['name' => $t[0], 'body' => $t[1], 'sort_order' => ($i + 1) * 10]);
        }
    }
}

/* ------------------------------------------------------------------ */
/*  v5 — Atölye panosu: "Atölyede" aşamasını Montajda / Kontrolde diye     */
/*  ikiye ayırmak ve işi kimin yaptığını / kimin kontrol ettiğini kaydetmek */
/*  için sipariş tablosuna alanlar ekler. Geriye dönük tüm mevcut          */
/*  "Atölyede" siparişler otomatik olarak "Montajda" ile başlar.           */
/* ------------------------------------------------------------------ */

function migrate_v5_workshop_board(): void
{
    add_column('orders', 'workshop_stage', "VARCHAR(16) NULL");
    add_column('orders', 'assigned_to', 'INT UNSIGNED NULL');
    add_column('orders', 'qc_by', 'INT UNSIGNED NULL');
    add_column('orders', 'qc_at', 'DATETIME NULL');
    add_index('orders', 'idx_orders_workshop', 'order_stage, workshop_stage');

    q("UPDATE orders SET workshop_stage = 'montajda' WHERE order_stage = 'atolyede' AND (workshop_stage IS NULL OR workshop_stage = '')");
}

/* ------------------------------------------------------------------ */
/*  v6 — Bazı siparişlerde camlar gelmiş olsa da montaj için müşterinin  */
/*  kendi çerçevesini getirmesi beklenebilir. Bu bekleyişi ayrı bir alan */
/*  olarak işaretleyip atölye panosunda belirgin biçimde göstermek için. */
/* ------------------------------------------------------------------ */

function migrate_v6_own_frame(): void
{
    add_column('orders', 'own_frame_pending', 'TINYINT(1) NOT NULL DEFAULT 0');
}

/* ------------------------------------------------------------------ */
/*  v7 — Tedarikçi & cari hesap modülü: hangi camın hangi tedarikçiden   */
/*  alındığını etiketlemek, tedarikçi faturalarını ve onlara yapılan     */
/*  ödemeleri kaydedip bakiye (cari) hesaplayabilmek için.               */
/* ------------------------------------------------------------------ */

function migrate_v7_suppliers(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS suppliers (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        contact_name VARCHAR(120) NULL,
        phone VARCHAR(20) NULL,
        address VARCHAR(255) NULL,
        tax_no VARCHAR(30) NULL,
        note TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) " . t_opts());

    db()->exec("CREATE TABLE IF NOT EXISTS supplier_invoices (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT UNSIGNED NOT NULL,
        invoice_no VARCHAR(60) NOT NULL,
        invoice_date DATE NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        note VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('supplier_invoices', 'idx_supplier_invoices', 'supplier_id, invoice_date');
    if (!constraint_exists('supplier_invoices', 'fk_supplier_invoice')) {
        db()->exec('ALTER TABLE supplier_invoices ADD CONSTRAINT fk_supplier_invoice FOREIGN KEY (supplier_id) REFERENCES suppliers(id)');
    }

    db()->exec("CREATE TABLE IF NOT EXISTS supplier_payments (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT UNSIGNED NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        method VARCHAR(20) NOT NULL DEFAULT 'havale',
        note VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('supplier_payments', 'idx_supplier_payments', 'supplier_id, created_at');
    if (!constraint_exists('supplier_payments', 'fk_supplier_payment')) {
        db()->exec('ALTER TABLE supplier_payments ADD CONSTRAINT fk_supplier_payment FOREIGN KEY (supplier_id) REFERENCES suppliers(id)');
    }

    add_column('prescription_lens_items', 'supplier_id', 'INT UNSIGNED NULL');
    if (!constraint_exists('prescription_lens_items', 'fk_lens_item_supplier')) {
        db()->exec('ALTER TABLE prescription_lens_items ADD CONSTRAINT fk_lens_item_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL');
    }
}

/* ------------------------------------------------------------------ */
/*  v8 — Camlar tedarikçiden "geldi" olarak işaretlendiğinde otomatik    */
/*  bir teslimat (alışveriş) kaydı oluşur; tedarikçi sayfasında bu       */
/*  teslimata fatura no + tutar girilince gerçek faturaya dönüşür ve     */
/*  birim maliyet ilgili cam satırlarına otomatik dağıtılır.             */
/* ------------------------------------------------------------------ */

function migrate_v8_supplier_deliveries(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS supplier_deliveries (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT UNSIGNED NOT NULL,
        delivered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        item_count INT UNSIGNED NOT NULL DEFAULT 0,
        invoice_id INT UNSIGNED NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('supplier_deliveries', 'idx_supplier_deliveries', 'supplier_id, invoice_id');
    if (!constraint_exists('supplier_deliveries', 'fk_delivery_supplier')) {
        db()->exec('ALTER TABLE supplier_deliveries ADD CONSTRAINT fk_delivery_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id)');
    }
    if (!constraint_exists('supplier_deliveries', 'fk_delivery_invoice')) {
        db()->exec('ALTER TABLE supplier_deliveries ADD CONSTRAINT fk_delivery_invoice FOREIGN KEY (invoice_id) REFERENCES supplier_invoices(id) ON DELETE SET NULL');
    }

    add_column('prescription_lens_items', 'delivery_id', 'INT UNSIGNED NULL');
    if (!constraint_exists('prescription_lens_items', 'fk_lens_item_delivery')) {
        db()->exec('ALTER TABLE prescription_lens_items ADD CONSTRAINT fk_lens_item_delivery FOREIGN KEY (delivery_id) REFERENCES supplier_deliveries(id) ON DELETE SET NULL');
    }
    add_column('prescription_lens_items', 'unit_cost', 'DECIMAL(10,2) NULL');
}

/* ------------------------------------------------------------------ */
/*  v9 — Çerçeve maliyeti (tahmini): çerçeveler yılda 1-2 kez toplu      */
/*  alındığı için tek tek sipariş bağlantısı kurulamıyor. Bunun yerine   */
/*  marka bazlı bir ortalama maliyet kataloğu tutulur: tedarikçi         */
/*  faturasına adet + tutar girilince o markanın ortalama maliyeti       */
/*  güncellenir; siparişte seçilen marka üzerinden tahmini maliyet        */
/*  hesaplanır.                                                          */
/* ------------------------------------------------------------------ */

function migrate_v9_frames(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS frame_products (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT UNSIGNED NULL,
        brand VARCHAR(80) NOT NULL,
        model VARCHAR(80) NULL,
        total_qty INT UNSIGNED NOT NULL DEFAULT 0,
        total_spent DECIMAL(12,2) NOT NULL DEFAULT 0,
        avg_cost DECIMAL(10,2) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    if (!constraint_exists('frame_products', 'fk_frame_supplier')) {
        db()->exec('ALTER TABLE frame_products ADD CONSTRAINT fk_frame_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL');
    }

    add_column('orders', 'frame_product_id', 'INT UNSIGNED NULL');
    if (!constraint_exists('orders', 'fk_orders_frame')) {
        db()->exec('ALTER TABLE orders ADD CONSTRAINT fk_orders_frame FOREIGN KEY (frame_product_id) REFERENCES frame_products(id) ON DELETE SET NULL');
    }
}

/* ------------------------------------------------------------------ */
/*  v10 — Tek "işlem" çatısı: gözlük siparişi / güneş gözlüğü satışı /   */
/*  tamir-bakım hepsi aynı "orders" tablosunda, işlem türüyle ayrılır.  */
/*  Uçtan uca kim yaptı: satışı alan (created_by) ve atölye/kontrol     */
/*  (assigned_to / qc_by) zaten vardı; teslim eden eksikti, eklenir.    */
/* ------------------------------------------------------------------ */

function migrate_v10_transaction_roles(): void
{
    add_column('orders', 'transaction_type', "VARCHAR(20) NOT NULL DEFAULT 'gozluk'");
    add_column('orders', 'delivered_by', 'INT UNSIGNED NULL');
}

/* ------------------------------------------------------------------ */
/*  v11 — Müşteri ürünü alıp bakiyeyi belirli bir tarihte ödemeyi       */
/*  söz verdiğinde bunu kaydedebilmek için (teslim fişinde görünür).    */
/* ------------------------------------------------------------------ */

function migrate_v11_payment_promise(): void
{
    add_column('orders', 'balance_promise_date', 'DATE NULL');
}

/* ------------------------------------------------------------------ */
/*  v12 — Teklif (proforma): kararsız müşteriye iyi/daha iyi/en iyi üç  */
/*  seçenekli fiyat teklifi. Sipariş değildir, siparişe dönüşene kadar  */
/*  sisteme kaydolmaz.                                                  */
/* ------------------------------------------------------------------ */

function migrate_v12_quotes(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS quotes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NULL,
        customer_name VARCHAR(160) NOT NULL,
        customer_phone VARCHAR(20) NULL,
        note VARCHAR(255) NULL,
        opt1_name VARCHAR(60) NULL, opt1_desc VARCHAR(500) NULL, opt1_price DECIMAL(12,2) NULL,
        opt2_name VARCHAR(60) NULL, opt2_desc VARCHAR(500) NULL, opt2_price DECIMAL(12,2) NULL,
        opt3_name VARCHAR(60) NULL, opt3_desc VARCHAR(500) NULL, opt3_price DECIMAL(12,2) NULL,
        converted_order_id INT UNSIGNED NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    if (!constraint_exists('quotes', 'fk_quote_customer')) {
        db()->exec('ALTER TABLE quotes ADD CONSTRAINT fk_quote_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL');
    }
    if (!constraint_exists('quotes', 'fk_quote_order')) {
        db()->exec('ALTER TABLE quotes ADD CONSTRAINT fk_quote_order FOREIGN KEY (converted_order_id) REFERENCES orders(id) ON DELETE SET NULL');
    }
}

/* ------------------------------------------------------------------ */
/*  v13 — Gün sonu kasa sayımı: sayılan nakit tutarını sistemin         */
/*  beklediği tutarla kıyaslayıp fark bırakmak için (tarihçe olarak).   */
/* ------------------------------------------------------------------ */

function migrate_v13_cash_counts(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS cash_counts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        count_date DATE NOT NULL,
        counted_amount DECIMAL(12,2) NOT NULL,
        expected_amount DECIMAL(12,2) NOT NULL,
        difference DECIMAL(12,2) NOT NULL,
        note VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('cash_counts', 'idx_cash_counts_date', 'count_date');
}

/* ------------------------------------------------------------------ */
/*  v14 — Günlük giderler: kasadan çıkan küçük harcamalar (kırtasiye,   */
/*  temizlik vb.) kaydedilir; hem kasa sayımındaki farkın nedeni        */
/*  olabilir hem de günlük gider takibi sağlar.                        */
/* ------------------------------------------------------------------ */

function migrate_v14_expenses(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS expenses (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        expense_date DATE NOT NULL,
        description VARCHAR(160) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        method VARCHAR(20) NOT NULL DEFAULT 'nakit',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('expenses', 'idx_expenses_date', 'expense_date');
}

/* ------------------------------------------------------------------ */
/*  v15 — Tamir/Bakım kaydı gözlük siparişinden farklı bilgiler ister:  */
/*  cam tipi/reçete yerine yapılan işlem türü ve ücretsiz mi bilgisi.   */
/* ------------------------------------------------------------------ */

function migrate_v15_service_orders(): void
{
    add_column('orders', 'service_type', 'VARCHAR(30) NULL');
    add_column('orders', 'is_free', 'TINYINT(1) NOT NULL DEFAULT 0');
}

/* ------------------------------------------------------------------ */
/*  v16 — Günlük giderler gibi, sipariş dışı gelirleri de (ör. ufak     */
/*  ürün satışı, kasaya eklenen para) kaydedebilmek için.               */
/* ------------------------------------------------------------------ */

function migrate_v16_revenues(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS revenues (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        revenue_date DATE NOT NULL,
        description VARCHAR(160) NOT NULL,
        amount DECIMAL(12,2) NOT NULL,
        method VARCHAR(20) NOT NULL DEFAULT 'nakit',
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('revenues', 'idx_revenues_date', 'revenue_date');
}

/* ------------------------------------------------------------------ */
/*  v17 — Telefon bildirimi (web push) abonelikleri. Her cihaz/tarayıcı */
/*  için ayrı bir satır tutulur; iptal edilenler otomatik silinir.      */
/* ------------------------------------------------------------------ */

function migrate_v17_push(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        endpoint VARCHAR(500) NOT NULL,
        endpoint_hash CHAR(64) NOT NULL UNIQUE,
        p256dh VARCHAR(140) NOT NULL,
        auth_secret VARCHAR(60) NOT NULL,
        device VARCHAR(120) NULL,
        fail_count INT NOT NULL DEFAULT 0,
        last_ok_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('push_subscriptions', 'idx_push_user', 'user_id');
}

/* ------------------------------------------------------------------ */
/*  v18 — Müşteri takip sayfası: her siparişe tahmin edilemez, benzersiz */
/*  bir anahtar. Fişteki karekod bu anahtarı taşır.                     */
/* ------------------------------------------------------------------ */

function migrate_v18_public_token(): void
{
    add_column('orders', 'public_token', 'VARCHAR(24) NULL');
    if (!index_exists('orders', 'idx_orders_public_token')) {
        db()->exec('ALTER TABLE `orders` ADD UNIQUE INDEX `idx_orders_public_token` (`public_token`)');
    }
}

/* ------------------------------------------------------------------ */
/*  v19 — SGK / Medula Optik köprüsü: mağaza bilgisayarından aktarılan  */
/*  reçete metinleri ve reçete kaydına SGK rapor alanları.              */
/* ------------------------------------------------------------------ */

function migrate_v19_sgk(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS sgk_incoming (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NULL,
        kaynak VARCHAR(20) NOT NULL DEFAULT 'kopru',
        baslik VARCHAR(160) NULL,
        raw_text MEDIUMTEXT NOT NULL,
        parsed TEXT NULL,
        used_order_id INT UNSIGNED NULL,
        used_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('sgk_incoming', 'idx_sgk_user', 'user_id, created_at');

    add_column('user_accounts', 'bridge_token', 'VARCHAR(64) NULL');
    add_column('prescription_records', 'sgk_rapor_no', 'VARCHAR(40) NULL');
    add_column('prescription_records', 'sgk_rapor_tarihi', 'DATE NULL');
}

/* ------------------------------------------------------------------ */
/*  v20 — Hatırlatma merkezi: gözlük yenileme, SGK hakkı, planlı arama  */
/*  ve teslim alınmamış gözlükler. Otomatik listeler hesaplanır;        */
/*  bu tablo yalnızca "yapıldı / ertelendi / kapatıldı" kaydını ve elle  */
/*  eklenen hatırlatmaları tutar.                                       */
/* ------------------------------------------------------------------ */

function migrate_v20_reminders(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS reminders (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        order_id INT UNSIGNED NULL,
        kind VARCHAR(16) NOT NULL DEFAULT 'manuel',
        due_date DATE NULL,
        note VARCHAR(255) NULL,
        status VARCHAR(12) NOT NULL DEFAULT 'bekliyor',
        channel VARCHAR(12) NULL,
        done_at DATETIME NULL,
        done_by INT UNSIGNED NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_rem_customer (customer_id, kind, status),
        INDEX idx_rem_due (status, due_date)
    ) " . t_opts());

    // Varsayılan süreler ve mesaj metinleri (daha önce kaydedilmişse dokunulmaz)
    $varsayilan = [
        'reminder_renew_months'   => '24',
        'reminder_sgk_months'     => '36',
        'reminder_sgk_child_months' => '12',
        'reminder_pickup_days'    => '7',
        'reminder_msg_yenileme'   => "Merhaba {ad}, {magaza}'tan yazıyoruz. Son gözlüğünüzün üzerinden {ay} ay geçmiş. Dilerseniz ücretsiz göz kontrolü ve gözlük bakımı için mağazamıza bekleriz. İyi günler dileriz.",
        'reminder_msg_sgk'        => "Merhaba {ad}, {magaza}'tan yazıyoruz. Son gözlük reçetenizin üzerinden {ay} ay geçti; SGK gözlük hakkınız yenilenmiş olabilir. Uygun olduğunuzda mağazamıza bekleriz.",
        'reminder_msg_teslim'     => "Merhaba {ad}, {siparis_no} numaralı gözlüğünüz hazır ve mağazamızda sizi bekliyor. Uygun olduğunuzda alabilirsiniz. {magaza}",
        'reminder_msg_manuel'     => "Merhaba {ad}, {magaza}'tan yazıyoruz. Size ulaşmak istedik. İyi günler dileriz.",
        'reminder_msg_bakiye'     => "Merhaba {ad}, {siparis_no} numaralı siparişinizde {kalan} tutarında bakiye görünüyor. Uygun olduğunuzda mağazamıza bekleriz. {magaza}",
    ];
    foreach ($varsayilan as $k => $v) {
        q('INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)', [$k, $v]);
    }
    setting('__reload__');
}

/* ------------------------------------------------------------------ */
/*  v21 — Çerçeve stoğu (vitrin sayımı, barkod, kritik adet), personel  */
/*  prim oranı ve yedek/günlük özet ayarları.                           */
/* ------------------------------------------------------------------ */

function migrate_v21_frames_stock(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS frame_items (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        brand VARCHAR(80) NOT NULL,
        model VARCHAR(80) NULL,
        color VARCHAR(60) NULL,
        size VARCHAR(40) NULL,
        barcode VARCHAR(64) NULL,
        qty INT NOT NULL DEFAULT 0,
        min_qty INT NOT NULL DEFAULT 1,
        cost DECIMAL(10,2) NULL,
        price DECIMAL(10,2) NULL,
        supplier_id INT UNSIGNED NULL,
        shelf VARCHAR(40) NULL,
        note VARCHAR(255) NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('frame_items', 'idx_frame_items_brand', 'brand, model');
    add_index('frame_items', 'idx_frame_items_active', 'is_active, qty');
    if (!index_exists('frame_items', 'idx_frame_items_barcode')) {
        db()->exec('ALTER TABLE frame_items ADD UNIQUE INDEX idx_frame_items_barcode (barcode)');
    }

    db()->exec("CREATE TABLE IF NOT EXISTS frame_moves (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        frame_item_id INT UNSIGNED NOT NULL,
        delta INT NOT NULL,
        reason VARCHAR(16) NOT NULL DEFAULT 'giris',
        order_id INT UNSIGNED NULL,
        note VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) " . t_opts());
    add_index('frame_moves', 'idx_frame_moves_item', 'frame_item_id, created_at');
    add_index('frame_moves', 'idx_frame_moves_order', 'order_id');

    // Sipariş hangi stok çerçevesini götürdü
    add_column('orders', 'frame_item_id', 'INT UNSIGNED NULL');
    add_index('orders', 'idx_orders_frame_item', 'frame_item_id');

    // Personel prim oranı (boşsa Ayarlar'daki genel oran kullanılır)
    add_column('user_accounts', 'commission_rate', 'DECIMAL(5,2) NULL');

    $varsayilan = [
        'commission_rate'     => '0',
        'backup_warn_days'    => '7',
        'daily_summary'       => '0',
        'daily_summary_hour'  => '19',
    ];
    foreach ($varsayilan as $k => $v) {
        q('INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)', [$k, $v]);
    }
    setting('__reload__');
}

/* ------------------------------------------------------------------ */
/*  v22 (4.12.0) — WhatsApp kuyruğu, lens takibi, ödeme linki,          */
/*  cam siparişi, e-Arşiv/e-Fatura taslakları, SGK e-reçete no          */
/*  Yalnızca YENİ tablo/sütun ekler; mevcut veriye dokunmaz.            */
/* ------------------------------------------------------------------ */

function migrate_v22_moduller(): void
{
    // Müşteri iletişim izni (ticari/bilgilendirme dışı mesajlar için). NULL = sorulmadı.
    add_column('customers', 'wa_izin', 'TINYINT(1) NULL');
    add_column('customers', 'wa_izin_tarihi', 'DATETIME NULL');
    add_column('customers', 'wa_izin_kaynak', 'VARCHAR(40) NULL');

    db()->exec("CREATE TABLE IF NOT EXISTS wa_mesajlar (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NULL,
        order_id INT UNSIGNED NULL,
        olay VARCHAR(24) NOT NULL,
        telefon VARCHAR(20) NOT NULL,
        metin TEXT NOT NULL,
        parametreler TEXT NULL,
        durum VARCHAR(12) NOT NULL DEFAULT 'bekliyor',
        kanal VARCHAR(8) NOT NULL DEFAULT 'link',
        deneme TINYINT UNSIGNED NOT NULL DEFAULT 0,
        son_hata VARCHAR(255) NULL,
        dis_id VARCHAR(96) NULL,
        tekil VARCHAR(80) NULL,
        planlanan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        gonderilme DATETIME NULL,
        gonderen INT UNSIGNED NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_wa_tekil (tekil),
        KEY idx_wa_durum (durum, planlanan),
        KEY idx_wa_musteri (customer_id)
    ) " . t_opts());

    db()->exec("CREATE TABLE IF NOT EXISTS lens_takip (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        customer_id INT UNSIGNED NOT NULL,
        order_id INT UNSIGNED NULL,
        urun VARCHAR(160) NOT NULL,
        goz VARCHAR(6) NOT NULL DEFAULT 'cift',
        kutu_adet SMALLINT UNSIGNED NOT NULL DEFAULT 1,
        kutu_gun SMALLINT UNSIGNED NOT NULL DEFAULT 30,
        baslangic DATE NOT NULL,
        bitis DATE NOT NULL,
        durum VARCHAR(12) NOT NULL DEFAULT 'aktif',
        not_metin VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_lens_bitis (durum, bitis),
        KEY idx_lens_musteri (customer_id)
    ) " . t_opts());

    db()->exec("CREATE TABLE IF NOT EXISTS odeme_linkleri (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NOT NULL,
        customer_id INT UNSIGNED NULL,
        tutar DECIMAL(12,2) NOT NULL,
        saglayici VARCHAR(12) NOT NULL DEFAULT 'paytr',
        dis_id VARCHAR(40) NULL,
        link VARCHAR(255) NULL,
        callback_id VARCHAR(64) NOT NULL,
        durum VARCHAR(12) NOT NULL DEFAULT 'olusturuldu',
        odenen DECIMAL(12,2) NULL,
        merchant_oid VARCHAR(64) NULL,
        payment_id INT UNSIGNED NULL,
        test TINYINT(1) NOT NULL DEFAULT 0,
        son_kullanim DATETIME NULL,
        hata VARCHAR(255) NULL,
        odeme_at DATETIME NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_odeme_callback (callback_id),
        UNIQUE KEY uq_odeme_oid (merchant_oid),
        KEY idx_odeme_siparis (order_id)
    ) " . t_opts());

    // Cam sipariş fişi: bir tedarikçiye giden, birden çok siparişin camlarını içeren toplu sipariş.
    db()->exec("CREATE TABLE IF NOT EXISTS cam_siparisleri (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT UNSIGNED NULL,
        durum VARCHAR(14) NOT NULL DEFAULT 'taslak',
        kanal VARCHAR(12) NULL,
        tedarikci_ref VARCHAR(60) NULL,
        notlar VARCHAR(500) NULL,
        gonderim_at DATETIME NULL,
        teslim_at DATETIME NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_cam_durum (durum, supplier_id)
    ) " . t_opts());
    add_column('prescription_lens_items', 'cam_siparis_id', 'INT UNSIGNED NULL');
    add_index('prescription_lens_items', 'idx_pli_cam_siparis', 'cam_siparis_id');
    add_column('suppliers', 'email', 'VARCHAR(120) NULL');

    db()->exec("CREATE TABLE IF NOT EXISTS faturalar (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_id INT UNSIGNED NULL,
        customer_id INT UNSIGNED NULL,
        fatura_no VARCHAR(16) NULL,
        uuid CHAR(36) NOT NULL,
        profil VARCHAR(14) NOT NULL DEFAULT 'EARSIVFATURA',
        tip VARCHAR(10) NOT NULL DEFAULT 'SATIS',
        durum VARCHAR(12) NOT NULL DEFAULT 'taslak',
        duzenleme DATETIME NULL,
        alici_tip VARCHAR(6) NOT NULL DEFAULT 'kisi',
        alici_ad VARCHAR(80) NULL,
        alici_soyad VARCHAR(80) NULL,
        alici_unvan VARCHAR(200) NULL,
        alici_kimlik VARCHAR(11) NULL,
        alici_vergi_dairesi VARCHAR(80) NULL,
        alici_adres VARCHAR(255) NULL,
        alici_ilce VARCHAR(60) NULL,
        alici_il VARCHAR(60) NULL,
        alici_eposta VARCHAR(120) NULL,
        alici_telefon VARCHAR(20) NULL,
        gonderim_sekli VARCHAR(10) NOT NULL DEFAULT 'ELEKTRONIK',
        ara_toplam DECIMAL(14,2) NOT NULL DEFAULT 0,
        iskonto_toplam DECIMAL(14,2) NOT NULL DEFAULT 0,
        kdv_toplam DECIMAL(14,2) NOT NULL DEFAULT 0,
        genel_toplam DECIMAL(14,2) NOT NULL DEFAULT 0,
        notlar VARCHAR(500) NULL,
        entegrator VARCHAR(20) NULL,
        entegrator_ref VARCHAR(80) NULL,
        entegrator_hata VARCHAR(255) NULL,
        xml MEDIUMTEXT NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_fatura_no (fatura_no),
        UNIQUE KEY uq_fatura_uuid (uuid),
        KEY idx_fatura_siparis (order_id),
        KEY idx_fatura_durum (durum)
    ) " . t_opts());

    db()->exec("CREATE TABLE IF NOT EXISTS fatura_satirlari (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        fatura_id INT UNSIGNED NOT NULL,
        sira SMALLINT UNSIGNED NOT NULL,
        ad VARCHAR(200) NOT NULL,
        miktar DECIMAL(12,3) NOT NULL DEFAULT 1,
        birim VARCHAR(6) NOT NULL DEFAULT 'C62',
        birim_fiyat DECIMAL(14,4) NOT NULL DEFAULT 0,
        iskonto DECIMAL(14,2) NOT NULL DEFAULT 0,
        kdv_orani DECIMAL(5,2) NOT NULL DEFAULT 10,
        tutar DECIMAL(14,2) NOT NULL DEFAULT 0,
        kdv_tutar DECIMAL(14,2) NOT NULL DEFAULT 0,
        KEY idx_fatura_satir (fatura_id, sira)
    ) " . t_opts());

    // SGK: aktarılan reçetenin e-reçete numarası ve tarihi (mutabakat / bekleyen reçete kontrolü)
    add_column('sgk_incoming', 'erecete', 'VARCHAR(20) NULL');
    add_column('sgk_incoming', 'recete_tarihi', 'DATE NULL');
    add_index('sgk_incoming', 'idx_sgk_erecete', 'erecete');
    add_column('orders', 'sgk_erecete', 'VARCHAR(20) NULL');
    add_index('orders', 'idx_orders_sgk_erecete', 'sgk_erecete');

    // Eski aktarımlar için e-reçete numarasını ayrıştırılmış veriden doldur (yalnızca boş olanlar).
    foreach (rows("SELECT id, parsed FROM sgk_incoming WHERE erecete IS NULL AND parsed IS NOT NULL") as $r) {
        $p = json_decode((string) $r['parsed'], true);
        if (!is_array($p)) {
            continue;
        }
        $no = strtoupper(trim((string) ($p['erecete'] ?? '')));
        $tr = (string) ($p['recete_tarihi'] ?? '');
        $tarih = preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $tr, $m) ? "$m[3]-$m[2]-$m[1]" : null;
        q('UPDATE sgk_incoming SET erecete = ?, recete_tarihi = ? WHERE id = ?', [$no !== '' ? $no : null, $tarih, (int) $r['id']]);
    }
}


/* ------------------------------------------------------------------ */
/*  v23 (4.13.0) — ÜTS bildirimleri: tekil ürün envanteri + bildirim     */
/*  kuyruğu/günlüğü, tedarikçinin ÜTS kurum numarası.                   */
/*  Yalnızca YENİ tablo/sütun ekler; mevcut veriye dokunmaz.            */
/* ------------------------------------------------------------------ */

function migrate_v23_uts(): void
{
    // Mağazadaki her ÜTS tekil ürünü (seri takipli: 1 satır = 1 ürün; lot takipli: aynı lot birden çok satırda
    // olabilir — her sevkiyat ve her sipariş parçası ayrı satır, adetli). Karekod alanları büyük/küçük harf ve
    // aksan duyarlı (utf8mb4_bin): GS1 seri numaralarında "ab1" ile "AB1" farklı ürünlerdir.
    db()->exec("CREATE TABLE IF NOT EXISTS uts_urunler (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        anahtar VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        uno VARCHAR(23) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        lno VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
        sno VARCHAR(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
        adet INT NOT NULL DEFAULT 1,
        kaynak VARCHAR(10) NOT NULL DEFAULT 'uts',
        skt DATE NULL,
        urt DATE NULL,
        kategori VARCHAR(10) NOT NULL DEFAULT 'diger',
        marka_model VARCHAR(200) NULL,
        gonderen VARCHAR(200) NULL,
        gonderen_kurum VARCHAR(20) NULL,
        belge_no VARCHAR(40) NULL,
        vbi CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
        durum VARCHAR(14) NOT NULL DEFAULT 'stokta',
        frame_item_id INT UNSIGNED NULL,
        order_id INT UNSIGNED NULL,
        satis_turu VARCHAR(8) NULL,
        alma_at DATETIME NULL,
        cikis_at DATETIME NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_uts_anahtar (anahtar),
        KEY idx_uts_urun_durum (durum, skt),
        KEY idx_uts_urun_uno (uno),
        KEY idx_uts_urun_siparis (order_id),
        KEY idx_uts_urun_vbi (vbi)
    ) " . t_opts());

    // Her ÜTS isteği: kuyruk + kalıcı günlük (istek gövdesi, yanıt, ÜTS bildirim ID).
    db()->exec("CREATE TABLE IF NOT EXISTS uts_bildirimler (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tur VARCHAR(20) NOT NULL,
        urun_id INT UNSIGNED NULL,
        order_id INT UNSIGNED NULL,
        adet INT NOT NULL DEFAULT 1,
        govde TEXT NOT NULL,
        durum VARCHAR(14) NOT NULL DEFAULT 'bekliyor',
        ortam VARCHAR(8) NOT NULL DEFAULT 'deneme',
        uts_id VARCHAR(40) NULL,
        ilgili_id INT UNSIGNED NULL,
        deneme TINYINT UNSIGNED NOT NULL DEFAULT 0,
        son_hata VARCHAR(500) NULL,
        yanit TEXT NULL,
        tekil VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
        planlanan DATETIME NOT NULL,
        gonderilme DATETIME NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_uts_bildirim_tekil (tekil),
        KEY idx_uts_bildirim_durum (durum, planlanan),
        KEY idx_uts_bildirim_urun (urun_id),
        KEY idx_uts_bildirim_siparis (order_id)
    ) " . t_opts());

    add_column('suppliers', 'uts_kurum_no', 'VARCHAR(20) NULL');

    foreach (['uts_ortam' => 'deneme', 'uts_gonderim' => 'otomatik'] as $k => $v) {
        q('INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)', [$k, $v]);
    }
    setting('__reload__');
}


/* ------------------------------------------------------------------ */
/*  v24 (4.14.0) — Alış faturası (e-Fatura XML), kalemler, ürün kodu     */
/*  eşleme hafızası, tedarikçiye verilen senetler.                       */
/*  Yalnızca YENİ tablo/sütun ekler; mevcut veriye dokunmaz.            */
/* ------------------------------------------------------------------ */

function migrate_v24_alis_senet(): void
{
    add_column('supplier_invoices', 'due_date', 'DATE NULL');
    add_column('supplier_invoices', 'ettn', 'CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL');
    add_column('supplier_invoices', 'kaynak', "VARCHAR(8) NOT NULL DEFAULT 'elle'");
    add_column('supplier_invoices', 'ara_toplam', 'DECIMAL(14,2) NULL');
    add_column('supplier_invoices', 'kdv_toplam', 'DECIMAL(14,2) NULL');
    add_column('supplier_invoices', 'xml', 'MEDIUMTEXT NULL');
    add_index('supplier_invoices', 'idx_supplier_invoices_ettn', 'ettn');
    add_index('supplier_invoices', 'idx_supplier_invoices_due', 'due_date');

    // e-Fatura kalemleri (yalnızca XML'den gelen faturalarda)
    db()->exec("CREATE TABLE IF NOT EXISTS supplier_invoice_lines (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT UNSIGNED NOT NULL,
        sira SMALLINT UNSIGNED NOT NULL,
        ad VARCHAR(255) NOT NULL,
        kod VARCHAR(80) NULL,
        gtin VARCHAR(20) NULL,
        miktar DECIMAL(12,3) NOT NULL DEFAULT 1,
        birim VARCHAR(8) NULL,
        birim_fiyat DECIMAL(14,4) NOT NULL DEFAULT 0,
        kdv_orani DECIMAL(5,2) NOT NULL DEFAULT 0,
        tutar DECIMAL(14,2) NOT NULL DEFAULT 0,
        kdv_tutar DECIMAL(14,2) NOT NULL DEFAULT 0,
        frame_item_id INT UNSIGNED NULL,
        stok_adet INT NOT NULL DEFAULT 0,
        KEY idx_sil_fatura (invoice_id, sira),
        KEY idx_sil_cerceve (frame_item_id)
    ) " . t_opts());

    // Tedarikçinin ürün kodu → çerçeve kartı (bir kez eşlenince sonraki faturalarda kendiliğinden)
    db()->exec("CREATE TABLE IF NOT EXISTS urun_eslesmeleri (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT UNSIGNED NOT NULL,
        kod VARCHAR(80) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
        frame_item_id INT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_urun_eslesme (supplier_id, kod)
    ) " . t_opts());

    // Tedarikçiye verilen senetler (bono). Senet verilince cari "senet" ödemesiyle kapanır (payment_id),
    // borç senete geçer; vadede ödenince durum 'odendi' olur.
    db()->exec("CREATE TABLE IF NOT EXISTS tedarikci_senetleri (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        supplier_id INT UNSIGNED NOT NULL,
        invoice_id INT UNSIGNED NULL,
        payment_id INT UNSIGNED NULL,
        senet_no VARCHAR(40) NULL,
        tutar DECIMAL(14,2) NOT NULL,
        duzenleme DATE NOT NULL,
        vade DATE NOT NULL,
        duzenleme_yeri VARCHAR(80) NULL,
        odeme_yeri VARCHAR(80) NULL,
        durum VARCHAR(10) NOT NULL DEFAULT 'bekliyor',
        odeme_tarihi DATE NULL,
        odeme_yontemi VARCHAR(12) NULL,
        notlar VARCHAR(255) NULL,
        created_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY idx_senet_vade (durum, vade),
        KEY idx_senet_tedarikci (supplier_id),
        KEY idx_senet_odeme (payment_id)
    ) " . t_opts());
}
