<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Package Pricing';
$pageSubtitle = 'Manage pricing tiers and inclusives for event packages';
$activePage   = 'packages';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="card mb-4">
    <div class="card-header">
        <div>
            <div class="card-title">Packages Matrix</div>
            <div class="card-subtitle">Define base pricing per tier (e.g., Standard, Premium, Luxury)</div>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add Package Tier
        </button>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="packagesTable">
            <thead>
                <tr>
                    <th>Package Name</th>
                    <th>Base Pax</th>
                    <th>Total Price</th>
                    <th>Price/Pax</th>
                    <th>Inclusions</th>
                    <th>Limits (Mains / Desserts)</th>
                    <th>Status</th>
                    <th class="td-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="packagesBody">
                <tr><td colspan="6"><div class="spinner"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: ADD/EDIT PACKAGE -->
<div class="modal fade" id="packageModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Package Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="packageForm">
                    <input type="hidden" name="type" value="package">
                    <input type="hidden" name="id" id="pkg_id">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label">Package Name <span class="required">*</span></label>
                            <input type="text" class="form-control" name="set_name" id="pkg_name" required placeholder="e.g. Premium">
                        </div>
                        <div class="col-5">
                            <label class="form-label">Base Pax Count <span class="required">*</span></label>
                            <input type="number" class="form-control" name="pax_count" id="pkg_pax" required placeholder="e.g. 50">
                        </div>
                    </div>

                    <div class="form-group mb-3">
                        <label class="form-label">Package Price (₱) <span class="required">*</span></label>
                        <input type="number" step="0.01" class="form-control" name="price" id="pkg_price" required placeholder="0.00" oninput="updatePerPax()">
                        <div id="pkg_rate_hint" style="font-size:11px; color:rgba(60,60,67,0.45); margin-top:4px;">Rate: ₱0.00 / pax</div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Max Main Dishes <span class="required">*</span></label>
                            <input type="number" class="form-control" name="max_main_dishes" id="pkg_main" required value="5">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Max Desserts <span class="required">*</span></label>
                            <input type="number" class="form-control" name="max_desserts" id="pkg_dessert" required value="1">
                        </div>
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="pkg_includes_rice" name="includes_rice" value="1" checked>
                        <label class="form-check-label" for="pkg_includes_rice">Includes Rice</label>
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="pkg_is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="pkg_is_active">Active Status (Visible to clients)</label>
                    </div>

                    <div class="form-group mt-3">
                        <label class="form-label">Package Inclusions (One per line)</label>
                        <textarea class="form-control" name="inclusions" id="pkg_inclusions" rows="4" placeholder="e.g. Free delivery within Dasma&#10;Unlimited rice&#10;Uniformed servers..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="savePackage()" id="saveBtn">
                    <i class="fas fa-save"></i> Save Configuration
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let allPackages = [];

async function loadPackages() {
    try {
        const d = await Api.get(BASE + '/src/api/packages.php', { include_inactive: 1 });
        allPackages = d.packages || [];
        renderTable(allPackages);
    } catch(e) {
        document.getElementById('packagesBody').innerHTML = '<tr><td colspan="6" class="text-center text-muted p-4">Failed to load packages.</td></tr>';
    }
}

function renderTable(items) {
    const tbody = document.getElementById('packagesBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="table-empty"><i class="fas fa-box"></i><p>No packages configured.</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = items.map(i => {
        const perPax = i.pax_count > 0 ? (i.price / i.pax_count) : 0;
        return `
        <tr>
            <td class="fw-600">${i.set_name}</td>
            <td class="td-mono">${i.pax_count} Pax</td>
            <td class="fw-600 text-success">${Format.peso(i.price)}</td>
            <td class="td-mono text-muted">₱${perPax.toLocaleString('en-PH', {minimumFractionDigits:2})}/pax</td>
            <td style="font-size:11px; color:rgba(60,60,67,0.6); max-width:200px;">
                ${(i.inclusions || '—').split('\n').join(', ')}
            </td>
            <td class="td-mono text-muted">
                ${i.max_main_dishes} Mains, ${i.max_desserts} Desserts
                ${i.includes_rice == 1 ? '<span class="badge badge-success ms-1">Rice</span>' : ''}
            </td>
            <td>
                <span class="badge badge-${i.is_active == 1 ? 'success' : 'secondary'}">
                    ${i.is_active == 1 ? 'Active' : 'Hidden'}
                </span>
            </td>
            <td class="td-actions">
                <button class="btn btn-outline-primary btn-sm" onclick="openEditModal(${i.id})" title="Edit">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-outline-${i.is_active == 1 ? 'danger' : 'success'} btn-sm" onclick="toggleStatus(${i.id})" title="${i.is_active == 1 ? 'Deactivate' : 'Activate'}">
                    <i class="fas fa-${i.is_active == 1 ? 'ban' : 'check'}"></i>
                </button>
            </td>
        </tr>
    `; }).join('');
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Package Tier';
    document.getElementById('packageForm').reset();
    document.getElementById('pkg_id').value = '';
    document.getElementById('pkg_includes_rice').checked = true;
    document.getElementById('pkg_is_active').checked = true;
    Modal.open('packageModal');
}

function openEditModal(id) {
    const item = allPackages.find(x => x.id == id);
    if (!item) return;

    document.getElementById('modalTitle').textContent = 'Edit Package Tier';
    document.getElementById('pkg_id').value = item.id;
    document.getElementById('pkg_name').value = item.set_name;
    document.getElementById('pkg_pax').value = item.pax_count;
    document.getElementById('pkg_price').value = item.price;
    document.getElementById('pkg_main').value = item.max_main_dishes;
    document.getElementById('pkg_dessert').value = item.max_desserts;
    document.getElementById('pkg_includes_rice').checked = (item.includes_rice == 1);
    document.getElementById('pkg_is_active').checked = (item.is_active == 1);
    document.getElementById('pkg_inclusions').value = item.inclusions || '';
    updatePerPax();
    Modal.open('packageModal');
}

async function savePackage() {
    const form = document.getElementById('packageForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const data = Form.serialize(form);
    data.includes_rice = document.getElementById('pkg_includes_rice').checked ? 1 : 0;
    data.is_active     = document.getElementById('pkg_is_active').checked ? 1 : 0;
    
    const btn = document.getElementById('saveBtn');
    Form.setLoading(btn, true);

    try {
        if (data.id) {
            await Api.put(BASE + '/src/api/packages.php', data);
            Toast.success('Package updated.');
        } else {
            await Api.post(BASE + '/src/api/packages.php', data);
            Toast.success('Package added successfully.');
        }
        Modal.close('packageModal');
        loadPackages();
    } catch(e) { Toast.error(e.message); }
    
    Form.setLoading(btn, false);
}

function updatePerPax() {
    const price = parseFloat(document.getElementById('pkg_price').value) || 0;
    const pax   = parseInt(document.getElementById('pkg_pax').value) || 0;
    const rate  = pax > 0 ? (price / pax) : 0;
    document.getElementById('pkg_rate_hint').textContent = `Rate: ₱${rate.toLocaleString('en-PH', {minimumFractionDigits:2, maximumFractionDigits:2})} / pax`;
}

// Attach listener to pax too
document.getElementById('pkg_pax')?.addEventListener('input', updatePerPax);

async function toggleStatus(id) {
    if(!confirm('Are you sure you want to toggle the status of this package?')) return;
    try {
        await Api.delete(BASE + '/src/api/packages.php', { id, type: 'package' });
        Toast.success('Status updated.');
        loadPackages();
    } catch(e) { Toast.error(e.message); }
}

loadPackages();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
