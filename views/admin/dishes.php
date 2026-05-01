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
        <button class="btn btn-primary py-3" onclick="openAddModal()" title="Add a new dish to the catalog">
            <i class="fas fa-plus"></i> Add Dish
        </button>
    </div>

    <!-- Filter Bar -->
    <div class="card-body dishes-filter-bar">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-prefix"><i class="fas fa-search"></i></span>
                    <input type="text" class="form-control" id="searchFilter" placeholder="Search dish name..." oninput="filterDishes()" title="Type to search dishes by name">
                </div>
            </div>
            <div class="col-md-3">
                <select class="form-control" id="catFilter" onchange="filterDishes()" title="Filter dishes by category">
                    <option value="all">All Categories</option>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-control" id="mealFilter" onchange="filterDishes()" title="Filter dishes by meal type">
                    <option value="all">All Meal Types</option>
                    <option value="breakfast">🍳 Breakfast</option>
                    <option value="lunch">🍱 Lunch</option>
                    <option value="dinner">🍷 Dinner</option>
                </select>
            </div>
            <div class="col-md-2 text-end">
                <div id="filterCount" class="filter-summary"></div>
            </div>
        </div>
    </div>

    <div class="table-wrapper table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="data-table" id="dishesTable">
            <thead>
                <tr>
                    <th>Dish Name</th>
                    <th>Category</th>
                    <th>Meal Type</th>
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
    <div class="table-pagination dishes-pagination" id="dishesPagination">
        <button type="button" class="pagination-button dishes-pagination-button" id="dishesPrevBtn" onclick="changeDishPage(currentDishPage - 1)" disabled>
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <div class="pagination-info" id="dishesPageInfo">Page 1 of 1</div>
        <button type="button" class="pagination-button dishes-pagination-button" id="dishesNextBtn" onclick="changeDishPage(currentDishPage + 1)" disabled>
            Next <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<!-- MODAL: ADD/EDIT DISH -->
<div class="modal fade" id="dishModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="position:relative;">
                <h5 class="modal-title" id="modalTitle" style="padding-right:40px;">Dish Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" style="position:absolute; right:20px; top:20px;"></button>
            </div>
            <div class="modal-body">
                <form id="dishForm">
                    <input type="hidden" name="type" value="dish">
                    <input type="hidden" name="id" id="dish_id">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-8">
                            <label class="form-label" for="dish_name">Dish Name <span class="required">*</span></label>
                            <input type="text" class="form-control" name="name" id="dish_name" required placeholder="e.g. Beef Caldereta" title="Enter the name of the dish">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Meal Type <span class="required">*</span></label>
                            <div class="d-flex gap-3 p-2 bg-light border rounded-3" style="border-style: dashed !important;">
                                <label class="d-flex align-items-center gap-2 mb-0" style="font-size:13px; cursor:pointer; font-weight:600;">
                                    <input type="checkbox" name="meal_type" value="breakfast" style="width:16px; height:16px;"> Breakfast
                                </label>
                                <label class="d-flex align-items-center gap-2 mb-0" style="font-size:13px; cursor:pointer; font-weight:600;">
                                    <input type="checkbox" name="meal_type" value="lunch" style="width:16px; height:16px;"> Lunch
                                </label>
                                <label class="d-flex align-items-center gap-2 mb-0" style="font-size:13px; cursor:pointer; font-weight:600;">
                                    <input type="checkbox" name="meal_type" value="dinner" style="width:16px; height:16px;"> Dinner
                                </label>
                            </div>
                            <div class="form-hint" style="font-size:11px;">A dish can belong to multiple meal types. If none selected, it defaults to 'all'.</div>
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-7">
                            <label class="form-label" for="dish_category">Category Classification <span class="required">*</span></label>
                            <select class="form-control" name="category" id="dish_category" required title="Select the food category">
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
                            <label class="form-label" for="dish_fee">Custom Surcharge (₱) <span class="required">*</span></label>
                            <input type="text" class="form-control" name="custom_fee" id="dish_fee" required value="0.00" data-restrict="price" title="Additional charge for this item (e.g. 500.00)">
                        </div>
                    </div>
                    
                    <div class="alert alert-info py-2 px-3 mb-0" style="font-size:12.5px; border-radius:6px; box-shadow:none;">
                        <strong>Surcharge Note:</strong> If this dish requires additional charge (e.g. Lechon, Soups needing bowls), set the fee here. It will automatically add to the booking total if selected.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="saveDish()" id="saveBtn" title="Save dish details to catalog">
                    <i class="fas fa-save"></i> Save Dish
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let allDishes = [];
let currentDishPage = 1;
let dishTotalPages = 1;

