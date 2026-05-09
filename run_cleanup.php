<?php
/**
 * ONE-TIME CLEANUP SCRIPT — DROP ORPHANED TABLES
 * Run this once via browser, then it self-deletes.
 * DO NOT leave this file in production.
 */
require_once __DIR__ . '/config/config.php';

// Simple IP guard — only allow localhost
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('Forbidden.');
}

$tables = [
    'taste_testing',
    'taste_test_feedback',
    'taste_test_appointments',
    'dish_ingredients',
    'booking_staff',
];

$results = [];

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    foreach ($tables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            $results[] = ['table' => $table, 'status' => 'dropped', 'ok' => true];
        } catch (PDOException $e) {
            $results[] = ['table' => $table, 'status' => 'FAILED: ' . $e->getMessage(), 'ok' => false];
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    // Verify remaining tables
    $remaining = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    die("Fatal DB error: " . htmlspecialchars($e->getMessage()));
}

// Self-delete after success
$allOk = !in_array(false, array_column($results, 'ok'));
if ($allOk) {
    @unlink(__FILE__);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orphaned Table Cleanup</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 60px auto; padding: 0 20px; }
        h1 { font-size: 22px; color: #1C1C1E; }
        .ok   { color: #25A244; font-weight: 700; }
        .fail { color: #DC2626; font-weight: 700; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { padding: 10px 14px; border: 1px solid #E5E5EA; text-align: left; font-size: 14px; }
        th { background: #F2F2F7; font-weight: 700; }
        .notice { background: #FFF3E0; border: 1px solid #E67E22; border-radius: 8px; padding: 14px; font-size: 13px; color: #7C4A00; margin-top: 20px; }
        .success { background: #F0FFF4; border: 1px solid #25A244; border-radius: 8px; padding: 14px; font-size: 13px; color: #1A5C2F; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>🗑️ Orphaned Table Cleanup</h1>
    <table>
        <thead><tr><th>Table</th><th>Result</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><code><?= htmlspecialchars($r['table']) ?></code></td>
                <td class="<?= $r['ok'] ? 'ok' : 'fail' ?>">
                    <?= $r['ok'] ? '✅ ' : '❌ ' ?><?= htmlspecialchars($r['status']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p><strong>Remaining tables in <code>yazzie</code>:</strong></p>
    <ul>
        <?php foreach ($remaining as $t): ?>
            <li><code><?= htmlspecialchars($t) ?></code></li>
        <?php endforeach; ?>
    </ul>

    <?php if ($allOk): ?>
        <div class="success">✅ All orphaned tables dropped successfully. This script has self-deleted.</div>
    <?php else: ?>
        <div class="notice">⚠️ Some tables failed to drop. Check errors above. This script was NOT deleted.</div>
    <?php endif; ?>
</body>
</html>
