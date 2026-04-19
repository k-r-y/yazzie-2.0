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
                <option value="pending">⏳ Pending DP</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select class="form-control" id="filterPayment" style="width:160px;">
                <option value="">All Payments</option>
                <option value="unpaid">Unpaid</option>
                <option value="partial">Partial</option>
                <option value="paid">Paid</option>
            </select>
            <button class="btn btn-primary" onclick="openBookingStepper()">
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
                                <option value="pending">⏳ Pending DP</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Guest Count (Pax)</label>
                            <input type="number" class="form-control" name="pax_count" id="edit_pax_count" min="50">
                        </div>
                    </div>
                    <div class="form-group mt-3" style="border:1px solid #e1e4e8; border-radius:8px; padding:12px;">
                        <label class="form-label" style="margin-bottom:8px;">Menu Selection</label>
                        <div style="display:flex; flex-wrap:wrap; gap:12px; margin-bottom:12px;" id="editDishGridMains"></div>
                        <div style="display:flex; flex-wrap:wrap; gap:12px;" id="editDishGridDesserts"></div>
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
                <button class="btn btn-outline-danger btn-sm me-2" id="cancelReqBtn" onclick="requestCancellation()">
                    <i class="fas fa-ban"></i> Request Cancellation
                </button>
                <button class="btn btn-outline-warning btn-sm me-2" id="breakageLogBtn" onclick="openBreakageModal()">
                    <i class="fas fa-shrimp"></i> Breakage Log
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

async function init() {
    await loadDishes();
    await loadBookings();
}

async function loadDishes() {
    try {
        const d = await Api.get(BASE + '/src/api/packages.php', { dishes: 1 });
        allDishes.mains = d.mainDishes || [];
        allDishes.desserts = d.desserts || [];
    } catch(e) {}
}

async function loadBookings() {
    const status  = document.getElementById('filterStatus').value;
    const payment = document.getElementById('filterPayment').value;
    const search  = document.getElementById('searchInput').value;

    try {
        const params = {};
        if (status)  params.status = status;
        if (payment) params.payment_status = payment;
        if (search)  params.search = search;

        const d = await Api.get(BASE + '/src/api/bookings.php', params);
        currentBookings = d.bookings || [];
        renderTable(currentBookings);
    } catch (e) {
        document.getElementById('bookingsBody').innerHTML =
            '<tr><td colspan="9" class="text-center text-muted p-4">Failed to load bookings.</td></tr>';
    }
}

