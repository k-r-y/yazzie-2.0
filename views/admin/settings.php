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
                let restrict = 'number';
                if (s.type === 'float' || s.key.includes('rate') || s.key.includes('fee')) restrict = 'price';
                
                html += `
                    <div class="setting-item">
                        <label class="setting-label" for="set_${s.key}">${s.key.replace(/_/g, ' ').toUpperCase()}</label>
                        <div class="setting-desc">${s.description}</div>
                        <div class="d-flex gap-2">
                            <input class="form-control form-control-sm" style="max-width:200px;" 
                                   type="text" id="set_${s.key}" value="${s.value}" 
                                   data-restrict="${restrict}" title="Update ${s.key.replace(/_/g, ' ')}">
                            <button class="btn btn-primary btn-sm px-3" onclick="updateSetting('${s.key}')" title="Save this setting">
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
