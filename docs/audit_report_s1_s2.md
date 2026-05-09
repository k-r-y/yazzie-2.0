# Yazzies Catering OMS — Enterprise System Documentation & Audit Report
**Generated:** 2026-05-09 | **Codebase:** `/htdocs/test` | **DB:** `backup_yazzie_2026-05-08_163925.sql`

---

# 1. Executive Business Overview

## The Problem
Yazzies Catering faces operational challenges common to Philippine catering SMEs:
- **Double-bookings** — no system to enforce one-event-per-date.
- **Fragmented cash flow** — downpayments tracked on paper with no reconciliation.
- **Staff coordination failure** — assigning cooks/waiters via SMS is unreliable.
- **Inventory blindspot** — no tracking of whether equipment left and returned intact.
- **Zero audit trail** — no record of who changed prices, cancelled bookings, or deleted payments.
- **No online payment path** — manual GCash transfers with no automated confirmation.

## The Solution
The OMS digitizes the entire catering lifecycle:

| Lifecycle Stage | OMS Module |
|---|---|
| Lead capture | Client Management + Taste-Test Appointments (schema-ready) |
| Booking & quoting | Multi-step Booking Wizard with dynamic pricing engine |
| Payment collection | Cash/GCash/Maya recording + PayMongo online checkout |
| Staff coordination | Job Order Dispatching with accept/decline workflow |
| Equipment tracking | Inventory Dispatch & Return with auto-breakage logging |
| Event execution | Staff Dashboard + Post-Event Report |
| Financial reporting | Financial Module with revenue analytics |
| Administration | Superadmin Console + complete Audit Log (445+ entries) |

## Deployment Strategy: Why This MUST Be Hosted Online

| Argument | Technical Detail |
|---|---|
| **PayMongo Webhooks** | PayMongo POSTs payment events to a public URL. `localhost` is unreachable from the internet — online payments can never auto-confirm without a live server. |
| **Client Payment Portal** | The invoice link must be accessible to clients on their phones. A `localhost` URL is meaningless outside the office. |
| **Staff Mobile Access** | Staff log in from the field (job board, event reports, inventory). Requires a live, public URL. |
| **24/7 Cron Worker** | `cron_worker.php` (email queue, reminders, cleanup) must run on an always-on server — a developer laptop is not a server. |
| **SMTP Deliverability** | Gmail/SMTP from localhost is flagged as spam. A proper domain improves email deliverability. |
| **Business Continuity** | A single hard-drive failure on the dev machine wipes all booking data. Cloud hosting provides redundancy. |
| **Recommended Stack** | Shared hosting (Hostinger/Namecheap), cPanel, PHP 8.1+, MySQL 8.0. Est. cost: ₱2,500–5,000/year. |

---

# 2. User Roles & System Modules

## Account Types & Capabilities

### Role: `admin` (Active Administrator)
Single active operator enforced by system. Full access to all modules.

| Module | C | R | U | D | Notes |
|---|---|---|---|---|---|
| Bookings | ✅ | ✅ | ✅ | ✅ | Wizard, status changes, archiving |
| Clients | ✅ | ✅ | ✅ | ✅ | Full CRUD |
| Payments | ✅ | ✅ | ✅ | ✅ | Record, delete, refund, PayMongo |
| Staff/Users | ✅ | ✅ | ✅ | ✅ | Create, deactivate, Master Key Transfer |
| Dispatching | ✅ | ✅ | ✅ | ✅ | Send job orders, view responses |
| Inventory | ✅ | ✅ | ✅ | ✅ | Manage equipment stock |
| Dishes/Packages | ✅ | ✅ | ✅ | ✅ | Menu and pricing |
| Financial Reports | ✅ | ✅ | — | — | Revenue analytics |
| Superadmin Console | ✅ | ✅ | ✅ | — | Settings, DB backup, audit log |

**Pages:** `dashboard.php`, `bookings.php`, `clients.php`, `staff.php`, `financial.php`, `inventory.php`, `dishes.php`, `packages.php`, `recipes.php`, `settings.php`, `superadmin.php`, `archive.php`

### Role: `frontdesk`
Day-to-day operations. Cannot manage users or view financial analytics.

| Module | C | R | U | D |
|---|---|---|---|---|
| Bookings | ✅ | ✅ | ✅ | — |
| Clients | ✅ | ✅ | ✅ | — |
| Payments | ✅ | ✅ | — | — |
| Dispatching | ✅ | ✅ | ✅ | ✅ |

**Pages:** `frontdesk/dashboard.php`, `frontdesk/bookings.php`, `frontdesk/dispatching.php`, `frontdesk/costing.php`

### Role: `staff`
Field workers. Isolated to job board, inventory, and event reports.

| Module | C | R | U | D |
|---|---|---|---|---|
| My Job Orders | — | ✅ | ✅ accept/decline | — |
| Inventory Dispatch | ✅ | ✅ | ✅ | — |
| Event Report | ✅ | ✅ | — | — |
| Leave Requests | ✅ | ✅ | — | — |
| Notifications | — | ✅ | ✅ mark-read | — |

**Pages:** `staff/dashboard.php`, `staff/event_report.php`

---

## Core Modules & Process Flows

### Module 1: Booking Wizard (End-to-End)
1. Select/create client (`nc_name` auto-creates new client record).
2. Choose event date — validated: ≥3 days lead time, no conflicts (`UNIQUE KEY idx_unique_event_date` + `FOR UPDATE` row lock inside transaction).
3. Select package (SET A/B/C at 50/75/100 pax) or custom pax count.
4. Choose dishes — enforces `max_main_dishes` and `max_desserts` per package; excess dishes trigger per-pax `custom_fee` surcharges.
5. Add custom items (lechon, etc.) — flat or per-pax pricing.
6. Enter transport fee, event type, dietary notes.
7. **Rush Booking Check:** If within `rush_threshold_hours` (120 hrs), forces 100% payment (`rush_dp_percent = 1.0`).
8. Pay via cash/GCash/Maya OR initiate PayMongo checkout session.
9. Transaction: `INSERT INTO bookings` → `booking_dishes` → `booking_custom_items` → `payments` — all inside `beginTransaction()`/`commit()`/`rollBack()`.
10. Audit log + confirmation email queued.

