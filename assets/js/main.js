/**
 * Yazzies Catering OMS — Shared JavaScript Utilities
 */

/* ================================================================
   XSS PREVENTION
   ================================================================ */
/**
 * Escape a string for safe HTML rendering.
 * Use this whenever inserting dynamic data into innerHTML or template literals.
 */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str)));
    return div.innerHTML;
}

/** Alias for template literal usage: ${esc(value)} */
const esc = escapeHtml;

/* ================================================================
   SWEETALERT / TOAST NOTIFICATIONS
   ================================================================ */
const Toast = {
    show(message, type = 'info', duration = 3000) {
        // Render success messages as a beautiful center popup
        if (type === 'success') {
            return Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                showConfirmButton: false,
                timer: 2000,
                timerProgressBar: true,
                padding: '2em',
                background: 'rgba(255, 255, 255, 0.95)',
                backdrop: `rgba(0,0,0,0.45)`,
                showClass: { popup: 'animate__animated animate__fadeInUp animate__faster' },
                hideClass: { popup: 'animate__animated animate__fadeOutDown animate__faster' },
                customClass: { popup: 'swal2-bento' }
            });
        }
        
        // Render errors/warnings as top-end toasts so it doesn't trap the user
        return Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: duration,
            timerProgressBar: true,
            background: 'rgba(255, 255, 255, 0.98)',
            color: 'var(--label)',
            padding: '12px 16px',
            showClass: { popup: 'animate__animated animate__slideInRight animate__faster' },
            hideClass: { popup: 'animate__animated animate__slideOutRight animate__faster' },
            customClass: { popup: 'swal2-bento-toast' }
        });
    },

    success(msg, dur)  { return this.show(msg, 'success', dur); },
    error(msg, dur)    { return this.show(msg, 'error',   dur); },
    warning(msg, dur)  { return this.show(msg, 'warning', dur); },
    info(msg, dur)     { return this.show(msg, 'info',    dur); }
};

/* ================================================================
   FETCH API WRAPPER
   ================================================================ */
const Api = {
    async request(url, options = {}) {
        const defaults = {
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
        };
        const config = Object.assign({}, defaults, options);
        if (config.headers && options.headers) {
            config.headers = Object.assign({}, defaults.headers, options.headers);
        }

        // ── Auto-inject CSRF token for state-changing methods ──
        const method = (config.method || 'GET').toUpperCase();
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            if (csrfMeta) {
                config.headers['X-CSRF-Token'] = csrfMeta.getAttribute('content');
            }
        }

        try {
            const res   = await fetch(url, config);
            const text  = await res.text();
            const data  = text ? JSON.parse(text) : {};

            if (!res.ok) {
                throw new Error(data.message || `HTTP ${res.status}`);
            }
            return data;
        } catch (err) {
            if (err instanceof SyntaxError) {
                throw new Error('Invalid response from server.');
            }
            throw err;
        }
    },

    get(url, params = {}) {
        const qs = new URLSearchParams(params).toString();
        return this.request(qs ? `${url}?${qs}` : url);
    },

    post(url, body = {}) {
        return this.request(url, { method: 'POST', body: JSON.stringify(body) });
    },

    put(url, body = {}) {
        return this.request(url, { method: 'PUT', body: JSON.stringify(body) });
    },

    delete(url, body = {}) {
        return this.request(url, { method: 'DELETE', body: JSON.stringify(body) });
    },
};

/* ================================================================
   FORMATTING UTILITIES
   ================================================================ */
