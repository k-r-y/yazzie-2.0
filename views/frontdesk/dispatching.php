<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('frontdesk');

$pageTitle    = 'Staff Dispatching';
$pageSubtitle = 'Broadcast job offers to on-call staff';
$activePage   = 'dispatching';
$preloadId    = (int)($_GET['booking_id'] ?? 0);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row g-3">
    <!-- LEFT: Dispatch Form -->
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-bullhorn me-2 text-primary"></i>Create Job Orders</div>
            </div>
            <div class="card-body">
                <form id="dispatchForm">
                    <div class="form-group">
                        <label class="form-label">Select Event <span class="required">*</span></label>
                        <select class="form-control" name="booking_id" id="bookingSel" required onchange="loadExistingOrders()"></select>
                    </div>

                    <div id="bookingInfo" style="display:none;" class="mb-3">
                        <div style="background:var(--primary-light);border:1px solid rgba(200,80,30,0.2);border-radius:var(--radius-sm);padding:12px;">
                            <div class="fw-700 mb-1" id="di-client"></div>
                            <div class="text-sm" id="di-date"></div>
                            <div class="text-sm text-muted" id="di-loc"></div>
                            <div class="text-sm"><strong id="di-pax"></strong></div>
                        </div>
                        <!-- Auto-Suggest Banner -->
                        <div id="suggestBanner" style="display:none; margin-top:8px; background:rgba(48,209,88,0.08); border:1px solid rgba(48,209,88,0.25); border-radius:var(--radius-sm); padding:10px 14px;">
                            <div style="display:flex; align-items:center; gap:8px; margin-bottom:2px;">
                                <i class="fas fa-wand-magic-sparkles" style="color:#1A7A32;"></i>
                                <span class="fw-700 text-sm" style="color:#1A7A32;">Staff Recommendation</span>
                            </div>
                            <div class="text-xs" style="color:rgba(60,60,67,0.7);">
                                <span id="suggest-text"></span>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Role Required <span class="required">*</span></label>
                        <select class="form-control" name="role_required" id="roleReqSel" required onchange="renderStaffList()">
                            <option value="head_cook">👨‍🍳 Head Cook</option>
                            <option value="cook">🍳 Assistant Cook</option>
                            <option value="waiter" selected>🤵 Waiter</option>
                            <option value="server">🍽️ Server</option>
                            <option value="helper">🙋 Helper / Utility</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Select Staff to Notify <span class="required">*</span></label>
                        <div id="staffCheckboxes" style="max-height:220px;overflow-y:auto;border:1.5px solid var(--border);border-radius:var(--radius-sm);padding:8px;">
                            <div class="text-muted text-sm p-2">Select an event to view available staff…</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Special instructions for this job…"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-full" id="dispatchBtn">
                        <i class="fas fa-paper-plane"></i> Send Job Offer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: Roster -->
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Current Staffing Roster</div>
                <div class="card-subtitle" id="rosterTitle">Select an event to see the roster</div>
            </div>
            <div id="rosterContent">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-users"></i></div>
                    <h3>No Event Selected</h3>
                    <p>Choose a booking on the left to view and manage the staffing roster.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let confirmedBookings = [], availStaff = [], allJobs = [];

async function initDispatch() {
    try {
        const bd = await Api.get(BASE + 'src/api/bookings.php', {
            status: 'confirmed',
            from: new Date().toISOString().split('T')[0]
        });
        confirmedBookings = bd.bookings || [];

        // Build booking dropdown
        const sel = document.getElementById('bookingSel');
        sel.innerHTML = '<option value="">Choose a confirmed event…</option>' +
            confirmedBookings.map(b =>
                `<option value="${b.id}" ${b.id == <?= $preloadId ?> ? 'selected' : ''}>
                    #${b.id} — ${esc(b.client_name)} (${Format.dateShort(b.event_date)})
                </option>`
            ).join('');

        if (<?= $preloadId ?> > 0) await loadExistingOrders();
    } catch(e) { Toast.error('Failed to load initial data.'); }
}

