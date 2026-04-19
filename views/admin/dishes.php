<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Menu & Dishes';
$pageSubtitle = 'Manage catering food options, categories, and custom fees';
$activePage   = 'dishes';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="card mb-4">
    <div class="card-header">
        <div>
            <div class="card-title">Food & Menu Catalog</div>
            <div class="card-subtitle">Items that clients can pick in the booking UI</div>
        </div>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add Dish
        </button>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="dishesTable">
            <thead>
                <tr>
                    <th>Dish Name</th>
                    <th>Category</th>
                    <th>Custom Surcharge Rate</th>
                    <th>Status</th>
                    <th class="td-actions">Actions</th>
                </tr>
            </thead>
            <tbody id="dishesBody">
                <tr><td colspan="5"><div class="spinner"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: ADD/EDIT DISH -->
<div class="modal fade" id="dishModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Dish Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="dishForm">
                    <input type="hidden" name="type" value="dish">
                    <input type="hidden" name="id" id="dish_id">
                    
                    <div class="form-group mb-3">
                        <label class="form-label">Dish Name <span class="required">*</span></label>
                        <input type="text" class="form-control" name="name" id="dish_name" required placeholder="e.g. Beef Caldereta">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label">Category Classification <span class="required">*</span></label>
                            <select class="form-control" name="category" id="dish_category" required>
                                <option value="Beef">Beef</option>
                                <option value="Pork">Pork</option>
                                <option value="Chicken">Chicken</option>
                                <option value="Seafood">Seafood</option>
                                <option value="Vegetables">Vegetables</option>
                                <option value="Pasta">Pasta</option>
                                <option value="Dessert">Dessert</option>
                                <option value="Rice">Rice</option>
                                <option value="Additional">Custom / Additional Item</option>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label">Custom Surcharge (₱) <span class="required">*</span></label>
                            <input type="number" step="0.01" class="form-control" name="custom_fee" id="dish_fee" required value="0.00">
                        </div>
                    </div>
                    
                    <div class="alert alert-info py-2 px-3 mb-0" style="font-size:12.5px; border-radius:6px; box-shadow:none;">
                        <strong>Surcharge Note:</strong> If this dish requires additional charge (e.g. Lechon, Soups needing bowls), set the fee here. It will automatically add to the booking total if selected.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveDish()" id="saveBtn">
                    <i class="fas fa-save"></i> Save Dish
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let allDishes = [];

async function loadDishes() {
    try {
        const d = await Api.get(BASE + '/src/api/packages.php', { dishes: 1 });
        
        // Flatten the dynamically grouped dishes array into a single array for the table
        allDishes = [];
        if (d.dishes_grouped) {
            Object.values(d.dishes_grouped).forEach(catArray => {
                allDishes = allDishes.concat(catArray);
            });
        }
        renderTable(allDishes);
    } catch(e) {
        document.getElementById('dishesBody').innerHTML = '<tr><td colspan="5" class="text-center text-muted p-4">Failed to load dishes.</td></tr>';
    }
}

function renderTable(items) {
    const tbody = document.getElementById('dishesBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="table-empty"><i class="fas fa-utensils"></i><p>No dishes configured.</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = items.map(i => `
        <tr>
            <td class="fw-600">${i.name}</td>
            <td>
                <span class="badge badge-light text-dark fw-500 border border-secondary" style="font-size:11px;">${i.category}</span>
            </td>
            <td class="td-mono">${parseFloat(i.custom_fee || 0) > 0 ? '+ ' + Format.peso(i.custom_fee) : '<span class="text-muted">₱0.00</span>'}</td>
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
    document.getElementById('modalTitle').textContent = 'Add Dish';
    document.getElementById('dishForm').reset();
    document.getElementById('dish_id').value = '';
    Modal.open('dishModal');
}

function openEditModal(id) {
    const item = allDishes.find(x => x.id == id);
    if (!item) return;

    document.getElementById('modalTitle').textContent = 'Edit Dish';
    document.getElementById('dish_id').value = item.id;
    document.getElementById('dish_name').value = item.name;
    document.getElementById('dish_category').value = item.category;
    document.getElementById('dish_fee').value = item.custom_fee || 0;
    Modal.open('dishModal');
}

async function saveDish() {
    const form = document.getElementById('dishForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const data = Form.serialize(form);
    const btn = document.getElementById('saveBtn');
    Form.setLoading(btn, true);

    try {
        if (data.id) {
            await Api.put(BASE + '/src/api/packages.php', data);
            Toast.success('Dish updated.');
        } else {
            await Api.post(BASE + '/src/api/packages.php', data);
            Toast.success('Dish added successfully.');
        }
        Modal.close('dishModal');
        loadDishes();
    } catch(e) { Toast.error(e.message); }
    
    Form.setLoading(btn, false);
}

async function toggleStatus(id) {
    try {
        await Api.delete(BASE + '/src/api/packages.php', { id });
        Toast.success('Status updated.');
        loadDishes();
    } catch(e) { Toast.error(e.message); }
}

loadDishes();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
