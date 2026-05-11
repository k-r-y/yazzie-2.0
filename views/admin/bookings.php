<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Manage Bookings';
$pageSubtitle = 'View, create, and manage all event bookings';
$activePage   = 'bookings';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="card mb-4">
    <div class="card-body" style="padding:16px 20px;">
        
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search by client, location">
            </div>

            <div class="d-flex align-items-center gap-2">
                <div style="font-size:10px; font-weight:700; color:var(--label-3); text-transform:uppercase; margin-right:2px; white-space:nowrap;">Status</div>
                <select class="form-control" id="filterStatus" style="width:150px; border-radius:var(--r-pill); font-size:13.5px;">
                    <option value="">All Statuses</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div style="font-size:10px; font-weight:700; color:var(--label-3); text-transform:uppercase; margin-right:2px; white-space:nowrap;">Order</div>
                <select class="form-control" id="filterOrder" style="width:160px; border-radius:var(--r-pill); font-size:13.5px;">
                    <option value="ASC">Upcoming First</option>
                    <option value="DESC">Latest First</option>
                </select>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div style="font-size:10px; font-weight:700; color:var(--label-3); text-transform:uppercase; margin-right:2px; white-space:nowrap;">Payment</div>
                <select class="form-control" id="filterPayment" style="width:150px; border-radius:var(--r-pill); font-size:13.5px;">
                    <option value="">All Payments</option>
                    <option value="unpaid">Unpaid Only</option>
                    <option value="partial">Partial Only</option>
                    <option value="paid">Paid Only</option>
                </select>
            </div>

            <button class="btn btn-primary" onclick="openBookingStepper()" style="border-radius:var(--r-pill); padding: 0 24px; height: 42px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fas fa-plus"></i>
                <span>New Booking</span>
            </button>
        </div>
    </div>
</div>

<!-- BOOKINGS TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">All Bookings</div>
        <span class="text-muted text-sm" id="bookingCount"></span>
    </div>
    <div class="table-wrapper">
        <div class="table-responsive">
        <table class="data-table" id="bookingsTable">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Event Date</th>
                    <th>Pax</th>
                    <th>Total</th>
                    <th colspan="2">Paid / Balance</th>
                    <th>Status</th>
                    <th class="td-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="bookingsBody">
                <tr><td colspan="9"><div class="spinner"></div></td></tr>
            </tbody>
        </table>
        </div>
    </div>
    <div class="table-pagination" id="bookingsPagination">
        <button type="button" class="pagination-button" id="bookingPrevBtn" onclick="changeBookingPage(currentBookingPage - 1)" disabled>
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <div class="pagination-info" id="bookingPageInfo">Page 1 of 1</div>
        <button type="button" class="pagination-button" id="bookingNextBtn" onclick="changeBookingPage(currentBookingPage + 1)" disabled>
            Next <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<?php
$bookingStepperRole = 'admin';
include __DIR__ . '/../../includes/_booking_stepper.php';
?>