### Module 2: Payments & Online Collections
1. Admin records additional payments against booking (POST `/src/api/payments.php`).
2. System SUMs all payments → updates `amount_paid`, `payment_status` (unpaid/partial/paid).
3. **PayMongo Flow:** Checkout session created → client redirected to hosted page → PayMongo POSTs webhook → `webhooks/paymongo.php` validates HMAC-SHA256 signature → inserts payment with `gateway_reference_id` (UNIQUE constraint = idempotency key prevents duplicate recording).
4. `payment_status.php` polled every few seconds from invoice page (AJAX short-polling) until payment confirms.
5. Balance reminders sent manually via admin or automatically by cron (3 days before event).

### Module 3: Staff Dispatching
1. Admin opens dispatching panel → Staff Suggestion Engine runs.
2. Engine filters: `leave_requests` (approved leave) + `job_orders` (already booked same date).
3. Calculates recommended count from `waiter_ratio_wedding`/`waiter_ratio_birthday` settings.
4. Enforces **1 Head Cook per event** rule at API level.
5. `job_orders` inserted with `status = 'pending'`.
6. Staff receives in-app notification + email.
7. Staff accepts/declines → system checks leave conflict + same-date overlap (prevents double-booking).
8. Admin notified in-app + email.

### Module 4: Inventory Dispatch & Return
1. Staff dispatches equipment (POST) — `FOR UPDATE` lock checks `current_stock` before deducting.
2. After event: Staff records returns (PUT). System computes `quantity_out - quantity_in`.
3. Shortfall → **auto-logs breakage** to `booking_breakages` (`ON DUPLICATE KEY UPDATE` = idempotent).
4. `booking.breakage_total` + `total_cost` recalculated atomically in same transaction.
5. If booking was archived, it is **automatically unarchived** to allow new charges.

### Module 5: Cancellation Management
1. Admin requests cancellation → `booking_cancellations` created (`status = 'requested'`).
2. Forfeiture = `cancel_forfeiture_percent` (50%) × `total_cost`.
3. Finalized → `booking_status = 'cancelled'`, refund payment inserted (negative amount).
4. Un-cancel restores status + reinstates prior payments.

### Module 6: Post-Event Report
1. Assigned staff submits report (`actual_start_time`, `actual_end_time`, `overtime_minutes`, `breakage` items).
2. `overtime_total = overtime_minutes × overtime_rate / 60` calculated and stored.
3. `report_submitted_by` + `report_submitted_at` updated. Admin reviews and manually archives.

### Module 7: Archive
1. Admin archives a completed paid booking.
2. Snapshot written to `archived_bookings` with full financial summary.
3. `bookings.is_archived = 1`. Hidden from main list by default.

### Module 8: Taste-Testing (Schema-Ready)
Tables `taste_testing`, `taste_test_appointments`, `taste_test_feedback` exist in the database. Supports prospect → appointment → feedback → booking conversion. **No frontend/API wired yet** — schema only.

---

## Advanced/Enterprise Features

| Feature | Implementation | Why It's Defense-Ready |
|---|---|---|
| PayMongo Webhooks | `webhooks/paymongo.php` — HMAC-SHA256 signature verification. `gateway_reference_id` UNIQUE constraint prevents duplicate payment recording. | Real-world payment gateway integration with idempotency. |
| OTP Account Recovery | 3-step: Email → OTP verify (rate-limited via `otp_attempts` table) → Password reset. OTP expires on timeout. | Brute-force protected with IP + email rate limiting. |
| Master Key Transfer | Admin transfers "master" role to another admin. Logged as `admin_master_transfer` in audit log. Single-active-admin enforced. | Business succession management. Full audit trail. |
| AJAX Short Polling | `payment_status.php` polled every few seconds from invoice page to detect webhook confirmation. | Real-time UX without WebSockets. Industry standard for payment confirmation. |
| PDO Transactions | All multi-table writes wrapped in `beginTransaction()`/`commit()`/`rollBack()`. | Prevents partial data corruption. Data integrity guaranteed. |
| Concurrent Booking Guard | `SELECT … FOR UPDATE` inside booking transaction prevents simultaneous same-date bookings. | Solves TOCTOU race condition. |
| Role-Based Audit Log | Every C/U/D writes to `audit_log`: `user_id`, `action`, `entity`, `old_value` (JSON), `new_value` (JSON), `ip_address`. 445+ entries in production. | Full forensic trail. Enterprise compliance standard. |
| Cron Worker | CLI-only (403 if browser access). Tasks: email queue, 3-day reminders, balance alerts, login cleanup. PHPMailer with STARTTLS. | Decoupled background processing. |
| CSRF Protection | `requireCsrf()` on every state-changing API. Token in session, validated via `X-CSRF-Token` header. | Prevents Cross-Site Request Forgery. Applied system-wide. |
| Rush Booking Engine | Calculates hours to event. If `< 120 hrs`, forces 100% downpayment. Configurable via settings. | Dynamic business rule engine. Zero hardcoding. |


# Sections 3–5: Database, Design System, QA Audit

---

# 3. Database Architecture & ERD

## Data Dictionary

### `users`
| Column | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | Auto-increment user ID |
| `name` | VARCHAR(100) | Full name |
| `email` | VARCHAR(150) UNIQUE | Login email |
| `password` | VARCHAR(255) | bcrypt hash |
| `role` | ENUM('super_admin','admin','frontdesk','staff') | Access role |
| `phone` | VARCHAR(20) | Contact number |
| `is_active` | TINYINT(1) | 1=active, 0=deactivated |
| `job_class` | ENUM('head_cook','cook','waiter','server','helper','any','admin','frontdesk') | Staff specialty |
| `created_at` | TIMESTAMP | Account creation time |

### `clients`
| Column | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | Auto-increment |
| `name` | VARCHAR(100) | Client full name |
| `email` | VARCHAR(150) | Contact email |
| `messenger_link` | VARCHAR(255) | Facebook/Messenger profile |
| `phone` | VARCHAR(20) | Mobile number |
| `address` | TEXT | Home/delivery address |
| `created_at` | TIMESTAMP | Record creation |

