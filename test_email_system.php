<?php
/**
 * SMTP System Diagnostic Tool
 * Visit this page in your browser to verify email functionality.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/mailer.php';

// Check if user is logged in (security)
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Access Denied. Please log in to the admin panel first.");
}

$testEmail = $_SESSION['user_email'] ?? BUSINESS_EMAIL;
$status = "";
$details = "";

if (isset($_POST['run_test'])) {
    ob_start();
    // Enable debug mode for this test
    if (defined('DEBUG_MODE')) {
        // Force debug on for this specific execution
        $mail_debug = true;
    }
    
    $subject = "System Diagnostic: SMTP Test — " . date('Y-m-d H:i:s');
    $body = "<h1>SMTP Test Successful</h1><p>This is a diagnostic email sent from your Catering Management System to verify that your mail server settings are correct.</p><p>Sent at: " . date('F j, Y, g:i a') . "</p>";
    
    $result = sendMailImmediate($testEmail, "Admin Tester", $subject, $body);
    $debug_output = ob_get_clean();
    
    if ($result) {
        $status = "success";
        $message = "Test email sent successfully to $testEmail!";
    } else {
        $status = "error";
        $message = "Failed to send test email. Check the details below.";
    }
    $details = $debug_output;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Diagnostic | Yazzies OMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #30D158; --error: #FF3B30; --bg: #F2F2F7; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: #1C1C1E; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); width: 100%; max-width: 500px; text-align: center; }
        .icon { font-size: 48px; margin-bottom: 20px; }
        h1 { margin: 0 0 10px; font-weight: 800; letter-spacing: -1px; }
        p { color: #8E8E93; margin-bottom: 30px; }
        .btn { background: var(--primary); color: white; border: none; padding: 16px 32px; border-radius: 12px; font-weight: 600; cursor: pointer; transition: transform 0.2s; font-size: 16px; }
        .btn:hover { transform: scale(1.02); }
        .status { margin-top: 20px; padding: 15px; border-radius: 12px; font-weight: 600; }
        .status.success { background: #E8F9EE; color: var(--primary); border: 1px solid rgba(48,209,88,0.2); }
        .status.error { background: #FFF5F5; color: var(--error); border: 1px solid rgba(255,59,48,0.2); }
        pre { text-align: left; background: #1C1C1E; color: #30D158; padding: 20px; border-radius: 12px; font-size: 12px; overflow-x: auto; margin-top: 20px; max-height: 300px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">📧</div>
        <h1>SMTP Diagnostic</h1>
        <p>Test your mail settings and view real-time logs.</p>
        
        <form method="POST">
            <button type="submit" name="run_test" class="btn">Send Test Email</button>
        </form>

        <?php if ($status): ?>
            <div class="status <?= $status ?>">
                <?= $message ?>
            </div>
            <?php if ($details): ?>
                <pre><?= htmlspecialchars($details) ?></pre>
            <?php endif; ?>
        <?php endif; ?>

        <div style="margin-top: 30px; font-size: 12px; color: #8E8E93;">
            Recipient: <strong><?= htmlspecialchars($testEmail) ?></strong><br>
            Mailer Status: <strong><?= MAIL_ENABLED ? 'ENABLED' : 'DISABLED' ?></strong>
        </div>
    </div>
</body>
</html>
