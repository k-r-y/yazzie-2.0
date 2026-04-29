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
    // Rule: if status was 'confirmed', forfeit based on settings.
    $forfeitureFee = 0.00;
    if ($booking['booking_status'] === 'confirmed') {
        $forfeitureFee = round($booking['total_cost'] * CANCEL_FORFEIT_PCT, 2);
    }

    $totalPaid = (float)$booking['amount_paid'];
    $refundableAmount = max(0, $totalPaid - $forfeitureFee);

    $pdo->beginTransaction();
    try {
        // 1. Clear any existing cancellation record for this booking (if it was un-cancelled before)
        $pdo->prepare("DELETE FROM booking_cancellations WHERE booking_id = :bid")->execute([':bid' => $bookingId]);

        // 2. Insert into booking_cancellations
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

        // 3. Update booking status
        $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE id = :id")
             ->execute([':id' => $bookingId]);

        // 4. Audit Log
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
// PUT — Update Refund Status OR Un-Cancel
// ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    requireApiRole('admin'); // Only admins can process refunds
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['id'])) {
        jsonResponse(false, 'ID is required.', [], 422);
    }
    $id = (int)$data['id'];

    if (isset($data['action']) && $data['action'] === 'uncancel') {
        $pdo->beginTransaction();
        try {
            // Get cancellation details
            $cstmt = $pdo->prepare("SELECT * FROM booking_cancellations WHERE id = :id FOR UPDATE");
            $cstmt->execute([':id' => $id]);
            $cancel = $cstmt->fetch();
            if (!$cancel) throw new Exception('Cancellation not found.');

            $bookingId = (int)$cancel['booking_id'];
            
            // Delete associated negative payment if it was processed
            if ($cancel['refund_status'] === 'processed') {
                $pdo->prepare("DELETE FROM payments WHERE booking_id = :bid AND amount < 0 AND notes = 'Refund'")->execute([':bid' => $bookingId]);
            }
            
            // Recalculate amount_paid from remaining positive payments
            $paidRow = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = :bid");
            $paidRow->execute([':bid' => $bookingId]);
            $amountPaid = (float)$paidRow->fetchColumn();

            // Get total_cost to derive correct payment_status
            $costRow = $pdo->prepare("SELECT total_cost FROM bookings WHERE id = :bid");
            $costRow->execute([':bid' => $bookingId]);
            $totalCost = (float)$costRow->fetchColumn();

            if ($amountPaid >= $totalCost - 0.01) {
                $paymentStatus = 'paid';
            } elseif ($amountPaid > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            // Restore booking: confirmed status + recalculated financials
            $pdo->prepare("
                UPDATE bookings SET 
                    booking_status = 'confirmed',
                    amount_paid    = :paid,
                    payment_status = :pstatus
                WHERE id = :bid
            ")->execute([
                ':paid'    => round($amountPaid, 2),
                ':pstatus' => $paymentStatus,
                ':bid'     => $bookingId,
            ]);

            // 4. Delete the cancellation record (it is no longer needed since booking is restored)
            $pdo->prepare("DELETE FROM booking_cancellations WHERE id = :id")->execute([':id' => $id]);
            
            // Audit trail
            auditLog($pdo, 'booking_uncancelled', 'booking', $bookingId,
                ['booking_status' => 'cancelled'],
                ['booking_status' => 'confirmed', 'amount_paid' => $amountPaid, 'payment_status' => $paymentStatus]
            );

            $pdo->commit();
            jsonResponse(true, 'Booking restored successfully.', [
                'amount_paid'    => round($amountPaid, 2),
                'payment_status' => $paymentStatus,
            ]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Failed to un-cancel: ' . $e->getMessage(), [], 500);
        }
        exit;
    }

    if (empty($data['refund_status'])) {
        jsonResponse(false, 'refund_status is required.', [], 422);
    }

    $status = $data['refund_status'];
    $rmethod = $data['refund_method'] ?? null;
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

        // Transitioning to 'processed'
        if ($status === 'processed' && $cancel['refund_status'] !== 'processed' && $cancel['refundable_amount'] > 0) {
            $ins = $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, reference_no, notes, payment_date, recorded_by) VALUES (:bid, :amt, :meth, :ref, 'Refund', NOW(), :uid)");
            $ins->execute([
                ':bid' => $cancel['booking_id'],
                ':amt' => -abs($cancel['refundable_amount']),
                ':meth' => $rmethod,
                ':ref' => $ref,
                ':uid' => (int)$_SESSION['user_id']
            ]);

            $paidRow = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = :bid");
            $paidRow->execute([':bid' => $cancel['booking_id']]);
            $amountPaid = (float)$paidRow->fetchColumn();
            $pdo->prepare("UPDATE bookings SET amount_paid = :amt WHERE id = :bid")->execute([':amt' => $amountPaid, ':bid' => $cancel['booking_id']]);
        }
        
        // Transitioning away from 'processed'
        if ($status !== 'processed' && $cancel['refund_status'] === 'processed') {
            $pdo->prepare("DELETE FROM payments WHERE booking_id = :bid AND amount < 0 AND notes = 'Refund'")->execute([':bid' => $cancel['booking_id']]);
            
            $paidRow = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = :bid");
            $paidRow->execute([':bid' => $cancel['booking_id']]);
            $amountPaid = (float)$paidRow->fetchColumn();
            $pdo->prepare("UPDATE bookings SET amount_paid = :amt WHERE id = :bid")->execute([':amt' => $amountPaid, ':bid' => $cancel['booking_id']]);
        }

        $stmt = $pdo->prepare("
            UPDATE booking_cancellations SET 
                refund_status       = :st,
                refund_method       = :meth,
                refund_reference    = :ref,
                refund_processed_at = IF(:st_at='processed', NOW(), NULL),
                refund_processed_by = IF(:st_by='processed', :uid, NULL)
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

        // ── Send Refund Receipt Email ──
        if ($status === 'processed' && $cancel['refund_status'] !== 'processed' && $cancel['refundable_amount'] > 0) {
            $bStmt = $pdo->prepare("
                SELECT b.*,
                       pk.set_name AS menu_name,
                       c.name AS client_name, c.email AS client_email 
                FROM bookings b 
                JOIN clients c ON c.id = b.client_id 
                LEFT JOIN packages pk ON pk.id = b.package_id
                WHERE b.id = :bid
            ");
            $bStmt->execute([':bid' => $cancel['booking_id']]);
            $booking = $bStmt->fetch();
            
            if ($booking && !empty($booking['client_email'])) {
                require_once __DIR__ . '/../../includes/mailer.php';
                sendRefundReceipt($booking, (float)$cancel['refundable_amount'], (string)$rmethod);
            }
        }

    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to update refund status: ' . $e->getMessage(), [], 500);
    }

    jsonResponse(true, 'Refund status updated successfully.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
