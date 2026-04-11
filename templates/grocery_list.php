<?php
/**
 * Printable Grocery List Template
 * URL: /templates/grocery_list.php?booking_id=X
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'frontdesk']);

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) die('Invalid booking ID.');

// Fetch booking details
$bStmt = $pdo->prepare("
    SELECT b.*, c.name AS client_name, c.phone AS client_phone,
           m.name AS menu_name, m.id AS menu_id
    FROM bookings b
    JOIN clients c ON c.id = b.client_id
    JOIN menus   m ON m.id = b.menu_id
    WHERE b.id = :id
");
$bStmt->execute([':id' => $bookingId]);
$booking = $bStmt->fetch();
if (!$booking) die('Booking not found.');

// Fetch ingredients
$iStmt = $pdo->prepare("SELECT * FROM ingredients WHERE menu_id = :mid ORDER BY item_name");
$iStmt->execute([':mid' => $booking['menu_id']]);
$ingredients = $iStmt->fetchAll();

$paxCount = (int)$booking['pax_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grocery List — <?= htmlspecialchars($booking['client_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #F2EFE9; padding: 20px; }
        @media screen { .print-document { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 8px 40px rgba(0,0,0,0.1); } }
        .no-print { position: fixed; bottom: 24px; right: 24px; z-index: 100; }
        .btn-print { background: #C8501E; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(200,80,30,0.35); }
        .btn-print:hover { background: #A83E16; }
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
            <h2>Grocery List</h2>
            <p>Generated: <?= date('F j, Y') ?></p>
            <p>Booking #<?= $bookingId ?></p>
        </div>
    </div>

    <!-- Event Details -->
    <div class="print-section-title">Event Details</div>
    <div class="print-info-grid">
        <div class="print-info-item">
            <label>Client Name</label>
            <span><?= htmlspecialchars($booking['client_name']) ?></span>
        </div>
        <div class="print-info-item">
            <label>Contact Number</label>
            <span><?= htmlspecialchars($booking['client_phone'] ?? '—') ?></span>
        </div>
        <div class="print-info-item">
            <label>Event Date</label>
            <span><?= date('F j, Y', strtotime($booking['event_date'])) ?></span>
        </div>
        <div class="print-info-item">
            <label>Event Time</label>
            <span><?php
                if ($booking['event_time']) {
                    $t = explode(':', $booking['event_time']);
                    $h = (int)$t[0]; $m = $t[1];
                    echo ($h % 12 ?: 12) . ':' . $m . ($h >= 12 ? ' PM' : ' AM');
                } else { echo '—'; }
            ?></span>
        </div>
        <div class="print-info-item">
            <label>Event Location</label>
            <span><?= htmlspecialchars($booking['event_location'] ?? '—') ?></span>
        </div>
        <div class="print-info-item">
            <label>Menu Package</label>
            <span><?= htmlspecialchars($booking['menu_name']) ?></span>
        </div>
    </div>

    <!-- PAX Count highlight -->
    <div style="background:#FEF3EC;border:2px solid #F0A028;border-radius:8px;padding:12px 16px;margin:16px 0;display:flex;align-items:center;gap:12px;">
        <span style="font-size:28px;">👥</span>
        <div>
            <div style="font-size:22px;font-weight:800;color:#C8501E;"><?= $paxCount ?> Guests</div>
            <div style="font-size:13px;color:#666;">All quantities below are calculated for <?= $paxCount ?> pax</div>
        </div>
    </div>

    <!-- Grocery List -->
    <div class="print-section-title">Ingredient Checklist</div>

    <?php if (empty($ingredients)): ?>
        <p style="color:#888;font-style:italic;">No ingredients defined for this menu package.</p>
    <?php else: ?>
        <table class="print-table">
            <thead>
                <tr>
                    <th style="width:40px;">#</th>
                    <th>Ingredient</th>
                    <th class="text-right" style="width:120px;">Qty per Pax</th>
                    <th class="text-center" style="width:80px;">× Pax</th>
                    <th class="text-right print-qty" style="width:120px;">Total</th>
                    <th style="width:80px;">Unit</th>
                    <th style="width:50px;">✓</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ingredients as $i => $ing):
                    $totalQty = round($ing['quantity_per_pax'] * $paxCount, 3);
                    $totalQtyStr = rtrim(rtrim(number_format($totalQty, 3), '0'), '.');
                ?>
                <tr class="no-page-break">
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($ing['item_name']) ?></td>
                    <td class="text-right"><?= $ing['quantity_per_pax'] ?></td>
                    <td class="text-center">× <?= $paxCount ?></td>
                    <td class="text-right print-qty" style="font-size:13pt;"><?= $totalQtyStr ?></td>
                    <td><?= htmlspecialchars($ing['unit']) ?></td>
                    <td><div style="width:18px;height:18px;border:1.5px solid #999;border-radius:3px;margin:0 auto;"></div></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:12px;font-size:11px;color:#888;">
            Total: <?= count($ingredients) ?> items to purchase for <?= $paxCount ?> guests.
        </div>
    <?php endif; ?>

    <?php if ($booking['notes']): ?>
    <div class="print-section-title" style="margin-top:20px;">Notes</div>
    <p style="font-size:12px;color:#444;"><?= nl2br(htmlspecialchars($booking['notes'])) ?></p>
    <?php endif; ?>

    <!-- Signatures -->
    <div class="print-signatures">
        <div>
            <div class="print-signature-line"></div>
            <div class="print-signature-label">Prepared by</div>
            <div class="print-signature-name"><?= htmlspecialchars(getCurrentUser()['name']) ?></div>
        </div>
        <div>
            <div class="print-signature-line"></div>
            <div class="print-signature-label">Head Cook / Reviewed by</div>
            <div class="print-signature-name">&nbsp;</div>
        </div>
    </div>

    <div class="print-footer">
        <?= APP_NAME ?> &bull; Booking #<?= $bookingId ?> &bull; Printed <?= date('F j, Y \a\t g:i A') ?>
    </div>
</div>

<!-- Print Button (hidden on print) -->
<div class="no-print">
    <button class="btn-print" onclick="window.print()">
        🖨️ Print Grocery List
    </button>
</div>

</body>
</html>
