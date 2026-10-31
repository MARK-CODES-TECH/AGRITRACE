<?php
// Smart environment loader: automatically loads .env if present
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            if (getenv($k) === false) {
                putenv("$k=$v");
                $_ENV[$k] = $v;
                $_SERVER[$k] = $v;
            }
        }
    }
}

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
