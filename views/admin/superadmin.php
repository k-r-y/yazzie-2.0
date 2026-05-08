<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

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
            <div class="card-header border-bottom-0 pb-0">
                <div class="card-title"><i class="fas fa-sliders me-2"></i>Global Configuration</div>
                <div class="card-subtitle">Manage business rules and operational constraints</div>
            </div>
            <div class="card-body p-0">
                <div class="d-flex flex-column flex-md-row">
                    <!-- Navigation Sidebar -->
                    <div class="settings-nav border-end" style="min-width: 200px; background: rgba(0,0,0,0.01);">
                        <div class="nav flex-column nav-pills p-3" id="settingsTabs" role="tablist">
                            <!-- Tabs will be injected here -->
                        </div>
                    </div>
                    
                    <!-- Content Area -->
                    <div class="flex-grow-1 p-4" style="max-height: 70vh; overflow-y: auto;">
                        <div class="tab-content" id="settingsTabContent">
                            <!-- Content will be injected here -->
                        </div>
                    </div>
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
                    <a href="#" onclick="runBackup(event)" class="list-group-item list-group-item-action d-flex align-items-center py-3">
                        <div class="stat-icon teal me-3"><i class="fas fa-database"></i></div>
                        <div style="flex:1;">
                            <div class="fw-700 text-sm">Database Backup</div>
                            <div class="text-xs text-muted">Download an SQL snapshot of the system state</div>
                        </div>
                        <i class="fas fa-download text-muted opacity-50"></i>
                    </a>
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
            <div class="table-pagination" style="margin:0; padding:0.75rem 1.25rem; border-top:1px solid rgba(0,0,0,0.06);">
                <button type="button" class="pagination-button" id="auditPrevBtn" onclick="changeAuditPage(currentAuditPage - 1)" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="pagination-info" id="auditPageInfo">Page 1 of 1</div>
                <button type="button" class="pagination-button" id="auditNextBtn" onclick="changeAuditPage(currentAuditPage + 1)" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.settings-nav .nav-link {
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    padding: 10px 14px;
    color: var(--label-2);
    border-radius: 8px;
    margin-bottom: 4px;
    transition: all 0.2s;
    border: 1px solid transparent;
}
.settings-nav .nav-link:hover {
    background: rgba(0,0,0,0.03);
    color: var(--sys-green);
}
.settings-nav .nav-link.active {
    background: var(--sys-green);
    color: white;
}
.settings-nav .nav-link.active .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}
.setting-item {
    padding: 16px 0;
    border-bottom: 0.5px solid var(--glass-sep);
}
.setting-item:last-child { border-bottom: none; }
.setting-item:first-child { padding-top: 0; }
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
.log-user { color: var(--sys-green); font-weight: 600; }
</style>

<script>
async function runBackup(e) {
    e.preventDefault();
    if (!await confirmDialog('Generate and download a full database backup?')) return;
    window.location.href = BASE + 'src/api/backup.php';
}

