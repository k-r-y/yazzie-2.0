<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole(['admin', 'super_admin']);

$pageTitle    = 'Profit Guard';
$pageSubtitle = 'Real-time net profit analysis per event';
$activePage   = 'profit';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- KPI Summary Cards -->
<div class="row g-3 mb-3" id="kpiRow">
    <div class="col-6 col-lg-3">
        <div class="card" style="border-left:4px solid var(--sys-green);">
            <div class="card-body" style="padding:18px;">
                <div class="text-xs text-muted text-bold">TOTAL REVENUE</div>
                <div class="fw-800" style="font-size:22px; color:var(--sys-green);" id="kpi-revenue">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card" style="border-left:4px solid #FF9500;">
            <div class="card-body" style="padding:18px;">
                <div class="text-xs text-muted text-bold">TOTAL COSTS</div>
                <div class="fw-800" style="font-size:22px; color:#FF9500;" id="kpi-costs">—</div>
                <div class="text-xs text-muted" id="kpi-costs-detail">COGS + Payroll + Transport</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card" style="border-left:4px solid #007AFF;">
            <div class="card-body" style="padding:18px;">
                <div class="text-xs text-muted text-bold">NET PROFIT</div>
                <div class="fw-800" style="font-size:22px;" id="kpi-profit">—</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card" style="border-left:4px solid #AF52DE;">
            <div class="card-body" style="padding:18px;">
                <div class="text-xs text-muted text-bold">AVG MARGIN</div>
                <div class="fw-800" style="font-size:22px; color:#AF52DE;" id="kpi-margin">—</div>
                <div class="text-xs text-muted" id="kpi-events">—</div>
            </div>
        </div>
    </div>
</div>

<!-- Period Selector + Table -->
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
        <div>
            <div class="card-title"><i class="fas fa-chart-pie me-2" style="color:var(--sys-green);"></i>Event Profitability</div>
            <div class="card-subtitle">Revenue − COGS − Staff Payroll − Transport = Net Profit</div>
        </div>
        <div style="display:flex; gap:6px;">
            <button class="btn btn-sm btn-outline-secondary period-btn active" data-period="all" onclick="loadProfit('all', this)">All Time</button>
            <button class="btn btn-sm btn-outline-secondary period-btn" data-period="year" onclick="loadProfit('year', this)">This Year</button>
            <button class="btn btn-sm btn-outline-secondary period-btn" data-period="quarter" onclick="loadProfit('quarter', this)">Quarter</button>
            <button class="btn btn-sm btn-outline-secondary period-btn" data-period="month" onclick="loadProfit('month', this)">Month</button>
        </div>
    </div>
    <div id="profitTableWrap" style="overflow-x:auto;">
        <div class="spinner" style="margin:40px auto;"></div>
    </div>
</div>

<style>
.period-btn.active { background: var(--sys-green); color: #fff; border-color: var(--sys-green); }
.profit-positive { color: #1A7A32; font-weight: 700; }
.profit-negative { color: #C0392B; font-weight: 700; }
.margin-badge {
    display: inline-block; padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700;
}
.margin-good { background: rgba(48,209,88,0.1); color: #1A7A32; }
.margin-warn { background: rgba(255,149,0,0.1); color: #9A5400; }
.margin-bad  { background: rgba(255,59,48,0.1); color: #C0392B; }
.no-cogs-badge {
    display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 6px;
    font-size: 10px; font-weight: 600; background: rgba(255,149,0,0.08); color: #9A5400;
}
</style>

<script>
async function loadProfit(period = 'all', btnEl) {
    // Active button state
    document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');

    document.getElementById('profitTableWrap').innerHTML = '<div class="spinner" style="margin:40px auto;"></div>';

    try {
        const data = await Api.get(BASE + '/src/api/analytics.php', { type: 'profit_guard', period });
        const s = data.summary;
        const bookings = data.bookings || [];

        // KPIs
        document.getElementById('kpi-revenue').textContent = Format.peso(s.total_revenue);
        const totalCosts = s.total_cogs + s.total_payroll + s.total_transport;
        document.getElementById('kpi-costs').textContent = Format.peso(totalCosts);
        document.getElementById('kpi-costs-detail').textContent =
            `COGS: ${Format.peso(s.total_cogs)} · Payroll: ${Format.peso(s.total_payroll)}`;

        const profitEl = document.getElementById('kpi-profit');
        profitEl.textContent = Format.peso(s.total_profit);
        profitEl.style.color = s.total_profit >= 0 ? '#007AFF' : '#C0392B';

        document.getElementById('kpi-margin').textContent = s.avg_margin + '%';
        document.getElementById('kpi-events').textContent = `${s.event_count} event(s)`;

        if (!bookings.length) {
            document.getElementById('profitTableWrap').innerHTML = `
                <div class="empty-state" style="padding:40px;">
                    <div class="empty-state-icon"><i class="fas fa-chart-pie"></i></div>
                    <h3>No events found</h3>
                    <p>No confirmed or completed events in this period.</p>
                </div>`;
            return;
        }

        // Build table
        const rows = bookings.map(b => {
            const profitClass = b.profit >= 0 ? 'profit-positive' : 'profit-negative';
            const marginClass = b.margin >= 30 ? 'margin-good' : (b.margin >= 15 ? 'margin-warn' : 'margin-bad');
            const cogsWarning = !b.cogs_has_data ? '<span class="no-cogs-badge"><i class="fas fa-exclamation-triangle"></i> No COGS</span>' : '';

            return `<tr>
                <td style="font-weight:600;">#${b.id}</td>
                <td>${Format.dateShort(b.event_date)}</td>
                <td>${esc(b.client_name)}</td>
                <td>${esc(b.event_type || '—')}</td>
                <td class="text-right">${b.pax_count}</td>
                <td class="text-right">${Format.peso(b.revenue)}</td>
                <td class="text-right" style="color:#9A5400;">${Format.peso(b.cogs)} ${cogsWarning}</td>
                <td class="text-right" style="color:rgba(60,60,67,0.6);">${Format.peso(b.payroll)}</td>
                <td class="text-right ${profitClass}">${Format.peso(b.profit)}</td>
                <td class="text-center"><span class="margin-badge ${marginClass}">${b.margin}%</span></td>
            </tr>`;
        }).join('');

        document.getElementById('profitTableWrap').innerHTML = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Type</th>
                        <th class="text-right">Pax</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">COGS</th>
                        <th class="text-right">Payroll</th>
                        <th class="text-right">Net Profit</th>
                        <th class="text-center">Margin</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>`;

    } catch(e) {
        Toast.error('Failed to load profit data: ' + e.message);
        document.getElementById('profitTableWrap').innerHTML = `
            <div class="empty-state" style="padding:40px;">
                <p style="color:#C0392B;">Error loading data. Please try again.</p>
            </div>`;
    }
}

loadProfit('all', document.querySelector('.period-btn[data-period="all"]'));
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
