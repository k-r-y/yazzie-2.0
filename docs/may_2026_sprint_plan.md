## May 2026 delivery sprint plan (ISO 25010-focused)

### Sprint 0 (1 week) — Hardening + prerequisites
- **Run migrations**: `database/migration_v7_settings.sql`, `database/migration_v8_archive_soft.sql`, `database/migration_v9_custom_items.sql`
- **Dynamic constraints**: validate pax/lead time/DP using `settings` (already wired in `config/config.php` + `src/api/bookings.php` + `includes/_booking_stepper.php`)
- **Ledger preservation**: confirm archive no longer deletes bookings/payments (`src/api/archive.php`)
- **Role tier**: enable Super Admin role assignment only from Super Admin (`src/api/staff.php`, `views/admin/users.php`)
- **Regression checklist**: booking create/update, dispatch offers, staff accept/decline, leave approval, archive, payments reporting

### Sprint 1 (1 week) — Cancellation & refunds (core business feature)
- **Schema**: apply `database/blueprint_refunds.sql` (or merge into a numbered migration file)
- **API**:
  - Add `src/api/cancellations.php` (request/approve/reject)
  - Add refund recording endpoint or extend `src/api/payments.php` to accept `payment_type='refund'`
- **Rules engine**:
  - Deposit forfeiture policy snapshot stored in `booking_cancellations.policy_json`
  - Compute refundable balance based on `SUM(payments.amount)` and policy
- **UI**:
  - Admin cancellation review panel
  - Financial report shows payments vs refunds separately

### Sprint 2 (1 week) — Taste testing appointments (lead conversion)
- **Schema**: apply `database/blueprint_taste_testing.sql`
- **API**:
  - `src/api/taste_testing.php`: CRUD, status changes, convert-to-booking
  - Conversion creates/links `clients` then creates `bookings` draft
- **UI**:
  - Frontdesk tasting calendar/list + conversion button

### Sprint 3 (1 week) — Staff portal completion + conflict correctness
- **Dispatching**:
  - Ensure all job offers are visible to staff (`GET ?my_jobs=1`)
  - Ensure accept/decline uses overlap + approved-leave guards (`PUT`)
- **Availability**:
  - Availability should treat only accepted jobs as booked (already updated in `src/api/staff.php`)
- **Mobile UX**:
  - Improve staff dashboard for small screens and offline-ish handling (retry, clear errors)

### Sprint 4 (1–2 weeks) — Advanced costing & logistics (“Actual Costing”)
- **Schema** (next iteration beyond current to-dos):
  - Add `ingredients` master + link `recipe_ingredients` to ingredient IDs
  - Add event purchase capture (ingredient buys) and roll up into `event_actual_costs`
- **UI**:
  - Planned grocery list vs actual purchases
  - Net profit per event (Revenue – Refunds – Actual Costs)

### Definition of Done (ISO 25010 aligned)
- **Functional suitability**: no orphan workflows (custom items supported, tastings convert cleanly).
- **Reliability**: archiving cannot delete ledger; transactions wrap multi-write operations.
- **Security**: role tier enforced, object-level checks on destructive actions.
- **Maintainability**: constraints live in `settings`, not hard-coded.
- **Performance**: add indexes for overlap checks (`leave_requests(staff_id, leave_date, status)`, `job_orders(staff_id, status)` already present/added in earlier migrations).

