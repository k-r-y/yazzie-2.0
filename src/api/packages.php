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
        $where = empty($_GET['include_inactive']) ? "WHERE is_active = 1" : "WHERE 1=1";
        $params = [];

        if (!empty($_GET['search'])) {
            $where .= " AND (name LIKE :search OR category LIKE :search)";
            $params[':search'] = '%' . trim($_GET['search']) . '%';
        }

        if (!empty($_GET['category']) && $_GET['category'] !== 'all') {
            $where .= " AND category = :category";
            $params[':category'] = trim($_GET['category']);
        }

        if (!empty($_GET['meal_type']) && $_GET['meal_type'] !== 'all') {
            $where .= " AND (LOWER(meal_type) LIKE :meal_type OR LOWER(meal_type) = 'all')";
            $params[':meal_type'] = '%' . strtolower(trim($_GET['meal_type'])) . '%';
        }

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = (int)($_GET['limit'] ?? 1000); // Increased default for menu selection
        if ($limit < 1) $limit = 10;
        $offset = ($page - 1) * $limit;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dishes $where");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($totalRecords / $limit);

        $categoryBaseWhere = empty($_GET['include_inactive']) ? "WHERE is_active = 1" : "WHERE 1=1";
        $categoriesStmt = $pdo->prepare("SELECT DISTINCT category FROM dishes $categoryBaseWhere ORDER BY category ASC");
        $categoriesStmt->execute();
        $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);

        $stmt = $pdo->prepare("
            SELECT id, name, category, meal_type, is_active, custom_fee
            FROM dishes
            $where
            ORDER BY category, name
            LIMIT :limit OFFSET :offset
        ");
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
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
            'dishes'         => $all,
            'dishes_grouped' => $grouped,
            'categories'     => $categories,
            'mainDishes'     => $mainsAggregated,
            'desserts'       => $grouped['Dessert'] ?? $grouped['dessert'] ?? [],
            'meta' => [
                'currentPage'  => $page,
                'totalPages'   => $totalPages,
                'totalRecords' => $totalRecords,
            ],
        ]);
    }

    $where = empty($_GET['include_inactive']) ? "WHERE is_active = 1" : "";
    
    $page  = max(1, (int)($_GET['page'] ?? 1));
    $limit = (int)($_GET['limit'] ?? 10);
    if ($limit < 1) $limit = 10;
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM packages $where");
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalRecords / $limit);

    $stmt = $pdo->prepare("
        SELECT id, set_name, pax_count, price,
               max_main_dishes, max_desserts, includes_rice, inclusions, is_active
        FROM packages
        $where
        ORDER BY pax_count ASC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $pkgs = $stmt->fetchAll();

    // Get rates from settings
    $rates = [
        'extra_main_rate'    => (float)appSetting('extra_main_rate', 50),
        'extra_dessert_rate' => (float)appSetting('extra_dessert_rate', 30),
        'extra_rice_rate'    => (float)appSetting('extra_rice_rate', 20),
    ];

    jsonResponse(true, '', [
        'packages' => $pkgs,
        'rates'    => $rates,
        'meta' => [
            'currentPage'  => $page,
            'totalPages'   => $totalPages,
            'totalRecords' => $totalRecords,
        ]
    ]);
}

