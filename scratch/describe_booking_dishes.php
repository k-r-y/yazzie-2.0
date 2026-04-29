<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("DESCRIBE booking_dishes");
$cols = $stmt->fetchAll();
foreach ($cols as $c) {
    echo $c['Field'] . " (" . $c['Type'] . ")\n";
}
