<?php
/**
 * Security Utilities — XSS escaping and password policy enforcement.
 *
 * Loaded globally via config.php.
 */

/**
 * Escape a string for safe HTML output.
 * Wraps htmlspecialchars with consistent flags.
 *
 * @param  mixed  $value  The value to escape
 * @return string         Escaped string safe for HTML contexts
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Escape a string for safe use inside a JavaScript string literal.
 *
 * @param  mixed  $value  The value to escape
 * @return string         JSON-encoded string (without surrounding quotes)
 */
function ejs(mixed $value): string
{
    $encoded = json_encode((string)($value ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    // Strip surrounding quotes since caller will place inside their own quotes
    return substr($encoded, 1, -1);
}

/**
 * Validate a password against the password policy.
 *
 * Requirements:
 * - Minimum 8 characters
 * - At least 1 uppercase letter
 * - At least 1 lowercase letter
 * - At least 1 digit
 * - At least 1 special character
 *
 * @param  string       $password  The password to validate
 * @return string|null             Error message if invalid, null if valid
 */
function validatePasswordPolicy(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must contain at least one special character (!@#$%^&*).';
    }
    return null; // Valid
}
