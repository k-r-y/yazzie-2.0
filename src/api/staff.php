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
            SELECT id, name, email, phone, role
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

        // Staff already assigned to a different booking that day
        $bookedStmt = $pdo->prepare("
            SELECT DISTINCT bs.staff_id
            FROM booking_staff bs
            JOIN bookings b ON b.id = bs.booking_id
            WHERE b.event_date = :d
              AND b.booking_status NOT IN ('cancelled')
              AND (:excl = 0 OR bs.booking_id != :excl2)
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
        SELECT id, name, email, role, phone, is_active, created_at
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

    if (!in_array($d['role'], ['admin', 'frontdesk', 'staff'], true)) {
        jsonResponse(false, 'Invalid role specified.', [], 422);
    }

    if (strlen($d['password']) < 8) {
        jsonResponse(false, 'Password must be at least 8 characters.', [], 422);
    }

    // Check duplicate email
    $dup = $pdo->prepare("SELECT id FROM users WHERE email = :e");
    $dup->execute([':e' => $d['email']]);
    if ($dup->fetch()) jsonResponse(false, 'Email address is already in use.', [], 409);

    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, phone)
        VALUES (:name, :email, :password, :role, :phone)
    ");
    $stmt->execute([
        ':name'     => trim($d['name']),
        ':email'    => trim($d['email']),
        ':password' => password_hash($d['password'], PASSWORD_BCRYPT),
        ':role'     => $d['role'],
        ':phone'    => trim($d['phone'] ?? ''),
    ]);
    jsonResponse(true, 'User account created.', ['id' => $pdo->lastInsertId()], 201);
}

if ($method === 'PUT') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'User ID required.', [], 422);

    // Update password only if supplied
    $pwSql = "";
    $params = [
        ':id'        => (int)$d['id'],
        ':name'      => $d['name']      ?? null,
        ':email'     => $d['email']     ?? null,
        ':role'      => $d['role']      ?? null,
        ':phone'     => $d['phone']     ?? null,
        ':is_active' => isset($d['is_active']) ? (int)$d['is_active'] : null,
    ];

    if (!empty($d['password'])) {
        if (strlen($d['password']) < 8) jsonResponse(false, 'Password must be at least 8 characters.', [], 422);
        $pwSql = ", password = :pw";
        $params[':pw'] = password_hash($d['password'], PASSWORD_BCRYPT);
    }

    $stmt = $pdo->prepare("
        UPDATE users SET
            name      = COALESCE(:name, name),
            email     = COALESCE(:email, email),
            role      = COALESCE(:role, role),
            phone     = :phone,
            is_active = COALESCE(:is_active, is_active)
            $pwSql
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
