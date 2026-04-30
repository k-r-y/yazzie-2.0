<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'frontdesk']);

$pageTitle    = 'Financial Tracking';
$pageSubtitle = 'Payment ledger — Admin access only';
$activePage   = 'financial';
$preloadBookingId = (int)($_GET['booking_id'] ?? 0);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- DATE FILTER -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-chart-pie text-muted"></i>
                    <span class="fw-bold">Financial Timeframe:</span>
                </div>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="tf" id="tf-day" value="day" onchange="refreshStats()">
                    <label class="btn btn-outline-primary btn-sm" for="tf-day">Daily</label>

                    <input type="radio" class="btn-check" name="tf" id="tf-week" value="week" onchange="refreshStats()">
                    <label class="btn btn-outline-primary btn-sm" for="tf-week">Weekly</label>

                    <input type="radio" class="btn-check" name="tf" id="tf-month" value="month" checked onchange="refreshStats()">
                    <label class="btn btn-outline-primary btn-sm" for="tf-month">Monthly</label>

                    <input type="radio" class="btn-check" name="tf" id="tf-year" value="year" onchange="refreshStats()">
                    <label class="btn btn-outline-primary btn-sm" for="tf-year">Yearly</label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SUMMARY KPIs -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="stat-total-rev">—</div>
                <div class="stat-label">Total Collected (All Time)</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="stat-mtd">—</div>
                <div class="stat-label">Collected This Month</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-circle-exclamation"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="stat-outstanding">—</div>
                <div class="stat-label">Outstanding Balance</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- LEFT: Booking Balance Ledger -->
    <div class="col-lg-8 order-2 order-lg-1">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Booking Balance Ledger</div>
                    <div class="card-subtitle">Per-booking view of total cost, paid, and remaining</div>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <!-- Tab Toggle -->
                    <div class="btn-group me-2">
                        <button class="btn btn-outline-primary btn-sm active" id="tabLedgerBtn" onclick="showTab('ledger')">
                            <i class="fas fa-list"></i> Payments
                        </button>
                        <button class="btn btn-outline-primary btn-sm" id="tabRefundBtn" onclick="showTab('refunds')">
                            <i class="fas fa-hand-holding-dollar"></i> Refunds
                        </button>
                    </div>

                    <!-- Search Bar -->
                    <div class="input-group input-group-sm me-2" style="min-width: 250px; flex: 2;">
                        <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control bg-light border-start-0" id="searchLedger" placeholder="Quick search client name...">
                    </div>

                    <select class="form-control" id="filterStatus" style="min-width:130px; flex:1;" title="Filter by booking status">
                        <option value="">All Statuses</option>

                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                    <select class="form-control" id="filterPayment" style="min-width:120px; flex:1;" title="Filter by payment status">
                        <option value="">All Payments</option>
                        <option value="unpaid">Unpaid</option>
                        <option value="partial">Partial</option>
                        <option value="paid">Paid</option>
                    </select>
                </div>
            </div>
            <div class="table-wrapper" id="ledgerWrapper">
                <table class="data-table" id="ledgerTable">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Event Details</th>
                            <th>Total Cost</th>
                            <th>Paid</th>
                            <th>Balance Due</th>
                            <th>Status</th>
                            <th class="td-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody id="ledgerBody">
                        <tr><td colspan="8"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="table-wrapper" id="refundsWrapper" style="display:none;">
                <table class="data-table" id="refundsTable">
                    <thead>
                        <tr>
                            <th>Client / Event</th>
                            <th>Total Paid</th>
                            <th>Forfeited</th>
                            <th>Refund Amount</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th class="td-actions">Action</th>
                        </tr>
                    </thead>
                    <tbody id="refundsBody">
                        <tr><td colspan="7"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payment history for selected booking -->
        <div class="card mt-3" id="paymentHistoryCard" style="display:none;">
            <div class="card-header">
                <div>
                    <div class="card-title" id="historyTitle">Payment History</div>
                    <div class="card-subtitle" id="historySubtitle"></div>
                </div>
                <div class="d-flex gap-2">
                    <a id="btnGenInvoice" href="#" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-file-invoice"></i> Generate Invoice
                    </a>
                    <button id="btnEmailInvoice" class="btn btn-outline-success btn-sm">
                        <i class="fas fa-paper-plane"></i> Email Invoice
                    </button>
                    <button class="btn btn-secondary btn-sm" onclick="closeHistory()">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <th class="td-actions">Del</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- RIGHT: Record Payment -->
    <div class="col-lg-4 order-1 order-lg-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-plus-circle me-2" style="color:var(--sys-green)"></i>Record Payment</div>
            </div>
            <div class="card-body">
                <form id="paymentForm">

                    <div class="form-group">
                        <label class="form-label" for="bookingSelect">Booking <span class="required">*</span></label>
                        <select class="form-control" name="booking_id" id="bookingSelect" required onchange="onBookingChange()" title="Select the booking to record payment for">
                            <option value="">Select booking…</option>
                        </select>
                    </div>

                    <!-- Balance Info Box -->
                    <div id="balanceBox" style="display:none; margin-bottom:14px;">
                        <div style="background:rgba(48,209,88,0.05); border:0.5px solid rgba(48,209,88,0.2); border-radius:12px; overflow:hidden;">
                            <div style="padding:12px 14px; border-bottom:0.5px solid rgba(48,209,88,0.12);">
                                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(60,60,67,0.4); margin-bottom:8px;">Balance Summary</div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <span style="font-size:13px; color:rgba(60,60,67,0.6);">Total Cost</span>
                                    <span style="font-size:13px; font-weight:600;" id="bi-total">—</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                    <span style="font-size:13px; color:rgba(60,60,67,0.6);">Amount Paid</span>
                                    <span style="font-size:13px; font-weight:600; color:#1A7A32;" id="bi-paid">—</span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center; padding-top:6px; border-top:0.5px solid rgba(48,209,88,0.15);">
                                    <span style="font-size:13px; font-weight:700;">Balance Due</span>
                                    <span style="font-size:15px; font-weight:800; color:#C0392B;" id="bi-remaining">—</span>
                                </div>
                            </div>
                            <!-- Quick fill button -->
                            <button type="button" id="fillFullBtn" onclick="fillFullBalance()"
                                style="display:block; width:100%; padding:9px; background:none; border:none; font-size:12px; font-weight:600; color:#1A7A32; cursor:pointer; transition:background 0.15s;"
                                onmouseover="this.style.background='rgba(48,209,88,0.06)'"
                                onmouseout="this.style.background='none'">
                                <i class="fas fa-bolt" style="margin-right:5px;"></i>Fill Full Balance
                            </button>
                        </div>
                        <!-- Payment status badge -->
                        <div style="text-align:center; margin-top:8px;" id="bi-status-wrap"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="amountInput">Payment Amount (₱) <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-prefix">₱</span>
                            <input type="text" class="form-control" name="amount" id="amountInput"
                                   required placeholder="0.00" data-restrict="price"
                                   title="Enter the amount to be recorded (numbers and decimal only)"
                                   oninput="validateAmount()">
                        </div>
                        <div id="amountError" style="font-size:11.5px; color:#C0392B; margin-top:4px; display:none;"></div>
                    </div>


                    <div class="form-group">
                        <label class="form-label">Payment Method <span class="required">*</span></label>
                        <select class="form-control" name="payment_method" required>
                            <option value="cash">💵 Cash</option>
                            <option value="gcash">📱 GCash</option>
                            <option value="maya">📱 Maya</option>
                            <option value="bank_transfer">🏦 Bank Transfer</option>
                        </select>
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Payment Date <span class="required">*</span></label>
                            <input type="date" class="form-control" name="payment_date" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reference No.</label>
                            <input type="text" class="form-control" name="reference_no" placeholder="GCash ref, trace no…" title="GCash Ref ID or Bank Transaction Trace ID">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full" id="recordPayBtn">
                        <i class="fas fa-check-circle"></i> Record Payment
                    </button>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
