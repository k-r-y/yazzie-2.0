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
 * Send an HTML email via SMTP.
 *
 * @param string $toEmail     Recipient email address
 * @param string $toName      Recipient display name
 * @param string $subject     Email subject
 * @param string $htmlBody    HTML content of the email
 * @param string|null $plainText  Optional plain-text alternative
 * @return bool               True on success, false on failure
 */
function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, ?string $plainText = null): bool
{
    global $pdo;

    if (!MAIL_ENABLED) {
        error_log("[Mailer] MAIL_ENABLED is false. Skipped queueing email to: $toEmail / Subject: $subject");
        return false;
    }

    if (!isset($pdo)) {
        error_log("[Mailer] Database connection not found. Cannot queue email to: $toEmail");
        return false;
    }

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

    $subject = "Booking Confirmed — " . APP_NAME;
    $eventDate = date('F j, Y', strtotime($booking['event_date']));

    $html = "
    <div style='font-family:-apple-system, BlinkMacSystemFont, \`Segoe UI\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;'>
      <div style='background:linear-gradient(135deg, #30D158, #25A244); padding:32px; border-radius:16px 16px 0 0; text-align:center;'>
        <div style='font-size:36px; margin-bottom:8px;'>🍽️</div>
        <h1 style='color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;'>Yazzies Catering</h1>
        <p style='color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;'>Booking Confirmed</p>
      </div>
      <div style='background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);'>
        <h2 style='color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;'>Hello, {$booking['client_name']}!</h2>
        <p style='color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;'>Your event booking is officially secured. We are thrilled to cater for you. Here are the event details:</p>
        
        <div style='background:#F2F2F7; border-radius:12px; padding:20px; margin-bottom:24px;'>
          <table style='width:100%; border-collapse:collapse;'>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500; width:40%;'>Event Date</td><td style='padding:8px 0; font-weight:600; font-size:14px; color:#000000;'>$eventDate</td></tr>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Menu Package</td><td style='padding:8px 0; font-weight:600; font-size:14px; color:#000000;'>{$booking['menu_name']}</td></tr>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Guest Count</td><td style='padding:8px 0; font-weight:600; font-size:14px; color:#000000;'>{$booking['pax_count']} guests</td></tr>
            <tr><td style='padding:8px 0; padding-top:16px; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Total Cost</td><td style='padding:8px 0; padding-top:16px; font-weight:600; font-size:14px; color:#000000;'>₱" . number_format($booking['total_cost'], 2) . "</td></tr>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Amount Paid</td><td style='padding:8px 0; font-weight:600; font-size:14px; color:#30D158;'>₱" . number_format($booking['amount_paid'], 2) . "</td></tr>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Remaining Balance</td><td style='padding:8px 0; font-weight:700; font-size:14px; color:#FF3B30;'>₱" . number_format($booking['total_cost'] - $booking['amount_paid'], 2) . "</td></tr>
          </table>
        </div>
        
        <p style='color:rgba(60,60,67,0.6); font-size:13px; line-height:1.5; margin:0 0 16px;'>Please settle any remaining balance on or before the event date. We look forward to serving you.</p>
        <p style='color:#000000; font-size:14px; font-weight:600; margin:0;'>Thank you! 🎉</p>
      </div>
      <div style='background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;'>
        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite
      </div>
    </div>";

    return sendMail($booking['client_email'], $booking['client_name'], $subject, $html);
}

/**
 * Send a payment receipt email to a client.
 */
function sendPaymentReceipt(array $booking, float $paymentAmount, string $method): bool
{
    if (empty($booking['client_email'])) return false;

    $subject = "Payment Received — " . APP_NAME;
    $html = "
    <div style='font-family:-apple-system, BlinkMacSystemFont, \`Segoe UI\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;'>
      <div style='background:linear-gradient(135deg, #30D158, #25A244); padding:32px; border-radius:16px 16px 0 0; text-align:center;'>
        <div style='font-size:36px; margin-bottom:8px;'>💳</div>
        <h1 style='color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;'>Payment Received</h1>
        <p style='color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;'>Yazzies Catering</p>
      </div>
      <div style='background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);'>
        <h2 style='color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;'>Hi, {$booking['client_name']}!</h2>
        <p style='color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;'>We have successfully processed your payment.</p>
        
        <div style='background:#F2F2F7; border-radius:12px; padding:20px; margin-bottom:24px; text-align:center;'>
            <p style='color:rgba(60,60,67,0.6); font-size:13px; margin:0 0 8px; font-weight:500;'>Amount Applied via $method</p>
            <div style='font-size:28px; font-weight:800; color:#30D158; letter-spacing:-1px; margin-bottom:16px;'>₱" . number_format($paymentAmount, 2) . "</div>
            <div style='border-top:0.5px solid rgba(60,60,67,0.1); padding-top:16px;'>
                <p style='color:rgba(60,60,67,0.6); font-size:13px; font-weight:500; margin:0;'>Remaining Balance: <span style='font-weight:700; color:#000000;'>₱" . number_format(max(0, $booking['total_cost'] - $booking['amount_paid']), 2) . "</span></p>
            </div>
        </div>
        
        <p style='color:rgba(60,60,67,0.6); font-size:13px; line-height:1.5; margin:0 0 16px;'>We can't wait to deliver an incredible experience on " . date('F j, Y', strtotime($booking['event_date'])) . ".</p>
        <p style='color:#000000; font-size:14px; font-weight:600; margin:0;'>Thank you! ✨</p>
      </div>
      <div style='background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;'>
        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite
      </div>
    </div>";

    return sendMail($booking['client_email'], $booking['client_name'], $subject, $html);
}

