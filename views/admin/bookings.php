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
            <select class="form-control" id="filterStatus" style="width:160px;">
                <option value="">All Statuses</option>

                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select class="form-control" id="filterOrder" style="width:160px;">
                <option value="DESC">Latest First</option>
                <option value="ASC">Upcoming First</option>
            </select>
            <select class="form-control" id="filterPayment" style="width:160px;">
                <option value="">All Payments</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
            </select>

             <button class="btn btn-primary py-3" onclick="openBookingStepper()">
                <i class="fas fa-plus"></i> New Booking
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

<!-- VIEW/EDIT BOOKING MODAL -->
<div class="modal fade" id="editBookingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Booking <span id="editBookingId"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                <option value="cancelled">Cancelled</option>
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
                <button class="btn btn-outline-warning btn-sm me-2" id="breakageLogBtn" onclick="openBreakageModal()">
                    <i class="fas fa-shrimp"></i> Breakage Log
                </button>
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
            (b.booking_status !== 'completed' && b.booking_status !== 'cancelled') ? 'flex' : 'none';
        
        document.getElementById('breakageLogBtn').style.display =
            (b.booking_status !== 'cancelled') ? 'flex' : 'none';

        // Reminder button logic
        const hasBalance = (parseFloat(b.total_cost) - parseFloat(b.amount_paid)) > 0.01;
        const reminderBtn = document.getElementById('reminderBtn');
        reminderBtn.style.display = (hasBalance && b.booking_status !== 'cancelled') ? 'flex' : 'none';
        
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
    try {
        const data = Form.serialize(document.getElementById('editBookingForm'));
        await Api.put(BASE + 'src/api/bookings.php', data);
        Toast.success('Booking updated.');
        Modal.close('editBookingModal');
        await loadBookings();
    } catch (e) { Toast.error(e.message); }
}

async function sendReminder() {
    const id = document.getElementById('edit_id').value;
    if (!id) return;
    const btn = document.getElementById('reminderBtn');
    const old = btn.innerHTML;
    try {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        await Api.post(BASE + 'src/api/bookings.php', { action: 'send_reminder', id: id });
        Toast.success('Reminder sent successfully.');
    } catch (e) { Toast.error(e.message); }
    finally {
        btn.disabled = false;
        btn.innerHTML = old;
    }
}

async function archiveBooking() {
    const id = document.getElementById('edit_id').value;
    if (!await confirmDialog('Archive this completed booking? It will be moved to the Archive and removed from the active calendar.')) return;
    try {
        await Api.post(BASE + 'src/api/archive.php', { booking_id: id });
        Toast.success('Booking archived successfully.');
        Modal.close('editBookingModal');
        await loadBookings();
    } catch (e) { Toast.error(e.message); }
}

async function requestCancellation() {
    const id    = document.getElementById('edit_id').value;
    const b     = currentBookings.find(x => x.id == id);
    if (!b) return;

    const totalPaid = parseFloat(b.amount_paid) || 0;
    const totalCost = parseFloat(b.total_cost) || 0;
    const isConfirmed = (b.booking_status === 'confirmed');
    
    // Preview the logic
    const forfeit = isConfirmed ? (totalCost * <?= CANCEL_FORFEIT_PCT ?>) : 0;
    const refund  = Math.max(0, totalPaid - forfeit);
    
    let html = `
        <div style="text-align:left;">
            <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
            <div style="background:#f8f9fa; border-radius:10px; padding:15px; font-size:14px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <span>Total Paid:</span>
                    <span style="font-weight:700; color:#1A7A32;">${Format.peso(totalPaid)}</span>
                </div>
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <span>Forfeiture Fee (<?= round(CANCEL_FORFEIT_PCT * 100) ?>%):</span>
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
    try {
        await Api.post(BASE + 'src/api/cancellations.php', { 
            booking_id: id, 
            reason: reason 
        });
        Toast.success('Booking cancelled and refund record created.');
        Modal.close('editBookingModal');
        await loadBookings();
    } catch (e) { Toast.error(e.message); }
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

// ── BREAKAGE LOGGING ─────────────────────────────────────────────
let inventory = [];

async function openBreakageModal() {
    const id = document.getElementById('edit_id').value;
    const b  = currentBookings.find(x => x.id == id);
    if (!b) return;

    // Fetch catalog first if empty
    if (inventory.length === 0) {
        const d = await Api.get(BASE + 'src/api/inventory.php');
        inventory = d.equipment || [];
    }

    // Load existing breakages
    const res = await Api.get(BASE + 'src/api/breakages.php', { booking_id: id });
    const logs = res.items || [];

    let html = `
        <div style="text-align:left;">
            <p class="text-xs text-muted mb-3">Log damaged or missing equipment for this event. These charges will be added to the final balance.</p>
            
            <div id="breakageList" class="mb-3" style="max-height:200px; overflow-y:auto; background:#f8f9fa; border-radius:10px; padding:10px;">
                ${logs.length === 0 ? '<div class="text-center py-3 text-muted">No losses logged.</div>' : logs.map(l => `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:13px; border-bottom:1px solid #eee; padding-bottom:5px;">
                        <span>${esc(l.equipment_name)} (x${l.quantity}) <small class="text-muted">[${esc(l.charge_to)}]</small></span>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="fw-700">${Format.peso(l.total_cost)}</span>
                            <button class="btn-icon text-danger" onclick="deleteBreakage(${l.id})"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                `).join('')}
            </div>

            <div class="card p-3" style="background:#fff2f0; border:1px solid #ffccc7;">
                <div class="row g-2">
                    <div class="col-8">
                        <label class="form-label text-xs">Item</label>
                        <select class="form-control form-control-sm" id="bb_item">
                            <option value="">Select equipment...</option>
                            ${inventory.map(i => `<option value="${i.id}">${esc(i.name)} (${Format.peso(i.replacement_cost)})</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label text-xs">Qty</label>
                        <input type="number" class="form-control form-control-sm" id="bb_qty" value="1" min="1">
                    </div>
                    <div class="col-12">
                        <label class="form-label text-xs">Charge To</label>
                        <select class="form-control form-control-sm" id="bb_charge_to">
                            <option value="client">Client</option>
                            <option value="staff">Staff</option>
                            <option value="business">Business</option>
                        </select>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label text-xs">Internal Note (optional)</label>
                    <input type="text" class="form-control form-control-sm" id="bb_note" placeholder="Table 4 breakage...">
                </div>
            </div>
        </div>
    `;

    const result = await CustomConfirm.show({
        title: 'Breakage & Loss Log: #' + id,
        html: html,
        confirmText: 'Add Loss Log',
        confirmColor: 'var(--sys-orange)'
    });

    if (!result) return;

    const itemId   = result.bb_item;
    const qty      = result.bb_qty;
    const chargeTo = result.bb_charge_to || 'client';
    const note     = result.bb_note;

    if (!itemId) return Toast.error('Please select an item.');

    try {
        await Api.post(BASE + 'src/api/breakages.php', {
            booking_id: id,
            equipment_id: itemId,
            quantity: qty,
            charge_to: chargeTo,
            notes: note
        });
        Toast.success('Loss logged.');
        openBreakageModal(); // Re-open to show updated list
    } catch(e) { Toast.error(e.message); }
}

async function deleteBreakage(id) {

    if (!await confirmDialog('Remove this breakage entry?')) return;
    try {
        await Api.delete(BASE + 'src/api/breakages.php', { id });
        Toast.success('Entry removed.');
        openBreakageModal(); // Re-open
    } catch(e) { Toast.error(e.message); }
}

init().then(() => console.log('[DEBUG] Bookings Page Initialized'));
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
