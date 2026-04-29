<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "TABLES:\n" . implode("\n", $tables) . "\n";