/**
 * Notify a staff member they have been assigned to an event.
 */
function sendStaffAssignmentEmail(array $staff, array $booking): bool
{
    if (empty($staff['email'])) return false;

    $subject   = "📋 You've been assigned to an event — " . APP_NAME;
    $eventDate = date('F j, Y', strtotime($booking['event_date']));
    $eventTime = !empty($booking['event_time']) ? date('g:i A', strtotime($booking['event_time'])) : '—';
    $role      = htmlspecialchars($booking['staff_role'] ?? 'Staff');
    $location  = htmlspecialchars($booking['event_location'] ?? '—');
    $pax       = (int)($booking['pax_count'] ?? 0);
    $loginUrl  = BASE_URL . '/';

    $html = "
    <div style='font-family:-apple-system, BlinkMacSystemFont, \`Segoe UI\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;'>
      <div style='background:linear-gradient(135deg, #30D158, #25A244); padding:32px; border-radius:16px 16px 0 0; text-align:center;'>
        <div style='font-size:36px; margin-bottom:8px;'>🍽️</div>
        <h1 style='color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;'>Event Assignment</h1>
        <p style='color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;'>Yazzies Catering OMS</p>
      </div>
      <div style='background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);'>
        <h2 style='color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;'>Hi, {$staff['name']}!</h2>
        <p style='color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;'>You have been selected as part of the team for an upcoming catering event. Please review the details below.</p>

        <div style='background:#F2F2F7; border-radius:12px; padding:20px; margin-bottom:24px;'>
          <table style='width:100%; border-collapse:collapse;'>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500; width:38%;'>Your Role</td>
                <td style='padding:8px 0; font-weight:600; font-size:14px; color:#000000;'>$role</td></tr>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Event Date</td>
                <td style='padding:8px 0; font-weight:600; font-size:14px; color:#000000;'>$eventDate</td></tr>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Time</td>
                <td style='padding:8px 0; font-weight:600; font-size:14px; color:#000000;'>$eventTime</td></tr>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Location</td>
                <td style='padding:8px 0; font-weight:600; font-size:14px; color:#000000;'>$location</td></tr>
            <tr><td style='padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;'>Guests</td>
                <td style='padding:8px 0; font-weight:600; font-size:14px; color:#000000;'>$pax pax</td></tr>
          </table>
        </div>

        <p style='color:rgba(60,60,67,0.8); font-size:13px; line-height:1.5; margin:0 0 16px;'>Log in to your account to view full details and confirm your availability.</p>
        <div style='text-align:center;'>
            <a href='$loginUrl' style='display:inline-block; margin-top:8px; background:#30D158; color:#ffffff; padding:12px 24px; border-radius:10px; font-weight:600; font-size:14px; text-decoration:none;'>View My Schedule</a>
        </div>
      </div>
      <div style='background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;'>
        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite
      </div>
    </div>";

    return sendMail($staff['email'], $staff['name'], $subject, $html);
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
        ? "✅ Leave Approved — $formattedDate — " . APP_NAME
        : "❌ Leave Request Not Approved — $formattedDate — " . APP_NAME;

    $headerBg   = $isApproved ? '#30D158' : '#FF3B30';
    $emoji      = $isApproved ? '✅' : '❌';
    $headline   = $isApproved ? 'Leave Approved!' : 'Leave Not Approved';
    $message    = $isApproved
        ? "Your leave request for <strong>$formattedDate</strong> has been approved. You are free from duty on this date."
        : "Unfortunately, your leave request for <strong>$formattedDate</strong> was not approved. Please contact your manager if you have questions.";

    $html = "
    <div style='font-family:-apple-system, BlinkMacSystemFont, \`Segoe UI\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;'>
      <div style='background:$headerBg; padding:32px; border-radius:16px 16px 0 0; text-align:center;'>
        <div style='font-size:42px; margin-bottom:8px;'>$emoji</div>
        <h1 style='color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;'>$headline</h1>
      </div>
      <div style='background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);'>
        <h2 style='color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;'>Hi, {$staff['name']}!</h2>
        <p style='color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0;'>$message</p>
      </div>
      <div style='background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;'>
        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite
      </div>
    </div>";

    return sendMail($staff['email'], $staff['name'], $subject, $html);
}
