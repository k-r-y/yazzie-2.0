<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('frontdesk');

$pageTitle    = 'Dashboard';
$pageSubtitle = date('l, F j, Y');
$activePage   = 'dashboard';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- DATE FILTER -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-calendar-alt text-muted"></i>
                    <span class="fw-bold">Viewing Events For:</span>
                </div>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="tf" id="tf-day" value="day" onchange="refreshDashboard()">
                    <label class="btn btn-outline-primary btn-sm" for="tf-day">Today</label>

                    <input type="radio" class="btn-check" name="tf" id="tf-week" value="week" checked onchange="refreshDashboard()">
                    <label class="btn btn-outline-primary btn-sm" for="tf-week">This Week</label>

                    <input type="radio" class="btn-check" name="tf" id="tf-month" value="month" onchange="refreshDashboard()">
                    <label class="btn btn-outline-primary btn-sm" for="tf-month">This Month</label>

                    <input type="radio" class="btn-check" name="tf" id="tf-year" value="year" onchange="refreshDashboard()">
                    <label class="btn btn-outline-primary btn-sm" for="tf-year">This Year</label>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QUICK STATS -->
<div class="row g-3 mb-4">
    <div class="col-sm-6">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="stat-count">—</div>
                <div class="stat-label" id="label-count">Events</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-bullhorn"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="stat-pending-jobs">—</div>
                <div class="stat-label">Pending Job Orders</div>
            </div>
        </div>
    </div>
</div>

<!-- QUICK ACTIONS -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body" style="padding:16px 20px;">
                <div class="d-flex gap-3 flex-wrap">
                    <a href="<?= BASE_URL ?>/views/frontdesk/bookings.php" class="btn btn-primary py-3">
                        <i class="fas fa-plus"></i> New Booking
                    </a>
                    <a href="<?= BASE_URL ?>/views/frontdesk/costing.php" class="btn btn-outline-primary">
                        <i class="fas fa-cart-shopping"></i> Generate Grocery List
                    </a>
                    <a href="<?= BASE_URL ?>/views/frontdesk/dispatching.php" class="btn btn-outline-primary">
                        <i class="fas fa-bullhorn"></i> Dispatch Staff
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- UPCOMING BOOKINGS CALENDAR VIEW -->
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Upcoming Confirmed Events</div>
                    <div class="card-subtitle">Next 30 days</div>
                </div>
                <a href="<?= BASE_URL ?>/views/frontdesk/bookings.php" class="btn btn-outline-primary btn-sm">
                    All Bookings
                </a>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event Date</th>
                            <th>Client</th>
                            <th>Menu Package</th>
                            <th>Pax</th>
                            <th>Location</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="upcomingBody">
                        <tr><td colspan="6"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><div class="card-title">Unpaid Balances</div></div>
            <div style="max-height:420px;overflow-y:auto;" id="unpaidList">
                <div class="spinner"></div>
            </div>
        </div>
    </div>
</div>

<script>
function getTimeframe() {
    return document.querySelector('input[name="tf"]:checked').value;
}

function refreshDashboard() {
    const tf = getTimeframe();
    loadStats({ timeframe: tf });
    loadUpcoming({ timeframe: tf });
}

async function loadStats(params = {}) {
    try {
        const [kpis, pendingJobs] = await Promise.all([
            Api.get(BASE + 'src/api/analytics.php', { type: 'kpis', ...params }),
            Api.get(BASE + 'src/api/dispatching.php'),
        ]);

        const tf = params.timeframe || 'week';
        const labelMap = { day: 'Today', week: 'This Week', month: 'This Month', year: 'This Year' };
        const label = labelMap[tf] || tf;

        // Use standardized KPI values: active_bookings
        document.getElementById('stat-count').textContent   = kpis.active_bookings || 0;
        document.getElementById('label-count').textContent  = 'Events (' + label + ')';
        
        document.getElementById('stat-pending-jobs').textContent = pendingJobs.pending_count || 0;
    } catch (e) {
        console.error('Stats fail:', e);
    }
}

async function loadUpcoming(params = {}) {
    try {
        const d      = await Api.get(BASE + 'src/api/bookings.php', {
            status: 'confirmed', order: 'ASC', ...params
        });
        const tbody  = document.getElementById('upcomingBody');
        const events = d.bookings || [];

        const tf = params.timeframe || 'week';
        const label = tf.charAt(0).toUpperCase() + tf.slice(1);
        document.querySelector('.card-subtitle').textContent = label;

        if (events.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6"><div class="table-empty">
                <i class="fas fa-calendar-check"></i><p>No confirmed events found for this period.</p></div></td></tr>`;
            return;
        }

        tbody.innerHTML = events.map(b => `
            <tr>
                <td>
                    <div class="fw-700">${Format.dateShort(b.event_date)}</div>
                    <small class="text-muted">${b.event_time ? Format.time(b.event_time) : ''}</small>
                </td>
                <td class="td-name">${esc(b.client_name)}</td>
                <td>${esc(b.menu_name)}</td>
                <td>${b.pax_count}</td>
                <td class="text-sm text-muted">${esc((b.event_location || '—').substring(0, 35))}${b.event_location?.length > 35 ? '…' : ''}</td>
                <td>
                    <a href="${BASE}views/frontdesk/costing.php?booking_id=${b.id}" class="btn btn-outline-primary btn-sm" title="Grocery List">
                        <i class="fas fa-cart-shopping"></i>
                    </a>
                    <a href="${BASE}views/frontdesk/dispatching.php?booking_id=${b.id}" class="btn btn-outline-secondary btn-sm" title="Dispatch">
                        <i class="fas fa-bullhorn"></i>
                    </a>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        console.error('Upcoming fail:', e);
    }
}

async function loadUnpaidBalances() {
    try {
        const d    = await Api.get(BASE + 'src/api/bookings.php', { status: 'confirmed' });
        const div  = document.getElementById('unpaidList');
        const list = (d.bookings || []).filter(b =>
            parseFloat(b.total_cost) - parseFloat(b.amount_paid) > 0.01
        );

        if (list.length === 0) {
            div.innerHTML = `<div class="table-empty"><i class="fas fa-circle-check"></i><p>All confirmed bookings are fully paid.</p></div>`;
            return;
        }

        div.innerHTML = list.map(b => {
            const balance = parseFloat(b.total_cost) - parseFloat(b.amount_paid);
            return `
            <div style="padding:14px 20px;border-bottom:1px solid var(--border);">
                <div class="fw-700" style="font-size:14px;">${esc(b.client_name)}</div>
                <div class="text-sm text-muted">${Format.dateShort(b.event_date)} &mdash; ${esc(b.menu_name)}</div>
                <div class="text-sm text-muted">${b.pax_count} pax</div>
                
            </div>`;
        }).join('');
    } catch (e) {
        console.error('Unpaid fail:', e);
        document.getElementById('unpaidList').innerHTML = `<div class="p-4 text-center text-danger">Failed to load balances.</div>`;
    }
}

// Initial Load
(function() {
    refreshDashboard();
    loadUnpaidBalances();
})();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
