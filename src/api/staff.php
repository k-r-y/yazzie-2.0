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

$currentUser = requireApiRole(['admin', 'frontdesk']);
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
        $where[] = 'is_active = 1';
    }

    $whereClause = implode(' AND ', $where);
    $stmt = $pdo->prepare("
        SELECT id, name, email, role, phone, job_class, is_active,
               DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at
        FROM users
        WHERE $whereClause
        ORDER BY role ASC, name ASC
    ");
    $stmt->execute($params);
    jsonResponse(true, '', ['users' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    foreach (['name', 'email', 'password', 'role'] as $f) {
        if (empty($d[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }

    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(false, 'Invalid email address.', [], 422);
    }

    $requestedRole  = $d['role'];
    $callerRole     = $currentUser['role'] ?? '';

    // ── SUPERADMIN QUOTA ENFORCEMENT ────────────────────────────
    if ($requestedRole === 'super_admin') {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1");
        if ((int)$countStmt->fetchColumn() >= 1) {
            jsonResponse(false, 'Only one Super Admin can exist. Please downgrade an existing Super Admin before creating a new one.', [], 409);
        }
    }

    // Role creation rules:
    // - super_admin  → can create any role
    // - admin        → can only create 'staff' or 'frontdesk'
    // - frontdesk    → cannot reach this endpoint (requireApiRole blocks them)
    if ($callerRole === 'super_admin') {
        $allowedRoles = ['super_admin', 'admin', 'frontdesk', 'staff'];
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

    if (strlen($d['password']) < 8) {
        jsonResponse(false, 'Password must be at least 8 characters.', [], 422);
    }

    // Phone validation — PH mobile format (09XXXXXXXXX or +639XXXXXXXXX)
    if (!empty($d['phone'])) {
        $phone = preg_replace('/\s+/', '', (string)$d['phone']);
        if (!preg_match('/^(09|\+639)\d{9}$/', $phone)) {
            jsonResponse(false, 'Invalid phone number. Use PH format: 09XXXXXXXXX or +639XXXXXXXXX.', [], 422);
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

    $validJobClasses = ['head_cook','cook','waiter','server','helper','any'];
    $jobClass = in_array($d['job_class'] ?? '', $validJobClasses, true) ? $d['job_class'] : 'any';

    $stmt->execute([
        ':name'      => trim($d['name']),
        ':email'     => trim($d['email']),
        ':password'  => password_hash($d['password'], PASSWORD_BCRYPT),
        ':role'      => $requestedRole,
        ':phone'     => !empty($d['phone']) ? trim($d['phone']) : null,
        ':job_class' => $jobClass,
    ]);
    jsonResponse(true, 'User account created.', ['id' => $pdo->lastInsertId()], 201);
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
        if (strlen($d['password']) < 8) jsonResponse(false, 'Password must be at least 8 characters.', [], 422);
        $pwSql = ", password = :pw";
        $params[':pw'] = password_hash($d['password'], PASSWORD_BCRYPT);
    }

    // Phone validation on update
    if (!empty($d['phone'])) {
        $phone = preg_replace('/\s+/', '', (string)$d['phone']);
        if (!preg_match('/^(09|\+639)\d{9}$/', $phone)) {
            jsonResponse(false, 'Invalid phone number. Use PH format: 09XXXXXXXXX or +639XXXXXXXXX.', [], 422);
        }
        $params[':phone'] = $phone;
    }

    // job_class update (staff only)
    $jobClassSql = '';
    if (isset($d['job_class'])) {
        $validJobClasses = ['head_cook','cook','waiter','server','helper','any'];
        $jc = in_array($d['job_class'], $validJobClasses, true) ? $d['job_class'] : 'any';
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
    jsonResponse(true, 'User deactivated.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
