<?php
require_once __DIR__ . '/../db_connect.php';

// Composer autoload for PHPMailer + Twilio
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

/* ========================================
   CONFIG — pulled from environment variables.
   Set these in your server config (Apache/Nginx vhost,
   .env + a loader, or php.ini) instead of hardcoding them.
   NEVER commit real credentials to source control.
   ======================================== */
define('MAIL_HOST',      getenv('MAIL_HOST')      ?: 'smtp.gmail.com');
define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
define('MAIL_USERNAME',  getenv('MAIL_USERNAME')  ?: 'vennethcuala@gmail.com');
define('MAIL_PASSWORD',  getenv('MAIL_PASSWORD')  ?: 'ooyq hkpl mnhc grcd');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'AgriTrace+');

define('TWILIO_SID',   getenv('TWILIO_SID')   ?: '');
define('TWILIO_TOKEN', getenv('TWILIO_TOKEN') ?: '');
define('TWILIO_FROM',  getenv('TWILIO_FROM')  ?: '');

function sendResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function isLoggedIn(): bool {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function requireRole(array $roles): void {
    if (!isLoggedIn()) {
        sendResponse(['error' => 'Authentication required'], 401);
    }
    $role = $_SESSION['user_role'] ?? '';
    if (!in_array($role, $roles, true)) {
        sendResponse(['error' => 'Access denied'], 403);
    }
}

function validateTable(string $table): bool {
    $allowed = ['users', 'farms', 'livestock', 'incidents', 'public_reports', 'audit_log', 'officer_permissions', 'officer_profiles'];
    return in_array($table, $allowed, true);
}

function decodeJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function parseSimpleWhere(string $where): array {
    $where = trim(urldecode($where));
    if ($where === '') {
        return ['', []];
    }

    if (preg_match('/(;|--|\/\*|\*\/|\bUNION\b|\bDROP\b|\bDELETE\b|\bINSERT\b|\bUPDATE\b|\bALTER\b)/i', $where)) {
        throw new InvalidArgumentException('Unsafe where clause');
    }

    $allowedFields = ['status', 'role', 'ownerId', 'type'];
    $parts = preg_split('/\s+AND\s+/i', $where);
    $clauses = [];
    $params = [];

    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }

        if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:\'([^\']*)\'|"([^\"]*)"|([0-9]+))$/', $part, $m)) {
            throw new InvalidArgumentException('Unsupported where format');
        }

        $field = $m[1];
        if (!in_array($field, $allowedFields, true)) {
            throw new InvalidArgumentException('Unsupported where field');
        }

        $value = $m[2] !== '' ? $m[2] : ($m[3] !== '' ? $m[3] : $m[4]);
        $clauses[] = "`{$field}` = ?";
        $params[] = ctype_digit((string)$value) ? (int)$value : $value;
    }

    if (empty($clauses)) {
        return ['', []];
    }

    return [implode(' AND ', $clauses), $params];
}

function getDateRangeFilter(string $range, string $column = 'createdAt'): array {
    $range = strtolower(trim($range));
    if ($range === '' || $range === 'all') {
        return ['', []];
    }

    if ($range === '30d') {
        return ["{$column} >= DATE_SUB(NOW(), INTERVAL 30 DAY)", []];
    }

    if ($range === '90d') {
        return ["{$column} >= DATE_SUB(NOW(), INTERVAL 90 DAY)", []];
    }

    if ($range === 'year') {
        return ["YEAR({$column}) = YEAR(CURDATE())", []];
    }

    return ['', []];
}

