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
                    <th>#</th>
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
                            <label class="form-label">Event Date</label>
                            <input type="date" class="form-control" name="event_date" id="edit_event_date">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Event Time</label>
                            <input type="time" class="form-control" name="event_time" id="edit_event_time">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Pax Count</label>
                            <input type="number" class="form-control" name="pax_count" id="edit_pax_count" min="1">
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
                <button class="btn btn-danger btn-sm me-auto" id="archiveBtn" onclick="archiveBooking()">
                    <i class="fas fa-box-archive"></i> Archive
                </button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="updateBooking()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= BASE_URL ?>';
let clients = [];
let currentBookings = [];

async function init() {
    await loadBookings();
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
            <td class="text-muted text-xs">#${b.id}</td>
            <td class="td-name">${b.client_name}<br><small class="text-muted">${b.client_phone}</small></td>
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

        // Show archive button only for completed bookings
        document.getElementById('archiveBtn').style.display =
            b.booking_status === 'completed' ? 'flex' : 'none';

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

// Wire up filters
['filterStatus','filterPayment'].forEach(id => {
    document.getElementById(id).addEventListener('change', loadBookings);
});
document.getElementById('searchInput').addEventListener('input', debounce(loadBookings, 400));

init();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
