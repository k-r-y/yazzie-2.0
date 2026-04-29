<?php
require_once __DIR__ . '/../config/config.php';

$bookingId = 83; // Example booking

// 0. Force paid status for test
$pdo->prepare("UPDATE bookings SET total_cost = 1000, amount_paid = 1000, payment_status = 'paid', breakage_total = 0 WHERE id = :id")->execute([':id' => $bookingId]);

// 1. Check current status
$stmt = $pdo->prepare("SELECT id, total_cost, amount_paid, payment_status, breakage_total FROM bookings WHERE id = :id");
$stmt->execute([':id' => $bookingId]);
$b = $stmt->fetch();
echo "BEFORE BREAKAGE:\n";
print_r($b);

// 2. Simulate breakage log (charged to client)
$totalCost = 500.00; // Breakage cost
$pdo->beginTransaction();
try {
    $pdo->prepare("UPDATE bookings SET breakage_total = breakage_total + :total, total_cost = total_cost + :total_adj WHERE id = :bid")
        ->execute([':total' => $totalCost, ':total_adj' => $totalCost, ':bid' => $bookingId]);
    $pdo->commit();
    echo "\nBREAKAGE LOGGED (+$totalCost)\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "ERROR: " . $e->getMessage();
}

// 3. Check status again
$stmt->execute([':id' => $bookingId]);
$b = $stmt->fetch();
echo "\nAFTER BREAKAGE:\n";
print_r($b);

// 4. Calculate what payment status SHOULD be
$newStatus = ($b['amount_paid'] >= $b['total_cost'] - 0.01) ? 'paid' : 'partial';
echo "\nEXPECTED PAYMENT STATUS: $newStatus\n";
?>
