-- =====================================================================
-- SIMKOTOL - Sistem Manajemen Kopi Titik Nol
-- Skema Database MySQL 8.0.16+ (InnoDB, utf8mb4)
-- Catatan: tabel bawaan Laravel (sessions, cache, jobs, failed_jobs,
-- password_reset_tokens, notifications) dibuat lewat `php artisan migrate`
-- dan tidak diulang di sini.
-- Uang disimpan sebagai BIGINT dalam Rupiah (tanpa desimal).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS simkotol
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE simkotol;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 1. USERS & PENGATURAN
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name                  VARCHAR(120) NOT NULL,
  email                 VARCHAR(190) NOT NULL,
  email_verified_at     TIMESTAMP NULL,
  password              VARCHAR(255) NOT NULL,
  role                  ENUM('owner','kasir') NOT NULL DEFAULT 'kasir',
  phone                 VARCHAR(20) NULL,
  pin_code              VARCHAR(255) NULL COMMENT 'hash PIN 6 digit (untuk approval owner)',
  pin_failed_attempts   TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pin_locked_until      DATETIME NULL,
  is_active             TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at         DATETIME NULL,
  remember_token        VARCHAR(100) NULL,
  created_at            TIMESTAMP NULL,
  updated_at            TIMESTAMP NULL,
  deleted_at            TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_active (role, is_active)
) ENGINE=InnoDB;

