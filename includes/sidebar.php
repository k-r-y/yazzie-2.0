<?php
/**
 * macOS-Style Vibrancy Sidebar
 * Renders role-specific nav based on the user's session role.
 */
$user       = getCurrentUser();
$role       = $user['role']  ?? 'staff';
$name       = $user['name']  ?? 'User';
$initials   = getInitials($name);
$roleLabel  = getRoleLabel($role);
$active     = $activePage ?? '';
$base       = BASE_URL;

/**
 * Nav items per role
 * [id, label, icon, url]
 */
$nav = [
    'admin' => [
        ['section' => 'Overview'],
        ['dashboard',   'Dashboard',    'fa-chart-line',   '/views/admin/dashboard.php'],
        ['section' => 'Manage'],
        ['bookings',    'Bookings',          'fa-calendar-days',  '/views/admin/bookings.php'],
        ['packages',    'Package Pricing',   'fa-tags',           '/views/admin/packages.php'],
        ['dishes',      'Food & Menu',       'fa-utensils',       '/views/admin/dishes.php'],
        ['recipes',     'Recipes & Costing', 'fa-flask',          '/views/admin/recipes.php'],
        ['inventory',   'Inventory Items',   'fa-boxes-stacked',  '/views/admin/inventory.php'],
        ['financial',   'Financials',   'fa-coins',        '/views/admin/financial.php'],
        ['section' => 'Human Resources'],
        ['staff',       'Staff',        'fa-id-badge',     '/views/admin/staff.php'],
        ['section' => 'System'],
        ['profit',      'Profit Guard', 'fa-chart-pie',    '/views/admin/profit.php'],
        ['archive',     'Archive',      'fa-box-archive',  '/views/admin/archive.php'],
    ],
    'frontdesk' => [
        ['section' => 'Overview'],
        ['dashboard',   'Dashboard',    'fa-house',        '/views/frontdesk/dashboard.php'],
        ['section' => 'Operations'],
        ['bookings',    'Bookings',     'fa-calendar-days','/views/frontdesk/bookings.php'],
        ['dispatching', 'Staff Dispatching', 'fa-bullhorn',  '/views/frontdesk/dispatching.php'],
        ['costing',     'Grocery List', 'fa-cart-shopping','/views/frontdesk/costing.php'],
    ],
    'staff' => [
        ['section' => 'My Jobs'],
        ['dashboard',     'Job Board',      'fa-briefcase',        '/views/staff/dashboard.php'],
        ['event_report',  'Event Report',   'fa-clipboard-check',  '/views/staff/event_report.php'],
    ],
];

$items = $nav[$role] ?? $nav['staff'];

// ── Superadmin specific additions ───────────────────────────
if ($role === 'super_admin') {
    // Ensure we start with the admin list if we don't have a dedicated super_admin entry
    if ($role === 'super_admin' && !isset($nav['super_admin'])) {
        $items = $nav['admin']; 
        
        // Add Superadmin Console at the very beginning
        array_unshift($items, ['section' => 'Super Admin Control']);
        array_splice($items, 1, 0, [['superadmin', 'Superadmin Console', 'fa-shield-halved', '/views/admin/superadmin.php']]);

        // Add User Management before Archive
        $archiveIdx = -1;
        foreach($items as $idx => $item) { if(($item[0] ?? '') === 'archive') $archiveIdx = $idx; }
        if($archiveIdx !== -1) {
            array_splice($items, $archiveIdx, 0, [['users', 'User Accounts', 'fa-users', '/views/admin/users.php']]);
        }
    }
}
?>

<!-- Sidebar Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar pt-3" id="sidebar">

    <!-- Brand -->
  

    <!-- User -->
    <div class="sidebar-user">
        <div class="sidebar-avatar"><?= $initials ?></div>
        <div>
            <div class="sidebar-user-name"><?= htmlspecialchars($name) ?></div>
            <div class="sidebar-user-role"><?= $roleLabel ?></div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav" role="navigation" aria-label="Main navigation">
        <?php foreach ($items as $item): ?>
            <?php if (isset($item['section'])): ?>
                <div class="sidebar-section"><?= htmlspecialchars($item['section']) ?></div>
            <?php else: ?>
                <?php
                    [$id, $label, $icon, $path] = $item;
                    $isActive = ($active === $id);
                ?>
                <a href="<?= $base . $path ?>"
                   class="sidebar-link <?= $isActive ? 'active' : '' ?>"
                   aria-current="<?= $isActive ? 'page' : 'false' ?>">
                    <i class="fas <?= $icon ?>"></i>
                    <span><?= $label ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="<?= $base ?>/logout.php" class="sidebar-logout">
            <i class="fas fa-rectangle-xmark"></i>
            <span>Sign Out</span>
        </a>
    </div>

