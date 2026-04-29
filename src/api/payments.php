<?php
/**
 * Payments API (Admin only — Financial Tracking)
 * GET    ?booking_id=X  — payments for a booking
 * GET    (no param)     — all payments with booking/client info
 * POST   — record a new payment
 * DELETE — remove a payment
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

requireApiRole(['admin', 'frontdesk']);

requireCsrf(); // CSRF protection for all state-changing methods
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['booking_id'])) {
        $stmt = $pdo->prepare("
            SELECT p.*, u.name AS recorded_by_name
            FROM payments p
            JOIN users u ON u.id = p.recorded_by
            WHERE p.booking_id = :bid
            ORDER BY p.payment_date ASC, p.id ASC
        ");
        $stmt->execute([':bid' => (int)$_GET['booking_id']]);
        $payments = $stmt->fetchAll();

        // Also return booking summary
        $bStmt = $pdo->prepare("
            SELECT b.total_cost, b.amount_paid, b.payment_status, b.market_cost, b.breakage_total,
                   c.name AS client_name
            FROM bookings b JOIN clients c ON c.id = b.client_id
            WHERE b.id = :bid
        ");
        $bStmt->execute([':bid' => (int)$_GET['booking_id']]);
        $booking = $bStmt->fetch();

        jsonResponse(true, '', ['payments' => $payments, 'booking' => $booking]);
    }

    // All payments with filter by month/year
    $where  = ['1=1'];
    $params = [];
    if (!empty($_GET['month']) && !empty($_GET['year'])) {
        $where[]         = 'MONTH(p.payment_date) = :mon AND YEAR(p.payment_date) = :yr';
        $params[':mon']  = (int)$_GET['month'];
        $params[':yr']   = (int)$_GET['year'];
    }
    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT p.*,
               b.event_date, b.total_cost, b.payment_status, b.booking_status,
               b.base_pax,
               CONCAT('Pax Tier ', IFNULL(b.base_pax, b.pax_count)) AS menu_name,
               c.name AS client_name,
               u.name AS recorded_by_name
        FROM payments p
        JOIN bookings b ON b.id  = p.booking_id
        JOIN clients  c ON c.id  = b.client_id
        JOIN users    u ON u.id  = p.recorded_by
        WHERE $whereClause
        ORDER BY p.payment_date DESC, p.id DESC
    ");
    $stmt->execute($params);
    $payments = $stmt->fetchAll();

    // Summary totals
    $totalRevenue  = array_sum(array_column($payments, 'amount'));
    jsonResponse(true, '', [
        'payments'      => $payments,
        'total_revenue' => $totalRevenue,
    ]);
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['booking_id', 'amount', 'payment_method', 'payment_date'];
    foreach ($required as $f) {
        if (empty($d[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }

    $bookingId = (int)$d['booking_id'];
    $amount    = (float)$d['amount'];

    // Verify booking exists + get current details for receipt
    $bStmt = $pdo->prepare("
        SELECT b.id, b.total_cost, b.amount_paid, b.booking_status, b.event_date, b.invoice_token, 
               b.pax_count, b.extra_cost, b.transport_fee, b.overtime_total, b.breakage_total, b.surcharge_total,
               COALESCE(pk.set_name, 'Catering Service') AS menu_name,
               c.email AS client_email, c.name AS client_name
        FROM bookings b
        JOIN clients c ON c.id = b.client_id
        LEFT JOIN packages pk ON pk.id = b.package_id
        WHERE b.id = :id
    ");
    $bStmt->execute([':id' => $bookingId]);
    $booking = $bStmt->fetch();
    if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);

    // ── Wrap balance check + INSERT in a single transaction with row locking ──
    // This prevents the race condition where two admins record payments simultaneously
    $pdo->beginTransaction();
    try {
        // ── Live balance under row lock: prevents TOCTOU race condition ──
        $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid FOR UPDATE");
        $paidStmt->execute([':bid' => $bookingId]);
        $totalAlreadyPaid = (float)$paidStmt->fetchColumn();

        $totalCost   = (float)$booking['total_cost'];
        $maxAllowed  = round($totalCost - $totalAlreadyPaid, 2);

        // Validation: must be > 0 and not negative
        if ($amount <= 0) {
            $pdo->rollBack();
            jsonResponse(false, 'Payment amount must be greater than ₱0.', [], 422);
        }

        // Validation: payment_method whitelist
        $validMethods = ['cash', 'bank_transfer', 'gcash', 'maya'];
        $method = $d['payment_method'] ?? 'cash';
        if (!in_array($method, $validMethods, true)) {
            $pdo->rollBack();
            jsonResponse(false, 'Invalid payment method. Allowed: cash, gcash, maya, bank_transfer.', [], 422);
        }

        // Validation: Reference number required for digital payments
        $ref = trim((string)($d['reference_no'] ?? ''));
        if ($method !== 'cash' && $ref === '') {
            $pdo->rollBack();
            jsonResponse(false, "Reference number is required for " . strtoupper($method) . " payments.", ['field' => 'reference_no'], 422);
        }

        // Validation: cannot go below zero balance
        if ($maxAllowed <= 0) {
            $pdo->rollBack();
            jsonResponse(false, 'This booking is already fully paid. No further payments are needed.', [
                'total_cost'  => $totalCost,
                'amount_paid' => $totalAlreadyPaid,
            ], 422);
        }

        // Validation: cannot exceed remaining balance
        if ($amount > $maxAllowed + 0.01) {
            $pdo->rollBack();
            jsonResponse(false,
                'Payment of ₱' . number_format($amount, 2) .
                ' exceeds the remaining balance of ₱' . number_format($maxAllowed, 2) . '.' .
                ' Total paid so far: ₱' . number_format($totalAlreadyPaid, 2) . '.',
                ['max_allowed'       => $maxAllowed,
                 'total_already_paid'=> $totalAlreadyPaid,
                 'total_cost'        => $totalCost], 422);
        }
        $stmt = $pdo->prepare("
            INSERT INTO payments (booking_id, amount, payment_method, reference_no, payment_date, notes, recorded_by)
            VALUES (:booking_id, :amount, :method, :ref, :date, :notes, :recorded_by)
        ");
        $stmt->execute([
            ':booking_id'  => $bookingId,
            ':amount'      => $amount,
            ':method'      => $d['payment_method'],
            ':ref'         => !empty($d['reference_no']) ? trim(substr((string)$d['reference_no'], 0, 100)) : null,
            ':date'        => $d['payment_date'],
            ':notes'       => !empty($d['notes']) ? trim(substr((string)$d['notes'], 0, 500)) : null,
            ':recorded_by' => (int)$_SESSION['user_id'],
        ]);
        $newPaymentId = $pdo->lastInsertId();

        // ── Force-sync bookings.amount_paid & payment_status ────────────────
        $newPaid = $totalAlreadyPaid + $amount;
        $status  = ($newPaid >= $totalCost - 0.01) ? 'paid'
                 : ($newPaid > 0                   ? 'partial' : 'unpaid');

        $pdo->prepare("
            UPDATE bookings SET
                amount_paid    = :paid,
                payment_status = :status
            WHERE id = :id
        ")->execute([
            ':paid'   => round($newPaid, 2),
            ':status' => $status,
            ':id'     => $bookingId,
        ]);

        // ── Auto-promote logic removed: pending status is deprecated ────────
        $finalStatus = $booking['booking_status'];

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Payment could not be recorded: ' . $e->getMessage(), [], 500);
    }

    // Audit: payment recorded
    auditLog($pdo, 'payment_recorded', 'payment', $newPaymentId,
        null,
        ['booking_id' => $bookingId, 'amount' => $amount, 'method' => $d['payment_method']]
    );

    // ── Immediate Email Receipt (No Queuing) ──
    if (!empty($booking['client_email'])) {
        require_once __DIR__ . '/../../includes/mailer.php';
        try {
            // Update the booking object with the NEW paid amount for the receipt
            $booking['amount_paid'] = round($totalAlreadyPaid + $amount, 2);
            
            error_log("[Payment API] Attempting to send receipt to " . $booking['client_email']);
            sendPaymentReceipt($booking, (float)$amount, (string)$d['payment_method']);
        } catch (\Throwable $e) {
            error_log("[Payment Receipt Error] " . $e->getMessage());
        }
    }

    jsonResponse(true, 'Payment recorded successfully.', [
        'id'             => $newPaymentId,
        'amount_paid'    => round($newPaid, 2),
        'balance'        => round($totalCost - $newPaid, 2),
        'payment_status' => $status,
        'booking_status' => $finalStatus,
        'invoice_token'  => $booking['invoice_token'],
    ], 201);
}

if ($method === 'DELETE') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Payment ID required.', [], 422);

    // Get the booking_id before deleting so we can re-sync
    $payRow = $pdo->prepare("SELECT booking_id FROM payments WHERE id = :id");
    $payRow->execute([':id' => (int)$d['id']]);
    $pay = $payRow->fetch();
    if (!$pay) jsonResponse(false, 'Payment not found.', [], 404);

    $bookingId = (int)$pay['booking_id'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM payments WHERE id = :id")->execute([':id' => (int)$d['id']]);

        // ── Audit: payment deleted (critical financial event) ──
        auditLog($pdo, 'payment_deleted', 'payment', (int)$d['id'],
            ['booking_id' => $bookingId],
            null
        );

        // ── Force re-sync bookings.amount_paid after deletion ────────────────
        $paidStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE booking_id = :bid");
        $paidStmt->execute([':bid' => $bookingId]);
        $newPaid = (float)$paidStmt->fetchColumn();

        $costStmt = $pdo->prepare("SELECT total_cost, event_date FROM bookings WHERE id = :id");
        $costStmt->execute([':id' => $bookingId]);
        $bk = $costStmt->fetch();
        $totalCost = (float)$bk['total_cost'];

        $status = ($newPaid >= $totalCost - 0.01) ? 'paid'
                : ($newPaid > 0                   ? 'partial' : 'unpaid');

        $pdo->prepare("
            UPDATE bookings SET
                amount_paid    = :paid,
                payment_status = :status,
                updated_at     = NOW()
            WHERE id = :id
        ")->execute([
            ':paid'   => round($newPaid, 2),
            ':status' => $status,
            ':id'     => $bookingId,
        ]);

        // ── Demote booking_status if paid falls below DP threshold ──
        $eventDateObj = new DateTime($bk['event_date']);
        $now = new DateTime();
        $interval = $now->diff($eventDateObj);
        $diffHours = ($interval->days * 24) + $interval->h;
        $dpPercent = (!$interval->invert && $diffHours < RUSH_THRESHOLD_HOURS) ? RUSH_DP_PERCENT : MIN_DP_PERCENT;
        
        $minDPThresh = round($totalCost * $dpPercent, 2);
        
        if ($newPaid < $minDPThresh - 0.01) {
            $curStatusStmt = $pdo->prepare("SELECT booking_status FROM bookings WHERE id = :id");
            $curStatusStmt->execute([':id' => $bookingId]);
            $curStatus = $curStatusStmt->fetchColumn();
            
            // Auto-demotion removed: pending status deprecated

        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to delete payment: ' . $e->getMessage(), [], 500);
    }

    jsonResponse(true, 'Payment removed. Balance updated.', [
        'amount_paid'    => round($newPaid, 2),
        'balance'        => round($totalCost - $newPaid, 2),
        'payment_status' => $status,
    ]);
}


jsonResponse(false, 'Method Not Allowed.', [], 405);
