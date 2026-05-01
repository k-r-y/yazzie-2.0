<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('frontdesk');

$pageTitle    = 'Manage Bookings';
$pageSubtitle = 'Encode clients and manage event bookings';
$activePage   = 'bookings';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- TOOLBAR -->
<div class="card mb-4">
    <div class="card-body" style="padding:16px 20px;">
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="searchInput" placeholder="Search client, location…">
            </div>
            <select class="form-control" id="filterStatus" style="min-width:120px; max-width:100%; flex:1;">
                <option value="">All Statuses</option>

                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <select class="form-control" id="filterOrder" style="min-width:120px; max-width:100%; flex:1;">
                <option value="DESC">Latest First</option>
                <option value="ASC">Upcoming First</option>
            </select>
            <button class="btn btn-primary py-3" onclick="openBookingStepper()">
                <i class="fas fa-plus"></i> New Booking
            </button>
            <button class="btn btn-outline-primary py-3" onclick="Modal.open('addClientModal')">
                <i class="fas fa-user-plus"></i> New Client
            </button>
        </div>
    </div>
</div>

<!-- TABLE -->
<div class="card">
    <div class="card-header">
        <div class="card-title">All Bookings</div>
        <span class="text-muted text-sm" id="countLabel"></span>
    </div>
    <div class="table-wrapper table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="data-table" id="bookingTable">
            <thead>
                <tr>
                    <th>Client</th><th>Event Date</th><th>Menu</th>
                    <th>Pax</th><th>Status</th><th>Payment</th><th class="td-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="bookingBody"><tr><td colspan="8"><div class="spinner"></div></td></tr></tbody>
        </table>
    </div>
    <div class="table-pagination" id="bookingPaginationFD">
        <button type="button" class="pagination-button" id="bookingPrevBtnFD" onclick="changeBookingPageFD(currentBookingPageFD - 1)" disabled>
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <div class="pagination-info" id="bookingPageInfoFD">Page 1 of 1</div>
        <button type="button" class="pagination-button" id="bookingNextBtnFD" onclick="changeBookingPageFD(currentBookingPageFD + 1)" disabled>
            Next <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<?php
$bookingStepperRole = 'frontdesk';
include __DIR__ . '/../../includes/_booking_stepper.php';
?>

<!-- EDIT STATUS MODAL -->
<div class="modal fade" id="editStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="position:relative;">
                <h5 class="modal-title" style="padding-right:40px;">Update Booking #<span id="editIdLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="position:absolute; right:20px; top:20px;"></button>
            </div>
            <div class="modal-body">
                 <form id="editStatusForm">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="updated_at" id="edit_updated_at">
                    <div class="form-group">
                        <label class="form-label">Event Date</label>
                        <input type="date" class="form-control" name="event_date" id="edit_event_date">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Booking Status</label>
                        <select class="form-control" name="booking_status" id="edit_bs">

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
                    <div class="alert alert-soft-warning mb-3" style="font-size:12.5px; border-radius:10px; display:flex; align-items:center;">
                        <i class="fas fa-info-circle me-2"></i>
                        <span><strong>Menu Lock:</strong> Frontdesk cannot edit dishes directly after encoding. Please put changes in the <strong>Notes</strong> field.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" id="edit_notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveStatusFD()">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- ADD CLIENT MODAL -->
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="position:relative;">
                <h5 class="modal-title" style="padding-right:40px;"><i class="fas fa-user-plus me-2"></i>Add New Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="position:absolute; right:20px; top:20px;"></button>
            </div>
            <div class="modal-body">
                <form id="clientForm">
                    <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="name" required></div>
                    <div class="form-group"><label class="form-label">Phone <span class="required">*</span></label>
                        <input type="tel" class="form-control" name="phone" required placeholder="09XXXXXXXXX"></div>
                    <div class="form-group"><label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email"></div>
                    <div class="form-group"><label class="form-label">Address</label>
                        <textarea class="form-control" name="address" rows="2"></textarea></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveClientBtn" onclick="saveClient()"><i class="fas fa-save"></i> Save Client</button>
            </div>
        </div>
    </div>
</div>

<script>
let menusFD = [], allBookings = [];
let allDishes = { mains: [], desserts: [] };
let currentBookingPageFD = 1;
let bookingTotalPagesFD = 1;

async function initFD() {
    await loadDishes();
    await loadBookingsFD();
}

async function loadDishes() {
    try {
        const d = await Api.get(BASE + 'src/api/packages.php', { dishes: 1 });
        allDishes.mains = d.mainDishes || [];
        allDishes.desserts = d.desserts || [];
    } catch(e) {}
}

