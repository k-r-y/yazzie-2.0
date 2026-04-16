<?php
require_once __DIR__ . '/../config/config.php';

try {
    // Check if columns exist
    $stmt = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'actual_start_time'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN actual_start_time TIME DEFAULT NULL AFTER event_time");
        $pdo->exec("ALTER TABLE bookings ADD COLUMN actual_end_time TIME DEFAULT NULL AFTER actual_start_time");
        $pdo->exec("ALTER TABLE bookings ADD COLUMN event_report_notes TEXT DEFAULT NULL AFTER notes");
        $pdo->exec("ALTER TABLE bookings ADD COLUMN report_submitted_by INT(10) UNSIGNED DEFAULT NULL AFTER created_by");
        $pdo->exec("ALTER TABLE bookings ADD COLUMN report_submitted_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at");
        
        // Add transport_fee and event_type as well since I used them in previous steps
        $pdo->exec("ALTER TABLE bookings ADD COLUMN event_type VARCHAR(50) DEFAULT 'Wedding' AFTER package_id");
        $pdo->exec("ALTER TABLE bookings ADD COLUMN transport_fee DECIMAL(10,2) DEFAULT 0.00 AFTER extra_cost");
        
        echo "Migration v14 (Event Reports & Stepper Expansion) applied successfully.\n";
    } else {
        echo "Migration v14 already applied.\n";
    }
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
