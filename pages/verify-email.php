<?php
session_start();

try {
    require __DIR__ . '/db_connect.php';
} catch (Throwable $e) {
    $db_error = 'Database connection failed';
}

$state   = 'error'; // 'success' | 'error'
$message = 'Something went wrong verifying your email.';

$token = trim($_GET['token'] ?? '');
$email = trim($_GET['email'] ?? '');

if ($token === '' || $email === '') {
    $message = 'This verification link is missing required information.';
} elseif (!isset($pdo)) {
    $message = $db_error ?? 'Database connection failed.';
} else {
    try {
        $stmt = $pdo->prepare(
            "SELECT id FROM users
             WHERE email = ? AND verifyToken = ?
             AND emailVerified = 0
             AND verifyExpires IS NOT NULL AND verifyExpires > NOW()"
        );
        $stmt->execute([$email, $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $update = $pdo->prepare(
                "UPDATE users SET emailVerified = 1, verifyToken = NULL, verifyExpires = NULL WHERE id = ?"
            );
            $update->execute([(int)$user['id']]);

            $state   = 'success';
            $message = "Your email address has been confirmed. Your account is now waiting for admin approval — you'll be able to log in once it's approved.";
        } else {
            // Distinguish "already verified" from "invalid/expired" for a clearer message.
            $already = $pdo->prepare("SELECT id FROM users WHERE email = ? AND emailVerified = 1");
            $already->execute([$email]);

            if ($already->fetch()) {
                $state   = 'success';
                $message = 'This email address is already confirmed. You can log in once your account is approved.';
            } else {
                $state   = 'error';
                $message = 'This verification link is invalid or has expired. Please register again to get a new one.';
            }
        }
    } catch (PDOException $e) {
        error_log('Email verification error: ' . $e->getMessage());
        $message = 'Something went wrong verifying your email. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Email | AgriTrace+</title>
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
            letter-spacing: -1px;
        }
        .brand-logo i  { color: var(--green); font-size: 1.7rem; }
        .brand-logo span { color: var(--green); }

        .status-icon {
            width: 76px; height: 76px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 22px;
            font-size: 2.1rem;
        }
        .status-icon.success {
            background: rgba(16,185,129,0.18);
            border: 2px solid rgba(16,185,129,0.4);
            color: var(--green);
        }
        .status-icon.error {
            background: rgba(239,68,68,0.15);
            border: 2px solid rgba(239,68,68,0.4);
            color: #f87171;
        }

        h1 { color: #fff; font-size: 1.5rem; font-weight: 700; margin-bottom: 12px; }

        .message {
            color: var(--dim);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 15px;
            background: var(--green);
            color: #fff;
            border: none;
            border-radius: 16px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.25s ease;
            box-shadow: 0 10px 20px rgba(16,185,129,0.25);
            margin-bottom: 14px;
        }
        .btn-primary:hover { background: var(--green-hover); transform: translateY(-2px); }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 15px;
            background: rgba(255,255,255,0.08);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.22);
            border-radius: 16px;
            font-weight: 700;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-secondary:hover { background: rgba(255,255,255,0.14); }

        @media (max-width: 480px) {
            .auth-card { padding: 32px 24px; border-radius: 24px; }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="brand-logo">
                <i class="bi bi-leaf-fill"></i> Agri<span>Trace+</span>
            </div>

            <?php if ($state === 'success'): ?>
                <div class="status-icon success">
                    <i class="bi bi-check-lg"></i>
                </div>
                <h1>Email Confirmed</h1>
                <p class="message"><?= htmlspecialchars($message) ?></p>
                <a href="login.php" class="btn-primary">
                    Go to Login <i class="bi bi-arrow-right"></i>
                </a>
            <?php else: ?>
                <div class="status-icon error">
                    <i class="bi bi-x-lg"></i>
                </div>
                <h1>Verification Failed</h1>
                <p class="message"><?= htmlspecialchars($message) ?></p>
                <a href="register-form.php" class="btn-primary">
                    Register Again <i class="bi bi-arrow-right"></i>
                </a>
                <a href="login.php" class="btn-secondary">
                    Back to Login
                </a>
            <?php endif; ?>
        </div>

        <div style="text-align:center;margin-top:30px;color:rgba(255,255,255,0.4);font-size:0.75rem;">
            &copy; 2026 AgriTrace Technologies. All rights reserved.
        </div>
    </div>
</body>
</html>