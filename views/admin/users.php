<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'User Accounts';
$pageSubtitle = 'Manage system users and their roles';
$activePage   = 'users';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="card mb-3">
    <div class="card-body" style="padding:14px 20px;">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <div class="search-input-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" id="userSearch" placeholder="Search name/email…" title="Search users by name or email address">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px;margin-bottom:4px;" for="roleFilter">Role</label>
                <select class="form-control" id="roleFilter" onchange="applyFilters()" title="Filter the list by account role">
                    <option value="">All Roles</option>
                    <option value="admin">Administrator</option>
                    <option value="frontdesk">Front Desk</option>
                    <option value="staff">On-Call Staff</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px;margin-bottom:4px;" for="statusFilter">Status</label>
                <select class="form-control" id="statusFilter" onchange="applyFilters()" title="Filter the list by active or inactive status">
                    <option value="">All Status</option>
                    <option value="1">Active</option>
                    <option value="0">Deactivated</option>
                </select>
            </div>
            <div class="col-md-2 text-end">
                <button class="btn btn-primary py-3 w-100" onclick="openAddUser()" title="Create a new system user account">
                    <i class="fas fa-user-plus"></i> Add User
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">All User Accounts</div></div>
    <div class="table-wrapper table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="data-table" id="userTable">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Status</th><th>Created</th><th class="td-actions">Actions</th></tr>
            </thead>
            <tbody id="userBody"><tr><td colspan="7"><div class="spinner"></div></td></tr></tbody>
        </table>
    </div>
    <div class="table-pagination" id="usersPagination">
        <button type="button" class="pagination-button" id="usersPrevBtn" onclick="changeUserPage(currentUserPage - 1)" disabled>
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <div class="pagination-info" id="usersPageInfo">Page 1 of 1</div>
        <button type="button" class="pagination-button" id="usersNextBtn" onclick="changeUserPage(currentUserPage + 1)" disabled>
            Next <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<!-- USER MODAL -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="position:relative;">
                <h5 class="modal-title" id="userModalTitle" style="padding-right:40px;">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="position:absolute; right:20px; top:20px;"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" name="id" id="user_id">
                    <div class="form-group"><label class="form-label" for="u_name">Full Name <span class="required">*</span></label>
                        <input class="form-control" name="name" id="u_name" required pattern="^[a-zA-Z\s\-\.]+$" title="Legal name of the user" maxlength="100"></div>
                    <div class="form-group"><label class="form-label" for="u_email">Email Address <span class="required">*</span></label>
                        <input class="form-control" name="email" id="u_email" type="email" required maxlength="100" title="Account login email"></div>
                    <div class="form-group"><label class="form-label" for="u_role">Role <span class="required">*</span></label>
                        <select class="form-control" name="role" id="u_role" required onchange="onRoleChange()" title="Determines system access and permissions">
                            <option value="">— Select Role —</option>
                            <option value="admin" id="opt_admin">Administrator</option>
                            <option value="frontdesk">Front Desk</option>
                            <option value="staff" selected>On-Call Staff</option>
                        </select>
                    </div>
                    <!-- Job Class: visible only for staff role -->
                    <div class="form-group" id="jobClassGroup">
                        <label class="form-label" for="u_job_class">Job Classification <span class="required">*</span></label>
                        <select class="form-control" name="job_class" id="u_job_class" title="Primary function for event assignments">
                            <option value="any">— Any / Not Specified —</option>
                            <option value="head_cook">👨‍🍳 Head Cook</option>
                            <option value="cook">🍳 Cook</option>
                            <option value="waiter">🤵 Waiter</option>
                            <option value="server">🍽️ Food Server</option>
                            <option value="helper">🙋 Helper</option>
                        </select>
                        <div class="form-hint">Used to enforce booking lineup structure (e.g. 1 Head Cook required).</div>
                    </div>
                    <div class="form-group"><label class="form-label" for="u_phone">Phone</label>
                        <input class="form-control" name="phone" id="u_phone"
                               placeholder="09XXXXXXXXX"
                               data-restrict="phone"
                               maxlength="11"
                               title="11-digit mobile number for communication"></div>
                    <div class="form-group">
                        <label class="form-label" for="u_pw">Password <span class="required" id="pwReq">*</span></label>
                        <input class="form-control" name="password" id="u_pw" type="password" placeholder="Min 8 characters" title="Account login password">
                        <div class="form-hint" id="pwHint" style="display:none;">Leave blank to keep current password.</div>
                    </div>
                    <div class="form-group" id="statusGroup" style="display:none;">
                        <label class="form-label" for="u_active">Account Status</label>
                        <select class="form-control" name="is_active" id="u_active" title="Enable or deactivate this account">
                            <option value="1">Active</option>
                            <option value="0">Deactivated</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="userSaveBtn" onclick="saveUser()" title="Save account details to the system database"><i class="fas fa-save"></i> Save User</button>
            </div>
        </div>
    </div>