<!-- VIEW BOOKING INFO MODAL (Operational Dashboard) -->
<div class="modal fade" id="viewBookingModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" style="border-radius:24px; overflow:hidden; border:none; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #1a1a1a 0%, #333 100%); color:white; padding:20px 30px; border:none; position:relative;">
                <div style="display:flex; align-items:center; gap:15px; padding-right:40px;">
                    <div style="width:48px; height:48px; background:rgba(255,255,255,0.1); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="margin:0; font-weight:700; font-size:20px; letter-spacing:-0.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:600px;">Booking <span id="view_booking_id_title"></span></h5>
                        <div id="view_status_badge" style="margin-top:4px;"></div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="position:absolute; right:25px; top:35px;"></button>
            </div>
            <div class="modal-body" style="padding:0; background:#f8f9fa;">
                <div style="display:grid; grid-template-columns: 350px 1fr; min-height:600px;">
                    
                    <!-- SIDEBAR: KEY DETAILS -->
                    <div style="background:white; border-right:1px solid #eee; padding:30px;">
                        <div class="mb-4">
                            <label style="display:block; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:8px;">Client Information</label>
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:15px;">
                                <div style="width:40px; height:40px; background:var(--sys-blue-soft); color:var(--sys-blue-deeper); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700;">
                                    <span id="view_client_initial"></span>
                                </div>
                                <div>
                                    <div id="view_client_name" style="font-weight:700; color:#1a1a1a;"></div>
                                    <div id="view_client_phone" style="font-size:13px; color:#666;"></div>
                                </div>
                            </div>
                            <div id="view_client_email" style="font-size:13px; color:#666; margin-bottom:5px; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-envelope" style="width:14px;"></i> <span></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label style="display:block; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:12px;">Event Logistics</label>
                            <div style="display:grid; gap:15px;">
                                <div style="display:flex; gap:12px;">
                                    <div style="color:#888;"><i class="fas fa-calendar-day"></i></div>
                                    <div>
                                        <div style="font-size:11px; color:#888;">Date</div>
                                        <div id="view_event_date" style="font-weight:600;"></div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px;">
                                    <div style="color:#888;"><i class="fas fa-clock"></i></div>
                                    <div>
                                        <div style="font-size:11px; color:#888;">Time</div>
                                        <div id="view_event_time" style="font-weight:600;"></div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px;">
                                    <div style="color:#888;"><i class="fas fa-map-marker-alt"></i></div>
                                    <div>
                                        <div style="font-size:11px; color:#888;">Location</div>
                                        <div id="view_event_location" style="font-weight:600; font-size:13px; line-height:1.4;"></div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px;">
                                    <div style="color:#888;"><i class="fas fa-users"></i></div>
                                    <div>
                                        <div style="font-size:11px; color:#888;">Pax Count</div>
                                        <div id="view_pax_count" style="font-weight:600;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label style="display:block; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:12px;">Package & Pricing</label>
                            <div style="background:#f0f7ff; border-radius:12px; padding:15px; border:1px solid #d0e5ff;">
                                <div id="view_package_name" style="font-weight:700; color:var(--sys-blue-deeper); font-size:14px; margin-bottom:5px;"></div>
                                <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:3px;">
                                    <span style="color:#555;">Base Price:</span>
                                    <span id="view_base_price" style="font-weight:600;"></span>
                                </div>
                                <div id="view_extra_pax_row" style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:3px;">
                                    <span style="color:#555;">Extra Pax:</span>
                                    <span id="view_extra_cost" style="font-weight:600; color:var(--sys-orange-deeper);"></span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:13px;">
                                    <span style="color:#555;">Transport:</span>
                                    <span id="view_transport_fee" style="font-weight:600;"></span>
                                </div>
                                <hr style="margin:10px 0; border-top:1px dashed #d0e5ff;">
                                <div style="display:flex; justify-content:space-between; font-size:15px;">
                                    <span style="font-weight:700;">Total:</span>
                                    <span id="view_total_cost" style="font-weight:800; color:#1a1a1a;"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto">
                             <div style="font-size:12px; color:#888;">Created By: <span id="view_created_by" style="font-weight:600; color:#444;"></span></div>
                             <div style="font-size:12px; color:#888;">Created At: <span id="view_created_at"></span></div>
                        </div>
                    </div>

                    <!-- MAIN CONTENT: TABS/SECTIONS -->
                    <div style="padding:30px; display:flex; flex-direction:column; gap:25px; overflow-y:auto; max-height:calc(100vh - 120px);">
                        
                        <!-- FINANCIAL SUMMARY CARD -->
                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px;">
                            <div class="card p-3 shadow-sm" style="border-radius:16px; border:none; background:white;">
                                <div style="font-size:11px; font-weight:700; color:#888; margin-bottom:5px;">TOTAL COST</div>
                                <div id="card_total_cost" style="font-size:22px; font-weight:800; letter-spacing:-0.5px;"></div>
                            </div>
                            <div class="card p-3 shadow-sm" style="border-radius:16px; border:none; background:white;">
                                <div style="font-size:11px; font-weight:700; color:#888; margin-bottom:5px;">AMOUNT PAID</div>
                                <div id="card_amount_paid" style="font-size:22px; font-weight:800; color:#1A7A32; letter-spacing:-0.5px;"></div>
                            </div>
                            <div class="card p-3 shadow-sm" style="border-radius:16px; border:none; background:white;">
                                <div style="font-size:11px; font-weight:700; color:#888; margin-bottom:5px;">REMAINING BALANCE</div>
                                <div id="card_balance" style="font-size:22px; font-weight:800; color:#C0392B; letter-spacing:-0.5px;"></div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
                            <!-- MENU SELECTION -->
                            <div>
                                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px;">
                                    <h6 style="font-weight:700; margin:0;"><i class="fas fa-utensils me-2" style="color:var(--sys-orange);"></i>Menu Selection</h6>
                                    <span id="view_menu_count" class="badge bg-soft-secondary text-muted">0 dishes</span>
                                </div>
                                <div id="view_menu_grid" style="display:grid; grid-template-columns: 1fr; gap:8px;">
                                    <!-- Dishes injected here -->
                                </div>
                                <div id="view_custom_items_wrap" style="margin-top:15px; border-top:1px solid #eee; padding-top:15px; display:none;">
                                    <div style="font-size:12px; font-weight:700; color:#888; margin-bottom:10px;">CUSTOM ADD-ONS</div>
                                    <div id="view_custom_items_list"></div>
                                </div>
                            </div>

                            <!-- OPERATIONS & STAFF -->
                            <div style="display:flex; flex-direction:column; gap:25px;">
                                <div>
                                    <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-users-gear me-2" style="color:var(--sys-blue);"></i>Staff Assignments</h6>
                                    <div id="view_staff_list" style="display:grid; gap:8px;">
                                        <!-- Staff injected here -->
                                    </div>
                                    <div id="no_staff_msg" style="padding:20px; text-align:center; background:rgba(0,0,0,0.03); border-radius:12px; color:#888; font-size:13px;">
                                        No staff dispatched yet.
                                    </div>
                                </div>


                                <div>
                                    <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-triangle-exclamation me-2" style="color:var(--sys-orange);"></i>Breakage Log</h6>
                                    <div id="view_breakage_list" style="display:grid; gap:8px;">
                                        <!-- Breakages injected here -->
                                    </div>
                                    <div id="no_breakage_msg" style="padding:20px; text-align:center; background:rgba(0,0,0,0.03); border-radius:12px; color:#888; font-size:13px;">
                                        No incidents reported.
                                    </div>
                                </div>

                                <div id="view_report_section">
                                    <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-clipboard-check me-2" style="color:var(--sys-green);"></i>Staff Event Report</h6>
                                    <div id="view_report_notes" style="padding:15px; background:#fff8f0; border:1px solid #ffe8cc; border-radius:12px; font-size:13px; line-height:1.5; color:#5a3e1b;">
                                        <!-- Report notes injected here -->
                                    </div>
                                </div>

                                <div id="view_notes_section" style="margin-top:20px;">
                                    <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-sticky-note me-2" style="color:var(--sys-blue);"></i>Client Notes</h6>
                                    <div id="view_notes_content" style="padding:15px; background:#f0f7ff; border:1px solid #d0e5ff; border-radius:12px; font-size:13px; line-height:1.5; color:#2c3e50;">
                                        <!-- Notes injected here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PAYMENT HISTORY -->
                        <div>
                            <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-peso-sign me-2" style="color:#1A7A32;"></i>Payment History</h6>
                            <div class="table-wrapper shadow-sm" style="background:white; border-radius:16px; border:none;">
                                <div class="table-responsive">
                                <table class="data-table" style="font-size:13px;">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th>Ref. No</th>
                                            <th>Amount</th>
                                            <th>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody id="view_payments_body">
                                        <!-- Payments injected here -->
                                    </tbody>
                                </table>
                                </div>
                            </div>
                            <!-- History Pagination -->
                            <div class="table-pagination" id="historyPaginationBar" style="margin-top: 10px;">
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
                </div>
            </div>
            <div class="modal-footer" style="padding:20px 30px; background:white; border-top:1px solid #eee;">
                <!-- Payment Link button — shown only when balance > 0 and booking is active -->
                <button class="btn btn-success px-4 me-auto" id="payLinkBtn"
                        onclick="generatePaymentLink()"
                        style="display:none; gap:8px; align-items:center;"
                        title="Create a PayMongo checkout link and copy it to share with the client">
                    <i class="fas fa-link"></i>
                    Generate Payment Link
                </button>
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button class="btn btn-primary px-4" id="viewToEditBtn" onclick="openEditFromView()">
                    <i class="fas fa-edit me-2"></i>Edit Booking
                </button>
            </div>
        </div>
    </div>
