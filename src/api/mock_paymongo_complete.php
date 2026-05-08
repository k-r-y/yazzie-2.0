<?php
/**
 * Mock PayMongo Completion API
 * POST /src/api/mock_paymongo_complete.php
 * 
 * Used only during development to simulate a successful payment webhook
 * when the system is in "mock_paymongo" mode.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/audit.php';

header('Content-Type: application/json');

// Only allow in development or if explicitly enabled
if (APP_ENV === 'production' && !isset($_GET['force_mock'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden in production']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$bookingId = (int)($data['booking_id'] ?? 0);

if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid booking ID']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get booking details
    $stmt = $pdo->prepare("SELECT id, total_cost, amount_paid FROM bookings WHERE id = :id FOR UPDATE");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception("Booking #$bookingId not found");
    }

    // 2. Calculate remaining balance to "pay"
    // For simulation, we'll assume they paid the remaining balance or at least the minimum downpayment.
    // Let's just simulate paying the full remaining balance.
    $totalCost = (float)$booking['total_cost'];
    $alreadyPaid = (float)$booking['amount_paid'];
    $amountToPay = max(0, $totalCost - $alreadyPaid);

    if ($amountToPay <= 0) {
        $pdo->rollBack();
        echo json_encode(['success' => true, 'message' => 'Already fully paid']);
        exit;
    }

    $gatewayRefId = 'mock_' . bin2hex(random_bytes(8));

    // 3. Insert payment record
    $pdo->prepare("
        INSERT INTO payments (booking_id, amount, payment_method, reference_no, gateway_reference_id, payment_date, notes, recorded_by, payment_type)
        VALUES (:bid, :amt, 'gcash', :ref, :gref, CURDATE(), 'MOCK PAYMONGO SUCCESS', 0, 'payment')
    ")->execute([
        ':bid' => $bookingId,
        ':amt' => $amountToPay,
        ':ref' => $gatewayRefId,
        ':gref' => $gatewayRefId
    ]);

    // 4. Update booking
    $pdo->prepare("
        UPDATE bookings 
        SET amount_paid = total_cost, 
            payment_status = 'paid', 
            booking_status = 'confirmed',
            updated_at = NOW() 
        WHERE id = :id
    ")->execute([':id' => $bookingId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Payment simulated successfully']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