### `bookings` (Core Table — 36 columns)
| Column | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | Booking ID |
| `client_id` | INT UNSIGNED FK→clients | Who booked |
| `package_id` | INT UNSIGNED FK→packages | Package tier chosen (nullable) |
| `event_type` | VARCHAR(50) | Wedding/Debut/Birthday/etc. |
| `event_date` | DATE UNIQUE | Event date (enforced unique) |
| `event_time` | TIME | Start time |
| `event_location` | TEXT | Venue |
| `pax_count` | INT UNSIGNED | Total guests |
| `base_pax` | INT UNSIGNED | Tier base pax count |
| `extra_pax` | INT UNSIGNED | Pax above base |
| `base_price` | DECIMAL(10,2) | Package base price |
| `extra_cost` | DECIMAL(10,2) | Extra pax cost |
| `transport_fee` | DECIMAL(10,2) | Delivery/travel fee |
| `surcharge_total` | DECIMAL(10,2) | Sum of custom_item prices |
| `breakage_total` | DECIMAL(10,2) | Auto-calculated breakage charges |
| `total_cost` | DECIMAL(10,2) | Grand total |
| `amount_paid` | DECIMAL(10,2) | Sum of all payments |
| `payment_status` | VARCHAR(255) | unpaid/partial/paid |
| `booking_status` | VARCHAR(255) | pending/confirmed/completed/cancelled |
| `is_archived` | TINYINT(1) | 1=archived |
| `invoice_token` | VARCHAR(255) | Public access token for invoice |
| `paymongo_checkout_id` | VARCHAR(255) | PayMongo session ID (cs_xxxx) |
| `dietary_notes` | TEXT | Allergy/dietary restrictions |
| `overtime_minutes` | INT | Minutes over scheduled end |
| `overtime_rate` | DECIMAL(10,2) | Rate per hour |
| `overtime_total` | DECIMAL(10,2) | Computed overtime charge |
| `created_by` | INT UNSIGNED FK→users | Staff who created booking |
| `report_submitted_by` | INT UNSIGNED FK→users | Staff who submitted event report |

### `payments`
| Column | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | Payment ID |
| `booking_id` | INT UNSIGNED FK→bookings | Associated booking |
| `amount` | DECIMAL(10,2) | Amount (negative = refund) |
| `payment_method` | ENUM('cash','bank_transfer','gcash','maya','paymongo') | How paid |
| `payment_type` | ENUM('payment','refund') | Transaction direction |
| `gateway_reference_id` | VARCHAR(255) UNIQUE | PayMongo pay_xxxx ID (idempotency key) |
| `reference_no` | VARCHAR(100) | Manual GCash/bank reference |
| `payment_date` | DATE | Date of payment |
| `recorded_by` | INT UNSIGNED FK→users | Staff who recorded it |

### `packages`
| Column | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | Package ID |
| `set_name` | VARCHAR(100) | SET A / SET B / SET C |
| `pax_count` | INT UNSIGNED | Base pax for this tier |
| `price` | DECIMAL(10,2) | Flat price at base pax |
| `max_main_dishes` | INT UNSIGNED | Allowed main dishes |
| `max_desserts` | INT UNSIGNED | Allowed desserts |
| `includes_rice` | TINYINT(1) | Whether rice is included |

**Current packages:** SET A (50/75/100 pax: ₱28,500/34,500/41,500), SET B (50/75/100: ₱39,500/45,500/52,500), SET C (50/75/100: ₱41,500/62,500/72,500)

### `dishes`
| Column | Type | Description |
|---|---|---|
| `id` | INT UNSIGNED PK | Dish ID |
| `name` | VARCHAR(100) | Dish name |
| `category` | VARCHAR(30) | Beef/Pork/Chicken/Seafood/Vegetables/Pasta/Rice/Dessert |
| `meal_type` | VARCHAR(100) | breakfast/lunch/dinner/all |
| `base_pax` | INT | Base pax for this dish |
| `custom_fee` | DECIMAL(10,2) | Extra per-pax charge if dish is selected |
| `is_active` | TINYINT(1) | Whether dish is bookable |

**71 dishes** across 8 categories.

### `booking_dishes` (Junction)
Links bookings to selected dishes. `UNIQUE KEY uq_booking_dish(booking_id, dish_id)`.

### `booking_custom_items`
Free-text add-ons per booking (lechon, juice, etc.) with category and price.

### `booking_breakages`
| Column | Type | Description |
|---|---|---|
| `booking_id` | FK→bookings | Associated booking |
| `equipment_id` | FK→equipment | Which item broke |
| `quantity` | INT UNSIGNED | Number broken |
| `unit_price` | DECIMAL(10,2) | Snapshotted replacement cost |
| `total_cost` | DECIMAL(10,2) | qty × unit_price |
| `charge_to` | ENUM('client','staff','business') | Who bears the cost |
| `logged_by` | FK→users | Who recorded it |

`UNIQUE KEY uq_bb_booking_equipment(booking_id, equipment_id)` — prevents duplicate breakage entries for same item.

### `booking_cancellations`
Full financial record of cancellation including `deposit_forfeit`, `refundable_amount`, `refund_method`, `refund_processed_by`.

### `booking_staff` / `job_orders`
`job_orders` is the active dispatching table. `booking_staff` is the legacy table (empty). `job_orders` tracks: `staff_id`, `role_required`, `status` (pending/accepted/declined), `job_class` (snapshotted at dispatch time).

### `equipment`
| Column | Type | Description |
|---|---|---|
| `name` | VARCHAR(100) | Equipment name |
| `category` | VARCHAR(50) | General/Dinnerware |
| `current_stock` | INT | Available units (deducted on dispatch) |
| `total_stock` | INT | Total owned |
| `replacement_cost` | DECIMAL(10,2) | Cost if broken |

### `booking_inventory`
Tracks each dispatch event: `quantity_out`, `quantity_in`, `dispatched_by`, `dispatched_at`, `returned_by`, `returned_at`. `UNIQUE KEY uq_booking_equipment(booking_id, equipment_id)`.

### `leave_requests`
Staff leave requests with `leave_date`, `status` (pending/approved/rejected), `reviewed_by`. `UNIQUE KEY uq_staff_date(staff_id, leave_date)`.

### `notifications`
| Column | Type | Description |
|---|---|---|
| `recipient_id` | INT nullable | Specific user (NULL = role-broadcast) |
| `target_role` | VARCHAR(30) | 'global' / 'admin' / 'frontdesk' |
| `type` | VARCHAR(50) | user_management/booking/finance/dispatch/system |
| `message` | TEXT | Human-readable notification body |
| `action_url` | VARCHAR(500) | Deep-link URL |
| `is_read` | TINYINT(1) | Read state |