</div>

<!-- VIEW/EDIT BOOKING MODAL -->
<div class="modal fade" id="editBookingModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="position:relative;">
                <h5 class="modal-title" style="padding-right:40px;"><i class="fas fa-edit me-2"></i>Edit Booking <span id="editBookingId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="position:absolute; right:20px; top:20px;"></button>
            </div>
            <div class="modal-body">
                <form id="editBookingForm">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="updated_at" id="edit_updated_at">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Event Date
                                <small class="text-muted" style="font-weight:400;"> (reschedule — checks availability)</small>
                            </label>
                            <input type="date" class="form-control" name="event_date" id="edit_event_date">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Event Time</label>
                            <input type="time" class="form-control" name="event_time" id="edit_event_time">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Booking Status</label>
                            <select class="form-control" name="booking_status" id="edit_booking_status">

                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Guest Count (Pax)</label>
                            <div id="edit_pax_display" style="font-weight:700; font-size:16px; color:var(--sys-green-deeper); padding:8px 0;"></div>
                            <input type="hidden" name="pax_count" id="edit_pax_count">
                        </div>
                    </div>
                    <div class="alert alert-soft-warning mb-3" style="font-size:12.5px; border-radius:10px; display:flex; align-items:center;">
                        <i class="fas fa-info-circle me-2"></i>
                        <span><strong>Menu Lock:</strong> To prevent costing drifts, direct menu editing is disabled. If the client requested changes, please document them in the <strong>Notes</strong> field below.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Event Location</label>
                        <input type="text" class="form-control" name="event_location" id="edit_event_location">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-info btn-sm me-2" id="reminderBtn" onclick="sendReminder()">
                    <i class="fas fa-envelope"></i> Send Reminder
                </button>
                <button class="btn btn-outline-danger btn-sm me-2" id="cancelReqBtn" onclick="requestCancellation()">
                    <i class="fas fa-ban"></i> Request Cancellation
                </button>
                <button class="btn btn-outline-warning btn-sm me-2" style="display:none;" id="breakageLogBtn"></button>
                <button class="btn btn-outline-secondary btn-sm me-2" id="archiveBtn" onclick="archiveBooking()" title="Archive Booking">
                    <i class="fas fa-box-archive"></i> Archive Booking
                </button>
                <div style="flex:1;"></div>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="updateBooking()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let clients = [];
