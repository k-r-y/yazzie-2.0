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
    if (!MAIL_ENABLED) {
        error_log("[Mailer] MAIL_ENABLED is false. Skipped email to: $toEmail / Subject: $subject");
        return false;
    }

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log("[Mailer] PHPMailer class not found. Install via Composer: composer require phpmailer/phpmailer");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $plainText ?? strip_tags($htmlBody);

        $mail->send();
        error_log("[Mailer] Email sent to $toEmail — Subject: $subject");
        return true;

    } catch (MailException $e) {
        error_log("[Mailer] Failed to send email to $toEmail. Error: " . $mail->ErrorInfo);
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
    <div style='font-family:Outfit,Arial,sans-serif;max-width:560px;margin:0 auto;'>
      <div style='background:linear-gradient(135deg,#C8501E,#F0A028);padding:32px;border-radius:12px 12px 0 0;text-align:center;'>
        <h1 style='color:white;margin:0;font-size:22px;'>🍽️ Yazzies Catering</h1>
        <p style='color:rgba(255,255,255,0.8);margin:4px 0 0;'>Booking Confirmation</p>
      </div>
      <div style='background:#fffffe;padding:32px;border:1px solid #e8e3da;'>
        <h2 style='color:#1a1a2a;'>Hello, {$booking['client_name']}!</h2>
        <p style='color:#555;'>Your event booking has been confirmed. Here are your event details:</p>
        <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
          <tr><td style='padding:8px 0;color:#888;font-size:13px;width:40%;'>Event Date</td><td style='padding:8px 0;font-weight:700;'>$eventDate</td></tr>
          <tr><td style='padding:8px 0;color:#888;font-size:13px;'>Menu Package</td><td style='padding:8px 0;font-weight:700;'>{$booking['menu_name']}</td></tr>
          <tr><td style='padding:8px 0;color:#888;font-size:13px;'>Guests</td><td style='padding:8px 0;font-weight:700;'>{$booking['pax_count']} persons</td></tr>
          <tr><td style='padding:8px 0;color:#888;font-size:13px;'>Total Cost</td><td style='padding:8px 0;font-weight:700;'>₱" . number_format($booking['total_cost'], 2) . "</td></tr>
          <tr><td style='padding:8px 0;color:#888;font-size:13px;'>Amount Paid</td><td style='padding:8px 0;font-weight:700;color:#059669;'>₱" . number_format($booking['amount_paid'], 2) . "</td></tr>
          <tr><td style='padding:8px 0;color:#888;font-size:13px;'>Remaining Balance</td><td style='padding:8px 0;font-weight:700;color:#DC2626;'>₱" . number_format($booking['total_cost'] - $booking['amount_paid'], 2) . "</td></tr>
        </table>
        <p style='color:#555;font-size:13px;'>Please settle your remaining balance on or before the event date. If you have any questions, feel free to contact us.</p>
        <p style='color:#555;font-size:13px;'>Thank you for choosing Yazzies Catering! 🎉</p>
      </div>
      <div style='background:#f4f1ec;padding:16px;border-radius:0 0 12px 12px;text-align:center;font-size:12px;color:#999;'>
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
    <div style='font-family:Outfit,Arial,sans-serif;max-width:560px;margin:0 auto;'>
      <div style='background:linear-gradient(135deg,#059669,#047857);padding:32px;border-radius:12px 12px 0 0;text-align:center;'>
        <h1 style='color:white;margin:0;font-size:22px;'>✅ Payment Received</h1>
        <p style='color:rgba(255,255,255,0.8);'>Yazzies Catering</p>
      </div>
      <div style='background:#fffffe;padding:32px;border:1px solid #e8e3da;'>
        <h2>Hi, {$booking['client_name']}!</h2>
        <p>We have received your payment of <strong>₱" . number_format($paymentAmount, 2) . "</strong> via <strong>$method</strong>.</p>
        <p>Remaining balance: <strong>₱" . number_format(max(0, $booking['total_cost'] - $booking['amount_paid']), 2) . "</strong></p>
        <p style='color:#555;font-size:13px;'>Thank you! See you on " . date('F j, Y', strtotime($booking['event_date'])) . "!</p>
      </div>
      <div style='background:#f4f1ec;padding:16px;border-radius:0 0 12px 12px;text-align:center;font-size:12px;color:#999;'>
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
    <div style='font-family:-apple-system,Helvetica Neue,Arial,sans-serif;max-width:560px;margin:0 auto;'>
      <div style='background:linear-gradient(135deg,#30D158,#25A244);padding:32px;border-radius:14px 14px 0 0;text-align:center;'>
        <div style='font-size:36px;margin-bottom:8px;'>🍽️</div>
        <h1 style='color:white;margin:0;font-size:20px;font-weight:700;'>Event Assignment</h1>
        <p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:13px;'>Yazzies Catering OMS</p>
      </div>
      <div style='background:#ffffff;padding:32px;border:1px solid #e5e7eb;'>
        <h2 style='color:#111827;font-size:17px;margin:0 0 6px;'>Hi, {$staff['name']}!</h2>
        <p style='color:#6b7280;font-size:13px;margin:0 0 24px;'>You have been selected as part of the team for an upcoming catering event. Please review the details below.</p>

        <div style='background:#f9fafb;border-radius:10px;padding:20px;border:1px solid #e5e7eb;margin-bottom:24px;'>
          <table style='width:100%;border-collapse:collapse;'>
            <tr><td style='padding:6px 0;font-size:12px;color:#9ca3af;width:38%;'>Your Role</td>
                <td style='padding:6px 0;font-size:13px;font-weight:700;color:#111827;'>$role</td></tr>
            <tr><td style='padding:6px 0;font-size:12px;color:#9ca3af;'>Event Date</td>
                <td style='padding:6px 0;font-size:13px;font-weight:700;color:#111827;'>$eventDate</td></tr>
            <tr><td style='padding:6px 0;font-size:12px;color:#9ca3af;'>Time</td>
                <td style='padding:6px 0;font-size:13px;font-weight:600;'>$eventTime</td></tr>
            <tr><td style='padding:6px 0;font-size:12px;color:#9ca3af;'>Location</td>
                <td style='padding:6px 0;font-size:13px;font-weight:600;'>$location</td></tr>
            <tr><td style='padding:6px 0;font-size:12px;color:#9ca3af;'>Guests</td>
                <td style='padding:6px 0;font-size:13px;font-weight:600;'>$pax pax</td></tr>
          </table>
        </div>

        <p style='color:#6b7280;font-size:12px;'>Log in to your account to view full details and confirm your availability.</p>
        <a href='$loginUrl' style='display:inline-block;margin-top:8px;background:#30D158;color:white;padding:11px 24px;border-radius:8px;font-weight:700;font-size:13px;text-decoration:none;'>View My Schedule</a>
      </div>
      <div style='background:#f4f1ec;padding:14px;border-radius:0 0 14px 14px;text-align:center;font-size:11px;color:#9ca3af;'>
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
    <div style='font-family:-apple-system,Helvetica Neue,Arial,sans-serif;max-width:560px;margin:0 auto;'>
      <div style='background:$headerBg;padding:28px;border-radius:14px 14px 0 0;text-align:center;'>
        <div style='font-size:32px;'>$emoji</div>
        <h1 style='color:white;margin:8px 0 0;font-size:18px;'>$headline</h1>
      </div>
      <div style='background:#ffffff;padding:28px;border:1px solid #e5e7eb;'>
        <p style='color:#374151;'>Hi <strong>{$staff['name']}</strong>,</p>
        <p style='color:#6b7280;'>$message</p>
      </div>
      <div style='background:#f4f1ec;padding:14px;border-radius:0 0 14px 14px;text-align:center;font-size:11px;color:#9ca3af;'>
        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite
      </div>
    </div>";

    return sendMail($staff['email'], $staff['name'], $subject, $html);
}
