<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$role = (string)($_SESSION['user_role'] ?? '');
if (!in_array($role, ['Admin', 'Agriculture Official'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$incidentId = (int)($payload['incidentId'] ?? ($payload['id'] ?? 0));
if ($incidentId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'incidentId is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE incidents SET status = 'Resolved', resolvedAt = CURRENT_TIMESTAMP, updatedAt = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$incidentId]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Incident not found or already resolved']);
        exit;
    }

    // Notify the reporter
    $iStmt = $pdo->prepare("SELECT reporterId, title FROM incidents WHERE id = ?");
    $iStmt->execute([$incidentId]);
    $incInfo = $iStmt->fetch(PDO::FETCH_ASSOC);
    if ($incInfo && !empty($incInfo['reporterId'])) {
        try {
            $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, incident_id, priority, is_read, created_at) VALUES (?, 'INFO', ?, ?, ?, 'Medium', 0, NOW())")
                ->execute([
                    $incInfo['reporterId'],
                    "🛡️ Incident Resolved: " . substr($incInfo['title'], 0, 30),
                    "Your reported incident \"" . $incInfo['title'] . "\" has been investigated and marked as Resolved by the Department of Agriculture.",
                    $incidentId
                ]);
        } catch (Exception $eN) {}
    }

    $audit = $pdo->prepare('INSERT INTO audit_log (userId, action, tableName, recordId, ipAddress, userAgent, details) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $audit->execute([
        (int)($_SESSION['user_id'] ?? 0),
        'UPDATE',
        'incidents',
        $incidentId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode(['status' => 'Resolved'], JSON_UNESCAPED_SLASHES),
    ]);

    echo json_encode(['success' => true, 'message' => 'Incident marked as resolved']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to resolve incident']);
}