CREATE TABLE settings (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group`     VARCHAR(40) NOT NULL DEFAULT 'general',
  `key`       VARCHAR(80) NOT NULL,
  `value`     TEXT NULL,
  `type`      ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
  updated_by  BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_settings_key (`key`),
  CONSTRAINT fk_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Penomoran harian (order_number, queue_number, purchase_number, shift_code)
CREATE TABLE number_sequences (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name         VARCHAR(40) NOT NULL COMMENT 'order | queue | purchase | shift',
  seq_date     DATE NOT NULL,
  last_number  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seq (name, seq_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. SHIFT & KAS
-- ---------------------------------------------------------------------
CREATE TABLE shifts (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shift_code            VARCHAR(30) NOT NULL,
  user_id               BIGINT UNSIGNED NOT NULL COMMENT 'kasir pemilik shift',
  cash_float_start      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  cash_expected_end     BIGINT NULL COMMENT 'dihitung sistem saat tutup',
  cash_actual_end       BIGINT UNSIGNED NULL COMMENT 'uang fisik',
  cash_variance         BIGINT NULL COMMENT 'fisik - expected',
  total_orders          INT UNSIGNED NOT NULL DEFAULT 0,
  total_cash_sales      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_noncash_sales   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  total_void_amount     BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status                ENUM('open','closed') NOT NULL DEFAULT 'open',
  opened_at             DATETIME NOT NULL,
  closed_at             DATETIME NULL,
  closed_by             BIGINT UNSIGNED NULL COMMENT 'beda dengan user_id jika ditutup paksa owner',
  close_notes           VARCHAR(255) NULL,
  -- Menjamin 1 kasir hanya punya 1 shift open (NULL tidak dianggap duplikat)
  open_lock             BIGINT UNSIGNED GENERATED ALWAYS AS (IF(status = 'open', user_id, NULL)) STORED,
  created_at            TIMESTAMP NULL,
  updated_at            TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shift_code (shift_code),
  UNIQUE KEY uq_shift_one_open_per_user (open_lock),
  KEY idx_shifts_user_status (user_id, status),
  KEY idx_shifts_opened (opened_at),
  CONSTRAINT fk_shifts_user   FOREIGN KEY (user_id)   REFERENCES users(id),
  CONSTRAINT fk_shifts_closer FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE shift_cash_movements (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  shift_id    BIGINT UNSIGNED NOT NULL,
  user_id     BIGINT UNSIGNED NOT NULL,
  type        ENUM('in','out','refund') NOT NULL COMMENT 'refund = pengembalian tunai akibat void',
  amount      BIGINT UNSIGNED NOT NULL,
  reason      VARCHAR(255) NOT NULL,
  order_id    BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_scm_shift (shift_id),
  CONSTRAINT fk_scm_shift FOREIGN KEY (shift_id) REFERENCES shifts(id),
  CONSTRAINT fk_scm_user  FOREIGN KEY (user_id)  REFERENCES users(id),
  CONSTRAINT chk_scm_amount CHECK (amount > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. KATALOG: KATEGORI, MENU, OPSI
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(80) NOT NULL,
  slug        VARCHAR(100) NOT NULL,
  icon        VARCHAR(50) NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  deleted_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_slug (slug),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

CREATE TABLE products (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_id  BIGINT UNSIGNED NOT NULL,
  sku          VARCHAR(40) NULL,
  name         VARCHAR(120) NOT NULL,
  description  TEXT NULL,
  price        BIGINT UNSIGNED NOT NULL COMMENT 'harga jual (Rp)',
  cost_price   BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'HPP terhitung dari resep',
  image        VARCHAR(255) NULL,
  is_active    TINYINT(1) NOT NULL DEFAULT 1,
  is_sold_out  TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'penanda habis manual',
  sort_order   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at   TIMESTAMP NULL,
  updated_at   TIMESTAMP NULL,
  deleted_at   TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_products_sku (sku),
  KEY idx_products_cat_active (category_id, is_active),
  KEY idx_products_name (name),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Fase 2 (disiapkan): varian/opsi seperti ukuran, level gula, es
CREATE TABLE product_option_groups (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id  BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL COMMENT 'mis. Ukuran, Level Gula',
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  min_select  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  max_select  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pog_product (product_id),
  CONSTRAINT fk_pog_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_options (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id    BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(60) NOT NULL,
  price_delta BIGINT NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_po_group (group_id),
  CONSTRAINT fk_po_group FOREIGN KEY (group_id) REFERENCES product_option_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. BAHAN BAKU, SUPPLIER, PEMBELIAN, RESEP
-- ---------------------------------------------------------------------
CREATE TABLE ingredients (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  sku            VARCHAR(40) NULL,
  name           VARCHAR(120) NOT NULL,
  unit           ENUM('gram','ml','pcs') NOT NULL,
  stock          DECIMAL(14,3) NOT NULL DEFAULT 0 COMMENT 'boleh negatif (BR-16)',
  min_stock      DECIMAL(14,3) NOT NULL DEFAULT 0,
  cost_per_unit  DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'moving average',
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  low_stock_notified_at DATETIME NULL COMMENT 'anti-spam notifikasi',
  created_at     TIMESTAMP NULL,
  updated_at     TIMESTAMP NULL,
  deleted_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ingredients_sku (sku),
  UNIQUE KEY uq_ingredients_name (name),
  KEY idx_ingredients_stock (stock, min_stock)
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120) NOT NULL,
  phone      VARCHAR(20) NULL,
  address    VARCHAR(255) NULL,
  notes      VARCHAR(255) NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE purchases (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_number  VARCHAR(30) NOT NULL,
  supplier_id      BIGINT UNSIGNED NULL,
  user_id          BIGINT UNSIGNED NOT NULL,
  purchased_at     DATE NOT NULL,
  total_cost       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  notes            VARCHAR(255) NULL,
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_purchase_number (purchase_number),
  KEY idx_purchases_date (purchased_at),
  CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  CONSTRAINT fk_purchases_user     FOREIGN KEY (user_id)     REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE purchase_items (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  purchase_id    BIGINT UNSIGNED NOT NULL,
  ingredient_id  BIGINT UNSIGNED NOT NULL,
  quantity       DECIMAL(14,3) NOT NULL,
  unit_cost      DECIMAL(14,4) NOT NULL COMMENT 'harga per satuan dasar (gram/ml/pcs)',
  subtotal       BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  KEY idx_pi_purchase (purchase_id),
  KEY idx_pi_ingredient (ingredient_id),
  CONSTRAINT fk_pi_purchase   FOREIGN KEY (purchase_id)   REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
  CONSTRAINT chk_pi_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

CREATE TABLE recipes (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id       BIGINT UNSIGNED NOT NULL,
  ingredient_id    BIGINT UNSIGNED NOT NULL,
  amount_required  DECIMAL(14,3) NOT NULL,
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_recipe_product_ingredient (product_id, ingredient_id),
  KEY idx_recipes_ingredient (ingredient_id),
  CONSTRAINT fk_recipes_product    FOREIGN KEY (product_id)    REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_recipes_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
  CONSTRAINT chk_recipe_amount CHECK (amount_required > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. MARKETING: VOUCHER & BANNER
-- ---------------------------------------------------------------------
CREATE TABLE vouchers (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code          VARCHAR(30) NOT NULL,
  name          VARCHAR(120) NOT NULL,
  type          ENUM('percent','fixed') NOT NULL,
  value         BIGINT UNSIGNED NOT NULL COMMENT 'persen (1-100) atau nominal Rp',
  max_discount  BIGINT UNSIGNED NULL COMMENT 'plafon diskon untuk tipe percent',
  min_purchase  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  quota         INT UNSIGNED NULL COMMENT 'NULL = tanpa batas',
  used_count    INT UNSIGNED NOT NULL DEFAULT 0,
  starts_at     DATETIME NOT NULL,
  ends_at       DATETIME NOT NULL,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_by    BIGINT UNSIGNED NULL,
  created_at    TIMESTAMP NULL,
  updated_at    TIMESTAMP NULL,
  deleted_at    TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vouchers_code (code),
  KEY idx_vouchers_period (is_active, starts_at, ends_at),
  CONSTRAINT fk_vouchers_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT chk_voucher_period CHECK (ends_at >= starts_at),
  CONSTRAINT chk_voucher_percent CHECK (type <> 'percent' OR value BETWEEN 1 AND 100)
) ENGINE=InnoDB;

CREATE TABLE banners (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(120) NOT NULL,
  image       VARCHAR(255) NOT NULL,
  link_url    VARCHAR(255) NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  starts_at   DATETIME NULL,
  ends_at     DATETIME NULL,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  created_by  BIGINT UNSIGNED NULL,
  created_at  TIMESTAMP NULL,
  updated_at  TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_banners_active (is_active, sort_order),
  CONSTRAINT fk_banners_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. PESANAN
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_number     VARCHAR(30) NOT NULL COMMENT 'SKT-YYMMDD-0001',
  queue_date       DATE NOT NULL,
  queue_number     SMALLINT UNSIGNED NOT NULL,
  shift_id         BIGINT UNSIGNED NOT NULL,
  cashier_id       BIGINT UNSIGNED NOT NULL,
  order_type       ENUM('dine_in','take_away') NOT NULL DEFAULT 'dine_in',
  customer_name    VARCHAR(80) NULL,
  customer_phone   VARCHAR(20) NULL,
  subtotal         BIGINT UNSIGNED NOT NULL DEFAULT 0,
  voucher_id       BIGINT UNSIGNED NULL,
  voucher_code     VARCHAR(30) NULL COMMENT 'snapshot kode',
  discount_amount  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tax_percent      DECIMAL(5,2) NOT NULL DEFAULT 0,
  tax_amount       BIGINT UNSIGNED NOT NULL DEFAULT 0,
  service_amount   BIGINT UNSIGNED NOT NULL DEFAULT 0,
  rounding_amount  BIGINT NOT NULL DEFAULT 0,
  grand_total      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  status           ENUM('draft','pending','paid','expired','cancelled','voided') NOT NULL DEFAULT 'pending',
  notes            VARCHAR(255) NULL,
  ordered_at       DATETIME NOT NULL,
  paid_at          DATETIME NULL,
  expired_at       DATETIME NULL,
  cancelled_at     DATETIME NULL,
  voided_at        DATETIME NULL,
  created_at       TIMESTAMP NULL,
  updated_at       TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_orders_number (order_number),
  UNIQUE KEY uq_orders_queue (queue_date, queue_number),
  KEY idx_orders_status_date (status, ordered_at),
  KEY idx_orders_shift_status (shift_id, status),
  KEY idx_orders_cashier (cashier_id, ordered_at),
  KEY idx_orders_paid_at (paid_at),
  CONSTRAINT fk_orders_shift   FOREIGN KEY (shift_id)   REFERENCES shifts(id),
  CONSTRAINT fk_orders_cashier FOREIGN KEY (cashier_id) REFERENCES users(id),
  CONSTRAINT fk_orders_voucher FOREIGN KEY (voucher_id) REFERENCES vouchers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id            BIGINT UNSIGNED NOT NULL,
  product_id          BIGINT UNSIGNED NULL,
  product_name        VARCHAR(120) NOT NULL COMMENT 'snapshot',
  unit_price          BIGINT UNSIGNED NOT NULL COMMENT 'snapshot harga',
  quantity            SMALLINT UNSIGNED NOT NULL,
  options_total       BIGINT NOT NULL DEFAULT 0 COMMENT 'total price_delta opsi per unit',
  subtotal            BIGINT UNSIGNED NOT NULL,
  cost_price_snapshot BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'HPP per unit saat transaksi',
  note                VARCHAR(100) NULL,
  created_at          TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_oi_order (order_id),
  KEY idx_oi_product (product_id),
  CONSTRAINT fk_oi_order   FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  CONSTRAINT chk_oi_qty CHECK (quantity > 0)
) ENGINE=InnoDB;

CREATE TABLE order_item_options (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_item_id     BIGINT UNSIGNED NOT NULL,
  product_option_id BIGINT UNSIGNED NULL,
  group_name        VARCHAR(60) NOT NULL,
  option_name       VARCHAR(60) NOT NULL,
  price_delta       BIGINT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_oio_item (order_item_id),
  CONSTRAINT fk_oio_item   FOREIGN KEY (order_item_id)     REFERENCES order_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_oio_option FOREIGN KEY (product_option_id) REFERENCES product_options(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. PEMBAYARAN & WEBHOOK
-- ---------------------------------------------------------------------
CREATE TABLE payments (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id           BIGINT UNSIGNED NOT NULL,
  method             ENUM('cash','qris','ewallet','virtual_account','bank_transfer') NOT NULL,
  provider           VARCHAR(30) NULL COMMENT 'midtrans | xendit | pakasir',
  external_id        VARCHAR(80) NOT NULL COMMENT 'ID unik dari SIMKOTOL yang dikirim ke gateway',
  provider_reference VARCHAR(120) NULL COMMENT 'ID transaksi di gateway',
  amount             BIGINT UNSIGNED NOT NULL,
  cash_received      BIGINT UNSIGNED NULL,
  change_amount      BIGINT UNSIGNED NULL,
  status             ENUM('pending','paid','expired','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  qr_string          TEXT NULL,
  qr_url             VARCHAR(255) NULL,
  va_number          VARCHAR(40) NULL,
  expires_at         DATETIME NULL,
  paid_at            DATETIME NULL,
  needs_review       TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'bayar terlambat / nominal tidak cocok',
  review_note        VARCHAR(255) NULL,
  reviewed_by        BIGINT UNSIGNED NULL,
  reviewed_at        DATETIME NULL,
  raw_response       JSON NULL,
  created_by         BIGINT UNSIGNED NOT NULL,
  created_at         TIMESTAMP NULL,
  updated_at         TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_external (external_id),
  KEY idx_payments_order (order_id),
  KEY idx_payments_status_exp (status, expires_at),
  KEY idx_payments_provider_ref (provider, provider_reference),
  KEY idx_payments_review (needs_review),
  CONSTRAINT fk_payments_order    FOREIGN KEY (order_id)    REFERENCES orders(id),
  CONSTRAINT fk_payments_creator  FOREIGN KEY (created_by)  REFERENCES users(id),
  CONSTRAINT fk_payments_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payment_webhook_logs (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider         VARCHAR(30) NOT NULL,
  event_id         VARCHAR(120) NULL COMMENT 'ID event unik dari gateway (idempotensi)',
  external_id      VARCHAR(80) NULL,
  payment_id       BIGINT UNSIGNED NULL,
  payload          JSON NOT NULL,
  signature_valid  TINYINT(1) NOT NULL DEFAULT 0,
  status           ENUM('received','processed','ignored','failed') NOT NULL DEFAULT 'received',
  error_message    VARCHAR(255) NULL,
  received_at      DATETIME NOT NULL,
  processed_at     DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_webhook_event (provider, event_id),
  KEY idx_webhook_external (external_id),
  KEY idx_webhook_status (status, received_at),
  CONSTRAINT fk_webhook_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. VOID
-- ---------------------------------------------------------------------
CREATE TABLE order_voids (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  order_id       BIGINT UNSIGNED NOT NULL,
  shift_id       BIGINT UNSIGNED NOT NULL COMMENT 'shift saat void dilakukan',
  requested_by   BIGINT UNSIGNED NOT NULL,
  approved_by    BIGINT UNSIGNED NOT NULL COMMENT 'owner pemilik PIN',
  reason         VARCHAR(255) NOT NULL,
  stock_action   ENUM('rollback','waste') NOT NULL,
  refund_amount  BIGINT UNSIGNED NOT NULL DEFAULT 0,
  refund_method  ENUM('cash','manual_transfer','none') NOT NULL DEFAULT 'none',
  refund_status  ENUM('not_required','pending','completed') NOT NULL DEFAULT 'not_required',
  refund_note    VARCHAR(255) NULL,
  voided_at      DATETIME NOT NULL,
  created_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_void_order (order_id),
  KEY idx_void_date (voided_at),
  CONSTRAINT fk_void_order     FOREIGN KEY (order_id)     REFERENCES orders(id),
  CONSTRAINT fk_void_shift     FOREIGN KEY (shift_id)     REFERENCES shifts(id),
  CONSTRAINT fk_void_requester FOREIGN KEY (requested_by) REFERENCES users(id),
  CONSTRAINT fk_void_approver  FOREIGN KEY (approved_by)  REFERENCES users(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. MUTASI STOK & AUDIT
-- ---------------------------------------------------------------------
CREATE TABLE stock_mutations (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ingredient_id  BIGINT UNSIGNED NOT NULL,
  type           ENUM('in','out','spill','expired','adjustment','sales','void_rollback') NOT NULL,
  quantity       DECIMAL(14,3) NOT NULL COMMENT 'bertanda: + menambah, - mengurangi',
  stock_before   DECIMAL(14,3) NOT NULL,
  stock_after    DECIMAL(14,3) NOT NULL,
  unit_cost      DECIMAL(14,4) NULL,
  order_id       BIGINT UNSIGNED NULL,
  order_item_id  BIGINT UNSIGNED NULL,
  purchase_id    BIGINT UNSIGNED NULL,
  user_id        BIGINT UNSIGNED NULL,
  notes          VARCHAR(255) NULL,
  created_at     TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_sm_ingredient_date (ingredient_id, created_at),
  KEY idx_sm_type_date (type, created_at),
  KEY idx_sm_order (order_id),
  KEY idx_sm_order_item (order_item_id),
  CONSTRAINT fk_sm_ingredient FOREIGN KEY (ingredient_id) REFERENCES ingredients(id),
  CONSTRAINT fk_sm_order      FOREIGN KEY (order_id)      REFERENCES orders(id) ON DELETE SET NULL,
  CONSTRAINT fk_sm_order_item FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE SET NULL,
  CONSTRAINT fk_sm_purchase   FOREIGN KEY (purchase_id)   REFERENCES purchases(id) ON DELETE SET NULL,
  CONSTRAINT fk_sm_user       FOREIGN KEY (user_id)       REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NULL,
  action          VARCHAR(60) NOT NULL COMMENT 'mis. product.price_changed, order.voided',
  auditable_type  VARCHAR(80) NULL,
  auditable_id    BIGINT UNSIGNED NULL,
  old_values      JSON NULL,
  new_values      JSON NULL,
  ip_address      VARCHAR(45) NULL,
  user_agent      VARCHAR(255) NULL,
  created_at      TIMESTAMP NULL,
  PRIMARY KEY (id),
  KEY idx_audit_user_date (user_id, created_at),
  KEY idx_audit_target (auditable_type, auditable_id),
  KEY idx_audit_action (action, created_at),
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Relasi tambahan yang butuh tabel tujuan sudah ada
ALTER TABLE shift_cash_movements
  ADD CONSTRAINT fk_scm_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 10. VIEW BANTU
-- ---------------------------------------------------------------------
-- Status stok bahan: ok | low | out
CREATE OR REPLACE VIEW v_ingredient_stock_status AS
SELECT
  i.id, i.name, i.unit, i.stock, i.min_stock,
  CASE
    WHEN i.stock <= 0 THEN 'out'
    WHEN i.stock <= i.min_stock THEN 'low'
    ELSE 'ok'
  END AS stock_status
FROM ingredients i
WHERE i.deleted_at IS NULL AND i.is_active = 1;

-- Ketersediaan menu (jumlah porsi maksimal dari stok bahan); NULL = tanpa resep
CREATE OR REPLACE VIEW v_product_availability AS
SELECT
  p.id AS product_id,
  p.name,
  p.is_sold_out,
  CASE WHEN COUNT(r.id) = 0 THEN NULL
       ELSE GREATEST(0, MIN(FLOOR(i.stock / r.amount_required)))
  END AS max_portions
FROM products p
LEFT JOIN recipes r ON r.product_id = p.id
LEFT JOIN ingredients i ON i.id = r.ingredient_id
WHERE p.deleted_at IS NULL AND p.is_active = 1
GROUP BY p.id, p.name, p.is_sold_out;

-- Penjualan harian (hanya PAID)
CREATE OR REPLACE VIEW v_daily_sales AS
SELECT
  DATE(o.paid_at) AS sales_date,
  COUNT(*)        AS total_orders,
  SUM(o.grand_total) AS total_sales,
  SUM(o.discount_amount) AS total_discount
FROM orders o
WHERE o.status = 'paid'
GROUP BY DATE(o.paid_at);

-- ---------------------------------------------------------------------
-- 11. DATA AWAL PENGATURAN
-- (Akun owner dibuat lewat Laravel seeder agar password ter-hash benar)
-- ---------------------------------------------------------------------
INSERT INTO settings (`group`,`key`,`value`,`type`,created_at,updated_at) VALUES
 ('store','store_name','Kopi Titik Nol','string',NOW(),NOW()),
 ('store','store_address','','string',NOW(),NOW()),
 ('store','store_phone','','string',NOW(),NOW()),
 ('receipt','receipt_footer','Terima kasih, sampai jumpa lagi!','string',NOW(),NOW()),
 ('receipt','paper_size','80','int',NOW(),NOW()),
 ('tax','tax_percent','0','string',NOW(),NOW()),
 ('tax','service_percent','0','string',NOW(),NOW()),
 ('tax','tax_inclusive','0','bool',NOW(),NOW()),
 ('tax','cash_rounding','100','int',NOW(),NOW()),
 ('payment','qris_expiry_minutes','15','int',NOW(),NOW()),
 ('payment','payment_provider','midtrans','string',NOW(),NOW()),
 ('stock','allow_negative_stock_on_paid','1','bool',NOW(),NOW());
