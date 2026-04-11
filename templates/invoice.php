<?php
/**
 * Invoice / Payment Receipt Template
 * URL: /templates/invoice.php?booking_id=X
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'frontdesk']);

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) die('Invalid booking ID.');

$stmt = $pdo->prepare("
    SELECT b.*, c.name AS client_name, c.phone AS client_phone, c.email AS client_email,
           m.name AS menu_name, m.price_per_pax
    FROM bookings b
    JOIN clients c ON c.id = b.client_id
    JOIN menus   m ON m.id = b.menu_id
    WHERE b.id = :id
");
$stmt->execute([':id' => $bookingId]);
$b = $stmt->fetch();
if (!$b) die('Booking not found.');

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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #F2EFE9; padding: 20px; }
        @media screen { .print-document { max-width: 760px; margin: 0 auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 8px 40px rgba(0,0,0,0.1); } }
        .no-print { position: fixed; bottom: 24px; right: 24px; }
        .btn-print { background: #C8501E; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(200,80,30,0.35); }
        .paid-stamp { display: inline-block; border: 3px solid #059669; border-radius: 8px; padding: 4px 16px; color: #059669; font-size: 18pt; font-weight: 900; letter-spacing: 2px; transform: rotate(-15deg); margin-left: 20px; opacity: 0.9; vertical-align: middle; }
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
            <h2>INVOICE <?= $b['payment_status'] === 'paid' ? '<span class="paid-stamp">PAID</span>' : '' ?></h2>
            <p>Invoice #: INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></p>
            <p>Date: <?= date('F j, Y') ?></p>
        </div>
    </div>

    <div class="print-info-grid" style="margin-top:16pt;">
        <div class="print-info-item"><label>Billed To</label>
            <span style="font-size:14pt;font-weight:700;"><?= htmlspecialchars($b['client_name']) ?></span>
            <span><?= htmlspecialchars($b['client_phone'] ?? '') ?></span>
            <span><?= htmlspecialchars($b['client_email'] ?? '') ?></span>
        </div>
        <div class="print-info-item"><label>Event Details</label>
            <span><?= $eventDate ?></span>
            <span><?= htmlspecialchars($b['menu_name']) ?></span>
            <span><?= $b['pax_count'] ?> guests</span>
        </div>
    </div>

    <!-- Line Items -->
    <div class="print-section-title" style="margin-top:20pt;">Invoice Items</div>
    <table class="print-table">
        <thead><tr><th>Description</th><th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
            <tr>
                <td><?= htmlspecialchars($b['menu_name']) ?><br>
                    <small style="color:#888;">Catering services for event on <?= $eventDate ?></small></td>
                <td class="text-right"><?= $b['pax_count'] ?> pax</td>
                <td class="text-right">₱<?= number_format($b['price_per_pax'], 2) ?></td>
                <td class="text-right" style="font-weight:700;">₱<?= number_format($b['total_cost'], 2) ?></td>
            </tr>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="print-total-block">
        <div class="print-total-row"><span>Subtotal</span><span>₱<?= number_format($b['total_cost'], 2) ?></span></div>
        <div class="print-total-row"><span>Amount Paid</span><span style="color:#059669;">(₱<?= number_format($b['amount_paid'], 2) ?>)</span></div>
        <div class="print-total-row grand"><span>Balance Due</span>
            <span style="color:<?= $balance > 0 ? '#DC2626' : '#059669' ?>;">₱<?= number_format(max(0, $balance), 2) ?></span></div>
    </div>

    <!-- Payment History -->
    <?php if (!empty($payments)): ?>
    <div class="print-section-title" style="margin-top:20pt;">Payment History</div>
    <table class="print-table">
        <thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th><th>Recorded By</th></tr></thead>
        <tbody>
            <?php foreach ($payments as $p): ?>
            <tr>
                <td><?= date('M j, Y', strtotime($p['payment_date'])) ?></td>
                <td><?= $methodLabel[$p['payment_method']] ?? $p['payment_method'] ?></td>
                <td><?= htmlspecialchars($p['reference_no'] ?? '—') ?></td>
                <td class="text-right" style="font-weight:700;color:#059669;">₱<?= number_format($p['amount'], 2) ?></td>
                <td><?= htmlspecialchars($p['recorded_by_name']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div style="margin-top:20pt;padding:12pt;background:#f9f7f4;border-radius:8px;font-size:11px;color:#666;">
        <strong>Payment Methods Accepted:</strong> Cash, GCash (09XX-XXX-XXXX), Maya, Bank Transfer.<br>
        Please settle any outstanding balance on or before the event date.<br>
        For inquiries, contact Yazzies Catering at our address above.
    </div>

    <div class="print-footer">
        Yazzies Catering &bull; Invoice INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?> &bull; Thank you for choosing Yazzies!
    </div>
</div>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Print Invoice</button>
</div>
</body>
</html>
