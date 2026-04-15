-- ============================================================
-- Migration v5 — Yazzie's Catering OMS
-- Addresses audit findings: P2-02, P2-03, P2-05
-- Date: 2026-04-15
-- Run on: MySQL / MariaDB (yazzie database)
-- BACKUP FIRST: mysqldump yazzie > yazzie_backup_pre_v5.sql
-- ============================================================

USE `yazzie`;

-- ── P2-02: Add super_admin to users.role ENUM ─────────────────────────────
-- Allows a privileged super_admin tier above regular admins.
ALTER TABLE `users`
    MODIFY COLUMN `role`
        ENUM('super_admin','admin','frontdesk','staff') NOT NULL DEFAULT 'staff';

-- Create the first super_admin from the existing primary admin account (ID=1)
UPDATE `users` SET `role` = 'super_admin' WHERE `id` = 1;

-- ── P2-03: Refund Management ────────────────────────────────────────────────
-- Adds payment_type to payments table so refunds are tracked as negative flows.
-- The SUM-based balance logic in payments.php handles this correctly because
-- refunds have negative amounts.
ALTER TABLE `payments`
    ADD COLUMN `payment_type` ENUM('payment', 'refund') NOT NULL DEFAULT 'payment'
        AFTER `payment_method`,
    ADD COLUMN `refund_reason` VARCHAR(255) NULL
        AFTER `payment_type`;

-- Index for fast refund queries on the financial report
ALTER TABLE `payments`
    ADD KEY `idx_payment_type` (`payment_type`);

-- ── P2-05: Actual Costing / Expense Tracking ──────────────────────────────
-- Tracks real costs incurred per event (ingredients, staff wages, etc.)
-- so Yazzie can calculate actual net profit per booking.
CREATE TABLE IF NOT EXISTS `event_actual_costs` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `booking_id`  INT UNSIGNED NOT NULL,
    `cost_type`   ENUM('ingredients','staff_wages','equipment','transport','other') NOT NULL DEFAULT 'other',
    `description` VARCHAR(255) DEFAULT NULL,
    `amount`      DECIMAL(10,2) NOT NULL,
    `recorded_by` INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_eac_booking` (`booking_id`),
    KEY `idx_eac_type`    (`cost_type`),
    CONSTRAINT `fk_eac_booking`  FOREIGN KEY (`booking_id`)  REFERENCES `bookings` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_eac_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users`    (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tracks real per-event expenses for P&L reporting';

-- ── P2-06 + Notification type: booking_rescheduled ────────────────────────
-- The notifications.type ENUM did not include 'booking_rescheduled' — our
-- new reschedule notification in bookings.php PUT would silently fail/error.
ALTER TABLE `notifications`
    MODIFY COLUMN `type`
        ENUM('job_assigned','leave_approved','leave_rejected','booking_rescheduled','general')
        NOT NULL DEFAULT 'general';

-- ── Archived bookings: add package_name column (optional upgrade) ─────────
-- This is optional. If you want the Archive to store the package tier name,
-- run this. The archive API already writes this field if the column exists.
-- If you revert the API code, comment this out.
ALTER TABLE `archived_bookings`
    ADD COLUMN `package_name` VARCHAR(60) DEFAULT NULL
        AFTER `client_phone`;

-- ── Index: speed up staff availability check (job_orders) ─────────────────
-- Composite index to make the new job_orders-based availability check fast.
ALTER TABLE `job_orders`
    ADD KEY `idx_jo_staff_status` (`staff_id`, `status`);

-- ── Data integrity: dishes.base_pax must be > 0 ───────────────────────────
-- All existing dishes have base_pax=2 (correct), but enforce it going forward.
ALTER TABLE `dishes`
    ADD CONSTRAINT `chk_base_pax_positive`
        CHECK (`base_pax` > 0);

-- ── P2-10: Canonical note ─────────────────────────────────────────────────
-- The canonical schema is now: yazzie-2.sql + this migration.
-- After this migration, export a new dump: mysqldump --no-data yazzie > database/schema_current.sql

COMMIT;
