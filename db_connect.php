<?php
// Smart database connection: Supports standard port 3306 and secondary 3307 automatically
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'myproject4';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$ports  = getenv('DB_PORT') ? [getenv('DB_PORT')] : ['3306', '3307'];

$pdo = null;
$lastError = null;

foreach ($ports as $port) {
    try {
        $dsn = "mysql:host={$dbHost};port={$port};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => false
        ]);
        $pdo->query("SELECT 1");
        break;
    } catch (PDOException $e) {
        $lastError = $e->getMessage();
        $pdo = null;
    }
}

if (!$pdo) {
    error_log("Database connection failed: " . $lastError);
    die("Database connection failed. Please check XAMPP MySQL service.");
}
