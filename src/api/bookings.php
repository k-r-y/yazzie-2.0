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
    if ($h < 8 || $h >= 22) {
        jsonResponse(false, 'Event start time cannot be earlier than 08:00 AM or later than 09:59 PM.', [], 422);
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
    $tierStep = 50;
    $basePax  = (int)(floor($paxCount / $tierStep) * $tierStep);
    if ($basePax < 50) $basePax = 50;
    
    $maxPaxSetting = function_exists('appSettingInt') ? appSettingInt('max_pax', MAX_PAX) : MAX_PAX;
    if ($basePax > $maxPaxSetting) $basePax = $maxPaxSetting;

    $basePrice = round(5000 + ($basePax * 100), 2);
    $pkgName = "Pax Tier {$basePax}";
    $maxMain = 5;
    $maxDesserts = 1;

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
        }
    }

    $ratePerPax = $basePax > 0 ? ($basePrice / $basePax) : 0.0;
    $extraPax   = max(0, $paxCount - $basePax);
    $extraCost  = round($extraPax * $ratePerPax, 2);
    $totalCost  = round($basePrice + $extraCost, 2);

    // Legacy logic for max dishes if no package was provided
    if (!$packageId || empty($pkg)) {
        if ($paxCount < 100)      { $maxMain = 5;  $maxDesserts = 1; }
        elseif ($paxCount < 150)  { $maxMain = 6;  $maxDesserts = 1; }
        elseif ($paxCount < 200)  { $maxMain = 7;  $maxDesserts = 2; }
        elseif ($paxCount < 250)  { $maxMain = 8;  $maxDesserts = 2; }
        elseif ($paxCount < 300)  { $maxMain = 9;  $maxDesserts = 3; }
        else                      { $maxMain = 10; $maxDesserts = 3; }
    }

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

