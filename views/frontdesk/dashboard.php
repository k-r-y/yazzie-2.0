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

<!-- QUICK STATS -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="stat-today">—</div>
                <div class="stat-label">Today's Events</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon gold"><i class="fas fa-calendar-week"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="stat-week">—</div>
                <div class="stat-label">This Week's Events</div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
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
                    <a href="<?= BASE_URL ?>/views/frontdesk/bookings.php" class="btn btn-primary">
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

async function loadStats() {
    try {
        const today     = new Date().toISOString().split('T')[0];
        const weekEnd   = new Date(Date.now() + 7 * 86400000).toISOString().split('T')[0];

        const [todayRes, weekRes, pendingJobs] = await Promise.all([
            Api.get(BASE + '/src/api/bookings.php', { status: 'confirmed', from: today, to: today }),
            Api.get(BASE + '/src/api/bookings.php', { status: 'confirmed', from: today, to: weekEnd }),
            Api.get(BASE + '/src/api/dispatching.php'),
        ]);

        document.getElementById('stat-today').textContent       = todayRes.bookings?.length || 0;
        document.getElementById('stat-week').textContent        = weekRes.bookings?.length  || 0;
        document.getElementById('stat-pending-jobs').textContent = pendingJobs.pending_count || 0;
    } catch (e) {
        console.error('Stats fail:', e);
        ['stat-today', 'stat-week', 'stat-pending-jobs'].forEach(id => {
            document.getElementById(id).textContent = 'ERR';
        });
    }
}

async function loadUpcoming() {
    try {
        const from   = new Date().toISOString().split('T')[0];
        const to     = new Date(Date.now() + 30 * 86400000).toISOString().split('T')[0];
        const d      = await Api.get(BASE + '/src/api/bookings.php', {
            status: 'confirmed', from, to
        });
        const tbody  = document.getElementById('upcomingBody');
        const events = d.bookings || [];

        if (events.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6"><div class="table-empty">
                <i class="fas fa-calendar-check"></i><p>No confirmed events in the next 30 days.</p></div></td></tr>`;
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
                    <a href="${BASE}/views/frontdesk/costing.php?booking_id=${b.id}" class="btn btn-outline-primary btn-sm" title="Grocery List">
                        <i class="fas fa-cart-shopping"></i>
                    </a>
                    <a href="${BASE}/views/frontdesk/dispatching.php?booking_id=${b.id}" class="btn btn-outline-secondary btn-sm" title="Dispatch">
                        <i class="fas fa-bullhorn"></i>
                    </a>
                </td>
            </tr>
        `).join('');
    } catch (e) {
        console.error('Upcoming fail:', e);
        document.getElementById('upcomingBody').innerHTML = `<tr><td colspan="6" class="text-center text-danger p-4">
            <i class="fas fa-exclamation-triangle"></i> Failed to load events.</td></tr>`;
    }
}

async function loadUnpaidBalances() {
    try {
        const d    = await Api.get(BASE + '/src/api/bookings.php', { status: 'confirmed' });
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
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px;">
                    <span style="font-size:12px; font-weight:700; color:#C0392B;">Balance: ${Format.peso(balance)}</span>
                    <a href="${BASE}/views/frontdesk/bookings.php?highlight=${b.id}" class="btn btn-outline-primary btn-sm">Pay</a>
                </div>
            </div>`;
        }).join('');
    } catch (e) {
        console.error('Unpaid fail:', e);
        document.getElementById('unpaidList').innerHTML = `<div class="p-4 text-center text-danger">Failed to load balances.</div>`;
    }
}

loadStats();
loadUpcoming();
loadUnpaidBalances();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
