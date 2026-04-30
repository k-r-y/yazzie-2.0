<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/pdf_generator.php';
require_once __DIR__ . '/../../includes/mailer.php';

requireRole(['admin', 'frontdesk']);

header('Content-Type: application/json');

$d = json_decode(file_get_contents('php://input'), true) ?? [];
$bookingId = (int)($d['booking_id'] ?? 0);

if (!$bookingId) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
    exit;
}

try {
    // 1. Fetch booking and client details
    $stmt = $pdo->prepare("
        SELECT b.id, b.total_cost, b.amount_paid, b.event_date,
               c.name AS client_name, c.email AS client_email
        FROM bookings b
        JOIN clients c ON c.id = b.client_id
        WHERE b.id = :id
    ");
    $stmt->execute([':id' => $bookingId]);
    $b = $stmt->fetch();

    if (!$b) {
        throw new Exception('Booking not found.');
    }

    if (empty($b['client_email'])) {
        throw new Exception('Client does not have an email address set.');
    }

    // 2. Generate PDF Invoice
    $pdfContent = generateInvoicePDF($pdo, $bookingId);
    $fileName = 'Invoice_INV-' . str_pad($bookingId, 5, '0', STR_PAD_LEFT) . '.pdf';

    // 3. Prepare Email Body
    $subject = "Invoice for Your Catering Event - " . date('M d, Y', strtotime($b['event_date']));
    $htmlBody = renderEmailTemplate(
        "Your Invoice is Ready",
        "📄",
        "<p>Hello <strong>" . htmlspecialchars($b['client_name']) . "</strong>,</p>
         <p>Please find attached the official invoice for your upcoming catering event scheduled on " . date('F j, Y', strtotime($b['event_date'])) . ".</p>
         <p>If you have any questions regarding the charges or payment instructions, please feel free to contact us.</p>
         <p>Thank you for choosing Yazzies Catering!</p>",
        '#25A244'
    );

    // 4. Send Email
    $sent = sendMailImmediate(
        $b['client_email'],
        $b['client_name'],
        $subject,
        $htmlBody,
        $pdfContent,
        $fileName
    );

    if ($sent) {
        echo json_encode(['success' => true, 'message' => 'Invoice sent successfully to ' . $b['client_email']]);
    } else {
        throw new Exception('Failed to send email. Please check your SMTP settings.');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
