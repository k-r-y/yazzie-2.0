# Yazzies Catering OMS — Project Guidelines

## Code Style
- Procedural PHP (no frameworks), snake_case for files/functions/variables
- Database: snake_case columns, SQL ENUM for statuses
- Security: Use `e()` for XSS escaping, `requireCsrf()` for state changes, prepared statements with PDO
- API responses: Always use `jsonResponse(bool $success, string $message, array $data, int $httpCode)`

## Architecture
- MVC-lite: index.php → role-based redirect → /src/api/ (JSON endpoints) → /views/{role}/ (server-rendered with fetch calls)
- Roles: super_admin (full), admin, frontdesk, staff
- Key tables: users, bookings, clients, payments, job_orders, audit_log
- 19 API endpoints in /src/api/ for CRUD operations

## Build and Test
- Install PHP deps: `composer install`
- Install frontend deps: `npm install` (Bootstrap 5 only)
- Database: Import `database/yazzie_latest.sql` or run setup.php
- Background tasks: Run `cron_worker.php` every 5 minutes for email/SMS queue and reminders
- No test suite configured

## Conventions
- Business constants in config/config.php (overridable via settings table): MIN_LEAD_TIME_DAYS=1, MIN_PAX=50, MAX_PAX=300, MIN_DP_PERCENT=0.30
- Pricing: Pax-based tiers (50-person increments), base_price = 5000 + (base_pax * 100)
- Email: PHPMailer via Gmail SMTP (use App Password)
- SMS: Semaphore API (disabled by default)
- Audit: All financial/booking changes logged with old/new JSON values
- Pitfalls: Ensure XAMPP MySQL running, check .env for DB_PASS/MAIL_PASSWORD, super_admin bypasses role checks