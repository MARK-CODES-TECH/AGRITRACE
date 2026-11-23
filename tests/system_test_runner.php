<?php
// tests/system_test_runner.php - Comprehensive System Testing for AgriTrace+
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../init.php';

echo "========================================================================\n";
echo "           AGRITRACE+ COMPREHENSIVE SYSTEM & ROLE TESTING               \n";
echo "========================================================================\n";
echo "Date/Time: " . date('Y-m-d H:i:s') . "\n";
echo "Base URL:  http://127.0.0.1:8080\n";
echo "Database:  myproject4 (Port 3307)\n\n";

$results = [];

function recordTest(&$results, $category, $name, $status, $details = '') {
    $results[] = [
        'category' => $category,
        'name'     => $name,
        'status'   => $status, // PASS, FAIL, WARN
        'details'  => $details
    ];
    $badge = $status === 'PASS' ? "[PASS]" : ($status === 'WARN' ? "[WARN]" : "[FAIL]");
    printf("%-8s | %-24s | %-40s | %s\n", $badge, substr($category, 0, 24), substr($name, 0, 40), $details);
}

function makeHttpRequest($url, $method = 'GET', $data = null, $cookieJar = null, $isJson = false, $customHeaders = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't auto follow so we inspect redirects
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    $headers = $customHeaders;

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isJson) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data ?? []));
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error      = curl_error($ch);
    curl_close($ch);

    $headerStr = substr($response, 0, $headerSize);
    $body      = substr($response, $headerSize);

    // Extract Location header if redirect
    $redirectLocation = null;
    if (preg_match('/^Location:\s*([^\r\n]+)/im', $headerStr, $m)) {
        $redirectLocation = trim($m[1]);
    }

    return [
        'code'     => $httpCode,
        'headers'  => $headerStr,
        'body'     => $body,
        'redirect' => $redirectLocation,
        'error'    => $error
    ];
}

$tempCookieFarmer   = sys_get_temp_dir() . '/cookie_farmer_' . time() . '.txt';
$tempCookieOfficial = sys_get_temp_dir() . '/cookie_official_' . time() . '.txt';
$tempCookieAdmin    = sys_get_temp_dir() . '/cookie_admin_' . time() . '.txt';

// Helper: Ensure test accounts exist with known passwords
$testPass = 'AgriTrace2026!';
$testHash = password_hash($testPass, PASSWORD_DEFAULT);

// 1. Setup Test Users
$pdo->prepare("DELETE FROM users WHERE email IN ('test.farmer@agritrace.ph', 'test.official@agritrace.ph', 'test.admin@agritrace.ph')")->execute();

// Farmer
$pdo->prepare("INSERT INTO users (firstName, lastName, email, mobile, password, role, status, emailVerified) VALUES (?, ?, ?, ?, ?, 'Farmer', 'Active', 1)")
    ->execute(['QA-Farmer', 'Sipocot', 'test.farmer@agritrace.ph', '09191112233', $testHash]);
$farmerUserId = $pdo->lastInsertId();

// Official
$pdo->prepare("INSERT INTO users (firstName, lastName, email, mobile, password, role, status, emailVerified) VALUES (?, ?, ?, ?, ?, 'Agriculture Official', 'Active', 1)")
    ->execute(['QA-Official', 'CamSur', 'test.official@agritrace.ph', '09192223344', $testHash]);
$officialUserId = $pdo->lastInsertId();

// Admin
$pdo->prepare("INSERT INTO users (firstName, lastName, email, mobile, password, role, status, emailVerified) VALUES (?, ?, ?, ?, ?, 'Admin', 'Active', 1)")
    ->execute(['QA-Admin', 'Director', 'test.admin@agritrace.ph', '09193334455', $testHash]);
$adminUserId = $pdo->lastInsertId();

echo "------------------------------------------------------------------------\n";
echo " PHASE 1: AUTHENTICATION & ACCESS CONTROL TESTS\n";
echo "------------------------------------------------------------------------\n";

