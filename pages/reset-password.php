<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | AgriTrace+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=Syne:wght@800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --green: #10b981;
            --green-hover: #059669;
            --glass: rgba(255,255,255,0.1);
            --border: rgba(255,255,255,0.2);
            --dim: rgba(255,255,255,0.65);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'DM Sans', sans-serif; }

        body {
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: linear-gradient(rgba(0,0,0,0.65), rgba(0,0,0,0.85)),
                        url('https://images.unsplash.com/photo-1500382017468-9049fed747ef?auto=format&fit=crop&q=80&w=2000') center/cover fixed;
            padding: 20px;
        }

        .auth-container { width: 100%; max-width: 460px; }

        .auth-card {
            background: var(--glass);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 32px;
            padding: 44px 40px;
            box-shadow: 0 40px 100px rgba(0,0,0,0.5);
            text-align: center;
            animation: rise 0.6s cubic-bezier(0.16,1,0.3,1);
        }

        @keyframes rise {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .brand-logo {
            font-family: 'Syne', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            color: #fff;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            letter-spacing: -1px;
        }
        .brand-logo i  { color: var(--green); font-size: 1.7rem; }
        .brand-logo span { color: var(--green); }

        .lock-icon {
            width: 72px; height: 72px;
            background: rgba(16,185,129,0.18);
            border: 2px solid rgba(16,185,129,0.4);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 22px;
            font-size: 2rem;
            color: var(--green);
            animation: pulse-ring 2.5s ease-in-out infinite;
        }

        @keyframes pulse-ring {
            0%,100% { box-shadow: 0 0 0 0 rgba(16,185,129,0.3); }
            50%      { box-shadow: 0 0 0 10px rgba(16,185,129,0); }
        }

        h1 { color: #fff; font-size: 1.55rem; font-weight: 700; margin-bottom: 10px; }

        .subtitle {
            color: var(--dim);
            font-size: 0.93rem;
            line-height: 1.55;
            margin-bottom: 30px;
        }

        /* ---- Form fields ---- */
        .field-group {
            text-align: left;
            margin-bottom: 24px;
            position: relative;
        }
        .field-group:last-of-type { margin-bottom: 28px; }

        .field-label {
            display: block;
            color: var(--dim);
            font-size: 0.82rem;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.02em;
        }

        .field-wrap { position: relative; }

        .field-input {
            width: 100%;
            padding: 15px 46px 15px 16px;
            border-radius: 14px;
            border: 2px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.92);
            font-size: 1rem;
            font-weight: 500;
            color: #1e293b;
            outline: none;
            transition: all 0.2s ease;
        }
        .field-input:focus {
            border-color: var(--green);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(16,185,129,0.22);
        }
        .field-input.error {
            border-color: #ef4444;
            background: #fff5f5;
            animation: shake 0.35s ease;
        }
        .field-input.valid { border-color: var(--green); }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25%      { transform: translateX(-5px); }
            75%      { transform: translateX(5px); }
        }

        .toggle-eye {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
            font-size: 1.1rem;
            padding: 4px;
            display: flex;
        }
        .toggle-eye:hover { color: #1e293b; }

        .field-hint {
            font-size: 0.78rem;
            color: var(--dim);
            margin-top: 6px;
            min-height: 16px;
            text-align: left;
        }
        .field-hint.error-text { color: #fca5a5; }
        .field-hint.valid-text { color: #6ee7b7; }

        /* ---- Strength meter ---- */
        .strength-row {
            display: flex;
            gap: 5px;
            margin-top: 14px;
            margin-bottom: 8px;
        }
        .strength-bar {
            flex: 1;
            height: 5px;
            border-radius: 4px;
            background: rgba(255,255,255,0.2);
            transition: background 0.25s ease;
        }
        .strength-bar.weak   { background: #ef4444; }
        .strength-bar.medium { background: #f59e0b; }
        .strength-bar.strong { background: var(--green); }

        .strength-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-align: left;
            margin-bottom: 14px;
            min-height: 18px;
            color: var(--dim);
        }

        /* ---- Requirements info icon + popover ---- */
        .field-label-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .field-label-row .field-label { margin-bottom: 0; }

        .req-info-wrap {
            position: relative;
            display: inline-flex;
        }

        .req-info-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.22);
            color: var(--dim);
            font-size: 0.95rem;
            cursor: pointer;
            padding: 0;
            transition: all 0.2s ease;
        }
        .req-info-btn:hover, .req-info-btn.active {
            background: rgba(16,185,129,0.2);
            border-color: rgba(16,185,129,0.5);
            color: #6ee7b7;
        }

        .req-dot {
            position: absolute;
            top: -3px;
            right: -3px;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: #ef4444;
            border: 2px solid rgba(20,25,22,0.9);
            transition: background 0.2s ease;
        }
        .req-dot.complete { background: var(--green); }

        .req-popover {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            z-index: 20;
            width: 230px;
            background: rgba(18,26,22,0.98);
            border: 1px solid rgba(255,255,255,0.18);
            border-radius: 14px;
            padding: 14px 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.45);
            text-align: left;
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
            transition: all 0.2s cubic-bezier(0.16,1,0.3,1);
        }
        .req-popover.open {
            opacity: 1;
            transform: translateY(0);
            pointer-events: auto;
        }
        .req-popover::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 10px;
            width: 12px; height: 12px;
            background: rgba(18,26,22,0.98);
            border-left: 1px solid rgba(255,255,255,0.18);
            border-top: 1px solid rgba(255,255,255,0.18);
            transform: rotate(45deg);
        }

        .req-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }
        .req-list li {
            font-size: 0.82rem;
            color: var(--dim);
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: color 0.2s ease;
        }
        .req-list li i { font-size: 0.9rem; color: rgba(255,255,255,0.3); transition: color 0.2s ease; flex-shrink: 0; }
        .req-list li.met { color: #6ee7b7; }
        .req-list li.met i { color: var(--green); }

        .btn-submit {
            width: 100%;
            padding: 16px;
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 16px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 10px 20px rgba(16,185,129,0.25);
            margin-top: 6px;
            margin-bottom: 22px;
        }
        .btn-submit:hover:not(:disabled) { background: var(--green-hover); transform: translateY(-2px); box-shadow: 0 15px 28px rgba(16,185,129,0.35); }
        .btn-submit:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

        .auth-footer { font-size: 0.9rem; }
        .auth-footer a {
            color: #fff; text-decoration: none; font-weight: 700;
            display: inline-flex; align-items: center; gap: 5px; transition: color 0.2s;
        }
        .auth-footer a:hover { color: var(--green); }

        .spinner { animation: spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @keyframes slideIn  { from { transform: translateX(110%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(110%); opacity: 0; } }

        @media (max-width: 480px) {
            .auth-card { padding: 30px 20px; border-radius: 24px; }
            .req-list { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="brand-logo" onclick="window.location.href='index.php'">
                <i class="bi bi-leaf-fill"></i> Agri<span>Trace+</span>
            </div>

            <div class="lock-icon">
                <i class="bi bi-key-fill"></i>
            </div>

            <h1>Set a New Password</h1>
            <p class="subtitle">Choose a strong password you haven't used before.</p>

            <form id="reset-form" novalidate>
                <div class="field-group">
                    <div class="field-label-row">
                        <label class="field-label" for="new-password">New Password</label>
                        <div class="req-info-wrap">
                            <button type="button" class="req-info-btn" id="req-info-btn" aria-label="Password requirements">
                                <i class="bi bi-info-circle-fill"></i>
                                <span class="req-dot" id="req-dot"></span>
                            </button>

                            <div class="req-popover" id="req-popover">
                                <ul class="req-list" id="req-list">
                                    <li id="req-length"><i class="bi bi-circle"></i> 8+ characters</li>
                                    <li id="req-upper"><i class="bi bi-circle"></i> Uppercase letter</li>
                                    <li id="req-lower"><i class="bi bi-circle"></i> Lowercase letter</li>
                                    <li id="req-number"><i class="bi bi-circle"></i> One number</li>
                                    <li id="req-symbol"><i class="bi bi-circle"></i> Special character</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="field-wrap">
                        <input class="field-input" type="password" id="new-password" placeholder="Enter new password" autocomplete="new-password" required>
                        <button type="button" class="toggle-eye" data-target="new-password">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>

                    <div class="strength-row" id="strength-row">
                        <div class="strength-bar" id="bar-1"></div>
                        <div class="strength-bar" id="bar-2"></div>
                        <div class="strength-bar" id="bar-3"></div>
                        <div class="strength-bar" id="bar-4"></div>
                    </div>
                    <div class="strength-label" id="strength-label">&nbsp;</div>
                </div>

                <div class="field-group">
                    <label class="field-label" for="confirm-password">Confirm Password</label>
                    <div class="field-wrap">
                        <input class="field-input" type="password" id="confirm-password" placeholder="Re-enter new password" autocomplete="new-password" required>
                        <button type="button" class="toggle-eye" data-target="confirm-password">
                            <i class="bi bi-eye-fill"></i>
                        </button>
                    </div>
                    <div class="field-hint" id="confirm-hint">&nbsp;</div>
                </div>

                <button type="submit" class="btn-submit" id="submit-btn" disabled>
                    <span id="btn-text">Reset Password</span>
                    <i class="bi bi-arrow-right" id="btn-icon"></i>
                </button>
            </form>

            <div class="auth-footer">
                <a href="login.php">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>

        <div style="text-align:center;margin-top:30px;color:rgba(255,255,255,0.4);font-size:0.75rem;">
            &copy; 2026 AgriTrace Technologies. All rights reserved.
        </div>
    </div>

    <script>
        /* ---- Read URL params ---- */
        const params     = new URLSearchParams(window.location.search);
        const TOKEN      = params.get('token') || '';
        const IDENTIFIER = params.get('identifier') || '';

        if (!TOKEN || !IDENTIFIER) window.location.href = 'forgot.php';

        /* ---- Element refs ---- */
        const form           = document.getElementById('reset-form');
        const newPasswordEl  = document.getElementById('new-password');
        const confirmEl      = document.getElementById('confirm-password');
        const confirmHint    = document.getElementById('confirm-hint');
        const submitBtn      = document.getElementById('submit-btn');
        const strengthLabel  = document.getElementById('strength-label');
        const bars           = [1, 2, 3, 4].map(n => document.getElementById(`bar-${n}`));
        const reqInfoBtn     = document.getElementById('req-info-btn');
        const reqPopover     = document.getElementById('req-popover');
        const reqDot         = document.getElementById('req-dot');

        const requirements = {
            length: { el: document.getElementById('req-length'), test: v => v.length >= 8 },
            upper:  { el: document.getElementById('req-upper'),  test: v => /[A-Z]/.test(v) },
            lower:  { el: document.getElementById('req-lower'),  test: v => /[a-z]/.test(v) },
            number: { el: document.getElementById('req-number'), test: v => /[0-9]/.test(v) },
            symbol: { el: document.getElementById('req-symbol'), test: v => /[^A-Za-z0-9]/.test(v) },
        };

        /* ---- Show/hide password ---- */
        document.querySelectorAll('.toggle-eye').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                const icon   = btn.querySelector('i');
                const show   = target.type === 'password';
                target.type  = show ? 'text' : 'password';
                icon.className = show ? 'bi bi-eye-slash-fill' : 'bi bi-eye-fill';
            });
        });

        /* ---- Requirements popover toggle ---- */
        reqInfoBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            reqPopover.classList.toggle('open');
            reqInfoBtn.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!reqPopover.contains(e.target) && e.target !== reqInfoBtn) {
                reqPopover.classList.remove('open');
                reqInfoBtn.classList.remove('active');
            }
        });

        newPasswordEl.addEventListener('focus', () => {
            reqPopover.classList.add('open');
            reqInfoBtn.classList.add('active');
        });

        /* ---- Strength + requirements ---- */
        function evaluatePassword() {
            const v = newPasswordEl.value;
            let met = 0;

            Object.values(requirements).forEach(req => {
                const passed = req.test(v);
                req.el.classList.toggle('met', passed);
                req.el.querySelector('i').className = passed ? 'bi bi-check-circle-fill' : 'bi bi-circle';
                if (passed) met++;
            });

            reqDot.classList.toggle('complete', met === 5);

            bars.forEach(b => b.className = 'strength-bar');

            if (v.length === 0) {
                strengthLabel.textContent = '\u00A0';
            } else if (met <= 2) {
                bars[0].classList.add('weak');
                strengthLabel.textContent = 'Weak password';
                strengthLabel.style.color = '#f87171';
            } else if (met <= 4) {
                bars[0].classList.add('medium'); bars[1].classList.add('medium');
                strengthLabel.textContent = 'Medium strength';
                strengthLabel.style.color = '#fbbf24';
            } else {
                bars.forEach(b => b.classList.add('strong'));
                strengthLabel.textContent = 'Strong password';
                strengthLabel.style.color = '#6ee7b7';
            }

            newPasswordEl.classList.toggle('valid', met === 5);
            newPasswordEl.classList.remove('error');

            validateConfirm();
            updateSubmitState(met === 5);
        }

        function validateConfirm() {
            const a = newPasswordEl.value;
            const b = confirmEl.value;

            if (b.length === 0) {
                confirmHint.textContent = '\u00A0';
                confirmHint.className = 'field-hint';
                confirmEl.classList.remove('valid', 'error');
                return false;
            }

            if (a === b) {
                confirmHint.textContent = 'Passwords match';
                confirmHint.className = 'field-hint valid-text';
                confirmEl.classList.add('valid');
                confirmEl.classList.remove('error');
                return true;
            } else {
                confirmHint.textContent = 'Passwords do not match';
                confirmHint.className = 'field-hint error-text';
                confirmEl.classList.add('error');
                confirmEl.classList.remove('valid');
                return false;
            }
        }

        function updateSubmitState(strongEnough) {
            const match = newPasswordEl.value.length > 0 && newPasswordEl.value === confirmEl.value;
            submitBtn.disabled = !(strongEnough && match);
        }

        newPasswordEl.addEventListener('input', evaluatePassword);
        confirmEl.addEventListener('input', () => {
            validateConfirm();
            const met = Object.values(requirements).filter(r => r.test(newPasswordEl.value)).length;
            updateSubmitState(met === 5);
        });

        /* ---- Submit ---- */
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const password = newPasswordEl.value;
            const confirm  = confirmEl.value;

            if (password !== confirm) {
                validateConfirm();
                return;
            }

            const btnText = document.getElementById('btn-text');
            const btnIcon = document.getElementById('btn-icon');
            submitBtn.disabled = true;
            btnText.textContent = 'Resetting…';
            btnIcon.className = 'bi bi-arrow-repeat spinner';

            try {
                const res = await fetch('../api/db.php?action=reset-password', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        token: TOKEN,
                        identifier: IDENTIFIER,
                        password: password
                    })
                });
                const result = await res.json();

                if (result.success) {
                    btnText.textContent = 'Password Reset!';
                    btnIcon.className = 'bi bi-check2-all';
                    showToast('Password updated! Redirecting to login…', false);
                    setTimeout(() => { window.location.href = 'login.php'; }, 1500);
                } else {
                    showToast(result.error || 'Could not reset password.', true);
                    submitBtn.disabled = false;
                    btnText.textContent = 'Reset Password';
                    btnIcon.className = 'bi bi-arrow-right';
                }
            } catch {
                showToast('Network error. Please try again.', true);
                submitBtn.disabled = false;
                btnText.textContent = 'Reset Password';
                btnIcon.className = 'bi bi-arrow-right';
            }
        });

        /* ---- Toast ---- */
        function showToast(msg, isError = false) {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position:fixed;top:20px;right:20px;z-index:10000;
                background:${isError ? '#ef4444' : '#10b981'};
                color:#fff;padding:16px 24px;border-radius:16px;
                box-shadow:0 10px 30px rgba(0,0,0,0.3);
                display:flex;align-items:center;gap:12px;
                font-weight:600;font-size:0.9rem;
                animation:slideIn 0.4s cubic-bezier(0.175,0.885,0.32,1.275);
                max-width:320px;
            `;
            toast.innerHTML = `<i class="bi ${isError ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill'}"></i><span>${msg}</span>`;
            document.body.appendChild(toast);
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.4s forwards';
                setTimeout(() => toast.remove(), 400);
            }, 4000);
        }

        newPasswordEl.focus();
    </script>
</body>
</html>