### `audit_log`
| Column | Type | Description |
|---|---|---|
| `user_id` | INT UNSIGNED | Who performed action |
| `action` | VARCHAR(60) | e.g. payment_recorded, booking_archived |
| `entity` | VARCHAR(30) | booking/payment/client/user/setting |
| `entity_id` | INT UNSIGNED | ID of affected record |
| `old_value` | LONGTEXT JSON | State before change |
| `new_value` | LONGTEXT JSON | State after change |
| `ip_address` | VARCHAR(45) | IPv4/IPv6 of actor |

### `email_queue`
Async email pipeline. `status` ENUM: pending/sending/sent/failed. `attempts` max 3. Processed by cron.

### `settings`
Key-value store for all runtime configuration. `type` ENUM: string/int/float/bool/json. 50+ settings including financial ratios, SMTP credentials, operating hours.

### `password_resets`
OTP storage: `email`, `otp` (6-digit INT), `expires_at`. Cleared after successful verification.

### `login_attempts`
Brute-force tracking: `ip_address`, `email`, `attempted_at`. Cleaned up by cron after 24h.

### `otp_attempts`
Separate rate-limit table for OTP verification: `email`, `ip_address`, `attempted_at`.

### `recipe_ingredients`
Ingredients per dish: `dish_id`, `ingredient_name`, `base_quantity`, `unit`, `unit_price`, `supplier`. 513 ingredient records.

### `archived_bookings`
Immutable snapshot of completed bookings: `original_id`, client info, financial summary, `archived_by`.

### `migrations`
Tracks applied SQL migrations with filename, batch, and SHA-256 checksum.

### `taste_testing` / `taste_test_appointments` / `taste_test_feedback`
Schema-ready tables for the taste-testing pipeline (not yet wired to frontend).

---

## Entity Relationship Diagram (Mermaid ERD)

```mermaid
erDiagram
    users {
        int id PK
        string name
        string email
        string role
        string job_class
        int is_active
    }
    clients {
        int id PK
        string name
        string email
        string phone
    }
    packages {
        int id PK
        string set_name
        int pax_count
        decimal price
        int max_main_dishes
        int max_desserts
    }
    bookings {
        int id PK
        int client_id FK
        int package_id FK
        int created_by FK
        date event_date
        int pax_count
        decimal total_cost
        decimal amount_paid
        string payment_status
        string booking_status
        string invoice_token
        string paymongo_checkout_id
        int is_archived
    }
    dishes {
        int id PK
        string name
        string category
        decimal custom_fee
        int is_active
    }
    booking_dishes {
        int id PK
        int booking_id FK
        int dish_id FK
    }
    booking_custom_items {
        int id PK
        int booking_id FK
        string name
        decimal price
    }
    payments {
        int id PK
        int booking_id FK
        int recorded_by FK
        decimal amount
        string payment_method
        string gateway_reference_id
        date payment_date
    }
    equipment {
        int id PK
        string name
        int current_stock
        int total_stock
        decimal replacement_cost
    }
    booking_inventory {
        int id PK
        int booking_id FK
        int equipment_id FK
        int quantity_out
        int quantity_in
        int dispatched_by FK
        int returned_by FK
    }
    booking_breakages {
        int id PK
        int booking_id FK
        int equipment_id FK
        int quantity
        decimal unit_price
        decimal total_cost
        string charge_to
        int logged_by FK
    }
    job_orders {
        int id PK
        int booking_id FK
        int staff_id FK
        string role_required
        string status
        string job_class
    }
    booking_cancellations {
        int id PK
        int booking_id FK
        int requested_by FK
        decimal deposit_forfeit
        decimal refundable_amount
        string refund_status
    }
    leave_requests {
        int id PK
        int staff_id FK
        date leave_date
        string status
        int reviewed_by FK
    }
    notifications {
        int id PK
        int recipient_id FK
        string target_role
        string type
        string message
        int is_read
    }
    audit_log {
        bigint id PK
        int user_id FK
        string action
        string entity
        int entity_id
        json old_value
        json new_value
    }
    email_queue {
        int id PK
        string recipient_email
        string status
        int attempts
    }
    settings {
        int id PK
        string key
        string value
        string type
    }
    archived_bookings {
        int id PK
        int original_id
        int archived_by FK
    }
    recipe_ingredients {
        int id PK
        int dish_id FK
        string ingredient_name
        decimal base_quantity
    }

    clients ||--o{ bookings : "has"
    packages ||--o{ bookings : "applied to"
    users ||--o{ bookings : "created_by"
    bookings ||--o{ booking_dishes : "includes"
    dishes ||--o{ booking_dishes : "selected in"
    bookings ||--o{ booking_custom_items : "has"
    bookings ||--o{ payments : "receives"
    users ||--o{ payments : "recorded_by"
    bookings ||--o{ booking_inventory : "uses"
    equipment ||--o{ booking_inventory : "dispatched in"
    users ||--o{ booking_inventory : "dispatched_by"
    bookings ||--o{ booking_breakages : "incurs"
    equipment ||--o{ booking_breakages : "broken in"
    bookings ||--o{ job_orders : "requires"
    users ||--o{ job_orders : "assigned to"
    bookings ||--o{ booking_cancellations : "cancelled via"
    users ||--o{ leave_requests : "requests"
    users ||--o{ notifications : "receives"
    users ||--o{ audit_log : "generates"
    dishes ||--o{ recipe_ingredients : "composed of"
    bookings ||--o{ archived_bookings : "archived as"
```

---

# 4. Design System & UI/UX

## Design Framework
The application uses a **custom CSS design system** built on top of Bootstrap 5, with a **dark glassmorphism aesthetic** inspired by Apple's Human Interface Guidelines. Key characteristics:

- **Color Palette:** System green (`#30D158`) as primary accent — the same green used in iOS. Neutral grays derived from `rgba(60,60,67,x)` opacity scales. Dark mode backgrounds using deep charcoal.
- **Typography:** System font stack (`-apple-system, BlinkMacSystemFont, Segoe UI, Roboto`) for a native OS feel. Font weights span 400–800, used deliberately for hierarchy.
- **Glassmorphism:** Cards use `backdrop-filter: blur()`, semi-transparent backgrounds, and `0.5px` hairline borders (`rgba(60,60,67,0.08)`).
- **Micro-animations:** Sidebar link transitions, table row hover lifts, button press feedback, toast slide-in/out, modal fade effects.
- **Icon Library:** Font Awesome 6 used throughout for semantic icons.
- **Component Library:** Custom card system (`card`, `card-header`, `card-body`), `stat-icon` badges (color-coded: teal, sage, amber, coral), `badge-status` pills (paid/partial/unpaid/confirmed/cancelled).

