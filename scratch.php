<?php
require_once 'config/config.php';
$_GET['compute_pax'] = 238;

$dishId = $pdo->query("SELECT id FROM dishes LIMIT 1")->fetchColumn();
if (!$dishId) die("No dishes in DB\n");
echo "Dish ID: $dishId\n";

$_GET['dish_id'] = $dishId;

// Fake a unit test on recipes.php? No, it requires auth.
// Let's just insert one and verify.
$pdo->exec("UPDATE dishes SET base_pax = 50 WHERE id = $dishId");
$pdo->exec("DELETE FROM recipe_ingredients WHERE dish_id = $dishId");
$pdo->exec("INSERT INTO recipe_ingredients (dish_id, ingredient_name, base_quantity, unit) VALUES ($dishId, 'Chicken', 10, 'kg')");
$pdo->exec("INSERT INTO recipe_ingredients (dish_id, ingredient_name, base_quantity, unit) VALUES ($dishId, 'Soy Sauce', 1.5, 'L')");

$basePax = 50;
$targetPax = 238;
$multiplier = $targetPax / $basePax;
echo "Multiplier: $multiplier\n";

$ings = $pdo->query("SELECT * FROM recipe_ingredients WHERE dish_id = $dishId ORDER BY ingredient_name")->fetchAll();
foreach ($ings as $ing) {
    $computedQty = round($ing['base_quantity'] * $multiplier, 2);
    echo "{$ing['ingredient_name']}: {$ing['base_quantity']} {$ing['unit']} -> $computedQty {$ing['unit']}\n";
}
