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
    $stmt = $pdo->prepare("SELECT id, booking_status, total_cost, amount_paid FROM bookings WHERE id = :id");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);
    if ($booking['booking_status'] === 'cancelled' || $booking['booking_status'] === 'pending_cancellation') {
        jsonResponse(false, 'This booking is already cancelled or has a pending request.', [], 409);
    }

    // ── Calculate Forfeiture & Refund Preview ─────────────────────────
    $totalPaid = (float)$booking['amount_paid'];
    $forfeitureFee = round($totalPaid * CANCEL_FORFEIT_PCT, 2);
    $refundableAmount = $totalPaid - $forfeitureFee;

    $pdo->beginTransaction();
    try {
        // 1. Clear any existing cancellation record
        $pdo->prepare("DELETE FROM booking_cancellations WHERE booking_id = :bid")->execute([':bid' => $bookingId]);

        // 2. Insert into booking_cancellations (Status: PENDING)
        $ins = $pdo->prepare("
            INSERT INTO booking_cancellations (
                booking_id, requested_by, reason, total_paid, 
                forfeited_amount, refundable_amount, refund_status,
                cancelled_at
            ) VALUES (
                :bid, :uid, :reason, :paid, 
                :forfeit, :refund, 'pending',
                NOW()
            )
        ");
        
        $ins->execute([
            ':bid'     => $bookingId,
            ':uid'     => (int)$_SESSION['user_id'],
            ':reason'  => $reason,
            ':paid'    => $totalPaid,
            ':forfeit' => $forfeitureFee,
            ':refund'  => $refundableAmount
        ]);
        $cancelId = $pdo->lastInsertId();

        // 3. Update booking status to 'pending_cancellation'
        // No recalculation of revenue yet, no payment recorded.
        $pdo->prepare("UPDATE bookings SET booking_status = 'pending_cancellation' WHERE id = :id")
             ->execute([':id' => $bookingId]);

        // 4. Audit Log
        auditLog($pdo, 'booking_cancellation_requested', 'booking', $bookingId, 
            ['status' => $booking['booking_status']], 
            ['status' => 'pending_cancellation', 'refundable' => $refundableAmount]
        );

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to request cancellation: ' . $e->getMessage(), [], 500);
    }

    jsonResponse(true, 'Cancellation request submitted. Please process the refund in Financials.', [
        'id'                => $cancelId,
        'refundable_amount' => $refundableAmount
    ], 201);
}

// ────────────────────────────────────────────────────────────────
// PUT — Update Refund Status OR Un-Cancel
// ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    requireApiRole('admin');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($data['id'] ?? 0);
    $bookingId = (int)($data['booking_id'] ?? 0);

    // PUT — Update Refund Status
    // Un-cancel action has been removed to prevent date conflicts and enforce finality of cancellations.

    if (empty($data['refund_status'])) {
        jsonResponse(false, 'refund_status is required.', [], 422);
    }

    $status = $data['refund_status'];
    $rmethod = $data['refund_method'] ?? 'cash';
    $ref    = $data['refund_reference'] ?? null;

    if (!in_array($status, ['pending', 'processed', 'waived'])) {
        jsonResponse(false, 'Invalid refund status.', [], 422);
    }

    $pdo->beginTransaction();
    try {
        $cstmt = $pdo->prepare("SELECT * FROM booking_cancellations WHERE id = :id FOR UPDATE");
        $cstmt->execute([':id' => $id]);
        $cancel = $cstmt->fetch();
        if (!$cancel) throw new Exception('Cancellation not found.');

        $bookingId = (int)$cancel['booking_id'];

        // Transitioning to 'processed' (STEP 2: FINAL CANCELLATION)
        if ($status === 'processed' && $cancel['refund_status'] !== 'processed') {
            
            // 1. Record negative payment
            if ($cancel['refundable_amount'] > 0) {
                $refundPct = 100 - (CANCEL_FORFEIT_PCT * 100);
                $ins = $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, reference_no, notes, payment_date, recorded_by) VALUES (:bid, :amt, :meth, :ref, CONCAT('Cancellation Refund (', :pct, '%)'), NOW(), :uid)");
                $ins->execute([
                    ':bid' => $bookingId,
                    ':amt' => -abs($cancel['refundable_amount']),
                    ':meth' => $rmethod,
                    ':ref' => $ref,
                    ':pct' => $refundPct,
                    ':uid' => (int)$_SESSION['user_id']
                ]);
            }

            // Apply strict row lock before recalculation
            $pdo->prepare("SELECT id FROM bookings WHERE id = :bid FOR UPDATE")->execute([':bid' => $bookingId]);

            // 2. Recalculate and Update Booking
            $paidRow = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = :bid");
            $paidRow->execute([':bid' => $bookingId]);
            $amountPaid = (float)$paidRow->fetchColumn();

            // Status is now finally 'cancelled'
            $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled', amount_paid = :amt WHERE id = :bid")
                ->execute([':amt' => $amountPaid, ':bid' => $bookingId]);
            
            auditLog($pdo, 'booking_cancelled_final', 'booking', $bookingId, ['status' => 'pending_cancellation'], ['status' => 'cancelled']);
        }
        
        // Update cancellation record
        $stmt = $pdo->prepare("
            UPDATE booking_cancellations SET 
                refund_status       = :st,
                refund_method       = :meth,
                refund_reference    = :ref,
                refund_processed_at = IF(:st_at='processed', NOW(), refund_processed_at),
                refund_processed_by = IF(:st_by='processed', :uid, refund_processed_by)
            WHERE id = :id
        ");
        $stmt->execute([
            ':id'    => $id,
            ':st'    => $status,
            ':st_at' => $status,
            ':st_by' => $status,
            ':meth'  => $rmethod,
            ':ref'   => $ref,
            ':uid'   => (int)$_SESSION['user_id']
        ]);
        
        $pdo->commit();

        // ── Send Notification Email (Cancellation Successful) ──
        if ($status === 'processed' && $cancel['refund_status'] !== 'processed') {
            $bStmt = $pdo->prepare("
                SELECT b.*,
                       cl.name AS client_name, cl.email AS client_email 
                FROM bookings b 
                JOIN clients cl ON cl.id = b.client_id 
                WHERE b.id = :bid
            ");
            $bStmt->execute([':bid' => $bookingId]);
            $booking = $bStmt->fetch();
            
            if ($booking && !empty($booking['client_email'])) {
                require_once __DIR__ . '/../../includes/mailer.php';
                try {
                    sendRefundReceipt($booking, (float)$cancel['refundable_amount'], (string)$rmethod);
                } catch (Throwable $e) {}
            }
        }

    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to update refund status: ' . $e->getMessage(), [], 500);
    }

    jsonResponse(true, 'Refund status updated and booking cancelled successfully.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
