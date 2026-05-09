<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'System Activity Log';
$pageSubtitle = 'Audit Trail & Event History';
$activePage   = 'activity_log';

// Quick Stats
$totalLogs   = $pdo->query("SELECT COUNT(*) FROM audit_log")->fetchColumn();
$authLogs    = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'login%' OR action LIKE 'logout%'")->fetchColumn();
$payLogs     = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'payment%' OR action LIKE 'refund%' OR action LIKE 'paymongo%'")->fetchColumn();
$bookingLogs = $pdo->query("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'booking%' OR action LIKE 'reminder%'")->fetchColumn();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- ── Page Header ─────────────────────────────────────────────────── -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
    <div>
        <h1 class="fw-800 mb-0" style="font-size:1.75rem; letter-spacing: -0.5px;">
            <i class="fas fa-shield-halved me-2" style="color:var(--sys-green);"></i>System Activity Log
        </h1>
        <p class="text-muted text-sm mb-0 mt-1">Complete audit trail of all system actions</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <span class="badge text-bg-secondary fw-600" id="totalRecordsBadge" style="font-size:11px; padding:6px 10px;">
            <i class="fas fa-database me-1"></i>Loading…
        </span>
        <button class="btn btn-sm fw-600" id="exportBtn" onclick="exportLogs()" style="background:var(--glass-card-bg); border: 1px solid var(--glass-sep); color: var(--label-1); box-shadow: var(--shadow-sm);">
            <i class="fas fa-download me-1 text-muted"></i>Export CSV
        </button>
    </div>
</div>

<!-- ── Quick Stats ──────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card stat-card" style="background: var(--glass-card-bg); backdrop-filter: blur(20px); border: 0.5px solid var(--glass-sep); border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(120,120,128,0.1); color: var(--label-2); width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fas fa-database"></i>
                </div>
                <div>
                    <div class="text-muted text-xs fw-600 text-uppercase mb-1">Total Logs</div>
                    <div class="fw-800" style="font-size: 1.25rem; line-height: 1;"><?= number_format($totalLogs) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card" style="background: var(--glass-card-bg); backdrop-filter: blur(20px); border: 0.5px solid var(--glass-sep); border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(48, 209, 88, 0.12); color: #30d158; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fas fa-right-to-bracket"></i>
                </div>
                <div>
                    <div class="text-muted text-xs fw-600 text-uppercase mb-1">Auth Events</div>
                    <div class="fw-800" style="font-size: 1.25rem; line-height: 1;"><?= number_format($authLogs) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card" style="background: var(--glass-card-bg); backdrop-filter: blur(20px); border: 0.5px solid var(--glass-sep); border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(255,159,10,0.12); color: #ff9f0a; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fas fa-peso-sign"></i>
                </div>
                <div>
                    <div class="text-muted text-xs fw-600 text-uppercase mb-1">Payments</div>
                    <div class="fw-800" style="font-size: 1.25rem; line-height: 1;"><?= number_format($payLogs) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card stat-card" style="background: var(--glass-card-bg); backdrop-filter: blur(20px); border: 0.5px solid var(--glass-sep); border-radius: 16px;">
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="stat-icon" style="background: rgba(10, 132, 255, 0.12); color: #0a84ff; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div>
                    <div class="text-muted text-xs fw-600 text-uppercase mb-1">Bookings</div>
                    <div class="fw-800" style="font-size: 1.25rem; line-height: 1;"><?= number_format($bookingLogs) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── Control Panel (Sticky Filter Bar) ──────────────────────────── -->
