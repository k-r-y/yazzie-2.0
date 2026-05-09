<?php
/**
 * Audit Logs API — Enterprise Edition
 * GET /src/api/audit_logs.php
 *
 * Supports:
 *   ?page=1          — Page number (default: 1)
 *   ?limit=20        — Results per page (default: 20, max: 100)
 *   ?search=term     — Full-text search on action, entity, user name, IP
 *   ?action_type=X   — Filter by action category: login, booking, payment,
 *                       user, setting, inventory, dispatch, system
 *   ?date_from=Y-m-d — Filter from this date (inclusive)
 *   ?date_to=Y-m-d   — Filter to this date (inclusive)
 *   ?entity_type=X   — Filter by entity (booking, client, user, etc.)
 *
 * Auth: admin only
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

requireApiRole('admin');

// ── 1. Sanitize & validate params ──────────────────────────────────────────
$page  = max(1, (int)($_GET['page']  ?? 1));
$limit = min(10000, max(1, (int)($_GET['limit'] ?? 20))); // Increased max to 10,000 for CSV exports
$offset = ($page - 1) * $limit;

$search     = trim($_GET['search']      ?? '');
$actionType = trim($_GET['action_type'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');
$dateFrom   = trim($_GET['date_from']   ?? '');
$dateTo     = trim($_GET['date_to']     ?? '');

// ── 2. Action-type category mapping ────────────────────────────────────────
// Map broad category names to LIKE patterns on the action column
$actionCategories = [
    'login'     => ['login%', 'logout%'],
    'booking'   => ['booking_%', 'reminder_%'],
    'payment'   => ['payment_%', 'paymongo_%', 'refund_%'],
    'user'      => ['user_%', 'admin_%', 'staff_%', 'master_key_%'],
    'setting'   => ['setting_%'],
    'inventory' => ['inventory_%', 'breakage_%'],
    'dispatch'  => ['inventory_dispatch%', 'inventory_return%', 'job_%'],
    'system'    => ['system_%', 'database_backup%'],
];

// ── 3. Build dynamic WHERE clause ──────────────────────────────────────────
$where  = [];
$params = [];

// Action type filter
if (!empty($actionType) && isset($actionCategories[$actionType])) {
    $patterns = $actionCategories[$actionType];
    $orClauses = [];
    foreach ($patterns as $i => $pattern) {
        $key = ":action_pattern_{$i}";
        $orClauses[] = "a.action LIKE $key";
        $params[$key] = $pattern;
    }
    $where[] = '(' . implode(' OR ', $orClauses) . ')';
}

// Entity type filter
if (!empty($entityType)) {
    $where[] = 'a.entity = :entity_type';
    $params[':entity_type'] = $entityType;
}

// Date range filter (on created_at)
if (!empty($dateFrom) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 'DATE(a.created_at) >= :date_from';
    $params[':date_from'] = $dateFrom;
}
if (!empty($dateTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 'DATE(a.created_at) <= :date_to';
    $params[':date_to'] = $dateTo;
}

// Full-text search across key columns
if (!empty($search)) {
    $like = '%' . $search . '%';
    $where[] = '(
        a.action     LIKE :s1 OR
        a.entity     LIKE :s2 OR
        u.name       LIKE :s3 OR
        a.ip_address LIKE :s4 OR
        a.new_value  LIKE :s5
    )';
    $params[':s1'] = $like;
    $params[':s2'] = $like;
    $params[':s3'] = $like;
    $params[':s4'] = $like;
    $params[':s5'] = $like;
}

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// ── 4. Count total matching records ────────────────────────────────────────
$countSql = "
    SELECT COUNT(*)
    FROM audit_log a
    LEFT JOIN users u ON u.id = a.user_id
    $whereClause
";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalRecords = (int)$countStmt->fetchColumn();
$totalPages   = max(1, (int)ceil($totalRecords / $limit));

// ── 5. Fetch page of records with relational JOINs ──────────────────────────
$dataSql = "
    SELECT
        a.id,
        a.action,
        a.entity,
        a.entity_id,
        a.old_value,
        a.new_value,
        a.ip_address,
        a.created_at,

        -- Actor (who performed the action)
        a.user_id,
        COALESCE(u.name, 'System / Cron') AS user_name,
        u.role                             AS user_role,

        -- Related booking (if entity = 'booking')
        b.event_date,
        b.event_type,
        bc.name AS booking_client_name,

        -- Related client (if entity = 'client')
        c.name  AS client_name

    FROM audit_log a
    LEFT JOIN users   u  ON u.id  = a.user_id
    LEFT JOIN bookings b ON b.id  = a.entity_id  AND a.entity = 'booking'
    LEFT JOIN clients  bc ON bc.id = b.client_id
    LEFT JOIN clients  c  ON c.id  = a.entity_id  AND a.entity = 'client'
    $whereClause
    ORDER BY a.created_at DESC, a.id DESC
    LIMIT :limit OFFSET :offset
";

$dataStmt = $pdo->prepare($dataSql);
foreach ($params as $k => $v) {
    $dataStmt->bindValue($k, $v);
}
$dataStmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$dataStmt->execute();
$logs = $dataStmt->fetchAll();

// ── 6. Enrich each log entry for the frontend ───────────────────────────────
foreach ($logs as &$log) {
    // Decode JSON values for detail display
    $oldVal = !empty($log['old_value']) ? json_decode($log['old_value'], true) : null;
    $newVal = !empty($log['new_value']) ? json_decode($log['new_value'], true) : null;

    // Generate a human-readable description
    $log['description'] = buildDescription($log, $oldVal, $newVal);

    // Assign a semantic category + badge color
    [$log['category'], $log['badge_color'], $log['icon']] = categorizeAction($log['action']);

    // Shorten the raw JSON for display (keep it readable)
    $log['detail_snippet'] = buildDetailSnippet($newVal ?? $oldVal);

    // Unset raw blobs to keep response lean — frontend doesn't need them
    unset($log['old_value'], $log['new_value']);
}
unset($log);

// ── 7. Respond ─────────────────────────────────────────────────────────────
jsonResponse(true, '', [
    'logs' => $logs,
    'meta' => [
        'currentPage'  => $page,
        'totalPages'   => $totalPages,
        'totalRecords' => $totalRecords,
        'limit'        => $limit,
    ],
]);

// ── Helper functions ────────────────────────────────────────────────────────

/**
 * Build a human-readable one-line description from an audit log entry.
 */
