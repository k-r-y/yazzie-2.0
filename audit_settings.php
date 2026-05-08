<?php
require_once __DIR__ . '/config/config.php';
$rows = $pdo->query("SELECT * FROM settings ORDER BY `key` ASC")->fetchAll();
echo "<h1>System Settings Audit</h1>";
echo "<table border='1' cellpadding='10' style='border-collapse:collapse; font-family:sans-serif;'>";
echo "<tr><th>Key</th><th>Value</th><th>Type</th><th>Category</th></tr>";
foreach ($rows as $r) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($r['key']) . "</td>";
    echo "<td>" . htmlspecialchars($r['value']) . "</td>";
    echo "<td>" . htmlspecialchars($r['type']) . "</td>";
    echo "<td>" . htmlspecialchars($r['category'] ?? 'N/A') . "</td>";
    echo "</tr>";
}
echo "</table>";