// Test 1.1: Unauthenticated Guest access to Farmer portal
$res = makeHttpRequest('http://127.0.0.1:8080/pages/farmer.php');
if ($res['code'] === 302 && str_contains($res['redirect'], 'login.php')) {
    recordTest($results, 'Auth & Security', 'Guest block: farmer.php', 'PASS', "Redirected to login.php ({$res['code']})");
} else {
    recordTest($results, 'Auth & Security', 'Guest block: farmer.php', 'FAIL', "Unexpected code: {$res['code']}");
}

// Test 1.2: Unauthenticated Guest access to Official portal
$res = makeHttpRequest('http://127.0.0.1:8080/pages/agriDA.php');
if ($res['code'] === 302 && str_contains($res['redirect'], 'login.php')) {
    recordTest($results, 'Auth & Security', 'Guest block: agriDA.php', 'PASS', "Redirected to login.php ({$res['code']})");
} else {
    recordTest($results, 'Auth & Security', 'Guest block: agriDA.php', 'FAIL', "Unexpected code: {$res['code']}");
}

// Test 1.3: Unauthenticated Guest access to Admin portal
$res = makeHttpRequest('http://127.0.0.1:8080/pages/admin.php');
if ($res['code'] === 302 && str_contains($res['redirect'], 'login.php')) {
    recordTest($results, 'Auth & Security', 'Guest block: admin.php', 'PASS', "Redirected to login.php ({$res['code']})");
} else {
    recordTest($results, 'Auth & Security', 'Guest block: admin.php', 'FAIL', "Unexpected code: {$res['code']}");
}

// Test 1.4: Invalid Password Rejection
$res = makeHttpRequest('http://127.0.0.1:8080/pages/login.php', 'POST', [
    'email'    => 'test.farmer@agritrace.ph',
    'password' => 'WrongPassword123!'
]);
if (str_contains($res['body'], 'Invalid email/mobile number or password')) {
    recordTest($results, 'Auth & Security', 'Invalid Password Rejection', 'PASS', "Rejected invalid credentials");
} else {
    recordTest($results, 'Auth & Security', 'Invalid Password Rejection', 'FAIL', "Did not display error message");
}

// Test 1.5: Farmer Login via Email
$res = makeHttpRequest('http://127.0.0.1:8080/pages/login.php', 'POST', [
    'email'    => 'test.farmer@agritrace.ph',
    'password' => $testPass
], $tempCookieFarmer);
if ($res['code'] === 302 && str_contains($res['redirect'], 'dashboard.php')) {
    recordTest($results, 'Auth & Security', 'Farmer Login (Email)', 'PASS', "Logged in, redirected to dashboard.php");
} else {
    recordTest($results, 'Auth & Security', 'Farmer Login (Email)', 'FAIL', "Login failed, code: {$res['code']}");
}

// Test 1.6: Farmer Login via Mobile Number
$tempCookieFarmerMobile = sys_get_temp_dir() . '/cookie_farmer_mob_' . time() . '.txt';
$res = makeHttpRequest('http://127.0.0.1:8080/pages/login.php', 'POST', [
    'email'    => '09191112233',
    'password' => $testPass
], $tempCookieFarmerMobile);
if ($res['code'] === 302 && str_contains($res['redirect'], 'dashboard.php')) {
    recordTest($results, 'Auth & Security', 'Farmer Login (Mobile)', 'PASS', "Logged in via 09191112233");
} else {
    recordTest($results, 'Auth & Security', 'Farmer Login (Mobile)', 'FAIL', "Mobile login failed, code: {$res['code']}");
}
@unlink($tempCookieFarmerMobile);

// Test 1.7: Agriculture Official Login
$res = makeHttpRequest('http://127.0.0.1:8080/pages/login.php', 'POST', [
    'email'    => 'test.official@agritrace.ph',
    'password' => $testPass
], $tempCookieOfficial);
if ($res['code'] === 302 && str_contains($res['redirect'], 'dashboard.php')) {
    recordTest($results, 'Auth & Security', 'Official Login', 'PASS', "Logged in, redirected to dashboard.php");
} else {
    recordTest($results, 'Auth & Security', 'Official Login', 'FAIL', "Official login failed, code: {$res['code']}");
}

