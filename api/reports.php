<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../db_connect.php';

$action = $_GET['action'] ?? '';

function output(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getTotalLivestock(PDO $pdo): int {
    return (int)$pdo->query("SELECT COALESCE(SUM(qty), 0) FROM livestock WHERE healthStatus = 'Healthy'")->fetchColumn();
}

function getTotalFarms(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM farms WHERE status = 'Approved'")->fetchColumn();
}

function getRecentIncidents(PDO $pdo): int {
    return (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE createdAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
}

function getHealthRate(PDO $pdo): float {
    $healthy = (int)$pdo->query("SELECT COALESCE(SUM(qty), 0) FROM livestock WHERE healthStatus = 'Healthy'")->fetchColumn();
    $total = (int)$pdo->query("SELECT COALESCE(SUM(qty), 0) FROM livestock")->fetchColumn();
    if ($total === 0) {
        return 0.0;
    }
    return round(($healthy / $total) * 100, 2);
}

switch ($action) {
    case 'stats':
        output([
            'totalLivestock' => getTotalLivestock($pdo),
            'totalFarms' => getTotalFarms($pdo),
            'totalIncidents' => getRecentIncidents($pdo),
            'healthRate' => getHealthRate($pdo),
        ]);
        break;

    case 'farms':
        $stmt = $pdo->query("SELECT farms.*, users.firstName, users.lastName, (SELECT COALESCE(SUM(qty), 0) FROM livestock WHERE farmId = farms.id) AS total_livestock FROM farms LEFT JOIN users ON farms.ownerId = users.id WHERE farms.status = 'Approved' ORDER BY farms.createdAt DESC");
        output($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    default:
        output(['error' => 'Invalid action'], 400);
}
