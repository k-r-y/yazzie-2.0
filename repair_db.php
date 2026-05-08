<?php
require_once __DIR__ . '/config/config.php';
// $pdo is already initialized in config/config.php

try {
    echo "<h1>Starting Aggressive Database Repair...</h1>";
    
    // Disable foreign key checks temporarily to allow structural changes
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<p style='color:blue;'>ℹ Foreign key checks disabled</p>";

    // 1. Drop existing index that might be restricting the column
    try {
        $pdo->exec("ALTER TABLE `bookings` DROP INDEX `idx_book_status` ");
        echo "<p style='color:green;'>✓ Dropped old index idx_book_status</p>";
    } catch(Exception $e) {
        echo "<p style='color:orange;'>! Note: Index drop skipped (might not exist)</p>";
    }

    // 2. Force the booking_status to be a LARGE VARCHAR
    $pdo->exec("ALTER TABLE `bookings` MODIFY COLUMN `booking_status` VARCHAR(255) NOT NULL DEFAULT 'pending'");
    echo "<p style='color:green;'>✓ SUCCESS: booking_status repaired to VARCHAR(255).</p>";
    
    // 3. Repair payment_status too
    $pdo->exec("ALTER TABLE `bookings` MODIFY COLUMN `payment_status` VARCHAR(255) NOT NULL DEFAULT 'unpaid'");
    echo "<p style='color:green;'>✓ SUCCESS: payment_status repaired to VARCHAR(255).</p>";

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p style='color:blue;'>ℹ Foreign key checks re-enabled</p>";

    echo "<h3>REPAIR COMPLETE. Please refresh your booking page and try again!</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>REPAIR FAILED: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
