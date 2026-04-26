<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Equipment Inventory';
$pageSubtitle = 'Manage catalog of items for breakage tracking';
$activePage   = 'inventory';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="card mb-4">
    <div class="card-header">
        <div>
            <div class="card-title">Equipment Catalog</div>
            <div class="card-subtitle">Items that can be charged for breakage or loss</div>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add Item
        </button>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="inventoryTable">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Unit</th>
                    <th>Replacement Cost</th>
                    <th>Status</th>
                    <th class="td-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryBody">
                <tr><td colspan="5"><div class="spinner"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: ADD/EDIT EQUIPMENT -->
<div class="modal fade" id="equipmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add Equipment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="equipmentForm">
                    <input type="hidden" name="id" id="equip_id">
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Item Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="name" id="equip_name" required placeholder="e.g. Dinner Plate (Ceramic)" maxlength="100">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label">Unit <span class="required">*</span></label>
                            <input type="text" class="form-control" name="unit" id="equip_unit" required placeholder="pcs, set, etc." maxlength="20">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Replacement Price (₱) <span class="required">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control" name="replacement_cost" id="equip_cost" required placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="equip_is_active" name="is_active" value="1" checked>
                        <label class="form-check-label" for="equip_is_active">Available for logging</label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveEquipment()" id="saveBtn">
                    <i class="fas fa-save"></i> Save Equipment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let allEquipment = [];

async function loadInventory() {
    try {
        const d = await Api.get(BASE + '/src/api/inventory.php', { all: 1 });
        allEquipment = d.equipment || [];
        renderTable(allEquipment);
    } catch(e) {
        document.getElementById('inventoryBody').innerHTML = '<tr><td colspan="5" class="text-center text-muted p-4">Failed to load catalog.</td></tr>';
    }
}

function renderTable(items) {
    const tbody = document.getElementById('inventoryBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="table-empty"><i class="fas fa-boxes-stacked"></i><p>No equipment found.</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = items.map(i => `
        <tr>
            <td class="td-name">${i.name}</td>
            <td class="td-mono">${i.unit}</td>
            <td class="fw-600">${Format.peso(i.replacement_cost)}</td>
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
    `).join('');
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Equipment';
    document.getElementById('equipmentForm').reset();
    document.getElementById('equip_id').value = '';
    document.getElementById('equip_is_active').checked = true;
    Modal.open('equipmentModal');
}

function openEditModal(id) {
    const item = allEquipment.find(x => x.id == id);
    if (!item) return;

    document.getElementById('modalTitle').textContent = 'Edit Equipment';
    document.getElementById('equip_id').value = item.id;
    document.getElementById('equip_name').value = item.name;
    document.getElementById('equip_unit').value = item.unit;
    document.getElementById('equip_cost').value = item.replacement_cost;
    document.getElementById('equip_is_active').checked = (item.is_active == 1);
    Modal.open('equipmentModal');
}

async function saveEquipment() {
    const form = document.getElementById('equipmentForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const data = Form.serialize(form);
    // Handle checkbox not serializing if unchecked
    data.is_active = document.getElementById('equip_is_active').checked ? 1 : 0;
    
    const btn = document.getElementById('saveBtn');
    Form.setLoading(btn, true);

    try {
        if (data.id) {
            await Api.put(BASE + '/src/api/inventory.php', data);
            Toast.success('Equipment updated.');
        } else {
            await Api.post(BASE + '/src/api/inventory.php', data);
            Toast.success('Equipment added to catalog.');
        }
        Modal.close('equipmentModal');
        loadInventory();
    } catch(e) { Toast.error(e.message); }
    
    Form.setLoading(btn, false);
}

async function toggleStatus(id) {
    try {
        await Api.delete(BASE + '/src/api/inventory.php', { id });
        Toast.success('Status updated.');
        loadInventory();
    } catch(e) { Toast.error(e.message); }
}

loadInventory();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
