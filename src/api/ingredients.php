<?php
/**
 * Ingredients API (recipe management per menu)
 * GET    ?menu_id=X — list ingredients for a menu
 * POST   — add ingredient to menu
 * PUT    — update ingredient
 * DELETE — remove ingredient
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$user   = requireApiRole(['admin', 'frontdesk']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (empty($_GET['menu_id'])) jsonResponse(false, 'menu_id is required.', [], 422);
    $stmt = $pdo->prepare("
        SELECT * FROM ingredients WHERE menu_id = :mid ORDER BY item_name ASC
    ");
    $stmt->execute([':mid' => (int)$_GET['menu_id']]);
    jsonResponse(true, '', ['ingredients' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    $required = ['menu_id', 'item_name', 'quantity_per_pax', 'unit'];
    foreach ($required as $f) {
        if (empty($d[$f])) jsonResponse(false, "Field '$f' is required.", [], 422);
    }
    $stmt = $pdo->prepare("
        INSERT INTO ingredients (menu_id, item_name, quantity_per_pax, unit)
        VALUES (:menu_id, :item_name, :qty, :unit)
    ");
    $stmt->execute([
        ':menu_id'   => (int)$d['menu_id'],
        ':item_name' => trim($d['item_name']),
        ':qty'       => (float)$d['quantity_per_pax'],
        ':unit'      => trim($d['unit']),
    ]);
    jsonResponse(true, 'Ingredient added.', ['id' => $pdo->lastInsertId()], 201);
}

if ($method === 'PUT') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Ingredient ID required.', [], 422);
    $stmt = $pdo->prepare("
        UPDATE ingredients SET
            item_name        = COALESCE(:item_name, item_name),
            quantity_per_pax = COALESCE(:qty, quantity_per_pax),
            unit             = COALESCE(:unit, unit)
        WHERE id = :id
    ");
    $stmt->execute([
        ':id'        => (int)$d['id'],
        ':item_name' => $d['item_name']        ?? null,
        ':qty'       => $d['quantity_per_pax'] ?? null,
        ':unit'      => $d['unit']             ?? null,
    ]);
    jsonResponse(true, 'Ingredient updated.');
}

if ($method === 'DELETE') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Ingredient ID required.', [], 422);
    $pdo->prepare("DELETE FROM ingredients WHERE id = :id")->execute([':id' => (int)$d['id']]);
    jsonResponse(true, 'Ingredient removed.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
