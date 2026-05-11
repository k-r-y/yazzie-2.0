<?php
/**
 * Unified User & Staff Management API
 * GET    — list all users/staff
 * POST   — create user (admin only)
 * PUT    — update user / Master Key Transfer
 * DELETE — deactivate user
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

// Authorization: admin role is required for all management operations
$currentUser = requireApiRole(['admin']);
requireCsrf();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $role   = $_GET['role']    ?? '';
    $where  = ['1=1'];
    $params = [];

    if ($role) {
        $roles = explode(',', $role);
        $placeholders = [];
        foreach ($roles as $idx => $r) {
            $key = ":role_list_$idx";
            $placeholders[] = $key;
            $params[$key] = trim($r);
        }
        $where[] = 'role IN (' . implode(',', $placeholders) . ')';
    }

    // Availability check for dispatching
    if (isset($_GET['available_on'])) {
        $date = $_GET['available_on'];
        $excludeBookingId = (int)($_GET['exclude_booking'] ?? 0);

        $availWhere = $where;
        $availWhere[] = 'is_active = 1';
        $whereClause = implode(' AND ', $availWhere);

        $staffStmt = $pdo->prepare("
            SELECT id, name, email, phone, role, job_class,
                   DATE_FORMAT(created_at, '%Y-%m-%dT%H:%i:%s') AS created_at
            FROM users
            WHERE $whereClause
            ORDER BY name ASC
        ");
        $staffStmt->execute($params);
        $staff = $staffStmt->fetchAll();

        $onLeaveStmt = $pdo->prepare("
            SELECT staff_id FROM leave_requests
            WHERE leave_date = :d AND status = 'approved'
        ");
        $onLeaveStmt->execute([':d' => $date]);
        $onLeave = array_column($onLeaveStmt->fetchAll(), 'staff_id');

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

    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $where[] = 'is_active = :status';
        $params[':status'] = (int)$_GET['status'];
    }

    if (!empty($_GET['job_class'])) {
        $where[] = 'job_class = :job_class';
        $params[':job_class'] = $_GET['job_class'];
    }

    if (!empty($_GET['search'])) {
        $where[] = '(name LIKE :search OR email LIKE :search OR phone LIKE :search)';
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $whereClause = implode(' AND ', $where);

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
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
        ORDER BY FIELD(role, 'admin', 'frontdesk', 'staff'), name ASC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $value) $stmt->bindValue($key, $value);
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
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    foreach (['name', 'email', 'password', 'role'] as $f) {
        if (empty($d[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }

    // Role Validation
    $requestedRole = $d['role'];
    $allowedRoles  = ['admin', 'frontdesk', 'staff'];
    if (!in_array($requestedRole, $allowedRoles, true)) {
        jsonResponse(false, 'Invalid role specified.', [], 403);
    }

    // CREATION GUARD: Force admin to be inactive by default
    $isActive = ($requestedRole === 'admin') ? 0 : 1;

    // Password Policy
    $pwError = validatePasswordPolicy($d['password']);
    if ($pwError) jsonResponse(false, $pwError, [], 422);

    // Duplicate Email Check
    $dup = $pdo->prepare("SELECT id FROM users WHERE email = :e");
    $dup->execute([':e' => trim($d['email'])]);
    if ($dup->fetch()) jsonResponse(false, 'Email address is already in use.', [], 409);

    // Phone validation
    if (!empty($d['phone'])) {
        if (!preg_match('/^09\d{9}$/', $d['phone'])) {
            jsonResponse(false, 'Phone number must be exactly 11 digits starting with 09.', [], 422);
        }
    }

    // Prepare Job Class
    if (in_array($requestedRole, ['admin', 'frontdesk'], true)) {
        $jobClass = $requestedRole;
    } else {
        $validJobClasses = ['head_cook','cook','waiter','server','helper'];
        $jobClass = in_array($d['job_class'] ?? '', $validJobClasses, true) ? $d['job_class'] : 'waiter';
    }

    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, phone, job_class, is_active)
        VALUES (:name, :email, :password, :role, :phone, :job_class, :is_active)
    ");

    $stmt->execute([
        ':name'      => trim($d['name']),
        ':email'     => trim($d['email']),
        ':password'  => password_hash($d['password'], PASSWORD_BCRYPT),
        ':role'      => $requestedRole,
        ':phone'     => !empty($d['phone']) ? trim($d['phone']) : null,
        ':job_class' => $jobClass,
        ':is_active' => $isActive
    ]);
    
    $newId = $pdo->lastInsertId();
    auditLog($pdo, 'user_created', 'user', (int)$newId, null, ['name' => $d['name'], 'role' => $requestedRole]);

    jsonResponse(true, 'User account created.', ['id' => $newId], 201);
}

if ($method === 'PUT') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $target_id = (int)($d['id'] ?? 0);
    if (!$target_id) jsonResponse(false, 'User ID required.', [], 422);

    $current_admin_id = (int)$_SESSION['user_id'];

    // SELF-LOCKOUT GUARD: Block current admin from deactivating or changing their own role
    if ($target_id === $current_admin_id) {
        if (isset($d['is_active']) && (int)$d['is_active'] === 0) {
            jsonResponse(false, 'You cannot deactivate yourself. Transfer the Master Key to another admin instead.', [], 403);
        }
        if (isset($d['role']) && $d['role'] !== 'admin') {
             jsonResponse(false, 'Self-lockout guard: You cannot demote yourself.', [], 403);
        }
    }

    // Fetch target user current state
    $stmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ?");
    $stmt->execute([$target_id]);
    $targetUser = $stmt->fetch();
    if (!$targetUser) jsonResponse(false, 'User not found.', [], 404);

    $adminTransferred = false;

    // MASTER KEY TRANSFER LOGIC
    // If activating another admin, transfer authority (deactivate current session)
    if ($targetUser['role'] === 'admin' && isset($d['is_active']) && (int)$d['is_active'] === 1 && (int)$targetUser['is_active'] === 0) {
        try {
            $pdo->beginTransaction();

            // 1. Activate the target admin
            $pdo->prepare("UPDATE users SET is_active = 1 WHERE id = ?")->execute([$target_id]);

            // 2. Deactivate the current admin
            $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$current_admin_id]);

            $pdo->commit();
            $adminTransferred = true;
            
            auditLog($pdo, 'admin_master_transfer', 'user', $target_id, ['from' => $current_admin_id]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Failed to transfer admin authority.', [], 500);
        }
    }

    // Proceed with regular updates if not transferred or if additional fields exist
    if (!$adminTransferred) {
        $updates = [];
        $params  = [':id' => $target_id];

        if (isset($d['name'])) {
            $updates[] = "name = :name";
            $params[':name'] = trim($d['name']);
        }
        if (isset($d['email'])) {
            $updates[] = "email = :email";
            $params[':email'] = trim($d['email']);
        }
        if (isset($d['role'])) {
            $updates[] = "role = :role";
            $params[':role'] = $d['role'];
            
            // Sync job class for admin/frontdesk
            if (in_array($d['role'], ['admin', 'frontdesk'])) {
                $updates[] = "job_class = :jc";
                $params[':jc'] = $d['role'];
            }
        }
        if (isset($d['phone'])) {
            $phone = trim($d['phone']);
            if ($phone !== '' && !preg_match('/^09\d{9}$/', $phone)) {
                jsonResponse(false, 'Phone number must be exactly 11 digits starting with 09.', [], 422);
            }
            $updates[] = "phone = :phone";
            $params[':phone'] = $phone;
        }
        if (isset($d['is_active']) && $target_id !== $current_admin_id) {
            $updates[] = "is_active = :active";
            $params[':active'] = (int)$d['is_active'];
        }
        if (!empty($d['password'])) {
            $pwError = validatePasswordPolicy($d['password']);
            if ($pwError) jsonResponse(false, $pwError, [], 422);
            $updates[] = "password = :pw";
            $params[':pw'] = password_hash($d['password'], PASSWORD_BCRYPT);
        }
        if (isset($d['job_class']) && (!isset($d['role']) || $d['role'] === 'staff')) {
             $updates[] = "job_class = :jc_manual";
             $params[':jc_manual'] = $d['job_class'];
        }

        if ($updates) {
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = :id";
            $pdo->prepare($sql)->execute($params);
            auditLog($pdo, 'user_updated', 'user', $target_id);
        }
    }

    jsonResponse(true, 'User updated.', ['admin_transferred' => $adminTransferred]);
}

if ($method === 'DELETE') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($d['id'] ?? 0);
    if (!$id) jsonResponse(false, 'User ID required.', [], 422);

    // SELF-LOCKOUT GUARD
    if ($id === (int)$_SESSION['user_id']) {
        jsonResponse(false, 'You cannot deactivate yourself. Transfer the Master Key to another admin instead.', [], 403);
    }

    // Soft delete
    $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$id]);
    auditLog($pdo, 'user_deactivated', 'user', $id);

    jsonResponse(true, 'User deactivated.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
