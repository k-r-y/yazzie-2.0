<?php
/**
 * ============================================================
 * Yazzies Catering OMS — Lightweight PHP Migration System
 * ============================================================
 *
 * Usage:
 *   CLI: php tools/migrate.php [--dry-run] [--rollback]
 *   Web: http://localhost/test/tools/migrate.php
 *        (Restricted to Super Admin session or CLI only)
 *
 * Conventions:
 *   - Migration files live in /database/migrations/
 *   - Filename format: YYYY_MM_DD_HHMMSS_description.sql
 *   - Files are executed in strict alphabetical (chronological) order
 *   - Each file is executed atomically inside a transaction
 * ============================================================
 */

define('MIGRATE_VERSION', '1.0.0');

// ── Guard: Restrict browser access to Super Admins only ──────
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    ob_start();
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/auth.php';
    $currentUser = currentUser();
    if (!$currentUser || $currentUser['role'] !== 'super_admin') {
        http_response_code(403);
        die('<pre style="font-family:monospace; padding:20px; color:#C0392B;">403 Forbidden — Super Admin access required.</pre>');
    }
} else {
    require_once __DIR__ . '/../config/config.php';
}

// ── Parse CLI flags ───────────────────────────────────────────
$dryRun  = in_array('--dry-run',  $argv ?? []);
$verbose = in_array('--verbose',  $argv ?? []) || !$isCli;

// ── Output Helper ─────────────────────────────────────────────
function out(string $msg, string $level = 'info'): void {
    global $isCli;
    $colors = ['info' => "\033[0m", 'ok' => "\033[32m", 'warn' => "\033[33m", 'error' => "\033[31m", 'bold' => "\033[1m"];
    $reset  = "\033[0m";
    $html   = ['info' => '', 'ok' => 'color:#27AE60;', 'warn' => 'color:#E67E22;', 'error' => 'color:#C0392B; font-weight:bold;', 'bold' => 'font-weight:bold;'];

    if ($isCli) {
        echo ($colors[$level] ?? '') . $msg . $reset . "\n";
    } else {
        echo '<div style="font-family:monospace; font-size:13px; padding:2px 0; ' . ($html[$level] ?? '') . '">' . htmlspecialchars($msg) . '</div>';
    }
}

