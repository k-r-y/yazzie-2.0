<?php
/**
 * Analytics API — Admin dashboard charts & KPIs
 * GET ?type=revenue_chart   — monthly revenue (last 12 months)
 * GET ?type=menu_popularity — booking count per menu
 * GET ?type=kpis            — KPI summary cards
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

requireApiRole('admin');

$type = $_GET['type'] ?? 'kpis';

// ----------------------------------------------------------------
// REVENUE CHART — last 12 months of payment amounts
// ----------------------------------------------------------------
if ($type === 'revenue_chart') {
    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(payment_date, '%b %Y') AS label,
            YEAR(payment_date)  AS yr,
            MONTH(payment_date) AS mo,
            SUM(amount)         AS total
        FROM payments
        WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY yr, mo
        ORDER BY yr ASC, mo ASC
    ");
    $rows = $stmt->fetchAll();

    // Fill in missing months with 0
    $labels  = [];
    $data    = [];
    $months  = 12;
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts      = strtotime("-$i months");
        $label   = date('M Y', $ts);
        $yr      = (int)date('Y', $ts);
        $mo      = (int)date('n', $ts);
        $labels[] = $label;
        $found   = false;
        foreach ($rows as $row) {
            if ((int)$row['yr'] === $yr && (int)$row['mo'] === $mo) {
                $data[] = (float)$row['total'];
                $found  = true;
                break;
            }
        }
        if (!$found) $data[] = 0;
    }

    jsonResponse(true, '', ['labels' => $labels, 'data' => $data]);
}

// ----------------------------------------------------------------
// MENU POPULARITY — top menus by booking count
// ----------------------------------------------------------------
if ($type === 'menu_popularity') {
    $stmt = $pdo->query("
        SELECT
            COALESCE(pk.set_name, 'Other') AS label,
            COUNT(b.id) AS count
        FROM bookings b
        LEFT JOIN packages pk ON pk.id = b.package_id
        WHERE b.booking_status NOT IN ('cancelled')
        GROUP BY label
        ORDER BY count DESC
        LIMIT 8
    ");
    $rows = $stmt->fetchAll();
    jsonResponse(true, '', [
        'labels' => array_column($rows, 'label'),
        'data'   => array_map('intval', array_column($rows, 'count')),
    ]);
}

// ----------------------------------------------------------------
// KPIs — summary stats for dashboard cards
// ----------------------------------------------------------------
if ($type === 'kpis') {
    // Total revenue (all time)
    $totalRevenue = $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments")->fetchColumn();

    // Revenue this month
    $revenueThisMonth = $pdo->query("
        SELECT COALESCE(SUM(amount), 0) FROM payments
        WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())
    ")->fetchColumn();

    // Active bookings (pending + confirmed, future dates)
    $activeBookings = $pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status IN ('pending','confirmed') AND event_date >= CURDATE()
    ")->fetchColumn();

    // Pending bookings (awaiting downpayment)
    $pendingBookings = $pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status = 'pending' AND event_date >= CURDATE()
    ")->fetchColumn();

    // Events this month
    $eventsThisMonth = $pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())
        AND booking_status NOT IN ('cancelled')
    ")->fetchColumn();

    // Total clients
    $totalClients = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();

    // Unpaid bookings count
    $unpaidCount = $pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE payment_status IN ('unpaid','partial')
        AND booking_status IN ('pending','confirmed')
    ")->fetchColumn();

    // Outstanding balance — include breakages + event cost - live payments
    $outstanding = $pdo->query("
        SELECT COALESCE(SUM( (b.total_cost + COALESCE(br_sum.total, 0)) - COALESCE(p_sum.paid, 0) ), 0)
        FROM bookings b
        LEFT JOIN (
            SELECT booking_id, SUM(total_cost) AS total
            FROM booking_breakages
            GROUP BY booking_id
        ) br_sum ON br_sum.booking_id = b.id
        LEFT JOIN (
            SELECT booking_id, SUM(amount) AS paid
            FROM payments
            GROUP BY booking_id
        ) p_sum ON p_sum.booking_id = b.id
        WHERE b.booking_status NOT IN ('cancelled', 'completed')
          AND ((b.total_cost + COALESCE(br_sum.total, 0)) - COALESCE(p_sum.paid, 0)) > 0
    ")->fetchColumn();

    jsonResponse(true, '', [
        'total_revenue'     => (float)$totalRevenue,
        'revenue_mtd'       => (float)$revenueThisMonth,
        'active_bookings'   => (int)$activeBookings,
        'pending_bookings'  => (int)$pendingBookings,
        'events_this_month' => (int)$eventsThisMonth,
        'total_clients'     => (int)$totalClients,
        'unpaid_count'      => (int)$unpaidCount,
        'outstanding'       => (float)$outstanding,
    ]);
}

jsonResponse(false, 'Unknown analytics type.', [], 400);
