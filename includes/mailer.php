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
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        
        $sec = strtolower(MAIL_SECURE);
        if ($sec === 'ssl' || (int)MAIL_PORT === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($sec === 'tls' || (int)MAIL_PORT === 587) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAuth = false;
        }
        
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
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
    
    // Project Standard Tokens (Strict)
    $sysGreen      = '#30D158';
    $sysGreenDark  = '#25A244';
    $bgPrimary     = '#F2F2F7';
    $labelPrimary  = 'rgba(0, 0, 0, 0.88)';
    $labelSecondary = 'rgba(60, 60, 67, 0.60)';
    $labelTertiary  = 'rgba(60, 60, 67, 0.30)';

    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Inter', -apple-system, sans-serif !important; margin: 0; padding: 0; background-color: $bgPrimary; }
            .btn-primary {
                display: inline-block;
                background: $sysGreen;
                color: #ffffff !important;
                padding: 12px 24px;
                font-size: 14px;
                font-weight: 600;
                text-decoration: none;
                border-radius: 10px;
                box-shadow: 0 4px 12px rgba(48, 209, 88, 0.35), inset 0 1px 0 rgba(255,255,255,0.2);
            }
        </style>
    </head>
    <body>
        <div style='display:none;font-size:1px;color:#ffffff;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;'>$preheader</div>
        <div style='padding: 50px 20px;'>
            <div style='max-width: 560px; margin: 0 auto; border-radius: 28px; overflow: hidden; background-color: #ffffff; box-shadow: 0 4px 16px rgba(0,0,0,0.08), 0 0 0 0.5px rgba(60,60,67,0.08);'>
                
                <!-- BRAND HEADER -->
                <div style='background: linear-gradient(145deg, $sysGreen, $sysGreenDark); padding: 55px 30px; text-align: center; color: #ffffff;'>
                    <div style='width: 64px; height: 64px; background: rgba(255,255,255,0.25); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 34px; margin-bottom: 24px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.3); line-height: 64px;'>
                        <span style='vertical-align: middle;'>$emoji</span>
                    </div>
                    <h1 style='margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -1px; color: #ffffff;'>$title</h1>
                    <div style='margin-top: 10px; opacity: 0.9;'>
                        <span style='font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 2px;'>YAZZIES</span>
                        <div style='font-size: 10px; font-weight: 500; letter-spacing: 0.5px; margin-top: 2px;'>CATERING SERVICES</div>
                    </div>
                </div>

                <!-- CONTENT -->
                <div style='padding: 45px 40px; font-size: 15px; line-height: 1.6; color: $labelPrimary;'>
                    $content
                </div>

                <!-- FOOTER -->
                <div style='padding: 35px 40px; background-color: rgba(60,60,67,0.02); border-top: 0.5px solid rgba(60,60,67,0.08); text-align: center;'>
                    <p style='margin: 0; font-size: 12px; color: $labelSecondary; font-weight: 500; line-height: 2;'>
                        <strong>$appName</strong> &middot; Professional Catering<br>
                        Barangay St. Peter, Dasmariñas City, Cavite<br>
                        &copy; " . date('Y') . " &middot; All rights reserved.
                    </p>
                </div>
            </div>
            
            <div style='max-width: 560px; margin: 24px auto 0; text-align: center;'>
                <p style='font-size: 11px; color: $labelTertiary;'>This is an automated system notification from the Yazzies OMS.</p>
            </div>
        </div>
    </body>
    </html>";
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
        <h2 style='margin: 0 0 12px; font-size: 21px; font-weight: 700; color: #1C1C1E; letter-spacing: -0.8px;'>Hi, {$booking['client_name']}!</h2>
        <p style='margin: 0 0 36px; font-size: 15px; color: rgba(60, 60, 67, 0.7); line-height: 1.6;'>Your event is officially secured. We are excited to deliver a professional catering experience for you.</p>

        <div style='background-color: #F8F8FA; border-radius: 20px; padding: 28px; margin-bottom: 36px; border: 0.5px solid rgba(60, 60, 67, 0.08);'>
            <table style='width: 100%; border-collapse: collapse;'>
                <tr>
                    <td style='padding: 10px 0; font-size: 11px; color: rgba(60, 60, 67, 0.5); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Event Date</td>
                    <td style='padding: 10px 0; text-align: right; font-weight: 600; color: #1C1C1E;'>$eventDate</td>
                </tr>
                <tr>
                    <td style='padding: 10px 0; font-size: 11px; color: rgba(60, 60, 67, 0.5); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Package</td>
                    <td style='padding: 10px 0; text-align: right; font-weight: 600; color: #1C1C1E;'>{$booking['menu_name']}</td>
                </tr>
                <tr>
                    <td style='padding: 10px 0; font-size: 11px; color: rgba(60, 60, 67, 0.5); font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;'>Guests</td>
                    <td style='padding: 10px 0; text-align: right; font-weight: 700; color: #30D158;'>{$booking['pax_count']} pax</td>
                </tr>
                
                <tr><td colspan='2' style='border-top: 0.5px solid rgba(60, 60, 67, 0.1); padding-top: 18px; margin-top: 18px;'></td></tr>
                
                <tr>
                    <td style='padding: 8px 0; font-size: 11px; color: rgba(60, 60, 67, 0.5); font-weight: 700; text-transform: uppercase;'>Total Cost</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: 700; color: #1C1C1E; font-size: 18px;'>₱" . number_format($booking['total_cost'], 2) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-size: 11px; color: rgba(60, 60, 67, 0.5); font-weight: 700; text-transform: uppercase;'>Amount Paid</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: 700; color: #30D158;'>₱" . number_format($booking['amount_paid'], 2) . "</td>
                </tr>
                <tr>
                    <td style='padding: 8px 0; font-size: 11px; color: rgba(60, 60, 67, 0.5); font-weight: 700; text-transform: uppercase;'>Remaining</td>
                    <td style='padding: 8px 0; text-align: right; font-weight: 800; color: #FF3B30;'>₱" . number_format($booking['total_cost'] - $booking['amount_paid'], 2) . "</td>
                </tr>
            </table>
        </div>

        <p style='margin: 0; font-size: 14px; font-weight: 600; color: #30D158; text-align: center; text-transform: uppercase; letter-spacing: 1px;'>See you soon! 🎉</p>
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
        <h2 style='margin: 0 0 12px; font-size: 21px; font-weight: 700; color: #1C1C1E; letter-spacing: -0.8px;'>Hi, {$booking['client_name']}!</h2>
        <p style='margin: 0 0 36px; font-size: 15px; color: rgba(60, 60, 67, 0.7); line-height: 1.6;'>We've successfully processed your payment. Thank you for your continued trust in Yazzies Catering.</p>

        <div style='background-color: #F8F8FA; border-radius: 20px; padding: 36px; margin-bottom: 36px; text-align: center; border: 0.5px solid rgba(60, 60, 67, 0.08);'>
            <p style='margin: 0 0 10px; font-size: 11px; color: rgba(60, 60, 67, 0.5); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;'>Payment via $method</p>
            <div style='font-size: 44px; font-weight: 800; color: #30D158; letter-spacing: -2px;'>₱" . number_format($paymentAmount, 2) . "</div>
            <div style='margin-top: 28px; padding-top: 24px; border-top: 0.5px solid rgba(60, 60, 67, 0.1);'>
                <p style='margin: 0; font-size: 14px; color: rgba(60, 60, 67, 0.5);'>New Remaining Balance: <strong style='color: #1C1C1E;'>₱" . number_format($balance, 2) . "</strong></p>
            </div>
        </div>

        <p style='margin: 0; font-size: 14px; font-weight: 700; color: #30D158; text-align: center; text-transform: uppercase; letter-spacing: 1px;'>Thank you! ✨</p>
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

        <div style='background: #F2F2F7; border-radius: 16px; padding: 30px; margin-bottom: 30px; text-align: center; border: 1px solid rgba(48,209,88,0.2);'>
            <p style='margin: 0 0 8px; font-size: 13px; color: #30D158; font-weight: 700; text-transform: uppercase;'>Remaining Balance for $eventDate</p>
            <div style='font-size: 36px; font-weight: 800; color: #1C1C1E; letter-spacing: -1.5px;'>₱" . number_format($balance, 2) . "</div>
        </div>

        <p style='margin: 0 0 8px; font-size: 14px; color: rgba(60,60,67,0.6); line-height: 1.5;'>If you've already made this payment, please disregard this note. We look forward to serving you!</p>
        <p style='margin: 0; font-size: 15px; font-weight: 700; color: #30D158;'>Thank you! 🍽️</p>
    ";

    $html = renderEmailTemplate("Balance Reminder", "🔔", $content, "#30D158", "Friendly reminder: your remaining balance for $eventDate is ₱" . number_format($balance, 2) . ".");
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

    $themeColor = '#30D158'; // Standardized to system green
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

    $themeColor = '#30D158'; // Standardized to system green
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
