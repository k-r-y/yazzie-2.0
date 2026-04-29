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
           pk.price AS pkg_price
    FROM bookings b
    JOIN clients   c  ON c.id  = b.client_id
    LEFT JOIN packages pk ON pk.id = b.package_id
    WHERE b.id = :id
");
$stmt->execute([':id' => $bookingId]);
$b = $stmt->fetch();
if (!$b) die('Booking not found.');

// Compute display price per pax from whichever source is available
$displayPricePerPax = $b['base_pax'] > 0
    ? round($b['base_price'] / $b['base_pax'], 2)
    : 0;

$overtimeTotal  = (float)($b['overtime_total'] ?? 0);
$breakageTotal = (float)($b['breakage_total'] ?? 0);
$extraCost      = (float)($b['extra_cost'] ?? 0);
$transportFee   = (float)($b['transport_fee'] ?? 0);
$miscSurcharge  = (float)($b['surcharge_total'] ?? 0);

// Fetch specific dishes for inclusions
$dStmt = $pdo->prepare("
    SELECT d.name, d.category 
    FROM booking_dishes bd
    JOIN dishes d ON d.id = bd.dish_id
    WHERE bd.booking_id = :bid
    ORDER BY d.category ASC
");
$dStmt->execute([':bid' => $bookingId]);
$dishes = $dStmt->fetchAll();

// Fetch Custom Items
$cStmt = $pdo->prepare("SELECT name, price FROM booking_custom_items WHERE booking_id = :bid");
$cStmt->execute([':bid' => $bookingId]);
$customItems = $cStmt->fetchAll();

// Calculate Custom Items total to subtract from baseline
$customTotal = 0;
foreach ($customItems as $ci) {
    $customTotal += (float)$ci['price'];
}

$trueMiscSurcharge = round(max(0, $b['surcharge_total'] - $customTotal), 2);
$baseLineAmount = round(max(0, $b['total_cost'] - $extraCost - $overtimeTotal - $breakageTotal - $transportFee - (float)($b['surcharge_total'] ?? 0)), 2);

if (!$isAuth) {
    $token = $_GET['token'] ?? null;
    if (empty($b['invoice_token']) || $token !== $b['invoice_token']) {
        die('Access Denied. You do not have permission to view this secure document.');
    }
}

// Fetch payment history
$pStmt = $pdo->prepare("
    SELECT p.*, u.name AS recorded_by_name
    FROM payments p JOIN users u ON u.id = p.recorded_by
    WHERE p.booking_id = :bid ORDER BY p.payment_date ASC
");
$pStmt->execute([':bid' => $bookingId]);
$payments = $pStmt->fetchAll();

$methodLabel = ['cash' => 'Cash', 'gcash' => 'GCash', 'maya' => 'Maya', 'bank_transfer' => 'Bank Transfer'];
$balance = $b['total_cost'] - $b['amount_paid'];
$eventDate = date('F j, Y', strtotime($b['event_date']));
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
            --brand-green: #166534;
            --brand-light: #F0FDF4;
            --text-dark: #171717;
            --text-muted: #737373;
            --sys-red: #DC2626;
            --sys-orange: #EA580C;
        }

        body { 
            font-family: 'Outfit', sans-serif; 
            background: #F8F9FA; 
            color: var(--text-dark);
            margin: 0; 
            padding: 40px 20px;
            -webkit-print-color-adjust: exact;
        }

        .invoice-wrapper {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 50px;
            border-radius: 24px;
            box-shadow: 0 10px 50px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
        }

        /* Decorative Background Pattern */
        .invoice-wrapper::before {
            content: '';
            position: absolute;
            top: 0; right: 0;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(22, 101, 52, 0.03) 0%, rgba(255,255,255,0) 70%);
            z-index: 0;
        }

        .header-main {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }

        .brand-block h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: var(--brand-green);
            letter-spacing: -1px;
            text-transform: uppercase;
        }
        .brand-block h1 span { color: #22C55E; }
        .brand-block p {
            margin: 5px 0 0;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .status-block {
            text-align: right;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 15px;
        }
        .status-badge.paid { background: #DCFCE7; color: #166534; border: 1px solid #BBF7D0; }
        .status-badge.partial { background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; }
        .status-badge.unpaid { background: #FEE2E2; color: #991B1B; border: 1px solid #FECACA; }
        .status-badge.cancelled { background: #F5F5F5; color: #525252; border: 1px solid #E5E5E5; }

        .invoice-meta {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 30px;
            padding: 30px 0;
            border-top: 1px solid #F1F5F9;
            border-bottom: 1px solid #F1F5F9;
            margin-bottom: 40px;
        }

        .meta-item label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .meta-item span {
            display: block;
            font-size: 15px;
            font-weight: 600;
        }

        .billing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            margin-bottom: 40px;
        }
        .billing-item h3 {
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin: 0 0 15px;
            border-bottom: 2px solid var(--brand-light);
            padding-bottom: 8px;
        }
        .billing-item .name { font-size: 18px; font-weight: 700; margin-bottom: 5px; }
        .billing-item .detail { font-size: 14px; color: var(--text-muted); margin-bottom: 3px; }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            text-align: left;
            padding: 15px;
            background: #F8FAFC;
            font-size: 12px;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .items-table td {
            padding: 20px 15px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: top;
        }
        .item-desc .title { font-weight: 700; font-size: 15px; display: block; margin-bottom: 4px; }
        .item-desc .sub { font-size: 13px; color: var(--text-muted); }
        .text-right { text-align: right !important; }

        /* Totals Area */
        .financial-summary {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
        }
        .totals-card {
            width: 320px;
            background: #F8FAFC;
            border-radius: 16px;
            padding: 20px;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }
        .total-row.grand {
            margin-top: 10px;
            padding-top: 15px;
            border-top: 2px dashed #CBD5E1;
            font-size: 18px;
            font-weight: 800;
            color: var(--brand-green);
        }

        /* Payment History Area */
        .history-section {
            margin-top: 50px;
        }
        .history-section h3 {
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .history-section h3::after { content: ''; flex: 1; height: 1px; background: #E2E8F0; }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .history-table th { text-align: left; padding: 10px; color: var(--text-muted); border-bottom: 1px solid #E2E8F0; }
        .history-table td { padding: 12px 10px; border-bottom: 1px solid #F1F5F9; }

        /* Terms and Footer */
        .invoice-footer {
            margin-top: 60px;
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 40px;
        }
        .terms-block h4 { font-size: 12px; font-weight: 800; text-transform: uppercase; margin: 0 0 10px; }
        .terms-block p { font-size: 12px; color: var(--text-muted); line-height: 1.6; margin: 0; }

        .signature-block {
            text-align: center;
        }
        .sig-line {
            border-top: 2px solid var(--text-dark);
            margin-top: 40px;
            padding-top: 8px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .no-print-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--brand-green);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 100px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 10px 30px rgba(22, 101, 52, 0.3);
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Outfit', sans-serif;
            transition: transform 0.2s;
        }
        .no-print-btn:hover { transform: translateY(-2px); }

        @media print {
            body { background: white; padding: 0; }
            .invoice-wrapper { box-shadow: none; padding: 0; max-width: 100%; }
            .no-print-btn { display: none; }
            .invoice-wrapper::before { display: none; }
        }

        /* Paid Stamp Overlay */
        .paid-stamp {
            position: absolute;
            top: 120px;
            right: 60px;
            border: 5px solid #166534;
            border-radius: 12px;
            padding: 10px 30px;
            color: #166534;
            font-size: 40px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 4px;
            transform: rotate(-15deg);
            opacity: 0.15;
            pointer-events: none;
            z-index: 10;
        }
    </style>
</head>
<body>

<div class="invoice-wrapper">
    <?php if ($b['payment_status'] === 'paid'): ?>
        <div class="paid-stamp">Fully Paid</div>
    <?php endif; ?>

    <!-- HEADER -->
    <div class="header-main">
        <div class="brand-block">
            <h1>Yazzies <span>Catering</span></h1>
            <p>
                Professional Catering & Event Management<br>
                Barangay St. Peter, Dasmariñas City, Cavite<br>
                Contact: 09XX-XXX-XXXX | yazzies.catering@gmail.com
            </p>
        </div>
        <div class="status-block">
            <?php
            $stClass = $b['payment_status'];
            if ($b['booking_status'] === 'cancelled') $stClass = 'cancelled';
            ?>
            <div class="status-badge <?= $stClass ?>">
                <?= $b['booking_status'] === 'cancelled' ? 'Booking Cancelled' : ($b['payment_status'] === 'paid' ? 'Payment Complete' : 'Payment Outstanding') ?>
            </div>
            <div style="font-size: 13px; color: var(--text-muted);">
                Invoice #: <strong>INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></strong><br>
                Issued Date: <strong><?= date('F j, Y') ?></strong>
            </div>
        </div>
    </div>

    <!-- META INFO -->
    <div class="invoice-meta">
        <div class="meta-item">
            <label>Event Date</label>
            <span><?= $eventDate ?></span>
        </div>
        <div class="meta-item">
            <label>Event Type</label>
            <span><?= htmlspecialchars($b['menu_name']) ?></span>
        </div>
        <div class="meta-item">
            <label>Guest Count</label>
            <span><?= $b['pax_count'] ?> Professional Service</span>
        </div>
    </div>

    <!-- BILLING GRID -->
    <div class="billing-grid">
        <div class="billing-item">
            <h3>Client Information</h3>
            <div class="name"><?= htmlspecialchars($b['client_name']) ?></div>
            <div class="detail"><?= htmlspecialchars($b['client_phone'] ?? '—') ?></div>
            <div class="detail"><?= htmlspecialchars($b['client_email'] ?? '—') ?></div>
        </div>
        <div class="billing-item">
            <h3>Payment Instructions</h3>
            <div class="detail"><strong>GCash:</strong> 09XX-XXX-XXXX (Yazzies)</div>
            <div class="detail"><strong>Bank:</strong> BDO / SA 00XXXXXXXXXX</div>
            <div class="detail"><strong>Note:</strong> Please include INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?> in reference.</div>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="item-desc">
                    <span class="title"><?= htmlspecialchars($b['menu_name']) ?></span>
                    <span class="sub">
                        <strong>Menu Inclusions:</strong><br>
                        <?php if (!empty($dishes)): ?>
                            <?= implode(', ', array_map(fn($d) => htmlspecialchars($d['name']), $dishes)) ?>
                        <?php else: ?>
                            Includes buffet setup, professional servers, and selected menu items.
                        <?php endif; ?>
                    </span>
                </td>
                <td class="text-right"><?= $b['pax_count'] ?> pax</td>
                <td class="text-right">₱<?= number_format($displayPricePerPax, 2) ?></td>
                <td class="text-right"><strong>₱<?= number_format($baseLineAmount, 2) ?></strong></td>
            </tr>
            <?php if ($extraCost > 0): ?>
            <tr>
                <td class="item-desc">
                    <span class="title">Additional Guest Service</span>
                    <span class="sub">Extra headcount surcharge beyond initial tier.</span>
                </td>
                <td class="text-right"><?= $b['extra_pax'] ?> pax</td>
                <td class="text-right">₱<?= number_format($displayPricePerPax, 2) ?></td>
                <td class="text-right">₱<?= number_format($extraCost, 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($overtimeTotal > 0): ?>
            <tr>
                <td class="item-desc">
                    <span class="title">Overtime Extension</span>
                    <span class="sub">Additional service hours requested.</span>
                </td>
                <td class="text-right">&mdash;</td>
                <td class="text-right">&mdash;</td>
                <td class="text-right">₱<?= number_format($overtimeTotal, 2) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($breakageTotal > 0): ?>
            <tr>
                <td class="item-desc">
                    <span class="title">Operational Loss / Breakage</span>
                    <span class="sub">Equipment or property damage reported during event.</span>
                </td>
                <td class="text-right">&mdash;</td>
                <td class="text-right">&mdash;</td>
                <td class="text-right">₱<?= number_format($breakageTotal, 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php if ((float)$b['transport_fee'] > 0): ?>
            <tr>
                <td class="item-desc">
                    <span class="title">Transport & Logistics Fee</span>
                    <span class="sub">Standard delivery and mobilization surcharge.</span>
                </td>
                <td class="text-right">1</td>
                <td class="text-right">₱<?= number_format($b['transport_fee'], 2) ?></td>
                <td class="text-right">₱<?= number_format($b['transport_fee'], 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php if ($trueMiscSurcharge > 0): ?>
            <tr>
                <td class="item-desc">
                    <span class="title">Menu & Service Surcharge</span>
                    <span class="sub">Premium dish upgrades or holiday service fee.</span>
                </td>
                <td class="text-right">1</td>
                <td class="text-right">₱<?= number_format($trueMiscSurcharge, 2) ?></td>
                <td class="text-right">₱<?= number_format($trueMiscSurcharge, 2) ?></td>
            </tr>
            <?php endif; ?>

            <?php foreach ($customItems as $ci): if ((float)$ci['price'] <= 0) continue; ?>
            <tr>
                <td class="item-desc">
                    <span class="title"><?= htmlspecialchars($ci['name']) ?> (Custom)</span>
                    <span class="sub">Additional item or service requested.</span>
                </td>
                <td class="text-right">1</td>
                <td class="text-right">₱<?= number_format($ci['price'], 2) ?></td>
                <td class="text-right">₱<?= number_format($ci['price'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- TOTALS -->
    <div class="financial-summary">
        <div class="totals-card">
            <div class="total-row">
                <span>Subtotal</span>
                <span>₱<?= number_format($b['total_cost'], 2) ?></span>
            </div>
            <div class="total-row" style="color: #059669; font-weight: 600;">
                <span>Total Payments Received</span>
                <span>- ₱<?= number_format($b['amount_paid'], 2) ?></span>
            </div>
            <div class="total-row grand">
                <span>Balance Due</span>
                <span style="color: <?= $balance > 0.01 ? 'var(--sys-red)' : 'var(--brand-green)' ?>;">
                    ₱<?= number_format(max(0, $balance), 2) ?>
                </span>
            </div>
        </div>
    </div>

    <!-- PAYMENT HISTORY -->
    <?php if (!empty($payments)): ?>
    <div class="history-section">
        <h3>Payment Registry</h3>
        <table class="history-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Reference</th>
                    <th>Recorded By</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?= date('M j, Y', strtotime($p['payment_date'])) ?></td>
                    <td><?= $methodLabel[$p['payment_method']] ?? ucfirst($p['payment_method']) ?></td>
                    <td style="font-family: monospace; font-size: 11px;"><?= htmlspecialchars($p['reference_no'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($p['recorded_by_name']) ?></td>
                    <td class="text-right" style="font-weight: 700; color: #166534;">₱<?= number_format($p['amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- FOOTER -->
    <div class="invoice-footer">
        <div class="terms-block">
            <h4>Terms & Conditions</h4>
            <p>
                1. Full payment is required on or before the event date.<br>
                2. Cancellations made within 7 days of the event are subject to a forfeiture fee.<br>
                3. Any additional requests on the day of the event will be billed separately.<br>
                4. This document serves as an official statement of account.
            </p>
        </div>
        <div class="signature-block">
            <div class="sig-line">Authorized Signature</div>
            <p style="font-size: 10px; color: var(--text-muted); margin-top: 10px;">
                Generated by Yazzies Operations Management System<br>
                Ref: <?= bin2hex(random_bytes(4)) ?>-<?= $bookingId ?>
            </p>
        </div>
    </div>
</div>

<button class="no-print-btn" onclick="window.print()">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
    Print / Save as PDF
</button>

</body>
</html>
