# Yazzies Catering OMS — Notification Module Audit & Implementation

**Date**: April 29, 2026  
**Project**: Notification System for Cross-Role Communication (In-System + Email)  
**Status**: 80% Complete — Core Infrastructure Exists

---

## PHASE 1: Database Audit & Setup ✅

### Notifications Table Exists
- **Location**: `database/yazzie_latest.sql` (Line 596)
- **Status**: FULLY IMPLEMENTED

#### Current Schema:
```sql
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` enum('job_assigned','job_declined','leave_approved','leave_rejected',
             'leave_reviewed','balance_reminder','event_reminder','general') NOT NULL DEFAULT 'general',
  `title` varchar(150) NOT NULL,
  `body` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `link_url` varchar(500) DEFAULT NULL COMMENT 'Optional URL for in-app navigation',
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_created` (`created_at`),
  KEY `fk_notif_booking` (`booking_id`),
  CONSTRAINT `fk_notif_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

**Fields Breakdown**:
- `id`: Auto-increment primary key
- `user_id`: Target recipient (FK → users.id)
- `type`: ENUM for categorization (job_assigned, balance_reminder, etc.)
- `title`: Short notification heading
- `body`: Full notification message (HTML-safe, escaped in frontend)
- `is_read`: Boolean flag (0 = unread, 1 = read)
- `booking_id`: Optional FK to related booking (for UI navigation)
- `created_at`: Timestamp (default NOW())
- `link_url`: Optional direct navigation link

**Indexes**:
- `idx_user_read`: Optimized for "get unread count by user" queries (Bell Badge)
- `idx_created`: For ordering by latest
- FK on booking_id with SET NULL cascade

---

## PHASE 2: API Endpoint ✅

### Notifications REST API
- **Location**: `src/api/notifications.php`
- **Status**: FULLY IMPLEMENTED

#### Endpoints:

##### GET `/api/notifications.php`
**Returns**: Full notification list (latest 30) for logged-in user
```json
{
  "success": true,
  "data": {
    "notifications": [
      {
        "id": 93,
        "user_id": 22,
        "type": "job_assigned",
        "title": "New Job Offer: waiter",
        "body": "Event on Apr 25 for kry. Please respond in your Job Board.",
        "is_read": 1,
        "booking_id": null,
        "created_at": "2026-04-19 04:25:17",
        "link_url": null,
        "event_date": null,
        "event_location": null
      }
    ]
  }
}
```

##### GET `/api/notifications.php?unread=1`
**Returns**: Unread count only (for Bell Badge)
```json
{
  "success": true,
  "data": {
    "count": 5
  }
}
```

##### PUT `/api/notifications.php`
**Payload**: `{ "id": 93 }` or `{ "mark_all": true }`
- Marks single or all notifications as read
- **Response**: `{ "success": true, "message": "Marked as read." }`

**Auth**: Requires session + CSRF token

---

## PHASE 3: Email Configuration ✅

### PHPMailer Setup
- **Location**: `includes/mailer.php`
- **Status**: FULLY IMPLEMENTED

#### Core Functions:
1. **`sendMailImmediate()`** (Line 42)
   - Sends emails immediately via SMTP
   - No queuing
   - Supports attachments

2. **`sendStaffAssignmentEmail()`** (Line 498)
   - Triggered when staff is dispatched
   - Includes event date, time, location, pax count, role
   - HTML template with event details card
   - **Called by**: `src/api/dispatching.php` (Line 383-393)

3. **`sendJobResponseEmailToAdmin()`** (Line 570)
   - Notifies admins when staff accepts/declines job
   - Includes staff name, event date, role, client name

4. **`sendPaymentReminderEmail()`** (Line 470)
   - Sends payment reminder to clients

5. **`renderEmailTemplate()`** (Line 110)
   - Consistent HTML wrapper for all emails
   - Apple-style design (system green theme)
   - Supports custom emoji, title, content, theme color

