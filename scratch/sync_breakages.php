<?php
require_once __DIR__ . '/../config/config.php';

echo "STARTING BREAKAGE SYNC...\n";

// 1. Get all bookings with breakages
$stmt = $pdo->query("
    SELECT booking_id, SUM(total_cost) as total_breakage 
    FROM booking_breakages 
    WHERE charge_to = 'client' 
    GROUP BY booking_id
");
$breakages = $stmt->fetchAll();

foreach ($breakages as $b) {
    $bid = $b['booking_id'];
    $totalB = (float)$b['total_breakage'];

    // Get current booking breakage_total
    $bStmt = $pdo->prepare("SELECT total_cost, breakage_total, amount_paid FROM bookings WHERE id = :id");
    $bStmt->execute([':id' => $bid]);
    $booking = $bStmt->fetch();

    if (!$booking) continue;

    $currentB = (float)$booking['breakage_total'];
    $diff = $totalB - $currentB;

    if (abs($diff) > 0.01) {
        echo "Updating Booking #$bid: breakage_total $currentB -> $totalB (Adj: $diff)\n";
        
        // Update columns and re-evaluate status
        $newTotalCost = (float)$booking['total_cost'] + $diff;
        $newStatus = ($booking['amount_paid'] >= $newTotalCost - 0.01) ? 'paid' : 'partial';

        $pdo->prepare("
            UPDATE bookings 
            SET breakage_total = :bt, 
                total_cost = :tc,
                payment_status = :ps
            WHERE id = :id
        ")->execute([
            ':bt' => $totalB,
            ':tc' => $newTotalCost,
            ':ps' => $newStatus,
            ':id' => $bid
        ]);
    } else {
        echo "Booking #$bid already synced.\n";
    }
}

echo "SYNC COMPLETE.\n";
?>
