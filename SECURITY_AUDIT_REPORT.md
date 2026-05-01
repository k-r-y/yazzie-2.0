# Yazzies Catering OMS — Comprehensive Security Audit Report
**Date:** May 1, 2026
**Target:** Yazzies Catering OMS (Vanilla PHP 8.x, PDO, Vanilla JS)
**Scope:** Deep static analysis of core API endpoints, configuration, business logic, and frontend interactions.

This document outlines the findings of a rigorous static code analysis aimed at verifying the strict implementation of Zero Trust security principles across the system.

---

## 1. Injection & Data Layer (SQLi & Mass Assignment)
**Status:** Highly Secure
**Findings:**
* **SQL Injection (SQLi):** The system uniformly utilizes strict PDO prepared statements for all database interactions. String concatenation into SQL queries has been successfully eliminated.
  * In `config/config.php`, `PDO::ATTR_EMULATE_PREPARES => false` is enforced, ensuring true prepared statements at the database level and mitigating edge-case SQL injection risks.
  * Search queries (e.g., in `clients.php`) securely bind `LIKE` parameters (`$like = "%$search%"; $stmt->bindValue(':s1', $like);`).
* **Mass Assignment:** The data layer avoids blind mapping of `$_POST` or JSON payloads directly to database columns. Inserts and updates explicitly map JSON data to specific parameterized queries. 
  * `clients.php` correctly utilizes `COALESCE(:name, name)` during `UPDATE` operations to prevent unintentional overwriting of fields with `NULL` values when partial data is submitted.

## 2. Access Control & Authorization (IDOR & Privilege Escalation)
**Status:** Strong (Minor Optimization Recommended)
**Findings:**
* **Role-Based Access Control (RBAC):** Zero Trust principles are properly established at the top of every API endpoint via `requireApiRole()` and `requireRole()`.
* **Privilege Escalation Mitigation:** Administrative actions (e.g., `DELETE` operations, modifying global settings in `settings.php`, executing `backup.php`) are strictly shielded by `requireApiRole(['admin', 'super_admin'])`. 
* **Insecure Direct Object Reference (IDOR):** 
  * In `dispatching.php`, staff can only accept/decline their own job orders (`WHERE jo.id = :id AND jo.staff_id = :uid`).
  * In `event_reports.php`, staff are restricted to submitting reports only for bookings they are explicitly assigned to (`INNER JOIN job_orders jo ON jo.booking_id = b.id AND jo.staff_id = :uid`).
  * In `leave.php`, staff can only view and cancel their own pending leave requests.

## 3. Frontend & Session Shields (XSS & CSRF)
**Status:** Secure
**Findings:**
* **Cross-Site Scripting (XSS):** 
  * Data binding in the frontend (`Vanilla JS`) primarily utilizes `textContent` (e.g., `document.getElementById('view_event_location').textContent = b.event_location;`), which intrinsically prevents DOM-based XSS.
  * Where `innerHTML` is necessary (e.g., table generation in `bookings.php`), the `esc()` sanitizer function is deployed to neutralize malicious input before rendering.
  * Global variables printed from PHP to JS utilize `ejs()` to prevent literal injection.
* **Cross-Site Request Forgery (CSRF):** 
  * All state-changing API endpoints invoke `requireCsrf()` immediately after role validation, ensuring request authenticity via session-bound tokens.
* **Session Management:** Auth cookies and sessions are bound tightly. Accounts marked as `is_active = 0` are forcibly rejected during login (`auth.php`).

## 4. Business Logic Defenses (Race Conditions & Financial Integrity)
**Status:** Excellent
**Findings:**
* **Race Conditions (Time-of-Check to Time-of-Use):**
  * The system actively utilizes row-level database locking (`SELECT ... FOR UPDATE`) during critical financial and inventory operations.
  * `inventory_dispatch.php` explicitly locks equipment rows before deducting stock (`SELECT name, current_stock FROM equipment WHERE id = :eid FOR UPDATE`).
  * `cancellations.php` locks the booking and cancellation records before calculating final refund mathematics, preventing double-cancellation or overlapping payment processing.
* **Financial Integrity:** 
  * Refund and forfeiture calculations are strictly handled server-side (`CANCEL_FORFEIT_PCT`).
  * Bookings enforce automated demotion/promotion of payment status based on calculated sum totals (`amount_paid` vs `total_cost`).
* **Operational Constraints:** 
  * Staff scheduling dynamically blocks double-booking by querying approved leave (`leave_requests`) and existing accepted job orders (`job_orders`).

## 5. Secrets Management & Configuration
**Status:** Secure
**Findings:**
* **Credentials:** No sensitive keys or database passwords are hardcoded within the application source code. All secrets are effectively bridged via `loadEnv()` into `$_ENV` from the `.env` file (e.g., `DB_PASS`, `JWT_SECRET`, `MAIL_PASSWORD`).
* **Environment Separation:** The `.env` structure is clean and separates local configurations from potential production credentials.
* **Debug Mode Handling:** `DEBUG_MODE` enforces an application-level lockout for all roles except `super_admin`, effectively shielding internal error traces from standard users and staff during maintenance.

---

## 6. Prioritized Patch Checklist
While the system's security architecture is remarkably solid, the following minor hardening actions should be performed prior to the May 2026 launch to ensure total defense-in-depth:

- [ ] **Rate Limiting Hardening:** Verify that `checkLoginRateLimit($pdo)` in `auth.php` explicitly tracks IP address in addition to email, preventing distributed brute-force enumeration on administrative accounts.
- [ ] **HTTPS Enforcements:** Ensure that the final production server environment (Nginx/Apache) enforces `Strict-Transport-Security (HSTS)` and forces HTTP to HTTPS redirects, securing the session cookies in transit.
- [ ] **Security Headers:** Add a middleware or `header()` inclusion to all API responses asserting `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY` to mitigate MIME-sniffing and clickjacking respectively.
- [ ] **CSRF Token Rotation:** Ensure the CSRF token is regenerated upon successful login and logout to mitigate session fixation variants.
- [ ] **Disable Directory Listing:** Ensure `Options -Indexes` is set within the `.htaccess` or server configuration for the `/src/api` and `/includes` directories to prevent file enumeration.
