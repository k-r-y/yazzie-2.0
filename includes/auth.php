<?php
/**
 * Yazzies Catering OMS — Authentication & Session Helpers
 * Include this file on every protected page AFTER config.php
 */

/**
 * Check if the system is in maintenance mode.
 * Only Admins (the highest role tier) can bypass this.
 */
function checkMaintenanceMode(): void
{
    if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE) {
        $user = getCurrentUser();
        if (!$user || $user['role'] !== 'admin') {
            $isApi = str_contains($_SERVER['REQUEST_URI'], '/src/api/');
            if ($isApi) {
                jsonResponse(false, 'System is currently under maintenance. Please try again later.', [], 503);
            } else {
                // Check if already on maintenance page to avoid loops
                if (!str_contains($_SERVER['REQUEST_URI'], 'maintenance.php') && !str_contains($_SERVER['REQUEST_URI'], 'index.php')) {
                     die("<h1>System Under Maintenance</h1><p>The system is currently undergoing scheduled maintenance. Please check back later.</p><p><a href='".BASE_URL."/index.php'>Return to Home</a></p>");
                }
            }
        }
    }
}

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if the session has exceeded the timeout duration.
 * If it has, destroy the session and redirect to login.
 * Also checks if debug mode is enabled and logs out user.
 */
function checkSessionTimeout(): void
{
    global $pdo;
    
    // Only check if user is logged in (direct check, no function calls to avoid recursion)
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return; // Not logged in, nothing to timeout
    }
    
    // ── Check if debug mode is enabled; if so, force logout non-admins ──
    // The admin role is now the highest tier and retains debug-mode access.
    if (defined('DEBUG_MODE') && (int)DEBUG_MODE === 1) {
        $role = $_SESSION['role'] ?? '';
        if ($role !== 'admin') {
            session_destroy();
            $_SESSION = [];
            $isApi = str_contains($_SERVER['REQUEST_URI'], '/src/api/');
            if ($isApi) {
                jsonResponse(false, 'System is in debug mode. All users have been logged out. Please try again later.', [], 503);
            } else {
                header('Location: ' . BASE_URL . '/index.php?error=debug');
                exit;
            }
        }
    }
    
    // Get timeout from settings (default 30 minutes)
    $timeoutMinutes = 30;
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'session_timeout_minutes' LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            if ($result) {
                $timeoutMinutes = max(5, (int)$result['value']); // Minimum 5 minutes
            }
        }
    } catch (Throwable $e) {
        // If settings table doesn't exist, use default
    }
    
    // Session inactivity timeout (in seconds)
    $sessionTimeout = $timeoutMinutes * 60;
    
    // Check if session was last accessed
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = time();
    } else {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > $sessionTimeout) {
            // Session has expired, destroy it
            session_destroy();
            $_SESSION = [];
            // Redirect to login with timeout message
            $isApi = str_contains($_SERVER['REQUEST_URI'], '/src/api/');
            if ($isApi) {
                jsonResponse(false, 'Your session has expired. Please log in again.', [], 401);
            } else {
                header('Location: ' . BASE_URL . '/index.php?error=timeout');
                exit;
            }
        }
    }
    
    // Update last activity timestamp
    $_SESSION['last_activity'] = time();
}

/**
 * Check if a user is currently authenticated.
 */
function isLoggedIn(): bool
{
    startSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require the user to be logged in and have the specified role(s).
 * Redirects to the login page if not authenticated or unauthorized.
 *
 * @param string|array $roles  Single role string or array of allowed roles.
 */
function requireRole(string|array $roles): void
{
    startSession();
    checkMaintenanceMode();
    checkSessionTimeout();

    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?error=auth');
        exit;
    }

    $roles = (array) $roles;
    $userRole = $_SESSION['role'] ?? '';

    if (!in_array($userRole, $roles, true)) {
        header('Location: ' . BASE_URL . '/index.php?error=forbidden');
        exit;
    }
}

/**
 * Get the currently authenticated user's data from the session.
 *
 * @return array|null  User data array or null if not logged in.
 */
function getCurrentUser(): ?array
{
    startSession();
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['name'],
        'email' => $_SESSION['email'],
        'role'  => $_SESSION['role'],
    ];
}

/**
 * Redirect the user to their role-specific dashboard.
 *
 * @param string $role  The user's role.
 */
function redirectByRole(string $role): void
{
    // super_admin has been retired; admin is now the top-tier role.
    $redirectMap = [
        'admin'     => BASE_URL . '/views/admin/dashboard.php',
        'frontdesk' => BASE_URL . '/views/frontdesk/dashboard.php',
        'staff'     => BASE_URL . '/views/staff/dashboard.php',
    ];

    $url = $redirectMap[$role] ?? BASE_URL . '/index.php';
    header('Location: ' . $url);
    exit;
}

/**
 * Get a human-readable role label.
 */
function getRoleLabel(string $role): string
{
    // super_admin label removed — admin is now the singular top-tier role.
    return match($role) {
        'admin'     => 'Administrator',
        'frontdesk' => 'Front Desk',
        'staff'     => 'On-Call Staff',
        default     => ucfirst($role),
    };
}

/**
 * Get a user's initials for the avatar.
 */
function getInitials(string $name): string
{
    $parts    = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    return $initials ?: 'U';
}

/**
 * Respond with a JSON payload and exit. For use in API endpoints.
 *
 * @param bool   $success
 * @param string $message
 * @param array  $data
 * @param int    $httpCode
 */
function jsonResponse(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
{
    header('Content-Type: application/json');
    http_response_code($httpCode);
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $data
    ));
    exit;
}

/**
 * Require the API caller to be authenticated and have the specified role(s).
 * Returns a JSON error if not.
 */
function requireApiRole(string|array $roles): array
{
    startSession();
    checkMaintenanceMode();
    checkSessionTimeout();

    if (!isLoggedIn()) {
        jsonResponse(false, 'Unauthorized. Please log in.', [], 401);
    }

    global $pdo;
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $isActive = (int)$stmt->fetchColumn();
        if ($isActive === 0) {
            $_SESSION = [];
            session_destroy();
            jsonResponse(false, 'Your account has been deactivated.', [], 403);
        }
        if ($isActive === 2) {
            $_SESSION = [];
            session_destroy();
            jsonResponse(false, 'Your account setup is incomplete. Please check your invitation email to finish setting up your account.', [], 403);
        }
    }

    $roles = (array) $roles;

    // No legacy super_admin bypass — every role is validated explicitly.
    // The admin role must be included in $roles wherever admin-level
    // access is required.
    $userRole = $_SESSION['role'] ?? '';
    if (!in_array($userRole, $roles, true)) {
        jsonResponse(false, 'Forbidden. You do not have access to this resource.', [], 403);
    }

    return getCurrentUser();
}
