-- Poyraz Optik Atölye yedeği
-- Veritabanı: deneme
-- Tarih: 01.10.2026 10:00:00
-- Sürüm: 3.43.0 · şema 21

-- TEST VERİSİ: tablo yapıları eski Poyraz sürümünden, kayıtların tamamı uydurmadır.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';
DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `user_name` varchar(120) NOT NULL DEFAULT '',
  `action` varchar(40) NOT NULL,
  `entity` varchar(30) NOT NULL DEFAULT '',
  `entity_id` int(10) unsigned DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_entity` (`entity`,`entity_id`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `cash_counts`;
CREATE TABLE `cash_counts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `count_date` date NOT NULL,
  `counted_amount` decimal(12,2) NOT NULL,
  `expected_amount` decimal(12,2) NOT NULL,
  `difference` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cash_counts_date` (`count_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `phone` varchar(20) NOT NULL DEFAULT '',
  `birth_year` smallint(5) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_customers_phone` (`phone`),
  KEY `idx_customers_name` (`last_name`,`first_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `expenses`;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `expense_date` date NOT NULL,
  `description` varchar(160) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(20) NOT NULL DEFAULT 'nakit',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_expenses_date` (`expense_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `frame_items`;
CREATE TABLE `frame_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `brand` varchar(80) NOT NULL,
  `model` varchar(80) DEFAULT NULL,
  `color` varchar(60) DEFAULT NULL,
  `size` varchar(40) DEFAULT NULL,
  `barcode` varchar(64) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `min_qty` int(11) NOT NULL DEFAULT 1,
  `cost` decimal(10,2) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `shelf` varchar(40) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_frame_items_barcode` (`barcode`),
  KEY `idx_frame_items_brand` (`brand`,`model`),
  KEY `idx_frame_items_active` (`is_active`,`qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `frame_moves`;
CREATE TABLE `frame_moves` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `frame_item_id` int(10) unsigned NOT NULL,
  `delta` int(11) NOT NULL,
  `reason` varchar(16) NOT NULL DEFAULT 'giris',
  `order_id` int(10) unsigned DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_frame_moves_item` (`frame_item_id`,`created_at`),
  KEY `idx_frame_moves_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `frame_products`;
CREATE TABLE `frame_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `brand` varchar(80) NOT NULL,
  `model` varchar(80) DEFAULT NULL,
  `total_qty` int(10) unsigned NOT NULL DEFAULT 0,
  `total_spent` decimal(12,2) NOT NULL DEFAULT 0.00,
  `avg_cost` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_frame_supplier` (`supplier_id`),
  CONSTRAINT `fk_frame_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `lens_products`;
CREATE TABLE `lens_products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `brand` varchar(60) NOT NULL,
  `name` varchar(120) NOT NULL,
  `design` varchar(20) NOT NULL DEFAULT 'tek_odak',
  `tier` varchar(12) NOT NULL DEFAULT 'dengeli',
  `lens_index` varchar(5) DEFAULT NULL,
  `coating` varchar(40) DEFAULT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_products_design` (`design`,`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `lens_types`;
CREATE TABLE `lens_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(80) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `username` varchar(60) NOT NULL DEFAULT '',
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_ip` (`ip`,`attempted_at`),
  KEY `idx_login_user` (`username`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `manual_debts`;
CREATE TABLE `manual_debts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(160) NOT NULL,
  `phone` varchar(24) NOT NULL,
  `original_amount` decimal(10,2) NOT NULL,
  `remaining_amount` decimal(10,2) NOT NULL,
  `debt_date` date NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_manual_debt_remaining` (`remaining_amount`),
  KEY `idx_manual_debt_date` (`debt_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `message_templates`;
CREATE TABLE `message_templates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `body` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `message_templates_eski_yedek`;
CREATE TABLE `message_templates_eski_yedek` (
  `template_key` varchar(40) NOT NULL,
  `template_text` text NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`template_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `near_prescription_details`;
CREATE TABLE `near_prescription_details` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` int(10) unsigned NOT NULL,
  `usage_type` varchar(40) NOT NULL DEFAULT 'ayri_cerceve',
  `lens_type` varchar(80) DEFAULT NULL,
  `right_sph` varchar(16) DEFAULT NULL,
  `right_cyl` varchar(16) DEFAULT NULL,
  `right_axis` varchar(16) DEFAULT NULL,
  `left_sph` varchar(16) DEFAULT NULL,
  `left_cyl` varchar(16) DEFAULT NULL,
  `left_axis` varchar(16) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_id` (`prescription_id`),
  CONSTRAINT `fk_near_rx` FOREIGN KEY (`prescription_id`) REFERENCES `prescription_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `order_maker_notes`;
CREATE TABLE `order_maker_notes` (
  `order_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `note` varchar(240) NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(80) NOT NULL,
  `last_name` varchar(80) NOT NULL,
  `phone` varchar(24) NOT NULL,
  `sales_person` varchar(80) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `lens_type` varchar(80) DEFAULT NULL,
  `stock_status` varchar(20) NOT NULL DEFAULT 'stokta_var',
  `status` varchar(32) NOT NULL DEFAULT 'siparis_verildi',
  `order_stage` varchar(32) DEFAULT NULL,
  `sms_sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `customer_id` int(10) unsigned NOT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `frame_info` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `promised_date` date DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `workshop_stage` varchar(16) DEFAULT NULL,
  `assigned_to` int(10) unsigned DEFAULT NULL,
  `qc_by` int(10) unsigned DEFAULT NULL,
  `qc_at` datetime DEFAULT NULL,
  `own_frame_pending` tinyint(1) NOT NULL DEFAULT 0,
  `frame_product_id` int(10) unsigned DEFAULT NULL,
  `transaction_type` varchar(20) NOT NULL DEFAULT 'gozluk',
  `delivered_by` int(10) unsigned DEFAULT NULL,
  `balance_promise_date` date DEFAULT NULL,
  `service_type` varchar(30) DEFAULT NULL,
  `is_free` tinyint(1) NOT NULL DEFAULT 0,
  `public_token` varchar(24) DEFAULT NULL,
  `frame_item_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_orders_public_token` (`public_token`),
  KEY `idx_status` (`status`),
  KEY `idx_orders_customer` (`customer_id`),
  KEY `idx_orders_stage` (`order_stage`,`created_at`),
  KEY `idx_orders_created` (`created_at`),
  KEY `idx_orders_workshop` (`order_stage`,`workshop_stage`),
  KEY `fk_orders_frame` (`frame_product_id`),
  KEY `idx_orders_frame_item` (`frame_item_id`),
  CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  CONSTRAINT `fk_orders_frame` FOREIGN KEY (`frame_product_id`) REFERENCES `frame_products` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `note` varchar(160) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `method` varchar(20) NOT NULL DEFAULT 'nakit',
  `created_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_order` (`order_id`),
  KEY `idx_payments_created` (`created_at`),
  CONSTRAINT `fk_payments_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `prescription_lens_items`;
CREATE TABLE `prescription_lens_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `prescription_id` int(10) unsigned NOT NULL,
  `lens_no` tinyint(3) unsigned NOT NULL,
  `lens_label` varchar(120) NOT NULL,
  `lens_value` varchar(255) DEFAULT NULL,
  `stock_status` varchar(20) NOT NULL DEFAULT 'stokta_var',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `item_group` varchar(10) NOT NULL DEFAULT 'uzak',
  `eye` char(1) NOT NULL DEFAULT 'R',
  `lens_type` varchar(80) NOT NULL DEFAULT '',
  `sph` varchar(16) NOT NULL DEFAULT '',
  `cyl` varchar(16) NOT NULL DEFAULT '',
  `axis` varchar(16) NOT NULL DEFAULT '',
  `add_power` varchar(16) NOT NULL DEFAULT '',
  `ordered_at` datetime DEFAULT NULL,
  `arrived_at` datetime DEFAULT NULL,
  `supplier_id` int(10) unsigned DEFAULT NULL,
  `delivery_id` int(10) unsigned DEFAULT NULL,
  `unit_cost` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_rx_lens` (`prescription_id`,`lens_no`),
  KEY `idx_lens_stock` (`stock_status`),
  KEY `fk_lens_item_supplier` (`supplier_id`),
  KEY `fk_lens_item_delivery` (`delivery_id`),
  CONSTRAINT `fk_lens_item_delivery` FOREIGN KEY (`delivery_id`) REFERENCES `supplier_deliveries` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lens_item_rx` FOREIGN KEY (`prescription_id`) REFERENCES `prescription_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lens_item_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `prescription_records`;
CREATE TABLE `prescription_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `prescription_date` date NOT NULL,
  `lens_type` varchar(80) DEFAULT NULL,
  `right_sph` varchar(16) DEFAULT NULL,
  `right_cyl` varchar(16) DEFAULT NULL,
  `right_axis` varchar(16) DEFAULT NULL,
  `right_add` varchar(16) DEFAULT NULL,
  `left_sph` varchar(16) DEFAULT NULL,
  `left_cyl` varchar(16) DEFAULT NULL,
  `left_axis` varchar(16) DEFAULT NULL,
  `left_add` varchar(16) DEFAULT NULL,
  `pd` varchar(16) DEFAULT NULL,
  `prescription_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `customer_id` int(10) unsigned DEFAULT NULL,
  `lens_design` varchar(20) NOT NULL DEFAULT 'tek_odak_uzak',
  `lens_eyes` varchar(6) NOT NULL DEFAULT 'both',
  `right_pd` varchar(8) DEFAULT NULL,
  `left_pd` varchar(8) DEFAULT NULL,
  `right_height` varchar(8) DEFAULT NULL,
  `left_height` varchar(8) DEFAULT NULL,
  `doctor` varchar(120) DEFAULT NULL,
  `advisor_summary` text DEFAULT NULL,
  `advisor_product_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `updated_by` int(10) unsigned DEFAULT NULL,
  `sgk_rapor_no` varchar(40) DEFAULT NULL,
  `sgk_rapor_tarihi` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rx_order` (`order_id`),
  KEY `idx_rx_date` (`prescription_date`),
  KEY `idx_rx_customer` (`customer_id`,`prescription_date`),
  CONSTRAINT `fk_rx_record_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `prescriptions`;
CREATE TABLE `prescriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `right_sph` varchar(16) DEFAULT NULL,
  `right_cyl` varchar(16) DEFAULT NULL,
  `right_axis` varchar(16) DEFAULT NULL,
  `right_add` varchar(16) DEFAULT NULL,
  `left_sph` varchar(16) DEFAULT NULL,
  `left_cyl` varchar(16) DEFAULT NULL,
  `left_axis` varchar(16) DEFAULT NULL,
  `left_add` varchar(16) DEFAULT NULL,
  `pd` varchar(16) DEFAULT NULL,
  `prescription_note` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  CONSTRAINT `fk_prescription_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `push_subscriptions`;
CREATE TABLE `push_subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `endpoint` varchar(500) NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `p256dh` varchar(140) NOT NULL,
  `auth_secret` varchar(60) NOT NULL,
  `device` varchar(120) DEFAULT NULL,
  `fail_count` int(11) NOT NULL DEFAULT 0,
  `last_ok_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint_hash` (`endpoint_hash`),
  KEY `idx_push_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `quotes`;
CREATE TABLE `quotes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned DEFAULT NULL,
  `customer_name` varchar(160) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `opt1_name` varchar(60) DEFAULT NULL,
  `opt1_desc` varchar(500) DEFAULT NULL,
  `opt1_price` decimal(12,2) DEFAULT NULL,
  `opt2_name` varchar(60) DEFAULT NULL,
  `opt2_desc` varchar(500) DEFAULT NULL,
  `opt2_price` decimal(12,2) DEFAULT NULL,
  `opt3_name` varchar(60) DEFAULT NULL,
  `opt3_desc` varchar(500) DEFAULT NULL,
  `opt3_price` decimal(12,2) DEFAULT NULL,
  `converted_order_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_quote_customer` (`customer_id`),
  KEY `fk_quote_order` (`converted_order_id`),
  CONSTRAINT `fk_quote_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_quote_order` FOREIGN KEY (`converted_order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `reminders`;
CREATE TABLE `reminders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` int(10) unsigned NOT NULL,
  `order_id` int(10) unsigned DEFAULT NULL,
  `kind` varchar(16) NOT NULL DEFAULT 'manuel',
  `due_date` date DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL,
  `status` varchar(12) NOT NULL DEFAULT 'bekliyor',
  `channel` varchar(12) DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `done_by` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_rem_customer` (`customer_id`,`kind`,`status`),
  KEY `idx_rem_due` (`status`,`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `revenues`;
CREATE TABLE `revenues` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `revenue_date` date NOT NULL,
  `description` varchar(160) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(20) NOT NULL DEFAULT 'nakit',
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_revenues_date` (`revenue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `sgk_incoming`;
CREATE TABLE `sgk_incoming` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `kaynak` varchar(20) NOT NULL DEFAULT 'kopru',
  `baslik` varchar(160) DEFAULT NULL,
  `raw_text` mediumtext NOT NULL,
  `parsed` text DEFAULT NULL,
  `used_order_id` int(10) unsigned DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sgk_user` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `sms_logs`;
CREATE TABLE `sms_logs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(10) unsigned NOT NULL,
  `recipient` varchar(24) NOT NULL,
  `message` text NOT NULL,
  `provider` varchar(32) NOT NULL,
  `provider_job_id` varchar(80) DEFAULT NULL,
  `success` tinyint(1) NOT NULL DEFAULT 0,
  `response_summary` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sms_logs_order` (`order_id`),
  CONSTRAINT `fk_sms_logs_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `staff_profiles`;
CREATE TABLE `staff_profiles` (
  `user_id` int(10) unsigned NOT NULL,
  `show_public` tinyint(1) NOT NULL DEFAULT 0,
  `title` varchar(60) NOT NULL DEFAULT '',
  `bio` varchar(240) NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `supplier_deliveries`;
CREATE TABLE `supplier_deliveries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) unsigned NOT NULL,
  `delivered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `item_count` int(10) unsigned NOT NULL DEFAULT 0,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier_deliveries` (`supplier_id`,`invoice_id`),
  KEY `fk_delivery_invoice` (`invoice_id`),
  CONSTRAINT `fk_delivery_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_delivery_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `supplier_invoices`;
CREATE TABLE `supplier_invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) unsigned NOT NULL,
  `invoice_no` varchar(60) NOT NULL,
  `invoice_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier_invoices` (`supplier_id`,`invoice_date`),
  CONSTRAINT `fk_supplier_invoice` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `supplier_payments`;
CREATE TABLE `supplier_payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` int(10) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `method` varchar(20) NOT NULL DEFAULT 'havale',
  `note` varchar(255) DEFAULT NULL,
  `created_by` int(10) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_supplier_payments` (`supplier_id`,`created_at`),
  CONSTRAINT `fk_supplier_payment` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `contact_name` varchar(120) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `tax_no` varchar(30) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
DROP TABLE IF EXISTS `user_accounts`;
CREATE TABLE `user_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'personel',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` datetime DEFAULT NULL,
  `password_changed_at` datetime DEFAULT NULL,
  `bridge_token` varchar(64) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_user_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `app_settings` (`setting_key`,`setting_value`) VALUES
('schema_version','21'),
('shop_name','Deneme Optik; Şube \'Merkez\'');
INSERT INTO `user_accounts` (`id`,`full_name`,`username`,`password_hash`,`role`,`is_active`) VALUES
(1,'Deneme Yönetici','eski.yonetici','$2y$12$17mVLDt2KegV2Kony6lJBuGH7ld5x1cdqwtM.xf2Q5g1JqjieDtd.','super_yetkili',1),
(2,'Deneme Personel','eski.personel','$2y$12$17mVLDt2KegV2Kony6lJBuGH7ld5x1cdqwtM.xf2Q5g1JqjieDtd.','personel',1);
INSERT INTO `customers` (`id`,`first_name`,`last_name`,`phone`) VALUES
(1,'Ayşe','Örnekoğlu','05000000001'),
(2,'Ali -- yorum değil','Deneme; noktalı','05000000002'),
(3,'Zeynep /* yorum değil */','Çalışkan','05000000003');
INSERT INTO `orders` (`id`,`first_name`,`last_name`,`phone`,`customer_id`,`total_amount`,`created_at`) VALUES
(10,'Ayşe','Örnekoğlu','05000000001',1,1500.00,'2026-09-01 10:00:00'),
(11,'Ali','Deneme','05000000002',2,2750.50,'2026-09-15 12:30:00');
INSERT INTO `payments` (`id`,`order_id`,`amount`,`created_at`) VALUES
(1,10,500.00,'2026-09-01 10:05:00'),
(2,11,2750.50,'2026-09-15 12:35:00');

SET FOREIGN_KEY_CHECKS=1;
-- Yedek sonu
