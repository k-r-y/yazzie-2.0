<?php
require_once __DIR__ . '/config/config.php';

try {
    echo "<h1>Consolidating Redundant Settings...</h1>";

    // 1. Check if min_dp_percent exists
    $stmt = $pdo->prepare("SELECT * FROM settings WHERE `key` = 'min_dp_percent'");
    $stmt->execute();
    $minDp = $stmt->fetch();

    if ($minDp) {
        echo "<p>Found redundant 'min_dp_percent' with value: " . $minDp['value'] . "</p>";
        
        // 2. Ensure standard_dp_percent has the correct value if min_dp_percent was intended to be the rule
        // For now, we assume standard_dp_percent is the master key.
        
        // 3. Delete the redundant key
        $pdo->exec("DELETE FROM settings WHERE `key` = 'min_dp_percent'");
        echo "<p style='color:green;'>✓ SUCCESS: 'min_dp_percent' deleted. System will now use 'standard_dp_percent' exclusively.</p>";
    } else {
        echo "<p>No redundant 'min_dp_percent' found in database.</p>";
    }

    // 4. Update descriptions for clarity
    $pdo->exec("UPDATE settings SET description = 'Standard downpayment percentage (e.g., 0.30 for 30%)' WHERE `key` = 'standard_dp_percent'");
    $pdo->exec("UPDATE settings SET description = 'Rush downpayment percentage (e.g., 1.00 for 100%)' WHERE `key` = 'rush_dp_percent'");
    
    echo "<h3>Cleanup Complete!</h3>";

} catch (Exception $e) {
    echo "<h3 style='color:red;'>CLEANUP FAILED: " . htmlspecialchars($e->getMessage()) . "</h3>";
}
