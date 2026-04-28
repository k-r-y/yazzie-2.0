<?php
/**
 * Bookings API
 * GET    ?id=X            — single booking (with selected dishes)
 * GET    (list)           — bookings list with filters
 * GET    ?count_active=1  — public count for login page
 * POST                    — create booking (package-based)
 * PUT                     — update booking status / notes
 * DELETE                  — delete booking (admin)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

function readJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireFields(array $data, array $fields): void {
    foreach ($fields as $f) {
        if (!array_key_exists($f, $data) || $data[$f] === null || $data[$f] === '' || $data[$f] === []) {
            jsonResponse(false, "Field '$f' is required.", [], 422);
        }
    }
}

function validateYmdDate(string $date, string $fieldName = 'date'): string {
    $date = trim($date);
    $dt = \DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date) {
        jsonResponse(false, "Invalid $fieldName. Use YYYY-MM-DD format.", [], 422);
    }
    return $date;
}

function validateEventTime(?string $time): ?string {
    if ($time === null || trim($time) === '') return null;
    $time = trim($time);
    // Accept HH:MM or HH:MM:SS
    if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        jsonResponse(false, 'Invalid event time format. Use HH:MM.', [], 422);
    }
    $h = (int) explode(':', $time)[0];

    // Use settings constants
    if ($time < OPERATING_HOURS_START || $time >= OPERATING_HOURS_END) {
        jsonResponse(false, "Event start time must be between " . OPERATING_HOURS_START . " and " . OPERATING_HOURS_END . ".", [], 422);
    }
    return $time;
}

function normalizePaymentMethod(?string $method): string {
    $method = strtolower(trim((string)$method));
    return in_array($method, ['cash','gcash','maya','bank_transfer'], true) ? $method : 'cash';
}

/**
 * Package-less pricing + dish limits derived ONLY from pax_count.
 *
 * Pricing model inferred from existing booking data:
 * base_price = 5000 + (base_pax * 100)
 * with base_pax snapped to 50-pax tiers.
 *
 * Examples:
 *  - 50 pax  => base_pax=50  base_price=10000
 *  - 150 pax => base_pax=150 base_price=20000
 *  - 200 pax => base_pax=200 base_price=25000
 *  - 300 pax => base_pax=300 base_price=35000
 */
function computePaxPricing($pdo, int $paxCount, $packageId = null): array {
    $tierStep = TIER_SNAP_STEP;
    $basePax  = (int)(ceil($paxCount / $tierStep) * $tierStep);
    if ($basePax < MIN_PAX) $basePax = MIN_PAX;
    
    $maxPaxSetting = function_exists('appSettingInt') ? appSettingInt('max_pax', MAX_PAX) : MAX_PAX;
    if ($basePax > $maxPaxSetting) $basePax = $maxPaxSetting;

    $basePrice = round(TIER_BASE_PRICE + ($basePax * TIER_PAX_RATE), 2);
    $pkgName = "Pax Tier {$basePax}";
    $maxMain = DEFAULT_MAX_MAIN;
    $maxDesserts = DEFAULT_MAX_DESSERT;
    $maxRice = DEFAULT_MAX_ADDITIONAL;

    // Use Package from DB if provided
    if ($pdo && $packageId > 0) {
        $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
        $stmt->execute([$packageId]);
        $pkg = $stmt->fetch();
        if ($pkg) {
            $basePax = (int)$pkg['pax_count'];
            $basePrice = (float)$pkg['price'];
            $pkgName = $pkg['set_name'] . ' (' . $basePax . ' Pax)';
            $maxMain = (int)$pkg['max_main_dishes'];
            $maxDesserts = (int)$pkg['max_desserts'];
            $maxRice = (int)$pkg['max_additional_dishes'];
        }
    }

    $ratePerPax = $basePax > 0 ? ($basePrice / $basePax) : 0.0;
    $extraPax   = max(0, $paxCount - $basePax);
    $extraCost  = round($extraPax * $ratePerPax, 2);
    $totalCost  = round($basePrice + $extraCost, 2);

    return [
        'base_pax'       => $basePax,
        'base_price'     => $basePrice,
        'rate_per_pax'   => $ratePerPax,
        'extra_pax'      => $extraPax,
        'extra_cost'     => $extraCost,
        'total_cost'     => $totalCost,
        'max_main'       => $maxMain,
        'max_desserts'   => $maxDesserts,
        'pricing_label'  => $pkgName,
    ];
}