<div class="card mb-4" id="filterPanel" style="position:sticky; top: 70px; z-index:100; background: var(--glass-card-bg); backdrop-filter: blur(20px);">
    <div class="card-body py-3 px-4">
        <div class="d-flex flex-wrap align-items-end gap-2">

            <!-- Search -->
            <div style="flex: 1 1 220px; min-width: 200px;">
                <label class="text-muted text-xs fw-600 text-uppercase mb-1" style="font-size:10px;">Search</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text px-3 py-2" style="background: rgba(120,120,128,0.04); border-color:var(--glass-sep); border-radius: 10px 0 0 10px;">
                        <i class="fas fa-magnifying-glass text-muted py-1" style="font-size:11px;"></i>
                    </span>
                    <input type="text" id="filterSearch" class="form-control py-2"
                           placeholder="Search actions, users, IP…"
                           style="border-left:none; background:rgba(120,120,128,0.04); border-radius: 0 10px 10px 0; font-size: 13px;">
                </div>
            </div>

            <!-- Action Category -->
            <div style="flex: 0 1 150px; min-width: 140px;">
                <label class="text-muted text-xs fw-600 text-uppercase mb-1" style="font-size:10px;">Action Type</label>
                <select id="filterAction" class="form-select form-select-sm py-2" style="background-color:rgba(120,120,128,0.04); border-radius: 10px; font-size: 13px;">
                    <option value="">All Actions</option>
                    <option value="login">🔐 Authentication</option>
                    <option value="booking">📅 Bookings</option>
                    <option value="payment">💰 Payments</option>
                    <option value="inventory">📦 Inventory</option>
                    <option value="dispatch">👥 Dispatch</option>
                    <option value="user">🛡️ User Mgmt</option>
                    <option value="setting">⚙️ Settings</option>
                    <option value="system">🖥️ System</option>
                </select>
            </div>


            <!-- Date From -->
            <div style="flex: 0 1 140px; min-width: 130px;">
                <label class="text-muted text-xs fw-600 text-uppercase mb-1" style="font-size:10px;">Date From</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text px-2 py-2" style="background:rgba(120,120,128,0.04); border-color:var(--glass-sep); border-radius: 10px 0 0 10px; display:flex; align-items:center;">
                        <i class="fas fa-calendar text-muted py-1" style="font-size:11px;"></i>
                    </span>
                    <input type="date" id="filterDateFrom" class="form-control py-2"
                           title="From date" style="background:rgba(120,120,128,0.04); border-left:none; border-radius: 0 10px 10px 0; font-size: 13px; padding-left: 4px;">
                </div>
            </div>

            <!-- Date To -->
            <div style="flex: 0 1 140px; min-width: 130px;">
                <label class="text-muted text-xs fw-600 text-uppercase mb-1" style="font-size:10px;">Date To</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text px-2 py-2" style="background:rgba(120,120,128,0.04); border-color:var(--glass-sep); border-radius: 10px 0 0 10px; display:flex; align-items:center;">
                        <i class="fas fa-calendar-check text-muted py-1" style="font-size:11px;"></i>
                    </span>
                    <input type="date" id="filterDateTo" class="form-control py-2"
                           title="To date" style="background:rgba(120,120,128,0.04); border-left:none; border-radius: 0 10px 10px 0; font-size: 13px; padding-left: 4px;">
                </div>
            </div>

            <!-- Reset -->
            <div class="ms-auto" style="flex: 0 0 auto;">
                <button class="btn btn-sm fw-600 py-2 px-3" id="resetFiltersBtn" onclick="resetFilters()"
                        style="background:rgba(255,59,48,0.08); color:#ff3b30; border:1px solid rgba(255,59,48,0.2); border-radius: 10px;">
                    <i class="fas fa-rotate-left me-1"></i>Reset
                </button>
            </div>

        </div>
    </div>
</div>

<!-- ── Log List Container ───────────────────────────────────────────── -->
<div id="logListContainer">
    <!-- Skeleton loader shown on first load -->
    <div id="logSkeleton">
        <?php for ($i = 0; $i < 8; $i++): ?>
        <div class="log-card-skeleton mb-2">
            <div class="skeleton-line short"></div>
            <div class="skeleton-line long"></div>
            <div class="skeleton-line medium"></div>
        </div>
        <?php endfor; ?>
    </div>

    <!-- Actual log list (rendered by JS) -->
    <div id="logList" style="display:none;"></div>

    <!-- Empty State -->
    <div id="logEmptyState" style="display:none;" class="text-center py-5 my-4">
        <div style="width:80px; height:80px; border-radius:50%; background:rgba(120,120,128,0.1);
                    display:flex; align-items:center; justify-content:center; margin:0 auto 20px;">
            <i class="fas fa-binoculars" style="font-size:32px; color:var(--label-4);"></i>
        </div>
        <h5 class="fw-700 mb-1" style="color:var(--label-2);">No Activity Found</h5>
        <p class="text-muted text-sm mb-3">No log entries match your current filters.</p>
        <button class="btn btn-sm btn-outline-secondary" onclick="resetFilters()">
            <i class="fas fa-rotate-left me-1"></i>Clear Filters
        </button>
    </div>
