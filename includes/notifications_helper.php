<?php
/**
 * Yazzies Catering OMS — Notification Helper v2
 * ================================================
 * Centralised helper for the deep-linking, role-based notification system.
 *
 * Public API
 * ----------
 * dispatchNotification(PDO $pdo, array $data): int|false
 *     Universal trigger that safely inserts one notification row.
 *
 * checkUpcomingUnpaidBookings(PDO $pdo): int
 *     Passive cron: creates finance alerts for admin/frontdesk.
 *
 * SCHEMA (notifications v2)
 * -------------------------
 * recipient_id  INT  NULL  — specific user (Staff direct message)
 * target_role   VAR  NULL  — role broadcast slug (superadmin|admin|frontdesk|global)
 * type          VAR  NOT NULL — domain: user_management|booking|finance|dispatch|system|general
 * message       TEXT NOT NULL — the human-readable body
 * action_url    VAR  NULL  — relative deep-link, e.g. /views/admin/bookings.php?id=5
 * is_read       TINY NOT NULL DEFAULT 0
 * created_at    TS   NOT NULL DEFAULT CURRENT_TIMESTAMP
 */

// ═══════════════════════════════════════════════════════════════════════════════
// § 1  UNIVERSAL TRIGGER — dispatchNotification()
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Insert a single notification into the database.
 *
 * Routing rules (enforced here, not just by convention):
 *   - Staff messages  → set recipient_id; leave target_role NULL.
 *   - Role broadcasts → set target_role; leave recipient_id NULL.
 *   - Both NULL       → rejected (would be an orphan notification).
 *
 * @param PDO   $pdo
 * @param array $data {
 *   @type int|null    $recipient_id  Specific user ID (staff direct dispatch). Nullable.
 *   @type string|null $target_role   Role slug for broadcast. Nullable.
 *   @type string      $type          Domain classifier. Required.
 *   @type string      $message       The notification body text. Required.
 *   @type string|null $action_url    Relative deep-link URL. Nullable.
 * }
 * @return int|false  The new notification ID on success, false on validation failure.
 */
function dispatchNotification(PDO $pdo, array $data): int|false
{
    // ── Validation ─────────────────────────────────────────────────────────
    $recipientId = isset($data['recipient_id']) ? (int)$data['recipient_id'] : null;
    $targetRole  = isset($data['target_role'])  ? trim((string)$data['target_role']) : null;
    $type        = trim((string)($data['type']    ?? 'general'));
    $message     = trim((string)($data['message'] ?? ''));
    $actionUrl   = isset($data['action_url'])   ? trim((string)$data['action_url']) : null;

    // At least one routing target is mandatory
    if ($recipientId === null && empty($targetRole)) {
        error_log('[dispatchNotification] Rejected: both recipient_id and target_role are empty.');
        return false;
    }

    if (empty($message)) {
        error_log('[dispatchNotification] Rejected: message is empty.');
        return false;
    }

    // Normalise empty strings to NULL so the schema stays clean
    if (empty($targetRole))  $targetRole = null;
    if (empty($actionUrl))   $actionUrl  = null;
    if ($recipientId === 0)  $recipientId = null;

    // ── Insert ─────────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO notifications (recipient_id, target_role, type, message, action_url)
        VALUES (:rid, :role, :type, :msg, :url)
    ");

    $stmt->execute([
        ':rid'  => $recipientId,
        ':role' => $targetRole,
        ':type' => $type,
        ':msg'  => $message,
        ':url'  => $actionUrl,
    ]);

    return (int)$pdo->lastInsertId();
}


