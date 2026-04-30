<?php
// ============================================================
// Yazzies Catering OMS — Application Configuration
// ============================================================

// Application Constants
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
// Database Configuration
// ============================================================
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_NAME',    getenv('DB_NAME') ?: 'yazzie');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── DSN: On XAMPP macOS, CLI uses 'localhost' which triggers
//         Unix socket lookup in the wrong path. Auto-detect the
//         correct socket and use it so both CLI and web work.
function buildDsn(): string {
    $host    = DB_HOST;
    $name    = DB_NAME;
    $charset = DB_CHARSET;

    // Only apply socket override when host is 'localhost' (not an IP)
    if ($host === 'localhost' && php_sapi_name() === 'cli') {
        $knownSockets = [
            '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock', // XAMPP macOS
            '/tmp/mysql.sock',                                       // Homebrew / Linux default
            '/var/run/mysqld/mysqld.sock',                           // Ubuntu / Debian
        ];
        foreach ($knownSockets as $sock) {
            if (file_exists($sock)) {
                return "mysql:unix_socket={$sock};dbname={$name};charset={$charset}";
            }
        }
        // Fallback: force TCP to avoid socket resolution
        return "mysql:host=127.0.0.1;dbname={$name};charset={$charset}";
    }

    return "mysql:host={$host};dbname={$name};charset={$charset}";
}

$dsn = buildDsn();

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
        'boolean' => filter_var($v, FILTER_VALIDATE_BOOLEAN),
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

// ============================================================
// Business Logic Constants (Dynamically loaded)
// ============================================================
define('BUSINESS_NAME',        appSetting('business_name', 'Yazzies Catering Services'));
define('BUSINESS_ADDRESS',     appSetting('business_address', 'Poblacion, Alabel, Sarangani Province'));
define('BUSINESS_PHONE',       appSetting('business_phone', '09XX-XXX-XXXX'));
define('BUSINESS_EMAIL',       appSetting('business_email', 'info@yazzies.com'));

// Payment Account Details
define('GCASH_NAME',           appSetting('gcash_name', 'Yazzies Catering'));
define('GCASH_NO',             appSetting('gcash_no', '09XX-XXX-XXXX'));
define('MAYA_NAME',            appSetting('maya_name', 'Yazzies Catering'));
define('MAYA_NO',              appSetting('maya_no', '09XX-XXX-XXXX'));
define('BANK_NAME',            appSetting('bank_name', 'BPI'));
define('BANK_ACC_NAME',        appSetting('bank_account_name', 'Yazzies Catering Services'));
define('BANK_ACC_NO',          appSetting('bank_account_no', 'XXXX-XXXX-XX'));

define('APP_NAME',             BUSINESS_NAME);
define('MIN_LEAD_TIME_DAYS',   appSettingInt('min_lead_time_days', 1));
define('MIN_PAX',              appSettingInt('min_pax', 50));
define('MAX_PAX',              appSettingInt('max_pax', 300));
define('MIN_DP_PERCENT',       appSettingFloat('standard_dp_percent', 0.30)); 
define('RUSH_DP_PERCENT',      appSettingFloat('rush_dp_percent', 1.00));
define('EXTRA_PAX_RATE',       appSettingFloat('extra_pax_rate', 125.0));
define('EVENT_DURATION_HOURS', appSettingInt('event_duration_hours', 4));
define('OVERTIME_RATE',        appSettingFloat('overtime_rate_per_hour', 200.0));
define('RUSH_THRESHOLD_HOURS', appSettingInt('rush_threshold_hours', 72));
define('OPERATING_HOURS_START', appSetting('operating_hours_start', '07:00'));
define('OPERATING_HOURS_END',   appSetting('operating_hours_end', '23:00'));
define('MEAL_BREAKFAST_START', appSettingInt('meal_breakfast_start', 6));
define('MEAL_LUNCH_START',     appSettingInt('meal_lunch_start', 11));
define('MEAL_DINNER_START',    appSettingInt('meal_dinner_start', 17));