function validateSelectedDishes(PDO $pdo, array $selectedDishIds, int $maxMain, int $maxDesserts): array {
    $selectedDishIds = array_values(array_filter(array_map('intval', $selectedDishIds), fn($v) => $v > 0));
    if (empty($selectedDishIds)) return [[], 0.0];

    $placeholders = implode(',', array_fill(0, count($selectedDishIds), '?'));
    $dishStmt = $pdo->prepare("SELECT id, category, custom_fee FROM dishes WHERE id IN ($placeholders) AND is_active = 1");
    $dishStmt->execute($selectedDishIds);
    $valid = $dishStmt->fetchAll();

    $mainCount = 0;
    $dessertCount = 0;
    $customFees = 0.0;
    $validIds = [];
    $mainCategories = ['beef', 'pork', 'chicken', 'seafood', 'vegetables', 'pasta', 'main'];

    foreach ($valid as $d) {
        $validIds[] = (int)$d['id'];
        $cat = strtolower($d['category']);
        $isMain = in_array($cat, $mainCategories);
        if ($isMain) {
            $mainCount++;
        } elseif ($cat === 'dessert') {
            $dessertCount++;
        }
        $customFees += (float)($d['custom_fee'] ?? 0);
    }

    if ($mainCount > $maxMain) {
        jsonResponse(false, "You can select a maximum of $maxMain main dishes for this package.", [], 422);
    }
    if ($dessertCount > $maxDesserts) {
        jsonResponse(false, "You can select a maximum of $maxDesserts dessert(s) for this package.", [], 422);
    }

    return [$validIds, $customFees];
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Public: active booking count for login page
if ($method === 'GET' && isset($_GET['count_active'])) {
    $count = $pdo->query("
        SELECT COUNT(*) FROM bookings
        WHERE booking_status IN ('pending','confirmed')
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
    if (!empty($_GET['from'])) {
        $where[] = 'b.event_date >= :from';
        $params[':from'] = $_GET['from'];
    }
    if (!empty($_GET['to'])) {
        $where[] = 'b.event_date <= :to';
        $params[':to'] = $_GET['to'];
    }
    if (!empty($_GET['search'])) {
        $where[] = "(c.name LIKE :s1 OR b.event_location LIKE :s2)";
        $like = '%' . $_GET['search'] . '%';
        $params[':s1'] = $like;
        $params[':s2'] = $like;
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT b.*,
               c.name AS client_name, c.phone AS client_phone,
               (SELECT COALESCE(SUM(total_cost), 0) FROM booking_breakages WHERE booking_id = b.id) as breakage_total
        FROM bookings b
        JOIN clients c ON c.id = b.client_id
        WHERE $whereClause
        ORDER BY b.event_date DESC, b.id DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['package_name'] = !empty($r['base_pax']) ? ('Pax Tier ' . (int)$r['base_pax']) : 'Pax Tier';
        $r['menu_name']    = $r['package_name']; // Shared alias for frontend compatibility
    }
    unset($r);
    jsonResponse(true, '', ['bookings' => $rows]);
}

// ────────────────────────────────────────────────────────────────
// POST — create booking
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = readJsonBody();

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

    requireFields($data, ['client_id', 'event_date', 'pax_count']);

    // ── 1) Validate inputs ────────────────────────────────────────────────
    $minLeadDays = function_exists('appSettingInt') ? appSettingInt('min_lead_time_days', MIN_LEAD_TIME_DAYS) : MIN_LEAD_TIME_DAYS;
    $minPax = function_exists('appSettingInt') ? appSettingInt('min_pax', MIN_PAX) : MIN_PAX;
    $maxPax = function_exists('appSettingInt') ? appSettingInt('max_pax', MAX_PAX) : MAX_PAX;

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
    if ($paxCount < $minPax) {
        jsonResponse(false, "Minimum of $minPax guests is required.", [], 422);
    }
    if ($paxCount > $maxPax) {
        jsonResponse(false, "Maximum guest count is capped at $maxPax.", [], 422);
    }
    // Removed 5-pax check as requested


    $pId       = (int)($data['package_id'] ?? 0);
    $pricing   = computePaxPricing($pdo, $paxCount, $pId);
    
    // Validate dishes early to calculate total cost including custom fees
    $maxMain = (int)$pricing['max_main'];
    $maxDess = (int)$pricing['max_desserts'];
    list($validDishIds, $customFees) = validateSelectedDishes($pdo, (array)($data['selected_dishes'] ?? []), $maxMain, $maxDess);

    $basePax   = (int)$pricing['base_pax'];
    $basePrice = (float)$pricing['base_price'];
    $extraCost = (float)$pricing['extra_cost'];
    $transportFee = max(0, round((float)($data['transport_fee'] ?? 0), 2));

    // Calculate Custom Add-ons Surcharge
    $customItemsTotal = 0;
    if (!empty($data['custom_items']) && is_array($data['custom_items'])) {
        foreach ($data['custom_items'] as $ci) {
            $customItemsTotal += max(0, (float)($ci['price'] ?? 0));
        }
    }

    $totalCost = (float)$pricing['total_cost'] + $transportFee + $customFees + $customItemsTotal;

    $downpayment = round((float)($data['downpayment'] ?? 0), 2);
    if ($downpayment < 0) jsonResponse(false, 'Downpayment cannot be a negative value.', [], 422);

    // ── 5. Financial check ─────────────────────────────────────
    $eventDateObj = new DateTime($eventDate);
    $now          = new DateTime();
    $interval     = $now->diff($eventDateObj);
    $diffHours    = ($interval->days * 24) + $interval->h;
    if (!$interval->invert && $diffHours < 72) {
        $minDpPct = 1.0; // Last minute: force full payment (within 3 days)
    } else {
        $minDpPct = (float)appSetting('min_dp_percent', MIN_DP_PERCENT);
    }

    $minDPVal = round($totalCost * $minDpPct, 2);
    if ($downpayment > 0 && $downpayment < $minDPVal - 0.01) {
        $msg = ($minDpPct >= 1.0) 
            ? 'Full payment is required for bookings made within 3 days (72h) of the event.'
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
        // ── Determine initial booking_status based on downpayment ────────────
        // 'confirmed' requires meeting the threshold (30% or 100% if <72h).
        $initialStatus = ($downpayment >= $minDPVal - 0.01) ? 'confirmed' : 'pending';

        $bookingStmt->execute([
            ':client_id'      => (int)$data['client_id'],
            ':package_id'     => $pId > 0 ? $pId : null,
            ':event_date'     => $eventDate,
            ':event_time'     => $eventTime,
            ':event_location' => !empty($data['event_location']) ? $data['event_location'] : null,
            ':event_type'     => !empty($data['event_type']) ? trim($data['event_type']) : 'Wedding',
            ':pax_count'      => $paxCount,
            ':base_pax'       => $basePax,
            ':extra_pax'      => $extraPax,
            ':base_price'     => $basePrice,
            ':extra_cost'     => $extraCost,
            ':transport_fee'  => $transportFee,
            ':surcharge_total'=> $customItemsTotal,
            ':total_cost'     => $totalCost,
            ':booking_status' => $initialStatus,
            ':invoice_token'  => bin2hex(random_bytes(16)),
            ':notes'          => $data['notes'] ?? null,
            ':dietary_notes'  => !empty($data['dietary_notes']) ? trim((string)$data['dietary_notes']) : null,
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
            $finalStatus = ($amountPaid >= $minDPVal - 0.01) ? 'confirmed' : 'pending';

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

        // Trigger the cron worker in the background (non-blocking)
        // This ensures the email queue is processed immediately after the booking.
        $phpPath = '/Applications/XAMPP/xamppfiles/bin/php';
        $cronPath = escapeshellarg(__DIR__ . '/../../cron_worker.php');
        if (file_exists($phpPath)) {
            shell_exec("$phpPath $cronPath > /dev/null 2>&1 &");
        }

        // Send email to client (Queuing)
        $clientStmt = $pdo->prepare("SELECT * FROM clients WHERE id = :cid");
        $clientStmt->execute([':cid' => $data['client_id']]);
        $client = $clientStmt->fetch();
        if ($client && !empty($client['email'])) {
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
    $cur = $pdo->prepare("SELECT booking_status, notes, event_date, pax_count, package_id, base_pax, base_price, extra_pax, extra_cost, total_cost, amount_paid FROM bookings WHERE id = :id");
    $cur->execute([':id' => $bookingId]);
    $current = $cur->fetch();
    if (!$current) jsonResponse(false, 'Booking not found.', [], 404);

    if ($current['booking_status'] === 'cancelled' && ($data['booking_status'] ?? '') !== 'pending') {
        jsonResponse(false, 'This booking is cancelled and cannot be modified. Reset status to pending to edit.', [], 403);
    }

    // Validate status transition: pending can't skip to completed
    $newStatus = $data['booking_status'] ?? null;
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if ($newStatus !== null && !in_array($newStatus, $validStatuses, true)) {
        jsonResponse(false, 'Invalid booking status.', [], 422);
    }

    $event_time = !empty($data['event_time']) ? $data['event_time'] : null;
    $event_location = !empty($data['event_location']) ? $data['event_location'] : null;
    $booking_status = !empty($data['booking_status']) ? $data['booking_status'] : null;

    // ── P1-02: Re-validate date ONLY if it's actually being changed ─────────────────────────────
    $newDate = !empty($data['event_date']) ? trim($data['event_date']) : null;
    $dateChanged = ($newDate && $current && $newDate !== $current['event_date']);

    if ($dateChanged) {
        $parsedNew = \DateTime::createFromFormat('Y-m-d', $newDate);
        if (!$parsedNew || $parsedNew->format('Y-m-d') !== $newDate) {
            jsonResponse(false, 'Invalid event date format. Use YYYY-MM-DD.', [], 422);
        }
        // Check lead time
        $minLeadDays = function_exists('appSettingInt') ? appSettingInt('min_lead_time_days', MIN_LEAD_TIME_DAYS) : MIN_LEAD_TIME_DAYS;
        if ($newDate < date('Y-m-d', strtotime('+' . $minLeadDays . ' days'))) {
            jsonResponse(false, 'Rescheduled date must be at least ' . $minLeadDays . ' days from today.', [], 422);
        }
        // Check one-booking-per-date (exclude self, though we know it changed)
        $avail = $pdo->prepare("
            SELECT COUNT(*) FROM bookings
            WHERE event_date = :date
              AND id != :self
              AND booking_status NOT IN ('cancelled')
        ");
        $avail->execute([':date' => $newDate, ':self' => $bookingId]);
        if ((int)$avail->fetchColumn() > 0) {
            jsonResponse(false, 'The chosen reschedule date is already booked by another event. Please select a different date.', ['field' => 'event_date'], 409);
        }
    }

    $newPax = !empty($data['pax_count']) ? (int)$data['pax_count'] : (int)$current['pax_count'];
    $paxChanged = ($newPax !== (int)$current['pax_count']);
    
    $base_pax   = $current['base_pax'];
    $base_price = $current['base_price'];
    $extra_pax  = $current['extra_pax'];
    $extra_cost = $current['extra_cost'];
    $total_cost = $current['total_cost'];
    $maxMain = 5;
    $maxDesserts = 1;
    
    if ($paxChanged) {
        $minPax = function_exists('appSettingInt') ? appSettingInt('min_pax', MIN_PAX) : MIN_PAX;
        $maxPax = function_exists('appSettingInt') ? appSettingInt('max_pax', MAX_PAX) : MAX_PAX;
        if ($newPax < $minPax) jsonResponse(false, 'Minimum of ' . $minPax . ' guests is required.', [], 422);
        if ($newPax > $maxPax) jsonResponse(false, 'Maximum of ' . $maxPax . ' guests is allowed.', [], 422);

        $pId     = isset($data['package_id']) ? (int)$data['package_id'] : (int)$booking['package_id'];
        $pricing = computePaxPricing($pdo, $newPax, $pId);
        $base_pax   = (int)$pricing['base_pax'];
        $base_price = (float)$pricing['base_price'];
        $extra_pax  = (int)$pricing['extra_pax'];
        $extra_cost = (float)$pricing['extra_cost'];
        $newTransport = isset($data['transport_fee']) ? max(0, round((float)$data['transport_fee'], 2)) : 0;
        $total_cost = (float)$pricing['total_cost'] + $newTransport;
        $maxMain = (int)$pricing['max_main'];
        $maxDesserts = (int)$pricing['max_desserts'];
    }
    
    }
    
    // Always re-calculate total cost if pax, package, transport, or dishes changed
    // to avoid rounding drifts or double-counting surcharges
    $finalTransport = isset($data['transport_fee']) ? max(0, round((float)$data['transport_fee'], 2)) : (float)$current['transport_fee'];
    $finalPId = isset($data['package_id']) ? (int)$data['package_id'] : (int)$current['package_id'];
    $finalPricing = computePaxPricing($pdo, $newPax, $finalPId);
    
    $total_cost = (float)$finalPricing['total_cost'] + $finalTransport;
    
    $dishesUpdated = false;
    $validDishIds = [];
    if (isset($data['selected_dishes'])) {
        list($validDishIds, $customFees) = validateSelectedDishes($pdo, (array)$data['selected_dishes'], $maxMain, $maxDesserts);
        $total_cost += $customFees;
        $dishesUpdated = true;
    } else {
        // If dishes NOT updated, we still need current dish surcharges to keep total_cost accurate
        $stmtOld = $pdo->prepare("SELECT SUM(custom_fee) FROM dishes d JOIN booking_dishes bd ON d.id = bd.dish_id WHERE bd.booking_id = ?");
        $stmtOld->execute([$bookingId]);
        $total_cost += (float)$stmtOld->fetchColumn();
    }

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
                pax_count      = :pax_count,
                package_id     = :package_id,
                base_pax       = :base_pax,
                base_price     = :base_price,
                extra_pax      = :extra_pax,
                extra_cost     = :extra_cost,
                transport_fee  = :transport_fee,
                total_cost     = :total_cost
            WHERE id = :id
        ")->execute([
            ':id'             => $bookingId,
            ':event_date'     => $newDate,
            ':event_time'     => $event_time,
            ':event_location' => $event_location,
            ':event_type'     => !empty($data['event_type']) ? trim($data['event_type']) : null,
            ':booking_status' => $booking_status,
            ':notes'          => !empty($data['notes']) ? $data['notes'] : null,
            ':pax_count'      => $newPax,
            ':package_id'     => null, // package-less system
            ':base_pax'       => $base_pax,
            ':base_price'     => $base_price,
            ':extra_pax'      => $extra_pax,
            ':extra_cost'     => $extra_cost,
            ':transport_fee'  => isset($data['transport_fee']) ? max(0, round((float)$data['transport_fee'], 2)) : null,
            ':total_cost'     => $total_cost,
        ]);

        if ($dishesUpdated) {
            $pdo->prepare("DELETE FROM booking_dishes WHERE booking_id = :bid")->execute([':bid' => $bookingId]);
            $allDishIds = array_merge($mainDishIds ?? [], $dessertIds ?? []);
            if (!empty($allDishIds)) {
                $dInsert = $pdo->prepare("INSERT INTO booking_dishes (booking_id, dish_id) VALUES (:bid, :did)");
                foreach ($allDishIds as $did) $dInsert->execute([':bid' => $bookingId, ':did' => $did]);
            }
        }

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
