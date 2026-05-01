<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('staff');

$pageTitle    = 'My Dashboard';
$pageSubtitle = 'View jobs, schedule & manage leave requests';
$activePage   = 'dashboard';

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

.job-card {
    background: var(--surface-1);
    border: 1px solid var(--glass-sep);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    transition: all 0.2s ease;
    cursor: pointer;
}

.job-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
    border-color: var(--label-4);
}

.job-card.urgent {
    border-left: 4px solid #FF9500;
    background: linear-gradient(135deg, rgba(255, 149, 0, 0.04), transparent);
}

.job-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
}

.job-card-role {
    font-size: 14px;
    font-weight: 700;
    color: var(--label);
    margin-bottom: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.job-card-meta {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
    color: var(--label-2);
    margin-bottom: 12px;
}

    .job-meta-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--label-2); margin-bottom: 6px; }
    .job-meta-item i { width: 14px; text-align: center; color: var(--label-3); font-size: 12px; }

    /* Compact Grid Layout */
    .job-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 16px;
    }
    .job-card.compact {
        padding: 16px;
        margin-bottom: 0;
    }
    .job-card.compact .job-card-header {
        margin-bottom: 12px;
    }
    .job-card.compact .job-card-meta {
        display: grid;
        grid-template-columns: 1fr;
        gap: 4px;
        margin-bottom: 0;
    }
    .job-card.compact .job-meta-item {
        margin-bottom: 0;
        font-size: 12px;
    }

.job-card-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 0.5px solid var(--glass-sep);
}

.job-card-actions .btn {
    flex: 1;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    transition: all 0.15s ease;
}

.job-card-actions .btn:hover {
    transform: scale(1.02);
}

