<?php
/**
 * Dispatching API — Job Orders for On-Call Staff
 * GET    ?booking_id=X  — get job orders for a booking
 * GET    ?my_jobs=1     — staff sees their own jobs
 * POST   — create job order(s) (frontdesk/admin)
 * PUT    — staff responds (accept/decline)
 * DELETE — cancel a job order (frontdesk/admin)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/sms.php';

$currentUser = requireApiRole(['admin', 'frontdesk', 'staff']);
$method      = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {

    // Staff: see their own pending / upcoming jobs
    if (isset($_GET['my_jobs'])) {
        requireApiRole('staff');
        $stmt = $pdo->prepare("
            SELECT jo.id, jo.booking_id, jo.role_required, jo.status, jo.sent_at, jo.notes,
                   b.event_date, b.event_time, b.event_location, b.pax_count,
                   c.name AS client_name,
                   COALESCE(pk.set_name, m.name, 'Package Booking') AS menu_name
            FROM job_orders jo
            JOIN bookings b  ON b.id  = jo.booking_id
            JOIN clients  c  ON c.id  = b.client_id
            LEFT JOIN menus    m  ON m.id  = b.menu_id
            LEFT JOIN packages pk ON pk.id = b.package_id
            WHERE jo.staff_id = :uid
            ORDER BY b.event_date ASC, jo.sent_at DESC
        ");
        $stmt->execute([':uid' => $currentUser['id']]);
        jsonResponse(true, '', ['job_orders' => $stmt->fetchAll()]);
    }

    // Front desk / admin: jobs for a specific booking
    if (isset($_GET['booking_id'])) {
        requireApiRole(['admin', 'frontdesk']);
        $stmt = $pdo->prepare("
            SELECT jo.*, u.name AS staff_name, u.phone AS staff_phone, u.role AS staff_role
            FROM job_orders jo
            JOIN users u ON u.id = jo.staff_id
            WHERE jo.booking_id = :bid
            ORDER BY jo.sent_at DESC
        ");
        $stmt->execute([':bid' => (int)$_GET['booking_id']]);
        jsonResponse(true, '', ['job_orders' => $stmt->fetchAll()]);
    }

    // All pending job orders (frontdesk dashboard count)
    requireApiRole(['admin', 'frontdesk']);
    $stmt = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status = 'pending'");
    jsonResponse(true, '', ['pending_count' => (int)$stmt->fetchColumn()]);
}

if ($method === 'POST') {
    requireApiRole(['admin', 'frontdesk']);
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['booking_id']) || empty($d['staff_ids']) || empty($d['role_required'])) {
        jsonResponse(false, 'booking_id, staff_ids (array), and role_required are required.', [], 422);
    }

    $stmt = $pdo->prepare("
        INSERT INTO job_orders (booking_id, staff_id, role_required, notes)
        VALUES (:booking_id, :staff_id, :role, :notes)
    ");

    // Get booking details for SMS
    $bStmt = $pdo->prepare("
        SELECT b.event_date, b.event_time, b.event_location, c.name AS client_name
        FROM bookings b JOIN clients c ON c.id = b.client_id WHERE b.id = :id
    ");
    $bStmt->execute([':id' => (int)$d['booking_id']]);
    $booking = $bStmt->fetch();

    $created = [];
    foreach ((array)$d['staff_ids'] as $staffId) {
        // Avoid duplicate active job orders for same booking+staff
        $dupCheck = $pdo->prepare("
            SELECT id FROM job_orders
            WHERE booking_id = :bid AND staff_id = :sid AND status = 'pending'
        ");
        $dupCheck->execute([':bid' => (int)$d['booking_id'], ':sid' => (int)$staffId]);
        if ($dupCheck->fetch()) continue;

        $stmt->execute([
            ':booking_id' => (int)$d['booking_id'],
            ':staff_id'   => (int)$staffId,
            ':role'       => $d['role_required'],
            ':notes'      => $d['notes'] ?? null,
        ]);
        $created[] = $pdo->lastInsertId();

        // Send SMS alert to staff if enabled
        if ($booking && SMS_ENABLED) {
            $staffInfo = $pdo->prepare("SELECT phone, name FROM users WHERE id = :id");
            $staffInfo->execute([':id' => (int)$staffId]);
            $s = $staffInfo->fetch();
            if ($s && $s['phone']) {
                $msg = "Hi {$s['name']}! You have a new job offer from Yazzies Catering."
                    . " Role: {$d['role_required']}."
                    . " Event: " . date('M j, Y', strtotime($booking['event_date']))
                    . " at {$booking['event_location']}."
                    . " Please log in to accept or decline.";
                sendSms($s['phone'], $msg);
            }
        }
    }

    jsonResponse(true, count($created) . ' job order(s) dispatched.', ['created_ids' => $created], 201);
}

if ($method === 'PUT') {
    // Staff responds to a job offer
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id']) || empty($d['status'])) {
        jsonResponse(false, 'Job order ID and status (accepted/declined) are required.', [], 422);
    }
    if (!in_array($d['status'], ['accepted', 'declined'], true)) {
        jsonResponse(false, 'Status must be "accepted" or "declined".', [], 422);
    }

    // Staff can only update their own jobs; admin/frontdesk can update any
    // Idempotency: do not allow re-responding to an already-responded job
    $existing = $pdo->prepare("SELECT status FROM job_orders WHERE id = :id");
    $existing->execute([':id' => (int)$d['id']]);
    $existingJob = $existing->fetch();
    if ($existingJob && $existingJob['status'] !== 'pending') {
        jsonResponse(false, 'This job order has already been responded to (status: ' . $existingJob['status'] . ').', [], 409);
    }

    $whereExtra = '';
    $params     = [':id' => (int)$d['id'], ':status' => $d['status']];

    if ($currentUser['role'] === 'staff') {
        $whereExtra         = ' AND staff_id = :uid';
        $params[':uid']     = $currentUser['id'];
    }

    $stmt = $pdo->prepare("
        UPDATE job_orders
        SET status = :status, responded_at = NOW()
        WHERE id = :id $whereExtra
    ");
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'Job order not found or you do not have permission to update it.', [], 404);
    }

    jsonResponse(true, 'Response recorded. Thank you!');
}

if ($method === 'DELETE') {
    requireApiRole(['admin', 'frontdesk']);
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Job order ID required.', [], 422);
    $pdo->prepare("DELETE FROM job_orders WHERE id = :id")->execute([':id' => (int)$d['id']]);
    jsonResponse(true, 'Job order cancelled.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
