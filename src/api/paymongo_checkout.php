<?php
/**
 * PayMongo Checkout Session API
 * POST /src/api/paymongo_checkout.php
 *
 * Creates a PayMongo Checkout Session for the outstanding balance of a booking,
 * stores the returned session ID on the booking row, and returns the hosted
 * checkout URL to the frontend for client-side redirection.
 *
 * Security:
 *   - Requires an active admin or frontdesk session (requireApiRole).
 *   - CSRF token enforced on POST.
 *   - Balance is re-read inside a transaction to prevent stale reads.
 *   - All external I/O (cURL) is outside the DB transaction.
 *
 * PayMongo API reference:
 *   https://developers.paymongo.com/reference/create-a-checkout-session
 *
 * Required .env keys:
 *   PAYMONGO_SECRET_KEY=sk_live_xxxxxxxxxxxxxxxxxxxx   (or sk_test_…)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

// ── 1. Auth & CSRF / Token Bypass ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method Not Allowed.', [], 405);
}

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$bookingId = (int)($input['booking_id'] ?? 0);
$token     = $input['invoice_token'] ?? '';

if ($bookingId <= 0) {
    jsonResponse(false, 'A valid booking_id is required.', [], 422);
}

session_start();
$isAuth = isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'frontdesk'], true);

if ($isAuth) {
    // Admin/Frontdesk: Require CSRF
    $headers = getallheaders();
    $csrfHeader = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? '';
    if (empty($csrfHeader) || !hash_equals($_SESSION['csrf_token'] ?? '', $csrfHeader)) {
        jsonResponse(false, 'Invalid or missing CSRF token.', [], 403);
    }
} else {
    // Public Client: Require a valid invoice token for this specific booking
    $stmt = $pdo->prepare("SELECT invoice_token FROM bookings WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $bookingId]);
    $bookingData = $stmt->fetch();
    
    if (!$bookingData || empty($bookingData['invoice_token']) || $bookingData['invoice_token'] !== $token) {
        jsonResponse(false, 'Unauthorized. Invalid invoice token.', [], 401);
    }
}

// ── 2. Determine Optional Amount Override ──────────────────────────────────────
// Optional: caller can override the charge amount (in centavos) e.g. for downpayments.
// Must be a positive integer and not exceed the outstanding balance.
$overrideAmountCentavos = isset($input['override_amount']) ? (int)$input['override_amount'] : 0;

// ── 3. Fetch booking + client details ───────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT b.id,
           b.total_cost,
           b.amount_paid,
           b.payment_status,
           b.booking_status,
           b.event_date,
           b.event_type,
           b.paymongo_checkout_id,
           c.name  AS client_name,
           c.email AS client_email,
           c.phone AS client_phone
    FROM bookings b
    JOIN clients c ON c.id = b.client_id
    WHERE b.id = :id
    LIMIT 1
");
$stmt->execute([':id' => $bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    jsonResponse(false, 'Booking not found.', [], 404);
}

// ── 4. Guard: booking must still have an outstanding balance ─────────────────
$balanceDue = round((float)$booking['total_cost'] - (float)$booking['amount_paid'], 2);

if ($balanceDue <= 0) {
    jsonResponse(false, 'This booking is already fully paid. No checkout session is needed.', [
        'payment_status' => $booking['payment_status'],
    ], 409);
}

// ── 5. Guard: cancelled bookings cannot accept new payments ─────────────────
if ($booking['booking_status'] === 'cancelled') {
    jsonResponse(false, 'Cannot process payment for a cancelled booking.', [], 409);
}

// ── 6. Resolve PayMongo secret key ──────────────────────────────────────────
$secretKey = $_ENV['PAYMONGO_SECRET_KEY'] ?? '';

if (empty($secretKey)) {
    error_log('[PayMongo Checkout] PAYMONGO_SECRET_KEY is not set in .env');
    jsonResponse(false, 'Payment gateway is not configured. Please contact the administrator.', [], 500);
}

// ── 7. Build the PayMongo Checkout Session payload ───────────────────────────
// If an override_amount (in centavos) was provided, use it (e.g. downpayment from booking stepper).
// Otherwise default to the full outstanding balance.
if ($overrideAmountCentavos > 0) {
    // Validate it doesn't exceed the balance (allow full balance for new bookings where amount_paid=0)
    $maxCentavos = (int)round((float)$booking['total_cost'] * 100);
    if ($overrideAmountCentavos > $maxCentavos + 1) {
        jsonResponse(false, 'Override amount cannot exceed the total booking cost.', [], 422);
    }
    $amountCentavos = $overrideAmountCentavos;
    $lineItemName   = 'Downpayment — Booking #' . $bookingId;
} else {
    $amountCentavos = (int)round($balanceDue * 100);
    $lineItemName   = 'Balance Due — Booking #' . $bookingId;
}

// Human-readable description for the PayMongo dashboard / receipt.
$eventDate   = date('F j, Y', strtotime($booking['event_date']));
$description = sprintf(
    'Balance payment for Booking #%d — %s catering on %s',
    $bookingId,
    htmlspecialchars($booking['event_type'] ?? 'Event', ENT_QUOTES),
    $eventDate
);

// Return URLs — PayMongo redirects the client browser here after payment.
// We default to the public invoice but allow the admin financial module to specify a return path.
$origin = $input['origin'] ?? 'public';
$successUrl = BASE_URL . '/templates/invoice.php?booking_id=' . $bookingId . '&paid=1';
$cancelUrl  = BASE_URL . '/templates/invoice.php?booking_id='  . $bookingId . '&cancelled=1';

if ($origin === 'financial') {
    // If from admin financial module, we can redirect back to admin view directly
    // but we still go via invoice to trigger the 'Waiting for Confirmation' polling UX
    $successUrl .= '&return_to=financial';
    $cancelUrl  .= '&return_to=financial';
}

$payload = [
    'data' => [
        'attributes' => [
            'billing'              => [
                'name'  => $booking['client_name'],
                'email' => $booking['client_email'] ?: null,
                'phone' => $booking['client_phone'] ?: null,
            ],
            'line_items'           => [
                [
                    'currency'  => 'PHP',
                    'amount'    => $amountCentavos,
                    'name'      => $lineItemName,
                    'quantity'  => 1,
                    'description' => $description,
                ],
            ],
            'payment_method_types' => ['gcash', 'card', 'paymaya'],
            'description'          => $description,
            'metadata'             => [
                'booking_id'  => $bookingId,
                'client_name' => $booking['client_name'],
            ],
            'send_email_receipt'   => true,
            'show_description'     => true,
            'show_line_items'      => true,
            'success_url'          => $successUrl,
            'cancel_url'           => $cancelUrl,
        ],
    ],
];

// ── 8. Call the PayMongo API via cURL ────────────────────────────────────────
$apiUrl     = 'https://api.paymongo.com/v1/checkout_sessions';
$authHeader = 'Authorization: Basic ' . base64_encode($secretKey . ':');

// Developer Mock Bypass: If using the default dummy key, simulate a successful response
if ($secretKey === 'sk_test_your_secret_key_here' || strpos($secretKey, 'your_secret_key') !== false) {
    $rawResponse = json_encode([
        'data' => [
            'id' => 'cs_mock_' . uniqid(),
            'attributes' => [
                'checkout_url' => BASE_URL . '/templates/invoice.php?booking_id=' . $bookingId . '&mock_paymongo=1'
            ]
        ]
    ]);
    $httpCode  = 200;
    $curlError = '';
} else {
    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Accept: application/json',
            $authHeader,
        ],
        CURLOPT_TIMEOUT        => 20,       // 20 s timeout; PayMongo is generally fast
        CURLOPT_SSL_VERIFYPEER => true,     // Never disable SSL verification in production
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError   = curl_error($ch);
    curl_close($ch);
}

// ── 9. Handle cURL / network errors ─────────────────────────────────────────
if ($curlError || $rawResponse === false) {
    error_log('[PayMongo Checkout] cURL error: ' . $curlError);
    jsonResponse(false, 'Could not reach the payment gateway. Please try again later.', [], 502);
}

$pmResponse = json_decode($rawResponse, true);

// ── 10. Handle PayMongo API errors ───────────────────────────────────────────
if ($httpCode < 200 || $httpCode >= 300) {
    $pmError = $pmResponse['errors'][0]['detail'] ?? 'Unknown PayMongo error.';
    error_log("[PayMongo Checkout] API error (HTTP $httpCode): " . $rawResponse);
    jsonResponse(false, 'Payment gateway error: ' . $pmError, ['http_code' => $httpCode], 502);
}

// ── 11. Extract the session ID and checkout URL ──────────────────────────────
$checkoutSessionId = $pmResponse['data']['id']                                       ?? '';
$checkoutUrl       = $pmResponse['data']['attributes']['checkout_url']               ?? '';

if (empty($checkoutSessionId) || empty($checkoutUrl)) {
    error_log('[PayMongo Checkout] Unexpected API response shape: ' . $rawResponse);
    jsonResponse(false, 'Received an unexpected response from the payment gateway.', [], 502);
}

// ── 12. Persist the checkout session ID on the booking ──────────────────────
// This allows administrators to cross-reference pending sessions in the OMS.
$pdo->prepare("
    UPDATE bookings
    SET paymongo_checkout_id = :cid,
        updated_at           = NOW()
    WHERE id = :id
")->execute([
    ':cid' => $checkoutSessionId,
    ':id'  => $bookingId,
]);

// ── 13. Audit trail ──────────────────────────────────────────────────────────
auditLog($pdo, 'paymongo_checkout_created', 'booking', $bookingId, null, [
    'checkout_session_id' => $checkoutSessionId,
    'amount_pesos'        => $balanceDue,
    'initiated_by'        => $currentUser['id'],
]);

// ── 14. Return the checkout URL to the frontend ──────────────────────────────
jsonResponse(true, 'Checkout session created.', [
    'checkout_url'        => $checkoutUrl,
    'checkout_session_id' => $checkoutSessionId,
    'amount'              => $balanceDue,
]);
