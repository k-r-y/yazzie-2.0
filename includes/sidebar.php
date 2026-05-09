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

// ── Passive Cron: Check for upcoming unpaid bookings ────────────────
// super_admin has been retired; admin is now the top-tier role.
if (in_array($role, ['admin', 'frontdesk'])) {
    if (!isset($_SESSION['last_payment_check']) || (time() - $_SESSION['last_payment_check'] > 1800)) { // Every 30 mins
        try {
            require_once __DIR__ . '/notifications_helper.php';
            checkUpcomingUnpaidBookings($pdo);
            $_SESSION['last_payment_check'] = time();
        } catch (Throwable $e) {
            error_log("Payment alert check failed: " . $e->getMessage());
        }
    }
}

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
        ['clients',     'Clients',           'fa-user-group',     '/views/admin/clients.php'],
        ['packages',    'Package Pricing',   'fa-tags',           '/views/admin/packages.php'],
        ['dishes',      'Food & Menu',       'fa-utensils',       '/views/admin/dishes.php'],
        ['recipes',     'Recipes & Costing', 'fa-flask',          '/views/admin/recipes.php'],
        ['inventory',   'Inventory Items',   'fa-boxes-stacked',  '/views/admin/inventory.php'],
        ['financial',   'Financials',   'fa-coins',        '/views/admin/financial.php'],
        ['section' => 'Human Resources'],
        ['dispatching', 'Staff Dispatching',      'fa-bullhorn',     '/views/frontdesk/dispatching.php'],
        // Unified module: User & Staff Management (staff.php is canonical, users.php removed)
        ['staff',       'User & Staff Management', 'fa-id-badge',     '/views/admin/staff.php'],
        ['section' => 'Business Rules'],
        ['settings',    'Business Settings',       'fa-sliders',      '/views/admin/settings.php'],
        // System Settings transferred from super_admin to admin
        ['superadmin',    'System Settings',   'fa-shield-halved',  '/views/admin/superadmin.php'],
        ['activity_log',  'Activity Log',      'fa-rectangle-list', '/views/admin/activity_log.php'],
        ['archive',       'Archive',           'fa-box-archive',    '/views/admin/archive.php'],
    ],
    'frontdesk' => [
        ['section' => 'Overview'],
        ['dashboard',   'Dashboard',    'fa-house',        '/views/frontdesk/dashboard.php'],
        ['section' => 'Operations'],
        ['bookings',    'Bookings',     'fa-calendar-days','/views/frontdesk/bookings.php'],
        ['financial',   'Financials',   'fa-coins',        '/views/admin/financial.php'],
        ['dispatching', 'Staff Dispatching', 'fa-bullhorn',  '/views/frontdesk/dispatching.php'],
        ['costing',     'Grocery List', 'fa-cart-shopping','/views/frontdesk/costing.php'],
    ],
    'staff' => [
        ['section' => 'My Jobs'],
        ['dashboard',     'Job Board',      'fa-briefcase',        '/views/staff/dashboard.php'],
        ['event_report',  'Event Report',   'fa-clipboard-check',  '/views/staff/event_report.php'],
    ],
];

// Resolve nav items for the current role.
// super_admin has been retired — admin is the highest tier and its
// nav items are defined directly in the $nav array above.
$items = $nav[$role] ?? $nav['staff'];
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

