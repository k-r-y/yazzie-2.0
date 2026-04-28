<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('staff');

$pageTitle    = 'Event Report';
$pageSubtitle = 'Submit post-event reports for your assigned bookings';
$activePage   = 'event_report';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row g-3">
    <!-- LEFT: Booking Selector -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-clipboard-check me-2 text-primary"></i>My Past Events</div>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Select a Booking</label>
                    <select class="form-control" id="bookingSelect" onchange="loadReportForm()">
                        <option value="">Choose a past event…</option>
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
                            <div class="text-xs text-muted text-bold">LOCATION</div>
                            <div class="fw-600" id="detail-location">—</div>
                        </div>
                        <div>
                            <div class="text-xs text-muted text-bold">GUEST COUNT</div>
                            <div class="fw-700 text-primary" style="font-size:22px;" id="detail-pax">—</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT: Report Form -->
    <div class="col-lg-8">
        <div class="card" id="reportCard">
            <div class="card-header">
                <div>
                    <div class="card-title">Post-Event Report</div>
                    <div class="card-subtitle" id="reportSubtitle">Select a past event to submit a report</div>
                </div>
            </div>
            <div id="reportContent">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-clipboard-check"></i></div>
                    <h3>No event selected</h3>
                    <p>Select one of your past assigned events on the left to submit a report.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let reportableBookings = [];
let equipmentList = [];

async function init() {
    try {
        const [bData, eData] = await Promise.all([
            Api.get(BASE + 'src/api/event_reports.php'),
            Api.get(BASE + 'src/api/inventory.php'),
        ]);
        reportableBookings = bData.bookings || [];
        equipmentList = eData.equipment || eData.items || [];
    } catch(e) {
        Toast.error('Failed to load data: ' + e.message);
        return;
    }

    const sel = document.getElementById('bookingSelect');
    sel.innerHTML = '<option value="">Choose a past event…</option>' +
        reportableBookings.map(b => {
            const submitted = b.report_submitted_at ? ' ✅' : '';
            return `<option value="${b.id}">#${b.id} — ${esc(b.client_name)} (${Format.dateShort(b.event_date)})${submitted}</option>`;
        }).join('');
}