const preloadBookingId = <?= $preloadBookingId ?>;
let currentBalance = 0;


// ── SEARCH DEBOUNCE ────────────────────────────────────────────────────
let searchTimer;
document.getElementById('searchLedger').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        loadLedger();
    }, 300);
});

// ── INIT ───────────────────────────────────────────────────────────────
async function initFinancial() {
    await refreshBookingSelector();
    if (preloadBookingId) {
        document.getElementById('bookingSelect').value = preloadBookingId;
        await onBookingChange();
    }
    await loadLedger();
    await refreshStats();
}

// Rebuild the booking dropdown with fresh live balances from the API
async function refreshBookingSelector() {
    const d  = await Api.get(BASE + 'src/api/bookings.php', { limit: 1000 });
    const bs = (d.bookings || []).filter(b => b.booking_status !== 'cancelled');
    const sel = document.getElementById('bookingSelect');
    const cur = sel.value; // preserve current selection

    sel.innerHTML = '<option value="">Select booking…</option>' +
        bs.map(b => {
            const total   = parseFloat(b.total_cost);
            const paid    = parseFloat(b.amount_paid);
            const balance = total - paid;
            const balStr  = balance > 0.01
                ? ` — Balance: ${Format.peso(balance)}`
                : ' ✓ Paid';
            return `<option value="${b.id}"
                data-total="${total}" data-paid="${paid}" data-status="${b.payment_status}"
                ${b.id == cur ? 'selected' : ''}>
             ${esc(b.client_name)} (${Format.dateShort(b.event_date)})${balStr}
            </option>`;
        }).join('');
}

