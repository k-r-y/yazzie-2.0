<?php
/**
 * Auth API — Login endpoint
 * POST /src/api/auth.php
 * Body: { "email": "...", "password": "..." }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';
require_once __DIR__ . '/../../includes/audit.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}


// Parse JSON body
$input    = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email']    ?? '');
$password = trim($input['password'] ?? '');

// ── Rate Limiting: block brute-force attacks ──
checkLoginRateLimit($pdo, $email);

// Basic validation
if (!$email || !$password) {
    jsonResponse(false, 'Email and password are required.', [], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Please enter a valid email address.', [], 422);
}

// Fetch user by email
$stmt = $pdo->prepare("
    SELECT id, name, email, password, role, phone, is_active
    FROM users
    WHERE email = :email
    LIMIT 1
");
$stmt->execute([':email' => $email]);
$user = $stmt->fetch();

// Verify user and password — generic message to prevent enumeration
if (!$user || !password_verify($password, $user['password'])) {
    recordFailedLogin($pdo, $email);
    jsonResponse(false, 'Invalid email or password. Please try again.', [], 401);
}

if (!$user['is_active']) {
    recordFailedLogin($pdo, $email);
    jsonResponse(false, 'Your account has been deactivated. Please contact the Administrator.', [], 403);
}

// ── Check if debug mode is enabled (block non-admin logins) ──
if (defined('DEBUG_MODE') && (int)DEBUG_MODE === 1 && $user['role'] !== 'admin') {
    jsonResponse(false, 'System is currently in debug mode. Login is temporarily unavailable. Please try again later.', [], 503);
}

// ── Successful login: clear rate limit counters ──
clearLoginAttempts($pdo);

// Create session
startSession();
session_regenerate_id(true);
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Rotate CSRF token

$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];
$_SESSION['phone']   = $user['phone'];

// ── Log the authentication ──
auditLog($pdo, 'login', 'user', $user['id']);

// Determine redirect URL
$redirectMap = [
    'admin'       => BASE_URL . '/views/admin/dashboard.php',
    'frontdesk'   => BASE_URL . '/views/frontdesk/dashboard.php',
    'staff'       => BASE_URL . '/views/staff/dashboard.php',
];

$redirect = $redirectMap[$user['role']] ?? BASE_URL . '/index.php';

// Ensure session is written and closed before sending response
session_write_close();

jsonResponse(true, 'Login successful.', [
    'redirect' => $redirect,
    'user'     => [
        'id'   => $user['id'],
        'name' => $user['name'],
        'role' => $user['role'],
    ],
]);
