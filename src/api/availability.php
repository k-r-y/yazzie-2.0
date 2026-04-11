<?php
/**
 * Availability API
 * GET ?date=YYYY-MM-DD  → Check if an event date is available
 *
 * Rule: Only ONE non-cancelled booking may exist per event_date.
 * Cancelled bookings free up the date.
 *
 * GET ?date=YYYY-MM-DD&exclude_id=X  → Exclude booking #X (for edit flows)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require auth — even frontdesk/staff can check availability
requireApiRole(['admin', 'frontdesk']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}

$date = trim($_GET['date'] ?? '');
$excludeId = (int)($_GET['exclude_id'] ?? 0);

// Validate date format
if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonResponse(false, 'A valid date (YYYY-MM-DD) is required.', [], 422);
}

// Must not be in the past
if ($date < date('Y-m-d')) {
    jsonResponse(false, 'Cannot book a date in the past.', [
        'available' => false,
        'reason'    => 'past',
    ], 422);
}

// Check for existing non-cancelled bookings on this date
$sql = "
    SELECT b.id, c.name AS client_name, b.event_time, b.event_location, b.pax_count,
           b.booking_status,
           COALESCE(pk.set_name, m.name, 'Package Booking') AS menu_name
    FROM bookings b
    JOIN clients  c  ON c.id  = b.client_id
    LEFT JOIN menus    m  ON m.id  = b.menu_id
    LEFT JOIN packages pk ON pk.id = b.package_id
    WHERE b.event_date = :date
      AND b.booking_status NOT IN ('cancelled')
";
$params = [':date' => $date];

if ($excludeId > 0) {
    $sql    .= ' AND b.id != :excl';
    $params[':excl'] = $excludeId;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$existing = $stmt->fetch();

if ($existing) {
    jsonResponse(true, '', [
        'available' => false,
        'reason'    => 'taken',
        'booking'   => [
            'id'             => $existing['id'],
            'client_name'    => $existing['client_name'],
            'event_time'     => $existing['event_time'],
            'event_location' => $existing['event_location'],
            'pax_count'      => $existing['pax_count'],
            'booking_status' => $existing['booking_status'],
            'menu_name'      => $existing['menu_name'],
        ],
    ]);
}

jsonResponse(true, 'Date is available.', ['available' => true]);
