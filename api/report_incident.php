<?php
require_once __DIR__ . '/guard.php';

if ($_SESSION['user_role'] !== 'Farmer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'report_incident') {
    header('Content-Type: application/json');
    try {
        $type    = $_POST['incident_type'] ?? 'Others';
        $title   = trim($_POST['title']  ?? '');
        $desc    = trim($_POST['description'] ?? '');
        $farmId  = !empty($_POST['farm_id']) ? (int)$_POST['farm_id'] : null;
        $lat     = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
        $lng     = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $address = $_POST['incident_address'] ?? null;
        
        if(!$title || !$desc) throw new Exception('Title and description required');

        $photoPath = null;
        if(!empty($_POST['photo_data']) && strpos($_POST['photo_data'],'base64,') !== false){
            $imgData = base64_decode(explode(',', $_POST['photo_data'])[1]);
            
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_buffer($finfo, $imgData);
            finfo_close($finfo);
            
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowedMimes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format uploaded.']);
                exit;
            }

            @mkdir(__DIR__ . '/../uploads/incidents', 0755, true);
            $photoPath = 'uploads/incidents/inc_'.time().'_'.$userId.'.jpg';
            file_put_contents(__DIR__ . '/../' . $photoPath, $imgData);
        }

        $lvType   = $_POST['livestock_type'] ?? null;
        $symptoms = trim($_POST['symptoms'] ?? '');
        $severity = $_POST['severity'] ?? 'Medium';
        $priority = in_array($severity, ['Low', 'Medium', 'High', 'Critical'], true) ? $severity : 'Medium';

        $pdo->prepare("INSERT INTO incidents(farmId,reporterId,type,title,description,latitude,longitude,photoUrl,status,priority,livestock_type,symptoms,severity)
                        VALUES(?,?,?,?,?,?,?,?,'Pending',?,?,?,?)")
            ->execute([$farmId, $userId, $type, $title, $desc, $lat, $lng, $photoPath, $priority, $lvType, $symptoms ?: null, $severity]);
            
        $incId = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'incident_id' => $incId]);
    } catch(Exception $e) { 
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
    }
}
