<?php
/**
 * Migration v15 — Dynamic System Settings
 * Creates the 'settings' table and seeds it with default business rules.
 */
require_once __DIR__ . '/../config/config.php';

try {
    // 1. Create or alter settings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `settings` (
            `key` varchar(60) NOT NULL,
            `value` text DEFAULT NULL,
            `type` enum('string', 'int', 'float', 'bool', 'json') NOT NULL DEFAULT 'string',
            `description` varchar(255) DEFAULT NULL,
            `category` varchar(30) DEFAULT 'general',
            `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
            PRIMARY KEY (`key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // Check for "category" and "key" as Primary Key
    $cols = $pdo->query("SHOW COLUMNS FROM settings")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category', $cols)) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN category varchar(30) DEFAULT 'general' AFTER description");
    }

    // 2. Seed default values
    $defaults = [
        ['min_pax', '50', 'int', 'Minimum guest count per booking', 'revenue'],
        ['max_pax', '300', 'int', 'Maximum guest count allowed in stepper', 'operations'],
        ['standard_dp_percent', '0.30', 'float', 'Downpayment required for standard bookings (30%)', 'finance'],
        ['rush_dp_percent', '1.00', 'float', 'Downpayment required for bookings < 48hrs away (100%)', 'finance'],
        ['operating_hours_start', '08:00', 'string', 'Earliest event start time', 'operations'],
        ['operating_hours_end', '21:59', 'string', 'Latest event end time', 'operations'],
        ['staff_ratio_premium', '10', 'int', 'Guest-to-staff ratio for Weddings/Corporate (1:10)', 'operations'],
        ['staff_ratio_standard', '20', 'int', 'Guest-to-staff ratio for other events (1:20)', 'operations'],
    ];

    $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`, `type`, `description`, `category`) 
                          VALUES (?, ?, ?, ?, ?) 
                          ON DUPLICATE KEY UPDATE value = value");
    foreach ($defaults as $d) {
        $stmt->execute($d);
    }

    echo "Migration v15 (System Settings) applied successfully.\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
