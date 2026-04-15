<?php
/**
 * Yazzies Catering OMS — Authentication & Session Helpers
 * Include this file on every protected page AFTER config.php
 */

function startSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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

    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php?error=auth');
        exit;
    }

    $roles = (array) $roles;

    // super_admin inherits all roles — they can access any admin/frontdesk page
    $userRole = $_SESSION['role'] ?? '';
    if ($userRole === 'super_admin' && !in_array('super_admin', $roles, true)) {
        $roles[] = 'super_admin';
    }

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
    $redirectMap = [
        'super_admin' => BASE_URL . '/views/admin/dashboard.php', // super_admin uses admin dashboard
        'admin'       => BASE_URL . '/views/admin/dashboard.php',
        'frontdesk'   => BASE_URL . '/views/frontdesk/dashboard.php',
        'staff'       => BASE_URL . '/views/staff/dashboard.php',
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
    return match($role) {
        'super_admin' => '⭐ Super Admin',
        'admin'       => 'Administrator',
        'frontdesk'   => 'Front Desk',
        'staff'       => 'On-Call Staff',
        default       => ucfirst($role),
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

    if (!isLoggedIn()) {
        jsonResponse(false, 'Unauthorized. Please log in.', [], 401);
    }

    $roles = (array) $roles;

    // super_admin inherits all role permissions
    $userRole = $_SESSION['role'] ?? '';
    if ($userRole === 'super_admin') {
        return getCurrentUser(); // super_admin bypasses all role checks
    }

    if (!in_array($userRole, $roles, true)) {
        jsonResponse(false, 'Forbidden. You do not have access to this resource.', [], 403);
    }

    return getCurrentUser();
}
