<?php
//
// Cron Worker — Automated background tasks for Yazzies OMS
//
// Tasks:
//   1. Process queued emails (email_queue → SMTP)
//   2. Send 3-day-before event reminders to assigned staff
//   3. Cleanup stale login attempts
//
// Schedule via cron (every 5 minutes):
//   crontab: 0,5,10,15,20,25,30,35,40,45,50,55 * * * * php /path/to/cron_worker.php
//
// Can also be run manually:
//   php cron_worker.php
//

// Prevent web access
if (php_sapi_name() !== 'cli' && !defined('CRON_TEST')) {
    http_response_code(403);
    die('CLI only.');
}

define('CRON_MODE', true);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/notifications_helper.php';

$startTime = microtime(true);
$log = function(string $msg) {
    $ts = date('Y-m-d H:i:s');
    echo "[$ts] $msg\n";
};

$log('=== Cron worker started ===');

// ────────────────────────────────────────────────────────────
// TASK 1: Process Email Queue
// ────────────────────────────────────────────────────────────
function processEmailQueue(PDO $pdo, callable $log): int
{
    // Load PHPMailer
    $composerAutoload = __DIR__ . '/vendor/autoload.php';
    $phpmailerSrc     = __DIR__ . '/includes/phpmailer/PHPMailer.php';

    if (file_exists($composerAutoload)) {
        require_once $composerAutoload;
    } elseif (file_exists($phpmailerSrc)) {
        require_once __DIR__ . '/includes/phpmailer/PHPMailer.php';
        require_once __DIR__ . '/includes/phpmailer/SMTP.php';
        require_once __DIR__ . '/includes/phpmailer/Exception.php';
    } else {
        $log('[EMAIL] PHPMailer not found. Skipping email queue.');
        return 0;
    }

    if (!MAIL_ENABLED) {
        $log('[EMAIL] MAIL_ENABLED is false. Skipping.');
        return 0;
    }

    // Fetch up to 20 queued emails per run (rate limit)
    $stmt = $pdo->prepare("
        SELECT id, recipient_email AS to_email, recipient_name AS to_name, subject, body_html AS body 
        FROM email_queue 
        WHERE status = 'pending' AND attempts < 3
        ORDER BY created_at ASC
        LIMIT 20
    ");
    $stmt->execute();
    $emails = $stmt->fetchAll();

    if (empty($emails)) {
        $log('[EMAIL] No queued emails.');
        return 0;
    }

    $sent = 0;
    $markSending = $pdo->prepare("UPDATE email_queue SET status = 'sending' WHERE id = :id");
    $markSent    = $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = :id");
    $markFailed  = $pdo->prepare("
        UPDATE email_queue 
        SET status = IF(attempts >= 2, 'failed', 'pending'), 
            attempts = attempts + 1, 
            error_log = :err 
        WHERE id = :id
    ");

    foreach ($emails as $email) {
        $markSending->execute([':id' => $email['id']]);

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = MAIL_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_USERNAME;
            $mail->Password   = MAIL_PASSWORD;
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = MAIL_PORT;

            $mail->setFrom(MAIL_USERNAME, APP_NAME);
            $mail->addAddress($email['to_email'], $email['to_name'] ?? '');
            $mail->isHTML(true);
            $mail->Subject = $email['subject'];
            $mail->Body    = $email['body'];
            $mail->AltBody = strip_tags($email['body']);

            $mail->send();
            $markSent->execute([':id' => $email['id']]);
            $sent++;
            $log("[EMAIL] Sent #{$email['id']} to {$email['to_email']}");
        } catch (\Throwable $e) {
            $markFailed->execute([':id' => $email['id'], ':err' => $e->getMessage()]);
            $log("[EMAIL] FAILED #{$email['id']}: " . $e->getMessage());
        }

        // Small delay to avoid hammering SMTP
        usleep(200000); // 200ms
    }

    $log("[EMAIL] Processed: $sent/" . count($emails) . " sent successfully.");
    return $sent;
}

// ────────────────────────────────────────────────────────────
// TASK 2: 3-Day Event Reminders
// ────────────────────────────────────────────────────────────
function sendEventReminders(PDO $pdo, callable $log): int
{
    // Find confirmed bookings happening exactly 3 days from now
    $stmt = $pdo->prepare("
        SELECT b.id, b.event_date, b.event_time, b.event_location, b.pax_count,
               c.name AS client_name
        FROM bookings b
        JOIN clients c ON c.id = b.client_id
        WHERE b.event_date = CURDATE() + INTERVAL 3 DAY
          AND b.booking_status = 'confirmed'
    ");
    $stmt->execute();
    $bookings = $stmt->fetchAll();

    if (empty($bookings)) {
        $log('[REMIND] No events in 3 days.');
        return 0;
    }

    // v2: helper is used directly inside the loop
...
    // Check if we already sent reminders for today (prevent duplicates on re-runs)
    $checkSent = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE message LIKE '%Event Reminder%' 
          AND DATE(created_at) = CURDATE()
          AND recipient_id = :sid
    ");

    $count = 0;
    foreach ($bookings as $bk) {
        // Get accepted staff for this booking
        $staffStmt = $pdo->prepare("
            SELECT jo.staff_id, u.name AS staff_name
            FROM job_orders jo
            JOIN users u ON u.id = jo.staff_id
            WHERE jo.booking_id = :bid AND jo.status = 'accepted'
        ");
        $staffStmt->execute([':bid' => $bk['id']]);
        $staffList = $staffStmt->fetchAll();

        if (empty($staffList)) {
            $log("[REMIND] Booking #{$bk['id']} has no accepted staff. Skipping.");
            continue;
        }

        $eventDateFormatted = date('F j, Y', strtotime($bk['event_date']));
        $eventTimeFormatted = !empty($bk['event_time']) ? date('g:i A', strtotime($bk['event_time'])) : '';

        foreach ($staffList as $staff) {
            // Check if we already sent reminders to this specific staff member today
            $checkSent->execute([':sid' => $staff['staff_id']]);
            if ((int)$checkSent->fetchColumn() > 0) {
                continue;
            }
            try {
                dispatchNotification($pdo, [
                    'recipient_id' => $staff['staff_id'],
                    'type'         => 'general',
                    'message'      => "Reminder: You are assigned to {$bk['client_name']}'s event on {$eventDateFormatted}" .
                                    ($eventTimeFormatted ? " at {$eventTimeFormatted}" : '') .
                                    ($bk['event_location'] ? " at {$bk['event_location']}" : '') .
                                    ". Pax: {$bk['pax_count']}. Please confirm your availability.",
                    'action_url'   => '/views/staff/dashboard.php',
                ]);
                $count++;
            } catch (\Throwable $e) {
                $log("[REMIND] Failed to notify staff #{$staff['staff_id']}: " . $e->getMessage());
            }
        }

        $log("[REMIND] Sent " . count($staffList) . " reminders for booking #{$bk['id']} ({$bk['client_name']}).");
    }

    $log("[REMIND] Total reminders sent: $count.");
    return $count;
}

// ────────────────────────────────────────────────────────────
// TASK 4: Client Balance Reminders (3 Days Before Event)
// ────────────────────────────────────────────────────────────
function sendBalanceReminders(PDO $pdo, callable $log): int
{
    // Find bookings with remaining balance happening exactly 3 days from now
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

    if (empty($bookings)) {
        $log('[BALANCE] No client balances to check for events in 3 days.');
        return 0;
    }

    $count = 0;
    // Duplicate check statement for internal notification
    $checkNotified = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE booking_id = :bid AND title LIKE '%Pending Balance%' 
          AND DATE(created_at) = CURDATE()
    ");

    foreach ($bookings as $bk) {
        $checkNotified->execute([':bid' => $bk['id']]);
        if ((int)$checkNotified->fetchColumn() > 0) {
            continue;
        }

        $balance = $bk['total_cost'] - $bk['amount_paid'];
        
        // Notify the System (Admins/Frontdesk) ONLY — No automatic client email as per new manual control policy
        try {
            dispatchNotification($pdo, [
                'target_role' => 'global',
                'type'        => 'finance',
                'message'     => "Booking #{$bk['id']} is in 3 days but still has a remaining balance of ₱" . number_format($balance, 2) . ". Please send a manual reminder if needed.",
                'action_url'  => '/views/admin/bookings.php?id=' . $bk['id'],
            ]);
            $count++;
            $log("[BALANCE] Created internal alert for booking #{$bk['id']} ({$bk['client_name']})");
        } catch (\Throwable $e) {
            $log("[BALANCE] System notification failed: " . $e->getMessage());
        }
    }

    $log("[BALANCE] Total internal balance alerts created: $count.");
    return $count;
}

// ────────────────────────────────────────────────────────────
// TASK 3: Cleanup Stale Login Attempts (older than 24h)
// ────────────────────────────────────────────────────────────
function cleanupLoginAttempts(PDO $pdo, callable $log): int
{
    try {
        $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 24 HOUR");
        $stmt->execute();
        $deleted = $stmt->rowCount();
        if ($deleted > 0) {
            $log("[CLEANUP] Removed $deleted stale login attempts.");
        }
        return $deleted;
    } catch (\Throwable $e) {
        $log("[CLEANUP] Error: " . $e->getMessage());
        return 0;
    }
}

// ────────────────────────────────────────────────────────────
// Execute all tasks
// ────────────────────────────────────────────────────────────
try {
    processEmailQueue($pdo, $log);
    sendEventReminders($pdo, $log);
    sendBalanceReminders($pdo, $log);
    cleanupLoginAttempts($pdo, $log);
} catch (\Throwable $e) {
    $log("[FATAL] Cron worker error: " . $e->getMessage());
}

$elapsed = round((microtime(true) - $startTime) * 1000);
$log("=== Cron worker completed in {$elapsed}ms ===\n");
