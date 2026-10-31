<?php
// api/appeal-farm.php
require_once __DIR__ . '/guard.php';
header('Content-Type: application/json');

if ($_SESSION['user_role'] !== 'Farmer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

try {
    $farmId = isset($_POST['farm_id']) ? (int)$_POST['farm_id'] : 0;
    
    // Validate ownership and status
    $stmt = $pdo->prepare("SELECT id FROM farms WHERE id=? AND ownerId=? AND status='Rejected'");
    $stmt->execute([$farmId, $_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        throw new Exception("Invalid farm or not eligible for appeal.");
    }
    
    // Update status to pending
    $update = $pdo->prepare("UPDATE farms SET status='Pending', rejection_reason=NULL WHERE id=?");
    $update->execute([$farmId]);
    
    echo json_encode(['success' => true]);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}