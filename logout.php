<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

startSession();

require_once __DIR__ . '/includes/audit.php';

if (isset($_SESSION['user_id'])) {
    auditLog($pdo, 'logout', 'user', $_SESSION['user_id']);
}

// Clear all session variables
$_SESSION = [];

// Expire the session cookie immediately
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 3600,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

header('Location: ' . BASE_URL . '/index.php?msg=logged_out');
exit;
