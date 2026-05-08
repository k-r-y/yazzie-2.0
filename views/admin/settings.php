<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle    = 'Business Settings';
$pageSubtitle = 'Manage operational limits, pricing rules, and event configurations';
$activePage   = 'settings';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="row g-4">
    <div class="col-lg-12">
        <div class="card h-100">
            <div class="card-header border-bottom-0 pb-0">
                <div class="card-title"><i class="fas fa-sliders me-2"></i>Business Configuration</div>
                <div class="card-subtitle">Values used for cost estimation and automated scheduling</div>
            </div>
            <div class="card-body p-0">
                <div class="d-flex flex-column flex-md-row">
                    <!-- Navigation Sidebar -->
                    <div class="settings-nav border-end" style="min-width: 240px; background: rgba(0,0,0,0.01);">
                        <div class="nav flex-column nav-pills p-3" id="settingsTabs" role="tablist">
                            <!-- Tabs will be injected here -->
                        </div>
                    </div>
                    
                    <!-- Content Area -->
                    <div class="flex-grow-1 p-4" style="max-height: 80vh; overflow-y: auto;">
                        <div class="tab-content" id="settingsTabContent">
                            <!-- Content will be injected here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.settings-nav .nav-link {
    text-align: left;
    font-size: 13px;
    font-weight: 600;
    padding: 12px 16px;
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
    box-shadow: 0 4px 12px rgba(var(--sys-green-rgb), 0.2);
}
.settings-nav .nav-link.active .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}
.setting-item {
    padding: 20px 0;
    border-bottom: 0.5px solid var(--glass-sep);
}
.setting-item:last-child { border-bottom: none; }
.setting-item:first-child { padding-top: 0; }
.setting-label { font-size: 13px; font-weight: 700; color: var(--label-1); margin-bottom: 4px; display: block; }
.setting-desc { font-size: 11px; color: var(--label-3); margin-bottom: 12px; line-height: 1.4; }
</style>