function fetchReportDataset(PDO $pdo, string $dataset, string $range): array {
    [$dateWhere, $dateParams] = getDateRangeFilter($range);

    $tables = [
        'farms' => ['createdAt', "SELECT id, name, type, status, createdAt, updatedAt FROM farms"],
        'livestock' => ['createdAt', "SELECT id, tagId, type, healthStatus, vaccinationStatus, createdAt, updatedAt FROM livestock"],
        'incidents' => ['createdAt', "SELECT id, title, type, status, priority, createdAt, updatedAt FROM incidents"],
        'audit' => ['createdAt', "SELECT al.id, al.action, al.tableName, al.createdAt, u.role FROM audit_log al LEFT JOIN users u ON al.userId = u.id"],
    ];

    if ($dataset === 'overview') {
        $overview = [];
        foreach (['farms', 'livestock', 'incidents', 'audit'] as $key) {
            [$col, $baseSql] = $tables[$key];
            [$where, $params] = getDateRangeFilter($range, $col);
            $sql = $baseSql . ($where !== '' ? " WHERE {$where}" : '') . ' ORDER BY createdAt DESC LIMIT 300';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['table'] = $key === 'audit' ? 'audit_log' : $key;
                $row['date'] = $row['createdAt'] ?? null;
            }
            unset($row);
            $overview = array_merge($overview, $rows);
        }

        usort($overview, static function (array $a, array $b): int {
            return strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? ''));
        });

        return array_slice($overview, 0, 600);
    }

    if (!isset($tables[$dataset])) {
        return [];
    }

    [$column, $baseSql] = $tables[$dataset];
    [$where, $params] = getDateRangeFilter($range, $column);
    $sql = $baseSql;
    if ($where !== '') {
        $sql .= " WHERE {$where}";
    }
    $sql .= ' ORDER BY createdAt DESC LIMIT 1000';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function convertRowsToCsv(array $rows): string {
    if (empty($rows)) {
        return "No records found\n";
    }

    $memory = fopen('php://temp', 'r+');
    $headers = array_keys($rows[0]);
    fputcsv($memory, $headers);
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $header) {
            $line[] = is_scalar($row[$header] ?? null) || $row[$header] === null
                ? ($row[$header] ?? '')
                : json_encode($row[$header]);
        }
        fputcsv($memory, $line);
    }
    rewind($memory);
    $content = stream_get_contents($memory);
    fclose($memory);
    return $content === false ? '' : $content;
}

function firstDistrictAddressCondition(string $alias): string {
    $address = "LOWER(COALESCE({$alias}.address, ''))";
    return "({$address} LIKE '%cabusao%'
        OR {$address} LIKE '%del gallego%'
        OR {$address} LIKE '%lupi%'
        OR {$address} LIKE '%ragay%'
        OR {$address} LIKE '%sipocot%')";
}

function firstDistrictCoordinateCondition(string $alias): string {
    return "({$alias}.latitude BETWEEN 13.645 AND 13.955 AND {$alias}.longitude BETWEEN 122.505 AND 123.180)";
}

function firstDistrictMunicipalitySql(string $alias): string {
    $address = "LOWER(COALESCE({$alias}.address, ''))";
    return "CASE
        WHEN {$address} LIKE '%cabusao%' THEN 'Cabusao'
        WHEN {$address} LIKE '%del gallego%' THEN 'Del Gallego'
        WHEN {$address} LIKE '%lupi%' THEN 'Lupi'
        WHEN {$address} LIKE '%ragay%' THEN 'Ragay'
        WHEN {$address} LIKE '%sipocot%' THEN 'Sipocot'
        ELSE '1st District'
    END";
}

/**
 * Send OTP via Email (PHPMailer)
 */
