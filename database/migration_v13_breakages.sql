-- Migration: Breakage & Loss Module (v13)
-- Adds equipment catalog and post-event breakage tracking

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;

-- 1. Equipment Catalog
-- Tracks items that can be broken or lost (e.g., plates, glasses)
CREATE TABLE IF NOT EXISTS `equipment` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `unit` varchar(20) NOT NULL DEFAULT 'pcs',
    `replacement_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Booking Breakages (Loss Ledger)
-- Links specific equipment losses to a booking
CREATE TABLE IF NOT EXISTS `booking_breakages` (
    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id` int(10) UNSIGNED NOT NULL,
    `equipment_id` int(10) UNSIGNED NOT NULL,
    `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
    `unit_price` decimal(10,2) NOT NULL COMMENT 'Snapshotted replacement cost at the time of logging',
    `total_cost` decimal(10,2) NOT NULL COMMENT 'qty * unit_price',
    `notes` varchar(255) DEFAULT NULL,
    `logged_by` int(10) UNSIGNED NOT NULL,
    `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_bb_booking` (`booking_id`),
    KEY `idx_bb_equipment` (`equipment_id`),
    CONSTRAINT `fk_bb_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bb_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_bb_logger` FOREIGN KEY (`logged_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed initial equipment (Sample data)
INSERT INTO `equipment` (`name`, `unit`, `replacement_cost`) VALUES
('Dinner Plate (Ceramic)', 'pcs', 150.00),
('Spoon/Fork (Stainless)', 'pcs', 45.00),
('Highball Glass', 'pcs', 85.00),
('Water Goblet', 'pcs', 120.00),
('Melamine Plate', 'pcs', 75.00),
('Serving Spoon (Large)', 'pcs', 250.00),
('Chafing Dish Lid', 'pcs', 1200.00),
('Table Cloth (Large)', 'pcs', 650.00);

COMMIT;
