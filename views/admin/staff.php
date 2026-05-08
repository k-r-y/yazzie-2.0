<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'User & Staff Management';
$pageSubtitle = 'Unified control for administrators, desk officers, and event staff';
$activePage   = 'staff';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-user-shield"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-admins">—</div>
                <div class="stat-label">Active Admins</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-total">—</div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-user-check"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-active">—</div>
                <div class="stat-label">Active Staff</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="cursor:pointer;" onclick="switchTab('leaves',document.getElementById('tabLeaves'))">
            <div class="stat-icon orange"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-pending-leave">—</div>
                <div class="stat-label">Pending Leaves</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div class="glass-tabs mb-4">
    <button class="glass-tab active" id="tabRoster"  onclick="switchTab('roster',  this)"><i class="fas fa-users-cog me-1"></i>Unified Directory</button>
    <button class="glass-tab"        id="tabLeaves"  onclick="switchTab('leaves',  this)"><i class="fas fa-calendar-xmark me-1"></i>Leave Requests <span class="badge badge-pending ms-1" id="tabLeaveBadge"></span></button>
    <button class="glass-tab"        id="tabSchedule" onclick="switchTab('schedule', this)"><i class="fas fa-calendar-day me-1"></i>Today's Roster</button>
</div>

<!-- ── TAB: Unified Roster ─────────────────────────────────────────────── -->
<div id="panel-roster">
    <div class="card mb-3">
        <div class="card-body p-3">
            <div class="search-bar">
                <div class="search-input-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" id="staffSearch" placeholder="Search name, email, or phone...">
                </div>
                <select class="form-control" id="staffFilterRole" style="width:160px;">
                    <option value="">All Roles</option>
                    <option value="admin">Administrators</option>
                    <option value="frontdesk">Front Desk</option>
                    <option value="staff">Event Staff</option>
                </select>
                <select class="form-control" id="staffFilterStatus" style="width:140px;">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Unified User Directory</div>
                <div class="card-subtitle" id="staffCount">Loading users...</div>
            </div>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Onboard User
            </button>
        </div>
        <div class="table-wrapper table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Job Class</th>
                        <th>Status</th>
                        <th class="td-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="staffBody">
                    <tr><td colspan="6"><div class="spinner"></div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="table-pagination" id="staffPagination">
            <button type="button" class="pagination-button" id="staffPrevBtn" onclick="changeStaffPage(currentStaffPage - 1)" disabled>
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <div class="pagination-info" id="staffPageInfo">Page 1 of 1</div>
            <button type="button" class="pagination-button" id="staffNextBtn" onclick="changeStaffPage(currentStaffPage + 1)" disabled>
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>
</div>

<!-- ── TAB: Leave Requests (Merged) ────────────────────────────────────── -->
<div id="panel-leaves" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Leave Applications</div>
                <div class="card-subtitle">Manage time-off requests for all staff levels</div>
            </div>
            <select class="form-control" id="leaveFilter" style="width:140px;" onchange="loadLeaves()">
                <option value="pending">⏳ Pending</option>
                <option value="approved">✅ Approved</option>
                <option value="rejected">❌ Rejected</option>
                <option value="">All</option>
            </select>
        </div>
        <div class="table-wrapper table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Staff member</th>
                        <th>Date</th>
                        <th>Reason</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th class="td-actions">Action</th>
                    </tr>
                </thead>
                <tbody id="leavesBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── TAB: Today's Roster (Merged) ────────────────────────────────────── -->
<div id="panel-schedule" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div class="card-title">Event Operations Roster</div>
        </div>
        <div class="card-body" id="todayRoster"></div>
    </div>
</div>

<!-- ── UNIFIED ADD / EDIT MODAL ─────────────────────────────────── -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalTitle">Add New Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="staffForm">
                    <input type="hidden" name="id" id="sf-id">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" id="sf-name" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="sf-phone" maxlength="11">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" id="sf-email" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">System Role</label>
                        <select class="form-control" name="role" id="sf-role" onchange="toggleJobClassField()">
                            <option value="staff">Staff</option>
                            <option value="frontdesk">Front Desk</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <!-- Dynamic Job Class Field -->
                    <div class="form-group" id="jobClassGroup">
                        <label class="form-label">Job Classification</label>
                        <select class="form-control" name="job_class" id="sf-job-class">
                            <option value="waiter">🤵 Waiter</option>
                            <option value="head_cook">👨‍🍳 Head Cook</option>
                            <option value="cook">🍳 Cook</option>
                            <option value="server">🍽️ Server</option>
                            <option value="helper">🙋 Helper</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" id="sf-pw" placeholder="Min. 8 characters">
                        <div class="form-hint" id="sf-pw-hint" style="display:none;">Leave blank to keep current password.</div>
                    </div>
                    <div class="form-group" id="sf-active-group">
                        <label class="form-label">Account Status</label>
                        <select class="form-control" name="is_active" id="sf-active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-glass" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveStaffBtn" onclick="handleStaffSave()">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── MASTER KEY TRANSFER MODAL ─────────────────────────────────── -->
