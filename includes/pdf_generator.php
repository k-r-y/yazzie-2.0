<?php
/**
 * Professional Invoice Generator
 * Generates high-fidelity PDF invoices for client bookings.
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Generates a professional PDF invoice for a specific booking.
 * 
 * @param int $bookingId The ID of the booking to generate the invoice for.
 * @param string|null $generatedBy The name of the user generating the invoice.
 * @return string The binary PDF data.
 */
function generateInvoicePDF($bookingId, $generatedBy = null) {
    global $pdo;

    // Try to get generator name from session if not provided
    if (!$generatedBy && isset($_SESSION['user_name'])) {
        $generatedBy = $_SESSION['user_name'];
    }

    // 1. Fetch Booking Data
    $stmt = $pdo->prepare("
        SELECT b.*,
               c.name  AS client_name,
               c.phone AS client_phone,
               c.email AS client_email,
               COALESCE(pk.set_name, 'Custom Package') AS menu_name,
               pk.max_main_dishes, pk.max_desserts, pk.includes_rice
        FROM bookings b
        JOIN clients   c  ON c.id  = b.client_id
        LEFT JOIN packages pk ON pk.id = b.package_id
        WHERE b.id = :id
    ");
    $stmt->execute([':id' => $bookingId]);
    $b = $stmt->fetch();

    if (!$b) {
        error_log("[PDF Generator] Booking #$bookingId not found.");
        return '';
    }

    // 2. Fetch Supporting Data
    $dStmt = $pdo->prepare("SELECT d.name, d.category, d.custom_fee FROM booking_dishes bd JOIN dishes d ON d.id = bd.dish_id WHERE bd.booking_id = :bid ORDER BY d.category ASC");
    $dStmt->execute([':bid' => $bookingId]);
    $dishes = $dStmt->fetchAll();

    $cStmt = $pdo->prepare("SELECT name, price, category FROM booking_custom_items WHERE booking_id = :bid");
    $cStmt->execute([':bid' => $bookingId]);
    $customItems = $cStmt->fetchAll();

    $pStmt = $pdo->prepare("SELECT p.*, u.name AS recorded_by_name FROM payments p JOIN users u ON u.id = p.recorded_by WHERE p.booking_id = :bid ORDER BY p.payment_date ASC");
    $pStmt->execute([':bid' => $bookingId]);
    $payments = $pStmt->fetchAll();

    $brStmt = $pdo->prepare("SELECT bb.*, e.name AS equipment_name FROM booking_breakages bb JOIN equipment e ON e.id = bb.equipment_id WHERE bb.booking_id = :bid AND bb.charge_to = 'client'");
    $brStmt->execute([':bid' => $bookingId]);
    $breakages = $brStmt->fetchAll();

    // 3. Logic: Extra Dishes
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

    // 4. Formatted Values
    $invoiceDate = date('F j, Y');
    $eventDate = date('F j, Y', strtotime($b['event_date']));
    $ratePerPax = $b['base_pax'] > 0 ? ($b['base_price'] / $b['base_pax']) : 0;
    $balance = (float)$b['total_cost'] - (float)$b['amount_paid'];
    $terms = appSetting('terms_and_conditions', "Full payment is required on or before the event date.\nThis document serves as an official statement of account.");

    // 5. Generate HTML
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 25pt; }
            body { 
                font-family: 'DejaVu Sans', 'Helvetica', 'Arial', sans-serif; 
                font-size: 10pt; 
                color: #1c1c1e; 
                margin: 0; padding: 0;
                line-height: 1.3;
            }
            .clear { clear: both; }
            
            /* Typography */
            .text-muted { color: #8e8e93; font-size: 9pt; }
            .text-right { text-align: right !important; }
            .text-center { text-align: center !important; }
            .text-left { text-align: left !important; }
            
            /* Header */
            .header { border-bottom: 0.5pt solid #e5e5ea; padding-bottom: 15pt; margin-bottom: 20pt; }
            .brand { font-size: 16pt; font-weight: 800; color: #25A244; text-transform: uppercase; letter-spacing: -0.5pt; }
            .biz-details { font-size: 8pt; color: #8e8e93; margin-top: 2pt; line-height: 1.1; }
            
            .invoice-info { float: right; text-align: right; margin-top: -38pt; }
            .invoice-title { font-size: 12pt; font-weight: 700; text-transform: uppercase; margin-bottom: 2pt; color: #1c1c1e; }
            .meta-label { font-size: 7.5pt; font-weight: 700; color: #8e8e93; text-transform: uppercase; margin-right: 4pt; }
            .meta-val { font-weight: 600; }

            /* Client & Event Info */
            .info-grid { margin-bottom: 15pt; }
            .info-box { float: left; width: 48%; }
            .section-label { font-size: 7.5pt; font-weight: 800; color: #8e8e93; text-transform: uppercase; margin-bottom: 4pt; border-bottom: 0.5pt solid #f2f2f7; padding-bottom: 2pt; }
            .info-name { font-size: 11pt; font-weight: 700; margin-bottom: 2pt; }
            .info-detail { font-size: 9pt; color: #3a3a3c; }

            /* Table */
            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 10pt; table-layout: fixed; }
            .items-table th { background: #f2f2f7; padding: 5pt 8pt; text-align: left; font-size: 7.5pt; font-weight: 700; color: #8e8e93; text-transform: uppercase; border-bottom: 0.5pt solid #d1d1d6; }
            .items-table td { padding: 8pt; border-bottom: 0.5pt solid #f2f2f7; vertical-align: top; word-wrap: break-word; }
            .items-table tfoot th { background: #f8f8f8; padding: 5pt 8pt; border-top: 0.5pt solid #d1d1d6; }
            .item-name { font-weight: 700; font-size: 9.5pt; }
            .item-desc { font-size: 8pt; color: #8e8e93; margin-top: 1pt; }

            /* Financials */
            .summary-area { float: right; width: 200pt; margin-top: 5pt; }
            .summary-row { padding: 3pt 0; border-bottom: 0.5pt solid #f2f2f7; overflow: hidden; }
            .summary-label { float: left; font-size: 9pt; color: #3a3a3c; }
            .summary-val { float: right; font-weight: 600; font-size: 9.5pt; }
            .summary-row.grand { border-top: 1pt solid #1c1c1e; border-bottom: none; padding-top: 6pt; margin-top: 4pt; }
            .summary-row.grand .summary-label { font-size: 10pt; font-weight: 800; }
            .summary-row.grand .summary-val { font-size: 12pt; font-weight: 800; color: #25A244; }

            /* History */
            .history { margin-top: 25pt; clear: both; }
            .history-table { width: 100%; border-collapse: collapse; margin-top: 5pt; table-layout: fixed; }
            .history-table th { font-size: 7.5pt; color: #8e8e93; text-align: left; padding: 3pt 8pt; border-bottom: 0.5pt solid #e5e5ea; }
            .history-table td { font-size: 8.5pt; padding: 4pt 8pt; border-bottom: 0.5pt solid #f2f2f7; }
            .history-table tfoot th { font-size: 8.5pt; color: #1c1c1e; padding: 6pt 8pt; border-top: 0.5pt solid #d1d1d6; background: #f8f8f8; }

            /* Footer */
            .footer { margin-top: 30pt; }
            .terms { float: left; width: 65%; }
            .signature { float: right; width: 30%; text-align: center; }
            .sig-line { border-top: 0.5pt solid #1c1c1e; margin-top: 30pt; padding-top: 3pt; font-size: 8.5pt; font-weight: 700; text-transform: uppercase; }

            /* Stamp */
            .stamp {
                position: absolute; top: 80pt; right: 40pt;
                border: 1.5pt solid #25A244; padding: 4pt 10pt;
                color: #25A244; font-size: 20pt; font-weight: 900;
                text-transform: uppercase; opacity: 0.1;
                transform: rotate(-10deg);
            }
        </style>
    </head>
    <body>
        <?php if ($b['payment_status'] === 'paid'): ?>
            <div class="stamp">FULLY PAID</div>
        <?php elseif ($b['payment_status'] === 'partial'): ?>
            <div class="stamp" style="border-color: #FF9500; color: #FF9500;">PARTIAL</div>
        <?php endif; ?>

        <div class="header">
            <div class="brand"><?= htmlspecialchars(BUSINESS_NAME) ?></div>
            <div class="biz-details">
                <?= nl2br(htmlspecialchars(BUSINESS_ADDRESS)) ?><br>
                Phone: <?= htmlspecialchars(BUSINESS_PHONE) ?> | Email: <?= htmlspecialchars(BUSINESS_EMAIL) ?>
            </div>
            <div class="invoice-info">
                <div class="invoice-title">Invoice</div>
                <div class="meta-item"><span class="meta-label">ID:</span><span class="meta-val">INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></span></div>
                <div class="meta-item"><span class="meta-label">Date:</span><span class="meta-val"><?= $invoiceDate ?></span></div>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="section-label">Bill To</div>
                <div class="info-name"><?= htmlspecialchars($b['client_name']) ?></div>
                <div class="info-detail"><?= htmlspecialchars($b['client_phone']) ?></div>
                <div class="info-detail"><?= htmlspecialchars($b['client_email']) ?></div>
            </div>
            <div class="info-box text-right" style="float: right;">
                <div class="section-label">Event Details</div>
                <div class="info-detail"><span class="meta-label">Date:</span> <?= $eventDate ?></div>
                <div class="info-detail"><span class="meta-label">Occasion:</span> <?= htmlspecialchars($b['event_type']) ?></div>
                <div class="info-detail"><span class="meta-label">Guests:</span> <?= $b['pax_count'] ?> Pax</div>
            </div>
            <div class="clear"></div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="45%">Description</th>
                    <th class="text-center" width="10%">Qty</th>
                    <th class="text-right" width="22%">Price</th>
                    <th class="text-right" width="23%">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-name"><?= htmlspecialchars($b['menu_name']) ?></div>
                        <div class="item-desc">Base package inclusions.</div>
                    </td>
                    <td class="text-center"><?= $b['base_pax'] ?></td>
                    <td class="text-right">&#8369;<?= number_format($ratePerPax, 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($b['base_price'], 2) ?></td>
                </tr>
                <?php if ($b['extra_pax'] > 0): ?>
                <tr>
                    <td>
                        <div class="item-name">Exceeding Pax</div>
                        <div class="item-desc">Guests beyond base limit.</div>
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
                    <td><div class="item-name"><?= htmlspecialchars($ed['name']) ?></div><div class="item-desc">Extra Dish Surcharge</div></td>
                    <td class="text-center"><?= $b['pax_count'] ?></td>
                    <td class="text-right">&#8369;<?= number_format($fee, 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($lineTotal, 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php foreach ($customItems as $ci): 
                    $qty = in_array(strtolower($ci['category']), ['main','dessert','food']) ? $b['pax_count'] : 1;
                    $lineTotal = $ci['price'] * $qty;
                ?>
                <tr>
                    <td><div class="item-name"><?= htmlspecialchars($ci['name']) ?></div><div class="item-desc">Custom Add-on</div></td>
                    <td class="text-center"><?= $qty ?></td>
                    <td class="text-right">&#8369;<?= number_format($ci['price'], 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($lineTotal, 2) ?></td>
                </tr>
                <?php endforeach; ?>

                <?php if ($b['transport_fee'] > 0): ?>
                <tr>
                    <td><div class="item-name">Transport Fee</div><div class="item-desc">Mobilization.</div></td>
                    <td class="text-center">-</td>
                    <td class="text-right">&#8369;<?= number_format($b['transport_fee'], 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($b['transport_fee'], 2) ?></td>
                </tr>
                <?php endif; ?>

                <?php foreach ($breakages as $br): ?>
                <tr>
                    <td><div class="item-name">Breakage: <?= htmlspecialchars($br['equipment_name']) ?></div><div class="item-desc">Damaged item.</div></td>
                    <td class="text-center"><?= $br['quantity'] ?></td>
                    <td class="text-right">&#8369;<?= number_format($br['unit_price'], 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($br['total_cost'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="summary-area">
            <div class="summary-row"><span class="summary-label">Total Billing</span><span class="summary-val">&#8369;<?= number_format($b['total_cost'], 2) ?></span></div>
            <div class="summary-row"><span class="summary-label">Total Payments</span><span class="summary-val">&#8369;<?= number_format($b['amount_paid'], 2) ?></span></div>
            <div class="summary-row grand"><span class="summary-label">Balance Due</span><span class="summary-val">&#8369;<?= number_format(max(0, $balance), 2) ?></span></div>
        </div>
        <div class="clear"></div>

        <?php if (!empty($payments)): ?>
        <div class="history">
            <div class="section-label">Payment Registry</div>
            <table class="history-table">
                <thead><tr><th width="20%">Date</th><th width="20%">Method</th><th width="35%">Reference</th><th width="25%" class="text-right">Amount</th></tr></thead>
                <tbody>
                    <?php 
                    $totalPay = 0;
                    foreach ($payments as $p): 
                        $totalPay += (float)$p['amount'];
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
                        <th class="text-right">&#8369;<?= number_format($totalPay, 2) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>

        <div class="footer">
            <div class="terms">
                <div class="section-label">Terms & Conditions</div>
                <div class="terms-text"><?= htmlspecialchars($terms) ?></div>
                <div class="section-label" style="margin-top: 10pt;">Payment Instructions</div>
                <div class="terms-text"><?= htmlspecialchars(appSetting('payment_instructions')) ?></div>
            </div>
            <div class="signature">
                <div class="sig-line"><?= htmlspecialchars($generatedBy ?: 'Authorized Signatory') ?></div>
                <div class="text-muted" style="margin-top: 4pt;">Yazzies Catering OMS</div>
            </div>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    try {
        // 6. Init Dompdf
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans'); // Supports UTF-8 and symbol better
        
        // Ensure temporary directories are defined to avoid "Path cannot be empty"
        $tmp = sys_get_temp_dir();
        $options->set('tempDir', $tmp);
        $options->set('fontDir', $tmp);
        $options->set('fontCache', $tmp);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string)$dompdf->output();
    } catch (\Throwable $e) {
        error_log("[PDF Generation Error] Booking #$bookingId: " . $e->getMessage());
        return '';
    }
}
