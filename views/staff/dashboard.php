<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('staff');

$pageTitle    = 'My Dashboard';
$pageSubtitle = 'View jobs, schedule & manage leave requests';
$activePage   = 'staff_dashboard';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';

$user = getCurrentUser();
?>

<style>
.job-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--glass-sep); margin-bottom: 20px; overflow-x: auto; }
.job-tab {
    flex: none;
    text-align: center;
    padding: 8px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--label-3);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    margin-bottom: -1px;
    transition: var(--tr);
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    background: none;
    border-top: none;
    border-left: none;
    border-right: none;
}
.job-tab.active { color: var(--sys-green-dark); border-bottom-color: var(--sys-green); }
.job-tab-panel { display: none; }
.job-tab-panel.active { display: block; }

.leave-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; border-radius: 99px; font-size: 10px;
    font-weight: 700; padding: 0 5px;
}
.leave-badge.pending  { background: rgba(255,159,10,0.15); color: #9A5400; }
.leave-badge.approved { background: rgba(48,209,88,0.15);  color: #1A7A32; }
.leave-badge.rejected { background: rgba(255,59,48,0.12);  color: #9B1C1C; }
</style>

<!-- Tabs -->
<div class="job-tabs">
    <button class="job-tab active" id="tab-btn-pending"  onclick="switchTab('pending',  this)">
        <i class="fas fa-inbox"></i> Job Offers <span class="badge badge-pending ms-1" id="pendingCount"></span>
    </button>
    <button class="job-tab" id="tab-btn-accepted" onclick="switchTab('accepted', this)">
        <i class="fas fa-calendar-check"></i> My Schedule
    </button>
    <button class="job-tab" id="tab-btn-leaves" onclick="switchTab('leaves', this)">
        <i class="fas fa-umbrella-beach"></i> Leave Requests
    </button>
    <button class="job-tab" id="tab-btn-history" onclick="switchTab('history', this)">
        <i class="fas fa-history"></i> History
    </button>
</div>

<!-- ── TAB: Pending Job Offers ── -->
<div class="job-tab-panel active" id="tab-pending">
    <div id="pendingJobs"><div class="spinner"></div></div>
</div>

<!-- ── TAB: My Schedule ── -->
<div class="job-tab-panel" id="tab-accepted">
    <div id="acceptedJobs"><div class="spinner"></div></div>
</div>

<!-- ── TAB: Leave Requests ── -->
<div class="job-tab-panel" id="tab-leaves">
    <div class="card mb-3">
        <div class="card-header">
            <div><div class="card-title"><i class="fas fa-plus-circle me-2" style="color:var(--sys-green)"></i>Request Leave</div></div>
        </div>
        <div class="card-body">
            <form id="leaveForm" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                    <label class="form-label">Date <span class="required">*</span></label>
                    <input type="date" class="form-control" name="leave_date" id="leaveDate" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                </div>
                <div class="form-group" style="flex:3;min-width:200px;margin-bottom:0;">
                    <label class="form-label">Reason</label>
                    <input type="text" class="form-control" name="reason" placeholder="e.g. Personal matter, medical appointment…">
                </div>
                <button type="submit" class="btn btn-primary" id="leaveSubmitBtn" style="height:40px;">
                    <i class="fas fa-paper-plane"></i> Submit
                </button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <div class="card-title">My Leave Requests</div>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Leave Date</th>
                        <th>Reason</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th class="td-actions">Action</th>
                    </tr>
                </thead>
                <tbody id="leaveBody"><tr><td colspan="5"><div class="spinner"></div></td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── TAB: History ── -->
<div class="job-tab-panel" id="tab-history">
    <div id="historyJobs"><div class="spinner"></div></div>
</div>

<script>
const BASE = '<?= BASE_URL ?>';
let allJobs = [];

function switchTab(tab, el) {
    document.querySelectorAll('.job-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.job-tab-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');
    if (tab === 'leaves') loadMyLeaves();
}

function renderJob(j, showActions = true) {
    const statusColor = { pending: 'badge-pending', accepted: 'badge-accepted', declined: 'badge-cancelled' };
    return `
    <div class="job-card ${j.status === 'pending' ? 'urgent' : ''}">
        <div class="job-card-header">
            <div>
                <div class="job-card-role">${j.role_required}</div>
                <div class="text-sm text-muted">Job #${j.id}</div>
            </div>
            <span class="badge ${statusColor[j.status] || ''}">${j.status.charAt(0).toUpperCase() + j.status.slice(1)}</span>
        </div>
        <div class="job-card-meta">
            <div class="job-meta-item"><i class="fas fa-user"></i>${j.client_name}</div>
            <div class="job-meta-item"><i class="fas fa-calendar"></i>${Format.dateShort(j.event_date)}${j.event_time ? ' · ' + Format.time(j.event_time) : ''}</div>
            <div class="job-meta-item"><i class="fas fa-location-dot"></i>${j.event_location || '—'}</div>
            <div class="job-meta-item"><i class="fas fa-utensils"></i>${j.menu_name}</div>
            <div class="job-meta-item"><i class="fas fa-users"></i>${j.pax_count} guests</div>
        </div>
        ${j.notes ? `<div class="text-sm text-muted mb-3"><i class="fas fa-note-sticky me-1"></i>${j.notes}</div>` : ''}
        ${showActions && j.status === 'pending' ? `
        <div class="job-card-actions">
            <button class="btn btn-success" onclick="respond(${j.id}, 'accepted')">
                <i class="fas fa-check"></i> Accept
            </button>
            <button class="btn btn-outline-secondary" onclick="respond(${j.id}, 'declined')">
                <i class="fas fa-times"></i> Decline
            </button>
        </div>` : ''}
    </div>`;
}

async function loadJobs() {
    try {
        const d = await Api.get(BASE + '/src/api/dispatching.php', { my_jobs: 1 });
        allJobs = d.job_orders || [];

        const pending  = allJobs.filter(j => j.status === 'pending');
        const accepted = allJobs.filter(j => j.status === 'accepted' && new Date(j.event_date) >= new Date());
        const history  = allJobs.filter(j => j.status === 'declined' || new Date(j.event_date) < new Date());

        document.getElementById('pendingCount').textContent = pending.length || '';

        const emptyState = (icon, title, msg) =>
            `<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-${icon}"></i></div><h3>${title}</h3><p>${msg}</p></div>`;

        document.getElementById('pendingJobs').innerHTML  = pending.length  ? pending.map(j => renderJob(j, true)).join('')  : emptyState('inbox','No Pending Offers','No new job offers at the moment.');
        document.getElementById('acceptedJobs').innerHTML = accepted.length ? accepted.map(j => renderJob(j, false)).join('') : emptyState('calendar-check','No Upcoming Jobs','You have no accepted upcoming events.');
        document.getElementById('historyJobs').innerHTML  = history.length  ? history.map(j => renderJob(j, false)).join('')  : emptyState('history','No History','Past jobs will appear here.');
    } catch (e) {
        document.getElementById('pendingJobs').innerHTML = `<div class="empty-state"><p>Failed to load jobs. Please refresh.</p></div>`;
    }
}

async function respond(jobId, status) {
    if (!await confirmDialog(`${status === 'accepted' ? 'Accept' : 'Decline'} this job offer?`)) return;
    try {
        await Api.put(BASE + '/src/api/dispatching.php', { id: jobId, status });
        Toast.success(status === 'accepted' ? '✓ Job accepted! See you at the event.' : 'Job declined.');
        await loadJobs();
    } catch (e) { Toast.error(e.message); }
}

// ── LEAVE REQUESTS ─────────────────────────────────────────────────
async function loadMyLeaves() {
    try {
        const d = await Api.get(BASE + '/src/api/leave.php', { my_leaves: 1 });
        const leaves  = d.leaves || [];
        const tbody   = document.getElementById('leaveBody');

        if (!leaves.length) {
            tbody.innerHTML = `<tr><td colspan="5"><div class="table-empty"><i class="fas fa-calendar-check"></i><p>No leave requests yet.</p></div></td></tr>`;
            return;
        }

        tbody.innerHTML = leaves.map(l => {
            const badgeCls = { pending: 'pending', approved: 'accepted', rejected: 'cancelled' }[l.status] || '';
            const badgeLabel = { pending: '⏳ Pending', approved: '✅ Approved', rejected: '❌ Rejected' }[l.status] || l.status;
            const canCancel = l.status === 'pending';
            return `
            <tr>
                <td>${Format.dateShort(l.leave_date)}</td>
                <td class="text-sm text-muted">${l.reason || '—'}</td>
                <td class="text-xs text-muted">${Format.dateShort(l.created_at)}</td>
                <td><span class="badge badge-${badgeCls}">${badgeLabel}</span></td>
                <td class="td-actions">
                    ${canCancel ? `<button class="btn btn-danger btn-sm" onclick="cancelLeave(${l.id})">
                        <i class="fas fa-times"></i>
                    </button>` : '—'}
                </td>
            </tr>`;
        }).join('');
    } catch(e) { Toast.error('Failed to load leave requests.'); }
}

document.getElementById('leaveForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn  = document.getElementById('leaveSubmitBtn');
    const data = {
        leave_date: document.getElementById('leaveDate').value,
        reason:     this.querySelector('[name="reason"]').value.trim(),
    };
    Form.setLoading(btn, true, 'Submitting…');
    try {
        await Api.post(BASE + '/src/api/leave.php', data);
        Toast.success('Leave request submitted! Awaiting admin approval.');
        this.reset();
        loadMyLeaves();
    } catch(e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
});

async function cancelLeave(id) {
    if (!await confirmDialog('Cancel this leave request?')) return;
    try {
        await Api.delete(BASE + '/src/api/leave.php', { id });
        Toast.success('Leave request cancelled.');
        loadMyLeaves();
    } catch(e) { Toast.error(e.message); }
}

loadJobs();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
