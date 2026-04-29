<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/mailer.php';

$to = 'kry@gmail.com'; // User's email from conversation context or just a dummy
$subject = 'Test Email Connection';
$body = '<h1>Test</h1><p>If you see this, SMTP is working.</p>';

echo "Attempting to send email to $to...\n";
$res = sendMailImmediate($to, 'Test User', $subject, $body);

if ($res) {
    echo "SUCCESS: Email sent!\n";
} else {
    echo "FAILURE: Check error logs.\n";
    // Check if we can find the error log
    $log = '/Applications/XAMPP/xamppfiles/logs/php_error_log';
    if (file_exists($log)) {
        echo "Last 5 lines of error log:\n";
        passthru("tail -n 5 $log");
    }
}
