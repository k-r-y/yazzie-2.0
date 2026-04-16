<?php
/**
 * Dispatching API
 * GET    ?booking_id=X  — List job orders for an event
 * GET    ?my_jobs=1     — Staff: list own job orders (with booking info)
 * POST                  — Create job orders (broadcast recruitment)
 * DELETE ?id=X          — Cancel a job order
 * PUT                   — Staff: respond to job offer { id, status: accepted|declined }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$method = $_SERVER['REQUEST_METHOD'];

// ────────────────────────────────────────────────────────────────
// GET — List job orders
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    // Staff: my job board
    if (isset($_GET['my_jobs'])) {
        $currentUser = requireApiRole('staff');
        $uid = (int)$currentUser['id'];

        $stmt = $pdo->prepare("
            SELECT jo.*,
                   b.event_date, b.event_time, b.event_location, b.pax_count,
                   c.name AS client_name
            FROM job_orders jo
            JOIN bookings b ON b.id = jo.booking_id
            JOIN clients  c ON c.id = b.client_id
            WHERE jo.staff_id = :uid
            ORDER BY b.event_date ASC, jo.id DESC
        ");
        $stmt->execute([':uid' => $uid]);
        $rows = $stmt->fetchAll();

        jsonResponse(true, '', ['job_orders' => $rows]);
    }

    requireApiRole(['admin', 'frontdesk']);
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
// PUT — Staff respond to job offer
// ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $currentUser = requireApiRole('staff');
    $uid = (int)$currentUser['id'];

    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    $status = strtolower(trim((string)($data['status'] ?? '')));
    if (!$id || !in_array($status, ['accepted', 'declined'], true)) {
        jsonResponse(false, 'id and valid status (accepted|declined) are required.', [], 422);
    }

    // Load job order + booking date, ensure ownership and still pending
    $row = $pdo->prepare("
        SELECT jo.id, jo.status, jo.booking_id, b.event_date
        FROM job_orders jo
        JOIN bookings b ON b.id = jo.booking_id
        WHERE jo.id = :id AND jo.staff_id = :uid
        LIMIT 1
    ");
    $row->execute([':id' => $id, ':uid' => $uid]);
    $jo = $row->fetch();
    if (!$jo) jsonResponse(false, 'Job offer not found.', [], 404);
    if ($jo['status'] !== 'pending') {
        jsonResponse(false, 'This job offer has already been responded to.', ['current_status' => $jo['status']], 409);
    }

    // If accepting, enforce leave + overlap constraints
    if ($status === 'accepted') {
        $eventDate = $jo['event_date'];

        // Block if staff is on approved leave that date
        $leave = $pdo->prepare("
            SELECT id FROM leave_requests
            WHERE staff_id = :sid AND leave_date = :d AND status = 'approved'
            LIMIT 1
        ");
        $leave->execute([':sid' => $uid, ':d' => $eventDate]);
        if ($leave->fetch()) {
            jsonResponse(false, 'Cannot accept: you are on approved leave for this event date.', [
                'field' => 'leave_date',
                'event_date' => $eventDate,
            ], 409);
        }

        // Block if staff already accepted another non-cancelled booking on same date
        $overlap = $pdo->prepare("
            SELECT jo2.id, jo2.booking_id
            FROM job_orders jo2
            JOIN bookings b2 ON b2.id = jo2.booking_id
            WHERE jo2.staff_id = :sid
              AND jo2.status = 'accepted'
              AND b2.event_date = :d
              AND b2.booking_status NOT IN ('cancelled')
              AND jo2.booking_id != :thisBid
            LIMIT 1
        ");
        $overlap->execute([':sid' => $uid, ':d' => $eventDate, ':thisBid' => (int)$jo['booking_id']]);
        $ov = $overlap->fetch();
        if ($ov) {
            jsonResponse(false, 'Cannot accept: you already accepted another job on this date.', [
                'conflict_job_order_id' => (int)$ov['id'],
                'conflict_booking_id'   => (int)$ov['booking_id'],
                'event_date'            => $eventDate,
            ], 409);
        }
    }

    $pdo->prepare("
        UPDATE job_orders
        SET status = :st, responded_at = NOW()
        WHERE id = :id AND staff_id = :uid AND status = 'pending'
    ")->execute([':st' => $status, ':id' => $id, ':uid' => $uid]);

    // ── Declined: notify all admin & frontdesk users to update the lineup ──
    if ($status === 'declined') {
        // Fetch booking info for the notification message
        $bInfo = $pdo->prepare("
            SELECT b.id AS booking_id, b.event_date, c.name AS client_name
            FROM bookings b JOIN clients c ON c.id = b.client_id
            WHERE b.id = :bid
        ");
        $bInfo->execute([':bid' => $jo['booking_id']]);
        $bData = $bInfo->fetch();

        if ($bData) {
            $eventDateFormatted = date('F j, Y', strtotime($bData['event_date']));
            $staffName  = $currentUser['name'] ?? 'A staff member';
            $bookingUrl = BASE_URL . '/views/admin/bookings.php?highlight=' . $bData['booking_id'];

            try {
                $notifyAdmins = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, body, booking_id, link_url)
                    SELECT id,
                           'job_declined',
                           'Staff Declined Job Offer',
                           CONCAT(:staffName, ' has declined the job offer for ',
                                  :clientName, '\'s event on ', :eventDate,
                                  '. Please update the lineup.'),
                           :bid,
                           :linkUrl
                    FROM users
                    WHERE role IN ('admin', 'super_admin', 'frontdesk')
                      AND is_active = 1
                ");
                $notifyAdmins->execute([
                    ':staffName'  => $staffName,
                    ':clientName' => $bData['client_name'],
                    ':eventDate'  => $eventDateFormatted,
                    ':bid'        => $bData['booking_id'],
                    ':linkUrl'    => $bookingUrl,
                ]);
            } catch (\Throwable $e) {
                // Non-fatal: notification insert may fail if migration V11 not yet run
                error_log('Decline notification failed: ' . $e->getMessage());
            }
        }
    }

    jsonResponse(true, $status === 'accepted' ? 'Job accepted.' : 'Job declined. Admin has been notified.');
}

// ────────────────────────────────────────────────────────────────
// POST — Create job orders (Broadcast)
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    requireApiRole(['admin', 'frontdesk']);
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
        // Skip staff on approved leave for this booking date
        $leaveChk = $pdo->prepare("
            SELECT id FROM leave_requests
            WHERE staff_id = :sid AND leave_date = :d AND status = 'approved'
            LIMIT 1
        ");
        $leaveChk->execute([':sid' => $sid, ':d' => $booking['event_date']]);
        if ($leaveChk->fetch()) continue;

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
    requireApiRole(['admin', 'frontdesk']);
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonResponse(false, 'ID is required.', [], 422);

    $stmt = $pdo->prepare("DELETE FROM job_orders WHERE id = :id");
    $stmt->execute([':id' => $id]);
    
    jsonResponse(true, 'Job order cancelled.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
