<?php
// api/get_farm_livestock.php
// Returns livestock for a specific farm (used by Farm Review modal)
require_once __DIR__ . '/../init.php';

if (!isset($_SESSION['logged_in']) || !in_array($_SESSION['user_role']??'', ['Admin','Agriculture Official'])) {
    http_response_code(403);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$farm_id = (int)($_GET['farm_id'] ?? 0);
if (!$farm_id) { echo json_encode(['success'=>false,'message'=>'No farm_id']); exit; }

header('Content-Type: application/json');
try {
    $stmt = $pdo->prepare("SELECT id, type, tagId, breed, age, weight, qty, healthStatus, vaccinationStatus, notes FROM livestock WHERE farmId = ? ORDER BY type ASC");
    $stmt->execute([$farm_id]);
    echo json_encode(['success'=>true, 'livestock'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}