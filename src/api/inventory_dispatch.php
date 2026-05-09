<?php
/**
 * Inventory Dispatch API
 * GET    ?booking_id=X — List dispatched items for a booking
 * POST                 — Record dispatch (ingress)
 * PUT                  — Record return (egress)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

requireApiRole(['admin', 'frontdesk', 'staff']);
requireCsrf();
$method = $_SERVER['REQUEST_METHOD'];
$userId = (int)$_SESSION['user_id'];

// ────────────────────────────────────────────────────────────────
// GET — List dispatched items
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    if (empty($_GET['booking_id'])) jsonResponse(false, 'Booking ID is required.', [], 422);
    $bookingId = (int)$_GET['booking_id'];

    $stmt = $pdo->prepare("
        SELECT bi.*, e.name AS equipment_name, e.unit, e.replacement_cost,
               u1.name AS dispatched_by_name, u2.name AS returned_by_name
        FROM booking_inventory bi
        JOIN equipment e ON e.id = bi.equipment_id
        JOIN users u1 ON u1.id = bi.dispatched_by
        LEFT JOIN users u2 ON u2.id = bi.returned_by
        WHERE bi.booking_id = :bid
        ORDER BY bi.dispatched_at ASC
    ");
    $stmt->execute([':bid' => $bookingId]);
    $items = $stmt->fetchAll();

    jsonResponse(true, '', ['items' => $items]);
}

// ────────────────────────────────────────────────────────────────
// POST — Record Dispatch (Ingress)
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (empty($d['booking_id'])) jsonResponse(false, 'Booking ID is required.', [], 422);
    if (empty($d['items']) || !is_array($d['items'])) jsonResponse(false, 'Items are required.', [], 422);

    $bookingId = (int)$d['booking_id'];
    
    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare("
            INSERT INTO booking_inventory (booking_id, equipment_id, quantity_out, dispatch_notes, dispatched_by)
            VALUES (:bid, :eid, :qty, :notes, :uid)
            ON DUPLICATE KEY UPDATE 
                quantity_out = quantity_out + VALUES(quantity_out),
                dispatch_notes = CONCAT_WS('; ', dispatch_notes, VALUES(dispatch_notes))
        ");

        foreach ($d['items'] as $item) {
            if (empty($item['equipment_id']) || empty($item['quantity'])) continue;
            
            $eid = (int)$item['equipment_id'];
            $qty = (int)$item['quantity'];
            $notes = $item['notes'] ?? null;

            // Check stock
            $sStmt = $pdo->prepare("SELECT name, current_stock FROM equipment WHERE id = :eid FOR UPDATE");
            $sStmt->execute([':eid' => $eid]);
            $eq = $sStmt->fetch();
            
            if (!$eq) throw new Exception("Equipment ID $eid not found.");
            if ($eq['current_stock'] < $qty) {
                throw new Exception("Insufficient stock for '{$eq['name']}'. Available: {$eq['current_stock']}.");
            }

            $ins->execute([
                ':bid'   => $bookingId,
                ':eid'   => $eid,
                ':qty'   => $qty,
                ':notes' => $notes,
                ':uid'   => $userId
            ]);

            // Optional: You might want to deduct from current_stock here if you consider dispatched items "unavailable"
            // But usually current_stock should reflect what's in the warehouse.
            // If it's dispatched, it's not in the warehouse.
            $pdo->prepare("UPDATE equipment SET current_stock = current_stock - :qty WHERE id = :eid")
                ->execute([':qty' => $qty, ':eid' => $eid]);
        }

        auditLog($pdo, 'inventory_dispatched', 'booking', $bookingId, null, ['count' => count($d['items'])]);
        $pdo->commit();
        jsonResponse(true, 'Inventory dispatched successfully.');
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to dispatch inventory: ' . $e->getMessage(), [], 500);
    }
}

// ────────────────────────────────────────────────────────────────
// PUT — Record Return (Egress)
// ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (empty($d['booking_id'])) jsonResponse(false, 'Booking ID is required.', [], 422);
    if (empty($d['returns']) || !is_array($d['returns'])) jsonResponse(false, 'Return data is required.', [], 422);

    $bookingId = (int)$d['booking_id'];

    $pdo->beginTransaction();
    try {
        // Check if booking is archived
        $bStmt = $pdo->prepare("SELECT is_archived, payment_status FROM bookings WHERE id = :id");
        $bStmt->execute([':id' => $bookingId]);
        $booking = $bStmt->fetch();
        if (!$booking) throw new Exception("Booking not found.");

        $updateInv = $pdo->prepare("
            UPDATE booking_inventory 
            SET quantity_in = :qty_in, 
                return_notes = :notes,
                returned_by = :uid,
                returned_at = NOW()
            WHERE id = :id AND booking_id = :bid
        ");

        $breakageTotal = 0;
        $unarchived = false;

        foreach ($d['returns'] as $ret) {
            if (empty($ret['id'])) continue;
            
            $id = (int)$ret['id'];
            $qtyIn = (int)$ret['quantity_in'];
            $notes = $ret['notes'] ?? null;
            $chargeTo = $ret['charge_to'] ?? 'client'; // Default to client if discrepancy

            // Get dispatch info
            $infoStmt = $pdo->prepare("
                SELECT bi.*, e.name, e.replacement_cost, e.current_stock 
                FROM booking_inventory bi
                JOIN equipment e ON e.id = bi.equipment_id
                WHERE bi.id = :id
            ");
            $infoStmt->execute([':id' => $id]);
            $inv = $infoStmt->fetch();
            if (!$inv) continue;

            if ($qtyIn > $inv['quantity_out']) {
                throw new Exception("Returned quantity ($qtyIn) for '{$inv['name']}' cannot exceed dispatched quantity ({$inv['quantity_out']}).");
            }

            $updateInv->execute([
                ':qty_in' => $qtyIn,
                ':notes'  => $notes,
                ':uid'    => $userId,
                ':id'     => $id,
                ':bid'    => $bookingId
            ]);

            // Replenish current stock using delta to allow idempotent updates
            $delta = $qtyIn - (int)$inv['quantity_in'];
            if ($delta != 0) {
                $pdo->prepare("UPDATE equipment SET current_stock = current_stock + :qty WHERE id = :eid")
                    ->execute([':qty' => $delta, ':eid' => $inv['equipment_id']]);
            }

            $diff = $inv['quantity_out'] - $qtyIn;
            if ($diff > 0) {
                $totalCost = $diff * $inv['replacement_cost'];
                $insBreak = $pdo->prepare("
                    INSERT INTO booking_breakages (booking_id, equipment_id, quantity, unit_price, total_cost, charge_to, notes, logged_by)
                    VALUES (:bid, :eid, :qty, :price, :total, :charge, :notes, :uid)
                    ON DUPLICATE KEY UPDATE 
                        quantity = VALUES(quantity),
                        total_cost = VALUES(total_cost),
                        notes = VALUES(notes),
                        logged_by = VALUES(logged_by),
                        logged_at = NOW()
                ");
                $insBreak->execute([
                    ':bid'    => $bookingId,
                    ':eid'    => $inv['equipment_id'],
                    ':qty'    => $diff,
                    ':price'  => $inv['replacement_cost'],
                    ':total'  => $totalCost,
                    ':charge' => strtoupper($chargeTo),
                    ':notes'  => "Auto-logged from inventory return discrepancy.",
                    ':uid'    => $userId
                ]);
            } else {
                // If diff <= 0, remove any existing breakage record for this item in this booking
                $pdo->prepare("DELETE FROM booking_breakages WHERE booking_id = :bid AND equipment_id = :eid")
                    ->execute([':bid' => $bookingId, ':eid' => $inv['equipment_id']]);
            }
        }

        // 3. Synchronize Booking Totals (Recalculate from all breakages for this booking)
        // COALESCE on every cost column guards against NULL arithmetic nullifying total_cost
        $sync = $pdo->prepare("
            UPDATE bookings b
            SET b.breakage_total = (
                    SELECT COALESCE(SUM(total_cost), 0) 
                    FROM booking_breakages 
                    WHERE booking_id = :bid1 AND charge_to = 'CLIENT'
                ),
                b.total_cost = (
                    COALESCE(b.base_price, 0)
                    + COALESCE(b.extra_cost, 0)
                    + COALESCE(b.transport_fee, 0)
                    + COALESCE(b.surcharge_total, 0)
                    + COALESCE(b.overtime_total, 0)
                ) + (
                    SELECT COALESCE(SUM(total_cost), 0) 
                    FROM booking_breakages 
                    WHERE booking_id = :bid2 AND charge_to = 'CLIENT'
                ),
                b.payment_status = CASE 
                    WHEN b.amount_paid <= 0 THEN 'unpaid'
                    WHEN b.amount_paid >= (
                        (
                            COALESCE(b.base_price, 0)
                            + COALESCE(b.extra_cost, 0)
                            + COALESCE(b.transport_fee, 0)
                            + COALESCE(b.surcharge_total, 0)
                            + COALESCE(b.overtime_total, 0)
                        ) + (
                            SELECT COALESCE(SUM(total_cost), 0) 
                            FROM booking_breakages 
                            WHERE booking_id = :bid3 AND charge_to = 'CLIENT'
                        )
                    ) THEN 'paid'
                    ELSE 'partial'
                END
            WHERE b.id = :bid4
        ");
        $sync->execute([
            ':bid1' => $bookingId,
            ':bid2' => $bookingId,
            ':bid3' => $bookingId,
            ':bid4' => $bookingId
        ]);

        // Unarchive logic
        if ($booking['is_archived']) {
            $pdo->prepare("UPDATE bookings SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = :bid")
                ->execute([':bid' => $bookingId]);
            $pdo->prepare("DELETE FROM archived_bookings WHERE original_id = :bid")
                ->execute([':bid' => $bookingId]);
            $unarchived = true;
        }

        // Fetch updated booking for audit log
        $updatedBooking = $pdo->prepare("SELECT breakage_total FROM bookings WHERE id = :id");
        $updatedBooking->execute([':id' => $bookingId]);
        $newTotals = $updatedBooking->fetch();

        auditLog($pdo, 'inventory_returned', 'booking', $bookingId, null, [
            'breakage_total' => $newTotals['breakage_total'] ?? 0,
            'unarchived'     => $unarchived
        ]);

        $pdo->commit();
        jsonResponse(true, 'Inventory return recorded successfully.' . ($unarchived ? ' Booking has been unarchived due to new charges.' : ''));
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to record return: ' . $e->getMessage(), [], 500);
    }
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