function renderTable(bookings) {
    document.getElementById('bookingCount').textContent = bookings.length + ' booking(s)';
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
            <td>${Format.bookingBadge(b.booking_status)}</td>
            <td class="td-actions">
                <button class="btn btn-outline-primary btn-sm" onclick="openEdit(${b.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <a href="${BASE}/views/admin/financial.php?booking_id=${b.id}" class="btn btn-outline-secondary btn-sm" title="Payments">
                    <i class="fas fa-peso-sign"></i>
                </a>
                <a href="${BASE}/templates/contract.php?booking_id=${b.id}" target="_blank" class="btn btn-outline-secondary btn-sm" title="Contract">
                    <i class="fas fa-file-contract"></i>
                </a>
            </td>
        </tr>
    `).join('');
}


async function openEdit(id) {
    try {
        const d = await Api.get(BASE + '/src/api/bookings.php', { id });
        const b = d.booking;
        document.getElementById('edit_id').value = b.id;
        document.getElementById('editBookingId').textContent = '#' + b.id;
        document.getElementById('edit_event_date').value = b.event_date;
        document.getElementById('edit_event_time').value = b.event_time || '';
        document.getElementById('edit_pax_count').value = b.pax_count;
        document.getElementById('edit_booking_status').value = b.booking_status;
        document.getElementById('edit_event_location').value = b.event_location || '';
        document.getElementById('edit_notes').value = b.notes || '';

        // Extract already selected dishes
        const selectedDishIds = (b.dishes || []).map(ds => ds.id);

        // Build dish checkboxes
        document.getElementById('editDishGridMains').innerHTML = allDishes.mains.map(dish => `
            <label style="display:flex; align-items:center; gap:6px; font-size:13px; background:#f8f9fa; padding:4px 8px; border-radius:4px; border:1px solid #eee;">
                <input type="checkbox" name="selected_dishes[]" value="${dish.id}" ${selectedDishIds.includes(dish.id) ? 'checked' : ''}>
                🍲 ${esc(dish.name)}
            </label>
        `).join('');

        document.getElementById('editDishGridDesserts').innerHTML = allDishes.desserts.map(dish => `
            <label style="display:flex; align-items:center; gap:6px; font-size:13px; background:#fff2cc; padding:4px 8px; border-radius:4px; border:1px solid #ffe699;">
                <input type="checkbox" name="selected_dishes[]" value="${dish.id}" ${selectedDishIds.includes(dish.id) ? 'checked' : ''}>
                🍮 ${esc(dish.name)}
            </label>
        `).join('');

        // Show archive/cancel buttons logic
        document.getElementById('archiveBtn').style.display =
            b.booking_status === 'completed' ? 'flex' : 'none';
        
        document.getElementById('cancelReqBtn').style.display =
            (b.booking_status !== 'completed' && b.booking_status !== 'cancelled') ? 'flex' : 'none';
        
        document.getElementById('breakageLogBtn').style.display =
            (b.booking_status !== 'cancelled') ? 'flex' : 'none';

        Modal.open('editBookingModal');
    } catch (e) { Toast.error(e.message); }
}

async function updateBooking() {
    try {
        const data = Form.serialize(document.getElementById('editBookingForm'));
        await Api.put(BASE + '/src/api/bookings.php', data);
        Toast.success('Booking updated.');
        Modal.close('editBookingModal');
        await loadBookings();
    } catch (e) { Toast.error(e.message); }
}

async function archiveBooking() {
    const id = document.getElementById('edit_id').value;
    if (!await confirmDialog('Archive this completed booking? It will be moved to the Archive and removed from the active calendar.')) return;
    try {
        await Api.post(BASE + '/src/api/archive.php', { booking_id: id });
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
    const forfeit = isConfirmed ? (totalCost * 0.5) : 0;
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
                    <span>Forfeiture Fee (50%):</span>
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

    const ok = await CustomConfirm.show({
        title: 'Cancel Booking',
        html: html,
        confirmText: 'Confirm Cancellation',
        confirmColor: 'var(--sys-red)'
    });

    if (!ok) return;

    const reason = document.getElementById('cancel_reason').value;
    try {
        await Api.post(BASE + '/src/api/cancellations.php', { 
            booking_id: id, 
            reason: reason 
        });
        Toast.success('Booking cancelled and refund record created.');
        Modal.close('editBookingModal');
        await loadBookings();
    } catch (e) { Toast.error(e.message); }
}

// Wire up filters
['filterStatus','filterPayment'].forEach(id => {
    document.getElementById(id).addEventListener('change', loadBookings);
});
document.getElementById('searchInput').addEventListener('input', debounce(loadBookings, 400));

// ── BREAKAGE LOGGING ─────────────────────────────────────────────
let inventory = [];

async function openBreakageModal() {
    const id = document.getElementById('edit_id').value;
    const b  = currentBookings.find(x => x.id == id);
    if (!b) return;

    // Fetch catalog first if empty
    if (inventory.length === 0) {
        const d = await Api.get(BASE + '/src/api/inventory.php');
        inventory = d.equipment || [];
    }

    // Load existing breakages
    const res = await Api.get(BASE + '/src/api/breakages.php', { booking_id: id });
    const logs = res.items || [];

    let html = `
        <div style="text-align:left;">
            <p class="text-xs text-muted mb-3">Log damaged or missing equipment for this event. These charges will be added to the final balance.</p>
            
            <div id="breakageList" class="mb-3" style="max-height:200px; overflow-y:auto; background:#f8f9fa; border-radius:10px; padding:10px;">
                ${logs.length === 0 ? '<div class="text-center py-3 text-muted">No losses logged.</div>' : logs.map(l => `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-size:13px; border-bottom:1px solid #eee; padding-bottom:5px;">
                        <span>${esc(l.equipment_name)} (x${l.quantity})</span>
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
                </div>
                <div class="mt-2">
                    <label class="form-label text-xs">Internal Note (optional)</label>
                    <input type="text" class="form-control form-control-sm" id="bb_note" placeholder="Table 4 breakage...">
                </div>
            </div>
        </div>
    `;

    const ok = await CustomConfirm.show({
        title: 'Breakage & Loss Log: #' + id,
        html: html,
        confirmText: 'Add Loss Log',
        confirmColor: 'var(--sys-orange)'
    });

    if (!ok) return;

    const itemId = document.getElementById('bb_item').value;
    const qty    = document.getElementById('bb_qty').value;
    const note   = document.getElementById('bb_note').value;

    if (!itemId) return Toast.error('Please select an item.');

    try {
        await Api.post(BASE + '/src/api/breakages.php', {
            booking_id: id,
            equipment_id: itemId,
            quantity: qty,
            notes: note
        });
        Toast.success('Loss logged.');
        openBreakageModal(); // Re-open to show updated list
    } catch(e) { Toast.error(e.message); }
}

async function deleteBreakage(id) {
    if (!await confirmDialog('Remove this breakage entry?')) return;
    try {
        await Api.delete(BASE + '/src/api/breakages.php', { id });
        Toast.success('Entry removed.');
        openBreakageModal(); // Re-open
    } catch(e) { Toast.error(e.message); }
}

init();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