// ── POST — add dish or package (admin only) ──────────────────────────────
if ($method === 'POST') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    if (isset($d['type']) && $d['type'] === 'package') {
        if (strlen($d['set_name']) > 100) jsonResponse(false, 'Package name cannot exceed 100 characters.', [], 422);
        if ((float)$d['price'] < 0) jsonResponse(false, 'Price cannot be negative.', [], 422);
        if ((int)$d['pax_count'] < 1) jsonResponse(false, 'Pax count must be at least 1.', [], 422);

        $stmt = $pdo->prepare("
            INSERT INTO packages (set_name, pax_count, price, max_main_dishes, max_desserts, includes_rice, inclusions) 
            VALUES (:name, :pax, :price, :opt_main, :opt_dessert, :rice, :inclusions)
        ");
        $stmt->execute([
            ':name'        => trim($d['set_name']),
            ':pax'         => (int)$d['pax_count'],
            ':price'       => (float)$d['price'],
            ':opt_main'    => max(0, (int)($d['max_main_dishes'] ?? 5)),
            ':opt_dessert' => max(0, (int)($d['max_desserts'] ?? 1)),
            ':rice'        => (int)($d['includes_rice'] ?? 1),
            ':inclusions'  => trim(substr($d['inclusions'] ?? '', 0, 1000))
        ]);
        jsonResponse(true, 'Package added.', ['id' => $pdo->lastInsertId()], 201);
        exit;
    }

    // Default POST: add dish
    if (empty($d['name']))     jsonResponse(false, 'Dish name is required.', [], 422);
    if (strlen($d['name']) > 100) jsonResponse(false, 'Dish name cannot exceed 100 characters.', [], 422);
    if (empty($d['category'])) jsonResponse(false, 'Category is required.', [], 422);
    if (strlen($d['category']) > 50) jsonResponse(false, 'Category name too long.', [], 422);

    $stmt = $pdo->prepare("INSERT INTO dishes (name, category, meal_type, custom_fee, base_pax) VALUES (:name, :cat, :meal, :fee, 50)");
    $stmt->execute([
        ':name' => trim($d['name']), 
        ':cat' => trim($d['category']), 
        ':meal' => trim(substr($d['meal_type'] ?? 'all', 0, 50)),
        ':fee' => max(0, (float)($d['custom_fee'] ?? 0))
    ]);
    jsonResponse(true, 'Dish added.', ['id' => $pdo->lastInsertId()], 201);
}

// ── PUT — update dish or package ─────────────────────────────────────────
if ($method === 'PUT') {
    requireApiRole('admin');
    $d = json_decode(file_get_contents('php://input'), true) ?? [];

    if (isset($d['type']) && $d['type'] === 'package') {
        if (empty($d['id'])) jsonResponse(false, 'Package ID required.', [], 422);
        if (strlen($d['set_name'] ?? '') > 100) jsonResponse(false, 'Package name too long.', [], 422);

        $pdo->prepare("
            UPDATE packages 
            SET set_name = :name, pax_count = :pax, price = :price, 
                max_main_dishes = :opt_main, max_desserts = :opt_dessert, includes_rice = :rice,
                inclusions = :inclusions, is_active = :is_active
            WHERE id = :id
        ")->execute([
            ':name'        => trim(substr($d['set_name'] ?? 'Tier', 0, 100)),
            ':pax'         => max(1, (int)($d['pax_count'] ?? 50)),
            ':price'       => max(0, (float)($d['price'] ?? 0)),
            ':opt_main'    => max(0, (int)($d['max_main_dishes'] ?? 5)),
            ':opt_dessert' => max(0, (int)($d['max_desserts'] ?? 1)),
            ':rice'        => (int)($d['includes_rice'] ?? 1),
            ':inclusions'  => trim(substr($d['inclusions'] ?? '', 0, 1000)),
            ':is_active'   => isset($d['is_active']) ? (int)$d['is_active'] : 1,
            ':id'          => (int)$d['id']
        ]);
        jsonResponse(true, 'Package updated.');
        exit;
    }

    // Default PUT: update dish
    if (empty($d['id']))   jsonResponse(false, 'Dish ID required.', [], 422);
    if (empty($d['name'])) jsonResponse(false, 'Dish name required.', [], 422);
    if (strlen($d['name']) > 100) jsonResponse(false, 'Dish name too long.', [], 422);

    $pdo->prepare("UPDATE dishes SET name = :name, category = :cat, meal_type = :meal, custom_fee = :fee WHERE id = :id")
        ->execute([
            ':name' => trim(substr($d['name'], 0, 100)),
            ':cat'  => trim(substr($d['category'] ?? 'main', 0, 50)),
            ':meal' => trim(substr($d['meal_type'] ?? 'all', 0, 50)),
            ':fee'  => max(0, (float)($d['custom_fee'] ?? 0)),
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
