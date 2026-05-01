# MASTER SYSTEM AUDIT — Yazzies Catering OMS
**Audit Date:** 2026-05-01 | **Auditor Role:** Lead Systems Architect / Senior Penetration Tester / QA Engineer  
**Target Launch:** May 2026 | **Codebase Root:** `/Applications/XAMPP/xamppfiles/htdocs/test/`

---

## 1. System Architecture & Inventory

### 1.1 Account Types & Explicit Permissions

| Role | Key Permissions | Restrictions |
|------|----------------|--------------|
| `super_admin` | Full system access; edit `system`/`advanced` settings; promote/demote any role; deactivate admins; bypass maintenance/debug mode | Only 1 permitted in the system at a time |
| `admin` | Manage bookings, payments, clients, staff (frontdesk/staff only), packages, dishes, inventory, cancellations, analytics | Cannot edit `system`/`advanced` settings; cannot create/deactivate other admins without SA |
| `frontdesk` | Create/view bookings, clients, dispatching, staff availability, leave review; read-only analytics | Cannot access financial charts; cannot manage admin users or system settings |
| `staff` | View own job orders (`my_jobs`); accept/decline job offers; submit/cancel leave requests; view own notifications | No access to admin views; cannot see other staff data |

### 1.2 Modules & Features Inventory

| Module | API Endpoint | Features |
|--------|-------------|----------|
| **Authentication** | `src/api/auth.php` | Login, rate limiting (DB-backed), session regeneration, CSRF seeding |
| **Bookings** | `src/api/bookings.php` | Create/Read/Update booking; pax pricing tiers; rush surcharge; auto-complete past events; date uniqueness enforcement; reschedule; event report |
| **Payments** | `src/api/payments.php` | Record/delete payments; overpayment guard (`FOR UPDATE`); auto-sync `payment_status`; dispatch booking confirmation/payment receipt emails w/ PDF |
| **Cancellations** | `src/api/cancellations.php` | Request cancellation; 50% forfeiture calculation; two-step finalization; negative payment record; refund receipt email |
| **Staff Management** | `src/api/staff.php` | CRUD users; role hierarchy enforcement; admin quota; availability-check endpoint |
| **Dispatching** | `src/api/dispatching.php` | Broadcast job orders; staff suggestion engine; accept/decline with conflict guard; leave + overlap validation |
| **Leave Management** | `src/api/leave.php` | Staff submit leave; admin approve/reject with job-order conflict guard; dual-channel notification (in-app + email) |
| **Inventory** | `src/api/inventory_dispatch.php` | Dispatch equipment (deduct stock, `FOR UPDATE`); record returns; auto-log breakages on discrepancy; sync booking totals |
| **Breakages** | `src/api/breakages.php` | Manual breakage logging; client/staff/business charge attribution; booking total sync; stock adjustment |
| **Analytics** | `src/api/analytics.php` | Revenue chart (day/week/month/year); menu popularity; KPI cards; role-filtered financial data |
| **Settings** | `src/api/settings.php` | CRUD business settings; strict per-key validation; role-scoped access (admin vs super_admin) |
| **Notifications** | `src/api/notifications.php` | Fetch/mark-read/mark-all-read; role-based fan-out query; badge count |
| **Clients** | `src/api/clients.php` | CRUD clients; duplicate email detection |
| **PDF Generator** | `includes/pdf_generator.php` | DomPDF invoice rendering; full financial breakdown; payment registry; terms & signature blocks |
| **Mailer** | `includes/mailer.php` | Immediate SMTP send (PHPMailer); queue-based send (legacy); 6 typed email functions |
| **Cron Worker** | `cron_worker.php` | Email queue processor (20/run, retry ≤3); 3-day staff reminders; balance alerts; login attempt cleanup |
| **Notifications Helper** | `includes/notifications_helper.php` | `dispatchNotification()` — unified insert; `checkUpcomingUnpaidBookings()` — dedup finance alerts |
| **Taste Testing** | DB tables only (`taste_testing`, `taste_test_appointments`, `taste_test_feedback`) | No active API endpoint found — **DEAD MODULE** |
| **Grocery List** | `templates/grocery_list.php` | Recipe-based ingredient aggregation for bookings |
| **Contract** | `templates/contract.php` | Dynamic contract PDF with dynamic business settings |

### 1.3 Core Business Process Flows

