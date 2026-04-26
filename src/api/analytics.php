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

$currentUser = requireApiRole(['admin', 'frontdesk']);

$type      = $_GET['type'] ?? 'kpis';
$timeframe = $_GET['timeframe'] ?? 'month'; // day, week, month, year

// ----------------------------------------------------------------
// REVENUE CHART — grouped by timeframe
// ----------------------------------------------------------------
if ($type === 'revenue_chart') {
    if ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'super_admin') {
        jsonResponse(false, 'Unauthorized access to financial charts.', [], 403);
    }
    $labels = [];
    $data   = [];
    $sql    = "";
    $params = [];

    switch ($timeframe) {
        case 'day':
            // Last 14 days
            $sql = "SELECT DATE(payment_date) as grp, SUM(amount) as total 
                    FROM payments 
                    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                    GROUP BY grp ORDER BY grp ASC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            for ($i = 13; $i >= 0; $i--) {
                $d = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('D, M j', strtotime($d));
                $data[]   = (float)($rows[$d] ?? 0);
            }
            break;

        case 'week':
            // Last 8 weeks
            $sql = "SELECT YEARWEEK(payment_date, 1) as grp, SUM(amount) as total 
                    FROM payments 
                    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
                    GROUP BY grp ORDER BY grp ASC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            for ($i = 7; $i >= 0; $i--) {
                $ts = strtotime("-$i weeks");
                $yw = date('oW', $ts); // ISO Year and Week
                $labels[] = "Week " . date('W, Y', $ts);
                $data[]   = (float)($rows[$yw] ?? 0);
            }
            break;

        case 'year':
            // Last 5 years
            $sql = "SELECT YEAR(payment_date) as grp, SUM(amount) as total 
                    FROM payments 
                    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 5 YEAR)
                    GROUP BY grp ORDER BY grp ASC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            for ($i = 4; $i >= 0; $i--) {
                $y = (int)date('Y', strtotime("-$i years"));
                $labels[] = (string)$y;
                $data[]   = (float)($rows[$y] ?? 0);
            }
            break;

        case 'month':
        default:
            // Last 12 months (Default)
            $sql = "SELECT DATE_FORMAT(payment_date, '%Y-%m') as grp, SUM(amount) as total 
                    FROM payments 
                    WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                    GROUP BY grp ORDER BY grp ASC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            for ($i = 11; $i >= 0; $i--) {
                $ts = strtotime("-$i months");
                $m = date('Y-m', $ts);
                $labels[] = date('M Y', $ts);
                $data[]   = (float)($rows[$m] ?? 0);
            }
            break;
    }

    jsonResponse(true, '', ['labels' => $labels, 'data' => $data]);
}