#### Configuration:
- **Credentials**: `config.php` (MAIL_USERNAME, MAIL_PASSWORD, MAIL_HOST, MAIL_PORT)
- **From**: MAIL_FROM & MAIL_FROM_NAME
- **Protocol**: SMTP with TLS/SSL auto-detection
- **Toggle**: MAIL_ENABLED constant

---

## PHASE 4: Staff Dispatch Notifications (Dual-Channel) ✅

### In-System Notifications
- **Location**: `src/api/dispatching.php` (Line 243-250)
- **Trigger**: POST `/api/dispatching.php` (Create job orders)
- **Status**: FULLY IMPLEMENTED

#### Code Flow:
```php
// When admin/frontdesk creates job orders for staff:
$notif = $pdo->prepare("
    INSERT INTO notifications (user_id, type, title, body)
    VALUES (:uid, 'job_assigned', :title, :body)
");

$notif->execute([
    ':uid'   => $staffId,
    ':title' => 'New Job Offer: ' . $role,
    ':body'  => "Event on " . date('M d', strtotime($booking['event_date'])) 
                . " for " . $booking['client_name'] . ". Please respond in your Job Board."
]);
```

### Email Notifications
- **Location**: `src/api/dispatching.php` (Line 363-393)
- **Trigger**: Same POST request
- **Status**: FULLY IMPLEMENTED

#### Code Flow:
```php
if ($count > 0 && MAIL_ENABLED) {
    require_once __DIR__ . '/../../includes/mailer.php';
    // Batch fetch all staff
    $staffUsers = $uStmt->fetchAll();
    foreach ($staffUsers as $staffUser) {
        if (!empty($staffUser['email'])) {
            sendStaffAssignmentEmail(
                ['name' => $staffUser['name'], 'email' => $staffUser['email']],
                [
                    'event_date'     => $booking['event_date'],
                    'event_time'     => $booking['event_time'] ?? '',
                    'event_location' => $booking['event_location'] ?? 'TBA',
                    'pax_count'      => $booking['pax_count'],
                    'staff_role'     => $role,
                ]
            );
        }
    }
}
```

### Staff Response Notifications
- **Location**: `src/api/dispatching.php` (Line 163-190)
- **Trigger**: PUT `/api/dispatching.php` (Staff accepts/declines)
- **Status**: FULLY IMPLEMENTED

#### Notifies:
- All admin + frontdesk users in-system
- All admin + frontdesk users via email
- Includes staff name, event date, status (accepted/declined)

---

## PHASE 5: 72-Hour Payment Alert (Passive Cron) ✅

### Balance Reminder Function
- **Location**: `cron_worker.php` (Line 211)
- **Trigger**: Runs every 5 minutes (cron_worker.php execution)
- **Status**: FULLY IMPLEMENTED

#### Query:
```sql
SELECT b.id, b.event_date, b.total_cost, b.amount_paid,
       c.name AS client_name, c.email AS client_email
FROM bookings b
JOIN clients c ON c.id = b.client_id
WHERE b.event_date = CURDATE() + INTERVAL 3 DAY
  AND b.booking_status IN ('confirmed', 'partial')
  AND (b.total_cost - b.amount_paid) > 0
```

#### Logic:
1. Find all bookings where:
   - Event date is **exactly 3 days from TODAY**
   - Booking status is confirmed or partial
   - Balance remaining > 0

2. For each booking:
   - Check if notification already exists for today (deduplication)
   - If NOT, insert in-system notification for all admin/frontdesk
   - Message: "URGENT: Booking #[ID] is in 3 days and is missing final payment."
   - Link to booking detail page for quick action

3. **Deduplication**: 
   - Checks if title contains "Pending Balance" AND created_at is TODAY
   - Prevents spam on every page refresh

