# MASTER AUDIT: 100% Dynamic & Mobile-Responsive Guarantee

## 1. Hardcoded Business Logic (The "100% Dynamic" Sweep)

This sweep identifies "magic numbers," static strings, and fixed constraints that bypass the settings database table.

### Financials & Penalties
* **Cancellation Penalty (PHP):**
  * **File:** `src/api/cancellations.php`
  * **Line:** 71
  * **Issue:** Hardcoded 50% forfeiture fee logic.
  * **Fix:** ` $forfeitureFee = round($totalPaid * appSettingFloat('cancel_forfeiture_percent', 0.50), 2);`
* **Cancellation Penalty (JS):**
  * **File:** `views/admin/bookings.php`
  * **Line:** 729
  * **Issue:** Hardcoded 50% forfeiture calculation in the frontend DOM update.
  * **Fix:** Inject the penalty percentage globally via a meta tag or a localized JS variable `window.AppConfig.cancelPenalty` mapped from the database.
  * **Code snippet:** `const forfeit = Math.round(totalPaid * window.AppConfig.cancelPenalty * 100) / 100;`

### Time Rules
* **Staff Dispatch Duration Limit:**
  * **File:** `views/staff/event_report.php`
  * **Line:** 244
  * **Issue:** Hardcoded 4-hour standard duration for staff events (`const standardMin = 4 * 60; // 4 hours`).
  * **Fix:** Pull this from system settings to accommodate flexible shift rules.
  * **Code snippet:** `const standardMin = parseInt(window.AppConfig.standardShiftHours || 4) * 60;`

### Company Identity
* **Email Boilerplate:**
  * **File:** `src/api/send_invoice.php`
  * **Line:** 51
  * **Issue:** Hardcoded company name string bypassing the `business_name` setting.
  * **Fix:** ` "<p>Thank you for choosing " . htmlspecialchars(appSetting('business_name', 'Yazzies Catering')) . "!</p>"`
* **Grocery List Header:**
  * **File:** `templates/grocery_list.php`
  * **Line:** 119
  * **Issue:** Hardcoded logo text (`<div class="print-logo">Yazzies <span>Catering</span></div>`).
  * **Fix:** Replace with `<?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering')) ?>`
* **Grocery List Title:**
  * **File:** `templates/grocery_list.php`
  * **Line:** 192
  * **Issue:** Hardcoded system name string (`Yazzies Catering &bull; Market List`).
  * **Fix:** `<?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering')) ?> &bull; Market List...`

---

## 2. Responsive UI & Layout Audit (The Mobile Sweep)

This sweep ensures the system interfaces will not break, overlap, or cause horizontal scrolling on mobile devices (e.g., iPhone SE - 320px width).

### Table Overflows
The following files contain `<table class="data-table">` elements without a responsive wrapper, guaranteeing horizontal breakage on mobile screens:
* `views/admin/inventory.php`
* `views/staff/dashboard.php`
* `views/admin/clients.php`
* `views/admin/dishes.php`
* `views/admin/bookings.php`
* `views/admin/staff.php`
* `views/admin/packages.php`
* `views/admin/users.php`
* `views/frontdesk/bookings.php`
* `views/frontdesk/dispatching.php`
* `views/frontdesk/costing.php`
* `views/admin/archive.php`
* `views/frontdesk/dashboard.php`
* `views/admin/financial.php`
* `views/admin/dashboard.php`
  * **Fix required for all above:** Wrap the table in a responsive div container:
    ```html
    <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="data-table">...</table>
    </div>
    ```

### Rigid Modals & Fixed Widths
* **Admin Bookings Modal:**
  * **File:** `views/admin/bookings.php`
  * **Line:** 105
  * **Issue:** Inline grid style forcing a 350px left column (`style="display:grid; grid-template-columns: 350px 1fr;"`), causing elements to crush on mobile.
  * **Fix:** Extract to a CSS class with media queries.
    ```css
    /* In CSS */
    .modal-responsive-grid { display: grid; grid-template-columns: 1fr; gap: 20px; }
    @media (min-width: 768px) { .modal-responsive-grid { grid-template-columns: 350px 1fr; } }
    ```
    ```html
    <!-- In PHP -->
    <div class="modal-responsive-grid" style="min-height:600px;">
    ```
