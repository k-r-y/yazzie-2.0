<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('frontdesk');

$pageTitle    = 'Grocery Costing';
$pageSubtitle = 'Generate automated ingredient lists for confirmed bookings';
$activePage   = 'costing';
$preloadId    = (int)($_GET['booking_id'] ?? 0);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row g-3">
    <!-- LEFT: Selector -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><div class="card-title"><i class="fas fa-cart-shopping me-2 text-primary"></i>Select Booking</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Confirmed Booking</label>
                    <select class="form-control" id="bookingSelect" onchange="loadGroceryList()">
                        <option value="">Choose a booking…</option>
                    </select>
                </div>
                <div id="bookingDetails" style="display:none;margin-top:16px;">
                    <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;">
                        <div class="mb-2">
                            <div class="text-xs text-muted text-bold">CLIENT</div>
                            <div class="fw-600" id="detail-client">—</div>
                        </div>
                        <div class="mb-2">
                            <div class="text-xs text-muted text-bold">EVENT DATE</div>
                            <div class="fw-600" id="detail-date">—</div>
                        </div>
                        <div class="mb-2">
                            <div class="text-xs text-muted text-bold">MENU PACKAGE</div>
                            <div class="fw-600" id="detail-menu">—</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted text-bold">GUEST COUNT (PAX)</div>
                            <div class="fw-700 text-primary" style="font-size:22px;" id="detail-pax">—</div>
                        </div>
                    </div>
                    <a id="printBtn" href="#" target="_blank" class="btn btn-primary btn-full mt-3">
                        <i class="fas fa-print"></i> Print Grocery List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Grocery List -->
    <div class="col-lg-8">
        <div class="card" id="groceryCard">
            <div class="card-header">
                <div>
                    <div class="card-title">Generated Grocery List</div>
                    <div class="card-subtitle" id="grocerySubtitle">Select a booking to generate the list</div>
                </div>
            </div>
            <div id="groceryContent">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-cart-shopping"></i></div>
                    <h3>No booking selected</h3>
                    <p>Select a confirmed booking on the left to generate the precise ingredient list.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentBookings = [];

async function init() {
    const [today] = [new Date().toISOString().split('T')[0]];
    const d = await Api.get(BASE + 'src/api/bookings.php', {
        status: 'confirmed', from: today
    });
    currentBookings = d.bookings || [];

    const sel = document.getElementById('bookingSelect');
    sel.innerHTML = '<option value="">Choose a booking…</option>' +
        currentBookings.map(b =>
            `<option value="${b.id}" ${b.id == <?= $preloadId ?> ? 'selected' : ''}>
                #${b.id} — ${esc(b.client_name)} (${Format.dateShort(b.event_date)}, ${b.pax_count} pax)
            </option>`
        ).join('');

    if (<?= $preloadId ?> > 0) await loadGroceryList();
}

