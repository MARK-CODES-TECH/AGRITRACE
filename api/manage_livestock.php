<?php
require_once __DIR__ . '/guard.php';

if ($_SESSION['user_role'] !== 'Farmer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_livestock') {
        try {
            $id = (int)$_POST['livestock_id'];
            $pdo->prepare("UPDATE livestock SET type=?,breed=?,age=?,weight=?,healthStatus=?,vaccinationStatus=?,qty=?,tagId=? WHERE id=?")
                ->execute([$_POST['type'], $_POST['breed']??null, $_POST['age']??null, $_POST['weight']??null,
                           $_POST['healthStatus'], $_POST['vaccinationStatus'], $_POST['qty'], $_POST['tagId'], $id]);
            echo json_encode(['success' => true]);
        } catch(Exception $e) { 
            echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
        }
        exit;
    }
    
    if ($_POST['action'] === 'add_livestock') {
        try {
            $farmId = (int)$_POST['farm_id'];
            // verify farm belongs to user
            $f = $pdo->prepare("SELECT id FROM farms WHERE id=? AND ownerId=?");
            $f->execute([$farmId, $userId]);
            if(!$f->fetch()) throw new Exception('Farm not found');
            
            $pdo->prepare("INSERT INTO livestock(farmId,type,tagId,breed,age,weight,qty,healthStatus,vaccinationStatus)
                            VALUES(?,?,?,?,?,?,?,'Healthy','None')")
                ->execute([$farmId, $_POST['type'], $_POST['tagId'], $_POST['breed']??null,
                           $_POST['age']??null, $_POST['weight']??null, $_POST['qty']??1]);
            echo json_encode(['success' => true]);
        } catch(Exception $e) { 
            echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
        }
        exit;
    }
}
