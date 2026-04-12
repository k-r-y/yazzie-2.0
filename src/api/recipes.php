<?php
/**
 * Recipes & Computation API
 */



header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

$user   = requireApiRole(['admin', 'frontdesk', 'staff']);
$method = $_SERVER['REQUEST_METHOD'];

// ── GET: Fetch full recipes or compute scaled yields ────────────────
if ($method === 'GET') {
    // SCALING COMPUTATION MODE
    if (!empty($_GET['compute_pax']) && !empty($_GET['dish_id'])) {
        $dishId = (int)$_GET['dish_id'];
        $targetPax = (int)$_GET['compute_pax'];
        
        $dish = $pdo->prepare("SELECT id, name, base_pax FROM dishes WHERE id = :id");
        $dish->execute([':id' => $dishId]);
        $dishData = $dish->fetch();
        if (!$dishData) jsonResponse(false, 'Dish not found.', [], 404);
        
        $basePax = (int)$dishData['base_pax'] ?: 1;
        $multiplier = $targetPax / $basePax;

        $ings = $pdo->prepare("SELECT * FROM recipe_ingredients WHERE dish_id = :id ORDER BY ingredient_name");
        $ings->execute([':id' => $dishId]);
        $ingredients = $ings->fetchAll();

        // Compute yielded quantities
        $scaled = [];
        foreach ($ingredients as $ing) {
            $computedQty = round($ing['base_quantity'] * $multiplier, 2);
            $scaled[] = [
                'id' => $ing['id'],
                'ingredient_name' => $ing['ingredient_name'],
                'base_quantity' => (float)$ing['base_quantity'],
                'computed_quantity' => $computedQty,
                'unit' => $ing['unit']
            ];
        }

        jsonResponse(true, '', [
            'dish_name' => $dishData['name'],
            'base_pax' => $basePax,
            'target_pax' => $targetPax,
            'multiplier' => round($multiplier, 4),
            'ingredients' => $scaled
        ]);
    }

    // LIST ALL RECIPES MODE
    $dishes = $pdo->query("SELECT id, name, category, is_active, base_pax FROM dishes ORDER BY category, name")->fetchAll();
    
    $ingsQuery = $pdo->query("SELECT * FROM recipe_ingredients ORDER BY dish_id, ingredient_name")->fetchAll();
    $ingredientsMap = [];
    foreach ($ingsQuery as $ing) {
        // FORCE dish_id to be an integer to ensure it matches the dish object id
        $dishId = (int)$ing['dish_id']; 
        $ingredientsMap[$dishId][] = $ing;
    }

    foreach ($dishes as &$d) {
        $dId = (int)$d['id']; // Force integer here too
        $d['ingredients'] = $ingredientsMap[$dId] ?? [];
    }
    unset($d);

    jsonResponse(true, '', ['recipes' => $dishes]);
}

// ── POST: Add ingredient to a recipe ──────────────────────────────────
if ($method === 'POST') {
    requireApiRole(['admin', 'staff']);
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    
    // If updating Dish Base Pax
    if (!empty($d['action']) && $d['action'] === 'update_base_pax') {
        if (empty($d['dish_id']) || empty($d['base_pax'])) jsonResponse(false, 'Dish ID and Base Pax required', [], 422);
        $pdo->prepare("UPDATE dishes SET base_pax = :bp WHERE id = :id")->execute([
            ':bp' => (int)$d['base_pax'],
            ':id' => (int)$d['dish_id']
        ]);
        jsonResponse(true, 'Base Pax updated successfully.', [], 200);
    }

    // Otherwise adding ingredient
    $required = ['dish_id', 'ingredient_name', 'base_quantity', 'unit'];
    foreach ($required as $req) {
        if (empty($d[$req])) jsonResponse(false, "Field '$req' is required.", [], 422);
    }

    $stmt = $pdo->prepare("
        INSERT INTO recipe_ingredients (dish_id, ingredient_name, base_quantity, unit)
        VALUES (:did, :iname, :bqty, :unit)
    ");
    $stmt->execute([
        ':did'   => (int)$d['dish_id'],
        ':iname' => trim($d['ingredient_name']),
        ':bqty'  => (float)$d['base_quantity'],
        ':unit'  => trim($d['unit'])
    ]);

    jsonResponse(true, 'Ingredient added successfully.', ['id' => $pdo->lastInsertId()], 201);
}

// ── PUT: Update ingredient ──────────────────────────────────
if ($method === 'PUT') {
    requireApiRole(['admin', 'staff']);
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Ingredient ID required.', [], 422);

    $stmt = $pdo->prepare("
        UPDATE recipe_ingredients SET 
            ingredient_name = COALESCE(:iname, ingredient_name),
            base_quantity   = COALESCE(:bqty, base_quantity),
            unit            = COALESCE(:unit, unit)
        WHERE id = :id
    ");
    $stmt->execute([
        ':id'    => (int)$d['id'],
        ':iname' => $d['ingredient_name'] ?? null,
        ':bqty'  => $d['base_quantity'] !== null ? (float)$d['base_quantity'] : null,
        ':unit'  => $d['unit'] ?? null
    ]);

    jsonResponse(true, 'Ingredient updated successfully.');
}

// ── DELETE: Remove ingredient ──────────────────────────────────
if ($method === 'DELETE') {
    requireApiRole(['admin', 'staff']);
    $d = json_decode(file_get_contents('php://input'), true) ?? [];
    if (empty($d['id'])) jsonResponse(false, 'Ingredient ID required.', [], 422);

    $pdo->prepare("DELETE FROM recipe_ingredients WHERE id = :id")->execute([':id' => (int)$d['id']]);
    jsonResponse(true, 'Ingredient removed successfully.');
}

jsonResponse(false, 'Method Not Allowed.', [], 405);
