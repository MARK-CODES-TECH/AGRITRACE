<?php 
session_start(); 

// Connect to correct database myproject4
try {
    require __DIR__ . '/db_connect.php';
} catch (Throwable $e) {
    $db_error = 'Database connection failed';
}

$login_error = '';
$login_success = false;
$info_message = isset($_GET['logged_out']) ? 'You have been logged out successfully.' : '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'Active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];           // ✅ Correct
                $_SESSION['user_role'] = $user['role'];       // ✅ Changed from 'user_role' to match farmer.php check
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['firstName'] = $user['firstName'];
                $_SESSION['lastName'] = $user['lastName'];
                $_SESSION['user_name'] = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
                $_SESSION['mobile'] = $user['mobile'];
                $_SESSION['logged_in'] = true;   
                
                $login_success = true;
                
                // Redirect to dashboard instead of directly to panel
                header("Location: dashboard.php");
                exit;
                
            } else {
                $login_error = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $login_error = 'Database query failed';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | AgriTrace+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --forest: #123f33;
            --teal: #1aa37a;
            --mint: #45d19a;
            --sun: #f5b542;
            --night: #0e1a16;
            --card: #fdfdfc;
            --ink: #1f2a26;
            --muted: #5f6f68;
            --danger: #d93025;
            --ring: rgba(26, 163, 122, 0.3);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        html {
            overflow-x: hidden;
            width: 100%;
            -webkit-text-size-adjust: 100%;
            text-size-adjust: 100%;
        }

        body {
            min-height: 100vh;
            font-family: 'Outfit', sans-serif;
            color: var(--ink);
            background:
                linear-gradient(130deg, rgba(14, 26, 22, 0.95), rgba(18, 63, 51, 0.92)),
                url('https://images.unsplash.com/photo-1516466723877-e4ec1d736c8a?auto=format&fit=crop&q=80&w=2000') center/cover;
            padding: 24px;
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
        }

        h1, h2, h3, h4, p, span, a, label, strong, em {
            overflow-wrap: break-word;
            word-wrap: break-word;
        }

        .auth-shell {
            max-width: 1220px;
            margin: 0 auto;
            min-height: calc(100vh - 48px);
            border-radius: 30px;
            overflow: hidden;
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
            background: var(--card);
            position: relative;
        }

        .auth-shell::before {
            content: '';
            position: absolute;
            inset: 0;
            pointer-events: none;
            background: linear-gradient(130deg, rgba(69, 209, 154, 0.1), transparent 40%, rgba(245, 181, 66, 0.1));
            z-index: 1;
        }

        .visual-pane {
            position: relative;
            color: #ffffff;
            padding: 44px 40px;
            background:
                linear-gradient(160deg, rgba(14, 26, 22, 0.2), rgba(14, 26, 22, 0.5)),
                radial-gradient(circle at 20% 20%, rgba(69, 209, 154, 0.55), transparent 45%),
                radial-gradient(circle at 80% 90%, rgba(245, 181, 66, 0.4), transparent 45%),
                linear-gradient(160deg, #0e1a16, #123f33 65%, #1a6f55);
            z-index: 2;
        }

        .top-brand {
            position: relative;
            z-index: 1;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            letter-spacing: -0.01em;
            padding: 10px 14px;
            border-radius: 14px;
            border: 1px solid rgba(255, 255, 255, 0.22);
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(7px);
        }

        .top-brand i { color: #c8ffeb; }

        .headline {
            position: relative;
            z-index: 1;
            margin-top: 52px;
            max-width: 460px;
        }

        .headline h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2rem, 4.5vw, 3.1rem);
            line-height: 1.03;
            letter-spacing: -0.03em;
        }

        .headline p {
            margin-top: 16px;
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.88);
            max-width: 380px;
        }

        .mini-list {
            margin-top: 30px;
            display: grid;
            gap: 10px;
            max-width: 420px;
        }

        .mini-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.24);
        }

        .mini-item i { color: #d8ffe7; }

        .form-pane {
            padding: 26px;
            background:
                radial-gradient(circle at 95% 0%, rgba(245, 181, 66, 0.18), transparent 35%),
                radial-gradient(circle at 0% 100%, rgba(26, 163, 122, 0.14), transparent 35%),
                #f8fbfa;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .form-card {
            width: 100%;
            max-width: 520px;
            border-radius: 24px;
            background: #ffffff;
            border: 1px solid #d8e2de;
            padding: 24px;
            position: relative;
            box-shadow: 0 20px 46px rgba(18, 63, 51, 0.15);
        }

        .close-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            border: 1px solid #cad7d1;
            width: 36px;
            height: 36px;
            border-radius: 10px;
            background: #ffffff;
            color: #4b5563;
            cursor: pointer;
            display: grid;
            place-items: center;
        }

        .quick-chip {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            border: 1px solid #b8e7d6;
            background: #ecfaf4;
            color: #116244;
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            padding: 6px 10px;
            margin-bottom: 10px;
        }

        .card-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.7rem;
            color: #12231d;
            line-height: 1.2;
            margin-bottom: 7px;
        }

        .card-subtitle {
            color: var(--muted);
            font-size: 0.93rem;
            margin-bottom: 18px;
        }

        .status-msg {
            margin-bottom: 14px;
            border-radius: 11px;
            padding: 11px 13px;
            font-size: 0.86rem;
            font-weight: 600;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .status-ok {
            background: #ecfdf5;
            border: 1px solid #86efac;
            color: #166534;
        }

        .status-error {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .form-group {
            margin-top: 12px;
        }

        .form-label {
            display: block;
            font-size: 0.79rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            color: #38534a;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i:not(.password-toggle) {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }

        .form-input {
            width: 100%;
            border: 1px solid #c5d5ce;
            border-radius: 12px;
            background: #ffffff;
            color: #10221b;
            padding: 12px 12px 12px 39px;
            font-size: 0.93rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-input:focus {
            border-color: var(--teal);
            box-shadow: 0 0 0 4px var(--ring);
            outline: none;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            cursor: pointer;
        }

        .inline-error {
            margin-top: 8px;
            min-height: 18px;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--danger);
        }

        .btn-login {
            margin-top: 12px;
            width: 100%;
            border: none;
            border-radius: 13px;
            padding: 13px;
            font-size: 0.95rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            color: #ffffff;
            background: linear-gradient(135deg, var(--forest), var(--teal));
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-login:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(26, 163, 122, 0.36);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            box-shadow: none;
        }

        .footer-links {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            font-size: 0.86rem;
        }

        .footer-links a,
        .btn-back {
            color: var(--teal);
            text-decoration: none;
            font-weight: 700;
        }

        .divider {
            height: 1px;
            background: #e5ece8;
            margin: 18px 0;
        }

        .btn-back { display: inline-flex; align-items: center; gap: 7px; }

        @media (max-width: 980px) {
            * { scrollbar-width: none !important; -ms-overflow-style: none !important; }
            *::-webkit-scrollbar, html::-webkit-scrollbar, body::-webkit-scrollbar, ::-webkit-scrollbar { display: none !important; width: 0 !important; height: 0 !important; background: transparent !important; }
            .auth-shell { grid-template-columns: 1fr; }
            .visual-pane { min-height: 250px; }
            .headline { margin-top: 30px; }
        }

        @media (max-width: 650px) {
            body { padding: 8px; }
            .auth-shell { min-height: calc(100vh - 16px); border-radius: 18px; }
            .form-pane { padding: 10px; }
            .form-card { padding: 18px 16px; border-radius: 16px; }
            .card-title { font-size: 1.45rem; }
            input { font-size: 16px !important; }
        }

        @media (max-width: 480px) {
            body { padding: 4px; }
            .auth-shell { border-radius: 14px; min-height: 100vh; }
            .visual-pane { padding: 18px 16px; min-height: 200px; }
            .headline h1 { font-size: 1.65rem; }
            .headline p { font-size: 0.88rem; margin-top: 10px; }
            .form-card { padding: 16px 14px; }
            .role-grid { gap: 6px; }
            .role-btn { padding: 10px 8px; font-size: 0.82rem; }
        }

        .lang-switch-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 9999px;
            background: #ffffff;
            border: 1px solid #d1ded8;
            color: #1f2a26;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.78rem;
            font-weight: 700;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
            outline: none;
        }

        .lang-switch-btn:hover {
            background: #f1f7f4;
            border-color: #1aa37a;
            transform: translateY(-1px);
        }

        .lang-switch-btn.active-tl {
            background: #ecfdf5;
            border-color: #86efac;
            color: #166534;
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="visual-pane">
            <div class="top-brand">
                <i class="bi bi-leaf-fill"></i>
                <span>AgriTrace+</span>
            </div>

            <div class="headline">
                <h1>Welcome To Agritace+: A Digital Livestock Registration and Monitoring Sytem</h1>
                <p>Sign in to monitor livestock, review incidents, and keep your agriculture data up to date.</p>
            </div>

          <div class="mini-list">
                <div class="mini-item">
                    <i class="bi bi-speedometer2"></i>
                    <span>Unified dashboard for daily operations</span>
                </div>
                <div class="mini-item">
                    <i class="bi bi-shield-check"></i>
                    <span>Secure access by role and account status</span>
                </div>
                <div class="mini-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Geo-aware records and incident workflows</span>
                </div>
            </div>
        </section>

        <section class="form-pane">
            <div class="form-card">
                <button type="button" class="close-btn" onclick="window.location.href='index.php'" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <div class="quick-chip" style="margin-bottom:0;"><i class="bi bi-stars"></i> AGRITRACE ACCESS</div>
                    <button type="button" class="lang-switch-btn" onclick="toggleLanguage()" title="Palitan ang Wika / Switch Language"><span class="lang-flag">🇵🇭</span> <span class="lang-label">Tagalog</span></button>
                </div>
                <h2 class="card-title">Sign In</h2>
                <p class="card-subtitle">Access your AgriTrace+ workspace.</p>

                <?php if (isset($db_error)): ?>
                    <div class="status-msg status-error">
                        <i class="bi bi-database-exclamation"></i>
                        <span>Database connection issue. Please try again later.</span>
                    </div>
                <?php endif; ?>

                <?php if ($info_message): ?>
                    <div class="status-msg status-ok">
                        <i class="bi bi-check-circle-fill"></i>
                        <span><?= htmlspecialchars($info_message) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($login_error): ?>
                    <div class="status-msg status-error">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($login_error) ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" id="loginForm" novalidate>
                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <div class="input-wrap">
                            <i class="bi bi-envelope"></i>
                            <input id="email" type="email" name="email" class="form-input" placeholder="juan@example.com" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <div class="input-wrap">
                            <i class="bi bi-shield-lock"></i>
                            <input id="password" type="password" name="password" class="form-input" placeholder="Enter your password" required>
                            <i class="bi bi-eye password-toggle" id="toggleIcon" role="button" aria-label="Toggle password"></i>
                        </div>
                    </div>

                    <div class="inline-error" id="formInlineError" aria-live="polite"></div>

                    <button type="submit" class="btn-login" id="submitBtn">SIGN IN</button>
                </form>

                <div class="footer-links">
                    <a href="register-form.php">Create Account</a>
                    <a href="forgot.php">Forgot Password?</a>
                </div>

                <div style="margin-top: 14px; padding: 10px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; text-align: center;">
                    <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px;">
                        <i class="bi bi-lightning-charge-fill" style="color: #f59e0b;"></i> Fast Defense Sign-In (Localhost)
                    </div>
                    <div style="display: flex; gap: 6px; justify-content: center; flex-wrap: wrap;">
                        <a href="quick_login.php?role=Admin" style="padding: 4px 10px; background: #059669; color: #fff; border-radius: 8px; font-size: 0.75rem; text-decoration: none; font-weight: 600;">👑 Admin</a>
                        <a href="quick_login.php?role=Agriculture+Official" style="padding: 4px 10px; background: #0284c7; color: #fff; border-radius: 8px; font-size: 0.75rem; text-decoration: none; font-weight: 600;">🌾 DA Official</a>
                        <a href="quick_login.php?role=Farmer" style="padding: 4px 10px; background: #d97706; color: #fff; border-radius: 8px; font-size: 0.75rem; text-decoration: none; font-weight: 600;">🚜 Farmer</a>
                    </div>
                </div>

                <div class="divider"></div>

                <a href="index.php" class="btn-back">
                    <i class="bi bi-arrow-left"></i>
                    <span>Back to Home</span>
                </a>
            </div>
        </section>
    </main>

    <script>
        const passwordInput = document.getElementById('password');
        const toggleIcon = document.getElementById('toggleIcon');
        const form = document.getElementById('loginForm');
        const submitBtn = document.getElementById('submitBtn');
        const inlineError = document.getElementById('formInlineError');

        if (toggleIcon && passwordInput) {
            toggleIcon.addEventListener('click', () => {
                const isHidden = passwordInput.type === 'password';
                passwordInput.type = isHidden ? 'text' : 'password';
                toggleIcon.classList.toggle('bi-eye', !isHidden);
                toggleIcon.classList.toggle('bi-eye-slash', isHidden);
            });
        }

        if (form) {
            form.addEventListener('submit', (e) => {
                inlineError.textContent = '';

                const email = document.getElementById('email').value.trim();
                const password = passwordInput.value;

                if (!email || !password) {
                    e.preventDefault();
                    inlineError.textContent = 'Please enter both email and password.';
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = 'SIGNING IN... <i class="bi bi-arrow-repeat"></i>';
            });
        }
    </script>
    <script src="../assets/js/translator.js"></script>
</body>
</html>