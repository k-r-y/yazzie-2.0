<?php
require_once __DIR__ . '/config/config.php';
try {
    $pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS resched_count TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of times rescheduled' AFTER dietary_notes");
    echo "✅ Added resched_count\n";
} catch(Exception $e) {
    echo "resched_count: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("ALTER TABLE clients ADD UNIQUE KEY uq_client_email (email)");
    echo "✅ Added UNIQUE constraint on clients.email\n";
} catch(Exception $e) {
    echo "clients.email unique: " . $e->getMessage() . "\n";
}
try {
    $pdo->exec("DROP TABLE IF EXISTS dish_ingredients");
    echo "✅ Dropped dish_ingredients\n";
} catch(Exception $e) {
    echo "drop dish_ingredients: " . $e->getMessage() . "\n";
}
echo "\nDone. Delete this file.";
