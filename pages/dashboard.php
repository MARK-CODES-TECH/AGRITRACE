<?php
require_once __DIR__ . '/init.php';

// If not logged in, show login options
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

$user_name = trim(($_SESSION['firstName'] ?? '') . ' ' . ($_SESSION['lastName'] ?? ''));
$user_role = $_SESSION['user_role'] ?? 'User';
$user_id = $_SESSION['user_id'] ?? 0;

// Role-based redirect
$dashboard_link = match($user_role) {
    'Admin' => 'admin.php',
    'Farmer' => 'farmer.php',
    'Agriculture Official' => 'agriDA.php',
    default => 'index.php'
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | AgriTrace+</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #064e3b;
            --bg-light: #f9fafb;
            --text-dark: #064e3b;
            --text-grey: #6b7280;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg-light);
            color: var(--text-dark);
            min-height: 100vh;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .header-user {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.85rem;
            color: var(--text-grey);
        }

        .avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .welcome-section p {
            color: var(--text-grey);
            margin-bottom: 20px;
        }

        .redirect-btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 14px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
            border: none;
            cursor: pointer;
        }

        .redirect-btn:hover {
            background: var(--primary-dark);
            text-decoration: none;
        }

        .quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .link-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-dark);
            border: 1px solid #e5e7eb;
            transition: 0.3s;
        }

        .link-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.1);
        }

        .link-card i {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
            display: block;
        }

        .link-card-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .link-card-desc {
            font-size: 0.85rem;
            color: var(--text-grey);
        }

        .logout-btn {
            background: #ef4444;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
            border: none;
            cursor: pointer;
            transition: 0.3s;
        }

        .logout-btn:hover {
            background: #dc2626;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }

            .welcome-section h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-logo">
            <i class="bi bi-leaf-fill"></i> AgriTrace+
        </div>
        <div class="header-user">
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
            </div>
            <div class="avatar"><?php echo htmlspecialchars(strtoupper(substr($user_name ? $user_name[0] : 'U', 0, 1))); ?></div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars(explode(' ', $user_name)[0] ?? 'User'); ?>! 👋</h1>
            <p>You are logged in as a <?php echo htmlspecialchars($user_role); ?>. Access your panel below.</p>
            
            <a href="<?php echo htmlspecialchars($dashboard_link); ?>" class="redirect-btn">
                <i class="bi bi-arrow-right"></i> Go to <?php echo htmlspecialchars($user_role); ?> Panel
            </a>

            <div class="quick-links">
                <?php if ($user_role === 'Farmer'): ?>
                    <a href="farmer.php" class="link-card">
                        <i class="bi bi-house-fill"></i>
                        <div class="link-card-title">My Farm</div>
                        <div class="link-card-desc">Manage your farm and livestock</div>
                    </a>
                    <a href="farmer.php?tab=reports" class="link-card">
                        <i class="bi bi-file-earmark-text"></i>
                        <div class="link-card-title">Reports</div>
                        <div class="link-card-desc">View incident reports</div>
                    </a>
                    <a href="farmer.php?tab=livestock" class="link-card">
                        <i class="bi bi-collection"></i>
                        <div class="link-card-title">Livestock</div>
                        <div class="link-card-desc">Manage livestock records</div>
                    </a>
                <?php elseif ($user_role === 'Admin'): ?>
                    <a href="admin.php" class="link-card">
                        <i class="bi bi-speedometer2"></i>
                        <div class="link-card-title">Dashboard</div>
                        <div class="link-card-desc">View overview and statistics</div>
                    </a>
                    <a href="admin.php?tab=farms" class="link-card">
                        <i class="bi bi-map"></i>
                        <div class="link-card-title">Farms</div>
                        <div class="link-card-desc">Approve and manage farms</div>
                    </a>
                    <a href="admin.php?tab=users" class="link-card">
                        <i class="bi bi-people"></i>
                        <div class="link-card-title">Users</div>
                        <div class="link-card-desc">Manage users</div>
                    </a>
                    <a href="admin.php?tab=reports" class="link-card">
                        <i class="bi bi-graph-up"></i>
                        <div class="link-card-title">Reports</div>
                        <div class="link-card-desc">System reports</div>
                    </a>
                <?php elseif ($user_role === 'Agriculture Official'): ?>
                    <a href="agriDA.php" class="link-card">
                        <i class="bi bi-briefcase"></i>
                        <div class="link-card-title">My Dashboard</div>
                        <div class="link-card-desc">View your activities</div>
                    </a>
                    <a href="agriDA.php?tab=incidents" class="link-card">
                        <i class="bi bi-exclamation-triangle"></i>
                        <div class="link-card-title">Incidents</div>
                        <div class="link-card-desc">Manage incidents</div>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
