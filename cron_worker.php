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

    $notifInsert = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, body, booking_id, link_url)
        VALUES (:uid, 'general', :title, :body, :bid, :link)
    ");

    // Check if we already sent reminders for today (prevent duplicates on re-runs)
    $checkSent = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE booking_id = :bid AND title LIKE '%Event Reminder%' 
          AND DATE(created_at) = CURDATE()
    ");

    $count = 0;
    foreach ($bookings as $bk) {
        $checkSent->execute([':bid' => $bk['id']]);
        if ((int)$checkSent->fetchColumn() > 0) {
            $log("[REMIND] Reminder already sent today for booking #{$bk['id']}. Skipping.");
            continue;
        }

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
            try {
                $notifInsert->execute([
                    ':uid'   => $staff['staff_id'],
                    ':title' => '📅 Event Reminder — 3 Days Away',
                    ':body'  => "Reminder: You are assigned to {$bk['client_name']}'s event on {$eventDateFormatted}" .
                                ($eventTimeFormatted ? " at {$eventTimeFormatted}" : '') .
                                ($bk['event_location'] ? " at {$bk['event_location']}" : '') .
                                ". Pax: {$bk['pax_count']}. Please confirm your availability.",
                    ':bid'   => $bk['id'],
                    ':link'  => BASE_URL . '/views/staff/dashboard.php',
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
        $log('[BALANCE] No client balances to remind for events in 3 days.');
        return 0;
    }

    $count = 0;
    require_once __DIR__ . '/includes/mailer.php';

    // Duplicate check statement
    $checkSent = $pdo->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE booking_id = :bid AND title LIKE '%Pending Balance%' 
          AND DATE(created_at) = CURDATE()
    ");

    foreach ($bookings as $bk) {
        $checkSent->execute([':bid' => $bk['id']]);
        if ((int)$checkSent->fetchColumn() > 0) {
            $log("[BALANCE] Reminder already sent today for booking #{$bk['id']}. Skipping.");
            continue;
        }

        $balance = $bk['total_cost'] - $bk['amount_paid'];
        $eventDate = date('F j, Y', strtotime($bk['event_date']));
        
        $subject = "Payment Reminder: Your event is in 3 days! — " . APP_NAME;
        $html = "
        <div style='font-family:-apple-system, BlinkMacSystemFont, \`Segoe UI\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;'>
          <div style='background:linear-gradient(135deg, #FF9500, #FF5E00); padding:32px; border-radius:16px 16px 0 0; text-align:center;'>
            <div style='font-size:36px; margin-bottom:8px;'>🔔</div>
            <h1 style='color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;'>Balance Reminder</h1>
            <p style='color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;'>Yazzies Catering</p>
          </div>
          <div style='background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);'>
            <h2 style='color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;'>Hello, {$bk['client_name']}!</h2>
            <p style='color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;'>This is a friendly reminder that your catering event is just 3 days away! To ensure everything runs smoothly, please settle your remaining balance.</p>
            
            <div style='background:#FFF9F0; border:1px solid #FF9500; border-radius:12px; padding:20px; margin-bottom:24px; text-align:center;'>
                <p style='color:rgba(60,60,67,0.6); font-size:13px; margin:0 0 8px; font-weight:500;'>Remaining Balance for $eventDate</p>
                <div style='font-size:32px; font-weight:800; color:#FF9500; letter-spacing:-1px;'>₱" . number_format($balance, 2) . "</div>
            </div>
            
            <p style='color:rgba(60,60,67,0.6); font-size:13px; line-height:1.5; margin:0 0 16px;'>If you have already made the payment, please disregard this email or send us a copy of your proof of payment. We are excited to serve you!</p>
            <p style='color:#000000; font-size:14px; font-weight:600; margin:0;'>See you soon! 🍽️</p>
          </div>
          <div style='background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;'>
            Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite
          </div>
        </div>";

        if (sendMail($bk['client_email'], $bk['client_name'], $subject, $html)) {
            $count++;
            $log("[BALANCE] Queued reminder for #{$bk['id']} ({$bk['client_name']})");

            // Also Notify the System (Admins/Frontdesk)
            try {
                $sysNotif = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, body, booking_id, link_url)
                    SELECT id, 'general', :title, :body, :bid, :link
                    FROM users 
                    WHERE role IN ('super_admin', 'admin', 'frontdesk') 
                      AND is_active = 1
                ");
                $sysNotif->execute([
                    ':title' => '💰 Pending Balance: ' . $bk['client_name'],
                    ':body'  => "Booking #{$bk['id']} is in 3 days but still has a remaining balance of ₱" . number_format($balance, 2),
                    ':bid'   => $bk['id'],
                    ':link'  => BASE_URL . '/views/admin/bookings.php?highlight=' . $bk['id']
                ]);
            } catch (\Throwable $e) {
                $log("[BALANCE] System notification failed: " . $e->getMessage());
            }
        }
    }

    $log("[BALANCE] Total balance reminders queued: $count.");
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
