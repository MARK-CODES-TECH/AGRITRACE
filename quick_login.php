<?php
require_once __DIR__ . '/init.php';

$roleParam = trim($_GET['role'] ?? '');

$roleMap = [
    'admin' => 'Admin',
    'official' => 'Agriculture Official',
    'da' => 'Agriculture Official',
    'farmer' => 'Farmer'
];

$normalizedRole = $roleMap[strtolower($roleParam)] ?? $roleParam;

if (!empty($normalizedRole)) {
    try {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? AND status = 'Active' LIMIT 1");
        $stmt->execute([$normalizedRole]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['firstName'] = $user['firstName'];
            $_SESSION['lastName'] = $user['lastName'];
            $_SESSION['user_name'] = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
            $_SESSION['mobile'] = $user['mobile'];
            $_SESSION['logged_in'] = true;

            switch ($user['role']) {
                case 'Admin':
                    header('Location: pages/admin.php');
                    exit;
                case 'Agriculture Official':
                    header('Location: pages/agriDA.php');
                    exit;
                case 'Farmer':
                    header('Location: pages/farmer.php');
                    exit;
                default:
                    header('Location: pages/dashboard.php');
                    exit;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriTrace+ Quick Access Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: linear-gradient(135deg, #064e3b 0%, #0f172a 100%); color: #f8fafc; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, sans-serif; padding: 20px; }
        .card-hub { background: rgba(255, 255, 255, 0.06); backdrop-filter: blur(16px); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.4); max-width: 780px; width: 100%; padding: 36px; }
        .role-box { background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 14px; padding: 20px; transition: all 0.25s ease; text-decoration: none; color: inherit; display: block; }
        .role-box:hover { background: rgba(16, 185, 129, 0.15); border-color: #10b981; transform: translateY(-3px); color: #fff; }
        .badge-role { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; padding: 4px 10px; border-radius: 9999px; }
    </style>
</head>
<body>
    <div class="card-hub">
        <div class="text-center mb-4">
            <h2 class="fw-bold mb-1"><i class="bi bi-shield-check text-success me-2"></i>AgriTrace+ Access Portal</h2>
            <p class="text-secondary mb-0">Select any test role below for instant panel access, or use the standard login.</p>
        </div>

        <div class="row g-3 mb-4">
            <!-- Admin -->
            <div class="col-md-4">
                <a href="quick_login.php?role=Admin" class="role-box text-center h-100">
                    <div class="fs-1 text-warning mb-2"><i class="bi bi-shield-lock-fill"></i></div>
                    <span class="badge bg-warning text-dark badge-role mb-2">Administrator</span>
                    <h5 class="fw-bold mb-1">Admin Panel</h5>
                    <div class="small text-secondary mb-3">System audit, users, analytics & GIS</div>
                    <button class="btn btn-sm btn-outline-warning w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Instant Login</button>
                    <div class="mt-2 text-muted" style="font-size: 0.72rem;">admin@agritrace.ph / password</div>
                </a>
            </div>

            <!-- Agriculture Official -->
            <div class="col-md-4">
                <a href="quick_login.php?role=Agriculture%20Official" class="role-box text-center h-100">
                    <div class="fs-1 text-info mb-2"><i class="bi bi-building-check"></i></div>
                    <span class="badge bg-info text-dark badge-role mb-2">DA Official</span>
                    <h5 class="fw-bold mb-1">DA Official Panel</h5>
                    <div class="small text-secondary mb-3">Verifications, permits, disease alerts & maps</div>
                    <button class="btn btn-sm btn-outline-info w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Instant Login</button>
                    <div class="mt-2 text-muted" style="font-size: 0.72rem;">official@agritrace.gov.ph / password</div>
                </a>
            </div>

            <!-- Farmer -->
            <div class="col-md-4">
                <a href="quick_login.php?role=Farmer" class="role-box text-center h-100">
                    <div class="fs-1 text-success mb-2"><i class="bi bi-person-badge-fill"></i></div>
                    <span class="badge bg-success badge-role mb-2">Farmer</span>
                    <h5 class="fw-bold mb-1">Farmer Portal</h5>
                    <div class="small text-secondary mb-3">Farm registry, livestock, health & permits</div>
                    <button class="btn btn-sm btn-outline-success w-100"><i class="bi bi-box-arrow-in-right me-1"></i> Instant Login</button>
                    <div class="mt-2 text-muted" style="font-size: 0.72rem;">ana.ramos@agritrace.local / password</div>
                </a>
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-between align-items-center pt-3 border-top border-secondary border-opacity-25 gap-2">
            <div>
                <a href="maps/farm-map.php" class="btn btn-outline-light btn-sm"><i class="bi bi-map-fill text-success me-1"></i> Open Google Map Directly</a>
                <a href="pages/index.php" class="btn btn-outline-light btn-sm ms-1"><i class="bi bi-house me-1"></i> Landing Page</a>
            </div>
            <div>
                <a href="pages/login.php" class="btn btn-light btn-sm fw-bold"><i class="bi bi-door-open me-1"></i> Standard Login Page</a>
            </div>
        </div>
    </div>
</body>
</html>
