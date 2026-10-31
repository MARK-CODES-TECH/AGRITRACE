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

require_once __DIR__ . '/../db_connect.php';

$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$sessionRole = (string)($_SESSION['user_role'] ?? '');

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];
if (stripos($contentType, 'application/json') !== false) {
    $decoded = json_decode(file_get_contents('php://input'), true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
} else {
    $input = $_POST;
}

$targetUserId = (int)($input['user_id'] ?? $sessionUserId);
if ($targetUserId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid user id']);
    exit;
}

if ($targetUserId !== $sessionUserId && $sessionRole !== 'Admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$allowedUserFields = ['firstName', 'lastName', 'mobile'];
$allowedProfileFields = ['gov_id', 'department', 'position', 'office', 'assigned_region', 'municipality', 'province'];

try {
    $profilePicPath = null;
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/profiles';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo((string)$_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExt, true)) {
            throw new RuntimeException('Invalid image format');
        }

        $fileName = $targetUserId . '_' . time() . '.' . $ext;
        $absolutePath = $uploadDir . '/' . $fileName;
        if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $absolutePath)) {
            throw new RuntimeException('Unable to save profile image');
        }

        $profilePicPath = 'uploads/profiles/' . $fileName;
    }

    $pdo->beginTransaction();

    $userSet = [];
    $userParams = [];
    foreach ($allowedUserFields as $field) {
        if (array_key_exists($field, $input)) {
            $userSet[] = "`{$field}` = ?";
            $userParams[] = $input[$field] !== '' ? $input[$field] : null;
        }
    }

    if (!empty($input['new_password'])) {
        $userSet[] = '`password` = ?';
        $userParams[] = password_hash((string)$input['new_password'], PASSWORD_DEFAULT);
    }

    if (!empty($userSet)) {
        $userSet[] = '`updatedAt` = CURRENT_TIMESTAMP';
        $sql = 'UPDATE users SET ' . implode(', ', $userSet) . ' WHERE id = ?';
        $userParams[] = $targetUserId;
        $stmtUser = $pdo->prepare($sql);
        $stmtUser->execute($userParams);
    }

    $profilePayload = [];
    foreach ($allowedProfileFields as $field) {
        if (array_key_exists($field, $input)) {
            $profilePayload[$field] = $input[$field] !== '' ? $input[$field] : null;
        }
    }
    if ($profilePicPath !== null) {
        $profilePayload['profile_pix'] = $profilePicPath;
    }

    if (!empty($profilePayload)) {
        $columns = array_keys($profilePayload);
        $insertCols = '`user_id`, `' . implode('`, `', $columns) . '`';
        $insertVals = rtrim(str_repeat('?,', count($columns) + 1), ',');

        $updateParts = [];
        foreach ($columns as $col) {
            $updateParts[] = "`{$col}` = VALUES(`{$col}`)";
        }
        $updateParts[] = '`updated_at` = CURRENT_TIMESTAMP';

        $sqlProfile = "INSERT INTO officer_profiles ({$insertCols}) VALUES ({$insertVals}) ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
        $stmtProfile = $pdo->prepare($sqlProfile);

        $profileParams = [$targetUserId];
        foreach ($columns as $col) {
            $profileParams[] = $profilePayload[$col];
        }
        $stmtProfile->execute($profileParams);
    }

    $audit = $pdo->prepare('INSERT INTO audit_log (userId, action, tableName, recordId, ipAddress, userAgent, details) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $audit->execute([
        $sessionUserId,
        'UPDATE',
        'users',
        $targetUserId,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null,
        json_encode(['source' => 'update_profile.php'], JSON_UNESCAPED_SLASHES),
    ]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
}
