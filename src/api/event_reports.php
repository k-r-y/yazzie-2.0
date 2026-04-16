<?php
/**
 * Event Reports API
 * POST — submit a post-event report (staff only)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

requireApiRole(['staff', 'admin', 'frontdesk']);

function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = readJsonBody();
    
    if (empty($data['booking_id'])) {
        jsonResponse(false, 'Booking ID is required.', [], 400);
    }

    $bid = (int)$data['booking_id'];
    $startTime = $data['actual_start_time'] ?? null;
    $endTime   = $data['actual_end_time'] ?? null;
    $complaints = $data['complaints'] ?? null;
    $staffId   = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        // Check if report already exists or update booking
        // For simplicity, we'll store actual times in the bookings table
        // and complaints in a new table if it doesn't exist.
        // Let's check if columns exist first.
        
        $update = $pdo->prepare("
            UPDATE bookings 
            SET actual_start_time = :start, 
                actual_end_time = :end,
                event_report_notes = :notes,
                report_submitted_by = :staff,
                report_submitted_at = NOW()
            WHERE id = :id
        ");
        
        $update->execute([
            ':start' => $startTime,
            ':end'   => $endTime,
            ':notes' => $complaints,
            ':staff' => $staffId,
            ':id'    => $bid
        ]);

        // Audit Trail
        auditLog($pdo, 'event_report_submitted', 'booking', $bid, null, [
            'actual_start' => $startTime,
            'actual_end' => $endTime,
            'submitted_by' => $staffId
        ]);

        $pdo->commit();
        jsonResponse(true, 'Event report submitted successfully.');

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResponse(false, 'Failed to submit report: ' . $e->getMessage(), [], 500);
    }
} else {
    jsonResponse(false, 'Method not allowed.', [], 405);
}
