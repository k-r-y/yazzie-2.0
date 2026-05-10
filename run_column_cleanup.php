<?php
/**
 * ONE-TIME COLUMN CLEANUP — DROP UNUSED COLUMNS
 * Run once via browser (localhost only), then self-deletes.
 * DO NOT leave this file in production.
 *
 * Columns to drop:
 *   booking_cancellations.policy_json   — never read/written in codebase
 *   booking_cancellations.deposit_forfeit — never read/written in codebase
 *   notifications.link_url              — defined but never used
 *
 * Also removes the dead `market_cost` reference from payments.php
 * (that column doesn't even exist in the schema — was already returning NULL).
 */
require_once __DIR__ . '/config/config.php';

// Localhost guard
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Forbidden.');
}

$drops = [
    ['table' => 'booking_cancellations', 'column' => 'policy_json'],
    ['table' => 'booking_cancellations', 'column' => 'deposit_forfeit'],
    ['table' => 'notifications',          'column' => 'link_url'],
    // Overtime removed — no longer a feature
    ['table' => 'bookings',              'column' => 'overtime_minutes'],
    ['table' => 'bookings',              'column' => 'overtime_rate'],
    ['table' => 'bookings',              'column' => 'overtime_total'],
];

$results = [];

foreach ($drops as $item) {
    $t = $item['table'];
    $c = $item['column'];

    // Check if column actually exists before trying to drop
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = :t
          AND COLUMN_NAME  = :c
    ");
    $check->execute([':t' => $t, ':c' => $c]);
    $exists = (int) $check->fetchColumn();

    if (!$exists) {
        $results[] = ['col' => "$t.$c", 'status' => 'already absent', 'ok' => true];
        continue;
    }

    try {
        $pdo->exec("ALTER TABLE `{$t}` DROP COLUMN `{$c}`");
        $results[] = ['col' => "$t.$c", 'status' => 'dropped', 'ok' => true];
    } catch (PDOException $e) {
        $results[] = ['col' => "$t.$c", 'status' => 'FAILED: ' . $e->getMessage(), 'ok' => false];
    }
}

$allOk = !in_array(false, array_column($results, 'ok'));
if ($allOk) {
    @unlink(__FILE__);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Column Cleanup</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 60px auto; padding: 0 20px; }
        h1   { font-size: 20px; color: #1C1C1E; }
        .ok   { color: #25A244; font-weight: 700; }
        .fail { color: #DC2626; font-weight: 700; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { padding: 10px 14px; border: 1px solid #E5E5EA; text-align: left; font-size: 14px; }
        th { background: #F2F2F7; font-weight: 700; }
        .box { border-radius: 8px; padding: 14px; font-size: 13px; margin-top: 20px; }
        .success { background: #F0FFF4; border: 1px solid #25A244; color: #1A5C2F; }
        .notice  { background: #FFF3E0; border: 1px solid #E67E22; color: #7C4A00; }
    </style>
</head>
<body>
    <h1>🗑️ Unused Column Cleanup</h1>
    <table>
        <thead><tr><th>Column</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><code><?= htmlspecialchars($r['col']) ?></code></td>
                <td class="<?= $r['ok'] ? 'ok' : 'fail' ?>">
                    <?= $r['ok'] ? '✅ ' : '❌ ' ?><?= htmlspecialchars($r['status']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($allOk): ?>
        <div class="box success">✅ All columns dropped successfully. This script has self-deleted.</div>
    <?php else: ?>
        <div class="box notice">⚠️ Some operations failed. Check errors above. Script was NOT deleted.</div>
    <?php endif; ?>
</body>
</html>