## Email Templates
Transactional emails use **inline CSS Apple-style cards**: green gradient headers for bookings, orange gradient for balance reminders, matching the in-app design system.

## Mobile Responsiveness
- **Bootstrap 5 Grid:** `col-lg-X` breakpoints used throughout for two-column to single-column collapsing.
- **Sidebar:** Collapsible hamburger menu on mobile, fixed rail on desktop.
- **Tables:** Horizontal scroll wrappers (`overflow-x: auto`) with sticky first columns where needed.
- **Modals:** Full-screen on mobile (`modal-fullscreen-sm-down`).
- **Staff Dashboard:** Specifically optimized for mobile — large tap targets, card-based layout instead of table, inventory dispatch with mobile-friendly quantity inputs.
- **Pagination:** Custom CSS pagination component in `assets/css/pagination.css` with responsive button sizing.

---

# 5. QA Audit: Edge Cases & Known Issues

## Edge Cases Successfully Handled

| Scenario | How It's Handled |
|---|---|
| **Same-date double booking** | `UNIQUE KEY idx_unique_event_date(event_date)` + `SELECT … FOR UPDATE` inside transaction. Two concurrent requests cannot both succeed. |
| **Duplicate PayMongo webhook delivery** | `UNIQUE KEY uq_gateway_ref(gateway_reference_id)` on `payments` table. Second webhook for same `pay_xxxx` ID silently fails insert — no duplicate payment. |
| **Inventory over-dispatch** | `SELECT current_stock … FOR UPDATE` before deducting. Throws exception if `current_stock < quantity`. Transaction rolled back. |
| **Breakage re-recording** | `booking_breakages` uses `ON DUPLICATE KEY UPDATE`. Multiple return submissions for same equipment update in place — never create duplicate rows. |
| **Return quantity > dispatched** | `PUT` endpoint throws: `"Returned quantity cannot exceed dispatched quantity"` and rolls back. |
| **Staff double-booking** | Accept endpoint checks `job_orders` for accepted status on same `event_date`. Rejects with `409 Conflict` and conflict details. |
| **Staff on leave + accepting** | Accept endpoint queries `leave_requests` before allowing accept. Rejects with clear message. |
| **Rush booking payment bypass** | System checks `diffHours < rush_threshold_hours` before accepting downpayment — forces 100% for short-notice bookings. |
| **Session expired mid-booking** | `$creatorId <= 0` check with descriptive message: "Your session has expired. Open a new tab." |
| **PayMongo mock key bypass** | `paymongo_checkout.php` detects `sk_test_your_secret_key_here` and returns a simulated response — dev testing without real API key. |
| **PDO transaction failures** | All multi-step operations wrapped in `try/catch(Throwable)` with `rollBack()`. Error message surfaced to caller. |
| **Head Cook duplication** | API enforces max 1 `head_cook` per booking with explicit `409` response. |
| **Booking 1-year max** | `$eventDate > date('+1 year')` check in booking creation. |
| **Cancelled booking payment** | `paymongo_checkout.php` guard: `if ($booking_status === 'cancelled') → 409`. Cannot pay for cancelled booking. |
| **Archive unarchived on breakage** | If returning inventory reveals breakage on an archived booking, system auto-unarchives it for financial correction. |
| **OTP brute-force** | `otp_attempts` table tracks failed attempts. `checkOtpRateLimit()` blocks further attempts. |
| **Login brute-force** | `login_attempts` tracks by IP. Lockout duration configurable (`lockout_duration_minutes`). |
| **Cron web access** | `cron_worker.php` checks `php_sapi_name() !== 'cli'` and returns `403 CLI only.` |

## Known Vulnerabilities & Bugs

> **Honest assessment for defense preparation.**

| Issue | Severity | Details |
|---|---|---|
| **SMTP password in `settings` table** | 🔴 HIGH | `smtp_pass` stored in plaintext in the `settings` table (DB). The audit log shows it was even logged in `old_value`/`new_value` JSON on update. If DB is compromised, Gmail App Password is exposed. **Fix:** Encrypt at rest or move to `.env` only. |
| **`audit_log` initiated_by null bug** | 🟡 MEDIUM | Recent conversation (ID: `aee33e6f`) identified that `initiated_log` field records NULL in some audit entries. The `auditLog()` function reads `$_SESSION['user_id']` but the session may not be active for cron-triggered operations. **Fix:** Pass `$userId` explicitly to all audit calls. |
| **`booking_inventory` collation mismatch** | 🟡 MEDIUM | `booking_inventory` uses `utf8mb4_general_ci` while most tables use `utf8mb4_unicode_ci`. This can cause JOIN performance issues and unexpected collation errors on string comparisons. |
| **`max_admins` setting orphaned** | 🟡 MEDIUM | Setting exists in DB (`max_admins = 5`) but the single-active-admin refactor removed the enforcement logic. The setting is now misleading — it does nothing. **Fix:** Remove the setting key from DB or add a deprecation note. |
| **No rate limit on manual payment recording** | 🟡 MEDIUM | `payments.php` POST has no rate limiting. A fast loop (as demonstrated by audit_log entries 70–89 showing 20+ ₱1 payments in rapid succession) can flood the payments table. **Fix:** Add a minimum amount validation (e.g., ≥ ₱1.00) and optionally a per-booking rate limit. |
| **`booking_staff` table is orphaned** | 🟢 LOW | `booking_staff` table exists in schema with 0 rows. `job_orders` has replaced it. The table wastes schema space and causes confusion. **Fix:** Drop the table, remove FK references. |
| **Taste-testing tables have no UI** | 🟢 LOW | 3 taste-testing tables exist with full FK relationships but zero frontend. Dead schema. **Fix:** Either implement or document as "v2 feature." |
| **`debug_mode` does not fully disable all paths** | 🟢 LOW | `debug_mode` setting exists and is checked in some places, but it's not consistently enforced across all API endpoints. Some error messages may still leak stack traces. |
| **No server-side file upload validation** | 🟢 LOW | No file upload feature exists currently, but if added, `max_file_upload_mb` setting is defined without corresponding server-side enforcement. |
| **PayMongo `amount_pesos` logged incorrectly** | 🟢 LOW | `audit_log` entry for `paymongo_checkout_created` logs `amount_pesos: balanceDue` but the actual amount charged may be the `override_amount`. The log value may not match the real checkout amount in override scenarios. |


