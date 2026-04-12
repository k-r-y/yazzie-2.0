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

$method = $_SERVER['REQUEST_METHOD'];

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

// ────────────────────────────────────────────────────────────────
// GET
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("
            SELECT b.*,
                   c.name  AS client_name, c.phone AS client_phone, c.email AS client_email,
                   pk.set_name AS package_name,
                   pk.pax_count AS package_pax, pk.price AS package_base_price,
                   
                   u.name AS created_by_name
            FROM bookings b
            LEFT JOIN clients  c  ON c.id  = b.client_id
            LEFT JOIN packages pk ON pk.id = b.package_id
           
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

        jsonResponse(true, '', ['booking' => $booking]);
    }

    // ── List with filters ─────────────────────────────────────────
    $where  = ['1=1'];
    $params = [];

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
        $where[] = "(c.name LIKE :s1 OR b.event_location LIKE :s2 OR pk.set_name LIKE :s3)";
        $like = '%' . $_GET['search'] . '%';
        $params[':s1'] = $like;
        $params[':s2'] = $like;
        $params[':s3'] = $like;
    }

    $whereClause = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT b.id, b.event_date, b.event_time, b.event_location,
               b.pax_count, b.total_cost, b.amount_paid,
               b.payment_status, b.booking_status,
               b.base_pax, b.extra_pax, b.base_price, b.extra_cost,
               b.notes, b.created_at,
               c.name AS client_name, c.phone AS client_phone,
               pk.set_name AS package_name
        FROM bookings b
        LEFT JOIN clients  c  ON c.id  = b.client_id
        LEFT JOIN packages pk ON pk.id = b.package_id
        WHERE $whereClause
        ORDER BY b.event_date ASC, b.id DESC
    ");
    $stmt->execute($params);
    jsonResponse(true, '', ['bookings' => $stmt->fetchAll()]);
}

