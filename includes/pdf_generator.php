<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generates a PDF version of the invoice for a given booking.
 * Returns the PDF binary data as a string.
 */
function generateInvoicePDF(int $bookingId): string {
    global $pdo;

    // 1. Fetch all data needed for the invoice (Replicating logic from invoice.php)
    $stmt = $pdo->prepare("
        SELECT b.*,
               c.name  AS client_name,
               c.phone AS client_phone,
               c.email AS client_email,
               COALESCE(pk.set_name, 'Catering Package') AS menu_name
        FROM bookings b
        JOIN clients   c  ON c.id  = b.client_id
        LEFT JOIN packages pk ON pk.id = b.package_id
        WHERE b.id = :id
    ");
    $stmt->execute([':id' => $bookingId]);
    $b = $stmt->fetch();
    if (!$b) return '';

    $displayPricePerPax = $b['base_pax'] > 0 ? round($b['base_price'] / $b['base_pax'], 2) : 0;
    $extraCost      = (float)($b['extra_cost'] ?? 0);
    $transportFee   = (float)($b['transport_fee'] ?? 0);
    $overtimeTotal  = (float)($b['overtime_total'] ?? 0);
    $breakageTotal  = (float)($b['breakage_total'] ?? 0);

    $dStmt = $pdo->prepare("SELECT d.name FROM booking_dishes bd JOIN dishes d ON d.id = bd.dish_id WHERE bd.booking_id = :bid");
    $dStmt->execute([':bid' => $bookingId]);
    $dishes = $dStmt->fetchAll(PDO::FETCH_COLUMN);

    $cStmt = $pdo->prepare("SELECT name, price FROM booking_custom_items WHERE booking_id = :bid");
    $cStmt->execute([':bid' => $bookingId]);
    $customItems = $cStmt->fetchAll();
    $customTotal = array_sum(array_column($customItems, 'price'));

    $trueMiscSurcharge = round(max(0, (float)($b['surcharge_total'] ?? 0) - $customTotal), 2);
    $baseLineAmount = round(max(0, $b['total_cost'] - $extraCost - $overtimeTotal - $breakageTotal - $transportFee - (float)($b['surcharge_total'] ?? 0)), 2);

    $pStmt = $pdo->prepare("SELECT p.*, u.name AS recorded_by_name FROM payments p JOIN users u ON u.id = p.recorded_by WHERE p.booking_id = :bid ORDER BY p.payment_date ASC");
    $pStmt->execute([':bid' => $bookingId]);
    $payments = $pStmt->fetchAll();

    $balance = $b['total_cost'] - $b['amount_paid'];
    $eventDate = date('F j, Y', strtotime($b['event_date']));

    // 2. Build the HTML for PDF (Simplified CSS for Dompdf compatibility)
    // Note: Dompdf prefers inline styles and older CSS patterns.
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: 'Helvetica', sans-serif; color: #171717; font-size: 11px; margin: 0; padding: 0; line-height: 1.4; }
            .header { margin-bottom: 20px; border-bottom: 2px solid #166534; padding-bottom: 10px; }
            .brand { color: #166534; font-size: 24px; font-weight: bold; text-transform: uppercase; }
            .status-box { float: right; text-align: right; }
            .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
            .paid { background-color: #DCFCE7; color: #166534; border: 1px solid #166534; }
            .partial { background-color: #FEF3C7; color: #92400E; border: 1px solid #92400E; }
            .unpaid { background-color: #FEE2E2; color: #991B1B; border: 1px solid #991B1B; }
            
            .meta-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .meta-table td { padding: 5px 0; width: 33%; }
            .label { font-size: 9px; color: #737373; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
            .val { font-size: 12px; font-weight: bold; }
            
            .info-table { width: 100%; margin-bottom: 25px; border-top: 1px solid #eee; padding-top: 15px; }
            .info-table th { text-align: left; font-size: 9px; color: #737373; text-transform: uppercase; padding-bottom: 8px; }
            
            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            .items-table th { background-color: #F8FAFC; padding: 8px; text-align: left; font-size: 9px; color: #737373; text-transform: uppercase; border-bottom: 1px solid #eee; }
            .items-table td { padding: 10px 8px; border-bottom: 1px solid #F1F5F9; vertical-align: top; }
            .text-right { text-align: right; }
            
            .totals-section { width: 100%; margin-top: 10px; }
            .totals-table { width: 260px; float: right; border-collapse: collapse; background-color: #F8FAFC; border-radius: 8px; }
            .totals-table td { padding: 8px 12px; font-size: 11px; }
            .grand-total-row td { border-top: 1px dashed #cbd5e1; padding-top: 12px; margin-top: 5px; font-size: 14px; font-weight: bold; color: #166534; }
            
            .footer { margin-top: 60px; font-size: 9px; color: #737373; border-top: 1px solid #eee; padding-top: 15px; clear: both; }
            .clear { clear: both; }
        </style>
    </head>
    <body>
        <div class="header">
            <div class="status-box">
                <span class="status-badge <?= $b['payment_status'] ?>">
                    <?= $b['payment_status'] === 'paid' ? 'Payment Complete' : ($b['payment_status'] === 'partial' ? 'Partial Payment' : 'Payment Outstanding') ?>
                </span>
                <div style="margin-top: 8px; font-weight: bold; font-size: 12px;">Invoice #INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></div>
            </div>
            <div class="brand">Yazzies <span style="font-weight: normal;">Catering</span></div>
            <div style="font-size: 10px; color: #737373; margin-top: 2px;">Professional Catering & Event Management</div>
        </div>

        <table class="meta-table">
            <tr>
                <td>
                    <div class="label">Event Date</div>
                    <div class="val"><?= $eventDate ?></div>
                </td>
                <td>
                    <div class="label">Event Type</div>
                    <div class="val"><?= htmlspecialchars($b['menu_name']) ?></div>
                </td>
                <td>
                    <div class="label">Guest Count</div>
                    <div class="val"><?= $b['pax_count'] ?> Pax</div>
                </td>
            </tr>
        </table>

        <table class="info-table">
            <tr>
                <th width="50%">Client Information</th>
                <th width="50%">Payment Instructions</th>
            </tr>
            <tr>
                <td style="vertical-align: top;">
                    <div style="font-weight: bold; font-size: 13px; color: #166534;"><?= htmlspecialchars($b['client_name']) ?></div>
                    <div style="margin-top: 3px;"><?= htmlspecialchars($b['client_phone']) ?></div>
                    <div><?= htmlspecialchars($b['client_email']) ?></div>
                </td>
                <td style="vertical-align: top;">
                    <div style="margin-bottom: 2px;"><strong>GCash:</strong> 09XX-XXX-XXXX (Yazzies)</div>
                    <div><strong>Bank:</strong> BDO / SA 00XXXXXXXXXX</div>
                </td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="border-radius: 4px 0 0 4px;">Description</th>
                    <th class="text-right" width="60">Qty</th>
                    <th class="text-right" width="80">Unit Price</th>
                    <th class="text-right" width="100" style="border-radius: 0 4px 4px 0;">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div style="font-weight: bold; margin-bottom: 4px;"><?= htmlspecialchars($b['menu_name']) ?></div>
                        <div style="font-size: 9px; color: #666; font-style: italic;">
                            Inclusions: <?= implode(', ', array_map('htmlspecialchars', $dishes)) ?>
                        </div>
                    </td>
                    <td class="text-right"><?= $b['pax_count'] ?> pax</td>
                    <td class="text-right">&#8369;<?= number_format($displayPricePerPax, 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($baseLineAmount, 2) ?></td>
                </tr>
                <?php if ($extraCost > 0): ?>
                <tr>
                    <td>Additional Guest Service (Extra Pax)</td>
                    <td class="text-right"><?= $b['extra_pax'] ?> pax</td>
                    <td class="text-right">&#8369;<?= number_format($displayPricePerPax, 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($extraCost, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($transportFee > 0): ?>
                <tr>
                    <td>Transport & Logistics Fee</td>
                    <td class="text-right">1</td>
                    <td class="text-right">&#8369;<?= number_format($transportFee, 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($transportFee, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($trueMiscSurcharge > 0): ?>
                <tr>
                    <td>Menu & Service Surcharge</td>
                    <td class="text-right">1</td>
                    <td class="text-right">&#8369;<?= number_format($trueMiscSurcharge, 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($trueMiscSurcharge, 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php foreach ($customItems as $ci): ?>
                <tr>
                    <td><?= htmlspecialchars($ci['name']) ?> (Custom)</td>
                    <td class="text-right">1</td>
                    <td class="text-right">&#8369;<?= number_format($ci['price'], 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($ci['price'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td style="color: #737373;">Subtotal</td>
                    <td class="text-right" style="font-weight: bold;">&#8369;<?= number_format($b['total_cost'], 2) ?></td>
                </tr>
                <tr>
                    <td style="color: #059669;">Payments Received</td>
                    <td class="text-right" style="color: #059669; font-weight: bold;">- &#8369;<?= number_format($b['amount_paid'], 2) ?></td>
                </tr>
                <tr class="grand-total-row">
                    <td>Balance Due</td>
                    <td class="text-right">&#8369;<?= number_format(max(0, $balance), 2) ?></td>
                </tr>
            </table>
            <div class="clear"></div>
        </div>

        <div class="footer">
            <div style="font-weight: bold; margin-bottom: 4px; color: #444;">Terms & Conditions</div>
            <div style="margin-bottom: 2px;">1. Full payment is required on or before the event date.</div>
            <div>2. This document serves as an official statement of account for your catering booking.</div>
            <div style="margin-top: 25px; text-align: center; color: #cbd5e1; font-style: italic;">
                Generated by Yazzies Operations Management System
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // 3. Generate PDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}
