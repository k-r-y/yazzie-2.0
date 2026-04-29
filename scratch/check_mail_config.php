<?php
require_once __DIR__ . '/../config/config.php';
echo "MAIL_ENABLED (final): " . (MAIL_ENABLED ? 'TRUE' : 'FALSE') . "\n";
echo "DB mail_enabled: " . var_export(appSetting('mail_enabled'), true) . "\n";
echo "ENV mail_enabled: " . var_export(getenv('MAIL_ENABLED'), true) . "\n";
echo "SMTP Host: " . MAIL_HOST . "\n";
echo "SMTP User: " . MAIL_USERNAME . "\n";
echo "SMTP Pass: " . (empty(MAIL_PASSWORD) ? 'EMPTY' : 'SET') . "\n";
