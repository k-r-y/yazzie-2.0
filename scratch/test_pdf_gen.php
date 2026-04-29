<?php
require_once __DIR__ . '/../includes/pdf_generator.php';
require_once __DIR__ . '/../config/config.php';

$bookingId = 83; // Use a known valid booking ID
$pdf = generateInvoicePDF($bookingId);

if (empty($pdf)) {
    echo "ERROR: PDF generation failed (returned empty string).\n";
    exit(1);
}

$outputFile = __DIR__ . '/test_output.pdf';
file_put_contents($outputFile, $pdf);

echo "SUCCESS: PDF generated and saved to $outputFile (" . strlen($pdf) . " bytes).\n";
?>
