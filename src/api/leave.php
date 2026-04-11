<?php
/**
 * Leave Request API
 * GET  ?my_leaves=1          — staff: own leave requests
 * GET  ?date=YYYY-MM-DD      — admin/frontdesk: who is on leave on date
 * GET  ?pending_only=1       — admin: all pending leave requests
 * POST                       — staff: submit leave request { leave_date, reason }
 * PUT                        — admin: review { id, status: approved|rejected }
 * DELETE                     — staff: cancel own pending request { id }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/mailer.php';

$currentUser = requireApiRole(['admin', 'frontdesk', 'staff']);
$method      = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    // Staff: view own leave requests
    if (isset($_GET['my_leaves'])) {
        requireApiRole('staff');
        $stmt = $pdo->prepare("
            SELECT lr.*, u2.name AS reviewed_by_name
            FROM leave_requests lr
            LEFT JOIN users u2 ON u2.id = lr.reviewed_by
            WHERE lr.staff_id = :uid
            ORDER BY lr.leave_date DESC
        ");
        $stmt->execute([':uid' => $currentUser['id']]);
        jsonResponse(true, '', ['leaves' => $stmt->fetchAll()]);
    }

    // Admin/Frontdesk: who is on approved leave on a specific date
    if (isset($_GET['date'])) {
        requireApiRole(['admin', 'frontdesk']);
        $stmt = $pdo->prepare("
            SELECT lr.staff_id, u.name, u.phone, lr.status
            FROM leave_requests lr
            JOIN users u ON u.id = lr.staff_id
            WHERE lr.leave_date = :d AND lr.status = 'approved'
        ");
        $stmt->execute([':d' => $_GET['date']]);
        jsonResponse(true, '', ['on_leave' => $stmt->fetchAll()]);
    }

    // Admin: all pending leave requests
    if (isset($_GET['pending_only'])) {
        requireApiRole(['admin', 'frontdesk']);
        $stmt = $pdo->query("
            SELECT lr.*, u.name AS staff_name, u.phone AS staff_phone
            FROM leave_requests lr
            JOIN users u ON u.id = lr.staff_id
            WHERE lr.status = 'pending'
            ORDER BY lr.leave_date ASC, lr.created_at ASC
        ");
        jsonResponse(true, '', ['leaves' => $stmt->fetchAll()]);
    }

    // Admin: all leave requests (with filters)
    requireApiRole(['admin', 'frontdesk']);
    $where  = ['1=1'];
    $params = [];
    if (!empty($_GET['status'])) {
        $where[] = 'lr.status = :status';
        $params[':status'] = $_GET['status'];
    }
    if (!empty($_GET['staff_id'])) {
        $where[] = 'lr.staff_id = :sid';
        $params[':sid'] = (int)$_GET['staff_id'];
    }
    $wc = implode(' AND ', $where);
    $stmt = $pdo->prepare("
        SELECT lr.*, u.name AS staff_name, u.phone AS staff_phone,
               u2.name AS reviewed_by_name
        FROM leave_requests lr
        JOIN users u  ON u.id  = lr.staff_id
        LEFT JOIN users u2 ON u2.id = lr.reviewed_by
        WHERE $wc
        ORDER BY lr.leave_date DESC
    ");
    $stmt->execute($params);
    jsonResponse(true, '', ['leaves' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    requireApiRole('staff');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['leave_date'])) {
        jsonResponse(false, 'leave_date is required.', [], 422);
    }
    // Validate date format and not in the past
    $leaveDate = $d['leave_date'];
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $leaveDate)) {
        jsonResponse(false, 'Invalid date format. Use YYYY-MM-DD.', [], 422);
    }
    if ($leaveDate < date('Y-m-d')) {
        jsonResponse(false, 'Cannot request leave for a past date.', [], 422);
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO leave_requests (staff_id, leave_date, reason)
            VALUES (:sid, :date, :reason)
        ");
        $stmt->execute([
            ':sid'    => $currentUser['id'],
            ':date'   => $leaveDate,
            ':reason' => $d['reason'] ?? null,
        ]);
        jsonResponse(true, 'Leave request submitted. Awaiting admin review.', [
            'id' => $pdo->lastInsertId()
        ], 201);
    } catch (\PDOException $e) {
        if ($e->getCode() === '23000') {
            jsonResponse(false, 'You already have a leave request for this date.', [], 409);
        }
        throw $e;
    }
}

if ($method === 'PUT') {
    requireApiRole(['admin', 'frontdesk']);
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id']) || empty($d['status'])) {
        jsonResponse(false, 'id and status are required.', [], 422);
    }
    if (!in_array($d['status'], ['approved', 'rejected'], true)) {
        jsonResponse(false, 'Status must be approved or rejected.', [], 422);
    }

    // Get leave + staff info for email
    $lr = $pdo->prepare("
        SELECT lr.*, u.name AS staff_name, u.email AS staff_email
        FROM leave_requests lr
        JOIN users u ON u.id = lr.staff_id
        WHERE lr.id = :id
    ");
    $lr->execute([':id' => (int)$d['id']]);
    $leave = $lr->fetch();
    if (!$leave) jsonResponse(false, 'Leave request not found.', [], 404);
    if ($leave['status'] !== 'pending') {
        jsonResponse(false, "This request has already been {$leave['status']}.", [], 409);
    }

    $pdo->prepare("
        UPDATE leave_requests
        SET status = :status, reviewed_by = :by, reviewed_at = NOW()
        WHERE id = :id
    ")->execute([
        ':status' => $d['status'],
        ':by'     => $currentUser['id'],
        ':id'     => (int)$d['id'],
    ]);

    // In-app notification
    $notifTitle = $d['status'] === 'approved'
        ? '✅ Leave Approved'
        : '❌ Leave Request Rejected';
    $notifBody = $d['status'] === 'approved'
        ? "Your leave request for " . date('F j, Y', strtotime($leave['leave_date'])) . " has been approved."
        : "Your leave request for " . date('F j, Y', strtotime($leave['leave_date'])) . " was not approved.";

    $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, body)
        VALUES (:uid, :type, :title, :body)
    ")->execute([
        ':uid'   => $leave['staff_id'],
        ':type'  => 'leave_' . $d['status'],
        ':title' => $notifTitle,
        ':body'  => $notifBody,
    ]);

    // Email notification
    if ($leave['staff_email']) {
        sendLeaveStatusEmail(
            ['name' => $leave['staff_name'], 'email' => $leave['staff_email']],
            $leave['leave_date'],
            $d['status']
        );
    }

    jsonResponse(true, "Leave request {$d['status']}.");
}

if ($method === 'DELETE') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'id is required.', [], 422);

    // Staff can only cancel their own; admin can cancel any
    $whereExtra = '';
    $params = [':id' => (int)$d['id']];
    if ($currentUser['role'] === 'staff') {
        $whereExtra = ' AND staff_id = :sid';
        $params[':sid'] = $currentUser['id'];
    }

    $check = $pdo->prepare("SELECT status FROM leave_requests WHERE id = :id $whereExtra");
    $check->execute($params);
    $row = $check->fetch();
    if (!$row) jsonResponse(false, 'Not found or access denied.', [], 404);
    if ($row['status'] !== 'pending') {
        jsonResponse(false, 'Only pending requests can be cancelled.', [], 409);
    }

    $pdo->prepare("DELETE FROM leave_requests WHERE id = :id $whereExtra")->execute($params);
    jsonResponse(true, 'Leave request cancelled.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
