<?php
/**
 * Account Self-Setup API — Phase 5: Email Invitation Flow
 * POST /src/api/setup_account.php
 *
 * Public endpoint (no session required). Validates the one-time
 * invitation token + OTP, then lets the new user set their password.
 *
 * Supports two sub-actions via the `action` field:
 *   "verify_otp"   — validate token + OTP only (Step 1 AJAX call)
 *   "set_password" — full completion: verify token+OTP, hash password,
 *                    activate user, delete token record (Step 2 AJAX call)
 *
 * CSRF: requireCsrf() is called — the setup.php view must include the
 * CSRF meta tag so the global Api.js wrapper sends X-CSRF-Token.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

// This is a public endpoint — no requireApiRole() call.
// But we DO enforce CSRF to prevent cross-site form submissions.
requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}

$d      = json_decode(file_get_contents('php://input'), true) ?? [];
$token  = trim($d['token']  ?? '');
$otp    = trim($d['otp']    ?? '');
$action = trim($d['action'] ?? 'set_password');

// ── 1. Basic input validation ─────────────────────────────────────
if (empty($token)) {
    jsonResponse(false, 'Setup token is missing.', [], 422);
}
if (empty($otp)) {
    jsonResponse(false, 'OTP is required.', [], 422);
}

// ── 2. Look up the token in user_invitations ──────────────────────
$stmt = $pdo->prepare("
    SELECT ui.id AS invite_id, ui.user_id, ui.otp, ui.expires_at,
           u.name, u.email, u.role
    FROM   user_invitations ui
    JOIN   users u ON u.id = ui.user_id
    WHERE  ui.token = :token
    LIMIT  1
");
$stmt->execute([':token' => $token]);
$invite = $stmt->fetch();

if (!$invite) {
    jsonResponse(false, 'This setup link is invalid or has already been used. Please contact your administrator.', [], 404);
}

// ── 2.1 OTP-specific rate limit ───────────────────────────────────
checkOtpRateLimit($pdo, $invite['email']);

// ── 3. Expiry check ───────────────────────────────────────────────
if (strtotime($invite['expires_at']) < time()) {
    jsonResponse(false, 'This setup link has expired (24-hour window). Please ask your administrator to resend the invitation.', [], 410);
}

// ── 4. OTP verification ───────────────────────────────────────────
if (!hash_equals((string)$invite['otp'], (string)$otp)) {
    recordOtpAttempt($pdo, $invite['email']); // penalize bad OTP guesses
    jsonResponse(false, 'The OTP you entered is incorrect. Please check your email and try again.', [], 422);
}

// Code is valid — clear OTP attempts
clearOtpAttempts($pdo, $invite['email']);

// ── If action is just OTP verification, return early ─────────────
if ($action === 'verify_otp') {
    jsonResponse(true, 'OTP verified. Please set your new password.', [
        'name' => $invite['name'],
    ]);
}

// ── 5. Password validation (set_password action) ──────────────────
$password        = $d['password']         ?? '';
$passwordConfirm = $d['password_confirm'] ?? '';

if (empty($password)) {
    jsonResponse(false, 'Password is required.', [], 422);
}
if ($password !== $passwordConfirm) {
    jsonResponse(false, 'Passwords do not match. Please try again.', [], 422);
}

// Reuse the existing password policy validator
$pwError = validatePasswordPolicy($password);
if ($pwError) {
    jsonResponse(false, $pwError, [], 422);
}

// ── 6. Determine target is_active state ───────────────────────────
// Single-Admin Edge Case: admins are created in a Dormant (0) state
// to prevent violating the Single-Active-Admin rule. They must receive
// a Master Key Transfer from the existing active admin to become active.
// Non-admin roles (frontdesk, staff) are immediately activated (1).
$newIsActive = in_array($invite['role'], ['staff', 'frontdesk'], true) ? 1 : 0;

// ── 7. Update user: set password + new is_active in one transaction
try {
    $pdo->beginTransaction();

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    $pdo->prepare("
        UPDATE users
        SET    password  = :pw,
               is_active = :active
        WHERE  id = :uid
    ")->execute([
        ':pw'     => $hashedPassword,
        ':active' => $newIsActive,
        ':uid'    => $invite['user_id'],
    ]);

    // ── 8. Delete the used token (single-use enforcement) ─────────
    $pdo->prepare("
        DELETE FROM user_invitations WHERE id = :iid
    ")->execute([':iid' => $invite['invite_id']]);

    $pdo->commit();

    // ── 9. Audit log ──────────────────────────────────────────────
    auditLog($pdo, 'account_self_setup', 'user', (int)$invite['user_id'], null, [
        'role'      => $invite['role'],
        'is_active' => $newIsActive,
    ]);

} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[Setup Account API] Transaction failed: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred while activating your account. Please try again.', [], 500);
}

// ── 10. Return contextual success message ─────────────────────────
if ($newIsActive === 0) {
    // Admin edge case — account is dormant, awaiting Master Key Transfer
    jsonResponse(true, 'Your password has been set. Your administrator account is currently dormant and will be activated when the Master Key is transferred to you. You may now log in and await activation.', [
        'is_active' => 0,
        'role'      => $invite['role'],
    ]);
} else {
    jsonResponse(true, 'Your account is ready! You can now log in to the system.', [
        'is_active' => 1,
        'role'      => $invite['role'],
    ]);
}