define('TIER_BASE_PRICE',      appSettingFloat('tier_base_price', 5000.0));
define('TIER_PAX_RATE',        appSettingFloat('tier_pax_rate', 100.0));
define('TIER_SNAP_STEP',       appSettingInt('tier_snap_step', 50));
define('CANCEL_FORFEIT_PCT',   appSettingFloat('cancel_forfeiture_percent', 0.50));

define('DEFAULT_MAX_MAIN',     appSettingInt('default_max_main', 5));
define('DEFAULT_MAX_DESSERT',  appSettingInt('default_max_dessert', 1));
define('DEFAULT_MAX_ADDITIONAL', appSettingInt('default_max_additional', 1));
define('EXTRA_MAIN_RATE',      appSettingFloat('extra_main_rate', 50.0));
define('EXTRA_DESSERT_RATE',   appSettingFloat('extra_dessert_rate', 30.0));
define('EXTRA_RICE_RATE',      appSettingFloat('extra_rice_rate', 20.0));

define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Global System Settings
define('MAINTENANCE_MODE', appSetting('maintenance_mode', 0));
define('DEBUG_MODE', appSetting('debug_mode', 0));
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Timezone
$tz = appSetting('system_timezone', 'Asia/Manila');
date_default_timezone_set($tz);

// ── Secure session cookie (CSRF protection) ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,    // Set to true when HTTPS is enabled
        'httponly' => true,     // Prevent JS access to session cookie
        'samesite' => 'Lax',    // CSRF protection (Lax is better for local dev with fetch)
    ]);
}

// ============================================================
// Security Helpers (XSS escape, password policy)
// ============================================================
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/formatters.php';

// ── Global exception handler for API routes ──────────────────────
if (strpos($_SERVER['PHP_SELF'] ?? '', '/api/') !== false) {
    set_exception_handler(function (Throwable $e) {
        if (ob_get_level()) ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        $isDev = (APP_ENV === 'development' || DEBUG_MODE);
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
// ============================================================
$dbSmtpHost = appSetting('smtp_host');
$dbSmtpPort = appSetting('smtp_port');
$dbSmtpUser = appSetting('smtp_user');
$dbSmtpPass = appSetting('smtp_pass');
$dbSmtpSecure = appSetting('smtp_secure', 'tls');
$dbSmtpFrom = appSetting('smtp_from');
$dbSmtpFromName = appSetting('smtp_from_name');
$dbMailEnabled = appSetting('mail_enabled');

define('MAIL_HOST',     !empty($dbSmtpHost) ? $dbSmtpHost : (getenv('MAIL_HOST') ?: 'smtp.gmail.com'));
define('MAIL_PORT',     !empty($dbSmtpPort) ? (int)$dbSmtpPort : (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME', !empty($dbSmtpUser) ? $dbSmtpUser : (getenv('MAIL_USERNAME') ?: 'yazziecateringservices@gmail.com'));
define('MAIL_PASSWORD', !empty($dbSmtpPass) ? $dbSmtpPass : (getenv('MAIL_PASSWORD') ?: '')); 
define('MAIL_SECURE',   !empty($dbSmtpSecure) ? $dbSmtpSecure : 'tls');
define('MAIL_FROM',     !empty($dbSmtpFrom) ? $dbSmtpFrom : (getenv('MAIL_FROM') ?: 'yazziecateringservices@gmail.com'));
define('MAIL_FROM_NAME', !empty($dbSmtpFromName) ? $dbSmtpFromName : (getenv('MAIL_FROM_NAME') ?: 'Yazzies Catering Services'));
define('MAIL_ENABLED',  $dbMailEnabled !== null ? filter_var($dbMailEnabled, FILTER_VALIDATE_BOOLEAN) : filter_var(getenv('MAIL_ENABLED'), FILTER_VALIDATE_BOOLEAN));

// ============================================================
// SMS Configuration (Semaphore PH)
// ============================================================
define('SMS_API_KEY',   appSetting('sms_api_key', getenv('SMS_API_KEY') ?: ''));
define('SMS_SENDER',    getenv('SMS_SENDER') ?: 'YAZZIES');
define('SMS_ENABLED',   filter_var(getenv('SMS_ENABLED'), FILTER_VALIDATE_BOOLEAN));