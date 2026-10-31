<?php
require_once __DIR__ . '/guard.php';

if ($_SESSION['user_role'] !== 'Farmer') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    header('Content-Type: application/json');
    try {
        $pdo->prepare("UPDATE users SET email=?,mobile=? WHERE id=?")
            ->execute([$_POST['email'], $_POST['mobile'], $userId]);
            
        $picPath = null;
        if (!empty($_FILES['profile_pix']['tmp_name'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['profile_pix']['tmp_name']);
            finfo_close($finfo);
            $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mime, $allowedMimes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid image format. Detected: ' . $mime]);
                exit;
            }

            $ext = strtolower(pathinfo($_FILES['profile_pix']['name'], PATHINFO_EXTENSION));
            $filename = "{$userId}_" . time() . ".{$ext}";
            $targetDir = __DIR__ . '/../uploads/profiles';
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            if (!move_uploaded_file($_FILES['profile_pix']['tmp_name'], $targetDir . '/' . $filename)) {
                echo json_encode(['success' => false, 'message' => 'Failed to save uploaded profile picture.']);
                exit;
            }
            $picPath = "uploads/profiles/{$filename}";
        }
        
        $stmt = $pdo->prepare("INSERT INTO farmer_profiles(user_id,gender,dob,address,emergency_contact".($picPath?",profile_pix":"").")
            VALUES(?,?,?,?,?".($picPath?",?":"").")
            ON DUPLICATE KEY UPDATE gender=VALUES(gender),dob=VALUES(dob),address=VALUES(address),
            emergency_contact=VALUES(emergency_contact)".($picPath?",profile_pix=VALUES(profile_pix)":""));
            
        $params = [$userId, $_POST['gender']??'', $_POST['dob']??'', $_POST['address']??'', $_POST['emergency_contact']??''];
        if($picPath) $params[] = $picPath;
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
    } catch(Exception $e) { 
        echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
    }
}
