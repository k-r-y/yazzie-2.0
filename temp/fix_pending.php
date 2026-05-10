<?php
require_once __DIR__ . '/../config/config.php';

$stmt = $pdo->query("SELECT id, total_cost, amount_paid, event_date FROM bookings WHERE booking_status = 'pending'");
$bookings = $stmt->fetchAll();
$count = 0;

foreach ($bookings as $b) {
    $eventDateObj = new DateTime($b['event_date']);
    $now = new DateTime();
    $interval = $now->diff($eventDateObj);
    $diffHours = ($interval->days * 24) + $interval->h;
    
    $dpPercent = (!$interval->invert && $diffHours < RUSH_THRESHOLD_HOURS) ? RUSH_DP_PERCENT : MIN_DP_PERCENT;
    $minDPThresh = round($b['total_cost'] * $dpPercent, 2);

    if ($b['amount_paid'] >= $minDPThresh - 0.01) {
        $pdo->prepare("UPDATE bookings SET booking_status = 'confirmed' WHERE id = :id")->execute([':id' => $b['id']]);
        $count++;
    }
}

echo "Successfully updated $count bookings from pending to confirmed.\n";
