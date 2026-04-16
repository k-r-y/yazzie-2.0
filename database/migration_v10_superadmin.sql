-- ============================================================
-- Migration V10 — Super Admin Role + Staff Job Class
-- Run this in phpMyAdmin on the `yazzie` database
-- ============================================================

-- 1. Expand the users.role ENUM to include 'super_admin'
ALTER TABLE `users`
    MODIFY COLUMN `role`
        ENUM('super_admin','admin','frontdesk','staff')
        NOT NULL DEFAULT 'staff';

-- 2. Add job_class to users (for lineup enforcement on staff)
ALTER TABLE `users`
    ADD COLUMN `job_class`
        ENUM('head_cook','cook','waiter','server','helper','any')
        NOT NULL DEFAULT 'any'
        COMMENT 'Staff job classification for booking lineup enforcement';

-- 3. Add job_class to job_orders (snapshot of role at assignment time)
ALTER TABLE `job_orders`
    ADD COLUMN `job_class`
        ENUM('head_cook','cook','waiter','server','helper','any')
        NOT NULL DEFAULT 'any'
        COMMENT 'Mirrors the staff job_class at time of assignment';

-- 4. Seed the Super Admin account
--    Default password: SuperAdmin@2026 (change immediately after first login)
-- Default credentials: superadmin@yazzies.com / SuperAdmin@2026!
-- ⚠️ CHANGE THE PASSWORD IMMEDIATELY AFTER FIRST LOGIN
INSERT INTO `users` (`name`, `email`, `password`, `role`, `phone`, `is_active`, `job_class`)
VALUES (
    'Super Admin',
    'superadmin@yazzies.com',
    '$2y$10$hZDvbvkOB22GUtBVBis2nOE.Bd2D5olq.FTl.nrQv5Ec0LGltTGke',
    'super_admin',
    NULL,
    1,
    'any'
);
-- Password is: SuperAdmin@2026!
