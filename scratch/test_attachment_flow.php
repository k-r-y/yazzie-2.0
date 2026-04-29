<?php
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../config/config.php';

// Mock booking data
$booking = [
    'id' => 83,
    'client_name' => 'KRY Test',
    'client_email' => 'kry@example.com',
    'event_date' => '2026-05-10',
    'total_cost' => 10000,
    'amount_paid' => 5000,
    'menu_name' => 'Test Menu'
];

echo "Generating PDF for Booking #83...\n";
$pdf = (string)generateInvoicePDF((int)$booking['id']);

if (empty($pdf)) {
    echo "ERROR: PDF generation failed again!\n";
    exit(1);
}

echo "PDF generated successfully (" . strlen($pdf) . " bytes).\n";
echo "Simulating email send (checking for any errors in sendMailImmediate)...\n";

// We won't actually send a real email if SMTP is not set, but we want to see if the attachment logic fails
$res = sendMailImmediate($booking['client_email'], $booking['client_name'], "Test PDF Attachment", "Test Body", $pdf, 'Invoice.pdf');

if ($res) {
    echo "SUCCESS: Email sent (or queued) successfully with attachment.\n";
} else {
    echo "NOTICE: Email sending failed (this might be expected if SMTP is not configured), but check logs for 'Path cannot be empty'.\n";
}
?>