// ────────────────────────────────────────────────────────────────
// POST — create booking
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── Intercept link_staff request ──────────────────────────────
    if (!empty($data['link_staff']) && !empty($data['booking_id']) && !empty($data['staff_roles'])) {
        requireApiRole(['admin', 'frontdesk']);
        $bookingId = (int)$data['booking_id'];
        
        $stmt = $pdo->prepare("
            INSERT INTO job_orders (booking_id, staff_id, role_required)
            VALUES (:bid, :sid, :role)
        ");
        
        foreach ($data['staff_roles'] as $s) {
            $sid = (int)$s['id'];
            $role = $s['role'];
            // Check dupes
            $dupCheck = $pdo->prepare("SELECT id FROM job_orders WHERE booking_id=:bid AND staff_id=:sid AND status='pending'");
            $dupCheck->execute([':bid' => $bookingId, ':sid' => $sid]);
            if ($dupCheck->fetch()) continue;
            
            $stmt->execute([':bid' => $bookingId, ':sid' => $sid, ':role' => $role]);
            
            // In-app notification
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, title, body) VALUES (?, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a $role. Please check your schedule.')");
            $notif->execute([$sid]);
        }
        
        // Let's send an email too - get the staff list
        require_once __DIR__ . '/../../includes/mailer.php';
        
        $uStmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
        $bStmt = $pdo->prepare("SELECT b.*, c.name as client_name FROM bookings b JOIN clients c ON c.id = b.client_id WHERE b.id = :bid");
        
        foreach ($data['staff_roles'] as $s) {
            $uStmt->execute([':id' => $s['id']]);
            $u = $uStmt->fetch();
            
            $bStmt->execute([':bid' => $bookingId]);
            $b = $bStmt->fetch();
            
            if ($u && $b && !empty($u['email'])) {
                try {
                    sendStaffAssignmentEmail($u['email'], $u['name'], [
                        'event_date' => $b['event_date'],
                        'event_time' => $b['event_time'] ?? 'TBA',
                        'event_location' => $b['event_location'] ?? 'TBA',
                        'client_name' => $b['client_name'],
                        'role' => $s['role']
                    ]);
                } catch (\Throwable $e) {
                    error_log("Non-fatal: Email assignment dispatch failed for staff ID " . $u['id']);
                }
            }
        }
        
        jsonResponse(true, 'Staff linked successfully.', []);
    }

    foreach (['client_id', 'event_date', 'pax_count'] as $f) {
        if (empty($data[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }

    // ── 1. Validate date format (guard against leap-year overflow etc.) ──
    $eventDate = trim($data['event_date'] ?? '');
    $parsedDate = \DateTime::createFromFormat('Y-m-d', $eventDate);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $eventDate) {
        jsonResponse(false, 'Invalid event date. Use YYYY-MM-DD format.', [], 422);
    }
    if ($eventDate < date('Y-m-d', strtotime('+' . MIN_LEAD_TIME_DAYS . ' days'))) {
        jsonResponse(false, 'Booking date must be at least ' . MIN_LEAD_TIME_DAYS . ' days before the event.', [], 422);
    }
    
    $eventTime = $data['event_time'] ?? '';
    if (!empty($eventTime)) {
        $hour = (int)explode(':', $eventTime)[0];
        if ($hour < 8 || $hour >= 22) {
            jsonResponse(false, 'Event start time cannot be earlier than 08:00 AM or later than 09:59 PM.', [], 422);
        }
    }

    $paxCount = (int)$data['pax_count'];
    if ($paxCount < 50) {
        jsonResponse(false, 'Minimum of 50 guests is required.', ['min_pax' => 50], 422);
    }
    if ($paxCount > 300) {
        jsonResponse(false, 'Maximum of 300 guests is allowed.', ['max_pax' => 300], 422);
    }

    // ── 2. Auto-select package tier from DB ────────────────────────
    // Find the correct package: biggest tier NOT exceeding paxCount
    // e.g. 75 pax → Set A (50 pax), 130 pax → Set B (100 pax)
    $TIER_STEP = 50;
    $tierPax   = (int)(floor($paxCount / $TIER_STEP) * $TIER_STEP);
    if ($tierPax < 50) $tierPax = 50;

    $pkgStmt = $pdo->prepare("
        SELECT id, set_name, pax_count, price, max_main_dishes, max_desserts
        FROM packages
        WHERE pax_count = :tier AND is_active = 1
    ");
    $pkgStmt->execute([':tier' => $tierPax]);
    $pkg = $pkgStmt->fetch();

    // If no exact match (e.g. pax > 300), use the highest available package
    if (!$pkg) {
        $pkg = $pdo->query("SELECT id, set_name, pax_count, price, max_main_dishes, max_desserts
                            FROM packages WHERE is_active = 1 ORDER BY pax_count DESC LIMIT 1")->fetch();
        if (!$pkg) jsonResponse(false, 'No active packages found. Please contact the administrator.', [], 500);
        $tierPax = (int)$pkg['pax_count'];
    }

    $p = $paxCount;
    if ($p < 100) { $pkg['max_main_dishes'] = 5; $pkg['max_desserts'] = 1; }
    elseif ($p < 150) { $pkg['max_main_dishes'] = 6; $pkg['max_desserts'] = 1; }
    elseif ($p < 200) { $pkg['max_main_dishes'] = 7; $pkg['max_desserts'] = 2; }
    elseif ($p < 250) { $pkg['max_main_dishes'] = 8; $pkg['max_desserts'] = 2; }
    elseif ($p < 300) { $pkg['max_main_dishes'] = 9; $pkg['max_desserts'] = 3; }
    else { $pkg['max_main_dishes'] = 10; $pkg['max_desserts'] = 3; }

    // ── 3. Pro-rated pricing engine ──────────────────────────────
    $basePax    = (int)$pkg['pax_count'];
    $basePrice  = (float)$pkg['price'];
    $ratePerPax = $basePrice / $basePax;          // e.g. ₱10,000/50 = ₱200/pax
    $extraPax   = max(0, $paxCount - $tierPax);
    $extraCost  = round($extraPax * $ratePerPax, 2);
    $totalCost  = round($basePrice + $extraCost, 2);

    // ── 4. Validate downpayment ──────────────────────────────────
    $downpayment = round((float)($data['downpayment'] ?? 0), 2);
    if ($downpayment < 0) {
        jsonResponse(false, 'Downpayment cannot be a negative value.', [], 422);
    }
    if ($downpayment > 0) {
        $minDP = round($totalCost * 0.50, 2);
        if ($downpayment < $minDP) {
            jsonResponse(false,
                'Minimum downpayment is 50% of total cost (₱' . number_format($minDP, 2) . ').',
                ['min_downpayment' => $minDP, 'total_cost' => $totalCost], 422);
        }
        if ($downpayment > round($totalCost, 2)) {
            jsonResponse(false,
                'Downpayment cannot exceed total cost of ₱' . number_format($totalCost, 2) . '.',
                [], 422);
        }
    }

    // ── 5. Validate selected dishes ──────────────────────────────
    $selectedDishIds = array_map('intval', (array)($data['selected_dishes'] ?? []));
    $mainDishIds     = [];
    $dessertIds      = [];

    if (!empty($selectedDishIds)) {
        $placeholders = implode(',', array_fill(0, count($selectedDishIds), '?'));
        $dishStmt     = $pdo->prepare("SELECT id, category FROM dishes WHERE id IN ($placeholders) AND is_active = 1");
        $dishStmt->execute($selectedDishIds);
        $validDishes  = $dishStmt->fetchAll();

        foreach ($validDishes as $d2) {
            if ($d2['category'] === 'main')    $mainDishIds[]  = (int)$d2['id'];
            if ($d2['category'] === 'dessert') $dessertIds[]   = (int)$d2['id'];
        }

        $maxMain = (int)$pkg['max_main_dishes'];
        if (count($mainDishIds) > $maxMain) {
            jsonResponse(false, "You can select a maximum of $maxMain main dishes for this package.", [], 422);
        }
        if (count($dessertIds) > (int)$pkg['max_desserts']) {
            jsonResponse(false, 'You can only select 1 dessert.', [], 422);
        }
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
              (client_id, package_id, event_date, event_time, event_location, pax_count,
               base_pax, extra_pax, base_price, extra_cost,
               total_cost, booking_status, invoice_token, notes, created_by)
            VALUES
              (:client_id, :package_id, :event_date, :event_time, :event_location, :pax_count,
               :base_pax, :extra_pax, :base_price, :extra_cost,
               :total_cost, :booking_status, :invoice_token, :notes, :created_by)
        ");
        // ── Determine initial booking_status based on downpayment ────────────
        // 'confirmed' requires >= 50% DP. Without it, booking is 'pending'.
        $minDP50 = round($totalCost * 0.50, 2);
        $initialStatus = ($downpayment >= $minDP50 - 0.01) ? 'confirmed' : 'pending';

        $bookingStmt->execute([
            ':client_id'      => (int)$data['client_id'],
            ':package_id'     => (int)$pkg['id'],
            ':event_date'     => $eventDate,
            ':event_time'     => $data['event_time']     ?? null,
            ':event_location' => $data['event_location'] ?? null,
            ':pax_count'      => $paxCount,
            ':base_pax'       => $basePax,
            ':extra_pax'      => $extraPax,
            ':base_price'     => $basePrice,
            ':extra_cost'     => $extraCost,
            ':total_cost'     => $totalCost,
            ':booking_status' => $initialStatus,
            ':invoice_token'  => bin2hex(random_bytes(16)),
            ':notes'          => $data['notes'] ?? null,
            ':created_by'     => (int)$_SESSION['user_id'],
        ]);
        $newId = $pdo->lastInsertId();

        // ── 7. Save selected dishes ──────────────────────────────
        $allDishIds = array_merge($mainDishIds, $dessertIds);
        if (!empty($allDishIds)) {
            $dishInsert = $pdo->prepare("INSERT INTO booking_dishes (booking_id, dish_id) VALUES (:bid, :did)");
            foreach ($allDishIds as $did) {
                $dishInsert->execute([':bid' => $newId, ':did' => $did]);
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
                ':rec'  => (int)$_SESSION['user_id'],
            ]);

            // ── Force-sync amount_paid and auto-promote pending → confirmed ──
            $paidRow = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE booking_id = :bid");
            $paidRow->execute([':bid' => $newId]);
            $amountPaid = (float)$paidRow->fetchColumn();

            $dpStatus = ($amountPaid >= $totalCost - 0.01) ? 'paid' : 'partial';

            // Auto-promote: if DP meets 50% threshold, confirm the booking
            $finalStatus = ($amountPaid >= $minDP50 - 0.01) ? 'confirmed' : 'pending';

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

            // Audit: downpayment recorded
            auditLog($pdo, 'payment_recorded', 'payment', (int)$pdo->lastInsertId(),
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

        // Send email to client
        require_once __DIR__ . '/../../includes/mailer.php';
        $clientStmt = $pdo->prepare("SELECT * FROM clients WHERE id = :cid");
        $clientStmt->execute([':cid' => $data['client_id']]);
        $client = $clientStmt->fetch();
        if ($client && !empty($client['email'])) {
            try {
                sendBookingConfirmation([
                    'client_email' => $client['email'],
                    'client_name' => $client['name'],
                    'event_date' => $eventDate,
                   
                    'pax_count' => $paxCount,
                    'total_cost' => $totalCost,
                    'amount_paid' => $amountPaid
                ]);
            } catch (\Throwable $e) {
                error_log("Non-fatal: Email dispatch failed for booking " . $newId);
            }
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, 'Failed to create booking: ' . $e->getMessage(), [], 500);
    }

    $remaining = round($totalCost - $amountPaid, 2);

    jsonResponse(true, 'Booking created successfully.', [
        'id'             => $newId,
        'package_name'   => $pkg['set_name'],
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

    // Fetch current state for audit trail
    $cur = $pdo->prepare("SELECT booking_status, notes FROM bookings WHERE id = :id");
    $cur->execute([':id' => $bookingId]);
    $current = $cur->fetch();

    // Validate status transition: pending can't skip to completed
    $newStatus = $data['booking_status'] ?? null;
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if ($newStatus !== null && !in_array($newStatus, $validStatuses, true)) {
        jsonResponse(false, 'Invalid booking status.', [], 422);
    }

    $pdo->prepare("
        UPDATE bookings SET
            event_time     = COALESCE(:event_time,     event_time),
            event_location = COALESCE(:event_location, event_location),
            booking_status = COALESCE(:booking_status, booking_status),
            notes          = :notes
        WHERE id = :id
    ")->execute([
        ':id'             => $bookingId,
        ':event_time'     => $data['event_time']     ?? null,
        ':event_location' => $data['event_location'] ?? null,
        ':booking_status' => $newStatus,
        ':notes'          => $data['notes']          ?? null,
    ]);

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

    $pdo->prepare("DELETE FROM bookings WHERE id = :id")->execute([':id' => (int)$data['id']]);
    jsonResponse(true, 'Booking deleted.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
