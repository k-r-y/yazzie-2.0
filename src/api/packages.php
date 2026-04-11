<?php
/**
 * Packages & Dishes API
 * GET  /packages.php              — list active packages (tiers)
 * GET  /packages.php?dishes=1    — list dishes grouped by category
 * POST /packages.php             — add a dish (admin)
 * PUT  /packages.php             — update a dish (admin)
 * DELETE /packages.php           — toggle dish active state (admin)
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

requireApiRole(['admin', 'frontdesk', 'staff']);
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    if (isset($_GET['dishes'])) {
        // Return dishes grouped by category
        $stmt = $pdo->query("
            SELECT id, name, category, is_active
            FROM dishes
            WHERE is_active = 1
            ORDER BY category, name
        ");
        $all = $stmt->fetchAll();

        $grouped = ['main' => [], 'dessert' => []];
        foreach ($all as $d) {
            $grouped[$d['category']][] = $d;
        }

        jsonResponse(true, '', [
            'mainDishes' => $grouped['main'],
            'desserts'   => $grouped['dessert'],
        ]);
    }

    // Default: return all packages
    $stmt = $pdo->query("
        SELECT id, set_name, pax_count, price,
               max_main_dishes, max_desserts, includes_rice
        FROM packages
        WHERE is_active = 1
        ORDER BY pax_count ASC
    ");
    jsonResponse(true, '', ['packages' => $stmt->fetchAll()]);
}

// ── POST — add dish (admin only) ─────────────────────────────────────────
if ($method === 'POST') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['name']))     jsonResponse(false, 'Dish name is required.', [], 422);
    if (empty($d['category'])) jsonResponse(false, 'Category is required.', [], 422);
    if (!in_array($d['category'], ['main','dessert'])) {
        jsonResponse(false, 'Category must be main or dessert.', [], 422);
    }

    $stmt = $pdo->prepare("INSERT INTO dishes (name, category) VALUES (:name, :cat)");
    $stmt->execute([':name' => trim($d['name']), ':cat' => $d['category']]);
    jsonResponse(true, 'Dish added.', ['id' => $pdo->lastInsertId()], 201);
}

// ── PUT — update dish ────────────────────────────────────────────────────
if ($method === 'PUT') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id']))   jsonResponse(false, 'Dish ID required.', [], 422);
    if (empty($d['name'])) jsonResponse(false, 'Dish name required.', [], 422);

    $pdo->prepare("UPDATE dishes SET name = :name, category = :cat WHERE id = :id")
        ->execute([
            ':name' => trim($d['name']),
            ':cat'  => $d['category'] ?? 'main',
            ':id'   => (int)$d['id'],
        ]);
    jsonResponse(true, 'Dish updated.');
}

// ── DELETE — toggle active ───────────────────────────────────────────────
if ($method === 'DELETE') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Dish ID required.', [], 422);

    $pdo->prepare("UPDATE dishes SET is_active = IF(is_active=1, 0, 1) WHERE id = :id")
        ->execute([':id' => (int)$d['id']]);
    jsonResponse(true, 'Dish status toggled.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
