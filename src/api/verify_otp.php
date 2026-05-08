<?php
/**
 * Verify OTP API — Validate the code without consuming it
 * POST /src/api/verify_otp.php
 * Body: { "email": "...", "otp": "..." }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$otp   = trim($input['otp']   ?? '');

if (!$email || !$otp) {
    jsonResponse(false, 'Email and OTP are required.', [], 422);
}

// OTP-specific rate limit — separate from login lockout
checkOtpRateLimit($pdo, $email);

try {
    $stmt = $pdo->prepare("
        SELECT id FROM password_resets
        WHERE email = :email AND otp = :otp AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':email' => $email, ':otp' => $otp]);

    if (!$stmt->fetch()) {
        recordOtpAttempt($pdo, $email); // penalise bad OTP guesses
        jsonResponse(false, 'Invalid or expired verification code.', [], 400);
    }

    // Code is valid — clear OTP attempts and advance to Step 3
    clearOtpAttempts($pdo, $email);
    jsonResponse(true, 'Code verified.');

} catch (Exception $e) {
    error_log('[Verify OTP Error] ' . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again.', [], 500);
}