async function loadDishes(page = null) {
    if (page !== null) {
        currentDishPage = Math.max(1, page);
    }

    const search = document.getElementById('searchFilter').value.trim();
    const category = document.getElementById('catFilter').value;
    const meal = document.getElementById('mealFilter').value;

    try {
        const params = {
            dishes: 1,
            include_inactive: 1,
            page: currentDishPage,
            limit: 10,
        };

        if (search) {
            params.search = search;
        }
        if (category && category !== 'all') {
            params.category = category;
        }
        if (meal && meal !== 'all') {
            params.meal_type = meal;
        }

        const d = await Api.get(BASE + 'src/api/packages.php', params);
        allDishes = d.dishes || [];
        if (!allDishes.length && d.dishes_grouped) {
            Object.values(d.dishes_grouped).forEach(group => {
                allDishes = allDishes.concat(group);
            });
        }

        const categorySelect = document.getElementById('catFilter');
        const currentCat = categorySelect.value;
        const categories = new Set(d.categories || []);
        if (!categories.size && allDishes.length) {
            allDishes.forEach(item => categories.add(item.category));
        }
        categorySelect.innerHTML = '<option value="all">All Categories</option>';
        Array.from(categories).sort().forEach(cat => {
            categorySelect.innerHTML += `<option value="${cat}" ${cat === currentCat ? 'selected' : ''}>${cat}</option>`;
        });

        dishTotalPages = d.meta?.totalPages || 1;
        const totalRecords = d.meta?.totalRecords ?? allDishes.length;
        document.getElementById('filterCount').textContent = `${totalRecords} item${totalRecords === 1 ? '' : 's'} found`;
        renderDishPagination();
        renderTable(allDishes);
    } catch(e) {
        document.getElementById('dishesBody').innerHTML = '<tr><td colspan="5" class="text-center text-muted p-4">Failed to load dishes.</td></tr>';
    }
}

function filterDishes() {
    loadDishes(1);
}

function renderDishPagination() {
    const prevBtn = document.getElementById('dishesPrevBtn');
    const nextBtn = document.getElementById('dishesNextBtn');
    const pageInfo = document.getElementById('dishesPageInfo');

    pageInfo.textContent = `Page ${currentDishPage} of ${dishTotalPages}`;
    prevBtn.disabled = currentDishPage <= 1;
    nextBtn.disabled = currentDishPage >= dishTotalPages;
}

function changeDishPage(page) {
    if (page < 1 || page > dishTotalPages) return;
    currentDishPage = page;
    loadDishes();
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
            <td>
                <span class="badge badge-info" style="font-size:10px; text-transform:uppercase;">${(i.meal_type && i.meal_type.trim()) ? i.meal_type.replace(/,/g, ', ') : 'all'}</span>
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
    const form = document.getElementById('dishForm');
    const types = (item.meal_type || 'all').split(',');
    form.querySelectorAll('input[name="meal_type"]').forEach(cb => {
        cb.checked = types.includes(cb.value) || types.includes('all');
    });
    document.getElementById('dish_fee').value = item.custom_fee || 0;
    Modal.open('dishModal');
}

async function saveDish() {
    const form = document.getElementById('dishForm');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const data = Form.serialize(form);
    const selectedTypes = Array.from(form.querySelectorAll('input[name="meal_type"]:checked')).map(cb => cb.value);
    data.meal_type = selectedTypes.length > 0 ? selectedTypes.join(',') : 'all';
    const btn = document.getElementById('saveBtn');
    Form.setLoading(btn, true);

    try {
        if (data.id) {
            await Api.put(BASE + 'src/api/packages.php', data);
            Toast.success('Dish updated.');
        } else {
            await Api.post(BASE + 'src/api/packages.php', data);
            Toast.success('Dish added successfully.');
        }
        Modal.close('dishModal');
        loadDishes();
    } catch(e) { Toast.error(e.message); }
    
    Form.setLoading(btn, false);
}

async function toggleStatus(id) {
    try {
        await Api.delete(BASE + 'src/api/packages.php', { id });
        Toast.success('Status updated.');
        loadDishes();
    } catch(e) { Toast.error(e.message); }
}

loadDishes();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