let currentBookings = [];
let allDishes = { mains: [], desserts: [] };
let currentBookingPage = 1;
let bookingTotalPages = 1;

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

async function init() {
    await loadDishes();
    await loadBookings();
}

async function loadDishes() {
    try {
        const d = await Api.get(BASE + 'src/api/packages.php', { dishes: 1 });
        allDishes.mains = d.mainDishes || [];
        allDishes.desserts = d.desserts || [];
    } catch(e) {}
}

async function loadBookings(page = null) {
    if (page !== null) {
        currentBookingPage = Math.max(1, page);
    }

    const status  = document.getElementById('filterStatus').value;
    const payment = document.getElementById('filterPayment').value;
    const search  = document.getElementById('searchInput').value;
    const order   = document.getElementById('filterOrder').value;

    try {
        const params = {
            page: currentBookingPage,
            limit: 10,
        };
        if (status)  params.status = status;
        if (payment) params.payment_status = payment;
        if (search)  params.search = search;
        if (order)   params.order = order;

        const d = await Api.get(BASE + 'src/api/bookings.php', params);
        currentBookings = d.bookings || [];
        bookingTotalPages = d.meta?.totalPages || 1;
        const totalRecords = d.meta?.totalRecords ?? currentBookings.length;
        document.getElementById('bookingCount').textContent = `${totalRecords} booking${totalRecords === 1 ? '' : 's'} found`;
        renderBookingPagination();
        renderTable(currentBookings);
    } catch (e) {
        document.getElementById('bookingsBody').innerHTML =
            '<tr><td colspan="9" class="text-center text-muted p-4">Failed to load bookings.</td></tr>';
    }
}

function renderTable(bookings) {
    const tbody = document.getElementById('bookingsBody');
    if (bookings.length === 0) {
        tbody.innerHTML = `<tr data-empty><td colspan="9"><div class="table-empty">
            <i class="fas fa-calendar-xmark"></i><p>No bookings found.</p></div></td></tr>`;
        return;
    }
    tbody.innerHTML = bookings.map((b, i) => `
        <tr>
            <td class="td-name">${esc(b.client_name)}<br><small class="text-muted">${esc(b.client_phone)}</small></td>
            <td>${Format.dateShort(b.event_date)}<br><small class="text-muted">${b.event_time ? Format.time(b.event_time) : ''}</small></td>
            <td>${b.pax_count}</td>
            <td class="text-bold">${Format.peso(b.total_cost)}</td>
            <td>
                <div style="font-weight:600; color:#1A7A32; font-size:13px;">${Format.peso(b.amount_paid)}</div>
                <div style="font-size:11px; color:${(parseFloat(b.total_cost)-parseFloat(b.amount_paid)) > 0 ? '#C0392B' : '#1A7A32'};">
                    ${(parseFloat(b.total_cost)-parseFloat(b.amount_paid)) > 0
                        ? 'Balance: '+Format.peso(parseFloat(b.total_cost)-parseFloat(b.amount_paid))
                        : '✓ Fully Paid'}
                </div>
            </td>
            <td>${Format.paymentBadge(b.payment_status)}</td>
            <td>
                ${Format.bookingBadge(b.booking_status)}
                ${parseInt(b.resched_count) > 0 ? `<br><span class="badge bg-soft-info text-info mt-1" style="font-size:10px;"><i class="fas fa-redo me-1"></i>Rescheduled</span>` : ''}
            </td>
            <td class="td-actions">
                <button class="btn btn-outline-info btn-sm" onclick="openViewBooking(${b.id})" title="View Info">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-outline-primary btn-sm" onclick="openEdit(${b.id})" title="Edit Booking">
                    <i class="fas fa-edit"></i>
                </button>
                <a href="${BASE}views/admin/financial.php?booking_id=${b.id}" class="btn btn-outline-secondary btn-sm" title="Payments">
                    <i class="fas fa-peso-sign"></i>
                </a>
                <a href="${BASE}templates/contract.php?booking_id=${b.id}" target="_blank" class="btn btn-outline-secondary btn-sm" title="Contract">
                    <i class="fas fa-file-contract"></i>
                </a>
            </td>
        </tr>
    `).join('');
}