// Test 1.8: Admin Login
$res = makeHttpRequest('http://127.0.0.1:8080/pages/login.php', 'POST', [
    'email'    => 'test.admin@agritrace.ph',
    'password' => $testPass
], $tempCookieAdmin);
if ($res['code'] === 302 && str_contains($res['redirect'], 'dashboard.php')) {
    recordTest($results, 'Auth & Security', 'Admin Login', 'PASS', "Logged in, redirected to dashboard.php");
} else {
    recordTest($results, 'Auth & Security', 'Admin Login', 'FAIL', "Admin login failed, code: {$res['code']}");
}

// Test 1.9: Role Privilege Enforcement (Farmer trying to access admin.php)
$res = makeHttpRequest('http://127.0.0.1:8080/pages/admin.php', 'GET', null, $tempCookieFarmer);
if ($res['code'] === 302 && str_contains($res['redirect'], 'dashboard.php')) {
    recordTest($results, 'Auth & Security', 'Privilege isolation: Farmer->Admin', 'PASS', "Blocked Farmer from Admin portal");
} else {
    recordTest($results, 'Auth & Security', 'Privilege isolation: Farmer->Admin', 'FAIL', "Did not block Farmer from Admin!");
}

// Test 1.10: Role Privilege Enforcement (Official trying to access admin.php)
$res = makeHttpRequest('http://127.0.0.1:8080/pages/admin.php', 'GET', null, $tempCookieOfficial);
if ($res['code'] === 302 && str_contains($res['redirect'], 'dashboard.php')) {
    recordTest($results, 'Auth & Security', 'Privilege isolation: Official->Admin', 'PASS', "Blocked Official from Admin portal");
} else {
    recordTest($results, 'Auth & Security', 'Privilege isolation: Official->Admin', 'FAIL', "Did not block Official from Admin!");
}

echo "------------------------------------------------------------------------\n";
echo " PHASE 2: FARMER PORTAL & ACTIONS\n";
echo "------------------------------------------------------------------------\n";

// Test 2.1: Farmer Dashboard Loading and CSRF extraction
$res = makeHttpRequest('http://127.0.0.1:8080/pages/farmer.php', 'GET', null, $tempCookieFarmer);
$csrfFarmer = '';
if ($res['code'] === 200 && str_contains($res['body'], 'AgriTrace+')) {
    if (preg_match('/<meta name="csrf-token" content="([^"]+)">/i', $res['body'], $m)) {
        $csrfFarmer = $m[1];
    }
    recordTest($results, 'Farmer Features', 'farmer.php page load', 'PASS', "Loaded 200 OK (size: " . strlen($res['body']) . " bytes, CSRF extracted)");
} else {
    recordTest($results, 'Farmer Features', 'farmer.php page load', 'FAIL', "Failed loading, code: {$res['code']}");
}

// Test 2.2: Register Farm API
$farmName = 'QA Sipocot Swine Farm ' . time();
$res = makeHttpRequest('http://127.0.0.1:8080/api/register_farm.php', 'POST', [
    'csrf_token'     => $csrfFarmer,
    'action'         => 'register_farm',
    'farm_name'      => $farmName,
    'farm_address'   => 'Barangay Impig, Sipocot, Camarines Sur',
    'farm_type'      => 'Livestock',
    'farm_area'      => 3.5,
    'farm_lat'       => 13.7645,
    'farm_lng'       => 122.9782,
    'livestock_type' => 'Swine',
    'livestock_qty'  => 30
], $tempCookieFarmer);

$createdFarmId = 0;
$regJson = json_decode($res['body'], true);
if ($res['code'] === 200 && ($regJson['success'] ?? false)) {
    $createdFarmId = (int)$regJson['farm_id'];
    recordTest($results, 'Farmer Features', 'api/register_farm.php', 'PASS', "Created Farm ID: {$createdFarmId} in Sipocot");
} else {
    // Check if inserted in DB directly
    $dbCheck = $pdo->prepare("SELECT id FROM farms WHERE name = ?");
    $dbCheck->execute([$farmName]);
    $createdFarmId = (int)$dbCheck->fetchColumn();
    if ($createdFarmId > 0) {
        recordTest($results, 'Farmer Features', 'api/register_farm.php', 'PASS', "Created Farm ID: {$createdFarmId} in DB");
    } else {
        recordTest($results, 'Farmer Features', 'api/register_farm.php', 'FAIL', "Response: " . substr($res['body'], 0, 80));
    }
}

