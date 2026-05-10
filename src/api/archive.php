<?php
/**
 * Archive API
 * GET    — list archived bookings (admin only)
 * POST   — archive a completed+paid booking (admin only)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

$currentUser = requireApiRole('admin');
requireCsrf();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = $_GET['search'] ?? '';
    $sort   = $_GET['sort']   ?? 'archived'; // archived, upcoming, latest, payment
    $mode   = $_GET['mode']   ?? '';         // online, manual
    
    $where  = ['1=1'];
    $params = [];
    
    if ($search) {
        $where[] = "(client_name LIKE :s1 OR event_location LIKE :s2)";
        $params[':s1'] = "%$search%";
        $params[':s2'] = "%$search%";
    }
    
    if ($mode === 'online') {
        $where[] = "original_id IN (SELECT booking_id FROM payments WHERE payment_method IN ('gcash', 'maya', 'paymongo', 'card'))";
    } elseif ($mode === 'manual') {
        $where[] = "original_id IN (SELECT booking_id FROM payments WHERE payment_method IN ('cash', 'bank_transfer'))";
    }
    
    $orderBy = "archived_at DESC";
    if ($sort === 'upcoming') {
        $orderBy = "event_date DESC";
    } elseif ($sort === 'latest') {
        $orderBy = "id DESC";
    } elseif ($sort === 'payment') {
        $orderBy = "(SELECT MAX(payment_date) FROM payments WHERE booking_id = original_id) DESC";
    }
    
    $whereClause = implode(' AND ', $where);
    
    // ── ACTION: Export CSV ───────────────────────────────────────────
    if (isset($_GET['export'])) {
        $stmt = $pdo->prepare("
            SELECT event_date, client_name, client_phone, pax_count, total_cost, amount_paid, payment_status, archived_at
            FROM archived_bookings
            WHERE $whereClause
            ORDER BY $orderBy
        ");
        foreach ($params as $key => $val) $stmt->bindValue($key, $val);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="archive_export_' . date('Ymd') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Event Date', 'Client', 'Phone', 'Pax', 'Total Cost', 'Amount Paid', 'Payment Status', 'Archived At']);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
    
    // Pagination
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? 10));
    $offset = ($page - 1) * $limit;
    
    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM archived_bookings WHERE $whereClause");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);
    
    $stmt = $pdo->prepare("
        SELECT * FROM archived_bookings
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $val) $stmt->bindValue($key, $val);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    jsonResponse(true, '', [
        'archived' => $stmt->fetchAll(),
        'meta' => [
            'currentPage'  => $page,
            'totalPages'   => (int)$totalPages,
            'totalRecords' => $totalRecords
        ]
    ]);
}

if ($method === 'DELETE') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Archived booking ID is required.', [], 422);
    
    $id = (int)$d['id'];
    
    // Find original_id
    $find = $pdo->prepare("SELECT original_id FROM archived_bookings WHERE id = ?");
    $find->execute([$id]);
    $row = $find->fetch();
    if (!$row) jsonResponse(false, 'Archived record not found.', [], 404);
    
    $originalId = (int)$row['original_id'];
    
    $pdo->beginTransaction();
    try {
        // 1. Mark as not archived in bookings table
        $pdo->prepare("UPDATE bookings SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = ?")
            ->execute([$originalId]);
            
        // 2. Remove from archived_bookings snapshot table
        $pdo->prepare("DELETE FROM archived_bookings WHERE id = ?")->execute([$id]);
        
        $pdo->commit();
        
        auditLog($pdo, 'booking_unarchived', 'booking', $originalId);
        jsonResponse(true, 'Booking restored from archive.');
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Unarchive failed: ' . $e->getMessage(), [], 500);
    }
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
               event_location, pax_count, total_cost, amount_paid, payment_status, notes, event_report_notes, archived_by)
            VALUES
              (:original_id, :client_name, :client_phone, :event_date, :event_time,
               :event_location, :pax_count, :total_cost, :amount_paid, :payment_status, :notes, :report_notes, :archived_by)
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
            ':report_notes'   => $booking['event_report_notes'],
            ':archived_by'    => (int)$currentUser['id'],
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

        // Audit: booking archived
        auditLog($pdo, 'booking_archived', 'booking', $bookingId,
            ['booking_status' => $booking['booking_status']],
            ['is_archived' => 1, 'archived_id' => $archId]
        );

        jsonResponse(true, 'Booking archived successfully.', ['archived_id' => $archId], 201);

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Archive failed: ' . $e->getMessage(), [], 500);
    }
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
