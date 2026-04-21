<?php
/**
 * Email Sender — PHPMailer SMTP Wrapper
 *
 * Usage:
 *   require_once __DIR__ . '/mailer.php';
 *   sendMail('client@email.com', 'John', 'Booking Confirmed', $htmlBody);
 *
 * Setup:
 *   1. Update MAIL_USERNAME and MAIL_PASSWORD in config/config.php
 *   2. Set MAIL_ENABLED = true in config/config.php
 *   3. Install PHPMailer: composer require phpmailer/phpmailer
 *      OR place PHPMailer src files in /includes/phpmailer/
 */

// Load PHPMailer — try Composer autoload first, then manual
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
$phpmailerSrc     = __DIR__ . '/phpmailer/PHPMailer.php';

if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} elseif (file_exists($phpmailerSrc)) {
    require_once __DIR__ . '/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/phpmailer/SMTP.php';
    require_once __DIR__ . '/phpmailer/Exception.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Send an HTML email IMMEDIATELY via SMTP (No Queuing).
 *
 * @param string $toEmail     Recipient email address
 * @param string $toName      Recipient display name
 * @param string $subject     Email subject
 * @param string $htmlBody    HTML content of the email
 * @return bool               True on success, false on failure
 */
function sendMailImmediate(string $toEmail, string $toName, string $subject, string $htmlBody): bool
{
    if (!MAIL_ENABLED) {
        error_log("[Mailer] MAIL_ENABLED is false. Skipped sending email to: $toEmail");
        return false;
    }

    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_USERNAME, APP_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        return $mail->send();
    } catch (\Exception $e) {
        error_log("[Mailer Error] " . $e->getMessage());
        return false;
    }
}

/**
 * Internal helper to provide a consistent premium design wrapper for all emails.
 */
function renderEmailTemplate(string $title, string $emoji, string $content, string $themeColor = '#30D158', string $preheader = ''): string 
{
    $appName = defined('APP_NAME') ? APP_NAME : 'Yazzies Catering';
    
    // Gradient logic: lighter version of themeColor for nice look
    $gradientEnd = '#25A244'; // default for green
    if ($themeColor === '#FF9500') $gradientEnd = '#FF5E00'; // orange
    if ($themeColor === '#FF3B30') $gradientEnd = '#C0392B'; // red

    return "
    <div style='display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;'>$preheader</div>
    <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; background-color: #F2F2F7; padding: 40px 20px;'>
        <div style='max-width: 560px; margin: 0 auto; border-radius: 20px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); background-color: #ffffff;'>
            
            <!-- HEADER -->
            <div style='background: linear-gradient(135deg, $themeColor, $gradientEnd); padding: 40px 30px; text-align: center; color: #ffffff;'>
                <div style='font-size: 48px; margin-bottom: 12px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.15));'>$emoji</div>
                <h1 style='margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -0.8px;'>$title</h1>
                <p style='margin: 6px 0 0; font-size: 14px; font-weight: 500; opacity: 0.9;'>$appName</p>
            </div>

            <!-- BODY -->
            <div style='padding: 40px 30px;'>
                $content
            </div>

            <!-- FOOTER -->
            <div style='padding: 30px; background-color: #F8F8FA; border-top: 0.5px solid rgba(60,60,67,0.06); text-align: center;'>
                <p style='margin: 0; font-size: 12px; color: rgba(60,60,67,0.4); font-weight: 500; line-height: 1.6;'>
                    <strong>$appName</strong><br>
                    Barangay St. Peter, Dasmariñas City, Cavite<br>
                    &copy; " . date('Y') . " All rights reserved.
                </p>
            </div>
        </div>
    </div>";
}

/**
 * Send an HTML email via Queue (Legacy).
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $plainText = null): bool
{
    global $pdo;

    if (!MAIL_ENABLED) { return false; }
    if (!isset($pdo)) { return false; }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO email_queue (recipient_email, recipient_name, subject, body_html, status)
            VALUES (:email, :name, :sub, :html, 'pending')
        ");
        $stmt->execute([
            ':email' => $toEmail,
            ':name'  => $toName,
            ':sub'   => $subject,
            ':html'  => $htmlBody
        ]);
        return true;
    } catch (\Throwable $e) {
        error_log("[Mailer Queue Error] " . $e->getMessage());
        return false;
    }
}

/**
 * Send a booking confirmation email to a client.
 */
