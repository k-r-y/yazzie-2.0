<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Recipes and Computation';
$pageSubtitle = 'Compute Recipes';
$activePage   = 'recipes';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="main-content">
    <div class="top-nav">
        <div class="nav-title">
            <h1 style="font-weight:700; font-size:22px;">Recipes & Costing</h1>
            <div style="font-size:13px; color:var(--text-secondary);">Manage dish recipes and compute ingredient yields</div>
        </div>
        <div class="nav-actions my-3">
            <button class="btn btn-secondary" onclick="switchTab('calculator')" id="tab-btn-calculator" style="display:none;"><i class="fas fa-calculator"></i> Yield Calculator</button>
            <button class="btn btn-primary" onclick="switchTab('builder')" id="tab-btn-builder"><i class="fas fa-book-open"></i> Recipe Builder</button>
        </div>
    </div>

    <div id="recipe-builder-tab">
        <div class="modal-responsive-grid" style="gap:24px;">
            
            <div class="card" style="padding:0; overflow:hidden; border:1px solid var(--border);">
                <div style="padding:16px; border-bottom:1px solid var(--border); background:var(--surface-2);">
                    <input type="text" class="form-control" id="searchDish" placeholder="Search dish recipes..." oninput="filterDishes()" style="font-size:13px; padding:8px 12px; height:auto;">
                </div>
                <div id="dishListWrapper" style="overflow-y:auto; height:calc(60vh - 220px);">
                </div>
            </div>

            <!-- Recipe Editor Canvas -->
            <div class="card p-3 mt-3" id="recipeEditor" style="display:none; position:relative;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid var(--border);">
                    <div>
                        <h2 id="r_dishName" style="font-size:20px; font-weight:700; margin-bottom:4px;">Dish Name</h2>
                        <span id="r_dishCategory" style="font-size:11px; background:rgba(0,0,0,0.05); padding:2px 8px; border-radius:12px; font-weight:600; text-transform:uppercase;">Category</span>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <span style="font-size:13px; font-weight:600;">Base Yield:</span>
                        <input type="number" class="form-control" id="r_basePax" style="width:80px; text-align:center;" min="1" onchange="updateBasePax()">
                        <span style="font-size:13px; font-weight:500;">pax</span>
                    </div>
                </div>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                    <h3 style="font-size:16px; font-weight:600;">Raw Ingredients</h3>
                    <button class="btn btn-primary py-3" onclick="openAddIngredientModal()" style="font-size:13px; padding:6px 14px;"><i class="fas fa-plus"></i> Add Item</button>
                </div>

                <div class="table-responsive">
                    <table class="table" style="font-size:13px;">
                        <thead style="background:var(--surface-2);">
                            <tr>
                                <th>Ingredient</th>
                                <th style="text-align:right;">Base Quantity</th>
                                <th style="text-align:left;">Unit</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="recipeItemsTbody">
                            <!-- Populated by JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div id="noIngredientsState mt-3" style="display:none; text-align:center; padding:60px 20px; color:var(--text-secondary);">
                    <i class="fas fa-lemon" style="font-size:32px; opacity:0.3; margin-bottom:12px;"></i>
                    <div style="font-weight:600; color:var(--text-primary); font-size:15px; margin-bottom:4px;">No ingredients listed</div>
                    <div style="font-size:13px;">Add ingredients to build out this recipe.</div>
                </div>
            </div>

            <!-- Editor Blank State -->
            <div class="card p-5 mt-3" id="recipeEditorBlank" style="display:flex; flex-direction:column; align-items:center; justify-content:center; color:var(--text-secondary);">
                <i class="fas fa-utensils" style="font-size:48px; opacity:0.2; margin-bottom:16px;"></i>
                <div style="font-weight:600; color:var(--text-primary); font-size:16px; margin-bottom:4px;">Select a Dish</div>
                <div style="font-size:13px;">Choose a dish from the left to view or edit its recipe formula.</div>
            </div>

        </div>
    </div>

    <!-- ══ YIELD CALCULATOR TAB ══ -->
    <div id="yield-calculator-tab" style="display:none;">
        <div style="max-width:800px; margin:0 auto mt-4;">
            
            <div class="card" style="margin-bottom:24px; padding:24px;">
                <h3 style="font-size:16px; font-weight:700; margin-bottom:16px;"><i class="fas fa-magic" style="color:#FF9500; margin-right:8px;"></i> Calculation Formula</h3>
                
                <div style="display:flex; gap:16px; align-items:flex-end;">
                    <div style="flex:2;">
                        <label class="form-label" style="font-size:12px;">Select Dish</label>
                        <select class="form-control" id="calcSelectDish">
                            <!-- populated by JS -->
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label class="form-label" style="font-size:12px;">Target Yield (Pax)</label>
                        <input type="number" class="form-control" id="calcTargetPax" placeholder="e.g. 238" min="1">
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="computeYield()" style="height:38px; padding:0 24px;"><i class="fas fa-flask"></i> Compute</button>
                    </div>
                </div>
            </div>

            <div class="card" id="calcResultCard" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:20px; padding-bottom:16px; border-bottom:1px solid var(--border);">
                    <div>
                        <h2 id="resDishName" style="font-size:22px; font-weight:800; margin-bottom:4px; color:var(--sys-green);">Adobo</h2>
                        <div style="font-size:13px; color:var(--text-secondary);">
                            Base Recipe: <strong id="resBasePax">50</strong> pax · Scaled for: <strong id="resTargetPax" style="color:var(--text-primary);">238</strong> pax
                        </div>
                    </div>
                    <div style="background:rgba(48,209,88,0.1); padding:8px 16px; border-radius:12px; border:1px solid rgba(48,209,88,0.2); text-align:center;">
                        <div style="font-size:11px; font-weight:700; color:#1A7A32; text-transform:uppercase;">Volume Multiplier</div>
                        <div id="resMultiplier" style="font-size:18px; font-weight:800; color:var(--sys-green);">x 4.76</div>
                    </div>
                </div>

                <div class="table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                    <table class="table" style="font-size:14px; width:100%;">
                        <thead>
                            <tr>
                                <th style="color:var(--text-secondary);">Ingredient Needed</th>
                                <th style="text-align:right; color:var(--text-secondary);">Base Qty</th>
                                <th style="text-align:right; font-weight:700; color:var(--text-primary);">Required Scaled Amount</th>
                            </tr>
                        </thead>
                        <tbody id="resTbody">
                            <!-- JS populated -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="calcBlank" style="text-align:center; padding:60px 20px; color:var(--text-secondary);">
                <i class="fas fa-boxes" style="font-size:40px; opacity:0.2; margin-bottom:16px;"></i>
                <div style="font-size:15px; font-weight:600; color:var(--text-primary); margin-bottom:6px;">Ready to Compute</div>
                <div style="font-size:13px;">Select a dish and target pax above to generate a scaled grocery list.</div>
            </div>

        </div>
    </div>

