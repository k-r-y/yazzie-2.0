<?php
/**
 * Settings API
 * GET — Retrieve all settings (Admin: full access to all categories)
 * PUT — Update a setting (Admin: full read/write including system category)
 *
 * super_admin role has been retired. The admin role is the highest tier
 * and inherits all former super_admin capabilities over system settings.
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

// admin is the sole top-tier role; super_admin has been retired.
$currentUser = requireApiRole(['admin']);
requireCsrf();
$method      = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    // admin has full visibility across all setting categories,
    // including system and advanced — no category filter applied.
    $stmt = $pdo->query("SELECT * FROM settings ORDER BY category, `key` ASC");
    jsonResponse(true, '', ['settings' => $stmt->fetchAll()]);
}

if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['key'])) jsonResponse(false, 'Setting key is required.', [], 422);

    $key   = $data['key'];
    $value = $data['value'] ?? null;

    // Strict Input Validation based on Key
    switch ($key) {
        case 'operating_hours_start':
        case 'operating_hours_end':
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
                jsonResponse(false, 'Operating hours must be in HH:mm format (24-hour).', [], 422);
            }
            if ($key === 'operating_hours_end') {
                $start = appSetting('operating_hours_start', '07:00');
                if (strtotime($value) <= strtotime($start)) {
                    jsonResponse(false, 'End time must be after start time.', [], 422);
                }
            }
            break;
        case 'meal_breakfast_start':
        case 'meal_lunch_start':
        case 'meal_dinner_start':
            $h = (int)$value;
            if ($h < 0 || $h > 23) jsonResponse(false, 'Meal start hour must be between 0 and 23.', [], 422);
            break;
        case 'staff_ratio_premium':
        case 'staff_ratio_standard':
        case 'waiter_ratio_wedding':
        case 'waiter_ratio_birthday':
            if ((int)$value <= 0) jsonResponse(false, 'Staff ratios must be greater than 0.', [], 422);
            break;
        case 'extra_main_rate':
        case 'extra_dessert_rate':
        case 'extra_rice_rate':
            if ((float)$value < 0) jsonResponse(false, 'Extra item rates cannot be negative.', [], 422);
            break;
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
        case 'lockout_duration_minutes':
            if ((int)$value < 1 || (int)$value > 1440) jsonResponse(false, 'Lockout duration must be between 1 and 1440 minutes (24h).', [], 422);
            break;
        case 'session_timeout_minutes':
            if ((int)$value < 5 || (int)$value > 1440) jsonResponse(false, 'Session timeout must be between 5 and 1440 minutes (24h).', [], 422);
            break;
        case 'max_file_upload_mb':
            if ((int)$value < 1 || (int)$value > 50) jsonResponse(false, 'Max file upload size must be between 1MB and 50MB.', [], 422);
            break;
        case 'audit_log_retention_days':
            if ((int)$value < 30 || (int)$value > 3650) jsonResponse(false, 'Audit log retention must be between 30 and 3650 days.', [], 422);
            break;
        case 'debug_mode':
            if ((int)$value !== 0 && (int)$value !== 1) jsonResponse(false, 'Debug mode must be either 0 (disabled) or 1 (enabled).', [], 422);
            break;
        case 'smtp_user':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'SMTP user must be a valid email address.', [], 422);
            break;
        case 'smtp_pass':
            $value = str_replace(' ', '', (string)$value);
            if (strlen(trim((string)$value)) < 5) jsonResponse(false, 'SMTP password must be at least 5 characters.', [], 422);
            break;
        case 'smtp_port':
            $portNum = (int)$value;
            if ($portNum < 1 || $portNum > 65535) jsonResponse(false, 'SMTP port must be between 1 and 65535.', [], 422);
            break;
        case 'smtp_host':
            $hostStr = trim((string)$value);
            if (strlen($hostStr) < 3) jsonResponse(false, 'SMTP host must be a valid hostname or IP address.', [], 422);
            if (!preg_match('/^[a-zA-Z0-9\.\-]+$/', $hostStr)) jsonResponse(false, 'SMTP host contains invalid characters.', [], 422);
            break;
        case 'smtp_secure':
            $sec = strtolower(trim((string)$value));
            if (!in_array($sec, ['tls', 'ssl', 'none'])) jsonResponse(false, 'SMTP security must be tls, ssl, or none.', [], 422);
            break;
        case 'smtp_from':
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'SMTP From must be a valid email address.', [], 422);
            break;
        case 'smtp_from_name':
            if (strlen(trim((string)$value)) < 2) jsonResponse(false, 'SMTP From Name is too short.', [], 422);
            break;
        case 'mail_enabled':
            if ((int)$value !== 0 && (int)$value !== 1) jsonResponse(false, 'Mail Enabled must be either 0 or 1.', [], 422);
            break;
        case 'sms_api_key':
            // Optional field, if provided must be at least 10 characters
            if (!empty($value) && strlen(trim((string)$value)) < 10) jsonResponse(false, 'SMS API key must be at least 10 characters if provided.', [], 422);
            break;
        case 'gcash_no':
            if (!empty($value) && !preg_match('/^09[0-9]{9}$/', (string)$value)) {
                jsonResponse(false, 'GCash number must be exactly 11 digits and start with 09.', [], 422);
            }
            break;
        case 'maya_no':
            if (!empty($value) && !preg_match('/^09[0-9]{9}$/', (string)$value)) {
                jsonResponse(false, 'Maya number must be exactly 11 digits and start with 09.', [], 422);
            }
            break;
        case 'bank_account_no':
            if (!empty($value) && !preg_match('/^[0-9]+$/', (string)$value)) {
                jsonResponse(false, 'Account number must contain only digits.', [], 422);
            }
            break;
    }

    // Get old value for audit trail
    $oldStmt = $pdo->prepare("SELECT value, category FROM settings WHERE `key` = :key");
    $oldStmt->execute([':key' => $key]);
    $oldRow = $oldStmt->fetch();
    if (!$oldRow) jsonResponse(false, 'Setting not found.', [], 404);

    // admin is the top-tier role and has full write access to all categories.
    // No category-level restriction is applied. Any future role-based
    // category gating should be re-introduced here via an explicit check.

    $stmt = $pdo->prepare("UPDATE settings SET value = :val WHERE `key` = :key");
    $stmt->execute([':val' => trim(substr((string)$value, 0, 5000)), ':key' => $key]);

    // FIX: Use correct function name auditLog() instead of logAudit()
    auditLog($pdo, 'setting_updated', 'setting', 0,
        ['key' => $key, 'value' => $oldRow['value']],
        ['key' => $key, 'value' => $value]
    );

    jsonResponse(true, 'Setting updated successfully.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