#### Code:
```php
function sendBalanceReminders(PDO $pdo, callable $log): int
{
    $stmt = $pdo->prepare("
        SELECT b.id, b.event_date, b.total_cost, b.amount_paid,
               c.name AS client_name, c.email AS client_email
        FROM bookings b
        JOIN clients c ON c.id = b.client_id
        WHERE b.event_date = CURDATE() + INTERVAL 3 DAY
          AND b.booking_status IN ('confirmed', 'partial')
          AND (b.total_cost - b.amount_paid) > 0
    ");
    $stmt->execute();
    $bookings = $stmt->fetchAll();

    $checkNotified = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE booking_id = :bid AND title LIKE '%Pending Balance%' 
          AND DATE(created_at) = CURDATE()
    ");

    foreach ($bookings as $bk) {
        $checkNotified->execute([':bid' => $bk['id']]);
        if ((int)$checkNotified->fetchColumn() > 0) {
            continue; // Already notified today
        }

        $balance = $bk['total_cost'] - $bk['amount_paid'];
        
        $sysNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, body, booking_id, link_url)
            SELECT id, 'general', :title, :body, :bid, :link
            FROM users 
            WHERE role IN ('super_admin', 'admin', 'frontdesk') 
              AND is_active = 1
        ");
        $sysNotif->execute([
            ':title' => '💰 Pending Balance: ' . $bk['client_name'],
            ':body'  => "Booking #{$bk['id']} is in 3 days but still has a remaining balance of ₱" 
                       . number_format($balance, 2) . ". Please send a manual reminder if needed.",
            ':bid'   => $bk['id'],
            ':link'  => BASE_URL . '/views/admin/bookings.php?highlight=' . $bk['id']
        ]);
    }

    return count($bookings);
}
```

---

## PHASE 6: Frontend Bell Icon ❌ (TO BE IMPLEMENTED)

### Current Status
- Bell icon UI does NOT exist
- No navbar header with notification badge
- Need to add:
  1. HTML bell icon element
  2. JavaScript fetch logic
  3. Badge with unread count
  4. Dropdown with notification list
  5. Click-to-mark-read functionality

### Implementation Plan
**Location**: Modify `includes/header.php` to add a top bar with:
1. App logo/title (left)
2. Notification bell (right) with badge
3. User menu (right)

**JavaScript**: Create `assets/js/notifications.js` to:
1. Fetch `/api/notifications.php?unread=1` on page load
2. Update badge count in real-time
3. Show dropdown with latest notifications on bell click
4. Mark as read when clicked
5. Auto-refresh every 30 seconds (optional)

---

## Summary

| Phase | Component | Status | Location |
|-------|-----------|--------|----------|
| 1 | Database Table | ✅ Complete | `database/yazzie_latest.sql:596` |
| 2 | API Endpoint | ✅ Complete | `src/api/notifications.php` |
| 3 | Email Config | ✅ Complete | `includes/mailer.php` |
| 4 | Staff Dispatch Notifications | ✅ Complete | `src/api/dispatching.php:243-393` |
| 5 | 72-Hour Payment Alert | ✅ Complete | `cron_worker.php:211` |
| 6 | Frontend Bell Icon | ❌ **TODO** | `includes/header.php` + `assets/js/notifications.js` |

---

## Testing Checklist

- [ ] Staff dispatch creates in-system notification
- [ ] Staff dispatch email is sent to recipient
- [ ] Staff accept/decline updates notification status
- [ ] Admin email received when staff responds
- [ ] 3-day payment alert fires at correct time
- [ ] No duplicate alerts on same day
- [ ] Bell icon displays unread count
- [ ] Clicking notification marks as read
- [ ] Dropdown shows latest 30 notifications

---

## Next Steps

1. **Create Header Bar Component** (`includes/header.php`)
   - Add top navigation with bell icon
   - Add user menu dropdown

2. **Create Notifications JS Module** (`assets/js/notifications.js`)
   - Fetch unread count
   - Display dropdown
   - Handle mark-as-read
   - Auto-refresh logic

3. **Add CSS Styling** (`assets/css/main.css`)
   - Bell icon styling
   - Badge styling
   - Dropdown animation
   - Hover states

4. **Integration Testing**
   - Test all triggers
   - Test email delivery
   - Test UI responsiveness
   - Test performance with large notification counts

