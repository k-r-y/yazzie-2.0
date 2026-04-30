<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'frontdesk']);

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) die('Invalid booking ID.');

$stmt = $pdo->prepare("
    SELECT b.*, c.name AS client_name, c.phone AS client_phone, 
           c.email AS client_email, c.address AS client_address
    FROM bookings b
    JOIN clients c ON c.id = b.client_id
    WHERE b.id = :id
");
$stmt->execute([':id' => $bookingId]);
$b = $stmt->fetch();

if (!$b) die('Booking not found.');

$stmtDishes = $pdo->prepare("
    SELECT d.name, d.category 
    FROM booking_dishes bd
    JOIN dishes d ON d.id = bd.dish_id
    WHERE bd.booking_id = :id
    ORDER BY d.category ASC
");
$stmtDishes->execute([':id' => $bookingId]);
$selectedDishes = $stmtDishes->fetchAll(PDO::FETCH_GROUP);

$stmtCustom = $pdo->prepare("SELECT * FROM booking_custom_items WHERE booking_id = :id");
$stmtCustom->execute([':id' => $bookingId]);
$customItems = $stmtCustom->fetchAll();
$eventDate = date('F j, Y', strtotime($b['event_date']));
$eventTime = '';
if ($b['event_time']) {
    $t = explode(':', $b['event_time']);
    $h = (int)$t[0]; $m = $t[1];
    $eventTime = ($h % 12 ?: 12) . ':' . $m . ($h >= 12 ? ' PM' : ' AM');
}
$balance = $b['total_cost'] - $b['amount_paid'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Contract — <?= htmlspecialchars($b['client_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/print.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #F2EFE9; padding: 20px; }
        @media screen { .print-document { max-width: 800px; margin: 0 auto; background: white; padding: 40px; border-radius: 16px; box-shadow: 0 8px 40px rgba(0,0,0,0.1); } }
        .no-print { position: fixed; bottom: 24px; right: 24px; }
        .btn-print { background: #C8501E; color: white; border: none; padding: 12px 24px; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 16px rgba(200,80,30,0.35); }
        .clause { font-size: 11px; color: #444; line-height: 1.6; margin-bottom: 8pt; }
        .clause strong { color: #000; }
    </style>
</head>
<body>

<div class="print-document">
    <div class="print-header">
        <div>
            <div class="print-logo"><?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering')) ?></div>
            <div class="print-logo-sub"><?= nl2br(htmlspecialchars(appSetting('business_address', 'Barangay St. Peter, Dasmariñas City, Cavite'))) ?></div>
        </div>
        <div class="print-doc-type">
            <h2>Event Contract</h2>
            <p>Contract No.: YZC-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></p>
            <p>Date Issued: <?= date('F j, Y') ?></p>
        </div>
    </div>

    <div class="print-section-title">Client Information</div>
    <div class="print-info-grid">
        <div class="print-info-item"><label>Client Name</label><span><?= htmlspecialchars($b['client_name']) ?></span></div>
        <div class="print-info-item"><label>Contact No.</label><span><?= htmlspecialchars($b['client_phone'] ?? '—') ?></span></div>
        <div class="print-info-item"><label>Email Address</label><span><?= htmlspecialchars($b['client_email'] ?? '—') ?></span></div>
        <div class="print-info-item"><label>Address</label><span><?= htmlspecialchars($b['client_address'] ?? '—') ?></span></div>
    </div>

    <div class="print-section-title">Event Details</div>
    <div class="print-info-grid">
        <div class="print-info-item"><label>Event Date</label><span><?= $eventDate ?></span></div>
        <div class="print-info-item"><label>Event Time</label><span><?= $eventTime ?: '—' ?></span></div>
        <div class="print-info-item"><label>Venue / Location</label><span><?= htmlspecialchars($b['event_location'] ?? '—') ?></span></div>
        <div class="print-info-item"><label>Number of Guests</label><span><?= $b['pax_count'] ?> persons</span></div>
    </div>

    <div class="print-info-grid" style="align-items: start; gap: 40px; margin-top: 20px;">
        <!-- Left Column: Totals -->
        <div class="print-total-block" style="max-width:100%; margin:0;">
            <div class="print-total-row">
                <span>Base Package (<?= $b['base_pax'] ?> pax)</span>
                <span>₱<?= number_format($b['base_price'], 2) ?></span>
            </div>
            
            <?php if ($b['extra_pax'] > 0): ?>
            <div class="print-total-row">
                <span>Extra Guests (<?= $b['extra_pax'] ?> × ₱<?= number_format(125, 2) ?>)</span>
                <span>₱<?= number_format($b['extra_cost'], 2) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($b['transport_fee'] > 0): ?>
            <div class="print-total-row">
                <span>Transport Fee / Surcharge</span>
                <span>₱<?= number_format($b['transport_fee'], 2) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($b['surcharge_total'] > 0): ?>
            <div class="print-total-row">
                <span>Additional Items / Menu Surcharges</span>
                <span>₱<?= number_format($b['surcharge_total'], 2) ?></span>
            </div>
            <?php endif; ?>

            <div style="height:1px; background:#ddd; margin:8px 0;"></div>
            
            <div class="print-total-row">
                <span style="font-weight:700;">TOTAL CONTRACT AMOUNT</span>
                <span style="font-weight:700;">₱<?= number_format($b['total_cost'], 2) ?></span>
            </div>
            <div class="print-total-row">
                <span>Amount Paid / Downpayment</span>
                <span style="color:#059669;">(₱<?= number_format($b['amount_paid'], 2) ?>)</span>
            </div>
            <div class="print-total-row grand">
                <span>OUTSTANDING BALANCE</span>
                <span style="color:<?= $balance > 0 ? '#DC2626' : '#059669'; ?>">₱<?= number_format(max(0, $balance), 2) ?></span>
            </div>
        </div>

        <!-- Right Column: Menu & Instructions -->
        <div>
            <div class="print-section-title" style="margin-top: 0;">Selected Menu</div>
            <div style="margin-bottom: 20px;">
                <?php if (empty($selectedDishes)): ?>
                    <p style="font-size: 13px; color: #666;">No dishes selected.</p>
                <?php else: ?>
                    <?php foreach ($selectedDishes as $category => $dishes): ?>
                        <div style="margin-bottom: 8px;">
                            <strong style="text-transform: uppercase; font-size: 11px; color: #C8501E;">
                                <?= htmlspecialchars($category) ?>:
                            </strong>
                            <span style="font-size: 13px; margin-left: 8px; color: #333;">
                                <?= implode(', ', array_column($dishes, 'name')) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($customItems)): ?>
                    <div style="margin-top: 15px; padding-top: 10px; border-top: 1px dashed #ddd;">
                         <strong style="text-transform: uppercase; font-size: 11px; color: #C8501E;">Additional Items:</strong>
                         <ul style="margin: 5px 0; font-size: 13px; padding-left: 20px; color: #333;">
                            <?php foreach ($customItems as $ci): ?>
                                <li><?= htmlspecialchars($ci['name']) ?></li>
                            <?php endforeach; ?>
                         </ul>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($b['notes']): ?>
            <div style="padding: 12pt; background: #fafafa; border-radius: 8pt; border: 1px solid #eee;">
                <div class="print-signature-label" style="margin-bottom: 4pt; color: #C8501E; font-weight: 800; font-size: 10px; text-transform: uppercase;">Special Instructions</div>
                <div style="font-size: 13px; color: #171717; font-weight: 500; line-height: 1.4;">
                    <?= htmlspecialchars($b['notes']) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Row 1: Terms & Privacy (2 Columns) -->
    <div class="layout-row" style="margin-top: 30pt; display: flex; gap: 20pt; width: 100%; page-break-inside: avoid;">
        <div style="flex: 1; padding: 15pt; background: #fafafa; border-radius: 12pt; border: 1px solid #eee; box-sizing: border-box;">
            <div class="print-signature-label" style="margin-bottom: 8pt; color: #166534; font-weight: 800; font-size: 10px; text-transform: uppercase;">Terms & Conditions</div>
            <div style="font-size: 11px; line-height: 1.5; color: #171717;">
                <?= nl2br(htmlspecialchars(appSetting('terms_and_conditions', "1. Full payment is required on or before the event date.\n2. This document serves as an official statement of account for your catering booking.\n3. Cancellations must be made 7 days before the event for a partial refund."))) ?>
            </div>
        </div>
        <div style="flex: 1; padding: 15pt; background: #fafafa; border-radius: 12pt; border: 1px solid #eee; box-sizing: border-box;">
            <div class="print-signature-label" style="margin-bottom: 8pt; color: #166534; font-weight: 800; font-size: 10px; text-transform: uppercase;">Data Privacy Notice</div>
            <div style="font-size: 11px; line-height: 1.5; color: #171717;">
                <?= nl2br(htmlspecialchars(appSetting('data_privacy_notice', "We value your privacy. Your personal data is collected solely for the purpose of managing your booking and will not be shared with third parties without your consent."))) ?>
            </div>
        </div>
    </div>

    <!-- Row 2: Signatures (2 Columns) -->
    <div class="print-signatures" style="margin-top: 50pt; display: flex; justify-content: space-between; gap: 60pt; width: 100%; page-break-inside: avoid;">
        <div class="signature-col" style="flex: 1;">
            <div class="print-signature-line" style="border-top: 1.5px solid #171717; width: 100%; margin-bottom: 5pt;"></div>
            <div class="print-signature-name" style="font-size: 11pt; font-weight: 700;"><?= htmlspecialchars($b['client_name']) ?></div>
            <div class="print-signature-label" style="font-size: 8pt; color: #737373; text-transform: uppercase;">Customer Signature</div>
        </div>
        <div class="signature-col" style="flex: 1;">
            <div class="print-signature-line" style="border-top: 1.5px solid #171717; width: 100%; margin-bottom: 5pt;"></div>
            <div class="print-signature-name" style="font-size: 11pt; font-weight: 700;"><?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering Services')) ?></div>
            <div class="print-signature-label" style="font-size: 8pt; color: #737373; text-transform: uppercase;">Authorized Signature</div>
        </div>
    </div>

    <div class="print-footer">
        <?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering')) ?> &bull; Contract No. YZC-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?> &bull; <?= date('F j, Y') ?>
    </div>
</div>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Print Contract</button>
</div>

</body>
</html>