function sendBookingConfirmation(array $booking): bool
{
    if (empty($booking['client_email'])) return false;

    $subject   = "Booking Confirmed — " . APP_NAME;
    $eventDate = date('F j, Y', strtotime($booking['event_date']));
    
    $content = "
        <h2 style='margin: 0 0 12px; font-size: 20px; font-weight: 700; color: #000000; letter-spacing: -0.4px;'>Hi, {$booking['client_name']}!</h2>
        <p style='margin: 0 0 30px; font-size: 15px; color: rgba(60,60,67,0.7); line-height: 1.6;'>Your event is officially secured! We are excited to cater for your special occasion. Here's a summary of your booking details:</p>

        <div style='background: #F2F2F7; border-radius: 16px; padding: 24px; margin-bottom: 30px; border: 0.5px solid rgba(60,60,67,0.06);'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Event Date</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>$eventDate</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Package</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>{$booking['menu_name']}</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Guest Count</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #30D158;'>{$booking['pax_count']} guests</td></tr>
                
                <tr><td colspan='2' style='border-top: 1px dashed rgba(60,60,67,0.1); padding-top: 16px; margin-top: 16px;'></td></tr>
                
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Total Amount</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000; font-size: 18px;'>₱" . number_format($booking['total_cost'], 2) . "</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Amount Paid</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #30D158;'>₱" . number_format($booking['amount_paid'], 2) . "</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Remaining</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 800; color: #FF3B30;'>₱" . number_format($booking['total_cost'] - $booking['amount_paid'], 2) . "</td></tr>
            </table>
        </div>

        <p style='margin: 0 0 8px; font-size: 14px; color: rgba(60,60,67,0.6); line-height: 1.5;'>Please settle the remaining balance before or on the day of your event.</p>
        <p style='margin: 0; font-size: 15px; font-weight: 700; color: #30D158;'>Thank you and see you soon! 🎉</p>
    ";

    $html = renderEmailTemplate("Booking Confirmed", "🍽️", $content, "#30D158", "Your event on $eventDate is officially secured.");
    return sendMailImmediate($booking['client_email'], $booking['client_name'], $subject, $html);
}

/**
 * Send a payment receipt email to a client.
 */
function sendPaymentReceipt(array $booking, float $paymentAmount, string $method): bool
{
    if (empty($booking['client_email'])) return false;

    $subject = "Payment Received — " . APP_NAME;
    $balance = max(0, $booking['total_cost'] - $booking['amount_paid']);

    $content = "
        <h2 style='margin: 0 0 12px; font-size: 20px; font-weight: 700; color: #000000; letter-spacing: -0.4px;'>Hi, {$booking['client_name']}!</h2>
        <p style='margin: 0 0 30px; font-size: 15px; color: rgba(60,60,67,0.7); line-height: 1.6;'>We've successfully processed your payment. Thank you for your continued trust in Yazzies Catering.</p>

        <div style='background: #F2F2F7; border-radius: 16px; padding: 30px; margin-bottom: 30px; text-align: center; border: 0.5px solid rgba(60,60,67,0.06);'>
            <p style='margin: 0 0 8px; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Amount Applied via $method</p>
            <div style='font-size: 36px; font-weight: 800; color: #30D158; letter-spacing: -1.5px;'>₱" . number_format($paymentAmount, 2) . "</div>
            <div style='margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(60,60,67,0.08);'>
                <p style='margin: 0; font-size: 14px; color: rgba(60,60,67,0.6);'>Remaining Balance: <strong style='color: #000000;'>₱" . number_format($balance, 2) . "</strong></p>
            </div>
        </div>

        <p style='margin: 0 0 8px; font-size: 14px; color: rgba(60,60,67,0.6); line-height: 1.5;'>We're looking forward to delivering an incredible experience on " . date('F j, Y', strtotime($booking['event_date'])) . ".</p>
        <p style='margin: 0; font-size: 15px; font-weight: 700; color: #30D158;'>Thank you! ✨</p>
    ";

    $html = renderEmailTemplate("Payment Received", "💳", $content, "#30D158", "We have processed your payment of ₱" . number_format($paymentAmount, 2) . ".");
    return sendMailImmediate($booking['client_email'], $booking['client_name'], $subject, $html);
}
/**
 * Send a manual payment reminder email to a client.
 */