// ── Bootstrap the migrations tracking table ───────────────────
function bootstrapMigrationsTable(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS migrations (
            id           INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
            filename     VARCHAR(255)    NOT NULL UNIQUE,
            batch        INT UNSIGNED    NOT NULL DEFAULT 1,
            applied_at   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
            checksum     VARCHAR(64)     NOT NULL COMMENT 'SHA-256 of the SQL file at time of execution',
            INDEX idx_batch (batch)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
          COMMENT='Tracks applied database migrations.'
    ");
}

// ── Get the set of already-applied migrations ─────────────────
function getAppliedMigrations(PDO $pdo): array {
    $stmt = $pdo->query("SELECT filename FROM migrations ORDER BY filename ASC");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ── Get the current max batch number ─────────────────────────
function getCurrentBatch(PDO $pdo): int {
    $stmt = $pdo->query("SELECT COALESCE(MAX(batch), 0) FROM migrations");
    return (int) $stmt->fetchColumn();
}

// ── Scan disk for migration files ─────────────────────────────
function getPendingMigrations(string $dir, array $applied): array {
    if (!is_dir($dir)) {
        out("Migration directory not found: $dir", 'error');
        exit(1);
    }
    $files = glob($dir . '/*.sql');
    if ($files === false) return [];
    sort($files); // Alphabetical = chronological by naming convention
    return array_filter($files, fn($f) => !in_array(basename($f), $applied));
}

// ── Execute a single migration file ───────────────────────────
function runMigration(PDO $pdo, string $filepath, int $batch, bool $dryRun): bool {
    $filename = basename($filepath);
    $sql      = file_get_contents($filepath);
    $checksum = hash('sha256', $sql);

    if (empty(trim($sql))) {
        out("  → SKIPPED (empty file): $filename", 'warn');
        return true;
    }

    out("  → Running: $filename", 'info');

    if ($dryRun) {
        out("    [DRY RUN] Would execute " . strlen($sql) . " bytes — batch #$batch", 'warn');
        return true;
    }

    try {
        $pdo->beginTransaction();

        // Split on semicolons to handle multi-statement files, filtering empties
        $statements = array_filter(
            array_map('trim', explode(';', $sql)),
            fn($s) => !empty($s)
        );

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        // Record the successful migration
        $stmt = $pdo->prepare("
            INSERT INTO migrations (filename, batch, checksum)
            VALUES (:filename, :batch, :checksum)
        ");
        $stmt->execute([':filename' => $filename, ':batch' => $batch, ':checksum' => $checksum]);

        $pdo->commit();
        out("    ✓ Applied successfully (batch #$batch, sha256: " . substr($checksum, 0, 8) . "…)", 'ok');
        return true;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        out("    ✗ FAILED: " . $e->getMessage(), 'error');
        return false;
    }
}

// ── Main Execution ────────────────────────────────────────────
if (!$isCli) {
    echo '<html><head><title>DB Migration — Yazzies OMS</title>';
    echo '<meta charset="UTF-8"><style>body{background:#1a1a2e;color:#e0e0e0;padding:30px;}h2{color:#4fc3f7;border-bottom:1px solid #333;padding-bottom:10px;}</style>';
    echo '</head><body><h2>🚀 Yazzies OMS — Database Migration Runner v' . MIGRATE_VERSION . '</h2><pre style="background:#111;padding:20px;border-radius:8px;font-size:13px;">';
}

out("╔══════════════════════════════════════════════════════════╗", 'bold');
out("║  Yazzies OMS — Database Migration Runner v" . MIGRATE_VERSION . "         ║", 'bold');
out("╚══════════════════════════════════════════════════════════╝", 'bold');
out($dryRun ? "★ DRY RUN MODE — No changes will be committed." : "★ LIVE MODE — Changes will be committed.", $dryRun ? 'warn' : 'info');
out("");

$migrationsDir = __DIR__ . '/../database/migrations';

try {
    // 1. Ensure the tracking table exists
    bootstrapMigrationsTable($pdo);
    out("✓ Migration tracking table ready.", 'ok');

    // 2. Fetch applied and pending migrations
    $applied = getAppliedMigrations($pdo);
    $pending = getPendingMigrations($migrationsDir, $applied);

    out("✓ Already applied: " . count($applied) . " migration(s).", 'info');
    out("✓ Pending:         " . count($pending) . " migration(s).", count($pending) > 0 ? 'warn' : 'ok');
    out("");

    if (empty($pending)) {
        out("✅ Database is up to date. Nothing to migrate.", 'ok');
    } else {
        $batch     = getCurrentBatch($pdo) + 1;
        $succeeded = 0;
        $failed    = 0;

        out("Running batch #$batch …", 'bold');
        out(str_repeat('─', 58), 'info');

        foreach ($pending as $filepath) {
            $ok = runMigration($pdo, $filepath, $batch, $dryRun);
            $ok ? $succeeded++ : $failed++;

            // Stop on first failure to preserve database consistency
            if (!$ok) {
                out("", 'info');
                out("⛔ Migration halted after failure. Subsequent files were NOT executed.", 'error');
                out("   Fix the issue and re-run the migrator.", 'error');
                break;
            }
        }

        out(str_repeat('─', 58), 'info');
        out("");
        out("Summary:", 'bold');
        out("  Succeeded : $succeeded", 'ok');
        if ($failed > 0) out("  Failed    : $failed", 'error');
        out("  Batch     : #$batch" . ($dryRun ? ' (dry run — not recorded)' : ''), 'info');
    }

} catch (Throwable $e) {
    out("FATAL: " . $e->getMessage(), 'error');
    if (!$isCli) echo '</pre></body></html>';
    exit(1);
}

out("");
out("Done.", 'bold');

if (!$isCli) echo '</pre></body></html>';
exit(0);
