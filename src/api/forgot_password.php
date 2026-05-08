<?php
/**
 * Forgot Password API — Generate and send OTP
 * POST /src/api/forgot_password.php
 * Body: { "email": "..." }
 *
 * Security: Response time is padded to a fixed minimum so an attacker
 * cannot enumerate valid emails by measuring timing differences.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

// Record start time — used to normalise response duration at the end.
$requestStart = microtime(true);

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}

// Parse JSON body
$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.', [], 422);
}

// Rate-limit OTP requests (re-uses the login_attempts table / same thresholds)
checkLoginRateLimit($pdo, $email);
recordFailedLogin($pdo, $email); // Each OTP request counts as an attempt

/**
 * Pad the response to a fixed minimum duration (in seconds).
 * Prevents timing side-channels that reveal whether an email exists.
 */
function padResponseTime(float $start, float $minSeconds = 1.5): void
{
    $elapsed = microtime(true) - $start;
    $remaining = $minSeconds - $elapsed;
    if ($remaining > 0) {
        usleep((int)($remaining * 1_000_000));
    }
}

try {
    // 1. Check if the email exists in the users table
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    // 2. If user exists, process OTP. If not, we still return success (Zero-Trust)
    if ($user) {
        // Generate a secure 6-digit integer
        $otp = random_int(100000, 999999);
        $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));

        // 3. Upsert: delete old OTP then insert fresh one
        $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
        $stmt->execute([':email' => $email]);

        $stmt = $pdo->prepare("
            INSERT INTO password_resets (email, otp, expires_at)
            VALUES (:email, :otp, :expires)
        ");
        $stmt->execute([
            ':email'   => $email,
            ':otp'     => $otp,
            ':expires' => $expires_at
        ]);

        // 4. Send the OTP to the user
        $subject = "Your Verification Code — " . APP_NAME;
        $content = "
            <h2 style='margin: 0 0 12px; font-size: 22px; font-weight: 800; color: #1C1C1E; letter-spacing: -0.8px;'>Password Reset Request</h2>
            <p style='margin: 0 0 32px; font-size: 15px; color: rgba(60, 60, 67, 0.6); line-height: 1.6;'>Hello, <strong>{$user['name']}</strong>. Use the verification code below to reset your password. This code will expire in 15 minutes.</p>
            
            <div style='background-color: #F8F8FA; border-radius: 24px; padding: 40px; margin-bottom: 32px; border: 1px solid rgba(60, 60, 67, 0.08); text-align: center;'>
                <div style='font-size: 11px; color: rgba(60, 60, 67, 0.4); font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 16px;'>Verification Code</div>
                <div style='font-size: 48px; font-weight: 800; color: #30D158; letter-spacing: 12px; font-family: monospace;'>$otp</div>
            </div>

            <p style='margin: 0; font-size: 13px; color: rgba(60, 60, 67, 0.4); text-align: center;'>If you did not request this, you can safely ignore this email.</p>
        ";

        $html = renderEmailTemplate("Verification Code", "🔐", $content, "#30D158", "Your password reset code is $otp");
        sendMailImmediate($email, $user['name'], $subject, $html);
    }

    // 5. Normalise response time — both paths must feel identical to the caller
    padResponseTime($requestStart, 1.5);

    // 6. Return the same generic response regardless of whether the email existed
    jsonResponse(true, 'If your email is registered, you will receive a verification code shortly.');

} catch (Exception $e) {
    error_log("[Forgot Password API Error] " . $e->getMessage());
    padResponseTime($requestStart, 1.5); // pad even on error to avoid leaking info
    jsonResponse(false, 'An error occurred. Please try again later.', [], 500);
}
