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
async function runBackup(e) {
    e.preventDefault();
    if (!await confirmDialog('Generate and download a full database backup?')) return;
    window.location.href = BASE + 'src/api/backup.php';
}

async function loadSettings() {
    try {
        const d = await Api.get(BASE + 'src/api/settings.php');
        const container = document.getElementById('settingsContainer');
        const groups = {};

        // Define setting metadata for better labels and help text
        const settingMetadata = {
            'audit_log_retention_days': {
                label: 'Audit Log Retention Days',
                help: 'Number of days to retain audit logs (30-3650). Logs older than this will be automatically deleted. Minimum 30 days recommended for compliance.',
                min: 30, max: 3650, type: 'int'
            },
            'debug_mode': {
                label: 'Debug Mode',
                help: 'WARNING: Enable (1) only for troubleshooting. Disables all logins and logs out all users. Must be 0 or 1 only.',
                type: 'select',
                options: [{value: '0', label: '0 - Disabled (Normal Operation)'}, {value: '1', label: '1 - Enabled (All Logins Disabled)'}]
            },
            'max_login_attempts': {
                label: 'Max Login Attempts',
                help: 'Maximum failed login attempts before account lockout (1-100). Default is 5.',
                min: 1, max: 100, type: 'int'
            },
            'max_admins': {
                label: 'Max Admin Accounts',
                help: 'Maximum number of Administrator accounts allowed (1-100). Cannot be lowered below current active admins.',
                min: 1, max: 100, type: 'int'
            },
            'session_timeout_minutes': {
                label: 'Session Timeout Minutes',
                help: 'Session timeout duration in minutes (5-1440 / 24 hours). Users will be logged out after this time.',
                min: 5, max: 1440, type: 'int'
            },
            'smtp_host': {
                label: 'SMTP Host',
                help: 'SMTP server hostname or IP address (e.g., smtp.gmail.com, mail.example.com).',
                type: 'text'
            },
            'smtp_user': {
                label: 'SMTP User (Email)',
                help: 'Email address for SMTP authentication. Required for system email notifications.',
                type: 'email'
            },
            'smtp_pass': {
                label: 'SMTP Password',
                help: 'Password or App Password for SMTP authentication. Keep this secure.',
                type: 'password'
            },
            'smtp_port': {
                label: 'SMTP Port',
                help: 'SMTP server port (1-65535). Common: 587 (TLS), 465 (SSL), 25 (unencrypted).',
                min: 1, max: 65535, type: 'int'
            },
            'sms_api_key': {
                label: 'SMS API Key (Semaphore)',
                help: 'API key for SMS gateway integration (optional). Required only if SMS notifications are enabled.',
                type: 'password'
            }
        };

        d.settings.forEach(s => {
            if (s.category !== 'system') return;
            if (!groups[s.category]) groups[s.category] = [];
            groups[s.category].push(s);
        });

        let html = '';
        for (const cat in groups) {
            html += `<h6 class="text-uppercase text-xs fw-800 text-muted mt-4 mb-2" style="letter-spacing:0.5px;">System Configuration</h6>`;
            groups[cat].forEach(s => {
                const meta = settingMetadata[s.key] || {};
                let inputHtml = '';
                
                if (meta.type === 'select') {
                    inputHtml = `
                        <select class="form-control form-control-sm" style="max-width:200px;" id="set_${s.key}">
                            ${(meta.options || []).map(opt => 
                                `<option value="${opt.value}" ${s.value == opt.value ? 'selected' : ''}>${opt.label}</option>`
                            ).join('')}
                        </select>
                    `;
                } else if (meta.type === 'password' || s.key === 'smtp_pass' || s.key === 'sms_api_key') {
                    inputHtml = `<input class="form-control form-control-sm" style="max-width:200px;" type="password" id="set_${s.key}" value="${s.value}">`;
                } else if (meta.type === 'email') {
                    inputHtml = `<input class="form-control form-control-sm" style="max-width:200px;" type="email" id="set_${s.key}" value="${s.value}" required>`;
                } else if (meta.type === 'int' || s.type === 'int') {
                    const extras = `min="${meta.min || 1}" max="${meta.max || 9999}"`;
                    inputHtml = `<input class="form-control form-control-sm" style="max-width:200px;" type="number" id="set_${s.key}" value="${s.value}" ${extras} required>`;
                } else {
                    inputHtml = `<input class="form-control form-control-sm" style="max-width:200px;" type="text" id="set_${s.key}" value="${s.value}" maxlength="2000">`;
                }
                
                const label = meta.label || s.key.replace(/_/g, ' ').toUpperCase();
                const help = meta.help || s.description || '';
                
                html += `
                    <div class="setting-item">
                        <div class="setting-label">${label}</div>
                        <div class="setting-desc">${help}</div>
                        <div class="d-flex gap-2">
                            ${inputHtml}
                            <button class="btn btn-primary btn-sm px-3" onclick="updateSetting('${s.key}')">
                                <i class="fas fa-save"></i> Save
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