</aside>

<!-- Main content wrapper start -->
<div class="main-area">

    <!-- Page Header (iOS Navigation Bar) -->
    <header class="page-header">
        <div class="page-header-left">
            <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation menu" aria-expanded="false">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="page-title"><?= htmlspecialchars($pageTitle  ?? 'Dashboard') ?></div>
                <?php if (!empty($pageSubtitle)): ?>
                    <div class="page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="page-header-right">
            <span class="header-date" id="headerDate"></span>
            <!-- Notification Bell -->
            <div style="position:relative;" id="notifWrap">
                <button id="notifBell" onclick="toggleNotifPanel()"
                    style="position:relative;background:none;border:none;cursor:pointer;padding:6px;color:var(--label-2);font-size:16px;line-height:1;display:flex;align-items:center;gap:4px;"
                    aria-label="Notifications">
                    <i class="fas fa-bell"></i>
                    <span id="notifBadge" style="display:none;position:absolute;top:2px;right:2px;min-width:15px;height:15px;border-radius:99px;background:#FF3B30;color:#fff;font-size:9px;font-weight:800;line-height:15px;text-align:center;padding:0 4px;"></span>
                </button>
                <!-- Dropdown panel -->
                <div id="notifPanel" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:360px;background:var(--glass-ultra);backdrop-filter:var(--glass-blur-hvy);-webkit-backdrop-filter:var(--glass-blur-hvy);border:0.5px solid var(--glass-sep);border-radius:16px;box-shadow:var(--shadow-xl);z-index:9999;overflow:hidden;">
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:0.5px solid var(--glass-sep);">
                        <span style="font-size:13px;font-weight:700;">Notifications</span>
                        <button onclick="markAllRead()" style="font-size:11px;font-weight:600;color:var(--sys-green-dark);background:none;border:none;cursor:pointer;">Mark all read</button>
                    </div>
                    <div id="notifList" style="max-height:320px;overflow-y:auto;"><div class="spinner" style="margin:20px auto;"></div></div>
                </div>
            </div>
            <span class="header-badge"><?= $roleLabel ?></span>
        </div>
    </header>

    <!-- Content -->
    <main class="content-area">

<script>
// Live date in header
(function () {
    const el = document.getElementById('headerDate');
    if (!el) return;
    const d = new Date();
    el.textContent = d.toLocaleDateString('en-PH', { weekday:'short', month:'short', day:'numeric' });
})();

// ── Mobile sidebar toggle ───────────────────────────────────────────
(function () {
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebarOverlay');
    const menuBtn  = document.getElementById('mobileMenuBtn');
    if (!sidebar || !overlay || !menuBtn) return;

    function openSidebar() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('active');
        menuBtn.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
        menuBtn.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    menuBtn.addEventListener('click', function () {
        if (sidebar.classList.contains('mobile-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    // Clicking the overlay closes the sidebar
    overlay.addEventListener('click', closeSidebar);

    // ESC key closes the sidebar
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('mobile-open')) {
            closeSidebar();
        }
    });

    // Close sidebar when a nav link is clicked (single-page navigation)
    sidebar.querySelectorAll('.sidebar-link').forEach(function(link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 768) closeSidebar();
        });
    });
})();