async function loadSettings() {
    try {
        const d = await Api.get(BASE + 'src/api/settings.php');
        const tabsContainer = document.getElementById('settingsTabs');
        const contentContainer = document.getElementById('settingsTabContent');
        
        const categoryMapping = {
            'debug_mode': 'SECURITY',
            'max_admins': 'SECURITY',
            'max_login_attempts': 'SECURITY',
            'lockout_duration_minutes': 'SECURITY',
            'session_timeout_minutes': 'SECURITY',
            'mail_enabled': 'EMAIL',
            'smtp_from': 'EMAIL',
            'smtp_from_name': 'EMAIL',
            'smtp_host': 'EMAIL',
            'smtp_pass': 'EMAIL',
            'smtp_port': 'EMAIL',
            'smtp_secure': 'EMAIL',
            'smtp_user': 'EMAIL',
            'audit_log_retention_days': 'SYSTEM'
        };

        const settingMetadata = {
            'audit_log_retention_days': { label: 'Audit Log Retention Days', help: 'Number of days to retain audit logs (30-3650).', type: 'number', min: 30, max: 3650 },
            'debug_mode': { 
                label: 'Debug Mode', 
                help: 'WARNING: Enable only for troubleshooting. Disables all logins.', 
                type: 'select', 
                options: [{value: '0', label: 'Disabled'}, {value: '1', label: 'Enabled'}] 
            },
            'mail_enabled': { 
                label: 'Email System Status', 
                help: 'Globally enable or disable all outgoing emails.', 
                type: 'select', 
                options: [{value: '1', label: 'Enabled'}, {value: '0', label: 'Disabled'}] 
            },
            'max_admins': { label: 'Max Admin Accounts', help: 'Maximum number of Administrator accounts allowed.', type: 'number', min: 1, max: 100 },
            'max_login_attempts': { label: 'Max Login Attempts', help: 'Maximum failed login attempts before lockout.', type: 'number', min: 1, max: 100 },
            'lockout_duration_minutes': { label: 'Lockout Duration (Minutes)', help: 'How long a user is locked out after exceeding max failed login attempts (1–1440 min).', type: 'number', min: 1, max: 1440 },
            'session_timeout_minutes': { label: 'Session Timeout Minutes', help: 'Users will be logged out after this time (5-1440).', type: 'number', min: 5, max: 1440 },
            'smtp_from': { label: 'Mail From Address', help: 'Email address that appears in the "From" field.', type: 'email' },
            'smtp_from_name': { label: 'Mail From Name', help: 'Display name that appears in the "From" field.', type: 'text' },
            'smtp_host': { label: 'SMTP Host', help: 'SMTP server hostname or IP address.', type: 'text' },
            'smtp_pass': { label: 'SMTP Password', help: 'Password or App Password for SMTP.', type: 'password' },
            'smtp_port': { label: 'SMTP Port', help: 'SMTP server port (e.g., 587, 465).', type: 'number', min: 1, max: 65535 },
            'smtp_secure': { 
                label: 'SMTP Security', 
                help: 'Encryption protocol (TLS or SSL).', 
                type: 'select', 
                options: [{value: 'tls', label: 'TLS'}, {value: 'ssl', label: 'SSL'}, {value: 'none', label: 'None'}] 
            },
            'smtp_user': { label: 'SMTP User (Email)', help: 'Email address for SMTP authentication.', type: 'email' }
        };

        const groups = {};
        d.settings.forEach(s => {
            const cat = categoryMapping[s.key];
            if (!cat) return;
            if (!groups[cat]) groups[cat] = [];
            groups[cat].push(s);
        });

        const categoryInfo = {
            'SECURITY': { icon: 'shield-alt', label: 'Security', desc: 'Login & session controls' },
            'EMAIL': { icon: 'envelope', label: 'Email', desc: 'SMTP & notifications' },
            'SYSTEM': { icon: 'cogs', label: 'System', desc: 'Audit logs & core rules' }
        };

        const orderedCategories = ['SECURITY', 'EMAIL', 'SYSTEM'];
        let tabsHtml = '';
        let contentHtml = '';

        orderedCategories.forEach((cat, index) => {
            if (!groups[cat]) return;
            const info = categoryInfo[cat];
            const tabId = `tab-${cat.toLowerCase()}`;
            const isActive = index === 0;

            tabsHtml += `
                <button class="nav-link ${isActive ? 'active' : ''}" id="${tabId}-tab" data-bs-toggle="pill" 
                        data-bs-target="#${tabId}" type="button" role="tab" style="padding: 10px 14px;">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon me-3" style="width:32px; height:32px; font-size:12px; background:rgba(0,0,0,0.05);">
                            <i class="fas fa-${info.icon}"></i>
                        </div>
                        <div>
                            <div class="fw-700" style="font-size: 11px; line-height: 1.2;">${info.label}</div>
                            <div class="text-xs text-muted fw-400 d-none d-xl-block" style="font-size: 9px; opacity: 0.7;">${info.desc}</div>
                        </div>
                    </div>
                </button>`;

            let settingsHtml = '';
            groups[cat].forEach(s => {
                const meta = settingMetadata[s.key] || {};
                let inputHtml = '';
                
                if (meta.type === 'select') {
                    inputHtml = `
                        <select class="form-select form-select-sm" style="max-width:200px;" id="set_${s.key}">
                            ${(meta.options || []).map(opt => `<option value="${opt.value}" ${s.value == opt.value ? 'selected' : ''}>${opt.label}</option>`).join('')}
                        </select>`;
                } else {
                    const type = meta.type || 'text';
                    const extras = meta.type === 'number' ? `min="${meta.min}" max="${meta.max}"` : '';
                    inputHtml = `<input class="form-control form-control-sm" style="max-width:250px;" type="${type}" id="set_${s.key}" value="${s.value}" ${extras}>`;
                }
                
                settingsHtml += `
                    <div class="setting-item">
                        <div class="setting-label">${meta.label || s.key}</div>
                        <div class="setting-desc">${meta.help || ''}</div>
                        <div class="d-flex gap-2 align-items-center">
                            <div style="flex: 1; max-width: 300px;">${inputHtml}</div>
                            <button class="btn btn-primary btn-sm px-3" onclick="updateSetting('${s.key}')">
                                <i class="fas fa-save me-1"></i> Save
                            </button>
                        </div>
                    </div>`;
            });

            contentHtml += `
                <div class="tab-pane fade ${isActive ? 'show active' : ''}" id="${tabId}" role="tabpanel">
                    <div class="mb-4">
                        <h5 class="fw-800 text-uppercase text-xs mb-1" style="letter-spacing: 1px; color: var(--sys-green);">
                             <i class="fas fa-${info.icon} me-2"></i> ${info.label}
                        </h5>
                        <div class="text-xs text-muted fw-500">${info.desc}</div>
                    </div>
                    ${settingsHtml}
                </div>`;
        });

        tabsContainer.innerHTML = tabsHtml;
        contentContainer.innerHTML = contentHtml;
    } catch (e) { Toast.error(e.message); }
}

async function updateSetting(key) {
    const val = document.getElementById('set_' + key).value;
    try {
        await Api.put(BASE + 'src/api/settings.php', { key, value: val });
        Toast.success('Setting updated.');
        loadAudit(); // Refresh logs
    } catch (e) { Toast.error(e.message); }
}

let currentAuditPage = 1;
let auditTotalPages = 1;

async function loadAudit(page = null) {
    if (page !== null) {
        currentAuditPage = Math.max(1, page);
    }

    try {
        const d = await Api.get(BASE + 'src/api/audit_logs.php', {
            page: currentAuditPage,
            limit: 15,
        });
        const container = document.getElementById('auditContainer');
        auditTotalPages = d.meta?.totalPages || 1;
        renderAuditPagination();
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

function changeAuditPage(newPage) {
    if (newPage < 1 || newPage > auditTotalPages) return;
    loadAudit(newPage);
}

function renderAuditPagination() {
    document.getElementById('auditPageInfo').textContent = `Page ${currentAuditPage} of ${auditTotalPages}`;
    document.getElementById('auditPrevBtn').disabled = currentAuditPage <= 1;
    document.getElementById('auditNextBtn').disabled = currentAuditPage >= auditTotalPages;
}

initTableSearch = null; // Disable table search helper for this custom page
loadSettings();
loadAudit();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