function buildDescription(array $log, ?array $old, ?array $new): string
{
    $actor  = htmlspecialchars($log['user_name'] ?? 'System');
    $action = $log['action'];
    $entity = $log['entity'] ?? '';
    $eid    = $log['entity_id'] ? " #" . (int)$log['entity_id'] : '';

    // Context-aware descriptions
    $map = [
        'login'                  => "$actor logged in.",
        'logout'                 => "$actor logged out.",
        'login_failed'           => "$actor failed to log in (invalid credentials).",
        'login_locked'           => "Account locked after repeated failures for $actor.",
        'booking_created'        => "$actor created Booking{$eid}" . ($log['booking_client_name'] ? " for {$log['booking_client_name']}" : '') . ".",
        'booking_updated'        => "$actor updated Booking{$eid}.",
        'booking_cancelled'      => "$actor cancelled Booking{$eid}.",
        'booking_archived'       => "$actor archived Booking{$eid}.",
        'booking_unarchived'     => "$actor unarchived Booking{$eid}.",
        'booking_status_changed' => "$actor changed status of Booking{$eid}.",
        'payment_recorded'       => "$actor recorded a payment on Booking{$eid}.",
        'payment_deleted'        => "$actor deleted a payment on Booking{$eid}.",
        'refund_recorded'        => "$actor processed a refund on Booking{$eid}.",
        'paymongo_checkout_created' => "$actor initiated a PayMongo checkout for Booking{$eid}.",
        'paymongo_payment_confirmed' => "PayMongo confirmed payment for Booking{$eid}.",
        'inventory_dispatched'   => "$actor dispatched inventory for Booking{$eid}.",
        'inventory_returned'     => "$actor recorded inventory return for Booking{$eid}.",
        'user_created'           => "$actor created a new user account{$eid}.",
        'user_updated'           => "$actor updated user account{$eid}.",
        'user_deactivated'       => "$actor deactivated user account{$eid}.",
        'user_reactivated'       => "$actor reactivated user account{$eid}.",
        'admin_master_transfer'  => "$actor transferred the Master Admin key.",
        'setting_updated'        => "$actor updated a system setting" . ($new['key'] ? " ({$new['key']})" : "") . ".",
        'reminder_sent'          => "$actor sent a payment reminder for Booking{$eid}.",
        'backup_created'         => "$actor generated a database backup.",
        'cancellation_requested' => "$actor requested cancellation of Booking{$eid}.",
        'cancellation_finalized' => "$actor finalized cancellation of Booking{$eid}.",
    ];

    return $map[$action]
        ?? ucwords(str_replace('_', ' ', $action)) . " on " . ucfirst($entity) . $eid . " by $actor.";
}

/**
 * Return [category_label, badge_css_class, icon_class] for a given action string.
 */
function categorizeAction(string $action): array
{
    if (str_starts_with($action, 'login') || str_starts_with($action, 'logout')) {
        return ['Authentication', 'badge-login', 'fa-right-to-bracket'];
    }
    if (str_starts_with($action, 'booking')) {
        return ['Booking', 'badge-booking', 'fa-calendar-check'];
    }
    if (str_starts_with($action, 'payment') || str_starts_with($action, 'paymongo') || str_starts_with($action, 'refund')) {
        return ['Payment', 'badge-payment', 'fa-peso-sign'];
    }
    if (str_starts_with($action, 'inventory') || str_starts_with($action, 'breakage')) {
        return ['Inventory', 'badge-inventory', 'fa-boxes-stacked'];
    }
    if (str_starts_with($action, 'dispatch') || str_starts_with($action, 'job')) {
        return ['Dispatch', 'badge-dispatch', 'fa-people-carry-box'];
    }
    if (str_starts_with($action, 'user') || str_starts_with($action, 'admin') || str_starts_with($action, 'staff')) {
        return ['User Mgmt', 'badge-user', 'fa-user-shield'];
    }
    if (str_starts_with($action, 'setting')) {
        return ['Settings', 'badge-setting', 'fa-sliders'];
    }
    if (str_starts_with($action, 'archive') || str_starts_with($action, 'backup') || str_starts_with($action, 'cancellation')) {
        return ['System', 'badge-system', 'fa-gear'];
    }
    return ['Other', 'badge-other', 'fa-circle-info'];
}

/**
 * Build a short readable snippet from the JSON new_value or old_value.
 */
function buildDetailSnippet(?array $data): string
{
    if (empty($data)) return '';
    // Take first 3 key-value pairs for the snippet
    $parts = [];
    $count = 0;
    foreach ($data as $k => $v) {
        if ($count >= 3) { $parts[] = '…'; break; }
        if (is_array($v)) continue;
        $parts[] = htmlspecialchars($k) . ': ' . htmlspecialchars((string)$v);
        $count++;
    }
    return implode(' · ', $parts);
}
