-- ================================================================
-- Yazzies OMS — Migration v3: Package Tiers + Dish Selection System
-- Run ONCE after migration_v2.sql
-- ================================================================

-- ── 1. PACKAGES table (Set A → Set F price tiers) ────────────────
CREATE TABLE IF NOT EXISTS `packages` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `set_name`        VARCHAR(10)   NOT NULL COMMENT 'Set A, Set B, …',
  `pax_count`       INT UNSIGNED  NOT NULL COMMENT 'Base pax count for this tier',
  `price`           DECIMAL(10,2) NOT NULL COMMENT 'Flat price at base pax_count',
  `max_main_dishes` INT UNSIGNED  NOT NULL DEFAULT 5,
  `max_desserts`    INT UNSIGNED  NOT NULL DEFAULT 1,
  `includes_rice`   TINYINT(1)    NOT NULL DEFAULT 1,
  `is_active`       TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_set_name`  (`set_name`),
  UNIQUE KEY `uq_pax_count` (`pax_count`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed package tiers (+₱5,000 per 50-pax interval)
INSERT IGNORE INTO `packages` (set_name, pax_count, price, max_main_dishes, max_desserts) VALUES
('Set A', 50,  10000.00, 5, 1),
('Set B', 100, 15000.00, 5, 1),
('Set C', 150, 20000.00, 5, 1),
('Set D', 200, 25000.00, 5, 1),
('Set E', 250, 30000.00, 5, 1),
('Set F', 300, 35000.00, 5, 1);

-- ── 2. DISHES catalog ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `dishes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `category`   ENUM('main','dessert') NOT NULL DEFAULT 'main',
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed main dishes
INSERT IGNORE INTO `dishes` (name, category) VALUES
('Chicken Adobo',      'main'),
('Lechon Kawali',      'main'),
('Pork Mechado',       'main'),
('Beef Caldereta',     'main'),
('Pork Menudo',        'main'),
('Pancit Canton',      'main'),
('Embotido',           'main'),
('Pork Humba',         'main'),
('Grilled Liempo',     'main'),
('Kare-Kare',          'main'),
('Chicken Curry',      'main'),
('Sinigang na Baboy',  'main'),
('Dinuguan',           'main'),
('Callos',             'main'),
('Pork Bistek',        'main');

-- Seed desserts
INSERT IGNORE INTO `dishes` (name, category) VALUES
('Buko Pandan',   'dessert'),
('Leche Flan',    'dessert'),
('Halo-Halo',     'dessert'),
('Maja Blanca',   'dessert'),
('Fruit Salad',   'dessert'),
('Palitaw',       'dessert'),
('Biko',          'dessert');

-- ── 3. BOOKING_DISHES pivot ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `booking_dishes` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `dish_id`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bd_booking` (`booking_id`),
  KEY `idx_bd_dish`    (`dish_id`),
  CONSTRAINT `fk_bd_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bd_dish`    FOREIGN KEY (`dish_id`)    REFERENCES `dishes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. ALTER bookings to support package-based bookings ──────────
-- Drop old menus FK so we can make menu_id optional
ALTER TABLE `bookings` DROP FOREIGN KEY `fk_bookings_menu`;
ALTER TABLE `bookings` MODIFY COLUMN `menu_id` INT UNSIGNED DEFAULT NULL;

-- Add package_id reference
ALTER TABLE `bookings`
  ADD COLUMN `package_id` INT UNSIGNED DEFAULT NULL AFTER `menu_id`,
  ADD CONSTRAINT `fk_bookings_package` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`);

-- Re-add optional menu FK
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`);
