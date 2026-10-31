<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP | AgriTrace+</title>
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

        .otp-icon {
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
            margin-bottom: 8px;
        }
        .dest-label {
            display: inline-block;
            background: rgba(16,185,129,0.18);
            color: #6ee7b7;
            border: 1px solid rgba(16,185,129,0.35);
            border-radius: 8px;
            font-size: 0.82rem;
            font-weight: 700;
            padding: 4px 12px;
            margin-bottom: 28px;
            letter-spacing: 0.02em;
            word-break: break-all;
        }

        /* ---- 6 OTP boxes ---- */
        .otp-row {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 28px;
        }

        .otp-box {
            width: 52px; height: 62px;
            border-radius: 14px;
            border: 2px solid rgba(255,255,255,0.25);
            background: rgba(255,255,255,0.92);
            font-size: 1.7rem;
            font-weight: 700;
            color: #1e293b;
            text-align: center;
            outline: none;
            transition: all 0.2s ease;
            caret-color: var(--green);
        }
        .otp-box:focus {
            border-color: var(--green);
            background: #fff;
            box-shadow: 0 0 0 4px rgba(16,185,129,0.22);
            transform: translateY(-2px);
        }
        .otp-box.filled {
            border-color: var(--green);
            background: #fff;
        }
        .otp-box.error {
            border-color: #ef4444;
            background: #fff5f5;
            animation: shake 0.35s ease;
        }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            25%      { transform: translateX(-5px); }
            75%      { transform: translateX(5px); }
        }

        .btn-verify {
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
            margin-bottom: 20px;
        }
        .btn-verify:hover:not(:disabled) { background: var(--green-hover); transform: translateY(-2px); box-shadow: 0 15px 28px rgba(16,185,129,0.35); }
        .btn-verify:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }

        /* Timer + resend */
        .resend-row {
            font-size: 0.88rem;
            color: var(--dim);
        }
        #resend-btn {
            background: none;
            border: none;
            color: var(--green);
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            text-decoration: underline;
            padding: 0;
            display: none;
        }
        #resend-btn:hover { color: #6ee7b7; }

        #timer-wrap { display: inline; }

        .auth-footer { margin-top: 24px; font-size: 0.9rem; }
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
            .otp-box { width: 42px; height: 54px; font-size: 1.4rem; border-radius: 11px; }
            .otp-row { gap: 7px; }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="brand-logo" onclick="window.location.href='index.php'">
                <i class="bi bi-leaf-fill"></i> Agri<span>Trace+</span>
            </div>

            <div class="otp-icon" id="otp-icon">
                <i class="bi bi-shield-lock-fill" id="icon-inner"></i>
            </div>

            <h1 id="page-title">Verify Your Identity</h1>
            <p class="subtitle" id="page-sub">Enter the 6-digit code we sent to</p>
            <div class="dest-label" id="dest-label">loading…</div>

            <div class="otp-row" id="otp-row">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="one-time-code" aria-label="OTP digit 1">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 2">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 3">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 4">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 5">
                <input class="otp-box" type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" autocomplete="off" aria-label="OTP digit 6">
            </div>

            <button class="btn-verify" id="verify-btn" disabled>
                <span id="btn-text">Verify Code</span>
                <i class="bi bi-arrow-right" id="btn-icon"></i>
            </button>

            <div class="resend-row">
                <span id="timer-wrap">Resend code in <strong id="countdown">2:00</strong></span>
                <button id="resend-btn" onclick="resendOtp()">Resend Code</button>
            </div>

            <div class="auth-footer">
                <a href="forgot.php">
                    <i class="bi bi-arrow-left"></i> Change Email / Number
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
        const TYPE       = params.get('type') || 'email';       // 'email' | 'phone'
        const IDENTIFIER = params.get('identifier') || '';

        /* Redirect if no identifier */
        if (!IDENTIFIER) window.location.href = 'forgot.php';

        /* Update UI to match channel */
        document.getElementById('dest-label').textContent = IDENTIFIER;
        if (TYPE === 'phone') {
            document.getElementById('page-sub').textContent  = 'Enter the 6-digit SMS code sent to';
            document.getElementById('icon-inner').className  = 'bi bi-phone-fill';
        } else {
            document.getElementById('page-sub').textContent  = 'Enter the 6-digit email code sent to';
        }

        /* ---- OTP boxes behaviour ---- */
        const boxes      = Array.from(document.querySelectorAll('.otp-box'));
        const verifyBtn  = document.getElementById('verify-btn');

        boxes.forEach((box, idx) => {
            box.addEventListener('keydown', e => {
                if (e.key === 'Backspace' && !box.value && idx > 0) {
                    boxes[idx - 1].focus();
                    boxes[idx - 1].value = '';
                    boxes[idx - 1].classList.remove('filled', 'error');
                }
            });

            box.addEventListener('input', e => {
                /* Allow only digits */
                box.value = box.value.replace(/\D/g, '').slice(-1);
                box.classList.toggle('filled', box.value !== '');
                box.classList.remove('error');

                if (box.value && idx < boxes.length - 1) {
                    boxes[idx + 1].focus();
                }

                checkFilled();
            });

            box.addEventListener('paste', e => {
                e.preventDefault();
                const pasted = (e.clipboardData || window.clipboardData)
                    .getData('text').replace(/\D/g, '').slice(0, 6);
                pasted.split('').forEach((ch, i) => {
                    if (boxes[i]) { boxes[i].value = ch; boxes[i].classList.add('filled'); }
                });
                const next = Math.min(pasted.length, boxes.length - 1);
                boxes[next].focus();
                checkFilled();
            });
        });

        function checkFilled() {
            const code = boxes.map(b => b.value).join('');
            verifyBtn.disabled = code.length < 6;
        }

        function getCode() {
            return boxes.map(b => b.value).join('');
        }

        function setError() {
            boxes.forEach(b => { b.classList.add('error'); b.classList.remove('filled'); b.value = ''; });
            boxes[0].focus();
            verifyBtn.disabled = true;
        }

        /* ---- Verify button ---- */
        verifyBtn.addEventListener('click', async () => {
            const code = getCode();
            if (code.length < 6) return;

            const btnText = document.getElementById('btn-text');
            const btnIcon = document.getElementById('btn-icon');
            verifyBtn.disabled  = true;
            btnText.textContent = 'Verifying…';
            btnIcon.className   = 'bi bi-arrow-repeat spinner';

            try {
                const res    = await fetch(`../api/db.php?action=verify-otp&identifier=${encodeURIComponent(IDENTIFIER)}&type=${encodeURIComponent(TYPE)}&code=${encodeURIComponent(code)}`);
                const result = await res.json();

                if (result.success) {
                    btnText.textContent = 'Verified!';
                    btnIcon.className   = 'bi bi-check2-all';
                    showToast('Identity confirmed! Redirecting…', false);

                    /* Redirect to reset-password page with token */
                    setTimeout(() => {
                        window.location.href = `reset-password.php?token=${encodeURIComponent(result.token)}&identifier=${encodeURIComponent(IDENTIFIER)}`;
                    }, 1200);
                } else {
                    showToast(result.error || 'Incorrect code. Try again.', true);
                    setError();
                    verifyBtn.disabled = true;
                    btnText.textContent = 'Verify Code';
                    btnIcon.className   = 'bi bi-arrow-right';
                }
            } catch {
                showToast('Network error. Please try again.', true);
                verifyBtn.disabled  = false;
                btnText.textContent = 'Verify Code';
                btnIcon.className   = 'bi bi-arrow-right';
            }
        });

        /* ---- Countdown timer ---- */
        let timerInterval;
        let secondsLeft = 120; // 2 minutes

        function startTimer() {
            clearInterval(timerInterval);
            secondsLeft = 120;
            document.getElementById('timer-wrap').style.display = 'inline';
            document.getElementById('resend-btn').style.display = 'none';

            timerInterval = setInterval(() => {
                secondsLeft--;
                const m = Math.floor(secondsLeft / 60);
                const s = secondsLeft % 60;
                document.getElementById('countdown').textContent =
                    `${m}:${s.toString().padStart(2, '0')}`;

                if (secondsLeft <= 0) {
                    clearInterval(timerInterval);
                    document.getElementById('timer-wrap').style.display = 'none';
                    document.getElementById('resend-btn').style.display = 'inline';
                }
            }, 1000);
        }

        startTimer();

        /* ---- Resend OTP ---- */
        async function resendOtp() {
            const resendBtn = document.getElementById('resend-btn');
            resendBtn.disabled   = true;
            resendBtn.textContent = 'Sending…';

            try {
                const res    = await fetch(`../api/db.php?action=send-otp&identifier=${encodeURIComponent(IDENTIFIER)}&type=${encodeURIComponent(TYPE)}`);
                const result = await res.json();

                if (result.success) {
                    showToast('New code sent!', false);
                    boxes.forEach(b => { b.value = ''; b.classList.remove('filled', 'error'); });
                    boxes[0].focus();
                    verifyBtn.disabled = true;
                    startTimer();
                } else {
                    showToast(result.error || 'Could not resend code.', true);
                }
            } catch {
                showToast('Network error. Please try again.', true);
            }

            resendBtn.disabled   = false;
            resendBtn.textContent = 'Resend Code';
        }

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

        /* Auto-focus first box */
        boxes[0].focus();
    </script>
</body>
</html>