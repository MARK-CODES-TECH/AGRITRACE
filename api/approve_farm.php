<?php
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connect.php';

function respond(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$farmId = (int)($input['farmId'] ?? 0);
if ($farmId <= 0) {
    respond(['success' => false, 'message' => 'Invalid farm ID'], 400);
}

$userId = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("SELECT f.id, f.name, f.status, f.ownerId, u.firstName, u.lastName FROM farms f LEFT JOIN users u ON f.ownerId = u.id WHERE f.id = ?");
$stmt->execute([$farmId]);
$farm = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$farm) {
    respond(['success' => false, 'message' => 'Farm not found'], 404);
}

if ($farm['status'] !== 'Pending') {
    respond(['success' => false, 'message' => 'Farm is not pending approval'], 400);
}

$update = $pdo->prepare("UPDATE farms SET status = 'Approved', approved_by = ?, approved_at = CURRENT_TIMESTAMP, updatedAt = CURRENT_TIMESTAMP WHERE id = ?");
$update->execute([$userId > 0 ? $userId : null, $farmId]);

if (!empty($farm['ownerId'])) {
    try {
        $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, farm_id, priority, is_read, created_at) VALUES (?, 'FARM_APPROVED', ?, ?, ?, 'Medium', 0, NOW())")
            ->execute([
                $farm['ownerId'],
                "✅ Farm Registration Approved",
                "Your farm application for \"" . $farm['name'] . "\" has been officially APPROVED by Department of Agriculture officials.",
                $farmId
            ]);
    } catch (Exception $eN) {}
}

if ($userId > 0) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $details = json_encode([
        'farm_name' => $farm['name'],
        'owner' => trim(($farm['firstName'] ?? '') . ' ' . ($farm['lastName'] ?? '')),
    ]);

    $log = $pdo->prepare("INSERT INTO audit_log (userId, action, tableName, recordId, ipAddress, details) VALUES (?, 'APPROVE_FARM', 'farms', ?, ?, ?)");
    $log->execute([$userId, $farmId, $ip, $details]);
}

respond(['success' => true, 'message' => 'Farm approved successfully', 'farm_id' => $farmId]);
