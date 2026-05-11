<?php
/**
 * Account Self-Setup Portal — Phase 5: Email Invitation Flow
 * Public file: /views/setup.php?token=<64-char-hex>
 */
require_once __DIR__ . '/../config/config.php';

$token    = trim($_GET['token'] ?? '');
$hasToken = !empty($token);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Setup — <?= htmlspecialchars(APP_NAME) ?></title>
    <meta name="description" content="Complete your Yazzies Catering OMS account setup.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars(getCsrfToken()) ?>">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300;0,14..32,400;0,14..32,500;0,14..32,600;0,14..32,700;0,14..32,800;0,14..32,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:      #30D158;
            --green-dk:   #25A244;
            --green-tint: rgba(48,209,88,0.12);
            --red:        #FF3B30;
            --gold:       #B8860B;
            --gold-bg:    rgba(255,159,10,0.10);
            --label:      rgba(0,0,0,0.88);
            --label-2:    rgba(60,60,67,0.60);
            --label-3:    rgba(60,60,67,0.30);
            --fill-2:     rgba(120,120,128,0.16);
            --fill-3:     rgba(118,118,128,0.12);
            --fill-4:     rgba(116,116,128,0.065);
            --sep:        rgba(60,60,67,0.12);
            --shadow-xl:  0 20px 60px rgba(0,0,0,0.10), 0 4px 20px rgba(0,0,0,0.06);
        }

        html { -webkit-font-smoothing: antialiased; }

        body {
            font-family: -apple-system, 'SF Pro Text', 'Inter', 'Helvetica Neue', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F2F2F7;
            padding: 24px 20px;
        }

        /* ── Background ────────────────────────────────── */
        .bg {
            position: fixed; inset: 0; z-index: 0;
            background:
                radial-gradient(ellipse 900px 700px at -10% 60%, rgba(48,209,88,0.10) 0%, transparent 65%),
                radial-gradient(ellipse 600px 800px at 110% 40%, rgba(48,209,88,0.06) 0%, transparent 65%),
                #F2F2F7;
        }
        .bg::after {
            content: '';
            position: absolute; inset: 0;
            background-image:
                linear-gradient(rgba(60,60,67,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(60,60,67,0.025) 1px, transparent 1px);
            background-size: 40px 40px;
        }

        /* ── Card ──────────────────────────────────────── */
        .setup-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 400px;
            background: rgba(255,255,255,0.85);
            backdrop-filter: blur(48px) saturate(200%);
            -webkit-backdrop-filter: blur(48px) saturate(200%);
            border: 0.5px solid rgba(255,255,255,0.75);
            border-radius: 24px;
            padding: 36px 34px;
            box-shadow: var(--shadow-xl), 0 0 0 0.5px rgba(60,60,67,0.10);
            animation: slideUp 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(28px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0)    scale(1); }
        }

        /* ── Brand ─────────────────────────────────────── */
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 28px;
        }
        .brand-icon {
            width: 38px; height: 38px;
            background: linear-gradient(145deg, var(--green), var(--green-dk));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 12px rgba(48,209,88,0.28), inset 0 1px 0 rgba(255,255,255,0.25);
            flex-shrink: 0;
        }
        .brand-name { font-size: 14px; font-weight: 700; color: var(--label); letter-spacing: -0.3px; }
        .brand-sub  { font-size: 10.5px; color: var(--label-3); margin-top: 1px; }

        /* ── Card Head ─────────────────────────────────── */
        .card-head { margin-bottom: 24px; }
        .card-head h2 {
            font-size: 21px;
            font-weight: 800;
            color: var(--label);
            letter-spacing: -0.7px;
            margin-bottom: 3px;
        }
        .card-head p { font-size: 13px; color: var(--label-3); line-height: 1.5; }

        /* ── Step Indicator ────────────────────────────── */
        .steps {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 24px;
        }
        .step { display: flex; align-items: center; gap: 7px; flex: 1; }
        .step-dot {
            width: 24px; height: 24px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 700;
            flex-shrink: 0;
            border: 1.5px solid var(--sep);
            color: var(--label-3);
            transition: all 0.3s ease;
            background: var(--fill-4);
        }
        .step-dot.active  { background: var(--green);    border-color: var(--green);    color: #fff; box-shadow: 0 0 0 4px rgba(48,209,88,0.15); }
        .step-dot.done    { background: var(--green-dk); border-color: var(--green-dk); color: #fff; }
        .step-label { font-size: 11px; font-weight: 600; color: var(--label-3); transition: color 0.3s; }
        .step-label.active { color: var(--green-dk); }
        .step-divider { flex: 0 0 20px; height: 1px; background: var(--sep); }

        /* ── Alert messages ────────────────────────────── */
        .msg {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 13px;
            border-radius: 11px;
            font-size: 13px;
            margin-bottom: 18px;
            font-weight: 500;
            line-height: 1.45;
        }
        .msg i { font-size: 13px; flex-shrink: 0; margin-top: 1px; }
        .msg.err { background: rgba(255,59,48,0.08); color: #C0392B; }
        .msg.ok  { background: rgba(48,209,88,0.10); color: #1A7A32; }

        /* ── Fields ────────────────────────────────────── */
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
            background: var(--fill-4);
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
        .field input.error { border-color: var(--red); box-shadow: 0 0 0 3.5px rgba(255,59,48,0.12); }

        /* OTP input — large centred */
        .otp-field input {
            text-align: center;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 14px;
            padding: 14px 12px;
            font-family: inherit;
        }

        /* Password show/hide toggle */
        .pw-btn {
            position: absolute;
            right: 11px; top: 50%;
            transform: translateY(-50%);
            border: none; background: none;
            color: var(--label-3);
            font-size: 13px;
            cursor: pointer;
            padding: 3px;
            transition: color 0.15s;
        }
        .pw-btn:hover { color: var(--label-2); }

        /* ── Password Strength ─────────────────────────── */
        .pw-strength { margin-top: 7px; height: 3px; border-radius: 2px; background: var(--fill-3); overflow: hidden; }
        .pw-strength-bar { height: 100%; border-radius: 2px; width: 0; transition: width 0.4s ease, background 0.4s ease; }

        /* ── Submit Button ─────────────────────────────── */
        .btn-submit {
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
        .btn-submit:hover:not(:disabled) {
            background: linear-gradient(180deg, #30D158 0%, #1E9E40 100%);
            transform: translateY(-1px);
            box-shadow: 0 8px 24px rgba(48,209,88,0.30), inset 0 1px 0 rgba(255,255,255,0.20);
        }
        .btn-submit:active:not(:disabled) { transform: scale(0.98); }
        .btn-submit:disabled { opacity: 0.5; cursor: default; }

        /* ── Info notice (invite banner) ───────────────── */
        .invite-notice {
            background: rgba(48,209,88,0.07);
            border: 0.5px solid rgba(48,209,88,0.22);
            border-radius: 11px;
            padding: 11px 13px;
            font-size: 12.5px;
            color: var(--green-dk);
            font-weight: 500;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        /* ── Error state card ──────────────────────────── */
        .error-state { text-align: center; padding: 8px 0; }
        .error-icon { font-size: 40px; color: var(--red); opacity: 0.6; margin-bottom: 16px; }
        .error-state h3 { font-size: 19px; font-weight: 800; color: var(--label); letter-spacing: -0.5px; margin-bottom: 8px; }
        .error-state p  { font-size: 13.5px; color: var(--label-2); line-height: 1.6; }

        /* ── Success state ─────────────────────────────── */
        .success-state { text-align: center; padding: 8px 0; display: none; }
        .success-icon  { font-size: 52px; margin-bottom: 16px; animation: pop 0.5s cubic-bezier(0.34,1.56,0.64,1) both; }
        @keyframes pop { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        .success-state h3 { font-size: 20px; font-weight: 800; color: var(--label); letter-spacing: -0.6px; margin-bottom: 6px; }
        .success-state p  { font-size: 13.5px; color: var(--label-2); line-height: 1.6; margin-bottom: 20px; }

        .btn-login {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 24px;
            font-size: 14px;
            font-weight: 700;
            font-family: inherit;
            color: #fff;
            background: linear-gradient(180deg, #3ADA63 0%, var(--green-dk) 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(48,209,88,0.25);
            transition: all 0.18s ease;
        }
        .btn-login:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(48,209,88,0.30); }

        /* ── Footer ────────────────────────────────────── */
        .card-footer {
            margin-top: 20px;
            text-align: center;
            font-size: 11px;
            color: var(--label-3);
        }

        /* ── Step 2 hidden by default ──────────────────── */
        #step2Panel { display: none; }
    </style>
</head>
<body>

<div class="bg"></div>

<div class="setup-card">

    <!-- Brand -->
    <div class="brand">
        <div class="brand-icon">🔐</div>
        <div>
            <div class="brand-name"><?= htmlspecialchars(BUSINESS_NAME) ?></div>
            <div class="brand-sub">Operational Management System</div>
        </div>
    </div>

<?php if (!$hasToken): ?>

    <!-- ── Error: No token ───────────────────────────── -->
    <div class="error-state">
        <div class="error-icon"><i class="fas fa-link-slash"></i></div>
        <h3>Invalid Setup Link</h3>
        <p>This account setup link is missing or malformed. Please check your invitation email or contact your administrator for a new link.</p>
    </div>

<?php else: ?>

    <!-- ── Card header ──────────────────────────────── -->
    <div class="card-head">
        <h2>Account Setup</h2>
        <p id="cardSubtitle">Verify your identity and set your password to get started.</p>
    </div>

    <!-- Step indicator -->
    <div class="steps" id="stepIndicator">
        <div class="step">
            <div class="step-dot active" id="dot1">1</div>
            <div class="step-label active" id="lbl1">Verify OTP</div>
        </div>
        <div class="step-divider"></div>
        <div class="step">
            <div class="step-dot" id="dot2">2</div>
            <div class="step-label" id="lbl2">Set Password</div>
        </div>
    </div>

    <!-- Alert box -->
    <div class="msg err" id="alertBox" style="display:none;"></div>

    <!-- ── Step 1: OTP ──────────────────────────────── -->
    <div id="step1Panel">
        <div class="invite-notice">
            <i class="fas fa-envelope-open-text me-1"></i>
            Enter the <strong>6-digit OTP</strong> from your invitation email.
        </div>
        <div class="field otp-field">
            <label for="otpInput">One-Time PIN</label>
            <div class="field-inner">
                <i class="fi fas fa-key"></i>
                <input
                    type="text"
                    id="otpInput"
                    inputmode="numeric"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    placeholder="• • • • • •"
                    autocomplete="one-time-code"
                    spellcheck="false"
                >
            </div>
        </div>
        <button class="btn-submit" id="verifyOtpBtn" onclick="verifyOtp()">
            <i class="fas fa-shield-check" id="verifyIcon"></i>
            <span id="verifyTxt">Verify OTP</span>
        </button>
    </div>

    <!-- ── Step 2: Password ─────────────────────────── -->
    <div id="step2Panel">
        <div class="field">
            <label for="pwInput">New Password</label>
            <div class="field-inner">
                <i class="fi fas fa-lock"></i>
                <input type="password" id="pwInput" placeholder="Min. 8 characters" autocomplete="new-password">
                <button class="pw-btn" type="button" onclick="togglePw('pwInput', this)" tabindex="-1">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
            <div class="pw-strength"><div class="pw-strength-bar" id="pwStrengthBar"></div></div>
        </div>
        <div class="field">
            <label for="pwConfirmInput">Confirm Password</label>
            <div class="field-inner">
                <i class="fi fas fa-lock"></i>
                <input type="password" id="pwConfirmInput" placeholder="Repeat password" autocomplete="new-password">
                <button class="pw-btn" type="button" onclick="togglePw('pwConfirmInput', this)" tabindex="-1">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>
        <button class="btn-submit" id="setPasswordBtn" onclick="setPassword()">
            <i class="fas fa-unlock" id="setIcon"></i>
            <span id="setTxt">Activate My Account</span>
        </button>
    </div>

    <!-- ── Success ──────────────────────────────────── -->
    <div class="success-state" id="successState">
        <div class="success-icon">🎉</div>
        <h3>You're all set!</h3>
        <p id="successMsg">Your account is ready. Redirecting you to login…</p>
        <a href="<?= htmlspecialchars(BASE_URL) ?>/" class="btn-login" id="loginLink">
            <i class="fas fa-right-to-bracket"></i> Go to Login
        </a>
    </div>

    <div class="card-footer" id="cardFooter">This link is valid for 24 hours from the time of issue.</div>

<?php endif; ?>

</div><!-- /.setup-card -->

<?php if ($hasToken): ?>
<script>
const SETUP_TOKEN = <?= json_encode($token) ?>;
const API_BASE    = <?= json_encode(BASE_URL . '/src/api/setup_account.php') ?>;
const LOGIN_URL   = <?= json_encode(BASE_URL . '/') ?>;
const CSRF_TOKEN  = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// ── Helpers ──────────────────────────────────────────────────────
function showAlert(msg) {
    const el = document.getElementById('alertBox');
    el.innerHTML = `<i class="fas fa-circle-exclamation"></i> ${msg}`;
    el.style.display = 'flex';
}
function hideAlert() {
    const el = document.getElementById('alertBox');
    el.style.display = 'none';
    el.innerHTML = '';
}

function setLoading(iconId, txtId, btnId, loading, iconClass, label) {
    const icon = document.getElementById(iconId);
    const txt  = document.getElementById(txtId);
    const btn  = document.getElementById(btnId);
    btn.disabled = loading;
    if (loading) {
        icon.className = 'fas fa-circle-notch fa-spin';
        txt.textContent = 'Please wait…';
    } else {
        icon.className = iconClass;
        txt.textContent = label;
    }
}

function togglePw(inputId, btn) {
    const input = document.getElementById(inputId);
    const isText = input.type === 'text';
    input.type = isText ? 'password' : 'text';
    btn.querySelector('i').className = isText ? 'fas fa-eye' : 'fas fa-eye-slash';
}

// ── Password Strength ────────────────────────────────────────────
document.getElementById('pwInput').addEventListener('input', function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8)          score++;
    if (/[A-Z]/.test(v))        score++;
    if (/[0-9]/.test(v))        score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const pct   = ['0%','30%','55%','78%','100%'][score];
    const color = ['#FF3B30','#FF9F0A','#FFD60A','#30D158','#25A244'][score];
    const bar = document.getElementById('pwStrengthBar');
    bar.style.width      = pct;
    bar.style.background = color;
});

// ── API helper ───────────────────────────────────────────────────
async function callApi(action, extra = {}) {
    const resp = await fetch(API_BASE, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN },
        body: JSON.stringify({ action, token: SETUP_TOKEN, ...extra }),
    });
    const json = await resp.json();
    if (!json.success) throw new Error(json.message || 'Unknown error.');
    return json;
}

// ── Step 1: Verify OTP ───────────────────────────────────────────
async function verifyOtp() {
    hideAlert();
    const otp = document.getElementById('otpInput').value.trim();
    const inp = document.getElementById('otpInput');

    if (!/^\d{6}$/.test(otp)) {
        showAlert('Please enter a valid 6-digit OTP.');
        inp.classList.add('error');
        inp.focus();
        return;
    }
    inp.classList.remove('error');

    setLoading('verifyIcon', 'verifyTxt', 'verifyOtpBtn', true);

    try {
        const res = await callApi('verify_otp', { otp });

        // Transition to Step 2
        document.getElementById('step1Panel').style.display = 'none';
        document.getElementById('step2Panel').style.display = 'block';
        document.getElementById('cardSubtitle').textContent =
            `Welcome, ${res.name || 'new user'}! Please set a strong password.`;

        // Update step dots
        document.getElementById('dot1').className = 'step-dot done';
        document.getElementById('dot1').innerHTML = '<i class="fas fa-check" style="font-size:10px;"></i>';
        document.getElementById('lbl1').className = 'step-label';
        document.getElementById('dot2').className = 'step-dot active';
        document.getElementById('lbl2').className = 'step-label active';

        // Store OTP for step 2
        document.getElementById('setPasswordBtn').dataset.otp = otp;
        document.getElementById('pwInput').focus();
    } catch(e) {
        showAlert(e.message);
    }

    setLoading('verifyIcon', 'verifyTxt', 'verifyOtpBtn', false, 'fas fa-shield-check', 'Verify OTP');
}

// ── Step 2: Set Password ─────────────────────────────────────────
async function setPassword() {
    hideAlert();
    const otp     = document.getElementById('setPasswordBtn').dataset.otp;
    const pw      = document.getElementById('pwInput').value;
    const pwConf  = document.getElementById('pwConfirmInput').value;
    const pwInp   = document.getElementById('pwInput');
    const cfInp   = document.getElementById('pwConfirmInput');

    pwInp.classList.remove('error');
    cfInp.classList.remove('error');

    if (pw.length < 8) {
        showAlert('Password must be at least 8 characters long.');
        pwInp.classList.add('error');
        pwInp.focus();
        return;
    }
    if (pw !== pwConf) {
        showAlert('Passwords do not match. Please try again.');
        cfInp.classList.add('error');
        cfInp.focus();
        return;
    }

    setLoading('setIcon', 'setTxt', 'setPasswordBtn', true);

    try {
        const res = await callApi('set_password', { otp, password: pw, password_confirm: pwConf });

        // Show success state
        document.getElementById('stepIndicator').style.display = 'none';
        document.getElementById('step2Panel').style.display    = 'none';
        document.getElementById('cardSubtitle').textContent    = '';
        document.getElementById('alertBox').style.display      = 'none';
        document.getElementById('cardFooter').style.display    = 'none';

        const successEl = document.getElementById('successState');
        successEl.style.display = 'block';

        document.getElementById('successMsg').textContent = res.is_active == 0
            ? 'Your password has been set. Your administrator account is dormant — the active admin must transfer the Master Key before you can log in.'
            : 'Your account is now active. Redirecting to login…';

        if (res.is_active == 1) {
            setTimeout(() => { window.location.href = LOGIN_URL; }, 3000);
        }
    } catch(e) {
        showAlert(e.message);
        setLoading('setIcon', 'setTxt', 'setPasswordBtn', false, 'fas fa-unlock', 'Activate My Account');
    }
}

// ── OTP: auto-submit on 6 digits ─────────────────────────────────
document.getElementById('otpInput').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').substring(0, 6);
    if (this.value.length === 6) verifyOtp();
});
</script>
<?php endif; ?>

</body>
</html>
