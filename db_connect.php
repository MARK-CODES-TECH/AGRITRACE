<?php
// Use standard XAMPP MySQL port (3306) unless you specifically changed it
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';  // Use 127.0.0.1 instead of localhost
$dbName = getenv('DB_NAME') ?: 'myproject4';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';  // Empty password for XAMPP root
$dbPort = getenv('DB_PORT') ?: '3306';  // Standard MySQL port

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_PERSISTENT => false
    ]);
    // Test connection
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please check XAMPP MySQL service.");
}

