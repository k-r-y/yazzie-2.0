-- ============================================================
-- Yazzies Catering Operations Management System
-- Database: yazzie
-- Timezone: Asia/Manila (UTC+8)
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+08:00";

-- ============================================================
-- DROP TABLES (safe reinstall)
-- ============================================================
DROP TABLE IF EXISTS `job_orders`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `archived_bookings`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `ingredients`;
DROP TABLE IF EXISTS `menus`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) NOT NULL,
  `password`   VARCHAR(255) NOT NULL,
  `role`       ENUM('admin','frontdesk','staff') NOT NULL DEFAULT 'staff',
  `phone`      VARCHAR(20) DEFAULT NULL,
  `is_active`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: clients
-- ============================================================
CREATE TABLE `clients` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) DEFAULT NULL,
  `phone`      VARCHAR(20) NOT NULL,
  `address`    TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: menus
-- ============================================================
CREATE TABLE `menus` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  `description`   TEXT DEFAULT NULL,
  `price_per_pax` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `is_active`     TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: ingredients (recipe per pax for each menu)
-- ============================================================
CREATE TABLE `ingredients` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `menu_id`          INT UNSIGNED NOT NULL,
  `item_name`        VARCHAR(100) NOT NULL,
  `quantity_per_pax` DECIMAL(10,4) NOT NULL,
  `unit`             VARCHAR(30) NOT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_menu_id` (`menu_id`),
  CONSTRAINT `fk_ingredients_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: bookings (central event lifecycle)
-- ============================================================
CREATE TABLE `bookings` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id`      INT UNSIGNED NOT NULL,
  `menu_id`        INT UNSIGNED NOT NULL,
  `event_date`     DATE NOT NULL,
  `event_time`     TIME DEFAULT NULL,
  `event_location` TEXT DEFAULT NULL,
  `pax_count`      INT UNSIGNED NOT NULL DEFAULT 1,
  `total_cost`     DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `amount_paid`    DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `payment_status` ENUM('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `booking_status` ENUM('inquiry','confirmed','completed','cancelled') NOT NULL DEFAULT 'inquiry',
  `notes`          TEXT DEFAULT NULL,
  `created_by`     INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_client`      (`client_id`),
  KEY `idx_menu`        (`menu_id`),
  KEY `idx_created_by`  (`created_by`),
  KEY `idx_event_date`  (`event_date`),
  KEY `idx_pay_status`  (`payment_status`),
  KEY `idx_book_status` (`booking_status`),
  CONSTRAINT `fk_bookings_client`
    FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_bookings_menu`
    FOREIGN KEY (`menu_id`)   REFERENCES `menus` (`id`),
  CONSTRAINT `fk_bookings_user`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: payments (Admin-only financial ledger)
-- ============================================================
CREATE TABLE `payments` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`     INT UNSIGNED NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `payment_method` ENUM('cash','bank_transfer','gcash','maya') NOT NULL DEFAULT 'cash',
  `reference_no`   VARCHAR(100) DEFAULT NULL,
  `payment_date`   DATE NOT NULL,
  `notes`          TEXT DEFAULT NULL,
  `recorded_by`    INT UNSIGNED NOT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_recorded` (`recorded_by`),
  CONSTRAINT `fk_payments_booking`
    FOREIGN KEY (`booking_id`)  REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_user`
    FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: job_orders (staff dispatching)
-- ============================================================
CREATE TABLE `job_orders` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id`    INT UNSIGNED NOT NULL,
  `staff_id`      INT UNSIGNED NOT NULL,
  `role_required` VARCHAR(50) NOT NULL DEFAULT 'waiter',
  `status`        ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `sent_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at`  TIMESTAMP NULL DEFAULT NULL,
  `notes`         TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_staff_id`   (`staff_id`),
  CONSTRAINT `fk_joborders_booking`
    FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_joborders_staff`
    FOREIGN KEY (`staff_id`)   REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: archived_bookings (completed event history)
-- ============================================================
CREATE TABLE `archived_bookings` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `original_id`    INT UNSIGNED NOT NULL COMMENT 'Original booking ID before archive',
  `client_name`    VARCHAR(100) NOT NULL,
  `client_phone`   VARCHAR(20) DEFAULT NULL,
  `menu_name`      VARCHAR(100) NOT NULL,
  `event_date`     DATE NOT NULL,
  `event_time`     TIME DEFAULT NULL,
  `event_location` TEXT DEFAULT NULL,
  `pax_count`      INT UNSIGNED NOT NULL,
  `total_cost`     DECIMAL(10,2) NOT NULL,
  `amount_paid`    DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('unpaid','partial','paid') NOT NULL,
  `notes`          TEXT DEFAULT NULL,
  `archived_at`    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `archived_by`    INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_event_date`  (`event_date`),
  KEY `idx_archived_by` (`archived_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TRIGGERS — Auto-update payment_status on payments table change
-- ============================================================

DROP TRIGGER IF EXISTS `after_payment_insert`;
DROP TRIGGER IF EXISTS `after_payment_delete`;
DROP TRIGGER IF EXISTS `after_payment_update`;

DELIMITER $$

CREATE TRIGGER `after_payment_insert`
AFTER INSERT ON `payments`
FOR EACH ROW
BEGIN
    DECLARE v_total_paid DECIMAL(10,2);
    DECLARE v_total_cost DECIMAL(10,2);

    SELECT COALESCE(SUM(amount), 0) INTO v_total_paid
    FROM payments WHERE booking_id = NEW.booking_id;

    SELECT total_cost INTO v_total_cost
    FROM bookings WHERE id = NEW.booking_id;

    UPDATE bookings SET
        amount_paid    = v_total_paid,
        payment_status = CASE
            WHEN v_total_paid >= v_total_cost THEN 'paid'
            WHEN v_total_paid > 0             THEN 'partial'
            ELSE 'unpaid'
        END,
        updated_at = NOW()
    WHERE id = NEW.booking_id;
END$$

CREATE TRIGGER `after_payment_delete`
AFTER DELETE ON `payments`
FOR EACH ROW
BEGIN
    DECLARE v_total_paid DECIMAL(10,2);
    DECLARE v_total_cost DECIMAL(10,2);

    SELECT COALESCE(SUM(amount), 0) INTO v_total_paid
    FROM payments WHERE booking_id = OLD.booking_id;

    SELECT total_cost INTO v_total_cost
    FROM bookings WHERE id = OLD.booking_id;

    UPDATE bookings SET
        amount_paid    = v_total_paid,
        payment_status = CASE
            WHEN v_total_paid >= v_total_cost THEN 'paid'
            WHEN v_total_paid > 0             THEN 'partial'
            ELSE 'unpaid'
        END,
        updated_at = NOW()
    WHERE id = OLD.booking_id;
END$$

CREATE TRIGGER `after_payment_update`
AFTER UPDATE ON `payments`
FOR EACH ROW
BEGIN
    DECLARE v_total_paid DECIMAL(10,2);
    DECLARE v_total_cost DECIMAL(10,2);

    SELECT COALESCE(SUM(amount), 0) INTO v_total_paid
    FROM payments WHERE booking_id = NEW.booking_id;

    SELECT total_cost INTO v_total_cost
    FROM bookings WHERE id = NEW.booking_id;

    UPDATE bookings SET
        amount_paid    = v_total_paid,
        payment_status = CASE
            WHEN v_total_paid >= v_total_cost THEN 'paid'
            WHEN v_total_paid > 0             THEN 'partial'
            ELSE 'unpaid'
        END,
        updated_at = NOW()
    WHERE id = NEW.booking_id;
END$$

DELIMITER ;