// ═══════════════════════════════════════════════════════════════════════════════
// § 2  EXAMPLE CALL-SITES  (inline documentation for developers)
// ═══════════════════════════════════════════════════════════════════════════════
/*

────────────────────────────────────────────────────────────────────────────────
EXAMPLE 1 — SuperAdmin alert (User Management)
Triggered when a new user account is created / role is changed.
Called from: src/api/staff.php (POST handler)
────────────────────────────────────────────────────────────────────────────────

    dispatchNotification($pdo, [
        'target_role' => 'superadmin',                      // superadmin inbox only
        'type'        => 'user_management',                 // strictly SA domain
        'message'     => "New account created: {$name} ({$email}) with role '{$role}'.",
        'action_url'  => '/views/admin/users.php',          // deep-link → User Management
    ]);


────────────────────────────────────────────────────────────────────────────────
EXAMPLE 2a — Admin + Frontdesk alert (New Booking Confirmed)
Triggered on booking creation.
Called from: src/api/bookings.php (POST handler, after INSERT)
────────────────────────────────────────────────────────────────────────────────

    dispatchNotification($pdo, [
        'target_role' => 'global',                          // admin + frontdesk
        'type'        => 'booking',
        'message'     => "New booking confirmed for {$clientName} on "
                       . date('M d, Y', strtotime($eventDate)) . ".",
        'action_url'  => "/views/admin/bookings.php?id={$bookingId}",
    ]);


────────────────────────────────────────────────────────────────────────────────
EXAMPLE 2b — Admin + Frontdesk alert (Payment Received)
Triggered when a payment is recorded.
Called from: src/api/payments.php (POST handler)
────────────────────────────────────────────────────────────────────────────────

    dispatchNotification($pdo, [
        'target_role' => 'global',
        'type'        => 'finance',
        'message'     => "Payment of ₱{$amountFormatted} received for Booking #{$bookingId} "
                       . "({$clientName}). Payment method: {$method}.",
        'action_url'  => "/views/admin/financial.php?booking_id={$bookingId}",
    ]);


────────────────────────────────────────────────────────────────────────────────
EXAMPLE 3 — Specific Staff alert (New Job Dispatch)
Triggered when a job order is created.
Called from: src/api/dispatching.php (POST handler, inside foreach $staffIds)
────────────────────────────────────────────────────────────────────────────────

    dispatchNotification($pdo, [
        'recipient_id' => $staffId,                         // direct to one staff member
        'type'         => 'dispatch',
        'message'      => "You have been assigned as {$roleRequired} for "
                        . "{$clientName}'s event on "
                        . date('M d, Y', strtotime($eventDate))
                        . ". Please respond in your Job Board.",
        'action_url'   => '/views/staff/dashboard.php',     // deep-link → staff job board
    ]);

*/


// ═══════════════════════════════════════════════════════════════════════════════
// § 3  PASSIVE CRON — checkUpcomingUnpaidBookings()
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * Checks for upcoming bookings (within 72 hours) that have an outstanding balance.
 * Dispatches a 'finance' notification to the 'global' role (admin + frontdesk).
 * Deduplicates: only one alert per booking per calendar day.
 *
 * @param PDO $pdo
 * @return int Number of notification rows created.
 */
function checkUpcomingUnpaidBookings(PDO $pdo): int
{
    // 1. Find bookings due within 3 days that are not fully paid and not cancelled
    $stmt = $pdo->prepare("
        SELECT b.id, c.name AS client_name, b.event_date,
               (b.total_cost - b.amount_paid) AS balance
        FROM bookings b
        JOIN clients c ON c.id = b.client_id
        WHERE b.event_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
          AND b.event_date >= CURDATE()
          AND b.booking_status NOT IN ('cancelled', 'completed')
          AND b.amount_paid < b.total_cost
    ");
    $stmt->execute();
    $upcoming = $stmt->fetchAll();

    if (empty($upcoming)) {
        return 0;
    }

    $created = 0;

    foreach ($upcoming as $booking) {
        $bid              = (int)$booking['id'];
        $eventDateFmt     = date('M d, Y', strtotime($booking['event_date']));
        $balanceFmt       = '₱' . number_format((float)$booking['balance'], 2);
        $clientName       = $booking['client_name'];

        // 2. Deduplication: skip if a finance alert for this booking was already
        //    dispatched today (match on target_role + type + action_url pattern)
        $dup = $pdo->prepare("
            SELECT id FROM notifications
            WHERE target_role = 'global'
              AND type        = 'finance'
              AND action_url  LIKE :pattern
              AND DATE(created_at) = CURDATE()
            LIMIT 1
        ");
        $dup->execute([':pattern' => "%bookings.php?id={$bid}%"]);
        if ($dup->fetch()) {
            continue; // Already notified today
        }

        // 3. Fire one notification row — the fetch API expands it to all
        //    admin + frontdesk users at query time (no per-user fan-out needed)
        $notifId = dispatchNotification($pdo, [
            'target_role' => 'global',
            'type'        => 'finance',
            'message'     => "⚠️ URGENT: Booking #{$bid} ({$clientName}) is on {$eventDateFmt} "
                           . "and still has an outstanding balance of {$balanceFmt}.",
            'action_url'  => "/views/admin/bookings.php?id={$bid}",
        ]);

        if ($notifId !== false) {
            $created++;
        }
    }

    return $created;
}
