<?php
/**
 * Booking Breakages API
 * GET    ?booking_id=X — List losses for a booking
 * POST                 — Log a new breakage entry
 * DELETE               — Remove a breakage entry
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

requireApiRole(['admin', 'frontdesk', 'staff']);
requireCsrf();
$method = $_SERVER['REQUEST_METHOD'];

// ────────────────────────────────────────────────────────────────
// GET — List breakages for a booking
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (empty($_GET['booking_id'])) jsonResponse(false, 'Booking ID is required.', [], 422);
    $bookingId = (int)$_GET['booking_id'];

    $stmt = $pdo->prepare("
        SELECT bb.*, e.name AS equipment_name, e.unit, u.name AS logged_by_name
        FROM booking_breakages bb
        JOIN equipment e ON e.id = bb.equipment_id
        JOIN users u ON u.id = bb.logged_by
        WHERE bb.booking_id = :bid
        ORDER BY bb.logged_at DESC
    ");
    $stmt->execute([':bid' => $bookingId]);
    $items = $stmt->fetchAll();

    // Calculate totals based on who is charged
    $totalCost = array_sum(array_column($items, 'total_cost'));
    $clientCharged = array_sum(array_map(function($i) { return $i['charge_to'] === 'client' ? $i['total_cost'] : 0; }, $items));

    jsonResponse(true, '', [
        'items'          => $items,
        'total_cost'     => $totalCost,
        'client_charged' => $clientCharged
    ]);
}

// ────────────────────────────────────────────────────────────────
// POST — Log a breakage
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $required = ['booking_id', 'equipment_id', 'quantity'];
    foreach ($required as $f) {
        if (empty($d[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }

    $bookingId   = (int)$d['booking_id'];
    $equipmentId = (int)$d['equipment_id'];
    $quantity    = (int)$d['quantity'];
    $chargeTo    = in_array($d['charge_to'] ?? '', ['client', 'staff', 'business']) ? $d['charge_to'] : 'client';

    if ($quantity <= 0) jsonResponse(false, 'Quantity must be greater than zero.', [], 422);

    // Fetch equipment to get live price and check stock
    $eStmt = $pdo->prepare("SELECT name, replacement_cost, current_stock FROM equipment WHERE id = :id AND is_active = 1");
    $eStmt->execute([':id' => $equipmentId]);
    $equipment = $eStmt->fetch();
    if (!$equipment) jsonResponse(false, 'Equipment not found or inactive.', [], 404);

    $unitPrice = (float)$equipment['replacement_cost'];
    $totalCost = round($unitPrice * $quantity, 2);

    if ($equipment['current_stock'] < $quantity) {
        jsonResponse(false, 'Insufficient stock. Only ' . $equipment['current_stock'] . ' available.', [], 400);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO booking_breakages (booking_id, equipment_id, quantity, unit_price, total_cost, charge_to, notes, logged_by)
            VALUES (:bid, :eid, :qty, :price, :total, :charge, :notes, :uid)
        ");
        $stmt->execute([
            ':bid'    => $bookingId,
            ':eid'    => $equipmentId,
            ':qty'    => $quantity,
            ':price'  => $unitPrice,
            ':total'  => $totalCost,
            ':charge' => $chargeTo,
            ':notes'  => $d['notes'] ?? null,
            ':uid'    => (int)$_SESSION['user_id']
        ]);
        $newId = $pdo->lastInsertId();

        // Deduct from stock
        $pdo->prepare("UPDATE equipment SET current_stock = current_stock - :qty, total_stock = total_stock - :qty WHERE id = :eid")
            ->execute([':qty' => $quantity, ':eid' => $equipmentId]);

        // If charged to client, update booking breakage total
        if ($chargeTo === 'client') {
            $pdo->prepare("UPDATE bookings SET breakage_total = breakage_total + :total, total_cost = total_cost + :total WHERE id = :bid")
                ->execute([':total' => $totalCost, ':bid' => $bookingId]);
        }

        // Audit Log
        auditLog($pdo, 'breakage_logged', 'booking', $bookingId, 
            null, 
            ['item' => $equipment['name'], 'qty' => $quantity, 'total_cost' => $totalCost, 'charge_to' => $chargeTo]
        );

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to log breakage: ' . $e->getMessage(), [], 500);
    }

    jsonResponse(true, 'Breakage logged successfully.', [
        'id'         => $newId,
        'total_cost' => $totalCost
    ], 201);
}

// ────────────────────────────────────────────────────────────────
// DELETE — Remove breakage log
// ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireApiRole('admin'); // Only admins can remove breakage logs
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'ID is required.', [], 422);

    $id = (int)$d['id'];

    // Get info for audit and restoration
    $stmt = $pdo->prepare("SELECT booking_id, equipment_id, quantity, total_cost, charge_to FROM booking_breakages WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if (!$row) jsonResponse(false, 'Entry not found.', [], 404);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM booking_breakages WHERE id = :id")->execute([':id' => $id]);

        // Restore stock
        $pdo->prepare("UPDATE equipment SET current_stock = current_stock + :qty, total_stock = total_stock + :qty WHERE id = :eid")
            ->execute([':qty' => $row['quantity'], ':eid' => $row['equipment_id']]);

        // If it was charged to client, subtract from booking totals
        if ($row['charge_to'] === 'client') {
            $pdo->prepare("UPDATE bookings SET breakage_total = breakage_total - :total, total_cost = total_cost - :total WHERE id = :bid")
                ->execute([':total' => $row['total_cost'], ':bid' => $row['booking_id']]);
        }

        auditLog($pdo, 'breakage_deleted', 'booking', $row['booking_id'], 
            ['total_cost' => $row['total_cost']], 
            null
        );
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to remove breakage log: ' . $e->getMessage(), [], 500);
    }

    jsonResponse(true, 'Breakage log removed and stock restored.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