# Sections 6–9: Security, API, Deployment & Defense Strategy

---

# 6. Security Architecture

## Authentication & Session Management

### Login Flow
1. `POST /src/api/auth.php` — receives `email` + `password`.
2. Checks `login_attempts` for lockout (configurable `max_login_attempts`, `lockout_duration_minutes`).
3. Fetches user row — verifies `is_active = 1` AND `debug_mode = 0` (debug mode blocks all logins).
4. `password_verify()` against bcrypt hash — **no plaintext passwords stored**.
5. On success: session regenerated (`session_regenerate_id(true)`), CSRF token generated (`bin2hex(random_bytes(32))`), `login_attempts` cleared.
6. On failure: `login_attempts` incremented. After threshold, `locked_until` set.

### Session Control
- **Session Timeout:** Configurable `session_timeout_minutes` (default: 480 minutes). Enforced on every page load via `requireRole()`.
- **Session Fixation Prevention:** `session_regenerate_id(true)` called on login.
- **CSRF Tokens:** Generated per-session, required in `X-CSRF-Token` header for all POST/PUT/DELETE API calls.
- **Role Enforcement:** `requireRole($role)` checks both `$_SESSION['role']` and session expiry on every protected page. `requireApiRole($roles)` does the same for API endpoints and returns `401` JSON on failure.

### Password Recovery (3-Step OTP)
```
Step 1: POST /src/api/forgot_password.php  { email }
        → generates 6-digit OTP, inserts into password_resets with expires_at
        → queues email via email_queue table

Step 2: POST /src/api/verify_otp.php  { email, otp }
        → queries: SELECT id FROM password_resets WHERE email=? AND otp=? AND expires_at > NOW()
        → rate-limited via otp_attempts table (checkOtpRateLimit / recordOtpAttempt)
        → valid: clears otp_attempts

Step 3: POST /src/api/reset_password.php  { email, otp, new_password }
        → re-verifies OTP (prevents step-skip)
        → password_hash($newPassword, PASSWORD_DEFAULT)
        → updates users.password
        → deletes password_resets row
```

### Role-Based Access Control (RBAC)
All API endpoints call `requireApiRole(['admin', 'frontdesk'])` (or similar) as the first line of business logic. The middleware:
1. Checks session exists + not expired.
2. Verifies `$_SESSION['role']` is in the allowed array.
3. Returns `401 Unauthorized` (JSON) if either check fails.
4. Returns the user record for use in the handler.

```php
// Example: inventory_dispatch.php
requireApiRole(['admin', 'frontdesk', 'staff']);
requireCsrf();
```

## Transport & Data Security

| Layer | Implementation |
|---|---|
| **HTTPS** | Required on production. `.env.example` mandates `BASE_URL=https://yourdomain.com`. PayMongo only sends webhooks to HTTPS endpoints. |
| **SQL Injection Prevention** | 100% parameterized queries via PDO. No raw string interpolation of user input into SQL. |
| **XSS Prevention** | `htmlspecialchars()` applied to user-provided strings before output in HTML contexts. Settings values and audit log entries escaped on render. |
| **CSRF Protection** | `X-CSRF-Token` header required on all state-changing requests. `hash_equals()` for timing-safe comparison. |
| **Webhook Signature** | PayMongo webhooks validated with `hash_hmac('sha256', $rawBody, $webhookSecret)` before processing. |
| **Sensitive `.env`** | `.htaccess` rule `Deny from all` in `config/` folder. `.env` never committed — `.env.example` shipped instead. |
| **Password Storage** | PHP `password_hash()` with `PASSWORD_DEFAULT` (bcrypt, cost factor 12+). Never MD5/SHA1. |
| **OTP Expiry** | OTPs have `expires_at` timestamp. `AND expires_at > NOW()` in every query. |
| **Database Backup** | `backup.php` uses `mysqldump` via `shell_exec()` — access restricted to `admin` role + CSRF. |

## .htaccess Security Rules
```apache
# /src/api/.htaccess
Options -Indexes          # Disable directory listing
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "SAMEORIGIN"
Header set X-XSS-Protection "1; mode=block"
```

---

# 7. API Endpoint Reference

## Endpoint Inventory (28 API files + 1 webhook)

| File | Methods | Auth | Description |
|---|---|---|---|
| `auth.php` | POST, DELETE | — | Login / Logout |
| `bookings.php` | GET, POST, PUT, DELETE | admin,frontdesk | Full booking CRUD + link_staff action |
| `clients.php` | GET, POST, PUT, DELETE | admin,frontdesk | Client management |
| `packages.php` | GET, POST, PUT, DELETE | admin,frontdesk | Package tier management |
| `dishes.php` (implied) | GET | all roles | Dish catalog |
| `recipes.php` | GET, POST, PUT, DELETE | admin | Recipe + ingredient management |
| `payments.php` | GET, POST, PUT, DELETE | admin,frontdesk | Payment recording + refunds |
| `paymongo_checkout.php` | POST | admin,frontdesk OR invoice_token | Create PayMongo checkout session |
| `payment_status.php` | GET | — (public) | Poll payment confirmation |
| `webhooks/paymongo.php` | POST | HMAC sig | PayMongo event handler |
| `dispatching.php` | GET, POST, PUT, DELETE | admin,frontdesk,staff | Job order management |
| `inventory.php` | GET, POST, PUT, DELETE | admin,frontdesk | Equipment catalog management |
| `inventory_dispatch.php` | GET, POST, PUT | admin,frontdesk,staff | Dispatch + return equipment |
| `breakages.php` | GET, POST, PUT, DELETE | admin,frontdesk,staff | Breakage log management |
| `staff.php` | GET, POST, PUT, DELETE | admin | User/staff account management |
| `settings.php` | GET, PUT | admin | Read/update settings |
| `audit_logs.php` | GET | admin | Paginated audit log |
| `notifications.php` | GET, POST, PUT | all roles | In-app notifications |
| `availability.php` | GET | admin,frontdesk | Check event date availability |
| `cancellations.php` | GET, POST, PUT, DELETE | admin,frontdesk | Cancellation workflow |
| `event_reports.php` | GET, POST, PUT | admin,frontdesk,staff | Post-event reports |
| `leave.php` | GET, POST, PUT, DELETE | all roles | Staff leave requests |
| `analytics.php` | GET | admin | Dashboard analytics + charts |
| `archive.php` | GET, POST, PUT | admin | Archive management |
| `forgot_password.php` | POST | — | OTP generation |
| `verify_otp.php` | POST | — | OTP validation |
| `reset_password.php` | POST | — | Password reset |
| `send_invoice.php` | POST | admin,frontdesk | Queue invoice email to client |
| `backup.php` | GET | admin | Trigger mysqldump download |

