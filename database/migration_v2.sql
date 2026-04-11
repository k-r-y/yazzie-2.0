-- ================================================================
-- Yazzies OMS — Migration v2: Package-Based Pricing Engine
-- Run ONCE after the initial schema.sql import
-- ================================================================

-- ── 1. Extend menus table with package pricing fields ────────────
ALTER TABLE `menus`
    ADD COLUMN `base_pax`   INT UNSIGNED   NOT NULL DEFAULT 50       COMMENT 'Minimum pax count this package covers' AFTER `price_per_pax`,
    ADD COLUMN `base_price` DECIMAL(10,2)  NOT NULL DEFAULT 10000.00  COMMENT 'Flat price for base_pax guests'       AFTER `base_pax`;

-- price_per_pax is now the PRO-RATED extra-pax rate: base_price / base_pax
-- We'll recompute it in the app; keep the column for backward compat.

-- ── 2. Update existing seed menus with realistic pricing ─────────
UPDATE `menus` SET base_pax = 50, base_price = 10000.00, price_per_pax = 200.00 WHERE name = 'Budget Package';
UPDATE `menus` SET base_pax = 50, base_price = 12500.00, price_per_pax = 250.00 WHERE name = 'Standard Package';
UPDATE `menus` SET base_pax = 50, base_price = 17500.00, price_per_pax = 350.00 WHERE name = 'Premium Package';
UPDATE `menus` SET base_pax = 50, base_price = 15000.00, price_per_pax = 300.00 WHERE name = 'Corporate Package';

-- ── 3. Extend bookings table ─────────────────────────────────────
ALTER TABLE `bookings`
    ADD COLUMN `base_pax`      INT UNSIGNED   DEFAULT 50   COMMENT 'Package tier pax chosen at booking time' AFTER `pax_count`,
    ADD COLUMN `extra_pax`     INT UNSIGNED   DEFAULT 0    COMMENT 'Pax above the package tier'              AFTER `base_pax`,
    ADD COLUMN `base_price`    DECIMAL(10,2)  DEFAULT 0.00 COMMENT 'Package base price snapshot'             AFTER `extra_pax`,
    ADD COLUMN `extra_cost`    DECIMAL(10,2)  DEFAULT 0.00 COMMENT 'Pro-rated cost for extra pax'            AFTER `base_price`;

-- ── 4. Note on date uniqueness ───────────────────────────────────
-- We enforce one booking per event_date at the APPLICATION layer
-- (see src/api/availability.php) to allow flexibility with
-- cancelled bookings freeing up dates.
-- If you want a hard DB constraint, uncomment:
-- ALTER TABLE `bookings` ADD UNIQUE KEY `uq_event_date` (`event_date`);
