<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Staff Management';
$pageSubtitle = 'Manage staff accounts, schedules & leave requests';
$activePage   = 'staff';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-total">—</div>
                <div class="stat-label">Total Staff</div>
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
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-umbrella-beach"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-on-leave">—</div>
                <div class="stat-label">On Leave Today</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="cursor:pointer;" onclick="switchTab('leaves',document.getElementById('tabLeaves'))">
            <div class="stat-icon red"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-pending-leave">—</div>
                <div class="stat-label">Pending Leave Requests</div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<div style="display:flex;gap:0;border-bottom:1px solid var(--glass-sep);margin-bottom:18px;">
    <button class="staff-tab active" id="tabRoster"  onclick="switchTab('roster',  this)"><i class="fas fa-users me-1"></i>Staff Roster</button>
    <button class="staff-tab"        id="tabLeaves"  onclick="switchTab('leaves',  this)"><i class="fas fa-calendar-xmark me-1"></i>Leave Requests <span class="badge badge-pending ms-1" id="tabLeaveBadge"></span></button>
    <button class="staff-tab"        id="tabSchedule" onclick="switchTab('schedule', this)"><i class="fas fa-calendar-day me-1"></i>Today's Roster</button>
</div>

<!-- ── TAB: Roster ─────────────────────────────────────────────── -->
<div id="panel-roster">
    <div class="card">
        <div class="card-header">
            <div><div class="card-title">All Staff Members</div></div>
            <button class="btn btn-primary btn-sm" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Staff
            </button>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Upcoming Events</th>
                        <th class="td-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="staffBody"><tr><td colspan="7"><div class="spinner"></div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── TAB: Leave Requests ────────────────────────────────────── -->
<div id="panel-leaves" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Leave Requests</div>
                <div class="card-subtitle">Review and approve or reject staff leave applications</div>
            </div>
            <select class="form-control" id="leaveFilter" style="width:140px;" onchange="loadLeaves()">
                <option value="pending">⏳ Pending</option>
                <option value="approved">✅ Approved</option>
                <option value="rejected">❌ Rejected</option>
                <option value="">All</option>
            </select>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Leave Date</th>
                        <th>Reason</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th class="td-actions">Action</th>
                    </tr>
                </thead>
                <tbody id="leavesBody"><tr><td colspan="6"><div class="spinner"></div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── TAB: Today's Roster ────────────────────────────────────── -->
<div id="panel-schedule" style="display:none;">
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Today's Event Roster</div>
                <div class="card-subtitle">Staff assigned to today's events</div>
            </div>
        </div>
        <div class="card-body" id="todayRoster"><div class="spinner"></div></div>
    </div>
</div>

<!-- ── ADD / EDIT STAFF MODAL ─────────────────────────────────── -->
<div class="modal fade" id="staffModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staffModalTitle">Add Staff Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="staffForm">
                    <input type="hidden" name="id" id="sf-id">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" class="form-control" name="name" id="sf-name" required placeholder="e.g. Maria Santos" pattern="^[a-zA-Z\s\-\.]+$" title="Only letters, spaces, hyphens, and periods allowed.">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" class="form-control" name="phone" id="sf-phone" placeholder="09XX XXX XXXX" pattern="^(09|\+639)\d{9}$" maxlength="13" title="Enter a valid PH mobile number (e.g. 09XXXXXXXXX or +639XXXXXXXXX)">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" class="form-control" name="email" id="sf-email" required placeholder="staff@email.com">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select class="form-control" name="role" id="sf-role" onchange="onRoleChange()">
                            <option value="staff">Staff</option>
                            <option value="frontdesk">Front Desk</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <!-- Job Class: visible only for staff role -->
                    <div class="form-group" id="jobClassGroup" style="display:none;">
                        <label class="form-label">Job Classification <span class="required">*</span></label>
                        <select class="form-control" name="job_class" id="sf-job-class">
                            <option value="waiter">🤵 Waiter</option>
                            <option value="head_cook">👨‍🍳 Head Cook</option>
                            <option value="cook">🍳 Cook</option>
                            <option value="server">🍽️ Food Server</option>
                            <option value="helper">🙋 Helper</option>
                        </select>
                        <div class="form-hint">Used to suggest the right staff in the dispatching tool.</div>
                    </div>
                    <div class="form-group" id="sf-pw-group">
                        <label class="form-label">Password <span class="required" id="sf-pw-req">*</span></label>
                        <input type="password" class="form-control" name="password" id="sf-pw" placeholder="Min. 8 characters">
                        <div class="form-hint" id="sf-pw-hint" style="display:none;">Leave blank to keep existing password.</div>
                    </div>
                    <div class="form-group" id="sf-active-group" style="display:none;">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="is_active" id="sf-active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="saveStaffBtn" onclick="saveStaff()">
                    <i class="fas fa-save"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.staff-tab {
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    color: var(--label-3);
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    cursor: pointer;
    transition: var(--tr);
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}
.staff-tab:hover { color: var(--label-2); }
.staff-tab.active { color: var(--sys-green-dark); border-bottom-color: var(--sys-green); }

