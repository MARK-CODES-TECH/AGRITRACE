<?php
// api/chat.php — Enhanced Farmer Chat API with media support
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}
try {
    require __DIR__ . '/../db_connect.php';
} catch(PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'DB error']); exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ── Ensure messages table has media columns ────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `messages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `sender_id` int(11) NOT NULL,
        `receiver_id` int(11) NOT NULL,
        `message` text DEFAULT NULL,
        `media_url` varchar(500) DEFAULT NULL,
        `media_type` enum('image','video','audio','file') DEFAULT NULL,
        `media_thumb` varchar(500) DEFAULT NULL,
        `is_read` tinyint(1) DEFAULT 0,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_msg_sender` (`sender_id`),
        KEY `idx_msg_receiver` (`receiver_id`),
        KEY `idx_msg_convo` (`sender_id`,`receiver_id`),
        KEY `idx_msg_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch(Exception $e) {}

// ── Ensure webrtc_signals table ───────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `webrtc_signals` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `sender_id` INT NOT NULL,
        `receiver_id` INT NOT NULL,
        `payload` JSON NOT NULL,
        `consumed` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recv_consumed (receiver_id, consumed),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch(Exception $e) {}

header('Content-Type: application/json');

switch($action) {

    // ── List farmers & agriculture officials ──────────────────────────────
    case 'list_farmers':
    case 'list_contacts':
        $stmt = $pdo->prepare("
            SELECT u.id, u.firstName, u.lastName, u.role, u.mobile,
                op.position as officer_position,
                op.department as officer_dept,
                op.municipality as officer_muni,
                (SELECT COUNT(*) FROM messages m WHERE m.sender_id=u.id AND m.receiver_id=? AND m.is_read=0) AS unread,
                (SELECT m2.message FROM messages m2 
                 WHERE (m2.sender_id=u.id AND m2.receiver_id=?) OR (m2.sender_id=? AND m2.receiver_id=u.id)
                 ORDER BY m2.id DESC LIMIT 1) AS last_message,
                (SELECT m2.created_at FROM messages m2 
                 WHERE (m2.sender_id=u.id AND m2.receiver_id=?) OR (m2.sender_id=? AND m2.receiver_id=u.id)
                 ORDER BY m2.id DESC LIMIT 1) AS last_msg_time
            FROM users u
            LEFT JOIN officer_profiles op ON op.user_id = u.id
            WHERE u.id != ? AND u.role IN ('Farmer', 'Agriculture Official') AND u.status='Active'
            ORDER BY (last_msg_time IS NOT NULL) DESC, last_msg_time DESC, (u.role='Agriculture Official') DESC, u.firstName ASC
        ");
        $stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
        echo json_encode(['success'=>true,'farmers'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── Get messages ────────────────────────────────────────────────────────
    case 'get_messages':
        $otherId = (int)($_GET['other_id'] ?? 0);
        $lastId  = (int)($_GET['last_id'] ?? 0);
        // Mark as read
        $pdo->prepare("UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=? AND is_read=0")
            ->execute([$otherId,$userId]);
        $stmt = $pdo->prepare("
            SELECT m.*, u.firstName, u.lastName
            FROM messages m
            LEFT JOIN users u ON m.sender_id = u.id
            WHERE ((m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?))
              AND m.id > ?
            ORDER BY m.created_at ASC LIMIT 100
        ");
        $stmt->execute([$userId,$otherId,$otherId,$userId,$lastId]);
        echo json_encode(['success'=>true,'messages'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;

    // ── Send message (text or media) ────────────────────────────────────────
    case 'send_message':
        $input      = json_decode(file_get_contents('php://input'), true) ?? [];
        $receiverId = (int)($input['receiver_id'] ?? 0);
        $message    = trim($input['message'] ?? '');
        $mediaData  = $input['media_data'] ?? null;  // base64
        $mediaType  = $input['media_type'] ?? null;  // 'image' | 'video'

        if (!$receiverId) { echo json_encode(['success'=>false,'message'=>'No receiver']); exit; }
        if (!$message && !$mediaData) { echo json_encode(['success'=>false,'message'=>'Empty message']); exit; }

        $mediaUrl = null;
        if ($mediaData && strpos($mediaData,'base64,') !== false) {
            $ext = ($mediaType === 'video') ? 'mp4' : 'jpg';
            $chatDir = __DIR__ . '/../uploads/chat';
            if (!is_dir($chatDir)) {
                @mkdir($chatDir, 0755, true);
            }
            $filename = 'msg_' . time() . '_' . $userId . '.' . $ext;
            file_put_contents($chatDir . '/' . $filename, base64_decode(explode(',', $mediaData)[1]));
            $mediaUrl = 'uploads/chat/' . $filename;
        }

        $pdo->prepare("INSERT INTO messages(sender_id,receiver_id,message,media_url,media_type) VALUES(?,?,?,?,?)")
            ->execute([$userId,$receiverId,$message,$mediaUrl,$mediaType]);
        $msgId = $pdo->lastInsertId();
        echo json_encode(['success'=>true,'message_id'=>$msgId,'media_url'=>$mediaUrl]);
        break;

    // ── Unread count ────────────────────────────────────────────────────────
    case 'unread_count':
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read=0");
        $stmt->execute([$userId]);
        echo json_encode(['success'=>true,'count'=>(int)$stmt->fetchColumn()]);
        break;

    // ── WebRTC Signaling: Send signal ───────────────────────────────────────
    case 'signal_send':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $receiverId = (int)($body['receiver_id'] ?? 0);
        if (!$receiverId) { 
            echo json_encode(['success'=>false,'message'=>'No receiver']); 
            exit; 
        }
        $pdo->prepare("INSERT INTO webrtc_signals (sender_id, receiver_id, payload) VALUES (?, ?, ?)")
            ->execute([$userId, $receiverId, json_encode($body)]);
        // Cleanup old signals (> 2 min)
        $pdo->exec("DELETE FROM webrtc_signals WHERE created_at < NOW() - INTERVAL 2 MINUTE");
        echo json_encode(['success'=>true]);
        break;

    // ── WebRTC Signaling: Poll signals ─────────────────────────────────────
    case 'signal_poll':
        $stmt = $pdo->prepare("SELECT id, sender_id, payload FROM webrtc_signals
                               WHERE receiver_id=? AND consumed=0
                               ORDER BY created_at ASC LIMIT 20");
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $ids = implode(',', array_column($rows, 'id'));
            $pdo->exec("UPDATE webrtc_signals SET consumed=1 WHERE id IN ($ids)");
        }
        $signals = array_map(function($r) {
            $p = json_decode($r['payload'], true);
            $p['sender_id'] = $r['sender_id'];
            return $p;
        }, $rows);
        echo json_encode(['success'=>true, 'signals'=>$signals]);
        break;

    default:
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
?>