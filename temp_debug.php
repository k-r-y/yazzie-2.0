<?php
require 'config/config.php';
$stmt = $pdo->query("SELECT b.id, b.client_id, b.event_date, b.booking_status, b.total_cost, b.amount_paid, (SELECT SUM(total_cost) FROM booking_breakages WHERE booking_id = b.id) as breakages, ((b.total_cost + COALESCE((SELECT SUM(total_cost) FROM booking_breakages WHERE booking_id = b.id), 0)) - COALESCE((SELECT SUM(amount) FROM payments WHERE booking_id = b.id), 0)) as calculated_outstanding FROM bookings b WHERE b.booking_status != 'cancelled' AND ((b.total_cost + COALESCE((SELECT SUM(total_cost) FROM booking_breakages WHERE booking_id = b.id), 0)) - COALESCE((SELECT SUM(amount) FROM payments WHERE booking_id = b.id), 0)) > 0 AND MONTH(b.event_date) = MONTH(CURDATE()) AND YEAR(b.event_date) = YEAR(CURDATE())");
header('Content-Type: application/json');
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
