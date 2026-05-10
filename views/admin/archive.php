<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Event Archive';
$pageSubtitle = 'Permanent history of completed events';
$activePage   = 'archive';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="card mb-3">
    <div class="card-body" style="padding:16px 20px;">
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" class="search-input" id="archiveSearch" placeholder="Search client, menu, location…">
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <div style="font-size:10px; font-weight:700; color:var(--label-3); text-transform:uppercase; margin-right:2px; white-space:nowrap;">Sort By</div>
                <select class="form-control" id="sortFilter" style="width:180px; border-radius:var(--r-pill); font-size:13px;">
                    <option value="archived">Archived Date (Default)</option>
                    <option value="upcoming">Event Date (Upcoming)</option>
                    <option value="latest">Latest Added</option>
                    <option value="payment">Payment Date</option>
                </select>
            </div>

            <div class="d-flex align-items-center gap-2">
                <div style="font-size:10px; font-weight:700; color:var(--label-3); text-transform:uppercase; margin-right:2px; white-space:nowrap;">Mode</div>
                <select class="form-control" id="modeFilter" style="width:150px; border-radius:var(--r-pill); font-size:13px;">
                    <option value="">All Payments</option>
                    <option value="online">Online Only</option>
                    <option value="manual">Manual Only</option>
                </select>
            </div>

            <button class="btn btn-secondary" onclick="resetFilters()" title="Reset All Filters" style="border-radius:50%; width:40px; height:40px; padding:0; flex-shrink:0;">
                <i class="fas fa-undo"></i>
            </button>

            
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title">Archived Events</div>
            <div class="card-subtitle">Read-only historical audit trail</div>
        </div>
        <span class="text-muted text-sm" id="archiveCount"></span>
    </div>
    <div class="table-wrapper table-responsive" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
        <table class="data-table" id="archiveTable">
            <thead>
                <tr><th>Event Date</th><th>Client</th><th>Pax</th>
                    <th>Total Cost</th><th>Amount Paid</th><th>Payment</th><th>Staff Report / Notes</th><th>Archived On</th><th class="td-actions">Action</th></tr>
            </thead>
            <tbody id="archiveBody"><tr><td colspan="8"><div class="spinner"></div></td></tr></tbody>
        </table>
    </div>
    <!-- Pagination Bar -->
    <div class="table-pagination" id="archivePagination">
        <button type="button" class="pagination-button" id="prevBtn" onclick="changePage(currentPage - 1)" disabled>
            <i class="fas fa-chevron-left"></i> Previous
        </button>
        <div class="pagination-info" id="pageInfo">Page 1 of 1</div>
        <button type="button" class="pagination-button" id="nextBtn" onclick="changePage(currentPage + 1)" disabled>
            Next <i class="fas fa-chevron-right"></i>
        </button>
    </div>
</div>

