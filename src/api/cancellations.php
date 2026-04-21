<?php
/**
 * Cancellations & Refunds API
 * GET    (list)           — List all cancellations
 * POST                    — Cancel a booking (and calculate refund)
 * PUT                     — Update refund status (mark as processed)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

$user   = requireApiRole(['admin', 'frontdesk']);
requireCsrf();
$method = $_SERVER['REQUEST_METHOD'];

// ────────────────────────────────────────────────────────────────
// GET — List cancellations
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $where  = ['1=1'];
    $params = [];
    
    if (!empty($_GET['status'])) {
        $where[] = 'c.refund_status = :status';
        $params[':status'] = $_GET['status'];
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT c.*, 
               b.event_date, b.total_cost AS booking_total,
               cl.name AS client_name,
               u_req.name AS requested_by_name,
               u_proc.name AS processed_by_name
        FROM booking_cancellations c
        JOIN bookings b ON b.id = c.booking_id
        JOIN clients cl ON cl.id = b.client_id
        JOIN users u_req ON u_req.id = c.requested_by
        LEFT JOIN users u_proc ON u_proc.id = c.refund_processed_by
        WHERE $whereClause
        ORDER BY c.created_at DESC
    ");
    $stmt->execute($params);
    jsonResponse(true, '', ['cancellations' => $stmt->fetchAll()]);
}

// ────────────────────────────────────────────────────────────────
// POST — Cancel a booking
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['booking_id'])) jsonResponse(false, 'Booking ID is required.', [], 422);

    $bookingId = (int)$data['booking_id'];
    $reason    = trim((string)($data['reason'] ?? 'No reason provided'));

    // Fetch booking details
    $stmt = $pdo->prepare("SELECT id, booking_status, total_cost, amount_paid, event_date FROM bookings WHERE id = :id");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);
    if ($booking['booking_status'] === 'cancelled') {
        jsonResponse(false, 'This booking has already been cancelled.', [], 409);
    }

    // ── Calculate Forfeiture & Refund ─────────────────────────────
    // Rule: if status was 'confirmed', forfeit 50% of total_cost.
    // If 'pending', forfeit 0%.
    $forfeitureFee = 0.00;
    if ($booking['booking_status'] === 'confirmed') {
        $forfeitureFee = round($booking['total_cost'] * 0.50, 2);
    }

    $totalPaid = (float)$booking['amount_paid'];
    $refundableAmount = max(0, $totalPaid - $forfeitureFee);

    $pdo->beginTransaction();
    try {
        // 1. Insert into booking_cancellations
        $ins = $pdo->prepare("
            INSERT INTO booking_cancellations (
                booking_id, requested_by, reason, total_paid, 
                forfeited_amount, refundable_amount, refund_status,
                cancelled_at
            ) VALUES (
                :bid, :uid, :reason, :paid, 
                :forfeit, :refund, :status,
                NOW()
            )
        ");
        
        // If refundable is 0, status is 'waived' or 'processed'? Let's use 'pending' or 'waived'.
        $refundStatus = ($refundableAmount > 0) ? 'pending' : 'waived';

        $ins->execute([
            ':bid'     => $bookingId,
            ':uid'     => (int)$_SESSION['user_id'],
            ':reason'  => $reason,
            ':paid'    => $totalPaid,
            ':forfeit' => $forfeitureFee,
            ':refund'  => $refundableAmount,
            ':status'  => $refundStatus
        ]);
        $cancelId = $pdo->lastInsertId();

        // 2. Update booking status
        $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE id = :id")
             ->execute([':id' => $bookingId]);

        // 3. Audit Log
        auditLog($pdo, 'booking_cancelled', 'booking', $bookingId, 
            ['status' => $booking['booking_status']], 
            ['status' => 'cancelled', 'forfeiture' => $forfeitureFee, 'refundable' => $refundableAmount]
        );

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to cancel booking: ' . $e->getMessage(), [], 500);
    }

    jsonResponse(true, 'Booking cancelled successfully.', [
        'id'                => $cancelId,
        'forfeited_amount'  => $forfeitureFee,
        'refundable_amount' => $refundableAmount,
        'refund_status'     => $refundStatus
    ], 201);
}

// ────────────────────────────────────────────────────────────────
// PUT — Update Refund Status
// ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    requireApiRole('admin'); // Only admins can process refunds
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['id']) || empty($data['refund_status'])) {
        jsonResponse(false, 'ID and refund_status are required.', [], 422);
    }

    $id     = (int)$data['id'];
    $status = $data['refund_status'];
    $method = $data['refund_method'] ?? null;
    $ref    = $data['refund_reference'] ?? null;

    if (!in_array($status, ['pending', 'processed', 'waived'])) {
        jsonResponse(false, 'Invalid refund status.', [], 422);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            UPDATE booking_cancellations SET 
                refund_status       = :st,
                refund_method       = :meth,
                refund_reference    = :ref,
                refund_processed_at = NOW(),
                refund_processed_by = :uid
            WHERE id = :id
        ");
        $stmt->execute([
            ':id'   => $id,
            ':st'   => $status,
            ':meth' => $method,
            ':ref'  => $ref,
            ':uid'  => (int)$_SESSION['user_id']
        ]);
        
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to update refund status: ' . $e->getMessage(), [], 500);
    }

    jsonResponse(true, 'Refund status updated successfully.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
