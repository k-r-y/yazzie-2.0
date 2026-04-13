<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Menu Manager';
$pageSubtitle = 'Manage catering packages and recipe ingredients';
$activePage   = 'menus';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header">
                <div class="card-title">All Menu Packages</div>
                <button class="btn btn-primary btn-sm" onclick="openAddMenu()">
                    <i class="fas fa-plus"></i> Add Menu
                </button>
            </div>
            <div id="menuList"><div class="spinner"></div></div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card" id="ingredientCard">
            <div class="card-header">
                <div>
                    <div class="card-title" id="ingTitle">Ingredients</div>
                    <div class="card-subtitle">Recipe quantities per 1 guest (pax)</div>
                </div>
                <button class="btn btn-outline-primary btn-sm" id="addIngBtn" style="display:none;" onclick="openAddIngredient()">
                    <i class="fas fa-plus"></i> Add Ingredient
                </button>
            </div>
            <div id="ingredientContent">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-utensils"></i></div>
                    <h3>Select a Menu</h3>
                    <p>Click a menu package on the left to manage its ingredient recipe.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ADD MENU MODAL -->
<div class="modal fade" id="menuModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="menuModalTitle">Add Menu Package</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="menuForm">
                    <input type="hidden" name="id" id="menu_id">
                    <div class="form-group"><label class="form-label">Package Name <span class="required">*</span></label>
                        <input class="form-control" name="name" id="menu_name" required placeholder="Package A — Fiesta Buffet"></div>
                    <div class="form-group"><label class="form-label">Price per Pax (₱) <span class="required">*</span></label>
                        <div class="input-group"><span class="input-prefix">₱</span>
                        <input class="form-control" name="price_per_pax" id="menu_price" type="number" min="1" step="0.01" required></div></div>
                    <div class="form-group"><label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="menu_desc" rows="2"></textarea></div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="menuSaveBtn" onclick="saveMenu()"><i class="fas fa-save"></i> Save Menu</button>
            </div>
        </div>
    </div>
</div>

<!-- ADD INGREDIENT MODAL -->
<div class="modal fade" id="ingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Ingredient</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="ingForm">
                    <input type="hidden" name="menu_id" id="ing_menu_id">
                    <div class="form-group"><label class="form-label">Ingredient Name <span class="required">*</span></label>
                        <input class="form-control" name="item_name" required placeholder="e.g. Pork Belly"></div>
                    <div class="form-grid-2">
                        <div class="form-group"><label class="form-label">Qty per Pax <span class="required">*</span></label>
                            <input class="form-control" name="quantity_per_pax" type="number" step="0.0001" min="0.0001" required placeholder="0.3000"></div>
                        <div class="form-group"><label class="form-label">Unit <span class="required">*</span></label>
                            <select class="form-control" name="unit">
                                <option>kg</option><option>g</option><option>pcs</option>
                                <option>liters</option><option>ml</option><option>cups</option>
                                <option>tbsp</option><option>tsp</option><option>pack</option>
                            </select></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="ingSaveBtn" onclick="saveIngredient()"><i class="fas fa-save"></i> Add</button>
            </div>
        </div>
    </div>
</div>

<script>
let activeMenuId = null;

async function loadMenus() {
    const d = await Api.get(BASE + '/src/api/menus.php');
    const menus = d.menus || [];
    document.getElementById('menuList').innerHTML = menus.length
        ? menus.map(m => `
            <div onclick="selectMenu(${m.id}, '${m.name.replace(/'/g,"\\'")}', this)"
                 style="padding:14px 20px;border-bottom:1px solid var(--border);cursor:pointer;display:flex;justify-content:space-between;align-items:center;"
                 class="menu-item ${!m.is_active ? 'opacity-50' : ''}"
                 data-menu-id="${m.id}">
                <div>
                    <div class="fw-700" style="font-size:14px;">${m.name}</div>
                    <div class="text-sm text-muted">₱${parseFloat(m.price_per_pax).toLocaleString()} per pax</div>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    ${m.is_active ? '<span class="badge badge-success">Active</span>' : '<span class="badge">Inactive</span>'}
                    <button class="btn btn-outline-secondary btn-sm" onclick="event.stopPropagation();editMenu(${m.id},'${m.name.replace(/'/g,"\\'")}',${m.price_per_pax},'${(m.description||'').replace(/'/g,"\\'")}')">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>`).join('')
        : '<div class="empty-state" style="padding:40px;"><div class="empty-state-icon"><i class="fas fa-utensils"></i></div><h3>No menus yet</h3></div>';
}

