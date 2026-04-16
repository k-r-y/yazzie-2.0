<?php
/**
 * Audit Logs API
 * GET — Retrieve system logs (Super Admin Only)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Strictly Super Admin
$currentUser = requireApiRole('super_admin');

$limit  = (int)($_GET['limit']  ?? 50);
$offset = (int)($_GET['offset'] ?? 0);

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

jsonResponse(true, '', ['logs' => $stmt->fetchAll()]);
