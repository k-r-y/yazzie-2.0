-- ============================================================
-- Migration V12 — Booking Dietary Notes + Cancellations Table
-- Run this in phpMyAdmin on the `yazzie` database AFTER V11
-- ============================================================

-- 1. Add dietary/allergy notes field to bookings
ALTER TABLE `bookings`
    ADD COLUMN `dietary_notes` TEXT DEFAULT NULL
        COMMENT 'Allergy notes and special dietary requests (e.g. less salt, no pork, no fish)';

-- 2. Full Cancellations & Refunds table
CREATE TABLE IF NOT EXISTS `cancellations` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id`          INT UNSIGNED NOT NULL,
    `requested_by`        INT UNSIGNED NOT NULL COMMENT 'User who triggered the cancellation',
    `reason`              TEXT DEFAULT NULL,
    `cancelled_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Financial resolution
    `total_paid`          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `forfeited_amount`    DECIMAL(10,2) NOT NULL DEFAULT 0.00
        COMMENT '50% of total_cost if cancelled after confirmation',
    `refundable_amount`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `refund_status`       ENUM('pending','processed','waived') NOT NULL DEFAULT 'pending',
    `refund_method`       ENUM('cash','gcash','maya','bank_transfer') DEFAULT NULL,
    `refund_reference`    VARCHAR(100) DEFAULT NULL,
    `refund_processed_at` TIMESTAMP NULL DEFAULT NULL,
    `refund_processed_by` INT UNSIGNED DEFAULT NULL,

    `notes`               TEXT DEFAULT NULL,
    `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_booking_cancellation` (`booking_id`),
    KEY `idx_refund_status` (`refund_status`),
    KEY `idx_cancelled_at`  (`cancelled_at`),
    CONSTRAINT `fk_cancel_booking` FOREIGN KEY (`booking_id`)
        REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cancel_by` FOREIGN KEY (`requested_by`)
        REFERENCES `users` (`id`),
    CONSTRAINT `fk_refund_by` FOREIGN KEY (`refund_processed_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Taste Testing Appointments table
CREATE TABLE IF NOT EXISTS `taste_testing` (
    `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`                INT UNSIGNED NOT NULL,
    `scheduled_date`           DATE NOT NULL,
    `scheduled_time`           TIME DEFAULT NULL,
    `location`                 VARCHAR(255) DEFAULT NULL,
    `status`                   ENUM('pending','confirmed','completed','cancelled','converted')
                                   NOT NULL DEFAULT 'pending',
    `notes`                    TEXT DEFAULT NULL,
    `created_by`               INT UNSIGNED NOT NULL,
    `converted_to_booking_id`  INT UNSIGNED DEFAULT NULL
        COMMENT 'Set when this taste test is converted to a booking',
    `converted_at`             TIMESTAMP NULL DEFAULT NULL,
    `converted_by`             INT UNSIGNED DEFAULT NULL,
    `created_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    KEY `idx_tt_client`  (`client_id`),
    KEY `idx_tt_date`    (`scheduled_date`),
    KEY `idx_tt_status`  (`status`),
    CONSTRAINT `fk_tt_client`    FOREIGN KEY (`client_id`)
        REFERENCES `clients` (`id`),
    CONSTRAINT `fk_tt_created`   FOREIGN KEY (`created_by`)
        REFERENCES `users` (`id`),
    CONSTRAINT `fk_tt_booking`   FOREIGN KEY (`converted_to_booking_id`)
        REFERENCES `bookings` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tt_converter` FOREIGN KEY (`converted_by`)
        REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Add unit_price and supplier to recipe_ingredients (Advanced Costing)
ALTER TABLE `recipe_ingredients`
    ADD COLUMN `unit_price` DECIMAL(10,2) DEFAULT NULL
        COMMENT 'Price per unit (e.g. per kg, per pack). NULL = not yet costed.',
    ADD COLUMN `supplier`   VARCHAR(100) DEFAULT NULL
        COMMENT 'Supplier name for this ingredient';
