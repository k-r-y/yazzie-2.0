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
        WHERE client_name LIKE :s1 OR menu_name LIKE :s2 OR event_location LIKE :s3
        ORDER BY event_date DESC, archived_at DESC
    ");
    $stmt->execute([':s1' => $like, ':s2' => $like, ':s3' => $like]);
    jsonResponse(true, '', ['archived' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['booking_id'])) jsonResponse(false, 'booking_id is required.', [], 422);

    $bookingId = (int)$d['booking_id'];

    // ── Get full booking details (LEFT JOIN so package-only bookings are found) ──
    $stmt = $pdo->prepare("
        SELECT b.*, c.name AS client_name, c.phone AS client_phone,
               COALESCE(pk.set_name, m.name, 'Package Booking') AS menu_name
        FROM bookings b
        JOIN clients  c  ON c.id  = b.client_id
        LEFT JOIN menus    m  ON m.id  = b.menu_id
        LEFT JOIN packages pk ON pk.id = b.package_id
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
        $archStmt = $pdo->prepare("
            INSERT INTO archived_bookings
              (original_id, client_name, client_phone, menu_name, event_date, event_time,
               event_location, pax_count, total_cost, amount_paid, payment_status, notes, archived_by)
            VALUES
              (:original_id, :client_name, :client_phone, :menu_name, :event_date, :event_time,
               :event_location, :pax_count, :total_cost, :amount_paid, :payment_status, :notes, :archived_by)
        ");

        $archStmt->execute([
            ':original_id'    => $booking['id'],
            ':client_name'    => $booking['client_name'],
            ':client_phone'   => $booking['client_phone'],
            ':menu_name'      => $booking['menu_name'],
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

        // Delete from active bookings (cascade deletes payments + job_orders + booking_dishes)
        $pdo->prepare("DELETE FROM bookings WHERE id = :id")->execute([':id' => $bookingId]);

        $pdo->commit();

        jsonResponse(true, 'Booking archived successfully.', ['archived_id' => $archId], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Archive failed: ' . $e->getMessage(), [], 500);
    }
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
