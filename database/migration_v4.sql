-- ================================================================
-- Yazzies OMS — Migration v4: Pending Workflow + Audit Log
-- Run ONCE after migration_v3.sql
-- ================================================================

-- ── 1. Add 'pending' to booking_status ENUM ───────────────────────
-- pending = booking created but downpayment < 50% (awaiting DP)
-- confirmed = downpayment >= 50% received
ALTER TABLE `bookings`
  MODIFY COLUMN `booking_status`
    ENUM('pending','confirmed','completed','cancelled')
    NOT NULL DEFAULT 'pending';

-- ── 2. AUDIT LOG — tracks all financial & booking mutations ───────
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`    INT UNSIGNED    NOT NULL COMMENT 'Who performed the action',
  `action`     VARCHAR(60)     NOT NULL COMMENT 'e.g. payment_recorded, booking_confirmed',
  `entity`     VARCHAR(30)     NOT NULL COMMENT 'booking | payment | client | job_order',
  `entity_id`  INT UNSIGNED    NOT NULL,
  `old_value`  JSON            DEFAULT NULL COMMENT 'State before the change',
  `new_value`  JSON            DEFAULT NULL COMMENT 'State after the change',
  `ip_address` VARCHAR(45)     DEFAULT NULL,
  `created_at` TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_al_entity` (`entity`, `entity_id`),
  KEY `idx_al_user`   (`user_id`),
  KEY `idx_al_action` (`action`),
  KEY `idx_al_ts`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Update existing confirmed bookings with no DP to pending ───
-- Any booking that has amount_paid = 0 should be 'pending', not 'confirmed'
UPDATE `bookings`
SET `booking_status` = 'pending'
WHERE `booking_status` = 'confirmed'
  AND `amount_paid` = 0.00;
