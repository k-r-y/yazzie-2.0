<?php
require_once __DIR__ . '/../config/config.php';

try {
    $pdo->exec("ALTER TABLE bookings DROP INDEX idx_unique_event_date");
    echo "Index dropped successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
