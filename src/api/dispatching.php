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
requireCsrf();

// ────────────────────────────────────────────────────────────────
// GET — List job orders
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    // ── Staff Suggestion Engine ────────────────────────────────
    if (isset($_GET['suggest']) && !empty($_GET['booking_id'])) {
        requireApiRole(['admin', 'frontdesk']);
        $bookingId = (int)$_GET['booking_id'];

        // Fetch booking details
        $bStmt = $pdo->prepare("
            SELECT b.id, b.event_date, b.event_type, b.pax_count,
                   c.name AS client_name
            FROM bookings b
            JOIN clients c ON c.id = b.client_id
            WHERE b.id = :bid
        ");
        $bStmt->execute([':bid' => $bookingId]);
        $booking = $bStmt->fetch();
        if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);

        $pax       = (int)$booking['pax_count'];
        $eventType = $booking['event_type'] ?? 'Wedding';

        // Calculate recommended staff count based on event type ratios
        $ratio = in_array($eventType, ['Wedding', 'Corporate']) ? 10 : 20;
        $recommended = max(5, (int)ceil($pax / $ratio));

        // Fetch available staff for this date, sorted by job_class match
        $date = $booking['event_date'];

        $staffStmt = $pdo->query("
            SELECT id, name, phone, job_class
            FROM users
            WHERE role = 'staff' AND is_active = 1
            ORDER BY name ASC
        ");
        $allStaff = $staffStmt->fetchAll();

        // Check leave + existing bookings
        $onLeaveStmt = $pdo->prepare("SELECT staff_id FROM leave_requests WHERE leave_date = :d AND status = 'approved'");
        $onLeaveStmt->execute([':d' => $date]);
        $onLeave = array_column($onLeaveStmt->fetchAll(), 'staff_id');

        $bookedStmt = $pdo->prepare("
            SELECT DISTINCT jo.staff_id
            FROM job_orders jo JOIN bookings b ON b.id = jo.booking_id
            WHERE b.event_date = :d AND b.booking_status NOT IN ('cancelled')
              AND jo.status = 'accepted' AND jo.booking_id != :excl
        ");
        $bookedStmt->execute([':d' => $date, ':excl' => $bookingId]);
        $alreadyBooked = array_column($bookedStmt->fetchAll(), 'staff_id');

        // Already dispatched to this booking
        $dispatchedStmt = $pdo->prepare("
            SELECT staff_id FROM job_orders WHERE booking_id = :bid AND status != 'declined'
        ");
        $dispatchedStmt->execute([':bid' => $bookingId]);
        $alreadyDispatched = array_column($dispatchedStmt->fetchAll(), 'staff_id');

        foreach ($allStaff as &$s) {
            if (in_array($s['id'], $onLeave)) {
                $s['availability'] = 'on_leave';
            } elseif (in_array($s['id'], $alreadyBooked)) {
                $s['availability'] = 'booked';
            } else {
                $s['availability'] = 'available';
            }
            $s['already_dispatched'] = in_array($s['id'], $alreadyDispatched);
        }
        unset($s);

        jsonResponse(true, '', [
            'booking'     => $booking,
            'recommended' => $recommended,
            'ratio'       => "1:$ratio",
            'event_type'  => $eventType,
            'staff'       => $allStaff,
        ]);
    }

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

    // Dashboard support: if no booking_id, return total pending job orders
    if (!$bookingId) {
        $count = $pdo->query("SELECT COUNT(*) FROM job_orders WHERE status = 'pending'")->fetchColumn();
        jsonResponse(true, '', ['pending_count' => (int)$count]);
    }

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

    $stmt = $pdo->prepare("
        UPDATE job_orders
        SET status = :st, responded_at = NOW()
        WHERE id = :id AND staff_id = :uid AND status = 'pending'
    ");
    $stmt->execute([':st' => $status, ':id' => $id, ':uid' => $uid]);
    if ($stmt->rowCount() === 0) {
        jsonResponse(false, 'This job offer has already been responded to or is no longer available.', [], 409);
    }

    // ── Notify all admin & frontdesk users about the response ──
    $bInfo = $pdo->prepare("
        SELECT b.id AS booking_id, b.event_date, b.event_time, b.event_location, b.pax_count, c.name AS client_name, jo.role_required
        FROM job_orders jo
        JOIN bookings b ON b.id = jo.booking_id
        JOIN clients c ON c.id = b.client_id
        WHERE jo.id = :id
    ");
    $bInfo->execute([':id' => $id]);
    $bData = $bInfo->fetch();

    if ($bData) {
        $eventDateFormatted = date('F j, Y', strtotime($bData['event_date']));
        $staffName  = $currentUser['name'] ?? 'A staff member';
        $statusLabel= ($status === 'accepted' ? 'ACCEPTED' : 'DECLINED');
        $bookingUrl = BASE_URL . '/views/admin/bookings.php?highlight=' . $bData['booking_id'];

        $notifBody = ($status === 'accepted')
            ? "$staffName has ACCEPTED the job offer for {$bData['client_name']}'s event on $eventDateFormatted."
            : "$staffName has DECLINED the job offer for {$bData['client_name']}'s event on $eventDateFormatted. Please update the lineup.";

        try {
            // 1. In-App Notifications
            $notifyAdmins = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, body, booking_id, link_url)
                SELECT id,
                       :type,
                       :title,
                       :body,
                       :bid,
                       :linkUrl
                FROM users
                WHERE role IN ('admin', 'super_admin', 'frontdesk')
                  AND is_active = 1
            ");
            $notifyAdmins->execute([
                ':type'    => ($status === 'accepted' ? 'job_accepted' : 'job_declined'),
                ':title'   => "Staff $statusLabel Job Offer",
                ':body'    => $notifBody,
                ':bid'     => $bData['booking_id'],
                ':linkUrl' => $bookingUrl,
            ]);

            // 2. Email Notifications
            if (MAIL_ENABLED) {
                require_once __DIR__ . '/../../includes/mailer.php';
                $admins = $pdo->query("SELECT name, email FROM users WHERE role IN ('admin', 'super_admin', 'frontdesk') AND is_active = 1")->fetchAll();
                foreach ($admins as $admin) {
                    if (!empty($admin['email'])) {
                        sendJobResponseEmailToAdmin(
                            $admin,
                            ['name' => $staffName],
                            [
                                'event_date'  => $bData['event_date'],
                                'client_name' => $bData['client_name'],
                                'staff_role'  => $bData['role_required']
                            ],
                            $status
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Response notification failed: ' . $e->getMessage());
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

    // Enforce 1 Head Cook per event
    if ($role === 'head_cook') {
        if (count($staffIds) > 1) {
            jsonResponse(false, 'You cannot dispatch multiple Head Cooks at once. Only one Head Cook is allowed per event.', [], 409);
        }
        $chkHc = $pdo->prepare("SELECT id FROM job_orders WHERE booking_id = ? AND role_required = 'head_cook' AND status != 'declined'");
        $chkHc->execute([$bookingId]);
        if ($chkHc->fetch()) {
            jsonResponse(false, 'A Head Cook has already been dispatched or is pending for this event.', [], 409);
        }
    }

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("
            INSERT INTO job_orders (booking_id, staff_id, role_required, notes, status, job_class)
            VALUES (:bid, :sid, :role, :notes, 'pending', :jc)
        ");

        $notif = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, body)
            VALUES (:uid, 'job_assigned', :title, :body)
        ");

        // Fetch job classes for all staff to be notified
        $uStmt = $pdo->prepare("SELECT id, job_class FROM users WHERE id IN (" . implode(',', array_fill(0, count($staffIds), '?')) . ")");
        $uStmt->execute($staffIds);
        $staffMap = [];
        foreach ($uStmt->fetchAll() as $usr) { $staffMap[(int)$usr['id']] = $usr['job_class']; }

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
                ':notes' => $notes ?: null,
                ':jc'    => $staffMap[$sid] ?? 'any'
            ]);
            
            $notif->execute([
                ':uid'   => $sid,
                ':title' => 'New Job Offer: ' . $role,
                ':body'  => "Event on " . date('M d', strtotime($booking['event_date'])) . " for " . $booking['client_name'] . ". Please respond in your Job Board."
            ]);
            
            $count++;
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to dispatch job orders: ' . $e->getMessage(), [], 500);
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