function renderStaffList() {
    const div = document.getElementById('staffCheckboxes');
    if (!availStaff.length) {
        div.innerHTML = '<div class="text-muted text-sm p-2">No active staff found.</div>';
        return;
    }

    const selectedRole = document.getElementById('roleReqSel').value;
    
    // Filter to strictly matching staff only as requested, and NOT already dispatched
    const filteredStaff = availStaff.filter(s => s.job_class === selectedRole && !s.already_dispatched);

    if (!filteredStaff.length) {
        div.innerHTML = `<div class="text-muted text-sm p-3 text-center" style="border: 1px dashed var(--border); border-radius: 8px;">
            <i class="fas fa-user-slash mb-2 text-muted" style="font-size: 1.5rem;"></i><br>
            No staff available for the selected role.
        </div>`;
        return;
    }

    // Sort: Available first
    const sorted = [...filteredStaff].sort((a,b) => {
        const aAvail = a.availability === 'available';
        const bAvail = b.availability === 'available';
        if (aAvail !== bAvail) return aAvail ? -1 : 1;
        return a.name.localeCompare(b.name);
    });

    div.innerHTML = sorted.map(s => {
        const isAvail = s.availability === 'available';
        const opacity = isAvail ? '1' : '0.5';
        const disable = isAvail ? '' : 'disabled';
        
        const badge = isAvail ? '<span style="color:#1A7A32;font-size:10px;">🟢 Available</span>' : 
                     (s.availability === 'on_leave' ? '<span style="color:#9A5400;font-size:10px;">🟡 On Leave</span>' : '<span style="color:#FF3B30;font-size:10px;">⚫ Booked</span>');

        const jobClassLabel = { head_cook: '👨‍🍳 Head Cook', cook: '🍳 Cook', waiter: '🤵 Waiter', server: '🍽️ Server', helper: '🙋 Helper', any: '—' };
        const roleDisp = jobClassLabel[s.job_class] || (s.job_class ? s.job_class.replace('_',' ') : 'any');

        return `
        <label style="display:flex;align-items:center;gap:8px;padding:8px 8px;cursor:${isAvail?'pointer':'not-allowed'};border-radius:6px;opacity:${opacity};margin-bottom:2px;"
               onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
            <input type="checkbox" name="staff_ids" value="${s.id}" ${disable} style="width:16px;height:16px;accent-color:var(--primary);">
            <div style="flex:1;min-width:0;">
                <div class="fw-600 text-sm d-flex align-items-center justify-content-between">
                    <span>${s.name}</span>
                    ${badge}
                </div>
                <div class="text-xs text-muted d-flex justify-content-between mt-1">
                    <span>${s.phone || '—'}</span>
                    <span style="text-transform:capitalize;font-weight:600;color:var(--label-2);">${roleDisp}</span>
                </div>
            </div>
        </label>
        `;
    }).join('');
}

