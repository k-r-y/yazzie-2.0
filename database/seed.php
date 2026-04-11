<?php
/**
 * Yazzies Catering OMS — Database Seeder
 * Run this script ONCE after importing schema.sql
 * URL: http://localhost/test/database/seed.php
 *
 * Creates:
 *   - 1 Admin, 2 Front Desk, 4 On-Call Staff users
 *   - 4 sample menu packages with ingredients
 *   - 5 sample clients
 *   - 6 sample bookings (various statuses)
 *   - Sample payments for some bookings
 */

require_once __DIR__ . '/../config/config.php';

// Security: only run from CLI or localhost
$allowedHosts = ['localhost', '127.0.0.1', '::1'];
if (php_sapi_name() !== 'cli') {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!in_array($host, $allowedHosts)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>This script can only be run from localhost.</p>');
    }
}

echo "<pre style='font-family:monospace; background:#1a1a2e; color:#a8e6cf; padding:20px;'>";
echo "=== Yazzies Catering OMS — Database Seeder ===\n\n";

try {
    // -------------------------------------------------------
    // 1. USERS
    // -------------------------------------------------------
    echo "Seeding users...\n";

    $users = [
        ['name' => 'Yasmin Reyes',    'email' => 'admin@yazzies.com',     'password' => 'Admin@1234',    'role' => 'admin',     'phone' => '09171234567'],
        ['name' => 'Maria Gonzales',  'email' => 'frontdesk@yazzies.com', 'password' => 'Frontdesk@123', 'role' => 'frontdesk', 'phone' => '09281234567'],
        ['name' => 'Jovelyn Santos',  'email' => 'jovy@yazzies.com',      'password' => 'Staff@1234',    'role' => 'frontdesk', 'phone' => '09391234567'],
        ['name' => 'Ramon dela Cruz', 'email' => 'ramon@yazzies.com',     'password' => 'Staff@1234',    'role' => 'staff',     'phone' => '09501234567'],
        ['name' => 'Luisa Bautista',  'email' => 'luisa@yazzies.com',     'password' => 'Staff@1234',    'role' => 'staff',     'phone' => '09611234567'],
        ['name' => 'Eduardo Manuel',  'email' => 'eduardo@yazzies.com',   'password' => 'Staff@1234',    'role' => 'staff',     'phone' => '09721234567'],
        ['name' => 'Cristina Vera',   'email' => 'cristina@yazzies.com',  'password' => 'Staff@1234',    'role' => 'staff',     'phone' => '09831234567'],
    ];

    $stmtUser = $pdo->prepare("
        INSERT IGNORE INTO users (name, email, password, role, phone)
        VALUES (:name, :email, :password, :role, :phone)
    ");

    foreach ($users as $u) {
        $stmtUser->execute([
            ':name'     => $u['name'],
            ':email'    => $u['email'],
            ':password' => password_hash($u['password'], PASSWORD_BCRYPT),
            ':role'     => $u['role'],
            ':phone'    => $u['phone'],
        ]);
        echo "  ✓ {$u['role']} — {$u['name']} ({$u['email']}) / pw: {$u['password']}\n";
    }

    // -------------------------------------------------------
    // 2. MENUS
    // -------------------------------------------------------
    echo "\nSeeding menus...\n";

    $menus = [
        ['name' => 'Package A — Fiesta Buffet',     'price_per_pax' => 450.00, 'description' => 'Classic Filipino fiesta buffet with lechon, kare-kare, and pancit.'],
        ['name' => 'Package B — Ihaw-Ihaw BBQ',     'price_per_pax' => 350.00, 'description' => 'Grilled pork, chicken, and fish with rice and atchara.'],
        ['name' => 'Package C — Premium Salo-Salo', 'price_per_pax' => 600.00, 'description' => 'Premium package with seafood, beef caldereta, and leche flan.'],
        ['name' => 'Package D — Kids Party Set',    'price_per_pax' => 280.00, 'description' => 'Spaghetti, fried chicken, steamed rice, and juice.'],
    ];

    $stmtMenu = $pdo->prepare("
        INSERT IGNORE INTO menus (name, description, price_per_pax)
        VALUES (:name, :description, :price_per_pax)
    ");

    $menuIds = [];
    foreach ($menus as $m) {
        $stmtMenu->execute([':name' => $m['name'], ':description' => $m['description'], ':price_per_pax' => $m['price_per_pax']]);
        $menuIds[] = $pdo->lastInsertId();
        echo "  ✓ Menu: {$m['name']} @ ₱{$m['price_per_pax']}/pax\n";
    }

    // -------------------------------------------------------
    // 3. INGREDIENTS (recipe per pax)
    // -------------------------------------------------------
    echo "\nSeeding ingredients...\n";

    $ingredients = [
        // Package A — Fiesta Buffet (menu index 0)
        [0, 'Pork Belly (Lechon)',     0.3000, 'kg'],
        [0, 'Beef Shank (Kare-Kare)', 0.2500, 'kg'],
        [0, 'Pancit Canton (dry)',     0.1000, 'kg'],
        [0, 'Steamed Rice',           0.2000, 'kg'],
        [0, 'Puso ng Saging',         0.0500, 'kg'],
        [0, 'Bagoong Alamang',        0.0200, 'kg'],
        [0, 'Patis (Fish Sauce)',     0.0100, 'liters'],
        [0, 'Cooking Oil',            0.0300, 'liters'],
        // Package B — Ihaw-Ihaw BBQ (menu index 1)
        [1, 'Pork Liempo',            0.2500, 'kg'],
        [1, 'Chicken (cut)',          0.2000, 'kg'],
        [1, 'Tilapia (whole)',        0.1500, 'kg'],
        [1, 'Steamed Rice',           0.2000, 'kg'],
        [1, 'Atchara (jar)',          0.0300, 'kg'],
        [1, 'BBQ Marinade',           0.0500, 'liters'],
        [1, 'Charcoal',               0.2000, 'kg'],
        // Package C — Premium Salo-Salo (menu index 2)
        [2, 'Shrimp (suahe)',         0.2000, 'kg'],
        [2, 'Beef (caldereta cut)',   0.2500, 'kg'],
        [2, 'Tahong (mussels)',       0.1500, 'kg'],
        [2, 'Leche Flan (servings)',  1.0000, 'pc'],
        [2, 'Steamed Rice',           0.2000, 'kg'],
        [2, 'Coconut Cream',          0.0500, 'liters'],
        [2, 'Tomato Sauce',           0.0800, 'liters'],
        // Package D — Kids Party Set (menu index 3)
        [3, 'Spaghetti Noodles',      0.1000, 'kg'],
        [3, 'Spaghetti Sauce',        0.0800, 'liters'],
        [3, 'Chicken (drumsticks)',   0.2000, 'kg'],
        [3, 'Flour (for coating)',    0.0300, 'kg'],
        [3, 'Steamed Rice',           0.1500, 'kg'],
        [3, 'Juice Concentrate',      0.0500, 'liters'],
    ];

    $stmtIng = $pdo->prepare("
        INSERT INTO ingredients (menu_id, item_name, quantity_per_pax, unit)
        VALUES (:menu_id, :item_name, :quantity_per_pax, :unit)
    ");

    foreach ($ingredients as $ing) {
        if (!empty($menuIds[$ing[0]])) {
            $stmtIng->execute([
                ':menu_id'          => $menuIds[$ing[0]],
                ':item_name'        => $ing[1],
                ':quantity_per_pax' => $ing[2],
                ':unit'             => $ing[3],
            ]);
        }
    }
    echo "  ✓ " . count($ingredients) . " ingredients seeded across 4 menus.\n";

    // -------------------------------------------------------
    // 4. CLIENTS
    // -------------------------------------------------------
    echo "\nSeeding clients...\n";

    $clients = [
        ['name' => 'Maricel Flores',   'email' => 'maricel@gmail.com',   'phone' => '09171111111', 'address' => 'Blk 5 Lot 2, San Jose, Dasmariñas City, Cavite'],
        ['name' => 'Roberto Tan',      'email' => 'roberto@yahoo.com',   'phone' => '09282222222', 'address' => 'Phase 3, Paliparan, Dasmariñas City, Cavite'],
        ['name' => 'Evangeline Cruz',  'email' => 'evange@gmail.com',    'phone' => '09393333333', 'address' => '123 Maharlika St., Sampaloc, Dasmariñas City, Cavite'],
        ['name' => 'Dennis Soriano',   'email' => 'dennis@gmail.com',    'phone' => '09504444444', 'address' => 'Unit 4B, Grand Royale, Dasmariñas City, Cavite'],
        ['name' => 'Arceli Paglinawan','email' => 'arceli@hotmail.com',  'phone' => '09615555555', 'address' => 'Blk 12 Lot 7, Greenbreeze, Dasmariñas City, Cavite'],
    ];

    $stmtClient = $pdo->prepare("
        INSERT IGNORE INTO clients (name, email, phone, address)
        VALUES (:name, :email, :phone, :address)
    ");

    $clientIds = [];
    foreach ($clients as $c) {
        $stmtClient->execute([':name' => $c['name'], ':email' => $c['email'], ':phone' => $c['phone'], ':address' => $c['address']]);
        $clientIds[] = $pdo->lastInsertId();
        echo "  ✓ Client: {$c['name']}\n";
    }

    // -------------------------------------------------------
    // 5. BOOKINGS
    // -------------------------------------------------------
    echo "\nSeeding bookings...\n";

    // Get frontdesk user ID
    $fdUser = $pdo->query("SELECT id FROM users WHERE role='frontdesk' LIMIT 1")->fetch();
    $fdId   = $fdUser['id'] ?? 2;

    $bookings = [
        // id, client_idx, menu_idx, event_date, pax, status, pay_status
        [$clientIds[0] ?? 1, $menuIds[0] ?? 1, date('Y-m-d', strtotime('+14 days')), '11:00:00', 'Barangay Hall, St. Peter, Dasmariñas', 100, 'confirmed', 'partial', 'Birthday of Mama Maricel — 100 pax'],
        [$clientIds[1] ?? 2, $menuIds[2] ?? 3, date('Y-m-d', strtotime('+21 days')), '13:00:00', 'Casa de Roberto, Paliparan, Dasmariñas', 80,  'confirmed', 'unpaid',  'Wedding reception — kindly prepare 2 days before'],
        [$clientIds[2] ?? 3, $menuIds[1] ?? 2, date('Y-m-d', strtotime('+7 days')),  '10:00:00', 'Evange Events Hall, Sampaloc, Dasmariñas', 50, 'pending',   'unpaid',  'Awaiting downpayment for birthday party'],
        [$clientIds[3] ?? 4, $menuIds[3] ?? 4, date('Y-m-d', strtotime('+30 days')), '14:00:00', 'Grand Royale Clubhouse, Dasmariñas', 60,          'confirmed', 'paid',    'Kids birthday — Dora the Explorer theme'],
        [$clientIds[4] ?? 5, $menuIds[0] ?? 1, date('Y-m-d', strtotime('-10 days')), '10:30:00', 'St. Peter Parish Hall, Dasmariñas', 150,          'completed', 'paid',    'Fiesta celebration — completed'],
        [$clientIds[0] ?? 1, $menuIds[2] ?? 3, date('Y-m-d', strtotime('-30 days')), '12:00:00', 'Blk 5 Lot 2, San Jose, Dasmariñas', 75,           'completed', 'paid',    'Debut celebration — archived candidate'],
    ];

    $stmtBook = $pdo->prepare("
        INSERT INTO bookings
          (client_id, menu_id, event_date, event_time, event_location, pax_count,
           total_cost, booking_status, notes, created_by)
        VALUES
          (:client_id, :menu_id, :event_date, :event_time, :event_location, :pax_count,
           :total_cost, :booking_status, :notes, :created_by)
    ");

    $bookingIds = [];
    foreach ($bookings as $b) {
        $menuPrice  = $pdo->query("SELECT price_per_pax FROM menus WHERE id = {$b[1]}")->fetchColumn();
        $totalCost  = $menuPrice * $b[5];
        $stmtBook->execute([
            ':client_id'      => $b[0],
            ':menu_id'        => $b[1],
            ':event_date'     => $b[2],
            ':event_time'     => $b[3],
            ':event_location' => $b[4],
            ':pax_count'      => $b[5],
            ':total_cost'     => $totalCost,
            ':booking_status' => $b[6],
            ':notes'          => $b[8],
            ':created_by'     => $fdId,
        ]);
        $bookingIds[] = $pdo->lastInsertId();
        echo "  ✓ Booking: {$b[6]} / {$b[7]} / ₱" . number_format($totalCost, 2) . " / {$b[5]} pax\n";
    }

    // -------------------------------------------------------
    // 6. PAYMENTS
    // -------------------------------------------------------
    echo "\nSeeding payments...\n";

    $adminUser = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetch();
    $adminId   = $adminUser['id'] ?? 1;

    // Booking 0 — partial (50% down)
    $book0Cost = $pdo->query("SELECT total_cost FROM bookings WHERE id = {$bookingIds[0]}")->fetchColumn();
    $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_date, notes, recorded_by) VALUES (?, ?, 'gcash', ?, 'Downpayment 50%', ?)")
        ->execute([$bookingIds[0], round($book0Cost * 0.5, 2), date('Y-m-d', strtotime('-5 days')), $adminId]);

    // Booking 3 — fully paid (2 payments)
    $book3Cost = $pdo->query("SELECT total_cost FROM bookings WHERE id = {$bookingIds[3]}")->fetchColumn();
    $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_date, notes, recorded_by) VALUES (?, ?, 'gcash', ?, 'Downpayment', ?)")
        ->execute([$bookingIds[3], round($book3Cost * 0.5, 2), date('Y-m-d', strtotime('-15 days')), $adminId]);
    $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_date, notes, recorded_by) VALUES (?, ?, 'cash', ?, 'Full settlement before event', ?)")
        ->execute([$bookingIds[3], round($book3Cost * 0.5, 2), date('Y-m-d', strtotime('-2 days')), $adminId]);

    // Bookings 4 & 5 — fully paid (completed)
    foreach ([$bookingIds[4], $bookingIds[5]] as $bId) {
        $bCost = $pdo->query("SELECT total_cost FROM bookings WHERE id = $bId")->fetchColumn();
        $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, payment_date, notes, recorded_by) VALUES (?, ?, 'cash', ?, 'Full payment', ?)")
            ->execute([$bId, $bCost, date('Y-m-d', strtotime('-35 days')), $adminId]);
    }

    echo "  ✓ Sample payments recorded and triggers updated payment_status.\n";

    // -------------------------------------------------------
    // 7. JOB ORDERS (dispatching samples)
    // -------------------------------------------------------
    echo "\nSeeding job orders...\n";

    $staffIds = $pdo->query("SELECT id FROM users WHERE role='staff'")->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($staffIds) && !empty($bookingIds[0])) {
        $roles = ['Head Cook', 'Waiter', 'Waiter', 'Assistant Cook'];
        $statuses = ['accepted', 'accepted', 'pending', 'declined'];
        $stmtJO = $pdo->prepare("INSERT INTO job_orders (booking_id, staff_id, role_required, status, responded_at) VALUES (?, ?, ?, ?, ?)");

        foreach (array_slice($staffIds, 0, min(4, count($staffIds))) as $i => $sId) {
            $respondedAt = in_array($statuses[$i], ['accepted', 'declined']) ? date('Y-m-d H:i:s', strtotime('-1 day')) : null;
            $stmtJO->execute([$bookingIds[0], $sId, $roles[$i], $statuses[$i], $respondedAt]);
        }
        echo "  ✓ Job orders seeded for Booking #1.\n";
    }

    // -------------------------------------------------------
    echo "\n=== SEED COMPLETE ===\n\n";
    echo "Test Login Credentials:\n";
    echo "  [Admin]     admin@yazzies.com     / Admin@1234\n";
    echo "  [Frontdesk] frontdesk@yazzies.com / Frontdesk@123\n";
    echo "  [Staff]     ramon@yazzies.com     / Staff@1234\n";
    echo "</pre>";

} catch (\PDOException $e) {
    echo "<b style='color:red;'>DB Error: " . htmlspecialchars($e->getMessage()) . "</b>";
    echo "</pre>";
}
