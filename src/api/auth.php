<?php
/**
 * Auth API — Login endpoint
 * POST /src/api/auth.php
 * Body: { "email": "...", "password": "..." }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}

// Parse JSON body
$input    = json_decode(file_get_contents('php://input'), true);
$email    = trim($input['email']    ?? '');
$password = trim($input['password'] ?? '');

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

// Verify user and password
if (!$user || !password_verify($password, $user['password'])) {
    jsonResponse(false, 'Invalid email or password. Please try again.', [], 401);
}

if (!$user['is_active']) {
    jsonResponse(false, 'Your account has been deactivated. Please contact the Administrator.', [], 403);
}

// Create session
startSession();
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['name']    = $user['name'];
$_SESSION['email']   = $user['email'];
$_SESSION['role']    = $user['role'];
$_SESSION['phone']   = $user['phone'];

// Determine redirect URL
$redirectMap = [
    'admin'     => BASE_URL . '/views/admin/dashboard.php',
    'frontdesk' => BASE_URL . '/views/frontdesk/dashboard.php',
    'staff'     => BASE_URL . '/views/staff/dashboard.php',
];

$redirect = $redirectMap[$user['role']] ?? BASE_URL . '/index.php';

jsonResponse(true, 'Login successful.', [
    'redirect' => $redirect,
    'user'     => [
        'id'   => $user['id'],
        'name' => $user['name'],
        'role' => $user['role'],
    ],
]);
