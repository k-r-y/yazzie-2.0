<?php
/**
 * Database Backup Utility
 * Generates and downloads a SQL dump of the entire database.
 * Strictly Admin only.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/audit.php';

requireApiRole('admin');

$dbHost = DB_HOST;
$dbName = DB_NAME;
$dbUser = DB_USER;
$dbPass = DB_PASS;

$filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_His') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Use mysqldump if available, with fallback for XAMPP on macOS
$mysqldump = '/Applications/XAMPP/xamppfiles/bin/mysqldump';
if (!file_exists($mysqldump)) {
    $mysqldump = 'mysqldump';
}

$cmd = sprintf(
    '%s -h %s -u %s %s %s',
    $mysqldump,
    escapeshellarg($dbHost),
    escapeshellarg($dbUser),
    $dbPass ? '-p' . escapeshellarg($dbPass) : '',
    escapeshellarg($dbName)
);

// Execute and stream directly to output
passthru($cmd, $returnVar);

// Log activity (after headers have been sent so we can't redirect, but that's fine for downloads)
if ($returnVar === 0) {
    auditLog($pdo, 'database_backup', 'system', 0, [], ['filename' => $filename]);
} else {
    // If mysqldump fails, output an error in the SQL file
    echo "\n-- ERROR: mysqldump command failed. Check if mysqldump is in PATH and accessible.\n";
}
exit;
