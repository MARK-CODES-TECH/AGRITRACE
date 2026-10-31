<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    require_once __DIR__ . '/../db_connect.php';
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = [];
}

$action = $_GET['action'] ?? 'saveSettings';

// Ensure config table exists before upsert.
$pdo->exec("CREATE TABLE IF NOT EXISTS config (`key` VARCHAR(191) PRIMARY KEY, `value` TEXT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

switch ($action) {
    case 'testEmail':
        echo json_encode(['success' => true, 'message' => 'SMTP test simulated']);
        exit;

    case 'testSMS':
        echo json_encode(['success' => true, 'message' => 'SMS test simulated']);
        exit;

    case 'saveSettings':
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("REPLACE INTO config (`key`, `value`) VALUES (?, ?)");
            foreach ($input as $key => $value) {
                $stmt->execute([(string)$key, is_scalar($value) ? (string)$value : json_encode($value)]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Throwable $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save settings']);
        }
        exit;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        exit;
}