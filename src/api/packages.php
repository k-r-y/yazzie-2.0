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
requireCsrf();
$method = $_SERVER['REQUEST_METHOD'];

// ── GET ──────────────────────────────────────────────────────────────────
if ($method === 'GET') {

    if (isset($_GET['dishes'])) {
        // Return dishes grouped by category
        $stmt = $pdo->query("
            SELECT id, name, category, meal_type, is_active, custom_fee
            FROM dishes
            WHERE is_active = 1
            ORDER BY category, name
        ");
        $all = $stmt->fetchAll();

        $grouped = [];
        foreach ($all as $d) {
            $cat = $d['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [];
            }
            $grouped[$cat][] = $d;
        }

        // Aggregate all main categories for legacy compatibility
        $mainCats = ['Beef', 'Pork', 'Chicken', 'Seafood', 'Vegetables', 'Pasta', 'Main'];
        $mainsAggregated = [];
        foreach ($mainCats as $mc) {
            if (isset($grouped[$mc])) {
                $mainsAggregated = array_merge($mainsAggregated, $grouped[$mc]);
            }
        }

        jsonResponse(true, '', [
            'dishes_grouped' => $grouped,
            'mainDishes'     => $mainsAggregated,
            'desserts'       => $grouped['Dessert'] ?? $grouped['dessert'] ?? [],
        ]);
    }

    // Default: return all packages
    $stmt = $pdo->query("
        SELECT id, set_name, pax_count, price,
               max_main_dishes, max_desserts, includes_rice, inclusions
        FROM packages
        WHERE is_active = 1
        ORDER BY pax_count ASC
    ");
    $pkgs = $stmt->fetchAll();

    // Get rates from settings
    $rates = [
        'extra_main_rate'    => (float)appSetting('extra_main_rate', 50),
        'extra_dessert_rate' => (float)appSetting('extra_dessert_rate', 30),
        'extra_rice_rate'    => (float)appSetting('extra_rice_rate', 20),
    ];

    jsonResponse(true, '', [
        'packages' => $pkgs,
        'rates'    => $rates
    ]);
}

// ── POST — add dish or package (admin only) ──────────────────────────────
if ($method === 'POST') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    if (isset($d['type']) && $d['type'] === 'package') {
        if (empty($d['set_name'])) jsonResponse(false, 'Package name is required.', [], 422);
        
        $stmt = $pdo->prepare("
            INSERT INTO packages (set_name, pax_count, price, max_main_dishes, max_desserts, includes_rice, inclusions) 
            VALUES (:name, :pax, :price, :opt_main, :opt_dessert, :rice, :inclusions)
        ");
        $stmt->execute([
            ':name'        => trim($d['set_name']),
            ':pax'         => (int)$d['pax_count'],
            ':price'       => (float)$d['price'],
            ':opt_main'    => (int)($d['max_main_dishes'] ?? 5),
            ':opt_dessert' => (int)($d['max_desserts'] ?? 1),
            ':rice'        => (int)($d['includes_rice'] ?? 1),
            ':inclusions'  => trim($d['inclusions'] ?? '')
        ]);
        jsonResponse(true, 'Package added.', ['id' => $pdo->lastInsertId()], 201);
        exit;
    }

    // Default POST: add dish
    if (empty($d['name']))     jsonResponse(false, 'Dish name is required.', [], 422);
    if (empty($d['category'])) jsonResponse(false, 'Category is required.', [], 422);
    $stmt = $pdo->prepare("INSERT INTO dishes (name, category, meal_type, custom_fee) VALUES (:name, :cat, :meal, :fee)");
    $stmt->execute([
        ':name' => trim($d['name']), 
        ':cat' => trim($d['category']), 
        ':meal' => trim($d['meal_type'] ?? 'all'),
        ':fee' => (float)($d['custom_fee'] ?? 0)
    ]);
    jsonResponse(true, 'Dish added.', ['id' => $pdo->lastInsertId()], 201);
}

// ── PUT — update dish or package ─────────────────────────────────────────
if ($method === 'PUT') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    if (isset($d['type']) && $d['type'] === 'package') {
        if (empty($d['id'])) jsonResponse(false, 'Package ID required.', [], 422);

        $pdo->prepare("
            UPDATE packages 
            SET set_name = :name, pax_count = :pax, price = :price, 
                max_main_dishes = :opt_main, max_desserts = :opt_dessert, includes_rice = :rice,
                inclusions = :inclusions
            WHERE id = :id
        ")->execute([
            ':name'        => trim($d['set_name'] ?? 'Tier'),
            ':pax'         => (int)$d['pax_count'],
            ':price'       => (float)$d['price'],
            ':opt_main'    => (int)($d['max_main_dishes'] ?? 5),
            ':opt_dessert' => (int)($d['max_desserts'] ?? 1),
            ':rice'        => (int)($d['includes_rice'] ?? 1),
            ':inclusions'  => trim($d['inclusions'] ?? ''),
            ':id'          => (int)$d['id']
        ]);
        jsonResponse(true, 'Package updated.');
        exit;
    }

    // Default PUT: update dish
    if (empty($d['id']))   jsonResponse(false, 'Dish ID required.', [], 422);
    if (empty($d['name'])) jsonResponse(false, 'Dish name required.', [], 422);

    $pdo->prepare("UPDATE dishes SET name = :name, category = :cat, meal_type = :meal, custom_fee = :fee WHERE id = :id")
        ->execute([
            ':name' => trim($d['name']),
            ':cat'  => trim($d['category'] ?? 'main'),
            ':meal' => trim($d['meal_type'] ?? 'all'),
            ':fee'  => (float)($d['custom_fee'] ?? 0),
            ':id'   => (int)$d['id'],
        ]);
    jsonResponse(true, 'Dish updated.');
}

// ── DELETE — toggle dish or package status ──────────────────────────────
if ($method === 'DELETE') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'ID required.', [], 422);

    if (isset($d['type']) && $d['type'] === 'package') {
        $pdo->prepare("UPDATE packages SET is_active = IF(is_active=1, 0, 1) WHERE id = :id")
            ->execute([':id' => (int)$d['id']]);
        jsonResponse(true, 'Package status toggled.');
        exit;
    }

    $pdo->prepare("UPDATE dishes SET is_active = IF(is_active=1, 0, 1) WHERE id = :id")
        ->execute([':id' => (int)$d['id']]);
    jsonResponse(true, 'Dish status toggled.');
}



jsonResponse(false, 'Method Not Allowed.', [], 405);
