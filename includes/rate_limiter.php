<?php
/**
 * Rate Limiter — Prevents brute-force login attacks.
 *
 * Uses the `login_attempts` database table to track failed attempts per IP.
 * Blocks an IP for the configured lockout minutes after exceeding max attempts.
 *
 * Usage (in auth.php API):
 *   checkLoginRateLimit($pdo);           // Before password check
 *   recordFailedLogin($pdo);             // After failed attempt
 *   clearLoginAttempts($pdo);            // After successful login
 */

// Get MAX_LOGIN_ATTEMPTS from settings table, default to 5
$maxLoginAttemptsCache = null;
function getMaxLoginAttempts(PDO $pdo): int
{
    global $maxLoginAttemptsCache;
    if ($maxLoginAttemptsCache !== null) return $maxLoginAttemptsCache;
    
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'max_login_attempts' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        $maxLoginAttemptsCache = $result ? (int)$result['value'] : 5;
    } catch (\Throwable $e) {
        error_log('[RateLimiter] Failed to fetch max_login_attempts: ' . $e->getMessage());
        $maxLoginAttemptsCache = 5;
    }
    return $maxLoginAttemptsCache;
}

// Get LOCKOUT_DURATION_MINUTES from settings table, default to 15
$lockoutDurationCache = null;
function getLockoutDurationMinutes(PDO $pdo): int
{
    global $lockoutDurationCache;
    if ($lockoutDurationCache !== null) return $lockoutDurationCache;

    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'lockout_duration_minutes' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        $lockoutDurationCache = $result ? (int)$result['value'] : 15;
    } catch (\Throwable $e) {
        error_log('[RateLimiter] Failed to fetch lockout_duration_minutes: ' . $e->getMessage());
        $lockoutDurationCache = 15;
    }
    return $lockoutDurationCache;
}

function getClientIp(): string
{
    // Trust X-Forwarded-For only behind a known reverse proxy
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if the current IP or email is rate-limited.
 * Returns HTTP 429 if too many attempts.
 */
function checkLoginRateLimit(PDO $pdo, ?string $email = null): void
{
    $ip             = getClientIp();
    $maxAttempts    = getMaxLoginAttempts($pdo);
    $lockoutMinutes = getLockoutDurationMinutes($pdo);
    $windowStart    = date('Y-m-d H:i:s', strtotime('-' . $lockoutMinutes . ' minutes'));

    try {
        $sql = "SELECT COUNT(*) AS attempts FROM login_attempts WHERE (ip_address = :ip";
        $params = [':ip' => $ip, ':window' => $windowStart];
        if (!empty($email)) {
            $sql .= " OR email = :email";
            $params[':email'] = $email;
        }
        $sql .= ") AND attempted_at >= :window";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        if ($row && (int)$row['attempts'] >= $maxAttempts) {
            header('Content-Type: application/json');
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many login attempts. Please try again in ' . $lockoutMinutes . ' minutes.',
            ]);
            exit;
        }
    } catch (\Throwable $e) {
        // If login_attempts table doesn't exist yet, skip silently
        error_log('[RateLimiter] Check failed: ' . $e->getMessage());
    }
}

/**
 * Record a failed login attempt for the current IP.
 */
function recordFailedLogin(PDO $pdo, ?string $email = null): void
{
    $ip = getClientIp();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (ip_address, email, attempted_at)
            VALUES (:ip, :email, NOW())
        ");
        $stmt->execute([':ip' => $ip, ':email' => $email]);
    } catch (\Throwable $e) {
        error_log('[RateLimiter] Record failed: ' . $e->getMessage());
    }
}

/**
 * Clear login attempts for the current IP after a successful login.
 */
function clearLoginAttempts(PDO $pdo): void
{
    $ip = getClientIp();
    try {
        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = :ip")
            ->execute([':ip' => $ip]);
    } catch (\Throwable $e) {
        error_log('[RateLimiter] Clear failed: ' . $e->getMessage());
    }
}

// ============================================================
// OTP Rate Limiter — separate table, separate thresholds
// Tracks failed OTP verification attempts per email + IP.
//
// Thresholds (hardcoded, not configurable via admin settings):
//   Max attempts : 5  per email OR IP
//   Window       : 10 minutes
//
// Usage:
//   checkOtpRateLimit($pdo, $email);    // Before verifying OTP
//   recordOtpAttempt($pdo, $email);     // After a failed attempt
//   clearOtpAttempts($pdo, $email);     // After a successful verify
// ============================================================

define('OTP_MAX_ATTEMPTS',    5);
define('OTP_WINDOW_MINUTES', 10);

/**
 * Block the request if the email or IP has exceeded OTP attempt limits.
 * Returns HTTP 429 on violation.
 */
function checkOtpRateLimit(PDO $pdo, string $email): void
{
    $ip          = getClientIp();
    $windowStart = date('Y-m-d H:i:s', strtotime('-' . OTP_WINDOW_MINUTES . ' minutes'));

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS attempts
            FROM otp_attempts
            WHERE (email = :email OR ip_address = :ip)
              AND attempted_at >= :window
        ");
        $stmt->execute([':email' => $email, ':ip' => $ip, ':window' => $windowStart]);
        $row = $stmt->fetch();

        if ($row && (int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
            header('Content-Type: application/json');
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many incorrect code attempts. Please wait ' . OTP_WINDOW_MINUTES . ' minutes before trying again.',
            ]);
            exit;
        }
    } catch (\Throwable $e) {
        error_log('[OTP RateLimiter] Check failed: ' . $e->getMessage());
    }
}

/**
 * Record a failed OTP attempt for the current email + IP.
 */
function recordOtpAttempt(PDO $pdo, string $email): void
{
    $ip = getClientIp();
    try {
        $pdo->prepare("
            INSERT INTO otp_attempts (email, ip_address, attempted_at)
            VALUES (:email, :ip, NOW())
        ")->execute([':email' => $email, ':ip' => $ip]);
    } catch (\Throwable $e) {
        error_log('[OTP RateLimiter] Record failed: ' . $e->getMessage());
    }
}

/**
 * Clear OTP attempts for this email after a successful verification.
 */
function clearOtpAttempts(PDO $pdo, string $email): void
{
    $ip = getClientIp();
    try {
        $pdo->prepare("
            DELETE FROM otp_attempts
            WHERE email = :email OR ip_address = :ip
        ")->execute([':email' => $email, ':ip' => $ip]);
    } catch (\Throwable $e) {
        error_log('[OTP RateLimiter] Clear failed: ' . $e->getMessage());
    }
}
