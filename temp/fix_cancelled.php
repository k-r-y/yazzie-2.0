<?php
require_once __DIR__ . '/../config/config.php';

// Find bookings that are stuck in 'pending_cancellation' but their cancellation record is 'waived'
$stmt = $pdo->query("
    SELECT b.id, c.id as cancel_id 
    FROM bookings b
    JOIN booking_cancellations c ON c.booking_id = b.id
    WHERE b.booking_status = 'pending_cancellation' 
    AND c.refund_status = 'waived'
");
$stuck = $stmt->fetchAll();
$count = 0;

foreach ($stuck as $row) {
    // Recalculate amount_paid
    $paidRow = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = :bid");
    $paidRow->execute([':bid' => $row['id']]);
    $amountPaid = (float)$paidRow->fetchColumn();

    $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled', amount_paid = :amt WHERE id = :id")
        ->execute([':amt' => $amountPaid, ':id' => $row['id']]);
    $count++;
}

echo "Successfully fixed $count stuck cancelled bookings.\n";