async function openViewBooking(id) {
    currentHistoryBid  = id;
    currentHistoryPage = 1;
    try {
        const d = await Api.get(BASE + 'src/api/bookings.php', { id });
        const b = d.booking;
        if (!b) throw new Error('Booking data not found.');

        // Sidebar
        document.getElementById('view_booking_id_title').textContent = '#' + b.id;
        document.getElementById('view_status_badge').innerHTML = Format.bookingBadge(b.booking_status);
        document.getElementById('view_client_initial').textContent = b.client_name.charAt(0).toUpperCase();
        document.getElementById('view_client_name').textContent = b.client_name;
        document.getElementById('view_client_phone').textContent = b.client_phone;
        document.getElementById('view_client_email').querySelector('span').textContent = b.client_email || 'No email provided';
        
        document.getElementById('view_event_date').textContent = Format.date(b.event_date);
        document.getElementById('view_event_time').textContent = b.event_time ? Format.time(b.event_time) : 'TBA';
        document.getElementById('view_event_location').textContent = b.event_location || 'Venue TBA';
        document.getElementById('view_pax_count').textContent = b.pax_count + ' Pax';
        
        document.getElementById('view_package_name').textContent = b.package_name;
        document.getElementById('view_base_price').textContent = Format.peso(b.base_price);
        document.getElementById('view_extra_cost').textContent = '+' + Format.peso(b.extra_cost);
        document.getElementById('view_transport_fee').textContent = Format.peso(b.transport_fee);
        document.getElementById('view_total_cost').textContent = Format.peso(b.total_cost);

        document.getElementById('view_created_by').textContent = b.created_by_name || 'System';
        document.getElementById('view_created_at').textContent = Format.date(b.created_at);

        // Financial Cards
        document.getElementById('card_total_cost').textContent = Format.peso(b.total_cost);
        document.getElementById('card_amount_paid').textContent = Format.peso(b.amount_paid);
        const balance = Math.max(0, parseFloat(b.total_cost) - parseFloat(b.amount_paid));
        document.getElementById('card_balance').textContent = Format.peso(balance);

        // Menu Grid
        const menuGrid = document.getElementById('view_menu_grid');
        menuGrid.innerHTML = b.dishes.map(d => `
            <div style="background:white; padding:10px 15px; border-radius:10px; border:1px solid #eee; display:flex; align-items:center; gap:12px;">
                <div style="width:8px; height:8px; border-radius:50%; background:var(--sys-orange);"></div>
                <div style="flex:1;">
                    <div style="font-weight:600; font-size:13.5px;">${esc(d.name)}</div>
                    <div style="font-size:11px; color:#888; text-transform:uppercase;">${esc(d.category)}</div>
                </div>
            </div>
        `).join('');
        document.getElementById('view_menu_count').textContent = b.dishes.length + ' dishes';

        // Custom Items
        const ciWrap = document.getElementById('view_custom_items_wrap');
        const ciList = document.getElementById('view_custom_items_list');
        if (b.custom_items && b.custom_items.length > 0) {
            ciWrap.style.display = 'block';
            ciList.innerHTML = b.custom_items.map(ci => `
                <div style="font-size:13px; color:#444; margin-bottom:5px; display:flex; justify-content:space-between;">
                    <span><i class="fas fa-plus-circle text-muted me-2"></i>${esc(ci.name)}</span>
                    <span class="text-muted text-xs">${esc(ci.category)}</span>
                </div>
            `).join('');
        } else {
            ciWrap.style.display = 'none';
        }

        // Staff List
        const staffList = document.getElementById('view_staff_list');
        const noStaff = document.getElementById('no_staff_msg');
        if (b.staff && b.staff.length > 0) {
            noStaff.style.display = 'none';
            staffList.innerHTML = b.staff.map(s => `
                <div style="background:white; padding:10px 15px; border-radius:10px; border:1px solid #eee; display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-user-circle text-muted" style="font-size:18px;"></i>
                        <div>
                            <div style="font-weight:600; font-size:13px;">${esc(s.staff_name)}</div>
                            <div style="font-size:11px; color:#888;">${esc(s.staff_email)}</div>
                        </div>
                    </div>
                    <span class="badge bg-soft-info text-info" style="font-size:10px;">${esc(s.role_required.toUpperCase())}</span>
                </div>
            `).join('');
        } else {
            staffList.innerHTML = '';
            noStaff.style.display = 'block';
        }

        // Breakages
        const breakageList = document.getElementById('view_breakage_list');
        const noBreakage = document.getElementById('no_breakage_msg');
        if (b.breakages && b.breakages.length > 0) {
            noBreakage.style.display = 'none';
            breakageList.innerHTML = b.breakages.map(br => {
                let chargeBadge = '';
                const c = (br.charge_to || '').toUpperCase();
                if (c === 'CLIENT') chargeBadge = '<span class="badge bg-soft-danger text-danger" style="font-size:10px;">CLIENT</span>';
                else if (c === 'STAFF') chargeBadge = '<span class="badge bg-soft-warning text-warning" style="font-size:10px;">STAFF</span>';
                else chargeBadge = '<span class="badge bg-soft-secondary text-muted" style="font-size:10px;">LOSS</span>';

                return `
                <div style="background:white; padding:10px 15px; border-radius:10px; border:1px solid #eee; display:flex; align-items:center; justify-content:space-between;">
                    <div style="flex:1;">
                        <div style="font-weight:600; font-size:13px; color:#C0392B;">${esc(br.equipment_name)} (x${br.quantity})</div>
                        <div style="display:flex; align-items:center; gap:8px; margin-top:4px;">
                            ${chargeBadge}
                            ${br.notes ? `<span style="font-size:11px; color:#666; font-style:italic; border-left:1px solid #eee; padding-left:8px;">${esc(br.notes)}</span>` : ''}
                        </div>
                    </div>
                    <div style="font-weight:700; color:#1a1a1a; font-size:14px;">${Format.peso(br.total_cost)}</div>
                </div>`;
            }).join('');
        } else {
            breakageList.innerHTML = '';
            noBreakage.style.display = 'block';
        }

        // Staff Report Notes
        const reportSection = document.getElementById('view_report_section');
        const reportNotes = document.getElementById('view_report_notes');
        if (b.event_report_notes) {
            reportSection.style.display = 'block';
            reportNotes.innerHTML = `<i class="fas fa-quote-left me-2 opacity-50"></i>${esc(b.event_report_notes)}`;
        } else {
            reportSection.style.display = 'none';
        }

        // Payment History
        loadPaymentHistory(id);

        // Client Notes
        const notesSection = document.getElementById('view_notes_section');
        const notesContent = document.getElementById('view_notes_content');
        if (b.notes) {
            notesSection.style.display = 'block';
            notesContent.innerHTML = `<i class="fas fa-quote-left me-2 opacity-50"></i>${esc(b.notes)}`;
        } else {
            notesSection.style.display = 'none';
        }

        // Store ID for Edit transition
        window.currentViewingId = id;
        window.currentViewingBooking = b;

        // Show "Generate Payment Link" button only when there is an outstanding balance
        // and the booking is not cancelled or completed
        const payLinkBtn = document.getElementById('payLinkBtn');
        const isPayable  = balance > 0.01
                        && b.booking_status !== 'cancelled'
                        && b.booking_status !== 'completed'
                        && b.booking_status !== 'pending_cancellation';
        payLinkBtn.style.display = isPayable ? 'inline-flex' : 'none';

        Modal.open('viewBookingModal');
    } catch (e) { Toast.error(e.message); }
}

