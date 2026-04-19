<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Dashboard';
$pageSubtitle = 'Business Analytics & Overview';
$activePage   = 'dashboard';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- KPI CARDS -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-peso-sign"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-revenue-mtd">—</div>
                <div class="stat-label">Revenue This Month</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-active">—</div>
                <div class="stat-label">Active Bookings</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon sage"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-clients">—</div>
                <div class="stat-label">Total Clients</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card" style="cursor:pointer;" onclick="location.href='<?= BASE_URL ?>/views/admin/bookings.php?status=pending'">
            <div class="stat-icon orange"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-value" id="kpi-pending">—</div>
                <div class="stat-label">Awaiting Downpayment</div>
                <div class="stat-label text-sm" id="kpi-outstanding" style="margin-top:2px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="card-title">Monthly Revenue</div>
                    <div class="card-subtitle">Total payments collected — last 12 months</div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:280px;">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <div>
                    <div class="card-title">Menu Popularity</div>
                    <div class="card-subtitle">Bookings per package</div>
                </div>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div class="chart-container" style="height:240px;width:100%;">
                    <canvas id="menuChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- RECENT BOOKINGS TABLE -->
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Upcoming Events</div>
            <div class="card-subtitle">Next confirmed bookings</div>
        </div>
        <a href="<?= BASE_URL ?>/views/admin/bookings.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-list"></i> All Bookings
        </a>
    </div>
    <div class="table-wrapper">
        <table class="data-table" id="upcomingTable">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Event Date</th>
                    <th>Menu Package</th>
                    <th>Pax</th>
                    <th>Total Cost</th>
                    <th>Payment</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody id="upcomingBody">
                <tr><td colspan="7"><div class="spinner"></div></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
async function loadKPIs() {
    try {
        const d = await Api.get(BASE + 'src/api/analytics.php', { type: 'kpis' });
        document.getElementById('kpi-revenue-mtd').textContent  = Format.peso(d.revenue_mtd);
        document.getElementById('kpi-active').textContent       = d.active_bookings;
        document.getElementById('kpi-clients').textContent      = d.total_clients;
        document.getElementById('kpi-pending').textContent      = d.pending_bookings ?? d.unpaid_count ?? 0;
        document.getElementById('kpi-outstanding').textContent  = 'Outstanding: ' + Format.peso(d.outstanding);
    } catch (e) { console.error(e); }
}

async function loadRevenueChart() {
    try {
        const d = await Api.get(BASE + 'src/api/analytics.php', { type: 'revenue_chart' });
        const ctx = document.getElementById('revenueChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: d.labels,
                datasets: [{
                    label: 'Revenue (₱)',
                    data: d.data,
                    backgroundColor: 'rgba(22, 163, 74, 0.12)',
                    borderColor: '#16A34A',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: v => '₱' + v.toLocaleString('en-PH'),
                            font: { family: 'Inter', size: 11 },
                            color: '#737373',
                        },
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        border: { display: false },
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Inter', size: 11 }, color: '#737373' },
                        border: { display: false },
                    }
                }
            }
        });
    } catch (e) { console.error(e); }
}

async function loadMenuChart() {
    try {
        const d = await Api.get(BASE + 'src/api/analytics.php', { type: 'menu_popularity' });
        const ctx = document.getElementById('menuChart').getContext('2d');
        const colors = ['#16A34A','#22C55E','#0D9488','#4ADE80','#6A9B7E','#059669','#15803D','#86EFAC'];
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: d.labels,
                datasets: [{ data: d.data, backgroundColor: colors, borderWidth: 3, borderColor: 'rgba(255,255,255,0.8)' }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { family: 'Inter', size: 11 }, padding: 14, color: '#525252', boxWidth: 10, usePointStyle: true } }
                },
                cutout: '65%'
            }
        });
    } catch (e) { console.error(e); }
}

async function loadUpcoming() {
    try {
        const d = await Api.get(BASE + 'src/api/bookings.php', {
            from: new Date().toISOString().split('T')[0]
        });
        const tbody = document.getElementById('upcomingBody');
        if (!d.bookings || d.bookings.length === 0) {
            tbody.innerHTML = `<tr data-empty><td colspan="7"><div class="table-empty">
                <i class="fas fa-calendar-xmark"></i><p>No upcoming confirmed bookings.</p></div></td></tr>`;
            return;
        }
        tbody.innerHTML = d.bookings.slice(0, 8).map(b => `
            <tr>
                <td class="td-name">${esc(b.client_name)}</td>
                <td>${Format.dateShort(b.event_date)} ${b.event_time ? '· ' + Format.time(b.event_time) : ''}</td>
                <td>${esc(b.menu_name)}</td>
                <td>${b.pax_count} pax</td>
                <td class="text-bold">${Format.peso(b.total_cost)}</td>
                <td>${Format.paymentBadge(b.payment_status)}</td>
                <td>${Format.bookingBadge(b.booking_status)}</td>
            </tr>
        `).join('');
    } catch (e) {
        document.getElementById('upcomingBody').innerHTML =
            '<tr><td colspan="7" class="text-center text-muted p-4">Failed to load bookings.</td></tr>';
    }
}

loadKPIs();
loadRevenueChart();
loadMenuChart();
loadUpcoming();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
