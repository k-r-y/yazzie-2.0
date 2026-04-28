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

define('LOGIN_LOCKOUT_MINUTES', 15);

function getClientIp(): string
{
    // Trust X-Forwarded-For only behind a known reverse proxy
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Check if the current IP is rate-limited.
 * Returns HTTP 429 if too many attempts.
 */
function checkLoginRateLimit(PDO $pdo): void
{
    $ip = getClientIp();
    $maxAttempts = getMaxLoginAttempts($pdo);
    $windowStart = date('Y-m-d H:i:s', strtotime('-' . LOGIN_LOCKOUT_MINUTES . ' minutes'));

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) AS attempts
            FROM login_attempts
            WHERE ip_address = :ip
              AND attempted_at >= :window
        ");
        $stmt->execute([':ip' => $ip, ':window' => $windowStart]);
        $row = $stmt->fetch();

        if ($row && (int)$row['attempts'] >= $maxAttempts) {
            header('Content-Type: application/json');
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'message' => 'Too many login attempts. Please try again in ' . LOGIN_LOCKOUT_MINUTES . ' minutes.',
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