.leave-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; border-radius: 99px; font-size: 10px;
    font-weight: 700; padding: 0 5px;
}
.leave-badge.pending  { background: rgba(255,159,10,0.15); color: #9A5400; }
.leave-badge.approved { background: rgba(48,209,88,0.15);  color: #1A7A32; }
.leave-badge.rejected { background: rgba(255,59,48,0.12);  color: #9B1C1C; }

/* FullCalendar Customization */
.fc {
    font-family: -apple-system, 'SF Pro Text', 'SF Pro Display', 'Helvetica Neue', 'Inter', Arial, sans-serif;
}

.fc .fc-button-primary {
    background-color: var(--sys-green);
    border-color: var(--sys-green);
    color: #fff;
    font-weight: 600;
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 8px;
}

.fc .fc-button-primary:hover {
    background-color: var(--sys-green-dark);
    border-color: var(--sys-green-dark);
}

.fc .fc-button-primary.fc-button-active {
    background-color: var(--sys-green-dark);
    border-color: var(--sys-green-dark);
}

.fc .fc-daygrid-day-number {
    padding: 8px 4px;
    font-size: 12px;
    font-weight: 600;
}

.fc .fc-daygrid-day {
    border-color: var(--glass-sep);
}

.fc .fc-daygrid-day.fc-day-other {
    background-color: var(--bg-primary);
}

.fc .fc-daygrid-day.fc-day-today {
    background-color: rgba(48, 209, 88, 0.05);
}

.fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
    color: var(--sys-green);
}

.fc .fc-col-header-cell {
    background-color: var(--surface-1);
    padding: 12px 0;
    font-weight: 700;
    color: var(--label);
    border-color: var(--glass-sep);
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: 0.5px;
}

.fc .fc-daygrid-day-frame {
    position: relative;
}

.fc-daygrid-event {
    margin: 2px 0;
    padding: 0 !important;
}

.fc .fc-daygrid-event-harness {
    padding: 1px;
}

.fc .fc-event {
    all: unset;
}

.fc-popover {
    border-radius: 12px;
    border: 0.5px solid var(--glass-sep);
    background: var(--glass-ultra);
    backdrop-filter: var(--glass-blur);
    box-shadow: var(--shadow-xl);
}

.fc-popover-header {
    background: var(--surface-2);
    padding: 12px;
    border-bottom: 0.5px solid var(--glass-sep);
    font-weight: 700;
    color: var(--label);
}

.fc-popover-body {
    padding: 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .job-card {
        padding: 12px;
    }
    
    .job-card-meta {
        font-size: 12px;
        gap: 6px;
    }
    
    .fc .fc-daygrid-day-number {
        padding: 6px 2px;
        font-size: 11px;
    }
    
    .fc .fc-button-primary {
        padding: 4px 8px;
        font-size: 11px;
    }
}
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
    <button class="job-tab" id="tab-btn-inventory" onclick="switchTab('inventory', this)">
        <i class="fas fa-boxes-stacked"></i> Inventory Dispatch
    </button>
</div>

<!-- ── TAB: Pending Job Offers ── -->
<div class="job-tab-panel active" id="tab-pending">
    <div id="pendingJobs"><div class="spinner"></div></div>
</div>

<!-- ── TAB: My Schedule ── -->
<div class="job-tab-panel" id="tab-accepted">
    <div class="card mb-4">
        <div class="card-header">
            <div>
                <div class="card-title">My Event Schedule</div>
                <div class="card-subtitle">Click an event to view details</div>
            </div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; font-size:11px;">
                <div style="display:flex; gap:4px; align-items:center;"><span style="width:8px;height:8px;border-radius:50%;background:#30D158;"></span><span>Head Cook</span></div>
                <div style="display:flex; gap:4px; align-items:center;"><span style="width:8px;height:8px;border-radius:50%;background:#0A84FF;"></span><span>Waiter</span></div>
                <div style="display:flex; gap:4px; align-items:center;"><span style="width:8px;height:8px;border-radius:50%;background:#FF9500;"></span><span>Kitchen Staff</span></div>
                <div style="display:flex; gap:4px; align-items:center;"><span style="width:8px;height:8px;border-radius:50%;background:#A2845E;"></span><span>Other</span></div>
            </div>
        </div>
        <div class="card-body" style="padding:16px;">
            <div id="calendar"></div>
        </div>
    </div>
    
    <!-- Calendar Legend & Controls -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; font-size:12px; color:var(--label-3);">
        <div id="calendarStats">
            <span id="upcomingEventCount">0 upcoming events</span>
        </div>
        <button class="btn btn-sm btn-outline-secondary" onclick="scrollToToday()" style="padding:4px 12px; font-size:12px;">
            <i class="fas fa-circle-dot me-1"></i> Jump to Today
        </button>
    </div>
    
    <h5 style="font-size:15px; font-weight:700; color:var(--label-1); margin-bottom:12px;">📋 Upcoming Job Details</h5>
    <div id="acceptedJobs" class="job-grid"><div class="spinner"></div></div>
</div>

<!-- ── TAB: Leave Requests ── -->
<div class="job-tab-panel" id="tab-leaves">
    <div class="card mb-3">
        <div class="card-header">
            <div><div class="card-title"><i class="fas fa-plus-circle me-2" style="color:var(--sys-green)"></i>Request Leave</div></div>
        </div>
        <div class="card-body">
            <form id="leaveForm" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
                <div class="form-group" style="flex:1; min-width:120px; max-width:100%; margin-bottom:0;">
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
        <div class="table-wrapper table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
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
    <div id="historyJobs" class="job-grid"><div class="spinner"></div></div>
</div>

<!-- ── TAB: Inventory ── -->
<div class="job-tab-panel" id="tab-inventory">
    <div class="row">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Select Event</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <select class="form-control" id="invEventSelect" onchange="loadInventoryForEvent()">
                            <option value="">Choose an upcoming event…</option>
                        </select>
                    </div>
                    <div id="invBookingDetails" class="mt-3" style="display:none;">
                        <div style="background:var(--surface-2);border:1px solid var(--border);border-radius:var(--radius-sm);padding:14px;">
                            <div class="mb-2">
                                <div class="text-xs text-muted text-bold">CLIENT</div>
                                <div class="fw-600" id="inv-detail-client">—</div>
                            </div>
                            <div class="mb-2">
                                <div class="text-xs text-muted text-bold">EVENT DATE</div>
                                <div class="fw-600" id="inv-detail-date">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="card" id="invActionCard" style="display:none;">
                <div class="card-header" style="justify-content: space-between; display: flex; align-items: center;">
                    <div class="card-title">Dispatch / Return Log</div>
                    <div class="job-tabs" style="margin-bottom:0; border-bottom:none;">
                        <button class="job-tab active" id="inv-subtab-dispatch" onclick="switchInvSubTab('dispatch')">Ingress (Out)</button>
                        <button class="job-tab" id="inv-subtab-return" onclick="switchInvSubTab('return')">Egress (In)</button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Dispatch Panel -->
                    <div id="inv-panel-dispatch">
                        <div style="font-size:12px; color:var(--label-3); margin-bottom:12px;">Add items being sent to the event location.</div>
                        <div id="dispatchRows"></div>
                        <button class="btn btn-outline-secondary btn-sm mt-2" onclick="addDispatchRow()">
                            <i class="fas fa-plus me-1"></i> Add Item
                        </button>
                        <hr class="my-4">
                        <div class="d-flex justify-content-end">
                            <button class="btn btn-primary" id="btnSaveDispatch" onclick="saveDispatch()">
                                <i class="fas fa-truck-loading me-1"></i> Record Dispatch
                            </button>
                        </div>
                    </div>
                    <!-- Return Panel -->
                    <div id="inv-panel-return" style="display:none;">
                        <div style="font-size:12px; color:var(--label-3); margin-bottom:12px;">Record items returned from the event. Discrepancies will be logged as breakages.</div>
                        <div class="table-wrapper table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th style="width:100px;">Out</th>
                                        <th style="width:100px;">In (Return)</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody id="returnTableBody">
                                    <tr><td colspan="4" class="text-center">No items dispatched yet.</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <hr class="my-4">
                        <div class="d-flex justify-content-end">
                            <button class="btn btn-success" id="btnSaveReturn" onclick="saveReturn()">
                                <i class="fas fa-clipboard-check me-1"></i> Record Return & Finalize
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div id="invEmptyState">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-boxes-stacked"></i></div>
                    <h3>No event selected</h3>
                    <p>Select an event from the left to manage inventory dispatch.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
let allJobs = [];
let calendar;

// Role-based color scheme
const roleColors = {
    'head_cook': '#30D158',      // System green
    'waiter': '#0A84FF',          // System blue
    'kitchen_staff': '#FF9500',   // System orange
    'dishwasher': '#FF3B30',      // System red
    'default': '#A2845E'          // Brown
};

function getRoleColor(role) {
    const normalized = (role || 'default').toLowerCase().replace(/\s+/g, '_');
    return roleColors[normalized] || roleColors.default;
}

function initCalendar(acceptedJobs) {
    if (calendar) {
        calendar.destroy();
    }
    const calendarEl = document.getElementById('calendar');
    
    // Update event count
    document.getElementById('upcomingEventCount').textContent = acceptedJobs.length + ' upcoming event' + (acceptedJobs.length !== 1 ? 's' : '');
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listWeek'
        },
        height: 'auto',
        contentHeight: 'auto',
        expandRows: false,
        weekText: 'W',
        dayMaxEventRows: 3,
        moreLinkText: function(info) {
            return '+' + info.num + ' more';
        },
        moreLinkClick: 'popover',
        events: acceptedJobs.map(j => ({
            title: j.role_required + ' · ' + j.client_name,
            start: j.event_date + (j.event_time ? 'T' + j.event_time : ''),
            backgroundColor: getRoleColor(j.role_required),
            borderColor: getRoleColor(j.role_required),
            textColor: '#fff',
            extendedProps: { 
                ...j,
                displayTime: j.event_time ? Format.time(j.event_time) : 'All day',
                displayLocation: j.event_location || 'TBA',
                displayPax: j.pax_count + ' guests'
            }
        })),
        eventDidMount: function(info) {
            // Add custom styling to event
            const el = info.el;
            el.style.cursor = 'pointer';
            el.style.fontWeight = '600';
            el.style.fontSize = '11px';
            el.style.padding = '2px 4px';
            el.style.borderRadius = '4px';
            el.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
            el.style.transition = 'all 0.15s ease';
            
            // Hover effect
            el.addEventListener('mouseenter', function() {
                el.style.transform = 'scale(1.05)';
                el.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                el.style.zIndex = '10';
            });
            el.addEventListener('mouseleave', function() {
                el.style.transform = 'scale(1)';
                el.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                el.style.zIndex = '1';
            });
        },
        eventClick: function(info) {
            const job = info.event.extendedProps;
            
            // Highlight and scroll to the job card below
            const jobCards = document.querySelectorAll('.job-card');
            jobCards.forEach(card => {
                card.style.borderColor = 'var(--glass-sep)';
                card.style.borderWidth = '1px';
            });
            
            // Find and highlight matching job
            const matchingCard = Array.from(jobCards).find(card => 
                card.textContent.includes('Job #' + job.id)
            );
            
            if (matchingCard) {
                matchingCard.style.borderColor = getRoleColor(job.role_required);
                matchingCard.style.borderWidth = '2px';
                matchingCard.style.backgroundColor = 'rgba(' + 
                    getRoleColor(job.role_required).replace('#', '').match(/\w\w/g)
                    .map(x => parseInt(x, 16)).join(', ') + ', 0.05)';
                matchingCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            
            // Show detailed popover
            const roleColor = getRoleColor(job.role_required);
            const detailsHtml = `
            <div style="background: linear-gradient(135deg, ${roleColor}, ${roleColor}dd); 
                        padding: 12px; border-radius: 12px; color: #fff; margin-bottom: 12px;">
                <div style="font-weight: 700; font-size: 14px; margin-bottom: 8px;">
                    📋 ${esc(job.role_required.toUpperCase())}
                </div>
                <div style="font-size: 12px; line-height: 1.6; opacity: 0.95;">
                    <div><strong>Client:</strong> ${esc(job.client_name)}</div>
                    <div><strong>Date:</strong> ${Format.dateShort(job.event_date)} at ${job.displayTime}</div>
                    <div><strong>Location:</strong> ${esc(job.displayLocation)}</div>
                    <div><strong>Party Size:</strong> ${job.displayPax}</div>
                </div>
            </div>`;
            
            Swal.fire({
                title: `Event on ${Format.dateShort(job.event_date)}`,
                html: detailsHtml + (job.notes ? `<p style="text-align: left; font-size: 12px; color: var(--label-2); margin-top: 12px;"><strong>Notes:</strong> ${esc(job.notes)}</p>` : ''),
                icon: 'info',
                confirmButtonText: 'Got it',
                confirmButtonColor: roleColor,
                background: 'rgba(255,255,255,0.95)',
                backdrop: 'rgba(0,0,0,0.4)',
                didOpen: (modal) => {
                    modal.style.borderRadius = '16px';
                }
            });
        },
        datesSet: function(info) {
            // Update the "Jump to Today" button state
            const isCurrentMonth = new Date().toDateString() === new Date(info.start).toDateString() || 
                                  (new Date() >= info.start && new Date() <= info.end);
        }
    });
    
    calendar.render();
    
    // Auto-highlight first event on load
    if (acceptedJobs.length > 0) {
        const firstJob = acceptedJobs[0];
        const jobCard = Array.from(document.querySelectorAll('.job-card')).find(card => 
            card.textContent.includes('Job #' + firstJob.id)
        );
        if (jobCard) {
            jobCard.style.borderColor = getRoleColor(firstJob.role_required);
            jobCard.style.borderWidth = '2px';
        }
    }
}

