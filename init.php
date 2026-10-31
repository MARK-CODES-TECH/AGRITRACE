<?php
// init.php - Central Initialization File
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure working directory is project root so relative file paths resolve consistently
chdir(__DIR__);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    require_once __DIR__ . '/db_connect.php';
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Add any other global configurations or utility functions here
if (!defined('GOOGLE_MAPS_API_KEY')) {
    // Set your Google Maps API Key here or through environment variable GOOGLE_MAPS_API_KEY
    define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');
}

// ── BILINGUAL LANGUAGE SUPPORT (English / Tagalog) ──────────────────────────
if (isset($_GET['lang'])) {
    $requestedLang = strtolower(trim($_GET['lang'])) === 'tl' ? 'tl' : 'en';
    $_SESSION['lang'] = $requestedLang;
    setcookie('agritrace_lang', $requestedLang, time() + (86400 * 365), '/');
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = isset($_COOKIE['agritrace_lang']) && strtolower($_COOKIE['agritrace_lang']) === 'tl' ? 'tl' : 'en';
}

function current_lang(): string {
    return $_SESSION['lang'] ?? 'en';
}

function t(string $text): string {
    static $dict = [
        'Dashboard' => 'Dashboard',
        'Farmer Registry' => 'Talaan ng mga Magsasaka',
        'Farm Holdings' => 'Mga Sakahan at Lupa',
        'Livestock Inventory' => 'Imbentaryo ng mga Hayop',
        'Incident Alerts' => 'Mga Alerto sa Insidente',
        'User Management' => 'Pamamahala ng Gumagamit',
        'Export Reports' => 'I-export ang mga Ulat',
        'System Audit Logs' => 'Mga Talaan ng Pagsusuri ng Sistema',
        'GIS Geo-Monitoring' => 'GIS Geo-Pagmamanman',
        'Total Farmers' => 'Kabuuang Magsasaka',
        'Registered Holdings' => 'Mga Rehistradong Sakahan',
        'Livestock Census' => 'Sensus ng mga Hayop',
        'Active Incidents' => 'Mga Aktibong Insidente',
        'Logout' => 'Lumabas',
        'Sign In' => 'Mag-sign In',
        'Pending' => 'Nakabinbin',
        'Approved' => 'Aprubado',
        'Rejected' => 'Tinanggihan',
        'Resolved' => 'Nalutas',
        'Healthy' => 'Malusog',
        'Critical' => 'Kritikal'
    ];

    if (current_lang() === 'tl' && isset($dict[$text])) {
        return $dict[$text];
    }
    return $text;
}

if (!function_exists('__')) {
    function __(string $text): string {
        return t($text);
    }
}