function sendPaymentReminderEmail(array $booking): bool
{
    if (empty($booking['client_email'])) return false;

    $subject   = "Payment Reminder: Your event is approaching! — " . APP_NAME;
    $eventDate = date('F j, Y', strtotime($booking['event_date']));
    $balance   = (float)$booking['total_cost'] - (float)$booking['amount_paid'];

    $content = "
        <h2 style='margin: 0 0 12px; font-size: 20px; font-weight: 700; color: #000000; letter-spacing: -0.4px;'>Hello, {$booking['client_name']}!</h2>
        <p style='margin: 0 0 30px; font-size: 15px; color: rgba(60,60,67,0.7); line-height: 1.6;'>This is a friendly reminder regarding your upcoming catering event. To ensure everything is ready for your special day, please settle your remaining balance.</p>

        <div style='background: #FFF9F0; border-radius: 16px; padding: 30px; margin-bottom: 30px; text-align: center; border: 1px solid rgba(255,149,0,0.25);'>
            <p style='margin: 0 0 8px; font-size: 13px; color: rgba(255,149,0,0.8); font-weight: 700; text-transform: uppercase;'>Remaining Balance for $eventDate</p>
            <div style='font-size: 36px; font-weight: 800; color: #FF9500; letter-spacing: -1.5px;'>₱" . number_format($balance, 2) . "</div>
        </div>

        <p style='margin: 0 0 8px; font-size: 14px; color: rgba(60,60,67,0.6); line-height: 1.5;'>If you've already made this payment, please disregard this note. We look forward to serving you!</p>
        <p style='margin: 0; font-size: 15px; font-weight: 700; color: #FF9500;'>Thank you! 🍽️</p>
    ";

    $html = renderEmailTemplate("Balance Reminder", "🔔", $content, "#FF9500", "Friendly reminder: your remaining balance for $eventDate is ₱" . number_format($balance, 2) . ".");
    return sendMailImmediate($booking['client_email'], $booking['client_name'], $subject, $html);
}

/**
 * Notify a staff member they have been assigned to an event.
 */
function sendStaffAssignmentEmail(array $staff, array $booking): bool
{
    if (empty($staff['email'])) return false;

    $subject   = "📋 Assignment: {$booking['event_date']} — " . APP_NAME;
    $eventDate = date('F j, Y', strtotime($booking['event_date']));
    $eventTime = !empty($booking['event_time']) ? date('g:i A', strtotime($booking['event_time'])) : 'TBA';
    $role      = htmlspecialchars($booking['staff_role'] ?? 'Staff');
    $location  = htmlspecialchars($booking['event_location'] ?? 'TBA');
    $pax       = (int)($booking['pax_count'] ?? 0);
    $loginUrl  = BASE_URL . '/';

    $content = "
        <h2 style='margin: 0 0 12px; font-size: 20px; font-weight: 700; color: #000000; letter-spacing: -0.4px;'>Hi, {$staff['name']}!</h2>
        <p style='margin: 0 0 30px; font-size: 15px; color: rgba(60,60,67,0.7); line-height: 1.6;'>You've been assigned to the team for an upcoming event. Please review the assignment details below:</p>

        <div style='background: #F2F2F7; border-radius: 16px; padding: 24px; margin-bottom: 30px; border: 0.5px solid rgba(60,60,67,0.06);'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Your Role</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #30D158;'>$role</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Date</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>$eventDate</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Time</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>$eventTime</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Guests</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>$pax pax</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Location</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>$location</td></tr>
            </table>
        </div>

        <div style='text-align: center;'>
            <a href='$loginUrl' style='display: inline-block; background-color: #30D158; color: #ffffff; padding: 14px 28px; border-radius: 12px; font-weight: 700; font-size: 14px; text-decoration: none; box-shadow: 0 4px 12px rgba(48,209,88,0.25);'>View My Schedule</a>
        </div>
    ";

    $html = renderEmailTemplate("Event Assignment", "📋", $content, "#30D158", "You have a new event assignment for $eventDate as $role.");
    return sendMailImmediate($staff['email'], $staff['name'], $subject, $html);
}

