<?php
/**
 * Unified User & Staff Management API
 * GET    — list all users/staff
 * POST   — create user via invitation (no password — email invite flow)
 * PUT    — update user / Master Key Transfer
 * PATCH  — resend invitation email
 * DELETE — deactivate user
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';
require_once __DIR__ . '/../../includes/mailer.php';

// Authorization: admin role is required for all management operations
$currentUser = requireApiRole(['admin']);
requireCsrf();

/**
 * Internal helper — generate a fresh invitation record and queue the
 * setup email. Deletes any pre-existing invitation for this user first.
 *
 * @param int    $userId    Target user's ID
 * @param string $userName  Recipient display name
 * @param string $userEmail Recipient email address
 * @return void
 */
function issueInvitation(PDO $pdo, int $userId, string $userName, string $userEmail): void
{
    // Generate cryptographically secure token + OTP
    $token     = bin2hex(random_bytes(32));          // 64-char hex
    $otp       = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Remove any stale invitation for this user (idempotent resend)
    $pdo->prepare("DELETE FROM user_invitations WHERE user_id = :uid")
        ->execute([':uid' => $userId]);

    // Insert the new invitation record
    $pdo->prepare("
        INSERT INTO user_invitations (user_id, token, otp, expires_at)
        VALUES (:uid, :token, :otp, :exp)
    ")->execute([
        ':uid'   => $userId,
        ':token' => $token,
        ':otp'   => $otp,
        ':exp'   => $expiresAt,
    ]);

    // Build the setup URL (absolute so it works in any email client)
    $setupUrl = BASE_URL . '/views/setup.php?token=' . urlencode($token);

    // Compose the invitation email
    $subject = 'You\'ve been invited to ' . APP_NAME . ' — Complete Your Account Setup';
    $content = "
        <h2 style='margin: 0 0 12px; font-size: 22px; font-weight: 800; color: #1C1C1E; letter-spacing: -0.8px;'>Welcome to the Team, {$userName}!</h2>
        <p style='margin: 0 0 28px; font-size: 15px; color: rgba(60, 60, 67, 0.6); line-height: 1.6;'>An administrator has created an account for you on the Yazzies Catering OMS. To activate your account, you'll need your <strong>One-Time PIN</strong> and the button below to set your password.</p>

        <div style='background-color: #F8F8FA; border-radius: 24px; padding: 40px; margin-bottom: 32px; border: 1px solid rgba(60, 60, 67, 0.08); text-align: center;'>
            <div style='font-size: 11px; color: rgba(60, 60, 67, 0.4); font-weight: 700; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 16px;'>Your One-Time PIN</div>
            <div style='font-size: 52px; font-weight: 800; color: #30D158; letter-spacing: 14px; font-family: monospace;'>{$otp}</div>
        </div>

        <div style='text-align: center; margin-bottom: 32px;'>
            <a href='{$setupUrl}' class='btn-primary' style='display:inline-block; background:#30D158; color:#fff; padding:14px 32px; border-radius:12px; font-weight:700; font-size:14px; text-decoration:none;'>Complete Account Setup &rarr;</a>
        </div>

        <p style='margin: 0; font-size: 13px; color: rgba(60, 60, 67, 0.4); text-align: center;'>This link and PIN expire in <strong>24 hours</strong>. If you did not expect this email, please ignore it.</p>
    ";

    $html = renderEmailTemplate(
        'Account Invitation',
        '🔐',
        $content,
        '#30D158',
        "Your OTP is {$otp} — complete your account setup now."
    );

    // Use immediate send so the admin gets instant feedback if SMTP fails;
    // log the error but don't abort the user creation.
    $sent = sendMailImmediate($userEmail, $userName, $subject, $html);
    if (!$sent) {
        error_log("[Invite] Failed to send invitation email to {$userEmail} (user_id={$userId}). Check SMTP config.");
    }
}

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

    // Password is no longer accepted at creation time — it is set by the
    // user themselves via the Email Invitation & Self-Setup flow.
    foreach (['name', 'email', 'role'] as $f) {
        if (empty($d[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }

    // Role Validation
    $requestedRole = $d['role'];
    $allowedRoles  = ['admin', 'frontdesk', 'staff'];
    if (!in_array($requestedRole, $allowedRoles, true)) {
        jsonResponse(false, 'Invalid role specified.', [], 403);
    }

    // All newly created accounts start as "Pending Invite" (is_active = 2)
    // regardless of role. is_active transitions to 1 (active) or 0 (dormant)
    // only AFTER the user completes the self-setup flow.
    $isActive = 2;

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

    // Insert user with NULL password — password is set during self-setup
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role, phone, job_class, is_active)
        VALUES (:name, :email, NULL, :role, :phone, :job_class, :is_active)
    ");
    $stmt->execute([
        ':name'      => trim($d['name']),
        ':email'     => trim($d['email']),
        ':role'      => $requestedRole,
        ':phone'     => !empty($d['phone']) ? trim($d['phone']) : null,
        ':job_class' => $jobClass,
        ':is_active' => $isActive,
    ]);

    $newId = (int)$pdo->lastInsertId();
    auditLog($pdo, 'user_invited', 'user', $newId, null, ['name' => trim($d['name']), 'role' => $requestedRole]);

    // Issue the invitation: generate token+OTP and send the setup email
    issueInvitation($pdo, $newId, trim($d['name']), trim($d['email']));

    jsonResponse(true, 'Invitation sent! The user will receive an email with their setup link and OTP.', ['id' => $newId], 201);
}

// ── PATCH: Resend Invitation ──────────────────────────────────────────────
if ($method === 'PATCH') {
    $d      = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = trim($d['action'] ?? '');
    $uid    = (int)($d['id'] ?? 0);

    if ($action !== 'resend_invite' || !$uid) {
        jsonResponse(false, 'Invalid PATCH action or missing user ID.', [], 422);
    }

    // Fetch the target user — must be in Pending Invite state
    $stmt = $pdo->prepare("SELECT id, name, email, is_active FROM users WHERE id = :id");
    $stmt->execute([':id' => $uid]);
    $target = $stmt->fetch();

    if (!$target) jsonResponse(false, 'User not found.', [], 404);
    if ((int)$target['is_active'] !== 2) {
        jsonResponse(false, 'This user is not in a Pending Invite state. Only pending users can have invitations resent.', [], 409);
    }

    issueInvitation($pdo, (int)$target['id'], $target['name'], $target['email']);
    auditLog($pdo, 'invite_resent', 'user', (int)$target['id']);

    jsonResponse(true, "Invitation resent to {$target['name']}.");
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
             $validJobClasses = ['head_cook','cook','waiter','server','helper'];
             $updates[] = "job_class = :jc_manual";
             $params[':jc_manual'] = in_array($d['job_class'], $validJobClasses, true) ? $d['job_class'] : 'waiter';
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