## Standard JSON Response Envelope
Every endpoint returns the same envelope:
```json
{
  "success": true,
  "message": "Human-readable message",
  "data": { ... }
}
```
HTTP status codes used correctly: `200 OK`, `201 Created`, `400 Bad Request`, `401 Unauthorized`, `403 Forbidden`, `404 Not Found`, `409 Conflict`, `422 Unprocessable Entity`, `500 Internal Server Error`, `502 Bad Gateway`.

## Example: Booking Creation Request (POST `/src/api/bookings.php`)
```json
{
  "client_id": 12,
  "event_date": "2026-06-15",
  "event_time": "10:00",
  "event_location": "Tagaytay Reception Hall",
  "event_type": "Wedding",
  "pax_count": 120,
  "package_id": 3,
  "selected_dishes": [14, 22, 31, 45, 67],
  "transport_fee": 500.00,
  "downpayment": 15000.00,
  "downpayment_method": "gcash",
  "downpayment_ref": "GC-20260615-001",
  "dietary_notes": "2 guests are lactose intolerant",
  "notes": "VIP table of 10 at the front"
}
```

## Example: PayMongo Webhook Payload (Verified via HMAC-SHA256)
```json
{
  "data": {
    "attributes": {
      "type": "payment.paid",
      "data": {
        "attributes": {
          "amount": 1500000,
          "status": "paid",
          "metadata": { "booking_id": 89 }
        },
        "id": "pay_xxxxxxxxxxxxxxxxxx"
      }
    }
  }
}
```

---

# 8. Deployment Checklist

## Pre-Launch Requirements

### Environment Configuration (`.env`)
```ini
# Application
APP_NAME=Yazzies Catering OMS
APP_ENV=production
BASE_URL=https://yourdomain.com/

# Database
DB_HOST=localhost
DB_NAME=yazzie_catering
DB_USER=db_user_here
DB_PASS=strong_password_here

# PayMongo
PAYMONGO_SECRET_KEY=sk_live_xxxxxxxxxxxx
PAYMONGO_PUBLIC_KEY=pk_live_xxxxxxxxxxxx
PAYMONGO_WEBHOOK_SECRET_KEY=whsec_xxxxxxxxxxxx

# SMTP (e.g. Gmail App Password)
MAIL_FROM=noreply@yourdomain.com
MAIL_FROM_NAME=Yazzies Catering
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_gmail@gmail.com
SMTP_PASS=your_app_password_here
SMTP_SECURE=tls
```

### Server Requirements
| Requirement | Minimum | Recommended |
|---|---|---|
| PHP | 7.4 | 8.1+ |
| MySQL / MariaDB | 5.7 | 8.0 |
| Extensions | PDO, PDO_MySQL, mbstring, curl, openssl | + zip, opcache |
| RAM | 256MB | 512MB |
| Storage | 1GB | 5GB |
| SSL | Required | Let's Encrypt (free) |

### Cron Job Setup (Production cPanel / SSH)
```bash
# Run every 15 minutes
*/15 * * * * /usr/bin/php /home/username/public_html/cron_worker.php >> /home/username/logs/cron.log 2>&1
```

### PayMongo Webhook Configuration
1. Log into PayMongo Dashboard → Developers → Webhooks.
2. Add URL: `https://yourdomain.com/src/api/webhooks/paymongo.php`
3. Select events: `payment.paid`, `checkout_session.payment.paid`.
4. Copy the Webhook Secret → paste into `.env` as `PAYMONGO_WEBHOOK_SECRET_KEY`.

### Post-Deployment Verification
- [ ] `https://yourdomain.com/` loads login page correctly.
- [ ] Admin can log in. CSRF token present in `<meta name="csrf-token">`.
- [ ] Create test booking → payment recorded → audit log entry created.
- [ ] PayMongo mock checkout session returns URL (test key in `.env`).
- [ ] Staff can log in → see job board → accept job.
- [ ] Email queue → cron runs → test email received.
- [ ] `cron_worker.php` returns 403 when accessed from browser.
- [ ] `backup.php` downloads valid `.sql` file.
- [ ] OTP password reset flow completes end-to-end.

---

# 9. System Defense Strategy

## Anticipated Examiner Questions & Model Answers

### Q1: "Why did you choose PHP over a modern framework like Laravel or Node.js?"
**A:** The decision was deliberate and client-driven. PHP runs on any shared hosting (₱2,500/year) without a Node.js server or Docker. For a catering SME operating in a Philippine province, zero-configuration deployment is a business requirement, not a technical compromise. The codebase follows MVC-adjacent separation: `config/` (infrastructure), `includes/` (middleware), `src/api/` (controllers), `views/` (templates). The custom router in `index.php`/`.htaccess` handles routing. This demonstrates that good architecture is a design decision — not a framework dependency.

### Q2: "Why is the database not normalized to 3NF? I see `client_name` repeated in `archived_bookings`."
**A:** `archived_bookings` is an **intentional denormalized snapshot**. When a booking is archived 6 months later, the client's name may have changed. Financial and legal records require immutable historical snapshots. This is the same design pattern used in invoice systems worldwide — the invoice stores the client's name at the time of issue, not a foreign key that points to a mutable record. This is a deliberate trade-off between normalization and audit fidelity.

### Q3: "Is the PayMongo integration production-ready?"
**A:** Yes. The integration implements all four pillars of production-grade payment processing: (1) **Idempotency** — `gateway_reference_id` UNIQUE constraint prevents duplicate charges from re-delivered webhooks. (2) **Signature Verification** — HMAC-SHA256 validation of every webhook payload. (3) **Balance Validation** — system re-reads balance inside a DB transaction to prevent stale reads. (4) **Guard Rails** — cancelled bookings and fully paid bookings are blocked from generating new checkout sessions with descriptive `409 Conflict` responses. The developer mock bypass (`sk_test_your_secret_key_here`) allows full UI testing without live API credentials.

