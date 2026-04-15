<?php
/**
 * Dispatching API
 * GET    ?booking_id=X  — List job orders for an event
 * POST                  — Create job orders (broadcast recruitment)
 * DELETE ?id=X          — Cancel a job order
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$user = requireApiRole(['admin', 'frontdesk']);
$method = $_SERVER['REQUEST_METHOD'];

// ────────────────────────────────────────────────────────────────
// GET — List job orders
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $bookingId = (int)($_GET['booking_id'] ?? 0);
    if (!$bookingId) jsonResponse(false, 'Booking ID is required.', [], 422);

    $stmt = $pdo->prepare("
        SELECT jo.*, u.name AS staff_name, u.phone AS staff_phone
        FROM job_orders jo
        JOIN users u ON u.id = jo.staff_id
        WHERE jo.booking_id = :bid
        ORDER BY jo.id DESC
    ");
    $stmt->execute([':bid' => $bookingId]);
    jsonResponse(true, '', ['job_orders' => $stmt->fetchAll()]);
}

// ────────────────────────────────────────────────────────────────
// POST — Create job orders (Broadcast)
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    
    foreach (['booking_id', 'staff_ids', 'role_required'] as $f) {
        if (empty($data[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }

    $bookingId = (int)$data['booking_id'];
    $staffIds  = array_map('intval', (array)$data['staff_ids']);
    $role      = trim($data['role_required']);
    $notes     = trim($data['notes'] ?? '');

    // Get Booking Info for Notifications
    $bStmt = $pdo->prepare("SELECT b.*, c.name as client_name FROM bookings b JOIN clients c ON c.id = b.client_id WHERE b.id = :bid");
    $bStmt->execute([':bid' => $bookingId]);
    $booking = $bStmt->fetch();
    if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);

    $ins = $pdo->prepare("
        INSERT INTO job_orders (booking_id, staff_id, role_required, notes, status)
        VALUES (:bid, :sid, :role, :notes, 'pending')
    ");

    $notif = $pdo->prepare("
        INSERT INTO notifications (user_id, type, title, body)
        VALUES (:uid, 'job_assigned', :title, :body)
    ");

    $count = 0;
    foreach ($staffIds as $sid) {
        // Check if already dispatched to this staff for this event
        $chk = $pdo->prepare("SELECT id FROM job_orders WHERE booking_id = ? AND staff_id = ? AND status != 'declined'");
        $chk->execute([$bookingId, $sid]);
        if ($chk->fetch()) continue;

        $ins->execute([
            ':bid'   => $bookingId,
            ':sid'   => $sid,
            ':role'  => $role,
            ':notes' => $notes ?: null
        ]);
        
        $notif->execute([
            ':uid'   => $sid,
            ':title' => 'New Job Offer: ' . $role,
            ':body'  => "Event on " . date('M d', strtotime($booking['event_date'])) . " for " . $booking['client_name'] . ". Please respond in your Job Board."
        ]);
        
        $count++;
    }

    // Email broadcast — batch fetch all staff in one query (avoids N+1)
    if ($count > 0 && MAIL_ENABLED) {
        require_once __DIR__ . '/../../includes/mailer.php';
        if (!empty($staffIds)) {
            $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
            $uStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id IN ($placeholders) AND is_active = 1");
            $uStmt->execute($staffIds);
            $staffUsers = $uStmt->fetchAll();
            foreach ($staffUsers as $staffUser) {
                if (!empty($staffUser['email'])) {
                    try {
                        sendStaffAssignmentEmail(
                            ['name' => $staffUser['name'], 'email' => $staffUser['email']],
                            [
                                'event_date'     => $booking['event_date'],
                                'event_time'     => $booking['event_time'] ?? '',
                                'event_location' => $booking['event_location'] ?? 'TBA',
                                'pax_count'      => $booking['pax_count'],
                                'staff_role'     => $role,
                            ]
                        );
                    } catch (\Throwable $e) {
                        error_log("Email fail on dispatch for user {$staffUser['id']}: " . $e->getMessage());
                    }
                }
            }
        }
    }

    jsonResponse(true, "$count job offers dispatched successfully.");
}

// ────────────────────────────────────────────────────────────────
// DELETE — Cancel job order
// ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(false, 'ID is required.', [], 422);

    $stmt = $pdo->prepare("DELETE FROM job_orders WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    jsonResponse(true, 'Job order cancelled.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