const Format = {
    /** Format a number as Philippine Peso */
    peso(amount, decimals = 2) {
        const n = parseFloat(amount) || 0;
        return '₱' + n.toLocaleString('en-PH', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    },

    /** Format a date string (YYYY-MM-DD → Month D, YYYY) */
    date(dateStr) {
        if (!dateStr || String(dateStr).startsWith('0000')) return '—';
        const s = String(dateStr);
        const match = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return '—';
        
        const [_, y, m, d] = match.map(Number);
        const dt = new Date(y, m - 1, d);
        if (isNaN(dt.getTime())) return 'Invalid Date';
        return dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' });
    },

    /** Format a date as short (Apr 14, 2026) */
    dateShort(dateStr) {
        if (!dateStr || String(dateStr).startsWith('0000')) return '—';
        const s = String(dateStr);
        const match = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (!match) return '—';

        const [_, y, m, d] = match.map(Number);
        const dt = new Date(y, m - 1, d);
        if (isNaN(dt.getTime())) return 'Invalid Date';
        return dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    },

    /** Format 24h time (HH:MM:SS → H:MM AM/PM) */
    time(timeStr) {
        if (!timeStr) return '—';
        const [h, m] = timeStr.split(':');
        const hour  = parseInt(h, 10);
        const ampm  = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${m} ${ampm}`;
    },

    /** 
     * Intelligent Unit Formatter & Scaler
     * Bidirectional conversion for kg/g and L/ml. Normalizes casing.
     */
    unit(qty, unitStr) {
        if (!unitStr) return qty;
        let u = String(unitStr).trim().toLowerCase();
        let val = parseFloat(qty) || 0;
        let finalUnit = u;

        // Weights
        if (['kg', 'kilogram', 'kilograms'].includes(u)) {
            if (val < 1.0 && val > 0) { val *= 1000; finalUnit = 'g'; }
            else finalUnit = 'kg';
        } else if (['g', 'gram', 'grams'].includes(u)) {
            if (val >= 1000) { val /= 1000; finalUnit = 'kg'; }
            else finalUnit = 'g';
        }
        // Volumes
        else if (['l', 'liter', 'liters', 'litre'].includes(u)) {
            if (val < 1.0 && val > 0) { val *= 1000; finalUnit = 'ml'; }
            else finalUnit = 'L';
        } else if (['ml', 'milliliter', 'milliliters'].includes(u)) {
            if (val >= 1000) { val /= 1000; finalUnit = 'L'; }
            else finalUnit = 'ml';
        }
        // Packaging
        else if (['pc', 'pcs', 'piece', 'pieces'].includes(u)) finalUnit = 'pcs';
        else if (['pack', 'packs', 'pck', 'pks'].includes(u)) finalUnit = 'packs';
        else if (['can', 'cans', 'cn'].includes(u)) finalUnit = 'cans';
        else if (['jar', 'jars'].includes(u)) finalUnit = 'jars';
        else if (['head', 'heads'].includes(u)) finalUnit = 'heads';
        else if (['bundle', 'bundles'].includes(u)) finalUnit = 'bundles';

        // Clean trailing decimals
        let fVal = Number(val.toFixed(3));
        return `${fVal} ${finalUnit}`;
    },

    /** Capitalize first letter */
    ucfirst(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    },

    /** Get payment status badge HTML */
    paymentBadge(status) {
        const map = {
            unpaid:  ['badge-unpaid',  '● Unpaid'],
            partial: ['badge-partial', '◑ Partial'],
            paid:    ['badge-paid',    '✓ Paid'],
        };
        const [cls, label] = map[status] || ['', status];
        return `<span class="badge ${cls}">${label}</span>`;
    },

    /** Get booking status badge HTML */
    bookingBadge(status) {
        const map = {
            confirmed: ['badge-confirmed', 'Confirmed'],
            completed: ['badge-completed', 'Completed'],
            cancelled: ['badge-cancelled', 'Cancelled'],
        };
        const [cls, label] = map[status] || ['badge-pending', status];
        return `<span class="badge ${cls}">${label}</span>`;
    },

    /** Get job order status badge HTML */
    jobBadge(status) {
        const map = {
            pending:  ['badge-pending',  'Pending'],
            accepted: ['badge-accepted', 'Accepted'],
            declined: ['badge-declined', 'Declined'],
        };
        const [cls, label] = map[status] || ['', status];
        return `<span class="badge ${cls}">${label}</span>`;
    },
};

/* ================================================================
   MODAL HELPERS (Bootstrap 5)
   ================================================================ */
const Modal = {
    open(id) {
        const el = document.getElementById(id);
        if (el) bootstrap.Modal.getOrCreateInstance(el).show();
    },

    close(id) {
        const el = document.getElementById(id);
        if (el) bootstrap.Modal.getInstance(el)?.hide();
    },

    closeAll() {
        document.querySelectorAll('.modal.show').forEach(el => {
            bootstrap.Modal.getInstance(el)?.hide();
        });
    },
};

/* ================================================================
   FORM UTILITIES
   ================================================================ */
const Form = {
    /** Read all values from a form element into an object */
    serialize(formEl) {
        const data = {};
        const fd   = new FormData(formEl);
        fd.forEach((v, k) => { data[k] = v; });
        return data;
    },

    /** Set button to loading state */
    setLoading(btnEl, loading = true, loadingText = 'Saving...') {
        if (!btnEl) return;
        if (loading) {
            btnEl._original = btnEl.innerHTML;
            btnEl.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> ${loadingText}`;
            btnEl.disabled  = true;
        } else {
            btnEl.innerHTML = btnEl._original || btnEl.innerHTML;
            btnEl.disabled  = false;
        }
    },

    /** Clear all validation states from a form */
    clearErrors(formEl) {
        formEl.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        formEl.querySelectorAll('.form-error').forEach(el => el.remove());
    },

    /** Mark a field as invalid with a message */
    showError(fieldEl, message) {
        fieldEl.classList.add('is-invalid');
        const err = document.createElement('div');
        err.className = 'form-error';
        err.textContent = message;
        fieldEl.closest('.form-group')?.appendChild(err);
    },
};

