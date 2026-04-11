<?php
/**
 * Database Diagnostics & Auto-Setup
 * Run this ONCE to: check DB connection, create tables, and seed data.
 * Access: http://localhost/test/database/setup.php
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: text/html; charset=utf-8');

function ok($msg)  { echo "<div style='color:#1A7A32;margin:4px 0'>✅ $msg</div>"; }
function err($msg) { echo "<div style='color:#C0392B;margin:4px 0'>❌ $msg</div>"; }
function info($msg){ echo "<div style='color:#0040A3;margin:4px 0'>ℹ️  $msg</div>"; }
function h($t)     { echo "<h3 style='margin:20px 0 8px;border-bottom:1px solid #eee;padding-bottom:6px'>$t</h3>"; }
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Yazzies — DB Setup</title>
<style>
  body { font-family: -apple-system,sans-serif; max-width:720px; margin:40px auto; padding:0 20px; font-size:14px; }
  h2 { font-size:20px; }
  pre { background:#F2F2F7; padding:12px; border-radius:8px; font-size:12px; overflow-x:auto; }
  .btn { display:inline-block; padding:10px 20px; border-radius:10px; background:#30D158; color:#fff; font-weight:700; text-decoration:none; margin-top:16px; cursor:pointer; border:none; font-size:14px; }
  .btn.danger { background:#FF3B30; }
</style>
</head>
<body>
<h2>🛠️ Yazzies OMS — Database Setup</h2>

<?php

h('1. Database Connection');
try {
    // Test the connection
    $pdo->query('SELECT 1');
    ok("Connected to MySQL: <code>" . DB_HOST . " / " . DB_NAME . "</code>");
} catch (Exception $e) {
    err("Connection failed: " . $e->getMessage());
    echo "<p>Check your <code>config/config.php</code> DB credentials.</p>";
    exit;
}

h('2. Table Status');
$requiredTables = ['users', 'clients', 'menus', 'ingredients', 'bookings', 'payments', 'job_orders', 'archived_bookings'];
$existingTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$missing = [];

foreach ($requiredTables as $t) {
    if (in_array($t, $existingTables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();
        ok("Table <code>$t</code> exists — <strong>$count rows</strong>");
    } else {
        err("Table <code>$t</code> is MISSING");
        $missing[] = $t;
    }
}

h('3. Trigger Status');
$triggers = $pdo->query("SHOW TRIGGERS FROM `" . DB_NAME . "`")->fetchAll(PDO::FETCH_COLUMN);
$requiredTriggers = ['after_payment_insert', 'after_payment_delete', 'after_payment_update'];
foreach ($requiredTriggers as $tr) {
    if (in_array($tr, $triggers)) {
        ok("Trigger <code>$tr</code> exists");
    } else {
        err("Trigger <code>$tr</code> is MISSING");
    }
}

// ── AUTO INSTALL ────────────────────────────────────────────────
if (isset($_GET['install']) || !empty($missing)) {

    h('4. Running Schema Import…');

    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        err("schema.sql not found at: $schemaFile");
    } else {
        $sql = file_get_contents($schemaFile);

        // Split on DELIMITER changes for triggers
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Split into individual statements (handle DELIMITER $$)
        $sql = preg_replace('/DELIMITER\s+\$\$/', '', $sql);
        $sql = preg_replace('/DELIMITER\s+;/',    '', $sql);
        $sql = str_replace('$$', ';', $sql);

        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => strlen($s) > 3
        );

        $ok = 0; $fail = 0;
        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
                $ok++;
            } catch (PDOException $e) {
                // Ignore "already exists" type warnings
                if (strpos($e->getMessage(), 'already exists') === false) {
                    err("SQL error: " . htmlspecialchars($e->getMessage()));
                    err("Statement: <pre>" . htmlspecialchars(substr($stmt, 0, 200)) . "</pre>");
                    $fail++;
                }
            }
        }

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        ok("Schema applied — $ok statements OK, $fail errors");
    }

    // ── SEED DATA ───────────────────────────────────────────────
    h('5. Seeding Default Users…');

    $existingAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE email='admin@yazzies.com'")->fetchColumn();
    if ($existingAdmin > 0) {
        info("Admin user already exists — skipping seed");
    } else {
        $users = [
            ['Yasmin Reyes',    'admin@yazzies.com',      'Admin@1234',      'admin'],
            ['Maria Santos',    'frontdesk@yazzies.com',  'Frontdesk@123',   'frontdesk'],
            ['Ramon Dela Cruz', 'ramon@yazzies.com',      'Staff@1234',      'staff'],
            ['Ana Lim',         'ana@yazzies.com',        'Staff@1234',      'staff'],
        ];
        $s = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (:n,:e,:p,:r)");
        foreach ($users as [$n,$e,$pw,$r]) {
            try {
                $s->execute([':n'=>$n,':e'=>$e,':p'=>password_hash($pw, PASSWORD_BCRYPT),':r'=>$r]);
                ok("User created: <strong>$e</strong> ($r)");
            } catch (Exception $ex) {
                info("User $e may already exist: " . $ex->getMessage());
            }
        }
    }

    h('5b. Seeding Clients…');
    $clientCount = $pdo->query("SELECT COUNT(*) FROM clients")->fetchColumn();
    if ($clientCount > 0) {
        info("Clients already seeded ($clientCount rows)");
    } else {
        $clients = [
            ['Santos Family',    'santos@gmail.com',    '09171234567', 'Dasmariñas City, Cavite'],
            ['Reyes-Cruz Wedding','reyes@gmail.com',    '09181234567', 'Imus, Cavite'],
            ['Lim Corporation',  'lim@company.com',     '09191234567', 'Bacoor, Cavite'],
            ['Dela Torre Family','delatorre@gmail.com', '09201234567', 'GMA, Cavite'],
        ];
        $s = $pdo->prepare("INSERT INTO clients (name,email,phone,address) VALUES (:n,:e,:p,:a)");
        foreach ($clients as [$n,$e,$p,$a]) {
            $s->execute([':n'=>$n,':e'=>$e,':p'=>$p,':a'=>$a]);
            ok("Client: <strong>$n</strong>");
        }
    }

    h('5c. Seeding Menu Packages…');
    $menuCount = $pdo->query("SELECT COUNT(*) FROM menus")->fetchColumn();
    if ($menuCount > 0) {
        info("Menus already seeded ($menuCount rows)");
    } else {
        $menus = [
            ['Budget Package',   'Simple buffet for small events',           350.00],
            ['Standard Package', '5-course buffet with drinks',              550.00],
            ['Premium Package',  'Full-service 7-course with live station',  850.00],
            ['Corporate Package','Lunch/dinner for corporate events',        650.00],
        ];
        $s = $pdo->prepare("INSERT INTO menus (name,description,price_per_pax) VALUES (:n,:d,:p)");
        foreach ($menus as [$n,$d,$p]) {
            $s->execute([':n'=>$n,':d'=>$d,':p'=>$p]);
            $mid = $pdo->lastInsertId();
            ok("Menu: <strong>$n</strong> (₱$p/pax)");

            // Add sample ingredients per menu
            $ingredients = [
                ['Rice',       0.25, 'kg'],
                ['Chicken',    0.30, 'kg'],
                ['Vegetables', 0.15, 'kg'],
                ['Cooking Oil',0.05, 'L'],
                ['Salt',       0.005,'kg'],
            ];
            $si = $pdo->prepare("INSERT INTO ingredients (menu_id,item_name,quantity_per_pax,unit) VALUES (:mid,:item,:qty,:unit)");
            foreach ($ingredients as [$item,$qty,$unit]) {
                $si->execute([':mid'=>$mid,':item'=>$item,':qty'=>$qty,':unit'=>$unit]);
            }
        }
    }

    h('5d. Seeding Sample Bookings…');
    $bookingCount = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    if ($bookingCount > 0) {
        info("Bookings already seeded ($bookingCount rows)");
    } else {
        $adminId  = $pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
        $clientId = $pdo->query("SELECT id FROM clients LIMIT 1")->fetchColumn();
        $menuId   = $pdo->query("SELECT id FROM menus WHERE name='Standard Package'")->fetchColumn();

        if ($adminId && $clientId && $menuId) {
            $bookings = [
                [$clientId, $menuId, date('Y-m-d', strtotime('+7 days')),  '10:00:00', 'Imus, Cavite',       50,  'confirmed'],
                [$clientId, $menuId, date('Y-m-d', strtotime('+14 days')), '18:00:00', 'Dasmariñas, Cavite', 80,  'confirmed'],
                [$clientId, $menuId, date('Y-m-d', strtotime('-7 days')),  '10:00:00', 'GMA, Cavite',        30,  'completed'],
            ];
            $s = $pdo->prepare("INSERT INTO bookings (client_id,menu_id,event_date,event_time,event_location,pax_count,total_cost,booking_status,created_by) VALUES (:c,:m,:ed,:et,:el,:px,:tc,:bs,:cb)");
            foreach ($bookings as [$c,$m,$ed,$et,$el,$px,$bs]) {
                $price = $pdo->prepare("SELECT price_per_pax FROM menus WHERE id=:id");
                $price->execute([':id'=>$m]);
                $tc = $price->fetchColumn() * $px;
                $s->execute([':c'=>$c,':m'=>$m,':ed'=>$ed,':et'=>$et,':el'=>$el,':px'=>$px,':tc'=>$tc,':bs'=>$bs,':cb'=>$adminId]);
                ok("Booking: $el — $px pax — ₱$tc");
            }
        } else {
            err("Could not seed bookings: missing admin/client/menu IDs");
        }
    }

    h('✅ Setup Complete!');
    echo '<p>All tables and seed data are ready. You can now use the system.</p>';
}

?>

<hr style="margin:24px 0; border-color:#eee">

<?php if (!empty($missing) || isset($_GET['install'])): ?>
    <a href="<?= BASE_URL ?>/index.php" class="btn">→ Go to Login</a>
<?php else: ?>
    <a href="?install=1" class="btn">🔄 Re-run Full Install (drops + recreates tables)</a>
    <a href="<?= BASE_URL ?>/index.php" class="btn" style="margin-left:10px">→ Go to Login</a>
<?php endif; ?>

<p style="color:#8E8E93; margin-top:24px; font-size:12px">
    Delete or restrict access to this file on a production server.
</p>

</body>
</html>
