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

$currentUser = requireApiRole(['super_admin', 'admin']);
requireCsrf();
$method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if ($currentUser['role'] === 'super_admin') {
        $where = "WHERE category = 'system'";
    } else {
        $where = "WHERE category NOT IN ('system', 'advanced')";
    }
    $stmt = $pdo->query("SELECT * FROM settings $where ORDER BY category, `key` ASC");
    jsonResponse(true, '', ['settings' => $stmt->fetchAll()]);
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['key'])) jsonResponse(false, 'Setting key is required.', [], 422);

    $key   = $data['key'];
    $value = $data['value'] ?? null;

    // Strict Input Validation based on Key
    switch ($key) {
        case 'min_pax':
            if ((int)$value < 10) jsonResponse(false, 'Minimum pax cannot be less than 10.', [], 422);
            break;
        case 'max_pax':
            $currentMin = (int)appSettingInt('min_pax', 50);
            if ((int)$value < $currentMin) jsonResponse(false, "Maximum pax cannot be less than the minimum pax ($currentMin).", [], 422);
            break;
        case 'standard_dp_percent':
        case 'rush_dp_percent':
            if ((float)$value < 0.1 || (float)$value > 1.0) jsonResponse(false, 'Downpayment percentage must be between 0.1 (10%) and 1.0 (100%).', [], 422);
            break;
        case 'extra_pax_rate':
        case 'staff_hourly_rate':
        case 'overtime_rate_per_hour':
            if ((float)$value < 0) jsonResponse(false, 'Rates cannot be negative.', [], 422);
            break;
        case 'event_duration_hours':
            if ((int)$value < 1 || (int)$value > 24) jsonResponse(false, 'Event duration must be between 1 and 24 hours.', [], 422);
            break;
        case 'min_lead_time_days':
            if ((int)$value < 0 || (int)$value > 365) jsonResponse(false, 'Lead time must be between 0 and 365 days.', [], 422);
            break;
        case 'rush_threshold_hours':
            if ((int)$value < 1 || (int)$value > 720) jsonResponse(false, 'Rush threshold must be between 1 and 720 hours.', [], 422);
            break;
        case 'max_login_attempts':
            if ((int)$value < 1 || (int)$value > 100) jsonResponse(false, 'Max login attempts must be between 1 and 100.', [], 422);
            break;
        case 'session_timeout_minutes':
            if ((int)$value < 5 || (int)$value > 1440) jsonResponse(false, 'Session timeout must be between 5 and 1440 minutes (24h).', [], 422);
            break;
        case 'max_file_upload_mb':
            if ((int)$value < 1 || (int)$value > 50) jsonResponse(false, 'Max file upload size must be between 1MB and 50MB.', [], 422);
            break;
        case 'audit_log_retention_days':
            if ((int)$value < 1 || (int)$value > 3650) jsonResponse(false, 'Audit log retention must be between 1 and 3650 days.', [], 422);
            break;
    }

    // Get old value for audit trail
    $oldStmt = $pdo->prepare("SELECT value, category FROM settings WHERE `key` = :key");
    $oldStmt->execute([':key' => $key]);
    $oldRow = $oldStmt->fetch();
    if (!$oldRow) jsonResponse(false, 'Setting not found.', [], 404);

    if ($currentUser['role'] === 'admin' && in_array(strtolower($oldRow['category']), ['system', 'advanced'])) {
        jsonResponse(false, 'Forbidden. You cannot edit system or advanced settings.', [], 403);
    }
    if ($currentUser['role'] === 'super_admin' && strtolower($oldRow['category']) !== 'system') {
        jsonResponse(false, 'Forbidden. Super Admins can only edit system-level settings.', [], 403);
    }

    $stmt = $pdo->prepare("UPDATE settings SET value = :val WHERE `key` = :key");
    $stmt->execute([':val' => (string)$value, ':key' => $key]);

    // FIX: Use correct function name auditLog() instead of logAudit()
    auditLog($pdo, 'setting_updated', 'setting', 0,
        ['key' => $key, 'value' => $oldRow['value']],
        ['key' => $key, 'value' => $value]
    );

    jsonResponse(true, 'Setting updated successfully.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
