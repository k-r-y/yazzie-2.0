<?php
/**
 * Menus API
 * GET    — list all / single
 * POST   — create (admin only)
 * PUT    — update (admin only)
 * DELETE — admin only
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$user   = requireApiRole(['admin', 'frontdesk']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM menus WHERE id = :id");
        $stmt->execute([':id' => (int)$_GET['id']]);
        $menu = $stmt->fetch();
        if (!$menu) jsonResponse(false, 'Menu not found.', [], 404);

        // Fetch ingredients
        $ingStmt = $pdo->prepare("SELECT * FROM ingredients WHERE menu_id = :mid ORDER BY item_name");
        $ingStmt->execute([':mid' => $menu['id']]);
        $menu['ingredients'] = $ingStmt->fetchAll();

        jsonResponse(true, '', ['menu' => $menu]);
    }

    $activeOnly = isset($_GET['active']) ? ' WHERE is_active = 1' : '';
    $menus      = $pdo->query("SELECT * FROM menus $activeOnly ORDER BY name ASC")->fetchAll();
    jsonResponse(true, '', ['menus' => $menus]);
}

if ($method === 'POST') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['name']) || empty($d['price_per_pax'])) {
        jsonResponse(false, 'Name and price_per_pax are required.', [], 422);
    }
    $stmt = $pdo->prepare("
        INSERT INTO menus (name, description, price_per_pax)
        VALUES (:name, :description, :price_per_pax)
    ");
    $stmt->execute([
        ':name'          => trim($d['name']),
        ':description'   => trim($d['description'] ?? ''),
        ':price_per_pax' => (float)$d['price_per_pax'],
    ]);
    jsonResponse(true, 'Menu created.', ['id' => $pdo->lastInsertId()], 201);
}

if ($method === 'PUT') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Menu ID required.', [], 422);

    $stmt = $pdo->prepare("
        UPDATE menus SET
            name          = COALESCE(:name, name),
            description   = :description,
            price_per_pax = COALESCE(:price_per_pax, price_per_pax),
            is_active     = COALESCE(:is_active, is_active)
        WHERE id = :id
    ");
    $stmt->execute([
        ':id'            => (int)$d['id'],
        ':name'          => $d['name']          ?? null,
        ':description'   => $d['description']   ?? null,
        ':price_per_pax' => $d['price_per_pax'] ?? null,
        ':is_active'     => isset($d['is_active']) ? (int)$d['is_active'] : null,
    ]);
    jsonResponse(true, 'Menu updated.');
}

if ($method === 'DELETE') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Menu ID required.', [], 422);
    // Soft delete
    $pdo->prepare("UPDATE menus SET is_active = 0 WHERE id = :id")->execute([':id' => (int)$d['id']]);
    jsonResponse(true, 'Menu deactivated.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
