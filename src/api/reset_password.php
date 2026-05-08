<?php
/**
 * Reset Password API — Verify OTP and update password
 * PUT /src/api/reset_password.php
 * Body: { "email": "...", "otp": "...", "new_password": "..." }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

// Only PUT/POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}

// Parse body
$input           = json_decode(file_get_contents('php://input'), true);
$email           = trim($input['email'] ?? '');
$otp             = trim($input['otp'] ?? '');
$new_password    = $input['new_password'] ?? '';
$confirm_password = $input['confirm_password'] ?? '';

if (!$email || !$otp || !$new_password || !$confirm_password) {
    jsonResponse(false, 'All fields are required.', [], 422);
}

// Use OTP-specific rate limit (separate from login lockout)
checkOtpRateLimit($pdo, $email);

// Confirm passwords match before hitting the DB
if ($new_password !== $confirm_password) {
    recordOtpAttempt($pdo, $email);
    jsonResponse(false, 'Passwords do not match.', [], 422);
}


try {
    // 1. Query the password_resets table. Verify the OTP matches and expires_at is > NOW()
    $stmt = $pdo->prepare("
        SELECT id FROM password_resets 
        WHERE email = :email AND otp = :otp AND expires_at > NOW()
        LIMIT 1
    ");
    $stmt->execute([':email' => $email, ':otp' => $otp]);
    $resetRequest = $stmt->fetch();

    if (!$resetRequest) {
        recordOtpAttempt($pdo, $email); // count bad OTP guesses
        jsonResponse(false, 'Invalid or expired verification code.', [], 400);
    }

    // 2. Validate the new_password against the system's password policy
    $policyError = validatePasswordPolicy($new_password);
    if ($policyError) {
        jsonResponse(false, $policyError, [], 422);
    }

    // 3. Hash the new password using password_hash(..., PASSWORD_BCRYPT)
    $hashedPassword = password_hash($new_password, PASSWORD_BCRYPT);

    // 4. Update the users table
    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
    $stmt->execute([':password' => $hashedPassword, ':email' => $email]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'Failed to update password. User not found.', [], 404);
    }

    // 5. Delete the used OTP record from password_resets
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = :email");
    $stmt->execute([':email' => $email]);

    // 6. Clear OTP rate-limit attempts on success
    clearOtpAttempts($pdo, $email);

    // 7. Return a JSON success response
    jsonResponse(true, 'Password has been reset successfully. You can now sign in.');

} catch (Exception $e) {
    error_log("[Reset Password API Error] " . $e->getMessage());
    jsonResponse(false, 'An error occurred. Please try again later.', [], 500);
}