### Q4: "What happens if two staff members book the same event date simultaneously?"
**A:** The booking creation API uses a `SELECT COUNT(*) FROM bookings WHERE event_date = :date FOR UPDATE` inside a PDO transaction. The `FOR UPDATE` clause acquires a row-level lock in InnoDB, serializing concurrent requests for the same date. The first transaction to acquire the lock succeeds; the second finds `count > 0` and returns a `409 Conflict` with `"This date was just booked by someone else."` The database also has a `UNIQUE KEY idx_unique_event_date(event_date)` as a final backstop.

### Q5: "How do you prevent a staff member from being assigned to two events on the same date?"
**A:** The job acceptance endpoint (`PUT /dispatching.php`) runs two queries before updating: (1) `SELECT FROM leave_requests WHERE staff_id=? AND leave_date=? AND status='approved'` — blocks acceptance if on approved leave. (2) `SELECT FROM job_orders jo JOIN bookings b … WHERE jo.staff_id=? AND jo.status='accepted' AND b.event_date=? AND jo.booking_id != thisBid` — blocks acceptance if already committed to another event on the same date. Both queries return `409 Conflict` with the conflicting record IDs for full traceability.

### Q6: "How does the system handle broken equipment charges?"
**A:** When staff record inventory returns via `PUT /inventory_dispatch.php`, the system computes `diff = quantity_out - quantity_in`. If `diff > 0`, a breakage record is inserted/updated in `booking_breakages` (idempotent via `ON DUPLICATE KEY UPDATE`). The `bookings.breakage_total` and `bookings.total_cost` are then **recalculated atomically** in the same transaction using a subquery SUM, and `payment_status` is re-evaluated (paid/partial/unpaid) based on the new total. All of this happens in one database transaction — either all succeeds or all rolls back.

### Q7: "What are the most critical security vulnerabilities?"
**A:** (Honest, pre-prepared answer shows academic integrity.) Three identified risks: (1) **SMTP password in DB** — `smtp_pass` is stored in the `settings` table. Mitigated by DB access controls, but ideally should be in `.env` only. (2) **Audit log session dependency** — cron-triggered operations can log NULL `user_id`. Fix: pass explicit system user ID. (3) **Debug mode** — if accidentally left `enabled`, all logins are blocked system-wide. Mitigated by a UI warning label in the Superadmin Console.

### Q8: "What is the Superadmin Console for?"
**A:** It is the system's infrastructure management panel, restricted to the `admin` role. It provides: (1) **Global Configuration** — live-editable settings (SMTP, security thresholds, pricing rules) organized into Security / Email / System tabs. Changes take effect immediately across all modules via the `settings` table cache. (2) **Database Backup** — one-click `mysqldump` download of a full SQL snapshot. (3) **System Activity Log** — paginated audit trail of all actions, auto-refreshes on setting change.

### Q9: "How would you scale this if Yazzies grew from 5 bookings/month to 500?"
**A:** Current stack handles 500 bookings/month easily. For scale: (1) **Read replicas** — offload `SELECT` queries from the `analytics.php` and financial reports to a MySQL read replica. (2) **Redis cache** — cache `settings` table (queried on every page load) and `dishes` catalog. (3) **Queue worker** — replace `email_queue` + cron polling with a proper queue (Beanstalkd, Redis Queue) for sub-second email delivery. (4) **CDN** — offload `assets/` (CSS, JS, images) to Cloudflare or AWS CloudFront. (5) **Object storage** — move database backups from server filesystem to S3. None of these require rewriting business logic — the API layer is already stateless and scalable.

### Q10: "What is the `invoice_token` for?"
**A:** Each booking is issued a cryptographically random 32-character hex token (`bin2hex(random_bytes(16))`) stored in `bookings.invoice_token`. This token enables **passwordless, client-facing invoice access**. The invoice URL is: `https://domain.com/templates/invoice.php?booking_id=89&token=abc123…`. The `paymongo_checkout.php` endpoint accepts this token as an alternative to a staff session, enabling the client to pay directly from the invoice link on their phone — without creating an account. The token is validated via constant-time comparison to prevent timing attacks.

---

## Defense Presentation Outline (30-min oral defense)

| Time | Topic | Key Points |
|---|---|---|
| 0:00–3:00 | **System Demo** | Show login → create booking → dispatch staff → record payment → view audit log |
| 3:00–8:00 | **Problem & Solution** | Present Section 1. Emphasize the "no localhost" argument for production deployment. |
| 8:00–15:00 | **Architecture Deep-Dive** | ERD walkthrough. `bookings` as central entity. Explain UNIQUE constraint + FOR UPDATE. |
| 15:00–20:00 | **Security Features** | CSRF, bcrypt, OTP flow, PayMongo HMAC, role middleware. Show a real audit log entry. |
| 20:00–25:00 | **Q&A Defense** | Use the model answers above. Be honest about the smtp_pass issue — show you identified it. |
| 25:00–30:00 | **Roadmap** | Mention: v2.0 PDF invoices, SMS integration, Redis caching, taste-testing module UI completion. |

## Proof-of-Enterprise Talking Points (Lead with These)
1. **Idempotent payment processing** — industry-standard pattern. "PayMongo can send the same webhook 3 times. Our UNIQUE constraint prevents triple-charging the client."
2. **TOCTOU Race Condition fix** — "We solved the concurrent booking problem using database-level locking, not application-level checks."
3. **PDO transaction rollback** — "If inserting the dishes fails after inserting the booking, the booking is also rolled back. Partial data is impossible."
4. **Cron Worker as infrastructure** — "We designed background processing as a separate concern. The `cron_worker.php` is CLI-only — it cannot be triggered from the web."
5. **Role-based audit log** — "Every financial mutation has a paper trail. We can reconstruct the exact state of any booking at any point in time from the audit log."
6. **Master Key Transfer** — "Business succession is built into the system. The ownership of the admin account can be transferred formally, with a logged record."
ENDOFFILE

---

*End of Report. Sections: 6 (Security), 7 (API Reference), 8 (Deployment), 9 (Defense Strategy)*
*Generated from live codebase analysis — 28 API files, 24 DB tables, 513 recipe ingredients, 445+ audit log entries.*
