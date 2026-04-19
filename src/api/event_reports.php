<?php
/**
 * Event Reports API
 * GET  — list reportable bookings for staff (assigned via job_orders, event completed)
 * POST — submit a post-event report (staff) with breakages + overtime calc
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

requireApiRole(['staff', 'admin', 'frontdesk']);
requireCsrf();

$method  = $_SERVER['REQUEST_METHOD'];
$userId  = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'];

// ────────────────────────────────────────────────────────────────
// GET — List bookings staff can report on
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    // Staff: only bookings they were assigned to
    // Admin/Frontdesk: all completed/confirmed bookings
    if ($role === 'staff') {
        $stmt = $pdo->prepare("
            SELECT b.id, b.event_date, b.event_time, b.event_location, b.pax_count,
                   b.booking_status, b.actual_start_time, b.actual_end_time,
                   b.event_report_notes, b.report_submitted_at,
                   b.overtime_minutes, b.overtime_total,
                   c.name AS client_name,
                   pk.set_name AS menu_name
            FROM bookings b
            JOIN clients c ON c.id = b.client_id
            LEFT JOIN packages pk ON pk.id = b.package_id
            INNER JOIN job_orders jo ON jo.booking_id = b.id AND jo.staff_id = :uid
            WHERE b.booking_status IN ('confirmed', 'completed')
              AND b.event_date <= CURDATE()
              AND jo.status = 'accepted'
            ORDER BY b.event_date DESC
            LIMIT 50
        ");
        $stmt->execute([':uid' => $userId]);
    } else {
        $stmt = $pdo->query("
            SELECT b.id, b.event_date, b.event_time, b.event_location, b.pax_count,
                   b.booking_status, b.actual_start_time, b.actual_end_time,
                   b.event_report_notes, b.report_submitted_at,
                   b.overtime_minutes, b.overtime_total,
                   c.name AS client_name,
                   pk.set_name AS menu_name,
                   u.name AS report_by_name
            FROM bookings b
            JOIN clients c ON c.id = b.client_id
            LEFT JOIN packages pk ON pk.id = b.package_id
            LEFT JOIN users u ON u.id = b.report_submitted_by
            WHERE b.booking_status IN ('confirmed', 'completed')
              AND b.event_date <= CURDATE()
            ORDER BY b.event_date DESC
            LIMIT 100
        ");
    }
    $bookings = $stmt->fetchAll();
    jsonResponse(true, '', ['bookings' => $bookings]);
}

// ────────────────────────────────────────────────────────────────
// POST — Submit event report
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];

    if (empty($data['booking_id'])) {
        jsonResponse(false, 'Booking ID is required.', [], 400);
    }

    $bid        = (int)$data['booking_id'];
    $startTime  = $data['actual_start_time'] ?? null;
    $endTime    = $data['actual_end_time'] ?? null;
    $complaints = $data['complaints'] ?? null;
    $breakages  = $data['breakages'] ?? []; // array of {equipment_id, quantity, notes}

    // Validate booking exists and staff is assigned (if staff role)
    if ($role === 'staff') {
        $check = $pdo->prepare("
            SELECT b.id FROM bookings b
            INNER JOIN job_orders jo ON jo.booking_id = b.id AND jo.staff_id = :uid AND jo.status = 'accepted'
            WHERE b.id = :bid
        ");
        $check->execute([':uid' => $userId, ':bid' => $bid]);
        if (!$check->fetch()) {
            jsonResponse(false, 'You are not assigned to this booking.', [], 403);
        }
    }

    // ── Calculate overtime ──
    $overtimeMinutes = 0;
    $overtimeTotal   = 0.00;
    $eventDuration   = function_exists('appSettingInt') ? appSettingInt('event_duration_hours', 4) : 4;
    $overtimeRate    = function_exists('appSetting') ? (float)appSetting('overtime_rate_per_hour', '200') : 200;

    if ($startTime && $endTime) {
        $start = strtotime($startTime);
        $end   = strtotime($endTime);
        if ($start && $end && $end > $start) {
            $actualMinutes = ($end - $start) / 60;
            $standardMinutes = $eventDuration * 60;
            $overtimeMinutes = max(0, (int)($actualMinutes - $standardMinutes));
            $overtimeTotal   = round(($overtimeMinutes / 60) * $overtimeRate, 2);
        }
    }

    try {
        $pdo->beginTransaction();

        // Update booking with report data
        $update = $pdo->prepare("
            UPDATE bookings 
            SET actual_start_time = :start, 
                actual_end_time = :end,
                event_report_notes = :notes,
                report_submitted_by = :staff,
                report_submitted_at = NOW(),
                overtime_minutes = :ot_min,
                overtime_rate = :ot_rate,
                overtime_total = :ot_total
            WHERE id = :id
        ");
        $update->execute([
            ':start'    => $startTime,
            ':end'      => $endTime,
            ':notes'    => $complaints,
            ':staff'    => $userId,
            ':id'       => $bid,
            ':ot_min'   => $overtimeMinutes,
            ':ot_rate'  => $overtimeRate,
            ':ot_total' => $overtimeTotal,
        ]);

        // ── Log breakages ──
        $breakageTotal = 0;
        if (!empty($breakages)) {
            $insertBreak = $pdo->prepare("
                INSERT INTO booking_breakages (booking_id, equipment_id, quantity, unit_price, total_cost, notes, logged_by)
                VALUES (:bid, :eid, :qty, :price, :total, :notes, :uid)
            ");

            foreach ($breakages as $br) {
                if (empty($br['equipment_id']) || empty($br['quantity'])) continue;
                $qty = max(1, (int)$br['quantity']);

                // Get equipment price
                $eStmt = $pdo->prepare("SELECT name, replacement_cost FROM equipment WHERE id = :id AND is_active = 1");
                $eStmt->execute([':id' => (int)$br['equipment_id']]);
                $equipment = $eStmt->fetch();
                if (!$equipment) continue;

                $unitPrice = (float)$equipment['replacement_cost'];
                $total = round($unitPrice * $qty, 2);
                $breakageTotal += $total;

                $insertBreak->execute([
                    ':bid'   => $bid,
                    ':eid'   => (int)$br['equipment_id'],
                    ':qty'   => $qty,
                    ':price' => $unitPrice,
                    ':total' => $total,
                    ':notes' => $br['notes'] ?? null,
                    ':uid'   => $userId,
                ]);
            }
        }

        // Audit Trail
        auditLog($pdo, 'event_report_submitted', 'booking', $bid, null, [
            'actual_start'     => $startTime,
            'actual_end'       => $endTime,
            'overtime_minutes' => $overtimeMinutes,
            'overtime_total'   => $overtimeTotal,
            'breakage_total'   => $breakageTotal,
            'submitted_by'     => $userId,
        ]);

        $pdo->commit();

        jsonResponse(true, 'Event report submitted successfully.', [
            'overtime_minutes' => $overtimeMinutes,
            'overtime_total'   => $overtimeTotal,
            'breakage_total'   => $breakageTotal,
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Failed to submit report: ' . $e->getMessage(), [], 500);
    }
}

jsonResponse(false, 'Method not allowed.', [], 405);