function scrollToToday() {
    if (calendar) {
        calendar.today();
    }
}

function switchTab(tab, el) {
    document.querySelectorAll('.job-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.job-tab-panel').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('tab-' + tab).classList.add('active');

    if (tab === 'leaves') loadMyLeaves();
    if (tab === 'accepted' && calendar) {
        setTimeout(() => calendar.render(), 100);
    }
    if (tab === 'inventory') loadInventoryEvents();
}

// ── INVENTORY DISPATCH ─────────────────────────────────────────────
let equipmentList = [];
let dispatchedItems = [];
let invSelectedBookingId = null;

async function loadInventoryEvents() {
    const accepted = allJobs.filter(j => j.status === 'accepted');
    const sel = document.getElementById('invEventSelect');
    sel.innerHTML = '<option value="">Choose an upcoming event…</option>' +
        accepted.map(j => `<option value="${j.booking_id}">#${j.booking_id} — ${esc(j.client_name)} (${Format.dateShort(j.event_date)})</option>`).join('');
    
    // Pre-load equipment list if empty
    if (equipmentList.length === 0) {
        const d = await Api.get(BASE + 'src/api/inventory.php');
        equipmentList = d.equipment || [];
    }
}

function loadInventoryForEvent() {
    const bid = document.getElementById('invEventSelect').value;
    invSelectedBookingId = bid;
    
    if (!bid) {
        document.getElementById('invBookingDetails').style.display = 'none';
        document.getElementById('invActionCard').style.display = 'none';
        document.getElementById('invEmptyState').style.display = 'block';
        return;
    }

    const job = allJobs.find(j => j.booking_id == bid);
    document.getElementById('inv-detail-client').textContent = job.client_name;
    document.getElementById('inv-detail-date').textContent = Format.dateShort(job.event_date);
    document.getElementById('invBookingDetails').style.display = 'block';
    document.getElementById('invActionCard').style.display = 'block';
    document.getElementById('invEmptyState').style.display = 'none';
    
    refreshInventoryList();
}

