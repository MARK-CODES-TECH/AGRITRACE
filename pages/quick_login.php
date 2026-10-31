<?php
session_start();
require_once __DIR__ . '/init.php';

$role = $_GET['role'] ?? 'Admin';

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE role = ? AND status = 'Active' ORDER BY id ASC LIMIT 1");
    $stmt->execute([$role]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['firstName'] = $user['firstName'];
        $_SESSION['lastName'] = $user['lastName'];
        $_SESSION['user_name'] = trim(($user['firstName'] ?? '') . ' ' . ($user['lastName'] ?? ''));
        $_SESSION['mobile'] = $user['mobile'];
        $_SESSION['logged_in'] = true;

        $target = match($user['role']) {
            'Admin' => 'admin.php',
            'Farmer' => 'farmer.php',
            'Agriculture Official' => 'agriDA.php',
            default => 'dashboard.php'
        };

        header("Location: " . $target);
        exit;
    }
} catch (Throwable $e) {
    error_log("Quick login error: " . $e->getMessage());
}

header("Location: login.php");
exit;

