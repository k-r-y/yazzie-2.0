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

$creatorStmt = $pdo->prepare("SELECT name FROM users WHERE id = :uid");
$creatorStmt->execute([':uid' => $b['created_by']]);
$creatorName = $creatorStmt->fetchColumn() ?: 'Authorized Signatory';

$balance = (float)$b['total_cost'] - (float)$b['amount_paid'];
$eventDate = date('F j, Y', strtotime($b['event_date']));
$terms = appSetting('terms_and_conditions', "Full payment is required on or before the event date.");
$paymentInstructions = appSetting('payment_instructions', "GCash: 09XX-XXX-XXXX");
$privacyNotice = appSetting('data_privacy_notice', "We value your privacy. Your data is handled securely.");
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

        .stamp {
            position: absolute;
            top: 60pt;
            right: 40pt;
            border: 4px double;
            padding: 8pt 20pt;
            font-size: 32pt;
            font-weight: 900;
            text-transform: uppercase;
            opacity: 0.6;
            transform: rotate(-15deg);
            pointer-events: none;
            z-index: 100;
            font-family: 'Outfit', sans-serif;
            letter-spacing: 2px;
        }
        .stamp.paid { border-color: #059669; color: #059669; }
        .stamp.partial { border-color: #D97706; color: #D97706; }
        .stamp.unpaid { border-color: #DC2626; color: #DC2626; }

        .layout-row { display: flex; gap: 24pt; margin: 24pt 0; width: 100%; }
        .layout-col { flex: 1; }
        .print-signatures { display: flex; justify-content: space-between; gap: 40pt; margin-top: 60pt; width: 100%; page-break-inside: avoid; }
        .signature-col { flex: 1; }
        .print-signature-line { border-top: 1.5px solid var(--label); margin-bottom: 5pt; width: 100%; }
        .print-signature-label { font-size: 7.5pt; color: var(--label-3); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 1pt; }
        .print-signature-name { font-size: 10pt; font-weight: 700; color: var(--label); }

        .print-footer {
            margin: 40pt -40px -40px;
            padding: 15pt 40px 15pt;
            border-top: 2px solid var(--sys-green);
            font-size: 9px;
            color: var(--sys-green-dark);
            text-align: center;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .print-btn-container {
            position: fixed;
            bottom: 24pt;
            right: 24pt;
            z-index: 9999;
        }

        .print-btn {
            background: #C8501E;
            color: white;
            border: none;
            padding: 12pt 24pt;
            border-radius: 12pt;
            font-size: 14pt;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 8pt 24pt rgba(200, 80, 30, 0.3);
            display: flex;
            align-items: center;
            gap: 8pt;
            transition: transform 0.2s;
        }
        .print-btn:hover { transform: translateY(-2pt); }

        /* ── PayNow Banner ──────────────────────────────────────────── */
        .paynow-banner {
            margin: 28px 0 0;
            padding: 24px 28px;
            background: linear-gradient(135deg, rgba(48,209,88,0.07) 0%, rgba(37,162,68,0.05) 100%);
            border: 1px solid rgba(48,209,88,0.20);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
        }

        .paynow-info { flex: 1; min-width: 0; }
        .paynow-info h4 {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 800;
            color: var(--label);
            letter-spacing: -0.3px;
        }
        .paynow-info p {
            margin: 0;
            font-size: 12px;
            color: var(--label-3);
            line-height: 1.5;
        }
        .paynow-amount {
            font-size: 26px;
            font-weight: 800;
            color: var(--sys-green-dark);
            letter-spacing: -1px;
            white-space: nowrap;
        }

        .btn-paynow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 14px 28px;
            background: linear-gradient(180deg, #3ADA63 0%, var(--sys-green-dark) 100%);
            color: #fff;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 800;
            font-family: inherit;
            letter-spacing: -0.2px;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(48,209,88,0.30), inset 0 1px 0 rgba(255,255,255,0.20);
            transition: all 0.18s cubic-bezier(0.4,0,0.2,1);
            white-space: nowrap;
            flex-shrink: 0;
            text-decoration: none;
        }
        .btn-paynow:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(48,209,88,0.38), inset 0 1px 0 rgba(255,255,255,0.20);
        }
        .btn-paynow:active:not(:disabled) { transform: scale(0.98); }
        .btn-paynow:disabled {
            opacity: 0.7;
            cursor: default;
            transform: none;
        }
        .btn-paynow .spin {
            display: inline-block;
            width: 16px; height: 16px;
            border: 2px solid rgba(255,255,255,0.4);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .paynow-methods {
            display: flex;
            gap: 6px;
            margin-top: 10px;
            flex-wrap: wrap;
        }
        .method-chip {
            padding: 3px 10px;
            background: rgba(48,209,88,0.10);
            border: 1px solid rgba(48,209,88,0.20);
            border-radius: 99px;
            font-size: 11px;
            font-weight: 700;
            color: var(--sys-green-dark);
            letter-spacing: 0.2px;
        }

        @media print {
            body { background: white; padding: 0; }
            .invoice-wrapper { box-shadow: none; border: none; padding: 0; margin: 0; }
            .print-btn-container { display: none; }
            .paynow-banner { display: none; }
            .print-footer { margin-left: 0; margin-right: 0; }
        }
    </style>
</head>
<body>

<div class="invoice-wrapper">
    <?php 
        $statusClass = strtolower($b['payment_status'] ?? 'unpaid');
        $statusLabel = ($statusClass === 'paid') ? 'FULLY PAID' : strtoupper($statusClass);
    ?>
    <div class="stamp <?= $statusClass ?>"><?= $statusLabel ?></div>

    <div class="header-main">
        <div class="brand-block">
            <h1><?= htmlspecialchars(appSetting('business_name', BUSINESS_NAME)) ?></h1>
            <p>
                <?= nl2br(htmlspecialchars(appSetting('business_address', BUSINESS_ADDRESS))) ?><br>
                Phone: <?= htmlspecialchars(appSetting('business_phone', BUSINESS_PHONE)) ?> | Email: <?= htmlspecialchars(appSetting('business_email', BUSINESS_EMAIL)) ?>
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
                $cat = strtolower($ed['category'] ?? '');
                $defaultRate = EXTRA_MAIN_RATE;
                if (in_array($cat, ['dessert', 'desserts'])) $defaultRate = EXTRA_DESSERT_RATE;
                else if (in_array($cat, ['rice', 'additional'])) $defaultRate = EXTRA_RICE_RATE;

                $fee = (float)($ed['custom_fee'] > 0 ? $ed['custom_fee'] : $defaultRate);
                $lineTotal = $fee * $b['pax_count'];
            ?>
            <tr>
                <td><div class="item-name"><?= htmlspecialchars($ed['name']) ?></div><div class="item-sub">Extra Dish Surcharge</div></td>
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

    <?php
    // Show the Pay Now banner only when there is an outstanding balance
    // and the booking has not been cancelled.
    $showPayNow = ($balance > 0.01)
               && ($b['booking_status'] !== 'cancelled')
               && ($b['booking_status'] !== 'completed');
    ?>

    <?php /* Payment Banner Removed per Request */ ?>

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

    <!-- Row 1: Terms & Privacy (2 Columns) -->
    <div class="layout-row" style="margin-top: 30pt; display: flex; gap: 20pt; width: 100%; page-break-inside: avoid;">
        <div style="flex: 1; padding: 15pt; background: #fafafa; border-radius: 12pt; border: 1px solid #eee; box-sizing: border-box;">
            <div class="print-signature-label" style="margin-bottom: 8pt; color: var(--sys-green-dark); font-weight: 800; font-size: 10px; text-transform: uppercase;">Terms & Conditions</div>
            <div style="font-size: 11px; line-height: 1.5; color: var(--label-2);">
                <?= nl2br(htmlspecialchars($terms)) ?>
            </div>
        </div>
        <div style="flex: 1; padding: 15pt; background: #fafafa; border-radius: 12pt; border: 1px solid #eee; box-sizing: border-box;">
            <div class="print-signature-label" style="margin-bottom: 8pt; color: var(--sys-green-dark); font-weight: 800; font-size: 10px; text-transform: uppercase;">Data Privacy Notice</div>
            <div style="font-size: 11px; line-height: 1.5; color: var(--label-2);">
                <?= nl2br(htmlspecialchars($privacyNotice)) ?>
            </div>
        </div>
    </div>

    <!-- Row 2: Signatures (2 Columns) -->
    <div class="print-signatures" style="margin-top: 50pt; display: flex; justify-content: space-between; gap: 60pt; width: 100%; page-break-inside: avoid;">
        <div class="signature-col" style="flex: 1;">
            <div class="print-signature-line" style="border-top: 1.5px solid var(--label); width: 100%; margin-bottom: 5pt;"></div>
            <div class="print-signature-name" style="font-size: 11pt; font-weight: 700;"><?= htmlspecialchars($b['client_name']) ?></div>
            <div class="print-signature-label" style="font-size: 8pt; color: var(--label-3); text-transform: uppercase;">Customer Signature</div>
        </div>
        <div class="signature-col" style="flex: 1;">
            <div class="print-signature-line" style="border-top: 1.5px solid var(--label); width: 100%; margin-bottom: 5pt;"></div>
            <div class="print-signature-name" style="font-size: 11pt; font-weight: 700;"><?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering Services')) ?></div>
            <div class="print-signature-label" style="font-size: 8pt; color: var(--label-3); text-transform: uppercase;">Authorized Signature</div>
        </div>
    </div>

    <div class="print-footer">
        <?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering')) ?> &bull; Invoice #INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?> &bull; <?= date('F j, Y') ?>
    </div>
</div>

<div class="no-print print-btn-container" style="display:flex;flex-direction:column;gap:10pt;">
    <button class="print-btn" onclick="window.print()">
        <span>🖨️</span> Print Invoice
    </button>
</div>

<?php if ($showPayNow ?? false): ?>
<style>
    /* Payment Overlay Styles */
    #paymentOverlay {
        display: none;
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(255,255,255,0.95);
        z-index: 9999;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 20px;
    }
    .po-spinner {
        width: 48px; height: 48px;
        border: 4px solid var(--sys-gray-5);
        border-top-color: var(--sys-green-dark);
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin-bottom: 24px;
    }
    .po-title {
        font-size: 24px; font-weight: 700; color: var(--text-color); margin-bottom: 12px;
    }
    .po-subtitle {
        font-size: 15px; color: var(--label-2); max-width: 400px; line-height: 1.5;
    }
    .po-success-icon {
        width: 64px; height: 64px; background: var(--sys-green-dark); color: white;
        border-radius: 50%; display: none; align-items: center; justify-content: center;
        font-size: 32px; margin-bottom: 24px;
    }

</style>

<div id="paymentOverlay">
    <div class="po-spinner" id="poSpinner"></div>
    <div class="po-success-icon" id="poSuccess"><i class="fas fa-check"></i></div>
    <div class="po-title" id="poTitle">Payment in Progress</div>
    <div class="po-subtitle" id="poSubtitle">Waiting for payment... Please complete the transaction in the new tab. This page will refresh automatically once payment is received.</div>


</div>

<script>
(function () {
    'use strict';

    const btn      = document.getElementById('payNowBtn');
    if (!btn) return;

    const bookingId = btn.dataset.bookingId;
    const token     = btn.dataset.token;

    // Build the API endpoint — works for both admin-session and public-token access
    const apiBase   = '<?= BASE_URL ?>';
    const endpoint  = apiBase + '/src/api/paymongo_checkout.php';

    // Read CSRF token from the meta tag if an admin session is active;
    // the endpoint accepts the invoice token as a fallback identifier.
    const csrfMeta  = document.querySelector('meta[name="csrf_token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    const pollerFn = async (bId, bToken) => {
        const overlay = document.getElementById('paymentOverlay');
        if (!overlay) return;
        overlay.style.display = 'flex';
        
        const pollEndpoint = apiBase + '/src/api/payment_status.php?booking_id=' + bId + '&token=' + encodeURIComponent(bToken);
        let pollCount = 0;

        const poller = setInterval(async () => {
            try {
                pollCount++;
                if (pollCount > 300) {
                    clearInterval(poller);
                    document.getElementById('poTitle').textContent = 'Session Expired';
                    document.getElementById('poSubtitle').textContent = 'The payment window has timed out. Please refresh the page to try again.';
                    document.getElementById('poSpinner').style.display = 'none';
                    return;
                }

                const statusRes = await fetch(pollEndpoint);
                const statusData = await statusRes.json();

                if (statusData.success && (statusData.payment_status === 'paid' || statusData.amount_paid > 0)) {
                    clearInterval(poller);
                    document.getElementById('poSpinner').style.display = 'none';
                    document.getElementById('poSuccess').style.display = 'flex';
                    document.getElementById('poTitle').textContent = 'Payment Received!';
                    
                    const returnTo = urlParams.get('return_to');
                    if (returnTo === 'financial') {
                        document.getElementById('poSubtitle').innerHTML = 
                            'We have successfully recorded the payment.<br><br>' +
                            '<a href="' + apiBase + '/views/admin/financial.php" class="btn btn-success" style="text-decoration:none; display:inline-block; margin-top:10px; padding:10px 20px; border-radius:8px; background:#27AE60; color:white; font-weight:700;">' +
                            '<i class="fas fa-arrow-left"></i> Back to Financial Module</a>';
                    } else {
                        document.getElementById('poSubtitle').textContent = 'We have successfully recorded your payment. Refreshing your invoice...';
                        setTimeout(() => {
                            const url = new URL(window.location.href);
                            url.searchParams.delete('paid');
                            window.location.href = url.toString();
                        }, 2000);
                    }
                }
            } catch (e) { console.error('[Poller] Error:', e); }
        }, 3000);
    };

    // ── Auto-start poller if redirected back from PayMongo ─────────────────
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('paid')) {
        pollerFn(bookingId, token);
    }

    btn.addEventListener('click', async function handlePayNow() {
        const originalHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spin"></span> Opening secure checkout…';

        try {
            const headers = { 'Content-Type': 'application/json' };
            if (csrfToken) headers['X-CSRF-Token'] = csrfToken;

            const response = await fetch(endpoint, {
                method:      'POST',
                credentials: 'same-origin',
                headers,
                body: JSON.stringify({
                    booking_id:    parseInt(bookingId, 10),
                    invoice_token: token,
                }),
            });

            const data = await response.json();

            if (data.success && data.checkout_url) {
                window.open(data.checkout_url, '_blank');
                pollerFn(bookingId, token);
                return;
            }

            const message = data.message || 'Could not create a payment session. Please try again.';
            alert('⚠️ ' + message);

        } catch (networkErr) {
            console.error('[PayNow] Network error:', networkErr);
            alert('⚠️ A network error occurred. Please check your connection and try again.');
        }

        btn.innerHTML = originalHTML;
        btn.disabled  = false;
    });
}());
</script>
<?php endif; ?>

</body>
</html>