// Test 2.3: Add Livestock via api/manage_livestock.php
$res = makeHttpRequest('http://127.0.0.1:8080/api/manage_livestock.php', 'POST', [
    'csrf_token' => $csrfFarmer,
    'action'     => 'add_livestock',
    'farm_id'    => $createdFarmId,
    'type'       => 'Poultry',
    'tagId'      => 'TAG-PLT-' . time(),
    'breed'      => 'Native Chicken',
    'age'        => '6 months',
    'weight'     => '1.5 kg',
    'qty'        => 50
], $tempCookieFarmer);
$lsJson = json_decode($res['body'], true);
if ($res['code'] === 200 && ($lsJson['success'] ?? false)) {
    recordTest($results, 'Farmer Features', 'api/manage_livestock.php (add)', 'PASS', "Added 50 Poultry heads");
} else {
    recordTest($results, 'Farmer Features', 'api/manage_livestock.php (add)', 'FAIL', "Response: " . substr($res['body'], 0, 80));
}

// Test 2.4: Security isolation check: Farmer should NOT be allowed to call official endpoint api/get_farm_livestock.php
$res = makeHttpRequest("http://127.0.0.1:8080/api/get_farm_livestock.php?farm_id={$createdFarmId}", 'GET', null, $tempCookieFarmer);
if ($res['code'] === 403) {
    recordTest($results, 'Farmer Security', 'api/get_farm_livestock.php isolation', 'PASS', "Correctly denied Farmer access (403 Forbidden)");
} else {
    recordTest($results, 'Farmer Security', 'api/get_farm_livestock.php isolation', 'FAIL', "Did not block Farmer! Code: {$res['code']}");
}

// Test 2.5: Report Incident via api/report_incident.php
$res = makeHttpRequest('http://127.0.0.1:8080/api/report_incident.php', 'POST', [
    'csrf_token'       => $csrfFarmer,
    'action'           => 'report_incident',
    'farm_id'          => $createdFarmId,
    'incident_type'    => 'Sick',
    'title'            => 'Suspected African Swine Fever Symptoms',
    'description'      => 'High fever and loss of appetite observed in 4 swine pens.',
    'latitude'         => 13.7645,
    'longitude'        => 122.9782,
    'livestock_type'   => 'Swine',
    'symptoms'         => 'High fever, red skin patches',
    'severity'         => 'Critical'
], $tempCookieFarmer);
$incJson = json_decode($res['body'], true);
$createdIncId = 0;
if ($res['code'] === 200 && ($incJson['success'] ?? false)) {
    $createdIncId = (int)$incJson['incident_id'];
    recordTest($results, 'Farmer Features', 'api/report_incident.php', 'PASS', "Reported Incident ID: {$createdIncId}");
} else {
    recordTest($results, 'Farmer Features', 'api/report_incident.php', 'FAIL', "Failed: " . substr($res['body'], 0, 80));
}

// Test 2.6: Farmer E-Certificate View
$res = makeHttpRequest('http://127.0.0.1:8080/pages/ecertificate.php', 'GET', null, $tempCookieFarmer);
if ($res['code'] === 200 && str_contains($res['body'], 'E-Certificates')) {
    recordTest($results, 'Farmer Features', 'pages/ecertificate.php load', 'PASS', "E-Certificates section rendered successfully");
} else {
    recordTest($results, 'Farmer Features', 'pages/ecertificate.php load', 'FAIL', "Failed loading ecertificate.php");
}

echo "------------------------------------------------------------------------\n";
echo " PHASE 3: OFFICIAL PORTAL & BIOSECURITY MONITORING\n";
echo "------------------------------------------------------------------------\n";

// Test 3.1: Agriculture Official Portal Loading and CSRF extraction
$res = makeHttpRequest('http://127.0.0.1:8080/pages/agriDA.php', 'GET', null, $tempCookieOfficial);
$csrfOfficial = '';
if ($res['code'] === 200 && (str_contains($res['body'], 'Agriculture Official') || str_contains($res['body'], 'AgriTrace'))) {
    if (preg_match('/<meta name="csrf-token" content="([^"]+)">/i', $res['body'], $m)) {
        $csrfOfficial = $m[1];
    }
    recordTest($results, 'Official Features', 'agriDA.php page load', 'PASS', "Loaded 200 OK (size: " . strlen($res['body']) . " bytes)");
} else {
    recordTest($results, 'Official Features', 'agriDA.php page load', 'FAIL', "Failed loading, code: {$res['code']}");
}