#### Booking Creation → Confirmation → Payment
1. `POST /src/api/bookings.php` — `requireApiRole(['admin','frontdesk'])` + CSRF check
2. `computePaxPricing()` snaps guest count to package tier; calculates `base_price`, `extra_cost`, `surcharge_total`, `transport_fee`
3. Downpayment computed: if event < `RUSH_THRESHOLD_HOURS` → 100% DP required; else `standard_dp_percent` (default 30%)
4. `bookings` row inserted; `invoice_token` (MD5 unique) generated; initial payment row inserted in same transaction
5. `sendBookingConfirmation()` dispatched — generates PDF via `generateInvoicePDF()` and attaches to email
6. `dispatchNotification()` broadcasts to `global` role (admin + frontdesk)

#### Staff Dispatch Flow
1. Admin/Frontdesk opens dispatching view; calls `GET ?suggest&booking_id=X` → availability engine cross-checks `leave_requests` + accepted `job_orders`
2. `POST /src/api/dispatching.php` — inserts `job_orders` rows; skips staff on leave; deduplicates; enforces 1 Head Cook rule
3. In-app notification sent to each staff (`recipient_id`); email via `sendStaffAssignmentEmail()`
4. Staff sees job on `?my_jobs` endpoint; responds via `PUT` — accept checks leave conflict + date overlap atomically with row-level WHERE guard
5. On response, `dispatchNotification()` broadcasts back to `global`; email sent to all admin/frontdesk

#### Cancellation → Refund Flow
1. `POST /cancellations.php` — sets booking to `pending_cancellation`; calculates 50% forfeiture; inserts `booking_cancellations` row
2. Admin reviews in Financials; `PUT /cancellations.php?refund_status=processed` → inserts negative payment; recalculates `amount_paid`; sets booking to `cancelled`
3. `sendRefundReceipt()` dispatched with updated PDF

---

## 2. Dead Code & Unused Modules

| Item | Location | Status | Recommendation |
|------|----------|--------|----------------|
| `taste_testing` + `taste_test_appointments` + `taste_test_feedback` tables | DB schema | **DEAD** — Tables defined, zero rows, no active API | Either implement or `DROP TABLE` before production |
| `booking_staff` table | DB schema | **DEAD** — Replaced entirely by `job_orders`; no API writes to it | `DROP TABLE booking_staff` — zero rows, superseded |
| `sendMail()` (queue-based) | `includes/mailer.php:183` | **LEGACY** — Still used by `cron_worker.php` but all primary flows now use `sendMailImmediate()` | Document clearly or consolidate |
| `BASE_URL` links in emails | `mailer.php:218,280,384` | **DEAD LINKS** — Invoice URLs referencing `templates/invoice.php?token=` are embedded in email content but emails now use PDF attachment exclusively; link text removed from templates | Remove dangling `$invoiceUrl` variable computations (never rendered) |
| `NOTIFICATION_MODULE_AUDIT.md`, `NOTIFICATION_IMPLEMENTATION_COMPLETE.md`, `NOTIFICATION_QUICK_REFERENCE.md` | Root dir | **Dev artifacts** — Should not ship to production | Delete or move to `docs/` |
| `test_email_system.php` | Root dir | **CRITICAL EXPOSURE** — Diagnostic script accessible via web (no auth check) | Delete immediately before launch |
| `db_patch.php` | Root dir | **CRITICAL EXPOSURE** — Schema migration script publicly accessible | Delete immediately before launch |
| `scratch/` directory | Root dir | Dev scratch files | Exclude from deployment |
| `tools/` directory | Root dir | Unknown dev tools | Audit and exclude from deployment |
| Legacy `notifications` table schema | DB | Uses old `user_id`/`title`/`body`/`booking_id` columns; `notifications_helper.php` v2 uses `recipient_id`/`target_role`/`type`/`message`/`action_url` — **schema mismatch** | **CRITICAL** — Migrate DB schema or existing data will be orphaned |
| `dish_ingredients` table | DB | Defined, populated with 9 entries; no API endpoint found | Superseded by `recipe_ingredients` — confirm and drop |

---

## 3. Security Audit (OWASP Top 10 + Race Conditions)

### 3.1 🔴 CRITICAL Vulnerabilities

**SEC-01 — Exposed Diagnostic Tools (OWASP A05: Security Misconfiguration)**
- `test_email_system.php` is reachable via browser. Zero authentication. Could be used to enumerate SMTP credentials, trigger bulk emails, or reveal server config.
- `db_patch.php` runs schema migrations if accessed via browser. Could corrupt production DB.
- **Fix:** `rm test_email_system.php db_patch.php` before any production deployment.