</div>

<!-- ── Pagination ────────────────────────────────────────────────────── -->
<div id="paginationContainer" class="table-pagination mt-3" style="display:none;">
    <button id="pagePrevBtn" class="pagination-button" onclick="changePage(currentPage - 1)" disabled>
        <i class="fas fa-chevron-left me-1"></i>Previous
    </button>
    <div id="pageNumbers" class="d-flex gap-1 align-items-center"></div>
    <button id="pageNextBtn" class="pagination-button" onclick="changePage(currentPage + 1)" disabled>
        Next<i class="fas fa-chevron-right ms-1"></i>
    </button>
</div>

<!-- ── Styles ─────────────────────────────────────────────────────────── -->
<style>
/* ── Log Card ── */
.log-card {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 18px 22px;
    background: var(--glass-card-bg);
    border: 0.5px solid var(--glass-sep);
    border-radius: 16px;
    margin-bottom: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.02);
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    animation: fadeSlideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
.log-card:hover {
    background: var(--surface-1);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.04);
    border-color: rgba(120,120,128,0.15);
}
@keyframes fadeSlideIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Badge Column ── */
.log-badge-col {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    min-width: 90px;
}
.log-action-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
    white-space: nowrap;
    width: 100%;
}
/* Badge color variants */
.badge-login     { background: rgba(48, 209, 88, 0.12);  color: #30d158; border: 1px solid rgba(48,209,88,0.25); }
.badge-booking   { background: rgba(10, 132, 255, 0.12); color: #0a84ff; border: 1px solid rgba(10,132,255,0.25); }
.badge-payment   { background: rgba(255,159,10,0.12);    color: #ff9f0a; border: 1px solid rgba(255,159,10,0.25); }
.badge-inventory { background: rgba(100,210,255,0.12);   color: #64d2ff; border: 1px solid rgba(100,210,255,0.25); }
.badge-dispatch  { background: rgba(191,90,242,0.12);    color: #bf5af2; border: 1px solid rgba(191,90,242,0.25); }
.badge-user      { background: rgba(255,69,58,0.12);     color: #ff453a; border: 1px solid rgba(255,69,58,0.25); }
.badge-setting   { background: rgba(120,120,128,0.12);   color: #8e8e93; border: 1px solid rgba(120,120,128,0.25); }
.badge-system    { background: rgba(255,55,95,0.10);     color: #ff375f; border: 1px solid rgba(255,55,95,0.20); }
.badge-other     { background: rgba(120,120,128,0.08);   color: #aeaeb2; border: 1px solid rgba(120,120,128,0.15); }

/* ── Middle Column ── */
.log-body {
    flex: 1;
    min-width: 0;
    padding-top: 2px;
}
.log-description {
    font-size: 14px;
    font-weight: 600;
    color: var(--label-1);
    line-height: 1.5;
    margin-bottom: 6px;
}
.log-detail {
    font-size: 12px;
    color: var(--label-3);
    font-family: 'SF Mono', 'Menlo', 'Monaco', monospace;
    background: rgba(120,120,128,0.04);
    padding: 6px 10px;
    border-radius: 8px;
    border: 0.5px solid var(--glass-sep);
    display: inline-block;
}
.log-entity-pill {
    display: inline-block;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(120,120,128,0.1);
    color: var(--label-2);
    margin-right: 6px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    vertical-align: middle;
}

/* ── Right Column ── */
.log-meta-col {
    flex-shrink: 0;
    text-align: right;
    min-width: 150px;
    padding-top: 2px;
}
.log-time {
    font-size: 12px;
    color: var(--label-3);
    font-weight: 500;
    margin-bottom: 6px;
}
.log-ip {
    font-family: 'SF Mono', 'Menlo', 'Monaco', monospace;
    font-size: 11px;
    color: var(--label-4);
    background: rgba(120,120,128,0.07);
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-block;
    border: 0.5px solid var(--glass-sep);
}

/* ── Skeleton Loader ── */
.log-card-skeleton {
    height: 84px;
    background: var(--glass-card-bg);
    border: 0.5px solid var(--glass-sep);
    border-radius: 16px;
    padding: 18px 22px;
    overflow: hidden;
}
.skeleton-line {
    height: 10px;
    border-radius: 6px;
    background: linear-gradient(90deg, rgba(120,120,128,0.08) 25%, rgba(120,120,128,0.15) 50%, rgba(120,120,128,0.08) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.4s infinite;
    margin-bottom: 8px;
}
.skeleton-line.short  { width: 25%; }
.skeleton-line.medium { width: 50%; }
.skeleton-line.long   { width: 80%; }
@keyframes shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* ── Pagination Numbers ── */
.page-num-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: 0.5px solid var(--glass-sep);
    background: transparent;
    font-size: 11px;
    font-weight: 600;
    color: var(--label-2);
    cursor: pointer;
    transition: all 0.15s;
}
.page-num-btn:hover    { background: rgba(120,120,128,0.1); }
.page-num-btn.active   { background: var(--sys-green); color: #fff; border-color: var(--sys-green); }
.page-num-btn.ellipsis { background: transparent; border: none; cursor: default; color: var(--label-4); }

/* ── Responsive ── */
@media (max-width: 640px) {
    .log-card     { flex-wrap: wrap; }
    .log-meta-col { text-align: left; min-width: 100%; margin-top: 6px; }
    .log-badge-col{ flex-direction: row; min-width: auto; }
}
</style>

<!-- ── JavaScript Controller ─────────────────────────────────────────── -->
<script>
// ── State ──────────────────────────────────────────────────────────────────
let currentPage   = 1;
let totalPages    = 1;
let totalRecords  = 0;
let debounceTimer = null;
const LIMIT       = 20;

// ── Bootstrap ──────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    fetchLogs();

    // Debounced search
    document.getElementById('filterSearch').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => { currentPage = 1; fetchLogs(); }, 380);
    });

    // Instant-trigger filters
    ['filterAction', 'filterDateFrom', 'filterDateTo'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => {
            currentPage = 1;
            fetchLogs();
        });
    });
});

// ── Core Fetch ──────────────────────────────────────────────────────────────
async function fetchLogs() {
    showSkeleton();

    const params = new URLSearchParams({
        page:        currentPage,
        limit:       LIMIT,
        search:      document.getElementById('filterSearch').value.trim(),
        action_type: document.getElementById('filterAction').value,
        date_from:   document.getElementById('filterDateFrom').value,
        date_to:     document.getElementById('filterDateTo').value,
    });

    // Strip empty params
    for (const [k, v] of [...params.entries()]) {
        if (!v) params.delete(k);
    }

    try {
        const data = await Api.get(BASE + 'src/api/audit_logs.php?' + params.toString());
        renderLogs(data.logs || []);
        renderPagination(data.meta);
        totalRecords = data.meta?.totalRecords ?? 0;
        document.getElementById('totalRecordsBadge').innerHTML =
            `<i class="fas fa-database me-1"></i>${totalRecords.toLocaleString()} entries`;
    } catch (err) {
        hideSkeleton();
        console.error(err);
        Toast.error('Failed to load activity log.');
    }
}

// ── Render Functions ───────────────────────────────────────────────────────
function renderLogs(logs) {
    hideSkeleton();
    const list = document.getElementById('logList');
    const empty = document.getElementById('logEmptyState');

    if (!logs.length) {
        list.style.display = 'none';
        empty.style.display = 'block';
        document.getElementById('paginationContainer').style.display = 'none';
        return;
    }

    empty.style.display = 'none';
    list.style.display  = 'block';
    list.innerHTML = logs.map((log, idx) => buildLogCard(log, idx)).join('');
}

function buildLogCard(log, idx) {
    const time = Format.dateShort(log.created_at) + ' · '
               + new Date(log.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });

    const ip = log.ip_address
        ? `<span class="log-ip">${escHtml(log.ip_address)}</span>`
        : `<span class="log-ip text-muted">—</span>`;

    const entityPill = log.entity
        ? `<span class="log-entity-pill">${escHtml(log.entity)}${log.entity_id ? ' #' + log.entity_id : ''}</span>`
        : '';

    const detail = log.detail_snippet
        ? `<div class="log-detail mt-1">${escHtml(log.detail_snippet)}</div>`
        : '';

    const actorRole = log.user_role
        ? `<span class="text-xs text-muted fw-500">${formatRole(log.user_role)}</span>`
        : '';

    return `
    <div class="log-card" style="animation-delay: ${idx * 0.03}s">

        <!-- Badge Column -->
        <div class="log-badge-col">
            <div class="log-action-badge ${escHtml(log.badge_color)}">
                <i class="fas ${escHtml(log.icon)}"></i>
                ${escHtml(log.category)}
            </div>
            ${actorRole}
        </div>

        <!-- Body Column -->
        <div class="log-body">
            <div class="log-description">
                ${entityPill}${escHtml(log.description)}
            </div>
            ${detail}
        </div>

        <!-- Meta Column -->
        <div class="log-meta-col">
            <div class="log-time"><i class="fas fa-clock me-1 opacity-50"></i>${escHtml(time)}</div>
            ${ip}
        </div>

    </div>`;
}

function renderPagination(meta) {
    if (!meta) return;

    currentPage  = meta.currentPage;
    totalPages   = meta.totalPages;

    const container = document.getElementById('paginationContainer');
    container.style.display = totalPages > 1 ? 'flex' : 'none';

    document.getElementById('pagePrevBtn').disabled = currentPage <= 1;
    document.getElementById('pageNextBtn').disabled = currentPage >= totalPages;

    // Page number buttons (show up to 7 numbers with ellipsis)
    const numbers = document.getElementById('pageNumbers');
    numbers.innerHTML = buildPageNumbers(currentPage, totalPages)
        .map(p => p === '…'
            ? `<button class="page-num-btn ellipsis" disabled>…</button>`
            : `<button class="page-num-btn ${p === currentPage ? 'active' : ''}"
                       onclick="changePage(${p})">${p}</button>`)
        .join('');
}

function buildPageNumbers(current, total) {
    if (total <= 7) return Array.from({ length: total }, (_, i) => i + 1);
    const pages = [];
    if (current <= 4) {
        pages.push(1, 2, 3, 4, 5, '…', total);
    } else if (current >= total - 3) {
        pages.push(1, '…', total - 4, total - 3, total - 2, total - 1, total);
    } else {
        pages.push(1, '…', current - 1, current, current + 1, '…', total);
    }
    return pages;
}

// ── User Actions ───────────────────────────────────────────────────────────
function changePage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    fetchLogs();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function resetFilters() {
    document.getElementById('filterSearch').value   = '';
    document.getElementById('filterAction').value   = '';
    document.getElementById('filterDateFrom').value = '';
    document.getElementById('filterDateTo').value   = '';
    currentPage = 1;
    fetchLogs();
}

async function exportLogs() {
    const params = new URLSearchParams({
        search:      document.getElementById('filterSearch').value.trim(),
        action_type: document.getElementById('filterAction').value,
        date_from:   document.getElementById('filterDateFrom').value,
        date_to:     document.getElementById('filterDateTo').value,
        limit:       9999,
        page:        1,
    });
    for (const [k, v] of [...params.entries()]) if (!v) params.delete(k);

    try {
        const data = await Api.get(BASE + 'src/api/audit_logs.php?' + params.toString());
        if (!data.logs?.length) { Toast.error('No data to export.'); return; }

        const rows = [['ID','Action','Category','Entity','Entity ID','User','Description','IP','Timestamp']];
        data.logs.forEach(l => rows.push([
            l.id, l.action, l.category, l.entity, l.entity_id ?? '',
            l.user_name, l.description.replace(/,/g, ';'), l.ip_address ?? '', l.created_at
        ]));

        const csv = rows.map(r => r.map(c => `"${String(c).replace(/"/g,'""')}"`).join(',')).join('\n');
        const blob = new Blob([csv], { type: 'text/csv' });
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), {
            href: url, download: `activity_log_${new Date().toISOString().split('T')[0]}.csv`
        });
        a.click(); URL.revokeObjectURL(url);
        Toast.success(`Exported ${data.logs.length} records.`);
    } catch (e) {
        Toast.error('Export failed.');
    }
}

// ── UI Helpers ─────────────────────────────────────────────────────────────
function showSkeleton() {
    document.getElementById('logSkeleton').style.display = 'block';
    document.getElementById('logList').style.display     = 'none';
    document.getElementById('logEmptyState').style.display = 'none';
}
function hideSkeleton() {
    document.getElementById('logSkeleton').style.display = 'none';
}
function escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function formatRole(role) {
    const map = { admin:'Admin', frontdesk:'Front Desk', staff:'Staff' };
    return map[role] ?? role;
}

// Disable the default table search since we have our own search
initTableSearch = null;
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