// ── Notification Bell ───────────────────────────────────────────────
(function () {
    const BASE_URL = '<?= BASE_URL ?>';
    const panel    = document.getElementById('notifPanel');
    const badge    = document.getElementById('notifBadge');
    let panelOpen  = false;

    async function fetchCount() {
        try {
            const r = await fetch(BASE_URL + '/src/api/notifications.php?unread=1', { credentials: 'same-origin' });
            const d = await r.json();
            const n = d.count || 0;
            if (n > 0) {
                badge.textContent = n > 9 ? '9+' : n;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        } catch(e) {}
    }

    async function fetchNotifs() {
        const list = document.getElementById('notifList');
        try {
            const r = await fetch(BASE_URL + '/src/api/notifications.php', { credentials: 'same-origin' });
            const d = await r.json();
            const notifs = d.notifications || [];
            if (!notifs.length) {
                list.innerHTML = '<p style="text-align:center;font-size:12px;color:#9ca3af;padding:20px 16px;">No notifications yet.</p>';
                return;
            }
            const typeIcon = { job_assigned: '📋', job_declined: '⚠️', leave_approved: '✅', leave_rejected: '❌', leave_reviewed: '📝', general: 'ℹ️' };
            list.innerHTML = notifs.map(n => {
                // Determine icon dynamically and clean title
                let icon = typeIcon[n.type] || 'ℹ️';
                let cleanTitle = n.title || '';
                
                if (n.type === 'general') {
                    if (n.title.includes('Event Reminder')) {
                        icon = '📅';
                        cleanTitle = cleanTitle.replace('📅', '').trim();
                    }
                    if (n.title.includes('Pending Balance')) {
                        icon = '💰';
                        cleanTitle = cleanTitle.replace('💰', '').trim();
                    }
                }

                // Build destination URL for click-to-navigate
                const BASE_URL_JS = '<?= BASE_URL ?>';
                let destUrl = null;
                if (n.link_url) {
                    destUrl = n.link_url;
                } else if (n.type === 'job_assigned' || n.type === 'job_declined') {
                    destUrl = BASE_URL_JS + '/views/staff/dashboard.php';
                } else if (n.type === 'leave_approved' || n.type === 'leave_rejected' || n.type === 'leave_reviewed') {
                    destUrl = BASE_URL_JS + '/views/staff/dashboard.php';
                } else if (n.type === 'general' && n.booking_id) {
                    // Admin/Frontdesk: route to booking details
                    const role = '<?= $_SESSION["role"] ?? "staff" ?>';
                    destUrl = role === 'staff'
                        ? BASE_URL_JS + '/views/staff/dashboard.php'
                        : BASE_URL_JS + '/views/admin/bookings.php?highlight=' + n.booking_id;
                }
                const clickAttr = destUrl
                    ? `onclick="markReadAndNavigate(${n.id}, '${destUrl}', this)"`
                    : `onclick="markRead(${n.id}, this)"`;
                return `
                <div ${clickAttr}
                     style="padding:11px 14px;border-bottom:0.5px solid var(--glass-sep);cursor:pointer;transition:background .15s;background:${n.is_read ? 'transparent' : 'rgba(48,209,88,0.04)'};"
                     onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='${n.is_read ? 'transparent' : 'rgba(48,209,88,0.04)'}'">
                    <div style="display:flex;gap:8px;align-items:flex-start;">
                        <span style="font-size:15px;flex-shrink:0;">${icon}</span>
                        <div style="min-width:0;">
                            <div style="font-size:12px;font-weight:${n.is_read ? '500' : '700'};color:var(--label-1);margin-bottom:2px;">${esc(cleanTitle)}</div>
                            ${n.body ? `<div style="font-size:11px;color:var(--label-3);white-space:normal;">${esc(n.body)}</div>` : ''}
                            <div style="font-size:10px;color:var(--label-4);margin-top:3px;">${new Date(n.created_at).toLocaleDateString('en-PH',{month:'short',day:'numeric',hour:'2-digit',minute:'2-digit'})}</div>
                        </div>
                        ${!n.is_read ? '<span style="width:7px;height:7px;border-radius:50%;background:#30D158;flex-shrink:0;margin-top:4px;"></span>' : ''}
                    </div>
                </div>`;
            }).join('');
        } catch(e) { list.innerHTML = '<p style="text-align:center;font-size:12px;color:#9ca3af;padding:16px;">Failed to load.</p>'; }
    }

    window.toggleNotifPanel = function() {
        panelOpen = !panelOpen;
        panel.style.display = panelOpen ? 'block' : 'none';
        if (panelOpen) fetchNotifs();
    };

    window.markRead = async function(id, el) {
        el.style.background = 'transparent';
        el.querySelector('span[style*="30D158"]')?.remove();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            await fetch(BASE_URL + '/src/api/notifications.php', {
                method: 'PUT', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ id })
            });
            fetchCount();
        } catch(e) {}
    };

    // Click notification → mark read THEN navigate to the relevant page
    window.markReadAndNavigate = async function(id, url, el) {
        el.style.background = 'transparent';
        el.querySelector('span[style*="30D158"]')?.remove();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            await fetch(BASE_URL + '/src/api/notifications.php', {
                method: 'PUT', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ id })
            });
            // Try to optimistically adjust the badge immediately so it looks fast
            let currentUnread = parseInt(badge.textContent) || 0;
            if (currentUnread > 1) { badge.textContent = currentUnread - 1; } 
            else { badge.style.display = 'none'; }
        } catch(e) {}
        
        // Close panel and navigate
        panelOpen = false;
        panel.style.display = 'none';
        window.location.href = url;
    };

    window.markAllRead = async function() {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        try {
            await fetch(BASE_URL + '/src/api/notifications.php', {
                method: 'PUT', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ mark_all: true })
            });
            badge.style.display = 'none';
            fetchNotifs();
        } catch(e) {}
    };

    // Close panel when clicking outside
    document.addEventListener('click', function(e) {
        if (panelOpen && !document.getElementById('notifWrap').contains(e.target)) {
            panelOpen = false;
            panel.style.display = 'none';
        }
    });

    // Initial fetch + poll every 60s
    fetchCount();
    setInterval(fetchCount, 60000);
})();
</script>