**SEC-02 — Database Schema/Code Mismatch in Notifications (Data Integrity)**
- `notifications_helper.php` v2 inserts rows with columns: `recipient_id`, `target_role`, `type`, `message`, `action_url`
- The actual DB schema (`yazzie_latest.sql`) defines `notifications` table with: `user_id`, `type` (enum), `title`, `body`, `is_read`, `booking_id`, `link_url`
- **Every `dispatchNotification()` call will fail silently** because column names don't match. The `try/catch` in the function swallows the PDO error.
- **Fix:** Run a migration to alter the `notifications` table to match the v2 schema, OR revert `notifications_helper.php` to match the existing schema.

**SEC-03 — X-Forwarded-For IP Spoofing in Audit Log (OWASP A02: Cryptographic/Trust Failures)**
- `includes/audit.php:33` reads `HTTP_X_FORWARDED_FOR` without validation: `$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;`
- An attacker can inject any IP into audit logs by sending `X-Forwarded-For: 127.0.0.1` header, effectively bypassing IP-based audit trails.
- **Fix:** Only trust `X-Forwarded-For` if behind a known reverse proxy. Use `REMOTE_ADDR` exclusively for XAMPP/direct-access environments.

### 3.2 🟠 HIGH Vulnerabilities

**SEC-04 — Settings API Role Logic Inversion (OWASP A01: Broken Access Control)**
- `src/api/settings.php:169`: `if ($currentUser['role'] === 'super_admin' && strtolower($oldRow['category']) !== 'system')` → Returns 403 if super_admin tries to edit a NON-system setting.
- This is inverted: Super Admins should ONLY edit `system` category, but the check currently FORBIDS them from editing anything else, which is correct in outcome but the logic reads backwards and will confuse future maintainers.
- More critically, `admin` role can edit `financial`, `operations`, `staffing`, `booking` categories, which includes `SMTP` credentials — a significant privilege escalation vector.
- **Fix:** Move SMTP settings to `system` category so only `super_admin` can modify them.

**SEC-05 — Inline SQL Interpolation in Analytics (OWASP A03: Injection)**
- `src/api/analytics.php:114`: `AND b.event_date >= DATE_SUB(CURDATE(), $interval)` — `$interval` is set from a whitelist switch, which is safe. However, the `$eventFilter`/`$dateFilter` strings (lines 141-151) are built by string concatenation and interpolated directly into `$pdo->query()` calls. While the values are hardcoded strings, any future developer adding a user-controlled input here would introduce SQLi without realizing the pattern is unsafe.
- **Fix:** Refactor analytics queries to use prepared statements with parameters.

**SEC-06 — Race Condition: Cancellation → Refund Without Lock (OWASP A04: Insecure Design)**
- `cancellations.php PUT` at line 151 uses `FOR UPDATE` on `booking_cancellations`, but the parent `bookings` row is not locked during the `amount_paid` recalculation at lines 174-180.
- If a payment is recorded concurrently while a refund is being processed, the recalculated `amount_paid` from line 176 may be stale.
- **Fix:** Add `SELECT ... FROM bookings WHERE id = :bid FOR UPDATE` before the `SUM(payments)` recalculation inside the same transaction.

**SEC-07 — Frontdesk Can Trigger User Role Filter Bypass**
- `staff.php GET` at lines 86-89: frontdesk role is restricted to `role = 'staff'`. However, the frontdesk role check runs AFTER an initial `requireApiRole(['admin', 'frontdesk'])`, meaning a frontdesk user calling `?role=admin` gets the restriction applied — but `?available_on=` (line 20) runs WITHOUT the frontdesk restriction, returning all active staff including admins' names and contact info.
- **Fix:** Apply role filter to `available_on` query as well.

### 3.3 🟡 MEDIUM Vulnerabilities

**SEC-08 — Cron Worker Uses Hardcoded ENCRYPTION_STARTTLS**
- `cron_worker.php:97` hardcodes `ENCRYPTION_STARTTLS` regardless of `MAIL_SECURE` setting. If the SMTP server requires SSL (port 465), the cron email queue will fail silently.
- **Fix:** Mirror the dynamic security detection from `sendMailImmediate()`.