<div class="modal fade" id="masterKeyTransferModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger shadow-lg">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Master Key Transfer</h5>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-key fa-3x text-danger opacity-50"></i>
                </div>
                <h4 class="fw-bold text-danger">Warning: Administrative Handover</h4>
                <p class="text-muted px-3">
                    Activating this <strong>Admin</strong> account will immediately <strong>deactivate</strong> your own account and terminate your current session.
                </p>
                <p class="fw-bold mb-0">Proceed with the transfer and logout?</p>
            </div>
            <div class="modal-footer justify-content-center">
                <button class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger px-4" id="confirmTransferBtn">
                    Yes, Transfer & Logout
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Glassmorphism Tabs */
.glass-tabs {
    display: flex;
    gap: 8px;
    border-bottom: 1px solid var(--glass-sep);
    padding-bottom: 2px;
}
.glass-tab {
    padding: 10px 20px;
    font-size: 13px;
    font-weight: 600;
    color: var(--label-3);
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid transparent;
    border-radius: 8px 8px 0 0;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.glass-tab:hover { color: var(--label-2); background: rgba(255, 255, 255, 0.1); }
.glass-tab.active { 
    color: var(--sys-green-dark); 
    background: rgba(48, 209, 88, 0.08); 
    border-color: var(--glass-sep);
    border-bottom-color: var(--bg-1);
    margin-bottom: -1px;
}

/* Glassmorphism Badges */
.badge-glass {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.badge-admin { 
    background: rgba(255, 215, 0, 0.15); 
    color: #D4AF37; 
    border-color: rgba(255, 215, 0, 0.3);
}
.badge-frontdesk { 
    background: rgba(0, 122, 255, 0.15); 
    color: #007AFF; 
    border-color: rgba(0, 122, 255, 0.3);
}
.badge-staff { 
    background: rgba(48, 209, 88, 0.15); 
    color: #28A745; 
    border-color: rgba(48, 209, 88, 0.3);
}

.btn-glass {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-sep);
    color: var(--label-2);
}
.btn-glass:hover { background: rgba(255, 255, 255, 0.1); }
</style>

<script>
const currentUserId = <?= (int)$_SESSION['user_id'] ?>;
let staffModal, transferModal, allStaff = [];
let currentStaffPage = 1;
let staffTotalPages = 1;
let pendingTransferData = null;

// ── UI INTERACTIVITY ───────────────────────────────────────────────

function switchTab(name, el) {
    ['roster','leaves','schedule'].forEach(t => {
        document.getElementById('panel-' + t).style.display = 'none';
    });
    document.querySelectorAll('.glass-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + name).style.display = 'block';
    el.classList.add('active');
    if (name === 'leaves')   loadLeaves();
    if (name === 'schedule') loadTodayRoster();
}

/**
 * Dynamic Fields Logic: Role-based field visibility
 */
function toggleJobClassField() {
    const role = document.getElementById('sf-role').value;
    const group = document.getElementById('jobClassGroup');
    const select = document.getElementById('sf-job-class');
    
    if (role === 'staff') {
        group.style.display = 'block';
    } else {
        group.style.display = 'none';
        select.value = ''; // Nullify for non-staff
    }
}

// ── API FETCHING & RENDERING ───────────────────────────────────────

async function loadKPIs() {
    try {
        const [usersData, leaveData] = await Promise.all([
            Api.get(BASE + 'src/api/staff.php', { limit: 1000 }),
            Api.get(BASE + 'src/api/leave.php',  { pending_only: 1 }),
        ]);
        const users = usersData.users || [];
        document.getElementById('kpi-admins').textContent = users.filter(u => u.role === 'admin' && u.is_active == 1).length;
        document.getElementById('kpi-total').textContent = users.length;
        document.getElementById('kpi-active').textContent = users.filter(u => u.role === 'staff' && u.is_active == 1).length;
        document.getElementById('kpi-pending-leave').textContent = (leaveData.leaves || []).length;
        document.getElementById('tabLeaveBadge').textContent = (leaveData.leaves || []).length || '';
    } catch(e) { console.error('KPI Load Failed'); }
}

async function loadRoster(page = 1) {
    currentStaffPage = page;
    const search = document.getElementById('staffSearch').value;
    const role   = document.getElementById('staffFilterRole').value;
    const status = document.getElementById('staffFilterStatus').value;

    try {
        const data = await Api.get(BASE + 'src/api/staff.php', { search, role, status, page, limit: 10 });
        allStaff = data.users || [];
        staffTotalPages = data.meta?.totalPages || 1;
        document.getElementById('staffCount').textContent = `${data.meta?.totalRecords || 0} users found`;
        renderRoster();
        renderPagination();
    } catch(e) { Toast.error('Failed to load roster.'); }
}

function renderRoster() {
    const tbody = document.getElementById('staffBody');
    if (!allStaff.length) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-5 text-muted">No records found matching filters.</td></tr>`;
        return;
    }

    tbody.innerHTML = allStaff.map(u => {
        const isSelf = parseInt(u.id) === currentUserId;
        const roleBadge = `<span class="badge-glass badge-${u.role}">${u.role}</span>`;
        const statusBadge = u.is_active == 1 
            ? '<span class="badge badge-accepted">Active</span>' 
            : '<span class="badge badge-cancelled">Inactive</span>';
        
        const jobClassLabel = { head_cook: '👨‍🍳 Cook (Head)', cook: '🍳 Cook', waiter: '🤵 Waiter', server: '🍽️ Server', helper: '🙋 Helper', admin: '—', frontdesk: '—' };
        
        return `
        <tr>
            <td class="td-name">
                <div class="fw-bold">${htmlEsc(u.name)}</div>
                <small class="text-muted">${htmlEsc(u.phone || 'No Phone')}</small>
            </td>
            <td class="text-sm">${htmlEsc(u.email)}</td>
            <td>${roleBadge}</td>
            <td class="text-sm text-muted">${jobClassLabel[u.job_class] || u.job_class || '—'}</td>
            <td>${statusBadge}</td>
            <td class="td-actions">
                <button class="btn btn-outline-primary btn-sm" onclick='openEditModal(${JSON.stringify(u)})'>
                    <i class="fas fa-edit"></i>
                </button>
                ${!isSelf ? `
                <button class="btn btn-danger btn-sm" onclick="handleStatusToggle(${u.id}, ${u.is_active}, '${u.role}')">
                    <i class="fas fa-${u.is_active == 1 ? 'user-slash' : 'user-check'}"></i>
                </button>
                ` : ''}
            </td>
        </tr>`;
    }).join('');
}

function renderPagination() {
    document.getElementById('staffPageInfo').textContent = `Page ${currentStaffPage} of ${staffTotalPages}`;
    document.getElementById('staffPrevBtn').disabled = currentStaffPage <= 1;
    document.getElementById('staffNextBtn').disabled = currentStaffPage >= staffTotalPages;
}

function changeStaffPage(p) {
    if (p < 1 || p > staffTotalPages) return;
    loadRoster(p);
}

// ── CRUD OPERATIONS & MASTER KEY HANDLER ────────────────────────────

function openAddModal() {
    document.getElementById('staffModalTitle').textContent = 'Onboard New User';
    document.getElementById('staffForm').reset();
    document.getElementById('sf-id').value = '';
    document.getElementById('sf-pw-hint').style.display = 'none';
    document.getElementById('sf-active-group').style.display = 'none';
    toggleJobClassField();
    staffModal.show();
}

function openEditModal(u) {
    document.getElementById('staffModalTitle').textContent = 'Update Account';
    document.getElementById('sf-id').value = u.id;
    document.getElementById('sf-name').value = u.name;
    document.getElementById('sf-email').value = u.email;
    document.getElementById('sf-phone').value = u.phone || '';
    document.getElementById('sf-role').value = u.role;
    document.getElementById('sf-active').value = u.is_active;
    document.getElementById('sf-job-class').value = u.job_class || '';
    document.getElementById('sf-pw').value = '';
    document.getElementById('sf-pw-hint').style.display = 'block';
    
    // UI Guard for status
    const isSelf = parseInt(u.id) === currentUserId;
    document.getElementById('sf-active-group').style.display = isSelf ? 'none' : 'block';
    
    toggleJobClassField();
    staffModal.show();
}

/**
 * Master Key Interception Logic
 */
async function handleStatusToggle(id, currentStatus, role) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    
    // If activating an admin, intercept and show warning
    if (role === 'admin' && newStatus === 1) {
        pendingTransferData = { id, is_active: 1 };
        transferModal.show();
        return;
    }

    const action = newStatus === 1 ? 'reactivate' : 'deactivate';
    if (!await confirmDialog(`Are you sure you want to ${action} this user?`)) return;
    
    executePut({ id, is_active: newStatus });
}