// ── Notification Bell v2 — Deep Linking & Role-Based ───────────────────────
(function () {
    const BASE_URL = '<?= BASE_URL ?>';
    const panel    = document.getElementById('notifPanel');
    const badge    = document.getElementById('notifBadge');
    let panelOpen  = false;

    // ── Icon map for the v2 `type` field ─────────────────────────────────
    const typeIconMap = {
        user_management : '🛡️',
        booking         : '📅',
        finance         : '💰',
        dispatch        : '📋',
        system          : '⚙️',
        general         : 'ℹ️',
    };

    // ── Safely escape HTML to prevent XSS in notification content ─────────
    function esc(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ── fetchCount() — runs on page load + every 60 s for the badge ───────
    async function fetchCount() {
        try {
            const r = await fetch(BASE_URL + '/api/notifications/get.php?unread=1', {
                credentials: 'same-origin'
            });
            if (!r.ok) return;
            const d = await r.json();
            const n = d.count ?? d.unread_count ?? 0;
            badge.textContent     = n > 9 ? '9+' : n;
            badge.style.display   = n > 0 ? 'flex' : 'none';
        } catch (e) { /* silent fail — network may be unreliable */ }
    }

    // ── fetchNotifications() — called when panel opens ────────────────────
    async function fetchNotifications() {
        const list = document.getElementById('notifList');
        list.innerHTML = '<div class="spinner" style="margin:20px auto;"></div>';
        try {
            const r = await fetch(BASE_URL + '/api/notifications/get.php', {
                credentials: 'same-origin'
            });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const d = await r.json();
            if (!d.success) throw new Error(d.message || 'Server error');

            const notifs = d.notifications || [];
            if (!notifs.length) {
                list.innerHTML = '<p style="text-align:center;font-size:12px;color:#9ca3af;padding:20px 16px;">No notifications yet.</p>';
                return;
            }

            list.innerHTML = notifs.map(n => {
                const icon     = typeIconMap[n.type] || 'ℹ️';
                const isRead   = n.is_read == 1;
                const timeStr  = new Date(n.created_at.replace(' ', 'T'))
                    .toLocaleDateString('en-PH', { month:'short', day:'numeric', hour:'2-digit', minute:'2-digit' });

                // Deep-link: prefer server-supplied action_url; fall back to BASE_URL prefix
                let destUrl = null;
                if (n.action_url) {
                    // Relative URLs get the base prepended; absolute URLs used as-is
                    destUrl = n.action_url.startsWith('http')
                        ? n.action_url
                        : BASE_URL + n.action_url;
                }

                const clickHandler = destUrl
                    ? `markAsReadAndRedirect(${n.id}, '${destUrl.replace(/'/g, "\\'")}')`
                    : `markSingleRead(${n.id}, this)`;

                return `
                <div onclick="${clickHandler}"
                     data-notif-id="${n.id}"
                     style="
                         padding:11px 14px;
                         border-bottom:0.5px solid var(--glass-sep);
                         cursor:pointer;
                         transition:background .15s;
                         background:${isRead ? 'transparent' : 'rgba(48,209,88,0.04)'};
                     "
                     onmouseover="this.style.background='var(--surface-2)'"
                     onmouseout="this.style.background='${isRead ? 'transparent' : 'rgba(48,209,88,0.04)'}'">
                    <div style="display:flex;gap:8px;align-items:flex-start;">
                        <span style="font-size:15px;flex-shrink:0;">${icon}</span>
                        <div style="min-width:0;flex:1;">
                            <div style="font-size:12px;font-weight:${isRead ? '500' : '700'};color:var(--label-1);margin-bottom:2px;">
                                ${esc(n.message)}
                            </div>
                            <div style="font-size:10px;color:var(--label-4);margin-top:3px;">
                                ${esc(timeStr)}
                            </div>
                        </div>
                        ${!isRead ? '<span style="width:7px;height:7px;border-radius:50%;background:#30D158;flex-shrink:0;margin-top:4px;"></span>' : ''}
                    </div>
                </div>`;
            }).join('');
        } catch (e) {
            list.innerHTML = '<p style="text-align:center;font-size:12px;color:#9ca3af;padding:16px;">Failed to load notifications.</p>';
        }
    }

    // ── markAsReadAndRedirect() — Phase 4 deep-link handler ───────────────
    //
    // 1. POSTs to /api/notifications/read.php with the notification ID.
    // 2. Optimistically updates the badge counter immediately.
    // 3. On POST success (or failure — we don't block navigation), redirects
    //    the user to actionUrl via window.location.href.
    window.markAsReadAndRedirect = async function (notificationId, actionUrl) {
        const csrf = document.querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || '';

        // Optimistic badge decrement
        const current = parseInt(badge.textContent, 10) || 0;
        if (current > 1) { badge.textContent = current - 1; }
        else             { badge.style.display = 'none'; }

        // Close the dropdown immediately for snappy UX
        panelOpen = false;
        panel.style.display = 'none';

        // Fire-and-forget POST — navigation is not blocked by the result
        fetch(BASE_URL + '/api/notifications/read.php', {
            method      : 'POST',
            credentials : 'same-origin',
            headers     : {
                'Content-Type' : 'application/json',
                'X-CSRF-Token' : csrf,
            },
            body: JSON.stringify({ id: notificationId }),
        }).catch(() => { /* silent — navigation already underway */ });

        // Deep-link the user to the related content
        window.location.href = actionUrl;
    };

    // ── markSingleRead() — for notifications without an action_url ─────────
    window.markSingleRead = async function (notificationId, el) {
        const csrf = document.querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || '';
        // Visual feedback
        if (el) {
            el.style.background = 'transparent';
            el.querySelector('span[style*="30D158"]')?.remove();
        }
        try {
            await fetch(BASE_URL + '/api/notifications/read.php', {
                method      : 'POST',
                credentials : 'same-origin',
                headers     : {
                    'Content-Type' : 'application/json',
                    'X-CSRF-Token' : csrf,
                },
                body: JSON.stringify({ id: notificationId }),
            });
            fetchCount();
        } catch (e) { /* silent */ }
    };

    // ── markAllRead() ─────────────────────────────────────────────────────
    window.markAllRead = async function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || '';
        try {
            await fetch(BASE_URL + '/api/notifications/read.php', {
                method      : 'POST',
                credentials : 'same-origin',
                headers     : {
                    'Content-Type' : 'application/json',
                    'X-CSRF-Token' : csrf,
                },
                body: JSON.stringify({ mark_all: true }),
            });
            badge.style.display = 'none';
            fetchNotifications(); // Refresh the panel list
        } catch (e) { /* silent */ }
    };

    // ── toggleNotifPanel() ────────────────────────────────────────────────
    window.toggleNotifPanel = function () {
        panelOpen = !panelOpen;
        panel.style.display = panelOpen ? 'block' : 'none';
        if (panelOpen) fetchNotifications();
    };

    // Close panel when clicking outside
    document.addEventListener('click', function (e) {
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
