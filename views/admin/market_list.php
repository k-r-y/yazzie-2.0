<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
$booking_id = $_GET['booking_id'] ?? null;
if (!$booking_id) die('Booking ID required');

// Fetch booking details
$stmt = $pdo->prepare("
    SELECT b.*, c.name as client_name 
    FROM bookings b 
    JOIN clients c ON b.client_id = c.id 
    WHERE b.id = :id
");
$stmt->execute([':id' => $booking_id]);
$booking = $stmt->fetch();

if (!$booking) die('Booking not found');

// Fetch dishes and their ingredients
$stmt = $pdo->prepare("
    SELECT d.name as dish_name, d.base_pax, bd.dish_id, 
           ri.ingredient_name, ri.base_quantity, ri.unit
    FROM booking_dishes bd
    JOIN dishes d ON bd.dish_id = d.id
    JOIN recipe_ingredients ri ON d.id = ri.dish_id
    WHERE bd.booking_id = :id
");
$stmt->execute([':id' => $booking_id]);
$items = $stmt->fetchAll();

// Aggregate ingredients
$marketList = [];
foreach ($items as $item) {
    $pax = $booking['pax_count'];
    $base_pax = (int)$item['base_pax'] > 0 ? (int)$item['base_pax'] : 50; 
    $scale = $pax / $base_pax;
    $scaledQty = $item['base_quantity'] * $scale;
    
    $key = $item['ingredient_name'] . '|' . $item['unit'];
    if (!isset($marketList[$key])) {
        $marketList[$key] = [
            'name' => $item['ingredient_name'],
            'qty'  => 0,
            'unit' => $item['unit'],
            'dishes' => []
        ];
    }
    $marketList[$key]['qty'] += $scaledQty;
    if (!in_array($item['dish_name'], $marketList[$key]['dishes'])) {
        $marketList[$key]['dishes'][] = $item['dish_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Market List - #<?= $booking['id'] ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --apple-blue: #007AFF;
            --apple-green: #34C759;
            --apple-gray: #8E8E93;
            --bg: #F2F2F7;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: var(--bg);
            margin: 0;
            padding: 20px;
            color: #1C1C1E;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            padding: 24px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            border-bottom: 1px solid #E5E5EA;
            padding-bottom: 16px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .header-meta {
            font-size: 13px;
            color: var(--apple-gray);
            margin-top: 4px;
        }
        .ingredient-item {
            display: flex;
            align-items: center;
            padding: 14px 0;
            border-bottom: 0.5px solid #E5E5EA;
        }
        .ingredient-item:last-child {
            border-bottom: none;
        }
        .checkbox {
            width: 22px;
            height: 22px;
            border: 2px solid #D1D1D6;
            border-radius: 6px;
            margin-right: 14px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .ingredient-info {
            flex: 1;
        }
        .ingredient-name {
            font-weight: 600;
            font-size: 15px;
        }
        .ingredient-dishes {
            font-size: 11px;
            color: var(--apple-gray);
            margin-top: 2px;
        }
        .qty-badge {
            background: rgba(0,122,255,0.1);
            color: var(--apple-blue);
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            white-space: nowrap;
        }
        .print-btn {
            display: block;
            width: 100%;
            background: var(--apple-blue);
            color: white;
            text-align: center;
            padding: 14px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 24px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 16px;
            color: var(--apple-blue);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        @media print {
            .print-btn, .header-actions, .back-link, .checkbox { display: none; }
            body { background: white; padding: 0; }
            .container { box-shadow: none; border-radius: 0; padding: 0; max-width: 100%; }
        }
    </style>
</head>
<body>
    <a href="bookings.php" class="back-link"><i class="fas fa-chevron-left"></i> Back to Bookings</a>
    <div class="container">
        <div class="header">
            <div>
                <h1>Grocery Market List</h1>
                <div class="header-meta">
                    Booking #<?= $booking['id'] ?> &middot; <?= htmlspecialchars($booking['client_name']) ?><br>
                    Event Date: <?= date('M d, Y', strtotime($booking['event_date'])) ?> &middot; <?= $booking['pax_count'] ?> Pax
                </div>
            </div>
            <div class="header-actions">
                <button onclick="window.print()" style="background:none; border:none; color:var(--apple-blue); font-size:18px; cursor:pointer;"><i class="fas fa-print"></i></button>
            </div>
        </div>

        <div class="list-section">
            <?php if (empty($marketList)): ?>
                <div style="text-align:center; padding:40px 0; color:var(--apple-gray);">
                    <i class="fas fa-shopping-basket" style="font-size:32px; margin-bottom:12px; opacity:0.3;"></i>
                    <p>No ingredients found for the selected dishes.</p>
                </div>
            <?php else: ?>
                <?php foreach ($marketList as $item): ?>
                    <div class="ingredient-item">
                        <div class="checkbox" onclick="this.style.background = this.style.background ? '' : '#34C759'; this.style.borderColor = this.style.background ? '#34C759' : '#D1D1D6';"></div>
                        <div class="ingredient-info">
                            <div class="ingredient-name"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="ingredient-dishes"><?= htmlspecialchars(implode(', ', $item['dishes'])) ?></div>
                        </div>
                        <div class="qty-badge"><?= number_format($item['qty'], 2) ?> <?= htmlspecialchars($item['unit']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <a href="javascript:void(0)" class="print-btn" onclick="window.print()"><i class="fas fa-print" style="margin-right:8px;"></i> Print List</a>
    </div>
</body>
</html>