</div>

<script>

let currentUserPage = 1;
let userTotalPages = 1;

async function loadSettings() {
    try {
        const d = await Api.get(BASE + 'src/api/settings.php');
    } catch (e) {
        console.error('Failed to load settings:', e);
    }
}

async function loadUsers(page = null) {
    if (page !== null) {
        currentUserPage = Math.max(1, page);
    }

    const search = document.getElementById('userSearch').value;
    const role = document.getElementById('roleFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    const params = {
        page: currentUserPage,
        limit: 10,
    };
    if (search) params.search = search;
    if (role) params.role = role;
    if (status !== '') params.active_only = (status === '1' ? 1 : 0);

    const d = await Api.get(BASE + 'src/api/staff.php', params);
    const users = d.users || [];
    userTotalPages = d.meta?.totalPages || 1;
    
    
    renderUserPagination();
    const tbody = document.getElementById('userBody');
    const roleLabel = { admin: 'Administrator', frontdesk: 'Front Desk', staff: 'On-Call Staff' };
    const roleBadge = { admin: 'badge-admin', frontdesk: 'badge-frontdesk', staff: 'badge-staff' };
    const jobClassLabel = { head_cook: '👨‍🍳 Head Cook', cook: '🍳 Cook', waiter: '🤵 Waiter', server: '🍽️ Server', helper: '🙋 Helper', any: '—' };

    if (!users.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="table-empty"><i class="fas fa-users"></i><p>No users found.</p></div></td></tr>`;
        return;
    }

    tbody.innerHTML = users.map(u => `
        <tr>
            <td class="td-name">${esc(u.name)}</td>
            <td>${u.email}</td>
            <td><span class="badge ${roleBadge[u.role]||''}">${roleLabel[u.role]||u.role}</span></td>
            <td>${u.phone || '—'} ${u.role === 'staff' && u.job_class && u.job_class !== 'any' ? `<br><small class="text-muted">${jobClassLabel[u.job_class] || u.job_class}</small>` : ''}</td>
            <td><span class="badge ${u.is_active ? 'badge-success' : 'badge-cancelled'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-xs text-muted">${Format.dateShort(u.created_at)}</td>
            <td class="td-actions">
                <button class="btn btn-outline-primary btn-sm" title="Edit User Details" onclick="openEditUser(${u.id},'${u.name.replace(/'/g,"\\'")}','${u.email}','${u.role}','${u.phone||''}',${u.is_active},'${u.job_class||'any'}')">
                    <i class="fas fa-edit"></i>
                </button>
                ${u.is_active ? `<button class="btn btn-danger btn-sm" title="Deactivate User" onclick="deactivateUser(${u.id})"><i class="fas fa-user-slash"></i></button>` : ''}
            </td>
        </tr>`).join('');
}

function applyFilters() {
    loadUsers(1);
}


function openAddUser() {
    document.getElementById('userModalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('pwReq').style.display = '';
    document.getElementById('pwHint').style.display = 'none';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('u_pw').required = true;
    onRoleChange();
    Modal.open('userModal');
}

function onRoleChange() {
    const role = document.getElementById('u_role').value;
    const jcGroup = document.getElementById('jobClassGroup');
    if (jcGroup) jcGroup.style.display = (role === 'staff') ? '' : 'none';
}

function openEditUser(id, name, email, role, phone, active, jobClass = 'any') {
    document.getElementById('userModalTitle').textContent = 'Edit User';
    document.getElementById('user_id').value  = id;
    document.getElementById('u_name').value   = name;
    document.getElementById('u_email').value  = email;
    document.getElementById('u_role').value   = role;
    document.getElementById('u_phone').value  = phone;
    document.getElementById('u_active').value = active;
    if (document.getElementById('u_job_class')) {
        document.getElementById('u_job_class').value = jobClass || 'any';
    }
    document.getElementById('pwReq').style.display    = 'none';
    document.getElementById('pwHint').style.display   = '';
    document.getElementById('statusGroup').style.display = '';
    document.getElementById('u_pw').required = false;
    document.getElementById('u_pw').value = '';
    onRoleChange();
    Modal.open('userModal');
}

function changeUserPage(newPage) {
    if (newPage < 1 || newPage > userTotalPages) return;
    loadUsers(newPage);
}

function renderUserPagination() {
    document.getElementById('usersPageInfo').textContent = `Page ${currentUserPage} of ${userTotalPages}`;
    document.getElementById('usersPrevBtn').disabled = currentUserPage <= 1;
    document.getElementById('usersNextBtn').disabled = currentUserPage >= userTotalPages;
}

async function saveUser() {
    const btn  = document.getElementById('userSaveBtn');
    const form = document.getElementById('userForm');
    
    const id = document.getElementById('user_id').value;
    const name = document.getElementById('u_name').value.trim();
    const email = document.getElementById('u_email').value.trim();
    const phone = document.getElementById('u_phone').value.trim();
    const password = document.getElementById('u_pw').value;
    const role = document.getElementById('u_role').value;

    // Frontend Validation
    if (!name || !email || (!id && !password)) {
        Toast.error('Please fill in all required fields.');
        return;
    }
    if (name.length > 100) return Toast.error('Name too long (max 100).');
    if (!/^[a-zA-Z\s\-.]+$/.test(name)) return Toast.error('Name contains invalid characters.');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return Toast.error('Invalid email address.');
    if (phone && !/^\d{11}$/.test(phone.replace(/\D/g, ''))) return Toast.error('Phone must be exactly 11 digits.');
    if (password && password.length < 8) return Toast.error('Password must be at least 8 characters.');

    if (!form.checkValidity()) { form.reportValidity(); return; }
    Form.setLoading(btn, true);
    const data = Form.serialize(form);
    // Strip non-digits from phone
    if (data.phone) {
        data.phone = data.phone.replace(/\D/g, '');
    }
    try {
        if (data.id) { await Api.put(BASE + 'src/api/staff.php', data); Toast.success('User updated.'); }
        else         { await Api.post(BASE + 'src/api/staff.php', data); Toast.success('User created. They can now log in.'); }
        Modal.close('userModal');
        await loadUsers();
    } catch (e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

async function deactivateUser(id) {
    if (!await confirmDialog('Deactivate this user? They will no longer be able to log in.')) return;
    try {
        await Api.delete(BASE + 'src/api/staff.php', { id });
        Toast.success('User deactivated.');
        await loadUsers(currentUserPage);
    } catch (e) { Toast.error(e.message); }
}

document.getElementById('userSearch').addEventListener('input', debounce(() => loadUsers(1), 400));

// Initialize
loadSettings().then(() => {
    loadUsers();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