<script>
async function loadSettings() {
    const instructions = {
        'operating_hours_start': 'Sets the earliest possible time an event can begin.',
        'operating_hours_end': 'Sets the latest possible time an event can conclude.',
        'min_pax': 'Minimum guest count required for any booking.',
        'max_pax': 'Maximum capacity limit for the catering service.',
        'standard_dp_percent': 'Percentage of total required for initial confirmation (0.3 = 30%).',
        'rush_dp_percent': 'Percentage required for bookings within the rush threshold (1.0 = 100%).',
        'min_lead_time_days': 'Days notice required before an event date can be selected.',
        'rush_threshold_hours': 'Timeframe (in hours) where stricter payment rules apply.',
        'business_name': 'The official name of your catering business as it appears on invoices.',
        'business_address': 'The physical or mailing address printed on client documents.',
        'terms_and_conditions': 'The fine print and legal terms shown at the bottom of invoices and contracts.',
        'data_privacy_notice': 'The privacy policy and data handling notice shown to clients during transactions.',
        'payment_instructions': 'Detailed instructions for clients on how to settle their payments (e.g., GCash, Bank details).',
        'cancel_forfeiture_percent': 'Percent of total cost forfeited on cancellation'
    };

    const categoryMapping = {
        'business_name': 'Company Profile',
        'business_address': 'Company Profile',
        'business_email': 'Company Profile',
        'business_phone': 'Company Profile',
        'company_name': 'Company Profile',

        'operating_hours_start': 'Event Operations',
        'operating_hours_end': 'Event Operations',
        'min_pax': 'Event Operations',
        'max_pax': 'Event Operations',
        'min_lead_time_days': 'Event Operations',
        'rush_threshold_hours': 'Event Operations',

        'standard_dp_percent': 'Financial Rules',
        'rush_dp_percent': 'Financial Rules',
        'min_dp_percent': 'Financial Rules',
        'cancel_forfeiture_percent': 'Financial Rules',

        'bank_account_name': 'Payment Channels',
        'bank_account_no': 'Payment Channels',
        'bank_name': 'Payment Channels',
        'gcash_name': 'Payment Channels',
        'gcash_no': 'Payment Channels',
        'maya_name': 'Payment Channels',
        'maya_no': 'Payment Channels',
        'payment_instructions': 'Payment Channels',

        'terms_and_conditions': 'Legal & Documents',
        'data_privacy_notice': 'Legal & Documents'
    };

    try {
        const d = await Api.get(BASE + 'src/api/settings.php');
        const tabsContainer = document.getElementById('settingsTabs');
        const contentContainer = document.getElementById('settingsTabContent');
        
        const groups = {};
        d.settings.forEach(s => {
            const cat = categoryMapping[s.key] || 'OTHER';
            if (cat === 'OTHER') return;
            if (!groups[cat]) groups[cat] = [];
            groups[cat].push(s);
        });

        const categoryInfo = {
            'Company Profile': { icon: 'building', label: 'Company Profile', desc: 'Public contact & brand info' },
            'Event Operations': { icon: 'calendar-alt', label: 'Event Operations', desc: 'Time, capacity, and scheduling rules' },
            'Financial Rules': { icon: 'percentage', label: 'Financial Rules', desc: 'Percentages and penalties' },
            'Payment Channels': { icon: 'university', label: 'Payment Channels', desc: 'Where clients send money' },
            'Legal & Documents': { icon: 'balance-scale', label: 'Legal & Documents', desc: 'Policies shown on invoices/forms' }
        };

        const orderedCategories = ['Company Profile', 'Event Operations', 'Financial Rules', 'Payment Channels', 'Legal & Documents'];
        
        let tabsHtml = '';
        let contentHtml = '';

        orderedCategories.forEach((cat, index) => {
            if (!groups[cat]) return;

            const info = categoryInfo[cat];
            const tabId = `tab-${cat.toLowerCase().replace(/[^a-z]/g, '')}`;
            const isActive = index === 0;

            tabsHtml += `
                <button class="nav-link ${isActive ? 'active' : ''}" id="${tabId}-tab" data-bs-toggle="pill" 
                        data-bs-target="#${tabId}" type="button" role="tab" style="padding: 10px 12px;">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon me-3" style="width:32px; height:32px; font-size:14px; background:rgba(0,0,0,0.05);">
                            <i class="fas fa-${info.icon}"></i>
                        </div>
                        <div>
                            <div class="fw-700" style="font-size: 12px; line-height: 1.2;">${info.label}</div>
                            <div class="text-xs text-muted fw-400 d-none d-xl-block" style="font-size: 10px; opacity: 0.7;">${info.desc}</div>
                        </div>
                    </div>
                </button>`;

            let settingsHtml = '';
            groups[cat].forEach(s => {
                let restrict = '';
                let inputType = 'text';
                let inputHtml = '';
                
                if (['min_pax', 'max_pax', 'min_lead_time_days', 'rush_threshold_hours'].includes(s.key)) {
                    restrict = 'number';
                }
                
                if (s.key.includes('percent')) {
                    restrict = 'price';
                }
                
                if (s.key.includes('hours_start') || s.key.includes('hours_end')) {
                    inputType = 'time';
                }

                if (['terms_and_conditions', 'data_privacy_notice', 'payment_instructions', 'business_address'].includes(s.key)) {
                    inputHtml = `
                        <textarea class="form-control form-control-sm" style="max-width:100%; min-height:80px;" 
                                  id="set_${s.key}">${s.value}</textarea>`;
                } else {
                    inputHtml = `
                        <input class="form-control form-control-sm" style="max-width:300px;" 
                               type="${inputType}" id="set_${s.key}" value="${s.value}" 
                               ${restrict ? `data-restrict="${restrict}"` : ''}>`;
                }
                
                settingsHtml += `
                    <div class="setting-item">
                        <label class="setting-label" for="set_${s.key}">${s.key.replace(/_/g, ' ').toUpperCase()}</label>
                        <div class="setting-desc">${s.description || ''}</div>
                        ${instructions[s.key] ? `<div class="text-xs text-muted mb-2" style="font-style:italic;">Note: ${instructions[s.key]}</div>` : ''}
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <div style="flex: 1; max-width: 400px;">${inputHtml}</div>
                            <button class="btn btn-primary btn-sm px-3" onclick="updateSetting('${s.key}')">
                                <i class="fas fa-save me-1"></i> Save
                            </button>
                        </div>
                    </div>
                `;
            });

            contentHtml += `
                <div class="tab-pane fade ${isActive ? 'show active' : ''}" id="${tabId}" role="tabpanel">
                    <div class="mb-4">
                        <h5 class="fw-800 text-uppercase text-sm mb-1" style="letter-spacing: 1px; color: var(--sys-green);">
                             <i class="fas fa-${info.icon} me-2"></i> ${info.label}
                        </h5>
                        <div class="text-xs text-muted fw-500">${info.desc}</div>
                    </div>
                    ${settingsHtml}
                </div>`;
        });

        tabsContainer.innerHTML = tabsHtml;
        contentContainer.innerHTML = contentHtml;
        
        // Apply restrictions
        contentContainer.querySelectorAll('[data-restrict]').forEach(el => Form.restrictInput(el, el.dataset.restrict));
        
    } catch (e) { Toast.error(e.message); }
}

async function updateSetting(key) {
    const el = document.getElementById('set_' + key);
    const val = el.value;
    try {
        await Api.put(BASE + 'src/api/settings.php', { key, value: val });
        Toast.success('Setting updated.');
    } catch (e) { Toast.error(e.message); }
}

initTableSearch = null;
loadSettings();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
