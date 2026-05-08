<?php
/**
 * Yazzies Catering OMS — Notifications GET API v2
 * ================================================
 * Endpoint: GET  /api/notifications/get.php
 *           GET  /api/notifications/get.php?unread=1
 *
 * Role-based strict filtering — prevents cross-contamination between roles:
 *
 *  admin        →  target_role IN ('admin','global','superadmin')
 *                  AND type IN ('booking','finance','dispatch','system','user_management')
 *  frontdesk    →  target_role IN ('frontdesk','global')
 *                  AND type IN ('booking','finance','dispatch')
 *  staff        →  recipient_id = :current_user_id  (direct messages only)
 *
 * Returns JSON:
 *   { success: true, notifications: [...], count: N }
 *   { success: true, count: N }              ← when ?unread=1
 *   { success: false, message: "..." }       ← on auth / method error
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Only GET is supported on this endpoint
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}

// ── Authenticate — all roles may call this endpoint ────────────────────────
$currentUser = requireApiRole(['admin', 'frontdesk', 'staff']);
$uid         = (int)$currentUser['id'];
$role        = $currentUser['role'];

// ═══════════════════════════════════════════════════════════════════════════
// Build the role-specific WHERE clause
// IMPORTANT: Parameters are bound via PDO to prevent SQL injection.
// The role check itself is server-side (session), never from user input.
// ═══════════════════════════════════════════════════════════════════════════
$params = [];

switch ($role) {

    // ── Admin: business + system + user management notifications ───────────
    case 'admin':
        $whereClause = "
            WHERE n.target_role IN ('admin', 'global', 'superadmin')
              AND n.type        IN ('booking', 'finance', 'dispatch', 'system', 'user_management')
        ";
        break;

    // ── Frontdesk: booking/finance/dispatch broadcast ──────────────────────
    case 'frontdesk':
        $whereClause = "
            WHERE n.target_role IN ('frontdesk', 'global')
              AND n.type        IN ('booking', 'finance', 'dispatch')
        ";
        break;

    // ── Staff: only direct messages assigned to their user ID ─────────────
    case 'staff':
        $whereClause = "
            WHERE n.recipient_id = :current_user_id
        ";
        $params[':current_user_id'] = $uid;
        break;

    default:
        // Defensive fallback — should never be reached given requireApiRole above
        jsonResponse(false, 'Your role does not have a notification configuration.', [], 403);
}

// ── ?unread=1 — return badge count only ────────────────────────────────────
if (isset($_GET['unread'])) {
    $countSql = "SELECT COUNT(*) FROM notifications n {$whereClause} AND n.is_read = 0";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    jsonResponse(true, '', ['count' => (int)$countStmt->fetchColumn()]);
}

// ── Full notification list (latest 40 records) ─────────────────────────────
$listSql = "
    SELECT
        n.id,
        n.recipient_id,
        n.target_role,
        n.type,
        n.message,
        n.action_url,
        n.is_read,
        n.created_at
    FROM notifications n
    {$whereClause}
    ORDER BY n.created_at DESC
    LIMIT 40
";

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$notifications = $listStmt->fetchAll(PDO::FETCH_ASSOC);

// Unread count (for badge refresh without a second trip)
$unreadCount = count(array_filter($notifications, fn($n) => !(int)$n['is_read']));

jsonResponse(true, '', [
    'notifications' => $notifications,
    'unread_count'  => $unreadCount,
]);