<!-- VIEW ARCHIVED BOOKING MODAL -->
<div class="modal fade" id="viewBookingModal" tabindex="-1" data-bs-focus="false">
    <div class="modal-dialog modal-xl">
        <div class="modal-content" style="border-radius:24px; overflow:hidden; border:none; box-shadow: 0 20px 40px rgba(0,0,0,0.2);">
            <div class="modal-header" style="background: linear-gradient(135deg, #1a1a1a 0%, #333 100%); color:white; padding:20px 30px; border:none; position:relative;">
                <div style="display:flex; align-items:center; gap:15px; padding-right:40px;">
                    <div style="width:48px; height:48px; background:rgba(255,255,255,0.1); border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:20px;">
                        <i class="fas fa-box-archive"></i>
                    </div>
                    <div>
                        <h5 class="modal-title" style="margin:0; font-weight:700; font-size:20px; letter-spacing:-0.5px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:600px;">Archived Booking <span id="view_booking_id_title"></span></h5>
                        <div id="view_status_badge" style="margin-top:4px;"></div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="position:absolute; right:25px; top:35px;"></button>
            </div>
            <div class="modal-body" style="padding:0; background:#f8f9fa;">
                <div class="modal-responsive-grid" style="min-height:600px;">
                    
                    <!-- SIDEBAR: KEY DETAILS -->
                    <div style="background:white; border-right:1px solid #eee; padding:30px;">
                        <div class="mb-4">
                            <label style="display:block; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:8px;">Client Information</label>
                            <div style="display:flex; align-items:center; gap:12px; margin-bottom:15px;">
                                <div style="width:40px; height:40px; background:var(--sys-blue-soft); color:var(--sys-blue-deeper); border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700;">
                                    <span id="view_client_initial"></span>
                                </div>
                                <div>
                                    <div id="view_client_name" style="font-weight:700; color:#1a1a1a;"></div>
                                    <div id="view_client_phone" style="font-size:13px; color:#666;"></div>
                                </div>
                            </div>
                            <div id="view_client_email" style="font-size:13px; color:#666; margin-bottom:5px; display:flex; align-items:center; gap:8px;">
                                <i class="fas fa-envelope" style="width:14px;"></i> <span></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label style="display:block; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:12px;">Event Logistics</label>
                            <div style="display:grid; gap:15px;">
                                <div style="display:flex; gap:12px;">
                                    <div style="color:#888;"><i class="fas fa-calendar-day"></i></div>
                                    <div>
                                        <div style="font-size:11px; color:#888;">Date</div>
                                        <div id="view_event_date" style="font-weight:600;"></div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px;">
                                    <div style="color:#888;"><i class="fas fa-clock"></i></div>
                                    <div>
                                        <div style="font-size:11px; color:#888;">Time</div>
                                        <div id="view_event_time" style="font-weight:600;"></div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px;">
                                    <div style="color:#888;"><i class="fas fa-map-marker-alt"></i></div>
                                    <div>
                                        <div style="font-size:11px; color:#888;">Location</div>
                                        <div id="view_event_location" style="font-weight:600; font-size:13px; line-height:1.4;"></div>
                                    </div>
                                </div>
                                <div style="display:flex; gap:12px;">
                                    <div style="color:#888;"><i class="fas fa-users"></i></div>
                                    <div>
                                        <div style="font-size:11px; color:#888;">Pax Count</div>
                                        <div id="view_pax_count" style="font-weight:600;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label style="display:block; font-size:11px; font-weight:700; color:#888; text-transform:uppercase; margin-bottom:12px;">Package & Pricing</label>
                            <div style="background:#f0f7ff; border-radius:12px; padding:15px; border:1px solid #d0e5ff;">
                                <div id="view_package_name" style="font-weight:700; color:var(--sys-blue-deeper); font-size:14px; margin-bottom:5px;"></div>
                                <div style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:3px;">
                                    <span style="color:#555;">Base Price:</span>
                                    <span id="view_base_price" style="font-weight:600;"></span>
                                </div>
                                <div id="view_extra_pax_row" style="display:flex; justify-content:space-between; font-size:13px; margin-bottom:3px;">
                                    <span style="color:#555;">Extra Pax:</span>
                                    <span id="view_extra_cost" style="font-weight:600; color:var(--sys-orange-deeper);"></span>
                                </div>
                                <div style="display:flex; justify-content:space-between; font-size:13px;">
                                    <span style="color:#555;">Transport:</span>
                                    <span id="view_transport_fee" style="font-weight:600;"></span>
                                </div>
                                <hr style="margin:10px 0; border-top:1px dashed #d0e5ff;">
                                <div style="display:flex; justify-content:space-between; font-size:15px;">
                                    <span style="font-weight:700;">Total:</span>
                                    <span id="view_total_cost" style="font-weight:800; color:#1a1a1a;"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-auto">
                             <div style="font-size:12px; color:#888;">Created By: <span id="view_created_by" style="font-weight:600; color:#444;"></span></div>
                             <div style="font-size:12px; color:#888;">Created At: <span id="view_created_at"></span></div>
                        </div>
                    </div>

                    <!-- MAIN CONTENT: TABS/SECTIONS -->
                    <div style="padding:30px; display:flex; flex-direction:column; gap:25px; overflow-y:auto; max-height:calc(100vh - 120px);">
                        
                        <!-- FINANCIAL SUMMARY CARD -->
                        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:20px;">
                            <div class="card p-3 shadow-sm" style="border-radius:16px; border:none; background:white;">
                                <div style="font-size:11px; font-weight:700; color:#888; margin-bottom:5px;">TOTAL COST</div>
                                <div id="card_total_cost" style="font-size:22px; font-weight:800; letter-spacing:-0.5px;"></div>
                            </div>
                            <div class="card p-3 shadow-sm" style="border-radius:16px; border:none; background:white;">
                                <div style="font-size:11px; font-weight:700; color:#888; margin-bottom:5px;">AMOUNT PAID</div>
                                <div id="card_amount_paid" style="font-size:22px; font-weight:800; color:#1A7A32; letter-spacing:-0.5px;"></div>
                            </div>
                            <div class="card p-3 shadow-sm" style="border-radius:16px; border:none; background:white;">
                                <div style="font-size:11px; font-weight:700; color:#888; margin-bottom:5px;">REMAINING BALANCE</div>
                                <div id="card_balance" style="font-size:22px; font-weight:800; color:#C0392B; letter-spacing:-0.5px;"></div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:25px;">
                            <!-- MENU SELECTION -->
                            <div>
                                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:15px;">
                                    <h6 style="font-weight:700; margin:0;"><i class="fas fa-utensils me-2" style="color:var(--sys-orange);"></i>Menu Selection</h6>
                                    <span id="view_menu_count" class="badge bg-soft-secondary text-muted">0 dishes</span>
                                </div>
                                <div id="view_menu_grid" style="display:grid; grid-template-columns: 1fr; gap:8px;">
                                    <!-- Dishes injected here -->
                                </div>
                                <div id="view_custom_items_wrap" style="margin-top:15px; border-top:1px solid #eee; padding-top:15px; display:none;">
                                    <div style="font-size:12px; font-weight:700; color:#888; margin-bottom:10px;">CUSTOM ADD-ONS</div>
                                    <div id="view_custom_items_list"></div>
                                </div>
                            </div>

                            <!-- OPERATIONS & STAFF -->
                            <div style="display:flex; flex-direction:column; gap:25px;">
                                <div>
                                    <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-users-gear me-2" style="color:var(--sys-blue);"></i>Staff Assignments</h6>
                                    <div id="view_staff_list" style="display:grid; gap:8px;">
                                        <!-- Staff injected here -->
                                    </div>
                                    <div id="no_staff_msg" style="padding:20px; text-align:center; background:rgba(0,0,0,0.03); border-radius:12px; color:#888; font-size:13px;">
                                        No staff dispatched yet.
                                    </div>
                                </div>

                                <div>
                                    <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-triangle-exclamation me-2" style="color:var(--sys-orange);"></i>Breakage & Reports</h6>
                                    <div id="view_breakage_list" style="display:grid; gap:8px;">
                                        <!-- Breakages injected here -->
                                    </div>
                                    <div id="no_breakage_msg" style="padding:20px; text-align:center; background:rgba(0,0,0,0.03); border-radius:12px; color:#888; font-size:13px;">
                                        No incidents reported.
                                    </div>
                                </div>

                                <div id="view_report_section">
                                    <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-clipboard-check me-2" style="color:var(--sys-green);"></i>Staff Event Report</h6>
                                    <div id="view_report_notes" style="padding:15px; background:#fff8f0; border:1px solid #ffe8cc; border-radius:12px; font-size:13px; line-height:1.5; color:#5a3e1b;">
                                        <!-- Report notes injected here -->
                                    </div>
                                </div>

                                <div id="view_notes_section" style="margin-top:20px;">
                                    <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-sticky-note me-2" style="color:var(--sys-blue);"></i>Client Notes</h6>
                                    <div id="view_notes_content" style="padding:15px; background:#f0f7ff; border:1px solid #d0e5ff; border-radius:12px; font-size:13px; line-height:1.5; color:#2c3e50;">
                                        <!-- Notes injected here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- PAYMENT HISTORY -->
                        <div>
                            <h6 style="font-weight:700; margin-bottom:15px;"><i class="fas fa-peso-sign me-2" style="color:#1A7A32;"></i>Payment History</h6>
                            <div class="table-wrapper shadow-sm" style="background:white; border-radius:16px; border:none;">
                                <table class="data-table" style="font-size:13px;">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Method</th>
                                            <th>Ref. No</th>
                                            <th>Amount</th>
                                            <th>Recorded By</th>
                                        </tr>
                                    </thead>
                                    <tbody id="view_payments_body">
                                        <!-- Payments injected here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding:20px 30px; background:white; border-top:1px solid #eee; justify-content: flex-end;">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>

