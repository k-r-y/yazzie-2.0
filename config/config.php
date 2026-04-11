<?php
// ============================================================
// Yazzies Catering OMS — Application Configuration
// ============================================================

// Application Constants
define('APP_NAME',    'Yazzies Catering OMS');
define('APP_VERSION', '1.0.0');
define('BASE_URL',    'http://localhost/test');

// Timezone
date_default_timezone_set('Asia/Manila');

// ============================================================
// Database Configuration
// ============================================================
define('DB_HOST',    'localhost');
define('DB_NAME',    'yazzie');
define('DB_USER',    'root');
define('DB_PASS',    '');
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
    if ($isApiRequest) {
        header('Content-Type: application/json');
        http_response_code(503);
        die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]));
    }
    http_response_code(503);
    die('<!DOCTYPE html><html><body style="font-family:sans-serif;text-align:center;padding:60px;"><h2>Database Unavailable</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Run <a href="/test/database/setup.php">/test/database/setup.php</a> to initialize the database.</p></body></html>');
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
        $isDev = (DB_HOST === 'localhost'); // Show detail in local dev only
        echo json_encode([
            'success' => false,
            'message' => $isDev
                ? '[DB ERROR] ' . $e->getMessage() . ' (in ' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : 'An internal server error occurred.',
        ]);
        exit;
    });
}

// ============================================================
// Email Configuration (PHPMailer / Gmail SMTP)
// Update these values when setting up email notifications
// ============================================================
define('MAIL_HOST',     'smtp.gmail.com');
define('MAIL_PORT',     587);
define('MAIL_USERNAME', 'your_email@gmail.com');   // <- Change this
define('MAIL_PASSWORD', 'your_app_password_here'); // <- Change this (Google App Password)
define('MAIL_FROM',     'your_email@gmail.com');   // <- Change this
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_ENABLED',  false); // Set to true once credentials are configured

// ============================================================
// SMS Configuration (Semaphore PH)
// Register free at semaphore.co.ph
// ============================================================
define('SMS_API_KEY',   'your_semaphore_api_key'); // <- Change this
define('SMS_SENDER',    'YAZZIES');                 // Registered sender name (max 11 chars)
define('SMS_ENABLED',   false); // Set to true once API key is configured