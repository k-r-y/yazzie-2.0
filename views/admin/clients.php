<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Client Management';
$pageSubtitle = 'View and manage client profiles and contact information';
$activePage   = 'clients';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-total-clients">—</div>
                <div class="stat-label">Total Clients</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-md-4">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-active-bookings">—</div>
                <div class="stat-label">Upcoming Bookings</div>
            </div>
        </div>
    </div>
    <div class="col-sm-12 col-md-4">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-user-plus"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-new-this-month">—</div>
                <div class="stat-label">New This Month</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Client Directory</div>
        </div>
        <div style="display:flex;gap:10px;">
            <div class="search-box-v2" style="width:280px;">
                <i class="fas fa-search"></i>
                <input type="text" id="clientSearch" placeholder="Search name, phone, or email..." oninput="filterClients()">
            </div>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Client
            </button>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Client Name</th>
                    <th>Contact Information</th>
                    <th>Location / Address</th>
                    <th class="text-center">Total Bookings</th>
                    <th class="td-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="clientsBody">
                <tr><td colspan="5"><div class="spinner"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- CLIENT MODAL -->
<div class="modal fade" id="clientModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Edit Client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="clientForm">
                    <input type="hidden" name="id" id="f-id">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="name" id="f-name" required placeholder="e.g. Juan Dela Cruz">
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Phone Number <span class="required">*</span></label>
                            <input type="text" class="form-control" name="phone" id="f-phone" required placeholder="09XX XXX XXXX">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address <span class="required">*</span></label>
                            <input type="email" class="form-control" name="email" id="f-email" required placeholder="client@email.com">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Messenger Link</label>
                        <div style="position:relative;">
                            <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--label-4);font-size:12px;">m.me/</span>
                            <input type="text" class="form-control" name="messenger_link" id="f-msgr" style="padding-left:50px;" placeholder="username">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Home / Event Address</label>
                        <textarea class="form-control" name="address" id="f-address" rows="2" placeholder="Street, Barangay, City..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveBtn" onclick="saveClient()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let clients = [];
let clientModal;

async function init() {
    clientModal = new bootstrap.Modal(document.getElementById('clientModal'));
    await loadData();
}

async function loadData() {
    try {
        const [res, statsRes] = await Promise.all([
            Api.get(BASE + '/src/api/clients.php'),
            Api.get(BASE + '/src/api/analytics.php', { type: 'kpis' }) 
        ]);

        clients = res.clients || [];
        renderClients(clients);

        // Update KPIs
        document.getElementById('kpi-total-clients').textContent = clients.length;
        document.getElementById('kpi-active-bookings').textContent = statsRes.active_bookings || 0;
        
        // Compute "New this month" from client created_at dates
        const now = new Date();
        const thisMonth = clients.filter(c => {
            const d = new Date(c.created_at);
            return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
        }).length;
        document.getElementById('kpi-new-this-month').textContent = thisMonth;

    } catch (e) {
        Toast.error('Failed to load client data: ' + e.message);
    }
}

function renderClients(list) {
    const tbody = document.getElementById('clientsBody');
    if (!list.length) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state">No clients found.</div></td></tr>';
        return;
    }

    tbody.innerHTML = list.map(c => {
        const initials = c.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
        return `
        <tr>
            <td>
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm">${initials}</div>
                    <div>
                        <div class="fw-700 text-sm">${esc(c.name)}</div>
                        <div class="text-xs text-muted">ID: #${c.id}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class="text-sm fw-600">${esc(c.phone)}</div>
                <div class="text-xs text-muted">${esc(c.email)}</div>
                ${c.messenger_link ? `
                    <a href="https://m.me/${esc(c.messenger_link)}" target="_blank" class="text-xs text-primary text-decoration-none">
                        <i class="fab fa-facebook-messenger"></i> m.me/${esc(c.messenger_link)}
                    </a>` : ''}
            </td>
            <td>
                <div class="text-xs text-muted text-truncate-2" style="max-width:240px;">
                    <i class="fas fa-location-dot me-1"></i> ${esc(c.address || 'No address provided')}
                </div>
            </td>
            <td class="text-center">
                <span class="badge ${c.total_bookings > 0 ? 'badge-accepted' : 'badge-pending'}">
                    ${c.total_bookings} Booking${c.total_bookings == 1 ? '' : 's'}
                </span>
            </td>
            <td class="td-actions">
                <button class="btn btn-outline-primary btn-sm" onclick="openEditModal(${c.id})">
                    <i class="fas fa-pencil"></i> Edit
                </button>
                <button class="btn btn-danger btn-sm" onclick="deleteClient(${c.id}, '${esc(c.name)}')">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        </tr>
        `;
    }).join('');
}

function filterClients() {
    const q = document.getElementById('clientSearch').value.toLowerCase();
    const filtered = clients.filter(c => 
        c.name.toLowerCase().includes(q) || 
        c.email.toLowerCase().includes(q) || 
        c.phone.includes(q)
    );
    renderClients(filtered);
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Client';
    document.getElementById('clientForm').reset();
    document.getElementById('f-id').value = '';
    clientModal.show();
}

function openEditModal(id) {
    const c = clients.find(x => x.id == id);
    if (!c) return;

    document.getElementById('modalTitle').textContent = 'Edit Client Information';
    document.getElementById('f-id').value    = c.id;
    document.getElementById('f-name').value  = c.name;
    document.getElementById('f-phone').value = c.phone;
    document.getElementById('f-email').value = c.email;
    document.getElementById('f-msgr').value  = c.messenger_link || '';
    document.getElementById('f-address').value = c.address || '';
    
    clientModal.show();
}

async function saveClient() {
    const id = document.getElementById('f-id').value;
    const data = {
        name: document.getElementById('f-name').value.trim(),
        phone: document.getElementById('f-phone').value.trim(),
        email: document.getElementById('f-email').value.trim(),
        messenger_link: document.getElementById('f-msgr').value.trim(),
        address: document.getElementById('f-address').value.trim()
    };

    if (id) data.id = id;

    const btn = document.getElementById('saveBtn');
    Form.setLoading(btn, true);

    try {
        if (id) {
            await Api.put(BASE + '/src/api/clients.php', data);
            Toast.success('Client updated successfully');
        } else {
            await Api.post(BASE + '/src/api/clients.php', data);
            Toast.success('New client added');
        }
        clientModal.hide();
        loadData();
    } catch (e) {
        Toast.error(e.message);
    } finally {
        Form.setLoading(btn, false);
    }
}

async function deleteClient(id, name) {
    if (!await confirmDialog(`Are you sure you want to delete ${name}? This cannot be undone.`)) return;

    try {
        await Api.delete(BASE + '/src/api/clients.php', { id });
        Toast.success('Client removed');
        loadData();
    } catch (e) {
        Toast.error(e.message);
    }
}

init();
</script>

<style>
.avatar-sm {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: var(--glass-3);
    border: 1.5px solid var(--glass-sep);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
    font-weight: 800;
    color: var(--sys-green-dark);
}
.text-truncate-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.search-box-v2 {
    position: relative;
}
.search-box-v2 i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--label-4);
    font-size: 13px;
}
.search-box-v2 input {
    width: 100%;
    height: 34px;
    padding-left: 36px;
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 13px;
    transition: var(--tr);
}
.search-box-v2 input:focus {
    border-color: var(--sys-green);
    background: var(--surface-1);
    box-shadow: 0 0 0 3px rgba(48,209,88,0.15);
}
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
