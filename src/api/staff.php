<?php
/**
 * Staff (User) API
 * GET    — list staff / all users
 * POST   — create user (admin only)
 * PUT    — update user (admin only)
 * DELETE — deactivate user (admin only)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

$currentUser = requireApiRole(['admin', 'frontdesk']);
requireCsrf();
$method      = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // ── Availability check for a specific date ──────────────────────
    if (isset($_GET['available_on'])) {
        $date = $_GET['available_on'];
        $excludeBookingId = (int)($_GET['exclude_booking'] ?? 0);

        // Get all active staff
        $staffStmt = $pdo->query("
            SELECT id, name, email, phone, role, job_class,
                   DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at
            FROM users
            WHERE role = 'staff' AND is_active = 1
            ORDER BY name ASC
        ");
        $staff = $staffStmt->fetchAll();

        // Staff on approved leave that day
        $onLeaveStmt = $pdo->prepare("
            SELECT staff_id FROM leave_requests
            WHERE leave_date = :d AND status = 'approved'
        ");
        $onLeaveStmt->execute([':d' => $date]);
        $onLeave = array_column($onLeaveStmt->fetchAll(), 'staff_id');

        // Staff already accepted for a different booking that day (via job_orders)
        $bookedStmt = $pdo->prepare("
            SELECT DISTINCT jo.staff_id
            FROM job_orders jo
            JOIN bookings b ON b.id = jo.booking_id
            WHERE b.event_date = :d
              AND b.booking_status NOT IN ('cancelled')
              AND jo.status = 'accepted'
              AND (:excl = 0 OR jo.booking_id != :excl2)
        ");
        $bookedStmt->execute([':d' => $date, ':excl' => $excludeBookingId, ':excl2' => $excludeBookingId]);
        $alreadyBooked = array_column($bookedStmt->fetchAll(), 'staff_id');

        // Annotate each staff member
        foreach ($staff as &$s) {
            if (in_array($s['id'], $onLeave)) {
                $s['availability'] = 'on_leave';
            } elseif (in_array($s['id'], $alreadyBooked)) {
                $s['availability'] = 'booked';
            } else {
                $s['availability'] = 'available';
            }
        }
        unset($s);

        jsonResponse(true, '', ['staff' => $staff, 'date' => $date]);
    }

    $role   = $_GET['role']    ?? '';
    $where  = ['1=1'];
    $params = [];

    if ($role) {
        $where[]           = 'role = :role';
        $params[':role']   = $role;
    }

    // Frontdesk can only see staff list; admin sees all
    if ($currentUser['role'] === 'frontdesk') {
        $where[]           = 'role = :role_staff';
        $params[':role_staff'] = 'staff';
    }

    if (isset($_GET['active_only'])) {
        $where[] = 'is_active = :active';
        $params[':active'] = (int)$_GET['active_only'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(name LIKE :search OR email LIKE :search OR phone LIKE :search)';
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $whereClause = implode(' AND ', $where);

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if ($limit < 1) $limit = 10;
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $whereClause");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalRecords / $limit);

    $stmt = $pdo->prepare("
        SELECT id, name, email, role, phone, job_class, is_active,
               DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at
        FROM users
        WHERE $whereClause
        ORDER BY role ASC, name ASC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    jsonResponse(true, '', [
        'users' => $stmt->fetchAll(),
        'meta' => [
            'currentPage'  => $page,
            'totalPages'   => $totalPages,
            'totalRecords' => $totalRecords,
        ]
    ]);
}

if ($method === 'POST') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    foreach (['name', 'email', 'password', 'role'] as $f) {
        if (empty($d[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }

    if (strlen($d['name']) > 100) jsonResponse(false, 'Name too long.', [], 422);
    if (!preg_match('/^[a-zA-Z\s\-\.]+$/', trim($d['name']))) {
        jsonResponse(false, 'Invalid name format. Only letters, spaces, hyphens, and periods are allowed.', [], 422);
    }

    if (strlen($d['email']) > 100) jsonResponse(false, 'Email too long.', [], 422);
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Invalid email address.', [], 422);
    }

    $requestedRole  = $d['role'];
    $callerRole     = $currentUser['role'] ?? '';

    // ── PREVENT SUPER_ADMIN CREATION ────────────────────────────
    // Only 1 superadmin can exist in the system; it cannot be created, only assigned
    if ($requestedRole === 'super_admin') {
        jsonResponse(false, 'Super Admin accounts cannot be created. Only a system administrator can promote an existing admin.', [], 403);
    }

    // ── ADMIN QUOTA ENFORCEMENT ────────────────────────────
    if ($requestedRole === 'admin') {
        $maxAdmins = appSettingInt('max_admins', 5);
        $activeAdmins = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
        if ((int)$activeAdmins >= $maxAdmins) {
            jsonResponse(false, "Maximum number of administrators ($maxAdmins) reached. Deactivate an admin account before creating a new one.", [], 409);
        }
    }

    // Role creation rules:
    // - super_admin  → can create admin, frontdesk, staff (not super_admin)
    // - admin        → can only create 'staff' or 'frontdesk'
    // - frontdesk    → cannot reach this endpoint (requireApiRole blocks them)
    if ($callerRole === 'super_admin') {
        $allowedRoles = ['admin', 'frontdesk', 'staff'];
    } else {
        // Regular admin: cannot create admin or super_admin
        $allowedRoles = ['frontdesk', 'staff'];
    }

    if (!in_array($requestedRole, $allowedRoles, true)) {
        jsonResponse(false,
            $callerRole === 'admin'
                ? 'Administrators can only create Staff or Front Desk accounts. Contact the Super Admin to create admin-level accounts.'
                : 'Invalid role specified.',
            [], 403
        );
    }

    // Password policy enforcement
    $pwError = validatePasswordPolicy($d['password']);
    if ($pwError) jsonResponse(false, $pwError, [], 422);

    // Phone validation — 11 digits only, no letters
    if (!empty($d['phone'])) {
        $phone = preg_replace('/[^\d]/', '', (string)$d['phone']); // Strip all non-digits
        if (strlen($phone) !== 11) {
            jsonResponse(false, 'Phone number must be exactly 11 digits.', [], 422);
        }
    }

    // Check duplicate email
    $dup = $pdo->prepare("SELECT id FROM users WHERE email = :e");
    $dup->execute([':e' => $d['email']]);
    if ($dup->fetch()) jsonResponse(false, 'Email address is already in use.', [], 409);

    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, phone, job_class)
        VALUES (:name, :email, :password, :role, :phone, :job_class)
    ");

    if (in_array($requestedRole, ['admin', 'super_admin', 'frontdesk'], true)) {
        $jobClass = $requestedRole;
    } else {
        $validJobClasses = ['head_cook','cook','waiter','server','helper'];
        $jobClass = in_array($d['job_class'] ?? '', $validJobClasses, true) ? $d['job_class'] : 'waiter';
    }

    $stmt->execute([
        ':name'      => trim($d['name']),
        ':email'     => trim($d['email']),
        ':password'  => password_hash($d['password'], PASSWORD_BCRYPT),
        ':role'      => $requestedRole,
        ':phone'     => !empty($d['phone']) ? trim($d['phone']) : null,
        ':job_class' => $jobClass,
    ]);
    $newId = $pdo->lastInsertId();

    // Audit: user created
    auditLog($pdo, 'user_created', 'user', (int)$newId,
        null,
        ['name' => trim($d['name']), 'role' => $requestedRole, 'email' => trim($d['email'])]
    );

    jsonResponse(true, 'User account created.', ['id' => $newId], 201);
}

if ($method === 'PUT') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'User ID required.', [], 422);

    // Update password only if supplied
    $pwSql = "";
    // Role updates: only super_admin may assign super_admin
    $newRole = $d['role'] ?? null;
    if ($newRole === 'super_admin' && ($currentUser['role'] ?? '') !== 'super_admin') {
        jsonResponse(false, 'Only Super Admin can assign the Super Admin role.', [], 403);
    }

    // If changing to super_admin, verify quota
    if ($newRole === 'super_admin') {
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE role = 'super_admin' AND is_active = 1 LIMIT 1");
        $checkStmt->execute();
        $existing = $checkStmt->fetch();
        if ($existing && (int)$existing['id'] !== (int)$d['id']) {
            jsonResponse(false, 'Cannot promote to Super Admin. Only one Super Admin account is permitted.', [], 409);
        }
    }

    $params = [
        ':id'        => (int)$d['id'],
        ':name'      => $d['name']      ?? null,
        ':email'     => $d['email']     ?? null,
        ':role'      => $newRole,
        ':phone'     => $d['phone']     ?? null,
        ':is_active' => isset($d['is_active']) ? (int)$d['is_active'] : null,
    ];

    if (!empty($d['password'])) {
        $pwError = validatePasswordPolicy($d['password']);
        if ($pwError) jsonResponse(false, $pwError, [], 422);
        $pwSql = ", password = :pw";
        $params[':pw'] = password_hash($d['password'], PASSWORD_BCRYPT);
    }

    // Name validation
    if (isset($d['name']) && !preg_match('/^[a-zA-Z\s\-\.]+$/', trim($d['name']))) {
        jsonResponse(false, 'Invalid name format. Only letters, spaces, hyphens, and periods are allowed.', [], 422);
    }

    // Phone validation on update — 11 digits only, no letters
    if (!empty($d['phone'])) {
        $phone = preg_replace('/[^\d]/', '', (string)$d['phone']); // Strip all non-digits
        if (strlen($phone) !== 11) {
            jsonResponse(false, 'Phone number must be exactly 11 digits.', [], 422);
        }
        $params[':phone'] = $phone;
    }

    // job_class update
    $jobClassSql = '';
    
    // Always sync job class with the new or existing role based on context
    // If role is being changed to admin/frontdesk, or if it already is one and being updated.
    // For simplicity, we just look at the $newRole if provided.
    // However, PUT doesn't strictly provide $newRole if not changed, but in our UI it always does.
    if ($newRole && in_array($newRole, ['admin', 'super_admin', 'frontdesk'], true)) {
        $jobClassSql = ', job_class = :job_class';
        $params[':job_class'] = $newRole;
    } elseif ($newRole === 'staff' || (!$newRole && isset($d['job_class']))) {
        $validJobClasses = ['head_cook','cook','waiter','server','helper'];
        $jc = in_array($d['job_class'] ?? '', $validJobClasses, true) ? $d['job_class'] : 'waiter';
        $jobClassSql = ', job_class = :job_class';
        $params[':job_class'] = $jc;
    }

    $stmt = $pdo->prepare("
        UPDATE users SET
            name      = COALESCE(:name, name),
            email     = COALESCE(:email, email),
            role      = COALESCE(:role, role),
            phone     = :phone,
            is_active = COALESCE(:is_active, is_active)
            $pwSql
            $jobClassSql
        WHERE id = :id
    ");
    $stmt->execute($params);

    // Audit: user updated
    auditLog($pdo, 'user_updated', 'user', (int)$d['id'],
        null,
        array_filter([
            'name' => $d['name'] ?? null,
            'role' => $newRole,
            'is_active' => $d['is_active'] ?? null,
        ], fn($v) => $v !== null)
    );

    jsonResponse(true, 'User updated.');
}

if ($method === 'DELETE') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'User ID required.', [], 422);
    // Prevent self-deletion
    if ((int)$d['id'] === (int)$_SESSION['user_id']) {
        jsonResponse(false, 'You cannot deactivate your own account.', [], 403);
    }
    // Soft delete (deactivate)
    $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = :id")->execute([':id' => (int)$d['id']]);

    // Audit: user deactivated
    auditLog($pdo, 'user_deactivated', 'user', (int)$d['id'],
        ['is_active' => 1],
        ['is_active' => 0]
    );

    jsonResponse(true, 'User deactivated.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
