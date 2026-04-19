<?php
/**
 * Notifications API
 * GET            — returns notifications for logged-in user
 * GET ?unread=1  — unread count only
 * PUT            — mark as read { id } OR { mark_all: true }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$currentUser = requireApiRole(['admin', 'frontdesk', 'staff']);
requireCsrf();
$method      = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $uid = $currentUser['id'];

    // Unread count only (for notification bell badge)
    if (isset($_GET['unread'])) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
        $stmt->execute([':uid' => $uid]);
        jsonResponse(true, '', ['count' => (int)$stmt->fetchColumn()]);
    }

    // Full notification list (latest 30)
    $stmt = $pdo->prepare("
        SELECT n.*, b.event_date, b.event_location
        FROM notifications n
        LEFT JOIN bookings b ON b.id = n.booking_id
        WHERE n.user_id = :uid
        ORDER BY n.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([':uid' => $uid]);
    jsonResponse(true, '', ['notifications' => $stmt->fetchAll()]);
}

if ($method === 'PUT') {
    $d   = json_decode(file_get_contents('php://input'), true) ?? [];
    $uid = $currentUser['id'];

    // Mark all as read
    if (!empty($d['mark_all'])) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid")
            ->execute([':uid' => $uid]);
        jsonResponse(true, 'All notifications marked as read.');
    }

    // Mark single
    if (empty($d['id'])) jsonResponse(false, 'id or mark_all is required.', [], 422);
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :uid")
        ->execute([':id' => (int)$d['id'], ':uid' => $uid]);
    jsonResponse(true, 'Marked as read.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
