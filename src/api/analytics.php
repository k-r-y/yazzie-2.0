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

// ----------------------------------------------------------------
// PROFIT GUARD — Net profit per booking
// ----------------------------------------------------------------
if ($type === 'profit_guard') {
    $period = $_GET['period'] ?? 'all'; // 'all', 'month', 'quarter', 'year'

    $dateFilter = '';
    if ($period === 'month') {
        $dateFilter = "AND MONTH(b.event_date) = MONTH(CURDATE()) AND YEAR(b.event_date) = YEAR(CURDATE())";
    } elseif ($period === 'quarter') {
        $dateFilter = "AND QUARTER(b.event_date) = QUARTER(CURDATE()) AND YEAR(b.event_date) = YEAR(CURDATE())";
    } elseif ($period === 'year') {
        $dateFilter = "AND YEAR(b.event_date) = YEAR(CURDATE())";
    }

    $staffRate = function_exists('appSetting') ? (float)appSetting('staff_hourly_rate', '75') : 75;
    $eventHours = function_exists('appSettingInt') ? appSettingInt('event_duration_hours', 4) : 4;

    // Fetch bookings with revenue, breakage, staff count
    $stmt = $pdo->query("
        SELECT b.id, b.event_date, b.event_type, b.pax_count,
               b.total_cost AS contract_price,
               b.transport_fee,
               b.overtime_total,
               b.booking_status,
               c.name AS client_name,
               pk.set_name AS menu_name,
               COALESCE(br.breakage_total, 0) AS breakage_total,
               COALESCE(p.paid_total, 0) AS amount_paid,
               COALESCE(jo.staff_count, 0) AS staff_count
        FROM bookings b
        JOIN clients c ON c.id = b.client_id
        LEFT JOIN packages pk ON pk.id = b.package_id
        LEFT JOIN (
            SELECT booking_id, SUM(total_cost) AS breakage_total FROM booking_breakages GROUP BY booking_id
        ) br ON br.booking_id = b.id
        LEFT JOIN (
            SELECT booking_id, SUM(amount) AS paid_total FROM payments GROUP BY booking_id
        ) p ON p.booking_id = b.id
        LEFT JOIN (
            SELECT booking_id, COUNT(*) AS staff_count FROM job_orders WHERE status = 'accepted' GROUP BY booking_id
        ) jo ON jo.booking_id = b.id
        WHERE b.booking_status IN ('confirmed', 'completed')
        $dateFilter
        ORDER BY b.event_date DESC
    ");
    $bookings = $stmt->fetchAll();

    // Compute COGS for each booking (based on recipe ingredients)
    $results = [];
    $totalRevenue = 0;
    $totalCogs = 0;
    $totalPayroll = 0;
    $totalTransport = 0;
    $totalProfit = 0;

    foreach ($bookings as $bk) {
        $bid = (int)$bk['id'];
        $pax = (int)$bk['pax_count'];

        // Get dishes for this booking
        $dishStmt = $pdo->prepare("
            SELECT d.id, d.base_pax
            FROM booking_dishes bd
            JOIN dishes d ON d.id = bd.dish_id
            WHERE bd.booking_id = :bid
        ");
        $dishStmt->execute([':bid' => $bid]);
        $dishes = $dishStmt->fetchAll();

        $cogs = 0;
        foreach ($dishes as $dish) {
            $basePax = (int)$dish['base_pax'] ?: 1;
            $multiplier = $pax / $basePax;

            $ingStmt = $pdo->prepare("
                SELECT base_quantity, unit_price
                FROM recipe_ingredients
                WHERE dish_id = :did AND unit_price IS NOT NULL AND unit_price > 0
            ");
            $ingStmt->execute([':did' => $dish['id']]);
            $ingredients = $ingStmt->fetchAll();

            foreach ($ingredients as $ing) {
                $cogs += round($ing['base_quantity'] * $multiplier * $ing['unit_price'], 2);
            }
        }

        $payroll   = $bk['staff_count'] * $staffRate * $eventHours;
        $revenue   = (float)$bk['contract_price'] + (float)$bk['breakage_total'];
        $transport = (float)$bk['transport_fee'];
        $overtime  = (float)$bk['overtime_total'];
        $profit    = $revenue - $cogs - $payroll - $transport;
        $margin    = $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0;

        $totalRevenue   += $revenue;
        $totalCogs      += $cogs;
        $totalPayroll   += $payroll;
        $totalTransport += $transport;
        $totalProfit    += $profit;

        $results[] = [
            'id'             => $bid,
            'event_date'     => $bk['event_date'],
            'event_type'     => $bk['event_type'],
            'client_name'    => $bk['client_name'],
            'menu_name'      => $bk['menu_name'],
            'pax_count'      => $pax,
            'status'         => $bk['booking_status'],
            'revenue'        => round($revenue, 2),
            'cogs'           => round($cogs, 2),
            'payroll'        => round($payroll, 2),
            'transport'      => round($transport, 2),
            'overtime'       => round($overtime, 2),
            'profit'         => round($profit, 2),
            'margin'         => $margin,
            'staff_count'    => (int)$bk['staff_count'],
            'amount_paid'    => (float)$bk['amount_paid'],
            'cogs_has_data'  => ($cogs > 0),
        ];
    }

    $avgMargin = $totalRevenue > 0 ? round(($totalProfit / $totalRevenue) * 100, 1) : 0;

    jsonResponse(true, '', [
        'bookings'        => $results,
        'summary'         => [
            'total_revenue'   => round($totalRevenue, 2),
            'total_cogs'      => round($totalCogs, 2),
            'total_payroll'   => round($totalPayroll, 2),
            'total_transport' => round($totalTransport, 2),
            'total_profit'    => round($totalProfit, 2),
            'avg_margin'      => $avgMargin,
            'event_count'     => count($results),
        ],
    ]);
}

jsonResponse(false, 'Unknown analytics type.', [], 400);
