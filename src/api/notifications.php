<?php
/**
 * Yazzies Catering OMS — Core Notifications API v2
 * ==================================================
 * This file is the canonical backend for the notification bell.
 * The thin wrapper at /api/notifications/get.php is now replaced
 * by dedicated endpoints:
 *
 *   GET  /api/notifications/get.php   → role-filtered notification list
 *   POST /api/notifications/read.php  → mark single or all as read
 *
 * This file is kept for backward compatibility with any direct callers
 * (e.g. cron_worker.php) that may PUT to src/api/notifications.php.
 *
 * PUT body: { "id": N }              — mark single notification as read
 * PUT body: { "mark_all": true }     — mark all as read for current user/role
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = requireApiRole(['super_admin', 'admin', 'frontdesk', 'staff']);
requireCsrf();

$method = $_SERVER['REQUEST_METHOD'];

// ── GET — delegate to dedicated endpoint ───────────────────────────────────
if ($method === 'GET') {
    // Forward to the v2 GET endpoint which handles role filtering
    require_once __DIR__ . '/../../api/notifications/get.php';
    exit;
}

// ── PUT — mark-read (backward compatibility) ───────────────────────────────
if ($method === 'PUT') {
    $uid   = (int)$currentUser['id'];
    $role  = $currentUser['role'];
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    // Build the same role-scoped ownership WHERE as read.php
    $params = [];
    switch ($role) {
        case 'super_admin':
            $ownershipWhere = "target_role = 'superadmin' AND type = 'user_management'";
            break;
        case 'admin':
            $ownershipWhere = "target_role IN ('admin', 'global') AND type IN ('booking', 'finance', 'dispatch', 'system')";
            break;
        case 'frontdesk':
            $ownershipWhere = "target_role IN ('frontdesk', 'global') AND type IN ('booking', 'finance', 'dispatch')";
            break;
        case 'staff':
        default:
            $ownershipWhere = "recipient_id = :uid";
            $params[':uid'] = $uid;
            break;
    }

    if (!empty($input['mark_all'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE is_read = 0 AND ({$ownershipWhere})");
        $stmt->execute($params);
        jsonResponse(true, 'All notifications marked as read.');
    }

    $notifId = (int)($input['id'] ?? 0);
    if (!$notifId) {
        jsonResponse(false, 'id or mark_all is required.', [], 422);
    }
    $params[':id'] = $notifId;
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND ({$ownershipWhere})")
        ->execute($params);
    jsonResponse(true, 'Marked as read.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
