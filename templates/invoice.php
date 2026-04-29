<?php
/**
 * Professional Invoice / Payment Receipt Template
 * High-fidelity, premium design for client documents.
 * URL: /templates/invoice.php?booking_id=X&token=Y
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';

startSession();
$isAuth = isset($_SESSION['user_id']) && in_array($_SESSION['role'] ?? '', ['admin', 'frontdesk'], true);

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) die('Invalid booking ID.');

$stmt = $pdo->prepare("
    SELECT b.*,
           c.name  AS client_name,
           c.phone AS client_phone,
           c.email AS client_email,
           COALESCE(pk.set_name, 'Catering Package') AS menu_name,
           pk.max_main_dishes, pk.max_desserts, pk.includes_rice
    FROM bookings b
    JOIN clients   c  ON c.id  = b.client_id
    LEFT JOIN packages pk ON pk.id = b.package_id
    WHERE b.id = :id
");
$stmt->execute([':id' => $bookingId]);
$b = $stmt->fetch();
if (!$b) die('Booking not found.');

if (!$isAuth) {
    $token = $_GET['token'] ?? null;
    if (empty($b['invoice_token']) || $token !== $b['invoice_token']) {
        die('Access Denied. You do not have permission to view this secure document.');
    }
}

$generatedBy = $_SESSION['user_name'] ?? 'Authorized Signatory';

// Calculate rates
$ratePerPax = $b['base_pax'] > 0 ? ($b['base_price'] / $b['base_pax']) : 0;

// Fetch Dishes
$dStmt = $pdo->prepare("SELECT d.name, d.category, d.custom_fee FROM booking_dishes bd JOIN dishes d ON d.id = bd.dish_id WHERE bd.booking_id = :bid ORDER BY d.category ASC");
$dStmt->execute([':bid' => $bookingId]);
$dishes = $dStmt->fetchAll();

// Identify Extra Dishes (exceeding package limits)
$mainLimit = (int)($b['max_main_dishes'] ?? 5);
$dessertLimit = (int)($b['max_desserts'] ?? 1);
$riceLimit = ($b['includes_rice'] == 1) ? 99 : 1;

$mains = array_filter($dishes, fn($d) => in_array(strtolower($d['category']), ['beef','pork','chicken','seafood','main']));
$desserts = array_filter($dishes, fn($d) => in_array(strtolower($d['category']), ['dessert','desserts']));
$others = array_filter($dishes, fn($d) => !in_array($d, $mains) && !in_array($d, $desserts));

$extraMains = array_slice($mains, $mainLimit);
$extraDesserts = array_slice($desserts, $dessertLimit);
$extraOthers = array_slice($others, $riceLimit);
$allExtraDishes = array_merge($extraMains, $extraDesserts, $extraOthers);

// Fetch Custom Items
$cStmt = $pdo->prepare("SELECT name, price, category FROM booking_custom_items WHERE booking_id = :bid");
$cStmt->execute([':bid' => $bookingId]);
$customItems = $cStmt->fetchAll();

// Fetch Payments
$pStmt = $pdo->prepare("SELECT p.*, u.name AS recorded_by_name FROM payments p JOIN users u ON u.id = p.recorded_by WHERE p.booking_id = :bid ORDER BY p.payment_date ASC");
$pStmt->execute([':bid' => $bookingId]);
$payments = $pStmt->fetchAll();

// Fetch Breakages
$brStmt = $pdo->prepare("
    SELECT bb.*, e.name AS equipment_name 
    FROM booking_breakages bb 
    JOIN equipment e ON e.id = bb.equipment_id 
    WHERE bb.booking_id = :bid AND bb.charge_to = 'client'
");
$brStmt->execute([':bid' => $bookingId]);
$breakages = $brStmt->fetchAll();

$balance = (float)$b['total_cost'] - (float)$b['amount_paid'];
$eventDate = date('F j, Y', strtotime($b['event_date']));
$terms = appSetting('terms_and_conditions', "Full payment is required on or before the event date.");
$paymentInstructions = appSetting('payment_instructions', "GCash: 09XX-XXX-XXXX");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $bookingId ?> — <?= htmlspecialchars($b['client_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --sys-green: #30D158;
            --sys-green-dark: #25A244;
            --label: #1C1C1E;
            --label-2: #3A3A3C;
            --label-3: #8E8E93;
            --sep: #E5E5EA;
            --bg: #F2F2F7;
        }

        body { 
            font-family: 'Outfit', -apple-system, sans-serif; 
            background: var(--bg); 
            color: var(--label);
            margin: 0; 
            padding: 40px 20px;
            font-size: 14px;
        }

        .invoice-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.05);
            position: relative;
        }

        .header-main {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            border-bottom: 0.5px solid var(--sep);
            padding-bottom: 20px;
        }

        .brand-block h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: var(--sys-green-dark);
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .brand-block p {
            margin: 4px 0 0;
            font-size: 11px;
            color: var(--label-3);
            line-height: 1.4;
        }

        .status-block { text-align: right; }
        .invoice-id { font-size: 14px; font-weight: 700; color: var(--label); margin-bottom: 2px; }
        .invoice-date { font-size: 12px; color: var(--label-3); }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 30px;
        }

        .section-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--label-3);
            border-bottom: 0.5px solid var(--sep);
            padding-bottom: 4px;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .info-name { font-size: 16px; font-weight: 700; margin-bottom: 4px; }
        .info-detail { font-size: 13px; color: var(--label-2); margin-bottom: 2px; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            text-align: left;
            padding: 10px;
            background: #F8F8F8;
            font-size: 10px;
            color: var(--label-3);
            text-transform: uppercase;
            font-weight: 700;
            border-bottom: 0.5px solid var(--sep);
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 0.5px solid #F8F8F8;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .item-name { font-weight: 700; font-size: 14px; color: var(--label); }
        .item-sub { font-size: 11px; color: var(--label-3); margin-top: 2px; }

        .financial-summary {
            display: flex;
            justify-content: flex-end;
            margin-top: 10px;
        }
        .totals-card {
            width: 280px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }
        .total-row.grand {
            border-top: 1px solid var(--label);
            margin-top: 8px;
            padding-top: 12px;
            font-size: 18px;
            font-weight: 800;
            color: var(--sys-green-dark);
        }

        .history-section { margin-top: 40px; }
        .history-table { width: 100%; border-collapse: collapse; font-size: 12px; }
        .history-table th { text-align: left; padding: 8px; border-bottom: 0.5px solid var(--sep); color: var(--label-3); font-weight: 700; }
        .history-table td { padding: 8px; border-bottom: 0.5px solid #F8F8F8; }
        .history-table tfoot th { background: #F8F8F8; padding: 10px 8px; border-top: 0.5px solid var(--sep); font-weight: 800; color: var(--label); }

        .footer {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 40px;
        }
        .terms p { font-size: 11px; color: var(--label-3); white-space: pre-line; line-height: 1.4; }
        .signature { text-align: center; }
        .sig-line { border-top: 1px solid var(--label); margin-top: 40px; padding-top: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; }

        .stamp {
            position: absolute; top: 100px; right: 60px;
            border: 2px solid var(--sys-green); padding: 5px 15px;
            color: var(--sys-green); font-size: 24px; font-weight: 900;
            text-transform: uppercase; opacity: 0.15;
            transform: rotate(-10deg); pointer-events: none;
        }

        .print-btn {
            position: fixed; bottom: 30px; right: 30px;
            background: var(--sys-green-dark); color: white;
            border: none; padding: 12px 24px; border-radius: 100px;
            font-weight: 700; cursor: pointer; font-family: inherit;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        @media print {
            body { background: white; padding: 0; }
            .invoice-wrapper { box-shadow: none; border: none; padding: 0; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

<div class="invoice-wrapper">
    <?php if ($b['payment_status'] === 'paid'): ?>
        <div class="stamp">FULLY PAID</div>
    <?php elseif ($b['payment_status'] === 'partial'): ?>
        <div class="stamp" style="border-color: #FF9500; color: #FF9500;">PARTIAL</div>
    <?php endif; ?>

    <div class="header-main">
        <div class="brand-block">
            <h1><?= htmlspecialchars(BUSINESS_NAME) ?></h1>
            <p>
                <?= nl2br(htmlspecialchars(BUSINESS_ADDRESS)) ?><br>
                Phone: <?= htmlspecialchars(BUSINESS_PHONE) ?> | Email: <?= htmlspecialchars(BUSINESS_EMAIL) ?>
            </p>
        </div>
        <div class="status-block">
            <div class="invoice-id">INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></div>
            <div class="invoice-date"><?= date('F j, Y') ?></div>
        </div>
    </div>

    <div class="info-grid">
        <div class="info-box">
            <div class="section-label">Bill To</div>
            <div class="info-name"><?= htmlspecialchars($b['client_name']) ?></div>
            <div class="info-detail"><?= htmlspecialchars($b['client_phone']) ?></div>
            <div class="info-detail"><?= htmlspecialchars($b['client_email']) ?></div>
        </div>
        <div class="info-box text-right">
            <div class="section-label">Event Details</div>
            <div class="info-detail"><strong>Date:</strong> <?= $eventDate ?></div>
            <div class="info-detail"><strong>Type:</strong> <?= htmlspecialchars($b['event_type']) ?></div>
            <div class="info-detail"><strong>Guests:</strong> <?= $b['pax_count'] ?> Pax</div>
        </div>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-center">Qty</th>
                <th class="text-right w-100">Price</th>
                <th class="text-right w-100">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="item-name"><?= htmlspecialchars($b['menu_name']) ?></div>
                    <div class="item-sub">Base package inclusions.</div>
                </td>
                <td class="text-center"><?= $b['base_pax'] ?></td>
                <td class="text-right">&#8369;<?= number_format($ratePerPax, 2) ?></td>
                <td class="text-right">&#8369;<?= number_format($b['base_price'], 2) ?></td>
            </tr>
            <?php if ($b['extra_pax'] > 0): ?>
            <tr>
                <td>
                    <div class="item-name">Exceeding Pax</div>
                    <div class="item-sub">Guests beyond base limit.</div>
                </td>
                <td class="text-center"><?= $b['extra_pax'] ?></td>
                <td class="text-right">&#8369;<?= number_format($ratePerPax, 2) ?></td>
                <td class="text-right">&#8369;<?= number_format($b['extra_cost'], 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php foreach ($allExtraDishes as $ed): 
                $fee = (float)($ed['custom_fee'] > 0 ? $ed['custom_fee'] : EXTRA_MAIN_RATE);
                $lineTotal = $fee * $b['pax_count'];
            ?>
            <tr>
                <td><div class="item-name"><?= htmlspecialchars($ed['name']) ?></div><div class="item-sub">Extra Course Surcharge</div></td>
                <td class="text-center"><?= $b['pax_count'] ?></td>
                <td class="text-right">&#8369;<?= number_format($fee, 2) ?></td>
                <td class="text-right">&#8369;<?= number_format($lineTotal, 2) ?></td>
            </tr>
            <?php endforeach; ?>

            <?php foreach ($customItems as $ci): 
                $qty = in_array(strtolower($ci['category']), ['main','dessert','food']) ? $b['pax_count'] : 1;
                $lineTotal = (float)$ci['price'] * $qty;
            ?>
            <tr>
                <td><div class="item-name"><?= htmlspecialchars($ci['name']) ?></div><div class="item-sub">Custom Add-on</div></td>
                <td class="text-center"><?= $qty ?></td>
                <td class="text-right">&#8369;<?= number_format($ci['price'], 2) ?></td>
                <td class="text-right">&#8369;<?= number_format($lineTotal, 2) ?></td>
            </tr>
            <?php endforeach; ?>

            <?php if ($b['transport_fee'] > 0): ?>
            <tr>
                <td><div class="item-name">Transport Fee</div><div class="item-sub">Mobilization.</div></td>
                <td class="text-center">-</td>
                <td class="text-right">&#8369;<?= number_format($b['transport_fee'], 2) ?></td>
                <td class="text-right">&#8369;<?= number_format($b['transport_fee'], 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php foreach ($breakages as $br): ?>
            <tr>
                <td><div class="item-name">Breakage: <?= htmlspecialchars($br['equipment_name']) ?></div><div class="item-sub">Damaged item.</div></td>
                <td class="text-center"><?= $br['quantity'] ?></td>
                <td class="text-right">&#8369;<?= number_format($br['unit_price'], 2) ?></td>
                <td class="text-right">&#8369;<?= number_format($br['total_cost'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="financial-summary">
        <div class="totals-card">
            <div class="total-row"><span>Subtotal</span><span>&#8369;<?= number_format($b['total_cost'], 2) ?></span></div>
            <div class="total-row" style="color: var(--sys-green-dark); font-weight: 600;"><span>Payments Received</span><span>- &#8369;<?= number_format($b['amount_paid'], 2) ?></span></div>
            <div class="total-row grand"><span>Balance Due</span><span>&#8369;<?= number_format(max(0, $balance), 2) ?></span></div>
        </div>
    </div>

    <?php if (!empty($payments)): ?>
    <div class="history-section">
        <div class="section-label">Payment Registry</div>
        <table class="history-table">
            <thead>
                <tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th></tr>
            </thead>
            <tbody>
                <?php 
                $totalReceived = 0;
                foreach ($payments as $p): 
                    $totalReceived += (float)$p['amount'];
                ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                    <td><?= strtoupper($p['payment_method']) ?></td>
                    <td><?= htmlspecialchars($p['reference_no'] ?: '—') ?></td>
                    <td class="text-right" style="font-weight: 600;">&#8369;<?= number_format($p['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-right">Total Payments Recorded</th>
                    <th class="text-right">&#8369;<?= number_format($totalReceived, 2) ?></th>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>

    <div class="footer">
        <div class="terms">
            <div class="section-label">Terms & Conditions</div>
            <p><?= htmlspecialchars($terms) ?></p>
            <div class="section-label" style="margin-top: 20px;">Payment Instructions</div>
            <p><?= htmlspecialchars($paymentInstructions) ?></p>
        </div>
        <div class="signature">
            <div class="sig-line"><?= htmlspecialchars($generatedBy) ?></div>
            <div style="font-size: 10px; color: var(--label-3); margin-top: 6px;">Yazzies Catering OMS</div>
        </div>
    </div>
</div>

<button class="print-btn" onclick="window.print()">Print Invoice</button>

</body>
</html>
