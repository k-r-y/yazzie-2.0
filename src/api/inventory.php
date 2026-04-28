<?php
/**
 * Inventory (Equipment) API
 * GET    — List all equipment
 * POST   — Add equipment (Admin)
 * PUT    — Update equipment (Admin)
 * DELETE — Toggle active status (Admin)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

requireApiRole(['admin', 'frontdesk', 'staff']);
requireCsrf();
$method = $_SERVER['REQUEST_METHOD'];

// ────────────────────────────────────────────────────────────────
// GET — List equipment
// ────────────────────────────────────────────────────────────────
if ($method === 'GET') {
    $showAll = !empty($_GET['all']);
    $where   = $showAll ? '1=1' : 'is_active = 1';
    
    $stmt = $pdo->query("SELECT * FROM equipment WHERE $where ORDER BY name ASC");
    jsonResponse(true, '', ['equipment' => $stmt->fetchAll()]);
}

// ────────────────────────────────────────────────────────────────
// POST — Add equipment (Admin only)
// ────────────────────────────────────────────────────────────────
if ($method === 'POST') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (empty($d['name']))             jsonResponse(false, 'Name is required.', [], 422);
    if (!isset($d['replacement_cost'])) jsonResponse(false, 'Replacement cost is required.', [], 422);

    $stmt = $pdo->prepare("
        INSERT INTO equipment (name, category, unit, replacement_cost, total_stock, current_stock, is_active)
        VALUES (:name, :category, :unit, :cost, :total_stock, :current_stock, :active)
    ");
    $stmt->execute([
        ':name'          => trim(substr($d['name'], 0, 100)),
        ':category'      => trim(substr($d['category'] ?? 'General', 0, 50)),
        ':unit'          => trim(substr($d['unit'] ?? 'pcs', 0, 20)),
        ':cost'          => max(0, (float)$d['replacement_cost']),
        ':total_stock'   => max(0, (int)($d['total_stock'] ?? 0)),
        ':current_stock' => max(0, (int)($d['total_stock'] ?? 0)), // Initializes current to total
        ':active'        => isset($d['is_active']) ? (int)$d['is_active'] : 1
    ]);

    jsonResponse(true, 'Equipment added.', ['id' => $pdo->lastInsertId()], 201);
}

// ────────────────────────────────────────────────────────────────
// PUT — Update equipment (Admin only)
// ────────────────────────────────────────────────────────────────
if ($method === 'PUT') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    
    if (empty($d['id']))               jsonResponse(false, 'ID is required.', [], 422);
    if (empty($d['name']))             jsonResponse(false, 'Name is required.', [], 422);
    if (!isset($d['replacement_cost'])) jsonResponse(false, 'Replacement cost is required.', [], 422);

    $stmt = $pdo->prepare("
        UPDATE equipment SET
            name = :name,
            category = :category,
            unit = :unit,
            replacement_cost = :cost,
            current_stock = current_stock + (:total_stock_adj - total_stock),
            total_stock = :total_stock,
            is_active = :active
        WHERE id = :id
    ");
    
    $totalStock = max(0, (int)($d['total_stock'] ?? 0));
    
    $stmt->execute([
        ':id'              => (int)$d['id'],
        ':name'            => trim($d['name']),
        ':category'        => trim(substr($d['category'] ?? 'General', 0, 50)),
        ':unit'            => $d['unit'] ?? 'pcs',
        ':cost'            => (float)$d['replacement_cost'],
        ':total_stock'     => $totalStock,
        ':total_stock_adj' => $totalStock,
        ':active'          => (int)$d['is_active']
    ]);

    jsonResponse(true, 'Equipment updated.');
}

// ────────────────────────────────────────────────────────────────
// DELETE — Toggle status (Admin only)
// ────────────────────────────────────────────────────────────────
if ($method === 'DELETE') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'ID is required.', [], 422);

    $stmt = $pdo->prepare("UPDATE equipment SET is_active = IF(is_active=1, 0, 1) WHERE id = :id");
    $stmt->execute([':id' => (int)$d['id']]);

    jsonResponse(true, 'Equipment status toggled.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