async function loadPaymentHistory(bookingId) {
    const payBody = document.getElementById('view_payments_body');
    payBody.innerHTML = '<tr><td colspan="5" class="text-center"><div class="spinner"></div></td></tr>';

    try {
        const d = await Api.get(BASE + 'src/api/payments.php', { 
            booking_id: bookingId,
            page: currentHistoryPage,
            limit: HISTORY_LIMIT
        });
        const payments = d.payments || [];
        renderHistoryPagination(d.meta || { currentPage: 1, totalPages: 1 });

        if (payments.length > 0) {
            payBody.innerHTML = payments.map(p => `
                <tr>
                    <td>${Format.dateShort(p.payment_date)}</td>
                    <td><span class="text-xs uppercase fw-700">${esc(p.payment_method)}</span></td>
                    <td class="text-muted text-xs">${esc(p.reference_no || '—')}</td>
                    <td class="fw-700">${Format.peso(p.amount)}</td>
                    <td class="text-muted text-xs">${esc(p.recorded_by_name)}</td>
                </tr>
            `).join('');
        } else {
            payBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No payments recorded.</td></tr>';
        }
    } catch (e) {
        payBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Failed to load payments.</td></tr>';
    }
}

// ── PAYMONGO: Generate & Copy Payment Link ────────────────────────────────────
async function generatePaymentLink() {
    const b   = window.currentViewingBooking;
    const btn = document.getElementById('payLinkBtn');
    if (!b || !btn) return;

    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating link…';

    try {
        const res = await Api.post(BASE + 'src/api/paymongo_checkout.php', {
            booking_id: parseInt(b.id, 10)
        });

        if (!res.checkout_url) throw new Error('No checkout URL returned.');

        // Build a shareable invoice URL that will show the Pay Now button to the client
        const invoiceUrl = BASE + 'templates/invoice.php?booking_id=' + b.id
                         + '&token=' + encodeURIComponent(b.invoice_token || '');

        // Copy the checkout URL directly to the clipboard for quick sharing
        try {
            await navigator.clipboard.writeText(res.checkout_url);
            Toast.success(
                '\u2713 Payment link copied! ' +
                'Balance: ' + Format.peso(res.amount) +
                ' | Session: ' + res.checkout_session_id.slice(-8)
            );
        } catch {
            // Clipboard permission denied — show the URL in a confirm dialog
            window.prompt('Copy this payment link to share with the client:', res.checkout_url);
        }

    } catch (e) {
        Toast.error(e.message || 'Failed to generate payment link.');
    } finally {
        btn.innerHTML = originalHTML;
        btn.disabled  = false;
    }
}

