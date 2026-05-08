<?php
/**
 * Payment Status API
 * GET /src/api/payment_status.php
 *
 * Polled by the public invoice page to check if a PayMongo checkout session
 * has successfully recorded a payment via the webhook.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';

$bookingId = (int)($_GET['booking_id'] ?? 0);
$token     = $_GET['token'] ?? '';

if ($bookingId <= 0) {
    jsonResponse(false, 'Invalid booking ID.', [], 400);
}

// Ensure the caller is authorized to view this booking's status
$stmt = $pdo->prepare("
    SELECT payment_status, amount_paid, invoice_token 
    FROM bookings 
    WHERE id = :id 
    LIMIT 1
");
$stmt->execute([':id' => $bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    jsonResponse(false, 'Booking not found.', [], 404);
}

// Token validation: must match the database token OR have an active admin session
$isAuth = false;
session_start();
if (isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'frontdesk'], true)) {
    $isAuth = true;
}

if (!$isAuth && (empty($booking['invoice_token']) || $booking['invoice_token'] !== $token)) {
    jsonResponse(false, 'Unauthorized.', [], 401);
}

// Return the highly optimized JSON payload
echo json_encode([
    'success'        => true,
    'payment_status' => $booking['payment_status'],
    'amount_paid'    => (float)$booking['amount_paid']
]);
exit;