/* ================================================================
   TABLE SEARCH (client-side filter)
   ================================================================ */
function initTableSearch(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('input', function () {
        const q    = this.value.toLowerCase().trim();
        const rows = table.querySelectorAll('tbody tr');
        let visible = 0;

        rows.forEach(row => {
            if (row.dataset.empty) return;
            const text = row.textContent.toLowerCase();
            const show = !q || text.includes(q);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        // Show/hide empty state row
        const emptyRow = table.querySelector('tr[data-empty]');
        if (emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';
    });
}

/* ================================================================
   SIDEBAR — Mobile toggle
   ================================================================ */
function initSidebar() {
    const btn     = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (!btn || !sidebar) return;

    btn.addEventListener('click', () => {
        sidebar.classList.add('mobile-open');
        overlay?.classList.add('active');
    });

    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    });
}

/* ================================================================
   CONFIRM DIALOG (async)
   ================================================================ */
const CustomConfirm = {
    /**
     * Show a custom confirmation dialog with HTML content.
     * Captures all input/select/textarea values by ID and returns them.
     */
    show(options) {
        return Swal.fire({
            title: options.title || 'Confirm Action',
            html: options.html || '',
            icon: options.icon || null,
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Confirm',
            cancelButtonText: options.cancelButtonText || 'Cancel',
            confirmButtonColor: options.confirmColor || 'var(--sys-blue)',
            cancelButtonColor: options.cancelColor || '#aaa',
            reverseButtons: true,
            padding: '2em',
            background: 'rgba(255, 255, 255, 0.95)',
            color: 'var(--label)',
            showClass: { popup: 'animate__animated animate__zoomIn animate__faster' },
            hideClass: { popup: 'animate__animated animate__zoomOut animate__faster' },
            customClass: {
                popup: 'swal2-bento confirm-glass',
                confirmButton: 'btn btn-primary btn-lg',
                cancelButton: 'btn btn-secondary btn-lg'
            },
            preConfirm: () => {
                const data = {};
                const container = Swal.getHtmlContainer();
                if (!container) return data;
                
                container.querySelectorAll('input, select, textarea').forEach(el => {
                    if (el.id) {
                        data[el.id] = el.value;
                    }
                });
                return data;
            }
        }).then(result => {
            if (result.isConfirmed) {
                return result.value || true;
            }
            return false;
        });
    }
};

function confirmDialog(message, title = 'Confirm Action') {
    return new Promise(resolve => {
        // Use simple browser confirm; replace with modal if desired
        resolve(window.confirm(`${title}\n\n${message}`));
    });
}


/* ================================================================
   DEBOUNCE
   ================================================================ */
function debounce(fn, delay = 300) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

/* ================================================================
   AUTO-INIT on DOM Ready
   ================================================================ */
document.addEventListener('DOMContentLoaded', () => {
    initSidebar();

    // Set current year in footers
    document.querySelectorAll('.current-year').forEach(el => {
        el.textContent = new Date().getFullYear();
    });

    // Format all .js-peso elements
    document.querySelectorAll('[data-peso]').forEach(el => {
        const v = parseFloat(el.dataset.peso) || 0;
        el.textContent = Format.peso(v);
    });
});
