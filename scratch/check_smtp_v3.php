<?php
require_once __DIR__ . '/../config/config.php';
$stmt = $pdo->query("SELECT setting_key, setting_value FROM app_settings WHERE setting_key LIKE 'smtp_%'");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($settings as $k => $v) {
    echo "$k: " . ($k === 'smtp_pass' ? '[HIDDEN]' : $v) . "\n";
}
echo "Constants:\n";
echo "MAIL_USERNAME: " . MAIL_USERNAME . "\n";
echo "MAIL_PASSWORD: [HIDDEN]\n";
