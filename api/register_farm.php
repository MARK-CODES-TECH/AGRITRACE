<?php
require_once __DIR__ . '/guard.php';

if ($_SESSION['user_role'] !== 'Farmer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId    = $_SESSION['user_id'] ?? 0;
$firstName = $_SESSION['firstName'] ?? 'Farmer';
$lastName  = $_SESSION['lastName']  ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_farm') {
    header('Content-Type: application/json');
    try {
        $name    = trim($_POST['farm_name']   ?? '');
        $address = trim($_POST['farm_address']?? '');
        $type    = $_POST['farm_type']  ?? 'Mixed';
        $area    = (float)($_POST['farm_area'] ?? 0);
        $lat     = !empty($_POST['farm_lat']) ? (float)$_POST['farm_lat'] : null;
        $lng     = !empty($_POST['farm_lng']) ? (float)$_POST['farm_lng'] : null;
        $ownerName = trim(($firstName??' ').' '.($lastName??''));

        if(!$name) throw new Exception('Farm name required');

        // Save photo if provided
        $photoPath = null;
        if(!empty($_POST['photo_data']) && strpos($_POST['photo_data'],'base64,') !== false) {
            $imgData = base64_decode(explode(',', $_POST['photo_data'])[1]);
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($finfo, $imgData);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowedMimes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format uploaded.']);
                exit;
            }

            @mkdir(__DIR__ . '/../uploads/farms', 0755, true);
            $photoPath = 'uploads/farms/farm_'.time().'_'.$userId.'.jpg';
            file_put_contents(__DIR__ . '/../' . $photoPath, $imgData);
        }

        $pdo->prepare("INSERT INTO farms(name,ownerId,ownerName,type,address,latitude,longitude,area,status,photo_url)
                        VALUES(?,?,?,?,?,?,?,?,'Pending',?)")
            ->execute([$name, $userId, $ownerName, $type, $address, $lat, $lng, $area, $photoPath]);
        $farmId = $pdo->lastInsertId();

        // Livestock
        $lType = $_POST['livestock_type'] ?? 'Cattle';
        $qty   = (int)($_POST['livestock_qty'] ?? 1);
        $tag   = $_POST['livestock_tag'] ?? 'TAG-' . strtoupper(substr($lType,0,3)) . '-' . str_pad($farmId, 4, '0', STR_PAD_LEFT);
        if(!$tag) $tag = 'TAG-' . strtoupper(substr($lType,0,3)) . '-' . str_pad($farmId, 4, '0', STR_PAD_LEFT);

        $pdo->prepare("INSERT INTO livestock(farmId,type,tagId,qty,healthStatus,vaccinationStatus,latitude,longitude)
                        VALUES(?,?,?,?,'Healthy','None',?,?)")
            ->execute([$farmId, $lType, $tag, $qty, $lat, $lng]);

        // Audit log
        $pdo->prepare("INSERT INTO audit_log(userId,action,tableName,recordId,details,ipAddress) VALUES(?,?,?,?,?,?)")
            ->execute([$userId, 'CREATE', 'farms', $farmId, json_encode(['farm_name'=>$name]), $_SERVER['REMOTE_ADDR']??'']);

        echo json_encode(['success' => true, 'farm_id' => $farmId, 'message' => "Farm '$name' registered! Awaiting approval."]);
    } catch(Exception $e) { 
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
    }
}
