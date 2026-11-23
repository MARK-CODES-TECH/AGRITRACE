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
    define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: 'AIzaSyD4wib27YHw9ISdYdXP_p5k5cA0bhWkGqM');
}
if (!defined('GOOGLE_MAPS_PROJECT_ID')) {
    define('GOOGLE_MAPS_PROJECT_ID', getenv('GOOGLE_MAPS_PROJECT_ID') ?: 'dfms-19927');
}
if (!defined('GOOGLE_MAPS_ACCOUNT')) {
    define('GOOGLE_MAPS_ACCOUNT', getenv('GOOGLE_MAPS_ACCOUNT') ?: 'reaganalmonte02@gmail.com');
}

// ── FIREBASE AUTHENTICATION CONFIGURATION (Project: dfms-19927) ─────────────
if (!defined('FIREBASE_API_KEY')) {
    define('FIREBASE_API_KEY', getenv('FIREBASE_API_KEY') ?: 'AIzaSyCkJ08Mb6c9SFDdkb_M6I_X7ZNYYf0l-3o');
}
if (!defined('FIREBASE_AUTH_DOMAIN')) {
    define('FIREBASE_AUTH_DOMAIN', getenv('FIREBASE_AUTH_DOMAIN') ?: 'dfms-19927.firebaseapp.com');
}
if (!defined('FIREBASE_PROJECT_ID')) {
    define('FIREBASE_PROJECT_ID', getenv('FIREBASE_PROJECT_ID') ?: 'dfms-19927');
}
if (!defined('FIREBASE_STORAGE_BUCKET')) {
    define('FIREBASE_STORAGE_BUCKET', getenv('FIREBASE_STORAGE_BUCKET') ?: 'dfms-19927.firebasestorage.app');
}
if (!defined('FIREBASE_MESSAGING_SENDER_ID')) {
    define('FIREBASE_MESSAGING_SENDER_ID', getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '430985218377');
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
