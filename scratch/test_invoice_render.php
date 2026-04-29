<?php
// Mocking the environment
$_GET['booking_id'] = 63;
$_GET['token'] = '0127e2755d40910efe4693ef64d631ec'; // from the screenshot

// Start capturing output
ob_start();
try {
    include __DIR__ . '/../templates/invoice.php';
} catch (Throwable $e) {
    echo "\n\n[FATAL ERROR] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
}
$output = ob_get_clean();

if (empty($output)) {
    echo "OUTPUT IS EMPTY\n";
} else {
    echo "OUTPUT SIZE: " . strlen($output) . " bytes\n";
    echo "First 500 chars:\n" . substr($output, 0, 500) . "\n";
    if (strpos($output, '[FATAL ERROR]') !== false) {
        echo "FOUND ERROR IN OUTPUT\n";
    }
}