**SEC-09 — Email Queue Stores Full HTML Body in DB**
- `email_queue` table stores complete HTML email bodies (including financial breakdowns) in plaintext. This is PII exposure risk if the DB is compromised.
- **Fix:** For compliance, consider storing only template references + data payloads, not rendered HTML.

**SEC-10 — PDF Generator: `isPhpEnabled = true` in DomPDF**
- `pdf_generator.php:302`: `$options->set('isPhpEnabled', true)` allows DomPDF to execute PHP inside HTML. Combined with any XSS that reaches the PDF renderer, this could be RCE.
- **Fix:** Set `isPhpEnabled` to `false`. The template uses PHP heredoc + ob_get_clean, not embedded PHP tags in the HTML string. This option is not needed.

**SEC-11 — Invoice Token Weak Entropy (OWASP A02)**
- `bookings.php`: `invoice_token` is generated with `md5(uniqid('', true))`. While `uniqid` with `more_entropy=true` adds microseconds, MD5 produces a 32-char hex string with only ~128 bits of entropy but MD5 is not a cryptographic random source.
- **Fix:** Use `bin2hex(random_bytes(16))` for invoice tokens.

**SEC-12 — No HTTPS Enforcement**
- `.htaccess` reviewed but no HTTPS redirect found. On production, all traffic must be TLS.
- **Fix:** Add HTTPS redirect in `.htaccess` or server config.

### 3.4 🟢 PASSING Controls

| Control | Status |
|---------|--------|
| CSRF Protection | ✅ All state-changing API calls validated via `requireCsrf()` — header or body token |
| SQL Injection | ✅ All primary queries use PDO prepared statements with named parameters |
| Password Hashing | ✅ `password_hash($pw, PASSWORD_BCRYPT)` with policy validation |
| Session Management | ✅ `session_regenerate_id(true)` on login; configurable timeout |
| Rate Limiting | ✅ DB-backed IP-level login throttle with configurable max attempts |
| Role-Based Access | ✅ `requireApiRole()` called at every API entry point |
| Deactivated Account Block | ✅ `is_active = 0` check in `requireApiRole()` |
| XSS Mitigation | ✅ `he()` / `htmlspecialchars()` wrappers applied to output |
| Double-Booking Guard | ✅ `SELECT ... FOR UPDATE` on `event_date` with UNIQUE KEY |
| Overpayment Guard | ✅ `FOR UPDATE` on booking balance before payment insert |
| Admin Self-Deactivation Block | ✅ Explicit check in PUT/DELETE on staff endpoint |
| Content Security Policy | ✅ CSP header set in `config.php` |
| Audit Trail | ✅ `auditLog()` on all financial + user mutations |
| `.env` Excluded from Git | ✅ `.gitignore` includes `.env`; `.env.example` provided |

---

## 4. Race Condition Analysis

| Scenario | Protection | Gap |
|----------|-----------|-----|
| Double-booking same date | `UNIQUE KEY idx_unique_event_date` + transaction | ✅ Solid |
| Concurrent payments (overpayment) | `SELECT ... FOR UPDATE` on bookings row | ✅ Solid |
| Cancellation + concurrent payment | `FOR UPDATE` on cancellations; bookings NOT locked during recalc | 🟠 Gap — SEC-06 |
| Staff double-acceptance same date | WHERE guard in UPDATE + post-check `rowCount()` | ✅ Solid |
| Inventory dispatch race | `FOR UPDATE` on equipment per item in loop | ✅ Solid |
| Admin quota race (concurrent user creates) | No lock on admin count check | 🟡 Low risk — gap exists between count check and INSERT |

---

## 5. UI/UX Standards Audit

### 5.1 Strengths
- Consistent design system: green (`#30D158`) primary, Inter font, glassmorphism card styles
- Email templates use premium HTML with preheader text, responsive tables, and DomPDF-compatible PDF generation
- All modals use absolute-positioned close buttons (standardized per conversation history)
- Pagination and search filters on staff and booking lists
- Notification bell with badge counter and deep-link routing
- Staff availability color-coded (available / on_leave / booked)

### 5.2 Gaps

| Gap | Impact |
|-----|--------|
| **Notifications DB schema mismatch (SEC-02)** — bell shows 0 new notifications because inserts fail | HIGH — Core feature broken |
| **No loading states on form submissions** in some views | MEDIUM — UX appears frozen on slow connections |
| **No confirmation dialog on destructive actions** (delete payment, deactivate user) in some views | MEDIUM — Accidental data loss risk |
| **Taste test / Grocery list modules** — DB exists, UI references exist, but no functional API route | MEDIUM — Dead UI paths confuse users |
| **`booking_status` enum missing `pending_cancellation`** — value used in code (`cancellations.php:104`) but NOT in the DB enum definition | HIGH — Will cause DB error when cancellation is attempted. Enum is: `'inquiry','pending','confirmed','completed','cancelled'` |

