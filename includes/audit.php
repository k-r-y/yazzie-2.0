<?php
/**
 * Audit Log Helper
 * Writes a row to audit_log for any create/update/delete of financial or booking data.
 * 
 * Usage:
 *   auditLog($pdo, 'payment_recorded', 'payment', $paymentId,
 *             null, ['amount' => 5000, 'method' => 'gcash']);
 */
function auditLog(
    PDO    $pdo,
    string $action,
    string $entity,
    int    $entityId,
    ?array $oldValue = null,
    ?array $newValue = null
): void {
    try {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $ip     = $_SERVER['HTTP_X_FORWARDED_FOR']
                  ?? $_SERVER['REMOTE_ADDR']
                  ?? null;

        $pdo->prepare("
            INSERT INTO audit_log (user_id, action, entity, entity_id, old_value, new_value, ip_address)
            VALUES (:uid, :action, :entity, :eid, :old, :new, :ip)
        ")->execute([
            ':uid'    => $userId,
            ':action' => $action,
            ':entity' => $entity,
            ':eid'    => $entityId,
            ':old'    => $oldValue !== null ? json_encode($oldValue) : null,
            ':new'    => $newValue !== null ? json_encode($newValue)  : null,
            ':ip'     => $ip,
        ]);
    } catch (Throwable $e) {
        // Audit failures must NEVER break the main request flow
        error_log('[AuditLog] Failed: ' . $e->getMessage());
    }
}
