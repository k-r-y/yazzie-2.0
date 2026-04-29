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
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header">
                <div class="card-title"><i class="fas fa-sliders me-2"></i>Business Configuration</div>
                <div class="card-subtitle">Values used for cost estimation and automated scheduling</div>
            </div>
            <div class="card-body">
                <div id="settingsContainer">
                    <div class="spinner my-4"></div>
                </div>
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
        'extra_pax_rate': 'Charge per additional guest beyond the package base.',
        'staff_hourly_rate': 'Base hourly pay used for internal cost calculations.',
        'overtime_rate_per_hour': 'Additional hourly charge for events exceeding standard duration.',
        'event_duration_hours': 'Standard number of hours included in base package pricing.',
        'min_lead_time_days': 'Days notice required before an event date can be selected.',
        'rush_threshold_hours': 'Timeframe (in hours) where stricter payment rules apply.',
        'meal_breakfast_start': 'Hour (0-23) when breakfast service typically begins.',
        'meal_lunch_start': 'Hour (0-23) when lunch service typically begins.',
        'meal_dinner_start': 'Hour (0-23) when dinner service typically begins.',
        'extra_main_rate': 'Price for each additional main course selected.',
        'extra_dessert_rate': 'Price for each additional dessert item.',
        'extra_rice_rate': 'Price for unlimited rice upgrades or extra servings.',
        'maintenance_mode': 'When enabled, only administrators can access the system.',
        'debug_mode': 'Enables detailed error reporting (for developers only).',
        'mail_enabled': 'Master switch for all system email notifications.',
        'smtp_secure': 'The encryption protocol used for email delivery (typically TLS).',
        'system_timezone': 'The default timezone for all event logs and schedules.',
        'business_name': 'The official name of your catering business as it appears on invoices.',
        'business_address': 'The physical or mailing address printed on client documents.',
        'terms_and_conditions': 'The fine print and legal terms shown at the bottom of invoices and contracts.',
        'payment_instructions': 'Detailed instructions for clients on how to settle their payments (e.g., GCash, Bank details).'
    };

    try {
        const d = await Api.get(BASE + 'src/api/settings.php');
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
                let restrict = '';
                let inputType = 'text';
                let inputHtml = '';
                
                // Numeric fields
                if (['min_pax', 'max_pax', 'event_duration_hours', 'min_lead_time_days', 'rush_threshold_hours', 'max_admins', 'max_file_upload_mb'].includes(s.key)) {
                    restrict = 'number';
                }
                
                // Price/Percentage fields
                if (s.type === 'float' || s.key.includes('rate') || s.key.includes('fee') || s.key.includes('percent')) {
                    restrict = 'price';
                }
                
                if (s.key.includes('hours_start') || s.key.includes('hours_end')) {
                    inputType = 'time';
                    restrict = '';
                }

                // Handle specialized input types
                if (s.key === 'smtp_secure') {
                    inputHtml = `
                        <select class="form-select form-select-sm" style="max-width:200px;" id="set_${s.key}">
                            <option value="tls" ${s.value === 'tls' ? 'selected' : ''}>TLS</option>
                            <option value="ssl" ${s.value === 'ssl' ? 'selected' : ''}>SSL</option>
                            <option value="none" ${s.value === 'none' ? 'selected' : ''}>None</option>
                        </select>`;
                } else if (['terms_and_conditions', 'payment_instructions', 'business_address'].includes(s.key)) {
                    inputHtml = `
                        <textarea class="form-control form-control-sm" style="max-width:400px; min-height:100px;" 
                                  id="set_${s.key}" title="Update ${s.key.replace(/_/g, ' ')}">${s.value}</textarea>`;
                } else if (['debug_mode', 'maintenance_mode', 'mail_enabled'].includes(s.key)) {
                    inputHtml = `
                        <select class="form-select form-select-sm" style="max-width:200px;" id="set_${s.key}">
                            <option value="1" ${s.value == 1 ? 'selected' : ''}>Enabled / Yes</option>
                            <option value="0" ${s.value == 0 ? 'selected' : ''}>Disabled / No</option>
                        </select>`;
                } else {
                    inputHtml = `
                        <input class="form-control form-control-sm" style="max-width:200px;" 
                               type="${inputType}" id="set_${s.key}" value="${s.value}" 
                               ${restrict ? `data-restrict="${restrict}"` : ''} 
                               title="Update ${s.key.replace(/_/g, ' ')}">`;
                }
                
                html += `
                    <div class="setting-item ">
                        <label class="setting-label" for="set_${s.key}">${s.key.replace(/_/g, ' ').toUpperCase()}</label>
                        <div class="setting-desc">${s.description}</div>
                        ${instructions[s.key] ? `<div class="text-xs text-muted mb-2" style="font-style:italic;">Note: ${instructions[s.key]}</div>` : ''}
                        <div class="d-flex align-items-start flex-column  gap-2">
                            ${inputHtml}
                            <button class="btn btn-primary btn-sm p-3" onclick="updateSetting('${s.key}')" title="Save this setting permanently">
                                <i class="fas fa-save"></i> Save
                            </button>
                        </div>
                    </div>
                `;
            });
        }
        if (!html) html = '<div class="text-muted text-sm text-center">No business settings found.</div>';
        container.innerHTML = html;
        // Apply restrictions to newly created elements
        container.querySelectorAll('[data-restrict]').forEach(el => Form.restrictInput(el, el.dataset.restrict));
    } catch (e) { Toast.error(e.message); }
}

async function updateSetting(key) {
    const val = document.getElementById('set_' + key).value;
    try {
        await Api.put(BASE + 'src/api/settings.php', { key, value: val });
        Toast.success('Setting updated.');
    } catch (e) { Toast.error(e.message); }
}

initTableSearch = null; // Disable table search helper
loadSettings();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
