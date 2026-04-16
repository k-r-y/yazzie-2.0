<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('super_admin');

$pageTitle    = 'Superadmin Console';
$pageSubtitle = 'System Infrastructure & Configuration';
$activePage   = 'superadmin';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row g-4">
    <!-- LEFT: System Settings -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-sliders me-2"></i>Global Configuration</div>
                <div class="card-subtitle">Manage business rules and operational constraints</div>
            </div>
            <div class="card-body">
                <div id="settingsContainer">
                    <div class="spinner my-4"></div>
                </div>
            </div>
            <div class="card-footer" style="background:rgba(0,0,0,0.02);">
                <span class="text-xs text-muted"><i class="fas fa-info-circle me-1"></i> Changes here take effect immediately across all modules.</span>
            </div>
        </div>
    </div>

    <!-- RIGHT: Quick Actions & Status -->
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">
                <div class="card-title">Security & Access</div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <a href="<?= BASE_URL ?>/views/admin/users.php" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="stat-icon sage me-3"><i class="fas fa-users-cog"></i></div>
                        <div style="flex:1;">
                            <div class="fw-700 text-sm">User Management</div>
                            <div class="text-xs text-muted">Manage administrators and staff accounts</div>
                        </div>
                        <i class="fas fa-chevron-right text-muted opacity-50"></i>
                    </a>
                    <div class="list-group-item d-flex align-items-center py-3 opacity-50" style="cursor:not-allowed;">
                        <div class="stat-icon teal me-3"><i class="fas fa-database"></i></div>
                        <div style="flex:1;">
                            <div class="fw-700 text-sm">Database Backup</div>
                            <div class="text-xs text-muted">Snapshot system state (coming soon)</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Log Preview -->
        <div class="card h-100" style="max-height: 500px; display:flex; flex-direction:column;">
            <div class="card-header">
                <div class="card-title">System Activity Log</div>
            </div>
            <div class="card-body" style="flex:1; overflow-y:auto; padding:0;" id="auditContainer">
                <div class="spinner my-4"></div>
            </div>
        </div>
    </div>
</div>

<style>
.setting-item {
    padding: 16px 0;
    border-bottom: 0.5px solid var(--glass-sep);
}
.setting-item:last-child { border-bottom: none; }
.setting-label { font-size: 13px; font-weight: 700; color: var(--label-1); margin-bottom: 2px; }
.setting-desc { font-size: 11px; color: var(--label-3); margin-bottom: 12px; }
.log-item {
    padding: 12px 16px;
    border-bottom: 0.5px solid var(--glass-sep);
    transition: background 0.2s;
}
.log-item:hover { background: rgba(0,0,0,0.02); }
.log-meta { display: flex; justify-content: space-between; font-size: 10px; color: var(--label-4); margin-bottom: 4px; }
.log-text { font-size: 12px; line-height: 1.4; color: var(--label-2); }
.log-user { color: var(--sys-blue); font-weight: 600; }
</style>

<script>
async function loadSettings() {
    try {
        const d = await Api.get(BASE + '/src/api/settings.php');
        const container = document.getElementById('settingsContainer');
        const groups = {};

        d.settings.forEach(s => {
            if (!groups[s.category]) groups[s.category] = [];
            groups[s.category].push(s);
        });

        let html = '';
        for (const cat in groups) {
            html += `<h6 class="text-uppercase text-xs fw-800 text-muted mt-4 mb-2" style="letter-spacing:0.5px;">${cat}</h6>`;
            groups[cat].forEach(s => {
                const inputType = s.type === 'int' ? 'number' : 'text';
                html += `
                    <div class="setting-item">
                        <div class="setting-label">${s.key.replace(/_/g, ' ').toUpperCase()}</div>
                        <div class="setting-desc">${s.description}</div>
                        <div class="d-flex gap-2">
                            <input class="form-control form-control-sm" style="max-width:200px;" 
                                   type="${inputType}" id="set_${s.key}" value="${s.value}">
                            <button class="btn btn-primary btn-sm px-3" onclick="updateSetting('${s.key}')">
                                <i class="fas fa-save"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        container.innerHTML = html;
    } catch (e) { Toast.error(e.message); }
}

async function updateSetting(key) {
    const val = document.getElementById('set_' + key).value;
    try {
        await Api.put(BASE + '/src/api/settings.php', { key, value: val });
        Toast.success('Setting updated.');
        loadAudit(); // Refresh logs
    } catch (e) { Toast.error(e.message); }
}

async function loadAudit() {
    try {
        const d = await Api.get(BASE + '/src/api/audit_logs.php', { limit: 15 });
        const container = document.getElementById('auditContainer');
        if (!d.logs?.length) {
            container.innerHTML = '<div class="p-4 text-center text-muted text-sm">No activity recorded.</div>';
            return;
        }
        container.innerHTML = d.logs.map(l => `
            <div class="log-item">
                <div class="log-meta">
                    <span class="log-user">${l.user_name || 'System'}</span>
                    <span>${Format.dateShort(l.created_at)} · ${new Date(l.created_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'})}</span>
                </div>
                <div class="log-text">
                    <strong>${l.action.replace(/_/g, ' ').toUpperCase()}</strong>: 
                    ${l.entity} #${l.entity_id || 'Global'}
                    ${l.new_value ? `<div class="text-xs text-muted mt-1 opacity-75">${l.new_value}</div>` : ''}
                </div>
            </div>
        `).join('');
    } catch (e) { console.error(e); }
}

initTableSearch = null; // Disable table search helper for this custom page
loadSettings();
loadAudit();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