function openEditFromView() {
    if (window.currentViewingId) {
        Modal.close('viewBookingModal');
        openEdit(window.currentViewingId);
    }
}

async function openEdit(id) {
    console.log('[DEBUG] openEdit called for ID:', id);
    Modal.open('editBookingModal');
    try {
        const d = await Api.get(BASE + 'src/api/bookings.php', { id });
        const b = d.booking;
        if (!b) throw new Error('Booking data not found.');
        document.getElementById('edit_id').value = b.id;
        document.getElementById('edit_updated_at').value = b.updated_at || '';
        document.getElementById('editBookingId').textContent = '#' + b.id;
        document.getElementById('edit_event_date').value = b.event_date;
        document.getElementById('edit_event_time').value = b.event_time || '';
        document.getElementById('edit_pax_count').value = b.pax_count;
        document.getElementById('edit_pax_display').textContent = b.pax_count;
        document.getElementById('edit_booking_status').value = b.booking_status;
        document.getElementById('edit_event_location').value = b.event_location || '';
        document.getElementById('edit_notes').value = b.notes || '';

        // Enforce 14-day UI restriction for rescheduling
        const minDate = new Date();
        minDate.setDate(minDate.getDate() + 14);
        document.getElementById('edit_event_date').min = minDate.toISOString().split('T')[0];

        // Dish selection update removed as per policy shift
        Modal.open('editBookingModal');

        // Show archive/cancel buttons logic
        document.getElementById('archiveBtn').style.display =
            b.booking_status === 'completed' ? 'flex' : 'none';
        
        document.getElementById('cancelReqBtn').style.display =
            (b.booking_status === 'confirmed') ? 'flex' : 'none';

        // Reminder button logic
        const hasBalance = (parseFloat(b.total_cost) - parseFloat(b.amount_paid)) > 0.01;
        const reminderBtn = document.getElementById('reminderBtn');
        reminderBtn.style.display = (hasBalance && b.booking_status !== 'cancelled' && b.booking_status !== 'pending_cancellation') ? 'flex' : 'none';
        
        if (hasBalance) {
            const evDate = new Date(b.event_date);
            const today  = new Date();
            today.setHours(0,0,0,0);
            evDate.setHours(0,0,0,0);
            const diff = Math.round((evDate - today) / (86400000));
            if (diff >= 0 && diff <= 3) {
                reminderBtn.classList.replace('btn-outline-info', 'btn-info');
                reminderBtn.title = "Event is in " + diff + " days! Send reminder now.";
            } else {
                reminderBtn.classList.replace('btn-info', 'btn-outline-info');
                reminderBtn.title = "Send payment reminder email";
            }
        }
    } catch (e) { Toast.error(e.message); }
}