async function loadArchive(page = 1) {
    currentPage = page;
    const search = document.getElementById('archiveSearch').value;
    const sort   = document.getElementById('sortFilter').value;
    const mode   = document.getElementById('modeFilter').value;
    
    const tbody = document.getElementById('archiveBody');
    tbody.innerHTML = '<tr><td colspan="9"><div class="spinner"></div></td></tr>';

    try {
        const d = await Api.get(BASE + 'src/api/archive.php', { 
            search, sort, mode, page: currentPage, limit: 10 
        });
        const rows = d.archived || [];
        const meta = d.meta || { currentPage: 1, totalPages: 1, totalRecords: 0 };
        
        totalPages = meta.totalPages;
        document.getElementById('archiveCount').textContent = meta.totalRecords + ' archived event(s)';
        renderPagination(meta);

        if (!rows.length) {
            tbody.innerHTML = `<tr><td colspan="9"><div class="table-empty">
                <i class="fas fa-box-archive"></i><p>No archived events found.</p>
            </div></td></tr>`;
            return;
        }

        tbody.innerHTML = rows.map(r => `
            <tr>
                <td class="fw-600">${Format.dateShort(r.event_date)}</td>
                <td class="td-name">${esc(r.client_name)}<br><small class="text-muted">${esc(r.client_phone||'')}</small></td>
                <td>${r.pax_count}</td>
                <td>${Format.peso(r.total_cost)}</td>
                <td class="text-success fw-600">${Format.peso(r.amount_paid)}</td>
                <td>${Format.paymentBadge(r.payment_status)}</td>
                <td style="max-width:250px;">
                    ${r.notes ? `<div class="text-xs mb-1"><i class="fas fa-sticky-note me-1 text-muted"></i>${esc(r.notes)}</div>` : ''}
                    ${r.event_report_notes ? `<div class="text-xs text-info"><i class="fas fa-clipboard-check me-1"></i>${esc(r.event_report_notes)}</div>` : ''}
                    ${!r.notes && !r.event_report_notes ? '<span class="text-muted text-xs">—</span>' : ''}
                </td>
                <td class="text-xs text-muted">${Format.dateShort(r.archived_at)}</td>
                <td class="td-actions">
                    <div class="btn-group">
                        <button class="btn btn-outline-info btn-sm" onclick="openViewBooking(${r.original_id})" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="unarchiveBooking(${r.id})" title="Unarchive / Restore">
                            <i class="fas fa-undo"></i>
                        </button>
                    </div>
                </td>
            </tr>`).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="9" class="text-center p-4 text-muted">Failed to load archive.</td></tr>';
    }
}