function getTimeframe() {
    return document.querySelector('input[name="tf"]:checked').value;
}

async function refreshStats() {
    const tf = getTimeframe();
    loadKPIs({ timeframe: tf });
    loadLedger(); 
}

// ── KPIs ───────────────────────────────────────────────────────────────
async function loadKPIs(params = {}) {
    try {
        const d = await Api.get(BASE + 'src/api/analytics.php', { type: 'kpis', ...params });
        document.getElementById('stat-total-rev').textContent   = Format.peso(d.total_revenue);
        document.querySelector('#stat-total-rev + .stat-label').textContent = 'Revenue (' + d.period_label + ')';
        
        document.getElementById('stat-mtd').textContent         = Format.peso(d.revenue_mtd);
        document.getElementById('stat-outstanding').textContent = Format.peso(d.outstanding);
        document.querySelector('#stat-outstanding + .stat-label').textContent = 'Outstanding (' + d.period_label + ')';
    } catch(e) {}
}

// ── BOOKING BALANCE PICKER ─ always reads LIVE data from payments API ──
async function onBookingChange() {
    const sel = document.getElementById('bookingSelect');
    const bid = sel.value;
    const box = document.getElementById('balanceBox');

    if (!bid) { box.style.display = 'none'; return; }

    // Fetch live balance from API (bypasses stale bookings.amount_paid)
    try {
        const d     = await Api.get(BASE + 'src/api/payments.php', { booking_id: bid });
        const b     = d.booking;

        // Re-compute from the SUM of actual payments returned
        const payments      = d.payments || [];
        const livePaid      = payments.reduce((s, p) => s + parseFloat(p.amount), 0);
        const breakageTotal = parseFloat(b.breakage_total || 0);
        const grandTotal    = parseFloat(b.total_cost);
        const eventTotal    = grandTotal - breakageTotal; 
        const remaining     = Math.max(0, grandTotal - livePaid);
        currentBalance      = remaining;

        document.getElementById('bi-total').innerHTML = `
            ${Format.peso(eventTotal)}
            ${breakageTotal > 0 ? `<div style="font-size:11px; color:var(--sys-orange); font-weight:600;">+ ${Format.peso(breakageTotal)} Breakage</div>` : ''}
        `;
        document.getElementById('bi-paid').textContent      = Format.peso(livePaid);
        document.getElementById('bi-remaining').textContent = Format.peso(remaining);
        document.getElementById('bi-remaining').style.color = remaining > 0.005 ? '#C0392B' : '#1A7A32';

        // Status badge — derive from live data
        const liveStatus = livePaid >= grandTotal - 0.01 ? 'paid'
                         : livePaid > 0                  ? 'partial' : 'unpaid';
        document.getElementById('bi-status-wrap').innerHTML = Format.paymentBadge(liveStatus);

        // Hide fill button if fully paid
        document.getElementById('fillFullBtn').style.display = remaining > 0.005 ? 'block' : 'none';

        // Set max on amount input
        const amtInput = document.getElementById('amountInput');
        amtInput.max   = remaining.toFixed(2);
        amtInput.value = '';
        document.getElementById('amountError').style.display = 'none';
        document.getElementById('recordPayBtn').disabled = false;

        box.style.display = 'block';

        // Auto-load history
        loadPaymentHistory(bid, { total_cost: eventTotal, amount_paid: livePaid });
    } catch(e) { Toast.error('Could not load booking balance.'); }
}