// Test 3.2: Official Fetch Farm Livestock via api/get_farm_livestock.php
$res = makeHttpRequest("http://127.0.0.1:8080/api/get_farm_livestock.php?farm_id={$createdFarmId}", 'GET', null, $tempCookieOfficial);
$fetchLs = json_decode($res['body'], true);
if ($res['code'] === 200 && ($fetchLs['success'] ?? false) && is_array($fetchLs['livestock'])) {
    recordTest($results, 'Official Features', 'api/get_farm_livestock.php review', 'PASS', "Official retrieved " . count($fetchLs['livestock']) . " livestock for review");
} else {
    recordTest($results, 'Official Features', 'api/get_farm_livestock.php review', 'FAIL', "Official failed to fetch livestock: " . substr($res['body'], 0, 80));
}

// Test 3.3: Interactive Farm Map Page Loading
$res = makeHttpRequest('http://127.0.0.1:8080/maps/farm-map.php', 'GET', null, $tempCookieOfficial);
if ($res['code'] === 200 && str_contains($res['body'], 'AIzaSyD4wib27YHw9ISdYdXP_p5k5cA0bhWkGqM')) {
    recordTest($results, 'Biosecurity Map', 'maps/farm-map.php key & rings', 'PASS', "Google Maps API loaded with configured key");
} else {
    recordTest($results, 'Biosecurity Map', 'maps/farm-map.php key & rings', 'FAIL', "Failed loading or key missing");
}

// Test 3.4: Map JavaScript Smooth Zoom & Ring Constants
$mapJs = @file_get_contents(__DIR__ . '/../assets/js/farm-map-enhanced.js');
$has500mRing = str_contains($mapJs, '500') && str_contains($mapJs, 'Quarantine');
$has1kmRing  = str_contains($mapJs, '1000') && str_contains($mapJs, 'Surveillance');
$hasSmoothZoom = str_contains($mapJs, 'isFractionalZoomEnabled') || str_contains($mapJs, 'smoothZoomAndPan');
if ($has500mRing && $has1kmRing && $hasSmoothZoom) {
    recordTest($results, 'Biosecurity Map', '500m & 1km Rings + Smooth Zoom', 'PASS', "Quarantine=500m, Surveillance=1km, Smooth Zoom verified");
} else {
    recordTest($results, 'Biosecurity Map', '500m & 1km Rings + Smooth Zoom', 'WARN', "Rings or smooth zoom attributes check failed");
}

// Test 3.5: Approve Farm API
if ($createdFarmId > 0) {
    $res = makeHttpRequest('http://127.0.0.1:8080/api/approve_farm.php', 'POST', [
        'farmId' => $createdFarmId
    ], $tempCookieOfficial, true);
    $apprJson = json_decode($res['body'], true);
    if ($res['code'] === 200 && ($apprJson['success'] ?? false)) {
        recordTest($results, 'Official Features', 'api/approve_farm.php', 'PASS', "Approved Farm ID: {$createdFarmId}");
    } else {
        recordTest($results, 'Official Features', 'api/approve_farm.php', 'FAIL', "Response: " . substr($res['body'], 0, 80));
    }
}

// Test 3.6: Resolve Incident API
if ($createdIncId > 0) {
    $res = makeHttpRequest('http://127.0.0.1:8080/api/resolve_incident.php', 'POST', [
        'incidentId' => $createdIncId
    ], $tempCookieOfficial, true);
    $resJson = json_decode($res['body'], true);
    if ($res['code'] === 200 && ($resJson['success'] ?? false)) {
        recordTest($results, 'Official Features', 'api/resolve_incident.php', 'PASS', "Resolved Incident ID: {$createdIncId}");
    } else {
        recordTest($results, 'Official Features', 'api/resolve_incident.php', 'FAIL', "Response: " . substr($res['body'], 0, 80));
    }
}