let currentPage = 1;
let totalPages = 1;

function renderPagination(meta) {
    document.getElementById('pageInfo').textContent = `Page ${meta.currentPage} of ${meta.totalPages}`;
    document.getElementById('prevBtn').disabled = meta.currentPage <= 1;
    document.getElementById('nextBtn').disabled = meta.currentPage >= meta.totalPages;
}

function changePage(page) {
    if (page < 1 || page > totalPages) return;
    loadArchive(page);
}

function resetFilters() {
    document.getElementById('archiveSearch').value = '';
    document.getElementById('sortFilter').value = 'archived';
    document.getElementById('modeFilter').value = '';
    loadArchive(1);
}

async function unarchiveBooking(id) {
    if (!await confirmDialog('Restore this booking to the active list? It will be removed from the archive snapshot.')) return;
    try {
        await Api.delete(BASE + 'src/api/archive.php', { id });
        Toast.success('Booking restored successfully.');
        loadArchive(currentPage);
    } catch (e) { Toast.error(e.message); }
}

function exportArchive() {
    const search = document.getElementById('archiveSearch').value;
    const sort   = document.getElementById('sortFilter').value;
    const mode   = document.getElementById('modeFilter').value;
    
    // Construct export URL
    const url = BASE + 'src/api/archive.php?export=1' + 
                '&search=' + encodeURIComponent(search) + 
                '&sort=' + encodeURIComponent(sort) + 
                '&mode=' + encodeURIComponent(mode);
    
    window.location.href = url;
}

