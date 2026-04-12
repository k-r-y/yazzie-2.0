<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireRole(['admin', 'frontdesk']);

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) die('Invalid booking ID.');

// 1. Fetch the main booking and client details
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
            <div class="print-logo">Yazzies <span>Catering</span></div>
            <div class="print-logo-sub">Barangay St. Peter, Dasmariñas City, Cavite</div>
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
       <div class="print-section-title">Selected Menu</div>
<div class="print-info-grid" style="display: block;"> <?php if (empty($selectedDishes)): ?>
        <p>No dishes selected.</p>
    <?php else: ?>
        <?php foreach ($selectedDishes as $category => $dishes): ?>
            <div style="margin-bottom: 10px;">
                <strong style="text-transform: uppercase; font-size: 12px; color: #C8501E;">
                    <?= htmlspecialchars($category) ?>:
                </strong>
                <span style="font-size: 14px; margin-left: 10px;">
                    <?= implode(', ', array_column($dishes, 'name')) ?>
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

    <div class="print-section-title">Payment Summary</div>
    <div class="print-total-block" style="max-width:100%;margin:0 0 16pt;">
        <div class="print-total-row"><span>Package Rate (<?= $b['pax_count'] ?> × ₱<?= number_format($b['price_per_pax'], 2) ?>)</span><span>₱<?= number_format($b['total_cost'], 2) ?></span></div>
        <div class="print-total-row"><span>Amount Paid</span><span style="color:#059669;">(₱<?= number_format($b['amount_paid'], 2) ?>)</span></div>
        <div class="print-total-row grand"><span>Outstanding Balance</span><span style="color:<?= $balance > 0 ? '#DC2626' : '#059669'; ?>">₱<?= number_format(max(0, $balance), 2) ?></span></div>
    </div>

    <div class="print-section-title">Terms & Conditions</div>
    <div class="clause"><strong>1. Booking Confirmation.</strong> This contract is binding upon signature of both parties. The event shall be considered confirmed upon receipt of the required downpayment.</div>
    <div class="clause"><strong>2. Downpayment Policy.</strong> A minimum downpayment of fifty percent (50%) of the total package price is required to confirm and reserve the event date.</div>
    <div class="clause"><strong>3. Final Payment.</strong> The remaining balance must be settled in full on or before the event date. Yazzies Catering reserves the right to withhold services for unsettled accounts.</div>
    <div class="clause"><strong>4. Cancellation Policy.</strong> Cancellations made less than seven (7) days before the event date shall forfeit the paid downpayment. Cancellations prior to seven (7) days may be subject to rescheduling at the management's discretion.</div>
    <div class="clause"><strong>5. Guest Count Changes.</strong> Final guest count adjustments must be communicated no later than three (3) days before the event. Changes may affect the final billing.</div>
    <div class="clause"><strong>6. Force Majeure.</strong> Yazzies Catering shall not be held liable for service failure due to unforeseen circumstances beyond its control.</div>
    <?php if ($b['notes']): ?>
    <div class="clause"><strong>Special Instructions:</strong> <?= htmlspecialchars($b['notes']) ?></div>
    <?php endif; ?>

    <div class="print-signatures" style="margin-top:32pt;">
        <div>
            <div class="print-signature-line"></div>
            <div class="print-signature-label">Client Signature over Printed Name</div>
            <div class="print-signature-name"><?= htmlspecialchars($b['client_name']) ?></div>
        </div>
        <div>
            <div class="print-signature-line"></div>
            <div class="print-signature-label">Authorized Representative — Yazzies Catering</div>
            <div class="print-signature-name">&nbsp;</div>
        </div>
    </div>

    <div class="print-footer">
        Yazzies Catering &bull; Contract No. YZC-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?> &bull; <?= date('F j, Y') ?>
    </div>
</div>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨️ Print Contract</button>
</div>

</body>
</html>