</div>

<!-- CREATE/EDIT INGREDIENT MODAL -->
<div class="modal fade" id="ingredientModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 16px; border: none;">
            <form onsubmit="saveIngredient(event)">
                <div class="modal-header" style="border-bottom: 1px solid rgba(0,0,0,0.05); padding: 20px 24px; position:relative;">
                    <h3 id="ingModalTitle" style="font-size: 18px; font-weight: 700; margin: 0; padding-right:40px;">Add Ingredient</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" style="position:absolute; right:20px; top:20px;"></button>
                </div>

                <div class="modal-body" style="padding: 24px;">
                    <input type="hidden" id="ing_id">
                    
                    <div class="form-group mb-3">
                        <label class="form-label" style="font-weight: 600; font-size: 13px;">Ingredient Name</label>
                        <input type="text" class="form-control" id="ing_name" required placeholder="e.g. Chicken Breast">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; font-size: 13px;">Quantity <small class="text-muted">(Base)</small></label>
                            <input type="number" step="0.0001" class="form-control" id="ing_qty" required placeholder="0.00">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" style="font-weight: 600; font-size: 13px;">Unit</label>
                            <select class="form-control" id="ing_unit" required>
                                <option value="kg">Kilograms (kg)</option>
                                <option value="g">Grams (g)</option>
                                <option value="L">Liters (L)</option>
                                <option value="pcs">Pieces (pcs)</option>
                                <option value="packs">Packs</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer" style="border-top: 1px solid rgba(0,0,0,0.05); padding: 16px 24px;">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="ingSaveBtn" style="padding: 8px 24px;">Save Ingredient</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let recipesData     = [];
let currentDishId   = null;

window.addEventListener('DOMContentLoaded', async () => {
    switchTab('builder');
    await loadRecipes();
});

// TAB SWITCHING
window.switchTab = function(tabName) {
    const builder   = document.getElementById('recipe-builder-tab');
    const calc      = document.getElementById('yield-calculator-tab');
    const btnBuilder= document.getElementById('tab-btn-builder');
    const btnCalc   = document.getElementById('tab-btn-calculator');

    if (tabName === 'builder') {
        builder.style.display = 'block';
        calc.style.display = 'none';
        btnBuilder.style.display = 'none';
        btnCalc.style.display = 'inline-flex';
    } else {
        builder.style.display = 'none';
        calc.style.display = 'block';
        btnBuilder.style.display = 'inline-flex';
        btnCalc.style.display = 'none';
        
        // Refresh calculation dropdown
        populateCalcDropdown();
    }
};

