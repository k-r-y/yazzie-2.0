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
        <div class="d-flex justify-content-between align-items-center">
            <div class="search-input-wrap" style="max-width:320px;flex:1;">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="userSearch" placeholder="Search users…">
            </div>
            <button class="btn btn-primary" onclick="openAddUser()">
                <i class="fas fa-user-plus"></i> Add User
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">All User Accounts</div></div>
    <div class="table-wrapper">
        <table class="data-table" id="userTable">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Phone</th><th>Status</th><th>Created</th><th class="td-actions">Actions</th></tr>
            </thead>
            <tbody id="userBody"><tr><td colspan="7"><div class="spinner"></div></td></tr></tbody>
        </table>
    </div>
</div>

<!-- USER MODAL -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="userForm">
                    <input type="hidden" name="id" id="user_id">
                    <div class="form-group"><label class="form-label">Full Name <span class="required">*</span></label>
                        <input class="form-control" name="name" id="u_name" required></div>
                    <div class="form-group"><label class="form-label">Email Address <span class="required">*</span></label>
                        <input class="form-control" name="email" id="u_email" type="email" required></div>
                    <div class="form-group"><label class="form-label">Role <span class="required">*</span></label>
                        <select class="form-control" name="role" id="u_role" required>
                            <option value="admin">Administrator</option>
                            <option value="frontdesk">Front Desk</option>
                            <option value="staff" selected>On-Call Staff</option>
                        </select></div>
                    <div class="form-group"><label class="form-label">Phone (for SMS alerts)</label>
                        <input class="form-control" name="phone" id="u_phone" placeholder="09XXXXXXXXX"></div>
                    <div class="form-group">
                        <label class="form-label">Password <span class="required" id="pwReq">*</span></label>
                        <input class="form-control" name="password" id="u_pw" type="password" placeholder="Min 8 characters">
                        <div class="form-hint" id="pwHint" style="display:none;">Leave blank to keep current password.</div>
                    </div>
                    <div class="form-group" id="statusGroup" style="display:none;">
                        <label class="form-label">Account Status</label>
                        <select class="form-control" name="is_active" id="u_active">
                            <option value="1">Active</option>
                            <option value="0">Deactivated</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="userSaveBtn" onclick="saveUser()"><i class="fas fa-save"></i> Save User</button>
            </div>
        </div>
    </div>
</div>

<script>
const BASE = '<?= BASE_URL ?>';

async function loadUsers() {
    const d = await Api.get(BASE + '/src/api/staff.php');
    const users = d.users || [];
    const tbody = document.getElementById('userBody');
    const roleLabel = { admin: 'Administrator', frontdesk: 'Front Desk', staff: 'On-Call Staff' };
    const roleBadge = { admin: 'badge-admin', frontdesk: 'badge-frontdesk', staff: 'badge-staff' };

    if (!users.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="table-empty"><i class="fas fa-users"></i><p>No users found.</p></div></td></tr>`;
        return;
    }

    tbody.innerHTML = users.map(u => `
        <tr>
            <td class="td-name">${u.name}</td>
            <td>${u.email}</td>
            <td><span class="badge ${roleBadge[u.role]||''}">${roleLabel[u.role]||u.role}</span></td>
            <td>${u.phone || '—'}</td>
            <td><span class="badge ${u.is_active ? 'badge-success' : 'badge-cancelled'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
            <td class="text-xs text-muted">${Format.dateShort(u.created_at)}</td>
            <td class="td-actions">
                <button class="btn btn-outline-primary btn-sm" onclick="openEditUser(${u.id},'${u.name.replace(/'/g,"\\'")}','${u.email}','${u.role}','${u.phone||''}',${u.is_active})">
                    <i class="fas fa-edit"></i>
                </button>
                ${u.is_active ? `<button class="btn btn-danger btn-sm" onclick="deactivateUser(${u.id})"><i class="fas fa-user-slash"></i></button>` : ''}
            </td>
        </tr>`).join('');
    initTableSearch('userSearch', 'userTable');
}

function openAddUser() {
    document.getElementById('userModalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('pwReq').style.display = '';
    document.getElementById('pwHint').style.display = 'none';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('u_pw').required = true;
    Modal.open('userModal');
}

function openEditUser(id, name, email, role, phone, active) {
    document.getElementById('userModalTitle').textContent = 'Edit User';
    document.getElementById('user_id').value  = id;
    document.getElementById('u_name').value   = name;
    document.getElementById('u_email').value  = email;
    document.getElementById('u_role').value   = role;
    document.getElementById('u_phone').value  = phone;
    document.getElementById('u_active').value = active;
    document.getElementById('pwReq').style.display    = 'none';
    document.getElementById('pwHint').style.display   = '';
    document.getElementById('statusGroup').style.display = '';
    document.getElementById('u_pw').required = false;
    document.getElementById('u_pw').value = '';
    Modal.open('userModal');
}

async function saveUser() {
    const btn  = document.getElementById('userSaveBtn');
    const form = document.getElementById('userForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    Form.setLoading(btn, true);
    const data = Form.serialize(form);
    try {
        if (data.id) { await Api.put(BASE + '/src/api/staff.php', data); Toast.success('User updated.'); }
        else         { await Api.post(BASE + '/src/api/staff.php', data); Toast.success('User created. They can now log in.'); }
        Modal.close('userModal');
        await loadUsers();
    } catch (e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

async function deactivateUser(id) {
    if (!await confirmDialog('Deactivate this user? They will no longer be able to log in.')) return;
    try {
        await Api.delete(BASE + '/src/api/staff.php', { id });
        Toast.success('User deactivated.');
        await loadUsers();
    } catch (e) { Toast.error(e.message); }
}

loadUsers();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
