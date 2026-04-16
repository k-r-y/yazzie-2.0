<?php
// ============================================================
// Yazzies Catering OMS — Application Configuration
// ============================================================

// Application Constants
define('APP_NAME',    'Yazzies Catering OMS');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/test');

// ============================================================
// Environment Variable Loader
// ============================================================
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"");
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
loadEnv(__DIR__ . '/../.env');

// ============================================================
// Business Logic Constants
// Change these values here ONLY — they propagate everywhere.
// ============================================================
define('MIN_LEAD_TIME_DAYS', 3);
define('MIN_PAX',            50);   // Minimum guests per booking
define('MAX_PAX',            300);  // Maximum guests per booking
define('MIN_DP_PERCENT',     0.50); // Minimum downpayment (50%)
define('APP_ENV', getenv('APP_ENV') ?: 'development'); // Set APP_ENV=production on server

// Timezone
date_default_timezone_set('Asia/Manila');

// ── Secure session cookie (CSRF protection) ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,    // Set to true when HTTPS is enabled
        'httponly' => true,     // Prevent JS access to session cookie
        'samesite' => 'Strict', // CSRF protection
    ]);
}

// ============================================================
// Database Configuration
// ============================================================
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME') ?: 'yazzie');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    $isApiRequest = (strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false);
    if (php_sapi_name() === 'cli') {
        die("Database connection failed: " . $e->getMessage() . "\n");
    }
    if ($isApiRequest) {
        header('Content-Type: application/json');
        http_response_code(503);
        die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
    }
    http_response_code(503);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px;"><h2>Database Unavailable</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Run <a href="/test/database/setup.php">/test/database/setup.php</a> to initialize the database.</p></body></html>');
}

// ============================================================
// Settings (dynamic business rules from DB)
// ============================================================
/**
 * Read application settings from `settings` table (if present).
 * Falls back to provided default when table/key is missing.
 *
 * NOTE: This is intentionally lightweight for this codebase (no framework).
 */
function appSetting(string $key, mixed $default = null): mixed
{
    global $pdo;
    static $cache = null;

    if ($cache === null) {
        $cache = [];
        try {
            // If the table doesn't exist yet, this will throw and we fallback to defaults.
            $rows = $pdo->query("SELECT `key`, `value`, `type` FROM settings")->fetchAll();
            foreach ($rows as $r) {
                $k = (string)($r['key'] ?? '');
                if ($k === '') continue;
                $cache[$k] = ['value' => $r['value'] ?? null, 'type' => $r['type'] ?? 'string'];
            }
        } catch (Throwable $e) {
            // ignore: defaults will be used
        }
    }

    if (!array_key_exists($key, $cache)) return $default;
    $v = $cache[$key]['value'];
    $t = $cache[$key]['type'];

    return match ($t) {
        'int'  => (int)$v,
        'bool' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
        'json' => (json_decode((string)$v, true) ?: $default),
        default => $v,
    };
}

function appSettingInt(string $key, int $default): int {
    $v = appSetting($key, $default);
    return is_numeric($v) ? (int)$v : $default;
}

function appSettingFloat(string $key, float $default): float {
    $v = appSetting($key, $default);
    return is_numeric($v) ? (float)$v : $default;
}

// ── Global exception handler for API routes ──────────────────────
// Catches any unhandled PDOException (e.g. missing table) and returns
// a JSON error instead of a blank HTTP 500 page.
if (strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false) {
    set_exception_handler(function (Throwable $e) {
        // Clear any partial output
        if (ob_get_level()) ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        $isDev = (APP_ENV === 'development'); // Controlled by APP_ENV env var
        echo json_encode([
            'success' => false,
            'message' => $isDev
                ? '[ERROR] ' . $e->getMessage() . ' (in ' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : 'An internal server error occurred. Please contact the administrator.',
        ]);
        exit;
    });
}

// ============================================================
// Email Configuration (PHPMailer / Gmail SMTP)
// Update these values when setting up email notifications
// ============================================================
define('MAIL_HOST',     getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT',     getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'yazziecateringservices@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: ''); // (Google App Password)
define('MAIL_FROM',     getenv('MAIL_FROM') ?: 'yazziecateringservices@gmail.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Yazzies Catering Services');
define('MAIL_ENABLED',  filter_var(getenv('MAIL_ENABLED'), FILTER_VALIDATE_BOOLEAN)); // True if enabled

// ============================================================
// SMS Configuration (Semaphore PH)
// Register free at semaphore.co.ph
// ============================================================
define('SMS_API_KEY',   getenv('SMS_API_KEY') ?: 'your_semaphore_api_key');
define('SMS_SENDER',    getenv('SMS_SENDER') ?: 'YAZZIES'); // Registered sender name (max 11 chars)
define('SMS_ENABLED',   filter_var(getenv('SMS_ENABLED'), FILTER_VALIDATE_BOOLEAN)); // True if enabled