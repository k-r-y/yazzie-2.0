<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'smtp_%'");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
print_r($settings);
echo "\nMAIL_ENABLED: " . (MAIL_ENABLED ? 'true' : 'false') . "\n";
echo "MAIL_USERNAME: " . MAIL_USERNAME . "\n";
echo "MAIL_PASSWORD length: " . strlen(MAIL_PASSWORD) . "\n";