async function refreshInventoryList() {
    try {
        const d = await Api.get(BASE + 'src/api/inventory_dispatch.php', { booking_id: invSelectedBookingId });
        dispatchedItems = d.items || [];
        renderReturnTable();
        
        // If items exist, default to Return tab, else Dispatch
        if (dispatchedItems.length > 0) {
            switchInvSubTab('return');
        } else {
            switchInvSubTab('dispatch');
            document.getElementById('dispatchRows').innerHTML = '';
            addDispatchRow();
        }
    } catch(e) { Toast.error(e.message); }
}

function switchInvSubTab(sub) {
    document.getElementById('inv-subtab-dispatch').classList.toggle('active', sub === 'dispatch');
    document.getElementById('inv-subtab-return').classList.toggle('active', sub === 'return');
    document.getElementById('inv-panel-dispatch').style.display = sub === 'dispatch' ? 'block' : 'none';
    document.getElementById('inv-panel-return').style.display = sub === 'return' ? 'block' : 'none';
}

function addDispatchRow() {
    const container = document.getElementById('dispatchRows');
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 mb-2 dispatch-row';
    const options = equipmentList.map(e => `<option value="${e.id}" data-stock="${e.current_stock}">${esc(e.name)} (Stock: ${e.current_stock})</option>`).join('');
    row.innerHTML = `
        <select class="form-control inv-eid" style="flex:3;">
            <option value="">Select Item…</option>
            ${options}
        </select>
        <input type="number" class="form-control inv-qty" placeholder="Qty" style="flex:1;" min="1" value="1">
        <input type="text" class="form-control inv-notes" placeholder="Notes" style="flex:2;">
        <button class="btn btn-outline-danger btn-sm" onclick="this.parentElement.remove()">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(row);
}

function renderReturnTable() {
    const tbody = document.getElementById('returnTableBody');
    if (dispatchedItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No items dispatched yet.</td></tr>';
        return;
    }

    tbody.innerHTML = dispatchedItems.map(item => `
        <tr class="return-row" data-id="${item.id}" data-out="${item.quantity_out}">
            <td>
                <div class="fw-600">${esc(item.equipment_name)}</div>
                <div class="text-xs text-muted">Dispatched by ${esc(item.dispatched_by_name)}</div>
            </td>
            <td><span class="badge badge-secondary">${item.quantity_out} ${item.unit}</span></td>
            <td>
                <input type="number" class="form-control form-control-sm inv-qty-in" 
                    value="${item.quantity_in !== null ? item.quantity_in : item.quantity_out}" 
                    max="${item.quantity_out}" min="0">
            </td>
            <td>
                <input type="text" class="form-control form-control-sm inv-ret-notes" 
                    placeholder="e.g. Broken 2" value="${esc(item.return_notes || '')}">
            </td>
        </tr>
    `).join('');
}

async function saveDispatch() {
    
    const rows = document.querySelectorAll('.dispatch-row');
    const items = [];
    const errors = [];
    rows.forEach(r => {
        const sel = r.querySelector('.inv-eid');
        const eid = sel.value;
        const qty = parseInt(r.querySelector('.inv-qty').value);
        const notes = r.querySelector('.inv-notes').value;
        
        if (eid && qty > 0) {
            const opt = sel.options[sel.selectedIndex];
            const stock = parseInt(opt.dataset.stock);
            if (qty > stock) {
                const name = opt.text.split(' (Stock:')[0];
                errors.push(`Insufficient stock for "${name}". Available: ${stock}`);
            }
            items.push({ equipment_id: eid, quantity: qty, notes });
        }
    });

    if (errors.length > 0) return Toast.error(errors.join('<br>'));
    if (items.length === 0) return Toast.error('Please add at least one item.');

    const btn = document.getElementById('btnSaveDispatch');
    Form.setLoading(btn, true);
    try {
        await Api.post(BASE + 'src/api/inventory_dispatch.php', { booking_id: invSelectedBookingId, items });
        Toast.success('Dispatch recorded!');
        document.getElementById('dispatchRows').innerHTML = '';
        refreshInventoryList();
    } catch(e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

async function saveReturn() {
    const rows = document.querySelectorAll('.return-row');
    const returns = [];
    let hasDiscrepancy = false;

    rows.forEach(r => {
        const id = r.dataset.id;
        const qtyOut = parseInt(r.dataset.out);
        const qtyIn = parseInt(r.querySelector('.inv-qty-in').value);
        const notes = r.querySelector('.inv-ret-notes').value;
        
        returns.push({ id, quantity_in: qtyIn, notes });
        if (qtyIn < qtyOut) hasDiscrepancy = true;
    });

    if (hasDiscrepancy) {
        const { value: chargeTo } = await Swal.fire({
            title: 'Discrepancy Detected',
            text: 'Some items were not returned. Who should be charged for the loss?',
            icon: 'warning',
            input: 'select',
            inputOptions: {
                'client': 'Charge to Client (Adds to balance)',
                'staff': 'Charge to Staff',
                'business': 'Business Loss'
            },
            inputPlaceholder: 'Select responsible party',
            showCancelButton: true,
            confirmButtonColor: '#FF9500'
        });
        
        if (!chargeTo) return; // Cancelled
        returns.forEach(ret => ret.charge_to = chargeTo);
    }

    const btn = document.getElementById('btnSaveReturn');
    Form.setLoading(btn, true);
    try {
        const res = await Api.put(BASE + 'src/api/inventory_dispatch.php', { booking_id: invSelectedBookingId, returns });
        Toast.success(res.message);
        refreshInventoryList();
    } catch(e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
}

function renderJob(j, showActions = true, compact = false) {
    const statusColor = { pending: 'badge-pending', accepted: 'badge-accepted', declined: 'badge-cancelled' };
    return `
    <div class="job-card ${compact ? 'compact' : ''} ${j.status === 'pending' ? 'urgent' : ''}">
        <div class="job-card-header">
            <div>
                <div class="job-card-role" style="${compact ? 'font-size:13px;' : ''}">${esc(j.role_required)}</div>
                <div class="text-xs text-muted">Job #${j.id}</div>
            </div>
            <span class="badge ${statusColor[j.status] || ''}">${j.status.charAt(0).toUpperCase() + j.status.slice(1)}</span>
        </div>
        <div class="job-card-meta">
            <div class="job-meta-item"><i class="fas fa-user"></i><span>${esc(j.client_name)}</span></div>
            <div class="job-meta-item"><i class="fas fa-calendar"></i><span>${Format.dateShort(j.event_date)}${j.event_time ? ' · ' + Format.time(j.event_time) : ''}</span></div>
            <div class="job-meta-item"><i class="fas fa-location-dot"></i><span class="text-truncate">${esc(j.event_location || '—')}</span></div>
            ${!compact ? `<div class="job-meta-item"><i class="fas fa-users"></i><span>${j.pax_count} guests</span></div>` : ''}
        </div>
        ${!compact && j.notes ? `<div class="text-sm text-muted mb-3 mt-2"><i class="fas fa-note-sticky me-1"></i>${esc(j.notes)}</div>` : ''}
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
        const d = await Api.get(BASE + 'src/api/dispatching.php', { my_jobs: 1 });
        allJobs = d.job_orders || [];

        const pending  = allJobs.filter(j => j.status === 'pending');
        const accepted = allJobs.filter(j => j.status === 'accepted' && new Date(j.event_date) >= new Date());
        const history  = allJobs.filter(j => j.status === 'declined' || new Date(j.event_date) < new Date());

        document.getElementById('pendingCount').textContent = pending.length || '';

        const emptyState = (icon, title, msg) =>
            `<div class="empty-state"><div class="empty-state-icon"><i class="fas fa-${icon}"></i></div><h3>${title}</h3><p>${msg}</p></div>`;

        document.getElementById('pendingJobs').innerHTML  = pending.length  ? pending.map(j => renderJob(j, true)).join('')  : emptyState('inbox','No Pending Offers','No new job offers at the moment.');
        document.getElementById('acceptedJobs').innerHTML = accepted.length ? accepted.map(j => renderJob(j, false, true)).join('') : emptyState('calendar-check','No Upcoming Jobs','You have no accepted upcoming events.');
        
        initCalendar(accepted);
        
        document.getElementById('historyJobs').innerHTML  = history.length  ? history.map(j => renderJob(j, false, true)).join('')  : emptyState('history','No History','Past jobs will appear here.');
    } catch (e) {
        document.getElementById('pendingJobs').innerHTML = `<div class="empty-state"><p>Failed to load jobs. Please refresh.</p></div>`;
    }
}

async function respond(jobId, status) {
    if (!await confirmDialog(`${status === 'accepted' ? 'Accept' : 'Decline'} this job offer?`)) return;
    try {
        await Api.put(BASE + 'src/api/dispatching.php', { id: jobId, status });
        Toast.success(status === 'accepted' ? '✓ Job accepted! See you at the event.' : 'Job declined.');
        await loadJobs();
    } catch (e) { Toast.error(e.message); }
}

// ── LEAVE REQUESTS ─────────────────────────────────────────────────
async function loadMyLeaves() {
    try {
        const d = await Api.get(BASE + 'src/api/leave.php', { my_leaves: 1 });
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
        await Api.post(BASE + 'src/api/leave.php', data);
        Toast.success('Leave request submitted! Awaiting admin approval.');
        this.reset();
        loadMyLeaves();
    } catch(e) { Toast.error(e.message); }
    Form.setLoading(btn, false);
});

async function cancelLeave(id) {
    if (!await confirmDialog('Cancel this leave request?')) return;
    try {
        await Api.delete(BASE + 'src/api/leave.php', { id });
        Toast.success('Leave request cancelled.');
        loadMyLeaves();
    } catch(e) { Toast.error(e.message); }
}

loadJobs();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

