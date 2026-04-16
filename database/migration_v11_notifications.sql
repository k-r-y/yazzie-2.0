-- ============================================================
-- Migration V11 — Notifications: Expanded ENUM + link_url + booking_id FK
-- Run this in phpMyAdmin on the `yazzie` database AFTER V10
-- ============================================================

-- 1. Expand type ENUM to include job_declined and leave_reviewed
ALTER TABLE `notifications`
    MODIFY COLUMN `type`
        ENUM('job_assigned','job_declined','leave_approved','leave_rejected','leave_reviewed','general')
        NOT NULL DEFAULT 'general';

-- 2. Add a link_url column for click-to-navigate functionality
ALTER TABLE `notifications`
    ADD COLUMN `link_url` VARCHAR(500) DEFAULT NULL
        COMMENT 'Optional URL to navigate to when notification is clicked';