// LOAD MAIN DATA
async function loadRecipes() {
    try {
        const d = await Api.get(BASE + 'src/api/recipes.php');
        recipesData = d.recipes || [];
        renderDishList(recipesData);
        populateCalcDropdown();
    } catch(e) {
        Toast.error('Failed to load recipes.');
    }
}

// ── BUILDER SIDEBAR ────────────────────────────────
function filterDishes() {
    const q = document.getElementById('searchDish').value.toLowerCase();
    const filtered = recipesData.filter(x => x.name.toLowerCase().includes(q));
    renderDishList(filtered);
}

function renderDishList(list) {
    const wrapper = document.getElementById('dishListWrapper');
    if (list.length === 0) {
        wrapper.innerHTML = '<div style="padding:16px; color:#888; text-align:center; font-size:13px;">No dishes found.</div>';
        return;
    }

    wrapper.innerHTML = list.map(d => `
        <div class="dish-list-item ${currentDishId === d.id ? 'active' : ''}" onclick="selectDish(${d.id})" id="dListItem_${d.id}">
            <div style="font-weight:600; font-size:14px; margin-bottom:2px;">${d.name}</div>
            <div style="font-size:12px; color:rgba(60,60,67,0.5);">
                ${d.category.charAt(0).toUpperCase() + d.category.slice(1)} · ${d.ingredients.length} items
            </div>
            <div style="font-size:11px; font-weight:600; color:var(--sys-green); margin-top:6px;">Base Pax: ${d.base_pax || 50}</div>
        </div>
    `).join('');
}

// ── RECIPE EDITOR UI ────────────────────────────────
window.selectDish = async function(id) {
    currentDishId = id;
    document.querySelectorAll('.dish-list-item').forEach(el => el.classList.remove('active'));
    const activeEl = document.getElementById('dListItem_' + id);
    if(activeEl) activeEl.classList.add('active');
    const dish = recipesData.find(x => x.id == id);
    if (!dish) return;

    document.getElementById('recipeEditorBlank').style.display = 'none';
    const editor = document.getElementById('recipeEditor');
    editor.style.display = 'block';
    
    document.getElementById('r_dishName').textContent = dish.name;
    document.getElementById('r_dishCategory').textContent = dish.category;
    document.getElementById('r_basePax').value = dish.base_pax || 50;

    renderRecipeItems(dish.ingredients);
};