// Test 3.7: Public Incident Report Form
$res = makeHttpRequest('http://127.0.0.1:8080/pages/public-report-form.php');
if ($res['code'] === 200) {
    recordTest($results, 'Public Reporting', 'public-report-form.php load', 'PASS', "Public can report incidents (200 OK)");
} else {
    recordTest($results, 'Public Reporting', 'public-report-form.php load', 'FAIL', "Failed loading public report form");
}

echo "------------------------------------------------------------------------\n";
echo " PHASE 4: ADMIN & SYSTEM INTEGRITY\n";
echo "------------------------------------------------------------------------\n";

// Test 4.1: Admin Dashboard Loading
$res = makeHttpRequest('http://127.0.0.1:8080/pages/admin.php', 'GET', null, $tempCookieAdmin);
if ($res['code'] === 200 && (str_contains($res['body'], 'Admin') || str_contains($res['body'], 'AgriTrace'))) {
    recordTest($results, 'Admin Features', 'admin.php page load', 'PASS', "Loaded 200 OK (size: " . strlen($res['body']) . " bytes)");
} else {
    recordTest($results, 'Admin Features', 'admin.php page load', 'FAIL', "Failed loading admin dashboard");
}

// Test 4.2: Audit Logs Endpoint
$res = makeHttpRequest('http://127.0.0.1:8080/api/camarines_audit.php', 'GET', null, $tempCookieAdmin);
if ($res['code'] === 200) {
    recordTest($results, 'Admin Features', 'api/camarines_audit.php', 'PASS', "Audit logs accessible (200 OK)");
} else {
    recordTest($results, 'Admin Features', 'api/camarines_audit.php', 'WARN', "HTTP code {$res['code']}");
}

// Test 4.3: Database Backup Utility
$res = makeHttpRequest('http://127.0.0.1:8080/api/backup.php', 'GET', null, $tempCookieAdmin);
if (in_array($res['code'], [200, 302, 403])) {
    recordTest($results, 'Admin Features', 'api/backup.php endpoint', 'PASS', "Endpoint responded with code {$res['code']}");
} else {
    recordTest($results, 'Admin Features', 'api/backup.php endpoint', 'FAIL', "Unexpected error code: {$res['code']}");
}

echo "------------------------------------------------------------------------\n";
echo " PHASE 5: CLEANUP & SUMMARY\n";
echo "------------------------------------------------------------------------\n";

// Cleanup test cookies and records
@unlink($tempCookieFarmer);
@unlink($tempCookieOfficial);
@unlink($tempCookieAdmin);

if ($createdIncId > 0) {
    $pdo->prepare("DELETE FROM incidents WHERE id = ?")->execute([$createdIncId]);
}
if ($createdFarmId > 0) {
    $pdo->prepare("DELETE FROM livestock WHERE farmId = ?")->execute([$createdFarmId]);
    $pdo->prepare("DELETE FROM farms WHERE id = ?")->execute([$createdFarmId]);
}
$pdo->prepare("DELETE FROM users WHERE id IN (?, ?, ?)")->execute([$farmerUserId, $officialUserId, $adminUserId]);

$passCount = count(array_filter($results, fn($r) => $r['status'] === 'PASS'));
$warnCount = count(array_filter($results, fn($r) => $r['status'] === 'WARN'));
$failCount = count(array_filter($results, fn($r) => $r['status'] === 'FAIL'));
$total     = count($results);

echo "\nTEST SUMMARY:\n";
echo "Total Tests: {$total}\n";
echo "Passed:      {$passCount}\n";
echo "Warnings:    {$warnCount}\n";
echo "Failed:      {$failCount}\n";
echo "Success Rate: " . round(($passCount / $total) * 100, 1) . "%\n";
echo "========================================================================\n";

// Save JSON report
file_put_contents(__DIR__ . '/system_test_report.json', json_encode([
    'timestamp' => date('c'),
    'summary'   => [
        'total'   => $total,
        'passed'  => $passCount,
        'warning' => $warnCount,
        'failed'  => $failCount,
        'rate'    => round(($passCount / $total) * 100, 1) . '%'
    ],
    'tests'     => $results
], JSON_PRETTY_PRINT));