async function selectMenu(id, name, el) {
    document.querySelectorAll('.menu-item').forEach(e => e.style.background = '');
    el.style.background = 'var(--primary-light)';
    activeMenuId = id;
    document.getElementById('ingTitle').textContent = 'Ingredients — ' + name;
    document.getElementById('addIngBtn').style.display = '';
    document.getElementById('ing_menu_id').value = id;

    document.getElementById('ingredientContent').innerHTML = '<div class="spinner"></div>';
    const d = await Api.get(BASE + '/src/api/ingredients.php', { menu_id: id });
    const ings = d.ingredients || [];
    if (!ings.length) {
        document.getElementById('ingredientContent').innerHTML = `
            <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-leaf"></i></div>
            <h3>No Ingredients</h3><p>Add ingredients to define this menu's recipe.</p></div>`;
        return;
    }
    document.getElementById('ingredientContent').innerHTML = `
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead><tr><th>Ingredient</th><th class="text-right">Qty/Pax</th><th>Unit</th><th>Delete</th></tr></thead>
                <tbody>
                    ${ings.map(i => `<tr>
                        <td class="td-name">${i.item_name}</td>
                        <td class="text-right text-bold">${i.quantity_per_pax}</td>
                        <td>${i.unit}</td>
                        <td><button class="btn btn-danger btn-sm" onclick="deleteIng(${i.id})"><i class="fas fa-trash"></i></button></td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

function openAddMenu() {
    document.getElementById('menuModalTitle').textContent = 'Add Menu Package';
    document.getElementById('menuForm').reset();
    document.getElementById('menu_id').value = '';
    Modal.open('menuModal');
}

function editMenu(id, name, price, desc) {
    document.getElementById('menuModalTitle').textContent = 'Edit Menu Package';
    document.getElementById('menu_id').value   = id;
    document.getElementById('menu_name').value  = name;
    document.getElementById('menu_price').value = price;
    document.getElementById('menu_desc').value  = desc;
    Modal.open('menuModal');
}

async function saveMenu() {
    const btn  = document.getElementById('menuSaveBtn');
    const form = document.getElementById('menuForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    Form.setLoading(btn, true);
    const data = Form.serialize(form);
    try {
        if (data.id) { await Api.put(BASE + '/src/api/menus.php', data); Toast.success('Menu updated.'); }
        else         { await Api.post(BASE + '/src/api/menus.php', data); Toast.success('Menu added.'); }
        Modal.close('menuModal');
        await loadMenus();
    } catch (e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

function openAddIngredient() { document.getElementById('ingForm').reset(); document.getElementById('ing_menu_id').value = activeMenuId; Modal.open('ingModal'); }

async function saveIngredient() {
    const btn  = document.getElementById('ingSaveBtn');
    const form = document.getElementById('ingForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    Form.setLoading(btn, true);
    try {
        await Api.post(BASE + '/src/api/ingredients.php', Form.serialize(form));
        Toast.success('Ingredient added.');
        Modal.close('ingModal');
        const activeEl = document.querySelector(`.menu-item[data-menu-id="${activeMenuId}"]`);
        if (activeEl) await selectMenu(activeMenuId, document.getElementById('ingTitle').textContent.split(' — ')[1], activeEl);
    } catch (e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

async function deleteIng(id) {
    if (!await confirmDialog('Remove this ingredient from the recipe?')) return;
    try {
        await Api.delete(BASE + '/src/api/ingredients.php', { id });
        Toast.success('Ingredient removed.');
        const activeEl = document.querySelector('.menu-item[style*="background"]');
        if (activeEl) await selectMenu(activeMenuId, document.getElementById('ingTitle').textContent.replace('Ingredients — ', ''), activeEl);
    } catch (e) { Toast.error(e.message); }
}

loadMenus();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
