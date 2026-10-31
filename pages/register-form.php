<?php
session_start();

// Connect to database (same as login.php)
try {
    require __DIR__ . '/db_connect.php';
} catch (Throwable $e) {
    $db_error = 'Database connection failed';
}

// Composer autoload for PHPMailer
require_once dirname(__DIR__) . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/* ========================================
   Mail config — env vars, same pattern as api/db.php.
   Set these in your server config, not in source control.
   ======================================== */
define('MAIL_HOST',      getenv('MAIL_HOST')      ?: 'smtp.gmail.com');
define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME',  getenv('MAIL_USERNAME')  ?: '');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD')  ?: '');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'AgriTrace+');

// Base URL used to build the verification link. Set APP_BASE_URL in your
// environment (e.g. https://agritrace.example.com) for production so the
// link is correct regardless of how the script was reached.
$proto = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$currentDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/pages')), '/');
define('APP_BASE_URL', rtrim(getenv('APP_BASE_URL') ?: ($proto . $host . $currentDir), '/'));

/**
 * Send the "confirm your email" message.
 */
function sendVerificationEmail(string $toEmail, string $toName, string $verifyLink): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Confirm your AgriTrace+ email address';
        $safeLink = htmlspecialchars($verifyLink, ENT_QUOTES);
        $mail->Body = "
            <div style='font-family:DM Sans,Arial,sans-serif;max-width:480px;margin:0 auto;background:#f8fbfa;border-radius:16px;padding:36px;border:1px solid #d8e2de;'>
                <div style='text-align:center;margin-bottom:24px;'>
                    <span style='font-size:1.8rem;font-weight:800;color:#123f33;letter-spacing:-1px;'>🌿 AgriTrace+</span>
                </div>
                <h2 style='color:#12231d;font-size:1.3rem;margin-bottom:8px;'>Confirm your email</h2>
                <p style='color:#5f6f68;font-size:0.95rem;line-height:1.5;margin-bottom:26px;'>
                    Hi " . htmlspecialchars($toName, ENT_QUOTES) . ", thanks for signing up. Click the button below to confirm this
                    email address and finish creating your account. This link expires in <strong>24 hours</strong>.
                </p>
                <div style='text-align:center;margin-bottom:26px;'>
                    <a href='{$safeLink}' style='display:inline-block;background:#123f33;color:#ffffff;text-decoration:none;font-weight:700;padding:14px 28px;border-radius:12px;'>
                        Confirm Email Address
                    </a>
                </div>
                <p style='color:#9ca3af;font-size:0.78rem;line-height:1.5;word-break:break-all;'>
                    Or paste this link into your browser:<br>{$safeLink}
                </p>
                <hr style='border:none;border-top:1px solid #e5e7eb;margin:20px 0;'>
                <p style='color:#9ca3af;font-size:0.8rem;line-height:1.5;'>
                    If you did not create an AgriTrace+ account, you can safely ignore this email — no account will be
                    activated without confirmation.
                </p>
                <p style='color:#9ca3af;font-size:0.75rem;text-align:center;margin-top:16px;'>&copy; 2026 AgriTrace Technologies</p>
            </div>
        ";
        $mail->AltBody = "Confirm your AgriTrace+ email by visiting: {$verifyLink}\nThis link expires in 24 hours.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Verification email error: {$mail->ErrorInfo}");
        return false;
    }
}

