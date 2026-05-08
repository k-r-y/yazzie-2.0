<?php
/**
 * Audit Logs API
 * GET — Retrieve system logs (Admin Only)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Strictly Admin
$currentUser = requireApiRole('admin');

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = (int)($_GET['limit'] ?? 10);
if ($limit < 1) $limit = 10;
$offset = ($page - 1) * $limit;

$totalStmt = $pdo->query('SELECT COUNT(*) FROM audit_log');
$totalRecords = (int)$totalStmt->fetchColumn();
$totalPages = (int)ceil($totalRecords / $limit);

$stmt = $pdo->prepare("
    SELECT a.*, u.name as user_name
    FROM audit_log a
    LEFT JOIN users u ON u.id = a.user_id
    ORDER BY a.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();

jsonResponse(true, '', [
    'logs' => $stmt->fetchAll(),
    'meta' => [
        'currentPage'  => $page,
        'totalPages'   => $totalPages,
        'totalRecords' => $totalRecords,
    ]
]);
