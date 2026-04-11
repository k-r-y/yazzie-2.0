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
    <div class="card-body" style="padding:14px 20px;">
        <div class="search-input-wrap" style="max-width:380px;">
            <i class="fas fa-search"></i>
            <input type="text" class="search-input" id="archiveSearch" placeholder="Search client, menu, location…">
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
    <div class="table-wrapper">
        <table class="data-table" id="archiveTable">
            <thead>
                <tr><th>Event Date</th><th>Client</th><th>Menu</th><th>Pax</th>
                    <th>Total Cost</th><th>Amount Paid</th><th>Payment</th><th>Archived On</th></tr>
            </thead>
            <tbody id="archiveBody"><tr><td colspan="8"><div class="spinner"></div></td></tr></tbody>
        </table>
    </div>
</div>

<script>
const BASE = '<?= BASE_URL ?>';

async function loadArchive() {
    const search = document.getElementById('archiveSearch').value;
    const d = await Api.get(BASE + '/src/api/archive.php', { search });
    const rows = d.archived || [];
    document.getElementById('archiveCount').textContent = rows.length + ' archived event(s)';
    const tbody = document.getElementById('archiveBody');
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="8"><div class="table-empty">
            <i class="fas fa-box-archive"></i><p>No archived events yet. Complete and archive bookings from the Bookings page.</p>
        </div></td></tr>`;
        return;
    }
    tbody.innerHTML = rows.map(r => `
        <tr>
            <td class="fw-600">${Format.dateShort(r.event_date)}</td>
            <td class="td-name">${r.client_name}<br><small class="text-muted">${r.client_phone||''}</small></td>
            <td>${r.menu_name}</td>
            <td>${r.pax_count}</td>
            <td>${Format.peso(r.total_cost)}</td>
            <td class="text-success fw-600">${Format.peso(r.amount_paid)}</td>
            <td>${Format.paymentBadge(r.payment_status)}</td>
            <td class="text-xs text-muted">${Format.dateShort(r.archived_at)}</td>
        </tr>`).join('');
    initTableSearch('archiveSearch', 'archiveTable');
}

document.getElementById('archiveSearch').addEventListener('input', debounce(loadArchive, 400));
loadArchive();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