* **Archive Bookings Modal:**
  * **File:** `views/admin/archive.php`
  * **Line:** 59
  * **Issue:** Same as above (`grid-template-columns: 350px 1fr`). Apply the same fix.
* **Recipes Grid:**
  * **File:** `views/admin/recipes.php`
  * **Line:** 27
  * **Issue:** `style="grid-template-columns:350px 1fr;"` used on main layout grid.
* **Dynamic Costing Stepper Grid:**
  * **File:** `includes/_booking_stepper.php`
  * **Line:** 1872
  * **Issue:** JS injects hardcoded column widths (`grid-template-columns:1fr 100px 100px 40px`).
  * **Fix:** Use a responsive flexbox layout with `flex-wrap` or adjust grid logic per breakpoint.
* **Select Filters:**
  * **File:** `views/admin/staff.php`, `views/frontdesk/bookings.php`, `views/admin/bookings.php`
  * **Issue:** Inline styles (`style="width:160px;"`) for filter dropdowns.
  * **Fix:** Change to `min-width:120px; max-width:100%;` or use standard flex child styling to allow wrapping.

### Viewport Verification
* **Status:** **PASSED.**
* Verified that the core header partial (`includes/header.php`, line 13) and all standalone template files correctly declare: `<meta name="viewport" content="width=device-width, initial-scale=1.0">`.

---

## 3. Actionable Fix Checklist

### 100% Dynamic Sweep
- [ ] `src/api/cancellations.php`: Replace `0.5` forfeiture fee with dynamic `CANCEL_FORFEIT_PCT`.
- [ ] `views/admin/bookings.php`: Remove JS hardcoded `0.5` forfeiture modifier and pull from localized global configuration.
- [ ] `views/staff/event_report.php`: Eliminate hardcoded `4 * 60` logic for shift durations.
- [ ] `src/api/send_invoice.php`: Remove static "Yazzies Catering" string and pull from settings payload.
- [ ] `templates/grocery_list.php`: Eradicate static company name references on lines 119 and 192.

### Mobile Responsive Sweep
- [ ] Add `.table-responsive` global class to `assets/css/main.css` if it does not already exist.
- [ ] Wrap `views/admin/inventory.php` table in responsive container.
- [ ] Wrap `views/staff/dashboard.php` table in responsive container.
- [ ] Wrap `views/admin/clients.php` table in responsive container.
- [ ] Wrap `views/admin/dishes.php` table in responsive container.
- [ ] Wrap `views/admin/bookings.php` table in responsive container.
- [ ] Wrap `views/admin/staff.php` table in responsive container.
- [ ] Wrap `views/admin/packages.php` table in responsive container.
- [ ] Wrap `views/admin/users.php` table in responsive container.
- [ ] Wrap `views/frontdesk/bookings.php` table in responsive container.
- [ ] Wrap `views/frontdesk/dispatching.php` table in responsive container.
- [ ] Wrap `views/frontdesk/costing.php` table in responsive container.
- [ ] Wrap `views/admin/archive.php` table in responsive container.
- [ ] Wrap `views/frontdesk/dashboard.php` table in responsive container.
- [ ] Wrap `views/admin/financial.php` table in responsive container.
- [ ] Wrap `views/admin/dashboard.php` table in responsive container.
- [ ] Refactor inline grid layout (`350px 1fr`) in `views/admin/bookings.php` modal to use responsive media queries.
- [ ] Refactor inline grid layout (`350px 1fr`) in `views/admin/archive.php` modal to use responsive media queries.
- [ ] Refactor inline grid layout (`350px 1fr`) in `views/admin/recipes.php` to use responsive media queries.
- [ ] Correct inline filter widths (e.g., `width:160px;`) across admin lists to support fluid collapsing on small viewports.
- [ ] Refactor JS-injected rigid layout (`grid-template-columns:1fr 100px...`) in `includes/_booking_stepper.php` to prevent mobile horizontal scroll blowouts.
