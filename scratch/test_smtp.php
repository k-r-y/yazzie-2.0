<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

echo "Attempting to send test email to yazziecateringservices@gmail.com...\n";
echo "Host: " . MAIL_HOST . "\n";
echo "Port: " . MAIL_PORT . "\n";
echo "User: " . MAIL_USERNAME . "\n";
echo "Enabled: " . (MAIL_ENABLED ? 'Yes' : 'No') . "\n";

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output
    $mail->Host       = MAIL_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = MAIL_USERNAME;
    $mail->Password   = MAIL_PASSWORD;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = MAIL_PORT;

    $mail->setFrom(MAIL_USERNAME, 'SMTP TEST');
    $mail->addAddress(MAIL_USERNAME, 'Test Recipient');
    $mail->isHTML(true);
    $mail->Subject = 'SMTP Test Connection';
    $mail->Body    = 'This is a test to verify SMTP settings.';

    if ($mail->send()) {
        echo "\nSUCCESS: Email sent!\n";
    } else {
        echo "\nFAILURE: Email not sent.\n";
    }
} catch (Exception $e) {
    echo "\nEXCEPTION: " . $e->getMessage() . "\n";
}