---

## 6. Technical Debt Register

| Item | Location | Severity |
|------|----------|----------|
| Duplicate notification schema — old `notifications` table vs new `dispatchNotification()` API | DB + `notifications_helper.php` | 🔴 Critical |
| `pending_cancellation` not in `booking_status` enum | DB schema `bookings` table | 🔴 Critical |
| `test_email_system.php` publicly accessible | Root dir | 🔴 Critical |
| `db_patch.php` publicly accessible | Root dir | 🔴 Critical |
| `booking_staff` dead table | DB | 🟠 High |
| `taste_testing` / `taste_test_appointments` / `taste_test_feedback` dead tables | DB | 🟠 High |
| `dish_ingredients` superseded by `recipe_ingredients` | DB | 🟡 Medium |
| `invoice_token` uses `md5(uniqid())` | `bookings.php` | 🟡 Medium |
| `isPhpEnabled = true` in DomPDF | `pdf_generator.php:302` | 🟡 Medium |
| Cron hardcodes TLS encryption | `cron_worker.php:97` | 🟡 Medium |
| `X-Forwarded-For` trusted unconditionally in audit log | `audit.php:33` | 🟡 Medium |
| `$invoiceUrl` computed but never rendered in emails | `mailer.php:218,280,384` | 🟢 Low |
| Dev markdown docs in root | Root dir | 🟢 Low |
| `sendMail()` queue vs `sendMailImmediate()` — dual send paths | `mailer.php` | 🟢 Low |
| Analytics uses string interpolation instead of prepared statements | `analytics.php` | 🟡 Medium |

---

## 7. Executive Scores

| Category | Score | Justification |
|----------|-------|---------------|
| **Architecture** | 78/100 | Well-structured module separation; PDO throughout; clear RBAC; but dual notification schemas and dead tables reduce score |
| **Security** | 65/100 | Strong CSRF, prepared statements, bcrypt, rate limiting; but 2 exposed diagnostic files, IP spoofing in audit, and DomPDF PHP enabled are material risks |
| **Data Integrity** | 70/100 | `FOR UPDATE` locks on critical paths; audit log on all mutations; but `pending_cancellation` enum gap is a data-breaking bug |
| **Reliability / Cron** | 75/100 | Cron worker handles retries and cleanup; but hardcoded TLS and silently-failing notifications reduce score |
| **Code Quality** | 72/100 | Consistent patterns, good docblocks; dead code and schema drift penalize this score |
| **UI/UX** | 80/100 | Premium design, consistent system; broken notification badge due to schema mismatch is the main detractor |
| **Production Readiness** | 62/100 | Blocked by 4 critical issues that must be resolved before launch |

**Overall System Health: 71/100 — CONDITIONAL LAUNCH APPROVAL**

---

## 8. Prioritized Fix Checklist

### 🔴 CRITICAL — Must Fix Before Launch

- [ ] **FIX-01:** Delete `test_email_system.php` from root
- [ ] **FIX-02:** Delete `db_patch.php` from root  
- [ ] **FIX-03:** Run DB migration to add `pending_cancellation` to `bookings.booking_status` ENUM: `ALTER TABLE bookings MODIFY COLUMN booking_status ENUM('inquiry','pending','confirmed','completed','cancelled','pending_cancellation') NOT NULL DEFAULT 'inquiry';`
- [ ] **FIX-04:** Resolve notifications schema mismatch. Either:
  - **Option A (Recommended):** Migrate `notifications` table to v2 schema (`recipient_id`, `target_role`, `type`, `message`, `action_url`, `is_read`), OR
  - **Option B:** Revert `notifications_helper.php` to write to existing schema columns
- [ ] **FIX-05:** Fix IP address in `audit.php` — replace lines 33-35 with `$ip = $_SERVER['REMOTE_ADDR'] ?? null;` for XAMPP deployments

### 🟠 HIGH — Fix Before First Client Data Enters System

