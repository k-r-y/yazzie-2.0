<?php
/**
 * Notifications Helper — Passive Cron & Logic
 */

/**
 * Checks for upcoming bookings (within 72 hours) that have an outstanding balance.
 * Triggers a system notification for Admin and Frontdesk roles.
 * Deduplicates to ensure only one alert per booking per day is created.
 *
 * @param PDO $pdo
 * @return int Number of alerts created
 */
function checkUpcomingUnpaidBookings(PDO $pdo): int
{
    // 1. Query bookings meeting the criteria:
    // - Event date is within the next 3 days (72 hours)
    // - Not cancelled
    // - Amount paid is less than total cost
    $stmt = $pdo->prepare("
        SELECT id, client_id, event_date, total_cost, amount_paid, 
               (total_cost - amount_paid) as balance
        FROM bookings
        WHERE event_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
          AND event_date >= CURDATE()
          AND booking_status NOT IN ('cancelled', 'completed')
          AND amount_paid < total_cost
    ");
    $stmt->execute();
    $upcomingUnpaid = $stmt->fetchAll();

    if (empty($upcomingUnpaid)) {
        return 0;
    }

    // 2. Fetch target recipients (Admins and Frontdesk)
    $uStmt = $pdo->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin', 'frontdesk') AND is_active = 1");
    $recipients = array_column($uStmt->fetchAll(), 'id');

    if (empty($recipients)) {
        return 0;
    }

    $alertsCreated = 0;

    foreach ($upcomingUnpaid as $booking) {
        $bid = (int)$booking['id'];
        $eventDate = date('M d, Y', strtotime($booking['event_date']));
        $balanceFormatted = number_format($booking['balance'], 2);

        // 3. Deduplication: Check if an alert for this booking was already sent TODAY
        // We check if a notification of type 'payment_alert' exists for this booking_id created today.
        $dupCheck = $pdo->prepare("
            SELECT id FROM notifications 
            WHERE booking_id = :bid 
              AND type = 'payment_alert' 
              AND DATE(created_at) = CURDATE() 
            LIMIT 1
        ");
        $dupCheck->execute([':bid' => $bid]);
        
        if ($dupCheck->fetch()) {
            continue; // Already notified today
        }

        // 4. Create Notifications for each recipient
        $ins = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, body, booking_id, link_url)
            VALUES (:uid, 'payment_alert', :title, :body, :bid, :link)
        ");

        $title = "⚠️ URGENT: Payment Due";
        $body  = "Booking #{$bid} is in 3 days ($eventDate) and is missing final payment (Balance: ₱$balanceFormatted).";
        $link  = BASE_URL . "/views/admin/bookings.php?highlight=$bid";

        foreach ($recipients as $uid) {
            $ins->execute([
                ':uid'   => $uid,
                ':title' => $title,
                ':body'  => $body,
                ':bid'   => $bid,
                ':link'  => $link
            ]);
            $alertsCreated++;
        }
    }

    return $alertsCreated;
}