async function loadBookingsFD(page = null) {
    if (page !== null) {
        currentBookingPageFD = Math.max(1, page);
    }

    const status = document.getElementById('filterStatus').value;
    const search = document.getElementById('searchInput').value;
    const order  = document.getElementById('filterOrder').value;
    const params = {
        page: currentBookingPageFD,
        limit: 10,
    };
    if (status) params.status = status;
    if (search) params.search = search;
    if (order)  params.order = order;
    const d = await Api.get(BASE + 'src/api/bookings.php', params);
    allBookings = d.bookings || [];
    bookingTotalPagesFD = d.meta?.totalPages || 1;
    const totalRecords = d.meta?.totalRecords ?? allBookings.length;
    document.getElementById('countLabel').textContent = `${totalRecords} booking${totalRecords === 1 ? '' : 's'} found`;
    renderBookingPaginationFD();
    const tbody = document.getElementById('bookingBody');
    if (!allBookings.length) {
        tbody.innerHTML = `<tr><td colspan="8"><div class="table-empty"><i class="fas fa-calendar-xmark"></i><p>No bookings found.</p></div></td></tr>`;
        return;
    }
    tbody.innerHTML = allBookings.map(b => `
        <tr>
            <td class="td-name">${esc(b.client_name)}<br><small class="text-muted">${esc(b.client_phone)}</small></td>
            <td>${Format.dateShort(b.event_date)}<br><small class="text-muted">${b.event_time ? Format.time(b.event_time) : ''}</small></td>
            <td>${b.menu_name}</td>
            <td>${b.pax_count}</td>
            <td>
                ${Format.bookingBadge(b.booking_status)}
                ${parseInt(b.resched_count) > 0 ? `<br><span class="badge bg-soft-info text-info mt-1" style="font-size:10px;"><i class="fas fa-redo me-1"></i>Rescheduled</span>` : ''}
            </td>
            <td>${Format.paymentBadge(b.payment_status)}</td>
            <td class="td-actions">
                <button class="btn btn-outline-primary btn-sm" onclick="openEdit(${b.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <a href="${BASE}views/frontdesk/costing.php?booking_id=${b.id}" class="btn btn-outline-secondary btn-sm" title="Grocery">
                    <i class="fas fa-cart-shopping"></i>
                </a>
                <a href="${BASE}views/frontdesk/dispatching.php?booking_id=${b.id}" class="btn btn-outline-secondary btn-sm" title="Dispatch">
                    <i class="fas fa-bullhorn"></i>
                </a>
            </td>
        </tr>`).join('');
}

function openAddBooking() { openBookingStepper(); }


async function openEdit(id) {
    try {
        const d = await Api.get(BASE + 'src/api/bookings.php', { id });
        const b = d.booking;
        document.getElementById('edit_id').value = id;
        document.getElementById('editIdLabel').textContent = id;
        document.getElementById('edit_event_date').value = b.event_date;
        document.getElementById('edit_bs').value = b.booking_status;
        document.getElementById('edit_pax_count').value = b.pax_count;
        document.getElementById('edit_pax_display').textContent = b.pax_count;
        document.getElementById('edit_notes').value = b.notes || '';
        document.getElementById('edit_updated_at').value = b.updated_at;

        // Enforce 14-day UI restriction for rescheduling
        const minDate = new Date();
        minDate.setDate(minDate.getDate() + 14);
        document.getElementById('edit_event_date').min = minDate.toISOString().split('T')[0];

        // Dish extraction/building removed as per policy shift
        Modal.open('editStatusModal');

        Modal.open('editStatusModal');
    } catch (e) { Toast.error(e.message); }
}

async function saveStatusFD() {
    try {
        const data = Form.serialize(document.getElementById('editStatusForm'));
        await Api.put(BASE + 'src/api/bookings.php', data);
        Toast.success('Status updated.');
        Modal.close('editStatusModal');
        await loadBookingsFD();
    } catch (e) { Toast.error(e.message); }
}

async function saveClient() {
    const btn  = document.getElementById('saveClientBtn');
    const form = document.getElementById('clientForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    Form.setLoading(btn, true);
    try {
        await Api.post(BASE + 'src/api/clients.php', Form.serialize(form));
        Toast.success('Client added! Reload to see them in the dropdown.');
        Modal.close('addClientModal');
        form.reset();
        await initFD(); // re-init to refresh client selects
    } catch (e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

['filterStatus', 'filterOrder'].forEach(id => document.getElementById(id).addEventListener('change', () => loadBookingsFD(1)));
document.getElementById('searchInput').addEventListener('input', debounce(() => loadBookingsFD(1), 400));

function changeBookingPageFD(newPage) {
    if (newPage < 1 || newPage > bookingTotalPagesFD) return;
    loadBookingsFD(newPage);
}

function renderBookingPaginationFD() {
    document.getElementById('bookingPageInfoFD').textContent = `Page ${currentBookingPageFD} of ${bookingTotalPagesFD}`;
    document.getElementById('bookingPrevBtnFD').disabled = currentBookingPageFD <= 1;
    document.getElementById('bookingNextBtnFD').disabled = currentBookingPageFD >= bookingTotalPagesFD;
}

initFD();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
