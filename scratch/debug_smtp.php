<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

echo "--- SMTP DEBUG SESSION ---\n";
echo "Host: " . MAIL_HOST . "\n";
echo "Port: " . MAIL_PORT . "\n";
echo "User: " . MAIL_USERNAME . "\n";
echo "Pass: " . substr(MAIL_PASSWORD, 0, 2) . str_repeat('*', max(0, strlen(MAIL_PASSWORD)-4)) . substr(MAIL_PASSWORD, -2) . " (Length: " . strlen(MAIL_PASSWORD) . ")\n";
echo "Secure: " . MAIL_SECURE . "\n";
echo "Enabled: " . (MAIL_ENABLED ? 'Yes' : 'No') . "\n";

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
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
    $mail->addAddress(MAIL_USERNAME);
    $mail->Subject = 'Debug Test';
    $mail->Body    = 'Test body';
    
    echo "\nStarting send...\n";
    $mail->send();
    echo "\nSUCCESS!\n";
} catch (Exception $e) {
    echo "\nMAILER EXCEPTION: " . $e->getMessage() . "\n";
}
