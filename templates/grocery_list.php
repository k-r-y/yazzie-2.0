<?php
/**
 * Printable Market / Grocery List
 * URL: /templates/grocery_list.php?booking_id=X
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'frontdesk']);

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) die('Invalid booking ID.');

// Fetch booking + client
$stmt = $pdo->prepare("
    SELECT b.*, c.name AS client_name, c.phone AS client_phone,
           pk.set_name AS menu_name
    FROM bookings b
    JOIN clients c ON c.id = b.client_id
    LEFT JOIN packages pk ON pk.id = b.package_id
    WHERE b.id = :id
");
$stmt->execute([':id' => $bookingId]);
$b = $stmt->fetch();
if (!$b) die('Booking not found.');

$eventDate = date('F j, Y', strtotime($b['event_date']));
$pax = (int)$b['pax_count'];

// Fetch selected dishes for this booking
$dishStmt = $pdo->prepare("
    SELECT d.id, d.name, d.category, d.base_pax
    FROM booking_dishes bd
    JOIN dishes d ON d.id = bd.dish_id
    WHERE bd.booking_id = :id
    ORDER BY d.category ASC, d.name ASC
");
$dishStmt->execute([':id' => $bookingId]);
$dishes = $dishStmt->fetchAll();

// All dishes from booking_dishes are standard (custom items are handled separately if they exist)
$standardDishes = $dishes;
$customDishes   = [];

// Compute scaled ingredients per dish, aggregate totals
$aggregated = []; // key => [name, unit, total, unitPrice, estCost]

foreach ($standardDishes as $dish) {
    $basePax = (int)$dish['base_pax'] ?: 1;
    $multiplier = $pax / $basePax;

    $ingStmt = $pdo->prepare("SELECT * FROM recipe_ingredients WHERE dish_id = :id ORDER BY ingredient_name");
    $ingStmt->execute([':id' => $dish['id']]);
    $ingredients = $ingStmt->fetchAll();

    foreach ($ingredients as $ing) {
        $computedQty = round($ing['base_quantity'] * $multiplier, 3);
        $key = $ing['ingredient_name'] . '|' . $ing['unit'];

        if (!isset($aggregated[$key])) {
            $aggregated[$key] = [
                'name' => $ing['ingredient_name'],
                'unit' => $ing['unit'],
                'total' => 0,
            ];
        }
        $aggregated[$key]['total'] += $computedQty;
    }
}

$rows = array_values($aggregated);
usort($rows, fn($a, $b) => strcmp($a['name'], $b['name']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market List — <?= htmlspecialchars($b['client_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #F2EFE9; padding: 20px; }
        @media screen { .print-document { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 8px 40px rgba(0,0,0,0.1); } }
        .no-print { position: fixed; bottom: 24px; right: 24px; z-index: 10; }
        .btn-print { background: #2D7F3A; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(45,127,58,0.35); }
        .grocery-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .grocery-table th { background: #f8f8f8; padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #666; border-bottom: 2px solid #ddd; text-align: left; }
        .grocery-table td { padding: 8px 10px; border-bottom: 1px solid #eee; }
        .grocery-table tr:nth-child(even) td { background: rgba(0,0,0,0.015); }
        .text-right { text-align: right; }
        .fw-700 { font-weight: 700; }
        .check-box { width: 16px; height: 16px; border: 2px solid #ccc; border-radius: 3px; display: inline-block; }
        .total-row td { background: rgba(45,127,58,0.06) !important; font-weight: 700; font-size: 14px; border-top: 2px solid #2D7F3A; }
        .warn-badge { display: inline-block; background: rgba(255,149,0,0.1); color: #9A5400; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 600; }
        .dish-chip { display: inline-block; background: rgba(45,127,58,0.08); color: #2D7F3A; padding: 3px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; margin: 2px 3px 2px 0; }
        .custom-chip { background: rgba(255,149,0,0.1); color: #9A5400; }

        @media print {
            body { background: white; padding: 0; margin: 0; }
            .print-document { box-shadow: none; padding: 20px; border-radius: 0; }
            .no-print { display: none !important; }
            .grocery-table { font-size: 11px; }
            .grocery-table th { background: #f0f0f0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .total-row td { background: rgba(45,127,58,0.06) !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        @media (max-width: 600px) {
            .print-document { padding: 16px; }
            .grocery-table th:nth-child(4), .grocery-table td:nth-child(4) { display: none; }
            .print-info-grid { grid-template-columns: 1fr !important; }
        }
    </style>
</head>
<body>

<div class="print-document">
    <div class="print-header">
        <div>
            <div class="print-logo">Yazzies <span>Catering</span></div>
            <div class="print-logo-sub">Barangay St. Peter, Dasmariñas City, Cavite</div>
        </div>
        <div class="print-doc-type">
            <h2>Market List</h2>
            <p>Booking #<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></p>
            <p>Generated: <?= date('F j, Y g:i A') ?></p>
        </div>
    </div>

    <div class="print-section-title">Event Information</div>
    <div class="print-info-grid">
        <div class="print-info-item"><label>Client</label><span><?= htmlspecialchars($b['client_name']) ?></span></div>
        <div class="print-info-item"><label>Event Date</label><span><?= $eventDate ?></span></div>
        <div class="print-info-item"><label>Menu Package</label><span><?= htmlspecialchars($b['menu_name'] ?? 'Custom') ?></span></div>
        <div class="print-info-item"><label>Guest Count</label><span><?= $pax ?> pax</span></div>
    </div>

    <?php if (!empty($dishes)): ?>
    <div class="print-section-title">Selected Dishes</div>
    <div style="margin-bottom: 16px;">
        <?php foreach ($standardDishes as $d): ?>
            <span class="dish-chip"><?= htmlspecialchars($d['name']) ?></span>
        <?php endforeach; ?>
        <?php foreach ($customDishes as $d): ?>
            <span class="dish-chip custom-chip"><?= htmlspecialchars($d['name']) ?> (custom)</span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="print-section-title">Grocery / Ingredient List</div>


    <?php if (empty($rows)): ?>
    <p style="color: #999; font-style: italic; padding: 20px 0;">No recipe data available for the selected dishes. Add ingredients in Recipes & Computation.</p>
    <?php else: ?>
    <table class="grocery-table">
        <thead>
            <tr>
                <th style="width:50%;">Ingredient</th>
                <th class="text-right" style="width:20%;">Qty Required</th>
                <th style="width:15%;">Unit</th>
                <th style="width:15%; text-align:center;">✓ Check</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r):
                $formatted = formatUnit($r['total'], $r['unit']);
                list($val, $unit) = explode(' ', $formatted, 2);
            ?>
            <tr>
                <td class="fw-700"><?= htmlspecialchars($r['name']) ?></td>
                <td class="text-right fw-700" style="font-size: 14px;"><?= $val ?></td>
                <td style="color: #666;"><?= htmlspecialchars($unit) ?></td>
                <td style="text-align: center;"><span class="check-box"></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if (!empty($customDishes)): ?>
    <div style="margin-top: 16px; padding: 12px 14px; background: rgba(255,149,0,0.06); border: 1px solid rgba(255,149,0,0.2); border-radius: 8px; font-size: 12px; color: #9A5400;">
        ⚠️ <strong><?= count($customDishes) ?> custom dish(es)</strong> have no recipe data and are not included in this list. Please add ingredients manually.
    </div>
    <?php endif; ?>

    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #eee;">
        <div style="font-size: 11px; color: #999; margin-bottom: 8px;">Notes / Additional Items:</div>
        <div style="border: 1px solid #ddd; border-radius: 6px; min-height: 60px; padding: 8px;"></div>
    </div>

    <div class="print-footer">
        Yazzies Catering &bull; Market List — Booking #<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?> &bull; <?= date('F j, Y') ?>
    </div>
</div>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🛒 Print Market List</button>
</div>

</body>
</html>
