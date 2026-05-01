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
    $eventFilter = "";
    $clientFilter = "";
    
    // Security: Use parameter binding instead of string interpolation
    if ($timeframe === 'day') {
        $dateFilter = "AND DATE(p.payment_date) = :date_val";
        $eventFilter = "AND DATE(event_date) = :date_val";
        $clientFilter = "AND DATE(created_at) = :date_val";
        $paramVal = date('Y-m-d');
    } elseif ($timeframe === 'week') {
        $dateFilter = "AND YEARWEEK(p.payment_date, 1) = YEARWEEK(:date_val, 1)";
        $eventFilter = "AND YEARWEEK(event_date, 1) = YEARWEEK(:date_val, 1)";
        $clientFilter = "AND YEARWEEK(created_at, 1) = YEARWEEK(:date_val, 1)";
        $paramVal = date('Y-m-d');
    } elseif ($timeframe === 'year') {
        $dateFilter = "AND YEAR(p.payment_date) = :year_val";
        $eventFilter = "AND YEAR(event_date) = :year_val";
        $clientFilter = "AND YEAR(created_at) = :year_val";
        $paramVal = date('Y');
    } else {
        $dateFilter = "AND MONTH(p.payment_date) = :month_val AND YEAR(p.payment_date) = :year_val";
        $eventFilter = "AND MONTH(event_date) = :month_val AND YEAR(event_date) = :year_val";
        $clientFilter = "AND MONTH(created_at) = :month_val AND YEAR(created_at) = :year_val";
        $paramMonth = date('m');
        $paramYear = date('Y');
    }

    $bindParams = function($stmt) use ($timeframe, &$paramVal, &$paramMonth, &$paramYear) {
        if ($timeframe === 'year') {
            $stmt->bindValue(':year_val', $paramYear ?? $paramVal);
        } elseif ($timeframe === 'month' || !in_array($timeframe, ['day', 'week', 'year'])) {
            $stmt->bindValue(':month_val', $paramMonth);
            $stmt->bindValue(':year_val', $paramYear);
        } else {
            $stmt->bindValue(':date_val', $paramVal);
        }
    };

    // 1. Revenue in Period
    $stmt1 = $pdo->prepare("
        SELECT COALESCE(SUM(p.amount), 0) 
        FROM payments p
        JOIN bookings b ON b.id = p.booking_id
        WHERE 1=1 $dateFilter
    ");
    $bindParams($stmt1);
    $stmt1->execute();
    $revenue = $stmt1->fetchColumn();

    // 2. Events in Period
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status NOT IN ('cancelled') $eventFilter
    ");
    $bindParams($stmt2);
    $stmt2->execute();
    $events = $stmt2->fetchColumn();

    // 3. New Clients in Period
    $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE 1=1 $clientFilter");
    $bindParams($stmt3);
    $stmt3->execute();
    $clients = $stmt3->fetchColumn();

    // 4. Outstanding Balance for events in period
    $sqlOutstanding = "
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
        WHERE b.booking_status != 'cancelled'
          AND ((b.total_cost + COALESCE(br_sum.total, 0)) - COALESCE(p_sum.paid, 0)) > 0
          $eventFilter
    ";
    $stmt4 = $pdo->prepare($sqlOutstanding);
    $bindParams($stmt4);
    $stmt4->execute();
    $outstanding = $stmt4->fetchColumn();

    $stmt5 = $pdo->prepare("
        SELECT COUNT(*)
        FROM bookings b
        LEFT JOIN (
            SELECT booking_id, SUM(total_cost) AS total
            FROM booking_breakages GROUP BY booking_id
        ) br_sum ON br_sum.booking_id = b.id
        LEFT JOIN (
            SELECT booking_id, SUM(amount) AS paid
            FROM payments GROUP BY booking_id
        ) p_sum ON p_sum.booking_id = b.id
        WHERE b.booking_status != 'cancelled'
          AND ((b.total_cost + COALESCE(br_sum.total, 0)) - COALESCE(p_sum.paid, 0)) > 0
          $eventFilter
    ");
    $bindParams($stmt5);
    $stmt5->execute();
    $unpaidCount = $stmt5->fetchColumn();

    // 5. Month-To-Date (MTD) Revenue
    $stmt6 = $pdo->prepare("
        SELECT COALESCE(SUM(p.amount), 0) 
        FROM payments p
        JOIN bookings b ON b.id = p.booking_id
        WHERE MONTH(p.payment_date) = :mtd_m 
          AND YEAR(p.payment_date) = :mtd_y
    ");
    $stmt6->bindValue(':mtd_m', date('m'));
    $stmt6->bindValue(':mtd_y', date('Y'));
    $stmt6->execute();
    $revenue_mtd = $stmt6->fetchColumn();

    $res = [
        'active_bookings'   => (int)$events,
        'total_clients'     => (int)$clients,
        'period_label'      => $label,
    ];

    if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'super_admin') {
        $res['total_revenue'] = (float)$revenue;
        $res['revenue_mtd']   = (float)$revenue_mtd;
        $res['outstanding']   = (float)$outstanding;
        $res['unpaid_count']  = (int)$unpaidCount;
    }

    jsonResponse(true, '', $res);
}

jsonResponse(false, 'Unknown analytics type.', [], 400);