.avail-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 99px;
}
.avail-available { background: rgba(48,209,88,0.12); color: #1A7A32; }
.avail-on_leave  { background: rgba(255,149,0,0.12);  color: #9A5400; }
.avail-booked    { background: rgba(142,142,147,0.12);color: #636366; }
</style>

<script>
let staffModal, allStaff = [];

function switchTab(name, el) {
    ['roster','leaves','schedule'].forEach(t => {
        document.getElementById('panel-' + t).style.display = 'none';
    });
    document.querySelectorAll('.staff-tab').forEach(b => b.classList.remove('active'));
    document.getElementById('panel-' + name).style.display = 'block';
    el.classList.add('active');
    if (name === 'leaves')   loadLeaves();
    if (name === 'schedule') loadTodayRoster();
}

// ── KPI ────────────────────────────────────────────────────────────
async function loadKPIs() {
    const today = new Date().toISOString().split('T')[0];
    const [usersData, leaveData, pendingLeave, availData] = await Promise.all([
        Api.get(BASE + '/src/api/staff.php', { role: 'staff' }),
        Api.get(BASE + '/src/api/leave.php',  { date: today }),
        Api.get(BASE + '/src/api/leave.php',  { pending_only: 1 }),
        Api.get(BASE + '/src/api/staff.php',  { available_on: today }),
    ]);
    allStaff = usersData.users || [];
    const active = allStaff.filter(s => s.is_active == 1).length;
    const onLeave = (leaveData.on_leave || []).length;
    const pending = (pendingLeave.leaves || []).length;

    document.getElementById('kpi-total').textContent    = allStaff.length;
    document.getElementById('kpi-active').textContent   = active;
    document.getElementById('kpi-on-leave').textContent = onLeave;
    document.getElementById('kpi-pending-leave').textContent = pending;
    document.getElementById('tabLeaveBadge').textContent = pending || '';
}

// ── STAFF ROSTER TABLE ─────────────────────────────────────────────
async function loadRoster() {
    try {
        const today = new Date().toISOString().split('T')[0];
        const [usersData, schedData] = await Promise.all([
            Api.get(BASE + '/src/api/staff.php', { role: 'staff' }),
            Api.get(BASE + '/src/api/staff.php',  { available_on: today }),
        ]);
        allStaff = usersData.users || [];
        const schedStaff = schedData.staff || [];
        const availMap = {};
        schedStaff.forEach(s => availMap[s.id] = s.availability);

        const tbody = document.getElementById('staffBody');
        if (!allStaff.length) {
            tbody.innerHTML = `<tr><td colspan="7"><div class="table-empty"><i class="fas fa-users"></i><p>No staff found.</p></div></td></tr>`;
            return;
        }

        tbody.innerHTML = allStaff.map(s => {
            const av = availMap[s.id] || 'available';
            const avLabel = { available: '🟢 Available', on_leave: '🟡 On Leave', booked: '⚫ Booked' };
            const statusBadge = s.is_active == 1
                ? '<span class="badge badge-accepted">Active</span>'
                : '<span class="badge badge-cancelled">Inactive</span>';
            const jobClassLabel = { head_cook: '👨‍🍳 Head Cook', cook: '🍳 Cook', waiter: '🤵 Waiter', server: '🍽️ Server', helper: '🙋 Helper', admin: '⚙️ Admin', super_admin: '👑 Super Admin', frontdesk: '💻 Front Desk' };
            const jobClassText = (s.job_class && s.job_class !== 'any') 
                ? `<br><small class="text-muted">${jobClassLabel[s.job_class] || s.job_class}</small>` 
                : '';

            return `
            <tr>
                <td class="td-name">${htmlEsc(s.name)}${jobClassText}</td>
                <td class="text-sm text-muted">${htmlEsc(s.email)}</td>
                <td class="text-sm">${htmlEsc(s.phone || '—')}</td>
                <td>${statusBadge}</td>
                <td><span class="avail-chip avail-${av}">${avLabel[av]||av}</span></td>
                <td class="td-actions">
                    <button class="btn btn-outline-primary btn-sm" onclick='openEditModal(${JSON.stringify(s)})' title="Edit">
                        <i class="fas fa-pencil"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="toggleActive(${s.id}, ${s.is_active})" title="${s.is_active ? 'Deactivate' : 'Reactivate'}">
                        <i class="fas fa-${s.is_active ? 'user-slash' : 'user-check'}"></i>
                    </button>
                </td>
            </tr>`;
        }).join('');
    } catch(e) { Toast.error('Failed to load staff roster.'); }
}

// ── LEAVE REQUESTS ─────────────────────────────────────────────────
async function loadLeaves() {
    const status = document.getElementById('leaveFilter').value;
    try {
        const d = await Api.get(BASE + '/src/api/leave.php', status ? { status } : {});
        const leaves = d.leaves || [];
        const tbody  = document.getElementById('leavesBody');
        if (!leaves.length) {
            tbody.innerHTML = `<tr><td colspan="6"><div class="table-empty"><i class="fas fa-calendar-check"></i><p>No leave requests found.</p></div></td></tr>`;
            return;
        }
        tbody.innerHTML = leaves.map(l => {
            const statusBadge = {
                pending:  '<span class="badge badge-pending">⏳ Pending</span>',
                approved: '<span class="badge badge-accepted">✅ Approved</span>',
                rejected: '<span class="badge badge-cancelled">❌ Rejected</span>',
            }[l.status] || l.status;
            const actions = l.status === 'pending' ? `
                <button class="btn btn-primary btn-sm" onclick="reviewLeave(${l.id},'approved')">
                    <i class="fas fa-check"></i>
                </button>
                <button class="btn btn-danger btn-sm ms-1" onclick="reviewLeave(${l.id},'rejected')">
                    <i class="fas fa-times"></i>
                </button>` : '—';
            return `
            <tr>
                <td class="td-name">${htmlEsc(l.staff_name)}<br><small class="text-muted">${htmlEsc(l.staff_phone||'')}</small></td>
                <td>${Format.dateShort(l.leave_date)}</td>
                <td class="text-sm text-muted">${htmlEsc(l.reason||'—')}</td>
                <td class="text-xs text-muted">${Format.dateShort(l.created_at)}</td>
                <td>${statusBadge}</td>
                <td class="td-actions">${actions}</td>
            </tr>`;
        }).join('');
    } catch(e) { Toast.error('Failed to load leave requests.'); }
}

async function reviewLeave(id, status) {
    const label = status === 'approved' ? 'Approve' : 'Reject';
    if (!await confirmDialog(`${label} this leave request? The staff member will be notified.`)) return;
    try {
        await Api.put(BASE + '/src/api/leave.php', { id, status });
        Toast.success(`Leave request ${status}.`);
        loadLeaves();
        loadKPIs();
    } catch(e) { Toast.error(e.message); }
}

// ── TODAY'S ROSTER ─────────────────────────────────────────────────
async function loadTodayRoster() {
    const today = new Date().toISOString().split('T')[0];
    const d = document.getElementById('todayRoster');
    d.innerHTML = '<div class="spinner"></div>';
    try {
        const data = await Api.get(BASE + '/src/api/staff.php', { available_on: today });
        const staff = data.staff || [];
        const booked   = staff.filter(s => s.availability === 'booked');
        const onLeave  = staff.filter(s => s.availability === 'on_leave');
        const avail    = staff.filter(s => s.availability === 'available');

        const renderGroup = (list, label, icon, cls) => {
            if (!list.length) return '';
            return `<div style="margin-bottom:16px;">
                <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--label-3);margin-bottom:8px;">${icon} ${label} (${list.length})</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    ${list.map(s => `<div class="avail-chip avail-${s.availability}" style="padding:6px 12px;font-size:12px;">
                        <i class="fas fa-user" style="margin-right:5px;font-size:10px;"></i>${htmlEsc(s.name)}
                    </div>`).join('')}
                </div>
            </div>`;
        };

        d.innerHTML = renderGroup(booked, 'Assigned Today', '⚫', 'booked')
            + renderGroup(onLeave, 'On Leave', '🟡', 'on_leave')
            + renderGroup(avail, 'Available', '🟢', 'available')
            || '<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-calendar"></i></div><h3>No staff data found.</h3></div>';
    } catch(e) { d.innerHTML = '<p class="text-muted text-center p-4">Failed to load.</p>'; }
}

// ── ADD / EDIT STAFF ───────────────────────────────────────────────
function openAddModal() {
    document.getElementById('staffModalTitle').textContent = 'Add Staff Member';
    document.getElementById('staffForm').reset();
    document.getElementById('sf-id').value = '';
    document.getElementById('sf-pw-hint').style.display = 'none';
    document.getElementById('sf-pw-req').style.display  = 'inline';
    document.getElementById('sf-active-group').style.display = 'none';
    onRoleChange();
    staffModal.show();
}

function onRoleChange() {
    const role = document.getElementById('sf-role').value;
    const jcGroup = document.getElementById('jobClassGroup');
    if (jcGroup) jcGroup.style.display = (role === 'staff') ? '' : 'none';
}

function openEditModal(s) {
    document.getElementById('staffModalTitle').textContent = 'Edit Staff Member';
    document.getElementById('sf-id').value    = s.id;
    document.getElementById('sf-name').value  = s.name;
    document.getElementById('sf-email').value = s.email;
    document.getElementById('sf-phone').value = s.phone || '';
    document.getElementById('sf-role').value  = s.role;
    document.getElementById('sf-active').value = s.is_active;
    document.getElementById('sf-job-class').value = (s.job_class && s.job_class !== 'any') ? s.job_class : 'waiter';
    document.getElementById('sf-pw').value    = '';
    document.getElementById('sf-pw-hint').style.display = 'block';
    document.getElementById('sf-pw-req').style.display  = 'none';
    document.getElementById('sf-active-group').style.display = 'block';
    onRoleChange();
    staffModal.show();
}

async function saveStaff() {
    const form = document.getElementById('staffForm');
    const id   = document.getElementById('sf-id').value;
    const data = {
        name:      document.getElementById('sf-name').value.trim(),
        email:     document.getElementById('sf-email').value.trim(),
        phone:     document.getElementById('sf-phone').value.trim(),
        role:      document.getElementById('sf-role').value,
        job_class: document.getElementById('sf-job-class').value,
        password:  document.getElementById('sf-pw').value,
    };
    if (id) {
        data.id        = parseInt(id);
        data.is_active = parseInt(document.getElementById('sf-active').value);
    }

    const btn = document.getElementById('saveStaffBtn');
    Form.setLoading(btn, true);
    try {
        if (id) {
            await Api.put(BASE + '/src/api/staff.php', data);
            Toast.success('Staff member updated.');
        } else {
            await Api.post(BASE + '/src/api/staff.php', data);
            Toast.success('Staff member added.');
        }
        staffModal.hide();
        loadRoster();
        loadKPIs();
    } catch(e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

async function toggleActive(id, current) {
    const action = current == 1 ? 'Deactivate' : 'Reactivate';
    if (!await confirmDialog(`${action} this staff member?`)) return;
    try {
        await Api.put(BASE + '/src/api/staff.php', { id, is_active: current == 1 ? 0 : 1 });
        Toast.success(`Staff member ${action.toLowerCase()}d.`);
        loadRoster();
        loadKPIs();
    } catch(e) { Toast.error(e.message); }
}

function htmlEsc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── INIT ───────────────────────────────────────────────────────────
staffModal = new bootstrap.Modal(document.getElementById('staffModal'));
loadKPIs();
loadRoster();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
