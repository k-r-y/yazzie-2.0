<?php
/**
 * PayMongo Webhook Listener (Enhanced Debugging)
 */

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/audit.php';

// Disable all direct output
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

function debugLog($msg) {
    file_put_contents(__DIR__ . '/../../../temp/webhook_debug.log', date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

debugLog("--- WEBHOOK STARTED ---");

// 1. Method Guard
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Error: Method not allowed: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405);
    exit;
}

// 2. Read Payload
$rawPayload = file_get_contents('php://input');
debugLog("Payload received (bytes): " . strlen($rawPayload));
file_put_contents(__DIR__ . '/../../../temp/paymongo_webhook_last.json', $rawPayload);

// 3. Signature Guard
$sigHeader = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';
debugLog("Signature Header: " . $sigHeader);

if (empty($sigHeader)) {
    debugLog("Error: Missing signature header");
    http_response_code(401);
    exit;
}

$sigParts = [];
foreach (explode(',', $sigHeader) as $part) {
    if (strpos($part, '=') === false) continue;
    [$k, $v] = explode('=', trim($part), 2);
    $sigParts[$k] = $v;
}

$timestamp = $sigParts['t'] ?? '';
$receivedHmac = (($_ENV['APP_ENV'] ?? 'development') === 'production')
    ? ($sigParts['li'] ?? '')
    : ($sigParts['te'] ?? '');

debugLog("Timestamp: $timestamp | Received HMAC: $receivedHmac");

$webhookSecret = $_ENV['PAYMONGO_WEBHOOK_SECRET_KEY'] ?? '';
debugLog("Using Secret: " . ($webhookSecret ? substr($webhookSecret, 0, 8) . '...' : 'NOT SET'));

if (empty($webhookSecret)) {
    debugLog("Error: Secret not set in .env");
    http_response_code(500);
    exit;
}

$expectedHmac = hash_hmac('sha256', $timestamp . '.' . $rawPayload, $webhookSecret);
debugLog("Expected HMAC: $expectedHmac");

if (!hash_equals($expectedHmac, $receivedHmac)) {
    debugLog("Error: Signature mismatch!");
    http_response_code(401);
    exit;
}

// 4. Decode
$event = json_decode($rawPayload, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    debugLog("Error: JSON Decode Fail: " . json_last_error_msg());
    http_response_code(400);
    exit;
}

$eventType = $event['data']['attributes']['type'] ?? '';
debugLog("Event Type: $eventType");

if (!in_array($eventType, ['payment.paid', 'checkout_session.payment.paid'])) {
    debugLog("Note: Ignoring event type: $eventType");
    http_response_code(200);
    exit;
}

// 5. Extract
$dataObj    = $event['data']['attributes']['data'] ?? [];
$dataAttrs  = $dataObj['attributes']               ?? [];

if ($eventType === 'checkout_session.payment.paid') {
    $paymentObj   = $dataAttrs['payments'][0]              ?? [];
    $paymentAttrs = $paymentObj['attributes']              ?? [];
    $gatewayRefId = $paymentObj['id']                      ?? '';
    $metadata     = $dataAttrs['metadata']                 ?? [];
} else {
    $paymentAttrs = $dataAttrs;
    $gatewayRefId = $dataObj['id']                         ?? '';
    $metadata     = $paymentAttrs['metadata']              ?? [];
}

$amountCentavos = (int)($paymentAttrs['amount'] ?? 0);
$amountPesos = round($amountCentavos / 100, 2);
$bookingId = (int)($metadata['booking_id'] ?? 0);

debugLog("Extracted: Booking #$bookingId | Amount: $amountPesos | Ref: $gatewayRefId");

if ($bookingId <= 0 || empty($gatewayRefId)) {
    debugLog("Error: Missing Booking ID or Ref");
    http_response_code(422);
    exit;
}

// 6. Database Update
try {
    $pdo->beginTransaction();

    $bStmt = $pdo->prepare("SELECT id, amount_paid, total_cost, booking_status, event_date FROM bookings WHERE id = :id FOR UPDATE");
    $bStmt->execute([':id' => $bookingId]);
    $booking = $bStmt->fetch();

    if (!$booking) {
        debugLog("Error: Booking #$bookingId not found in DB");
        $pdo->rollBack();
        http_response_code(404);
        exit;
    }

    // Idempotency
    $check = $pdo->prepare("SELECT id FROM payments WHERE gateway_reference_id = :ref LIMIT 1");
    $check->execute([':ref' => $gatewayRefId]);
    if ($check->fetch()) {
        debugLog("Note: Already processed. Skipping.");
        $pdo->rollBack();
        http_response_code(200);
        exit;
    }

    // Insert Payment
    // We use a subquery for recorded_by to ensure it always hits a valid user ID (the first admin),
    // since the 'payments' table has a NOT NULL foreign key constraint on users.id.
    $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, reference_no, gateway_reference_id, payment_date, notes, recorded_by, payment_type)
                   VALUES (:bid, :amt, 'paymongo', :ref, :gref, CURDATE(), :notes, (SELECT id FROM users LIMIT 1), 'payment')")
        ->execute([
            ':bid' => $bookingId,
            ':amt' => $amountPesos,
            ':ref' => $gatewayRefId,
            ':gref' => $gatewayRefId,
            ':notes' => "PayMongo Auto-Update ($eventType)"
        ]);

    $newPaid = $booking['amount_paid'] + $amountPesos;
    $status = ($newPaid >= $booking['total_cost'] - 0.01) ? 'paid' : 'partial';

    $bStatus = $booking['booking_status'];
    if ($bStatus === 'pending') {
        $eventDateObj = new DateTime($booking['event_date']);
        $now = new DateTime();
        $interval = $now->diff($eventDateObj);
        $diffHours = ($interval->days * 24) + $interval->h;
        // Assume constants are loaded from config.php
        $dpPercent = (!$interval->invert && $diffHours < RUSH_THRESHOLD_HOURS) ? RUSH_DP_PERCENT : MIN_DP_PERCENT;
        $minDPThresh = round($booking['total_cost'] * $dpPercent, 2);
        
        if ($newPaid >= $minDPThresh - 0.01) {
            $bStatus = 'confirmed';
        }
    }

    $pdo->prepare("UPDATE bookings SET amount_paid = :p, payment_status = :s, booking_status = :bs, updated_at = NOW() WHERE id = :id")
        ->execute([':p' => $newPaid, ':s' => $status, ':bs' => $bStatus, ':id' => $bookingId]);

    $pdo->commit();
    debugLog("SUCCESS: Booking #$bookingId updated to $status.");
    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    debugLog("FATAL DB ERROR: " . $e->getMessage());
    http_response_code(500);
}

ob_end_clean();