async function loadExistingOrders() {
    const bookingId = document.getElementById('bookingSel').value;
    const booking   = confirmedBookings.find(b => b.id == bookingId);

    // Show booking info
    if (booking) {
        document.getElementById('di-client').textContent = booking.client_name;
        document.getElementById('di-date').textContent   = '📅 ' + Format.dateShort(booking.event_date) + (booking.event_time ? ' · ' + Format.time(booking.event_time) : '');
        document.getElementById('di-loc').textContent    = '📍 ' + (booking.event_location || '—');
        document.getElementById('di-pax').textContent    = '👥 ' + booking.pax_count + ' guests';
        document.getElementById('bookingInfo').style.display = 'block';
        document.getElementById('rosterTitle').textContent   = booking.client_name + ' · ' + Format.dateShort(booking.event_date);
    } else {
        document.getElementById('bookingInfo').style.display = 'none';
        document.getElementById('suggestBanner').style.display = 'none';
    }

    if (!bookingId) {
        document.getElementById('rosterContent').innerHTML = `
            <div class="empty-state"><div class="empty-state-icon"><i class="fas fa-users"></i></div>
            <h3>No Event Selected</h3><p>Choose a booking to view the roster.</p></div>`;
        document.getElementById('staffCheckboxes').innerHTML = '<div class="text-muted text-sm p-2">Select an event to view available staff…</div>';
        return;
    }

    document.getElementById('rosterContent').innerHTML = '<div class="spinner"></div>';
    document.getElementById('staffCheckboxes').innerHTML = '<div class="spinner"></div>';
    try {
        // Use the suggest endpoint for staff + recommendations, roster from dispatching
        const [d, sg] = await Promise.all([
            Api.get(BASE + 'src/api/dispatching.php', { booking_id: bookingId }),
            Api.get(BASE + 'src/api/dispatching.php', { suggest: 1, booking_id: bookingId })
        ]);
        
        allJobs = d.job_orders || [];
        availStaff = sg.staff || [];
        
        // Show recommendation banner
        const banner = document.getElementById('suggestBanner');
        const suggestText = document.getElementById('suggest-text');
        if (sg.recommended) {
            const dispatched = allJobs.filter(j => j.status !== 'declined').length;
            const remaining  = Math.max(0, sg.recommended - dispatched);
            suggestText.innerHTML = `<strong>${sg.recommended} staff</strong> recommended for <strong>${booking.pax_count} pax</strong> ` +
                `(${sg.event_type}, ratio ${sg.ratio})` +
                (dispatched > 0 ? ` · <strong>${dispatched}</strong> already dispatched` : '') +
                (remaining > 0 ? ` · <span style="color:#C0392B;font-weight:700;">${remaining} more needed</span>` : ' · <span style="color:#1A7A32;font-weight:700;">✓ Fully staffed</span>');
            banner.style.display = 'block';
        } else {
            banner.style.display = 'none';
        }

        renderStaffList();
        
        // Auto-check top N available staff (only if no one is dispatched yet)
        if (sg.recommended && allJobs.length === 0) {
            let checked = 0;
            const checkboxes = document.querySelectorAll('[name="staff_ids"]');
            checkboxes.forEach(cb => {
                if (checked >= sg.recommended) return;
                if (!cb.disabled) {
                    cb.checked = true;
                    checked++;
                }
            });
        }

        if (!allJobs.length) {
            document.getElementById('rosterContent').innerHTML = `
                <div class="empty-state" style="padding:40px;">
                    <div class="empty-state-icon"><i class="fas fa-user-slash"></i></div>
                    <h3>No Staff Dispatched Yet</h3>
                    <p>Use the form on the left to send job offers to available staff.</p>
                </div>`;
            return;
        }

        const statusIcon = { pending: '⏳', accepted: '✅', declined: '❌' };
        const jobClassLabel = { head_cook: '👨‍🍳 Head Cook', cook: '🍳 Cook', waiter: '🤵 Waiter', server: '🍽️ Server', helper: '🙋 Helper', any: '—' };
        
        const rows = allJobs.map(jo => {
            const roleDisp = jobClassLabel[jo.role_required] || jo.role_required;
            return `
            <tr>
                <td class="td-name">${jo.staff_name}</td>
                <td class="text-sm text-muted">${jo.staff_phone || '—'}</td>
                <td style="font-weight:600;color:var(--label-2);">${roleDisp}</td>
                <td>${Format.jobBadge(jo.status)}</td>
                <td class="text-xs text-muted">${jo.responded_at ? Format.dateShort(jo.responded_at) : '—'}</td>
                <td>
                    <button class="btn btn-danger btn-sm" onclick="cancelJob(${jo.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `}).join('');

        document.getElementById('rosterContent').innerHTML = `
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead><tr><th>Staff</th><th>Phone</th><th>Role</th><th>Status</th><th>Responded</th><th>Cancel</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    } catch (e) { Toast.error('Failed to load roster.'); }
}

document.getElementById('dispatchForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const bookingId  = document.getElementById('bookingSel').value;
    const staffIds   = [...document.querySelectorAll('[name="staff_ids"]:checked')].map(el => el.value);
    const roleReq    = this.querySelector('[name="role_required"]').value;
    const notes      = this.querySelector('[name="notes"]').value;

    if (!bookingId) { Toast.warning('Please select an event.'); return; }
    if (!staffIds.length) { Toast.warning('Please select at least one staff member.'); return; }

    const btn = document.getElementById('dispatchBtn');
    Form.setLoading(btn, true, 'Sending…');
    try {
        const d = await Api.post(BASE + 'src/api/dispatching.php', {
            booking_id: bookingId, staff_ids: staffIds, role_required: roleReq, notes
        });
        Toast.success(d.message || 'Job orders dispatched!');
        this.querySelector('[name="notes"]').value = '';
        document.querySelectorAll('[name="staff_ids"]').forEach(el => el.checked = false);
        await loadExistingOrders();
    } catch (e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
});

async function cancelJob(id) {
    if (!await confirmDialog('Cancel this job order?')) return;
    try {
        await Api.delete(BASE + 'src/api/dispatching.php', { id });
        Toast.success('Job order cancelled.');
        await loadExistingOrders();
    } catch (e) { Toast.error(e.message); }
}

initDispatch();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