async function handleStaffSave() {
    const id = document.getElementById('sf-id').value;
    const role = document.getElementById('sf-role').value;
    const isActive = document.getElementById('sf-active').value;
    
    const data = {
        id: id || undefined,
        name: document.getElementById('sf-name').value,
        email: document.getElementById('sf-email').value,
        phone: document.getElementById('sf-phone').value,
        role: role,
        job_class: document.getElementById('sf-job-class').value,
        password: document.getElementById('sf-pw').value,
        is_active: id ? parseInt(isActive) : undefined
    };

    // Master Key Interception for Modal Update
    if (id && role === 'admin' && isActive == 1) {
        // We need to check if it was previously inactive. 
        // For simplicity, we can fetch from allStaff or just trigger the modal always if role=admin+active
        const prev = allStaff.find(u => u.id == id);
        if (prev && prev.is_active == 0) {
            pendingTransferData = data;
            staffModal.hide();
            transferModal.show();
            return;
        }
    }

    executeSave(data);
}

async function executeSave(data) {
    const btn = document.getElementById('saveStaffBtn');
    Form.setLoading(btn, true);
    try {
        const res = data.id 
            ? await Api.put(BASE + 'src/api/staff.php', data)
            : await Api.post(BASE + 'src/api/staff.php', data);
        
        Toast.success(res.message || 'Operation successful');
        staffModal.hide();
        loadRoster();
        loadKPIs();
    } catch(e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

async function executePut(data) {
    try {
        const res = await Api.put(BASE + 'src/api/staff.php', data);
        
        // Logout Redirection Logic
        if (res.admin_transferred) {
            Toast.success('Master Key Transferred. Logging out...');
            setTimeout(() => window.location.href = 'logout.php', 1500);
            return;
        }

        Toast.success(res.message);
        loadRoster();
        loadKPIs();
    } catch(e) { Toast.error(e.message); }
}

// ── LEAVE & ROSTER HELPERS ──────────────────────────────────────────

async function loadLeaves() {
    const status = document.getElementById('leaveFilter').value;
    try {
        const d = await Api.get(BASE + 'src/api/leave.php', status ? { status } : {});
        const leaves = d.leaves || [];
        const tbody  = document.getElementById('leavesBody');
        tbody.innerHTML = leaves.map(l => `
            <tr>
                <td class="td-name">${htmlEsc(l.staff_name)}</td>
                <td>${Format.dateShort(l.leave_date)}</td>
                <td class="text-sm text-muted">${htmlEsc(l.reason)}</td>
                <td class="text-xs text-muted">${Format.dateShort(l.created_at)}</td>
                <td><span class="badge badge-${l.status}">${l.status}</span></td>
                <td class="td-actions">
                    ${l.status === 'pending' ? `
                        <button class="btn btn-primary btn-sm" onclick="reviewLeave(${l.id},'approved')"><i class="fas fa-check"></i></button>
                    ` : '—'}
                </td>
            </tr>`).join('');
    } catch(e) { }
}

async function loadTodayRoster() {
    const today = new Date().toISOString().split('T')[0];
    const d = document.getElementById('todayRoster');
    try {
        const data = await Api.get(BASE + 'src/api/staff.php', { available_on: today });
        const staff = data.staff || [];
        d.innerHTML = staff.map(s => `
            <div class="badge-glass badge-staff m-1 d-inline-block">
                ${htmlEsc(s.name)} (${s.availability})
            </div>
        `).join('') || 'No staff assigned today.';
    } catch(e) { }
}

function htmlEsc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── INITIALIZATION ────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    staffModal = new bootstrap.Modal(document.getElementById('staffModal'));
    transferModal = new bootstrap.Modal(document.getElementById('masterKeyTransferModal'));

    loadKPIs();
    loadRoster();

    // Filter Listeners
    ['staffFilterRole', 'staffFilterStatus'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => loadRoster(1));
    });
    document.getElementById('staffSearch').addEventListener('input', debounce(() => loadRoster(1), 400));

    // Transfer Confirmation
    document.getElementById('confirmTransferBtn').addEventListener('click', () => {
        if (pendingTransferData) {
            transferModal.hide();
            executePut(pendingTransferData);
        }
    });
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
