<?php
use Dompdf\Dompdf;
use Dompdf\Options;

function generateInvoicePDF($pdo, $bookingId) {
    // 1. Fetch main booking data
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
    if (!$b) return false;

    // 2. Fetch related data
    $ratePerPax = $b['base_pax'] > 0 ? ($b['base_price'] / $b['base_pax']) : 0;

    $dStmt = $pdo->prepare("SELECT d.name, d.category, d.custom_fee FROM booking_dishes bd JOIN dishes d ON d.id = bd.dish_id WHERE bd.booking_id = :bid ORDER BY d.category ASC");
    $dStmt->execute([':bid' => $bookingId]);
    $dishes = $dStmt->fetchAll();

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

    $cStmt = $pdo->prepare("SELECT name, price, category FROM booking_custom_items WHERE booking_id = :bid");
    $cStmt->execute([':bid' => $bookingId]);
    $customItems = $cStmt->fetchAll();

    $pStmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = :bid ORDER BY payment_date ASC");
    $pStmt->execute([':bid' => $bookingId]);
    $payments = $pStmt->fetchAll();

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
    $invoiceDate = date('F j, Y');
    $terms = appSetting('terms_and_conditions', "Full payment is required on or before the event date.");
    $privacy = appSetting('data_privacy_notice', "We value your privacy. Your data is handled securely.");

    // 3. Prepare HTML Content (DomPDF-friendly version of invoice.php)
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 40px; }
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1c1c1e; line-height: 1.4; }
            
            .header { border-bottom: 0.5pt solid #e5e5ea; padding-bottom: 15pt; margin-bottom: 20pt; }
            .brand { color: #25A244; font-size: 16pt; font-weight: bold; text-transform: uppercase; margin-bottom: 4pt; }
            .biz-details { font-size: 8pt; color: #8e8e93; line-height: 1.2; float: left; width: 60%; }
            .invoice-info { float: right; text-align: right; width: 35%; }
            .invoice-title { font-size: 12pt; font-weight: bold; margin-bottom: 2pt; }
            .meta-item { font-size: 8pt; color: #3a3a3c; }
            .meta-label { color: #8e8e93; }

            .info-grid { width: 100%; margin-bottom: 20pt; clear: both; }
            .info-box { float: left; width: 48%; }
            .info-box.right { float: right; text-align: right; }
            .section-label { font-size: 7pt; font-weight: bold; text-transform: uppercase; color: #8e8e93; border-bottom: 0.5pt solid #e5e5ea; padding-bottom: 2pt; margin-bottom: 6pt; letter-spacing: 0.5pt; }
            .info-name { font-size: 11pt; font-weight: bold; margin-bottom: 2pt; }
            .info-detail { font-size: 9pt; color: #3a3a3c; }

            .items-table { width: 100%; border-collapse: collapse; margin-bottom: 15pt; clear: both; }
            .items-table th { background: #f8f8f8; color: #8e8e93; font-size: 7.5pt; font-weight: bold; text-transform: uppercase; text-align: left; padding: 8pt 6pt; border-bottom: 0.5pt solid #e5e5ea; }
            .items-table td { padding: 8pt 6pt; border-bottom: 0.5pt solid #f8f8f8; vertical-align: top; }
            .item-name { font-weight: bold; font-size: 9pt; color: #1c1c1e; }
            .item-sub { font-size: 7.5pt; color: #8e8e93; margin-top: 1pt; }
            .text-right { text-align: right; }
            .text-center { text-align: center; }

            .financial-summary { width: 100%; margin-top: 5pt; clear: both; }

            .history-section { margin-top: 30pt; clear: both; }
            .history-table { width: 100%; border-collapse: collapse; font-size: 8pt; }
            .history-table th { border-bottom: 0.5pt solid #e5e5ea; padding: 6pt; text-align: left; color: #8e8e93; }
            .history-table td { padding: 6pt; border-bottom: 0.5pt solid #f8f8f8; }

            .stamp { position: absolute; top: 100pt; right: 50pt; border: 3pt double; padding: 6pt 15pt; font-size: 24pt; font-weight: bold; transform: rotate(-15deg); opacity: 0.4; }
            .stamp.paid { border-color: #25A244; color: #25A244; }
            .stamp.partial { border-color: #D97706; color: #D97706; }
            .stamp.unpaid { border-color: #DC2626; color: #DC2626; }
            
            .pdf-footer { border-top: 1.5pt solid #30D158; padding-top: 10pt; margin-top: 30pt; text-align: center; color: #25A244; font-size: 8pt; font-weight: bold; text-transform: uppercase; clear: both; }
            .clear { clear: both; height: 0; line-height: 0; }
        </style>
    </head>
    <body>
        <?php 
            $statusClass = strtolower($b['payment_status'] ?? 'unpaid');
            $statusLabel = ($statusClass === 'paid') ? 'FULLY PAID' : strtoupper($statusClass);
        ?>
        <div class="stamp <?= $statusClass ?>"><?= $statusLabel ?></div>

        <div class="header">
            <div class="biz-details">
                <div class="brand"><?= htmlspecialchars(appSetting('business_name', BUSINESS_NAME)) ?></div>
                <?= nl2br(htmlspecialchars(appSetting('business_address', BUSINESS_ADDRESS))) ?><br>
                Phone: <?= htmlspecialchars(appSetting('business_phone', BUSINESS_PHONE)) ?> | Email: <?= htmlspecialchars(appSetting('business_email', BUSINESS_EMAIL)) ?>
            </div>
            <div class="invoice-info">
                <div class="invoice-title">Invoice</div>
                <div class="meta-item"><span class="meta-label">ID:</span> #INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?></div>
                <div class="meta-item"><span class="meta-label">Date:</span> <?= $invoiceDate ?></div>
            </div>
            <div class="clear"></div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="section-label">Bill To</div>
                <div class="info-name"><?= htmlspecialchars($b['client_name']) ?></div>
                <div class="info-detail"><?= htmlspecialchars($b['client_phone']) ?></div>
                <div class="info-detail"><?= htmlspecialchars($b['client_email']) ?></div>
            </div>
            <div class="info-box right">
                <div class="section-label">Event Details</div>
                <div class="info-detail"><span class="meta-label">Date:</span> <?= $eventDate ?></div>
                <div class="info-detail"><span class="meta-label">Type:</span> <?= htmlspecialchars($b['event_type']) ?></div>
                <div class="info-detail"><span class="meta-label">Guests:</span> <?= $b['pax_count'] ?> Pax</div>
            </div>
            <div class="clear"></div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th width="50%">Description</th>
                    <th width="10%" class="text-center">Qty</th>
                    <th width="20%" class="text-right">Price</th>
                    <th width="20%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><div class="item-name"><?= htmlspecialchars($b['menu_name']) ?></div><div class="item-sub">Base package inclusions.</div></td>
                    <td class="text-center"><?= $b['base_pax'] ?></td>
                    <td class="text-right">&#8369;<?= number_format($ratePerPax, 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($b['base_price'], 2) ?></td>
                </tr>
                <?php if ($b['extra_pax'] > 0): ?>
                <tr>
                    <td><div class="item-name">Exceeding Pax</div><div class="item-sub">Guests beyond base limit.</div></td>
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
                ?>
                <tr>
                    <td><div class="item-name"><?= htmlspecialchars($ed['name']) ?></div><div class="item-sub">Extra Dish Surcharge</div></td>
                    <td class="text-center"><?= $b['pax_count'] ?></td>
                    <td class="text-right">&#8369;<?= number_format($fee, 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($fee * $b['pax_count'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php foreach ($customItems as $ci): 
                    $qty = in_array(strtolower($ci['category']), ['main','dessert','food']) ? $b['pax_count'] : 1;
                ?>
                <tr>
                    <td><div class="item-name"><?= htmlspecialchars($ci['name']) ?></div><div class="item-sub">Custom Add-on</div></td>
                    <td class="text-center"><?= $qty ?></td>
                    <td class="text-right">&#8369;<?= number_format($ci['price'], 2) ?></td>
                    <td class="text-right">&#8369;<?= number_format($ci['price'] * $qty, 2) ?></td>
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
            <table style="width: 240pt; float: right; border-collapse: collapse; margin-top: 10pt;">
                <tr>
                    <td style="padding: 4pt 0; font-size: 9pt; color: #3a3a3c;">Subtotal</td>
                    <td style="padding: 4pt 0; font-size: 9pt; font-weight: bold; text-align: right;">&#8369;<?= number_format($b['total_cost'], 2) ?></td>
                </tr>
                <tr>
                    <td style="padding: 4pt 0; font-size: 9pt; color: #25A244;">Payments Received</td>
                    <td style="padding: 4pt 0; font-size: 9pt; font-weight: bold; text-align: right; color: #25A244;">- &#8369;<?= number_format($b['amount_paid'], 2) ?></td>
                </tr>
                <tr>
                    <td style="padding: 10pt 0 4pt; font-size: 11pt; border-top: 0.5pt solid #1c1c1e;">Balance Due</td>
                    <td style="padding: 10pt 0 4pt; font-size: 11pt; font-weight: bold; text-align: right; color: #25A244; border-top: 0.5pt solid #1c1c1e;">&#8369;<?= number_format(max(0, $balance), 2) ?></td>
                </tr>
            </table>
            <div class="clear"></div>
        </div>

        <?php if (!empty($payments)): ?>
        <div class="history-section">
            <div class="section-label">Payment Registry</div>
            <table class="history-table">
                <thead>
                    <tr><th>Date</th><th>Method</th><th>Reference</th><th class="text-right">Amount</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($p['payment_date'])) ?></td>
                        <td><?= strtoupper($p['payment_method']) ?></td>
                        <td><?= htmlspecialchars($p['reference_no'] ?: '—') ?></td>
                        <td class="text-right">&#8369;<?= number_format($p['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div style="margin-top: 40pt; clear: both;">
            <table style="width: 100%; border-collapse: separate; border-spacing: 20pt 0; margin: 0 -20pt;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding: 12pt; background: #f9f9f9; border: 0.5pt solid #e5e5ea; border-radius: 8pt;">
                        <div style="color:#25A244; font-size: 8pt; font-weight:bold; text-transform: uppercase; margin-bottom:6pt;">Terms & Conditions</div>
                        <div style="font-size:7.5pt; color:#3a3a3c; line-height:1.4;"><?= nl2br(htmlspecialchars($terms)) ?></div>
                    </td>
                    <td style="width: 50%; vertical-align: top; padding: 12pt; background: #f9f9f9; border: 0.5pt solid #e5e5ea; border-radius: 8pt;">
                        <div style="color:#25A244; font-size: 8pt; font-weight:bold; text-transform: uppercase; margin-bottom:6pt;">Data Privacy Notice</div>
                        <div style="font-size:7.5pt; color:#3a3a3c; line-height:1.4;"><?= nl2br(htmlspecialchars($privacy)) ?></div>
                    </td>
                </tr>
            </table>
        </div>

        <div style="margin-top: 60pt; clear: both;">
            <table style="width: 100%; border-collapse: separate; border-spacing: 20pt 0; margin: 0 -20pt;">
                <tr>
                    <td style="width: 40%; vertical-align: bottom;">
                        <div style="border-top: 1pt solid #1c1c1e; margin-bottom: 4pt;"></div>
                        <div style="font-weight: bold; font-size: 10pt;"><?= htmlspecialchars($b['client_name']) ?></div>
                        <div style="font-size: 7.5pt; color: #8e8e93; text-transform: uppercase;">Customer Signature</div>
                    </td>
                    <td style="width: 40%; vertical-align: bottom;">
                        <div style="border-top: 1pt solid #1c1c1e; margin-bottom: 4pt;"></div>
                        <div style="font-weight: bold; font-size: 10pt;"><?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering Services')) ?></div>
                        <div style="font-size: 7.5pt; color: #8e8e93; text-transform: uppercase;">Authorized Signature</div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="pdf-footer">
            <?= htmlspecialchars(appSetting('business_name', 'Yazzies Catering')) ?> &bull; Invoice #INV-<?= str_pad($bookingId, 5, '0', STR_PAD_LEFT) ?> &bull; <?= date('F j, Y') ?>
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    try {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $tmp = __DIR__ . '/../temp';
        if (!is_dir($tmp)) @mkdir($tmp, 0777, true);
        $options->set('tempDir', $tmp);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        return $dompdf->output();
    } catch (Exception $e) {
        error_log("PDF Generation Error: " . $e->getMessage());
        return false;
    }
}
