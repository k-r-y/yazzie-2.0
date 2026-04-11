<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

startSession();
if (isLoggedIn()) { redirectByRole($_SESSION['role']); }

$error   = htmlspecialchars($_GET['error'] ?? '');
$message = htmlspecialchars($_GET['msg']   ?? '');

$errorMessages = [
    'auth'      => 'Please sign in to access the system.',
    'forbidden' => 'You do not have permission to view that page.',
    'invalid'   => 'Incorrect email or password.',
    'inactive'  => 'Your account has been deactivated.',
];
$successMessages = ['logged_out' => "You've been signed out."];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;0,14..32,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:       #30D158;
            --green-dk:    #25A244;
            --green-tint:  rgba(48,209,88,0.12);
            --red:         #FF3B30;
            --label:       rgba(0,0,0,0.88);
            --label-2:     rgba(60,60,67,0.60);
            --label-3:     rgba(60,60,67,0.30);
            --fill-2:      rgba(120,120,128,0.16);
            --fill-3:      rgba(118,118,128,0.12);
            --fill-4:      rgba(116,116,128,0.08);
            --sep:         rgba(60,60,67,0.12);
            --shadow-xl:   0 20px 60px rgba(0,0,0,0.10), 0 4px 20px rgba(0,0,0,0.06);
        }

        html { -webkit-font-smoothing: antialiased; }

        body {
            font-family: -apple-system, 'SF Pro Text', 'Inter', 'Helvetica Neue', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #F2F2F7;
            overflow: hidden;
        }

        /* ── BACKGROUND ── */
        .bg {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 900px 700px at -10% 60%,
                    rgba(48,209,88,0.10) 0%, transparent 65%),
                radial-gradient(ellipse 600px 800px at 110% 40%,
                    rgba(48,209,88,0.06) 0%, transparent 65%),
                radial-gradient(ellipse 400px 400px at 50% 105%,
                    rgba(90,200,250,0.05) 0%, transparent 70%),
                #F2F2F7;
        }

        /* Subtle grid texture */
        .bg::after {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(60,60,67,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(60,60,67,0.025) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* ── LAYOUT ── */
        .container {
            display: flex;
            width: 100%;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        /* ── LEFT PANEL ── */
        .left {
            flex: 1.2;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 64px 72px;
            position: relative;
        }

        /* macOS window chrome decoration */
        .window-chrome {
            position: absolute;
            top: 280px;
            right: -20px;
            width: 340px;
            background: rgba(255,255,255,0.75);
            backdrop-filter: blur(40px) saturate(200%);
            -webkit-backdrop-filter: blur(40px) saturate(200%);
            border: 0.5px solid rgba(255,255,255,0.8);
            border-radius: 16px;
            box-shadow: var(--shadow-xl), 0 0 0 0.5px rgba(60,60,67,0.10);
            overflow: hidden;
            animation: floatCard 4s ease-in-out infinite;
            z-index: 2;
        }

        @keyframes floatCard {
            0%, 100% { transform: translateY(0) rotate(-1.5deg); }
            50%       { transform: translateY(-8px) rotate(-1.5deg); }
        }

        .chrome-bar {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 12px 14px;
            border-bottom: 0.5px solid var(--sep);
            background: rgba(242,242,247,0.7);
        }

        .chrome-dot {
            width: 10px; height: 10px;
            border-radius: 50%;
        }
        .chrome-dot.red    { background: #FF5F57; }
        .chrome-dot.yellow { background: #FFBD2E; }
        .chrome-dot.green  { background: #28CA41; }

        .chrome-title {
            flex: 1;
            text-align: center;
            font-size: 11px;
            font-weight: 600;
            color: var(--label-3);
            margin-left: -28px;
        }

        .chrome-body { padding: 14px 16px; }

        .chrome-stat {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            background: var(--fill-4);
            border-radius: 10px;
            margin-bottom: 8px;
        }

        .chrome-icon {
            width: 32px; height: 32px;
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
        }

        .chrome-icon.g { background: linear-gradient(145deg,#30D158,#25A244); color:#fff; }
        .chrome-icon.t { background: linear-gradient(145deg,#5AC8FA,#32ADE6); color:#fff; }
        .chrome-icon.o { background: linear-gradient(145deg,#FF9500,#E07800); color:#fff; }

        .chrome-stat-label  { font-size: 10px; color: var(--label-3); font-weight: 500; }
        .chrome-stat-value  { font-size: 15px; font-weight: 800; color: var(--label); letter-spacing: -0.5px; }

        /* Brand */
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 56px;
        }

        .brand-icon {
            width: 44px; height: 44px;
            background: linear-gradient(145deg, var(--green), var(--green-dk));
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            box-shadow: 0 6px 16px rgba(48,209,88,0.30), inset 0 1px 0 rgba(255,255,255,0.25);
            flex-shrink: 0;
        }

        .brand-name { font-size: 15px; font-weight: 700; color: var(--label); letter-spacing: -0.3px; }
        .brand-sub  { font-size: 11px; color: var(--label-3); margin-top: 1px; }

        /* Hero */
        .hero { margin-bottom: 44px; }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            color: var(--green-dk);
            background: var(--green-tint);
            padding: 4px 10px;
            border-radius: 99px;
            margin-bottom: 16px;
            letter-spacing: 0.3px;
        }

        .hero-eyebrow::before {
            content: '';
            width: 5px; height: 5px;
            background: var(--green);
            border-radius: 50%;
            animation: blink 2s ease-in-out infinite;
        }

        @keyframes blink {
            0%,100% { opacity: 1; }
            50%      { opacity: 0.3; }
        }

        .hero h1 {
            font-size: 48px;
            font-weight: 800;
            color: var(--label);
            line-height: 1.08;
            letter-spacing: -2.5px;
            margin-bottom: 16px;
        }

        .hero h1 .accent { color: var(--green-dk); }

        .hero p {
            font-size: 15px;
            line-height: 1.65;
            color: var(--label-2);
            max-width: 380px;
        }

        /* Feature list */
        .features {
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            color: var(--label-2);
            font-weight: 500;
        }

        .feature-dot {
            width: 20px; height: 20px;
            background: var(--fill-3);
            border-radius: 5px;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px;
            color: var(--green-dk);
            flex-shrink: 0;
        }

        /* ── RIGHT PANEL: FORM ── */
        .right {
            width: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 36px 32px;
            flex-shrink: 0;
        }

        .form-card {
            width: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(48px) saturate(200%);
            -webkit-backdrop-filter: blur(48px) saturate(200%);
            border: 0.5px solid rgba(255, 255, 255, 0.75);
            border-radius: 24px;
            padding: 34px;
            box-shadow: var(--shadow-xl), 0 0 0 0.5px rgba(60,60,67,0.10);
            animation: slideUp 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        .form-head { margin-bottom: 26px; }

        .form-head h2 {
            font-size: 22px;
            font-weight: 800;
            color: var(--label);
            letter-spacing: -0.8px;
            margin-bottom: 4px;
        }

        .form-head p { font-size: 13.5px; color: var(--label-3); }

        /* Inset form field (iOS grouped style) */
        .field { margin-bottom: 14px; }

        .field > label {
            display: block;
            font-size: 11.5px;
            font-weight: 600;
            color: var(--label-2);
            margin-bottom: 6px;
            letter-spacing: 0.1px;
        }

        .field-inner { position: relative; }

        .field-inner i.fi {
            position: absolute;
            left: 12px; top: 50%;
            transform: translateY(-50%);
            font-size: 12.5px;
            color: var(--label-3);
            pointer-events: none;
            transition: color 0.15s;
        }

        .field input {
            display: block;
            width: 100%;
            padding: 11px 12px 11px 36px;
            font-size: 14px;
            font-family: inherit;
            color: var(--label);
            background: rgba(118,118,128,0.065);
            border: 0.5px solid rgba(60,60,67,0.14);
            border-radius: 12px;
            outline: none;
            transition: all 0.18s ease;
            -webkit-appearance: none;
        }

        .field input::placeholder { color: rgba(60,60,67,0.28); }
        .field input:hover  { background: rgba(118,118,128,0.09); border-color: rgba(60,60,67,0.20); }
        .field input:focus  {
            background: rgba(255,255,255,0.95);
            border-color: var(--green);
            box-shadow: 0 0 0 3.5px rgba(48,209,88,0.15);
        }
        .field input:focus ~ i.fi,
        .field-inner:focus-within i.fi { color: var(--green-dk); }

        /* Password toggle */
        .pw-btn {
            position: absolute;
            right: 11px; top: 50%;
            transform: translateY(-50%);
            border: none;
            background: none;
            color: var(--label-3);
            font-size: 13px;
            cursor: pointer;
            padding: 3px;
            transition: color 0.15s;
        }
        .pw-btn:hover { color: var(--label-2); }

        /* Alert messages */
        .msg {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 13px;
            border-radius: 11px;
            font-size: 13px;
            margin-bottom: 18px;
            font-weight: 500;
        }
        .msg.err { background: rgba(255,59,48,0.08); color: #C0392B; }
        .msg.ok  { background: rgba(48,209,88,0.10); color: #1A7A32; }
        .msg i   { font-size: 13px; flex-shrink: 0; }

        /* Sign-in button */
        .btn-signin {
            width: 100%;
            padding: 13px;
            font-size: 15px;
            font-weight: 700;
            font-family: inherit;
            color: #fff;
            background: linear-gradient(180deg, #3ADA63 0%, var(--green-dk) 100%);
            border: none;
            border-radius: 13px;
            cursor: pointer;
            transition: all 0.18s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 6px;
            box-shadow: 0 2px 6px rgba(48,209,88,0.25), inset 0 1px 0 rgba(255,255,255,0.20);
            letter-spacing: -0.1px;
        }

        .btn-signin:hover:not(:disabled) {
            background: linear-gradient(180deg, #30D158 0%, #1E9E40 100%);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(48,209,88,0.30), inset 0 1px 0 rgba(255,255,255,0.20);
        }

        .btn-signin:active:not(:disabled) {
            transform: scale(0.98);
            box-shadow: 0 2px 8px rgba(48,209,88,0.20);
        }

        .btn-signin:disabled { opacity: 0.5; cursor: default; }

        /* Footer */
        .card-footer {
            margin-top: 22px;
            text-align: center;
            font-size: 11px;
            color: var(--label-3);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 960px) {
            .left { padding: 48px 40px; flex: 1; }
            .window-chrome { display: none; }
            .hero h1 { font-size: 38px; }
        }

        @media (max-width: 680px) {
            body { overflow-y: auto; }
            .container { flex-direction: column; }
            .left { padding: 32px 24px 16px; }
            .hero h1 { font-size: 30px; }
            .hero p, .features { display: none; }
            .brand { margin-bottom: 0; }
            .right { width: 100%; padding: 14px 20px 36px; }
        }
    </style>
</head>
<body>

<div class="bg"></div>

<div class="container">

    <!-- ── LEFT ── -->
    <div class="left">

        <div class="brand">
            <div class="brand-icon">🍽️</div>
            <div>
                <div class="brand-name">Yazzies Catering</div>
                <div class="brand-sub">Operational Management System</div>
            </div>
        </div>

        <div class="hero">
            <div class="hero-eyebrow">System Online</div>
            <h1>Run every<br>event <span class="accent">perfectly.</span></h1>
            <p>Bookings, ingredient costing, staff dispatching, and financial tracking — all in one place for your catering team.</p>
        </div>

        <div class="features">
            <div class="feature"><div class="feature-dot"><i class="fas fa-calendar-check"></i></div>Event booking calendar</div>
            <div class="feature"><div class="feature-dot"><i class="fas fa-cart-shopping"></i></div>Automated grocery list generation</div>
            <div class="feature"><div class="feature-dot"><i class="fas fa-coins"></i></div>Financial tracking &amp; payment ledger</div>
            <div class="feature"><div class="feature-dot"><i class="fas fa-bullhorn"></i></div>On-call staff dispatching</div>
        </div>

        <!-- macOS-style floating widget -->
        <div class="window-chrome" aria-hidden="true">
            <div class="chrome-bar">
                <div class="chrome-dot red"></div>
                <div class="chrome-dot yellow"></div>
                <div class="chrome-dot green"></div>
                <div class="chrome-title">Overview</div>
            </div>
            <div class="chrome-body">
                <div class="chrome-stat">
                    <div class="chrome-icon g"><i class="fas fa-calendar-check"></i></div>
                    <div>
                        <div class="chrome-stat-label">Active Bookings</div>
                        <div class="chrome-stat-value" id="statBookings">—</div>
                    </div>
                </div>
                <div class="chrome-stat">
                    <div class="chrome-icon t"><i class="fas fa-users"></i></div>
                    <div>
                        <div class="chrome-stat-label">On-Call Staff</div>
                        <div class="chrome-stat-value">8</div>
                    </div>
                </div>
                <div class="chrome-stat">
                    <div class="chrome-icon o"><i class="fas fa-peso-sign"></i></div>
                    <div>
                        <div class="chrome-stat-label">This Month</div>
                        <div class="chrome-stat-value">₱—</div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── RIGHT: LOGIN ── -->
    <div class="right">
        <div class="form-card">

            <div class="form-head">
                <h2>Sign In</h2>
                <p>Enter your credentials to continue</p>
            </div>

            <?php if ($error && isset($errorMessages[$error])): ?>
                <div class="msg err"><i class="fas fa-exclamation-circle"></i><?= $errorMessages[$error] ?></div>
            <?php endif; ?>
            <?php if ($message && isset($successMessages[$message])): ?>
                <div class="msg ok"><i class="fas fa-check-circle"></i><?= $successMessages[$message] ?></div>
            <?php endif; ?>

            <div class="msg err" id="jsErr" style="display:none;">
                <i class="fas fa-exclamation-circle"></i>
                <span id="jsErrMsg"></span>
            </div>

            <form id="loginForm" novalidate>

                <div class="field">
                    <label for="email">Email Address</label>
                    <div class="field-inner">
                        <input type="email" id="email" name="email"
                               placeholder="you@yazzies.com"
                               autocomplete="email" required>
                        <i class="fas fa-envelope fi"></i>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="field-inner">
                        <input type="password" id="pw" name="password"
                               placeholder="Your password"
                               autocomplete="current-password" required>
                        <i class="fas fa-lock fi"></i>
                        <button type="button" class="pw-btn" id="pwBtn">
                            <i class="fas fa-eye" id="pwIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-signin" id="submitBtn">
                    <i class="fas fa-right-to-bracket" id="submitIcon"></i>
                    <span id="submitTxt">Sign In</span>
                </button>

            </form>

            <div class="card-footer">
                <?= APP_NAME ?> &nbsp;·&nbsp; <?= APP_VERSION ?> &nbsp;·&nbsp; <?= date('Y') ?>
            </div>

        </div>
    </div>

</div>

<script>
document.getElementById('pwBtn').onclick = () => {
    const i = document.getElementById('pw');
    const ic = document.getElementById('pwIcon');
    i.type = i.type === 'password' ? 'text' : 'password';
    ic.className = i.type === 'text' ? 'fas fa-eye-slash' : 'fas fa-eye';
};

document.getElementById('loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn  = document.getElementById('submitBtn');
    const txt  = document.getElementById('submitTxt');
    const icon = document.getElementById('submitIcon');
    const errD = document.getElementById('jsErr');
    const errM = document.getElementById('jsErrMsg');

    errD.style.display = 'none';
    btn.disabled = true;
    icon.className = 'fas fa-circle-notch fa-spin';
    txt.textContent = 'Signing in…';

    try {
        const r = await fetch('<?= BASE_URL ?>/src/api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email:    document.getElementById('email').value.trim(),
                password: document.getElementById('pw').value,
            }),
        });
        const d = await r.json();
        if (d.success && d.redirect) {
            txt.textContent = 'Opening…';
            window.location.href = d.redirect;
        } else {
            errM.textContent = d.message || 'Incorrect email or password.';
            errD.style.display = 'flex';
            btn.disabled = false;
            icon.className = 'fas fa-right-to-bracket';
            txt.textContent = 'Sign In';
        }
    } catch {
        errM.textContent = 'Network error. Please try again.';
        errD.style.display = 'flex';
        btn.disabled = false;
        icon.className = 'fas fa-right-to-bracket';
        txt.textContent = 'Sign In';
    }
});

// Decorative active bookings count
(async () => {
    try {
        const r = await fetch('<?= BASE_URL ?>/src/api/bookings.php?count_active=1');
        if (r.ok) {
            const d = await r.json();
            if (d.count !== undefined) {
                document.getElementById('statBookings').textContent = d.count;
            }
        }
    } catch {}
})();
</script>
</body>
</html>