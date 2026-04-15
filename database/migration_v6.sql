-- ============================================================
-- Migration v6 — Production Architecture Upgrade
-- Implements Async Mailer Queue & N+1 Indexing Fixes
-- ============================================================

USE `yazzie`;

-- 1. Indexing optimizations to prevent full table scans
ALTER TABLE `bookings`
    ADD KEY `idx_event_date` (`event_date`);

-- 2. Async Mail Queue Engine
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `recipient_email` VARCHAR(255) NOT NULL,
    `recipient_name`  VARCHAR(255) NOT NULL,
    `subject`         VARCHAR(255) NOT NULL,
    `body_html`       LONGTEXT NOT NULL,
    `status`          ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    `attempts`        INT UNSIGNED NOT NULL DEFAULT 0,
    `error_log`       TEXT DEFAULT NULL,
    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `sent_at`         TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_queue_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
