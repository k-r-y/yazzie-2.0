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

            $updateInv->execute([
                ':qty_in' => $qtyIn,
                ':notes'  => $notes,
                ':uid'    => $userId,
                ':id'     => $id,
                ':bid'    => $bookingId
            ]);

            // Replenish current stock with returned quantity
            if ($qtyIn > 0) {
                $pdo->prepare("UPDATE equipment SET current_stock = current_stock + :qty WHERE id = :eid")
                    ->execute([':qty' => $qtyIn, ':eid' => $inv['equipment_id']]);
            }

            $diff = $inv['quantity_out'] - $qtyIn;
            if ($diff > 0) {
                // Log breakage
                $unitPrice = (float)$inv['replacement_cost'];
                $totalCost = round($unitPrice * $diff, 2);

                $insBreak = $pdo->prepare("
                    INSERT INTO booking_breakages (booking_id, equipment_id, quantity, unit_price, total_cost, charge_to, notes, logged_by)
                    VALUES (:bid, :eid, :qty, :price, :total, :charge, :notes, :uid)
                ");
                $insBreak->execute([
                    ':bid'    => $bookingId,
                    ':eid'    => $inv['equipment_id'],
                    ':qty'    => $diff,
                    ':price'  => $unitPrice,
                    ':total'  => $totalCost,
                    ':charge' => $chargeTo,
                    ':notes'  => "Auto-logged from inventory return discrepancy. " . ($notes ? "Note: $notes" : ""),
                    ':uid'    => $userId
                ]);

                // Deduct from total stock because it's lost/broken
                // (current_stock was already reduced during dispatch and not replenished by the loss)
                $pdo->prepare("UPDATE equipment SET total_stock = total_stock - :qty WHERE id = :eid")
                    ->execute([':qty' => $diff, ':eid' => $inv['equipment_id']]);

                if ($chargeTo === 'client') {
                    $breakageTotal += $totalCost;
                }
            }
        }

        if ($breakageTotal > 0) {
            // Update booking totals
            $pdo->prepare("
                UPDATE bookings 
                SET breakage_total = breakage_total + :total, 
                    total_cost = total_cost + :total,
                    payment_status = CASE 
                        WHEN payment_status = 'paid' THEN 'partial' 
                        ELSE payment_status 
                    END
                WHERE id = :bid
            ")->execute([':total' => $breakageTotal, ':bid' => $bookingId]);

            // Unarchive logic
            if ($booking['is_archived']) {
                $pdo->prepare("UPDATE bookings SET is_archived = 0, archived_at = NULL, archived_by = NULL WHERE id = :bid")
                    ->execute([':bid' => $bookingId]);
                $pdo->prepare("DELETE FROM archived_bookings WHERE original_id = :bid")
                    ->execute([':bid' => $bookingId]);
                $unarchived = true;
            }
        }

        auditLog($pdo, 'inventory_returned', 'booking', $bookingId, null, [
            'breakage_total' => $breakageTotal,
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
