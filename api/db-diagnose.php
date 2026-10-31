<?php
header('Content-Type: application/json');

// Database configuration
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'myproject4';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbPort = getenv('DB_PORT') ?: '3306';

// Step 1: Connect to MySQL (without specific database)
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die(json_encode([
        'status' => 'error',
        'message' => 'MySQL Connection Failed: ' . $e->getMessage(),
        'fix' => 'Ensure MySQL is running in XAMPP'
    ]));
}

$response = [
    'status' => 'success',
    'checks' => [],
    'actions_needed' => []
];

// Check 1: Database exists
try {
    $result = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbName'")->fetch();
    if ($result) {
        $response['checks'][] = ['name' => 'Database Exists', 'status' => 'OK', 'message' => "Database '$dbName' found"];
    } else {
        $response['checks'][] = ['name' => 'Database Exists', 'status' => 'FAIL', 'message' => "Database '$dbName' not found"];
        $response['actions_needed'][] = 'create_database';
    }
} catch (Exception $e) {
    $response['checks'][] = ['name' => 'Database Exists', 'status' => 'ERROR', 'message' => $e->getMessage()];
}

// Create database if needed
if (in_array('create_database', $response['actions_needed'])) {
    try {
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $response['checks'][] = ['name' => 'Database Created', 'status' => 'OK', 'message' => "Database created successfully"];
        $index = array_search('create_database', $response['actions_needed'], true);
        if ($index !== false) {
            array_splice($response['actions_needed'], $index, 1);
        }
    } catch (Exception $e) {
        $response['checks'][] = ['name' => 'Database Created', 'status' => 'FAIL', 'message' => $e->getMessage()];
    }
}

// Step 2: Connect to specific database
try {
    $pdo = new PDO("mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $response['checks'][] = ['name' => 'Database Connection', 'status' => 'OK', 'message' => 'Connected to myproject4'];
} catch (PDOException $e) {
    $response['checks'][] = ['name' => 'Database Connection', 'status' => 'FAIL', 'message' => $e->getMessage()];
    die(json_encode($response));
}

// Check 2: Tables exist
$required_tables = ['users', 'farms', 'livestock', 'incidents', 'public_reports', 'officer_profiles'];
$missing_tables = [];

foreach ($required_tables as $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if ($result) {
            $response['checks'][] = ['name' => "Table: $table", 'status' => 'OK'];
        } else {
            $response['checks'][] = ['name' => "Table: $table", 'status' => 'MISSING'];
            $missing_tables[] = $table;
        }
    } catch (Exception $e) {
        $response['checks'][] = ['name' => "Table: $table", 'status' => 'ERROR', 'message' => $e->getMessage()];
    }
}

if (!empty($missing_tables)) {
    $response['actions_needed'][] = 'import_schema';
}

// Check 3: Sample data
try {
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $farmCount = $pdo->query("SELECT COUNT(*) FROM farms")->fetchColumn();
    $response['checks'][] = ['name' => 'Users Count', 'status' => 'OK', 'value' => $userCount];
    $response['checks'][] = ['name' => 'Farms Count', 'status' => 'OK', 'value' => $farmCount];
} catch (Exception $e) {
    $response['checks'][] = ['name' => 'Data Check', 'status' => 'ERROR', 'message' => $e->getMessage()];
}

echo json_encode($response, JSON_PRETTY_PRINT);