async function loadGroceryList() {
    const bookingId = document.getElementById('bookingSelect').value;
    if (!bookingId) {
        document.getElementById('bookingDetails').style.display = 'none';
        document.getElementById('groceryContent').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-cart-shopping"></i></div>
                <h3>No booking selected</h3><p>Select a confirmed booking to generate the list.</p>
            </div>`;
        return;
    }

    const booking = currentBookings.find(b => b.id == bookingId);
    if (!booking) return;

    // Show booking details
    document.getElementById('detail-client').textContent = booking.client_name;
    document.getElementById('detail-date').textContent   = Format.dateShort(booking.event_date) + (booking.event_time ? ' ' + Format.time(booking.event_time) : '');
    document.getElementById('detail-menu').textContent   = booking.menu_name || booking.package_name || '—';
    document.getElementById('detail-pax').textContent    = booking.pax_count + ' guests';
    document.getElementById('bookingDetails').style.display = 'block';
    document.getElementById('printBtn').href = BASE + 'templates/grocery_list.php?booking_id=' + bookingId;

    document.getElementById('grocerySubtitle').textContent =
        `${booking.pax_count} pax × recipe quantities`;

    document.getElementById('groceryContent').innerHTML = '<div class="spinner"></div>';

    try {
        const pax = parseInt(booking.pax_count);

        // Step 1: Get the dishes selected for this booking
        const dishData = await Api.get(BASE + 'src/api/bookings.php', {
            id: bookingId, dishes: 1
        });
        const allDishes   = dishData.dishes || [];
        const dishes       = allDishes.filter(d => !d.is_custom);
        const customDishes = allDishes.filter(d => d.is_custom);

        // Build custom dish warning if any exist
        const customWarningHtml = customDishes.length > 0 ? `
            <div style="display:flex; gap:10px; align-items:flex-start; background:rgba(255,149,0,0.08);
                        border:1px solid rgba(255,149,0,0.3); border-radius:12px; padding:14px 16px; margin-bottom:16px;">
                <span style="font-size:20px; flex-shrink:0;">⚠️</span>
                <div>
                    <div style="font-size:13px; font-weight:700; color:#9A5400; margin-bottom:4px;">
                        ${customDishes.length} Custom Dish${customDishes.length > 1 ? 'es' : ''} — No Recipe Data
                    </div>
                    <div style="font-size:12px; color:rgba(60,60,67,0.7); margin-bottom:6px;">
                        The following items have no recipe/ingredient data and are <strong>excluded from this grocery list</strong>.
                        Add them manually or go to Recipes & Computation to define their ingredients.
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap:5px;">
                        ${customDishes.map(d => `<span style="background:rgba(255,149,0,0.12); color:#9A5400; border-radius:6px; padding:3px 10px; font-size:11.5px; font-weight:600;">${d.name}</span>`).join('')}
                    </div>
                </div>
            </div>` : '';

        if (dishes.length === 0 && customDishes.length > 0) {
            document.getElementById('groceryContent').innerHTML = customWarningHtml + `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle" style="font-size:36px;color:#FF9500;display:block;margin-bottom:12px;"></i>
                    <p>All dishes for this booking are custom items with no recipe data.<br>
                    Add ingredients in <strong>Recipes &amp; Computation</strong> or list them manually.</p>
                    <a href="${BASE}views/admin/recipes.php" class="btn btn-primary btn-sm mt-3">Go to Recipe Manager</a>
                </div>`;
            return;
        }

        if (dishes.length === 0) {
            document.getElementById('groceryContent').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-list-ul" style="font-size:36px;color:var(--text-muted);display:block;margin-bottom:12px;"></i>
                    <p>No dishes selected for this booking, or no recipes defined.<br>
                    Assign dishes and add ingredients in the Recipe Manager.</p>
                    <a href="${BASE}views/admin/recipes.php" class="btn btn-primary btn-sm mt-3">Go to Recipe Manager</a>
                </div>`;
            return;
        }

        // Step 2: For each dish, fetch the scaled recipe. Aggregate totals.
        const aggregated = {}; // { "Ingredient (unit)": { name, unit, total } }

        await Promise.all(dishes.map(async (dish) => {
            try {
                const res = await Api.get(BASE + `/src/api/recipes.php`, {
                    compute_pax: pax,
                    dish_id: dish.dish_id || dish.id
                });
                (res.ingredients || []).forEach(ing => {
                    const key = ing.ingredient_name + '|' + ing.unit;
                    if (!aggregated[key]) {
                        aggregated[key] = { name: ing.ingredient_name, unit: ing.unit, total: 0, basePax: res.base_pax };
                    }
                    aggregated[key].total += parseFloat(ing.computed_quantity);
                });
            } catch (e) {
                // Dish has no recipe yet — skip silently, it'll show in empty-ingredient warning
                console.warn('No recipe for dish ' + (dish.dish_id || dish.id) + ':', e.message);
            }
        }));

        const rows = Object.values(aggregated);

        if (!rows.length) {
            document.getElementById('groceryContent').innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-exclamation-triangle" style="font-size:36px;color:var(--sys-orange,#FF9500);display:block;margin-bottom:12px;"></i>
                    <p>Dishes are selected but none have recipe ingredients defined yet.<br>
                    Go to <strong>Recipes &amp; Computation</strong> to add ingredients to each dish.</p>
                    <a href="${BASE}views/admin/recipes.php" class="btn btn-primary btn-sm mt-3">Go to Recipe Manager</a>
                </div>`;
            return;
        }

        const tableRows = rows.map(r => {
            const formatted = Format.unit(r.total, r.unit);
            const [val, unit] = formatted.split(' ', 2);
            return `<tr>
                <td class="td-name">${esc(r.name)}</td>
                <td class="text-right text-bold" style="font-size:16px;">${val}</td>
                <td class="text-muted">${unit}</td>
                <td><div style="width:18px;height:18px;border:2px solid var(--border);border-radius:4px;"></div></td>
            </tr>`;
        }).join('');

        document.getElementById('groceryContent').innerHTML = `
            ${customWarningHtml}
            <div class="table-responsive" style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th class="text-right">Qty (${pax} pax)</th>
                            <th>Unit</th>
                            <th>✓ Check</th>
                        </tr>
                    </thead>
                    <tbody>${tableRows}</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" style="padding:12px 16px;font-size:13px;color:var(--text-muted);">
                                <i class="fas fa-circle-info me-1"></i>
                                Quantities aggregated across <strong>${dishes.length} dish(es)</strong> for
                                <strong>${pax} guests</strong>.
                                ${customDishes.length > 0 ? `<br><span style="color:#9A5400;">⚠️ ${customDishes.length} custom dish(es) not included — see warning above.</span>` : ''}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `;
    } catch (e) {
        Toast.error('Failed to generate grocery list: ' + e.message);
    }
}

init();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