function renderRecipeItems(ings) {
    const tbody = document.getElementById('recipeItemsTbody');
    const empty = document.getElementById('noIngredientsState');
    // Select the wrapper specifically
    const tableWrapper = document.querySelector('.table-responsive');

    if (!ings || ings.length === 0) {
        if (empty) empty.style.setProperty('display', 'block', 'important');
        if (tableWrapper) tableWrapper.style.display = 'none';
        tbody.innerHTML = '';
        return;
    }

    // Hide empty state and show table
    if (empty) empty.style.display = 'none';
    if (tableWrapper) tableWrapper.style.display = 'block';
    
    tbody.innerHTML = ings.map(i => `
        <tr style="border-bottom: 1px solid rgba(0,0,0,0.05);">
            <td style="padding: 12px 0; font-weight:600; color: #333;">${i.ingredient_name}</td>
            <td style="padding: 12px 0; text-align:right; font-family: monospace;">${parseFloat(i.base_quantity).toFixed(2)}</td>
            <td style="padding: 12px 8px; text-align:left; color:var(--text-secondary);">${i.unit}</td>
            <td style="padding: 12px 0; text-align:right;">
                <button class="btn-icon" onclick="editIng(${i.id})" style="color: var(--sys-blue);"><i class="fas fa-edit"></i></button>
                <button class="btn-icon" onclick="deleteIng(${i.id})" style="color: var(--sys-red);"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

window.updateBasePax = async function() {
    if (!currentDishId) return;
    const bp = parseInt(document.getElementById('r_basePax').value);
    if (bp < 1) return Toast.error('Base pax must be at least 1');

    try {
        await Api.post(BASE + 'src/api/recipes.php', {
            action: 'update_base_pax',
            dish_id: currentDishId,
            base_pax: bp
        });
        Toast.success('Base pax updated');
        // Update local object
        const dish = recipesData.find(x => x.id == currentDishId);
        if(dish) dish.base_pax = bp;
        filterDishes(); // redraw sidebar
    } catch(e) {
        Toast.error(e.message);
    }
};

// ── CRUD INGREDIENTS ────────────────────────────────
window.openAddIngredientModal = function() {
    if (!currentDishId) return Toast.error('Select a dish first.');
    document.getElementById('ing_id').value = '';
    document.getElementById('ing_name').value = '';
    document.getElementById('ing_qty').value = '';
    document.getElementById('ing_unit').value = 'kg';
    document.getElementById('ingModalTitle').textContent = 'Add Ingredient';
    Modal.open('ingredientModal');
};

window.editIng = function(id) {
    const dish = recipesData.find(x => x.id == currentDishId);
    const ing = dish.ingredients.find(x => x.id == id);
    if(!ing) return;
    
    document.getElementById('ing_id').value = ing.id;
    document.getElementById('ing_name').value = ing.ingredient_name;
    document.getElementById('ing_qty').value = ing.base_quantity;
    document.getElementById('ing_unit').value = ing.unit;
    document.getElementById('ingModalTitle').textContent = 'Edit Ingredient';
    Modal.open('ingredientModal');
};

window.saveIngredient = async function(e) {
    e.preventDefault();
    const btn = document.getElementById('ingSaveBtn');
    Form.setLoading(btn, true);

    const id = document.getElementById('ing_id').value;
    const payload = {
        dish_id: currentDishId,
        ingredient_name: document.getElementById('ing_name').value,
        base_quantity: document.getElementById('ing_qty').value,
        unit: document.getElementById('ing_unit').value
    };

    try {
        if (id) {
            payload.id = id;
            await Api.put(BASE + 'src/api/recipes.php', payload);
            Toast.success('Ingredient updated');
        } else {
            await Api.post(BASE + 'src/api/recipes.php', payload);
            Toast.success('Ingredient added');
        }
        Modal.close('ingredientModal');
        await loadRecipes();
        selectDish(currentDishId); 
    } catch(err) {
        Toast.error(err.message);
    }
    Form.setLoading(btn, false);
};

window.deleteIng = async function(id) {
    if(!confirm('Are you sure you want to remove this ingredient?')) return;
    try {
        await Api.delete(BASE + 'src/api/recipes.php', { id });
        Toast.success('Ingredient removed');
        await loadRecipes();
        selectDish(currentDishId);
    } catch(e) {
        Toast.error(e.message);
    }
};

// ── YIELD CALCULATOR ────────────────────────────────
function populateCalcDropdown() {
    const sel = document.getElementById('calcSelectDish');
    sel.innerHTML = '<option value="">-- Choose Recipe --</option>' + 
        recipesData.map(d => `<option value="${d.id}">${d.name} (Base: ${d.base_pax})</option>`).join('');
}

window.computeYield = async function() {
    const dishId = document.getElementById('calcSelectDish').value;
    const target = parseInt(document.getElementById('calcTargetPax').value);

    if (!dishId) return Toast.error('Please select a dish to compute.');
    if (!target || target < 1) return Toast.error('Please specify a valid target pax count.');

    try {
        const res = await Api.get(BASE + `src/api/recipes.php?compute_pax=${target}&dish_id=${dishId}`);
        
        document.getElementById('calcBlank').style.display = 'none';
        const resultCard = document.getElementById('calcResultCard');
        resultCard.style.display = 'block';

        document.getElementById('resDishName').textContent = res.dish_name;
        document.getElementById('resBasePax').textContent = res.base_pax;
        document.getElementById('resTargetPax').textContent = res.target_pax;
        document.getElementById('resMultiplier').textContent = 'x ' + res.multiplier;

        const tbody = document.getElementById('resTbody');
        if (res.ingredients.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; color:#888;">No ingredients mapped to this recipe.</td></tr>';
        } else {
            tbody.innerHTML = res.ingredients.map(i => `
                <tr>
                    <td style="font-weight:600;">${i.ingredient_name}</td>
                    <td style="text-align:right; color:var(--text-secondary);">${i.base_quantity} ${i.unit}</td>
                    <td style="text-align:right; font-weight:700; color:var(--sys-green); font-size:16px;">
                        ${i.computed_quantity} <span style="font-size:12px; font-weight:600; color:#1A7A32;">${i.unit}</span>
                    </td>
                </tr>
            `).join('');
        }
    } catch(e) {
        Toast.error(e.message);
    }
};
</script>

<style>
.dish-list-item {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: all 0.2s ease;
}
.dish-list-item:hover {
    background: var(--surface-2);
}
.dish-list-item.active {
    background: rgba(48,209,88,0.06);
    border-left: 3px solid var(--sys-green);
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
