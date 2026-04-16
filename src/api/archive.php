<?php
/**
 * Archive API
 * GET    — list archived bookings (admin only)
 * POST   — archive a completed+paid booking (admin only)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

requireApiRole('admin');
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    $like   = "%$search%";
    $stmt = $pdo->prepare("
        SELECT * FROM archived_bookings
        WHERE client_name LIKE :s1 OR event_location LIKE :s2
        ORDER BY event_date DESC, archived_at DESC
    ");
    $stmt->execute([':s1' => $like, ':s2' => $like]);
    jsonResponse(true, '', ['archived' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['booking_id'])) jsonResponse(false, 'booking_id is required.', [], 422);

    $bookingId = (int)$d['booking_id'];

    // ── Get full booking details (LEFT JOIN so package-only bookings are found) ──
    $stmt = $pdo->prepare("
        SELECT b.*,
               c.name AS client_name,
               c.phone AS client_phone
        FROM bookings b
        JOIN clients   c  ON c.id  = b.client_id
        WHERE b.id = :id
    ");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);

    if ($booking['booking_status'] !== 'completed') {
        jsonResponse(false, 'Only bookings with status "completed" can be archived.', [], 422);
    }

    // ── Guard: do not archive if there is still an outstanding balance ──────
    $balance = round((float)$booking['total_cost'] - (float)$booking['amount_paid'], 2);
    if ($balance > 0.01) {
        jsonResponse(false,
            'Cannot archive a booking with an outstanding balance of ₱' . number_format($balance, 2) . '. ' .
            'Please settle the remaining payment before archiving.',
            ['balance' => $balance], 422
        );
    }

    // ── Wrap INSERT + DELETE in a transaction to prevent partial archive ─────
    $pdo->beginTransaction();
    try {
        // Prevent duplicate archive snapshot
        $dup = $pdo->prepare("SELECT id FROM archived_bookings WHERE original_id = :oid LIMIT 1");
        $dup->execute([':oid' => $bookingId]);
        if ($dup->fetch()) {
            $pdo->rollBack();
            jsonResponse(false, 'This booking has already been archived.', [], 409);
        }

        $archStmt = $pdo->prepare("
            INSERT INTO archived_bookings
              (original_id, client_name, client_phone, event_date, event_time,
               event_location, pax_count, total_cost, amount_paid, payment_status, notes, archived_by)
            VALUES
              (:original_id, :client_name, :client_phone, :event_date, :event_time,
               :event_location, :pax_count, :total_cost, :amount_paid, :payment_status, :notes, :archived_by)
        ");

        $archStmt->execute([
            ':original_id'    => $booking['id'],
            ':client_name'    => $booking['client_name'],
            ':client_phone'   => $booking['client_phone'],
            ':event_date'     => $booking['event_date'],
            ':event_time'     => $booking['event_time'],
            ':event_location' => $booking['event_location'],
            ':pax_count'      => $booking['pax_count'],
            ':total_cost'     => $booking['total_cost'],
            ':amount_paid'    => $booking['amount_paid'],
            ':payment_status' => $booking['payment_status'],
            ':notes'          => $booking['notes'],
            ':archived_by'    => (int)$_SESSION['user_id'],
        ]);

        $archId = $pdo->lastInsertId();

        // Mark booking as archived (preserves payments history)
        $pdo->prepare("
            UPDATE bookings
            SET is_archived = 1,
                archived_at = NOW(),
                archived_by = :by
            WHERE id = :id
        ")->execute([
            ':id' => $bookingId,
            ':by' => (int)$_SESSION['user_id'],
        ]);

        $pdo->commit();

        jsonResponse(true, 'Booking archived successfully.', ['archived_id' => $archId], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Archive failed: ' . $e->getMessage(), [], 500);
    }
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
