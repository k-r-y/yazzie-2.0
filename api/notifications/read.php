<?php
/**
 * Yazzies Catering OMS — Notifications Mark-Read API v2
 * ======================================================
 * Endpoint: POST /api/notifications/read.php
 *
 * Body (JSON):
 *   { "id": 42 }             — mark a single notification as read
 *   { "mark_all": true }     — mark all visible notifications as read
 *
 * Security:
 *   - CSRF token required (X-CSRF-Token header or _csrf body field)
 *   - Ownership enforced per role: a user can only mark notifications
 *     that their role is entitled to see (mirrors the GET API filters).
 *     This prevents a malicious actor from marking another user's
 *     notifications as read by guessing an ID.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed. Use POST.', [], 405);
}

requireCsrf();

$currentUser = requireApiRole(['admin', 'frontdesk', 'staff']);
$uid         = (int)$currentUser['id'];
$role        = $currentUser['role'];

$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ── Role-scoped ownership WHERE clause (mirrors get.php exactly) ───────────
// This ensures a staff member cannot mark an admin notification as read.
$params = [];
switch ($role) {
    case 'admin':
        $ownershipWhere = "target_role IN ('admin', 'global', 'superadmin') AND type IN ('booking', 'finance', 'dispatch', 'system', 'user_management')";
        break;
    case 'frontdesk':
        $ownershipWhere = "target_role IN ('frontdesk', 'global') AND type IN ('booking', 'finance', 'dispatch')";
        break;
    case 'staff':
        $ownershipWhere = "recipient_id = :uid";
        $params[':uid'] = $uid;
        break;
    default:
        jsonResponse(false, 'Forbidden.', [], 403);
}

// ── Mark All ───────────────────────────────────────────────────────────────
if (!empty($input['mark_all'])) {
    $sql   = "UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND ({$ownershipWhere})";
    $stmt  = $pdo->prepare($sql);
    $stmt->execute($params);
    jsonResponse(true, 'All notifications marked as read.', ['affected' => $stmt->rowCount()]);
}

// ── Mark Single ────────────────────────────────────────────────────────────
$notifId = (int)($input['id'] ?? 0);
if (!$notifId) {
    jsonResponse(false, 'A notification id is required.', [], 422);
}

$params[':id'] = $notifId;
$sql = "UPDATE notifications SET is_read = 1 WHERE id = :id AND ({$ownershipWhere})";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

if ($stmt->rowCount() === 0) {
    // Either already read, or the ID doesn't belong to this role — return 200
    // to avoid leaking existence of other notifications
    jsonResponse(true, 'Notification already marked as read or not found.');
}

jsonResponse(true, 'Notification marked as read.');