function loadReportForm() {
    const bookingId = document.getElementById('bookingSelect').value;
    if (!bookingId) {
        document.getElementById('bookingDetails').style.display = 'none';
        document.getElementById('reportContent').innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-clipboard-check"></i></div>
                <h3>No event selected</h3>
                <p>Select one of your past assigned events on the left to submit a report.</p>
            </div>`;
        return;
    }

    const booking = reportableBookings.find(b => b.id == bookingId);
    if (!booking) return;

    // Fill details
    document.getElementById('detail-client').textContent = booking.client_name;
    document.getElementById('detail-date').textContent = Format.dateShort(booking.event_date) + (booking.event_time ? ' ' + Format.time(booking.event_time) : '');
    document.getElementById('detail-location').textContent = booking.event_location || '—';
    document.getElementById('detail-pax').textContent = booking.pax_count + ' guests';
    document.getElementById('bookingDetails').style.display = 'block';

    const alreadySubmitted = !!booking.report_submitted_at;

    // Equipment options
    const eqOptions = equipmentList.map(e => `<option value="${e.id}">${esc(e.name)} (₱${parseFloat(e.replacement_cost).toFixed(2)})</option>`).join('');

    document.getElementById('reportSubtitle').textContent =
        alreadySubmitted ? `Report already submitted — ${Format.dateShort(booking.report_submitted_at)}` : `Booking #${booking.id} — ${booking.client_name}`;

    document.getElementById('reportContent').innerHTML = `
        <div class="card-body" style="max-width:650px;">
            ${alreadySubmitted ? `
            <div style="display:flex; gap:10px; align-items:center; background:rgba(48,209,88,0.08);
                        border:1px solid rgba(48,209,88,0.3); border-radius:12px; padding:14px 16px; margin-bottom:20px;">
                <i class="fas fa-check-circle" style="color:#1A7A32; font-size:20px;"></i>
                <div>
                    <div style="font-size:13px; font-weight:700; color:#1A7A32;">Report Already Submitted</div>
                    <div style="font-size:12px; color:rgba(60,60,67,0.6);">You can update the report by submitting again.</div>
                </div>
            </div>` : ''}

            <!-- Actual Times -->
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(60,60,67,0.4); margin-bottom:12px;">
                ⏰ Actual Event Times
            </div>
            <div class="form-grid-2" style="gap:14px; margin-bottom:20px;">
                <div class="form-group">
                    <label class="form-label">Actual Start Time <span class="required">*</span></label>
                    <input type="time" class="form-control" id="rpt_start" value="${booking.actual_start_time || ''}" onchange="calcOvertime()">
                </div>
                <div class="form-group">
                    <label class="form-label">Actual End Time <span class="required">*</span></label>
                    <input type="time" class="form-control" id="rpt_end" value="${booking.actual_end_time || ''}" onchange="calcOvertime()">
                </div>
            </div>

            <!-- Overtime Badge -->
            <div id="overtimeBadge" style="display:none; margin-bottom:20px;"></div>

            <!-- Complaints -->
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(60,60,67,0.4); margin-bottom:12px; margin-top:8px;">
                📋 Complaints & Notes
            </div>
            <div class="form-group" style="margin-bottom:20px;">
                <textarea class="form-control" id="rpt_complaints" rows="3"
                    placeholder="Any client complaints, incidents, or notes…">${esc(booking.event_report_notes || '')}</textarea>
            </div>

            <!-- Breakage Logger -->
            <div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(60,60,67,0.4); margin-bottom:12px;">
                🔧 Equipment Breakage / Loss
            </div>
            <div id="breakageRows" style="margin-bottom:10px;"></div>
            <button type="button" class="btn btn-outline-secondary btn-sm py-3" onclick="addBreakageRow()" style="margin-bottom:20px;">
                <i class="fas fa-plus"></i> Add Breakage Entry
            </button>

            <!-- Submit -->
            <div style="border-top:1px solid var(--border); padding-top:16px; display:flex; justify-content:flex-end; gap:10px;">
                <button class="btn btn-success" id="submitReportBtn" onclick="submitReport(${booking.id})">
                    <i class="fas fa-paper-plane"></i> Submit Report
                </button>
            </div>
        </div>`;

    // Pre-populate overtime badge if times exist
    if (booking.actual_start_time && booking.actual_end_time) calcOvertime();
}

let breakageCounter = 0;

function addBreakageRow() {
    breakageCounter++;
    const eqOptions = equipmentList.map(e =>
        `<option value="${e.id}">${esc(e.name)} (₱${parseFloat(e.replacement_cost).toFixed(2)})</option>`
    ).join('');

    const row = document.createElement('div');
    row.className = 'breakage-row';
    row.id = 'br_row_' + breakageCounter;
    row.style.cssText = 'display:flex; gap:8px; align-items:flex-start; margin-bottom:8px; padding:10px; background:rgba(255,59,48,0.03); border:1px solid rgba(255,59,48,0.1); border-radius:10px;';
    row.innerHTML = `
        <div style="flex:2;">
            <select class="form-control br-equip" style="font-size:12px;">
                <option value="">Select item…</option>
                ${eqOptions}
            </select>
        </div>
        <div style="flex:0 0 70px;">
            <input type="number" class="form-control br-qty" placeholder="Qty" min="1" value="1" style="font-size:12px;">
        </div>
        <div style="flex:1;">
            <select class="form-control br-charge-to" style="font-size:12px;">
                <option value="client">Charge: Client</option>
                <option value="staff">Charge: Staff</option>
                <option value="business">Charge: Business</option>
            </select>
        </div>
        <div style="flex:1;">
            <input type="text" class="form-control br-notes" placeholder="Notes…" style="font-size:12px;">
        </div>
        <button type="button" onclick="document.getElementById('br_row_${breakageCounter}').remove()" style="background:none;border:none;color:#FF3B30;cursor:pointer;font-size:16px;padding:6px;">
            <i class="fas fa-trash-can"></i>
        </button>`;
    document.getElementById('breakageRows').appendChild(row);
}

function calcOvertime() {
    const start = document.getElementById('rpt_start')?.value;
    const end   = document.getElementById('rpt_end')?.value;
    const badge = document.getElementById('overtimeBadge');
    if (!start || !end || !badge) return;

    const [sh, sm] = start.split(':').map(Number);
    const [eh, em] = end.split(':').map(Number);
    const startMin = sh * 60 + sm;
    const endMin   = eh * 60 + em;
    const duration = endMin - startMin;

    if (duration <= 0) {
        badge.style.display = 'none';
        return;
    }

    const standardMin = 4 * 60; // 4 hours
    const otMin = Math.max(0, duration - standardMin);
    const hours = Math.floor(duration / 60);
    const mins  = duration % 60;

    if (otMin > 0) {
        const otHours = (otMin / 60).toFixed(1);
        badge.style.display = 'block';
        badge.innerHTML = `
            <div style="display:flex; gap:10px; align-items:center; background:rgba(255,59,48,0.08);
                        border:1px solid rgba(255,59,48,0.25); border-radius:12px; padding:14px 16px;">
                <span style="font-size:24px;">⏱️</span>
                <div>
                    <div style="font-size:14px; font-weight:700; color:#C0392B;">
                        Overtime Detected: ${otHours} hour(s) beyond 4-hour standard
                    </div>
                    <div style="font-size:12px; color:rgba(60,60,67,0.6);">
                        Total duration: ${hours}h ${mins}m · Standard: 4h · Excess: ${otHours}h
                    </div>
                </div>
            </div>`;
    } else {
        badge.style.display = 'block';
        badge.innerHTML = `
            <div style="display:flex; gap:10px; align-items:center; background:rgba(48,209,88,0.08);
                        border:1px solid rgba(48,209,88,0.25); border-radius:12px; padding:12px 16px;">
                <span style="font-size:20px;">✅</span>
                <div style="font-size:13px; font-weight:600; color:#1A7A32;">
                    Within standard time — ${hours}h ${mins}m (4h limit)
                </div>
            </div>`;
    }
}

async function submitReport(bookingId) {
    const startTime  = document.getElementById('rpt_start').value;
    const endTime    = document.getElementById('rpt_end').value;
    const complaints = document.getElementById('rpt_complaints').value.trim();

    if (!startTime || !endTime) {
        Toast.error('Please enter both actual start and end times.');
        return;
    }

    // Collect breakage rows
    const breakageRows = document.querySelectorAll('.breakage-row');
    const breakages = [];
    breakageRows.forEach(row => {
        const equipId = row.querySelector('.br-equip').value;
        const qty     = parseInt(row.querySelector('.br-qty').value) || 0;
        const notes   = row.querySelector('.br-notes').value.trim();
        if (equipId && qty > 0) {
            const chargeTo = row.querySelector('.br-charge-to')?.value || 'client';
            breakages.push({ equipment_id: parseInt(equipId), quantity: qty, charge_to: chargeTo, notes: notes || null });
        }
    });

    const btn = document.getElementById('submitReportBtn');
    Form.setLoading(btn, true);

    try {
        const res = await Api.post(BASE + 'src/api/event_reports.php', {
            booking_id: bookingId,
            actual_start_time: startTime,
            actual_end_time: endTime,
            complaints,
            breakages,
        });

        let msg = 'Event report submitted successfully!';
        if (res.overtime_minutes > 0) {
            msg += ` Overtime: ${(res.overtime_minutes / 60).toFixed(1)}h (₱${res.overtime_total.toFixed(2)})`;
        }
        if (res.breakage_total > 0) {
            msg += ` | Breakage: ₱${res.breakage_total.toFixed(2)}`;
        }

        Toast.success(msg, 6000);

        // Refresh data
        const bData = await Api.get(BASE + 'src/api/event_reports.php');
        reportableBookings = bData.bookings || [];
        loadReportForm(); // Reload to show "already submitted" badge
    } catch(e) {
        Toast.error(e.message);
    }
    Form.setLoading(btn, false);
}

init();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