function fillFullBalance() {
    const inp = document.getElementById('amountInput');
    inp.value = currentBalance.toFixed(2);
    validateAmount();
}

function validateAmount() {
    const val = parseFloat(document.getElementById('amountInput').value) || 0;
    const errEl = document.getElementById('amountError');
    const btn   = document.getElementById('recordPayBtn');

    if (val <= 0) {
        errEl.textContent = 'Amount must be greater than ₱0.';
        errEl.style.display = 'block';
        btn.disabled = true;
    } else if (currentBalance > 0 && val > currentBalance + 0.01) {
        errEl.textContent = `Cannot exceed the remaining balance of ${Format.peso(currentBalance)}.`;
        errEl.style.display = 'block';
        btn.disabled = true;
    } else {
        errEl.style.display = 'none';
        btn.disabled = false;
    }
}



// ── LEDGER TABLE ───────────────────────────────────────────────────────
async function loadLedger() {
    const search  = document.getElementById('searchLedger').value;
    const status  = document.getElementById('filterStatus').value;
    const payment = document.getElementById('filterPayment').value;
    const tf      = getTimeframe();
    
    try {
        const params = { timeframe: tf };
        if (search)  params.search = search;
        if (status)  params.status = status;
        if (payment) params.payment_status = payment;
        
        const d = await Api.get(BASE + 'src/api/bookings.php', params);
        const bookings = d.bookings || [];
        const tbody = document.getElementById('ledgerBody');

        if (bookings.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8"><div class="table-empty">
                <i class="fas fa-receipt"></i><p>No bookings found.</p></div></td></tr>`;
            return;
        }

        tbody.innerHTML = bookings.map(b => {
            const total     = parseFloat(b.total_cost);
            const breakage  = parseFloat(b.breakage_total || 0);
            const paid      = parseFloat(b.amount_paid);
            const balance   = total - paid;
            const paidPct   = total > 0 ? Math.min(100, (paid / total) * 100) : 0;
            const isCancelled = b.booking_status === 'cancelled';

            const balColor = balance <= 0 ? '#1A7A32' : (paid > 0 ? '#9A5400' : '#C0392B');

            return `
            <tr>
                <td class="td-name">${esc(b.client_name)}<br><small class="text-muted">${Format.dateShort(b.event_date)}</small></td>
                <td>${b.package_name ?? b.menu_name ?? '—'}<br><small class="text-muted">${b.pax_count} pax</small></td>
                <td>
                    <div class="fw-600">${Format.peso(total)}</div>
                    ${breakage > 0 ? `<div style="font-size:10px; color:var(--sys-orange); font-weight:700;">Incl. ${Format.peso(breakage)} Loss</div>` : ''}
                </td>
                <td>
                    <span style="color:#1A7A32; font-weight:600;">${Format.peso(paid)}</span>
                    <div style="height:3px; background:rgba(60,60,67,0.08); border-radius:2px; margin-top:4px; width:70px;">
                        <div style="height:3px; background:${paidPct >= 100 ? '#30D158' : '#FF9500'}; border-radius:2px; width:${paidPct}%;"></div>
                    </div>
                </td>
                <td><span style="font-weight:700; color:${balColor};">${balance > 0 ? Format.peso(balance) : '✓ Fully Paid'}</span></td>
                <td>${Format.paymentBadge(b.payment_status)}<br>${Format.bookingBadge(b.booking_status)}</td>
                <td class="td-actions">
                    ${balance > 0 && !isCancelled ? `
                    <button class="btn btn-primary btn-sm py-3" onclick="quickPay(${b.id})" title="Add Payment">
                        <i class="fas fa-plus"></i>
                    </button>` : ''}
                    <button class="btn btn-outline-secondary btn-sm py-3" onclick="viewHistory(${b.id})" title="View Payments">
                        <i class="fas fa-list"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    } catch(e) {
        document.getElementById('ledgerBody').innerHTML =
            '<tr><td colspan="8" class="text-center text-muted p-4">Failed to load ledger.</td></tr>';
    }
}

// ── PAYMENT HISTORY ────────────────────────────────────────────────────
async function viewHistory(bookingId) {
    const d = await Api.get(BASE + 'src/api/bookings.php', { id: bookingId });
    loadPaymentHistory(bookingId, d.booking);
}

async function loadPaymentHistory(bookingId, bookingInfo) {
    const card = document.getElementById('paymentHistoryCard');
    const tbody = document.getElementById('historyBody');
    const btnInv = document.getElementById('btnGenInvoice');
    const btnEmail = document.getElementById('btnEmailInvoice');

    card.style.display = 'block';
    tbody.innerHTML = '<tr><td colspan="6"><div class="spinner"></div></td></tr>';

    if (bookingInfo) {
        // Update Links
        btnInv.href = `${BASE}/templates/invoice.php?booking_id=${bookingId}&token=${bookingInfo.invoice_token || ''}`;
        btnEmail.onclick = () => sendInvoice(bookingId);
        
        const total     = parseFloat(bookingInfo.total_cost);
        const breakage  = parseFloat(bookingInfo.breakage_total || 0);
        const eventCost = total - breakage;
        const paid      = parseFloat(bookingInfo.amount_paid);
        const balance   = total - paid;
        
        let sub = `Event: ${Format.peso(eventCost)}`;
        if (breakage > 0) sub += ` + Loss: ${Format.peso(breakage)}`;
        sub += ` | Paid: ${Format.peso(paid)} | Balance: ${Format.peso(Math.max(0, balance))}`;
        
        document.getElementById('historySubtitle').textContent = sub;
    }

    try {
        const d = await Api.get(BASE + 'src/api/payments.php', { booking_id: bookingId });
        const payments = d.payments || [];
        const methodLabel = { cash:'💵 Cash', gcash:'📱 GCash', maya:'📱 Maya', bank_transfer:'🏦 Bank' };

        if (payments.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6"><div class="table-empty">
                <i class="fas fa-money-bill-wave"></i><p>No payments recorded yet.</p></div></td></tr>`;
            return;
        }

        tbody.innerHTML = payments.map(p => `
            <tr>
                <td>${Format.dateShort(p.payment_date)}</td>
                <td class="fw-600" style="color:#1A7A32;">${Format.peso(p.amount)}</td>
                <td>${methodLabel[p.payment_method] || p.payment_method}</td>
                <td class="td-mono text-xs">${esc(p.reference_no || '—')}</td>
                <td class="text-muted text-sm">${esc(p.notes || '—')}</td>
                <td class="td-actions">
                    <button class="btn btn-danger btn-sm" onclick="deletePayment(${p.id}, ${bookingId})" title="Remove">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    } catch(e) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">Failed to load.</td></tr>'; }

    card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closeHistory() {
    document.getElementById('paymentHistoryCard').style.display = 'none';
}

// ── SEND INVOICE VIA EMAIL ─────────────────────────────────────────────
async function sendInvoice(bookingId) {
    if (!bookingId) return;
    
    const btn = document.getElementById('btnEmailInvoice');
    const originalHtml = btn.innerHTML;
    
    try {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        
        const res = await Api.post(BASE + 'src/api/send_invoice.php', { booking_id: bookingId });
        
        if (res.success) {
            Toast.success(res.message);
        } else {
            Toast.error(res.message);
        }
    } catch (e) {
        Toast.error('An unexpected error occurred while sending the email.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
    }
}

// ── QUICK PAY: select booking in the right panel and scroll ────────────
async function quickPay(bookingId) {
    const sel = document.getElementById('bookingSelect');
    sel.value = bookingId;
    await onBookingChange();
    document.getElementById('paymentForm').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── DELETE PAYMENT ─────────────────────────────────────────────────────
async function deletePayment(id, bookingId) {
    if (!await confirmDialog('Remove this payment? The balance will be automatically recalculated.')) return;
    try {
        await Api.delete(BASE + 'src/api/payments.php', { id });
        Toast.success('Payment removed. Balance updated.');
        await loadLedger();
        await loadKPIs();
        if (bookingId) await loadPaymentHistory(bookingId, null);
        await onBookingChange();
    } catch(e) { Toast.error(e.message); }
}

// ── RECORD PAYMENT FORM ────────────────────────────────────────────────
document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const bid    = document.getElementById('bookingSelect').value;
    const amount = parseFloat(document.getElementById('amountInput').value) || 0;
    if (!bid)      { Toast.error('Please select a booking.'); return; }
    if (amount <= 0) { Toast.error('Enter a valid payment amount.'); return; }
    if (currentBalance > 0.005 && amount > currentBalance + 0.01) {
        Toast.error(`Amount cannot exceed the remaining balance of ${Format.peso(currentBalance)}.`);
        return;
    }

    const btn = document.getElementById('recordPayBtn');
    Form.setLoading(btn, true);
    try {
        const res  = await Api.post(BASE + 'src/api/payments.php', Form.serialize(this));

        // Show success with updated balance from API response
        const newBalance = res.balance ?? 0;
        const invUrl = `${BASE}/templates/invoice.php?booking_id=${bid}&token=${res.invoice_token || ''}`;
        
        Toast.success(
            `Payment of ${Format.peso(amount)} recorded! ` +
            (newBalance > 0.005
                ? `Remaining balance: ${Format.peso(newBalance)}.`
                : 'Booking fully paid! ✅') +
            `<br><a href="${invUrl}" target="_blank" class="text-white fw-bold" style="text-decoration:underline;">View Updated Invoice</a>`
        );

        // Reset form but keep the date
        const today = new Date().toISOString().split('T')[0];
        this.reset();
        document.querySelector('#paymentForm [name="payment_date"]').value = today;
        document.getElementById('amountError').style.display = 'none';

        // Reload everything with live data
        await Promise.all([
            refreshBookingSelector(),
            loadLedger(),
            loadKPIs(),
        ]);

        // Re-select the same booking and refresh balance panel
        if (bid) {
            document.getElementById('bookingSelect').value = bid;
            await onBookingChange();
        } else {
            document.getElementById('balanceBox').style.display = 'none';
            currentBalance = 0;
        }

    } catch(e) {
        Toast.error(e.message);
    }
    Form.setLoading(btn, false);
});

// ── FILTERS ────────────────────────────────────────────────────────────
['filterStatus','filterPayment'].forEach(id => {
    document.getElementById(id).addEventListener('change', loadLedger);
});

function showTab(tab) {
    document.getElementById('ledgerWrapper').style.display  = tab === 'ledger' ? 'block' : 'none';
    document.getElementById('refundsWrapper').style.display = tab === 'refunds' ? 'block' : 'none';
    document.getElementById('tabLedgerBtn').classList.toggle('active', tab === 'ledger');
    document.getElementById('tabRefundBtn').classList.toggle('active', tab === 'refunds');
    
    if (tab === 'refunds') loadRefunds();
}

async function loadRefunds() {
    try {
        const d = await Api.get(BASE + 'src/api/cancellations.php');
        const refunds = d.cancellations || [];
        const tbody   = document.getElementById('refundsBody');

        if (refunds.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7"><div class="table-empty"><i class="fas fa-hand-holding-dollar"></i><p>No refund requests found.</p></div></td></tr>';
            return;
        }

        tbody.innerHTML = refunds.map(r => {
            let statusBadge = '';
            if (r.refund_status === 'pending') statusBadge = '<span class="badge badge-warning">⏳ Pending</span>';
            else if (r.refund_status === 'processed') statusBadge = '<span class="badge badge-success">✅ Processed</span>';
            else if (r.refund_status === 'reversed') statusBadge = '<span class="badge badge-info">🔄 Reversed</span>';
            else statusBadge = '<span class="badge badge-secondary">⚪ Waived</span>';

            return `
                <tr>
                    <td class="td-name">${esc(r.client_name)}<br><small class="text-muted">${Format.dateShort(r.event_date)}</small></td>
                    <td class="fw-600">${Format.peso(r.total_paid)}</td>
                    <td style="color:#C0392B;">${Format.peso(r.forfeited_amount)}</td>
                    <td style="font-weight:700; color:#1A7A32;">${Format.peso(r.refundable_amount)}</td>
                    <td><small class="text-muted">${esc(r.reason || '—')}</small></td>
                    <td>${statusBadge}</td>
                    <td class="td-actions">
                        ${r.refund_status === 'pending' ? `
                            <button class="btn btn-success btn-sm py-3" onclick="processRefund(${r.id}, ${r.refundable_amount})" title="Process Refund">
                                <i class="fas fa-check"></i>
                            </button>
                        ` : `
                            <button class="btn btn-outline-secondary btn-sm py-3" disabled>
                                <i class="fas fa-lock"></i>
                            </button>
                        `}
                        ${r.refund_status !== 'processed' ? `
                            <button class="btn btn-outline-danger btn-sm py-3 ms-1" onclick="waiveRefund(${r.id})" title="Waive Refund">
                                <i class="fas fa-times"></i>
                            </button>
                        ` : ''}
                    </td>
                </tr>
            `;
        }).join('');
    } catch(e) {
        document.getElementById('refundsBody').innerHTML = '<tr><td colspan="7" class="text-center text-muted">Failed to load refunds.</td></tr>';
    }
}

async function waiveRefund(id) {
    if (!await confirmDialog('Are you sure you want to waive this refund? No money will be returned to the client.')) return;
    try {
        await Api.put(BASE + 'src/api/cancellations.php', { id: id, refund_status: 'waived' });
        Toast.success('Refund waived.');
        loadRefunds();
    } catch(e) { Toast.error(e.message); }
}

async function processRefund(id, amount) {
    let html = `
        <div style="text-align:left;">
            <p>You are about to mark a refund as processed. Please confirm the amount and method.</p>
            <div class="form-group mb-3">
                <label class="form-label">Refund Amount (₱) <span class="required">*</span></label>
                <div class="input-group">
                    <span class="input-prefix">₱</span>
                    <input type="text" class="form-control" id="rf_amount" value="${amount.toFixed(2)}" placeholder="0.00" data-restrict="price">
                </div>
                <small class="text-muted">Must match exactly ₱${amount.toLocaleString()}</small>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Refund Method</label>
                <select class="form-control" id="rf_method">
                    <option value="cash">💵 Cash</option>
                    <option value="gcash">📱 GCash</option>
                    <option value="maya">📱 Maya</option>
                    <option value="bank_transfer">🏦 Bank Transfer</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Reference Number</label>
                <input type="text" class="form-control" id="rf_ref" placeholder="GCash Ref ID, Bank Trace, etc.">
            </div>
        </div>
    `;

    const result = await CustomConfirm.show({
        title: 'Process Refund',
        html: html,
        confirmText: 'Mark as Processed',
        confirmColor: 'var(--sys-green)'
    });

    if (!result) return;
    
    const enteredAmt = parseFloat(result.rf_amount) || 0;
    if (Math.abs(enteredAmt - amount) > 0.01) {
        Toast.error(`Refund amount must be exactly ${Format.peso(amount)}.`);
        return;
    }

    try {
        const method = result.rf_method;
        const ref    = result.rf_ref;
        
        await Api.put(BASE + 'src/api/cancellations.php', {
            id: id,
            refund_status: 'processed',
            refund_method: method,
            refund_reference: ref
        });
        
        Toast.success('Refund marked as processed.');
        loadRefunds();
        loadKPIs(); // Refresh outstanding balance if applicable
    } catch(e) { Toast.error(e.message); }
}


initFinancial();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