async function openViewBooking(id) {
    try {
        const d = await Api.get(BASE + 'src/api/bookings.php', { id });
        const b = d.booking;
        if (!b) throw new Error('Booking data not found.');

        // Sidebar
        document.getElementById('view_booking_id_title').textContent = '#' + b.id;
        document.getElementById('view_status_badge').innerHTML = Format.bookingBadge(b.booking_status) + ' <span class="badge bg-secondary ms-1">ARCHIVED</span>';
        document.getElementById('view_client_initial').textContent = b.client_name.charAt(0).toUpperCase();
        document.getElementById('view_client_name').textContent = b.client_name;
        document.getElementById('view_client_phone').textContent = b.client_phone;
        document.getElementById('view_client_email').querySelector('span').textContent = b.client_email || 'No email provided';
        
        document.getElementById('view_event_date').textContent = Format.date(b.event_date);
        document.getElementById('view_event_time').textContent = b.event_time ? Format.time(b.event_time) : 'TBA';
        document.getElementById('view_event_location').textContent = b.event_location || 'Venue TBA';
        document.getElementById('view_pax_count').textContent = b.pax_count + ' Pax';
        
        document.getElementById('view_package_name').textContent = b.package_name;
        document.getElementById('view_base_price').textContent = Format.peso(b.base_price);
        document.getElementById('view_extra_cost').textContent = '+' + Format.peso(b.extra_cost);
        document.getElementById('view_transport_fee').textContent = Format.peso(b.transport_fee);
        document.getElementById('view_total_cost').textContent = Format.peso(b.total_cost);

        document.getElementById('view_created_by').textContent = b.created_by_name || 'System';
        document.getElementById('view_created_at').textContent = Format.date(b.created_at);

        // Financial Cards
        document.getElementById('card_total_cost').textContent = Format.peso(b.total_cost);
        document.getElementById('card_amount_paid').textContent = Format.peso(b.amount_paid);
        const balance = Math.max(0, parseFloat(b.total_cost) - parseFloat(b.amount_paid));
        document.getElementById('card_balance').textContent = Format.peso(balance);

        // Menu Grid
        const menuGrid = document.getElementById('view_menu_grid');
        menuGrid.innerHTML = b.dishes.map(d => `
            <div style="background:white; padding:10px 15px; border-radius:10px; border:1px solid #eee; display:flex; align-items:center; gap:12px;">
                <div style="width:8px; height:8px; border-radius:50%; background:var(--sys-orange);"></div>
                <div style="flex:1;">
                    <div style="font-weight:600; font-size:13.5px;">${esc(d.name)}</div>
                    <div style="font-size:11px; color:#888; text-transform:uppercase;">${esc(d.category)}</div>
                </div>
            </div>
        `).join('');
        document.getElementById('view_menu_count').textContent = b.dishes.length + ' dishes';

        // Custom Items
        const ciWrap = document.getElementById('view_custom_items_wrap');
        const ciList = document.getElementById('view_custom_items_list');
        if (b.custom_items && b.custom_items.length > 0) {
            ciWrap.style.display = 'block';
            ciList.innerHTML = b.custom_items.map(ci => `
                <div style="font-size:13px; color:#444; margin-bottom:5px; display:flex; justify-content:space-between;">
                    <span><i class="fas fa-plus-circle text-muted me-2"></i>${esc(ci.name)}</span>
                    <span class="text-muted text-xs">${esc(ci.category)}</span>
                </div>
            `).join('');
        } else {
            ciWrap.style.display = 'none';
        }

        // Staff List
        const staffList = document.getElementById('view_staff_list');
        const noStaff = document.getElementById('no_staff_msg');
        if (b.staff && b.staff.length > 0) {
            noStaff.style.display = 'none';
            staffList.innerHTML = b.staff.map(s => `
                <div style="background:white; padding:10px 15px; border-radius:10px; border:1px solid #eee; display:flex; align-items:center; justify-content:space-between;">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <i class="fas fa-user-circle text-muted" style="font-size:18px;"></i>
                        <div>
                            <div style="font-weight:600; font-size:13px;">${esc(s.staff_name)}</div>
                            <div style="font-size:11px; color:#888;">${esc(s.staff_email)}</div>
                        </div>
                    </div>
                    <span class="badge bg-soft-info text-info" style="font-size:10px;">${esc(s.role_required.toUpperCase())}</span>
                </div>
            `).join('');
        } else {
            staffList.innerHTML = '';
            noStaff.style.display = 'block';
        }

        // Breakages
        const breakageList = document.getElementById('view_breakage_list');
        const noBreakage = document.getElementById('no_breakage_msg');
        if (b.breakages && b.breakages.length > 0) {
            noBreakage.style.display = 'none';
            breakageList.innerHTML = b.breakages.map(br => `
                <div style="background:white; padding:10px 15px; border-radius:10px; border:1px solid #eee; display:flex; align-items:center; justify-content:space-between;">
                    <div>
                        <div style="font-weight:600; font-size:13px; color:#C0392B;">${esc(br.equipment_name)} (x${br.quantity})</div>
                        ${br.notes ? `<div style="font-size:11px; color:#666; font-style:italic;">Note: ${esc(br.notes)}</div>` : ''}
                        <div style="font-size:11px; color:#888;">Charge to: ${esc(br.charge_to.toUpperCase())}</div>
                    </div>
                    <div style="font-weight:700; color:#1a1a1a;">${Format.peso(br.total_cost)}</div>
                </div>
            `).join('');
        } else {
            breakageList.innerHTML = '';
            noBreakage.style.display = 'block';
        }

        // Staff Report Notes
        const reportSection = document.getElementById('view_report_section');
        const reportNotes = document.getElementById('view_report_notes');
        if (b.event_report_notes) {
            reportSection.style.display = 'block';
            reportNotes.innerHTML = `<i class="fas fa-quote-left me-2 opacity-50"></i>${esc(b.event_report_notes)}`;
        } else {
            reportSection.style.display = 'none';
        }

        // Payment History
        const payBody = document.getElementById('view_payments_body');
        if (b.payments && b.payments.length > 0) {
            payBody.innerHTML = b.payments.map(p => `
                <tr>
                    <td>${Format.dateShort(p.payment_date)}</td>
                    <td><span class="text-xs uppercase fw-700">${esc(p.payment_method)}</span></td>
                    <td class="text-muted text-xs">${esc(p.reference_no || '—')}</td>
                    <td class="fw-700">${Format.peso(p.amount)}</td>
                    <td class="text-muted text-xs">${esc(p.recorded_by_name)}</td>
                </tr>
            `).join('');
        } else {
            payBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No payments recorded.</td></tr>';
        }

        // Client Notes
        const notesSection = document.getElementById('view_notes_section');
        const notesContent = document.getElementById('view_notes_content');
        if (b.notes) {
            notesSection.style.display = 'block';
            notesContent.innerHTML = `<i class="fas fa-quote-left me-2 opacity-50"></i>${esc(b.notes)}`;
        } else {
            notesSection.style.display = 'none';
        }

        Modal.open('viewBookingModal');
    } catch (e) { Toast.error(e.message); }
}

document.getElementById('archiveSearch').addEventListener('input', debounce(() => loadArchive(1), 400));
document.getElementById('sortFilter').addEventListener('change', () => loadArchive(1));
document.getElementById('modeFilter').addEventListener('change', () => loadArchive(1));
loadArchive();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
