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
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <!-- Tab Toggle -->
                    <div class="btn-group me-2">
                        <button class="btn btn-outline-primary btn-sm active" id="tabLedgerBtn" onclick="showTab('ledger')" style="border-radius:var(--r-pill) 0 0 var(--r-pill);">
                            <i class="fas fa-list"></i> Payments
                        </button>
                        <button class="btn btn-outline-primary btn-sm" id="tabRefundBtn" onclick="showTab('refunds')" style="border-radius:0 var(--r-pill) var(--r-pill) 0;">
                            <i class="fas fa-hand-holding-dollar"></i> Refunds
                        </button>
                    </div>

                    <!-- Search Bar -->
                    <div class="d-flex align-items-center gap-2" style="flex: 2; min-width: 250px;">
                        <div style="font-size:10px; font-weight:700; color:var(--label-3); text-transform:uppercase; margin-right:2px; white-space:nowrap;">Search</div>
                        <div class="search-input-wrap flex-grow-1">
                            <i class="fas fa-search"></i>
                            <input type="text" class="search-input" id="searchLedger" placeholder="Quick search client name...">
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2" style="flex: 1; min-width: 150px;">
                        <div style="font-size:10px; font-weight:700; color:var(--label-3); text-transform:uppercase; margin-right:2px; white-space:nowrap;">Status</div>
                        <select class="form-control" id="filterStatus" style="border-radius:var(--r-pill); font-size:13px;" title="Filter by booking status">
                            <option value="">All Statuses</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>

                    <div class="d-flex align-items-center gap-2" style="flex: 1; min-width: 140px;">
                        <div style="font-size:10px; font-weight:700; color:var(--label-3); text-transform:uppercase; margin-right:2px; white-space:nowrap;">Payment</div>
                        <select class="form-control" id="filterPayment" style="border-radius:var(--r-pill); font-size:13px;" title="Filter by payment status">
                            <option value="">All Payments</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="table-wrapper table-responsive" id="ledgerWrapper" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
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
            <!-- Ledger Pagination -->
            <div class="table-pagination" id="ledgerPaginationBar">
                <button type="button" class="pagination-button" id="ledgerPrevBtn"
                    onclick="changeLedgerPage(currentLedgerPage - 1)" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-info" id="ledgerPageInfo">Page 1 of 1</div>
                <button type="button" class="pagination-button" id="ledgerNextBtn"
                    onclick="changeLedgerPage(currentLedgerPage + 1)" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>

            <div class="table-wrapper table-responsive" id="refundsWrapper" style="display:none; overflow-x: auto; -webkit-overflow-scrolling: touch;">
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
            
            <!-- Refunds Pagination -->
            <div class="table-pagination" id="refundsPaginationBar" style="display:none;">
                <button type="button" class="pagination-button" id="refundsPrevBtn"
                    onclick="changeRefundPage(currentRefundPage - 1)" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-info" id="refundsPageInfo">Page 1 of 1</div>
                <button type="button" class="pagination-button" id="refundsNextBtn"
                    onclick="changeRefundPage(currentRefundPage + 1)" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
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
            <div class="table-wrapper table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Notes</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th class="td-actions">Del</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody id="historyBody"></tbody>
                </table>
            </div>
            <!-- History Pagination -->
            <div class="table-pagination" id="historyPaginationBar">
                <button type="button" class="pagination-button" id="historyPrevBtn"
                    onclick="changeHistoryPage(currentHistoryPage - 1)" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-info" id="historyPageInfo">Page 1 of 1</div>
                <button type="button" class="pagination-button" id="historyNextBtn"
                    onclick="changeHistoryPage(currentHistoryPage + 1)" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
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

                <!-- ── Payment Mode Toggle ── -->
                <div class="form-group" style="margin-bottom:16px;">
                    <label class="form-label" style="margin-bottom:8px;">Payment Mode <span class="required">*</span></label>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                        <button type="button" id="modeCashBtn" onclick="setPayMode('cash')"
                            style="padding:12px 8px; border-radius:12px; border:1.5px solid var(--sys-green);
                                   background:rgba(48,209,88,0.08); color:var(--sys-green-dark);
                                   font-weight:700; font-size:13px; cursor:pointer; transition:all .18s;
                                   display:flex; align-items:center; justify-content:center; gap:7px;">
                            <i class="fas fa-money-bill-wave"></i> Cash / Manual
                        </button>
                        <button type="button" id="modeOnlineBtn" onclick="setPayMode('online')"
                            style="padding:12px 8px; border-radius:12px; border:1.5px solid rgba(60,60,67,0.15);
                                   background:transparent; color:rgba(60,60,67,0.45);
                                   font-weight:700; font-size:13px; cursor:pointer; transition:all .18s;
                                   display:flex; align-items:center; justify-content:center; gap:7px;">
                            <i class="fas fa-link"></i> PayMongo Online
                        </button>
                    </div>
                    <div style="font-size:11px; color:rgba(60,60,67,0.4); text-align:center; margin-top:6px;" id="payModeHint">
                        Manually record cash, GCash, Maya, or bank transfer.
                    </div>
                </div>

                <!-- Booking Selector — shared by both modes -->
                <div class="form-group">
                    <label class="form-label" for="bookingSelect">Booking <span class="required">*</span></label>
                    <select class="form-control" name="booking_id" id="bookingSelect" required onchange="onBookingChange()" title="Select the booking to record payment for">
                        <option value="">Select booking…</option>
                    </select>
                </div>

                <!-- Balance Info Box — shared by both modes -->
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
                        <!-- Quick fill — only relevant in cash mode -->
                        <button type="button" id="fillFullBtn" onclick="fillFullBalance()"
                            style="display:block; width:100%; padding:9px; background:none; border:none; font-size:12px; font-weight:600; color:#1A7A32; cursor:pointer; transition:background 0.15s;"
                            onmouseover="this.style.background='rgba(48,209,88,0.06)'"
                            onmouseout="this.style.background='none'">
                            <i class="fas fa-bolt" style="margin-right:5px;"></i>Fill Full Balance
                        </button>
                    </div>
                    <div style="text-align:center; margin-top:8px;" id="bi-status-wrap"></div>
                </div>

                <!-- ── CASH / MANUAL FIELDS ── -->
                <div id="cashFields">
                    <form id="paymentForm">
                        <div class="form-group">
                            <label class="form-label" for="amountInput">Payment Amount (&#8369;) <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-prefix">&#8369;</span>
                                <input type="text" class="form-control" name="amount" id="amountInput"
                                       required placeholder="0.00" data-restrict="price"
                                       title="Enter the amount to be recorded (numbers and decimal only)"
                                       oninput="validateAmount()">
                            </div>
                            <div id="amountError" style="font-size:11.5px; color:#C0392B; margin-top:4px; display:none;"></div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Payment Method <span class="required">*</span></label>
                            <select class="form-control" name="payment_method" id="paymentMethodSel" required>
                                <option value="cash">&#x1F4B5; Cash</option>
                                <option value="gcash">&#x1F4F1; GCash</option>
                                <option value="maya">&#x1F4F1; Maya</option>
                                <option value="bank_transfer">&#x1F3E6; Bank Transfer</option>
                            </select>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group">
                                <label class="form-label">Payment Date <span class="required">*</span></label>
                                <input type="date" class="form-control" name="payment_date" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Reference No.</label>
                                <input type="text" class="form-control" name="reference_no" id="refNoInput" placeholder="GCash ref, trace no…" title="GCash Ref ID or Bank Transaction Trace ID">
                            </div>
                        </div>

                        <!-- booking_id is submitted via the form's select above (name=booking_id on bookingSelect) -->
                        <button type="submit" class="btn btn-primary btn-full" id="recordPayBtn">
                            <i class="fas fa-check-circle"></i> Record Payment
                        </button>
                    </form>
                </div>

                <!-- ── PAYMONGO ONLINE PANEL ── -->
                <div id="onlineFields" style="display:none;">
                    <div style="background:linear-gradient(135deg,rgba(48,209,88,0.07),rgba(37,162,68,0.04));
                                border:1px solid rgba(48,209,88,0.20); border-radius:14px; padding:20px;
                                text-align:center; margin-bottom:16px;">
                        <div style="font-size:28px; margin-bottom:10px;">&#x1F517;</div>
                        <div style="font-weight:800; font-size:15px; color:#1C1C1E; margin-bottom:6px;">Send PayMongo Payment Link</div>
                        <div style="font-size:12px; color:rgba(60,60,67,0.55); line-height:1.55;">
                            Generates a secure checkout link for the outstanding balance.
                            The client pays via <strong>GCash</strong>, <strong>Maya</strong>, or <strong>Card</strong>.
                            The ledger updates automatically on payment.
                        </div>
                        <div style="display:flex; gap:6px; justify-content:center; margin-top:12px; flex-wrap:wrap;">
                            <span style="padding:3px 10px; background:rgba(48,209,88,0.12); border:1px solid rgba(48,209,88,0.25); border-radius:99px; font-size:11px; font-weight:700; color:#25A244;">GCash</span>
                            <span style="padding:3px 10px; background:rgba(48,209,88,0.12); border:1px solid rgba(48,209,88,0.25); border-radius:99px; font-size:11px; font-weight:700; color:#25A244;">Maya</span>
                            <span style="padding:3px 10px; background:rgba(48,209,88,0.12); border:1px solid rgba(48,209,88,0.25); border-radius:99px; font-size:11px; font-weight:700; color:#25A244;">Credit / Debit Card</span>
                        </div>
                    </div>
                    <div class="form-group" style="margin-bottom:14px; text-align:left;">
                        <label class="form-label" for="onlineAmountInput">Payment Amount (&#8369;) <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-prefix">&#8369;</span>
                            <input type="text" class="form-control" id="onlineAmountInput"
                                   required placeholder="0.00" data-restrict="price"
                                   title="Enter the amount to charge via PayMongo"
                                   oninput="validateOnlineAmount()">
                        </div>
                        <div id="onlineAmountError" style="font-size:11.5px; color:#C0392B; margin-top:4px; display:none;"></div>
                    </div>

                    <button type="button" class="btn btn-success btn-full" id="sendPayLinkBtn"
                            onclick="sendOnlinePaymentLink()" disabled>
                        <i class="fas fa-link"></i> Generate &amp; Copy Payment Link
                    </button>
                    <div style="font-size:11px; color:rgba(60,60,67,0.4); text-align:center; margin-top:8px;" id="onlineHint">
                        Select a booking above to enable.
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
const USER_ROLE = "<?= $_SESSION['role'] ?? '' ?>";
const isAdmin   = USER_ROLE === 'admin';

const preloadBookingId = <?= $preloadBookingId ?>;
let currentBalance = 0;

// ── LEDGER PAGINATION STATE ────────────────────────────────────────────
let currentLedgerPage = 1;
let ledgerTotalPages  = 1;
const LEDGER_LIMIT    = 10;

function changeLedgerPage(page) {
    currentLedgerPage = Math.max(1, Math.min(ledgerTotalPages, page));
    loadLedger();
}

function renderLedgerPagination(meta) {
    ledgerTotalPages = meta.totalPages || 1;
    document.getElementById('ledgerPageInfo').textContent =
        `Page ${meta.currentPage} of ${ledgerTotalPages}`;
    document.getElementById('ledgerPrevBtn').disabled = currentLedgerPage <= 1;
    document.getElementById('ledgerNextBtn').disabled = currentLedgerPage >= ledgerTotalPages;
}

// ── REFUNDS PAGINATION STATE ───────────────────────────────────────────
let currentRefundPage = 1;
let refundTotalPages  = 1;
const REFUND_LIMIT    = 10;

function changeRefundPage(page) {
    currentRefundPage = Math.max(1, Math.min(refundTotalPages, page));
    loadRefunds();
}

function renderRefundsPagination(meta) {
    refundTotalPages = meta.totalPages || 1;
    document.getElementById('refundsPageInfo').textContent =
        `Page ${meta.currentPage} of ${refundTotalPages}`;
    document.getElementById('refundsPrevBtn').disabled = currentRefundPage <= 1;
    document.getElementById('refundsNextBtn').disabled = currentRefundPage >= refundTotalPages;
}

// ── HISTORY PAGINATION STATE ───────────────────────────────────────────
let currentHistoryPage = 1;
let historyTotalPages  = 1;
let currentHistoryBid  = null;
const HISTORY_LIMIT    = 10;

function changeHistoryPage(page) {
    currentHistoryPage = Math.max(1, Math.min(historyTotalPages, page));
    loadPaymentHistory(currentHistoryBid);
}

function renderHistoryPagination(meta) {
    historyTotalPages = meta.totalPages || 1;
    document.getElementById('historyPageInfo').textContent =
        `Page ${meta.currentPage} of ${historyTotalPages}`;
    document.getElementById('historyPrevBtn').disabled = currentHistoryPage <= 1;
    document.getElementById('historyNextBtn').disabled = currentHistoryPage >= historyTotalPages;
}

// ── SEARCH DEBOUNCE ────────────────────────────────────────────────────
let searchTimer;
document.getElementById('searchLedger').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        currentLedgerPage = 1;
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
    currentLedgerPage = 1;
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
        const wrap = document.getElementById('bi-status-wrap');
        if (currentBalance <= 0) {
            wrap.innerHTML = `<span class="badge bg-success-subtle text-success-emphasis" style="font-size:12px; padding:6px 12px;"><i class="fas fa-check-circle me-1"></i> Fully Paid</span>`;
            document.getElementById('cashFields').style.opacity = '0.5';
            document.getElementById('amountInput').disabled = true;
            document.getElementById('recordPayBtn').disabled = true;
            
            document.getElementById('onlineFields').style.opacity = '0.5';
            document.getElementById('onlineAmountInput').disabled = true;
            document.getElementById('sendPayLinkBtn').disabled = true;
        } else {
            wrap.innerHTML = Format.paymentBadge(liveStatus);
            document.getElementById('cashFields').style.opacity = '1';
            document.getElementById('amountInput').disabled = false;
            document.getElementById('recordPayBtn').disabled = false;
            document.getElementById('amountInput').value = currentBalance.toFixed(2);
            
            document.getElementById('onlineFields').style.opacity = '1';
            document.getElementById('onlineAmountInput').disabled = false;
            document.getElementById('sendPayLinkBtn').disabled = false;
            document.getElementById('onlineAmountInput').value = currentBalance.toFixed(2);
        }

        // Hide fill button if fully paid
        document.getElementById('fillFullBtn').style.display = remaining > 0.005 ? 'block' : 'none';

        // Set max on amount input
        const amtInput = document.getElementById('amountInput');
        amtInput.max   = remaining.toFixed(2);
        document.getElementById('amountError').style.display = 'none';

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

    // Show spinner
    document.getElementById('ledgerBody').innerHTML =
        '<tr><td colspan="8"><div class="spinner"></div></td></tr>';
    document.getElementById('ledgerPrevBtn').disabled = true;
    document.getElementById('ledgerNextBtn').disabled = true;

    try {
        const params = {
            page:  currentLedgerPage,
            limit: LEDGER_LIMIT,
        };
        if (search)  params.search = search;
        if (status)  params.status = status;
        if (payment) params.payment_status = payment;

        const d = await Api.get(BASE + 'src/api/bookings.php', params);
        const bookings = d.bookings || [];
        renderLedgerPagination(d.meta || { currentPage: 1, totalPages: 1, totalRecords: bookings.length });

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
                    <div class="btn-group" role="group">
                        <button class="btn btn-primary btn-sm py-3" onclick="quickPay(${b.id})" title="Record Cash / Manual Payment">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-success btn-sm py-3" onclick="quickPayOnline(${b.id})" title="Send PayMongo Payment Link">
                            <i class="fas fa-link"></i>
                        </button>
                        <button class="btn btn-dark btn-sm py-3" onclick="quickPayQR(${b.id})" title="Show QR Code for Payment">
                            <i class="fas fa-qrcode"></i>
                        </button>
                    </div>` : ''}
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
    currentHistoryPage = 1;
    loadPaymentHistory(bookingId);
}

async function loadPaymentHistory(bookingId, bookingInfo = null) {
    if (!bookingId) return;
    currentHistoryBid = bookingId;

    const card = document.getElementById('paymentHistoryCard');
    const tbody = document.getElementById('historyBody');
    const btnInv = document.getElementById('btnGenInvoice');
    const btnEmail = document.getElementById('btnEmailInvoice');

    card.style.display = 'block';
    tbody.innerHTML = '<tr><td colspan="6"><div class="spinner"></div></td></tr>';

    try {
        const d = await Api.get(BASE + 'src/api/payments.php', { 
            booking_id: bookingId,
            page: currentHistoryPage,
            limit: HISTORY_LIMIT
        });
        
        const b = bookingInfo || d.booking;
        if (b) {
            // Update Links
            btnInv.href = `${BASE}/templates/invoice.php?booking_id=${bookingId}&token=${b.invoice_token || ''}`;
            btnEmail.onclick = () => sendInvoice(bookingId);
            
            const total     = parseFloat(b.total_cost);
            const breakage  = parseFloat(b.breakage_total || 0);
            const eventCost = total - breakage;
            const paid      = parseFloat(b.amount_paid);
            const balance   = total - paid;
            
            let sub = `Event: ${Format.peso(eventCost)}`;
            if (breakage > 0) sub += ` + Loss: ${Format.peso(breakage)}`;
            sub += ` | Paid: ${Format.peso(paid)} | Balance: ${Format.peso(Math.max(0, balance))}`;
            
            document.getElementById('historySubtitle').textContent = sub;
        }

        const payments = d.payments || [];
        renderHistoryPagination(d.meta || { currentPage: 1, totalPages: 1 });

        const methodLabel = { cash:'💵 Cash', gcash:'📱 GCash', maya:'📱 Maya', bank_transfer:'🏦 Bank', paymongo:'🔗 PayMongo' };

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
                ${isAdmin ? `
                <td class="td-actions">
                    <button class="btn btn-danger btn-sm" onclick="deletePayment(${p.id}, ${bookingId})" title="Remove">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
                ` : ''}
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
    Form.setLoading(btn, true, 'Sending...');
    
    try {
        const res = await Api.post(BASE + 'src/api/send_invoice.php', { booking_id: bookingId });
        Toast.success(res.message || 'Invoice sent successfully.');
    } catch (e) {
        Toast.error(e.message || 'An unexpected error occurred while sending the email.');
    } finally {
        Form.setLoading(btn, false);
    }
}

// ── QUICK PAY: Cash mode — select booking and switch to Cash tab ───────
async function quickPay(bookingId) {
    setPayMode('cash');
    const sel = document.getElementById('bookingSelect');
    sel.value = bookingId;
    await onBookingChange();
    document.getElementById('cashFields').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ── QUICK PAY ONLINE: switch to PayMongo mode and pre-select booking ────
async function quickPayOnline(bookingId) {
    setPayMode('online');
    const sel = document.getElementById('bookingSelect');
    sel.value = bookingId;
    await onBookingChange();
    document.getElementById('onlineFields').scrollIntoView({ behavior: 'smooth', block: 'start' });
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
        const payload = Form.serialize(this);
        payload.booking_id = bid; // Inject booking_id (select is outside the form)
        
        const res  = await Api.post(BASE + 'src/api/payments.php', payload);

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
    document.getElementById(id).addEventListener('change', () => {
        currentLedgerPage = 1;
        loadLedger();
    });
});

function showTab(tab) {
    document.getElementById('ledgerWrapper').style.display  = tab === 'ledger' ? 'block' : 'none';
    document.getElementById('ledgerPaginationBar').style.display = tab === 'ledger' ? 'flex' : 'none';
    
    document.getElementById('refundsWrapper').style.display = tab === 'refunds' ? 'block' : 'none';
    document.getElementById('refundsPaginationBar').style.display = tab === 'refunds' ? 'flex' : 'none';
    
    document.getElementById('tabLedgerBtn').classList.toggle('active', tab === 'ledger');
    document.getElementById('tabRefundBtn').classList.toggle('active', tab === 'refunds');
    
    if (tab === 'refunds') loadRefunds();
}

async function loadRefunds() {
    // Show spinner
    document.getElementById('refundsBody').innerHTML =
        '<tr><td colspan="7"><div class="spinner"></div></td></tr>';
    document.getElementById('refundsPrevBtn').disabled = true;
    document.getElementById('refundsNextBtn').disabled = true;

    try {
        const params = {
            page:  currentRefundPage,
            limit: REFUND_LIMIT,
        };
        const d = await Api.get(BASE + 'src/api/cancellations.php', params);
        const refunds = d.cancellations || [];
        renderRefundsPagination(d.meta || { currentPage: 1, totalPages: 1, totalRecords: refunds.length });

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


// ── PAYMENT MODE TOGGLE ────────────────────────────────────────────────
let currentPayMode = 'cash';

function setPayMode(mode) {
    currentPayMode = mode;
    const cashBtn    = document.getElementById('modeCashBtn');
    const onlineBtn  = document.getElementById('modeOnlineBtn');
    const cashFields = document.getElementById('cashFields');
    const onlineFields = document.getElementById('onlineFields');
    const hint       = document.getElementById('payModeHint');

    if (mode === 'cash') {
        // Active style for Cash button
        cashBtn.style.border     = '1.5px solid var(--sys-green)';
        cashBtn.style.background = 'rgba(48,209,88,0.08)';
        cashBtn.style.color      = 'var(--sys-green-dark)';
        // Inactive style for Online button
        onlineBtn.style.border     = '1.5px solid rgba(60,60,67,0.15)';
        onlineBtn.style.background = 'transparent';
        onlineBtn.style.color      = 'rgba(60,60,67,0.45)';
        // Show/hide panels
        cashFields.style.display   = 'block';
        onlineFields.style.display = 'none';
        hint.textContent = 'Manually record cash, GCash, Maya, or bank transfer.';
        // Show fill-full button (only relevant for manual entry)
        const ffb = document.getElementById('fillFullBtn');
        if (ffb) ffb.style.display = currentBalance > 0.005 ? 'block' : 'none';
    } else {
        // Active style for Online button
        onlineBtn.style.border     = '1.5px solid #25A244';
        onlineBtn.style.background = 'rgba(48,209,88,0.08)';
        onlineBtn.style.color      = '#25A244';
        // Inactive style for Cash button
        cashBtn.style.border     = '1.5px solid rgba(60,60,67,0.15)';
        cashBtn.style.background = 'transparent';
        cashBtn.style.color      = 'rgba(60,60,67,0.45)';
        // Show/hide panels
        cashFields.style.display   = 'none';
        onlineFields.style.display = 'block';
        hint.textContent = 'Generate a secure PayMongo link and share it with the client.';
        // Hide fill-full in online mode (no manual amount entry)
        const ffb = document.getElementById('fillFullBtn');
        if (ffb) ffb.style.display = 'none';
        // Enable/disable the send button based on whether a booking is selected
        const bid = document.getElementById('bookingSelect').value;
        const linkBtn = document.getElementById('sendPayLinkBtn');
        linkBtn.disabled = !bid || currentBalance <= 0.01;
    }
}

// ── PAYMONGO ONLINE: Generate & Copy link ─────────────────────────────
async function sendOnlinePaymentLink() {
    const bid     = document.getElementById('bookingSelect').value;
    const linkBtn = document.getElementById('sendPayLinkBtn');
    const amtStr  = document.getElementById('onlineAmountInput').value;
    const amt     = parseFloat(amtStr) || 0;
    
    if (!bid) { Toast.error('Please select a booking first.'); return; }
    if (amt <= 0) { Toast.error('Please enter a valid payment amount.'); return; }
    if (currentBalance > 0.005 && amt > currentBalance + 0.01) {
        Toast.error(`Amount cannot exceed the remaining balance of ${Format.peso(currentBalance)}.`);
        return;
    }

    const originalHTML = linkBtn.innerHTML;
    linkBtn.disabled = true;
    linkBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating link…';

    try {
        const res = await Api.post(BASE + 'src/api/paymongo_checkout.php', {
            booking_id: parseInt(bid, 10),
            override_amount: Math.round(amt * 100), // Backend expects centavos
            origin: 'financial'
        });

        if (!res.checkout_url) throw new Error('No checkout URL returned from PayMongo.');

        Toast.success(
            '🔗 Payment Gateway opened in a new tab! ' +
            'Amount: ' + Format.peso(res.amount)
        );
        window.open(res.checkout_url, '_blank');

        document.getElementById('onlineHint').textContent =
            '✓ Session: ' + res.checkout_session_id.slice(-12) +
            ' | Amt: ' + Format.peso(res.amount);

    } catch (e) {
        Toast.error(e.message || 'Failed to generate payment link.');
    } finally {
        linkBtn.innerHTML = originalHTML;
        linkBtn.disabled  = currentBalance <= 0.01;
    }
}

// Patch onBookingChange to also toggle the online send-button state
const _origOnBookingChange = onBookingChange;
onBookingChange = async function() {
    await _origOnBookingChange();
    const bid     = document.getElementById('bookingSelect').value;
    const linkBtn = document.getElementById('sendPayLinkBtn');
    if (linkBtn) {
        linkBtn.disabled = !bid || currentBalance <= 0.01;
        document.getElementById('onlineHint').textContent =
            bid && currentBalance > 0.01
                ? 'Ready — click above to generate the link.'
                : 'Select a booking above to enable.';
    }
};


/**
 * QR Code Generation logic
 */
async function quickPayQR(bid) {
    const btn = event.currentTarget;
    const origHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    try {
        const res = await Api.post(BASE + 'src/api/paymongo_checkout.php', {
            booking_id: parseInt(bid, 10),
            origin: 'financial'
        });

        if (!res.checkout_url) throw new Error('No checkout URL returned.');
        
        showQRModal(res.checkout_url, res.amount);
    } catch (e) {
        Toast.error(e.message || 'Failed to generate QR code.');
    } finally {
        btn.innerHTML = origHTML;
        btn.disabled = false;
    }
}

function showQRModal(url, amount) {
    const modalEl = document.getElementById('qrModal');
    const imgEl   = document.getElementById('qrImage');
    const amtEl   = document.getElementById('qrAmount');
    
    // Use goqr.me API for instant QR generation
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(url)}`;
    
    imgEl.src = qrUrl;
    amtEl.textContent = Format.peso(amount);
    
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
}

initFinancial();
</script>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 400px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Scan to Pay</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-2 pb-4">
                <p class="text-muted small mb-4">Customer can scan this with GCash, Maya, or any QRPh app to complete the payment.</p>
                
                <div class="p-3 bg-white d-inline-block rounded shadow-sm mb-3">
                    <img id="qrImage" src="" alt="QR Code" style="width: 250px; height: 250px;">
                </div>
                
                <div class="mt-2">
                    <span class="text-muted d-block small">Amount to Pay</span>
                    <h3 class="fw-bold text-success mb-0" id="qrAmount">₱0.00</h3>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0 justify-content-center">
                <button type="button" class="btn btn-light w-100" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
