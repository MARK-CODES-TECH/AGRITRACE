<?php
// No authentication - all panels publicly accessible
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AgriTrace+ Panels</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #064e3b;
            --bg-light: #f9fafb;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #041a14 0%, #0a473d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 600px;
        }

        .header {
            text-align: center;
            color: white;
            margin-bottom: 40px;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .header p {
            opacity: 0.9;
        }

        .panels-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .panel-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-decoration: none;
            color: #064e3b;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 20px;
            cursor: pointer;
        }

        .panel-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            background: #f0fdf4;
        }

        .panel-icon {
            font-size: 2.5rem;
            color: var(--primary);
            flex-shrink: 0;
        }

        .panel-content h2 {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }

        .panel-content p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        .panel-arrow {
            margin-left: auto;
            color: var(--primary);
            font-size: 1.5rem;
        }

        .info-banner {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #10b981;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 30px;
            text-align: center;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo"><i class="bi bi-leaf-fill"></i> AgriTrace+</div>
            <h1>Access Panels</h1>
            <p>All panels are open and accessible</p>
        </div>

        <div class="info-banner">
            <i class="bi bi-info-circle"></i> Development Mode - No Authentication Required
        </div>

        <div class="panels-grid">
            <a href="farmer.php" class="panel-card">
                <i class="bi bi-house-fill panel-icon"></i>
                <div class="panel-content">
                    <h2>Farmer Panel</h2>
                    <p>Manage farms and livestock</p>
                </div>
                <i class="bi bi-arrow-right panel-arrow"></i>
            </a>

            <a href="admin.php" class="panel-card">
                <i class="bi bi-shield-lock panel-icon"></i>
                <div class="panel-content">
                    <h2>Admin Panel</h2>
                    <p>System administration</p>
                </div>
                <i class="bi bi-arrow-right panel-arrow"></i>
            </a>

            <a href="agriDA.php" class="panel-card">
                <i class="bi bi-briefcase panel-icon"></i>
                <div class="panel-content">
                    <h2>Official Panel</h2>
                    <p>Agriculture official dashboard</p>
                </div>
                <i class="bi bi-arrow-right panel-arrow"></i>
            </a>

            <a href="index.php" class="panel-card" style="background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(16,185,129,0.1)); border: 1px solid rgba(16,185,129,0.3);">
                <i class="bi bi-house panel-icon"></i>
                <div class="panel-content">
                    <h2>Back to Home</h2>
                    <p>Return to homepage</p>
                </div>
                <i class="bi bi-arrow-right panel-arrow"></i>
            </a>
        </div>
    </div>
</body>
</html>
