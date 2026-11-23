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
} elseif (isset($_COOKIE['agritrace_lang'])) {
    $_SESSION['lang'] = strtolower($_COOKIE['agritrace_lang']) === 'tl' ? 'tl' : 'en';
} elseif (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en';
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
        'Critical' => 'Kritikal',
        'Home' => 'Tahanan',
        'About' => 'Tungkol sa Amin',
        'Contact' => 'Makipag-ugnayan',
        'Login' => 'Mag-login',
        'Farmer Portal' => 'Portal ng Magsasaka',
        'Official Portal' => 'Opisyal na Portal',
        'Welcome to' => 'Maligayang Pagdating sa',
        'Digital Livestock Registration & Reporting System' => 'Digital na Sistema sa Rehistrasyon at Pag-uulat ng mga Hayop',
        'Get Started' => 'Magsimula Na',
        'Public Report' => 'Pampublikong Ulat',
        'Geo-Tagging' => 'Pagtukoy ng Lokasyon',
        'Secure Data' => 'Ligtas na Impormasyon',
        'Real-time Alerts' => 'Mabilisang mga Alerto',
        'Public Access' => 'Pampublikong Pag-access',
        'Submit anonymous reports for livestock health and safety' => 'Magpasa ng ulat para sa kalusugan at kaligtasan ng mga hayop',
        'Report Type' => 'Uri ng Ulat',
        'Sick livestock' => 'May sakit na mga hayop',
        'Dead animals' => 'Mga patay na hayop',
        'Stray livestock' => 'Mga ligaw na hayop',
        'Others' => 'Iba pa',
        'Suspected disease outbreak' => 'Pinaghihinalaang paglaganap ng sakit',
        'Please specify…' => 'Mangyaring tukuyin…',
        'Please specify...' => 'Mangyaring tukuyin...',
        'Photos / Videos' => 'Mga Larawan / Video',
        'Incident evidence • max 10 MB' => 'Ebidensya ng insidente • hanggang 10 MB',
        'No file chosen' => 'Walang napiling file',
        'Description' => 'Paglalarawan',
        'Describe the issue, approximate location, number of animals affected…' => 'Ilarawan ang isyu, tinatayang lokasyon, bilang ng apektadong hayop…',
        'Location (GPS)' => 'Lokasyon (GPS)',
        'Location not captured yet' => 'Hindi pa nakuha ang lokasyon',
        'Detect' => 'Tukuyin',
        'Detecting…' => 'Tinutukoy…',
        'Contact Phone' => 'Telepono ng Kontak',
        'Contact Email' => 'Email ng Kontak',
        'ID Photo' => 'Larawan ng ID',
        'Face Photo' => 'Larawan ng Mukha',
        '(optional)' => '(opsyonal)',
        'I confirm this report is accurate and genuine. I have read and agree to the' => 'Pinatutunayan ko na ang ulat na ito ay totoo at tumpak. Nabasa at sumasang-ayon ako sa',
        'Terms & Conditions' => 'Mga Tuntunin at Kundisyon',
        'Terms &amp; Conditions' => 'Mga Tuntunin at Kundisyon',
        'and understand the applicable laws on filing reports.' => 'at nauunawaan ang mga naaangkop na batas sa paghahain ng ulat.',
        'SUBMIT REPORT' => 'IPASA ANG ULAT',
        'Submit Report' => 'Ipasa ang Ulat',
        'Log in for full access' => 'Mag-log in para sa buong access',
        'Need help?' => 'Kailangan ng tulong?',
        'Report submitted successfully!' => 'Matagumpay na naisumite ang ulat!',
        'Your report has been logged and will be reviewed by an agriculture officer.' => 'Naitala na ang iyong ulat at susuriin ng isang opisyal ng agrikultura.',
        'Reference ID:' => 'ID ng Sanggunian:',
        'Submit Another Report' => 'Magpasa ng Isa Pang Ulat',
        'Choose Source' => 'Pumili ng Pagmumulan',
        'Open Camera' => 'Buksan ang Kamera',
        '+ auto-pin location' => '+ kusa itatak ang lokasyon',
        'Choose from Files' => 'Pumili mula sa mga File',
        'Photo / video' => 'Larawan / video',
        'Cancel' => 'Kanselahin',
        'I Understand & Accept' => 'Nauunawaan Ko at Tinatanggap',
        'Upload Required:' => 'Kailangan ang Upload:',
        'Valid ID Photo is required before submitting the report.' => 'Kailangan ang wastong Larawan ng ID bago maipasa ang ulat.',
        'Face Photo is required before submitting the report.' => 'Kailangan ang Larawan ng Mukha bago maipasa ang ulat.',
        'Both a valid ID Photo and Face Photo are required before submitting this report.' => 'Parehong kailangan ang wastong Larawan ng ID at Larawan ng Mukha bago maipasa ang ulat na ito.',
        'Upload ID Photo' => 'Mag-upload ng Larawan ng ID',
        'Upload Face Photo' => 'Mag-upload ng Larawan ng Mukha',
        'Add Photo / Video' => 'Magdagdag ng Larawan / Video',
        'Terms & Conditions for Report Submission' => 'Mga Tuntunin at Kundisyon sa Pagpasa ng Ulat',
        'Purpose of This Form' => 'Layunin ng Form na Ito',
        'Accuracy of Reports' => 'Katumpakan ng mga Ulat',
        'Applicable Laws — Filing False Reports' => 'Mga Naaangkop na Batas — Pagpasa ng Maling Ulat',
        'Privacy & Reporter Protection' => 'Proteksyon sa Pagkapribado at Tagapag-ulat',
        'Photo & Media Requirements' => 'Mga Kinakailangan sa Larawan at Media',
        'Location Data' => 'Datos ng Lokasyon',
        'By Submitting You Agree That' => 'Sa Pagpasa Ikaw ay Sumasang-ayon Na',
        'Please select a report type.' => 'Mangyaring pumili ng uri ng ulat.',
        'Please specify the report type.' => 'Mangyaring tukuyin ang uri ng ulat.',
        'Description is required.' => 'Kinakailangan ang paglalarawan.',
        'Contact phone is required.' => 'Kinakailangan ang telepono ng kontak.',
        'Invalid phone number.' => 'Hindi wastong numero ng telepono.',
        'Invalid email address.' => 'Hindi wastong email address.',
        'You must accept the terms and conditions.' => 'Dapat mong tanggapin ang mga tuntunin at kundisyon.',
        'Please fix:' => 'Mangyaring itama:',
        'Public Incident Report' => 'Pampublikong Ulat ng Insidente',

        // --- Login & Authentication ---
        'SIGN IN' => 'MAG-SIGN IN',
        'Signing in...' => 'Pumapasok...',
        'SIGNING IN...' => 'PUMAPASOK...',
        'Welcome to AgriTrace+: A Digital Livestock Registration and Monitoring System' => 'Maligayang Pagdating sa AgriTrace+: Digital na Sistema sa Rehistrasyon at Pagmamanman ng mga Hayop',
        'Welcome To AgriTrace+: A Digital Livestock Registration and Monitoring System' => 'Maligayang Pagdating sa AgriTrace+: Digital na Sistema sa Rehistrasyon at Pagmamanman ng mga Hayop',
        'Welcome To Agritace+: A Digital Livestock Registration and Monitoring Sytem' => 'Maligayang Pagdating sa AgriTrace+: Digital na Sistema sa Rehistrasyon at Pagmamanman ng mga Hayop',
        'Sign in to monitor livestock, review incidents, and keep your agriculture data up to date.' => 'Mag-sign in upang masubaybayan ang mga hayop, suriin ang mga insidente, at panatilihing napapanahon ang datos ng agrikultura.',
        'Unified dashboard for daily operations' => 'Pinag-isang dashboard para sa pang-araw-araw na operasyon',
        'Secure access by role and account status' => 'Ligtas na access ayon sa tungkulin at katayuan ng account',
        'Geo-aware records and incident workflows' => 'Mga talaang may heograpikong lokasyon at proseso ng insidente',
        'AGRITRACE ACCESS' => 'ACCESS SA AGRITRACE',
        'Access your AgriTrace+ workspace.' => 'I-access ang iyong AgriTrace+ workspace.',
        'Email Address' => 'Email Address',
        'Password' => 'Password',
        'Enter your password' => 'Ilagay ang iyong password',
        'Please enter both email and password.' => 'Mangyaring ilagay ang parehong email at password.',
        'Create Account' => 'Gumawa ng Account',
        'Create an Account' => 'Gumawa ng Account',
        'Forgot Password?' => 'Nakalimutan ang Password?',
        'Back to Home' => 'Bumalik sa Tahanan',
        'Back to Login' => 'Bumalik sa Login',
        'Invalid email or password' => 'Maling email o password',
        'Invalid email/mobile number or password' => 'Maling email/mobile number o password',
        'Email or Mobile Number' => 'Email o Mobile Number',
        'Mobile Number' => 'Numero ng Mobile',
        'juan@example.com or 09123456789' => 'juan@example.com o 09123456789',
        'Please enter your email or mobile number and password.' => 'Mangyaring ilagay ang iyong email o mobile number at password.',
        'You have been logged out successfully.' => 'Matagumpay kang naka-logout.',
        'Database connection issue. Please try again later.' => 'May problema sa koneksyon ng database. Pakisubukang muli mamaya.',
        'Database connection failed' => 'Nabigo ang koneksyon sa database',
        'Database query failed' => 'Nabigo ang query sa database'
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