function sendEmailOtp(string $toEmail, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        // Recipients
        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your AgriTrace+ Verification Code';
        $mail->Body    = "
            <div style='font-family:DM Sans,Arial,sans-serif;max-width:480px;margin:0 auto;background:#f8fbfa;border-radius:16px;padding:36px;border:1px solid #d8e2de;'>
                <div style='text-align:center;margin-bottom:24px;'>
                    <span style='font-size:1.8rem;font-weight:800;color:#123f33;letter-spacing:-1px;'>🌿 AgriTrace+</span>
                </div>
                <h2 style='color:#12231d;font-size:1.3rem;margin-bottom:8px;'>Password Reset Code</h2>
                <p style='color:#5f6f68;font-size:0.95rem;line-height:1.5;margin-bottom:28px;'>
                    Use the verification code below to reset your password. It expires in <strong>10 minutes</strong>.
                </p>
                <div style='background:#123f33;border-radius:14px;padding:22px;text-align:center;margin-bottom:24px;'>
                    <span style='font-size:2.6rem;font-weight:800;color:#ffffff;letter-spacing:12px;'>{$otp}</span>
                </div>
                <p style='color:#9ca3af;font-size:0.8rem;line-height:1.5;'>
                    If you did not request this, you can safely ignore this email.
                    Do not share this code with anyone.
                </p>
                <hr style='border:none;border-top:1px solid #e5e7eb;margin:20px 0;'>
                <p style='color:#9ca3af;font-size:0.75rem;text-align:center;'>&copy; 2026 AgriTrace Technologies</p>
            </div>
        ";
        $mail->AltBody = "Your AgriTrace+ verification code is: {$otp}\nExpires in 10 minutes.";

        $mail->send();
        error_log("OTP Email sent to: $toEmail");
        return true;

    } catch (Exception $e) {
        error_log("PHPMailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send OTP via SMS (Twilio)
 */
function sendSmsOtp(string $toPhone, string $otp): bool {
    if (TWILIO_SID === '' || TWILIO_TOKEN === '') {
        error_log("Twilio not configured - skipping SMS");
        return false;
    }

    try {
        $phone = preg_replace('/\D/', '', $toPhone);
        if (str_starts_with($phone, '09')) {
            $phone = '+63' . substr($phone, 1);
        } elseif (str_starts_with($phone, '63')) {
            $phone = '+' . $phone;
        } elseif (!str_starts_with($phone, '+')) {
            $phone = '+63' . $phone;
        }

        $client = new \Twilio\Rest\Client(TWILIO_SID, TWILIO_TOKEN);
        $client->messages->create($phone, [
            'from' => TWILIO_FROM,
            'body' => "🌿 AgriTrace+ verification code: {$otp}\nExpires in 10 minutes. Do not share this code.",
        ]);

        error_log("OTP SMS sent to: $phone");
        return true;

    } catch (Exception $e) {
        error_log("Twilio Error: " . $e->getMessage());
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    sendResponse(['success' => true]);
}

$action = $_GET['action'] ?? '';
$table = $_GET['table'] ?? '';

if ($action === '') {
    sendResponse(['error' => 'Action is required'], 400);
}

$tableRequiredActions = ['getAll', 'getById', 'insert', 'update', 'delete', 'query'];
if (in_array($action, $tableRequiredActions, true)) {
    if ($table === '' || !validateTable($table)) {
        sendResponse(['error' => 'Invalid table'], 400);
    }
}

try {
    switch ($action) {
        case 'send-otp': {
            $identifier = trim($_GET['identifier'] ?? '');
            $type       = $_GET['type'] ?? (filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone');

            if ($identifier === '') {
                sendResponse(['error' => 'Identifier is required'], 400);
            }

            if ($type === 'email' && !filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
                sendResponse(['error' => 'Invalid email address'], 400);
            }

            // Check if user exists
            if ($type === 'email') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND status = 'Active'");
                $stmt->execute([$identifier]);
            } else {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE mobile = ? AND status = 'Active'");
                $stmt->execute([$identifier]);
            }

            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user) {
                error_log("No user found for {$identifier} ({$type})");
                sendResponse(['error' => 'No active account found for this email/number.'], 404);
            }

            // Generate & store OTP.
            // FIX: expiry is computed by MySQL (DATE_ADD(NOW(), ...)) instead of PHP's date().
            // This guarantees the stored expiry and the later "otpExpires > NOW()" check in
            // verify-otp are compared using the exact same clock/timezone, so the code can
            // never appear "already expired" due to a PHP vs. MySQL timezone mismatch.
            $otp     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = password_hash($otp, PASSWORD_DEFAULT);

            $update = $pdo->prepare(
                'UPDATE users SET otpCode = ?, otpExpires = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = ?'
            );
            $update->execute([$otpHash, (int)$user['id']]);

            // Send OTP
            $sent = false;
            if ($type === 'email') {
                $sent = sendEmailOtp($identifier, $otp);
            } else {
                $sent = sendSmsOtp($identifier, $otp);
            }

            if (!$sent) {
                sendResponse(['error' => 'Failed to send verification code. Please try again or contact support.'], 500);
            }

            sendResponse(['success' => true, 'message' => 'Verification code sent successfully! Check your ' . ($type === 'email' ? 'email' : 'SMS')]);
        }

        case 'verify-otp': {
            $identifier = trim($_GET['identifier'] ?? '');
            $type       = $_GET['type'] ?? (filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone');
            $code       = trim($_GET['code'] ?? '');

            if ($identifier === '' || $code === '') {
                sendResponse(['error' => 'Identifier and code are required'], 400);
            }

            if (!preg_match('/^\d{6}$/', $code)) {
                sendResponse(['error' => 'Invalid code format (6 digits)'], 400);
            }

            if ($type === 'email') {
                $stmt = $pdo->prepare(
                    "SELECT id, otpCode, otpExpires FROM users
                     WHERE email = ? AND status = 'Active'
                     AND otpExpires IS NOT NULL AND otpExpires > NOW()"
                );
                $stmt->execute([$identifier]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT id, otpCode, otpExpires FROM users
                     WHERE mobile = ? AND status = 'Active'
                     AND otpExpires IS NOT NULL AND otpExpires > NOW()"
                );
                $stmt->execute([$identifier]);
            }

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                sendResponse(['error' => 'Code has expired or invalid. Request a new one.'], 400);
            }

            if (!password_verify($code, $user['otpCode'])) {
                sendResponse(['error' => 'Incorrect verification code.'], 400);
            }

            // Clear OTP
            $pdo->prepare('UPDATE users SET otpCode = NULL, otpExpires = NULL WHERE id = ?')
                ->execute([(int)$user['id']]);

            // Generate reset token.
            // Same fix applied here: expiry computed by MySQL, not PHP's date().
            $token = bin2hex(random_bytes(32));
            $pdo->prepare(
                'UPDATE users SET resetToken = ?, resetExpires = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?'
            )->execute([$token, (int)$user['id']]);

            sendResponse(['success' => true, 'token' => $token]);
        }

        case 'reset-password': {
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                sendResponse(['error' => 'POST method required'], 405);
            }

            $body       = decodeJsonBody();
            $token      = trim($body['token'] ?? '');
            $identifier = trim($body['identifier'] ?? '');
            $newPass    = (string)($body['password'] ?? '');

            if ($token === '' || $identifier === '' || $newPass === '') {
                sendResponse(['error' => 'Token, identifier, and new password are required.'], 400);
            }

            if (strlen($newPass) < 8) {
                sendResponse(['error' => 'Password must be at least 8 characters long.'], 400);
            }

            // Verify resetToken is valid and not expired
            $stmt = $pdo->prepare(
                "SELECT id FROM users
                 WHERE (email = ? OR mobile = ?)
                   AND resetToken = ?
                   AND resetExpires IS NOT NULL
                   AND resetExpires > NOW()
                   AND status = 'Active'"
            );
            $stmt->execute([$identifier, $identifier, $token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                sendResponse(['error' => 'Reset token is invalid or has expired. Please request a new one.'], 400);
            }

            // Hash new password and clear resetToken
            $hashed = password_hash($newPass, PASSWORD_DEFAULT);
            $update = $pdo->prepare(
                "UPDATE users SET password = ?, resetToken = NULL, resetExpires = NULL, updatedAt = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $update->execute([$hashed, (int)$user['id']]);

            sendResponse(['success' => true, 'message' => 'Password reset successfully. You can now log in.']);
        }

        // ... [REST OF YOUR CASES REMAIN THE SAME - getFirstDistrictMapData, getFirstDistrictIncidents, etc.]

        case 'getFirstDistrictMapData': {
            $addressCondition = firstDistrictAddressCondition('f');
            $coordinateCondition = firstDistrictCoordinateCondition('f');
            $municipalitySql = firstDistrictMunicipalitySql('f');

            $stmt = $pdo->query("
                SELECT
                    f.id, f.name, f.ownerId, f.ownerName, f.type, f.address,
                    f.latitude, f.longitude, f.area, f.status, f.createdAt, f.updatedAt,
                    u.firstName, u.lastName, u.email, u.mobile,
                    {$municipalitySql} AS municipality,
                    COUNT(l.id) AS livestock_records,
                    COALESCE(SUM(l.qty), 0) AS total_livestock,
                    GROUP_CONCAT(DISTINCT l.type ORDER BY l.type SEPARATOR ', ') AS livestock_types
                FROM farms f
                LEFT JOIN users u ON f.ownerId = u.id
                LEFT JOIN livestock l ON l.farmId = f.id
                WHERE {$addressCondition} OR {$coordinateCondition}
                GROUP BY f.id, f.name, f.ownerId, f.ownerName, f.type, f.address, 
                         f.latitude, f.longitude, f.area, f.status, f.createdAt, f.updatedAt,
                         u.firstName, u.lastName, u.email, u.mobile
                ORDER BY FIELD(f.status, 'Approved', 'Pending', 'Rejected'), municipality, f.name
            ");

            sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

        case 'getFirstDistrictIncidents': {
            $farmAddressCondition = firstDistrictAddressCondition('f');
            $incidentCoordinateCondition = firstDistrictCoordinateCondition('i');
            $farmCoordinateCondition = firstDistrictCoordinateCondition('f');
            $farmMunicipalitySql = firstDistrictMunicipalitySql('f');

            $farmIncidents = $pdo->query("
                SELECT
                    i.id, 'Farm Incident' AS source, i.title, i.type, i.description,
                    i.priority, i.status, COALESCE(i.latitude, f.latitude) AS latitude,
                    COALESCE(i.longitude, f.longitude) AS longitude, i.createdAt,
                    {$farmMunicipalitySql} AS municipality, f.name AS farm_name
                FROM incidents i
                LEFT JOIN farms f ON i.farmId = f.id
                WHERE {$incidentCoordinateCondition}
                    OR {$farmAddressCondition}
                    OR {$farmCoordinateCondition}
            ")->fetchAll(PDO::FETCH_ASSOC);

            $publicReports = $pdo->query("
                SELECT
                    pr.id, 'Public Report' AS source,
                    CONCAT(pr.reportType, ' Report') AS title,
                    pr.reportType AS type, pr.description,
                    'Public' AS priority, pr.status,
                    pr.latitude, pr.longitude, pr.createdAt,
                    '1st District' AS municipality, NULL AS farm_name
                FROM public_reports pr
                WHERE " . firstDistrictCoordinateCondition('pr') . "
            ")->fetchAll(PDO::FETCH_ASSOC);

            sendResponse(['success' => true, 'data' => array_merge($farmIncidents, $publicReports)]);
        }

        case 'getAll': {
            $stmt = $pdo->query("SELECT * FROM `$table` ORDER BY id DESC");
            sendResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
        }

        case 'getById': {
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                sendResponse(['error' => 'ID required'], 400);
            }
            $stmt = $pdo->prepare("SELECT * FROM `$table` WHERE id = ?");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            sendResponse($result ?: ['error' => 'Not found'], $result ? 200 : 404);
        }

        case 'insert': {
            requireRole(['Admin', 'Agriculture Official', 'Farmer']);
            $data = decodeJsonBody();
            if (empty($data)) {
                sendResponse(['error' => 'Invalid data'], 400);
            }
            $columns = array_keys($data);
            $placeholders = array_map(static fn($key) => ':' . $key, $columns);
            $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            sendResponse(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
        }

        case 'update': {
            requireRole(['Admin', 'Agriculture Official', 'Farmer']);
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                sendResponse(['error' => 'ID required'], 400);
            }
            $data = decodeJsonBody();
            if (empty($data)) {
                sendResponse(['error' => 'Invalid data'], 400);
            }
            $setParts = [];
            $params = [];
            foreach ($data as $key => $value) {
                $setParts[] = "`$key` = :$key";
                $params[$key] = $value;
            }
            $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . ", updatedAt = CURRENT_TIMESTAMP WHERE id = :id";
            $params['id'] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            sendResponse(['success' => true, 'rowsAffected' => $stmt->rowCount()]);
        }

        case 'delete': {
            requireRole(['Admin', 'Agriculture Official']);
            $id = (int)($_GET['id'] ?? 0);
            if ($id <= 0) {
                sendResponse(['error' => 'ID required'], 400);
            }
            $stmt = $pdo->prepare("DELETE FROM `$table` WHERE id = ?");
            $stmt->execute([$id]);
            sendResponse(['success' => true, 'rowsAffected' => $stmt->rowCount()]);
        }

        // ... [Add other cases like query, getDashboardStats, etc. as needed]

        default:
            sendResponse(['error' => 'Invalid action'], 400);
    }
} catch (Throwable $e) {
    error_log("API Error: " . $e->getMessage());
    sendResponse(['error' => 'Server error occurred'], 500);
}