function validateSelectedDishes(PDO $pdo, array $selectedDishIds): array {
    $selectedDishIds = array_values(array_filter(array_map('intval', $selectedDishIds), fn($v) => $v > 0));
    if (empty($selectedDishIds)) return [];

    $placeholders = implode(',', array_fill(0, count($selectedDishIds), '?'));
    $dishStmt = $pdo->prepare("SELECT id, category, custom_fee FROM dishes WHERE id IN ($placeholders) AND is_active = 1");
    $dishStmt->execute($selectedDishIds);
    $dishes = $dishStmt->fetchAll(PDO::FETCH_ASSOC);

    // Reorder fetched dishes to match the original $selectedDishIds order
    $ordered = [];
    foreach ($selectedDishIds as $id) {
        foreach ($dishes as $d) {
            if ((int)$d['id'] === $id) {
                $ordered[] = $d;
                break;
            }
        }
    }
    return $ordered;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

/**
 * Auto-complete past confirmed bookings.
 * Transitions confirmed → completed when event_date (+ event_time) is in the past.
 */
function autoCompleteExpiredBookings(PDO $pdo): int {
    // Past days: any confirmed booking with event_date before today
    $stmt = $pdo->prepare("
        UPDATE bookings
        SET booking_status = 'completed'
        WHERE booking_status = 'confirmed'
          AND (
              event_date < CURDATE()
              OR (event_date = CURDATE() AND event_time IS NOT NULL AND event_time < CURTIME())
          )
    ");
    $stmt->execute();
    $affected = $stmt->rowCount();

    // 2. Auto-archive fully paid completed bookings (System Action)
    // Only if not already archived.
    $pdo->exec("
        UPDATE bookings
        SET is_archived = 1,
            archived_at = NOW(),
            archived_by = NULL
        WHERE booking_status = 'completed'
          AND payment_status = 'paid'
          AND is_archived = 0
    ");
    
    return $affected;
}

// Public: active booking count for login page
if ($method === 'GET' && isset($_GET['count_active'])) {
    $count = $pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status = 'confirmed'
        AND event_date >= CURDATE()
    ")->fetchColumn();
    jsonResponse(true, '', ['count' => (int)$count]);
}

$user = requireApiRole(['admin', 'frontdesk']);
requireCsrf();

// ────────────────────────────────────────────────────────────────
// GET
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    autoCompleteExpiredBookings($pdo);

    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT b.*,
                   c.name  AS client_name, c.phone AS client_phone, c.email AS client_email,
                   u.name AS created_by_name,
                   (SELECT COALESCE(SUM(total_cost), 0) FROM booking_breakages WHERE booking_id = b.id) as breakage_total
            FROM bookings b
            LEFT JOIN clients  c  ON c.id  = b.client_id
            LEFT JOIN users    u  ON u.id  = b.created_by
            WHERE b.id = :id
        ");
        $stmt->execute([':id' => (int)$_GET['id']]);
        $booking = $stmt->fetch();
        if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);

        // Attach selected dishes
        $dStmt = $pdo->prepare("
            SELECT d.id, d.name, d.category
            FROM booking_dishes bd
            JOIN dishes d ON d.id = bd.dish_id
            WHERE bd.booking_id = :bid
            ORDER BY d.category, d.name
        ");
        $dStmt->execute([':bid' => $booking['id']]);
        $booking['dishes'] = $dStmt->fetchAll();

        // Attach custom items (optional table)
        try {
            $cStmt = $pdo->prepare("
                SELECT id, name, category, notes
                FROM booking_custom_items
                WHERE booking_id = :bid
                ORDER BY id ASC
            ");
            $cStmt->execute([':bid' => $booking['id']]);
            $booking['custom_items'] = $cStmt->fetchAll();
        } catch (Throwable $e) {
            $booking['custom_items'] = [];
        }

        // Package-less computed label
        $booking['package_name'] = !empty($booking['base_pax']) ? ('Pax Tier ' . (int)$booking['base_pax']) : 'Pax Tier';
        $booking['menu_name']    = $booking['package_name']; // Shared alias for frontend compatibility

        // Attach Staff Assignments
        $sStmt = $pdo->prepare("
            SELECT jo.*, u.name AS staff_name, u.email AS staff_email
            FROM job_orders jo
            JOIN users u ON u.id = jo.staff_id
            WHERE jo.booking_id = :bid
            ORDER BY jo.sent_at ASC
        ");
        $sStmt->execute([':bid' => $booking['id']]);
        $booking['staff'] = $sStmt->fetchAll();

        // Attach Payments
        $pStmt = $pdo->prepare("
            SELECT p.*, u.name AS recorded_by_name
            FROM payments p
            JOIN users u ON u.id = p.recorded_by
            WHERE p.booking_id = :bid
            ORDER BY p.payment_date ASC, p.id ASC
        ");
        $pStmt->execute([':bid' => $booking['id']]);
        $booking['payments'] = $pStmt->fetchAll();

        // Attach Breakage Logs
        $bStmt = $pdo->prepare("
            SELECT bb.*, e.name AS equipment_name, u.name AS logged_by_name
            FROM booking_breakages bb
            JOIN equipment e ON e.id = bb.equipment_id
            JOIN users u ON u.id = bb.logged_by
            WHERE bb.booking_id = :bid
            ORDER BY bb.logged_at ASC
        ");
        $bStmt->execute([':bid' => $booking['id']]);
        $booking['breakages'] = $bStmt->fetchAll();

        // Backward-compatible dish list for grocery/costing module: ?dishes=1
        if (isset($_GET['dishes'])) {
            $dishes = array_map(fn($d) => [
                'dish_id'   => (int)$d['id'],
                'name'      => $d['name'],
                'category'  => $d['category'],
                'is_custom' => false,
            ], $booking['dishes'] ?? []);

            foreach (($booking['custom_items'] ?? []) as $ci) {
                $dishes[] = [
                    'dish_id'   => null,
                    'name'      => $ci['name'],
                    'category'  => $ci['category'],
                    'is_custom' => true,
                    'notes'     => $ci['notes'] ?? null,
                ];
            }

            jsonResponse(true, '', ['dishes' => $dishes]);
        }

        jsonResponse(true, '', ['booking' => $booking]);
    }

    // ── List with filters ─────────────────────────────────────────
    $where  = ['1=1'];
    $params = [];

    // Default: hide archived bookings unless explicitly requested
    if (empty($_GET['include_archived'])) {
        $where[] = 'COALESCE(b.is_archived, 0) = 0';
    }

    if (!empty($_GET['status'])) {
        $where[] = 'b.booking_status = :status';
        $params[':status'] = $_GET['status'];
    }
    if (!empty($_GET['payment_status'])) {
        $where[] = 'b.payment_status = :pay_status';
        $params[':pay_status'] = $_GET['payment_status'];
    }
    if (!empty($_GET['timeframe'])) {
        $tf = $_GET['timeframe'];
        if ($tf === 'day') {
            $where[] = "DATE(b.event_date) = CURDATE()";
        } elseif ($tf === 'week') {
            $where[] = "YEARWEEK(b.event_date, 1) = YEARWEEK(CURDATE(), 1)";
        } elseif ($tf === 'year') {
            $where[] = "YEAR(b.event_date) = YEAR(CURDATE())";
        } else {
            $where[] = "MONTH(b.event_date) = MONTH(CURDATE()) AND YEAR(b.event_date) = YEAR(CURDATE())";
        }
    } else {
        if (!empty($_GET['from'])) {
            $where[] = 'b.event_date >= :from';
            $params[':from'] = $_GET['from'];
        }
        if (!empty($_GET['to'])) {
            $where[] = 'b.event_date <= :to';
            $params[':to'] = $_GET['to'];
        }
    }

    if (!empty($_GET['search'])) {
        $where[] = "(c.name LIKE :s1 OR b.event_location LIKE :s2)";
        $like = '%' . $_GET['search'] . '%';
        $params[':s1'] = $like;
        $params[':s2'] = $like;
    }

    $whereClause = implode(' AND ', $where);

    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if ($limit < 1) $limit = 10;
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN clients c ON c.id = b.client_id WHERE $whereClause");
    $countStmt->execute($params);
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalRecords / $limit);

    $order = 'DESC';
    if (isset($_GET['order']) && strtoupper($_GET['order']) === 'ASC') {
        $order = 'ASC';
    }

    $stmt = $pdo->prepare("
        SELECT b.*,
               c.name AS client_name, c.phone AS client_phone,
               (SELECT COALESCE(SUM(total_cost), 0) FROM booking_breakages WHERE booking_id = b.id) as breakage_total,
               b.resched_count
        FROM bookings b
        JOIN clients c ON c.id = b.client_id
        WHERE $whereClause
        ORDER BY b.event_date $order, b.id $order
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['package_name'] = !empty($r['base_pax']) ? ('Pax Tier ' . (int)$r['base_pax']) : 'Pax Tier';
        $r['menu_name']    = $r['package_name']; // Shared alias for frontend compatibility
    }
    unset($r);
    jsonResponse(true, '', [
        'bookings' => $rows,
        'meta' => [
            'currentPage'  => $page,
            'totalPages'   => $totalPages,
            'totalRecords' => $totalRecords,
        ]
    ]);
}

// ────────────────────────────────────────────────────────────────
// POST — create booking
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = readJsonBody();

    // ── ACTION: Send manual reminder ───────────────────────────────────────────
    if (isset($data['action']) && $data['action'] === 'send_reminder') {
        requireFields($data, ['id']);
        $id = (int)$data['id'];

        $stmt = $pdo->prepare("
            SELECT b.*, c.name AS client_name, c.email AS client_email 
            FROM bookings b
            JOIN clients c ON c.id = b.client_id
            WHERE b.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();

        if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);
        if (empty($booking['client_email'])) jsonResponse(false, 'Client has no email address.', [], 422);

        require_once __DIR__ . '/../../includes/mailer.php';
        if (sendPaymentReminderEmail($booking)) {
            auditLog($pdo, 'reminder_sent', 'booking', $id, null, ['method' => 'manual_email']);
            jsonResponse(true, 'Payment reminder sent successfully.');
        } else {
            jsonResponse(false, 'Failed to send email. Check mailer settings.', [], 500);
        }
    }


    // Compatibility: action-based subroutes
    $action = strtolower(trim((string)($data['action'] ?? '')));

    // ── action: link_staff (legacy) ────────────────────────────────────────
    if ($action === 'link_staff' || (!empty($data['link_staff']) && empty($action))) {
        requireFields($data, ['booking_id', 'staff_roles']);

        $bookingId = (int)$data['booking_id'];
        if ($bookingId <= 0) jsonResponse(false, 'Invalid booking_id.', [], 422);

        // Ensure booking exists
        $bStmt = $pdo->prepare("
            SELECT b.id, b.event_date, b.event_time, b.event_location, b.pax_count
            FROM bookings b
            WHERE b.id = :bid
        ");
        $bStmt->execute([':bid' => $bookingId]);
        $booking = $bStmt->fetch();
        if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);

        if (!is_array($data['staff_roles'])) {
            jsonResponse(false, 'staff_roles must be an array.', [], 422);
        }

        $staffRoles = $data['staff_roles'];
        $insJob = $pdo->prepare("
            INSERT INTO job_orders (booking_id, staff_id, role_required, status)
            VALUES (:bid, :sid, :role, 'pending')
        ");
        $dupCheck = $pdo->prepare("
            SELECT id FROM job_orders
            WHERE booking_id = :bid AND staff_id = :sid AND status IN ('pending','accepted')
            LIMIT 1
        ");
        $insNotif = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, body, booking_id)
            VALUES (:uid, 'job_assigned', 'New Job Assignment', :body, :bid)
        ");

        $created = 0;
        $pdo->beginTransaction();
        try {
            foreach ($staffRoles as $sr) {
                $sid  = (int)($sr['id'] ?? 0);
                $role = trim((string)($sr['role'] ?? 'Staff'));
                if ($sid <= 0 || $role === '') continue;

                // Ensure user exists and is staff
                $uStmt = $pdo->prepare("SELECT id, name, email, role, is_active FROM users WHERE id = :id LIMIT 1");
                $uStmt->execute([':id' => $sid]);
                $u = $uStmt->fetch();
                if (!$u || ($u['role'] ?? '') !== 'staff' || !(int)$u['is_active']) continue;

                $dupCheck->execute([':bid' => $bookingId, ':sid' => $sid]);
                if ($dupCheck->fetch()) continue;

                $insJob->execute([':bid' => $bookingId, ':sid' => $sid, ':role' => $role]);
                $insNotif->execute([
                    ':uid'  => $sid,
                    ':bid'  => $bookingId,
                    ':body' => 'You have been assigned to a booking as a ' . $role . '. Please check your schedule.',
                ]);
                $created++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            jsonResponse(false, 'Failed to link staff: ' . $e->getMessage(), [], 500);
        }

        // Optional email dispatch (best effort, outside transaction)
        if ($created > 0) {
            try {
                require_once __DIR__ . '/../../includes/mailer.php';
                foreach ($staffRoles as $sr) {
                    $sid  = (int)($sr['id'] ?? 0);
                    $role = trim((string)($sr['role'] ?? 'Staff'));
                    if ($sid <= 0) continue;
                    $uStmt = $pdo->prepare("SELECT id, name, email, role, is_active FROM users WHERE id = :id LIMIT 1");
                    $uStmt->execute([':id' => $sid]);
                    $u = $uStmt->fetch();
                    if (!$u || empty($u['email']) || ($u['role'] ?? '') !== 'staff' || !(int)$u['is_active']) continue;

                    sendStaffAssignmentEmail(
                        ['name' => $u['name'], 'email' => $u['email']],
                        [
                            'event_date'     => $booking['event_date'],
                            'event_time'     => $booking['event_time'] ?? 'TBA',
                            'event_location' => $booking['event_location'] ?? 'TBA',
                            'pax_count'      => $booking['pax_count'],
                            'staff_role'     => $role,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                // non-fatal
            }
        }

        jsonResponse(true, 'Staff linked successfully.', ['created' => $created]);
    }

    // ── AUTOMATIC CLIENT CREATION FALLBACK ──
    $clientId = (int)($data['client_id'] ?? 0);
    if ($clientId === 0 && !empty($data['nc_name'])) {
        $ncName  = trim($data['nc_name']);
        $ncPhone = trim($data['nc_phone'] ?? '');
        $ncEmail = trim($data['nc_email'] ?? '');
        if (empty($ncPhone)) jsonResponse(false, 'New client phone is required.', [], 422);
        
        $dup = $pdo->prepare("SELECT id FROM clients WHERE email = :email LIMIT 1");
        $dup->execute([':email' => $ncEmail]);
        if ($existing = $dup->fetch()) {
            $clientId = (int)$existing['id'];
            $data['client_id'] = $clientId;
        } else {
            $cStmt = $pdo->prepare("INSERT INTO clients (name, phone, email, address) VALUES (:n, :p, :e, :a)");
            $cStmt->execute([':n'=>$ncName, ':p'=>$ncPhone, ':e'=>$ncEmail, ':a'=>trim($data['nc_address'] ?? '')]);
            $clientId = (int)$pdo->lastInsertId();
            $data['client_id'] = $clientId;
        }
    }

    requireFields($data, ['client_id', 'event_date', 'pax_count']);

    // ── 1) Validate inputs ────────────────────────────────────────────────
    $minLeadDays = MIN_LEAD_TIME_DAYS;
    $minPax      = MIN_PAX;
    $maxPax      = MAX_PAX;

    $eventDate = validateYmdDate((string)$data['event_date'], 'event date');
    $minAllowedDate = date('Y-m-d', strtotime('+' . $minLeadDays . ' days'));
    $maxAllowedDate = date('Y-m-d', strtotime('+1 year'));

    if ($eventDate < $minAllowedDate) {
        jsonResponse(false, 'Booking date must be at least ' . $minLeadDays . ' days before the event (starting from tomorrow).', [], 422);
    }
    if ($eventDate > $maxAllowedDate) {
        jsonResponse(false, 'Bookings cannot be made more than 1 year in advance.', [], 422);
    }

    $eventTime = validateEventTime($data['event_time'] ?? null);

    $paxCount = (int)($data['pax_count'] ?? 0);
    if ($paxCount < MIN_PAX) jsonResponse(false, 'Minimum ' . MIN_PAX . ' guests required.', [], 422);
    if ($paxCount > MAX_PAX) jsonResponse(false, 'Maximum ' . MAX_PAX . ' guests allowed.', [], 422);
    // Removed 5-pax check as requested


    $pId       = (int)($data['package_id'] ?? 0);
    $pricing   = computePaxPricing($pdo, $paxCount, $pId);
    
    // Validate dishes early and calculate dynamic surcharge
    $maxMain = (int)$pricing['max_main'];
    $maxDess = (int)$pricing['max_desserts'];
    
    $selectedDishes = validateSelectedDishes($pdo, (array)($data['selected_dishes'] ?? []));
    $validDishIds = array_column($selectedDishes, 'id');

    // Dynamic Surcharge Calculation
    $mainCats = ['beef', 'pork', 'chicken', 'seafood', 'vegetables', 'pasta', 'main'];
    
    $dishSurcharge = 0.0;
    $counts = ['main' => 0, 'dessert' => 0, 'rice' => 0];

    foreach ($selectedDishes as $d) {
        $cat = strtolower($d['category']);
        $fee = (float)($d['custom_fee'] ?? 0);
        $isPremium = $fee > 0;
        
        $type = 'other';
        if (in_array($cat, $mainCats)) $type = 'main';
        elseif (in_array($cat, ['dessert', 'desserts', 'sweets'])) $type = 'dessert';
        elseif (in_array($cat, ['rice', 'additional'])) $type = 'rice';

        $limit = ($type === 'main') ? $maxMain : (($type === 'dessert') ? $maxDess : 1);
        $isExtra = ($counts[$type] ?? 0) >= $limit;

        // Only apply surcharge when the dish EXCEEDS the free allowance.
        // Dishes within the limit are always free, even if they have a custom_fee.
        if ($isExtra) {
            $dishSurcharge += $fee;
        }

        if (isset($counts[$type])) $counts[$type]++;
    }

    $basePax   = (int)$pricing['base_pax'];
    $basePrice = (float)$pricing['base_price'];
    $extraPax  = (int)$pricing['extra_pax'];
    $extraCost = (float)$pricing['extra_cost'];
    $transportFee = max(0, round((float)($data['transport_fee'] ?? 0), 2));

    // Calculate Custom Add-ons Surcharge (manual items)
    $customItemsTotal = 0;
    if (!empty($data['custom_items']) && is_array($data['custom_items'])) {
        foreach ($data['custom_items'] as $ci) {
            $p = max(0, (float)($ci['price'] ?? 0));
            $cat = strtolower($ci['category'] ?? 'other');
            // Food items are per-pax; others (e.g. equipment) are flat
            if ($cat === 'main' || $cat === 'dessert') {
                $customItemsTotal += ($p * $paxCount);
            } else {
                $customItemsTotal += $p;
            }
        }
    }

    $totalSurchargePerPax = $dishSurcharge * $paxCount;
    $totalCost = (float)$pricing['total_cost'] + $transportFee + $totalSurchargePerPax + $customItemsTotal;

    $downpayment = round((float)($data['downpayment'] ?? 0), 2);
    if ($downpayment < 0) jsonResponse(false, 'Downpayment cannot be a negative value.', [], 422);

    // ── 5. Financial check ─────────────────────────────────────
    $eventDateObj = new DateTime($eventDate);
    $now          = new DateTime();
    $interval     = $now->diff($eventDateObj);
    $diffHours    = ($interval->days * 24) + $interval->h;
    if (!$interval->invert && $diffHours < RUSH_THRESHOLD_HOURS) {
        $minDpPct = RUSH_DP_PERCENT; // Last minute: force rush payment (within 3 days)
    } else {
        $minDpPct = MIN_DP_PERCENT;
    }

    $minDPVal = round($totalCost * $minDpPct, 2);
    if ($downpayment < $minDPVal - 0.01) {
        $msg = ($minDpPct >= 1.0) 
            ? 'Full payment is required for bookings made within ' . RUSH_THRESHOLD_HOURS . ' hours of the event.'
            : 'Minimum downpayment is ' . (int)round($minDpPct * 100) . '% of total cost (₱' . number_format($minDPVal, 2) . ').';
            
        jsonResponse(false, $msg, ['min_downpayment' => $minDPVal, 'total_cost' => $totalCost], 422);
    }
    if ($downpayment > round($totalCost, 2)) {
        jsonResponse(false,
            'Downpayment cannot exceed total cost of ₱' . number_format($totalCost, 2) . '.',
            [], 422
        );
    }

    // the validation and extraction of validDishIds is now handled before lines 450.

    // ── P1-05: Ensure session is still robustly active before starting transaction ──
    // Using a local variable prevents errors if the $_SESSION is lost during a long request.
    $creatorId = (int)($user['id'] ?? 0);
    if ($creatorId <= 0) {
        jsonResponse(false, 'Your session has expired. Please log in again in a new tab to save your progress.', [], 401);
    }

    // ── 6. Insert booking (in a transaction for data integrity) ────
    $pdo->beginTransaction();
    try {
        // ── Re-check availability inside the transaction with a row lock ──────
        // This prevents the race condition where two users book the same date
        // simultaneously (TOCTOU: Time-of-Check / Time-of-Use gap).
        $avail = $pdo->prepare("
            SELECT COUNT(*) FROM bookings
            WHERE event_date = :date
              AND booking_status NOT IN ('cancelled')
            FOR UPDATE
        ");
        $avail->execute([':date' => $eventDate]);
        if ((int)$avail->fetchColumn() > 0) {
            $pdo->rollBack();
            jsonResponse(false, 'This date was just booked by someone else. Please choose a different date.', [
                'field' => 'event_date',
            ], 409);
        }

        $bookingStmt = $pdo->prepare("
            INSERT INTO bookings
              (client_id, package_id, event_date, event_time, event_location, event_type,
               pax_count, base_pax, extra_pax, base_price, extra_cost,
               transport_fee, surcharge_total, total_cost, booking_status, invoice_token, notes, dietary_notes, created_by)
            VALUES
              (:client_id, :package_id, :event_date, :event_time, :event_location, :event_type,
               :pax_count, :base_pax, :extra_pax, :base_price, :extra_cost,
               :transport_fee, :surcharge_total, :total_cost, :booking_status, :invoice_token, :notes, :dietary_notes, :created_by)
        ");
        $initialStatus = 'confirmed';

        $bookingStmt->execute([
            ':client_id'      => (int)$data['client_id'],
            ':package_id'     => $pId > 0 ? $pId : null,
            ':event_date'     => $eventDate,
            ':event_time'     => $eventTime,
            ':event_location' => !empty($data['event_location']) ? trim(substr($data['event_location'], 0, 500)) : null,
            ':event_type'     => !empty($data['event_type']) ? trim(substr((string)$data['event_type'], 0, 100)) : 'Wedding',
            ':pax_count'      => $paxCount,
            ':base_pax'       => $basePax,
            ':extra_pax'      => $extraPax,
            ':base_price'     => $basePrice,
            ':extra_cost'     => $extraCost,
            ':transport_fee'  => $transportFee,
            ':surcharge_total'=> $totalSurchargePerPax + $customItemsTotal,
            ':total_cost'     => $totalCost,
            ':booking_status' => $initialStatus,
            ':invoice_token'  => bin2hex(random_bytes(16)),
            ':notes'          => !empty($data['notes']) ? trim(substr((string)$data['notes'], 0, 2000)) : null,
            ':dietary_notes'  => !empty($data['dietary_notes']) ? trim(substr((string)$data['dietary_notes'], 0, 1000)) : null,
            ':created_by'     => $creatorId,
        ]);
        $newId = $pdo->lastInsertId();

        // ── 7. Save selected dishes ──────────────────────────────
        $allDishIds = $validDishIds;
        if (!empty($allDishIds)) {
            $dishInsert = $pdo->prepare("INSERT INTO booking_dishes (booking_id, dish_id) VALUES (:bid, :did)");
            foreach ($allDishIds as $did) {
                $dishInsert->execute([':bid' => $newId, ':did' => $did]);
            }
        }

        // ── 7b. Save custom items (optional) ─────────────────────
        if (!empty($data['custom_items']) && is_array($data['custom_items'])) {
            $insCustom = $pdo->prepare("
                INSERT INTO booking_custom_items (booking_id, name, category, price, notes)
                VALUES (:bid, :name, :cat, :price, :notes)
            ");
            foreach ($data['custom_items'] as $ci) {
                $name = trim((string)($ci['name'] ?? ''));
                if ($name === '') continue;
                $cat = (string)($ci['category'] ?? 'other');
                if (!in_array($cat, ['main','dessert','other'], true)) $cat = 'other';
                $price = max(0, (float)($ci['price'] ?? 0));
                
                $insCustom->execute([
                    ':bid'   => $newId,
                    ':name'  => $name,
                    ':cat'   => $cat,
                    ':price' => $price,
                    ':notes' => !empty($ci['notes']) ? trim((string)$ci['notes']) : null,
                ]);
            }
        }

        // ── 8. Record downpayment ────────────────────────────────
        //    amount_paid stays 0 until a payment is recorded
        $amountPaid = 0.00;
        if ($downpayment > 0) {
            $dpMethod = in_array($data['downpayment_method'] ?? '', ['cash','gcash','maya','bank_transfer'])
                ? $data['downpayment_method'] : 'cash';
            $dpRef = trim((string)($data['downpayment_ref'] ?? ''));

            // Validation: Reference number required for digital payments
            if ($dpMethod !== 'cash' && $dpRef === '') {
                jsonResponse(false, "Reference number is required for " . strtoupper($dpMethod) . " payments.", ['field' => 'downpayment_ref'], 422);
            }

            // Insert the payment record
            $pdo->prepare("
                INSERT INTO payments
                  (booking_id, amount, payment_method, reference_no, notes, payment_date, recorded_by)
                VALUES (:bid, :amt, :meth, :ref, 'Downpayment', :dt, :rec)
            ")->execute([
                ':bid'  => $newId,
                ':amt'  => round($downpayment, 2),
                ':meth' => $dpMethod,
                ':ref'  => trim($data['downpayment_ref'] ?? '') ?: null,
                ':dt'   => date('Y-m-d'),
                ':rec'  => $creatorId,
            ]);

            // ── Force-sync amount_paid and auto-promote pending → confirmed ──
            $paidRow = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = :bid");
            $paidRow->execute([':bid' => $newId]);
            $amountPaid = (float)$paidRow->fetchColumn();

            $dpStatus = ($amountPaid >= $totalCost - 0.01) ? 'paid' : 'partial';
            $finalStatus = 'confirmed'; // DP is mandatory — always confirmed on creation

            $pdo->prepare("
                UPDATE bookings SET
                    amount_paid    = :paid,
                    payment_status = :pstatus,
                    booking_status = :bstatus
                WHERE id = :id
            ")->execute([
                ':paid'    => round($amountPaid, 2),
                ':pstatus' => $dpStatus,
                ':bstatus' => $finalStatus,
                ':id'      => $newId,
            ]);

            // Audit: downpayment recorded — capture the payment ID immediately after INSERT
            $newPaymentId = (int)$pdo->lastInsertId();
            auditLog($pdo, 'payment_recorded', 'payment', $newPaymentId,
                null,
                ['booking_id' => $newId, 'amount' => round($downpayment,2), 'notes' => 'Downpayment']
            );

            $initialStatus = $finalStatus; // update for response
        }

        // Audit: booking created
        auditLog($pdo, 'booking_created', 'booking', $newId,
            null,
            ['client_id' => (int)$data['client_id'], 'event_date' => $eventDate,
             'total_cost' => $totalCost, 'booking_status' => $initialStatus]
        );

        $pdo->commit();

        // Immediate email sending now handled directly in sendBookingConfirmation


        // Send email to client (Synchronous — No Queuing)
        $clientStmt = $pdo->prepare("SELECT * FROM clients WHERE id = :cid");
        $clientStmt->execute([':cid' => $data['client_id']]);
        $client = $clientStmt->fetch();
        if ($client && !empty($client['email'])) {
            require_once __DIR__ . '/../../includes/mailer.php';
            try {
                sendBookingConfirmation([
                    'client_email' => $client['email'],
                    'client_name'  => $client['name'],
                    'event_date'   => $eventDate,
                    'menu_name'    => $pricing['pricing_label'] ?? 'Catering Service',
                    'pax_count'    => $paxCount,
                    'total_cost'   => $totalCost,
                    'amount_paid'  => $amountPaid,
                ]);
            } catch (\Throwable $e) {
                error_log("Non-fatal: Email dispatch failed for booking $newId: " . $e->getMessage());
            }
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to create booking: ' . $e->getMessage(), [], 500);
    }

    $remaining = round($totalCost - $amountPaid, 2);

    jsonResponse(true, 'Booking created successfully.', [
        'id'             => $newId,
        'package_name'   => $pricing['pricing_label'] ?? ('Pax Tier ' . $basePax),
        'package_pax'    => $basePax,
        'base_price'     => $basePrice,
        'extra_pax'      => $extraPax,
        'extra_cost'     => $extraCost,
        'total_cost'     => $totalCost,
        'amount_paid'    => $amountPaid,
        'balance'        => $remaining,
        'booking_status' => $initialStatus,
        'downpayment'    => $downpayment,
        'dishes_saved'   => count($allDishIds ?? []),
    ], 201);
}

// ────────────────────────────────────────────────────────────────
// PUT — update status / notes
// ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['id'])) jsonResponse(false, 'Booking ID is required.', [], 422);

    $bookingId = (int)$data['id'];

    // Fetch current state for audit pattern and actual date change detection
    $cur = $pdo->prepare("SELECT booking_status, notes, event_date, pax_count, package_id, base_pax, base_price, extra_pax, extra_cost, total_cost, amount_paid, resched_count, updated_at FROM bookings WHERE id = :id");
    $cur->execute([':id' => $bookingId]);
    $current = $cur->fetch();
    if (!$current) jsonResponse(false, 'Booking not found.', [], 404);

    if ($current['booking_status'] === 'cancelled') {
        jsonResponse(false, 'This booking is cancelled and cannot be modified. Use the Un-Cancel feature to restore it first.', [], 403);
    }

    $archCheck = $pdo->prepare("SELECT is_archived FROM bookings WHERE id = :id");
    $archCheck->execute([':id' => $bookingId]);
    if ((int)$archCheck->fetchColumn() === 1) {
        jsonResponse(false, 'Archived bookings cannot be modified.', [], 403);
    }

    // ── Optimistic Locking Check ──
    // Detect if another user updated this booking since the current user loaded the edit modal.
    $clientUpdatedAt = $data['updated_at'] ?? '';
    $currentUpdatedAt = $current['updated_at'] ?? '';
    
    // Perform loose comparison to avoid microsecond/format mismatch issues common in different MySQL/PHP configs
    if (empty($clientUpdatedAt)) {
        jsonResponse(false, 'CONFLICT: Missing updated_at timestamp. Please refresh the page to get the latest data before saving.', [], 409);
    }
    if (trim($clientUpdatedAt) !== trim($currentUpdatedAt)) {
        jsonResponse(false, 'CONFLICT: This booking was modified by another user while you were editing it. Please refresh and try again.', [
            'db_updated_at' => $currentUpdatedAt,
            'client_updated_at' => $clientUpdatedAt
        ], 409);
    }

    // Validate status transition: pending can't skip to completed
    $newStatus = $data['booking_status'] ?? null;
    $validStatuses = ['confirmed', 'completed', 'cancelled'];
    if ($newStatus !== null && !in_array($newStatus, $validStatuses, true)) {
        jsonResponse(false, 'Invalid booking status.', [], 422);
    }

    if ($newStatus && $newStatus !== $current['booking_status']) {
        $cur = $current['booking_status'];
        if ($newStatus === 'completed' && $cur !== 'confirmed') {
            jsonResponse(false, 'Only confirmed bookings can be marked as completed.', [], 422);
        }
    }

    $event_time = !empty($data['event_time']) ? validateEventTime($data['event_time']) : null;
    $event_location = !empty($data['event_location']) ? $data['event_location'] : null;
    $booking_status = !empty($data['booking_status']) ? $data['booking_status'] : null;

    // ── P1-02: Re-validate date ONLY if it's actually being changed ─────────────────────────────
    $newDate = !empty($data['event_date']) ? trim($data['event_date']) : null;
    $dateChanged = ($newDate && $current && $newDate !== $current['event_date']);

    $dateChanged = ($newDate && $newDate !== $current['event_date']);
    if ($dateChanged) {
        // RESHEDULING POLICY (May 2026):
        // 1. Only one reschedule allowed
        if ((int)($current['resched_count'] ?? 0) >= 1) {
            jsonResponse(false, 'This booking has already been rescheduled once and cannot be moved again.', [], 422);
        }

        $minLeadDays = MIN_LEAD_TIME_DAYS;
        $todayTs = strtotime(date('Y-m-d'));
        $origTs  = strtotime($current['event_date']);
        $newTs   = strtotime($newDate);

        // 2. Original date must be at least 14 days away from today
        $daysToOrig = round(($origTs - $todayTs) / 86400);
        if ($daysToOrig < $minLeadDays) {
            jsonResponse(false, "Rescheduling must be done at least $minLeadDays days before the original event date (Current: $daysToOrig days away).", [], 422);
        }

        // 3. New date must be at least 14 days in the future
        $daysToNew = round(($newTs - $todayTs) / 86400);
        if ($daysToNew < $minLeadDays) {
            jsonResponse(false, "The new event date must be at least $minLeadDays days from today (Selected: $daysToNew days away).", [], 422);
        }

        // Availability check
        $avail = $pdo->prepare("
            SELECT COUNT(*) FROM bookings
            WHERE event_date = :date
              AND id != :self
              AND booking_status NOT IN ('cancelled')
        ");
        $avail->execute([':date' => $newDate, ':self' => $bookingId]);
        if ((int)$avail->fetchColumn() > 0) {
            jsonResponse(false, 'The chosen reschedule date is already booked by another event.', ['field' => 'event_date'], 409);
        }
    }

    // Use the existing values for the update (effectively locking them)
    // $total_cost remains what is already in the database for this booking

    // Wrap in transaction just in case
    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE bookings SET
                event_date     = COALESCE(:event_date,     event_date),
                event_time     = COALESCE(:event_time,     event_time),
                event_location = COALESCE(:event_location, event_location),
                event_type     = COALESCE(:event_type,     event_type),
                booking_status = COALESCE(:booking_status, booking_status),
                notes          = :notes,
                dietary_notes  = COALESCE(:dietary_notes,  dietary_notes),
                resched_count  = resched_count + :resched_inc
            WHERE id = :id
        ")->execute([
            ':id'             => $bookingId,
            ':event_date'     => $newDate,
            ':event_time'     => $event_time,
            ':event_location' => $event_location,
            ':event_type'     => !empty($data['event_type']) ? trim($data['event_type']) : null,
            ':booking_status' => $booking_status,
            ':notes'          => !empty($data['notes']) ? $data['notes'] : null,
            ':dietary_notes'  => !empty($data['dietary_notes']) ? trim((string)$data['dietary_notes']) : null,
            ':resched_inc'    => $dateChanged ? 1 : 0,
        ]);

        // Dish selection update removed as per order
        /*
        if ($dishesUpdated) {
            ...
        }
        */

        // Custom items update (optional). If provided, replace the set.
        if (array_key_exists('custom_items', $data) && is_array($data['custom_items'])) {
            try {
                $pdo->prepare("DELETE FROM booking_custom_items WHERE booking_id = :bid")
                    ->execute([':bid' => $bookingId]);
                $insCustom = $pdo->prepare("
                    INSERT INTO booking_custom_items (booking_id, name, category, price, notes)
                    VALUES (:bid, :name, :cat, :price, :notes)
                ");
                foreach ($data['custom_items'] as $ci) {
                    $name = trim((string)($ci['name'] ?? ''));
                    if ($name === '') continue;
                    $cat = (string)($ci['category'] ?? 'other');
                    if (!in_array($cat, ['main','dessert','other'], true)) $cat = 'other';
                    $price = max(0, (float)($ci['price'] ?? 0));
                    $insCustom->execute([
                        ':bid'   => $bookingId,
                        ':name'  => $name,
                        ':cat'   => $cat,
                        ':price' => $price,
                        ':notes' => !empty($ci['notes']) ? trim((string)$ci['notes']) : null,
                    ]);
                }
            } catch (Throwable $e) {
                // ignore if table not yet migrated
            }
        }
        $pdo->commit();
    } catch (\Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to update booking: ' . $e->getMessage(), [], 500);
    }

    // Notify assigned staff if rescheduled safely using the dateChanged flag
    if ($dateChanged) {
        // Update job order notifications
        $assignedStmt = $pdo->prepare("
            SELECT jo.staff_id, u.name AS staff_name, u.email AS staff_email
            FROM job_orders jo
            JOIN users u ON u.id = jo.staff_id
            WHERE jo.booking_id = :bid AND jo.status IN ('pending','accepted')
        ");
        $assignedStmt->execute([':bid' => $bookingId]);
        $assignedStaff = $assignedStmt->fetchAll();
        foreach ($assignedStaff as $staff) {
            try {
                $notif = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, body)
                    VALUES (:uid, 'general', 'Booking Rescheduled', :body)
                ");
                $notif->execute([
                    ':uid'  => $staff['staff_id'],
                    ':body' => 'A booking you are assigned to has been rescheduled to ' . date('F j, Y', strtotime($newDate)) . '. Please check your assignments.',
                ]);
            } catch (\Exception $e) {
                // Ignore enum restriction error if user hasn't run the migration yet, fallback works
            }
        }
    }

    // Audit the status change
    if ($newStatus && $current && $newStatus !== $current['booking_status']) {
        auditLog($pdo, 'booking_status_changed', 'booking', $bookingId,
            ['booking_status' => $current['booking_status']],
            ['booking_status' => $newStatus]
        );
    }

    jsonResponse(true, 'Booking updated successfully.');
}

// ────────────────────────────────────────────────────────────────
// DELETE — admin only
// ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireApiRole('admin');
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($data['id'])) jsonResponse(false, 'Booking ID is required.', [], 422);

    $bookingId = (int)$data['id'];

    // Fetch booking details for guard check
    $bRow = $pdo->prepare("SELECT booking_status, amount_paid, total_cost FROM bookings WHERE id = :id");
    $bRow->execute([':id' => $bookingId]);
    $booking = $bRow->fetch();
    if (!$booking) jsonResponse(false, 'Booking not found.', [], 404);

    // ── P1-03: Block hard-delete if the booking has payment records ──
    $payCountStmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE booking_id = :id");
    $payCountStmt->execute([':id' => $bookingId]);
    $payCount = (int)$payCountStmt->fetchColumn();

    if ($payCount > 0) {
        jsonResponse(false,
            'Cannot delete a booking that has payment records. ' .
            'To permanently remove it, first archive it (if completed) or set it to cancelled and remove payments individually.',
            ['payments_count' => $payCount], 422
        );
    }

    if ($booking['booking_status'] !== 'cancelled') {
        jsonResponse(false,
            'Only cancelled bookings with no payment records can be deleted. ' .
            'Change the status to "cancelled" first.',
            [], 422
        );
    }

    auditLog($pdo, 'booking_deleted', 'booking', $bookingId, $booking, null);
    $pdo->prepare("DELETE FROM bookings WHERE id = :id")->execute([':id' => $bookingId]);
    jsonResponse(true, 'Booking deleted.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
