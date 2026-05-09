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
