<?php
/**
 * Settings API
 * GET — Retrieve all settings
 * PUT — Update a setting (Super Admin Only)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

// Strictly Super Admin
$currentUser = requireApiRole('super_admin');
requireCsrf();
$method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY category, `key` ASC");
    jsonResponse(true, '', ['settings' => $stmt->fetchAll()]);
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['key'])) jsonResponse(false, 'Setting key is required.', [], 422);

    $key   = $data['key'];
    $value = $data['value'] ?? null;

    // Optional: Validation per key
    if ($key === 'min_pax' && (int)$value < 10) jsonResponse(false, 'Minimum pax cannot be less than 10.', [], 422);

    // Get old value for audit trail
    $oldStmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = :key");
    $oldStmt->execute([':key' => $key]);
    $oldValue = $oldStmt->fetchColumn();

    $stmt = $pdo->prepare("UPDATE settings SET value = :val WHERE `key` = :key");
    $stmt->execute([':val' => (string)$value, ':key' => $key]);

    // FIX: Use correct function name auditLog() instead of logAudit()
    auditLog($pdo, 'setting_updated', 'setting', 0,
        ['key' => $key, 'value' => $oldValue],
        ['key' => $key, 'value' => $value]
    );

    jsonResponse(true, 'Setting updated successfully.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