/**
 * Notify a staff member about the outcome of their leave request.
 */
function sendLeaveStatusEmail(array $staff, string $leaveDate, string $status): bool
{
    if (empty($staff['email'])) return false;

    $formattedDate = date('F j, Y', strtotime($leaveDate));
    $isApproved    = $status === 'approved';
    $subject       = $isApproved
        ? "✅ Leave Approved — $formattedDate"
        : "❌ Leave Request Denied — $formattedDate";

    $themeColor = $isApproved ? '#30D158' : '#FF3B30';
    $emoji      = $isApproved ? '✅' : '❌';
    $headline   = $isApproved ? 'Leave Approved!' : 'Request Updates';
    $message    = $isApproved
        ? "Good news! Your leave request for <strong>$formattedDate</strong> has been approved. You are free from duty on this date."
        : "Your leave request for <strong>$formattedDate</strong> was not approved at this time. Please contact your manager if you have any questions.";

    $content = "
        <h2 style='margin: 0 0 12px; font-size: 20px; font-weight: 700; color: #000000; letter-spacing: -0.4px;'>Hi, {$staff['name']}!</h2>
        <p style='margin: 0; font-size: 15px; color: rgba(60,60,67,0.7); line-height: 1.6;'>$message</p>
    ";

    $html = renderEmailTemplate($headline, $emoji, $content, $themeColor, "Update on your leave request for $formattedDate.");
    return sendMailImmediate($staff['email'], $staff['name'], $subject, $html);
}

/**
 * Notify an administrator that a staff member has responded to a job invitation.
 */
function sendJobResponseEmailToAdmin(array $admin, array $staff, array $booking, string $status): bool
{
    if (empty($admin['email'])) return false;

    $isAccepted = $status === 'accepted';
    $subject    = $isAccepted
        ? "✅ Job Accepted: {$staff['name']} for {$booking['event_date']}"
        : "❌ Job Declined: {$staff['name']} for {$booking['event_date']}";

    $themeColor = $isAccepted ? '#30D158' : '#FF9500'; // Green for ok, Orange for alert/decline
    $emoji      = $isAccepted ? '✅' : '🚫';
    $headline   = $isAccepted ? 'Job Accepted' : 'Job Declined';

    $eventDate = date('F j, Y', strtotime($booking['event_date']));
    $role      = htmlspecialchars($booking['staff_role'] ?? 'Staff');
    
    $message = $isAccepted
        ? "<strong>{$staff['name']}</strong> has <strong>accepted</strong> their role as <em>$role</em> for the upcoming event."
        : "<strong>{$staff['name']}</strong> has <strong>declined</strong> the job offer for the upcoming event. You may need to find a replacement.";

    $content = "
        <h2 style='margin: 0 0 12px; font-size: 20px; font-weight: 700; color: #000000; letter-spacing: -0.4px;'>Hi, {$admin['name']}!</h2>
        <p style='margin: 0 0 24px; font-size: 15px; color: rgba(60,60,67,0.7); line-height: 1.6;'>$message</p>

        <div style='background: #F2F2F7; border-radius: 16px; padding: 24px; border: 0.5px solid rgba(60,60,67,0.06);'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Staff Name</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>{$staff['name']}</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Event Date</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>$eventDate</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Role</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #000000;'>$role</td></tr>
                <tr><td style='padding: 6px 0; font-size: 13px; color: rgba(60,60,67,0.5); font-weight: 600; text-transform: uppercase;'>Client</td>
                    <td style='padding: 6px 0; text-align: right; font-weight: 700; color: #30D158;'>{$booking['client_name']}</td></tr>
            </table>
        </div>
    ";

    $html = renderEmailTemplate($headline, $emoji, $content, $themeColor, "Staff Response: {$staff['name']} updated their status to $status.");
    return sendMailImmediate($admin['email'], $admin['name'], $subject, $html);
}
