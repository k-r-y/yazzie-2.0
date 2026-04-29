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
    $pdo->exec("CREATE TABLE IF NOT EXISTS `notifications` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `type` varchar(50) NOT NULL,
      `title` varchar(255) DEFAULT NULL,
      `body` text DEFAULT NULL,
      `booking_id` int(11) DEFAULT NULL,
      `link_url` varchar(255) DEFAULT NULL,
      `is_read` tinyint(1) DEFAULT 0,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      PRIMARY KEY (`id`),
      KEY `user_id` (`user_id`),
      KEY `booking_id` (`booking_id`),
      KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "✅ Created/Verified notifications table\n";
} catch(Exception $e) {
    echo "notifications table: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `email_queue` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `recipient_email` varchar(255) NOT NULL,
      `recipient_name` varchar(255) DEFAULT NULL,
      `subject` varchar(255) NOT NULL,
      `body_html` text NOT NULL,
      `status` enum('pending','sent','failed') DEFAULT 'pending',
      `error_message` text DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `sent_at` timestamp NULL DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    echo "✅ Created/Verified email_queue table\n";
} catch(Exception $e) {
    echo "email_queue table: " . $e->getMessage() . "\n";
}

echo "\nDone. Delete this file.";