$register_success = '';
$register_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fname     = trim($_POST['fname'] ?? '');
    $lname     = trim($_POST['lname'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $mobile    = trim($_POST['mobile'] ?? '');
    $role      = $_POST['role'] ?? '';
    $password  = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';

    // Server-side validation
    $errors = [];

    if (empty($fname) || empty($lname) || empty($email) || empty($mobile) || empty($role) || empty($password)) {
        $errors[] = "All fields are required.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if ($password !== $cpassword) {
        $errors[] = "Passwords do not match.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }

    if (!isset($_POST['terms'])) {
        $errors[] = "You must agree to the Terms and Conditions.";
    }

    // Check for an existing account with this email.
    // - If it's already verified, block registration (real duplicate).
    // - If it's unverified but the verification link hasn't expired yet,
    //   block it too (someone already has a pending confirmation for it).
    // - If it's unverified AND expired, treat it as abandoned: delete the
    //   stale row so the email isn't permanently locked out, then proceed.
    if (empty($errors)) {
        try {
            $checkStmt = $pdo->prepare(
                "SELECT id, emailVerified, verifyExpires FROM users WHERE email = ?"
            );
            $checkStmt->execute([$email]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $isVerified   = (int)($existing['emailVerified'] ?? 0) === 1;
                $stillPending = !empty($existing['verifyExpires']) && strtotime($existing['verifyExpires']) > time();

                if ($isVerified) {
                    $errors[] = "Email already exists. Please log in or use \"Forgot password\" instead.";
                } elseif ($stillPending) {
                    $errors[] = "A confirmation email was already sent to this address. Please check your inbox (or spam folder), or wait for it to expire before trying again.";
                } else {
                    // Stale, unverified, expired — safe to remove and let this registration take over.
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$existing['id']]);
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database check failed.";
        }
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $verifyToken    = bin2hex(random_bytes(32));

            // verifyExpires computed by MySQL (not PHP date()) to avoid any
            // PHP/MySQL timezone drift when it's later compared with NOW().
            $stmt = $pdo->prepare(
                "INSERT INTO users
                    (firstName, lastName, email, password, mobile, role, status, emailVerified, verifyToken, verifyExpires)
                 VALUES
                    (?, ?, ?, ?, ?, ?, 'Pending', 0, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))"
            );
            $stmt->execute([$fname, $lname, $email, $hashedPassword, $mobile, $role, $verifyToken]);

            $verifyLink = APP_BASE_URL . '/verify-email.php?token=' . urlencode($verifyToken) . '&email=' . urlencode($email);
            $sent = sendVerificationEmail($email, $fname, $verifyLink);

            if ($sent) {
                $register_success = "Almost there! We've sent a confirmation link to {$email}. Please check your inbox to verify your email — your account will also need admin approval before you can log in.";
            } else {
                // Registration still succeeded in the DB, but let the user know
                // the email didn't go out so they're not left wondering.
                $register_error = "Your account was created, but we couldn't send the confirmation email. Please contact support to verify your email manually.";
            }
        } catch (PDOException $e) {
            $register_error = "Registration failed. Please try again.";
            error_log("Registration error: " . $e->getMessage());
        }
    } else {
        $register_error = implode(' ', $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | AgriTrace+</title>
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
            --weak: #ef4444;
            --fair: #f59e0b;
            --good: #3b82f6;
            --strong: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            font-family: 'Outfit', sans-serif;
            color: var(--ink);
            background:
                linear-gradient(130deg, rgba(14, 26, 22, 0.95), rgba(18, 63, 51, 0.92)),
                url('https://images.unsplash.com/photo-1516466723877-e4ec1d736c8a?auto=format&fit=crop&q=80&w=2000') center/cover;
            padding: 24px;
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

        .floating-blur {
            position: absolute;
            border-radius: 999px;
            opacity: 0.28;
            animation: drift 7s ease-in-out infinite alternate;
        }

        .blob-one {
            width: 210px;
            height: 210px;
            right: -48px;
            top: 22px;
            background: #45d19a;
        }

        .blob-two {
            width: 190px;
            height: 190px;
            left: -55px;
            bottom: -30px;
            background: #f5b542;
            animation-delay: 1s;
        }

        @keyframes drift {
            from { transform: translateY(0px) translateX(0px) rotate(0deg); }
            to { transform: translateY(-16px) translateX(8px) rotate(8deg); }
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
            margin-top: 40px;
            max-width: 440px;
        }

        .headline h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: clamp(2rem, 4.5vw, 3.2rem);
            line-height: 1.03;
            letter-spacing: -0.03em;
        }

        .headline p {
            margin-top: 16px;
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.88);
            max-width: 380px;
        }

        .feature-list {
            position: relative;
            z-index: 1;
            margin-top: 28px;
            display: grid;
            gap: 10px;
            max-width: 430px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.11);
            border: 1px solid rgba(255, 255, 255, 0.24);
            backdrop-filter: blur(6px);
            transition: transform 0.25s ease, background-color 0.25s ease;
            animation: fadeSlide 0.55s ease both;
        }

        .feature-item:nth-child(2) { animation-delay: 0.08s; }
        .feature-item:nth-child(3) { animation-delay: 0.16s; }

        .feature-item:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.16);
        }

        @keyframes fadeSlide {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .feature-item i {
            color: #d8ffe7;
            font-size: 1.05rem;
        }

        .feature-item span {
            font-size: 0.94rem;
            font-weight: 600;
        }

        .insight-strip {
            position: relative;
            z-index: 1;
            margin-top: 22px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .insight-card {
            border-radius: 10px;
            padding: 10px 9px;
            background: rgba(255, 255, 255, 0.13);
            border: 1px solid rgba(255, 255, 255, 0.24);
            backdrop-filter: blur(5px);
        }

        .insight-card strong {
            display: block;
            font-size: 1.06rem;
            font-family: 'Sora', sans-serif;
        }

        .insight-card span {
            font-size: 0.73rem;
            color: rgba(255, 255, 255, 0.86);
            letter-spacing: 0.03em;
        }

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
            max-width: 560px;
            border-radius: 24px;
            background: #ffffff;
            border: 1px solid #d8e2de;
            padding: 24px;
            position: relative;
            box-shadow: 0 20px 46px rgba(18, 63, 51, 0.15);
            animation: riseIn 0.55s ease-out;
            overflow: hidden;
        }

        .form-card::before {
            content: '';
            position: absolute;
            left: -35%;
            top: -95%;
            width: 78%;
            height: 220%;
            background: linear-gradient(140deg, rgba(26, 163, 122, 0.14), transparent 60%);
            transform: rotate(18deg);
            pointer-events: none;
        }

        @keyframes riseIn {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
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
            transition: all 0.2s ease;
        }

        .close-btn:hover {
            background: #f3f4f6;
            color: #111827;
        }

        .card-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.7rem;
            color: #12231d;
            line-height: 1.2;
            margin-bottom: 7px;
            position: relative;
            z-index: 1;
        }

        .card-subtitle {
            color: var(--muted);
            font-size: 0.93rem;
            margin-bottom: 18px;
            position: relative;
            z-index: 1;
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
            position: relative;
            z-index: 1;
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

        .grid-two {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .form-group {
            margin-top: 12px;
            position: relative;
            z-index: 1;
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
            transition: transform 0.2s ease;
        }

        .input-wrap:focus-within {
            transform: translateY(-1px);
        }

        .input-wrap i:not(.password-toggle) {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
        }

        .form-input,
        .form-select {
            width: 100%;
            border: 1px solid #c5d5ce;
            border-radius: 12px;
            background: #ffffff;
            color: #10221b;
            padding: 12px 12px 12px 39px;
            font-size: 0.93rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-input:focus,
        .form-select:focus {
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

        .strength-container {
            margin-top: 9px;
        }

        .strength-meter {
            height: 6px;
            border-radius: 999px;
            background: #e5e7eb;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
        }

        .strength-text {
            margin-top: 4px;
            display: inline-block;
            font-size: 0.78rem;
            font-weight: 800;
            letter-spacing: 0.03em;
        }

        .requirements-grid {
            margin-top: 11px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 7px;
        }

        .req-item {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 10px;
            padding: 7px 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.74rem;
            color: #4b5563;
            font-weight: 600;
        }

        .req-item.valid {
            border-color: #86efac;
            background: #ecfdf5;
            color: #166534;
        }

        .inline-error {
            margin-top: 7px;
            font-size: 0.8rem;
            color: var(--danger);
            min-height: 18px;
            font-weight: 600;
        }

        .terms {
            margin-top: 10px;
            display: flex;
            gap: 8px;
            align-items: flex-start;
            font-size: 0.82rem;
            color: #374151;
        }

        .terms a {
            color: var(--teal);
            font-weight: 700;
            text-decoration: none;
        }

        .btn-register {
            margin-top: 14px;
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
            position: relative;
            overflow: hidden;
        }

        .btn-register::after {
            content: '';
            position: absolute;
            inset: 0;
            transform: translateX(-105%);
            background: linear-gradient(100deg, transparent 20%, rgba(255, 255, 255, 0.3), transparent 80%);
            transition: transform 0.45s ease;
        }

        .btn-register:hover {
            transform: translateY(-1px);
            box-shadow: 0 12px 24px rgba(26, 163, 122, 0.36);
        }

        .btn-register:hover::after {
            transform: translateX(105%);
        }

        .btn-register:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            box-shadow: none;
            transform: none;
        }

        .footer-line {
            margin-top: 15px;
            font-size: 0.85rem;
            color: #4b5563;
            text-align: center;
        }

        .footer-line a {
            color: var(--teal);
            font-weight: 700;
            text-decoration: none;
        }

        .tiny-copy {
            margin-top: 14px;
            text-align: center;
            color: #9ca3af;
            font-size: 0.72rem;
        }

        @media (prefers-reduced-motion: reduce) {
            * {
                animation: none !important;
                transition: none !important;
            }
        }

        @media (max-width: 980px) {
            .auth-shell {
                grid-template-columns: 1fr;
            }

            .visual-pane {
                min-height: 270px;
                padding: 30px;
            }

            .headline {
                margin-top: 26px;
            }

            .insight-strip {
                max-width: 450px;
            }
        }

        @media (max-width: 650px) {
            body {
                padding: 10px;
            }

            .auth-shell {
                min-height: calc(100vh - 20px);
                border-radius: 18px;
            }

            .form-pane {
                padding: 12px;
            }

            .form-card {
                padding: 18px;
                border-radius: 16px;
            }

            .grid-two,
            .requirements-grid {
                grid-template-columns: 1fr;
            }

            .insight-strip {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="visual-pane">
            <div class="floating-blur blob-one"></div>
            <div class="floating-blur blob-two"></div>

            <div class="top-brand">
                <i class="bi bi-leaf-fill"></i>
                <span>AgriTrace+</span>
            </div>

            <div class="headline">
                <h1>Register Once. Manage Every Farm Better.</h1>
                <p>A faster onboarding experience designed for modern agriculture operations and accurate field reporting.</p>
            </div>

            <div class="feature-list">
                <div class="feature-item">
                    <i class="bi bi-shield-check"></i>
                    <span>Smart onboarding with built-in security checks</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span>Geo-tag friendly setup for location-based records</span>
                </div>
                <div class="feature-item">
                    <i class="bi bi-broadcast-pin"></i>
                    <span>Faster reports with better incident visibility</span>
                </div>
            </div>

            <div class="insight-strip">
                <div class="insight-card">
                    <strong>45s</strong>
                    <span>AVERAGE SIGNUP</span>
                </div>
                <div class="insight-card">
                    <strong>24/7</strong>
                    <span>DATA ACCESS</span>
                </div>
                <div class="insight-card">
                    <strong>100%</strong>
                    <span>ROLE-BASED FLOW</span>
                </div>
            </div>
        </section>

        <section class="form-pane">
            <div class="form-card">
                <button type="button" class="close-btn" onclick="window.history.back()" aria-label="Close">
                    <i class="bi bi-x-lg"></i>
                </button>

                <div class="quick-chip"><i class="bi bi-stars"></i> AGRITRACE ACCESS</div>
                <h2 class="card-title">Create Your Account</h2>
                <p class="card-subtitle">Set up your secure account and start working with real-time farm data.</p>

                <?php if ($register_success): ?>
                    <div class="status-msg status-ok">
                        <i class="bi bi-envelope-check-fill"></i>
                        <span><?= htmlspecialchars($register_success) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($register_error): ?>
                    <div class="status-msg status-error" id="serverError">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= htmlspecialchars($register_error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!$register_success): ?>
                    <form action="register-form.php" method="POST" id="registerForm" novalidate>
                        <div class="grid-two">
                            <div class="form-group">
                                <label class="form-label" for="fname">First Name</label>
                                <div class="input-wrap">
                                    <i class="bi bi-person"></i>
                                    <input id="fname" type="text" name="fname" class="form-input" placeholder="Juan" value="<?= htmlspecialchars($_POST['fname'] ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="lname">Last Name</label>
                                <div class="input-wrap">
                                    <i class="bi bi-person"></i>
                                    <input id="lname" type="text" name="lname" class="form-input" placeholder="Dela Cruz" value="<?= htmlspecialchars($_POST['lname'] ?? '') ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <div class="input-wrap">
                                <i class="bi bi-envelope"></i>
                                <input id="email" type="email" name="email" class="form-input" placeholder="juan@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="mobile">Mobile Number</label>
                            <div class="input-wrap">
                                <i class="bi bi-phone"></i>
                                <input id="mobile" type="tel" name="mobile" class="form-input" placeholder="+63 9XX XXX XXXX" value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="role">Role</label>
                            <div class="input-wrap">
                                <i class="bi bi-briefcase"></i>
                                <select id="role" name="role" class="form-select" required>
                                    <option value="">Select role</option>
                                    <option value="Farmer" <?= ($_POST['role'] ?? '') === 'Farmer' ? 'selected' : '' ?>>Farmer</option>
                                    <option value="Agriculture Official" <?= ($_POST['role'] ?? '') === 'Agriculture Official' ? 'selected' : '' ?>>Agriculture Official</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="pass">Password</label>
                            <div class="input-wrap">
                                <i class="bi bi-lock"></i>
                                <input id="pass" type="password" name="password" class="form-input" placeholder="Create a strong password" required minlength="8">
                                <i class="bi bi-eye password-toggle" id="toggleIcon" role="button" aria-label="Toggle password"></i>
                            </div>

                            <div class="strength-container">
                                <div class="strength-meter">
                                    <div class="strength-bar" id="strengthBar"></div>
                                </div>
                                <span class="strength-text" id="strengthText">VERY WEAK</span>
                            </div>

                            <div class="requirements-grid">
                                <div class="req-item" id="length"><i class="bi bi-circle"></i> 8+ chars</div>
                                <div class="req-item" id="number"><i class="bi bi-circle"></i> Number</div>
                                <div class="req-item" id="special"><i class="bi bi-circle"></i> Special symbol</div>
                                <div class="req-item" id="lowercase"><i class="bi bi-circle"></i> Lowercase</div>
                                <div class="req-item" id="uppercase"><i class="bi bi-circle"></i> Uppercase</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirmPass">Confirm Password</label>
                            <div class="input-wrap">
                                <i class="bi bi-shield-lock"></i>
                                <input id="confirmPass" type="password" name="cpassword" class="form-input" placeholder="Repeat password" required>
                            </div>
                        </div>

                        <div class="terms">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">
                                I agree to the <a href="javascript:void(0)" onclick="openTermsModal()">Terms and Conditions</a>
                            </label>
                        </div>

                        <div class="inline-error" id="formInlineError" aria-live="polite"></div>

                        <button type="submit" class="btn-register" id="submitBtn">
                            CREATE ACCOUNT
                        </button>

                        <p class="footer-line">
                            Already have an account? <a href="login.php">Log in</a>
                        </p>
                    </form>
                <?php else: ?>
                    <p class="footer-line" style="margin-top: 20px;">
                        Wrong email? <a href="register-form.php">Start over</a> &nbsp;•&nbsp;
                        <a href="login.php">Back to Log in</a>
                    </p>
                <?php endif; ?>

                <p class="tiny-copy">© 2026 AgriTrace Technologies</p>
            </div>
        </section>
    </main>

    <!-- Terms and Conditions Modal -->
    <div id="termsModal" class="terms-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.65);z-index:9999;backdrop-filter:blur(6px);align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:18px;max-width:540px;width:100%;max-height:85vh;overflow-y:auto;padding:28px;box-shadow:0 20px 45px rgba(0,0,0,0.3);font-family:inherit;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;font-size:1.3rem;color:#123f33;display:flex;align-items:center;gap:8px;"><i class="bi bi-shield-check"></i> Terms &amp; Conditions</h3>
                <button type="button" onclick="closeTermsModal()" style="border:none;background:#f1f5f9;width:34px;height:34px;border-radius:50%;cursor:pointer;font-size:1.1rem;display:grid;place-items:center;" aria-label="Close">&times;</button>
            </div>
            <div style="font-size:0.88rem;color:#475569;line-height:1.6;margin-bottom:20px;">
                <p>By registering an account on <strong>AgriTrace+</strong>, you agree to comply with Philippine Biosecurity and Livestock Registration standards under the Department of Agriculture (DA) and the Bureau of Animal Industry (BAI).</p>
                <p style="margin-top:10px;"><strong>1. Truthful Information:</strong> You certify that all farm, livestock, and personal identification data provided during registration are accurate and up-to-date.</p>
                <p style="margin-top:10px;"><strong>2. Incident &amp; Disease Reporting:</strong> You agree to report suspected animal biosecurity incidents (such as African Swine Fever, Avian Influenza, or Foot-and-Mouth Disease) promptly to prevent outbreak propagation across Camarines Sur 1st District.</p>
                <p style="margin-top:10px;"><strong>3. Data Privacy (RA 10173):</strong> Your personal information will be protected and used exclusively by verified DA officials and municipal agriculture offices for disease prevention, assistance programs, and livestock accreditation.</p>
            </div>
            <button type="button" onclick="acceptTermsModal()" style="width:100%;background:#123f33;color:#fff;border:none;padding:12px;border-radius:12px;font-weight:700;font-size:0.95rem;cursor:pointer;">I Accept the Terms &amp; Conditions</button>
        </div>
    </div>

    <script>
        function openTermsModal() {
            const m = document.getElementById('termsModal');
            if (m) m.style.display = 'flex';
        }
        function closeTermsModal() {
            const m = document.getElementById('termsModal');
            if (m) m.style.display = 'none';
        }
        function acceptTermsModal() {
            closeTermsModal();
            const t = document.getElementById('terms');
            if (t) t.checked = true;
        }

        const passwordInput = document.getElementById('pass');
        const toggleIcon = document.getElementById('toggleIcon');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');
        const confirmPass = document.getElementById('confirmPass');
        const terms = document.getElementById('terms');
        const inlineError = document.getElementById('formInlineError');
        const form = document.getElementById('registerForm');

        if (toggleIcon && passwordInput) {
            toggleIcon.addEventListener('click', () => {
                const isHidden = passwordInput.type === 'password';
                passwordInput.type = isHidden ? 'text' : 'password';
                toggleIcon.classList.toggle('bi-eye', !isHidden);
                toggleIcon.classList.toggle('bi-eye-slash', isHidden);
            });
        }

        const requirements = {
            length: (val) => val.length >= 8,
            number: (val) => /[0-9]/.test(val),
            special: (val) => /[!@#$%^&*(),.?":{}|<>]/.test(val),
            lowercase: (val) => /[a-z]/.test(val),
            uppercase: (val) => /[A-Z]/.test(val)
        };

        function updateStrengthMeter(score, length) {
            let width = '0%';
            let color = 'transparent';
            let label = 'VERY WEAK';

            if (length > 0) {
                if (score <= 2) {
                    width = '25%';
                    color = 'var(--weak)';
                    label = 'WEAK';
                } else if (score === 3) {
                    width = '50%';
                    color = 'var(--fair)';
                    label = 'FAIR';
                } else if (score === 4) {
                    width = '75%';
                    color = 'var(--good)';
                    label = 'GOOD';
                } else {
                    width = '100%';
                    color = 'var(--strong)';
                    label = 'STRONG';
                }
            }

            strengthBar.style.width = width;
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = label;
            strengthText.style.color = length > 0 ? color : '#6b7280';
        }

        function updatePasswordStrength(value) {
            let score = 0;
            Object.keys(requirements).forEach((key) => {
                const reqItem = document.getElementById(key);
                if (!reqItem) return;

                const icon = reqItem.querySelector('i');
                const isValid = requirements[key](value);
                if (isValid) {
                    reqItem.classList.add('valid');
                    icon.classList.remove('bi-circle');
                    icon.classList.add('bi-check-circle-fill');
                    score++;
                } else {
                    reqItem.classList.remove('valid');
                    icon.classList.remove('bi-check-circle-fill');
                    icon.classList.add('bi-circle');
                }
            });

            updateStrengthMeter(score, value.length);
            return score;
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', () => {
                updatePasswordStrength(passwordInput.value);
                inlineError.textContent = '';
            });
        }

        if (confirmPass) {
            confirmPass.addEventListener('input', () => {
                inlineError.textContent = '';
            });
        }

        if (form) {
            form.addEventListener('submit', (e) => {
                inlineError.textContent = '';

                const pass = passwordInput.value;
                const cpass = confirmPass.value;
                const score = updatePasswordStrength(pass);

                if (pass !== cpass) {
                    e.preventDefault();
                    inlineError.textContent = 'Passwords do not match.';
                    confirmPass.focus();
                    return;
                }

                if (score < 3) {
                    e.preventDefault();
                    inlineError.textContent = 'Use a stronger password (at least 3 checks).';
                    passwordInput.focus();
                    return;
                }

                if (!terms.checked) {
                    e.preventDefault();
                    inlineError.textContent = 'Please agree to the Terms and Conditions.';
                    terms.focus();
                    return;
                }

                submitBtn.disabled = true;
                submitBtn.innerHTML = 'CREATING ACCOUNT... <i class="bi bi-arrow-repeat"></i>';
            });
        }
    </script>
    <script src="../assets/js/translator.js"></script>
</body>
</html>