async function updateBooking() {
    const btn = document.querySelector('#editBookingModal .btn-primary');
    Form.setLoading(btn, true);
    try {
        const data = Form.serialize(document.getElementById('editBookingForm'));
        await Api.put(BASE + 'src/api/bookings.php', data);
        Toast.success('Booking updated.');
        Modal.close('editBookingModal');
        await loadBookings();
    } catch (e) { Toast.error(e.message); }
    finally { Form.setLoading(btn, false); }
}

async function sendReminder() {
    const id = document.getElementById('edit_id').value;
    if (!id) return;
    const btn = document.getElementById('reminderBtn');
    Form.setLoading(btn, true, 'Sending...');
    try {
        await Api.post(BASE + 'src/api/bookings.php', { action: 'send_reminder', id: id });
        Toast.success('Reminder sent successfully.');
    } catch (e) { Toast.error(e.message); }
    finally {
        Form.setLoading(btn, false);
    }
}

async function archiveBooking() {
    const id = document.getElementById('edit_id').value;
    if (!await confirmDialog('Archive this completed booking? It will be moved to the Archive and removed from the active calendar.')) return;
    
    const btn = document.querySelector('#editBookingModal .btn-dark'); // The archive button
    Form.setLoading(btn, true, 'Archiving...');
    try {
        await Api.post(BASE + 'src/api/archive.php', { booking_id: id });
        Toast.success('Booking archived successfully.');
        Modal.close('editBookingModal');
        await loadBookings();
    } catch (e) { Toast.error(e.message); }
    finally { Form.setLoading(btn, false); }
}

async function requestCancellation() {
    const id    = document.getElementById('edit_id').value;
    const b     = currentBookings.find(x => x.id == id);
    if (!b) return;

    const totalPaid = parseFloat(b.amount_paid) || 0;
    const totalCost = parseFloat(b.total_cost) || 0;
    const isConfirmed = (b.booking_status === 'confirmed');
    
    // Preview the logic (50% of Paid Amount)
    const forfeit = Math.round(totalPaid * 0.5 * 100) / 100;
    const refund  = totalPaid - forfeit;
    
    let html = `
        <div style="text-align:left;">
            <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
            <div style="background:#f8f9fa; border-radius:10px; padding:15px; font-size:14px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <span>Total Paid:</span>
                    <span style="font-weight:700; color:#1A7A32;">${Format.peso(totalPaid)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <span>Forfeiture Fee (50% of Paid):</span>
                    <span style="font-weight:700; color:#C0392B;">${Format.peso(forfeit)}</span>
                </div>
                <hr style="margin:10px 0;">
                <div style="display:flex; justify-content:space-between; font-weight:800;">
                    <span>Estimated Refund:</span>
                    <span>${Format.peso(refund)}</span>
                </div>
            </div>
            <div class="form-group mt-3">
                <label class="form-label">Cancellation Reason</label>
                <textarea class="form-control" id="cancel_reason" placeholder="Client family emergency, etc."></textarea>
            </div>
        </div>
    `;

    const result = await CustomConfirm.show({
        title: 'Cancel Booking',
        html: html,
        confirmText: 'Confirm Cancellation',
        confirmColor: 'var(--sys-red)'
    });

    if (!result) return;

    const reason = result.cancel_reason || '';
    const btn = document.querySelector('#editBookingModal .btn-danger'); // The cancel button
    Form.setLoading(btn, true, 'Cancelling...');
    try {
        await Api.post(BASE + 'src/api/cancellations.php', { 
            booking_id: id, 
            reason: reason 
        });
        Toast.success('Cancellation requested. Please go to Financials > Refunds to process the payout.');
        Modal.close('editBookingModal');
        await loadBookings();
    } catch (e) { Toast.error(e.message); }
    finally { Form.setLoading(btn, false); }
}




// Wire up filters
['filterStatus','filterPayment','filterOrder'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => loadBookings(1));
});
document.getElementById('searchInput').addEventListener('input', debounce(() => loadBookings(1), 400));

function changeBookingPage(newPage) {
    if (newPage < 1 || newPage > bookingTotalPages) return;
    loadBookings(newPage);
}

function renderBookingPagination() {
    document.getElementById('bookingPageInfo').textContent = `Page ${currentBookingPage} of ${bookingTotalPages}`;
    document.getElementById('bookingPrevBtn').disabled = currentBookingPage <= 1;
    document.getElementById('bookingNextBtn').disabled = currentBookingPage >= bookingTotalPages;
}

init().then(() => console.log('[DEBUG] Bookings Page Initialized'));
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
