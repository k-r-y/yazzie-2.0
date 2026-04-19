<?php
/**
 * CSRF Protection Module
 *
 * Generates and validates CSRF tokens per session.
 * Include AFTER config.php and auth.php.
 *
 * Usage (API endpoint):
 *   requireCsrf();  // Call at the top of POST/PUT/DELETE handlers
 *
 * Usage (View/HTML):
 *   <meta name="csrf-token" content="<?= getCsrfToken() ?>">
 *   JS: Api wrapper reads this automatically.
 */

/**
 * Generate or retrieve the current session CSRF token.
 */
function getCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token from the request.
 * Checks both X-CSRF-Token header and _csrf_token body field.
 *
 * Call this at the top of any state-changing API endpoint (POST/PUT/DELETE).
 */
function requireCsrf(): void
{
    // Skip validation for GET/HEAD/OPTIONS (safe methods)
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CSRF token missing from session. Please reload the page.']);
        exit;
    }

    // Check header first (preferred for API calls)
    $submitted = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    // Fallback: check JSON body
    if (empty($submitted)) {
        $input = json_decode(file_get_contents('php://input'), true);
        $submitted = $input['_csrf_token'] ?? '';
    }

    if (!hash_equals($sessionToken, $submitted)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid or expired CSRF token. Please reload the page.']);
        exit;
    }
}