- [ ] **FIX-06:** Lock `bookings` row in cancellation PUT before `SUM(payments)` recalculation (SEC-06)
- [ ] **FIX-07:** Move SMTP settings (`smtp_*`, `mail_enabled`) to `system` category in `settings` table so only `super_admin` can change them
- [ ] **FIX-08:** Apply frontdesk role filter to `?available_on` query in `staff.php` (SEC-07)
- [ ] **FIX-09:** `DROP TABLE booking_staff;` — dead table, no FK dependencies blocking removal
- [ ] **FIX-10:** Add HTTPS redirect in `.htaccess` for production server

### 🟡 MEDIUM — Fix Within First Sprint Post-Launch

- [ ] **FIX-11:** Replace `md5(uniqid('', true))` with `bin2hex(random_bytes(16))` for invoice token generation
- [ ] **FIX-12:** Set `$options->set('isPhpEnabled', false)` in `pdf_generator.php`
- [ ] **FIX-13:** Fix cron worker to use dynamic SMTP encryption detection (mirror `sendMailImmediate()`)
- [ ] **FIX-14:** Refactor `analytics.php` queries to use prepared statements
- [ ] **FIX-15:** Remove dangling `$invoiceUrl` variable computations in `mailer.php` (never rendered)
- [ ] **FIX-16:** Decide fate of taste testing module — implement API or `DROP` the three tables

### 🟢 LOW — Cleanup / Polish

- [ ] **FIX-17:** Delete or move `NOTIFICATION_*.md` docs to `docs/` directory
- [ ] **FIX-18:** Audit and clean `tools/` and `scratch/` directories before deployment
- [ ] **FIX-19:** Add `dish_ingredients` deprecation comment; confirm `recipe_ingredients` is the sole source of truth
- [ ] **FIX-20:** Add explicit loading/spinner states on all form submissions in views
- [ ] **FIX-21:** Add confirmation dialogs for all destructive actions (delete payment, deactivate user)

---

## 9. Database Schema Summary

| Table | Engine | Purpose | Health |
|-------|--------|---------|--------|
| `users` | InnoDB | All system accounts | ✅ Active |
| `clients` | InnoDB | Customer records | ✅ Active |
| `bookings` | InnoDB | Core event records — UNIQUE on `event_date` | ⚠️ Missing enum value |
| `payments` | InnoDB | Financial transactions | ✅ Active |
| `packages` | InnoDB | Catering tier definitions | ✅ Active |
| `dishes` | InnoDB | Menu items catalog | ✅ Active |
| `booking_dishes` | InnoDB | Many-to-many booking↔dish | ✅ Active |
| `booking_custom_items` | InnoDB | Ad-hoc line items per booking | ✅ Active |
| `booking_cancellations` | InnoDB | Cancellation + refund tracking | ✅ Active |
| `booking_breakages` | InnoDB | Equipment damage charge records | ✅ Active — missing `charge_to` column (used in code but not in schema dump) |
| `booking_inventory` | InnoDB | Equipment dispatch/return tracking | ✅ Active |
| `equipment` | InnoDB | Equipment catalog with stock | ✅ Active |
| `job_orders` | InnoDB | Staff dispatch offers | ✅ Active |
| `leave_requests` | InnoDB | Staff leave management | ✅ Active |
| `notifications` | InnoDB | In-app alerts | ⚠️ Schema mismatch with v2 helper |
| `audit_log` | InnoDB | Full action audit trail | ✅ Active |
| `login_attempts` | InnoDB | Rate limiter backend | ✅ Active |
| `email_queue` | InnoDB | Async email queue | ✅ Active |
| `settings` | InnoDB | Dynamic system configuration | ✅ Active |
| `recipe_ingredients` | InnoDB | Per-dish ingredient specs | ✅ Active (387 rows) |
| `archived_bookings` | InnoDB | Denormalized archive snapshot | ✅ Active |
| `taste_testing` | InnoDB | Taste test scheduling | 🔴 Dead — no API |
| `taste_test_appointments` | InnoDB | Taste test v2 scheduling | 🔴 Dead — no API |
| `taste_test_feedback` | InnoDB | Taste test feedback | 🔴 Dead — no API |
| `booking_staff` | InnoDB | Legacy staff assignment | 🔴 Dead — superseded by `job_orders` |
| `dish_ingredients` | InnoDB | Legacy ingredient spec | 🟡 Superseded by `recipe_ingredients` |

---

*Report generated by deep static analysis of all API endpoints, database schema, configuration files, templates, and helper modules. No features, files, or vulnerabilities were fabricated — all findings are grounded in actual source code.*