// ----------------------------------------------------------------
// MENU POPULARITY — top menus by booking count (respect timeframe?)
// Usually popularity is better as a "last X days" thing if timeframe is small.
// ----------------------------------------------------------------
if ($type === 'menu_popularity') {
    $interval = "INTERVAL 12 MONTH";
    if ($timeframe === 'day')   $interval = "INTERVAL 7 DAY";
    if ($timeframe === 'week')  $interval = "INTERVAL 4 WEEK";
    if ($timeframe === 'year')  $interval = "INTERVAL 5 YEAR";

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(pk.set_name, 'Other') AS label,
            COUNT(b.id) AS count
        FROM bookings b
        LEFT JOIN packages pk ON pk.id = b.package_id
        WHERE b.booking_status NOT IN ('cancelled')
        AND b.event_date >= DATE_SUB(CURDATE(), $interval)
        GROUP BY label
        ORDER BY count DESC
        LIMIT 8
    ");
    $stmt->execute();
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
    $interval = "INTERVAL 1 MONTH"; // Default to MTD
    $label = "This Month";
    
    if ($timeframe === 'day')   { $interval = "INTERVAL 0 DAY"; $label = "Today"; }
    if ($timeframe === 'week')  { $interval = "INTERVAL 1 WEEK"; $label = "This Week"; }
    if ($timeframe === 'year')  { $interval = "INTERVAL 1 YEAR"; $label = "This Year"; }

    // Logic: "This X" where X is day/week/month/year
    $dateFilter = "";
    if ($timeframe === 'day') {
        $dateFilter = "AND DATE(p.payment_date) = CURDATE()";
        $eventFilter = "AND DATE(event_date) = CURDATE()";
    } elseif ($timeframe === 'week') {
        $dateFilter = "AND YEARWEEK(p.payment_date, 1) = YEARWEEK(CURDATE(), 1)";
        $eventFilter = "AND YEARWEEK(event_date, 1) = YEARWEEK(CURDATE(), 1)";
    } elseif ($timeframe === 'year') {
        $dateFilter = "AND YEAR(p.payment_date) = YEAR(CURDATE())";
        $eventFilter = "AND YEAR(event_date) = YEAR(CURDATE())";
    } else {
        $dateFilter = "AND MONTH(p.payment_date) = MONTH(CURDATE()) AND YEAR(p.payment_date) = YEAR(CURDATE())";
        $eventFilter = "AND MONTH(event_date) = MONTH(CURDATE()) AND YEAR(event_date) = YEAR(CURDATE())";
    }

    // 1. Revenue in Period
    $revenue = $pdo->query("
        SELECT COALESCE(SUM(p.amount), 0) 
        FROM payments p
        JOIN bookings b ON b.id = p.booking_id
        WHERE b.booking_status != 'cancelled'
        $dateFilter
    ")->fetchColumn();

    // 2. Events in Period
    $events = $pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status NOT IN ('cancelled')
        $eventFilter
    ")->fetchColumn();

    // 3. New Clients in Period
    $clientFilter = str_replace('p.payment_date', 'created_at', $dateFilter);
    $clients = $pdo->query("SELECT COUNT(*) FROM clients WHERE 1=1 " . str_replace('p.payment_date', 'created_at', $dateFilter))->fetchColumn();

    // 4. Outstanding Balance for events in period
    $outstanding = $pdo->query("
        SELECT COALESCE(SUM( (b.total_cost + COALESCE(br_sum.total, 0)) - COALESCE(p_sum.paid, 0) ), 0)
        FROM bookings b
        LEFT JOIN (
            SELECT booking_id, SUM(total_cost) AS total
            FROM booking_breakages GROUP BY booking_id
        ) br_sum ON br_sum.booking_id = b.id
        LEFT JOIN (
            SELECT booking_id, SUM(amount) AS paid
            FROM payments GROUP BY booking_id
        ) p_sum ON p_sum.booking_id = b.id
        WHERE b.booking_status NOT IN ('cancelled', 'completed')
          AND ((b.total_cost + COALESCE(br_sum.total, 0)) - COALESCE(p_sum.paid, 0)) > 0
          $eventFilter
    ")->fetchColumn();

    // 5. Month-To-Date (MTD) Revenue - Independent of timeframe
    $revenue_mtd = $pdo->query("
        SELECT COALESCE(SUM(p.amount), 0) 
        FROM payments p
        JOIN bookings b ON b.id = p.booking_id
        WHERE b.booking_status != 'cancelled'
        AND MONTH(p.payment_date) = MONTH(CURDATE()) 
        AND YEAR(p.payment_date) = YEAR(CURDATE())
    ")->fetchColumn();

    $res = [
        'active_bookings'   => (int)$events,
        'total_clients'     => (int)$clients,
        'period_label'      => $label,
    ];

    if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin') {
        $res['total_revenue'] = (float)$revenue;
        $res['revenue_mtd']   = (float)$revenue_mtd;
        $res['outstanding']   = (float)$outstanding;
    }

    jsonResponse(true, '', $res);
}

jsonResponse(false, 'Unknown analytics type.', [], 400);
